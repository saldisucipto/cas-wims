<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'WIMS')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">

    <style>
        .wims-surface {
            border-radius: 1rem;
            border: 1px solid rgb(226 232 240 / 90%);
            background: rgb(255 255 255 / 92%);
            box-shadow: 0 14px 32px rgb(15 23 42 / 0.07);
            backdrop-filter: blur(12px);
        }

        .wims-page-title {
            font-size: 1.7rem;
            line-height: 2rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            color: rgb(15 23 42);
        }

        .wims-page-subtitle {
            margin-top: 0.35rem;
            color: rgb(71 85 105);
            font-size: 0.93rem;
            line-height: 1.4rem;
        }

        .wims-breadcrumb {
            margin-top: 0.8rem;
            color: rgb(100 116 139);
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .wims-form-control,
        .wims-form-select,
        .wims-form-textarea,
        input[type='text'],
        input[type='number'],
        input[type='password'],
        input[type='email'],
        select,
        textarea {
            border-radius: 0.65rem;
            border-color: rgb(203 213 225);
            font-size: 0.875rem;
        }

        .wims-form-control:focus,
        .wims-form-select:focus,
        .wims-form-textarea:focus,
        input[type='text']:focus,
        input[type='number']:focus,
        input[type='password']:focus,
        input[type='email']:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: rgb(59 130 246);
            box-shadow: 0 0 0 3px rgb(59 130 246 / 0.2);
        }

        .wims-btn-primary {
            background: rgb(29 78 216);
            color: #fff;
        }

        .wims-btn-primary:hover {
            background: rgb(30 64 175);
        }

        .wims-btn-success {
            background: rgb(22 163 74);
            color: #fff;
        }

        .wims-btn-success:hover {
            background: rgb(21 128 61);
        }

        .wims-btn-warning {
            background: rgb(202 138 4);
            color: #fff;
        }

        .wims-btn-warning:hover {
            background: rgb(161 98 7);
        }

        .wims-btn-danger {
            background: rgb(220 38 38);
            color: #fff;
        }

        .wims-btn-danger:hover {
            background: rgb(185 28 28);
        }

        .wims-btn {
            border-radius: 0.75rem;
            padding: 0.55rem 0.9rem;
            font-size: 0.8125rem;
            font-weight: 700;
            box-shadow: 0 4px 10px rgb(15 23 42 / 0.07);
            transition: transform .2s, box-shadow .2s, background-color .2s;
        }

        .wims-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 16px rgb(15 23 42 / 0.12);
        }

        .wims-alert-success {
            border: 1px solid rgb(167 243 208);
            background: rgb(236 253 245);
            color: rgb(6 95 70);
            border-radius: 0.7rem;
            padding: 0.75rem 0.95rem;
            font-size: 0.875rem;
        }

        .wims-alert-danger {
            border: 1px solid rgb(254 202 202);
            background: rgb(254 242 242);
            color: rgb(153 27 27);
            border-radius: 0.7rem;
            padding: 0.75rem 0.95rem;
            font-size: 0.875rem;
        }

        .wims-table {
            min-width: 100%;
            text-align: left;
            font-size: 0.875rem;
        }

        .wims-table thead {
            background: linear-gradient(90deg, rgb(248 250 252), rgb(240 253 250));
            color: rgb(71 85 105);
        }

        .wims-table th,
        .wims-table td {
            padding: 0.7rem 0.8rem;
            white-space: nowrap;
            vertical-align: middle;
        }

        .wims-table tbody tr {
            border-top: 1px solid rgb(226 232 240);
        }

        .wims-table tbody tr:hover {
            background: rgb(240 253 250 / 55%);
        }

        .wims-sortable {
            cursor: pointer;
            user-select: none;
        }

        .wims-sortable::after {
            content: ' ↕';
            color: rgb(148 163 184);
            font-weight: 400;
        }

        .wims-empty-state {
            border: 1px dashed rgb(203 213 225);
            border-radius: 0.8rem;
            background: rgb(248 250 252);
            text-align: center;
            color: rgb(100 116 139);
            padding: 1.2rem;
            font-size: 0.875rem;
        }

        .sidebar-link,
        .sidebar-group-toggle {
            transition: background-color .18s, color .18s, transform .18s;
        }

        .sidebar-link:hover,
        .sidebar-group-toggle:hover {
            transform: translateX(2px);
        }

        @media (max-width: 640px) {
            .wims-company-footer__inner {
                align-items: flex-start;
                flex-direction: column;
            }

            .wims-company-footer__inner > p {
                text-align: left;
            }
        }
    </style>

    @stack('styles')
