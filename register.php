<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Validation
    if (strlen($username) < 3 || strlen($username) > 30) {
        $error = 'Username must be between 3 and 30 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username may only contain letters, numbers, and underscores.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $safe = mysqli_real_escape_string($conn, $username);
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$safe'");
        if (mysqli_num_rows($check) > 0) {
            $error = 'That username is already taken. Please choose another.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $safe_hash = mysqli_real_escape_string($conn, $hash);
            mysqli_query($conn, "INSERT INTO users (username, password_hash) VALUES ('$safe', '$safe_hash')");
            $new_id = mysqli_insert_id($conn);

            // Auto login
            $_SESSION['user_id']  = $new_id;
            $_SESSION['username'] = $username;

            header('Location: index.php');
            exit;
        }
    }
}

$active_nav = '';
$page_title = 'Register';
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

    <h1 class="auth-title">Create Account</h1>
    <p class="auth-sub">Track prices, build wishlists, and get AI recommendations.</p>

    <?php if ($error): ?>
    <div class="auth-alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="register.php" method="POST" class="auth-form" novalidate>
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username"
               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
               placeholder="e.g. gamer42" maxlength="30" autocomplete="username" required>
        <span class="form-hint">3–30 characters. Letters, numbers, underscores only.</span>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-password-wrap">
          <input type="password" id="password" name="password"
                 placeholder="At least 6 characters" autocomplete="new-password" required>
          <button type="button" class="toggle-pw" onclick="togglePw('password')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label for="confirm_password">Confirm Password</label>
        <div class="input-password-wrap">
          <input type="password" id="confirm_password" name="confirm_password"
                 placeholder="Repeat your password" autocomplete="new-password" required>
          <button type="button" class="toggle-pw" onclick="togglePw('confirm_password')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-primary auth-submit">Create Account</button>
    </form>

    <p class="auth-switch">Already have an account? <a href="login.php">Sign in →</a></p>
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
