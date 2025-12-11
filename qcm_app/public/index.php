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
    // Support pour attempt_id (nouveau système)
    $attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : null;
    
    if ($attemptId) {
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
        
        $pdo = getPDO();
        foreach ($answersMap as $qid => $ansrow) {
            $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = :id");
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
    } else {
        // Ancien système (session) - rétrocompatibilité
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
    }
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
    if (empty($ui)) { 
        echo "<p>Indiquez votre identifiant pour voir votre historique.</p>"; 
        break; 
    }
    
    $attempts = getAttemptsForUser($ui);
    if (empty($attempts)) { 
        echo "<p>Aucune tentative enregistrée pour " . h($ui) . ".</p>"; 
        break; 
    }
    
    // Grouper les tentatives par examen
    $attemptsByExam = [];
    foreach ($attempts as $attempt) {
        $examId = (int)$attempt['exam_id'];
        if (!isset($attemptsByExam[$examId])) {
            $attemptsByExam[$examId] = [];
        }
        $attemptsByExam[$examId][] = $attempt;
    }
    
    echo "<h3>Vos examens</h3>";
    echo "<ul>";
    foreach ($attemptsByExam as $examId => $examAttempts) {
        $exam = getExamById($examId);
        if (!$exam) continue;
        
        $lastAttempt = $examAttempts[0]; // Première = la plus récente (tri DESC)
        $lastScore = (float)$lastAttempt['score_auto'];
        $lastMax = (float)$lastAttempt['total_points'];
        $lastPercentage = $lastMax > 0 ? round(($lastScore / $lastMax) * 100, 1) : 0;
        
        echo "<li>";
        echo "<strong>" . h($exam['titre']) . "</strong> ";
        echo "(" . count($examAttempts) . " tentative" . (count($examAttempts) > 1 ? "s" : "") . ") ";
        echo "- Dernier score : " . $lastPercentage . "% ";
        echo "- <a href=\"" . BASE_URL . "/?action=user_exam_stats&user_identifier=" . urlencode($ui) . "&exam_id=" . $examId . "\">Voir statistiques détaillées</a>";
        echo "</li>";
    }
    echo "</ul>";
    break;

  case 'user_exam_stats':
    $ui = isset($_GET['user_identifier']) ? trim($_GET['user_identifier']) : ($_SESSION['user_identifier'] ?? null);
    $examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
    
    if (empty($ui) || $examId === 0) {
        echo "<p>Paramètres manquants.</p>";
        echo '<p><a href="' . BASE_URL . '/?action=user_history">← Retour à l\'historique</a></p>';
        break;
    }
    
    $exam = getExamById($examId);
    if (!$exam) {
        echo "<p>Examen introuvable.</p>";
        break;
    }
    
    $attempts = getAttemptsForUserAndExam($ui, $examId);
    $stats = computeExamStatistics($attempts);
    
    echo "<h2>Statistiques - " . h($exam['titre']) . "</h2>";
    echo "<p>Utilisateur : <strong>" . h($ui) . "</strong></p>";
    echo '<p><a href="' . BASE_URL . '/?action=user_history&user_identifier=' . urlencode($ui) . '">← Retour à l\'historique</a></p>';
    echo "<hr>";
    
    if (empty($attempts)) {
        echo "<p>Aucune tentative pour cet examen.</p>";
        break;
    }
    
    // Vue d'ensemble
    echo "<h3>Vue d'ensemble</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
    echo "<tr><th>Métrique</th><th>Valeur</th></tr>";
    echo "<tr><td>Nombre total de tentatives</td><td><strong>" . $stats['total_attempts'] . "</strong></td></tr>";
    echo "<tr><td>Score moyen</td><td><strong>" . $stats['average_score'] . "%</strong></td></tr>";
    echo "<tr><td>Meilleur score</td><td><strong style='color:green;'>" . $stats['best_score'] . "%</strong></td></tr>";
    echo "<tr><td>Pire score</td><td><strong style='color:red;'>" . $stats['worst_score'] . "%</strong></td></tr>";
    echo "<tr><td>Premier score</td><td>" . $stats['first_score'] . "%</td></tr>";
    echo "<tr><td>Dernier score</td><td><strong>" . $stats['last_score'] . "%</strong></td></tr>";
    
    $improvementColor = $stats['improvement'] > 0 ? 'green' : ($stats['improvement'] < 0 ? 'red' : 'gray');
    $improvementSign = $stats['improvement'] > 0 ? '+' : '';
    echo "<tr><td>Évolution</td><td><strong style='color:" . $improvementColor . ";'>" . $improvementSign . $stats['improvement'] . "%</strong></td></tr>";
    
    $trendText = [
        'improving' => '📈 En amélioration',
        'declining' => '📉 En baisse',
        'stable' => '➡️ Stable'
    ];
    echo "<tr><td>Tendance</td><td>" . ($trendText[$stats['trend']] ?? $stats['trend']) . "</td></tr>";
    echo "</table>";
    
    // Graphique simple d'évolution
    echo "<h3>Évolution des scores</h3>";
    if (count($stats['scores']) > 0) {
        echo "<div style='border:1px solid #ccc; padding:20px; background:#f9f9f9;'>";
        $maxBarWidth = 500;
        foreach ($stats['scores'] as $idx => $scoreData) {
            $barWidth = ($scoreData['percentage'] / 100) * $maxBarWidth;
            $barColor = $scoreData['percentage'] >= 80 ? '#4CAF50' : ($scoreData['percentage'] >= 50 ? '#FFC107' : '#F44336');
            echo "<div style='margin-bottom:10px;'>";
            echo "<div style='display:inline-block; width:100px;'>Tentative " . ($idx + 1) . "</div>";
            echo "<div style='display:inline-block; width:" . $maxBarWidth . "px; background:#e0e0e0; border:1px solid #999;'>";
            echo "<div style='width:" . $barWidth . "px; background:" . $barColor . "; height:25px; text-align:center; color:white; line-height:25px;'>" . $scoreData['percentage'] . "%</div>";
            echo "</div>";
            echo "<span style='margin-left:10px;'>" . date('d/m/Y H:i', strtotime($scoreData['date'])) . "</span>";
            echo "</div>";
        }
        echo "</div>";
    }
    
    // Liste chronologique des tentatives
    echo "<h3>Dernières tentatives</h3>";
    $recentAttempts = array_slice($attempts, -10); // 10 dernières
    echo "<ul>";
    foreach (array_reverse($recentAttempts) as $attempt) {
        $score = (float)$attempt['score_auto'];
        $maxScore = (float)$attempt['total_points'];
        $percentage = $maxScore > 0 ? round(($score / $maxScore) * 100, 1) : 0;
        echo "<li>";
        echo date('d/m/Y H:i', strtotime($attempt['date_end'])) . " - ";
        echo "Score : <strong>" . $percentage . "%</strong> (" . round($score, 2) . " / " . round($maxScore, 2) . ") ";
        echo "- <a href=\"" . BASE_URL . "/?action=show_correction&attempt_id=" . $attempt['id'] . "\">Voir correction</a>";
        echo "</li>";
    }
    echo "</ul>";
    
    if (count($attempts) > 10) {
        echo "<p><em>(Affichage des 10 dernières tentatives sur " . count($attempts) . ")</em></p>";
    }
    break;

  default:
    echo "<p>Action inconnue</p>";
    break;
}
?>
</body>
</html>

