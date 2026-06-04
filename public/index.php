<?php
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Importar el autoload de Composer
require __DIR__ . '/../vendor/autoload.php';

// --- Conexion base de datos ---
require_once __DIR__ . '/../src/database/db.php';
require_once __DIR__ . '/../src/middleware/middleware.php';

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

$app->setBasePath('/mi-proyecto/public');

$app->addBodyParsingMiddleware();

$app->get('/', function (Request $request, Response $response) {
	$response->getBody()->write(json_encode([
		'success' => true,
		'message' => 'Ruta raiz encontrada',
		'method' => $request->getMethod(),
		'path' => (string) $request->getUri()->getPath()
	]));

	return $response->withHeader('Content-Type', 'application/json');
});


// Registrar rutas
require_once __DIR__ . '/../src/routes/auth.php';
require_once __DIR__ . '/../src/routes/users.php';
require_once __DIR__ . '/../src/routes/assets.php';
require_once __DIR__ . '/../src/routes/portfolio.php';
require_once __DIR__ . '/../src/routes/trade.php';
 
$app->run();