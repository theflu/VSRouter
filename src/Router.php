<?php

namespace VSRouter;

class Router
{
    /**
     * @var array Holds all the registered routes, categorized by HTTP method.
     */
    private $routes = array();

    /**
     * Loads route definitions from a file or a directory.
     *
     * @param string $routes The path to a file or directory containing route definitions.
     * @throws \Exception If the provided path is not a valid file or directory.
     */
    public function loadRoutes($routes)
    {
        if (is_dir($routes)) {
            // If it's a directory, recursively iterate through all files and include them.
            $di = new \RecursiveDirectoryIterator($routes);
            foreach (new \RecursiveIteratorIterator($di) as $filename => $file) {
                if ($file->isFile()) {
                    require $filename;
                }
            }
        } elseif (is_file($routes)) {
            // If it's a single file, just include it.
            require $routes;
        } else {
            throw new \Exception('No routes found');
        }
    }

    /**
     * Registers a new route for the GET HTTP method.
     *
     * @param string|array $uri The URI pattern for the route.
     * @param callable $callback The function to execute when the route matches.
     * @param callable|null $precheck An optional function to run before the callback.
     * @param callable|null $fail An optional function to run if the pre-check or callback fails.
     */
    public function get($uri, $callback, $precheck = null, $fail = null)
    {
        $this->add('get', $uri, $callback, $precheck, $fail);
    }

    /**
     * Registers a new route for the POST HTTP method.
     */
    public function post($uri, $callback, $precheck = null, $fail = null)
    {
        $this->add('post', $uri, $callback, $precheck, $fail);
    }

    /**
     * Registers a new route for both GET and POST HTTP methods.
     */
    public function postGet($uri, $callback, $precheck = null, $fail = null)
    {
        $this->add(['post', 'get'], $uri, $callback, $precheck, $fail);
    }

    /**
     * Sets a callback function for handling 404 Not Found errors.
     *
     * @param callable $callback The function to execute for 404 errors.
     */
    public function set404($callback)
    {
        if (is_callable($callback)) {
            $this->routes['404'] = $callback;
        }
    }

    /**
     * Executes the 404 callback and terminates the script.
     */
    public function get404()
    {
        http_response_code(404);
        if (isset($this->routes['404'])) {
            call_user_func($this->routes['404']);
        }
        
        exit();
    }

    /**
     * Creates a redirect from one URI to another.
     *
     * @param string $uri The URI to redirect from.
     * @param string $to The URI to redirect to.
     * @param int $code The HTTP status code for the redirect.
     */
    public function redirect($uri, $to, $code = 301)
    {
        // Add a route for the redirect
        $this->add('both', $uri, function () use ($to, $code) {
            header('Location: ' . $to, true, $code);
            exit();
        });
    }

    /**
     * Splits a URI into its component parts.
     *
     * @param string $uri The URI to explode.
     * @return array The URI parts.
     */
    private function uriExplode($uri)
    {
        // Trim whitespace and remove the query string.
        $uri = trim($uri);
        $uri = explode('?', $uri);
        // Split the URI by slashes.
        $uri_array = explode('/', $uri[0]);
        // Remove any empty values, which can result from leading/trailing slashes.
        $uri_array = array_filter($uri_array);

        // If the URI was just "/", the array will be empty. Add it back.
        if (!$uri_array) {
            array_push($uri_array, '/');
        }

        // Re-index the array.
        return array_values($uri_array);
    }

    /**
     * The core method for adding a route to the routing table.
     *
     * @param string|array $type The HTTP method(s) for the route.
     * @param string|array $uris The URI pattern(s).
     * @param callable $callback The callback function.
     * @param callable|null $precheck The pre-check function.
     * @param callable|null $fail The failure function.
     */
    public function add($request_types, $uris, $callback, $precheck = null, $fail = null)
    {
        // Basic validation to ensure callbacks are actually callable.
        if (is_callable($callback) && (is_null($precheck) || is_callable($precheck)) && (is_null($fail) || is_callable($fail))) {
            // Allow for a single request type or an array of types
            if (!is_array($request_types)) {
                $request_types = [$request_types];
            }

            // Allow for a single URI or an array of URIs.
            if (!is_array($uris)) {
                $uris = [$uris];
            }

            foreach ($uris as $uri) {
                $uri_array = $this->uriExplode($uri);
                $uri_count = count($uri_array);

                // Build the route array
                $route = array(
                    'uri' => $uri_array,
                    'uri_count' => $uri_count,
                    'callback' => $callback,
                    'precheck' => $precheck,
                    'fail' => $fail
                );

                // Add a route for each request type
                foreach ($request_types as $request_type) {
                    if (!isset($this->routes[$request_type])) $this->routes[$request_type] = array();
                    array_push($this->routes[$request_type], $route);
                }
            }
        }
    }

    /**
     * This is the main method that processes the incoming request and dispatches it to the correct route.
     *
     * @param bool|null $trailing_slash Enforces or removes trailing slashes.
     * @param string|null $subdir If the application is in a subdirectory, specify it here.
     */
    public function route($trailing_slash = NULL, $subdir = null)
    {
        // Directly accessing superglobals makes this method harder to test.
        $http_method = strtolower($_SERVER['REQUEST_METHOD']);
        $uri = $_SERVER['REQUEST_URI'];

        // Optional trailing slash enforcement.
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

            // Loop through all registered routes for the current HTTP method.
            foreach ($this->routes[$http_method] as $route) {
                // If the number of URI segments doesn't match, skip.
                if ($route['uri_count'] == $uri_count) {
                    // Check each segment of the URI.
                    foreach ($route['uri'] as $k => $u) {
                        // This is the core matching logic. It checks for literal matches and placeholders.
                        if ($uri_array[$k] != $u && (substr($u, 0, 1) != '{' && substr($u, -1) != '}')) {
                            $route_match = false;
                            $params = array(); // Reset params for the next route check.
                            break;
                        } else {
                            $route_match = true;
                        }

                        // If a segment is a placeholder, capture its value.
                        if (substr($u, 0, 1) == '{' && substr($u, -1) == '}') {
                            array_push($params, $uri_array[$k]);
                        }
                    }
                }

                if ($route_match) {
                    // If a pre-check function is defined, execute it.
                    if (!is_null($route['precheck'])) {
                        $precheck_result = call_user_func_array($route['precheck'], $params);

                        if (!$precheck_result && !is_null($route['fail'])) {
                            call_user_func($route['fail']);
                        } else {
                            // The result of the pre-check is passed as the first argument to the main callback.
                            array_unshift($params, $precheck_result);
                            $route_result = call_user_func_array($route['callback'], $params);
                            if ($route_result === false && !is_null($route['fail'])) {
                                call_user_func_array($route['fail'], $params);
                            }
                        }
                    } else {
                        // No pre-check, just call the main callback.
                        $route_result = call_user_func_array($route['callback'], $params);
                        if ($route_result === false && !is_null($route['fail'])) {
                            call_user_func($route['fail']);
                        }
                    }
                    
                    // A route was matched and handled, so we can stop processing.
                    exit();
                }
            }
        }

        // If we've gone through all routes and none have matched, it's a 404.
        $this->get404();
    }

    /**
     * Returns the array of all registered routes. Useful for debugging.
     *
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }
}