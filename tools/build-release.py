#!/usr/bin/env python3
"""Create a tag-versioned installable plugin with a stable top-level folder."""
import argparse
import hashlib
import json
import re
import shutil
import zipfile
from pathlib import Path

SLUG = 'patsch9-orderdate'
parser = argparse.ArgumentParser()
parser.add_argument('version')
parser.add_argument('--output', default='dist')
args = parser.parse_args()
if not re.fullmatch(r'\d{4}\.(0[1-9]|1[0-2])\.[1-9]\d*(?:-(?:alpha|beta)[1-9]\d*)?', args.version):
    raise SystemExit('Version must be YYYY.MM.N[-alphaN|-betaN]')
source = Path(__file__).resolve().parents[1]
output = Path(args.output).resolve()
stage = output / 'package' / SLUG
if stage.exists():
    shutil.rmtree(stage)
stage.mkdir(parents=True)
for name in (f'{SLUG}.php', 'readme.txt', 'LICENSE', 'includes', 'assets', 'blocks'):
    path = source / name
    if path.is_symlink() or any(item.is_symlink() for item in path.rglob('*')):
        raise SystemExit('Symlinks must not be packaged')
    if path.is_dir():
        shutil.copytree(path, stage / name)
    else:
        shutil.copy2(path, stage / name)

def replace_once(path, pattern, value):
    text = path.read_text(encoding='utf-8')
    text, count = re.subn(pattern, lambda match: match[1] + value + match[2], text, flags=re.M)
    if count != 1:
        raise SystemExit(f'Expected one version match in {path.name}, got {count}')
    path.write_text(text, encoding='utf-8')

main = stage / f'{SLUG}.php'
replace_once(main, r'(^ \* Version: )\S+(\s*$)', args.version)
replace_once(main, r"(define\( 'P9OD_VERSION', ')[^']+(' \);)", args.version)
replace_once(stage / 'readme.txt', r'(^Stable tag: )\S+(\s*$)', args.version)
metadata_path = stage / 'blocks/pickup-date/block.json'
metadata = json.loads(metadata_path.read_text())
metadata['version'] = args.version
metadata_path.write_text(json.dumps(metadata, indent=2, ensure_ascii=False) + '\n')
archive = output / f'{SLUG}-{args.version}.zip'
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as package:
    for file in sorted(stage.rglob('*')):
        if file.is_file():
            package.write(file, str(Path(SLUG) / file.relative_to(stage)))
checksum = hashlib.sha256(archive.read_bytes()).hexdigest()
archive.with_suffix('.zip.sha256').write_text(f'{checksum}  {archive.name}\n')
print(archive)
