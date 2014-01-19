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
use Klein\DataCollection\RouteCollection;
use Klein\Exceptions\DispatchHaltedException;
use Klein\Exceptions\HttpException;
use Klein\Router;
use Klein\Request;
use Klein\Response;
use Klein\ServiceProvider;
use Klein\Tests\Mocks\HeadersEcho;
use Klein\Tests\Mocks\HeadersSave;
use Klein\Tests\Mocks\MockRequestFactory;

/**
 * RoutingTest
 * 
 * @uses AbstractKleinTest
 * @package Klein\Tests
 */
class RoutingTest extends AbstractKleinTest
{

    public function testBasic()
    {
        $this->expectOutputString('x');

        $this->router->respond(
            '/',
            function () {
                echo 'x';
            }
        );
        $this->router->respond(
            '/something',
            function () {
                echo 'y';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/')
        );
    }

    public function testCallable()
    {
        $this->expectOutputString('okok');

        $this->router->respond('/', array(__NAMESPACE__ . '\Mocks\TestClass', 'GET'));
        $this->router->respond('/', __NAMESPACE__ . '\Mocks\TestClass::GET');

        $this->router->dispatch(
            MockRequestFactory::create('/')
        );
    }

    public function testCallbackArguments()
    {
        // Create expected objects
        $expected_objects = array(
            'request'         => null,
            'response'        => null,
            'app'             => null,
            'router'          => null,
            'matched'         => null,
            'methods_matched' => null,
        );

        $this->router->respond(
            function ($a, $b, $c, $d, $e, $f) use (&$expected_objects) {
                $expected_objects['request']         = $a;
                $expected_objects['response']        = $b;
                $expected_objects['app']             = $c;
                $expected_objects['router']          = $d;
                $expected_objects['matched']         = $e;
                $expected_objects['methods_matched'] = $f;
            }
        );

        $this->router->dispatch();

        $this->assertTrue($expected_objects['request'] instanceof Request);
        $this->assertTrue($expected_objects['response'] instanceof Response);
        $this->assertTrue($expected_objects['app'] instanceof App);
        $this->assertTrue($expected_objects['router'] instanceof Router);
        $this->assertTrue($expected_objects['matched'] instanceof RouteCollection);
        $this->assertTrue(is_array($expected_objects['methods_matched']));

        $this->assertSame($expected_objects['request'], $this->router->request());
        $this->assertSame($expected_objects['response'], $this->router->response());
        $this->assertSame($expected_objects['app'], $this->router->app());
        $this->assertSame($expected_objects['router'], $this->router);
    }

    public function testAppReference()
    {
        $this->expectOutputString('ab');

        $this->router->respond(
            '/',
            function ($r, $r, $s, $a) {
                $a->state = 'a';
            }
        );
        $this->router->respond(
            '/',
            function ($r, $r, $s, $a) {
                $a->state .= 'b';
            }
        );
        $this->router->respond(
            '/',
            function ($r, $r, $s, $a) {
                print $a->state;
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/')
        );
    }

    public function testDispatchOutput()
    {
        $expectedOutput = array(
            'returned1' => 'alright!',
            'returned2' => 'woot!',
        );

        $this->router->respond(
            function () use ($expectedOutput) {
                return $expectedOutput['returned1'];
            }
        );
        $this->router->respond(
            function () use ($expectedOutput) {
                return $expectedOutput['returned2'];
            }
        );

        $this->router->dispatch();

        // Expect our output to match our ECHO'd output
        $this->expectOutputString(
            $expectedOutput['returned1'] . $expectedOutput['returned2']
        );

        // Make sure our response body matches the concatenation of what we returned in each callback
        $this->assertSame(
            $expectedOutput['returned1'] . $expectedOutput['returned2'],
            $this->router->response()->body()
        );
    }

    public function testDispatchOutputNotSent()
    {
        $this->router->respond(
            function () {
                return 'test output';
            }
        );

        $this->router->dispatch(null, null, false);

        $this->expectOutputString('');

        $this->assertSame(
            'test output',
            $this->router->response()->body()
        );
    }

    public function testDispatchOutputCaptured()
    {
        $expectedOutput = array(
            'echoed' => 'yup',
            'returned' => 'nope',
        );

        $this->router->respond(
            function () use ($expectedOutput) {
                echo $expectedOutput['echoed'];
            }
        );
        $this->router->respond(
            function () use ($expectedOutput) {
                return $expectedOutput['returned'];
            }
        );

        $output = $this->router->dispatch(null, null, true, Router::DISPATCH_CAPTURE_AND_RETURN);

        // Make sure nothing actually printed to the screen
        $this->expectOutputString('');

        // Make sure our returned output matches what we ECHO'd
        $this->assertSame($expectedOutput['echoed'], $output);

        // Make sure our response body matches what we returned
        $this->assertSame($expectedOutput['returned'], $this->router->response()->body());
    }

    public function testDispatchOutputReplaced()
    {
        $expectedOutput = array(
            'echoed' => 'yup',
            'returned' => 'nope',
        );

        $this->router->respond(
            function () use ($expectedOutput) {
                echo $expectedOutput['echoed'];
            }
        );
        $this->router->respond(
            function () use ($expectedOutput) {
                return $expectedOutput['returned'];
            }
        );

        $this->router->dispatch(null, null, false, Router::DISPATCH_CAPTURE_AND_REPLACE);

        // Make sure nothing actually printed to the screen
        $this->expectOutputString('');

        // Make sure our response body matches what we echoed
        $this->assertSame($expectedOutput['echoed'], $this->router->response()->body());
    }

    public function testDispatchOutputPrepended()
    {
        $expectedOutput = array(
            'echoed' => 'yup',
            'returned' => 'nope',
            'echoed2' => 'sure',
        );

        $this->router->respond(
            function () use ($expectedOutput) {
                echo $expectedOutput['echoed'];
            }
        );
        $this->router->respond(
            function () use ($expectedOutput) {
                return $expectedOutput['returned'];
            }
        );
        $this->router->respond(
            function () use ($expectedOutput) {
                echo $expectedOutput['echoed2'];
            }
        );

        $this->router->dispatch(null, null, false, Router::DISPATCH_CAPTURE_AND_PREPEND);

        // Make sure nothing actually printed to the screen
        $this->expectOutputString('');

        // Make sure our response body matches what we echoed
        $this->assertSame(
            $expectedOutput['echoed'] . $expectedOutput['echoed2'] . $expectedOutput['returned'],
            $this->router->response()->body()
        );
    }

    public function testDispatchOutputAppended()
    {
        $expectedOutput = array(
            'echoed' => 'yup',
            'returned' => 'nope',
            'echoed2' => 'sure',
        );

        $this->router->respond(
            function () use ($expectedOutput) {
                echo $expectedOutput['echoed'];
            }
        );
        $this->router->respond(
            function () use ($expectedOutput) {
                return $expectedOutput['returned'];
            }
        );
        $this->router->respond(
            function () use ($expectedOutput) {
                echo $expectedOutput['echoed2'];
            }
        );

        $this->router->dispatch(null, null, false, Router::DISPATCH_CAPTURE_AND_APPEND);

        // Make sure nothing actually printed to the screen
        $this->expectOutputString('');

        // Make sure our response body matches what we echoed
        $this->assertSame(
            $expectedOutput['returned'] . $expectedOutput['echoed'] . $expectedOutput['echoed2'],
            $this->router->response()->body()
        );
    }

    public function testDispatchResponseReplaced()
    {
        $expected_body = 'You SHOULD see this';
        $expected_code = 201;

        $expected_append = 'This should be appended?';

        $this->router->respond(
            '/',
            function ($request, $response) {
                // Set our response code
                $response->code(569);

                return 'This should disappear';
            }
        );
        $this->router->respond(
            '/',
            function () use ($expected_body, $expected_code) {
                return new Response($expected_body, $expected_code);
            }
        );
        $this->router->respond(
            '/',
            function () use ($expected_append) {
                return $expected_append;
            }
        );

        $this->router->dispatch(null, null, false, Router::DISPATCH_CAPTURE_AND_RETURN);

        // Make sure our response body and code match up
        $this->assertSame(
            $expected_body . $expected_append,
            $this->router->response()->body()
        );
        $this->assertSame(
            $expected_code,
            $this->router->response()->code()
        );
    }

    public function testRespondReturn()
    {
        $return_one = $this->router->respond(
            function () {
                return 1337;
            }
        );
        $return_two = $this->router->respond(
            function () {
                return 'dog';
            }
        );

        $this->router->dispatch(null, null, false);

        $this->assertTrue(is_callable($return_one));
        $this->assertTrue(is_callable($return_two));
    }

    public function testRespondReturnChaining()
    {
        $return_one = $this->router->respond(
            function () {
                return 1337;
            }
        );
        $return_two = $this->router->respond(
            function () {
                return 1337;
            }
        )->getPath();

        $this->assertSame($return_one->getPath(), $return_two);
    }

    public function testCatchallImplicit()
    {
        $this->expectOutputString('b');

        $this->router->respond(
            '/one',
            function () {
                echo 'a';
            }
        );
        $this->router->respond(
            function () {
                echo 'b';
            }
        );
        $this->router->respond(
            '/two',
            function () {

            }
        );
        $this->router->respond(
            '/three',
            function () {
                echo 'c';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/two')
        );
    }

    public function testCatchallAsterisk()
    {
        $this->expectOutputString('b');

        $this->router->respond(
            '/one',
            function () {
                echo 'a';
            }
        );
        $this->router->respond(
            '*',
            function () {
                echo 'b';
            }
        );
        $this->router->respond(
            '/two',
            function () {

            }
        );
        $this->router->respond(
            '/three',
            function () {
                echo 'c';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/two')
        );
    }

    public function testCatchallImplicitTriggers404()
    {
        $this->expectOutputString("b404\n");

        $this->router->respond(
            function () {
                echo 'b';
            }
        );
        $this->router->respond(
            404,
            function () {
                echo "404\n";
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/')
        );
    }

    public function testRegex()
    {
        $this->expectOutputString('zz');

        $this->router->respond(
            '@/bar',
            function () {
                echo 'z';
            }
        );

        $this->router->respond(
            '@/[0-9]s',
            function () {
                echo 'z';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/bar')
        );
        $this->router->dispatch(
            MockRequestFactory::create('/8s')
        );
        $this->router->dispatch(
            MockRequestFactory::create('/88s')
        );
    }

    public function testRegexNegate()
    {
        $this->expectOutputString("y");

        $this->router->respond(
            '!@/foo',
            function () {
                echo 'y';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/bar')
        );
    }

    public function testNormalNegate()
    {
        $this->expectOutputString('');

        $this->router->respond(
            '!/foo',
            function () {
                echo 'y';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/foo')
        );
    }

    public function test404()
    {
        $this->expectOutputString("404\n");

        $this->router->respond(
            '/',
            function () {
                echo 'a';
            }
        );
        $this->router->respond(
            404,
            function () {
                echo "404\n";
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/foo')
        );

        $this->assertSame(404, $this->router->response()->code());
    }

    public function testParamsBasic()
    {
        $this->expectOutputString('blue');

        $this->router->respond(
            '/[:color]',
            function ($request) {
                echo $request->param('color');
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/blue')
        );
    }

    public function testParamsIntegerSuccess()
    {
        $this->expectOutputString("string(3) \"987\"\n");

        $this->router->respond(
            '/[i:age]',
            function ($request) {
                var_dump($request->param('age'));
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/987')
        );
    }

    public function testParamsIntegerFail()
    {
        $this->expectOutputString('404 Code');

        $this->router->respond(
            '/[i:age]',
            function ($request) {
                var_dump($request->param('age'));
            }
        );
        $this->router->respond(
            '404',
            function () {
                echo '404 Code';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/blue')
        );
    }

    public function testParamsAlphaNum()
    {
        $this->router->respond(
            '/[a:audible]',
            function ($request) {
                echo $request->param('audible');
            }
        );


        $this->assertSame(
            'blue42',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/blue42')
            )
        );
        $this->assertSame(
            '',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/texas-29')
            )
        );
        $this->assertSame(
            '',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/texas29!')
            )
        );
    }

    public function testParamsHex()
    {
        $this->router->respond(
            '/[h:hexcolor]',
            function ($request) {
                echo $request->param('hexcolor');
            }
        );


        $this->assertSame(
            '00f',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/00f')
            )
        );
        $this->assertSame(
            'abc123',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/abc123')
            )
        );
        $this->assertSame(
            '',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/876zih')
            )
        );
        $this->assertSame(
            '',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/00g')
            )
        );
        $this->assertSame(
            '',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/hi23')
            )
        );
    }

    public function testParamsSlug()
    {
        $this->router->respond(
            '/[s:slug_name]',
            function ($request) {
                echo $request->param('slug_name');
            }
        );


        $this->assertSame(
            'dog-thing',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/dog-thing')
            )
        );
        $this->assertSame(
            'a_badass_slug',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/a_badass_slug')
            )
        );
        $this->assertSame(
            'AN_UPERCASE_SLUG',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/AN_UPERCASE_SLUG')
            )
        );
        $this->assertSame(
            'sample-wordpress-like-post-slug-based-on-the-title-2013-edition',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/sample-wordpress-like-post-slug-based-on-the-title-2013-edition')
            )
        );
        $this->assertSame(
            '',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/%!@#')
            )
        );
        $this->assertSame(
            '',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/')
            )
        );
        $this->assertSame(
            '',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/dog-%thing')
            )
        );
    }

    public function testPathParamsAreUrlDecoded()
    {
        $this->router->respond(
            '/[:test]',
            function ($request) {
                echo $request->param('test');
            }
        );

        $this->assertSame(
            'Knife Party',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/Knife%20Party')
            )
        );

        $this->assertSame(
            'and/or',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/and%2For')
            )
        );
    }

    public function testPathParamsAreUrlDecodedToRFC3986Spec()
    {
        $this->router->respond(
            '/[:test]',
            function ($request) {
                echo $request->param('test');
            }
        );

        $this->assertNotSame(
            'Knife Party',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/Knife+Party')
            )
        );

        $this->assertSame(
            'Knife+Party',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/Knife+Party')
            )
        );
    }

    public function test404TriggersOnce()
    {
        $this->expectOutputString('d404 Code');

        $this->router->respond(
            function () {
                echo "d";
            }
        );
        $this->router->respond(
            '404',
            function () {
                echo '404 Code';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/notroute')
        );
    }

    public function test404RouteDefinitionOrderDoesntEffectWhen404HandlersCalled()
    {
        $this->expectOutputString('onetwo404 Code');

        $this->router->respond(
            function () {
                echo 'one';
            }
        );
        $this->router->respond(
            '404',
            function () {
                echo '404 Code';
            }
        );
        $this->router->respond(
            function () {
                echo 'two';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/notroute')
        );
    }

    public function testMethodCatchAll()
    {
        $this->expectOutputString('yup!123');

        $this->router->respond(
            'POST',
            null,
            function ($request) {
                echo 'yup!';
            }
        );
        $this->router->respond(
            'POST',
            '*',
            function ($request) {
                echo '1';
            }
        );
        $this->router->respond(
            'POST',
            '/',
            function ($request) {
                echo '2';
            }
        );
        $this->router->respond(
            function ($request) {
                echo '3';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/', 'POST')
        );
    }

    public function testLazyTrailingMatch()
    {
        $this->expectOutputString('this-is-a-title-123');

        $this->router->respond(
            '/posts/[*:title][i:id]',
            function ($request) {
                echo $request->param('title')
                . $request->param('id');
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/posts/this-is-a-title-123')
        );
    }

    public function testFormatMatch()
    {
        $this->expectOutputString('xml');

        $this->router->respond(
            '/output.[xml|json:format]',
            function ($request) {
                echo $request->param('format');
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/output.xml')
        );
    }

    public function testDotSeparator()
    {
        $this->expectOutputString('matchA:slug=ABCD_E--matchB:slug=ABCD_E--');

        $this->router->respond(
            '/[*:cpath]/[:slug].[:format]',
            function ($rq) {
                echo 'matchA:slug='.$rq->param("slug").'--';
            }
        );
        $this->router->respond(
            '/[*:cpath]/[:slug].[:format]?',
            function ($rq) {
                echo 'matchB:slug='.$rq->param("slug").'--';
            }
        );
        $this->router->respond(
            '/[*:cpath]/[a:slug].[:format]?',
            function ($rq) {
                echo 'matchC:slug='.$rq->param("slug").'--';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create("/category1/categoryX/ABCD_E.php")
        );

        $this->assertSame(
            'matchA:slug=ABCD_E--matchB:slug=ABCD_E--',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/category1/categoryX/ABCD_E.php')
            )
        );
        $this->assertSame(
            'matchB:slug=ABCD_E--',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/category1/categoryX/ABCD_E')
            )
        );
    }

    public function testControllerActionStyleRouteMatch()
    {
        $this->expectOutputString('donkey-kick');

        $this->router->respond(
            '/[:controller]?/[:action]?',
            function ($request) {
                echo $request->param('controller')
                     . '-' . $request->param('action');
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/donkey/kick')
        );
    }

    public function testRespondArgumentOrder()
    {
        $this->expectOutputString('abcdef');

        $this->router->respond(
            function () {
                echo 'a';
            }
        );
        $this->router->respond(
            null,
            function () {
                echo 'b';
            }
        );
        $this->router->respond(
            '/endpoint',
            function () {
                echo 'c';
            }
        );
        $this->router->respond(
            'GET',
            null,
            function () {
                echo 'd';
            }
        );
        $this->router->respond(
            array('GET', 'POST'),
            null,
            function () {
                echo 'e';
            }
        );
        $this->router->respond(
            array('GET', 'POST'),
            '/endpoint',
            function () {
                echo 'f';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/endpoint')
        );
    }

    public function testTrailingMatch()
    {
        $this->router->respond(
            '/?[*:trailing]/dog/?',
            function ($request) {
                echo 'yup';
            }
        );


        $this->assertSame(
            'yup',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/cat/dog')
            )
        );
        $this->assertSame(
            'yup',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/cat/cheese/dog')
            )
        );
        $this->assertSame(
            'yup',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/cat/ball/cheese/dog/')
            )
        );
        $this->assertSame(
            'yup',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/cat/ball/cheese/dog')
            )
        );
        $this->assertSame(
            'yup',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('cat/ball/cheese/dog/')
            )
        );
        $this->assertSame(
            'yup',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('cat/ball/cheese/dog')
            )
        );
    }

    public function testTrailingPossessiveMatch()
    {
        $this->router->respond(
            '/sub-dir/[**:trailing]',
            function ($request) {
                echo 'yup';
            }
        );


        $this->assertSame(
            'yup',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/sub-dir/dog')
            )
        );

        $this->assertSame(
            'yup',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/sub-dir/cheese/dog')
            )
        );

        $this->assertSame(
            'yup',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/sub-dir/ball/cheese/dog/')
            )
        );

        $this->assertSame(
            'yup',
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create('/sub-dir/ball/cheese/dog')
            )
        );
    }

    public function testNSDispatch()
    {
        $this->router->with(
            '/u',
            function ($klein_app) {
                $klein_app->respond(
                    'GET',
                    '/?',
                    function ($request, $response) {
                        echo "slash";
                    }
                );
                $klein_app->respond(
                    'GET',
                    '/[:id]',
                    function ($request, $response) {
                        echo "id";
                    }
                );
            }
        );
        $this->router->respond(
            404,
            function ($request, $response) {
                echo "404";
            }
        );


        $this->assertSame(
            "slash",
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create("/u")
            )
        );
        $this->assertSame(
            "slash",
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create("/u/")
            )
        );
        $this->assertSame(
            "id",
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create("/u/35")
            )
        );
        $this->assertSame(
            "404",
            $this->dispatchAndReturnOutput(
                MockRequestFactory::create("/35")
            )
        );
    }

    public function testNSDispatchExternal()
    {
        $ext_namespaces = $this->loadExternalRoutes();

        $this->router->respond(
            404,
            function ($request, $response) {
                echo "404";
            }
        );

        foreach ($ext_namespaces as $namespace) {

            $this->assertSame(
                'yup',
                $this->dispatchAndReturnOutput(
                    MockRequestFactory::create($namespace . '/')
                )
            );

            $this->assertSame(
                'yup',
                $this->dispatchAndReturnOutput(
                    MockRequestFactory::create($namespace . '/testing/')
                )
            );
        }
    }

    public function testNSDispatchExternalRerequired()
    {
        $ext_namespaces = $this->loadExternalRoutes();

        $this->router->respond(
            404,
            function ($request, $response) {
                echo "404";
            }
        );

        foreach ($ext_namespaces as $namespace) {

            $this->assertSame(
                'yup',
                $this->dispatchAndReturnOutput(
                    MockRequestFactory::create($namespace . '/')
                )
            );

            $this->assertSame(
                'yup',
                $this->dispatchAndReturnOutput(
                    MockRequestFactory::create($namespace . '/testing/')
                )
            );
        }
    }

    public function test405DefaultRequest()
    {
        $this->router->respond(
            array('GET', 'POST'),
            null,
            function () {
                echo 'fail';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/', 'DELETE')
        );

        $this->assertEquals('405 Method Not Allowed', $this->router->response()->status()->getFormattedString());
        $this->assertEquals('GET, POST', $this->router->response()->headers()->get('Allow'));
    }

    public function test405Routes()
    {
        $resultArray = array();

        $this->expectOutputString('_');

        $this->router->respond(
            function () {
                echo '_';
            }
        );
        $this->router->respond(
            'GET',
            null,
            function () {
                echo 'fail';
            }
        );
        $this->router->respond(
            array('GET', 'POST'),
            null,
            function () {
                echo 'fail';
            }
        );
        $this->router->respond(
            405,
            function ($a, $b, $c, $d, $e, $methods_matched) use (&$resultArray) {
                $resultArray = $methods_matched;
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/sure', 'DELETE')
        );

        $this->assertCount(2, $resultArray);
        $this->assertContains('GET', $resultArray);
        $this->assertContains('POST', $resultArray);
        $this->assertSame(405, $this->router->response()->code());
    }

    public function test405ErrorHandler()
    {
        $resultArray = array();

        $this->expectOutputString('_');

        $this->router->respond(
            function () {
                echo '_';
            }
        );
        $this->router->respond(
            'GET',
            null,
            function () {
                echo 'fail';
            }
        );
        $this->router->respond(
            array('GET', 'POST'),
            null,
            function () {
                echo 'fail';
            }
        );
        $this->router->onHttpError(
            function ($code, $router, $matched, $methods, $exception) use (&$resultArray) {
                $resultArray = $methods;
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/sure', 'DELETE')
        );

        $this->assertCount(2, $resultArray);
        $this->assertContains('GET', $resultArray);
        $this->assertContains('POST', $resultArray);
        $this->assertSame(405, $this->router->response()->code());
    }

    public function testOptionsDefaultRequest()
    {
        $this->router->respond(
            function ($request, $response) {
                $response->code(200);
            }
        );
        $this->router->respond(
            array('GET', 'POST'),
            null,
            function () {
                echo 'fail';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/', 'OPTIONS')
        );

        $this->assertEquals('200 OK', $this->router->response()->status()->getFormattedString());
        $this->assertEquals('GET, POST', $this->router->response()->headers()->get('Allow'));
    }

    public function testOptionsRoutes()
    {
        $access_control_headers = array(
            array(
                'key' => 'Access-Control-Allow-Origin',
                'val' => 'http://example.com',
            ),
            array(
                'key' => 'Access-Control-Allow-Methods',
                'val' => 'POST, GET, DELETE, OPTIONS, HEAD',
            ),
        );

        $this->router->respond(
            'GET',
            null,
            function () {
                echo 'fail';
            }
        );
        $this->router->respond(
            array('GET', 'POST'),
            null,
            function () {
                echo 'fail';
            }
        );
        $this->router->respond(
            'OPTIONS',
            null,
            function ($request, $response) use ($access_control_headers) {
                // Add access control headers
                foreach ($access_control_headers as $header) {
                    $response->header($header[ 'key' ], $header[ 'val' ]);
                }
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/', 'OPTIONS')
        );


        // Assert headers were passed
        $this->assertEquals('GET, POST, OPTIONS', $this->router->response()->headers()->get('Allow'));

        foreach ($access_control_headers as $header) {
            $this->assertEquals($header['val'], $this->router->response()->headers()->get($header['key']));
        }
    }

    public function testHeadDefaultRequest()
    {
        $expected_headers = array(
            array(
                'key' => 'X-Some-Random-Header',
                'val' => 'This was a GET route',
            ),
        );

        $this->router->respond(
            'GET',
            null,
            function ($request, $response) use ($expected_headers) {
                $response->code(200);

                // Add access control headers
                foreach ($expected_headers as $header) {
                    $response->header($header[ 'key' ], $header[ 'val' ]);
                }
            }
        );
        $this->router->respond(
            'GET',
            '/',
            function () {
                echo 'GET!';
                return 'more text';
            }
        );
        $this->router->respond(
            'POST',
            '/',
            function () {
                echo 'POST!';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/', 'HEAD')
        );

        // Make sure we don't get a response body
        $this->expectOutputString('');

        // Assert headers were passed
        foreach ($expected_headers as $header) {
            $this->assertEquals($header['val'], $this->router->response()->headers()->get($header['key']));
        }
    }

    public function testHeadMethodMatch()
    {
        $test_strings = array(
            'oh, hello',
            'yea',
        );

        $test_result = null;

        $this->router->respond(
            array('GET', 'HEAD'),
            null,
            function ($request, $response) use ($test_strings, &$test_result) {
                $test_result .= $test_strings[0];
            }
        );
        $this->router->respond(
            'GET',
            '/',
            function ($request, $response) use ($test_strings, &$test_result) {
                $test_result .= $test_strings[1];
            }
        );
        $this->router->respond(
            'POST',
            '/',
            function ($request, $response) use ($test_strings, &$test_result) {
                $test_result .= 'nope';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/', 'HEAD')
        );

        $this->assertSame(
            implode('', $test_strings),
            $test_result
        );
    }

    public function testGetPathFor()
    {
        $this->router->respond(
            '/dogs',
            function () {
            }
        )->setName('dogs');

        $this->router->respond(
            '/dogs/[i:dog_id]/collars',
            function () {
            }
        )->setName('dog-collars');

        $this->router->respond(
            '/dogs/[i:dog_id]/collars/[a:collar_slug]/?',
            function () {
            }
        )->setName('dog-collar-details');

        $this->router->respond(
            '/dog/foo',
            function () {
            }
        )->setName('dog-foo');

        $this->router->respond(
            '/dog/[i:dog_id]?',
            function () {
            }
        )->setName('dog-optional-details');

        $this->router->respond(
            '@/dog/regex',
            function () {
            }
        )->setName('dog-regex');

        $this->router->respond(
            '!@/dog/regex',
            function () {
            }
        )->setName('dog-neg-regex');

        $this->router->respond(
            '@\.(json|csv)$',
            function () {
            }
        )->setName('complex-regex');

        $this->router->respond(
            '!@^/admin/',
            function () {
            }
        )->setName('complex-neg-regex');

        $this->router->dispatch(
            MockRequestFactory::create('/', 'HEAD')
        );

        $this->assertSame(
            '/dogs',
            $this->router->getPathFor('dogs')
        );
        $this->assertSame(
            '/dogs/[i:dog_id]/collars',
            $this->router->getPathFor('dog-collars')
        );
        $this->assertSame(
            '/dogs/idnumberandstuff/collars',
            $this->router->getPathFor(
                'dog-collars',
                array(
                    'dog_id' => 'idnumberandstuff',
                )
            )
        );
        $this->assertSame(
            '/dogs/[i:dog_id]/collars/[a:collar_slug]/?',
            $this->router->getPathFor('dog-collar-details')
        );
        $this->assertSame(
            '/dogs/idnumberandstuff/collars/d12f3d1f2d3/?',
            $this->router->getPathFor(
                'dog-collar-details',
                array(
                    'dog_id' => 'idnumberandstuff',
                    'collar_slug' => 'd12f3d1f2d3',
                )
            )
        );
        $this->assertSame(
            '/dog/foo',
            $this->router->getPathFor('dog-foo')
        );
        $this->assertSame(
            '/dog',
            $this->router->getPathFor('dog-optional-details')
        );
        $this->assertSame(
            '/',
            $this->router->getPathFor('dog-regex')
        );
        $this->assertSame(
            '/',
            $this->router->getPathFor('dog-neg-regex')
        );
        $this->assertSame(
            '@/dog/regex',
            $this->router->getPathFor('dog-regex', null, false)
        );
        $this->assertNotSame(
            '/',
            $this->router->getPathFor('dog-neg-regex', null, false)
        );
        $this->assertSame(
            '/',
            $this->router->getPathFor('complex-regex')
        );
        $this->assertSame(
            '/',
            $this->router->getPathFor('complex-neg-regex')
        );
        $this->assertSame(
            '@\.(json|csv)$',
            $this->router->getPathFor('complex-regex', null, false)
        );
        $this->assertNotSame(
            '/',
            $this->router->getPathFor('complex-neg-regex', null, false)
        );
    }

    public function testDispatchHalt()
    {
        $this->expectOutputString('2,4,7,8,');

        $this->router->respond(
            function ($a, $b, $app, $router) {
	            $router->skipThis();
                echo '1,';
            }
        );
        $this->router->respond(
            function ($a, $b, $app) {
                echo '2,';
	            $app->router()->skipNext();
            }
        );
        $this->router->respond(
            function ($a, $b, $app) {
                echo '3,';
            }
        );
        $this->router->respond(
            function ($a, $b, $app, $router) {
                echo '4,';
	            $router->skipNext(2);
            }
        );
        $this->router->respond(
            function ($a, $b, $app) {
                echo '5,';
            }
        );
        $this->router->respond(
            function ($a, $b, $app) {
                echo '6,';
            }
        );
        $this->router->respond(
            function ($a, $b, $app) {
                echo '7,';
            }
        );
        $this->router->respond(
            function ($a, $b, $app, $router) {
                echo '8,';
                $router->skipRemaining();
            }
        );
        $this->router->respond(
            function ($a, $b, $app) {
                echo '9,';
            }
        );
        $this->router->respond(
            function ($a, $b, $app) {
                echo '10,';
            }
        );

        $this->router->dispatch();
    }

    public function testDispatchSkipCauses404()
    {
        $this->expectOutputString('404');

        $this->router->respond(
            'POST',
            '/steez',
            function ($a, $b, $app) {
	            $app->router()->skipThis();
                echo 'Style... with ease';
            }
        );
        $this->router->respond(
            'GET',
            '/nope',
            function ($a, $b, $app) {
                echo 'How did I get here?!';
            }
        );
        $this->router->respond(
            '404',
            function ($a, $b, $app) {
                echo '404';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/steez', 'POST')
        );
    }

    public function testDispatchAbort()
    {
        $this->expectOutputString('1,');

        $this->router->respond(
            function ($a, $b, $app) {
                echo '1,';
            }
        );
        $this->router->respond(
            function ($a, $b, $app) {
	            $app->router()->abort(404);
                echo '2,';
            }
        );
        $this->router->respond(
            function ($a, $b, $app) {
                echo '3,';
            }
        );

        $this->router->dispatch();

        $this->assertSame(404, $this->router->response()->code());
    }

    public function testDispatchAbortCallsHttpError()
    {
        $this->expectOutputString('1,aborted');

        $this->router->onHttpError(
            function ($code, $app) {
                echo 'aborted';
            }
        );

        $this->router->respond(
            function ($a, $b, $app) {
                echo '1,';
            }
        );
        $this->router->respond(
            function ($a, $b, $app) {
	            $app->router()->abort(404);
                echo '2,';
            }
        );
        $this->router->respond(
            function ($a, $b, $app) {
                echo '3,';
            }
        );

        $this->router->dispatch();

        $this->assertSame(404, $this->router->response()->code());
    }

    /**
     * @expectedException Klein\Exceptions\DispatchHaltedException
     */
    public function testDispatchExceptionRethrowsUnknownCode()
    {
        $this->expectOutputString('');

        $test_message = 'whatever';
        $test_code = 666;

        $this->router->respond(
            function ($a, $b, $c, $d, $klein_app) use ($test_message, $test_code) {
                throw new DispatchHaltedException($test_message, $test_code);
            }
        );

        $this->router->dispatch();

        $this->assertSame(404, $this->router->response()->code());
    }

    public function testThrowHttpExceptionHandledProperly()
    {
        $this->expectOutputString('');

        $this->router->respond(
            '/',
            function ($a, $b, $c, $d, $klein_app) {
                throw HttpException::createFromCode(400);

                echo 'hi!';
            }
        );

        $this->router->dispatch();

        $this->assertSame(400, $this->router->response()->code());
    }

    public function testHttpExceptionStopsRouteMatching()
    {
        $this->expectOutputString('one');

        $this->router->respond(
            function () {
                echo 'one';

                throw HttpException::createFromCode(404);
            }
        );
        $this->router->respond(
            function () {
                echo 'two';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/notroute')
        );
    }

    public function testOptionsAlias()
    {
        $this->expectOutputString('1,2,');

        // With path
        $this->router->options(
            '/',
            function () {
                echo '1,';
            }
        );

        // Without path
        $this->router->options(
            function () {
                echo '2,';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/', 'OPTIONS')
        );
    }

    public function testHeadAlias()
    {
        // HEAD requests shouldn't return data
        $this->expectOutputString('');

        // With path
        $this->router->head(
            '/',
            function ($request, $response) {
                echo '1,';
                $response->headers()->set('Test-1', 'yup');
            }
        );

        // Without path
        $this->router->head(
            function ($request, $response) {
                echo '2,';
                $response->headers()->set('Test-2', 'yup');
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/', 'HEAD')
        );

        $this->assertTrue($this->router->response()->headers()->exists('Test-1'));
        $this->assertTrue($this->router->response()->headers()->exists('Test-2'));
        $this->assertFalse($this->router->response()->headers()->exists('Test-3'));
    }

    public function testGetAlias()
    {
        $this->expectOutputString('1,2,');

        // With path
        $this->router->get(
            '/',
            function () {
                echo '1,';
            }
        );

        // Without path
        $this->router->get(
            function () {
                echo '2,';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/')
        );
    }

    public function testPostAlias()
    {
        $this->expectOutputString('1,2,');

        // With path
        $this->router->post(
            '/',
            function () {
                echo '1,';
            }
        );

        // Without path
        $this->router->post(
            function () {
                echo '2,';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/', 'POST')
        );
    }

    public function testPutAlias()
    {
        $this->expectOutputString('1,2,');

        // With path
        $this->router->put(
            '/',
            function () {
                echo '1,';
            }
        );

        // Without path
        $this->router->put(
            function () {
                echo '2,';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/', 'PUT')
        );
    }

    public function testDeleteAlias()
    {
        $this->expectOutputString('1,2,');

        // With path
        $this->router->delete(
            '/',
            function () {
                echo '1,';
            }
        );

        // Without path
        $this->router->delete(
            function () {
                echo '2,';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/', 'DELETE')
        );
    }


    /**
     * Advanced string route matching tests
     *
     * As the original Klein project was designed as a PHP version of Sinatra,
     * many of the following tests are ports of the Sinatra ruby equivalents:
     * https://github.com/sinatra/sinatra/blob/cd82a57154d57c18acfadbfefbefc6ea6a5035af/test/routing_test.rb
     */

    public function testMatchesEncodedSlashes()
    {
        $this->router->respond(
            '/[:a]',
            function ($request) {
                return $request->param('a');
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/foo%2Fbar'),
            null,
            true,
            Router::DISPATCH_CAPTURE_AND_RETURN
        );

        $this->assertSame(200, $this->router->response()->code());
        $this->assertSame('foo/bar', $this->router->response()->body());
    }

    public function testMatchesDotAsNamedParam()
    {
        $this->router->respond(
            '/[:foo]/[:bar]',
            function ($request) {
                return $request->param('foo');
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/user@example.com/name'),
            null,
            true,
            Router::DISPATCH_CAPTURE_AND_RETURN
        );

        $this->assertSame(200, $this->router->response()->code());
        $this->assertSame('user@example.com', $this->router->response()->body());
    }

    public function testMatchesDotOutsideOfNamedParam()
    {
        $file = null;
        $ext  = null;

        $this->router->respond(
            '/[:file].[:ext]',
            function ($request) use (&$file, &$ext) {
                $file = $request->param('file');
                $ext = $request->param('ext');

                return 'woot!';
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/unicorn.png'),
            null,
            true,
            Router::DISPATCH_CAPTURE_AND_RETURN
        );

        $this->assertSame(200, $this->router->response()->code());
        $this->assertSame('woot!', $this->router->response()->body());
        $this->assertSame('unicorn', $file);
        $this->assertSame('png', $ext);
    }

    public function testMatchesLiteralDotsInPaths()
    {
        $this->router->respond(
            '/file.ext',
            function () {
            }
        );

        // Should match
        $this->router->dispatch(
            MockRequestFactory::create('/file.ext')
        );
        $this->assertSame(200, $this->router->response()->code());

        // Shouldn't match
        $this->router->dispatch(
            MockRequestFactory::create('/file0ext')
        );
        $this->assertSame(404, $this->router->response()->code());
    }

    public function testMatchesLiteralDotsInPathBeforeNamedParam()
    {
        $this->router->respond(
            '/file.[:ext]',
            function () {
            }
        );

        // Should match
        $this->router->dispatch(
            MockRequestFactory::create('/file.ext')
        );
        $this->assertSame(200, $this->router->response()->code());

        // Shouldn't match
        $this->router->dispatch(
            MockRequestFactory::create('/file0ext')
        );
        $this->assertSame(404, $this->router->response()->code());
    }

    public function testMatchesLiteralPlusSignsInPaths()
    {
        $this->router->respond(
            '/te+st',
            function () {
            }
        );

        // Should match
        $this->router->dispatch(
            MockRequestFactory::create('/te+st')
        );
        $this->assertSame(200, $this->router->response()->code());

        // Shouldn't match
        $this->router->dispatch(
            MockRequestFactory::create('/teeeeeeeeest')
        );
        $this->assertSame(404, $this->router->response()->code());
    }

    public function testMatchesParenthesesInPaths()
    {
        $this->router->respond(
            '/test(bar)',
            function () {
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/test(bar)')
        );
        $this->assertSame(200, $this->router->response()->code());
    }

    public function testMatchesAdvancedRegularExpressions()
    {
        $this->router->respond(
            '@^/foo.../bar$',
            function () {
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/foooom/bar')
        );
        $this->assertSame(200, $this->router->response()->code());
    }

    public function testSendCallsFastCGIFinishRequest()
    {
        // Custom apc function
        implement_custom_apc_cache_functions();

        $this->router->respond(
            '/test',
            function () {
            }
        );

        $this->router->dispatch(
            MockRequestFactory::create('/test')
        );
        $this->assertSame(200, $this->router->response()->code());
    }
}
