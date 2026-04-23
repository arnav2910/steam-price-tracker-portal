<?php 
include 'includes/db.php'; 
include 'includes/logic.php'; 

$id = (int)$_GET['id'];
$game_res = mysqli_query($conn, "SELECT * FROM games WHERE id = $id");
$game = mysqli_fetch_assoc($game_res);
if (!$game) die("Game Not Found");

// Buy Score
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
    <title><?php echo htmlspecialchars($game['name']); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container">
    <a href="index.php" class="btn-reset">← Back</a>
    
    <h1 style="font-size: 48px; margin: 20px 0 5px 0; color: #fff;">
        <?php echo strtoupper(htmlspecialchars($game['name'])); ?>
    </h1>

    <div style="margin-bottom: 25px;">
        <?php 
        $tags = explode('|', $game['category']);
        foreach($tags as $tag) {
            $clean_tag = trim($tag);
            if(!empty($clean_tag)) {
                echo '<span style="display:inline-block; background:#2a475e; color:#66c0f4; padding:5px 12px; border-radius:15px; margin-right:8px; font-size:14px; font-weight:bold;">' . htmlspecialchars($clean_tag) . '</span>';
            }
        }
        ?>
    </div>

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

    <div class="table-card" style="padding: 20px; margin-bottom: 20px;">
        <h3>Review Growth History</h3>
        <canvas id="reviewChart"></canvas>
    </div>

    <div class="table-card" style="padding: 25px; margin-bottom: 20px;">
        <h3>Because you viewed <?php echo htmlspecialchars($game['name']); ?></h3>
        
        <?php
        $tags_array = explode('|', $game['category']);
        $primary_tag = mysqli_real_escape_string($conn, trim($tags_array[0]));
        $current_cluster = mysqli_real_escape_string($conn, $game['cluster_label'] ?: 'Uncategorized');
        $current_id = $game['id'];
        
        // --- THE WATERFALL LOGIC ---
        
        // Attempt 1: Perfect Match (Same Cluster AND Same Tag)
        $rec_sql = "SELECT g.id, g.name, (SELECT price FROM price_history WHERE game_id = g.id ORDER BY price_date DESC LIMIT 1) as price 
                    FROM games g WHERE cluster_label = '$current_cluster' AND category LIKE '%$primary_tag%' AND id != $current_id LIMIT 4";
        $rec_res = mysqli_query($conn, $rec_sql);
        $match_type = "pricing behavior and genre";

        // Fallback 1: Just match the Genre (Tag)
        if (mysqli_num_rows($rec_res) == 0) {
            $rec_sql = "SELECT g.id, g.name, (SELECT price FROM price_history WHERE game_id = g.id ORDER BY price_date DESC LIMIT 1) as price 
                        FROM games g WHERE category LIKE '%$primary_tag%' AND id != $current_id LIMIT 4";
            $rec_res = mysqli_query($conn, $rec_sql);
            $match_type = "similar genres ($primary_tag)";
        }

        // Fallback 2: Just match the ML Cluster (Price/Sentiment tier)
        if (mysqli_num_rows($rec_res) == 0) {
            $rec_sql = "SELECT g.id, g.name, (SELECT price FROM price_history WHERE game_id = g.id ORDER BY price_date DESC LIMIT 1) as price 
                        FROM games g WHERE cluster_label = '$current_cluster' AND id != $current_id LIMIT 4";
            $rec_res = mysqli_query($conn, $rec_sql);
            $match_type = "similar market pricing ($current_cluster)";
        }
        ?>

        <p style="color: #889297; font-size: 14px; margin-top: -10px; margin-bottom: 20px;">
            Based on <?php echo htmlspecialchars($match_type); ?>
        </p>

        <div style="display: flex; gap: 20px; overflow-x: auto; padding-bottom: 10px;">
            <?php
            if (mysqli_num_rows($rec_res) > 0) {
                while($rec = mysqli_fetch_assoc($rec_res)) {
                    $rec_price = $rec['price'] ?? 0;
                    echo '<a href="game.php?id=' . $rec['id'] . '" style="flex: 1; min-width: 200px; background: #1b2838; padding: 15px; border-radius: 8px; text-decoration: none; border: 1px solid #2a475e; transition: 0.3s;" onmouseover="this.style.borderColor=\'#66c0f4\'" onmouseout="this.style.borderColor=\'#2a475e\'">';
                    echo '<strong style="color: #fff; display: block; margin-bottom: 10px;">' . htmlspecialchars($rec['name']) . '</strong>';
                    
                    if ($rec_price == 0) {
                        echo '<span style="color: #2ecc71; font-weight: bold;">Free to Play</span>';
                    } else {
                        echo '<span style="color: #66c0f4; font-weight: bold;">₹' . number_format($rec_price) . '</span>';
                    }
                    echo '</a>';
                }
            } else {
                echo '<p style="color: #889297;">Import more games to see recommendations!</p>';
            }
            ?>
        </div>
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

// Review Chart (Bi-directional Bar Chart)
new Chart(document.getElementById('reviewChart'), {
    type: 'bar', // Changed from 'line' to 'bar'
    data: {
        labels: <?php echo json_encode($rh_dates); ?>,
        datasets: [
            {
                label: 'Positive Reviews',
                data: <?php echo json_encode($rh_pos); ?>,
                backgroundColor: '#2ecc71',
                borderColor: '#27ae60',
                borderWidth: 1
            },
            {
                label: 'Negative Reviews',
                // Map the data to negative values so they grow downwards
                data: <?php echo json_encode(array_map(function($v) { return -$v; }, $rh_neg)); ?>,
                backgroundColor: '#e74c3c',
                borderColor: '#c0392b',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            x: {
                ticks: { maxTicksLimit: 10, color: '#889297' },
                stacked: true // Stack bars on the same x-axis point
            },
            y: {
                stacked: true,
                ticks: {
                    color: '#889297',
                    // Optional: Format labels to show absolute values (remove negative sign)
                    callback: function(value) {
                        return Math.abs(value);
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    // Correct the tooltip so it doesn't show a negative sign for bad reviews
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) label += ': ';
                        label += Math.abs(context.parsed.y);
                        return label;
                    }
                }
            }
        }
    }
});
</script>
</body>
</html>