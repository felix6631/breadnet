"""Development harness. Run python3 bin/harness.py --help from any directory."""
import argparse
import ast
from pathlib import Path
import os
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'tests'))
from runtime import php_command


def run(command, **kwargs):
    subprocess.run(command, cwd=ROOT, check=True, **kwargs)


def doctor(php):
    probe = '''
require $argv[1];
foreach (['pdo_sqlite', 'mbstring'] as $extension) {
    if (!extension_loaded($extension)) {
        fwrite(STDERR, "Missing PHP extension: $extension\\n"); exit(1);
    }
}
if (ini_get('session.upload_progress.enabled') !== '0') {
    fwrite(STDERR, "session.upload_progress.enabled must be 0\\n"); exit(1);
}
echo 'PHP ', PHP_VERSION, "; pdo_sqlite, mbstring; project ini: OK\\n";
'''
    run(php + ['-r', probe, str(ROOT / 'app/bootstrap.php')], timeout=15)


def lint(php):
    for directory in ('app', 'bin', 'public', 'templates'):
        for path in sorted((ROOT / directory).rglob('*.php')):
            run(php + ['-l', str(path)], timeout=15)
    for directory in ('bin', 'tests'):
        for path in sorted((ROOT / directory).rglob('*.py')):
            ast.parse(path.read_text(encoding='utf-8'), filename=str(path))
    print('PHP and Python syntax: OK', flush=True)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('command', choices=('doctor', 'lint', 'test', 'check', 'dev'))
    parser.add_argument('--php', help='PHP executable (or set BREADNET_PHP)')
    parser.add_argument('--port', type=int, default=8000, help='dev loopback port (default: 8000)')
    args = parser.parse_args()
    if not 1 <= args.port <= 65535:
        parser.error('--port must be between 1 and 65535')
    php = php_command(args.php)
    doctor(php)
    if args.command in ('lint', 'check'):
        lint(php)
    if args.command in ('test', 'check'):
        # Do not inherit Python optimization: smoke assertions must execute.
        env = dict(os.environ, PYTHONOPTIMIZE='', BREADNET_PHP=php[0])
        run([sys.executable, str(ROOT / 'tests/smoke.py'), php[0]], env=env)
    if args.command == 'dev':
        with tempfile.TemporaryDirectory(prefix='breadnet-dev-') as temporary:
            sessions = Path(temporary) / 'sessions'
            sessions.mkdir()
            env = dict(os.environ, BREADNET_DB=str(Path(temporary) / 'dev.sqlite'))
            isolated_php = php + ['-d', f'session.save_path={sessions}']
            print('Temporary development database; removed on exit.', flush=True)
            run(isolated_php + [str(ROOT / 'bin/setup.php')], env=env)
            print(f'Open http://127.0.0.1:{args.port}/ (Ctrl+C to stop)', flush=True)
            run(isolated_php + ['-S', f'127.0.0.1:{args.port}', '-t', str(ROOT / 'public')], env=env)


if __name__ == '__main__':
    try:
        main()
    except (RuntimeError, OSError) as error:
        print(f'ERROR: {error}', file=sys.stderr)
        sys.exit(1)
    except subprocess.CalledProcessError as error:
        print(f'ERROR: command failed (exit {error.returncode})', file=sys.stderr)
        sys.exit(1)
    except subprocess.TimeoutExpired:
        print('ERROR: PHP environment or syntax check timed out', file=sys.stderr)
        sys.exit(1)
    except KeyboardInterrupt:
        sys.exit(130)
