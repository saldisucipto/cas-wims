@props(['label', 'value', 'subvalue' => null])

<article
    {{ $attributes->class(['wims-elevated-card rounded-xl border bg-white p-5 transition duration-200 hover:-translate-y-0.5 hover:shadow-lg before:block before:h-1 before:w-10 before:rounded-full before:bg-gradient-to-r before:from-teal-600 before:to-blue-700 before:content-["\""]']) }}>
    <p class="mt-4 text-xs font-bold uppercase tracking-widest text-slate-500">{{ $label }}</p>
    <p class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ $value }}</p>

    @if ($subvalue)
        <p class="mt-1 text-sm text-slate-600">{{ $subvalue }}</p>
    @endif
</article>
