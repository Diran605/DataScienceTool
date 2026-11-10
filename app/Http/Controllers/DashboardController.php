<?php

namespace App\Http\Controllers;

use App\Enums\RunStatus;
use App\Models\Delivery;
use App\Models\Run;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $runsQuery = Run::query()
            ->when(! $user->hasRole('superadmin'), function ($query) use ($user) {
                $query->where('created_by', $user->id);
            });

        $totalRuns = $runsQuery->count();

        $runsByStatus = (clone $runsQuery)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $recentRuns = (clone $runsQuery)
            ->with('creator:id,name')
            ->latest()
            ->take(5)
            ->get();

        $totalDeliveries = Delivery::query()
            ->when(! $user->hasRole('superadmin'), function ($query) use ($user) {
                $query->where('delivered_by', $user->id);
            })
            ->count();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_runs' => $totalRuns,
                // Runs with data ready for review/cleaning/validation/export (not yet delivered).
                'profiled' => collect([RunStatus::Profiled, RunStatus::Cleaned, RunStatus::Validated, RunStatus::ValidationFailed])
                    ->sum(fn (RunStatus $status) => $runsByStatus[$status->value] ?? 0),
                'delivered' => $totalDeliveries,
                'failed' => $runsByStatus['failed'] ?? 0,
            ],
            'recent_runs' => $recentRuns,
        ]);
    }
}
