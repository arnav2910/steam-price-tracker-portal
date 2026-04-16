<?php 
include 'includes/db.php'; 
// Get unique tags for dropdown
$res = mysqli_query($conn, "SELECT DISTINCT category FROM games");
$all_tags = [];
while($r = mysqli_fetch_assoc($res)) {
    $tags = explode('|', $r['category']);
    foreach($tags as $t) if(!empty($t)) $all_tags[] = trim($t);
}
$unique_tags = array_unique($all_tags);
sort($unique_tags);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Insight Quest</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container" style="max-width: 600px; text-align: center;">
    <a href="index.php" class="btn-reset">← Back</a>
    <h1 style="margin-top: 50px;">INSIGHT QUEST</h1>
    <div class="table-card" style="padding: 40px; margin-top: 30px;">
        <form action="results.php" method="GET">
            <p>1. Select a Category:</p>
            <select name="q" style="width: 100%; padding: 12px; border-radius: 4px; border: none; margin-bottom: 25px;">
                <option value="">-- All Categories --</option>
                <?php foreach($unique_tags as $tag): ?>
                    <option value="<?php echo $tag; ?>"><?php echo $tag; ?></option>
                <?php endforeach; ?>
            </select>
            
            <p>2. Choose your Question:</p>
            <select name="insight" style="width: 100%; padding: 12px; border-radius: 4px; border: none; margin-bottom: 30px;">
                <option value="cheapest">Which is the cheapest?</option>
                <option value="high_reviews">Which has the highest reviews?</option>
                <option value="neg_reviews">Which has the most negative reviews?</option>
                <option value="price_drop">Which had the biggest price drop ever?</option>
                <option value="expensive">Which is the most expensive?</option>
            </select>
            <button type="submit" class="btn-explore" style="width: 100%; padding: 15px;">Get Answer</button>
        </form>
    </div>
</div>
</body>
</html>