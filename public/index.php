<?php
declare(strict_types=1);
require __DIR__ . '/../src/app.php';

$moi = exiger_connexion();
$pdo = db();
$semaine = semaine_courante();
$colonnesTicket = 't.id, t.ref, t.titre, t.semaine, t.priorite, t.statut, m.prenom, m.couleur';

// Le bonus ne compte pas dans l'avancement : il n'est pris en compte que si tout le reste est fait
$global = $pdo->query("SELECT COUNT(*) total, SUM(statut = 'fait') faits, SUM(statut = 'en_cours') en_cours,
                              SUM(statut = 'relecture') relecture, SUM(assigne_a IS NULL AND statut <> 'fait') libres
                       FROM tickets WHERE section <> 'Bonus'")->fetch();

$parSection = $pdo->query("SELECT section, COUNT(*) total, SUM(statut = 'fait') faits, SUM(statut IN ('en_cours', 'relecture')) actifs
                           FROM tickets GROUP BY section ORDER BY section")->fetchAll();

$parSemaine = $pdo->query("SELECT semaine, COUNT(*) total, SUM(statut = 'fait') faits
                           FROM tickets WHERE semaine IS NOT NULL GROUP BY semaine ORDER BY semaine")->fetchAll();

$parMembre = $pdo->query("SELECT m.id, m.prenom, m.couleur, m.role, COUNT(t.id) total,
                                 COALESCE(SUM(t.statut = 'fait'), 0) fait, COALESCE(SUM(t.statut = 'relecture'), 0) relecture,
                                 COALESCE(SUM(t.statut = 'en_cours'), 0) en_cours, COALESCE(SUM(t.statut = 'a_faire'), 0) a_faire
                          FROM membres m LEFT JOIN tickets t ON t.assigne_a = m.id AND t.section <> 'Bonus'
                          GROUP BY m.id ORDER BY m.prenom")->fetchAll();

$st = $pdo->prepare("SELECT $colonnesTicket FROM tickets t LEFT JOIN membres m ON m.id = t.assigne_a
                     WHERE t.statut <> 'fait' AND t.semaine < ? ORDER BY t.semaine, " . ORDRE_PRIORITE_SQL);
$st->execute([$semaine]);
$enRetard = $st->fetchAll();

$st = $pdo->prepare("SELECT $colonnesTicket FROM tickets t LEFT JOIN membres m ON m.id = t.assigne_a
                     WHERE t.assigne_a IS NULL AND t.statut <> 'fait' AND t.section <> 'Bonus' AND t.semaine <= ?
                     ORDER BY t.semaine, " . ORDRE_PRIORITE_SQL . " LIMIT 12");
$st->execute([$semaine]);
$aPrendre = $st->fetchAll();

$aRelire = $pdo->query("SELECT $colonnesTicket FROM tickets t LEFT JOIN membres m ON m.id = t.assigne_a
                        WHERE t.statut = 'relecture' ORDER BY t.maj_le")->fetchAll();

$scenarios = $pdo->query('SELECT COUNT(*) total, COALESCE(SUM(ok), 0) ok FROM scenarios')->fetch();

$activite = $pdo->query('SELECT h.*, m.prenom, m.couleur, t.ref FROM historique h
                         LEFT JOIN membres m ON m.id = h.membre_id LEFT JOIN tickets t ON t.id = h.ticket_id
                         ORDER BY h.id DESC LIMIT 20')->fetchAll();

function liste_tickets(array $tickets, string $vide): void
{
    if (!$tickets) {
        echo '<p class="text-muted small mb-0">' . e($vide) . '</p>';
        return;
    }
    echo '<ul class="liste-tickets">';
    foreach ($tickets as $t) {
        echo '<li><a href="ticket.php?id=' . (int) $t['id'] . '"><span class="ref">' . e($t['ref'] ?? '#' . $t['id']) . '</span> ' . e($t['titre']) . '</a>'
            . '<span class="d-flex gap-1 align-items-center">' . badge_priorite($t['priorite'])
            . ($t['semaine'] ? '<span class="badge text-bg-light">S' . (int) $t['semaine'] . '</span>' : '')
            . avatar($t['prenom'], $t['couleur'], 'avatar-sm') . '</span></li>';
    }
    echo '</ul>';
}

$titre = 'Tableau de bord';
$page = 'index';
require __DIR__ . '/../src/vues/header.php';
$pctGlobal = pourcentage($global['faits'], $global['total']);
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
    <div>
        <h1 class="h3 mb-0">Salut <?= e($moi['prenom']) ?> 👋</h1>
        <p class="text-muted mb-0">On est en <strong>semaine <?= $semaine ?></strong> sur 4 du projet Maison Rosalie.</p>
    </div>
    <a class="btn btn-outline-choco" href="tickets.php?membre=<?= (int) $moi['id'] ?>"><i class="bi bi-person"></i> Mes tickets</a>
</div>

<div class="row g-3 mb-4">
    <?php
    $tuiles = [
        ['Avancement', $pctGlobal . ' %', ($global['faits'] ?? 0) . ' / ' . $global['total'] . ' tickets faits', 'tickets.php'],
        ['En cours', (int) $global['en_cours'], 'quelqu\'un travaille dessus', 'tickets.php'],
        ['À relire', (int) $global['relecture'], 'attendent une relecture', 'tickets.php'],
        ['Sans personne', (int) $global['libres'], 'tickets à se répartir', 'tickets.php?membre=libre'],
        ['En retard', count($enRetard), 'semaine prévue dépassée', '#retard'],
        ['Scénarios de démo', $scenarios['ok'] . ' / ' . $scenarios['total'], 'validés', 'scenarios.php'],
    ];
    foreach ($tuiles as [$libelle, $valeur, $sousTitre, $lien]): ?>
        <div class="col-6 col-md-4 col-xl-2">
            <a class="tuile" href="<?= e($lien) ?>">
                <span class="tuile-libelle"><?= e($libelle) ?></span>
                <span class="tuile-valeur"><?= e($valeur) ?></span>
                <span class="tuile-sous"><?= e($sousTitre) ?></span>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <section class="panneau mb-4">
            <h2 class="h5">Qui fait quoi</h2>
            <div class="row g-3">
                <?php foreach ($parMembre as $m): ?>
                    <div class="col-md-4">
                        <a class="carte-membre" href="tickets.php?membre=<?= (int) $m['id'] ?>">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <?= avatar($m['prenom'], $m['couleur']) ?>
                                <div>
                                    <strong><?= e($m['prenom']) ?></strong>
                                    <div class="small text-muted"><?= e($m['role'] ?: '—') ?></div>
                                </div>
                                <span class="ms-auto fw-bold"><?= pourcentage($m['fait'], $m['total']) ?> %</span>
                            </div>
                            <div class="barre-empilee" role="img" aria-label="<?= (int) $m['fait'] ?> faits, <?= (int) $m['relecture'] ?> en relecture, <?= (int) $m['en_cours'] ?> en cours, <?= (int) $m['a_faire'] ?> à faire">
                                <?php foreach (['fait', 'relecture', 'en_cours', 'a_faire'] as $s): ?>
                                    <span class="seg-<?= $s ?>" style="width:<?= pourcentage($m[$s], $m['total']) ?>%"></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="small text-muted mt-2">
                                <?= (int) $m['total'] ?> tickets · <?= (int) $m['fait'] ?> faits · <?= (int) $m['en_cours'] ?> en cours · <?= (int) $m['relecture'] ?> à relire
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="legende mt-3">
                <?php foreach (['fait', 'relecture', 'en_cours', 'a_faire'] as $s): ?>
                    <span><i class="pastille seg-<?= $s ?>"></i><?= STATUTS[$s] ?></span>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="row g-4 mb-4">
            <section class="col-md-6">
                <div class="panneau h-100">
                    <h2 class="h5">Avancement par section</h2>
                    <?php foreach ($parSection as $s): $pct = pourcentage($s['faits'], $s['total']); ?>
                        <a class="ligne-progression" href="tickets.php?section=<?= urlencode($s['section']) ?>">
                            <span class="d-flex justify-content-between small">
                                <span><?= e($s['section']) ?></span>
                                <span class="text-muted"><?= (int) $s['faits'] ?>/<?= (int) $s['total'] ?></span>
                            </span>
                            <span class="progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= e($s['section']) ?>">
                                <span class="progress-bar" style="width:<?= $pct ?>%"></span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="col-md-6">
                <div class="panneau h-100">
                    <h2 class="h5">Avancement par semaine</h2>
                    <?php foreach ($parSemaine as $s): $pct = pourcentage($s['faits'], $s['total']); ?>
                        <a class="ligne-progression<?= (int) $s['semaine'] === $semaine ? ' semaine-courante' : '' ?>" href="tickets.php?semaine=<?= (int) $s['semaine'] ?>">
                            <span class="d-flex justify-content-between small">
                                <span>Semaine <?= (int) $s['semaine'] ?><?= (int) $s['semaine'] === $semaine ? ' · maintenant' : '' ?></span>
                                <span class="text-muted"><?= (int) $s['faits'] ?>/<?= (int) $s['total'] ?></span>
                            </span>
                            <span class="progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Semaine <?= (int) $s['semaine'] ?>">
                                <span class="progress-bar" style="width:<?= $pct ?>%"></span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                    <p class="small text-muted mt-3 mb-0">
                        S1 : stack, modèle de données, wireframes, dépôt, logo · S2 : base, 4 pages, recettes affichées ·
                        S3 : comptes, étoiles, commentaires, top 3, contact, sécurité · S4 : tests, docs, rapport, démo.
                    </p>
                </div>
            </section>
        </div>

        <div class="row g-4">
            <section class="col-md-6">
                <div class="panneau h-100">
                    <h2 class="h5"><i class="bi bi-hand-index"></i> À prendre maintenant</h2>
                    <p class="small text-muted">Tickets sans personne, prévus jusqu'à cette semaine.</p>
                    <?php liste_tickets($aPrendre, 'Tout ce qui est prévu est déjà réparti. 🎉'); ?>
                </div>
            </section>
            <section class="col-md-6">
                <div class="panneau h-100">
                    <h2 class="h5"><i class="bi bi-eyeglasses"></i> À relire</h2>
                    <p class="small text-muted">Une relecture par un autre membre avant la fusion sur main.</p>
                    <?php liste_tickets($aRelire, 'Rien à relire pour le moment.'); ?>
                </div>
            </section>
            <section class="col-12" id="retard">
                <div class="panneau">
                    <h2 class="h5"><i class="bi bi-exclamation-triangle"></i> En retard</h2>
                    <?php liste_tickets($enRetard, 'Aucun retard. Bravo !'); ?>
                </div>
            </section>
        </div>
    </div>

    <aside class="col-lg-4">
        <section class="panneau">
            <h2 class="h5">Activité récente</h2>
            <?php if (!$activite): ?>
                <p class="text-muted small mb-0">Rien pour l'instant. Commencez par vous répartir les tickets !</p>
            <?php else: ?>
                <ul class="liste-histo"><?php foreach ($activite as $h) echo ligne_historique($h); ?></ul>
            <?php endif; ?>
        </section>
    </aside>
</div>
<?php require __DIR__ . '/../src/vues/footer.php'; ?>
