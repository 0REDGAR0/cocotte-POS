<?php
header("Content-Type: application/json");
require_once(__DIR__ . "/config.php");

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    $pdo->exec("
        DELETE FROM commande_articles
        WHERE commande_id IN (
            SELECT id FROM commandes WHERE statut = 'terminee'
        )
    ");

    $pdo->exec("DELETE FROM commandes WHERE statut = 'terminee'");

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Commandes archivees supprimees. Le prochain identifiant sera recalcule automatiquement."
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erreur lors du vidage",
        "details" => $e->getMessage()
    ]);
}
