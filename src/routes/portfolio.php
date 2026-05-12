<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/portfolio', function (Request $request, Response $response) {
    try {
        $db = getDB();

        $userId = $request->getAttribute('user_id');
        if(!$userId) {
            $response->getBody()->write(json_encode(["error" => "Usuario no autenticado"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $sql = "SELECT p.quantity, a.name AS asset_name, a.current_price 
                FROM portfolio p 
                JOIN assets a ON p.asset_id = a.id 
                WHERE p.user_id = :user_id AND p.quantity > 0";

        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $tenencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $listaActivos = [];
        $valorTotalPortfolio = 0;

        foreach ($tenencias as $item) {
            $cantidad = (float) $item['quantity'];
            $precioMercado = (float) $item['current_price'];
            $valorActual = $cantidad * $precioMercado;

            $valorTotalPortfolio += $valorActual;

            $listaActivos[] = [
                "activo" => $item['asset_name'],
                "cantidad" => $cantidad,
                "precio_actual" => $precioMercado,
                "valor_total_tenencia" => round($valorActual, 2)
            ];

        }

        $response->getBody()->write(json_encode([
            "usuario_id" => $userId,
            "valor_total_portfolio" => round($valorTotalPortfolio, 2),
            "activos" => $listaActivos
        ]));

        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode(["error" => "Error al obtener el portfolio ", 
            "detalle" => $e->getMessage()]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
})->add($authMiddleware);

$app->delete('/portfolio/{asset_id}', function (Request $request, Response $response, array $args) {

    try {
        $db = getDB();
         
        $userId = $request->getAttribute('user_id');
        $assetId = $args['asset_id'];

        $sqlCheck = "SELECT quantity FROM portfolio WHERE user_id = :user_id AND asset_id = :asset_id";
        $stmtCheck = $db->prepare($sqlCheck);
        $stmtCheck->execute([
            ':user_id' => $userId,
            ':asset_id' => $assetId
        ]);

        $portfolioItem = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if(!$portfolioItem) {
            $response->getBody()->write(json_encode(["error" => "El activo no existe en el portfolio"]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        if((float) $portfolioItem['quantity'] > 0) {
            $response->getBody()->write(json_encode(["error" => "No puedes quitar un activo de tu portfolio si aún tienes unidades. Debes venderlas primero."]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $sqlDelete = "DELETE FROM portfolio WHERE user_id = :user_id AND asset_id = :asset_id";
        $stmtDelete = $db->prepare($sqlDelete);
        $stmtDelete->execute([
            ':user_id' => $userId,
            ':asset_id' => $assetId
        ]);

        $response->getBody()->write(json_encode(["message" => "Activo eliminado del portfolio"]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');

    
    } catch (Exception $e) {
        $response->getBody()->write(json_encode(["error" => "Error al eliminar el activo del portfolio ",
            "detalle" => $e->getMessage()
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

})->add($authMiddleware);


$app->get('/transactions', function (Request $request, Response $response) {

    try {
        $db = getDB();
        $user_id = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $type = $params['type'] ?? null;        // buy o sell
        $asset_id = $params['asset_id'] ?? null; // id del asset

        if ($type && !in_array($type, ['buy', 'sell'])) {
            $response->getBody()->write(json_encode(["error" => "El parámetro 'type' debe ser 'buy' o 'sell'"]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        // 4. Armar la query base
        $sql = "SELECT * FROM transactions WHERE user_id = :user_id";
        if ($type) {
            $sql .= " AND transaction_type = :type";
        }

        if ($asset_id) {
            $sql .= " AND asset_id = :asset_id";
        }

        $sql .= " ORDER BY transaction_date DESC";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);

        if ($type) {
            $stmt->bindParam(':type', $type);
        }

        if ($asset_id) {
            $stmt->bindParam(':asset_id', $asset_id);
        }

        $stmt->execute();
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode($transactions));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        
    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            "error" => "Error al obtener las transacciones",
            "detalle" => $e->getMessage()
        ]));

        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    
})->add($authMiddleware);