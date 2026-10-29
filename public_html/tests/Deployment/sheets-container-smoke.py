#!/usr/bin/env python3
"""Exercise the built image with no network, ports, credentials, or host DB mounts.

Usage: python3 tests/Deployment/sheets-container-smoke.py [local-image-tag]
The disposable container is removed in finally, including on failure.
"""
import json
import subprocess
import sys
import time
import uuid

image = sys.argv[1] if len(sys.argv) > 1 else "claps-sheet-transition:local"
name = "claps-sheet-smoke-" + uuid.uuid4().hex[:12]


def run(*args):
    return subprocess.check_output(args, text=True, stderr=subprocess.STDOUT).strip()


def inside(*args):
    return run("docker", "exec", name, *args)


def scheduler_pid():
    return inside("python3", "-c", """
from pathlib import Path
for p in Path('/proc').glob('[0-9]*/cmdline'):
    try:
        args = p.read_bytes().split(b'\\0')
        if b'artisan' in args and b'schedule:work' in args:
            print(p.parent.name)
    except (FileNotFoundError, PermissionError, ProcessLookupError):
        pass
""")


try:
    run("docker", "run", "--detach", "--name", name, "--network", "none", "--platform", "linux/amd64",
        "--env", "APP_ENV=testing", "--env", "APP_DEBUG=false",
        "--env", "APP_KEY=01234567890123456789012345678901",  # synthetic key, never a real secret
        "--env", "DB_CONNECTION=sqlite", "--env", "DB_DATABASE=/tmp/bridge-smoke/records.sqlite",
        "--env", "SESSION_DRIVER=array", "--env", "CACHE_DRIVER=file",
        "--env", "ERP_SHEETS_TRANSITION_ENABLED=true", "--env", "ERP_DATA_AUTHORITY=sheets",
        "--entrypoint", "/bin/sh", image, "-c",
        "mkdir /tmp/bridge-smoke && touch /tmp/bridge-smoke/records.sqlite && chown -R www-data:www-data /tmp/bridge-smoke && php artisan migrate --force --no-interaction && exec /usr/local/bin/start-container")
    deadline = time.monotonic() + 60
    while True:
        if run("docker", "inspect", "--format", "{{.State.Running}}", name) != "true":
            raise RuntimeError("Container exited: " + run("docker", "logs", "--tail", "30", name))
        try:
            ready = json.loads(inside("curl", "--silent", "--fail", "http://127.0.0.1:8080/ready"))
            before = scheduler_pid()
            if ready["status"] == "ok" and before:
                break
        except (subprocess.CalledProcessError, json.JSONDecodeError):
            pass
        if time.monotonic() > deadline:
            raise RuntimeError("Container startup failed: " + run("docker", "logs", "--tail", "30", name))
        time.sleep(1)
    schedule = inside("php", "artisan", "schedule:list", "--no-ansi")
    assert "erp:sheets-refresh all --publish" in schedule, schedule
    assert "erp:sheets-prune" in schedule, schedule
    assert "integration:deliver-outbox" not in schedule, schedule
    inside("python3", "-c", f"import os, signal; os.kill({int(before)}, signal.SIGKILL)")
    deadline = time.monotonic() + 15
    while time.monotonic() < deadline:
        after = scheduler_pid()
        if after and after != before:
            break
        time.sleep(1)
    else:
        raise RuntimeError("Supervisor did not restart the scheduler")
    assert json.loads(inside("curl", "--silent", "--fail", "http://127.0.0.1:8080/ready"))["status"] == "ok"
    print(json.dumps({"status":"passed", "database":"disposable SQLite", "network":"none", "scheduler_restart":True, "legacy_delivery_scheduled":False}))
finally:
    subprocess.run(["docker", "rm", "--force", name], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, check=False)
