<?php
require "../includes/auth_check.php";
require_once "../config/db.php";
include "../includes/header.php";

// ============================
// 👤 Validar sesión y rol
// ============================
if (!isset($_SESSION["user"]) || $_SESSION["user"]["rol"] !== "usuario") {
    header("Location: ../auth/login.php");
    exit;
}

$user = $_SESSION["user"];
$id_usuario = $user["id"];
$id_comercio = $_GET["id"] ?? null;

// ============================
// 🚨 Redirigir siempre a ajustar ubicación antes del menú
// ============================
if (!isset($_GET["ubicacion_confirmada"])) {
    header("Location: ../dashboard/ajustar_ubicacion.php?from=menu&id_comercio=" . urlencode($id_comercio));
    exit;
}

// ============================
// 🍔 Validar comercio
// ============================
if (!$id_comercio) {
    echo "<p>⚠️ Comercio no encontrado (sin ID).</p>";
    include "../includes/footer.php";
    exit;
}

// Obtener información del comercio
$stmt = $pdo->prepare('SELECT nombre, imagen, email 
                       FROM "Division_Geografica"."comercio" 
                       WHERE codigo_com = :id OR gid = CAST(:id AS INTEGER)');
$stmt->execute([":id" => $id_comercio]);
$comercio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$comercio) {
    echo "<p>⚠️ Comercio no encontrado en la base de datos.</p>";
    include "../includes/footer.php";
    exit;
}

// Obtener productos
$stmt = $pdo->prepare('SELECT id, nombre, descripcion, precio, imagen 
                       FROM "Division_Geografica"."productos"
                       WHERE codigo_com = :c');
$stmt->execute([":c" => $id_comercio]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!-- ============================
     ESTILOS COMPACTOS EN CUADROS
     ============================ -->
<style>
.page {
    max-width: 1100px;
    margin: 0 auto;
    padding: 1.5rem;
}
.user-hero {
    background: linear-gradient(135deg, #ff0000ff, #940000ff);
    color: white;
    padding: 1.2rem;
    border-radius: 16px;
    text-align: center;
    margin-bottom: 1.5rem;
    box-shadow: 0 8px 18px rgba(0,0,0,0.2);
}
.user-hero h1 {
    margin: 0;
    font-size: 1.6rem;
}
.user-hero p {
    opacity: .9;
    margin-top: .3rem;
}
.user-section {
    margin-top: 1rem;
}
.section-title {
    font-size: 1.4rem;
    color: #333;
    margin-bottom: 1rem;
}
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 1rem;
}
.card-local {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    transition: transform .15s ease, box-shadow .2s ease;
    display: flex;
    flex-direction: column;
}
.card-local:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.15);
}
.card-local img {
    width: 100%;
    height: 160px;
    object-fit: cover;
}
.card-local-content {
    padding: .8rem;
    flex: 1;
}
.card-local-content h3 {
    margin: 0;
    font-size: 1rem;
    color: #222;
}
.card-local-content p {
    font-size: .85rem;
    color: #555;
    margin: .3rem 0;
}
.card-local-content strong {
    color: #ff0000ff;
    font-size: .9rem;
}
label {
    font-size: .8rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
input[type="number"] {
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: .2rem;
    width: 50px;
    text-align: center;
}
textarea {
    border-radius: 8px;
    padding: .6rem;
    border: 1px solid #ccc;
    font-size: .95rem;
    resize: none;
    width: 100%;
    margin-top: 1rem;
}
.btn {
    background: linear-gradient(135deg, #ff0000ff, #9b0000ff);
    color: white;
    padding: .6rem 1.2rem;
    border: none;
    border-radius: 999px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 1rem;
    transition: .2s;
}
.btn:hover {
    background: linear-gradient(135deg, #ff2222ffrgba(255, 0, 0, 1)00);
    transform: translateY(-2px);
}
</style>

<!-- ============================
     CONTENIDO
     ============================ -->
<div class="page user-wrapper">
    <section class="user-hero">
        <h1>🍴 <?= htmlspecialchars($comercio["nombre"]) ?></h1>
        <p><?= htmlspecialchars($comercio["email"]) ?></p>
    </section>

    <section class="user-section">
        <h2 class="section-title">Menú</h2>
        <form method="POST" action="../pedidos/crear_pedido.php">
            <input type="hidden" name="id_comercio" value="<?= $id_comercio ?>">

            <div class="grid">
                <?php foreach ($productos as $p): ?>
                    <div class="card-local">
                        <img src="../public/img/productos/<?= htmlspecialchars($p["imagen"] ?: "default_food.jpg") ?>" alt="Producto">
                        <div class="card-local-content">
                            <h3><?= htmlspecialchars($p["nombre"]) ?></h3>
                            <p><?= htmlspecialchars($p["descripcion"]) ?></p>
                            <strong>$<?= number_format($p["precio"], 2) ?></strong>
                            <label>
                                Cantidad:
                                <input type="number" name="cantidades[<?= $p['id'] ?>]" min="0" max="20" value="0">
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($productos)): ?>
                    <p>🍽️ Este comercio aún no tiene productos registrados.</p>
                <?php endif; ?>
            </div>

            <textarea name="detalle" placeholder="Notas para el pedido (opcional)" rows="3"></textarea>
            <button type="submit" class="btn">🛒 Confirmar pedido</button>
        </form>
    </section>
</div>

<?php include "../includes/footer.php"; ?>
