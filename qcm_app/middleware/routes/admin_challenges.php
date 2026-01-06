<?php
// qcm_app/middleware/routes/admin_challenges.php
// Handler for GET /api/admin-challenges/{id}/leaderboard

require_once __DIR__ . '/../../app/services/exam_service.php';

function handle_get_leaderboard(int $challengeId) {
    try {
        $leaderboard = getLeaderboardForAdminChallenge($challengeId, 10);
        // Normalize entries
        $data = array_map(function($row){
            return [
                'user_identifier' => $row['user_identifier'] ?? null,
                'score_auto' => isset($row['score_auto']) ? (float)$row['score_auto'] : null,
                'total_points' => isset($row['total_points']) ? (float)$row['total_points'] : null,
                'percentage' => isset($row['total_points']) && $row['total_points'] > 0 ? round(((float)$row['score_auto'] / (float)$row['total_points']) * 100, 2) : null,
                'time_spent_seconds' => isset($row['time_spent_seconds']) ? (int)$row['time_spent_seconds'] : null,
                'date_end' => $row['date_end'] ?? null,
                'is_forced_submit' => !empty($row['is_forced_submit']) ? true : false
            ];
        }, $leaderboard);
        send_json($data);
    } catch (Throwable $t) {
        send_error('Failed to load leaderboard: ' . $t->getMessage(), 500);
    }
}
