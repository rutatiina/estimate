<?php

namespace Rutatiina\Estimate\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Rutatiina\Contact\Models\Contact;
use Rutatiina\Estimate\Models\EstimateSetting;
use Rutatiina\Tax\Models\Tax;
use Rutatiina\Estimate\Models\Estimate;

trait Validate
{
    private function insertDataDefault($key, $defaultValue)
    {
        if (isset($this->txnInsertData[$key]))
        {
            return $this->txnInsertData[$key];
        }
        else
        {
            return $defaultValue;
        }
    }

    private function itemDataDefault($item, $key, $defaultValue)
    {
        if (isset($item[$key]))
        {
            return $item[$key];
        }
        else
        {
            return $defaultValue;
        }
    }

    private function validate()
    {
        //$request = request(); //used for the flash when validation fails
        $user = auth()->user();

        //print_r($request->all()); exit;

        $data = $this->txnInsertData;

        //print_r($data); exit;

        $data['user_id'] = $user->id;
        $data['tenant_id'] = $user->tenant->id;
        $data['created_by'] = $user->name;

        //set default values ********************************************
        $data['id'] = $this->insertDataDefault('id', null);
        $data['app'] = $this->insertDataDefault('app', null);
        $data['app_id'] = $this->insertDataDefault('app_id', null);
        $data['internal_ref'] = $this->insertDataDefault('internal_ref', null);
        $data['txn_entree_id'] = $this->insertDataDefault('txn_entree_id', null);

        $data['payment_mode'] = $this->insertDataDefault('payment_mode', null);
        $data['payment_terms'] = $this->insertDataDefault('payment_terms', null);
        $data['invoice_number'] = $this->insertDataDefault('invoice_number', null);
        $data['due_date'] = $this->insertDataDefault('due_date', null);
        $data['expiry_date'] = $this->insertDataDefault('expiry_date', null);

        $data['base_currency'] = $this->insertDataDefault('base_currency', null);
        $data['quote_currency'] = $this->insertDataDefault('quote_currency', $data['base_currency']);
        $data['exchange_rate'] = $this->insertDataDefault('exchange_rate', 1);

        $data['contact_id'] = $this->insertDataDefault('contact_id', null);
        $data['contact_name'] = $this->insertDataDefault('contact_name', null);
        $data['contact_address'] = $this->insertDataDefault('contact_address', null);

        $data['branch_id'] = $this->insertDataDefault('branch_id', null);
        $data['store_id'] = $this->insertDataDefault('store_id', null);
        $data['terms_and_conditions'] = $this->insertDataDefault('terms_and_conditions', null);
        $data['external_ref'] = $this->insertDataDefault('external_ref', null);
        $data['reference'] = $this->insertDataDefault('reference', null);
        $data['recurring'] = $this->insertDataDefault('recurring', []);

        $data['items'] = $this->insertDataDefault('items', []);

        $data['taxes'] = $this->insertDataDefault('taxes', []);

        //$data['taxable_amount'] = $this->insertDataDefault('taxable_amount', );
        $data['discount'] = $this->insertDataDefault('discount', 0);


        // >> data validation >>------------------------------------------------------------

        //validate the data
        $customMessages = [
            //'total.in' => "Item total is invalid:\nItem total = item rate x item quantity",

            'items.*.taxes.*.code.required' => "Tax code is required",
            'items.*.taxes.*.total.required' => "Tax total is required",
            //'items.*.taxes.*.exclusive.required' => "Tax exclusive amount is required",
        ];

        $rules = [
            'tenant_id' => 'required|numeric',
            'contact_id' => 'required|numeric',
            'date' => 'required|date',
            'base_currency' => 'required',
            'document_id' => 'numeric|nullable',
            'salesperson_contact_id' => 'numeric|nullable',

            'items' => 'required|array',
            'items.*.name' => 'required_without:type_id',
            'items.*.rate' => 'required|numeric',
            'items.*.quantity' => 'required|numeric|gt:0',
            //'items.*.total' => 'required|numeric|in:' . $itemTotal, //todo custom validator to check this
            'items.*.units' => 'numeric|nullable',
            'items.*.taxes' => 'array|nullable',

            'items.*.taxes.*.code' => 'required',
            'items.*.taxes.*.total' => 'required|numeric',
            //'items.*.taxes.*.exclusive' => 'required|numeric',
        ];

        $validator = Validator::make($data, $rules, $customMessages);

        if ($validator->fails())
        {
            $this->errors = $validator->errors()->all();
            return false;
        }

        // << data validation <<------------------------------------------------------------

        $this->settings = EstimateSetting::has('financial_account')->with(['financial_account'])->firstOrFail();
        //Log::info($this->settings);


        $contact = Contact::findOrFail($data['contact_id']);

        $data['contact_name'] = (!empty(trim($contact->name))) ? $contact->name : $contact->display_name;
        $data['contact_address'] = trim($contact->shipping_address_street1 . ' ' . $contact->shipping_address_street2);


        //set the transaction total to zero
        $txnTotal = 0;
        $taxableAmount = 0;


        //Formulate the DB ready items array
        $items = [];
        foreach ($data['items'] as $item)
        {
            $itemData = [
                'contact_id' => $item['contact_id'],
                'name' => $item['name'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'rate' => $item['rate'],
                'total' => $item['total'],
                'units' => $this->itemDataDefault($item, 'units', null),
                'batch' => $this->itemDataDefault($item, 'batch', null),
                'expiry' => $this->itemDataDefault($item, 'expiry', null),
                'taxes' => (isset($item['taxes']) && is_array($item['taxes'])) ? $item['taxes'] : [],
            ];

            $items[] = $itemData;

            //generate the transaction total
            $txnTotal += $item['total'];
            $taxableAmount += $item['total'];

            foreach ($itemData['taxes'] as $itemTax)
            {
                //update the total of the transaction i.e. only add the amount exclusive
                $taxableAmount -= $itemTax['inclusive'];
                $txnTotal += $itemTax['exclusive'];
            }
        }


        // >> Generate the transaction variables
        $this->txn['tenant_id'] = $data['tenant_id'];
        $this->txn['user_id'] = $data['user_id'];
        $this->txn['app'] = $data['app'];
        $this->txn['app_id'] = $data['app_id'];
        $this->txn['created_by'] = $data['created_by'];
        $this->txn['internal_ref'] = $data['internal_ref'];
        $this->txn['document_name'] = $this->settings->document_name;
        $this->txn['number_prefix'] = $this->settings->number_prefix;
        $this->txn['number'] = $data['number'];
        $this->txn['number_length'] = $this->settings->minimum_number_length;
        $this->txn['number_postfix'] = $this->settings->number_postfix;
        $this->txn['date'] = $data['date'];
        $this->txn['financial_account_code'] = $this->settings->financial_account->code;
        $this->txn['contact_id'] = $data['contact_id'];
        $this->txn['contact_name'] = $data['contact_name'];
        $this->txn['contact_address'] = $data['contact_address'];
        $this->txn['reference'] = $data['reference'];
        $this->txn['invoice_number'] = $data['invoice_number'];
        $this->txn['base_currency'] = $data['base_currency'];
        $this->txn['quote_currency'] = $data['quote_currency'];
        $this->txn['exchange_rate'] = $data['exchange_rate'];
        $this->txn['taxable_amount'] = $data['taxable_amount'];
        $this->txn['total'] = $txnTotal;
        $this->txn['balance'] = $txnTotal;
        $this->txn['salesperson_contact_id'] = $data['salesperson_contact_id'];
        $this->txn['branch_id'] = $data['branch_id'];
        $this->txn['store_id'] = $data['store_id'];
        $this->txn['due_date'] = $data['due_date'];
        $this->txn['expiry_date'] = $data['expiry_date'];
        $this->txn['terms_and_conditions'] = $data['terms_and_conditions'];
        $this->txn['external_ref'] = $data['external_ref'];
        $this->txn['payment_mode'] = $data['payment_mode'];
        $this->txn['payment_terms'] = $data['payment_terms'];
        $this->txn['status'] = $data['status'];

        // << Generate the transaction variables

        $this->txn['items'] = $items;

        $this->txn['accounts'] = [
            'debit' => $this->settings->financial_account,
        ];

        $this->txn['ledgers'][] = [
            'financial_account_code' => $this->settings->financial_account->code,
            'effect' => 'debit',
            'total' => $this->txn['total'],
            'contact_id' => $this->txn['contact_id']
        ];

        //print_r($this->txn); exit;

        //Now add the default values to items and ledgers

        foreach ($this->txn['ledgers'] as &$ledger)
        {
            $ledger['tenant_id'] = $data['tenant_id'];
            $ledger['date'] = date('Y-m-d', strtotime($data['date']));
            $ledger['base_currency'] = $data['base_currency'];
            $ledger['quote_currency'] = $data['quote_currency'];
            $ledger['exchange_rate'] = $data['exchange_rate'];
        }
        unset($ledger);

        //Return the array of txns
        //print_r($this->txn); exit;

        return true;

    }

}
