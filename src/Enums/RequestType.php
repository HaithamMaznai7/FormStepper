<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Enums;

enum RequestType: string
{
    case Customer = 'Customer';
    case Company = 'Company';
    case Partner = 'Partner';
    case B2C = 'B2C';
    case B2B = 'B2B';
    case B2Partner = 'B2Partner';
}
