@extends('aeroshopify::layout')
@section('title', 'Pedido ' . $link->order_name)
@section('content')
<h1>Pedido {{ $link->order_name }}</h1>
<div class="amount">{{ number_format((float) $link->amount, 2) }} {{ $link->currency }}</div>

@if($link->status === 'paid')
    <p class="ok">¡Pago recibido! Gracias por tu compra.</p>
@elseif(in_array($link->status, ['cancelled', 'expired'], true))
    <p class="err">Este cobro {{ $link->status === 'expired' ? 'venció' : 'fue cancelado' }}. Contacta a la tienda.</p>
@elseif(!$qr || $link->status === 'error' || $link->status === 'skipped')
    <p class="err">No pudimos generar tu QR. La tienda se pondrá en contacto contigo.</p>
@elseif($qr->renderingType() === 'redirect' && $qr->redirect_url)
    <p>Completa el pago en el sitio de tu proveedor.</p>
    <a class="btn" href="{{ $qr->redirect_url }}" rel="noopener">Pagar ahora</a>
@else
    <p>Escanea el QR con la app de tu banco.</p>
    <img class="qr" alt="QR de pago" src="{{ url('api/v1/pay/public/qr/' . $qr->internal_reference . '/image') }}">
    <p id="state">Esperando tu pago…</p>
    <script>
    (function () {
        var url = @json(url('shopify/pagar/o/' . $link->token . '/estado'));
        var el = document.getElementById('state');
        var timer = setInterval(function () {
            fetch(url, {cache: 'no-store'}).then(function (r) { return r.json(); }).then(function (d) {
                if (d.status !== 'pending') { clearInterval(timer); location.reload(); }
            }).catch(function () {});
        }, 5000);
    })();
    </script>
@endif
@endsection
