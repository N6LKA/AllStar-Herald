<?php
// herald-common.php — shared helpers for herald's web API endpoints.
// Not directly web-accessible logic on its own; included by web/api/*.php.

define('HERALD_BIN', '/usr/local/bin/herald');
define('HERALD_CONFIG_DIR', '/etc/asterisk/scripts/herald');
define('HERALD_AUTH_FILE', HERALD_CONFIG_DIR . '/auth.json');

function herald_json_response($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Shared login session, checked by every mutating (and now every) API
// endpoint - see herald_require_session(). Established three ways, each
// verified server-side, never just trusted from client-side JS:
//   - login.php (standalone UI's own username/password form)
//   - web/supermon/herald.php, right where it already checks Supermon's
//     real $_SESSION['sm61loggedin'] === true
//   - web/allmon3/herald-session-bridge.php, which forwards the browser's
//     Allmon3 cookie to Allmon3's own internal auth/check endpoint
// session.cookie_path is forced to "/" so the resulting cookie is valid
// across every directory Herald's pages/API live in (the web root, the
// Allmon3 web root, and the api/ subdirectory), not just whichever one
// happened to start the session first.
// Named explicitly (not PHP's default session name) so it can never collide
// with a host app's own session that might already be active in the same
// request - e.g. Supermon's session.inc starts a "supermon61"-named session
// before web/supermon/herald.php ever reaches this code. PHP only allows one
// active session per request, so any caller that already has a different
// named session active (Supermon) must session_write_close() it first - see
// the comment in web/supermon/herald.php.
function herald_start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('herald_session');
        session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function herald_check_session(): bool {
    herald_start_session();
    return !empty($_SESSION['herald_authed']);
}

// Called at the top of every web/api/*.php endpoint. Matches the existing
// herald_json_response() error shape so the JS side's error handling
// doesn't need a special case for "not logged in".
function herald_require_session(): void {
    if (!herald_check_session()) {
        herald_json_response(['success' => false, 'message' => 'Not authenticated'], 401);
    }
}

function herald_establish_session(): void {
    herald_start_session();
    $_SESSION['herald_authed'] = true;
    session_regenerate_id(true);
}

// Scheduled announcement names may contain spaces (e.g. "ARRL Audio News");
// tail rotation names are restricted to filename-safe characters by the UI
// itself but are validated with the same rule here. Excludes characters
// that would be unsafe in a shell argument or generated filename.
function herald_valid_name(string $name): bool {
    return (bool) preg_match('/^[a-zA-Z0-9 _-]{1,60}$/', $name);
}

function herald_exec_cmd(array $argv): array {
    $cmd = implode(' ', array_map('escapeshellarg', $argv));
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($proc)) {
        return ['stdout' => '', 'stderr' => 'Failed to launch command', 'exit_code' => 1];
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit_code = proc_close($proc);
    return ['stdout' => $stdout, 'stderr' => $stderr, 'exit_code' => $exit_code];
}

// Read-only commands — no sudo needed (config file is world-readable).
function herald_run(array $args): array {
    return herald_exec_cmd(array_merge([HERALD_BIN], $args));
}

// Mutating commands — run as root via the narrow sudoers rule for HERALD_BIN.
function herald_run_sudo(array $args): array {
    return herald_exec_cmd(array_merge(['sudo', HERALD_BIN], $args));
}

// herald's mutating subcommands print one JSON line (from herald.py)
// plus extra human-readable status lines (e.g. from cmd_reload). Scan from
// the end for the last line that parses as JSON.
function herald_extract_json(string $stdout): ?array {
    $lines = array_reverse(explode("\n", trim($stdout)));
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $decoded = json_decode($line, true);
        if (is_array($decoded)) return $decoded;
    }
    return null;
}

function herald_respond_from_cli(array $result): void {
    $data = herald_extract_json($result['stdout']);
    if ($data !== null) {
        herald_json_response($data);
    }
    herald_json_response([
        'success' => $result['exit_code'] === 0,
        'message' => trim($result['stdout']) !== '' ? trim($result['stdout']) : trim($result['stderr']),
    ]);
}

// Saves an uploaded file and converts it to 8kHz mono 16-bit WAV via sox.
// Returns the path to the converted temp file, or null on failure. Caller
// is responsible for deleting the returned file after use.
function herald_handle_upload(array $file): ?string {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, ['wav', 'mp3'], true)) {
        return null;
    }
    $dest = sys_get_temp_dir() . '/herald_upload_' . bin2hex(random_bytes(8)) . '.wav';
    $cmd = 'sox ' . escapeshellarg($file['tmp_name']) . ' -r 8000 -c 1 -b 16 -t wav ' . escapeshellarg($dest) . ' 2>&1';
    exec($cmd, $out, $ret);
    if ($ret !== 0 || !file_exists($dest)) {
        return null;
    }
    return $dest;
}
