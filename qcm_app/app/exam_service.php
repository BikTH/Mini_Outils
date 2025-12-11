<?php
// app/exam_service.php
require_once __DIR__ . '/database.php';

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
function saveAttempt(int $examId, ?string $userIdentifier, string $dateStart, string $dateEnd, float $scoreAuto, float $totalPoints, array $answers): int
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("INSERT INTO attempts (exam_id, user_identifier, date_start, date_end, score_auto, total_points) VALUES (:exam_id, :user_identifier, :date_start, :date_end, :score_auto, :total_points)");
    $stmt->execute([
        ':exam_id' => $examId,
        ':user_identifier' => $userIdentifier,
        ':date_start' => $dateStart,
        ':date_end' => $dateEnd,
        ':score_auto' => $scoreAuto,
        ':total_points' => $totalPoints
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

