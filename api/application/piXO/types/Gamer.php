<?php

class Gamer {

    public $id;
    public $name;
    public $side;

    function __construct($id, $name, $side) {
        $this->id = $id;
        $this->name = $name;
        $this->side = $side;
    }
}