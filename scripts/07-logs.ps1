# ---------------------------------------------------------------------------
# 07 - Stream the application log (the main Azure diagnostic tool)
#
#   .\scripts\07-logs.ps1 -App clouddeploy-swe40006
#   .\scripts\07-logs.ps1 -App phpdeploy-swe40006
#
# Opens the live log stream. Press Ctrl+C to stop. Every line shown here is the
# same text that appears in the portal under Monitoring -> Log stream, so it is
# a legitimate source of evidence for the "error analysis" criterion.
# ---------------------------------------------------------------------------
param(
    [Parameter(Mandatory = $true)]
    [string]$App
)

. "$PSScriptRoot/config.ps1"
$ErrorActionPreference = 'Stop'

Write-Host "Streaming logs for $App (Ctrl+C to stop) ..." -ForegroundColor Cyan
az webapp log tail --resource-group $ResourceGroup --name $App
