<?php

/**
 * @author Ivan Tikhonov <tihh@yandex.ru>
 */
namespace SimpleRouter;

class Router {

    /**
     * @var string
     */
    private $_url;

    /**
     * @var string
     */
    private $_method;

    /**
     * @var array
     */
    private $_urlComponents;

    /**
     * @var array
     */
    private $_mappings = [
        'GET'  => [],
        'POST' => []
    ];

    /**
     *  @var array
     */
    private $_typesRegExp = [
        'i' => '[0-9]+',
		's' => '[a-zA-Z0-9\-_]+',
		'all' => '.*'
    ];

    /**
     * @var string[]
     */
    private $_backMappings = [];

    public function __construct() {
        $this->_loadUrl();
        $this->_loadMethod();
        $this->_parseUrl();
    }

    /**
     * @param string $methodsString
     * @param string $url
     * @param callable $callable
     * @param string $name
     * @throws Exception
     */
    public function map($methodsString, $url, $callable, $name) {
        $methods = explode('|', $methodsString);
        $methods = array_map('trim', $methods);
		if (array_key_exists($name, $this->_backMappings)) {
			throw new Exception("Duplicate route with name: {$name}.");
		}
        foreach ($methods as $method) {
			if (array_key_exists($url, $this->_mappings[$method])) {
				throw new Exception("Duplicate route with url: {$url}.");
			}
			$this->_mappings[$method][$url] = $callable;
			$this->_backMappings[$name] = $url;
		}
    }

    /**
     * @return Route
     */
    public function route() {
        $this->_mappings = $this->_mappings[$this->_method];
        $route = $this->_routeHard();
        if ($route === null) {
            $route = $this->_routeWithMask();
        }
        return $route;
    }
    private function _routeHard() {
        $route = new Route();
        if (!array_key_exists($this->_url, $this->_mappings)) {
            return null;
        }
        $route->callable = $this->_mappings[$this->_url];
        $route->params = [];
        return $route;
    }
    private function _routeWithMask() {
        $route = null;
        foreach ($this->_mappings as $urlMask => $callable) {
            if (strpos($urlMask, '[') !== false) {
                $matches = $this->_checkMask($urlMask, $this->_url);
                if (count($matches) === 0) {
                    continue;
                }
                array_shift($matches);
                $route = new Route();
                $route->callable = $callable;
                $route->params = $matches;
            }
        }
        return $route;
    }
    private function _checkMask($urlMask, $requestUrl) {
        $types = array_keys($this->_typesRegExp);
        $typesRegexp = implode('|', $types);
        $urlMaskRegExp = '~^'.preg_replace_callback(
            '/\[:(' . $typesRegexp . ')\]/',
            function ($matches) {
                return "({$this->_typesRegExp[$matches[1]]})";
            },
            $urlMask
        ) . '$~';
        if (preg_match($urlMaskRegExp, $requestUrl, $matches) !== 1) {
            return [];
        }
        return $matches;
    }

    /**
     * @param string $name
     * @param array $params
     * @return string
     */
    public function generate($name, array $params = []) {
    	if (!array_key_exists($name, $this->_backMappings)) {
    		return null;
		}
        $urlMask = $this->_backMappings[$name];
        if (strpos($urlMask,'[') !== false) {
			foreach ($params as $param) {
				$urlMask = preg_replace('/\[[^\]]+\]/', $param, $urlMask, 1);
			}
        }
		return $urlMask;
    }

    private function _parseUrl() {
        $this->_urlComponents = explode('/', $this->_url);
    }
    private function _loadMethod() {
        $this->_method = $_SERVER['REQUEST_METHOD'];
    }
    private function _loadUrl() {
        $this->_url = parse_url($_SERVER['REQUEST_URI'])['path'];
    }

    /**
     * @return string
     */
    public function getUrl() {
        return $this->_url;
    }

    /**
     * @return string
     */
    public function getMethod() {
        return $this->_method;
    }
}