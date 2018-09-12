<?php

namespace App\Http\Controllers;

use App\ActivityRecorder;
use App\TemplateQuestion;
use App\TemplateUserTest;
use App\TestUsersQuestion;
use App\User;
use Illuminate\Http\Request;
use App\TestCategory;
use App\TestQuestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Session;
use App\UserTest;
use App\UserQuestion;
use App\Department_info;
use Mail;
use Illuminate\Support\Facades\URL;

/**
 * Statusy testu w  UserTest:
 * 1 - Test jest stworzony
 * 2 - Test aktywowany
 * 3 - Test zakończony / do oceny
 * 4 - Test oceniony (do wglądu dla pracownika)
 */

class TestsController extends Controller
{
    /* 
        Wyświetlanie widoku testu dla użytkownika
        Sprawdzane jest pole "user_answer" w tabeli user_questions
        Pytania wybierane są kolejno, tam gdzie "user_answer"  = null
    */
    public function testUserGet($id) {
        $test = UserTest::find($id);

        /**
         * Sprawdzenie czy istnieje test
         */
        if ($test == null) {
            return view('errors.404');
        }

        /**
         * Sprawdzenie czy test należy do użytkownika
         */
        if ($test->user_id != Auth::user()->id) {
            return view('errors.404');
        }

        /**
         * Sprawdzenie czy test jest aktywowany
         */
        if ($test->status < 2) {
            return view('errors.404');
        }

        /**
         * SPrawdzenie czy test został rozpoczęty, jęzeli nie, wrzucamy czas poczatkowy testu
         */
        if ($test->test_start == null) {
            $test->test_start = date('Y-m-d H:i:s');
            $test->save();
        }

        /**
         * Pobranie pierwszego z kolejnosci pytania bez odpowiedzi
         */
        $question = UserQuestion::where('test_id', '=', $id)
            ->whereNull('user_answer')
            ->first();

        /**
         * Zliczenie ilości pytań w teście
         */
        $question_count = $test->questions->count();

        /**
         * Pobranie numeru aktualnego pytania
         */
        $actual_count = UserQuestion::where('test_id', '=', $id)
            ->whereNull('user_answer')
            ->count();

        /* 
            $status = określa czy pytanie jest pierwszym lub ostatnim
            0 - pytanie nie jest początkowe/ostatnie
            1 - pierwsze pytanie
            2 - ostanite pytanie 
            3 - test zakończony
        */

        if ($question_count == $actual_count) {
            // jezeli ilość pytań bez odpowiedzi jest rowna sumie pytań to pytaine jest pierwsze 
            $status = 1;
        } else if ($actual_count <= 0) {
            //jezeli ilosc pytan bez odpowiedzi jest mniejsza lub rowna 0 to pytanie jest ostatnie
            $status = 2;
        } else {
            //jezeli pytanie nie jest pierwsze lub ostatnie to pytanie jest środkowe (MR OBVIOUS)
            $status = 0;
        }

        /**
         * Jezeli pytanie nie istnieje (koniec testu)
         */
        if ($question == null) {

            /**
             * Jezeli status testu jest mniejszy niz 4 (status oceniony)
             * to pytanie ma status 3 (do oceny)
             */
            if ($test->status < 4) {
                $status = 3;
                $test->status = 3;
                /**
                 * Jezeli pytanie nie istnieje i nie ma daty zakończenia testu, ustalamy ją
                 */
                if ($test->date_stop == null) {
                    $test->test_stop = date('Y-m-d H:i:s');
                }
                $test->save();
            }
            /**
             * Sprawdzenie czy test jest oceniony
             */
            if ($test->status == 4) {
                $status = 3;
            }
        }

        /**
         * Pobranie treści pytania
         */
        if ($question != null) {
            //odejmujemy od czasu rozpoczęcia pytania czas na jego rozwiązanie i nadpisujemy 
            $testQuestion = TestQuestion::where('id', '=', $question->question_id)->get();
        } else {
            //zdefiniowanie zmiennej (coś w widoku się wykrzacza bez niej)
            $testQuestion = false;
        }

        /**
         * Sprawdzenie czy była podjęta próba odpowiedzi
         * Sprawdzenie czy pytanie zostało juz rozpoczęte (w przypadku odświerzenia strony)
         */
        if ($question != null && $question->user_answer == null && $question->attempt != null)
            $rest_of_time = $question->available_time - (strtotime($question->attempt) - time()) * (-1);
        else 
            $rest_of_time = false;

        return view('tests.userTest')
            ->with('test', $test)
            ->with('rest_of_time', $rest_of_time)
            ->with('testQuestion', $testQuestion[0])
            ->with('status', $status)
            ->with('question_count', $question_count)
            ->with('actual_count', $question_count - $actual_count + 1)
            ->with('question', $question);
    }

    /* 
        Przesłanie odpowiedzi przez użytkownika
    */
    public function testUserPost(Request $request) {
        $question = UserQuestion::find($request->question_id);
        $questionToLog = UserQuestion::find($request->question_id);
        if ($question->test->user_id != Auth::user()->id) {
            return view('errors.404');
        }

        if ($question == null) {
            return view('errors.404');
        }

        $question->user_answer = ($request->user_answer != null) ? $request->user_answer : 'Brak odpowiedzi' ;
        $answer_time = $request->answer_time;
        $question->answer_time = $request->available_time - $answer_time * (-1);

        $LogData = array_merge(['T ' => 'Zapisanie odpowiedzi testu'],$questionToLog->toArray());
        new ActivityRecorder($LogData, 98, 1);
        $question->save();

        return Redirect::back();
    }

    /**
     * Funkcja zwracająca widok z dostępem do wszystkich zakończonych testów
     */
    public function allTestsGet() {
        $tests = UserTest::whereIn('status', [3])->get();

        return view('tests.allTests')
            ->with('tests', $tests);
    }

