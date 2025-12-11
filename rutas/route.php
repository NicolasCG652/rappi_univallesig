<?php
header("Content-Type: application/json; charset=utf-8");
require_once "../config/db.php";

/*
 🧭 PARÁMETROS GET
 id_pedido → pedido actual
 latR/lonR → repartidor (6249)
 latC/lonC → comercio   (6249)
 latU/lonU → usuario    (6249)
 La red vial está en EPSG:3115
*/

$id_pedido = $_GET["id_pedido"] ?? null;
$latR = $_GET["latR"] ?? null;
$lonR = $_GET["lonR"] ?? null;
$latC = $_GET["latC"] ?? null;
$lonC = $_GET["lonC"] ?? null;
$latU = $_GET["latU"] ?? null;
$lonU = $_GET["lonU"] ?? null;

if (!$id_pedido || !$latR || !$lonR || !$latC || !$lonC || !$latU || !$lonU) {
    echo json_encode(["ok" => false, "error" => "⚠️ Faltan parámetros o coordenadas."]);
    exit;
}

try {
    // =======================================
    // 🧮 Cálculo de ruta (pgRouting + PostGIS)
    // =======================================
    $sql = "
    WITH puntos AS (
        SELECT
            ST_Transform(ST_SetSRID(ST_MakePoint(:lonR, :latR), 6249), 3115) AS geom_rep,
            ST_Transform(ST_SetSRID(ST_MakePoint(:lonC, :latC), 6249), 3115) AS geom_com,
            ST_Transform(ST_SetSRID(ST_MakePoint(:lonU, :latU), 6249), 3115) AS geom_usr
    ),
    vertices AS (
        SELECT
            (SELECT id FROM \"Expansion_Universitaria\".\"vias_vertices_pgr\"
             WHERE ST_DWithin(the_geom, (SELECT geom_rep FROM puntos), 300)
             ORDER BY the_geom <-> (SELECT geom_rep FROM puntos) LIMIT 1) AS id_rep,

            (SELECT id FROM \"Expansion_Universitaria\".\"vias_vertices_pgr\"
             WHERE ST_DWithin(the_geom, (SELECT geom_com FROM puntos), 300)
             ORDER BY the_geom <-> (SELECT geom_com FROM puntos) LIMIT 1) AS id_com,

            (SELECT id FROM \"Expansion_Universitaria\".\"vias_vertices_pgr\"
             WHERE ST_DWithin(the_geom, (SELECT geom_usr FROM puntos), 300)
             ORDER BY the_geom <-> (SELECT geom_usr FROM puntos) LIMIT 1) AS id_usr
    ),
    ruta1 AS (
        SELECT seq, node, edge, cost
        FROM pgr_dijkstra(
            'SELECT id_0 AS id, source, target, cost, reverse_cost FROM \"Expansion_Universitaria\".\"vias\"',
            (SELECT id_rep FROM vertices),
            (SELECT id_com FROM vertices),
            false
        )
    ),
    ruta2 AS (
        SELECT seq, node, edge, cost
        FROM pgr_dijkstra(
            'SELECT id_0 AS id, source, target, cost, reverse_cost FROM \"Expansion_Universitaria\".\"vias\"',
            (SELECT id_com FROM vertices),
            (SELECT id_usr FROM vertices),
            false
        )
    ),
    todas AS (
        SELECT * FROM ruta1
        UNION ALL
        SELECT * FROM ruta2
    )
    SELECT
        ST_AsGeoJSON(ST_Transform(ST_Union(v.geom), 4326)) AS geom,
        ST_AsGeoJSON(ST_Transform((SELECT geom_rep FROM puntos), 4326)) AS rep,
        ST_AsGeoJSON(ST_Transform((SELECT geom_com FROM puntos), 4326)) AS com,
        ST_AsGeoJSON(ST_Transform((SELECT geom_usr FROM puntos), 4326)) AS usr,
        SUM(v.cost) AS total_cost
    FROM \"Expansion_Universitaria\".\"vias\" v
    JOIN todas t ON v.id_0 = t.edge;
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ":latR" => $latR, ":lonR" => $lonR,
        ":latC" => $latC, ":lonC" => $lonC,
        ":latU" => $latU, ":lonU" => $lonU
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // =======================
    // 🚨 Si no hay ruta
    // =======================
    if (!$row || !$row["geom"]) {
        echo json_encode([
            "ok" => false,
            "error" => "❌ No se pudo generar la ruta. Los puntos están fuera de la red o sin conexión topológica.",
            "debug" => [
                "repartidor" => json_decode($row["rep"] ?? "{}"),
                "comercio"   => json_decode($row["com"] ?? "{}"),
                "usuario"    => json_decode($row["usr"] ?? "{}")
            ]
        ]);
        exit;
    }

    // =======================================
    // 💾 Guardar la ruta en la tabla pedidos
    // =======================================
    $update = $pdo->prepare('
    UPDATE "Division_Geografica"."pedidos"
    SET ruta_geom = ST_LineMerge(ST_CollectionExtract(ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(:geojson), 3115)), 2))
    WHERE id = :id_pedido
');
    $update->execute([
        ":geojson" => $row["geom"],
        ":id_pedido" => $id_pedido
    ]);

    // =======================================
    // 📤 Devolver respuesta JSON
    // =======================================
    echo json_encode([
        "ok" => true,
        "route" => json_decode($row["geom"]),
        "meta" => [
            "distancia_m" => round($row["total_cost"], 2),
            "mensaje" => "Ruta generada correctamente 🚴"
        ],
        "debug" => [
            "repartidor" => json_decode($row["rep"]),
            "comercio"   => json_decode($row["com"]),
            "usuario"    => json_decode($row["usr"])
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        "ok" => false,
        "error" => "Error pgRouting: " . $e->getMessage()
    ]);
}
?>
