<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user = $_SESSION["user"] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Delivery univalle</title>
<link rel="stylesheet" href="/rappi_univallesig/public/css/styles.css">
</head>
<body>

<header class="topbar">
    <div class="logo">Delivery Univalle</div>
    <nav>
      <?php if ($user): ?>
        <span><?= htmlspecialchars($user["nombre"]) ?> (<?= $user["rol"] ?>)</span>
        <a href="/rappi_univallesig/dashboard/<?= $user["rol"] ?>.php">Inicio</a>
        <a href="/rappi_univallesig/auth/logout.php">Salir</a>
      <?php else: ?>
        <a href="/rappi_univallesig/auth/login.php">Login</a>
        <a href="/rappi_univallesig/auth/register.php">Registrarse</a>
      <?php endif; ?>
    </nav>
</header>

<main class="main">
