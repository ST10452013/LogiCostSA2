<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$companyId = current_company_id();
function offers_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* Approximate city/province reference points only. The database has no offer GPS coordinates. */
$cities = [
'gauteng'=>['johannesburg'=>[-26.2041,28.0473],'pretoria'=>[-25.7479,28.2293],'pretoria west'=>[-25.7600,28.1600],'centurion'=>[-25.8601,28.1894],'kempton park'=>[-26.1000,28.2333],'midrand'=>[-25.9992,28.1263],'randburg'=>[-26.0936,28.0064],'soweto'=>[-26.2678,27.8585],'benoni'=>[-26.1885,28.3208],'boksburg'=>[-26.2110,28.2596],'germiston'=>[-26.2259,28.1700],'roodepoort'=>[-26.1201,27.9015]],
'western cape'=>['cape town'=>[-33.9249,18.4241],'stellenbosch'=>[-33.9321,18.8602],'paarl'=>[-33.7342,18.9621],'george'=>[-33.9630,22.4617]],
'kwazulu-natal'=>['durban'=>[-29.8587,31.0218],'pietermaritzburg'=>[-29.6006,30.3794],'newcastle'=>[-27.7579,29.9318],'richards bay'=>[-28.7807,32.0383]],
'eastern cape'=>['gqeberha'=>[-33.9608,25.6022],'port elizabeth'=>[-33.9608,25.6022],'east london'=>[-33.0153,27.9116],'mthatha'=>[-31.5889,28.7844]],
'free state'=>['bloemfontein'=>[-29.0852,26.1596],'welkom'=>[-27.9774,26.7351]],
'limpopo'=>['polokwane'=>[-23.9045,29.4689],'tzaneen'=>[-23.8332,30.1635],'thohoyandou'=>[-22.9456,30.4849]],
'mpumalanga'=>['nelspruit'=>[-25.4753,30.9694],'mbombela'=>[-25.4753,30.9694],'witbank'=>[-25.8713,29.2332],'emalahleni'=>[-25.8713,29.2332],'middelburg'=>[-25.7751,29.4648]],
'north west'=>['mahikeng'=>[-25.8652,25.6442],'rustenburg'=>[-25.6676,27.2421],'potchefstroom'=>[-26.7145,27.0970]],
'northern cape'=>['kimberley'=>[-28.7282,24.7499],'upington'=>[-28.4478,21.2561]]
];
$provinces = ['gauteng'=>[-26.2708,28.1123],'western cape'=>[-33.2278,21.8569],'kwazulu-natal'=>[-29.0,30.5],'eastern cape'=>[-32.2968,26.4194],'free state'=>[-28.4541,26.7968],'limpopo'=>[-23.4013,29.4179],'mpumalanga'=>[-25.5653,30.5279],'north west'=>[-26.6639,25.2838],'northern cape'=>[-29.0467,21.8569]];

