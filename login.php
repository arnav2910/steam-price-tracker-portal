<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error    = '';
$redirect = $_GET['redirect'] ?? 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirect = $_POST['redirect'] ?? 'index.php';

    if ($username === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        $safe = mysqli_real_escape_string($conn, $username);
        $res  = mysqli_query($conn, "SELECT id, username, password_hash FROM users WHERE username='$safe'");
        $user = mysqli_fetch_assoc($res);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            session_regenerate_id(true);

            // Safety: only redirect to internal URLs
            $safe_redirect = (strpos($redirect, '//') === false) ? $redirect : 'index.php';
            header("Location: $safe_redirect");
            exit;
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    }
}

$active_nav = 'login';
$page_title = 'Sign In';
include 'includes/header.php';
?>

<div class="page-container auth-container">
  <div class="auth-card">
    <div class="auth-logo">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--accent)">
        <path d="M3 3h18v18H3z"/><path d="M3 9h18M9 21V9"/>
      </svg>
      <span>SteamTracker</span>
    </div>

    <h1 class="auth-title">Welcome Back</h1>
    <p class="auth-sub">Sign in to access your wishlist, cart, and AI chat.</p>

    <?php if ($error): ?>
    <div class="auth-alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="login.php" method="POST" class="auth-form" novalidate>
      <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username"
               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
               placeholder="Your username" autocomplete="username" required autofocus>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-password-wrap">
          <input type="password" id="password" name="password"
                 placeholder="Your password" autocomplete="current-password" required>
          <button type="button" class="toggle-pw" onclick="togglePw('password')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-primary auth-submit">Sign In</button>
    </form>

    <p class="auth-switch">Don't have an account? <a href="register.php">Create one →</a></p>
  </div>
</div>

<script>
function togglePw(id) {
  var el = document.getElementById(id);
  el.type = el.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
