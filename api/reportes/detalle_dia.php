<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://theplace818app.gastroredes.com');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$cuadreId = (int)($_GET['cuadre_id'] ?? 0);
if ($cuadreId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'cuadre_id inválido']);
    exit;
}

try {
    $db = getDB();

    // ---- Datos del cuadre ----
    $stm = $db->prepare("
        SELECT c.id, c.fecha, c.cerrado, c.observaciones, t.tasa
        FROM cuadre_caja c
        JOIN tasa_bcv t ON c.tasa_bcv_id = t.id
        WHERE c.id = :id
    ");
    $stm->execute([':id' => $cuadreId]);
    $cuadre = $stm->fetch();

    if (!$cuadre) {
        http_response_code(404);
        echo json_encode(['error' => 'Cuadre no encontrado']);
        exit;
    }
    $cuadre['cerrado'] = (bool)$cuadre['cerrado'];
    $cuadre['tasa']    = (float)$cuadre['tasa'];

    // ---- Pagos ----
    $stm = $db->prepare("
        SELECT metodo, monto_bs, monto_usd
        FROM pagos_dia
        WHERE cuadre_id = :id
        ORDER BY monto_usd DESC
    ");
    $stm->execute([':id' => $cuadreId]);
    $pagos = $stm->fetchAll();
    foreach ($pagos as &$row) {
        $row['monto_bs']  = (float)$row['monto_bs'];
        $row['monto_usd'] = (float)$row['monto_usd'];
    }
    unset($row);

    // ---- Gastos ----
    $stm = $db->prepare("
        SELECT id, categoria, descripcion, monto_bs, monto_usd
        FROM gastos
        WHERE cuadre_id = :id
        ORDER BY categoria, monto_usd DESC
    ");
    $stm->execute([':id' => $cuadreId]);
    $gastos = $stm->fetchAll();
    foreach ($gastos as &$row) {
        $row['monto_bs']  = (float)$row['monto_bs'];
        $row['monto_usd'] = (float)$row['monto_usd'];
    }
    unset($row);

    // ---- Consumo familiar ----
    $stm = $db->prepare("
        SELECT responsable, descripcion, cantidad, precio_usd, precio_bs
        FROM consumo_familiar
        WHERE cuadre_id = :id
        ORDER BY responsable
    ");
    $stm->execute([':id' => $cuadreId]);
    $consumo = $stm->fetchAll();
    foreach ($consumo as &$row) {
        $row['cantidad']   = (int)$row['cantidad'];
        $row['precio_usd'] = (float)$row['precio_usd'];
        $row['precio_bs']  = (float)$row['precio_bs'];
    }
    unset($row);

    // ---- Totales calculados ----
    $ventasUsd  = array_sum(array_column($pagos,  'monto_usd'));
    $ventasBs   = array_sum(array_column($pagos,  'monto_bs'));
    $gastosUsd  = array_sum(array_column($gastos, 'monto_usd'));
    $gastosBs   = array_sum(array_column($gastos, 'monto_bs'));
    $consumoUsd = array_sum(array_map(
        fn($c) => $c['cantidad'] * $c['precio_usd'],
        $consumo
    ));

    echo json_encode([
        'success' => true,
        'cuadre'  => $cuadre,
        'pagos'   => $pagos,
        'gastos'  => $gastos,
        'consumo' => $consumo,
        'totales' => [
            'ventas_usd'  => round($ventasUsd,  2),
            'ventas_bs'   => round($ventasBs,   2),
            'gastos_usd'  => round($gastosUsd,  2),
            'gastos_bs'   => round($gastosBs,   2),
            'consumo_usd' => round($consumoUsd, 2),
            'balance_usd' => round($ventasUsd - $gastosUsd - $consumoUsd, 2),
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos']);
}
