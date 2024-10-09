<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Support\Services\Translation;

class TranslationTwo
{
    public function list(): void
    {
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
        // Empty in comments lang('') lang(' ')
    }
}
