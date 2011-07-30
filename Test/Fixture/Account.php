<?php
App::uses('CakeDocument', 'MongoCake.Model');

/** @Document(collection="accounts") */
class Account extends CakeDocument {
    /** @Id */
    private $id;

    /** @String */
    private $name;

	public $useDbConfig = 'testMongo';

    public function __construct($name = null)
    {
        $this->name = $name;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function __toString()
    {
        return $this->name;
    }
}