<?php
// app/services/exam_service.php (moved from app/exam_service.php)
require_once __DIR__ . '/../core/database.php';

function getAllExams(): array
{
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT * FROM exams ORDER BY date_creation DESC");
    return $stmt->fetchAll();
}

function createExam(string $titre, ?string $description, ?int $nbQuestions): bool
{
    $pdo = getPDO();
    $sql = "INSERT INTO exams (titre, description, nb_questions) VALUES (:titre, :description, :nb_questions)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':titre'        => $titre,
        ':description'  => $description,
        ':nb_questions' => $nbQuestions,
    ]);
}

function getExamById(int $examId): ?array
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = :id");
    $stmt->execute([':id' => $examId]);
    $exam = $stmt->fetch();
    return $exam ?: null;
}

function getRandomQuestionsForExam(int $examId, int $limit): array
{
    $pdo = getPDO();
    $sql = "SELECT * FROM questions 
            WHERE exam_id = :exam_id AND actif = 1 AND est_notee = 1
            ORDER BY RAND()
            LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getOptionsForQuestion(int $questionId): array
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM options WHERE question_id = :qid ORDER BY label ASC");
    $stmt->execute([':qid' => $questionId]);
    return $stmt->fetchAll();
}

function isQuestionCorrect(int $questionId, array $selectedOptionIds): bool
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id FROM options WHERE question_id = :qid AND is_correct = 1");
    $stmt->execute([':qid' => $questionId]);
    $correctIds = array_column($stmt->fetchAll(), 'id');

    sort($correctIds);
    $selected = $selectedOptionIds;
    sort($selected);

    return $correctIds === $selected;
}

// Récupère les IDs d'options correctes pour une question
function getCorrectOptionIds(int $questionId): array
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id FROM options WHERE question_id = :qid AND is_correct = 1");
    $stmt->execute([':qid' => $questionId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

// Scoring partiel : retourne [partialScore (0..1), isFullCorrect (bool)]
function computePartialScore(int $questionId, array $selectedOptionIds): array
{
    $selected = array_map('intval', array_values(array_filter($selectedOptionIds, function($v){ return $v !== '' && $v !== null; })));
    $correctIds = getCorrectOptionIds($questionId);

    if (count($correctIds) === 1) {
        $isFull = (count($selected) === 1 && in_array($correctIds[0], $selected, true));
        return [$isFull ? 1.0 : 0.0, $isFull];
    }

    $N = max(1, count($correctIds));
    $tp = count(array_intersect($selected, $correctIds));
    $fp = count(array_diff($selected, $correctIds));

    $raw = ($tp - $fp) / $N;
    if ($raw < 0) $raw = 0;
    if ($raw > 1) $raw = 1;

    $isFull = ($tp === $N && $fp === 0);
    return [(float)$raw, (bool)$isFull];
}

// Sauvegarde d'une tentative et de ses réponses (returns attempt_id)
function saveAttempt(int $examId, ?string $userIdentifier, string $dateStart, string $dateEnd, float $scoreAuto, float $totalPoints, array $answers, string $mode = 'training', ?int $timeLimitSeconds = null, int $timeSpentSeconds = 0, bool $isForcedSubmit = false, ?int $adminChallengeId = null): int
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("INSERT INTO attempts (exam_id, user_identifier, date_start, date_end, score_auto, total_points, mode, time_limit_seconds, time_spent_seconds, is_forced_submit, admin_challenge_id) VALUES (:exam_id, :user_identifier, :date_start, :date_end, :score_auto, :total_points, :mode, :time_limit_seconds, :time_spent_seconds, :is_forced_submit, :admin_challenge_id)");
    $stmt->execute([
        ':exam_id' => $examId,
        ':user_identifier' => $userIdentifier,
        ':date_start' => $dateStart,
        ':date_end' => $dateEnd,
        ':score_auto' => $scoreAuto,
        ':total_points' => $totalPoints,
        ':mode' => $mode,
        ':time_limit_seconds' => $timeLimitSeconds,
        ':time_spent_seconds' => $timeSpentSeconds,
        ':is_forced_submit' => $isForcedSubmit ? 1 : 0
        ,':admin_challenge_id' => $adminChallengeId
    ]);
    $attemptId = (int)$pdo->lastInsertId();

    $stmtAns = $pdo->prepare("INSERT INTO attempt_answers (attempt_id, question_id, selected_option_ids, is_full_correct, partial_score) VALUES (:attempt_id, :question_id, :selected_option_ids, :is_full_correct, :partial_score)");
    foreach ($answers as $qid => $ansData) {
        $selectedIds = $ansData['selected'] ?? [];
        $partial = $ansData['partial'] ?? 0.0;
        $isFull = !empty($ansData['is_full']) ? 1 : 0;
        $stmtAns->execute([
            ':attempt_id' => $attemptId,
            ':question_id' => $qid,
            ':selected_option_ids' => json_encode(array_values($selectedIds)),
            ':is_full_correct' => $isFull,
            ':partial_score' => $partial
        ]);
    }
    return $attemptId;
}

// Récupérer une tentative et ses réponses
function getAttemptById(int $attemptId): ?array
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM attempts WHERE id = :id");
    $stmt->execute([':id' => $attemptId]);
    $attempt = $stmt->fetch();
    if (!$attempt) return null;
    $stmt2 = $pdo->prepare("SELECT * FROM attempt_answers WHERE attempt_id = :aid");
    $stmt2->execute([':aid' => $attemptId]);
    $answers = $stmt2->fetchAll();
    $attempt['answers'] = $answers;
    return $attempt;
}

