<?php

use Selective\BasePath\BasePathMiddleware;
use Slim\App;
use Slim\Middleware\ErrorMiddleware;

return function (App $app) {

    // Parse json, form data and xml
    $app->addBodyParsingMiddleware();

    $ini_array = parse_ini_file("settings.ini");

    $app->add(new Tuupola\Middleware\HttpBasicAuthentication([
        "users" => [
            "root" => $ini_array["basicroot"]
        ],
        "error" => function ($response, $arguments) {
            $data = [];
            $data["status"] = "error";
            $data["message"] = $arguments["message"];

            $body = $response->getBody();
            $body->write(json_encode($data, JSON_UNESCAPED_SLASHES));

            return $response->withBody($body);
        }
    ]));

    // Add the Slim built-in routing middleware
    $app->addRoutingMiddleware();

    $app->add(BasePathMiddleware::class); // <--- here

    // Catch exceptions and errors
    $app->add(ErrorMiddleware::class);
};
