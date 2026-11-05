@extends('aerotickets::layout')
@section('title', 'Eventos')
@section('content')
<h1>Eventos</h1>
<form method="get" class="filters">
  <input type="search" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Buscar evento o lugar">
  <select name="categoria"><option value="">Todas las categorías</option>@foreach($categories as $c)<option value="{{ $c->slug }}" @selected(($f['categoria'] ?? '') === $c->slug)>{{ $c->name }}</option>@endforeach</select>
  <select name="ciudad"><option value="">Todas las ciudades</option>@foreach($cities as $c)<option @selected(($f['ciudad'] ?? '') === $c)>{{ $c }}</option>@endforeach</select>
  <input type="date" name="desde" value="{{ $f['desde'] ?? '' }}" aria-label="Desde">
  <input type="date" name="hasta" value="{{ $f['hasta'] ?? '' }}" aria-label="Hasta">
  <button class="btn">Filtrar</button>
</form>
@forelse($events as $e)
  @if($loop->first)<div class="grid">@endif
  <a class="card" href="{{ url('evento/'.$e->slug) }}" style="text-decoration:none;color:inherit">
    @if($e->cover)<img src="{{ $e->cover->path }}" alt="" loading="lazy">@else<img alt="" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">@endif
    <div class="b"><span class="pill">{{ $e->category?->name ?? 'Evento' }}</span>
      <h2 style="font-size:1.1rem;margin:.4em 0 .1em">{{ $e->name }}</h2>
      <div class="mut">{{ $e->starts_at?->translatedFormat('D d M, H:i') }} · {{ $e->venue_name }}{{ $e->city ? ', '.$e->city : '' }}</div>
      @if($e->status === 'ongoing')<div class="mut">En curso</div>@endif</div>
  </a>
  @if($loop->last)</div>@endif
@empty
  <p class="mut">No hay eventos que coincidan.</p>
@endforelse
<div style="margin-top:16px">{{ $events->links() }}</div>
@endsection
