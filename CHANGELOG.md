# Changelog - Mini_Outils

## 2025-12-29 - `qcm_app` v0.3.1
- Corrigé : Correction du parsing PDF (fix preg_split) pour éviter des warnings et garantir l'import des questions.
- Technique : Réorganisation des sources (app/core, app/services, app/pdf) et ajout de helpers centralisés — aucun changement de logique métier.

## 2025-12-11 - `qcm_app` v0.1.0
- **Première release** : Application PHP minimaliste pour gestion d'examens QCM
- Import de QCM depuis PDF via Smalot\PdfParser 2.12.2 (PHP pur, sans pdftotext)
- Création d'examens, tirage aléatoire de questions, saisie réponses, score et correction détaillée
- Support QCM simple/multiple, stockage explication, réponses en session (pas d'historique V1)
- Architecture ultra-minimaliste : PHP procédural, PDO, point d'entrée unique `public/index.php`

