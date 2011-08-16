<?php

use Doctrine\ODM\MongoDB\DocumentManager,
	Doctrine\ODM\MongoDB\Query\Expr;

/**
 * This class is a hybrid between a QueryBuilder and a lazy loader for query results
 * It inherits all methods from the `Builder` class but at the same time it is able
 * to return query results if tried to be iterated
 *
 * It also acts a bridge between the query API and the document itself to call custom
 * finder methods in the Document class to add or modify any property on the active query
 * being built.
 *
 * @package MongoCake.Query
 */
class QueryProxy extends \Doctrine\ODM\MongoDB\Query\Builder implements ArrayAccess, IteratorAggregate, Countable {

/**
 * Iterator instance
 *
 * @var Iterator
 */
	private $iterator;

/**
 * Whether the query changed after getting results from it
 *
 * @var boolean
 */
	protected $queryChanged = false;

/**
 * Name of the document associated to this query
 * The document name will be used to connect to the correct repository
 * to get results from it
 *
 * @var string
 */
	protected $documentName;

/**
 * List of additional arguments that needs to be passed to the last call of a custom finder in the document class
 *
 * @var array
 */
	protected $args = array();

/**
 * Magic function to acts a a bridge for calling custom finder methods in the document class
 *
 * @param string $method method to be called in the document class
 * @param array $arguments list of arguments to be passed to it
 * @return mixed
 */
	public function __call($method, $arguments) {
		$class = $this->documentName;
		if (!empty($class)) {
			$this->args = $arguments;
			return $class::$method($this);
		}
		throw new BadMethodCallException('No class specified to call find method');
	}

/**
 * Returns the list of arguments to be passed to the last custom finder method called
 *
 * @return array
 */
	public function getArgs() {
		return $this->args;
	}

/**
 * Clears the arguments array. This arguments where created from the last call to a custom finder in
 * the document class and was managed by the magic __call() function in this class
 *
 * @return void
 */
	public function clearArgs() {
		$this->args = array();
	}

/**
 * Object's constructor
 *
 * @param DocumentManager $dm 
 * @param Cmd $cmd 
 * @param string $documentName 
 */
	public function __construct(DocumentManager $dm, $cmd, $documentName = null) {
		$this->documentName = is_array($documentName) ? current($documentName) : $documentName;
		parent::__construct($dm, $cmd, $documentName);
	}

/**
 * Returns true if a query parameter is set
 *
 * @param string $property 
 * @return boolean
 */
	public function oxffsetExists($property) {
		return isset($this->query[$property]);
	}

/**
 * Returns a query parameter
 *
 * @param string $property 
 * @return mixed
 */
	public function offsetGet($property) {
		if ($property === 'args') {
			return $this->getArgs();
		}
		if (isset($this->query[$property])) {
			return $this->query[$property];
		}
	}

/**
 * Sets a query parameter
 *
 * @param string $property 
 * @param mixed $value 
 * @return void
 */
	public function offsetSet($property, $value) {
		$this->addQueryArray(array($property => $value));
	}

/**
 * Unsets a query parameter
 *
 * @param string $offset 
 * @return void
 */
	public function offsetUnset($offset) {
		if (isset($query[$property])) {
			$this->query['property'] = null;
		}
	}

/**
 * Returns an iterator for the query results
 *
 * @return Iterator
 */
	public function getIterator() {
		if ($this->queryChanged || $this->iterator === null) {
			$iterator = $this->getQuery()->iterate();
			if ($iterator !== null && !$iterator instanceof Iterator) {
				throw new \BadMethodCallException('Query execution did not return an iterator. This query may not support returning iterators. ');
			}
			$this->iterator = $iterator;
		}
		return $this->iterator;
    }

/**
 * Count the number of results for this query.
 *
 * @param boolean $resultSetOnly 
 * @return integer
 */
    public function count($resultSetOnly = true) {
		if (!is_object($this->getIterator())) {
			return 0;
		}
        return $this->getIterator()->count($resultSetOnly);
    }

/**
 * Returns the first result for the current query
 *
 * @return mixed
 */
	public function getSingleResult() {
		return $this->getIterator()->getSingleResult();
	}

/**
 * Converts the query result in a plain array
 *
 * @return array
 */
    public function toArray() {
        return $this->getIterator()->toArray();
    }

/**
 * Sets a CakePHP-style query options array to this object
 *
 * @param array $query 
 * @return QueryProxy this instance
 */
	public function addQueryArray(array $query) {
		if (!empty($query['args'])) {
			$this->args = $query['args'];
			unset($query['args']);
		}
		if (!empty($query['fields'])) {
			call_user_func_array(array($this, 'select'), $query['fields']);
			unset($query['fields']);
		}

		if (!empty($query['conditions'])) {
			$this->addConditions($query['conditions']);
			unset($query['conditions']);
		}

		if (!empty($query['limit'])) {
			$this->limit($query['limit']);
		}

		if (isset($query['offset'])) {
			$this->skip($query['offset']);
		}

		if (!empty($query['order'])) {
			$this->sort($query['order']);
			unset($query['order']);
		}

		$this->query = $query + $this->query;
		return $this;
	}

/**
 * Parses a CakePHP-style conditions array and sets them to
 * this query
 *
 * @param array $conditions 
 * @return QueryProxy this instance
 */
	public function addConditions($conditions) {
		foreach ($conditions as $field => $value) {
			$operator = '=';
			if (strpos($field, ' ')) {
				list($field, $operator) = explode(" ", $field, 2);
			}
			if (strpos($field, '.')) {
				list($document, $f) = explode('.', $field, 2);
				$field = ($document === $this->documentName) ? $f : $field;
			}
			switch ($operator) {
				case 'size' :
				case 'type' :
				case 'all' :
					$this->field($field)->{$operator}($value);
					break;
				case 'mod' :
					$this->field($field)->mod($value[0], $value[1]);
					break;
				case 'between' :
				case 'between ? and ?' :
					$this->field($field)->range(array_shift($value), array_shift($value));
					break;
				case '<=' :
						$this->field($field)->lte($value);
					break;
				case '<' :
						$this->field($field)->lt($value);
					break;
				case '>=' :
						$this->field($field)->gte($value);
					break;
				case '>' :
						$this->field($field)->gt($value);
					break;
				case '!=' :
					if (is_array($value)) {
						$this->field($field)->notIn($value);
					} else {
						$this->field($field)->notEqual($value);
					}
					break;
				default:
					if (is_array($value)) {
						$this->field($field)->in($value);
					} else {
						$this->field($field)->equals($value);
					}
			}
		}
	}

/**
 * Set slave okay.
 *
 * @param bool $bool
 * @return QueryProxy this instance
 */
	public function slaveOkay($bool = true) {
		$this->queryChanged = true;
		return parent::slaveOkay($bool);
	}

/**
 * Set snapshot.
 *
 * @param bool $bool
 * @return QueryProxy this instance
 */
	public function snapshot($bool = true) {
		$this->queryChanged = true;
		return parent::snapshot($bool);
	}

/**
 * Set immortal.
 *
 * @param bool $bool
 * @return QueryProxy this instance
 */
	public function immortal($bool = true) {
		$this->queryChanged = true;
		parent::immortal($bool);
	}

/**
 * Pass a hint to the Cursor
 *
 * @param string $keyPattern
 * @return QueryProxy this instance
 */
	public function hint($keyPattern) {
		$this->queryChanged = true;
		return parent::hint($keyPattern);
	}


