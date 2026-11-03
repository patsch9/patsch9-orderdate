"""Verify the installable ZIP, not just source-tree metadata."""
import hashlib
import json
import re
import sys
import zipfile
from pathlib import Path

archive = Path(sys.argv[1])
slug = 'patsch9-orderdate'
version = archive.name.removeprefix(slug + '-').removesuffix('.zip')
with zipfile.ZipFile(archive) as package:
    names = package.namelist()
    assert all(name.startswith(slug + '/') and '..' not in name.split('/') for name in names)
    assert not any('/tests/' in name or '/tools/' in name or '/.github/' in name or '/.' in name for name in names)
    main = package.read(slug + '/' + slug + '.php').decode()
    readme = package.read(slug + '/readme.txt').decode()
    block = json.loads(package.read(slug + '/blocks/pickup-date/block.json'))
    assert re.search(r'^ \* Version: (\S+)', main, re.M)[1] == version
    assert re.search(r"define\( 'P9OD_VERSION', '([^']+)'", main)[1] == version
    assert re.search(r'^Stable tag: (\S+)', readme, re.M)[1] == version
    assert block['version'] == version
    for name in ('LICENSE', 'includes/class-rules.php', 'assets/checkout-block.js', 'includes/class-pickup-manager.php', 'assets/payment-block.js', 'templates/emails/pickup.php', 'templates/emails/plain/pickup.php'):
        assert slug + '/' + name in names
assert archive.with_suffix('.zip.sha256').read_text().split()[0] == hashlib.sha256(archive.read_bytes()).hexdigest()
print('Package layout, version consistency and checksum passed.')

