<?php

declare(strict_types=1);

namespace Translator\Framework;

use Illuminate\Console\Command;
use Translator\Translator\UnusedTranslationFinder;

class TranslatorUnusedCommand extends Command
{
    protected $signature = 'translator:unused';
    protected $description = 'List possibly unused PHP and JSON translation keys without modifying files';

    public function handle(UnusedTranslationFinder $finder): int
    {
        $results = $finder->find();
        $total = 0;
        foreach ($results as $language => $keys) {
            $this->line($language . ': ' . count($keys) . ' possibly unused keys');
            foreach ($keys as $key) {
                $this->line('  ' . $key);
            }
            $total += count($keys);
        }
        $this->warn('Static analysis only: dynamic translation keys may be reported as unused. Nothing was deleted.');
        $this->info('Total: ' . $total);
        return self::SUCCESS;
    }
}
