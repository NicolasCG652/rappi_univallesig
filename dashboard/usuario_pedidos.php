<?php
require "../includes/auth_check.php";
require_once "../config/db.php";

$user = $_SESSION["user"];
$id_usuario = $user["id"];

$sql = 'SELECT p.id, p.estado, p.total, p.resumen, p.fecha,
               c.nombre AS comercio
        FROM "Division_Geografica"."pedidos" p
        JOIN "Division_Geografica"."comercio" c ON p.id_comercio = c.codigo_com
        WHERE p.id_usuario = :u
        ORDER BY p.fecha DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute([":u" => $id_usuario]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.card-pedido {
  background: linear-gradient(135deg, #ff8f00, #ff6f00);
  color: #fff; border-radius: 14px; padding: 1rem;
  margin-bottom: 1rem; box-shadow: 0 6px 18px rgba(0,0,0,0.15);
}
.card-pedido h3 { margin: 0; font-size: 1.1rem; }
.card-pedido small { opacity: .9; }
.estado {
  display:inline-block; padding:.3rem .7rem; border-radius:999px;
  background:#fff; color:#ff6f00; font-weight:600; font-size:.8rem;
}
</style>

<div id="pedidos-usuario">
  <h2>🛍️ Mis pedidos</h2>
  <?php if (empty($pedidos)): ?>
      <p>No has realizado pedidos aún.</p>
  <?php else: ?>
      <?php foreach ($pedidos as $p): ?>
          <div class="card-pedido">
              <h3>Pedido #<?= $p["id"] ?> — <span class="estado"><?= strtoupper($p["estado"]) ?></span></h3>
              <small>Comercio: <?= htmlspecialchars($p["comercio"]) ?></small>
              <p><?= nl2br(htmlspecialchars($p["resumen"])) ?></p>
              <strong>Total: $<?= number_format($p["total"], 2) ?></strong><br>
              <small><?= date("d/m/Y H:i", strtotime($p["fecha"])) ?></small>
          </div>
      <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
setInterval(()=>{ location.reload(); },5000);
</script>
