<?php

declare(strict_types=1);

use CodeIgniter\CodingStandard\CodeIgniter4;
use Nexus\CsConfig\Factory;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->files()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

$options = [
    'cacheFile' => './build/.php-cs-fixer.cache',
    'finder'    => $finder,
];

return Factory::create(new CodeIgniter4(), [], $options)->forProjects();
