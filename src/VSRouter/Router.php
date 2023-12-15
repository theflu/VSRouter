<?php

namespace VSRouter;

class Router
{
    private $routes = array();

    public function loadRoutes($routes)
    {
        if (is_dir($routes)) {
            $di = new RecursiveDirectoryIterator($routes);
            foreach (new RecursiveIteratorIterator($di) as $filename => $file) {
                if ($file->isFile())
                    require $filename;
            }
        } elseif (is_file($routes)) {
            require $routes;
        } else {
            throw new Exception('No routes found');
        }
    }

    public function get($uri, $callback, $precheck = null, $fail = null)
    {
        $this->add('get', $uri, $callback, $precheck, $fail);
    }

    public function post($uri, $callback, $precheck = null, $fail = null)
    {
        $this->add('post', $uri, $callback, $precheck, $fail);
    }

    public function postGet($uri, $callback, $precheck = null, $fail = null)
    {
        $this->add('both', $uri, $callback, $precheck, $fail);
    }

    public function set404($callback)
    {
        if (is_callable($callback)) {
            $this->routes['404'] = array();
            $this->routes['404']['callback'] = $callback;
        }
    }

    public function get404()
    {
        http_response_code(404);
        if (isset($this->routes['404'])) {
            call_user_func($this->routes['404']['callback']);
        }
        exit();
    }

    public function redirect($uri, $to, $code = 301)
    {
        $this->add('both', $uri, function () use ($to, $code) {
            header('Location: ' . $to, true, $code);
            exit();
        });
    }

    private function uriExplode($uri)
    {
        $uri = trim($uri);
        $uri = explode('?', $uri);
        $uri_array = explode('/', $uri[0]);
        $uri_array = array_filter($uri_array);

        if (!$uri_array) array_push($uri_array, '/');

        return array_values($uri_array);
    }

    public function add($type, $uris, $callback, $precheck = null, $fail = null)
    {
        if (is_callable($callback) && (is_null($precheck) || is_callable($precheck)) && (is_null($fail) || is_callable($fail))) {
            if (!is_array($uris))
                $uris = [$uris];

            foreach ($uris as $uri) {
                $uri_array = $this->uriExplode($uri);

                $uri_count = count($uri_array);

                $route = array(
                    'uri' => $uri_array,
                    'uri_count' => $uri_count,
                    'callback' => $callback,
                    'precheck' => $precheck,
                    'fail' => $fail
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
    }

    public function route($trailing_slash = NULL, $subdir = null)
    {
        $http_method = strtolower($_SERVER['REQUEST_METHOD']);
        $uri = $_SERVER['REQUEST_URI'];

        if (!is_null($trailing_slash)) {
            $uri_trailing_slash = explode('?', $uri);

            if (strlen($uri_trailing_slash[0]) > 1) {
                if ($trailing_slash && substr($uri_trailing_slash[0], -1) != '/') {
                    $uri_trailing_slash[0] .= '/';
                    header('Location: ' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $subdir . join('', $uri_trailing_slash));
                    exit();
                } elseif (!$trailing_slash && substr($uri_trailing_slash[0], -1) == '/') {
                    $uri_trailing_slash[0] = rtrim($uri_trailing_slash[0], '/');
                    header('Location: ' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $subdir .  join('', $uri_trailing_slash));
                    exit();
                }
            }
        }

        if (isset($this->routes[$http_method]) && $this->routes[$http_method]) {
            $uri_array = $this->uriExplode($uri);

            $uri_count = count($uri_array);

            $params = array();
            $route_match = false;

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
                    if (!is_null($route['precheck'])) {

                        $precheck_result = call_user_func_array($route['precheck'], $params);

                        if (!$precheck_result && !is_null($route['fail'])) {
                            call_user_func($route['fail']);
                        } else {
                            array_unshift($params, $precheck_result);
                            $route_result = call_user_func_array($route['callback'], $params);
                            if ($route_result === false && !is_null($route['fail'])) {
                                call_user_func_array($route['fail'], $params);
                            }
                        }
                    } else {
                        $route_result = call_user_func_array($route['callback'], $params);
                        if ($route_result === false && !is_null($route['fail'])) {
                            call_user_func($route['fail']);
                        }
                    }

                    exit();
                }
            }

            if (!$route_match && isset($this->routes['404'])) {
                http_response_code(404);
                call_user_func($this->routes['404']['callback']);
                exit();
            }

            return false;
        }

        return false;
    }

    public function getRoutes()
    {
        return $this->routes;
    }
}
