<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-vtt-accent border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-vtt-accent-hover focus:bg-vtt-accent-hover active:bg-vtt-accent focus:outline-none focus:ring-2 focus:ring-vtt-accent focus:ring-offset-2 dark:focus:ring-offset-vtt-panel disabled:opacity-40 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
