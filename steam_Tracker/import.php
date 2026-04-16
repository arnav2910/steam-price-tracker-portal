<?php
include 'includes/db.php';
set_time_limit(0); 
$directory = "data/";
$files = scandir($directory);

function updateGame($conn, $name, $tags) {
    $clean_name = mysqli_real_escape_string($conn, str_replace('_', ' ', $name));
    $clean_tags = mysqli_real_escape_string($conn, $tags);
    $res = mysqli_query($conn, "SELECT id FROM games WHERE name = '$clean_name'");
    if ($row = mysqli_fetch_assoc($res)) {
        $gid = $row['id'];
        mysqli_query($conn, "UPDATE games SET category = '$clean_tags' WHERE id = $gid");
        return $gid;
    } else {
        mysqli_query($conn, "INSERT INTO games (name, category) VALUES ('$clean_name', '$clean_tags')");
        return mysqli_insert_id($conn);
    }
}

foreach ($files as $file) {
    if (preg_match('/^(.*)_([0-9]+)_(price|prices|reviews)\.csv$/i', $file, $matches)) {
        $name = $matches[1];
        $type = strtolower($matches[3]);
        $handle = fopen($directory . $file, "r");
        fgetcsv($handle); 
        while (($data = fgetcsv($handle)) !== FALSE) {
            $tags = ($type == 'reviews' && isset($data[3])) ? $data[3] : "General";
            $game_id = updateGame($conn, $name, $tags);
            $date = date('Y-m-d', strtotime($data[0]));
            if (strpos($type, 'price') !== false) {
                mysqli_query($conn, "INSERT INTO price_history (game_id, price_date, price) VALUES ($game_id, '$date', '$data[1]')");
            } else {
                mysqli_query($conn, "INSERT INTO review_history (game_id, review_date, pos_reviews, neg_reviews) VALUES ($game_id, '$date', '$data[1]', '$data[2]')");
            }
        }
        fclose($handle);
    }
}
echo "Import Complete!";
?>