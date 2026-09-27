<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_seo_off_keeps_login_on_the_home_page(): void
    {
        config(['vtt.seo' => false]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Zaloguj się')
            ->assertSee('Załóż konto')
            ->assertDontSee('Darmowy stół VTT do gier RPG online', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->get('/jak-grac')->assertNotFound();
        $this->get('/sitemap.xml')->assertNotFound();

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee("Disallow: /\n", false)
            ->assertDontSee('Sitemap:', false);

        $this->get('/dashboard')->assertRedirect('/');
    }

    public function test_seo_on_serves_the_marketing_site(): void
    {
        config(['vtt.seo' => true]);

        $home = $this->get('/');

        $home->assertOk()
            ->assertSee('<h1', false)
            ->assertSee('Darmowy stół VTT do gier RPG online', false)
            ->assertSee('<link rel="canonical" href="'.e(route('home')).'"', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('SoftwareApplication', false)
            ->assertSee('FAQPage', false)
            ->assertSee('https://github.com/jacek-korzemski/FreeRoll-VTT', false)
            ->assertSee('Jak grać', false)
            ->assertDontSee('Zapamiętaj mnie', false)
            ->assertHeaderMissing('X-Robots-Tag');

        $this->assertSame(1, substr_count($home->getContent(), '<h1'));

        $tutorial = $this->get('/jak-grac');

        $tutorial->assertOk()
            ->assertSee('<h1', false)
            ->assertSee('Jak zagrać na darmowym stole VTT', false)
            ->assertSee('HowTo', false)
            ->assertSee('<link rel="canonical" href="'.e(route('tutorial')).'"', false)
            ->assertHeaderMissing('X-Robots-Tag');

        $this->assertSame(1, substr_count($tutorial->getContent(), '<h1'));

        $this->get('/login')
            ->assertOk()
            ->assertSee('Zapamiętaj mnie')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->get('/dashboard')->assertRedirect('/login');

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Allow: /', false)
            ->assertSee('Disallow: /dashboard', false)
            ->assertSee('Sitemap: '.route('sitemap'), false);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('home'), false)
            ->assertSee(route('tutorial'), false);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Twoje stoły');
    }
}
