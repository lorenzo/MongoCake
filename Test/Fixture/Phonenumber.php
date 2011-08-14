<?php
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
App::uses('CakeDocument', 'MongoCake.Model');

/** @ODM\EmbeddedDocument */
class Phonenumber extends CakeDocument {

	/** @ODM\BelongsTo(targetDocument="User", inversedBy="phonenumbers", alias="OwningUser") */
	private $user;

    /** @ODM\String */
    private $phonenumber;

	/** @ODM\Date */
	public $created;

	/** @ODM\Date */
	public $modified;

	public static $useDbConfig = 'testMongo';
	public $validate = array(
		'phonenumber' => array(
			'fail' => array('rule' => array('shouldNotStartWith00'))
		)
	);

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

	public function shouldNotStartWith00() {
		if ($this->phonenumber[0] === '0' && $this->phonenumber[0] === '0') {
			return 'The number should not start with 00';
		}
		return true;
	}
}