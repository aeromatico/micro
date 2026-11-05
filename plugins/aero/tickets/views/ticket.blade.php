@extends('aerotickets::layout')
@section('title', 'Entrada — '.$event->name)
@section('robots', 'noindex,nofollow')
@section('content')
<div style="max-width:440px;margin:0 auto">
 <div class="card"><div class="b" style="text-align:center">
  <p class="mut" style="margin:0">{{ $ticket->state === 'issued' ? 'Entrada digital' : 'Entrada' }}@if($serial) · {{ $serial }}@endif</p>
  <h1 style="margin:.2em 0">{{ $event->name }}</h1>
  <p class="mut" style="margin:0">{{ $when }}</p>

  @if($ticket->state === 'issued')<p><span class="pill" style="background:var(--ok);color:#fff">Emitida · válida</span></p>
  @elseif($ticket->state === 'used')<p><span class="pill">Utilizada{{ $ticket->checked_in_at ? ' · '.$ticket->checked_in_at->format('d/m H:i') : '' }}</span></p>
  @elseif($ticket->state === 'cancelled')<div class="alert bad">Entrada anulada: no permite el ingreso.</div>
  @elseif($ticket->state === 'expired')<div class="alert bad">Entrada vencida{{ $ticket->status === 'valid' ? ' (el evento ya finalizó)' : ' (la reserva venció)' }}.</div>
  @else<div class="alert">Pendiente de pago o aprobación. Aún no es válida para ingresar.</div>@endif

  @if($qr)
    <div style="display:inline-block;background:#fff;padding:10px;border-radius:12px;max-width:100%;{{ $ticket->state === 'used' ? 'opacity:.35' : '' }}">{!! $qr !!}</div>
    <p style="font:700 1.35rem/1.2 ui-monospace,monospace;letter-spacing:.12em;margin:.4em 0">{{ $ticket->code }}</p>
    <p class="mut" style="margin:0">Sube el brillo de tu pantalla y muestra el QR en la puerta.</p>
  @endif
 </div></div>

 <div class="card" style="margin-top:14px"><div class="b">
  <p style="margin:0"><strong>{{ $holder }}</strong><br><span class="mut">{{ $type?->name ?? 'General' }}@if($session) · {{ $session->name }}@endif</span></p>
  <hr style="border:0;border-top:1px solid var(--line)">
  <p style="margin:0">{{ $where }}@if($reference)<br><span class="mut">{{ $reference }}</span>@endif
    @if($event->lat && $event->lng)<br><a href="https://www.openstreetmap.org/?mlat={{ $event->lat }}&mlon={{ $event->lng }}#map=17/{{ $event->lat }}/{{ $event->lng }}" target="_blank" rel="noopener">Ver en el mapa</a>@endif</p>
  @if($origin)<p class="mut" style="margin:.6em 0 0">{{ $origin }}</p>@endif
  @if($contact)<p class="mut" style="margin:.3em 0 0">Consultas: {{ $contact }}</p>@endif
 </div></div>

 <div class="card" style="margin-top:14px"><div class="b">
  <strong>Para ingresar</strong>
  <ul style="padding-left:1.1em;margin:.4em 0 0">@foreach($notes as $n)<li class="mut" style="margin:.2em 0;white-space:pre-line">{{ $n }}</li>@endforeach</ul>
 </div></div>

 @if($ticket->state === 'issued' || $ticket->state === 'used')
  <p style="margin-top:14px">@if($pdfAvailable)<a class="btn" href="{{ url('entrada/'.$ticket->public_token.'/pdf') }}">Descargar PDF</a> @endif
     <a class="btn sec" href="{{ url('mis-entradas') }}">Mis entradas</a></p>
 @endif
</div>
@endsection
