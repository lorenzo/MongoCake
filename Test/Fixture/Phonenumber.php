<?php
App::uses('CakeDocument', 'MongoCake.Model');

/** @EmbeddedDocument */
class Phonenumber extends CakeDocument {
    /** @String */
    private $phonenumber;

	public $useDbConfig = 'testMongo';

    public function __construct($phonenumber = null)
    {
        $this->phonenumber = $phonenumber;
    }

    public function setPhonenumber($phonenumber)
    {
        $this->phonenumber = $phonenumber;
    }

    public function getPhonenumber()
    {
        return $this->phonenumber;
    }

    public function __toString()
    {
        return $this->phonenumber;
    }
}