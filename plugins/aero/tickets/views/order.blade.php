@extends('aerotickets::layout')
@section('title', 'Orden '.$o->code)
@section('robots', 'noindex,nofollow')
@section('content')
@php($m = fn ($v) => \Aero\Tickets\Classes\Currency::format($v, $o->currency))
<div style="max-width:640px;margin:0 auto">
<p><a href="{{ url('evento/'.$e->slug) }}{{ $e->visibility === 'link' ? '?t='.$e->access_token : '' }}">← {{ $e->name }}</a></p>
<h1>Orden {{ $o->code }}</h1>
@if(session('notice'))<div class="alert ok">{{ session('notice') }}</div>@endif
@if($errors->any())<div class="alert bad">{{ $errors->first() }}</div>@endif

@switch($o->status)
  @case('pending_payment')
    @if($o->isLapsed())<div class="alert bad">Esta orden venció sin pago. Si ya pagaste, avísanos abajo; si no, haz una nueva compra.</div>
    @else<div class="alert">Todavía <strong>no tienes entradas</strong>: se emiten cuando se confirma tu pago de {{ $m($o->total) }}. Esta orden <strong>no reserva lugares</strong> (se entregan a quien confirme su pago primero). Paga y avísanos aquí abajo.</div>@endif
    @break
  @case('verifying')<div class="alert">{{ $o->payment_reference ? 'Recibimos tu aviso de pago' : 'Tu solicitud está en revisión' }}. El organizador la verificará; tus entradas se emitirán al confirmarse.</div>@break
  @case('confirmed')<div class="alert ok">Orden confirmada. Presenta el código QR de cada entrada al ingresar.</div>@break
  @case('incident')<div class="alert bad">Hay una incidencia con tu orden. El organizador se pondrá en contacto contigo{{ $e->contact_phone ? ' ('.$e->contact_phone.')' : '' }}.</div>@break
  @case('expired')<div class="alert bad">La orden venció sin pago. Haz una nueva compra.</div>@break
  @case('cancelled')<div class="alert bad">Orden cancelada.</div>@break
@endswitch

<div class="card"><div class="b">
  <p class="mut" style="margin-top:0">{{ \Aero\Tickets\Models\Order::$statuses[$o->status] }} · creada {{ $o->created_at->format('d/m/Y H:i') }}</p>
  @foreach($items as $i)<div style="display:flex;justify-content:space-between"><span>{{ $i->quantity }} × {{ $i->name }}</span><span>{{ $i->kind === 'free' || $i->kind === 'courtesy' ? 'Sin costo' : $m($i->subtotal) }}</span></div>@endforeach
  <hr style="border:0;border-top:1px solid var(--line)">
  <div style="display:flex;justify-content:space-between"><span>Subtotal</span><span>{{ $m($o->subtotal) }}</span></div>
  <div style="display:flex;justify-content:space-between"><span>Comisiones</span><span>{{ $m($o->fees) }}</span></div>
  <div style="display:flex;justify-content:space-between"><strong>Total</strong><strong>{{ $m($o->total) }}</strong></div>
  <p class="mut">Comprador: {{ $o->buyer_name }} · {{ $o->buyer_email }} · {{ $o->buyer_phone }}</p>
</div></div>

@if($rejected && in_array($o->status, ['pending_payment'], true))
  <div class="alert bad">Tu último pago fue rechazado: {{ $rejected->reject_reason }}. Puedes intentar de nuevo.</div>
@endif

@if($payment && $payment->status === 'submitted')
  <div class="card" style="margin-top:16px"><div class="b">
    <h3 style="margin-top:0">Pago pendiente de verificación</h3>
    <p>Informaste tu pago el {{ $payment->submitted_at?->format('d/m/Y H:i') }}@if($payment->reference) con la referencia <strong>{{ $payment->reference }}</strong>@endif.
       El organizador debe comprobar el dinero en su cuenta; recién entonces tus entradas serán válidas. Un comprobante no es prueba suficiente por sí solo.</p>
  </div></div>
