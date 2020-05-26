<?php

require_once('types/Gamer.php');
require_once('types/Cell.php');

class PiXO {

    /**
     * @var DB
     */
    private $db;

    const SIDE_X = 'X';
    const SIDE_O = 'O';
    const IMMERSION_LEVEL = 2;

    function __construct($db) {
        $this->db = $db;
    }

    private function createField() {
        return array(
            array(
                new Cell(1, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0))), 
                new Cell(2, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0))), 
                new Cell(3, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0)))
            ),
            array(
                new Cell(4, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0))), 
                new Cell(5, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0))), 
                new Cell(6, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0)))
            ),
            array(
                new Cell(7, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0))), 
                new Cell(8, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0))), 
                new Cell(9, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0)))
            )
        );
    }

    public function createGame($partyId) {
        // создать пустое игровое поле
        $field = serialize($this->createField()); // перевести его в строку
        $hash = md5($field); // получить хеш-сумму от этого поля
        $this->db->updateGame($partyId, $field, $hash); // записать в БД
    }

    public function getGame($party, $hash) {
        if ($party->hash != $hash) { 
            return array(
                'field' => unserialize($party->game),
                'hash' => $party->hash,
                'turn' => $party->turn,
                'gamers' => array(
                    'gamer1' => $this->db->getGamerById($party->user1_id),
                    'gamer2' => $this->db->getGamerById($party->user2_id)
                )
            );
        }
        return false;
    }

    // вернуть окончание игры
    public function getEndGame($party) {
        if ($party->winner_id) {
            $loserId = ($party->winner_id === $party->user1_id) ? $party->user2_id : $party->user1_id;
            return array(
                'endGame' => true,
                'status' => 'Победа!',
                'winner' => $this->db->getGamerById($party->winner_id)->login,
                'loser' => $this->db->getGamerById($party->loserId)->login
            );
        }
        return array(
            'endGame' => true,
            'status' => 'Ничья!'
        );
    }

    private function checkEmptyCells($cell) {
        for($i = 0; $i <= 2; $i++)
            for($j = 0; $j <= 2; $j++)
                if(!$cell[$i][$j]) return true;
        return false;
    }

    private function isSameValues($cell1, $cell2, $cell3) {
        return !!($cell1 === $cell2 && $cell2 === $cell3 && $cell1 === $cell3);
    }

    private function checkCell($cell, $y, $x, $value) {
        // сравнение значений в столбце
        if ($this->isSameValues($cell->field[$y][0], $cell->field[$y][1], $cell->field[$y][2])) {
            return $value;
        }
        // сравнение значений в строке
        if ($this->isSameValues($cell->field[0][$x], $cell->field[1][$x], $cell->field[2][$x])) {
            return $value;
        }
        // сравнение диагоналей
        // главная диагональ
        if ($y == $x) {
            if ($this->isSameValues($cell->field[0][0], $cell->field[1][1], $cell->field[2][2])) {
                return $value;
            }
        }

        // побочная диагональ
        if ($y + $x == 2) {
            if ($this->isSameValues($cell->field[0][2], $cell->field[1][1], $cell->field[2][0])) {
                return $value;
            }
        }

        if (!$this->checkEmptyCells($cell->field)) {
            return 'draw';
        }

        return null;
    }

    // игрок сходил как-то
    public function turn($party, $userId, $x, $y) {
        if ($userId === $party->turn) { // проверить, что ход игрока
            $field = unserialize($party->game);
            $r1 = floor($y/3);
            $r2 = floor($x/3);
            $r3 = floor($y - 3 * $r1);
            $r4 = floor($x - 3 * $r2);
            if (!$field[$r1][$r2]->result) { // проверить, что в малый квадрат можно ходить
                // проверить, что в ячейке ещё ничего нет
                if ($field[$r1][$r2]->field[$r3][$r4] !== $this::SIDE_X && 
                    $field[$r1][$r2]->field[$r3][$r4] !== $this::SIDE_O
                ) {
                    // совершить ход
                    $value = ($party->user1_id === $userId) ? $this::SIDE_X : $this::SIDE_O;
                    $field[$r1][$r2]->field[$r3][$r4] = $value;
                    // проверить на победу в ячейке
                    $field[$r1][$r2]->result = $this->checkCell($field[$r1][$r2], $r3, $r4, $value);
                    // проверить на победу в игре
                    $victory = $this->isVictory($field[$r1][$r2]->field, $value);
                    // записать данные в БД
                    $fieldStr = serialize($field); // перевести его в строку
                    $hash = md5($fieldStr); // получить хеш-сумму от этого поля
                    if ($victory) {
                        $this->db->updateGame($party->id, $fieldStr, $hash);
                        $this->db->gameVictory($party->id);
                        return true;
                    }
                    $this->db->updateGame($party->id, $fieldStr, $hash); // записать в БД
                    // поменять turn партии
                    ($userId == $party->user1_id) ? $this->db->updateTurn($party->id, $party->user2_id) :
                                                      $this->db->updateTurn($party->id, $party->user1_id);
                    return $this->db->rememberTheMove($party->id, $r2, $r1, $r4, $r3, $userId);
                }
            }
        }

        return false;
    }

    /**
     * Проверка на победу в игре
     *
     * @param array $field
     * @param string $value
     * @return bool
     */
    public function isVictory($field, $value)
    {
        $combsX = [];
        $combsY = [];

        foreach ($field as $y => $line) {
            foreach ($line as $x => $cell) {
                if ($cell === $value) {
                    $combsX[] = $x;
                    $combsY[] = $y;
                }
            }
        }

        $lineCombo = max($this->getLineCombo($combsX), $this->getLineCombo($combsY));
        if ($lineCombo === 3) {
            return true;
        }

        if ($this->getDiagonalCombo($field, $value)) {
            return true;
        }

        return false;
    }

    /**
     * Проверка комбинаций в линию
     *
     * @param array $combs
     * @return int
     */
    public function getLineCombo($combs)
    {
        $coords = [0, 0, 0];

        foreach ($combs as $combo) {
            $coords[$combo]++;
        }

        return max($coords);
    }

    /**
     * Проверка комбинаций по диагонали
     *
     * @param array $field
     * @param string $value
     * @return bool
     */
    public function getDiagonalCombo($field, $value)
    {
        if ($field[1][1] !== $value) {
            return false;
        }

        if ($field[0][0] === $value && $field[2][2] === $value) {
            return true;
        }

        if ($field[0][2] === $value && $field[2][0] === $value) {
            return true;
        }

        return false;
    }
    
    /**
     * Запуск ИИ для совершения хода
     *
     * @param StdClass $party
     * @param string $userId
     * @return bool
     */
    public function getAnAiMove($party, $userId)
    {
        $playground = unserialize($party->game);
        $progress = $this->db->getProgress($party->id, $userId);
        $x = $progress[0][0];
        $y = $progress[0][1];

        if ($playground[$y][$x]->result !== null) {
            $progress = $this->db->getProgress($party->id, 0);
            list($x, $y) = $this->winningMove($playground, $progress);
            return $this->turn($party, $party->user2_id, $x, $y);
        }

        $area = $this->getAttackZone($progress);
        if ($area) {
            $field = $playground[$area[1]][$area[0]]->field;
            list($x, $y) = $this->getAGoodMove($field, self::SIDE_X);
            list($x, $y) = $this->normalizeCoords($area[0], $area[1], $x, $y);
        } else {
            $progress = $this->db->getProgress($party->id, 0);
            list($x, $y) = $this->winningMove($playground, $progress);
        }

        return $this->turn($party, $party->user2_id, $x, $y);
    }

    /**
     * Проверка области для дальнейшей защиты
     *
     * @param array $progress
     * @return int[]|null
     */
    public function getAttackZone($progress)
    {
        $areas = [];

        foreach ($progress as $step) {
            $areas[] = $step[0] . $step[1];
        }

        if (!isset($areas[1])) {
            return null;
        }

        if ($areas[0] === $areas[1]) {
            return $progress[0];
        }

        if (!isset($areas[2])) {
            return null;
        }

        if ($areas[0] === $areas[2]) {
            return $progress[0];
        }

        return $areas[0];
    }

    /**
     * Защита зоны от проигрыша
     *
     * @param array $field
     * @param string $value
     * @return int[]
     */
    public function getAGoodMove($field, $value)
    {
        $emptyCells = $this->getEmptyCells($field);

        foreach ($emptyCells as $cell) {
            $tempField = $field;
            $tempField[$cell['y']][$cell['x']] = $value;
            if ($this->isVictory($tempField, $value)) {
                return [$cell['x'], $cell['y']];
            }
        }

        return $this->getRandCoords($field);
    }

    /**
     * Получение новых координат
     *
     * @param array $field
     * @return int[]
     */
    public function getRandCoords($field)
    {
        $flag = true;

        do {
            $x = rand(0, 2);
            $y = rand(0, 2);
            if ($field[$y][$x] == 0) {
                $flag = false;
            }
        } while ($flag);

        return [$x, $y];
    }

    /**
     * Получение пустых клеток
     *
     * @param $field
     * @return array
     */
    public function getEmptyCells($field)
    {
        $emptyCells = [];

        foreach ($field as $y => $line) {
            foreach ($line as $x => $cell) {
                if ($cell === 0) {
                    $emptyCells[] = ['x' => $x, 'y' => $y];
                }
            }
        }

        return $emptyCells;
    }

    /**
     * Расчет победной тактики
     *
     * @param array $playground
     * @param array $progress
     * @return int[]
     */
    public function winningMove($playground, $progress)
    {
        if (empty($progress)) {
            $ax = rand(0, 2);
            $ay = rand(0, 2);
            list($x, $y) = $this->getRandCoords($playground[$ax][$ay]->field);
            list($x, $y) = $this->normalizeCoords($ax, $ay, $x, $y);
            return [$x, $y];
        }

        $ax = $progress[0][0];
        $ay = $progress[0][1];
        return $this->getAGoodMove($playground[$ax][$ay]->field, self::SIDE_O);
    }

    /**
     * Костыльная нормализация координат
     *
     * @param int $ax
     * @param int $ay
     * @param int $x
     * @param int $y
     * @return array
     */
    public function normalizeCoords($ax, $ay, $x, $y)
    {
        $x = $ax * 3 + $x;
        $y = $ay * 3 + $y;

        return [$x, $y];
    }
}