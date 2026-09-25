-- =====================================================================
-- Suivi Rosalie : outil de tickets pour l'équipe Maison Rosalie
-- ATTENTION : ce script EFFACE puis recrée la base suivi_rosalie.
-- =====================================================================

DROP DATABASE IF EXISTS suivi_rosalie;
CREATE DATABASE suivi_rosalie CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE suivi_rosalie;

CREATE TABLE membres (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prenom   VARCHAR(50)  NOT NULL,
    role     VARCHAR(100) NOT NULL DEFAULT '',
    couleur  CHAR(7)      NOT NULL DEFAULT '#8A5A2B',
    mot_de_passe VARCHAR(255) NULL,  -- hash password_hash(), défini avec outils/definir-mdp.php
    CONSTRAINT uq_membres_prenom UNIQUE (prenom),
    CONSTRAINT chk_membres_couleur CHECK (couleur REGEXP '^#[0-9A-Fa-f]{6}$')
) ENGINE=InnoDB;

CREATE TABLE tickets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ref         VARCHAR(20)  NULL,
    titre       VARCHAR(200) NOT NULL,
    description TEXT         NOT NULL,
    section     ENUM('Organisation','Frontend','Backend','Base de données','Sécurité','Qualité','Recettes','Livrables','Bonus') NOT NULL,
    semaine     TINYINT UNSIGNED NULL,
    priorite    ENUM('basse','normale','haute','critique') NOT NULL DEFAULT 'normale',
    statut      ENUM('a_faire','en_cours','relecture','fait') NOT NULL DEFAULT 'a_faire',
    assigne_a   INT UNSIGNED NULL,
    cree_le     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    maj_le      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fait_le     DATETIME NULL,
    CONSTRAINT uq_tickets_ref UNIQUE (ref),
    CONSTRAINT chk_tickets_semaine CHECK (semaine IS NULL OR semaine BETWEEN 1 AND 4),
    CONSTRAINT fk_tickets_membre FOREIGN KEY (assigne_a) REFERENCES membres(id) ON DELETE SET NULL,
    INDEX idx_tickets_statut (statut),
    INDEX idx_tickets_section (section)
) ENGINE=InnoDB;

