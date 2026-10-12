<?php
/**
 * ATHLETICA — Admin: Toggle Hero Slide Status
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
    $stmt = $pdo->prepare("UPDATE hero_slides SET is_active = NOT is_active WHERE id = :id");
    $stmt->execute([':id' => $slideId]);
    set_flash('success', 'Slide status updated.');
} catch (PDOException $e) {
    set_flash('error', 'Database error: ' . $e->getMessage());
}

header('Location: index.php');
exit;