@elseif($o->status === 'pending_payment' && !$o->isLapsed())
  @if($setting->general_instructions)<div class="alert" style="white-space:pre-line">{{ $setting->general_instructions }}</div>@endif

  @if($payment && in_array($payment->status, ['pending'], true))
    <div class="card" style="margin-top:16px"><div class="b">
      <h3 style="margin-top:0">Paga {{ $m($payment->amount) }} — {{ $payment->method_label }}</h3>
      @if($payment->method === 'qr')
        @if(!empty($present['image']))<p style="text-align:center"><img src="{{ $present['image'] }}" alt="QR de pago" style="max-width:280px;width:100%;background:#fff;padding:8px;border-radius:10px"></p>@endif
        <p class="mut">Escanea el QR con la app de tu banco y paga exactamente <strong>{{ $m($payment->amount) }}</strong>.
          {{ !empty($present['automatic']) ? 'Cuando el banco confirme el pago tus entradas se activarán solas; si prefieres, puedes avisarnos abajo.' : 'Este QR no se confirma automáticamente: avísanos abajo con la referencia de tu operación.' }}</p>
      @elseif($payment->method === 'transfer')
        <div style="white-space:pre-line">{{ $setting->transfer_instructions ?: 'Coordina los datos de la transferencia con el organizador'.($e->contact_phone ? ' ('.$e->contact_phone.')' : '').'.' }}</div>
        <p class="mut">Importe exacto: <strong>{{ $m($payment->amount) }}</strong>. Usa la orden <strong>{{ $o->code }}</strong> como glosa.</p>
      @endif
      <form method="post" action="{{ url('orden/'.$o->code.'/informar') }}" enctype="multipart/form-data">@csrf
        <label>Número / referencia de la operación @if($payment->method === 'qr' && !empty($present['automatic']))(opcional)@endif</label><input name="reference" maxlength="255" value="{{ old('reference') }}" @if(!($payment->method === 'qr' && !empty($present['automatic']))) required minlength="3" @endif>
        <label>Nombre del titular de la cuenta que pagó (opcional)</label><input name="sender" maxlength="255" value="{{ old('sender') }}">
        @if($payment->method === 'transfer' ? $setting->proof_policy !== 'none' : true)
          <label>Comprobante (JPG, PNG, WEBP o PDF, máx. 3 MB){{ $payment->method === 'transfer' && $setting->proof_policy === 'required' ? '' : ' — opcional' }}</label>
          <input type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf" {{ $payment->method === 'transfer' && $setting->proof_policy === 'required' ? 'required' : '' }}>
        @endif
        <p><button class="btn">Ya pagué — avisar</button></p>
      </form>
    </div></div>
  @endif

  @if(count($methods) > 0 && (!$payment || $payment->status !== 'pending'))
    <form method="post" action="{{ url('orden/'.$o->code.'/pagar') }}" class="card" style="margin-top:16px"><div class="b">@csrf
      <h3 style="margin-top:0">¿Cómo vas a pagar?</h3>
      @foreach($methods as $key => $label)<p><button class="btn {{ $loop->first ? '' : 'sec' }}" name="method" value="{{ $key }}">{{ $label }}</button></p>@endforeach
    </div></form>
  @elseif($payment && $payment->status === 'pending' && count($methods) > 1)
    <form method="post" action="{{ url('orden/'.$o->code.'/pagar') }}" style="margin-top:8px">@csrf
      @foreach(array_diff_key($methods, [$payment->method => 1]) as $key => $label)<button class="btn sec" name="method" value="{{ $key }}">Mejor pagar por: {{ $label }}</button> @endforeach
    </form>
  @endif

  <form method="post" action="{{ url('orden/'.$o->code.'/cancelar') }}" style="margin-top:12px" onsubmit="return confirm('¿Cancelar la orden y liberar los cupos?')">@csrf<button class="btn sec">Cancelar orden</button></form>
@elseif($payment && $o->isLapsed() || $o->status === 'expired')
  @if($payment && in_array($payment->status, ['pending','expired'], true))
    <div class="card" style="margin-top:16px"><div class="b">
      <h3 style="margin-top:0">¿Ya pagaste?</h3>
      <p class="mut">Esta orden venció, pero si ya hiciste el pago avísanos: el organizador lo verificará y, si todavía hay lugares, emitirá tus entradas. Si no los hay, te devolverá el dinero.</p>
      <form method="post" action="{{ url('orden/'.$o->code.'/informar') }}" enctype="multipart/form-data">@csrf
        <label>Número / referencia de la operación</label><input name="reference" required minlength="3" maxlength="255">
        <label>Comprobante (opcional)</label><input type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf">
        <p><button class="btn">Avisar mi pago</button></p>
      </form>
    </div></div>
  @endif
@endif

@if($o->status === 'confirmed' && $tickets->where('status','valid')->isNotEmpty())
  <p>@if(\Aero\Tickets\Classes\TicketRenderer::pdfAvailable())<a class="btn" href="{{ url('orden/'.$o->code.'/entradas.pdf') }}">Descargar todas en PDF</a> @endif
  @if($o->buyer_email)<form method="post" action="{{ url('orden/'.$o->code.'/reenviar') }}" style="display:inline">@csrf<button class="btn sec">Reenviar por correo</button></form>@endif</p>
@endif
@if($tickets->isNotEmpty() && in_array($o->status, ['confirmed','pending_payment','verifying','incident'], true))
  <h3>Entradas</h3>
  @foreach($tickets as $t)<p><a class="btn sec" href="{{ url('entrada/'.$t->public_token) }}">{{ $t->holder_name }} — {{ \Aero\Tickets\Models\Ticket::$statuses[$t->status] }}</a></p>@endforeach
@endif
</div>
@endsection
