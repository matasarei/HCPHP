<?php

namespace Tests\Unit\Core;

use core\Language;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * Application::start() took the language code from the request (?l=) and Language built a path
 * from it: application/lang/<code>.json. So ?l=../config/database loaded the database
 * credentials file as the page's strings, any other JSON file under the document root went the
 * same way, and a code with no file behind it (?l=zz) threw on every page -- a 500 that any
 * visitor could ask for.
 */
class LanguageCodeTest extends TestCase
{
    /**
     * @dataProvider pathProvider
     */
    public function testALanguageCodeCannotNameAPath(string $code): void
    {
        // The exception a missing language has always thrown, refused before any path is built.
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Not a language code');

        Language::getInstance($code);
    }

    public function pathProvider(): array
    {
        return [
            'parent directory' => ['../config/routing'],
            'two levels up' => ['../../composer'],
            'into vendor' => ['../lib/vendor/composer/installed'],
            'absolute' => ['/etc/passwd'],
            'backslash' => ['..\\config\\routing'],
            'dot' => ['en.json'],
        ];
    }

    /**
     * @dataProvider requestedCodeProvider
     */
    public function testTheRequestedCodeIsUsedOnlyWhenThereIsSuchALanguage($requested, string $expected): void
    {
        self::assertSame($expected, Language::chooseCode($requested, 'en'));
    }

    public function requestedCodeProvider(): array
    {
        return [
            'an installed language' => ['en', 'en'],
            'with the trailing slash a link may add' => ['en/', 'en'],
            'a path' => ['../config/database', 'en'],
            'a code with no file' => ['zz', 'en'],
            'empty' => ['', 'en'],
            'absent' => [null, 'en'],
            'an array, from ?l[]=' => [['en'], 'en'],
        ];
    }

    public function testAnInstalledLanguageStillLoads(): void
    {
        self::assertSame('en', Language::getInstance('en')->getCurrentLanguageCode());
    }
}
