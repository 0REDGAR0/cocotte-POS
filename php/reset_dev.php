<?php
header("Content-Type: text/plain; charset=UTF-8");

require_once(__DIR__ . "/config.php");

try {
    $pdo = getPDO();
    initializeSQLiteSchema($pdo);
    $pdo->beginTransaction();

    $deletedCommandeArticles = (int) $pdo->query("SELECT COUNT(*) FROM commande_articles")->fetchColumn();
    $deletedCommandes = (int) $pdo->query("SELECT COUNT(*) FROM commandes")->fetchColumn();
    $deletedArticles = (int) $pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn();

    $pdo->exec("DELETE FROM commande_articles");
    $pdo->exec("DELETE FROM commandes");
    $pdo->exec("DELETE FROM articles");
    $pdo->commit();

    file_put_contents(getAutoModePath(), json_encode([
        "bar" => false,
        "cuisine" => false,
        "snack" => false
    ]));

    echo "Reset dev termine.\n";
    echo "Base: " . getSQLitePath() . "\n";
    echo "commande_articles supprimes: " . $deletedCommandeArticles . "\n";
    echo "commandes supprimees: " . $deletedCommandes . "\n";
    echo "articles supprimes: " . $deletedArticles . "\n";
    echo "auto_mode reinitialise: oui\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (PHP_SAPI !== "cli") {
        http_response_code(500);
    }

    echo "Erreur pendant le reset dev.\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
