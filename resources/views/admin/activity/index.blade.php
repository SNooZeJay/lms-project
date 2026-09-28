@extends('layouts.app-shell')

@section('title', 'Activity log')
@section('workspace-context', 'Operations workspace')

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <x-page-header
            eyebrow="Administration"
            title="Activity log"
            description="Read-only records of approved role and account status changes. Each row shows who acted, who was changed, what moved, and when. Nothing else is recorded."
        >
            <x-slot:actions>
                <x-btn :href="route('admin.users.index')" variant="secondary" size="md">
                    <x-icon name="users" size="sm" />
                    Manage users
                </x-btn>
            </x-slot:actions>
        </x-page-header>

        @if ($activityLogs->isEmpty())
            <div class="card mt-8">
                <x-empty-state
                    icon="activity"
                    title="No activity records"
                    description="A record appears here whenever an Administrator assigns a role or changes an account status."
                >
                    <x-btn :href="route('admin.users.index')" variant="primary" size="md">
                        Manage users
                    </x-btn>
                </x-empty-state>
            </div>
        @else
            {{-- The table scrolls inside its own container, so a wide report can
                 never widen the page on a phone. --}}
            <div class="card mt-8 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-3xl text-left text-sm">
                        <caption class="sr-only">
                            One row per role or account status change, with the actor, the target, the previous and
                            new value, and the time
                        </caption>
                        <thead class="table-head">
                            <tr>
                                <th scope="col">Event</th>
                                <th scope="col">Actor</th>
                                <th scope="col">Target</th>
                                <th scope="col">Change</th>
                                <th scope="col">Time</th>
                            </tr>
                        </thead>
                        <tbody role="list" class="divide-y divide-line">
                            @foreach ($activityLogs as $activityLog)
                                <tr>
                                    <td class="table-cell font-semibold">
                                        <x-status
                                            :value="$activityLog->event_type->value"
                                            :label="$activityLog->event_type->value === 'role_changed' ? 'Role changed' : 'Account status changed'"
                                            tone="info"
                                        />
                                    </td>
                                    <td class="table-cell">{{ $activityLog->actor?->name ?? 'System' }}</td>
                                    <td class="table-cell">{{ $activityLog->targetUser?->name ?? 'Unknown' }}</td>
                                    <td class="table-cell text-ink-muted">
                                        @if ($activityLog->event_type->value === 'role_changed')
                                            <span class="font-medium text-ink">{{ \App\Support\StatusLabel::words($activityLog->previous_role?->value) }}</span>
                                            <span aria-hidden="true">→</span>
                                            <span class="font-medium text-ink">{{ \App\Support\StatusLabel::words($activityLog->new_role?->value) }}</span>
                                        @else
                                            <span class="font-medium text-ink">{{ \App\Support\StatusLabel::words($activityLog->previous_status?->value) }}</span>
                                            <span aria-hidden="true">→</span>
                                            <span class="font-medium text-ink">{{ \App\Support\StatusLabel::words($activityLog->new_status?->value) }}</span>
                                        @endif
                                    </td>
                                    <td class="table-cell whitespace-nowrap text-ink-muted">
                                        {{ $activityLog->created_at->format('M j, Y g:i A') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">{{ $activityLogs->links() }}</div>
        @endif
    </div>
@endsection
