<?php
/** @var string $titre  @var string $page */
$moi = moi();
$liens = [
    'index'     => ['index.php', 'bi-speedometer2', 'Tableau de bord'],
    'tickets'   => ['tickets.php', 'bi-kanban', 'Tickets'],
    'scenarios' => ['scenarios.php', 'bi-check2-square', 'Scénarios de démo'],
    'equipe'    => ['equipe.php', 'bi-people', 'Équipe'],
];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf" content="<?= e(csrf_token()) ?>">
    <title><?= e($titre) ?> · Suivi Rosalie</title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body data-dernier-evenement="<?= $moi ? dernier_evenement() : 0 ?>">
<nav class="navbar navbar-expand-lg navbar-dark app-nav">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php"><img src="assets/favicon.svg" alt="" width="26" height="26"> Suivi Rosalie</a>
        <?php if ($moi): ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu" aria-controls="menu" aria-expanded="false" aria-label="Ouvrir le menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menu">
                <ul class="navbar-nav me-auto">
                    <?php foreach ($liens as $cle => [$url, $icone, $libelle]): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $page === $cle ? ' active' : '' ?>" href="<?= $url ?>"<?= $page === $cle ? ' aria-current="page"' : '' ?>>
                                <i class="bi <?= $icone ?>"></i> <?= $libelle ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <a class="btn btn-sm btn-caramel" href="ticket.php"><i class="bi bi-plus-lg"></i> Nouveau ticket</a>
                    <span class="text-white-50 small ms-lg-2"><?= avatar($moi['prenom'], $moi['couleur'], 'avatar-sm') ?> <?= e($moi['prenom']) ?></span>
                    <form method="post" action="actions.php" class="m-0">
                        <?= csrf_champ() ?>
                        <input type="hidden" name="do" value="deconnexion">
                        <button class="btn btn-sm btn-outline-light" title="Se déconnecter"><i class="bi bi-box-arrow-right"></i><span class="visually-hidden">Se déconnecter</span></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</nav>
<div id="bandeau-nouveautes" class="bandeau-nouveautes" hidden>
    <i class="bi bi-bell"></i> <span></span> <a href="" class="js-recharger">Actualiser</a>
</div>
<main class="container-fluid py-4 px-3 px-lg-4">
    <?php foreach (flashs() as [$type, $message]): ?>
        <div class="alert alert-<?= e($type) ?> alert-dismissible fade show" role="alert">
            <?= e($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    <?php endforeach; ?>
