<?php

namespace App\Exports;

use App\Models\VerificationJournal;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class VerificationJournalExport implements FromView
{
    private $journal_details;

    public function __construct($journal_details)
    {
        $this->journal_details = $journal_details;
    }

    public function view(): View
    {
        $data = $this->journal_details;

        return view('accounting.sap-sync.export', compact('data'));
    }
}
