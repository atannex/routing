<div align="center">

<h1>Atannex Routing</h1>

<p><strong>Attribute-based routing for Laravel applications.</strong></p>

<p>
  <a href="https://github.com/atannex/routing/actions/workflows/tests.yml"><img src="https://github.com/atannex/routing/actions/workflows/tests.yml/badge.svg?branch=main" alt="Test workflow status"></a>
  <a href="https://github.com/atannex/routing/blob/main/composer.json"><img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&amp;logoColor=white" alt="PHP 8.2 or later"></a>
  <a href="https://github.com/atannex/routing/blob/main/composer.json"><img src="https://img.shields.io/badge/Laravel-11%20%7C%2012-FF2D20?logo=laravel&amp;logoColor=white" alt="Laravel 11 and 12"></a>
  <a href="https://github.com/atannex/routing/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-MIT-2EA44F" alt="MIT license"></a>
</p>

<p>
  <a href="https://packages.atannex.com">Documentation</a>
  &middot;
  <a href="https://github.com/atannex/routing">Source Code</a>
  &middot;
  <a href="#contributing">Contributing</a>
</p>

</div>

## Introduction

[Atannex Routing](https://packages.atannex.com) is a [Laravel](https://laravel.com/) package for defining application [routes](https://laravel.com/framework/docs/routing) directly on [controller classes and methods](https://laravel.com/framework/docs/controllers) using [PHP](https://www.php.net/) [attributes](https://www.php.net/manual/en/language.attributes.overview.php). It keeps route declarations close to the actions that handle requests, reducing routing boilerplate and making web applications, [APIs](https://laravel.com/framework/docs/routing#api-routes), and modular codebases easier to maintain.

Built on Laravel's router, the package preserves [named routes](https://laravel.com/framework/docs/routing#named-routes), [URL generation](https://laravel.com/framework/docs/urls), [middleware](https://laravel.com/framework/docs/middleware), and [route caching](https://laravel.com/framework/docs/routing#route-caching). It adds [controller discovery](https://packages.atannex.com), [specificity-based route ordering](https://packages.atannex.com), and [route inspection tools](https://packages.atannex.com) to help teams manage growing route collections.

## Capabilities

The package supports [resource controllers](https://laravel.com/framework/docs/controllers#resource-controllers), [single-action controllers](https://laravel.com/framework/docs/controllers#single-action-controllers), [route groups](https://laravel.com/framework/docs/routing#route-groups), [URL prefixes](https://laravel.com/framework/docs/routing#route-prefixes), [domains](https://laravel.com/framework/docs/routing#subdomain-routing), [parameter constraints](https://laravel.com/framework/docs/routing#parameters-regular-expression-constraints), [model binding](https://laravel.com/framework/docs/routing#route-model-binding), and [request validation](https://laravel.com/framework/docs/validation). Shared routing rules can be applied across controllers and application directories.

Access controls integrate with Laravel's [authentication](https://laravel.com/framework/docs/authentication), [authorization](https://laravel.com/framework/docs/authorization), [email verification](https://laravel.com/framework/docs/verification), [signed URLs](https://laravel.com/framework/docs/urls#signed-urls), [password confirmation](https://laravel.com/framework/docs/authentication#password-confirmation), and [rate limiting](https://laravel.com/framework/docs/routing#rate-limiting). Additional capabilities include [webhook signature verification](https://packages.atannex.com), [response caching headers](https://laravel.com/framework/docs/responses#cache-control-middleware), and [JSON error handling](https://laravel.com/framework/docs/errors#rendering-exceptions-as-json).

Applications can manage [redirects](https://laravel.com/framework/docs/routing#redirect-routes), [view routes](https://laravel.com/framework/docs/routing#view-routes), [fallback routes](https://laravel.com/framework/docs/routing#fallback-routes), [conditional registration](https://packages.atannex.com), and [endpoint deprecation](https://packages.atannex.com), together with attribute-based [task scheduling](https://laravel.com/framework/docs/scheduling) and [broadcast channel registration](https://laravel.com/framework/docs/broadcasting#defining-channel-classes). [Custom attributes and discovery caching](https://packages.atannex.com) support project-specific extensions and deployment workflows.

## Official Documentation

Read the [official Atannex Routing documentation](https://packages.atannex.com) for installation instructions, configuration options, supported attributes, and complete usage examples.

## Local Development

Clone the repository and install its dependencies with [Composer](https://getcomposer.org/):

```bash
git clone https://github.com/atannex/routing.git
cd routing
composer install
```

Run the [PHPUnit](https://phpunit.de/) test suite:

```bash
composer test
```

## Contributing

Contributions are welcome through [GitHub pull requests](https://github.com/atannex/routing/pulls). Describe the problem your change addresses, include tests for behavior changes, and run the test suite before submitting your contribution.

## License

Atannex Routing is open-source software licensed under the [MIT license](https://github.com/atannex/routing/blob/main/LICENSE).
