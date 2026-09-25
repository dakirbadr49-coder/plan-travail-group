<?php
// Interrogé régulièrement par app.js : y a-t-il eu des changements depuis l'affichage de la page ?
declare(strict_types=1);
require __DIR__ . '/../src/app.php';

$_SERVER['HTTP_X_REQUESTED_WITH'] = 'fetch';
$moi = exiger_connexion();
$depuis = max(0, (int) ($_GET['depuis'] ?? 0));

$st = db()->prepare('SELECT m.prenom, COUNT(*) nb FROM historique h LEFT JOIN membres m ON m.id = h.membre_id
                     WHERE h.id > ? AND (h.membre_id IS NULL OR h.membre_id <> ?) GROUP BY m.prenom');
$st->execute([$depuis, $moi['id']]);
$parAuteur = $st->fetchAll();

repondre_json([
    'ok'      => true,
    'donnees' => [
        'dernier_evenement' => dernier_evenement(),
        'auteurs'           => array_map(fn($r) => $r['prenom'] ?? 'Quelqu\'un', $parAuteur),
        'nombre'            => array_sum(array_column($parAuteur, 'nb')),
    ],
]);
