<?php 
include 'includes/db.php'; 
include 'includes/logic.php'; 

$id = (int)$_GET['id'];
$game_res = mysqli_query($conn, "SELECT * FROM games WHERE id = $id");
$game = mysqli_fetch_assoc($game_res);
if (!$game) die("Game Not Found");

$buy_score = getBuyScore($conn, $id);

// 1. Data Fetching for Charts
$p_res = mysqli_query($conn, "SELECT price_date, price FROM price_history WHERE game_id = $id ORDER BY price_date ASC");
$p_dates = []; $p_vals = [];
while($r = mysqli_fetch_assoc($p_res)) { $p_dates[] = $r['price_date']; $p_vals[] = $r['price']; }

$rev_res = mysqli_query($conn, "SELECT pos_reviews, neg_reviews FROM review_history WHERE game_id = $id ORDER BY review_date DESC LIMIT 1");
$latest = mysqli_fetch_assoc($rev_res);
$pos = $latest['pos_reviews'] ?? 0;
$neg = $latest['neg_reviews'] ?? 0;
$total = $pos + $neg;
$percent = ($total > 0) ? round(($pos / $total) * 100) : 0;

// Sentiment Label Mapping
$label = "Mixed"; $color = "#b9a074";
if ($percent >= 95) { $label = "Overwhelmingly Positive"; $color = "#66c0f4"; }
elseif ($percent >= 80) { $label = "Very Positive"; $color = "#66c0f4"; }
elseif ($percent >= 70) { $label = "Positive"; $color = "#66c0f4"; }
elseif ($percent < 40) { $label = "Negative"; $color = "#a34c32"; }

$rh_res = mysqli_query($conn, "SELECT review_date, pos_reviews, neg_reviews FROM review_history WHERE game_id = $id ORDER BY review_date ASC");
$rh_dates = []; $rh_pos = []; $rh_neg = [];
while($r = mysqli_fetch_assoc($rh_res)) { $rh_dates[] = $r['review_date']; $rh_pos[] = $r['pos_reviews']; $rh_neg[] = $r['neg_reviews']; }
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $game['name']; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container">
    <a href="index.php" class="btn-reset">← Back</a>
    <h1 style="font-size: 48px; margin: 20px 0; color: #fff;"><?php echo strtoupper($game['name']); ?></h1>

    <div class="table-card" style="margin-bottom: 20px; padding: 25px;">
        <h3 style="margin-top:0;">Buy Recommendation Score</h3>
        <div class="score-container">
            <div class="pointer" style="left: <?php echo $buy_score; ?>%;">▼<br><strong><?php echo $buy_score; ?></strong></div>
            <div class="number-line"></div>
        </div>
    </div>

    <div class="table-card" style="margin-bottom: 20px; padding: 20px;">
        <h3>Price History (INR)</h3>
        <canvas id="priceChart"></canvas>
    </div>

    <div class="table-card" style="padding: 25px; margin-bottom: 20px;">
        <h3 style="margin-top:0;">Overall Reviews: <span style="color:<?php echo $color; ?>"><?php echo $label; ?></span></h3>
        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
            <span style="color: #66c0f4; font-weight: bold;"><?php echo $percent; ?>% Positive</span>
            <span style="color: #889297;"><?php echo number_format($total); ?> reviews</span>
        </div>
        <div style="width: 100%; height: 12px; background: #a34c32; border-radius: 6px; overflow: hidden; display: flex;">
            <div style="width: <?php echo $percent; ?>%; height: 100%; background: #66c0f4;"></div>
        </div>
    </div>

    <div class="table-card" style="padding: 20px;">
        <h3>Review Growth History</h3>
        <canvas id="reviewChart"></canvas>
    </div>
</div>

<script>
const opt = { 
    responsive: true, 
    scales: { 
        x: { ticks: { maxTicksLimit: 10, color: '#889297' } }, 
        y: { ticks: { color: '#889297' } } 
    } 
};

// Price Chart
new Chart(document.getElementById('priceChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($p_dates); ?>,
        datasets: [{ label: 'Price', data: <?php echo json_encode($p_vals); ?>, borderColor: '#66c0f4', stepped: true, fill: false, pointRadius: 0 }]
    },
    options: opt
});

// Review History Chart
new Chart(document.getElementById('reviewChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($rh_dates); ?>,
        datasets: [
            { label: 'Positives', data: <?php echo json_encode($rh_pos); ?>, borderColor: '#2ecc71', tension: 0.3 },
            { label: 'Negatives', data: <?php echo json_encode($rh_neg); ?>, borderColor: '#e74c3c', tension: 0.3 }
        ]
    },
    options: opt
});
</script>
</body>
</html>