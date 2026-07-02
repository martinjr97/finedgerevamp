<?php

namespace App\PaymentPlatform\Enums;

enum GatewayAttemptPurpose: string
{
    case LoanRepayment = 'loan_repayment';
    case LoanDisbursement = 'loan_disbursement';
}
