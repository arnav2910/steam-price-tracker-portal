<?php 
include 'includes/db.php'; 
$q = mysqli_real_escape_string($conn, $_GET['q'] ?? '');
$insight = $_GET['insight'] ?? '';

$filter = !empty($q) ? " AND (g.name LIKE '%$q%' OR g.category LIKE '%$q%')" : "";

// Base query joins games with latest price and reviews
$query = "SELECT g.*, p.price, r.pos_reviews, r.neg_reviews 
          FROM games g 
          JOIN price_history p ON g.id = p.game_id 
          JOIN review_history r ON g.id = r.game_id
          WHERE p.price_date = (SELECT MAX(price_date) FROM price_history WHERE game_id = g.id)
          AND r.review_date = (SELECT MAX(review_date) FROM review_history WHERE game_id = g.id)
          $filter ";

if (!empty($insight)) {
    switch ($insight) {
        case 'cheapest': $query .= " ORDER BY p.price ASC LIMIT 1"; break;
        case 'expensive': $query .= " ORDER BY p.price DESC LIMIT 1"; break;
        case 'high_reviews': $query .= " ORDER BY r.pos_reviews DESC LIMIT 1"; break;
        case 'neg_reviews': $query .= " ORDER BY r.neg_reviews DESC LIMIT 1"; break;
        case 'price_drop':
            $query = "SELECT g.*, p.price, (MAX(ph.price) - MIN(ph.price)) as drop_amt FROM games g 
                      JOIN price_history p ON g.id = p.game_id JOIN price_history ph ON g.id = ph.game_id
                      WHERE 1=1 $filter GROUP BY g.id ORDER BY drop_amt DESC LIMIT 1";
            break;
    }
}

$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Results - Steam Tracker</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="top-bar" style="display: flex; align-items: center; padding: 15px 5%; background: #171a21; border-bottom: 1px solid #2a475e;">
        <a href="index.php" style="font-size: 22px; font-weight: bold; color: #66c0f4; text-decoration: none; margin-right: 30px;">STEAM TRACKER</a>
        <form action="results.php" method="GET">
            <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" style="width: 400px; padding: 10px 20px; border-radius: 20px; border: none; background: #fff; color: #333;">
        </form>
    </div>
    <div class="container">
        <div class="table-card">
            <table>
                <tr><th>Game</th><th>Categories</th><th>Price</th><th>Action</th></tr>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><strong><?php echo $row['name']; ?></strong></td>
                    <td><?php echo str_replace('|', ', ', $row['category']); ?></td>
                    <td>₹<?php echo number_format($row['price'], 2); ?></td>
                    <td><a href="game.php?id=<?php echo $row['id']; ?>" class="analyze-link">Analyze</a></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</body>
</html>