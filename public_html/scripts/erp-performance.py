#!/usr/bin/env python3
"""Bounded live read benchmark: three authenticated sessions, at most 54 measured GETs."""
import concurrent.futures, datetime, json, math, pathlib, re, statistics, subprocess, tempfile, time, urllib.parse
BASE='https://erp.clapslearn.in'

def request(path,cookie,data=None):
    args=['curl','--silent','--show-error','--max-time','45','--location','--cookie',str(cookie),'--cookie-jar',str(cookie),'--write-out','\n%{http_code} %{url_effective}',BASE+path]
    if data is not None:args+=['--data-binary','@-']
    started=time.monotonic()
    result=subprocess.run(args,input=urllib.parse.urlencode(data) if data is not None else None,text=True,capture_output=True)
    elapsed=round((time.monotonic()-started)*1000,2)
    if result.returncode:return {'path':path,'status':0,'ms':elapsed},''
    body,info=result.stdout.rsplit('\n',1);status,url=info.split(' ',1)
    return {'path':path,'status':int(status),'ms':elapsed,'redirected':urllib.parse.urlparse(url).path!=path},body

def benchmark(access_file):
    access=json.loads(pathlib.Path(access_file).read_text())
    if access.get('url','').rstrip('/')!=BASE:raise ValueError('Credential origin does not match production')
    with tempfile.TemporaryDirectory(prefix='claps-perf-') as folder:
        cookies=[pathlib.Path(folder)/str(i) for i in range(3)]
        units=[]
        try:
            for cookie in cookies:
                response,body=request('/superadmin/login',cookie)
                token=re.search(r'name="_token" value="([^"]+)"',body)
                if response['status']!=200 or not token:raise RuntimeError('Login page unavailable')
                response,body=request('/login',cookie,{'_token':token.group(1),'email':access['email'],'password':access['password'],'portal':'superadmin'})
                if response['status']!=200 or 'Superadmin' not in body:raise RuntimeError('Authentication failed')
                units=re.findall(r'/erp/units/(\d+)/business/dashboard',body)
            if not units:raise RuntimeError('No team dashboard available')
            paths=['/health','/','/tools','/erp/units/'+units[0]+'/business/dashboard','/css/erp.css','/js/erp-shell.js']
            for cookie in cookies:request('/health',cookie) # warmup, separately from measured requests
            records=[]
            def run(cookie):
                samples=[]
                for _ in range(3):
                    for path in paths:
                        response,_=request(path,cookie);samples.append(response)
                        if response['status']!=200 or response.get('redirected'):return samples
                return samples
            with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
                for samples in pool.map(run,cookies):records.extend(samples)
            summary={}
            for path in paths:
                rows=[r for r in records if r['path']==path];times=sorted(r['ms'] for r in rows)
                summary[path]={'requests':len(rows),'failures':sum(r['status']!=200 or r.get('redirected',False) for r in rows),'p50_ms':statistics.median(times),'p95_ms':times[math.ceil(len(times)*.95)-1],'max_ms':max(times)} if times else {'requests':0,'failures':1}
            return {'timestamp':datetime.datetime.now(datetime.timezone.utc).isoformat(),'concurrent_sessions':3,'measured_requests':len(records),'results':summary,'scope':'Small warm read-only production sample, not a capacity certification. Login/logout create and delete sessions; no business records are changed.'}
        finally:
            for cookie in cookies:
                response,body=request('/',cookie)
                token=re.search(r'name="csrf-token" content="([^"]+)"',body)
                if token:request('/logout',cookie,{'_token':token.group(1)})
if __name__=='__main__':
    import argparse
    parser=argparse.ArgumentParser();parser.add_argument('--credentials',required=True);parser.add_argument('--output',required=True);args=parser.parse_args()
    result=benchmark(args.credentials);pathlib.Path(args.output).write_text(json.dumps(result,indent=2));print(json.dumps(result,indent=2))
    if any(row['failures'] for row in result['results'].values()):raise SystemExit(1)
