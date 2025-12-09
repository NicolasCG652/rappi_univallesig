<?php
require "../includes/auth_check.php";
require_once "../config/db.php";

if ($_SESSION["user"]["rol"] !== "repartidor") {
    header("Location: ../auth/login.php");
    exit;
}

$id_repartidor = $_SESSION["user"]["id"];
$id_pedido = $_POST["id_pedido"];

$stmt = $pdo->prepare('UPDATE "Pedidos"."pedidos"
                       SET id_repartidor = :r, estado = \'aceptado\'
                       WHERE id = :p');
$stmt->execute([":r" => $id_repartidor, ":p" => $id_pedido]);

echo "<script>alert('🚴 Pedido aceptado correctamente');window.location='../dashboard/repartidor_pedidos.php';</script>";
?>
