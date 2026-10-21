# ---------------------------------------------------------------------------
# 03 - Package and deploy the PHP web application (Task 3.3)
#
#   .\scripts\03-deploy-php.ps1
#
# Only the runtime files are packaged: tests/, router.php and any local notes
# stay out of the public site. Everything else is plain source, so there is no
# Composer build step and no vendor directory to restore on the server.
# ---------------------------------------------------------------------------
. "$PSScriptRoot/config.ps1"
$ErrorActionPreference = 'Stop'

New-Item -ItemType Directory -Force -Path $Artifacts | Out-Null

$source = Join-Path $RepoRoot 'src\php-webapp'
$staging = Join-Path $Artifacts 'php'
$zip = Join-Path $Artifacts 'php.zip'

Write-Host ''
Write-Host '=== 1/4  Local checks ==============================================' -ForegroundColor Cyan
$php = (Get-Command php -ErrorAction SilentlyContinue).Source
if (-not $php) { throw 'php.exe is not on PATH. Install PHP 8.3 and reopen the terminal.' }
& $php -v | Select-Object -First 1
& $php (Join-Path $source 'tests\run-tests.php')
if ($LASTEXITCODE -ne 0) { throw 'PHP tests failed.' }

Write-Host ''
Write-Host '=== 2/4  Stage runtime files =======================================' -ForegroundColor Cyan
if (Test-Path $staging) { Remove-Item $staging -Recurse -Force }
New-Item -ItemType Directory -Force -Path $staging | Out-Null

Copy-Item (Join-Path $source 'index.php')      $staging
Copy-Item (Join-Path $source 'bootstrap.php')  $staging
Copy-Item (Join-Path $source 'src')            $staging -Recurse
Copy-Item (Join-Path $source 'templates')      $staging -Recurse
Copy-Item (Join-Path $source 'assets')         $staging -Recurse

# The custom nginx config has to sit at the package root: the App Service startup
# command copies /home/site/wwwroot/default into /etc/nginx/sites-available/.
Copy-Item (Join-Path $source 'deploy\default') (Join-Path $staging 'default')

Write-Host '  packaged: index.php, bootstrap.php, src/, templates/, assets/, default (nginx)'

Write-Host ''
Write-Host '=== 3/4  Zip and deploy ============================================' -ForegroundColor Cyan
if (Test-Path $zip) { Remove-Item $zip -Force }
Compress-Archive -Path (Join-Path $staging '*') -DestinationPath $zip -Force
Write-Host ("  package: {0:N0} KB" -f ((Get-Item $zip).Length / 1KB))

az webapp deploy --resource-group $ResourceGroup --name $PhpAppName `
    --src-path $zip --type zip --clean true -o table
if ($LASTEXITCODE -ne 0) { throw 'az webapp deploy failed.' }

Write-Host ''
Write-Host '=== 4/4  Verify ====================================================' -ForegroundColor Cyan
az webapp restart --resource-group $ResourceGroup --name $PhpAppName -o none
Write-Host '  waiting 20 s for the PHP worker to start...'
Start-Sleep -Seconds 20

foreach ($path in @('/health', '/api/info')) {
    $url = "$PhpUrl$path"
    try {
        $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 90
        Write-Host ("  {0} -> HTTP {1}" -f $path, $response.StatusCode) -ForegroundColor Green
    }
    catch {
        Write-Host ("  {0} -> {1}" -f $path, $_.Exception.Message) -ForegroundColor Red
        Write-Host "  Run: az webapp log tail -g $ResourceGroup -n $PhpAppName"
    }
}

Write-Host ''
Write-Host "Open $PhpUrl to see the deployed PHP application." -ForegroundColor Green
