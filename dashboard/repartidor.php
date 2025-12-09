<?php
require "../includes/auth_check.php";
require_once "../config/db.php";
include "../includes/header.php";

// ============================
// 1️⃣ Verificar sesión
// ============================
if (!isset($_SESSION["user"]) || $_SESSION["user"]["rol"] !== "repartidor") {
    echo '<div class="auth-message auth-message-error" style="margin:2rem;">⚠️ Acceso no autorizado.</div>';
    include "../includes/footer.php";
    exit;
}

$user = $_SESSION["user"];
$codigo_rep = $user["id"] ?? null;

if (!$codigo_rep) {
    echo '<div class="auth-message auth-message-error" style="margin:2rem;">⚠️ No se encontró el repartidor asociado a tu cuenta.</div>';
    include "../includes/footer.php";
    exit;
}

// ============================
// 2️⃣ Resolver si el id es gid o codigo_rep
// ============================
$stmt = $pdo->prepare('SELECT codigo_rep FROM "Gestion_Usuarios"."repartidores" WHERE codigo_rep = :id');
$stmt->execute([":id" => $codigo_rep]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    $stmt = $pdo->prepare('SELECT codigo_rep FROM "Gestion_Usuarios"."repartidores" WHERE gid = :g');
    $stmt->execute([":g" => $codigo_rep]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$row) {
    echo '<div class="auth-message auth-message-error" style="margin:2rem;">⚠️ No se encontró información del repartidor en la base de datos.</div>';
    include "../includes/footer.php";
    exit;
}

$codigo_rep = $row["codigo_rep"];

// ============================
// 3️⃣ Actualizar disponibilidad/capacidad
// ============================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {
    $disponibilidad = isset($_POST["disponibilidad"]) ? true : false;
    $capacidad = intval($_POST["capacidad"]);

    $stmt = $pdo->prepare('UPDATE "Gestion_Usuarios"."repartidores"
                           SET disponibilidad = :d, capacidad = :c
                           WHERE codigo_rep = :id');
    $stmt->execute([
        ":d" => $disponibilidad,
        ":c" => $capacidad,
        ":id" => $codigo_rep
    ]);

    header("Location: repartidor.php");
    exit;
}

// ============================
// 4️⃣ Obtener información del repartidor
// ============================
$stmt = $pdo->prepare('SELECT nombre, email, disponibilidad, capacidad
                       FROM "Gestion_Usuarios"."repartidores"
                       WHERE codigo_rep = :id');
$stmt->execute([":id" => $codigo_rep]);
$repartidor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$repartidor) {
    echo '<div class="auth-message auth-message-error" style="margin:2rem;">
            ⚠️ No se encontró información del repartidor con código <strong>' . htmlspecialchars($codigo_rep) . '</strong>.
          </div>';
    include "../includes/footer.php";
    exit;
}

// ============================
// 5️⃣ Obtener comercios para el mapa
// ============================
$comercios = $pdo->query('SELECT nombre, ST_X(geom) AS lon, ST_Y(geom) AS lat 
                          FROM "Division_Geografica"."comercio" 
                          WHERE geom IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ============================
     ESTILO Y DISEÑO
     ============================ -->
<style>
.repartidor-dashboard {
    max-width: 900px;
    margin: 0 auto;
    padding: 1.5rem;
}
.repartidor-hero {
    background: linear-gradient(135deg, #b80000ff, #ce0000ff);
    color: #fff;
    padding: 1.5rem;
    border-radius: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 10px 25px rgba(0,0,0,0.25);
}
.status-card {
    background: #fff;
    padding: 1.2rem;
    border-radius: 14px;
    margin-top: 1.5rem;
    box-shadow: 0 5px 18px rgba(0,0,0,0.1);
}
.status-form label { display: block; margin-top: .8rem; font-weight: 500; }
.switch { position: relative; display: inline-block; width: 52px; height: 28px; margin-left: 10px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
          background-color: #ccc; transition: .4s; border-radius: 34px; }
.slider:before { position: absolute; content: ""; height: 20px; width: 20px;
                 left: 4px; bottom: 4px; background-color: white; transition: .4s;
                 border-radius: 50%; }
input:checked + .slider { background-color: #00b894; }
input:checked + .slider:before { transform: translateX(24px); }
#map { height: 420px; border-radius: 14px; margin-top: 1.2rem; box-shadow: 0 6px 18px rgba(0,0,0,0.2); }
</style>

<div class="repartidor-dashboard">
    <div class="repartidor-hero">
        <div>
            <h1>👋 Hola, <?= htmlspecialchars($repartidor["nombre"]) ?></h1>
            <p><?= htmlspecialchars($repartidor["email"]) ?></p>
        </div>
    </div>

    <div class="status-card">
        <h2>🚴 Estado de disponibilidad</h2>
        <form method="POST" class="status-form">
            <label>Disponible
                <label class="switch">
                    <input type="checkbox" name="disponibilidad" <?= $repartidor["disponibilidad"] ? "checked" : "" ?>>
                    <span class="slider"></span>
                </label>
            </label>

            <label>Capacidad (número máximo de objetos)
                <input type="number" name="capacidad" min="1" max="10" value="<?= htmlspecialchars($repartidor["capacidad"] ?? 1) ?>">
            </label>

            <button type="submit" name="update_status">Actualizar estado</button>
        </form>
    </div>

 

    <hr style="margin:2rem 0;border:none;border-top:2px solid #ffb2b2ff;">

    <h2>📍 Pedidos asignados en curso</h2>

<?php
$stmt = $pdo->prepare('SELECT p.id, p.total, p.estado, p.resumen,
                              c.nombre AS comercio, u.nombre AS usuario
                       FROM "Division_Geografica"."pedidos" p
                       JOIN "Division_Geografica"."comercio" c ON p.id_comercio = c.codigo_com
                       JOIN "Gestion_Usuarios"."Usuario" u ON p.id_usuario = u.gid
                       WHERE p.id_repartidor = :r
                       ORDER BY p.fecha DESC');
$stmt->execute([":r" => $codigo_rep]);
$asignados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

  <div id="pedidos-asignados">
    <?php if (empty($asignados)): ?>
        <p>🚫 No tienes pedidos asignados en este momento.</p>
    <?php else: ?>
        <?php foreach ($asignados as $p): ?>
            <div class="card-pedido" style="background:#fff4e0;border-left:5px solid #ff0000ff;padding:1rem;margin-bottom:1rem;border-radius:12px;">
                <h3>🧾 Pedido #<?= $p["id"] ?> — <?= strtoupper($p["estado"]) ?></h3>
                <p><strong>Recoge en:</strong> <?= htmlspecialchars($p["comercio"]) ?></p>
                <p><strong>Entrega a:</strong> <?= htmlspecialchars($p["usuario"]) ?></p>
                <p><?= nl2br(htmlspecialchars($p["resumen"])) ?></p>
                <strong>Total:</strong> $<?= number_format($p["total"], 2) ?><br><br>

                <?php if ($p["estado"] === "en camino"): ?>
                    <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
                        <a href="../rutas/repartidor_ruta.php?id_pedido=<?= $p["id"] ?>" 
                           target="_blank"
                           style="background:linear-gradient(135deg,#007bff,#0056d2);color:#fff;
                           padding:.45rem .9rem;border:none;border-radius:999px;
                           text-decoration:none;font-weight:600;">
                           🗺️ Ver ruta
                        </a>

                        <a href="../pedidos/actualizar_estado.php?id=<?= $p["id"] ?>&estado=entregado" 
                           style="background:linear-gradient(135deg,#ff8f00,#ff6f00);
                           color:#fff;padding:.45rem .9rem;border:none;border-radius:999px;
                           text-decoration:none;font-weight:600;">
                           ✅ Marcar como entregado
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>


    <!-- Botón "Ver pedidos disponibles" -->
<a href="ajustar_ubicacion_repartidor.php?from=repartidor" class="btn" 
   style="margin-top:1rem;background:linear-gradient(135deg,#ff8f00,#ff6f00);
          color:#fff;padding:.6rem 1rem;border-radius:999px;
          text-decoration:none;font-weight:600;">
  📍 Ver pedidos disponibles
</a>

</div>

<!-- ============================
     MAPA LEAFLET
     ============================ -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('map').setView([3.395, -76.55], 16);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

let markerRider = L.marker([3.395, -76.55])
  .addTo(map)
  .bindPopup("🚴 Tu ubicación")
  .openPopup();

if (navigator.geolocation) {
  navigator.geolocation.watchPosition(pos => {
    const { latitude, longitude } = pos.coords;
    markerRider.setLatLng([latitude, longitude]);
    map.setView([latitude, longitude], 17);
  });
}

const comercios = <?= json_encode($comercios) ?>;
comercios.forEach(c => {
  if (c.lat && c.lon) {
    L.marker([c.lat, c.lon])
      .addTo(map)
      .bindPopup(`<strong>${c.nombre}</strong><br>📍 Comercio`);
  }
});
</script>

<?php include "../includes/footer.php"; ?>

</a>
