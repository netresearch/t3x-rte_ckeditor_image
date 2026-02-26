#!/bin/bash

# Install cron in web container (alternative to Ofelia)

cat > /etc/cron.d/typo3-scheduler << 'CRONEOF'
# TYPO3 Scheduler - runs every minute for v12
* * * * * www-data cd /var/www/html/v12 && [ -f vendor/bin/typo3 ] && vendor/bin/typo3 scheduler:run > /dev/null 2>&1

# Cache warmup every hour
0 * * * * www-data cd /var/www/html/v12 && vendor/bin/typo3 cache:warmup > /dev/null 2>&1
CRONEOF

chmod 0644 /etc/cron.d/typo3-scheduler
crontab /etc/cron.d/typo3-scheduler

# Start cron daemon
service cron start
