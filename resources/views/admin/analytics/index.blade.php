@php
    $pct = fn (?float $value) => $value === null ? 'N/A' : rtrim(rtrim(number_format($value, 1), '0'), '.').'%';
    $num = fn (?float $value, int $decimals = 1) => $value === null ? 'N/A' : number_format($value, $decimals);
@endphp

<x-admin-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">Analytics</h2>
        <div class="text-secondary small">Platform-wide performance across every case</div>
    </x-slot>

    <div class="container-fluid py-4">
        {{-- Top-line KPI cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-secondary small text-uppercase">Completion Rate</div>
                        <div class="fs-2 fw-semibold">{{ $pct($summary['completion']['completion_rate_percent']) }}</div>
                        <div class="text-secondary small mt-2">{{ $summary['completion']['completed'] }} of {{ $summary['completion']['started'] }} attempts completed</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-secondary small text-uppercase">Average Score</div>
                        <div class="fs-2 fw-semibold">{{ $pct($summary['score_distribution']['average_percent']) }}</div>
                        <div class="text-secondary small mt-2">across {{ $summary['score_distribution']['evaluated_count'] }} evaluated attempts</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-secondary small text-uppercase">Avg Completion Time</div>
                        <div class="fs-2 fw-semibold">{{ $num($summary['completion_time']['average_minutes']) }}<span class="fs-6 text-secondary">{{ $summary['completion_time']['average_minutes'] === null ? '' : ' min' }}</span></div>
                        <div class="text-secondary small mt-2">started to completed</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-secondary small text-uppercase">Re-attempt Rate</div>
                        <div class="fs-2 fw-semibold">{{ $pct($summary['reattempts']['reattempt_rate_percent']) }}</div>
                        <div class="text-secondary small mt-2">{{ $summary['reattempts']['students_with_multiple_attempts'] }} of {{ $summary['reattempts']['students_with_any_attempt'] }} students re-attempted</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            {{-- Completion statistics --}}
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Completion Statistics</div>
                    <div class="card-body">
                        @php $totalStarted = $summary['completion']['started']; @endphp
                        @if ($totalStarted === 0)
                            <p class="text-secondary text-center py-4 mb-0">No attempts recorded yet.</p>
                        @else
                            <div class="progress mb-3" style="height: 1.5rem" role="progressbar" aria-label="Attempt status breakdown">
                                @foreach (['in_progress' => 'bg-info', 'submitted' => 'bg-warning', 'completed' => 'bg-success', 'abandoned' => 'bg-dark'] as $status => $color)
                                    @if ($summary['completion']['by_status'][$status] > 0)
                                        <div class="progress-bar {{ $color }}" style="width: {{ $summary['completion']['by_status'][$status] / $totalStarted * 100 }}%">
                                            {{ $summary['completion']['by_status'][$status] }}
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                            <dl class="row mb-0 small">
                                <dt class="col-8 fw-normal"><span class="badge text-bg-info">&nbsp;</span> In Progress</dt>
                                <dd class="col-4 text-end">{{ $summary['completion']['by_status']['in_progress'] }}</dd>
                                <dt class="col-8 fw-normal"><span class="badge text-bg-warning">&nbsp;</span> Submitted</dt>
                                <dd class="col-4 text-end">{{ $summary['completion']['by_status']['submitted'] }}</dd>
                                <dt class="col-8 fw-normal"><span class="badge text-bg-success">&nbsp;</span> Completed</dt>
                                <dd class="col-4 text-end">{{ $summary['completion']['by_status']['completed'] }}</dd>
                                <dt class="col-8 fw-normal"><span class="badge text-bg-dark">&nbsp;</span> Abandoned</dt>
                                <dd class="col-4 text-end">{{ $summary['completion']['by_status']['abandoned'] }}</dd>
                                <dt class="col-8 fw-semibold border-top pt-2 mt-2">Total Started</dt>
                                <dd class="col-4 text-end fw-semibold border-top pt-2 mt-2">{{ $totalStarted }}</dd>
                            </dl>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Score distribution --}}
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Score Distribution</div>
                    <div class="card-body">
                        @php $evaluatedCount = $summary['score_distribution']['evaluated_count']; @endphp
                        @if ($evaluatedCount === 0)
                            <p class="text-secondary text-center py-4 mb-0">No evaluated attempts yet.</p>
                        @else
                            <div class="progress mb-3" style="height: 1.5rem" role="progressbar" aria-label="Score distribution breakdown">
                                @foreach (['below_50' => 'bg-danger', 'between_50_and_75' => 'bg-warning', 'above_75' => 'bg-success'] as $bucket => $color)
                                    @if ($summary['score_distribution']['buckets'][$bucket] > 0)
                                        <div class="progress-bar {{ $color }}" style="width: {{ $summary['score_distribution']['buckets'][$bucket] / $evaluatedCount * 100 }}%">
                                            {{ $summary['score_distribution']['buckets'][$bucket] }}
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                            <dl class="row mb-0 small">
                                <dt class="col-8 fw-normal"><span class="badge text-bg-danger">&nbsp;</span> Below 50%</dt>
                                <dd class="col-4 text-end">{{ $summary['score_distribution']['buckets']['below_50'] }}</dd>
                                <dt class="col-8 fw-normal"><span class="badge text-bg-warning">&nbsp;</span> 50%–75%</dt>
                                <dd class="col-4 text-end">{{ $summary['score_distribution']['buckets']['between_50_and_75'] }}</dd>
                                <dt class="col-8 fw-normal"><span class="badge text-bg-success">&nbsp;</span> Above 75%</dt>
                                <dd class="col-4 text-end">{{ $summary['score_distribution']['buckets']['above_75'] }}</dd>
                                <dt class="col-8 fw-semibold border-top pt-2 mt-2">Average</dt>
                                <dd class="col-4 text-end fw-semibold border-top pt-2 mt-2">{{ $pct($summary['score_distribution']['average_percent']) }}</dd>
                            </dl>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            {{-- Hint usage --}}
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Hint Usage</div>
                    <div class="card-body p-0">
                        <div class="d-flex justify-content-between p-3 border-bottom small text-secondary">
                            <span>Total Unlocks: <strong class="text-body">{{ $summary['hint_usage']['total_unlocks'] }}</strong></span>
                            <span>Avg per Completed Attempt: <strong class="text-body">{{ $num($summary['hint_usage']['average_hints_per_completed_attempt'], 2) }}</strong></span>
                        </div>
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Hint</th>
                                    <th class="text-end">Unlocks</th>
                                    <th class="text-end">Total Penalty</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($summary['hint_usage']['by_hint'] as $hint)
                                    <tr>
                                        <td>Hint #{{ $hint['hint_id'] }}</td>
                                        <td class="text-end">{{ $hint['unlock_count'] }}</td>
                                        <td class="text-end">{{ $num($hint['total_penalty_applied'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-secondary py-4">No hints unlocked yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Average completion time / re-attempt statistics --}}
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Completion Time &amp; Re-attempts</div>
                    <div class="card-body">
                        <dl class="row mb-0 small">
                            <dt class="col-8 fw-normal">Average Completion Time</dt>
                            <dd class="col-4 text-end">
                                {{ $num($summary['completion_time']['average_minutes']) }}{{ $summary['completion_time']['average_minutes'] === null ? '' : ' min' }}
                            </dd>
                            <dt class="col-8 fw-normal border-top pt-2 mt-2">Students with Any Attempt</dt>
                            <dd class="col-4 text-end border-top pt-2 mt-2">{{ $summary['reattempts']['students_with_any_attempt'] }}</dd>
                            <dt class="col-8 fw-normal">Students with Multiple Attempts</dt>
                            <dd class="col-4 text-end">{{ $summary['reattempts']['students_with_multiple_attempts'] }}</dd>
                            <dt class="col-8 fw-semibold border-top pt-2 mt-2">Re-attempt Rate</dt>
                            <dd class="col-4 text-end fw-semibold border-top pt-2 mt-2">{{ $pct($summary['reattempts']['reattempt_rate_percent']) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        {{-- Category breakdown --}}
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Category Breakdown</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="text-end">Cases</th>
                            <th class="text-end">Started</th>
                            <th class="text-end">Completed</th>
                            <th class="text-end">Completion Rate</th>
                            <th class="text-end">Avg Score</th>
                            <th class="text-end">Avg Time</th>
                            <th class="text-end">Re-attempt Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categoryAggregates as $category)
                            <tr>
                                <td>{{ $category['category_name'] }}</td>
                                <td class="text-end">{{ $category['case_count'] }}</td>
                                <td class="text-end">{{ $category['completion']['started'] }}</td>
                                <td class="text-end">{{ $category['completion']['completed'] }}</td>
                                <td class="text-end">{{ $pct($category['completion']['completion_rate_percent']) }}</td>
                                <td class="text-end">{{ $pct($category['score_distribution']['average_percent']) }}</td>
                                <td class="text-end">{{ $num($category['completion_time']['average_minutes']) }}{{ $category['completion_time']['average_minutes'] === null ? '' : ' min' }}</td>
                                <td class="text-end">{{ $pct($category['reattempts']['reattempt_rate_percent']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-secondary py-4">No categories yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
