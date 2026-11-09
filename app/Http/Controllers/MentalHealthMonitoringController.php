<?php

namespace App\Http\Controllers;

use App\Services\MentalHealthMonitoringService;
use App\Models\CounselorAlert;
use Illuminate\Http\Request;

class MentalHealthMonitoringController extends Controller
{
    protected $monitoringService;
    
    public function __construct(MentalHealthMonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }
    
    /**
     * Show student's mental status dashboard
     */
    public function studentDashboard()
    {
        $mentalStatus = $this->monitoringService->getMentalStatus(auth()->id());
        $usagePatterns = $this->monitoringService->getUsagePatterns(auth()->id(), 7);
        $trends = $this->monitoringService->getMentalHealthTrend(auth()->id(), 4);
        
        return view('student.mental-health-dashboard', [
            'mentalStatus' => $mentalStatus,
            'usagePatterns' => $usagePatterns,
            'trends' => $trends
        ]);
    }
    
    /**
     * Show counselor's monitoring dashboard
     */
    public function counselorDashboard()
    {
        $counselorId = (int) auth()->id();
        $alerts = CounselorAlert::where('status', 'pending')
            ->where(function ($query) use ($counselorId) {
                $query->whereNull('counselor_id')
                    ->orWhere('counselor_id', $counselorId);
            })
            ->whereHas('student.privacySetting', fn ($query) =>
                $query->withCurrentConsent('share_with_counselor'))
            ->whereHas('student.appointments', fn ($query) => $query
                ->where('counselor_id', $counselorId)
                ->where('status', 'confirmed')
                ->whereNull('session_outcome'))
            ->with('student')
            ->orderByRaw("CASE severity WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'moderate' THEN 2 WHEN 'low' THEN 1 ELSE 0 END DESC")
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20);
        
        return view('counselor.mental-health-monitoring', [
            'alerts' => $alerts
        ]);
    }
    
    /**
     * Get mental status API
     */
    public function getMentalStatus()
    {
        $mentalStatus = $this->monitoringService->getMentalStatus(auth()->id());
        
        return response()->json($mentalStatus);
    }
    
    /**
     * Acknowledge alert
     */
    public function acknowledgeAlert(Request $request, $alertId)
    {
        $this->findVisibleAlertOrFail((int) $alertId);
        $this->monitoringService->acknowledgeAlert($alertId, auth()->id());
        
        return response()->json(['success' => true, 'message' => 'Alert acknowledged']);
    }
    
    /**
     * Resolve alert
     */
    public function resolveAlert(Request $request, $alertId)
    {
        $this->findVisibleAlertOrFail((int) $alertId);
        $this->monitoringService->resolveAlert($alertId);
        
        return response()->json(['success' => true, 'message' => 'Alert resolved']);
    }

    private function findVisibleAlertOrFail(int $alertId): CounselorAlert
    {
        $counselorId = (int) auth()->id();

        return CounselorAlert::whereKey($alertId)
            ->where(function ($query) use ($counselorId) {
                $query->whereNull('counselor_id')
                    ->orWhere('counselor_id', $counselorId);
            })
            ->whereHas('student.privacySetting', fn ($query) =>
                $query->withCurrentConsent('share_with_counselor'))
            ->whereHas('student.appointments', fn ($query) => $query
                ->where('counselor_id', $counselorId)
                ->where('status', 'confirmed')
                ->whereNull('session_outcome'))
            ->firstOrFail();
    }
}
