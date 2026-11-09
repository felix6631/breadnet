param([int]$Port = 8000)
$ErrorActionPreference = 'Stop'
& "$PSScriptRoot/php.ps1" -S "127.0.0.1:$Port" -t "$PSScriptRoot/public"
