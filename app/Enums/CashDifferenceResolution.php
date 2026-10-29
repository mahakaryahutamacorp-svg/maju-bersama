<?php

namespace App\Enums;

enum CashDifferenceResolution: string
{
    case Expense = 'expense_6810';
    case EmployeeReceivable = 'employee_receivable_1135';
    case OtherIncome = 'other_income_4150';

    public function allowedFor(CashDifferenceDirection $direction): bool
    {
        return match ($direction) {
            CashDifferenceDirection::Shortage => $this !== self::OtherIncome,
            CashDifferenceDirection::Overage => $this === self::OtherIncome,
        };
    }
}
