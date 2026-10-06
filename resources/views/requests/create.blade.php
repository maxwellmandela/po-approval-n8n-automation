<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">New Procurement Request</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <form action="{{ route('requests.store') }}" method="POST" enctype="multipart/form-data" data-loading-form class="space-y-6 bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="department" class="block text-sm font-medium text-gray-700">Department</label>
                        <select name="department" id="department" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            @foreach(['Operations','Finance','IT','Procurement'] as $department)
                                <option value="{{ $department }}">{{ $department }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="vendor_id" class="block text-sm font-medium text-gray-700">Vendor</label>
                        <select name="vendor_id" id="vendor_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">Select vendor</option>
                            @foreach($vendors as $vendor)
                                <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label for="item_description" class="block text-sm font-medium text-gray-700">Item / Service</label>
                        <input type="text" name="item_description" id="item_description" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    </div>

                    <div>
                        <label for="quantity" class="block text-sm font-medium text-gray-700">Quantity</label>
                        <input type="number" min="1" name="quantity" id="quantity" value="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    </div>

                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700">Amount (KES)</label>
                        <input type="number" min="0" step="0.01" name="amount" id="amount" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    </div>

                    <div>
                        <label for="required_by" class="block text-sm font-medium text-gray-700">Required By</label>
                        <input type="date" name="required_by" id="required_by" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label for="supporting_documents" class="block text-sm font-medium text-gray-700">Supporting Document(s)</label>
                        <input type="file" name="supporting_documents[]" id="supporting_documents" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="mt-1 block w-full text-sm text-gray-600">
                    </div>
                </div>

                <div>
                    <label for="justification" class="block text-sm font-medium text-gray-700">Justification</label>
                    <textarea name="justification" id="justification" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required></textarea>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button type="submit" name="status" value="draft" class="inline-flex min-w-28 items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-wait disabled:opacity-70">
                        <span data-submit-label>Save Draft</span>
                        <span data-submit-loading class="hidden inline-flex items-center gap-2" aria-live="polite"><span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true"></span>Saving…</span>
                    </button>
                    <button type="submit" name="status" value="submitted" class="inline-flex min-w-40 items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:cursor-wait disabled:opacity-70">
                        <span data-submit-label>Submit Request</span>
                        <span data-submit-loading class="hidden inline-flex items-center gap-2" aria-live="polite"><span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true"></span>Submitting…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
