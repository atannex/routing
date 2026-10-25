<?php

declare(strict_types=1);

namespace Atannex\Routing\Discovery;

use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Finds the classes declared in a directory.
 *
 * It reads each file's namespace and class name from the source instead of
 * guessing them from PSR-4 paths, so any layout works (and files that hold
 * no class are skipped without being loaded).
 */
final class ClassFinder
{
    /**
     * @param  list<string>  $patterns  file name globs, e.g. ["*Controller.php"]
     * @param  list<string>  $exclude  globs matched against the path relative to $directory
     * @return list<class-string>
     */
    public function find(string $directory, array $patterns = ['*.php'], array $exclude = []): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $finder = Finder::create()->files()->in($directory)->name($patterns)->sortByName();
        $classes = [];

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());

            if ($exclude !== [] && Str::is($exclude, $relative)) {
                continue;
            }

            $class = $this->classIn((string) file_get_contents($file->getRealPath()));

            if ($class !== null && class_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /** The fully qualified name of the first class declared in the source, if any. */
    public function classIn(string $source): ?string
    {
        if (! preg_match('/^\s*(?:(?:final|abstract|readonly)\s+)*class\s+(\w+)/m', $source, $class)) {
            return null;
        }

        $namespace = preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $source, $match) ? $match[1].'\\' : '';

        return $namespace.$class[1];
    }
}
