<?php
require "../includes/auth_check.php";
require_once "../config/db.php";
include "../includes/header.php";

// ============================
// 1️⃣ Verificar sesión
// ============================
if (!isset($_SESSION["user"]) || $_SESSION["user"]["rol"] !== "comercio") {
    echo '<div class="auth-message auth-message-error" style="margin:2rem;">⚠️ Acceso no autorizado.</div>';
    include "../includes/footer.php";
    exit;
}

$user = $_SESSION["user"];
$codigo_com = $user["id"] ?? null;

// ============================
// 2️⃣ Resolver código del comercio
// ============================
if ($codigo_com) {
    $stmt = $pdo->prepare('SELECT codigo_com FROM "Division_Geografica"."comercio" WHERE codigo_com = :id');
    $stmt->execute([":id" => $codigo_com]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare('SELECT codigo_com FROM "Division_Geografica"."comercio" WHERE gid = :g');
        $stmt->execute([":g" => $codigo_com]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $codigo_com = $row["codigo_com"];
        }
    }
}

if (!$codigo_com) {
    echo '<div class="auth-message auth-message-error" style="margin:2rem;">⚠️ No se encontró el comercio asociado.</div>';
    include "../includes/footer.php";
    exit;
}

// ============================
// 3️⃣ Obtener datos del comercio
// ============================
$stmt = $pdo->prepare('SELECT codigo_com, nombre, email, imagen 
                       FROM "Division_Geografica"."comercio" 
                       WHERE codigo_com = :id');
$stmt->execute([":id" => $codigo_com]);
$comercio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$comercio) {
    echo '<div class="auth-message auth-message-error" style="margin:2rem;text-align:center;">
            ⚠️ No se encontró información del comercio.
          </div>';
    include "../includes/footer.php";
    exit;
}

// ============================
// 4️⃣ Actualizar datos
// ============================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_info"])) {
    $nombre = trim($_POST["nombre"]);
    $imagen = $comercio["imagen"];

    if (!empty($_FILES["imagen"]["name"])) {
        $filename = uniqid() . "_" . basename($_FILES["imagen"]["name"]);
        $target = "../public/img/comercios/" . $filename;
        if (move_uploaded_file($_FILES["imagen"]["tmp_name"], $target)) {
            $imagen = $filename;
        }
    }

    $stmt = $pdo->prepare('UPDATE "Division_Geografica"."comercio" 
                           SET nombre = :n, imagen = :i WHERE codigo_com = :id');
    $stmt->execute([":n" => $nombre, ":i" => $imagen, ":id" => $codigo_com]);
    header("Location: comercio.php");
    exit;
}

// ============================
// 5️⃣ Agregar producto
// ============================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_product"])) {
    $nombre_prod = trim($_POST["nombre_prod"]);
    $descripcion = trim($_POST["descripcion"]);
    $precio = floatval($_POST["precio"]);
    $img = "";

    if (!empty($_FILES["imagen_prod"]["name"])) {
        $filename = uniqid() . "_" . basename($_FILES["imagen_prod"]["name"]);
        $target = "../public/img/productos/" . $filename;
        if (move_uploaded_file($_FILES["imagen_prod"]["tmp_name"], $target)) {
            $img = $filename;
        }
    }

    $stmt = $pdo->prepare('INSERT INTO "Division_Geografica"."productos" 
                           (codigo_com, nombre, descripcion, precio, imagen) 
                           VALUES (:c, :n, :d, :p, :i)');
    $stmt->execute([
        ":c" => $codigo_com,
        ":n" => $nombre_prod,
        ":d" => $descripcion,
        ":p" => $precio,
        ":i" => $img
    ]);
    header("Location: comercio.php");
    exit;
}

// ============================
// 6️⃣ Eliminar producto
// ============================
if (isset($_GET["delete"])) {
    $id = intval($_GET["delete"]);
    $pdo->prepare('DELETE FROM "Division_Geografica"."productos" WHERE id = :id')->execute([":id" => $id]);
    header("Location: comercio.php");
    exit;
}

