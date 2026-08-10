<?php
require_once __DIR__ . '/../../herald-common.php';

// Starts a one-click update from whichever branch the UI's dropdown sent
// (defaults to main - see update_install_cmd()'s comment in herald.py).
// This call returns almost instantly: `herald update` itself only checks
// whether one's already running and, if not, launches the real work as a
// detached background process before printing success - it never waits for
// the update to actually finish, since that can take minutes and must not
// be tied to this request's (or PHP's) execution time limit. The web UI
// polls update_status.php for progress.
//
// branch is whitelisted here too, not just in herald.py's own argparse
// choices= - an invalid value gets a clean error from this layer instead
// of silently falling through to whatever argparse's exit code produces.
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$branch = $input['branch'] ?? 'main';
if (!in_array($branch, ['main', 'develop'], true)) {
    herald_json_response(['success' => false, 'message' => 'Invalid branch'], 400);
}

herald_respond_from_cli(herald_run_sudo(['update', '--branch', $branch]));
