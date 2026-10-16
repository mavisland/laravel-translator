<?php

declare(strict_types=1);

namespace Translator\Tests;

use PHPUnit\Framework\TestCase;
use Translator\Infra\LaravelTranslationRepository;
use Translator\Translator\ConfigLoader;
use Translator\Translator\Translation;

final class TranslationPreservationTest extends TestCase
{
    public function testSynchronizingMissingKeysPreservesExistingJsonTranslations(): void
    {
        $directory = sys_get_temp_dir() . '/translator-regression-' . bin2hex(random_bytes(8));
        mkdir($directory, 0775, true);

        try {
            file_put_contents($directory . '/en.json', json_encode([
                'Adres' => 'Address',
                'Ana Sayfa' => 'Home',
                'validation.required' => 'Required field',
            ], JSON_THROW_ON_ERROR));

            $config = $this->config($directory);
            $repository = new LaravelTranslationRepository($config);

            foreach (['Adres', 'Ana Sayfa', 'validation.required', 'Yeni Anahtar'] as $key) {
                $translation = new Translation($key, '');
                if (!$repository->exists($translation, 'en')) {
                    $repository->save($translation, 'en');
                }
            }

            $expected = [
                'Adres' => 'Address',
                'Ana Sayfa' => 'Home',
                'Yeni Anahtar' => '',
                'validation.required' => 'Required field',
            ];
            $actual = json_decode((string) file_get_contents($directory . '/en.json'), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($expected, $actual);

            $before = file_get_contents($directory . '/en.json');
            foreach (array_keys($expected) as $key) {
                $translation = new Translation($key, '');
                if (!$repository->exists($translation, 'en')) {
                    $repository->save($translation, 'en');
                }
            }
            self::assertSame($before, file_get_contents($directory . '/en.json'));
            self::assertFileDoesNotExist($directory . '/en/validation.php');
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($directory);
        }
    }

    private function config(string $directory): ConfigLoader
    {
        return new class ($directory) implements ConfigLoader {
            public function __construct(private string $directory)
            {
            }

            public function languages(): array { return ['tr', 'en']; }
            public function defaultLanguage(): string { return 'tr'; }
            public function useKeysAsDefaultValue(): bool { return false; }
            public function directories(): array { return []; }
            public function output(): string { return $this->directory; }
            public function extensions(): array { return ['php']; }
            public function functions(): array { return ['__']; }
        };
    }
}
