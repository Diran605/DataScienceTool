<?php

namespace App\Enums;

enum StepType: string
{
    case Profile = 'profile';
    case Clean = 'clean';
    case Validate = 'validate';
    case Export = 'export';
}
