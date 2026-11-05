@extends('aerotickets::layout')
@section('title', 'Mis entradas')
@section('robots', 'noindex,nofollow')
@section('content')
<div style="max-width:560px;margin:0 auto">
<h1>Mis entradas</h1>
@if($errors->any())<div class="alert bad">{{ $errors->first() }}</div>@endif
@if($sent)<div class="alert ok">Si ese correo tiene entradas, te enviamos un enlace para verlas. Revisa tu bandeja (y spam). Vale 60 minutos.</div>@endif

@if($groups === null)
  <form method="post" action="{{ url('mis-entradas') }}" class="card"><div class="b">@csrf
    <p class="mut" style="margin-top:0">No necesitas cuenta: escribe el correo que usaste al comprar y te enviamos un enlace seguro.</p>
    <label>Correo de la compra</label><input type="email" name="email" required maxlength="255">
    <p><button class="btn">Enviarme el enlace</button></p>
  </div></form>
@else
  <p class="mut">Entradas de {{ $email }}</p>
  @forelse($groups as $g)
    <div class="card" style="margin-bottom:14px"><div class="b">
      <h3 style="margin:0">{{ $g['event']->name }}</h3>
      <p class="mut" style="margin:.2em 0">{{ $g['event']->starts_at?->translatedFormat('l d \d\e F, H:i') }} · {{ $g['event']->venue_name }}</p>
      <p class="mut" style="margin:.2em 0">Orden <a href="{{ url('orden/'.$g['order']->code) }}">{{ $g['order']->code }}</a> · {{ \Aero\Tickets\Models\Order::$statuses[$g['order']->status] }}</p>
      @foreach($g['tickets'] as $t)
        <p style="margin:.4em 0"><a class="btn sec" href="{{ url('entrada/'.$t->public_token) }}">{{ $t->holder_name }} — {{ $t->state_label }}</a></p>
      @endforeach
    </div></div>
  @empty
    <p class="mut">No encontramos entradas con este correo.</p>
  @endforelse
@endif
</div>
@endsection
