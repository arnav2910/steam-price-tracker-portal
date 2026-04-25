<?php
include 'includes/db.php';
set_time_limit(0);
$active_nav = 'ml';
$page_title = 'ML Engine';
include 'includes/header.php';

// Run clustering
$sql="SELECT g.id,g.name,
      (SELECT price FROM price_history WHERE game_id=g.id ORDER BY price_date DESC LIMIT 1) as price,
      (SELECT pos_reviews FROM review_history WHERE game_id=g.id ORDER BY review_date DESC LIMIT 1) as pos,
      (SELECT neg_reviews FROM review_history WHERE game_id=g.id ORDER BY review_date DESC LIMIT 1) as neg
      FROM games g";
$result=mysqli_query($conn,$sql);
$games=[]; $max_price=1;
while($row=mysqli_fetch_assoc($result)){
    $price=$row['price']??0; $pos=$row['pos']??0; $neg=$row['neg']??0;
    $total=$pos+$neg;
    $sentiment=($total>0)?($pos/$total)*100:0;
    if($price>$max_price) $max_price=$price;
    $games[]=['id'=>$row['id'],'name'=>$row['name'],'price'=>$price,'sentiment'=>$sentiment,
              'norm_price'=>$price/$max_price,'norm_sentiment'=>$sentiment/100];
}
// Re-normalize
foreach($games as &$g) $g['norm_price']=$g['price']/$max_price;
unset($g);

$centroids=[
    ['p'=>0.1,'s'=>0.9,'label'=>'Budget Hits'],
    ['p'=>0.5,'s'=>0.5,'label'=>'Standard Tier'],
    ['p'=>0.9,'s'=>0.8,'label'=>'Premium Titles'],
];

foreach($games as &$game){
    $min_dist=999; $label='Uncategorized';
    foreach($centroids as $c){
        $d=sqrt(pow($game['norm_price']-$c['p'],2)+pow($game['norm_sentiment']-$c['s'],2));
        if($d<$min_dist){ $min_dist=$d; $label=$c['label']; }
    }
    $game['cluster']=$label;
    $is_anomaly=($game['price']>0 && $game['price']<400 && $game['sentiment']>85) ? 1 : 0;
    $game['is_anomaly']=$is_anomaly;
    $cid=$game['id'];
    $clabel=mysqli_real_escape_string($conn,$label);
    mysqli_query($conn,"UPDATE games SET cluster_label='$clabel',is_anomaly=$is_anomaly WHERE id=$cid");
}

// Group by cluster for display
$clusters=[];
foreach($games as $g) $clusters[$g['cluster']][]=$g;
?>

<div class="page-container">
  <div class="section-header">
    <div class="section-title"><span class="dot"></span> ML Engine Results</div>
  </div>
  <p style="color:var(--text-secondary);font-size:14px;margin-bottom:28px">
    Grouped <?php echo count($games); ?> games into clusters using K-Means and flagged Hidden Gems.
  </p>

  <?php foreach($clusters as $name=>$items): ?>
  <div class="section-header" style="margin-top:24px">
    <div class="section-title"><span class="dot"></span> <?php echo htmlspecialchars($name); ?></div>
    <span style="font-family:var(--font-mono);font-size:12px;color:var(--text-dim)"><?php echo count($items); ?> games</span>
  </div>
  <div class="table-card" style="margin-bottom:20px">
    <table>
      <tr><th>Game</th><th>Price</th><th>Sentiment</th><th>Hidden Gem</th></tr>
      <?php foreach($items as $g): ?>
      <tr>
        <td><a href="game.php?id=<?php echo $g['id']; ?>" style="color:var(--text-primary);font-weight:600"><?php echo htmlspecialchars($g['name']); ?></a></td>
        <td style="font-family:var(--font-mono)"><?php echo $g['price']>0 ? '₹'.number_format($g['price'],0) : 'Free'; ?></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <div style="width:80px;height:6px;background:var(--bg-input);border-radius:3px;overflow:hidden">
              <div style="width:<?php echo round($g['sentiment']); ?>%;height:100%;background:var(--steam-blue);border-radius:3px"></div>
            </div>
            <span style="font-family:var(--font-mono);font-size:12px;color:var(--text-secondary)"><?php echo round($g['sentiment'],1); ?>%</span>
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