CREATE TABLE commentaires (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id  INT UNSIGNED NOT NULL,
    membre_id  INT UNSIGNED NULL,
    message    TEXT NOT NULL,
    cree_le    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_commentaires_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_commentaires_membre FOREIGN KEY (membre_id) REFERENCES membres(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Journal de tout ce qui se passe : sert à voir « qui a fait quoi »
CREATE TABLE historique (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id  INT UNSIGNED NULL,
    membre_id  INT UNSIGNED NULL,
    action     VARCHAR(30)  NOT NULL,
    detail     VARCHAR(255) NOT NULL DEFAULT '',
    cree_le    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_historique_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_historique_membre FOREIGN KEY (membre_id) REFERENCES membres(id) ON DELETE SET NULL,
    INDEX idx_historique_date (cree_le)
) ENGINE=InnoDB;

CREATE TABLE scenarios (
    numero      TINYINT UNSIGNED PRIMARY KEY,
    description VARCHAR(255) NOT NULL,
    ok          TINYINT(1)   NOT NULL DEFAULT 0,
    valide_par  INT UNSIGNED NULL,
    valide_le   DATETIME NULL,
    note        VARCHAR(255) NOT NULL DEFAULT '',
    CONSTRAINT fk_scenarios_membre FOREIGN KEY (valide_par) REFERENCES membres(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tableau de répartition des 5 recettes (section 7 du cahier des charges)
CREATE TABLE recettes (
    numero     TINYINT UNSIGNED PRIMARY KEY,
    nom        VARCHAR(100) NOT NULL DEFAULT '',
    categorie  ENUM('Gâteaux','Mousses','Boissons','Glacé') NULL,
    difficulte ENUM('facile','moyen','difficile') NULL,
    membre_id  INT UNSIGNED NULL,
    CONSTRAINT chk_recettes_numero CHECK (numero BETWEEN 1 AND 5),
    CONSTRAINT fk_recettes_membre FOREIGN KEY (membre_id) REFERENCES membres(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Échecs de connexion, pour bloquer les essais répétés
CREATE TABLE connexions_echouees (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip      VARCHAR(45) NOT NULL,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_echecs_ip_date (ip, cree_le)
) ENGINE=InnoDB;

CREATE TABLE parametres (
    cle    VARCHAR(50)  PRIMARY KEY,
    valeur VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Données de départ
-- ---------------------------------------------------------------------

INSERT INTO membres (prenom, role, couleur) VALUES
('Yuliia',     '', '#B5485F'),
('Christophe', '', '#2F6F73'),
('Badr',       '', '#8A5A2B');

INSERT INTO parametres (cle, valeur) VALUES ('date_debut', '2026-09-21');

INSERT INTO recettes (numero) VALUES (1), (2), (3), (4), (5);

INSERT INTO scenarios (numero, description) VALUES
(1,  'Un visiteur non connecté parcourt les 4 pages et ouvre chacune des 5 recettes depuis le menu déroulant.'),
(2,  'Une 6e recette ajoutée directement dans la base apparaît dans le menu sans modifier le code.'),
(3,  'Chaque recette affiche ses ingrédients, ses étapes avec photos, son temps et sa difficulté.'),
(4,  'Un visiteur non connecté ne peut ni noter ni commenter, ni depuis l''interface, ni en envoyant une requête au backend.'),
(5,  'Une inscription invalide ou avec un email déjà pris est refusée avec des messages clairs.'),
(6,  'Après connexion, une note de 4 étoiles met à jour la moyenne sans rechargement.'),
(7,  'Noter à nouveau la même recette remplace la note, sans second vote.'),
(8,  'Une note falsifiée (0, 6, texte) envoyée directement au backend est refusée.'),
(9,  'Le top 3 de l''accueil change quand de nouvelles notes le justifient.'),
(10, 'Un commentaire ajouté apparaît sans rechargement et reste visible après actualisation.'),
(11, 'Un commentaire contenant du code HTML ou JavaScript s''affiche comme simple texte.'),
(12, 'Un utilisateur ne peut pas supprimer le commentaire d''un autre, même avec une requête fabriquée.'),
(13, 'Le formulaire de contact refuse les données invalides (client et serveur) et enregistre un message valide.'),
(14, 'Des tentatives d''injection SQL dans la connexion et dans les formulaires n''ont aucun effet.'),
(15, 'Le site reste utilisable sur un écran de 360 px de large.'),
(16, 'Base coupée volontairement : le visiteur voit un message propre, jamais une erreur technique.'),
(17, 'Sur une base vide, le script d''import et le README suffisent à obtenir un site fonctionnel.');

INSERT INTO tickets (ref, titre, description, section, semaine, priorite) VALUES
-- Organisation (semaine 1)
('ORG-01', 'Choisir PHP procédural ou objet + moteur de base', 'Décision d''équipe en semaine 1, à documenter dans le README avec les raisons du choix.', 'Organisation', 1, 'critique'),
('ORG-02', 'Créer le dépôt GitHub et les branches', 'Un dépôt par équipe, une branche par développeur, personne ne pousse directement sur main. Toute fusion passe par une relecture (pull request).', 'Organisation', 1, 'critique'),
('ORG-03', 'Répartir les 5 recettes', 'Remplir le tableau de la section 7 (page Équipe) : 5 recettes différentes, au moins 3 catégories, au moins une facile, une moyenne et une difficile.', 'Organisation', 1, 'haute'),
('ORG-04', 'Désigner le référent base de données et le référent sécurité', 'Règle de collaboration de la section 11. Indiquer les rôles dans la page Équipe.', 'Organisation', 1, 'normale'),
('ORG-05', 'Wireframes des pages', 'Maquettes simples : Accueil, Recettes, page d''une recette, À propos, Contact, modales connexion / inscription.', 'Organisation', 1, 'haute'),
('ORG-06', 'README initial', 'Présentation du projet, choix de la stack et leurs raisons, organisation de l''équipe.', 'Organisation', 1, 'haute'),

-- Base de données
('BDD-01', 'Concevoir le modèle relationnel', 'Tables, clés, relations, types pour : utilisateur, catégorie, recette, ingrédient (+ quantité/unité par recette), étape, note, commentaire, message de contact. À faire valider par le formateur en fin de semaine 1.', 'Base de données', 1, 'critique'),
('BDD-02', 'Contraintes d''intégrité portées par la base', 'Unicité (nom/email utilisateur, slug recette, numéro d''étape par recette, une note par utilisateur et par recette), plages de valeurs (note 1 à 5, longueurs), clés étrangères.', 'Base de données', 2, 'critique'),
('BDD-03', 'Suppression en cascade d''une recette', 'Supprimer une recette supprime proprement étapes, liens ingrédients, notes, commentaires : aucune donnée orpheline.', 'Base de données', 2, 'haute'),
('BDD-04', 'Encodage utf8mb4 de bout en bout', 'Accents, apostrophes et caractères spéciaux fonctionnent partout, de la saisie à l''affichage (base, connexion PDO, pages HTML).', 'Base de données', 2, 'haute'),
('BE-60', 'Script d''import unique + données de démo', 'Crée la base vide puis la remplit : 5 recettes complètes, catégories, au moins 20 notes réparties, plusieurs comptes de test dont un administrateur, des commentaires.', 'Base de données', 2, 'critique'),
('BE-61', 'Script d''import rejouable', 'Le script peut être rejoué à volonté sans erreur, et le site fonctionne juste après son exécution.', 'Base de données', 2, 'haute'),

-- Frontend
('FE-01', 'Palette caramel / brun chaud / rose poudré', 'Palette cohérente sur les 4 pages, texte toujours lisible.', 'Frontend', 1, 'normale'),
('FE-02', 'Créer le logo', 'Logo de l''équipe en SVG ou PNG transparent, utilisé dans la navigation, le hero et le pied de page.', 'Frontend', 1, 'normale'),
('FE-03', 'Typographies et composants homogènes', 'Deux familles de polices maximum, boutons, cartes et formulaires au style homogène. Résumer palette, typographies et composants dans le README.', 'Frontend', 1, 'normale'),
('FE-04', 'Barre de navigation commune', 'Logo, Accueil, Recettes (menu déroulant), À propos, Contact, et zone connexion / inscription qui devient nom de l''utilisateur + déconnexion une fois connecté.', 'Frontend', 2, 'haute'),
('FE-05', 'Menu déroulant Recettes alimenté par la base', 'Les noms des recettes proviennent de la base de données (scénario 2 : une 6e recette doit apparaître sans toucher au code).', 'Frontend', 2, 'haute'),
('FE-06', 'Page courante repérable + menu mobile', 'La page courante est visible dans la navigation ; sur mobile la navigation se replie en menu.', 'Frontend', 2, 'normale'),
('FE-07', 'Pied de page commun', 'Logo, liens de navigation, contact, mentions légales, icônes réseaux sociaux (liens fictifs), « © 2026 Maison Rosalie - Tous droits réservés ».', 'Frontend', 2, 'normale'),
('FE-08', 'Titre, description et favicon par page', 'Chaque page a un titre d''onglet et une meta description propres, ainsi qu''un favicon.', 'Frontend', 2, 'basse'),
('FE-10', 'Hero de la page d''accueil', 'Image chaleureuse, voile de couleur pour la lisibilité, logo, nom, slogan et bouton « Découvrir nos recettes ».', 'Frontend', 2, 'normale'),
('FE-11', 'Bloc « Les 3 recettes les mieux notées »', '3 cartes (3 colonnes sur ordinateur, 1 sur mobile) : rang, photo, titre, note en étoiles, nombre d''avis, temps total, difficulté, bouton « Voir la recette ».', 'Frontend', 3, 'haute'),
('FE-12', 'Top 3 robuste', 'Comportement correct quand moins de 3 recettes sont notées ou quand les données sont indisponibles : aucune carte vide ni cassée.', 'Frontend', 3, 'haute'),
('FE-13', 'Bloc de présentation en 2 colonnes (accueil)', 'Texte « Depuis 1987, la famille Moreau… » + image d''atelier, bouton « Notre histoire » vers À propos.', 'Frontend', 2, 'basse'),
('FE-20', 'Page Recettes : cartes', 'Photo, titre, catégorie, temps total, difficulté, note moyenne avec nombre d''avis, bouton « Voir la recette ».', 'Frontend', 2, 'haute'),
('FE-21', 'Page Recettes : états chargement / erreur / vide', 'Les trois états sont prévus et affichés proprement.', 'Frontend', 2, 'normale'),
('FE-30', 'Page recette : en-tête', 'Titre, catégorie, photo principale, courte description, note moyenne en étoiles et nombre d''avis.', 'Frontend', 2, 'haute'),
('FE-31', 'Page recette : fiche rapide', 'Temps de préparation, de cuisson, total, difficulté représentée visuellement (icônes), nombre de portions.', 'Frontend', 2, 'haute'),
('FE-32', 'Page recette : ingrédients', 'Liste claire avec quantité et unité pour chacun.', 'Frontend', 2, 'haute'),
('FE-33', 'Page recette : étapes illustrées', 'Étapes numérotées (4 minimum) : numéro, titre court, texte et photo pour chaque étape. Lecture confortable sur mobile.', 'Frontend', 2, 'haute'),
('FE-34', 'Notation par étoiles interactive', 'Aperçu au survol, clic pour noter, note personnelle rappelée, moyenne et nombre d''avis mis à jour sans rechargement.', 'Frontend', 3, 'critique'),
('FE-35', 'Section commentaires', 'Liste (auteur, date, texte) du plus récent au plus ancien, ajout sans rechargement, chargement progressif au-delà de 10, suppression de ses propres commentaires.', 'Frontend', 3, 'critique'),
('FE-36', 'Invitation à se connecter', 'Non connecté : notation et formulaire de commentaire remplacés par une invitation qui ouvre la modale de connexion.', 'Frontend', 3, 'haute'),
('FE-37', 'Liens vers les autres recettes', 'En fin de page recette.', 'Frontend', 2, 'basse'),
('FE-38', 'Recette inexistante', 'Message d''erreur convivial et retour possible, jamais de page blanche.', 'Frontend', 2, 'haute'),
('FE-40', 'Page À propos', 'Histoire et valeurs, photo de l''atelier ou de l''équipe, chiffres clés ou frise chronologique (1987…). Contenus fictifs mais crédibles.', 'Frontend', 2, 'normale'),
('FE-41', 'Contact : formulaire + validation', 'Nom, email, sujet, message ; validation avant envoi, erreurs affichées près des champs.', 'Frontend', 3, 'haute'),
('FE-42', 'Contact : retour d''envoi', 'Retour clair de succès ou d''erreur, sans perdre la saisie en cas d''erreur.', 'Frontend', 3, 'normale'),
('FE-43', 'Contact : coordonnées et carte', 'Adresse fictive à Liège, contact@maisonrosalie.be, carte Google Maps intégrée.', 'Frontend', 2, 'basse'),
('FE-50', 'Modales connexion et inscription', 'Accessibles depuis la navigation et depuis les invitations à se connecter. Aucune page supplémentaire.', 'Frontend', 3, 'haute'),
('FE-51', 'Formulaire d''inscription', 'Nom d''utilisateur, email, mot de passe et confirmation, avec indication des règles à respecter.', 'Frontend', 3, 'haute'),
('FE-52', 'Affichage des erreurs du backend', 'Email déjà utilisé, identifiants incorrects, etc. affichés de façon compréhensible.', 'Frontend', 3, 'normale'),
('FE-53', 'État connecté partout', 'Reflété dans la navigation, la notation, les commentaires, et conservé d''une page à l''autre.', 'Frontend', 3, 'haute'),
('FE-60', 'Échanges asynchrones (fetch)', 'Notation, commentaires et connexion ne provoquent aucun rechargement de page.', 'Frontend', 3, 'haute'),
('FE-61', 'Validation côté client de tous les formulaires', 'Champs requis, formats, longueurs, messages précis. Ne remplace jamais la validation serveur.', 'Frontend', 3, 'normale'),
('FE-62', 'Retours visuels + anti double-clic', 'Chargement, succès, erreur pour chaque action ; boutons désactivés pendant un envoi.', 'Frontend', 3, 'normale'),
('FE-63', 'Affichage sûr du contenu utilisateur', 'Jamais interprété comme du code (textContent plutôt qu''innerHTML).', 'Frontend', 3, 'critique'),
('FE-64', 'Responsive dès 360 px', 'Affichage soigné sur mobile 360 px, tablette et ordinateur, sans défilement horizontal.', 'Frontend', 4, 'haute'),
('FE-65', 'Accessibilité de base', 'HTML sémantique, alt sur les images, contrastes, labels, navigation au clavier, étoiles utilisables au clavier.', 'Frontend', 4, 'normale'),
('FE-66', 'Performance des images', 'Images compressées (300 Ko max chacune), chargement différé (loading="lazy") hors écran.', 'Frontend', 4, 'normale'),
('FE-67', 'Compatibilité navigateurs', 'Versions récentes de Chrome, Firefox, et Edge ou Safari.', 'Frontend', 4, 'basse'),
('FE-68', 'Code frontend propre', 'HTML valide au validateur W3C, JS découpé en fichiers cohérents, pas de duplication, pas de style inline en masse.', 'Frontend', 4, 'normale'),

-- Backend
('BE-01', 'Inscription', 'Nom d''utilisateur et email uniques et valides, politique de mot de passe définie et documentée (longueur, complexité).', 'Backend', 3, 'critique'),
('BE-02', 'Connexion / déconnexion', 'Le message d''erreur ne révèle jamais si c''est l''email ou le mot de passe qui est faux.', 'Backend', 3, 'critique'),
('BE-03', 'Protection contre les tentatives répétées', 'Blocage ou ralentissement après plusieurs échecs de connexion (à concevoir et documenter).', 'Backend', 3, 'haute'),
('BE-04', 'Endpoint « qui est connecté »', 'Le frontend peut à tout moment savoir si un utilisateur est connecté et qui il est.', 'Backend', 3, 'haute'),
('BE-10', 'Liste légère des recettes (menu)', 'Pour alimenter le menu déroulant.', 'Backend', 2, 'haute'),
('BE-11', 'Liste des recettes avec notes', 'Avec note moyenne et nombre de votes.', 'Backend', 2, 'haute'),
('BE-12', 'Détail d''une recette par son slug', 'Infos, ingrédients avec quantités, étapes dans l''ordre, note moyenne, nombre de votes, note de l''utilisateur connecté.', 'Backend', 2, 'critique'),
('BE-13', 'Recette inconnue', 'Réponse d''erreur appropriée (404), sans fuite d''information technique.', 'Backend', 2, 'haute'),
('BE-14', 'Temps total calculé par le backend', 'Jamais saisi à la main.', 'Backend', 2, 'normale'),
('BE-20', 'Noter une recette', 'Réservé aux utilisateurs connectés ; seules les valeurs entières de 1 à 5 sont acceptées.', 'Backend', 3, 'critique'),
('BE-21', 'Une seule note par utilisateur et par recette', 'Noter à nouveau remplace l''ancienne note. Deux envois simultanés ne créent pas de doublon (contrainte unique + upsert).', 'Backend', 3, 'critique'),
('BE-22', 'Renvoyer la nouvelle moyenne', 'Après chaque note : moyenne à une décimale et nombre de votes.', 'Backend', 3, 'haute'),
('BE-23', 'Moyenne toujours calculée', 'Calculée à partir des notes enregistrées, jamais stockée à la main.', 'Backend', 3, 'haute'),
('BE-30', 'Calcul du top 3', 'Tri : moyenne décroissante, puis nombre de votes, puis recette la plus récente. Seules les recettes notées sont classées ; complément avec les plus récentes s''il y en a moins de 3.', 'Backend', 3, 'critique'),
('BE-31', 'Top 3 à jour', 'Le classement reflète une nouvelle note dès le chargement suivant de l''accueil.', 'Backend', 3, 'normale'),
('BE-40', 'Lister les commentaires par tranches', 'Du plus récent au plus ancien, avec pagination.', 'Backend', 3, 'haute'),
('BE-41', 'Ajouter un commentaire', 'Réservé aux connectés ; message de 3 à 500 caractères après trim ; sujet facultatif de 120 caractères max.', 'Backend', 3, 'critique'),
('BE-42', 'Réponse d''ajout avec le commentaire créé', 'Pour un affichage immédiat côté frontend.', 'Backend', 3, 'normale'),
('BE-43', 'Supprimer un commentaire', 'Uniquement par son auteur ou un administrateur. Toute autre tentative refusée, même avec une requête fabriquée.', 'Backend', 3, 'critique'),
('BE-44', 'Limite de commentaires par utilisateur', 'Nombre maximum sur une période donnée (valeurs choisies et documentées).', 'Backend', 3, 'normale'),
('BE-45', 'Commentaires jamais exécutés', 'Le contenu d''un commentaire ne peut jamais exécuter de code à l''affichage.', 'Backend', 3, 'critique'),
('BE-50', 'Message de contact côté serveur', 'Validation complète, enregistrement, confirmation, protection anti-spam choisie et documentée (honeypot, délai…).', 'Backend', 3, 'haute'),
('BE-70', 'Format JSON homogène', 'Structure de réponse commune (succès/erreur, message, données) définie par l''équipe.', 'Backend', 3, 'haute'),
('BE-71', 'Codes HTTP cohérents', '200, 201, 400/422, 401, 403, 404, 500 utilisés à bon escient.', 'Backend', 3, 'haute'),
('BE-72', 'Ne jamais faire confiance au frontend', 'Tout ce qui arrive est vérifié comme si le JavaScript n''existait pas.', 'Backend', 3, 'haute'),
('BE-73', 'Documentation de l''API', 'Chaque point d''accès : rôle, accès, données attendues, réponses possibles. Fichier livré avec le dépôt.', 'Backend', 4, 'haute'),

-- Sécurité
('SEC-01', 'Aucune injection SQL', 'Requêtes préparées sur tous les champs et paramètres. POINT ÉLIMINATOIRE.', 'Sécurité', 3, 'critique'),
('SEC-02', 'Aucune faille XSS', 'Toute donnée saisie par un visiteur est inoffensive à l''affichage.', 'Sécurité', 3, 'critique'),
('SEC-03', 'Protection CSRF', 'Note, commentaire, suppression, contact, déconnexion protégés contre les requêtes forgées.', 'Sécurité', 3, 'critique'),
('SEC-04', 'Mots de passe hachés', 'password_hash / password_verify. Jamais lisibles. POINT ÉLIMINATOIRE si stockés en clair.', 'Sécurité', 3, 'critique'),
('SEC-05', 'Sessions sécurisées', 'Identifiant régénéré à la connexion, durée de vie limitée, cookies HttpOnly/SameSite, déconnexion qui détruit vraiment la session.', 'Sécurité', 3, 'haute'),
('SEC-06', 'Contrôle d''accès sur chaque action', 'Connecté, auteur, administrateur vérifiés côté serveur à chaque fois.', 'Sécurité', 3, 'critique'),
('SEC-07', 'Validation serveur de chaque donnée', 'Présence, type, longueur, plage, indépendamment du JavaScript.', 'Sécurité', 3, 'haute'),
('SEC-08', 'Secrets hors du dépôt et du web', 'Identifiants de base hors du dépôt et hors des dossiers publics ; fournir un modèle de configuration sans secret. À mettre en place dès le début (.gitignore).', 'Sécurité', 1, 'critique'),
('SEC-09', 'Compte base de données dédié', 'Le site se connecte avec un utilisateur aux droits limités, jamais root.', 'Sécurité', 2, 'haute'),
('SEC-10', 'Aucune erreur technique visible', 'Messages propres pour le visiteur, détails dans un journal côté serveur.', 'Sécurité', 3, 'haute'),
('SEC-11', 'Historique Git sans secret', 'Aucun secret dans l''historique, même supprimé depuis. Vérifier avant chaque push.', 'Sécurité', 1, 'critique'),
('SEC-12', 'Mesures avant mise en ligne réelle', 'Section du rapport technique : HTTPS, en-têtes de sécurité, sauvegardes, etc.', 'Sécurité', 4, 'normale'),

-- Qualité
('QC-01', 'Organisation du code', 'Procédural (fonctions par thème) ou objet (classes à responsabilité claire), sans mélanger les deux styles.', 'Qualité', 2, 'haute'),
('QC-02', 'Pas d''accès aux données dans les pages d''affichage', 'Séparer la logique d''accès aux données des pages qui produisent le HTML.', 'Qualité', 2, 'haute'),
('QC-03', 'Connexion à la base définie à un seul endroit', 'Réutilisée partout.', 'Qualité', 2, 'haute'),
('QC-04', 'Code propre', 'Pas de duplication, noms explicites, commentaires utiles.', 'Qualité', 4, 'normale'),
('QC-05', 'Gestion des cas d''erreur', 'Base indisponible, données invalides, recette absente, session expirée : message adapté à chaque fois.', 'Qualité', 3, 'haute'),
('QC-06', 'Nombre de requêtes raisonnable', 'Nombre de requêtes SQL par page raisonnable et justifié.', 'Qualité', 4, 'normale'),
('QC-07', 'Plan de tests documenté', 'Cas, résultat attendu, résultat obtenu. Couvre toute la section 6.2 et les exigences de sécurité.', 'Qualité', 4, 'critique'),

-- Recettes
('REC-1', 'Recette n°1 : réaliser, photographier, saisir', 'Réaliser la recette, prendre une photo par étape (4 minimum), rédiger chaque étape en 1 à 3 phrases avec températures et durées, saisir en base (ingrédients, quantités, unités, temps, portions, difficulté).', 'Recettes', 2, 'haute'),
('REC-2', 'Recette n°2 : réaliser, photographier, saisir', 'Même chose que la recette n°1.', 'Recettes', 2, 'haute'),
('REC-3', 'Recette n°3 : réaliser, photographier, saisir', 'Même chose que la recette n°1.', 'Recettes', 2, 'haute'),
('REC-4', 'Recette n°4 : réaliser, photographier, saisir', 'Même chose que la recette n°1.', 'Recettes', 2, 'haute'),
('REC-5', 'Recette n°5 : réaliser, photographier, saisir', 'Même chose que la recette n°1.', 'Recettes', 2, 'haute'),
('REC-6', 'Compresser les photos des recettes', '300 Ko maximum par image (FE-66).', 'Recettes', 2, 'normale'),

-- Livrables
('LIV-01', 'README complet', 'Présentation, choix techniques, installation pas à pas, comptes de test, captures d''écran. Un site qui ne s''installe pas avec le README = POINT ÉLIMINATOIRE.', 'Livrables', 4, 'critique'),
('LIV-02', 'Rapport technique (2 à 3 pages)', 'Choix de la stack, modèle de données, mesures de sécurité, difficultés rencontrées, ce que l''équipe referait autrement.', 'Livrables', 4, 'haute'),
('LIV-03', 'Préparer la démonstration', '10 minutes de démo + 5 minutes de questions. Qui présente quoi ?', 'Livrables', 4, 'haute'),
('LIV-04', 'Rejouer les 17 scénarios de recette finale', 'Voir la page Scénarios de démo. Installer depuis zéro en suivant le README.', 'Livrables', 4, 'critique'),

-- Bonus (seulement si tout l'obligatoire est fait)
('BON-01', 'Recherche en temps réel', 'Par titre ou ingrédient. Niveau : facile.', 'Bonus', NULL, 'basse'),
('BON-02', 'Mode sombre « chocolat noir »', 'Niveau : facile.', 'Bonus', NULL, 'basse'),
('BON-03', 'Feuille de style d''impression', 'Pour une page recette. Niveau : facile.', 'Bonus', NULL, 'basse'),
('BON-04', 'Filtres catégorie / difficulté / temps', 'Niveau : moyen.', 'Bonus', NULL, 'basse'),
('BON-05', 'Changement du nombre de portions', 'Avec recalcul des quantités. Niveau : moyen.', 'Bonus', NULL, 'basse'),
('BON-06', 'Mode « cuisine »', 'Une étape à la fois, en grand, avec minuteur. Niveau : moyen.', 'Bonus', NULL, 'basse'),
('BON-07', 'Recettes favorites', 'Par utilisateur. Niveau : moyen.', 'Bonus', NULL, 'basse'),
('BON-08', 'Panneau d''administration', 'Ajouter ou modifier une recette, modérer les commentaires. Niveau : difficile.', 'Bonus', NULL, 'basse'),
('BON-09', 'Mise en ligne Docker + reverse proxy', 'Niveau : difficile.', 'Bonus', NULL, 'basse');
