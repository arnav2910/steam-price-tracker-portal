<?php
include 'includes/db.php';
$active_nav = 'home';
$page_title = 'Home';

// Stats
$total_games = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as c FROM games"))['c'] ?? 0;
$tag_res = mysqli_query($conn,"SELECT DISTINCT category FROM games");
$all_tags = [];
while($r = mysqli_fetch_assoc($tag_res))
    foreach(explode('|',$r['category']) as $t) if(trim($t)) $all_tags[] = trim($t);
$total_cats = count(array_unique($all_tags));
$on_sale = 0;
$sale_res = mysqli_query($conn,
    "SELECT g.id,
     (SELECT price FROM price_history WHERE game_id=g.id ORDER BY price_date DESC LIMIT 1) as cur,
     (SELECT MAX(price) FROM price_history WHERE game_id=g.id) as mx
     FROM games g");
while($r = mysqli_fetch_assoc($sale_res))
    if($r['mx']>0 && $r['cur'] < $r['mx']) $on_sale++;

// Category list — count frequency, top 10 first
$cat_res = mysqli_query($conn,"SELECT category FROM games");
$tag_counts = [];
while($r = mysqli_fetch_assoc($cat_res))
    foreach(explode('|',$r['category']) as $t)
        if(trim($t)) $tag_counts[trim($t)] = ($tag_counts[trim($t)] ?? 0) + 1;
arsort($tag_counts);
$unique_cats = array_keys($tag_counts);

// All games with buy score
$games_res = mysqli_query($conn,
    "SELECT g.id,g.name,g.category,g.cluster_label,g.is_anomaly,p.price,
     r.pos_reviews,r.neg_reviews,
     (SELECT MIN(ph2.price) FROM price_history ph2 WHERE ph2.game_id=g.id) as min_price,
     (SELECT MAX(ph3.price) FROM price_history ph3 WHERE ph3.game_id=g.id) as max_price
     FROM games g
     JOIN price_history p ON g.id=p.game_id
       AND p.price_date=(SELECT MAX(price_date) FROM price_history WHERE game_id=g.id)
     LEFT JOIN review_history r ON g.id=r.game_id
       AND r.review_date=(SELECT MAX(review_date) FROM review_history WHERE game_id=g.id)
     ORDER BY g.name ASC");

function calcBuyScore($cur, $min, $max, $pos, $neg) {
    $total = $pos + $neg;
    $pct = ($total > 0) ? ($pos / $total) * 100 : 0;
    if($cur <= 0) $priceScore = 100;

    // --- Price Score (0-100) ---
    // 100 = at/below all-time low, ~20 = above average price
    $avg = ($min + $max) / 2;
    if($cur <= $min) {
        $priceScore = 100;
    } elseif($cur > $avg) {
        $priceScore = 20;
    } else {
        $priceScore = 20 + (($avg - $cur) / max(0.01, $avg - $min)) * 80;
    }

    // --- Review Score (0-100) ---
    // Uses positive review percentage directly
    $reviewScore = $pct;

    // --- Blending Weights based on review sentiment ---
    if($pct >= 95) {          // Overwhelmingly Positive
        $rw = 0.75; $pw = 0.25;
    } elseif($pct >= 80) {    // Very Positive
        $rw = 0.65; $pw = 0.35;
    } elseif($pct >= 65) {    // Positive
        $rw = 0.55; $pw = 0.45;
    } elseif($pct >= 50) {    // Mixed
        $rw = 0.45; $pw = 0.55;
    } elseif($pct >= 30) {    // Negative
        $rw = 0.35; $pw = 0.65;
    } else {                  // Overwhelmingly Negative
        $rw = 0.25; $pw = 0.75;
    }

    $score = ($reviewScore * $rw) + ($priceScore * $pw);
    return round($score);
}

function buyLabel($s){
    if($s>=85) return ['label'=>'Excellent Buy','color'=>'#2ecc71'];
    if($s>=70) return ['label'=>'Good Value',   'color'=>'#27ae60'];
    if($s>=55) return ['label'=>'Fair Deal',     'color'=>'#f39c12'];
    if($s>=35) return ['label'=>'Wait a Bit',    'color'=>'#e67e22'];
    return             ['label'=>'Avoid',         'color'=>'#e74c3c'];
}

$games = [];
while($g = mysqli_fetch_assoc($games_res)) $games[] = $g;

include 'includes/header.php';
?>

<div class="page-container">

  <!-- HERO -->
  <div class="hero">
    <h1 class="hero-title">Track Steam Prices.<br>Buy at the <span>Right Time</span>.</h1>
    <p class="hero-sub">Historical price &amp; review data for Steam games with ML-powered buy recommendations.</p>
    <div class="hero-stats">
      <div><div class="hero-stat-val"><?php echo $total_games; ?></div><div class="hero-stat-lbl">Games Tracked</div></div>
      <div><div class="hero-stat-val"><?php echo $total_cats; ?></div><div class="hero-stat-lbl">Categories</div></div>
      <div><div class="hero-stat-val"><?php echo $on_sale; ?></div><div class="hero-stat-lbl">On Sale Now</div></div>
    </div>
    <div class="hero-search-row">
      <form action="results.php" method="GET">
        <input type="text" name="q" class="hero-search-input" placeholder="Search for a game or category (e.g. Action, RPG)…" autofocus>
        <button type="submit" class="btn-primary">Search</button>
      </form>
    </div>
  </div>

  <!-- CATEGORY CHIPS -->
  <div class="section-header">
    <div class="section-title"><span class="dot"></span> Browse by Category</div>
  </div>
  <div class="category-chips" style="margin-bottom:32px">
    <?php foreach($unique_cats as $i => $cat):
      $esc = htmlspecialchars($cat); ?>
    <a href="results.php?q=<?php echo urlencode($cat); ?>"
       class="cat-chip<?php echo $i >= 10 ? ' cat-chip-extra' : ''; ?>"><?php echo $esc; ?></a>
    <?php endforeach; ?>
    <?php if(count($unique_cats) > 10): ?>
    <button class="cat-chip cat-chip-more" id="catToggle" onclick="toggleCats()">+ <?php echo count($unique_cats)-10; ?> more</button>
    <?php endif; ?>
  </div>
  <style>
    .cat-chip-extra { display:none; }
    .cat-chip-more { background:transparent; border:1px dashed var(--border); color:var(--text-secondary); cursor:pointer; font-family:inherit; font-size:13px; transition:all .2s; }
    .cat-chip-more:hover { border-color:var(--steam-blue); color:var(--steam-blue); }
  </style>
  <script>
  function toggleCats(){
    var extras = document.querySelectorAll('.cat-chip-extra');
    var btn = document.getElementById('catToggle');
    var open = btn.dataset.open==='1';
    extras.forEach(function(el){ el.style.display = open ? '' : 'inline-flex'; });
    btn.textContent = open ? '+ <?php echo count($unique_cats)-10; ?> more' : '\u2212 fewer';
    btn.dataset.open = open ? '0' : '1';
  }
  </script>

  <!-- ALL GAMES GRID -->
  <div class="section-header">
    <div class="section-title"><span class="dot"></span> All Games</div>
    <a href="questions.php" class="section-link">View Insights →</a>
  </div>

  <?php if(!empty($games)): ?>
  <div class="games-grid">
    <?php foreach($games as $g):
      $cur  = floatval($g['price']);
      $min  = floatval($g['min_price']);
      $max  = floatval($g['max_price']);
      $score= calcBuyScore($cur,$min,$max,$g['pos_reviews'],$g['neg_reviews']);
      $bl   = buyLabel($score);
      $disc = ($max>0 && $cur<$max) ? round(($max-$cur)/$max*100) : 0;
      $isFree = ($cur==0);
    ?>
    <div class="game-card" onclick="location.href='game.php?id=<?php echo $g['id']; ?>'">
      <?php if($disc>0): ?><div class="game-card-discount">-<?php echo $disc; ?>%</div><?php endif; ?>
      <?php
        $gi_file = 'steam_game_headers_by_name/' . $g['name'] . '.jpg';
        $gi_has  = file_exists($gi_file);
      ?>
      <?php if($gi_has): ?>
      <div class="game-card-img has-img"><img src="<?php echo htmlspecialchars($gi_file); ?>" alt="<?php echo htmlspecialchars($g['name']); ?>"></div>
      <?php else: ?>
      <div class="game-card-img"><?php echo htmlspecialchars($g['name']); ?></div>
      <?php endif; ?>
      <div class="game-card-body">
        <div class="game-card-name"><?php echo htmlspecialchars($g['name']); ?></div>
        <div class="game-card-meta">
          <div class="game-card-cat"><?php echo htmlspecialchars(explode('|',$g['category'])[0]); ?></div>
          <div class="game-card-price<?php if($isFree) echo ' free'; ?>">
            <?php echo $isFree ? 'Free' : '₹'.number_format($cur); ?>
          </div>
        </div>
        <div class="game-card-buy">
          <div class="buy-needle">
            <div class="buy-needle-fill" style="width:<?php echo $score; ?>%;background:<?php echo $bl['color']; ?>"></div>
          </div>
          <span class="buy-label" style="color:<?php echo $bl['color']; ?>"><?php echo $bl['label']; ?></span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="table-card" style="padding:48px;text-align:center;color:var(--text-secondary)">
    No games found. <a href="import.php">Sync data</a> to get started.
  </div>
  <?php endif; ?>

</div>
</body>
</html>