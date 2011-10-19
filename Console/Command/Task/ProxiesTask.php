<?php
use Doctrine\ODM\MongoDB\Tools\Console\MetadataFilter;
App::uses('ConnectionManager', 'Model');

class ProxiesTask extends Shell {

	public $tasks = array('DbConfig');

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser
			->description(
				__('Generates proxy classes for document classes.')
			)
			->addOption('filter', array(
				'short' => 'f',
				'default' => null,
				'help' => __('A string pattern used to match documents that should be processed.')
		))->addOption('destPath', array(
				'short' => 'd',
				'default' => null,
				'help' => __('The path to generate your proxy classes. Default taken from configuration.')
		));
		return $parser;
	}

	public function execute() {
		if (empty($this->connection)) {
			$this->connection = $this->DbConfig->getConfig();
		}
		$this->out('Generating Proxy classes');
		$dm = ConnectionManager::getDataSource($this->connection)->getDocumentManager();
		
		$metadatas = $dm->getMetadataFactory()->getAllMetadata();
		$metadatas = MetadataFilter::filter($metadatas, isset($this->params['filter']) ? $this->params['filter'] : null);

		// Process destination directory
		$destPath = empty($this->params['destPath']) ? $dm->getConfiguration()->getProxyDir() : $this->params['destPath'];
		if (!is_dir($destPath)) {
			mkdir($destPath, 0777, true);
		}

		$destPath = realpath($destPath);

		if (!file_exists($destPath)) {
			throw new \InvalidArgumentException(
				sprintf("Proxies destination directory '<info>%s</info>' does not exist.", $destPath)
			);
		} else if (!is_writable($destPath)) {
			throw new \InvalidArgumentException(
				sprintf("Proxies destination directory '<info>%s</info>' does not have write permissions.", $destPath)
			);
		}

		if (count($metadatas)) {
			foreach ($metadatas as $metadata) {
				$this->out(sprintf('Processing document "<info>%s</info>"', $metadata->name));
			}

			// Generating Proxies
			$dm->getProxyFactory()->generateProxyClasses($metadatas, $destPath);

			// Outputting information message
			$this->out(sprintf('Proxy classes generated to "<info>%s</info>"', $destPath));
		} else {
			$this->out('No Metadata Classes to process.');
		}
	}

}