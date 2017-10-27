@extends('layouts.main')
@section('content')

    <style>
        table{
            font-size: 12px;
            text-align: center;
        }
    </style>

{{--Header page --}}
    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">Kadra Informacje</h1>
        </div>
    </div>

    @if(isset($saved))
        <div class="alert alert-success">
            <strong>Success!</strong> Konto użytkownika {{$saved}} zostało zmodyfikowane.
        </div>
    @endif

    <div class="row">
        <div class="col-lg-12">

            <div class="panel panel-default">
                <div class="panel-heading">
                    Lista Pracowników
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div id="start_stop">
                                <div class="panel-body">
                                    <table id="datatable">
                                        <thead>
                                        <tr>
                                            <th>Imię</th>
                                            <th>Nazwisko</th>
                                            <th>Oddział</th>
                                            <th>Dział</th>
                                            <th>Stanowisko</th>
                                            <th>Nr. Tel.</th>
                                            <th>Akcja</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')

<script>

    $(document).ready( function () {

        table = $('#datatable').DataTable({
            "autoWidth": false,
            "processing": true,
            "serverSide": true,
            "drawCallback": function( settings ) {
            },
            "ajax": {
                'url': "{{ route('api.datatableCadreManagement') }}",
                'type': 'POST',
                'headers': {'X-CSRF-TOKEN': '{{ csrf_token() }}'}
            },
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Polish.json"
            },"columns":[
                {"data": "first_name"},
                {"data": "last_name"},
                {"data": "department_name","name":"departments.name"},
                {"data": "department_type_name","name":"department_type.name"},
                {"data": "user_type_name","name":"user_types.name"},
                {"data": "phone"},
                {"data": function (data, type, dataToSet) {
                    return '<a href="/edit_cadre/'+data.id+'" >Edytuj</a>'
                },"orderable": false, "searchable": false }
                ]
        });
    });

</script>
@endsection
