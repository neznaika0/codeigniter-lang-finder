<?php

declare(strict_types=1);

namespace Neznaika0\LangFinder\Commands\Translation;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\StreamFilterTrait;
use Config\App;
use SplFileInfo;

/**
 * @internal
 */
final class LocalizationFinderTest extends CIUnitTestCase
{
    use StreamFilterTrait;

    private static string $locale;
    private static string $languageTestPath;

    protected function setUp(): void
    {
        parent::setUp();

        self::$locale           = config(App::class)->defaultLocale;
        self::$languageTestPath = SUPPORTPATH . 'Language/';
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->clearGeneratedFiles();
    }

    public function testWithEmptyDirOption(): void
    {
        $this->makeLocaleDirectory();

        command('lang:finder --dir --lang-dir');

        $this->assertTranslationsExistAndHaveTranslatedKeys();
    }

    public function testNew(): void
    {
        $this->makeLocaleDirectory();

        command('lang:finder');

        $this->assertTranslationsExistAndHaveTranslatedKeys();
    }

    public function testUpdateDefaultLocale(): void
    {
        $this->makeLocaleDirectory();

        command('lang:finder');

        $this->assertTranslationsExistAndHaveTranslatedKeys();
    }

    public function testUpdateWithLocaleOption(): void
    {
        $appConfig        = config(App::class);
        $supportedLocales = $appConfig->supportedLocales;

        $appConfig->supportedLocales = ['ru', 'es', 'en'];

        self::$locale = config(App::class)->supportedLocales[0];
        $this->makeLocaleDirectory();

        command('lang:finder --dir tests/_support/Sources/Translation --locale ' . self::$locale);

        $appConfig->supportedLocales = $supportedLocales;

        $this->assertTranslationsExistAndHaveTranslatedKeys();
    }

    public function testUpdateWithIncorrectLocaleOption(): void
    {
        self::$locale = 'test_locale_incorrect';
        $this->makeLocaleDirectory();

        $status = service('commands')->run('lang:finder', [
            'dir'    => 'tests/_support/Sources/Translation',
            'locale' => self::$locale,
        ]);

        $this->assertSame(EXIT_USER_INPUT, $status);
    }

    public function testUpdateWithIncorrectDirOption(): void
    {
        $this->makeLocaleDirectory();

        $status = service('commands')->run('lang:finder', [
            'dir' => 'tests/_support/Sources/Translation/NotExistFolder',
        ]);

        $this->assertSame(EXIT_USER_INPUT, $status);
    }

    public function testUpdateWithLangDirOption(): void
    {
        mkdir(ROOTPATH . 'tests/_support/Sources/Language', 0755, true);

        command('lang:finder --lang-dir tests/_support/Sources/Language');

        $this->assertFileExists(ROOTPATH . 'tests/_support/Sources/Language/' . self::$locale . '/TranslationOne.php');
        $this->assertFileExists(ROOTPATH . 'tests/_support/Sources/Language/' . self::$locale . '/TranslationThree.php');
        $this->assertFileExists(ROOTPATH . 'tests/_support/Sources/Language/' . self::$locale . '/Translation-Four.php');
        $this->assertFileExists(ROOTPATH . 'tests/_support/Sources/Language/' . self::$locale . '/TranslationFive.php');
        $this->assertFileExists(ROOTPATH . 'tests/_support/Sources/Language/' . self::$locale . '/Статья.php');

        $translationOneKeys      = require ROOTPATH . 'tests/_support/Sources/Language/' . self::$locale . '/TranslationOne.php';
        $translationThreeKeys    = require ROOTPATH . 'tests/_support/Sources/Language/' . self::$locale . '/TranslationThree.php';
        $translationFourKeys     = require ROOTPATH . 'tests/_support/Sources/Language/' . self::$locale . '/Translation-Four.php';
        $translationFiveKeys     = require ROOTPATH . 'tests/_support/Sources/Language/' . self::$locale . '/TranslationFive.php';
        $translationCyrillicKeys = require ROOTPATH . 'tests/_support/Sources/Language/' . self::$locale . '/Статья.php';

        $this->assertSame($translationOneKeys, $this->getActualTranslationOneKeys());
        $this->assertSame($translationThreeKeys, $this->getActualTranslationThreeKeys());
        $this->assertSame($translationFourKeys, $this->getActualTranslationFourKeys());
        $this->assertSame($translationFiveKeys, $this->getActualTranslationFiveKeys());
        $this->assertSame($translationCyrillicKeys, $this->getActualTranslationCyrillicKeys());
    }

