<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Support;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/** @internal Laravel 10 uses DBAL to change SQLite enum columns. */
class OwnershipStatusType extends StringType
{
    public function getName(): string
    {
        return 'enum';
    }

    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column)
            .' CHECK ('.$platform->quoteIdentifier($column['name'])." IN ('draft', 'submitted'))";
    }
}
