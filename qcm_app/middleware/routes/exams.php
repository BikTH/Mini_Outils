<?php
// qcm_app/middleware/routes/exams.php
// Handler for GET /api/exams

require_once __DIR__ . '/../../app/services/exam_service.php';

function handle_get_exams() {
    // Use existing service function to fetch exams
    try {
        $exams = getAllExams();
        // Normalize data for API (avoid leaking internal fields if any)
        $list = array_map(function($e){
            return [
                'id' => (int)$e['id'],
                'titre' => $e['titre'] ?? null,
                'description' => $e['description'] ?? null,
                'nb_questions' => isset($e['nb_questions']) ? (int)$e['nb_questions'] : null,
                'date_creation' => $e['date_creation'] ?? null
            ];
        }, $exams);
        send_json($list);
    } catch (Throwable $t) {
        send_error('Failed to load exams: ' . $t->getMessage(), 500);
    }
}
