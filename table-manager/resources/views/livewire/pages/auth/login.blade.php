<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public string $tab = 'login';

    public function mount(): void
    {
        $requested = request()->query('tab', 'login');

        $this->tab = in_array($requested, ['login', 'register'], true) ? $requested : 'login';
    }

    public function showLogin(): void
    {
        $this->tab = 'login';
    }

    public function showRegister(): void
    {
        $this->tab = 'register';
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6 flex rounded-lg bg-white/10 p-1" role="tablist" aria-label="Logowanie lub rejestracja">
        <button
            type="button"
            role="tab"
            wire:click="showLogin"
            aria-selected="{{ $tab === 'login' ? 'true' : 'false' }}"
            @class([
                'flex-1 rounded-md px-3 py-2 text-sm font-semibold transition',
                'bg-blue-600 text-white shadow-sm' => $tab === 'login',
                'text-gray-400 hover:text-white' => $tab !== 'login',
            ])
        >
            Zaloguj się
        </button>
        <button
            type="button"
            role="tab"
            wire:click="showRegister"
            aria-selected="{{ $tab === 'register' ? 'true' : 'false' }}"
            @class([
                'flex-1 rounded-md px-3 py-2 text-sm font-semibold transition',
                'bg-blue-600 text-white shadow-sm' => $tab === 'register',
                'text-gray-400 hover:text-white' => $tab !== 'register',
            ])
        >
            Załóż konto
        </button>
    </div>

    @if ($tab === 'register')
        <livewire:pages.auth.register :embedded="true" :key="'auth-register'" />
    @else
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form wire:submit="login">
            <div>
                <x-input-label for="email" value="Email" />
                <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="password" value="Hasło" />

                <x-text-input wire:model="form.password" id="password" class="block mt-1 w-full"
                                type="password"
                                name="password"
                                required autocomplete="current-password" />

                <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
            </div>

            <div class="block mt-4">
                <label for="remember" class="inline-flex items-center">
                    <input wire:model="form.remember" id="remember" type="checkbox" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-blue-600 shadow-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:focus:ring-offset-gray-800" name="remember">
                    <span class="ms-2 text-sm text-gray-400">Zapamiętaj mnie</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                @if (Route::has('password.request'))
                    <a class="underline text-sm text-gray-400 hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800" href="{{ route('password.request') }}" wire:navigate>
                        Nie pamiętasz hasła?
                    </a>
                @endif

                <x-primary-button class="ms-3">
                    Zaloguj się
                </x-primary-button>
            </div>
        </form>
    @endif
</div>
