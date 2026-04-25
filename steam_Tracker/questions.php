<?php
include 'includes/db.php';
$active_nav = 'insights';
$page_title = 'Insights';

$tag_res = mysqli_query($conn,"SELECT DISTINCT category FROM games");
$all_tags=[];
while($r=mysqli_fetch_assoc($tag_res))
    foreach(explode('|',$r['category']) as $t) if(trim($t)) $all_tags[]=trim($t);
$unique_tags=array_unique($all_tags);
sort($unique_tags);

include 'includes/header.php';
?>

<div class="page-container">

  <div class="section-header">
    <div class="section-title"><span class="dot"></span> Insight Quest</div>
  </div>
  <p style="color:var(--text-secondary);font-size:14px;margin-bottom:24px;max-width:600px">
    Answer real questions from your game data. Filter by category, choose a question, and get the answer.
  </p>

  <!-- Filter form -->
  <div class="insights-filter-bar" style="margin-bottom:24px">
    <form action="results.php" method="GET">
      <span class="filter-label">Category:</span>
      <select name="q">
        <option value="">All Categories</option>
        <?php foreach($unique_tags as $tag): ?>
        <option value="<?php echo htmlspecialchars($tag); ?>"><?php echo htmlspecialchars($tag); ?></option>
        <?php endforeach; ?>
      </select>
      <span class="filter-label" style="margin-left:8px">Question:</span>
      <select name="insight" style="flex:1;min-width:240px">
        <option value="cheapest">Which is the cheapest right now?</option>
        <option value="high_reviews">Which has the highest positive reviews?</option>
        <option value="neg_reviews">Which has the most negative reviews?</option>
        <option value="price_drop">Which had the biggest price drop ever?</option>
        <option value="expensive">Which is the most expensive?</option>
      </select>
      <button type="submit" class="btn-primary">Get Answer →</button>
    </form>
  </div>

  <!-- Quick chips -->
  <div class="section-header">
    <div class="section-title"><span class="dot"></span> Quick Questions</div>
  </div>
  <div class="questions-grid">
    <a class="question-chip" href="results.php?insight=cheapest">
      <span class="q-num">01</span><span class="q-text">Which game is the cheapest right now?</span>
    </a>
    <a class="question-chip" href="results.php?insight=expensive">
      <span class="q-num">02</span><span class="q-text">Which is the most expensive game?</span>
    </a>
    <a class="question-chip" href="results.php?insight=high_reviews">
      <span class="q-num">03</span><span class="q-text">Which game has the most positive reviews?</span>
    </a>
    <a class="question-chip" href="results.php?insight=neg_reviews">
      <span class="q-num">04</span><span class="q-text">Which game has the most negative reviews?</span>
    </a>
    <a class="question-chip" href="results.php?insight=price_drop">
      <span class="q-num">05</span><span class="q-text">Which game had the biggest price drop in history?</span>
    </a>
    <a class="question-chip" href="import.php">
      <span class="q-num">→</span><span class="q-text">Sync new game data from CSV files</span>
    </a>
  </div>

</div>
</body>
</html>
