<?php

declare(strict_types=1);

namespace Neznaika0\LangFinder\Commands\Translation;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use CodeIgniter\Helpers\Array\ArrayHelper;
use Config\App;
use InvalidArgumentException;
use Neznaika0\LangFinder\Helpers\Array\ArrayHelper as ExtraArrayHelper;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * @see \Neznaika0\LangFinder\Commands\Translation\LocalizationFinderTest
 */
class LocalizationFinder extends BaseCommand
{
    public const ARGS_SEPARATOR = '...';

    protected $group       = 'Translation';
    protected $name        = 'lang:find';
    protected $description = 'Find and save available phrases to translate.';
    protected $usage       = 'lang:find [options]';
    protected $arguments   = [];
    protected $options     = [
        '--locale'   => 'Specify locale (en, ru, etc.) to save files.',
        '--dir'      => 'Directory to search for translations relative to APPPATH.',
        '--show-new' => 'Show only new translations in table. Does not write to files.',
        '--verbose'  => 'Output detailed information.',
    ];

    /**
     * Flag for output detailed information
     */
    private bool $verbose = false;

    /**
     * Flag for showing only translations, without saving
     */
    private bool $showNew = false;

    /**
     * Starting directory of the translation search
     */
    private string $currentDir = APPPATH;

    /**
     * Output directory for translations
     */
    private string $languagePath = APPPATH . 'Language' . DIRECTORY_SEPARATOR;

    public function __construct(LoggerInterface $logger, Commands $commands)
    {
        parent::__construct($logger, $commands);

        if (ENVIRONMENT === 'testing') {
            $this->currentDir   = SUPPORTPATH . 'Sources' . DIRECTORY_SEPARATOR;
            $this->languagePath = SUPPORTPATH . 'Language' . DIRECTORY_SEPARATOR;
        }
    }

    public function run(array $params)
    {
        $this->verbose = array_key_exists('verbose', $params);
        $this->showNew = array_key_exists('show-new', $params);
        $optionLocale  = $params['locale'] ?? null;
        $optionDir     = $params['dir'] ?? null;
        $currentLocale = config(App::class)->defaultLocale;

        if (is_string($optionLocale)) {
            if (! in_array($optionLocale, config(App::class)->supportedLocales, true)) {
                CLI::error(
                    'Error: "' . $optionLocale . '" is not supported. Supported locales: '
                    . implode(', ', config(App::class)->supportedLocales)
                );

                return EXIT_USER_INPUT;
            }

            $currentLocale = $optionLocale;
        }

        if (is_string($optionDir)) {
            $tempCurrentDir = realpath($this->currentDir . $optionDir);

            if ($tempCurrentDir === false) {
                CLI::error('Error: Directory must be located in "' . $this->currentDir . '"');

                return EXIT_USER_INPUT;
            }

            if ($this->isSubDirectory($tempCurrentDir, $this->languagePath)) {
                CLI::error('Error: Directory "' . $this->languagePath . '" restricted to scan.');

                return EXIT_USER_INPUT;
            }

            $this->currentDir = $tempCurrentDir;
            $this->currentDir = rtrim($this->currentDir, '/') . DIRECTORY_SEPARATOR;
        }

        $this->process($this->currentDir, $currentLocale);

        CLI::write('All operations done!');

        return EXIT_SUCCESS;
    }

    private function process(string $currentDir, string $currentLocale): void
    {
        $this->writeIsVerbose('Directory to scan: ' . $currentDir);

        $tableRows    = [];
        $countNewKeys = 0;

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($currentDir));
        $files    = iterator_to_array($iterator, true);
        ksort($files);

        [
            'foundLanguageKeys' => $foundLanguageKeys,
            'badLanguageKeys'   => $badLanguageKeys,
            'countFiles'        => $countFiles
        ] = $this->findLanguageKeysInFiles($files);
        ksort($foundLanguageKeys);

        $languageDiff        = [];
        $languageFoundGroups = array_unique(array_keys($foundLanguageKeys));

        foreach ($languageFoundGroups as $langFileName) {
            $languageStoredKeys = [];
            $languageFilePath   = $this->languagePath . DIRECTORY_SEPARATOR . $currentLocale . DIRECTORY_SEPARATOR . $langFileName . '.php';

            if (is_file($languageFilePath)) {
                // Load old localization
                $languageStoredKeys = require $languageFilePath;
            }

            $languageDiff = ArrayHelper::recursiveDiff($foundLanguageKeys[$langFileName], $languageStoredKeys);
            $countNewKeys += ArrayHelper::recursiveCount($languageDiff);

            if ($this->showNew) {
                $tableRows = array_merge($this->arrayToTableRows($langFileName, $languageDiff), $tableRows);
            } else {
                $newLanguageKeys = array_replace_recursive($foundLanguageKeys[$langFileName], $languageStoredKeys);

                if ($languageDiff !== []) {
                    if (file_put_contents($languageFilePath, $this->templateFile($newLanguageKeys)) === false) {
                        $this->writeIsVerbose('Lang file ' . $langFileName . ' (error write).', 'red');
                    } else {
                        $this->writeIsVerbose('Lang file "' . $langFileName . '" successful updated!', 'green');
                    }
                }
            }
        }

