<?php
header("Content-Type: application/json");
require_once(__DIR__ . "/config.php");

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['commande_id']) || !isset($data['categorie'])) {
    http_response_code(400);
    echo json_encode(["error" => "Paramètres manquants"]);
    exit;
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare("
        UPDATE commande_articles
        SET statut = 'pret'
        WHERE commande_id = :commande_id
        AND categorie = :categorie
        AND statut != 'pret'
    ");
    $stmt->execute([
        'commande_id' => $data['commande_id'],
        'categorie' => $data['categorie']
    ]);

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Erreur serveur",
        "details" => $e->getMessage()
    ]);
}
