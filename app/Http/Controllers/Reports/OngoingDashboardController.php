<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\UserController;
use App\Models\Account;
use App\Models\Incoming;
use App\Models\Outgoing;
use App\Models\Payreq;
use App\Models\Realization;
use App\Models\RealizationDetail;
use App\Models\User;
use App\Models\VerificationJournalDetail;
use Illuminate\Support\Facades\DB;

class OngoingDashboardController extends Controller
{
    public function dashboard()
    {
        $userRoles = app(UserController::class)->getUserRoles();

        if (array_intersect(['superadmin', 'admin', 'cashier'], $userRoles)) {
            $project = request()->query('project');
        } else {
            $project = auth()->user()->project;
        }
        $dashboard_data = $this->dashboard_data($project);

        return view('reports.ongoing.dashboard', compact(['project', 'dashboard_data']));
    }

    public function dashboard_data($project)
    {
        $saldo_pc_payreq_system = Account::where('type', 'cash')->where('project', $project)->first()->app_balance;
        $payreq_belum_realisasi_amount = $this->payreq_belum_realisasi_amount($project);
        $realisasi_belum_verifikasi_amount = $this->realisasi_belum_verifikasi_amount($project);
        $verifikasi_belum_posted_amount = $this->verifikasi_belum_posted_amount($project); // this is not used anymore, so we just set it to '0.00
        $variance_realisasi_belum_outgoing_amount = $this->variance_realisasi_belum_outgoing_amount($project);
        $variance_realisasi_belum_incoming_amount = $this->variance_realisasi_belum_incoming_amount($project);
        $total_advance_employee = $payreq_belum_realisasi_amount + $realisasi_belum_verifikasi_amount + $verifikasi_belum_posted_amount + $variance_realisasi_belum_incoming_amount - $variance_realisasi_belum_outgoing_amount;
        $cek_balance_pc_sap = $saldo_pc_payreq_system + $total_advance_employee;

        $dashboard_data = [
            'saldo_pc_payreq_system' => number_format($saldo_pc_payreq_system, 2),
            'payreq_belum_realisasi_amount' => number_format($payreq_belum_realisasi_amount, 2),
            'realisasi_belum_verifikasi_amount' => number_format($realisasi_belum_verifikasi_amount, 2),
            'verifikasi_belum_posted_amount' => number_format($verifikasi_belum_posted_amount, 2),
            'variance_realisasi_belum_incoming_amount' => number_format($variance_realisasi_belum_incoming_amount, 2),
            'variance_realisasi_belum_outgoing_amount' => number_format($variance_realisasi_belum_outgoing_amount, 2),
            'total_advance_employee' => number_format($total_advance_employee, 2),
            'cek_balance_pc_sap' => number_format($cek_balance_pc_sap, 2),
            'ongoing_documents_by_user' => $this->ongoing_documents_by_user($project),
        ];

        return $dashboard_data;
    }

    public function payreq_belum_realisasi_amount($project)
    {
        $project = $this->get_projects($project);

        $payreqs = $this->get_payreq_belum_realisasi($project);
        $payreqIds = $payreqs->pluck('id')->toArray();

        $outgoings = Outgoing::whereIn('payreq_id', $payreqIds)->get();
        $amount = $outgoings->sum('amount');

        return $amount;
    }

    public function realisasi_belum_verifikasi_amount($project)
    {
        $project = $this->get_projects($project);

        $realizations = $this->get_realisasi_belum_verifikasi($project);
        $realizationIds = $realizations->pluck('id')->toArray();

        $realizationDetails = RealizationDetail::whereIn('realization_id', $realizationIds)->get();
        $amount = $realizationDetails->sum('amount');

        return $amount;
    }

    public function verifikasi_belum_posted_amount($project)
    {
        $project = $this->get_projects($project);

        $vj_details = $this->get_verifikasi_belum_posted($project);
        $amount = $vj_details->sum('amount');

        return $amount;
    }

    public function variance_realisasi_belum_outgoing_amount($project)
    {
        $project = $this->get_projects($project);

        $payreqs = $this->get_payreq_other_belum_outgoing($project);
        $total_amount = $payreqs->sum('amount');

        return $total_amount;
    }

