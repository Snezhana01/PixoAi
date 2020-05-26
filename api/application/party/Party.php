<?php

class Party {

    /**
     * @var DB
     */
    private $db;

    function __construct($db) {
        $this->db = $db;
    }

    public function getFreeUsers($userId) {
        return $this->db->getFreeUsers($userId);
    }

    public function newParty($userId1, $userId2) {
        $this->db->deleteOpenParties($userId1); // удалить все открытые вызовы $userId1
        $this->db->newParty($userId1, $userId2); // добавить новый вызов $userId1
        return true;
    }

    public function deleteOpenParties($userId)
    {
        $this->db->deleteOpenParties($userId);
    }

    public function newGameWithAi($userId)
    {
        $this->db->deleteOpenParties($userId);
        $this->db->newGameWithAi($userId);
        $this->db->acceptParty(0, 'game');
        return $this->db->getPartyByUserIdAndAi($userId, 0);
    }

    public function isParty($userId) {
        return (bool)$this->db->isParty($userId);
    }

    public function acceptParty($userId, $answer) {
        $status = ($answer === 'yes') ? 'game' : 'close';
        return $this->db->acceptParty($userId, $status);
    }

    public function isAcceptParty($userId) {
        return (bool)$this->db->isAcceptParty($userId);
    }

    public function getPartyByUser2Id($userId) {
        return $this->db->getPartyByUser2Id($userId);
    }

    public function getPartyByUserId($userId, $status) {
        return $this->db->getPartyByUserId($userId, $status);
    }
}