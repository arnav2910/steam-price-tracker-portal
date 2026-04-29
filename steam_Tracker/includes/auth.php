<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function currentUserId(): ?int {
    return isLoggedIn() ? (int)$_SESSION['user_id'] : null;
}

function currentUsername(): ?string {
    return $_SESSION['username'] ?? null;
}

function requireLogin(string $redirect = ''): void {
    if (!isLoggedIn()) {
        $url = 'login.php';
        if ($redirect) $url .= '?redirect=' . urlencode($redirect);
        header("Location: $url");
        exit;
    }
}

function wishlistCount($conn): int {
    $uid = currentUserId();
    if (!$uid) return 0;
    $res = mysqli_query($conn, "SELECT COUNT(*) as c FROM wishlist WHERE user_id=$uid");
    return (int)(mysqli_fetch_assoc($res)['c'] ?? 0);
}

function cartCount($conn): int {
    $uid = currentUserId();
    if (!$uid) return 0;
    $res = mysqli_query($conn, "SELECT COUNT(*) as c FROM cart WHERE user_id=$uid");
    return (int)(mysqli_fetch_assoc($res)['c'] ?? 0);
}
