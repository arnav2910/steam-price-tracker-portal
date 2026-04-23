<?php
include 'includes/db.php';
set_time_limit(0); 

$directory = "data/";

if (!is_dir($directory)) {
    die("Error: The '$directory' folder does not exist.");
}

$files = scandir($directory);

// We added $type to the function so it knows WHICH file it is reading
function updateGame($conn, $name, $tags, $type) {
    // Clean the name (handles both "Baldur's_Gate_3" and "Baldur's Gate 3")
    $clean_name = mysqli_real_escape_string($conn, str_replace('_', ' ', $name));
    $clean_tags = mysqli_real_escape_string($conn, $tags);
    
    $res = mysqli_query($conn, "SELECT id, category FROM games WHERE name = '$clean_name'");
    
    if ($row = mysqli_fetch_assoc($res)) {
        $gid = $row['id'];
        
        // BUG FIX: Only update the category if we are reading a reviews file!
        // This prevents the prices.csv from overwriting categories with "General"
        if ($type == 'reviews' && $tags != 'General') {
            mysqli_query($conn, "UPDATE games SET category = '$clean_tags' WHERE id = $gid");
        }
        
        return $gid;
    } else {
        // If the game doesn't exist yet, insert it
        mysqli_query($conn, "INSERT INTO games (name, category) VALUES ('$clean_name', '$clean_tags')");
        return mysqli_insert_id($conn);
    }
}

foreach ($files as $file) {
    // Match the filename pattern: GameName_AppID_Type.csv
    if (preg_match('/^(.*)_([0-9]+)_(price|prices|reviews)\.csv$/i', $file, $matches)) {
        $name = $matches[1];
        $type = strtolower($matches[3]);
        
        $handle = fopen($directory . $file, "r");
        fgetcsv($handle); // Skip header row
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            // Get tags from column 4 if it's a review file
            $tags = ($type == 'reviews' && isset($data[3])) ? $data[3] : "General";
            
            // Pass the $type into the function
            $game_id = updateGame($conn, $name, $tags, $type);
            $date = date('Y-m-d', strtotime($data[0]));
            
            // Insert Price or Review Data
            if (strpos($type, 'price') !== false) {
                mysqli_query($conn, "INSERT INTO price_history (game_id, price_date, price) VALUES ($game_id, '$date', '$data[1]')");
            } else {
                mysqli_query($conn, "INSERT INTO review_history (game_id, review_date, pos_reviews, neg_reviews) VALUES ($game_id, '$date', '$data[1]', '$data[2]')");
            }
        }
        fclose($handle);
    }
}

echo "Import Complete! Categories have been safely preserved.";
?>