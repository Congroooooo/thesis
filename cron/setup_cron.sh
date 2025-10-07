#!/bin/bash

# Script to set up cron job for void unpaid orders
# This script can be run inside the Docker container to manually set up the cron job

echo "Setting up cron job for void unpaid orders..."

# Add cron job to run every minute
echo "* * * * * cd /var/www/html && /usr/local/bin/php cron/void_unpaid_orders.php >> cron/void_cron.log 2>&1" | crontab -

# Start cron daemon if not running
if ! pgrep crond > /dev/null; then
    echo "Starting cron daemon..."
    crond -b
fi

# Display current cron jobs
echo "Current cron jobs:"
crontab -l

echo "Cron job setup completed!"
echo "The job will run every minute and logs will be written to cron/void_cron.log"