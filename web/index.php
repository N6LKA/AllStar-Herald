<?php
// index.php — standalone Herald UI entry point, for anyone not running
// Allmon3 or Supermon (or who just doesn't want the integration). Gated by
// Herald's own login (see login.php) - Allmon3/Supermon users never see
// this page at all, since their own entry points establish the same
// session transparently and never link here.
require __DIR__ . '/herald-common.php';

herald_start_session();
if (!herald_check_session()) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="AllStarLink Herald - Announcement Manager Suite">
<title>AllStarLink Herald — Announcement Manager Suite</title>
<style>
  body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; }
  .herald-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff;
    border-bottom: 1px solid #ccc;
    padding: 10px 20px;
  }
  .herald-topbar-brand { display: flex; align-items: center; gap: 14px; }
  .herald-topbar-brand img.herald-icon { height: 64px; display: block; }
  .herald-topbar-brand img.herald-banner { height: 64px; display: block; }
  .herald-topbar a {
    color: #2f5d65;
    text-decoration: none;
    font-weight: bold;
    font-size: 0.9em;
  }
  .herald-topbar a:hover { text-decoration: underline; }
  .herald-content { max-width: 1800px; margin: 0 auto; padding: 20px; }
</style>
</head>
<body>
  <div class="herald-topbar">
    <div class="herald-topbar-brand">
      <img class="herald-icon" src="img/herald-icon.png" alt="">
      <img class="herald-banner" src="img/herald-title-banner.png" alt="AllStarLink Herald">
    </div>
    <a href="logout.php">Log Out</a>
  </div>
  <div class="herald-content">
    <?php include __DIR__ . '/herald-ui-fragment.php'; ?>
  </div>
  <?php
  $herald_ui_js_ver = @filemtime(__DIR__ . '/herald-ui.js') ?: time();
  ?>
  <script src="herald-ui.js?v=<?php echo $herald_ui_js_ver; ?>"></script>
</body>
</html>
