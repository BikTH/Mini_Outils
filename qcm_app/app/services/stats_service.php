<?php
// app/services/stats_service.php
// Centralized statistics helpers for qcm_app
require_once __DIR__ . '/../core/database.php';

/**
 * Return a stable leaderboard for an admin_challenge following the project's rules:
 * 1) score descending (score_auto / total_points)
 * 2) time_spent_seconds ascending
 * 3) date_end ascending
 * Only one entry per user (best performance retained). Includes forced submits. Limit to $limit.
 */
function getAdminChallengeLeaderboard(int $challengeId, int $limit = 10): array
{
    $pdo = getPDO();

    // Fetch challenge to validate
    $stmt = $pdo->prepare("SELECT id, exam_id, nb_questions FROM admin_challenges WHERE id = :id");
    $stmt->execute([':id' => $challengeId]);
    $challenge = $stmt->fetch();
    if (!$challenge) return [];

    // Try to use window functions (MySQL 8+) for an efficient, deterministic selection
    try {
        $sql = "SELECT id, user_identifier, score_auto, total_points, time_spent_seconds, date_end, is_forced_submit
                FROM (
                    SELECT a.*, ROW_NUMBER() OVER (PARTITION BY COALESCE(user_identifier, CONCAT('anon_', id))
                        ORDER BY (CASE WHEN a.total_points > 0 THEN (a.score_auto / a.total_points) ELSE 0 END) DESC,
                                 a.time_spent_seconds ASC,
                                 a.date_end ASC) AS rn
                    FROM attempts a
                    WHERE a.admin_challenge_id = :cid
                ) AS t
                WHERE rn = 1
                ORDER BY (CASE WHEN total_points > 0 THEN (score_auto / total_points) ELSE 0 END) DESC, time_spent_seconds ASC, date_end ASC
                LIMIT :limit";

        $stmt2 = $pdo->prepare($sql);
        $stmt2->bindValue(':cid', $challengeId, PDO::PARAM_INT);
        $stmt2->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt2->execute();
        $rows = $stmt2->fetchAll();
        return $rows ?: [];
    } catch (PDOException $e) {
        // Fallback: older MySQL without window functions.
        // Select candidate attempts ordered by our ranking and pick the first per user in PHP.
        $sql2 = "SELECT * FROM attempts WHERE admin_challenge_id = :cid ORDER BY (CASE WHEN total_points > 0 THEN (score_auto / total_points) ELSE 0 END) DESC, time_spent_seconds ASC, date_end ASC";
        $stmt3 = $pdo->prepare($sql2);
        $stmt3->execute([':cid' => $challengeId]);
        $all = $stmt3->fetchAll();

        $seen = [];
        $result = [];
        foreach ($all as $r) {
            $uid = $r['user_identifier'] ?? null;
            // key for uniqueness: user_identifier if present, otherwise use 'anon:' + id to keep anonymous distinct
            $key = $uid !== null && $uid !== '' ? $uid : 'anon:' . $r['id'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $result[] = $r;
            if (count($result) >= $limit) break;
        }
        return $result;
    }
}

?>
