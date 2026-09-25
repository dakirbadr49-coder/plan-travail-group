<?php
// Définit (ou réinitialise) le mot de passe d'un membre, en ligne de commande uniquement.
// Usage : php outils/definir-mdp.php <Prénom> [mot de passe]
// Sans mot de passe fourni, un mot de passe aléatoire est généré et affiché.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$prenom = $argv[1] ?? '';
if ($prenom === '') {
    fwrite(STDERR, "Usage : php outils/definir-mdp.php <Prénom> [mot de passe]\n");
    exit(1);
}

$c = require __DIR__ . '/../config.php';
$pdo = new PDO("mysql:host={$c['db_host']};port={$c['db_port']};dbname={$c['db_name']};charset=utf8mb4", $c['db_user'], $c['db_pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$motDePasse = $argv[2] ?? substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(12))), 0, 10);
if (strlen($motDePasse) < 8) {
    fwrite(STDERR, "Le mot de passe doit faire au moins 8 caractères.\n");
    exit(1);
}

$st = $pdo->prepare('UPDATE membres SET mot_de_passe = ? WHERE prenom = ?');
$st->execute([password_hash($motDePasse, PASSWORD_DEFAULT), $prenom]);

if ($st->rowCount() === 0) {
    fwrite(STDERR, "Aucun membre nommé « $prenom ».\n");
    exit(1);
}
echo "$prenom : $motDePasse\n";
