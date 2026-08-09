#!/usr/bin/env bash
# herald uninstall script
# Usage: curl -fsSL -H "Cache-Control: no-cache" https://raw.githubusercontent.com/N6LKA/AllStar-Herald/main/uninstall.sh | sudo bash
#   (the "sudo bash <(curl ...)" process-substitution form fails with
#    /dev/fd/63: No such file or directory on some systems — pipe instead.
#    See install.sh's header comment for why this bootstrap fetch uses
#    raw.githubusercontent.com rather than GitHub's Contents API.)
#
# Options (pass after "--" when piping): --purge-config  --purge-piper  --purge-all
#   e.g. curl -fsSL ... | sudo bash -s -- --purge-all

set -euo pipefail

# As of the AllStar-Herald rename, daemon code and config/state share one
# directory (see install.sh's "Path layout" comment) - CONFIG_DIR and
# INSTALL_DIR are the same path. Only the specific daemon-code files are
# removed by default; config/state/announcements/node-id are only wiped with
# --purge-config, same preserve-by-default behavior as before the rename.
INSTALL_DIR="/etc/asterisk/scripts/herald"
CONFIG_DIR="$INSTALL_DIR"
SERVICE_FILE="/etc/systemd/system/herald.service"
HERALD_BIN="/usr/local/bin/herald"
WEB_DIR="/var/www/html/herald"
SUDOERS_WEB="/etc/sudoers.d/herald-web"
MENU_INI="/etc/allmon3/menu.ini"
ALLMON3_CUSTOM_CSS="/etc/allmon3/custom.css"
ALLMON3_WEB_ROOT="/usr/share/allmon3"
SUPERMON_DIR="/var/www/html/supermon"
SUPERMON_FOOTER="$SUPERMON_DIR/footer.inc"
PIPER_DIR="/opt/piper"

# Pre-rename artifact names — cleaned up unconditionally below so this script
# also fully uninstalls a system that never ran the migrated install.sh (e.g.
# went straight from an old asl3-herald install to uninstall.sh).
OLD_INSTALL_DIR="/usr/local/bin/asl3-herald"
OLD_CONFIG_DIR="/etc/asterisk/scripts/asl3-herald"
OLD_SERVICE_FILE="/etc/systemd/system/asl3-herald.service"
OLD_WEB_DIR="/var/www/html/asl3-herald"
OLD_SUDOERS_WEB="/etc/sudoers.d/asl3-herald-web"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
info()    { echo -e "${GREEN}[INFO]${NC}  $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
error()   { echo -e "${RED}[ERROR]${NC} $*"; exit 1; }

if [[ $EUID -ne 0 ]]; then
    error "This script must be run as root. Use: curl -fsSL ... | sudo bash"
fi

PURGE_CONFIG=false
PURGE_PIPER=false
for arg in "$@"; do
    case "$arg" in
        --purge-config) PURGE_CONFIG=true ;;
        --purge-piper)  PURGE_PIPER=true ;;
        --purge-all)    PURGE_CONFIG=true; PURGE_PIPER=true ;;
        *) warn "Unknown option: $arg" ;;
    esac
done

echo ""
echo "  herald uninstaller"
echo "  https://github.com/N6LKA/AllStar-Herald"
echo ""

# ── Service ────────────────────────────────────────────────────────────────────

if [[ -f "$SERVICE_FILE" ]]; then
    info "Stopping and disabling herald service..."
    systemctl stop herald 2>/dev/null || true
    systemctl disable herald 2>/dev/null || true
    rm -f "$SERVICE_FILE"
fi
if [[ -f "$OLD_SERVICE_FILE" ]]; then
    info "Stopping and disabling legacy asl3-herald service..."
    systemctl stop asl3-herald 2>/dev/null || true
    systemctl disable asl3-herald 2>/dev/null || true
    rm -f "$OLD_SERVICE_FILE"
fi
systemctl daemon-reload 2>/dev/null || true

