<?php

namespace App\Http\Controllers\Admin;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\CashBookBalanceBfw;
use App\Models\SampleBalace;
use App\Models\FinancialYear;
use App\Models\BankActivity;
use App\Http\Requests\StoreCashBookBalanceRequest;
use App\Http\Resources\CashBookBalanceResource;
use App\Services\CashBookService;

use Inertia\Inertia;

class CashBookBalanceBfwController extends Controller
{
    protected $service;

    public function __construct(CashBookService $service)
    {
        $this->service = $service;
    }

    public function index()
    {  //$mysaamples = SampleBalace::where('id','>',0)->get();
    //dd($mysaample);
    // foreach($mysaamples as $mysaample){
    //      $update = SampleBalace::find($mysaample->id);
    //      $data = explode('_',$mysaample->name);
    //     if (isset($data[1])) {
    //     $update->account = $data[1];
    //     $update->save();
    //     }
    // } dd('kolo');
    // ********************
    //  $bank_activities = BankActivity::where('id','>',0)->get();
    //  foreach ($bank_activities as $bank_activity) {
    //    $update = SampleBalace::where('account','=',$bank_activity->account_number)->first();
    //    if(isset($update)){
    //       $myCash = CashBookBalanceBfw::where('bank_activity_id','=',$bank_activity->id)->first();
    //       $myCash->amount = $update->amount;
    //       $myCash->save();
    //    }
    //  }
    //   dd('lo');
        $balances = $this->service->getAllPaginated();
        return Inertia::render('admin/cashBookBallanceBfw/index', [
            'balances' => CashBookBalanceResource::collection($balances),
            'financialYears' => \App\Models\FinancialYear::all(['name']),
        ]);
    }

    public function store(StoreCashBookBalanceRequest $request)
    {
        $this->service->storeOrUpdate($request->validated());
        return redirect()->back()->with('message', 'Record created successfully');
    }

    public function update(StoreCashBookBalanceRequest $request, $id)
    {
        $this->service->storeOrUpdate($request->validated(), $id);
        return redirect()->back()->with('message', 'Record updated successfully');
    }

    public function toggleStatus($id)
    {
        $this->service->toggleStatus($id);
        return redirect()->back();
    }
    public function generate_year_account_Bbfw(Request $request)
    {
        // Ensure you have: use App\Models\BankActivity; at the top as well
        $bankActs = \App\Models\BankActivity::all();

        foreach($bankActs as $bankAct){
            $check = CashBookBalanceBfw::where('bank_activity_id','=', $bankAct->id)
            ->where('financial_year', '=', $request->financial_year)->first();
            if(empty($check)){
                CashBookBalanceBfw::create([
                    'financial_year' => $request->financial_year,
                    'bank_activity_id' => $bankAct->id,
                    'amount' => 0,
                    'status' => 1,
                ]);
            }
        }

        return redirect()->back()->with('message', 'Balances generated successfully');
    }
}
