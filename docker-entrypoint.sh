#!/bin/bash

# Start cron daemon in the background
crond -b

# Start PHP-FPM in the foreground
php-fpm