// Récupérer tentatives par examen
function getAttemptsForExam(int $examId): array
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM attempts WHERE exam_id = :exam ORDER BY created_at DESC");
    $stmt->execute([':exam' => $examId]);
    return $stmt->fetchAll();
}

// Récupérer tentatives par user_identifier
function getAttemptsForUser(?string $userIdentifier): array
{
    if ($userIdentifier === null || $userIdentifier === '') return [];
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM attempts WHERE user_identifier = :ui ORDER BY created_at DESC");
    $stmt->execute([':ui' => $userIdentifier]);
    return $stmt->fetchAll();
}

// Récupérer tentatives d'un utilisateur pour un examen spécifique
function getAttemptsForUserAndExam(?string $userIdentifier, int $examId): array
{
    if ($userIdentifier === null || $userIdentifier === '') return [];
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM attempts WHERE user_identifier = :ui AND exam_id = :exam_id ORDER BY created_at ASC");
    $stmt->execute([
        ':ui' => $userIdentifier,
        ':exam_id' => $examId
    ]);
    return $stmt->fetchAll();
}

// Get admin_challenge by id
function getAdminChallengeById(int $challengeId): ?array
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM admin_challenges WHERE id = :id");
    $stmt->execute([':id' => $challengeId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// Get all admin challenges for an exam
function getAdminChallengesForExam(int $examId): array
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM admin_challenges WHERE exam_id = :exam_id ORDER BY created_at DESC");
    $stmt->execute([':exam_id' => $examId]);
    return $stmt->fetchAll();
}

// Create a new admin challenge
function createAdminChallenge(int $examId, string $title, int $nbQuestions, ?int $timeLimitSeconds = null, ?string $createdBy = null): int
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("INSERT INTO admin_challenges (exam_id, title, nb_questions, time_limit_seconds, created_by) VALUES (:exam_id, :title, :nb_questions, :time_limit_seconds, :created_by)");
    $stmt->execute([
        ':exam_id' => $examId,
        ':title' => $title,
        ':nb_questions' => $nbQuestions,
        ':time_limit_seconds' => $timeLimitSeconds,
        ':created_by' => $createdBy
    ]);
    return (int)$pdo->lastInsertId();
}

