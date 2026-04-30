<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$id      = (int)($body['id']      ?? 0);
$nombre  = trim((string)($body['nombre']  ?? ''));
$telefono = trim((string)($body['telefono'] ?? ''));
$cedula  = trim((string)($body['cedula']  ?? ''));

if ($nombre === '') {
    http_response_code(400);
    echo json_encode(['error' => 'El nombre es obligatorio']);
    exit;
}

try {
    $db = getDB();

    if ($id > 0) {
        // Actualizar cliente existente
        $db->prepare(
            'UPDATE clientes_credito SET nombre = ?, telefono = ?, cedula = ? WHERE id = ?'
        )->execute([$nombre, $telefono ?: null, $cedula ?: null, $id]);

        echo json_encode(['success' => true, 'id' => $id]);
    } else {
        // Crear nuevo cliente
        $db->prepare(
            'INSERT INTO clientes_credito (nombre, telefono, cedula) VALUES (?, ?, ?)'
        )->execute([$nombre, $telefono ?: null, $cedula ?: null]);

        echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId()]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de servidor']);
}
