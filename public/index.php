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

//importar auth.php para registrar las rutas relacionadas con LOGIN
require_once __DIR__ . '/../src/routes/auth.php';

//importar users.php para registrar las rutas relacionadas con USERS
require_once __DIR__ . '/../src/routes/users.php';

//importar assets.php para registrar las rutas relacionadas con ASSETS
require_once __DIR__ . '/../src/routes/assets.php';

 
$app->run();