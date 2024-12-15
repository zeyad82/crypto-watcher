#!/bin/bash

# Array of timeframes
timeframes=("1m" "15m" "1h" "4h")

# Function to run the artisan command with a specific timeframe
run_artisan_command() {
    local timeframe=$1
    local line_number=$2

    # Run the artisan command and pipe its output to be displayed on the same line
    php artisan tracker:fetch-volumes --timeframe="$timeframe" 2>&1 | while IFS= read -r line; do
        # Move to the specific line number and clear the line
        tput cup "$line_number" 0
        tput el
        echo "[$timeframe] $line"
    done &
}

# Clear the screen to set up for progress display
clear

# Run the artisan commands in parallel, assigning each command a specific line on the terminal
line_number=0
for timeframe in "${timeframes[@]}"; do
    run_artisan_command "$timeframe" "$line_number"
    ((line_number++))
done

# Wait for all background processes to finish
wait

# Print completion message
tput cup "$line_number" 0
echo "All commands have completed."
