<?php
require "../includes/auth_check.php";
require_once "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["rol"] !== "repartidor") {
    exit("⚠️ Sesión inválida.");
}

$id_repartidor = $_SESSION["user"]["id"];

// 1️⃣ Aceptar pedido
if (isset($_POST["aceptar"])) {
    $id = intval($_POST["aceptar"]);
    $stmt = $pdo->prepare('UPDATE "Division_Geografica"."pedidos"
                           SET id_repartidor = :r, estado = :e WHERE id = :id');
    $stmt->execute([":r" => $id_repartidor, ":e" => "en camino", ":id" => $id]);
}

// 2️⃣ Marcar como entregado
if (isset($_POST["entregado"])) {
    $id = intval($_POST["entregado"]);
    $stmt = $pdo->prepare('UPDATE "Division_Geografica"."pedidos"
                           SET estado = :e WHERE id = :id');
    $stmt->execute([":e" => "entregado", ":id" => $id]);
}

// 3️⃣ Pedidos
$sql = 'SELECT p.id, p.estado, p.total, p.resumen, c.nombre AS comercio, u.nombre AS cliente
        FROM "Division_Geografica"."pedidos" p
        JOIN "Division_Geografica"."comercio" c ON p.id_comercio = c.codigo_com
        JOIN "Gestion_Usuarios"."Usuario" u ON p.id_usuario = u.gid
        ORDER BY p.fecha DESC';
$stmt = $pdo->query($sql);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($pedidos as $p): ?>
    <div class="card-pedido">
        <h3>Pedido #<?= $p["id"] ?> — 
            <span style="color:#ff6f00;"><?= strtoupper($p["estado"]) ?></span></h3>
        <p><strong>Recoger:</strong> <?= htmlspecialchars($p["comercio"]) ?></p>
        <p><strong>Entregar a:</strong> <?= htmlspecialchars($p["cliente"]) ?></p>
        <p><strong>Productos:</strong><br><?= nl2br(htmlspecialchars($p["resumen"])) ?></p>
        <p><strong>Total:</strong> $<?= number_format($p["total"], 2) ?></p>
        <?php if ($p["estado"] === "listo"): ?>
            <form method="POST">
                <input type="hidden" name="aceptar" value="<?= $p["id"] ?>">
                <button type="submit" class="btn-aceptar" style="background:#00b894;">🚚 Aceptar</button>
            </form>
        <?php elseif ($p["estado"] === "en camino"): ?>
            <form method="POST">
                <input type="hidden" name="entregado" value="<?= $p["id"] ?>">
                <button type="submit" class="btn-aceptar" style="background:#00796b;">✅ Entregado</button>
            </form>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
