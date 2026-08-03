@props(['action', 'filter'])

<div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
    <form action="{{ $action }}" method="GET" class="space-y-4" id="reportDateFilterForm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="md:col-span-1">
                <label for="period" class="mb-1 block text-sm font-semibold text-slate-700">Periode Cepat</label>
                <select id="period" name="period"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="today" @selected(($filter['period'] ?? '') === 'today')>Hari Ini</option>
                    <option value="yesterday" @selected(($filter['period'] ?? '') === 'yesterday')>Kemarin</option>
                    <option value="last_7_days" @selected(($filter['period'] ?? '') === 'last_7_days')>7 Hari Terakhir</option>
                    <option value="last_30_days" @selected(($filter['period'] ?? '') === 'last_30_days')>30 Hari Terakhir</option>
                    <option value="this_month" @selected(($filter['period'] ?? '') === 'this_month')>Bulan Ini</option>
                    <option value="last_month" @selected(($filter['period'] ?? '') === 'last_month')>Bulan Lalu</option>
                    <option value="this_year" @selected(($filter['period'] ?? '') === 'this_year')>Tahun Ini</option>
                    <option value="custom" @selected(($filter['period'] ?? '') === 'custom')>Rentang Kustom</option>
                </select>
            </div>
            <div>
                <label for="start_date" class="mb-1 block text-sm font-semibold text-slate-700">Tanggal Mulai</label>
                <input id="start_date" name="start_date" type="date" value="{{ $filter['start_date'] ?? '' }}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="end_date" class="mb-1 block text-sm font-semibold text-slate-700">Tanggal Selesai</label>
                <input id="end_date" name="end_date" type="date" value="{{ $filter['end_date'] ?? '' }}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="wims-btn wims-btn-primary">Terapkan Filter</button>
            <a href="{{ $action }}"
                class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Reset
                Filter</a>
        </div>
    </form>
</div>

@once
    @push('scripts')
        <script>
            $(function() {
                const period = $('#period');
                const start = $('#start_date');
                const end = $('#end_date');

                function syncCustomMode() {
                    const isCustom = period.val() === 'custom';
                    start.prop('disabled', !isCustom);
                    end.prop('disabled', !isCustom);

                    if (!isCustom) {
                        start.removeAttr('required');
                        end.removeAttr('required');
                        return;
                    }

                    start.attr('required', 'required');
                    end.attr('required', 'required');
                }

                period.on('change', syncCustomMode);
                syncCustomMode();
            });
        </script>
    @endpush
@endonce
