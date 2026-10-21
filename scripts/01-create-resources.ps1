# ---------------------------------------------------------------------------
# 01 - Create the Azure resources (Task 3.1 + the hosting for 3.2 and 3.3)
#
#   .\scripts\01-create-resources.ps1
#
# Creates:
#   * resource group          rg-swe40006-task3
#   * Linux App Service plan  plan-swe40006-task3   (F1, Free)
#   * .NET 9 web app          clouddeploy-swe40006
#   * PHP 8.3 web app         phpdeploy-swe40006
#
# A single Linux plan hosts both apps, which keeps the cost at $0 while still
# satisfying the "deploy a C# app" and "deploy a PHP app" requirements:
# App Service Linux can run different runtimes in different apps of one plan.
# ---------------------------------------------------------------------------
. "$PSScriptRoot/config.ps1"
$ErrorActionPreference = 'Stop'

Write-Host ''
Write-Host '=== 1/6  Selecting the subscription =================================' -ForegroundColor Cyan
az account set --subscription $SubscriptionId | Out-Null
az account show --query "{name:name, id:id, user:user.name}" -o table

Write-Host ''
Write-Host '=== 2/6  Resource group ============================================' -ForegroundColor Cyan
az group create --name $ResourceGroup --location $Location -o table

Write-Host ''
Write-Host '=== 3/6  Linux App Service plan (F1 Free) ==========================' -ForegroundColor Cyan
# --is-linux is required for PHP 8.3 and is also fine for .NET 9.
az appservice plan create --resource-group $ResourceGroup --name $PlanName `
    --is-linux --sku F1 --location $Location -o table

Write-Host ''
Write-Host '=== 4/6  .NET 9 web application ====================================' -ForegroundColor Cyan
# The runtime separator is a colon, not a pipe. On Windows `az` is a .cmd shim,
# so "DOTNETCORE|9.0" is split by cmd.exe and the CLI never receives the value
# ("'9.0' is not recognized as an internal or external command").
az webapp create --resource-group $ResourceGroup --plan $PlanName `
    --name $AppName --runtime "DOTNETCORE:9.0" -o table
if ($LASTEXITCODE -ne 0) { throw "Failed to create $AppName (is the name already taken?)." }

Write-Host ''
Write-Host '=== 5/6  PHP 8.3 web application ===================================' -ForegroundColor Cyan
az webapp create --resource-group $ResourceGroup --plan $PlanName `
    --name $PhpAppName --runtime "PHP:8.3" -o table
if ($LASTEXITCODE -ne 0) { throw "Failed to create $PhpAppName (is the name already taken?)." }

Write-Host ''
Write-Host '=== 6/6  Platform and application settings =========================' -ForegroundColor Cyan

foreach ($app in @($AppName, $PhpAppName)) {
    Write-Host "  -> $app : HTTPS only, TLS 1.2, FTPS disabled"
    az webapp update --resource-group $ResourceGroup --name $app --https-only true -o none
    az webapp config set --resource-group $ResourceGroup --name $app `
        --min-tls-version 1.2 --ftps-state Disabled -o none
}

# The .NET app is uploaded pre-compiled, so an explicit start command removes all
# ambiguity about which DLL App Service should run.
az webapp config set --resource-group $ResourceGroup --name $AppName `
    --startup-file "dotnet DeploymentDashboard.dll" -o none

# The PHP image's own nginx config has no try_files fallback, so extensionless
# routes such as /health return nginx's 404. The startup command copies the
# reviewed config from the deployment package into place and reloads nginx.
az webapp config set --resource-group $ResourceGroup --name $PhpAppName `
    --startup-file "cp /home/site/wwwroot/default /etc/nginx/sites-available/default && service nginx reload" -o none

# Identity shown in the UI. Stored as app settings so no personal data is in Git.
az webapp config appsettings set --resource-group $ResourceGroup --name $AppName --settings `
    "Student__FullName=$StudentName" "Student__Id=$StudentId" `
    "Student__UnitCode=$UnitCode" "App__Version=$AppVersion" `
    "ASPNETCORE_ENVIRONMENT=Production" "SCM_DO_BUILD_DURING_DEPLOYMENT=false" -o none

az webapp config appsettings set --resource-group $ResourceGroup --name $PhpAppName --settings `
    "STUDENT_NAME=$StudentName" "STUDENT_ID=$StudentId" `
    "UNIT_CODE=$UnitCode" "APP_VERSION=$AppVersion" `
    "APP_NAME=CloudDeploy PHP" "SCM_DO_BUILD_DURING_DEPLOYMENT=false" -o none

# Health probe. App Service calls /health and removes the instance from rotation
# when it stops returning 200. Done through the REST resource because the
# --generic-configurations argument is awkward to quote from PowerShell.
Write-Host '  -> health check path /health'
foreach ($app in @($AppName, $PhpAppName)) {
    $appId = az webapp show --resource-group $ResourceGroup --name $app --query id -o tsv
    az resource update --ids $appId --set properties.siteConfig.healthCheckPath=/health -o none
}

Write-Host ''
Write-Host '=== Resources created ==============================================' -ForegroundColor Green
Write-Host "  C#  app : $CsharpUrl"
Write-Host "  PHP app : $PhpUrl"
Write-Host ''
Write-Host 'Next: .\scripts\02-deploy-csharp.ps1'
