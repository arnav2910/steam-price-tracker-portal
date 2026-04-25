<?php
include 'includes/db.php';
include 'includes/logic.php';
require_once 'includes/auth.php';

$id       = (int)$_GET['id'];
$game_res = mysqli_query($conn,"SELECT * FROM games WHERE id=$id");
$game     = mysqli_fetch_assoc($game_res);
if(!$game) die("Game not found.");

// Buy Score (original logic)
$buy_score = getBuyScore($conn,$id);
function buyLabel($s){
    if($s>=85) return ['label'=>'Excellent Buy','color'=>'#2ecc71'];
    if($s>=70) return ['label'=>'Good Value',   'color'=>'#27ae60'];
    if($s>=55) return ['label'=>'Fair Deal',     'color'=>'#f39c12'];
    if($s>=35) return ['label'=>'Wait a Bit',    'color'=>'#e67e22'];
    return             ['label'=>'Avoid',         'color'=>'#e74c3c'];
}
$bl = buyLabel($buy_score);

// Price data
$p_res = mysqli_query($conn,"SELECT price_date,price FROM price_history WHERE game_id=$id ORDER BY price_date ASC");
$p_dates=[]; $p_vals=[];
while($r=mysqli_fetch_assoc($p_res)){ $p_dates[]=$r['price_date']; $p_vals[]=$r['price']; }
$cur_price = end($p_vals) ?: 0;
$max_price = $p_vals ? max($p_vals) : 0;
$min_price = $p_vals ? min($p_vals) : 0;
$disc = ($max_price>0 && $cur_price<$max_price) ? round(($max_price-$cur_price)/$max_price*100) : 0;

// Review data - sum ALL rows for all-time total
$rev_res = mysqli_query($conn,"SELECT SUM(pos_reviews) as pos, SUM(neg_reviews) as neg FROM review_history WHERE game_id=$id");
$latest  = mysqli_fetch_assoc($rev_res);
$pos     = $latest['pos'] ?? 0;
$neg     = $latest['neg'] ?? 0;
$total   = $pos+$neg;
$pct     = $total>0 ? round($pos/$total*100) : 0;
$rev_label='Mixed'; $rev_color='var(--yellow)';
if($pct>=95){ $rev_label='Overwhelmingly Positive'; $rev_color='var(--steam-blue)'; }
elseif($pct>=80){ $rev_label='Very Positive'; $rev_color='var(--steam-blue)'; }
elseif($pct>=70){ $rev_label='Positive'; $rev_color='var(--steam-blue)'; }
elseif($pct<40) { $rev_label='Negative'; $rev_color='var(--red)'; }

// Review history chart
$rh_res = mysqli_query($conn,"SELECT review_date,pos_reviews,neg_reviews FROM review_history WHERE game_id=$id ORDER BY review_date ASC");
$rh_dates=[]; $rh_pos=[]; $rh_neg=[];
while($r=mysqli_fetch_assoc($rh_res)){ $rh_dates[]=$r['review_date']; $rh_pos[]=$r['pos_reviews']; $rh_neg[]=$r['neg_reviews']; }

// Waterfall Recommendations (original logic preserved)
$tags_array    = explode('|',$game['category']);
$primary_tag   = mysqli_real_escape_string($conn,trim($tags_array[0]));
$cur_cluster   = mysqli_real_escape_string($conn,$game['cluster_label']?:'Uncategorized');
$cur_id        = $game['id'];

$rec_sql = "SELECT g.id,g.name,(SELECT price FROM price_history WHERE game_id=g.id ORDER BY price_date DESC LIMIT 1) as price
            FROM games g WHERE cluster_label='$cur_cluster' AND category LIKE '%$primary_tag%' AND id!=$cur_id LIMIT 4";
$rec_res = mysqli_query($conn,$rec_sql);
$match_type = "pricing behavior and genre";

