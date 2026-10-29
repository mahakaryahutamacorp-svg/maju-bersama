<?php

namespace App\Enums;

enum PosSessionStatus: string
{
    case Open = 'open';
    case Closing = 'closing';
    case Closed = 'closed';
    case PendingApproval = 'pending_approval';
    case Reconciled = 'reconciled';
}
