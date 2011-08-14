<?php
App::uses('AuthComponent', 'Controller/Component');

class DocumentAuthComponent extends AuthComponent {

	protected static $loggedInUser;
	protected static $documentModel = 'User';

/**
 * Log a user in. If a $user is provided that data will be stored as the logged in user.  If `$user` is empty or not
 * specified, the request will be used to identify a user. If the identification was successful,
 * the user record is written to the session key specified in AuthComponent::$sessionKey.
 *
 * @param mixed $user Either an array of user data, or null to identify a user using the current request.
 * @return boolean True on login success, false on failure
 * @link http://book.cakephp.org/view/1261/login
 */
	public function login($user = null) {
		$this->__setDefaults();

		if (empty($user)) {
			$user = $this->identify($this->request, $this->response);
		}
		if (is_object($user)) {
			$this->Session->write(static::$sessionKey, $user->id);
			static::$loggedInUser = $user;
		} else {
			$this->Session->write(static::$sessionKey, $user);
		}

		return $this->loggedIn();
	}

/**
 * Logs a user out, and returns the login action to redirect to.
 * Triggers the logout() method of all the authenticate objects, so they can perform
 * custom logout logic.  AuthComponent will remove the session data, so
 * there is no need to do that in an authentication object.
 *
 * @param mixed $url Optional URL to redirect the user to after logout
 * @return string AuthComponent::$loginAction
 * @see AuthComponent::$loginAction
 * @link http://book.cakephp.org/view/1262/logout
 */
	public function logout() {
		static::$loggedInUser = null;
		return parent::logout();
	}


/**
 * Use the configured authentication adapters, and attempt to identify the user
 * by credentials contained in $request.
 *
 * @param CakeRequest $request The request that contains authentication data.
 * @return array User record data, or false, if the user could not be identified.
 */
	public function identify(CakeRequest $request, CakeResponse $response) {
		if (empty($this->_authenticateObjects)) {
			$this->constructAuthenticate();
		}
		foreach ($this->_authenticateObjects as $auth) {
			$result = $auth->authenticate($request, $response);
			if (!empty($result)) {
				return $result;
			}
		}
		return false;
	}

/**
 * Get the current user from the session.
 *
 * @param string $key field to retrive.  Leave null to get entire User record
 * @return mixed User record. or null if no user is logged in.
 * @link http://book.cakephp.org/view/1264/user
 */
	public static function user($key = null) {
		if (!empty(static::$loggedInUser)) {
			return static::$loggedInUser;
		}

		if (!CakeSession::check(static::$sessionKey)) {
			return null;
		}

		$user = CakeSession::read(static::$sessionKey);
		if (is_string($user)) {
			$class = static::$documentModel;
			$user = static::$loggedInUser = $class::find($user);
		}

		if ($key == null) {
			return $user;
		}

		if (isset($user[$key])) {
			return $user[$key];
		}
		return null;
	}

/**
 * Similar to AuthComponent::user() except if the session user cannot be found, connected authentication
 * objects will have their getUser() methods called.  This lets stateless authentication methods function correctly.
 *
 * @return boolean true if a user can be found, false if one cannot.
 */
	protected function _getUser() {
		$user = $this->user();
		if ($user) {
			return true;
		}
		if (empty($this->_authenticateObjects)) {
			$this->constructAuthenticate();
		}
		foreach ($this->_authenticateObjects as $auth) {
			$result = $auth->getUser($this->request);
			if (!empty($result)) {
				return true;
			}
		}
		return false;
	}

/**
 * Check whether or not the current user has data in the session, and is considered logged in.
 *
 * @return boolean true if the user is logged in, false otherwise
 * @access public
 */
	public function loggedIn() {
		$user = static::user();
		return !empty($user);
	}

/**
 * Attempts to introspect the correct values for object properties including
 * $userModel and $sessionKey.
 *
 * @param object $controller A reference to the instantiating controller object
 * @return boolean
 * @access private
 */
	function __setDefaults() {
		$result = parent::__setDefaults();
		if (!empty($this->userModel)) {
			list($plugin, $class) = pluginSplit($this->userModel, null, true);
			App::uses($class, $plugin . 'Model');
			static::$documentModel = $class;
		}
		return $result;
	}

}