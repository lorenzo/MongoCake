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
		$manager = ConnectionManager::getDataSource('testMongo')->getSchemaManager();
		$manager->dropDocumentCollection('User');
		$manager->dropDocumentCollection('Account');
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
		$user = User::find($u->getId());
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
	
		$this->assertNull(User::find($user->getId()));
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
		$this->assertNull(User::find($user->getId()));
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

/**
 * Tests that set() function will also set properties on associated objects
 *
 * @return void
 */
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

		$data = array(
			'User' => array(
				'username' => 'larry'
			),
			'PhoneNumber' => array(
				array(
					'phonenumber' => '444-45676'
				),
				array(
					'phonenumber' => '1234-1234'
				)
			)
		);
		$user = new User();
		$user->set($data);
		$this->assertEquals('larry', $user->username);
		$this->assertEquals('444-45676', $user->phonenumbers[0]['phonenumber']);
		$this->assertEquals('1234-1234', $user->phonenumbers[1]['phonenumber']);
	}

/**
 * Tests that set() will also set properties on hasMany references
 *
 * @return void
 */
	public function testSetWithPreexistentHasMany() {
		$data = array(
			'User' => array(
				'username' => 'larry',
				'password' => '12345'
			),
			'PhoneNumber' => array(
				array(
					'phonenumber' => '444-45676'
				),
				array(
					'phonenumber' => '1234-1234'
				)
			)
		);
		$user = new User();
		$user->set($data);
		$user->save();
		$user->flush();

		$users = $user->getRepository()->findBy(array('username' => 'larry'));
		$users->next();
		$user = $users->current();

		$data['PhoneNumber'][0]['phonenumber'] = '555-4455';
		$data['PhoneNumber'][1]['phonenumber'] = '777-4455';
		$data['PhoneNumber'][2]['phonenumber'] = '1234-1234';
		$user->set($data);
		$this->assertEquals('555-4455', $user->phonenumbers[0]['phonenumber']);
		$this->assertEquals('777-4455', $user->phonenumbers[1]['phonenumber']);
		$this->assertEquals('1234-1234', $user->phonenumbers[2]['phonenumber']);
		$user->save();
		$user->flush();

		$data = array(
			'User' => array(),
			'SubAccount' => array(
				array('name' => 'First account'),
				array('name' => 'Second account'),
				array('name' => 'Third account'),
			)
		);
		$user->set($data, true);
		$this->assertEquals('First account', $user->subAccounts[0]['name']);
		$this->assertEquals('Second account', $user->subAccounts[1]['name']);
		$this->assertEquals('Third account', $user->subAccounts[2]['name']);
		$user->save();
		$user->flush();

		$users = $user->getRepository()->findBy(array('username' => 'larry'));
		$users->next();
		$user = $users->current();
		$this->assertEquals('First account', $user->subAccounts[0]['name']);
		$this->assertEquals('Second account', $user->subAccounts[1]['name']);
		$this->assertEquals('Third account', $user->subAccounts[2]['name']);

		$account1 = spl_object_hash($user->subAccounts[0]);
		$account2 = spl_object_hash($user->subAccounts[1]);
		$account3 = spl_object_hash($user->subAccounts[2]);
		$data = array(
			'User' => array(),
			'SubAccount' => array(
				array(
					'id' => $user->subAccounts[0]['id'],
					'name' => 'Modified First account'
				),
				array(
					'id' => $user->subAccounts[1]['id'],
					'name' => 'Modified Second account'
				),
				array(
					'id' => $user->subAccounts[2]['id'],
					'name' => 'Modified Third account'
				)
			)
		);


		$user->set($data, true);
		$this->assertEquals('Modified First account', $user->subAccounts[0]['name']);
		$this->assertEquals('Modified Second account', $user->subAccounts[1]['name']);
		$this->assertEquals('Modified Third account', $user->subAccounts[2]['name']);
		$this->assertEquals($data['SubAccount'][0]['id'], $user->subAccounts[0]['id']);
		$this->assertEquals($data['SubAccount'][1]['id'], $user->subAccounts[1]['id']);
		$this->assertEquals($data['SubAccount'][2]['id'], $user->subAccounts[2]['id']);

		$this->assertEquals($account1, spl_object_hash($user->subAccounts[0]));
		$this->assertEquals($account2, spl_object_hash($user->subAccounts[1]));
		$this->assertEquals($account3, spl_object_hash($user->subAccounts[2]));
		$user->save();
		$user->flush();


		$data = array(
			'User' => array(),
			'SubAccount' => array(
				array(
					'id' => $user->subAccounts[1]['id'],
					'name' => 'Altered Second account'
				),
				array(
					'id' => $user->subAccounts[2]['id'],
					'name' => 'Altered Third account'
				),
				array(
					'id' => $user->subAccounts[0]['id'],
					'name' => 'Altered First account'
				)
			)
		);
		$user->set($data, true);
		$this->assertEquals('Altered First account', $user->subAccounts[0]['name']);
		$this->assertEquals('Altered Second account', $user->subAccounts[1]['name']);
		$this->assertEquals('Altered Third account', $user->subAccounts[2]['name']);
		$this->assertEquals($data['SubAccount'][2]['id'], $user->subAccounts[0]['id']);
		$this->assertEquals($data['SubAccount'][0]['id'], $user->subAccounts[1]['id']);
		$this->assertEquals($data['SubAccount'][1]['id'], $user->subAccounts[2]['id']);
		$this->assertEquals($account1, spl_object_hash($user->subAccounts[0]));
		$this->assertEquals($account2, spl_object_hash($user->subAccounts[1]));
		$this->assertEquals($account3, spl_object_hash($user->subAccounts[2]));
		$user->save();
		$user->flush();

		$data = array(
			'User' => array(),
			'SubAccount' => array(
				array(
					'id' => $user->subAccounts[1]['id'],
					'name' => 'Modified Second account'
				),
				array(
					'name' => 'New Forth account'
				),
				array(
					'id' => $user->subAccounts[2]['id'],
					'name' => 'Modified Third account'
				),
				array(
					'id' => $user->subAccounts[0]['id'],
					'name' => 'Modified First account'
				)
			)
		);
		$user->set($data, true);
		$this->assertEquals('Modified First account', $user->subAccounts[0]['name']);
		$this->assertEquals('Modified Second account', $user->subAccounts[1]['name']);
		$this->assertEquals('Modified Third account', $user->subAccounts[2]['name']);
		$this->assertEquals('New Forth account', $user->subAccounts[3]['name']);
		$this->assertEquals($data['SubAccount'][3]['id'], $user->subAccounts[0]['id']);
		$this->assertEquals($data['SubAccount'][0]['id'], $user->subAccounts[1]['id']);
		$this->assertEquals($data['SubAccount'][2]['id'], $user->subAccounts[2]['id']);
		$this->assertEquals($account1, spl_object_hash($user->subAccounts[0]));
		$this->assertEquals($account2, spl_object_hash($user->subAccounts[1]));
		$this->assertEquals($account3, spl_object_hash($user->subAccounts[2]));
	}

