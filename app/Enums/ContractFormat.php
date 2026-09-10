<?php

namespace App\Enums;

enum ContractFormat: string
{
    case Csv = 'csv';
    case Json = 'json';
    case Db = 'db';
}
