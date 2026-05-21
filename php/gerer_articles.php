<?php
header("Content-Type: application/json");
require_once(__DIR__ . "/config.php");

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['action'])) {
    http_response_code(400);
    echo json_encode(["error" => "Action manquante"]);
    exit;
}

$action = $data['action'];

try {
    $pdo = getPDO();

    if ($action === "ajouter") {
        $stmt = $pdo->prepare("
            INSERT INTO articles (nom, prix, bar, cuisine, snack)
            VALUES (:nom, :prix, :bar, :cuisine, :snack)
        ");
        $stmt->execute([
            'nom' => $data['nom'],
            'prix' => $data['prix'],
            'bar' => !empty($data['bar']) ? 1 : 0,
            'cuisine' => !empty($data['cuisine']) ? 1 : 0,
            'snack' => !empty($data['snack']) ? 1 : 0
        ]);
        echo json_encode(["success" => true, "message" => "Article ajoute"]);
    } elseif ($action === "supprimer") {
        $stmt = $pdo->prepare("DELETE FROM articles WHERE id = :id");
        $stmt->execute(['id' => $data['article_id']]);
        echo json_encode(["success" => true, "message" => "Article supprime"]);
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Action non reconnue"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Erreur de traitement", "details" => $e->getMessage()]);
}
