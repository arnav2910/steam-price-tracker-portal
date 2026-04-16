<?php 
include 'includes/db.php'; 
$q = mysqli_real_escape_string($conn, $_GET['q'] ?? '');

$sql = "SELECT g.*, p.price FROM games g 
        JOIN price_history p ON g.id = p.game_id 
        WHERE (g.name LIKE '%$q%' OR g.category LIKE '%$q%')
        AND p.price_date = (SELECT MAX(price_date) FROM price_history WHERE game_id = g.id)";

$res = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Results - Steam Tracker</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .top-bar { display: flex; align-items: center; padding: 20px 5%; background: #171a21; border-bottom: 1px solid #2a475e; }
        .logo-small { font-size: 24px; font-weight: bold; color: #66c0f4; margin-right: 30px; text-decoration: none; }
        .search-mini { width: 500px; padding: 10px 20px; border-radius: 20px; border: none; }
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="index.php" class="logo-small">STEAM TRACKER</a>
        <form action="results.php" method="GET">
            <input type="text" name="q" class="search-mini" value="<?php echo htmlspecialchars($q); ?>">
        </form>
    </div>

    <div class="container">
        <div class="table-card">
            <table>
                <tr><th>Game Name</th><th>Tags</th><th>Current Price</th><th>Action</th></tr>
                <?php while($row = mysqli_fetch_assoc($res)): ?>
                <tr>
                    <td><strong><?php echo $row['name']; ?></strong></td>
                    <td><?php echo str_replace('|', ', ', $row['category']); ?></td>
                    <td>₹<?php echo $row['price']; ?></td>
                    <td><a href="game.php?id=<?php echo $row['id']; ?>" class="analyze-link">Analyze Details</a></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</body>
</html>