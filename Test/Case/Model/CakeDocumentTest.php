<?php

include_once CakePlugin::path('MongoCake') . 'Config' . DS . 'bootstrap.php';

App::uses('ConnectionManager', 'Model');

class CakeDocumentTest extends CakeTestCase {
	
	public function setUp() {
		App::build(array(
			'Model' => CakePlugin::path('MongoCake') . 'Test' . DS . 'Fixture' . DS
		), APP::RESET);
		ConnectionManager::create('testMongo', array(
			'datasource' => 'MongoCake.CakeMongoSource',
			'database' => 'test'
		));
		$this->User = ClassRegistry::init('User');
	}

	public function tearDown() {
		ConnectionManager::getDataSource('testMongo')
			->getSchemaManager()
			->dropDocumentCollection('User');
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

/**
 * Tests that it is possible to cancel an update operation on beforeSave() and change
 * the data
 *
 * @return void
 */
	public function testBeforeUpdate() {
		$u = $this->User;
		$u->setUsername('larry');
		$u->save();
		$u->flush();

		// the callback is configured to return false if the name is jose sucks
		$u->setUsername('jose sucks');
		$u->save();
		$u->flush();

		$users = $u->getRepository()->findBy(array('username' => 'larry'));
		$users->next();
		$user = $users->current();
		$this->assertEquals($user->getId(), $u->getId());

		$u->setUsername('jose rules');
		$u->save();
		$u->flush();

		//The beforeSave callback should have changed the name
		$this->assertEquals($u->getUsername(), 'jose rules, it is true');
		$user = $this->User->find($u->getId());
	}

/**
 * Tests that beforeSave() is called for new persisted objects
 *
 * @return void
 */
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
 * Tests that afterSave method is invoked after flushing the unit of work
 *
 * @return void
 */
	public function testAfterSave() {
		$user = $this->_mockDocument('User', array('afterSave'));
		$user->expects($this->at(0))->method('afterSave')->with(false);
		$user->expects($this->at(1))->method('afterSave')->with(true);
		$user->setUsername('graham');
		$user->save();
		$user->flush();

		$user->setUsername('andy');
		$user->setPassword('1234');
		$user->save();
		$user->flush();
	}

/**
 * Tests that it is possible to cancel a delete operation in the beforeDelete callback
 *
 * @return void
 */
	public function testBeforeDelete() {
		$user = $this->_mockDocument('User', array('beforeDelete'));
		$user->expects($this->at(0))->method('beforeDelete')->will($this->returnValue(false));
		$user->expects($this->at(1))->method('beforeDelete')->will($this->returnValue(true));
		$user->setUsername('graham');
		$user->save();
		$user->flush();

		$user->delete();
		$user->flush();

		$user->delete();
		$user->flush();
	
		$this->assertNull($this->User->find($user->getId()));
	}

/**
 * Tests that afterDelete callback is reached
 *
 * @return void
 */
	public function testAfterDelete() {
		$user = $this->_mockDocument('User', array('afterDelete'));
		$user->expects($this->once())->method('afterDelete');
		$user->setUsername('graham');
		$user->save();
		$user->flush();
		$user->delete();
		$user->flush();
		$this->assertNull($this->User->find($user->getId()));
	}

/**
 * Tests that validates behaves as a normal CakePHP model
 *
 * @return void
 */
	public function testValidation() {
		$user = new User();
		$user->validate = array(
			'username' => array(
				'notEmpty' => array(
					'rule' => array('notEmpty'),
					'message' => 'The username is required',
					'required' => true
				),
				'crazy' => array(
					'rule' => array('custom', '/abc$/'),
					'on' => 'update',
					'message' => 'if updating it should end with abc'
				)
			),
			'password' => array(
				'length' => array(
					'rule' => array('between', 6, 100),
					'required' => true,
					'message' => 'The password is required',
				)
			)
		);
		$this->assertFalse($user->validates());
		$this->assertEquals(array('The username is required'), $user->validationErrors['username']);
		$this->assertEquals(array('The password is required'), $user->validationErrors['password']);

		$user->validationErrors = array();
		$user->setUsername('jose');
		$this->assertFalse($user->validates());
		$this->assertFalse(isset($user->validationErrors['username']));
		$this->assertEquals(array('The password is required'), $user->validationErrors['password']);

		$user->validationErrors = array();
		$user->setUsername('jose');
		$this->assertFalse($user->validates());
		$this->assertFalse(isset($user->validationErrors['username']));
		$this->assertEquals(array('The password is required'), $user->validationErrors['password']);

		$user->validationErrors = array();
		$user->setUsername('jose');
		$user->setPassword('jose12345');
		$this->assertTrue($user->validates());
		$this->assertEmpty($user->validationErrors);

		$this->assertTrue($user->save());
		$user->setUsername('not Jose');
		$this->assertFalse($user->validates());
		$this->assertEquals(array('if updating it should end with abc'), $user->validationErrors['username']);

		$user->validationErrors = array();
		$user->setUsername('joseabc');
		$this->assertTrue($user->validates());
		$this->assertEmpty($user->validationErrors);
	}

/**
 * Tests that validates triggers the beforeValidate callback
 *
 * @return void
 */
	public function testValidationBeforeCallback() {
		$user = new User();
		$user->validate = array(
			'username' => array(
				'notEmpty' => array(
					'rule' => array('notEmpty'),
					'message' => 'The username is required',
					'required' => true
				)
			),
			'password' => array(
				'length' => array(
					'rule' => array('between', 6, 100),
					'required' => true,
					'message' => 'The password is required',
				)
			)
		);
		$user->setUsername('thisshouldnotvalidate');
		$this->assertFalse($user->validates());
		$this->assertEquals(array('This is not good'), $user->validationErrors['username']);
	}

/**
 * Tests that CakeDocument::set() will call set[Property] for each key on the array that is not managed by
 * another document
 *
 * @return void
 */
	public function testSet() {
		$data = array(
			'username' => 'jose',
			'password' => '12345'
		);
		$user = new User();
		$user->set($data);
		$this->assertEquals('jose', $user->getUsername());
		$this->assertEquals(md5('12345'), $user->getPassword());

		$data = array(
			'User' => array(
				'username' => 'jose',
				'password' => '12345'
			)
		);
		$user = new User();
		$user->set($data);
		$this->assertEquals('jose', $user->getUsername());
		$this->assertEquals(md5('12345'), $user->getPassword());

		$data = array(
			'User' => array(
				'account' => 'jose',
				'phonenumber' => '12345',
			)
		);
		$user = new User();
		$user->set($data);
		$this->assertNull($user->getUsername());
		$this->assertNull($user->getPassword());
		$this->assertNull($user->getAccount());
		$this->assertEquals(new \Doctrine\Common\Collections\ArrayCollection(), $user->getPhonenumbers());
	}

/**
 * Test that getAssociated can translate document mappings into well know CakePHP association names
 *
 */
	public function testGetAssociated() {
		$user = new User();
		$result = $user->getAssociated();
		$this->assertEquals('hasOne', $result['Address']['type']);
		$this->assertTrue($result['Address']['embedded']);
		$this->assertEquals('hasOne', $result['Account']['type']);
		$this->assertTrue($result['Account']['reference']);
		$this->assertEquals('hasMany', $result['PhoneNumber']['type']);
		$this->assertTrue($result['PhoneNumber']['embedded']);

		$number = new PhoneNumber();
		$expected = array('OwningUser' => array('type' => 'belongsTo'));
		$result = $number->getAssociated();
		$this->assertEquals('belongsTo', $result['OwningUser']['type']);
		$this->assertTrue($result['OwningUser']['reference']);
	}

	public function testSetWitAssociations() {
		$data = array(
			'User' => array(
				'username' => 'jose',
			),
			'Account' => array(
				'name' => 'My Account name'
			),
			'Address' => array(
				'state' => 'California',
				'city' => 'Los Angeles',
				'street' => '154 NW',
				'postalCode' => '90210'
			)
		);
		$user = new User();
		$user->set($data);
		$this->assertEquals('jose', $user->username);
		$this->assertEquals('My Account name', $user->account->name);
		$this->assertEquals('California', $user->address->state);
		$this->assertEquals('Los Angeles', $user->address->city);
		$this->assertEquals('154 NW', $user->address->street);
		$this->assertEquals('90210', $user->address->postalCode);
	}

/**
 * Returns a mocked document class, and sets the metadata in the driver for the new document
 *
 * @param $class The name of the Document to mock
 * @param $methods list of methods to be mock, if left empty all methods will be mocked
 * @param $setMetaData if true it will set the driver metadata for the class by copying it form the original class
 */
	protected function _mockDocument($class, $methods = array(), $setMetaData = true, $id = null) {
		$mock = $this->getMock($class, $methods);
		if ($setMetaData) {
			$mf = $mock->getDocumentManager()->getMetadataFactory();
			$cm = $mf->getMetadataFor($class);
			$cm->rootDocumentName = $cm->name = get_class($mock);
			$mf->setMetadataFor(get_class($mock), $cm);

			//Not fully working yet
			if ($id) {
				$cm->setIdentifierValue($mock, $id);
				$mock->getDocumentManager()->getUnitOfWork()->registerManaged($mock, $id, array());
			}
		}
		return $mock;
	}

}
