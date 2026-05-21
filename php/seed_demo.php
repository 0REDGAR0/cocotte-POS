<?php
header("Content-Type: text/plain; charset=UTF-8");

require_once(__DIR__ . "/config.php");

$demoArticles = [
    ["nom" => "Espresso", "prix" => 2.20, "categorie" => "bar"],
    ["nom" => "Jus orange", "prix" => 3.50, "categorie" => "bar"],
    ["nom" => "Burger d'Edgar", "prix" => 11.90, "categorie" => "cuisine"],
    ["nom" => "Salade du jour", "prix" => 9.50, "categorie" => "cuisine"],
    ["nom" => "Panini jambon", "prix" => 6.80, "categorie" => "snack"],
    ["nom" => "Glace 2 boules", "prix" => 4.50, "categorie" => "snack"]
];

try {
    $pdo = getPDO();
    initializeSQLiteSchema($pdo);
    $pdo->beginTransaction();

    $inserted = 0;
    $updated = 0;

    $selectStmt = $pdo->prepare("SELECT id FROM articles WHERE nom = :nom LIMIT 1");
    $insertStmt = $pdo->prepare("
        INSERT INTO articles (nom, prix, bar, cuisine, snack)
        VALUES (:nom, :prix, :bar, :cuisine, :snack)
    ");
    $updateStmt = $pdo->prepare("
        UPDATE articles
        SET prix = :prix, bar = :bar, cuisine = :cuisine, snack = :snack
        WHERE id = :id
    ");

    foreach ($demoArticles as $article) {
        $flags = [
            'bar' => $article['categorie'] === 'bar' ? 1 : 0,
            'cuisine' => $article['categorie'] === 'cuisine' ? 1 : 0,
            'snack' => $article['categorie'] === 'snack' ? 1 : 0
        ];

        $selectStmt->execute(['nom' => $article['nom']]);
        $existingId = $selectStmt->fetchColumn();

        if ($existingId) {
            $updateStmt->execute([
                'id' => $existingId,
                'prix' => $article['prix'],
                'bar' => $flags['bar'],
                'cuisine' => $flags['cuisine'],
                'snack' => $flags['snack']
            ]);
            $updated++;
            continue;
        }

        $insertStmt->execute([
            'nom' => $article['nom'],
            'prix' => $article['prix'],
            'bar' => $flags['bar'],
            'cuisine' => $flags['cuisine'],
            'snack' => $flags['snack']
        ]);
        $inserted++;
    }

    $pdo->commit();

    echo "Seed demo termine.\n";
    echo "Base: " . getSQLitePath() . "\n";
    echo "Articles inseres: " . $inserted . "\n";
    echo "Articles mis a jour: " . $updated . "\n";
    echo "Total source demo: " . count($demoArticles) . "\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (PHP_SAPI !== "cli") {
        http_response_code(500);
    }

    echo "Erreur pendant le seed demo.\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