if(mysqli_num_rows($rec_res)==0){
    $rec_sql = "SELECT g.id,g.name,(SELECT price FROM price_history WHERE game_id=g.id ORDER BY price_date DESC LIMIT 1) as price
                FROM games g WHERE category LIKE '%$primary_tag%' AND id!=$cur_id LIMIT 4";
    $rec_res = mysqli_query($conn,$rec_sql);
    $match_type = "similar genres ($primary_tag)";
}
if(mysqli_num_rows($rec_res)==0){
    $rec_sql = "SELECT g.id,g.name,(SELECT price FROM price_history WHERE game_id=g.id ORDER BY price_date DESC LIMIT 1) as price
                FROM games g WHERE cluster_label='$cur_cluster' AND id!=$cur_id LIMIT 4";
    $rec_res = mysqli_query($conn,$rec_sql);
    $match_type = "similar market pricing ($cur_cluster)";
}


// Wishlist & Cart status for this game
$_uid = currentUserId();
$_in_wishlist = false;
$_in_cart     = false;
if ($_uid) {
    $_wl = mysqli_query($conn, "SELECT 1 FROM wishlist WHERE user_id=$_uid AND game_id=$id LIMIT 1");
    $_in_wishlist = mysqli_num_rows($_wl) > 0;
    $_ct = mysqli_query($conn, "SELECT 1 FROM cart WHERE user_id=$_uid AND game_id=$id LIMIT 1");
    $_in_cart = mysqli_num_rows($_ct) > 0;
}

$active_nav = 'home';
$page_title = $game['name'];
$extra_head = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>';
include 'includes/header.php';
?>

