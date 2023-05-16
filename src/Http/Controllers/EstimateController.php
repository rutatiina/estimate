<?php

namespace Rutatiina\Estimate\Http\Controllers;

use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Rutatiina\Estimate\Models\Estimate;
use Yajra\DataTables\Facades\DataTables;
use Rutatiina\Estimate\Models\EstimateSetting;
use Rutatiina\Invoice\Services\InvoiceService;
use Rutatiina\Item\Traits\ItemsVueSearchSelect;
use Rutatiina\Estimate\Services\EstimateService;
use Rutatiina\SalesOrder\Services\SalesOrderService;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Rutatiina\RetainerInvoice\Services\RetainerInvoiceService;
use Rutatiina\FinancialAccounting\Traits\FinancialAccountingTrait;

class EstimateController extends Controller
{
    use FinancialAccountingTrait;
    use ItemsVueSearchSelect;

    // >> get the item attributes template << !!important

    public function __construct()
    {
        $this->middleware('permission:estimates.view');
        $this->middleware('permission:estimates.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:estimates.update', ['only' => ['edit', 'update']]);
        $this->middleware('permission:estimates.delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $query = Estimate::query();

        if ($request->contact)
        {
            $query->where(function ($q) use ($request)
            {
                $q->where('contact_id', $request->contact);
            });
        }

        $txns = $query->latest()->paginate($request->input('per_page', 20));

        return [
            'tableData' => $txns
        ];
    }

    public function create()
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $tenant = Auth::user()->tenant;

        $txnAttributes = (new Estimate)->rgGetAttributes();

        $txnAttributes['number'] = EstimateService::nextNumber();

        $txnAttributes['status'] = 'Approved';
        //$txnAttributes['contact_id'] = null;
        $txnAttributes['contact'] = json_decode('{"currencies":[]}'); #required
        $txnAttributes['date'] = date('Y-m-d');

        $txnAttributes['base_currency'] = $tenant->base_currency;
        $txnAttributes['quote_currency'] = $tenant->base_currency;
        $txnAttributes['taxes'] = json_decode('{}');
        $txnAttributes['isRecurring'] = false;
        $txnAttributes['recurring'] = [
            'date_range' => [],
            'day_of_month' => '*',
            'month' => '*',
            'day_of_week' => '*',
        ];
        $txnAttributes['contact_notes'] = null;
        $txnAttributes['terms_and_conditions'] = null;
        $txnAttributes['items'] = [
            [
                'selectedTaxes' => [], #required
                'selectedItem' => json_decode('{}'), #required
                'displayTotal' => 0,
                'name' => '',
                'description' => '',
                'rate' => 0,
                'quantity' => 1,
                'total' => 0,
                'taxable_amount' => 0,
                'taxes' => [],

                'item_id' => '',
                'contact_id' => '',
                'tax_id' => '',
                'units' => '',
                'batch' => '',
                'expiry' => ''
            ]
        ];

        return [
            'pageTitle' => 'Create Estimate', #required
            'pageAction' => 'Create', #required
            'txnUrlStore' => '/estimates', #required
            'txnAttributes' => $txnAttributes, #required
        ];
    }

