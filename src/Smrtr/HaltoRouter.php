<?php

namespace Smrtr;

/**
 * Class HaltoRouter
 *
 * This is a fork of https://github.com/dannyvankooten/AltoRouter with hostname matching built in
 * and basepath functionality stripped out.
 *
 * @package Smrtr
 * @author Joe Green
 */
class HaltoRouter
{
    /**
     * @var array
     */
    protected $routes = array();

    /**
     * @var array
     */
    protected $namedRoutes = array();

    /**
     * @var array
     */
    protected $httpHostGroups = array();

    /**
     * Add a hostname to a hostgroup (creates the hostgroup if it doesn't exist).
     *
     * @param string $hostname
     * @param string $hostGroup
     *
     * @return $this
     *
     * @throws HaltoRouterException
     */
    public function addHostname($hostname, $hostGroup)
    {
        if (!is_string($hostGroup)) {
            throw new HaltoRouterException("Invalid hostgroup name");
        }

        if (!is_string($hostname)) {
            throw new HaltoRouterException("Invalid hostname");
        }

        if (!array_key_exists($hostGroup, $this->httpHostGroups)) {
            $this->httpHostGroups[$hostGroup] = array();
        }

        if (!in_array($hostname, $this->httpHostGroups[$hostGroup])) {
            $this->httpHostGroups[$hostGroup][] = $hostname;
        }

        return $this;
    }

