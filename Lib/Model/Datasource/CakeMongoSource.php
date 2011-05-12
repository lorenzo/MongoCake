<?php
use Doctrine\Common\Annotations\AnnotationReader,
	Doctrine\ODM\MongoDB\DocumentManager,
	Doctrine\MongoDB\Connection,
	Doctrine\ODM\MongoDB\Configuration,
	Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver;

class CakeMongoSource {
	
	public $config;
	private $configuration;
	private $connection;
	private $documentManager;

	public function __construct($config = array()) {
		$this->config = $config;
		$proxyDir = TMP . 'cache';
		$proxyNamespace = 'Proxies';
		$hydratorDir = TMP . 'cache';
		$hydratorNamespace = 'Hydrators';
		$server = null;
		
		extract($config, EXTR_OVERWRITE);

		$configuration = new Configuration();
		$configuration->setProxyDir($proxyDir);
		$configuration->setProxyNamespace($proxyNamespace);
		$configuration->setHydratorDir($hydratorDir);
		$configuration->setHydratorNamespace($hydratorNamespace);

		$reader = new AnnotationReader();
		$reader->setDefaultAnnotationNamespace('Doctrine\ODM\MongoDB\Mapping\\');
		$configuration->setMetadataDriverImpl(new AnnotationDriver($reader, App::path('Model')));

		$this->configuration = $configuration;
		$this->connection = new Connection($server);
		$this->documentManager = DocumentManager::create($this->connection, $configuration);
	}

	public function getConnection() {
		return $this->connection;
	}

	public function getDocumentManager() {
		return $this->documentManager;
	}

	public function getConfiguration() {
		return $this->configuration;
	}

	
}