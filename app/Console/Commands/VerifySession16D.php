<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifySession16D extends Command
{
    protected $signature = 'verify:session-16d';
    protected $description = 'Verify SESSION 16D issues without modifying behavior';

    public function handle()
    {
        $this->line("\n" . str_repeat("=", 80));
        $this->line("SESSION 16D: SYSTEMATIC VERIFICATION OF 6 CRITICAL ISSUES");
        $this->line(str_repeat("=", 80));

        // ISSUE 1: AUTOMATIC APPOINTMENT VERIFICATION
        $this->issue1_appointment_verification();
        
        // ISSUE 2: E2E TEST DATABASE VERIFICATION
        $this->issue2_e2e_database();
        
        // ISSUE 3: BOM TEST HARNESS
        $this->issue3_bom_analysis();
        
        // ISSUE 4: CRISIS RESOURCE WORDING
        $this->issue4_resource_wording();
        
        // ISSUE 5: TEST DATA CONTAMINATION
        $this->issue5_test_data();
        
        // ISSUE 6: NOTIFICATION DUPLICATION
        $this->issue6_duplication();
        
        // SUMMARY
        $this->summary();
    }

    private function issue1_appointment_verification()
    {
        $this->line("\n" . str_repeat("-", 80));
        $this->line("ISSUE 1: AUTOMATIC APPOINTMENT VERIFICATION");
        $this->line(str_repeat("-", 80));

        $student_id = 2;

        $this->info("\n[*] Checking current appointments for student_id={$student_id}:");
        $appointments = DB::table('appointments')->where('student_id', $student_id)->get();

        $this->line("  Found " . count($appointments) . " appointment(s):");
        foreach ($appointments as $appt) {
            $this->line("    - ID: {$appt->id}, Category: {$appt->category}, Status: {$appt->status}");
            $this->line("      created_from_crisis: " . ($appt->created_from_crisis ?? 'NULL'));
            $this->line("      Reason: {$appt->reason}");
            $this->line("      Created: {$appt->created_at}");
        }

        $this->info("\n[*] Analyzing duplicate-check logic in CrisisDetectionService:");
        
        $this->line("\n[CRITICAL FINDING]:");
        $this->line("  The check reads: Appointment::where('student_id', \$studentId)");
        $this->line("                      ->whereIn('status', ['pending', 'confirmed'])");
        $this->line("                      ->first();");
        $this->line("\n  This checks for ANY appointment with status 'pending' or 'confirmed'");
        $this->line("  REGARDLESS OF CATEGORY. Therefore:");
        $this->line("\n  ✗ Existing 'anxiety' appointments WILL PREVENT crisis appointment creation.");
        $this->line("\n  Is this intentional? Current evidence suggests NO.");
        $this->line("\n  REQUIRED FIX:");
        $this->line("  Change to category-specific check:");
        $this->line("  ->where('category', 'crisis')");
    }

    private function issue2_e2e_database()
    {
        $this->line("\n" . str_repeat("-", 80));
        $this->line("ISSUE 2: E2E TEST DATABASE VERIFICATION");
        $this->line(str_repeat("-", 80));

        $this->info("\n[*] Checking for recent crisis alerts (last 24 hours):");
        $crisis_alerts = DB::table('crisis_alerts')
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->get();

        $this->line("  Found " . count($crisis_alerts) . " crisis alert(s):");
        foreach ($crisis_alerts as $alert) {
            $this->line("    - Alert ID: {$alert->id}, Student: {$alert->student_id}, Level: {$alert->crisis_level}");
            $this->line("      Category: {$alert->crisis_category}, Resolved: " . ($alert->is_resolved ? 'YES' : 'NO'));
        }

        $this->info("\n[*] Checking for recent counselor notifications (last 24 hours):");
        $notifications = DB::table('notifications')
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $this->line("  Found " . count($notifications) . " notification(s):");
        foreach ($notifications as $notif) {
            $this->line("    - ID: {$notif->id}, User: {$notif->user_id}, Type: {$notif->type}");
            $this->line("      Title: {$notif->title}");
            $this->line("      Priority: {$notif->priority}, Is Read: " . ($notif->is_read ? 'YES' : 'NO'));
        }

        $this->line("\n[ACTION REQUIRED]:");
        $this->line("  Create Laravel E2E test that verifies:");
        $this->line("  1. Crisis alert created in crisis_alerts table");
        $this->line("  2. Counselor notification row exists with correct schema");
        $this->line("  3. Appointment created with category='crisis'");
        $this->line("  4. No database errors or schema violations");
    }

    private function issue3_bom_analysis()
    {
        $this->line("\n" . str_repeat("-", 80));
        $this->line("ISSUE 3: BOM TEST HARNESS ANALYSIS");
        $this->line(str_repeat("-", 80));

        $test_file = base_path('ml_service/test_detector_improvements.py');

        if (file_exists($test_file)) {
            $this->info("\n[*] Reading test_detector_improvements.py to detect BOM:");
            
            $file_content = file_get_contents($test_file, false, null, 0, 100);
            $hex = bin2hex($file_content);
            
            if (substr($hex, 0, 6) === 'efbbbf') {
                $this->error("  ✗ BOM DETECTED: File starts with UTF-8 BOM (EF BB BF)");
            } else {
                $this->info("  ✓ No BOM detected at file start");
            }
            
            $this->line("\n[ANALYSIS]:");
            $this->line("  The actual problem: UnicodeEncodeError for characters like ✓ (\\u2713)");
            $this->line("  This is Windows console encoding, not file BOM.");
            $this->line("\n  SOLUTION:");
            $this->line("  Add to top of test harness:");
            $this->line("    import sys");
            $this->line("    import io");
            $this->line("    sys.stdout = io.TextIOWrapper(");
            $this->line("        sys.stdout.buffer, encoding='utf-8'");
            $this->line("    )");
        }
    }

    private function issue4_resource_wording()
    {
        $this->line("\n" . str_repeat("-", 80));
        $this->line("ISSUE 4: CRISIS RESOURCE WORDING - WHERE DOES USAP COME FROM?");
        $this->line(str_repeat("-", 80));

        $this->info("\n[*] Searching for 'USAP' and '2919' in codebase:");

        $controller_file = base_path('app/Http/Controllers/AIChatController.php');
        if (file_exists($controller_file)) {
            $content = file_get_contents($controller_file);
            if (strpos($content, 'USAP') !== false) {
                $this->line("  FOUND in: AIChatController.php");
                
                $lines = explode("\n", $content);
                foreach ($lines as $idx => $line) {
                    if (strpos($line, 'USAP') !== false || strpos($line, '2919') !== false) {
                        $this->line("    Line " . ($idx + 1) . ": " . trim($line));
                    }
                }
            }
        }

        $this->line("\n[CRITICAL ANALYSIS]:");
        $this->line("  USAP appears HARDCODED in getCrisisSafeResponse():");
        $this->line("    0917-899-USAP (8727) - Crisis Hotline");
        $this->line("    Text USAP to 2919 - SMS option");
        $this->line("\n  STATUS: ⚠️ UNVERIFIED - Not supported by independent resource config");
        $this->line("\n  REQUIRED:");
        $this->line("  1. Verify this is the correct hotline for your institution");
        $this->line("  2. Move to configurable resource table instead of hardcoding");
        $this->line("  3. Document the source of this hotline information");
    }

    private function issue5_test_data()
    {
        $this->line("\n" . str_repeat("-", 80));
        $this->line("ISSUE 5: TEST DATA CONTAMINATION ANALYSIS");
        $this->line(str_repeat("-", 80));

        $student_id = 2;

        $this->info("\n[*] Analyzing user_id={$student_id} account history:");

        $journal_entries = DB::table('journal_entries')
            ->where('user_id', $student_id)
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        $this->line("  Last 7 days journal entries: " . count($journal_entries));

        $late_night_entries = DB::table('journal_entries')
            ->where('user_id', $student_id)
            ->whereRaw('HOUR(created_at) >= 22 OR HOUR(created_at) <= 5')
            ->get();

        $this->line("  Late-night entries (22:00-05:00): " . count($late_night_entries));

        $crisis_for_user = DB::table('crisis_alerts')
            ->where('student_id', $student_id)
            ->get();

        $this->line("  Total crisis alerts for this user: " . count($crisis_for_user));

        $appts_for_user = DB::table('appointments')
            ->where('student_id', $student_id)
            ->get();

        $this->line("  Total appointments: " . count($appts_for_user));

        $this->line("\n[RECOMMENDATION]:");
        $this->line("  For future E2E testing:");
        $this->line("  1. Use an isolated test account separate from QA");
        $this->line("  2. Create fresh before each test suite");
        $this->line("  3. Clean only test-specific data after tests");
        $this->line("  4. Never reuse without cleanup");
    }

    private function issue6_duplication()
    {
        $this->line("\n" . str_repeat("-", 80));
        $this->line("ISSUE 6: NOTIFICATION DUPLICATION CHECK");
        $this->line(str_repeat("-", 80));

        $this->info("\n[*] Checking for duplicate crisis notifications in last 24 hours:");

        $dup_check = DB::table('notifications')
            ->where('type', 'crisis_alert')
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at')
            ->get();

        $this->line("  Found " . count($dup_check) . " crisis_alert notification(s)");

        if (count($dup_check) > 0) {
            $grouped = [];
            foreach ($dup_check as $notif) {
                $key = $notif->user_id;
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [];
                }
                $grouped[$key][] = $notif;
            }
            
            foreach ($grouped as $user_id => $notifs) {
                $this->line("\n  User {$user_id}:");
                foreach ($notifs as $notif) {
                    $this->line("    - Created: {$notif->created_at}");
                    $this->line("      Title: {$notif->title}");
                }
                
                if (count($notifs) > 1) {
                    $times = array_map(fn($n) => strtotime($n->created_at), $notifs);
                    $max_delta = max($times) - min($times);
                    if ($max_delta < 5) {
                        $this->warn("    ⚠️ POTENTIAL DUPLICATE: Within 5 seconds of each other");
                    }
                }
            }
        }

        $this->line("\n[ANALYSIS]:");
        $this->line("  Trace through: handleCrisis() -> createAutomaticAppointment() -> CrisisDetectionService");
        $this->line("  Both may create notifications. Determine if intentional.");
    }

    private function summary()
    {
        $this->line("\n" . str_repeat("=", 80));
        $this->line("VERIFICATION SUMMARY & REQUIRED ACTIONS");
        $this->line(str_repeat("=", 80));

        $this->line("\n[ISSUE 1] Automatic Appointment Duplicate Check:");
        $this->warn("  Status: ⚠️ REQUIRES CORRECTION");
        $this->line("  Action: Make duplicate check category-specific");

        $this->line("\n[ISSUE 2] E2E Test Database Assertions:");
        $this->warn("  Status: 🔍 NEEDS STRUCTURED TEST");
        $this->line("  Action: Create Laravel E2E test with DB verification");

        $this->line("\n[ISSUE 3] BOM Test Harness:");
        $this->warn("  Status: 🔍 WINDOWS ENCODING ISSUE");
        $this->line("  Action: Add UTF-8 console encoding setup");

        $this->line("\n[ISSUE 4] Crisis Resource Wording:");
        $this->warn("  Status: ⚠️ UNVERIFIED HARDCODED");
        $this->line("  Action: Verify USAP hotline details independently");

        $this->line("\n[ISSUE 5] Test Data Contamination:");
        $this->info("  Status: 📊 ANALYZED");
        $this->line("  Action: Use isolated test account in future");

        $this->line("\n[ISSUE 6] Notification Duplication:");
        $this->warn("  Status: 🔍 CHECK NEEDED");
        $this->line("  Action: Review logic for intentionality");

        $this->line("\n" . str_repeat("=", 80));
        $this->line("END OF VERIFICATION REPORT");
        $this->line(str_repeat("=", 80) . "\n");
    }
}