    /**
     * Add an array of hostnames to a hostgroup (creates the hostgroup if it doesn't exist).
     *
     * @param array $hostnames
     * @param string $hostGroup
     *
     * @return $this
     *
     * @throws HaltoRouterException
     */
    public function addHostnames(array $hostnames, $hostGroup)
    {
        if (!is_string($hostGroup)) {
            throw new HaltoRouterException("Invalid hostgroup name");
        }

        if (!is_array($hostnames)) {
            throw new HaltoRouterException("Array of hostnames expected");
        }

        if (!array_key_exists($hostGroup, $this->httpHostGroups)) {
            $this->httpHostGroups[$hostGroup] = array();
        }

        foreach ($hostnames as $hostname) {
            if (!is_string($hostname)) {
                continue;
            }
            if (!in_array($hostname, $this->httpHostGroups[$hostGroup])) {
                $this->httpHostGroups[$hostGroup][] = $hostname;
            }
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getHostgroups()
    {
        return $this->httpHostGroups;
    }

    /**
     * Add a route mapping to the router.
     *
     * @param string $method One, or a pipe-separated list, of Http Methods (GET|POST|PUT|DELETE)
     * @param string $route The route. Custom regex starts with @. You can use pre-set regex filters, like [i:id]
     * @param mixed  $target The target where this route should point to. Can be anything.
     * @param string $name Optional name of the route. Supply if you want to reverse route this url in your application.
     * @param string $hostGroup Optional hostGroup bound to route - the route will only match requests on these hosts.
     * @param bool   $prepend Optional
     *
     * @return $this
     *
     * @throws HaltoRouterException
     */
    public function map($method, $route, $target, $name = null, $hostGroup = null, $prepend = false)
    {
        if (!$hostGroup) {
            $hostGroup = null;
        }

        if ($prepend) {
            array_unshift($this->routes, array($method, $route, $target, $name, $hostGroup));
        } else {
            $this->routes[] = array($method, $route, $target, $name, $hostGroup);
        }

        if ($name) {
            if (array_key_exists($name, $this->namedRoutes)) {
                throw new HaltoRouterException("Can not redeclare route $name");
            }

            $this->namedRoutes[$name] = array($route, $hostGroup);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * @return array
     */
    public function getNamedRoutes()
    {
        return $this->namedRoutes;
    }

    /**
     * Match a given Request against stored routes.
     *
     * @param string $requestUrl
     * @param string $requestMethod
     * @param string $requestHost
     * @param bool   $looseMatching
     *
     * @return array|boolean Array with route information on success, false on failure (no match).
     */
    public function match($requestUrl, $requestMethod, $requestHost, $looseMatching)
    {
        $params = array();
        $validGroups = $this->getValidHostGroups($requestHost);

        foreach ($this->routes as $handler) {
            list($method, $_route, $target, $name, $hostGroup) = $handler;
            if (null !== $hostGroup) {
                $hostGroups = explode('|', $hostGroup);
                if (!count(array_intersect($hostGroups, $validGroups))) {
                    continue;
                }
            }

            $methods = explode('|', $method);
            $method_match = false;
            foreach ($methods as $method) {
                if (strcasecmp($requestMethod, $method) === 0) {
                    $method_match = true;
                    break;
                }
            }
            if (!$method_match) {
                continue;
            }

            if ($looseMatching) {
                if (substr($_route, strlen($_route) - 1, 1) === '$') {
                   if (substr($_route, strlen($_route) - 2, 1) === '/') {
                       $_route = sprintf('%s?$', substr($_route, 0, strlen($_route) - 1));
                   } elseif (substr($_route, strlen($_route) - 2, 2) !== '/?') {
                       $_route = sprintf('%s/?$', substr($_route, 0, strlen($_route) - 1));
                   }
                } elseif (substr($_route, strlen($_route) - 1, 1) === '/') {
                    $_route = sprintf('%s?', $_route);
                } elseif (substr($_route, strlen($_route) - 2, 2) !== '/?') {
                    $_route = sprintf('%s/?', $_route);
                }
            } 

            if ($_route === '*') {
                $match = true;
            } elseif (isset($_route[0]) && $_route[0] === '@') {
                $match = preg_match('`' . substr($_route, 1) . '`', $requestUrl, $params);
            } else {
                $route = null;
                $regex = false;
                $j = 0;
                $n = isset($_route[0]) ? $_route[0] : null;
                $i = 0;
                while (true) {
                    if (!isset($_route[$i])) {
                        break;
                    } elseif (false === $regex) {
                        $c = $n;
                        $regex = $c === '[' || $c === '(' || $c === '.';
                        if (false === $regex && false !== isset($_route[$i+1])) {
                            $n = $_route[$i + 1];
                            $regex = $n === '?' || $n === '+' || $n === '*' || $n === '{';
                        }
                        if (false === $regex && $c !== '/' && (!isset($requestUrl[$j]) || $c !== $requestUrl[$j])) {
                            continue 2;
                        }
                        $j++;
                    }
                    $route .= $_route[$i++];
                }
                $regex = $this->compileRoute($route);
                $match = preg_match($regex, $requestUrl, $params);
            }

            if ($match == true || $match > 0) {

                if ($params) {
                    foreach ($params as $key => $value) {
                        if (is_numeric($key)) {
                            unset($params[$key]);
                        } elseif ('controller' == $key) {
                            $controller = preg_replace('#([-]+)#', ' ', urldecode($value));
                            $controller = str_replace(' ', '', ucwords($controller));
                            unset($params[$key]);
                        } elseif ('action' == $key) {
                            $action = preg_replace('#([-]+)#', ' ', urldecode($value));
                            $action = lcfirst(str_replace(' ', '', ucwords($action)));
                            unset($params[$key]);
                        } else {
                            $params[$key] = urldecode($value);
                        }
                    }
                }

                if (isset($controller) || isset($action)) {
                    $targets = explode('@', $target, 2);
                    $C = isset($targets[0]) ? $targets[0] : 'Index';
                    $A = isset($targets[1]) ? $targets[1] : 'index';
                    $target =
                        (isset($controller) ? str_replace('@', '', $controller) : $C)
                        .'@'
                        .(isset($action) ? str_replace('@', '', $action) : $A);
                }

                return array(
                    'target' => $target,
                    'params' => $params,
                    'name' => $name
                );
            }
        }

        return false;
    }

    /**
     * @param string $requestHost
     *
     * @return array
     */
    protected function getValidHostGroups($requestHost)
    {
        $validGroups = array();

        foreach ($this->httpHostGroups as $group => $hostnames) {
            if (in_array($requestHost, $hostnames)) {
                $validGroups[] = $group;
            }
        }

        return $validGroups;
    }

    /**
     * Reversed routing
     *
     * Generate the URL for a named route. Replace regexes with supplied parameters.
     *
     * @param string $routeName The name of the route.
     * @param array  $params Associative array of parameters to replace placeholders with.
     * @param string $hostname Optional; specify a valid hostname or one will be detected automatically if possible.
     * @param string $protocol Specify a protocol including "://" if you wish
     * @param int    $port Optional; specify a port to use in the generated URL
     *
     * @return string The URL of the route with named parameters in place.
     *
     * @throws HaltoRouterException
     */
    public function generate($routeName, array $params = array(), $hostname = null, $protocol = '//', $port = null)
    {
        // Check if named route exists
        if(!isset($this->namedRoutes[$routeName])) {
            throw new HaltoRouterException("Route '{$routeName}' does not exist.");
        }

        // Replace named parameters
        $hostGroup = $this->namedRoutes[$routeName][1];
        $route = $this->namedRoutes[$routeName][0];
        $url = $route;

        if (preg_match_all('`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`', $route, $matches, PREG_SET_ORDER)) {

            foreach($matches as $match) {
                list($block, $pre, $type, $param, $optional) = $match;

                if ($pre) {
                    $block = substr($block, 1);
                }

                if(isset($params[$param])) {
                    $url = str_replace($block, $params[$param], $url);
                } elseif ($optional) {
                    $url = str_replace($pre . $block, '', $url);
                }
            }


        }

        // Try to include the hostname and protocol
        $hasHostPart = false;
        if (is_string($hostGroup) && array_key_exists($hostGroup, $this->httpHostGroups)) {

            $hasHostPart = true;

            if (is_string($hostname) && in_array($hostname, $this->httpHostGroups[$hostGroup])) {
                $hostPart = rtrim($hostname, '/');
            } else {
                $hostPart = $this->httpHostGroups[$hostGroup][0];
            }

            if (is_int($port)) {
                $hostPart .= ':' . $port;
            }

            $url = $hostPart . '/' . ltrim($url, '/');
        }

        if ($hasHostPart) {
            $url = $protocol . $url;
        }

        return $url;
    }

    /**
     * Compile the regex for a given route
     */
    private function compileRoute($route)
    {
        if (preg_match_all('`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`', $route, $matches, PREG_SET_ORDER)) {

            $match_types = array(
                'i'  => '[0-9]++',
                'a'  => '[0-9A-Za-z]++',
                'h'  => '[0-9A-Fa-f]++',
                '*'  => '.+?',
                '**' => '.++',
                ''   => '[^/\.]++'
            );

            foreach ($matches as $match) {
                list($block, $pre, $type, $param, $optional) = $match;

                if (isset($match_types[$type])) {
                    $type = $match_types[$type];
                }
                if ($pre === '.') {
                    $pre = '\.';
                }

                //Older versions of PCRE require the 'P' in (?P<named>)
                $pattern = '(?:'
                        . ($pre !== '' ? $pre : null)
                        . '('
                        . ($param !== '' ? "?P<$param>" : null)
                        . $type
                        . '))'
                        . ($optional !== '' ? '?' : null);

                $route = str_replace($block, $pattern, $route);
            }

        }
        return "`^$route$`";
    }
}
