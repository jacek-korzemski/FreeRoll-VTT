<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\View\View;

class SeoController extends Controller
{
    public function tutorial(): View
    {
        abort_unless(config('vtt.seo'), 404);

        return view('marketing.tutorial');
    }

    public function sheets(): View
    {
        abort_unless(config('vtt.seo'), 404);

        return view('marketing.sheets');
    }

    public function robots(): Response
    {
        if (! config('vtt.seo')) {
            $body = "User-agent: *\nDisallow: /\n";
        } else {
            $body = implode("\n", [
                'User-agent: *',
                'Allow: /',
                'Disallow: /dashboard',
                'Disallow: /profile',
                'Disallow: /admin',
                'Disallow: /login',
                'Disallow: /register',
                'Disallow: /forgot-password',
                'Disallow: /reset-password',
                'Disallow: /verify-email',
                'Disallow: /confirm-password',
                '',
                'Sitemap: '.route('sitemap'),
                '',
            ]);
        }

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function sitemap(): Response
    {
        abort_unless(config('vtt.seo'), 404);

        $urls = [
            ['loc' => route('home'), 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => route('tutorial'), 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => route('sheets'), 'changefreq' => 'monthly', 'priority' => '0.8'],
        ];

        $body = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $body .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($urls as $url) {
            $body .= "  <url>\n";
            $body .= '    <loc>'.htmlspecialchars($url['loc'], ENT_XML1)."</loc>\n";
            $body .= '    <changefreq>'.$url['changefreq']."</changefreq>\n";
            $body .= '    <priority>'.$url['priority']."</priority>\n";
            $body .= "  </url>\n";
        }

        $body .= "</urlset>\n";

        return response($body, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
