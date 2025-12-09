<?php
require_once "../config/db.php";
session_start();

$msg = "";
$msgType = ""; // success | error

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"]);
    $email  = trim($_POST["email"]);
    $pass   = $_POST["password"];
    $rol    = $_POST["rol"];

    if ($nombre === "" || $email === "" || $pass === "") {
        $msg = "⚠️ Todos los campos son obligatorios.";
        $msgType = "error";
    } else {
        $hash = password_hash($pass, PASSWORD_BCRYPT);

        try {
            if ($rol === "usuario") {
                $sql = 'INSERT INTO "Gestion_Usuarios"."Usuario"
                        (nombre, email, password_hash)
                        VALUES (:n, :e, :p)';
                $params = [":n" => $nombre, ":e" => $email, ":p" => $hash];

            } elseif ($rol === "repartidor") {
                do {
                    $codigo_rep = random_int(10000, 99999);
                    $stmtCheck = $pdo->prepare('SELECT 1 FROM "Gestion_Usuarios"."repartidores" WHERE codigo_rep = :c');
                    $stmtCheck->execute([":c" => $codigo_rep]);
                    $existe = $stmtCheck->fetchColumn();
                } while ($existe);

                $sql = 'INSERT INTO "Gestion_Usuarios"."Usuario"
        (nombre, email, password_hash, geom)
        VALUES (:n, :e, :p, ST_SetSRID(ST_MakePoint(-76.5300, 3.3740), 4326))';

                $params = [
                    ":c" => $codigo_rep,
                    ":n" => $nombre,
                    ":e" => $email,
                    ":p" => $hash
                ];

            } elseif ($rol === "comercio") {
                do {
                    $codigo_com = random_int(1000, 9999);
                    $stmtCheck = $pdo->prepare('SELECT 1 FROM "Division_Geografica"."comercio" WHERE codigo_com = :c');
                    $stmtCheck->execute([":c" => $codigo_com]);
                    $existe = $stmtCheck->fetchColumn();
                } while ($existe);

                $sql = 'INSERT INTO "Division_Geografica"."comercio"
                        (codigo_com, nombre, email, password_hash)
                        VALUES (:c, :n, :e, :p)';
                $params = [
                    ":c" => $codigo_com,
                    ":n" => $nombre,
                    ":e" => $email,
                    ":p" => $hash
                ];
            } else {
                throw new Exception("Rol inválido.");
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $msg = "✅ Registro exitoso. Ya puedes iniciar sesión.";
            $msgType = "success";

        } catch (Exception $e) {
            $msg = "❌ Error: " . $e->getMessage();
            $msgType = "error";
        }
    }
}
?>

<?php include "../includes/header.php"; ?>

<!-- ======================= ESTILOS VISUALES ======================= -->
<style>
body {
    background: #f6f7fb;
}
.page {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 90vh;
    padding: 2rem;
}
.auth-card {
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    max-width: 420px;
    width: 100%;
    text-align: center;
    padding: 2rem;
    transition: transform .2s ease;
}
.auth-card:hover {
    transform: translateY(-4px);
}
.auth-header {
    margin-bottom: 1.5rem;
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
.form input, .form select {
    width: 100%;
    padding: .7rem;
    border-radius: 10px;
    border: 1px solid #ddd;
    font-size: .95rem;
    margin-top: .3rem;
    transition: border .2s;
}
.form input:focus, .form select:focus {
    outline: none;
    border-color: #ff0000ff;
    box-shadow: 0 0 0 2px rgba(255,140,0,0.2);
}
button[type="submit"] {
    background: linear-gradient(135deg, #ff0000ffrgba(255, 0, 0, 1)00);
    color: #fff;
    border: none;
    padding: .8rem;
    border-radius: 999px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s;
}
button[type="submit"]:hover {
    background: linear-gradient(135deg, #ff2222ff, #ff0000ff);
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

<!-- ======================= CONTENIDO REGISTER ======================= -->
<div class="page">
    <div class="card auth-card">

        <div class="auth-header">
            <div class="auth-icon">📝</div>
            <h2 class="auth-title">Crear cuenta</h2>
            <p class="auth-subtitle">
                Regístrate como <strong>usuario</strong>, <strong>repartidor</strong> o <strong>comercio</strong> para usar la plataforma.
            </p>
        </div>

        <?php if ($msg): ?>
            <div class="auth-message <?= $msgType === 'error' ? 'auth-message-error' : 'auth-message-success' ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="form">
            <label>Nombre
                <input type="text" name="nombre" placeholder="Tu nombre completo" required>
            </label>

            <label>Email
                <input type="email" name="email" placeholder="tucorreo@ejemplo.com" required>
            </label>

            <label>Rol
                <select name="rol" required>
                    <option value="">Selecciona tu rol</option>
                    <option value="usuario">👤 Usuario (estudiante o visitante)</option>
                    <option value="repartidor">🚴 Repartidor</option>
                    <option value="comercio">🏪 Comercio</option>
                </select>
            </label>

            <label>Contraseña
                <input type="password" name="password" placeholder="Crea una contraseña segura" required>
            </label>

            <button type="submit">✅ Registrarme</button>
        </form>

        <div class="auth-footer">
            <p>¿Ya tienes cuenta?</p>
            <a href="login.php" class="auth-link">Iniciar sesión</a>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
