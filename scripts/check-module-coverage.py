import sys
import xml.etree.ElementTree as ET
from pathlib import Path

root = Path.cwd().resolve()
modules = ("Entitlements", "Commerce", "InstructorFinance", "AI")
files = {}
for entry in ET.parse(sys.argv[1]).findall(".//file"):
    metrics = entry.find("metrics")
    if metrics is not None:
        files[Path(entry.attrib["name"]).resolve()] = metrics.attrib

failed = False
for module in modules:
    directory = root / "app" / "Domain" / module
    sources = sorted(directory.rglob("*.php"))
    if not sources:
        print(f"{module}: pending module implementation")
        continue
    missing = [source for source in sources if source not in files]
    if missing:
        print(f"{module}: missing coverage data for {', '.join(str(path.relative_to(root)) for path in missing)}")
        failed = True
        continue
    total = sum(int(files[source]["statements"]) for source in sources)
    covered = sum(int(files[source]["coveredstatements"]) for source in sources)
    if total == 0:
        print(f"{module}: no executable statements")
        continue
    print(f"{module}: {covered / total:.1%} statement coverage (minimum 90%)")
    failed |= covered * 10 < total * 9

sys.exit(int(failed))
