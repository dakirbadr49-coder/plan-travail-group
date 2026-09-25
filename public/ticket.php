<?php
declare(strict_types=1);
require __DIR__ . '/../src/app.php';
require __DIR__ . '/../src/tickets.php';

$moi = exiger_connexion();
$id = (int) ($_GET['id'] ?? 0);
$ticket = $id > 0 ? ticket_ou_404($id) : null;
$valeurs = $ticket ?? [
    'ref' => '', 'titre' => '', 'description' => '', 'section' => 'Frontend', 'semaine' => semaine_courante(),
    'priorite' => 'normale', 'statut' => 'a_faire', 'assigne_a' => $moi['id'],
];
$erreurs = [];
$erreurCommentaire = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_post();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'enregistrer') {
        [$valeurs, $erreurs] = valider_ticket($_POST, $ticket['id'] ?? null);
        if (!$erreurs) {
            if ($ticket) {
                mettre_a_jour_ticket($ticket, $valeurs);
                flash('Ticket enregistré.');
            } else {
                $id = creer_ticket($valeurs);
                flash('Ticket créé.');
            }
            rediriger('ticket.php?id=' . $id);
        }
    } elseif ($ticket && $action === 'commenter') {
        $message = trim((string) ($_POST['message'] ?? ''));
        if ($message === '' || mb_strlen($message) > 2000) {
            $erreurCommentaire = 'Le commentaire doit faire entre 1 et 2000 caractères.';
        } else {
            db()->prepare('INSERT INTO commentaires (ticket_id, membre_id, message) VALUES (?, ?, ?)')
                ->execute([$ticket['id'], $moi['id'], $message]);
            journaliser((int) $ticket['id'], 'commentaire', 'a commenté');
            rediriger('ticket.php?id=' . $ticket['id'] . '#commentaires');
        }
    } elseif ($ticket && $action === 'supprimer') {
        db()->prepare('DELETE FROM tickets WHERE id = ?')->execute([$ticket['id']]);
        journaliser(null, 'suppression', 'a supprimé ' . ($ticket['ref'] ? $ticket['ref'] . ' ' : '') . '« ' . $ticket['titre'] . ' »');
        flash('Ticket supprimé.', 'info');
        rediriger('tickets.php');
    } else {
        page_erreur('Action inconnue.', 400);
    }
}