	public function returnNew($bool = true) {
		return parent::returnNew($bool);
	}

	public function upsert($bool = true) {
		$this->queryChanged = true;
		return parent::upsert($bool);
	}

/**
 * Perform an operation similar to SQL's GROUP BY command
 *
 * @param array $keys
 * @param array $initial
 * @return QueryProxy this instance
 */
	public function group($keys, array $initial) {
		$this->queryChanged = true;
		return parent::group($keys, $initial);
	}

/**
 * The distinct method queries for a list of distinct values for the given
 * field for the document being queried for.
 *
 * @param string $field
 * @return QueryProxy this instance
 */
	public function distinct($field) {
		$this->queryChanged = true;
		return parent::distinct($field);
	}

/**
 * The fields to select.
 *
 * @param string $fieldName
 * @return QueryProxy this instance
 */
	public function select($fieldName = null)	{
		$this->queryChanged = true;
		$select = func_get_args();
		foreach ($select as $fieldName) {
			$this->query['select'][$fieldName] = 1;
		}
		return $this;
	}

/**
 * The fields not to select.
 *
 * @param string $fieldName
 * @return QueryProxy this instance
 */
	public function exclude($fieldName = null) {
		$this->queryChanged = true;
		$select = func_get_args();
		foreach ($select as $fieldName) {
			$this->query['select'][$fieldName] = 0;
		}
		return $this;
	}

/**
 * Select a slice of an embedded document.
 *
 * @param string $fieldName
 * @param integer $skip
 * @param integer $limit
 * @return QueryProxy this instance
 */
	public function selectSlice($fieldName, $skip, $limit = null) {
		$this->queryChanged = true;
		return parent::selectSlice($fieldName, $keys, $limit);
	}

/**
 * Add where near criteria.
 *
 * @param string $x
 * @param string $y
 * @return QueryProxy this instance
 */
	public function near($value) {
		$this->queryChanged = true;
		return parent::near($value);
	}

/**
 * Add a new where criteria erasing all old criteria.
 *
 * @param string $value
 * @return QueryProxy this instance
 */
	public function equals($value) {
		$this->queryChanged = true;
		return parent::equals($value);
	}

/**
 * Add $where javascript function to reduce result sets.
 *
 * @param string $javascript
 * @return QueryProxy this instance
 */
	public function where($javascript) {
		$this->queryChanged = true;
		return parent::where($javascript);
	}

/**
 * Add a new where in criteria.
 *
 * @param mixed $values
 * @return QueryProxy this instance
 */
	public function in($values) {
		$this->queryChanged = true;
		return parent::in($values);
	}

/**
 * Add where not in criteria.
 *
 * @param mixed $values
 * @return QueryProxy this instance
 */
	public function notIn($values) {
		$this->queryChanged = true;
		return parent::notIn($values);
	}

/**
 * Add where not equal criteria.
 *
 * @param string $value
 * @return QueryProxy this instance
 */
	public function notEqual($value) {
		$this->queryChanged = true;
		return parent::notEqual($value);
	}

/**
 * Add where greater than criteria.
 *
 * @param string $value
 * @return QueryProxy this instance
 */
	public function gt($value) {
		$this->queryChanged = true;
		return parent::gt($value);
	}

/**
 * Add where greater than or equal to criteria.
 *
 * @param string $value
 * @return QueryProxy this instance
 */
	public function gte($value) {
		$this->queryChanged = true;
		return parent::gte($value);
	}

/**
 * Add where less than criteria.
 *
 * @param string $value
 * @return QueryProxy this instance
 */
	public function lt($value) {
		$this->queryChanged = true;
		return parent::lt($value);
	}

/**
 * Add where less than or equal to criteria.
 *
 * @param string $value
 * @return QueryProxy this instance
 */
	public function lte($value) {
		$this->queryChanged = true;
		return parent::lte($value);
	}

/**
 * Add where range criteria.
 *
 * @param string $start
 * @param string $end
 * @return QueryProxy this instance
 */
	public function range($start, $end) {
		$this->queryChanged = true;
		return parent::range($start, $end);
	}

/**
 * Add where size criteria.
 *
 * @param string $size
 * @return QueryProxy this instance
 */
	public function size($size) {
		$this->queryChanged = true;
		return parent::size($size);
	}

/**
 * Add where exists criteria.
 *
 * @param string $bool
 * @return QueryProxy this instance
 */
	public function exists($bool) {
		$this->queryChanged = true;
		return parent::notEqual($bool);
	}

/**
 * Add where type criteria.
 *
 * @param string $type
 * @return QueryProxy this instance
 */
	public function type($type) {
		$this->queryChanged = true;
		return parent::type($type);
	}

/**
 * Add where all criteria.
 *
 * @param mixed $values
 * @return QueryProxy this instance
 */
	public function all($values) {
		$this->queryChanged = true;
		return parent::all($values);
	}

/**
 * Add where mod criteria.
 *
 * @param string $mod
 * @return QueryProxy this instance
 */
	public function mod($mod) {
		$this->queryChanged = true;
		return parent::mod($mod);
	}

/**
 * Add where $within $box query.
 *
 * @param string $x1
 * @param string $y1
 * @param string $x2
 * @param string $y2
 * @return QueryProxy this instance
 */
	public function withinBox($x1, $y1, $x2, $y2) {
		$this->queryChanged = true;
		return parent::withinBox($x1, $y1, $x2, $y2);
	}

/**
 * Add where $within $center query.
 *
 * @param string $x
 * @param string $y
 * @param string $radius
 * @return QueryProxy this instance
 */
	public function withinCenter($x, $y, $radius) {
		$this->queryChanged = true;
		return parent::withinCenter($x, $y, $radius);
	}

/**
 * Set sort and erase all old sorts.
 *
 * @param string $order
 * @return QueryProxy this instance
 */
	public function sort($fieldName, $order = null) {
		$this->queryChanged = true;
		return parent::sort($fieldName, $order);
	}

/**
 * Set the Document limit for the Cursor
 *
 * @param string $limit
 * @return QueryProxy this instance
 */
	public function limit($limit) {
		$this->queryChanged = true;
		return parent::limit($limit);
	}

/**
 * Set the number of Documents to skip for the Cursor
 *
 * @param string $skip
 * @return QueryProxy this instance
 */
	public function skip($skip) {
		$this->queryChanged = true;
		return parent::skip($skip);
	}

/**
 * Specify a map reduce operation for this query.
 *
 * @param mixed $map
 * @param mixed $reduce
 * @param array $options
 * @return QueryProxy this instance
 */
	public function mapReduce($map, $reduce, array $out = array('inline' => true), array $options = array()) {
		$this->queryChanged = true;
		return parent::mapReduce($map, $reduce, $out, $options);
	}

/**
 * Specify a map operation for this query.
 *
 * @param string $map
 * @return QueryProxy this instance
 */
	public function map($map) {
		$this->queryChanged = true;
		return parent::map($map);
	}

/**
 * Specify a reduce operation for this query.
 *
 * @param string $reduce
 * @return QueryProxy this instance
 */
	public function reduce($reduce) {
		$this->queryChanged = true;
		return parent::reduce($map);
	}

/**
 * Specify output type for mar/reduce operation.
 *
 * @param array $out
 * @return QueryProxy this instance
 */
	public function out(array $out) {
		$this->queryChanged = true;
		return parent::out($out);
	}

/**
 * Specify the map reduce array of options for this query.
 *
 * @param array $options
 * @return QueryProxy this instance
 */
	public function mapReduceOptions(array $options) {
		$this->queryChanged = true;
		return parent::mapReduceOptions($options);
	}

/**
 * Set field to value.
 *
 * @param mixed $value
 * @param boolean $atomic
 * @return QueryProxy this instance
 */
	public function set($value, $atomic = true) {
		$this->queryChanged = true;
		return parent::set($value, $atomic);
	}

/**
 * Increment field by the number value if field is present in the document,
 * otherwise sets field to the number value.
 *
 * @param integer $value
 * @return QueryProxy this instance
 */
	public function inc($value) {
		$this->queryChanged = true;
		return parent::map($map);
	}

/**
 * Deletes a given field.
 *
 * @return QueryProxy this instance
 */
	public function unsetField() {
		$this->queryChanged = true;
		return parent::unsetField();
	}

/**
 * Appends value to field, if field is an existing array, otherwise sets
 * field to the array [value] if field is not present. If field is present
 * but is not an array, an error condition is raised.
 *
 * @param mixed $value
 * @return QueryProxy this instance
 */
	public function push($value) {
		$this->queryChanged = true;
		return parent::push($value);
	}

/**
 * Appends each value in valueArray to field, if field is an existing
 * array, otherwise sets field to the array valueArray if field is not
 * present. If field is present but is not an array, an error condition is
 * raised.
 *
 * @param array $valueArray
 * @return QueryProxy this instance
 */
	public function pushAll(array $valueArray) {
		$this->queryChanged = true;
		return parent::pushAll($valueArray);
	}

/**
 * Adds value to the array only if it's not in the array already.
 *
 * @param mixed $value
 * @return QueryProxy this instance
 */
	public function addToSet($value) {
		$this->queryChanged = true;
		return parent::addToSet($value);
	}

/**
 * Adds values to the array only if they are not in the array already.
 *
 * @param array $values
 * @return QueryProxy this instance
 */
	public function addManyToSet(array $values) {
		$this->queryChanged = true;
		return parent::addManyToSet($values);
	}

/**
 * Removes first element in an array
 *
 * @return QueryProxy this instance
 */
	public function popFirst() {
		$this->queryChanged = true;
		return parent::map($map);
	}

/**
 * Removes last element in an array
 *
 * @return QueryProxy this instance
 */
	public function popLast() {
		$this->queryChanged = true;
		parent::popLast();
	}

/**
 * Removes all occurrences of value from field, if field is an array.
 * If field is present but is not an array, an error condition is raised.
 *
 * @param mixed $value
 * @return QueryProxy this instance
 */
	public function pull($value) {
		$this->queryChanged = true;
		parent::pull($value);
	}

/**
 * Removes all occurrences of each value in value_array from field, if
 * field is an array. If field is present but is not an array, an error
 * condition is raised.
 *
 * @param array $valueArray
 * @return QueryProxy this instance
 */
	public function pullAll(array $valueArray) {
		$this->queryChanged = true;
		parent::pullAll($valueArray);
	}

/**
 * Adds an "or" expression to the current query.
 *
 * You can create the expression using the expr() method:
 *
 *	   $qb = $this->createQueryBuilder('User');
 *	   $qb
 *		  ->addOr($qb->expr()->field('first_name')->equals('Kris'))
 *		  ->addOr($qb->expr()->field('first_name')->equals('Chris'));
 *
 * @param array|QueryBuilder $expression
 * @return QueryProxy this instance
 */
	public function addOr($expression) {
		$this->queryChanged = true;
		parent::addOr($expression);
	}

/**
 * Adds an "elemMatch" expression to the current query.
 *
 * You can create the expression using the expr() method:
 *
 *	   $qb = $this->createQueryBuilder('User');
 *	   $qb
 *		   ->field('phonenumbers')
 *		   ->elemMatch($qb->expr()->field('phonenumber')->equals('6155139185'));
 *
 * @param array|QueryBuilder $expression
 * @return QueryProxy this instance
 */
	public function elemMatch($expression) {
		$this->queryChanged = true;
		parent::elemMatch($expression);
	}

/**
 * Adds a "not" expression to the current query.
 *
 * You can create the expression using the expr() method:
 *
 *	   $qb = $this->createQueryBuilder('User');
 *	   $qb->field('id')->not($qb->expr()->in(1));
 *
 * @param array|QueryBuilder $expression
 * @return QueryProxy this instance
 */
	public function not($expression) {
		$this->queryChanged = true;
		parent::not($expression);
	}


/**
 * Whether or not to hydrate the data to documents.
 *
 * @param bool $shouldRefresh
 * @return QueryProxy this instance
 */
	public function hydrate($shouldHydrate = true) {
		$this->queryChanged = true;
		return parent::hydrate($shouldHydrate);
	}

/**
 * Whether or not to refresh the data for documents that are already in the identity map.
 *
 * @param bool $shouldRefresh
 * @return QueryProxy this instance
 */
	public function refresh($shouldRefresh = true){
		$this->queryChanged = true;
		return parent::refresh($shouldHydrate);
	}

/**
 * Change the query type to find and optionally set and change the class being queried.
 * Optionally sets the document name for the query
 *
 * @param string $documentName
 * @return QueryProxy this instance
 */
	public function find($documentName = null) {
		$this->queryChanged = true;
		return parent::find($s);
	}

/**
 * Sets a flag for the query to be executed as a findAndUpdate query.
 * Optionally sets the document name for the query
 *
 * @param string $documentName
 * @return QueryProxy this instance
 */
	public function findAndUpdate($documentName = null) {
		$this->queryChanged = true;
		return parent::findAndUpdate($documentName);
	}

/**
 * Sets a flag for the query to be executed as a findAndUpdate query.
 * Optionally sets the document name for the query
 *
 * @param string $documentName
 * @return QueryProxy this instance
 */
	public function findAndRemove($documentName = null) {
		$this->queryChanged = true;
		return parent::findAndRemove();
	}

/**
 * Change the query type to update and optionally set and change the class being queried.
 *
 * @param string $documentName
 * @return QueryProxy this instance
 */
	public function update($documentName = null) {
		$this->queryChanged = true;
		return parent::update($documentName);
	}

/**
 * Change the query type to insert and optionally set and change the class being queried.
 *
 * @param string $documentName
 * @return QueryProxy this instance
 */
	public function insert($documentName = null) {
		$this->queryChanged = true;
		return parent::insert($documentName);
	}

/**
 * Change the query type to remove and optionally set and change the class being queried.
 *
 * @param string $documentName
 * @return QueryProxy this instance
 */
	public function remove($documentName = null) {
		$this->queryChanged = true;
		return parent::remove($documentName);
	}

/**
 * Adds a condition to a the query where the supplied object should exist in the associated
 * collection represented by a field.
 *
 * ##Example:
 *
 *	{{{
 *		$kris = new Person('Kris');
 *		$jon = new Person('Jon');
 *		$kris->save();
 *		$jon->save();
 *		// ... after flush ...
 *		$jon->bestFriend = $kris;
 *		$qb = $person->getDataSource()->createQueryBuilder('Person');
 *		$qb->field('bestFriend')->references($kris);
 *
 *		// You should expect this
 *		$jon === $qb->getSingleResult();
 *	}}}
 *
 * @param object $document instance of any document to be used as reference for the collection
 * @return QueryProxy this instance
 */
	public function references($document) {
		$this->queryChanged = true;
		return parent::references($document);
	}

/**
 * Adds a condition to a the query where the supplied object should exist in the associated
 * collection represented by a field.
 *
 * ##Example:
 *
 *	{{{
 *		$kris = new Person('Kris');
 *		$jon = new Person('Jon');
 *		$kris->save();
 *		$jon->save();
 *		// ... after flush ...
 *		$jon->friends[] = $kris;
 *		$qb = $person->getDataSource()->createQueryBuilder('Person');
 *		$qb->field('friends')->includesReferenceTo($kris);
 *
 *		// You should expect this
 *		$jon === $qb->getSingleResult();
 *	}}}
 *
 * @param object $document instance of any document to be used as reference for the collection
 * @return QueryProxy this instance
 */
	public function includesReferenceTo($document) {
		$this->queryChanged = true;
		return parent::includesReferenceTo($document);
	}

}