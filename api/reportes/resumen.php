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

// ---- Calcular rango de fechas según período ----
$periodo = $_GET['periodo'] ?? 'mes';
$mesParam  = max(1, min(12, (int)($_GET['mes']  ?? (int)date('m'))));
$anioParam = max(2020, min(2099, (int)($_GET['anio'] ?? (int)date('Y'))));

$MESES_ES = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',     4 => 'Abril',
    5 => 'Mayo',  6 => 'Junio',   7 => 'Julio',      8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];

switch ($periodo) {
    case 'hoy':
        $inicio = date('Y-m-d');
        $fin    = date('Y-m-d');
        $label  = 'Hoy — ' . date('d/m/Y');
        break;

    case 'semana':
        // Lunes de la semana actual
        $dow    = (int)date('N'); // 1=Lun … 7=Dom
        $inicio = date('Y-m-d', strtotime('-' . ($dow - 1) . ' days'));
        $fin    = date('Y-m-d');
        $label  = 'Esta semana';
        break;

    case 'mes_anterior':
        $ts     = strtotime('first day of last month');
        $inicio = date('Y-m-01', $ts);
        $fin    = date('Y-m-t',  $ts);
        $m      = (int)date('m', $ts);
        $a      = (int)date('Y', $ts);
        $label  = ($MESES_ES[$m] ?? '') . ' ' . $a;
        break;

    case 'personalizado':
        $inicio = sprintf('%04d-%02d-01', $anioParam, $mesParam);
        $fin    = date('Y-m-t', strtotime($inicio));
        $label  = ($MESES_ES[$mesParam] ?? '') . ' ' . $anioParam;
        break;

    case 'mes':
    default:
        $periodo = 'mes';
        $inicio  = date('Y-m-01');
        $fin     = date('Y-m-t');
        $label   = ($MESES_ES[(int)date('m')] ?? '') . ' ' . date('Y');
        break;
}

