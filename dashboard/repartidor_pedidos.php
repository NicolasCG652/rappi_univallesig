<?php
require "../includes/auth_check.php";
require_once "../config/db.php";

$user = $_SESSION["user"];
$id_comercio = $user["id"];

$sql = 'SELECT p.id, p.estado, p.total, p.resumen, p.fecha,
               p.id_repartidor,
               c.nombre AS comercio, u.nombre AS usuario
        FROM "Division_Geografica"."pedidos" p
        JOIN "Division_Geografica"."comercio" c ON p.id_comercio = c.codigo_com
        JOIN "Gestion_Usuarios"."Usuario" u ON p.id_usuario = u.gid
        WHERE (p.id_repartidor IS NULL OR p.id_repartidor = :r)
        AND p.estado IN (\'listo\', \'en camino\')
        ORDER BY p.fecha DESC';
?>

<style>
.card-pedido {background:#fff2e0;border-left:6px solid #ff6f00;padding:1rem;
border-radius:12px;margin-bottom:1rem;box-shadow:0 5px 14px rgba(0,0,0,0.1);}
.btn-despachar{
 background:linear-gradient(135deg,#ff8f00,#ff6f00);color:#fff;padding:.4rem .8rem;
 border:none;border-radius:999px;font-weight:600;cursor:pointer;}
.btn-despachar:hover{opacity:.9;}
</style>

<div id="pedidos-comercio">
  <h2>📦 Pedidos recibidos</h2>
  <?php if (empty($pedidos)): ?>
    <p>No tienes pedidos aún.</p>
  <?php else: foreach ($pedidos as $p): ?>
    <div class="card-pedido">
      <h3>Pedido #<?= $p["id"] ?> — <?= strtoupper($p["estado"]) ?></h3>
      <p><strong>Cliente:</strong> <?= htmlspecialchars($p["usuario"]) ?> (<?= htmlspecialchars($p["email"]) ?>)</p>
      <p><?= nl2br(htmlspecialchars($p["resumen"])) ?></p>
      <strong>Total: $<?= number_format($p["total"],2) ?></strong><br>
      <small><?= date("d/m/Y H:i", strtotime($p["fecha"])) ?></small><br><br>
      <?php if ($p["estado"] === "pendiente"): ?>
        <a href="../pedidos/actualizar_estado.php?id=<?= $p["id"] ?>&estado=listo" class="btn-despachar">🚀 Marcar como listo</a>
      <?php elseif ($p["estado"] === "en camino"): ?>
        <a href="../pedidos/actualizar_estado.php?id=<?= $p["id"] ?>&estado=entregado" class="btn-despachar">✅ Pedido entregado</a>
      <?php endif; ?>
    </div>
  <?php endforeach; endif; ?>
</div>

<script>setInterval(()=>{location.reload();},5000);</script>
