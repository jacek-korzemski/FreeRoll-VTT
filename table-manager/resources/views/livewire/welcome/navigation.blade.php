<nav class="-mx-3 flex flex-1 justify-end gap-1">
    @auth
        <a href="{{ route('dashboard') }}" class="rounded-md px-3 py-2 text-white transition hover:text-vtt-accent">
            Stoły
        </a>
    @else
        <a href="{{ route('login') }}" class="rounded-md px-3 py-2 text-gray-300 transition hover:text-white">
            Logowanie
        </a>
        @if (Route::has('register'))
            <a href="{{ route('register') }}" class="rounded-md px-3 py-2 text-gray-300 transition hover:text-white">
                Rejestracja
            </a>
        @endif
    @endauth
</nav>
