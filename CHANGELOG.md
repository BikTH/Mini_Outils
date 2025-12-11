# Changelog - Mini_Outils

## 2025-12-11 - `qcm_app` v0.2.0
- **Historique des tentatives** : Sauvegarde en base de données (tables `attempts` et `attempt_answers`)
- **Scoring partiel** : Calcul de score partiel pour QCM à choix multiple (formule : (TP - FP) / N)
- **Identifiant utilisateur** : Champ optionnel pour enregistrer l'historique personnel
- **Page "Mon historique"** : Consultation des tentatives par identifiant utilisateur
- **Menu renommé** : "Admin" → "Menu d'édition des examens"
- Correction détaillée lue depuis la base (plus de session uniquement)

## 2025-12-11 - `qcm_app` v0.1.0
- **Première release** : Application PHP minimaliste pour gestion d'examens QCM
- Import de QCM depuis PDF via Smalot\PdfParser 2.12.2 (PHP pur, sans pdftotext)
- Création d'examens, tirage aléatoire de questions, saisie réponses, score et correction détaillée
- Support QCM simple/multiple, stockage explication, réponses en session (pas d'historique V1)
- Architecture ultra-minimaliste : PHP procédural, PDO, point d'entrée unique `public/index.php`

