<?php
include 'db.php';

/**
 * Wilson score lower bound (z = 1.65 → ~95% one-sided CI).
 * Prevents "5/5 reviews" beating "47,000/50,000 reviews".
 */
function wilsonScore(int $pos, int $neg): float {
    $n = $pos + $neg;
    if ($n === 0) return 0.0;

    $z    = 1.65;
    $phat = $pos / $n;

    return (
        $phat + ($z * $z) / (2 * $n)
        - $z * sqrt(($phat * (1 - $phat) + ($z * $z) / (4 * $n)) / $n)
    ) / (1 + ($z * $z) / $n);
}

/**
 * Maps a Wilson score to a 0–75 review score using fuzzy bands.
 *
 * Mirrors Steam's own sentiment labels so scores feel intuitive:
 *
 *   ≥ 0.93  → "Overwhelmingly Positive"  → 65–75
 *   ≥ 0.80  → "Very Positive"            → 52–65
 *   ≥ 0.70  → "Mostly Positive"          → 35–52
 *   ≥ 0.55  → "Mixed"                    → 18–35
 *   ≥ 0.40  → "Mostly Negative"          →  5–18
 *    < 0.40  → "Overwhelmingly Negative"  →  0–5
 *
 * Within each band, score interpolates linearly to avoid hard jumps
 * at boundaries.
 */
function reviewScore(int $pos, int $neg): float {
    $w = wilsonScore($pos, $neg);

    // [ lower_wilson, base_score, ceil_score, upper_wilson ]
    $bands = [
        [0.93, 65.0, 75.0, 1.00],
        [0.80, 52.0, 65.0, 0.93],
        [0.70, 35.0, 52.0, 0.80],
        [0.55, 18.0, 35.0, 0.70],
        [0.40,  5.0, 18.0, 0.55],
    ];

    foreach ($bands as [$lo, $base, $ceil, $hi]) {
        if ($w >= $lo) {
            $t = ($w - $lo) / ($hi - $lo);
            return $base + $t * ($ceil - $base);
        }
    }

    // Below 0.40: scale down toward 0
    return 5.0 * ($w / 0.40);
}

/**
 * Returns a 0–100 "buy now" score.
 *
 * Breakdown:
 *   75 pts — Review quality  (fuzzy-banded Wilson sentiment)
 *   25 pts — Price value     (1 - current/all_time_high)
 *
 * Free-to-play rules:
 *   - Always free  (current = 0, ATH = 0) → full 25 price pts
 *   - Temporarily free (current = 0, ATH > 0) → full 25 price pts
 *   - Normal paid game → scales 0–25 by discount depth
 *   - Bad data (current > 0, ATH = 0) → 0 price pts
 */
function getBuyScore(mysqli $conn, int $game_id): int {

    // ------------------------------------------------------------------ //
    //  1. Reviews (75 pts)                                                 //
    // ------------------------------------------------------------------ //
    $score_reviews = 0.0;

    $res = mysqli_query($conn, "
        SELECT pos_reviews, neg_reviews
        FROM review_history
        WHERE game_id = $game_id
        ORDER BY review_date DESC
        LIMIT 1
    ");
    $rev = mysqli_fetch_assoc($res);

    if ($rev) {
        $score_reviews = reviewScore(
            (int)$rev['pos_reviews'],
            (int)$rev['neg_reviews']
        );
    }

    // ------------------------------------------------------------------ //
    //  2. Price (25 pts)                                                   //
    // ------------------------------------------------------------------ //
    $score_price = 0.0;

    $res = mysqli_query($conn, "
        SELECT MAX(price) AS all_time_high
        FROM price_history
        WHERE game_id = $game_id
    ");
    $all_time_high = (float)(mysqli_fetch_assoc($res)['all_time_high'] ?? 0);

    $res = mysqli_query($conn, "
        SELECT price
        FROM price_history
        WHERE game_id = $game_id
        ORDER BY price_date DESC
        LIMIT 1
    ");
    $current = (float)(mysqli_fetch_assoc($res)['price'] ?? 0);

    if ($current == 0 && $all_time_high == 0) {
        // Always free (F2P) — price is never a barrier
        $score_price = 25.0;
    } elseif ($current == 0 && $all_time_high > 0) {
        // Temporarily free — best possible deal
        $score_price = 25.0;
    } elseif ($all_time_high > 0) {
        // Normal paid game: deeper discount = more points
        $ratio       = min($current / $all_time_high, 1.0);
        $score_price = (1.0 - $ratio) * 25.0;
    }
    // Bad data (current > 0, ATH = 0): score_price stays 0.0

    // ------------------------------------------------------------------ //
    //  3. Final score                                                      //
    // ------------------------------------------------------------------ //
    $total = $score_reviews + $score_price;
    return (int)round(min(100, max(0, $total)));
}
?>