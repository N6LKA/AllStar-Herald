<?php
// Allmon3-only pass-through - deliberately unauthenticated. See
// herald-common.php's session-helper comments for why: Allmon3's own
// session cookie can't be verified server-side without modifying Allmon3
// itself, so this preserves Allmon3's pre-existing page-gated-only trust
// model exactly as it was before Herald's login system existed. Supermon
// and the standalone UI use the real, session-protected copy at
// web/api/add_scheduled.php instead.
require __DIR__ . '/../api/_impl/add_scheduled.php';
