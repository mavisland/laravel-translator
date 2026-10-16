<?php

declare(strict_types=1);

namespace Translator\Translator;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
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

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                throw new \RuntimeException('Unable to read source file: ' . $file->getPathname());
            }

            $keys = str_ends_with($file->getFilename(), '.php')
                && !str_ends_with($file->getFilename(), '.blade.php')
                ? $this->getPhpKeys($content, $functions)
                : $this->getTemplateKeys($content, $functions);
            $translations = array_merge($translations, $keys);
        }

        return $translations;
    }

    /**
     * @param list<string> $functions
     * @return array<string, Translation>
     */
    private function getPhpKeys(string $content, array $functions): array
    {
        try {
            $statements = (new ParserFactory())->createForNewestSupportedVersion()->parse($content);
        } catch (\PhpParser\Error $exception) {
            throw new \RuntimeException('Unable to parse PHP source: ' . $exception->getMessage(), 0, $exception);
        }

        $keys = [];
        $calls = (new NodeFinder())->findInstanceOf($statements ?? [], Node\Expr\FuncCall::class);
        foreach ($calls as $call) {
            if (!$call->name instanceof Node\Name || !in_array($call->name->toString(), $functions, true)) {
                continue;
            }

            $argument = $call->args[0] ?? null;
            if (!$argument instanceof Node\Arg || !$argument->value instanceof Node\Scalar\String_) {
                continue;
            }

            $key = $argument->value->value;
            if ($key !== '') {
                $keys[$key] = new Translation($key, '');
            }
        }

        return $keys;
    }

    /**
     * @param list<string> $functions
     * @return array<string, Translation>
     */
    private function getTemplateKeys(string $content, array $functions): array
    {
        $keys = [];
        foreach ($functions as $functionName) {
            $function = preg_quote($functionName, '#');
            $pattern = '#(?<![\\w$>:\\x5c])' . $function
                . '\\s*\\(\\s*([\\'"])((?:\\\\.|(?!\\1).)*)\\1\\s*(?=,|\\))#s';
            preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $raw = $match[2];
                $key = $match[1] === '"'
                    ? stripcslashes($raw)
                    : str_replace(["\\'", "\\\\"], ["'", "\\"], $raw);

                if ($key !== '') {
                    $keys[$key] = new Translation($key, '');
                }
            }
        }

        return $keys;
    }

}
