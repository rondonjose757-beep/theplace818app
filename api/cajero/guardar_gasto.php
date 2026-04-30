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

$body        = json_decode(file_get_contents('php://input'), true) ?? [];
$cuadreId    = (int)($body['cuadre_id']   ?? 0);
$categoria   = trim((string)($body['categoria']   ?? ''));
$descripcion = trim((string)($body['descripcion'] ?? ''));
$montoBs     = (float)($body['monto_bs']  ?? 0);
$montoUsd    = (float)($body['monto_usd'] ?? 0);

$categoriasValidas = ['materia_prima', 'operativos', 'nomina', 'otros'];
if ($cuadreId <= 0 || !in_array($categoria, $categoriasValidas, true) || $descripcion === '' || $montoBs < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

$hoy = date('Y-m-d');
$db  = getDB();

$st = $db->prepare('SELECT cerrado FROM cuadre_caja WHERE id = ? AND fecha = ? LIMIT 1');
$st->execute([$cuadreId, $hoy]);
$cuadre = $st->fetch();

if (!$cuadre || (bool)$cuadre['cerrado']) {
    http_response_code(403);
    echo json_encode(['error' => 'Cuadre no válido o caja cerrada']);
    exit;
}

$db->prepare(
    'INSERT INTO gastos (cuadre_id, categoria, descripcion, monto_bs, monto_usd) VALUES (?, ?, ?, ?, ?)'
)->execute([$cuadreId, $categoria, $descripcion, $montoBs, $montoUsd]);

echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId()]);
