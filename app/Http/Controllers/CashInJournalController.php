<?php

namespace App\Http\Controllers;

use App\Models\CashJournal;
use App\Models\Incoming;
use Illuminate\Http\Request;

class CashInJournalController extends Controller
{
    public function create()
    {
        $incomings = $this->incoming_will_post()->count();

        if ($incomings > 0) {
            $select_all_button = true;
        } else {
            $select_all_button = false;
        }

        $incomings_in_cart = Incoming::where('flag', 'CJT' . auth()->user()->id)
            ->get();

        if ($incomings_in_cart->count() > 0) {
            $remove_all_button = true;
        } else {
            $remove_all_button = false;
        }

        return view('cash-journal.in.create', compact(['select_all_button', 'remove_all_button']));
    }

    public function store(Request $request)
    {
        $incomings = Incoming::where('flag', 'CJT' . auth()->user()->id)
            ->get();

        $cash_journal = new CashJournal();
        $cash_journal->date = $request->date;
        $cash_journal->type = "cash-in";
        $cash_journal->amount = $incomings->sum('amount');
        $cash_journal->description = $request->description;
        $cash_journal->project = auth()->user()->project;
        $cash_journal->created_by = auth()->user()->id;
        $cash_journal->save();

        // update cash journal number
        $cash_journal->journal_no = app(ToolController::class)->generateCashJournalNumber($cash_journal->id, 'cash-in');
        $cash_journal->save();

        // update incomings cash_journal_id
        foreach ($incomings as $incoming) {
            $incoming->cash_journal_id = $cash_journal->id;
            $incoming->flag = null;
            $incoming->save();
        }

        return redirect()->route('cash-journals.index')->with('success', 'Cash Journal created successfully.');
    }

    public function move_all_tocart()
    {
        $incomings = $this->incoming_will_post();

        foreach ($incomings as $incoming) {
            $incoming->flag = 'CJT' . auth()->user()->id; // CJT = Cash Journal Temporary
            $incoming->save();
        }

        return redirect()->back();
    }

    public function remove_all_fromcart()
    {
        $incomings = Incoming::where('flag', 'CJT' . auth()->user()->id)
            ->get();

        foreach ($incomings as $incoming) {
            $incoming->flag = null;
            $incoming->save();
        }

        return redirect()->back();
    }

    public function add_to_cart(Request $request)
    {
        $incoming = Incoming::findOrFail($request->incoming_id);
        $incoming->flag = 'CJT' . auth()->user()->id; // CJT = Cash Journal Temporary
        $incoming->save();

        return redirect()->back();
    }

    public function remove_from_cart(Request $request)
    {
        $incoming = Incoming::findOrFail($request->incoming_id);
        $incoming->flag = null;
        $incoming->save();

        return redirect()->back();
    }

    public function to_cart_data()
    {
        $incomings = $this->incoming_will_post();

        return datatables()->of($incomings)
            ->addColumn('relization_no', function ($incoming) {
                if ($incoming->realization_id == null) {
                    return $incoming->nomor;
                } else {
                    return $incoming->realization->nomor;
                }
            })
            ->addColumn('amount', function ($incoming) {
                return number_format($incoming->amount, 2);
            })
            ->editColumn('receive_date', function ($incoming) {
                $date = new \Carbon\Carbon($incoming->receive_date);
                return $date->addHours(8)->format('d-M-Y');
            })
            ->addIndexColumn()
            ->addColumn('action', 'cash-journal.in.to-cart-action')
            ->toJson();
    }

    public function in_cart_data()
    {
        $incomings = Incoming::where('flag', 'CJT' . auth()->user()->id)
            ->get();

        return datatables()->of($incomings)
            ->addColumn('relization_no', function ($incoming) {
                if ($incoming->realization_id == null) {
                    return $incoming->nomor;
                } else {
                    return $incoming->realization->nomor;
                }
            })
            ->addColumn('amount', function ($incoming) {
                return number_format($incoming->amount, 2);
            })
            ->editColumn('receive_date', function ($incoming) {
                $date = new \Carbon\Carbon($incoming->receive_date);
                return $date->addHours(8)->format('d-M-Y');
            })
            ->addIndexColumn()
            ->addColumn('action', 'cash-journal.in.in-cart-action')
            ->toJson();
    }

    public function incoming_will_post()
    {
        if (auth()->user()->project === '000H') {
            $project_include = ['000H', 'APS'];
        } else {
            $project_include = [auth()->user()->project];
        }
        $incomings = Incoming::whereIn('project', $project_include)
            ->whereNull('flag')
            ->whereNull('cash_journal_id')
            // ->where('realization_id', '<>', null)
            ->where('will_post', 1)
            ->whereNotNull('receive_date')
            ->get();

        return $incomings;
    }
}
