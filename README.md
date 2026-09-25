# Suivi Rosalie

Outil de tickets de l'équipe **Maison Rosalie** (Yuliia, Christophe, Badr) pour se répartir le travail du projet et suivre son avancement.

Toutes les exigences du cahier des charges (FE-xx, BE-xx, SEC-xx, QC-xx), les recettes, les livrables, les bonus et les 17 scénarios de la démo finale sont déjà chargés en tickets.

> Cet outil est **séparé** du projet Maison Rosalie : il ne fait pas partie du rendu.

## Ce que ça fait

| Page | À quoi ça sert |
|---|---|
| **Tableau de bord** | Avancement global, par section, par semaine et par personne ; tickets à prendre, à relire, en retard ; activité récente |
| **Tickets** | Tableau Kanban (À faire, En cours, En relecture, Fait). Glisser une carte pour changer son statut, bouton « Je prends », filtres par personne, section, semaine, priorité, recherche |
| **Ticket** | Détail, modification, commentaires, historique complet de qui a fait quoi |
| **Scénarios de démo** | Les 17 scénarios de la section 13, à cocher au fur et à mesure |
| **Équipe** | Fiches (rôle, couleur), ce que chacun a fait récemment, répartition des 5 recettes avec vérification des règles, changement de mot de passe, date de début du projet |

Chaque modification est signée par la personne connectée. Un bandeau prévient quand quelqu'un d'autre a modifié quelque chose pendant que tu as la page ouverte.

## Installation (WAMP)

1. Cloner le dépôt dans `C:\wamp64\www\suivi-rosalie`.
2. Créer la base (⚠️ `install.sql` **efface** la base `suivi_rosalie` si elle existe) :
   ```
   C:\wamp64\bin\mariadb\mariadb11.4.9\bin\mariadb.exe -u root --port=3307 --default-character-set=utf8mb4 < sql\install.sql
   ```
3. Créer un compte base de données aux droits limités (remplacer `MOT_DE_PASSE`) :
   ```sql
   CREATE USER 'suivi_app'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE';
   CREATE USER 'suivi_app'@'127.0.0.1' IDENTIFIED BY 'MOT_DE_PASSE';
   GRANT SELECT, INSERT, UPDATE, DELETE ON suivi_rosalie.* TO 'suivi_app'@'localhost', 'suivi_app'@'127.0.0.1';
   ```
4. Copier `config.example.php` en `config.php` et y mettre ce mot de passe. `config.php` est ignoré par Git.
5. Donner un mot de passe à chaque membre (un mot de passe aléatoire est affiché si on n'en donne pas) :
   ```
   php outils\definir-mdp.php Yuliia
   php outils\definir-mdp.php Christophe
   php outils\definir-mdp.php Badr
   ```
6. Ouvrir http://localhost/suivi-rosalie/public/

Chacun peut ensuite changer son mot de passe dans la page **Équipe**.

## Accès pour toute l'équipe (lien Internet)

`en-ligne.bat` démarre un petit serveur PHP qui ne sert **que** le dossier `public/` (pas phpMyAdmin), puis ouvre un tunnel Cloudflare gratuit qui affiche une adresse du type `https://xxxx.trycloudflare.com`. C'est ce lien qu'on partage.

- Il faut télécharger une fois [`cloudflared-windows-amd64.exe`](https://github.com/cloudflare/cloudflared/releases/latest) et le renommer en `cloudflared.exe` à la racine du dossier.
- Le site n'est accessible que tant que le PC est allumé et que la fenêtre reste ouverte.
- **L'adresse change à chaque lancement** : il faut la renvoyer à l'équipe.

Pour une adresse fixe accessible en permanence, il faudra un hébergement PHP/MariaDB (par exemple alwaysdata, gratuit jusqu'à 100 Mo) : on y importe `install.sql` et on y dépose le code.

## Sécurité

- Mots de passe hachés avec `password_hash`, sessions régénérées à la connexion, expiration après 8 h d'inactivité.
- Blocage de 15 minutes après 5 échecs de connexion depuis la même adresse.
- Requêtes préparées partout, échappement de tout affichage, jeton CSRF sur chaque formulaire.
- Seul `public/` est accessible depuis le web (`.htaccess`) ; erreurs journalisées dans `logs/`, jamais affichées.

## Organisation du code

```
config.example.php   modèle de configuration (sans secret)
sql/install.sql      structure + tickets de départ
src/app.php          session, base, sécurité, helpers
src/tickets.php      opérations sur les tickets
src/vues/            en-tête et pied de page communs
public/              pages accessibles depuis le navigateur
outils/              scripts en ligne de commande
```
