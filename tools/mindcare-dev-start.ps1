[CmdletBinding()]
param(
    [int]$LaravelPort = 8086,
    [int]$FlaskPort = 5018,
    [string]$MySqlHost = '127.0.0.1',
    [int]$MySqlPort = 3306,
    [string]$MySqlUser = 'root',
    [System.Management.Automation.PSCredential]$MySqlCredential,
    [switch]$SkipChatSmoke,
    [string]$RuntimeRoot
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$MlRoot = Join-Path $ProjectRoot 'ml_service'
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$nonce = ([Guid]::NewGuid().ToString('N')).Substring(0, 8)

function Select-WritableRuntimeRoot([string]$RequestedRoot) {
    $candidates = @()
    if ($RequestedRoot) { $candidates += $RequestedRoot }
    else {
        $candidates += 'C:\tmp'
        if ($env:TEMP) { $candidates += $env:TEMP }
        if ($env:TMP -and $env:TMP -ne $env:TEMP) { $candidates += $env:TMP }
        $candidates += [IO.Path]::GetTempPath()
    }
    foreach ($candidate in ($candidates | Where-Object { $_ } | Select-Object -Unique)) {
        try {
            $root = [IO.Path]::GetFullPath($candidate)
            New-Item -ItemType Directory -Force -Path $root -ErrorAction Stop | Out-Null
            $probe = Join-Path $root ('.mindcare-write-' + [Guid]::NewGuid().ToString('N'))
            New-Item -ItemType Directory -Path $probe -ErrorAction Stop | Out-Null
            Remove-Item -LiteralPath $probe -Force -Recurse -ErrorAction Stop
            return $root.TrimEnd('\','/')
        } catch {
            if ($RequestedRoot) { throw "Requested runtime root '$candidate' is not writable: $($_.Exception.Message)" }
        }
    }
    throw 'No writable runtime root was available. Supply -RuntimeRoot pointing to an authorized temporary directory.'
}

$runtimeRoot = Select-WritableRuntimeRoot $RuntimeRoot
$runtime = Join-Path $runtimeRoot "mindcare-dev-$stamp-$nonce"
$statePath = Join-Path $runtime 'state.json'
$laravelLog = Join-Path $runtime 'laravel.log'
$flaskLog = Join-Path $runtime 'flask.log'
$flaskErr = Join-Path $runtime 'flask.err.log'
$diagnosticsLog = Join-Path $runtime 'mindcare-diagnostics.jsonl'
$memoryDb = Join-Path $runtime 'memory.sqlite'
$consentDb = Join-Path $runtime 'consent-and-deletion.sqlite'
$replayDb = Join-Path $runtime 'replay.sqlite'
$mysqlExe = $null
$started = @()
$state = $null
$workflowStart = Get-Date

function Fail([string]$Message) { throw "MindCare local startup failed: $Message" }

function Find-Executable([string]$Name, [string[]]$Candidates = @()) {
    $command = Get-Command $Name -ErrorAction SilentlyContinue
    if ($command) { return $command.Source }
    foreach ($candidate in $Candidates) {
        if (Test-Path -LiteralPath $candidate) { return (Resolve-Path -LiteralPath $candidate).Path }
    }
    return $null
}

function Assert-PortFree([int]$Port) {
    $client = [Net.Sockets.TcpClient]::new()
    try {
        $task = $client.ConnectAsync('127.0.0.1', $Port)
        if ($task.Wait(300) -and $client.Connected) { Fail "TCP port $Port is already in use." }
    } finally {
        $client.Dispose()
    }
}

function Invoke-MySql([string]$Sql, [string]$Database = $null) {
    $sqlPath = Join-Path $runtime ("sql-" + [Guid]::NewGuid().ToString('N') + '.sql')
    [System.IO.File]::WriteAllText($sqlPath, $Sql, [System.Text.UTF8Encoding]::new($false))
    try {
        # Pipe the temporary SQL file through stdin so SQL is not placed in a process argument.
        $mysqlArgs = @('--protocol=TCP', '-h', $MySqlHost, '-P', "$MySqlPort", '-u', $MySqlUser, '--batch', '--skip-column-names')
        if ($Database) { $mysqlArgs += @('-D', $Database) }
        $output = Get-Content -LiteralPath $sqlPath -Raw | & $mysqlExe @mysqlArgs 2>&1
        if ($LASTEXITCODE -ne 0) { Fail "MySQL command failed (exit $LASTEXITCODE); verify the service, host, user, and supply -MySqlCredential when password-protected." }
        return @($output)
    } finally {
        Remove-Item -LiteralPath $sqlPath -Force -ErrorAction SilentlyContinue
    }
}

function Stop-StartedProcesses {
    $rootIds = @($started | ForEach-Object { [int]$_.Id })
    $allIds = [Collections.Generic.HashSet[int]]::new()
    foreach ($id in $rootIds) { [void]$allIds.Add($id) }
    try {
        $processTable = @(Get-CimInstance Win32_Process -ErrorAction Stop)
        $changed = $true
        while ($changed) {
            $changed = $false
            foreach ($child in $processTable) {
                if ($allIds.Contains([int]$child.ParentProcessId) -and $allIds.Add([int]$child.ProcessId)) { $changed = $true }
            }
        }
    } catch { $processTable = @() }
    foreach ($id in @($allIds | Sort-Object -Descending)) {
        try {
            $p = Get-Process -Id $id -ErrorAction Stop
            if ($p.StartTime -ge $workflowStart.AddSeconds(-2)) { Stop-Process -Id $p.Id -Force -ErrorAction SilentlyContinue }
        } catch { }
    }
}

try {
    if (-not (Test-Path -LiteralPath $ProjectRoot)) { Fail 'Project root is unavailable.' }
    $php = Find-Executable 'php'
    $python = Find-Executable 'python' @('C:\Python312\python.exe', 'C:\Python311\python.exe')
    $mysqlExe = Find-Executable 'mysql' @('C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe')
    if (-not $php) { Fail 'PHP executable was not found on PATH.' }
    if (-not $python) { Fail 'Python executable was not found on PATH.' }
    if (-not $mysqlExe) { Fail 'MySQL client was not found; install/use the existing Laragon client or pass it on PATH.' }
    if (-not (Test-Path -LiteralPath (Join-Path $MlRoot 'phase4a_secure_api.py'))) { Fail 'Flask secure entry point is missing.' }
    if (-not (Test-Path -LiteralPath (Join-Path $MlRoot 'emotion_model_multilingual_deployable\model.safetensors'))) { Fail 'Trained RoBERTa checkpoint is missing.' }
    Assert-PortFree $LaravelPort
    Assert-PortFree $FlaskPort

    $historicalDb = $null
    $envFile = Join-Path $ProjectRoot '.env'
    if (Test-Path -LiteralPath $envFile) {
        $line = Select-String -LiteralPath $envFile -Pattern '^DB_DATABASE=' | Select-Object -First 1
        if ($line) { $historicalDb = ($line.Line -replace '^DB_DATABASE=', '').Trim().Trim('"') }
    }
    $dbName = "mindcare_dev_$stamp`_$nonce".ToLowerInvariant()
    if ($historicalDb -and $dbName -eq $historicalDb) { Fail 'Generated database name equals the historical database name.' }

    New-Item -ItemType Directory -Force -Path $runtime | Out-Null
    $bytes = New-Object byte[] 32
    $rng = New-Object System.Security.Cryptography.RNGCryptoServiceProvider; $rng.GetBytes($bytes); $rng.Dispose()
    $hmacSecret = [Convert]::ToBase64String($bytes)
    $pwBytes = New-Object byte[] 18
    $rng = New-Object System.Security.Cryptography.RNGCryptoServiceProvider; $rng.GetBytes($pwBytes); $rng.Dispose()
    $syntheticPassword = [Convert]::ToBase64String($pwBytes)
    $syntheticEmail = "mindcare-dev-$nonce@example.test"

    # Credential is held only in this process and inherited by the MySQL client.
    $oldMysqlPwd = $env:MYSQL_PWD
    if ($MySqlCredential) { $env:MYSQL_PWD = $MySqlCredential.GetNetworkCredential().Password }
    try {
        $existing = Invoke-MySql 'SHOW DATABASES;'
        if ($existing -contains $dbName) { Fail "Disposable database $dbName already exists." }
        Invoke-MySql "CREATE DATABASE ``$dbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | Out-Null
        $check = Invoke-MySql 'SHOW DATABASES;'
        if (-not ($check -contains $dbName)) { Fail 'Disposable MySQL database was not created.' }
    } finally {
        if ($null -eq $oldMysqlPwd) { Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue } else { $env:MYSQL_PWD = $oldMysqlPwd }
    }

    $env:APP_ENV = 'local'
    $env:APP_DEBUG = 'false'
    $env:APP_URL = "http://127.0.0.1:$LaravelPort"
    $env:DB_CONNECTION = 'mysql'; $env:DB_HOST = $MySqlHost; $env:DB_PORT = "$MySqlPort"; $env:DB_DATABASE = $dbName; $env:DB_USERNAME = $MySqlUser
    if ($MySqlCredential) { $env:DB_PASSWORD = $MySqlCredential.GetNetworkCredential().Password } else { Remove-Item Env:DB_PASSWORD -ErrorAction SilentlyContinue }
    $env:MINDCARE_ML_API_URL = "http://127.0.0.1:$FlaskPort"
    $env:MINDCARE_PHASE4A_AUTH_ENABLED = 'true'; $env:MINDCARE_PHASE4A_PORT = "$FlaskPort"
    $env:MINDCARE_INTERNAL_HMAC_SECRET = $hmacSecret; $env:MINDCARE_INTERNAL_HMAC_KEY_ID = 'local-dev'
    $env:MINDCARE_INTERNAL_HMAC_ISSUER = 'laravel-local-dev'; $env:MINDCARE_INTERNAL_HMAC_AUDIENCE = 'mindcare-flask-local-dev'; $env:MINDCARE_INTERNAL_HMAC_TTL = '60'
    $env:MINDCARE_INTERNAL_HMAC_REPLAY_DB = $replayDb
    $env:MINDCARE_PHASE4B_CONSENT_DB = $consentDb; $env:MINDCARE_PHASE4B_POLICY_VERSION = 'synthetic-local-dev-v1'
    $env:PRIVACY_CONSENT_POLICY_VERSION = 'synthetic-local-dev-v1'
    $env:MINDCARE_MEMORY_DB_PATH = $memoryDb
    $env:MINDCARE_DIAGNOSTICS_LOG = $diagnosticsLog
    $env:MINDCARE_DIAGNOSTICS_ENABLED = 'true'
    $env:MINDCARE_MEMORY_ENABLED = 'false'
    Remove-Item Env:CRISIS_SAFEGUARD_POLICY_VERSION -ErrorAction SilentlyContinue
    Remove-Item Env:CRISIS_AUTHORIZED_COUNSELOR_IDS -ErrorAction SilentlyContinue
    Remove-Item Env:MINDCARE_PHASE4A_SYNTHETIC_MODE -ErrorAction SilentlyContinue
    $env:MINDCARE_DEV_SYNTH_EMAIL = $syntheticEmail; $env:MINDCARE_DEV_SYNTH_PASSWORD = $syntheticPassword

    Push-Location $ProjectRoot
    try {
        & $php 'artisan' 'config:clear' | Out-File -FilePath $laravelLog -Encoding utf8
        if ($LASTEXITCODE -ne 0) { Fail 'Laravel config:clear failed.' }
        & $php 'artisan' 'migrate:fresh' '--force' | Out-File -FilePath $laravelLog -Append -Encoding utf8
        if ($LASTEXITCODE -ne 0) { Fail "Laravel migrations failed; inspect $laravelLog." }
        $hashCode = '$pw=getenv(''MINDCARE_DEV_SYNTH_PASSWORD''); echo password_hash($pw, PASSWORD_BCRYPT);'
        $passwordHash = (& $php '-r' $hashCode 2>$null | Out-String).Trim()
        if ($passwordHash -notmatch '^\$2[aby]\$') { Fail 'Synthetic password hash generation failed.' }
        $seedSql = "INSERT INTO users (name,email,password,role,student_id,is_active,status,created_at,updated_at) VALUES ('Synthetic Local Student','$syntheticEmail','$passwordHash','student','DEV-$nonce',1,'active',NOW(),NOW()); SET @uid=LAST_INSERT_ID(); INSERT INTO privacy_settings (user_id,allow_ai_recommendations,allow_crisis_alerts,ai_recommendations_consented_at,consent_policy_version,consent_source,created_at,updated_at) VALUES (@uid,1,0,NOW(),'synthetic-local-dev-v1','synthetic-local-dev',NOW(),NOW()); SELECT @uid;"
        $seedOutput = (Invoke-MySql $seedSql $dbName | Out-String).Trim()
        if ($seedOutput -notmatch '\d+') { Fail 'Synthetic Laravel account could not be created.' }
        $syntheticUserId = [int]([regex]::Match($seedOutput, '\d+').Value)
    } finally { Pop-Location }

    $flask = Start-Process -FilePath $python -ArgumentList @('-u','phase4a_secure_api.py') -WorkingDirectory $MlRoot -RedirectStandardOutput $flaskLog -RedirectStandardError $flaskErr -WindowStyle Hidden -PassThru
    $started += [pscustomobject]@{ Id=$flask.Id; StartTimeUtc=$flask.StartTime.ToUniversalTime().ToString('o'); Kind='flask' }
    $deadline = (Get-Date).AddSeconds(180); $health = $null
    while ((Get-Date) -lt $deadline) { try { $health = Invoke-RestMethod "http://127.0.0.1:$FlaskPort/health" -TimeoutSec 3; if ($health.status -eq 'ok') { break } } catch { Start-Sleep -Milliseconds 500 } }
    if (-not $health -or $health.model_type -ne 'trained') { Fail "Flask did not report a trained model; inspect $flaskErr." }

    $laravel = Start-Process -FilePath $php -ArgumentList @('artisan','serve','--host=127.0.0.1',"--port=$LaravelPort") -WorkingDirectory $ProjectRoot -RedirectStandardOutput $laravelLog -RedirectStandardError (Join-Path $runtime 'laravel.err.log') -WindowStyle Hidden -PassThru
    $started += [pscustomobject]@{ Id=$laravel.Id; StartTimeUtc=$laravel.StartTime.ToUniversalTime().ToString('o'); Kind='laravel' }
    $deadline = (Get-Date).AddSeconds(45); $loginPage = $null
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    while ((Get-Date) -lt $deadline) { try { $loginPage = Invoke-WebRequest "http://127.0.0.1:$LaravelPort/login" -WebSession $session -UseBasicParsing -TimeoutSec 3; if ($loginPage.StatusCode -eq 200) { break } } catch { Start-Sleep -Milliseconds 500 } }
    if (-not $loginPage -or $loginPage.StatusCode -ne 200) { Fail "Laravel did not serve /login; inspect $laravelLog." }

    $state = [ordered]@{ RuntimeDirectory=$runtime; LaravelPort=$LaravelPort; FlaskPort=$FlaskPort; DisposableDatabase=$dbName; HistoricalDatabase=$historicalDb; SyntheticUserId=$syntheticUserId; SyntheticEmail=$syntheticEmail; Processes=$started; PersistentMemoryEnabled=$false; InstitutionalEscalationEnabled=$false; StartedUtc=(Get-Date).ToUniversalTime().ToString('o') }
    $state | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $statePath -Encoding utf8

    $unsignedStatus = $null
    try { Invoke-WebRequest "http://127.0.0.1:$FlaskPort/chat" -Method Post -ContentType 'application/json' -Body '{"user_id":"unauthenticated","message":"synthetic"}' -UseBasicParsing -TimeoutSec 10 | Out-Null } catch { if ($_.Exception.Response) { $unsignedStatus = [int]$_.Exception.Response.StatusCode } }
    if ($unsignedStatus -ne 401) { Fail "Unsigned Flask /chat request did not fail closed with HTTP 401 (got $unsignedStatus)." }

    $ollama = Invoke-RestMethod 'http://127.0.0.1:11434/api/tags' -TimeoutSec 5
    if (-not ($ollama.models | Where-Object { $_.name -eq 'gemma3:4b' })) { Fail 'Ollama is reachable but gemma3:4b is not installed.' }
    $ollamaBody = @{ model='gemma3:4b'; prompt='Reply only with OK.'; stream=$false } | ConvertTo-Json
    $ollamaResult = Invoke-RestMethod 'http://127.0.0.1:11434/api/generate' -Method Post -ContentType 'application/json' -Body $ollamaBody -TimeoutSec 120
    if (-not $ollamaResult.response) { Fail 'Ollama gemma3:4b returned no response.' }

    if (-not $SkipChatSmoke) {
        $tokenMatch = [regex]::Match($loginPage.Content, 'name=["'']_token["''][^>]*value=["'']([^"'']+)', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
        if (-not $tokenMatch.Success) { Fail 'Laravel login form did not expose a CSRF token.' }
        $loginForm = @{ _token=$tokenMatch.Groups[1].Value; email=$syntheticEmail; password=$syntheticPassword; remember='0' }
        try {
            $loginResult = Invoke-WebRequest "http://127.0.0.1:$LaravelPort/login" -Method Post -Body $loginForm -WebSession $session -UseBasicParsing -MaximumRedirection 5 -TimeoutSec 15
        } catch { Fail "Synthetic Laravel login request failed: $($_.Exception.Message)" }
        if ($loginResult.StatusCode -ne 200) { Fail "Synthetic Laravel login returned HTTP $($loginResult.StatusCode)." }
        try {
            $sessionResponse = Invoke-RestMethod "http://127.0.0.1:$LaravelPort/api/ai/sessions" -Method Post -Headers @{ Accept='application/json' } -ContentType 'application/json' -Body '{}' -WebSession $session -TimeoutSec 15
        } catch { Fail "Synthetic session creation request failed: $($_.Exception.Message)" }
        if (-not $sessionResponse.success) { Fail 'Synthetic conversation session was not created.' }
        $sid = [int]$sessionResponse.session_id
        $headers = @{ Accept='application/json' }
        $init = Invoke-RestMethod "http://127.0.0.1:$LaravelPort/api/ai/initialize-session" -Method Post -Headers $headers -ContentType 'application/json' -Body (@{ session_id=$sid } | ConvertTo-Json) -WebSession $session -TimeoutSec 45
        if (-not $init.success) { Fail 'Initial greeting smoke request failed.' }
        $chat = Invoke-RestMethod "http://127.0.0.1:$LaravelPort/api/ai/chat" -Method Post -Headers $headers -ContentType 'application/json' -Body (@{ user_id=$syntheticUserId; session_id=$sid; message='I feel okay and steady right now.' } | ConvertTo-Json) -WebSession $session -TimeoutSec 120
        if (-not $chat.success) { Fail 'Ordinary signed Laravel-to-Flask chat failed.' }
        Write-Output "CHAT_SMOKE=PASS session=$sid emotion=$($chat.emotion)"
    }

    Write-Output "MINDCARE_DEV_RUNTIME=$runtime"
    Write-Output "LARAVEL_URL=http://127.0.0.1:$LaravelPort"
    Write-Output "FLASK_URL=http://127.0.0.1:$FlaskPort"
    Write-Output "DISPOSABLE_DATABASE=$dbName"
    Write-Output "FLASK_HEALTH=PASS model=$($health.model_type)"
    Write-Output 'HMAC_UNSIGNED_REQUEST=401'
    Write-Output 'OLLAMA_GEMMA3_4B=PASS'
    Write-Output "STOP_COMMAND=.\tools\mindcare-dev-stop.ps1 -StatePath '$statePath'"
} catch {
    Stop-StartedProcesses
    Write-Error $_
    exit 1
}
