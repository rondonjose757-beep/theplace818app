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
    $db   = getDB();
    $hoy  = date('Y-m-d');
    $stmt = $db->prepare('SELECT id, cerrado FROM cuadre_caja WHERE fecha = ? LIMIT 1');
    $stmt->execute([$hoy]);
    $cuadre = $stmt->fetch();

    if (!$cuadre) {
        echo json_encode(['cuadre_id' => null, 'cerrado' => null]);
        exit;
    }

    echo json_encode([
        'cuadre_id' => (int)$cuadre['id'],
        'cerrado'   => (bool)$cuadre['cerrado'],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de servidor']);
}
