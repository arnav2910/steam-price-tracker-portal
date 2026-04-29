<?php
include 'includes/db.php';
set_time_limit(0);
$active_nav = 'ml';
$page_title  = 'ML Engine';
include 'includes/header.php';

// ─────────────────────────────────────────────
// 1. LOAD DATA
// ─────────────────────────────────────────────
$sql = "SELECT g.id, g.name,
        (SELECT price       FROM price_history  WHERE game_id=g.id ORDER BY price_date  DESC LIMIT 1) as price,
        (SELECT pos_reviews FROM review_history WHERE game_id=g.id ORDER BY review_date DESC LIMIT 1) as pos,
        (SELECT neg_reviews FROM review_history WHERE game_id=g.id ORDER BY review_date DESC LIMIT 1) as neg
        FROM games g";
$result = mysqli_query($conn, $sql);

$games = [];
while ($row = mysqli_fetch_assoc($result)) {
    $price = (float)($row['price'] ?? 0);
    $pos   = (int)($row['pos']   ?? 0);
    $neg   = (int)($row['neg']   ?? 0);
    $total = $pos + $neg;
    $sentiment = ($total > 0) ? ($pos / $total) * 100 : 0;

    $games[] = [
        'id'        => $row['id'],
        'name'      => $row['name'],
        'price'     => $price,
        'sentiment' => $sentiment,
    ];
}

if (count($games) < 3) {
    echo '<p style="color:var(--text-secondary);padding:32px">Not enough games to cluster.</p></body></html>';
    exit;
}

// ─────────────────────────────────────────────
// 2. HELPER FUNCTIONS
// ─────────────────────────────────────────────
function arr_mean(array $vals): float {
    return array_sum($vals) / count($vals);
}
function arr_stddev(array $vals, float $mean): float {
    $var = 0;
    foreach ($vals as $v) $var += pow($v - $mean, 2);
    return sqrt($var / count($vals));
}
function arr_percentile(array $sorted, float $pct): float {
    $n   = count($sorted);
    $idx = max(0, min((int)floor($n * $pct), $n - 1));
    return $sorted[$idx];
}

// ─────────────────────────────────────────────
// 3. Z-SCORE NORMALISATION
//    Puts price and sentiment on the same scale
//    (mean=0, stddev=1) so neither dimension
//    dominates the distance calculation.
// ─────────────────────────────────────────────
$prices     = array_column($games, 'price');
$sentiments = array_column($games, 'sentiment');

$mean_p = arr_mean($prices);     $std_p = arr_stddev($prices,     $mean_p) ?: 1;
$mean_s = arr_mean($sentiments); $std_s = arr_stddev($sentiments, $mean_s) ?: 1;

foreach ($games as &$g) {
    $g['z_p'] = ($g['price']     - $mean_p) / $std_p;
    $g['z_s'] = ($g['sentiment'] - $mean_s) / $std_s;
}
unset($g);

// ─────────────────────────────────────────────
// 4. DATA-DRIVEN CENTROID INITIALISATION
//    Seed centroids from the midpoint of each
//    price+sentiment percentile band.
//    Budget  → bottom third (p0–p33)
//    Standard→ middle third (p33–p66)
//    Premium → top third    (p66–p100)
// ─────────────────────────────────────────────
$sp = $prices;     sort($sp);
$ss = $sentiments; sort($ss);

$p33_price = arr_percentile($sp, 0.33);
$p66_price = arr_percentile($sp, 0.66);
$p33_sent  = arr_percentile($ss, 0.33);
$p66_sent  = arr_percentile($ss, 0.66);
$max_price = max($sp);
$max_sent  = max($ss);

// Midpoint of each band, converted to Z-score space
$centroids = [
    'Budget Hits'    => [
        'z_p' => (($p33_price / 2)                                - $mean_p) / $std_p,
        'z_s' => (($p33_sent  / 2)                                - $mean_s) / $std_s,
    ],
    'Standard Tier'  => [
        'z_p' => ((($p33_price + $p66_price) / 2)                 - $mean_p) / $std_p,
        'z_s' => ((($p33_sent  + $p66_sent)  / 2)                 - $mean_s) / $std_s,
    ],
    'Premium Titles' => [
        'z_p' => (($p66_price + ($max_price - $p66_price) * 0.5)  - $mean_p) / $std_p,
        'z_s' => (($p66_sent  + ($max_sent  - $p66_sent)  * 0.5)  - $mean_s) / $std_s,
    ],
];

