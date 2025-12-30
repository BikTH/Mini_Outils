# Chantier 2 — Utilisateurs & Authentification

Checklist des tâches (obligatoire)

- [x] Étape 1 — Migration DB : création du fichier de migration `db/migrations/002_add_users.sql` (tables & rôles) — migration créée (sans utilisateur admin automatique)

- [x] Création utilisateur admin initial : fournir et exécuter le script `app/scripts/create_admin.php` pour créer un admin (sécurisé)
- [x] Étape 2 — Backend (services) : `app/services/user_service.php` & `app/core/auth.php` (créés)
- [x] Étape 3 — Middleware : `middleware/middlewares/auth_middleware.php`, `middleware/middlewares/role_middleware.php` (créés)
- [x] Étape 4 — Intégration routes existantes : protéger `admin_exams`, `admin_import_pdf`, `user_history`, `user_exam_stats` (modifications dans `public/index.php`)
- [x] Étape 5 — UX minimale : routes `/login` et `/logout` + formulaires simples (implémentés dans `public/index.php`)
- [x] Étape 6 — Tests manuels checklist (préparée)

## Chantier 3 — Modes d’examen & Timer (version étendue)

Checklist des tâches (obligatoire pour Chantier 3)

- [x] Étape 1 — Migration DB : création du fichier de migration `db/migrations/003_add_exam_modes.sql` (ajout colonnes attempts, table admin_challenges) — migration créée
- [x] Étape 2 — Backend : adapter la logique de lancement d'examen selon le mode, valider paramètres côté serveur, implémenter timer serveur, soumission forcée et blocage soumission prématurée — implémenté (validation durcie)
 - [x] Étape 3 — UX minimale : écran de sélection du mode, champs dynamiques, affichage des règles avant démarrage, affichage temps restant — implémenté (UI améliorée)
 - [x] Étape 4 — Historique & stats : enregistrer le mode dans chaque tentative, séparer statistiques par mode, implémenter leaderboard top 10 pour `admin_challenge` — en partie implémenté (leaderboard & CRUD admin_challenge ajoutés)
 - [x] Étape 4 — Historique & stats : enregistrer le mode dans chaque tentative, séparer statistiques par mode, implémenter leaderboard top 10 pour `admin_challenge` — en partie implémenté (leaderboard & CRUD admin_challenge ajoutés + migration prévue)

Corrections récentes :

- Visibilité des `admin_challenges` pour les utilisateurs : les challenges configurés par les admins sont désormais visibles depuis la liste d'examens et un utilisateur peut lancer un challenge existant (sans pouvoir le modifier).
- Fix timer : le champ `duration_minutes` est correctement converti en secondes côté serveur pour `training_timed` et le compte à rebours démarre dès l'affichage de l'épreuve.
- Historique : l'affichage `user_history` affiche désormais une ventilation par mode pour chaque examen et `user_exam_stats` accepte un paramètre `mode` pour filtrer les statistiques.
- Admin : nouvelle page `admin_user_overview` (menu Historique) pour lister les utilisateurs avec nombre de tentatives et tendance, et accès aux détails par examen et par mode.

Note: Après ces modifications, pensez à exécuter la migration `004_add_admin_challenge_id_to_attempts.sql` si ce n'est pas déjà fait pour que les tentatives liées à un `admin_challenge` soient correctement historisées.

Migrations ajoutées/préparées:

- `004_add_admin_challenge_id_to_attempts.sql` : ajoute `admin_challenge_id` nullable à `attempts` — fichier créé, à exécuter sur la base de données locale.
- [ ] Étape 5 — Tests manuels : préparer checklist détaillée et exécuter (training, training_timed, official timeout, admin challenge, leaderboard, soumission prématurée, soumission forcée) — à faire

Modes à implémenter (strict)

1. training
	- utilisateur choisit le nombre de questions
	- pas de timer

2. training_timed
	- utilisateur choisit : nombre de questions, durée du timer

3. official
	- 90 questions
	- timer fixe 3600s

