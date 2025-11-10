#!/usr/bin/env bash
# File: httpdocs/scripts/run_cron_with_log.sh
# Description: Generic wrapper to run a PHP CLI (or any) script and append stdout+stderr to
#              a log file under httpdocs/logs. Writes a timestamped run header in UK format (DD/MM/YYYY HH:MM:SS).
#              Intended for use with Plesk "Run a command" or direct crontab entries.
# Date: 10/11/2025 (UK format)
set -euo pipefail

# -------- CONFIG (edit if needed) ----------
# Maximum log filesize before rotate (bytes)
MAX_BYTES=5242880   # 5 MB
# Default logs directory relative to repo (adjust if your layout differs)
LOG_DIR_REL="httpdocs/logs"
# Candidate PHP binaries to auto-detect (common locations)
DEFAULT_PHP_BIN_CANDIDATES=(/usr/bin/php8.4 /usr/bin/php8 /usr/bin/php /usr/local/bin/php)
# ------------------------------------------------

usage() {
  cat <<USAGE
Usage: $0 <script-path> [label] [--php-binary=/full/path/to/php] [--log=/full/path/to/logfile]
  script-path   Full absolute path to PHP script (or other executable) to run (required)
  label         Optional short label used to name the log file (defaults to basename of script)
  --php-binary  Optional: absolute path to php cli binary to use (overrides auto-detect)
  --log         Optional: explicit logfile path. If not provided, logs go to <repo-root>/${LOG_DIR_REL}/cron_<label>.log
Example:
  sh $0 /var/www/vhosts/hosting215226.ae97b.netcup.net/eclectyc.energy/httpdocs/scripts/setup_carbon.php setup_carbon --php-binary=/usr/bin/php8.4
USAGE
  exit 1
}

# ---- parse args ----
if [ $# -lt 1 ]; then
  usage
fi

SCRIPT_PATH="$1"
LABEL="${2:-}"

shift 2 || true

# parse optional flags
PHP_BIN=""
LOG_FILE=""
while [ $# -gt 0 ]; do
  case "$1" in
    --php-binary=*)
      PHP_BIN="${1#--php-binary=}"
      shift
      ;;
    --log=*)
      LOG_FILE="${1#--log=}"
      shift
      ;;
    *)
      echo "Unknown option: $1" >&2
      usage
      ;;
  esac
done

# Validate script path
if [ ! -f "$SCRIPT_PATH" ]; then
  echo "ERROR: script not found: $SCRIPT_PATH" >&2
  exit 2
fi

# Determine label and default log file if not set
if [ -z "$LABEL" ]; then
  LABEL="$(basename "$SCRIPT_PATH" | sed -E 's/[^a-zA-Z0-9._-]/_/g')"
  LABEL="${LABEL%.*}"
fi

# detect repo root by assuming wrapper is in httpdocs/scripts; adapt if different
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
if [ -d "${SCRIPT_DIR}/../httpdocs" ]; then
  REPO_ROOT="$(cd "${SCRIPT_DIR}/.."; pwd)"
else
  REPO_ROOT="$(cd "${SCRIPT_DIR}/../.."; pwd || true)"
fi

LOG_FILE="${LOG_FILE:-${REPO_ROOT}/${LOG_DIR_REL}/cron_${LABEL}.log}"

# ensure log directory exists
mkdir -p "$(dirname "$LOG_FILE")"
chmod 750 "$(dirname "$LOG_FILE")" 2>/dev/null || true

# auto-detect PHP binary if not given
if [ -z "$PHP_BIN" ]; then
  for candidate in "${DEFAULT_PHP_BIN_CANDIDATES[@]}"; do
    if [ -x "$candidate" ]; then
      PHP_BIN="$candidate"
      break
    fi
  done
fi

# Decide if script is PHP by filename extension
IS_PHP=0
case "$SCRIPT_PATH" in
  *.php) IS_PHP=1 ;;
esac

# rotate log if >= MAX_BYTES (keep one previous copy)
if [ -f "$LOG_FILE" ]; then
  filesize=$(wc -c <"$LOG_FILE" 2>/dev/null || echo 0)
  if [ "$filesize" -ge "$MAX_BYTES" ]; then
    mv -f "$LOG_FILE" "${LOG_FILE}.1" 2>/dev/null || true
    : > "$LOG_FILE"
  fi
fi

# UK formatted header
echo "===========================================" >> "$LOG_FILE"
echo "Cron job run at: $(date '+%d/%m/%Y %H:%M:%S')" >> "$LOG_FILE"
echo "Script: ${SCRIPT_PATH}" >> "$LOG_FILE"
echo "User: $(whoami 2>/dev/null || echo unknown)  Host: $(hostname 2>/dev/null || echo unknown)" >> "$LOG_FILE"
echo "-------------------------------------------" >> "$LOG_FILE"

# Execute and capture both stdout and stderr
RC=0
if [ "$IS_PHP" -eq 1 ]; then
  if [ -n "$PHP_BIN" ]; then
    "${PHP_BIN}" -f "${SCRIPT_PATH}" >> "$LOG_FILE" 2>&1 || RC=$?
  else
    php -f "${SCRIPT_PATH}" >> "$LOG_FILE" 2>&1 || RC=$?
  fi
else
  "${SCRIPT_PATH}" >> "$LOG_FILE" 2>&1 || RC=$?
fi

echo "-------------------------------------------" >> "$LOG_FILE"
echo "Exit code: ${RC}" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"

exit $RC
