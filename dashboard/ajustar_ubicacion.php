<?php
require "../includes/auth_check.php";
require_once "../config/db.php";

// Validar sesión
if (!isset($_SESSION["user"]) || $_SESSION["user"]["rol"] !== "usuario") {
  header("Location: ../auth/login.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>📍 Ajustar mi ubicación</title>

<!-- ✅ CDN de Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
html, body, #map {
  height: 100%;
  width: 100%;
  margin: 0;
  padding: 0;
}
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
#panel h3 {
  margin: 0 0 .5rem 0;
  color: #ff0000ff;
  font-size: 1.2rem;
}
#panel p {
  font-size: .9rem;
  color: #444;
}
button {
  background: linear-gradient(135deg, #ff2323ff, #ff0000ff);
  border: none;
  color: #fff;
  padding: .6rem 1.2rem;
  border-radius: 999px;
  cursor: pointer;
  font-weight: 600;
  margin-top: .5rem;
  width: 100%;
}
button:hover {
  background: linear-gradient(135deg, #ff2222ff, #ff0000ff);
}
#msg-aviso {
  background: #fff9d6;
  border-left: 5px solid #ff0000ff;
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
  <?php if (isset($_GET["from"]) && $_GET["from"] === "menu"): ?>
    <div id="msg-aviso">⚠️ Antes de continuar con tu pedido, confirma tu ubicación en el mapa.</div>
  <?php endif; ?>
  <h3>📍 Ajusta tu ubicación</h3>
  <p>Mueve el pin naranja hasta donde te encuentres exactamente dentro del campus.</p>
  <p id="estado">Esperando ubicación...</p>
  <button onclick="guardarUbicacion()">💾 Guardar ubicación</button>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  let lat = 3.3760, lon = -76.5300; // 📍 Coordenadas por defecto (Univalle)
  let map, marker;

  // Inicializar mapa
  map = L.map("map").setView([lat, lon], 17);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 20,
    attribution: "&copy; OpenStreetMap"
  }).addTo(map);

  // Crear marcador draggable
  marker = L.marker([lat, lon], { draggable: true, title: "Arrástrame" })
    .addTo(map)
    .bindPopup("📍 Arrástrame hasta tu posición exacta.")
    .openPopup();

  marker.on("dragend", e => {
    const p = e.target.getLatLng();
    lat = p.lat;
    lon = p.lng;
  });

  // Intentar detectar ubicación real
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      lat = pos.coords.latitude;
      lon = pos.coords.longitude;
      map.setView([lat, lon], 18);
      marker.setLatLng([lat, lon]);
      document.getElementById("estado").innerText = "Ubicación detectada ✅";
    }, () => {
      document.getElementById("estado").innerText = "⚠️ No se pudo obtener ubicación automática.";
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
      const id_comercio = params.get("id_comercio");

      if (from === "menu" && id_comercio) {
        // 🔁 Regresar al menú con confirmación
        window.location.href = `../dashboard/menu.php?id=${id_comercio}&ubicacion_confirmada=1`;
      } else {
        // Si no viene desde un menú, ir al dashboard
        window.location.href = "usuario.php";
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
