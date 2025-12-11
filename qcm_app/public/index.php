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
    <a href="<?php echo BASE_URL; ?>/?action=admin_exams">Admin</a>
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
    <h2>Créer un examen</h2>
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
    ?>
    <h2>Passer: <?php echo h($exam['titre']); ?></h2>
    <form method="post" action="<?php echo BASE_URL; ?>/?action=submit_exam">
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
    if (!isset($_SESSION['current_exam_id'], $_SESSION['current_question_ids'])) { echo "<p>Aucune épreuve en cours.</p>"; break; }
    $questionIds = $_SESSION['current_question_ids'];
    $total = count($questionIds); $correct = 0; $answers = [];

    foreach ($questionIds as $qid) {
      $field = 'q_' . $qid;
      $selected = $_POST[$field] ?? [];
      if (!is_array($selected)) $selected = [$selected];
      $selIds = array_map('intval', $selected);
      $answers[$qid] = $selIds;
      if (isQuestionCorrect($qid, $selIds)) $correct++;
    }

    $_SESSION['last_exam_result'] = [
      'exam_id' => $_SESSION['current_exam_id'],
      'question_ids' => $questionIds,
      'answers' => $answers,
      'score' => $correct,
      'total' => $total
    ];
    echo "<h2>Résultat : $correct / $total</h2>";
    echo '<p><a href="' . BASE_URL . '/?action=show_correction">Voir la correction</a></p>';
    break;

  case 'show_correction':
    $res = $_SESSION['last_exam_result'] ?? null;
    if (!$res) { echo "<p>Aucun résultat.</p>"; break; }
    $exam = getExamById($res['exam_id']);
    echo "<h2>Correction : " . h($exam['titre']) . "</h2>";
    echo "<p>Score : " . $res['score'] . " / " . $res['total'] . "</p>";
    $pdo = getPDO();

    foreach ($res['question_ids'] as $i => $qid) {
      $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = :id");
      $stmt->execute([':id' => $qid]); $q = $stmt->fetch();
      echo "<hr><h3>Question " . ($i+1) . "</h3>";
      echo "<p>" . nl2br(h($q['enonce'])) . "</p>";
      $opts = getOptionsForQuestion($qid);
      $userSelected = $res['answers'][$qid] ?? [];
      echo "<ul>";
      foreach ($opts as $opt) {
        $isCorrect = (int)$opt['is_correct'] === 1;
        $userChose = in_array($opt['id'], $userSelected, true);
        echo "<li>";
        if ($isCorrect) echo "<strong>[Bonne]</strong> ";
        if ($userChose) echo "<em>[Votre]</em> ";
        echo h($opt['label'] . '. ' . $opt['texte']);
        echo "</li>";
      }
      echo "</ul>";
      if (!empty($q['explication'])) echo "<p><strong>Explication :</strong><br>" . nl2br(h($q['explication'])) . "</p>";
    }
    break;

  default:
    echo "<p>Action inconnue</p>";
    break;
}
?>
</body>
</html>

