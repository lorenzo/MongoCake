<?php

use Doctrine\ODM\MongoDB\UnitOfWork;

App::uses('ConnectionManager', 'Model');

class CakeDocument implements ArrayAccess {
	public $useDbConfig = 'default';
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
		try {
			$this->getDocumentManager()->flush();
		} catch (OperationCancelledException $e) {}
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

	public function exists() {
		$uow = $this->getDocumentManager()->getUnitOfWork();
		$documentState = $uow->getDocumentState($this);
		return $documentState === UnitOfWork::STATE_MANAGED || $documentState ===  UnitOfWork::STATE_DETACHED;
	}

/**
 * This callback is fired just before the data is going to be committed into the persistent storage.
 * Keep in mind this means just after the flush() call, and not inside the save() method, so don't
 * rely on any behavior that cancels a save at this point unless you know what you are doing.
 * 
 * Returning false will cancel the persist operation.
 *
 * @param boolean $isUpdate whether this operation is a create or create operation
 * @return boolean true to continue saving, false to cancel
 */
	public function beforeSave($isUpdate) {
		return true;
	}

	public function afterSave($exists) {}

	public function beforeDelete() {
		return true;
	}

	public function afterDelete() {}

/**
 * Persists a document in the datastore. Keep in mind that issuing flush() is needed after this method
 *
 * @return boolean success
 */
	public function save() {
		try {
			$this->getDocumentManager()->persist($this);
		} catch (OperationCancelledException $e) {
			return false;
		}
		return true;
	}

	public function delete() {
		return $this->getDocumentManager()->remove($this);
	}

	public function find($type, $options = array()) {
		$repository = $this->getRepository();
		if (!in_array($type, array('all', 'first'))) {
			return $repository->find($type);
		}
		$options += array('conditions' => array());
	}
}