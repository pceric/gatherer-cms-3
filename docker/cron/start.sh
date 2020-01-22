#!/bin/bash

echo "Gatherer cron service starting (interval: ${SLEEP_SECONDS:-900}s)"

child_pid=""

terminate() {
    echo "Received signal, shutting down"
    [ -n "$child_pid" ] && kill "$child_pid" 2>/dev/null
    exit 0
}

trap terminate TERM QUIT

while true; do
    sleep "${SLEEP_SECONDS:-900}" &
    child_pid=$!
    wait "$child_pid"
    child_pid=""

    echo "[$(date -Iseconds)] Running app:gather..."
    php /var/www/html/bin/console app:gather --no-interaction 2>&1 &
    child_pid=$!
    wait "$child_pid"
    child_pid=""
done
