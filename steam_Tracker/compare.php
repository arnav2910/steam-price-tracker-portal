<?php
include 'includes/db.php';
include 'includes/logic.php';
require_once 'includes/auth.php';

$active_nav  = 'compare';
$page_title  = 'Compare Games';
$extra_head  = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>';

// Fetch all games for the selector dropdowns
$all_games_res = mysqli_query($conn, "SELECT id, name FROM games ORDER BY name ASC");
$all_games = [];
while ($r = mysqli_fetch_assoc($all_games_res)) $all_games[] = $r;

$g1_id = isset($_GET['g1']) ? (int)$_GET['g1'] : 0;
$g2_id = isset($_GET['g2']) ? (int)$_GET['g2'] : 0;

// ── Helper: load full game data for comparison ──────────────────────────
function loadGameData($conn, $id) {
    if (!$id) return null;

    $g = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM games WHERE id=$id"));
    if (!$g) return null;

    // Price history
    $ph = mysqli_query($conn, "SELECT price_date, price FROM price_history WHERE game_id=$id ORDER BY price_date ASC");
    $dates = []; $prices = [];
    while ($r = mysqli_fetch_assoc($ph)) { $dates[] = $r['price_date']; $prices[] = (float)$r['price']; }

    $cur   = $prices ? end($prices)   : 0;
    $min   = $prices ? min($prices)   : 0;
    $max   = $prices ? max($prices)   : 0;
    $avg   = $prices ? array_sum($prices)/count($prices) : 0;
    $disc  = ($max > 0 && $cur < $max) ? round(($max - $cur) / $max * 100) : 0;

    // Reviews (latest row)
    $rv = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT SUM(pos_reviews) as pos, SUM(neg_reviews) as neg FROM review_history WHERE game_id=$id"));
    $pos   = (int)($rv['pos'] ?? 0);
    $neg   = (int)($rv['neg'] ?? 0);
    $total = $pos + $neg;
    $pct   = $total > 0 ? round($pos / $total * 100) : 0;

    // Review history for chart
    $rh = mysqli_query($conn, "SELECT review_date, pos_reviews, neg_reviews FROM review_history WHERE game_id=$id ORDER BY review_date ASC");
    $rh_dates = []; $rh_pos = []; $rh_neg = [];
    while ($r = mysqli_fetch_assoc($rh)) {
        $rh_dates[] = $r['review_date'];
        $rh_pos[]   = (int)$r['pos_reviews'];
        $rh_neg[]   = (int)$r['neg_reviews'];
    }

    // Buy score
    $buy_score = getBuyScore($conn, $id);

    $img_file = 'steam_game_headers_by_name/' . $g['name'] . '.jpg';
    $has_img  = file_exists($img_file);

    // Review label
    $rev_label = 'Mixed'; $rev_color = 'var(--yellow)';
    if      ($pct >= 95) { $rev_label = 'Overwhelmingly Positive'; $rev_color = 'var(--steam-blue)'; }
    elseif  ($pct >= 80) { $rev_label = 'Very Positive';           $rev_color = 'var(--steam-blue)'; }
    elseif  ($pct >= 70) { $rev_label = 'Positive';                $rev_color = 'var(--steam-blue)'; }
    elseif  ($pct <  40) { $rev_label = 'Negative';                $rev_color = 'var(--red)'; }

    // Buy label
    $buy_label = 'Avoid'; $buy_color = '#e74c3c';
    if      ($buy_score >= 85) { $buy_label = 'Excellent Buy'; $buy_color = '#2ecc71'; }
    elseif  ($buy_score >= 70) { $buy_label = 'Good Value';    $buy_color = '#27ae60'; }
    elseif  ($buy_score >= 55) { $buy_label = 'Fair Deal';     $buy_color = '#f39c12'; }
    elseif  ($buy_score >= 35) { $buy_label = 'Wait a Bit';    $buy_color = '#e67e22'; }

    return compact('g','dates','prices','cur','min','max','avg','disc',
                   'pos','neg','total','pct','buy_score',
                   'rh_dates','rh_pos','rh_neg',
                   'img_file','has_img','rev_label','rev_color','buy_label','buy_color');
}

$data1 = ($g1_id) ? loadGameData($conn, $g1_id) : null;
$data2 = ($g2_id) ? loadGameData($conn, $g2_id) : null;
$comparing = ($data1 && $data2);

