<?php
App::uses('ConnectionManager', 'Model');

class CakeDocument implements ArrayAccess {
	public $useDbConfig = 'mongoDefault';
	protected $_schema = array();

	public function __get($property) {
		$schema = $this->schema();
		if (isset($schema['fieldMappings'][$property]) && method_exists($this, 'get' . $property)) {
			return $this->{'get'.$property}();
		}
	}

	public function __set($property, $value) {
		$schema = $this->schema();
		if (isset($schema['fieldMappings'][$property]) && method_exists($this, 'set' . $property)) {
			$this->{'set'.$property}($value);
		}
	}

	public function offsetExists($property) {
		try {
			$this->extractOffset($property);
		} catch (Exception $e) {
			return false;
		}

		return true;
	}

	public function offsetGet($property) {
		return $this->extractOffset($property);
	}

	public function offsetSet($property, $value) {
		$schema = $this->schema();
		if (isset($schema['fieldMappings'][$property])) {
			$this->{$property} = $value;
		}
	}

	public function offsetUnset($offset) {
		$schema = $this->schema();
		if (isset($schema['fieldMappings'][$property])) {
			$this->{$property} = null;
		}
	}

	protected function extractOffset($property) {
		if ($property == get_class($this)) {
			return $this;
		}

		$schema = $this->schema();
		$isField = isset($schema['fieldMappings'][$property]);

		if ($isField) {
			return $this->{$property};
		}

		foreach ($schema['fieldMappings'] as $p => $field) {
			if (!empty($field['targetDocument']) && $field['targetDocument'] == $property) {
				return $this->{$p};
			}
		}

		throw new Exception('Invalid offset');
	}

	public function getDataSource() {
		return ConnectionManager::getDataSource($this->useDbConfig);
	}

	public function getDocumentManager() {
		return $this->getDataSource()->getDocumentManager();
	}

	public function getRepository() {
		return $this->getDocumentManager()->getRepository(get_class($this));
	}

	public function flush() {
		return $this->getDocumentManager()->flush();
	}

	public function schema() {
		if (!empty($this->_schema)) {
			return $this->_schema;
		}
		$this->_schema = new ArrayObject(
			$this->getDocumentManager()
			->getMetadataFactory()
			->getMetadataFor(get_class($this))
		);
		return $this->_schema;
	}

	public function save() {
		return $this->getDocumentManager()->persist($this);
	}

	public function delete() {
		return $this->getDocumentManager()->remove($this);
	}

	public function find($type, $options = array()) {
		$repository = $this->getRepository();
	}
}