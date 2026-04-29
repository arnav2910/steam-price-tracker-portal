<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin('cart.php');

$user_id = currentUserId();

$games_res = mysqli_query($conn,
    "SELECT g.id, g.name, g.category,
            (SELECT price FROM price_history WHERE game_id=g.id ORDER BY price_date DESC LIMIT 1) AS cur_price,
            (SELECT MAX(price) FROM price_history WHERE game_id=g.id) AS max_price,
            c.added_at
     FROM cart c
     JOIN games g ON g.id = c.game_id
     WHERE c.user_id = $user_id
     ORDER BY c.added_at DESC"
);

$games = [];
$total = 0;
while ($g = mysqli_fetch_assoc($games_res)) {
    $games[] = $g;
    $total += floatval($g['cur_price']);
}

$active_nav = 'cart';
$page_title = 'My Cart';
include 'includes/header.php';
?>

<div class="page-container">
  <div class="section-header">
    <div class="section-title"><span class="dot"></span> My Cart</div>
    <a href="index.php" class="section-link">← Continue Shopping</a>
  </div>

  <?php if (empty($games)): ?>
  <div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--text-dim)">
      <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
      <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
    </svg>
    <p>Your cart is empty.</p>
    <a href="index.php" class="btn-primary">Browse Games</a>
  </div>
  <?php else: ?>

  <div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start">

    <div class="table-card">
      <table>
        <thead>
          <tr><th>Game</th><th>Categories</th><th>Price</th><th>Discount</th><th></th></tr>
        </thead>
        <tbody id="cartBody">
        <?php foreach ($games as $g):
          $cur  = floatval($g['cur_price']);
          $max  = floatval($g['max_price']);
          $disc = ($max > 0 && $cur < $max) ? round(($max - $cur) / $max * 100) : 0;
        ?>
        <tr id="cart-row-<?php echo $g['id']; ?>">
          <td>
            <a href="game.php?id=<?php echo $g['id']; ?>" style="color:var(--text-primary);font-weight:600;text-decoration:none">
              <?php echo htmlspecialchars($g['name']); ?>
            </a>
          </td>
          <td style="font-size:12px;color:var(--text-secondary)"><?php echo htmlspecialchars(str_replace('|', ', ', $g['category'])); ?></td>
          <td class="cart-price-cell" data-price="<?php echo $cur; ?>">
            <?php if ($cur == 0): ?>
              <span class="price-free">Free</span>
            <?php else: ?>
              <span style="font-family:var(--font-mono);color:var(--steam-blue)">₹<?php echo number_format($cur, 2); ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($disc > 0): ?><span class="badge-discount">-<?php echo $disc; ?>%</span><?php else: ?>—<?php endif; ?>
          </td>
          <td>
            <button class="btn-icon-danger" onclick="removeCart(<?php echo $g['id']; ?>, <?php echo $cur; ?>)" title="Remove">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                <path d="M10 11v6M14 11v6"/>
              </svg>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="cart-summary-card">
      <div class="cart-summary-title">Order Summary</div>
      <div class="cart-summary-row">
        <span>Items (<?php echo count($games); ?>)</span>
        <span id="totalDisplay">₹<?php echo number_format($total, 2); ?></span>
      </div>
      <div class="cart-summary-divider"></div>
      <div class="cart-summary-total">
        <span>Total</span>
        <span id="grandTotal">₹<?php echo number_format($total, 2); ?></span>
      </div>
      <p style="font-size:11px;color:var(--text-dim);margin-top:10px;line-height:1.6">
        Prices shown in INR. Actual purchase is done through Steam's storefront.
      </p>
      <a href="https://store.steampowered.com" target="_blank" rel="noopener noreferrer"
         class="btn-primary" style="width:100%;justify-content:center;margin-top:16px">
        Go to Steam Store →
      </a>
    </div>

  </div>
  <?php endif; ?>
</div>

<div id="toast-container"></div>

<script>
var runningTotal = <?php echo json_encode(floatval($total)); ?>;

function toast(msg, type) {
  var c = document.getElementById('toast-container');
  var t = document.createElement('div');
  t.className = 'toast ' + (type||'success');
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(function(){ t.remove(); }, 3000);
}

function removeCart(gameId, price) {
  var row = document.getElementById('cart-row-' + gameId);
  fetch('cart_action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=remove&game_id=' + gameId
  }).then(r => r.json()).then(d => {
    if (d.success) {
      row.style.opacity = '0';
      row.style.transition = 'opacity 0.3s';
      setTimeout(() => row.remove(), 300);
      runningTotal = Math.max(0, runningTotal - price);
      var fmt = '₹' + runningTotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
      document.getElementById('totalDisplay').textContent = fmt;
      document.getElementById('grandTotal').textContent   = fmt;
      toast('Removed from cart');
    }
  });
}
</script>
</body>
</html>