        if ($this->showNew && $tableRows !== []) {
            sort($tableRows);
            CLI::table($tableRows, ['File', 'Key']);
        }

        if (! $this->showNew && $countNewKeys > 0) {
            CLI::write('Note: You need to run your linting tool to fix coding standards issues.', 'white', 'red');
        }

        $this->writeIsVerbose('Files found: ' . $countFiles);
        $this->writeIsVerbose('New translates found: ' . $countNewKeys);
        $this->writeIsVerbose('Bad translates found: ' . count($badLanguageKeys));

        if ($this->verbose && $badLanguageKeys !== []) {
            $tableBadRows = [];

            foreach ($badLanguageKeys as $value) {
                $tableBadRows[] = [$value[1], $value[0]];
            }

            ArrayHelper::sortValuesByNatural($tableBadRows, 0);

            CLI::table($tableBadRows, ['Bad Key', 'Filepath']);
        }
    }

    /**
     * @param SplFileInfo|string $file
     *
     * @return array<string, array>
     */
    private function findTranslationsInFile($file): array
    {
        $foundLanguageKeys = [];
        $badLanguageKeys   = [];

        if (is_string($file) && is_file($file)) {
            $file = new SplFileInfo($file);
        }

        $fileContent = file_get_contents($file->getRealPath());

        if ($fileContent === false || $fileContent === '') {
            return compact('foundLanguageKeys', 'badLanguageKeys');
        }

        $parser     = (new ParserFactory())->createForVersion(PhpVersion::fromString('8.1'));
        $nodeFinder = new NodeFinder();

        $stmts = $parser->parse($fileContent);

        /**
         * @var list<FuncCall>
         */
        $functions = $nodeFinder->find($stmts, static function (Node $node) {
            if ($node instanceof FuncCall && $node->name instanceof Name && $node->name->name === 'lang') {
                return true;
            }
        });

        if ($functions === []) {
            return compact('foundLanguageKeys', 'badLanguageKeys');
        }

        foreach ($functions as $function) {
            /**
             * @var ?String_
             */
            $langStringKey = isset($function?->args[0]->value) ? $function->args[0]->value : null;

            // We are only looking for string keys.
            // Expressions will be omitted.args.
            // Right     = File.key
            // Incorrect = File. ' . $obj->getKey() . '
            if ($langStringKey === null || ! $langStringKey instanceof String_) {
                continue;
            }

            $langKey    = $langStringKey->value;
            $phraseKeys = explode('.', $langKey);

            // Language key not have Filename/Lang key or contains many dots
            if (count($phraseKeys) < 2 || str_contains($langKey, '..')) {
                $badLanguageKeys[] = [mb_substr($file->getRealPath(), mb_strlen($this->currentDir)), $langKey];

                continue;
            }

            $languageFileName   = array_shift($phraseKeys);
            $isEmptyNestedArray = ($languageFileName !== '' && $phraseKeys[0] === '')
                || ($languageFileName === '' && $phraseKeys[0] !== '')
                || ($languageFileName === '' && $phraseKeys[0] === '');

            if ($isEmptyNestedArray) {
                $badLanguageKeys[] = [mb_substr($file->getRealPath(), mb_strlen($this->currentDir)), $langKey];

                continue;
            }

            $langKeyPossibleArgs = [];
            $foundLanguageKeys[$languageFileName] ??= [];

            /**
             * @var ?Array_
             */
            $langKeyArgs = $function->args[1]->value ?? null;

            if ($langKeyArgs !== null) {
                if ($langKeyArgs instanceof Array_) {
                    foreach ($langKeyArgs->items as $langKeyIndex => $langKeyArg) {
                        $langKeyPossibleArgs[] = $langKeyArg->key->value ?? $langKeyIndex;
                    }
                } else {
                    $langKeyPossibleArgs[] = '?';
                }
            }

            if (count($phraseKeys) === 1) {
                if (isset($foundLanguageKeys[$languageFileName][$phraseKeys[0]])) {
                    $foundLanguageKeys[$languageFileName][$phraseKeys[0]] = $this->appendLangArgs($foundLanguageKeys[$languageFileName][$phraseKeys[0]], $langKeyPossibleArgs);
                } else {
                    $foundLanguageKeys[$languageFileName][$phraseKeys[0]] = $this->appendLangArgs($langKey, $langKeyPossibleArgs);
                }
            } else {
                if (ExtraArrayHelper::getNestedValue($foundLanguageKeys[$languageFileName], $phraseKeys)) {
                    $childKeys = ExtraArrayHelper::setNestedValue($phraseKeys, $this->appendLangArgs(ExtraArrayHelper::getNestedValue($foundLanguageKeys[$languageFileName], $phraseKeys), $langKeyPossibleArgs));
                } else {
                    $childKeys = ExtraArrayHelper::setNestedValue($phraseKeys, $this->appendLangArgs($langKey, $langKeyPossibleArgs));
                }

                $foundLanguageKeys[$languageFileName] = array_replace_recursive($foundLanguageKeys[$languageFileName], $childKeys);
            }
        }

        return compact('foundLanguageKeys', 'badLanguageKeys');
    }

    /**
     * Possible placeholders are stored as split string "File.key[separator]{0} {1} {var}"
     */
    private function appendLangArgs(string $langValue, array $langArgs): string
    {
        if ($langArgs === []) {
            return $langValue;
        }

        array_walk($langArgs, static function (&$value) {
            $value = '{' . $value . '}';
        });

        $oldLangArgs = explode(self::ARGS_SEPARATOR, $langValue);

        if (isset($oldLangArgs[1])) {
            $langArgs = array_merge($langArgs, explode(' ', $oldLangArgs[1]));
            $langArgs = array_unique($langArgs);
            natsort($langArgs);
        }

        return $oldLangArgs[0] . ($langArgs !== [] ? self::ARGS_SEPARATOR . implode(' ', $langArgs) : '');
    }

    private function isIgnoredFile(SplFileInfo $file): bool
    {
        if ($file->isDir() || $this->isSubDirectory((string) $file->getRealPath(), $this->languagePath)) {
            return true;
        }

        return $file->getExtension() !== 'php';
    }

    private function templateFile(array $language = []): string
    {
        if ($language !== []) {
            $languageArrayString = var_export($language, true);

            $code = <<<PHP
                <?php

                return {$languageArrayString};

                PHP;

            return $this->replaceArraySyntax($code);
        }

        return <<<PHP
            <?php

            return [];

            PHP;
    }

    private function replaceArraySyntax(string $code): string
    {
        $tokens    = token_get_all($code);
        $newTokens = $tokens;

        foreach ($tokens as $i => $token) {
            if (is_array($token)) {
                [$tokenId, $tokenValue] = $token;

                // Replace "array ("
                if (
                    $tokenId === T_ARRAY
                    && $tokens[$i + 1][0] === T_WHITESPACE
                    && $tokens[$i + 2] === '('
                ) {
                    $newTokens[$i][1]     = '[';
                    $newTokens[$i + 1][1] = '';
                    $newTokens[$i + 2]    = '';
                }

                // Replace indent
                if ($tokenId === T_WHITESPACE && preg_match('/\n([ ]+)/u', $tokenValue, $matches)) {
                    $newTokens[$i][1] = "\n{$matches[1]}{$matches[1]}";
                }
            } // Replace ")"
            elseif ($token === ')') {
                $newTokens[$i] = ']';
            }
        }

        $output = '';

        foreach ($newTokens as $token) {
            $output .= $token[1] ?? $token;
        }

        return $output;
    }

    /**
     * Convert multi arrays to specific CLI table rows (flat array)
     */
    private function arrayToTableRows(string $langFileName, array $array): array
    {
        $rows = [];

        foreach ($array as $value) {
            if (is_array($value)) {
                $rows = array_merge($rows, $this->arrayToTableRows($langFileName, $value));

                continue;
            }

            if (is_string($value)) {
                $rows[] = [$langFileName, $value];
            }
        }

        return $rows;
    }

    /**
     * Show details in the console if the flag is set
     */
    private function writeIsVerbose(string $text = '', ?string $foreground = null, ?string $background = null): void
    {
        if ($this->verbose) {
            CLI::write($text, $foreground, $background);
        }
    }

    private function isSubDirectory(string $directory, string $rootDirectory): bool
    {
        if ($directory === '' || $rootDirectory === '') {
            throw new InvalidArgumentException('The "$directory" or "$rootDirectory" parameters should not be an empty value');
        }

        return str_starts_with($directory, $rootDirectory);
    }

    /**
     * @param list<SplFileInfo> $files
     *
     * @return         array<string, array|int>
     * @phpstan-return array{'foundLanguageKeys': array<string, array<string, string>>, 'badLanguageKeys': array<int, array<int, string>>, 'countFiles': int}
     */
    private function findLanguageKeysInFiles(array $files): array
    {
        $foundLanguageKeys = [];
        $badLanguageKeys   = [];
        $countFiles        = 0;

        foreach ($files as $file) {
            if ($this->isIgnoredFile($file)) {
                continue;
            }

            $this->writeIsVerbose('File found: ' . mb_substr($file->getRealPath(), mb_strlen($this->currentDir)));
            $countFiles++;

            $findInFile = $this->findTranslationsInFile($file);

            $foundLanguageKeys = array_replace_recursive($findInFile['foundLanguageKeys'], $foundLanguageKeys);
            $badLanguageKeys   = array_merge($findInFile['badLanguageKeys'], $badLanguageKeys);
        }

        return compact('foundLanguageKeys', 'badLanguageKeys', 'countFiles');
    }
}
