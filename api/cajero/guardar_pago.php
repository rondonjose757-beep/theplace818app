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

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$cuadreId  = (int)($body['cuadre_id'] ?? 0);
$metodo    = trim((string)($body['metodo'] ?? ''));
$montoBs   = (float)($body['monto_bs']  ?? 0);
$montoUsd  = (float)($body['monto_usd'] ?? 0);

$metodosValidos = ['tarjeta', 'pago_movil', 'dolares_efectivo', 'zelle', 'credito', 'efectivo_bs'];
if ($cuadreId <= 0 || !in_array($metodo, $metodosValidos, true) || $montoBs < 0 || $montoUsd < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

$hoy = date('Y-m-d');
$db  = getDB();

// Verificar cuadre: debe ser de hoy y no estar cerrado
$st = $db->prepare('SELECT id, cerrado FROM cuadre_caja WHERE id = ? AND fecha = ? LIMIT 1');
$st->execute([$cuadreId, $hoy]);
$cuadre = $st->fetch();

if (!$cuadre) {
    http_response_code(404);
    echo json_encode(['error' => 'Cuadre no encontrado']);
    exit;
}
if ((bool)$cuadre['cerrado']) {
    http_response_code(403);
    echo json_encode(['error' => 'La caja ya fue cerrada']);
    exit;
}

// Upsert pago
$st = $db->prepare('SELECT id FROM pagos_dia WHERE cuadre_id = ? AND metodo = ? LIMIT 1');
$st->execute([$cuadreId, $metodo]);
$pagoExistente = $st->fetchColumn();

if ($pagoExistente) {
    $db->prepare('UPDATE pagos_dia SET monto_bs = ?, monto_usd = ? WHERE id = ?')
       ->execute([$montoBs, $montoUsd, $pagoExistente]);
} else {
    $db->prepare('INSERT INTO pagos_dia (cuadre_id, metodo, monto_bs, monto_usd) VALUES (?, ?, ?, ?)')
       ->execute([$cuadreId, $metodo, $montoBs, $montoUsd]);
}

echo json_encode(['success' => true]);
