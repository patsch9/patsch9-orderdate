"""The CI gate must reject findings and missing/incomplete scanner output."""
import importlib.util
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location("plugin_report", Path(__file__).resolve().parents[1] / "tools/check-plugin-report.py")
plugin_report = importlib.util.module_from_spec(spec)
spec.loader.exec_module(plugin_report)


class ReportGateTests(unittest.TestCase):
    def test_success_through_wp_env_output(self):
        plugin_report.verify_report("Starting wp plugin check...\n\x1b[32mSuccess: Checks complete. No errors found.\x1b[0m\nFinished.")

    def test_findings_fail_even_if_scanner_reports_success(self):
        for severity in ("ERROR", "WARNING", "UNKNOWN"):
            with self.subTest(severity=severity), self.assertRaisesRegex(ValueError, "Strict Plugin Check failed"):
                plugin_report.verify_report('FILE: includes/test.php\n[{"type":"' + severity + '","line":2,"code":"test","message":"Finding"}]\nSuccess: Checks complete. No errors found.')

    def test_missing_and_malformed_reports_fail(self):
        for report in ("", "Finished.", "FILE: test.php\n[", 'FILE: test.php\n{"bad":true}', "FILE: test.php\n[42]"):
            with self.subTest(report=report), self.assertRaises(ValueError):
                plugin_report.verify_report(report)
