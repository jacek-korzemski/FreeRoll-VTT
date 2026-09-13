<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-amber-500 border border-transparent rounded-md font-semibold text-xs text-gray-900 uppercase tracking-widest hover:bg-amber-400 focus:bg-amber-400 active:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 dark:focus:ring-offset-vtt-panel disabled:opacity-40 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
