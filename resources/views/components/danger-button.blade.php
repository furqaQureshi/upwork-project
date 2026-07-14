<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-2xl bg-rose-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-600/25 transition hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-300']) }}>
    {{ $slot }}
</button>
