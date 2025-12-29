# Changelog - qcm_app

## [0.3.0] - 2025-12-11

### Ajouté
- **Statistiques par examen** : Page détaillée avec vue d'ensemble des performances
- **Groupement par examen** : L'historique personnel groupe maintenant les tentatives par examen
- **Métriques de performance** : Score moyen, meilleur/pire score, évolution, tendance
- **Graphique d'évolution** : Visualisation simple des scores dans le temps
- **Analyse de progression** : Comparaison première vs dernière tentative avec indicateur d'amélioration
- **Liste chronologique** : Affichage des 10 dernières tentatives avec liens vers les corrections

### Modifié
- Page "Mon historique" : Affichage groupé par examen avec résumé et lien vers statistiques
- Page "Correction" : Support pour affichage depuis `attempt_id` (en plus de la session)

### Technique
- Nouvelles fonctions dans `app/exam_service.php` : `getAttemptsForUserAndExam()`, `computeExamStatistics()`
- Calcul automatique des tendances (amélioration/baisse/stable)
- Graphique simple en HTML/CSS (barres horizontales colorées)

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

