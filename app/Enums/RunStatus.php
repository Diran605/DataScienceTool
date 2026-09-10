<?php

namespace App\Enums;

enum RunStatus: string
{
    case Pending = 'pending';
    case Profiling = 'profiling';
    case Profiled = 'profiled';
    case Cleaning = 'cleaning';
    case Validating = 'validating';
    case Exporting = 'exporting';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
