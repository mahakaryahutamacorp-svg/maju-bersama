<?php

namespace App\Enums;

enum CashDifferenceStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Resolved = 'resolved';
}
