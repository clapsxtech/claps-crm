import importlib.util, hashlib, json, pathlib, tempfile, unittest
spec=importlib.util.spec_from_file_location('ops',pathlib.Path(__file__).resolve().parents[2]/'scripts/erp-ops.py')
ops=importlib.util.module_from_spec(spec);spec.loader.exec_module(ops)
class BackupTest(unittest.TestCase):
    def fixture(self,path):
        sql='CREATE TABLE users(id INTEGER,profile_photo TEXT);\n'
        for table in ops.REQUIRED[1:]:
            sql+='CREATE TABLE '+table+'('+('unit_id INTEGER,kind TEXT,amount_paise INTEGER' if table=='erp_ledger' else 'id INTEGER')+');\n'
        sql+="INSERT INTO erp_ledger VALUES(1,'payment',12500);"
        (path/'database.sql').write_text(sql)
        (path/'manifest.json').write_text(json.dumps({'sha256':ops.digest(path/'database.sql')}))
    def test_integrity_and_mismatch(self):
        with tempfile.TemporaryDirectory() as root:
            path=pathlib.Path(root);self.fixture(path)
            result=ops.verify(path);self.assertEqual(result['integrity'],'ok');self.assertEqual(result['ledger_totals'],[[1,'payment',1,12500]])
            (path/'database.sql').write_text('corrupted')
            with self.assertRaises(ValueError):ops.verify(path)
    def test_restore_rejects_external_database_attachment(self):
        with tempfile.TemporaryDirectory() as root:
            path=pathlib.Path(root);self.fixture(path)
            dump=path/'database.sql';dump.write_text(dump.read_text()+"ATTACH DATABASE ':memory:' AS external;")
            (path/'manifest.json').write_text(json.dumps({'sha256':ops.digest(dump)}))
            with self.assertRaises(Exception):ops.verify(path)
if __name__=='__main__':unittest.main()
