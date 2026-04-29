<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'login_required']);
    exit;
}

$action  = $_POST['action'] ?? '';
$game_id = (int)($_POST['game_id'] ?? 0);
$user_id = currentUserId();

if (!$game_id || !in_array($action, ['add', 'remove'])) {
    echo json_encode(['success' => false, 'message' => 'invalid_request']);
    exit;
}

if ($action === 'add') {
    mysqli_query($conn, "INSERT IGNORE INTO wishlist (user_id, game_id) VALUES ($user_id, $game_id)");
    $new_action = 'added';
} else {
    mysqli_query($conn, "DELETE FROM wishlist WHERE user_id=$user_id AND game_id=$game_id");
    $new_action = 'removed';
}

$res   = mysqli_query($conn, "SELECT COUNT(*) as c FROM wishlist WHERE user_id=$user_id");
$count = (int)(mysqli_fetch_assoc($res)['c'] ?? 0);

echo json_encode(['success' => true, 'action' => $new_action, 'count' => $count]);
