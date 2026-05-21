<?php

header("Content-Type: text/plain; charset=UTF-8");

require_once(__DIR__ . "/config.php");

try {
    $dbAlreadyExisted = file_exists(getSQLitePath());

    $pdo = getPDO();
    initializeSQLiteSchema($pdo);

    $tables = $pdo
        ->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name ASC")
        ->fetchAll(PDO::FETCH_COLUMN);

    echo "SQLite initialisee avec succes.\n";
    echo "Base: " . getSQLitePath() . "\n";
    echo "Etat du fichier: " . ($dbAlreadyExisted ? "existant" : "cree") . "\n";
    echo "Schema: " . getSQLiteSchemaPath() . "\n";
    echo "Tables: " . implode(", ", $tables) . "\n";
} catch (Throwable $e) {
    if (PHP_SAPI !== "cli") {
        http_response_code(500);
    }

    echo "Erreur lors de l'initialisation SQLite.\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
