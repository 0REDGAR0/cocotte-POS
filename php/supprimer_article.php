<?php
header("Content-Type: application/json");
require_once(__DIR__ . "/config.php");

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
    echo json_encode(["success" => false, "message" => "ID manquant."]);
    exit;
}

$id = (int)$data['id'];
if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "ID invalide."]);
    exit;
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    // 1. Supprimer les références dans les commandes
    $stmt = $pdo->prepare("DELETE FROM commande_articles WHERE article_id = :id");
    $stmt->execute(['id' => $id]);

    // 2. Supprimer l’article lui-même
    $stmt = $pdo->prepare("DELETE FROM articles WHERE id = :id");
    $stmt->execute(['id' => $id]);

    $pdo->commit();
    echo json_encode(["success" => true, "message" => "Article supprimé."]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erreur serveur", "details" => $e->getMessage()]);
}
