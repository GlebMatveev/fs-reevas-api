<?php

use Slim\App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\RouteCollectorProxy;
use Slim\Routing\RouteContext;

return function (App $app) {

    $app->add(function (Request $request, RequestHandlerInterface $handler): Response {
        $routeContext = RouteContext::fromRequest($request);
        $routingResults = $routeContext->getRoutingResults();
        $methods = $routingResults->getAllowedMethods();
        $requestHeaders = $request->getHeaderLine('Access-Control-Request-Headers');

        $response = $handler->handle($request);

        $response = $response->withHeader('Access-Control-Allow-Origin', '*');
        $response = $response->withHeader('Access-Control-Allow-Methods', implode(',', $methods));
        $response = $response->withHeader('Access-Control-Allow-Headers', $requestHeaders);

        return $response;
    });

    // PRODUCTS
    $app->post('/products', '\App\Modules\Products\Products:getProducts');
    $app->options('/products', function (Request $request, Response $response): Response {
        return $response;
    });

    $app->post('/products/add', '\App\Modules\Products\Products:postProduct');
    $app->options('/products/add', function (Request $request, Response $response): Response {
        return $response;
    });

    $app->post('/products/search', '\App\Modules\Products\Products:searchProducts');
    $app->options('/products/search', function (Request $request, Response $response): Response {
        return $response;
    });

    $app->get('/products/{id}', '\App\Modules\Products\Products:getProductsById');
    $app->options('/products/{id}', function (Request $request, Response $response): Response {
        return $response;
    });

    $app->get('/products/user/{id}', '\App\Modules\Products\Products:getProductsByUserId');
    $app->options('/products/user/{id}', function (Request $request, Response $response): Response {
        return $response;
    });

    // CATEGORIES
    $app->get('/categories', '\App\Modules\Categories\Categories:getCategories');
    $app->options('/categories', function (Request $request, Response $response): Response {
        return $response;
    });

    // AUTH
    $app->post('/auth/signup', '\App\Modules\Auth\Auth:signUpUser');
    $app->options('/auth/signup', function (Request $request, Response $response): Response {
        return $response;
    });

    $app->post('/auth/signin', '\App\Modules\Auth\Auth:signInUser');
    $app->options('/auth/signin', function (Request $request, Response $response): Response {
        return $response;
    });

    $app->post('/auth/token', '\App\Modules\Auth\Auth:checkToken');
    $app->options('/auth/token', function (Request $request, Response $response): Response {
        return $response;
    });

    // SETTINGS
    $app->get('/settings', '\App\Modules\Settings\Settings:getSettings');
    $app->options('/settings', function (Request $request, Response $response): Response {
        return $response;
    });

    $app->put('/settings/{id}', '\App\Modules\Settings\Settings:putSettingById');
    $app->options('/settings/{id}', function (Request $request, Response $response): Response {
        return $response;
    });

    // REQUESTS
    $app->post('/requests', '\App\Modules\Requests\Requests:postRequest');
    $app->options('/requests', function (Request $request, Response $response): Response {
        return $response;
    });

    // USERS
    $app->get('/users', '\App\Modules\Users\Users:getUsers');
    $app->options('/users', function (Request $request, Response $response): Response {
        return $response;
    });

    $app->get('/users/{id}', '\App\Modules\Users\Users:getUsersById');
    $app->options('/users/{id}', function (Request $request, Response $response): Response {
        return $response;
    });

    // ORDERS
    $app->post('/orders', '\App\Modules\Orders\Orders:postOrder');
    $app->options('/orders', function (Request $request, Response $response): Response {
        return $response;
    });
};
