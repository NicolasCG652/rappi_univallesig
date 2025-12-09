<?php
require "../includes/auth_check.php";
require_once "../config/db.php";
include "../includes/header.php";

// ============================
// Verificar sesión
// ============================
if (!isset($_SESSION["user"]) || $_SESSION["user"]["rol"] !== "repartidor") {
    echo '<div class="auth-message auth-message-error" style="margin:2rem;">⚠️ Acceso no autorizado.</div>';
    include "../includes/footer.php";
    exit;
}

// ============================
// Obtener código del repartidor real
// ============================
$gid = $_SESSION["user"]["id"];
$stmt = $pdo->prepare('SELECT codigo_rep FROM "Gestion_Usuarios"."repartidores" WHERE gid = :gid');
$stmt->execute([":gid" => $gid]);
$codigo_rep = $stmt->fetchColumn();

if (!$codigo_rep) {
    echo '<div class="auth-message auth-message-error" style="margin:2rem;">❌ No se encontró tu código de repartidor.</div>';
    include "../includes/footer.php";
    exit;
}

/* ======================================
   1️⃣ Aceptar pedido y redirigir a ruta
   ====================================== */
if (isset($_GET["aceptar"])) {
    $id_pedido = intval($_GET["aceptar"]);

    try {
        // Verificar que el pedido aún no tenga repartidor
        $check = $pdo->prepare('SELECT id_repartidor FROM "Division_Geografica"."pedidos" WHERE id = :id');
        $check->execute([":id" => $id_pedido]);
        $asignado = $check->fetchColumn();

        if ($asignado) {
            echo "<script>alert('⚠️ Este pedido ya fue tomado por otro repartidor.');window.location.href='pedidos_disponibles.php';</script>";
            exit;
        }

        // Actualizar pedido con el repartidor asignado
        $stmt = $pdo->prepare('UPDATE "Division_Geografica"."pedidos"
                               SET id_repartidor = :r, estado = :e
                               WHERE id = :id');
        $stmt->execute([
            ":r" => $codigo_rep,
            ":e" => "en camino",
            ":id" => $id_pedido
        ]);

        // Redirigir al mapa de ruta
        header("Location: ../rutas/repartidor_ruta.php?id_pedido=$id_pedido");
        exit;

    } catch (PDOException $e) {
        echo "<div class='auth-message auth-message-error' style='margin:2rem;'>
              ❌ Error al asignar pedido: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

/* ======================================
   2️⃣ Pedidos pendientes (sin repartidor)
   ====================================== */
$sql = 'SELECT p.id, p.total, p.detalle, p.estado, p.resumen,
               c.nombre AS comercio, c.email AS correo_comercio,
               u.nombre AS usuario, u.email AS correo_usuario
        FROM "Division_Geografica"."pedidos" p
        JOIN "Division_Geografica"."comercio" c ON p.id_comercio = c.codigo_com
        JOIN "Gestion_Usuarios"."Usuario" u ON p.id_usuario = u.gid
        WHERE p.id_repartidor IS NULL
        ORDER BY p.fecha DESC';
$stmt = $pdo->query($sql);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.repartidor-pedidos {
    max-width: 1000px;
    margin: 0 auto;
    padding: 1.5rem;
}
.card-pedido {
    background: #ffffff;
    border-radius: 14px;
    padding: 1rem;
    box-shadow: 0 6px 18px rgba(0,0,0,0.1);
    margin-bottom: 1rem;
    transition: transform .15s ease;
}
.card-pedido:hover {
    transform: translateY(-3px);
}
.card-pedido h3 { margin: 0; color: #222; }
.card-pedido p { margin: .3rem 0; color: #555; font-size: .9rem; }
.card-pedido strong { color: #00b894; }
.btn-aceptar {
    background: #00b894;
    color: #fff;
    border: none;
    padding: .5rem 1rem;
    border-radius: 999px;
    cursor: pointer;
    font-weight: 600;
    margin-top: .5rem;
    display: inline-block;
    text-decoration: none;
}
.btn-aceptar:hover { background: #019870; }
</style>

<div class="repartidor-pedidos">
    <h1>🚴 Pedidos disponibles</h1>
    <p>Puedes aceptar pedidos y ver los datos del comercio y del cliente.</p>

    <?php if (empty($pedidos)): ?>
        <p>✨ No hay pedidos pendientes en este momento.</p>
    <?php else: ?>
        <?php foreach ($pedidos as $p): ?>
            <div class="card-pedido">
                <h3>Pedido #<?= htmlspecialchars($p["id"]) ?> - <?= htmlspecialchars($p["estado"]) ?></h3>
                <p><strong>Recoger en:</strong> <?= htmlspecialchars($p["comercio"]) ?> (<?= htmlspecialchars($p["correo_comercio"]) ?>)</p>
                <p><strong>Entregar a:</strong> <?= htmlspecialchars($p["usuario"]) ?> (<?= htmlspecialchars($p["correo_usuario"]) ?>)</p>
                <p><strong>Total:</strong> $<?= number_format($p["total"], 2) ?></p>
                <p><strong>Productos:</strong><br><?= nl2br(htmlspecialchars($p["resumen"])) ?></p>
                <a href="?aceptar=<?= $p["id"] ?>" class="btn-aceptar">Aceptar pedido 🚚</a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include "../includes/footer.php"; ?>
