<?php
require "../includes/auth_check.php";
require_once "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["rol"] !== "usuario") {
    header("Location: ../auth/login.php");
    exit;
}

$id_pedido = $_GET["id_pedido"] ?? null;
if (!$id_pedido) die("⚠️ Falta el ID del pedido.");

// ===============================
// 🔹 Obtener ruta guardada + posiciones
// ===============================
$sql = '
SELECT 
    p.id, p.estado,
    ST_AsGeoJSON(p.ruta_geom) AS ruta,
    r.nombre AS repartidor_nombre,
    ST_Y(ST_Transform(r.geom, 4326)) AS rep_lat,
    ST_X(ST_Transform(r.geom, 4326)) AS rep_lon,
    c.nombre AS comercio_nombre,
    ST_Y(ST_Transform(c.geom, 4326)) AS comercio_lat,
    ST_X(ST_Transform(c.geom, 4326)) AS comercio_lon,
    u.nombre AS usuario_nombre,
    ST_Y(ST_Transform(u.geom, 4326)) AS usuario_lat,
    ST_X(ST_Transform(u.geom, 4326)) AS usuario_lon
FROM "Division_Geografica"."pedidos" p
JOIN "Gestion_Usuarios"."repartidores" r ON p.id_repartidor = r.codigo_rep
JOIN "Division_Geografica"."comercio" c ON p.id_comercio = c.codigo_com
JOIN "Gestion_Usuarios"."Usuario" u ON p.id_usuario = u.gid
WHERE p.id = :id';
$stmt = $pdo->prepare($sql);
$stmt->execute([":id" => $id_pedido]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) die("❌ Pedido no encontrado o sin ruta registrada.");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>📦 Ruta del pedido #<?= htmlspecialchars($id_pedido) ?></title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
html, body, #map { height: 100%; margin: 0; }
.info-box {
  position: absolute;
  top: 10px; left: 10px; z-index: 1000;
  background: rgba(255,255,255,0.95);
  padding: 1rem; border-radius: 12px;
  box-shadow: 0 3px 6px rgba(0,0,0,0.3);
  font-family: 'Segoe UI', sans-serif;
}
.info-box h3 { margin: 0 0 .5rem; color: #ff6f00; }
</style>
</head>
<body>

<div id="map"></div>
<div class="info-box">
  <h3>📦 Ruta del pedido #<?= htmlspecialchars($id_pedido) ?></h3>
  <p><strong>Repartidor:</strong> <?= htmlspecialchars($pedido["repartidor_nombre"]) ?><br>
     <strong>Comercio:</strong> <?= htmlspecialchars($pedido["comercio_nombre"]) ?><br>
     <strong>Cliente:</strong> <?= htmlspecialchars($pedido["usuario_nombre"]) ?><br>
     Estado: <?= strtoupper($pedido["estado"]) ?>
  </p>
</div>

<script>
const pedido = <?= json_encode($pedido, JSON_UNESCAPED_UNICODE) ?>;

let map = L.map('map').setView([pedido.usuario_lat, pedido.usuario_lon], 17);
L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
  maxZoom: 22,
  attribution: 'Map data © Google'
}).addTo(map);

// Dibujar ruta guardada
if (pedido.ruta) {
  const ruta = JSON.parse(pedido.ruta);
  const routeLayer = L.geoJSON(ruta, { color: "#ff6f00", weight: 5 }).addTo(map);
  map.fitBounds(routeLayer.getBounds());
}

// Marcadores
L.marker([pedido.rep_lat, pedido.rep_lon]).addTo(map).bindPopup("🚴 Repartidor");
L.marker([pedido.comercio_lat, pedido.comercio_lon]).addTo(map).bindPopup("🏪 Comercio");
L.marker([pedido.usuario_lat, pedido.usuario_lon]).addTo(map).bindPopup("👤 Tú");
</script>
</body>
</html>
