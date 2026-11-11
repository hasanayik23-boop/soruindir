(function () {
  'use strict';
  var C = window.OSC_PN, kok = document.getElementById('osc-pn');
  if (!C || !kok) return;
  var zam = null, sayac = null;
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function api(action, data) {
    var fd = new URLSearchParams(); fd.append('action', 'osc_sp_' + action); fd.append('nonce', C.nonce);
    Object.keys(data || {}).forEach(function (k) { if (data[k] !== undefined && data[k] !== null) fd.append(k, data[k]); });
    return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j || !j.success) throw new Error(j && typeof j.data === 'string' ? j.data : 'İşlem yapılamadı.');
      return j.data;
    });
  }
  function bildir(m, tur) {
    var t = document.createElement('div'); t.className = 'osc-pn-toast ' + (tur || ''); t.textContent = m; document.body.appendChild(t);
    setTimeout(function () { t.classList.add('git'); }, 2800); setTimeout(function () { t.remove(); }, 3300);
  }
  function durdur() { clearInterval(zam); clearInterval(sayac); zam = sayac = null; }
  function sure(sn) { sn = Math.max(0, sn | 0); var h = Math.floor(sn / 3600), m = Math.floor(sn % 3600 / 60), s = sn % 60, p = function (x) { return x < 10 ? '0' + x : x; }; return (h ? h + ':' + p(m) : m) + ':' + p(s); }
  function n2(x) { return Math.round(x * 100) / 100; }
  var DURUM = { bekliyor: ['Başlamadı', 'gri'], basladi: ['Devam ediyor', 'yesil'], bitti: ['Bitti', 'koyu'] };

  function cerceve(aktif, ic) {
    kok.innerHTML = '<div class="osc-pn-ust"><div class="osc-pn-marka"><a class="lg" href="' + esc(C.home) + '"><img src="' + C.logo + '" alt="soruindir.net"></a><div><b>Öğretmen Paneli</b><small>' + esc(C.site) + '</small></div></div>' +
      '<nav class="osc-pn-sek"><a href="#" class="' + (aktif === 'ot' ? 'on' : '') + '">Sınıf oturumlarım</a><a href="#testler" class="' + (aktif === 'test' ? 'on' : '') + '">Testlerim</a>' + (C.yz ? '<a href="#yazili" class="' + (aktif === 'yz' ? 'on' : '') + '">📄 Yazılı hazırla</a>' : (C.yazili ? '<a href="' + esc(C.yazili) + '">Yazılı hazırla</a>' : '')) + '</nav>' +
      '<div class="osc-pn-kim">' + esc(C.ad) + ' · <a href="' + esc(C.cikis) + '">Çıkış</a></div></div><div class="osc-pn-ic">' + ic + '</div>';
  }
  function yonlendir() {
    durdur();
    var h = (location.hash || '').replace(/^#/, '').split('/');
    if (h[0] === 'yeni') return vYeni(h[1] === 'odev' ? 'odev' : 'canli', +h[2] || 0);
    if (h[0] === 'oturum') return vOturum(+h[1]);
    if (h[0] === 'testler') return vTestler();
    if (h[0] === 'yazili' && C.yz) return vYazili();
    if (h[0] === 'test') return vEditor(h[1] === 'kopya' ? +h[2] : +h[1] || 0, h[1] === 'kopya');
    if (C.yeni) { var y = C.yeni; C.yeni = 0; location.hash = 'yeni/canli/' + y; return; }
    vOturumlar();
  }
  function vYazili() {
    cerceve('yz', '<div id="osc-yz" class="osc-yz gomulu"><div class="yz-yuk">Yükleniyor…</div></div>');
    window.OSC_YZ = C.yz;
    if (!document.getElementById('osc-yz-css')) { var l = document.createElement('link'); l.id = 'osc-yz-css'; l.rel = 'stylesheet'; l.href = C.yz_css; document.head.appendChild(l); var f = document.createElement('link'); f.rel = 'stylesheet'; f.href = 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap'; document.head.appendChild(f); }
    if (window.OSC_YZ_BASLAT) return window.OSC_YZ_BASLAT();
    if (document.getElementById('osc-yz-js')) return;
    var sc = document.createElement('script'); sc.id = 'osc-yz-js'; sc.src = C.yz_js; sc.onerror = function () { var e = document.getElementById('osc-yz'); if (e) e.innerHTML = '<div class="yz-yuk">Yazılı aracı yüklenemedi. Sayfayı yenileyin.</div>'; }; document.head.appendChild(sc);
  }
  window.addEventListener('hashchange', yonlendir);

  /* ======================= oturum listesi ======================= */
  function vOturumlar() {
    cerceve('ot', '<div class="osc-pn-selam"><div><small>Hoş geldiniz 👋</small><h2>' + esc(C.ad) + '</h2></div><div class="osc-pn-mini" id="osc-pn-mini"></div></div><div class="osc-pn-cta"><a href="#yeni/canli" class="osc-pn-cta-k canli"><span>🔴</span><div><b>Canlı sınıf sınavı başlat</b><small>Öğrenciler kodla katılır, hepsi aynı anda başlar, siz canlı izlersiniz.</small></div></a><a href="#yeni/odev" class="osc-pn-cta-k odev"><span>📚</span><div><b>Ödev linki oluştur</b><small>Son teslim tarihli link gönderin; sonuçlar burada toplanır.</small></div></a></div>' +
      '<h2 class="osc-pn-h2">Oturumlarım</h2><div id="osc-pn-liste" class="osc-pn-liste"><div class="osc-pn-bos">Yükleniyor…</div></div>');
    api('oturumlar').then(function (l) {
      var el = document.getElementById('osc-pn-liste'); if (!el) return;
      var mini = document.getElementById('osc-pn-mini'), kat = 0, bit = 0, aktif = 0; l.forEach(function (o) { kat += o.katilan; bit += o.biten; if (o.durum !== 'bitti') aktif++; });
      if (mini) mini.innerHTML = '<div><b>' + l.length + '</b><small>Oturum</small></div><div><b>' + aktif + '</b><small>Aktif</small></div><div><b>' + kat + '</b><small>Öğrenci katılımı</small></div><div><b>' + bit + '</b><small>Teslim</small></div>';
      if (!l.length) { el.innerHTML = '<div class="osc-pn-bos"><b>Henüz oturum yok.</b><br>Yukarıdan ilk canlı sınavınızı başlatın ya da bir ödev linki oluşturun.</div>'; return; }
      el.innerHTML = l.map(function (o) {
        var d = DURUM[o.durum] || ['', ''];
        return '<a class="osc-pn-ok" href="#oturum/' + o.id + '"><div class="ust"><span class="tur ' + o.tur + '">' + (o.tur === 'odev' ? 'Ödev' : 'Canlı') + '</span><span class="cip ' + d[1] + '">' + d[0] + '</span><span class="kod">' + esc(o.kod) + '</span></div><b>' + esc(o.baslik) + '</b><small>' + esc(o.test) + '</small>' +
          '<div class="alt"><span>👥 ' + o.katilan + ' katılım</span><span>✅ ' + o.biten + ' teslim</span>' + (o.ort !== null ? '<span>Ort. net ' + o.ort + '</span>' : '') + '</div><small class="tarih">' + esc(o.tarih) + (o.son_tarih ? ' · son teslim ' + esc(o.son_tarih) : '') + '</small></a>';
      }).join('');
    }).catch(function (e) { bildir(e.message, 'hata'); });
  }

  /* ======================= yeni oturum ======================= */
  function vYeni(tur, qid) {
    var secili = null;
    var yarin = new Date(Date.now() + 7 * 864e5); yarin.setHours(23, 59, 0, 0);
    var yerel = function (d) { var p = function (x) { return x < 10 ? '0' + x : x; }; return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes()); };
    cerceve('ot', '<a href="#" class="osc-pn-geri">‹ Oturumlarım</a><h2 class="osc-pn-h2">Yeni oturum</h2>' +
      '<div class="osc-pn-tur-sec"><button type="button" data-tur="canli" class="' + (tur === 'canli' ? 'on' : '') + '"><span>🔴</span><b>Canlı sınıf</b><small>Herkes aynı anda başlar</small></button><button type="button" data-tur="odev" class="' + (tur === 'odev' ? 'on' : '') + '"><span>📚</span><b>Ödev linki</b><small>Son teslim tarihine kadar</small></button></div>' +
      '<div class="osc-pn-kart"><h3>1. Test seçin</h3><div class="osc-pn-ara"><input id="osc-pn-q" placeholder="Test ara (ör. LGS Matematik, Üslü sayılar)…" autocomplete="off"></div><div id="osc-pn-sonuc" class="osc-pn-testler"></div><div id="osc-pn-secili"></div></div>' +
      '<div class="osc-pn-kart" id="osc-pn-ayar" style="display:none"><h3>2. Ayarlar</h3><div class="osc-pn-iz">' +
      '<label>Oturum adı<input id="osc-pn-baslik" placeholder="Örn: 8-A Matematik"></label>' +
      '<label>Süre (dakika)<input id="osc-pn-sure" type="number" min="0" max="600"><small>0 yazarsanız süre sınırı olmaz.</small></label>' +
      '<label class="odev-al">Son teslim<input id="osc-pn-son" type="datetime-local" value="' + yerel(yarin) + '"></label>' +
      '<label>Doğru cevaplar<select id="osc-pn-goster"><option value="bitince">Oturum bitince öğrenciye gösterilsin</option><option value="hemen">Öğrenci bitirir bitirmez gösterilsin</option></select><small>Kopya riskine karşı “oturum bitince” önerilir.</small></label>' +
      '</div><button type="button" class="osc-pn-btn ana buyuk" id="osc-pn-olustur">Oturumu oluştur →</button></div>');
    var ayarEl = document.getElementById('osc-pn-ayar');
    function turYaz() { kok.querySelectorAll('.osc-pn-tur-sec button').forEach(function (b) { b.classList.toggle('on', b.dataset.tur === tur); }); kok.querySelectorAll('.odev-al').forEach(function (e) { e.style.display = tur === 'odev' ? '' : 'none'; }); }
    kok.querySelectorAll('.osc-pn-tur-sec button').forEach(function (b) { b.onclick = function () { tur = b.dataset.tur; turYaz(); }; });
    turYaz();
    function sec(t) {
      secili = t;
      document.getElementById('osc-pn-sonuc').innerHTML = '';
      document.getElementById('osc-pn-secili').innerHTML = '<div class="osc-pn-sec-k"><div><b>' + esc(t.baslik) + '</b><small>' + t.soru + ' soru · ' + esc(t.tur) + ' (' + (t.kural ? t.kural + ' yanlış 1 doğru' : 'yanlış götürmez') + ')' + (t.kategori ? ' · ' + esc(t.kategori) : '') + '</small></div><button type="button" class="osc-pn-btn" id="osc-pn-degis">Değiştir</button></div>';
      document.getElementById('osc-pn-degis').onclick = function () { secili = null; document.getElementById('osc-pn-secili').innerHTML = ''; ayarEl.style.display = 'none'; document.getElementById('osc-pn-q').focus(); ara(''); };
      document.getElementById('osc-pn-sure').value = t.sure || 0;
      if (!document.getElementById('osc-pn-baslik').value) document.getElementById('osc-pn-baslik').value = t.baslik;
      ayarEl.style.display = '';
    }
    var araT = null, son = [];
    function ara(q) {
      api('ara', { q: q }).then(function (l) {
        son = l; var el = document.getElementById('osc-pn-sonuc'); if (!el || secili) return;
        el.innerHTML = l.length ? l.map(function (t, i) { return '<div role="button" tabindex="0" class="osc-pn-test" data-i="' + i + '"><b>' + esc(t.baslik) + '</b><small>' + t.soru + ' soru · ' + esc(t.tur) + (t.sure ? ' · ' + t.sure + ' dk' : '') + (t.benim ? ' · <em>' + (t.durum === 'publish' ? 'testim' : 'testim (onay bekliyor/taslak)') + '</em>' : '') + '</small></div>'; }).join('') : '<div class="osc-pn-bos">Sonuç yok.</div>';
        el.querySelectorAll('.osc-pn-test').forEach(function (b) { b.onclick = function () { sec(son[+b.dataset.i]); }; });
      }).catch(function (e) { bildir(e.message, 'hata'); });
    }
    document.getElementById('osc-pn-q').oninput = function (e) { clearTimeout(araT); var v = e.target.value; araT = setTimeout(function () { ara(v); }, 300); };
    if (qid) api('ara', { id: qid }).then(function (l) { if (l[0]) sec(l[0]); else ara(''); }).catch(function () { ara(''); }); else ara('');
    document.getElementById('osc-pn-olustur').onclick = function (e) {
      if (!secili) return bildir('Önce bir test seçin.', 'hata');
      e.target.disabled = true;
      api('olustur', { quiz_id: secili.id, tur: tur, baslik: document.getElementById('osc-pn-baslik').value, sure: document.getElementById('osc-pn-sure').value, son_tarih: tur === 'odev' ? document.getElementById('osc-pn-son').value : '', sonuc_goster: document.getElementById('osc-pn-goster').value })
        .then(function (r) { location.hash = 'oturum/' + r.id; })
        .catch(function (er) { e.target.disabled = false; bildir(er.message, 'hata'); });
    };
  }

  /* ======================= oturum detay (canlı izleme + rapor) ======================= */
  function vOturum(id) {
    var V = null, sekme = 'canli', siralama = 'net', qrCizildi = false, yansit = false;
    cerceve('ot', '<a href="#" class="osc-pn-geri">‹ Oturumlarım</a><div id="osc-pn-ot"><div class="osc-pn-bos">Yükleniyor…</div></div>');
    function yukle() {
      return api('izle', { id: id, rapor: 1 }).then(function (v) { V = v; ciz(); }).catch(function (e) { bildir(e.message, 'hata'); durdur(); });
    }
    function sayilar() {
      var k = V.katilimcilar, s = { top: k.length, bek: 0, coz: 0, bit: 0, net: 0 };
      k.forEach(function (x) { if (x.durum === 'bitti') { s.bit++; s.net += x.net; } else if (x.durum === 'cozuyor') s.coz++; else s.bek++; });
      s.ort = s.bit ? n2(s.net / s.bit) : '–'; return s;
    }
    function ciz() {
      var el = document.getElementById('osc-pn-ot'); if (!el) return;
      var s = sayilar(), d = DURUM[V.durum] || ['', ''];
      var kontrol = '';
      if (V.tur === 'canli') {
        if (V.durum === 'bekliyor') kontrol = '<button class="osc-pn-btn ana buyuk" data-k="baslat">▶ Sınavı başlat</button><small class="osc-pn-not">Öğrenciler katıldıkça aşağıda görünür. Hazır olunca başlatın; herkesin süresi aynı anda başlar.</small>';
        else if (V.durum === 'basladi') kontrol = '<div class="osc-pn-geri-say" id="osc-pn-gs">' + (V.sure ? sure(V.kalan) : 'Süresiz') + '</div><div class="osc-pn-btns">' + (V.sure ? '<button class="osc-pn-btn" data-k="sure_ekle" data-dk="5">+5 dk</button>' : '') + '<button class="osc-pn-btn kirmizi" data-k="bitir">■ Sınavı bitir</button></div>';
        else kontrol = '<div class="osc-pn-bitti">Oturum bitti</div><div class="osc-pn-btns"><button class="osc-pn-btn" data-k="sil">Oturumu sil</button></div>';
      } else {
        kontrol = (V.durum === 'bitti' ? '<div class="osc-pn-bitti">Ödev kapandı</div>' : '<div class="osc-pn-son">Son teslim<b>' + esc(V.son_tarih || 'yok') + '</b></div>') + '<div class="osc-pn-btns">' + (V.son_tarih ? '<button class="osc-pn-btn" data-k="sure_ekle" data-dk="1440">+1 gün</button>' : '') + (V.durum !== 'bitti' ? '<button class="osc-pn-btn kirmizi" data-k="bitir">Ödevi kapat</button>' : '<button class="osc-pn-btn" data-k="sil">Sil</button>') + '</div>';
      }
      if (!el.querySelector('.osc-pn-ot-ust')) {
        el.innerHTML = '<div class="osc-pn-ot-ust"><div class="osc-pn-kodk"><small>Katılım kodu</small><div class="kod">' + esc(V.kod) + '</div><div id="osc-pn-qr" class="qr"></div><div class="link"><input readonly value="' + esc(V.link) + '"><button class="osc-pn-btn" data-k="kopyala">Kopyala</button></div><div class="osc-pn-btns"><button class="osc-pn-btn" data-k="yansit">📺 Tahtaya yansıt</button><a class="osc-pn-btn" target="_blank" rel="noopener" href="https://api.whatsapp.com/send?text=' + encodeURIComponent(V.baslik + ' sınavına katılmak için: ' + V.link) + '">WhatsApp</a></div></div>' +
          '<div class="osc-pn-ot-bilgi"><div class="b"><span class="tur ' + V.tur + '">' + (V.tur === 'odev' ? 'Ödev' : 'Canlı sınıf') + '</span><span class="cip" id="osc-pn-dur"></span></div><h2>' + esc(V.baslik) + '</h2><small>' + esc(V.test) + ' · ' + V.soru + ' soru · ' + (V.kural ? V.kural + ' yanlış 1 doğruyu götürür' : 'yanlış götürmez') + '</small><div class="osc-pn-say" id="osc-pn-say"></div><div id="osc-pn-kontrol" class="osc-pn-kontrol"></div></div></div>' +
          '<nav class="osc-pn-alt-sek"><button data-s="canli">Canlı izleme</button><button data-s="ogr">Öğrenci sonuçları</button><button data-s="soru">Soru analizi</button><button data-s="konu">Konu analizi</button><span class="sag"><button class="osc-pn-btn" data-k="csv">⬇ Excel (CSV)</button><button class="osc-pn-btn" data-k="yazdir">🖨 Yazdır</button></span></nav><div id="osc-pn-govde"></div>';
        el.onclick = tikla;
      }
      document.getElementById('osc-pn-dur').className = 'cip ' + d[1]; document.getElementById('osc-pn-dur').textContent = d[0];
      document.getElementById('osc-pn-say').innerHTML = '<div><b>' + s.top + '</b><small>Katılan</small></div><div><b>' + s.coz + '</b><small>Çözüyor</small></div><div><b>' + s.bit + '</b><small>Teslim etti</small></div><div><b>' + s.ort + '</b><small>Ort. net</small></div>';
      var kk = document.getElementById('osc-pn-kontrol'); if (kk.dataset.h !== kontrol) { kk.innerHTML = kontrol; kk.dataset.h = kontrol; }
      if (!window.QRCode) { var qe = document.getElementById('osc-pn-qr'); if (qe) qe.style.display = 'none'; }
      if (!qrCizildi && window.QRCode) { try { new QRCode(document.getElementById('osc-pn-qr'), { text: V.link, width: 132, height: 132, correctLevel: QRCode.CorrectLevel.M }); qrCizildi = true; } catch (e) {} }
      kok.querySelectorAll('.osc-pn-alt-sek [data-s]').forEach(function (b) { b.classList.toggle('on', b.dataset.s === sekme); });
      var g = document.getElementById('osc-pn-govde');
      g.innerHTML = sekme === 'canli' ? canliHtml() : sekme === 'ogr' ? ogrHtml() : sekme === 'soru' ? soruHtml() : konuHtml();
      if (yansit) yansitCiz();
      clearInterval(sayac);
      if (V.durum === 'basladi' && V.tur === 'canli' && V.sure) { var bit = Date.now() + V.kalan * 1000; sayac = setInterval(function () { var e = document.getElementById('osc-pn-gs'); if (e) e.textContent = sure((bit - Date.now()) / 1000); }, 500); }
    }
    function canliHtml() {
      var k = V.katilimcilar;
      if (!k.length) return '<div class="osc-pn-bos big"><b>Henüz katılan yok.</b><br>Öğrenciler <b>' + esc(V.katil_link) + '</b> adresine girip <b>' + esc(V.kod) + '</b> kodunu yazarak ya da karekodu okutarak katılabilir.</div>';
      return '<div class="osc-pn-ogrler">' + k.map(function (x) {
        var yuzde = V.soru ? Math.round(x.ilerleme / V.soru * 100) : 0;
        var etiket = x.durum === 'bitti' ? '<span class="e bitti">%' + x.puan + ' · ' + n2(x.net) + ' net</span>' : x.durum === 'cozuyor' ? (x.cevrimici ? '<span class="e coz">Çözüyor</span>' : '<span class="e cevrimdisi">Bağlantı yok</span>') : '<span class="e bek">Bekliyor</span>';
        return '<div class="osc-pn-ogr ' + x.durum + '"><button class="sil" title="Çıkar" data-k="katilimci_sil" data-id="' + x.id + '">×</button><b>' + esc(x.ad) + '</b>' + etiket + '<div class="bar"><i style="width:' + (x.durum === 'bitti' ? 100 : yuzde) + '%"></i></div><small>' + x.ilerleme + ' / ' + V.soru + ' cevap</small></div>';
      }).join('') + '</div>';
    }
    function bitenler() { return V.katilimcilar.filter(function (x) { return x.durum === 'bitti' && x.durumlar; }); }
    function ogrHtml() {
      var l = bitenler().slice();
      if (!l.length) return '<div class="osc-pn-bos big">Teslim eden öğrenci olunca sonuçlar burada listelenir.</div>';
      l.sort(function (a, b) { return siralama === 'ad' ? a.ad.localeCompare(b.ad, 'tr') : (b.net - a.net) || (a.sure - b.sure); });
      return '<div class="osc-pn-tablo-kap"><table class="osc-pn-tablo"><thead><tr><th>#</th><th class="sol"><a href="#" data-k="sirala" data-v="ad">Öğrenci</a></th><th>D</th><th>Y</th><th>B</th><th><a href="#" data-k="sirala" data-v="net">Net ▾</a></th><th>Puan</th><th>Süre</th><th class="sol">Sorular</th></tr></thead><tbody>' + l.map(function (x, i) {
        return '<tr><td>' + (i + 1) + '</td><td class="sol"><b>' + esc(x.ad) + '</b></td><td class="d">' + n2(x.d) + '</td><td class="y">' + n2(x.y) + '</td><td>' + x.b + '</td><td><b>' + n2(x.net) + '</b></td><td>%' + x.puan + '</td><td>' + (x.sure ? sure(x.sure) : '–') + '</td><td class="sol"><div class="osc-pn-serit">' + x.durumlar.map(function (s, j) { return '<i class="' + s + '" title="' + (j + 1) + '. soru"></i>'; }).join('') + '</div></td></tr>';
      }).join('') + '</tbody></table></div>';
    }
    function soruIstat() {
      var l = bitenler();
      return (V.sorular || []).map(function (q, i) {
        var dag = {}, d = 0, b = 0;
        l.forEach(function (x) { var s = x.durumlar[i], c = x.cevaplar ? x.cevaplar[i] : null; if (s === 'd') d++; if (s === 'b') b++; else if (c && (q.tur === 'multiple' || q.tur === 'truefalse')) dag[c] = (dag[c] || 0) + 1; });
        return { i: i, q: q, d: d, b: b, n: l.length, oran: l.length ? d / l.length : 0, dag: dag };
      });
    }
    function soruHtml() {
      var st = soruIstat();
      if (!bitenler().length) return '<div class="osc-pn-bos big">Teslim eden öğrenci olunca her sorunun başarı oranı burada görünür.</div>';
      var sirali = st.slice().sort(function (a, b) { return a.oran - b.oran; });
      return '<p class="osc-pn-not">En zor sorular üstte. Kırmızı şık, yanlış cevaplarda en çok seçilen şıktır (yaygın yanılgı).</p><div class="osc-pn-sorular">' + sirali.map(function (x) {
        var y = Math.round(x.oran * 100), harf = function (k) { return x.q.tur === 'truefalse' ? (k === 'A' ? 'D' : 'Y') : k; };
        var enCok = null; Object.keys(x.dag).forEach(function (k) { if (k !== x.q.dogru && (!enCok || x.dag[k] > x.dag[enCok])) enCok = k; });
        var cipler = (x.q.secenekler || []).map(function (k) { return '<span class="sc ' + (k === x.q.dogru ? 'dogru' : (k === enCok ? 'yanilgi' : '')) + '">' + harf(k) + ' <b>' + (x.dag[k] || 0) + '</b></span>'; }).join('') + '<span class="sc bos">Boş <b>' + x.b + '</b></span>';
        return '<div class="osc-pn-soru"><div class="no">' + (x.i + 1) + '</div><div class="ic"><div class="ust">' + (x.q.konu ? '<span class="konu">' + esc(x.q.konu) + '</span>' : '') + '<span class="cevap">Doğru: <b>' + esc(x.q.tur === 'truefalse' ? (x.q.dogru === 'A' ? 'Doğru' : 'Yanlış') : x.q.dogru) + '</b></span></div><p>' + esc(x.q.kok || '(görsel soru)') + '</p><div class="cipler">' + cipler + '</div></div><div class="oran" style="--r:' + (y >= 70 ? '#10b981' : y >= 40 ? '#f59e0b' : '#ef4444') + '"><b>%' + y + '</b><small>' + x.d + '/' + x.n + ' doğru</small></div></div>';
      }).join('') + '</div>';
    }
    function konuHtml() {
      var st = soruIstat(), k = {};
      if (!bitenler().length) return '<div class="osc-pn-bos big">Teslim eden öğrenci olunca sınıfın konu bazında başarısı burada görünür.</div>';
      st.forEach(function (x) { var ad = x.q.konu || 'Konusu belirtilmemiş'; var o = k[ad] || (k[ad] = { soru: 0, d: 0, n: 0 }); o.soru++; o.d += x.d; o.n += x.n; });
      var l = Object.keys(k).map(function (ad) { return { ad: ad, o: k[ad], oran: k[ad].n ? k[ad].d / k[ad].n : 0 }; }).sort(function (a, b) { return a.oran - b.oran; });
      return '<p class="osc-pn-not">Sınıfın en zorlandığı konular üstte.</p><div class="osc-pn-konular">' + l.map(function (x) { var y = Math.round(x.oran * 100); return '<div class="osc-pn-konu"><div><b>' + esc(x.ad) + '</b><small>' + x.o.soru + ' soru</small></div><div class="bar"><i style="width:' + y + '%;background:' + (y >= 70 ? '#10b981' : y >= 40 ? '#f59e0b' : '#ef4444') + '"></i></div><b class="y">%' + y + '</b></div>'; }).join('') + '</div>';
    }
    function csv() {
      var l = bitenler(), sat = [['Sıra', 'Öğrenci', 'Doğru', 'Yanlış', 'Boş', 'Net', 'Puan', 'Süre'].concat((V.sorular || []).map(function (q, i) { return 'S' + (i + 1); }))];
      l.slice().sort(function (a, b) { return b.net - a.net; }).forEach(function (x, i) { sat.push([i + 1, x.ad, n2(x.d), n2(x.y), x.b, String(n2(x.net)).replace('.', ','), x.puan, x.sure ? sure(x.sure) : ''].concat(x.durumlar.map(function (s, j) { return (x.cevaplar && x.cevaplar[j]) ? x.cevaplar[j] + (s === 'd' ? ' ✓' : s === 'y' ? ' ✗' : '') : (s === 'b' ? '' : s); }))); });
      sat.push([]); sat.push(['Doğru cevap', '', '', '', '', '', '', ''].concat((V.sorular || []).map(function (q) { return q.dogru; })));
      var txt = '﻿' + sat.map(function (r) { return r.map(function (c) { c = String(c == null ? '' : c); return /[;"\n]/.test(c) ? '"' + c.replace(/"/g, '""') + '"' : c; }).join(';'); }).join('\r\n');
      var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([txt], { type: 'text/csv;charset=utf-8' })); a.download = (V.baslik || 'sonuclar').replace(/[^\wçğıöşüÇĞİÖŞÜ\- ]+/g, '') + '-sonuclar.csv'; document.body.appendChild(a); a.click(); setTimeout(function () { a.remove(); }, 500);
    }
    function yazdir() {
      var w = window.open('', '_blank'); if (!w) return bildir('Açılır pencere engellendi.', 'hata');
      var s = sayilar();
      w.document.write('<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>' + esc(V.baslik) + ' — Sonuç raporu</title><style>body{font-family:Arial,sans-serif;color:#111;margin:24px}h1{font-size:20px;margin:0}small{color:#555}table{border-collapse:collapse;width:100%;margin-top:12px;font-size:12px}th,td{border:1px solid #bbb;padding:4px 6px;text-align:center}th{background:#eef}td.sol,th.sol{text-align:left}.oz{display:flex;gap:20px;margin:10px 0;font-size:13px}.osc-pn-serit{display:flex;gap:1px}.osc-pn-serit i{width:8px;height:12px;display:inline-block}.d{background:#10b981}.y{background:#ef4444}.b{background:#ddd}.n{background:#999}h2{font-size:15px;margin:22px 0 4px}@media print{body{margin:10mm}}</style></head><body>' +
        '<h1>' + esc(V.baslik) + '</h1><small>' + esc(V.test) + ' · ' + V.soru + ' soru · ' + esc(new Date().toLocaleString('tr-TR')) + '</small><div class="oz"><span>Katılan: <b>' + s.top + '</b></span><span>Teslim: <b>' + s.bit + '</b></span><span>Ortalama net: <b>' + s.ort + '</b></span></div>' +
        '<h2>Öğrenci sonuçları</h2>' + ogrHtml().replace(/<a [^>]*>([^<]*)<\/a>/g, '$1').replace(/class="d"/g, '').replace(/class="y"/g, '') +
        '<h2>Konu analizi</h2>' + konuHtml().replace(/<div class="bar">.*?<\/div>/g, '') + '<p style="margin-top:30px;font-size:11px;color:#777">soruindir.net Öğretmen Paneli</p><script>window.onload=function(){window.print()}<\/script></body></html>');
      w.document.close();
    }
    function yansitCiz() {
      var z = document.getElementById('osc-pn-yansit');
      if (!z) { z = document.createElement('div'); z.id = 'osc-pn-yansit'; z.className = 'osc-pn-yansit'; document.body.appendChild(z); z.onclick = function (e) { if (e.target.dataset.k === 'kapat') { yansit = false; z.remove(); if (document.fullscreenElement) document.exitFullscreen().catch(function () {}); } }; }
      var s = sayilar();
      z.innerHTML = '<button class="kapat" data-k="kapat">× Kapat</button><div class="ic"><div class="sol"><small>1. Telefonundan şu adrese gir</small><b class="adr">' + esc(V.katil_link.replace(/^https?:\/\//, '')) + '</b><small>2. Kodu yaz</small><div class="kod">' + esc(V.kod) + '</div><div class="say">👥 ' + s.top + ' öğrenci katıldı' + (V.durum === 'basladi' ? ' · ' + s.bit + ' teslim' : '') + '</div></div><div class="sag"><div id="osc-pn-yqr"></div><small>ya da karekodu okut</small></div></div><div class="isimler">' + V.katilimcilar.map(function (x) { return '<span>' + esc(x.ad) + '</span>'; }).join('') + '</div>';
      if (window.QRCode) try { new QRCode(document.getElementById('osc-pn-yqr'), { text: V.link, width: 300, height: 300, correctLevel: QRCode.CorrectLevel.M }); } catch (e) {}
    }
    function tikla(e) {
      var b = e.target.closest('[data-k],[data-s]'); if (!b) return;
      if (b.dataset.s) { sekme = b.dataset.s; ciz(); return; }
      var k = b.dataset.k;
      if (k === 'kopyala') { var inp = b.parentNode.querySelector('input'); inp.select(); (navigator.clipboard ? navigator.clipboard.writeText(inp.value) : Promise.reject()).then(function () { bildir('Link kopyalandı', 'ok'); }).catch(function () { document.execCommand('copy'); bildir('Link kopyalandı', 'ok'); }); return; }
      if (k === 'yansit') { yansit = true; yansitCiz(); var z = document.getElementById('osc-pn-yansit'); if (z.requestFullscreen) z.requestFullscreen().catch(function () {}); return; }
      if (k === 'csv') { if (!bitenler().length) return bildir('Henüz teslim eden yok.', 'hata'); csv(); return; }
      if (k === 'yazdir') { if (!bitenler().length) return bildir('Henüz teslim eden yok.', 'hata'); yazdir(); return; }
      if (k === 'sirala') { e.preventDefault(); siralama = b.dataset.v; ciz(); return; }
      var onayMetni = { bitir: V.tur === 'odev' ? 'Ödev kapatılsın mı? Teslim etmeyenlerin kaydedilen cevapları değerlendirilir.' : 'Sınav herkes için bitirilsin mi? Öğrencilerin cevapları otomatik teslim edilir.', sil: 'Oturum ve tüm sonuçları kalıcı olarak silinsin mi?', katilimci_sil: 'Bu öğrenci oturumdan çıkarılsın mı?' }[k];
      if (onayMetni && !confirm(onayMetni)) return;
      b.disabled = true;
      api('komut', { id: id, islem: k, dk: b.dataset.dk, kid: b.dataset.id }).then(function () { if (k === 'sil') { location.hash = ''; return; } if (k === 'baslat') bildir('Sınav başladı!', 'ok'); return yukle(); }).catch(function (er) { b.disabled = false; bildir(er.message, 'hata'); });
    }
    yukle();
    zam = setInterval(function () { if (document.visibilityState === 'visible') yukle(); }, 4000);
  }

  /* ======================= testlerim ======================= */
  var DS = { publish: ['Yayında', 'yesil'], pending: ['Onay bekliyor', 'sari'], draft: ['Taslak', 'gri'] };
  function vTestler() {
    cerceve('test', '<div class="osc-pn-bas"><div><h2 class="osc-pn-h2">Testlerim</h2><p class="osc-pn-not">Hazırladığınız testler yönetici onayından sonra sitede yayınlanır. Onay beklerken de kendi sınıfınızda kullanabilirsiniz.</p></div><a class="osc-pn-btn ana" href="#test/yeni">+ Yeni test</a></div><div id="osc-pn-tl" class="osc-pn-liste"><div class="osc-pn-bos">Yükleniyor…</div></div>');
    api('testlerim').then(function (l) {
      var el = document.getElementById('osc-pn-tl'); if (!el) return;
      if (!l.length) { el.innerHTML = '<div class="osc-pn-bos"><b>Henüz testiniz yok.</b><br><a href="#test/yeni">İlk testinizi hazırlayın →</a></div>'; return; }
      el.innerHTML = l.map(function (t) {
        var d = t.red ? ['Düzeltme istendi', 'kirmizi'] : (DS[t.durum] || ['', '']);
        return '<div class="osc-pn-ok test"><div class="ust"><span class="cip ' + d[1] + '">' + d[0] + '</span><span class="tarih">' + esc(t.tarih) + '</span></div><b>' + esc(t.baslik) + '</b><small>' + t.soru + ' soru · ' + esc(t.tur) + (t.kategori ? ' · ' + esc(t.kategori) : '') + '</small>' + (t.red ? '<div class="red">Yönetici notu: ' + esc(t.red) + '</div>' : '') +
          '<div class="eylem">' + (t.durum !== 'publish' ? '<a class="osc-pn-btn" href="#test/' + t.id + '">Düzenle</a>' : '<a class="osc-pn-btn" href="#test/kopya/' + t.id + '">Kopyala</a>') + '<a class="osc-pn-btn" href="#yeni/canli/' + t.id + '">Sınıfta uygula</a>' + (t.link ? '<a class="osc-pn-btn" target="_blank" href="' + esc(t.link) + '">Sitede gör</a>' : '') + (t.durum !== 'publish' ? '<button class="osc-pn-btn kirmizi-y" data-sil="' + t.id + '">Sil</button>' : '') + '</div></div>';
      }).join('');
      el.querySelectorAll('[data-sil]').forEach(function (b) { b.onclick = function () { if (!confirm('Test silinsin mi?')) return; api('test_sil', { id: b.dataset.sil }).then(function () { bildir('Silindi', 'ok'); vTestler(); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
    }).catch(function (e) { bildir(e.message, 'hata'); });
  }

  /* ======================= test editörü ======================= */
  function vEditor(id, kopya) {
    var T = { id: 0, baslik: '', aciklama: '', kategori: 0, sinav_turu: '', sure: '', sorular: [] };
    var anahtar = 'osc_pn_taslak_' + (kopya ? 'k' + id : (id || 'yeni'));
    function yeniSoru(tip) { return { type: tip || 'multiple', konu: '', paragraph: '', root: '', options: { A: '', B: '', C: '', D: '', E: '' }, correct: '' }; }
    cerceve('test', '<a href="#testler" class="osc-pn-geri">‹ Testlerim</a><div id="osc-pn-ed"><div class="osc-pn-bos">Yükleniyor…</div></div>');
    var hazir = id ? api('test_al', { id: id }).then(function (t) { T = t; if (kopya) { T.id = 0; T.baslik = T.baslik + ' (kopya)'; } }) : Promise.resolve();
    hazir.then(function () {
      try { var ls = JSON.parse(localStorage.getItem(anahtar) || 'null'); if (ls && ls.sorular && ls.sorular.length && confirm('Bu test için kaydedilmemiş bir taslak bulundu. Kaldığınız yerden devam edilsin mi?')) T = Object.assign(T, ls); } catch (e) {}
      if (!T.sorular.length) T.sorular.push(yeniSoru());
      T.sorular.forEach(function (q) { q.options = q.options || {}; ['A', 'B', 'C', 'D', 'E'].forEach(function (k) { if (q.options[k] === undefined) q.options[k] = ''; }); });
      ciz();
    }).catch(function (e) { bildir(e.message, 'hata'); location.hash = 'testler'; });
    var kayT = null;
    function yerelKaydet() { clearTimeout(kayT); kayT = setTimeout(function () { try { localStorage.setItem(anahtar, JSON.stringify(T)); } catch (e) {} }, 600); }
    function turSecenek() { return '<option value="">Otomatik (başlıktan algıla)</option>' + C.turler.map(function (t) { return '<option value="' + t.k + '"' + (T.sinav_turu === t.k ? ' selected' : '') + '>' + esc(t.ad) + ' — ' + (t.kural ? t.kural + ' yanlış 1 doğru' : 'yanlış götürmez') + '</option>'; }).join(''); }
    function konular() { var l = []; T.sorular.forEach(function (q) { var k = (q.konu || '').trim(); if (k && l.indexOf(k) < 0) l.push(k); }); return l; }
    function araclar() { return '<div class="osc-pn-arac"><button type="button" data-a="b" title="Kalın"><b>K</b></button><button type="button" data-a="i" title="İtalik"><i>İ</i></button><button type="button" data-a="u" title="Altı çizili"><u>A</u></button><button type="button" data-a="sup" title="Üs">x²</button><button type="button" data-a="sub" title="Alt simge">x₂</button><button type="button" data-a="img" title="Görsel ekle">🖼 Görsel</button></div>'; }
    function soruHtml(q, i) {
      var h = '<div class="osc-pn-q" data-i="' + i + '"><div class="osc-pn-q-bas"><span class="no">' + (i + 1) + '</span><select data-f="type"><option value="multiple"' + (q.type === 'multiple' ? ' selected' : '') + '>Çoktan seçmeli</option><option value="truefalse"' + (q.type === 'truefalse' ? ' selected' : '') + '>Doğru / Yanlış</option><option value="gap"' + (q.type === 'gap' ? ' selected' : '') + '>Boşluk doldurma</option><option value="classic"' + (q.type === 'classic' ? ' selected' : '') + '>Açık uçlu (klasik)</option></select>' +
        '<input class="konu" data-f="konu" list="osc-pn-kl" value="' + esc(q.konu) + '" placeholder="Konu (ör. Üslü İfadeler)"><span class="bosluk"></span><button type="button" data-q="yukari" title="Yukarı">↑</button><button type="button" data-q="asagi" title="Aşağı">↓</button><button type="button" data-q="kopya" title="Çoğalt">⧉</button><button type="button" data-q="sil" class="sil" title="Sil">🗑</button></div>';
      h += '<div class="osc-pn-alan' + (q.paragraph ? '' : ' kapali') + '"><label>Paragraf / öncül <small>(isteğe bağlı)</small></label>' + araclar() + '<div class="osc-pn-ce" contenteditable="true" data-f="paragraph" data-ph="Okuma parçası, tablo açıklaması ya da öncüller…">' + (q.paragraph || '') + '</div></div>' + (q.paragraph ? '' : '<button type="button" class="osc-pn-link" data-q="para">+ Paragraf / öncül ekle</button>');
      h += '<div class="osc-pn-alan"><label>Soru kökü</label>' + araclar() + '<div class="osc-pn-ce kok" contenteditable="true" data-f="root" data-ph="Soruyu yazın. Görseli yapıştırabilir ya da 🖼 ile ekleyebilirsiniz.">' + (q.root || '') + '</div></div>';
      if (q.type === 'multiple') {
        h += '<div class="osc-pn-siklar"><label>Şıklar <small>— doğru şıkkın harfine tıklayın</small></label>' + ['A', 'B', 'C', 'D', 'E'].map(function (k) { return '<div class="osc-pn-sik' + (q.correct === k ? ' dogru' : '') + '"><button type="button" class="harf" data-dogru="' + k + '" title="Doğru cevap olarak işaretle">' + k + '</button><div class="osc-pn-ce tek" contenteditable="true" data-o="' + k + '" data-ph="' + (k === 'E' ? 'E şıkkı (LGS gibi 4 şıklı testlerde boş bırakın)' : k + ' şıkkı') + '">' + (q.options[k] || '') + '</div>' + (q.correct === k ? '<span class="tik">✓ Doğru</span>' : '') + '</div>'; }).join('') + '</div>';
      } else if (q.type === 'truefalse') {
        h += '<div class="osc-pn-dy"><label>Doğru cevap</label><button type="button" data-dogru="A" class="' + (q.correct === 'A' ? 'on' : '') + '">✓ Doğru</button><button type="button" data-dogru="B" class="' + (q.correct === 'B' ? 'on' : '') + '">✗ Yanlış</button></div>';
      } else {
        h += '<div class="osc-pn-alan"><label>' + (q.type === 'gap' ? 'Doğru cevap <small>(alternatifleri / ile ayırın: Ankara / Angora)</small>' : 'Model cevap <small>(öğrencinin cevabı bununla karşılaştırılır)</small>') + '</label><div class="osc-pn-ce' + (q.type === 'gap' ? ' tek' : '') + '" contenteditable="true" data-o="A" data-ph="Cevap">' + (q.options.A || '') + '</div></div>';
      }
      return h + '</div>';
    }
    function ciz() {
      var el = document.getElementById('osc-pn-ed'); if (!el) return;
      el.innerHTML = '<div class="osc-pn-kart"><h3>' + (T.id ? 'Testi düzenle' : 'Yeni test') + '</h3><div class="osc-pn-iz">' +
        '<label class="genis">Test başlığı<input data-t="baslik" value="' + esc(T.baslik) + '" placeholder="Örn: 8. Sınıf LGS Matematik – Üslü İfadeler Testi"></label>' +
        '<label>Kategori<select data-t="kategori"><option value="0">Seçin…</option>' + C.kategoriler.map(function (c) { return '<option value="' + c.id + '"' + (+T.kategori === c.id ? ' selected' : '') + '>' + esc(c.ad) + '</option>'; }).join('') + '</select></label>' +
        '<label>Sınav türü (net hesabı)<select data-t="sinav_turu">' + turSecenek() + '</select></label>' +
        '<label>Süre (dakika)<input data-t="sure" type="number" min="0" max="600" value="' + esc(T.sure) + '" placeholder="Boş: sınav türüne göre"></label>' +
        '<label class="genis">Kısa açıklama <small>(sitede testin üstünde görünür)</small><textarea data-t="aciklama" rows="2" placeholder="Bu test … konularını kapsar.">' + esc(T.aciklama) + '</textarea></label></div>' +
        '<div class="osc-pn-dosya-k"><div class="ik">📄</div><div class="ac"><b>Dosyadan soru al</b><small>PDF, Word (.docx), metin ya da soru fotoğrafı (PNG/JPG) yükleyin; sorular, şıklar, görseller ve cevap anahtarı otomatik okunur.</small><span id="osc-pn-dosya-durum"></span></div><button type="button" class="osc-pn-btn ana" data-e="dosya">Dosya seç</button><input type="file" id="osc-pn-ice" accept=".pdf,.docx,.txt,image/*" hidden></div>' +
        '<div class="osc-pn-hizli"><div><b>Cevap anahtarını hızlı gir</b><small>Çoktan seçmeli soruların doğru şıklarını sırayla yazın: <code>ABDCA BCD…</code></small></div><input id="osc-pn-anahtar" placeholder="Örn: ABCDA BCDAB" autocomplete="off"><button type="button" class="osc-pn-btn" data-e="anahtar">Uygula</button></div></div>' +
        '<datalist id="osc-pn-kl">' + konular().map(function (k) { return '<option value="' + esc(k) + '">'; }).join('') + '</datalist>' +
        '<div id="osc-pn-qs">' + T.sorular.map(soruHtml).join('') + '</div>' +
        '<div class="osc-pn-ekle"><span>Soru ekle:</span><button type="button" data-ekle="multiple">+ Çoktan seçmeli</button><button type="button" data-ekle="truefalse">+ Doğru/Yanlış</button><button type="button" data-ekle="gap">+ Boşluk doldurma</button><button type="button" data-ekle="classic">+ Açık uçlu</button></div>' +
        '<div class="osc-pn-alt-bar"><span id="osc-pn-ozet"></span><button type="button" class="osc-pn-btn" data-e="onizle">Önizle</button><button type="button" class="osc-pn-btn" data-e="taslak">Taslak kaydet</button><button type="button" class="osc-pn-btn ana" data-e="gonder">Onaya gönder →</button></div>' +
        '<input type="file" id="osc-pn-dosya" accept="image/*" hidden>';
      ozet();
      el.oninput = girdi; el.onchange = degisti; el.onclick = tik; el.onpaste = yapistir;
      el.onmousedown = function (e) { if (e.target.closest && e.target.closest('[data-a]')) e.preventDefault(); };
      el.addEventListener('blur', function (e) { if (e.target.classList && e.target.classList.contains('osc-pn-ce')) ozet(); }, true);
    }
    function ozet() {
      var eksik = 0; T.sorular.forEach(function (q) { if (!dogrula(q)) eksik++; });
      var e = document.getElementById('osc-pn-ozet'); if (e) e.innerHTML = '<b>' + T.sorular.length + '</b> soru' + (eksik ? ' · <span class="uyari">' + eksik + ' soruda eksik var</span>' : ' · <span class="tamam">hazır ✓</span>');
    }
    function bosHtml(h) { return !String(h || '').replace(/<br\s*\/?>/gi, '').replace(/&nbsp;/g, ' ').replace(/<(?!img)[^>]+>/gi, '').trim(); }
    function dogrula(q) {
      if (bosHtml(q.root) && bosHtml(q.paragraph)) return false;
      if (q.type === 'multiple') { var dolu = ['A', 'B', 'C', 'D', 'E'].filter(function (k) { return !bosHtml(q.options[k]); }); return dolu.length >= 2 && q.correct && !bosHtml(q.options[q.correct]); }
      if (q.type === 'truefalse') return q.correct === 'A' || q.correct === 'B';
      if (q.type === 'gap') return !bosHtml(q.options.A);
      return true;
    }
    function soruNo(el) { var q = el.closest('.osc-pn-q'); return q ? +q.dataset.i : -1; }
    function temizHtml(h) { return String(h || '').replace(/<(\/?)(div|p)[^>]*>/gi, function (m, s) { return s ? '' : '<br>'; }).replace(/^(<br>)+/, '').replace(/(<br>\s*)+$/, '').replace(/ style="[^"]*"/gi, function (m) { return /max-width|width/.test(m) ? m : ''; }).replace(/<span>([^<]*)<\/span>/gi, '$1'); }
    function girdi(e) {
      var t = e.target;
      if (t.dataset.t) { T[t.dataset.t] = t.value; yerelKaydet(); return; }
      var i = soruNo(t); if (i < 0) return; var q = T.sorular[i];
      if (t.dataset.f === 'konu') { q.konu = t.value; yerelKaydet(); return; }
      if (t.classList.contains('osc-pn-ce')) {
        var v = temizHtml(t.innerHTML);
        if (t.dataset.f) q[t.dataset.f] = v; else if (t.dataset.o) q.options[t.dataset.o] = v;
        yerelKaydet();
      }
    }
    function degisti(e) {
      var t = e.target;
      if (t.dataset.t) { T[t.dataset.t] = t.value; yerelKaydet(); return; }
      if (t.dataset.f === 'type') { var i = soruNo(t), q = T.sorular[i]; q.type = t.value; q.correct = ''; if (q.type === 'truefalse') q.options = { A: 'Doğru', B: 'Yanlış', C: '', D: '', E: '' }; else if (q.type !== 'multiple') q.options = { A: '', B: '', C: '', D: '', E: '' }; yeniden(i); }
      if (t.dataset.f === 'konu') { var dl = document.getElementById('osc-pn-kl'); if (dl) dl.innerHTML = konular().map(function (k) { return '<option value="' + esc(k) + '">'; }).join(''); }
    }
    function yeniden(odak) { var y = window.scrollY; document.getElementById('osc-pn-qs').innerHTML = T.sorular.map(soruHtml).join(''); window.scrollTo(0, y); ozet(); yerelKaydet(); if (odak !== undefined) { var e = document.querySelector('.osc-pn-q[data-i="' + odak + '"]'); if (e) e.classList.add('vurgu'); } }
    var hedefCe = null;
    function tik(e) {
      var b = e.target.closest('button,[data-q],[data-ekle],[data-e],[data-dogru],[data-a]'); if (!b) return;
      var i = soruNo(b), q = T.sorular[i];
      if (b.dataset.a) {
        e.preventDefault();
        var ce = b.closest('.osc-pn-alan, .osc-pn-q').querySelector('.osc-pn-ce');
        if (b.closest('.osc-pn-alan')) ce = b.closest('.osc-pn-alan').querySelector('.osc-pn-ce');
        if (b.dataset.a === 'img') { hedefCe = ce; document.getElementById('osc-pn-dosya').value = ''; document.getElementById('osc-pn-dosya').onchange = function (ev) { var f = ev.target.files[0]; if (f) gorselYukle(f, hedefCe); }; document.getElementById('osc-pn-dosya').click(); return; }
        ce.focus(); document.execCommand({ b: 'bold', i: 'italic', u: 'underline', sup: 'superscript', sub: 'subscript' }[b.dataset.a]); ce.dispatchEvent(new Event('input', { bubbles: true })); return;
      }
      if (b.dataset.dogru) { q.correct = b.dataset.dogru; yeniden(); return; }
      if (b.dataset.q === 'para') { var al = b.previousElementSibling; al.classList.remove('kapali'); b.remove(); al.querySelector('.osc-pn-ce').focus(); return; }
      if (b.dataset.q === 'sil') { if (T.sorular.length === 1) return bildir('Testte en az bir soru olmalı.', 'hata'); if (!confirm((i + 1) + '. soru silinsin mi?')) return; T.sorular.splice(i, 1); yeniden(); return; }
      if (b.dataset.q === 'kopya') { T.sorular.splice(i + 1, 0, JSON.parse(JSON.stringify(q))); yeniden(i + 1); return; }
      if (b.dataset.q === 'yukari' && i > 0) { T.sorular.splice(i - 1, 0, T.sorular.splice(i, 1)[0]); yeniden(i - 1); return; }
      if (b.dataset.q === 'asagi' && i < T.sorular.length - 1) { T.sorular.splice(i + 1, 0, T.sorular.splice(i, 1)[0]); yeniden(i + 1); return; }
      if (b.dataset.ekle) { var son = T.sorular[T.sorular.length - 1], s = yeniSoru(b.dataset.ekle); if (son && son.konu) s.konu = son.konu; if (s.type === 'truefalse') s.options = { A: 'Doğru', B: 'Yanlış', C: '', D: '', E: '' }; T.sorular.push(s); yeniden(T.sorular.length - 1); var yeni = document.querySelector('.osc-pn-q:last-child'); if (yeni) { yeni.scrollIntoView({ behavior: 'smooth', block: 'center' }); var k = yeni.querySelector('.osc-pn-ce.kok'); if (k) setTimeout(function () { k.focus(); }, 300); } return; }
      if (b.dataset.e === 'anahtar') {
        var harfler = (document.getElementById('osc-pn-anahtar').value || '').toUpperCase().replace(/[^A-E]/g, '').split(''), j = 0, n = 0;
        T.sorular.forEach(function (x) { if (x.type === 'multiple' && j < harfler.length) { x.correct = harfler[j++]; n++; } });
        yeniden(); bildir(n + ' sorunun doğru cevabı işlendi.', 'ok'); return;
      }
      if (b.dataset.e === 'onizle') { onizle(); return; }
      if (b.dataset.e === 'dosya') { var fi = document.getElementById('osc-pn-ice'); fi.value = ''; fi.onchange = function (ev) { var f = ev.target.files[0]; if (f) iceAktar(f, b); }; fi.click(); return; }
      if (b.dataset.e === 'taslak' || b.dataset.e === 'gonder') kaydet(b.dataset.e === 'gonder', b);
    }
    function yapistir(e) {
      var ce = e.target.closest && e.target.closest('.osc-pn-ce'); if (!ce) return;
      var cd = e.clipboardData || window.clipboardData; if (!cd) return;
      var dosyalar = cd.files && cd.files.length ? cd.files : null;
      if (!dosyalar && cd.items) for (var k = 0; k < cd.items.length; k++) if (cd.items[k].type.indexOf('image/') === 0) { dosyalar = [cd.items[k].getAsFile()]; break; }
      if (dosyalar && dosyalar[0]) { e.preventDefault(); gorselYukle(dosyalar[0], ce); return; }
      e.preventDefault();
      var metin = cd.getData('text/plain') || '';
      document.execCommand('insertHTML', false, esc(metin).replace(/\r?\n/g, '<br>'));
    }
    function gorselYukle(f, ce) {
      if (!/^image\//.test(f.type)) return bildir('Lütfen bir resim dosyası seçin.', 'hata');
      var fr = new FileReader();
      fr.onload = function () {
        var im = new Image();
        im.onload = function () {
          var k = Math.min(1, 1200 / Math.max(im.width, im.height)), cv = document.createElement('canvas'); cv.width = Math.round(im.width * k); cv.height = Math.round(im.height * k);
          var cx = cv.getContext('2d'); cx.fillStyle = '#fff'; cx.fillRect(0, 0, cv.width, cv.height); cx.drawImage(im, 0, 0, cv.width, cv.height);
          var png = cv.toDataURL('image/png'), jpg = cv.toDataURL('image/jpeg', 0.86), veri = png.length < jpg.length * 1.2 ? png : jpg;
          bildir('Görsel yükleniyor…');
          api('gorsel', { data: veri }).then(function (r) {
            ce.focus(); document.execCommand('insertHTML', false, '<img src="' + esc(r.url) + '" alt="">'); ce.dispatchEvent(new Event('input', { bubbles: true })); bildir('Görsel eklendi', 'ok');
          }).catch(function (er) { bildir(er.message, 'hata'); });
        };
        im.src = fr.result;
      };
      fr.readAsDataURL(f);
    }
    function durumYaz(m, tur) { var e = document.getElementById('osc-pn-dosya-durum'); if (e) { e.className = tur || ''; e.textContent = m; } }
    function scriptYukle(u) { return new Promise(function (ok, no) { if (document.querySelector('script[data-osc="' + u + '"]')) return ok(); var sc = document.createElement('script'); sc.src = u; sc.dataset.osc = u; sc.onload = ok; sc.onerror = function () { no(new Error('Kütüphane yüklenemedi: ' + u)); }; document.head.appendChild(sc); }); }
    function kutuphane() { return window.OSCImport && window.OSCExtract ? Promise.resolve() : scriptYukle(C.lib).then(function () { if (!window.OSCImport) { window.OSCImport = OSCImport; window.OSCExtract = OSCExtract; } }); }
    function pdfKutuphane() {
      if (window.__oscPdfLib) return Promise.resolve(window.__oscPdfLib);
      var base = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@6.2.108/legacy/build/';
      return import(base + 'pdf.min.mjs').then(function (m) { m.GlobalWorkerOptions.workerSrc = base + 'pdf.worker.min.mjs'; window.__oscPdfLib = m; return m; }).catch(function () {
        return scriptYukle('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js').then(function () { window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js'; window.__oscPdfLib = window.pdfjsLib; return window.pdfjsLib; });
      });
    }
    function ocr(kaynak) { return scriptYukle('https://cdn.jsdelivr.net/npm/tesseract.js@2.1.5/dist/tesseract.min.js').then(function () { return Tesseract.recognize(kaynak, 'tur'); }).then(function (r) { return r.data.text; }); }
    function iceAktar(file, btn) {
      var ad = (file.name || '').toLowerCase(), tip = (file.type || '').toLowerCase(), resimler = [], tablolar = [], yzSonuc = null;
      btn.disabled = true; durumYaz('Dosya okunuyor…');
      kutuphane().then(function () {
        if (/\.pdf$/.test(ad) || tip.indexOf('pdf') > -1) return pdfKutuphane().then(function (lib) { return file.arrayBuffer().then(function (buf) { return lib.getDocument({ data: new Uint8Array(buf) }).promise; }).then(function (pdf) { if (window.OSC_PDF_YZ && OSCExtract.pdfYZ) return OSCExtract.pdfYZ(pdf, { OPS: lib.OPS, onProgress: function (m) { durumYaz('🤖 ' + m); } }).then(function (r) { if (!r.questions.length) throw new Error('soru bulunamadı'); yzSonuc = r; return ''; }).catch(function (e) { durumYaz('Yapay zekâ okuyamadı (' + e.message + '), standart okuma deneniyor…'); return null; }).then(function (x) { return x === '' ? { yz: 1 } : OSCExtract.pdfToText(pdf, durumYaz, function (page) { var vp = page.getViewport({ scale: 2 }), cv = document.createElement('canvas'); cv.width = vp.width; cv.height = vp.height; return page.render({ canvasContext: cv.getContext('2d'), canvas: cv, viewport: vp }).promise.then(function () { return ocr(cv); }); }, { OPS: lib.OPS }); }); return OSCExtract.pdfToText(pdf, durumYaz, function (page) { var vp = page.getViewport({ scale: 2 }), cv = document.createElement('canvas'); cv.width = vp.width; cv.height = vp.height; return page.render({ canvasContext: cv.getContext('2d'), canvas: cv, viewport: vp }).promise.then(function () { return ocr(cv); }); }, { OPS: lib.OPS }); }).then(function (pr) { if (pr && pr.yz) return ''; resimler = pr.images || []; return pr.ocr ? OSCImport.reflowPlain(pr.text) : pr.text; }); });
        if (/\.docx$/.test(ad) || tip.indexOf('wordprocessingml') > -1) return scriptYukle('https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js').then(function () { return file.arrayBuffer(); }).then(function (buf) { return OSCExtract.docxToText(buf); }).then(function (dr) { resimler = dr.images || []; tablolar = dr.tables || []; return dr.text; });
        if (/\.doc$/.test(ad)) throw new Error('Eski .doc biçimi okunamıyor. Word\'de “Farklı Kaydet > .docx” yapıp yükleyin.');
        if (/\.txt$/.test(ad) || tip === 'text/plain') return file.text();
        if (tip.indexOf('image/') === 0 || /\.(png|jpe?g|webp|bmp|gif)$/.test(ad)) { var ocrYap = function () { durumYaz('Resimdeki yazılar okunuyor (OCR)… bu biraz sürebilir.'); return ocr(file).then(function (t) { return OSCImport.reflowPlain(t); }); }; if (window.OSC_PDF_YZ && OSCExtract.resimYZ) return OSCExtract.resimYZ(file, { onProgress: function () { durumYaz('🤖 Yapay zekâ resmi okuyor…'); } }).then(function (r) { if (!r.questions.length) throw new Error('soru yok'); yzSonuc = r; return ''; }).catch(ocrYap); return ocrYap(); }
        throw new Error('Desteklenmeyen dosya. PDF, DOCX, TXT ya da resim yükleyin.');
      }).then(function (metin) {
        var res = yzSonuc || OSCImport.parse(metin);
        if (!res.questions.length) throw new Error('Soru bulunamadı. Soru numaraları satır başında “1.” ya da “1)” biçiminde olmalı.');
        res.questions.forEach(function (q) { q.paragraph = OSCExtract.restoreImages(q.paragraph, resimler, tablolar); q.root = OSCExtract.restoreImages(q.root, resimler, tablolar); Object.keys(q.options).forEach(function (k) { q.options[k] = OSCExtract.restoreImages(q.options[k], resimler, tablolar); }); });
        var kul = [], tara = function (h) { String(h || '').replace(/src="(data:image\/[^"]+)"/g, function (m, u) { if (kul.indexOf(u) < 0) kul.push(u); return m; }); };
        res.questions.forEach(function (q) { tara(q.paragraph); tara(q.root); Object.keys(q.options).forEach(function (k) { tara(q.options[k]); }); });
        var harita = {}, sira = Promise.resolve();
        kul.forEach(function (u, i) { sira = sira.then(function () { durumYaz('Görseller yükleniyor: ' + (i + 1) + '/' + kul.length); return OSCExtract.compressImage(u, 1000).then(function (k) { return api('gorsel', { data: k }); }).then(function (r) { harita[u] = r.url; }).catch(function () { harita[u] = ''; }); }); });
        return sira.then(function () {
          var degis = function (h) { return String(h || '').replace(/<img[^>]*src="(data:image\/[^"]+)"[^>]*>/g, function (m, u) { return harita[u] ? m.replace(u, harita[u]) : ''; }); };
          var bos = T.sorular.length === 1 && bosHtml(T.sorular[0].root) && bosHtml(T.sorular[0].paragraph);
          if (bos) T.sorular = [];
          res.questions.forEach(function (q) { var o = q.options || {}; T.sorular.push({ type: q.type || 'multiple', konu: '', paragraph: degis(q.paragraph), root: degis(q.root), options: { A: degis(o.A || ''), B: degis(o.B || ''), C: degis(o.C || ''), D: degis(o.D || ''), E: degis(o.E || '') }, correct: q.correct || '' }); });
          yeniden(); btn.disabled = false;
          var r = res.report || {};
          durumYaz('✅ ' + res.questions.length + ' soru eklendi' + (r.keyed !== undefined ? ' · ' + r.keyed + ' sorunun cevabı okundu' : '') + (r.missing ? ' · ⚠ ' + r.missing + ' soruda doğru cevabı siz işaretleyin' : '') + '. Lütfen soruları kontrol edin.', 'ok');
        });
      }).catch(function (e) { btn.disabled = false; durumYaz('Hata: ' + e.message, 'hata'); });
    }
    function onizle() {
      var z = document.createElement('div'); z.className = 'osc-pn-modal';
      z.innerHTML = '<div class="osc-pn-modal-k"><div class="bas"><b>' + esc(T.baslik || 'Önizleme') + '</b><button type="button" data-x="1">× Kapat</button></div><div class="ic">' + T.sorular.map(function (q, i) {
        var o = q.type === 'truefalse' ? { A: 'Doğru', B: 'Yanlış' } : q.options;
        return '<div class="onq"><div class="n">' + (i + 1) + (q.konu ? ' <span>' + esc(q.konu) + '</span>' : '') + '</div>' + (q.paragraph ? '<div class="p">' + q.paragraph + '</div>' : '') + '<div class="r">' + (q.root || '') + '</div>' + (q.type === 'multiple' || q.type === 'truefalse' ? Object.keys(o).filter(function (k) { return !bosHtml(o[k]); }).map(function (k) { return '<div class="o' + (q.correct === k ? ' d' : '') + '"><b>' + k + ')</b> ' + o[k] + '</div>'; }).join('') : '<div class="o d">Cevap: ' + (q.options.A || '—') + '</div>') + (dogrula(q) ? '' : '<div class="eksik">⚠ Bu soruda eksik var</div>') + '</div>';
      }).join('') + '</div></div>';
      z.onclick = function (e) { if (e.target === z || e.target.dataset.x) z.remove(); };
      document.body.appendChild(z);
    }
    function kaydet(gonder, btn) {
      kok.querySelectorAll('.osc-pn-ce').forEach(function (ce) { ce.dispatchEvent(new Event('input', { bubbles: true })); });
      if ((T.baslik || '').trim().length < 5) { bildir('Test başlığını yazın (en az 5 karakter).', 'hata'); var b = kok.querySelector('[data-t=baslik]'); if (b) b.focus(); return; }
      if (gonder) {
        var hata = -1; T.sorular.some(function (q, i) { if (!dogrula(q)) { hata = i; return true; } return false; });
        if (hata >= 0) { bildir((hata + 1) + '. soruda eksik var (metin, şıklar ya da doğru cevap).', 'hata'); var q = document.querySelector('.osc-pn-q[data-i="' + hata + '"]'); if (q) { q.scrollIntoView({ behavior: 'smooth', block: 'center' }); q.classList.add('hata'); } return; }
        if (!+T.kategori) { bildir('Lütfen bir kategori seçin.', 'hata'); return; }
      }
      btn.disabled = true;
      api('test_kaydet', { id: T.id || 0, baslik: T.baslik, aciklama: T.aciklama, kategori: T.kategori, sinav_turu: T.sinav_turu, sure: T.sure, sorular: JSON.stringify(T.sorular), gonder: gonder ? 1 : '' }).then(function (r) {
        try { localStorage.removeItem(anahtar); } catch (e) {}
        T.id = r.id; anahtar = 'osc_pn_taslak_' + r.id;
        if (gonder) { kok.querySelector('#osc-pn-ed').innerHTML = '<div class="osc-pn-kart tamam"><div class="ik">🎉</div><h3>Testiniz onaya gönderildi</h3><p>Yönetici onayladığında sitede yayınlanacak ve size e-posta ile haber verilecek. Bu sürede testi kendi sınıfınızda kullanabilirsiniz.</p><div class="osc-pn-btns"><a class="osc-pn-btn ana" href="#yeni/canli/' + r.id + '">Sınıfta uygula</a><a class="osc-pn-btn" href="#testler">Testlerim</a><a class="osc-pn-btn" href="#test/yeni">Yeni test</a></div></div>'; window.scrollTo({ top: 0, behavior: 'smooth' }); }
        else { btn.disabled = false; bildir('Taslak kaydedildi', 'ok'); history.replaceState(null, '', '#test/' + r.id); }
      }).catch(function (er) { btn.disabled = false; bildir(er.message, 'hata'); });
    }
  }

  yonlendir();
})();
