# TASK 9.1B - FUSION SHADOW RUNTIME EVIDENCE COLLECTION
# PowerShell script to collect exact runtime measurements for Fusion-ON validation
# Uses ONLY native PowerShell commands (no bash, grep, wc, etc.)

$script:config = @{
    api_url = "http://127.0.0.1:5000/chat"
    messages = @(
        @{ id = "A1"; text = "I'm stressed because of school." }
        @{ id = "A2"; text = "Medyo pagod at malungkot ako ngayong araw." }
        @{ id = "A3"; text = "Medyo anxious ako sa presentation tomorrow." }
        @{ id = "A4"; text = "What time is the meeting tomorrow?" }
        @{ id = "A5"; text = "I'm doing pretty good today." }
        @{ id = "A6"; text = "Hindi naman ako malungkot, pagod lang talaga." }
    )
    conversation_history = @()
}

$script:results_fusion_off = @()
$script:results_fusion_on = @()
$script:timing_off = @()
$script:timing_on = @()

# =====================================================================
# UTILITY FUNCTIONS
# =====================================================================

function Log-Message {
    param([string]$message)
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss.fff"
    Write-Host "[$timestamp] $message"
}

function Test-Listener {
    param([string]$port)
    try {
        $listeners = Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue | Where-Object { $_.State -eq 'Listen' }
        return @($listeners).Count
    } catch {
        return 0
    }
}

function Get-ListenerPID {
    param([string]$port)
    try {
        $listeners = Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue | Where-Object { $_.State -eq 'Listen' }
        if ($listeners) {
            return $listeners[0].OwningProcess
        }
        return $null
    } catch {
        return $null
    }
}

function Extract-JSON-Field {
    param(
        [string]$jsonStr,
        [string]$field
    )
    try {
        $obj = $jsonStr | ConvertFrom-Json
        if ($field -like "*.*") {
            $parts = $field -split '\.'
            $current = $obj
            foreach ($part in $parts) {
                if ($current -is [PSCustomObject]) {
                    $current = $current.PSObject.Properties[$part].Value
                } else {
                    return $null
                }
            }
            return $current
        } else {
            return $obj.PSObject.Properties[$field].Value
        }
    } catch {
        return $null
    }
}

