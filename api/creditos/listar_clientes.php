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

try {
    $db = getDB();

    // Calcula saldo pendiente por cliente: total créditos no pagados - total abonado
    $sql = "
        SELECT
            cc.id,
            cc.nombre,
            cc.telefono,
            cc.cedula,
            cc.activo,
            cc.created_at,
            COALESCE(
                (SELECT SUM(c.monto_usd)
                 FROM creditos c
                 WHERE c.cliente_id = cc.id AND c.pagado = 0), 0
            ) - COALESCE(
                (SELECT SUM(a.monto_usd)
                 FROM abonos_credito a
                 JOIN creditos c2 ON c2.id = a.credito_id
                 WHERE c2.cliente_id = cc.id AND c2.pagado = 0), 0
            ) AS saldo_usd,
            COALESCE(
                (SELECT SUM(c.monto_bs)
                 FROM creditos c
                 WHERE c.cliente_id = cc.id AND c.pagado = 0), 0
            ) - COALESCE(
                (SELECT SUM(a.monto_bs)
                 FROM abonos_credito a
                 JOIN creditos c2 ON c2.id = a.credito_id
                 WHERE c2.cliente_id = cc.id AND c2.pagado = 0), 0
            ) AS saldo_bs,
            (SELECT COUNT(*) FROM creditos c WHERE c.cliente_id = cc.id AND c.pagado = 0) AS creditos_pendientes
        FROM clientes_credito cc
        ORDER BY cc.activo DESC, cc.nombre ASC
    ";

    $stmt    = $db->query($sql);
    $clientes = $stmt->fetchAll();

    // Totales globales
    $totalUsd = 0;
    $totalBs  = 0;
    foreach ($clientes as &$c) {
        $c['saldo_usd']           = (float)$c['saldo_usd'];
        $c['saldo_bs']            = (float)$c['saldo_bs'];
        $c['activo']              = (bool)$c['activo'];
        $c['creditos_pendientes'] = (int)$c['creditos_pendientes'];
        $totalUsd += $c['saldo_usd'];
        $totalBs  += $c['saldo_bs'];
    }
    unset($c);

    echo json_encode([
        'clientes'  => $clientes,
        'total_usd' => round($totalUsd, 2),
        'total_bs'  => round($totalBs, 2),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de servidor']);
}