// Wishlist / Cart status
$_uid = currentUserId();
$in_wl1 = $in_wl2 = $in_ct1 = $in_ct2 = false;
if ($_uid && $comparing) {
    $r = mysqli_query($conn, "SELECT game_id FROM wishlist WHERE user_id=$_uid AND game_id IN ($g1_id,$g2_id)");
    while($row = mysqli_fetch_assoc($r)) {
        if ($row['game_id'] == $g1_id) $in_wl1 = true;
        if ($row['game_id'] == $g2_id) $in_wl2 = true;
    }
    $r = mysqli_query($conn, "SELECT game_id FROM cart WHERE user_id=$_uid AND game_id IN ($g1_id,$g2_id)");
    while($row = mysqli_fetch_assoc($r)) {
        if ($row['game_id'] == $g1_id) $in_ct1 = true;
        if ($row['game_id'] == $g2_id) $in_ct2 = true;
    }
}

// ── Compute winner flags ─────────────────────────────────────────────────
function winnerClass($a, $b, $lower_is_better = false) {
    if ($a == $b) return ['', ''];
    if ($lower_is_better) {
        return ($a < $b) ? ['better', 'worse'] : ['worse', 'better'];
    }
    return ($a > $b) ? ['better', 'worse'] : ['worse', 'better'];
}

$w_price = $comparing ? winnerClass($data1['cur'], $data2['cur'], true)  : ['',''];
$w_disc  = $comparing ? winnerClass($data1['disc'], $data2['disc'])       : ['',''];
$w_min   = $comparing ? winnerClass($data1['min'], $data2['min'], true)   : ['',''];
$w_buy   = $comparing ? winnerClass($data1['buy_score'], $data2['buy_score']) : ['',''];
$w_rev   = $comparing ? winnerClass($data1['pct'], $data2['pct'])         : ['',''];
$w_total = $comparing ? winnerClass($data1['total'], $data2['total'])     : ['',''];

// Overall winner
$score1 = $score2 = 0;
if ($comparing) {
    // Buy score 40%, review % 35%, discount 25%
    $score1 = $data1['buy_score'] * 0.40 + $data1['pct'] * 0.35 + $data1['disc'] * 0.25;
    $score2 = $data2['buy_score'] * 0.40 + $data2['pct'] * 0.35 + $data2['disc'] * 0.25;
}

include 'includes/header.php';
?>

<div class="page-container">

  <!-- Page heading -->
  <div style="display:flex;align-items:center;gap:16px;margin-bottom:28px;flex-wrap:wrap">
    <a href="javascript:history.back()" class="btn-reset">← Back</a>
    <div>
      <h1 style="font-family:var(--font-display);font-size:26px;font-weight:700;color:#fff;margin-bottom:2px">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9b59b6" stroke-width="2" style="vertical-align:-3px;margin-right:6px"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
        Compare Games
      </h1>
      <p style="color:var(--text-secondary);font-size:13px">Select two games to compare price history, reviews &amp; value</p>
    </div>
  </div>

  <!-- ── SELECTOR FORM ───────────────────────────────────────────────── -->
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:28px;margin-bottom:36px">
    <form method="GET" action="compare.php" id="compareForm">
      <div style="display:grid;grid-template-columns:1fr auto 1fr auto;gap:14px;align-items:end;flex-wrap:wrap">
        <!-- Game 1 -->
        <div>
          <label style="display:block;font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px">Game A</label>
          <div style="position:relative">
            <select name="g1" id="sel1" onchange="updateSwap()" style="width:100%;padding:11px 14px;background:var(--bg-input);border:1px solid var(--border);border-radius:var(--radius);color:#e8eaf0;font-size:14px;font-family:inherit;appearance:none;padding-right:32px">
              <option value="">— Select a game —</option>
              <?php foreach($all_games as $ag): ?>
              <option value="<?php echo $ag['id']; ?>" <?php if($g1_id==$ag['id']) echo 'selected'; ?>>
                <?php echo htmlspecialchars($ag['name']); ?>
              </option>
              <?php endforeach; ?>
            </select>
            <svg style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--text-dim)" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
          </div>
        </div>

        <!-- VS divider + swap button -->
        <div style="text-align:center;padding-bottom:2px">
          <button type="button" onclick="swapGames()" title="Swap games" style="display:flex;flex-direction:column;align-items:center;gap:4px;background:transparent;border:1px solid var(--border);border-radius:var(--radius);padding:10px 12px;cursor:pointer;color:var(--text-secondary);transition:var(--transition)" onmouseover="this.style.borderColor='#9b59b6';this.style.color='#9b59b6'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-secondary)'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 16V4m0 0L3 8m4-4l4 4"/><path d="M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
            <span style="font-size:10px;font-weight:700;letter-spacing:.5px">SWAP</span>
          </button>
        </div>

        <!-- Game 2 -->
        <div>
          <label style="display:block;font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px">Game B</label>
          <div style="position:relative">
            <select name="g2" id="sel2" onchange="updateSwap()" style="width:100%;padding:11px 14px;background:var(--bg-input);border:1px solid var(--border);border-radius:var(--radius);color:#e8eaf0;font-size:14px;font-family:inherit;appearance:none;padding-right:32px">
              <option value="">— Select a game —</option>
              <?php foreach($all_games as $ag): ?>
              <option value="<?php echo $ag['id']; ?>" <?php if($g2_id==$ag['id']) echo 'selected'; ?>>
                <?php echo htmlspecialchars($ag['name']); ?>
              </option>
              <?php endforeach; ?>
            </select>
            <svg style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--text-dim)" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
          </div>
        </div>

        <!-- Submit -->
        <div style="padding-bottom:2px">
          <button type="submit" class="btn-primary" style="white-space:nowrap;padding:11px 24px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:6px"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
            Compare
          </button>
        </div>
      </div>
    </form>
  </div>

