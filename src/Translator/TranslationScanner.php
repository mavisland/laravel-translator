<?php

declare(strict_types = 1);

namespace Translator\Translator;

use Translator\Translator\Exception\InvalidDirectoriesConfiguration;
use Translator\Translator\Exception\InvalidExtensionsConfiguration;
use Translator\Translator\Exception\InvalidFunctionsConfiguration;

class TranslationScanner
{
    /**
     * @param string[] $extensions
     * @param string[] $directories
     * @param string[] $functions
     * @return array<string, Translation>
     * @throws InvalidDirectoriesConfiguration
     * @throws InvalidExtensionsConfiguration
     * @throws InvalidFunctionsConfiguration
     */
    public function scan(array $extensions, array $directories, array $functions): array
    {
        if (empty($extensions)) {
            throw new InvalidExtensionsConfiguration();
        }

        if (empty($directories)) {
            throw new InvalidDirectoriesConfiguration();
        }

        if (empty($functions)) {
            throw new InvalidFunctionsConfiguration();
        }

        $translations = [];
        foreach ($directories as $directory) {
            $translations = array_merge(
                $translations,
                $this->scanDirectory($directory, array_values($extensions), array_values($functions))
            );
        }

        return $translations;
    }

    /**
     * @param string[] $functions
     * @return Translation[]
     */
    /** @param list<string> $extensions
     *  @param list<string> $functions
     *  @return array<string, Translation>
     */
    private function scanDirectory(string $path, array $extensions, array $functions): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $translations = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $matchesExtension = false;
            foreach ($extensions as $extension) {
                if (str_ends_with($file->getFilename(), '.' . ltrim($extension, '.'))) {
                    $matchesExtension = true;
                    break;
                }
            }

            if (!$matchesExtension) {
                continue;
            }

            $content = $this->getFileContent($file->getPathname());
            foreach ($functions as $function) {
                $translations = array_merge(
                    $translations,
                    $this->getKeysFromFunction($function, $content)
                );
            }
        }

        return $translations;
    }

    private function getFileContent(string $filePath): string
    {
        $content = file_get_contents($filePath) ?: '';

        return str_replace("\n", ' ', $content);
    }

    /**
     * @return array<string, Translation>
     */
    private function getKeysFromFunction(string $functionName, string $content): array
    {
        $function = preg_quote($functionName, '#');
        $pattern = '#(?<![\w$>:\x5c])' . $function
            . '\s*\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*(?=,|\))#s';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        $keys = [];
        foreach ($matches as $match) {
            $quote = $match[1];
            $raw = $match[2];
            $key = $quote === '"'
                ? stripcslashes($raw)
                : str_replace(["\\'", "\\\\"], ["'", "\\"], $raw);

            if ($key !== '') {
                $keys[$key] = new Translation($key, '');
            }
        }

        return $keys;
    }
}
