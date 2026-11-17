"""Shared PHP discovery for the dependency-free development tools."""
import os
from pathlib import Path
import shutil

ROOT = Path(__file__).resolve().parents[1]


def php_command(executable=None):
    local = ROOT / '.runtime/php/php.exe'
    candidate = executable or os.environ.get('BREADNET_PHP')
    candidate = candidate or (str(local) if local.is_file() else 'php')
    resolved = shutil.which(str(candidate))
    if not resolved:
        raise RuntimeError(
            f'PHP executable not found: {candidate}. Install the PHP version required '
            'by README.md with pdo_sqlite and mbstring, or set BREADNET_PHP.'
        )
    command = [resolved, '-c', str(ROOT / 'config/php.ini')]
    if Path(resolved).resolve() == local.resolve():
        command += ['-d', f'extension_dir={local.parent / "ext"}',
                    '-d', 'extension=pdo_sqlite', '-d', 'extension=mbstring']
    return command
