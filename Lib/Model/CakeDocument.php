<?php

use Doctrine\ODM\MongoDB\UnitOfWork;

App::uses('ConnectionManager', 'Model');
App::uses('Validation', 'Utility');

/**
 * This class is meant to be an almost API compatible replacement for the 
 * CakePHP Model class, but based on a Document database and using as datasource
 * the CakeMongoSource class.
 *
 * This class uses at the same time a different paradigm in how to handle data when
 * compared with the Model class. This one uses the data mapper patter, and mixes it
 * a bit with the ActiveRecord one too. Each object represents an entry in the persistent
 * storage, and has the methods to be saved, updated, and deleted; Unlike the model class
 * where the object represents the repository and results are returned as arrays.
 *
 * Properties in this object are treated as properties in the persistent storage collection,
 * and each change made on them, after using the persisting methods, will be reflected on the database.
 *
 * @package MongoCake.Model
 */
abstract class CakeDocument implements ArrayAccess {

/**
 * Specifies which connection name to use for this instance
 *
 * @var string
 */
	public static $useDbConfig = 'default';

/**
 * Set of validation rules for this document
 *
 * @var array
 */
	public $validate = array();

/**
 * Holds the list of validation errors for each property of this document
 *
 * @var array
 */
	public $validationErrors = array();

/**
 * Name of the validation string domain to use when translating validation errors.
 *
 * @var string
 * @access public
 */
	public $validationDomain = null;

/**
 * Whitelist of fields allowed to be saved.
 *
 * @var array
 */
	public $whitelist = array();

/**
 * Name of the property used as document identification
 *
 * @var string
 */
	public $primaryKey = 'id';

/**
 * List of valid finder method options, supplied as the first parameter to find()
 * Methods can be listed either as 'method' => true, or define a set of options to
 * be appended to the query. If defined as a method you must defined a function
 * to append any special options to the current query.
 *
 * ## Examples:
 *
 * ### Defining inline scopes
 *
 *	{{{
 *		public static $findMethods = array(
 *			'published' => array(
 *				'conditions' => array('published' => true)
 *			)
 *		);
 *
 *		// You can use different ways for querying using the published scope
 *		$publishedPosts = Post::published();
 *		$publishedPosts = Post::find('published');
 *		$publishedPosts = Post::find('all')->published();
 *	}}}
 *	
 * ### Defining scoped finder methods
 *
 *	{{{
 *		public static $findMethods = array(
 *			'recent' => true
 *		);
 *
 *		protected function _find($state, $query, $args) {
 *			if ($state == 'before') {
 *				$limit = current($args) ?: 5;
 *				$query->order('created', 'desc')->limit($limit);
 *			}
 *			return $query;
 *		}
 *
 *		// You can pass params when using finder methods
 *		$publishedPosts = Post::recent(10); // Limits the results to 10 documents
 *		$publishedPosts = Post::find('recent');
 *		$publishedPosts = Post::find('all')->recent(5);
 *	}}}
 *
 *	### Chaining Scopes
 *
 *	It is possible to chain multiple scopes un the same query
 *
 *	{{{
 *		$recentlyPublished = Post::recent(10)->published();
 *	}}}
 *
 * @var array
 */
	public static $findMethods = array();

/**
 * Holds the schema description for this document
 *
 * @var array
 */
	protected $_schema = array();

/**
 * Magic method to proxy calls to special finder methods when called as static functions
 *
 * @param string $method name of the method to be called
 * @param string $arguments array of arguments to be passed in, thus params will be passed inside
 *	the QueryProxy object internal property 'args'
 * @return mixed
 */
	public static function __callStatic($method, $arguments) {
		if (!empty(static::$findMethods[$method])) {
			if (current($arguments) instanceof QueryProxy) {
				$query = empty($arguments) ? array() : array_shift($arguments);
			} else {
				$query = array('args' => $arguments);
			}
			return static::find($method, $query);
		}
		throw new BadMethodCallException(sprintf('%s is not a valid call method in class %s', $method, get_called_class()));
	}

/**
 * Magic method to retrieve object unreachable internal properties when accessed directly
 * it will work only if the object implements the function get[Property]() to return the value.
 *
 *	### Example
 *
 *	{{{
 *		class User extends CakeDocument {
 *			private $fullName;
 *			public function getFullName() {
 *				return $this->fullName;
 *			}
 *		}
 *		$user->fullname; // Will call User::getFullName();
 *	}}}
 *
 *	One of the advantages of using getters is that it makes possible to execute specific logic
 *	before the property is returned. It also enables you to define virtual fields by calculating
 *	other internal object data
 *
 * @param string $property name of the property to get
 * @return mixed property value if it an be reached using a getter function
 */
	public function __get($property) {
		if ($property === 'hasAndBelongsToMany') {
			return array();
		}
		if (method_exists($this, 'get' . $property)) {
			return $this->{'get'.$property}();
		}
		if (isset($this->{$property})) {
			return $this->{$property};
		}
	}


/**
 * Magic method to set object unreachable internal properties when accessed directly
 * it will work only if the object implements the function set[Property]() to set the value.
 *
 *	### Example
 *
 *	{{{
 *		class User extends CakeDocument {
 *			private $fullName;
 *			public function setFullName($name) {
 *				return $this->fullName = $name;
 *			}
 *		}
 *		$user->fullname = 'John'; // Will call User::setFullName('Jhon');
 *	}}}
 *
 *	One of the advantages of using setters is that it makes possible to execute specific logic
 *	before the property is set, such as validation. It also enables you to define virtual fields by calculating
 *	other internal object data
 *
 * @param string $property name of the property to set
 * @param mixed $value data to be set to the property
 * @return void
 */
	public function __set($property, $value) {
		if (method_exists($this, 'set' . $property)) {
			$this->{'set'.$property}($value);
		}
	}

/**
 * Method implemented to comply with ArrayAccess interface, will return whether document's
 * internal properties that are mapped as fields in the datasource exist or not, when accessed
 * using the array interface
 *
 * @param string $property 
 * @return boolean whether the mapped field exists or not
 */
	public function offsetExists($property) {
		try {
			$this->extractOffset($property);
		} catch (Exception $e) {
			return false;
		}

		return true;
	}

/**
 * Method implemented to comply with ArrayAccess interface, will return document's
 * internal properties that are mapped as fields in the datasource when accessed
 * using the array interface. This interface is a backwards compatible method for
 * accessing result data in CakePHP. This method is also able to fetch associated
 * documents data using as array key the name of the associated document alias
 * defined in the annotation
 *
 * ### Examples:
 *
 *	{{{
 *		class User extends CakeDocument {
 *			public $fullName;
 * 			//HasManyEmbedded(targetDocument="PhoneNumber", alias="PhoneNumber")
 *			private $numbers;
 *
 *		}
 *		$fullName = $user['fullName'];
 *		$fullName = $user['User]['fullName']; // This is equivalent to the previous line
 *
 *		$phoneNumbers = $user['numbers'];
 *		$firstPhoneNumber = $user['PhoneNumber'][0]['number'];
 *	}}}
 *
 *
 *
 * @param string $property property name to be looked up
 * @return mixed
 */
	public function offsetGet($property) {
		return $this->extractOffset($property);
	}

/**
 * Method implemented to comply with ArrayAccess interface, will set document's
 * internal properties that are mapped as fields in the datasource when accessed
 * using the array interface. This interface is a backwards compatible method for
 * setting result data in CakePHP. This method is also able to set associated
 * documents data using as array key the name of the associated document alias
 * defined in the annotation
 *
 * ### Examples:
 *
 *	{{{
 *		class User extends CakeDocument {
 *			public $fullName;
 * 			//HasManyEmbedded(targetDocument="PhoneNumber", alias="PhoneNumber")
 *			private $numbers;
 *
 *		}
 *		$user['fullName'] = 'Jhon';
 *		$user['User]['fullName']; // This is equivalent to the previous line
 *
 *		$user['PhoneNumber'][0] = new PhoneNumber('444-555-33');
 *	}}}
 *
 *
 * @param string $property property name to be set
 * @param mixed $value data to be set to the property
 * @return void
 */
	public function offsetSet($property, $value) {
		$schema = $this->schema();
		if (isset($schema[$property])) {
			$this->{$property} = $value;
		}
	}

/**
 * Method implemented to comply with ArrayAccess interface, will unset document's
 * internal properties that are mapped as fields in the datasource when accessed
 * using the array interface. This interface is a backwards compatible method for
 * unsetting result data in CakePHP. This method is also able to unset associated
 * documents data using as array key the name of the associated document alias
 * defined in the annotation
 *
 * ### Examples:
 *
 *	{{{
 *		class User extends CakeDocument {
 *			public $fullName;
 * 			//HasManyEmbedded(targetDocument="PhoneNumber", alias="PhoneNumber")
 *			private $numbers;
 *
 *		}
 *		unset($user['fullName']);
 *		unset($user['User]['fullName']); // This is equivalent to the previous line
 *
 *		unset($user['PhoneNumber'][0]);
 *	}}}
 *
 *
 * @param string $property property name to be set
 * @param mixed $value data to be set to the property
 * @return void
 */
	public function offsetUnset($property) {
		$schema = $this->schema();
		if (isset($schema[$property])) {
			$this->{$property} = null;
		}
	}

/**
 * Auxiliary method to extract a mapped internal property
 *
 * @param string $property name of the property to be returned
 * @return mixed
 */
	protected function extractOffset($property) {
		if ($property == get_class($this)) {
			return $this;
		}

		$schema = $this->schema();
		$isField = isset($schema[$property]);

		if ($isField) {
			return $this->{$property};
		}

		foreach ($schema as $p => $field) {
			if (!empty($field['targetDocument']) && $field['targetDocument'] == $property) {
				return $this->{$p};
			}
		}

		trigger_error(sprintf('Invalid offset %s', $property));
	}

/**
 * Returns the instance of the datasource used by this class to persist and retrieve data
 *
 * @return CakeMongoSource
 */
	public function getDataSource() {
		return ConnectionManager::getDataSource(static::$useDbConfig);
	}

/**
 * Returns the document manager associated to this document's datasource
 *
 * @return DocumentManager
 */
	public function getDocumentManager() {
		return $this->getDataSource()->getDocumentManager();
	}

/**
 * Returns the repository associated to this document. A Repository is an object
 * representing the collection of the same type of documents and it's mainly used
 * for finding operations. Doctrine allows you to create your own repository classes
 * in order to implement custom finders, although this feature is already covered
 * by CakeDocument. You are, nevertheless, still able to create those and call the methods
 * as described in the Doctrine Mongo ODM documentation.
 *
 * @return DocumentRepository
 */
	public function getRepository() {
		return $this->getDocumentManager()->getRepository(get_class($this));
	}

/**
 * Calling this function is required in order to actually persist your changes in to
 * the data repository. Think of it as a `commit` statement, where all your previous
 * inserts and updates are saved. Calling this method will not only flush changes for this
 * object, but rather it will do it for all documents managed by the same DocumentManager this
 * class is using.
 *
 * Do not invoke flush after every change to a document or every single invocation of save/delete
 * it will unnecessarily reduce the performance of your application. Instead, you can call this
 * method just after all your changes are done for the same request. 
 *
 * There should be usually no need for invoking flush more than 0-2 times per request.
 *
 * @return void
 */
	public function flush() {
		try {
			$this->getDocumentManager()->flush();
		} catch (OperationCancelledException $e) {}
	}

/**
 * Returns a list of fields mapped as document properties in the persistent storage.
 * each field name will be found as an array key having as values another array of
 * options set for the the field, such as the type, special callbacks, target document, etc.
 *
 * @return array
 */
	public function schema() {
		if (!empty($this->_schema)) {
			return $this->_schema;
		}

		$this->_schema = $this->getDocumentMetaData()->fieldMappings;
		return $this->_schema;
	}

/**
 * Returns this document's metadata (field definitions, relations, reflection fields)
 *
 * @return ClassMetadata
 */
	public function getDocumentMetaData() {
		return $this->getDocumentManager()
		->getMetadataFactory()
		->getMetadataFor(get_class($this));
	}

/**
 * Returns whether this document exists in database or not, it actually only checks
 * if the document is managed or detached from the document manager, it will not query
 * the datasource
 *
 * @return boolean
 */
	public function exists() {
		$uow = $this->getDocumentManager()->getUnitOfWork();
		$documentState = $uow->getDocumentState($this);
		return $documentState === UnitOfWork::STATE_MANAGED || $documentState ===  UnitOfWork::STATE_DETACHED;
	}

/**
 * This callback is fired just before the data is going to be committed into the persistent storage.
 * Keep in mind this means it will fire just after the flush() call, and not inside the save() method, so don't
 * rely on any behavior that cancels a save at this point unless you know what you are doing.
 * 
 * Returning false will cancel the flush operation. This basically means that the data will not be saved
 * in the persistent storage, although the objects will not return to the original pre-save state if 
 * cancelled
 *
 * @param boolean $isUpdate whether this operation is a create or create operation
 * @return boolean true to continue saving, false to cancel
 */
	public function beforeSave($isUpdate) {
		return true;
	}

/**
 * Callback fired just after the flush() operation was successful
 * 
 * @param boolean $isUpdate, whether this object was just created or updated
 * @return void
 */
	public function afterSave($isUpdate) {}

/**
 * Callback fired just before an object is deleted when calling the flush() operation
 * Returning false in this callback will cancel the operation
 *
 * @return boolean
 */
	public function beforeDelete() {
		return true;
	}

/**
 * Callback fired just after the object have been deleted from persistent storage
 * when calling flush()
 *
 * @return void
 */
	public function afterDelete() {}

/**
 * Called during validation operations, before validation. Please note that custom
 * validation rules can be defined in $validate.
 *
 * @return boolean True if validate operation should continue, false to abort
 * @param $options array Options passed from model::save(), see $options of model::save().
 */
	public function beforeValidate($options = array()) {
		return true;
	}

/**
 * Returns true if all fields pass validation.
 *
 * Will validate the currently set data.  Use Model::set() or Model::create() to set the active data.
 *
 * @param array $options An optional array of custom options to be made available in the beforeValidate callback
 * @return boolean True if there are no errors
 * @link http://book.cakephp.org/view/1182/Validating-Data-from-the-Controller
 */
	public function validates($options = array()) {
		$errors = $this->invalidFields($options);
		return empty($errors);
	}

/**
 * Returns an array of fields that have failed validation. On the current model.
 *
 * @param string $options An optional array of custom options to be made available in the beforeValidate callback
 * @return array Array of invalid fields
 * @link http://book.cakephp.org/view/1182/Validating-Data-from-the-Controller
 */
	public function invalidFields($options = array()) {
		//TODO: Add behaviors support
		if ($this->beforeValidate($options) === false) {
			return false;
		}

		if (!isset($this->validate) || empty($this->validate)) {
			return $this->validationErrors;
		}

		$exists = $this->exists();
		$_validate = $this->validate;
		$whitelist = $this->whitelist;
		$methods = get_class_methods($this);

		if (!empty($options['fieldList'])) {
			$whitelist = $options['fieldList'];
		}

		if (!empty($whitelist)) {
			$validate = array();
			foreach ((array)$whitelist as $f) {
				if (!empty($this->validate[$f])) {
					$validate[$f] = $this->validate[$f];
				}
			}
			$this->validate = $validate;
		}

		foreach ($this->validate as $fieldName => $ruleSet) {
			if (!is_array($ruleSet) || (is_array($ruleSet) && isset($ruleSet['rule']))) {
				$ruleSet = array($ruleSet);
			}
			$default = array(
				'allowEmpty' => null,
				'required' => null,
				'rule' => 'blank',
				'last' => true,
				'on' => null
			);

			foreach ($ruleSet as $index => $validator) {
				if (!is_array($validator)) {
					$validator = array('rule' => $validator);
				}
				$validator = array_merge($default, $validator);

				$validationDomain = $this->validationDomain;
				if (empty($validationDomain)) {
					$validationDomain = 'default';
				}
				if (isset($validator['message'])) {
					$message = $validator['message'];
				} else {
					$message = __d('cake_dev', 'This field cannot be left blank');
				}

				$value = $this->{$fieldName};
				if (
					empty($validator['on']) || ($validator['on'] == 'create' &&
					!$exists) || ($validator['on'] == 'update' && $exists
				)) {
					$required = (
						(!isset($value) && $validator['required'] === true) ||
						(
							isset($value) && (empty($value) &&
							!is_numeric($value)) && $validator['allowEmpty'] === false
						)
					);

					if ($required) {
						$this->invalidate($fieldName, __d($validationDomain, $message));
						if ($validator['last']) {
							break;
						}
					} elseif (!is_null($value)) {
						if (empty($value) && $value != '0' && $validator['allowEmpty'] === true) {
							break;
						}
						if (is_array($validator['rule'])) {
							$rule = $validator['rule'][0];
							unset($validator['rule'][0]);
							$ruleParams = array_merge(array($value), array_values($validator['rule']));
						} else {
							$rule = $validator['rule'];
							$ruleParams = array($value);
						}

						$valid = true;

						if (in_array($rule, $methods)) {
							$ruleParams[] = $validator;
							$ruleParams[0] = array($fieldName => $ruleParams[0]);
							$valid = call_user_func_array(array($this, $rule), $ruleParams);
						} elseif (method_exists('Validation', $rule)) {
							$valid = call_user_func_array(array('Validation', $rule), $ruleParams);
						} elseif (!is_array($validator['rule'])) {
							$valid = preg_match($rule, $value);
						} elseif (Configure::read('debug') > 0) {
							trigger_error(__d('cake_dev', 'Could not find validation handler %s for %s', $rule, $fieldName), E_USER_WARNING);
						}

						if (!$valid || (is_string($valid) && strlen($valid) > 0)) {
							if (is_string($valid) && strlen($valid) > 0) {
								$validator['message'] = $valid;
							} elseif (!isset($validator['message'])) {
								if (is_string($index)) {
									$validator['message'] = $index;
								} elseif (is_numeric($index) && count($ruleSet) > 1) {
									$validator['message'] = $index + 1;
								} else {
									$validator['message'] = __d($validationDomain, $message);
								}
							}
							$this->invalidate($fieldName, $validator['message']);

							if ($validator['last']) {
								break;
							}
						}
					}
				}
			}
		}
		$this->validate = $_validate;
		return $this->validationErrors;
	}

/**
 * Marks a field as invalid, optionally setting the name of validation
 * rule (in case of multiple validation for field) that was broken.
 *
 * @param string $field The name of the field to invalidate
 * @param mixed $value Name of validation rule that was not failed, or validation message to
 *    be returned. If no validation key is provided, defaults to true.
 */
	public function invalidate($field, $value = true) {
		if (!is_array($this->validationErrors)) {
			$this->validationErrors = array();
		}
		$this->validationErrors[$field][]= $value;
	}

/**
 * Instantiates a new record or document object, initialized with any data passed in.
 *
 *	### Example
 *
 *	{{{
 *		$user = User::create(array("username" => "Jhon"));
 *		echo $user->username; // echoes "Jhon"
 *		$success = $user->save();
 *	}}}
 *
 * @param array $data set of fields to be passed to the set() function
 * @return CakeDocument new instance of object with initialized data
 */
	public static function create($data = null) {
		$document = new static();
		if ($data) {
			$document->set($data);
		}
		return $document;
	}

/**
 * For all keys different to the primaryKey in $one, the keys and values of $one are set to
 * the object properties in this document. If you provide as keys in $one names or aliases
 * for associated documents, this function will recursively set the properties on the associated objects
 *
 *	In the case of hasMany associations, if the id is set in the data, this function will try to locate the
 *	same object into the collection to be modified instead of creating a new one.
 *
 *	If $persistAssociated associated is set to true, this function will call save() on every method whose properties
 *	were set. As a side effect the validation routines for the associated object will be executed, populating this
 *	object validationErrors too. There is currently no way to disable this behavior.
 *
 * @param array $one Array list of properties to be set indexed by Document name
 * @param boolean $persistAssociated Whether this method should persist associated Documents after being set
 * @return CakeDocument this instance
 */
	public function set($one, $persistAssociated = false) {
		if (!$one) {
			return $this;
		}

		$name = get_class($this);
		if (is_array($one)) {
			$data = $one;
			if (!isset($one[get_class($this)])) {
				$data = array($name => $one);
			}
		}

		$schema = $this->schema();
		$assotiated = $this->getAssociated();
		foreach ($data as $modelName => $fieldSet) {
			if (is_array($fieldSet)) {
				if (isset($assotiated[$modelName])) {
					$this->_setAssociated($modelName, $fieldSet, $assotiated[$modelName], $persistAssociated);
					continue;
				}
				foreach ($fieldSet as $fieldName => $fieldValue) {
					if (isset($this->validationErrors[$fieldName])) {
						unset($this->validationErrors[$fieldName]);
					}
					if ($modelName === $name) {
						if (!empty($schema[$fieldName]['targetDocument'])) {
							//Trying to set an attribute managed by other document
							continue;
						}
						if ($fieldName === 'id') {
							//Only a search or a save can set the id
							continue;
						}
						if (isset($schema[$fieldName]['type']) && $schema[$fieldName]['type'] == 'date') {
							if (is_array($fieldValue)) {
								$fieldValue = $this->convertArrayToDate($fieldValue);
							} if (is_string($fieldValue)) {
								try {
									$fieldValue = new DateTime($fieldValue);
								} catch (Exception $e) {}
							}
						}
						if (method_exists($this, 'set' . $fieldName)) {
							$this->{'set' . $fieldName}($fieldValue);
						} else {
							$this->{$fieldName} = $fieldValue;
						}
					}
				}
			}
		}
		return $this;
	}

/**
 * Auxiliary function to set properties on associated documents
 *
 * @param string $modelName name or alias of the Document association 
 * @param array $fieldSet set of fields to be assigned to the associated document
 * @param ArrayObject $assocOpts containing the association options for the associated field
 * @param boolean $persist whether this function should call save() on the object after calling set() on it
 * @return void
 */
	protected function _setAssociated($modelName, $fieldSet, $assocOpts, $persist) {
		if ($assocOpts['type'] == 'hasMany' && Set::numeric(array_keys($fieldSet))) {
			$this->_setHasMany($modelName, $fieldSet, $assocOpts, $persist);
		}
		if (method_exists($this, 'set' . $assocOpts['fieldName'])) {
			$assocDocument = $this->{$assocOpts['fieldName']};
			$prop = ($assocDocument !== null) ? $assocDocument : new $assocOpts['targetDocument'];
			$prop->set($fieldSet);
			if ($assocDocument === null) {
				$this->{'set' . $assocOpts['fieldName']}($prop);
			}
			if ($persist) {
				$prop->save();
			}
			if (!empty($prop->validationErrors)) {
				$this->validationErrors[$modelName] = $prop->validationErrors;
			}
		}
	}

/**
 * Auxiliary function to set the properties on the nested hasMany values in the $fieldSet array
 *
 * @param string $modelName name or alias of the associated document to be set
 * @param array $fieldSet list of document properties to be set
 * @param ArrayObject $assocOpts containing the association options for the associated field
 * @param boolean $persist whether this function should call save() on the object after calling set() on it
 * @return void
 */
	protected function _setHasMany($modelName, $fieldSet, $assocOpts, $persist) {
		$assocDocument = $this->{$assocOpts['fieldName']};
		$hasManySetter = function ($assocDocument, $i, $value) use ($assocOpts, $persist) {
			$validationErrors = array();
			if (is_array($value)) {
				$v = isset($assocDocument[$i]) ? $assocDocument[$i] : new $assocOpts['targetDocument'];
				$v->set($value);
				if ($persist) {
					$v->save();
				}
				$assocDocument[$i] = $v;
				if (!empty($v->validationErrors)) {
					$validationErrors = $v->validationErrors;
				}
				return $validationErrors;
			}
		};

		if ($assocDocument === null || !($assocDocument instanceof Doctrine\Common\Collections\Collection)) {
			$assocDocument =  new \Doctrine\Common\Collections\ArrayCollection;
			$reflection = $this->getDocumentMetaData()->reflFields[$assocOpts['fieldName']];
			$reflection->setAccessible(true);
			$reflection->setValue($this, $assocDocument);
		}
		$editions = array();
		foreach ($fieldSet as $i => $value) {
			if (!empty($value[$this->primaryKey])) {
				$editions[$value[$this->primaryKey]] = array('index' => $i, 'value' => $value);
				unset($fieldSet[$i]);
				continue;
			}
			$index = $i;
			if (!empty($assocDocument[$index][$this->primaryKey])) {
				$index = $assocDocument->count();
			}
			$errors = $hasManySetter($assocDocument, $index, $value);
			if (!empty($errors)) {
				$this->validationErrors[$modelName][$index] = $errors;
			}
		}
		if (!empty($editions)) {
			$assocDocument->forAll(function($key, $document) use ($hasManySetter, $assocDocument, $editions, $modelName) {
				$id = $document->{$document->primaryKey};
				if (isset($editions[$id])) {
					$errors = $hasManySetter($assocDocument, $key, $editions[$id]['value']);
					if (!empty($errors)) {
						$this->validationErrors[$modelName][$editions[$id]['index']] = $errors;
					}
				}
				return true;
			});
		}
	}

/**
 * Persists a document in the datastore. Keep in mind that issuing flush() is needed after this method
 *
 * @param array $data set of fields indexed by document name to be set to the respective objects and persisted
 * @param boolean $validate whether validation should be called for this object or not
 *	NOTE: if using $data with associated documents in it, validation will be executed for the associated documents
 *	regardless of the $validate param value
 * @return boolean success if there were no validation errors or operation was not cancelled by any
 * of the callbacks
 */
	public function save($data = array(), $validate = true) {
		try {
			if (!empty($data)) {
				$this->set($data, true);
			}
			if ($validate && !$this->validates()) {
				return false;
			}
			$this->getDocumentManager()->persist($this);
		} catch (OperationCancelledException $e) {
			return false;
		}
		return true;
	}

