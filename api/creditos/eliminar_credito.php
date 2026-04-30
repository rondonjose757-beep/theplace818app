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
$id   = (int)($body['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$hoy = date('Y-m-d');
$db  = getDB();

try {
    // Solo se puede eliminar si el crédito es de hoy, no tiene abonos y no está pagado
    $stmt = $db->prepare("
        SELECT c.id
        FROM creditos c
        JOIN cuadre_caja cc ON cc.id = c.cuadre_id
        WHERE c.id = ? AND c.pagado = 0 AND cc.fecha = ? AND cc.cerrado = 0
    ");
    $stmt->execute([$id, $hoy]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Solo se pueden eliminar créditos de hoy sin abonos y con caja abierta']);
        exit;
    }

    // Verificar que no tiene abonos
    $stAb = $db->prepare('SELECT COUNT(*) FROM abonos_credito WHERE credito_id = ?');
    $stAb->execute([$id]);
    if ((int)$stAb->fetchColumn() > 0) {
        http_response_code(403);
        echo json_encode(['error' => 'No se puede eliminar un crédito con abonos registrados']);
        exit;
    }

    $db->prepare('DELETE FROM creditos WHERE id = ?')->execute([$id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de servidor']);
}
