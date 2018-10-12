@extends('layouts.main')
@section('content')


    {{--Header page --}}
    <div class="row">
        <div class="col-lg-12">
            <div class="page-header">
                <div class="alert gray-nav">Raport Tygodniowy 30 Rbh(zbiorczy)</div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12">
            <form action="{{URL::to('/pageWeek30RbhReport')}}" method="post">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <div class="form-group">
                    <label for="date" class="myLabel">Data początkowa:</label>
                    <div class="input-group date form_date col-md-5" data-date="" data-date-format="yyyy-mm-dd" data-link-field="datak" style="width:50%;">
                        <input class="form-control" name="date_start" id="date" type="text" value="{{date("Y-m-d")}}">
                        <span class="input-group-addon"><span class="glyphicon glyphicon-th"></span></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="date_stop" class="myLabel">Data końcowa:</label>
                    <div class="input-group date form_date col-md-5" data-date="" data-date-format="yyyy-mm-dd" data-link-field="datak" style="width:50%;">
                        <input class="form-control" name="date_stop" id="date_stop" type="text" value="{{date("Y-m-d")}}">
                        <span class="input-group-addon"><span class="glyphicon glyphicon-th"></span></span>
                    </div>
                </div>
                <div class="form-group">
                    <input type="submit" class="btn btn-info form-control" value="Generuj" style="width:50%;">
                </div>
            </form>
        </div>
        <div class="col-lg-12">
            <div class="row">
                <div class="panel-body" style="font-size: 12px;">
                    @include('mail.week30RbhReport')
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')

    <script>
        $('.form_date').datetimepicker({
            language:  'pl',
            autoclose: 1,
            minView : 2,
            pickTime: false,
        });

        let date_start = `<?php echo $date_start; ?>`;
        let date_stop = `<?php echo $date_stop; ?>`;

        let date_start_input = document.querySelector('#date');
        $(date_start_input).val(date_start);

        let date_stop_input = document.querySelector('#date_stop');
        $(date_stop_input).val(date_stop);
    </script>
@endsection
