@extends('aeroshopify::layout')
@section('title', 'Pagar pedido')
@section('content')
<h1>Pagar con QR</h1>
<p>Ingresa el número de tu pedido y el correo con el que compraste.</p>
@if($error)<p class="err">{{ $error }}</p>@endif
<form method="post" action="{{ $store->lookupUrl() }}">
    <input name="order" placeholder="Número de pedido (ej. 1001)" required value="{{ old('order') }}">
    <input name="email" type="email" placeholder="Correo electrónico" required>
    <button type="submit">Ver mi QR</button>
</form>
@endsection
