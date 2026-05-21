<?php
header("Content-Type: application/json");
require_once(__DIR__ . "/config.php");

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'], $data['nom'], $data['prix'], $data['categorie'])) {
    echo json_encode(["success" => false, "message" => "Données incomplètes."]);
    exit;
}

$id = (int)$data['id'];
$nom = trim($data['nom']);
$prix = floatval($data['prix']);
$cat = $data['categorie'];

if ($id <= 0 || $nom === "" || $prix <= 0 || !in_array($cat, ['bar', 'cuisine', 'snack'])) {
    echo json_encode(["success" => false, "message" => "Champs invalides."]);
    exit;
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    // 1. Mise à jour de l'article
    $stmt = $pdo->prepare("
        UPDATE articles
        SET nom = :nom, prix = :prix,
            bar = :bar, cuisine = :cuisine, snack = :snack
        WHERE id = :id
    ");
    $stmt->execute([
        'id' => $id,
        'nom' => $nom,
        'prix' => $prix,
        'bar' => $cat === 'bar' ? 1 : 0,
        'cuisine' => $cat === 'cuisine' ? 1 : 0,
        'snack' => $cat === 'snack' ? 1 : 0
    ]);

    // 2. Mettre à jour toutes les commandes contenant cet article
    $stmt = $pdo->prepare("
        UPDATE commande_articles
        SET nom = :nom, prix = :prix
        WHERE article_id = :id
    ");
    $stmt->execute([
        'id' => $id,
        'nom' => $nom,
        'prix' => $prix
    ]);

    $pdo->commit();
    echo json_encode(["success" => true, "message" => "Article et commandes mis à jour."]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erreur serveur", "details" => $e->getMessage()]);
}