try {
    $db = getDB();

    // ---- Totales de ventas ----
    $stm = $db->prepare("
        SELECT
            COALESCE(SUM(p.monto_usd), 0) AS total_usd,
            COALESCE(SUM(p.monto_bs),  0) AS total_bs
        FROM pagos_dia p
        JOIN cuadre_caja c ON p.cuadre_id = c.id
        WHERE c.fecha BETWEEN :ini AND :fin
    ");
    $stm->execute([':ini' => $inicio, ':fin' => $fin]);
    $ventas = $stm->fetch();

    // ---- Totales de gastos ----
    $stm = $db->prepare("
        SELECT
            COALESCE(SUM(g.monto_usd), 0) AS total_usd,
            COALESCE(SUM(g.monto_bs),  0) AS total_bs
        FROM gastos g
        JOIN cuadre_caja c ON g.cuadre_id = c.id
        WHERE c.fecha BETWEEN :ini AND :fin
    ");
    $stm->execute([':ini' => $inicio, ':fin' => $fin]);
    $gastos = $stm->fetch();

    // ---- Ventas por método ----
    $stm = $db->prepare("
        SELECT
            p.metodo,
            COALESCE(SUM(p.monto_bs),  0) AS total_bs,
            COALESCE(SUM(p.monto_usd), 0) AS total_usd
        FROM pagos_dia p
        JOIN cuadre_caja c ON p.cuadre_id = c.id
        WHERE c.fecha BETWEEN :ini AND :fin
        GROUP BY p.metodo
        ORDER BY total_usd DESC
    ");
    $stm->execute([':ini' => $inicio, ':fin' => $fin]);
    $ventasPorMetodo = $stm->fetchAll();

    $totalVentasUsd = (float)$ventas['total_usd'];
    foreach ($ventasPorMetodo as &$row) {
        $row['total_usd'] = (float)$row['total_usd'];
        $row['total_bs']  = (float)$row['total_bs'];
        $row['pct']       = $totalVentasUsd > 0
            ? round($row['total_usd'] / $totalVentasUsd * 100, 1)
            : 0.0;
    }
    unset($row);

    // ---- Gastos por categoría ----
    $stm = $db->prepare("
        SELECT
            g.categoria,
            COALESCE(SUM(g.monto_bs),  0) AS total_bs,
            COALESCE(SUM(g.monto_usd), 0) AS total_usd
        FROM gastos g
        JOIN cuadre_caja c ON g.cuadre_id = c.id
        WHERE c.fecha BETWEEN :ini AND :fin
        GROUP BY g.categoria
        ORDER BY total_usd DESC
    ");
    $stm->execute([':ini' => $inicio, ':fin' => $fin]);
    $gastosPorCat = $stm->fetchAll();

    $totalGastosUsd = (float)$gastos['total_usd'];
    foreach ($gastosPorCat as &$row) {
        $row['total_usd'] = (float)$row['total_usd'];
        $row['total_bs']  = (float)$row['total_bs'];
        $row['pct']       = $totalGastosUsd > 0
            ? round($row['total_usd'] / $totalGastosUsd * 100, 1)
            : 0.0;
    }
    unset($row);

    // ---- Ventas diarias para la gráfica ----
    $stm = $db->prepare("
        SELECT
            c.fecha,
            COALESCE(SUM(p.monto_usd), 0) AS total_usd
        FROM cuadre_caja c
        LEFT JOIN pagos_dia p ON p.cuadre_id = c.id
        WHERE c.fecha BETWEEN :ini AND :fin
        GROUP BY c.fecha
        ORDER BY c.fecha ASC
    ");
    $stm->execute([':ini' => $inicio, ':fin' => $fin]);
    $ventasDiarias = $stm->fetchAll();
    foreach ($ventasDiarias as &$row) {
        $row['total_usd'] = (float)$row['total_usd'];
    }
    unset($row);

    // ---- Créditos pendientes (total acumulado) ----
    $totalCreditosPendientes = 0.0;
    $totalAbonosUsd          = 0.0;
    try {
        $stm = $db->query("
            SELECT COALESCE(SUM(monto_usd), 0) AS total
            FROM creditos
            WHERE pagado = 0
        ");
        $totalCreditosPendientes = (float)($stm->fetchColumn() ?: 0);

        $stm = $db->prepare("
            SELECT COALESCE(SUM(a.monto_usd), 0) AS total
            FROM abonos_credito a
            JOIN cuadre_caja c ON a.cuadre_id = c.id
            WHERE c.fecha BETWEEN :ini AND :fin
        ");
        $stm->execute([':ini' => $inicio, ':fin' => $fin]);
        $totalAbonosUsd = (float)($stm->fetchColumn() ?: 0);
    } catch (PDOException) {
        // Tablas de créditos aún no tienen datos
    }

    // ---- Lista de cuadres del período ----
    $stm = $db->prepare("
        SELECT
            c.id,
            c.fecha,
            t.tasa,
            c.cerrado,
            (SELECT COALESCE(SUM(p2.monto_usd), 0)
               FROM pagos_dia p2 WHERE p2.cuadre_id = c.id) AS total_ventas_usd,
            (SELECT COALESCE(SUM(g2.monto_usd), 0)
               FROM gastos g2 WHERE g2.cuadre_id = c.id)    AS total_gastos_usd
        FROM cuadre_caja c
        JOIN tasa_bcv t ON c.tasa_bcv_id = t.id
        WHERE c.fecha BETWEEN :ini AND :fin
        ORDER BY c.fecha DESC
    ");
    $stm->execute([':ini' => $inicio, ':fin' => $fin]);
    $cuadres = $stm->fetchAll();
    foreach ($cuadres as &$row) {
        $row['tasa']             = (float)$row['tasa'];
        $row['cerrado']          = (bool)$row['cerrado'];
        $row['total_ventas_usd'] = (float)$row['total_ventas_usd'];
        $row['total_gastos_usd'] = (float)$row['total_gastos_usd'];
    }
    unset($row);

    echo json_encode([
        'success' => true,
        'periodo' => [
            'tipo'   => $periodo,
            'inicio' => $inicio,
            'fin'    => $fin,
            'label'  => $label,
        ],
        'resumen' => [
            'total_ventas_usd'        => (float)$ventas['total_usd'],
            'total_ventas_bs'         => (float)$ventas['total_bs'],
            'total_gastos_usd'        => (float)$gastos['total_usd'],
            'utilidad_usd'            => round((float)$ventas['total_usd'] - (float)$gastos['total_usd'], 2),
            'total_creditos_pendientes' => $totalCreditosPendientes,
            'total_abonos_usd'        => $totalAbonosUsd,
        ],
        'ventas_por_metodo'   => $ventasPorMetodo,
        'gastos_por_categoria' => $gastosPorCat,
        'ventas_diarias'      => $ventasDiarias,
        'cuadres'             => $cuadres,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos']);
}
