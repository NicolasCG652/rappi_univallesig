<?php
require "../includes/auth_check.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["rol"] !== "admin") {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/db.php";
include "../includes/header.php";

$msg = "";

/* =========================================
   🔑 Verificación de Súper Administrador
   ========================================= */
$stmt = $pdo->prepare('SELECT es_super_admin FROM "Gestion_Usuarios"."admin" WHERE email = :email');
$stmt->execute([":email" => $_SESSION["user"]["email"]]);
$esSuperAdmin = $stmt->fetchColumn() ? true : false;

/* =========================================
   🧹 ELIMINAR usuarios / repartidores / comercios
   ========================================= */
if (isset($_GET["eliminar"]) && isset($_GET["tipo"])) {
    if (!$esSuperAdmin) {
        $msg = "⚠️ Solo el Súper Administrador puede eliminar registros.";
    } else {
        $tipo = $_GET["tipo"];
        $id = intval($_GET["eliminar"]);
        try {
            switch ($tipo) {
                case "usuario":
                    $pdo->prepare('DELETE FROM "Gestion_Usuarios"."Usuario" WHERE gid = :id')->execute([":id" => $id]);
                    $msg = "✅ Usuario eliminado correctamente.";
                    break;
                case "repartidor":
                    $pdo->prepare('DELETE FROM "Gestion_Usuarios"."repartidores" WHERE gid = :id')->execute([":id" => $id]);
                    $msg = "✅ Repartidor eliminado correctamente.";
                    break;
                case "comercio":
                    $pdo->prepare('DELETE FROM "Division_Geografica"."comercio" WHERE gid = :id')->execute([":id" => $id]);
                    $msg = "✅ Comercio eliminado correctamente.";
                    break;
            }
        } catch (Exception $e) {
            $msg = "❌ Error al eliminar: " . $e->getMessage();
        }
    }
}

/* =========================================
   🆙 ASCENDER usuario a ADMIN (Migración)
   ========================================= */
