<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    $db   = getDB();
    $stmt = $db->query(
        'SELECT id, nombre, email, rol, activo, created_at
         FROM usuarios
         ORDER BY activo DESC, rol ASC, nombre ASC'
    );
    $usuarios = $stmt->fetchAll();

    foreach ($usuarios as &$u) {
        $u['activo'] = (bool)$u['activo'];
    }
    unset($u);

    echo json_encode(['usuarios' => $usuarios]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de servidor']);
}
