<?php
use Doctrine\Common\Annotations\AnnotationReader,
	Doctrine\ODM\MongoDB\DocumentManager,
	Doctrine\MongoDB\Connection,
	Doctrine\ODM\MongoDB\Configuration,
	Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver,
	Doctrine\ODM\MongoDB\SchemaManager;

App::uses('DataSource', 'Model/Datasource');

class CakeMongoSource extends DataSource {
	
	private $__configuration;
	private $__connection;
	private $__documentManager;

	public function __construct($config = array(), $autoConnect = true) {
		$this->_baseConfig = array(
			'proxyDir' => TMP . 'cache',
			'proxyNamespace' =>'Proxies',
			'hydratorDir' => TMP . 'cache',
			'hydratorNamespace' => 'Hydrators',
			'server' => 'localhost',
		);
		parent::__construct($config);
		extract($this->config, EXTR_OVERWRITE);

		$configuration = new Configuration();
		$configuration->setProxyDir($proxyDir);
		$configuration->setProxyNamespace($proxyNamespace);
		$configuration->setHydratorDir($hydratorDir);
		$configuration->setHydratorNamespace($hydratorNamespace);

		$reader = new AnnotationReader();
		$reader->setDefaultAnnotationNamespace('Doctrine\ODM\MongoDB\Mapping\\');
		$configuration->setMetadataDriverImpl(new AnnotationDriver($reader, App::path('Model')));

		$this->__configuration = $configuration;
		$this->__connection = new Connection($server);
		$this->__documentManager = DocumentManager::create($this->__connection, $configuration);
		
		if ($autoConnect) {
			$this->connect();
		}
	}

	public function getConnection() {
		return $this->__connection;
	}

	public function getDocumentManager() {
		return $this->__documentManager;
	}

	public function getConfiguration() {
		return $this->__configuration;
	}

	public function getSchemaManager() {
		return $this->getDocumentManager()->getSchemaManager();
	}

	public function connect() {
		return $this->__connection->connect();
	}

	public function isConnected() {
		return $this->__connection->isConnected();
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
 * Drop a Collection
 *
 * @param CakeSchema $schema Schema object
 * @param string $collection Collection name
 * @return void
 *
 * @todo We'll need to override CakeTestFixture to avoid $db->execute(...) but the implementation here is sound.
 */
	public function dropSchema(CakeSchema $schema, $collection = null) {
		foreach ($schema->tables as $curTable => $columns) {
			if (!$collection || $collection == $curTable) {
				$this->getSchemaManager()->dropDocumentCollection($collection);
			}
		}
	}
}