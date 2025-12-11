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