<div class="page-container">

  <a href="javascript:history.back()" class="btn-reset">← Back</a>

  <!-- DETAIL HEADER -->
  <div class="detail-header">
    <?php
      $gi_file = 'steam_game_headers_by_name/' . $game['name'] . '.jpg';
      $gi_has  = file_exists($gi_file);
    ?>
    <?php if($gi_has): ?>
    <div class="detail-hero-img"><img src="<?php echo htmlspecialchars($gi_file); ?>" alt="<?php echo htmlspecialchars($game['name']); ?>"></div>
    <?php else: ?>
    <div class="detail-hero-placeholder"><?php echo htmlspecialchars($game['name']); ?></div>
    <?php endif; ?>

    <div class="detail-info">
      <h1 class="detail-title"><?php echo strtoupper(htmlspecialchars($game['name'])); ?></h1>

      <!-- Tags + Cluster -->
      <div class="detail-meta-row">
        <?php foreach(explode('|',$game['category']) as $t):
              $t=trim($t); if(!$t) continue; ?>
        <span class="detail-tag"><?php echo htmlspecialchars($t); ?></span>
        <?php endforeach; ?>
        <?php if($game['cluster_label']): ?>
        <span class="detail-tag cluster"><?php echo htmlspecialchars($game['cluster_label']); ?></span>
        <?php endif; ?>
        <?php if($game['is_anomaly']): ?>
        <span class="anomaly-badge">⭐ Hidden Gem</span>
        <?php endif; ?>
      </div>

      <!-- Price block -->
      <div class="detail-price-block">
        <div>
          <div class="detail-current-price" style="<?php if($cur_price==0) echo 'color:var(--green)'; ?>">
            <?php echo $cur_price==0 ? 'Free to Play' : '₹'.number_format($cur_price,2); ?>
          </div>
          <?php if($disc>0): ?>
          <div class="detail-base-price">₹<?php echo number_format($max_price,2); ?> original</div>
          <?php endif; ?>
        </div>
        <?php if($disc>0): ?>
        <div class="detail-discount-badge">-<?php echo $disc; ?>%</div>
        <?php endif; ?>
      </div>

      <!-- Buy Score -->
      <div class="buy-rec-card">
        <div class="buy-rec-title">🤖 Buy Recommendation</div>
        <div class="buy-meter">
          <div class="buy-meter-indicator" style="left:<?php echo $buy_score; ?>%"></div>
        </div>
        <div class="buy-meter-labels">
          <span>Avoid</span><span>Wait</span><span>Fair</span><span>Good</span><span>Excellent</span>
        </div>
        <div class="buy-rec-label" style="color:<?php echo $bl['color']; ?>"><?php echo $bl['label']; ?></div>
        <div class="buy-meta">All-time low: ₹<?php echo number_format($min_price,2); ?> &nbsp;·&nbsp; Score: <?php echo $buy_score; ?>/100</div>
      </div>


      <!-- Wishlist & Cart Actions -->
      <div class="game-action-row">
        <?php if(isLoggedIn()): ?>
          <button class="btn-action-wl<?php if($_in_wishlist) echo ' active'; ?>"
                  id="wlBtn" onclick="toggleWishlist(<?php echo $id; ?>)">
            <svg width="15" height="15" viewBox="0 0 24 24"
                 fill="<?php echo $_in_wishlist ? 'currentColor' : 'none'; ?>"
                 stroke="currentColor" stroke-width="2">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            <span id="wlLabel"><?php echo $_in_wishlist ? 'In Wishlist' : 'Add to Wishlist'; ?></span>
          </button>
          <button class="btn-action-cart<?php if($_in_cart) echo ' active'; ?>"
                  id="cartBtn" onclick="toggleCart(<?php echo $id; ?>)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <span id="cartLabel"><?php echo $_in_cart ? 'In Cart' : 'Add to Cart'; ?></span>
          </button>
        <?php else: ?>
          <a href="login.php?redirect=<?php echo urlencode('game.php?id='.$id); ?>" class="btn-action-wl">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            Sign in to Wishlist
          </a>
          <a href="login.php?redirect=<?php echo urlencode('game.php?id='.$id); ?>" class="btn-action-cart">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            Sign in to Add to Cart
          </a>
        <?php endif; ?>
      </div>

      <!-- Review Bar -->
      <div class="review-bar-wrap">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
          <strong style="color:<?php echo $rev_color; ?>;font-size:14px"><?php echo $rev_label; ?></strong>
          <span style="font-family:var(--font-mono);font-size:12px;color:var(--text-secondary)"><?php echo number_format($total); ?> reviews</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-secondary);margin-bottom:6px">
          <span style="color:var(--steam-blue)"><?php echo $pct; ?>% Positive</span>
          <span><?php echo 100-$pct; ?>% Negative</span>
        </div>
        <div class="review-bar-track">
          <div class="review-bar-fill" style="width:<?php echo $pct; ?>%"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- CHARTS -->
  <div class="section-header">
    <div class="section-title"><span class="dot"></span> Price History (INR ₹)</div>
  </div>
  <div class="chart-wrap">
    <div class="chart-title">Price over time</div>
    <canvas id="priceChart"></canvas>
  </div>

  <div class="section-header">
    <div class="section-title"><span class="dot"></span> Review Growth History</div>
  </div>
  <div class="chart-wrap">
    <div class="chart-title">Positive vs Negative reviews over time</div>
    <canvas id="reviewChart"></canvas>
  </div>

  <!-- RECOMMENDATIONS -->
  <div class="section-header">
    <div class="section-title"><span class="dot"></span> Because You Viewed This</div>
    <span style="font-size:12px;color:var(--text-dim)">Based on <?php echo htmlspecialchars($match_type); ?></span>
  </div>
  <div class="rec-grid" style="margin-bottom:32px">
    <?php if(mysqli_num_rows($rec_res)>0):
      while($rec=mysqli_fetch_assoc($rec_res)):
        $rp = $rec['price']??0; ?>
    <?php
      $ri = $rec['name'];
      $ri_src = 'steam_game_headers_by_name/' . $ri . '.jpg';
      $ri_has = file_exists($ri_src);
    ?>
    <a href="game.php?id=<?php echo $rec['id']; ?>" class="rec-card">
      <?php if($ri_has): ?>
      <div class="rec-card-img"><img src="<?php echo htmlspecialchars($ri_src); ?>" alt="<?php echo htmlspecialchars($ri); ?>"></div>
      <?php else: ?>
      <div class="rec-card-img rec-card-img-placeholder"><?php echo htmlspecialchars($ri); ?></div>
      <?php endif; ?>
      <div class="rec-card-body">
        <strong><?php echo htmlspecialchars($rec['name']); ?></strong>
        <span class="rec-price<?php if($rp==0) echo ' free'; ?>">
          <?php echo $rp==0 ? 'Free to Play' : '₹'.number_format($rp); ?>
        </span>
      </div>
    </a>
    <?php endwhile; else: ?>
    <p style="color:var(--text-secondary)">Import more games to see recommendations.</p>
    <?php endif; ?>
  </div>

