# Git Workflow & Versioning Rules — QCM App

Ce document définit les **11 règles opérationnelles obligatoires** pour l’utilisation de Git et GitHub dans le projet QCM App.

Il complète et applique les principes définis dans :
**Global_Context_&_Goal.md**

Toute contribution au projet doit respecter ces règles.

---

## 1. Branches permanentes

- `main` : branche stable, toujours fonctionnelle et toujours taguée.
- `dev` : branche d’intégration continue.

❌ Aucun commit direct sur `main`.

---

## 2. Branches temporaires

- `feature/<nom>` : nouvelle fonctionnalité
- `fix/<nom>` : correction standard
- `hotfix/<nom>` : correction urgente depuis `main`
- `revert/<nom>` : rollback propre

Chaque branche a **un objectif unique**.

---

## 3. Création d’une fonctionnalité

Toute fonctionnalité part de `dev` :

```bash
git checkout dev
git pull origin dev
git checkout -b feature/<nom>
```

---

## 4. Discipline des commits

- Commits petits et explicites
- Préfixes recommandés :
  - `feat:` nouvelle fonctionnalité
  - `fix:` correction
  - `docs:` documentation
  - `refactor:` refactor sans impact fonctionnel
  - `test:` tests

---

## 5. Tests avant merge

Avant tout merge :
- application fonctionnelle
- pas d’erreurs PHP
- base de données cohérente
- migrations documentées
- changelog mis à jour

---

## 6. Merge vers `dev`

```bash
git checkout dev
git pull origin dev
git merge feature/<nom>
```

Puis suppression de la branche feature.

---

## 7. Release officielle

Toute release suit le schéma :

```
feature → dev → main → tag
```

Le numéro de version suit **Semantic Versioning** :
`MAJEUR.MINEUR.PATCH`

---

## 8. Tags de version

Tout merge vers `main` doit être tagué :

```bash
git tag -a vX.Y.Z -m "Release vX.Y.Z"
git push origin main --tags
```

❌ Aucun tag sur `dev`.

---

## 9. Revert de code

- `git revert` pour annuler un commit déjà partagé
- `git reset` autorisé uniquement sur branches locales non partagées

Jamais de reset sur `main` ou `dev`.

---

## 10. Revert base de données

- Une migration ne se supprime jamais
- Toute correction DB se fait via une **migration inverse**
- Chaque rollback doit être documenté

---

## 11. Règle absolue

> Toute modification doit être :
> - versionnée
> - documentée
> - réversible

Si ce n’est pas le cas, elle ne doit pas être mergée.

---

Fin du document.
