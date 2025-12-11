<?php
// public/index.php
session_start();
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/exam_service.php';
require_once __DIR__ . '/../app/pdf_parser.php';

$action = $_GET['action'] ?? 'home';
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>QCM App</title>
</head>
<body>
  <h1>QCM App (V1)</h1>
  <nav>
    <a href="<?php echo BASE_URL; ?>/?action=home">Accueil</a> |
    <a href="<?php echo BASE_URL; ?>/?action=admin_exams">Menu d'édition des examens</a> |
    <a href="<?php echo BASE_URL; ?>/?action=user_history">Mon historique</a>
  </nav>
  <hr>

<?php
switch ($action) {
  case 'home':
    $exams = getAllExams();
    echo "<h2>Examens</h2>";
    if (empty($exams)) echo "<p>Aucun examen disponible.</p>";
    else {
      echo "<ul>";
      foreach ($exams as $exam) {
        echo "<li>" . h($exam['titre']) . " - <a href=\"" . BASE_URL . "/?action=take_exam&exam_id=" . $exam['id'] . "\">Passer</a></li>";
      }
      echo "</ul>";
    }
    break;

  case 'admin_exams':
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $titre = trim($_POST['titre'] ?? '');
      $desc = trim($_POST['description'] ?? '');
      $nb = !empty($_POST['nb_questions']) ? (int)$_POST['nb_questions'] : null;
      if ($titre !== '') {
        createExam($titre, $desc, $nb);
        echo "<p>Examen créé.</p>";
      } else {
        echo "<p>Le titre est requis.</p>";
      }
    }
    $exams = getAllExams();
    ?>
    <h2>Menu d'édition des examens</h2>
    <h3>Créer un examen</h3>
    <form method="post" action="<?php echo BASE_URL; ?>/?action=admin_exams">
      <label>Titre: <input name="titre" required></label><br>
      <label>Description: <textarea name="description"></textarea></label><br>
      <label>Nbre questions par épreuve (optionnel): <input type="number" name="nb_questions"></label><br>
      <button type="submit">Créer</button>
    </form>

    <h3>Examens existants</h3>
    <ul>
    <?php foreach ($exams as $e): ?>
      <li><?php echo h($e['titre']); ?> - <a href="<?php echo BASE_URL; ?>/?action=admin_import_pdf&exam_id=<?php echo $e['id']; ?>">Importer PDF</a></li>
    <?php endforeach; ?>
    </ul>
    <?php
    break;

  case 'admin_import_pdf':
    $examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
    $exam = $examId ? getExamById($examId) : null;
    if (!$exam) { echo "<p>Examen introuvable.</p>"; break; }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf'])) {
      $f = $_FILES['pdf'];
      if ($f['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/pdf/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $dest = $uploadDir . basename($f['name']);
        if (move_uploaded_file($f['tmp_name'], $dest)) {
          $count = importQuestionsFromPdf($examId, $dest);
          echo "<p>Import terminé : " . (int)$count . " questions importées.</p>";
        } else echo "<p>Erreur copy</p>";
      } else echo "<p>Erreur upload</p>";
    }
    ?>
    <h2>Importer PDF pour <?php echo h($exam['titre']); ?></h2>
    <form method="post" enctype="multipart/form-data" action="<?php echo BASE_URL; ?>/?action=admin_import_pdf&exam_id=<?php echo $exam['id']; ?>">
      <input type="file" name="pdf" accept="application/pdf" required>
      <button type="submit">Importer</button>
    </form>
    <?php
    break;

  case 'take_exam':
    $examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
    $exam = $examId ? getExamById($examId) : null;
    if (!$exam) { echo "<p>Examen introuvable.</p>"; break; }
    $limit = $exam['nb_questions'] ?? 10;
    $questions = getRandomQuestionsForExam($examId, (int)$limit);
    if (empty($questions)) { echo "<p>Aucune question disponible.</p>"; break; }

    $_SESSION['current_exam_id'] = $examId;
    $_SESSION['current_question_ids'] = array_column($questions, 'id');
    $_SESSION['exam_start_time'] = date('Y-m-d H:i:s');
    ?>
    <h2>Passer: <?php echo h($exam['titre']); ?></h2>
    <form method="post" action="<?php echo BASE_URL; ?>/?action=submit_exam">
    <label>Votre identifiant (email ou pseudo) :
        <input type="text" name="user_identifier" value="<?php echo isset($_SESSION['user_identifier']) ? h($_SESSION['user_identifier']) : ''; ?>">
    </label>
    <p style="font-size:0.9em;color:#666;">(optionnel — permet d'enregistrer votre historique)</p>
    <hr>
    <?php foreach ($questions as $i => $q): 
      $opts = getOptionsForQuestion($q['id']);
      $isMultiple = $q['type'] === 'qcm_multiple';
      $name = 'q_' . $q['id'] . '[]';
    ?>
      <fieldset><legend>Question <?php echo $i+1; ?></legend>
        <p><?php echo nl2br(h($q['enonce'])); ?></p>
        <?php foreach ($opts as $opt): ?>
          <label>
            <input type="<?php echo $isMultiple ? 'checkbox' : 'radio'; ?>" name="<?php echo h($name); ?>" value="<?php echo $opt['id']; ?>">
            <?php echo h($opt['label'] . '. ' . $opt['texte']); ?>
          </label><br>
        <?php endforeach; ?>
      </fieldset>
    <?php endforeach; ?>
      <button type="submit">Valider</button>
    </form>
    <?php
    break;

  case 'submit_exam':
    if (!isset($_SESSION['current_exam_id'], $_SESSION['current_question_ids'])) {
        echo "<p>Aucune épreuve en cours.</p>";
        break;
    }

    $examId = (int)$_SESSION['current_exam_id'];
    $questionIds = $_SESSION['current_question_ids'];

    $totalPoints = 0.0;
    $scoreSum = 0.0;
    $answersForSave = [];
    $dateStart = $_SESSION['exam_start_time'] ?? date('Y-m-d H:i:s');
    $dateEnd = date('Y-m-d H:i:s');

    // Récupérer identifiant utilisateur si fourni
    $userIdentifier = null;
    if (!empty($_POST['user_identifier'])) {
        $userIdentifier = trim($_POST['user_identifier']);
        $_SESSION['user_identifier'] = $userIdentifier;
    } else if (!empty($_SESSION['user_identifier'])) {
        $userIdentifier = $_SESSION['user_identifier'];
    }

    foreach ($questionIds as $qid) {
        $field = 'q_' . $qid;
        $selected = $_POST[$field] ?? [];
        if (!is_array($selected)) $selected = [$selected];
        $selectedIds = array_map('intval', array_filter($selected));

        list($partial, $isFull) = computePartialScore((int)$qid, $selectedIds);

        $totalPoints += 1.0;
        $scoreSum += $partial;

        $answersForSave[$qid] = [
            'selected' => $selectedIds,
            'partial' => $partial,
            'is_full' => $isFull
        ];
    }

    $attemptId = saveAttempt($examId, $userIdentifier, $dateStart, $dateEnd, $scoreSum, $totalPoints, $answersForSave);

    $_SESSION['last_attempt_id'] = $attemptId;

    echo "<h2>Résultat</h2>";
    echo "<p>Score : " . round($scoreSum, 2) . " / " . round($totalPoints, 2) . "</p>";
    echo '<p><a href="' . BASE_URL . '/?action=show_correction&attempt_id=' . $attemptId . '">Voir la correction détaillée</a></p>';
    break;

  case 'show_correction':
    $attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : ($_SESSION['last_attempt_id'] ?? null);
    if (!$attemptId) {
        echo "<p>Aucun résultat disponible.</p>";
        break;
    }

    $attempt = getAttemptById($attemptId);
    if (!$attempt) {
        echo "<p>Tentative introuvable.</p>";
        break;
    }

    $exam = getExamById($attempt['exam_id']);
    echo "<h2>Correction de l'examen : " . h($exam['titre']) . "</h2>";
    echo "<p>Score : " . round($attempt['score_auto'], 2) . " / " . round($attempt['total_points'], 2) . "</p>";
    echo "<p>Date : " . h($attempt['date_end']) . "</p>";

    $answersMap = [];
    foreach ($attempt['answers'] as $a) {
        $answersMap[$a['question_id']] = $a;
    }

    foreach ($answersMap as $qid => $ansrow) {
        $stmt = getPDO()->prepare("SELECT * FROM questions WHERE id = :id");
        $stmt->execute([':id' => $qid]);
        $q = $stmt->fetch();
        if (!$q) continue;

        echo "<hr>";
        echo "<h3>Question</h3>";
        echo "<p>" . nl2br(h($q['enonce'])) . "</p>";

        $opts = getOptionsForQuestion($qid);
        $selected = json_decode($ansrow['selected_option_ids'] ?? '[]', true);
        if (!is_array($selected)) $selected = [];

        echo "<ul>";
        foreach ($opts as $opt) {
            $isCorrect = (int)$opt['is_correct'] === 1;
            $userChose = in_array($opt['id'], $selected, true);
            echo "<li>";
            if ($isCorrect) echo "<strong>[Bonne réponse]</strong> ";
            if ($userChose) echo "<em>[Votre choix]</em> ";
            echo h($opt['label'] . '. ' . $opt['texte']);
            echo "</li>";
        }
        echo "</ul>";

        echo "<p><strong>Score sur cette question :</strong> " . round((float)$ansrow['partial_score'], 3) . " / 1</p>";

        if (!empty($q['explication'])) {
            echo "<p><strong>Explication :</strong><br>" . nl2br(h($q['explication'])) . "</p>";
        }
    }
    break;

  case 'exam_history':
    $examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
    $exam = getExamById($examId);
    if (!$exam) { echo "<p>Examen introuvable.</p>"; break; }
    $attempts = getAttemptsForExam($examId);
    echo "<h2>Historique - " . h($exam['titre']) . "</h2>";
    if (empty($attempts)) { echo "<p>Aucune tentative.</p>"; break; }
    echo "<ul>";
    foreach ($attempts as $a) {
        $examObj = getExamById($a['exam_id']);
        echo "<li>" . h($a['user_identifier'] ?? 'anonyme') . " - " . h($a['date_end']) . " - Score: " . round($a['score_auto'],2) . " / " . round($a['total_points'],2) . " - <a href=\"" . BASE_URL . "/?action=show_correction&attempt_id=" . $a['id'] . "\">Voir</a></li>";
    }
    echo "</ul>";
    break;

  case 'user_history':
    $ui = $_SESSION['user_identifier'] ?? null;
    if (!empty($_GET['user_identifier'])) $ui = trim($_GET['user_identifier']);
    echo "<h2>Historique personnel</h2>";
    ?>
    <form method="get" action="<?php echo BASE_URL; ?>/">
        <input type="hidden" name="action" value="user_history">
        <label>Identifiant : <input type="text" name="user_identifier" value="<?php echo h($ui ?? ''); ?>"></label>
        <button type="submit">Voir</button>
    </form>
    <?php
    if (empty($ui)) { echo "<p>Indiquez votre identifiant pour voir votre historique.</p>"; break; }
    $attempts = getAttemptsForUser($ui);
    if (empty($attempts)) { echo "<p>Aucune tentative enregistrée pour " . h($ui) . ".</p>"; break; }
    echo "<p>Dernières tentatives pour : <strong>" . h($ui) . "</strong></p><ul>";
    foreach ($attempts as $a) {
        $examObj = getExamById($a['exam_id']);
        echo "<li>" . h($examObj['titre'] ?? 'Examen') . " - " . h($a['date_end']) . " - Score: " . round($a['score_auto'],2) . " / " . round($a['total_points'],2) . " - <a href=\"" . BASE_URL . "/?action=show_correction&attempt_id=" . $a['id'] . "\">Voir</a></li>";
    }
    echo "</ul>";
    break;

  default:
    echo "<p>Action inconnue</p>";
    break;
}
?>
</body>
</html>

