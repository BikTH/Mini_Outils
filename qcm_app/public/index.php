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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QCM App - Plateforme d'examens en ligne</title>
  <meta name="description" content="Plateforme moderne pour passer des examens QCM en ligne avec correction automatique et statistiques détaillées">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
  <script src="<?php echo BASE_URL; ?>/assets/js/exam.js" defer></script>
</head>
<body>
  <header class="app-header">
    <div class="header-content">
      <h1 class="app-title">QCM App</h1>
      <nav class="app-nav">
        <?php if (isAuthenticated()): ?>
          <span class="user-info">Bienvenue <?php echo h(currentUser()['display_name'] ?? currentUser()['username']); ?></span>
          <a href="<?php echo BASE_URL; ?>/?action=home" class="nav-link">Accueil</a>
          <a href="<?php echo BASE_URL; ?>/?action=user_history" class="nav-link">Mon historique</a>
          <?php if (userHasRole('admin')): ?>
            <a href="<?php echo BASE_URL; ?>/?action=admin_exams" class="nav-link">Examens</a>
            <a href="<?php echo BASE_URL; ?>/?action=admin_users" class="nav-link">Utilisateurs</a>
            <a href="<?php echo BASE_URL; ?>/?action=admin_challenges" class="nav-link">Challenges</a>
            <a href="<?php echo BASE_URL; ?>/?action=admin_user_overview" class="nav-link">Stats</a>
          <?php endif; ?>
          <a href="<?php echo BASE_URL; ?>/?action=logout" class="nav-link">Déconnexion</a>
        <?php else: ?>
          <a href="<?php echo BASE_URL; ?>/?action=login" class="nav-link">Se connecter</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <main class="app-main">
    <div class="content-wrapper">

