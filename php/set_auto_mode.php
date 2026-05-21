<?php
header("Content-Type: application/json");

require_once(__DIR__ . "/config.php");

$input = json_decode(file_get_contents("php://input"), true);
$role = $input["role"] ?? null;
$actif = $input["actif"] ?? null;

if (!in_array($role, ["bar", "cuisine", "snack"], true)) {
  echo json_encode(["success" => false, "message" => "Role invalide"]);
  exit;
}

$file = getAutoModePath();
if (!file_exists($file)) {
  file_put_contents($file, json_encode(["bar" => false, "cuisine" => false, "snack" => false]));
}

$etat = json_decode(file_get_contents($file), true);
if (!is_array($etat)) {
  $etat = ["bar" => false, "cuisine" => false, "snack" => false];
}

$etat[$role] = (bool)$actif;
file_put_contents($file, json_encode($etat));

echo json_encode(["success" => true]);
