<?php

include_once CakePlugin::path('MongoCake') . 'config' . DS . 'bootstrap.php';

App::uses('ConnectionManager', 'Model');

class CakeDocumentTest extends CakeTestCase {
	
	public function setUp() {
		App::build(array(
			'Model' => CakePlugin::path('MongoCake') . 'tests' . DS . 'Fixture' . DS
		), true);
		$this->User = ClassRegistry::init('User');
		ConnectionManager::create('mongoDefault', array(
			'datasource' => 'MongoCake.CakeMongoSource'
		));
	}

	public function tearDown() {
		$users = $this->User->getDocumentManager()->getRepository('User')->findAll();
		foreach ($users as $user) {
			$user->delete();
		}
		$this->User->getDocumentManager()->flush();
		App::build();
		ClassRegistry::flush();
		ConnectionManager::drop('mongoDefault');
		
	}

	public function testLoading() {
		$u = $this->User;
		$u->username = 'jose';
		$u->phonenumbers[] = new PhoneNumber('+5845559977');
		$u->phonenumbers[] = new PhoneNumber('graham number');
		$address = new Address();
		$address->city = 'New York';

		$u->address = $address;
		$u->save();
		$u->flush();

 		$dm = $this->User->getDocumentManager();
		$users = $dm->getRepository('User')->findBy(array('username' => 'jose'));
		$users->next();
		$user = $users->current();

		$this->assertEquals($user->username, 'jose');
		$this->assertEquals($user->phonenumbers[0]->phonenumber, '+5845559977');
		$this->assertEquals($user->phonenumbers[1]->phonenumber, 'graham number');
		$this->assertEquals($user->address->city, 'New York');

		$this->assertEquals($user['username'], 'jose');
		$this->assertEquals($user['PhoneNumber'][0]['phonenumber'], '+5845559977');
		$this->assertEquals($user['PhoneNumber'][1]['phonenumber'], 'graham number');
		$this->assertEquals($user['Address']['city'], 'New York');

		$user->delete();
		$this->User->flush();
	}
}