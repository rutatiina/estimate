<?php

namespace Rutatiina\Estimate\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Rutatiina\Estimate\Models\Estimate;
use Rutatiina\Estimate\Models\EstimateItem;
use Rutatiina\Estimate\Models\EstimateItemTax;

class StoreService
{
    public static $errors = [];

    public function __construct()
    {
        //
    }

    public static function run($requestInstance)
    {
        $data = ValidateService::run($requestInstance);
        //print_r($data); exit;
        if ($data === false)
        {
            self::$errors = ValidateService::$errors;
            return false;
        }

        //*
        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $Txn = new Estimate;
            $Txn->tenant_id = $data['tenant_id'];
            $Txn->created_by = Auth::id();
            $Txn->document_name = $data['document_name'];
            $Txn->number_prefix = $data['number_prefix'];
            $Txn->number = $data['number'];
            $Txn->number_length = $data['number_length'];
            $Txn->number_postfix = $data['number_postfix'];
            $Txn->date = $data['date'];
            $Txn->financial_account_code = $data['financial_account_code'];
            $Txn->contact_id = $data['contact_id'];
            $Txn->contact_name = $data['contact_name'];
            $Txn->contact_address = $data['contact_address'];
            $Txn->reference = $data['reference'];
            $Txn->base_currency = $data['base_currency'];
            $Txn->quote_currency = $data['quote_currency'];
            $Txn->exchange_rate = $data['exchange_rate'];
            $Txn->taxable_amount = $data['taxable_amount'];
            $Txn->total = $data['total'];
            $Txn->branch_id = $data['branch_id'];
            $Txn->store_id = $data['store_id'];
            $Txn->expiry_date = $data['expiry_date'];
            $Txn->memo = $data['memo'];
            $Txn->terms_and_conditions = $data['terms_and_conditions'];
            $Txn->status = $data['status'];

            $Txn->save();

            //print_r($data['items']); exit;

            //Save the items >> $data['items']
            foreach ($data['items'] as &$item)
            {
                $item['estimate_id'] = $Txn->id;

                $itemTaxes = (is_array($item['taxes'])) ? $item['taxes'] : [] ;
                unset($item['taxes']);

                $itemModel = EstimateItem::create($item);

                foreach ($itemTaxes as $tax)
                {
                    //save the taxes attached to the item
                    $itemTax = new EstimateItemTax;
                    $itemTax->tenant_id = $item['tenant_id'];
                    $itemTax->estimate_id = $item['estimate_id'];
                    $itemTax->estimate_item_id = $itemModel->id;
                    $itemTax->tax_code = $tax['code'];
                    $itemTax->amount = $tax['total'];
                    $itemTax->inclusive = $tax['inclusive'];
                    $itemTax->exclusive = $tax['exclusive'];
                    $itemTax->save();
                }
                unset($tax);
            }
            unset($item);

            //Save the ledgers >> $data['ledgers']; and update the balances
            //NOTE >> no need to update ledgers since this is not an accounting entry

            //$this->approve();

            DB::connection('tenant')->commit();

            return $Txn;

        }
        catch (\Throwable $e)
        {
            DB::connection('tenant')->rollBack();

            Log::critical('Fatal Internal Error: Failed to save estimate to database');
            Log::critical($e);

            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'Error: Failed to save estimate to database.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to save estimate to database. Please contact Admin';
            }

            return false;
        }
        //*/

    }

}
