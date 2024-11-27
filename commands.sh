#!/bin/bash

# Stop all previous PHP Artisan processes
pkill -f "php artisan" || echo "No previous processes to kill."

# Define commands that run indefinitely
commands=(
    "/usr/bin/php  /var/www/volume/artisan tracker:binance-websocket 1m"
    "/usr/bin/php  /var/www/volume/artisan tracker:binance-websocket 15m"
    "/usr/bin/php  /var/www/volume/artisan tracker:binance-websocket 1h"
)

# Create logs directory if it doesn't exist
mkdir -p /var/www/volume/logs

# Function to run a single command indefinitely in the background
run_command_in_background() {
    local cmd="$1"

    while true; do
        if $cmd; then
            echo "Command succeeded: $cmd"
            break
        else
            echo "Command failed: $cmd. Retrying..."
        fi
    done
}

# Function to run a command every 60 seconds
run_command_every_60_seconds_in_background() {
    local cmd="$1"

    while true; do
        echo "Running scheduled command: $cmd"
        $cmd 2>>"/var/www/volume/logs/error60.log" >/dev/null
        sleep 60 # Wait 60 seconds before running again
    done
}

# Export the functions so they are available in subshells
export -f run_command_in_background
export -f run_command_every_60_seconds_in_background

# Run all background commands
for cmd in "${commands[@]}"; do
    nohup bash -c "run_command_in_background \"$cmd\"" 2>"/var/www/volume/logs/errorwebsock.log" >/dev/null &
done

# Run the scheduled command every 60 seconds in the background
nohup bash -c "run_command_every_60_seconds_in_background '/usr/bin/php  /var/www/volume/artisan schedule:run'" >/dev/null 2>>"/var/www/volume/logs/php_artisan_schedule:run_error.log" &

echo "All commands are running in the background."
exit 0
