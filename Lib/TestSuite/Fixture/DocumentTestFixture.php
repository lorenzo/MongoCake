<?php

abstract class DocumentTestFixture  {

/**
 * Name of the object
 *
 * @var string
 */
	public $name = null;

/**
 * This property is not used, provided for compatibility purposes with
 * CakeFixtureManager
 *
 * @var string
 */
	public $table = null;

	public $document = null;

	public $plugin = null;

	public $records = array();

/**
 * Instantiate the fixture.
 *
 */
	public function __construct() {
		if ($this->name === null) {
			if (preg_match('/^(.*)Fixture$/', get_class($this), $matches)) {
				$this->name = $matches[1];
			} else {
				$this->name = get_class($this);
			}
		}
		if (empty($this->document)) {
			$this->document = $this->name;
		}

		if (empty($this->plugin)) {
			list($this->plugin, $this->document) = pluginSplit($this->document);
		}
		$this->init();
	}

/**
 * Initializes the fixture
 *
 */
	public function init() {
		$plugin = $this->plugin ? $this->plugin . '.' : null;
		App::uses($this->document, $plugin . 'Model');
		$documentClass = $this->document;
		$documentClass::$useDbConfig = 'test';
	}

/**
 * Method left blank to comply with the CakeTestFixture API, Document collections are created on the fly
 * from the object class properties
 *
 * @param object $db An instance of the datasource object
 * @return boolean true on success, false on failure
 */
	public function create($db) {
		return true;
	}

/**
 * Drops the collection associated with this fixture
 *
 * @param object $db An instance of the datasource object
 * @return boolean True on success, false on failure
 */
	public function drop($db) {
		try {
			$db->getSchemaManager()->dropDocumentCollection($this->document);
		} catch (Exception $e) {
			return false;
		}
		return true;
	}

/**
 * 
 *
 *
 * @param object $db An instance of the database into which the records will be inserted
 * @return boolean on success or if there are no records to insert, or false on failure
 */
	public function insert($db) {
		$this->run();
		if (!empty($this->records)) {
			$document = $this->document;
			foreach($this->records as $namedRecord => $record) {
				$instance = new $document;
				$instance->save($record, false);
			}
		}
	}

/**
 * Truncates the current fixture.
 *
 * @param object $db An instance of the datasource object
 * @return boolean
 */
	public function truncate($db) {
		$db->getDocumentManager()->createQueryBuilder($this->document)
			->findAndRemove()
			->getQuery()
			->execute();

		return true;
	}

	public function run() {
	}

}