<?php if (!$comparing && ($g1_id || $g2_id)): ?>
  <div style="text-align:center;padding:60px 24px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);color:var(--text-secondary)">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:.4"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
    <p style="font-size:15px">Please select two valid games to compare.</p>
  </div>
<?php endif; ?>

<?php if (!$comparing && !$g1_id && !$g2_id): ?>
  <!-- Empty state tips -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:8px">
    <?php
    $tips = [
      ['icon'=>'','title'=>'Best Value Pick','desc'=>'See which game offers more for your money right now'],
      ['icon'=>'','title'=>'Price History','desc'=>'Compare how prices have evolved over time on one chart'],
      ['icon'=>'','title'=>'Review Sentiment','desc'=>'Weigh community scores and total review counts side by side'],
      ['icon'=>'','title'=>'Overall Verdict','desc'=>'Get a clear recommendation on which game to buy today'],
    ];
    foreach($tips as $tip): ?>
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:20px;display:flex;gap:14px;align-items:flex-start">
      <span style="font-size:24px;line-height:1"><?php echo $tip['icon']; ?></span>
      <div>
        <div style="font-weight:600;color:#e8eaf0;font-size:13px;margin-bottom:4px"><?php echo $tip['title']; ?></div>
        <div style="color:var(--text-secondary);font-size:12px;line-height:1.5"><?php echo $tip['desc']; ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($comparing): ?>

  <!-- ── OVERALL VERDICT ────────────────────────────────────────────── -->
  <?php
    $verdict_winner = ($score1 > $score2) ? $data1['g']['name'] : (($score2 > $score1) ? $data2['g']['name'] : null);
    $verdict_loser  = ($score1 > $score2) ? $data2['g']['name'] : (($score2 > $score1) ? $data1['g']['name'] : null);
    $is_tie = ($score1 == $score2);
  ?>
  <div style="background:linear-gradient(135deg,rgba(155,89,182,0.12) 0%,rgba(26,30,42,1) 60%);border:1px solid rgba(155,89,182,0.35);border-radius:var(--radius-lg);padding:28px 32px;margin-bottom:32px;display:flex;align-items:center;gap:24px;flex-wrap:wrap">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#9b59b6" stroke-width="1.5" style="flex-shrink:0"><path d="M8 21H12M16 21H12M12 21V13M6 3H18L16 13H8L6 3Z"/></svg>
    <div style="flex:1;min-width:200px">
      <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#9b59b6;margin-bottom:4px">Overall Verdict</div>
      <?php if ($is_tie): ?>
        <div style="font-size:20px;font-weight:800;color:#e8eaf0;font-family:var(--font-display)">It's a Tie!</div>
        <div style="font-size:13px;color:var(--text-secondary);margin-top:4px">Both games score equally across price, value &amp; reviews. You can't go wrong with either choice.</div>
      <?php else: ?>
        <div style="font-size:20px;font-weight:800;color:#e8eaf0;font-family:var(--font-display)"><?php echo htmlspecialchars($verdict_winner); ?> wins</div>
        <div style="font-size:13px;color:var(--text-secondary);margin-top:4px">
          Based on buy score, review sentiment &amp; current discount —
          <strong style="color:#9b59b6"><?php echo htmlspecialchars($verdict_winner); ?></strong>
          offers better overall value right now compared to
          <strong style="color:var(--text-secondary)"><?php echo htmlspecialchars($verdict_loser); ?></strong>.
        </div>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:20px;flex-shrink:0;flex-wrap:wrap">
      <div style="text-align:center">
        <div style="font-size:22px;font-weight:800;color:<?php echo ($score1>=$score2)?'#9b59b6':'var(--text-secondary)'; ?>;font-family:var(--font-display)"><?php echo round($score1); ?></div>
        <div style="font-size:11px;color:var(--text-dim);margin-top:2px"><?php echo htmlspecialchars(mb_strimwidth($data1['g']['name'],0,18,'…')); ?></div>
      </div>
      <div style="font-size:18px;font-weight:700;color:var(--text-dim);align-self:center">vs</div>
      <div style="text-align:center">
        <div style="font-size:22px;font-weight:800;color:<?php echo ($score2>$score1)?'#9b59b6':'var(--text-secondary)'; ?>;font-family:var(--font-display)"><?php echo round($score2); ?></div>
        <div style="font-size:11px;color:var(--text-dim);margin-top:2px"><?php echo htmlspecialchars(mb_strimwidth($data2['g']['name'],0,18,'…')); ?></div>
      </div>
    </div>
  </div>

  <!-- ── SIDE-BY-SIDE PANELS ───────────────────────────────────────── -->
  <div class="compare-grid">

    <?php foreach ([1, 2] as $slot):
      $d = ($slot == 1) ? $data1 : $data2;
      $gid = ($slot == 1) ? $g1_id : $g2_id;
      $in_wl = ($slot == 1) ? $in_wl1 : $in_wl2;
      $in_ct = ($slot == 1) ? $in_ct1 : $in_ct2;
      $side_color = ($slot == 1) ? '#1a9fff' : '#9b59b6';
      $is_winner  = (!$is_tie) && (($slot == 1 && $score1 > $score2) || ($slot == 2 && $score2 > $score1));
    ?>
    <div class="compare-panel" style="border-color:<?php echo $is_winner ? $side_color : 'var(--border)'; ?>;box-shadow:<?php echo $is_winner ? "0 0 0 1px $side_color" : 'none'; ?>">
      <!-- Header image -->
      <div class="compare-panel-header">
        <?php if ($d['has_img']): ?>
          <img src="<?php echo htmlspecialchars($d['img_file']); ?>" alt="<?php echo htmlspecialchars($d['g']['name']); ?>">
        <?php else: ?>
          <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--text-dim);font-size:13px"><?php echo htmlspecialchars($d['g']['name']); ?></div>
        <?php endif; ?>
        <!-- Game A / Game B label -->
        <div style="position:absolute;top:10px;left:10px;background:<?php echo $side_color; ?>;color:#fff;font-size:11px;font-weight:700;padding:3px 10px;border-radius:4px;letter-spacing:.5px">
          Game <?php echo ($slot==1)?'A':'B'; ?>
          <?php if ($is_winner): ?> &nbsp;🏆<?php endif; ?>
        </div>
        <div class="compare-panel-title"><?php echo htmlspecialchars($d['g']['name']); ?></div>
      </div>

      <!-- Body: stats -->
      <div class="compare-panel-body">

        <!-- Categories -->
        <div style="margin-bottom:16px;display:flex;gap:6px;flex-wrap:wrap">
          <?php foreach(array_slice(explode('|',$d['g']['category']),0,4) as $tag): $tag=trim($tag); if(!$tag) continue; ?>
          <span style="background:rgba(26,159,255,0.1);border:1px solid rgba(26,159,255,0.2);color:var(--steam-blue);border-radius:4px;font-size:11px;font-weight:600;padding:2px 9px"><?php echo htmlspecialchars($tag); ?></span>
          <?php endforeach; ?>
        </div>

        <!-- Stats -->
        <?php
          $wc1 = ($slot==1) ? $w_price : array_reverse($w_price);
          $wc1 = ($slot==1) ? $w_price[0] : $w_price[1];
          $stats = [
            ['Current Price',  $d['cur']<=0  ? 'Free'      : '₹'.number_format($d['cur']),   ($slot==1)?$w_price[0]:$w_price[1]],
            ['Lowest Ever',    $d['min']<=0  ? '—'         : '₹'.number_format($d['min']),    ($slot==1)?$w_min[0]:$w_min[1]],
            ['Highest Ever',   $d['max']<=0  ? '—'         : '₹'.number_format($d['max']),    ''],
            ['Avg Price',      $d['avg']<=0  ? '—'         : '₹'.number_format($d['avg'],0),  ''],
            ['Discount',       $d['disc'].'%',                                                 ($slot==1)?$w_disc[0]:$w_disc[1]],
            ['Buy Score',      $d['buy_score'].'/100',                                         ($slot==1)?$w_buy[0]:$w_buy[1]],
            ['Buy Rating',     $d['buy_label'],                                                ''],
            ['Review Score',   $d['pct'].'% Positive',                                        ($slot==1)?$w_rev[0]:$w_rev[1]],
            ['Total Reviews',  number_format($d['total']),                                     ($slot==1)?$w_total[0]:$w_total[1]],
            ['Sentiment',      $d['rev_label'],                                                ''],
          ];
          foreach($stats as [$label, $val, $cls]):
        ?>
        <div class="compare-stat-row">
          <span class="compare-stat-label"><?php echo $label; ?></span>
          <span class="compare-stat-val <?php echo $cls; ?>"><?php echo htmlspecialchars($val); ?>
            <?php if($cls==='better'): ?><span class="compare-winner-tag">▲ BETTER</span><?php endif; ?>
          </span>
        </div>
        <?php endforeach; ?>

        <!-- Buy score needle -->
        <div style="margin-top:16px;margin-bottom:4px">
          <div class="buy-needle">
            <div class="buy-needle-fill" style="width:<?php echo $d['buy_score']; ?>%;background:<?php echo $d['buy_color']; ?>"></div>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-dim);margin-top:4px"><span>0</span><span style="color:<?php echo $d['buy_color']; ?>;font-weight:700"><?php echo $d['buy_label']; ?></span><span>100</span></div>
        </div>

        <!-- Review bar -->
        <div class="review-bar-wrap" style="margin-top:16px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <span style="color:<?php echo $d['rev_color']; ?>;font-size:12px;font-weight:600"><?php echo $d['rev_label']; ?></span>
            <span style="font-family:var(--font-mono);font-size:11px;color:var(--text-secondary)"><?php echo $d['pct']; ?>% positive</span>
          </div>
          <div class="review-bar-track">
            <div class="review-bar-fill" style="width:<?php echo $d['pct']; ?>%"></div>
          </div>
        </div>

        <!-- Action buttons -->
        <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap">
          <a href="game.php?id=<?php echo $gid; ?>" class="btn-reset" style="flex:1;text-align:center;font-size:13px">View Details</a>
          <?php if(isLoggedIn()): ?>
          <button class="btn-action-wl<?php if($in_wl) echo ' active'; ?>"
                  id="wlBtn<?php echo $slot; ?>"
                  onclick="toggleWL(<?php echo $gid; ?>,<?php echo $slot; ?>)"
                  style="flex:1;font-size:12px;padding:9px 14px;min-width:0">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="<?php echo $in_wl?'currentColor':'none'; ?>" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            <span id="wlLbl<?php echo $slot; ?>"><?php echo $in_wl?'In Wishlist':'Wishlist'; ?></span>
          </button>
          <button class="btn-action-cart<?php if($in_ct) echo ' active'; ?>"
                  id="ctBtn<?php echo $slot; ?>"
                  onclick="toggleCart(<?php echo $gid; ?>,<?php echo $slot; ?>)"
                  style="flex:1;font-size:12px;padding:9px 14px;min-width:0">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            <span id="ctLbl<?php echo $slot; ?>"><?php echo $in_ct?'In Cart':'Add to Cart'; ?></span>
          </button>
          <?php endif; ?>
        </div>

      </div><!-- /.compare-panel-body -->
    </div><!-- /.compare-panel -->
    <?php endforeach; ?>

  </div><!-- /.compare-grid -->

  <!-- ── OVERLAID PRICE HISTORY CHART ──────────────────────────────── -->
  <div class="section-header">
    <div class="section-title"><span class="dot"></span> Price History Comparison</div>
    <span style="font-size:12px;color:var(--text-dim)">INR ₹ over time</span>
  </div>
  <div class="chart-wrap" style="margin-bottom:32px">
    <div class="chart-title" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
      <span>Price over time — both games</span>
      <span style="display:flex;gap:10px;font-size:12px">
        <span style="color:#1a9fff;font-weight:600">■ <?php echo htmlspecialchars($data1['g']['name']); ?></span>
        <span style="color:#9b59b6;font-weight:600">■ <?php echo htmlspecialchars($data2['g']['name']); ?></span>
      </span>
    </div>
    <canvas id="priceCompareChart" style="height:280px"></canvas>
  </div>

  <!-- ── REVIEW CHARTS SIDE BY SIDE ────────────────────────────────── -->
  <div class="section-header">
    <div class="section-title"><span class="dot"></span> Review Growth History</div>
  </div>
  <div class="compare-chart-grid" style="margin-bottom:32px">
    <div class="chart-wrap">
      <div class="chart-title"><?php echo htmlspecialchars($data1['g']['name']); ?></div>
      <canvas id="revChart1" style="height:220px"></canvas>
    </div>
    <div class="chart-wrap">
      <div class="chart-title"><?php echo htmlspecialchars($data2['g']['name']); ?></div>
      <canvas id="revChart2" style="height:220px"></canvas>
    </div>
  </div>

  <!-- ── STAT COMPARISON TABLE ──────────────────────────────────────── -->
  <div class="section-header">
    <div class="section-title"><span class="dot"></span> Head-to-Head Stats</div>
  </div>
  <div class="table-card" style="margin-bottom:32px;overflow-x:auto">
    <table>
      <tr>
        <th style="width:30%">Metric</th>
        <th style="width:30%;color:#1a9fff"><?php echo htmlspecialchars($data1['g']['name']); ?></th>
        <th style="width:30%;color:#9b59b6"><?php echo htmlspecialchars($data2['g']['name']); ?></th>
        <th style="width:10%">Winner</th>
      </tr>
      <?php
        $h2h = [
          ['Current Price',   $data1['cur']<=0?'Free':'₹'.number_format($data1['cur']),   $data2['cur']<=0?'Free':'₹'.number_format($data2['cur']),   $w_price, true],
          ['All-Time Low',    $data1['min']<=0?'—':'₹'.number_format($data1['min']),       $data2['min']<=0?'—':'₹'.number_format($data2['min']),       $w_min,   true],
          ['All-Time High',   $data1['max']<=0?'—':'₹'.number_format($data1['max']),       $data2['max']<=0?'—':'₹'.number_format($data2['max']),       [null,null], false],
          ['Avg Price',       '₹'.number_format($data1['avg'],0),                          '₹'.number_format($data2['avg'],0),                          [null,null], false],
          ['Current Discount',$data1['disc'].'%',                                          $data2['disc'].'%',                                          $w_disc,  false],
          ['Buy Score',       $data1['buy_score'].'/100',                                  $data2['buy_score'].'/100',                                  $w_buy,   false],
          ['Positive Reviews',$data1['pct'].'%',                                           $data2['pct'].'%',                                           $w_rev,   false],
          ['Total Reviews',   number_format($data1['total']),                              number_format($data2['total']),                              $w_total, false],
        ];
        foreach($h2h as [$metric, $v1, $v2, $wcs, $lower]):
          $winner_slot = null;
          if ($wcs[0]==='better') $winner_slot = 1;
          elseif ($wcs[1]==='better') $winner_slot = 2;
      ?>
      <tr>
        <td style="color:var(--text-secondary);font-weight:500"><?php echo $metric; ?></td>
        <td style="font-family:var(--font-mono);color:<?php echo ($wcs[0]==='better')?'#2ecc71':(($wcs[0]==='worse')?'#e74c3c':'#e8eaf0'); ?>"><?php echo $v1; ?></td>
        <td style="font-family:var(--font-mono);color:<?php echo ($wcs[1]==='better')?'#2ecc71':(($wcs[1]==='worse')?'#e74c3c':'#e8eaf0'); ?>"><?php echo $v2; ?></td>
        <td>
          <?php if ($winner_slot === 1): ?>
            <span style="color:#1a9fff;font-size:11px;font-weight:700">A ▲</span>
          <?php elseif ($winner_slot === 2): ?>
            <span style="color:#9b59b6;font-size:11px;font-weight:700">B ▲</span>
          <?php else: ?>
            <span style="color:var(--text-dim);font-size:11px">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <!-- Share URL -->
  <!-- <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:16px 20px;display:flex;align-items:center;gap:14px;margin-bottom:32px;flex-wrap:wrap">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="2" style="flex-shrink:0"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
    <span style="font-size:13px;color:var(--text-secondary)">Share this comparison:</span>
    <input id="shareUrl" type="text" readonly
           value="<?php echo htmlspecialchars('compare.php?g1='.$g1_id.'&g2='.$g2_id); ?>"
           style="flex:1;min-width:200px;background:var(--bg-input);border:1px solid var(--border);border-radius:var(--radius);padding:8px 12px;color:var(--text-primary);font-family:var(--font-mono);font-size:12px;cursor:text" onclick="this.select()">
    <button onclick="copyShareUrl()" style="padding:8px 18px;background:var(--accent);color:#fff;border:none;border-radius:var(--radius);font-size:12px;font-weight:600;cursor:pointer;transition:var(--transition)" onmouseover="this.style.background='#1178cc'" onmouseout="this.style.background='var(--accent)'" id="copyBtn">Copy</button>
  </div> -->

