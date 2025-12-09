<?php
require "../includes/auth_check.php";
require_once "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["rol"] !== "usuario") {
    exit("⚠️ Sesión inválida.");
}

$id_usuario = $_SESSION["user"]["id"];

$sql = 'SELECT p.id, p.estado, p.total, p.resumen, c.nombre AS comercio
        FROM "Division_Geografica"."pedidos" p
        JOIN "Division_Geografica"."comercio" c ON p.id_comercio = c.codigo_com
        WHERE p.id_usuario = :u
        ORDER BY p.fecha DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute([":u" => $id_usuario]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pedidos)) {
    echo "<p>No tienes pedidos todavía.</p>";
    exit;
}

foreach ($pedidos as $p): ?>
    <div class="card-pedido">
        <h3>Pedido #<?= htmlspecialchars($p["id"]) ?> — 
            <span style="color:#ff6f00;"><?= strtoupper($p["estado"]) ?></span></h3>
        <p><strong>Comercio:</strong> <?= htmlspecialchars($p["comercio"]) ?></p>
        <p><strong>Productos:</strong><br><?= nl2br(htmlspecialchars($p["resumen"])) ?></p>
        <p><strong>Total:</strong> $<?= number_format($p["total"], 2) ?></p>
        <?php if ($p["estado"] === "entregado"): ?>
            <p style="color:green;font-weight:600;">✅ Pedido entregado con éxito.</p>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
