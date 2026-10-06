<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Review Request {{ $request->request_number ?? 'Draft' }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm lg:col-span-2">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div><dt class="text-gray-500">Requester</dt><dd class="mt-1 font-medium text-gray-900">{{ $request->requester->name }}</dd></div>
                        <div><dt class="text-gray-500">Department</dt><dd class="mt-1 font-medium text-gray-900">{{ $request->department }}</dd></div>
                        <div><dt class="text-gray-500">Vendor</dt><dd class="mt-1 font-medium text-gray-900">{{ $request->vendor?->name ?? $request->vendor_name }}</dd></div>
                        <div><dt class="text-gray-500">Required By</dt><dd class="mt-1 font-medium text-gray-900">{{ $request->required_by ? $request->required_by->format('d M Y') : '—' }}</dd></div>
                        <div><dt class="text-gray-500">Quantity</dt><dd class="mt-1 font-medium text-gray-900">{{ $request->quantity }}</dd></div>
                        <div><dt class="text-gray-500">Amount</dt><dd class="mt-1 font-medium text-gray-900">KES {{ number_format($request->amount, 2) }}</dd></div>
                    </dl>
                    <div class="mt-6">
                        <h3 class="text-sm font-semibold uppercase text-gray-500">Item / Service</h3>
                        <p class="mt-2 text-gray-900">{{ $request->item_description }}</p>
                    </div>
                    <div class="mt-6">
                        <h3 class="text-sm font-semibold uppercase text-gray-500">Justification</h3>
                        <p class="mt-2 text-gray-700">{{ $request->justification }}</p>
                    </div>
                    @if($request->supporting_documents)
                        <div class="mt-6">
                            <h3 class="text-sm font-semibold uppercase text-gray-500">Supporting Documents</h3>
                            <ul class="mt-2 space-y-2">
                                @foreach($request->supporting_documents as $document)
                                    <li><a href="{{ Storage::url($document) }}" target="_blank" class="text-blue-600 hover:text-blue-500">{{ basename($document) }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <div class="space-y-6">
                    <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
                        <h3 class="text-sm font-semibold uppercase text-gray-500">Budget</h3>
                        @php($budget = $request->budgetSnapshot())
                        @if($budget)
                            <ul class="mt-3 space-y-2 text-sm text-gray-700">
                                <li>Department budget: KES {{ number_format($budget->budget_amount, 2) }}</li>
                                <li>Committed amount: KES {{ number_format($budget->committed_amount, 2) }}</li>
                                <li>Current request: KES {{ number_format($request->amount, 2) }}</li>
                                <li>Remaining: KES {{ number_format($budget->budget_amount - $budget->committed_amount - $request->amount, 2) }}</li>
                                <li>Budget status: {{ $request->budgetStatus }}</li>
                            </ul>
                        @endif
                    </div>

                    <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
                        <h3 class="text-sm font-semibold uppercase text-gray-500">Actions</h3>
                        <div class="mt-4 space-y-3">
                            <form method="POST" action="{{ route('review.approve', $request) }}" data-loading-form>
                                @csrf
                                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 disabled:cursor-wait disabled:opacity-70">
                                    <span data-submit-label>Approve</span>
                                    <span data-submit-loading class="hidden inline-flex items-center gap-2" aria-live="polite"><span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true"></span>Approving…</span>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('review.reject', $request) }}" data-loading-form>
                                @csrf
                                <input type="text" name="reason" placeholder="Rejection reason" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm mb-2">
                                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 disabled:cursor-wait disabled:opacity-70">
                                    <span data-submit-label>Reject</span>
                                    <span data-submit-loading class="hidden inline-flex items-center gap-2" aria-live="polite"><span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true"></span>Rejecting…</span>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('review.clarify', $request) }}" data-loading-form>
                                @csrf
                                <input type="text" name="question" placeholder="Clarification question" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm mb-2">
                                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-400 disabled:cursor-wait disabled:opacity-70">
                                    <span data-submit-label>Request Clarification</span>
                                    <span data-submit-loading class="hidden inline-flex items-center gap-2" aria-live="polite"><span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true"></span>Sending…</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            @if($request->clarifications->count())
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Clarification History</h3>
                    @foreach($request->clarifications as $clarification)
                        <div class="rounded-lg border mb-4 border-gray-200 p-4">
                            <p class="font-medium text-gray-900">Question</p>
                            <p class="mt-2 text-gray-700">{{ $clarification->question }}</p>
                            @if($clarification->response)
                                <p class="mt-4 font-medium text-gray-900">Response</p>
                                <p class="mt-2 text-gray-700">{{ $clarification->response }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
