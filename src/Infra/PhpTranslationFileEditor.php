<?php declare(strict_types=1);

namespace Translator\Infra;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

final class PhpTranslationFileEditor
{
    /** @param list<string> $keys */
    public function add(string $filename, array $keys): void
    {
        $source = is_file($filename) ? file_get_contents($filename) : "<?php\n\nreturn [];\n";
        if ($source === false) {
            throw new \RuntimeException("Unable to read translation file: {$filename}");
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $oldStatements = $parser->parse($source);
        if ($oldStatements === null) {
            throw new \RuntimeException("Unable to parse translation file: {$filename}");
        }

        $return = null;
        foreach ($oldStatements as $statement) {
            if ($statement instanceof Node\Stmt\Return_) {
                $return = $statement;
                break;
            }
        }

        if (!$return || !$return->expr instanceof Node\Expr\Array_) {
            throw new \RuntimeException("Translation file must return a literal PHP array: {$filename}");
        }

        $tokens = $parser->getTokens();
        $traverser = new NodeTraverser(new CloningVisitor());
        $statements = $traverser->traverse($oldStatements);

        $array = null;
        foreach ($statements as $statement) {
            if ($statement instanceof Node\Stmt\Return_) {
                $array = $statement->expr;
                break;
            }
        }

        if (!$array instanceof Node\Expr\Array_) {
            throw new \RuntimeException("Translation file must return a literal PHP array: {$filename}");
        }

        $this->insert($array, $keys);
        $code = (new Standard())->printFormatPreserving($statements, $oldStatements, $tokens);

        $directory = dirname($filename);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create translation directory: {$directory}");
        }

        if (file_put_contents($filename, $code, LOCK_EX) === false) {
            throw new \RuntimeException("Unable to write translation file: {$filename}");
        }
    }

    /** @param list<string> $keys */
    private function insert(Node\Expr\Array_ $array, array $keys): void
    {
        $key = array_shift($keys);
        if ($key === null) {
            return;
        }

        foreach ($array->items as $item) {
            if (!$item instanceof Node\Expr\ArrayItem ||
                !$item->key instanceof Node\Scalar\String_ ||
                $item->key->value !== $key) {
                continue;
            }

            if ($keys === []) {
                return;
            }

            if (!$item->value instanceof Node\Expr\Array_) {
                throw new \RuntimeException("Cannot insert nested translation under scalar key: {$key}");
            }

            $this->insert($item->value, $keys);
            return;
        }

        $value = $keys === []
            ? new Node\Scalar\String_('')
            : new Node\Expr\Array_([]);

        if ($value instanceof Node\Expr\Array_) {
            $this->insert($value, $keys);
        }

        $array->items[] = new Node\Expr\ArrayItem($value, new Node\Scalar\String_($key));

        // Reorder existing nodes rather than recreating them, retaining their comments.
        usort($array->items, static function (Node\Expr\ArrayItem $a, Node\Expr\ArrayItem $b): int {
            if (!$a->key instanceof Node\Scalar\String_ || !$b->key instanceof Node\Scalar\String_) {
                return 0;
            }

            return strcmp($a->key->value, $b->key->value);
        });
    }
}
