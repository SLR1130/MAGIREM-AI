[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$StatePath
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
if (-not (Test-Path -LiteralPath $StatePath)) { throw "State file not found: $StatePath" }
$state = Get-Content -LiteralPath $StatePath -Raw | ConvertFrom-Json
$rootIds = @($state.Processes | ForEach-Object { [int]$_.Id })
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
} catch { throw 'Unable to enumerate process ancestry; refusing an unsafe broad stop.' }
$minimumStart = [DateTime]::MaxValue; foreach ($item in @($state.Processes)) { try { $candidate = ([DateTime]$item.StartTimeUtc).ToUniversalTime().AddSeconds(-2); if ($candidate -lt $minimumStart) { $minimumStart = $candidate } } catch { } }
foreach ($id in @($allIds | Sort-Object -Descending)) {
    try {
        $p = Get-Process -Id $id -ErrorAction Stop
        $identity = @($processTable | Where-Object { [int]$_.ProcessId -eq $id })
        $commandAllowed = $identity.Count -gt 0 -and (($identity[0].Name -match '^(python|php|cmd)\.exe$') -and ($identity[0].CommandLine -match 'phase4a_secure_api\.py|artisan serve|resources/server\.php'))
        if ($commandAllowed -and $p.StartTime.ToUniversalTime() -ge $minimumStart) { Stop-Process -Id $p.Id -Force; Write-Output "Stopped PID $($p.Id)." }
        elseif (-not $commandAllowed) { Write-Warning "Refused PID $($p.Id): command identity did not match this launcher." }
        else { Write-Warning "Refused PID $($p.Id): it predates this launcher state." }
    } catch [System.Management.Automation.ItemNotFoundException] { }
    catch { Write-Warning "Could not stop PID ${id}: $($_.Exception.Message)" }
}
Write-Output "Processes stopped. Disposable database '$($state.DisposableDatabase)' and runtime files were retained for review; no historical database was touched."
