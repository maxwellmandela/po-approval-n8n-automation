<?php

namespace App\Http\Controllers;

use App\Models\ProcurementRequest;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $requests = ProcurementRequest::with(['requester', 'vendor'])
            ->when(auth()->user()->role === 'requester', fn ($query) => $query->where('requester_id', auth()->id()))
            ->latest('updated_at')
            ->get();

        $stats = [
            'drafts' => $requests->where('status', 'draft')->count(),
            'pending_review' => $requests->whereIn('status', ['submitted', 'under_review', 'resubmitted'])->count(),
            'clarification_required' => $requests->where('status', 'clarification_required')->count(),
            'approved' => $requests->where('status', 'approved')->count(),
            'rejected' => $requests->where('status', 'rejected')->count(),
        ];

        return view('dashboard', [
            'stats' => $stats,
            'requests' => $requests->take(8),
        ]);
    }
}
