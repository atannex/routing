<?php

declare(strict_types=1);

namespace Atannex\Routing\Console;

use Illuminate\Console\Command;
use Illuminate\Routing\RedirectController;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use ReflectionClass;

/**
 * Prints the attributes that declare the routes from route files, grouped
 * by controller, ready to paste above each class or method. Closure routes
 * cannot move into attributes and are listed apart.
 */
final class ConvertCommand extends Command
{
    protected $signature = 'routing:convert
        {--path= : Only URIs containing this}
        {--base=web : Middleware the scanned directory already adds (comma separated), left out of the output}';

    protected $description = 'Print attribute declarations for the routes still registered in route files';

    private const VERBS = ['GET' => 'Get', 'POST' => 'Post', 'PUT' => 'Put', 'PATCH' => 'Patch', 'DELETE' => 'Delete', 'OPTIONS' => 'Options'];

    public function handle(Router $router): int
    {
        $base = array_filter(array_map('trim', explode(',', (string) $this->option('base'))));
        $byController = [];
        $redirects = [];
        $closures = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if ($route->getAction('routing') !== null || $this->skipped($route)) {
                continue;
            }

            $uses = $route->getAction('uses');

            if (! is_string($uses)) {
                $closures[] = implode('|', $route->methods()).' /'.$route->uri();

                continue;
            }

            if (ltrim($route->getControllerClass() ?? '', '\\') === RedirectController::class) {
                $redirects[] = "#[RedirectFrom('/".$route->uri()."'".((int) $route->defaults['status'] !== 301 ? ', status: '.$route->defaults['status'] : '').")]  // to {$route->defaults['destination']}";

                continue;
            }

            [$class, $method] = array_pad(explode('@', ltrim($uses, '\\')), 2, '__invoke');
            $byController[$class][$method][] = $this->attribute($route, $base);
        }

        foreach ($byController as $class => $methods) {
            $file = class_exists($class) ? (new ReflectionClass($class))->getFileName() : null;
            $this->newLine();
            $this->line('<fg=yellow>'.$class.'</>'.($file ? ' <fg=gray>'.str_replace(base_path().'/', '', $file).'</>' : ''));

            foreach ($methods as $method => $attributes) {
                $this->line($method === '__invoke' ? '  <fg=gray>// on the class</>' : "  <fg=gray>// on {$method}()</>");
                foreach ($attributes as $attribute) {
                    $this->line('  '.$attribute);
                }
            }
        }

        if ($redirects !== []) {
            $this->newLine();
            $this->line('<fg=yellow>Redirects</> <fg=gray>(put each on the route it points to)</>');
            foreach ($redirects as $redirect) {
                $this->line('  '.$redirect);
            }
        }

        if ($closures !== []) {
            $this->newLine();
            $this->line('<fg=yellow>Closure routes</> <fg=gray>(move each into a controller first)</>');
            foreach ($closures as $closure) {
                $this->line('  '.$closure);
            }
        }

        if ($byController === [] && $redirects === [] && $closures === []) {
            $this->components->info('Every route is already declared with attributes (or comes from a package).');
        }

        return self::SUCCESS;
    }

    private function skipped(Route $route): bool
    {
        $path = $this->option('path');
        $class = $route->getControllerClass() ?? '';

        if (is_string($path) && $path !== '' && ! str_contains($route->uri(), $path)) {
            return true;
        }

        // Package routes: only convert the application's own controllers.
        return is_string($route->getAction('uses')) && class_exists($class)
            && ltrim($class, '\\') !== RedirectController::class
            && str_contains((string) (new ReflectionClass($class))->getFileName(), DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR);
    }

    /**
     * @param  list<string>  $base
     */
    private function attribute(Route $route, array $base): string
    {
        $methods = array_values(array_diff($route->methods(), ['HEAD']));
        $arguments = [$this->export('/'.ltrim($route->uri(), '/'))];

        if (count($methods) === 1 && isset(self::VERBS[$methods[0]])) {
            $verb = self::VERBS[$methods[0]];
        } elseif (count($methods) >= 6) {
            $verb = 'Any';
        } else {
            $verb = 'Route';
            array_unshift($arguments, $this->export($methods));
        }

        if ($route->getName() !== null) {
            $arguments[] = 'name: '.$this->export($route->getName());
        }

        $middleware = array_values(array_diff((array) ($route->getAction('middleware') ?? []), $base));
        if ($middleware !== []) {
            $arguments[] = 'middleware: '.$this->export(count($middleware) === 1 ? $middleware[0] : $middleware);
        }

        $without = (array) ($route->getAction('excluded_middleware') ?? []);
        if ($without !== []) {
            $arguments[] = 'withoutMiddleware: '.$this->export(count($without) === 1 ? $without[0] : $without);
        }

        if ($route->wheres !== []) {
            $arguments[] = 'where: '.$this->export($route->wheres);
        }

        if ($route->defaults !== []) {
            $arguments[] = 'defaults: '.$this->export($route->defaults);
        }

        if ($route->getDomain() !== null) {
            $arguments[] = 'domain: '.$this->export($route->getDomain());
        }

        return '#['.$verb.'('.implode(', ', $arguments).')]';
    }

    private function export(mixed $value): string
    {
        if (is_array($value)) {
            $list = array_is_list($value);
            $items = [];
            foreach ($value as $key => $item) {
                $items[] = ($list ? '' : $this->export($key).' => ').$this->export($item);
            }

            return '['.implode(', ', $items).']';
        }

        if (is_string($value)) {
            return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
        }

        return var_export($value, true);
    }
}
