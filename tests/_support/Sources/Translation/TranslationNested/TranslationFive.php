<?php

declare(strict_types=1);

namespace Neznaika0\LangFinder\Tests\Support\Sources\Translation\TranslationNested;

class TranslationFive
{
    public function list(): void
    {
        $args = ['add', 'edit'];

        lang('TranslationFive.action');
        lang('TranslationFive.action', [], 'en');
        lang('TranslationFive.action', ['add', 'edit']);
        lang('TranslationFive.action', ['create', 'delete', 'patch']);
        lang('TranslationFive.action', ['page' => 150, 'filter' => 'name', 'search' => 'John']);

        lang('TranslationFive.users.action');
        lang('TranslationFive.users.action', ['search' => 'John', 'page' => 150, 'filter' => 'name']);
        lang('TranslationFive.users.action', ['create', 'patch', 'delete']);
        lang('TranslationFive.users.action', ['edit', 'add']);
        lang('TranslationFive.users.action', []);
        lang('TranslationFive.users.action', [], 'ru');

        lang('TranslationFive.users.post', $args);
        lang('TranslationFive.users.post', ['list', 'page' => 150]);
        lang('TranslationFive.users.post', $args);
        lang('TranslationFive.users.post', (static fn () => ['patch'])());
        lang('TranslationFive.users.post', [], 'de');

        lang('Статья.список.действие', $args);
        lang('Статья.список.действие', ['показать', 'page' => 150]);
        lang('Статья.список.действие', $args);
        lang('Статья.список.действие', (static fn () => ['обновить'])());
        lang('Статья.список.действие', [], 'de');
    }
}