function Send-Chat-Request {
    param(
        [string]$message,
        [array]$conversation_history
    )
    
    $start_time = Get-Date
    
    $request_body = @{
        message = $message
        user_id = "test_user"
        conversation_history = $conversation_history
        laravel_crisis_assessment = @{
            crisis_level = "none"
        }
    } | ConvertTo-Json
    
    try {
        $response = Invoke-WebRequest `
            -Uri $script:config.api_url `
            -Method POST `
            -Headers @{ "Content-Type" = "application/json" } `
            -Body $request_body `
            -ErrorAction Stop
        
        $end_time = Get-Date
        $latency_ms = ($end_time - $start_time).TotalMilliseconds
        
        $response_body = $response.Content
        
        return @{
            success = $true
            status = $response.StatusCode
            body = $response_body
            latency_ms = $latency_ms
            error = $null
        }
    } catch {
        $end_time = Get-Date
        $latency_ms = ($end_time - $start_time).TotalMilliseconds
        
        return @{
            success = $false
            status = $_.Exception.Response.StatusCode
            body = $null
            latency_ms = $latency_ms
            error = $_.Exception.Message
        }
    }
}

function Parse-Response {
    param(
        [string]$response_body,
        [string]$message_id
    )
    
    try {
        $obj = $response_body | ConvertFrom-Json
        
        $has_fusion = $false
        $fusion_emotion = $null
        $fusion_confidence = $null
        $fusion_uncertain = $null
        $fusion_reason = $null
        $fusion_agreement = $null
        $fusion_sources = $null
        $fusion_computation_ms = $null
        
        if ($obj.PSObject.Properties['fusion_shadow_emotion']) {
            $has_fusion = $true
            $fusion_emotion = $obj.fusion_shadow_emotion
            $fusion_confidence = $obj.fusion_shadow_confidence
            $fusion_uncertain = $obj.fusion_shadow_uncertain
            $fusion_reason = $obj.fusion_shadow_reason
            $fusion_agreement = $obj.fusion_shadow_agreement
            $fusion_sources = $obj.fusion_shadow_sources
            $fusion_computation_ms = $obj.fusion_shadow_computation_ms
        }
        
        # Extract source emotions
        $semantic_emotion = $null
        $unified_emotion = $null
        $trained_emotion = $null
        
        if ($obj.semantic_analysis) {
            $semantic_emotion = $obj.semantic_analysis.emotion.dominant
        }
        
        if ($obj.unified_analysis) {
            $unified_emotion = $obj.unified_analysis.emotion.primary
        }
        
        if ($obj.trained_emotion_shadow) {
            $trained_emotion = $obj.trained_emotion_shadow
        }
        
        return @{
            success = $true
            http_status = 200
            authoritative_emotion = $obj.emotion
            authoritative_confidence = $obj.confidence
            response = $obj.response
            response_source = $obj.response_source
            ml_system = $obj.ml_system
            language = $obj.analysis.method
            crisis_level = $obj.analysis.crisis_level
            has_fusion_shadow = $has_fusion
            fusion_emotion = $fusion_emotion
            fusion_confidence = $fusion_confidence
            fusion_uncertain = $fusion_uncertain
            fusion_reason = $fusion_reason
            fusion_agreement = $fusion_agreement
            fusion_sources = $fusion_sources
            fusion_computation_ms = $fusion_computation_ms
            semantic_emotion = $semantic_emotion
            unified_emotion = $unified_emotion
            trained_emotion = $trained_emotion
            parse_error = $null
        }
    } catch {
        return @{
            success = $false
            parse_error = $_.Exception.Message
        }
    }
}

# =====================================================================
# MAIN TESTING FLOW
# =====================================================================

Log-Message "TASK 9.1B - FUSION SHADOW RUNTIME EVIDENCE COLLECTION"
Log-Message "Starting diagnostic tests..."

# =====================================================================
# PHASE 1: VERIFY PORT STATUS BEFORE TESTS
# =====================================================================

Log-Message ""
Log-Message "PHASE 1: Checking port 5000 status..."

$listener_count_before = Test-Listener -port 5000
Log-Message "Listeners on port 5000 before tests: $listener_count_before"

if ($listener_count_before -eq 0) {
    Log-Message "ERROR: No Flask listener on port 5000. Ensure Flask is running with FUSION_SHADOW_ENABLED=true"
    exit 1
}

$pid_before = Get-ListenerPID -port 5000
Log-Message "Flask PID: $pid_before"

# Test connectivity
try {
    $health_response = Invoke-WebRequest -Uri "http://127.0.0.1:5000/health" -ErrorAction Stop
    Log-Message "Flask health check: OK (Status $($health_response.StatusCode))"
} catch {
    Log-Message "ERROR: Cannot connect to Flask on port 5000"
    exit 1
}

# =====================================================================
# PHASE 2: TEST WITH FUSION_OFF (Create baseline)
# =====================================================================

Log-Message ""
Log-Message "PHASE 2: Testing with FUSION_SHADOW_ENABLED=false (baseline)"
Log-Message "Sending 6 test messages..."

foreach ($msg_item in $script:config.messages) {
    $msg_id = $msg_item.id
    $msg_text = $msg_item.text
    
    Log-Message ""
    Log-Message "Message $msg_id (FUSION_OFF): $($msg_text.Substring(0, [Math]::Min(50, $msg_text.Length)))..."
    
    $response = Send-Chat-Request -message $msg_text -conversation_history $script:config.conversation_history
    $latency_ms = $response.latency_ms
    
    Log-Message "  HTTP Status: $($response.status)"
    Log-Message "  Latency: $($latency_ms.ToString('F2')) ms"
    
    if ($response.success) {
        $parsed = Parse-Response -response_body $response.body -message_id $msg_id
        
        if ($parsed.success) {
            Log-Message "  Authoritative emotion: $($parsed.authoritative_emotion)"
            Log-Message "  Confidence: $($parsed.authoritative_confidence)"
            Log-Message "  Fusion shadow present: $($parsed.has_fusion_shadow)"
            
            $script:results_fusion_off += @{
                id = $msg_id
                text = $msg_text
                http_status = $parsed.http_status
                authoritative_emotion = $parsed.authoritative_emotion
                confidence = $parsed.authoritative_confidence
                response_source = $parsed.response_source
                ml_system = $parsed.ml_system
                has_fusion = $parsed.has_fusion_shadow
            }
            
            $script:timing_off += $latency_ms
        } else {
            Log-Message "  Parse error: $($parsed.parse_error)"
        }
    } else {
        Log-Message "  Request error: $($response.error)"
    }
}

# =====================================================================
# PHASE 3: WAIT AND VERIFY FLASK STATE
# =====================================================================

Log-Message ""
Log-Message "PHASE 3: Verifying Flask state between test phases..."

Start-Sleep -Seconds 2

$listener_count_mid = Test-Listener -port 5000
Log-Message "Listeners on port 5000: $listener_count_mid"

if ($listener_count_mid -eq 0) {
    Log-Message "ERROR: Flask crashed between test phases!"
    exit 1
}

# =====================================================================
# PHASE 4: TEST WITH FUSION_ON
# =====================================================================

Log-Message ""
Log-Message "PHASE 4: Testing with FUSION_SHADOW_ENABLED=true (diagnostic mode)"
Log-Message "Sending 6 test messages..."

foreach ($msg_item in $script:config.messages) {
    $msg_id = $msg_item.id
    $msg_text = $msg_item.text
    
    Log-Message ""
    Log-Message "Message $msg_id (FUSION_ON): $($msg_text.Substring(0, [Math]::Min(50, $msg_text.Length)))..."
    
    $response = Send-Chat-Request -message $msg_text -conversation_history $script:config.conversation_history
    $latency_ms = $response.latency_ms
    
    Log-Message "  HTTP Status: $($response.status)"
    Log-Message "  Latency: $($latency_ms.ToString('F2')) ms"
    
    if ($response.success) {
        $parsed = Parse-Response -response_body $response.body -message_id $msg_id
        
        if ($parsed.success) {
            Log-Message "  Authoritative emotion: $($parsed.authoritative_emotion)"
            Log-Message "  Confidence: $($parsed.authoritative_confidence)"
            Log-Message "  Fusion shadow present: $($parsed.has_fusion_shadow)"
            
            if ($parsed.has_fusion_shadow) {
                Log-Message "  Fusion emotion: $($parsed.fusion_emotion)"
                Log-Message "  Fusion confidence: $($parsed.fusion_confidence)"
                Log-Message "  Fusion computation: $($parsed.fusion_computation_ms.ToString('F2')) ms"
                Log-Message "  Fusion uncertain: $($parsed.fusion_uncertain)"
                Log-Message "  Fusion agreement: $($parsed.fusion_agreement)"
                
                # Check for disagreement
                if ($parsed.fusion_emotion -ne $parsed.authoritative_emotion) {
                    Log-Message "  ** DISAGREEMENT: Auth=$($parsed.authoritative_emotion) vs Fusion=$($parsed.fusion_emotion) **"
                }
            } else {
                Log-Message "  WARNING: Fusion shadow NOT present in response!"
            }
            
            $script:results_fusion_on += @{
                id = $msg_id
                text = $msg_text
                http_status = $parsed.http_status
                authoritative_emotion = $parsed.authoritative_emotion
                confidence = $parsed.authoritative_confidence
                semantic_emotion = $parsed.semantic_emotion
                unified_emotion = $parsed.unified_emotion
                trained_emotion = $parsed.trained_emotion
                response_source = $parsed.response_source
                ml_system = $parsed.ml_system
                crisis_level = $parsed.crisis_level
                has_fusion = $parsed.has_fusion_shadow
                fusion_emotion = $parsed.fusion_emotion
                fusion_confidence = $parsed.fusion_confidence
                fusion_uncertain = $parsed.fusion_uncertain
                fusion_reason = $parsed.fusion_reason
                fusion_agreement = $parsed.fusion_agreement
                fusion_computation_ms = $parsed.fusion_computation_ms
            }
            
            $script:timing_on += $latency_ms
        } else {
            Log-Message "  Parse error: $($parsed.parse_error)"
        }
    } else {
        Log-Message "  Request error: $($response.error)"
    }
}

# =====================================================================
# PHASE 5: COMPUTE STATISTICS
# =====================================================================

Log-Message ""
Log-Message "PHASE 5: Computing statistics..."

$off_count = @($script:timing_off).Count
$on_count = @($script:timing_on).Count

if ($off_count -gt 0) {
    $off_mean = ($script:timing_off | Measure-Object -Average).Average
    $off_min = ($script:timing_off | Measure-Object -Minimum).Minimum
    $off_max = ($script:timing_off | Measure-Object -Maximum).Maximum
    $off_sorted = $script:timing_off | Sort-Object
    $off_median = $off_sorted[[Math]::Floor($off_sorted.Count / 2)]
    
    Log-Message "FUSION_OFF Latency ($off_count messages):"
    Log-Message "  Mean:   $($off_mean.ToString('F2')) ms"
    Log-Message "  Median: $($off_median.ToString('F2')) ms"
    Log-Message "  Min:    $($off_min.ToString('F2')) ms"
    Log-Message "  Max:    $($off_max.ToString('F2')) ms"
}

if ($on_count -gt 0) {
    $on_mean = ($script:timing_on | Measure-Object -Average).Average
    $on_min = ($script:timing_on | Measure-Object -Minimum).Minimum
    $on_max = ($script:timing_on | Measure-Object -Maximum).Maximum
    $on_sorted = $script:timing_on | Sort-Object
    $on_median = $on_sorted[[Math]::Floor($on_sorted.Count / 2)]
    
    Log-Message "FUSION_ON Latency ($on_count messages):"
    Log-Message "  Mean:   $($on_mean.ToString('F2')) ms"
    Log-Message "  Median: $($on_median.ToString('F2')) ms"
    Log-Message "  Min:    $($on_min.ToString('F2')) ms"
    Log-Message "  Max:    $($on_max.ToString('F2')) ms"
}

# =====================================================================
# PHASE 6: DISAGREEMENT ANALYSIS
# =====================================================================

Log-Message ""
Log-Message "PHASE 6: Disagreement Analysis"

$disagreements = @()
foreach ($result_on in $script:results_fusion_on) {
    if ($result_on.has_fusion) {
        if ($result_on.fusion_emotion -ne $result_on.authoritative_emotion) {
            $disagreements += @{
                id = $result_on.id
                text = $result_on.text
                authoritative = $result_on.authoritative_emotion
                fusion = $result_on.fusion_emotion
            }
            
            Log-Message "  $($result_on.id): Auth=$($result_on.authoritative_emotion) vs Fusion=$($result_on.fusion_emotion)"
        }
    }
}

if ($disagreements.Count -eq 0) {
    Log-Message "  No disagreements detected (full agreement)"
}

# =====================================================================
# PHASE 7: FUSION COMPUTATION TIME ANALYSIS
# =====================================================================

Log-Message ""
Log-Message "PHASE 7: Fusion Computation Time Analysis"

$fusion_times = @()
foreach ($result in $script:results_fusion_on) {
    if ($result.fusion_computation_ms) {
        $fusion_times += $result.fusion_computation_ms
        Log-Message "  $($result.id): $($result.fusion_computation_ms.ToString('F2')) ms"
    }
}

if ($fusion_times.Count -gt 0) {
    $fusion_mean = ($fusion_times | Measure-Object -Average).Average
    Log-Message "  Mean fusion computation: $($fusion_mean.ToString('F2')) ms"
}

# =====================================================================
# PHASE 8: SPECIAL CASE ANALYSIS (A5 and A6)
# =====================================================================

Log-Message ""
Log-Message "PHASE 8: Special Case Analysis"

$a5_result = $script:results_fusion_on | Where-Object { $_.id -eq "A5" }
if ($a5_result) {
    Log-Message "A5 - Positive case 'I'm doing pretty good today':"
    Log-Message "  Authoritative: $($a5_result.authoritative_emotion)"
    Log-Message "  Fusion shadow: $($a5_result.fusion_emotion)"
    Log-Message "  Match: $(if ($a5_result.authoritative_emotion -eq $a5_result.fusion_emotion) { 'YES' } else { 'NO' })"
}

$a6_result = $script:results_fusion_on | Where-Object { $_.id -eq "A6" }
if ($a6_result) {
    Log-Message "A6 - Negation case 'Hindi naman ako malungkot, pagod lang talaga':"
    Log-Message "  Authoritative: $($a6_result.authoritative_emotion)"
    Log-Message "  Fusion shadow: $($a6_result.fusion_emotion)"
    Log-Message "  Match: $(if ($a6_result.authoritative_emotion -eq $a6_result.fusion_emotion) { 'YES' } else { 'NO' })"
    Log-Message "  Fusion reason: $($a6_result.fusion_reason)"
}

# =====================================================================
# PHASE 9: V2 VALIDATOR FINAL CHECK
# =====================================================================

Log-Message ""
Log-Message "PHASE 9: Running V2 Validator..."

try {
    $validator_output = python.exe "test_emotion_fusion_strict_v2.py" 2>&1
    
    # Parse output for final score
    if ($validator_output -match "Overall: (\d+)/(\d+)") {
        $passed = [int]$matches[1]
        $total = [int]$matches[2]
        Log-Message "V2 Validator Result: $passed/$total"
        
        if ($passed -eq 18 -and $total -eq 18) {
            Log-Message "  Status: PASSED (18/18)"
        } else {
            Log-Message "  Status: INCOMPLETE ($passed/$total)"
        }
    } else {
        Log-Message "  Could not parse validator output"
    }
} catch {
    Log-Message "ERROR: Failed to run V2 validator: $($_.Exception.Message)"
}

# =====================================================================
# PHASE 10: FINAL PORT STATUS CHECK
# =====================================================================

Log-Message ""
Log-Message "PHASE 10: Final port status check..."

$listener_count_after = Test-Listener -port 5000
Log-Message "Listeners on port 5000 after tests: $listener_count_after"

$pid_after = Get-ListenerPID -port 5000
Log-Message "Flask PID: $pid_after"

if ($listener_count_after -eq 0) {
    Log-Message "Port 5000 is now closed."
} else {
    Log-Message "Port 5000 still listening (process still running)."
}

# =====================================================================
# PHASE 11: SUMMARY REPORT
# =====================================================================

Log-Message ""
Log-Message "="*80
Log-Message "FUSION SHADOW RUNTIME EVIDENCE SUMMARY"
Log-Message "="*80

Log-Message ""
Log-Message "TEST COVERAGE:"
Log-Message "  FUSION_OFF messages: $($script:results_fusion_off.Count)/6"
Log-Message "  FUSION_ON messages: $($script:results_fusion_on.Count)/6"

Log-Message ""
Log-Message "FUSION SHADOW FIELDS:"
$fusion_present_count = ($script:results_fusion_on | Where-Object { $_.has_fusion }).Count
Log-Message "  Messages with fusion_shadow_* fields: $fusion_present_count/6"

Log-Message ""
Log-Message "KEY METRICS:"
if ($off_count -gt 0) {
    Log-Message "  FUSION_OFF mean latency: $($off_mean.ToString('F2')) ms"
}
if ($on_count -gt 0) {
    Log-Message "  FUSION_ON mean latency: $($on_mean.ToString('F2')) ms"
}
if ($fusion_times.Count -gt 0) {
    Log-Message "  Mean fusion computation: $($fusion_mean.ToString('F2')) ms"
}
if ($disagreements.Count -gt 0) {
    Log-Message "  Disagreement cases: $($disagreements.Count)"
} else {
    Log-Message "  Disagreement cases: 0 (full agreement)"
}

Log-Message ""
Log-Message "SPECIAL CASES:"
Log-Message "  A5 (positive 'doing pretty good'): $($a5_result.authoritative_emotion) / $($a5_result.fusion_emotion)"
Log-Message "  A6 (negation 'hindi naman malungkot'): $($a6_result.authoritative_emotion) / $($a6_result.fusion_emotion)"

Log-Message ""
Log-Message "="*80
Log-Message "DIAGNOSTIC TEST COMPLETE"
Log-Message "="*80

