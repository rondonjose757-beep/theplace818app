<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$clienteId = (int)($_GET['cliente_id'] ?? 0);
if ($clienteId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'cliente_id requerido']);
    exit;
}

try {
    $db = getDB();

    // Datos del cliente
    $stmtC = $db->prepare('SELECT id, nombre, telefono, cedula, activo FROM clientes_credito WHERE id = ? LIMIT 1');
    $stmtC->execute([$clienteId]);
    $cliente = $stmtC->fetch();

    if (!$cliente) {
        http_response_code(404);
        echo json_encode(['error' => 'Cliente no encontrado']);
        exit;
    }
    $cliente['activo'] = (bool)$cliente['activo'];

    // Créditos del cliente con fecha del cuadre
    $stmtCr = $db->prepare("
        SELECT c.id, c.descripcion, c.monto_usd, c.monto_bs, c.pagado, c.created_at,
               DATE_FORMAT(cc.fecha, '%d/%m/%Y') AS fecha_cuadre
        FROM creditos c
        JOIN cuadre_caja cc ON cc.id = c.cuadre_id
        WHERE c.cliente_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmtCr->execute([$clienteId]);
    $creditos = $stmtCr->fetchAll();

    // Para cada crédito, cargar sus abonos
    $stmtAb = $db->prepare("
        SELECT a.id, a.monto_usd, a.monto_bs, a.created_at,
               DATE_FORMAT(cc.fecha, '%d/%m/%Y') AS fecha_cuadre
        FROM abonos_credito a
        JOIN cuadre_caja cc ON cc.id = a.cuadre_id
        WHERE a.credito_id = ?
        ORDER BY a.created_at ASC
    ");

    foreach ($creditos as &$cr) {
        $cr['monto_usd'] = (float)$cr['monto_usd'];
        $cr['monto_bs']  = (float)$cr['monto_bs'];
        $cr['pagado']    = (bool)$cr['pagado'];

        $stmtAb->execute([$cr['id']]);
        $abonos = $stmtAb->fetchAll();

        $abonadoUsd = 0;
        $abonadoBs  = 0;
        foreach ($abonos as &$ab) {
            $ab['monto_usd'] = (float)$ab['monto_usd'];
            $ab['monto_bs']  = (float)$ab['monto_bs'];
            $abonadoUsd += $ab['monto_usd'];
            $abonadoBs  += $ab['monto_bs'];
        }
        unset($ab);

        $cr['abonos']       = $abonos;
        $cr['abonado_usd']  = round($abonadoUsd, 2);
        $cr['abonado_bs']   = round($abonadoBs, 2);
        $cr['pendiente_usd'] = round($cr['monto_usd'] - $abonadoUsd, 2);
        $cr['pendiente_bs']  = round($cr['monto_bs']  - $abonadoBs, 2);
    }
    unset($cr);

    echo json_encode([
        'cliente'  => $cliente,
        'creditos' => $creditos,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de servidor']);
}
