<?php
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

App::uses('CakeDocument', 'MongoCake.Model');
App::uses('Address', 'Model');
App::uses('Account', 'Model');
App::uses('PhoneNumber', 'Model');

/** @ODM\Document */
class User extends CakeDocument {
    /** @ODM\Id */
    private $id;

    /** @ODM\String */
    private $username;

    /** @ODM\String */
    private $password;

	/** @ODM\Float */
	private $salary;

    /**
    * @ODM\HasOneEmbedded(targetDocument="Address", alias="Address")
    **/
    private $address;

    /** @ODM\HasOne(targetDocument="Account") */
    private $account;

    /**
    * @ODM\HasManyEmbedded(targetDocument="PhoneNumber", alias="PhoneNumber")
    **/
    private $phonenumbers;

    /**
    * @ODM\HasMany(targetDocument="Account", alias="SubAccount")
    **/
    private $subAccounts;

	/** @ODM\Date */
	public $created;

	/** @ODM\Date */
	public $modified;

	/** @ODM\Date */
	public $lastSeen;

	public static $useDbConfig = 'testMongo';

	public static $findMethods = array(
		'topPaid' => true,
		'lesserPaid' => array(
			'conditions' => array('salary <' => 102),
			'order' => array('salary' => 'asc')
		),
		'isUser1' => array(
			'conditions' => array('username' => 'User 1')
		)
	);

	public function __construct() {
		$this->phonenumbers = new \Doctrine\Common\Collections\ArrayCollection();
	}

	public function beforeSave($exists) {
		if ($exists && $this->getUsername() == 'jose sucks') {
			return false;
		}
		if ($exists && $this->getUsername() == 'jose rules') {
			$this->username .= ', it is true';
		}
		return true;
	}

	public function beforeValidate() {
		if ($this->username == 'thisshouldnotvalidate') {
			$this->invalidate('username', 'This is not good');
		}
		return true;
	}

    public function getId()
    {
        return $this->id;
    }

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function setPassword($password)
    {
        $this->password = md5($password);
    }

	public function getPassword()
    {
		return $this->password;
    }

    public function checkPassword($password)
    {
        return $this->password === md5($password) ? true : false;
    }

    public function setAddress(Address $address)
    {
        $this->address = $address;
    }

    public function getAddress()
    {
        return $this->address;
    }

    public function setAccount(Account $account)
    {
        $this->account = $account;
    }

    public function getAccount()
    {
        return $this->account;
    }

    public function addPhonenumber(Phonenumber $phonenumber)
    {
        $this->phonenumbers[] = $phonenumber;
    }

    public function getPhonenumbers()
    {
        return $this->phonenumbers;
    }

	public function getSubAccounts()
    {
        return $this->subAccounts;
    }

	public function setSalary($s) {
		$this->salary = $s;
	}

	public function getSalary() {
		return $this->salary;
	}

    public function __toString()
    {
        return $this->username;
    }

	protected static function _findTopPaid($status, $query, $args = array()) {
		if ($status == 'before') {
			$query->field('salary')->gt(100)->sort('salary', 'desc');
			if (!empty($args)) {
				$query['limit'] = array_shift($args);
			}
		}

		return $query;
	}
}