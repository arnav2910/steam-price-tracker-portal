<?php
include 'db.php';

/**
 * Wilson score lower bound for a Bernoulli parameter.
 * Gives a confidence-adjusted sentiment score (z=1.65 → 95% CI).
 */
function wilsonScore(int $pos, int $neg): float {
    $n = $pos + $neg;
    if ($n === 0) return 0.0;

    $z    = 1.65; // 95% confidence
    $phat = $pos / $n;

    $numerator   = $phat + ($z * $z) / (2 * $n)
                 - $z * sqrt(($phat * (1 - $phat) + ($z * $z) / (4 * $n)) / $n);
    $denominator = 1 + ($z * $z) / $n;

    return $numerator / $denominator;
}

/**
 * Returns a 0–100 "buy now" score based on price history, discount depth,
 * review quality, and how rarely the game has been this cheap.
 *
 * Component breakdown:
 *   50 pts – Price position   (where current sits in the historical min–max range)
 *   20 pts – Discount depth   (how steep the cut is vs. the recorded base/max price)
 *   20 pts – Review quality   (Wilson lower-bound sentiment)
 *   10 pts – Deal rarity      (how rarely the game has been at or below this price)
 */
function getBuyScore(mysqli $conn, int $game_id): int {

    // ------------------------------------------------------------------ //
    //  1. Price history stats                                              //
    // ------------------------------------------------------------------ //
    $res = mysqli_query($conn, "
        SELECT
            MIN(price)  AS min_p,
            MAX(price)  AS max_p,
            AVG(price)  AS avg_p,
            COUNT(*)    AS total_records
        FROM price_history
        WHERE game_id = $game_id
    ");
    $stats = mysqli_fetch_assoc($res);

    $min_p        = (float)$stats['min_p'];
    $max_p        = (float)$stats['max_p'];
    $total_records = (int)$stats['total_records'];

    if ($total_records === 0) return 0;

    // Current price (most recent record)
    $res = mysqli_query($conn, "
        SELECT price FROM price_history
        WHERE game_id = $game_id
        ORDER BY price_date DESC LIMIT 1
    ");
    $current = (float)(mysqli_fetch_assoc($res)['price'] ?? 0);

    if ($current <= 0) return 0;

    // ------------------------------------------------------------------ //
    //  2. COMPONENT A – Price position (50 pts)                           //
    //     Square-root curve: heavily rewards being near the floor,        //
    //     tapers off toward the ceiling rather than a harsh cliff.        //
    // ------------------------------------------------------------------ //
    $score_price = 0.0;
    $price_range = $max_p - $min_p;

    if ($price_range < 0.01) {
        // Price has never moved — treat as neutral
        $score_price = 25.0;
    } else {
        // 0 when current = max_p, 1 when current = min_p
        $normalised   = ($max_p - $current) / $price_range;
        $normalised   = max(0.0, min(1.0, $normalised));   // clamp [0,1]
        $score_price  = 50.0 * sqrt($normalised);
    }

    // ------------------------------------------------------------------ //
    //  3. COMPONENT B – Discount depth (20 pts)                           //
    //     Uses max_p as the "base/full" price proxy.                      //
    //     A 75 %+ discount is treated as a full-score deal.               //
    // ------------------------------------------------------------------ //
    $score_discount = 0.0;
    if ($max_p > 0 && $current < $max_p) {
        $discount_pct   = ($max_p - $current) / $max_p;   // 0 → 1
        $capped_pct     = min($discount_pct / 0.75, 1.0); // saturates at 75 %
        $score_discount = 20.0 * $capped_pct;
    }

    // ------------------------------------------------------------------ //
    //  4. COMPONENT C – Review quality (20 pts)                           //
    //     Wilson lower bound: adjusts for sample size so that             //
    //     "5 of 5" doesn't beat "47 000 of 50 000".                       //
    // ------------------------------------------------------------------ //
    $score_reviews = 0.0;
    $res = mysqli_query($conn, "
        SELECT pos_reviews, neg_reviews FROM review_history
        WHERE game_id = $game_id
        ORDER BY review_date DESC LIMIT 1
    ");
    $rev = mysqli_fetch_assoc($res);

    if ($rev) {
        $wilson        = wilsonScore((int)$rev['pos_reviews'], (int)$rev['neg_reviews']);
        $score_reviews = 20.0 * $wilson;
    }

    // ------------------------------------------------------------------ //
    //  5. COMPONENT D – Deal rarity (10 pts)                              //
    //     Fraction of historical records where price > current.           //
    //     High fraction = game is rarely this cheap = bonus points.       //
    // ------------------------------------------------------------------ //
    $res = mysqli_query($conn, "
        SELECT COUNT(*) AS cheaper_or_equal
        FROM price_history
        WHERE game_id = $game_id AND price <= $current
    ");
    $cheaper_count  = (int)(mysqli_fetch_assoc($res)['cheaper_or_equal'] ?? 0);
    $rarity_ratio   = 1.0 - ($cheaper_count / $total_records); // 1 = never been cheaper
    $score_rarity   = 10.0 * $rarity_ratio;

    // ------------------------------------------------------------------ //
    //  6. Final score                                                      //
    // ------------------------------------------------------------------ //
    $total = $score_price + $score_discount + $score_reviews + $score_rarity;
    return (int)round(min(100, max(0, $total)));
}
?>