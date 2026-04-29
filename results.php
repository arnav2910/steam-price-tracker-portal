<?php
include 'includes/db.php';
$q       = mysqli_real_escape_string($conn, $_GET['q'] ?? '');
$insight = $_GET['insight'] ?? '';
$active_nav = 'home';
$page_title = 'Results';

$filter = !empty($q) ? " AND (g.name LIKE '%$q%' OR g.category LIKE '%$q%')" : "";
$query  = "SELECT g.*,p.price,r.pos_reviews,r.neg_reviews
           FROM games g
           JOIN price_history p  ON g.id=p.game_id  AND p.price_date=(SELECT MAX(price_date) FROM price_history WHERE game_id=g.id)
           LEFT JOIN (SELECT game_id, SUM(pos_reviews) as pos_reviews, SUM(neg_reviews) as neg_reviews FROM review_history GROUP BY game_id) r ON g.id=r.game_id
           WHERE 1=1 $filter";

$insight_label = '';
if(!empty($insight)){
    switch($insight){
        case 'cheapest':     $query .= " ORDER BY p.price ASC LIMIT 10";       $insight_label='Cheapest Game'; break;
        case 'expensive':    $query .= " ORDER BY p.price DESC LIMIT 10";      $insight_label='Most Expensive'; break;
        case 'high_reviews': $query .= " ORDER BY r.pos_reviews DESC LIMIT 10";$insight_label='Most Positive Reviews'; break;
        case 'neg_reviews':  $query .= " ORDER BY r.neg_reviews DESC LIMIT 10";$insight_label='Most Negative Reviews'; break;
        case 'price_drop':
            $query="SELECT g.*,p.price,(MAX(ph.price)-MIN(ph.price)) as drop_amt FROM games g
                    JOIN price_history p ON g.id=p.game_id JOIN price_history ph ON g.id=ph.game_id
                    WHERE 1=1 $filter GROUP BY g.id ORDER BY drop_amt DESC LIMIT 1";
            $insight_label='Biggest Price Drop'; break;
    }
}
$result = mysqli_query($conn,$query);
include 'includes/header.php';
?>

<div class="page-container">
  <div class="filter-card">
    <form action="results.php" method="GET">
      <span class="filter-label">Search:</span>
      <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Game name or category…" style="flex:1;min-width:200px">
      <button type="submit" class="btn-primary">Search</button>
      <a href="index.php" class="btn-secondary">← Home</a>
    </form>
  </div>

  <div class="section-header">
    <div class="section-title">
      <span class="dot"></span>
      <?php if($insight_label) echo htmlspecialchars($insight_label);
            elseif($q)         echo 'Results for "'.htmlspecialchars($q).'"';
            else               echo 'All Games'; ?>
    </div>
    <?php if($result): ?>
    <span style="font-family:var(--font-mono);font-size:12px;color:var(--text-dim)"><?php echo mysqli_num_rows($result); ?> result(s)</span>
    <?php endif; ?>
  </div>

  <div class="table-card">
    <table>
      <tr>
        <th>Game</th><th>Categories</th><th>Current Price</th><th>Action</th>
      </tr>
      <?php if($result && mysqli_num_rows($result)>0): ?>
      <?php while($row=mysqli_fetch_assoc($result)): ?>
      <tr>
        <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
        <td style="color:var(--text-secondary)"><?php echo htmlspecialchars(str_replace('|',', ',$row['category'])); ?></td>
        <td><?php if($row['price']==0) echo '<span class="price-free">Free</span>';
                 else echo '<span style="font-family:var(--font-mono)">₹'.number_format($row['price'],2).'</span>'; ?></td>
        <td><a href="game.php?id=<?php echo $row['id']; ?>" class="analyze-link">Analyze →</a></td>
      </tr>
      <?php endwhile; ?>
      <?php else: ?>
      <tr><td colspan="4" style="text-align:center;padding:48px;color:var(--text-secondary)">No results found.</td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>
</body>
</html>
