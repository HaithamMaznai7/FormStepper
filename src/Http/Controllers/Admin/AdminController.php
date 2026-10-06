<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;

abstract class AdminController
{
    /**
     * @param  view-string  $name
     * @param  array<string, mixed>  $data
     */
    protected function view(string $name, array $data = []): View
    {
        return view($name, $data);
    }

    protected function redirectTo(string $route, mixed $parameters = [], ?string $status = null): RedirectResponse
    {
        $redirect = redirect()->route($this->routeName($route), $parameters);

        return $status === null ? $redirect : $redirect->with('status', $status);
    }

    protected function routeName(string $route): string
    {
        return config('form-stepper.admin.name', 'form-stepper.admin.').$route;
    }

    protected function perPage(): int
    {
        return (int) config('form-stepper.admin.per_page', 15);
    }

    /**
     * Turn a newline or pipe separated textarea into a list.
     *
     * @return list<string>
     */
    protected function lines(?string $value): array
    {
        return array_values(array_filter(
            array_map(trim(...), preg_split("/\r\n|\r|\n|\|/", (string) $value) ?: []),
            static fn (string $line): bool => $line !== '',
        ));
    }

    /**
     * Decode an optional JSON textarea, reporting failures on the given field.
     */
    protected function json(?string $value, string $field): mixed
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([$field => 'The value must be valid JSON.']);
        }
    }

    /**
     * Run a schema snapshot and report schema errors as validation errors.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function assertSchema(callable $callback, string $field = 'key'): mixed
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }
    }
}