    /**
     * Wyświetlanie wyników testu
     */
    public function testResult($id) {
        $test = UserTest::find($id);
        
        /**
         * Sprawdzenie czy istnieje test
         */
        if ($test == null) {
            return view('errors.404');
        }

        /**
         * Podgląd testu dostępny jedynie dla osoby przeprowadzajacej test
         */
        if ($test->status == 1 || $test->status == 2) {
            if ($test->cadre_id != Auth::user()->id) {
                return view('errors.404');
            }
        }

        /**
         * sprawdzenie czy test został oceniony i osoba testowana może mieć do niego wgląd
         */
        if ($test->user_id == Auth::user()->id && $test->status != 4) {
            return view('errors.404');
        }


        /**
         * Sprawdzenie czy użytkownik ma uprawnienia do podglądania ocen wszystkich testów
         */
        $ids = DB::table('privilage_user_relation')
            ->select(DB::raw('
                DISTINCT(user_id)
            '))
            ->get();

        $arr = [];
        foreach($ids as $item) {
            $arr[] = $item->user_id;
        }
        //gdy test jest stworzony przez osobę zarządzającą lub jest to osoba wypełniająca test
        if($test->user_id == Auth::user()->id || $test->cadre_id == Auth::user()->id){
            return view('tests.testResult')
                ->with('test', $test);
        }
        else if (!in_array(Auth::user()->id, $arr)) {
                return view('errors.404');
            }

        return view('tests.testResult')
            ->with('test', $test);
    }

    /* 
        Wyświetlenie wszystkich testów użytkownika
    */
    public function allUserTests() {
        $tests = UserTest::where('user_id', '=', Auth::user()->id)->get();

        return view('tests.allUserTests')
            ->with('tests', $tests);
    }

    /* 
        Dodanie testu przez osobę testującą
    */

    public function addTestGet() {

        if(Auth::user()->user_type_id == 4){
            // pobranie kategorii dla konsultantów
            $categories = TestCategory::where('deleted','=',0)
                ->where('level_category','=','1')->get();
            // pobranie wszystkich Konsultantów(pracujących)
            $cadre = User::where('status_work','=',1)
                ->whereIn('user_type_id',[1,2])
                ->orderBy('last_name');
            if(Auth::user()->id != 1364){
                $cadre = $cadre->where('coach_id','=',Auth::user()->id);
            }
            $cadre = $cadre->get();
            $teplate = TemplateUserTest::where('deleted',0)
                    ->where('level_template','=','1')
                    ->get();
        }else{
            // pobranie wszystkich kategorii dla kadry
            $categories = TestCategory::where('deleted','=',0)
                ->where('level_category','=','0')->get();
            // pobranie wszystkich pracowników kardy(pracujących)
            $cadre = User::where('status_work','=',1)
                ->whereNotin('user_type_id',[1,2])->orderBy('last_name')->get();
            $teplate = TemplateUserTest::where('deleted',0)
                ->where('level_template','=','0')
                ->get();
        }
        //generowanie widoku
        return view('tests.addTest')
            ->with('categories',$categories)
            ->with('users',$cadre)
            ->with('template',$teplate);
    }

    /*
     * Przygotowanie danych do datatable, związanych z pytaniami na konkretną kategorię Datatable
     */
    public function showQuestionDatatable(Request $request)
    {
        if($request->ajax())
        {
            $query = TestQuestion::where('category_id',$request->category_id)
                ->where('deleted','=',0)->get();
            return datatables($query)
                ->rawColumns(['content'])
                ->make(true);
        }
    }
    /*
     *  Zapisywanie testu
     */
    public function saveTestWithUser(Request $request)
    {
        if($request->ajax()){
            $all_users = User::whereIn('id',$request->id_user_tab)->get();
            $find_template = TemplateUserTest::where('id',$request->template_id)->get();
            if(($all_users->count() == count($request->id_user_tab)) && ($request->template_id == 0 || ($find_template->count() == 1))) {
                    // wyłuskanie wszystkoch użytkowników
                    for ($i = 0; $i < count($request->id_user_tab); $i++) {
                        $new_test = new UserTest();
                        $new_test->cadre_id = Auth::user()->id;
                        $new_test->user_id = $request->id_user_tab[$i];
                        $new_test->status = 1;
                        $new_test->template_id = ($request->template_id != 0) ? $request->template_id : null ;
                        $new_test->name = $request->subject;
                        $new_test->save();
                        $id_test = $new_test->id;
                        $question_array = $request->question_test_array;

                        foreach ($question_array as $item) {
                            $new_user_question = new UserQuestion();
                            $new_user_question->test_id = $id_test;
                            $new_user_question->question_id = $item['id'];
                            $new_user_question->available_time = $item['time'] * 60;
                            $new_user_question->save();
                            $new_many_to_many = new TestUsersQuestion();
                            $new_many_to_many->user_question_id = $new_user_question->id;
                            $new_many_to_many->test_question_id = $item['id'];
                            $new_many_to_many->save();
                        }
                    }
                $LogData = ['T ' => 'Zapisanie testu'];
                new ActivityRecorder($LogData, 97, 1);
                    Session::flash('message_ok', "Test został dodany!");
                    return 1;
                }
             return 0;
            }
        return 0;
    }

    // edycja testu ( usunięcie i wgranie ponownie
    public function editTestWithUser(Request $request)
    {
        if($request->ajax()) {
            // Sekcja usuwania
            //usunięcie wszystkich pytań danego testu
            $test_id = $request->test_id;
            $status = UserTest::find($test_id);
            $all_users = User::where('id',$request->id_user)->get();

            $find_template = TemplateUserTest::where('id',$request->template_id)->get();
            if($status != null ) {
                if (($all_users->count() == 1) && ($request->template_id == 0 || ($find_template->count() == 1))) {
                    if ($status->status == 1) {

                        $user_question = UserQuestion::where('test_id', $test_id)->get();
                        foreach ($user_question as $item) {
                            // usuniecie z pytań do testu
                            TestUsersQuestion::where('user_question_id', '=', $item->id)->delete();
                        }
                        // usunięcie z pytań testu
                        UserQuestion::where('test_id', $test_id)->delete();
                        // usunięcie testu
                        UserTest::where('id', '=', $test_id)->delete();


                            $new_test = new UserTest();
                            $new_test->cadre_id = Auth::user()->id;
                            $new_test->user_id = $request->id_user;
                            $new_test->status = 1;
                            $new_test->template_id = ($request->template_id != 0) ? $request->template_id : null ;
                            $new_test->name = $request->subject;
                            $new_test->save();
                            $id_test = $new_test->id;
                            $question_array = $request->question_test_array;

                            foreach ($question_array as $item) {
                                $new_user_question = new UserQuestion();
                                $new_user_question->test_id = $id_test;
                                $new_user_question->question_id = $item['id'];
                                $new_user_question->available_time = $item['time'] * 60;
                                $new_user_question->save();
                                $new_many_to_many = new TestUsersQuestion();
                                $new_many_to_many->user_question_id = $new_user_question->id;
                                $new_many_to_many->test_question_id = $item['id'];
                                $new_many_to_many->save();
                            }
                        $LogData = ['T ' => 'Edycja testu '.$test_id];
                        new ActivityRecorder($LogData, 108, 2);
                        Session::put('message_ok', "Test został zmieniony!");
                        return 1;
                    }
                    return 0;
                }
                return 0;
            }
            return 0;
        }
        return 0;

    }
    // Wysłanie infromacji o użytkowniku, jakie pytania już rozwiązał
    public function getRepeatQuestion (Request $request)
    {
        if($request->ajax())
        {   // chwilowo tylko sprawdza czy rozwiązywał a nie ile razy to robił
            $user_question_repeat = DB::table('user_questions')
            ->select(DB::raw('
                Distinct(question_id)
            '))
                ->join('user_tests', 'user_tests.id', 'user_questions.test_id')
                ->join('users', 'users.id', 'user_tests.user_id')
                ->where('users.id',$request->id_user)
                ->get();
            return response()->json($user_question_repeat);
        }
    }
        /*
        * Usuniecie testu
        */
    public function deleteTest($id)
    {
            $test_by_id = UserTest::find($id);
            if($test_by_id->status == 1) {
                $user_question = UserQuestion::where('test_id', $id)->get();
                foreach ($user_question as $item) {
                    // usuniecie z pytań do testu
                    TestUsersQuestion::where('user_question_id', '=', $item->id)->delete();
                }
                // usunięcie z pytań testu
                UserQuestion::where('test_id', $id)->delete();
                // usunięcie testu
                UserTest::where('id', '=', $id)->delete();
            }
        $LogData = array_merge(['T ' => 'Usunięcie testu '],$test_by_id->toArray());
        new ActivityRecorder($LogData, 100, 3);
         Session::flash('message_delete', "Test został usuniety!");
         return redirect('show_tests');
    }

    /*
     * Podgląd testu
     */
    public function viewTest($id)
    {
        // pobranie informacji o teście
        $test_by_id = UserTest::find($id);
        if($test_by_id != null) {
            if ($test_by_id->status == 1 || $test_by_id->status == 2) {
                // pobranie pytań z testu
                $all_question_id = $test_by_id->questions()->get();
                //Sprawdzenie czy jest to wersja dla trenerów
                if(Auth::user()->user_type_id == 4){
                    // pobranie kategorii dla konsultantów
                    $categories = TestCategory::where('deleted','=',0)
                        ->where('level_category','=','1')->get();
                    // pobranie wszystkich Konsultantów(pracujących)
                    $cadre = User::where('status_work','=',1)
                        ->whereIn('user_type_id',[1,2])
                        ->orderBy('last_name');
                    if(Auth::user()->id != 1364){
                        $cadre = $cadre->where('coach_id','=',Auth::user()->id);
                    }
                    $cadre = $cadre->get();
                    $teplate = TemplateUserTest::where('deleted',0)
                        ->where('level_template','=','1')
                        ->get();
                }else{
                    // pobranie wszystkich kategorii dla kadry
                    $categories = TestCategory::where('deleted','=',0)
                        ->where('level_category','=','0')->get();
                    // pobranie wszystkich pracowników kardy(pracujących)
                    $cadre = User::where('status_work','=',1)
                        ->whereNotin('user_type_id',[1,2])->orderBy('last_name')->get();
                    $teplate = TemplateUserTest::where('deleted',0)
                        ->where('level_template','=','0')
                        ->get();
                }
                //generowanie widoku
                $all_question = array();
                foreach ($all_question_id as $item) {
                    $content_question = $item->testQuestion()->get();
                    $category_name = TestCategory::where('id', '=', $content_question[0]->category_id)->get();
                    array_push($all_question, ["id_question" => $item->question_id, "content" => $content_question[0]->content, "category_name" => $category_name[0]->name, "avaible_time" => $item->available_time]);
                }

                return view('tests.viewTest')
                    ->with('test_by_id', $test_by_id)
                    ->with('all_question', $all_question)
                    ->with('categories', $categories)
                    ->with('users', $cadre)
                    ->with('template', $teplate);
            } else {
                return redirect('show_tests');
            }
        }else{
             return redirect('show_tests');
        }
    }

    /*
     * Pobranie pytań do szablonu
     */
    public function getTemplateQuestion(Request $request)
    {
        if($request->ajax())
        {
            $question_id = TemplateQuestion::select('question_id','question_time',
                'test_questions.content','test_categories.name')
                ->join('test_questions','question_id','test_questions.id')
                ->join('test_categories','test_questions.category_id','test_categories.id')
                ->where('template_id',$request->template_id)
                ->get();
            return $question_id;
        }
        return 0;
    }

    /* 
        Zapis testu przez osobę testującą
    */
    public function addTestPost(Request $request) {
        /**
         * Ta funkcia jest przejebana ajaxem 
         */
    }

    /*
        Dodawanie szablonu testu
    */
    public function addTestTemplate()
    {
        if(Auth::user()->user_type_id == 4){
            // pobranie kategorii dla konsultantów
            $categories = TestCategory::where('deleted','=',0)
                ->where('level_category','=','1')->get();
            // pobranie wszystkich Konsultantów(pracujących)
            $cadre = User::where('status_work','=',1)
                ->whereIn('user_type_id',[1,2])
                ->orderBy('last_name');
            if(Auth::user()->id != 1364){
                $cadre = $cadre->where('coach_id','=',Auth::user()->id);
            }
            $cadre = $cadre->get();
        }else{
            // pobranie wszystkich kategorii dla kadry
            $categories = TestCategory::where('deleted','=',0)
                ->where('level_category','=','0')->get();
            // pobranie wszystkich pracowników kardy(pracujących)
            $cadre = User::where('status_work','=',1)
                ->whereNotin('user_type_id',[1,2])->orderBy('last_name')->get();
        }

        return view('tests.addTestTemplate')
            ->with('categories',$categories)
            ->with('users',$cadre);
    }

    /*
       Zapisywanie szablonu testu
   */
    public function saveTestTemplate(Request $request)
    {
        if($request->ajax())
        {
                $new_template = new TemplateUserTest();
                $new_template->template_name = $request->template;
                $new_template->cadre_id = Auth::user()->id;
                $new_template->name= $request->subject;
                $new_template->level_template = Auth::user()->user_type_id == 4 ? 1 : 0;
                $new_template->save();
                $LogData = array_merge(['T ' => 'Zapisanie szablonu testu'],$new_template->toArray());
                new ActivityRecorder($LogData, 106, 1);
                $id_template = $new_template->id;
                $question_array = $request->question_test_array;

                foreach ($question_array as $item)
                {
                    $new_template_question = new TemplateQuestion();
                    $new_template_question->template_id = $id_template;
                    $new_template_question->question_id = $item['id'];
                    $new_template_question->question_time = $item['time']*60;
                    $new_template_question->save();
                }
                return 1;
            }
            return 0;
    }

    /*
        Wykaz Szablonów
    */
    public function showTestTemplate()
    {
        if(Auth::user()->user_type_id == 4){
            $template = TemplateUserTest::where('deleted','=',0)
                ->where('level_template','=',1)
                ->get();
        }else if(Auth::user()->id != 1364) {
            $template = TemplateUserTest::where('deleted', '=', 0)
                ->where('level_template', '=', 0)
                ->get();
            }
        else{
            $template = TemplateUserTest::where('deleted', '=', 0)
                ->get();
        }
        return view('tests.showTemplate')
            ->with('template',$template);
    }
    /*
     * Usuwanie szablonu
     */
    public function deleteTemplate($id)
    {
        $search_template = TemplateUserTest::find($id);
        $search_template->deleted = 1;
        $search_template->cadre_id = Auth::user()->id;
        $search_template->save();
        $LogData = array_merge(['T ' => 'Zmiana statusu szablonu'],$search_template->toArray());
        new ActivityRecorder($LogData, 106, 4);
        Session::flash('message_delete','Szablon został usunięty');
        return redirect('showTestTemplate');
    }
    /*
     * Wyświetlenie szablonu
     */
    public function viewTestTemplate($id)
    {
        $template = TemplateUserTest::find($id);
        $user = User::find($template->cadre_id);
        if(Auth::user()->user_type_id == $user->user_type_id && Auth::user()->user_type_id == 4)
        {
            // pobranie kategorii dla konsultantów
            $categories = TestCategory::where('deleted','=',0)
                ->where('level_category','=','1')->get();
            // pobranie wszystkich Konsultantów(pracujących)
            $cadre = User::where('status_work','=',1)
                ->whereIn('user_type_id',[1,2])
                ->orderBy('last_name');
            if(Auth::user()->id != 1364){
                $cadre = $cadre->where('coach_id','=',Auth::user()->id);
            }
            $cadre = $cadre->get();
        }else if(Auth::user()->user_type_id != 4){
            // pobranie wszystkich kategorii dla kadry
            $categories = TestCategory::where('deleted','=',0)
                ->where('level_category','=','0')->get();
            // pobranie wszystkich pracowników kardy(pracujących)
            $cadre = User::where('status_work','=',1)
                ->whereNotin('user_type_id',[1,2])->orderBy('last_name')->get();
        }else{
            return view('errors.404');
        }
        $template = TemplateUserTest::find($id);
        $template_content = TemplateUserTest::find($id)->questionsData();


        return view('tests.viewTestTemplate')
            ->with('template',$template)
            ->with('template_content',$template_content)
            ->with('categories',$categories)
            ->with('users',$cadre);
    }
    /*
     * Zapisanie szablonu po edycji
     */
    public function saveEditTemplate(Request $request)
    {
        if($request->ajax()){
                // wysłuskanie szukanego szablonu
                $template = TemplateUserTest::find($request->template_id);
                $template->template_name = $request->template;
                $template->name = $request->subject;
                $template->save();
                $LogData = array_merge(['T ' => 'Edycja szablonu'],$template->toArray());
                new ActivityRecorder($LogData, 106, 2);
                //usunięcie wszystkich pytań szablonu

                //Dodanie nowych pytań
            TemplateQuestion::where('template_id',$request->template_id)->delete();
                    $question_array = $request->question_test_array;
                    foreach ($question_array as $item) {
                        $new_user_question = new TemplateQuestion();
                        $new_user_question->template_id = $request->template_id;
                        $new_user_question->question_id = $item['id'];
                        $new_user_question->question_time = $item['time']*60;
                        $new_user_question->save();
                    }

                Session::flash('message_ok', "Szablon został zmieniony!");
                return 1;
        }
        return 0;
    }


    /*
        Wyświetlenie wsyzstkic testów osoby testującej
    */
    public function showTestsGet() {
        $tests = UserTest::where('cadre_id', '=', Auth::user()->id)
            ->orWhere('checked_by', '=', Auth::user()->id)
            ->get();

        return view('tests.showTest')
            ->with('tests', $tests);
    }

    /* 
        Zmiana statusu testu (osoba testująca)
    */
    public function showTestsPost(Request $request) {
        //Ta funkcja jest przejebana do activateTest()
    }

    /* 
        Ocena testu
    */
    public function testCheckGet($id) {
        $test = UserTest::find($id);

        if ($test == null) {
            return view('errors.404');
        }

        /**
         * Sprawdzenie czy test jest zakończony
         */
        if ($test->status < 3) {
            return view('errors.404');
        }

        /**
         * Sprawdzenie czy osoba nie sprawdza testu sama sobie
         */
        // if ($test->user_id == Auth::user()->id) {
        //     return view('errors.404');
        // }

        return view('tests.checkTest')
            ->with('test', $test);
    }

    /* 
        Zapis oceny testu
    */
    public function testCheckPost(Request $request) {
        $test = UserTest::find($request->test_id);
        
        if ($test == null) {
            return view('errors.404');
        }

        /**
         * Zdefiniowanie początkowej zmiennej przechowującej sumaryczny wynik testu
         */
        $result = 0;

        /**
         * Przekazanie danych na temat testu do tablic
         */
        $cadre_comments = $request->comment_question;
        $cadre_result = $request->question_result;

        /**
         * Zapis danych o pytaniach
         */
        foreach($test->questions as $question) {
            /**
             * Dodanie komentarza do pytania
             * Defaultowo 'Brak komentarza'
             */
            $question->cadre_comment = ($cadre_comments[0] != null) ? $cadre_comments[0] : 'Brak komentarza.' ;
            $question->result = ($cadre_result[0] != null) ? intval($cadre_result[0]) : 0 ;
            $question->save();
            /**
             * Dodanie wyniku pytania do sumarycznego wyniku testu
             */
            $result += intval($cadre_result[0]);
            /**
             * usunięcie pierwszych elementów tablicy z pytaniami
             */
            array_shift($cadre_comments);
            array_shift($cadre_result);
        }

        /**
         * Zmiana statusu testu na oceniony
         */
        $test->status = 4;
        /**
         * Zapis sumarycznego wyniku testu
         */
        $test->result = $result;

        /**
         * Zapis użytkownika sprawdzającego test
         */
        $test->checked_by = Auth::user()->id;

        $test->save();

        /**
         * Pobranie danych do maila
         */
        $user = User::find($test->user_id);

        $user_mail = $user->username;
        $user_name = $user->first_name . ' ' . $user->last_name;
        $mail_title = 'Ocena testu';
        $data = [
            'id' => $test->id,
            'result' => $result . '/' . $test->questions->count()
        ];
        $mail_type = 'mailChecked';
        
        /**
         * Przesłanie maila z informacją o wyniku testu
         */
        if($user->user_type_id != 1 && $user->user_type_id != 2){
            if (filter_var($user->username, FILTER_VALIDATE_EMAIL)) {
                $this->sendMail($user->username, $user_name, $mail_title, $data, $mail_type);
            }
            else if (filter_var($user->email_off, FILTER_VALIDATE_EMAIL)) {
                $this->sendMail($user->email_off, $user_name, $mail_title, $data, $mail_type);
            }
        }
        $LogData = array_merge(['T ' => 'Zapis oceny testu'],$test->toArray());
        new ActivityRecorder($LogData, 101, 2);

        Session::flash('message_ok', "Ocena została przesłana!");
        return Redirect::back();
    }

    /*
        Sttatystyki testów dla osoby testującej TRENERZY
    */
    public function testsStatisticsCoachGet() {
        $departments_stats = DB::table('user_tests')
            ->select(DB::raw('
                departments.name as dep_name,
                department_type.name as dep_type_name,
                count(user_tests.id) as dep_sum
            '))
            ->join('users', 'users.id', 'user_tests.user_id')
            ->join('department_info', 'users.department_info_id', 'department_info.id')
            ->join('departments', 'departments.id', 'department_info.id_dep')
            ->join('department_type', 'department_type.id', 'department_info.id_dep_type')
            ->groupBy('users.department_info_id')
            ->whereIn('users.user_type_id', [1,2])
            ->get();

        $results = DB::table('user_questions')
            ->select(DB::raw('
                SUM(CASE WHEN user_questions.result = 1 THEN 1 ELSE 0 END) as good,
                SUM(CASE WHEN user_questions.result = 0 THEN 1 ELSE 0 END) as bad
            '))
            ->join('user_tests', 'user_tests.id', 'user_questions.test_id')
            ->join('users', 'users.id', 'user_tests.user_id')
            ->whereIn('users.user_type_id', [1,2])
            ->get();

        $users = User::whereIn('user_type_id', [1,2])
            ->orderBy('last_name')
            ->where('status_work', '=', 1)
            ->where('department_info_id','=',Auth::user()->department_info_id)
            ->get();

        return view('tests.testsStatistics')
            ->with('users', $users)
            ->with('results', $results[0])
            ->with('departments_stats', $departments_stats)
            ->with('redirect',0);
    }

    /* 
        Sttatystyki testów dla osoby testującej
    */
    public function testsStatisticsGet() {
        $departments_stats = DB::table('user_tests')
            ->select(DB::raw('
                departments.name as dep_name,
                department_type.name as dep_type_name,
                count(user_tests.id) as dep_sum
            '))
            ->join('users', 'users.id', 'user_tests.user_id')
            ->join('department_info', 'users.department_info_id', 'department_info.id')
            ->join('departments', 'departments.id', 'department_info.id_dep')
            ->join('department_type', 'department_type.id', 'department_info.id_dep_type')
            ->whereNotIn('users.user_type_id', [1,2])
            ->groupBy('users.department_info_id')
            ->get();

        $results = DB::table('user_questions')
            ->select(DB::raw('
                SUM(CASE WHEN user_questions.result = 1 THEN 1 ELSE 0 END) as good,
                SUM(CASE WHEN user_questions.result = 0 THEN 1 ELSE 0 END) as bad
            '))
            ->join('user_tests', 'user_tests.id', 'user_questions.test_id')
            ->join('users', 'users.id', 'user_tests.user_id')
            ->whereNotIn('users.user_type_id', [1,2])
            ->get();

        $users = User::whereNotIn('user_type_id', [1,2])
            ->orderBy('last_name')
            ->where('status_work', '=', 1)
            ->get();

        return view('tests.testsStatistics')
            ->with('users', $users)
            ->with('results', $results[0])
            ->with('departments_stats', $departments_stats)
            ->with('redirect',1);
    }


    /**
     * Przekierowanie do statystyk konkretnego użytkownika KONSULTANTA
     */
    public function testsStatisticsCoachPost(Request $request) {
        $id = $request->user_id;
        $check = User::find($id);
        if ($check == null || $check->user_type_id >2) {
            return view('errors.404');
        }

        return redirect('/employee_statistics/' . $id);

    }

    /**
     * Przekierowanie do statystyk konkretnego użytkownika
     */
    public function testsStatisticsPost(Request $request) {
        $id = $request->user_id;

        $check = User::find($id);

        if ($check == null || $check->user_type_id == 1 || $check->user_type_id == 2) {
            return view('errors.404');
        }

        return redirect('/employee_statistics/' . $id);

    }

    /* 
        Wyświetlanie widoku dla panelu administarcyjnego testów
    */
    public function testsAdminPanelGet() {

        if(Auth::user()->user_type_id == 4)
        {
            $testCategory = TestCategory::where('level_category','=',1)
                            ->get();
        }else
            $testCategory = TestCategory::all();

        return view('tests.testsAdminPanel')
            ->with('testCategory', $testCategory);
    }

    /* 
        Zapisywanie zmian (panel administatorski testów)
    */
    public function testsAdminPanelPost(Request $request) {
        $category = new TestCategory();
        $category->name = $request->category_name;
        $category->user_id = Auth::user()->id;
        $category->cadre_id = Auth::user()->id;
        if(Auth::user()->user_type_id == 4){
            $category->level_category = 1;
        }else{
            $category->level_category = $request->coach_checkbox == null ? 0 : 1;
        }
        $category->created_at = date('Y-m-d H:i:s');
        $category->updated_at = date('Y-m-d H:i:s');
        $category->deleted = 0;
        $category->save();

        $LogData = array_merge(['T ' => 'Zapisanie kategorii testu'],$category->toArray());
        new ActivityRecorder($LogData, 96, 1);
        Session::flash('message_ok', "Kategoria została dodana!");
        return Redirect::back();
    }

    /* 
        Statystyki poszczególnych pracowników
    */
    public function employeeTestsStatisticsGet($id) {
        $user = User::find($id);

        if ($user == null) {
            return view('errors.404');
        }
        if(Auth::user()->user_type_id == 4){
            if($user->user_type_id > 2){
                return view('errors.404');
            }
        }

        $cadre = DB::table('user_tests')
            ->select(DB::raw('
                first_name,
                last_name,
                count(*) as cadre_sum
            '))
            ->join('users', 'users.id', 'user_tests.cadre_id')
            ->where('user_tests.user_id', '=', $id)
            ->groupBy('users.id')
            ->get();

        $categories = DB::table('user_questions')
            ->select(DB::raw('
                test_categories.name as name,
                count(*) as sum
            '))
            ->join('user_tests', 'user_tests.id', 'user_questions.test_id')
            ->join('test_questions', 'test_questions.id', 'user_questions.question_id')
            ->join('test_categories', 'test_categories.id', 'test_questions.category_id')
            ->where('user_tests.user_id', '=', $id)
            ->groupBy('test_categories.id')
            ->get();

        $stats = DB::table('user_questions')
            ->select(DB::raw('
                sum(CASE WHEN user_questions.result = 1 THEN 1 else 0 END) as user_good,
                sum(CASE WHEN user_questions.result = 0 THEN 1 else 0 END) as user_wrong
            '))
            ->join('user_tests', 'user_tests.id', 'user_questions.test_id')
            ->where('user_tests.user_id', '=', $id)
            ->where('user_tests.result', '!=', null)
            ->get();

        return view('tests.employeeStatistics')
            ->with('stats', $stats[0])
            ->with('categories', $categories)
            ->with('cadre', $cadre)
            ->with('user', $user);
    }

    /* 
        Statystyki poszczególnych oddziałów
    */
    public function departmentTestsStatisticsGet() {
        $department_info = Department_info::all();
        return view('tests.departmentStatistics') 
            ->with('department_info', $department_info);
    }

    /**
     * Statystyk wybranego oddziału
     */

     public function departmentTestsStatisticsPost(Request $request) {
        $id = $request->dep_id;
        // Określenie grupy statystyk
        $type_statistics = $request->type_statistic;
        $department = Department_info::find($id);

        if ($department == null) {
            return view('errors.404');
        }

        $department_info = Department_info::all();

        /**
         * Pobranie ilości przeprowadznych testow w oddziale
         */
        $count_dep_test_sum = DB::table('user_tests')
            ->select(DB::raw('
                count(*) as dep_sum
            '))
            ->leftJoin('users', 'users.id', 'user_tests.user_id')
            ->where('users.department_info_id', '=', $id);
            if($type_statistics == 1)
                $count_dep_test_sum = $count_dep_test_sum
                    ->whereIn('users.user_type_id',[1,2]);
            else
                $count_dep_test_sum = $count_dep_test_sum
                    ->whereNotIn('users.user_type_id',[1,2]);
        $count_dep_test_sum = $count_dep_test_sum->get();

        /**
         * Pobranie ilości dobrych i złych odpowiedzi
         */
        $results = DB::table('user_questions')
            ->select(DB::raw('
                sum(CASE WHEN user_questions.result = 1 THEN 1 ELSE 0 END) as dep_good,
                sum(CASE WHEN user_questions.result = 0 THEN 1 ELSE 0 END) as dep_wrong
            '))
            ->join('user_tests', 'user_tests.id', 'user_questions.test_id')
            ->join('users', 'users.id', 'user_tests.user_id')
            ->where('user_tests.result', '!=', null)
            ->where('users.department_info_id', '=', $id);
         if($type_statistics == 1){
             $results = $results
                 ->whereIn('users.user_type_id',[1,2]);
         }else{
             $results = $results
                 ->whereNotIn('users.user_type_id',[1,2]);
         }
         $results = $results->get();

        /**
         * Pobranie ilosci testow na uzytkownika
         */
        $tests_by_user = DB::table('user_tests')
            ->select(DB::raw('
                first_name,
                last_name,
                count(*) as user_sum
            '))
            ->leftJoin('users', 'users.id', 'user_tests.user_id')
            ->where('users.department_info_id', '=', $id);
         if($type_statistics == 1){
             $tests_by_user = $tests_by_user
                 ->whereIn('users.user_type_id',[1,2]);
         }else{
             $tests_by_user = $tests_by_user
                 ->whereNotIn('users.user_type_id',[1,2]);
         }
         $tests_by_user = $tests_by_user->groupBy('users.id')
            ->get();

        /**
         * Pobranie ilosci wykonanych testow przez kadre
         */
        $tests_by_cadre = DB::table('user_tests')
            ->select(DB::raw('
                first_name,
                last_name,
                count(*) as user_sum
            '))
            ->leftJoin('users', 'users.id', 'user_tests.cadre_id')
            ->where('users.department_info_id', '=', $id);
         if($type_statistics == 1){
             $tests_by_cadre = $tests_by_cadre
                 ->whereIn('users.user_type_id',[4]);
         }else{
             $tests_by_cadre = $tests_by_cadre
                 ->whereNotIn('users.user_type_id',[4]);
         }
         $tests_by_cadre = $tests_by_cadre->groupBy('users.id')
            ->get();

        /**
         * Pobranie ulości pytan ze względu na kategorię
         */
        $categories = DB::table('user_questions')
            ->select(DB::raw('
                test_categories.name as name,
                count(*) as sum
            '))
            ->join('user_tests', 'user_tests.id', 'user_questions.test_id')
            ->join('test_questions', 'test_questions.id', 'user_questions.question_id')
            ->join('test_categories', 'test_categories.id', 'test_questions.category_id')
            ->join('users', 'users.id', 'user_tests.user_id');
             if($type_statistics == 1){
                 $categories = $categories
                     ->whereIn('users.user_type_id',[1,2]);
             }else{
                 $categories = $categories
                     ->whereNotIn('users.user_type_id',[1,2]);
             }
            $categories = $categories->where('users.department_info_id', '=', $id)
            ->groupBy('test_categories.id')
            ->get();

        return view('tests.departmentStatistics')
            ->with('results', $results[0])
            ->with('dep_sum', $count_dep_test_sum[0]->dep_sum)
            ->with('id', $id)
            ->with('tests_by_user', $tests_by_user)
            ->with('tests_by_cadre', $tests_by_cadre)
            ->with('categories', $categories)
            ->with('department_info', $department_info)
            ->with('department', $department)
            ->with('type_statistic',$type_statistics);
     }

    /* 
        Statystyki poszczególnych testów
    */
    public function testStatisticsGet() {
        if(Auth::user()->user_type_id == 4){
            $templates = TemplateUserTest::where('level_template','=',1)->get();
        }else{
            $templates = TemplateUserTest::all();
        }

        return view('tests.oneTestStatistics')
            ->with('templates', $templates);
    }

    public function testStatisticsPost(Request $request) {
        $id = $request->template_id;

        $test = TemplateUserTest::find($id);

        if ($test == null) {
            return view('errors.404');
        }

        $templates = TemplateUserTest::all();

        /**
         * Funkcja zliczająca wyniki pracownikow  dla danego testu
         */

        $results = DB::table('user_questions')
            ->select(DB::raw('
                SUM(CASE WHEN user_questions.result is null THEN 1 ELSE 0 END) as not_judged,
                SUM(CASE WHEN user_questions.result = 1 THEN 1 ELSE 0 END) as good,
                SUM(CASE WHEN user_questions.result = 0 THEN 1 ELSE 0 END) as bad
            '))
            ->join('user_tests', 'user_tests.id', 'user_questions.test_id')
            ->where('user_tests.template_id', '=', $id)
            ->get();

        return view('tests.oneTestStatistics')
            ->with('results', $results[0])
            ->with('templates', $templates)
            ->with('test', $test);
    }

    /* 
        ******************** AJAX REQUESTS ************************
    */

    /**
     * Funkcja dodająca pytania testowe
     */
    public function addTestQuestion(Request $request) {
        if ($request->ajax()) {
            $question = new TestQuestion();

            $question->content = $request->content;
            $question->default_time = $request->question_time * 60;
            $question->category_id = $request->category_id;
            $question->created_at = date('Y-m-d H:i:s');
            $question->updated_at = date('Y-m-d H:i:s');
            $question->cadre_by = Auth::user()->id;
            $question->user_id = Auth::user()->id;
            $question->deleted = 0;

            $question->save();
            $LogData = array_merge(['T ' => 'Nowe pytanie testowe'],$question->toArray());
            new ActivityRecorder($LogData, 96, 1);
            return 1;
        }
    }

    /**
     * Funkcja edytująca nazwę kategorii
     */
    public function saveCategoryName(Request $request) {
        if ($request->ajax()) {
            $category = TestCategory::find($request->category_id);

            if ($category == null) {
                return 0;
            }
            $category->name = htmlentities($request->new_name_category, ENT_QUOTES, "UTF-8");
            $category->updated_at = date('Y-m-d H:i:s');
            $category->cadre_id = Auth::user()->id;
            $category->save();
            $LogData = array_merge(['T ' => 'Edycja kategorii'],$category->toArray());
            new ActivityRecorder($LogData, 96, 2);
            return 1;
        }
    }

    /**
     * Zmiana statusu kategorii ON/OFF
     */
    public function categoryStatusChange(Request $request) {
        if ($request->ajax()) {
            $category = TestCategory::find($request->category_id);

            if ($category == null) {
                return 0;
            }
            $category->deleted = $request->new_status;
            $category->updated_at = date('Y-m-d H:i:s');
            $category->cadre_id = Auth::user()->id;
            $category->save();
            $LogData = array_merge(['T ' => 'Zmiana statusu kategorii'],$category->toArray());
            new ActivityRecorder($LogData, 96, 4);

            return 1;
        }
    }

    /**
     * Funkcja zwracająca wszystkie pytania w danej kategorii
     */
    public function showCategoryQuestions(Request $request) {
        if ($request->ajax()) {
            $data = [];

            $data[] = TestCategory::find($request->category_id);
            $data[] = TestQuestion::where('category_id', '=', $request->category_id)->where('deleted', '=', 0)->get();

            return $data;
        }
    }

    /**
     * Funkcja edytująca pytanie testowe
     */
    public function editTestQuestion(Request $request) {
        if ($request->ajax()) {
            $question = TestQuestion::find($request->question_id);

            if ($question == null) {
                return 0;
            }

            $question->content = $request->question;
            $question->default_time = $request->newTime * 60;
            $question->updated_at = date('Y-m-d H:i:s');
            $question->cadre_by = Auth::user()->id;
            
            $question->save();
            $LogData = array_merge(['T ' => 'Edycja pytania testowego'],$question->toArray());
            new ActivityRecorder($LogData, 96, 2);
            return 1;
        }
    }

    /**
     * Usuwanie pytan testowych
     */
    public function deleteTestQuestion(Request $request) {
        if ($request->ajax()) {
            $question = TestQuestion::find($request->id);

            if ($question == null) {
                return 0;
            }

            $question->deleted = 1;
            $question->updated_at = date('Y-m-d H:i:s');
            $question->cadre_by = Auth::user()->id;

            $question->save();
            $LogData = array_merge(['T ' => 'Zmiana statusu pytania testowego'],$question->toArray());
            new ActivityRecorder($LogData, 96, 4);
            return 1;
        }
    }

    /**
     * Zliczanie ilości pytań w kategorii
     */
    public function mainTableCounter(Request $request) {
        if ($request->ajax()) {
            $category = TestCategory::find($request->category_id);

            if ($category == null) {
                return null;
            }

            return $category->questions->where('deleted', '=', 0)->count();
        }
    }

    /**
     * Funkcja aktywująca test
     */
    function activateTest(Request $request) {
        if ($request->ajax()) {
            $checkTest = UserTest::find($request->id);

            if ($checkTest == null) {
                return 0;
            }

            $checkTest->status = 2;
            $checkTest->save();
            $LogData = array_merge(['T ' => 'Aktywacja testu'],$checkTest->toArray());
            new ActivityRecorder($LogData, 100, 4);
            return 1;
        }
    }

    /**
     * Funkcja dezaktywujaca test
     */
    public function deactivateTest(Request $request) {
        if ($request->ajax()) {
            $checkTest = UserTest::find($request->id);
            
            if ($checkTest == null) {
                return 0;
            }
            $checkTest->status = 1;
            $checkTest->save();
            $LogData = array_merge(['T ' => 'Dezaktywacja testu'],$checkTest->toArray());
            new ActivityRecorder($LogData, 100, 4);

            return 1;
        }
    }

    /**
     * Metoda zapisująca podjęcie próby rozwiązania zadania 
     * 
     * @param Request 
     * @author konradja100
     * @access Public 
     * @return void
     */
    public function testAttempt(Request $request) {
        if ($request->ajax()) {
            $question = UserQuestion::find($request->question_id);

            /**
             * Sprawdzenie czy pytanie istnieje w bazie 
             */
            if ($question == null) {
                return 0;
            }

            $question->attempt = date('Y-m-d H:i:s');
            $question->save();
            $LogData = array_merge(['T ' => 'Rozpoczęcie rozwiązywania testu'],$question->toArray());
            new ActivityRecorder($LogData, 98, 4);
            return 1;
        }
    }

    /**
     * Funkcja zwracająca wszsystkie sprawdzone testy
     */
    public function datatableShowCheckedTests(Request $request) {
        $data = DB::table('user_tests')
            ->select(DB::raw('
                first_name,
                last_name,
                user_tests.id as test_id,
                user_tests.name as test_name,
                test_start,
                user_tests.result as test_result,
                count(user_questions.id) as count_questions
            '))
            ->join('users', 'users.id', 'user_tests.user_id')
            ->join('user_questions', 'user_questions.test_id', 'user_tests.id')
            ->groupBy('user_tests.id')
            ->where('user_tests.status', '=', 4)
            ->get();

        return datatables($data)->make(true);
    }

    /**
     * Funkcja zwracająca wszsystkie testy do sprawdzenia
     */
    public function datatableShowUncheckedTests(Request $request) {
        $data = DB::table('user_tests')
            ->select(DB::raw('
                first_name,
                last_name,
                user_tests.id as test_id,
                user_tests.name as test_name,
                test_start,
                user_tests.result as test_result,
                count(user_questions.id) as count_questions
            '))
            ->join('users', 'users.id', 'user_tests.user_id')
            ->join('user_questions', 'user_questions.test_id', 'user_tests.id')
            ->groupBy('user_tests.id')
            ->where('user_tests.status', '=', 3)
            ->get();

        return datatables($data)->make(true);
    }

    private function sendMail($user_mail, $user_name, $mail_title, $data, $mail_type) {

        Mail::send('tests.' . $mail_type, $data, function($message) use ($user_mail, $user_name, $mail_title)
        {

            $message->from('noreply.verona@gmail.com', 'Verona Consulting');
            $message->to($user_mail, $mail_title)->subject($mail_title);

        });
    }

    /**
     * Funkcja pobierająca listę osob z uprawnieniami do testowania
     */
    public function testerListGet() {
        /**
         * Pobranie pracownikow kadry
         */
        $users = User::whereNotIn('user_type_id', [1,2])
            ->where('status_work', '=', 1)
            ->orderBy('last_name')
            ->get();

        /**
         * pobranie ID uzytkownikkow z uprawnieniami
         */
        $ids = DB::table('privilage_user_relation')
            ->select(DB::raw('
                DISTINCT(user_id)
            '))
            ->get();

        /**
         * Przepierdolenie id-ków do tablicy
         */
        $arr = [];
        foreach($ids as $item) {
            $arr[] = $item->user_id;
        }

        /**
         * Pobranie testerow z bazy
         */
        $testers = DB::table('users')
            ->select(DB::raw('
                first_name,
                last_name,
                id
            '))
            ->whereIn('id', $arr)
            ->get();

        return view('tests.testerList')
            ->with('testers', $testers)
            ->with('users', $users);
    }

    /**
     * Zapis pracownikow do bazy z przywilejami
     */
    public function testerListPost(Request $request) {
        $id = $request->user_id;

        /**
         * Srawdzenie czy użytkownik istnieje
         */
        $check = User::find($id);

        if ($check == null) {
            return view('errors.404');
        }

        DB::table('privilage_user_relation')->insert([
            ['id' => null, 'link_id' => 96,  'user_id' => $id],
            ['id' => null, 'link_id' => 97,  'user_id' => $id],
            ['id' => null, 'link_id' => 98,  'user_id' => $id],
            ['id' => null, 'link_id' => 99,  'user_id' => $id],
            ['id' => null, 'link_id' => 100, 'user_id' => $id],
            ['id' => null, 'link_id' => 101, 'user_id' => $id],
            ['id' => null, 'link_id' => 102, 'user_id' => $id],
            ['id' => null, 'link_id' => 103, 'user_id' => $id],
            ['id' => null, 'link_id' => 104, 'user_id' => $id],
            ['id' => null, 'link_id' => 105, 'user_id' => $id],
            ['id' => null, 'link_id' => 106, 'user_id' => $id],
            ['id' => null, 'link_id' => 107, 'user_id' => $id],
            ['id' => null, 'link_id' => 108, 'user_id' => $id],
            ['id' => null, 'link_id' => 109, 'user_id' => $id],
            ['id' => null, 'link_id' => 110, 'user_id' => $id],
            ['id' => null, 'link_id' => 111, 'user_id' => $id],
            ['id' => null, 'link_id' => 112, 'user_id' => $id],
            ['id' => null, 'link_id' => 113, 'user_id' => $id],
            ['id' => null, 'link_id' => 114, 'user_id' => $id]
        ]);

        $LogData = ['T ' => 'Dodanie nowego testra do listy id: '.$id];
        new ActivityRecorder($LogData, 110, 1);

        Session::flash('message_ok', "Tester został dodany!");
        return Redirect::back();
    }

    /**
     * Funkcja odbierająca uprawnienia testerow
     */
    public function deleteTester(Request $request) {
        if ($request->ajax()) {
            $id = $request->user_id;

            $check = User::find($id);

            if ($check == null) {
                return 0;
            }

            /**
             * Usuwanie tylko linkow odnoszących się do testow
             */
            $links = [96,97,98,99,100,101,102,103,104,105,106,107,108,109,110,111,112,113,114];

            DB::table('privilage_user_relation')
                ->where('user_id', '=', $id)
                ->whereIn('link_id', $links)
                ->delete();
            $LogData = ['T ' => 'Usuniecie testera id: '.$id];
            new ActivityRecorder($LogData, 110, 3);
            return 1;
        }
    }
}
