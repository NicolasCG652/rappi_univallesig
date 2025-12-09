<?php
header("Content-Type: application/json");
require_once "../config/db.php";
session_start();

// ⚙️ Verificar sesión activa
if (!isset($_SESSION["user"])) {
    echo json_encode(["ok" => false, "error" => "❌ No autenticado."]);
    exit;
}

// 📍 Obtener coordenadas desde JSON o POST
$data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
$lat = isset($data["lat"]) ? floatval($data["lat"]) : null;
$lon = isset($data["lon"]) ? floatval($data["lon"]) : null;

if (!$lat || !$lon) {
    echo json_encode(["ok" => false, "error" => "⚠️ Faltan coordenadas válidas."]);
    exit;
}

// 🧍 Datos de la sesión actual
$rol = $_SESSION["user"]["rol"];
$id  = $_SESSION["user"]["id"];

try {
    // ✅ Convertir lat/lon a EPSG:6249 (metros Univalle)
    // PostgreSQL hace la conversión automáticamente
    // de EPSG:4326 → EPSG:6249 con ST_Transform.
    $geom = "ST_Transform(ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), 6249)";

    if ($rol === "usuario") {
        $sql = 'UPDATE "Gestion_Usuarios"."Usuario"
                SET geom = ' . $geom . '
                WHERE gid = :id';
    } elseif ($rol === "repartidor") {
        $sql = 'UPDATE "Gestion_Usuarios"."repartidores"
                SET geom = ' . $geom . '
                WHERE gid = :id';
    } elseif ($rol === "comercio") {
        $sql = 'UPDATE "Division_Geografica"."comercio"
                SET geom = ' . $geom . '
                WHERE codigo_com = :id';
    } else {
        throw new Exception("Rol no permitido para geolocalización.");
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ":lat" => $lat,
        ":lon" => $lon,
        ":id" => $id
    ]);

    // ✅ Verificar si se guardó
    $check = $pdo->prepare('SELECT ST_AsText(geom) FROM "Gestion_Usuarios"."Usuario" WHERE gid = :id');
    $check->execute([":id" => $id]);
    $geomText = $check->fetchColumn();

    echo json_encode([
        "ok" => true,
        "mensaje" => "✅ Ubicación actualizada correctamente en EPSG:6249.",
        "geom" => $geomText,
        "rol" => $rol,
        "id" => $id
    ]);
} catch (Exception $e) {
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}
?>