$stmt = $conn->prepare('SELECT ml.id, ml.company_id, ml.listing_type, ml.title, ml.description, ml.province, ml.city, ml.price, ml.currency, ml.created_at, c.business_name
FROM marketplace_listings ml INNER JOIN companies c ON c.id=ml.company_id
WHERE ml.status="published" AND c.status="active" ORDER BY ml.created_at DESC');
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$offers = []; $provinceOptions = [];
foreach ($rows as $r) {
    $province = trim((string)($r['province'] ?? '')); $city = trim((string)($r['city'] ?? ''));
    $pk = mb_strtolower($province); $ck = mb_strtolower($city);
    if ($province !== '') $provinceOptions[$pk] = $province;
    $point = $cities[$pk][$ck] ?? null;
    $precision = 'Approximate city reference point';
    if ($point === null && isset($provinces[$pk])) { $point = $provinces[$pk]; $precision = 'Approximate province reference point'; }
    if ($point === null) { $point = [-29.0,24.0]; $precision = 'Location not mapped; broad South Africa reference'; }
    $offers[] = [
        'id'=>(int)$r['id'], 'company_id'=>(int)$r['company_id'], 'type'=>(string)$r['listing_type'],
        'title'=>(string)$r['title'], 'description'=>(string)($r['description'] ?? ''),
        'province'=>$province, 'city'=>$city, 'price'=>$r['price'] === null ? null : (float)$r['price'],
        'currency'=>(string)($r['currency'] ?: 'ZAR'), 'business'=>(string)$r['business_name'],
        'lat'=>(float)$point[0], 'lng'=>(float)$point[1], 'precision'=>$precision,
        'own'=>((int)$r['company_id'] === $companyId)
    ];
}
ksort($provinceOptions);
$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Offers Map | LogiCost SA 2.0</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>
.om-page{max-width:1440px;margin:28px auto;padding:0 20px 44px;color:#20352a}.om-heading{display:flex;justify-content:space-between;align-items:start;gap:16px;flex-wrap:wrap}.om-muted{color:#637168;font-size:14px;line-height:1.5}.om-warning{background:#fff8e8;border:1px solid #f0dfb7;padding:12px 15px;border-radius:9px;margin:16px 0;font-size:13px;line-height:1.5}.om-layout{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(320px,.9fr);gap:18px;margin-top:20px}.om-panel{background:#fff;border:1px solid #e0e8e1;border-radius:14px;padding:18px;min-width:0}#offers-map{height:620px;border-radius:10px;background:#eaf0eb}.om-filters{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:14px 0}.om-filters label,.om-form label{display:block;font-weight:700;font-size:13px;margin:7px 0 5px}.om-filters input,.om-filters select,.om-form textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ccd8cf;border-radius:8px;font:inherit;background:#fff}.om-count{font-size:14px;color:#52665a;margin:10px 0}.om-results{display:flex;flex-direction:column;gap:10px;max-height:560px;overflow:auto}.om-card{border:1px solid #e0e8e1;border-radius:11px;padding:14px;cursor:pointer}.om-card:hover,.om-card:focus{border-color:#278354;outline:2px solid #27835422}.om-tag{display:inline-block;background:#edf5ef;color:#176b45;border-radius:20px;padding:4px 9px;font-size:12px;text-transform:capitalize}.om-card h3{margin:8px 0;font-size:17px}.om-price{font-size:20px;font-weight:800;color:#176b45;margin:7px 0}.om-card p{overflow-wrap:anywhere}.om-empty{background:#f5f8f5;border-radius:10px;padding:18px}.om-popup{min-width:220px;max-width:280px}.om-popup h3{font-size:16px;margin:7px 0}.om-popup .om-price{font-size:18px}.om-popup textarea{width:100%;box-sizing:border-box;min-height:65px;padding:8px;border:1px solid #ccd8cf;border-radius:7px;font:inherit}.om-popup button{background:#176b45;color:white;border:0;border-radius:7px;padding:9px 11px;margin-top:7px;cursor:pointer}.om-form{border-top:1px solid #e5ebe6;padding-top:10px;margin-top:10px}.om-form textarea{min-height:78px}.om-popup .om-small{font-size:12px;color:#637168}.om-form button{background:#176b45;color:#fff;border:0;border-radius:7px;padding:9px 11px;cursor:pointer;margin-top:7px}@media(max-width:900px){.om-layout{grid-template-columns:1fr}#offers-map{height:470px}.om-results{max-height:none}}@media(max-width:520px){.om-page{padding:0 12px 32px}.om-panel{padding:12px}.om-filters{grid-template-columns:1fr}#offers-map{height:380px}}
</style>
</head>
<body class="dashboard-page">
<header class="app-header">
<a class="brand brand-dark" href="dashboard.php"><span class="brand-mark">L</span><span>LogiCost <strong>SA</strong></span></a>
<div class="header-user"><a class="btn btn-outline-dark" href="dashboard.php">Dashboard</a><a class="btn btn-outline-dark" href="marketplace.php">Marketplace</a><a class="btn btn-outline-dark" href="live-map.php">Live Map</a><span><?= offers_h(current_user_name()) ?></span><form method="post" action="logout.php"><?= csrf_field() ?><button class="btn btn-outline-dark" type="submit">Log out</button></form></div>
</header>
<main class="om-page">
<div class="om-heading"><div><h1>Offers Map</h1><p class="om-muted">Explore published products, services and delivery offers from active businesses on LogiCost SA.</p></div><a class="btn btn-primary" href="marketplace.php">Publish or manage offers</a></div>
<div class="om-warning"><strong>Location accuracy:</strong> listings currently store city and province, not business GPS coordinates. Pins are approximate city/province reference points, not exact supplier, warehouse or driver locations. Prices and availability are supplied by listing businesses.</div>
<section class="om-layout">
<div class="om-panel"><h2>Map</h2><div id="offers-map" aria-label="Map showing approximate offer locations"></div><p class="om-muted">Select a pin or offer card to view details. Map tiles require an internet connection.</p></div>
<div class="om-panel"><h2>Find offers</h2>
<div class="om-filters">
<div><label for="om-search">Search</label><input id="om-search" type="search" placeholder="Offer, business or city"></div>
<div><label for="om-type">Offer type</label><select id="om-type"><option value="">All types</option><option value="product">Products / supplies</option><option value="service">Services</option><option value="delivery">Delivery / transport</option></select></div>
<div><label for="om-province">Province</label><select id="om-province"><option value="">All provinces</option><?php foreach($provinceOptions as $p): ?><option value="<?= offers_h($p) ?>"><?= offers_h($p) ?></option><?php endforeach; ?></select></div>
<div><label for="om-price-filter">Price</label><select id="om-price-filter"><option value="">Any price</option><option value="priced">Priced offers only</option><option value="quote">Quote required</option></select></div>
</div>
<div id="om-count" class="om-count" aria-live="polite"></div><div id="om-results" class="om-results"></div>
</div>
</section>
</main>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function(){
const offers=<?= json_encode($offers, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const csrf=<?= json_encode($csrf, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
const mapEl=document.getElementById('offers-map'), results=document.getElementById('om-results'), count=document.getElementById('om-count');
const search=document.getElementById('om-search'), type=document.getElementById('om-type'), province=document.getElementById('om-province'), priceFilter=document.getElementById('om-price-filter');
if(typeof L==='undefined'){mapEl.textContent='The map could not load. Check your internet connection and refresh.';return;}
const map=L.map('offers-map').setView([-29,24],5);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
const layer=L.featureGroup().addTo(map), markerMap=new Map();
function el(tag,cls,text){const n=document.createElement(tag);if(cls)n.className=cls;if(text!==undefined)n.textContent=String(text);return n;}
function price(o){return o.price===null?'Quote required':'R '+Number(o.price).toLocaleString('en-ZA',{minimumFractionDigits:2,maximumFractionDigits:2});}
function loc(o){return [o.city,o.province].filter(Boolean).join(', ')||'Location not supplied';}
function quoteForm(parent,o){
 if(o.own){parent.appendChild(el('p','om-small','Your company’s listing'));return;}
 const f=document.createElement('form');f.method='post';f.action='marketplace.php';f.className='om-form';
 [['csrf_token',csrf],['action','request_quote'],['listing_id',String(o.id)]].forEach(([name,value])=>{const i=document.createElement('input');i.type='hidden';i.name=name;i.value=value;f.appendChild(i);});
 const label=el('label','','Message to the business');label.htmlFor='om-quote-'+o.id;f.appendChild(label);
 const ta=document.createElement('textarea');ta.id='om-quote-'+o.id;ta.name='message';ta.required=true;ta.maxLength=5000;ta.placeholder='Ask about availability, quantities, delivery or a better price.';f.appendChild(ta);
 const b=el('button','','Send quotation request');b.type='submit';f.appendChild(b);parent.appendChild(f);
}
function popup(o){
 const box=el('div','om-popup');box.appendChild(el('span','om-tag',o.type));box.appendChild(el('h3','',o.title));box.appendChild(el('div','om-small',o.business));box.appendChild(el('div','om-small',loc(o)));box.appendChild(el('div','om-price',price(o)));box.appendChild(el('p','',o.description||'No description provided.'));box.appendChild(el('div','om-small',o.precision));quoteForm(box,o);return box;
}
function matches(o){
 const q=search.value.trim().toLocaleLowerCase(), p=province.value.toLocaleLowerCase(), pf=priceFilter.value;
 const hay=[o.title,o.business,o.description,o.city,o.province,o.type].join(' ').toLocaleLowerCase();
 return (!q||hay.includes(q))&&(!type.value||o.type===type.value)&&(!p||o.province.toLocaleLowerCase()===p)&&
 (!pf||(pf==='priced'&&o.price!==null)||(pf==='quote'&&o.price===null));
}
function render(){
 const visible=offers.filter(matches);results.replaceChildren();layer.clearLayers();markerMap.clear();
 count.textContent=visible.length+(visible.length===1?' offer found':' offers found');
 if(!visible.length){results.appendChild(el('div','om-empty','No offers match these filters. Try another search or publish an offer in the marketplace.'));return;}
 visible.forEach(o=>{
  const card=el('article','om-card');card.tabIndex=0;card.setAttribute('role','button');card.setAttribute('aria-label','Show map details for '+o.title);
  card.appendChild(el('span','om-tag',o.type));card.appendChild(el('h3','',o.title));card.appendChild(el('div','om-muted',o.business+' · '+loc(o)));card.appendChild(el('div','om-price',price(o)));
  const d=o.description||'No description provided.';card.appendChild(el('p','om-muted',d.length>170?d.slice(0,170)+'…':d));card.appendChild(el('p','om-muted',o.precision));
  if(o.own)card.appendChild(el('div','om-muted','Your company’s listing'));
  function focus(){const m=markerMap.get(o.id);if(m){map.setView(m.getLatLng(),Math.max(map.getZoom(),9));m.openPopup();}}
  card.addEventListener('click',focus);card.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();focus();}});
  results.appendChild(card);
  const marker=L.marker([o.lat,o.lng]).bindPopup(popup(o),{maxWidth:320}).addTo(layer);markerMap.set(o.id,marker);
 });
 if(visible.length===1)map.setView(layer.getLayers()[0].getLatLng(),9);else map.fitBounds(layer.getBounds().pad(.12),{maxZoom:8});
}
[search,type,province,priceFilter].forEach(x=>{x.addEventListener('input',render);x.addEventListener('change',render);});
render();setTimeout(()=>map.invalidateSize(),100);
})();
</script>
</body>
</html>