</div>

<script>
const CHART_DEFAULTS = {
  responsive:true, maintainAspectRatio:false,
  plugins:{ legend:{display:false}, tooltip:{backgroundColor:'#1a1e2a',borderColor:'#252a38',borderWidth:1} },
  scales:{
    x:{ grid:{color:'rgba(255,255,255,0.04)'}, ticks:{color:'#525970',maxTicksLimit:10} },
    y:{ grid:{color:'rgba(255,255,255,0.04)'}, ticks:{color:'#525970'} }
  }
};

new Chart(document.getElementById('priceChart'),{
  type:'line',
  data:{
    labels:<?php echo json_encode($p_dates); ?>,
    datasets:[{ data:<?php echo json_encode($p_vals); ?>, borderColor:'#1a9fff', backgroundColor:'rgba(26,159,255,0.08)', fill:true, stepped:'before', pointRadius:2, borderWidth:2 }]
  },
  options:CHART_DEFAULTS
});

new Chart(document.getElementById('reviewChart'),{
  type:'bar',
  data:{
    labels:<?php echo json_encode($rh_dates); ?>,
    datasets:[
      { label:'Positive', data:<?php echo json_encode($rh_pos); ?>, backgroundColor:'rgba(46,204,113,0.7)', borderColor:'#2ecc71', borderWidth:1 },
      { label:'Negative', data:<?php echo json_encode(array_map(fn($v)=>-$v,$rh_neg)); ?>, backgroundColor:'rgba(231,76,60,0.7)', borderColor:'#e74c3c', borderWidth:1 }
    ]
  },
  options:{
    ...CHART_DEFAULTS,
    plugins:{
      legend:{display:true,labels:{color:'#e8eaf0',boxWidth:12}},
      tooltip:{backgroundColor:'#1a1e2a',borderColor:'#252a38',borderWidth:1,callbacks:{label:ctx=>ctx.dataset.label+': '+Math.abs(ctx.parsed.y)}}
    },
    scales:{
      x:{...CHART_DEFAULTS.scales.x,stacked:true},
      y:{...CHART_DEFAULTS.scales.y,stacked:true,ticks:{color:'#525970',callback:v=>Math.abs(v)}}
    }
  }
});
</script>

<div id="toast-container"></div>
<script>
function toast(msg, type) {
  var c = document.getElementById('toast-container');
  var t = document.createElement('div');
  t.className = 'toast ' + (type || 'success');
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(function(){ t.remove(); }, 3000);
}

var _wlActive   = <?php echo $_in_wishlist ? 'true' : 'false'; ?>;
var _cartActive = <?php echo $_in_cart     ? 'true' : 'false'; ?>;

function toggleWishlist(gameId) {
  var action = _wlActive ? 'remove' : 'add';
  fetch('wishlist_action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=' + action + '&game_id=' + gameId
  }).then(r => r.json()).then(d => {
    if (d.success) {
      _wlActive = !_wlActive;
      var btn = document.getElementById('wlBtn');
      var lbl = document.getElementById('wlLabel');
      btn.classList.toggle('active', _wlActive);
      btn.querySelector('svg').setAttribute('fill', _wlActive ? 'currentColor' : 'none');
      lbl.textContent = _wlActive ? 'In Wishlist' : 'Add to Wishlist';
      toast(_wlActive ? '\u2764 Added to wishlist' : 'Removed from wishlist');
    }
  });
}

function toggleCart(gameId) {
  var action = _cartActive ? 'remove' : 'add';
  fetch('cart_action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=' + action + '&game_id=' + gameId
  }).then(r => r.json()).then(d => {
    if (d.success) {
      _cartActive = !_cartActive;
      var btn = document.getElementById('cartBtn');
      var lbl = document.getElementById('cartLabel');
      btn.classList.toggle('active', _cartActive);
      lbl.textContent = _cartActive ? 'In Cart' : 'Add to Cart';
      toast(_cartActive ? '\ud83d\uded2 Added to cart' : 'Removed from cart');
    }
  });
}
</script>
</body>
</html>
