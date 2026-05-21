<?php
header("Content-Type: application/json");
require_once(__DIR__ . "/config.php");

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['commande_id'])) {
  http_response_code(400);
  echo json_encode(["success" => false, "message" => "Parametre commande_id manquant"]);
  exit;
}

$commande_id = (int)$data['commande_id'];

try {
  $pdo = getPDO();

  $stmt = $pdo->prepare("
    SELECT COUNT(*) FROM commande_articles
    WHERE commande_id = :commande_id AND statut != 'pret'
  ");
  $stmt->execute(['commande_id' => $commande_id]);
  $restants = $stmt->fetchColumn();

  if ($restants > 0) {
    echo json_encode(["success" => false, "message" => "Tous les articles ne sont pas prets"]);
    exit;
  }

  $stmt = $pdo->prepare("
    UPDATE commandes
    SET statut = 'terminee', closed_at = :closed_at
    WHERE id = :id
  ");
  $stmt->execute([
    'id' => $commande_id,
    'closed_at' => getCurrentTimestampText()
  ]);

  echo json_encode(["success" => true]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["success" => false, "message" => "Erreur serveur", "details" => $e->getMessage()]);
}
