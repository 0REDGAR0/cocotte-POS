<?php
header("Content-Type: application/json");
require_once(__DIR__ . "/config.php");

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['nom'], $data['prix'], $data['categorie'])) {
    echo json_encode(["success" => false, "message" => "Données manquantes."]);
    exit;
}

$nom = trim($data['nom']);
$prix = floatval($data['prix']);
$cat = $data['categorie'];

if ($nom === "" || $prix <= 0 || !in_array($cat, ['bar', 'cuisine', 'snack'])) {
    echo json_encode(["success" => false, "message" => "Champs invalides."]);
    exit;
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare("
        INSERT INTO articles (nom, prix, bar, cuisine, snack)
        VALUES (:nom, :prix, :bar, :cuisine, :snack)
    ");

    $stmt->execute([
        'nom' => $nom,
        'prix' => $prix,
        'bar' => $cat === 'bar' ? 1 : 0,
        'cuisine' => $cat === 'cuisine' ? 1 : 0,
        'snack' => $cat === 'snack' ? 1 : 0
    ]);

    echo json_encode(["success" => true, "message" => "Article ajouté avec succès."]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erreur serveur", "details" => $e->getMessage()]);
}
