<?php

declare(strict_types=1);

namespace Translator\Framework;

use Illuminate\Support\ServiceProvider;
use Translator\Translator\ConfigLoader;
use Translator\Translator\TranslationRepository;

/**
 * @codeCoverageIgnore
 */
class TranslatorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([TranslatorCommand::class, TranslatorUnusedCommand::class]);
        }

        $this->setupConfigs();
        $this->setupContainer();
    }

    private function setupConfigs(): void
    {
        $default = __DIR__ . '/config.php';
        $custom = base_path("config/translator.php");

        $this->mergeConfigFrom($default, 'translator');
        $this->publishes([$default => $custom], 'translator-config');
    }

    private function setupContainer(): void
    {
        $configLoader = config('translator.container.config_loader');
        $repository = config('translator.container.translation_repository');

        if (!is_string($configLoader) || !is_string($repository)) {
            throw new \InvalidArgumentException('Translator container bindings must be class names.');
        }

        $this->app->bind(ConfigLoader::class, $configLoader);
        $this->app->bind(TranslationRepository::class, $repository);
    }
}
