<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Review Queue</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs uppercase text-gray-500">Request</th>
                                <th class="px-6 py-3 text-left text-xs uppercase text-gray-500">Requester</th>
                                <th class="px-6 py-3 text-left text-xs uppercase text-gray-500">Department</th>
                                <th class="px-6 py-3 text-left text-xs uppercase text-gray-500">Amount</th>
                                <th class="px-6 py-3 text-left text-xs uppercase text-gray-500">Status</th>
                                <th class="px-6 py-3 text-left text-xs uppercase text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($requests as $request)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-900">{{ $request->request_number ?? 'Draft' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $request->requester->name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $request->department }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">KES {{ number_format($request->amount, 2) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ ucfirst(str_replace('_',' ', $request->status)) }}</td>
                                    <td class="px-6 py-4 text-sm text-blue-600"><a href="{{ route('review.show', $request) }}">Review</a></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">No requests are awaiting review.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
