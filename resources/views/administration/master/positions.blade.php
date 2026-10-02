@extends('layouts.operation')

@section('title', 'Positions - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Master Data</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900">Positions</h1>
                    <p class="mt-1 text-sm text-slate-600">Posisi operasional + operational window &amp; device.</p>
                </div>
                <a href="{{ route('administration.dashboard') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            @if (session('success'))
                <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</p>
            @endif

            @if ($errors->any())
                <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</p>
            @endif

            <form action="{{ route('administration.master.positions.store') }}" method="POST" class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-4">
                @csrf
                <input name="code" placeholder="Code" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="name" placeholder="Name (e.g. Picker)" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="division_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">No Division</option>
                    @foreach ($divisions as $division)
                        <option value="{{ $division->id }}">{{ $division->name }}</option>
                    @endforeach
                </select>
                <input name="device_type" placeholder="Device (PC/RF)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="allowed_shifts" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="S1,S2">S1 + S2</option>
                    <option value="S1">S1 only</option>
                    <option value="S2">S2 only</option>
                </select>
                <input name="start_time" type="time" value="07:00" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="end_time" type="time" value="23:00" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Add</button>
            </form>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600"><tr><th class="px-3 py-2">Code</th><th class="px-3 py-2">Name</th><th class="px-3 py-2">Division</th><th class="px-3 py-2">Device</th><th class="px-3 py-2">Window</th><th class="px-3 py-2">Action</th></tr></thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-3 py-2 font-semibold">{{ $row->code }}</td>
                                <td class="px-3 py-2">{{ $row->name }}</td>
                                <td class="px-3 py-2">{{ $row->division?->name ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row->device_type ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row->allowed_shifts }} ({{ $row->start_time }}-{{ $row->end_time }})</td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-3">
                                        <button
                                            type="button"
                                            class="js-open-position-modal text-xs font-semibold text-blue-700"
                                            data-position-id="{{ $row->id }}"
                                            data-code="{{ $row->code }}"
                                            data-name="{{ $row->name }}"
                                            data-division-id="{{ $row->division_id }}"
                                            data-device-type="{{ $row->device_type }}"
                                            data-allowed-shifts="{{ $row->allowed_shifts }}"
                                            data-start-time="{{ $row->start_time }}"
                                            data-end-time="{{ $row->end_time }}"
                                        >
                                            Edit
                                        </button>

                                        <form action="{{ route('administration.master.positions.delete', $row) }}" method="POST" onsubmit="return confirm('Delete this position?');">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-red-700">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="wims-empty-state">Belum ada position.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div id="position-edit-modal" class="fixed inset-0 z-40 hidden">
            <div class="absolute inset-0 bg-slate-900/50"></div>
            <div class="relative z-10 mx-auto mt-10 w-full max-w-2xl px-4">
                <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-xl">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-slate-900">Edit Position</h2>
                        <button type="button" id="position-modal-close" class="rounded-lg border border-slate-300 px-3 py-1 text-sm font-semibold text-slate-700 hover:bg-slate-100">Close</button>
                    </div>

                    <form id="position-edit-form" method="POST" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                        @csrf
                        <input name="code" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Code">
                        <input name="name" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Name">

                        <select name="division_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">No Division</option>
                            @foreach ($divisions as $division)
                                <option value="{{ $division->id }}">{{ $division->name }}</option>
                            @endforeach
                        </select>

                        <input name="device_type" placeholder="Device (PC/RF)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">

                        <select name="allowed_shifts" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="S1,S2">S1 + S2</option>
                            <option value="S1">S1 only</option>
                            <option value="S2">S2 only</option>
                        </select>

                        <div class="grid grid-cols-2 gap-3">
                            <input name="start_time" type="time" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <input name="end_time" type="time" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </div>

                        <div class="md:col-span-2 flex justify-end gap-2 pt-1">
                            <button type="button" id="position-modal-cancel" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">Cancel</button>
                            <button type="submit" class="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white">Save</button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </main>
@endsection

@push('scripts')
    <script>
        $(function() {
            const modal = $('#position-edit-modal');
            const form = $('#position-edit-form');
            const updateBaseUrl = '{{ url('/administration/master-data/positions') }}';

            const fillValue = function(name, value) {
                const field = form.find(`[name="${name}"]`);

                if (field.length === 0) {
                    return;
                }

                field.val(value ?? '');
            };

            const normalizeTime = function(value) {
                if (!value) {
                    return '';
                }

                return String(value).slice(0, 5);
            };

            const openModal = function(button) {
                const positionId = button.getAttribute('data-position-id');
                form.attr('action', `${updateBaseUrl}/${positionId}`);

                fillValue('code', button.getAttribute('data-code'));
                fillValue('name', button.getAttribute('data-name'));
                fillValue('division_id', button.getAttribute('data-division-id'));
                fillValue('device_type', button.getAttribute('data-device-type'));
                fillValue('allowed_shifts', button.getAttribute('data-allowed-shifts'));
                fillValue('start_time', normalizeTime(button.getAttribute('data-start-time')));
                fillValue('end_time', normalizeTime(button.getAttribute('data-end-time')));

                modal.removeClass('hidden');
            };

            const closeModal = function() {
                modal.addClass('hidden');
            };

            $(document).on('click', '.js-open-position-modal', function() {
                openModal(this);
            });

            $('#position-modal-close, #position-modal-cancel').on('click', function() {
                closeModal();
            });

            modal.on('click', function(event) {
                if (event.target === this) {
                    closeModal();
                }
            });
        });
    </script>
@endpush
