<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.login')
            ->assertSee('Zaloguj się')
            ->assertSee('Załóż konto');
    }

    public function test_home_page_shows_login_form_for_guests(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeVolt('pages.auth.login')
            ->assertSee('Zaloguj się')
            ->assertSee('Załóż konto')
            ->assertSee('Hasło');
    }

    public function test_home_page_can_open_register_form_from_query(): void
    {
        $this->get('/?tab=register')
            ->assertOk()
            ->assertSee('Imię i nazwisko')
            ->assertSee('Nazwa użytkownika');
    }

    public function test_login_component_can_switch_to_register_tab(): void
    {
        Volt::test('pages.auth.login')
            ->call('showRegister')
            ->assertSet('tab', 'register');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password');

        $component->call('login');

        $component
            ->assertHasErrors()
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_navigation_menu_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response
            ->assertOk()
            ->assertSeeVolt('layout.navigation');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('layout.navigation');

        $component->call('logout');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
