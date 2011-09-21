<?php
use Doctrine\Common\Annotations\AnnotationReader,
	Doctrine\ODM\MongoDB\DocumentManager,
	Doctrine\MongoDB\Connection,
	Doctrine\ODM\MongoDB\Configuration,
	Doctrine\Common\Annotations\AnnotationRegistry,
	Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver,
	Doctrine\ODM\MongoDB\SchemaManager,
	Doctrine\ODM\MongoDB\Events,
	Doctrine\Common\ClassLoader,
	Doctrine\Common\Cache\ApcCache;

App::uses('DataSource', 'Model/Datasource');
App::uses('QueryProxy', 'MongoCake.Query');

/**
 * DataSource responsible for connecting to MongoDB using the Doctrine Mongo ODM adapter
 *
 * @package MongoCake.Model.Datasource
 */
class CakeMongoSource extends DataSource {

/**
 * Configuration object with all options passed in to it
 *
 * @var Configuration
 */
	protected $configuration;

/**
 * Connection Object to communicate with the data storage
 *
 * @var Connection
 */
	protected $connection;

/**
 * Object responsible for managing all objects created and retrieved for data storage
 *
 * @var DocumentManager
 */
	protected $documentManager;

	protected $_queriesLog = array();

/**
 * Datasource constructor, creates the Configuration, Connection and DocumentManager objects
 *
 * ### You can pass the following configuration options
 *
 *	- server: name of the server that will be used to connect to Mongo (default: `localhost`)
 *	- database: name of the database to use when connecting to Mongo (default: `cake`)
 *	- documentPaths: array containing a list of full path names where Document classes can be located (default: `App::path('Model')`)
 *	- proxyDir: full path to the directory that will contain the generated proxy classes for each document (default: `TMP . 'cache'`)
 *	- proxyNamespace: string representing the namespace the proxy classes will reside in (default: `Proxies`)
 *	- hydratorDir: directory well the hydrator classes will be generated in (default: `TMP . 'cache'`)
 *	- hydratorNamespace:  string representing the namespace the hydrator classes will reside in (default: `Hydrators`)
 *
 * @param arary $config 
 * @param boolean $autoConnect whether this object should attempt connection on creation
 * @throws MissingConnectionException if it was not possible to connect to MongoDB
 */
	public function __construct($config = array(), $autoConnect = true) {
		$this->_baseConfig = array(
			'proxyDir' => TMP . 'cache',
			'proxyNamespace' =>'Proxies',
			'hydratorDir' => TMP . 'cache',
			'hydratorNamespace' => 'Hydrators',
			'server' => 'localhost',
			'database' => 'cake',
			'documentPaths' => App::path('Model'),
			'prefix' => null //Not implemented, probably never will... Just there for compatibility
		);
		foreach (CakePlugin::loaded() as $plugin) {
			$this->_baseConfig['documentPaths'] = array_merge($this->_baseConfig['documentPaths'], App::path('Model', $plugin));
		}

		parent::__construct($config);
		extract($this->config, EXTR_OVERWRITE);

		$configuration = new Configuration();
		$configuration->setProxyDir($proxyDir);
		$configuration->setProxyNamespace($proxyNamespace);
		$configuration->setHydratorDir($hydratorDir);
		$configuration->setHydratorNamespace($hydratorNamespace);
		$configuration->setDefaultDB($database);
		$configuration->setMetadataDriverImpl($this->_getMetadataReader($documentPaths));

		if (Configure::read('debug') === 0) {
			$configuration->setAutoGenerateHydratorClasses(false);
			$configuration->setAutoGenerateProxyClasses(false);
			$configuration->setMetadataCacheImpl(new ApcCache());
		}

		$this->configuration = $configuration;
		$this->connection = new Connection($server, array(), $configuration);
		$this->documentManager = DocumentManager::create($this->connection, $configuration);

		$this->documentManager->getEventManager()
			->addEventListener(
				array(
					Events::prePersist,
					Events::preUpdate,
					Events::preRemove,
					Events::postPersist,
					Events::postUpdate,
					Events::postRemove,
				),
				$this
			);
		try {
			if ($autoConnect) {
				$this->connect();
			}
		} catch (Exception $e) {
			throw new MissingConnectionException(array('class' => get_class($this)));
		}

		$this->setupLogger();
	}

/**
 * Setups a logger function fore the queries generated
 *
 * @return void
 */
	protected function setupLogger() {
		if (Configure::read('debug') > 1) {
			$self = $this;
			$this->configuration->setLoggerCallable(function(array $log) use ($self) {
				$self->appendQueryLog($log);
			});
		}
	}

