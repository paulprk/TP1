<?php
//terminado

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// catedra
function variarPrecioPorTiempo($precioActual, $timestampUltimaVez, $volatilidadPorSegundo = 0.05) {
    $tiempoPasado = time() - $timestampUltimaVez;

    if ($tiempoPasado <= 0) return $precioActual;

    $direccion = mt_rand(-100, 100) / 100;
    $delta = $direccion * $volatilidadPorSegundo * $tiempoPasado;

    return $precioActual + $delta;
}



// GET /assets
$app->get('/assets', function (Request $request, Response $response) {

    try {
        $db = getDB();
        $params = $request->getQueryParams();

        $sql = "SELECT * FROM assets WHERE 1=1";
        $values = [];

        // 🔎 Validaciones        
        if (isset($params['min_price']) && !is_numeric($params['min_price'])) {
            $response->getBody()->write(json_encode(["error" => "min_price debe ser numérico"]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (isset($params['max_price']) && !is_numeric($params['max_price'])) {
            $response->getBody()->write(json_encode(["error" => "max_price debe ser numérico"]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        //Si enviaron AMBOS, validar que el mínimo no rompa la lógica superando al máximo
        if (isset($params['min_price']) && isset($params['max_price'])) {
            if ((float)$params['min_price'] > (float)$params['max_price']) {
                $response->getBody()->write(json_encode(["error" => "El precio mínimo no puede ser mayor al máximo"]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }

        if (isset($params['type'])) {
            $sql .= " AND name = ?";
            $values[] = $params['type'];
        }

        if (isset($params['min_price'])) {
            $sql .= " AND current_price >= ?";
            $values[] = $params['min_price'];
        }

        if (isset($params['max_price'])) {
            $sql .= " AND current_price <= ?";
            $values[] = $params['max_price'];
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($values);

        $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode($assets));
        return $response->withStatus(200)
                        ->withHeader('Content-Type', 'application/json');

    } catch (PDOException $e) {

        $response->getBody()->write(json_encode([
            "error" => "Error en la base de datos"
        ]));

        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }
});

// PUT /assets 
$app->put('/assets', function (Request $request, Response $response) {

    try {
        $db = getDB();

        $stmt = $db->query("SELECT * FROM assets");
        $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($assets as $asset) {

            $nuevoPrecio = variarPrecioPorTiempo(
                (float)$asset['current_price'],
                strtotime($asset['last_update'])
            );

            $update = $db->prepare("UPDATE assets SET current_price = ? WHERE id = ?");
            $update->execute([$nuevoPrecio, $asset['id']]);
        }

        $response->getBody()->write(json_encode([
            "message" => "Precios actualizados"
        ]));

        return $response->withStatus(200)
                        ->withHeader('Content-Type', 'application/json');

    } catch (PDOException $e) {

        $response->getBody()->write(json_encode([
            "error" => "Error al actualizar precios"
        ]));

        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }
});

// GET /assets/{id}/history/{quantity}
$app->get('/assets/{id}/history/{quantity}', function (Request $request, Response $response, $args) {

    try {
        $db = getDB();

        // Sanitización de parámetros de la URL
        $asset_id = (int)$args['id'];
        $quantityRequested = (int)$args['quantity'];
        
        //Aplicación de regla: Máximo 5 registros según requerimiento
        $limit = ($quantityRequested > 5) ? 5 : $quantityRequested;
        
        if ($limit <= 0) {
            $response->getBody()->write(json_encode(["error" => "La cantidad debe ser mayor a 0"]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        //// Preparación de consulta SQL con marcadores para prevenir SQL Injection
        $sql = "SELECT transaction_type, quantity, price_per_unit, transaction_date 
                FROM transactions 
                WHERE asset_id = :asset_id 
                ORDER BY transaction_date DESC 
                LIMIT :limit";

        $stmt = $db->prepare($sql);

        /** * Usamos bindValue con PARAM_INT porque:
         * - Protege contra Inyección SQL al forzar el tipo de dato.
         * - El motor SQL requiere que el parámetro LIMIT sea un entero puro (no un string).
         */

        $stmt->bindValue(':asset_id', $asset_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); // <-- Seguridad total
        
        $stmt->execute();

        // Muestra del historial sin revelar información sensible (user_id)
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si no hay transacciones para ese activo, devolvemos un mensaje claro o array vacío
        $response->getBody()->write(json_encode($history));

        return $response->withStatus(200)
                        ->withHeader('Content-Type', 'application/json');

    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(["error" => "Error al obtener historial"]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});