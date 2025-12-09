<?php
require "../includes/auth_check.php";
require_once "../config/db.php";
include "../includes/header.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["rol"] !== "usuario") {
    header("Location: ../auth/login.php");
    exit;
}

$user = $_SESSION["user"];
$id_usuario = $user["id"];
?>

<div class="repartidor-pedidos">
    <h1>🧾 Mis pedidos</h1>
    <div id="pedidos-container">
        <!-- Aquí se cargan los pedidos vía AJAX -->
    </div>
</div>

<script>
function cargarPedidos() {
    fetch("../pedidos/obtener_pedidos_usuario.php")
        .then(r => r.text())
        .then(html => document.getElementById("pedidos-container").innerHTML = html)
        .catch(err => console.error("Error al cargar pedidos:", err));
}

// Cargar al inicio y cada 5 segundos
cargarPedidos();
setInterval(cargarPedidos, 5000);
</script>

<?php include "../includes/footer.php"; ?>
