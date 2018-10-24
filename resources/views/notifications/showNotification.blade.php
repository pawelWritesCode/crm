@extends('layouts.main')
@section('content')

<div class="row">
    <div class="col-md-12">
        <div class="page-header">
            <div class="alert gray-nav ">Pomoc / Opis problemu</div>
        </div>
    </div>
</div>

@if(isset($message))
   <div class="alert alert-success">{{$message}}</div>
@endif

@if (Session::has('message_ok'))
   <div class="alert alert-success">{{ Session::get('message_ok') }}</div>
@endif
@if (Session::has('message_error'))
    <div class="alert alert-danger">{{ Session::get('message_error') }}</div>
@endif

<div class="row">
  <div class="col-md-12">
        <div class="panel panel-default">
          <div class="panel-heading">{{$notification->title}}</div>
          <div class="panel-body">
            <p>
                <div class="row">
                  <div class="col-md-4">
                      <b>Problem zgłoszony przez:</b>
                      {{$notification->user->first_name . ' ' . $notification->user->last_name}}
                  </div>
                  <div class="col-md-4">
                      <b>Nr telefonu służbowy:</b>
                      @if($notification->user->phone == 0 or $notification->user->phone == null)
                          Brak
                      @else
                          {{$notification->user->phone }}
                      @endif
                  </div>
                  <div class="col-md-4">
                      <b>Nr telefonu prywatny:</b>
                      @if($notification->user->private_phone == 0 or $notification->user->private_phone == null)
                          Brak
                      @else
                          {{$notification->user->private_phone }}
                      @endif
                  </div>
              </div>
              <hr>
            </p>
            <p>
              <b>Oddział:</b>
              {{$notification->department_info->departments->name . ' ' . $notification->department_info->department_type->name}}
              <br />
              <hr>
            </p>
            <p>
              <b>Treść problemu:</b><br />
                {{$notification->content}}
            </p>
              <div class="row">
                      <div class="col-md-12">
                          <ul class="list-group">
                              @if($notification->notification_about_id !== null)
                                  <li class="list-group-item">
                                    <b>Problem dotyczy: </b>{{$notification->notification_about->name}}
                                  </li>
                              @endif
                              @if($notification->sticker_number !== null)
                                  <li class="list-group-item">
                                      <b>Numer naklejki: </b>{{$notification->sticker_number}}
                                  </li>
                              @endif
                          </ul>
                      </div>
              </div>
            <hr>
            @if($notification->status == 2)
            <p>
              <b>Zgłoszenie przyjęte przez:</b>
                {{$user->first_name . ' ' . $user->last_name}}
            </p>
            @endif
            @if($notification->status == 3)
            <p>
              <b>Zgłoszenie zrealizowane przez:</b>
                {{$user->first_name . ' ' . $user->last_name}}
            </p>
            @endif
            <hr>
            @if($notification->status == 3)
            <p>
              <b>Czas realizacji zgłoszenia:</b>
                @if($notification->realization_days_duration > 0){{$notification->realization_days_duration}}d
                @endif {{$notification->sec}}
            </p>
            @endif
            <br />
            <br />
            <p>
              <form method="POST" action="{{URL::to('/show_notification/')}}/{{$notification->id}}" id="form_status">
                  <input type="hidden" name="_token" value="{{ csrf_token() }}">
                  <div class="col-md-4">
                      <div class="form-group ">
                        <label for="status_change">Zmień status:</label>
                        <select style="font-size:18px;" @if(($notification->status == 2 &&  $notification->displayed_by != Auth::user()->id) || $notification->status == 3) disabled @endif class="form-control" name="status" id="status_change">
                            <option value="1" @if($notification->status == 1) selected @endif >Zgłoszono</option>
                            <option value="2" @if($notification->status == 2) selected @endif >Przyjęto do realizacji</option>
                            <option value="3" @if($notification->status == 3) selected @elseif($notification->status == 1) disabled @endif >Zakończono</option>
                        </select>
                      </div>
                      <div class="form-group">
                          <input @if(($notification->status == 2 &&  $notification->displayed_by != Auth::user()->id) || $notification->status == 3) disabled @endif id="change_status" type="submit" class="btn btn-success" value="Zapisz zmiany">
                      </div>
                  </div>
              </form>
            </p>
            <p>
              <div class="col-md-8">
              <form method="POST" action="{{URL::to('/add_comment_notifications/')}}/{{$notification->id}}" id="form_comment">
                  <input type="hidden" name="_token" value="{{ csrf_token() }}">
                  <div class="form-group">
                      <label for="content">Dodaj komentarz:</label>
                      <textarea id="content" name="content" placeholder="Tutaj wpisz treść komentarza" style="resize: vertical" class="form-control"></textarea>
                  </div>
                  <div class="alert alert-danger" style="display: none" id="alert_comment">
                      Podaj treść komentarza!
                  </div>
                  <div class="form-group">
                      <input id="add_comment" type="submit" class="btn btn-default" value="Dodaj komentarz" />
                  </div>
              </form>
            </div>
            </p>
            <div class="col-md-12">
            <hr>
            @if($notification->comments != null)
              <h3>Komentarze:</h3>
              @foreach($notification->comments->sortByDesc('created_at') as $comment)
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            Dodał: {{$comment->user->first_name . ' ' . $comment->user->last_name}}
                        </div>
                        <div class="panel-body">
                            <small>Data dodania: {{$comment->created_at}}</small>
                            <p>{{$comment->content}}</p>
                        </div>
                    </div>
              @endforeach
            @endif
            </div>
          </div>
        </div>
  </div>
</div>

@endsection
@section('script')
<script>

  $("#add_comment").on('click', function() {
      var content = $("#content").val();

      if(content == '') {
          $('#alert_comment').slideDown(1000);
          return false;
      } else {
          $('#alert_comment').slideUp(1000);
          $('#form_comment').submit(function(){
              $(this).find(':submit').attr('disabled','disabled');
          });
      }
  });

  $("#change_status").on('click', function() {

      $('#form_status').submit(function(){
          $(this).find(':submit').attr('disabled','disabled');
      });
  });

</script>
@endsection
