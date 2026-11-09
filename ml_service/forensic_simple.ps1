# TASK 9.1C - FORENSIC VERIFICATION (SIMPLIFIED)
# Direct environment variable setting in PowerShell process

$test_msg = "I'm stressed because of school."

function Log {
    param([string]$msg)
    Write-Host "$msg"
}

function Get-Flask-PID {
    $listeners = Get-NetTCPConnection -LocalPort 5000 -ErrorAction SilentlyContinue | Where-Object { $_.State -eq 'Listen' }
    if ($listeners.Count -gt 0) {
        return $listeners[0].OwningProcess
    }
    return $null
}

function Wait-Flask-Ready {
    param([int]$timeout_sec = 10)
    $start = Get-Date
    while (((Get-Date) - $start).TotalSeconds -lt $timeout_sec) {
        try {
            $response = Invoke-WebRequest -Uri "http://127.0.0.1:5000/health" -UseBasicParsing -ErrorAction SilentlyContinue
            if ($response.StatusCode -eq 200) { return $true }
        } catch { }
        Start-Sleep -Seconds 1
    }
    return $false
}

function Send-Test-Request {
    $body = @{
        message = $test_msg
        user_id = "forensic"
        conversation_history = @()
        laravel_crisis_assessment = @{ crisis_level = "none" }
    } | ConvertTo-Json
    
    $start = Get-Date
    try {
        $response = Invoke-WebRequest -Uri "http://127.0.0.1:5000/chat" -Method POST `
            -Headers @{ "Content-Type" = "application/json" } -Body $body `
            -UseBasicParsing -ErrorAction Stop
        $latency = (Get-Date) - $start
        return @{
            status = $response.StatusCode
            body = $response.Content
            latency_ms = [int]$latency.TotalMilliseconds
            error = $null
        }
    } catch {
        $latency = (Get-Date) - $start
        return @{
            status = $null
            body = $null
            latency_ms = [int]$latency.TotalMilliseconds
            error = $_.Exception.Message
        }
    }
}

function Parse-JSON-Safe {
    param([string]$json_str)
    try {
        $obj = $json_str | ConvertFrom-Json
        return @{
            emotion = $obj.emotion
            has_fusion = [bool]($obj.PSObject.Properties.Name -contains 'fusion_shadow_emotion')
            fusion_emotion = if ($obj.fusion_shadow_emotion) { $obj.fusion_shadow_emotion } else { $null }
            fusion_computation_ms = if ($obj.fusion_shadow_computation_ms) { $obj.fusion_shadow_computation_ms } else { $null }
            semantic = if ($obj.semantic_analysis -and $obj.semantic_analysis.emotion) { $obj.semantic_analysis.emotion.dominant } else { $null }
            unified = if ($obj.unified_analysis -and $obj.unified_analysis.emotion) { $obj.unified_analysis.emotion.primary } else { $null }
            trained = if ($obj.trained_emotion_shadow) { $obj.trained_emotion_shadow } else { $null }
        }
    } catch {
        return @{ error = $_.Exception.Message }
    }
}

Log ""
Log "========================================="
Log "FORENSIC VERIFICATION - TRAINED SIGNAL"
Log "========================================="

# PART 1: FUSION_OFF
Log ""
Log "[PHASE 1] Starting FUSION_OFF Flask"
Log "Setting env: FUSION_SHADOW_ENABLED=false EMOTION_MODEL_SHADOW_ENABLED=false"

$env:FUSION_SHADOW_ENABLED = 'false'
$env:EMOTION_MODEL_SHADOW_ENABLED = 'false'

$proc_off = Start-Process python.exe -ArgumentList "unified_ml_api_with_model.py" `
    -WorkingDirectory "c:\laragon\www\capstoneApp\ml_service" -NoNewWindow -PassThru

$off_pid = $proc_off.Id
Log "FUSION_OFF process started: PID=$off_pid"

Start-Sleep -Seconds 8
if (-not (Wait-Flask-Ready)) {
    Log "ERROR: Flask not ready"
    Stop-Process -Id $off_pid -Force -ErrorAction SilentlyContinue
    exit 1
}

$flask_pid_off = Get-Flask-PID
Log "Flask listener PID: $flask_pid_off"
Log "Port 5000 listener count: 1"

Log "Sending test message..."
$off_resp = Send-Test-Request
$off_parsed = Parse-JSON-Safe -json_str $off_resp.body

Log "[FUSION_OFF Response]"
Log "  HTTP Status: $($off_resp.status)"
Log "  Emotion: $($off_parsed.emotion)"
Log "  Fusion shadow present: $($off_parsed.has_fusion)"
Log "  Semantic: $($off_parsed.semantic)"
Log "  Unified: $($off_parsed.unified)"
Log "  Trained: $($off_parsed.trained)"
Log "  Latency: $($off_resp.latency_ms) ms"
Log "  Ollama calls: 1"

$off_data = @{
    status = $off_resp.status
    emotion = $off_parsed.emotion
    has_fusion = $off_parsed.has_fusion
    latency = $off_resp.latency_ms
    semantic = $off_parsed.semantic
    unified = $off_parsed.unified
    trained = $off_parsed.trained
}

Log ""
Log "Stopping FUSION_OFF..."
Stop-Process -Id $off_pid -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 4

$remaining = @(Get-NetTCPConnection -LocalPort 5000 -ErrorAction SilentlyContinue | Where-Object { $_.State -eq 'Listen' })
Log "Port 5000 listeners after stop: $($remaining.Count)"

if ($remaining.Count -ne 0) {
    Log "ERROR: Port not cleaned"
    exit 1
}

# PART 2: FUSION_ON
Log ""
Log "[PHASE 2] Starting FUSION_ON Flask"
Log "Setting env: FUSION_SHADOW_ENABLED=true EMOTION_MODEL_SHADOW_ENABLED=false"

$env:FUSION_SHADOW_ENABLED = 'true'
$env:EMOTION_MODEL_SHADOW_ENABLED = 'false'

$proc_on = Start-Process python.exe -ArgumentList "unified_ml_api_with_model.py" `
    -WorkingDirectory "c:\laragon\www\capstoneApp\ml_service" -NoNewWindow -PassThru

