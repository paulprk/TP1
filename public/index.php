<?php
use Slim\Factory\AppFactory;

// Importar el autoload de Composer
require __DIR__ . '/../vendor/autoload.php';

// --- Conexion base de datos ---
require_once __DIR__ . '/../src/database/db.php';

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

$app->setBasePath('/mi-proyecto/public');
$app->addBodyParsingMiddleware();


// Middleware de autenticación
$authMiddleware = function ($request, $handler) {
    $headers = $request->getHeaders();
    $authHeader = $request->getHeaderLine('Authorization');

    if (empty($authHeader)) {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode(["error" => "Token requerido"]));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $token = str_replace('Bearer ', '', $authHeader);

    try {
        $db = getDB();
        $sql = "SELECT * FROM users WHERE token = :token";
        $stmt = $db->prepare($sql);
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();

        if (!$user) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(["error" => "Token inválido"]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        if ($user['token_expired_at'] < date('Y-m-d H:i:s')) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(["error" => "Token expirado"]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $nuevaExpiracion = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $sqlUpdate = "UPDATE users SET token_expired_at = :expira WHERE id = :id";
        $stmtUpdate = $db->prepare($sqlUpdate);
        $stmtUpdate->execute([':expira' => $nuevaExpiracion, ':id' => $user['id']]);

        $request = $request->withAttribute('user_id', $user['id']);

        return $handler->handle($request);

    } catch (PDOException $e) {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode(["error" => "Error en el servidor"]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
};

$app->get('/test', function ($req, $res) {
    $res->getBody()->write("OK");
    return $res;
});


// Registrar rutas
require_once __DIR__ . '/../src/routes/auth.php';
require_once __DIR__ . '/../src/routes/users.php';
require_once __DIR__ . '/../src/routes/assets.php';
require_once __DIR__ . '/../src/routes/portfolio.php';
require_once __DIR__ . '/../src/routes/trade.php';
 
$app->run();