# Changelog - qcm_app

## [0.7.0] - 2026-01-10

### Ajouté
- **Système de design moderne** : CSS Variables (design tokens) pour palette de couleurs, typographie, espacements, ombres et transitions
- **Layout responsive mobile-first** : breakpoints adaptatifs (mobile < 480px, tablet 768px, desktop 1024px+)
- **Header sticky** : navigation modernisée avec user info badge et collapse sur mobile
- **Composants UI** :
  - Cards avec ombres et hover effects
  - Système de boutons (5 variantes × 3 tailles)
  - Formulaires modernisés avec focus states
  - Alerts (success, error, warning, info)
  - Badges pour statuts
  - Tables responsives
  - Progress bars avec couleurs adaptatives
- **Interface d'examen optimisée** :
  - Timer visuel HH:MM:SS avec états Warning/Danger et animations (pulse, shake)
  - Barre de progression des réponses avec couleurs adaptatives
  - Validation temps réel avec feedback visuel immédiat
  - Raccourcis clavier (Alt+S soumettre, Alt+N question suivante)
  - Protection contre navigation accidentelle (beforeunload)
  - Soumission automatique au timeout
- **Animations CSS** : fadeIn, slideInRight, shake, pulse avec transitions fluides
- **Accessibilité WCAG AA** : contraste conforme, focus states visibles, navigation clavier, labels sémantiques

### Technique
- Nouveau fichier `public/assets/css/style.css` (1200+ lignes de CSS moderne)
- Nouveau fichier `public/assets/js/exam.js` (500+ lignes de JavaScript modulaire)
- Structure HTML améliorée dans `public/index.php` avec classes sémantiques
- Aucune dépendance externe (CSS/JS vanilla)
- Compatible navigateurs modernes (Chrome, Firefox, Safari, Edge)

### Documentation
- Ajout de `IMPROVEMENTS.md` : documentation technique détaillée des améliorations
- Ajout de `UI_UX_UPGRADE_SUMMARY.md` : résumé complet de la mise à niveau UI/UX

## [0.5.0] - 2026-01-06

### Ajouté
- Authentification full frontend via API middleware
- Endpoints API `/api/login`, `/api/logout`, `/api/me`
- Middleware API centralisé (routing JSON)
- Frontend autonome (login, menus, examens, leaderboard)
- Leaderboard admin_challenge sécurisé (top 10)

### Sécurité
- Protection anti-brute-force légère (session-based)
- Messages d'erreur normalisés
- Codes HTTP cohérents (401 / 429)

### Technique
- Séparation stricte Frontend / Middleware / Backend
- Aucune dépendance externe ajoutée
- task.md structuré comme journal de pilotage


## [0.3.1] - 2025-12-29

### Corrigé
- Correction du parsing PDF : fix de la regex `preg_split` qui provoquait des warnings "Unknown modifier ','" lors de l'import (fichier `app/pdf/pdf_parser.php`).
- Divers ajustements de structure (déplacement vers `app/core`, `app/services`, `app/pdf`) sans modification de la logique métier.

## [0.5.0] - 2025-12-30

### Ajouté
- Stabilisation du leaderboard pour `admin_challenge` (source de vérité centralisée, unicité par utilisateur, règles de classement strictes).

### Modifié
- Consolidation de la logique de scoring/leaderboard dans `app/services/stats_service.php`.
- Amélioration de l'affichage du leaderboard (rang, temps passé HH:MM:SS, indication des soumissions forcées) dans l'UI admin.
- Nettoyage et mise à jour de la documentation de projet (`Global_Context_&_Goal.md`, `task.md`).

### Technique
- Ajout de tests manuels et checklist pour le Chantier 3 (modes & timer).

## [0.4.0] - 2025-12-30

### Ajouté
- Authentification utilisateur : login/logout, session-based user context
- Gestion multi-utilisateurs : migration, script de création d'admin, service utilisateur
- Interface d'administration pour création et gestion des utilisateurs (`admin_users`)
 - Modes d'examen avancés : `training`, `training_timed`, `official`, `admin_challenge`.
 - Admin Challenges : CRUD pour les administrateurs, challenge visibles aux utilisateurs, leaderboard top 10.
 - Timer serveur-autoritaire pour les examens chronométrés, soumission forcée automatique à l'expiration.

### Modifié
- Accès protégé par défaut : l'application exige une authentification (sauf `/login` et `/logout`)
- Historique et passation d'examen liés au compte connecté (session)
 - Historique : ventilation par mode pour chaque examen (moyenne, total, meilleure tentative) et filtrage possible par mode.
 - Formulaires et UI : sélection de mode améliorée, conversion `duration_minutes` → secondes côté serveur pour `training_timed`, affichage hh:mm:ss du compte à rebours.

### Corrigé
- Masquage des éléments d'administration pour les utilisateurs non-admin

## [0.3.0] - 2025-12-11

### Ajouté
- **Statistiques par examen** : Page détaillée avec vue d'ensemble des performances
- **Groupement par examen** : L'historique personnel groupe maintenant les tentatives par examen
- **Métriques de performance** : Score moyen, meilleur/pire score, évolution, tendance
- **Graphique d'évolution** : Visualisation simple des scores dans le temps
- **Analyse de progression** : Comparaison première vs dernière tentative avec indicateur d'amélioration
- **Liste chronologique** : Affichage des 10 dernières tentatives avec liens vers les corrections

### Modifié
- Page "Mon historique" : Affichage groupé par examen avec résumé et lien vers statistiques
- Page "Correction" : Support pour affichage depuis `attempt_id` (en plus de la session)

### Technique
- Nouvelles fonctions dans `app/exam_service.php` : `getAttemptsForUserAndExam()`, `computeExamStatistics()`
- Calcul automatique des tendances (amélioration/baisse/stable)
- Graphique simple en HTML/CSS (barres horizontales colorées)

## [0.1.0] - 2025-12-11

### Ajouté
- Création d'examens (admin)
- Import automatique de QCM depuis PDF (parsing QUESTION NO:, options A./B./C./…, Correct Answer:)
- Passage d'épreuve avec tirage aléatoire de N questions
- Saisie réponses (radio pour QCM simple, checkbox pour QCM multiple)
- Calcul et affichage du score
- Correction détaillée avec bonnes réponses et explications
- Support QCM simple et multiple (détection automatique)
- Stockage des explications après la ligne "Correct Answer:"

### Technique
- Architecture PHP procédurale ultra-minimaliste (sans framework)
- Point d'entrée unique : `public/index.php`
- Base de données MySQL/MariaDB avec PDO
- Bibliothèque Smalot\PdfParser 2.12.2 (déposée manuellement dans `public/vendor_pdfparser/`)
- Compatible Windows/Linux (pas de dépendances système)

### Limitations V0
- Pas d'historique des tentatives (réponses stockées en session uniquement)
- Questions ouvertes conservées mais non notées
