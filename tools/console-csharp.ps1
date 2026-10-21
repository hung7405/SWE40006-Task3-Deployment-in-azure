# Captured live in Windows Terminal for the report (C# build + unit tests).
$ErrorActionPreference = 'Continue'
Set-Location 'C:\Users\vieth\SWE40006 portfolio Task 3'
function Cmd($text) { Write-Host ''; Write-Host "PS $($PWD.Path)> $text" -ForegroundColor Cyan }

Cmd 'dotnet --version'
dotnet --version
Write-Host ''

Cmd 'dotnet build src\DeploymentDashboard\DeploymentDashboard.csproj -c Release'
dotnet build src\DeploymentDashboard\DeploymentDashboard.csproj -c Release
Start-Sleep -Seconds 25

Cmd 'dotnet test tests\DeploymentDashboard.Tests\DeploymentDashboard.Tests.csproj -c Release'
dotnet test tests\DeploymentDashboard.Tests\DeploymentDashboard.Tests.csproj -c Release
Start-Sleep -Seconds 30
