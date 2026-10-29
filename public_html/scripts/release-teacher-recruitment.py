#!/usr/bin/env python3
"""Deploy teacher recruitment and import the reviewed, private Sheets snapshot."""
import argparse
import json
import os
import pathlib
import sqlite3
import subprocess
import tempfile
import urllib.error
import urllib.request
import urllib.parse

ROOT = pathlib.Path(__file__).resolve().parents[1]
SNAPSHOT = ROOT / 'storage/app/teacher-imports/2026-10-26'
DIGEST = 'bd06a0224de270df910d8118b488eb305beffc3f3c5c540ec7636078a6791f72'
ORIGIN = 'https://erp.clapslearn.in'
HEADERS = {'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/130.0.0.0 Safari/537.36', 'Accept': 'application/json'}


def run(args, env=None, capture=False):
    result = subprocess.run(args, cwd=ROOT, env=env, check=True, text=True,
                            stdout=subprocess.PIPE if capture else None)
    return result.stdout if capture else None


def check_ready():
    with urllib.request.urlopen(urllib.request.Request(ORIGIN + '/ready', headers=HEADERS), timeout=180) as response:
        if json.load(response).get('status') != 'ok':
            raise RuntimeError('Database readiness failed')
    print('ERP readiness confirmed.', flush=True)


def verify_deployment(token):
    """Read table definitions without downloading production table rows."""
    check_ready()
    request = urllib.request.Request(ORIGIN + '/__cloudflare/database/schema', data=b'{}', method='POST',
        headers={**HEADERS, 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token})
    with urllib.request.urlopen(request, timeout=180) as response:
        objects = json.load(response)['schema']
    required = {
        'erp_teacher_matching_profiles': set(), 'erp_allocation_requests': set(),
        'erp_demos': {'starts_at', 'ends_at', 'feedback_outcome', 'enrollment_id'},
        'erp_teacher_candidates': {'merged_into_id', 'reviewed_at'},
        'erp_leads': {'learning_requirement', 'concern', 'about_student', 'about_parent'},
        'erp_lead_activities': {'actor_id', 'kind', 'occurred_at'},
    }
    definitions = {obj['name']: obj['sql'] for obj in objects if obj['type'] == 'table'}
    with sqlite3.connect(':memory:') as db:
        for table, columns in required.items():
            if table not in definitions:
                raise RuntimeError('Required deployment table is missing: ' + table)
            db.execute(definitions[table])
            actual = {row[1] for row in db.execute('PRAGMA table_info("' + table + '")')}
            if not columns <= actual:
                raise RuntimeError('Required deployment columns are missing: ' + table)
    print('ERP deployment schema verified. No production table rows downloaded or changed.', flush=True)


def restart(token):
    print('Restarting ERP and waiting for database readiness...', flush=True)
    request = urllib.request.Request(ORIGIN + '/__cloudflare/restart', data=b'', method='POST',
                                    headers={**HEADERS, 'Authorization': 'Bearer ' + token})
    with urllib.request.urlopen(request, timeout=180) as response:
        result = json.load(response)
    if not result.get('restarted') or not result.get('ready'):
        raise RuntimeError('Container restart did not confirm readiness')
    check_ready()


