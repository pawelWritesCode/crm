<?php

namespace App\Http\Controllers;

use App\RecruitmentAttempt;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecruitmentStoryController extends Controller
{
    /**
     * Zwrócenie danych na temat ilości nowych kont w godziniówce
     */
    public function pageReportNewAccountGet(){
        $date_start = date('Y-m-d');
        $date_stop = date('Y-m-d');
        $select_type = 0;

        $data = $this->getReportNewAccountData($date_start, $date_stop);
        return view('recruitment.reportRecruitmentNewAccount')
            ->with('date_start', $date_start)
            ->with('date_stop', $date_stop)
            ->with('select_type', $select_type)
            ->with('data', $data);
    }

    /**
     * Wyszukanie danych na temat ilości nowych kont w godziniówce
     */
    public function pageReportNewAccountPost(Request $request){
        $date_start = $request->date_start;
        $date_stop = $request->date_stop;
        $select_type = 0;

        $data = $this->getReportNewAccountData($date_start, $date_stop);
        //dd($data);

        return view('recruitment.reportRecruitmentNewAccount')
            ->with('date_start', $date_start)
            ->with('date_stop', $date_stop)
            ->with('select_type', $select_type)
            ->with('data', $data);
        return view('recruitment.reportRecruitmentNewAccount');
    }

    /**
     *  Przygotowanie danych do raportu
     */
    public function getReportNewAccountData($date_start, $date_stop){

        $date = DB::table('users')->
        select(DB::raw('count(`users`.`id_manager`) as add_user,count(`users`.`candidate_id`) add_candidate,
            user.first_name,user.last_name,departments.name'))
            ->join('users as user','user.id','users.id_manager')
            ->join('department_info','department_info.id','users.department_info_id')
            ->join('departments','departments.id','department_info.id_dep')
            ->whereBetween('users.created_at', [$date_start . ' 01:00:00', $date_stop . ' 23:00:00'])
            ->groupby('users.id_manager')
            ->get();
        return $date;
    }
    /**
     * Zwrócenie danych na temat ilości rozmów rekrutacyjnych
     */
    public function pageReportInterviewsGet() {
        $date_start = date('Y-m-d');
        $date_stop = date('Y-m-d');
        $select_type = 0;

        $data = $this->getReportInterviewsData($date_start, $date_stop, $select_type);

        return view('recruitment.reportRecruitmentInterviews')
            ->with('date_start', $date_start)
            ->with('date_stop', $date_stop)
            ->with('select_type', $select_type)
            ->with('data', $data);
    }

    /**
     * Wyszukiwanie danych na temta ilości rozmów rekrutacyjnych
     */
    public function pageReportInterviewsPost(Request $request) {
        $date_start = $request->date_start;
        $date_stop = $request->date_stop;
        $select_type = $request->select_type;

        $data = $this->getReportInterviewsData($date_start, $date_stop, $select_type);

        return view('recruitment.reportRecruitmentInterviews')
            ->with('date_start', $date_start)
            ->with('date_stop', $date_stop)
            ->with('select_type', $select_type)
            ->with('data', $data);
    }

    /**
     * Pobranie danych na temat ilości rozmów rekrutacyjnych
     */
    private function getReportInterviewsData($date_start, $date_stop, $select_type) {
        if ($select_type == 0) {
            $data = DB::table('recruitment_attempt')
                ->select(DB::raw('
                    departments.name as dep_name,
                    department_type.name as dep_name_type,
                    count(recruitment_attempt.id) as counted
                '))
                ->join('users', 'users.id', 'recruitment_attempt.cadre_id')
                ->join('department_info', 'users.department_info_id', 'department_info.id')
                ->join('departments', 'departments.id', 'department_info.id_dep')
                ->join('department_type', 'department_type.id', 'department_info.id_dep_type')
                ->whereBetween('interview_date', [$date_start . ' 01:00:00', $date_stop . ' 23:00:00'])
                ->groupBy('users.department_info_id')
                ->get();
        } else if ($select_type == 1) {
            $data = DB::table('recruitment_attempt')
                ->select(DB::raw('
                    first_name,
                    last_name,
                    count(recruitment_attempt.id) as counted
                '))
                ->join('users', 'users.id', 'recruitment_attempt.cadre_id')
                ->whereBetween('interview_date', [$date_start . ' 01:00:00', $date_stop . ' 23:00:00'])
                ->groupBy('users.id')
                ->get();
        }

        return $data;
    }
}
