<?php
// Opérations sur les tickets, partagées par ticket.php et actions.php.
declare(strict_types=1);

function trouver_ticket(int $id): ?array
{
    $st = db()->prepare('SELECT t.*, m.prenom, m.couleur FROM tickets t LEFT JOIN membres m ON m.id = t.assigne_a WHERE t.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function ticket_ou_404(int $id): array
{
    return trouver_ticket($id) ?? page_erreur('Ce ticket n\'existe pas (ou a été supprimé).', 404);
}

function changer_statut(array $ticket, string $statut): void
{
    if (!isset(STATUTS[$statut])) {
        page_erreur('Statut inconnu.', 422);
    }
    if ($ticket['statut'] === $statut) {
        return;
    }
    db()->prepare("UPDATE tickets SET statut = ?, fait_le = IF(? = 'fait', NOW(), NULL) WHERE id = ?")
        ->execute([$statut, $statut, $ticket['id']]);
    journaliser((int) $ticket['id'], 'statut', 'a passé ' . STATUTS[$ticket['statut']] . ' → ' . STATUTS[$statut]);
}

function assigner(array $ticket, ?int $membreId): void
{
    if ($membreId !== null && membre_par_id($membreId) === null) {
        page_erreur('Membre inconnu.', 422);
    }
    $actuel = $ticket['assigne_a'] === null ? null : (int) $ticket['assigne_a'];
    if ($actuel === $membreId) {
        return;
    }
    db()->prepare('UPDATE tickets SET assigne_a = ? WHERE id = ?')->execute([$membreId, $ticket['id']]);
    $detail = $membreId === null ? 'a retiré l\'assignation' : 'a assigné à ' . membre_par_id($membreId)['prenom'];
    if ($membreId !== null && $membreId === (int) (moi()['id'] ?? 0)) {
        $detail = 'a pris le ticket';
    }
    journaliser((int) $ticket['id'], 'assignation', $detail);
}

/**
 * Valide les champs du formulaire ticket.
 * @return array{0: array, 1: array<string,string>} [valeurs nettoyées, erreurs par champ]
 */
function valider_ticket(array $in, ?int $idActuel): array
{
    $semaine = trim((string) ($in['semaine'] ?? ''));
    $assigne = trim((string) ($in['assigne_a'] ?? ''));
    $v = [
        'ref'         => strtoupper(trim((string) ($in['ref'] ?? ''))),
        'titre'       => trim((string) ($in['titre'] ?? '')),
        'description' => trim((string) ($in['description'] ?? '')),
        'section'     => (string) ($in['section'] ?? ''),
        'semaine'     => $semaine === '' ? null : (int) $semaine,
        'priorite'    => (string) ($in['priorite'] ?? ''),
        'statut'      => (string) ($in['statut'] ?? ''),
        'assigne_a'   => $assigne === '' ? null : (int) $assigne,
    ];
    $erreurs = [];

    if ($v['ref'] !== '') {
        if (!preg_match('/^[A-Z0-9-]{2,20}$/', $v['ref'])) {
            $erreurs['ref'] = 'Lettres, chiffres et tirets uniquement (ex. FE-34).';
        } else {
            $st = db()->prepare('SELECT id FROM tickets WHERE ref = ? AND id <> ?');
            $st->execute([$v['ref'], $idActuel ?? 0]);
            if ($st->fetchColumn()) {
                $erreurs['ref'] = 'Cette référence est déjà utilisée par un autre ticket.';
            }
        }
    }
    $longueurTitre = mb_strlen($v['titre']);
    if ($longueurTitre < 3 || $longueurTitre > 200) {
        $erreurs['titre'] = 'Le titre doit faire entre 3 et 200 caractères.';
    }
    if (mb_strlen($v['description']) > 5000) {
        $erreurs['description'] = '5000 caractères maximum.';
    }
    if (!in_array($v['section'], SECTIONS, true)) {
        $erreurs['section'] = 'Section inconnue.';
    }
    if ($v['semaine'] !== null && ($v['semaine'] < 1 || $v['semaine'] > 4)) {
        $erreurs['semaine'] = 'Semaine 1 à 4.';
    }
    if (!isset(PRIORITES[$v['priorite']])) {
        $erreurs['priorite'] = 'Priorité inconnue.';
    }
    if (!isset(STATUTS[$v['statut']])) {
        $erreurs['statut'] = 'Statut inconnu.';
    }
    if ($v['assigne_a'] !== null && membre_par_id($v['assigne_a']) === null) {
        $erreurs['assigne_a'] = 'Membre inconnu.';
    }
    $v['ref'] = $v['ref'] === '' ? null : $v['ref'];
    return [$v, $erreurs];
}

function creer_ticket(array $v): int
{
    db()->prepare("INSERT INTO tickets (ref, titre, description, section, semaine, priorite, statut, assigne_a, fait_le)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, IF(? = 'fait', NOW(), NULL))")
        ->execute([$v['ref'], $v['titre'], $v['description'], $v['section'], $v['semaine'], $v['priorite'], $v['statut'], $v['assigne_a'], $v['statut']]);
    $id = (int) db()->lastInsertId();
    journaliser($id, 'creation', 'a créé « ' . $v['titre'] . ' »');
    return $id;
}

function mettre_a_jour_ticket(array $ticket, array $v): void
{
    $champs = ['ref', 'titre', 'description', 'section', 'semaine', 'priorite'];
    $modifies = array_filter($champs, fn($c) => (string) $ticket[$c] !== (string) $v[$c]);
    if ($modifies) {
        db()->prepare('UPDATE tickets SET ref = ?, titre = ?, description = ?, section = ?, semaine = ?, priorite = ? WHERE id = ?')
            ->execute([$v['ref'], $v['titre'], $v['description'], $v['section'], $v['semaine'], $v['priorite'], $ticket['id']]);
        journaliser((int) $ticket['id'], 'modification', 'a modifié : ' . implode(', ', $modifies));
    }
    changer_statut($ticket, $v['statut']);
    assigner($ticket, $v['assigne_a']);
}