/**
 * Tests that save uses sets recursively the validation errors for all associated documents
 *
 * @return void
 */
	public function testSaveWithValidationErrors() {
		$data = array(
			'User' => array(
				'username' => 'larry',
				'password' => '12345'
			),
			'PhoneNumber' => array(
				array(
					'phonenumber' => '00444-45676'
				),
				array(
					'phonenumber' => '001234-1234'
				)
			),
			'SubAccount' => array(
				array('name' => 'X First account'),
				array('name' => 'X Second account'),
				array('name' => 'X Third account'),
			),
			'Account' => array(
				'name' => 'X Primary Account'
			)
		);
		$user = new User();
		$user->validate = array(
			'username' => array('fail' => array('rule' => array('custom', '/jose/'), 'message' => 'only jose is allowed')),
		);
		$this->assertFalse($user->save($data));
		$this->assertEquals(array('only jose is allowed'), $user->validationErrors['username']);

		$expected = array('name' => array('The name should not start with X'));
		$this->assertEquals(array_fill(0, 3, $expected), $user->validationErrors['SubAccount']);
		$this->assertEquals($expected, $user->validationErrors['Account']);

		$expected = array('phonenumber' => array('The number should not start with 00'));
		$this->assertEquals(array_fill(0, 2, $expected), $user->validationErrors['PhoneNumber']);

		$data = array(
			'User' => array(
				'username' => 'larry',
				'password' => '12345'
			),
			'PhoneNumber' => array(
				array(
					'phonenumber' => '444-45676'
				),
				array(
					'phonenumber' => '1234-1234'
				)
			),
			'SubAccount' => array(
				array('name' => 'First account'),
				array('name' => 'Second account'),
				array('name' => 'Third account'),
			),
			'Account' => array(
				'name' => 'Primary Account'
			)
		);
		$user = new User();
		$this->assertTrue($user->save($data));
		$this->assertEquals(array(), $user->validationErrors);
	}

	public function testFindAllWithConditions() {
		for ($i = 0; $i < 3; $i++) {
			$u = new User();
			$u->save(array(
				'User' => array(
					'username' => 'User ' . $i,
					'password' => 'password ' . $i,
					'salary' => 100 + $i
				),
				'SubAccount' => array(
					array('name' => 'Sub Account ' . $i),
					array('name' => 'Second Sub Account ' . $i),
				),
				'PhoneNumber' => array(
					array('phonenumber' => '555-000' . $i),
					array('phonenumber' => '333-000' . $i)
				)
			));
		}
		$this->User->flush();
		$users = User::find('all');
		$this->assertEquals(3, count($users));

		$users = User::find('all', array(
			'conditions' => array('username' => 'User 1')
		));
		$this->assertEquals(1, count($users));

		$users = User::find('all', array(
			'conditions' => array(
				'username' => 'User 1',
				'password' => md5('password 1')
			)
		));

		$this->assertEquals(1, count($users));
		$this->assertEquals('User 1', $users->getSingleResult()->username);

		$users = User::find('all', array(
			'conditions' => array(
				'username' => 'User 1',
				'password' => 'nonsense'
			)
		));
		$this->assertEquals(0, count($users));

		$users = User::find('all', array(
			'conditions' => array(
				'salary >' => 100
			)
		));
		$this->assertEquals(2, count($users));

		$users = User::find('all', array(
			'conditions' => array(
				'salary >=' => 100
			)
		));
		$this->assertEquals(3, count($users));

		$users = User::find('all', array(
			'conditions' => array(
				'salary <' => 101
			)
		));
		$this->assertEquals(1, count($users));

		$users = User::find('all', array(
			'conditions' => array(
				'salary <=' => 101
			)
		));
		$this->assertEquals(2, count($users));

		$users = User::find('all', array(
			'conditions' => array(
				'salary between' => array(100, 102)
			)
		));
		$this->assertEquals(2, count($users));

		$users = User::find('all', array(
			'conditions' => array(
				'username !=' => 'User 1'
			)
		));
		$this->assertEquals(2, count($users));

		$users = User::find('all', array(
			'conditions' => array(
				'username' => array('User 1', 'User 2')
			)
		));
		$this->assertEquals(2, count($users));

		$users = User::find('all', array(
			'conditions' => array(
				'username !=' => array('User 1', 'User 2')
			)
		));
		$this->assertEquals(1, count($users));
		$subAccount = $users->getSingleResult()->subAccounts[0];

		$users = User::find('all')->field('subAccounts')->includesReferenceTo($subAccount);
		$this->assertEquals(1, count($users));
		$this->assertEquals('User 0', $users->getSingleResult()->getUsername());

		$users = User::find('all')
			->field('phonenumbers.phonenumber')->in(array('555-0000'));
		$this->assertEquals(1, count($users));
		$this->assertEquals('User 0', $users->getSingleResult()->getUsername());

		$users = User::find('all', array(
			'conditions' => array(
				'phonenumbers.phonenumber' => array('555-0001')
			)
		));
		$this->assertEquals(1, count($users));
		$this->assertEquals('User 1', $users->getSingleResult()->getUsername());
	}

	public function testFindAllChainingConditions() {
		for ($i = 0; $i < 3; $i++) {
			$u = new User();
			$u->save(array(
				'User' => array(
					'username' => 'User ' . $i,
					'password' => 'password ' . $i,
					'salary' => 100 + $i
				)
			));
		}
		$this->User->flush();

		$users = User::find('all');
		$this->assertEquals(3, count($users));

		$users->field('username')->equals('User 2');
		$this->assertEquals(1, count($users));
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
