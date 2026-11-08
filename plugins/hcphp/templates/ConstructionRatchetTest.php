<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A ratchet on services, repositories, mappers and managers built inline instead of being
 * taken from the container, and on singletons reached through ::getInstance().
 *
 * Every `new XService(` outside the wiring gives its caller a private instance with its own
 * state, where the application means one shared, injected collaborator. A refactoring removes
 * them slice by slice; until it is done this holds each count at a ceiling, so no new ones creep
 * in. It fails both ways:
 *  - a count ABOVE its ceiling is a new inline construction: take the object from the container
 *    instead — a constructor parameter in a service or manager, $this->container in a controller,
 *    Application::getContainer() in a template, command or event;
 *  - a count BELOW its ceiling is progress: lower the ceiling to the new number in the same pull
 *    request, so it holds.
 *
 * Names are resolved the way PHP resolves them — namespace, `use` imports and aliases, qualified
 * and fully qualified names — because short names collide across bundles. Comments and strings
 * are not counted. A class named at run time (`new $class(`), `new static`/`self`/`parent` and
 * anonymous classes resolve to no name and are not counted.
 *
 * Copied from the hcphp plugin's templates/. Edit the five methods marked "configure" for this
 * application; the first run with empty ceilings lists the lines to paste.
 */
class ConstructionRatchetTest extends TestCase
{
    /**
     * Configure: the application root, relative to this file (tests/Unit/Architecture/).
     */
    protected function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * Configure: where to look, relative to the root.
     *
     * @return string[]
     */
    protected function scanPaths(): array
    {
        return ['application'];
    }

    /**
     * Configure: the wiring, where construction belongs, and third-party code.
     * A path prefix relative to the root; a directory ends with "/".
     *
     * @return string[]
     */
    protected function excludedPaths(): array
    {
        return ['application/lib/vendor/', 'application/events/Init.php'];
    }

    /**
     * Configure: which constructed classes are ratcheted, by their short name.
     */
    protected function classPattern(): string
    {
        return '/(Service|Repository|Mapper|Manager)$/';
    }

    /**
     * Configure: FQCN (or "FQCN::getInstance") => how many inline constructions are allowed
     * today. Anything not listed is allowed none. These numbers only ever go down.
     *
     * @return array<string, int>
     */
    protected function ceilings(): array
    {
        return [
        ];
    }

    public function testInlineConstructionsStayAtTheirCeilings(): void
    {
        $found = $this->countConstructions();
        $ceilings = $this->ceilings();
        $problems = [];
        $baseline = [];

        foreach ($found as $key => $files) {
            $count = array_sum($files);
            $ceiling = $ceilings[$key] ?? 0;

            if ($count > $ceiling) {
                $problems[] = sprintf(
                    '%s is built inline %d times, ceiling %d: %s. Take it from the container instead.',
                    $this->describe($key),
                    $count,
                    $ceiling,
                    $this->listFiles($files)
                );

                if (!isset($ceilings[$key])) {
                    $baseline[] = sprintf('            %s => %d,', var_export($key, true), $count);
                }
            }
        }

        foreach ($ceilings as $key => $ceiling) {
            $count = isset($found[$key]) ? array_sum($found[$key]) : 0;

            if ($count < $ceiling) {
                $problems[] = sprintf(
                    '%s is built inline %d times, ceiling %d. That is progress: lower its ceiling to %d.',
                    $this->describe($key),
                    $count,
                    $ceiling,
                    $count
                );
            }
        }

        $message = implode("\n", $problems);

        if ($baseline !== []) {
            $message .= "\n\nIf these are today's baseline, add to ceilings():\n" . implode("\n", $baseline);
        }

        self::assertSame([], $problems, $message);
    }