    public function testUpdateWithIncorrectLangDirOption(): void
    {
        $this->makeLocaleDirectory();

        $status = service('commands')->run('lang:finder', [
            'lang-dir' => 'tests/_support/Sources/Translation/NotExistFolder/Language',
        ]);

        $this->assertSame(EXIT_USER_INPUT, $status);
    }

    public function testShowNewTranslation(): void
    {
        $this->makeLocaleDirectory();

        command('lang:finder --dir tests/_support/Sources/Translation --show-new');

        $this->assertStringContainsString($this->getActualTableWithNewKeys(), $this->getStreamFilterBuffer());
    }

    public function testShowBadTranslation(): void
    {
        $this->makeLocaleDirectory();

        command('lang:finder --dir tests/_support/Sources/Translation --verbose');

        $this->assertStringContainsString($this->getActualTableWithBadKeys(), $this->getStreamFilterBuffer());
    }

    public function testAddNewKeysToEndArray(): void
    {
        $this->makeLocaleDirectory();

        command('lang:finder');

        $langFile = self::$languageTestPath . self::$locale . '/TranslationThree.php';
        $lines    = file($langFile, FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $index => $line) {
            if (str_contains($line, 'TranslationThree.alerts.CANCELED')) {
                unset($lines[$index]);
            }

            if (str_contains($line, 'TranslationThree.formErrors.edit.INVALID_TEXT')) {
                unset($lines[$index]);
            }
        }

        $code = implode('', $lines);
        file_put_contents($langFile, $code);

        command('lang:finder');

        $langKeys = require $langFile;
        $expected = [
            'alerts' => [
                'created'       => 'TranslationThree.alerts.created',
                'failed_insert' => 'TranslationThree.alerts.failed_insert',
                'missing_keys'  => 'TranslationThree.alerts.missing_keys',
                'Updated'       => 'TranslationThree.alerts.Updated',
                'DELETED'       => 'TranslationThree.alerts.DELETED',
                'CANCELED'      => 'TranslationThree.alerts.CANCELED',
            ],
            'formFields' => [
                'new' => [
                    'name'      => 'TranslationThree.formFields.new.name',
                    'TEXT'      => 'TranslationThree.formFields.new.TEXT',
                    'short_tag' => 'TranslationThree.formFields.new.short_tag',
                ],
                'edit' => [
                    'name'      => 'TranslationThree.formFields.edit.name',
                    'TEXT'      => 'TranslationThree.formFields.edit.TEXT',
                    'short_tag' => 'TranslationThree.formFields.edit.short_tag',
                ],
            ],
            'formErrors' => [
                'edit' => [
                    'empty_name'        => 'TranslationThree.formErrors.edit.empty_name',
                    'missing_short_tag' => 'TranslationThree.formErrors.edit.missing_short_tag',
                    'INVALID_TEXT'      => 'TranslationThree.formErrors.edit.INVALID_TEXT',
                ],
            ],
        ];

        $this->assertSame($expected, $langKeys);
    }

    public function testIsIgnoredFile(): void
    {
        $langFinder = new LocalizationFinder(service('logger'), service('commands'));

        $file = new SplFileInfo(SUPPORTPATH . 'Sources/Translation/TranslationOne.php');

        $this->assertFalse($this->getPrivateMethodInvoker($langFinder, 'isIgnoredFile')($file));

        $dir = new SplFileInfo(SUPPORTPATH . 'Sources/Translation');

        $this->assertTrue($this->getPrivateMethodInvoker($langFinder, 'isIgnoredFile')($dir));

        $this->makeLocaleDirectory();
        touch(self::$languageTestPath . self::$locale . '/TranslationOne.php');
        $dirForLocale = new SplFileInfo(self::$languageTestPath . self::$locale . '/TranslationOne.php');

        $this->assertTrue($this->getPrivateMethodInvoker($langFinder, 'isIgnoredFile')($dirForLocale));
    }

    /**
     * @return array<string, array<string, mixed>|string>
     */
    private function getActualTranslationOneKeys(): array
    {
        return [
            'title'                  => 'TranslationOne.title',
            'DESCRIPTION'            => 'TranslationOne.DESCRIPTION',
            'subTitle'               => 'TranslationOne.subTitle',
            'overflow_style'         => 'TranslationOne.overflow_style',
            'metaTags'               => 'TranslationOne.metaTags',
            'Copyright'              => 'TranslationOne.Copyright',
            'last_operation_success' => 'TranslationOne.last_operation_success',
        ];
    }

