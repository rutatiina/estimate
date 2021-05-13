<?php

namespace Rutatiina\Estimate\Services;

use Rutatiina\FinancialAccounting\Services\AccountBalanceUpdateService;
use Rutatiina\FinancialAccounting\Services\ContactBalanceUpdateService;

trait ApprovalService
{
    public static function run($data)
    {
        $status = strtolower($data['status']);

        //do not continue if txn status is draft
        if ($status == 'draft') return true;

        //inventory checks and inventory balance update if needed
        //$this->inventory(); //currentlly inventory update for estimates is disabled

        //Update the account balances
        AccountBalanceUpdateService::singleEntry($data);

        //Update the contact balances
        ContactBalanceUpdateService::singleEntry($data);

        return true;
    }

}
