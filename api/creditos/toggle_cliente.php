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

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$id   = (int)($body['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

try {
    $db   = getDB();
    $stmt = $db->prepare('SELECT activo FROM clientes_credito WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        http_response_code(404);
        echo json_encode(['error' => 'Cliente no encontrado']);
        exit;
    }

    $nuevoEstado = $cliente['activo'] ? 0 : 1;
    $db->prepare('UPDATE clientes_credito SET activo = ? WHERE id = ?')
       ->execute([$nuevoEstado, $id]);

    echo json_encode(['success' => true, 'activo' => (bool)$nuevoEstado]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de servidor']);
}
