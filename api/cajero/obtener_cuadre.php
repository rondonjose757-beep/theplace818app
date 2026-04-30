<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$hoy = date('Y-m-d');
$db  = getDB();

// Tasa del día
$st = $db->prepare('SELECT id, fecha, tasa FROM tasa_bcv WHERE fecha = ? LIMIT 1');
$st->execute([$hoy]);
$tasa = $st->fetch() ?: null;

if (!$tasa) {
    echo json_encode(['tasa' => null, 'cuadre' => null, 'pagos' => [], 'gastos' => [], 'consumo' => []]);
    exit;
}

// Cuadre del día
$st = $db->prepare('SELECT id, fecha, cerrado, observaciones FROM cuadre_caja WHERE fecha = ? LIMIT 1');
$st->execute([$hoy]);
$cuadre = $st->fetch() ?: null;

if (!$cuadre) {
    echo json_encode([
        'tasa'   => ['id' => (int)$tasa['id'], 'fecha' => $tasa['fecha'], 'tasa' => (float)$tasa['tasa']],
        'cuadre' => null,
        'pagos'  => [],
        'gastos' => [],
        'consumo'=> [],
    ]);
    exit;
}

$cuadreId = (int)$cuadre['id'];

// Pagos — indexados por metodo
$st = $db->prepare('SELECT id, metodo, monto_bs, monto_usd FROM pagos_dia WHERE cuadre_id = ?');
$st->execute([$cuadreId]);
$pagos = [];
foreach ($st->fetchAll() as $p) {
    $pagos[$p['metodo']] = [
        'id'        => (int)$p['id'],
        'monto_bs'  => (float)$p['monto_bs'],
        'monto_usd' => (float)$p['monto_usd'],
    ];
}

// Gastos
$st = $db->prepare('SELECT id, categoria, descripcion, monto_bs, monto_usd FROM gastos WHERE cuadre_id = ? ORDER BY id ASC');
$st->execute([$cuadreId]);
$gastos = array_map(fn($g) => [
    'id'        => (int)$g['id'],
    'categoria' => $g['categoria'],
    'descripcion'=> $g['descripcion'],
    'monto_bs'  => (float)$g['monto_bs'],
    'monto_usd' => (float)$g['monto_usd'],
], $st->fetchAll());

// Consumo familiar
$st = $db->prepare('SELECT id, responsable, descripcion, cantidad, precio_usd, precio_bs FROM consumo_familiar WHERE cuadre_id = ? ORDER BY id ASC');
$st->execute([$cuadreId]);
$consumo = array_map(fn($c) => [
    'id'          => (int)$c['id'],
    'responsable' => $c['responsable'],
    'descripcion' => $c['descripcion'],
    'cantidad'    => (int)$c['cantidad'],
    'precio_usd'  => (float)$c['precio_usd'],
    'precio_bs'   => (float)$c['precio_bs'],
], $st->fetchAll());

echo json_encode([
    'tasa'   => ['id' => (int)$tasa['id'], 'fecha' => $tasa['fecha'], 'tasa' => (float)$tasa['tasa']],
    'cuadre' => [
        'id'           => $cuadreId,
        'cerrado'      => (bool)$cuadre['cerrado'],
        'observaciones'=> $cuadre['observaciones'],
    ],
    'pagos'  => $pagos,
    'gastos' => $gastos,
    'consumo'=> $consumo,
]);
