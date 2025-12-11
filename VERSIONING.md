# Guide de versioning - Mini_Outils

Ce document définit les règles de versioning pour chaque mini-outil du repo, basées sur [SemVer](https://semver.org/) (Semantic Versioning).

## Format de version

Chaque mini-outil suit le format : `MAJOR.MINOR.PATCH`

- **MAJOR** (X.0.0) : Changements incompatibles avec les versions précédentes (breaking changes)
- **MINOR** (0.X.0) : Nouvelles fonctionnalités rétrocompatibles
- **PATCH** (0.0.X) : Corrections de bugs, améliorations mineures rétrocompatibles

## Règles de décision

### MAJOR (X.0.0)
- Changement de structure de base de données nécessitant migration
- Modification d'API publique (si applicable)
- Changement de format de fichier de configuration
- Suppression de fonctionnalités existantes
- Changement majeur d'architecture

### MINOR (0.X.0)
- Ajout de nouvelles fonctionnalités
- Ajout de nouveaux champs optionnels en base (sans casser l'existant)
- Amélioration significative d'une fonctionnalité existante
- Ajout de nouveaux types de questions/formats supportés

### PATCH (0.0.X)
- Correction de bugs
- Amélioration de performance sans changement fonctionnel
- Correction de typos, amélioration de messages d'erreur
- Ajustements mineurs d'interface utilisateur
- Correction de parsing PDF (sans changement de format)

## Fichiers à mettre à jour lors d'une release

Pour chaque release, mettre à jour :

1. **`<projet>/VERSION`** : Version actuelle du projet (ex. `qcm_app/VERSION`)
2. **`CHANGELOG.md`** (global) : Ajouter entrée avec date, version, changements
3. **`<projet>/CHANGELOG.md`** (si présent) : Détails spécifiques au projet
4. **Tag Git** : `git tag <projet>-vX.Y.Z` (ex. `qcm_app-v0.1.0`)

## Exemples

### Exemple 1 : Patch (0.0.1 -> 0.0.2)
- Bug : correction du parsing des réponses multiples
- Mise à jour : `qcm_app/VERSION` = `0.0.2`
- Tag : `qcm_app-v0.0.2`

### Exemple 2 : Minor (0.1.0 -> 0.2.0)
- Feature : ajout de l'historique des tentatives
- Mise à jour : `qcm_app/VERSION` = `0.2.0`
- Tag : `qcm_app-v0.2.0`

### Exemple 3 : Major (0.2.0 -> 1.0.0)
- Breaking : changement de structure BDD, migration requise
- Mise à jour : `qcm_app/VERSION` = `1.0.0`
- Tag : `qcm_app-v1.0.0`

## Workflow de versioning

1. Déterminer le type de changement (MAJOR/MINOR/PATCH)
2. Mettre à jour le fichier `<projet>/VERSION`
3. Mettre à jour `CHANGELOG.md` avec les changements
4. Commit : `git commit -m "chore: bump <projet> to vX.Y.Z"`
5. Tag : `git tag <projet>-vX.Y.Z`
6. Push : `git push origin main --tags`

