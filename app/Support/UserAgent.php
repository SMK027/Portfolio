<?php

namespace App\Support;

/** Analyse sommaire du User-Agent : type d'appareil, navigateur, système. */
final class UserAgent
{
    public const DEVICES = ['desktop' => 'Ordinateur', 'mobile' => 'Mobile', 'tablet' => 'Tablette', 'other' => 'Autre'];

    /** @return array{device: string, browser: ?string, os: ?string} */
    public static function parse(?string $ua): array
    {
        $ua = (string) $ua;

        $device = match (true) {
            (bool) preg_match('/iPad|Tablet|PlayBook|Silk|Kindle|(Android(?!.*Mobile))/i', $ua) => 'tablet',
            (bool) preg_match('/Mobi|iPhone|iPod|Android.*Mobile|Windows Phone|BlackBerry|Opera Mini/i', $ua) => 'mobile',
            (bool) preg_match('/Windows NT|Macintosh|X11|Linux x86_64|CrOS/i', $ua) => 'desktop',
            default => 'other',
        };

        $browser = match (true) {
            (bool) preg_match('/Edg(e|A|iOS)?\//', $ua)          => 'Edge',
            (bool) preg_match('/OPR\/|Opera/', $ua)              => 'Opera',
            (bool) preg_match('/SamsungBrowser/', $ua)           => 'Samsung Internet',
            (bool) preg_match('/Firefox\/|FxiOS/', $ua)          => 'Firefox',
            (bool) preg_match('/Chrome\/|CriOS/', $ua)           => 'Chrome',
            (bool) preg_match('/Safari\//', $ua)                 => 'Safari',
            default                                             => null,
        };

        $os = match (true) {
            (bool) preg_match('/Windows/', $ua)                  => 'Windows',
            (bool) preg_match('/iPhone|iPad|iPod/', $ua)         => 'iOS',
            (bool) preg_match('/Mac OS X|Macintosh/', $ua)       => 'macOS',
            (bool) preg_match('/Android/', $ua)                  => 'Android',
            (bool) preg_match('/CrOS/', $ua)                     => 'ChromeOS',
            (bool) preg_match('/Linux/', $ua)                    => 'Linux',
            default                                             => null,
        };

        return compact('device', 'browser', 'os');
    }
}
