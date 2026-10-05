<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Request {{ $request->request_number ?? 'Draft' }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm lg:col-span-2">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div><dt class="text-gray-500">Requester</dt><dd class="mt-1 font-medium text-gray-900">{{ $request->requester->name }}</dd></div>
                        <div><dt class="text-gray-500">Department</dt><dd class="mt-1 font-medium text-gray-900">{{ $request->department }}</dd></div>
                        <div><dt class="text-gray-500">Vendor</dt><dd class="mt-1 font-medium text-gray-900">{{ $request->vendor?->name ?? $request->vendor_name }}</dd></div>
                        <div><dt class="text-gray-500">Required Date</dt><dd class="mt-1 font-medium text-gray-900">{{ $request->required_by ? $request->required_by->format('d M Y') : '—' }}</dd></div>
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
                            <h3 class="text-sm font-semibold uppercase text-gray-500">Attachments</h3>
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
                        <h3 class="text-sm font-semibold uppercase text-gray-500">Current Status</h3>
                        <p class="mt-2 inline-flex rounded-full px-2 py-1 text-xs font-semibold
                            @if($request->status === 'draft') bg-slate-100 text-slate-700
                            @elseif(in_array($request->status, ['submitted','under_review','resubmitted'])) bg-amber-100 text-amber-700
                            @elseif($request->status === 'clarification_required') bg-rose-100 text-rose-700
                            @elseif($request->status === 'approved') bg-emerald-100 text-emerald-700
                            @elseif($request->status === 'rejected') bg-red-100 text-red-700
                            @else bg-blue-100 text-blue-700
                            @endif">
                            {{ ucfirst(str_replace('_',' ', $request->status)) }}
                        </p>
                    </div>

                    @if($budget)
                        <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
                            <h3 class="text-sm font-semibold uppercase text-gray-500">Budget</h3>
                            <ul class="mt-3 space-y-2 text-sm text-gray-700">
                                <li>Department: {{ $budget->department }}</li>
                                <li>Budget: KES {{ number_format($budget->budget_amount, 2) }}</li>
                                <li>Committed: KES {{ number_format($budget->committed_amount, 2) }}</li>
                                <li>Current Request: KES {{ number_format($request->amount, 2) }}</li>
                                <li>Remaining: KES {{ number_format($budget->budget_amount - $budget->committed_amount - $request->amount, 2) }}</li>
                                <li>Status: {{ $request->budgetStatus }}</li>
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Workflow Timeline</h3>
                <div class="space-y-4">
                    @foreach($request->auditLogs->sortByDesc('created_at') as $log)
                        <div class="flex gap-4">
                            <div class="flex-shrink-0 mt-1 h-3 w-3 rounded-full bg-blue-500"></div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="font-medium text-gray-900">{{ $log->action }}</p>
                                    <span class="text-xs text-gray-500">{{ $log->created_at->format('d M Y H:i') }}</span>
                                </div>
                                <p class="text-sm text-gray-600">{{ $log->description }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if($request->clarifications->count())
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Clarification History</h3>
                    @foreach($request->clarifications as $clarification)
                        <div class="rounded-lg border border-gray-200 p-4">
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

            @if(auth()->user()->id === $request->requester_id && in_array($request->status, ['clarification_required','resubmitted']))
                <form action="{{ route('requests.clarification.respond', $request->clarifications->first()) }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    @csrf
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Respond to Clarification</h3>
                    @php($clarification = $request->clarifications->last())
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Question</label>
                        <p class="mt-2 rounded-md bg-gray-50 p-3 text-gray-700">{{ $clarification?->question ?? 'No question recorded.' }}</p>
                    </div>
                    <div class="mb-4">
                        <label for="response" class="block text-sm font-medium text-gray-700">Response</label>
                        <textarea name="response" id="response" rows="4" class="mt-1 block w-full rounded-md border-gray-300" required></textarea>
                    </div>
                    <div class="mb-4">
                        <label for="response_documents" class="block text-sm font-medium text-gray-700">Supporting Document(s)</label>
                        <input type="file" name="response_documents[]" id="response_documents" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="mt-1 block w-full text-sm text-gray-600">
                    </div>
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Submit Response</button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
