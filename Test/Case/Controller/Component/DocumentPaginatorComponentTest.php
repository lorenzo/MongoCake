<?php
App::uses('ConnectionManager', 'Model');
App::uses('Controller', 'Controller');
App::uses('DocumentPaginatorComponent', 'MongoCake.Controller/Component');
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('User', 'Model');

class DocumentPaginatorComponentTest extends CakeTestCase {

	public function setUp() {
		App::build(array(
			'Model' => CakePlugin::path('MongoCake') . 'Test' . DS . 'Fixture' . DS
		), APP::RESET);
		ConnectionManager::create('testMongo', array(
			'datasource' => 'MongoCake.CakeMongoSource',
			'database' => 'test'
		));
		$this->request = new CakeRequest('users/index');
		$this->request->params['pass'] = $this->request->params['named'] = array();
		$this->Controller = new Controller($this->request, new CakeResponse());
		$this->Paginator = new DocumentPaginatorComponent($this->getMock('ComponentCollection'), array());
		$this->Controller->Paginator = $this->Paginator;
		$this->Paginator->Controller = $this->Controller;
		$this->Controller->uses = array('User');
	}

	public function tearDown() {
		$manager = ConnectionManager::getDataSource('testMongo')->getSchemaManager();
		$manager->dropDocumentCollection('User');
		$manager->dropDocumentCollection('Account');
		App::build();
		ClassRegistry::flush();
		ConnectionManager::drop('testMongo');
	}

	public function testPaginate() {
		for ($i = 0; $i < 10; $i++) {
			$u = new User();
			$u->save(array(
				'User' => array(
					'username' => 'User ' . $i,
					'password' => 'password ' . $i,
					'salary' => 100 + $i
				)
			));
		}
		$u->flush();

		$this->Controller->request->params['pass'] = array('1');
		$this->Controller->request->query = array();
		$this->Controller->constructClasses();
		$this->Controller->Paginator->settings = array('limit' => 3);

		$results = Set::extract($this->Controller->Paginator->paginate(), '{s}.User.username');
		$this->assertEqual($results, array('User 0', 'User 1', 'User 2'));

		$this->Controller->request->params['named'] = array('sort' => 'salary', 'direction' => 'desc');
		$results = Set::extract($this->Controller->Paginator->paginate(), '{s}.User.username');
		$this->assertEqual($this->Controller->params['paging']['User']['page'], 1);
		$this->assertEqual($results, array('User 9', 'User 8', 'User 7'));

		$this->Controller->request->params['named'] = array('sort' => 'User.salary', 'direction' => 'desc', 'page' => 2);
		$results = Set::extract($this->Controller->Paginator->paginate(), '{s}.User.username');
		$this->assertEqual($this->Controller->params['paging']['User']['page'], 2);
		$this->assertEqual($results, array('User 6', 'User 5', 'User 4'));

		$this->Controller->request->params['named'] = array();
		$this->Controller->Paginator->settings = array('topPaid', 'limit' => 3);
		$results = Set::extract($this->Controller->Paginator->paginate(), '{s}.User.username');
		$this->assertEqual($this->Controller->params['paging']['User']['page'], 1);
		$this->assertEqual($results, array('User 9', 'User 8', 'User 7'));
	}

}