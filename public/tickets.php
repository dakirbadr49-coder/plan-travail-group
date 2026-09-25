<?php
declare(strict_types=1);
require __DIR__ . '/../src/app.php';

$moi = exiger_connexion();

$filtres = [
    'membre'   => (string) ($_GET['membre'] ?? ''),
    'section'  => (string) ($_GET['section'] ?? ''),
    'semaine'  => (string) ($_GET['semaine'] ?? ''),
    'priorite' => (string) ($_GET['priorite'] ?? ''),
    'q'        => trim((string) ($_GET['q'] ?? '')),
];
$conditions = [];
$params = [];

if ($filtres['membre'] === 'libre') {
    $conditions[] = 't.assigne_a IS NULL';
} elseif (ctype_digit($filtres['membre'])) {
    $conditions[] = 't.assigne_a = ?';
    $params[] = (int) $filtres['membre'];
} else {
    $filtres['membre'] = '';
}

if (in_array($filtres['section'], SECTIONS, true)) {
    $conditions[] = 't.section = ?';
    $params[] = $filtres['section'];
} else {
    $filtres['section'] = '';
    $conditions[] = "t.section <> 'Bonus'";
}

if (in_array($filtres['semaine'], ['1', '2', '3', '4'], true)) {
    $conditions[] = 't.semaine = ?';
    $params[] = (int) $filtres['semaine'];
} else {
    $filtres['semaine'] = '';
}

if (isset(PRIORITES[$filtres['priorite']])) {
    $conditions[] = 't.priorite = ?';
    $params[] = $filtres['priorite'];
} else {
    $filtres['priorite'] = '';
}

if ($filtres['q'] !== '') {
    $motif = '%' . addcslashes(mb_substr($filtres['q'], 0, 100), '%_\\') . '%';
    $conditions[] = '(t.ref LIKE ? OR t.titre LIKE ? OR t.description LIKE ?)';
    array_push($params, $motif, $motif, $motif);
}