	public function delete() {
		try {
			$this->getDocumentManager()->remove($this);
			return true;
		} catch(OperationCancelledException $e) {
			return false;
		}
	}

/**
 * Finds a single or a set of documents based on a query list of options or QueryProxy object.
 * This method accepts special find methods defined in the $findMethods property for this class,
 * such methods can either define a set of options or point to a helper find methods to be executed
 * in order to append options to the query itself. 
 *
 * ## Examples
 *
 * ### Finding by document id
 *
 *	If you already know the id for a document you can get the object directly like this
 *
 *	{{{
 *		$post = Post::find('af5b7781'); // find the post with id af5b7781
 *	}}}
 *
 *	### Finding all documents based on conditions
 *
 *	You can ask this method to return all documents passing a list of options to the query as an array
 *
 *	{{{
 *		$posts = Post::find('all'); // All posts in collection
 *		$posts = Post::find('all', array(
 *			'conditions' => array('published' => true),
 *			'order' => array('created' => 'desc'),
 *			'limit' => 10
 *			'page' => 2 	// The second page for a paginated list of 10 records each
 *		));
 *	}}}
 *
 *	### Finding a single document
 *
 *	{{{
 *		$post = Post::find('first', array(
 *			'conditions' => array('timesViewed >' => 100) // First post viewed more than 100 times
 *		));
 *	}}}
 *
 *	### Custom find methods
 *
 *	You can define your custom methods in the property $findMethods on this class, please refer to its documentation.
 *	Custom find methods can be called as static function in this class. Given that you defined a recent and an published
 *	find method in $findMethods you can call those as shown next:
 *
 *	{{{
 *		$recent = Post::recent();
 *		$published = Post::published();
 *	}}}
 *
 *	Custom find methods, when used as function needs to be defined as follows
 *
 *	{{{
 *
 *		protected static _find[CustomMethod]($state, $query, $args) {
 *			...
 *		}
 *
 *	}}}
 *
 *	CustomMethod is the name defined in the $findMethods property, with the first letter uppercased
 *	The $state argument can take the values 'before' or 'after', the function is called twice one
 *	for each of those states.
 *
 *	The 'before' state should return the query with any ned options attached to it
 *
 *	The 'after' method can return either the same query or any other return value depending o the logic of such method,
 *	for instance, the _findFirst method returns a single query result instead of the complete result collection
 *
 *	The $query parameter is an instance of a QueryProxy class, refer to its API docs for full options
 *
 *	The $args parameter is an array of special arguments passed to the query when called as a separate method. Example:
 *
 *	{{{
 *		$recent = Post::recent(10) // the $args argument will be set as array(10)
 *	}}}
 *
 * @param string $type named of the special find method to be used
 * @param array|QueryProxy $query list of options accepted by a QueryProxy or a QueryProxy object
 * @see CakeDocument::$findMethods
 * @return mixed QueryObject or any return type defined in special find methods
 */
	public static function find($type, $query = array()) {
		static::$findMethods += array('first' => true);
		if ($type != 'all' && empty(static::$findMethods[$type])) {
			return ConnectionManager::getDataSource(static::$useDbConfig)
				->getDocumentManager()
				->getRepository(get_called_class())
				->find($type);
		}

		$query = static::buildQuery($type, $query);
		if (is_null($query)) {
			return null;
		}

		if (!empty(static::$findMethods[$type]) && static::$findMethods[$type] === true) {
			$method = '_find' . ucfirst($type);
			return static::$method('after', $query, $query->getArgs());
		}
		$query->clearArgs();
		return $query;
	}

/**
 * Builds the query array that is used by the data source to generate the query to fetch the data.
 *
 * @param string $type Type of find operation (all, first,count...)
 * @param array $query Option fields (conditions / fields / limit / offset / order / page / group / callbacks)
 * @return array Query array or null if it could not be build for some reasons
 * @see Model::find()
 */
	public static function buildQuery($type = 'first', $query = array()) {
		if (!$query instanceof QueryProxy) {
			$proxy = ConnectionManager::getDataSource(static::$useDbConfig)->createQueryBuilder(get_called_class());
			if (is_array($query)) {
				$proxy->addQueryArray($query);
			}
			$query = $proxy;
		}

		if ($type !== 'all') {
			if (static::$findMethods[$type] === true) {
				$method = '_find' . ucfirst($type);
				$query =  static::$method('before', $query, $query->getArgs());
			} elseif (is_array(static::$findMethods[$type])) {
				$query->addQueryArray(static::$findMethods[$type]);
			}
		}

		if (!isset($query['page']) || intval($query['page']) < 1) {
			$query['page'] = 1;
		}
		if (!isset($query['offset']) && $query['page'] > 1 && !empty($query['limit'])) {
			$query['offset'] = ($query['page'] - 1) * $query['limit'];
		}

		return $query;
	}

/**
 * Custom finder method to return the first result form any query
 *
 * @param string $state `before` ore `after`
 * @param array|QueryProxy $query 
 * @return CakeDocument
 */
	protected static function _findFirst($state, $query) {
		if ($state == 'before') {
			$query->limit(1);
			return $query;
		}
		return $query->getSingleResult();
	}

/**
 * Returns a list of all associated documents mapped by a property in this object
 * pointing to an array containing all the options set for such relation including the
 * the type of relationship (hasOne, hasMany, belongsTo)
 *
 * Return example:
 *
 *	{{{
 *		array(
 *			'Address' => array('type' => 'hasOne')
 *			'PhoneNumber' => array('type' => 'hasMany', 'limit' => 5),
 *			'ParentCompany' => array('type' => 'belongsTo')
 *		)
 *	}}}
 *
 * @return array
 */
	public function getAssociated() {
		$schema = $this->schema();
		$relations = array();
		foreach ($schema as $property => $opts) {
			if (empty($opts['targetDocument'])) {
				continue;
			}
			if ($opts['type'] == 'one') {
				$type = 'hasOne';
				$type = (!empty($opts['belongsTo'])) ? 'belongsTo' : $type;
			}
			if ($opts['type'] == 'many') {
				$type = 'hasMany';
			}
			$alias = $opts['targetDocument'];
			if (!empty($opts['alias'])) {
				$alias = $opts['alias'];
			}
			$opts['type'] = $type;
			$relations[$alias] = $opts;
		}
		return $relations;
	}

/**
 * Returns whether a field is a mapped property by the DocumentManager
 * Mapped properties are annotated with one of the valid Doctrine's Types
 *
 * @param string $field 
 * @return boolean
 */
	public function hasField($field) {
		$schema = $this->schema();
		return isset($schema[$field]);
	}

/**
 * Converts a datetime array sent from the form helper into a DateTime object
 *
 * @param mixed $data An array or object to be deconstructed into a DateTime
 * @return DateTime
 */
	public function convertArrayToDate($data) {
		$useNewDate = (isset($data['year']) || isset($data['month']) ||
			isset($data['day']) || isset($data['hour']) || isset($data['minute']));

		$dateFields = array('Y' => 'year', 'm' => 'month', 'd' => 'day', 'H' => 'hour', 'i' => 'min', 's' => 'sec');
		$timeFields = array('H' => 'hour', 'i' => 'min', 's' => 'sec');
		$date = array();

		if (isset($data['hour']) && isset($data['meridian']) && $data['hour'] != 12 && 'pm' == $data['meridian']) {
			$data['hour'] = $data['hour'] + 12;
		}
		if (isset($data['hour']) && isset($data['meridian']) && $data['hour'] == 12 && 'am' == $data['meridian']) {
			$data['hour'] = '00';
		}
	
		foreach ($timeFields as $key => $val) {
			if (!isset($data[$val]) || $data[$val] === '0' || $data[$val] === '00') {
				$data[$val] = '00';
			} elseif ($data[$val] === '') {
				$data[$val] = '';
			} else {
				$data[$val] = sprintf('%02d', $data[$val]);
			}
			if (!empty($data[$val])) {
				$date[$key] = $data[$val];
			} else {
				return null;
			}
		}

		foreach ($dateFields as $key => $val) {
			if ($val == 'hour' || $val == 'min' || $val == 'sec') {
				if (!isset($data[$val]) || $data[$val] === '0' || $data[$val] === '00') {
					$data[$val] = '00';
				} else {
					$data[$val] = sprintf('%02d', $data[$val]);
				}
			}
			if (!isset($data[$val]) || isset($data[$val]) && (empty($data[$val]) || $data[$val][0] === '-')) {
				return null;
			}
			if (isset($data[$val]) && !empty($data[$val])) {
				$date[$key] = $data[$val];
			}
		}

		$day = empty($date['Y']) ? null : $date['Y'] . '-' . $date['m'] . '-' . $date['d'] . ' ';
		$hour = empty($date['H']) ? null : $date['H'] . ':' . $date['i'] . ':' . $date['s'];
		return new DateTime($day . $hour);
	}

}