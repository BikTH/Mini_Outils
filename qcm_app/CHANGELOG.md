# Changelog - qcm_app

## [0.2.0] - 2025-12-11

### Ajouté
- **Historique des tentatives** : Tables `attempts` et `attempt_answers` pour sauvegarder toutes les tentatives
- **Scoring partiel** : Calcul de score partiel pour QCM à choix multiple (formule : (TP - FP) / N où TP = true positives, FP = false positives, N = nombre de bonnes réponses)
- **Identifiant utilisateur** : Champ optionnel lors du passage d'épreuve pour enregistrer l'historique personnel
- **Page "Mon historique"** : Consultation des tentatives par identifiant utilisateur (`action=user_history`)
- **Page "Historique d'examen"** : Consultation de toutes les tentatives d'un examen (`action=exam_history`)
- Affichage du score partiel par question dans la correction détaillée
- Stockage des dates de début et fin d'épreuve

### Modifié
- **Menu renommé** : "Admin" → "Menu d'édition des examens"
- Correction détaillée lue depuis la base de données (plus de session uniquement)
- Score calculé avec scoring partiel au lieu d'un simple comptage binaire

### Technique
- Nouvelles fonctions dans `app/exam_service.php` : `getCorrectOptionIds()`, `computePartialScore()`, `saveAttempt()`, `getAttemptById()`, `getAttemptsForExam()`, `getAttemptsForUser()`
- Migration SQL fournie dans `migration_v0.2.0.sql`

## [0.1.0] - 2025-12-11

### Ajouté
- Création d'examens (admin)
- Import automatique de QCM depuis PDF (parsing QUESTION NO:, options A./B./C./…, Correct Answer:)
- Passage d'épreuve avec tirage aléatoire de N questions
- Saisie réponses (radio pour QCM simple, checkbox pour QCM multiple)
- Calcul et affichage du score
- Correction détaillée avec bonnes réponses et explications
- Support QCM simple et multiple (détection automatique)
- Stockage des explications après la ligne "Correct Answer:"

### Technique
- Architecture PHP procédurale ultra-minimaliste (sans framework)
- Point d'entrée unique : `public/index.php`
- Base de données MySQL/MariaDB avec PDO
- Bibliothèque Smalot\PdfParser 2.12.2 (déposée manuellement dans `public/vendor_pdfparser/`)
- Compatible Windows/Linux (pas de dépendances système)

### Limitations V0
- Pas d'historique des tentatives (réponses stockées en session uniquement)
- Questions ouvertes conservées mais non notées