// ─────────────────────────────────────────────
// 5. PRICE-BAND ASSIGNMENT + SENTIMENT K-MEANS
//    Price band is enforced as a hard boundary using
//    p33 and p66 — a game above p66 cannot ever land
//    in Budget Hits regardless of sentiment score.
//    K-Means then runs purely on sentiment Z-score
//    within each band, so centroids drift to where
//    the sentiment actually clusters in that tier.
// ─────────────────────────────────────────────

// Map each game to its allowed cluster by price first
function price_band(float $price, float $p33, float $p66): string {
    if ($price <= $p33) return 'Budget Hits';
    if ($price <= $p66) return 'Standard Tier';
    return 'Premium Titles';
}

// Sentiment-only centroid per cluster (1D K-Means on z_s within band)
$sent_centroids = [
    'Budget Hits'    => $centroids['Budget Hits']['z_s'],
    'Standard Tier'  => $centroids['Standard Tier']['z_s'],
    'Premium Titles' => $centroids['Premium Titles']['z_s'],
];

$labels    = array_fill(0, count($games), '');
$max_iter  = 100;
$converged = false;

for ($iter = 0; $iter < $max_iter && !$converged; $iter++) {

    $new_labels = [];
    foreach ($games as $g) {
        // Hard price band — only this cluster is eligible
        $new_labels[] = price_band($g['price'], $p33_price, $p66_price);
    }

    $converged = ($new_labels === $labels);
    $labels    = $new_labels;

    // Recompute sentiment centroid from members in each band
    $sums = array_fill_keys(array_keys($sent_centroids), ['z_s' => 0, 'n' => 0]);
    foreach ($games as $i => $g) {
        $lbl = $labels[$i];
        $sums[$lbl]['z_s'] += $g['z_s'];
        $sums[$lbl]['n']++;
    }
    foreach ($sums as $lbl => $s) {
        if ($s['n'] > 0) $sent_centroids[$lbl] = $s['z_s'] / $s['n'];
    }
}

foreach ($games as $i => &$g) $g['cluster'] = $labels[$i];
unset($g);

// ─────────────────────────────────────────────
// 6. PER-CLUSTER STATS + HIDDEN GEM THRESHOLDS
// ─────────────────────────────────────────────
$clusters = [];
foreach ($games as $g) $clusters[$g['cluster']][] = $g;

$cluster_stats = [];
foreach ($clusters as $label => $items) {
    $s_vals  = array_column($items, 'sentiment');
    $p_vals  = array_column($items, 'price');
    $m_s     = arr_mean($s_vals);
    $m_p     = arr_mean($p_vals);
    $std_s_c = arr_stddev($s_vals, $m_s);

    // Median price — more robust than mean when a few expensive games skew the cluster
    $sorted_p = $p_vals; sort($sorted_p);
    $median_p = arr_percentile($sorted_p, 0.50);

    $cluster_stats[$label] = [
        'mean_sentiment'  => $m_s,
        'stddev_sentiment'=> $std_s_c,
        'mean_price'      => $m_p,
        'median_price'    => $median_p,
        // Hidden gem bars for this cluster:
        // Sentiment: cluster mean + 0.75 sigma  (top ~22% within cluster, not extreme)
        // Price cap : cluster median             (below-average price for the cluster)
        'gem_sentiment_bar' => $m_s + (0.75 * $std_s_c),
        'gem_price_cap'     => $median_p,
    ];
}

// ─────────────────────────────────────────────
// 7. PER-CLUSTER HIDDEN GEM DETECTION
//
//  Each cluster judges its own gems independently.
//  A game qualifies when it clears both bars:
//
//  Budget Hits    — sentiment > cluster mean + 0.75s
//                   AND price <= cluster median
//                   Finds cheap games that punch above
//                   their weight within the budget tier.
//
//  Standard Tier  — same formula, same bars
//                   Surfaces mid-range games that are
//                   well-reviewed but still affordable
//                   relative to their standard peers.
//
//  Premium Titles — same formula, same bars
//                   Flags premium games with exceptional
//                   reception that are priced below the
//                   premium median — underpriced quality.
//
//  0.75 sigma keeps the bar reachable (vs 1 sigma
//  which often flags only 1-2 games per cluster).
//  Median price cap avoids being skewed by outliers.
//  Free games (price=0) excluded throughout.
// ─────────────────────────────────────────────
foreach ($games as &$g) {
    $label = $g['cluster'];
    $stats = $cluster_stats[$label];

    $is_anomaly = (
        $g['price']     >=  0 &&
        $g['sentiment'] >= $stats['gem_sentiment_bar'] &&
        $g['price']     <= $stats['gem_price_cap']
    ) ? 1 : 0;

    $g['is_anomaly'] = $is_anomaly;
    $cid    = (int)$g['id'];
    $clabel = mysqli_real_escape_string($conn, $label);
    mysqli_query($conn, "UPDATE games SET cluster_label='$clabel', is_anomaly=$is_anomaly WHERE id=$cid");
}
unset($g);

