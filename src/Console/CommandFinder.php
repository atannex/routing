<?php

declare(strict_types=1);

namespace Atannex\Routing\Console;

use Atannex\Routing\Discovery\ClassFinder;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;

/**
 * Finds the console commands in extra directories (modules, src/...),
 * which Laravel only discovers in app/Console/Commands.
 */
final class CommandFinder
{
    /**
     * @param  list<string>  $directories
     * @return list<class-string<Command>>
     */
    public function find(array $directories): array
    {
        $finder = new ClassFinder;
        $commands = [];

        foreach ($directories as $directory) {
            foreach ($finder->find($directory) as $class) {
                if (is_subclass_of($class, Command::class) && (new ReflectionClass($class))->isInstantiable()) {
                    $commands[] = $class;
                }
            }
        }

        return array_values(array_unique($commands));
    }
}
