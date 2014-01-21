<?php
namespace Klein\Tests;

use Klein\ErrorGuard;
use Klein\Exception;
use Klein\App;

class ErrorGuardTest extends AbstractKleinTest
{
	protected $handlers;
	protected $autoloader_file;

	function setUp()
	{
		$this->handlers = ErrorGuard::instance()->getHandlers();
		ErrorGuard::instance()->resetHandlers();
		$this->autoloader_file = __DIR__.'/../../../vendor/autoload.php';
	}

	function tearDown()
	{
		ErrorGuard::instance()->setHandlers($this->handlers);
	}

	function testEnableFatalErrorHandler()
	{
		$exec_file = $this->var_dir . '/testEnableFatalErrorHandler.php';
		$php = <<<EOD
<?php
ini_set('display_errors', true);
ini_set('log_errors', false);

require('{$this->autoloader_file}');

class CustomGuard extends \Klein\ErrorGuard {
  function onError(\$type, \$message, \$file, \$line) {
    echo json_encode([
      'type' => \$type,
      'message' => \$message
    ]);
  }
}
CustomGuard::instance()->enableFatalErrorHandler();
\str_repeat('x', 1024 * 10000000);

?>
EOD;
		file_put_contents($exec_file, $php);

		$this->assertFileExists($exec_file);
		$error_json = exec('php -f ' . realpath($exec_file));

		$this->assertNotEquals("", $error_json);
		$this->assertJson($error_json, $error_json);
		$error = json_decode($error_json);
		$this->assertEquals(1, $error->type);
		$this->assertRegExp('/Allowed memory size/', $error->message);
	}

	function testEnableErrorHandler()
	{
		$exec_file = $this->var_dir . '/testEnableErrorHandler.php';
		$php = <<<EOD
<?php
ini_set('display_errors', false);
ini_set('log_errors', false);

require('{$this->autoloader_file}');

class CustomGuard extends \Klein\ErrorGuard {
  function onError(\$type, \$message, \$file, \$line) {
    echo json_encode([
      'type' => \$type,
      'message' => \$message
    ]);
  }
}
CustomGuard::instance()->enableErrorHandler();
ABRACADABRA;

?>
EOD;
		file_put_contents($exec_file, $php);

		$this->assertFileExists($exec_file);
		$error_json = exec('php -f ' . realpath($exec_file));
		$this->assertNotEquals("", $error_json);
		$this->assertJson($error_json);
		$error = json_decode($error_json);

		$this->assertEquals(8, $error->type);
		$this->assertRegExp('/Use of undefined constant/', $error->message);
	}

	function testEnableExceptionHandler()
	{
		$exec_file = $this->var_dir . '/testEnableExceptionHandler.php';
		$php = <<<EOD
<?php
ini_set('display_errors', false);
ini_set('log_errors', false);

require('{$this->autoloader_file}');

class CustomGuard extends \Klein\ErrorGuard {
  function onException(Exception \$e) {
    echo json_encode([
      'class' => get_class(\$e),
      'message' => \$e->getMessage()
    ]);
  }
}

class FooBarException extends Exception {}

CustomGuard::instance()->enableExceptionHandler();
throw new FooBarException('Foo');
EOD;
		file_put_contents($exec_file, $php);

		$this->assertFileExists($exec_file);
		$error_json = exec('php -f ' . realpath($exec_file));
		$this->assertNotEquals("", $error_json);
		$this->assertJson($error_json);
		$error = json_decode($error_json);

		$this->assertEquals('FooBarException', $error->class);
		$this->assertEquals('Foo', $error->message);
	}

	function testOnException()
	{
		$actual = true;
		GuardForOnErrorTest::instance()->pushHandler(function (Exception $e) use (&$actual) {
			$actual = $e;
		});
		$must_be = new Exception('FooMessage', ['bar' => 42]);
		GuardForOnErrorTest::instance()->onException($must_be);
		$this->assertEquals($must_be, $actual);
	}

	function testOnError()
	{
		$actual = true;
		GuardForOnErrorTest::instance()->pushHandler(function (Exception $e) use (&$actual) {
			$actual = $e;
		});
		GuardForOnErrorTest::instance()->onError(42, 'FooMessage', __FILE__, 4242);
		$this->assertEquals(42, $actual->getCode());
		$this->assertRegExp('/FooMessage/', $actual->getMessage());
	}
}

class GuardForOnErrorTest extends ErrorGuard
{
}