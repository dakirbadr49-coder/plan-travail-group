<?php
declare(strict_types=1);
require __DIR__ . '/../src/app.php';

$moi = exiger_connexion();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_post();
    $numero = (int) ($_POST['numero'] ?? 0);
    $ok = ($_POST['ok'] ?? '') === '1';
    $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255);

    $st = db()->prepare('SELECT ok FROM scenarios WHERE numero = ?');
    $st->execute([$numero]);
    $avant = $st->fetchColumn();
    if ($avant === false) {
        page_erreur('Scénario inconnu.', 404);
    }
    db()->prepare('UPDATE scenarios SET ok = ?, note = ?, valide_par = IF(? = 1, ?, NULL), valide_le = IF(? = 1, NOW(), NULL) WHERE numero = ?')
        ->execute([(int) $ok, $note, (int) $ok, $moi['id'], (int) $ok, $numero]);
    if ((bool) $avant !== $ok) {
        journaliser(null, 'scenario', ($ok ? 'a validé' : 'a invalidé') . ' le scénario de démo n°' . $numero);
    }
    rediriger('scenarios.php#scenario-' . $numero);
}

$scenarios = db()->query('SELECT s.*, m.prenom, m.couleur FROM scenarios s LEFT JOIN membres m ON m.id = s.valide_par ORDER BY s.numero')->fetchAll();
$nbOk = count(array_filter($scenarios, fn($s) => $s['ok']));

$titre = 'Scénarios de démo';
$page = 'scenarios';
require __DIR__ . '/../src/vues/header.php';
?>
<h1 class="h3">Scénarios de recette finale <span class="text-muted fs-6"><?= $nbOk ?>/<?= count($scenarios) ?></span></h1>
<p class="text-muted">Le formateur rejouera ces 17 scénarios le jour de la démo, sur le site installé à partir du README. Testez-les vous-mêmes avant et cochez-les ici.</p>
<div class="progress mb-4" role="progressbar" aria-valuenow="<?= pourcentage($nbOk, count($scenarios)) ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Scénarios validés">
    <div class="progress-bar" style="width:<?= pourcentage($nbOk, count($scenarios)) ?>%"></div>
</div>

<div class="panneau p-0">
    <?php foreach ($scenarios as $s): ?>
        <form method="post" class="ligne-scenario<?= $s['ok'] ? ' valide' : '' ?>" id="scenario-<?= (int) $s['numero'] ?>">
            <?= csrf_champ() ?>
            <input type="hidden" name="numero" value="<?= (int) $s['numero'] ?>">
            <input type="hidden" name="ok" value="0">
            <div class="form-check m-0">
                <input class="form-check-input js-autosubmit" type="checkbox" name="ok" value="1" id="ok-<?= (int) $s['numero'] ?>"<?= $s['ok'] ? ' checked' : '' ?>>
                <label class="form-check-label" for="ok-<?= (int) $s['numero'] ?>">
                    <strong><?= (int) $s['numero'] ?>.</strong> <?= e($s['description']) ?>
                </label>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <label class="visually-hidden" for="note-<?= (int) $s['numero'] ?>">Remarque sur le scénario <?= (int) $s['numero'] ?></label>
                <input class="form-control form-control-sm" id="note-<?= (int) $s['numero'] ?>" name="note" maxlength="255" value="<?= e($s['note']) ?>" placeholder="Remarque (ce qui bloque…)">
                <button class="btn btn-sm btn-outline-choco" title="Enregistrer la remarque"><i class="bi bi-check-lg"></i><span class="visually-hidden">Enregistrer</span></button>
                <?php if ($s['ok']): ?>
                    <span class="small text-muted text-nowrap" title="Validé le <?= e(date_courte($s['valide_le'])) ?>"><?= avatar($s['prenom'], $s['couleur'], 'avatar-sm') ?></span>
                <?php endif; ?>
            </div>
        </form>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../src/vues/footer.php'; ?>
