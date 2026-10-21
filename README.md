# SWE40006 Task 3 — Deployment to Microsoft Azure

Deployment portfolio for **SWE40006 Software Deployment and Evolution**.

Two independently deployed web applications run on Microsoft Azure App Service:

| Application | URL | Runtime | Status endpoint |
|---|---|---|---|
| C# / ASP.NET Core dashboard | https://clouddeploy-swe40006.azurewebsites.net | .NET 9 (Linux) | `/health`, `/api/deployment` |
| PHP manifest validator | https://phpdeploy-swe40006.azurewebsites.net | PHP 8.3 (Linux) | `/health`, `/api/info` |

Both apps share a single **Linux App Service plan, SKU F1 (Free)** in `australiaeast`,
which is why the whole deployment costs $0 while still using the real platform
(no local emulator, no static hosting).

---

## What each task required

| Sub-task | Requirement | Where it is evidenced |
|---|---|---|
| 3.1 | Create Azure account, install Visual Studio / VS Code, deploy an existing app | [`docs/01-STEP-BY-STEP.md`](docs/01-STEP-BY-STEP.md), report §3 |
| 3.2 | Develop a C# app, deploy it, deactivate it | [`src/DeploymentDashboard`](src/DeploymentDashboard), report §4–5 |
| 3.3 | Install PHP, develop a PHP web app, deploy it | [`src/php-webapp`](src/php-webapp), report §6 |

---

## Repository layout

```
src/
  DeploymentDashboard/       ASP.NET Core 9 Razor Pages application (Task 3.2)
    Models/                  DeploymentInfo, DeploymentTask
    Services/                DeploymentInfoProvider, InMemoryTaskStore
    Pages/                   Index, Tasks, About, Error
    wwwroot/css/site.css     Hand-written stylesheet (no CDN dependency)
  DeploymentConsole/         C# console app: release pre-flight checker (Task 3.2)
  php-webapp/                PHP 8.3 application (Task 3.3)
    index.php                Front controller and routing table
    bootstrap.php            Autoloader, error handling, logging to stderr
    src/Deployment/          EnvironmentReport, SelfTest, ManifestValidator
    src/Support/             Env, View
    templates/               layout, home, validate, about, not-found, error
    deploy/default           Custom nginx config (front-controller rewrite)
    tests/run-tests.php      Dependency-free test suite (19 assertions)
tests/
  DeploymentDashboard.Tests/ xUnit test suite (13 tests)
scripts/                     Azure CLI deployment scripts (PowerShell)
docs/                        Step-by-step guide, error catalogue, screenshot list
report/                      Submission-ready report
```

---

## Local development

### C# application

```powershell
dotnet test tests\DeploymentDashboard.Tests\DeploymentDashboard.Tests.csproj -c Release
dotnet run --project src\DeploymentDashboard
# http://localhost:5216
```

### C# release pre-flight checker

```powershell
dotnet run --project src\DeploymentConsole -- --path src\DeploymentDashboard
```

### PHP application

```powershell
# PHP 8.3 must be on PATH
php src\php-webapp\tests\run-tests.php
php -S localhost:8000 -t src\php-webapp src\php-webapp\router.php
# http://localhost:8000
```

`router.php` exists only for PHP's built-in web server, which has no rewrite
rules. On App Service the same behaviour comes from `src/php-webapp/deploy/default`
(custom nginx configuration with `try_files`).

---

## Deployment

### Manual (what was actually used to produce the screenshots)

```powershell
.\scripts\01-create-resources.ps1   # resource group, plan, both web apps
.\scripts\02-deploy-csharp.ps1      # test -> publish -> zip deploy -> probe
.\scripts\03-deploy-php.ps1         # test -> stage -> zip deploy -> probe
.\scripts\04-verify.ps1             # writes artifacts\verify-<timestamp>.txt
.\scripts\05-stop-apps.ps1          # Task 3.2 deactivation evidence
.\scripts\06-start-apps.ps1         # bring both apps back for marking
.\scripts\07-logs.ps1 -App <name>   # live log stream
.\scripts\09-probe-url.ps1 -Url <url>  # capture an error response body
.\scripts\08-cleanup.ps1            # delete the resource group when finished
```

Edit [`scripts/config.ps1`](scripts/config.ps1) first (subscription id, names,
student details).

### Continuous deployment (GitHub Actions)

Two workflows deploy on push to `main`:

| Workflow | Trigger paths | Target |
|---|---|---|
| [`.github/workflows/deploy-csharp.yml`](.github/workflows/deploy-csharp.yml) | `src/DeploymentDashboard/**`, `src/DeploymentConsole/**`, `tests/**` | `clouddeploy-swe40006` |
| [`.github/workflows/deploy-php.yml`](.github/workflows/deploy-php.yml) | `src/php-webapp/**` | `phpdeploy-swe40006` |

Both authenticate with **OpenID Connect** (`azure/login@v2`), so the repository
stores no password and no publishing profile. The required repository secrets
are `AZURE_CLIENT_ID`, `AZURE_TENANT_ID` and `AZURE_SUBSCRIPTION_ID`; the
federated credential is scoped to `repo:hung7405/SWE40006-Task3-Deployment-in-azure:ref:refs/heads/main`
and the service principal only has **Contributor** on the resource group
`rg-swe40006-task3`.

---

## Public endpoints

### C# application

| Route | Purpose |
|---|---|
| `/` | Runtime dashboard (process, .NET version, App Service region/instance) |
| `/Tasks` | Validated POST handlers mutating an in-memory checklist |
| `/api/deployment` | Deployment facts as JSON |
| `/health` | Health probe used by App Service |

### PHP application

| Route | Purpose |
|---|---|
| `/` | Runtime dashboard plus seven self-tests |
| `/validate` | Twelve-rule Azure deployment manifest validator |
| `/api/info` | Environment + self-test results as JSON |
| `/health` | Health probe (HTTP 503 when a critical check fails) |
| `/about` | Stack and deployment pipeline |

---

## Cost and clean-up

The plan is `F1` (Free). It has a daily CPU quota, no Always On and no
deployment slots; the first request after ~20 minutes of inactivity takes about
20 seconds (cold start). Run `scripts\08-cleanup.ps1` after marking to remove the
resource group.

---

## Student

- **Name:** _fill in_
- **Student ID:** _fill in_
- **Unit:** SWE40006 Software Deployment and Evolution
- **Assessment:** Deployment Portfolio Task 3 (HD — sub-tasks 3.1, 3.2 and 3.3)
