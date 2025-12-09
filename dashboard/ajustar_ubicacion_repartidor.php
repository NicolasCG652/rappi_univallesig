<?php
require "../includes/auth_check.php";
require_once "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["rol"] !== "repartidor") {
  header("Location: ../auth/login.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>📍 Ajustar mi ubicación - Repartidor</title>

<!-- ✅ Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
html, body, #map { height: 100%; margin: 0; padding: 0; }
#panel {
  position: absolute;
  top: 10px;
  left: 10px;
  z-index: 9999;
  background: rgba(255,255,255,0.97);
  padding: 1rem;
  border-radius: 12px;
  box-shadow: 0 3px 6px rgba(0,0,0,0.3);
  width: 320px;
  font-family: 'Segoe UI', sans-serif;
}
#panel h3 { margin: 0 0 .5rem 0; color: #ff6f00; font-size: 1.2rem; }
#panel p { font-size: .9rem; color: #444; }
button {
  background: linear-gradient(135deg, #ff8f00, #ff6f00);
  border: none;
  color: #fff;
  padding: .6rem 1.2rem;
  border-radius: 999px;
  cursor: pointer;
  font-weight: 600;
  margin-top: .5rem;
  width: 100%;
}
button:hover { background: linear-gradient(135deg, #ff9f22, #ff7f00); }
#msg-aviso {
  background: #fff9d6;
  border-left: 5px solid #ffcc00;
  padding: .6rem;
  border-radius: 6px;
  margin-bottom: .6rem;
  font-size: .85rem;
  color: #333;
}
</style>
</head>
<body>

<div id="map"></div>

<div id="panel">
  <?php if (isset($_GET["from"]) && $_GET["from"] === "repartidor"): ?>
    <div id="msg-aviso">⚠️ Antes de ver pedidos disponibles, confirma tu ubicación actual.</div>
  <?php endif; ?>

  <h3>📍 Ajusta tu ubicación</h3>
  <p>Mueve el pin naranja hasta donde estás realmente antes de tomar pedidos.</p>
  <p id="estado">Esperando ubicación...</p>
  <button onclick="guardarUbicacion()">💾 Guardar ubicación</button>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  let lat = 3.3760, lon = -76.5300;
  let map, marker;

  map = L.map("map").setView([lat, lon], 17);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 20,
    attribution: "&copy; OpenStreetMap"
  }).addTo(map);

  marker = L.marker([lat, lon], { draggable: true })
    .addTo(map)
    .bindPopup("📍 Arrástrame hasta tu posición exacta.")
    .openPopup();

  marker.on("dragend", e => {
    const p = e.target.getLatLng();
    lat = p.lat;
    lon = p.lng;
  });

  // 🌍 Obtener ubicación real del GPS si se permite
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      lat = pos.coords.latitude;
      lon = pos.coords.longitude;
      map.setView([lat, lon], 18);
      marker.setLatLng([lat, lon]);
      document.getElementById("estado").innerText = "Ubicación detectada ✅";
    }, () => {
      document.getElementById("estado").innerText = "⚠️ No se pudo obtener la ubicación automáticamente.";
    });
  } else {
    document.getElementById("estado").innerText = "⚠️ Tu navegador no soporta geolocalización.";
  }

  // 💾 Guardar ubicación
  window.guardarUbicacion = async function() {
    document.getElementById("estado").innerText = "Guardando ubicación...";
    const res = await fetch("../api/actualizar_posicion.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ lat, lon })
    });
    const data = await res.json();

    if (data.ok) {
      alert("✅ Ubicación guardada correctamente.");
      const params = new URLSearchParams(window.location.search);
      const from = params.get("from");

      if (from === "repartidor") {
        // 🔁 Regresar al panel del repartidor
        window.location.href = "pedidos_disponibles.php";
      } else {
        window.location.href = "repartidor.php";
      }
    } else {
      alert("❌ Error: " + data.error);
      document.getElementById("estado").innerText = "Error al guardar ubicación";
    }
  };
});
</script>

</body>
</html>
