<?php

class Cell {

    public $id;
    public $field;
    public $result;

    function __construct($id, $field, $result = null) {
        $this->id = $id;
        $this->field = $field;
        $this->result = $result;
    }
}