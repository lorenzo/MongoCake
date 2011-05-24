<?php

include_once CakePlugin::path('MongoCake') . 'Config' . DS . 'bootstrap.php';

App::uses('ConnectionManager', 'Model');

class CakeDocumentTest extends CakeTestCase {
	
	public function setUp() {
		App::build(array(
			'Model' => CakePlugin::path('MongoCake') . 'Test' . DS . 'Fixture' . DS
		), true);
		ConnectionManager::create('testMongo', array(
			'datasource' => 'MongoCake.CakeMongoSource',
			'database' => 'test'
		));
		$this->User = ClassRegistry::init('User');
		$this->User->useDbConfig = 'testMongo';
	}

	public function tearDown() {
		App::build();
		ClassRegistry::flush();
		ConnectionManager::drop('testMongo');
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

	public function testBeforeCreate() {
		$user = $this->_mockDocument('User', array('beforeSave'));
		$user->expects($this->once())
			->method('beforeSave')
			->with(false)
			->will($this->returnValue(true));

		$user->username = 'larry';
		$result = $user->save();
		$this->assertTrue($result);

		$user = $this->_mockDocument('User', array('beforeSave'));
		$user->expects($this->once())
			->method('beforeSave')
			->with(false)
			->will($this->returnValue(false));

		$user->setUsername('larry');
		$result = $user->save();
		$this->assertFalse($result);
	}

/**
 * Tests that it is possible to cancel an update operation on beforeSave() and change
 * the data
 *
 * @return void
 */
	public function testBeforeUpdate() {
		$u = new User();
		$u->setUsername('larry');
		$u->save();
		$u->flush();

		// the callback is configured to return false if the name is jose sucks
		$u->setUsername('jose sucks');
		$u->save();
		$u->flush();

		$user = $this->User->find($u->getId());
		$this->assertEquals($user->getUsername(), 'larry');
		
		$u->setUsername('jose rules');
		$u->save();
		$u->flush();

		//The beforeSave callback should have changed the name
		$this->assertEquals($u->getUsername(), 'jose rules, it is true');
		$user = $this->User->find($u->getId());
	}

/**
 * Returns a mocked document class, and sets the metadata in the driver for the new document
 *
 * @param $class The name of the Document to mock
 * @param $methods list of methods to be mock, if left empty all methods will be mocked
 * @param $setMetaData if true it will set the driver metadata for the class by copying it form the original class
 */
	protected function _mockDocument($class, $methods = array(), $setMetaData = true) {
		$mock = $this->getMock($class, $methods);
		if ($setMetaData) {
			$mf = $mock->getDocumentManager()->getMetadataFactory();
			$mf->setMetadataFor(get_class($mock), $mf->getMetadataFor($class));
		}
		return $mock;
	}

}