$commentaires = $historique = [];
if ($ticket) {
    $st = db()->prepare('SELECT c.*, m.prenom, m.couleur FROM commentaires c LEFT JOIN membres m ON m.id = c.membre_id
                         WHERE c.ticket_id = ? ORDER BY c.cree_le, c.id');
    $st->execute([$ticket['id']]);
    $commentaires = $st->fetchAll();

    $st = db()->prepare('SELECT h.*, m.prenom, m.couleur FROM historique h LEFT JOIN membres m ON m.id = h.membre_id
                         WHERE h.ticket_id = ? ORDER BY h.id DESC');
    $st->execute([$ticket['id']]);
    $historique = $st->fetchAll();
}

function classe_erreur(array $erreurs, string $champ): string
{
    return isset($erreurs[$champ]) ? ' is-invalid' : '';
}

function message_erreur(array $erreurs, string $champ): string
{
    return isset($erreurs[$champ]) ? '<div class="invalid-feedback">' . e($erreurs[$champ]) . '</div>' : '';
}

$titre = $ticket ? ($ticket['ref'] ? $ticket['ref'] . ' · ' : '') . $ticket['titre'] : 'Nouveau ticket';
$page = 'tickets';
require __DIR__ . '/../src/vues/header.php';
?>
<nav aria-label="Fil d'Ariane" class="mb-3">
    <a href="tickets.php" class="small"><i class="bi bi-arrow-left"></i> Tous les tickets</a>
</nav>

<div class="row g-4">
    <div class="col-lg-8">
        <?php if ($ticket): ?>
            <section class="panneau mb-4">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <span class="ref fs-6"><?= e($ticket['ref'] ?? '#' . $ticket['id']) ?></span>
                    <?= badge_statut($ticket['statut']) ?>
                    <?= badge_priorite($ticket['priorite']) ?>
                    <span class="badge section-badge"><?= e($ticket['section']) ?></span>
                    <?php if ($ticket['semaine']): ?><span class="badge text-bg-light">Semaine <?= (int) $ticket['semaine'] ?></span><?php endif; ?>
                </div>
                <h1 class="h3"><?= e($ticket['titre']) ?></h1>
                <div class="description"><?= $ticket['description'] !== '' ? nl2br(e($ticket['description'])) : '<em class="text-muted">Pas de description.</em>' ?></div>
                <button class="btn btn-sm btn-outline-choco mt-3" type="button" data-bs-toggle="collapse" data-bs-target="#form-ticket" aria-expanded="<?= $erreurs ? 'true' : 'false' ?>" aria-controls="form-ticket">
                    <i class="bi bi-pencil"></i> Modifier le ticket
                </button>
            </section>
        <?php else: ?>
            <h1 class="h3 mb-3">Nouveau ticket</h1>
        <?php endif; ?>

        <form method="post" id="form-ticket" class="panneau mb-4<?= $ticket && !$erreurs ? ' collapse' : '' ?>" novalidate>
            <?= csrf_champ() ?>
            <input type="hidden" name="action" value="enregistrer">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="ref">Référence</label>
                    <input class="form-control<?= classe_erreur($erreurs, 'ref') ?>" id="ref" name="ref" maxlength="20" value="<?= e($valeurs['ref']) ?>" placeholder="FE-34">
                    <?= message_erreur($erreurs, 'ref') ?>
                </div>
                <div class="col-md-9">
                    <label class="form-label" for="titre">Titre *</label>
                    <input class="form-control<?= classe_erreur($erreurs, 'titre') ?>" id="titre" name="titre" required minlength="3" maxlength="200" value="<?= e($valeurs['titre']) ?>">
                    <?= message_erreur($erreurs, 'titre') ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control<?= classe_erreur($erreurs, 'description') ?>" id="description" name="description" rows="4" maxlength="5000"><?= e($valeurs['description']) ?></textarea>
                    <?= message_erreur($erreurs, 'description') ?>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="section">Section</label>
                    <select class="form-select<?= classe_erreur($erreurs, 'section') ?>" id="section" name="section">
                        <?php foreach (SECTIONS as $s): ?>
                            <option<?= $valeurs['section'] === $s ? ' selected' : '' ?>><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= message_erreur($erreurs, 'section') ?>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="semaine">Semaine prévue</label>
                    <select class="form-select<?= classe_erreur($erreurs, 'semaine') ?>" id="semaine" name="semaine">
                        <option value="">—</option>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <option value="<?= $i ?>"<?= (int) $valeurs['semaine'] === $i ? ' selected' : '' ?>>Semaine <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                    <?= message_erreur($erreurs, 'semaine') ?>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="priorite">Priorité</label>
                    <select class="form-select<?= classe_erreur($erreurs, 'priorite') ?>" id="priorite" name="priorite">
                        <?php foreach (PRIORITES as $cle => $libelle): ?>
                            <option value="<?= $cle ?>"<?= $valeurs['priorite'] === $cle ? ' selected' : '' ?>><?= $libelle ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= message_erreur($erreurs, 'priorite') ?>
                </div>
                <div class="col-6 col-md-6">
                    <label class="form-label" for="statut">Statut</label>
                    <select class="form-select<?= classe_erreur($erreurs, 'statut') ?>" id="statut" name="statut">
                        <?php foreach (STATUTS as $cle => $libelle): ?>
                            <option value="<?= $cle ?>"<?= $valeurs['statut'] === $cle ? ' selected' : '' ?>><?= $libelle ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= message_erreur($erreurs, 'statut') ?>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="assigne_a">Assigné à</label>
                    <select class="form-select<?= classe_erreur($erreurs, 'assigne_a') ?>" id="assigne_a" name="assigne_a">
                        <option value="">Personne</option>
                        <?php foreach (membres() as $m): ?>
                            <option value="<?= (int) $m['id'] ?>"<?= (string) $valeurs['assigne_a'] === (string) $m['id'] ? ' selected' : '' ?>><?= e($m['prenom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= message_erreur($erreurs, 'assigne_a') ?>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-choco"><i class="bi bi-check-lg"></i> <?= $ticket ? 'Enregistrer' : 'Créer le ticket' ?></button>
                <a class="btn btn-outline-secondary" href="<?= $ticket ? 'ticket.php?id=' . (int) $ticket['id'] : 'tickets.php' ?>">Annuler</a>
            </div>
        </form>

        <?php if ($ticket): ?>
            <section class="panneau" id="commentaires">
                <h2 class="h5">Commentaires (<?= count($commentaires) ?>)</h2>
                <?php foreach ($commentaires as $c): ?>
                    <article class="commentaire">
                        <?= avatar($c['prenom'], $c['couleur'], 'avatar-sm') ?>
                        <div>
                            <div><strong><?= e($c['prenom'] ?? 'Ancien membre') ?></strong> <span class="text-muted small"><?= e(date_courte($c['cree_le'])) ?></span></div>
                            <div><?= nl2br(e($c['message'])) ?></div>
                        </div>
                    </article>
                <?php endforeach; ?>
                <form method="post" class="mt-3" novalidate>
                    <?= csrf_champ() ?>
                    <input type="hidden" name="action" value="commenter">
                    <label class="form-label visually-hidden" for="message">Ton commentaire</label>
                    <textarea class="form-control<?= $erreurCommentaire ? ' is-invalid' : '' ?>" id="message" name="message" rows="2" maxlength="2000" required placeholder="Une question, un lien vers la PR, un blocage…"></textarea>
                    <?php if ($erreurCommentaire): ?><div class="invalid-feedback"><?= e($erreurCommentaire) ?></div><?php endif; ?>
                    <button class="btn btn-sm btn-choco mt-2"><i class="bi bi-send"></i> Commenter</button>
                </form>
            </section>
        <?php endif; ?>
    </div>

    <?php if ($ticket): ?>
        <aside class="col-lg-4">
            <section class="panneau mb-4">
                <h2 class="h6 text-uppercase text-muted">Actions rapides</h2>
                <form method="post" action="actions.php" class="d-flex flex-wrap gap-2 mb-3">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="do" value="statut">
                    <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
                    <?php foreach (STATUTS as $cle => $libelle): ?>
                        <button class="btn btn-sm <?= $ticket['statut'] === $cle ? 'btn-choco' : 'btn-outline-choco' ?>" name="statut" value="<?= $cle ?>"<?= $ticket['statut'] === $cle ? ' aria-pressed="true"' : '' ?>><?= $libelle ?></button>
                    <?php endforeach; ?>
                </form>
                <form method="post" action="actions.php" class="d-flex gap-2 align-items-center">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="do" value="assigner">
                    <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
                    <?= avatar($ticket['prenom'], $ticket['couleur']) ?>
                    <label class="visually-hidden" for="assignation-rapide">Assigné à</label>
                    <select class="form-select form-select-sm js-autosubmit" id="assignation-rapide" name="assigne_a">
                        <option value="">Personne</option>
                        <?php foreach (membres() as $m): ?>
                            <option value="<?= (int) $m['id'] ?>"<?= (int) $ticket['assigne_a'] === (int) $m['id'] ? ' selected' : '' ?>><?= e($m['prenom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button class="btn btn-sm btn-choco">OK</button></noscript>
                </form>
                <p class="small text-muted mt-3 mb-0">
                    Créé le <?= e(date('d/m/Y', strtotime($ticket['cree_le']))) ?>
                    <?= $ticket['fait_le'] ? ' · terminé le ' . e(date('d/m/Y', strtotime($ticket['fait_le']))) : '' ?>
                </p>
            </section>
            <section class="panneau mb-4">
                <h2 class="h6 text-uppercase text-muted">Historique</h2>
                <?php if ($historique): ?>
                    <ul class="liste-histo"><?php foreach ($historique as $h) echo ligne_historique($h, false); ?></ul>
                <?php else: ?>
                    <p class="small text-muted mb-0">Aucune modification depuis la création du projet.</p>
                <?php endif; ?>
            </section>
            <form method="post" data-confirm="Supprimer définitivement ce ticket ?">
                <?= csrf_champ() ?>
                <input type="hidden" name="action" value="supprimer">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Supprimer le ticket</button>
            </form>
        </aside>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../src/vues/footer.php'; ?>
