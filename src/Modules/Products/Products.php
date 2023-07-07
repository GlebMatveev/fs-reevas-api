<?php

namespace App\Modules\Products;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

class Products
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function getProducts(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $request->getParsedBody();

        // sorting
        $sortDirection = empty($data["sort_direction"]) ? "desc" : $data["sort_direction"];         // STRING - asc, desc
        $sortBy = empty($data["sort_by"]) ? "price" : $data["sort_by"];                             // STRING - price

        $skip = empty($data["skip"]) ? 0 : $data["skip"];

        $take = empty($data["take"]) ? "24" : $data["take"];                                        // INTEGER - 24, 48

        // filtration
        $categories = empty($data["categories"]) ? [] : $data["categories"];                        // ARRAY - [], [1, 2, 3, 4]

        $priceMin = empty($data["price_min"]) ? 0 : $data["price_min"];                             // INTEGER
        $priceMax = empty($data["price_max"]) ? "" : $data["price_max"];                            // INTEGER

        // $ratingMin = empty($data["rating_min"]) ? 0 : $data["rating_min"];                          // INTEGER

        $sqlFirst = "SELECT * ";
        $sqlSecond = "SELECT COUNT(*) ";

        $sqlBody = "FROM `products` WHERE";

        foreach ($categories as $key => $value) {
            if ($key === 0) {
                $sqlBody .= " products.category_id = $value";
            } else if ($key > 0) {
                $sqlBody .= " OR products.category_id = $value";
            };
        }
        if (!empty($categories)) $sqlBody .= " AND";

        // $sqlBody .= " products.rating >= $ratingMin";
        // $sqlBody .= " AND products.price >= $priceMin";

        $sqlBody .= " products.price >= $priceMin";
        if (!empty($data["price_max"])) $sqlBody .= " AND products.price <= $priceMax";

        $sqlBody .= " ORDER BY $sortBy $sortDirection, id $sortDirection";

        $sqlFirst .= $sqlBody . " LIMIT $take OFFSET $skip; ";
        $sqlSecond .= $sqlBody . ";";


        $statement = $this->connection->query($sqlFirst);
        $result["payload"] = $statement->fetchAll(PDO::FETCH_ASSOC);                              // items

        $statement = $this->connection->query($sqlSecond);
        $result["meta"]["count"] = $statement->fetchAll(PDO::FETCH_COLUMN)[0];                  // items count
        $result["meta"]["pages"] = ceil((int)$result["meta"]["count"] / (int)$take);            // pages count
        $result["meta"]["page"] = ((int)$skip / (int)$take) + 1;                                // current page number


        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
    }

    public function getProductsByUserId(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $request->getAttribute('id');

        $sql = "SELECT * FROM `products` WHERE user_id = $id;";

        $statement = $this->connection->query($sql);
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
    }

    public function getProductsById(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $request->getAttribute('id');

        $sql = "SELECT * FROM `products` WHERE id = $id;";

        $statement = $this->connection->query($sql);
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
    }

    public function postProduct(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $request->getParsedBody();

        $ini_array = parse_ini_file("../config/settings.ini");

        $sql = "SELECT auto_increment FROM information_schema.tables WHERE table_schema = '$ini_array[database]' AND table_name = 'products';";
        $statement = $this->connection->query($sql);
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        $id = (int)$result[0]["auto_increment"];
        $sql = 0;
        $statement = 0;
        $result = 0;

        $imgFolder = str_replace(' ', '_', $data["name"]) . "_" . $id;

        $serverPath = $_SERVER['DOCUMENT_ROOT'];
        if (!is_dir($serverPath . '/img/products/' .  $imgFolder)) {
            mkdir($serverPath . '/img/products/' .  $imgFolder, 0777, true);
        }

        $newImage = "";

        foreach ($data["images"] as $key => $value) {

            $str = substr($data["images"][$key], 0, 4);
            if ($str == "data") {
                $arrImg = explode(',', $data["images"][$key]);
                $base64_decode  = base64_decode($arrImg[1]);

                $extArr = explode(';', $data["images"][$key]);
                $extArr = explode('/', $extArr[0]);
                $extension = $extArr[1];

                $imagePathStart = '/img/products/';
                $imagePathEnd = $imgFolder . '/' . uniqid() . '.' . $extension;

                $newImage = $imagePathEnd;

                $imagePath = $imagePathStart . $imagePathEnd;
                $dirSave = $serverPath . $imagePath;

                file_put_contents($dirSave, $base64_decode);
            }
        }


        $sql = "INSERT INTO
        products(
            products.name,
            products.price,
            products.developer,
            products.image,
            products.keyword,
            products.category_id,
            products.user_id
        ) VALUES (
            '$data[name]',
            '$data[price]',
            '$data[developer]',
            '$newImage',
            '$data[keyword]',
            '$data[category_id]',
            '$data[user_id]'
        );";

        $statement = $this->connection->prepare($sql);

        $result = $statement->execute();

        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
    }

    public function searchProducts(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $request->getParsedBody();

        $sql = "SELECT * FROM `products` WHERE `name` LIKE '%$data[word]%' OR `developer` LIKE '%$data[word]%' OR `keyword` LIKE '%$data[word]%' LIMIT 10;";

        $statement = $this->connection->query($sql);
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);


        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
    }
}
