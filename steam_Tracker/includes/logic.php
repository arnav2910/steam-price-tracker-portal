<?php
include 'db.php';

function getBuyScore($conn, $game_id) {
    // 1. Get Current Price
    $res = mysqli_query($conn, "SELECT price FROM price_history WHERE game_id = $game_id ORDER BY price_date DESC LIMIT 1");
    $current = mysqli_fetch_assoc($res)['price'] ?? 0;

    if($cur <= 0) $priceScore = 100;

    // 2. Get Price Stats (min and max for all-time range)
    $res_stats = mysqli_query($conn, "SELECT MIN(price) as min_p, MAX(price) as max_p FROM price_history WHERE game_id = $game_id");
    $stats = mysqli_fetch_assoc($res_stats);
    $min = $stats['min_p'];
    $max = $stats['max_p'];
    $avg = ($min + $max) / 2;

    // Price Score
    if ($current <= $min) {
        $priceScore = 100;
    } elseif ($current > $avg) {
        $priceScore = 20;
    } else {
        $priceScore = 20 + (($avg - $current) / max(0.01, $avg - $min)) * 80;
    }

    // 3. Get Reviews
    $res_rev = mysqli_query($conn, "SELECT pos_reviews, neg_reviews FROM review_history WHERE game_id = $game_id ORDER BY review_date DESC LIMIT 1");
    $rev = mysqli_fetch_assoc($res_rev);

    $pct = 0;
    if ($rev) {
        $total = $rev['pos_reviews'] + $rev['neg_reviews'];
        $pct = ($total > 0) ? ($rev['pos_reviews'] / $total) * 100 : 0;
    }

    // Review Score (0-100)
    $reviewScore = $pct;

    // Blending Weights based on review sentiment
    if ($pct >= 95) {         // Overwhelmingly Positive
        $rw = 0.75; $pw = 0.25;
    } elseif ($pct >= 80) {   // Very Positive
        $rw = 0.65; $pw = 0.35;
    } elseif ($pct >= 65) {   // Positive
        $rw = 0.55; $pw = 0.45;
    } elseif ($pct >= 50) {   // Mixed
        $rw = 0.45; $pw = 0.55;
    } elseif ($pct >= 30) {   // Negative
        $rw = 0.35; $pw = 0.65;
    } else {                  // Overwhelmingly Negative
        $rw = 0.25; $pw = 0.75;
    }

    $score = ($reviewScore * $rw) + ($priceScore * $pw);
    return round($score);
}
?>