    /**
     * @return array<string, array<string, int>> key => [relative file => count], sorted
     */
    protected function countConstructions(): array
    {
        $root = rtrim($this->root(), '/');
        $found = [];

        foreach ($this->phpFiles($root) as $relative) {
            foreach ($this->constructionsIn((string)file_get_contents($root . '/' . $relative)) as $key) {
                $found[$key][$relative] = ($found[$key][$relative] ?? 0) + 1;
            }
        }

        ksort($found);

        foreach ($found as &$files) {
            ksort($files);
        }

        return $found;
    }

    /**
     * @return string[] relative paths
     */
    private function phpFiles(string $root): array
    {
        $files = [];

        foreach ($this->scanPaths() as $path) {
            if (!is_dir($root . '/' . $path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root . '/' . $path, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = substr($file->getPathname(), strlen($root) + 1);

                if (!$this->isExcluded($relative)) {
                    $files[] = $relative;
                }
            }
        }

        sort($files);

        return $files;
    }

    private function isExcluded(string $relative): bool
    {
        foreach ($this->excludedPaths() as $prefix) {
            if (strpos($relative, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[] one key per counted construction
     */
    protected function constructionsIn(string $source): array
    {
        $tokens = array_values(array_filter(
            token_get_all($source),
            static function ($token): bool {
                return !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
            }
        ));

        $namespace = '';
        $imports = [];
        $depth = 0;
        // Imports sit at the top level of a file, or one level down inside `namespace X { }`.
        $importDepth = 0;
        $keys = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
                continue;
            }

            if ($token === '}') {
                $depth--;
                continue;
            }

            if (!is_array($token)) {
                continue;
            }

            // PHP 7 spells the relative name `namespace\Foo` as T_NAMESPACE, T_NS_SEPARATOR: not a
            // declaration. (PHP 8 gives it a token of its own.)
            $next = $tokens[$i + 1] ?? null;

            if ($token[0] === T_NAMESPACE && !(is_array($next) && $next[0] === T_NS_SEPARATOR)) {
                $name = $this->nameAt($tokens, $i + 1);

                if ($name !== null) {
                    $namespace = trim($name[0], '\\');
                    $i = $name[1];
                } elseif ($next !== '{') {
                    continue;
                }

                $imports = [];
                $importDepth = ($tokens[$i + 1] ?? null) === '{' ? 1 : 0;
                continue;
            }

            // An import is a `use` at the import level. Inside a class it is a trait, and a
            // closure's `use (` captures variables; neither is an import.
            if ($token[0] === T_USE && $depth === $importDepth && ($tokens[$i + 1] ?? null) !== '(') {
                $i = $this->readImports($tokens, $i + 1, $imports);
                continue;
            }

            if ($token[0] === T_NEW) {
                $name = $this->nameAt($tokens, $i + 1);

                if ($name !== null) {
                    $class = $this->resolve($name[0], $namespace, $imports);

                    if ($class !== null && preg_match($this->classPattern(), $this->shortName($class))) {
                        $keys[] = $class;
                    }
                }

                continue;
            }

            if ($token[0] === T_DOUBLE_COLON
                && is_array($tokens[$i + 1] ?? null)
                && $tokens[$i + 1][0] === T_STRING
                && strtolower($tokens[$i + 1][1]) === 'getinstance'
                && ($tokens[$i + 2] ?? null) === '('
            ) {
                $name = $this->nameEndingAt($tokens, $i - 1);
                $class = $name === null ? null : $this->resolve($name, $namespace, $imports);

                if ($class !== null) {
                    $keys[] = $class . '::getInstance';
                }
            }
        }

        return $keys;
    }

    /**
     * Reads `use A\B;`, `use A\B as C;`, `use A\{B, C as D};` and lists of them into $imports
     * (lower-cased alias => FQCN). `use function` and `use const` are skipped.
     *
     * @param array $tokens
     * @param array<string, string> $imports
     *
     * @return int the index of the terminating ";"
     */
    private function readImports(array $tokens, int $i, array &$imports): int
    {
        $count = count($tokens);

        if (is_array($tokens[$i] ?? null) && in_array($tokens[$i][0], [T_FUNCTION, T_CONST], true)) {
            while ($i < $count && $tokens[$i] !== ';') {
                $i++;
            }

            return $i;
        }

        $prefix = '';

        while ($i < $count && $tokens[$i] !== ';') {
            $name = $this->nameAt($tokens, $i);

            if ($name === null) {
                if ($tokens[$i] === '}') {
                    $prefix = '';
                }

                $i++;
                continue;
            }

            [$text, $i] = $this->nameAt($tokens, $i);
            $i++;

            // A group: `use A\{` arrives as the name "A\" followed by "{".
            if (($tokens[$i] ?? null) === '{' || substr($text, -1) === '\\') {
                $prefix = rtrim($text, '\\') . '\\';
                continue;
            }

            $fqcn = trim($prefix . $text, '\\');
            $alias = $this->shortName($fqcn);

            if (is_array($tokens[$i] ?? null) && $tokens[$i][0] === T_AS) {
                $alias = $tokens[$i + 1][1];
                $i += 2;
            }

            $imports[strtolower($alias)] = $fqcn;
        }

        return $i;
    }

    /**
     * The class name starting at $i, as written, and the index of its last token; null when no
     * name starts there (a variable, `class`, `static`, `(`).
     *
     * @return array{0: string, 1: int}|null
     */
    private function nameAt(array $tokens, int $i): ?array
    {
        $name = '';
        $last = null;

        while (isset($tokens[$i]) && is_array($tokens[$i]) && in_array($tokens[$i][0], $this->nameTokens(), true)) {
            $name .= $tokens[$i][1];
            $last = $i;
            $i++;
        }

        if ($last === null || in_array(strtolower($name), ['static', 'self', 'parent', 'class'], true)) {
            return null;
        }

        return [$name, $last];
    }

    private function nameEndingAt(array $tokens, int $i): ?string
    {
        $name = '';

        while ($i >= 0 && is_array($tokens[$i]) && in_array($tokens[$i][0], $this->nameTokens(), true)) {
            $name = $tokens[$i][1] . $name;
            $i--;
        }

        return $name === '' || in_array(strtolower($name), ['static', 'self', 'parent'], true) ? null : $name;
    }

    /**
     * PHP 7 spells a name as T_STRING and T_NS_SEPARATOR tokens; PHP 8 has one token per name.
     *
     * @return int[]
     */
    private function nameTokens(): array
    {
        $tokens = [T_STRING, T_NS_SEPARATOR];

        foreach (['T_NAME_QUALIFIED', 'T_NAME_FULLY_QUALIFIED', 'T_NAME_RELATIVE'] as $constant) {
            if (defined($constant)) {
                $tokens[] = constant($constant);
            }
        }

        return $tokens;
    }

    /**
     * @param array<string, string> $imports
     */
    private function resolve(string $name, string $namespace, array $imports): ?string
    {
        if ($name === '') {
            return null;
        }

        if ($name[0] === '\\') {
            return ltrim($name, '\\');
        }

        if (stripos($name, 'namespace\\') === 0) {
            return ltrim($namespace . '\\' . substr($name, 10), '\\');
        }

        $parts = explode('\\', $name);
        $first = strtolower($parts[0]);

        if (isset($imports[$first])) {
            $parts[0] = $imports[$first];

            return implode('\\', $parts);
        }

        return ltrim($namespace . '\\' . $name, '\\');
    }

    private function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    private function describe(string $key): string
    {
        $suffix = '::getInstance';

        return substr($key, -strlen($suffix)) === $suffix ? $key . '()' : 'new ' . $key . '(';
    }

    /**
     * @param array<string, int> $files
     */
    private function listFiles(array $files): string
    {
        $parts = [];

        foreach ($files as $file => $count) {
            $parts[] = sprintf('%s (%d)', $file, $count);
        }

        return implode(', ', $parts);
    }
}
