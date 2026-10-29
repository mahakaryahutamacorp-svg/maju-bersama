<?php

namespace App\Enums;

enum PeriodStatus: string
{
    case Open = 'open';
    case SoftClosed = 'soft_closed';
    case Closed = 'closed';
}
