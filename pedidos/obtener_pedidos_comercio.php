<?php
require "../includes/auth_check.php";
require_once "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["rol"] !== "comercio") {
    exit("⚠️ Sesión inválida.");
}

$id_comercio = $_SESSION["user"]["id"];

// 1️⃣ Cambiar estado a “listo” (despachado)
if (isset($_POST["despachar"])) {
    $id = intval($_POST["despachar"]);
    $stmt = $pdo->prepare('UPDATE "Division_Geografica"."pedidos" SET estado = :e WHERE id = :id');
    $stmt->execute([":e" => "listo", ":id" => $id]);
}

// 2️⃣ Obtener pedidos del comercio
$sql = 'SELECT p.id, p.estado, p.total, p.resumen, u.nombre AS cliente
        FROM "Division_Geografica"."pedidos" p
        JOIN "Gestion_Usuarios"."Usuario" u ON p.id_usuario = u.gid
        WHERE p.id_comercio = :c
        ORDER BY p.fecha DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute([":c" => $id_comercio]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pedidos)) {
    echo "<p>No hay pedidos para este comercio.</p>";
    exit;
}

foreach ($pedidos as $p): ?>
    <div class="card-pedido">
        <h3>Pedido #<?= htmlspecialchars($p["id"]) ?> — 
            <span style="color:#ff6f00;"><?= strtoupper($p["estado"]) ?></span></h3>
        <p><strong>Cliente:</strong> <?= htmlspecialchars($p["cliente"]) ?></p>
        <p><strong>Total:</strong> $<?= number_format($p["total"], 2) ?></p>
        <p><strong>Productos:</strong><br><?= nl2br(htmlspecialchars($p["resumen"])) ?></p>
        <?php if ($p["estado"] === "pendiente"): ?>
            <form method="POST">
                <input type="hidden" name="despachar" value="<?= $p["id"] ?>">
                <button type="submit" class="btn-aceptar" style="background:#ff6f00;">🚀 Despachar</button>
            </form>
        <?php elseif ($p["estado"] === "listo"): ?>
            <p style="color:green;">✅ Pedido listo para repartidor.</p>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
