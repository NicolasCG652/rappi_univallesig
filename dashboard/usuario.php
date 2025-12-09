<?php
require "../includes/auth_check.php";
require_once "../config/db.php";
include "../includes/header.php";

$usuario = $_SESSION["user"] ?? null;

// ============================
// 1️⃣ OBTENER COMERCIOS
// ============================
$sql = 'SELECT codigo_com, nombre, email, imagen 
        FROM "Division_Geografica"."comercio"
        ORDER BY nombre';
$stmt = $pdo->query($sql);
$comercios = $stmt->fetchAll(PDO::FETCH_ASSOC);
$cantidadComercios = count($comercios);

// ============================
// 2️⃣ OBTENER PEDIDOS DEL USUARIO
// ============================
$stmt = $pdo->prepare('SELECT p.id, p.total, p.estado, p.resumen, p.fecha,
                              c.nombre AS comercio, c.email AS correo_comercio
                       FROM "Division_Geografica"."pedidos" p
                       JOIN "Division_Geografica"."comercio" c ON p.id_comercio = c.codigo_com
                       WHERE p.id_usuario = :u
                       ORDER BY p.fecha DESC');
$stmt->execute([":u" => $usuario["id"]]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ============================
     ESTILOS MEJORADOS
     ============================ -->
<style>
.user-dashboard {
    max-width: 1100px;
    margin: 0 auto;
    padding: 1.5rem;
}
.section {
    margin-top: 2rem;
}
.section-title {
    font-size: 1.4rem;
    color: #333;
    margin-bottom: .8rem;
}
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.2rem;
}
.card-local {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform .15s, box-shadow .2s;
}
.card-local:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}
.card-local img {
    width: 100%;
    height: 160px;
    object-fit: cover;
}
.card-local-content {
    padding: 1rem;
}
.card-local-content h3 {
    margin: 0;
    font-size: 1.1rem;
}
.card-local-content p {
    margin: .4rem 0;
    color: #555;
    font-size: .9rem;
}
.card-pedido {
    background: #fff4e0;
    border-left: 5px solid #ff0000ff;
    padding: 1rem;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
    margin-bottom: 1rem;
}
.btn {
    display: inline-block;
    background: linear-gradient(135deg, #ff3737ff, #ff0000ff);
    color: #fff;
    padding: .5rem 1rem;
    border-radius: 999px;
    text-decoration: none;
    font-weight: 600;
    transition: background .2s;
}
.btn:hover { background: linear-gradient(135deg, #ff2222ff, #ff0000ff); }
.btn-delete {
    background: #dc2626;
    color: #fff;
    padding: .4rem .9rem;
    border-radius: 999px;
    text-decoration: none;
    font-size: .85rem;
}
.btn-delete:hover { background: #b91c1c; }
</style>

<!-- ============================
     CONTENIDO
     ============================ -->
<div class="user-dashboard">

    <section class="section">
        <h1>Hola, <?= htmlspecialchars($usuario["nombre"] ?? "Usuario") ?> 👋</h1>
        <p>Explora los locales del campus y revisa su menú actualizado.</p>

        <div style="display:flex;align-items:center;gap:1rem;margin-top:1rem;">
            <a href="../geovisor/index.html" class="btn">🗺️ Ver geovisor</a>
            <span style="background:#ffead1;color:#ff6f00;padding:.4rem 1rem;border-radius:20px;font-weight:600;">
                <?= $cantidadComercios ?> comercios activos
            </span>
        </div>
    </section>

    <!-- BLOQUE DE COMERCIOS -->
    <section class="section">
        <h2 class="section-title">🍴 Locales de comida</h2>
        <div class="grid">
            <?php foreach ($comercios as $c): ?>
                <div class="card-local">
                    <img src="../public/img/comercios/<?= htmlspecialchars($c["imagen"] ?: 'default_food.jpg') ?>" 
                         alt="Imagen de <?= htmlspecialchars($c["nombre"]) ?>">
                    <div class="card-local-content">
                        <span class="tag">Comercio</span>
                        <h3><?= htmlspecialchars($c["nombre"]) ?></h3>
                        <p>Haz clic para ver su menú y realizar pedidos.</p>
                        <a href="menu.php?id=<?= htmlspecialchars($c['codigo_com']) ?>" class="btn">🍔 Ver menú</a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($cantidadComercios === 0): ?>
                <p>No hay comercios registrados todavía.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- BLOQUE DE PEDIDOS -->
    <section class="section">
    <h2 class="section-title">📦 Mis pedidos</h2>

    <?php if (empty($pedidos)): ?>
        <p>No has realizado pedidos todavía.</p>

    <?php else: ?>
        <?php foreach ($pedidos as $p): ?>
            <div class="card-pedido" style="background:#fff4e0;border-left:5px solid #ff0000;padding:1rem;margin-bottom:1rem;border-radius:12px;">

                <h3>Pedido #<?= $p["id"] ?> — <?= strtoupper($p["estado"]) ?></h3>

                <p><strong>Comercio:</strong> <?= htmlspecialchars($p["comercio"]) ?></p>
                <p><strong>Fecha:</strong> <?= date("d/m/Y H:i", strtotime($p["fecha"])) ?></p>
                <p><?= nl2br(htmlspecialchars($p["resumen"])) ?></p>
                <p><strong>Total:</strong> $<?= number_format($p["total"], 2) ?></p>

                <!-- SOLO SI EL PEDIDO ESTÁ EN CAMINO -->
                <?php if (strtoupper($p["estado"]) === "EN CAMINO"): ?>
                    <a href="../rutas/usuario_ruta.php?id_pedido=<?= $p["id"] ?>" 
                        target="_blank"
                        style="background:linear-gradient(135deg,#007bff,#0056d2);color:#fff;
                        padding:.45rem .9rem;border:none;border-radius:999px;
                        text-decoration:none;font-weight:600;display:inline-block;margin-top:.7rem;">
                        🗺️ Ver por dónde viene mi pedido
                    </a>
                <?php endif; ?>

                <!-- ELIMINAR SOLO SI ESTÁ PENDIENTE -->
                <?php if (strtoupper($p["estado"]) === "PENDIENTE"): ?>
                    <a href="../pedidos/eliminar_pedido.php?id=<?= $p["id"] ?>" 
                       onclick="return confirm('¿Seguro que quieres eliminar este pedido?');"
                       class="btn-delete"
                       style="margin-left:.5rem;">
                       🗑️ Eliminar
                    </a>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>


</div>

<?php include "../includes/footer.php"; ?>

<?php if ($p["estado"] === "en camino"): ?>
  <a href="../rutas/usuario_ruta.php?id_pedido=<?= $p["id"] ?>" 
     class="btn" style="background:linear-gradient(135deg,#ff8f00,#ff6f00);color:white;">
     🚴 Ver ruta del repartidor
  </a>
<?php endif; ?>
