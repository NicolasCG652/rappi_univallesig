<?php
require "../includes/auth_check.php";
require_once "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["rol"] !== "repartidor") {
    header("Location: ../auth/login.php");
    exit;
}

$id_sesion = $_SESSION["user"]["id"] ?? null;
$id_pedido = $_GET["id"] ?? null;

if (!$id_pedido || !$id_sesion) {
    die("⚠️ Datos incompletos.");
}

// 🔹 Verificar si el ID corresponde a codigo_rep o gid
$stmt = $pdo->prepare('SELECT codigo_rep FROM "Gestion_Usuarios"."repartidores" WHERE codigo_rep = :id');
$stmt->execute([":id" => $id_sesion]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    // Si no hay coincidencia, buscar por gid
    $stmt = $pdo->prepare('SELECT codigo_rep FROM "Gestion_Usuarios"."repartidores" WHERE gid = :g');
    $stmt->execute([":g" => $id_sesion]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$row) {
    die("⚠️ No se encontró el repartidor en la base de datos.");
}

$codigo_rep = $row["codigo_rep"];

// 🔹 Asignar pedido al repartidor correcto
$stmt = $pdo->prepare('UPDATE "Division_Geografica"."pedidos"
                       SET id_repartidor = :r, estado = :e
                       WHERE id = :id AND (id_repartidor IS NULL OR id_repartidor = :r)');
$stmt->execute([
    ":r" => $codigo_rep,
    ":e" => "en camino",
    ":id" => $id_pedido
]);

header("Location: ../dashboard/repartidor.php");
exit;
?>
