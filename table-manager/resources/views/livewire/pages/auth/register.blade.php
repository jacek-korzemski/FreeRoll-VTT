<?php

use App\Models\User;
use App\Support\Username;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public bool $embedded = false;

    public string $name = '';
    public string $username = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $this->username = strtolower(trim($this->username));

        $validated = $this->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'username' => Username::rules(),
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
                'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            ],
            [
                'username.regex' => 'Nazwa użytkownika: 3–32 znaki, małe litery, cyfry i myślnik (nie na początku/końcu).',
                'username.not_in' => 'Ta nazwa użytkownika jest zarezerwowana.',
                'username.unique' => 'Ta nazwa użytkownika jest już zajęta.',
            ],
        );

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    @unless ($embedded)
        <div class="mb-6 flex rounded-lg bg-white/10 p-1" role="tablist" aria-label="Logowanie lub rejestracja">
            <a
                href="{{ route('home') }}"
                wire:navigate
                role="tab"
                aria-selected="false"
                class="flex-1 rounded-md px-3 py-2 text-center text-sm font-semibold text-gray-400 transition hover:text-white"
            >
                Zaloguj się
            </a>
            <span
                role="tab"
                aria-selected="true"
                class="flex-1 rounded-md px-3 py-2 text-center text-sm font-semibold bg-blue-600 text-white shadow-sm"
            >
                Załóż konto
            </span>
        </div>
    @endunless

    <form wire:submit="register">
        <!-- Name -->
        <div>
            <x-input-label for="name" value="Imię i nazwisko" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Username (immutable URL slug) -->
        <div class="mt-4">
            <x-input-label for="username" value="Nazwa użytkownika" />
            <x-text-input wire:model="username" id="username" class="block mt-1 w-full" type="text" name="username" required autocomplete="username" />
            <p class="mt-1 text-xs text-gray-400">Małe litery, cyfry i myślnik. Trafi do adresu stołu: /vtt/user/<em>nazwa</em>/… Nie da się później zmienić.</p>
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" value="Email" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autocomplete="email" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" value="Hasło" />

            <x-text-input wire:model="password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Potwierdź hasło" />

            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            @unless ($embedded)
                <a class="underline text-sm text-gray-400 hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800" href="{{ route('login') }}" wire:navigate>
                    Masz już konto?
                </a>
            @endunless

            <x-primary-button class="{{ $embedded ? '' : 'ms-4' }}">
                Załóż konto
            </x-primary-button>
        </div>
    </form>
</div>
