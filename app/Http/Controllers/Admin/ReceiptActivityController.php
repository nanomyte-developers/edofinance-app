<?php

namespace App\Http\Controllers\Admin; // Moved to Admin namespace based on your path
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReceiptActivityRequest;
use App\Http\Resources\ReceiptActivityResource;
use App\Models\ReceiptActivity;
use App\Services\ReceiptActivityService;
use Inertia\Inertia;

class ReceiptActivityController extends Controller
{
    public function __construct(protected ReceiptActivityService $service) {}

    public function index()
    {
        $activities = $this->service->getAllPaginated();
        return Inertia::render('admin/receiptActivities/index', [
            'receiptActivities' => ReceiptActivityResource::collection($activities)
        ]);
    }

    public function store(ReceiptActivityRequest $request)
    {
        $this->service->store($request->validated());
        return back()->with('message', 'Receipt Activity created successfully.');
    }

    public function update(ReceiptActivityRequest $request, ReceiptActivity $receiptActivity)
    {
        $this->service->update($receiptActivity, $request->validated());
        return back()->with('message', 'Receipt Activity updated successfully.');
    }

    public function toggleStatus(ReceiptActivity $receiptActivity)
    {
        // Uses the service we created earlier
        if (!empty($receiptActivity)) {
            if ($receiptActivity->status == 0) {
                $receiptActivity->status = 1;
            } else {
                $receiptActivity->status = 0;
            }
            $receiptActivity->save();

            return back()->with('message', 'Status updated successfully.');
        }
        return back()->with('message', 'Status updated was not successfully.');
    }
    //     public function toggleStatus(ReceiptActivity $receiptActivity)
    //     {
    //         $this->service->toggleStatus($receiptActivity);
    //         return back()->with('message', 'Status updated successfully.');
    //     }
}