# ── Daemon + CLI ───────────────────────────────────────────────────────────────
# Only the daemon-code files, not the whole directory - it's shared with
# config/state/announcements now, which are preserved unless --purge-config.

info "Removing daemon files and herald command..."
rm -f "$INSTALL_DIR/herald.py" "$INSTALL_DIR/version.txt" "$INSTALL_DIR/piper-voices-catalog.json"
rm -f "$HERALD_BIN"
[[ -d "$OLD_INSTALL_DIR" ]] && rm -rf "$OLD_INSTALL_DIR"

# ── Web UI + sudoers ───────────────────────────────────────────────────────────

if [[ -d "$WEB_DIR" ]]; then
    info "Removing web UI ($WEB_DIR)..."
    rm -rf "$WEB_DIR"
fi
[[ -d "$OLD_WEB_DIR" ]] && rm -rf "$OLD_WEB_DIR"

if [[ -f "$SUDOERS_WEB" ]]; then
    info "Removing sudoers rule ($SUDOERS_WEB)..."
    rm -f "$SUDOERS_WEB"
fi
rm -f "$OLD_SUDOERS_WEB"

# ── Allmon3 / Supermon integration ─────────────────────────────────────────────
# Surgical removal: only strips what herald's installer added, leaving
# the rest of each file (and any other customizations) untouched.

