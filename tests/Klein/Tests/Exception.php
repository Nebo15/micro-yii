<?php

namespace Klein\Tests;

use Klein\Exception;

class ExceptionTest extends AbstractKleinTest
{
	function testGetParams()
	{
		$e = new lmbException('foo', $params = array('bar' => 'baz'));
		$this->assertEqual($params, $e->getParams());
	}

	function testGetParam()
	{
		$e = new lmbException('foo', array('bar' => 'baz'));
		$this->assertEqual('baz', $e->getParam('bar'));
		$this->assertNull($e->getParam('not_existed'));
	}

	function testGetMessage()
	{
		$original_message = 'foo';
		$e = new lmbException($original_message, array('bar' => 'baz'));
		$this->assertPattern("/{$original_message}/", $e->getMessage());
		$this->assertPattern("/bar/", $e->getMessage());
		$this->assertPattern("/baz/", $e->getMessage());
	}

	function testGetOriginalMessage()
	{
		$original_message = 'foo';
		$e = new lmbException($original_message);
		$this->assertEqual($original_message, $e->getOriginalMessage());
	}

	function testGetNiceTraceAsString()
	{
		$e = $this->_createException('foo');
		$trace = $e->getNiceTraceAsString();
		list($first_call, $second_call) = explode(PHP_EOL, $trace);

		$this->assertPattern('/lmbExceptionTest/', $first_call);
		$this->assertPattern('/lmbException::__construct/', $first_call);
		$this->assertPattern('/foo/', $first_call);
		$this->assertPattern('/'.basename(__FILE__).'/', $first_call);
		$this->assertPattern('/_createException/', $second_call);
	}

	function testGetNiceTraceAsString_HideCalls()
	{
		$full = new lmbException('foo', array(), 0);
		$with_hidden_call = new lmbException('foo', array(), 0, 1);

		$trace_full = explode(PHP_EOL, $full->getNiceTraceAsString());
		$trace_with_hidden_call = explode(PHP_EOL, $with_hidden_call->getNiceTraceAsString());

		$this->assertEqual($trace_full[1], $trace_with_hidden_call[0]);
	}

	function testExceptionWithCycleInParams()
	{
		$a = new stdClass();
		$b = new stdClass();
		$a->b = $b;
		$b->a = $a;

		//at this line we can get a recursion
		$exception = new lmbException('some_text', array($a, $b));
		$this->pass();
	}

	protected function _createException() {
		return new lmbException('foo');
	}
}


