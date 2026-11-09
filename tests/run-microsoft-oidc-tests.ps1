$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$opensslConfig = (Resolve-Path (Join-Path $PSScriptRoot 'Fixtures/openssl.cnf')).Path
$previousOpenSslConfig = [Environment]::GetEnvironmentVariable('OPENSSL_CONF', 'Process')
$locationPushed = $false
$testExitCode = 1

try {
    [Environment]::SetEnvironmentVariable('OPENSSL_CONF', $opensslConfig, 'Process')
    Push-Location $projectRoot
    $locationPushed = $true
    & php artisan test --filter=MicrosoftOidcServiceTest
    $testExitCode = $LASTEXITCODE
}
finally {
    if ($locationPushed) {
        Pop-Location
    }
    [Environment]::SetEnvironmentVariable('OPENSSL_CONF', $previousOpenSslConfig, 'Process')
}

exit $testExitCode
