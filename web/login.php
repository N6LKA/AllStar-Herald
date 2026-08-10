<?php
// login.php — standalone Herald UI's own login gate.
//
// Only relevant when Herald's web root is reached directly (not through
// Allmon3 or Supermon, which have their own logins - see
// web/allmon3/herald-session-bridge.php and web/supermon/herald.php for how
// those establish the same session transparently). Credentials live in
// HERALD_AUTH_FILE (bcrypt hash via PHP's own password_hash()/verify() -
// no extra dependency), seeded to admin/admin by install.sh on first
// install only; see change_credentials.php for the change-password flow.
require __DIR__ . '/herald-common.php';

herald_start_session();

if (herald_check_session()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $auth = null;
    if (is_readable(HERALD_AUTH_FILE)) {
        $auth = json_decode((string) file_get_contents(HERALD_AUTH_FILE), true);
    }

    $ok = is_array($auth)
        && isset($auth['username'], $auth['password_hash'])
        && hash_equals((string) $auth['username'], $username)
        && password_verify($password, (string) $auth['password_hash']);

    if ($ok) {
        herald_establish_session();
        header('Location: index.php');
        exit;
    }

    // Generic message either way - don't confirm whether the username was
    // right. Brief delay as minimal brute-force friction; this isn't meant
    // to replace real rate-limiting, just slow down naive automated guessing
    // against the well-known admin/admin default.
    sleep(1);
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Herald — Login</title>
<style>
  body {
    font-family: Arial, sans-serif;
    background: #f4f4f4;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    margin: 0;
  }
  .login-card {
    background: #fff;
    border: 1px solid #ccc;
    border-radius: 8px;
    padding: 32px;
    width: 100%;
    max-width: 340px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  }
  .login-brand { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 20px; }
  .login-brand img.herald-icon { height: 36px; display: block; }
  .login-brand img.herald-banner { height: 36px; display: block; }
  .login-card label { display: block; margin-top: 14px; font-weight: bold; font-size: 0.9em; }
  .login-card input[type=text], .login-card input[type=password] {
    width: 100%;
    box-sizing: border-box;
    padding: 8px;
    margin-top: 4px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 1em;
  }
  .login-card button {
    width: 100%;
    margin-top: 20px;
    padding: 10px;
    background: #2f5d65;
    color: #fff;
    border: none;
    border-radius: 4px;
    font-size: 1em;
    cursor: pointer;
  }
  .login-card button:hover { background: #234952; }
  .error { color: #b3261e; margin-top: 14px; font-size: 0.9em; text-align: center; }
</style>
</head>
<body>
  <div class="login-card">
    <div class="login-brand">
      <img class="herald-icon" src="img/herald-icon.png" alt="">
      <img class="herald-banner" src="img/herald-title-banner.png" alt="AllStarLink Herald">
    </div>
    <form method="post">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" autocomplete="username" required autofocus>
      <label for="password">Password</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required>
      <button type="submit">Log In</button>
      <?php if ($error !== ''): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
    </form>
  </div>
</body>
</html>
