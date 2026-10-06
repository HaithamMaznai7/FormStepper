<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Support;

use HaithamMaznai\FormStepper\Contracts\ProvidesAvailableTypes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ScopeMatcher
{
    /**
     * @param  array{types: list<string>, tenants: list<string>, requester: list<string>, guests: list<string>}  $scope
     */
    public function matches(
        array $scope,
        string $formType,
        ?Model $requester,
        ?Model $tenant,
        bool $guest,
    ): bool {
        if (! $this->includes($scope['types'], $formType)) {
            return false;
        }

        if (! $this->modelAllows($scope['requester'], $formType, $requester, 'requester')) {
            return false;
        }

        if (! $this->modelAllows($scope['tenants'], $formType, $tenant, 'tenant')) {
            return false;
        }

        $context = $guest ? 'guest' : 'authenticated';

        return $this->includes($scope['guests'], $context);
    }

    /**
     * @param  list<string>  $scope
     */
    private function modelAllows(array $scope, string $formType, ?Model $model, string $name): bool
    {
        if ($model === null) {
            return $scope === ['*'];
        }

        if (! $model instanceof ProvidesAvailableTypes) {
            if ($scope !== ['*']) {
                throw new InvalidArgumentException(
                    "The {$name} model must implement ProvidesAvailableTypes when its scope is restricted.",
                );
            }

            return true;
        }

        $availableTypes = $model->getAvailableTypes();

        return in_array($formType, $availableTypes, true) && $this->includes($scope, $formType);
    }

    /**
     * @param  list<string>  $scope
     */
    private function includes(array $scope, string $value): bool
    {
        return in_array('*', $scope, true) || in_array($value, $scope, true);
    }
}
