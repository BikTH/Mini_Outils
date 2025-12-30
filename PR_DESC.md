Title: Release v0.4.0 — Modes d'examen & Timer

Description:
Cette PR prépare la release v0.4.0 qui introduit :
- Modes d'examen avancés : training, training_timed, official, admin_challenge
- Timer serveur-autoritaire (compte à rebours hh:mm:ss et soumission forcée à l'expiration)
- Admin Challenges : CRUD pour admins, visibilité des challenges aux utilisateurs, leaderboard top 10
- Historique & stats : ventilation par mode, filtrage des stats par mode
- Divers correctifs et améliorations UX (sélection de mode, validation, conversion duration_minutes)

Files touched (high level):
- `qcm_app/public/index.php` : UI/flow, timer JS, mode selection, user history, admin pages
- `qcm_app/app/services/exam_service.php` : helpers (admin_challenges, saveAttempt already extended)
- `qcm_app/task.md`, `qcm_app/CHANGELOG.md`, `CHANGELOG.md` : documentation & changelogs
- `qcm_app/db/migrations/004_add_admin_challenge_id_to_attempts.sql` : migration prepared (run locally)

Checklist (must pass before merge):
- [ ] Exécuter la migration `004_add_admin_challenge_id_to_attempts.sql` en environnement de test
- [ ] Valider qu'une tentative lancée via `admin_challenge` enregistre `admin_challenge_id` dans `attempts`
- [ ] Vérifier le timer `training_timed` : entrer `duration_minutes`, démarrer, laisser expirer -> tentative forcée enregistrée
- [ ] Vérifier `user_history` et `user_exam_stats` par mode (filtrer via `?mode=`)
- [ ] Tests manuels : training (block submit if unanswered), training_timed, official, admin_challenge

Notes:
- Conformément à `GIT_WORKFLOW.md`, le tag de release doit être appliqué sur `main` après merge final et validation complète.
- Je n'ai pas tagué `main` ni exécuté la migration en base (action manuelle requise).

Merge strategy recommendation:
- Merge `feature/modes-timer` → `dev` via PR, run QA, then `dev` → `main`, tag `main` with `v0.4.0`.