	public function appendQueryLog(array $log) {
		$this->_queriesLog[] = array(
			'query' => print_r($log, true),
			'numRows' => 0,
			'took' => 0,
			'affected' => 0,
			'error' => 0
		);
	}

/**
 * Get the query log as an array.
 *
 * @param boolean $sorted Get the queries sorted by time taken, defaults to false.
 * @param boolean $clear If True the existing log will cleared.
 * @return array Array of queries run as an array
 */
	public function getLog($sorted = false, $clear = true) {
		if ($sorted) {
			$log = sortByKey($this->_queriesLog, 'took', 'desc', SORT_NUMERIC);
		} else {
			$log = $this->_queriesLog;
		}
		if ($clear) {
			$this->_queriesLog = array();
		}
		return array('log' => $log, 'count' => count($this->_queriesLog), 'time' => 'Unknown');
	}

/**
 * Returns a new metadata reader driver to be used for configuring each of the document classes
 * found in the applications
 *
 * @param array $paths array containing a list of full path names where Document classes can be located 
 * @return Driver
 */
	protected function _getMetadataReader($paths) {
		$reader = new AnnotationReader();
		$driver = new AnnotationDriver($reader, $paths);
		AnnotationDriver::registerAnnotationClasses();
		AnnotationRegistry::registerFile(CakePlugin::path('MongoCake') . 'Lib' . DS . 'MongoCake' . DS . 'Annotation' . DS . 'Annotations.php');
		return $driver;
	}

/**
 * Returns the connection object instance
 *
 * @return Connection
 */
	public function getConnection() {
		return $this->connection;
	}

/**
 * Returns the document manager instance
 *
 * @return DocumentManager
 */
	public function getDocumentManager() {
		return $this->documentManager;
	}

/**
 * Returns the configuration object instance
 *
 * @return Configuration
 */
	public function getConfiguration() {
		return $this->configuration;
	}

/**
 * Returns a new QueryProxy class associated to a document name
 * A query proxy is a class responsible for building query based on options,
 * and also retrieve the query results and proxy custom finder calls to the
 * document object
 *
 * @param string $documentName name of the document whose repository will be queried
 * @return QueryProxy
 */
	public function createQueryBuilder($documentName = null) {
		return new QueryProxy($this->documentManager, $this->configuration->getMongoCmd(), $documentName);
	}

/**
 * Returns the Schema manager associated to the Document Manager
 *
 * @return SchemaManager
 */
	public function getSchemaManager() {
		return $this->getDocumentManager()->getSchemaManager();
	}

/**
 * Returns the result of attempting to connect to the persistent storage server
 *
 * @return boolean
 */
	public function connect() {
		return $this->connection->connect();
	}

/**
 * Return whether this datasource is connected or not to the persistent storage server
 *
 * @return boolean
 */
	public function isConnected() {
		return $this->connection->isConnected();
	}

/**
 * Callback function called when a document is about to be persisted for the first time in storage
 * It is also responsible for filling up the `created` property in documents
 *
 * @param LifecycleEventArgs $eventArgs
 * @return void
 */
	public function prePersist(\Doctrine\ODM\MongoDB\Event\LifecycleEventArgs $eventArgs) {
		$document = $eventArgs->getDocument();
		$schema = $document->schema();
		if ($document->hasField('created') && $schema['created']['type'] == 'date') {
			$document->created = new DateTime();
		}
		$continue = $document->beforeSave(false);
		if (!$continue) {
			throw new OperationCancelledException();
		}
	}

/**
 * Callback function called when a document was just persisted for the first time in storage
 *
 * @param LifecycleEventArgs $eventArgs
 * @return void
 */
	public function postPersist(\Doctrine\ODM\MongoDB\Event\LifecycleEventArgs $eventArgs) {
		$eventArgs->getDocument()->afterSave(false);
	}

/**
 * Callback function called when a document was just updated in storage
 * It is also responsible for filling up the `modified` property in documents
 *
 * @param LifecycleEventArgs $eventArgs
 * @return void
 */
	public function preUpdate(\Doctrine\ODM\MongoDB\Event\LifecycleEventArgs $eventArgs) {
		$document = $eventArgs->getDocument();
		$dm = $eventArgs->getDocumentManager();
		$schema = $document->schema();
		if ($document->hasField('modified') && $schema['modified']['type'] == 'date') {
			$document->modified = new DateTime();
		}
		$continue = $document->beforeSave(true);
		if (!$continue) {
			throw new OperationCancelledException();
		}

		$uow = $dm->getUnitOfWork();
		$class = $dm->getClassMetaData(get_class($document));
		$uow->recomputeSingleDocumentChangeSet($class, $document);
	}

/**
 * Callback function called when a document was just updated in storage
 *
 * @param LifecycleEventArgs $eventArgs
 * @return void
 */
	public function postUpdate(\Doctrine\ODM\MongoDB\Event\LifecycleEventArgs $eventArgs) {
		$eventArgs->getDocument()->afterSave(true);
	}

/**
 * Callback function called when a document is about to be removed from storage
 *
 * @param LifecycleEventArgs $eventArgs
 * @return void
 */
	public function preRemove(\Doctrine\ODM\MongoDB\Event\LifecycleEventArgs $eventArgs) {
		$continue = $eventArgs->getDocument()->beforeDelete();
		if (!$continue) {
			throw new OperationCancelledException();
		}
	}

/**
 * Callback function called when a document was just removed from storage
 *
 * @param LifecycleEventArgs $eventArgs
 * @return void
 */
	public function postRemove(\Doctrine\ODM\MongoDB\Event\LifecycleEventArgs $eventArgs) {
		$eventArgs->getDocument()->afterDelete();
	}

/**
 * Get list of collection names
 *
 * @return array
 */
	public function listSources() {
		return array_keys($this->getDocumentManager()->getDocumentCollections());
	}

/**
 * Emulates a begin transaction call, it just returns without performing any action
 * there are no transactions in mongo
 *
 * @return boolean
 */
	public function begin() {
		return true;
	}

/**
 * The most similar thing to a transaction commit in Doctrine is a flush()
 * since it writes everything at once simulating what a transactions does
 * plus it persists any unstable change in memory
 *
 * @return boolean
 */
	public function commit() {
		$this->getDocumentManager()->flush();
		return true;
	}

}

/**
 * Exception to be thrown if a save operation is cancelled
 *
 */
class OperationCancelledException extends Exception {}