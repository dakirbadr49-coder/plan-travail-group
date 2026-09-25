<?php
declare(strict_types=1);
require __DIR__ . '/../src/app.php';

$moi = exiger_connexion();
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_post();
    $do = (string) ($_POST['do'] ?? '');

    if ($do === 'membre') {
        $id = (int) ($_POST['id'] ?? 0);
        $prenom = trim((string) ($_POST['prenom'] ?? ''));
        $role = mb_substr(trim((string) ($_POST['role'] ?? '')), 0, 100);
        $couleur = (string) ($_POST['couleur'] ?? '');
        if (membre_par_id($id) === null) {
            page_erreur('Membre inconnu.', 404);
        }
        if (mb_strlen($prenom) < 1 || mb_strlen($prenom) > 50) {
            $erreurs[$id] = 'Le prénom doit faire entre 1 et 50 caractères.';
        } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $couleur)) {
            $erreurs[$id] = 'Couleur invalide.';
        } else {
            try {
                db()->prepare('UPDATE membres SET prenom = ?, role = ?, couleur = ? WHERE id = ?')->execute([$prenom, $role, $couleur, $id]);
                journaliser(null, 'equipe', 'a mis à jour la fiche de ' . $prenom);
                flash('Fiche de ' . $prenom . ' enregistrée.');
                rediriger('equipe.php');
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                $erreurs[$id] = 'Ce prénom est déjà pris.';
            }
        }
    } elseif ($do === 'mot_de_passe') {
        $st = db()->prepare('SELECT mot_de_passe FROM membres WHERE id = ?');
        $st->execute([$moi['id']]);
        $actuel = (string) $st->fetchColumn();
        $nouveau = (string) ($_POST['nouveau'] ?? '');
        if (!password_verify((string) ($_POST['actuel'] ?? ''), $actuel)) {
            $erreurs['mdp'] = 'Mot de passe actuel incorrect.';
        } elseif (mb_strlen($nouveau) < 8) {
            $erreurs['mdp'] = 'Le nouveau mot de passe doit faire au moins 8 caractères.';
        } elseif ($nouveau !== (string) ($_POST['confirmation'] ?? '')) {
            $erreurs['mdp'] = 'La confirmation ne correspond pas.';
        } else {
            db()->prepare('UPDATE membres SET mot_de_passe = ? WHERE id = ?')->execute([password_hash($nouveau, PASSWORD_DEFAULT), $moi['id']]);
            session_regenerate_id(true);
            flash('Mot de passe changé.');
            rediriger('equipe.php');
        }
    } elseif ($do === 'recettes') {
        foreach ((array) ($_POST['recette'] ?? []) as $numero => $r) {
            $numero = (int) $numero;
            if ($numero < 1 || $numero > 5 || !is_array($r)) {
                continue;
            }
            $categorie = in_array($r['categorie'] ?? '', CATEGORIES, true) ? $r['categorie'] : null;
            $difficulte = isset(DIFFICULTES[$r['difficulte'] ?? '']) ? $r['difficulte'] : null;
            $membreId = (int) ($r['membre_id'] ?? 0);
            db()->prepare('UPDATE recettes SET nom = ?, categorie = ?, difficulte = ?, membre_id = ? WHERE numero = ?')->execute([
                mb_substr(trim((string) ($r['nom'] ?? '')), 0, 100), $categorie, $difficulte,
                membre_par_id($membreId) ? $membreId : null, $numero,
            ]);
        }
        journaliser(null, 'equipe', 'a mis à jour la répartition des recettes');
        flash('Répartition des recettes enregistrée.');
        rediriger('equipe.php#recettes');
    } elseif ($do === 'date_debut') {
        $date = (string) ($_POST['date_debut'] ?? '');
        $d = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            $erreurs['date'] = 'Date invalide.';
        } else {
            db()->prepare('UPDATE parametres SET valeur = ? WHERE cle = ?')->execute([$date, 'date_debut']);
            journaliser(null, 'equipe', 'a fixé le début du projet au ' . $d->format('d/m/Y'));
            flash('Date de début enregistrée.');
            rediriger('equipe.php');
        }
    } else {
        page_erreur('Action inconnue.', 400);
    }
}

