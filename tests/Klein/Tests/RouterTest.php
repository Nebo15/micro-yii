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

use Exception;
use Klein\App;
use Klein\DataCollection\RouteCollection;
use Klein\Exceptions\DispatchHaltedException;
use Klein\Exceptions\HttpException;
use Klein\Exceptions\HttpExceptionInterface;
use Klein\Router;
use Klein\Request;
use Klein\Response;
use Klein\Route;
use Klein\ServiceProvider;
use OutOfBoundsException;

/**
 * KleinTest 
 *
 * @uses AbstractKleinTest
 * @package Klein\Tests
 */
class RouterTest extends AbstractKleinTest
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


    /**
     * Tests
     */

    public function testConstructor()
    {
        $klein = new Router();

        $this->assertNotNull($klein);
        $this->assertTrue($klein instanceof Router);
    }

    public function testApp()
    {
        $app = $this->router->app();

        $this->assertNotNull($app);
        $this->assertTrue($app instanceof App);
    }

    public function testRoutes()
    {
        $routes = $this->router->routes();

        $this->assertNotNull($routes);
        $this->assertTrue($routes instanceof RouteCollection);
    }

    public function testRequest()
    {
        $this->router->dispatch();

        $request = $this->router->request();

        $this->assertNotNull($request);
        $this->assertTrue($request instanceof Request);
    }

    public function testResponse()
    {
        $this->router->dispatch();

        $response = $this->router->response();

        $this->assertNotNull($response);
        $this->assertTrue($response instanceof Response);
    }

    public function testRespond()
    {
        $route = $this->router->respond($this->getTestCallable());

        $object_id = spl_object_hash($route);

        $this->assertNotNull($route);
        $this->assertTrue($route instanceof Route);
        $this->assertTrue($this->router->routes()->exists($object_id));
        $this->assertSame($route, $this->router->routes()->get($object_id));
    }

    public function testWith()
    {
        // Test data
        $test_namespace = '/test/namespace';
        $passed_context = null;

        $this->router->with(
            $test_namespace,
            function ($context) use (&$passed_context) {
                $passed_context = $context;
            }
        );

        $this->assertTrue($passed_context instanceof Router);
    }

    public function testWithStringCallable()
    {
        // Test data
        $test_namespace = '/test/namespace';

        $this->router->with(
            $test_namespace,
            'test_num_args_wrapper'
        );

        $this->expectOutputString('1');
    }

    /**
     * Weird PHPUnit bug is causing scope errors for the
     * isolated process tests, unless I run this also in an
     * isolated process
     *
     * @runInSeparateProcess
     */
    public function testWithUsingFileInclude()
    {
        // Test data
        $test_namespace = '/test/namespace';
        $test_routes_include = __DIR__ .'/routes/random.php';

        // Test file include
        $this->assertEmpty($this->router->routes()->all());
        $this->router->with($test_namespace, $test_routes_include);

        $this->assertNotEmpty($this->router->routes()->all());

        $all_routes = array_values($this->router->routes()->all());
        $test_route = $all_routes[0];

        $this->assertTrue($test_route instanceof Route);
        $this->assertSame($test_namespace . '/?', $test_route->getPath());
    }

    public function testDispatch()
    {
        $request = new Request();
        $response = new Response();

        $this->router->dispatch($request, $response);

        $this->assertSame($request, $this->router->request());
        $this->assertSame($response, $this->router->response());
    }

    public function testGetPathFor()
    {
        // Test data
        $test_path = '/test';
        $test_name = 'Test Route Thing';

        $route = new Route($this->getTestCallable());
        $route->setPath($test_path);
        $route->setName($test_name);

        $this->router->routes()->addRoute($route);

        // Make sure it fails if not prepared
        try {
            $this->router->getPathFor($test_name);
        } catch (Exception $e) {
            $this->assertTrue($e instanceof OutOfBoundsException);
        }

        $this->router->routes()->prepareNamed();

        $returned_path = $this->router->getPathFor($test_name);

        $this->assertNotEmpty($returned_path);
        $this->assertSame($test_path, $returned_path);
    }

    public function testOnErrorWithStringCallables()
    {
        $this->router->onError('test_num_args_wrapper');

        $this->router->respond(
            function ($request, $response, $service) {
                throw new Exception('testing');
            }
        );

        $this->assertSame(
            '4',
            $this->dispatchAndReturnOutput()
        );
    }

    public function testOnErrorWithBadCallables()
    {
        $this->router->onError('this_function_doesnt_exist');

        $this->router->respond(
            function ($request, $response, $service) {
                throw new Exception('testing');
            }
        );

        $this->assertSame(
            '',
            $this->dispatchAndReturnOutput()
        );
    }

    public function testOnHttpError()
    {
        // Create expected arguments
        $num_of_args = 0;
        $expected_arguments = array(
            'code'            => null,
            'klein'           => null,
            'matched'         => null,
            'methods_matched' => null,
            'exception'       => null,
        );

        $this->router->onHttpError(
            function ($code, $klein, $matched, $methods_matched, $exception) use (&$num_of_args, &$expected_arguments) {
                // Keep track of our arguments
                $num_of_args = func_num_args();
                $expected_arguments['code'] = $code;
                $expected_arguments['klein'] = $klein;
                $expected_arguments['matched'] = $matched;
                $expected_arguments['methods_matched'] = $methods_matched;
                $expected_arguments['exception'] = $exception;

                $klein->response()->body($code .' error');
            }
        );

        $this->router->dispatch(null, null, false);

        $this->assertSame(
            '404 error',
            $this->router->response()->body()
        );

        $this->assertSame(count($expected_arguments), $num_of_args);

        $this->assertTrue(is_int($expected_arguments['code']));
        $this->assertTrue($expected_arguments['klein'] instanceof Router);
        $this->assertTrue($expected_arguments['matched'] instanceof RouteCollection);
        $this->assertTrue(is_array($expected_arguments['methods_matched']));
        $this->assertTrue($expected_arguments['exception'] instanceof HttpExceptionInterface);

        $this->assertSame($expected_arguments['klein'], $this->router);
    }

    public function testOnHttpErrorWithStringCallables()
    {
        $this->router->onHttpError('test_num_args_wrapper');

        $this->assertSame(
            '5',
            $this->dispatchAndReturnOutput()
        );
    }

    public function testOnHttpErrorWithBadCallables()
    {
        $this->router->onError('this_function_doesnt_exist');

        $this->assertSame(
            '',
            $this->dispatchAndReturnOutput()
        );
    }

    public function testAfterDispatch()
    {
        $this->router->afterDispatch(
            function ($klein) {
                $klein->response()->body('after callbacks!');
            }
        );

        $this->router->dispatch(null, null, false);

        $this->assertSame(
            'after callbacks!',
            $this->router->response()->body()
        );
    }

    public function testAfterDispatchWithMultipleCallbacks()
    {
        $this->router->afterDispatch(
            function ($klein) {
                $klein->response()->body('after callbacks!');
            }
        );

        $this->router->afterDispatch(
            function ($klein) {
                $klein->response()->body('whatever');
            }
        );

        $this->router->dispatch(null, null, false);

        $this->assertSame(
            'whatever',
            $this->router->response()->body()
        );
    }

    public function testAfterDispatchWithStringCallables()
    {
        $this->router->afterDispatch('test_response_edit_wrapper');

        $this->router->dispatch(null, null, false);

        $this->assertSame(
            'after callbacks!',
            $this->router->response()->body()
        );
    }

    public function testAfterDispatchWithBadCallables()
    {
        $this->router->afterDispatch('this_function_doesnt_exist');

        $this->router->dispatch();

        $this->expectOutputString(null);
    }

    /**
     * @expectedException Klein\Exceptions\UnhandledException
     */
    public function testAfterDispatchWithCallableThatThrowsException()
    {
        $this->router->afterDispatch(
            function ($klein) {
                throw new Exception('testing');
            }
        );

        $this->router->dispatch();

        $this->assertSame(
            500,
            $this->router->response()->code()
        );
    }

    /**
     * @expectedException Klein\Exceptions\UnhandledException
     */
    public function testErrorsWithNoCallbacks()
    {
        $this->router->respond(
            function ($request, $response, $service) {
                throw new Exception('testing');
            }
        );

        $this->router->dispatch();

        $this->assertSame(
            500,
            $this->router->response()->code()
        );
    }

    public function testSkipThis()
    {
        try {
            $this->router->skipThis();
        } catch (Exception $e) {
            $this->assertTrue($e instanceof DispatchHaltedException);
            $this->assertSame(DispatchHaltedException::SKIP_THIS, $e->getCode());
            $this->assertSame(1, $e->getNumberOfSkips());
        }
    }

    public function testSkipNext()
    {
        $number_of_skips = 3;

        try {
            $this->router->skipNext($number_of_skips);
        } catch (Exception $e) {
            $this->assertTrue($e instanceof DispatchHaltedException);
            $this->assertSame(DispatchHaltedException::SKIP_NEXT, $e->getCode());
            $this->assertSame($number_of_skips, $e->getNumberOfSkips());
        }
    }

    public function testSkipRemaining()
    {
        try {
            $this->router->skipRemaining();
        } catch (Exception $e) {
            $this->assertTrue($e instanceof DispatchHaltedException);
            $this->assertSame(DispatchHaltedException::SKIP_REMAINING, $e->getCode());
        }
    }

    public function testAbort()
    {
        $test_code = 503;

        $this->router->respond(
            function ($a, $b, $c, $router) use ($test_code) {
                $router->abort($test_code);
            }
        );

        try {
            $this->router->dispatch();
        } catch (Exception $e) {
            $this->assertTrue($e instanceof DispatchHaltedException);
            $this->assertSame(DispatchHaltedException::SKIP_REMAINING, $e->getCode());
        }

        $this->assertSame($test_code, $this->router->response()->code());
        $this->assertTrue($this->router->response()->isLocked());
    }

    public function testOptions()
    {
        $route = $this->router->options($this->getTestCallable());

        $this->assertNotNull($route);
        $this->assertTrue($route instanceof Route);
        $this->assertSame('OPTIONS', $route->getMethod());
    }

    public function testHead()
    {
        $route = $this->router->head($this->getTestCallable());

        $this->assertNotNull($route);
        $this->assertTrue($route instanceof Route);
        $this->assertSame('HEAD', $route->getMethod());
    }

    public function testGet()
    {
        $route = $this->router->get($this->getTestCallable());

        $this->assertNotNull($route);
        $this->assertTrue($route instanceof Route);
        $this->assertSame('GET', $route->getMethod());
    }

    public function testPost()
    {
        $route = $this->router->post($this->getTestCallable());

        $this->assertNotNull($route);
        $this->assertTrue($route instanceof Route);
        $this->assertSame('POST', $route->getMethod());
    }

    public function testPut()
    {
        $route = $this->router->put($this->getTestCallable());

        $this->assertNotNull($route);
        $this->assertTrue($route instanceof Route);
        $this->assertSame('PUT', $route->getMethod());
    }

    public function testDelete()
    {
        $route = $this->router->delete($this->getTestCallable());

        $this->assertNotNull($route);
        $this->assertTrue($route instanceof Route);
        $this->assertSame('DELETE', $route->getMethod());
    }

    public function testPatch()
    {
        $route = $this->router->patch($this->getTestCallable());

        $this->assertNotNull($route);
        $this->assertTrue($route instanceof Route);
        $this->assertSame('PATCH', $route->getMethod());
    }
}
