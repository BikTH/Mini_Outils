# QCM App 

Application PHP minimaliste (procédural, sans framework) pour créer des examens, importer des QCM depuis des PDF et passer des épreuves avec correction.

## Prérequis
- PHP 8+, MySQL/MariaDB, Apache.
- PDO activé.
- Bibliothèque Smalot\\PdfParser 2.12.2 (https://github.com/smalot/pdfparser/releases/tag/v2.12.2), l'extraire le renommer vendor_pdfparser et placée manuellement dans `public/vendor_pdfparser/`.  
  *Ce dossier est ignoré par Git ; déposez-y le contenu du package (avec `autoload.php` ou au moins `src/`).*

## Installation rapide
1. Copier le projet dans le docroot (ex. `/var/www/html/qcm` ou `C:\\xampp\\htdocs\\qcm`).
2. Configurer la BDD (voir script SQL fourni initialement) et ajuster `app/config.php` (`DB_*`, `BASE_URL`).
3. Placer Smalot\\PdfParser 2.12.2 dans `public/vendor_pdfparser/`.
4. S’assurer que `uploads/pdf/` est accessible en écriture par le serveur web.

## Utilisation
- Accès : `http://localhost/qcm/public` (ou adapter selon `BASE_URL`).
- Admin : créer un examen, importer un PDF.  
  Le parser extrait les blocs « QUESTION NO: », options A./B./C./… et « Correct Answer: », puis insère en base.
- Front : passer l’épreuve, soumettre, afficher la correction (stockée en session).

## Notes
- Import en PHP pur (Smalot\\PdfParser), aucune dépendance système externe.
- QCM à choix unique ou multiple (détectés selon le nombre de réponses correctes). Questions non notées/open non gérées en V0.

