<?php
/**
 * Klein (klein.php) - A lightning fast router for PHP
 *
 * @author      Chris O'Hara <cohara87@gmail.com>
 * @author      Trevor Suarez (Rican7) (contributor and v2 refactorer)
 * @copyright   (c) Chris O'Hara
 * @link        https://github.com/chriso/klein.php
 * @license     MIT
 */

namespace Klein;

use \BadMethodCallException;

use \Klein\Exceptions\UnknownServiceException;
use \Klein\Exceptions\DuplicateServiceException;

if(!defined('LIMB_UNDEFINED'))
	define('LIMB_UNDEFINED', 'undefined' . microtime());

/**
 * App
 *
 * @package    Klein
 */
class App
{
	/**
	 * Class properties
	 */

	/**
	 * The array of app services
	 *
	 * @var array
	 * @access protected
	 */
	protected $services = array();

	/**
	 * The array of return values
	 *
	 * @var array
	 * @access protected
	 */
	protected $values = array();

	protected $mode;

	protected $var_dir;

	public static function i()
	{
		static $i;
		if(!$i)
		{
			$class = get_called_class();
			$i = new $class;
		}
		return $i;
	}

	/**
	 * Register new service
	 * @param $name
	 * @param $closure
	 */
	public function service($name, $closure)
	{
		$this->services[$name] = $closure;
	}

	/**
	 * Instantiate object from stored closure or return instance
	 * @param $name
	 * @return mixed
	 * @throws Exceptions\UnknownServiceException
	 */
	public function instantiate($name)
	{
		if (!isset($this->services[$name])) {
			throw new UnknownServiceException('Unknown service ' . $name);
		}
		$service = $this->services[$name];
		if(!is_callable($service))
			return $service;

		$args = array_slice(func_get_args(), 1);
		return call_user_func_array($service, $args);
	}

	/**
	 * Magic "__call" method
	 *
	 * Allows the ability to arbitrarily call a property as a callable method
	 * Allow callbacks to be assigned as properties and called like normal methods
	 *
	 * @param callable $method The callable method to execute
	 * @param array $args The argument array to pass to our callback
	 * @throws BadMethodCallException   If a non-registered method is attempted to be called
	 * @access public
	 * @return void
	 */
	public function __call($method, $args)
	{
		if (isset($args[0]) && is_callable($args[0])) {
			return $this->service($method, $args[0]);
		} else {
			if(isset($this->values[$method]))
				return $this->values[$method];
			else
			{
				$this->values[$method] = $this->instantiate($method, $args);
				return $this->values[$method];
			}
		}
	}

	/**
	 * Magic "__get" method
	 *
	 * Allows the ability to arbitrarily request a service from this instance
	 * while treating it as an instance property
	 *
	 * This checks the lazy service register and automatically calls the registered
	 * service method
	 *
	 * @param string $name The name of the service
	 * @throws UnknownServiceException  If a non-registered service is attempted to fetched
	 * @access public
	 * @return mixed
	 */
	public function __get($name)
	{
		return $this->instantiate($name);
	}

	function var_dir($new_value = null)
	{
		if($new_value)
			$this->var_dir = $new_value;
		else
			return $this->var_dir ?: sys_get_temp_dir().'micro-yii/';
	}

	function mode($new_value = null)
	{
		if($new_value)
			$this->mode = $new_value;
		else
			return $this->mode;
	}

	function guard($handler)
	{
		$guard = ErrorGuard::instance();
		$guard->pushHandler($handler);
		$guard->enable();
		return $guard;
	}
}
