<?php

declare(strict_types=1);

namespace App;

final class View
{
    /** Render templates/pages/$page.php inside the layout. $page is always an internal constant. */
    public static function render(string $page, array $data, int $status = 200): void
    {
        http_response_code($status);
        Security::htmlHeaders();
        header('Cache-Control: no-cache, private');
        header('Vary: Accept-Language, Cookie');

        Guard::logVisit($status);
        $data['page'] = $page;
        extract($data, EXTR_SKIP);
        ob_start();
        require ROOT . '/templates/pages/' . $page . '.php';
        $content = (string) ob_get_clean();
        require ROOT . '/templates/layout.php';
    }

    /** Browser-check page (no layout, never cached, never indexed). */
    public static function challenge(): void
    {
        http_response_code(200);
        Security::htmlHeaders();
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
        $challenge = Guard::challenge();
        $bits = Guard::powBits();
        $tsKey = Guard::turnstileKey();
        require ROOT . '/templates/challenge.php';
    }

    public static function partial(string $name, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require ROOT . '/templates/partials/' . $name . '.php';
    }
}
