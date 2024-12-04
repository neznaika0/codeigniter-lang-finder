<?php

declare(strict_types=1);

namespace Neznaika0\LangFinder\Tests\Support\Sources\Translation\TranslationNested;

class TranslationFour
{
    public function list(): void
    {
        lang('TranslationOne.title');
        lang('TranslationOne.last_operation_success');

        lang('TranslationThree.alerts.created');
        lang('TranslationThree.alerts.failed_insert');

        lang('TranslationThree.formFields.new.name');
        lang('TranslationThree.formFields.new.short_tag');

        lang('Translation-Four.dashed.key-with-dash');
        lang('Translation-Four.dashed.key-with-dash-two');
    }
}
