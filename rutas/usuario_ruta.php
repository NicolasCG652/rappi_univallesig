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
// 🔹 Leer directamente la ruta guardada + ubicaciones reales
// ===============================
$sql = '
SELECT 
  p.id, p.estado,
  ST_AsGeoJSON(p.ruta_geom) AS ruta,
  r.nombre AS repartidor_nombre,
  ST_Y(ST_Transform(r.geom, 4326)) AS rep_lat,
  ST_X(ST_Transform(r.geom, 4326)) AS rep_lon,
  c.nombre AS comercio_nombre,
  ST_Y(ST_Transform(c.geom, 4326)) AS com_lat,
  ST_X(ST_Transform(c.geom, 4326)) AS com_lon,
  u.nombre AS usuario_nombre,
  ST_Y(ST_Transform(u.geom, 4326)) AS usr_lat,
  ST_X(ST_Transform(u.geom, 4326)) AS usr_lon
FROM "Division_Geografica"."pedidos" p
LEFT JOIN "Gestion_Usuarios"."repartidores" r ON p.id_repartidor = r.codigo_rep
LEFT JOIN "Division_Geografica"."comercio" c ON p.id_comercio = c.codigo_com
LEFT JOIN "Gestion_Usuarios"."Usuario" u ON p.id_usuario = u.gid
WHERE p.id = :id';

$stmt = $pdo->prepare($sql);
$stmt->execute([":id" => $id_pedido]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) die("❌ Pedido no encontrado.");
if (empty($pedido["ruta"])) die("⚠️ Este pedido aún no tiene una ruta guardada en la base de datos.");
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
.info-box h3 { margin: 0 0 .5rem; color: #ff0000ff; }
</style>
</head>
<body>
<div id="map"></div>
<div class="info-box">
  <h3>📦 Ruta del pedido #<?= htmlspecialchars($id_pedido) ?></h3>
  <p>
    <strong>Repartidor:</strong> <?= htmlspecialchars($pedido["repartidor_nombre"] ?? "N/D") ?><br>
    <strong>Comercio:</strong> <?= htmlspecialchars($pedido["comercio_nombre"] ?? "N/D") ?><br>
    <strong>Cliente:</strong> <?= htmlspecialchars($pedido["usuario_nombre"] ?? "N/D") ?><br>
    <strong>Estado:</strong> <?= strtoupper($pedido["estado"]) ?>
  </p>
</div>

<script>
const pedido = <?= json_encode($pedido, JSON_UNESCAPED_UNICODE) ?>;

// Crear mapa base
let map = L.map('map').setView([3.376, -76.53], 17);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 22,
  attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

// Mostrar ruta directamente desde GeoJSON (sin tocar)
try {
  const ruta = JSON.parse(pedido.ruta);
  const layer = L.geoJSON(ruta, { color: "#ff0000ff", weight: 5 }).addTo(map);
  map.fitBounds(layer.getBounds());
} catch (err) {
  console.error("❌ Error mostrando ruta:", err);
  alert("⚠️ La geometría guardada no es válida o está vacía.");
}

// ===============================
// 🔹 Agregar marcadores
// ===============================
const iconRider = L.icon({
  iconUrl: "https://cdn-icons-png.flaticon.com/512/2972/2972185.png",
  iconSize: [32, 32],
  iconAnchor: [16, 32]
});
const iconShop = L.icon({
  iconUrl: "https://cdn-icons-png.flaticon.com/512/869/869636.png",
  iconSize: [32, 32],
  iconAnchor: [16, 32]
});
const iconUser = L.icon({
  iconUrl: "https://cdn-icons-png.flaticon.com/512/3177/3177440.png",
  iconSize: [32, 32],
  iconAnchor: [16, 32]
});

if (pedido.rep_lat && pedido.rep_lon)
  L.marker([pedido.rep_lat, pedido.rep_lon], { icon: iconRider })
   .addTo(map).bindPopup("🚴 Repartidor");

if (pedido.com_lat && pedido.com_lon)
  L.marker([pedido.com_lat, pedido.com_lon], { icon: iconShop })
   .addTo(map).bindPopup("🏪 Comercio");

if (pedido.usr_lat && pedido.usr_lon)
  L.marker([pedido.usr_lat, pedido.usr_lon], { icon: iconUser })
   .addTo(map).bindPopup("👤 Cliente");

// Ajustar vista si no hay ruta
const puntos = [
  [pedido.rep_lat, pedido.rep_lon],
  [pedido.com_lat, pedido.com_lon],
  [pedido.usr_lat, pedido.usr_lon]
].filter(p => p[0] && p[1]);

if (puntos.length > 0) {
  const bounds = L.latLngBounds(puntos);
  map.fitBounds(bounds, { padding: [50, 50] });
}
</script>
</body>
</html>
