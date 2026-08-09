<?php
// logout.php — destroys the standalone UI's session and returns to login.
require __DIR__ . '/herald-common.php';

herald_start_session();
$_SESSION = [];
session_destroy();

header('Location: login.php');
