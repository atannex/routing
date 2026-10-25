<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Validates the request input before the controller runs, with the usual
 * outcome on failure (redirect back with errors, or 422 JSON):
 *
 *     #[Validate(['q' => 'nullable|string|max:120', 'page' => 'integer|min:1'])]
 *     #[Validate(StorePostRequest::class)]   // a FormRequest
 *
 * The validated data is on the request: $request->routeValidated('q').
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Validate implements RouteModifier
{
    /**
     * @param  array<string, mixed>|class-string  $rules
     * @param  array<string, string>  $messages
     * @param  array<string, string>  $attributes
     */
    public function __construct(
        public array|string $rules,
        public array $messages = [],
        public array $attributes = [],
        public ?string $errorBag = null,
    ) {}

    public function modify(RouteDefinition $route): void
    {
        $route->validate = [
            'rules' => $this->rules,
            'messages' => $this->messages,
            'attributes' => $this->attributes,
            'bag' => $this->errorBag,
        ];
    }
}
