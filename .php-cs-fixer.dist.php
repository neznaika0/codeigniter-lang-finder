<?php

declare(strict_types=1);

use CodeIgniter\CodingStandard\CodeIgniter4;
use Nexus\CsConfig\Factory;

$options = [
    'cacheFile' => './build/.php-cs-fixer.cache',
];

return Factory::create(new CodeIgniter4(), [], $options)->forProjects();
