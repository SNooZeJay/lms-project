@extends('layouts.app')

@section('title', 'Activity log')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="font-mono text-sm font-semibold text-primary-text">Administration</p>
                <h1 class="mt-3 text-3xl font-[650] tracking-tight text-ink">Activity log</h1>
                <p class="mt-3 max-w-2xl leading-7 text-ink-muted">Read-only records of approved role and account-status changes.</p>
            </div>
            <a href="{{ route('admin.users.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line bg-surface px-5 py-3 text-sm font-semibold text-ink transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-focus">Manage users</a>
        </div>

        @if ($activityLogs->isEmpty())
            <div class="mt-10 border-t border-line py-12 text-center">
                <h2 class="text-lg font-semibold text-ink">No activity records</h2>
                <p class="mt-2 text-sm text-ink-muted">Role and account-status changes will appear here.</p>
            </div>
        @else
            <div class="mt-8 overflow-x-auto border-t border-line">
                <table class="min-w-full divide-y divide-line text-left text-sm">
                    <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold">Event</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Actor</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Target</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Change</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-surface">
                        @foreach ($activityLogs as $activityLog)
                            <tr>
                                <td class="px-4 py-5 align-top font-medium text-ink">{{ $activityLog->event_type->value === 'role_changed' ? 'Role changed' : 'Account status changed' }}</td>
                                <td class="px-4 py-5 align-top text-ink">{{ $activityLog->actor?->name }}</td>
                                <td class="px-4 py-5 align-top text-ink">{{ $activityLog->targetUser?->name }}</td>
                                <td class="px-4 py-5 align-top text-ink-muted">
                                    @if ($activityLog->event_type->value === 'role_changed')
                                        {{ ucfirst($activityLog->previous_role->value) }} → {{ ucfirst($activityLog->new_role->value) }}
                                    @else
                                        {{ ucfirst($activityLog->previous_status->value) }} → {{ ucfirst($activityLog->new_status->value) }}
                                    @endif
                                </td>
                                <td class="px-4 py-5 align-top text-ink-muted">{{ $activityLog->created_at->format('M j, Y g:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $activityLogs->links() }}</div>
        @endif
    </div>
@endsection
