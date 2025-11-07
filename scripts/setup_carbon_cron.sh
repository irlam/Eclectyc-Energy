#!/bin/bash
# eclectyc-energy/scripts/setup_carbon_cron.sh
# Sets up cron jobs for carbon intensity data fetching

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "Setting up Carbon Intensity data fetching cron jobs..."
echo "Project directory: $PROJECT_DIR"

# Create the cron job entries
CRON_JOBS="
# Carbon Intensity Data Fetching - Eclectyc Energy
# Fetch current carbon intensity every 30 minutes
*/30 * * * * cd $PROJECT_DIR && php scripts/fetch_carbon_intensity.php current >> logs/carbon-fetch.log 2>&1

# Fetch daily forecast at 6 AM
0 6 * * * cd $PROJECT_DIR && php scripts/fetch_carbon_intensity.php forecast >> logs/carbon-fetch.log 2>&1

# Weekly cleanup of old data (keep 90 days)
0 2 * * 0 cd $PROJECT_DIR && php scripts/fetch_carbon_intensity.php cleanup >> logs/carbon-fetch.log 2>&1
"

# Check if running as root or with sudo
if [ "$EUID" -eq 0 ]; then
    echo "Warning: Running as root. Cron jobs will be installed for root user."
    echo "Consider running as the web server user instead."
fi

# Add to current user's crontab
echo "Adding cron jobs to current user's crontab..."
(crontab -l 2>/dev/null; echo "$CRON_JOBS") | crontab -

if [ $? -eq 0 ]; then
    echo "✓ Cron jobs successfully added!"
    echo ""
    echo "Current crontab entries:"
    crontab -l | grep -A 5 -B 1 "Carbon Intensity"
else
    echo "✗ Failed to add cron jobs. Please add manually:"
    echo "$CRON_JOBS"
fi

echo ""
echo "To view cron job logs:"
echo "  tail -f $PROJECT_DIR/logs/carbon-fetch.log"
echo ""
echo "To remove these cron jobs later:"
echo "  crontab -e  (then delete the Carbon Intensity lines)"
echo ""
echo "To test manually:"
echo "  cd $PROJECT_DIR && php scripts/fetch_carbon_intensity.php summary"