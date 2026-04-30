<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$gastoId = (int)($body['gasto_id'] ?? 0);

if ($gastoId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$hoy = date('Y-m-d');
$db  = getDB();

// Verificar que el gasto pertenece al cuadre de hoy y que no esté cerrado
$st = $db->prepare(
    'SELECT g.id FROM gastos g
     JOIN cuadre_caja c ON c.id = g.cuadre_id
     WHERE g.id = ? AND c.fecha = ? AND c.cerrado = 0
     LIMIT 1'
);
$st->execute([$gastoId, $hoy]);

if (!$st->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['error' => 'No se puede eliminar este gasto']);
    exit;
}

$db->prepare('DELETE FROM gastos WHERE id = ?')->execute([$gastoId]);
echo json_encode(['success' => true]);
