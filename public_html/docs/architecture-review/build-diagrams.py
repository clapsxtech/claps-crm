"""Rebuild diagrams from the reviewed metadata and explicit logical relationships."""
import csv
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parent
source = json.loads((ROOT / 'source-evidence.json').read_text())
schema = json.loads((ROOT / 'application-schema.json').read_text())['tables']
books = source['workbooks'] + source['externalDependencies']
labels = {b['id']: b['title'].strip() for b in books}
nodes = {b['id']: f'W{i+1}' for i, b in enumerate(books)}
lines = ['flowchart LR']
for b in books:
    lines.append(f'  {nodes[b["id"]]}["{b["title"].strip()}"]')
for a, b in sorted({(e['from'], e['to']) for e in source['importRangeEdges']}):
    lines.append(f'  {nodes[a]} --> {nodes[b]}')
lines += ['  classDef upstream fill:#fff2dc,stroke:#ba7428,color:#432d12']
lines.append('  class '+','.join(nodes[b['id']] for b in source['externalDependencies'])+' upstream')
(ROOT / 'sheet-dependencies.mmd').write_text('\n'.join(lines)+'\n')
with (ROOT / 'formula-lineage.csv').open('w', newline='') as f:
    writer = csv.writer(f)
    writer.writerow(['source_workbook','source_range','consumer_workbook','consumer_tab','formula_cell','consumer_url'])
    for edge in source['importRangeEdges']:
        sheet = next(s for b in source['workbooks'] if b['id']==edge['to'] for s in b['sheets'] if s['title']==edge['tab'])
        writer.writerow([labels[edge['from']],edge['range'],labels[edge['to']],edge['tab'],edge['cell'],f'https://docs.google.com/spreadsheets/d/{edge["to"]}/edit#gid={sheet["sheetId"]}&range={edge["cell"]}'])
sampled = {(s['book'],s['sheetId']):s for s in source['sampleStats']}
with (ROOT / 'tab-inventory.csv').open('w', newline='') as f:
    writer = csv.writer(f)
    writer.writerow(['workbook','tab','gid','hidden','grid_row_capacity_not_records','grid_columns','review_scope','sample_formula_cells','sample_error_cells'])
    for book in source['workbooks']:
        for tab in book['sheets']:
            item = sampled.get((book['id'],tab['sheetId']))
            writer.writerow([book['title'],tab['title'],tab['sheetId'],bool(tab.get('hidden')),tab['gridProperties']['rowCount'],tab['gridProperties']['columnCount'],'bounded cell sample' if item else 'metadata only',item['formulaCount'] if item else '',item['errorCount'] if item else ''])