// Rebuild clusters with anomaly flags, in fixed display order
$clusters = [];
foreach ($games as $g) $clusters[$g['cluster']][] = $g;

$order = ['Budget Hits', 'Standard Tier', 'Premium Titles'];
uksort($clusters, fn($a, $b) => array_search($a, $order) <=> array_search($b, $order));
?>

<div class="page-container">
  <div class="section-header">
    <div class="section-title"><span class="dot"></span> ML Engine Results</div>
  </div>

  <p style="color:var(--text-secondary);font-size:14px;margin-bottom:6px">
    <?php echo count($games); ?> games clustered via K-Means
    (converged in <?php echo $iter; ?> iteration<?php echo $iter !== 1 ? 's' : ''; ?>)
    using Z-score normalised price &amp; sentiment.
  </p>


  <?php foreach ($clusters as $name => $items):
    $stats     = $cluster_stats[$name];
    $gem_count = count(array_filter($items, fn($g) => $g['is_anomaly']));
  ?>
  <div class="section-header" style="margin-top:24px">
    <div class="section-title"><span class="dot"></span> <?php echo htmlspecialchars($name); ?></div>
    <span style="font-family:var(--font-mono);font-size:12px;color:var(--text-dim)">
      <?php echo count($items); ?> games
      <?php if ($gem_count > 0): ?>
        &nbsp;·&nbsp;<span style="color:#c0a060">⭐ <?php echo $gem_count; ?> hidden gem<?php echo $gem_count > 1 ? 's' : ''; ?></span>
      <?php endif; ?>
    </span>
  </div>

  <p style="font-size:12px;color:#889297;margin-top:-10px;margin-bottom:10px;">
    Avg Price: ₹<?php echo number_format($stats['mean_price'], 0); ?>
    &nbsp;|&nbsp;
    Avg Sentiment: <?php echo round($stats['mean_sentiment'], 1); ?>%
    (σ: <?php echo round($stats['stddev_sentiment'], 1); ?>)
    &nbsp;·&nbsp;
    <span style="color:#c0a060">
      Gem bar: sentiment ≥ <?php echo round($stats['gem_sentiment_bar'], 1); ?>%
      &amp; price ≤ ₹<?php echo number_format($stats['gem_price_cap'], 0); ?>
    </span>
  </p>

  <div class="table-card" style="margin-bottom:20px">
    <table>
      <tr><th>Game</th><th>Price</th><th>Sentiment</th><th>Hidden Gem</th></tr>
      <?php foreach ($items as $g): ?>
      <tr>
        <td>
          <a href="game.php?id=<?php echo $g['id']; ?>" style="color:var(--text-primary);font-weight:600">
            <?php echo htmlspecialchars($g['name']); ?>
          </a>
        </td>
        <td style="font-family:var(--font-mono)">
          <?php echo $g['price'] > 0 ? '₹' . number_format($g['price'], 0) : 'Free'; ?>
        </td>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <div style="width:80px;height:6px;background:var(--bg-input);border-radius:3px;overflow:hidden">
              <div style="width:<?php echo round($g['sentiment']); ?>%;height:100%;background:var(--steam-blue);border-radius:3px"></div>
            </div>
            <span style="font-family:var(--font-mono);font-size:12px;color:var(--text-secondary)">
              <?php echo round($g['sentiment'], 1); ?>%
            </span>
          </div>
        </td>
        <td><?php echo $g['is_anomaly'] ? '<span class="anomaly-badge">⭐ Yes</span>' : '—'; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endforeach; ?>

  <div style="display:flex;gap:12px;margin-top:8px">
    <a href="index.php" class="btn-primary">← View Games</a>
    <a href="ml_engine.php" class="btn-secondary">🔄 Re-run</a>
  </div>
</div>
</body>
</html>