# ---------------------------------------------------------------------------
# 04 - Release verification (evidence for the report)
#
#   .\scripts\04-verify.ps1
#
# Calls every public endpoint on both applications and writes a timestamped
# transcript to artifacts\verify-<time>.txt. Paste that transcript into the
# report instead of the source code, as the assignment requires.
# ---------------------------------------------------------------------------
. "$PSScriptRoot/config.ps1"
$ErrorActionPreference = 'Continue'

New-Item -ItemType Directory -Force -Path $Artifacts | Out-Null
$transcript = Join-Path $Artifacts ("verify-{0:yyyyMMdd-HHmmss}.txt" -f (Get-Date))

$lines = New-Object System.Collections.Generic.List[string]
function Log([string]$text) {
    $lines.Add($text)
    Write-Host $text
}

Log "SWE40006 Task 3 - release verification"
Log ("Timestamp : {0:u}" -f (Get-Date))
Log ("C#  app   : {0}" -f $CsharpUrl)
Log ("PHP app   : {0}" -f $PhpUrl)
Log ''

$targets = @(
    @{ Name = 'C#  /health';          Method = 'GET';  Url = "$CsharpUrl/health" },
    @{ Name = 'C#  /api/deployment';  Method = 'GET';  Url = "$CsharpUrl/api/deployment" },
    @{ Name = 'C#  /';                Method = 'GET';  Url = "$CsharpUrl/" },
    @{ Name = 'PHP /health';          Method = 'GET';  Url = "$PhpUrl/health" },
    @{ Name = 'PHP /api/info';        Method = 'GET';  Url = "$PhpUrl/api/info" },
    @{ Name = 'PHP /';                Method = 'GET';  Url = "$PhpUrl/" },
    @{ Name = 'PHP /validate';        Method = 'GET';  Url = "$PhpUrl/validate" },
    @{ Name = 'PHP /validate (API)';  Method = 'POST'; Url = "$PhpUrl/validate" }
)

foreach ($target in $targets) {
    $stopwatch = [System.Diagnostics.Stopwatch]::StartNew()
    try {
        if ($target.Method -eq 'POST') {
            $body = '{"appName":"clouddeploy-swe40006","runtime":"DOTNETCORE:9.0","os":"linux","region":"australiaeast","sku":"F1","healthCheckPath":"/health","httpsOnly":true,"minTlsVersion":"1.2","alwaysOn":false,"appSettings":{"Student__Id":"12345678"}}'
            $response = Invoke-WebRequest -Uri $target.Url -Method Post -ContentType 'application/json' -Body $body -UseBasicParsing -TimeoutSec 90
        }
        else {
            $response = Invoke-WebRequest -Uri $target.Url -UseBasicParsing -TimeoutSec 90
        }
        $stopwatch.Stop()
        Log ("{0,-22} HTTP {1}  {2,5:N0} ms  {3:N0} bytes" -f $target.Name, $response.StatusCode, $stopwatch.Elapsed.TotalMilliseconds, $response.RawContentLength)
    }
    catch {
        $stopwatch.Stop()
        $status = 'n/a'
        if ($_.Exception.Response) { $status = [int]$_.Exception.Response.StatusCode }
        Log ("{0,-22} HTTP {1}  {2,5:N0} ms  {3}" -f $target.Name, $status, $stopwatch.Elapsed.TotalMilliseconds, $_.Exception.Message)
    }
}

Log ''
Log 'Resource state'
$rgJson = az group show --name $ResourceGroup --query "{name:name, location:location, state:properties.provisioningState}" -o tsv
Log ("  resource group : {0}" -f ($rgJson -join "`t"))

foreach ($app in @($AppName, $PhpAppName)) {
    $state = az webapp show --resource-group $ResourceGroup --name $app --query "state" -o tsv
    $runtime = az webapp show --resource-group $ResourceGroup --name $app --query "siteConfig.linuxFxVersion" -o tsv
    Log ("  {0,-26} state={1,-10} runtime={2}" -f $app, $state, $runtime)
}

$lines | Set-Content -Path $transcript -Encoding UTF8
Write-Host ''
Write-Host "Transcript written to $transcript" -ForegroundColor Green