# child.column -> parent. These are logical references, NOT declared SQL FKs.
groups = {
 'application-people-sales': [
  ('memberships','user_id','users'),('memberships','unit_id','units'),
  ('students','unit_id','units'),('student_profiles','student_id','students'),
  ('teachers','user_id','users'),('teacher_units','teacher_id','teachers'),('teacher_units','unit_id','units'),
  ('teacher_profiles','teacher_id','teachers'),('teacher_matching_profiles','teacher_id','teachers'),
  ('eligibilities','teacher_id','teachers'),('eligibilities','subject_id','subjects'),
  ('leads','unit_id','units'),('leads','student_id','students'),('leads','assigned_to','users'),
  ('lead_activities','lead_id','leads'),('lead_reminders','lead_id','leads'),('lead_transfers','lead_id','leads'),
  ('demos','lead_id','leads'),('demos','teacher_id','teachers'),('demos','student_id','students'),('demos','subject_id','subjects'),
  ('teacher_enquiries','staff_id','users'),
  ('teacher_candidate_sources','candidate_id','teacher_candidates'),('teacher_candidate_activities','candidate_id','teacher_candidates'),
  ('teacher_candidates','teacher_id','teachers'),('teacher_candidates','merged_into_id','teacher_candidates'),
  ('candidates','unit_id','units'),('candidate_interviews','candidate_id','candidates')],
 'application-delivery': [
  ('enrollments','student_id','students'),('enrollments','teacher_id','teachers'),('enrollments','subject_id','subjects'),
  ('availability','student_id','students'),('availability','teacher_id','teachers'),
  ('exceptions','student_id','students'),('exceptions','teacher_id','teachers'),
  ('agreements','enrollment_id','enrollments'),('agreements','replaces_agreement_id','agreements'),
  ('lessons','enrollment_id','enrollments'),('lessons','agreement_id','agreements'),('lessons','replaces_lesson_id','lessons'),
  ('attendance','lesson_id','lessons'),('internal_notes','lesson_id','lessons'),
  ('duplicate_reviews','attendance_id','attendance'),('duplicate_reviews','other_attendance_id','attendance'),
  ('marks','enrollment_id','enrollments'),('allocation_requests','demo_id','demos'),
  ('allocation_requests','enrollment_id','enrollments'),('allocation_requests','agreement_id','agreements'),
  ('demos','enrollment_id','enrollments'),('demos','agreement_id','agreements')],
 'application-finance-operations': [
  ('ledger','student_id','students'),('ledger','teacher_id','teachers'),('ledger','attendance_id','attendance'),('ledger','reverses_id','ledger'),
  ('ledger','invoice_id','invoices'),('invoices','student_id','students'),('invoice_lines','invoice_id','invoices'),
  ('payment_requests','student_id','students'),('request_allocations','request_id','payment_requests'),('request_allocations','payment_id','ledger'),
  ('tasks','student_id','students'),('tasks','assigned_to','users'),('task_items','task_id','tasks'),
  ('task_templates','unit_id','units'),('followups','student_id','students'),
  ('attendance_batches','unit_id','units'),('audit','actor_id','users'),
  ('migration_mappings','batch_id','migration_batches'),('migration_history','batch_id','migration_batches'),('migration_history','enrollment_id','enrollments')],
}
extra = {'code','name','status','stage','starts_on','ends_on','starts_at','ends_at','timezone','weekday','start_time','duration_minutes','actual_minutes','student_rate_paise','teacher_rate_paise','currency','kind','amount_paise','event_key','reference','source_external_id','identity_key','invoice_number','total_paise','quantity','unit_rate_paise','assessment','score','maximum','source_key','business_id','target_type','target_id','cutoff','digest','financial_effect','points'}
relationships = []
for title, rels in groups.items():
    names = set()
    refs = {}
    def table(name): return name if name=='users' else 'erp_'+name
    for child, column, parent in rels:
        c,p=table(child),table(parent)
        names.update([c,p]); refs.setdefault(c,set()).add(column)
        assert any(x['name']==column for x in schema[c]['columns']), (c,column)
    diagram = ['erDiagram', '  %% Logical references only. D1 schema declares zero ERP foreign keys.']
    for name in sorted(names):
        diagram.append('  '+name+' {')
        columns = schema.get(name, {'columns':[{'name':'id','type':'integer','pk':1,'notnull':1}]})['columns']
        for c in columns:
            if c['name'] in {'id','unit_id'} | refs.get(name,set()) | extra:
                typ=c['type'].split('(')[0].lower().replace(' ','_') or 'text'
                key=' PK' if c['pk'] else ''
                note=' "logical reference"' if c['name'] in refs.get(name,set()) else ''
                diagram.append(f'    {typ} {c["name"]}{key}{note}')
        diagram.append('  }')
    for child, column, parent in rels:
        c,p=table(child),table(parent)
        col=next(x for x in schema[c]['columns'] if x['name']==column)
        unique=any(i['unique'] and i['columns']==[column] for i in schema[c]['indexes'])
        lhs='||' if col['notnull'] else '|o'
        rhs='o|' if unique else 'o{'
        diagram.append(f'  {p} {lhs}..{rhs} {c} : "{column}"')
        relationships.append({'child':c,'column':column,'parent':p,'nullable':not bool(col['notnull']),'unique_child_reference':unique,'declared_foreign_key':False})
    (ROOT / (title+'.mmd')).write_text('\n'.join(diagram)+'\n')
(ROOT / 'logical-relationships.json').write_text(json.dumps(relationships,indent=2)+'\n')
print('Generated 4 diagrams, 2 CSVs, and logical relationship catalogue.')
