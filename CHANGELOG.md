# Changelog - Mini_Outils

## 2025-12-29 - `qcm_app` v0.3.1
- Corrigé : Correction du parsing PDF (fix preg_split) pour éviter des warnings et garantir l'import des questions.
- Technique : Réorganisation des sources (app/core, app/services, app/pdf) et ajout de helpers centralisés — aucun changement de logique métier.

## 2025-12-30 - `qcm_app` v0.4.0
- Ajout : Authentification utilisateur (login/logout), gestion multi-utilisateurs et service d'administration des utilisateurs
- Ajout : Page d'administration `admin_users` pour création/gestion des comptes
- Modifié : Accès protégé par défaut — application accessible seulement après authentification (sauf `/login`/`/logout`)

## 2025-12-11 - `qcm_app` v0.3.0
- **Statistiques par examen** : Page détaillée avec vue d'ensemble des performances (moyenne, meilleur/pire, évolution)
- **Groupement par examen** : L'historique personnel groupe maintenant les tentatives par examen
- **Métriques de performance** : Score moyen, meilleur/pire score, évolution, tendance
- **Graphique d'évolution** : Visualisation simple des scores dans le temps
- **Analyse de progression** : Comparaison première vs dernière tentative avec indicateur d'amélioration
- **Historique des tentatives** : Sauvegarde en base de données (tables `attempts` et `attempt_answers`)
- **Scoring partiel** : Calcul de score partiel pour QCM à choix multiple (formule : (TP - FP) / N)
- **Identifiant utilisateur** : Champ optionnel pour enregistrer l'historique personnel
- Page "Mon historique" : Consultation des tentatives par identifiant utilisateur (groupé)
- Page "Correction" : Support pour affichage depuis `attempt_id` (en plus de la session)

## 2025-12-11 - `qcm_app` v0.1.0
- **Première release** : Application PHP minimaliste pour gestion d'examens QCM
- Import de QCM depuis PDF via Smalot\PdfParser 2.12.2 (PHP pur, sans pdftotext)
- Création d'examens, tirage aléatoire de questions, saisie réponses, score et correction détaillée
- Support QCM simple/multiple, stockage explication, réponses en session (pas d'historique V1)
- Architecture ultra-minimaliste : PHP procédural, PDO, point d'entrée unique `public/index.php`

