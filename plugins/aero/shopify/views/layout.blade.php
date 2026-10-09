<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>@yield('title') · {{ $store->name }}</title>
<style>
:root{--bg:#f6f7f9;--card:#fff;--fg:#14171c;--muted:#5c6673;--line:#e3e6ea;--accent:#0b6bcb}
@media (prefers-color-scheme:dark){:root{--bg:#0f1115;--card:#181b21;--fg:#eceff3;--muted:#9aa4b2;--line:#2a2f38;--accent:#5aa2f0}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--fg);font:16px/1.5 system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;padding:16px}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:24px;width:100%;max-width:420px;text-align:center}
h1{font-size:1.2rem;margin:0 0 4px}p{color:var(--muted);margin:4px 0 16px}
img.qr{width:100%;max-width:280px;border-radius:8px;background:#fff;padding:8px}
.amount{font-size:1.6rem;font-weight:700;margin:8px 0}
input{width:100%;padding:10px 12px;margin:6px 0;border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--fg);font:inherit}
button,.btn{display:inline-block;width:100%;padding:11px;margin-top:10px;border:0;border-radius:8px;background:var(--accent);color:#fff;font:inherit;font-weight:600;text-decoration:none;cursor:pointer}
.err{color:#c0392b}.ok{color:#1e8e4f;font-weight:600}
</style>
</head>
<body><main class="card">@yield('content')</main></body>
</html>
