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

namespace Klein\Tests;

use Klein\App;

/**
 * AppTest
 *
 * @uses AbstractKleinTest
 * @package Klein\Tests
 */
class AppTest extends AbstractKleinTest
{

	/**
	 * Constants
	 */

	const TEST_CALLBACK_MESSAGE = 'yay';


	/**
	 * Helpers
	 */

	protected function getTestCallable($message = self::TEST_CALLBACK_MESSAGE)
	{
		return function () use ($message) {
			return $message;
		};
	}

	public function testCall()
	{
		$app = new App();
		$app->foo(function () {
			return self::TEST_CALLBACK_MESSAGE;
		});
		$returned = $app->foo();

		$this->assertNotNull($returned);
		$this->assertEquals(self::TEST_CALLBACK_MESSAGE, $returned);
	}

	/**
	 * @expectedException Klein\Exceptions\UnknownServiceException
	 */
	public function testCall_BadMethod()
	{
		$app = new App();
		$app->random_thing_that_doesnt_exist();
	}

	public function testCall_SingletonByDefault()
	{
		$app = new App();
		$app->foo(function () {
			return new \stdClass;
		});
		$this->assertSame($app->foo(), $app->foo());
	}

	public function testCall_DontSaveResultIfCallableReturnsNull()
	{
		$i = 0;
		$app = new App();
		$app->foo(function() use(&$i) {
			$i++;
		});
		$app->foo();
		$app->foo();
		$this->assertEquals(2, $i);
	}

	public function testGet()
	{
		$app = new App();

		$app->testGet($this->getTestCallable());

		$returned = $app->testGet;

		$this->assertNotNull($returned);
		$this->assertSame(self::TEST_CALLBACK_MESSAGE, $returned);
	}

	/**
	 * @expectedException Klein\Exceptions\UnknownServiceException
	 */
	public function testGet_BadMethod()
	{
		$app = new App();
		$app->random_thing_that_doesnt_exist;
	}

	public function testRegisterDuplicateMethod()
	{
		$app = new App();
		$app->foo(function () {
			return 'foo';
		});
		$app->foo(function () {
			return 'foo2';
		});
	}
}
