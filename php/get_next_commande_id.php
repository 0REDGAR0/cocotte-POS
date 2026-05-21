<?php
header("Content-Type: application/json");
require_once(__DIR__ . "/config.php");

try {
  $pdo = getPDO();
  $stmt = $pdo->query("SELECT MAX(id) AS max_id FROM commandes");
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  $nextId = ($row["max_id"] ?? 0) + 1;
  echo json_encode(["next_id" => $nextId]);
} catch (Exception $e) {
  echo json_encode(["next_id" => null, "error" => $e->getMessage()]);
}
