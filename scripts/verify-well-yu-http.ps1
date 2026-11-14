$ErrorActionPreference = 'Stop'
# Fresh unauthenticated GETs only. No browser JavaScript, forms or write APIs.
$baseUrl = 'http://localhost/WELL-YU'
$checks = @{
    '/api/health.php' = @(200)
    '/' = @(200)
    '/investor-login.php' = @(200)
    '/app/config.local.php' = @(403, 404)
    '/database/well-yu/setup.sql' = @(403, 404)
    '/data/sessions/' = @(403, 404)
    '/.git/config' = @(403, 404)
    '/.env' = @(403, 404)
}
foreach ($relativePath in $checks.Keys) {
    $response = $null
    try {
        $response = Invoke-WebRequest -Uri ($baseUrl + $relativePath) -UseBasicParsing -MaximumRedirection 0 -TimeoutSec 15
        $status = [int] $response.StatusCode
    } catch {
        if (!$_.Exception.Response) { throw }
        $status = [int] $_.Exception.Response.StatusCode
    }
    if ($status -notin $checks[$relativePath]) { throw "Unexpected HTTP $status for $relativePath" }
    if ($relativePath -eq '/api/health.php' -and ($response.Content | ConvertFrom-Json).status -ne 'ok') {
        throw 'Database health response failed.'
    }
    if ($relativePath -in @('/', '/investor-login.php')) {
        $cookie = [string] $response.Headers['Set-Cookie']
        if ($cookie -notmatch 'WELL_YU_SESSION=[^;]+;[^\r\n]*path=/WELL-YU/') {
            throw "Missing isolated session cookie for $relativePath"
        }
        if ($response.Content -notmatch '<base href="/WELL-YU/">') {
            throw "Incorrect application base path for $relativePath"
        }
    }
    Write-Output "PASS HTTP $status $relativePath"
}
Write-Output 'HTTP checks passed. No browser scripts or write workflows were executed.'