if [[ -f "$MENU_INI" ]] && grep -q "^\[Herald\]" "$MENU_INI"; then
    info "Removing [Herald] section from menu.ini..."
    cp "$MENU_INI" "$MENU_INI.bak.$(date +%Y%m%d-%H%M%S)"
    awk '
    BEGIN { skip = 0 }
    /^\[Herald\]$/ { skip = 1; next }
    skip && /^\[/ { skip = 0 }
    !skip { print }
    ' "$MENU_INI" > "$MENU_INI.tmp" && mv "$MENU_INI.tmp" "$MENU_INI"
    warn "Restart allmon3 to apply: sudo systemctl restart allmon3"
fi

if [[ -f "$ALLMON3_CUSTOM_CSS" ]] && grep -qE 'a\[href\*="(asl3-)?herald"\]' "$ALLMON3_CUSTOM_CSS"; then
    info "Removing herald login-hide rule from Allmon3 custom.css..."
    cp "$ALLMON3_CUSTOM_CSS" "$ALLMON3_CUSTOM_CSS.bak.$(date +%Y%m%d-%H%M%S)"
    grep -vE '/\* (asl3-)?herald: hide sidebar link until logged into Allmon3 \*/' "$ALLMON3_CUSTOM_CSS" | \
        grep -vE 'body\.logged-out a\[href\*="(asl3-)?herald"\] \{ display: none !important; \}' \
        > "$ALLMON3_CUSTOM_CSS.tmp" && mv "$ALLMON3_CUSTOM_CSS.tmp" "$ALLMON3_CUSTOM_CSS"
fi

if [[ -f "$ALLMON3_WEB_ROOT/herald.html" ]]; then
    info "Removing Allmon3 Announcement Settings page..."
    rm -f "$ALLMON3_WEB_ROOT/herald.html"
fi
[[ -f "$ALLMON3_WEB_ROOT/asl3-herald.html" ]] && rm -f "$ALLMON3_WEB_ROOT/asl3-herald.html"

if [[ -f "$SUPERMON_FOOTER" ]] && grep -q "herald.php" "$SUPERMON_FOOTER"; then
    info "Removing herald link from Supermon footer..."
    cp "$SUPERMON_FOOTER" "$SUPERMON_FOOTER.bak.$(date +%Y%m%d-%H%M%S)"
    grep -v "herald.php" "$SUPERMON_FOOTER" > "$SUPERMON_FOOTER.tmp" \
        && mv "$SUPERMON_FOOTER.tmp" "$SUPERMON_FOOTER"
    chown www-data:www-data "$SUPERMON_FOOTER" 2>/dev/null || true
fi

if [[ -f "$SUPERMON_DIR/herald.php" ]]; then
    info "Removing Supermon Announcement Settings page..."
    rm -f "$SUPERMON_DIR/herald.php"
fi
[[ -f "$SUPERMON_DIR/asl3-herald.php" ]] && rm -f "$SUPERMON_DIR/asl3-herald.php"

SUPERMON_WEATHER_LINK="/usr/local/sbin/supermon/weather.sh"
SUPERMON_WEATHER_TARGET="$CONFIG_DIR/supermon-weather.sh"
if [[ -L "$SUPERMON_WEATHER_LINK" ]] && [[ "$(readlink "$SUPERMON_WEATHER_LINK")" == "$SUPERMON_WEATHER_TARGET" || "$(readlink "$SUPERMON_WEATHER_LINK")" == "$OLD_CONFIG_DIR/supermon-weather.sh" ]]; then
    if [[ -f "${SUPERMON_WEATHER_LINK}.bak-original" ]]; then
        info "Restoring Supermon's original weather.sh..."
        mv "${SUPERMON_WEATHER_LINK}.bak-original" "$SUPERMON_WEATHER_LINK"
    else
        info "Removing Supermon weather.sh symlink (no original backup found to restore)..."
        rm -f "$SUPERMON_WEATHER_LINK"
    fi
fi

# ── tmpfiles.d entry ────────────────────────────────────────────────────────────

rm -f /etc/tmpfiles.d/herald.conf /etc/tmpfiles.d/asl3-herald.conf

# ── Config / announcements / state (preserved by default) ─────────────────────

if [[ "$PURGE_CONFIG" == "true" ]]; then
    if [[ -d "$CONFIG_DIR" ]]; then
        warn "Purging config, announcements, and state ($CONFIG_DIR)..."
        rm -rf "$CONFIG_DIR"
    fi
    [[ -d "$OLD_CONFIG_DIR" ]] && { warn "Purging legacy config directory ($OLD_CONFIG_DIR)..."; rm -rf "$OLD_CONFIG_DIR"; }
elif [[ -d "$CONFIG_DIR" ]]; then
    info "Config, announcements, and state preserved at: $CONFIG_DIR"
    info "  (reinstalling later will pick this config back up)"
    info "  Remove manually with: sudo rm -rf $CONFIG_DIR"
elif [[ -d "$OLD_CONFIG_DIR" ]]; then
    info "Legacy config, announcements, and state preserved at: $OLD_CONFIG_DIR"
    info "  (reinstalling later will migrate this config automatically)"
    info "  Remove manually with: sudo rm -rf $OLD_CONFIG_DIR"
fi

# ── Piper TTS binary (preserved by default — large download) ──────────────────
# Voices live in the shared /var/lib/piper-tts (SkywarnPlus-NG / asl3-tts also
# use it) and are never touched here, purge or not - removing them would break
# whichever of those is still installed.

if [[ "$PURGE_PIPER" == "true" ]]; then
    if [[ -d "$PIPER_DIR" ]]; then
        warn "Purging Piper TTS binary ($PIPER_DIR)..."
        rm -rf "$PIPER_DIR"
    fi
elif [[ -d "$PIPER_DIR" ]]; then
    info "Piper TTS binary preserved at: $PIPER_DIR"
    info "  Remove manually with: sudo rm -rf $PIPER_DIR"
fi

# ── Summary ────────────────────────────────────────────────────────────────────

echo ""
echo -e "  ${GREEN}herald has been uninstalled.${NC}"
echo ""
echo "  Options for next time (pass after --  when piping through sudo bash -s):"
echo "    --purge-config   also remove config, announcements, and state"
echo "    --purge-piper    also remove the Piper TTS binary (voices are shared"
echo "                     with SkywarnPlus-NG/asl3-tts and always preserved)"
echo "    --purge-all      both of the above"
echo ""
