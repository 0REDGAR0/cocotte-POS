<?php

define("PROJECT_ROOT", dirname(__DIR__));
define("STORAGE_DIR", PROJECT_ROOT . DIRECTORY_SEPARATOR . "storage");
define("SQLITE_PATH", STORAGE_DIR . DIRECTORY_SEPARATOR . "pos.sqlite");
define("SQLITE_SCHEMA_PATH", PROJECT_ROOT . DIRECTORY_SEPARATOR . "database" . DIRECTORY_SEPARATOR . "schema.sql");

function ensureStorageDirectory() {
    if (!is_dir(STORAGE_DIR) && !mkdir(STORAGE_DIR, 0777, true) && !is_dir(STORAGE_DIR)) {
        throw new RuntimeException("Impossible de creer le dossier de stockage SQLite.");
    }
}

function getSQLitePath() {
    return SQLITE_PATH;
}

function getSQLiteSchemaPath() {
    return SQLITE_SCHEMA_PATH;
}

function getAutoModePath() {
    return __DIR__ . DIRECTORY_SEPARATOR . "auto_mode.json";
}

function normalizeDateForJs($value) {
    if ($value === null || $value === "") {
        return null;
    }

    return str_replace(" ", "T", trim((string) $value));
}

function getCurrentTimestampText() {
    return date("Y-m-d\\TH:i:s");
}

function getPDO() {
    ensureStorageDirectory();

    $pdo = new PDO("sqlite:" . SQLITE_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA foreign_keys = ON");
    $pdo->exec("PRAGMA busy_timeout = 5000");
    $pdo->exec("PRAGMA journal_mode = WAL");

    return $pdo;
}

function initializeSQLiteSchema(PDO $pdo) {
    $schemaPath = getSQLiteSchemaPath();

    if (!file_exists($schemaPath)) {
        throw new RuntimeException("Schema SQLite introuvable : " . $schemaPath);
    }

    $schemaSql = file_get_contents($schemaPath);
    if ($schemaSql === false) {
        throw new RuntimeException("Lecture du schema SQLite impossible.");
    }

    $pdo->exec($schemaSql);
    $pdo->exec("PRAGMA foreign_keys = ON");
}