    /**
     * @return array<string, array<string, mixed>|string>
     */
    private function getActualTranslationThreeKeys(): array
    {
        return [
            'alerts' => [
                'created'       => 'TranslationThree.alerts.created',
                'failed_insert' => 'TranslationThree.alerts.failed_insert',
                'CANCELED'      => 'TranslationThree.alerts.CANCELED',
                'missing_keys'  => 'TranslationThree.alerts.missing_keys',
                'Updated'       => 'TranslationThree.alerts.Updated',
                'DELETED'       => 'TranslationThree.alerts.DELETED',
            ],
            'formFields' => [
                'new' => [
                    'name'      => 'TranslationThree.formFields.new.name',
                    'TEXT'      => 'TranslationThree.formFields.new.TEXT',
                    'short_tag' => 'TranslationThree.formFields.new.short_tag',
                ],
                'edit' => [
                    'name'      => 'TranslationThree.formFields.edit.name',
                    'TEXT'      => 'TranslationThree.formFields.edit.TEXT',
                    'short_tag' => 'TranslationThree.formFields.edit.short_tag',
                ],
            ],
            'formErrors' => [
                'edit' => [
                    'empty_name'        => 'TranslationThree.formErrors.edit.empty_name',
                    'INVALID_TEXT'      => 'TranslationThree.formErrors.edit.INVALID_TEXT',
                    'missing_short_tag' => 'TranslationThree.formErrors.edit.missing_short_tag',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>|string>
     */
    private function getActualTranslationFourKeys(): array
    {
        return [
            'dashed' => [
                'key-with-dash'     => 'Translation-Four.dashed.key-with-dash',
                'key-with-dash-two' => 'Translation-Four.dashed.key-with-dash-two',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>|string>
     */
    private function getActualTranslationFiveKeys(): array
    {
        return [
            'action' => 'TranslationFive.action...{0} {1} {2} {filter} {page} {search}',
            'users'  => [
                'action' => 'TranslationFive.users.action...{0} {1} {2} {filter} {page} {search}',
                'post'   => 'TranslationFive.users.post...{0} {?} {page}',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>|string>
     */
    private function getActualTranslationCyrillicKeys(): array
    {
        return [
            'список' => [
                'действие' => 'Статья.список.действие...{0} {?} {page}',
            ],
        ];
    }

    private function getActualTableWithNewKeys(): string
    {
        return <<<'TEXT_WRAP'
            +------------------+---------------------------------------------------------------------+
            | File             | Key                                                                 |
            +------------------+---------------------------------------------------------------------+
            | Translation-Four | Translation-Four.dashed.key-with-dash                               |
            | Translation-Four | Translation-Four.dashed.key-with-dash-two                           |
            | TranslationFive  | TranslationFive.action...{0} {1} {2} {filter} {page} {search}       |
            | TranslationFive  | TranslationFive.users.action...{0} {1} {2} {filter} {page} {search} |
            | TranslationFive  | TranslationFive.users.post...{0} {?} {page}                         |
            | TranslationOne   | TranslationOne.Copyright                                            |
            | TranslationOne   | TranslationOne.DESCRIPTION                                          |
            | TranslationOne   | TranslationOne.last_operation_success                               |
            | TranslationOne   | TranslationOne.metaTags                                             |
            | TranslationOne   | TranslationOne.overflow_style                                       |
            | TranslationOne   | TranslationOne.subTitle                                             |
            | TranslationOne   | TranslationOne.title                                                |
            | TranslationThree | TranslationThree.alerts.CANCELED                                    |
            | TranslationThree | TranslationThree.alerts.DELETED                                     |
            | TranslationThree | TranslationThree.alerts.Updated                                     |
            | TranslationThree | TranslationThree.alerts.created                                     |
            | TranslationThree | TranslationThree.alerts.failed_insert                               |
            | TranslationThree | TranslationThree.alerts.missing_keys                                |
            | TranslationThree | TranslationThree.formErrors.edit.INVALID_TEXT                       |
            | TranslationThree | TranslationThree.formErrors.edit.empty_name                         |
            | TranslationThree | TranslationThree.formErrors.edit.missing_short_tag                  |
            | TranslationThree | TranslationThree.formFields.edit.TEXT                               |
            | TranslationThree | TranslationThree.formFields.edit.name                               |
            | TranslationThree | TranslationThree.formFields.edit.short_tag                          |
            | TranslationThree | TranslationThree.formFields.new.TEXT                                |
            | TranslationThree | TranslationThree.formFields.new.name                                |
            | TranslationThree | TranslationThree.formFields.new.short_tag                           |
            | Статья           | Статья.список.действие...{0} {?} {page}                             |
            +------------------+---------------------------------------------------------------------+
            TEXT_WRAP;
    }

    private function getActualTableWithBadKeys(): string
    {
        return <<<'TEXT_WRAP'
            +------------------------+--------------------+
            | Bad Key                | Filepath           |
            +------------------------+--------------------+
            |                        | TranslationTwo.php |
            |                        | TranslationTwo.php |
            |                        | TranslationTwo.php |
            |                        | TranslationTwo.php |
            | ..invalid_nested_key.. | TranslationTwo.php |
            | ..invalid_nested_key.. | TranslationTwo.php |
            | .invalid_key           | TranslationTwo.php |
            | .invalid_key           | TranslationTwo.php |
            | TranslationTwo         | TranslationTwo.php |
            | TranslationTwo         | TranslationTwo.php |
            | TranslationTwo.        | TranslationTwo.php |
            | TranslationTwo.        | TranslationTwo.php |
            | TranslationTwo...      | TranslationTwo.php |
            | TranslationTwo...      | TranslationTwo.php |
            +------------------------+--------------------+
            TEXT_WRAP;
    }

    private function assertTranslationsExistAndHaveTranslatedKeys(): void
    {
        $this->assertFileExists(self::$languageTestPath . self::$locale . '/TranslationOne.php');
        $this->assertFileExists(self::$languageTestPath . self::$locale . '/TranslationThree.php');
        $this->assertFileExists(self::$languageTestPath . self::$locale . '/Translation-Four.php');
        $this->assertFileExists(self::$languageTestPath . self::$locale . '/TranslationFive.php');
        $this->assertFileExists(self::$languageTestPath . self::$locale . '/Статья.php');

        $translationOneKeys      = require self::$languageTestPath . self::$locale . '/TranslationOne.php';
        $translationThreeKeys    = require self::$languageTestPath . self::$locale . '/TranslationThree.php';
        $translationFourKeys     = require self::$languageTestPath . self::$locale . '/Translation-Four.php';
        $translationFiveKeys     = require self::$languageTestPath . self::$locale . '/TranslationFive.php';
        $translationCyrillicKeys = require self::$languageTestPath . self::$locale . '/Статья.php';

        $this->assertSame($translationOneKeys, $this->getActualTranslationOneKeys());
        $this->assertSame($translationThreeKeys, $this->getActualTranslationThreeKeys());
        $this->assertSame($translationFourKeys, $this->getActualTranslationFourKeys());
        $this->assertSame($translationFiveKeys, $this->getActualTranslationFiveKeys());
        $this->assertSame($translationCyrillicKeys, $this->getActualTranslationCyrillicKeys());
    }

    private function makeLocaleDirectory(): void
    {
        @mkdir(self::$languageTestPath . self::$locale, 0755, true);
    }

    private function clearGeneratedFiles(): void
    {
        $filePaths = [
            self::$languageTestPath . self::$locale . '/TranslationOne.php',
            self::$languageTestPath . self::$locale . '/TranslationThree.php',
            self::$languageTestPath . self::$locale . '/Translation-Four.php',
            self::$languageTestPath . self::$locale . '/TranslationFive.php',
            self::$languageTestPath . self::$locale . '/Статья.php',
            self::$languageTestPath . self::$locale,
            self::$languageTestPath . '/test_locale_incorrect',
            ROOTPATH . 'tests/_support/Sources/Language/' . self::$locale . '/TranslationOne.php',
            ROOTPATH . 'tests/_support/Sources/Language/' . self::$locale . '/TranslationThree.php',
            ROOTPATH . 'tests/_support/Sources/Language/' . self::$locale . '/Translation-Four.php',
            ROOTPATH . 'tests/_support/Sources/Language/' . self::$locale . '/TranslationFive.php',
            ROOTPATH . 'tests/_support/Sources/Language/' . self::$locale . '/Статья.php',
            ROOTPATH . 'tests/_support/Sources/Language/' . self::$locale,
            ROOTPATH . 'tests/_support/Sources/Language/',
        ];

        foreach ($filePaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }

            if (is_dir($path)) {
                rmdir($path);
            }
        }
    }
}
