#!/bin/bash

# Stop all previous PHP Artisan processes
pkill -f "artisan" || echo "No previous processes to kill."

# Define commands that run indefinitely
commands=(
    "/usr/bin/php /var/www/volume/artisan tracker:binance-websocket 1m"
    "/usr/bin/php /var/www/volume/artisan tracker:binance-websocket 15m"
    "/usr/bin/php /var/www/volume/artisan tracker:binance-websocket 1h"
)

# Create logs directory if it doesn't exist
mkdir -p /var/www/volume/logs

# Function to monitor and restart commands if they stop
monitor_command() {
    local cmd="$1"
    local cmd_name=$(basename "$cmd")
    local pid_file="/var/www/volume/logs/${cmd_name}.pid"

    # Check if the process is alive
    if [ -f "$pid_file" ]; then
        local pid=$(cat "$pid_file")
        if ! kill -0 "$pid" 2>/dev/null; then
            echo "$(date): Process $cmd_name with PID $pid is no longer running. Removing stale PID file." >> /var/www/volume/logs/restart.log
            rm -f "$pid_file"
        fi
    fi

    # Start the process if not running
    if [ ! -f "$pid_file" ]; then
        echo "$(date): Starting command: $cmd" >> /var/www/volume/logs/restart.log
        nohup $cmd >> "/var/www/volume/logs/${cmd_name}.log" 2>&1 &
        echo $! > "$pid_file"
    else
        echo "$(date): Command is running: $cmd" >> /var/www/volume/logs/debug.log
    fi
}

# Monitor all commands
for cmd in "${commands[@]}"; do
    monitor_command "$cmd"
done

# Run the Laravel schedule:run command every 60 seconds
nohup bash -c "
while true; do
    /usr/bin/php /var/www/volume/artisan schedule:run >> /var/www/volume/logs/schedule.log 2>&1
    sleep 60
done
" > /dev/null 2>>"/var/www/volume/logs/schedule_error.log" & disown

echo "All commands are monitored and running in the background."
exit 0
