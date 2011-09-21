<?php
App::uses('ConnectionManager', 'Model');
App::uses('DocumentTestFixture', 'MongoCake.TestSuite/Fixture');

class MongoCakeUserTestFixture extends DocumentTestFixture {

	public $document = 'User';
	public $records = array(
		array('username' => 'James', 'password' => '12345', 'salary' => 1000),
		array(
			'User' => array('username' => 'Josie', 'password' => '123456', 'salary' => 1000, 'lastSeen' => '2011-01-01'),
			'PhoneNumber' => array(
				array(
					'phonenumber' => '444-45676'
				),
				array(
					'phonenumber' => '1234-1234'
				)
			)
		)
	);
}

class DocumentTestFixtureTest extends CakeTestCase {

	public function setUp() {
		App::build(array(
			'Model' => CakePlugin::path('MongoCake') . 'Test' . DS . 'Fixture' . DS
		), APP::RESET);
		$this->connection = ConnectionManager::create('testMongoFixture', array(
			'datasource' => 'MongoCake.CakeMongoSource',
			'database' => 'test'
		));
	}

	public function tearDown() {
		$manager = ConnectionManager::getDataSource('testMongoFixture')->getSchemaManager();
		$manager->dropDocumentCollection('User');
		$manager->dropDocumentCollection('Account');
		App::build();
		ClassRegistry::flush();
		ConnectionManager::drop('testMongoFixture');
	}

	public function testInit() {
		$fixture = new MongoCakeUserTestFixture($this->connection);
		$this->assertEquals('MongoCakeUserTest', $fixture->name);
		$this->assertEquals('User', $fixture->document);
		$this->assertEquals('testMongoFixture', User::$useDbConfig);
	}

	public function testCreate() {
		$Fixture = new MongoCakeUserTestFixture($this->connection);
		$return = $Fixture->create($this->connection);
		$this->assertTrue($return);
		$this->assertEquals(0, $this->connection->getDocumentManager()->getUnitOfWork()->size());
	}

	public function testInsert() {
		$this->assertEquals(0, User::find('all')->count());
		$Fixture = new MongoCakeUserTestFixture($this->connection);
		$Fixture->insert($this->connection);
		$this->connection->commit();
		$this->assertEquals(2, User::find('all')->count());
		$second = User::find('first', array('conditions' => array('username' => 'Josie')));
		$this->assertEquals('Josie', $second->username);
		$this->assertEquals(md5('123456'), $second->password);
		$this->assertEquals(1000, $second->salary);
		$this->assertEquals(new DateTime('2011-01-01'), $second->lastSeen);
		$this->assertEquals('444-45676', $second->phonenumbers[0]->phonenumber);
	}

	public function testDrop() {
		$Fixture = new MongoCakeUserTestFixture($this->connection);
		$Fixture->insert($this->connection);
		$this->connection->commit();
		$this->assertEquals(2, User::find('all')->count());
		$Fixture->drop($this->connection);
		$this->assertEquals(0, User::find('all')->count());
	}

	public function testTruncate() {
		$Fixture = new MongoCakeUserTestFixture($this->connection);
		$Fixture->insert($this->connection);
		$this->connection->commit();
		$this->assertEquals(2, User::find('all')->count());
		$Fixture->truncate($this->connection);
		$this->assertEquals(0, User::find('all')->count());
	}
}