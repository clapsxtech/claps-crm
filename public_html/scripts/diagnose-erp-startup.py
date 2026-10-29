#!/usr/bin/env python3
"""Capture Worker startup logs and reproduce the failed ERP restart."""
import json
import os
import pathlib
import re
import signal
import subprocess
import threading
import urllib.error
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[1]
ORIGIN = 'https://erp.clapslearn.in'
HEADERS = {'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/130.0.0.0 Safari/537.36', 'Accept': 'application/json'}


def main():
    secrets = json.loads((pathlib.Path.home() / '.codex/private/clapslearn-erp/worker-secrets.json').read_text())
    redactions = [v for v in secrets.values() if isinstance(v, str) and len(v) >= 8]
    def emit(value):
        value = re.sub(r'\x1b\[[0-9;]*m', '', str(value))
        for secret in redactions:
            value = value.replace(secret, '[REDACTED]')
        value = re.sub(r'(?i)(Bearer\s+)[^\s"\']+', r'\1[REDACTED]', value)
        print(value, flush=True)

    connected = threading.Event()
    process = subprocess.Popen(['npx', '--no-install', 'wrangler', 'tail', 'clapslearn-logs', '--format', 'pretty'],
                               cwd=ROOT, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True,
                               bufsize=1, start_new_session=True, env={**os.environ, 'NO_COLOR': '1'})
    def stream():
        for line in process.stdout:
            emit(line.rstrip())
            if 'connected to' in line.lower():
                connected.set()
    reader = threading.Thread(target=stream, daemon=True)
    reader.start()
    try:
        emit('Connecting to Worker logs (up to 60 seconds)...')
        if not connected.wait(60):
            emit('Log connection was not confirmed. No restart was requested. Send the Wrangler output above.')
            return 1
        request = urllib.request.Request(ORIGIN + '/__cloudflare/restart', data=b'', method='POST',
                                        headers={**HEADERS, 'Authorization': 'Bearer ' + secrets['INTERNAL_TOKEN']})
        emit('Reproducing the restart failure while logs are connected...')
        try:
            with urllib.request.urlopen(request, timeout=180) as response:
                emit('Restart response: ' + response.read(2000).decode(errors='replace'))
        except urllib.error.HTTPError as error:
            emit('Restart HTTP status: ' + str(error.code) + '; Ray: ' + error.headers.get('CF-Ray', 'not supplied'))
            # Show only Cloudflare's numeric error code, not an HTML page or request headers.
            body = error.read(4096).decode(errors='replace')
            match = re.search(r'(?:error code:|Error)\s*(1[0-9]{3})', body, re.I)
            if match:
                emit('Cloudflare error: ' + match.group(1))
        except (urllib.error.URLError, TimeoutError):
            emit('Restart request timed out or failed to connect; check the Worker exception below.')
        emit('Collecting remaining Worker logs for 15 seconds...')
        threading.Event().wait(15)
        emit('Diagnostic capture finished. Send the exception and error lines above. No teacher import was run.')
        return 0
    finally:
        if process.poll() is None:
            os.killpg(process.pid, signal.SIGINT)
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                os.killpg(process.pid, signal.SIGTERM)
        reader.join(timeout=2)


if __name__ == '__main__':
    try:
        raise SystemExit(main())
    except (OSError, ValueError, KeyError) as error:
        raise SystemExit('Diagnostic could not start (' + type(error).__name__ + '). No import was run.')
