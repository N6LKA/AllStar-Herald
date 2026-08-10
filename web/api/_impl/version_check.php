<?php
require_once __DIR__ . '/../../herald-common.php';

// Runs the same check the nightly automatic check uses (see
// perform_update_check() in herald.py) and records the result in the
// daemon's state file - so clicking this button updates the header badge
// immediately too, not just this tab's message, and there's exactly one
// implementation of the version-compare logic instead of a separate one
// living here in PHP.
//
// branch comes from the UI's dropdown (defaults to main) - whitelisted
// here too, not just in herald.py's own argparse choices=, so an invalid
// value gets a clean error from this layer instead of silently falling
// through to whatever argparse's exit code produces.
$branch = $_GET['branch'] ?? 'main';
if (!in_array($branch, ['main', 'develop'], true)) {
    herald_json_response(['success' => false, 'message' => 'Invalid branch'], 400);
}

herald_respond_from_cli(herald_run_sudo(['check-update', '--branch', $branch]));
