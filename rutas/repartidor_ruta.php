<?php
require "../includes/auth_check.php";
require_once "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["rol"] !== "repartidor") {
    header("Location: ../auth/login.php");
    exit;
}

$id_pedido = $_GET["id_pedido"] ?? null;
if (!$id_pedido) die("⚠️ Falta el ID del pedido.");

// ===============================
// 🔹 Obtener puntos desde la BD
// ===============================
$sql = '
SELECT 
    p.id_comercio, 
    c.nombre AS comercio_nombre,
    ST_Y(ST_Transform(c.geom, 6249)) AS comercio_lat,
    ST_X(ST_Transform(c.geom, 6249)) AS comercio_lon,
    u.nombre AS usuario_nombre,
    ST_Y(ST_Transform(u.geom, 6249)) AS usuario_lat,
    ST_X(ST_Transform(u.geom, 6249)) AS usuario_lon,
    r.nombre AS repartidor_nombre,
    ST_Y(ST_Transform(r.geom, 6249)) AS rep_lat,
    ST_X(ST_Transform(r.geom, 6249)) AS rep_lon
FROM "Division_Geografica"."pedidos" p
JOIN "Division_Geografica"."comercio" c ON p.id_comercio = c.codigo_com
JOIN "Gestion_Usuarios"."Usuario" u ON p.id_usuario = u.gid
JOIN "Gestion_Usuarios"."repartidores" r ON p.id_repartidor = r.codigo_rep
WHERE p.id = :id';

$stmt = $pdo->prepare($sql);
$stmt->execute([":id" => $id_pedido]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) die("❌ Pedido no encontrado o sin ubicaciones definidas.");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>🚴 Ruta del Pedido #<?= htmlspecialchars($id_pedido) ?></title>

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
html, body, #map {
  height: 100%;
  margin: 0;
}
.info-box {
  position: absolute;
  top: 10px;
  left: 10px;
  z-index: 1000;
  background: rgba(255, 255, 255, 0.95);
  padding: 1rem;
  border-radius: 12px;
  box-shadow: 0 3px 6px rgba(0, 0, 0, 0.3);
  font-family: 'Segoe UI', sans-serif;
}
.info-box h3 {
  margin: 0 0 .5rem;
  color: #ff0000ff;
}
button, .btn-back {
  background: linear-gradient(135deg, #ff0000ff, #ac0000ff);
  color: #fff;
  border: none;
  padding: .5rem 1rem;
  border-radius: 999px;
  cursor: pointer;
  font-weight: 600;
  display: inline-block;
  text-decoration: none;
  margin-top: .5rem;
}
button:hover, .btn-back:hover {
  background: linear-gradient(135deg, #ff2222ff, #980000ff);
}
</style>
</head>
<body>

<div id="map"></div>
<div class="info-box">
  <h3>🚴 Ruta del pedido #<?= htmlspecialchars($id_pedido) ?></h3>
  <p><strong>De:</strong> <?= htmlspecialchars($pedido["comercio_nombre"]) ?><br>
     <strong>Para:</strong> <?= htmlspecialchars($pedido["usuario_nombre"]) ?></p>

  <button onclick="calcularRuta()">🔄 Ver ruta óptima</button><br>
  <a href="../dashboard/repartidor.php" class="btn-back">⬅️ Volver al panel</a>
</div>

<script>
const API_RUTA = "../rutas/route.php";
const pedido = <?= json_encode($pedido, JSON_UNESCAPED_UNICODE) ?>;

// Inicializar mapa base igual al QGIS2Web
let map = L.map('map').setView([pedido.rep_lat, pedido.rep_lon], 17);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 22,
  attribution: 'Map data © OpenStreetMap contributors'
}).addTo(map);

let routeLayer = null;

// 🔹 Calcular ruta y dibujarla
async function calcularRuta() {
  const url = `${API_RUTA}?id_pedido=<?= $id_pedido ?>&latR=${pedido.rep_lat}&lonR=${pedido.rep_lon}&latC=${pedido.comercio_lat}&lonC=${pedido.comercio_lon}&latU=${pedido.usuario_lat}&lonU=${pedido.usuario_lon}`;
  const res = await fetch(url);
  const data = await res.json();

  if (!data.ok) {
    alert("❌ " + data.error);
    if (data.debug) {
      if (data.debug.repartidor) L.geoJSON(data.debug.repartidor, {color:'red'}).addTo(map);
      if (data.debug.comercio) L.geoJSON(data.debug.comercio, {color:'blue'}).addTo(map);
      if (data.debug.usuario) L.geoJSON(data.debug.usuario, {color:'green'}).addTo(map);
    }
    return;
  }

  // Limpiar rutas previas
  if (routeLayer) map.removeLayer(routeLayer);

  // Dibujar ruta óptima
  routeLayer = L.geoJSON(data.route, { color: "#ff0000ff", weight: 5 }).addTo(map);
  map.fitBounds(routeLayer.getBounds());

  // Dibujar puntos de interés
  L.marker([pedido.rep_lat, pedido.rep_lon]).addTo(map).bindPopup("🚴 Repartidor");
  L.marker([pedido.comercio_lat, pedido.comercio_lon]).addTo(map).bindPopup("🏪 Comercio");
  L.marker([pedido.usuario_lat, pedido.usuario_lon]).addTo(map).bindPopup("👤 Usuario");

  // Mostrar distancia total
  if (data.meta && data.meta.distancia_m) {
    L.popup({autoClose: false, closeOnClick: false})
      .setLatLng(routeLayer.getBounds().getCenter())
      .setContent(`📏 <b>Distancia total:</b> ${(data.meta.distancia_m/1000).toFixed(2)} km`)
      .openOn(map);
  }
}

// Ejecutar automáticamente al cargar
calcularRuta();
</script>
</body>
</html>
