<?php

namespace Tests\Feature;

use App\Livewire\Admin\Dashboard;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'admin.username' => 'admin',
            'admin.password' => 'test-admin-pass',
        ]);
    }

    public function test_guest_is_redirected_from_admin_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_admin_login_screen_renders_and_is_noindex(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Logowanie administratora')
            ->assertSee('noindex', false);
    }

    public function test_invalid_admin_password_is_rejected(): void
    {
        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), [
                'username' => 'admin',
                'password' => 'wrong',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('username');

        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_log_in_and_see_dashboard(): void
    {
        $this->post(route('admin.login.store'), [
            'username' => 'admin',
            'password' => 'test-admin-pass',
        ])->assertRedirect(route('admin.dashboard'));

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Przegląd')
            ->assertSee('Klienci online');
    }

    public function test_laravel_user_cannot_open_admin_without_admin_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(route('admin.login'));
    }

    public function test_user_facing_pages_do_not_link_to_admin(): void
    {
        $this->get('/')->assertOk()->assertDontSee('/admin');
        $this->get('/login')->assertOk()->assertDontSee('/admin');

        $user = User::factory()->create();
        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('/admin');
    }

    public function test_admin_can_log_out(): void
    {
        $this->withSession(['admin_authenticated' => true])
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_admin_tables_and_analytics_pages_render(): void
    {
        $this->withSession(['admin_authenticated' => true]);

        $this->get(route('admin.tables'))->assertOk()->assertSee('Stoły VTT');
        $this->get(route('admin.files'))->assertOk()->assertSee('Pliki graczy');
        $this->get(route('admin.analytics'))->assertOk()->assertSee('Analityka stołów');
    }

    public function test_admin_dashboard_shows_migrate_button(): void
    {
        $this->withSession(['admin_authenticated' => true])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Aktualizuj bazę')
            ->assertSee('php artisan migrate --force')
            ->assertSee('vtt_tables.color_template');
    }

    public function test_guest_cannot_run_migrations_via_livewire(): void
    {
        Livewire::test(Dashboard::class)
            ->call('runMigrations')
            ->assertForbidden();
    }

    public function test_admin_can_run_migrations_from_dashboard(): void
    {
        session(['admin_authenticated' => true]);

        $component = Livewire::test(Dashboard::class)
            ->call('runMigrations')
            ->assertHasNoErrors();

        $this->assertIsString($component->get('migrateOutput'));
        $this->assertNotSame('', $component->get('migrateOutput'));
        $this->assertTrue(Schema::hasColumn('vtt_tables', 'color_template'));
    }

    public function test_run_migrations_adds_missing_color_template_column(): void
    {
        Schema::table('vtt_tables', function (Blueprint $table) {
            $table->dropColumn('color_template');
        });
        $this->assertFalse(Schema::hasColumn('vtt_tables', 'color_template'));

        session(['admin_authenticated' => true]);

        Livewire::test(Dashboard::class)
            ->call('runMigrations')
            ->assertHasNoErrors();

        $this->assertTrue(Schema::hasColumn('vtt_tables', 'color_template'));
    }
}
