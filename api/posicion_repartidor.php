<?php
header('Content-Type: application/json');
require_once "../config/db.php"; // Conexión principal a PostgreSQL

// ==========================================
// 🔍 OBTENER POSICIÓN ACTUAL DE UN REPARTIDOR
// ==========================================
// Este endpoint devuelve:
//  - Latitud y longitud actuales del repartidor
//  - Su nombre y estado de disponibilidad
//
// Parámetros aceptados:
//   👉 id_pedido       → obtiene al repartidor desde ese pedido
//   👉 id_repartidor   → obtiene posición directa del repartidor
//
// Ejemplos:
//   /api/posicion_repartidor.php?id_pedido=12
//   /api/posicion_repartidor.php?id_repartidor=5
// ==========================================

$id_pedido = $_GET["id_pedido"] ?? null;
$id_repartidor = $_GET["id_repartidor"] ?? null;

if (!$id_pedido && !$id_repartidor) {
    echo json_encode([
        "ok" => false,
        "error" => "⚠️ Debes enviar id_pedido o id_repartidor"
    ]);
    exit;
}

try {
    // 🔹 Si se envía un pedido, obtener el repartidor asignado
    if ($id_pedido) {
        $sql = 'SELECT id_repartidor 
                FROM "Division_Geografica"."pedidos"
                WHERE id = :id_pedido LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":id_pedido" => $id_pedido]);
        $id_repartidor = $stmt->fetchColumn();

        if (!$id_repartidor) {
            echo json_encode(["ok" => false, "error" => "❌ El pedido no tiene repartidor asignado"]);
            exit;
        }
    }

    // 🔹 Obtener la ubicación actual del repartidor
    $sql = 'SELECT 
                ST_Y(geom) AS lat, 
                ST_X(geom) AS lon, 
                nombre, 
                disponibilidad
            FROM "Gestion_Usuarios"."repartidores"
            WHERE codigo_rep = :id
            LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([":id" => $id_repartidor]);
    $pos = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pos) {
        echo json_encode(["ok" => false, "error" => "❌ Repartidor no encontrado"]);
        exit;
    }

    // ✅ Devolver datos limpios y estructurados
    echo json_encode([
        "ok" => true,
        "lat" => floatval($pos["lat"]),
        "lon" => floatval($pos["lon"]),
        "nombre" => $pos["nombre"],
        "disponible" => $pos["disponibilidad"] === "t" ? true : false
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        "ok" => false,
        "error" => "Error en el servidor: " . $e->getMessage()
    ]);
}
?>
