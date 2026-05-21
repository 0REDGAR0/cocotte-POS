<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
require_once(__DIR__ . "/config.php");

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['commande_id'], $data['categorie'])) {
    echo json_encode(["success" => false, "message" => "Données incomplètes."]);
    exit;
}

$commande_id = intval($data['commande_id']);
$categorie = $data['categorie'];

if (!in_array($categorie, ['bar', 'cuisine', 'snack'])) {
    echo json_encode(["success" => false, "message" => "Catégorie invalide."]);
    exit;
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare("
        UPDATE commande_articles
        SET statut = 'en_cours'
        WHERE commande_id = :commande_id
        AND categorie = :categorie
        AND statut = 'pret'
    ");

    $stmt->execute([
        'commande_id' => $commande_id,
        'categorie' => $categorie
    ]);

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erreur serveur", "details" => $e->getMessage()]);
}
