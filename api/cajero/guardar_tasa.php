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

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$tasa = isset($body['tasa']) ? (float)$body['tasa'] : 0;

if ($tasa <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'La tasa debe ser mayor a cero']);
    exit;
}

$hoy = date('Y-m-d');
$db  = getDB();

try {
    $db->beginTransaction();

    // Upsert tasa — fecha tiene UNIQUE, usamos ON DUPLICATE KEY UPDATE
    $db->prepare(
        'INSERT INTO tasa_bcv (fecha, tasa, usuario_id)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE tasa = VALUES(tasa), usuario_id = VALUES(usuario_id), id = LAST_INSERT_ID(id)'
    )->execute([$hoy, $tasa, $_currentUserId]);

    $tasaId = (int)$db->lastInsertId();

    // Asegurar que tengamos el id real (en caso de que LAST_INSERT_ID retorne 0)
    if ($tasaId === 0) {
        $st = $db->prepare('SELECT id FROM tasa_bcv WHERE fecha = ? LIMIT 1');
        $st->execute([$hoy]);
        $tasaId = (int)$st->fetchColumn();
    }

    // Cuadre del día
    $st = $db->prepare('SELECT id, cerrado FROM cuadre_caja WHERE fecha = ? LIMIT 1');
    $st->execute([$hoy]);
    $cuadre = $st->fetch();

    if (!$cuadre) {
        $db->prepare(
            'INSERT INTO cuadre_caja (fecha, usuario_id, tasa_bcv_id) VALUES (?, ?, ?)'
        )->execute([$hoy, $_currentUserId, $tasaId]);
        $cuadreId = (int)$db->lastInsertId();
    } else {
        $cuadreId = (int)$cuadre['id'];
        if (!(bool)$cuadre['cerrado']) {
            $db->prepare('UPDATE cuadre_caja SET tasa_bcv_id = ? WHERE id = ?')
               ->execute([$tasaId, $cuadreId]);
        }
    }

    $db->commit();

    echo json_encode([
        'success'  => true,
        'tasa'     => $tasa,
        'tasa_id'  => $tasaId,
        'cuadre_id'=> $cuadreId,
    ]);
} catch (PDOException $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar la tasa']);
}
