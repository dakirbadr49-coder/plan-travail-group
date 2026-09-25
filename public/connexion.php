<?php
declare(strict_types=1);
require __DIR__ . '/../src/app.php';

const MAX_ECHECS = 5;
const FENETRE_BLOCAGE_MIN = 15;

if (moi()) {
    rediriger('index.php');
}

// Derrière le tunnel (proxy local), la vraie IP arrive dans un en-tête
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (in_array($ip, ['127.0.0.1', '::1'], true) && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $ip = substr((string) $_SERVER['HTTP_CF_CONNECTING_IP'], 0, 45);
}

$erreur = '';
$prenomChoisi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_post();
    $prenomChoisi = trim((string) ($_POST['prenom'] ?? ''));
    $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');

    $st = db()->prepare('SELECT COUNT(*) FROM connexions_echouees WHERE ip = ? AND cree_le > NOW() - INTERVAL ? MINUTE');
    $st->execute([$ip, FENETRE_BLOCAGE_MIN]);

    if ((int) $st->fetchColumn() >= MAX_ECHECS) {
        $erreur = 'Trop de tentatives. Réessaie dans ' . FENETRE_BLOCAGE_MIN . ' minutes.';
    } else {
        $st = db()->prepare('SELECT id, mot_de_passe FROM membres WHERE prenom = ?');
        $st->execute([$prenomChoisi]);
        $compte = $st->fetch();

        if ($compte && $compte['mot_de_passe'] !== null && password_verify($motDePasse, $compte['mot_de_passe'])) {
            db()->prepare('DELETE FROM connexions_echouees WHERE ip = ?')->execute([$ip]);
            connecter($compte);
            flash('Bienvenue ' . $prenomChoisi . ' !');
            rediriger('index.php');
        }
        db()->prepare('INSERT INTO connexions_echouees (ip) VALUES (?)')->execute([$ip]);
        $erreur = 'Prénom ou mot de passe incorrect.';
    }
}

$titre = 'Connexion';
$page = 'connexion';
require __DIR__ . '/../src/vues/header.php';
?>
<section class="carte-connexion">
    <h1 class="h3 mb-1">Connexion</h1>
    <p class="text-muted">L'outil de suivi de l'équipe Maison Rosalie.</p>
    <?php if ($erreur): ?>
        <div class="alert alert-danger" role="alert"><?= e($erreur) ?></div>
    <?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_champ() ?>
        <div class="mb-3">
            <label class="form-label" for="prenom">Qui es-tu ?</label>
            <select class="form-select" id="prenom" name="prenom" required>
                <?php foreach (membres() as $m): ?>
                    <option value="<?= e($m['prenom']) ?>"<?= $m['prenom'] === $prenomChoisi ? ' selected' : '' ?>><?= e($m['prenom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="mot_de_passe">Mot de passe</label>
            <input class="form-control" type="password" id="mot_de_passe" name="mot_de_passe" required autocomplete="current-password" autofocus>
        </div>
        <button class="btn btn-choco w-100">Se connecter</button>
    </form>
</section>
<?php require __DIR__ . '/../src/vues/footer.php'; ?>
