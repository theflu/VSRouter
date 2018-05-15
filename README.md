# VSRouter
Very Simple PHP Router

## Setup
### Define Routes
routes.php

    <?php
    $router = new Router;
    
    // GET request to root of domain
    $router->get('/', function () {
        echo "Hello World I'am a GET request";
    });
    
    // POST request to root of domain
    $router->post('/', function () {
        echo "Hello World I'am a POST request";
    });
    
    // POST or GET request to /postGet
    $router->postGet('/postGet', function () {
        echo "Hello World I'am a POST/GET request";
    });
    
    // 301 redirect '/oldPage' to '/newPage'
    $router->redirect('/oldPage', '/newPage', 301);
    
    // Set 404 page
    $router->set404(function () {
        echo "Hello World 404 page not found";
    });
    
    // URL variables
    $router->get('/user/{$username}', function ($username) {
        echo "Hello World I'am ".$username;
    });
    
    // Pass object to route
    $router->get('/user/{$username}', function ($username) use ($twig) {
        echo $twig->render('user.twig', array('username' => $username);
    });
    
    ?>


## Build Index
index.php

    <?php
    require 'vsrouter.php';
    require 'routes.php';
    
    $router->route();
    ?>


## .htaccess
    RewriteEngine on
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php?q=$1&p=$2 [L,NC,QSA]
