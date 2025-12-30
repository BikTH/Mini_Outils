# MIGRATIONS

Maintenir l'historique des migrations SQL pour la base `qcm_app`.

- 001_init.sql : initial schema (questions/options/exams/attempts...)
- 002_add_users.sql : ajoute les tables `users`, `roles`, `user_roles` et insert les rôles par défaut (admin, user). Ne crée pas d'utilisateur admin - utiliser `app/scripts/create_admin.php` pour cela.
