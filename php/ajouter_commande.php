<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");

require_once(__DIR__ . "/config.php");

try {
  $pdo = getPDO();
  $data = json_decode(file_get_contents("php://input"), true);

  if (!isset($data['articles']) || !is_array($data['articles'])) {
    throw new Exception("Donnees de commande invalides.");
  }

  $pdo->beginTransaction();

  $stmt = $pdo->prepare("
    INSERT INTO commandes (message, date)
    VALUES (:message, :date)
  ");
  $stmt->execute([
    'message' => $data['message'] ?? '',
    'date' => getCurrentTimestampText()
  ]);
  $commande_id = (int) $pdo->lastInsertId();

  if ($commande_id <= 0) {
    throw new Exception("Echec lors de la creation de la commande.");
  }

  $file = getAutoModePath();
  if (!file_exists($file)) {
    file_put_contents($file, json_encode(["bar" => false, "cuisine" => false, "snack" => false]));
  }
  $auto_mode = json_decode(file_get_contents($file), true);
  if (!is_array($auto_mode)) {
    $auto_mode = ["bar" => false, "cuisine" => false, "snack" => false];
  }

  $stmtArticle = $pdo->prepare("
    INSERT INTO commande_articles (commande_id, article_id, quantite, categorie, statut, prix, nom)
    VALUES (:commande_id, :article_id, :quantite, :categorie, :statut, :prix, :nom)
  ");

  foreach ($data['articles'] as $article) {
    if (!isset($article['quantite'], $article['categorie'])) {
      throw new Exception("Donnees article manquantes.");
    }

    $cat = $article['categorie'];
    $nom = $article['nom'] ?? null;
    $statut = (!empty($auto_mode[$cat])) ? "pret" : "en_cours";
    $article_id = (isset($article['id']) && $article['id'] > 0) ? (int) $article['id'] : null;

    $stmtArticle->execute([
      'commande_id' => $commande_id,
      'article_id' => $article_id,
      'quantite' => (int) $article['quantite'],
      'categorie' => $cat,
      'statut' => $statut,
      'prix' => isset($article['prix']) ? (float) $article['prix'] : 0,
      'nom' => $nom
    ]);
  }

  $pdo->commit();
  echo json_encode(["success" => true]);
} catch (Exception $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  http_response_code(500);
  echo json_encode([
    "success" => false,
    "message" => "Erreur serveur : " . $e->getMessage()
  ]);
}
