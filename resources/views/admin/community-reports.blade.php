@extends('layouts.app')

@section('title', 'Community Reports - MAGIREM AI')

@section('content')
<main class="max-w-5xl mx-auto p-6">
    <h1 class="text-2xl font-semibold">Community reports</h1>
    <p class="mt-2 text-sm text-gray-600">Reports are confidential. Review only the minimum information needed to protect students.</p>

    @if(session('success'))<div class="mt-4 rounded bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>@endif

    <div class="mt-6 space-y-4">
        @forelse($reports as $report)
            <article class="rounded border bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm font-medium">{{ ucfirst($report->reportable_type) }} #{{ $report->reportable_id }}</span>
                    <span class="text-xs uppercase tracking-wide text-gray-500">{{ $report->status }}</span>
                </div>
                <p class="mt-2 text-sm">Reason: {{ config('community.report_reasons.'.$report->reason, $report->reason) }}</p>
                <p class="mt-1 text-xs text-gray-500">Submitted {{ $report->created_at->diffForHumans() }} · reporter identity is restricted to moderators.</p>
                <form method="POST" action="{{ route('admin.community-reports.review', $report->id) }}" class="mt-3 flex flex-wrap gap-2">
                    @csrf
                    <select name="status" aria-label="Report status" class="rounded border px-2 py-1 text-sm">
                        @foreach(['reviewed','actioned','dismissed'] as $status)
                            <option value="{{ $status }}" @selected($report->status === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <input name="moderator_notes" maxlength="1000" value="{{ old('moderator_notes', $report->moderator_notes) }}" placeholder="Private moderator note" class="min-w-[220px] flex-1 rounded border px-2 py-1 text-sm">
                    <button class="rounded bg-slate-800 px-3 py-1 text-sm text-white" type="submit">Save review</button>
                </form>
            </article>
        @empty
            <p class="rounded border bg-white p-6 text-sm text-gray-600">No community reports.</p>
        @endforelse
    </div>

    <div class="mt-5">{{ $reports->links() }}</div>
</main>
@endsection
