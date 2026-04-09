<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Imports\VouchersImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Controllers\Controller;
use App\Imports\BankActivityImport;
use App\Imports\ReceiptsImport;

class ImportVoucherController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        Excel::import(new VouchersImport, $request->file('file'));

        return redirect()->back()->with('success', 'Data imported successfully.');
    }

    public function index()
    {
        return Inertia::render('admin/imports/ImportVouchers', ['csrf_token' => csrf_token() ]);
    }

    public function showBankActivity()
    {
        return Inertia::render('admin/imports/ImportBankActivities', ['csrf_token' => csrf_token() ]);
    }


    public function importBankActivity(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        Excel::import(new BankActivityImport, $request->file('file'));

        return redirect()->back()->with('success', 'Data imported successfully.');
    }
    
    
    public function showReceipt()
    {
        return Inertia::render('admin/imports/ImportReceipts', ['csrf_token' => csrf_token() ]);
    }


    public function importReceipt(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        Excel::import(new ReceiptsImport, $request->file('file'));

        return redirect()->back()->with('success', 'Data imported successfully.');
    }

}