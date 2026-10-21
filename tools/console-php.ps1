# Captured live in Windows Terminal for the report (PHP version, modules, tests).
$ErrorActionPreference = 'Continue'
$env:Path += ';C:\Users\vieth\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe'
Set-Location 'C:\Users\vieth\SWE40006 portfolio Task 3'
function Cmd($text) { Write-Host ''; Write-Host "PS $($PWD.Path)> $text" -ForegroundColor Cyan }

Cmd 'php -v'
php -v
Write-Host ''

Cmd 'php -m | Select-String -Pattern "json|mbstring|openssl|ctype"'
php -m | Select-String -Pattern 'json|mbstring|openssl|ctype'
Write-Host ''

Cmd 'php src\php-webapp\tests\run-tests.php'
php src\php-webapp\tests\run-tests.php
Start-Sleep -Seconds 40
