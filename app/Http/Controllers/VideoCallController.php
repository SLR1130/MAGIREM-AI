<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\AppointmentLifecycleService;

class VideoCallController extends Controller
{
    public function create(Request $request)
    {
        $user = $request->user();

        abort_unless($user && (string) $user->role === 'counselor', 403);

        $data = $request->validate([
            'callee_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $calleeId = (int) $data['callee_id'];

        abort_if($calleeId === (int) $user->id, 422, 'You cannot call yourself.');

        $appointment = app(AppointmentLifecycleService::class)
            ->findCallableAppointment((int) $user->id, $calleeId);

        abort_unless($appointment, 403, 'A confirmed appointment is required during its scheduled call window.');

        DB::table('video_calls')
            ->where('caller_id', $user->id)
            ->where('callee_id', $calleeId)
            ->whereIn('status', ['ringing', 'accepted'])
            ->update([
                'status' => 'ended',
                'ended_at' => now(),
                'updated_at' => now(),
            ]);

        $callId = DB::table('video_calls')->insertGetId([
            'appointment_id' => $appointment->id,
            'caller_id' => $user->id,
            'callee_id' => $calleeId,
            'status' => 'ringing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'call' => $this->serializeCall($callId, $user->id),
        ], 201);
    }

    public function incoming(Request $request)
    {
        $user = $request->user();

        $call = DB::table('video_calls')
            ->where('callee_id', $user->id)
            ->where('status', 'ringing')
            ->where('created_at', '>=', now()->subMinutes(2))
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'success' => true,
            'call' => $call ? $this->serializeCall($call->id, $user->id) : null,
        ]);
    }

    public function show(Request $request, int $callId)
    {
        $this->authorizeParticipant($request, $callId);

        return response()->json([
            'success' => true,
            'call' => $this->serializeCall($callId, $request->user()->id),
        ]);
    }

    public function accept(Request $request, int $callId)
    {
        $call = $this->authorizeParticipant($request, $callId);

        abort_unless((int) $call->callee_id === (int) $request->user()->id, 403);
        abort_unless($call->status === 'ringing', 409, 'This call is no longer ringing.');

        DB::table('video_calls')
            ->where('id', $callId)
            ->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'call' => $this->serializeCall($callId, $request->user()->id),
        ]);
    }

    public function decline(Request $request, int $callId)
    {
        $call = $this->authorizeParticipant($request, $callId);

        abort_unless((int) $call->callee_id === (int) $request->user()->id, 403);

        DB::table('video_calls')
            ->where('id', $callId)
            ->whereIn('status', ['ringing', 'accepted'])
            ->update([
                'status' => 'declined',
                'ended_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }

    public function end(Request $request, int $callId)
    {
        $this->authorizeParticipant($request, $callId);

        DB::table('video_calls')
            ->where('id', $callId)
            ->whereIn('status', ['ringing', 'accepted'])
            ->update([
                'status' => 'ended',
                'ended_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }

    public function signal(Request $request, int $callId)
    {
        $this->authorizeParticipant($request, $callId);

        $data = $request->validate([
            'type' => ['required', 'in:offer,answer,ice'],
            'payload' => ['required'],
        ]);

        DB::table('video_call_signals')->insert([
            'video_call_id' => $callId,
            'user_id' => $request->user()->id,
            'type' => $data['type'],
            'payload' => json_encode($data['payload'], JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true], 201);
    }

    public function signals(Request $request, int $callId)
    {
        $this->authorizeParticipant($request, $callId);

        $after = max(0, (int) $request->query('after', 0));

        $signals = DB::table('video_call_signals')
            ->where('video_call_id', $callId)
            ->where('id', '>', $after)
            ->where('user_id', '!=', $request->user()->id)
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(function ($signal) {
                return [
                    'id' => (int) $signal->id,
                    'user_id' => (int) $signal->user_id,
                    'type' => $signal->type,
                    'payload' => json_decode($signal->payload, true),
                ];
            });

        return response()->json([
            'success' => true,
            'signals' => $signals,
        ]);
    }

    private function authorizeParticipant(Request $request, int $callId)
    {
        $call = DB::table('video_calls')->where('id', $callId)->first();

        abort_unless($call, 404);
        abort_unless(
            (int) $call->caller_id === (int) $request->user()->id ||
            (int) $call->callee_id === (int) $request->user()->id,
            403
        );

        // Legacy calls without an appointment link remain readable for historical
        // integrity, but every new linked call must still have an unresolved
        // confirmed appointment and valid participants.
        if ($call->appointment_id !== null) {
            $appointment = DB::table('appointments')
                ->where('id', $call->appointment_id)
                ->where('status', 'confirmed')
                ->whereNull('session_outcome')
                ->first();
            abort_unless($appointment, 403, 'This appointment no longer permits video calling.');
            abort_unless(
                ((int) $appointment->counselor_id === (int) $call->caller_id && (int) $appointment->student_id === (int) $call->callee_id) ||
                ((int) $appointment->counselor_id === (int) $call->callee_id && (int) $appointment->student_id === (int) $call->caller_id),
                403
            );
        }

        return $call;
    }

    private function serializeCall(int $callId, int $viewerId): array
    {
        $call = DB::table('video_calls')->where('id', $callId)->first();

        abort_unless($call, 404);

        $peerId = (int) $call->caller_id === (int) $viewerId
            ? (int) $call->callee_id
            : (int) $call->caller_id;

        return [
            'id' => (int) $call->id,
            'appointment_id' => $call->appointment_id !== null ? (int) $call->appointment_id : null,
            'caller_id' => (int) $call->caller_id,
            'callee_id' => (int) $call->callee_id,
            'peer_id' => $peerId,
            'status' => $call->status,
            'accepted_at' => $call->accepted_at,
            'ended_at' => $call->ended_at,
        ];
    }
}
