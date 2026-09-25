<?php
// Actions rapides (formulaires POST et appels fetch du Kanban).
declare(strict_types=1);
require __DIR__ . '/../src/app.php';
require __DIR__ . '/../src/tickets.php';

verifier_post();
$do = (string) ($_POST['do'] ?? '');

if ($do === 'deconnexion') {
    deconnecter();
    session_start();
    flash('Tu es déconnecté·e.', 'info');
    rediriger('connexion.php');
}

$moi = exiger_connexion();
$ticket = isset($_POST['id']) ? ticket_ou_404((int) $_POST['id']) : null;

switch ($do) {
    case 'prendre':
        assigner($ticket ?? page_erreur('Ticket manquant.', 422), (int) $moi['id']);
        if ($ticket['statut'] === 'a_faire') {
            changer_statut($ticket, 'en_cours');
        }
        flash('C\'est pour toi : « ' . $ticket['titre'] . ' ».');
        break;

    case 'assigner':
        $cible = trim((string) ($_POST['assigne_a'] ?? ''));
        assigner($ticket ?? page_erreur('Ticket manquant.', 422), $cible === '' ? null : (int) $cible);
        flash('Assignation mise à jour.');
        break;

    case 'statut':
        $statut = (string) ($_POST['statut'] ?? '');
        changer_statut($ticket ?? page_erreur('Ticket manquant.', 422), $statut);
        if (est_ajax()) {
            repondre_json(['ok' => true, 'message' => 'Statut mis à jour.', 'dernier_evenement' => dernier_evenement()]);
        }
        flash('« ' . $ticket['titre'] . ' » → ' . STATUTS[$statut]);
        break;

    default:
        page_erreur('Action inconnue.', 400);
}

rediriger(page_precedente());
