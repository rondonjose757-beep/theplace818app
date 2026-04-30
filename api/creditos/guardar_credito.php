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

$body        = json_decode(file_get_contents('php://input'), true) ?? [];
$clienteId   = (int)($body['cliente_id']  ?? 0);
$cuadreId    = (int)($body['cuadre_id']   ?? 0);
$descripcion = trim((string)($body['descripcion'] ?? ''));
$montoUsd    = (float)($body['monto_usd'] ?? 0);
$montoBs     = (float)($body['monto_bs']  ?? 0);

if ($clienteId <= 0 || $cuadreId <= 0 || $descripcion === '' || $montoUsd <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos. cliente_id, cuadre_id, descripcion y monto_usd son obligatorios']);
    exit;
}

$hoy = date('Y-m-d');
$db  = getDB();

try {
    // Verificar cuadre abierto de hoy
    $stC = $db->prepare('SELECT id, cerrado FROM cuadre_caja WHERE id = ? AND fecha = ? LIMIT 1');
    $stC->execute([$cuadreId, $hoy]);
    $cuadre = $stC->fetch();

    if (!$cuadre || (bool)$cuadre['cerrado']) {
        http_response_code(403);
        echo json_encode(['error' => 'Cuadre no válido o caja cerrada']);
        exit;
    }

    // Verificar que el cliente existe y está activo
    $stCl = $db->prepare('SELECT id FROM clientes_credito WHERE id = ? AND activo = 1 LIMIT 1');
    $stCl->execute([$clienteId]);
    if (!$stCl->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Cliente no encontrado o inactivo']);
        exit;
    }

    $db->prepare(
        'INSERT INTO creditos (cliente_id, cuadre_id, descripcion, monto_usd, monto_bs)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$clienteId, $cuadreId, $descripcion, $montoUsd, $montoBs]);

    $newId = (int)$db->lastInsertId();

    echo json_encode(['success' => true, 'id' => $newId]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de servidor']);
}