// ============================
// 7️⃣ Obtener productos
// ============================
$stmt = $pdo->prepare('SELECT * FROM "Division_Geografica"."productos" 
                       WHERE codigo_com = :c ORDER BY id DESC');
$stmt->execute([":c" => $codigo_com]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ============================
     DASHBOARD DE COMERCIO
     ============================ -->
<style>
.dashboard-comercio {
    max-width: 1100px;
    margin: 0 auto;
    padding: 1.5rem;
}

/* HERO */
.hero-comercio {
    background: linear-gradient(135deg, #ac0000ff, #ff0000ff);
    color: #fff;
    padding: 2rem 1.5rem;
    border-radius: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 8px 25px rgba(255,111,0,0.3);
}
.hero-comercio img {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #fff;
}
.hero-text h1 {
    margin: 0;
    font-size: 1.6rem;
}
.hero-text p {
    margin: 0.2rem 0 0;
    opacity: 0.9;
}

/* SECCIONES */
.section {
    margin-top: 2rem;
}
.section h2 {
    font-size: 1.3rem;
    color: #333;
    margin-bottom: 0.8rem;
}

/* FORMULARIOS */
form {
    background: #fff;
    padding: 1.2rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}
form label {
    display: flex;
    flex-direction: column;
    font-size: 0.9rem;
    color: #333;
}
form input[type="text"],
form input[type="number"],
form input[type="file"] {
    padding: 0.55rem;
    border-radius: 8px;
    border: 1px solid #ddd;
}
form button {
    align-self: flex-start;
}

/* PRODUCTOS */
.grid-prod {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
}
.card-prod {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform 0.15s, box-shadow 0.2s;
}
.card-prod:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.15);
}
.card-prod img {
    width: 100%;
    height: 160px;
    object-fit: cover;
}
.card-content {
    padding: 0.9rem;
}
.card-content h3 {
    margin: 0;
    font-size: 1rem;
}
.card-content p {
    margin: 0.3rem 0;
    font-size: 0.85rem;
    color: #555;
}
.card-content strong {
    color: #ff0000ff;
}
.card-content .actions {
    margin-top: 0.5rem;
}

/* BOTÓN FLOTANTE */
.btn-floating {
    position: fixed;
    bottom: 25px;
    right: 25px;
    background: #ff0000ff;
    color: #fff;
    border-radius: 50%;
    width: 58px;
    height: 58px;
    font-size: 1.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 20px rgba(0,0,0,0.3);
    transition: transform .2s, box-shadow .2s;
    text-decoration: none;
}
.btn-floating:hover {
    transform: scale(1.08);
    box-shadow: 0 10px 25px rgba(0,0,0,0.35);
}
</style>

<div class="dashboard-comercio">
    <div class="hero-comercio">
        <div class="hero-text">
            <h1><?= htmlspecialchars($comercio["nombre"]) ?></h1>
            <p><?= htmlspecialchars($comercio["email"]) ?></p>
        </div>
        <img src="../public/img/comercios/<?= htmlspecialchars($comercio["imagen"] ?: 'default_food.jpg') ?>" alt="Comercio">
    </div>

    <div class="section">
        <h2>Actualizar información</h2>
        <form method="POST" enctype="multipart/form-data">
            <label>Nombre del comercio
                <input type="text" name="nombre" value="<?= htmlspecialchars($comercio["nombre"]) ?>">
            </label>
            <label>Imagen del comercio
                <input type="file" name="imagen">
            </label>
            <button type="submit" name="update_info">Guardar cambios</button>
        </form>
    </div>

    <div class="section">
        <h2>Mis productos</h2>
        <form method="POST" enctype="multipart/form-data">
            <label>Nombre
                <input type="text" name="nombre_prod" required>
            </label>
            <label>Descripción
                <input type="text" name="descripcion" required>
            </label>
            <label>Precio ($)
                <input type="number" step="0.01" name="precio" required>
            </label>
            <label>Imagen del producto
                <input type="file" name="imagen_prod">
            </label>
            <button type="submit" name="add_product">Agregar producto</button>
        </form>

        <div class="grid-prod">
            <?php foreach ($productos as $p): ?>
                <div class="card-prod">
                    <img src="../public/img/productos/<?= htmlspecialchars($p["imagen"] ?: 'default_food.jpg') ?>" alt="Producto">
                    <div class="card-content">
                        <h3><?= htmlspecialchars($p["nombre"]) ?></h3>
                        <p><?= htmlspecialchars($p["descripcion"]) ?></p>
                        <strong>$<?= number_format($p["precio"], 2) ?></strong>
                        <div class="actions">
                            <a href="?delete=<?= $p["id"] ?>" class="btn small" style="background:#dc2626;">Eliminar</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($productos)): ?>
                <p style="text-align:center;color:#555;">Aún no has agregado productos.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<a href="#top" class="btn-floating" title="Agregar producto">+</a>

<?php include "../includes/footer.php"; ?>
<hr style="margin:2rem 0;border:none;border-top:2px solid #ffe0b2;">
<?php
$rol = $_SESSION["user"]["rol"];
if ($rol === "usuario") include "usuario_pedidos.php";
elseif ($rol === "comercio") include "comercio_pedidos.php";
elseif ($rol === "repartidor") include "repartidor_pedidos.php";
?>
<a href="ajustar_ubicacion_comercio.php"
   style="background:linear-gradient(135deg,#ff8f00,#ff6f00);color:#fff;
          padding:.5rem 1rem;border-radius:999px;text-decoration:none;
          font-weight:600;">
📍 Ajustar mi ubicación
</a>
