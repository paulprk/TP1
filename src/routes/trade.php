<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../Database/db.php';


$app->post('/trade/buy', function (Request $request, Response $response) {

    $data = $request->getParsedBody();
    $user = $request->getAttribute('user'); // viene del middleware

  
    if (empty($data['asset_id']) || empty($data['quantity'])) {
        $response->getBody()->write(json_encode([
            "error" => "asset_id y quantity son obligatorios"
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if (!is_numeric($data['quantity']) || $data['quantity'] <= 0) {
        $response->getBody()->write(json_encode([
            "error" => "quantity inválida"
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    try {
        $db = getDB();

        // 🔎 Verificar asset
        $stmt = $db->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([$data['asset_id']]);
        $asset = $stmt->fetch();

        if (!$asset) {
            $response->getBody()->write(json_encode([
                "error" => "El asset no existe"
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $precio = (float)$asset['current_price'];
        $cantidad = (float)$data['quantity'];
        $total = $precio * $cantidad;

        if ($user['balance'] < $total) {
            $response->getBody()->write(json_encode([
                "error" => "Saldo insuficiente"
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        //  Restar saldo
        $stmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$total, $user['id']]);

        //  Portfolio
        $stmt = $db->prepare("SELECT * FROM portfolio WHERE user_id = ? AND asset_id = ?");
        $stmt->execute([$user['id'], $data['asset_id']]);
        $portfolio = $stmt->fetch();

        if ($portfolio) {
            $stmt = $db->prepare("UPDATE portfolio SET quantity = quantity + ? WHERE id = ?");
            $stmt->execute([$cantidad, $portfolio['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO portfolio (user_id, asset_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $data['asset_id'], $cantidad]);
        }

        // Registrar transacción
        $stmt = $db->prepare("
            INSERT INTO transactions (user_id, asset_id, transaction_type, quantity, price_per_unit, transaction_date)
            VALUES (?, ?, 'buy', ?, ?, NOW())
        ");
        $stmt->execute([$user['id'], $data['asset_id'], $cantidad, $precio]);

        $response->getBody()->write(json_encode([
            "message" => "Compra realizada con éxito"
        ]));

        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');

    } catch (PDOException $e) {

        $response->getBody()->write(json_encode([
            "error" => "Error en el servidor"
        ]));

        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

})->add($authMiddleware);



$app->post('/trade/sell', function (Request $request, Response $response) {

    $data = $request->getParsedBody();
    $user = $request->getAttribute('user'); // 🔥 del middleware

    if (empty($data['asset_id']) || empty($data['quantity'])) {
        $response->getBody()->write(json_encode([
            "error" => "asset_id y quantity son obligatorios"
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if (!is_numeric($data['quantity']) || $data['quantity'] <= 0) {
        $response->getBody()->write(json_encode([
            "error" => "quantity inválida"
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    try {
        $db = getDB();

        // verificar portfolio
        $stmt = $db->prepare("SELECT * FROM portfolio WHERE user_id = ? AND asset_id = ?");
        $stmt->execute([$user['id'], $data['asset_id']]);
        $portfolio = $stmt->fetch();

        if (!$portfolio || $portfolio['quantity'] < $data['quantity']) {
            $response->getBody()->write(json_encode([
                "error" => "No tenés suficiente cantidad"
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        //  asset
        $stmt = $db->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([$data['asset_id']]);
        $asset = $stmt->fetch();

        $precio = (float)$asset['current_price'];
        $cantidad = (float)$data['quantity'];
        $total = $precio * $cantidad;

        // sumar saldo
        $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$total, $user['id']]);

        // restar portfolio
        $stmt = $db->prepare("UPDATE portfolio SET quantity = quantity - ? WHERE id = ?");
        $stmt->execute([$cantidad, $portfolio['id']]);

        // registrar transacción
        $stmt = $db->prepare("
            INSERT INTO transactions (user_id, asset_id, transaction_type, quantity, price_per_unit, transaction_date)
            VALUES (?, ?, 'sell', ?, ?, NOW())
        ");
        $stmt->execute([$user['id'], $data['asset_id'], $cantidad, $precio]);

        $response->getBody()->write(json_encode([
            "message" => "Venta realizada con éxito"
        ]));

        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');

    } catch (PDOException $e) {

        $response->getBody()->write(json_encode([
            "error" => "Error en el servidor"
        ]));

        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

})->add($authMiddleware);