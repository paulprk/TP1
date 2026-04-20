<?php
//falta completar get/users/{user_id} y get /users para que respondan con el valor total del portfolio (balance + valor del portfolio) cuando se implemente el GET /portfolio

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// RUTA: POST /users (Registrar un nuevo usuario)
// -----------------------------------------------------------
$app->post('/users', function (Request $request, Response $response) {
    // 1. Leer los datos que manda el usuario en formato JSON
    $data = $request->getParsedBody();

    // 2. Validar que no manden campos vacíos
    if(empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        $response->getBody()->write(json_encode(["error" => "Faltan datos obligatorios (name, email o password)"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // 3. Validar que el email, password y nombre cumplan el formato
    if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ ]*$/", $data['name'])) {
        $response->getBody()->write(json_encode(["error" => "El nombre solo puede contener letras"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $response->getBody()->write(json_encode(["error" => "El formato del email no es válido"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $pass = $data['password'];
    if(strlen($pass) < 8 || 
       !preg_match('/[A-Z]/', $pass) || 
       !preg_match('/[a-z]/', $pass) || 
       !preg_match('/[0-9]/', $pass) || 
       !preg_match('/[\W]/', $pass)) {
        $response->getBody()->write(json_encode(["error" => "La contraseña no cumple los requisitos de seguridad"]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // 4. Encriptar la contraseña (¡NUNCA se guardan en texto plano!)
    $passwordHasheada = password_hash($data['password'], PASSWORD_DEFAULT);
    
    try {
        $db = getDB();      // Llamamos a tu bóveda para obtener la conexión a la base de datos

        // 5. Preparar la orden SQL
        $sql = "INSERT INTO users (name, email, password, balance) 
                VALUES (:name, :email, :password, DEFAULT(balance) + 1000.00)";
        $stmt = $db->prepare($sql);

        // 6. Ejecutar inyectando los datos de forma segura
        $stmt->execute([
            ':name' => $data['name'],
            ':email' => $data['email'],
            ':password' => $passwordHasheada
        ]);

        $nuevoID = $db->lastInsertId();
        $sqlSelect = "SELECT balance FROM users WHERE id = :id"; 
        $stmtSelect = $db->prepare($sqlSelect);
        $stmtSelect->execute([':id' => $nuevoID]);
        $usuarioCargado = $stmtSelect->fetch(PDO::FETCH_ASSOC);
        
        // 7. Responder con éxito(200 OK)
        $response->getBody()->write(json_encode([
            "success" => true,
            "message" => "Usuario registrado con éxito",
            "data" => [
                "name" => $data['name'],
                "balance" => $usuarioCargado['balance']
            ]
        ]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');

    } catch (PDOException $e) {
        // Si el email ya existe, MySQL nos avisará y capturamos el error aquí(409 Conflict)
        $response->getBody()->write(json_encode(["error" => "El email ya está registrado"]));
        return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
    }
});


// RUTA: GET /users/{user_id} (Ver perfil de un usuario específico)
// -----------------------------------------------------------
$app->get('/users/{user_id}', function (Request $request, Response $response, array $args) {

    try {
        $db = getDB();      //abrir database
        $idBuscado = $args['user_id'];      //id buscado

        $sqlSelect = "SELECT name, email, balance FROM users WHERE id = :id"; //seleccionar de la base name, email, password y balance del id buscado
        $stmtSelect = $db->prepare($sqlSelect);     //preparar la consulta
        $stmtSelect->execute([':id' => $idBuscado]);    //ejecutar la consulta inyectando el id buscado de forma segura
        $idCargado = $stmtSelect->fetch(PDO::FETCH_ASSOC);  //cargar el resultado en un array asociativo
        
        if($idCargado) {
            $response->getBody()->write(json_encode([
                "Ver Perfil" => [
                    "name" => $idCargado['name'],
                    "email" => $idCargado['email'],
                ],
                "Saldos" => [
                    "balance" => $idCargado['balance'],
                    //+ 0 sera reemplazado por valor del portfolio, cuando se implemente PORTAFOLIO e Historial
                    //que sera el GET /portfolio
                    "Valor Portfolio" => $idCargado['balance'] + 0 
                ]
            ]));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        } else {
            $response->getBody()->write(json_encode(["Error" => "Usuario no encontrado"]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode([
            "Error" => "No se proceso la solicitud en la base de datos: "
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
});

// RUTA: PUT /users/{user_id} (Actualizar datos de un usuario específico)
// -----------------------------------------------------------
$app->put('/users/{user_id}', function (Request $request, Response $response, array $args) {

    //datos de url y del body
    $idBuscado = $args['user_id'];      //id buscado
    $data = $request->getParsedBody();   //datos a actualizar

    //validaciónes
    $camposActualizar = [];
    $parametros = [':id' => $idBuscado];

    //revisar si el usuario envio campo "name"
    if(!empty($data['name'])) {
        if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ ]*$/", $data['name'])) {
            $response->getBody()->write(json_encode(["error" => "El nombre solo puede contener letras"]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        $camposActualizar[] = "name = :name";
        $parametros[':name'] = $data['name'];
    }

    //revisar si el usuario envio campo "email"
    if(!empty($data['email'])) {
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $response->getBody()->write(json_encode(["error" => "El formato del email no es válido"]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        $camposActualizar[] = "email = :email";
        $parametros[':email'] = $data['email'];
    }

    if(!empty($data['password'])) {
        $pass = $data['password'];
        if(strlen($pass) < 8 || 
        !preg_match('/[A-Z]/', $pass) || 
        !preg_match('/[a-z]/', $pass) || 
        !preg_match('/[0-9]/', $pass) || 
        !preg_match('/[\W]/', $pass)) {
            $response->getBody()->write(json_encode(["error" => "La contraseña no cumple los requisitos de seguridad"]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        $camposActualizar[] = "password = :password";
        $parametros[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
    }

    //si no se mandaron campos validos para actualizar, responder con error
    if(empty($camposActualizar)) {
        $response->getBody()->write(json_encode([
            "Error" => "No se enviaron datos validos para actualizar. Debe ingresar 'name' o 'email' para actualizar."
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    //actualizar solo los campos que se mandaron
    try {
        $db = getDB();      //abrir database

        $setSQL = implode(', ', $camposActualizar); //crear la parte SET de la consulta SQL
        $sqlUpdate = "UPDATE users SET $setSQL WHERE id = :id"; //consulta SQL para actualizar solo los campos que se mandaron
        $stmtUpdate = $db->prepare($sqlUpdate);
        $stmtUpdate->execute($parametros); //ejecutar la consulta inyectando los parametros de forma segura

        if($stmtUpdate->rowCount() > 0) {
            $detalles = [];

            if(isset($data['name'])) {
                $detalles['name'] = $data['name'];
            }

            if(isset($data['email'])) {
                $detalles['email'] = $data['email'];
            }

            if(isset($data['password'])) {
                $detalles['password'] = "Contraseña actualizada";
            }

            $response->getBody()->write(json_encode([
                "Mensaje" => "Usuario actualizado con éxito",
                "Datos Actualizados" => $detalles
            ]));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        } else {
            $response->getBody()->write(json_encode([
                "Error" => "No se pudo actualizar. El usuario no existe o los datos son idénticos a los actuales."
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

       
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode([
            "Error" => "No se encontró el usuario o no se pudo actualizar pruebe otro id"
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
})->add($authMiddleware); //proteger esta ruta para que solo usuarios autenticados puedan actualizar su perfil

// RUTA: GET /users (Listar inversores para monitoreo. Solo nombre y valor total del portfolio.)
$app->get('/users', function (Request $request, Response $response, array $args) {
    try {
        //abrir base de datos

        $db = getDB();

        //armar y ejecutar consulta SQL para obtener nombre y valor total del portfolio de todos los usuarios

        $sql = "SELECT name, balance FROM users"; //+ valor del portfolio, cuando se implemente PORTAFOLIO e Historial
        $stmt = $db->prepare($sql);
        $stmt->execute();

        //obetener todos los resultados(fetchAll) y responder con un JSON que contenga un array de usuarios con su nombre y valor total del portfolio

        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);


        //armar la respuesta con un array de usuarios, donde cada usuario es un objeto con su nombre y valor total del portfolio (balance + valor del portfolio)

        $response->getBody()->write(json_encode([
            "Usuarios" => array_map(function($usuarios) {
                return [
                    "name" => $usuarios['name'],

                    //+ 0 sera reemplazado por valor del portfolio, cuando se implemente PORTAFOLIO e Historial
                    //que sera el GET /portfolio
                    "Valor Portfolio" => $usuarios['balance'] + 0 
                ];
            }, $usuarios)
        ]));

        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');


    } catch (PDOException $e) {
        $response->getBody()->write(json_encode([
            "Error" => "No se pudo obtener la lista de usuarios para monitoreo"
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
});

