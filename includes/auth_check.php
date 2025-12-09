<?php
if (session_status() === PHP_SESSION_NONE) 
    session_start();
if (!isset($_SESSION["user"])) {
    header("Location: /rappi_univallesig/auth/login.php");
    exit;
}
