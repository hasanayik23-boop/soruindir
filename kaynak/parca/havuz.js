(function () {
  'use strict';
  var C = window.OSC_HV, SNR = window.OSC_SNR || { tablolar: [] }, kok = document.getElementById('osc-hv');
  if (!C || !kok) return;
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function api(a, d) {
    var fd = new FormData(); fd.append('action', 'osc_hv_' + a); fd.append('nonce', C.nonce);
    Object.keys(d || {}).forEach(function (k) { var v = d[k]; if (Array.isArray(v)) v.forEach(function (x) { fd.append(k + '[]', x); }); else if (v !== undefined && v !== null) fd.append(k, v); });
    return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (!j || !j.success) throw new Error((j && j.data) || 'Hata'); return j.data; });
  }

  /* formüller ($…$) KaTeX ile */
  (function () { if (window.katex) return; var l = document.createElement('link'); l.rel = 'stylesheet'; l.href = 'https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/katex.min.css'; document.head.appendChild(l); var sc = document.createElement('script'); sc.src = 'https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/katex.min.js'; document.head.appendChild(sc); })();
  function matIsle(kap) {
    if (!window.katex || !kap) return;
    kap.querySelectorAll('.ic .p,.ic .r,.ic .o').forEach(function (e) {
      if (e.innerHTML.indexOf('$') < 0) return;
      e.innerHTML = e.innerHTML.replace(/\$([^$\n]{1,600}?)\$/g, function (_, t) { try { return window.katex.renderToString(t.replace(/<br\s*\/?>/g, ' ').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&'), { throwOnError: false, strict: false }); } catch (x) { return _; } });
    });
  }

  /* ================= MEB senaryo tabloları ve eşleştirme ================= */
  var DERS_ES = { 'T.C. İnkılap Tarihi ve Atatürkçülük': function (s) { return s <= 8 ? 'Sosyal Bilgiler' : 'Tarih'; } };
  function tablo(sinif, ders) {
    if (!sinif || !ders) return null;
    var d = DERS_ES[ders] ? DERS_ES[ders](sinif) : ders;
    var bir = null;
    for (var i = 0; i < SNR.tablolar.length; i++) { var t = SNR.tablolar[i]; if (t.sinif === +sinif && t.ders === d) { if (!bir) bir = { sinif: t.sinif, ders: t.ders, kademe: t.kademe, senaryolar: t.senaryolar, satirlar: t.satirlar.slice() }; else bir.satirlar = bir.satirlar.concat(t.satirlar); } }
    return bir;
  }
  function trl(s) { return String(s || '').replace(/I/g, 'ı').replace(/İ/g, 'i').toLowerCase(); }
  var DUR = {}; ('ve ile bir bu şu o da de ki mi mı mu mü için gibi olarak olan en çok daha ise ya veya ama fakat ancak çünkü kadar sonra önce diye hem yani her ne nasıl hangi göre üzere ayrıca bunun bunu buna onun onu ona şey şekilde aşağıdaki yukarıdaki verilen verilmiştir buna göre yazınız açıklayınız belirtiniz bulunuz hesaplayınız örnek örnekle soru cevap puan nedir neler nelerdir hangisi hangileri kaç cevaplayınız yazın açıklayın the and of to is are a an in on for with that this what which how students can will able be use their about').split(' ').forEach(function (w) { DUR[w] = 1; });
  var ES_AN = { 'üssü': 'üslü', 'üzeri': 'üslü', 'kuvveti': 'üslü', 'kuvvet': 'üslü', 'zar': 'olasılık', 'torba': 'olasılık', 'ihtimal': 'olasılık', 'ebob': 'ebob', 'kök': 'karekök', 'kökü': 'karekök', 'karekökü': 'karekök', 'grafik': 'grafiği', 'grafikte': 'grafiği' };
  function kelimeler(s) {
    var t = trl(String(s || '').replace(/<[^>]+>/g, ' ')).replace(/[^a-zçğıöşüâîû0-9]+/g, ' ').split(' '), o = [];
    t.forEach(function (w) { if (w.length < 3 || DUR[w] || /^\d+$/.test(w)) return; w = ES_AN[w] || w; o.push(w.length > 6 ? w.slice(0, 6) : w); });
    return o;
  }
  var dizinler = {};
  function dizin(t) {
    var key = t.ders + '|' + t.sinif; if (dizinler[key]) return dizinler[key];
    var df = {}, docs = t.satirlar.map(function (x) {
      var tf = {}; kelimeler(x.cikti + ' ' + x.konu + ' ' + x.konu + ' ' + x.unite).forEach(function (w) { tf[w] = (tf[w] || 0) + 1; });
      Object.keys(tf).forEach(function (w) { df[w] = (df[w] || 0) + 1; });
      return { x: x, tf: tf, n: Object.keys(tf).length || 1 };
    });
    var N = docs.length, idf = {}; Object.keys(df).forEach(function (w) { idf[w] = Math.log(1 + N / df[w]); });
    return (dizinler[key] = { docs: docs, idf: idf });
  }
  function kullanilan(x, yazili) { var s = x.sayi || {}; if (yazili) return !!(s[String(yazili)] && Object.keys(s[String(yazili)]).length); return !!(s['1'] && Object.keys(s['1']).length) || !!(s['2'] && Object.keys(s['2']).length); }
  /** Soruyu öğrenme çıktısına eşleştir: {kod, puan, guven} */
  /** dönemi bilinen soru yalnızca o dönemin senaryo tablosuyla eşleşir; o dönemin tablosu yoksa eşleşmez (beklemede kalır) */
  function tabloD(sinif, ders, donem) {
    donem = +donem || 0; var dn = SNR.donemler || [1];
    if (donem && dn.indexOf(donem) < 0) return null;
    if (!donem) return tablo(sinif, ders);
    var d = DERS_ES[ders] ? DERS_ES[ders](sinif) : ders, bir = null;
    SNR.tablolar.forEach(function (t) { if (t.sinif === +sinif && t.ders === d && (t.donem || 1) === donem) bir = t; });
    return bir;
  }
  function eslestir(soru, meta, tumu) {
    var t = tabloD(meta.sinif, meta.ders, meta.donem); if (!t) return null;
    var D = dizin(t), q = {}; kelimeler((soru.paragraph || '') + ' ' + (soru.root || '') + ' ' + Object.keys(soru.options || {}).map(function (k) { return soru.options[k]; }).join(' ')).forEach(function (w) { q[w] = 1; });
    var sonuc = D.docs.map(function (d) {
      var s = 0; Object.keys(q).forEach(function (w) { if (d.tf[w]) s += D.idf[w] || 0; });
      s = s / Math.sqrt(d.n);
      if (meta.donem === 1 && kullanilan(d.x, meta.yazili)) s *= 1.25; else if (!kullanilan(d.x)) s *= 0.7;
      return { x: d.x, s: s };
    });
    var tum = sonuc.map(function (r) { return r.s; });
    sonuc.sort(function (a, b) { return b.s - a.s; });
    if (!sonuc.length || sonuc[0].s <= 0) return { kod: '', puan: 0, guven: 0, tum: tum };
    var b = sonuc[0], i = sonuc[1] ? sonuc[1].s : 0;
    var guven = Math.min(1, (b.s / 1.6) * (i ? Math.min(1, (b.s - i) / b.s * 2.5 + 0.2) : 1));
    return { kod: b.x.kod || b.x.cikti.slice(0, 38), puan: Math.round(b.s * 100) / 100, guven: Math.round(guven * 100) / 100, x: b.x, tum: tum };
  }
  function metaTahmin(metin, ust) {
    var m = trl(metin), o = { sinif: 0, ders: '', donem: 0, yazili: 0 };
    var x = m.match(/(\d{1,2})\s*[\.\-_ ]?\s*sınıf/) || m.match(/(\d{1,2})[\.\-_ ]*sinif/); if (x) o.sinif = +x[1];
    x = m.match(/(\d)\s*[\.\-_ ]?\s*(dönem|donem)/); if (x) o.donem = +x[1];
    x = m.match(/(\d)\s*[\.\-_ ]?\s*(yazılı|yazili|sınav|sinav|ortak)/); if (x) o.yazili = +x[1];
    var dl = [['türk dili', 'Türk Dili ve Edebiyatı'], ['turk dili', 'Türk Dili ve Edebiyatı'], ['edebiyat', 'Türk Dili ve Edebiyatı'], ['türkçe', 'Türkçe'], ['turkce', 'Türkçe'], ['matematik', 'Matematik'], ['fen bilim', 'Fen Bilimleri'], ['fen-bilim', 'Fen Bilimleri'], ['sosyal', 'Sosyal Bilgiler'], ['inkılap', 'T.C. İnkılap Tarihi ve Atatürkçülük'], ['inkilap', 'T.C. İnkılap Tarihi ve Atatürkçülük'], ['ingilizce', 'İngilizce'], ['din kültür', 'Din Kültürü ve Ahlak Bilgisi'], ['din-kultur', 'Din Kültürü ve Ahlak Bilgisi'], ['din kultur', 'Din Kültürü ve Ahlak Bilgisi'], ['fizik', 'Fizik'], ['kimya', 'Kimya'], ['biyoloji', 'Biyoloji'], ['coğrafya', 'Coğrafya'], ['cografya', 'Coğrafya'], ['felsefe', 'Felsefe'], ['tarih', 'Tarih']];
    for (var i = 0; i < dl.length; i++) if (m.replace(/[_\-]+/g, ' ').indexOf(dl[i][0]) >= 0) { o.ders = dl[i][1]; break; }
    if (ust) Object.keys(ust).forEach(function (k) { if (ust[k]) o[k] = ust[k]; });
    return o;
  }

  /* ================= görünüm ================= */
  var sekme = 'ozet';
  document.querySelectorAll('.osc-hv-sek button').forEach(function (b) { b.onclick = function () { if (calisiyor && b.dataset.s !== 'aktar' && !confirm('Aktarım sürüyor. Bu sekmeye geçince aktarım durmaz ama ilerlemeyi göremezsiniz. Devam?')) return; sekme = b.dataset.s; document.querySelectorAll('.osc-hv-sek button').forEach(function (x) { x.classList.toggle('on', x === b); }); ciz(); }; });
  function ciz() { if (sekme === 'ozet') vOzet(); else if (sekme === 'aktar') vAktar(); else if (sekme === 'ekle') vEkle(); else if (sekme === 'uret') vUret(); else if (sekme === 'senaryo') vSenaryo(); else vHavuz(); }

  function vOzet() {
    kok.innerHTML = '<div class="osc-hv-k">Yükleniyor…</div>';
    api('ozet').then(function (d) {
      var g = {}, top = { n: 0, onayli: 0, inceleme: 0, eslesmedi: 0 };
      d.satirlar.forEach(function (r) { var k = r.sinif + '|' + r.ders, o = g[k] || (g[k] = { sinif: +r.sinif, ders: r.ders, n: 0, d: {}, c: {} }); o.n += +r.n; o.d[r.durum] = (o.d[r.durum] || 0) + +r.n; if (r.cikti_kod && r.durum === 'onayli') o.c[r.cikti_kod] = (o.c[r.cikti_kod] || 0) + +r.n; top.n += +r.n; top[r.durum] = (top[r.durum] || 0) + +r.n; });
      var goruldu = {}, satir = SNR.tablolar.slice().sort(function (a, b) { return a.sinif - b.sinif || a.ders.localeCompare(b.ders, 'tr') || (a.donem || 1) - (b.donem || 1); }).map(function (t) {
        var o = g[t.sinif + '|' + t.ders] || { n: 0, d: {}, c: {} }, ger = t.satirlar.filter(function (x) { return kullanilan(x); });
        var yeter = ger.filter(function (x) { return (o.c[x.kod || x.cikti.slice(0, 38)] || 0) >= 3; }).length, oran = ger.length ? Math.round(yeter / ger.length * 100) : 0;
        goruldu[t.sinif + '|' + t.ders] = 1;
        return '<tr><td>' + t.sinif + '. sınıf</td><td>' + esc(t.ders) + ' <small>' + (t.donem || 1) + '. dönem</small></td><td>' + o.n + '</td><td class="y">' + (o.d.onayli || 0) + '</td><td class="s">' + ((o.d.inceleme || 0) + (o.d.eslesmedi || 0)) + '</td><td><div class="bar"><i style="width:' + oran + '%;background:' + (oran >= 70 ? '#10b981' : oran >= 30 ? '#f59e0b' : '#ef4444') + '"></i></div> ' + yeter + '/' + ger.length + '</td><td><a href="#" data-g="' + t.sinif + '|' + esc(t.ders) + '">İncele</a></td></tr>';
      }).join('');
      var diger = Object.keys(g).filter(function (k) { return !goruldu[k]; }).map(function (k) { var o = g[k]; return '<tr class="soluk"><td>' + (o.sinif ? o.sinif + '. sınıf' : '?') + '</td><td>' + esc(o.ders || 'Belirlenemedi') + '</td><td>' + o.n + '</td><td class="y">' + (o.d.onayli || 0) + '</td><td class="s">' + ((o.d.inceleme || 0) + (o.d.eslesmedi || 0)) + '</td><td>senaryo tablosu yok</td><td><a href="#" data-g="' + o.sinif + '|' + esc(o.ders) + '">İncele</a></td></tr>'; }).join('');
      kok.innerHTML = '<div class="osc-hv-cta"><div><b>Sitedeki bütün yazılıları havuza aktarın</b><span>Aktarım <b>sunucuda, arka planda</b> çalışır: sayfayı kapatabilir, bilgisayarı kapatabilirsiniz. “Yazılı Soruları” kategorisi ve başlığında “yazılı” geçen tüm yazılardaki zip/Word dosyaları açılır, sorular senaryolara göre ayıklanır. Yeni eklediğiniz yazılı yazıları da otomatik aktarılır.</span></div><button class="button button-primary button-hero" id="hv-oto-basla">🚀 Tümünü tara ve aktar</button></div>' + '<div class="osc-hv-kartlar"><div><b>' + top.n + '</b><span>Havuzdaki soru</span></div><div><b>' + (top.onayli || 0) + '</b><span>Onaylı</span></div><div><b>' + ((top.inceleme || 0) + (top.eslesmedi || 0)) + '</b><span>İnceleme bekliyor</span></div><div><b>' + d.islenen + '</b><span>İşlenen dosya</span></div></div>' +
        '<div class="osc-hv-k"><h2>Senaryo kapsaması (2026-2027)</h2><p class="osc-hv-alt">“Kapsama”: senaryolarda soru çıkan öğrenme çıktılarından kaçında en az 3 onaylı soru var. Eksik kalan çıktılarda yazılı hazırlarken yapay zekâ devreye girer.</p><table class="widefat striped osc-hv-t"><thead><tr><th>Sınıf</th><th>Ders</th><th>Soru</th><th>Onaylı</th><th>İnceleme</th><th>Kapsama</th><th></th></tr></thead><tbody>' + satir + diger + '</tbody></table></div>' + '<div class="osc-hv-k osc-hv-cevap" id="hv-cevap"><h2>🔑 Cevap anahtarı</h2><p class="osc-hv-alt">Yükleniyor…</p></div>';
      cevapIzle();
      document.getElementById('hv-oto-basla').onclick = function () { C.arkaBasla = 1; var b = document.querySelector('.osc-hv-sek button[data-s=aktar]'); if (b) b.click(); };
      kok.querySelectorAll('[data-g]').forEach(function (a) { a.onclick = function (e) { e.preventDefault(); var p = a.dataset.g.split('|'); filtre = { sinif: +p[0] || '', ders: p[1], durum: '', cikti: '', ara: '' }; sekme = 'havuz'; document.querySelectorAll('.osc-hv-sek button').forEach(function (x) { x.classList.toggle('on', x.dataset.s === 'havuz'); }); ciz(); }; });
    }).catch(function (e) { kok.innerHTML = '<div class="notice notice-error"><p>' + esc(e.message) + '</p></div>'; });
  }

  /* ================= arşivden aktarım ================= */
  var kaynaklar = [], calisiyor = false, dur = false, log = [];
  function vAktar() {
    kok.innerHTML = '<div class="osc-hv-k osc-hv-arka"><h2>🚀 Arka planda aktar <span class="onerilen">önerilen</span></h2><p class="osc-hv-alt">Sunucu dosyaları kendisi açar; bu sayfayı kapatsanız da devam eder. Word (.docx), .txt ve bunları içeren zip dosyaları okunur. PDF’ler için aşağıdaki tarayıcı aktarımını kullanın.</p><div class="osc-hv-satir"><button class="button button-primary button-hero" id="hv-arka-basla">Tüm siteyi tara ve arka planda aktar</button><button class="button" id="hv-arka-dur" style="display:none">Durdur</button><label class="cb"><input type="checkbox" id="hv-arka-yeniden"> Daha önce aktarılanları da yeniden işle</label><label class="cb" title="Eski sürümle aktarılmış, birleşik/çöp içeren arşiv sorularını siler ve tüm arşivi yeni kurallarla baştan aktarır. Öğretmenlerin ve yapay zekânın yazdığı sorular silinmez."><input type="checkbox" id="hv-arka-sifirla"> <b>Arşiv sorularını silip temiz yeniden aktar</b> (önerilir)</label></div><div id="hv-arka-durum"></div></div>' +
      '<details class="osc-hv-k"><summary><b>Tarayıcıda aktar</b> (PDF dahil; sayfa açık kalmalı)</summary><h2 style="margin-top:12px">1. Kaynakları bul</h2><div class="osc-hv-satir"><label>Kategori adresi (slug) <input id="hv-kat" value="yazili-sorulari"></label><label class="cb"><input type="checkbox" id="hv-tum" checked> Başlığında “yazılı” geçen <b>tüm site</b> yazılarını da tara</label><label class="cb"><input type="checkbox" id="hv-yz" checked> Yazılımatik’teki 309 dosyayı da ekle</label><button class="button" id="hv-tara">Kaynakları tara</button><button class="button button-primary button-hero" id="hv-hepsi-aktar">🚀 Tümünü tara ve aktar</button></div><div id="hv-kliste"></div></div>' +
      '<div class="osc-hv-k" id="hv-ak" style="display:none"><h2>2. Ayıkla ve havuza aktar</h2><p class="osc-hv-alt">Dosyalar sırayla açılır (zip içindeki Word/PDF dahil), sorular ayıklanır, sınıf/ders dosya adından ve yazı başlığından belirlenir, her soru en yakın öğrenme çıktısıyla eşleştirilir. Emin olunan eşleşmeler <b>onaylı</b>, emin olunamayanlar <b>inceleme</b> olarak kaydedilir. Bu sayfayı açık bırakın; kapatırsanız kaldığı yerden devam eder.</p>' +
      '<div class="osc-hv-satir"><label class="cb"><input type="checkbox" id="hv-yeniden"> Daha önce işlenmiş dosyaları da yeniden işle</label><label>Güven eşiği <input type="number" id="hv-esik" value="0.45" step="0.05" min="0.1" max="0.95" style="width:70px"></label><button class="button button-primary button-hero" id="hv-basla">Aktarımı başlat</button><button class="button" id="hv-dur" disabled>Durdur</button></div>' +
      '<div class="osc-hv-ilerleme"><div class="bar"><i id="hv-bar"></i></div><span id="hv-durum">Hazır</span></div><div class="osc-hv-log" id="hv-log"></div></div></details>';
    document.getElementById('hv-arka-basla').onclick = function () { arkaBasla(this); };
    document.getElementById('hv-arka-dur').onclick = function () { if (!confirm('Kuyruktaki dosyalar iptal edilsin mi? (Şimdiye kadar aktarılanlar havuzda kalır.)')) return; api('arka_dur', {}).then(arkaCiz); };
    arkaIzle();
    if (C.arkaBasla) { C.arkaBasla = 0; setTimeout(function () { document.getElementById('hv-arka-basla').click(); }, 200); }
    document.getElementById('hv-tara').onclick = function () { tara().catch(function () {}); };
    document.getElementById('hv-hepsi-aktar').onclick = function () { if (calisiyor) return; this.disabled = true; var bu = this; tara().then(function () { bu.disabled = false; if (!kaynaklar.length) return; var bekleyen = kaynaklar.filter(function (k) { return !k.islendi; }).length; if (!bekleyen) { logEkle('✓ Bütün dosyalar zaten aktarılmış. Yeni dosya yok.', 'ok'); return; } baslat(); }).catch(function () { bu.disabled = false; }); };
    if (C.oto && !calisiyor) { C.oto = 0; setTimeout(function () { var b = document.getElementById('hv-hepsi-aktar'); if (b) b.click(); }, 300); }
    document.getElementById('hv-basla').onclick = baslat;
    document.getElementById('hv-dur').onclick = function () { dur = true; this.disabled = true; durumYaz('Durduruluyor… (sıradaki dosyadan önce duracak)'); };
    if (kaynaklar.length) listeCiz();
    if (calisiyor) { document.getElementById('hv-basla').disabled = true; document.getElementById('hv-dur').disabled = false; }
    logCiz();
  }
  function tara() {
    var b = document.getElementById('hv-tara'); b.disabled = true; b.textContent = 'Taranıyor…';
    return api('kaynaklar', { kategori: document.getElementById('hv-kat').value, tum: document.getElementById('hv-tum').checked ? 1 : '', yazilimatik: document.getElementById('hv-yz').checked ? 1 : '' }).then(function (d) {
      kaynaklar = d.kaynaklar; b.disabled = false; b.textContent = 'Kaynakları tara';
      if (!d.kategori_var) logEkle('⚠ Bu adreste kategori bulunamadı.', 'hata');
      logEkle('🔎 ' + d.yazi + ' yazı tarandı, ' + d.kaynaklar.length + ' dosya bulundu.');
      listeCiz();
    }).catch(function (e) { b.disabled = false; b.textContent = 'Kaynakları tara'; alert(e.message); throw e; });
  }
  function listeCiz() {
    var t = { zip: 0, docx: 0, pdf: 0, doc: 0, diger: 0 }, isl = 0;
    kaynaklar.forEach(function (k) { var u = k.url.toLowerCase().split('?')[0], e = u.slice(u.lastIndexOf('.') + 1); t[t[e] !== undefined ? e : 'diger']++; if (k.islendi) isl++; });
    document.getElementById('hv-kliste').innerHTML = '<p><b>' + kaynaklar.length + '</b> dosya bulundu: ' + t.zip + ' zip, ' + t.docx + ' Word (.docx), ' + t.pdf + ' PDF' + (t.doc ? ', ' + t.doc + ' eski Word (.doc — okunamaz)' : '') + '. ' + (isl ? '<b>' + isl + '</b> tanesi daha önce işlendi.' : '') + '</p>';
    document.getElementById('hv-ak').style.display = kaynaklar.length ? '' : 'none';
  }
  /* ---- yapay zekâ ile cevap anahtarı ---- */
  var cevapZ = null;
  function cevapIzle() {
    clearTimeout(cevapZ);
    var tur = function () { if (sekme !== 'ozet') return; api('cevap_durum', {}).then(function (d) { cevapCiz(d); if (d.calisiyor) cevapZ = setTimeout(tur, 4000); }).catch(function () {}); };
    tur();
  }
  function cevapCiz(d) {
    var el = document.getElementById('hv-cevap'); if (!el) return;
    var top = Math.max(1, d.toplam || 0), y = d.calisiyor ? Math.min(100, Math.round((d.yapilan || 0) / top * 100)) : 100;
    var tahmin = Math.max(0, d.kalan || 0), maliyet = (tahmin * 0.00007).toFixed(2);
    el.innerHTML = '<h2>🔑 Cevap anahtarı</h2>' +
      '<p class="osc-hv-alt"><b>' + (d.kalan || 0) + '</b> soruda cevap anahtarı ya da zorluk bilgisi eksik. Yapay zekâ bu soruları arka planda çözer: cevabı yoksa cevabını yazar, her soruya kolay/orta/zor etiketi verir (Yazılı Hazırla’daki zorluk seçimi bu etikete göre soru seçer). Resimli sorularda resimler de yapay zekâya gösterilir; emin olunmayan sorulara cevap uydurulmaz. Soru aktarımı bittiğinde bu iş kendiliğinden başlar. Eklenen cevaplar <b>🤖 yapay zekâ</b> etiketiyle görünür, “Havuzu incele”de kontrol edip düzeltebilirsiniz. Sayfayı kapatabilirsiniz.' + (tahmin ? ' <span class="tahmin">Tahmini maliyet: yaklaşık $' + maliyet + ' (Claude Haiku ya da ücretli Gemini Flash-Lite). Gemini ücretsiz katmanda ücretsizdir; günlük kotaya takılırsa ertesi gün kaldığı yerden devam eder.</span>' : '') + '</p>' +
      '<div class="osc-hv-satir">' + (d.calisiyor ? '<button class="button" id="hv-cevap-dur">Durdur</button>' : '<button class="button button-primary button-hero" id="hv-cevap-basla"' + (d.kalan ? '' : ' disabled') + '>🤖 Cevapları ve zorlukları yapay zekâyla tamamla</button>') + '</div>' +
      (d.basladi ? '<div class="osc-hv-ilerleme"><div class="bar"><i style="width:' + y + '%"></i></div><span>' + (d.calisiyor ? '⏳ ' + (d.yapilan || 0) + ' / ' + top + ' soru' : (d.bitti ? '✓ Bitti' : 'Durdu')) + ' · <b>' + (d.cevap || 0) + '</b> cevap eklendi' + (d.atlanan ? ', ' + d.atlanan + ' atlandı' : '') + '</span></div><div class="osc-hv-log">' + (d.log || []).slice(0, 12).map(function (l) { return '<div class="' + (l.t || '') + '">' + esc(l.m) + '</div>'; }).join('') + '</div>' : '');
    var b = document.getElementById('hv-cevap-basla'); if (b) b.onclick = function () { b.disabled = true; b.textContent = 'Başlatılıyor…'; api('cevap_basla', {}).then(function (x) { cevapCiz(x); cevapIzle(); }).catch(function (e) { b.disabled = false; b.textContent = '🤖 Cevapları ve zorlukları yapay zekâyla tamamla'; alert(e.message); }); };
    var s2 = document.getElementById('hv-cevap-dur'); if (s2) s2.onclick = function () { api('cevap_dur', {}).then(cevapCiz); };
  }
  /* ---- sunucu (arka plan) aktarımı ---- */
  var arkaZ = null, arkaSon = null;
  function arkaBasla(b) {
    if (document.getElementById('hv-arka-sifirla').checked && !confirm('Arşivden aktarılmış tüm sorular silinip arşiv baştan aktarılacak. Onayladığınız arşiv soruları ve yapay zekânın eklediği cevaplar da silinir (yeni aktarımda yeniden hazırlanır). Devam edilsin mi?')) return;
    b.disabled = true; b.textContent = 'Kaynaklar taranıyor…';
    api('arka_basla', { kategori: 'yazili-sorulari', tum: 1, yazilimatik: 1, yeniden: document.getElementById('hv-arka-yeniden').checked ? 1 : '', sifirla: document.getElementById('hv-arka-sifirla').checked ? 1 : '' }).then(function (d) { b.disabled = false; b.textContent = 'Tüm siteyi tara ve arka planda aktar'; arkaCiz(d); arkaIzle(); }).catch(function (e) { b.disabled = false; b.textContent = 'Tüm siteyi tara ve arka planda aktar'; alert(e.message); });
  }
  function arkaIzle() {
    clearTimeout(arkaZ);
    var tur = function () { if (sekme !== 'aktar' && sekme !== 'ozet') return; api('arka_durum', {}).then(function (d) { arkaCiz(d); if (d.calisiyor) arkaZ = setTimeout(tur, 3000); }).catch(function () { arkaZ = setTimeout(tur, 8000); }); };
    tur();
  }
  function arkaCiz(d) {
    arkaSon = d; var el = document.getElementById('hv-arka-durum'); if (!el || !d || !d.basladi) return;
    var top = Math.max(1, (d.islenen || 0) + (d.kalan || 0)), yuzde = Math.round((d.islenen || 0) / top * 100);
    var dur = document.getElementById('hv-arka-dur'); if (dur) dur.style.display = d.calisiyor ? '' : 'none';
    var bas = document.getElementById('hv-arka-basla'); if (bas) bas.disabled = !!d.calisiyor;
    el.innerHTML = '<div class="osc-hv-ilerleme"><div class="bar"><i style="width:' + (d.calisiyor ? yuzde : 100) + '%"></i></div><span>' + (d.calisiyor ? '⏳ Çalışıyor: ' + (d.islenen || 0) + ' / ' + top + ' dosya · kalan ' + d.kalan : (d.bitti ? '✓ Bitti' : 'Bekliyor')) + ' · <b>' + (d.eklenen || 0) + '</b> yeni soru, <b>' + (d.onayli || 0) + '</b> onaylı' + (d.hata ? ', ' + d.hata + ' dosya okunamadı' : '') + '</span></div>' +
      (d.calisiyor ? '<p class="osc-hv-alt">Bu sayfayı kapatabilirsiniz. Aktarım sunucuda devam eder; geri geldiğinizde ilerlemeyi burada görürsünüz.</p>' : '') +
      '<div class="osc-hv-log">' + (d.log || []).map(function (l) { return '<div class="' + (l.t || '') + '">' + esc(l.m) + '</div>'; }).join('') + '</div>';
  }
  function durumYaz(m) { var e = document.getElementById('hv-durum'); if (e) e.textContent = m; }
  function logEkle(m, tur) { log.unshift({ m: m, t: tur || '' }); if (log.length > 400) log.length = 400; logCiz(); }
  function logCiz() { var e = document.getElementById('hv-log'); if (e) e.innerHTML = log.map(function (l) { return '<div class="' + l.t + '">' + esc(l.m) + '</div>'; }).join(''); }
  function scriptYukle(u) { return new Promise(function (ok, no) { if (document.querySelector('script[data-hv="' + u + '"]')) return ok(); var s = document.createElement('script'); s.src = u; s.dataset.hv = u; s.onload = ok; s.onerror = function () { no(new Error('Kütüphane yüklenemedi: ' + u)); }; document.head.appendChild(s); }); }
  var pdfLib = null;
  function kutuphaneler() {
    var p = [];
    if (!window.OSCImport) p.push(scriptYukle(C.lib));
    if (!window.JSZip) p.push(scriptYukle('https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js'));
    return Promise.all(p);
  }
  var pdfSoz = null;
  function pdfYukle() {
    if (pdfLib) return Promise.resolve(pdfLib);
    if (pdfSoz) return pdfSoz;
    var base = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@6.2.108/legacy/build/';
    pdfSoz = import(base + 'pdf.min.mjs').then(function (m) { m.GlobalWorkerOptions.workerSrc = base + 'pdf.worker.min.mjs'; pdfLib = m; return m; }).catch(function () {
      return scriptYukle('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js').then(function () { window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js'; pdfLib = window.pdfjsLib; return pdfLib; });
    }).catch(function (e) { pdfSoz = null; throw new Error('PDF okuyucu yüklenemedi, PDF atlandı'); });
    return pdfSoz;
  }
  function indir(url) {
    return fetch(C.ajax + '?action=osc_hv_dosya&nonce=' + encodeURIComponent(C.nonce) + '&url=' + encodeURIComponent(url), { credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw new Error('dosya indirilemedi (' + r.status + ')'); return r.arrayBuffer(); });
  }
  function belgeMetni(ad, buf) {
    var a = ad.toLowerCase();
    if (/\.docx$/.test(a)) return OSCExtract.docxToText(buf, { latex: true }).then(function (r) { return { text: r.text, images: r.images || [], tables: r.tables || [] }; });
    if (/\.pdf$/.test(a)) return pdfYukle().then(function () { return pdfLib.getDocument({ data: new Uint8Array(buf) }).promise.then(function (pdf) { return OSCExtract.pdfToText(pdf, null, function () { return Promise.resolve(''); }, { OPS: pdfLib.OPS }); }).then(function (r) { return { text: r.text, images: r.images || [], tables: [] }; }); });
    if (/\.txt$/.test(a)) return Promise.resolve({ text: new TextDecoder('utf-8').decode(buf), images: [], tables: [] });
    return Promise.reject(new Error(/\.doc$/.test(a) ? 'eski .doc biçimi okunamıyor' : 'desteklenmeyen dosya türü'));
  }
  function gorselYukle(uri) {
    return OSCExtract.compressImage(uri, 1000).then(function (k) {
      var fd = new FormData(); fd.append('action', 'osc_upload_image'); fd.append('nonce', C.img_nonce); fd.append('post_id', '0'); fd.append('data', k);
      return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { return j && j.success ? j.data.url : ''; });
    }).catch(function () { return ''; });
  }
  /** Bir belgeyi ayıkla → havuz kayıtları */

  /* ================= yazılı kâğıdı ayrıştırıcı (açık uçlu + test) ================= */
  function yaziliAyristir(metin) {
    var satir = String(metin || '').replace(/\r/g, '').split('\n').map(function (x) { return x.replace(/ /g, ' ').replace(/[ \t]+/g, ' ').trim(); });
    var anahtarBas = -1;
    for (var i = 0; i < satir.length; i++) if (/^(cevap\s*anahtar|cevaplar\b|yanıt\s*anahtar|cevap\s*kağıdı)/i.test(satir[i])) { anahtarBas = i; break; }
    var govde = anahtarBas >= 0 ? satir.slice(0, anahtarBas) : satir, anahtar = anahtarBas >= 0 ? satir.slice(anahtarBas).join('\n') : '';
    var NO = /^(?:soru\s*)?(\d{1,2})\s*[\)\.\-–:]\s*(?=\S)(.*)$|^(?:soru\s*)(\d{1,2})(?:\s*[\)\.\-–:]?\s+(.*)|\s*)$|^S\s*(\d{1,2})\s*[\)\.\-:]\s*(.*)$/i;
    var KOD = /\[\s*([A-ZÇĞİÖŞÜ]{1,5}\.\d+(?:\.\d+)+)\.?[^\]]*\]/;
    var sorular = [], cur = null, bek = 1, yeniKagit = false;
    govde.forEach(function (l) {
      l = l.replace(/\*\*/g, '');
      if (!l || /^[.…_\s]+$/.test(l)) { if (cur && !l) cur.satirlar.push(''); return; }
      var yk = l.match(/^\s*\[\s*([A-ZÇĞİÖŞÜ]{1,5}\.\d+(?:\.\d+)+)\.?[^\]]*\]\s*$/); if (yk) { if (cur && !cur.kod) cur.kod = yk[1]; return; }
      var sm = l.match(/^senaryo\s*\d+\s*[\/\-–]\s*soru\s*(\d{1,2})\b(.*)$/i);
      if (sm) { var kd2 = ''; var g2 = sm[2].replace(KOD, function (_, k) { kd2 = k; return ''; }).replace(/^[\s&\d]*(odak)?\s*/i, '').trim(); cur = { no: +sm[1], kod: kd2, satirlar: g2 ? [g2] : [] }; sorular.push(cur); bek = +sm[1] + 1; yeniKagit = false; return; }
      if (/^(başarılar|basarilar|kolay gelsin|bol şans|süre\s*:?\s*\d+|her soru\s*:?\s*\d+\s*puan|.{0,40}öğretmeni?\s*$)/i.test(l) && !/[?]/.test(l)) return;
      if (!/[?]/.test(l) && (/ad[ıi]\s*soyad[ıi]|sınıf[ıi]?\s*\/?\s*no\b|okul\s*no|^yönerge\s*:|^\d{1,2}\s*\.?\s*sınıf\b.{0,40}$|dönem.{0,25}(yazılı|ortak|sınav)|^senaryo\s*\d+\b|milli eğitim bakanlığı|millî eğitim|aldığı puan|^\d+\s*soru\s*\/|^çözümleri$|^uygun\)$|^düzenlenmiştir\)$|sayfa\s*\d+\s*(\/\s*\d+)?\s*$|^\s*\d{4}\s*[-–]\s*\d{4}|^t\.?\s*c\.?\s*$|^puan\s*:|^not\s*:|cevaplar soruların altında/i.test(l))) { if (/dönem.{0,25}(yazılı|ortak|sınav)|senaryo\s*\d+/i.test(l) && cur) { bek = 1; yeniKagit = true; } return; }
      var cvm = l.match(/^(?:çözüm\s*(?:ve|\/|-)\s*cevap|cevap\s*(?:ve|\/|-)\s*çözüm|cevap|yanıt|cevabı|çözüm|doğru cevap)\s*[:：]\s*(.*)$/i); if (cvm && cur) { cur.cevap = (cur.cevap ? cur.cevap + '\n' : '') + cvm[1]; cur.cevapta = true; return; }
      if (cur && cur.cevapta) { var mm = l.match(NO), nn = mm ? +(mm[1] || mm[3] || mm[5]) : 0; if (!mm || (!/^soru\s*\d/i.test(l) && nn !== cur.no + 1 && !(yeniKagit && nn === 1))) { cur.cevap += '\n' + l; return; } }
      var m = l.match(NO);
      if (m) {
        var n = +(m[1] || m[3] || m[5]), geri = m[1] ? m[2] : (m[3] ? (m[4] || '') : (m[6] || ''));
        if (n === bek || (n === 1 && !sorular.length && !cur) || (cur && n === cur.no + 1) || (n === 1 && (yeniKagit || (cur && cur.no >= 3)))) {
          var kd = ''; geri = String(geri || '').replace(/^soru\s*/i, '').replace(KOD, function (_, k) { kd = k; return ''; }).replace(/^\[?\s*([A-ZÇĞİÖŞÜ]{1,5}\.\d+(?:\.\d+)+)\.?\s*\]?\s*/, function (_, k) { kd = kd || k; return ''; }).replace(/^\s*\((?:senaryo|ek)[^)]*\)\s*/i, '').trim();
          cur = { no: n, kod: kd, satirlar: geri ? [geri] : [] }; sorular.push(cur); bek = n + 1; yeniKagit = false; return;
        }
      }
      if (cur) { var km = l.match(KOD); if (km) { if (!cur.kod) cur.kod = km[1]; l = l.replace(KOD, '').trim(); if (!l) return; } cur.satirlar.push(l); }
    });
    // cevap anahtarı: "3-B", "3) B", "1) 12 ve 72" ...
    var tk = {}, ak = {};
    if (anahtar) {
      anahtar.replace(/(\d{1,2})\s*[\)\.\-–:]\s*([A-Ea-e])(?![a-zçğıöşü])/g, function (_, n, h) { tk[+n] = h.toUpperCase(); return _; });
      anahtar.split('\n').forEach(function (l) { var m = l.match(/^\s*(?:soru\s*)?(\d{1,2})\s*[\)\.\-–:]\s*(.{2,})$/i); if (m && !/^[A-Ea-e](\s|$|[,;])/.test(m[2])) ak[+m[1]] = m[2].trim(); });
    }
    return sorular.map(function (q) {
      var t = q.satirlar.join('\n').replace(/\n{3,}/g, '\n\n').trim(), puan = 0;
      t = t.replace(/\(\s*(\d{1,3})\s*(puan|p|pn)\s*\.?\)/ig, function (_, p) { puan = +p; return ''; }).trim();
      var op = {}, kok = [], son = null;
      t.split('\n').forEach(function (l) {
        var parcalar = l.split(/\s(?=[A-E]\s*\)\s*\S)/);
        var hepsiSik = parcalar.length > 1 && parcalar.every(function (p) { return /^[A-E]\s*\)/.test(p.trim()); });
        if (hepsiSik || /^[A-E]\s*[\)\.]\s*\S/.test(l) && (son || !Object.keys(op).length)) {
          (hepsiSik ? parcalar : [l]).forEach(function (p) { var m = p.trim().match(/^([A-E])\s*[\)\.]\s*(.*)$/); if (m) { son = m[1]; op[son] = m[2]; } });
        } else if (son && Object.keys(op).length && !/[?:]$/.test(l)) op[son] += ' ' + l;
        else kok.push(l);
      });
      var harf = Object.keys(op);
      if (harf.length < 2 || harf[0] !== 'A') { kok = t.split('\n'); op = {}; harf = []; }
      kok = kok.filter(function (x, i, a) { return x || (i && a[i - 1]); });
      var root = kok.join('<br>').replace(/(<br>)+$/, ''), paragraph = '';
      var ks = kok.filter(Boolean), altMadde = ks.some(function (x) { return /^[a-hçğ]\s*\)/.test(x); });
      if (!altMadde && ks.length && !ks.some(function (x) { return /§(IMG|TBL)\d+§/.test(x); })) {
        // satır kırılmalarıyla bölünmüş tek paragraf: cümlelere ayır, son soru cümlesi kök, öncesi öncül
        var duz = ks.join(' ').replace(/\s+/g, ' ').trim(), cm = duz.match(/[^.?!…]+(?:[.?!…]+|$)/g) || [duz], ki = cm.length - 1;
        while (ki > 0 && !/[?]|(ınız|iniz|unuz|ünüz|yınız|yiniz|yunuz|yünüz)\s*[.!]?\s*$/i.test(cm[ki])) ki--;
        if (ki > 0 && /[?]|ınız|iniz|unuz|ünüz/i.test(cm[ki])) { var bas = ki; while (bas > 0 && /[?]|(ınız|iniz|unuz|ünüz)\s*[.!]?\s*$/i.test(cm[bas - 1]) && ki - bas < 1) bas--; paragraph = cm.slice(0, bas).join('').trim(); root = cm.slice(bas).join('').trim(); }
        else root = duz;
      } else if (ks.length > 2) { var r = ks.pop(); while (ks.length && /^(§IMG\d+§|§TBL\d+§)$/.test(r)) r = ks.pop() + '<br>' + r; paragraph = ks.join('<br>'); root = r; }
      // açık etiketli biçim: "Öncül: …" / "Soru: …"
      var ix = -1; q.satirlar.forEach(function (x, i) { if (ix < 0 && /^soru\s*[:：]/i.test(x)) ix = i; });
      if (ix >= 0 && harf.length < 2) { var sat2 = q.satirlar.filter(function (x) { return !/^[A-E]\s*\)/.test(x); }); ix = sat2.findIndex(function (x) { return /^soru\s*[:：]/i.test(x); }); paragraph = sat2.slice(0, ix).filter(Boolean).join('<br>').replace(/^(öncül|metin|bağlam|durum)\s*[:：]\s*/i, '').trim(); root = sat2.slice(ix).filter(Boolean).join('<br>').replace(/^soru\s*[:：]\s*/i, '').trim(); }
      paragraph = paragraph.replace(/^(öncül|metin|bağlam|durum)\s*[:：]\s*/i, ''); root = root.replace(/(^|<br>)\s*(öncül|metin|bağlam|durum)\s*[:：]\s*/ig, '$1').replace(/(^|<br>)\s*soru\s*[:：]\s*/ig, '$1');
      var soru = { type: 'classic', paragraph: paragraph, root: root, options: {}, correct: '' , puan: puan, no: q.no, kod: q.kod || '' };
      if (q.cevap) ak[q.no + '#' + sorular.indexOf(q)] = q.cevap.trim();
      if (harf.length >= 2) { soru.type = 'multiple'; ['A', 'B', 'C', 'D', 'E'].forEach(function (k) { soru.options[k] = op[k] || ''; }); soru.correct = tk[q.no] || ''; if (q.cevap) { var cm2 = q.cevap.trim().match(/^\(?([A-E])\b[\)\.]?\s*(.*)$/s); if (cm2) { soru.correct = soru.correct || cm2[1]; if (cm2[2].trim()) soru.cozum = cm2[2].trim().replace(/^\(|\)$/g, '').replace(/\n/g, '<br>'); } else soru.cozum = q.cevap.trim().replace(/\n/g, '<br>'); } }
      else { var cvp = ak[q.no + '#' + sorular.indexOf(q)] || ak[q.no] || ''; cvp = String(cvp).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/\n/g, '<br>'); if (/(\.{5,}|…{2,}|_{4,})/.test(root) && !/[?]\s*$/.test(root)) { soru.type = 'gap'; soru.options = { A: cvp }; } else soru.options = { A: cvp }; }
      if (!soru.root && !soru.paragraph) return null;
      return soru;
    }).filter(Boolean);
  }
  /** Bir belgedeki soruları sıraya duyarlı eşleştirir: yazılılar müfredat sırasını izler. */
  function siraliEslestir(sorular, meta) {
    var t = tabloD(meta.sinif, meta.ders, meta.donem); if (!t) return sorular.map(function () { return null; });
    var tek = sorular.map(function (q) { return eslestir(q, meta, true); });
    var J = t.satirlar.length, n = sorular.length; if (!n) return [];
    var S = tek.map(function (e) { return e ? e.tum : new Array(J).fill(0); });
    var mx = S.map(function (r) { return Math.max.apply(null, r) || 1; });
    var best = [], from = [];
    for (var i = 0; i < n; i++) {
      best.push([]); from.push([]);
      var onc = -1, oncv = -Infinity;
      for (var j = 0; j < J; j++) {
        if (i) { if (best[i - 1][j] > oncv) { oncv = best[i - 1][j]; onc = j; } }
        best[i][j] = S[i][j] / mx[i] + (i ? oncv : 0); from[i][j] = onc;
      }
    }
    var j = 0; for (var k = 1; k < J; k++) if (best[n - 1][k] > best[n - 1][j]) j = k;
    var yol = new Array(n); for (var i2 = n - 1; i2 >= 0; i2--) { yol[i2] = j; j = from[i2][j]; }
    return sorular.map(function (q, i) {
      var e = tek[i]; if (!e || !e.puan) return e;
      var jj = yol[i], x = t.satirlar[jj], oran = S[i][jj] / mx[i];
      if (x === e.x) return { kod: e.kod, puan: e.puan, guven: Math.min(1, e.guven + 0.15) };
      if (oran < 0.35 || (e.x && e.x.konu === x.konu)) return e;
      return { kod: x.kod || x.cikti.slice(0, 38), puan: Math.round(S[i][jj] * 100) / 100, guven: Math.round(Math.min(0.6, 0.3 + oran * 0.3) * 100) / 100 };
    });
  }
  function belgeIsle(ad, buf, kaynak) {
    return belgeMetni(ad, buf).then(function (b) {
      var y = yaziliAyristir(b.text), res = OSCImport.parse(b.text), qs = res.questions;
      if (y.length >= qs.length) qs = y;
      if (!qs.length) return [];
      qs.forEach(function (q) { q.paragraph = OSCExtract.restoreImages(q.paragraph, b.images, b.tables); q.root = OSCExtract.restoreImages(q.root, b.images, b.tables); Object.keys(q.options || {}).forEach(function (k) { q.options[k] = OSCExtract.restoreImages(q.options[k], b.images, b.tables); }); });
      var kul = []; var tara = function (h) { String(h || '').replace(/src="(data:image\/[^"]+)"/g, function (m, u) { if (kul.indexOf(u) < 0) kul.push(u); return m; }); };
      qs.forEach(function (q) { tara(q.paragraph); tara(q.root); Object.keys(q.options || {}).forEach(function (k) { tara(q.options[k]); }); });
      var harita = {}, sira = Promise.resolve();
      kul.forEach(function (u) { sira = sira.then(function () { return gorselYukle(u).then(function (x) { harita[u] = x; }); }); });
      return sira.then(function () {
        var degis = function (h) { return String(h || '').replace(/<img[^>]*src="(data:image\/[^"]+)"[^>]*>/g, function (m, u) { return harita[u] ? m.replace(u, harita[u]) : ''; }); };
        var meta = metaTahmin(ad.split('/').pop() + ' ' + b.text.slice(0, 400) + ' ' + kaynak.baslik + ' ' + kaynak.kategori, kaynak.etiket || null), esik = +(document.getElementById('hv-esik') || {}).value || 0.45;
        var sorular = qs.map(function (q) {
          var soru = { type: q.type, paragraph: degis(q.paragraph), root: degis(q.root), options: {}, correct: q.correct || '' };
          if (q.puan) soru.puan = q.puan;
          Object.keys(q.options || {}).forEach(function (k) { soru.options[k] = degis(q.options[k]); });
          return soru;
        });
        var es = siraliEslestir(sorular, meta), tt = tablo(meta.sinif, meta.ders), kodlar = {};
        if (tt) tt.satirlar.forEach(function (x) { if (x.kod) kodlar[x.kod.replace(/\.$/, '')] = x.kod; });
        return sorular.map(function (soru, i) {
          var e = es[i], kk = qs[i] && qs[i].kod && kodlar[String(qs[i].kod).replace(/\.$/, '')]; if (kk) e = { kod: kk, puan: 1, guven: 1 };
          return { elle: kk ? 1 : null, kaynak: kaynak.kaynak, kaynak_url: kaynak.url, kaynak_baslik: (kaynak.baslik || '') + ' · ' + ad.split('/').pop(), sinif: meta.sinif, ders: meta.ders, donem: meta.donem, yazili: meta.yazili, cikti_kod: e ? e.kod : '', cikti_puan: e ? e.guven : 0, durum: !e ? (meta.sinif && meta.ders && (!meta.donem || (SNR.donemler || [1]).indexOf(+meta.donem) >= 0) ? 'inceleme' : 'eslesmedi') : (e.guven >= esik ? 'onayli' : 'inceleme'), soru: soru };
        });
      });
    });
  }
  function kaynakIsle(k) {
    var ad = k.url.split('?')[0].split('/').pop(); ad = decodeURIComponent(ad);
    return indir(k.url).then(function (buf) {
      if (/\.zip$/i.test(ad)) {
        return JSZip.loadAsync(buf).then(function (z) {
          var girdiler = []; z.forEach(function (yol, f) { if (!f.dir && !/(^|\/)(__MACOSX|\.|~\$)/.test(yol) && /\.(docx|pdf|txt|zip|doc)$/i.test(yol)) girdiler.push(f); });
          var tum = [], sira = Promise.resolve(), atl = [];
          girdiler.forEach(function (f) {
            sira = sira.then(function () {
              if (/\.doc$/i.test(f.name)) { atl.push(f.name.split('/').pop() + ' (.doc)'); return; }
              return f.async('arraybuffer').then(function (b2) {
                if (/\.zip$/i.test(f.name)) return JSZip.loadAsync(b2).then(function (z2) { var p = Promise.resolve(); z2.forEach(function (y2, f2) { if (!f2.dir && /\.(docx|pdf)$/i.test(y2) && !/__MACOSX|~\$/.test(y2)) p = p.then(function () { return f2.async('arraybuffer').then(function (b3) { return belgeIsle(y2, b3, k).then(function (l) { tum = tum.concat(l); }); }); }); }); return p; });
                return belgeIsle(f.name, b2, k).then(function (l) { tum = tum.concat(l); });
              }).catch(function (e) { atl.push(f.name.split('/').pop() + ': ' + e.message); });
            });
          });
          return sira.then(function () { return { sorular: tum, atlanan: atl, belge: girdiler.length }; });
        });
      }
      return belgeIsle(ad, buf, k).then(function (l) { return { sorular: l, atlanan: [], belge: 1 }; });
    });
  }
  function baslat() {
    var yeniden = document.getElementById('hv-yeniden').checked, kuyruk = kaynaklar.filter(function (k) { return yeniden || !k.islendi; });
    if (!kuyruk.length) { alert('İşlenecek yeni dosya yok. Hepsini tekrar işlemek için “yeniden işle” kutusunu işaretleyin.'); return; }
    calisiyor = true; dur = false;
    document.getElementById('hv-basla').disabled = true; document.getElementById('hv-dur').disabled = false;
    var i = 0, top = { soru: 0, eklenen: 0, tekrar: 0, onayli: 0, hata: 0 };
    durumYaz('Kütüphaneler yükleniyor…');
    kutuphaneler().then(function adim() {
      if (dur || i >= kuyruk.length) {
        calisiyor = false; var b = document.getElementById('hv-basla'); if (b) b.disabled = false; var d = document.getElementById('hv-dur'); if (d) d.disabled = true;
        durumYaz((dur ? 'Durduruldu. ' : 'Tamamlandı. ') + top.eklenen + ' yeni soru eklendi (' + top.onayli + ' onaylı), ' + top.tekrar + ' tekrar atlandı, ' + top.hata + ' dosyada hata.');
        logEkle('■ ' + (dur ? 'Durduruldu' : 'Bitti') + ': ' + top.eklenen + ' yeni soru.', 'ok'); return;
      }
      var k = kuyruk[i++], ad = decodeURIComponent(k.url.split('?')[0].split('/').pop());
      var bar = document.getElementById('hv-bar'); if (bar) bar.style.width = Math.round(i / kuyruk.length * 100) + '%';
      durumYaz(i + '/' + kuyruk.length + ' — ' + ad);
      return kaynakIsle(k).then(function (r) {
        var parcalar = [], s = r.sorular; for (var j = 0; j < s.length; j += 150) parcalar.push(s.slice(j, j + 150));
        var p = Promise.resolve(), ek = 0, tk = 0;
        parcalar.forEach(function (pc) { p = p.then(function () { return api('ekle', { sorular: JSON.stringify(pc) }).then(function (x) { ek += x.eklenen; tk += x.tekrar; }); }); });
        return p.then(function () {
          var on = s.filter(function (x) { return x.durum === 'onayli'; }).length;
          top.soru += s.length; top.eklenen += ek; top.tekrar += tk; top.onayli += on;
          var m = s[0] ? (s[0].sinif ? s[0].sinif + '. sınıf ' : '') + (s[0].ders || '') : '';
          logEkle('✓ ' + ad + (m ? ' [' + m + ']' : '') + ': ' + s.length + ' soru (' + ek + ' yeni, ' + on + ' onaylı' + (tk ? ', ' + tk + ' tekrar' : '') + ')' + (r.atlanan.length ? ' · atlanan: ' + r.atlanan.join(', ') : ''), s.length ? '' : 'uyari');
          k.islendi = { n: s.length };
          return api('isaretle', { k: k.k, n: s.length, hata: r.atlanan.join(', ') });
        });
      }).catch(function (e) {
        top.hata++; logEkle('✗ ' + ad + ': ' + e.message, 'hata');
        k.islendi = { n: 0, hata: e.message };
        return api('isaretle', { k: k.k, n: 0, hata: e.message, gecici: /indirilemedi|fetch|network|Failed|zaman/i.test(e.message) ? 1 : '' }).catch(function () {});
      }).then(adim);
    }).catch(function (e) { calisiyor = false; durumYaz('Hata: ' + e.message); logEkle('✗ ' + e.message, 'hata'); });
  }

  /* ================= havuzu inceleme ================= */
  var filtre = { sinif: '', ders: '', donem: '', durum: 'inceleme', cikti: '', ara: '' }, sayfa = 0, son = null;
  function dersler() { var o = {}; SNR.tablolar.forEach(function (t) { o[t.ders] = 1; }); o['T.C. İnkılap Tarihi ve Atatürkçülük'] = 1; return Object.keys(o).sort(function (a, b) { return a.localeCompare(b, 'tr'); }); }
  function ciktiSecenek(sinif, ders, sec) {
    var t = tablo(sinif, ders); if (!t) return '<option value="">— senaryo tablosu yok —</option>';
    return '<option value="">— çıktı seçin —</option>' + t.satirlar.map(function (x) { var k = x.kod || x.cikti.slice(0, 38); return '<option value="' + esc(k) + '"' + (k === sec ? ' selected' : '') + '>' + esc((x.kod ? x.kod + ' · ' : '') + x.cikti.slice(0, 90)) + (kullanilan(x) ? '' : ' (senaryoda yok)') + '</option>'; }).join('');
  }
  function vHavuz() {
    kok.innerHTML = '<div class="osc-hv-k osc-hv-filtre"><label>Sınıf <select data-f="sinif"><option value="">Tümü</option>' + [5, 6, 7, 8, 9, 10, 11, 12, 4, 3, 2, 1].map(function (s) { return '<option' + (+filtre.sinif === s ? ' selected' : '') + '>' + s + '</option>'; }).join('') + '<option value="0"' + (filtre.sinif === 0 ? ' selected' : '') + '>Belirsiz</option></select></label>' +
      '<label>Ders <select data-f="ders"><option value="">Tümü</option>' + dersler().map(function (d) { return '<option' + (filtre.ders === d ? ' selected' : '') + '>' + esc(d) + '</option>'; }).join('') + '</select></label>' +
      '<label>Dönem <select data-f="donem"><option value="">Tümü</option><option value="1"' + (filtre.donem === '1' ? ' selected' : '') + '>1. dönem</option><option value="2"' + (filtre.donem === '2' ? ' selected' : '') + '>2. dönem</option></select></label>' +
      '<label>Durum <select data-f="durum"><option value="">Tümü</option><option value="inceleme"' + (filtre.durum === 'inceleme' ? ' selected' : '') + '>İnceleme bekliyor</option><option value="eslesmedi"' + (filtre.durum === 'eslesmedi' ? ' selected' : '') + '>Eşleşmedi</option><option value="onayli"' + (filtre.durum === 'onayli' ? ' selected' : '') + '>Onaylı</option></select></label>' +
      '<label>Ara <input data-f="ara" value="' + esc(filtre.ara) + '" placeholder="soru metninde"></label><button class="button" id="hv-uygula">Listele</button>' +
      '<span class="sag">' + ((SNR.donemler || [1]).indexOf(2) < 0 ? '<button class="button" id="hv-d2" title="2. dönem senaryoları henüz yüklenmedi; bu dönemin arşiv soruları 1. dönem çıktılarından ayrılır ve senaryolar gelene kadar beklemede kalır">2. dönem sorularını beklemeye al</button> ' : '') + '<button class="button" id="hv-oto" title="Seçili filtredeki sorular için öğrenme çıktısını yeniden hesaplar">Otomatik eşleştir</button> <button class="button button-primary" id="hv-hepsi">Görünenleri onayla</button></span></div><div id="hv-sonuc"></div>';
    kok.querySelectorAll('[data-f]').forEach(function (e) { e.onchange = function () { filtre[e.dataset.f] = e.dataset.f === 'sinif' ? (e.value === '' ? '' : +e.value) : e.value; }; });
    document.getElementById('hv-uygula').onclick = function () { var a = kok.querySelector('[data-f=ara]'); filtre.ara = a.value; sayfa = 0; listele(); };
    document.getElementById('hv-hepsi').onclick = function () { if (!son || !son.satirlar.length) return; var ids = son.satirlar.filter(function (r) { return r.cikti_kod; }).map(function (r) { return r.id; }); if (!ids.length) return alert('Görünen sorularda çıktısı seçilmiş soru yok.'); if (!confirm(ids.length + ' soru onaylansın mı?')) return; api('guncelle', { ids: ids, durum: 'onayli' }).then(listele); };
    document.getElementById('hv-oto').onclick = otoEslestir;
    var d2 = document.getElementById('hv-d2'); if (d2) d2.onclick = function () { if (!confirm('2. dönem senaryoları yüklenene kadar, 2. dönem yazılılarından gelen arşiv soruları 1. dönem çıktılarından ayrılıp “Eşleşmedi”de bekletilsin mi? (Sorular silinmez.)')) return; api('donem_beklet', {}).then(function (r) { alert(r.n + ' soru beklemeye alındı. 2. dönem senaryolarını yükleyince otomatik eşleştirilir.'); listele(); }); };
    listele();
  }
  function listele() {
    var el = document.getElementById('hv-sonuc'); el.innerHTML = '<div class="osc-hv-k">Yükleniyor…</div>';
    api('liste', { sinif: filtre.sinif === 0 ? '' : filtre.sinif, ders: filtre.ders, donem: filtre.donem, durum: filtre.durum, ara: filtre.ara, bas: sayfa * 40, adet: 40 }).then(function (d) {
      son = d;
      if (!d.satirlar.length) { el.innerHTML = '<div class="osc-hv-k">Bu filtrede soru yok.</div>'; return; }
      el.innerHTML = '<p class="osc-hv-alt">' + d.toplam + ' soru · ' + (sayfa * 40 + 1) + '–' + Math.min(d.toplam, sayfa * 40 + 40) + ' arası gösteriliyor</p>' + d.satirlar.map(function (r) {
        var q = r.soru || {}, op = q.options || {};
        return '<div class="osc-hv-soru ' + r.durum + '" data-id="' + r.id + '"><div class="ust"><span class="dur ' + r.durum + '">' + ({ onayli: 'Onaylı', inceleme: 'İnceleme', eslesmedi: 'Eşleşmedi' }[r.durum] || r.durum) + '</span><span>' + (r.sinif ? r.sinif + '. sınıf' : 'sınıf ?') + ' · ' + esc(r.ders || 'ders ?') + (r.donem ? ' · ' + r.donem + '. dönem' : '') + (r.yazili ? ' ' + r.yazili + '. yazılı' : '') + '</span><span class="kay" title="' + esc(r.kaynak_url) + '">' + esc(r.kaynak) + ': ' + esc(r.kaynak_baslik) + '</span></div>' +
          '<div class="ic">' + (q.paragraph ? '<div class="p">' + q.paragraph + '</div>' : '') + '<div class="r">' + (q.root || '') + '</div>' + (q.type === 'multiple' || q.type === 'truefalse' ? Object.keys(op).filter(function (k) { return op[k]; }).map(function (k) { return '<div class="o' + (q.correct === k ? ' d' : '') + '"><b>' + k + ')</b> ' + op[k] + '</div>'; }).join('') : '') +
            '<div class="cvp' + ((q.type === 'multiple' || q.type === 'truefalse') ? (q.correct ? '' : ' yok') : (op.A ? '' : ' yok')) + '"><b>Cevap:</b> ' + ((q.type === 'multiple' || q.type === 'truefalse') ? '<select data-cv><option value="">— seçin —</option>' + Object.keys(op).filter(function (k) { return op[k]; }).map(function (k) { return '<option' + (q.correct === k ? ' selected' : '') + '>' + k + '</option>'; }).join('') + '</select>' : '<input data-cv value="' + esc(String(op.A || '').replace(/<br\s*\/?>/g, ' ').replace(/<[^>]+>/g, '').replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#039;/g, "'")) + '" placeholder="Cevap anahtarı yok — yazın ya da yapay zekâya tamamlatın">') + (q.cevap_yz ? '<span class="yzr" title="Cevap yapay zekâ tarafından yazıldı; kontrol edin">🤖 yapay zekâ</span>' : '') + '<span class="kay-ok"></span></div></div>' +
          '<div class="alt"><select class="cikti">' + ciktiSecenek(r.sinif, r.ders, r.cikti_kod) + '</select>' + (r.cikti_puan ? '<span class="guven">güven %' + Math.round(r.cikti_puan * 100) + '</span>' : '') + '<button class="button button-primary" data-x="onay">Onayla</button><button class="button" data-x="sil">Sil</button></div></div>';
      }).join('') + '<div class="osc-hv-sayfa">' + (sayfa > 0 ? '<button class="button" data-p="-1">‹ Önceki</button>' : '') + (d.toplam > (sayfa + 1) * 40 ? '<button class="button" data-p="1">Sonraki ›</button>' : '') + '</div>';
      matIsle(el);
      el.querySelectorAll('[data-p]').forEach(function (b) { b.onclick = function () { sayfa += +b.dataset.p; listele(); window.scrollTo(0, 0); }; });
      el.querySelectorAll('.osc-hv-soru').forEach(function (k) {
        var id = k.dataset.id;
        k.querySelector('.cikti').onchange = function (e) { api('guncelle', { ids: [id], cikti_kod: e.target.value, cikti_puan: 1 }); };
        k.querySelector('[data-x=onay]').onclick = function () { var c = k.querySelector('.cikti').value; if (!c && !confirm('Öğrenme çıktısı seçilmedi. Yine de onaylansın mı? (Çıktısız sorular yazılıya otomatik gelmez.)')) return; api('guncelle', { ids: [id], durum: 'onayli', cikti_kod: c }).then(function () { k.classList.add('gitti'); setTimeout(function () { k.remove(); }, 250); }); };
        var cv = k.querySelector('[data-cv]');
        if (cv) cv.onchange = function () {
          var r = (son.satirlar || []).filter(function (x) { return String(x.id) === String(id); })[0]; if (!r) return;
          var q2 = JSON.parse(JSON.stringify(r.soru || {})), v = cv.value.trim();
          if (q2.type === 'multiple' || q2.type === 'truefalse') q2.correct = v; else q2.options = { A: esc(v) };
          delete q2.cevap_yz; r.soru = q2;
          api('guncelle', { ids: [id], soru: JSON.stringify(q2) }).then(function () { var o = k.querySelector('.kay-ok'); o.textContent = '✓ kaydedildi'; k.querySelector('.cvp').classList.toggle('yok', !v); var y = k.querySelector('.yzr'); if (y) y.remove(); setTimeout(function () { o.textContent = ''; }, 1800); }).catch(function (e) { alert(e.message); });
        };
        k.querySelector('[data-x=sil]').onclick = function () { api('guncelle', { ids: [id], sil: 1 }).then(function () { k.classList.add('gitti'); setTimeout(function () { k.remove(); }, 250); }); };
      });
    }).catch(function (e) { el.innerHTML = '<div class="notice notice-error"><p>' + esc(e.message) + '</p></div>'; });
  }
  /** yeni yüklenen dönemin bekleyen (eşleşmemiş) sorularını o dönemin çıktılarıyla eşleştir */
  function bekleyenEslestir(dns) {
    var top = { n: 0, on: 0 }, p = Promise.resolve();
    dns.forEach(function (dn) {
      var bas = 0, tur = function () {
        return api('liste', { donem: dn, durum: 'eslesmedi', bas: bas, adet: 300 }).then(function (d) {
          var q = Promise.resolve(), yeni = 0;
          d.satirlar.forEach(function (r) {
            if (!r.sinif || !r.ders) return;
            var e = eslestir(r.soru || {}, { sinif: +r.sinif, ders: r.ders, donem: dn, yazili: +r.yazili }); if (!e || !e.kod) return;
            var durum = e.guven >= 0.45 ? 'onayli' : 'inceleme'; top.n++; yeni++; if (durum === 'onayli') top.on++;
            q = q.then(function () { return api('guncelle', { ids: [r.id], cikti_kod: e.kod, cikti_puan: e.guven, durum: durum }); });
          });
          return q.then(function () { bas += 300 - yeni; if (d.satirlar.length === 300) return tur(); });
        });
      };
      p = p.then(tur);
    });
    return p.then(function () { return top; });
  }
  function otoEslestir() {
    var b = document.getElementById('hv-oto'); b.disabled = true; b.textContent = 'Eşleştiriliyor…';
    var esik = 0.45, n = 0, on = 0;
    api('liste', { sinif: filtre.sinif === 0 ? '' : filtre.sinif, ders: filtre.ders, donem: filtre.donem, durum: filtre.durum, ara: filtre.ara, bas: 0, adet: 500 }).then(function (d) {
      var p = Promise.resolve();
      d.satirlar.forEach(function (r) {
        var meta = (r.sinif && r.ders) ? { sinif: +r.sinif, ders: r.ders, donem: +r.donem, yazili: +r.yazili } : metaTahmin(r.kaynak_baslik + ' ' + r.ders);
        var e = eslestir(r.soru || {}, meta); if (!e || !e.kod) return;
        var durum = r.durum === 'onayli' ? 'onayli' : (e.guven >= esik ? 'onayli' : 'inceleme'); n++; if (durum === 'onayli') on++;
        p = p.then(function () { return api('guncelle', { ids: [r.id], cikti_kod: e.kod, cikti_puan: e.guven, durum: durum, sinif: meta.sinif, ders: meta.ders }); });
      });
      return p;
    }).then(function () { b.disabled = false; b.textContent = 'Otomatik eşleştir'; alert(n + ' soru eşleştirildi, ' + on + ' tanesi onaylandı.'); listele(); }).catch(function (e) { b.disabled = false; b.textContent = 'Otomatik eşleştir'; alert(e.message); });
  }

  /* ================= havuza soru ekleme: tek tek / dosyadan ================= */
  var ekS = { sinif: 5, ders: '', donem: 1, yazili: 1, mod: 'tek', tur: 'acik', liste: [] };
  function metinHtml(v) { return esc(String(v || '').trim()).replace(/\n/g, '<br>'); }
  function vEkle() {
    if (!ekS.ders) ekS.ders = dersler()[0] || '';
    var sec = function (ad, l, v) { return '<select data-e="' + ad + '">' + l.map(function (x) { var k = Array.isArray(x) ? x[0] : x, e = Array.isArray(x) ? x[1] : x; return '<option value="' + esc(k) + '"' + (String(k) === String(v) ? ' selected' : '') + '>' + esc(e) + '</option>'; }).join('') + '</select>'; };
    kok.innerHTML = '<div class="osc-hv-k"><h2 style="margin-top:0">Havuza soru ekle</h2><p class="osc-hv-alt">Eklediğiniz sorular soru havuzuna girer ve Yazılı Hazırla’da seçilen çıktıya göre kullanılır.</p>' +
      '<div class="osc-hv-filtre" style="padding:0;border:0;box-shadow:none"><label>Sınıf ' + sec('sinif', [5, 6, 7, 8, 9, 10, 11, 12], ekS.sinif) + '</label><label>Ders ' + sec('ders', dersler(), ekS.ders) + '</label><label>Dönem ' + sec('donem', [[1, '1. dönem'], [2, '2. dönem']], ekS.donem) + '</label><label>Yazılı ' + sec('yazili', [[1, '1. yazılı'], [2, '2. yazılı']], ekS.yazili) + '</label></div>' +
      '<nav class="osc-hv-mod" style="display:flex;gap:6px;margin:14px 0 4px"><button class="button' + (ekS.mod === 'tek' ? ' button-primary' : '') + '" data-mod="tek">✍️ Tek tek ekle</button><button class="button' + (ekS.mod === 'dosya' ? ' button-primary' : '') + '" data-mod="dosya">📁 Dosyadan toplu ekle</button></nav></div><div id="hv-ek"></div>';
    kok.querySelectorAll('[data-e]').forEach(function (e) { e.onchange = function () { ekS[e.dataset.e] = /sinif|donem|yazili/.test(e.dataset.e) ? +e.value : e.value; if (ekS.mod === 'tek') { var c = document.getElementById('ek-cikti'); if (c) c.innerHTML = ciktiSecenek(ekS.sinif, ekS.ders, ''); } else if (ekS.liste.length) onizle(); }; });
    kok.querySelectorAll('[data-mod]').forEach(function (b) { b.onclick = function () { ekS.mod = b.dataset.mod; vEkle(); }; });
    if (ekS.mod === 'tek') ekTek(); else ekDosya();
  }
  function ekTek() {
    var el = document.getElementById('hv-ek');
    el.innerHTML = '<div class="osc-hv-k osc-hv-form"><div class="sat"><label>Öğrenme çıktısı<select id="ek-cikti">' + ciktiSecenek(ekS.sinif, ekS.ders, '') + '</select></label></div>' +
      '<div class="sat iki"><label>Soru türü<select id="ek-tur"><option value="acik">Açık uçlu</option><option value="test">Çoktan seçmeli (test)</option><option value="gap">Boşluk doldurma</option><option value="dy">Doğru / yanlış</option></select></label><label>Zorluk<select id="ek-zor"><option value="0">Otomatik</option><option value="1">Kolay</option><option value="2">Orta</option><option value="3">Zor</option></select></label></div>' +
      '<label>Öncül / bağlam metni <small>(isteğe bağlı)</small><textarea id="ek-oncul" rows="3" placeholder="Soru öncesi okuma metni, durum, veri…"></textarea></label>' +
      '<label>Görsel <small>(isteğe bağlı)</small><input type="file" id="ek-gorsel" accept="image/*"><span id="ek-gorsel-on"></span></label>' +
      '<label>Soru kökü<textarea id="ek-kok" rows="3" placeholder="Örn: Verilen kesirleri büyükten küçüğe sıralayınız."></textarea></label>' +
      '<div id="ek-siklar" hidden><div class="sat iki">' + ['A', 'B', 'C', 'D', 'E'].map(function (h) { return '<label>' + h + ') <input data-sik="' + h + '"' + (h === 'E' ? ' placeholder="(boş bırakılabilir)"' : '') + '></label>'; }).join('') + '</div><label>Doğru şık<select id="ek-dogru"><option value="">—</option><option>A</option><option>B</option><option>C</option><option>D</option><option>E</option></select></label></div>' +
      '<label id="ek-cvp-l">Cevap / çözüm <small>(cevap anahtarında görünür)</small><textarea id="ek-cvp" rows="2"></textarea></label>' +
      '<p class="osc-hv-alt">Matematik ifadeleri için $…$ içinde LaTeX yazabilirsiniz: $\\frac{3}{4}$, $2^{5}$, $\\sqrt{16}$</p>' +
      '<p><button class="button button-primary button-large" id="ek-kaydet">Havuza ekle</button> <span id="ek-msj"></span></p></div>' +
      '<style>.osc-hv-form label{display:block;margin:0 0 12px;font-weight:600}.osc-hv-form label small{font-weight:400;color:#64748b}.osc-hv-form textarea,.osc-hv-form input:not([type=file]),.osc-hv-form select{display:block;width:100%;max-width:100%;margin-top:4px}.osc-hv-form .sat.iki{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}#ek-gorsel-on img{max-height:120px;display:block;margin-top:6px;border:1px solid #ddd;border-radius:6px}</style>';
    var gorsel = '';
    var tur = document.getElementById('ek-tur');
    tur.onchange = function () { document.getElementById('ek-siklar').hidden = tur.value !== 'test'; document.getElementById('ek-cvp-l').firstChild.textContent = tur.value === 'test' ? 'Çözüm ' : (tur.value === 'dy' ? 'Cevaplar (her satıra bir: D veya Y) ' : 'Cevap / çözüm '); document.getElementById('ek-kok').placeholder = tur.value === 'dy' ? 'Yönerge, ardından her satıra bir önerme yazın' : 'Soru kökü'; };
    document.getElementById('ek-gorsel').onchange = function (e) {
      var f = e.target.files[0]; if (!f) return; var on = document.getElementById('ek-gorsel-on'); on.textContent = ' yükleniyor…';
      var r = new FileReader(); r.onload = function () { kutuphaneler().then(function () { return gorselYukle(r.result); }).then(function (u) { gorsel = u; on.innerHTML = u ? '<img src="' + esc(u) + '">' : ' yüklenemedi'; }); }; r.readAsDataURL(f);
    };
    document.getElementById('ek-kaydet').onclick = function () {
      var t = tur.value, kok2 = document.getElementById('ek-kok').value.trim(), on = document.getElementById('ek-oncul').value.trim(), cv = document.getElementById('ek-cvp').value.trim(), msj = document.getElementById('ek-msj');
      if (!kok2 && !gorsel) { msj.textContent = 'Soru kökünü yazın.'; return; }
      var soru = { type: 'classic', paragraph: metinHtml(on) + (gorsel ? '<p><img src="' + esc(gorsel) + '" alt=""></p>' : ''), root: metinHtml(kok2), options: {}, correct: '' };
      if (t === 'test') {
        soru.type = 'multiple'; var n = 0; kok.querySelectorAll('[data-sik]').forEach(function (i) { soru.options[i.dataset.sik] = metinHtml(i.value); if (i.value.trim()) n++; });
        if (n < 2) { msj.textContent = 'En az iki şık yazın.'; return; }
        soru.correct = document.getElementById('ek-dogru').value; if (cv) soru.cozum = metinHtml(cv);
      } else if (t === 'dy') {
        var sat = kok2.split('\n').map(function (x) { return x.trim(); }).filter(Boolean), ilk = sat.shift();
        soru.root = metinHtml(ilk) + sat.map(function (x) { return '<br>(&nbsp;&nbsp;&nbsp;&nbsp;) ' + esc(x.replace(/^\(\s*\)\s*/, '')); }).join('');
        soru.options = { A: metinHtml(cv) };
      } else { soru.type = t === 'gap' ? 'gap' : 'classic'; soru.options = { A: metinHtml(cv) }; }
      var z = +document.getElementById('ek-zor').value; if (z) soru.zorluk = z;
      var c = document.getElementById('ek-cikti').value;
      var b = this; b.disabled = true;
      api('ekle', { sorular: JSON.stringify([{ kaynak: 'yonetici', kaynak_baslik: 'Elle eklendi', sinif: ekS.sinif, ders: ekS.ders, donem: ekS.donem, yazili: ekS.yazili, cikti_kod: c, cikti_puan: 1, durum: c ? 'onayli' : 'inceleme', soru: soru }]) }).then(function (r) {
        b.disabled = false;
        if (r.eklenen) { msj.innerHTML = '<b style="color:#059669">✓ Havuza eklendi' + (c ? '' : ' (çıktı seçilmediği için “inceleme”de)') + '.</b>'; ['ek-kok', 'ek-oncul', 'ek-cvp'].forEach(function (i) { document.getElementById(i).value = ''; }); kok.querySelectorAll('[data-sik]').forEach(function (i) { i.value = ''; }); gorsel = ''; document.getElementById('ek-gorsel-on').innerHTML = ''; document.getElementById('ek-gorsel').value = ''; }
        else msj.textContent = r.tekrar ? 'Bu soru havuzda zaten var.' : 'Eklenemedi.';
      }).catch(function (e) { b.disabled = false; msj.textContent = e.message; });
    };
  }
  /* Yapay zekâ ile belge okuma: PDF sayfa görüntüsünden, Word metninden; öncül/kök/cevap ayrı, formüller LaTeX, resimler kesilmiş */
  function yzBelge(ad, buf, meta) {
    var a = ad.toLowerCase(), oku;
    if (/\.pdf$/.test(a)) oku = pdfYukle().then(function () { return pdfLib.getDocument({ data: new Uint8Array(buf) }).promise; }).then(function (pdf) { return OSCExtract.pdfYZ(pdf, { mod: 'yazili', OPS: pdfLib.OPS, onProgress: function (m) { var e = document.getElementById('ek-dmsj'); if (e) e.textContent = '🤖 ' + m; } }); });
    else if (/\.docx$/.test(a)) oku = OSCExtract.docxToText(buf, { latex: true }).then(function (r) { return OSCExtract.metinYZ(r.text, { onProgress: function (m) { var e = document.getElementById('ek-dmsj'); if (e) e.textContent = '🤖 ' + m; } }).then(function (res) { res.questions.forEach(function (q) { q.paragraph = OSCExtract.restoreImages(q.paragraph, r.images || [], r.tables || []); q.root = OSCExtract.restoreImages(q.root, r.images || [], r.tables || []); Object.keys(q.options || {}).forEach(function (k) { q.options[k] = OSCExtract.restoreImages(q.options[k], r.images || [], r.tables || []); }); if (q.cozum) q.cozum = OSCExtract.restoreImages(q.cozum, r.images || [], r.tables || []); }); return res; }); });
    else return Promise.reject(new Error('yapay zekâ ile yalnızca PDF ve Word (.docx) okunur'));
    return oku.then(function (res) {
      var qs = res.questions, kul = [], tara = function (h) { String(h || '').replace(/src="(data:image\/[^"]+)"/g, function (m, u) { if (kul.indexOf(u) < 0) kul.push(u); return m; }); };
      qs.forEach(function (q) { tara(q.paragraph); tara(q.root); tara(q.cozum); Object.keys(q.options || {}).forEach(function (k) { tara(q.options[k]); }); });
      var harita = {}, sira = Promise.resolve();
      kul.forEach(function (u) { sira = sira.then(function () { return gorselYukle(u).then(function (x) { harita[u] = x; }); }); });
      return sira.then(function () {
        var degis = function (h) { return String(h || '').replace(/<img[^>]*src="(data:image\/[^"]+)"[^>]*>/g, function (m, u) { return harita[u] ? m.replace(u, harita[u]) : ''; }); };
        var t = tablo(meta.sinif, meta.ders), kodlar = {}; if (t) t.satirlar.forEach(function (x) { if (x.kod) kodlar[x.kod.replace(/\.$/, '')] = x.kod; });
        var sorular = qs.map(function (q) { var o = { type: q.type, paragraph: degis(q.paragraph), root: degis(q.root), options: {}, correct: q.correct || '' }; Object.keys(q.options || {}).forEach(function (k) { o.options[k] = degis(q.options[k]); }); if (q.cozum) o.cozum = degis(q.cozum); return o; });
        var es = siraliEslestir(sorular, meta);
        return sorular.map(function (soru, i) { var k = qs[i].kod && kodlar[qs[i].kod.replace(/\.$/, '')]; return { kaynak: 'yonetici', kaynak_url: '', kaynak_baslik: ad, cikti_kod: k || (es[i] ? es[i].kod : ''), elle: k ? 1 : null, soru: soru }; });
      });
    });
  }
  function ekDosya() {
    var el = document.getElementById('hv-ek');
    el.innerHTML = '<div class="osc-hv-k"><p><b>Word (.docx), PDF ya da metin (.txt)</b> dosyası seçin veya soruları aşağıya yapıştırın. Sorular numaralı olmalı (1) 2) … ya da 1. 2. …). Sonunda “Cevap anahtarı” bölümü varsa cevaplar da okunur.</p>' +
      '<p><input type="file" id="ek-dosya" accept=".docx,.pdf,.txt" multiple> ' + (window.OSC_PDF_YZ ? '<label style="margin-left:8px"><input type="checkbox" id="ek-yz" checked> 🤖 Yapay zekâ ile oku (önerilen: öncül, soru, cevap ayrı; formüller ve resimler düzgün)</label>' : '<em class="osc-hv-alt">Yapay zekâ anahtarı tanımlanırsa belgeler yapay zekâyla çok daha düzgün okunur.</em>') + ' <span id="ek-dmsj"></span></p>' +
      '<details><summary>…ya da metin yapıştırın</summary><textarea id="ek-metin" rows="8" style="width:100%" placeholder="1) Birinci soru…&#10;2) İkinci soru…&#10;A) … B) … C) … D) …"></textarea><p><button class="button" id="ek-metin-oku">Metni oku</button></p></details></div><div id="ek-on"></div>';
    var oku = function (sorular) { ekS.liste = ekS.liste.concat(sorular); onizle(); };
    document.getElementById('ek-dosya').onchange = function (e) {
      var fl = [].slice.call(e.target.files || []), msj = document.getElementById('ek-dmsj'); if (!fl.length) return; msj.textContent = 'Okunuyor…';
      var p = kutuphaneler();
      var yzk = document.getElementById('ek-yz'), yzAcik = yzk && yzk.checked, hata = '';
      fl.forEach(function (f) { p = p.then(function () { return f.arrayBuffer(); }).then(function (buf) {
        var meta = { sinif: ekS.sinif, ders: ekS.ders, donem: ekS.donem, yazili: ekS.yazili }, duz = function () { return belgeIsle(f.name, buf, { kaynak: 'yonetici', url: '', baslik: f.name, kategori: '', etiket: meta }); };
        if (yzAcik && /\.(pdf|docx)$/i.test(f.name)) return yzBelge(f.name, buf, meta).catch(function (er) { hata = f.name + ': yapay zekâ okuyamadı (' + er.message + '), standart okuma kullanıldı. '; return duz(); });
        return duz();
      }).then(oku).catch(function (er) { hata += f.name + ': ' + er.message + ' '; }); });
      p.then(function () { if (hata) setTimeout(function () { msj.textContent = hata; }, 50); });
      p.then(function () { msj.textContent = ''; e.target.value = ''; });
    };
    document.getElementById('ek-metin-oku').onclick = function () {
      var t = document.getElementById('ek-metin').value; if (!t.trim()) return;
      var y = yaziliAyristir(t); if (!y.length) { alert('Numaralı soru bulunamadı.'); return; }
      oku(y.map(function (q) { delete q.no; delete q.puan; return { kaynak: 'yonetici', kaynak_url: '', kaynak_baslik: 'Yapıştırılan metin', soru: q }; }));
      document.getElementById('ek-metin').value = '';
    };
    if (ekS.liste.length) onizle();
  }
  function onizle() {
    var el = document.getElementById('ek-on'); if (!el) return;
    var meta = { sinif: ekS.sinif, ders: ekS.ders, donem: ekS.donem, yazili: ekS.yazili };
    var es = siraliEslestir(ekS.liste.map(function (x) { return x.soru; }), meta);
    ekS.liste.forEach(function (x, i) { if (x.elle == null) x.cikti_kod = es[i] ? es[i].kod : ''; x.sec = x.sec !== false; });
    el.innerHTML = '<div class="osc-hv-k"><b>' + ekS.liste.length + ' soru okundu.</b> Eklemek istemediklerinizin işaretini kaldırın, gerekiyorsa öğrenme çıktısını değiştirin. <span class="sag"><button class="button" id="ek-temizle">Listeyi temizle</button> <button class="button button-primary" id="ek-hepsi">Seçilenleri havuza ekle</button></span> <span id="ek-smsj"></span></div>' +
      ekS.liste.map(function (x, i) {
        var q = x.soru || {}, op = q.options || {};
        return '<div class="osc-hv-soru" data-i="' + i + '"><div class="ust"><label><input type="checkbox" data-s' + (x.sec ? ' checked' : '') + '> ' + (i + 1) + '. soru</label><span>' + ({ multiple: 'Test', classic: 'Açık uçlu', gap: 'Boşluk', truefalse: 'D/Y' }[q.type] || q.type) + '</span></div><div class="ic">' + (q.paragraph ? '<div class="p">' + q.paragraph + '</div>' : '') + '<div class="r">' + (q.root || '') + '</div>' + (q.type === 'multiple' ? Object.keys(op).filter(function (k) { return op[k]; }).map(function (k) { return '<div class="o' + (q.correct === k ? ' d' : '') + '"><b>' + k + ')</b> ' + op[k] + '</div>'; }).join('') : (op.A ? '<div class="cvp"><b>Cevap:</b> ' + op.A + '</div>' : '')) + '</div><div class="alt"><select class="cikti">' + ciktiSecenek(ekS.sinif, ekS.ders, x.cikti_kod) + '</select></div></div>';
      }).join('');
    matIsle(el);
    el.querySelectorAll('.osc-hv-soru').forEach(function (k) { var x = ekS.liste[+k.dataset.i]; k.querySelector('[data-s]').onchange = function (e) { x.sec = e.target.checked; }; k.querySelector('.cikti').onchange = function (e) { x.cikti_kod = e.target.value; x.elle = 1; }; });
    document.getElementById('ek-temizle').onclick = function () { ekS.liste = []; el.innerHTML = ''; };
    document.getElementById('ek-hepsi').onclick = function () {
      var l = ekS.liste.filter(function (x) { return x.sec; }); if (!l.length) return;
      var b = this, msj = document.getElementById('ek-smsj'); b.disabled = true; msj.textContent = 'Ekleniyor…';
      api('ekle', { sorular: JSON.stringify(l.map(function (x) { return { kaynak: 'yonetici', kaynak_url: x.kaynak_url || '', kaynak_baslik: x.kaynak_baslik || 'Dosyadan eklendi', sinif: ekS.sinif, ders: ekS.ders, donem: ekS.donem, yazili: ekS.yazili, cikti_kod: x.cikti_kod || '', cikti_puan: 1, durum: x.cikti_kod ? 'onayli' : 'inceleme', soru: x.soru }; })) }).then(function (r) {
        ekS.liste = []; el.innerHTML = '<div class="osc-hv-k"><b style="color:#059669">✓ ' + r.eklenen + ' soru havuza eklendi.</b>' + (r.tekrar ? ' ' + r.tekrar + ' soru zaten havuzdaydı.' : '') + '</div>';
      }).catch(function (e) { b.disabled = false; msj.textContent = e.message; });
    };
  }

  /* ================= yapay zekâ ile toplu doldurma ================= */
  var uP = { donem: (SNR.donemler || [1])[0], yazili: 'hepsi', sinif: 0, ders: '', carpan: 4, en_az: 6, test: 25, dogrula: 1, butce: 15 }, uZ = 0;
  function vUret() {
    var dn = SNR.donemler || [1], sec = function (ad, l, v) { return '<select data-u="' + ad + '">' + l.map(function (x) { return '<option value="' + esc(x[0]) + '"' + (String(x[0]) === String(v) ? ' selected' : '') + '>' + esc(x[1]) + '</option>'; }).join('') + '</select>'; };
    var ders = {}; SNR.tablolar.forEach(function (t) { if ((t.donem || 1) === +uP.donem && (!uP.sinif || t.sinif === +uP.sinif)) ders[t.ders] = 1; });
    kok.innerHTML = '<div class="osc-hv-k"><h2 style="margin-top:0">🤖 Soru havuzunu yapay zekâ ile doldur</h2><p class="osc-hv-alt">MEB senaryo tablolarındaki her öğrenme çıktısı için, seçtiğiniz dönem ve yazılının senaryolarına göre yeterli sayıda özgün soru ve cevap üretilir. Öğretmen “Yazılı hazırla” dediğinde sorular bu havuzdan, senaryoya uygun olarak <b>anında</b> gelir. İş sunucuda arka planda çalışır; sayfayı kapatabilirsiniz.</p>' +
      '<div class="osc-hv-filtre" style="padding:0;border:0;box-shadow:none"><label>Dönem ' + sec('donem', [1, 2].map(function (d) { return [d, d + '. dönem' + (dn.indexOf(d) < 0 ? ' (senaryo yok)' : '')]; }), uP.donem) + '</label>' +
      '<label>Yazılı ' + sec('yazili', [['hepsi', '1. ve 2. yazılı'], ['1', '1. yazılı'], ['2', '2. yazılı']], uP.yazili) + '</label>' +
      '<label>Sınıf ' + sec('sinif', [[0, 'Tümü']].concat([5, 6, 7, 8, 9, 10, 11, 12].map(function (x) { return [x, x + '. sınıf']; })), uP.sinif) + '</label>' +
      '<label>Ders ' + sec('ders', [['', 'Tümü']].concat(Object.keys(ders).sort(function (a, b) { return a.localeCompare(b, 'tr'); }).map(function (x) { return [x, x]; })), uP.ders) + '</label></div>' +
      '<div class="osc-hv-filtre" style="padding:0;border:0;box-shadow:none;margin-top:10px"><label title="Bir senaryoda bir çıktıdan en çok kaç soru soruluyorsa, havuzda bunun kaç katı soru olsun">Çeşitlilik ' + sec('carpan', [[3, '3 kat'], [4, '4 kat (önerilen)'], [5, '5 kat'], [6, '6 kat']], uP.carpan) + '</label>' +
      '<label>Çıktı başına en az ' + sec('en_az', [[4, '4 soru'], [6, '6 soru'], [8, '8 soru'], [10, '10 soru']], uP.en_az) + '</label>' +
      '<label>Test sorusu oranı ' + sec('test', [[0, '%0 (yalnız açık uçlu)'], [25, '%25'], [40, '%40'], [50, '%50']], uP.test) + '</label>' +
      '<label><input type="checkbox" data-u="dogrula"' + (uP.dogrula ? ' checked' : '') + '> Cevapları ikinci kez doğrula (önerilir)</label>' +
      '<label>Bütçe sınırı $ <input type="number" step="0.5" min="0.5" data-u="butce" value="' + uP.butce + '" style="width:80px"></label></div>' +
      '<p><button class="button" id="u-test" title="Her yapay zekâ hesabını küçük bir istekle dener">🔌 Bağlantı testi</button> <button class="button" id="u-hesap">Hesapla</button> <button class="button button-primary button-large" id="u-basla" disabled>▶ Başlat</button> <span id="u-tahmin" class="osc-hv-alt"></span></p></div><div id="u-durum"></div>';
    kok.querySelectorAll('[data-u]').forEach(function (e) { e.onchange = function () { uP[e.dataset.u] = e.type === 'checkbox' ? (e.checked ? 1 : 0) : e.value; if (e.dataset.u === 'donem' || e.dataset.u === 'sinif') { uP.ders = ''; vUret(); } else { document.getElementById('u-basla').disabled = true; document.getElementById('u-tahmin').textContent = ''; } }; });
    document.getElementById('u-hesap').onclick = function () {
      var b = this; b.disabled = true; document.getElementById('u-tahmin').textContent = 'Hesaplanıyor…';
      api('uret_hesapla', uP).then(function (r) {
        b.disabled = false; var o = r.ozet, n = o.acik + o.test;
        document.getElementById('u-tahmin').innerHTML = o.cikti + ' öğrenme çıktısı · havuzda ' + o.mevcut + '/' + o.hedef + ' hazır · <b>' + n + ' soru üretilecek</b> (' + o.acik + ' açık uçlu, ' + o.test + ' test) · ' + o.ders + ' ders · tahmini maliyet <b>~' + o.tahmin + ' $</b> (' + esc(o.saglayici) + ' / ' + esc(o.model) + ')';
        document.getElementById('u-basla').disabled = !n;
      }).catch(function (e) { b.disabled = false; document.getElementById('u-tahmin').textContent = e.message; });
    };
    document.getElementById('u-test').onclick = function () {
      var b = this, t = document.getElementById('u-tahmin'); b.disabled = true; t.textContent = 'Yapay zekâ hesapları deneniyor…';
      api('uret_test', {}).then(function (l) { b.disabled = false; var ad = { claude: 'Claude', openai: 'ChatGPT', gemini: 'Gemini (ücretli)', gemini_ucretsiz: 'Gemini (ücretsiz)' }; t.innerHTML = l.length ? l.map(function (x) { return (x.ok ? '✅ ' : '❌ ') + '<b>' + (ad[x.s] || x.s) + '</b> ' + x.sure + ' sn' + (x.ok ? '' : ' — ' + esc(x.mesaj)); }).join(' &nbsp;·&nbsp; ') : 'Hiç yapay zekâ anahtarı tanımlı değil.'; }).catch(function (e) { b.disabled = false; t.textContent = e.message; });
    };
    document.getElementById('u-basla').onclick = function () { if (!confirm('Havuz doldurma başlatılsın mı? Bütçe sınırı: ' + uP.butce + ' $')) return; api('uret_basla', uP).then(uCiz).then(uIzle).catch(function (e) { alert(e.message); }); };
    api('uret_durum', {}).then(function (d) { uCiz(d); if (d.aktif) uIzle(); }).catch(function () {});
  }
  var uIsci = 0;
  function uSur() {   // sayfa açıkken 3 paralel işçi: hosting arka plan isteğini engellese bile hızlı çalışır
    if (uIsci) return;
    var calis = function () {
      if (sekme !== 'uret') { uIsci--; return; }
      api('uret_adim', {}).then(function (d) {
        uCiz(d);
        if (!d.aktif) { uIsci--; return; }
        setTimeout(calis, d.adim === 'ok' ? 200 : (d.adim === 'bekle' ? 15000 : 4000));
      }).catch(function () { setTimeout(calis, 8000); });
    };
    for (var i = 0; i < 3; i++) { uIsci++; setTimeout(calis, i * 1500); }
  }
  function uIzle() { uSur(); clearTimeout(uZ); var tur = function () { if (sekme !== 'uret') return; api('uret_durum', {}).then(function (d) { uCiz(d); if (d.aktif) uZ = setTimeout(tur, 5000); }).catch(function () { uZ = setTimeout(tur, 10000); }); }; uZ = setTimeout(tur, 3000); }
  function uCiz(d) {
    var el = document.getElementById('u-durum'); if (!el) return;
    if (!d || !d.var) { el.innerHTML = ''; return; }
    var yap = d.toplam - d.kalan, yuz = d.toplam ? Math.round(yap / d.toplam * 100) : 0;
    el.innerHTML = '<div class="osc-hv-k"><h3 style="margin-top:0">' + (d.aktif ? '⏳ Çalışıyor' : (d.kalan ? '⏸ Durdu' : '✅ Bitti')) + (d.p ? ' · ' + d.p.donem + '. dönem ' + (d.p.yazili === 'hepsi' ? '1. ve 2. yazılı' : d.p.yazili + '. yazılı') : '') + '</h3>' +
      '<div class="bar" style="height:10px;background:#eef0f6;border-radius:8px;overflow:hidden"><i style="display:block;height:100%;width:' + yuz + '%;background:linear-gradient(90deg,#5b5bf6,#c026d3)"></i></div>' +
      '<p><b>' + d.uretilen + '</b> soru üretildi · <b style="color:#059669">' + d.onayli + ' onaylı</b>' + (d.reddedilen ? ' · <span style="color:#b45309">' + d.reddedilen + ' doğrulamadan geçemedi (incelemede)</span>' : '') + ' · kalan ~' + d.kalan + ' · ' + d.istek + ' istek · harcanan ~<b>' + d.harcama + ' $</b>' + (d.p ? ' / ' + d.p.butce + ' $' : '') + '</p>' +
      (d.simdi ? '<p class="osc-hv-alt">Şimdi: ' + d.simdi.sinif + '. sınıf ' + esc(d.simdi.ders) + ' · ' + esc(d.simdi.kod) + ' — ' + esc(d.simdi.cikti) + '</p>' : '') +
      (d.mesaj ? '<p><b>' + esc(d.mesaj) + '</b>' + (d.bekle ? ' (' + d.bekle + ' sn)' : '') + '</p>' : '') + (d.son_hata && !d.mesaj ? '<p class="osc-hv-alt">Son hata: ' + esc(d.son_hata) + '</p>' : '') + (d.aktif ? '<p class="osc-hv-alt">💡 Bu sayfa açıkken üretim 3 kat hızlı çalışır; kapatırsanız arka planda daha yavaş devam eder.</p>' : '') +
      '<p>' + (d.aktif ? '<button class="button" id="u-dur">■ Durdur</button>' : (d.kalan ? 'Bütçe $ <input type="number" step="0.5" id="u-yb" value="' + (d.p ? d.p.butce : 5) + '" style="width:80px"> <button class="button button-primary" id="u-devam">▶ Devam et</button> ' : '') + '<button class="button" id="u-sil">Kaydı temizle</button>') + '</p>' +
      '<div class="osc-hv-log" style="max-height:220px;overflow:auto;font-size:12.5px;background:#f8fafc;border-radius:8px;padding:8px">' + (d.log || []).map(function (l) { return '<div class="' + l.t + '">' + esc(l.m) + '</div>'; }).join('') + '</div></div>';
    var x;
    if ((x = document.getElementById('u-dur'))) x.onclick = function () { api('uret_dur', {}).then(uCiz); };
    if ((x = document.getElementById('u-devam'))) x.onclick = function () { api('uret_devam', { butce: document.getElementById('u-yb').value }).then(function (r) { uCiz(r); uIzle(); }).catch(function (e) { alert(e.message); }); };
    if ((x = document.getElementById('u-sil'))) x.onclick = function () { if (confirm('İlerleme kaydı silinsin mi? (Üretilen sorular havuzda kalır)')) api('uret_sifirla', {}).then(uCiz); };
  }

  /* ================= senaryo tabloları (MEB Excel yükleme) ================= */
  function vSenaryo() {
    var l = SNR.tablolar.slice().sort(function (a, b) { return (a.donem || 1) - (b.donem || 1) || a.sinif - b.sinif || a.ders.localeCompare(b.ders, 'tr'); });
    var g = {}; l.forEach(function (t) { (g[t.donem || 1] = g[t.donem || 1] || []).push(t); });
    kok.innerHTML = '<div class="osc-hv-k"><h2 style="margin-top:0">📋 MEB konu-soru dağılım (senaryo) tabloları</h2><p class="osc-hv-alt">MEB yeni senaryo tablolarını yayımladığında (ör. 2. dönem) Excel dosyalarını buraya yükleyin. Her sayfa (9. SINIF, 10. SINIF …) bir sınıf olarak okunur; dönem, yazılı, senaryolar ve öğrenme çıktıları otomatik çıkarılır. Aynı sınıf-ders-dönem varsa yenisiyle değiştirilir.</p>' +
      '<p><input type="file" id="s-dosya" accept=".xlsx" multiple> <span id="s-msj"></span></p><div id="s-on"></div></div>' +
      Object.keys(g).map(function (dn) { return '<div class="osc-hv-k"><h3 style="margin-top:0">' + dn + '. dönem · ' + g[dn].length + ' tablo</h3><table class="widefat striped"><thead><tr><th>Sınıf</th><th>Ders</th><th>Çıktı</th><th>1. yazılı</th><th>2. yazılı</th><th></th></tr></thead><tbody>' + g[dn].map(function (t) {
        var y = function (k) { var s = (t.senaryolar || {})[k] || []; return s.length ? s.length + ' senaryo' : ((t.ulke || {})[k] ? 'ülke geneli' : '—'); };
        return '<tr><td>' + t.sinif + '</td><td>' + esc(t.ders) + '</td><td>' + t.satirlar.length + '</td><td>' + y('1') + '</td><td>' + y('2') + '</td><td>' + (t.yuklendi ? '<small>yüklendi</small> <a href="#" data-ssil="' + esc(t.sinif + '|' + t.ders + '|' + (t.donem || 1)) + '">sil</a>' : '<small>yerleşik</small>') + '</td></tr>';
      }).join('') + '</tbody></table></div>'; }).join('');
    kok.querySelectorAll('[data-ssil]').forEach(function (a) { a.onclick = function (e) { e.preventDefault(); if (!confirm('Bu yüklenen tablo silinsin mi?')) return; api('senaryo_sil', { k: a.dataset.ssil }).then(function (r) { SNR = r.snr; vSenaryo(); }); }; });
    document.getElementById('s-dosya').onchange = function (e) {
      var fl = [].slice.call(e.target.files || []), msj = document.getElementById('s-msj'), tum = [], uy = []; if (!fl.length) return; msj.textContent = 'Okunuyor…';
      var p = kutuphaneler();
      fl.forEach(function (f) { p = p.then(function () { return f.arrayBuffer(); }).then(function (b) { return xlsxSenaryo(f.name, b); }).then(function (r) { tum = tum.concat(r.tablolar); uy = uy.concat(r.uyari); }).catch(function (er) { uy.push(f.name + ': ' + er.message); }); });
      p.then(function () {
        msj.textContent = ''; e.target.value = '';
        var on = document.getElementById('s-on');
        on.innerHTML = (tum.length ? '<table class="widefat striped"><thead><tr><th>Dosya / sayfa</th><th>Dönem</th><th>Sınıf</th><th>Ders</th><th>Çıktı</th><th>1. yazılı</th><th>2. yazılı</th></tr></thead><tbody>' + tum.map(function (t, i) {
          var y = function (k) { return t.senaryolar[k].length ? t.senaryolar[k].length + ' senaryo' : (t.ulke[k] ? 'ülke geneli' : '—'); };
          return '<tr><td>' + esc(t._kaynak) + '</td><td><select data-sd="' + i + '"><option' + (t.donem === 1 ? ' selected' : '') + '>1</option><option' + (t.donem === 2 ? ' selected' : '') + '>2</option></select></td><td>' + t.sinif + '</td><td><input data-sders="' + i + '" value="' + esc(t.ders) + '" style="width:220px"></td><td>' + t.satirlar.length + '</td><td>' + y('1') + '</td><td>' + y('2') + '</td></tr>';
        }).join('') + '</tbody></table><p><button class="button button-primary" id="s-kaydet">' + tum.length + ' tabloyu kaydet</button></p>' : '') + (uy.length ? '<div class="notice notice-warning inline"><p>' + uy.map(esc).join('<br>') + '</p></div>' : '');
        on.querySelectorAll('[data-sd]').forEach(function (s) { s.onchange = function () { tum[+s.dataset.sd].donem = +s.value; }; });
        on.querySelectorAll('[data-sders]').forEach(function (s) { s.onchange = function () { tum[+s.dataset.sders].ders = s.value.trim(); }; });
        var k = document.getElementById('s-kaydet'); if (k) k.onclick = function () { k.disabled = true; api('senaryo_kaydet', { tablolar: JSON.stringify(tum) }).then(function (r) { SNR = r.snr; vSenaryo(); var dns = []; tum.forEach(function (t) { if (dns.indexOf(t.donem) < 0) dns.push(t.donem); }); var m = document.getElementById('s-msj'); m.innerHTML = '<b style="color:#059669">✓ ' + r.n + ' tablo kaydedildi.</b> Bekleyen arşiv soruları eşleştiriliyor…'; return bekleyenEslestir(dns).then(function (x) { m.innerHTML = '<b style="color:#059669">✓ ' + r.n + ' tablo kaydedildi. Beklemedeki ' + x.n + ' arşiv sorusu yeni çıktılarla eşleştirildi (' + x.on + ' onaylı, kalanlar “İnceleme”de).</b> Yazılı Hazırla’da hemen kullanılabilir; “🤖 Yapay zekâ ile doldur” sekmesinden bu dönem için soru üretebilirsiniz.'; }); }).catch(function (er) { k.disabled = false; alert(er.message); }); };
      });
    };
  }
  var BILINEN_DERS = ['Türkçe', 'Matematik', 'Fen Bilimleri', 'Sosyal Bilgiler', 'İngilizce', 'Din Kültürü ve Ahlak Bilgisi', 'T.C. İnkılap Tarihi ve Atatürkçülük', 'Türk Dili ve Edebiyatı', 'Fizik', 'Kimya', 'Biyoloji', 'Tarih', 'Coğrafya', 'Felsefe'];
  function trKucuk(s) { return String(s || '').replace(/I/g, 'ı').replace(/İ/g, 'i').toLowerCase().replace(/\s+/g, ' ').trim(); }
  function dersAdi(baslik, dosya) {
    var b = trKucuk(baslik).replace(/^\s*\d+\s*\.?\s*sınıf\s*/, '').replace(/\s*(dersi|dersı)\s*$/, '').trim(), f = trKucuk(dosya);
    var l = SNR.tablolar.map(function (t) { return t.ders; }).concat(BILINEN_DERS); l = l.filter(function (x, i) { return l.indexOf(x) === i; }).sort(function (a, c) { return c.length - a.length; });
    for (var i = 0; i < l.length; i++) if (b === trKucuk(l[i])) return l[i];
    for (i = 0; i < l.length; i++) if (b.indexOf(trKucuk(l[i])) >= 0) return l[i];
    var es = { 'edebiyat': 'Türk Dili ve Edebiyatı', 'turkce': 'Türkçe', 'mat': 'Matematik', 'fen': 'Fen Bilimleri', 'sosyal': 'Sosyal Bilgiler', 'ingilizce': 'İngilizce', 'dkab': 'Din Kültürü ve Ahlak Bilgisi', 'fizik': 'Fizik', 'kimya': 'Kimya', 'biyoloji': 'Biyoloji', 'tarih': 'Tarih', 'cografya': 'Coğrafya', 'felsefe': 'Felsefe', 'inkilap': 'T.C. İnkılap Tarihi ve Atatürkçülük' };
    for (var k in es) if (f.indexOf(k) >= 0 || b.indexOf(k) >= 0) return es[k];
    return b ? b.replace(/(^|\s)(\S)/g, function (m, a, c) { return a + c.toLocaleUpperCase('tr-TR'); }) : '';
  }
  /** MEB konu-soru dağılım Excel'i → senaryo tabloları (sunucudaki ayıklayıcının tarayıcı sürümü) */
  function xlsxSenaryo(ad, buf) {
    return JSZip.loadAsync(buf).then(function (z) {
      var oku = function (p) { var f = z.file(p); return f ? f.async('string') : Promise.resolve(''); };
      var px = function (s) { return new DOMParser().parseFromString(s, 'application/xml'); };
      return Promise.all([oku('xl/workbook.xml'), oku('xl/_rels/workbook.xml.rels'), oku('xl/sharedStrings.xml')]).then(function (r) {
        var wb = px(r[0]), rels = px(r[1]), ss = [];
        if (r[2]) [].forEach.call(px(r[2]).getElementsByTagName('si'), function (si) { ss.push([].map.call(si.getElementsByTagName('t'), function (t) { return t.textContent; }).join('')); });
        var hedef = {}; [].forEach.call(rels.getElementsByTagName('Relationship'), function (x) { hedef[x.getAttribute('Id')] = x.getAttribute('Target'); });
        var sh = [].map.call(wb.getElementsByTagName('sheet'), function (s) { var t = hedef[s.getAttribute('r:id')] || hedef[s.getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id')] || ''; return { ad: s.getAttribute('name'), yol: 'xl/' + t.replace(/^\/?xl\//, '') }; });
        var tablolar = [], uyari = [], p = Promise.resolve();
        sh.forEach(function (s) { p = p.then(function () { return oku(s.yol); }).then(function (x) { if (!x) return; var r2 = sayfaOku(px(x), ss, s.ad, ad); if (r2.hata) uyari.push(ad + ' · ' + s.ad + ': ' + r2.hata); else { r2.t._kaynak = ad + ' · ' + s.ad; tablolar.push(r2.t); } }); });
        return p.then(function () { return { tablolar: tablolar, uyari: uyari }; });
      });
    });
  }
  function sayfaOku(doc, ss, sayfaAd, dosya) {
    var V = {}, maxR = 0, maxC = 0, harf = function (s) { var n = 0; for (var i = 0; i < s.length; i++) n = n * 26 + s.charCodeAt(i) - 64; return n; };
    [].forEach.call(doc.getElementsByTagName('c'), function (c) {
      var m = (c.getAttribute('r') || '').match(/^([A-Z]+)(\d+)$/); if (!m) return;
      var t = c.getAttribute('t'), v = c.getElementsByTagName('v')[0], val = null;
      if (t === 's' && v) val = ss[+v.textContent]; else if (t === 'inlineStr') val = [].map.call(c.getElementsByTagName('t'), function (x) { return x.textContent; }).join(''); else if (v) val = t === 'str' ? v.textContent : (isNaN(+v.textContent) ? v.textContent : +v.textContent);
      if (val === null || val === '') return;
      var R = +m[2], C = harf(m[1]); V[R + ',' + C] = val; maxR = Math.max(maxR, R); maxC = Math.max(maxC, C);
    });
    var span = {};
    [].forEach.call(doc.getElementsByTagName('mergeCell'), function (mc) {
      var m = (mc.getAttribute('ref') || '').match(/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/); if (!m) return;
      var r1 = +m[2], c1 = harf(m[1]), r2 = +m[4], c2 = harf(m[3]), v = V[r1 + ',' + c1];
      for (var r = r1; r <= r2; r++) for (var c = c1; c <= c2; c++) span[r + ',' + c] = v;
    });
    var tx = function (v) { return v == null ? '' : String(v).replace(/\s+/g, ' ').trim(); };
    var g = function (r, c) { var k = r + ',' + c; return k in V ? V[k] : span[k]; };
    var sm = String(sayfaAd).match(/(\d+)/); if (!sm) return { hata: 'sayfa adında sınıf yok, atlandı' };
    var sinif = +sm[1], baslik = '', donem = 0;
    for (var r = 1; r <= 4; r++) for (var c = 1; c <= Math.min(maxC, 6); c++) { var x = tx(V[r + ',' + c]); if (!baslik && /sınıf|sinif/i.test(x)) baslik = x; var dm = trKucuk(x).match(/(\d)\.\s*dönem/); if (dm && !donem) donem = +dm[1]; }
    var hr = 0;
    for (r = 1; r <= 15 && !hr; r++) for (c = 1; c <= maxC; c++) if (/^senaryo\s*\d+/i.test(tx(V[r + ',' + c])) || /ülke geneli/i.test(trKucuk(tx(V[r + ',' + c])))) { hr = r; break; }
    if (!hr) return { hata: 'başlık (Senaryo 1 …) bulunamadı' };
    var kol = [];
    for (c = 1; c <= maxC; c++) {
      var h = tx(g(hr, c)), ust = [hr - 2, hr - 1].filter(function (x) { return x > 0; }).map(function (x) { return tx(g(x, c)); }).join(' '), lu = trKucuk(ust + ' ' + h);
      if (/^senaryo\s*\d+/i.test(h) || /ülke geneli/.test(trKucuk(h))) {
        var yz = /2\.\s*ortak|2\.\s*yazılı|2\.\s*sınav/.test(lu) ? 2 : 1, dm2 = lu.match(/(\d)\.\s*dönem/); if (dm2 && !donem) donem = +dm2[1];
        kol.push({ c: c, y: yz, s: /ülke geneli/.test(trKucuk(h)) ? 'ulke' : +h.match(/(\d+)/)[1] });
      }
    }
    if (!kol.length) return { hata: 'senaryo sütunu yok' };
    var ilk = Math.min.apply(null, kol.map(function (k) { return k.c; })), rol = {};
    for (c = 1; c < ilk; c++) {
      var hh = trKucuk([hr - 2, hr - 1, hr].filter(function (x) { return x > 0; }).map(function (x) { return tx(g(x, c)); }).join(' '));
      rol[c] = /çıktı|kazanım/.test(hh) ? 'cikti' : (/alt öğrenme|konu|içerik/.test(hh) ? 'konu' : (/ünite|tema|öğrenme alanı|beceri|alan/.test(hh) ? 'unite' : 'diger'));
    }
    if (!Object.keys(rol).some(function (k) { return rol[k] === 'cikti'; })) rol[ilk - 1] = 'cikti';
    var satirlar = [];
    for (r = hr + 1; r <= maxR; r++) {
      var d = { unite: '', konu: '', cikti: '' }, tum = '';
      for (c = 1; c < ilk; c++) { var v = tx(g(r, c)); tum += ' ' + v; if (rol[c] in d) d[rol[c]] = v; }
      if (!d.cikti || /toplam/i.test(tum)) continue;
      var sayi = {};
      kol.forEach(function (k) { var v2 = V[r + ',' + k.c]; var n = typeof v2 === 'number' ? v2 : parseInt(tx(v2).replace(',', '.'), 10); if (n > 0) { (sayi[k.y] = sayi[k.y] || {})[k.s] = Math.round(n); } });
      var m2 = d.cikti.match(/^\s*((?:[A-ZÇĞİÖŞÜ]{1,4}\.)*[A-ZÇĞİÖŞÜ]{0,3}\d+(?:\.\d+)*(?:\.[A-Z]{1,2}\d+)?)\.?\s*(.*)$/);
      if (m2 && m2[1].indexOf('.') < 0) m2 = null;
      var kod = m2 ? m2[1] : '', metin = m2 ? m2[2] : d.cikti;
      if (!kod && d.konu) metin = d.konu + (/bk\./.test(d.cikti) ? ' (' + d.cikti + ')' : '');
      satirlar.push({ unite: d.unite, konu: d.konu, kod: kod, cikti: metin, sayi: sayi });
    }
    if (!satirlar.length) return { hata: 'öğrenme çıktısı satırı bulunamadı' };
    var snr = { '1': [], '2': [] }, ulke = { '1': false, '2': false };
    kol.forEach(function (k) { if (k.s === 'ulke') ulke[k.y] = true; else if (snr[k.y].indexOf(k.s) < 0) snr[k.y].push(k.s); });
    var ders = dersAdi(baslik, dosya);
    if (!ders) return { hata: 'ders adı anlaşılamadı' };
    return { t: { kademe: sinif <= 8 ? 'ortaokul' : 'lise', ders: ders, sinif: sinif, donem: donem || 2, baslik: sayfaAd, senaryolar: snr, ulke: ulke, satirlar: satirlar } };
  }
  window.OSC_HV_ESLESTIR = eslestir; window.OSC_HV_META = metaTahmin;
  ciz();
})();
