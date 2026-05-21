<?php
header("Content-Type: application/json");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . "/config.php");

try {
  $pdo = getPDO();
  $cmdStmt = $pdo->prepare("SELECT * FROM commandes ORDER BY id ASC");
  $cmdStmt->execute();
  $commandes = [];

  while ($cmd = $cmdStmt->fetch(PDO::FETCH_ASSOC)) {
    $commande_id = $cmd["id"];

    $artStmt = $pdo->prepare("
      SELECT
        ca.quantite,
        ca.categorie,
        ca.statut,
        ca.prix,
        COALESCE(ca.nom, a.nom) AS nom,
        ca.article_id
      FROM commande_articles ca
      LEFT JOIN articles a ON ca.article_id = a.id
      WHERE ca.commande_id = :commande_id
      ORDER BY ca.id ASC
    ");
    $artStmt->execute(["commande_id" => $commande_id]);
    $articles = $artStmt->fetchAll(PDO::FETCH_ASSOC);

    $commandes[] = [
      "id" => $commande_id,
      "date" => normalizeDateForJs($cmd["date"] ?? null),
      "statut" => $cmd["statut"],
      "closed_at" => normalizeDateForJs($cmd["closed_at"] ?? null),
      "articles" => $articles
    ];
  }

  echo json_encode($commandes);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    "success" => false,
    "message" => "Erreur serveur : " . $e->getMessage()
  ]);
}
