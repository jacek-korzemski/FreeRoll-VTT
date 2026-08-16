<x-admin-guest-layout>
    <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-4">
        @csrf
        <h1 class="text-lg font-semibold text-white">Logowanie administratora</h1>
        <div>
            <x-input-label for="username" value="Login" />
            <x-text-input id="username" name="username" type="text" class="mt-1 block w-full" :value="old('username')" required autofocus autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('username')" />
        </div>
        <div>
            <x-input-label for="password" value="Hasło" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="current-password" />
        </div>
        <x-primary-button>Zaloguj</x-primary-button>
    </form>
</x-admin-guest-layout>