$stats = db()->query("SELECT m.id, COUNT(t.id) total, COALESCE(SUM(t.statut = 'fait'), 0) faits
                      FROM membres m LEFT JOIN tickets t ON t.assigne_a = m.id GROUP BY m.id")->fetchAll(PDO::FETCH_UNIQUE);

$st = db()->prepare('SELECT h.*, m.prenom, m.couleur, t.ref FROM historique h
                     LEFT JOIN membres m ON m.id = h.membre_id LEFT JOIN tickets t ON t.id = h.ticket_id
                     WHERE h.membre_id = ? ORDER BY h.id DESC LIMIT 8');
$activiteParMembre = [];
foreach (membres() as $m) {
    $st->execute([$m['id']]);
    $activiteParMembre[$m['id']] = $st->fetchAll();
}

$recettes = db()->query('SELECT * FROM recettes ORDER BY numero')->fetchAll();
$categoriesUtilisees = count(array_unique(array_filter(array_column($recettes, 'categorie'))));
$difficultesUtilisees = array_unique(array_filter(array_column($recettes, 'difficulte')));
$verifications = [
    ['5 recettes nommées', count(array_filter(array_column($recettes, 'nom'))) === 5],
    ['Au moins 3 catégories différentes (' . $categoriesUtilisees . ' pour l\'instant)', $categoriesUtilisees >= 3],
    ['Au moins une recette facile, une moyenne et une difficile', count($difficultesUtilisees) === 3],
    ['Chaque membre a au moins une recette', !array_diff(array_column(membres(), 'id'), array_column($recettes, 'membre_id'))],
];

$titre = 'Équipe';
$page = 'equipe';
require __DIR__ . '/../src/vues/header.php';
?>
<h1 class="h3 mb-3">L'équipe</h1>

<div class="row g-4 mb-4">
    <?php foreach (membres() as $m): $id = (int) $m['id']; ?>
        <section class="col-lg-4">
            <div class="panneau h-100">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <?= avatar($m['prenom'], $m['couleur']) ?>
                    <div>
                        <h2 class="h5 mb-0"><?= e($m['prenom']) ?><?= $id === (int) $moi['id'] ? ' <span class="badge text-bg-light">toi</span>' : '' ?></h2>
                        <div class="small text-muted"><?= (int) ($stats[$id]['faits'] ?? 0) ?> faits sur <?= (int) ($stats[$id]['total'] ?? 0) ?> tickets</div>
                    </div>
                    <a class="btn btn-sm btn-outline-choco ms-auto" href="tickets.php?membre=<?= $id ?>">Ses tickets</a>
                </div>
                <form method="post" class="row g-2 mb-3" novalidate>
                    <?= csrf_champ() ?>
                    <input type="hidden" name="do" value="membre">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <div class="col-8">
                        <label class="form-label small" for="prenom-<?= $id ?>">Prénom</label>
                        <input class="form-control form-control-sm" id="prenom-<?= $id ?>" name="prenom" value="<?= e($m['prenom']) ?>" maxlength="50" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label small" for="couleur-<?= $id ?>">Couleur</label>
                        <input class="form-control form-control-sm form-control-color w-100" type="color" id="couleur-<?= $id ?>" name="couleur" value="<?= e($m['couleur']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small" for="role-<?= $id ?>">Rôle</label>
                        <input class="form-control form-control-sm" id="role-<?= $id ?>" name="role" value="<?= e($m['role']) ?>" maxlength="100" placeholder="Référent base de données, référent sécurité…">
                    </div>
                    <?php if (isset($erreurs[$id])): ?><div class="col-12 text-danger small"><?= e($erreurs[$id]) ?></div><?php endif; ?>
                    <div class="col-12"><button class="btn btn-sm btn-choco">Enregistrer</button></div>
                </form>
                <h3 class="h6 text-uppercase text-muted">Ce que <?= e($m['prenom']) ?> a fait récemment</h3>
                <?php if ($activiteParMembre[$id]): ?>
                    <ul class="liste-histo"><?php foreach ($activiteParMembre[$id] as $h) echo ligne_historique($h); ?></ul>
                <?php else: ?>
                    <p class="small text-muted mb-0">Rien encore.</p>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<section class="panneau mb-4" id="recettes">
    <h2 class="h5">Répartition des 5 recettes <span class="small text-muted">(section 7 du cahier des charges)</span></h2>
    <ul class="list-unstyled small mb-3">
        <?php foreach ($verifications as [$libelle, $ok]): ?>
            <li class="<?= $ok ? 'text-success' : 'text-danger' ?>"><i class="bi <?= $ok ? 'bi-check-circle-fill' : 'bi-x-circle' ?>"></i> <?= e($libelle) ?></li>
        <?php endforeach; ?>
    </ul>
    <form method="post">
        <?= csrf_champ() ?>
        <input type="hidden" name="do" value="recettes">
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th scope="col">N°</th><th scope="col">Recette</th><th scope="col">Catégorie</th><th scope="col">Développeur</th><th scope="col">Difficulté</th></tr></thead>
                <tbody>
                <?php foreach ($recettes as $r): $n = (int) $r['numero']; ?>
                    <tr>
                        <th scope="row"><?= $n ?></th>
                        <td><input class="form-control form-control-sm" name="recette[<?= $n ?>][nom]" value="<?= e($r['nom']) ?>" maxlength="100" aria-label="Nom de la recette <?= $n ?>" placeholder="Ex. Fondant au chocolat noir"></td>
                        <td>
                            <select class="form-select form-select-sm" name="recette[<?= $n ?>][categorie]" aria-label="Catégorie de la recette <?= $n ?>">
                                <option value="">—</option>
                                <?php foreach (CATEGORIES as $c): ?><option<?= $r['categorie'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="recette[<?= $n ?>][membre_id]" aria-label="Développeur de la recette <?= $n ?>">
                                <option value="">—</option>
                                <?php foreach (membres() as $m): ?><option value="<?= (int) $m['id'] ?>"<?= (int) $r['membre_id'] === (int) $m['id'] ? ' selected' : '' ?>><?= e($m['prenom']) ?></option><?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="recette[<?= $n ?>][difficulte]" aria-label="Difficulté de la recette <?= $n ?>">
                                <option value="">—</option>
                                <?php foreach (DIFFICULTES as $cle => $libelle): ?><option value="<?= $cle ?>"<?= $r['difficulte'] === $cle ? ' selected' : '' ?>><?= $libelle ?></option><?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button class="btn btn-sm btn-choco">Enregistrer la répartition</button>
    </form>
</section>

<div class="row g-4">
    <section class="col-md-6">
        <form method="post" class="panneau h-100" novalidate>
            <h2 class="h5">Changer mon mot de passe</h2>
            <?= csrf_champ() ?>
            <input type="hidden" name="do" value="mot_de_passe">
            <?php if (isset($erreurs['mdp'])): ?><div class="alert alert-danger py-2"><?= e($erreurs['mdp']) ?></div><?php endif; ?>
            <div class="mb-2">
                <label class="form-label small" for="actuel">Mot de passe actuel</label>
                <input class="form-control form-control-sm" type="password" id="actuel" name="actuel" required autocomplete="current-password">
            </div>
            <div class="mb-2">
                <label class="form-label small" for="nouveau">Nouveau mot de passe (8 caractères minimum)</label>
                <input class="form-control form-control-sm" type="password" id="nouveau" name="nouveau" required minlength="8" autocomplete="new-password">
            </div>
            <div class="mb-3">
                <label class="form-label small" for="confirmation">Confirmation</label>
                <input class="form-control form-control-sm" type="password" id="confirmation" name="confirmation" required minlength="8" autocomplete="new-password">
            </div>
            <button class="btn btn-sm btn-choco">Changer</button>
        </form>
    </section>
    <section class="col-md-6">
        <form method="post" class="panneau h-100" novalidate>
            <h2 class="h5">Début du projet</h2>
            <p class="small text-muted">Sert à calculer la semaine en cours (1 à 4) et les tickets en retard. Mets le lundi de la semaine 1.</p>
            <?= csrf_champ() ?>
            <input type="hidden" name="do" value="date_debut">
            <?php if (isset($erreurs['date'])): ?><div class="alert alert-danger py-2"><?= e($erreurs['date']) ?></div><?php endif; ?>
            <label class="form-label small" for="date_debut">Lundi de la semaine 1</label>
            <input class="form-control form-control-sm mb-3" type="date" id="date_debut" name="date_debut" value="<?= e(parametre('date_debut')) ?>" required>
            <button class="btn btn-sm btn-choco">Enregistrer</button>
        </form>
    </section>
</div>
<?php require __DIR__ . '/../src/vues/footer.php'; ?>
