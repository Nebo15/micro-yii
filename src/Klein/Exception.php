<?php
namespace Klein;
/**
 * class Exception.
 *
 */
class Exception extends \Exception
{
	protected $original_message;
	protected $params = array();
	protected $file;
	protected $line;
	protected $backtrace;

	function __construct($message, $params = array(), $code = 0, $hide_calls_count = 0)
	{
		$this->original_message = $message;
		$this->params = $params;

		$this->backtrace = array_slice(debug_backtrace(), $hide_calls_count);

		foreach($this->backtrace as $item)
		{
			if (isset($item['file']))
			{
				$this->file = $item['file'];
				$this->line = $item['line'];
				break;
			}
		}

		$message .= ' ('.json_encode($params).')';

		parent :: __construct($message, $code);
	}

	function getOriginalMessage()
	{
		return $this->original_message;
	}

	function getRealFile()
	{
		return $this->file;
	}

	function getRealLine()
	{
		return $this->line;
	}

	function getParams()
	{
		return $this->params;
	}

	function getParam($name)
	{
		if(isset($this->params[$name]))
			return $this->params[$name];
	}

	function getBacktrace()
	{
		return $this->backtrace;
	}
}

