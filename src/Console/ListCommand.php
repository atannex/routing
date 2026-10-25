<?php

declare(strict_types=1);

namespace Atannex\Routing\Console;

use Atannex\Routing\Definition\RouteDefinition;
use Atannex\Routing\Routing;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Lists the attribute routes in registration order, with where each was
 * declared and how it ranks.
 */
final class ListCommand extends Command
{
    protected $signature = 'routing:list
        {--path= : Only URIs containing this}
        {--name= : Only names containing this}
        {--controller= : Only controllers containing this}
        {--json : Output JSON}';

    protected $description = 'List the routes declared with attributes, in the order they are matched';

    public function handle(Routing $routing): int
    {
        $rows = [];

        foreach ($routing->routes() as $position => $route) {
            if (! $this->wanted($route)) {
                continue;
            }

            $rows[] = [
                'order' => $position + 1,
                'method' => implode('|', array_diff($route->methods, ['HEAD'])) ?: 'GET',
                'uri' => ($route->domain ? $route->domain.' ' : '').$route->uri(),
                'name' => $route->fullName() ?? '',
                'handler' => match ($route->kind) {
                    RouteDefinition::KIND_REDIRECT => '→ '.$route->redirect[0].' ('.$route->redirect[1].')',
                    default => class_basename($route->controller).($route->action === '__invoke' ? '' : '@'.$route->action),
                },
                'middleware' => implode(', ', $route->middleware),
                'priority' => $route->priority,
                'kind' => $route->kind,
                'source' => $route->source,
            ];
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($rows === []) {
            $this->components->info('No attribute routes found.');

            return self::SUCCESS;
        }

        $this->table(
            ['#', 'Method', 'URI', 'Name', 'Action', 'Middleware', 'Declared in'],
            array_map(fn (array $row) => [
                $row['order'].($row['priority'] ? ' ⇡'.$row['priority'] : ''),
                $row['method'],
                $row['uri'],
                $row['name'],
                $row['handler'],
                Str::limit($row['middleware'], 40),
                $row['source'],
            ], $rows),
        );

        $this->components->info(count($rows).' attribute routes'.($routing->isCached() ? ' (from the discovery cache)' : '').'.');

        return self::SUCCESS;
    }

    private function wanted(RouteDefinition $route): bool
    {
        foreach (['path' => $route->uri(), 'name' => (string) $route->fullName(), 'controller' => $route->controller] as $option => $value) {
            $filter = $this->option($option);

            if (is_string($filter) && $filter !== '' && ! str_contains(strtolower($value), strtolower($filter))) {
                return false;
            }
        }

        return true;
    }
}
