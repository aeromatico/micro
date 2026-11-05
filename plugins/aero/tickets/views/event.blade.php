@extends('aerotickets::layout')
@section('title', $e->name)
@section('robots', $e->visibility === 'public' ? 'index,follow' : 'noindex,nofollow')
@section('content')
<p><a href="{{ url('eventos') }}">← Eventos</a></p>
@if($e->cover)<img src="{{ $e->cover->path }}" alt="" style="width:100%;max-height:380px;object-fit:cover;border-radius:14px">@endif
<h1>{{ $e->name }}</h1>
@if($e->status === 'cancelled')<div class="alert bad">Este evento fue cancelado.</div>
@elseif($e->status === 'finished')<div class="alert">Este evento ya finalizó.</div>
@elseif($e->status === 'ongoing')<div class="alert ok">El evento está en curso.</div>@endif
<div class="two">
  <div>
    <p class="mut">{{ $e->category?->name }}</p>
    <div style="white-space:pre-line">{{ $e->description }}</div>
    @if($e->requirements)<h3>Condiciones de ingreso</h3><div style="white-space:pre-line">{{ $e->requirements }}</div>@endif
    @if($e->min_age)<p><strong>Edad mínima:</strong> {{ $e->min_age }} años</p>@endif
    @if($e->gallery->count())<div class="grid" style="margin-top:12px">@foreach($e->gallery as $img)<img src="{{ $img->path }}" alt="" loading="lazy" style="width:100%;border-radius:10px">@endforeach</div>@endif
    <h3>Organiza</h3><p>{{ $e->organizer_name }}<br><span class="mut">{{ $e->organizer_description }}</span></p>
    @if($e->contact_phone || $e->contact_whatsapp || $e->contact_email)
      <p class="mut">Consultas: {{ $e->contact_name }} {{ $e->contact_phone }} {{ $e->contact_email }}
      @if($e->contact_whatsapp) · <a href="https://wa.me/{{ preg_replace('/\D/','',$e->contact_whatsapp) }}">WhatsApp</a>@endif</p>
    @endif
  </div>
  <aside>
    <div class="card"><div class="b">
      <p><strong>{{ $e->starts_at?->translatedFormat('l d \d\e F, H:i') }}</strong>@if($e->ends_at)<br><span class="mut">hasta {{ $e->ends_at->translatedFormat('d M H:i') }}</span>@endif</p>
      <p>{{ $e->venue_name }}<br><span class="mut">{{ $e->address }}{{ $e->zone ? ', '.$e->zone : '' }}, {{ $e->city }}{{ $e->department ? ' · '.$e->department : '' }}</span>
      @if($e->address_reference)<br><span class="mut">{{ $e->address_reference }}</span>@endif</p>
      @if($e->lat && $e->lng)<p><a href="https://www.openstreetmap.org/?mlat={{ $e->lat }}&mlon={{ $e->lng }}#map=17/{{ $e->lat }}/{{ $e->lng }}" target="_blank" rel="noopener">Ver en el mapa</a></p>@endif
      @if($left !== null)<p class="mut">{{ $left > 0 ? $left.' lugares disponibles' : 'Agotado' }}</p>@endif
    </div></div>

    <div class="card" style="margin-top:16px"><div class="b">
      <h3 style="margin-top:0">Entradas</h3>
      @foreach($types->where('kind','!=','courtesy') as $t)
        <div style="padding:8px 0;border-top:1px solid var(--line)">
          <strong>{{ $t->name }}</strong>
          @if($t->inv['sold_out'])<span class="pill">Agotada</span>
          @elseif(!$t->isOnSale())<span class="pill">{{ $t->sales_start && $t->sales_start->isFuture() ? 'Desde '.$t->sales_start->translatedFormat('d M H:i') : 'Venta cerrada' }}</span>@endif
          @if($t->description)<div class="mut">{{ $t->description }}</div>@endif
          @if($t->kind === 'free')<div>Gratis</div>
          @else
            <div><strong>{{ \Aero\Tickets\Classes\Currency::format($t->quote['total'], $t->quote['currency']) }}</strong>
            @if($t->quote['fee'] > 0 && $t->quote['fee_payer'] === 'buyer')<span class="mut">(base {{ \Aero\Tickets\Classes\Currency::format($t->quote['base'], $t->quote['currency']) }} + comisión {{ \Aero\Tickets\Classes\Currency::format($t->quote['fee'], $t->quote['currency']) }})</span>@endif</div>
          @endif
          @if(!$t->inv['sold_out'] && $t->inv['available'] !== null && $t->inv['available'] <= 10)<div class="mut">¡Quedan {{ $t->inv['available'] }}!</div>@endif
        </div>
      @endforeach
    </div></div>

    @if($e->acceptsSales())
    @php($buyable = $types->filter(fn($t) => $t->kind !== 'courtesy' && $t->isOnSale() && !$t->inv['sold_out']))
    <div class="card" style="margin-top:16px"><div class="b">
      <h3 style="margin-top:0">{{ $e->registration === 'approval' ? 'Solicitar inscripción' : 'Elige tus entradas' }}</h3>
      @if($errors->any())<div class="alert bad">{{ $errors->first() }}</div>@endif
      @if(session('notice'))<div class="alert ok">{{ session('notice') }}</div>@endif
      @if($buyable->isEmpty())<p class="mut">Por ahora no hay entradas disponibles.</p>
      @else
      <form method="post" action="{{ url('evento/'.$e->slug.'/comprar') }}" id="buy">@csrf
        <input type="hidden" name="t" value="{{ $token }}">
        <input type="hidden" name="nonce" value="{{ old('nonce', \Illuminate\Support\Str::random(32)) }}">
        @foreach($buyable as $t)
          @php($max = min(max(1, (int) $t->max_per_order), $t->inv['available'] ?? 50, $left ?? 50))
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 0;border-top:1px solid var(--line)">
            <div><strong>{{ $t->name }}</strong><br><span class="mut">{{ $t->kind === 'free' ? 'Gratis' : \Aero\Tickets\Classes\Currency::format($t->quote['total'], $t->quote['currency']) }} · máx. {{ $max }}</span></div>
            <input type="number" name="qty[{{ $t->id }}]" min="0" max="{{ $max }}" value="{{ old('qty.'.$t->id, 0) }}" data-price="{{ $t->quote['total'] }}" data-base="{{ $t->quote['base'] }}" style="width:76px">
          </div>
        @endforeach
        <div id="summary" class="alert" style="display:none"><div>Entradas: <strong id="s-n">0</strong></div><div>Subtotal: <span id="s-sub"></span></div><div>Comisiones: <span id="s-fee"></span></div><div><strong>Total: <span id="s-tot"></span></strong></div></div>
        <label>Nombre completo</label><input name="name" value="{{ old('name') }}" required maxlength="255">
        <label>Correo</label><input type="email" name="email" value="{{ old('email') }}" required>
        <label>Teléfono / WhatsApp</label><input name="phone" value="{{ old('phone') }}" required maxlength="30">
        <label>Documento (opcional)</label><input name="document" value="{{ old('document') }}" maxlength="30">
        @if($e->buyer_extra_label)<label>{{ $e->buyer_extra_label }}{{ $e->buyer_extra_required ? '' : ' (opcional)' }}</label><input name="extra" value="{{ old('extra') }}" maxlength="500" {{ $e->buyer_extra_required ? 'required' : '' }}>@endif
        <p class="mut">No necesitas crear una cuenta. Las entradas de pago se emiten cuando se confirma tu pago; la compra no reserva lugares.</p>
        <p><button class="btn" style="width:100%">{{ $e->registration === 'approval' ? 'Enviar solicitud' : 'Continuar' }}</button></p>
      </form>
      <script>
      (function(){var f=document.getElementById('buy');if(!f)return;var q=[].slice.call(f.querySelectorAll('input[name^="qty"]'));
      function fmt(n){return 'Bs '+n.toFixed(2).replace('.',',')}
      function up(){var n=0,sub=0,tot=0;q.forEach(function(i){var c=parseInt(i.value)||0;n+=c;sub+=c*parseFloat(i.dataset.base);tot+=c*parseFloat(i.dataset.price)});
      document.getElementById('summary').style.display=n?'':'none';document.getElementById('s-n').textContent=n;
      document.getElementById('s-sub').textContent=fmt(sub);document.getElementById('s-fee').textContent=fmt(tot-sub);document.getElementById('s-tot').textContent=fmt(tot)}
      q.forEach(function(i){i.addEventListener('input',up)});up()})();
      </script>
      @endif
    </div></div>
    @endif
  </aside>
</div>
@endsection
