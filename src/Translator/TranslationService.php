<?php

declare(strict_types=1);

namespace Translator\Translator;

class TranslationService
{
    private ConfigLoader $config;
    private TranslationScanner $scanner;
    private TranslationRepository $repository;

    public function __construct(ConfigLoader $config, TranslationScanner $scanner, TranslationRepository $repository)
    {
        $this->config = $config;
        $this->scanner = $scanner;
        $this->repository = $repository;
    }

    /**
     * @throws \Translator\Translator\Exception\InvalidDirectoriesConfiguration
     * @throws \Translator\Translator\Exception\InvalidExtensionsConfiguration
     */
    public function scanAndSaveNewKeys(): void
    {
        $directories = $this->config->directories();
        $extensions = $this->config->extensions();
        $functions = $this->config->functions();

        $translations = $this->scanner->scan($extensions, $directories, $functions);

        $this->storeTranslations($translations);
    }

    /**
     * @param Translation[] $translations
     */
    private function storeTranslations(array $translations): void
    {
        array_map(function (Translation $translation): void {
            $this->storeTranslation($translation);
        }, $translations);
    }

    private function storeTranslation(Translation $translation): void
    {
        $languages = $this->config->languages();

        array_map(function (string $language) use ($translation): void {
            if ($this->repository->exists($translation, $language)) {
                return;
            }

            $this->repository->save($translation, $language);
        }, $languages);
    }
}
