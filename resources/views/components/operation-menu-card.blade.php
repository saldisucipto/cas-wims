@props(['icon', 'title', 'description' => [], 'href' => '#'])

<a href="{{ $href }}"
    {{ $attributes->class(['group block rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40 transition duration-200 hover:-translate-y-0.5 hover:border-teal-300 hover:bg-teal-50/40 hover:shadow-lg focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500/60']) }}>
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-start gap-4">
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-teal-50 to-blue-50 text-3xl leading-none ring-1 ring-inset ring-teal-100" aria-hidden="true">{{ $icon }}</span>

            <div>
                <h3 class="text-lg font-semibold text-slate-900 group-hover:text-teal-800">{{ $title }}</h3>

                <p class="mt-2 text-sm leading-relaxed text-slate-600">
                    @foreach ($description as $line)
                        <span class="block">{{ $line }}</span>
                    @endforeach
                </p>
            </div>
        </div>

        <span
            class="mt-1 inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-300 bg-slate-50 text-sm text-slate-500 transition-colors group-hover:border-blue-400/60 group-hover:bg-white group-hover:text-blue-700"
            aria-hidden="true">
            >
        </span>
    </div>
</a>
