<?php
// public/index.php
session_start();
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/core/helpers.php';
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/exam_service.php';
require_once __DIR__ . '/../app/pdf_parser.php';
// middleware (simple functions)
require_once __DIR__ . '/../middleware/middlewares/auth_middleware.php';
require_once __DIR__ . '/../middleware/middlewares/role_middleware.php';

$action = $_GET['action'] ?? 'home';

// Enforce authentication for all actions except login/logout
$publicActions = ['login', 'logout'];
if (!in_array($action, $publicActions, true)) {
  require_auth();
}

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
    <?php if (isAuthenticated()): ?>
      <span>Bienvenue <?php echo h(currentUser()['display_name'] ?? currentUser()['username']); ?> — </span>
      <a href="<?php echo BASE_URL; ?>/?action=home">Accueil</a> |
      <a href="<?php echo BASE_URL; ?>/?action=user_history">Mon historique</a>
      <?php if (userHasRole('admin')): ?> |
        <a href="<?php echo BASE_URL; ?>/?action=admin_exams">Menu d'édition des examens</a> |
        <a href="<?php echo BASE_URL; ?>/?action=admin_users">Gestion des utilisateurs</a> |
        <a href="<?php echo BASE_URL; ?>/?action=admin_challenges">Admin Challenges</a>
      <?php endif; ?>
      | <a href="<?php echo BASE_URL; ?>/?action=logout">Se déconnecter</a>
    <?php else: ?>
      <a href="<?php echo BASE_URL; ?>/?action=login">Se connecter</a>
    <?php endif; ?>
  </nav>
  <hr>

