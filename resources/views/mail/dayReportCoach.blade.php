<table style="width:100%;border:1px solid #231f20;border-collapse:collapse;padding:3px">
    <thead style="color:#efd88f">
    <tr>
        <td colspan="6" style="border:1px solid #231f20;text-align:center;padding:3px;background:#231f20;color:#efd88f">
            <font size="6" face="Calibri">RAPORT DZIENNY TRENERZY {{$coach->last_name . ' ' . $coach->first_name . ' ' . $date_selected . ' ' . $hour_selected}} </font></td>
        <td colspan="2" style="border:1px solid #231f20;text-align:left;padding:6px;background:#231f20">
            <img src="http://teambox.pl/image/logovc.png" class="CToWUd"></td>
    </tr>
    <tr>
        <td style="border:1px solid #231f20;padding:3px;background:#231f20;">Konsultant</td>
        <td style="border:1px solid #231f20;padding:3px;background:#231f20;">Średnia</td>
        <td style="border:1px solid #231f20;padding:3px;background:#231f20;">% janków</td>
        <td style="border:1px solid #231f20;padding:3px;background:#231f20;">Ilość połączeń</td>
        <td style="border:1px solid #231f20;padding:3px;background:#231f20;">Umówienia</td>
        <td style="border:1px solid #231f20;padding:3px;background:#231f20;">% ilość um/poł</td>
        <td style="border:1px solid #231f20;padding:3px;background:#231f20;">Czas przerw</td>
        <td style="border:1px solid #231f20;padding:3px;background:#231f20;">Liczba godzin</td>
    </tr>
    </thead>
    <tbody>

        @php
            $total_received_calls = 0;
            $total_success = 0;
            $total_pause_time = 0;
            $total_login_time = 0;
            $total_janky_count = 0;
            $total_check_talks = $total_bad_checked_talks = 0;
        @endphp

        @foreach($data as $item)
            @php
                $janky_proc_item = $item->all_checked_talks > 0 ? round(100 * $item->all_bad_talks / $item->all_checked_talks,2) : 0;
            @endphp
            <tr>
                <td style="border:1px solid #231f20;text-align:center;padding:3px">{{ $item->user_last_name . ' ' . $item->user_first_name }}</td>
                <td style="border:1px solid #231f20;text-align:center;padding:3px">{{ $item->average }}</td>
                <td style="border:1px solid #231f20;text-align:center;padding:3px">{{ $janky_proc_item }} %</td>
                <td style="border:1px solid #231f20;text-align:center;padding:3px">{{ $item->received_calls }}</td>
                <td style="border:1px solid #231f20;text-align:center;padding:3px">{{ $item->success }}</td>
                <td style="border:1px solid #231f20;text-align:center;padding:3px">{{ ($item->received_calls > 0) ? round(($item->success / $item->received_calls) * 100, 2) : 0 }} %</td>
                <td style="border:1px solid #231f20;text-align:center;padding:3px">{{ sprintf('%02d:%02d:%02d', ($item->time_pause/3600),($item->time_pause/60%60), $item->time_pause%60) }}</td>
                <td style="border:1px solid #231f20;text-align:center;padding:3px">{{ $item->login_time }}</td>
            </tr>

            @php
                $total_check_talks += $item->all_checked_talks;
                $total_bad_checked_talks += $item->all_bad_talks;

                $total_received_calls += $item->received_calls;
                $total_success += $item->success;
                $total_pause_time += $item->time_pause;
                $hours_array = explode(':', $item->login_time);
                $total_login_time += (($hours_array[0] * 3600) + ($hours_array[1] * 60) + $hours_array[2]);
               // $total_janky_count += round($item->success * $item->dkj_proc);
            @endphp
        @endforeach

        @php
            $total_time = $total_login_time / 3600;
            $total_avg = ($total_time > 0) ? round(($total_success / $total_time), 2) : 0 ;
            $total_janky_proc = ($total_check_talks > 0) ? round(($total_bad_checked_talks*100) / $total_check_talks, 2) : 0 ;
        @endphp

        <tr>
            <td style="background-color: #efef7f;border:1px solid #231f20;text-align:center;padding:3px"><b>SUMA</b></td>
            <td style="background-color: #efef7f;border:1px solid #231f20;text-align:center;padding:3px"><b>{{ $total_avg }}</b></td>
            <td style="background-color: #efef7f;border:1px solid #231f20;text-align:center;padding:3px"><b>{{ $total_janky_proc }} %</b></td>
            <td style="background-color: #efef7f;border:1px solid #231f20;text-align:center;padding:3px"><b>{{ $total_received_calls }}</b></td>
            <td style="background-color: #efef7f;border:1px solid #231f20;text-align:center;padding:3px"><b>{{ $total_success }}</b></td>
            <td style="background-color: #efef7f;border:1px solid #231f20;text-align:center;padding:3px"><b>{{ ($total_received_calls > 0) ? round(($total_success / $total_received_calls) * 100, 2) : 0 }} %</b></td>
            <td style="background-color: #efef7f;border:1px solid #231f20;text-align:center;padding:3px"><b>{{ sprintf('%02d:%02d:%02d', ($total_pause_time/3600),($total_pause_time/60%60), $total_pause_time%60) }}</b></td>
            <td style="background-color: #efef7f;border:1px solid #231f20;text-align:center;padding:3px"><b>{{ sprintf('%02d:%02d:%02d', ($total_login_time/3600),($total_login_time/60%60), $total_login_time%60) }}</b></td>
        </tr>

    </tbody>
</table>
