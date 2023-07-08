<?php

namespace App\Modules\Orders;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

class Orders
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function postOrder(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $request->getParsedBody();

        // Mail sanding
        $this->sendEmail($data["userId"], $data["userEmail"], $data["quantity"], $data["total"], $data["cart"]);

        // Writing order to a database
        $sql = "INSERT INTO
                orders(
                    orders.user_id,
                    orders.email,
                    orders.fullname,
                    orders.cart,
                    orders.cart_quantity,
                    orders.cart_price
                ) VALUES (
                    '$data[userId]',
                    '$data[userEmail]',
                    '$data[userFullName]',
                    '$data[cart]',
                    '$data[quantity]',
                    '$data[total]'
                );";

        $statement = $this->connection->prepare($sql);
        $result = $statement->execute();


        // Getting access token
        $sql = "SELECT * FROM settings WHERE settings.id='2' LIMIT 1;";
        $statement = $this->connection->query($sql);
        $resultTokens = $statement->fetchAll(PDO::FETCH_ASSOC);
        $accessToken = $resultTokens[0]["value"];

        // Getting refresh token
        $sql = "SELECT * FROM settings WHERE settings.id='3' LIMIT 1;";
        $statement = $this->connection->query($sql);
        $resultTokens = $statement->fetchAll(PDO::FETCH_ASSOC);
        $refreshToken = $resultTokens[0]["value"];


        // Creating the order
        $resultCreate = $this->createOrder($data, $accessToken);

        // If the token is active, the response is 200, i.e. order created
        if ($resultCreate["httpcode"] === 200) {
            $result = array(
                "code" => 0,
                "payload" => json_decode($resultCreate["response"]),
            );
        }

        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
    }


    public function sendEmail($userId, $userEmail, $quantity, $total, $cart)
    {
        // Mail body
        $body = '<p><strong>new order from reevas.introlink.ru</strong></p>';

        $body = $body . "<br>user id: " . $userId;
        $body = $body . "<br>email: " . $userEmail;
        $body = $body . "<br>quantity: " . $quantity;
        $body = $body . "<br>total: " . $total . " USD";
        $$body = $body . "<br>cart: " . $cart;

        // Mailing list
        $sql = "SELECT * FROM settings WHERE settings.id='1' LIMIT 1;";
        $statement = $this->connection->query($sql);
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        $to = $result[0]["value"];
        $sql = 0;
        $statement = 0;


        $subject = "new request from reevas.introlink.ru";
        $from = "noreply@reevas.introlink.ru";

        $rn = "\r\n";
        $header = 'Content-type: text/html; charset=UTF-8' . $rn;
        $header .= 'Content-Transfer-Encoding: 8bit' . $rn;
        $header .= sprintf('From: %s', $from) . $rn;

        @mb_send_mail($to, $subject, $body, $header);
    }

    public function createOrder($data, $token)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.symoco.com/v1/orders',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
                "description": "' . $data["order"]["description"] . '",
                "autoConfirm": true,
                "successUrl": "' . $data["order"]["successUrl"] . '",
                "declineUrl": "' . $data["order"]["declineUrl"] . '",
                "customer": {
                    "accountId": "' . $data["order"]["customer"]["accountId"] . '",
                    "email": "' . $data["order"]["customer"]["email"] . '"
                },
                "amount": {
                    "value": "' . $data["order"]["amount"]["value"] . '",
                    "currency": "' . $data["order"]["amount"]["currency"] . '"
                }
            }',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                "Authorization: Bearer $token"
            ),
        ));

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        $return = array(
            "httpcode" => $httpcode,
            "response" => $response,
        );

        return $return;
    }
}
