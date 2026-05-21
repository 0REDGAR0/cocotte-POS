<?php
header("Content-Type: application/json");
require_once(__DIR__ . "/config.php");

try {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT id, nom, prix, bar, cuisine, snack FROM articles ORDER BY nom ASC");
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($articles);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Erreur recuperation", "details" => $e->getMessage()]);
}
