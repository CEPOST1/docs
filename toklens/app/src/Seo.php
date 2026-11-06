<?php

declare(strict_types=1);

namespace App;

final class Seo
{
    /**
     * Build the SEO context for a page.
     * $path is the path WITHOUT language prefix, e.g. '/account-age'.
     * $langs limits hreflang alternates (articles may exist in fewer languages).
     */
    public static function page(string $title, string $description, string $path, array $opt = []): array
    {
        $langs = $opt['langs'] ?? I18n::codes();
        $alternates = [];
        foreach ($langs as $code) {
            $alternates[$code] = abs_url(url($path, $code));
        }
        $default = in_array('en', $langs, true) ? 'en' : ($langs[0] ?? 'en');
        return [
            'title' => $title,
            'description' => mb_substr(trim(preg_replace('/\s+/u', ' ', $description) ?? ''), 0, 300),
            'canonical' => abs_url(url($path)),
            'alternates' => $alternates,
            'x_default' => abs_url(url($path, $default)),
            'robots' => $opt['robots'] ?? 'index,follow,max-image-preview:large,max-snippet:-1',
            'type' => $opt['type'] ?? 'website',
            'image' => $opt['image'] ?? abs_url('/assets/img/og.png'),
            'jsonld' => $opt['jsonld'] ?? [],
            'published' => $opt['published'] ?? null,
            'modified' => $opt['modified'] ?? null,
        ];
    }

    public static function noindex(string $title, string $path = '/'): array
    {
        return self::page($title, '', $path, ['robots' => 'noindex,follow', 'langs' => [I18n::current()]]);
    }

    public static function organization(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => base_url() . '/#org',
            'name' => site_name(),
            'url' => base_url() . '/',
            'logo' => abs_url('/assets/img/icon-512.png'),
        ];
    }

    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => base_url() . '/#website',
            'name' => site_name(),
            'url' => abs_url(url('/')),
            'inLanguage' => I18n::meta()['hreflang'],
            'publisher' => ['@id' => base_url() . '/#org'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => abs_url(url('/lookup')) . '?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public static function webApp(string $name, string $path, string $description): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebApplication',
            'name' => $name,
            'url' => abs_url(url($path)),
            'description' => $description,
            'applicationCategory' => 'UtilitiesApplication',
            'operatingSystem' => 'Any',
            'browserRequirements' => 'Requires JavaScript. Requires HTML5.',
            'inLanguage' => I18n::meta()['hreflang'],
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
            'publisher' => ['@id' => base_url() . '/#org'],
        ];
    }

    public static function faq(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn(array $qa) => [
                '@type' => 'Question',
                'name' => $qa[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]],
            ], $items),
        ];
    }

    /** @param array<array{0:string,1:string}> $crumbs [name, path-without-lang] */
    public static function breadcrumbs(array $crumbs): array
    {
        $list = [];
        foreach ($crumbs as $i => [$name, $path]) {
            $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => abs_url(url($path))];
        }
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }
}
