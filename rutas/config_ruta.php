<?php
// ============================================
// 🔗 Conexión a la base de datos PostGIS
// ============================================

// Ajusta tus credenciales según tu entorno:
$host = "localhost";
$port = "5432"; // ⚠️ cambia si tu PostgreSQL usa otro puerto (ej: 5432)
$dbname = "sig3";
$user = "postgres";
$password = "p"; // <-- cámbialo por tu contraseña real

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode([
        "ok" => false,
        "error" => "Error de conexión: " . $e->getMessage()
    ]));
}
?>
