# ---------------------------------------------------------------------------
# 08 - Delete everything (run this only AFTER the report is marked)
#
#   .\scripts\08-cleanup.ps1
#
# F1 is free, but leaving resources behind is untidy and Azure for Students
# subscriptions have a quota of 10 web apps per region. Deleting the resource
# group removes the plan, both web apps and their logs.
# ---------------------------------------------------------------------------
. "$PSScriptRoot/config.ps1"

Write-Host "This will delete resource group '$ResourceGroup' and everything in it." -ForegroundColor Yellow
$answer = Read-Host 'Type DELETE to continue'
if ($answer -ne 'DELETE') {
    Write-Host 'Cancelled.'
    return
}

az group delete --name $ResourceGroup --yes --no-wait
Write-Host 'Deletion started (running in the background).' -ForegroundColor Green
