<?php 
include 'includes/db.php'; 

// Fetch all unique tags for the dropdown
$all_games = mysqli_query($conn, "SELECT category FROM games");
$tags = [];
while($row = mysqli_fetch_assoc($all_games)) {
    $parts = explode('|', $row['category']);
    foreach($parts as $p) { if(!empty($p)) $tags[] = trim($p); }
}
$unique_tags = array_unique($tags);
sort($unique_tags);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Insights - Steam Tracker</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <a href="index.php" class="btn-reset">← Back to Search</a>
    <h1>Insight Quest</h1>
    
    <div class="filter-card" style="flex-direction: column; align-items: flex-start;">
        <form method="GET" action="results.php">
            <p>1. Select a Category:</p>
            <select name="q" style="width: 300px; margin-bottom: 20px;">
                <option value="">-- All Categories --</option>
                <?php foreach($unique_tags as $t): ?>
                    <option value="<?php echo $t; ?>"><?php echo $t; ?></option>
                <?php endforeach; ?>
            </select>

            <p>2. What do you want to find?</p>
            <select name="insight" style="width: 300px; margin-bottom: 20px;">
                <option value="cheapest">Which is the Cheapest?</option>
                <option value="high_reviews">Which has the Highest Reviews?</option>
                <option value="stable_price">Which has the Most Stable Price?</option>
                <option value="expensive">Which is the Most Expensive?</option>
                <option value="neg_reviews">Which has the Most Negative Reviews?</option>
                <option value="price_drop">Which had the Highest Price Drop?</option>
                <option value="review_change">Which has the Highest Growth in Reviews?</option>
            </select>
            <br>
            <button type="submit" class="btn-explore">Get Answer</button>
        </form>
    </div>
</div>
</body>
</html>