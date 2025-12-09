<?php
require "../includes/auth_check.php";
require_once "../config/db.php";

// Solo permitir acceso a comercios
if (!isset($_SESSION["user"]) || $_SESSION["user"]["rol"] !== "comercio") {
  header("Location: ../auth/login.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>📍 Ajustar mi ubicación - Comercio</title>

<!-- Leaflet (mapa base) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
html, body { height: 100%; margin: 0; padding: 0; }
#map { height: 100%; width: 100%; }
#panel {
  position: absolute; top: 10px; left: 10px; z-index: 999;
  background: rgba(255,255,255,0.97);
  padding: 1rem;
  border-radius: 12px;
  box-shadow: 0 3px 6px rgba(0,0,0,0.3);
  width: 300px;
  font-family: 'Segoe UI', sans-serif;
}
#panel h3 { margin: 0 0 .5rem 0; color: #ff6f00; font-size: 1.2rem; }
#panel p { font-size: .9rem; color: #444; }
button {
  background: linear-gradient(135deg, #ff8f00, #ff6f00);
  border: none; color: #fff; padding: .6rem 1.2rem;
  border-radius: 999px; cursor: pointer;
  font-weight: 600; margin-top: .5rem; width: 100%;
}
button:hover { background: linear-gradient(135deg, #ff9f22, #ff7f00); }
</style>
</head>

<body>
<div id="map"></div>
<div id="panel">
  <h3>📍 Ajustar ubicación del comercio</h3>
  <p>Arrastra el marcador naranja hasta la posición exacta de tu local.</p>
  <p id="estado">Esperando ubicación...</p>
  <button onclick="guardarUbicacion()">💾 Guardar ubicación</button>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  let lat = 3.3760, lon = -76.5300; // Coordenadas base Univalle
  let map, marker;

  // Inicializa el mapa
  map = L.map("map").setView([lat, lon], 17);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 20,
    attribution: "&copy; OpenStreetMap"
  }).addTo(map);

  // Crea el marcador draggable
  marker = L.marker([lat, lon], { draggable: true })
    .addTo(map)
    .bindPopup("📍 Arrástrame hasta la ubicación real de tu comercio.")
    .openPopup();

  marker.on("dragend", e => {
    const p = e.target.getLatLng();
    lat = p.lat;
    lon = p.lng;
  });

  // Intentar obtener ubicación GPS actual
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
      alert("✅ Ubicación guardada correctamente");
      window.location.href = "comercio.php";
    } else {
      alert("❌ Error: " + data.error);
      document.getElementById("estado").innerText = "Error al guardar ubicación";
    }
  };
});
</script>
</body>
</html>
