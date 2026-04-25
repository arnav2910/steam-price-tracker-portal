<?php
// Shared header — included at top of every page.
// Set $active_nav = 'home'|'insights'|'import' before including.
// Set $page_title for <title> tag.
// Set $css_root = '' for root pages, '../' for subdirectory pages.
$active_nav = $active_nav ?? 'home';
$page_title = isset($page_title) ? htmlspecialchars($page_title).' — ' : '';
$css_root   = $css_root ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?php echo $page_title; ?>SteamTracker</title>
  <link rel="stylesheet" href="<?php echo $css_root; ?>css/style.css">
  <?php if(!empty($extra_head)) echo $extra_head; ?>
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <a href="<?php echo $css_root; ?>index.php" class="logo">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M3 3h18v18H3z"/><path d="M3 9h18M9 21V9"/>
      </svg>
      Steam<span>Tracker</span>
    </a>

    <nav class="nav-links">
      <a href="<?php echo $css_root; ?>index.php"     <?php if($active_nav==='home')    echo 'class="active"'; ?>>Home</a>
      <a href="<?php echo $css_root; ?>questions.php"  <?php if($active_nav==='insights') echo 'class="active"'; ?>>Insights</a>
      <a href="<?php echo $css_root; ?>import.php"     <?php if($active_nav==='import')  echo 'class="active"'; ?>>Sync Data</a>
      <a href="<?php echo $css_root; ?>ml_engine.php"  <?php if($active_nav==='ml')      echo 'class="active"'; ?>>ML Engine</a>
    </nav>

    <div class="header-search">
      <svg class="search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
      </svg>
      <form action="<?php echo $css_root; ?>results.php" method="GET">
        <input type="text" name="q" placeholder="Search games or categories…" autocomplete="off">
      </form>
    </div>
  </div>
</header>
