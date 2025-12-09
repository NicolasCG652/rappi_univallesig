<?php
require_once "../config/db.php";
session_start();

$msg = "";
$msgType = ""; // success | error

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $identificador = trim($_POST["email"]);
    $pass  = $_POST["password"];

// =======================
// 🔒 ADMIN DESDE BD
// =======================
$sqlAdmin = 'SELECT id, nombre, email, password_hash 
             FROM "Gestion_Usuarios"."admin"
             WHERE LOWER(email) = LOWER(:id) OR LOWER(nombre) = LOWER(:id)';
$stmtAdmin = $pdo->prepare($sqlAdmin);
$stmtAdmin->execute([":id" => $identificador]);
$admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

if ($admin && password_verify($pass, $admin["password_hash"])) {
    $_SESSION["user"] = [
        "id"     => $admin["id"],
        "nombre" => $admin["nombre"],
        "email"  => $admin["email"],
        "rol"    => "admin"
    ];
    header("Location: ../dashboard/admin.php");
    exit;
}


    // Definir roles y tablas
    $roles = [
        "usuario"    => [
            "tabla" => '"Gestion_Usuarios"."Usuario"',
            "idcol" => "gid"
        ],
        "repartidor" => [
            "tabla" => '"Gestion_Usuarios"."repartidores"',
            "idcol" => "gid"
        ],
        "comercio"   => [
            "tabla" => '"Division_Geografica"."comercio"',
            "idcol" => "codigo_com"
        ]
    ];

    $encontrado = false;

    foreach ($roles as $rol => $info) {
        $tabla = $info["tabla"];
        $idcol = $info["idcol"];

        $sql = "SELECT $idcol AS id, nombre, email, password_hash 
                FROM $tabla 
                WHERE LOWER(email) = LOWER(:id) OR LOWER(nombre) = LOWER(:id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":id" => $identificador]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // 1️⃣ Hash válido
            if (!empty($user["password_hash"]) && password_verify($pass, $user["password_hash"])) {
                $_SESSION["user"] = [
                    "id"     => $user["id"],
                    "nombre" => $user["nombre"],
                    "email"  => $user["email"],
                    "rol"    => $rol
                ];
                header("Location: ../dashboard/$rol.php");
                exit;
            }

            // 2️⃣ Contraseña antigua en texto plano
            if ($user["password_hash"] === $pass) {
                $newHash = password_hash($pass, PASSWORD_BCRYPT);
                $update = "UPDATE $tabla SET password_hash = :h WHERE $idcol = :i";
                $pdo->prepare($update)->execute([":h" => $newHash, ":i" => $user["id"]]);

                $_SESSION["user"] = [
                    "id"     => $user["id"],
                    "nombre" => $user["nombre"],
                    "email"  => $user["email"],
                    "rol"    => $rol
                ];
                header("Location: ../dashboard/$rol.php");
                exit;
            }
        }
    }

    $msg = "⚠️ Credenciales inválidas. Verifica tu usuario/email y contraseña.";
    $msgType = "error";
}
?>

<?php include "../includes/header.php"; ?>

<!-- ======================= ESTILOS MEJORADOS ======================= -->
<style>
body {
    background: #f6f7fb;
}
.page {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 85vh;
    padding: 2rem;
}
.auth-card {
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    max-width: 400px;
    width: 100%;
    text-align: center;
    padding: 2rem;
    transition: transform .2s ease;
}
.auth-card:hover {
    transform: translateY(-4px);
}
.auth-header {
    margin-bottom: 1.2rem;
}
.auth-icon {
    font-size: 2.5rem;
    margin-bottom: .5rem;
}
.auth-title {
    font-size: 1.6rem;
    margin: 0;
    color: #ff0000ff;
    font-weight: 700;
}
.auth-subtitle {
    color: #666;
    font-size: .9rem;
    margin-top: .4rem;
}
.form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    text-align: left;
}
.form label {
    font-weight: 600;
    color: #444;
    font-size: .9rem;
}
.form input {
    width: 100%;
    padding: .7rem;
    border-radius: 10px;
    border: 1px solid #ddd;
    font-size: .95rem;
    margin-top: .3rem;
    transition: border .2s;
}
.form input:focus {
    outline: none;
    border-color: #ff0000ff;
    box-shadow: 0 0 0 2px rgba(255,140,0,0.2);
}
button[type="submit"] {
    background: linear-gradient(135deg, #ff0000ff, #ff0000ff);
    color: #fff;
    border: none;
    padding: .8rem;
    border-radius: 999px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s;
}
button[type="submit"]:hover {
    background: linear-gradient(135deg, #fa4242ff, #ff0000ff);
}
.auth-message {
    margin-bottom: 1rem;
    padding: .8rem;
    border-radius: 10px;
    font-size: .9rem;
    font-weight: 500;
}
.auth-message-error {
    background: #ffe6e6;
    color: #b00020;
    border: 1px solid #f5b5b5;
}
.auth-message-success {
    background: #e8f5e9;
    color: #2e7d32;
    border: 1px solid #c8e6c9;
}
.auth-footer {
    margin-top: 1.5rem;
    font-size: .9rem;
}
.auth-footer a {
    color: #ff0000ff;
    font-weight: 600;
    text-decoration: none;
}
.auth-footer a:hover {
    text-decoration: underline;
}
.auth-note {
    margin-top: 1.2rem;
    font-size: .8rem;
    color: #777;
}
</style>

<!-- ======================= CONTENIDO LOGIN ======================= -->
<div class="page">
    <div class="card auth-card">
        <div class="auth-header">
            <div class="auth-icon">🍔</div>
            <h2 class="auth-title">Iniciar sesión</h2>
            <p class="auth-subtitle">
                Ingresa con tu <strong>usuario o correo</strong> para acceder a Delivery Univalle.
            </p>
        </div>

        <?php if ($msg): ?>
            <div class="auth-message <?= $msgType === 'error' ? 'auth-message-error' : 'auth-message-success' ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="form">
            <label>Usuario o Email
                <input type="text" name="email" placeholder="ej: juan23 o juan@mail.com" required>
            </label>

            <label>Contraseña
                <input type="password" name="password" placeholder="Tu contraseña" required>
            </label>

            <button type="submit">🔐 Entrar</button>
        </form>

        <div class="auth-footer">
            <p>¿No tienes cuenta?</p>
            <a href="register.php">Crear una cuenta</a>
        </div>

        <p class="auth-note">
            Accesos válidos: <strong>usuario</strong>, <strong>repartidor</strong> y <strong>comercio</strong> .
        </p>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
