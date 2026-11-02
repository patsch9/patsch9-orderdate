#!/usr/bin/env python3
"""Fail on every Plugin Check finding, without uploading a report artifact."""
import json
import re
import sys
from pathlib import Path


def verify_report(report):
    report = re.sub(r"\x1b\[[0-9;]*m", "", report)
    sections = list(re.finditer(r"(?m)^FILE: (.+)\n", report))
    decoder = json.JSONDecoder()
    findings = []
    for section in sections:
        records, _ = decoder.raw_decode(report[section.end():].lstrip())
        if not isinstance(records, list) or any(not isinstance(item, dict) for item in records):
            raise ValueError("Malformed Plugin Check findings")
        for item in records:
            findings.append(f"{section[1]}:{item.get('line', 0)} {item.get('type', 'UNKNOWN')} {item.get('code', '')}: {item.get('message', '')}")
    if findings:
        raise ValueError("Strict Plugin Check failed:\n" + "\n".join(findings))
    if not sections and "Success: Checks complete. No errors found." not in report:
        raise ValueError("Missing Plugin Check success marker or structured report")


if __name__ == "__main__":
    try:
        verify_report(Path(sys.argv[1]).read_text(encoding="utf-8"))
    except (ValueError, IndexError, OSError) as error:
        raise SystemExit(str(error)) from error
    print("Strict Plugin Check passed without errors or warnings.")
