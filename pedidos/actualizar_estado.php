<?php
require "../includes/auth_check.php";
require_once "../config/db.php";

$id_pedido = $_GET["id"] ?? null;
$nuevo_estado = $_GET["estado"] ?? null;

if (!$id_pedido || !$nuevo_estado) {
    die("⚠️ Falta información para actualizar el estado.");
}

// Validar el rol
$rol = $_SESSION["user"]["rol"];
$permitidos = ["pendiente", "listo", "en camino", "entregado"];
if (!in_array($nuevo_estado, $permitidos)) {
    die("⚠️ Estado no permitido.");
}

// Actualizar estado del pedido
$stmt = $pdo->prepare('UPDATE "Division_Geografica"."pedidos"
                       SET estado = :e
                       WHERE id = :id');
$stmt->execute([
    ":e" => $nuevo_estado,
    ":id" => $id_pedido
]);

// Redirigir según el rol
if ($rol === "comercio") header("Location: ../dashboard/comercio.php");
elseif ($rol === "repartidor") header("Location: ../dashboard/repartidor.php");
else header("Location: ../dashboard/usuario.php");
exit;
?>
