<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Procurement Requests</h2>
            @if(auth()->user()->role === 'requester')
                <a href="{{ route('requests.create') }}" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">New Request</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Request</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Department</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Submitted</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($requests as $request)
                                <tr>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $request->request_number ?? 'Draft' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $request->item_description }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $request->department }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">KES {{ number_format($request->amount, 2) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ ucfirst(str_replace('_',' ', $request->status)) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $request->submitted_at ? $request->submitted_at->format('d M Y') : '—' }}</td>
                                    <td class="px-6 py-4 text-sm text-blue-600"><a href="{{ route('requests.show', $request) }}">Open</a></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">No requests found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
