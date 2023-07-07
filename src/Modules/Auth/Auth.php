<?php

namespace App\Modules\Auth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

class Auth
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function signUpUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $request->getParsedBody();
        $hash = password_hash($data["password"], PASSWORD_BCRYPT);

        // Get settings
        $ini_array = parse_ini_file("../config/settings.ini");

        // Get next auto increment value
        $sql = "SELECT auto_increment FROM information_schema.tables WHERE table_schema = '$ini_array[database]' AND table_name = 'users';";
        $statement = $this->connection->query($sql);
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        $id = (int)$result[0]["auto_increment"];
        $sql = 0;
        $statement = 0;
        $result = 0;

        // Images folder
        $serverPath = $_SERVER['DOCUMENT_ROOT'];
        if (!is_dir($serverPath . '/img/users/' .  $id)) {
            mkdir($serverPath . '/img/users/' .  $id, 0777, true);
        }

        // Saving images
        $str = substr($data["image"], 0, 4);
        if ($str == "data") {
            $arrImg = explode(',', $data["image"]);
            $base64_decode  = base64_decode($arrImg[1]);

            $extArr = explode(';', $data["image"]);
            $extArr = explode('/', $extArr[0]);
            $extension = $extArr[1];

            $imagePath = '/img/users/' . $id . '/' . uniqid() . '.' . $extension;
            $dirSave = $serverPath . $imagePath;

            file_put_contents($dirSave, $base64_decode);

            $data["image_mod"] = $imagePath;
        }

        // Searching user by email
        $sql = "SELECT * FROM users WHERE users.email = '$data[email]';";

        $statement = $this->connection->query($sql);
        $users = $statement->fetchAll(PDO::FETCH_ASSOC);

        $responseArray = array();

        // Creating a new token
        $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        function generate_string($input, $strength = 16)
        {
            $input_length = strlen($input);
            $random_string = '';
            for ($i = 0; $i < $strength; $i++) {
                $random_character = $input[random_int(0, $input_length - 1)];
                $random_string .= $random_character;
            }

            return $random_string;
        }
        $token = generate_string($permitted_chars, 20);

        // If the user is not yet in the database
        if (empty($users)) {
            $sql = "INSERT INTO
            users(
                users.name,
                users.surname,
                users.description,
                users.email,
                users.password,
                users.image,
                users.token
            ) VALUES (
                '$data[name]',
                '$data[surname]',
                '$data[description]',
                '$data[email]',
                '$hash',
                '$data[image_mod]',
                '$token'
            );";


            $statement = $this->connection->prepare($sql);
            $statement->execute();
            $insertedUserId = $this->connection->lastInsertId();

            // User added
            $responseArray["user_adding"] = true;
            $responseArray["user_id"] = $insertedUserId;
            $responseArray["user_token"] = $token;
        } else {
            // User found
            $responseArray["user_adding"] = false;
            $responseArray["user_id"] = $users[0]["id"];
            $responseArray["user_token"] = null;
        }

        $response->getBody()->write(json_encode($responseArray));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
    }

    public function signInUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $request->getParsedBody();

        $sql = "SELECT * FROM users WHERE users.email = '$data[email]';";

        $statement = $this->connection->query($sql);
        $users = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (empty($users)) {
            // User not found
            $responseArray["user_auth"] = 0;
            // } else if ($users[0]["password"] != $data["password"]) {
        } else if (!password_verify($data["password"], $users[0]["password"])) {
            // Wrong password
            $responseArray["user_auth"] = 1;
            // } else if ($users[0]["password"] == $data["password"]) {
        } else if (password_verify($data["password"], $users[0]["password"])) {
            // User found
            $responseArray["user_auth"] = 2;

            // Creating a new token
            $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            function generate_token_string($input, $strength = 16)
            {
                $input_length = strlen($input);
                $random_string = '';
                for ($i = 0; $i < $strength; $i++) {
                    $random_character = $input[random_int(0, $input_length - 1)];
                    $random_string .= $random_character;
                }

                return $random_string;
            }
            $token = generate_token_string($permitted_chars, 20);

            // Writing a new token to the database
            $sql =
                "UPDATE
                    users
                SET 
                    users.token = '$token'
                WHERE
                    users.id = '" . $users[0]["id"] . "'";

            $statement = $this->connection->prepare($sql);
            $resultUpdate = $statement->execute();

            $responseArray["user_id"] = $users[0]["id"];
            $responseArray["user_token"] = $token;
        }

        $response->getBody()->write(json_encode($responseArray));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
    }

    public function checkToken(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $result["code"] = null;

        $data = $request->getParsedBody();

        $userId = $data["userId"];
        $userToken = $data["userToken"];

        $sql = "SELECT * FROM users WHERE users.id = $userId";
        $statement = $this->connection->query($sql);
        $resultAuth = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (empty($resultAuth)) {
            $result["code"] = 0;
        } else {
            // Token lifetime calculation
            $endTokenLifeTime = strtotime($resultAuth[0]["modified_at"]);
            // Time of the last modification of the token + n hours
            $endTokenLifeTime += 3600 * 1;
            $endTokenLifeTime = date("Y-m-d H:i:s", $endTokenLifeTime);

            // If the token from the request matches the token from the database, THEN
            if ($resultAuth[0]["token"] == $userToken) {

                // If token expiration time <= current time, THEN
                if ($endTokenLifeTime <= date('Y-m-d H:i:s')) {
                    // Token matched, lifetime has expired, authorization redirect
                    $result["code"] = 0; // redirect to authorization

                    // Otherwise, if the token expiration time > current time, THEN
                } else {
                    $result["code"] = 1;
                }
            } else {

                // Token did not match, authorization redirect
                $result["code"] = 0; // redirect to authorization
            }
        }

        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
    }
}
