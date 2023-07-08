<?php

namespace App\Modules\Requests;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

class Requests
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function postRequest(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $request->getParsedBody();

        // Mail body
        $body = '<p><strong>new request from reevas.org</strong></p>';

        $body = $body . "<br>email: " . $data["email"];
        $body = $body . "<br>subject: " . $data["subject"];
        $body = $body . "<br>description: " . $data["description"];


        // Mailing list
        $sql = "SELECT * FROM settings WHERE settings.id='1' LIMIT 1;";
        $statement = $this->connection->query($sql);
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        $to = $result[0]["value"];
        $sql = 0;
        $statement = 0;


        $subject = "new request from reevas.org";
        $from = "noreply@reevas.org";

        $rn = "\r\n";
        $header = 'Content-type: text/html; charset=UTF-8' . $rn;
        $header .= 'Content-Transfer-Encoding: 8bit' . $rn;
        $header .= sprintf('From: %s', $from) . $rn;

        @mb_send_mail($to, $subject, $body, $header);


        $sql = "INSERT INTO
                requests(
                    requests.user_id,
                    requests.email,
                    requests.subject,
                    requests.description
                ) VALUES (
                    '$data[user_id]',
                    '$data[email]',
                    '$data[subject]',
                    '$data[description]'
                );";

        $statement = $this->connection->prepare($sql);

        $result = $statement->execute();


        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
    }
}
