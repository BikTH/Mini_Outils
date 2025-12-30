# Chantier 2 — Utilisateurs & Authentification

Checklist des tâches (obligatoire)

- [x] Étape 1 — Migration DB : création du fichier de migration `db/migrations/002_add_users.sql` (tables & rôles) — migration créée (sans utilisateur admin automatique)

- [x] Création utilisateur admin initial : fournir et exécuter le script `app/scripts/create_admin.php` pour créer un admin (sécurisé)
- [x] Étape 2 — Backend (services) : `app/services/user_service.php` & `app/core/auth.php` (créés)
- [x] Étape 3 — Middleware : `middleware/middlewares/auth_middleware.php`, `middleware/middlewares/role_middleware.php` (créés)
- [x] Étape 4 — Intégration routes existantes : protéger `admin_exams`, `admin_import_pdf`, `user_history`, `user_exam_stats` (modifications dans `public/index.php`)
- [x] Étape 5 — UX minimale : routes `/login` et `/logout` + formulaires simples (implémentés dans `public/index.php`)
- [x] Étape 6 — Tests manuels checklist (préparée)

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
