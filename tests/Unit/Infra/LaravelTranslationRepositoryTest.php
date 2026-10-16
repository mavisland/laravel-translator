<?php

declare(strict_types=1);

namespace Translator\Tests\Unit\Infra;

use PHPUnit\Framework\TestCase;
use Translator\Infra\LaravelTranslationRepository;
use Translator\Translator\ConfigLoader;
use Translator\Translator\Translation;

class LaravelTranslationRepositoryTest extends TestCase
{
    private string $directory;
    private LaravelTranslationRepository $repository;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/translator-php-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);

        $config = $this->createMock(ConfigLoader::class);
        $config->method('output')->willReturn($this->directory);
        $this->repository = new LaravelTranslationRepository($config);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function testCreatesMissingPhpTranslationFile(): void
    {
        $translation = new Translation('messages.title', '');
        self::assertFalse($this->repository->exists($translation, 'tr'));
        $this->repository->save($translation, 'tr');

        self::assertSame(['title' => ''], require $this->directory . '/tr/messages.php');
        self::assertTrue($this->repository->exists($translation, 'tr'));
    }

    public function testPreservesExistingValuesAndSortsNestedKeys(): void
    {
        mkdir($this->directory . '/tr');
        file_put_contents(
            $this->directory . '/tr/messages.php',
            "<?php return ['zulu' => 'Existing', 'form' => ['z' => 'Keep']];"
        );

        $this->repository->save(new Translation('messages.form.a', ''), 'tr');
        $this->repository->save(new Translation('messages.alpha', ''), 'tr');

        self::assertSame([
            'alpha' => '',
            'form' => ['a' => '', 'z' => 'Keep'],
            'zulu' => 'Existing',
        ], require $this->directory . '/tr/messages.php');
    }

    public function testDoesNotOverwriteExistingScalarWhenAddingNestedKey(): void
    {
        mkdir($this->directory . '/tr');
        file_put_contents($this->directory . '/tr/messages.php', "<?php return ['form' => 'Existing'];");

        $this->expectException(\RuntimeException::class);
        $this->repository->save(new Translation('messages.form.submit', ''), 'tr');
    }

    public function testCreatesJsonForUndottedKeys(): void
    {
        $this->repository->save(new Translation('Welcome', ''), 'en');

        self::assertSame(['Welcome' => ''], json_decode(
            file_get_contents($this->directory . '/en.json'),
            true
        ));
    }

    public function testKeepsCommentsWhenAddingMissingKeys(): void
    {
        mkdir($this->directory . '/tr');
        $filename = $this->directory . '/tr/messages.php';
        file_put_contents(
            $filename,
            <<<'PHP'
<?php

return [
    // Existing title translation
    'title' => 'Başlık',
    'zulu' => 'Son',
];
PHP
        );

        $this->repository->save(new Translation('messages.alpha', ''), 'tr');

        $contents = file_get_contents($filename);
        self::assertStringContainsString('// Existing title translation', $contents);
        self::assertSame([
            'alpha' => '',
            'title' => 'Başlık',
            'zulu' => 'Son',
        ], require $filename);
    }
}
