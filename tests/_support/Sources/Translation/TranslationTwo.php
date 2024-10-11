<?php

declare(strict_types=1);

namespace Neznaika0\LangFinder\Tests\Support\Sources\Translation;

class TranslationTwo
{
    public function list(): void
    {
        $langKey = 'Translation.Two.invalid_case';

        // Error language keys
        lang('TranslationTwo');
        lang(' ');
        lang('');
        lang('.invalid_key');
        lang('TranslationTwo.');
        lang('TranslationTwo...');
        lang('..invalid_nested_key..');
        lang('TranslationTwo');
        lang(' ');
        lang('');
        lang('.invalid_key');
        lang('TranslationTwo.');
        lang('TranslationTwo...');
        lang('..invalid_nested_key..');
        lang($langKey);
        // Empty in comments lang('') lang(' ')
    }
}
