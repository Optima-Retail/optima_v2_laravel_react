<?php

declare(strict_types=1);

namespace App\Support;

final class Locale
{
    public const DEFAULT = 'en';

    public const SESSION_KEY = 'locale';

    public const COOKIE_KEY = 'locale';

    /**
     * @var list<string>
     */
    public const SUPPORTED = ['en', 'es'];

    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        return self::SUPPORTED;
    }

    public static function isSupported(?string $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }

    public static function normalize(?string $locale): string
    {
        if (self::isSupported($locale)) {
            return (string) $locale;
        }

        return self::DEFAULT;
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['code' => 'en', 'label' => 'English'],
            ['code' => 'es', 'label' => 'Español'],
        ];
    }
}
