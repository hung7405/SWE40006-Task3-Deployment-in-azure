# ---------------------------------------------------------------------------
# 02 - Build and deploy the C# (ASP.NET Core) application
#
#   .\scripts\02-deploy-csharp.ps1
#
# 1. restores and publishes the Razor Pages app in Release mode
# 2. zips the published output
# 3. pushes it with zip deploy (no FTP, no VS publish profile needed)
# 4. restarts the app and probes /health and /api/deployment
# ---------------------------------------------------------------------------
. "$PSScriptRoot/config.ps1"
$ErrorActionPreference = 'Stop'

New-Item -ItemType Directory -Force -Path $Artifacts | Out-Null

Write-Host ''
Write-Host '=== 1/4  dotnet test ===============================================' -ForegroundColor Cyan
dotnet test (Join-Path $RepoRoot 'tests\DeploymentDashboard.Tests\DeploymentDashboard.Tests.csproj') -c Release
if ($LASTEXITCODE -ne 0) { throw 'Tests failed - deployment aborted.' }

Write-Host ''
Write-Host '=== 2/4  dotnet publish ============================================' -ForegroundColor Cyan
$publishDir = Join-Path $Artifacts 'csharp'
if (Test-Path $publishDir) { Remove-Item $publishDir -Recurse -Force }

dotnet publish (Join-Path $RepoRoot 'src\DeploymentDashboard\DeploymentDashboard.csproj') `
    -c Release -o $publishDir
if ($LASTEXITCODE -ne 0) { throw 'Publish failed.' }

Write-Host ''
Write-Host '=== 3/4  Zip and deploy ============================================' -ForegroundColor Cyan
$zip = Join-Path $Artifacts 'csharp.zip'
if (Test-Path $zip) { Remove-Item $zip -Force }
Compress-Archive -Path (Join-Path $publishDir '*') -DestinationPath $zip -Force
Write-Host ("  package: {0:N0} KB" -f ((Get-Item $zip).Length / 1KB))

az webapp deploy --resource-group $ResourceGroup --name $AppName `
    --src-path $zip --type zip --clean true -o table
if ($LASTEXITCODE -ne 0) { throw 'az webapp deploy failed.' }

Write-Host ''
Write-Host '=== 4/4  Verify ====================================================' -ForegroundColor Cyan
az webapp restart --resource-group $ResourceGroup --name $AppName -o none
Write-Host '  waiting 25 s for the worker to start (F1 cold start)...'
Start-Sleep -Seconds 25

foreach ($path in @('/health', '/api/deployment')) {
    $url = "$CsharpUrl$path"
    try {
        $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 90
        Write-Host ("  {0} -> HTTP {1}" -f $path, $response.StatusCode) -ForegroundColor Green
        if ($path -eq '/health') { $response.Content }
    }
    catch {
        Write-Host ("  {0} -> {1}" -f $path, $_.Exception.Message) -ForegroundColor Red
        Write-Host '  Run: az webapp log tail -g rg-swe40006-task3 -n clouddeploy-swe40006'
    }
}

Write-Host ''
Write-Host "Open $CsharpUrl to see the deployed application." -ForegroundColor Green