// Update an admin challenge
function updateAdminChallenge(int $id, string $title, int $nbQuestions, ?int $timeLimitSeconds = null): bool
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("UPDATE admin_challenges SET title = :title, nb_questions = :nb_questions, time_limit_seconds = :time_limit_seconds WHERE id = :id");
    return $stmt->execute([
        ':title' => $title,
        ':nb_questions' => $nbQuestions,
        ':time_limit_seconds' => $timeLimitSeconds,
        ':id' => $id
    ]);
}

// Delete an admin challenge
function deleteAdminChallenge(int $id): bool
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("DELETE FROM admin_challenges WHERE id = :id");
    return $stmt->execute([':id' => $id]);
}

// Get leaderboard (top N) for a given admin challenge
function getLeaderboardForAdminChallenge(int $challengeId, int $limit = 10): array
{
    $challenge = getAdminChallengeById($challengeId);
    if (!$challenge) return [];
    $pdo = getPDO();
    // Heuristic: select attempts for same exam, mode = 'admin_challenge' and matching total_points to challenge.nb_questions
    $sql = "SELECT * FROM attempts WHERE exam_id = :exam_id AND mode = 'admin_challenge' AND total_points = :nb_questions ORDER BY score_auto DESC, date_end ASC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':exam_id', (int)$challenge['exam_id'], PDO::PARAM_INT);
    $stmt->bindValue(':nb_questions', (int)$challenge['nb_questions'], PDO::PARAM_INT);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Calculer les statistiques pour un examen et un utilisateur
function computeExamStatistics(array $attempts): array
{
    if (empty($attempts)) {
        return [
            'total_attempts' => 0,
            'average_score' => 0,
            'best_score' => 0,
            'worst_score' => 0,
            'last_score' => 0,
            'first_score' => 0,
            'improvement' => 0,
            'trend' => 'none',
            'scores' => []
        ];
    }

    $scores = [];
    $totalPoints = 0;
    $bestScore = 0;
    $worstScore = 100;
    $firstAttempt = null;
    $lastAttempt = null;

    foreach ($attempts as $attempt) {
        $score = (float)$attempt['score_auto'];
        $maxScore = (float)$attempt['total_points'];
        $percentage = $maxScore > 0 ? ($score / $maxScore) * 100 : 0;
        
        $scores[] = [
            'score' => $score,
            'max' => $maxScore,
            'percentage' => round($percentage, 2),
            'date' => $attempt['date_end'],
            'attempt_id' => $attempt['id']
        ];
        
        $totalPoints += $percentage;
        
        if ($percentage > $bestScore) $bestScore = $percentage;
        if ($percentage < $worstScore) $worstScore = $percentage;
        
        if ($firstAttempt === null) $firstAttempt = $attempt;
        $lastAttempt = $attempt;
    }

    $averageScore = count($attempts) > 0 ? $totalPoints / count($attempts) : 0;
    
    // Calculer l'amélioration
    $firstScore = 0;
    $lastScore = 0;
    if ($firstAttempt && $lastAttempt) {
        $firstMax = (float)$firstAttempt['total_points'];
        $firstScore = $firstMax > 0 ? ((float)$firstAttempt['score_auto'] / $firstMax) * 100 : 0;
        $lastMax = (float)$lastAttempt['total_points'];
        $lastScore = $lastMax > 0 ? ((float)$lastAttempt['score_auto'] / $lastMax) * 100 : 0;
    }
    
    $improvement = $lastScore - $firstScore;
    
    // Déterminer la tendance
    $trend = 'stable';
    if (count($attempts) >= 2) {
        if ($improvement > 5) {
            $trend = 'improving';
        } elseif ($improvement < -5) {
            $trend = 'declining';
        }
    }

    return [
        'total_attempts' => count($attempts),
        'average_score' => round($averageScore, 2),
        'best_score' => round($bestScore, 2),
        'worst_score' => round($worstScore, 2),
        'last_score' => round($lastScore, 2),
        'first_score' => round($firstScore, 2),
        'improvement' => round($improvement, 2),
        'trend' => $trend,
        'scores' => $scores,
        'first_date' => $firstAttempt ? $firstAttempt['date_end'] : null,
        'last_date' => $lastAttempt ? $lastAttempt['date_end'] : null
    ];
}