<?php endif; // $comparing ?>

</div><!-- /.page-container -->

<div id="toast-container"></div>

<script>
// ── Selectors: prevent same game on both sides ──
function updateSwap() {
  var s1 = document.getElementById('sel1');
  var s2 = document.getElementById('sel2');
  if (s1.value && s1.value === s2.value) {
    // Reset the one that was just changed (can't tell which, so just clear g2)
    // Actually: do nothing, let PHP handle it
  }
}

function swapGames() {
  var s1 = document.getElementById('sel1');
  var s2 = document.getElementById('sel2');
  var tmp = s1.value;
  s1.value = s2.value;
  s2.value = tmp;
}

// ── Share URL copy ──
function copyShareUrl() {
  var inp = document.getElementById('shareUrl');
  inp.select();
  document.execCommand('copy');
  var btn = document.getElementById('copyBtn');
  btn.textContent = '✓ Copied!';
  setTimeout(function() { btn.textContent = 'Copy'; }, 2000);
}

// ── Toast ──
function toast(msg, type) {
  var c = document.getElementById('toast-container');
  var t = document.createElement('div');
  t.className = 'toast ' + (type || 'success');
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(function() { t.remove(); }, 3000);
}

// ── Wishlist ──
var wlState = {
  1: <?php echo ($in_wl1 ? 'true' : 'false'); ?>,
  2: <?php echo ($in_wl2 ? 'true' : 'false'); ?>
};
function toggleWL(gameId, slot) {
  var action = wlState[slot] ? 'remove' : 'add';
  fetch('wishlist_action.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action='+action+'&game_id='+gameId
  }).then(r=>r.json()).then(d=>{
    if(d.success){
      wlState[slot] = !wlState[slot];
      var btn = document.getElementById('wlBtn'+slot);
      var lbl = document.getElementById('wlLbl'+slot);
      btn.classList.toggle('active', wlState[slot]);
      btn.querySelector('svg').setAttribute('fill', wlState[slot]?'currentColor':'none');
      lbl.textContent = wlState[slot] ? 'In Wishlist' : 'Wishlist';
      toast(wlState[slot] ? '\u2764 Added to wishlist' : 'Removed from wishlist');
    }
  });
}

