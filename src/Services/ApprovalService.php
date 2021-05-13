<?php

namespace Rutatiina\Estimate\Services;

use Rutatiina\FinancialAccounting\Services\AccountBalanceService;
use Rutatiina\FinancialAccounting\Services\ContactBalanceService;

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
        AccountBalanceService::update($data['ledgers']);

        //Update the contact balances
        ContactBalanceService::update($data['ledgers']);

        return true;
    }

}
