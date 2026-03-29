<?php
require_once __DIR__ . '/includes/lms_functions.php';
require_once __DIR__ . '/includes/course_data.php';

require_once __DIR__ . '/includes/layout.php';

auth_require_login();
$user = auth_user();

if ($user['role'] !== 'instructor') {
    http_response_code(403);
    echo "<h1>Forbidden</h1><p>Only instructors can view course analytics.</p>";
    exit;
}

$courseId = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
if ($courseId <= 0) {
    http_response_code(400);
    echo "<h1>Bad Request</h1><p>Invalid course ID.</p>";
    exit;
}

$instructorId = lms_get_instructor_by_user_id((int) $user['user_id'])['instructor_id'] ?? null;
$isOwner = false;
foreach (lms_list_instructor_courses($instructorId) as $c) {
    if ((int)$c['course_id'] === $courseId) {
        $isOwner = true;
        break;
    }
}

if (!$isOwner) {
    http_response_code(403);
    echo "<h1>Forbidden</h1><p>You do not own this course.</p>";
    exit;
}

$analytics = lms_get_detailed_course_analytics($courseId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Course Analytics</title>
    <link rel="stylesheet" href="assets/app.css">
    <style>
        body { padding: 40px; }
        .progress-bar {
            appearance: none;
            width: 100%;
            height: 20px;
        }
        .progress-bar::-webkit-progress-bar { background-color: #eee; border-radius: 4px; }
        .progress-bar::-webkit-progress-value { border-radius: 4px; }
        .progress-bar::-moz-progress-bar { border-radius: 4px; border: none; }
        
        /* Red Default */
        .progress-red::-webkit-progress-value { background-color: #f44336; }
        .progress-red::-moz-progress-bar { background-color: #f44336; }
        
        /* Yellow (<85%) */
        .progress-yellow::-webkit-progress-value { background-color: #ffc107; }
        .progress-yellow::-moz-progress-bar { background-color: #ffc107; }
        
        /* Green (>=85%) */
        .progress-green::-webkit-progress-value { background-color: #4caf50; }
        .progress-green::-moz-progress-bar { background-color: #4caf50; }
        
        .table-wrap th { background: #fafafa; }
    </style>
</head>
<body>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="margin: 0;">Analytics Dashboard</h1>
        <a href="index.php" style="padding: 6px 14px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; font-weight: 500;">&larr; Back to Dashboard</a>
    </div>
    <p class="note">Showing currently enrolled students who have submitted at least 1 assessment.</p>
    
    <div class="card table-wrap">
        <?php if (empty($analytics)): ?>
            <p>No students match the criteria.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #ccc; text-align: left;">
                        <th style="padding: 10px;">Student Name</th>
                        <th style="padding: 10px;">Avg Graded Score</th>
                        <th style="padding: 10px;">Completion Progress</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analytics as $st): 
                        $percentage = (float) $st['completion_rate'];
                        
                        $colorClass = 'progress-red';
                        if ($percentage >= 85) {
                            $colorClass = 'progress-green';
                        } elseif ($percentage >= 65) {
                            $colorClass = 'progress-yellow';
                        }
                    ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;"><strong><?= htmlspecialchars($st['student_name']) ?></strong></td>
                        <td style="padding: 10px;"><?= htmlspecialchars((string) $st['avg_score']) ?></td>
                        <td style="padding: 10px; width: 50%;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <progress class="progress-bar <?= $colorClass ?>" value="<?= $percentage ?>" max="100"></progress>
                                <span style="font-weight: bold; width: 50px; text-align: right;"><?= round($percentage) ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
