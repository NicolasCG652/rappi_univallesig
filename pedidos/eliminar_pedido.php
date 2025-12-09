<?php
require "../includes/auth_check.php";
require_once "../config/db.php";

$id = $_GET["id"] ?? null;
$user = $_SESSION["user"] ?? null;

if (!$id || !$user) {
    die("⚠️ Parámetros inválidos.");
}

// Verificar que el pedido es del usuario y aún pendiente
$stmt = $pdo->prepare('SELECT * FROM "Division_Geografica"."pedidos" WHERE id = :id AND id_usuario = :u AND estado = \'pendiente\'');
$stmt->execute([":id" => $id, ":u" => $user["id"]]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die("⚠️ No puedes eliminar este pedido (ya fue tomado o no existe).");
}

// Eliminar
$pdo->prepare('DELETE FROM "Division_Geografica"."pedidos" WHERE id = :id')->execute([":id" => $id]);

header("Location: ../dashboard/usuario.php");
exit;
?>
