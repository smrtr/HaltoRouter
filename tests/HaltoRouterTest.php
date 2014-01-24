<?php

use Smrtr\HaltoRouter;

class HaltoRouterTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @var HaltoRouter
	 */
	protected $router;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp()
	{
		$this->router = new HaltoRouter;
	}

    /**
     * @covers Smrtr\HaltoRouter::addHostname
     */
    public function testAddHostname()
    {
        $hostname = 'www.example.com';
        $hostGroup = "example website";

        $this->router->addHostname($hostname, $hostGroup);

        $hostGroups = $this->router->getHostgroups();

        $this->assertSame(array($hostname), $hostGroups[$hostGroup]);
    }

    /**
     * @covers Smrtr\HaltoRouter::addHostnames
     */
    public function testAddHostnames()
    {
        $hostnames = array('www.example.com', 'public.example.com');
        $hostGroup = "example website";

        $this->router->addHostnames($hostnames, $hostGroup);

        $hostGroups = $this->router->getHostgroups();

        $this->assertSame($hostnames, $hostGroups[$hostGroup]);
    }

	/**
	 * @covers Smrtr\HaltoRouter::map
	 */
	public function testMap()
	{
		$method = 'POST';
		$route = '/[:controller]/[:action]';
        $target = function(){};
		
		$this->router->map($method, $route, $target);
		
		$routes = $this->router->getRoutes();

		$this->assertSame(array($method, $route, $target, null, null), $routes[0]);
	}

	/**
	 * @covers Smrtr\HaltoRouter::map
	 */
	public function testMapWithName()
	{
		$method = 'POST';
		$route = '/[:controller]/[:action]';
		$target = function(){};
		$name = 'myroute';
		
		$this->router->map($method, $route, $target, $name);
		
		$routes = $this->router->getRoutes();
		$this->assertSame(array($method, $route, $target, $name, null), $routes[0]);
		
		$named_routes = $this->router->getNamedRoutes();
		$this->assertSame(array($route, null), $named_routes[$name]);
		
		try{
			$this->router->map($method, $route, $target, $name);
			$this->fail('Should not be able to add existing named route');
		}catch(Exception $e){
			$this->assertSame("Can not redeclare route {$name}", $e->getMessage());
		}
	}

    /**
     * @covers Smrtr\HaltoRouter::map
     */
    public function testMapWithHostgroup()
    {
        $method = 'GET|POST';
        $route = '/[:controller]/[:action]';
        $target = function(){};
        $hostGroup = 'example website';

        $this->router->map($method, $route, $target, null, $hostGroup);

        $routes = $this->router->getRoutes();
        $this->assertSame(array($method, $route, $target, null, $hostGroup), $routes[0]);
    }

	/**
	 * @covers Smrtr\HaltoRouter::generate
	 */
	public function testGenerate()
	{
		$params = array(
			'controller' => 'test',
			'action' => 'someaction'
		);
		
		$this->router->map('GET', '/[:controller]/[:action]', function(){}, 'foo_route');
		
		$this->assertSame('/test/someaction',
			$this->router->generate('foo_route', $params));
		
		$params = array(
			'controller' => 'test',
			'action' => 'someaction',
			'type' => 'json'
		);
		
		$this->assertSame('/test/someaction',
			$this->router->generate('foo_route', $params));
		
	}

    /**
     * @covers Smrtr\HaltoRouter::generate
     */
	public function testGenerateWithOptionalUrlParts()
	{
		$this->router->map('GET', '/[:controller]/[:action].[:type]?', function(){}, 'bar_route');
		
		$params = array(
			'controller' => 'test',
			'action' => 'someaction'
		);
		
		$this->assertSame('/test/someaction',
			$this->router->generate('bar_route', $params));
		
		$params = array(
			'controller' => 'test',
			'action' => 'someaction',
			'type' => 'json'
		);
		
		$this->assertSame('/test/someaction.json',
			$this->router->generate('bar_route', $params));
	}

    /**
     * @covers Smrtr\HaltoRouter::generate
     */
	public function testGenerateWithNonexistingRoute()
	{
		try{
			$this->router->generate('nonexisting_route');
			$this->fail('Should trigger an exception on nonexisting named route');
		}catch(Exception $e){
			$this->assertSame("Route 'nonexisting_route' does not exist.", $e->getMessage());
		}
	}

    /**
     * @covers Smrtr\HaltoRouter::generate
     */
    public function testGenerateWithImplicitHostname()
    {
        $method = 'GET|POST';
        $route = '/[:controller]/[:action]';
        $target = function(){};
        $name = 'myroute';
        $hostGroup = 'example website';

        $this->router->addHostname('www.example.com', $hostGroup);
        $this->router->addHostname('public.example.com', $hostGroup);
        $this->router->map($method, $route, $target, $name, $hostGroup);

        $params = array('controller'=>'foo', 'action'=>'bar');

        $this->assertSame(
            '//www.example.com/foo/bar',
            $this->router->generate($name, $params)
        );
    }

    /**
     * @covers Smrtr\HaltoRouter::generate
     */
    public function testGenerateWithExplicitHostnameAndProtocol()
    {
        $method = 'GET|POST';
        $route = '/[:controller]/[:action]';
        $target = function(){};
        $name = 'myroute';
        $hostGroup = 'example website';

        $this->router->addHostname('www.example.com', $hostGroup);
        $this->router->addHostname('public.example.com', $hostGroup);
        $this->router->map($method, $route, $target, $name, $hostGroup);

        $params = array('controller'=>'foo', 'action'=>'bar');

        $this->assertSame(
            'https://public.example.com/foo/bar',
            $this->router->generate($name, $params, 'public.example.com', 'https://')
        );
    }

    /**
     * @covers Smrtr\HaltoRouter::generate
     */
    public function testGenerateWithExplicitHostnameAndProtocolAndPort()
    {
        $method = 'GET|POST';
        $route = '/[:controller]/[:action]';
        $target = function(){};
        $name = 'myroute';
        $hostGroup = 'example website';

        $this->router->addHostname('www.example.com', $hostGroup);
        $this->router->addHostname('public.example.com', $hostGroup);
        $this->router->map($method, $route, $target, $name, $hostGroup);

        $params = array('controller'=>'foo', 'action'=>'bar');

        $this->assertSame(
            'http://public.example.com:8080/foo/bar',
            $this->router->generate($name, $params, 'public.example.com', 'http://', 8080)
        );
    }

    /**
     * @covers Smrtr\HaltoRouter::generate
     */
    public function testGenerateWithExplicitProtocolAndPort()
    {
        $method = 'GET|POST';
        $route = '/[:controller]/[:action]';
        $target = function(){};
        $name = 'myroute';
        $hostGroup = 'example website';

        $this->router->addHostname('www.example.com', $hostGroup);
        $this->router->addHostname('public.example.com', $hostGroup);
        $this->router->map($method, $route, $target, $name, $hostGroup);

        $params = array('controller'=>'foo', 'action'=>'bar');

        $this->assertSame(
            'http://www.example.com:8080/foo/bar',
            $this->router->generate($name, $params, null, 'http://', 8080)
        );
    }

    /**
     * @covers Smrtr\HaltoRouter::generate
     */
    public function testGenerateWithInvalidHostname()
    {
        $method = 'GET|POST';
        $route = '/[:controller]/[:action]';
        $target = function(){};
        $name = 'myroute';
        $hostGroup = 'example website';

        $this->router->addHostname('www.example.com', $hostGroup);
        $this->router->addHostname('public.example.com', $hostGroup);
        $this->router->map($method, $route, $target, $name, $hostGroup);

        $params = array('controller'=>'foo', 'action'=>'bar');

        $this->assertSame(
            'https://www.example.com/foo/bar',
            $this->router->generate($name, $params, 'this hostname is not in the hostgroup', 'https://')
        );
    }

	/**
	 * @covers Smrtr\HaltoRouter::match
	 * @covers Smrtr\HaltoRouter::compileRoute
	 */
	public function testMatch()
	{
		$this->router->map('GET', '/foo/[:controller]/[:action]', 'foo_action', 'foo_route');
		
		$this->assertSame(array(
			'target' => 'Test@do',
			'params' => array(),
			'name' => 'foo_route'
		), $this->router->match('/foo/test/do', 'GET', 'www.example.com'));
		
		$this->assertFalse($this->router->match('/foo/test/do', 'POST', 'www.example.com'));
	}

    /**
     * @covers Smrtr\HaltoRouter::match
     * @covers Smrtr\HaltoRouter::compileRoute
     */
	public function testMatchWithFixedParamValues()
	{
		$this->router->map('POST','/users/[i:id]/[delete|update:action]', 'usersController', 'users_do');
		
		$this->assertSame(array(
			'target' => 'usersController@delete',
			'params' => array(
				'id' => '1'
			),
			'name' => 'users_do'
		), $this->router->match('/users/1/delete', 'POST', 'www.example.com'));
		
		$this->assertFalse($this->router->match('/users/1/delete', 'GET', 'www.example.com'));
		$this->assertFalse($this->router->match('/users/abc/delete', 'POST', 'www.example.com'));
		$this->assertFalse($this->router->match('/users/1/create', 'GET', 'www.example.com'));
	}

    /**
     * @covers Smrtr\HaltoRouter::match
     * @covers Smrtr\HaltoRouter::compileRoute
     */
	public function testMatchWithOptionalUrlParts()
	{
		$this->router->map('GET', '/bar/[:controller]/[:action].[:type]?', 'bar_action', 'bar_route');
		
		$this->assertSame(array(
			'target' => 'Test@do',
			'params' => array(
				'type' => 'json'
			),
			'name' => 'bar_route'
		), $this->router->match('/bar/test/do.json', 'GET', 'www.example.com'));
		
	}

    /**
     * @covers Smrtr\HaltoRouter::match
     * @covers Smrtr\HaltoRouter::compileRoute
     */
	public function testMatchWithWildcard()
	{
		$this->router->map('GET', '/a', 'foo_action', 'foo_route');
		$this->router->map('GET', '*', 'bar_action', 'bar_route');
		
		$this->assertSame(array(
			'target' => 'bar_action',
			'params' => array(),
			'name' => 'bar_route'
		), $this->router->match('/everything', 'GET', 'www.example.com'));

        $this->assertSame(array(
            'target' => 'foo_action',
            'params' => array(),
            'name' => 'foo_route'
        ), $this->router->match('/a', 'GET', 'www.example.com'));
	}

    /**
     * @covers Smrtr\HaltoRouter::match
     * @covers Smrtr\HaltoRouter::compileRoute
     */
	public function testMatchWithCustomRegexp()
	{
		$this->router->map('GET', '@^/[a-z]*$', 'bar_action', 'bar_route');
		
		$this->assertSame(array(
			'target' => 'bar_action',
			'params' => array(),
			'name' => 'bar_route'
		), $this->router->match('/everything', 'GET', 'www.example.com'));
		
		$this->assertFalse($this->router->match('/some-other-thing', 'GET', 'www.example.com'));
		
	}

    /**
     * @covers Smrtr\HaltoRouter::match
     * @covers Smrtr\HaltoRouter::compileRoute
     */
    public function testMatchWithHostGroup()
    {
        $this->router->addHostnames(array('www.example.com', 'public.example.com'), 'example website');
        $this->router->map('GET', '/foo/bar', 'Misc@foobar', null, 'example website');

        $this->assertFalse($this->router->match('/foo/bar', 'GET', 'private.example.com'));
        $this->assertSame(array(
            'target' => 'Misc@foobar',
            'params' => array(),
            'name'   => null
        ), $this->router->match('/foo/bar', 'GET', 'public.example.com'));
    }
}
