<?php
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
App::uses('CakeDocument', 'MongoCake.Model');

/** @ODM\Document */
class Account extends CakeDocument {
    /** @ODM\Id */
    private $id;

    /** @ODM\String */
    private $name;

	public static $useDbConfig = 'testMongo';
	public $validate = array(
		'name' => array(
			'fail' => array('rule' => array('shouldNotStartWithX'))
		)
	);

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

	public function shouldNotStartWithX() {
		if ($this->name[0] === 'X') {
			return 'The name should not start with X';
		}
		return true;
	}
}