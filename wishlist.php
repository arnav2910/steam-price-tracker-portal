<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
requireLogin('wishlist.php');

$user_id = currentUserId();

$games_res = mysqli_query($conn,
    "SELECT g.id, g.name, g.category,
            (SELECT price FROM price_history WHERE game_id=g.id ORDER BY price_date DESC LIMIT 1) AS cur_price,
            (SELECT MIN(price) FROM price_history WHERE game_id=g.id) AS min_price,
            (SELECT MAX(price) FROM price_history WHERE game_id=g.id) AS max_price,
            r.pos_reviews, r.neg_reviews,
            w.added_at
     FROM wishlist w
     JOIN games g ON g.id = w.game_id
     LEFT JOIN review_history r ON r.game_id = g.id
         AND r.review_date = (SELECT MAX(review_date) FROM review_history WHERE game_id=g.id)
     WHERE w.user_id = $user_id
     ORDER BY w.added_at DESC"
);

$active_nav = 'wishlist';
$page_title = 'My Wishlist';
include 'includes/header.php';
?>

<div class="page-container">
  <div class="section-header">
    <div class="section-title"><span class="dot"></span> My Wishlist</div>
    <a href="index.php" class="section-link">← Browse Games</a>
  </div>

  <?php if (mysqli_num_rows($games_res) === 0): ?>
  <div class="empty-state">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--text-dim)">
      <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
    </svg>
    <p>Your wishlist is empty.</p>
    <a href="index.php" class="btn-primary">Browse Games</a>
  </div>
  <?php else: ?>
  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>Game</th>
          <th>Categories</th>
          <th>Current Price</th>
          <th>All-Time Low</th>
          <th>Reviews</th>
          <th>Added</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php while ($g = mysqli_fetch_assoc($games_res)):
        $cur   = floatval($g['cur_price']);
        $min   = floatval($g['min_price']);
        $max   = floatval($g['max_price']);
        $disc  = ($max > 0 && $cur < $max) ? round(($max - $cur) / $max * 100) : 0;
        $tot   = ($g['pos_reviews'] + $g['neg_reviews']);
        $pct   = $tot > 0 ? round($g['pos_reviews'] / $tot * 100) : 0;
      ?>
      <tr>
        <td>
          <a href="game.php?id=<?php echo $g['id']; ?>" style="color:var(--text-primary);font-weight:600;text-decoration:none">
            <?php echo htmlspecialchars($g['name']); ?>
          </a>
          <?php if ($disc > 0): ?>
          <span class="badge-discount">-<?php echo $disc; ?>%</span>
          <?php endif; ?>
        </td>
        <td style="color:var(--text-secondary);font-size:12px"><?php echo htmlspecialchars(str_replace('|', ', ', $g['category'])); ?></td>
        <td>
          <?php if ($cur == 0): ?>
            <span class="price-free">Free</span>
          <?php else: ?>
            <span style="font-family:var(--font-mono);color:var(--steam-blue)">₹<?php echo number_format($cur, 2); ?></span>
          <?php endif; ?>
        </td>
        <td style="font-family:var(--font-mono);font-size:12px;color:var(--text-secondary)">
          <?php echo $min > 0 ? '₹'.number_format($min, 2) : '—'; ?>
        </td>
        <td>
          <?php if ($tot > 0): ?>
          <span style="font-size:12px;color:<?php echo $pct >= 70 ? 'var(--steam-blue)' : 'var(--red)'; ?>">
            <?php echo $pct; ?>% positive
          </span>
          <?php else: ?><span style="color:var(--text-dim)">—</span><?php endif; ?>
        </td>
        <td style="font-size:12px;color:var(--text-dim)"><?php echo date('M j, Y', strtotime($g['added_at'])); ?></td>
        <td>
          <div style="display:flex;gap:8px;align-items:center">
            <a href="game.php?id=<?php echo $g['id']; ?>" class="analyze-link">Analyze →</a>
            <button class="btn-icon-danger" onclick="removeWishlist(<?php echo $g['id']; ?>, this)" title="Remove from Wishlist">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/>
              </svg>
            </button>
          </div>
        </td>
      </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div id="toast-container"></div>

<script>
function toast(msg, type) {
  var c = document.getElementById('toast-container');
  var t = document.createElement('div');
  t.className = 'toast ' + (type||'success');
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(function(){ t.remove(); }, 3000);
}
function removeWishlist(gameId, btn) {
  var row = btn.closest('tr');
  fetch('wishlist_action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=remove&game_id=' + gameId
  }).then(r => r.json()).then(d => {
    if (d.success) {
      row.style.opacity = '0';
      row.style.transition = 'opacity 0.3s';
      setTimeout(() => row.remove(), 300);
      toast('Removed from wishlist');
    }
  });
}
</script>
</body>
</html>
