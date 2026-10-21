# ---------------------------------------------------------------------------
# 05 - Deactivate the applications (Task 3.2 requirement)
#
#   .\scripts\05-stop-apps.ps1
#
# Takes screenshots of:
#   * the portal "Overview" blade showing Status = Stopped
#   * the browser showing the platform 403 error page
#   * `az webapp show --query state` printing "Stopped"
# Then run 06-start-apps.ps1 to bring them back for marking.
# ---------------------------------------------------------------------------
. "$PSScriptRoot/config.ps1"
$ErrorActionPreference = 'Continue'

foreach ($app in @($AppName, $PhpAppName)) {
    Write-Host "Stopping $app ..." -ForegroundColor Cyan
    az webapp stop --resource-group $ResourceGroup --name $app -o none
}

Write-Host ''
Write-Host 'Current state:' -ForegroundColor Cyan
az webapp list --resource-group $ResourceGroup --query "[].{name:name, state:state, url:defaultHostName}" -o table
