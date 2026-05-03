<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->post('/login', function (Request $request, Response $response) {

    // 1. Obtener los datos enviados por el usuario (JSON → array)
    $data = $request->getParsedBody();

    // 2. Validar que existan email y password
    if (empty($data['email']) || empty($data['password'])) {

        // 2.1 Responder error si faltan datos
        $response->getBody()->write(json_encode([
            "error" => "Email y password son obligatorios"
        ]));

        // 2.2 Código 400 = Bad Request
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    try {
        // 3. Obtener conexión a la base de datos
        $db = getDB();

        // 4. Preparar consulta para buscar usuario por email
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $db->prepare($sql);

        // 5. Ejecutar consulta con el email recibido
        $stmt->execute([
            ':email' => $data['email']
        ]);

        // 6. Obtener el usuario encontrado (si existe)
        $user = $stmt->fetch();

        // 7. Verificar si el usuario NO existe
        if (!$user) {

            // 7.1 Responder error
            $response->getBody()->write(json_encode([
                "error" => "Credenciales inválidas"
            ]));

            // 7.2 Código 401 = Unauthorized
            return $response->withStatus(401)
                            ->withHeader('Content-Type', 'application/json');
        }

        // 8. Verificar la contraseña (comparar texto con hash)
        if (!password_verify($data['password'], $user['password'])) {

            // 8.1 Si no coincide → error
            $response->getBody()->write(json_encode([
                "error" => "Credenciales inválidas"
            ]));

            return $response->withStatus(401)
                            ->withHeader('Content-Type', 'application/json');
        }

        // 9. Generar token aleatorio
        $token = bin2hex(random_bytes(16)); // string seguro

        // 10. Generar fecha de expiración (+5 minutos)
        $expira = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        // 11. Guardar token y expiración en la base de datos
        $sqlUpdate = "UPDATE users SET token = :token, token_expired_at = :expira WHERE id = :id";
        $stmt = $db->prepare($sqlUpdate);

        $stmt->execute([
            ':token' => $token,
            ':expira' => $expira,
            ':id' => $user['id']
        ]);

        // 12. Responder con éxito
        $response->getBody()->write(json_encode([
            "mensaje" => "Login exitoso",
            "token" => $token,
            "expira" => $expira
        ]));

        // 13. Código 200 OK
        return $response->withStatus(200)
                        ->withHeader('Content-Type', 'application/json');

    } catch (PDOException $e) {

        // 14. Error del servidor o base de datos
        $response->getBody()->write(json_encode([
            "error" => "Error en el servidor"
        ]));

        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }
});

$app->post('/logout', function (Request $request, Response $response) {

    // 1. Obtener los datos enviados por el usuario (JSON → array)
    $data = $request->getParsedBody();

    // 2. Validar que venga el token (obligatorio)
    if (empty($data['token'])) {

        // 2.1 Responder error si no envía token
        $response->getBody()->write(json_encode([
            "error" => "Token es obligatorio"
        ]));

        // 2.2 Código 400 = Bad Request
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    try {
        // 3. Obtener conexión a la base de datos
        $db = getDB();

        // 4. Buscar usuario por token
        $sql = "SELECT * FROM users WHERE token = :token";
        $stmt = $db->prepare($sql);

        // 5. Ejecutar consulta con el token recibido
        $stmt->execute([
            ':token' => $data['token']
        ]);

        // 6. Obtener el usuario encontrado
        $user = $stmt->fetch();

        // 7. Verificar si el token NO existe
        if (!$user) {

            // 7.1 Responder error
            $response->getBody()->write(json_encode([
                "error" => "Token inválido"
            ]));

            // 7.2 Código 401 = Unauthorized
            return $response->withStatus(401)
                            ->withHeader('Content-Type', 'application/json');
        }

        // 8. Anular el token (cerrar sesión)
        $sqlUpdate = "UPDATE users SET token = NULL, token_expired_at = NULL WHERE id = :id";
        $stmt = $db->prepare($sqlUpdate);

        // 9. Ejecutar actualización
        $stmt->execute([
            ':id' => $user['id']
        ]);

        // 10. Responder éxito
        $response->getBody()->write(json_encode([
            "mensaje" => "Logout exitoso"
        ]));

        // 11. Código 200 OK
        return $response->withStatus(200)
                        ->withHeader('Content-Type', 'application/json');

    } catch (PDOException $e) {

        // 12. Error del servidor
        $response->getBody()->write(json_encode([
            "error" => "Error en el servidor"
        ]));

        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }
});