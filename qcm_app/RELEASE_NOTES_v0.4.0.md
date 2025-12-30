# Release v0.4.0 (draft)

This is a draft release notes file for v0.4.0. It contains the Chantier 3 work (Modes d'examen & Timer) and related fixes.

## Summary

- Implemented exam modes: training, training_timed, official, admin_challenge (UI + server-side validations).
- Server-authoritative timer and forced auto-submit on timeout.
- Admin challenges: CRUD for admins and visibility for users; leaderboard per challenge.
- Duration input standardized to minutes on UI; converted to seconds on server.
- History and stats: attempts are recorded with `mode`, `time_limit_seconds`, `time_spent_seconds`, `is_forced_submit`.
- User history now shows breakdown per mode and links to per-mode stats.
- Admin user overview: new page listing users with total attempts and trend, and details by exam / by mode.

## Migrations

- `003_add_exam_modes.sql` — already added earlier: adds `mode`, `time_limit_seconds`, `time_spent_seconds`, `is_forced_submit` to `attempts`, and creates `admin_challenges`.
- `004_add_admin_challenge_id_to_attempts.sql` — added in `db/migrations/` (needs to be executed on target DB): adds nullable `admin_challenge_id` to `attempts`.

## Notes for deploy

1. Run DB migrations in order: 003 then 004 if not already applied.
2. Verify PHP CLI is available locally if you run the provided scripts.
3. QA checklist: training, training_timed, official timeout, admin_challenge flows; ensure `admin_challenge_id` is recorded after migration.

## Known items / follow-ups

- Add automated smoke-tests for challenge flows (can be added as scripts).
- Polish leaderboard UI (styling) and optional pagination.

---

(Adjust and expand this draft before tagging the final release.)