$st = db()->prepare('SELECT t.id, t.ref, t.titre, t.section, t.semaine, t.priorite, t.statut, t.assigne_a, m.prenom, m.couleur,
                            (SELECT COUNT(*) FROM commentaires c WHERE c.ticket_id = t.id) nb_commentaires
                     FROM tickets t LEFT JOIN membres m ON m.id = t.assigne_a
                     WHERE ' . implode(' AND ', $conditions) . '
                     ORDER BY ' . ORDRE_PRIORITE_SQL . ', t.semaine IS NULL, t.semaine, t.ref IS NULL, t.ref');
$st->execute($params);

$colonnes = array_fill_keys(array_keys(STATUTS), []);
foreach ($st->fetchAll() as $t) {
    $colonnes[$t['statut']][] = $t;
}
$nbTotal = array_sum(array_map('count', $colonnes));

$titre = 'Tickets';
$page = 'tickets';
require __DIR__ . '/../src/vues/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Tickets <span class="text-muted fs-6">(<?= $nbTotal ?>)</span></h1>
    <p class="small text-muted mb-0 d-none d-lg-block"><i class="bi bi-arrows-move"></i> Glisse une carte d'une colonne à l'autre pour changer son statut.</p>
</div>

<form class="filtres panneau mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label small" for="q">Recherche</label>
            <input class="form-control form-control-sm" type="search" id="q" name="q" value="<?= e($filtres['q']) ?>" placeholder="FE-34, étoiles, CSRF…">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small" for="membre">Personne</label>
            <select class="form-select form-select-sm js-autosubmit" id="membre" name="membre">
                <option value="">Tout le monde</option>
                <option value="libre"<?= $filtres['membre'] === 'libre' ? ' selected' : '' ?>>Sans personne</option>
                <?php foreach (membres() as $m): ?>
                    <option value="<?= (int) $m['id'] ?>"<?= $filtres['membre'] === (string) $m['id'] ? ' selected' : '' ?>><?= e($m['prenom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small" for="section">Section</label>
            <select class="form-select form-select-sm js-autosubmit" id="section" name="section">
                <option value="">Toutes (hors bonus)</option>
                <?php foreach (SECTIONS as $s): ?>
                    <option<?= $filtres['section'] === $s ? ' selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small" for="semaine">Semaine</label>
            <select class="form-select form-select-sm js-autosubmit" id="semaine" name="semaine">
                <option value="">Toutes</option>
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <option value="<?= $i ?>"<?= $filtres['semaine'] === (string) $i ? ' selected' : '' ?>>Semaine <?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small" for="priorite">Priorité</label>
            <select class="form-select form-select-sm js-autosubmit" id="priorite" name="priorite">
                <option value="">Toutes</option>
                <?php foreach (PRIORITES as $cle => $libelle): ?>
                    <option value="<?= $cle ?>"<?= $filtres['priorite'] === $cle ? ' selected' : '' ?>><?= $libelle ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-choco flex-fill" title="Filtrer"><i class="bi bi-search"></i><span class="visually-hidden">Filtrer</span></button>
            <a class="btn btn-sm btn-outline-secondary" href="tickets.php" title="Réinitialiser"><i class="bi bi-x-lg"></i><span class="visually-hidden">Réinitialiser</span></a>
        </div>
    </div>
</form>

<div class="kanban">
    <?php foreach ($colonnes as $statut => $tickets): ?>
        <section class="kanban-col" data-statut="<?= $statut ?>" aria-labelledby="col-<?= $statut ?>">
            <h2 class="kanban-titre" id="col-<?= $statut ?>">
                <?= badge_statut($statut) ?> <span class="compteur"><?= count($tickets) ?></span>
            </h2>
            <div class="kanban-liste">
                <?php foreach ($tickets as $t): ?>
                    <article class="carte-ticket prio-bord-<?= e($t['priorite']) ?>" draggable="true" data-id="<?= (int) $t['id'] ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <span class="ref"><?= e($t['ref'] ?? '#' . $t['id']) ?></span>
                            <span class="badge section-badge"><?= e($t['section']) ?></span>
                        </div>
                        <a class="titre-ticket" href="ticket.php?id=<?= (int) $t['id'] ?>"><?= e($t['titre']) ?></a>
                        <div class="d-flex align-items-center gap-2 small text-muted">
                            <?php if ($t['semaine']): ?><span>S<?= (int) $t['semaine'] ?></span><?php endif; ?>
                            <?= badge_priorite($t['priorite']) ?>
                            <?php if ($t['nb_commentaires']): ?><span><i class="bi bi-chat"></i> <?= (int) $t['nb_commentaires'] ?></span><?php endif; ?>
                            <span class="ms-auto d-flex align-items-center gap-1">
                                <?php if ($t['assigne_a'] === null): ?>
                                    <form method="post" action="actions.php" class="m-0">
                                        <?= csrf_champ() ?>
                                        <input type="hidden" name="do" value="prendre">
                                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                        <button class="btn btn-xs btn-outline-choco">Je prends</button>
                                    </form>
                                <?php else: ?>
                                    <?= avatar($t['prenom'], $t['couleur'], 'avatar-sm') ?>
                                <?php endif; ?>
                                <span class="dropdown">
                                    <button class="btn btn-xs btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Changer le statut">
                                        <i class="bi bi-three-dots-vertical"></i><span class="visually-hidden">Changer le statut</span>
                                    </button>
                                    <form method="post" action="actions.php" class="dropdown-menu dropdown-menu-end">
                                        <?= csrf_champ() ?>
                                        <input type="hidden" name="do" value="statut">
                                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                        <?php foreach (STATUTS as $cle => $libelle): if ($cle === $statut) continue; ?>
                                            <button class="dropdown-item" name="statut" value="<?= $cle ?>">→ <?= $libelle ?></button>
                                        <?php endforeach; ?>
                                    </form>
                                </span>
                            </span>
                        </div>
                    </article>
                <?php endforeach; ?>
                <p class="kanban-vide"<?= $tickets ? ' hidden' : '' ?>>Aucun ticket</p>
            </div>
        </section>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../src/vues/footer.php'; ?>