    public function variance_realisasi_belum_incoming_amount($project)
    {
        $project = $this->get_projects($project);

        $incomings = $this->get_incomings_belum_diterima($project);
        $total_amount = $incomings->sum('amount');

        return $total_amount;
    }

    public function user_list($project)
    {
        $project = $this->get_projects($project);

        $users = User::join('departments', 'users.department_id', '=', 'departments.id')
            ->whereIn('project', $project)
            ->select('users.name', 'users.id', 'users.project', 'departments.department_name')
            ->orderBy('users.name', 'asc')
            ->get();

        $users->each(function ($user, $index) {
            $user->index = $index + 1;
        });

        return $users;
    }

    public function ongoing_documents_by_user($project)
    {
        $users = $this->user_list($project);

        foreach ($users as $user) {
            $payreq_belum_realisasi_amount = $this->payreqs_belum_realisasi_by_user_amount($user->id);
            if ($payreq_belum_realisasi_amount > 0) {
                $payreq_belum_realisasi_amount = number_format($payreq_belum_realisasi_amount, 2);
            } else {
                $payreq_belum_realisasi_amount = 0;
            }

            $realisasi_belum_verifikasi_by_user_amount = $this->realisasi_belum_verifikasi_by_user_amount($user->id);
            if ($realisasi_belum_verifikasi_by_user_amount > 0) {
                $realisasi_belum_verifikasi_amount = number_format($realisasi_belum_verifikasi_by_user_amount, 2);
            } else {
                $realisasi_belum_verifikasi_amount = 0;
            }

            $variance_realisasi_belum_incoming_amount = $this->variance_realisasi_belum_incoming_by_user_amount($user->id);
            if ($variance_realisasi_belum_incoming_amount > 0) {
                $variance_realisasi_belum_incoming_amount = number_format($variance_realisasi_belum_incoming_amount, 2);
            } else {
                $variance_realisasi_belum_incoming_amount = 0;
            }

            $variance_realisasi_belum_outgoing_amount = $this->variance_realisasi_belum_outgoing_by_user_amount($user->id);
            if ($variance_realisasi_belum_outgoing_amount > 0) {
                $variance_realisasi_belum_outgoing_amount = number_format($variance_realisasi_belum_outgoing_amount, 2);
            } else {
                $variance_realisasi_belum_outgoing_amount = 0;
            }

            $user->payreq_belum_realisasi_amount = $payreq_belum_realisasi_amount;
            $user->realisasi_belum_verifikasi_amount = $realisasi_belum_verifikasi_amount;
            $user->variance_realisasi_belum_incoming_amount = $variance_realisasi_belum_incoming_amount;
            $user->variance_realisasi_belum_outgoing_amount = $variance_realisasi_belum_outgoing_amount;
            $user->dana_belum_diselesaikan = $this->dana_belum_diselesaikan($user->id);
            $user->payreq_belum_realisasi_list = $this->get_payreqs_belum_realisasi_by_user($user->id);
            $user->realisasi_belum_verifikasi_list = $this->get_realisasi_belum_verifikasi_by_user($user->id);
            $user->variance_realisasi_belum_incoming_list = $this->get_variance_realisasi_belum_incoming_by_user($user->id);
            $user->variance_realisasi_belum_outgoing_list = $this->get_variance_realisasi_belum_outgoing_by_user($user->id);
            $user->display = $this->payreqs_belum_realisasi_by_user_amount($user->id) + $this->realisasi_belum_verifikasi_by_user_amount($user->id) + $this->variance_realisasi_belum_incoming_by_user_amount($user->id) + $this->variance_realisasi_belum_outgoing_by_user_amount($user->id) > 0 ? true : false;
        }

        return $users;
    }

    public function dana_belum_diselesaikan($user_id)
    {
        $total = $this->payreqs_belum_realisasi_by_user_amount($user_id) + $this->realisasi_belum_verifikasi_by_user_amount($user_id) + $this->variance_realisasi_belum_incoming_by_user_amount($user_id) - $this->variance_realisasi_belum_outgoing_by_user_amount($user_id);
        return $total > 0 ? number_format($total, 2) : 0;
    }


    public function payreqs_belum_realisasi_by_user_amount($user_id)
    {
        $amount = $this->get_payreqs_belum_realisasi_by_user($user_id)->sum('total_amount');

        return $amount;
    }

