<?php

declare(strict_types = 1);

use Translator\Framework\LaravelConfigLoader;
use Translator\Infra\LaravelTranslationRepository;

return [
    'languages' => ['pt-br', 'es'],
    'directories' => [
        app_path(),
        resource_path('views'),
    ],
    'output' => lang_path(),
    'extensions' => ['php'],
    'functions' => ['lang', '__'],
    'container' => [
        'config_loader' => LaravelConfigLoader::class,
        'translation_repository' => LaravelTranslationRepository::class,
    ],
];
