#!/usr/bin/env python3
import base64, zlib, pathlib, sys
root = pathlib.Path(__file__).resolve().parents[1]
parts_dir = root / "patches" / "je_list_perf_b64"
parts = sorted(parts_dir.glob("part_*.txt"))
if not parts:
    print("missing parts", parts_dir, file=sys.stderr); sys.exit(1)
data = "".join(p.read_text().strip() for p in parts)
pad = (-len(data)) % 4
if pad:
    data += "=" * pad
out = root / "app/Modules/FinancialAccounting/Application/Services/JournalEntryService.php"
raw = zlib.decompress(base64.b64decode(data))
out.write_bytes(raw)
print(f"OK wrote {out} bytes={len(raw)} withSum={'withSum' in raw.decode()}")
