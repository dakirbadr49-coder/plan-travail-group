<?php
// Point d'entrée commun : session, base de données, sécurité, helpers d'affichage.
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/erreurs.log');

session_name('suivi_rosalie');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
    'use_strict_mode' => true,
]);

const STATUTS = [
    'a_faire'   => 'À faire',
    'en_cours'  => 'En cours',
    'relecture' => 'En relecture',
    'fait'      => 'Fait',
];
const SECTIONS    = ['Organisation', 'Frontend', 'Backend', 'Base de données', 'Sécurité', 'Qualité', 'Recettes', 'Livrables', 'Bonus'];
const PRIORITES   = ['critique' => 'Critique', 'haute' => 'Haute', 'normale' => 'Normale', 'basse' => 'Basse'];
const CATEGORIES  = ['Gâteaux', 'Mousses', 'Boissons', 'Glacé'];
const DIFFICULTES = ['facile' => 'Facile', 'moyen' => 'Moyen', 'difficile' => 'Difficile'];
const ORDRE_PRIORITE_SQL = "FIELD(t.priorite, 'critique', 'haute', 'normale', 'basse')";

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = require __DIR__ . '/../config.php';
        $dsn = "mysql:host={$c['db_host']};port={$c['db_port']};dbname={$c['db_name']};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('Connexion BDD impossible : ' . $e->getMessage());
            page_erreur('La base de données est indisponible. Vérifie que WAMP (MariaDB) est bien démarré.', 503);
        }
    }
    return $pdo;
}

// Toute exception non prévue : journal + message propre
set_exception_handler(function (Throwable $e): void {
    error_log((string) $e);
    page_erreur('Une erreur inattendue est survenue.', 500);
});

function e(mixed $valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES, 'UTF-8');
}

function est_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

function repondre_json(array $donnees, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE);
    exit;
}

// Page d'erreur autonome (ne dépend pas de la base, pour pouvoir s'afficher quand elle est coupée)
function page_erreur(string $message, int $code = 500): never
{
    if (est_ajax()) {
        repondre_json(['ok' => false, 'message' => $message], $code);
    }
    http_response_code($code);
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Oups · Suivi Rosalie</title><link rel="stylesheet" href="assets/style.css"></head>'
        . '<body class="page-erreur"><main><h1>Oups</h1><p>' . e($message) . '</p><a href="index.php">Retour au tableau de bord</a></main></body></html>';
    exit;
}

function rediriger(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// Revient sur la page précédente seulement si elle appartient au même site
function page_precedente(string $defaut = 'index.php'): string
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    return ($ref !== '' && parse_url($ref, PHP_URL_HOST) === parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) ? $ref : $defaut;
}

/* ---------- Messages flash ---------- */

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function flashs(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_champ(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verifier_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        page_erreur('Formulaire expiré ou invalide : recharge la page et réessaie.', 403);
    }
}

/* ---------- Comptes ---------- */

const DUREE_SESSION = 8 * 3600; // déconnexion après 8 h d'inactivité

function moi(): ?array
{
    static $membre = false;
    if ($membre === false) {
        $membre = null;
        if (!empty($_SESSION['membre_id'])) {
            if (time() - ($_SESSION['derniere_activite'] ?? 0) > DUREE_SESSION) {
                deconnecter();
                return null;
            }
            $_SESSION['derniere_activite'] = time();
            $membre = membre_par_id((int) $_SESSION['membre_id']);
        }
    }
    return $membre;
}

function exiger_connexion(): array
{
    $membre = moi();
    if ($membre === null) {
        if (est_ajax()) {
            page_erreur('Ta session a expiré, reconnecte-toi.', 401);
        }
        rediriger('connexion.php');
    }
    return $membre;
}

function connecter(array $membre): void
{
    session_regenerate_id(true);
    $_SESSION['membre_id'] = (int) $membre['id'];
    $_SESSION['derniere_activite'] = time();
}

