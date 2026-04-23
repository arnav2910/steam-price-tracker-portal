<?php include 'includes/db.php'; ?>
<!DOCTYPE html> 
<html>
<head>
    <title>Steam Tracker</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Google-style centering */
        .home-search {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 80vh;
        }
        .logo-big { font-size: 72px; font-weight: bold; color: #66c0f4; margin-bottom: 30px; letter-spacing: -2px; }
        .search-box { width: 580px; padding: 15px 25px; border-radius: 30px; border: 1px solid #dfe1e5; font-size: 16px; outline: none; }
        .search-box:hover { box-shadow: 0 1px 6px rgba(32,33,36,.28); }
        .nav-links { margin-top: 20px; }
        .nav-links a { color: #66c0f4; text-decoration: none; margin: 0 15px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="home-search">
        <div class="logo-big">STEAM TRACKER</div>
        <form action="results.php" method="GET">
            <input type="text" name="q" class="search-box" placeholder="Search for a game or category (e.g. Action, RPG)..." autofocus>
        </form>
        <div class="nav-links">
            <a href="questions.php">Advanced Insights</a>
            <a href="import.php">Sync Data</a>
        </div>
    </div>
</body>
</html>