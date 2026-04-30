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
$responsable = trim((string)($body['responsable'] ?? ''));
$descripcion = trim((string)($body['descripcion'] ?? ''));
$cantidad    = max(1, (int)($body['cantidad']  ?? 1));
$precioUsd   = (float)($body['precio_usd'] ?? 0);
$precioBs    = (float)($body['precio_bs']  ?? 0);

if ($cuadreId <= 0 || $responsable === '' || $descripcion === '' || $precioUsd < 0) {
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
    'INSERT INTO consumo_familiar (cuadre_id, responsable, descripcion, cantidad, precio_usd, precio_bs)
     VALUES (?, ?, ?, ?, ?, ?)'
)->execute([$cuadreId, $responsable, $descripcion, $cantidad, $precioUsd, $precioBs]);

echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId()]);
