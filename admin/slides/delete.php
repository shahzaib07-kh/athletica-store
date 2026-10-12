<?php
/**
 * ATHLETICA — Admin: Delete Hero Slide
 */

require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/csrf.php';
require __DIR__ . '/../../includes/functions.php';
require __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!csrf_verify()) {
    set_flash('error', 'Invalid security token.');
    header('Location: index.php');
    exit;
}

$slideId = (int)($_POST['slide_id'] ?? 0);
if ($slideId <= 0) {
    set_flash('error', 'Invalid slide ID.');
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT media_path FROM hero_slides WHERE id = :id");
    $stmt->execute([':id' => $slideId]);
    $slide = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($slide) {
        // Delete physical file from disk
        $filePath = __DIR__ . '/../../' . $slide['media_path'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        $delStmt = $pdo->prepare("DELETE FROM hero_slides WHERE id = :id");
        $delStmt->execute([':id' => $slideId]);
        set_flash('success', 'Hero slide deleted successfully.');
    } else {
        set_flash('error', 'Slide not found.');
    }
} catch (PDOException $e) {
    set_flash('error', 'Database error: ' . $e->getMessage());
}

header('Location: index.php');
exit;
