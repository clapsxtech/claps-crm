#!/usr/bin/env python3
"""Private D1 backups and offline integrity drills. Never restores into production."""
import argparse, datetime, hashlib, json, os, pathlib, re, shutil, sqlite3, subprocess, tempfile
ROOT = pathlib.Path(__file__).resolve().parents[1]
DEFAULT = pathlib.Path.home()/'.codex/private/clapslearn-erp/backups'
REQUIRED = ('users','erp_units','erp_memberships','erp_students','erp_teachers','erp_ledger','erp_audit')

def digest(path):
    with path.open('rb') as source:
        return hashlib.file_digest(source, 'sha256').hexdigest()

def verify(directory):
    directory=pathlib.Path(directory).resolve()
    manifest=json.loads((directory/'manifest.json').read_text())
    dump=directory/'database.sql'
    if dump.is_symlink() or digest(dump)!=manifest['sha256']: raise ValueError('Backup checksum mismatch')
    with tempfile.TemporaryDirectory(prefix='claps-recovery-') as temporary:
        db=sqlite3.connect(str(pathlib.Path(temporary)/'recovered.sqlite'))
        try:
            # Accept only an operator-created SQLite export. Prevent filesystem access through SQL.
            denied={sqlite3.SQLITE_ATTACH,sqlite3.SQLITE_DETACH}
            db.set_authorizer(lambda action,*args: sqlite3.SQLITE_DENY if action in denied or (action==sqlite3.SQLITE_FUNCTION and 'load_extension' in args) else sqlite3.SQLITE_OK)
            db.executescript(dump.read_text())
            if db.execute('PRAGMA integrity_check').fetchone()[0]!='ok': raise ValueError('SQLite integrity check failed')
            if db.execute('PRAGMA foreign_key_check').fetchone(): raise ValueError('Foreign key check failed')
            counts={table:db.execute('SELECT COUNT(*) FROM "'+table+'"').fetchone()[0] for table in REQUIRED}
            balances=[list(row) for row in db.execute('SELECT unit_id,kind,COUNT(*),SUM(amount_paise) FROM erp_ledger GROUP BY unit_id,kind ORDER BY unit_id,kind')]
            if 'counts' in manifest and (counts!=manifest['counts'] or balances!=manifest['ledger_totals']): raise ValueError('Recovered totals differ from verified manifest')
            keys=sorted({row[0].removeprefix('/uploads/') for row in db.execute("SELECT profile_photo FROM users WHERE profile_photo LIKE '/uploads/profile_photo/%'")})
            for key in keys:
                if not re.fullmatch(r'profile_photo/[a-f0-9-]{36}\.(?:jpe?g|png|gif|webp)',key): raise ValueError('Unexpected upload key')
            if 'uploads' in manifest:
                if set(manifest['uploads'])!=set(keys): raise ValueError('Incomplete upload manifest')
                for key,checksum in manifest['uploads'].items():
                    image=directory/'uploads'/key
                    if image.is_symlink() or digest(image)!=checksum: raise ValueError('Upload checksum mismatch')
            return {'integrity':'ok','counts':counts,'ledger_totals':balances,'upload_keys':keys}
        finally: db.close()

def backup(destination):
    os.umask(0o077)
    destination=pathlib.Path(destination).expanduser().resolve()
    destination.mkdir(parents=True,exist_ok=True);destination.chmod(0o700)
    stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
    directory=destination/('d1-'+stamp);directory.mkdir(mode=0o700)
    target=directory/'database.sql'
    command=['npx','--no-install','wrangler','d1','export','clapslearn-erp','--remote','--output',str(target)]
    result=subprocess.run(command,cwd=ROOT,capture_output=True,text=True)
    if result.returncode: raise RuntimeError('D1 export failed; check Wrangler authentication. No backup was certified.')
    target.chmod(0o600)
    manifest={'format':1,'database':'clapslearn-erp','created_at':stamp,'sha256':digest(target),'scope':'D1 only; R2 objects and secrets are not included','retention_days':30}
    (directory/'manifest.json').write_text(json.dumps(manifest,indent=2))
    report=verify(directory);manifest.update(report)
    uploads={}
    for key in report['upload_keys']:
        image=directory/'uploads'/key;image.parent.mkdir(parents=True,exist_ok=True)
        downloaded=subprocess.run(['npx','--no-install','wrangler','r2','object','get','clapslearn-erp-uploads/'+key,'--remote','--file',str(image)],cwd=ROOT,capture_output=True,text=True)
        if downloaded.returncode: raise RuntimeError('Referenced upload backup failed')
        image.chmod(0o600);uploads[key]=digest(image)
    manifest['uploads']=uploads
    manifest['scope']='D1 and all profile uploads referenced by this snapshot; excludes unreferenced R2 objects and secrets'
    (directory/'manifest.json').write_text(json.dumps(manifest,indent=2))
    (directory/'recovery-report.json').write_text(json.dumps({'verified_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),**report},indent=2))
    # Prune only this tool's complete, verified backup directories; leave unknown files untouched.
    cutoff=datetime.datetime.now(datetime.timezone.utc)-datetime.timedelta(days=30)
    for old in destination.iterdir():
        if old.is_symlink() or not old.is_dir() or not re.fullmatch(r'd1-\d{8}T\d{6}Z',old.name): continue
        if datetime.datetime.strptime(old.name[3:],'%Y%m%dT%H%M%SZ').replace(tzinfo=datetime.timezone.utc)>=cutoff: continue
        try:
            metadata=json.loads((old/'manifest.json').read_text())
            if metadata.get('database')=='clapslearn-erp' and metadata.get('integrity')=='ok' and set(p.name for p in old.iterdir())==({'database.sql','manifest.json','recovery-report.json','uploads'} if (old/'uploads').exists() else {'database.sql','manifest.json','recovery-report.json'}): shutil.rmtree(old)
        except (OSError,ValueError): pass
    return {'backup':str(directory),'integrity':'ok','sha256':manifest['sha256'],'scope':manifest['scope']}

def main():
    parser=argparse.ArgumentParser(description=__doc__);sub=parser.add_subparsers(dest='command',required=True)
    create=sub.add_parser('backup');create.add_argument('--destination',default=str(DEFAULT))
    check=sub.add_parser('verify');check.add_argument('directory')
    args=parser.parse_args()
    result=backup(args.destination) if args.command=='backup' else verify(args.directory)
    print(json.dumps(result,indent=2))
if __name__=='__main__':
    try: main()
    except Exception as error:
        # SQL errors can contain values; do not print raw exception text.
        raise SystemExit('Backup/recovery operation failed ('+type(error).__name__+'). No production restore was performed.')
