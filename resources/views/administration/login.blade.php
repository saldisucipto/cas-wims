@extends('layouts.operation')

@section('title', 'Administration Login - WIMS')

@section('content')
    <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-6">
        <section
            class="wims-elevated-card w-full max-w-md rounded-2xl border border-slate-200 bg-white/95 p-6 sm:p-8">
            <div class="text-center">
                <p class="wims-brand-eyebrow">PT. Cipta Aneka Servis</p>
                <h1 class="mt-2 text-3xl font-bold text-slate-900">Administration Login</h1>
                <p class="mt-3 text-slate-600">Administrative control panel for warehouse operations.</p>
            </div>

            <form action="{{ route('administration.login.submit') }}" method="POST" class="mt-8 space-y-5">
                @csrf

                <div>
                    <label for="username" class="mb-1 block text-sm font-semibold text-slate-700">Username</label>
                    <input id="username" name="username" type="text" value="{{ old('username') }}" required
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-blue-500 focus:outline-hidden focus:ring-2 focus:ring-blue-500/30">
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-semibold text-slate-700">Password</label>
                    <input id="password" name="password" type="password" required
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-blue-500 focus:outline-hidden focus:ring-2 focus:ring-blue-500/30">
                </div>

                <p class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                    Dummy credentials: username <span class="font-semibold text-slate-900">admin</span>, password
                    <span class="font-semibold text-slate-900">admin123</span>.
                </p>

                <button type="submit"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-blue-700 px-6 py-3 text-base font-semibold text-white transition hover:bg-blue-800 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500/60">
                    Login
                </button>
            </form>
        </section>
    </main>
@endsection

@push('scripts')
    <script>
        $(function() {
            @if (session('administration_login_error'))
                Swal.fire({
                    title: 'Login Failed',
                    text: @json(session('administration_login_error')),
                    icon: 'error',
                    confirmButtonColor: '#1d4ed8',
                });
            @endif
        });
    </script>
@endpush