function deconnecter(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ---------- Données partagées ---------- */

function membres(): array
{
    static $liste = null;
    if ($liste === null) {
        $liste = db()->query('SELECT id, prenom, role, couleur, mot_de_passe IS NOT NULL AS a_compte FROM membres ORDER BY prenom')->fetchAll();
    }
    return $liste;
}

function membre_par_id(int $id): ?array
{
    foreach (membres() as $m) {
        if ((int) $m['id'] === $id) {
            return $m;
        }
    }
    return null;
}

function parametre(string $cle, string $defaut = ''): string
{
    $st = db()->prepare('SELECT valeur FROM parametres WHERE cle = ?');
    $st->execute([$cle]);
    $valeur = $st->fetchColumn();
    return $valeur === false ? $defaut : (string) $valeur;
}

// Semaine du projet (1 à 4) calculée à partir de la date de début
function semaine_courante(): int
{
    $debut = DateTime::createFromFormat('!Y-m-d', parametre('date_debut'));
    if (!$debut) {
        return 1;
    }
    $jours = (int) $debut->diff(new DateTime('today'))->format('%r%a');
    return max(1, min(4, intdiv($jours, 7) + 1));
}

function journaliser(?int $ticketId, string $action, string $detail = ''): void
{
    $membre = moi();
    db()->prepare('INSERT INTO historique (ticket_id, membre_id, action, detail) VALUES (?, ?, ?, ?)')
        ->execute([$ticketId, $membre['id'] ?? null, $action, mb_substr($detail, 0, 255)]);
}

function dernier_evenement(): int
{
    return (int) db()->query('SELECT COALESCE(MAX(id), 0) FROM historique')->fetchColumn();
}

/* ---------- Petits morceaux d'affichage ---------- */

function avatar(?string $prenom, ?string $couleur, string $taille = ''): string
{
    if ($prenom === null) {
        return '<span class="avatar avatar-vide ' . $taille . '" title="Personne">?</span>';
    }
    return '<span class="avatar ' . $taille . '" style="background:' . e($couleur) . '" title="' . e($prenom) . '">'
        . e(mb_strtoupper(mb_substr($prenom, 0, 1))) . '</span>';
}

function badge_statut(string $statut): string
{
    return '<span class="badge statut-' . e($statut) . '">' . e(STATUTS[$statut] ?? $statut) . '</span>';
}

function badge_priorite(string $priorite): string
{
    return '<span class="badge prio prio-' . e($priorite) . '">' . e(PRIORITES[$priorite] ?? $priorite) . '</span>';
}

function pourcentage(int|string|null $partie, int|string|null $total): int
{
    return (int) $total > 0 ? (int) round((int) $partie * 100 / (int) $total) : 0;
}

function date_courte(?string $date): string
{
    return $date ? date('d/m H:i', strtotime($date)) : '';
}

const ICONES_HISTORIQUE = [
    'creation'     => 'bi-plus-circle',
    'statut'       => 'bi-arrow-left-right',
    'assignation'  => 'bi-person-check',
    'modification' => 'bi-pencil',
    'commentaire'  => 'bi-chat-left-text',
    'suppression'  => 'bi-trash',
    'scenario'     => 'bi-check2-square',
    'equipe'       => 'bi-people',
];

function ligne_historique(array $h, bool $avecTicket = true): string
{
    $html = '<li class="histo">' . avatar($h['prenom'] ?? null, $h['couleur'] ?? null, 'avatar-sm')
        . '<div><i class="bi ' . (ICONES_HISTORIQUE[$h['action']] ?? 'bi-dot') . '"></i> '
        . '<strong>' . e($h['prenom'] ?? 'Quelqu\'un') . '</strong> ' . e($h['detail']);
    if ($avecTicket && !empty($h['ticket_id'])) {
        $html .= ' · <a href="ticket.php?id=' . (int) $h['ticket_id'] . '">' . e($h['ref'] ?: '#' . $h['ticket_id']) . '</a>';
    }
    return $html . '<div class="text-muted small">' . e(date_courte($h['cree_le'])) . '</div></div></li>';
}