4. admin_challenge
	- configuré uniquement par admin
	- utilisateur ne peut rien modifier
	- leaderboard top 10 requis

Règles de soumission (résumé)

- Le timer est BLOQUANT
- Si temps écoulé → soumission automatique (server-side enforced)
- Si temps restant > 0 : soumission REFUSÉE tant que toutes les questions ne sont pas répondues

Notes opérationnelles

- Respect strict de `Global_Context_&_Goal.md` et `GIT_WORKFLOW.md`.
- Migration non destructive : nouvelles colonnes ont des valeurs par défaut ou sont NULL.
- Toute évolution supplémentaire (leaderboard UI, worker server-side auto-submit) devra être planifiée et versionnée.

Mises à jour effectuées

- Migration `003_add_exam_modes.sql` ajoutée et documentée dans `db/MIGRATIONS.md` — ✅
- Backend : `app/services/exam_service.php` étendu pour stocker `mode`, `time_limit_seconds`, `time_spent_seconds`, `is_forced_submit` — travail initial effectué
- UX : sélection de mode et stockage en session + affichage compte à rebours minimal implémentés dans `public/index.php` — travail initial effectué
 - Fix: correction d'une erreur de syntaxe dans `public/index.php` (duplication de boucle foreach) qui empêchait le chargement de l'application — corrigé

Prochaines actions (techniques)

- Finaliser validations côté serveur (verifier que les paramètres fournis correspondent aux règles du mode choisi)
- Implémenter leaderboard et UI admin pour créer `admin_challenge`
- Rédiger la checklist de tests manuels et exécuter localement


Exigences supplémentaires (demandées par l'utilisateur)

- [x] Au démarrage de l'application, l'utilisateur doit se connecter avant tout accès (sauf route `/login` et `/logout`).
- [x] L'administrateur doit pouvoir créer des utilisateurs depuis l'interface (page `admin_users`).
- [x] Si un utilisateur n'est pas admin, il ne doit pas voir les boutons/liens d'accès aux menus d'édition des examens ou de création des utilisateurs.
- [x] Lorsqu'un utilisateur connecté lance un examen, son identifiant de compte (session) est utilisé automatiquement — plus besoin de renseigner un identifiant manuellement.
- [x] Lorsqu'un utilisateur connecté consulte son historique, l'identifiant est pris à partir de sa session (les admins peuvent consulter d'autres comptes).
- [x] Chaque utilisateur connecté doit pouvoir voir son historique personnel sans avoir à saisir son identifiant.

Tests manuels à exécuter (préparés)

- [x] Connexion (login) avec identifiants valides — OK
- [x] Connexion avec identifiants invalides — KO
- [x] Accès admin refusé pour utilisateur non admin — KO attendu
- [x] Session persistante entre pages — OK
- [x] Déconnexion (logout) révoque la session — OK
 - [x] Blocage de l'application si non connecté (redirige vers `/login`) — OK
 - [x] Création d'utilisateur via l'UI admin — OK (formulaire `admin_users`)
 - [x] Les boutons d'administration sont masqués pour les comptes non-admin — OK
 - [x] Lancement d'examen lier à l'utilisateur connecté (pas d'identifiant requis) — OK
 - [x] Consultation de l'historique personnel sans saisie d'identifiant — OK

Notes / décisions
- Respect strict de `Global_Context_&_Goal.md` et `GIT_WORKFLOW.md`.
- La migration ajoute uniquement les tables et rôles; la création de l'utilisateur admin se fait via script pour ne pas exposer de mot de passe en clair dans les migrations.
- Pour créer l'admin exécuter : `php app/scripts/create_admin.php <username> <password>` depuis le répertoire `qcm_app/app/scripts`.


Notes / décisions
- Respect strict de `Global_Context_&_Goal.md` et `GIT_WORKFLOW.md`.
- La migration ajoute uniquement les tables et rôles; la création de l'utilisateur admin se fait via script pour ne pas exposer de mot de passe en clair dans les migrations.
