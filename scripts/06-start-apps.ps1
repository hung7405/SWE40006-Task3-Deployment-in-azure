# ---------------------------------------------------------------------------
# 06 - Reactivate the applications
#
#   .\scripts\06-start-apps.ps1
#
# The marker must be able to open both URLs, so the apps have to be running at
# submission time even though Task 3.2 requires evidence of deactivation.
# ---------------------------------------------------------------------------
. "$PSScriptRoot/config.ps1"
$ErrorActionPreference = 'Continue'

foreach ($app in @($AppName, $PhpAppName)) {
    Write-Host "Starting $app ..." -ForegroundColor Cyan
    az webapp start --resource-group $ResourceGroup --name $app -o none
}

Write-Host 'Waiting 20 s for the workers to come back ...'
Start-Sleep -Seconds 20

Write-Host ''
Write-Host 'Current state:' -ForegroundColor Cyan
az webapp list --resource-group $ResourceGroup --query "[].{name:name, state:state, url:defaultHostName}" -o table

Write-Host ''
Write-Host 'Probing /health on both apps:' -ForegroundColor Cyan
foreach ($url in @("$CsharpUrl/health", "$PhpUrl/health")) {
    try {
        $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 90
        Write-Host ("  {0} -> HTTP {1}" -f $url, $response.StatusCode) -ForegroundColor Green
    }
    catch {
        Write-Host ("  {0} -> {1}" -f $url, $_.Exception.Message) -ForegroundColor Red
    }
}