    public function store(Request $request)
    {
        //print_r($request->all()); exit;

        $storeService = EstimateService::store($request);

        if ($storeService == false)
        {
            return [
                'status' => false,
                'messages' => EstimateService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Estimate saved'],
            'number' => 0,
            'callback' => URL::route('estimates.show', [$storeService->id], false)
        ];
    }

    public function show($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $txn = Estimate::findOrFail($id);
        $txn->load('contact', 'financial_account', 'items.taxes');
        $txn->setAppends([
            'taxes',
            'number_string',
            'total_in_words',
        ]);

        return $txn->toArray();
    }

    public function edit($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $txnAttributes = EstimateService::edit($id);

        $data = [
            'pageTitle' => 'Edit Estimate', #required
            'pageAction' => 'Edit', #required
            'txnUrlStore' => '/estimates/' . $id, #required
            'txnAttributes' => $txnAttributes, #required
        ];

        return $data;
    }

    public function update(Request $request)
    {
        //print_r($request->all()); exit;

        $storeService = EstimateService::update($request);

        if ($storeService == false)
        {
            return [
                'status' => false,
                'messages' => EstimateService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Estimate updated'],
            'number' => 0,
            'callback' => URL::route('estimates.show', [$storeService->id], false)
        ];
    }

    public function destroy($id)
    {
        $destroy = EstimateService::destroy($id);

        if ($destroy)
        {
            return [
                'status' => true,
                'messages' => ['Estimate deleted'],
                'callback' => URL::route('estimates.index', [], false)
            ];
        }
        else
        {
            return [
                'status' => false,
                'messages' => EstimateService::$errors
            ];
        }
    }

    #-----------------------------------------------------------------------------------


    public function approve($id)
    {
        $approve = EstimateService::approve($id);

        if ($approve == false)
        {
            return [
                'status' => false,
                'messages' => EstimateService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Estimate Approved'],
        ];
    }

    public function copy($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $txnAttributes = EstimateService::copy($id);

        $data = [
            'pageTitle' => 'Copy Estimate', #required
            'pageAction' => 'Copy', #required
            'txnUrlStore' => '/estimates', #required
            'txnAttributes' => $txnAttributes, #required
        ];

        return $data;
    }

    public function process($id, $processTo)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $txn = Estimate::find($id); //print_r($originalTxn); exit;

        if ($txn == false)
        {
            return redirect()->back()->withErrors(['error' => 'Error #E001: Transaction not found']);
        }

        $txnAttributes = EstimateService::edit($id);

        //check if transaction has been processed before

        $txnAttributes['_method'] = 'POST';
        $txnAttributes['id'] = '';
        $txnAttributes['reference'] = $txn->number;
        $txnAttributes['internal_ref'] = $txn->id;
        $txnAttributes['due_date'] = $txn->expiry_date;

        //var_dump($processTo); exit;

        switch ($processTo)
        {

            case 'retainer-invoices':

                $txnAttributes['number'] = RetainerInvoiceService::nextNumber();
                return [
                    'pageTitle' => 'Process Estimate into Retainer Invoice', #required
                    'pageAction' => 'Process Estimate', #required
                    'txnUrlStore' => '/retainer-invoices', #required
                    'txnAttributes' => $txnAttributes, #required
                ];
                break;

            case 'sales-orders':

                $txnAttributes['number'] = SalesOrderService::nextNumber();
                return [
                    'pageTitle' => 'Process Estimate into Sales Order', #required
                    'pageAction' => 'Process Estimate', #required
                    'txnUrlStore' => '/sales-orders', #required
                    'txnAttributes' => $txnAttributes, #required
                ];
                break;

            case 'invoice':

                $txnAttributes['number'] = InvoiceService::nextNumber();;
                return [
                    'pageTitle' => 'Process Estimate into Invoice', #required
                    'pageAction' => 'Process Estimate', #required
                    'txnUrlStore' => '/invoices', #required
                    'txnAttributes' => $txnAttributes, #required
                ];
                break;

            case 'recurring-invoices':

                $txnAttributes['isRecurring'] = true;
                $txnAttributes['is_recurring'] = true;
                return [
                    'pageTitle' => 'Process Estimate into Recurring Invoice', #required
                    'pageAction' => 'Process Estimate', #required
                    'txnUrlStore' => '/recurring-invoices', #required
                    'txnAttributes' => $txnAttributes, #required
                ];
                break;

            default:

                break;

        }


        return redirect()->back()->withErrors(['error' => 'Unexpected Error #10015']);

    }

    public function exportToExcel(Request $request)
    {
        $txns = collect([]);

        $txns->push([
            'DATE',
            'DOCUMENT#',
            'REFERENCE',
            'CUSTOMER',
            'STATUS',
            'EXPIRY DATE',
            'TOTAL',
            ' ', //Currency
        ]);

        foreach (array_reverse($request->ids) as $id)
        {
            $txn = Transaction::transaction($id);

            $txns->push([
                $txn->date,
                $txn->number,
                $txn->reference,
                $txn->contact_name,
                $txn->status,
                $txn->expiry_date,
                $txn->total,
                $txn->base_currency,
            ]);
        }

        $export = $txns->downloadExcel(
            'maccounts-estimates-export-' . date('Y-m-d-H-m-s') . '.xlsx',
            null,
            false
        );

        //$books->load('author', 'publisher'); //of no use

        return $export;
    }

    public function pdf($id)
    {
        ini_set('max_execution_time', 300); //300 seconds = 5 minutes
        set_time_limit(300);

        $txn = Transaction::transaction($id);

        $data = [
            'tenant' => Auth::user()->tenant,
            'txn' => $txn
        ];

        //return view('limitless-bs4::sales.estimates.pdf')->with($data);

        $pdf = PDF::loadView('limitless-bs4::sales.estimates.pdf', $data);
        return $pdf->inline($txn->type->name . '-' . $txn->number . '.pdf');
        //return $pdf->download($txn->type->name.'-'.$txn->number.'.pdf');
    }

    public function vueAndBlade(Request $request)
    {
        return view('ui.limitless::layout_2-ltr-default.vue-and-blade');
    }
}
