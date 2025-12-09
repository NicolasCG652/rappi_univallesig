<?php
require "../includes/auth_check.php";
require_once "../config/db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["id_pedido"])) {
    $id = intval($_POST["id_pedido"]);
    $stmt = $pdo->prepare('UPDATE "Division_Geografica"."pedidos" SET estado = :e WHERE id = :id');
    $stmt->execute([":e" => "entregado", ":id" => $id]);
}
header("Location: ../dashboard/pedidos_usuario.php");
exit;
?>
