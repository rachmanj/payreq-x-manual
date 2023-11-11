<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Payreq;
use App\Models\Realization;
use App\Models\RealizationDetail;
use Illuminate\Http\Request;

class PayreqReimburseController extends Controller
{
    public function create()
    {
        $roles = app(ToolController::class)->getUserRoles();

        if (in_array('superadmin', $roles) || in_array('admin', $roles)) {
            $equipments = Equipment::orderBy('unit_code', 'asc')->get();
        } else {
            $equipments = Equipment::where('project', auth()->user()->project)->orderBy('unit_code', 'asc')->get();
        }

        // Create new Payreq with type 'reimburse'
        $payreq = Payreq::create([
            'nomor' => app(PayreqController::class)->generateDraftNumber(),
            'type' => 'reimburse',
            'status' => 'draft',
            'type' => 'reimburse',
            'project' => auth()->user()->project,
            'department_id' => auth()->user()->department_id,
            'user_id' => auth()->user()->id,
        ]);

        // Create new Realization
        $realization = Realization::create([
            'payreq_id' => $payreq->id,
            'project' => $payreq->project,
            'department_id' => $payreq->department_id,
            'user_id' => $payreq->user_id,
            'nomor' => app(ToolController::class)->generateDraftRealizationNumber(),
            'status' => 'draft',
        ]);

        return view('user-payreqs.reimburse.create', compact(['payreq', 'equipments']));
    }

    public function edit($id)
    {
        $roles = app(ToolController::class)->getUserRoles();

        if (in_array('superadmin', $roles) || in_array('admin', $roles)) {
            $equipments = Equipment::orderBy('unit_code', 'asc')->get();
        } else {
            $equipments = Equipment::where('project', auth()->user()->project)->orderBy('unit_code', 'asc')->get();
        }

        $payreq = Payreq::findOrFail($id);
        $realization = Realization::where('payreq_id', $payreq->id)->first();

        return view('user-payreqs.reimburse.create', compact(['payreq', 'equipments', 'realization']));
    }

    public function store_detail(Request $request)
    {
        $this->validate($request, [
            'description' => 'required',
            'amount' => 'required|numeric',
        ]);

        $realization = Realization::findOrFail($request->realization_id);

        $realization->realizationDetails()->create([
            'description' => $request->description,
            'amount' => $request->amount,
            'project' => $realization->project,
            'department_id' => $realization->department_id,
            'unit_no' => $request->unit_no,
            'type' => $request->type,
            'qty' => $request->qty,
            'uom' => $request->uom,
            'km_position' => $request->km_position,
        ]);

        return redirect()->back();
    }

    public function submit_payreq(Request $request)
    {
        $realization = Realization::findOrFail($request->realization_id);

        // create approval plan
        $approval_plan = app(ApprovalPlanController::class)->create_approval_plan('payreq', $realization->id);

        if ($approval_plan) {
            $realization->update([
                'status' => 'submitted',
            ]);

            return redirect()->route('user-payreqs.realizations.index')->with('success', 'Realization submitted');
        } else {
            return redirect()->route('user-payreqs.realizations.index')->with('error', 'Realization failed to submit');
        }
    }

    public function delete_detail($realization_detail_id)
    {
        $realization_detail = RealizationDetail::findOrFail($realization_detail_id);
        $realization_detail->delete();

        return redirect()->back();
    }
}
