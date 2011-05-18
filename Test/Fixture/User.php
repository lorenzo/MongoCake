<?php
App::uses('CakeDocument', 'MongoCake.Model');
App::uses('Address', 'Model');
App::uses('Account', 'Model');
App::uses('PhoneNumber', 'Model');

/** @Document */
class User extends CakeDocument {
    /** @Id */
    private $id;

    /** @String */
    private $username;

    /** @String */
    private $password;

    /** @EmbedOne(targetDocument="Address") */
    private $address;

    /** @ReferenceOne(targetDocument="Account") */
    private $account;

    /** @EmbedMany(targetDocument="PhoneNumber") */
    private $phonenumbers;

	public function __construct() {
		$this->phonenumbers = new \Doctrine\Common\Collections\ArrayCollection();
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

    public function __toString()
    {
        return $this->username;
    }
}