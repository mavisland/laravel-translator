<?php

declare(strict_types=1);

namespace Translator\Translator;

final class UnusedTranslationFinder
{
    public function __construct(
        private ConfigLoader $config,
        private TranslationScanner $scanner
    ) {
    }

    /** @return array<string, list<string>> */
    public function find(): array
    {
        $used = $this->scanner->scan(
            $this->config->extensions(),
            $this->config->directories(),
            $this->config->functions()
        );
        $result = [];
        $languages = array_unique([...$this->config->languages(), $this->config->defaultLanguage()]);
        foreach ($languages as $language) {
            if ($language === '') {
                continue;
            }
            $keys = [];
            $root = rtrim($this->config->output(), '/');
            $json = $root . '/' . $language . '.json';
            if (is_file($json)) {
                $data = json_decode((string) file_get_contents($json), true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($data)) {
                    throw new \RuntimeException("Invalid JSON translations: {$json}");
                }
                foreach (array_keys($data) as $key) {
                    if (is_string($key)) {
                        $keys[$key] = true;
                    }
                }
            }

            $directory = $root . '/' . $language;
            if (is_dir($directory)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                        continue;
                    }
                    $relative = substr($file->getPathname(), strlen($directory) + 1);
                    $group = str_replace('/', '.', substr($relative, 0, -4));
                    $data = require $file->getPathname();
                    if (!is_array($data)) {
                        throw new \RuntimeException('Translation file must return an array: ' . $file->getPathname());
                    }
                    $this->collect($data, $group, $keys);
                }
            }

            $unused = array_values(array_diff(array_keys($keys), array_keys($used)));
            sort($unused, SORT_STRING);
            $result[$language] = $unused;
        }
        return $result;
    }

    /** @param array<array-key, mixed> $data
     *  @param array<string, bool> $keys
     */
    private function collect(array $data, string $prefix, array &$keys): void
    {
        foreach ($data as $key => $value) {
            $name = $prefix . '.' . $key;
            if (is_array($value)) {
                $this->collect($value, $name, $keys);
            } else {
                $keys[$name] = true;
            }
        }
    }
}
