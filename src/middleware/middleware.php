<?php

// Middleware compartidos de autenticación/autorización.

$authMiddleware = function ($request, $handler) {
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

$adminMiddleware = function ($request, $handler) {
    $db = getDB();
    $userId = $request->getAttribute('user_id');

    $sql = "SELECT is_admin FROM users WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || (int)$user['is_admin'] !== 1) {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            "error" => "Solo administradores"
        ]));
        return $response->withStatus(401)
                        ->withHeader('Content-Type', 'application/json');
    }

    return $handler->handle($request);
};

$userOrAdminMiddleware = function ($userIdParam = 'user_id') {
    return function ($request, $handler) use ($userIdParam) {
        $db = getDB();
        $currentUserId = (int)$request->getAttribute('user_id');

        $routeArgs = $request->getAttribute('routeInfo')[2] ?? [];
        $resourceUserId = isset($routeArgs[$userIdParam]) ? (int)$routeArgs[$userIdParam] : null;

        if (!$resourceUserId) {
            $data = $request->getParsedBody();
            $resourceUserId = isset($data[$userIdParam]) ? (int)$data[$userIdParam] : null;
        }

        $sql = "SELECT is_admin FROM users WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $currentUserId]);
        $user = $stmt->fetch();
        $isAdmin = $user && (int)$user['is_admin'] === 1;

        if ($currentUserId === $resourceUserId || $isAdmin) {
            return $handler->handle($request);
        }

        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            "error" => "No tienes permiso para acceder a este recurso"
        ]));
        return $response->withStatus(401)
                        ->withHeader('Content-Type', 'application/json');
    };
};
