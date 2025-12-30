# Global Context & Goals — QCM App

## 1. Contexte global du projet

Ce projet (`qcm_app`) est une application de QCM développée en PHP, initialement mono-utilisateur et locale, puis destinée à évoluer vers une application multi-utilisateurs structurée.

Le projet doit rester :
- simple à comprendre,
- lisible et maintenable,
- sans framework lourd imposé,
- compatible Windows / Linux,
- évolutif de manière contrôlée.

Ce document est une **charte contractuelle** entre :
- le développeur principal,
- ChatGPT (architecture, décisions techniques),
- GitHub Copilot (assistance à l’implémentation).

Aucune évolution ne doit contredire ce document sans mise à jour explicite de celui-ci.

---

## 2. Vision cible

L’application doit permettre :
- la création et l’import d’examens (QCM),
- le passage d’examens selon plusieurs **modes**,
- la gestion multi-utilisateurs avec rôles,
- le suivi détaillé des performances,
- une interface utilisateur propre et découplée.

---

## 3. Architecture globale

```
Frontend  <── API / Middleware ──>  Backend  <──>  Base de données
```

Chaque couche a une responsabilité claire et **ne doit pas empiéter sur les autres**.

---

## 4. Structure officielle des dossiers

```
qcm_app/
├── app/                     # BACKEND (logique métier)
│   ├── core/                # Fonctions transverses
│   │   ├── database.php
│   │   ├── auth.php
│   │   └── helpers.php
│   ├── services/            # Logique métier
│   │   ├── exam_service.php
│   │   ├── user_service.php
│   │   ├── attempt_service.php
│   │   └── stats_service.php
│   ├── pdf/
│   │   └── pdf_parser.php
│   └── config.php           # Configuration locale
│
├── middleware/              # API / Sécurité / Validation
│   ├── index.php
│   ├── routes/
│   │   ├── exams.php
│   │   ├── users.php
│   │   └── attempts.php
│   └── middlewares/
│       ├── auth_middleware.php
│       └── role_middleware.php
│
├── frontend/                # Interface utilisateur
│   ├── index.html
│   ├── assets/
│   │   ├── css/
│   │   └── js/
│   └── components/
│
├── public/
│   ├── index.php
│   └── uploads/pdf/
│
├── db/
│   ├── schema.sql
│   ├── migrations/
│   │   ├── 001_init.sql
│   │   ├── 002_add_users.sql
│   │   └── 003_add_exam_modes.sql
│   └── MIGRATIONS.md
│
├── tests/
│   ├── test_scoring.php
│   └── test_pdf_parser.php
│
├── CHANGELOG.md
├── GIT_WORKFLOW.md     #définit COMMENT on travaille avec Git (règles exécutables)
└── Global_Context_&_Goal.md
```

---

## 5. Git & GitHub — Stratégie de branches

### Branches principales

- `main`  
  → version stable, toujours taguée

- `dev`  
  → branche de développement et d’intégration continue

### Branches temporaires

- `feature/<nom>`  
  → nouvelle fonctionnalité (timer, users, frontend…)

- `fix/<nom>`  
  → correctif ciblé

### Workflow standard

1. Créer une branche depuis `dev`
2. Implémenter la fonctionnalité
3. Tester localement
4. Mettre à jour `CHANGELOG.md`
5. Merge vers `dev`
6. Merge `dev` → `main`
7. Tag de version (`git tag vX.Y.Z`)

---

## 6. CHANGELOG — Règles

Deux changelogs doivent être maintenus :
- `qcm_app/CHANGELOG.md` (local)
- `CHANGELOG.md` à la racine du repo (global)

Chaque version doit inclure :
- Ajouté
- Modifié
- Corrigé
- Technique (si pertinent)

Aucune version ne doit être publiée sans mise à jour du changelog.

---

## 7. Stratégie de tests

### Objectifs
- Vérifier la stabilité du scoring
- Garantir l’intégrité des examens
- Prévenir les régressions

### Types de tests
- Tests manuels documentés
- Tests PHP simples (fonctions critiques)
- Tests de parsing PDF sur échantillons fixes

Les tests doivent être **simples, reproductibles, sans outillage lourd**.

---

## 8. Évolution des examens

### Modes d’examen

| Mode                   | Paramètres utilisateur | Timer | Score | Historique | Leaderboard |
|------------------------|------------------------|-------|-------|------------|-------------|
| Entraînement           | nb questions           | Non   | Oui   | Oui        | Non         |
| Entraînement chronométré | nb questions, durée | Oui   | Oui   | Oui        | Non         |
| Officiel               | Aucun                  | 60 min| Oui   | Oui        | Non         |
| Défis des admins       | Aucun                  | Oui   | Oui   | Oui        | Oui (Top 10) |

