# Mini_Outils

Mono-repo de petits outils/projets PHP légers. Chaque sous-dossier contient un mini-projet autonome. Le premier projet ajouté est `qcm_app`, une appli QCM minimale avec import de PDF via Smalot\PdfParser.

## Arborescence
- `qcm_app/` : application QCM (admin création d’examen, import PDF, passage d’épreuve, correction).
- `README.md`, `CHANGELOG.md` : documentation globale du mono-repo.
- `.gitignore` : ignore les dépendances externes déposées manuellement et les uploads locaux.

## qcm_app (résumé)
- Stack : PHP procédural + PDO, MySQL/MariaDB, Apache. Aucun framework.
- Import PDF : Smalot\PdfParser 2.12.2 (déposée manuellement dans `qcm_app/public/vendor_pdfparser/` ou `qcm_app/public/pdfparser/`). Pas de `pdftotext`, pas de shell_exec.
- Fonctionnalités : création d’examens, import de QCM (QUESTION NO, options A./B./C./…, Correct Answer), tirage aléatoire, saisie réponses, score, correction détaillée avec explication.
- V1 : QCM simple/multiple ; questions ouvertes non notées (conservées mais non évaluées).

### Pré-requis
- PHP 8+, extension PDO + driver MySQL.
- MySQL/MariaDB.
- Apache (ou autre serveur capable de servir `public/` comme docroot).
- Dépendance manuelle : Smalot\PdfParser 2.12.2 placée dans `qcm_app/public/vendor_pdfparser/` (ou `public/pdfparser/`). Ce dossier est ignoré par Git.

### Installation rapide (Linux / Windows)
1. Cloner ce repo :
   ```bash
   git clone https://github.com/BikTH/Mini_Outils.git
   cd Mini_Outils/qcm_app
   ```
2. Déposer Smalot\PdfParser 2.12.2 dans `public/vendor_pdfparser/` (ou `public/pdfparser/`).
3. Config BDD : exécuter le script SQL (création DB `qcm_app`, user `qcm_user`, tables `exams`, `questions`, `options`). Ajuster `app/config.php` (DB_* et BASE_URL).
4. Droits : rendre `uploads/pdf/` inscriptible par le serveur web.
5. Accès : `http://localhost/qcm/public` (adapter BASE_URL et virtual host selon emplacement).

### Usage qcm_app
- Admin : créer un examen, importer un PDF QCM (format attendu : “QUESTION NO: …”, options A./B./…, “Correct Answer: …” avec réponses séparées par virgule possible).
- Passer l’épreuve : tirage aléatoire de N questions (nb_questions si défini, sinon fallback), réponses radio/checkbox, score, correction avec mise en évidence des bonnes réponses et celles choisies.
- Persistance des réponses dans la session uniquement (pas d’historique V1).

## Ajouter un nouveau mini-projet
1. Créer un dossier à la racine (ex. `mon_outil/`).
2. Ajouter un README local et, si besoin, une section dans `CHANGELOG.md`.
3. Déposer les dépendances non-Composers dans un sous-dossier ignoré par Git si nécessaire.
4. Commiter et pousser.

## Licence
Voir la licence du dépôt (si définie) ; à défaut, tous droits réservés à l’auteur.

