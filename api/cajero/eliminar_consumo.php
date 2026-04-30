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

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$consumoId = (int)($body['consumo_id'] ?? 0);

if ($consumoId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$hoy = date('Y-m-d');
$db  = getDB();

$st = $db->prepare(
    'SELECT cf.id FROM consumo_familiar cf
     JOIN cuadre_caja c ON c.id = cf.cuadre_id
     WHERE cf.id = ? AND c.fecha = ? AND c.cerrado = 0
     LIMIT 1'
);
$st->execute([$consumoId, $hoy]);

if (!$st->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['error' => 'No se puede eliminar este consumo']);
    exit;
}

$db->prepare('DELETE FROM consumo_familiar WHERE id = ?')->execute([$consumoId]);
echo json_encode(['success' => true]);