def deploy(token):
    backup_dir = ROOT / 'storage/app/teacher-release-backups'
    backup_dir.mkdir(mode=0o700, parents=True, exist_ok=True)
    backup = backup_dir / ('before-release-' + str(__import__('time').time_ns()) + '.sql')
    run(['npx', '--no-install', 'wrangler', 'd1', 'export', 'clapslearn-erp', '--remote', '--output', str(backup)])
    run(['npx', '--no-install', 'wrangler', 'd1', 'migrations', 'apply', 'clapslearn-erp', '--remote'])
    run(['npm', 'run', 'cf:deploy'])
    restart(token)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--unit', type=int, default=1, help='Manager unit for authorization and audit; teacher pool is shared')
    parser.add_argument('--actor', type=int, default=1)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument('--import-only', action='store_true', help='Check readiness and resume after a completed deployment')
    mode.add_argument('--verify-only', action='store_true', help='Verify historical teacher import counts')
    mode.add_argument('--verify-deployment-only', action='store_true', help='Read readiness and deployment schema only; no deploy, restart or import')
    mode.add_argument('--restart-only', action='store_true', help='Restart and check readiness without deploying or importing')
    mode.add_argument('--deploy-only', action='store_true', help='Back up, migrate and deploy matching/review workflows without rerunning the Sheets import')
    args = parser.parse_args()
    os.umask(0o077)
    secret_path = pathlib.Path.home() / '.codex/private/clapslearn-erp/worker-secrets.json'
    secrets = json.loads(secret_path.read_text())
    if args.verify_deployment_only:
        verify_deployment(secrets['INTERNAL_TOKEN'])
        return
    if args.restart_only:
        restart(secrets['INTERNAL_TOKEN'])
        return
    with tempfile.TemporaryDirectory(prefix='claps-teacher-release-') as temporary:
        env = dict(os.environ)
        env.update({'APP_ENV': 'production', 'APP_URL': ORIGIN, 'APP_KEY': secrets['APP_KEY'],
                    'DB_CONNECTION': 'd1', 'CLOUDFLARE_GATEWAY_URL': ORIGIN,
                    'CLOUDFLARE_INTERNAL_TOKEN': secrets['INTERNAL_TOKEN'],
                    'APP_CONFIG_CACHE': str(pathlib.Path(temporary) / 'config.php'),
                    'XDG_CONFIG_HOME': temporary})
        if args.deploy_only:
            deploy(secrets['INTERNAL_TOKEN'])
            verify_deployment(secrets['INTERNAL_TOKEN'])
            print('ERP deployed and verified. Existing Sheets data was not reimported.', flush=True)
            return
        # Preview does not open the database. Guard against missing or changed chunks.
        if not args.verify_only:
            preview = json.loads(run(['php', 'artisan', 'erp:teacher-sheets-import', str(SNAPSHOT)], env, capture=True))
            if preview['digest'] != DIGEST or preview['files'] != 103:
                raise RuntimeError('Teacher snapshot differs from the reviewed download')
            print(json.dumps(preview, indent=2), flush=True)
            if not args.import_only:
                deploy(secrets['INTERNAL_TOKEN'])
            else:
                check_ready()
            run(['php', 'artisan', 'erp:teacher-sheets-import', str(SNAPSHOT),
                 '--unit=' + str(args.unit), '--actor=' + str(args.actor), '--execute',
                 '--confirm-digest=' + DIGEST, '--target-database=' + str(ROOT / 'storage/app/d1/working.sqlite'),
                 '--allow-production'], env)
        verification = r'''
$db=\Illuminate\Support\Facades\DB::class;
$business=(int)config('erp.business_id');
$counts=[];
foreach(['APPLIED','CONTACTED','SELECTED','REGISTERED','APPROVED','REJECTED','REVIEW'] as $stage) {
    $counts[$stage]=$db::table('erp_teacher_candidates')->where('business_id',$business)->where('stage',$stage)->count();
}
$sources=$db::table('erp_teacher_candidate_sources as s')->join('erp_teacher_candidates as c','c.id','=','s.candidate_id')->where('c.business_id',$business)->count();
$linked=$db::table('erp_teacher_candidates')->where('business_id',$business)->whereNotNull('teacher_id')->count();
echo json_encode(['stages'=>$counts,'source_rows'=>$sources,'linked_existing_teachers'=>$linked],JSON_PRETTY_PRINT).PHP_EOL;
if($sources<93447)throw new \RuntimeException('Teacher source count is incomplete');
'''
        run(['php', 'artisan', 'tinker', '--execute=' + verification], env)
        print('Teacher recruitment deployed and all 93,447 source rows verified. Open Teacher recruitment in the ERP.', flush=True)


if __name__ == '__main__':
    try:
        main()
    except urllib.error.HTTPError as error:
        endpoint = urllib.parse.urlparse(error.url).path
        ray = error.headers.get('CF-Ray', 'not supplied') if error.headers else 'not supplied'
        print('HTTP status: ' + str(error.code) + '; endpoint: ' + endpoint + '; Cloudflare Ray: ' + ray, flush=True)
        raise SystemExit('Release stopped before the next step. Send the HTTP status and Ray ID above. No redeployment is needed to diagnose this response.')
    except (OSError, ValueError, KeyError, RuntimeError, subprocess.CalledProcessError, urllib.error.URLError) as error:
        # Never print secret-bearing request headers or database values.
        raise SystemExit('Teacher release stopped (' + type(error).__name__ + '). If deployment and readiness succeeded, run --verify-deployment-only. Use --import-only only to resume an intentionally requested teacher import.')