// ── Cart ──
var ctState = {
  1: <?php echo ($in_ct1 ? 'true' : 'false'); ?>,
  2: <?php echo ($in_ct2 ? 'true' : 'false'); ?>
};
function toggleCart(gameId, slot) {
  var action = ctState[slot] ? 'remove' : 'add';
  fetch('cart_action.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action='+action+'&game_id='+gameId
  }).then(r=>r.json()).then(d=>{
    if(d.success){
      ctState[slot] = !ctState[slot];
      var btn = document.getElementById('ctBtn'+slot);
      var lbl = document.getElementById('ctLbl'+slot);
      btn.classList.toggle('active', ctState[slot]);
      lbl.textContent = ctState[slot] ? 'In Cart' : 'Add to Cart';
      toast(ctState[slot] ? '\ud83d\uded2 Added to cart' : 'Removed from cart');
    }
  });
}

<?php if ($comparing): ?>
// ── Charts ──
const CHART_DEFAULTS = {
  responsive:true, maintainAspectRatio:false,
  plugins:{ legend:{display:false}, tooltip:{backgroundColor:'#1a1e2a',borderColor:'#252a38',borderWidth:1} },
  scales:{
    x:{ grid:{color:'rgba(255,255,255,0.04)'}, ticks:{color:'#525970',maxTicksLimit:10} },
    y:{ grid:{color:'rgba(255,255,255,0.04)'}, ticks:{color:'#525970'} }
  }
};

