<?php
require "../includes/auth_check.php";
require_once "../config/db.php";

if (!isset($_SESSION["user"]) || $_SESSION["user"]["rol"] !== "usuario") {
    header("Location: ../auth/login.php");
    exit;
}

$id_usuario = $_SESSION["user"]["id"];
$id_comercio = $_POST["id_comercio"] ?? null;
$cantidades = $_POST["cantidades"] ?? [];
$detalle = trim($_POST["detalle"] ?? "");

if (!$id_comercio || empty($cantidades)) {
    echo "<p>⚠️ No se seleccionó ningún producto o comercio.</p>";
    exit;
}

// Filtrar los productos con cantidad > 0
$productos_seleccionados = array_filter($cantidades, fn($cant) => $cant > 0);

if (empty($productos_seleccionados)) {
    echo "<p>⚠️ Debes seleccionar al menos un producto con cantidad mayor a 0.</p>";
    exit;
}

// Obtener info de los productos seleccionados
$ids = implode(",", array_keys($productos_seleccionados));
$sql = 'SELECT id, nombre, precio FROM "Division_Geografica"."productos" WHERE id IN (' . $ids . ')';
$stmt = $pdo->query($sql);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular total
$total = 0;
$resumen = "";

foreach ($productos as $p) {
    $cant = $productos_seleccionados[$p["id"]];
    $subtotal = $p["precio"] * $cant;
    $total += $subtotal;
    $resumen .= "- {$p['nombre']} x{$cant} ($" . number_format($subtotal, 0, ',', '.') . ")\n";
}

try {
    // Crear el pedido principal
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO "Division_Geografica"."pedidos"
        (id_usuario, id_comercio, detalle, estado, total, fecha, resumen)
        VALUES (:u, :c, :d, :e, :t, NOW(), :r)
        RETURNING id');
    $stmt->execute([
        ":u" => $id_usuario,
        ":c" => $id_comercio,
        ":d" => $detalle,
        ":e" => "pendiente",
        ":t" => $total,
        ":r" => $resumen
    ]);
    $id_pedido = $stmt->fetchColumn();

    // Guardar los productos
    $insertDetalle = $pdo->prepare('INSERT INTO "Division_Geografica"."detalle_pedido"
        (id_pedido, id_producto, cantidad, subtotal)
        VALUES (:p, :i, :c, :s)');
    foreach ($productos as $p) {
        $cant = $productos_seleccionados[$p["id"]];
        $subtotal = $p["precio"] * $cant;
        $insertDetalle->execute([
            ":p" => $id_pedido,
            ":i" => $p["id"],
            ":c" => $cant,
            ":s" => $subtotal
        ]);
    }

    $pdo->commit();

    echo "<script>alert('✅ Pedido realizado con éxito');window.location='../dashboard/usuario.php';</script>";
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "<p style='color:red;'>Error al crear pedido: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
