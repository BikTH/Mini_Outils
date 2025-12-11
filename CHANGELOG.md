# Changelog - Mini_Outils

## 2025-12-11 - `qcm_app` v0.3.0 (en développement)
- **Statistiques par examen** : Visualisation détaillée des performances avec métriques (moyenne, meilleur/pire, évolution)
- **Graphique d'évolution** : Affichage simple des scores dans le temps
- **Analyse de progression** : Comparaison première vs dernière tentative avec indicateur de tendance
- Historique groupé par examen avec résumé et accès aux statistiques détaillées

## 2025-12-11 - `qcm_app` v0.1.0
- **Première release** : Application PHP minimaliste pour gestion d'examens QCM
- Import de QCM depuis PDF via Smalot\PdfParser 2.12.2 (PHP pur, sans pdftotext)
- Création d'examens, tirage aléatoire de questions, saisie réponses, score et correction détaillée
- Support QCM simple/multiple, stockage explication, réponses en session (pas d'historique V1)
- Architecture ultra-minimaliste : PHP procédural, PDO, point d'entrée unique `public/index.php`

