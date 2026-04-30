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
$creditoId = (int)($body['credito_id'] ?? 0);
$cuadreId  = (int)($body['cuadre_id']  ?? 0);
$montoUsd  = (float)($body['monto_usd'] ?? 0);
$montoBs   = (float)($body['monto_bs']  ?? 0);

if ($creditoId <= 0 || $cuadreId <= 0 || $montoUsd <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'credito_id, cuadre_id y monto_usd son obligatorios']);
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

    // Verificar que el crédito existe y no está pagado
    $stCr = $db->prepare('SELECT id, monto_usd, monto_bs FROM creditos WHERE id = ? AND pagado = 0 LIMIT 1');
    $stCr->execute([$creditoId]);
    $credito = $stCr->fetch();

    if (!$credito) {
        http_response_code(404);
        echo json_encode(['error' => 'Crédito no encontrado o ya pagado']);
        exit;
    }

    // Calcular cuánto ya se ha abonado
    $stAb = $db->prepare('SELECT COALESCE(SUM(monto_usd), 0) AS total FROM abonos_credito WHERE credito_id = ?');
    $stAb->execute([$creditoId]);
    $totalAbonado = (float)$stAb->fetchColumn();

    $pendiente = (float)$credito['monto_usd'] - $totalAbonado;
    if ($montoUsd > round($pendiente, 2) + 0.01) {
        http_response_code(400);
        echo json_encode(['error' => "El abono ($montoUsd USD) supera el saldo pendiente (" . round($pendiente, 2) . " USD)"]);
        exit;
    }

    $db->beginTransaction();

    $db->prepare(
        'INSERT INTO abonos_credito (credito_id, cuadre_id, monto_usd, monto_bs) VALUES (?, ?, ?, ?)'
    )->execute([$creditoId, $cuadreId, $montoUsd, $montoBs]);

    $abId = (int)$db->lastInsertId();

    // Si el abono cubre el pendiente, marcar el crédito como pagado
    $totalAhora = $totalAbonado + $montoUsd;
    $pagado = false;
    if (round($totalAhora, 2) >= round((float)$credito['monto_usd'], 2)) {
        $db->prepare('UPDATE creditos SET pagado = 1 WHERE id = ?')->execute([$creditoId]);
        $pagado = true;
    }

    $db->commit();

    echo json_encode(['success' => true, 'id' => $abId, 'pagado' => $pagado]);
} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Error de servidor']);
}
