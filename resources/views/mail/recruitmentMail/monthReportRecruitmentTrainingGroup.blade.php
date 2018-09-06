<table style="width:100%;border:1px solid #231f20;border-collapse:collapse;padding:3px">
    <thead style="color:#efd88f">
    <tr>
        <td colspan="6" style="border:1px solid #231f20;text-align:center;padding:3px;background:#231f20;color:#efd88f">
            <font size="6" face="Calibri">Miesięczny Raport Szkoleń</font></td>
        <td colspan="2" style="border:1px solid #231f20;text-align:left;padding:6px;background:#231f20">
            <img src="http://teambox.pl/image/logovc.png" class="CToWUd"></td>
    </tr>
    <tr>
        <td colspan="8" style="border:1px solid #231f20;padding:3px;background:#231f20;color:#efd88f">Raport za okres od {{$date_start}} do {{$date_stop}}</td>
    </tr>
    <tr>
        <th style="border:1px solid #231f20;padding:3px;background:#231f20">Oddział</th>
        <th style="border:1px solid #231f20;padding:3px;background:#231f20">Umówionych Etap - 1</th>
        <th style="border:1px solid #231f20;padding:3px;background:#231f20">Obecnych Etap - 1</th>
        <th style="border:1px solid #231f20;padding:3px;background:#231f20">Nieobecnych Etap - 1</th>
        <th style="border:1px solid #231f20;padding:3px;background:#231f20">Umówionych Etap - 2</th>
        <th style="border:1px solid #231f20;padding:3px;background:#231f20">Obecnych Etap - 2</th>
        <th style="border:1px solid #231f20;padding:3px;background:#231f20">Nieobecnych Etap - 2</th>
        <th style="border:1px solid #231f20;padding:3px;background:#231f20">Zatrudnieni Kandydaci</th>
    </tr>
    </thead>
    <tbody>
    @foreach($data as  $item)
        <tr>
            <td  style="border:1px solid #231f20;text-align:center;padding:3px">{{$item->dep_name.' '.$item->dep_name_type}}</td>
            <td  style="border:1px solid #231f20;text-align:center;padding:3px;background-color: #CCC">{{$item->sum_choise_stageOne+$item->sum_absent_stageOne}}</td>
            <td  style="border:1px solid #231f20;text-align:center;padding:3px;background-color: #CCC">{{$item->sum_choise_stageOne}}</td>
            <td  style="border:1px solid #231f20;text-align:center;padding:3px;background-color: #CCC">{{$item->sum_absent_stageOne}}</td>
            <td  style="border:1px solid #231f20;text-align:center;padding:3px;">{{$item->sum_choise_stageTwo+$item->sum_absent_stageTwo}}</td>
            <td  style="border:1px solid #231f20;text-align:center;padding:3px">{{$item->sum_choise_stageTwo}}</td>
            <td  style="border:1px solid #231f20;text-align:center;padding:3px;}}">{{$item->sum_absent_stageTwo}}</td>
            <td  style="border:1px solid #231f20;text-align:center;padding:3px;background-color: #f5e79e">{{$item->countHireUserFromFirstTrainingGroup}}</td>
        </tr>
    @endforeach
    <tr>
        <td colspan="1" style="border:1px solid #231f20;text-align:center;padding:3px"><b>TOTAL</b></td>
        <td  style="border:1px solid #231f20;text-align:center;padding:3px;background-color: #CCC">{{$data->sum('sum_choise_stageOne') + $data->sum('sum_absent_stageOne')}}</td>
        <td  style="border:1px solid #231f20;text-align:center;padding:3px;background-color: #CCC">{{$data->sum('sum_choise_stageOne')}}</td>
        <td  style="border:1px solid #231f20;text-align:center;padding:3px;background-color: #CCC">{{$data->sum('sum_absent_stageOne')}}</td>
        <td  style="border:1px solid #231f20;text-align:center;padding:3px">{{$data->sum('sum_choise_stageTwo') + $data->sum('sum_absent_stageTwo')}}</td>
        <td  style="border:1px solid #231f20;text-align:center;padding:3px">{{$data->sum('sum_choise_stageTwo')}}</td>
        <td  style="border:1px solid #231f20;text-align:center;padding:3px">{{$data->sum('sum_absent_stageTwo')}}</td>
        <td  style="border:1px solid #231f20;text-align:center;padding:3px;background-color: #f5e79e">{{$data->sum('countHireUserFromFirstTrainingGroup')}}</td>
    </tr>
</table>
