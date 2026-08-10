<?php
// change_credentials.php
//
// GET: returns the current standalone-login username and whether it's still
// the shipped admin/admin default (used to show the nag banner) - no
// password data ever leaves the server.
// POST: changes the username and/or password. Requires the CURRENT password
// re-entered correctly first, same as any other account-settings change.
//
// Only relevant to the standalone UI's own login (Allmon3/Supermon use
// their own logins, unaffected by this) - but reachable from any logged-in
// context, so an admin can fix the default password without ever visiting
// the standalone UI directly.
require __DIR__ . '/../herald-common.php';
herald_require_session();

function herald_read_auth(): ?array {
    if (!is_readable(HERALD_AUTH_FILE)) return null;
    $auth = json_decode((string) file_get_contents(HERALD_AUTH_FILE), true);
    return is_array($auth) && isset($auth['username'], $auth['password_hash']) ? $auth : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $auth = herald_read_auth();
    if ($auth === null) {
        herald_json_response(['success' => false, 'message' => 'Could not read login settings'], 500);
    }
    herald_json_response([
        'success' => true,
        'username' => $auth['username'],
        'is_default' => $auth['username'] === 'admin' && password_verify('admin', $auth['password_hash']),
    ]);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$current_password = (string) ($input['current_password'] ?? '');
$new_username = trim((string) ($input['new_username'] ?? ''));
$new_password = (string) ($input['new_password'] ?? '');

$auth = herald_read_auth();
if ($auth === null) {
    herald_json_response(['success' => false, 'message' => 'Could not read login settings'], 500);
}

if (!password_verify($current_password, $auth['password_hash'])) {
    herald_json_response(['success' => false, 'message' => 'Current password is incorrect'], 403);
}

if ($new_username === '' && $new_password === '') {
    herald_json_response(['success' => false, 'message' => 'Nothing to change']);
}

if ($new_username !== '') {
    if (!preg_match('/^[a-zA-Z0-9_-]{1,60}$/', $new_username)) {
        herald_json_response(['success' => false, 'message' => 'Username may only contain letters, numbers, underscores, and hyphens']);
    }
    $auth['username'] = $new_username;
}

if ($new_password !== '') {
    if (strlen($new_password) < 8) {
        herald_json_response(['success' => false, 'message' => 'New password must be at least 8 characters']);
    }
    $auth['password_hash'] = password_hash($new_password, PASSWORD_DEFAULT);
}

// Written directly (not via a temp-file-then-rename swap) because
// CONFIG_DIR itself is root-owned 755 - www-data (which this script runs
// as) can modify an existing file it owns without needing write access to
// the directory, but couldn't create a new .tmp file there to rename from.
// flock() still protects against two concurrent saves corrupting each
// other, which is the realistic risk for a file an admin edits rarely.
$fp = fopen(HERALD_AUTH_FILE, 'c');
if ($fp === false || !flock($fp, LOCK_EX)) {
    herald_json_response(['success' => false, 'message' => 'Failed to save login settings'], 500);
}
ftruncate($fp, 0);
rewind($fp);
$written = fwrite($fp, json_encode($auth));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);
if ($written === false) {
    herald_json_response(['success' => false, 'message' => 'Failed to save login settings'], 500);
}

herald_json_response(['success' => true, 'message' => 'Login settings updated']);
