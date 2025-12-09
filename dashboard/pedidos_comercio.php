<?php
require "../includes/auth_check.php";
require_once "../config/db.php";
include "../includes/header.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["rol"] !== "comercio") {
    echo '<p>⚠️ Acceso no autorizado.</p>';
    include "../includes/footer.php";
    exit;
}

$user = $_SESSION["user"];
$codigo_com = $user["id"];

// Intentar mapear GID a codigo_com
$stmt = $pdo->prepare('SELECT codigo_com FROM "Division_Geografica"."comercio" WHERE gid = :g OR codigo_com = :g');
$stmt->execute([":g" => $codigo_com]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$codigo_com = $row["codigo_com"] ?? $codigo_com;

// Marcar pedido como entregado al repartidor
if (isset($_GET["entregar"])) {
    $id = intval($_GET["entregar"]);
    $pdo->prepare('UPDATE "Division_Geografica"."pedidos" SET estado = :e WHERE id = :id')
        ->execute([":e" => "listo", ":id" => $id]);
    header("Location: pedidos_comercio.php");
    exit;
}

// Obtener pedidos del comercio
$sql = 'SELECT p.id, p.estado, p.total, p.resumen,
               u.nombre AS usuario, u.email AS correo_usuario
        FROM "Division_Geografica"."pedidos" p
        JOIN "Gestion_Usuarios"."Usuario" u ON p.id_usuario = u.gid
        WHERE p.id_comercio = :c
        ORDER BY p.fecha DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute([":c" => $codigo_com]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="repartidor-pedidos">
    <h1>📦 Pedidos del comercio</h1>

    <?php if (empty($pedidos)): ?>
        <p>No hay pedidos por ahora.</p>
    <?php else: ?>
        <?php foreach ($pedidos as $p): ?>
            <div class="card-pedido">
                <h3>Pedido #<?= htmlspecialchars($p["id"]) ?> — <?= strtoupper($p["estado"]) ?></h3>
                <p><strong>Cliente:</strong> <?= htmlspecialchars($p["usuario"]) ?> (<?= htmlspecialchars($p["correo_usuario"]) ?>)</p>
                <p><strong>Productos:</strong><br><?= nl2br(htmlspecialchars($p["resumen"])) ?></p>
                <p><strong>Total:</strong> $<?= number_format($p["total"], 2) ?></p>

                <?php if ($p["estado"] === "aceptado"): ?>
                    <a href="?entregar=<?= $p["id"] ?>" class="btn-aceptar" style="background:#ff9800;">
                        🚚 Marcar como entregado al repartidor
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include "../includes/footer.php"; ?>
