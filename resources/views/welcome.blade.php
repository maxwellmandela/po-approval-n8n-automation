<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Procurement Hub</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <header class="mb-12 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-900 text-sm font-semibold text-white shadow-sm">PH</div>
                        <div>
                            <div class="text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Operations</div>
                            <div class="text-lg font-semibold text-slate-900">Procurement Hub</div>
                        </div>
                    </div>

                    @if (Route::has('login'))
                        <nav class="flex items-center gap-3">
                            @auth
                                <a href="{{ url('/dashboard') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:border-slate-300 hover:text-slate-900">Dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:border-slate-300 hover:text-slate-900">Log in</a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Create account</a>
                                @endif
                            @endauth
                        </nav>
                    @endif
                </header>

                <main class="grid items-center gap-8 lg:grid-cols-[1.2fr_0.8fr]">
                    <section class="glass-card rounded-3xl p-8 sm:p-10">
                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Internal workflow</span>
                        <h1 class="mt-6 max-w-xl text-2xl font-bold tracking-tight text-slate-900 sm:text-5xl">
                            Request, review, and approve purchase requests without the paperwork chase.
                        </h1>
                        <p class="mt-5 max-w-xl text-lg text-slate-600">
                            Request, review, and approve purchase requests in one system built for finance, operations, and procurement teams.
                        </p>

                        <div class="mt-8 flex flex-wrap items-center gap-3">
                            @if (Route::has('login'))
                                @auth
                                    <a href="{{ url('/dashboard') }}" class="rounded-xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-slate-700">Start a request</a>
                                @else
                                    <a href="{{ route('login') }}" class="rounded-xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-slate-700">Start a request</a>
                                @endauth
                            @endif
                            <a href="#features" class="rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 hover:border-slate-300 hover:text-slate-900">See how it works</a>
                        </div>

                        <dl class="mt-10 grid gap-4 sm:grid-cols-3">
                            <div class="metric-card">
                                <dt class="text-sm text-slate-500">Open approvals</dt>
                                <dd class="mt-2 text-3xl font-bold text-slate-900">18</dd>
                            </div>
                            <div class="metric-card">
                                <dt class="text-sm text-slate-500">Avg turnaround</dt>
                                <dd class="mt-2 text-3xl font-bold text-slate-900">2.4 days</dd>
                            </div>
                            <div class="metric-card">
                                <dt class="text-sm text-slate-500">POs created</dt>
                                <dd class="mt-2 text-3xl font-bold text-slate-900">94%</dd>
                            </div>
                        </dl>
                    </section>

                    <aside class="space-y-4">
                        <div class="glass-card rounded-3xl p-6">
                            <div class="mb-4 flex items-center justify-between">
                                <p class="text-sm font-medium text-slate-500">Today at a glance</p>
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Live</span>
                            </div>

                            <div class="space-y-4">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Pending review</div>
                                    <div class="mt-3 flex items-end justify-between">
                                        <span class="text-3xl font-semibold text-slate-900">9</span>
                                        <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-700">4 high priority</span>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Budget covered</div>
                                    <div class="mt-3 flex items-end justify-between">
                                        <span class="text-3xl font-semibold text-slate-900">$84k</span>
                                        <span class="text-sm font-medium text-emerald-600">Validated</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card rounded-3xl p-6">
                            <div class="text-sm font-medium text-slate-500">Approval chain</div>
                            <div class="mt-4 space-y-3">
                                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2">
                                    <span class="font-medium text-slate-700">Requester</span>
                                    <span class="text-sm text-slate-500">Draft / submit</span>
                                </div>
                                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2">
                                    <span class="font-medium text-slate-700">Procurement</span>
                                    <span class="text-sm text-slate-500">Review / clarify</span>
                                </div>
                                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2">
                                    <span class="font-medium text-slate-700">Approver</span>
                                    <span class="text-sm text-slate-500">Approve / reject</span>
                                </div>
                            </div>
                        </div>
                    </aside>
                </main>

                <section id="features" class="mt-14 grid gap-6 md:grid-cols-3">
                    <div class="glass-card rounded-2xl p-6">
                        <div class="mb-4 inline-flex rounded-xl bg-blue-100 p-2 text-blue-700">01</div>
                        <h2 class="text-xl font-semibold text-slate-900">Capture requests</h2>
                        <p class="mt-3 text-slate-600">Create purchase requests with vendor, budget, and justification details that match internal policy.</p>
                    </div>
                    <div class="glass-card rounded-2xl p-6">
                        <div class="mb-4 inline-flex rounded-xl bg-amber-100 p-2 text-amber-700">02</div>
                        <h2 class="text-xl font-semibold text-slate-900">Review quickly</h2>
                        <p class="mt-3 text-slate-600">Procurement and approvers can clarify, approve, reject, and document every decision in one place.</p>
                    </div>
                    <div class="glass-card rounded-2xl p-6">
                        <div class="mb-4 inline-flex rounded-xl bg-emerald-100 p-2 text-emerald-700">03</div>
                        <h2 class="text-xl font-semibold text-slate-900">Create POs</h2>
                        <p class="mt-3 text-slate-600">Once approved, the request flows into a purchase order and audit trail for downstream automation.</p>
                    </div>
                </section>
            </div>
    </body>
    </html>
