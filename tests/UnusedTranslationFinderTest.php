<?php

declare(strict_types=1);

namespace Translator\Tests;

use PHPUnit\Framework\TestCase;
use Translator\Translator\ConfigLoader;
use Translator\Translator\TranslationScanner;
use Translator\Translator\UnusedTranslationFinder;

final class UnusedTranslationFinderTest extends TestCase
{
    public function testReportsUnusedKeysAcrossJsonAndNestedPhpWithoutEditingFiles(): void
    {
        $root = sys_get_temp_dir() . '/translator-unused-' . bin2hex(random_bytes(8));
        $source = $root . '/app';
        $output = $root . '/lang';
        mkdir($source, 0775, true);
        mkdir($output . '/tr', 0775, true);
        mkdir($output . '/en', 0775, true);

        try {
            file_put_contents($source . '/example.php', "<?php\n__('Hello');\n__('messages.title');\n__('messages.nested.used');\n");
            file_put_contents($output . '/tr.json', json_encode([
                'Hello' => 'Merhaba',
                'Orphan' => 'Yetim',
            ], JSON_THROW_ON_ERROR));
            file_put_contents($output . '/en.json', json_encode([
                'Hello' => 'Hello',
                'Orphan' => 'Orphan',
            ], JSON_THROW_ON_ERROR));
            file_put_contents($output . '/tr/messages.php', "<?php\nreturn ['title' => 'Başlık', 'nested' => ['used' => 'Kullanım', 'old' => 'Eski']];\n");
            file_put_contents($output . '/en/messages.php', "<?php\nreturn ['title' => 'Title', 'nested' => ['used' => 'Used', 'old' => 'Old']];\n");

            $before = [];
            foreach (glob($output . '/*.*') ?: [] as $path) {
                $before[$path] = file_get_contents($path);
            }
            foreach (glob($output . '/*/*.php') ?: [] as $path) {
                $before[$path] = file_get_contents($path);
            }

            $config = new class ($source, $output) implements ConfigLoader {
                public function __construct(private string $source, private string $output)
                {
                }

                public function languages(): array { return ['tr', 'en']; }
                public function defaultLanguage(): string { return 'tr'; }
                public function useKeysAsDefaultValue(): bool { return false; }
                public function directories(): array { return [$this->source]; }
                public function output(): string { return $this->output; }
                public function extensions(): array { return ['php']; }
                public function functions(): array { return ['__']; }
            };

            $result = (new UnusedTranslationFinder($config, new TranslationScanner()))->find();
            self::assertSame(['Orphan', 'messages.nested.old'], $result['tr']);
            self::assertSame(['Orphan', 'messages.nested.old'], $result['en']);

            foreach ($before as $path => $content) {
                self::assertSame($content, file_get_contents($path));
            }
        } finally {
            foreach (glob($output . '/*/*.php') ?: [] as $path) {
                unlink($path);
            }
            foreach (glob($output . '/*.*') ?: [] as $path) {
                unlink($path);
            }
            foreach (glob($source . '/*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($output . '/tr');
            rmdir($output . '/en');
            rmdir($output);
            rmdir($source);
            rmdir($root);
        }
    }
}