// ── Merged price labels for overlay chart ──
var allDates = [...new Set([
  ...<?php echo json_encode($data1['dates']); ?>,
  ...<?php echo json_encode($data2['dates']); ?>
])].sort();

// Build price maps for step-before interpolation
function buildPriceMap(dates, prices) {
  var m = {};
  for (var i=0; i<dates.length; i++) m[dates[i]] = prices[i];
  return m;
}
var pmap1 = buildPriceMap(<?php echo json_encode($data1['dates']); ?>, <?php echo json_encode($data1['prices']); ?>);
var pmap2 = buildPriceMap(<?php echo json_encode($data2['dates']); ?>, <?php echo json_encode($data2['prices']); ?>);

// Step-interpolate: carry last known price for each merged date
function interpolatePrices(dates, priceMap) {
  var out = []; var last = null;
  for (var i=0; i<dates.length; i++) {
    if (priceMap[dates[i]] !== undefined) last = priceMap[dates[i]];
    out.push(last);
  }
  return out;
}

var p1interp = interpolatePrices(allDates, pmap1);
var p2interp = interpolatePrices(allDates, pmap2);

new Chart(document.getElementById('priceCompareChart'), {
  type: 'line',
  data: {
    labels: allDates,
    datasets: [
      {
        label: <?php echo json_encode($data1['g']['name']); ?>,
        data: p1interp,
        borderColor: '#1a9fff',
        backgroundColor: 'rgba(26,159,255,0.07)',
        fill: false,
        stepped: 'before',
        pointRadius: 2,
        borderWidth: 2.5
      },
      {
        label: <?php echo json_encode($data2['g']['name']); ?>,
        data: p2interp,
        borderColor: '#9b59b6',
        backgroundColor: 'rgba(155,89,182,0.07)',
        fill: false,
        stepped: 'before',
        pointRadius: 2,
        borderWidth: 2.5
      }
    ]
  },
  options: {
    ...CHART_DEFAULTS,
    plugins: {
      legend: {
        display: true,
        labels: { color: '#e8eaf0', boxWidth: 14, padding: 18 }
      },
      tooltip: {
        backgroundColor: '#1a1e2a',
        borderColor: '#252a38',
        borderWidth: 1,
        callbacks: {
          label: ctx => ctx.dataset.label + ': ₹' + (ctx.parsed.y ?? 'N/A')
        }
      }
    }
  }
});

