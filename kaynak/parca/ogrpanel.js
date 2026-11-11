(function () {
  'use strict';
  var C = window.OSC_OP, kok = document.getElementById('osc-op');
  if (!C || !kok) return;
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function n2(x) { return Math.round(x * 100) / 100; }
  var fd = new URLSearchParams(); fd.append('action', 'osc_sp_ogr_ozet');
  fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
    if (!j || !j.success) throw new Error((j && j.data) || 'Veriler alınamadı.');
    ciz(j.data);
  }).catch(function (e) { kok.innerHTML = '<div class="osc-op-bos">' + esc(e.message) + '</div>'; });

  function ciz(D) {
    var cz = D.cozum, haftaBas = Date.now() / 1000 - 7 * 86400;
    var top = cz.length, ort = top ? Math.round(cz.reduce(function (a, x) { return a + x.puan; }, 0) / top) : 0;
    var hafta = cz.filter(function (x) { return x.tarih > haftaBas; }).length;
    var soru = cz.reduce(function (a, x) { return a + (x.d + x.y + x.b); }, 0), dogru = cz.reduce(function (a, x) { return a + x.d; }, 0);
    var bekleyen = D.odev.filter(function (o) { return !o.bitti && o.acik; });
    var son = cz.slice(0, 12).reverse();
    var grafik = '';
    if (son.length > 1) {
      var w = 520, h = 120, st = w / (son.length - 1);
      var pts = son.map(function (x, i) { return [Math.round(i * st), Math.round(h - 10 - x.puan / 100 * (h - 20))]; });
      grafik = '<svg viewBox="-8 0 ' + (w + 16) + ' ' + h + '" class="osc-op-grafik" preserveAspectRatio="none"><polyline fill="none" stroke="#4f46e5" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" points="' + pts.map(function (p) { return p.join(','); }).join(' ') + '"/>' + pts.map(function (p, i) { return '<circle cx="' + p[0] + '" cy="' + p[1] + '" r="4.5" fill="#fff" stroke="#4f46e5" stroke-width="2.5"><title>' + esc(son[i].test) + ': %' + son[i].puan + '</title></circle>'; }).join('') + '</svg>';
    }
    var konular = Object.keys(D.konu).map(function (k) { var v = D.konu[k]; return { k: k, v: v, oran: v.t ? Math.max(0, v.d) / v.t : 0 }; }).filter(function (x) { return x.v.t > 0; }).sort(function (a, b) { return a.oran - b.oran; });
    var saat = new Date().getHours(), selam = saat < 12 ? 'Günaydın' : saat < 18 ? 'İyi günler' : 'İyi akşamlar';
    kok.innerHTML =
      '<div class="osc-op-ust"><a class="lg" href="' + esc(C.home) + '"><img src="' + C.logo + '" alt="soruindir.net"></a><div><small>' + selam + ' 👋</small><h2>' + esc(D.ad) + '</h2>' + (D.sinif ? '<span class="sinif">' + esc(D.sinif) + '</span>' : '') + '</div><div class="sag">' + (C.ogretmen ? '<a href="' + esc(C.ogretmen) + '">Öğretmen paneli</a>' : '') + '<a href="' + esc(C.cikis) + '">Çıkış</a></div></div>' +
      '<form class="osc-op-katil" method="get" action="' + esc(C.home) + '"><div><b>Öğretmeninden kod mu aldın?</b><small>Canlı sınava ya da ödeve katılmak için kodu yaz.</small></div><input name="osc_k" maxlength="8" placeholder="KOD" autocomplete="off" required><button type="submit">Katıl →</button></form>' +
      '<div class="osc-op-kartlar"><div><span>📝</span><b>' + top + '</b><small>Çözülen test</small></div><div><span>🎯</span><b>%' + ort + '</b><small>Ortalama başarı</small></div><div><span>✅</span><b>' + Math.round(dogru) + '</b><small>Doğru cevap · ' + Math.round(soru) + ' soruda</small></div><div><span>🔥</span><b>' + hafta + '</b><small>Bu hafta çözülen</small></div></div>' +
      (bekleyen.length ? '<div class="osc-op-kutu vurgu"><h3>📚 Bekleyen ödevlerin <span>' + bekleyen.length + '</span></h3>' + bekleyen.map(function (o) { return '<a class="osc-op-odev" href="' + esc(o.link) + '"><div><b>' + esc(o.baslik) + '</b><small>' + esc(o.test) + (o.ogretmen ? ' · ' + esc(o.ogretmen) : '') + '</small></div>' + (o.son_tarih ? '<span class="son">Son: ' + esc(o.son_tarih) + '</span>' : '<span class="son canli">Canlı</span>') + '<span class="git">Başla →</span></a>'; }).join('') + '</div>' : '') +
      '<div class="osc-op-iki"><div class="osc-op-kutu"><h3>📈 Gelişimin</h3>' + (grafik || '<div class="osc-op-bos kucuk">En az iki test çözünce puan grafiğin burada görünür.</div>') + '</div>' +
      '<div class="osc-op-kutu"><h3>🧭 Konu karnem</h3>' + (konular.length ? '<p class="not">En zayıf konuların üstte. Bunlara öncelik ver.</p>' + konular.slice(0, 8).map(function (x) { var y = Math.round(x.oran * 100); return '<div class="osc-op-konu"><div><b>' + esc(x.k) + '</b><small>' + x.v.t + ' soru · ' + n2(x.v.d) + ' D · ' + n2(x.v.y) + ' Y · ' + x.v.b + ' B</small></div><div class="bar"><i style="width:' + y + '%;background:' + (y >= 70 ? '#10b981' : y >= 40 ? '#f59e0b' : '#ef4444') + '"></i></div><b class="y">%' + y + '</b></div>'; }).join('') : '<div class="osc-op-bos kucuk">Konu bilgisi olan testler çözdükçe konu karnen oluşur.</div>') + '</div></div>' +
      '<div class="osc-op-kutu"><h3>🗂 Çözdüğüm testler</h3>' + (cz.length ? '<div class="osc-op-tablo-kap"><table class="osc-op-tablo"><thead><tr><th class="sol">Test</th><th>Tarih</th><th>D</th><th>Y</th><th>B</th><th>Net</th><th>Puan</th><th></th></tr></thead><tbody>' + cz.map(function (x) { return '<tr><td class="sol"><b>' + esc(x.test) + '</b>' + (x.kaynak !== 'test' ? ' <span class="et">' + (x.kaynak === 'odev' ? 'Ödev' : 'Canlı sınıf') + '</span>' : '') + '</td><td>' + esc(x.tarih_m) + '</td><td class="d">' + n2(x.d) + '</td><td class="yy">' + n2(x.y) + '</td><td>' + x.b + '</td><td><b>' + n2(x.net) + '</b></td><td><span class="puan" style="--r:' + (x.puan >= 70 ? '#10b981' : x.puan >= 40 ? '#f59e0b' : '#ef4444') + '">%' + x.puan + '</span></td><td>' + (x.url ? '<a class="tekrar" href="' + esc(x.url) + '">' + (x.yanlis ? 'Tekrar çöz' : 'Aç') + '</a>' : '') + '</td></tr>'; }).join('') + '</tbody></table></div>' : '<div class="osc-op-bos"><b>Henüz test çözmedin.</b><br>Sitedeki testleri giriş yapmışken çözdüğünde sonuçların burada birikir.<br><a href="' + esc(C.home) + '">Testlere göz at →</a></div>') + '</div>' +
      (D.odev.filter(function (o) { return o.bitti || !o.acik; }).length ? '<div class="osc-op-kutu"><h3>✅ Tamamlanan ödev ve sınavlar</h3>' + D.odev.filter(function (o) { return o.bitti || !o.acik; }).map(function (o) { return '<div class="osc-op-odev bitti"><div><b>' + esc(o.baslik) + '</b><small>' + esc(o.test) + (o.ogretmen ? ' · ' + esc(o.ogretmen) : '') + ' · ' + esc(o.tarih_m) + '</small></div>' + (o.bitti ? '<span class="son">' + n2(o.net) + ' net · %' + o.puan + '</span>' : '<span class="son kacti">Teslim edilmedi</span>') + '</div>'; }).join('') + '</div>' : '');
  }
})();
