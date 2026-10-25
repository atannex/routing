<?php

declare(strict_types=1);

namespace Atannex\Routing\Http\Middleware;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs #[ValidateParameter] and #[Validate] before the controller.
 *
 * Parameters are checked first, on their raw values (before model binding);
 * one failing ends the request with the declared status (404 by default).
 * Then the input is validated, with Laravel's usual failure response, and
 * the validated data is kept on the request ($request->routeValidated()).
 */
final class ValidateRouteRequest
{
    public const VALIDATED = 'routing.validated';

    public function __construct(
        private readonly Container $container,
        private readonly ValidationFactory $validation,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $config = (array) ($route?->getAction('routing') ?? []);

        if (! empty($config['parameters']) && $route !== null) {
            $values = $route->originalParameters() + array_fill_keys(array_keys($config['parameters']), null);
            $validator = $this->validation->make($values, $config['parameters']);

            if ($validator->fails()) {
                abort((int) ($config['parameter_status'] ?? 404));
            }
        }

        if (! empty($config['validate'])) {
            $request->attributes->set(self::VALIDATED, $this->validate($request, $config['validate']));
        }

        return $next($request);
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function validate(Request $request, array $spec): array
    {
        $rules = $spec['rules'];

        if (is_string($rules)) {
            // Resolving a FormRequest validates it (and authorizes it).
            $form = $this->container->make($rules);

            return $form instanceof FormRequest ? $form->validated() : [];
        }

        $validator = $this->validation->make(
            $request->all(),
            $rules,
            (array) ($spec['messages'] ?? []),
            (array) ($spec['attributes'] ?? []),
        );

        return empty($spec['bag']) ? $validator->validate() : $validator->validateWithBag($spec['bag']);
    }
}
