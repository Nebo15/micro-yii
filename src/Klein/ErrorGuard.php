<?php

namespace Klein;

/**
 * class ErrorGuard.
 *
 */
class ErrorGuard
{
	protected $is_fatal_error_handler_enabled = false;
	protected $handlers = array();

	/**
	 * @return ErrorGuard
	 */
	static function instance()
	{
		static $instances = [];
		$class = get_called_class();
		if (!isset($instances[$class]))
			$instances[$class] = new $class();
		return $instances[$class];
	}

	function enable()
	{
		$this->enableFatalErrorHandler();
		$this->enableErrorHandler();
		$this->enableExceptionHandler();
	}

	function disable()
	{
		$this->disableFatalErrorHandler();
		$this->disableErrorHandler();
		$this->disableExceptionHandler();
	}

	function enableExceptionHandler()
	{
		$guard_class = get_called_class();
		set_exception_handler(function($e) use ($guard_class)
		{
			$guard = $guard_class::instance();
			$guard::instance()->onException($e);
		});
	}

	function disableExceptionHandler()
	{
		restore_exception_handler();
	}

	function enableFatalErrorHandler()
	{
		if ($this->is_fatal_error_handler_enabled)
			return;

		$guard_class = get_called_class();
		register_shutdown_function(function () use ($guard_class) {

			if (session_id())
				session_write_close();

			if (!function_exists('error_get_last'))
				return;

			if (!$error = error_get_last())
				return;

			if ($error['type'] & (E_ERROR | E_COMPILE_ERROR))
			{
				$guard = $guard_class::instance();
				if($guard->isFatalErrorHandlerEnabled())
					call_user_func_array([$guard, 'onError'], $error);
			}
		});

		$this->is_fatal_error_handler_enabled = true;
	}

	function isFatalErrorHandlerEnabled()
	{
		return $this->is_fatal_error_handler_enabled;
	}

	function disableFatalErrorHandler()
	{
		$this->is_fatal_error_handler_enabled = false;
	}

	function enableErrorHandler()
	{
		$guard_class = get_called_class();
		set_error_handler(function() use($guard_class) {
			$guard = $guard_class::instance();
			call_user_func_array([$guard, 'onError'], func_get_args());
		});
	}

	function disableErrorHandler()
	{
		restore_error_handler();
	}

	function onException(\Exception $e)
	{
		$guard_class = get_called_class();
		foreach($guard_class::instance()->getHandlers() as $handler)
			call_user_func_array($handler, [$e]);
	}

	function onError($type, $message, $file, $line)
	{
		throw new Exception($message, ['file' => $file, 'line' => $line], $type);
	}

	function getHandlers()
	{
		return $this->handlers;
	}

	function setHandlers($handler_or_many)
	{
		if(!is_array($handler_or_many))
			$handler_or_many = [$handler_or_many];

		$this->handlers = $handler_or_many;
	}

	function pushHandler($handler)
	{
		$this->handlers[] = $handler;
		return $this->handlers;
	}

	function resetHandlers()
	{
		$this->handlers = [];
	}
}

