# Evidence helper: capture the exact text of an HTTP error response.
#
#   .\scripts\09-probe-url.ps1 -Url https://clouddeploy-swe40006.azurewebsites.net/
#
# Prints the status code, the server header and the response body. Use this
# when the browser shows a generic "Application Error" page: the body usually
# contains the App Service error code (500.30, 500.31, 503 ...) that tells you
# which platform layer failed.

param(
    [Parameter(Mandatory = $true)]
    [string]$Url
)

$ErrorActionPreference = 'Continue'

try {
    $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 90
    Write-Host ("HTTP {0}" -f $response.StatusCode) -ForegroundColor Green
    Write-Host ("Server: {0}" -f $response.Headers['Server'])
    Write-Host ('-' * 70)
    Write-Host $response.Content
}
catch {
    $response = $_.Exception.Response
    if ($response) {
        Write-Host ("HTTP {0}" -f [int]$response.StatusCode) -ForegroundColor Red
        Write-Host ("Server: {0}" -f $response.Headers['Server'])
        Write-Host ('-' * 70)
        $reader = New-Object System.IO.StreamReader($response.GetResponseStream())
        Write-Host $reader.ReadToEnd()
    }
    else {
        Write-Host $_.Exception.Message -ForegroundColor Red
    }
}
