#!/bin/sh
# Runs on every start of the dev container: serves the application on port 8000
# (forwarded by VS Code / Codespaces) and runs the scheduler, both in the
# background with logs in /tmp.
cd /workspace/backend
nohup php artisan serve --host=0.0.0.0 --port=8000 > /tmp/lcf-serve.log 2>&1 &
nohup php artisan schedule:work > /tmp/lcf-schedule.log 2>&1 &
i=0
until curl -fs -o /dev/null http://127.0.0.1:8000/up || [ "$i" -ge 30 ]; do sleep 1; i=$((i + 1)); done
echo "Application listening on port 8000 (logs: /tmp/lcf-serve.log)."
