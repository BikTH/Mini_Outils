# MIGRATIONS

Maintenir l'historique des migrations SQL pour la base `qcm_app`.

- 001_init.sql : initial schema (questions/options/exams/attempts...)
-- 002_add_users.sql : ajoute les tables `users`, `roles`, `user_roles` et insert les rôles par défaut (admin, user). Ne crée pas d'utilisateur admin - utiliser `app/scripts/create_admin.php` pour cela.

## 003_add_exam_modes.sql
- Ajoute la colonne `mode`, `time_limit_seconds`, `time_spent_seconds`, `is_forced_submit` dans `attempts`.
- Crée la table `admin_challenges` pour définir des challenges configurés par les administrateurs.

## 004_add_admin_challenge_id_to_attempts.sql
- Ajoute la colonne `admin_challenge_id` (nullable) à `attempts` pour lier une tentative à un `admin_challenge`.
- Ajoute un index `idx_attempts_admin_challenge_id` et une contrainte `fk_attempts_admin_challenge` (ON DELETE SET NULL) non destructive.

Notes:
- Après application, le backend doit commencer à stocker `admin_challenge_id` lors d'une tentative lancée en mode `admin_challenge`.

Notes:
- Le champ `mode` peut contenir: `training`, `training_timed`, `official`, `admin_challenge`.
- Les colonnes ajoutées sont non-destructives et ont des valeurs par défaut pour assurer compatibilité.
