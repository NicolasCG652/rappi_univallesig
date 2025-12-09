<?php
session_start();

if (!isset($_SESSION["user"])) {
    header("Location: /rappi_univallesig/auth/login.php");
    exit;
}

$rol = $_SESSION["user"]["rol"];
header("Location: /rappi_univallesig/dashboard/$rol.php");
exit;