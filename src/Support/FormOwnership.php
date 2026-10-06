<?php

declare(strict_types=1);

namespace FormStepper\FormStepper\Support;

use FormStepper\FormStepper\Contracts\TenantForm;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class FormOwnership
{
    public static function requester(?Authenticatable $user): ?Model
    {
        if ($user !== null && ! $user instanceof Model) {
            throw new InvalidArgumentException('The authenticated requester must be an Eloquent model.');
        }

        return $user;
    }

    public static function tenantEnabled(): bool
    {
        return (bool) config('form-stepper.tenant.enabled', false);
    }

    public static function validate(?Model $requester, ?Model $tenant): void
    {
        if ($requester !== null && $requester->getKey() === null) {
            throw new InvalidArgumentException('The requester must be a persisted model.');
        }

        if ($tenant === null) {
            return;
        }

        if (! self::tenantEnabled() || $requester === null) {
            throw new InvalidArgumentException('Tenant ownership requires tenant support and an authenticated requester.');
        }

        if (! $tenant instanceof TenantForm || $tenant->getKey() === null) {
            throw new InvalidArgumentException('The current tenant must be a persisted model implementing TenantForm.');
        }
    }
}
