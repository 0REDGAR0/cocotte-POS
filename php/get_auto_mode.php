<?php
header("Content-Type: application/json");

require_once(__DIR__ . "/config.php");

$file = getAutoModePath();

if (!file_exists($file)) {
    $default = ["bar" => false, "cuisine" => false, "snack" => false];
    file_put_contents($file, json_encode($default));
}

$data = json_decode(file_get_contents($file), true);

$data = array_merge(
    ["bar" => false, "cuisine" => false, "snack" => false],
    is_array($data) ? $data : []
);

echo json_encode($data);
