<?php

namespace App\Enums;

enum CashDifferenceDirection: string
{
    case Shortage = 'shortage';
    case Overage = 'overage';
}
