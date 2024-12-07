#!/bin/bash

# Stop all previous PHP Artisan processes and any running commands.sh
pkill -f "artisan tracker:binance-websocket" || echo "$(date): No previous artisan WebSocket processes to kill." > /var/www/volume/logs/restart.log

# Define commands to run
commands=(
    "/usr/bin/php /var/www/volume/artisan tracker:binance-websocket 1m"
    "/usr/bin/php /var/www/volume/artisan tracker:binance-websocket 15m"
    "/usr/bin/php /var/www/volume/artisan tracker:binance-websocket 1h"
)

# Create logs directory if it doesn't exist
mkdir -p /var/www/volume/logs

# Start each WebSocket command
for cmd in "${commands[@]}"; do
    cmd_name=$(basename "$cmd")
    log_file="/var/www/volume/logs/${cmd_name}.log"
    echo "$(date): Starting command: $cmd" > "$log_file"
    nohup $cmd > "$log_file" 2>&1 &
done

echo "$(date): All commands started." > /var/www/volume/logs/commands.log
exit 0
