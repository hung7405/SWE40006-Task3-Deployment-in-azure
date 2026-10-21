# ---------------------------------------------------------------------------
# Shared configuration for every Azure script in this folder.
# Edit this file once, then run the numbered scripts in order.
# ---------------------------------------------------------------------------

# Azure subscription (from `az login` -> "[Tenant and subscription selection]").
$SubscriptionId = '74e3f720-03fa-47b3-be35-2d626dcbb655'

# Region closest to you. Both apps must be in the same region as the plan.
$Location = 'australiaeast'

# Resource group and Linux App Service plan (F1 = Free, $0).
$ResourceGroup = 'rg-swe40006-task3'
$PlanName      = 'plan-swe40006-task3'

# Web app names. They become the public DNS label, so they must be globally
# unique: clouddeploy-<something>.azurewebsites.net
$AppName    = 'clouddeploy-swe40006'
$PhpAppName = 'phpdeploy-swe40006'

# Shown on both web applications. Replace with your own details, or edit the
# values in the portal later (Configuration -> Application settings) and take
# a screenshot of that screen as evidence.
$StudentName = 'Your Full Name'
$StudentId   = 'Your Student ID'
$UnitCode    = 'SWE40006'
$AppVersion  = '1.0.0'

# ---------------------------------------------------------------------------
# Derived values - do not edit.
# ---------------------------------------------------------------------------
$CsharpUrl = "https://$AppName.azurewebsites.net"
$PhpUrl    = "https://$PhpAppName.azurewebsites.net"
$RepoRoot  = Split-Path -Parent $PSScriptRoot
$Artifacts = Join-Path $RepoRoot 'artifacts'