</head>

<body class="min-h-screen text-slate-900 antialiased">
    @yield('content')

    <footer class="wims-company-footer" aria-label="Warehouse information">
        <div class="wims-company-footer__inner">
            <div class="flex items-center gap-3">
                <span class="wims-company-mark" aria-hidden="true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V8.25L12 4l7 4.25V21M9 21v-5h6v5M8 11h.01M16 11h.01" />
                    </svg>
                </span>
                <div>
                    <p class="wims-brand-eyebrow">Warehouse operation</p>
                    <p class="text-sm font-semibold text-slate-800">PT. Cipta Aneka Servis</p>
                </div>
            </div>
            <p class="max-w-md text-right text-xs leading-relaxed text-slate-500">WIMS supports the warehouse team with a single, connected operational workspace.</p>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(function() {
            const i18nMap = [
                ['Standardized module interface aligned with Administration Dashboard design system.',
                    'Tampilan modul sudah disesuaikan dengan standar Dashboard Administrasi.'
                ],
                ['Warehouse Information Management System', 'Sistem Informasi Manajemen Gudang'],
                ['Warehouse Information', 'Informasi Gudang'],
                ['Management System', 'Sistem Manajemen'],
                ['Administration Dashboard', 'Dashboard Administrasi'],
                ['Administration Login', 'Masuk Administrasi'],
                ['Leader Login', 'Masuk Leader'],
                ['Leader Panel', 'Panel Leader'],
                ['Packing Dashboard', 'Dashboard Packing'],
                ['RF Handheld Dashboard', 'Dashboard RF Handheld'],
                ['RF Handheld Registration', 'Registrasi RF Handheld'],
                ['Packing Station Registration', 'Registrasi Meja Packing'],
                ['Request Consumable', 'Permintaan Consumable'],
                ['Consumable Request Submitted', 'Permintaan Consumable Terkirim'],
                ['Waiting Leader Validation', 'Menunggu Validasi Leader'],
                ['Working Session Report', 'Laporan Sesi Kerja'],
                ['Consumable Usage Report', 'Laporan Pemakaian Consumable'],
                ['Inventory Report', 'Laporan Inventaris'],
                ['RF Device Usage Report', 'Laporan Penggunaan RF'],
                ['Daily Worker Activity Report', 'Laporan Aktivitas Pekerja Harian'],
                ['Warehouse Settings', 'Pengaturan Gudang'],
                ['Shift Settings', 'Pengaturan Shift'],
                ['Activity Logs', 'Log Aktivitas'],
                ['Stock Transaction History', 'Riwayat Transaksi Stok'],
                ['Stock Adjustment', 'Penyesuaian Stok'],
                ['Stock Opname', 'Stok Opname'],
                ['Consumable Receiving', 'Penerimaan Consumable'],
                ['System Users', 'Pengguna Sistem'],
                ['WMS Accounts', 'Akun WMS'],
                ['Daily Workers', 'Pekerja Harian'],
                ['Packing Station Master', 'Master Meja Packing'],
                ['RF Device Master', 'Master Perangkat RF'],
                ['Consumable Master', 'Master Consumable'],
                ['Master Data', 'Data Master'],
                ['Dashboard', 'Dashboard'],
                ['Inventory', 'Inventaris'],
                ['Reports', 'Laporan'],
                ['System', 'Sistem'],
                ['Operations', 'Operasional'],
                ['Working Information', 'Informasi Kerja'],
                ['Session Information', 'Informasi Sesi'],
                ['Current Shift', 'Shift Saat Ini'],
                ['Current Time', 'Waktu Saat Ini'],
                ['Current Date', 'Tanggal Saat Ini'],
                ['Current Status', 'Status Saat Ini'],
                ['Working Duration', 'Durasi Kerja'],
                ['Session Duration', 'Durasi Sesi'],
                ['Task Queue', 'Antrian Tugas'],
                ['Status', 'Status'],
                ['Function', 'Fungsi'],
                ['Position', 'Posisi'],
                ['Username', 'Nama Pengguna'],
                ['Password', 'Kata Sandi'],
                ['Employee', 'Pekerja'],
                ['Employee Name', 'Nama Pekerja'],
                ['Employee Code', 'Kode Pekerja'],
                ['RF Device', 'Perangkat RF'],
                ['Packing Station', 'Meja Packing'],
                ['Consumable', 'Consumable'],
                ['Quantity', 'Jumlah'],
                ['Notes', 'Catatan'],
                ['No WMS Account', 'Tanpa Akun WMS'],
                ['All Status', 'Semua Status'],
                ['All Types', 'Semua Jenis'],
                ['All Roles', 'Semua Peran'],
                ['Search table...', 'Cari di tabel...'],
                ['Search', 'Cari'],
                ['Save', 'Simpan'],
                ['Edit', 'Ubah'],
                ['Update', 'Perbarui'],
                ['Add', 'Tambah'],
                ['Create', 'Buat'],
                ['Delete', 'Hapus'],
                ['Submit', 'Kirim'],
                ['Cancel', 'Batal'],
                ['Back', 'Kembali'],
                ['Logout', 'Keluar'],
                ['Login', 'Masuk'],
                ['Open', 'Buka'],
                ['Export', 'Ekspor'],
                ['Validate', 'Validasi'],
                ['Reject', 'Tolak'],
                ['Validate Request', 'Validasi Permintaan'],
                ['Reject Request', 'Tolak Permintaan'],
                ['Start Working', 'Mulai Bekerja'],
                ['Finish Working', 'Selesai Bekerja'],
                ['Start RF Session', 'Mulai Sesi RF'],
                ['Finish RF Session', 'Selesai Sesi RF'],
                ['Waiting for Validation', 'Menunggu Validasi'],
                ['Waiting for Leader Validation...', 'Menunggu Validasi Leader...'],
                ['Success', 'Berhasil'],
                ['Error', 'Kesalahan'],
                ['Login Failed', 'Gagal Masuk'],
                ['Submit Failed', 'Gagal Mengirim'],
                ['Validation Failed', 'Validasi Gagal'],
                ['Rejection Failed', 'Penolakan Gagal'],
                ['Incomplete Request', 'Permintaan Belum Lengkap'],
                ['Delete this', 'Hapus data ini'],
                ['Delete this consumable?', 'Hapus consumable ini?'],
                ['Delete this RF device?', 'Hapus perangkat RF ini?'],
                ['Delete this packing station?', 'Hapus meja packing ini?'],
                ['Delete this daily worker?', 'Hapus pekerja harian ini?'],
                ['Delete this WMS account?', 'Hapus akun WMS ini?'],
                ['Delete this system user?', 'Hapus pengguna sistem ini?'],
                ['This will end current working session and release assigned resources.',
                    'Sesi kerja saat ini akan diakhiri dan resource yang dipakai akan dilepas.'
                ],
                ['This will end current RF session and release assigned resources.',
                    'Sesi RF saat ini akan diakhiri dan resource yang dipakai akan dilepas.'
                ],
                ['Finish Working?', 'Selesaikan Pekerjaan?'],
                ['Finish RF Session?', 'Selesaikan Sesi RF?'],
                ['Cannot Start Session', 'Tidak Bisa Memulai Sesi'],
                ['No activity logs.', 'Belum ada log aktivitas.'],
                ['No pending consumable request found.', 'Tidak ada permintaan consumable yang menunggu.'],
                ['No validated requests.', 'Belum ada permintaan tervalidasi.'],
                ['No rejected requests.', 'Belum ada permintaan ditolak.'],
                ['No inventory data found.', 'Data inventaris belum tersedia.'],
                ['No worker activity data found.', 'Data aktivitas pekerja belum tersedia.'],
                ['No RF device usage data found.', 'Data penggunaan perangkat RF belum tersedia.'],
                ['No consumable usage records found.', 'Data pemakaian consumable belum tersedia.'],
                ['No working session data found.', 'Data sesi kerja belum tersedia.'],
                ['Available', 'Tersedia'],
                ['In Use', 'Sedang Digunakan'],
                ['Maintenance', 'Dalam Perawatan'],
                ['Assigned', 'Dipakai'],
                ['Active', 'Aktif'],
                ['Inactive', 'Tidak Aktif'],
                ['Pending', 'Menunggu'],
                ['Validated', 'Tervalidasi'],
                ['Rejected', 'Ditolak'],
                ['Finished', 'Selesai'],
                ['Morning Shift', 'Shift Pagi'],
                ['Afternoon Shift', 'Shift Siang'],
                ['Night Shift', 'Shift Malam'],
                ['Morning', 'Pagi'],
                ['Afternoon', 'Siang'],
                ['Night', 'Malam'],
                ['Date', 'Tanggal'],
                ['Time', 'Waktu'],
                ['Version', 'Versi'],
                ['Main Menu', 'Menu Utama'],
                ['Operation Summary', 'Ringkasan Operasional'],
            ];

            function escapeRegExp(value) {
                return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }

            function translateText(value) {
                if (!value || typeof value !== 'string') {
                    return value;
                }

                let output = value;
                for (const [from, to] of i18nMap) {
                    output = output.replace(new RegExp(escapeRegExp(from), 'g'), to);
                }

                return output;
            }

            function localizeDomText(rootNode) {
                const walker = document.createTreeWalker(rootNode, NodeFilter.SHOW_TEXT);
                const skipTags = ['SCRIPT', 'STYLE', 'OPTION'];

                while (walker.nextNode()) {
                    const textNode = walker.currentNode;
                    const parentTag = textNode.parentNode && textNode.parentNode.nodeName;
                    if (!textNode.nodeValue || !textNode.nodeValue.trim() || skipTags.includes(parentTag)) {
                        continue;
                    }

                    textNode.nodeValue = translateText(textNode.nodeValue);
                }

                $(rootNode).find('[placeholder],[title],[aria-label],[data-placeholder]').each(function() {
                    const node = $(this);
                    ['placeholder', 'title', 'aria-label', 'data-placeholder'].forEach(function(attr) {
                        const val = node.attr(attr);
                        if (val) {
                            node.attr(attr, translateText(val));
                        }
                    });
                });
            }

            function normalizePageHeader() {
                const title = document.title.replace(' - WIMS', '').trim();
                const firstSection = $('main section, main header').first();
                const firstHeading = firstSection.find('h1').first();

                if (!firstSection.length || !firstHeading.length) {
                    return;
                }

                if (!firstSection.find('.wims-page-title').length) {
                    firstHeading.addClass('wims-page-title');
                }

                if (!firstSection.find('.wims-page-subtitle').length) {
                    firstHeading.after(
                        '<p class="wims-page-subtitle">Tampilan modul sudah disesuaikan dengan standar Dashboard Administrasi.</p>'
                        );
                }

                if (!firstSection.find('.wims-breadcrumb').length) {
                    firstSection.find('.wims-page-subtitle').after('<p class="wims-breadcrumb">WIMS / ' + title +
                        '</p>');
                }

                firstSection.addClass('wims-surface');
            }

            function normalizeStatusBadges(scope) {
                const map = {
                    'active': 'bg-emerald-100 text-emerald-700',
                    'available': 'bg-emerald-100 text-emerald-700',
                    'validated': 'bg-emerald-100 text-emerald-700',
                    'working': 'bg-emerald-100 text-emerald-700',
                    'aktif': 'bg-emerald-100 text-emerald-700',
                    'tersedia': 'bg-emerald-100 text-emerald-700',
                    'tervalidasi': 'bg-emerald-100 text-emerald-700',
                    'inactive': 'bg-slate-200 text-slate-700',
                    'disabled': 'bg-slate-200 text-slate-700',
                    'finished': 'bg-slate-200 text-slate-700',
                    'tidak aktif': 'bg-slate-200 text-slate-700',
                    'nonaktif': 'bg-slate-200 text-slate-700',
                    'selesai': 'bg-slate-200 text-slate-700',
                    'pending': 'bg-amber-100 text-amber-700',
                    'maintenance': 'bg-amber-100 text-amber-700',
                    'menunggu': 'bg-amber-100 text-amber-700',
                    'dalam perawatan': 'bg-amber-100 text-amber-700',
                    'assigned': 'bg-blue-100 text-blue-700',
                    'in use': 'bg-blue-100 text-blue-700',
                    'dipakai': 'bg-blue-100 text-blue-700',
                    'sedang digunakan': 'bg-blue-100 text-blue-700',
                    'rejected': 'bg-red-100 text-red-700',
                    'ditolak': 'bg-red-100 text-red-700',
                };

                scope.find('td, p, span').each(function() {
                    const target = $(this);
                    if (target.children().length > 0 || target.data('badge-ready')) {
                        return;
                    }

                    const raw = target.text().trim();
                    const key = raw.toLowerCase().replace('🟢', '').replace('🟡', '').replace('🔴', '')
                        .trim();
                    if (!map[key]) {
                        return;
                    }

                    target.html(
                        '<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ' +
                        map[key] + '">' + raw + '</span>');
                    target.attr('data-badge-ready', '1');
                });
            }

            function addTableTools(table, index) {
                const wrapper = table.closest('div');
                if (!wrapper.length || wrapper.data('wims-tools-ready')) {
                    return;
                }

                table.addClass('wims-table');

                const toolbar = $(
                    '<div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"></div>'
                    );
                const search = $(
                    '<input type="text" class="wims-form-control w-full rounded-lg border border-slate-300 px-3 py-2 sm:max-w-xs" placeholder="Cari di tabel..." data-table-search-id="' +
                    index + '">');
                toolbar.append(search);
                wrapper.before(toolbar);

                const tbodyRows = table.find('tbody tr').toArray();

                search.on('input', function() {
                    const term = $(this).val().toLowerCase();
                    $(tbodyRows).each(function() {
                        const row = $(this);
                        row.toggle(row.text().toLowerCase().includes(term));
                    });
                });

                table.find('thead th').each(function(colIndex) {
                    const th = $(this);
                    const thText = th.text().trim().toLowerCase();
                    if (thText === 'action' || thText === 'aksi') {
                        return;
                    }

                    th.addClass('wims-sortable');
                    let asc = true;

                    th.on('click', function() {
                        const rows = table.find('tbody tr').get();
                        rows.sort(function(a, b) {
                            const aText = ($(a).children('td').eq(colIndex).text() || '')
                                .trim().toLowerCase();
                            const bText = ($(b).children('td').eq(colIndex).text() || '')
                                .trim().toLowerCase();

                            if (aText < bText) {
                                return asc ? -1 : 1;
                            }

                            if (aText > bText) {
                                return asc ? 1 : -1;
                            }

                            return 0;
                        });

                        asc = !asc;
                        $.each(rows, function(_, row) {
                            table.children('tbody').append(row);
                        });
                    });
                });

                wrapper.attr('data-wims-tools-ready', '1');
            }

            if (window.Swal && window.Swal.fire) {
                const originalSwalFire = window.Swal.fire.bind(window.Swal);
                window.Swal.fire = function(options, ...rest) {
                    if (typeof options === 'string') {
                        options = translateText(options);
                    } else if (options && typeof options === 'object') {
                        ['title', 'text', 'html', 'confirmButtonText', 'cancelButtonText', 'footer'].forEach(
                            function(key) {
                                if (typeof options[key] === 'string') {
                                    options[key] = translateText(options[key]);
                                }
                            });
                    }

                    return originalSwalFire(options, ...rest);
                };
            }

            if ($.fn.select2) {
                const originalSelect2 = $.fn.select2;
                $.fn.select2 = function(options, ...rest) {
                    if (options && typeof options === 'object' && typeof options.placeholder === 'string') {
                        options.placeholder = translateText(options.placeholder);
                    }

                    return originalSelect2.call(this, options, ...rest);
                };
            }

            const originalConfirm = window.confirm;
            window.confirm = function(message) {
                return originalConfirm(translateText(message));
            };

            document.title = translateText(document.title);

            $('main table').each(function(index) {
                addTableTools($(this), index);
            });

            normalizePageHeader();
            localizeDomText(document.body);
            normalizeStatusBadges($('main'));

            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType !== 1) {
                            return;
                        }

                        localizeDomText(node);
                        normalizeStatusBadges($(node));
                    });
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true,
            });
        });
    </script>

    @stack('scripts')
</body>

</html>
