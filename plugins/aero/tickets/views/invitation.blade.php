@extends('aerotickets::layout')
@section('title', $g->isRevoked() ? 'Invitación' : 'Invitación — '.$e->name)
@section('robots', 'noindex,nofollow')
@section('content')
@if($g->isRevoked())
  <div class="card" style="max-width:520px;margin:40px auto"><div class="b">
    <h1>Invitación no válida</h1>
    <p>Esta invitación fue revocada por el organizador. Si crees que es un error, comunícate con él.</p>
  </div></div>
@else
<div class="card" style="max-width:640px;margin:0 auto"><div class="b">
  @if($e->cover)<img src="{{ $e->cover->path }}" alt="" style="border-radius:10px">@endif
  <p class="mut" style="margin-bottom:0">Invitación personal</p>
  <h1>{{ $g->name }}</h1>
  <p>Estás invitado/a a <strong>{{ $e->name }}</strong></p>
  <p><strong>{{ $e->starts_at?->translatedFormat('l d \d\e F, H:i') }}</strong><br>
     {{ $e->venue_name }} — {{ $e->address }}{{ $e->zone ? ', '.$e->zone : '' }}, {{ $e->city }}
     @if($e->lat && $e->lng)<br><a href="https://www.openstreetmap.org/?mlat={{ $e->lat }}&mlon={{ $e->lng }}#map=17/{{ $e->lat }}/{{ $e->lng }}" target="_blank" rel="noopener">Ver en el mapa</a>@endif</p>
  <p><span class="pill">{{ \Aero\Tickets\Models\Guest::$kinds[$g->invitation_kind] }}</span>
     @if($g->companions_allowed > 0) Puedes asistir con hasta <strong>{{ $g->companions_allowed }}</strong> acompañante(s) ({{ $g->party_size }} lugares en total).@else Invitación para una persona.@endif</p>
  @if($e->description)<div style="white-space:pre-line">{{ $e->description }}</div>@endif
  @if($e->requirements)<p class="mut" style="white-space:pre-line">{{ $e->requirements }}</p>@endif

  @if(session('ok'))<div class="alert ok">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="alert bad">{{ $errors->first() }}</div>@endif
  @if($lapsed)<div class="alert bad">Tu reserva venció y el cupo se liberó. Confirma de nuevo para reservar otra vez.</div>@endif

  <p>Estado: <strong>{{ $g->invitation_status_label }}</strong>@if($g->rsvp==='confirmed') — {{ $g->confirmed_count }} persona(s) confirmada(s)@endif</p>

  @if($e->status === 'cancelled')<div class="alert bad">El evento fue cancelado.</div>
  @elseif($e->status === 'finished')<div class="alert">El evento ya finalizó.</div>
  @elseif($e->acceptsSales())
  <form method="post" action="{{ url('invitacion/'.$g->token) }}">@csrf
    @if($g->party_size > 1)
      <label>¿Cuántas personas asisten? (hasta {{ $g->party_size }})</label>
      <input type="number" id="count" name="count" min="1" max="{{ $g->party_size }}" value="{{ old('count', $g->confirmed_count ?: $g->party_size) }}" onchange="document.querySelectorAll('.nm').forEach((el,i)=>el.style.display=(i<this.value-1)?'':'none')">
      <p class="mut" style="margin-bottom:0">Nombre de tus acompañantes (cada uno recibe su entrada; opcional):</p>
      @for($i = 1; $i < $g->party_size; $i++)
        <input class="nm" name="names[{{ $i }}]" maxlength="255" placeholder="Acompañante {{ $i }}" value="{{ old('names.'.$i, optional($tickets->values()->get($i))->holder_name && !str_contains(optional($tickets->values()->get($i))->holder_name, '(acompañante') ? $tickets->values()->get($i)->holder_name : '') }}" style="margin-top:6px;{{ $i < old('count', $g->confirmed_count ?: $g->party_size) ? '' : 'display:none' }}">
      @endfor
    @endif
    <p><button class="btn" name="attend" value="yes">{{ $g->rsvp==='confirmed' ? 'Actualizar mi confirmación' : 'Confirmo asistencia' }}</button>
       <button class="btn sec" name="attend" value="no">No podré asistir</button></p>
  </form>
  @endif

  @if($tickets->isNotEmpty())
    <h3>Tus entradas</h3>
    @php($due = $tickets->whereIn('status', ['reserved','pending'])->sum('amount'))
    @if($due > 0)<div class="alert">Orden <a href="{{ url('orden/'.$tickets[0]->order_code) }}"><strong>{{ $tickets[0]->order_code }}</strong></a>: total {{ \Aero\Tickets\Classes\Currency::format($due, $tickets[0]->currency) }}. Tus entradas valen al confirmarse el pago{{ $tickets->firstWhere('status','reserved')?->expires_at ? ' (reservadas hasta las '.$tickets->firstWhere('status','reserved')->expires_at->format('H:i').')' : '' }}. Paga al organizador{{ $e->contact_phone ? ' ('.$e->contact_phone.')' : '' }}.</div>@endif
    @foreach($tickets as $t)
      <p><a class="btn sec" href="{{ url('entrada/'.$t->public_token) }}">{{ $t->holder_name }} — {{ \Aero\Tickets\Models\Ticket::$statuses[$t->status] }}</a></p>
    @endforeach
  @endif
</div></div>
@endif
@endsection
