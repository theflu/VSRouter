<?php

/**
 * Created by PhpStorm.
 * User: Flu
 * Date: 10/10/2017
 * Time: 5:40 PM
 */
class Router
{
    public $routes = array();

    public function get ($uri, $callback) {
        $this->add('get', $uri, $callback);
    }

    public function post ($uri, $callback) {
        $this->add('post', $uri, $callback);
    }

    public function postGet ($uri, $callback) {
        $this->add('both', $uri, $callback);
    }

    public function set404 ($callback) {
        if (is_callable($callback)) {
            $this->routes['404'] = array();
            $this->routes['404']['callback'] = $callback;
        }
    }

    public function redirect ($uri, $to, $code = 301) {
        $this->add('both', $uri, function () use ($to, $code) {
            header('Location: '.$to, true, $code);
            exit();
        });
    }

    private function uriExplode ($uri) {
        $uri = trim($uri);
        $uri = explode('?', $uri);
        $uri_array = explode('/', $uri[0]);
        $uri_array = array_filter($uri_array);

        if (!$uri_array) array_push($uri_array, '/');

        return array_values($uri_array);
    }

    public function add ($type, $uri, $callback) {
        if (is_callable($callback)) {
            $uri_array = $this->uriExplode($uri);

            $uri_count = count($uri_array);

            $route = array(
                'uri'      => $uri_array,
                'uri_count'    => $uri_count,
                'callback' => $callback
            );

            if ($type == 'both') {
                if (!isset($this->routes['get'])) $this->routes['get'] = array();
                if (!isset($this->routes['post'])) $this->routes['post'] = array();

                array_push($this->routes['get'], $route);
                array_push($this->routes['post'], $route);
            } else {
                if (is_array($type)) {
                    foreach ($type as $t) {
                        if (!isset($this->routes[$t])) $this->routes[$t] = array();

                        array_push($this->routes[$t], $route);
                    }
                } else {
                    if (!isset($this->routes[$type])) $this->routes[$type] = array();

                    array_push($this->routes[$type], $route);
                }
            }

        }
    }

    public function route () {
        $http_method = strtolower($_SERVER['REQUEST_METHOD']);
        $uri = $_SERVER['REQUEST_URI'];

        if (isset($this->routes[$http_method]) && $this->routes[$http_method]) {
            $uri_array = $this->uriExplode($uri);

            $uri_count = count($uri_array);

            $params = array();
            $route_match =  false;

            foreach ($this->routes[$http_method] as $route) {
                if ($route['uri_count'] == $uri_count) {
                    foreach ($route['uri'] as $k => $u) {
                        if ($uri_array[$k] != $u && (substr($u, 0, 1) != '{' && substr($u, -1) != '}')) {
                            $route_match = false;
                            $params = array();
                            break;
                        } else {
                            $route_match = true;
                        }

                        if (substr($u, 0, 1) == '{' && substr($u, -1) == '}') {
                            array_push($params, $uri_array[$k]);
                        }
                    }
                }

                if ($route_match) {
                    return call_user_func_array($route['callback'], $params);
                }
            }

            if (!$route_match && isset($this->routes['404'])) {
                http_response_code(404);
                return call_user_func($this->routes['404']['callback']);
            }

            return false;
        }

        return false;
    }
}