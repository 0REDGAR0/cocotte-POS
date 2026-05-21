<?php
header("Content-Type: text/plain; charset=UTF-8");
require_once(__DIR__ . "/config.php");

try {
    $pdo = getPDO();
    $version = $pdo->query("SELECT sqlite_version()")->fetchColumn();

    echo "Connexion SQLite reussie.\n";
    echo "Base: " . getSQLitePath() . "\n";
    echo "Version SQLite: " . $version . "\n";
    echo "Note: le nom du fichier est legacy, il teste maintenant SQLite.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Erreur de connexion SQLite : " . $e->getMessage();
}
