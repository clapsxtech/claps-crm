#!/usr/bin/env python3
"""Inspect committed D1 migration SQL in memory. Never opens an application DB.

Usage: python3 scripts/review-erp-schema.py
Outputs schema and synthetic constraint probes as JSON. This is an audit, not
a production data scan; accepted invalid writes are release gaps, not success.
"""
import hashlib
import json
from pathlib import Path
import sqlite3


def review():
    root = Path(__file__).resolve().parents[1]
    db = sqlite3.connect(":memory:")
    db.execute("PRAGMA foreign_keys=ON")
    migrations = []
    for file in sorted((root / "cloudflare/migrations").glob("*.sql")):
        raw = file.read_bytes()
        db.executescript(raw.decode())
        migrations.append({"file": file.name, "sha256": hashlib.sha256(raw).hexdigest()})
    tables = [r[0] for r in db.execute("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'erp_%' AND name NOT LIKE 'erp_d1_%' ORDER BY name")]
    schema = {}
    missing = []
    for table in tables:
        schema[table] = {
            "columns": [dict(zip(["cid", "name", "type", "notnull", "default", "pk"], r)) for r in db.execute(f'PRAGMA table_info("{table}")')],
            "foreign_keys": list(db.execute(f'PRAGMA foreign_key_list("{table}")')),
            "indexes": [{"name": r[1], "unique": bool(r[2]), "columns": [x[2] for x in db.execute(f'PRAGMA index_info("{r[1]}")')]} for r in db.execute(f'PRAGMA index_list("{table}")')],
        }
        for action in ["insert", "update", "delete"]:
            if not db.execute("SELECT 1 FROM sqlite_master WHERE type='trigger' AND name=?", (f"erp_d1_rev_{table}_{action}",)).fetchone():
                missing.append(f"{table}:{action}")
    probes = {
        "orphan_student_profile": ["INSERT INTO erp_student_profiles(unit_id,student_id) VALUES(987,654)"],
        "cross_team_student_profile": [
            "INSERT INTO erp_units(id,code,name) VALUES(1,'A','Synthetic A'),(2,'B','Synthetic B')",
            "INSERT INTO erp_students(id,unit_id,code,name,registered_on,timezone,grade,syllabus) VALUES(1,1,'SYNTHETIC','Synthetic','2026-01-01','UTC','8','Synthetic')",
            "INSERT INTO erp_student_profiles(unit_id,student_id) VALUES(2,1)",
        ],
        "negative_attendance_with_missing_lesson": ["INSERT INTO erp_attendance(unit_id,lesson_id,actual_minutes,submitted_by) VALUES(987,654,-60,321)"],
        "duplicate_task_source_identity": [
            "INSERT INTO erp_tasks(unit_id,title,created_by,source,source_external_id) VALUES(987,'Synthetic',321,'sheets','same-source')",
            "INSERT INTO erp_tasks(unit_id,title,created_by,source,source_external_id) VALUES(987,'Synthetic',321,'sheets','same-source')",
        ],
    }
    results = {}
    for name, statements in probes.items():
        db.execute("SAVEPOINT probe")
        try:
            for sql in statements:
                db.execute(sql)
            results[name] = "ACCEPTED_INVALID_DATA"
        except sqlite3.IntegrityError:
            results[name] = "REJECTED_BY_DATABASE"
        finally:
            db.execute("ROLLBACK TO probe")
            db.execute("RELEASE probe")
    return {"scope": "Disposable in-memory SQLite built from D1 SQL; no production connection or source records.",
            "migrations": migrations, "erp_table_count": len(tables),
            "declared_erp_foreign_keys": sum(len(t["foreign_keys"]) for t in schema.values()),
            "missing_revision_triggers": missing, "synthetic_constraint_probes": results, "tables": schema}


if __name__ == "__main__":
    print(json.dumps(review(), indent=2))