// Review charts
function makeRevChart(canvasId, dates, pos, neg) {
  new Chart(document.getElementById(canvasId), {
    type: 'bar',
    data: {
      labels: dates,
      datasets: [
        { label:'Positive', data:pos, backgroundColor:'rgba(46,204,113,0.65)', borderColor:'#2ecc71', borderWidth:1 },
        { label:'Negative', data:neg.map(v=>-v), backgroundColor:'rgba(231,76,60,0.65)', borderColor:'#e74c3c', borderWidth:1 }
      ]
    },
    options: {
      ...CHART_DEFAULTS,
      plugins: {
        legend: { display:true, labels:{ color:'#e8eaf0', boxWidth:12 } },
        tooltip: {
          backgroundColor:'#1a1e2a',borderColor:'#252a38',borderWidth:1,
          callbacks:{ label:ctx=>ctx.dataset.label+': '+Math.abs(ctx.parsed.y) }
        }
      },
      scales: {
        x:{ ...CHART_DEFAULTS.scales.x, stacked:true },
        y:{ ...CHART_DEFAULTS.scales.y, stacked:true, ticks:{ color:'#525970', callback:v=>Math.abs(v) } }
      }
    }
  });
}
makeRevChart('revChart1',
  <?php echo json_encode($data1['rh_dates']); ?>,
  <?php echo json_encode($data1['rh_pos']); ?>,
  <?php echo json_encode($data1['rh_neg']); ?>
);
makeRevChart('revChart2',
  <?php echo json_encode($data2['rh_dates']); ?>,
  <?php echo json_encode($data2['rh_pos']); ?>,
  <?php echo json_encode($data2['rh_neg']); ?>
);
<?php endif; ?>
</script>

</body>
</html>