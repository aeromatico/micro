<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Eventos')</title>
<meta name="robots" content="@yield('robots', 'index,follow')">
<style>
:root{--bg:#f6f7f9;--card:#fff;--ink:#16181d;--mut:#667085;--line:#e4e7ec;--brand:#4f46e5;--ok:#047857;--bad:#b42318}
@media (prefers-color-scheme:dark){:root{--bg:#0f1115;--card:#171a21;--ink:#eef0f4;--mut:#98a2b3;--line:#2a2f3a;--brand:#818cf8;--ok:#34d399;--bad:#f97066}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
.wrap{max-width:1040px;margin:0 auto;padding:16px}
a{color:var(--brand)}h1{font-size:1.7rem;line-height:1.2;margin:.2em 0}
.grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fill,minmax(250px,1fr))}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;overflow:hidden}
.card .b{padding:14px}.card img{display:block;width:100%;aspect-ratio:16/9;object-fit:cover;background:var(--line)}
.mut{color:var(--mut);font-size:.9rem}.pill{display:inline-block;padding:2px 10px;border-radius:99px;background:var(--line);font-size:.8rem}
input,select,textarea{width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;background:var(--card);color:var(--ink);font:inherit}
label{display:block;font-size:.9rem;margin:10px 0 4px}
.btn{display:inline-block;border:0;border-radius:10px;background:var(--brand);color:#fff;padding:11px 18px;font:inherit;cursor:pointer;text-decoration:none}
.btn.sec{background:transparent;color:var(--ink);border:1px solid var(--line)}
.alert{padding:12px 14px;border-radius:10px;margin:12px 0;border:1px solid var(--line)}.alert.bad{color:var(--bad);border-color:var(--bad)}.alert.ok{color:var(--ok);border-color:var(--ok)}
.filters{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin:12px 0 20px}
.two{display:grid;gap:20px;grid-template-columns:1fr}@media(min-width:800px){.two{grid-template-columns:1.6fr 1fr}}
</style>
</head>
<body><div class="wrap">@yield('content')</div></body>
</html>
