<?php

declare(strict_types=1);

namespace Translator\Infra;

use Translator\Translator\ConfigLoader;
use Translator\Translator\Translation;

class LaravelTranslationRepository extends LaravelJsonTranslationRepository
{
    public function __construct(private ConfigLoader $configuration)
    {
        parent::__construct($configuration);
    }

    public function exists(Translation $translation, string $language): bool
    {
        $target = $this->phpTarget($translation->getKey(), $language);

        if ($target === null) {
            return parent::exists($translation, $language);
        }

        [$filename, $keys] = $target;
        $data = $this->readPhp($filename);
        foreach ($keys as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) {
                return false;
            }
            $data = $data[$key];
        }

        return true;
    }

    public function save(Translation $translation, string $language): void
    {
        $target = $this->phpTarget($translation->getKey(), $language);

        if ($target === null) {
            parent::save($translation, $language);
            return;
        }

        if ($this->exists($translation, $language)) {
            throw new \Translator\Infra\Exception\UnableToSaveTranslationKeyAlreadyExists($translation, $language);
        }

        [$filename, $keys] = $target;
        (new PhpTranslationFileEditor())->add($filename, $keys);
    }

    /** @return array{string, list<string>}|null */
    private function phpTarget(string $key, string $language): ?array
    {
        if (!str_contains($key, '.')) {
            return null;
        }

        [$group, $nested] = explode('.', $key, 2);
        if (
            !preg_match('/^[a-zA-Z0-9_-]+$/D', $group) ||
            !preg_match('/^[a-zA-Z0-9_.-]+$/D', $nested) ||
            !preg_match('/^[a-zA-Z0-9_-]+$/D', $language)
        ) {
            return null;
        }

        return [
            rtrim($this->configuration->output(), '/') . "/{$language}/{$group}.php",
            explode('.', $nested),
        ];
    }

    /** @return array<array-key, mixed> */
    private function readPhp(string $filename): array
    {
        if (!is_file($filename)) {
            return [];
        }

        $data = require $filename;
        if (!is_array($data)) {
            throw new \RuntimeException("Translation file must return an array: {$filename}");
        }

        return $data;
    }
}
