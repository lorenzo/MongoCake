<?php
App::uses('PaginatorComponent', 'Controller/Component');

class DocumentPaginatorComponent extends PaginatorComponent {

/**
 * Handles automatic pagination of model records.
 *
 * @param mixed $object Model to paginate (e.g: model instance, or 'Model', or 'Model.InnerModel')
 * @param mixed $scope Additional find conditions to use while paginating
 * @param array $whitelist List of allowed fields for ordering.  This allows you to prevent ordering 
 *   on non-indexed, or undesirable columns.
 * @return array Model query results
 */
	public function paginate($object = null, $scope = array(), $whitelist = array()) {
		if (is_array($object)) {
			$whitelist = $scope;
			$scope = $object;
			$object = null;
		}

		$object = $this->_getObject($object);

		if (!is_object($object)) {
			throw new MissingModelException($object);
		}

		$class = get_class($object);
		$options = $this->mergeOptions($class);
		$options = $this->validateSort($object, $options, $whitelist);
		$options = $this->checkLimit($options);

		$conditions = $fields = $order = $limit = $page = null;

		if (!isset($options['conditions'])) {
			$options['conditions'] = array();
		}

		$type = 'all';

		if (isset($options[0])) {
			$type = $options[0];
			unset($options[0]);
		}

		extract($options);

		if (is_array($scope) && !empty($scope)) {
			$conditions = array_merge($conditions, $scope);
		} elseif (is_string($scope)) {
			$conditions = array($conditions, $scope);
		}

		$extra = array_intersect_key($options, compact(
			'conditions', 'fields', 'order', 'limit', 'page'
		));

		if (intval($page) < 1) {
			$page = 1;
		}
		$page = $options['page'] = (int)$page;
		$parameters = compact('conditions', 'fields', 'order', 'limit', 'page');
		$results = $class::find($type, array_merge($parameters, $extra));
		$defaults = $this->getDefaults(get_class($object));
		unset($defaults[0]);
		$count = $results->count(false);
		$pageCount = intval(ceil($count / $limit));

		$paging = array(
			'page' => $page,
			'current' => count($results),
			'count' => $count,
			'prevPage' => ($page > 1),
			'nextPage' => ($count > ($page * $limit)),
			'pageCount' => $pageCount,
			'order' => $order,
			'limit' => $limit,
			'options' => Set::diff($options, $defaults),
			'paramType' => $options['paramType']
		);
		if (!isset($this->Controller->request['paging'])) {
			$this->Controller->request['paging'] = array();
		}
		$this->Controller->request['paging'] = array_merge(
			(array)$this->Controller->request['paging'],
			array($class => $paging)
		);

		if (
			!in_array('Paginator', $this->Controller->helpers) &&
			!array_key_exists('Paginator', $this->Controller->helpers)
		) {
			$this->Controller->helpers[] = 'Paginator';
		}
		return $results;
	}

/**
 * Validate that the desired sorting can be performed on the $object.  Only fields or 
 * virtualFields can be sorted on.  The direction param will also be sanitized.  Lastly
 * sort + direction keys will be converted into the model friendly order key.
 *
 * You can use the whitelist parameter to control which columns/fields are available for sorting.
 * This helps prevent users from ordering large result sets on un-indexed values.
 *
 * @param Model $object The model being paginated.
 * @param array $options The pagination options being used for this request.
 * @param array $whitelist The list of columns that can be used for sorting.  If empty all keys are allowed.
 * @return array An array of options with sort + direction removed and replaced with order if possible.
 */
	public function validateSort($object, $options, $whitelist = array()) {
		if (isset($options['sort'])) {
			$direction = null;
			if (isset($options['direction'])) {
				$direction = strtolower($options['direction']);
			}
			if ($direction != 'asc' && $direction != 'desc') {
				$direction = 'asc';
			}
			$options['order'] = array($options['sort'] => $direction);
		}

		if (!empty($whitelist)) {
			$field = key($options['order']);
			if (!in_array($field, $whitelist)) {
				$options['order'] = null;
			}
		}

		if (!empty($options['order']) && is_array($options['order'])) {
			$key = $field = key($options['order']);
			$value = $options['order'][$key];
			unset($options['order'][$key]);
			$options['order'][$field] = $value;
		}

		return $options;
	}
}