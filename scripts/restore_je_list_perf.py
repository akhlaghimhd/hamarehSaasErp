#!/usr/bin/env python3
"""Restore JournalEntryService.php from patches/je_list_perf_b64/part_*.txt"""
import base64, zlib, pathlib, sys
root = pathlib.Path(__file__).resolve().parents[1]
parts = sorted((root / "patches" / "je_list_perf_b64").glob("part_*.txt"))
if not parts:
    print("No parts found", file=sys.stderr); sys.exit(1)
data = "".join(p.read_text().strip() for p in parts)
pad = (-len(data)) % 4
if pad:
    data += "=" * pad
out = root / "app" / "Modules" / "FinancialAccounting" / "Application" / "Services" / "JournalEntryService.php"
out.write_bytes(zlib.decompress(base64.b64decode(data)))
print(f"OK wrote {out} bytes {out.stat().st_size}")