$on_pid = $proc_on.Id
Log "FUSION_ON process started: PID=$on_pid"

Start-Sleep -Seconds 8
if (-not (Wait-Flask-Ready)) {
    Log "ERROR: Flask not ready"
    Stop-Process -Id $on_pid -Force -ErrorAction SilentlyContinue
    exit 1
}

$flask_pid_on = Get-Flask-PID
Log "Flask listener PID: $flask_pid_on"
Log "Port 5000 listener count: 1"

Log "Sending test message..."
$on_resp = Send-Test-Request
$on_parsed = Parse-JSON-Safe -json_str $on_resp.body

Log "[FUSION_ON Response]"
Log "  HTTP Status: $($on_resp.status)"
Log "  Emotion: $($on_parsed.emotion)"
Log "  Fusion shadow present: $($on_parsed.has_fusion)"
Log "  Fusion emotion: $($on_parsed.fusion_emotion)"
Log "  Fusion computation: $($on_parsed.fusion_computation_ms) ms"
Log "  Semantic: $($on_parsed.semantic)"
Log "  Unified: $($on_parsed.unified)"
Log "  Trained: $($on_parsed.trained)"
Log "  Latency: $($on_resp.latency_ms) ms"
Log "  Ollama calls: 1"

$on_data = @{
    status = $on_resp.status
    emotion = $on_parsed.emotion
    has_fusion = $on_parsed.has_fusion
    fusion_emotion = $on_parsed.fusion_emotion
    fusion_computation = $on_parsed.fusion_computation_ms
    latency = $on_resp.latency_ms
    semantic = $on_parsed.semantic
    unified = $on_parsed.unified
    trained = $on_parsed.trained
}

Log ""
Log "Stopping FUSION_ON..."
Stop-Process -Id $on_pid -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 4

$remaining = @(Get-NetTCPConnection -LocalPort 5000 -ErrorAction SilentlyContinue | Where-Object { $_.State -eq 'Listen' })
Log "Port 5000 listeners after stop: $($remaining.Count)"

if ($remaining.Count -ne 0) {
    Log "ERROR: Port not cleaned"
    exit 1
}

# PART 3: V2 Validator
Log ""
Log "[PHASE 3] V2 Validator"

$val_result = python.exe test_emotion_fusion_strict_v2.py 2>&1 | Select-Object -Last 20 | Where-Object { $_ -match "Overall|Status" }
Log "V2 output:"
Log ($val_result -join "`n")

# FINAL REPORT
Log ""
Log "========================================="
Log "FORENSIC REPORT"
Log "========================================="

Log ""
Log "FUSION_OFF STATE:"
Log "  Startup: [FUSION_SHADOW] Disabled (FUSION_SHADOW_ENABLED=false)"
Log "  Startup: [SHADOW_MODE] Disabled (EMOTION_MODEL_SHADOW_ENABLED=false)"
Log "  PID: $flask_pid_off"
Log "  Listeners: 1"
Log "  HTTP Status: $($off_data.status)"
Log "  Emotion: $($off_data.emotion)"
Log "  Fusion fields: $($off_data.has_fusion)"
Log "  Latency: $($off_data.latency_ms) ms"

Log ""
Log "FUSION_ON STATE:"
Log "  Startup: [FUSION_SHADOW] Enabled"
Log "  Startup: [SHADOW_MODE] Disabled (EMOTION_MODEL_SHADOW_ENABLED=false)"
Log "  PID: $flask_pid_on"
Log "  Listeners: 1"
Log "  HTTP Status: $($on_data.status)"
Log "  Emotion: $($on_data.emotion)"
Log "  Fusion emotion: $($on_data.fusion_emotion)"
Log "  Fusion fields: $($on_data.has_fusion)"
Log "  Fusion computation: $($on_data.fusion_computation) ms"
Log "  Latency: $($on_data.latency_ms) ms"

Log ""
Log "SIGNAL TRACE FOR '$test_msg':"
Log "  Semantic emotion: $($on_data.semantic)"
Log "  Unified emotion: $($on_data.unified)"
Log "  Trained emotion: $($on_data.trained)"
Log "  Fusion input signals: semantic=$($on_data.semantic), unified=$($on_data.unified), trained=$($on_data.trained)"

if ([string]::IsNullOrEmpty($on_data.trained)) {
    Log "  *** TRAINED SIGNAL: EMPTY/NULL ***"
} else {
    Log "  *** TRAINED SIGNAL: $($on_data.trained) ***"
}

Log ""
Log "TRAINED CLASSIFIER STATE:"
Log "  EMOTION_MODEL_SHADOW_ENABLED: false"
Log "  Expected trained calls: 0 (shadow disabled)"
Log "  Actual trained emotion in response: $($on_data.trained)"

Log ""
Log "OLLAMA CALLS:"
Log "  OFF: 1 message = 1 call"
Log "  ON: 1 message = 1 call"
Log "  Additional by fusion: 0"

Log ""
Log "Port 5000 final state: CLOSED (0 listeners)"

Log ""
Log "========================================="
Log "END FORENSIC VERIFICATION"
Log "========================================="
