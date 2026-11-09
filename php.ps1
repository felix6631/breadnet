# Forward arguments without shell evaluation.
$ErrorActionPreference = 'Stop'
$localPhp = Join-Path $PSScriptRoot '.runtime/php/php.exe'
$phpArgs = @('-c', (Join-Path $PSScriptRoot 'config/php.ini'))
if (Test-Path -LiteralPath $localPhp) {
    $phpExe = $localPhp
    $phpArgs += @('-d', "extension_dir=$PSScriptRoot/.runtime/php/ext", '-d', 'extension=pdo_sqlite', '-d', 'extension=mbstring')
} else { $phpExe = (Get-Command php -ErrorAction Stop).Source }
& $phpExe @phpArgs @args
exit $LASTEXITCODE
