<?php

require_once __DIR__ . '/auth.php';

$active_nav = $active_nav ?? 'home';
$page_title = isset($page_title) ? htmlspecialchars($page_title).' — ' : '';
$css_root   = $css_root ?? '';

$_wl_count  = isset($conn) ? wishlistCount($conn) : 0;
$_cart_count= isset($conn) ? cartCount($conn) : 0;
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
      <a href="<?php echo $css_root; ?>compare.php"    <?php if($active_nav==='compare') echo 'class="active"'; ?>>
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-2px">
          <path d="M18 20V10M12 20V4M6 20v-6"/>
        </svg>
        Compare
      </a>
      <a href="<?php echo $css_root; ?>chatbot.php"    <?php if($active_nav==='chatbot') echo 'class="active"'; ?>>
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-2px">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        AI Chat
      </a>
    </nav>

    <!-- User action icons -->
    <div class="header-user-actions">
      <?php if(isLoggedIn()): ?>
        <a href="<?php echo $css_root; ?>wishlist.php" class="icon-btn<?php if($active_nav==='wishlist') echo ' active'; ?>" title="Wishlist">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
          </svg>
          <span class="icon-badge" id="hdr-wl-badge" style="<?php echo $_wl_count > 0 ? '' : 'display:none'; ?>"><?php echo $_wl_count; ?></span>
        </a>
        <a href="<?php echo $css_root; ?>cart.php" class="icon-btn<?php if($active_nav==='cart') echo ' active'; ?>" title="Cart">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
          </svg>
          <span class="icon-badge" id="hdr-cart-badge" style="<?php echo $_cart_count > 0 ? '' : 'display:none'; ?>"><?php echo $_cart_count; ?></span>
        </a>
        <div class="user-menu-wrap">
          <button class="user-menu-btn" id="userMenuBtn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
            <?php echo htmlspecialchars(currentUsername()); ?>
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
          </button>
          <div class="user-menu-dropdown" id="userMenuDropdown">
            <a href="<?php echo $css_root; ?>wishlist.php">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
              Wishlist <span id="hdr-dd-wl-count"><?php if($_wl_count > 0) echo "($_wl_count)"; ?></span>
            </a>
            <a href="<?php echo $css_root; ?>cart.php">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
              Cart <span id="hdr-dd-cart-count"><?php if($_cart_count > 0) echo "($_cart_count)"; ?></span>
            </a>
            <div class="user-menu-divider"></div>
            <a href="<?php echo $css_root; ?>logout.php" class="user-menu-logout">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
              Sign Out
            </a>
          </div>
        </div>
      <?php else: ?>
        <a href="<?php echo $css_root; ?>login.php"    class="btn-header-link<?php if($active_nav==='login') echo ' active'; ?>">Sign In</a>
        <a href="<?php echo $css_root; ?>register.php" class="btn-header-register">Register</a>
      <?php endif; ?>
    </div>

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

<script>
// User dropdown toggle
(function(){
  var btn = document.getElementById('userMenuBtn');
  var dd  = document.getElementById('userMenuDropdown');
  if (!btn || !dd) return;
  btn.addEventListener('click', function(e){
    e.stopPropagation();
    dd.classList.toggle('open');
  });
  document.addEventListener('click', function(){
    dd.classList.remove('open');
  });
})();
</script>