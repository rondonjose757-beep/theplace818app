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

$body          = json_decode(file_get_contents('php://input'), true) ?? [];
$cuadreId      = (int)($body['cuadre_id'] ?? 0);
$observaciones = trim((string)($body['observaciones'] ?? ''));

if ($cuadreId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

$hoy = date('Y-m-d');
$db  = getDB();

$st = $db->prepare('SELECT id, cerrado FROM cuadre_caja WHERE id = ? AND fecha = ? LIMIT 1');
$st->execute([$cuadreId, $hoy]);
$cuadre = $st->fetch();

if (!$cuadre) {
    http_response_code(404);
    echo json_encode(['error' => 'Cuadre no encontrado']);
    exit;
}
if ((bool)$cuadre['cerrado']) {
    http_response_code(409);
    echo json_encode(['error' => 'La caja ya estaba cerrada']);
    exit;
}

$db->prepare('UPDATE cuadre_caja SET cerrado = 1, observaciones = ? WHERE id = ?')
   ->execute([$observaciones ?: null, $cuadreId]);

echo json_encode(['success' => true]);