<?php
switch ($action) {
  case 'login':
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $u = trim($_POST['username'] ?? '');
      $p = $_POST['password'] ?? '';
      if ($u !== '' && login($u, $p)) {
        header('Location: ' . BASE_URL . '/'); exit;
      } else {
        echo "<p style='color:red;'>Identifiants invalides.</p>";
      }
    }
    ?>
    <h2>Connexion</h2>
    <form method="post" action="<?php echo BASE_URL; ?>/?action=login">
      <label>Utilisateur: <input name="username" required></label><br>
      <label>Mot de passe: <input type="password" name="password" required></label><br>
      <button type="submit">Se connecter</button>
    </form>
    <?php
    break;

  case 'logout':
    logout();
    header('Location: ' . BASE_URL . '/'); exit;
    break;

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
    // protect admin routes
    require_role('admin');
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
    // protect admin import
    require_role('admin');
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

  case 'admin_users':
    // Admin-only: create users and assign roles
    require_role('admin');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $username = trim($_POST['username'] ?? '');
      $password = $_POST['password'] ?? '';
      $display = trim($_POST['display_name'] ?? '');
      $role = trim($_POST['role'] ?? 'user');
      if ($username === '' || $password === '') {
        echo "<p style='color:red;'>Le nom d'utilisateur et le mot de passe sont requis.</p>";
      } else {
        $uid = createUser($username, $password, $display ?: null);
        if ($role) assignRole($uid, $role);
        echo "<p>Utilisateur créé (ID: " . (int)$uid . ").</p>";
      }
    }

    $users = listUsers();
    ?>
    <h2>Gestion des utilisateurs</h2>
    <h3>Créer un utilisateur</h3>
    <form method="post" action="<?php echo BASE_URL; ?>/?action=admin_users">
      <label>Nom d'utilisateur: <input name="username" required></label><br>
      <label>Mot de passe: <input type="password" name="password" required></label><br>
      <label>Nom affiché: <input name="display_name"></label><br>
      <label>Rôle: 
        <select name="role">
          <option value="user">Utilisateur</option>
          <option value="admin">Administrateur</option>
        </select>
      </label><br>
      <button type="submit">Créer</button>
    </form>

    <h3>Utilisateurs existants</h3>
    <ul>
    <?php foreach ($users as $u): ?>
      <li><?php echo h($u['username']); ?> (<?php echo h($u['display_name'] ?? '-'); ?>) - créé le <?php echo h($u['created_at']); ?></li>
    <?php endforeach; ?>
    </ul>
    <?php
    break;

  case 'admin_challenges':
    require_role('admin');
    $examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
    $exams = getAllExams();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (!empty($_POST['action']) && $_POST['action'] === 'create') {
        $title = trim($_POST['title'] ?? '');
        $nbq = !empty($_POST['nb_questions']) ? (int)$_POST['nb_questions'] : 0;
        $time = isset($_POST['time_limit_seconds']) && $_POST['time_limit_seconds'] !== '' ? (int)$_POST['time_limit_seconds'] : null;
        $createdBy = currentUser()['username'] ?? null;
        if ($examId && $title && $nbq > 0) {
          $cid = createAdminChallenge($examId, $title, $nbq, $time, $createdBy);
          echo "<p>Challenge créé (ID: " . (int)$cid . ")</p>";
        } else {
          echo "<p style='color:red;'>Les champs sont incomplets.</p>";
        }
      } elseif (!empty($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['challenge_id'])) {
        $cid = (int)$_POST['challenge_id'];
        deleteAdminChallenge($cid);
        echo "<p>Challenge supprimé.</p>";
      }
    }

    ?>
    <h2>Gestion des Admin Challenges</h2>
    <form method="get" action="<?php echo BASE_URL; ?>/">
      <input type="hidden" name="action" value="admin_challenges">
      <label>Examen: <select name="exam_id" onchange="this.form.submit()">
        <option value="">-- Choisir --</option>
        <?php foreach ($exams as $ex): ?>
          <option value="<?php echo (int)$ex['id']; ?>" <?php echo ($examId == $ex['id']) ? 'selected' : ''; ?>><?php echo h($ex['titre']); ?></option>
        <?php endforeach; ?>
      </select></label>
    </form>
    <?php if ($examId):
      $chals = getAdminChallengesForExam($examId);
    ?>
      <h3>Créer un challenge pour l'examen</h3>
      <form method="post" action="<?php echo BASE_URL; ?>/?action=admin_challenges&exam_id=<?php echo $examId; ?>">
        <input type="hidden" name="action" value="create">
        <label>Titre: <input name="title" required></label><br>
        <label>Nombre de questions: <input type="number" name="nb_questions" min="1" required></label><br>
        <label>Durée en secondes (optionnel): <input type="number" name="time_limit_seconds" min="5"></label><br>
        <button type="submit">Créer</button>
      </form>

      <h3>Challenges existants</h3>
      <ul>
      <?php foreach ($chals as $c): ?>
        <li><?php echo h($c['title']); ?> — <?php echo (int)$c['nb_questions']; ?> q — <?php echo $c['time_limit_seconds'] ? ((int)$c['time_limit_seconds'] . 's') : 'no time'; ?>
          <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="challenge_id" value="<?php echo (int)$c['id']; ?>">
            <button type="submit">Supprimer</button>
          </form>
          &nbsp; <a href="<?php echo BASE_URL; ?>/?action=admin_challenge_leaderboard&challenge_id=<?php echo (int)$c['id']; ?>">Voir leaderboard</a>
        </li>
      <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php
    break;

  case 'admin_challenge_leaderboard':
    $challengeId = isset($_GET['challenge_id']) ? (int)$_GET['challenge_id'] : 0;
    if (!$challengeId) { echo "<p>Challenge introuvable.</p>"; break; }
    $challenge = getAdminChallengeById($challengeId);
    if (!$challenge) { echo "<p>Challenge introuvable.</p>"; break; }
    $leaders = getLeaderboardForAdminChallenge($challengeId, 10);
    echo "<h2>Leaderboard: " . h($challenge['title']) . "</h2>";
    if (empty($leaders)) {
      echo "<p>Aucune tentative pour ce challenge.</p>";
    } else {
      echo "<ol>";
      foreach ($leaders as $l) {
        $pct = ($l['total_points'] > 0) ? round((($l['score_auto'] / $l['total_points']) * 100), 2) : 0;
        echo "<li>" . h($l['user_identifier'] ?? 'anon') . " — " . round($l['score_auto'],2) . " / " . round($l['total_points'],2) . " (" . $pct . "%) — " . h($l['date_end']) . "</li>";
      }
      echo "</ol>";
    }
    break;

  case 'take_exam':
    $examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
    $exam = $examId ? getExamById($examId) : null;
    if (!$exam) { echo "<p>Examen introuvable.</p>"; break; }

    // Mode selection / parameters
    $mode = $_GET['mode'] ?? $_POST['mode'] ?? null;
    if (!$mode) {
        // show selection form
        ?>
        <h2>Choisir le mode pour: <?php echo h($exam['titre']); ?></h2>
        <form method="get" action="<?php echo BASE_URL; ?>/">
          <input type="hidden" name="action" value="take_exam">
          <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
          <label>Mode:
            <select name="mode">
              <option value="training">Training (choix du nombre de questions)</option>
              <option value="training_timed">Training (timed) - choisis durée</option>
              <option value="official">Official (90 q, 60 min)</option>
              <?php if (userHasRole('admin')): ?>
                <option value="admin_challenge">Admin challenge (configuré par admin)</option>
              <?php endif; ?>
            </select>
          </label><br>
          <label>Nombre de questions (training / training_timed) : <input type="number" name="nb_questions" min="1"></label><br>
          <label>Durée en minutes (training_timed) : <input type="number" name="duration_minutes" min="1"></label><br>
          <button type="submit">Démarrer</button>
        </form>
        <?php
        break;
    }

    // determine limits according to mode
    if ($mode === 'official') {
        $limit = 90;
        $timeLimit = 3600;
    } elseif ($mode === 'training_timed') {
    $limit = !empty($_GET['nb_questions']) ? (int)$_GET['nb_questions'] : ($exam['nb_questions'] ?? 10);
    $timeLimit = !empty($_GET['duration']) ? (int)$_GET['duration'] : null;
    // validate duration
    if ($timeLimit !== null && $timeLimit < 5) $timeLimit = 5;
    if ($timeLimit !== null && $timeLimit > 86400) $timeLimit = 86400; // arbitrary sensible cap
    } elseif ($mode === 'admin_challenge' && userHasRole('admin')) {
    // admin_challenge: if challenge_id provided, load it
    $challengeId = !empty($_GET['challenge_id']) ? (int)$_GET['challenge_id'] : null;
    $limit = $exam['nb_questions'] ?? 10;
    $timeLimit = null;
    if ($challengeId) {
      $challenge = getAdminChallengeById($challengeId);
      if ($challenge) {
        $limit = (int)$challenge['nb_questions'] > 0 ? (int)$challenge['nb_questions'] : $limit;
        $timeLimit = $challenge['time_limit_seconds'] !== null ? (int)$challenge['time_limit_seconds'] : $timeLimit;
      }
    }
    } else {
        // training
        $limit = !empty($_GET['nb_questions']) ? (int)$_GET['nb_questions'] : ($exam['nb_questions'] ?? 10);
        $timeLimit = null;
    }

  // cap to available questions if exam defines a limit
  if (!empty($exam['nb_questions']) && (int)$exam['nb_questions'] > 0) {
    $limit = min((int)$limit, (int)$exam['nb_questions']);
  }
  $questions = getRandomQuestionsForExam($examId, (int)$limit);
    if (empty($questions)) { echo "<p>Aucune question disponible.</p>"; break; }

    // When a logged-in user starts an exam, link the attempt to their account automatically
    if (isAuthenticated()) {
      $_SESSION['user_identifier'] = currentUser()['username'];
    }

    // store session data for timer and mode
    $_SESSION['current_exam_id'] = $examId;
    $_SESSION['current_question_ids'] = array_column($questions, 'id');
    $_SESSION['exam_start_time'] = date('Y-m-d H:i:s');
    $_SESSION['exam_mode'] = $mode;
    $_SESSION['exam_time_limit'] = $timeLimit; // seconds or null

    ?>
    <h2>Passer: <?php echo h($exam['titre']); ?> (mode: <?php echo h($mode); ?>)</h2>
    <?php if ($timeLimit !== null): ?>
      <p><strong>Temps limite:</strong> <span id="timeLeftDisplay"><?php echo gmdate('H:i:s', intval($timeLimit)); ?></span></p>
    <?php endif; ?>
  <form method="post" action="<?php echo BASE_URL; ?>/?action=submit_exam" id="examForm" onsubmit="return validateExamForm()">
  <input type="hidden" name="forced_submit" id="forced_submit" value="0">
    <?php if (isAuthenticated()): ?>
      <p>Vous êtes connecté en tant que <strong><?php echo h(currentUser()['username']); ?></strong>. Votre tentative sera liée à ce compte.</p>
      <input type="hidden" name="user_identifier" value="<?php echo h(currentUser()['username']); ?>">
    <?php else: ?>
      <label>Votre identifiant (email ou pseudo) :
          <input type="text" name="user_identifier" value="<?php echo isset($_SESSION['user_identifier']) ? h($_SESSION['user_identifier']) : ''; ?>">
      </label>
      <p style="font-size:0.9em;color:#666;">(optionnel — permet d'enregistrer votre historique)</p>
    <?php endif; ?>
    <hr>
    <?php foreach ($questions as $i => $q): 
      $opts = getOptionsForQuestion($q['id']);
      $isMultiple = $q['type'] === 'qcm_multiple';
      $name = 'q_' . $q['id'] . ($isMultiple ? '[]' : '');
      $fieldsetId = 'question_' . $q['id'];
    ?>
    <hr>
      <fieldset id="<?php echo $fieldsetId; ?>" data-question-id="<?php echo $q['id']; ?>"><legend>Question <?php echo $i+1; ?></legend>
        <p><?php echo nl2br(h($q['enonce'])); ?></p>
        <?php foreach ($opts as $opt): ?>
          <label>
            <input type="<?php echo $isMultiple ? 'checkbox' : 'radio'; ?>" name="<?php echo h($name); ?>" value="<?php echo $opt['id']; ?>" class="answer-input" data-question-id="<?php echo $q['id']; ?>">
            <?php echo h($opt['label'] . '. ' . $opt['texte']); ?>
          </label><br>
        <?php endforeach; ?>
        <span class="error-message" id="error_<?php echo $q['id']; ?>" style="color:red;display:none;">Veuillez répondre à cette question.</span>
      </fieldset>
    <?php endforeach; ?>
      <div id="formError" style="color:red;display:none;margin:10px 0;font-weight:bold;"></div>
      <button type="submit">Valider</button>
    </form>
    <?php if ($timeLimit !== null): ?>
    <script>
    // countdown with hh:mm:ss display; on timeout set forced_submit flag and submit
    (function(){
      var timeLeft = <?php echo intval($timeLimit); ?>; // seconds
      var display = document.getElementById('timeLeftDisplay');
      function fmt(s){
        var h = Math.floor(s/3600); var m = Math.floor((s%3600)/60); var sec = s%60;
        return String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(sec).padStart(2,'0');
      }
      if (display) display.textContent = fmt(timeLeft);
      var interval = setInterval(function(){
        timeLeft--; if (display) display.textContent = fmt(Math.max(0, timeLeft));
        if (timeLeft <= 0) {
          clearInterval(interval);
          // mark forced and submit
          var forced = document.getElementById('forced_submit');
          if (forced) forced.value = '1';
          var form = document.getElementById('examForm');
          if (form) { form.submit(); }
        }
      }, 1000);
    })();
    </script>
    <?php endif; ?>
    <script>
    function validateExamForm() {
        var form = document.getElementById('examForm');
        var fieldsets = form.querySelectorAll('fieldset[data-question-id]');
        var hasError = false;
        var unansweredQuestions = [];
        
        // Réinitialiser les messages d'erreur
        document.getElementById('formError').style.display = 'none';
        fieldsets.forEach(function(fieldset) {
            var errorSpan = fieldset.querySelector('.error-message');
            if (errorSpan) errorSpan.style.display = 'none';
        });
        
        // Vérifier chaque question
        fieldsets.forEach(function(fieldset) {
            var questionId = fieldset.getAttribute('data-question-id');
            var inputs = fieldset.querySelectorAll('input[type="radio"], input[type="checkbox"]');
            var answered = false;
            
            inputs.forEach(function(input) {
                if (input.checked) {
                    answered = true;
                }
            });
            
            if (!answered) {
                hasError = true;
                unansweredQuestions.push(questionId);
                var errorSpan = fieldset.querySelector('.error-message');
                if (errorSpan) {
                    errorSpan.style.display = 'inline';
                    fieldset.style.border = '2px solid red';
                }
            } else {
                fieldset.style.border = '';
            }
        });
        
        if (hasError) {
            var errorMsg = 'Veuillez répondre à toutes les questions avant de valider.';
            if (unansweredQuestions.length > 0) {
                errorMsg += ' Questions non répondues : ' + unansweredQuestions.length;
            }
            document.getElementById('formError').textContent = errorMsg;
            document.getElementById('formError').style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return false;
        }
        
        return true;
    }
    </script>
    <?php
    break;

  case 'submit_exam':
    if (!isset($_SESSION['current_exam_id'], $_SESSION['current_question_ids'])) {
        echo "<p>Aucune épreuve en cours.</p>";
        break;
    }

    $examId = (int)$_SESSION['current_exam_id'];
    $questionIds = $_SESSION['current_question_ids'];

  // Validation : vérifier que toutes les questions ont des réponses
  $missingAnswers = [];
    foreach ($questionIds as $qid) {
        $field = 'q_' . $qid;
        $fieldArray = $field . '[]';
        
        // Vérifier d'abord le champ avec [] (pour les checkboxes)
        $selected = $_POST[$fieldArray] ?? null;
        
        // Si pas trouvé, vérifier le champ sans [] (pour les radio buttons)
        if ($selected === null) {
            $selected = $_POST[$field] ?? null;
        }
        
        // Convertir en tableau si nécessaire
        if ($selected === null || $selected === '') {
            $selectedIds = [];
        } elseif (!is_array($selected)) {
            $selectedIds = [(int)$selected];
        } else {
            $selectedIds = array_filter(array_map('intval', $selected));
        }
        
        // Vérifier si au moins une réponse a été sélectionnée
        if (empty($selectedIds)) {
            $missingAnswers[] = $qid;
        }
    }
    
  // Check if this submission was forced by client (timeout)
  $forcedSubmit = !empty($_POST['forced_submit']) && (string)$_POST['forced_submit'] === '1';
  if (!empty($missingAnswers) && !$forcedSubmit) {
    echo "<h2>Erreur de validation</h2>";
    echo "<p style='color:red;font-weight:bold;'>Vous devez répondre à toutes les questions avant de valider l'examen.</p>";
    echo "<p>Nombre de questions non répondues : <strong>" . count($missingAnswers) . "</strong></p>";
    echo '<p><a href="' . BASE_URL . '/?action=take_exam&exam_id=' . $examId . '">← Retour à l\'examen</a></p>';
    break;
  }

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

  // Timer & mode metadata
  $mode = $_SESSION['exam_mode'] ?? 'training';
  $timeLimit = $_SESSION['exam_time_limit'] ?? null;
  $started = strtotime($dateStart);
  $ended = strtotime($dateEnd);
  $timeSpent = max(0, $ended - $started);
  $isForced = false;
  if ($timeLimit !== null && $timeSpent >= (int)$timeLimit) {
    $isForced = true;
  }

  $attemptId = saveAttempt($examId, $userIdentifier, $dateStart, $dateEnd, $scoreSum, $totalPoints, $answersForSave, $mode, $timeLimit !== null ? (int)$timeLimit : null, (int)$timeSpent, $isForced);

  $_SESSION['last_attempt_id'] = $attemptId;

  echo "<h2>Résultat</h2>";
  echo "<p>Score : " . round($scoreSum, 2) . " / " . round($totalPoints, 2) . "</p>";
  echo '<p><a href="' . BASE_URL . '/?action=show_correction&attempt_id=' . $attemptId . '">Voir la correction détaillée</a></p>';
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
    // require login to view history
    require_auth();
  // If the current user is not admin, force the UI to the current user's username
  $ui = null;
  if (userHasRole('admin')) {
    $ui = $_SESSION['user_identifier'] ?? null;
    if (!empty($_GET['user_identifier'])) $ui = trim($_GET['user_identifier']);
  } else {
    $cu = currentUser();
    $ui = $cu['username'];
  }

  echo "<h2>Historique personnel</h2>";
  ?>
  <form method="get" action="<?php echo BASE_URL; ?>/">
    <input type="hidden" name="action" value="user_history">
    <?php if (userHasRole('admin')): ?>
      <label>Identifiant : <input type="text" name="user_identifier" value="<?php echo h($ui ?? ''); ?>"></label>
      <button type="submit">Voir</button>
    <?php else: ?>
      <p>Affichage de l'historique pour : <strong><?php echo h($ui); ?></strong></p>
    <?php endif; ?>
  </form>
  <?php
  if (empty($ui)) {
    echo "<p>Aucun identifiant sélectionné.</p>";
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
  require_auth();
  // Non-admins may only view their own stats
  if (userHasRole('admin')) {
    $ui = isset($_GET['user_identifier']) ? trim($_GET['user_identifier']) : ($_SESSION['user_identifier'] ?? null);
  } else {
    $cu = currentUser();
    $ui = $cu['username'];
  }
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
    echo "</ul>";
    break;

  default:
    echo "<p>Action inconnue</p>";
    break;
}
?>
</body>
</html>