if (isset($_POST["ascender_id"])) {
    if (!$esSuperAdmin) {
        $msg = "⚠️ Solo el Súper Administrador puede ascender usuarios.";
    } else {
        $idUsuario = intval($_POST["ascender_id"]);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT nombre, email, password_hash FROM "Gestion_Usuarios"."Usuario" WHERE gid = :id');
            $stmt->execute([":id" => $idUsuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $pdo->prepare('INSERT INTO "Gestion_Usuarios"."admin" (nombre, email, password_hash, es_super_admin)
                               VALUES (:n, :e, :p, FALSE)')
                    ->execute([
                        ":n" => $user["nombre"],
                        ":e" => $user["email"],
                        ":p" => $user["password_hash"]
                    ]);
                $pdo->prepare('DELETE FROM "Gestion_Usuarios"."Usuario" WHERE gid = :id')->execute([":id" => $idUsuario]);
                $pdo->commit();
                $msg = "✅ Usuario ascendido correctamente.";
            } else {
                $msg = "⚠️ Usuario no encontrado.";
                $pdo->rollBack();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "❌ Error al ascender: " . $e->getMessage();
        }
    }
}

/* =========================================
   ⬇️ DEGRADAR admin a USUARIO (Migración)
   ========================================= */
if (isset($_GET["degradar_id"])) {
    if (!$esSuperAdmin) {
        $msg = "⚠️ Solo el Súper Administrador puede degradar administradores.";
    } else {
        $idAdmin = intval($_GET["degradar_id"]);
        $pdo->beginTransaction();
        try {
            // 👇 Aquí usamos id (no gid)
            $stmt = $pdo->prepare('SELECT id, nombre, email, password_hash, es_super_admin FROM "Gestion_Usuarios"."admin" WHERE id = :id');
            $stmt->execute([":id" => $idAdmin]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin) {
                if ($admin["es_super_admin"]) {
                    $msg = "⚠️ No puedes degradar a otro Súper Administrador.";
                    $pdo->rollBack();
                } else {
                    // Insertar en usuarios
                    $pdo->prepare('INSERT INTO "Gestion_Usuarios"."Usuario" (nombre, email, password_hash)
                                   VALUES (:n, :e, :p)')
                        ->execute([
                            ":n" => $admin["nombre"],
                            ":e" => $admin["email"],
                            ":p" => $admin["password_hash"]
                        ]);

                    // 👇 También usamos id aquí, no gid
                    $pdo->prepare('DELETE FROM "Gestion_Usuarios"."admin" WHERE id = :id')
                        ->execute([":id" => $idAdmin]);

                    $pdo->commit();
                    $msg = "✅ Administrador degradado correctamente.";
                }
            } else {
                $msg = "⚠️ Administrador no encontrado.";
                $pdo->rollBack();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "❌ Error al degradar: " . $e->getMessage();
        }
    }
}

/* =========================================
   📊 CONSULTAS ESTADÍSTICAS
   ========================================= */
$usuariosTotal = $pdo->query('SELECT COUNT(*) FROM "Gestion_Usuarios"."Usuario"')->fetchColumn();
$repartidoresTotal = $pdo->query('SELECT COUNT(*) FROM "Gestion_Usuarios"."repartidores"')->fetchColumn();
$comerciosTotal = $pdo->query('SELECT COUNT(*) FROM "Division_Geografica"."comercio"')->fetchColumn();
$pedidosTotal = $pdo->query('SELECT COUNT(*) FROM "Division_Geografica"."pedidos"')->fetchColumn();

$ultimosUsuarios = $pdo->query('SELECT gid, nombre, email FROM "Gestion_Usuarios"."Usuario" ORDER BY gid DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
$ultimosComercios = $pdo->query('SELECT gid, nombre, email FROM "Division_Geografica"."comercio" ORDER BY gid DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-wrapper">

    <!-- HERO -->
    <section class="admin-hero">
        <div>
            <h1>⚙️ Panel de Administración</h1>
            <p>Controla usuarios, comercios, repartidores y pedidos del sistema Delivery Univalle.</p>
            <a href="../geovisor/index.html" class="btn">🗺️ Ver Geovisor</a>
        </div>
        <div class="admin-hero-badge">
            <span>Rol actual:</span><br>
            <strong><?= $esSuperAdmin ? "Súper Administrador" : "Administrador" ?></strong>
        </div>
    </section>

    <?php if ($msg): ?>
        <div class="msg-box <?= str_starts_with($msg, '✅') ? 'msg-ok' : 'msg-err' ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <!-- RESUMEN -->
    <section class="admin-section">
        <h2 class="section-title">Resumen general</h2>
        <div class="admin-grid">
            <div class="stat-card stat-users"><div class="stat-icon">👥</div><div><h3>Usuarios</h3><p class="stat-number"><?= $usuariosTotal ?></p></div></div>
            <div class="stat-card stat-riders"><div class="stat-icon">🚴</div><div><h3>Repartidores</h3><p class="stat-number"><?= $repartidoresTotal ?></p></div></div>
            <div class="stat-card stat-stores"><div class="stat-icon">🏪</div><div><h3>Comercios</h3><p class="stat-number"><?= $comerciosTotal ?></p></div></div>
            <div class="stat-card stat-orders"><div class="stat-icon">📦</div><div><h3>Pedidos</h3><p class="stat-number"><?= $pedidosTotal ?></p></div></div>
        </div>
    </section>

    <!-- ACTIVIDAD RECIENTE -->
    <section class="admin-section">
        <h2 class="section-title">Actividad reciente</h2>
        <div class="admin-grid">
            <div class="panel-card">
                <h3>🧑‍🎓 Últimos usuarios</h3>
                <table class="admin-table">
                    <thead><tr><th>ID</th><th>Nombre</th><th>Email</th></tr></thead>
                    <tbody>
                        <?php foreach ($ultimosUsuarios as $u): ?>
                            <tr><td><?= $u["gid"] ?></td><td><?= htmlspecialchars($u["nombre"]) ?></td><td><?= htmlspecialchars($u["email"]) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="panel-card">
                <h3>🏪 Últimos comercios</h3>
                <table class="admin-table">
                    <thead><tr><th>ID</th><th>Nombre</th><th>Email</th></tr></thead>
                    <tbody>
                        <?php foreach ($ultimosComercios as $c): ?>
                            <tr><td><?= $c["gid"] ?></td><td><?= htmlspecialchars($c["nombre"]) ?></td><td><?= htmlspecialchars($c["email"]) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- GESTIÓN DE REGISTROS -->
    <section class="admin-section">
        <h2 class="section-title">Gestión de registros</h2>
        <div class="panel-card">

            <h3>Usuarios</h3>
            <table class="admin-table">
                <thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php
                    $usuarios = $pdo->query('SELECT gid, nombre, email FROM "Gestion_Usuarios"."Usuario" ORDER BY gid DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($usuarios as $u): ?>
                        <tr>
                            <td><?= $u["gid"] ?></td>
                            <td><?= htmlspecialchars($u["nombre"]) ?></td>
                            <td><?= htmlspecialchars($u["email"]) ?></td>
                            <td>
                                <?php if ($esSuperAdmin): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="ascender_id" value="<?= $u["gid"] ?>">
                                        <button class="btn-small" type="submit">⬆️ Hacer admin</button>
                                    </form>
                                    <a href="?eliminar=<?= $u["gid"] ?>&tipo=usuario" class="btn-small btn-delete" onclick="return confirm('¿Eliminar este usuario?');">🗑️ Eliminar</a>
                                <?php else: ?>
                                    <span class="btn-small btn-disabled">👁 Solo lectura</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>


            <h3>Repartidores</h3>
            <table class="admin-table">
                <thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php
                    $reps = $pdo->query('SELECT gid, nombre, email FROM "Gestion_Usuarios"."repartidores" ORDER BY gid DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($reps as $r): ?>
                        <tr>
                            <td><?= $r["gid"] ?></td>
                            <td><?= htmlspecialchars($r["nombre"]) ?></td>
                            <td><?= htmlspecialchars($r["email"]) ?></td>
                            <td>
                                <?php if ($esSuperAdmin): ?>
                                    <a href="?eliminar=<?= $r["gid"] ?>&tipo=repartidor" class="btn-small btn-delete" onclick="return confirm('¿Eliminar este repartidor?');">🗑️ Eliminar</a>
                                <?php else: ?>
                                    <span class="btn-small btn-disabled">👁 Solo lectura</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <hr>

            <h3>Comercios</h3>
            <table class="admin-table">
                <thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php
                    $coms = $pdo->query('SELECT gid, nombre, email FROM "Division_Geografica"."comercio" ORDER BY gid DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($coms as $c): ?>
                        <tr>
                            <td><?= $c["gid"] ?></td>
                            <td><?= htmlspecialchars($c["nombre"]) ?></td>
                            <td><?= htmlspecialchars($c["email"]) ?></td>
                            <td>
                                <?php if ($esSuperAdmin): ?>
                                    <a href="?eliminar=<?= $c["gid"] ?>&tipo=comercio" class="btn-small btn-delete" onclick="return confirm('¿Eliminar este comercio?');">🗑️ Eliminar</a>
                                <?php else: ?>
                                    <span class="btn-small btn-disabled">👁 Solo lectura</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<hr>

            <h3>Administradores</h3>
            <table class="admin-table">
                <thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Tipo</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php
                    $admins = $pdo->query('SELECT id, nombre, email, es_super_admin FROM "Gestion_Usuarios"."admin" ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($admins as $a): ?>
                        <tr>
                            <td><?= $a["id"] ?></td>
                            <td><?= htmlspecialchars($a["nombre"]) ?></td>
                            <td><?= htmlspecialchars($a["email"]) ?></td>
                            <td><?= $a["es_super_admin"] ? "👑 Súper Admin" : "Admin" ?></td>
                            <td>
                                <?php if ($esSuperAdmin && !$a["es_super_admin"]): ?>
                                    <a href="?degradar_id=<?= $a["id"] ?>" class="btn-small btn-degrade" onclick="return confirm('¿Degradar a este admin a usuario?');">⬇️ Quitar Admin</a>
                                <?php else: ?>
                                    <span class="btn-small btn-disabled">👁 Sin permiso</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <hr>

<?php include "../includes/footer.php"; ?>