Les statistiques doivent être **séparées par mode** pour chaque utilisateur.
Les règles spécifiques (soumission bloquée, timeout forcé, non-modifiabilité)
doivent être clairement affichées à l’utilisateur AVANT le démarrage de l’examen.

---

## 9. Gestion des utilisateurs

### Rôles

- **Utilisateur**
  - passer examens
  - consulter historique
  - consulter stats & classement

- **Administrateur**
  - créer examens
  - importer questions
  - créer / gérer utilisateurs
  - consulter performances globales

L’authentification doit être simple, sécurisée, sans sur-ingénierie.

---

## 10. Frontend

### Contraintes
- Frameworks légers (Bootstrap, Pico.css, Alpine.js, HTMX…)
- Chargement via CDN
- Aucune compilation obligatoire
- Séparation stricte UI / logique

Le frontend consomme le backend via le middleware (API).

---

## 11. Base de données — Évolution et migrations

- La base de données **n’est pas figée**.
- Toute évolution passe par une migration SQL versionnée.

### Règles
1. Créer un fichier dans `db/migrations/`
2. Numérotation incrémentale (`XXX_description.sql`)
3. Documenter la migration dans `db/MIGRATIONS.md`
4. Mettre à jour le `CHANGELOG.md`

Aucune modification directe de table sans migration documentée.

---

## 12. Revert / Rollback — Règles officielles

### Revert de code
- Utiliser `git revert` pour annuler un commit déjà mergé
- Utiliser `git reset` uniquement sur branches locales non partagées

### Revert de fonctionnalité
- Créer une branche `fix/revert_<feature>`
- Annuler proprement le comportement
- Mettre à jour le changelog

### Revert base de données
- Une migration **ne doit jamais être supprimée**
- En cas d’erreur :
  - créer une **migration inverse** (`XXX_rollback_*.sql`)
  - documenter le rollback dans `MIGRATIONS.md`

---

## 13. Règles de collaboration avec IA

- Toute génération de code doit respecter ce document
- Pas de framework non validé
- Pas de logique métier dans le frontend
- Priorité à la lisibilité
- Chaque ajout significatif doit être versionné et documenté

---

## 14. Principe directeur

> “Faire simple, mais solide.  
> Ajouter seulement ce qui est justifié.  
> Toujours pouvoir expliquer chaque ligne de code.”


## 15. Suivi des chantiers — task.md (OBLIGATOIRE)

Chaque chantier structurant du projet (ex. : utilisateurs, modes d’examen, frontend)
DOIT être accompagné d’un fichier `task.md`.

### Règles obligatoires

- Le fichier `task.md` est créé à la racine de `qcm_app/`
- Chaque chantier dispose de :
  - une section dédiée
  - une checklist explicite `[ ] / [x]`
- Toute tâche commencée DOIT être listée
- Toute tâche terminée DOIT être cochée
- Une tâche cochée **ne doit jamais être supprimée**
- Les décisions importantes doivent être consignées dans une section :
  `Notes / décisions`

### Objectif

- éviter le travail implicite
- rendre l’avancement lisible
- permettre la revue critique avant commit ou release

Toute contribution (humaine ou assistée par IA) qui ne met pas à jour `task.md`
est considérée comme **incomplète**.

Cette règle est OBLIGATOIRE : la présence et la mise à jour de `task.md` sont vérifiées avant tout merge de branche liée à un chantier.

## 16. Défis des admins & leaderboard

Les "défis des admins" sont des examens spéciaux configurés exclusivement
par les administrateurs.

### Règles fondamentales

- Un défi admin définit :
  - le nombre de questions
  - la durée totale
  - les règles spécifiques du défi
- L’utilisateur ne peut modifier aucun paramètre
- Le timer est bloquant
- La soumission est forcée à l’expiration du temps

### Leaderboard

Chaque défi admin dispose d’un leaderboard dédié affichant
les **10 meilleures performances**.

#### Règles de classement (officielles)

1. Score décroissant
2. Temps passé croissant
3. Date de soumission la plus ancienne

Une seule entrée par utilisateur est affichée dans le leaderboard
(la meilleure performance).

Ces règles sont non négociables et doivent être respectées
par toute implémentation présente ou future.

## Référence officielle — Workflow Git

Les règles opérationnelles Git et GitHub du projet ne sont pas définies
directement dans ce document.

Elles sont formalisées dans le fichier : qcm_app/GIT_WORKFLOW.md


Ce fichier est **la référence unique et obligatoire** pour :
- la création et la gestion des branches,
- les merges,
- le versioning,
- les releases,
- la gestion des reverts (code et base de données).

Toute contribution au projet (humaine ou assistée par IA, y compris GitHub Copilot)
doit impérativement respecter les règles définies dans `GIT_WORKFLOW.md`.

En cas de contradiction entre ce document et `GIT_WORKFLOW.md`,
**`GIT_WORKFLOW.md` fait foi** sur tous les aspects liés à Git et au versioning.