    public function get_payreqs_belum_realisasi_by_user($user_id)
    {
        $payreqs = Payreq::whereIn('status', ['paid', 'split'])->where('user_id', $user_id)->get();
        $payreqIds = $payreqs->pluck('id')->toArray();

        return Outgoing::whereIn('outgoings.payreq_id', $payreqIds)
            ->join('payreqs', 'payreqs.id', '=', 'outgoings.payreq_id')
            ->select('payreqs.nomor as payreq_nomor', 'outgoings.outgoing_date as paid_date', DB::raw('SUM(outgoings.amount) as total_amount'))
            ->groupBy('payreqs.nomor', 'outgoings.outgoing_date')
            ->get();
    }

    public function realisasi_belum_verifikasi_by_user_amount($user_id)
    {
        $amount = $this->get_realisasi_belum_verifikasi_by_user($user_id)->sum('total_amount');

        return $amount;
    }

    public function get_realisasi_belum_verifikasi_by_user($user_id)
    {
        $realizations = Realization::whereIn('status', ['approved', 'reimburse-paid'])->where('user_id', $user_id)->get();
        $realizationIds = $realizations->pluck('id')->toArray();

        return RealizationDetail::whereIn('realization_id', $realizationIds)
            ->join('realizations', 'realizations.id', '=', 'realization_details.realization_id')
            ->select('realizations.nomor as realization_nomor', 'realizations.id as realization_id', DB::raw('SUM(realization_details.amount) as total_amount'), 'realizations.approved_at')
            ->groupBy('realizations.nomor', 'realizations.id', 'realizations.approved_at')
            ->get();
    }

    public function variance_realisasi_belum_incoming_by_user_amount($user_id)
    {
        $total_amount = $this->get_variance_realisasi_belum_incoming_by_user($user_id)->sum('amount');

        return $total_amount;
    }

    public function get_variance_realisasi_belum_incoming_by_user($user_id)
    {
        return Incoming::whereNull('receive_date')
            ->join('realizations', 'incomings.realization_id', '=', 'realizations.id')
            ->where('realizations.user_id', $user_id)
            ->select('realizations.nomor as realization_nomor', 'incomings.amount')
            ->get();
    }

    public function variance_realisasi_belum_outgoing_by_user_amount($user_id)
    {
        $total_amount = $this->get_variance_realisasi_belum_outgoing_by_user($user_id)->sum('amount');

        return $total_amount;
    }

    public function get_variance_realisasi_belum_outgoing_by_user($user_id)
    {
        return Payreq::where('type', 'other')
            ->where('user_id', $user_id)
            ->doesntHave('outgoings')
            ->select('nomor', 'amount')
            ->get();
    }

    public function get_payreq_belum_realisasi($project)
    {
        $payreqs = Payreq::whereIn('status', ['paid', 'split'])->whereIn('project', $project)
            ->get();

        return $payreqs;
    }

    public function get_realisasi_belum_verifikasi($project)
    {
        $realizations = Realization::whereIn('status', ['approved', 'reimburse-paid'])
            ->whereIn('project', $project)
            ->get();

        return $realizations;
    }

    public function get_verifikasi_belum_posted($project)
    {
        $vj_details = VerificationJournalDetail::whereNull('sap_journal_no')
            ->whereIn('project', $project)
            ->where('debit_credit', 'debit')
            ->get();

        return $vj_details;
    }

    public function get_payreq_other_belum_outgoing($project)
    {
        $payreqs = Payreq::where('type', 'other')
            ->whereIn('project', $project)
            ->doesntHave('outgoings')
            ->get();

        return $payreqs;
    }

    public function get_incomings_belum_diterima($project)
    {
        $incomings = Incoming::whereNull('receive_date')
            ->join('realizations', 'incomings.realization_id', '=', 'realizations.id')
            ->whereIn('incomings.project', $project)
            ->select('incomings.*', 'realizations.user_id')
            ->get();

        return $incomings;
    }

    public function get_projects($project)
    {
        if ($project === '000H') {
            $projects = ['000H', 'APS'];
        } else {
            $projects = [$project];
        }

        return $projects;
    }
}
