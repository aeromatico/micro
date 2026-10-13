<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=%%VW%%, height=%%VH%%" />
<script src="https://cdn.jsdelivr.net/npm/gsap@3.14.2/dist/gsap.min.js"></script>
<style>
%%FONTS%%
*{margin:0;padding:0;box-sizing:border-box}
html,body{width:%%VW%%px;height:%%VH%%px;overflow:hidden;background:#fff}
#root{position:relative;width:%%VW%%px;height:%%VH%%px;background:#fff;font-family:Inter,system-ui,sans-serif;overflow:hidden}
svg{position:absolute;inset:0}
.ink{stroke:#3f3f46;stroke-width:var(--sw,6);stroke-linecap:round;stroke-linejoin:round;fill:none}
.thin{stroke-width:4}
.cap{position:absolute;left:160px;width:1600px;bottom:48px;text-align:center;font-size:42px;font-weight:500;color:#3f3f46;line-height:1.25;opacity:0;text-wrap:balance}
#brand{position:absolute;left:0;width:%%VW%%px;top:300px;text-align:center;opacity:0}
#brand b{display:block;font-family:Urbanist,sans-serif;font-size:200px;font-weight:900;line-height:.86;letter-spacing:-.02em;color:#27272a}
#brand em{display:block;font-family:Urbanist,sans-serif;font-style:normal;font-size:50px;font-weight:300;letter-spacing:.03em;color:#3f3f46}
#brand .tags{margin-top:44px;display:flex;justify-content:center;gap:18px}
#brand .tags i{font-style:normal;font-size:38px;font-weight:700;color:#27272a;border:4px solid #3f3f46;border-radius:999px;padding:8px 26px;background:#fff}
#brand p{margin-top:36px;font-size:46px;color:#3f3f46}
#brand span{display:block;margin-top:12px;font-size:42px;color:#71717a}
text{font-family:Inter,system-ui,sans-serif}
.mono{font-family:monospace}
#vtitle{display:none;position:absolute;left:0;width:%%VW%%px;top:90px;text-align:center;font-family:Urbanist,sans-serif;color:#27272a;opacity:0}
#vtitle b{display:block;font-size:110px;font-weight:900;line-height:1;letter-spacing:-.02em}
#vtitle span{display:block;margin-top:8px;font-size:50px;font-weight:300}
#vtitle i{font-style:normal;font-weight:900}
%%VARCSS%%
#cover{position:absolute;inset:0;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:Urbanist,sans-serif;color:#27272a}
#cover h1{font-size:260px;font-weight:900;line-height:.92;letter-spacing:-.03em;text-align:center}
#cover .by{margin-top:56px;display:flex;align-items:center;gap:28px;font-size:84px;line-height:1}
#cover .by .flag{font-size:92px}
#cover .by span{font-weight:300}
#cover .by b{font-weight:900}
</style>
</head>
<body>
<div id="root" data-composition-id="main" data-start="0" data-duration="%%TOTAL%%" data-width="%%VW%%" data-height="%%VH%%">
<svg id="stage" viewBox="%%VIEWBOX%%" width="%%SVGW%%" height="%%SVGH%%">
  <g id="world">

    <!-- ═════ Fila superior: los tres sistemas ═════ -->
    <g id="upper">
      <!-- Tu sistema -->
      <g id="boxA" opacity="0">
        <rect class="ink" x="460" y="130" width="270" height="200" rx="22" fill="#fff"/>
        <line class="ink thin" x1="460" y1="176" x2="730" y2="176"/>
        <circle cx="486" cy="153" r="6" fill="#f59e0b"/><circle cx="508" cy="153" r="6" fill="#d4d4d8"/><circle cx="530" cy="153" r="6" fill="#d4d4d8"/>
        <rect x="486" y="204" width="150" height="14" rx="7" fill="#e4e4e7"/>
        <rect x="486" y="234" width="198" height="14" rx="7" fill="#e4e4e7"/>
        <rect x="486" y="264" width="120" height="14" rx="7" fill="#fde7c0"/>
        <text x="595" y="378" text-anchor="middle" font-size="32" font-weight="700" fill="#27272a">Tu sistema</text>
      </g>
      <!-- Bolivia Pay -->
      <g id="boxB" opacity="0">
        <rect id="boxBr" class="ink" x="820" y="130" width="270" height="200" rx="22" fill="#fff"/>
        <rect x="905" y="165" width="100" height="100" rx="12" fill="#fff" stroke="#3f3f46" stroke-width="4"/>
        <g id="boxBqr"></g>
        <text x="955" y="306" text-anchor="middle" font-size="22" font-weight="700" fill="#71717a">/api/v1/pay</text>
        <text x="955" y="378" text-anchor="middle" font-size="32" font-weight="700" fill="#27272a">Bolivia Pay</text>
      </g>
      <!-- Banco -->
      <g id="boxC" opacity="0">
        <rect class="ink" x="1180" y="130" width="270" height="200" rx="22" fill="#fff"/>
        <path d="M1260 215 L1315 170 L1370 215 Z" fill="#fff" stroke="#3f3f46" stroke-width="5" stroke-linejoin="round"/>
        <line x1="1280" y1="228" x2="1280" y2="278" stroke="#3f3f46" stroke-width="5" stroke-linecap="round"/>
        <line x1="1315" y1="228" x2="1315" y2="278" stroke="#3f3f46" stroke-width="5" stroke-linecap="round"/>
        <line x1="1350" y1="228" x2="1350" y2="278" stroke="#3f3f46" stroke-width="5" stroke-linecap="round"/>
        <line x1="1252" y1="292" x2="1378" y2="292" stroke="#3f3f46" stroke-width="5" stroke-linecap="round"/>
        <text x="1315" y="378" text-anchor="middle" font-size="32" font-weight="700" fill="#27272a">Banco</text>
      </g>
      <!-- Flechas: petición (A→B) y confirmación (C→B→A) -->
      <g id="arrAB" opacity="0"><path d="M740 215 H808 M796 203 L808 215 L796 227" fill="none" stroke="#3f3f46" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/></g>
      <g id="arrBA" opacity="0"><path d="M810 262 H742 M754 250 L742 262 L754 274" fill="none" stroke="#f59e0b" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="2 10"/></g>
      <g id="arrCB" opacity="0"><path d="M1170 215 H1102 M1114 203 L1102 215 L1114 227" fill="none" stroke="#f59e0b" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="2 10"/></g>
      <!-- Sobre del webhook -->
      <g id="env" opacity="0">
        <rect x="-26" y="-18" width="52" height="36" rx="6" fill="#fff" stroke="#3f3f46" stroke-width="4"/>
        <path d="M-26 -14 L0 6 L26 -14" fill="none" stroke="#3f3f46" stroke-width="4" stroke-linejoin="round"/>
        <circle cx="22" cy="14" r="13" fill="#22c55e" stroke="#fff" stroke-width="3"/>
        <path d="M16 14 l4 5 l8 -10" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
      </g>
    </g>

    <!-- ═════ Fila inferior: paneles por escena ═════ -->
    <g id="lower">
      <!-- 2 · token -->
      <g id="p_token" opacity="0">
        <rect class="ink thin" x="520" y="470" width="880" height="320" rx="28" fill="#fff"/>
        <circle cx="600" cy="540" r="26" fill="#fde7c0" stroke="#3f3f46" stroke-width="5"/>
        <path d="M624 540 H690 M668 540 v18 M686 540 v12" fill="none" stroke="#3f3f46" stroke-width="5" stroke-linecap="round"/>
        <text x="730" y="548" font-size="40" font-weight="800" fill="#27272a">Token de API</text>
        <text x="730" y="592" font-size="26" class="mono" fill="#71717a">Authorization: Bearer ••••••••••••</text>
        <g id="perm1" opacity="0"><rect x="570" y="650" width="260" height="76" rx="38" fill="#fff" stroke="#3f3f46" stroke-width="4"/><circle cx="610" cy="688" r="18" fill="#22c55e"/><path d="M601 688 l6 7 l12 -15" fill="none" stroke="#fff" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/><text x="640" y="698" font-size="27" font-weight="700" fill="#27272a">Crear cobros</text></g>
        <g id="perm2" opacity="0"><rect x="850" y="650" width="230" height="76" rx="38" fill="#fff" stroke="#3f3f46" stroke-width="4"/><circle cx="890" cy="688" r="18" fill="#22c55e"/><path d="M881 688 l6 7 l12 -15" fill="none" stroke="#fff" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/><text x="920" y="698" font-size="27" font-weight="700" fill="#27272a">Consultar</text></g>
        <g id="perm3" opacity="0"><rect x="1100" y="650" width="260" height="76" rx="38" fill="#fff" stroke="#3f3f46" stroke-width="4"/><circle cx="1140" cy="688" r="18" fill="#22c55e"/><path d="M1131 688 l6 7 l12 -15" fill="none" stroke="#fff" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/><text x="1170" y="698" font-size="27" font-weight="700" fill="#27272a">Anular</text></g>
      </g>

      <!-- 3 · petición y respuesta -->
      <g id="p_req" opacity="0">
        <rect class="ink thin" x="470" y="460" width="560" height="330" rx="26" fill="#fff"/>
        <rect x="494" y="482" width="330" height="46" rx="23" fill="#27272a"/>
        <text x="659" y="513" text-anchor="middle" font-size="24" font-weight="700" class="mono" fill="#fff">POST /api/v1/pay/qr</text>
        <text x="500" y="590" font-size="27" class="mono" fill="#27272a">amount: <tspan font-weight="800" fill="#d97706">150</tspan></text>
        <text x="500" y="640" font-size="27" class="mono" fill="#27272a">currency: <tspan font-weight="800" fill="#d97706">"BOB"</tspan></text>
        <text x="500" y="690" font-size="27" class="mono" fill="#27272a">external_reference:</text>
        <text x="500" y="735" font-size="27" class="mono" font-weight="800" fill="#d97706">  "PED-1042"</text>
      </g>
      <g id="p_res" opacity="0">
        <path d="M1042 625 H1090 M1078 613 L1090 625 L1078 637" fill="none" stroke="#f59e0b" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
        <rect class="ink thin" x="1100" y="460" width="360" height="330" rx="26" fill="#fff"/>
        <rect x="1160" y="490" width="170" height="170" rx="14" fill="#fff" stroke="#3f3f46" stroke-width="4"/>
        <g id="resqr"></g>
        <text x="1280" y="716" text-anchor="middle" font-size="26" class="mono" fill="#27272a">status: <tspan font-weight="800" fill="#d97706">"pending"</tspan></text>
        <text x="1280" y="758" text-anchor="middle" font-size="24" fill="#71717a">QR listo para mostrar</text>
      </g>

      <!-- 4 · cliente + webhook -->
      <g id="p_phone" opacity="0">
        <rect class="ink" x="1260" y="470" width="112" height="190" rx="20" fill="#fff"/>
        <circle cx="1316" cy="500" r="5" fill="#d4d4d8"/>
        <rect x="1282" y="520" width="68" height="68" rx="8" fill="#fff" stroke="#3f3f46" stroke-width="3.5"/>
        <g id="phqr"></g>
        <text x="1316" y="710" text-anchor="middle" font-size="28" font-weight="700" fill="#27272a">Cliente paga</text>
        <path d="M1316 462 V348" fill="none" stroke="#f59e0b" stroke-width="5" stroke-linecap="round" stroke-dasharray="2 10"/>
      </g>
      <g id="p_hook" opacity="0">
        <rect id="hookr" class="ink thin" x="470" y="470" width="700" height="320" rx="26" fill="#fff"/>
        <rect x="494" y="492" width="250" height="46" rx="23" fill="#27272a"/>
        <text x="619" y="523" text-anchor="middle" font-size="24" font-weight="700" class="mono" fill="#fff">POST /tu-webhook</text>
        <text x="500" y="592" font-size="26" class="mono" fill="#27272a">event: <tspan font-weight="800" fill="#16a34a">"qr.paid"</tspan></text>
        <text x="500" y="640" font-size="26" class="mono" fill="#27272a">X-Pay-Signature:</text>
        <text x="500" y="684" font-size="26" class="mono" font-weight="800" fill="#d97706">  sha256=9f2c…</text>
        <text x="500" y="740" font-size="26" class="mono" fill="#27272a">data: { reference, amount… }</text>
      </g>

      <!-- 5 · reintentos y firma -->
      <g id="p_retry" opacity="0">
        <rect class="ink thin" x="460" y="470" width="1000" height="330" rx="28" fill="#fff"/>
        <text x="500" y="526" font-size="32" font-weight="800" fill="#27272a">Si no responde, vuelve a intentarlo</text>
        <line x1="560" y1="610" x2="1360" y2="610" stroke="#d4d4d8" stroke-width="6" stroke-linecap="round"/>
        <g id="d1" opacity="0"><circle cx="560" cy="610" r="17" fill="#fff" stroke="#f59e0b" stroke-width="6"/><text x="560" y="662" text-anchor="middle" font-size="24" fill="#3f3f46">15 s</text></g>
        <g id="d2" opacity="0"><circle cx="720" cy="610" r="17" fill="#fff" stroke="#f59e0b" stroke-width="6"/><text x="720" y="662" text-anchor="middle" font-size="24" fill="#3f3f46">1 min</text></g>
        <g id="d3" opacity="0"><circle cx="880" cy="610" r="17" fill="#fff" stroke="#f59e0b" stroke-width="6"/><text x="880" y="662" text-anchor="middle" font-size="24" fill="#3f3f46">5 min</text></g>
        <g id="d4" opacity="0"><circle cx="1040" cy="610" r="17" fill="#fff" stroke="#f59e0b" stroke-width="6"/><text x="1040" y="662" text-anchor="middle" font-size="24" fill="#3f3f46">15 min</text></g>
        <g id="d5" opacity="0"><circle cx="1200" cy="610" r="17" fill="#fff" stroke="#f59e0b" stroke-width="6"/><text x="1200" y="662" text-anchor="middle" font-size="24" fill="#3f3f46">30 min</text></g>
        <g id="d6" opacity="0"><circle cx="1360" cy="610" r="17" fill="#fff" stroke="#f59e0b" stroke-width="6"/><text x="1360" y="662" text-anchor="middle" font-size="24" fill="#3f3f46">1 h</text></g>
        <g id="sig" opacity="0">
          <rect x="520" y="705" width="880" height="66" rx="33" fill="#dcfce7" stroke="#16a34a" stroke-width="4"/>
          <rect x="552" y="727" width="22" height="18" rx="4" fill="#16a34a"/><path d="M556 727 v-6 a7 7 0 0 1 14 0 v6" fill="none" stroke="#16a34a" stroke-width="4" stroke-linecap="round"/>
          <text x="595" y="749" font-size="27" font-weight="800" fill="#14532d">Firma HMAC-SHA256: compruebas que es auténtico</text>
        </g>
      </g>

      <!-- 6 · quién ya cobra con Pay -->
      <g id="p_tiles" opacity="0">
        <g id="t1" opacity="0"><rect class="ink thin" x="460" y="480" width="230" height="230" rx="26" fill="#fff"/>
          <path d="M520 556 h18 l12 46 h50 l10 -34 h-72" fill="#fde7c0" stroke="#3f3f46" stroke-width="4.5" stroke-linejoin="round" stroke-linecap="round"/><circle cx="558" cy="620" r="7" fill="#3f3f46"/><circle cx="604" cy="620" r="7" fill="#3f3f46"/>
          <text x="575" y="676" text-anchor="middle" font-size="26" font-weight="800" fill="#27272a">WooCommerce</text></g>
        <g id="t2" opacity="0"><rect class="ink thin" x="715" y="480" width="230" height="230" rx="26" fill="#fff"/>
          <path d="M785 548 h90 a14 14 0 0 1 14 14 v40 a14 14 0 0 1 -14 14 h-48 l-22 18 v-18 h-20 a14 14 0 0 1 -14 -14 v-40 a14 14 0 0 1 14 -14 Z" fill="#fde7c0" stroke="#3f3f46" stroke-width="4.5" stroke-linejoin="round" transform="translate(-6,0)"/>
          <text x="830" y="676" text-anchor="middle" font-size="26" font-weight="800" fill="#27272a">OmniChat</text></g>
        <g id="t3" opacity="0"><rect class="ink thin" x="970" y="480" width="230" height="230" rx="26" fill="#fff"/>
          <circle cx="1030" cy="580" r="16" fill="#fde7c0" stroke="#3f3f46" stroke-width="4.5"/><circle cx="1085" cy="548" r="16" fill="#fff" stroke="#3f3f46" stroke-width="4.5"/><circle cx="1140" cy="596" r="16" fill="#f59e0b" stroke="#3f3f46" stroke-width="4.5"/>
          <path d="M1045 574 L1070 556 M1100 556 L1126 588" fill="none" stroke="#3f3f46" stroke-width="4.5" stroke-linecap="round"/>
          <text x="1085" y="676" text-anchor="middle" font-size="26" font-weight="800" fill="#27272a">Workflows</text></g>
        <g id="t4" opacity="0"><rect id="t4r" class="ink thin" x="1225" y="480" width="230" height="230" rx="26" fill="#fff"/>
          <path d="M1305 556 l-30 30 l30 30 M1375 556 l30 30 l-30 30 M1352 546 l-24 80" fill="none" stroke="#3f3f46" stroke-width="5.5" stroke-linecap="round" stroke-linejoin="round"/>
          <text x="1340" y="676" text-anchor="middle" font-size="26" font-weight="800" fill="#27272a">Tu sistema</text></g>
      </g>
    </g>
  </g>
</svg>
<div id="cover"><h1 id="cv1">API de<br>pagos</h1><div class="by" id="cv2"><span class="flag">🇧🇴</span><span>by <b>Market</b></span></div></div>
<div id="vtitle"><b>API de pagos</b><span>by <i>Market</i></span></div>
<div id="brand"><b>Market</b><em>by Clouds</em><div class="tags"><i>API</i><i>Webhooks</i><i>Bolivia Pay</i></div><p>Cobros, confirmaciones y automatización.</p><span>market.com.bo</span></div>
%%CAPS%%
%%AUDIO%%
</div>
<script>
const S=%%SCENES%%;
const T=%%BEATS%%;
const COVER=%%COVER%%;
const VERT=%%VERT%%;
const NS='http://www.w3.org/2000/svg';
let seed=21;const rnd=()=>{seed=(seed*9301+49297)%233280;return seed/233280};
function mkQR(g,x,y,size,n){
  const c=size/n;const fin=(a,b)=>[[0,0],[n-3,0],[0,n-3]].some(([p,q])=>a>=p&&a<p+3&&b>=q&&b<q+3);
  for(let j=0;j<n;j++)for(let i=0;i<n;i++){
    if(!(fin(i,j)?true:rnd()>.5))continue;
    const r=document.createElementNS(NS,'rect');r.setAttribute('x',x+i*c);r.setAttribute('y',y+j*c);
    r.setAttribute('width',c-(size>60?1.5:1));r.setAttribute('height',c-(size>60?1.5:1));r.setAttribute('rx',1);r.setAttribute('fill','#27272a');g.appendChild(r)}
}
mkQR(document.getElementById('boxBqr'),913,173,84,9);
mkQR(document.getElementById('resqr'),1172,502,146,11);
mkQR(document.getElementById('phqr'),1288,526,56,7);

gsap.set(['#cv1','#cv2','#vtitle','#brand'],{opacity:0});
if(VERT){ gsap.set('#lower',{y:230}); }

const tl=gsap.timeline({paused:true});
const pop=(sel,at,ox,oy,dy)=>{tl.fromTo(sel,{opacity:0,y:dy===undefined?14:dy},{opacity:1,y:0,duration:.45,ease:'power3.out',immediateRender:false},at)};
const fade=(sel,at,dur)=>{tl.fromTo(sel,{opacity:0},{opacity:1,duration:dur||.35,immediateRender:false},at)};
const cap=(i,a,b)=>{tl.to('#cap'+i,{opacity:1,duration:.3},a).to('#cap'+i,{opacity:0,duration:.3},b)};
S.forEach((s,i)=>cap(i+1,s.t0+.2,s.t0+.3+s.d+.45));

// carátula
tl.fromTo('#cv1',{opacity:0,y:50,scale:.92},{opacity:1,y:0,scale:1,duration:.7,ease:'power3.out',immediateRender:false},.2);
tl.fromTo('#cv2',{opacity:0,y:30},{opacity:1,y:0,duration:.6,ease:'power2.out',immediateRender:false},.8);
tl.to('#cover',{opacity:0,duration:.5,ease:'power1.inOut'},COVER-.1);
tl.set('#cover',{display:'none'},COVER+.45);
if(VERT){ tl.fromTo('#vtitle',{opacity:0,y:-20},{opacity:1,y:0,duration:.6,immediateRender:false},COVER+.3).to('#vtitle',{opacity:0,duration:.4},S[6].t0); }

// 1 · tu sistema ↔ Bolivia Pay
pop('#boxA',T.boxes,595,230,16); pop('#boxB',T.boxes+.3,955,230,16); pop('#boxC',T.boxes+.6,1315,230,16);
fade('#arrAB',T.arrow,.4);
// 2 · token con permisos
pop('#p_token',T.token,960,630,18);
['#perm1','#perm2','#perm3'].forEach((s,k)=>pop(s,T.perm+k*.3,700+k*270,688,8));
// 3 · petición y QR de vuelta
tl.to('#p_token',{opacity:0,duration:.3},S[2].t0);
pop('#p_req',T.req,750,625,18);
fade('#arrBA',T.res,.4);
pop('#p_res',T.res,1280,625,18);
tl.to('#boxBr',{stroke:'#f59e0b',duration:.3},T.res);
// 4 · el cliente paga y el webhook llega
tl.to(['#p_req','#p_res','#arrAB','#arrBA'],{opacity:0,duration:.3},S[3].t0);
tl.to('#boxBr',{stroke:'#3f3f46',duration:.2},S[3].t0);
pop('#p_phone',T.phone,1316,565,16);
fade('#arrCB',T.bank,.4);
pop('#p_hook',T.hook,820,630,18);
tl.fromTo('#env',{opacity:0,x:955,y:262},{opacity:1,x:955,y:262,duration:.12,immediateRender:false},T.hook);
tl.to('#env',{x:595,y:262,duration:.9,ease:'power2.inOut'},T.hook+.15);
tl.fromTo('#arrBA',{opacity:0},{opacity:1,duration:.3,immediateRender:false},T.hook);
tl.to('#env',{scale:1.25,duration:.15,yoyo:true,repeat:1},T.hook+1.1);
tl.to('#hookr',{stroke:'#16a34a',duration:.3},T.hook+1.1);
// 5 · reintentos con espera creciente y firma
tl.to(['#p_phone','#p_hook','#env'],{opacity:0,duration:.3},S[4].t0);
tl.to(['#arrCB','#arrBA'],{opacity:.35,duration:.3},S[4].t0);
pop('#p_retry',T.retry,960,635,18);
['#d1','#d2','#d3','#d4','#d5','#d6'].forEach((s,k)=>pop(s,T.dots+k*.45,560+k*160,610,8));
pop('#sig',T.sig,960,738,10);
// 6 · quién ya cobra con Pay
tl.to(['#p_retry','#arrCB','#arrBA'],{opacity:0,duration:.3},S[5].t0);
tl.fromTo('#p_tiles',{opacity:0},{opacity:1,duration:.1,immediateRender:false},T.tiles-.1);
pop('#t1',T.tiles,575,595,14); pop('#t2',T.tiles+.3,830,595,14); pop('#t3',T.tiles+.6,1085,595,14); pop('#t4',T.tiles+.9,1340,595,14);
tl.to('#t4r',{stroke:'#f59e0b',duration:.3},T.tiles+1.2);
// 7 · cierre
tl.to('#world',{opacity:0,duration:.6},S[6].t0);
tl.fromTo('#brand',{opacity:0,y:30},{opacity:1,y:0,duration:.7,ease:'power3.out',immediateRender:false},T.brand);
window.__timelines['main']=tl;tl.seek(0);
</script>
</body>
</html>