<?php
switch ($action) {
  case 'login':
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $u = trim($_POST['username'] ?? '');
      $p = $_POST['password'] ?? '';
      if ($u !== '' && login($u, $p)) {
        header('Location: ' . BASE_URL . '/'); exit;
      } else {
        echo '<div class="alert alert-error fade-in">Identifiants invalides. Veuillez réessayer.</div>';
      }
    }
    ?>
    <div class="card fade-in" style="max-width: 500px; margin: 3rem auto;">
      <div class="card-header text-center">
        <h2 class="card-title">Connexion</h2>
        <p class="card-subtitle">Connectez-vous pour accéder à vos examens</p>
      </div>
      <div class="card-body">
        <form method="post" action="<?php echo BASE_URL; ?>/?action=login">
          <div class="form-group">
            <label class="form-label required" for="username">Nom d'utilisateur</label>
            <input type="text" id="username" name="username" class="form-input" required autofocus placeholder="Entrez votre nom d'utilisateur">
          </div>
          <div class="form-group">
            <label class="form-label required" for="password">Mot de passe</label>
            <input type="password" id="password" name="password" class="form-input" required placeholder="Entrez votre mot de passe">
          </div>
          <button type="submit" class="btn btn-primary btn-lg">Se connecter</button>
        </form>
      </div>
    </div>
    <?php
    break;

  case 'logout':
    logout();
    header('Location: ' . BASE_URL . '/'); exit;
    break;

  case 'home':
    $exams = getAllExams();
    ?>
    <div class="fade-in">
      <h2>📚 Examens disponibles</h2>
      <?php if (empty($exams)): ?>
        <div class="card text-center">
          <p class="text-gray">Aucun examen disponible pour le moment.</p>
          <?php if (userHasRole('admin')): ?>
            <a href="<?php echo BASE_URL; ?>/?action=admin_exams" class="btn btn-primary">Créer un examen</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <?php foreach ($exams as $exam): ?>
          <div class="exam-card slide-in-right">
            <h3 class="exam-title"><?php echo h($exam['titre']); ?></h3>
            <?php if (!empty($exam['description'])): ?>
              <p class="text-gray"><?php echo h($exam['description']); ?></p>
            <?php endif; ?>
            <div class="exam-meta">
              <?php if ($exam['nb_questions']): ?>
                <span class="exam-meta-item">📝 <?php echo (int)$exam['nb_questions']; ?> questions</span>
              <?php endif; ?>
              <span class="exam-meta-item">📅 Créé le <?php echo date('d/m/Y', strtotime($exam['date_creation'])); ?></span>
            </div>
            <a href="<?php echo BASE_URL; ?>/?action=take_exam&exam_id=<?php echo $exam['id']; ?>" class="btn btn-primary">Passer l'examen</a>

            <?php
            $chals = getAdminChallengesForExam((int)$exam['id']);
            if (!empty($chals)):
            ?>
              <div class="mt-lg">
                <h4 style="font-size: 1rem; margin-bottom: 0.5rem; color: var(--color-info);">🏆 Challenges disponibles</h4>
                <ul class="challenge-list">
                  <?php foreach ($chals as $c): ?>
                    <li class="challenge-item">
                      <div class="challenge-info">
                        <div class="challenge-title"><?php echo h($c['title']); ?></div>
                        <div class="challenge-meta">
                          <?php echo (int)$c['nb_questions']; ?> questions
                          <?php if ($c['time_limit_seconds']): ?>
                            • ⏱️ <?php echo gmdate('H:i:s', (int)$c['time_limit_seconds']); ?>
                          <?php endif; ?>
                        </div>
                      </div>
                      <div class="challenge-actions">
                        <a href="<?php echo BASE_URL; ?>/?action=take_exam&exam_id=<?php echo $exam['id']; ?>&mode=admin_challenge&challenge_id=<?php echo (int)$c['id']; ?>" class="btn btn-success btn-sm">Participer</a>
                        <a href="<?php echo BASE_URL; ?>/?action=admin_challenge_leaderboard&challenge_id=<?php echo (int)$c['id']; ?>" class="btn btn-secondary btn-sm">Classement</a>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php
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
      $rank = 1;
      foreach ($leaders as $l) {
        $pct = ($l['total_points'] > 0) ? round((($l['score_auto'] / $l['total_points']) * 100), 2) : 0;
        $timeSpent = isset($l['time_spent_seconds']) ? (int)$l['time_spent_seconds'] : 0;
        $timeDisplay = gmdate('H:i:s', $timeSpent);
        $forcedLabel = !empty($l['is_forced_submit']) ? ' (forced)' : '';
        $userLabel = h($l['user_identifier'] ?? 'anon');
        echo "<li>" . $rank . ". " . $userLabel . " — " . round($l['score_auto'],2) . " / " . round($l['total_points'],2) . " (" . $pct . "%) — temps: " . h($timeDisplay) . $forcedLabel . " — " . h($l['date_end']) . "</li>";
        $rank++;
      }
      echo "</ol>";
    }
    break;

  case 'admin_user_overview':
    require_role('admin');
    $users = listUsers();
    echo "<h2>Vue d'ensemble des utilisateurs</h2>";
    if (empty($users)) {
      echo "<p>Aucun utilisateur.</p>";
      break;
    }
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'><tr><th>Utilisateur</th><th>Nom affiché</th><th>Total tentatives</th><th>Tendance</th><th>Actions</th></tr>";
    foreach ($users as $u) {
      $uname = $u['username'];
      $attempts = getAttemptsForUser($uname);
      $stats = computeExamStatistics($attempts);
      $trendText = [ 'improving' => '📈 En amélioration', 'declining' => '📉 En baisse', 'stable' => '➡️ Stable' ];
      echo "<tr>";
      echo "<td>" . h($uname) . "</td>";
      echo "<td>" . h($u['display_name'] ?? '') . "</td>";
      echo "<td style='text-align:right;'>" . (int)$stats['total_attempts'] . "</td>";
      echo "<td>" . ($trendText[$stats['trend']] ?? h($stats['trend'])) . "</td>";
      echo "<td><a href='" . BASE_URL . "/?action=user_history&user_identifier=" . urlencode($uname) . "'>Détails par examen</a> | <a href='" . BASE_URL . "/?action=admin_user_details&user_identifier=" . urlencode($uname) . "'>Détails par mode</a></td>";
      echo "</tr>";
    }
    echo "</table>";
    break;

  case 'admin_user_details':
    require_role('admin');
    $ui = isset($_GET['user_identifier']) ? trim($_GET['user_identifier']) : null;
    if (empty($ui)) { echo "<p>Utilisateur non spécifié.</p>"; break; }
    $attempts = getAttemptsForUser($ui);
    if (empty($attempts)) { echo "<p>Aucune tentative pour " . h($ui) . "</p>"; break; }
    // group by mode across all exams
    $byMode = [];
    foreach ($attempts as $a) {
      $m = $a['mode'] ?? 'training';
      if (!isset($byMode[$m])) $byMode[$m] = [];
      $byMode[$m][] = $a;
    }
    echo "<h2>Détails par mode pour " . h($ui) . "</h2>";
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'><tr><th>Mode</th><th>Nb tentatives</th><th>Moyenne</th><th>Meilleur</th><th>Dernière</th></tr>";
    foreach ($byMode as $mkey => $matts) {
      $s = computeExamStatistics($matts);
      echo "<tr>";
      echo "<td>" . h($mkey) . "</td>";
      echo "<td style='text-align:right;'>" . (int)$s['total_attempts'] . "</td>";
      echo "<td>" . h($s['average_score']) . "%</td>";
      echo "<td>" . h($s['best_score']) . "%</td>";
      echo "<td>" . h($s['last_date'] ?? '') . "</td>";
      echo "</tr>";
    }
    echo "</table>";
    echo '<p><a href="' . BASE_URL . '/?action=admin_user_overview">← Retour</a></p>';
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
            <select name="mode" id="modeSelect">
              <option value="training">Training (choix du nombre de questions)</option>
              <option value="training_timed">Training (timed) - choisis durée</option>
              <option value="official">Official (90 q, 60 min)</option>
              <option value="admin_challenge">Admin challenge (configuré)</option>
            </select>
          </label><br>
          <div id="mode_params">
            <label>Nombre de questions (training / training_timed) : <input type="number" name="nb_questions" min="1"></label><br>
            <label id="durationBlock">Durée en minutes (training_timed) : <input type="number" name="duration_minutes" min="1"></label><br>
            <label id="challengeBlock" style="display:none;">Choisir un challenge: 
              <select name="challenge_id" id="challengeSelect">
                <option value="">-- aucun --</option>
                <?php foreach (getAdminChallengesForExam($exam['id']) as $chc): ?>
                  <option value="<?php echo (int)$chc['id']; ?>"><?php echo h($chc['title']); ?> — <?php echo (int)$chc['nb_questions']; ?> q<?php echo $chc['time_limit_seconds'] ? ' — ' . (int)$chc['time_limit_seconds'] . 's' : ''; ?></option>
                <?php endforeach; ?>
              </select>
            </label><br>
          </div>
          <script>
            (function(){
              var modeSel = document.getElementById('modeSelect');
              var durationBlock = document.getElementById('durationBlock');
              var challengeBlock = document.getElementById('challengeBlock');
              function update() {
                var v = modeSel.value;
                durationBlock.style.display = (v === 'training_timed') ? 'block' : 'none';
                challengeBlock.style.display = (v === 'admin_challenge') ? 'block' : 'none';
              }
              modeSel.addEventListener('change', update);
              update();
            })();
          </script>
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
  // duration_minutes provided by the form -> convert to seconds
  $timeLimit = !empty($_GET['duration_minutes']) ? (int)$_GET['duration_minutes'] * 60 : null;
  // validate duration
  if ($timeLimit !== null && $timeLimit < 5) $timeLimit = 5;
  if ($timeLimit !== null && $timeLimit > 86400) $timeLimit = 86400; // arbitrary sensible cap
  } elseif ($mode === 'admin_challenge') {
  // admin_challenge: if challenge_id provided, load it (participants can start configured challenges)
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
    // store admin challenge id when applicable (ensure challenge exists and belongs to this exam)
    if (!empty($challengeId) && $mode === 'admin_challenge') {
      $challenge = getAdminChallengeById((int)$challengeId);
      if ($challenge && (int)$challenge['exam_id'] === (int)$examId) {
        $_SESSION['admin_challenge_id'] = $challengeId;
      } else {
        unset($_SESSION['admin_challenge_id']);
      }
    } else {
      unset($_SESSION['admin_challenge_id']);
    }
    $_SESSION['exam_time_limit'] = $timeLimit; // seconds or null

    ?>
    <div class="card fade-in">
      <div class="card-header">
        <h2 class="card-title"><?php echo h($exam['titre']); ?></h2>
        <p class="card-subtitle">Mode: <span class="badge badge-primary"><?php echo h($mode); ?></span></p>
      </div>
    </div>

    <?php if ($timeLimit !== null): ?>
      <div class="timer-display" id="timerContainer">
        <div style="font-size: 0.875rem; margin-bottom: 0.25rem;">⏱️ Temps restant</div>
        <div id="timeLeftDisplay" data-time-limit="<?php echo intval($timeLimit); ?>">
          <?php echo gmdate('H:i:s', intval($timeLimit)); ?>
        </div>
      </div>
    <?php endif; ?>

  <form method="post" action="<?php echo BASE_URL; ?>/?action=submit_exam" id="examForm">
  <input type="hidden" name="forced_submit" id="forced_submit" value="0">
    <?php if (isAuthenticated()): ?>
      <input type="hidden" name="user_identifier" value="<?php echo h(currentUser()['username']); ?>">
    <?php else: ?>
      <div class="card">
        <div class="form-group">
          <label class="form-label" for="user_identifier">Votre identifiant (email ou pseudo)</label>
          <input type="text" id="user_identifier" name="user_identifier" class="form-input" value="<?php echo isset($_SESSION['user_identifier']) ? h($_SESSION['user_identifier']) : ''; ?>" placeholder="Optionnel - pour enregistrer votre historique">
          <span class="form-hint">Permet de sauvegarder vos tentatives et consulter votre historique</span>
        </div>
      </div>
    <?php endif; ?>
    <?php foreach ($questions as $i => $q):
      $opts = getOptionsForQuestion($q['id']);
      $isMultiple = $q['type'] === 'qcm_multiple';
      $name = 'q_' . $q['id'] . ($isMultiple ? '[]' : '');
    ?>
      <fieldset class="question-fieldset fade-in" data-question-id="<?php echo $q['id']; ?>" style="animation-delay: <?php echo min($i * 50, 500); ?>ms;">
        <legend class="question-legend">
          Question <?php echo $i+1; ?> / <?php echo count($questions); ?>
          <?php if ($isMultiple): ?>
            <span class="badge badge-warning" style="margin-left: 0.5rem;">Choix multiple</span>
          <?php endif; ?>
        </legend>

        <p class="question-text"><?php echo nl2br(h($q['enonce'])); ?></p>

        <div class="options-list">
          <?php foreach ($opts as $opt): ?>
            <label class="option-label">
              <input type="<?php echo $isMultiple ? 'checkbox' : 'radio'; ?>"
                     name="<?php echo h($name); ?>"
                     value="<?php echo $opt['id']; ?>"
                     class="answer-input"
                     data-question-id="<?php echo $q['id']; ?>">
              <span class="option-text"><?php echo h($opt['label'] . '. ' . $opt['texte']); ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <span class="error-message hidden" id="error_<?php echo $q['id']; ?>">
          ⚠️ Veuillez répondre à cette question.
        </span>
      </fieldset>
    <?php endforeach; ?>

      <div id="formError" class="hidden"></div>

      <div class="card" style="margin-top: var(--spacing-xl); text-align: center;">
        <p class="text-gray" style="margin-bottom: var(--spacing-md); font-size: var(--font-size-sm);">
          💡 <strong>Astuce:</strong> Alt+S pour soumettre • Alt+N pour question suivante non répondue
        </p>
        <button type="submit" class="btn btn-success btn-lg">
          ✅ Valider l'examen
        </button>
      </div>
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

  $adminChallengeId = $_SESSION['admin_challenge_id'] ?? null;
  $attemptId = saveAttempt($examId, $userIdentifier, $dateStart, $dateEnd, $scoreSum, $totalPoints, $answersForSave, $mode, $timeLimit !== null ? (int)$timeLimit : null, (int)$timeSpent, $isForced, $adminChallengeId);

  $_SESSION['last_attempt_id'] = $attemptId;

  $percentage = $totalPoints > 0 ? round(($scoreSum / $totalPoints) * 100, 2) : 0;
  $exam = getExamById($examId);
  ?>
  <div class="card fade-in text-center" style="max-width: 600px; margin: 3rem auto;">
    <div class="card-header">
      <h2 class="card-title">✅ Examen terminé !</h2>
      <p class="card-subtitle"><?php echo h($exam['titre']); ?></p>
    </div>
    <div class="card-body">
      <?php if ($isForced): ?>
        <div class="alert alert-warning" style="margin-bottom: 1.5rem;">
          ⏰ Temps écoulé - Soumission automatique
        </div>
      <?php endif; ?>

      <div class="stats-grid" style="margin: 2rem 0;">
        <div class="stat-card">
          <div class="stat-value"><?php echo round($scoreSum, 2); ?></div>
          <div class="stat-label">Points obtenus</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?php echo $percentage; ?>%</div>
          <div class="stat-label">Score</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?php echo (int)$totalPoints; ?></div>
          <div class="stat-label">Total points</div>
        </div>
      </div>

      <div class="progress-bar-container" style="margin: 2rem 0;">
        <div class="progress-bar <?php echo $percentage >= 80 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger'); ?>" style="width: <?php echo $percentage; ?>%;">
          <?php echo $percentage; ?>%
        </div>
      </div>

      <p class="text-gray mb-lg">
        <?php
        if ($percentage >= 80) {
          echo "🎉 Excellent travail ! Vous maîtrisez bien le sujet.";
        } elseif ($percentage >= 50) {
          echo "👍 Bon travail ! Quelques révisions et ce sera parfait.";
        } else {
          echo "💪 Continuez vos efforts ! La correction vous aidera à progresser.";
        }
        ?>
      </p>
    </div>
    <div class="card-footer" style="display: block;">
      <a href="<?php echo BASE_URL; ?>/?action=show_correction&attempt_id=<?php echo $attemptId; ?>" class="btn btn-primary btn-lg">
        📋 Voir la correction détaillée
      </a>
      <a href="<?php echo BASE_URL; ?>/?action=home" class="btn btn-secondary btn-lg" style="margin-top: 0.5rem;">
        🏠 Retour à l'accueil
      </a>
    </div>
  </div>
  <?php
    break;

  case 'show_correction':
    // Support pour attempt_id (nouveau système)
    $attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : null;

    if ($attemptId) {
        $attempt = getAttemptById($attemptId);
        if (!$attempt) {
            echo '<div class="card"><p class="alert alert-error">Tentative introuvable.</p></div>';
            break;
        }

        $exam = getExamById($attempt['exam_id']);
        $percentage = $attempt['total_points'] > 0 ? round(($attempt['score_auto'] / $attempt['total_points']) * 100, 2) : 0;
        ?>
        <div class="card fade-in">
          <div class="card-header">
            <h2 class="card-title">📝 Correction détaillée</h2>
            <p class="card-subtitle"><?php echo h($exam['titre']); ?></p>
          </div>
          <div class="card-body">
            <div class="stats-grid" style="margin-bottom: 2rem;">
              <div class="stat-card">
                <div class="stat-value text-<?php echo $percentage >= 80 ? 'success' : ($percentage >= 50 ? 'warning' : 'error'); ?>">
                  <?php echo $percentage; ?>%
                </div>
                <div class="stat-label">Score final</div>
              </div>
              <div class="stat-card">
                <div class="stat-value"><?php echo round($attempt['score_auto'], 2); ?></div>
                <div class="stat-label">Points obtenus</div>
              </div>
              <div class="stat-card">
                <div class="stat-value"><?php echo date('d/m/Y H:i', strtotime($attempt['date_end'])); ?></div>
                <div class="stat-label">Date</div>
              </div>
            </div>
          </div>
        </div>

        <?php
        $answersMap = [];
        foreach ($attempt['answers'] as $a) {
            $answersMap[$a['question_id']] = $a;
        }

        $pdo = getPDO();
        $questionNum = 1;
        foreach ($answersMap as $qid => $ansrow) {
            $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = :id");
            $stmt->execute([':id' => $qid]);
            $q = $stmt->fetch();
            if (!$q) continue;

            $questionScore = (float)$ansrow['partial_score'];
            $isFullCorrect = $questionScore >= 0.99;
            ?>
            <div class="correction-item fade-in" style="animation-delay: <?php echo min($questionNum * 30, 500); ?>ms;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin: 0; color: var(--color-gray-900);">Question <?php echo $questionNum; ?></h3>
                <div>
                  <?php if ($isFullCorrect): ?>
                    <span class="badge badge-success" style="font-size: 1rem;">✓ Correct</span>
                  <?php elseif ($questionScore > 0): ?>
                    <span class="badge badge-warning" style="font-size: 1rem;">⚠ Partiel</span>
                  <?php else: ?>
                    <span class="badge badge-error" style="font-size: 1rem;">✗ Incorrect</span>
                  <?php endif; ?>
                  <span class="badge badge-primary" style="margin-left: 0.5rem;">
                    <?php echo round($questionScore, 2); ?> / 1 point
                  </span>
                </div>
              </div>

              <p class="question-text" style="background-color: var(--color-gray-50); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem;">
                <?php echo nl2br(h($q['enonce'])); ?>
              </p>

              <?php
              $opts = getOptionsForQuestion($qid);
              $selected = json_decode($ansrow['selected_option_ids'] ?? '[]', true);
              if (!is_array($selected)) $selected = [];

              foreach ($opts as $opt):
                  $isCorrect = (int)$opt['is_correct'] === 1;
                  $userChose = in_array($opt['id'], $selected, true);

                  $classes = ['correction-option'];
                  if ($isCorrect) $classes[] = 'correct';
                  if ($userChose && !$isCorrect) $classes[] = 'incorrect';
                  if ($userChose) $classes[] = 'user-choice';
              ?>
                <div class="<?php echo implode(' ', $classes); ?>">
                  <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                    <div style="min-width: 80px;">
                      <?php if ($isCorrect): ?>
                        <span class="correction-label" style="background-color: var(--color-success); color: white;">✓ Correcte</span>
                      <?php endif; ?>
                      <?php if ($userChose): ?>
                        <span class="correction-label" style="background-color: var(--color-primary); color: white; margin-top: 0.25rem;">Votre choix</span>
                      <?php endif; ?>
                    </div>
                    <div style="flex: 1;">
                      <strong><?php echo h($opt['label']); ?>.</strong> <?php echo h($opt['texte']); ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>

              <?php if (!empty($q['explication'])): ?>
                <div class="correction-explanation">
                  <strong style="display: block; margin-bottom: 0.5rem; color: var(--color-info);">💡 Explication :</strong>
                  <p style="margin: 0;"><?php echo nl2br(h($q['explication'])); ?></p>
                </div>
              <?php endif; ?>
            </div>
            <?php
            $questionNum++;
        }
        ?>

        <div class="card" style="text-align: center; margin-top: 2rem;">
          <a href="<?php echo BASE_URL; ?>/?action=user_history" class="btn btn-primary btn-lg">
            📊 Voir mon historique
          </a>
          <a href="<?php echo BASE_URL; ?>/?action=home" class="btn btn-secondary btn-lg" style="margin-top: 0.5rem;">
            🏠 Retour à l'accueil
          </a>
        </div>
        <?php
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

        // Breakdown by mode for this exam
        $byMode = [];
        foreach ($examAttempts as $a) {
          $m = $a['mode'] ?? 'training';
          if (!isset($byMode[$m])) $byMode[$m] = [];
          $byMode[$m][] = $a;
        }
        echo "<ul>";
        foreach ($byMode as $mkey => $mattempts) {
          $mstats = computeExamStatistics($mattempts);
          echo "<li>Mode: <strong>" . h($mkey) . "</strong> — " . (int)$mstats['total_attempts'] . " tentative(s), moyenne: " . h($mstats['average_score']) . "% - <a href=\"" . BASE_URL . "/?action=user_exam_stats&user_identifier=" . urlencode($ui) . "&exam_id=" . $examId . "&mode=" . urlencode($mkey) . "\">Voir par mode</a></li>";
        }
        echo "</ul>";

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
    // Filter by mode if requested (optional)
    $filterMode = isset($_GET['mode']) ? trim($_GET['mode']) : null;
    if ($filterMode !== null && $filterMode !== '') {
      $attempts = array_values(array_filter($attempts, function($a) use ($filterMode) { return ($a['mode'] ?? '') === $filterMode; }));
    }
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
    echo '<div class="card"><p class="alert alert-error">Action inconnue</p></div>';
    break;
}
?>
    </div>
  </main>
</body>
</html>

