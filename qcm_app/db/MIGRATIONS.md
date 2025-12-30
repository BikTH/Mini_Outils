# MIGRATIONS

Maintenir l'historique des migrations SQL pour la base `qcm_app`.

- 001_init.sql : initial schema (questions/options/exams/attempts...)
-- 002_add_users.sql : ajoute les tables `users`, `roles`, `user_roles` et insert les rôles par défaut (admin, user). Ne crée pas d'utilisateur admin - utiliser `app/scripts/create_admin.php` pour cela.

## 003_add_exam_modes.sql
- Ajoute la colonne `mode`, `time_limit_seconds`, `time_spent_seconds`, `is_forced_submit` dans `attempts`.
- Crée la table `admin_challenges` pour définir des challenges configurés par les administrateurs.

Notes:
- Le champ `mode` peut contenir: `training`, `training_timed`, `official`, `admin_challenge`.
- Les colonnes ajoutées sont non-destructives et ont des valeurs par défaut pour assurer compatibilité.
