<?php
include 'includes/db.php';
set_time_limit(0);
$active_nav = 'import';
$page_title = 'Sync Data';

$import_result = null;

if($_SERVER['REQUEST_METHOD']==='POST'){
    $directory = "data/";
    if(!is_dir($directory)){
        $import_result=['error'=>true,'msg'=>"Error: The 'data/' folder does not exist."];
    } else {
        $files=scandir($directory);

        function updateGame($conn,$name,$tags,$type){
            $clean_name=mysqli_real_escape_string($conn,str_replace('_',' ',$name));
            $clean_tags=mysqli_real_escape_string($conn,$tags);
            $res=mysqli_query($conn,"SELECT id,category FROM games WHERE name='$clean_name'");
            if($row=mysqli_fetch_assoc($res)){
                $gid=$row['id'];
                if($type=='reviews'&&$tags!='General')
                    mysqli_query($conn,"UPDATE games SET category='$clean_tags' WHERE id=$gid");
                return $gid;
            }
            mysqli_query($conn,"INSERT INTO games(name,category) VALUES('$clean_name','$clean_tags')");
            return mysqli_insert_id($conn);
        }

        $imported=0;
        foreach($files as $file){
            if(!preg_match('/^(.*)_([0-9]+)_(price|prices|reviews)\.csv$/i',$file,$m)) continue;
            $name=$m[1]; $type=strtolower($m[3]);
            $handle=fopen($directory.$file,'r');
            fgetcsv($handle);
            while(($data=fgetcsv($handle))!==false){
                $tags=($type=='reviews'&&isset($data[3]))?$data[3]:'General';
                $gid=updateGame($conn,$name,$tags,$type);
                $date=date('Y-m-d',strtotime($data[0]));
                if(strpos($type,'price')!==false)
                    mysqli_query($conn,"INSERT INTO price_history(game_id,price_date,price) VALUES($gid,'$date','".floatval($data[1])."')");
                else
                    mysqli_query($conn,"INSERT INTO review_history(game_id,review_date,pos_reviews,neg_reviews) VALUES($gid,'$date',".intval($data[1]).",".intval($data[2]).")");
                $imported++;
            }
            fclose($handle);
        }
        $import_result=['error'=>false,'msg'=>"✔ Import complete! $imported records processed. Categories preserved."];
    }
}

include 'includes/header.php';
?>

<div class="page-container">
  <div class="section-header">
    <div class="section-title"><span class="dot"></span> Sync Data</div>
  </div>
  <p style="color:var(--text-secondary);font-size:14px;max-width:640px;margin-bottom:28px">
    Import price and review data from CSV files in the <code style="font-family:var(--font-mono);background:var(--bg-input);border:1px solid var(--border);border-radius:4px;padding:2px 7px;font-size:12px;color:var(--steam-blue)">data/</code> folder.
    Files must follow the naming pattern: <code style="font-family:var(--font-mono);background:var(--bg-input);border:1px solid var(--border);border-radius:4px;padding:2px 7px;font-size:12px;color:var(--steam-blue)">GameName_AppID_prices.csv</code> or <code style="font-family:var(--font-mono);background:var(--bg-input);border:1px solid var(--border);border-radius:4px;padding:2px 7px;font-size:12px;color:var(--steam-blue)">GameName_AppID_reviews.csv</code>.
  </p>

  <div class="import-card">
    <?php if($import_result): ?>
    <div class="import-result<?php if($import_result['error']) echo ' error'; ?>">
      <?php echo htmlspecialchars($import_result['msg']); ?>
    </div>
    <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap">
      <a href="index.php" class="btn-primary">← View Games</a>
      <a href="ml_engine.php" class="btn-secondary">🤖 Run ML Engine</a>
    </div>
    <?php else: ?>
    <p>Click <strong style="color:var(--text-primary)">Start Import</strong> to scan the <code>data/</code> directory and load all matching CSV files into the database.</p>
    <p>After import, run the <strong style="color:var(--text-primary)">ML Engine</strong> to assign games to pricing &amp; sentiment clusters for smarter recommendations.</p>
    <form action="import.php" method="POST" style="margin-top:20px;display:flex;gap:12px;flex-wrap:wrap">
      <button type="submit" class="btn-primary">▶ Start Import</button>
      <a href="ml_engine.php" class="btn-secondary">🤖 Run ML Engine</a>
    </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
