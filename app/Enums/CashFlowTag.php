<?php

namespace App\Enums;

enum CashFlowTag: string
{
    case Cash = 'cash';
    case Operating = 'operating';
    case Investing = 'investing';
    case Financing = 'financing';
    case None = 'none';
}
