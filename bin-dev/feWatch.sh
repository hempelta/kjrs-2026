#!/bin/sh

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Path to webpack.config.js relative to the root directory of the project
CONFIG_PATH="Build/Default/webpack.config.js"

# Check whether webpack.config.js exists
if [ -f "$CONFIG_PATH" ]; then
    # Check whether an 'npm run watch' process is already running
    WATCH_PID=$(ps ax | grep -v grep | grep "npm run watch" | awk '{print $1}')
    if [ -z "$WATCH_PID" ]; then
        echo "${GREEN}Start File Watcher in the background …${NC}"

        # Start the npm command and do not redirect its output
        npm --prefix "Build/Default" run watch &

        # Save the PID of the npm process
        PID=$!
        echo $PID > "./bin-dev/watch.pid"

        echo "File watcher started with PID $PID."
        echo "To terminate the process, run './bin-dev/stopFeWatch.sh' or 'composer fe-watch-stop'."

        # Create a stop script in the bin directory
        STOP_SCRIPT="./bin-dev/stopFeWatch.sh"
        cat > "$STOP_SCRIPT" << 'EOF'
#!/bin/sh

# Function for recursively terminating all child processes
kill_process_tree() {
    local pid=$1
    local children=$(pgrep -P $pid)

    # Recursively terminate all child processes
    for child in $children; do
        kill_process_tree $child
    done

    # Then terminate the process itself
    if ps -p $pid > /dev/null 2>&1; then
        echo "Terminate process $pid …"
        kill $pid 2>/dev/null || kill -9 $pid 2>/dev/null
    fi
}

EOF
        echo "PID=$PID" >> "$STOP_SCRIPT"
        cat >> "$STOP_SCRIPT" << 'EOF'

echo "Terminate file watcher with PID $PID and all child processes …"
kill_process_tree $PID
rm './bin-dev/watch.pid' 2>/dev/null
echo "${RED}File Watcher has been terminated.${NC}"
rm "$0"
EOF
        chmod +x "$STOP_SCRIPT"
    else
        echo "File Watcher is already running with PID $WATCH_PID."
    fi
else
    echo "The file $CONFIG_PATH could not be found."
fi
