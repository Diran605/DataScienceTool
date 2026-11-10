<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryController extends Controller
{
    /**
     * Display a listing of deliveries.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $deliveries = Delivery::query()
            ->when(! $user->hasRole('superadmin'), function ($query) use ($user) {
                $query->whereHas('contract', function ($q) use ($user) {
                    $q->whereHas('users', fn ($uq) => $uq
                        ->where('user_id', $user->id)
                        ->where('can_view_deliveries', true));
                });
            })
            ->with(['run:id,source_file_path,status', 'contract:id,dev_team_name,format', 'deliveredBy:id,name'])
            ->latest('delivered_at')
            ->paginate(15);

        return Inertia::render('Deliveries/Index', [
            'deliveries' => $deliveries,
        ]);
    }

    /**
     * Display the specified delivery.
     */
    public function show(Delivery $delivery): Response
    {
        Gate::authorize('view', $delivery);

        $delivery->load(['run', 'contract', 'deliveredBy:id,name']);

        return Inertia::render('Deliveries/Show', [
            'delivery' => $delivery,
        ]);
    }

    /**
     * Download the delivery file.
     */
    public function download(Delivery $delivery): StreamedResponse
    {
        Gate::authorize('view', $delivery);

        $disk = Storage::disk('data-processing');

        abort_unless($disk->exists($delivery->output_path), 404, 'Delivery file not found.');

        return $disk->download(
            $delivery->output_path,
            basename($delivery->output_path)
        );
    }
}
