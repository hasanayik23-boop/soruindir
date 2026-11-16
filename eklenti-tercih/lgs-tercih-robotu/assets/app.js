/* LGS Tercih Robotu — Soruindir */
(function () {
  'use strict';
  var CFG = window.LGSTR || {};
  var root = document.getElementById('lgstr-app');
  if (!root) return;

  /* ---------- yardımcılar ---------- */
  var TR = 'tr-TR';
  function el(tag, attrs, html) { var e = document.createElement(tag); if (attrs) for (var k in attrs) { if (k === 'class') e.className = attrs[k]; else if (k === 'text') e.textContent = attrs[k]; else e.setAttribute(k, attrs[k]); } if (html != null) e.innerHTML = html; return e; }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function fmt(n, d) { if (n == null || isNaN(n)) return '–'; return Number(n).toLocaleString(TR, { minimumFractionDigits: d, maximumFractionDigits: d }); }
  function lower(s) { return String(s || '').replace(/I/g, 'ı').replace(/İ/g, 'i').toLowerCase(); }
  function norm(s) { return lower(s).replace(/ç/g, 'c').replace(/ğ/g, 'g').replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ş/g, 's').replace(/ü/g, 'u'); }
  function store(k, v) { try { if (v === undefined) return JSON.parse(localStorage.getItem('lgstr_' + k) || 'null'); localStorage.setItem('lgstr_' + k, JSON.stringify(v)); } catch (e) { return null; } }
  var ICO = {
    star: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg>',
    info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>',
    up: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg>',
    down: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>',
    x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>',
    bed: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 18V7M3 14h18v4M21 14v-2a3 3 0 0 0-3-3h-7v5"/><circle cx="7" cy="11" r="2"/></svg>',
    pin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>'
  };

  /* ---------- durum ---------- */
  var S = {
    data: null, detay: null, detayYukleniyor: null,
    puan: null, yuzde: null, kaynak: null,       // kaynak: 'belge' | 'net'
    iller: store('iller') || [], turler: store('turler') || [], pansiyon: false, dil: '', ara: '',
    kapsam: 'uygun', sirala: 'taban', goster: 30,
    tercih: store('tercih') || []                 // [programId, ...]
  };
  var MAX_TERCIH = 10;

  /* ---------- puan modeli ---------- */
  var DERSLER = [
    { k: 'tr', ad: 'Türkçe', soru: 20 }, { k: 'mat', ad: 'Matematik', soru: 20 }, { k: 'fen', ad: 'Fen Bilimleri', soru: 20 },
    { k: 'ink', ad: 'T.C. İnkılap Tarihi', soru: 10 }, { k: 'din', ad: 'Din Kültürü', soru: 10 }, { k: 'ing', ad: 'Yabancı Dil', soru: 10 }
  ];
  function tahminPuan(nets) {
    var m = S.data.model, p = m.sabit;
    DERSLER.forEach(function (d) { p += (m.katsayi[d.k] || 0) * (nets[d.k] || 0); });
    return Math.max(100, Math.min(500, p));
  }
  /* puan -> yüzdelik: okulların (taban puan, yüzdelik) çiftlerinden oluşturulmuş eğri; aradaki değerler logaritmik doğrusal */
  function tahminYuzde(p) {
    var c = S.data.egri; if (!c || !c.length) return null;
    if (p >= c[0][0]) return c[0][1];
    for (var i = 1; i < c.length; i++) {
      if (p >= c[i][0]) { var a = c[i - 1], b = c[i], t = (p - b[0]) / (a[0] - b[0]); return Math.exp(Math.log(b[1]) + t * (Math.log(a[1]) - Math.log(b[1]))); }
    }
    return c[c.length - 1][1];
  }

  /* ---------- program listesi ---------- */
  var PROG = [], PROG_BY_ID = {};
  var VAR_YUZDE = false, VAR_PANS = false;
  function hazirla() {
    var d = S.data;
    d.okullar.forEach(function (o) { if (o.pans) VAR_PANS = true; o.programlar.forEach(function (p) { if (p.y25 != null) VAR_YUZDE = true; }); });
    d.okullar.forEach(function (o) {
      o.programlar.forEach(function (p, i) {
        var pr = { id: o.k + '-' + i, o: o, p: p, taban: p.t26 != null ? p.t26 : p.t25, tabanYil: p.t26 != null ? d.meta.tabanYili : d.meta.oncekiYil, ara: norm(o.ad + ' ' + o.il + ' ' + o.ilce + ' ' + (p.alan || '')) };
        PROG.push(pr); PROG_BY_ID[pr.id] = pr;
      });
    });
    S.tercih = S.tercih.filter(function (id) { return PROG_BY_ID[id]; });
    var q = new URLSearchParams(location.search).get('tercih');
    if (q) { var ids = q.split(',').filter(function (id) { return PROG_BY_ID[id]; }).slice(0, MAX_TERCIH); if (ids.length) { S.tercih = ids; store('tercih', ids); } }
    var sp = store('puan'); if (sp && sp.puan) { S.puan = sp.puan; S.yuzde = sp.yuzde; S.kaynak = sp.kaynak; }
  }

  function durum(pr) {
    if (S.puan == null || pr.taban == null) return null;
    var f = S.puan - pr.taban;
    if (f >= 15) return { c: 'ok', ad: 'Yüksek ihtimal', f: f };
    if (f >= 0) return { c: 'iyi', ad: 'Muhtemel', f: f };
    if (f >= -10) return { c: 'sinir', ad: 'Sınırda', f: f };
    return { c: 'dusuk', ad: 'Düşük ihtimal', f: f };
  }

  function filtrele() {
    var q = norm(S.ara.trim());
    var out = PROG.filter(function (pr) {
      if (S.iller.length && S.iller.indexOf(pr.o.il) < 0) return false;
      if (S.turler.length && S.turler.indexOf(pr.o.grup) < 0) return false;
      if (S.pansiyon && !pr.o.pans) return false;
      if (S.dil && pr.p.dil !== S.dil) return false;
      if (q && pr.ara.indexOf(q) < 0) return false;
      if (S.kapsam === 'uygun' && S.puan != null) { if (pr.taban == null || S.puan - pr.taban < -10) return false; }
      return true;
    });
    out.sort(function (a, b) {
      if (S.sirala === 'yakin' && S.puan != null) return Math.abs(S.puan - (a.taban || 0)) - Math.abs(S.puan - (b.taban || 0));
      if (S.sirala === 'ad') return a.o.ad.localeCompare(b.o.ad, TR);
      return (b.taban || 0) - (a.taban || 0);
    });
    return out;
  }

  /* ---------- arayüz iskeleti ---------- */
  var ICO_UY = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 2 20h20L12 3z"/><path d="M12 10v4M12 17h.01"/></svg>';
  function uyari(baslik, metin, cls) { return '<div class="lgstr-uyari' + (cls ? ' ' + cls : '') + '" role="note">' + ICO_UY + '<div><b>' + baslik + '</b><span>' + metin + '</span></div></div>'; }
  var RENK = { tr: '#E0457B', mat: '#1E5AA8', fen: '#0F7B4B', ink: '#B5641B', din: '#6D4AFF', ing: '#0E8FA8' };
  function iskelet() {
    var m = S.data.meta;
    root.innerHTML = '' +
      '<div class="lgstr-hero">' +
        '<div class="lgstr-hero-in">' +
          '<p class="lgstr-kicker">' + esc(m.tabanYili) + ' LGS · Resmî MEB taban puanlarıyla</p>' +
          '<h1>' + esc(CFG.baslik || 'LGS Tercih Robotu') + '</h1>' +
          '<p class="lgstr-lead">Netlerinden puanını hesapla, puanınla yerleşebileceğin liseleri gör ve kendi tercih listeni oluştur.</p>' +
          '<ul class="lgstr-stats"><li><b>' + fmt(m.okulSayisi, 0) + '</b><span>okul</span></li><li><b>' + fmt(m.programSayisi, 0) + '</b><span>okul ve alan</span></li><li><b>' + fmt(S.data.iller.length, 0) + '</b><span>il</span></li><li><b>' + MAX_TERCIH + '</b><span>tercih hakkı</span></li></ul>' +
        '</div>' +
        '<ol class="lgstr-steps" role="list">' +
          '<li><button type="button" data-git="puan"><span>1</span><b>Puanını hesapla</b></button></li>' +
          '<li><button type="button" data-git="okullar"><span>2</span><b>Okulları keşfet</b></button></li>' +
          '<li><button type="button" data-git="tercih"><span>3</span><b>Tercih listen</b><i id="lgstr-step-n">0</i></button></li>' +
        '</ol>' +
      '</div>' +

      /* 1. adım: puan hesaplama */
      '<section class="lgstr-view" data-view="puan" aria-labelledby="lgstr-p-h">' +
        '<div class="lgstr-calc">' +
          '<div class="lgstr-card lgstr-calc-form">' +
            '<div class="lgstr-calc-h"><h2 id="lgstr-p-h">Puanını hesapla</h2><p>Doğru ve yanlış sayılarını yaz, puanın anında hesaplansın. Sonuç belgen açıklandıysa kesin puanını da girebilirsin.</p></div>' +
            uyari('Hesaplanan puan tahminidir.', 'MEB puanı, o yıl sınava giren tüm öğrencilerin ortalamalarına göre hesaplar. Kesin puanın MEB sonuç belgende yazar.') +
            '<div class="lgstr-tabs" role="tablist">' +
              '<button type="button" role="tab" data-tab="net" aria-selected="true">Netlerimden hesapla</button>' +
              '<button type="button" role="tab" data-tab="belge" aria-selected="false">Sonuç belgemdeki puan</button>' +
            '</div>' +
            '<div class="lgstr-tab" data-pane="net">' +
              '<div class="lgstr-dersler" id="lgstr-dersler">' +
              DERSLER.map(function (d) {
                return '<div class="lgstr-ders" data-ders="' + d.k + '" style="--c:' + RENK[d.k] + '">' +
                  '<div class="lgstr-ders-ad"><b>' + d.ad + '</b><em>' + d.soru + ' soru</em></div>' +
                  '<label class="lgstr-in"><span>Doğru</span><input type="number" min="0" max="' + d.soru + '" inputmode="numeric" data-f="d" placeholder="0" aria-label="' + d.ad + ' doğru sayısı (' + d.soru + ' soru)"></label>' +
                  '<label class="lgstr-in"><span>Yanlış</span><input type="number" min="0" max="' + d.soru + '" inputmode="numeric" data-f="y" placeholder="0" aria-label="' + d.ad + ' yanlış sayısı"></label>' +
                  '<div class="lgstr-ders-net"><span>Net</span><b class="lgstr-netv">0</b></div>' +
                  '<div class="lgstr-ders-bar" aria-hidden="true"><i></i></div>' +
                '</div>';
              }).join('') +
              '</div>' +
              '<p class="lgstr-err" id="lgstr-net-err" hidden></p>' +
              '<div class="lgstr-calc-foot"><span>3 yanlış 1 doğruyu götürür · Toplam 90 soru</span><button type="button" class="lgstr-link" id="lgstr-net-sil">Temizle</button></div>' +
            '</div>' +
            '<div class="lgstr-tab" data-pane="belge" hidden>' +
              '<div class="lgstr-fields">' +
                '<label><span>LGS puanı</span><input type="text" inputmode="decimal" id="lgstr-puan" placeholder="Örn: 412,357"></label>' +
                '<label><span>Yüzdelik dilim (isteğe bağlı)</span><input type="text" inputmode="decimal" id="lgstr-yuzde" placeholder="Örn: 8,42"></label>' +
              '</div>' +
              '<p class="lgstr-hint">Puanın ve yüzdelik dilimin MEB\'in sonuç belgesinde yazar. Bu değerler kesindir; okullar puanına göre karşılaştırılır.</p>' +
            '</div>' +
          '</div>' +
          '<aside class="lgstr-result" id="lgstr-sonuc" aria-live="polite"></aside>' +
        '</div>' +
        uyari('Sonuçlar bilgilendirme amaçlıdır.', 'Netlerden hesaplanan puan ve yerleşme ihtimalleri tahminidir; gerçek puanın birkaç puan farklı çıkabilir. Okullar önceki yılların taban puanlarıyla karşılaştırılır ve taban puanlar her yıl değişir.', 'lgstr-uyari--alt') +
      '</section>' +

      /* 2. adım: okullar */
      '<section class="lgstr-view" data-view="okullar" hidden>' +
        '<div class="lgstr-ozet" id="lgstr-ozet"></div>' +
        uyari('Yerleşme ihtimalleri tahminidir.', 'Okullar ' + esc(m.tabanYili) + ' ve ' + esc(m.oncekiYil) + ' taban puanlarıyla karşılaştırılır. Bu yılın taban puanları kontenjana ve tercihlere göre değişebilir.', 'lgstr-uyari--ust') +
        '<div class="lgstr-grid">' +
          '<div class="lgstr-main">' +
            '<section class="lgstr-card lgstr-filtre" aria-labelledby="lgstr-f-h">' +
              '<h2 id="lgstr-f-h">Okulları süz</h2>' +
              '<div class="lgstr-frow">' +
                '<div class="lgstr-il"><span class="lgstr-lbl">İller</span><div class="lgstr-ilbox"><div class="lgstr-chips" id="lgstr-ilchips"></div><input type="text" id="lgstr-ilara" placeholder="İl ekle (boşsa tüm Türkiye)" autocomplete="off" aria-label="İl ara ve ekle"><ul class="lgstr-ilmenu" id="lgstr-ilmenu" hidden></ul></div></div>' +
                '<label class="lgstr-ara"><span class="lgstr-lbl">Okul ara</span><input type="search" id="lgstr-ara" placeholder="Okul adı ya da ilçe"></label>' +
              '</div>' +
              '<div class="lgstr-turler" id="lgstr-turler"></div>' +
              '<div class="lgstr-frow lgstr-frow--sm">' +
                (VAR_PANS ? '<label class="lgstr-check"><input type="checkbox" id="lgstr-pans"> Pansiyonlu okullar</label>' : '') +
                '<label><span class="lgstr-lbl">Yabancı dil</span><select id="lgstr-dil"><option value="">Tümü</option></select></label>' +
                '<label><span class="lgstr-lbl">Göster</span><select id="lgstr-kapsam"><option value="uygun">Puanımla yerleşebileceklerim</option><option value="hepsi">Tüm okullar</option></select></label>' +
                '<label><span class="lgstr-lbl">Sırala</span><select id="lgstr-sirala"><option value="taban">Taban puan (yüksekten)</option><option value="yakin">Puanıma en yakın</option><option value="ad">Okul adı</option></select></label>' +
              '</div>' +
            '</section>' +
            '<section aria-labelledby="lgstr-r-h"><h2 id="lgstr-r-h" class="lgstr-rh"></h2><ol class="lgstr-list" id="lgstr-list"></ol><button type="button" class="lgstr-btn lgstr-btn--ghost" id="lgstr-more" hidden>Daha fazla göster</button>' +
              uyari('Bu liste bir tahmindir, kesin sonuç değildir.', 'Tercihlerini e-Okul tercih ekranından ve MEB\'in yayımladığı güncel tercih kılavuzuna göre yap. Listene puanına göre daha güvenli okullar eklemeyi unutma.', 'lgstr-uyari--alt') +
            '</section>' +
          '</div>' +
          '<aside class="lgstr-side" id="lgstr-side" aria-labelledby="lgstr-t-h"></aside>' +
        '</div>' +
      '</section>' +
      '<p class="lgstr-src">' + ICO.info + '<span>' + kaynakMetni() + '</span></p>' +
      '<div class="lgstr-modal" id="lgstr-modal" hidden><div class="lgstr-modal-bg" data-close></div><div class="lgstr-modal-box" role="dialog" aria-modal="true" aria-labelledby="lgstr-m-h"><button type="button" class="lgstr-modal-x" data-close aria-label="Kapat">' + ICO.x + '</button><div id="lgstr-modal-body"></div></div></div>' +
      '<button type="button" class="lgstr-fab" id="lgstr-fab" hidden></button>' +
      '<div class="lgstr-mbar" id="lgstr-mbar" hidden></div>';

    var turler = S.data.gruplar;
    document.getElementById('lgstr-turler').innerHTML = turler.map(function (t) { return '<label class="lgstr-tur"><input type="checkbox" value="' + esc(t) + '"' + (S.turler.indexOf(t) >= 0 ? ' checked' : '') + '><span>' + esc(t) + '</span></label>'; }).join('');
    var dilSel = document.getElementById('lgstr-dil');
    S.data.diller.forEach(function (d) { dilSel.appendChild(el('option', { value: d, text: d })); });
    // kayıtlı değerler
    var kn = store('netler');
    if (kn) root.querySelectorAll('.lgstr-ders').forEach(function (r) { var v = kn[r.getAttribute('data-ders')]; if (v) { if (v[0]) r.querySelector('[data-f=d]').value = v[0]; if (v[1]) r.querySelector('[data-f=y]').value = v[1]; } });
    if (S.kaynak === 'belge' && S.puan != null) { document.getElementById('lgstr-puan').value = fmt(S.puan, 3).replace(/\./g, ''); if (S.yuzde != null) document.getElementById('lgstr-yuzde').value = fmt(S.yuzde, 2); sekme('belge'); }
    olaylar();
    netHesap(); sonucCiz();
    ozetCiz(); ilCiz(); listeCiz(); tercihCiz();
    goster(location.hash === '#okullar' && S.puan != null ? 'okullar' : 'puan', true);
  }

  function kaynakMetni() {
    var m = S.data.meta;
    return 'Taban puanlar MEB e-Okul\'un resmî "Boş Kontenjan ve Taban Puan Bilgileri" ekranından alındı: ' + esc(m.tabanYili) + ' ve ' + esc(m.oncekiYil) + ' merkezi yerleştirme sonuçları' + (VAR_YUZDE && m.yuzdelikYili ? ', ' + esc(m.yuzdelikYili) + ' yüzdelik dilimleriyle' : '') + '. Son güncelleme: ' + esc(m.olusturma) + '.';
  }

  function sayi(v) { v = String(v || '').trim().replace(/\s/g, ''); if (!v) return null; if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.'); var n = parseFloat(v); return isNaN(n) ? null : n; }

  /* ---------- görünüm ---------- */
  S.view = 'puan'; S.sekme = 'net';
  function goster(v, ilk) {
    S.view = v;
    root.querySelectorAll('.lgstr-view').forEach(function (x) { x.hidden = x.getAttribute('data-view') !== v; });
    root.querySelectorAll('.lgstr-steps [data-git]').forEach(function (b) {
      var g = b.getAttribute('data-git'), on = g === v || (g === 'tercih' && false);
      b.classList.toggle('is-on', on); b.classList.toggle('is-done', v === 'okullar' && g === 'puan' && S.puan != null);
      if (on) b.setAttribute('aria-current', 'step'); else b.removeAttribute('aria-current');
    });
    var fab = document.getElementById('lgstr-fab'); if (fab) fab.hidden = v !== 'okullar';
    mbarCiz();
    if (!ilk) { var top = root.getBoundingClientRect().top + window.pageYOffset - 90; if (window.pageYOffset > top) window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' }); }
    try { history.replaceState(null, '', v === 'okullar' ? '#okullar' : location.pathname + location.search); } catch (e) {}
  }
  function sekme(t) {
    S.sekme = t;
    root.querySelectorAll('.lgstr-tabs [data-tab]').forEach(function (x) { x.setAttribute('aria-selected', x.getAttribute('data-tab') === t ? 'true' : 'false'); });
    root.querySelectorAll('[data-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== t; });
  }

  /* ---------- puan hesabı ---------- */
  var NET = null, NET_OK = true, NET_TOP = 0, NET_DOLU = false;
  function netHesap() {
    var tot = 0, err = '', nets = {}, dolu = false, kay = {};
    root.querySelectorAll('.lgstr-ders').forEach(function (r) {
      var k = r.getAttribute('data-ders'), dd = DERSLER.filter(function (d) { return d.k === k; })[0];
      var di = r.querySelector('[data-f=d]'), yi = r.querySelector('[data-f=y]');
      var d = parseInt(di.value, 10) || 0, y = parseInt(yi.value, 10) || 0;
      if (di.value !== '' || yi.value !== '') dolu = true;
      kay[k] = [di.value, yi.value];
      var hata = d < 0 || y < 0 || d + y > dd.soru;
      if (hata && !err) err = dd.ad + ' için doğru ve yanlış toplamı ' + dd.soru + ' soruyu geçemez.';
      r.classList.toggle('is-err', hata);
      var n = hata ? 0 : Math.max(0, d - y / 3);
      nets[k] = n; tot += n;
      r.querySelector('.lgstr-netv').textContent = fmt(n, 2);
      r.querySelector('.lgstr-ders-bar i').style.width = (n / dd.soru * 100) + '%';
    });
    store('netler', kay);
    var e = document.getElementById('lgstr-net-err'); e.hidden = !err; e.textContent = err;
    NET = nets; NET_OK = !err; NET_TOP = tot; NET_DOLU = dolu;
    return !err;
  }
  function aktifPuan() {
    if (S.sekme === 'belge') {
      var p = sayi(document.getElementById('lgstr-puan').value), y = sayi(document.getElementById('lgstr-yuzde').value);
      if (p == null || p < 100 || p > 500) return null;
      return { puan: p, yuzde: (y != null && y > 0 && y <= 100) ? y : null, kaynak: 'belge' };
    }
    if (!NET_DOLU || !NET_OK) return null;
    var tp = tahminPuan(NET);
    return { puan: tp, yuzde: tahminYuzde(tp), kaynak: 'net' };
  }
  function sonucCiz() {
    var box = document.getElementById('lgstr-sonuc'), r = aktifPuan(), m = S.data.model;
    var R = 64, C = 2 * Math.PI * R, oran = r ? Math.max(0, Math.min(1, (r.puan - 100) / 400)) : 0;
    var halka = '<svg class="lgstr-ring" viewBox="0 0 160 160" aria-hidden="true"><circle cx="80" cy="80" r="' + R + '" class="lgstr-ring-bg"/><circle cx="80" cy="80" r="' + R + '" class="lgstr-ring-fg" stroke-dasharray="' + C.toFixed(1) + '" stroke-dashoffset="' + (C * (1 - oran)).toFixed(1) + '"/></svg>';
    var uygun = 0;
    if (r) PROG.forEach(function (pr) { if (pr.taban != null && r.puan >= pr.taban) uygun++; });
    box.innerHTML = '<p class="lgstr-result-k">' + (r ? (r.kaynak === 'net' ? 'Tahmini LGS puanın' : 'LGS puanın') : 'LGS puanın') + '</p>' +
      '<div class="lgstr-gauge">' + halka + '<div class="lgstr-gauge-v"><strong>' + (r ? fmt(r.puan, 3) : '—') + '</strong><span>500 üzerinden</span></div></div>' +
      '<dl class="lgstr-result-d">' +
        (S.sekme === 'net' ? '<div><dt>Toplam net</dt><dd>' + fmt(NET_TOP, 2) + ' <small>/ 90</small></dd></div>' : '') +
        (r && r.yuzde != null ? '<div><dt>' + (r.kaynak === 'net' ? 'Tahmini yüzdelik' : 'Yüzdelik dilim') + '</dt><dd>%' + fmt(r.yuzde, 2) + '</dd></div>' : '') +
        (r ? '<div><dt>Puanının yettiği okul ve alan</dt><dd>' + fmt(uygun, 0) + ' <small>/ ' + fmt(PROG.length, 0) + '</small></dd></div>' : '') +
      '</dl>' +
      '<button type="button" class="lgstr-btn lgstr-btn--cta" data-act="okullara"' + (r ? '' : ' disabled') + '>' + (r ? 'Bu puanla girebileceğin okulları gör' : (S.sekme === 'net' ? 'Netlerini girince okullar açılır' : 'Puanını girince okullar açılır')) + ' <span aria-hidden="true">→</span></button>' +
      (r && r.kaynak === 'net' ? '<p class="lgstr-result-n">Bu bir tahmindir. MEB puanı, o yıl sınava giren tüm öğrencilerin ortalamalarına göre hesaplar. ' + esc(m.aciklama || '') + '</p>' : '<p class="lgstr-result-n">Okullar ' + esc(S.data.meta.tabanYili) + ' ve ' + esc(S.data.meta.oncekiYil) + ' merkezi yerleştirme taban puanlarıyla karşılaştırılır.</p>');
    mbarCiz();
  }

  var SONUC_GORUNUR = false;
  function mbarCiz() {
    var b = document.getElementById('lgstr-mbar'); if (!b) return;
    var r = aktifPuan();
    var goster_ = S.view === 'puan' && root.classList.contains('lgstr-w-md') && !SONUC_GORUNUR;
    b.hidden = !goster_;
    if (!goster_) return;
    b.innerHTML = '<div><span>' + (r && r.kaynak === 'net' ? 'Tahmini puan' : 'Puan') + '</span><strong>' + (r ? fmt(r.puan, 3) : '—') + '</strong></div>' +
      '<button type="button" class="lgstr-btn lgstr-btn--cta" data-act="okullara"' + (r ? '' : ' disabled') + '>Okulları gör <span aria-hidden="true">→</span></button>';
  }

  function olaylar() {
    var $ = function (id) { return document.getElementById(id); };
    if (window.IntersectionObserver) new IntersectionObserver(function (es) { SONUC_GORUNUR = es[0].isIntersecting; mbarCiz(); }, { threshold: 0.25 }).observe($('lgstr-sonuc'));
    root.querySelectorAll('.lgstr-tabs [data-tab]').forEach(function (b) {
      b.addEventListener('click', function () { sekme(b.getAttribute('data-tab')); sonucCiz(); });
    });
    $('lgstr-dersler').addEventListener('input', function () { netHesap(); sonucCiz(); });
    $('lgstr-net-sil').addEventListener('click', function () { root.querySelectorAll('.lgstr-ders input').forEach(function (i) { i.value = ''; }); netHesap(); sonucCiz(); });
    $('lgstr-puan').addEventListener('input', sonucCiz);
    $('lgstr-yuzde').addEventListener('input', sonucCiz);
    root.querySelectorAll('.lgstr-steps [data-git]').forEach(function (b) {
      b.addEventListener('click', function () {
        var g = b.getAttribute('data-git');
        if (g === 'puan') return goster('puan');
        goster('okullar');
        if (g === 'tercih') { if (root.classList.contains('lgstr-w-md')) { $('lgstr-side').classList.add('is-open'); document.body.classList.add('lgstr-lock'); } }
      });
    });
    // il seçici
    var ilAra = $('lgstr-ilara'), ilMenu = $('lgstr-ilmenu');
    function ilMenuCiz() {
      var q = norm(ilAra.value);
      var list = S.data.iller.filter(function (il) { return S.iller.indexOf(il) < 0 && (!q || norm(il).indexOf(q) === 0 || norm(il).indexOf(q) > 0); }).slice(0, 12);
      ilMenu.innerHTML = list.map(function (il, i) { return '<li role="option" data-il="' + esc(il) + '"' + (i === 0 ? ' class="is-act"' : '') + '>' + esc(il) + '</li>'; }).join('');
      ilMenu.hidden = !list.length;
    }
    function ilEkle(il) { if (il && S.iller.indexOf(il) < 0) { S.iller.push(il); store('iller', S.iller); } ilAra.value = ''; ilMenu.hidden = true; ilCiz(); yenile(); }
    ilAra.addEventListener('focus', ilMenuCiz); ilAra.addEventListener('input', ilMenuCiz);
    ilAra.addEventListener('keydown', function (e) {
      var items = [].slice.call(ilMenu.querySelectorAll('li')), i = items.findIndex(function (x) { return x.classList.contains('is-act'); });
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); if (!items.length) return; items[i] && items[i].classList.remove('is-act'); i = (i + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length; items[i].classList.add('is-act'); }
      else if (e.key === 'Enter') { e.preventDefault(); if (items[i]) ilEkle(items[i].getAttribute('data-il')); }
      else if (e.key === 'Escape') ilMenu.hidden = true;
      else if (e.key === 'Backspace' && !ilAra.value && S.iller.length) { S.iller.pop(); store('iller', S.iller); ilCiz(); yenile(); }
    });
    ilMenu.addEventListener('mousedown', function (e) { var li = e.target.closest('li'); if (li) { e.preventDefault(); ilEkle(li.getAttribute('data-il')); } });
    document.addEventListener('click', function (e) { if (!e.target.closest('.lgstr-ilbox')) ilMenu.hidden = true; });
    $('lgstr-ilchips').addEventListener('click', function (e) { var b = e.target.closest('[data-rm]'); if (!b) return; S.iller = S.iller.filter(function (x) { return x !== b.getAttribute('data-rm'); }); store('iller', S.iller); ilCiz(); yenile(); });
    // diğer filtreler
    $('lgstr-turler').addEventListener('change', function () { S.turler = [].map.call(root.querySelectorAll('#lgstr-turler input:checked'), function (i) { return i.value; }); store('turler', S.turler); yenile(); });
    if ($('lgstr-pans')) $('lgstr-pans').addEventListener('change', function () { S.pansiyon = this.checked; yenile(); });
    $('lgstr-dil').addEventListener('change', function () { S.dil = this.value; yenile(); });
    $('lgstr-kapsam').addEventListener('change', function () { S.kapsam = this.value; yenile(); });
    $('lgstr-sirala').addEventListener('change', function () { S.sirala = this.value; yenile(); });
    var t; $('lgstr-ara').addEventListener('input', function () { var v = this.value; clearTimeout(t); t = setTimeout(function () { S.ara = v; yenile(); }, 200); });
    $('lgstr-more').addEventListener('click', function () { S.goster += 30; listeCiz(); });
    // liste ve tercih işlemleri
    root.addEventListener('click', function (e) {
      var b = e.target.closest('[data-act]'); if (!b) { if (e.target.closest('[data-close]')) modalKapat(); return; }
      var id = b.getAttribute('data-id'), act = b.getAttribute('data-act');
      if (act === 'okullara') { var ap = aktifPuan(); if (!ap) return; S.puan = ap.puan; S.yuzde = ap.yuzde; S.kaynak = ap.kaynak; puanKaydet(); return; }
      if (act === 'puana') { goster('puan'); return; }
      if (act === 'ekle') tercihDegis(id);
      else if (act === 'detay') detayAc(id);
      else if (act === 'yukari' || act === 'asagi') { var i = S.tercih.indexOf(id), j = i + (act === 'yukari' ? -1 : 1); if (j < 0 || j >= S.tercih.length) return; var x = S.tercih[i]; S.tercih[i] = S.tercih[j]; S.tercih[j] = x; store('tercih', S.tercih); tercihCiz(); }
      else if (act === 'cikar') tercihDegis(id);
      else if (act === 'temizle') { if (S.tercih.length && window.confirm('Tercih listen silinsin mi?')) { S.tercih = []; store('tercih', S.tercih); tercihCiz(); listeCiz(); } }
      else if (act === 'yazdir') yazdir();
      else if (act === 'paylas') paylas(b);
      else if (act === 'sirala-taban') { S.tercih.sort(function (a, c) { return (PROG_BY_ID[c].taban || 0) - (PROG_BY_ID[a].taban || 0); }); store('tercih', S.tercih); tercihCiz(); }
    });
    $('lgstr-fab').addEventListener('click', function () { $('lgstr-side').classList.add('is-open'); document.body.classList.add('lgstr-lock'); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { modalKapat(); $('lgstr-side').classList.remove('is-open'); document.body.classList.remove('lgstr-lock'); } });
  }

  function puanKaydet() { store('puan', { puan: S.puan, yuzde: S.yuzde, kaynak: S.kaynak }); S.kapsam = 'uygun'; document.getElementById('lgstr-kapsam').value = 'uygun'; ozetCiz(); yenile(); goster('okullar'); }

  function ozetCiz() {
    var o = document.getElementById('lgstr-ozet');
    if (S.puan == null) { o.innerHTML = '<div class="lgstr-ozet-in"><span>Puanını girersen okulların yanında yerleşme ihtimalini gösteririz.</span><button type="button" class="lgstr-btn lgstr-btn--sm" data-act="puana">Puanını hesapla</button></div>'; return; }
    o.innerHTML = '<div class="lgstr-ozet-in"><div class="lgstr-ozet-p"><span>' + (S.kaynak === 'net' ? 'Tahmini puanın' : 'Puanın') + '</span><strong>' + fmt(S.puan, 3) + '</strong></div>' +
      (S.yuzde != null ? '<div class="lgstr-ozet-p"><span>' + (S.kaynak === 'net' ? 'Tahmini yüzdelik' : 'Yüzdelik') + '</span><strong>%' + fmt(S.yuzde, 2) + '</strong></div>' : '') +
      '<em>' + (S.kaynak === 'net' ? 'netlerden tahmin' : 'sonuç belgesi') + '</em>' +
      '<button type="button" class="lgstr-btn lgstr-btn--sm lgstr-btn--ghost" data-act="puana">Puanı değiştir</button></div>';
  }

  function ilCiz() {
    document.getElementById('lgstr-ilchips').innerHTML = S.iller.map(function (il) { return '<span class="lgstr-chip">' + esc(il) + '<button type="button" data-rm="' + esc(il) + '" aria-label="' + esc(il) + ' ilini kaldır">' + ICO.x + '</button></span>'; }).join('');
  }

  function yenile() { S.goster = 30; listeCiz(); }

  function satir(pr, sira) {
    var o = pr.o, p = pr.p, dr = durum(pr), sec = S.tercih.indexOf(pr.id) >= 0;
    var tarih = [];
    if (p.t26 != null) tarih.push('<div><span>' + esc(S.data.meta.tabanYili) + ' taban</span><b>' + fmt(p.t26, 2) + '</b></div>');
    if (p.t25 != null) tarih.push('<div><span>' + esc(S.data.meta.oncekiYil) + ' taban</span><b>' + fmt(p.t25, 2) + '</b></div>');
    if (p.y25 != null) tarih.push('<div><span>' + esc(S.data.meta.yuzdelikYili) + ' yüzdelik</span><b>%' + fmt(p.y25, 2) + '</b></div>');
    return '<li class="lgstr-item' + (sec ? ' is-sel' : '') + '">' +
      '<div class="lgstr-item-main">' +
        '<h3><button type="button" data-act="detay" data-id="' + pr.id + '">' + esc(o.ad) + '</button></h3>' +
        '<p class="lgstr-meta"><span>' + ICO.pin + esc(o.il) + ' / ' + esc(o.ilce) + '</span><span>' + esc(p.alan || o.tur) + '</span>' + (p.dil ? '<span>' + esc(p.dil) + '</span>' : '') + (p.hz ? '<span>Hazırlık sınıflı</span>' : '') + (o.pans ? '<span class="lgstr-pans">' + ICO.bed + 'Pansiyon</span>' : '') + '</p>' +
      '</div>' +
      '<div class="lgstr-item-nums">' + tarih.join('') + '</div>' +
      '<div class="lgstr-item-act">' +
        (dr ? '<span class="lgstr-durum is-' + dr.c + '" title="Puanın ile taban puan farkı">' + dr.ad + '<small>' + (dr.f >= 0 ? '+' : '') + fmt(dr.f, 1) + '</small></span>' : '') +
        '<button type="button" class="lgstr-add' + (sec ? ' is-on' : '') + '" data-act="ekle" data-id="' + pr.id + '" aria-pressed="' + sec + '">' + ICO.star + '<span>' + (sec ? 'Listemde' : 'Tercih listeme ekle') + '</span></button>' +
      '</div>' +
    '</li>';
  }

  function listeCiz() {
    var res = filtrele(), h = document.getElementById('lgstr-r-h');
    var yer = S.iller.length ? (S.iller.length > 3 ? S.iller.length + ' ilde' : S.iller.join(', ') + ' ilinde') : 'Türkiye genelinde';
    var okulN = {}; res.forEach(function (pr) { okulN[pr.o.k] = 1; }); okulN = Object.keys(okulN).length;
    var say = '<b>' + fmt(okulN, 0) + '</b> okul' + (okulN !== res.length ? 'da <b>' + fmt(res.length, 0) + '</b> program' : '');
    if (S.puan != null && S.kapsam === 'uygun') h.innerHTML = yer + ' puanınla yerleşebileceğin ' + say;
    else h.innerHTML = yer + ' ' + say + (S.puan == null ? '<small>Puanını girersen hangi okullara yerleşebileceğini gösteririz.</small>' : '');
    var list = document.getElementById('lgstr-list');
    if (!res.length) { list.innerHTML = '<li class="lgstr-empty">Bu seçimlere uyan okul yok. İl ya da okul türü seçimini genişletmeyi dene' + (S.kapsam === 'uygun' && S.puan != null ? ' ya da "Tüm okullar"ı seç' : '') + '.</li>'; }
    else list.innerHTML = res.slice(0, S.goster).map(satir).join('');
    document.getElementById('lgstr-more').hidden = res.length <= S.goster;
  }

  function tercihDegis(id) {
    var i = S.tercih.indexOf(id);
    if (i >= 0) S.tercih.splice(i, 1);
    else { if (S.tercih.length >= MAX_TERCIH) { bildir('Merkezi yerleştirmede en fazla ' + MAX_TERCIH + ' okul tercih edebilirsin.'); return; } S.tercih.push(id); }
    store('tercih', S.tercih); tercihCiz(); listeCiz();
  }

  function tercihCiz() {
    var side = document.getElementById('lgstr-side'), n = S.tercih.length;
    var items = S.tercih.map(function (id, i) {
      var pr = PROG_BY_ID[id], dr = durum(pr);
      return '<li><span class="lgstr-tn">' + (i + 1) + '</span><div class="lgstr-tb"><button type="button" data-act="detay" data-id="' + id + '">' + esc(pr.o.ad) + '</button><small>' + esc(pr.o.il) + ' · ' + (pr.taban != null ? 'Taban ' + fmt(pr.taban, 2) : 'Taban yok') + (dr ? ' · <span class="is-' + dr.c + '">' + dr.ad + '</span>' : '') + '</small></div>' +
        '<div class="lgstr-tbtn"><button type="button" data-act="yukari" data-id="' + id + '" aria-label="Yukarı taşı"' + (i === 0 ? ' disabled' : '') + '>' + ICO.up + '</button><button type="button" data-act="asagi" data-id="' + id + '" aria-label="Aşağı taşı"' + (i === n - 1 ? ' disabled' : '') + '>' + ICO.down + '</button><button type="button" data-act="cikar" data-id="' + id + '" aria-label="Listeden çıkar">' + ICO.x + '</button></div></li>';
    }).join('');
    side.innerHTML = '<div class="lgstr-side-in"><div class="lgstr-side-h"><h2 id="lgstr-t-h">Tercih listem</h2><span class="lgstr-count">' + n + '/' + MAX_TERCIH + '</span><button type="button" class="lgstr-side-x" aria-label="Kapat" onclick="this.closest(\'.lgstr-side\').classList.remove(\'is-open\');document.body.classList.remove(\'lgstr-lock\')">' + ICO.x + '</button></div>' +
      (n ? '<ol class="lgstr-tlist">' + items + '</ol>' +
        '<p class="lgstr-tip">Merkezi yerleştirmede tercih sırası önemlidir: en çok istediğin okulu en üste yaz. Taban puanı puanına çok yakın ya da yüksek okulların yanına mutlaka daha güvenli seçenekler de ekle.</p>' +
        '<div class="lgstr-tact"><button type="button" class="lgstr-btn lgstr-btn--sm" data-act="yazdir">Yazdır / PDF</button><button type="button" class="lgstr-btn lgstr-btn--sm lgstr-btn--ghost" data-act="paylas">Bağlantıyı kopyala</button><button type="button" class="lgstr-link" data-act="sirala-taban">Taban puana göre sırala</button><button type="button" class="lgstr-link" data-act="temizle">Listeyi temizle</button></div>'
        : '<p class="lgstr-tempty">Listedeki okulların yanındaki ' + ICO.star + ' düğmesiyle en fazla ' + MAX_TERCIH + ' okul ekleyebilirsin. Listen bu cihazda saklanır.</p>') +
      '</div>';
    var fab = document.getElementById('lgstr-fab');
    fab.hidden = S.view !== 'okullar'; var sn = document.getElementById('lgstr-step-n'); if (sn) { sn.textContent = n; sn.classList.toggle('is-on', n > 0); }
    fab.innerHTML = ICO.star + '<span>Tercih listem</span><b>' + n + '</b>';
  }

  function bildir(t) { var b = el('div', { class: 'lgstr-toast', role: 'status' }); b.textContent = t; root.appendChild(b); setTimeout(function () { b.classList.add('is-on'); }, 10); setTimeout(function () { b.classList.remove('is-on'); setTimeout(function () { b.remove(); }, 300); }, 2800); }

  function paylas(btn) {
    var u = location.origin + location.pathname + '?tercih=' + S.tercih.join(',');
    function ok() { bildir('Bağlantı kopyalandı. Bu bağlantıyı açan kişi tercih listeni görür.'); }
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(u).then(ok, function () { window.prompt('Bağlantıyı kopyala:', u); });
    else window.prompt('Bağlantıyı kopyala:', u);
  }

  function yazdir() {
    var m = S.data.meta;
    var rows = S.tercih.map(function (id, i) { var pr = PROG_BY_ID[id]; return '<tr><td>' + (i + 1) + '</td><td><b>' + esc(pr.o.ad) + '</b><br><small>' + esc(pr.p.alan || pr.o.tur) + (pr.p.dil ? ' · ' + esc(pr.p.dil) : '') + ' · Kurum kodu ' + esc(pr.o.k) + '</small></td><td>' + esc(pr.o.il) + ' / ' + esc(pr.o.ilce) + '</td><td>' + fmt(pr.p.t26, 2) + '</td><td>' + fmt(pr.p.t25, 2) + '</td>' + (VAR_YUZDE ? '<td>' + (pr.p.y25 != null ? '%' + fmt(pr.p.y25, 2) : '–') + '</td>' : '') + '</tr>'; }).join('');
    var w = window.open('', '_blank');
    if (!w) { bildir('Yazdırma penceresi açılamadı. Tarayıcının açılır pencere engelini kapat.'); return; }
    w.document.write('<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>LGS Tercih Listem</title><style>body{font-family:system-ui,sans-serif;color:#16233F;margin:32px}h1{font-size:22px;margin:0 0 4px}p{margin:0 0 16px;color:#4A5468;font-size:13px}table{width:100%;border-collapse:collapse;font-size:13px}th,td{border:1px solid #D5DAE3;padding:8px;text-align:left;vertical-align:top}th{background:#F5F6FA}small{color:#4A5468}</style></head><body><h1>LGS Tercih Listem</h1><p>' + (S.puan != null ? 'Puan: ' + fmt(S.puan, 3) + (S.yuzde != null ? ' · Yüzdelik: %' + fmt(S.yuzde, 2) : '') + ' · ' : '') + 'Oluşturma: ' + new Date().toLocaleDateString(TR) + ' · soruindir.net LGS Tercih Robotu</p><table><thead><tr><th>#</th><th>Okul</th><th>İl / İlçe</th><th>' + esc(m.tabanYili) + ' taban</th><th>' + esc(m.oncekiYil) + ' taban</th>' + (VAR_YUZDE ? '<th>' + esc(m.yuzdelikYili) + ' yüzdelik</th>' : '') + '</tr></thead><tbody>' + rows + '</tbody></table><p style="margin-top:16px">Taban puanlar MEB e-Okul verisidir ve her yıl değişebilir. Kesin tercihlerini e-Okul tercih ekranından yapmalısın.</p><script>window.onload=function(){window.print()}<\/script></body></html>');
    w.document.close();
  }

  /* ---------- okul detayı ---------- */
  function detayYukle() {
    if (S.detay) return Promise.resolve(S.detay);
    if (!CFG.detay) { S.detay = {}; return Promise.resolve(S.detay); }
    if (!S.detayYukleniyor) S.detayYukleniyor = fetch(CFG.detay).then(function (r) { return r.json(); }).then(function (j) { S.detay = j; return j; }).catch(function () { S.detay = {}; return S.detay; });
    return S.detayYukleniyor;
  }
  var sonOdak = null;
  function detayAc(id) {
    var pr = PROG_BY_ID[id]; if (!pr) return;
    sonOdak = document.activeElement;
    var m = document.getElementById('lgstr-modal'), body = document.getElementById('lgstr-modal-body');
    m.hidden = false; document.body.classList.add('lgstr-lock');
    body.innerHTML = detayHtml(pr, null) + '<p class="lgstr-dload">Okul bilgileri yükleniyor…</p>';
    m.querySelector('.lgstr-modal-x').focus();
    detayYukle().then(function (d) { if (!m.hidden) body.innerHTML = detayHtml(pr, d[pr.o.k] || null); });
  }
  function modalKapat() { var m = document.getElementById('lgstr-modal'); if (!m || m.hidden) return; m.hidden = true; document.body.classList.remove('lgstr-lock'); if (sonOdak && sonOdak.focus) sonOdak.focus(); }

  function degisim(p) {
    if (p.t26 == null || p.t25 == null) return '–';
    var f = p.t26 - p.t25;
    return '<span class="lgstr-deg ' + (f > 0.005 ? 'is-up' : f < -0.005 ? 'is-down' : '') + '">' + (f > 0 ? '+' : '') + fmt(f, 2) + '</span>';
  }

  function detayHtml(pr, d) {
    var o = pr.o, meta = S.data.meta, dr = durum(pr), sec = S.tercih.indexOf(pr.id) >= 0;
    var h = '<div class="lgstr-dhead"><p class="lgstr-dtur">' + esc(o.tur) + '</p><h2 id="lgstr-m-h">' + esc(o.ad) + '</h2><p class="lgstr-meta"><span>' + ICO.pin + esc(o.il) + ' / ' + esc(o.ilce) + '</span><span>Kurum kodu ' + esc(o.k) + '</span></p></div>';
    // programlar
    h += '<div class="lgstr-dsec"><h3>Taban puanlar</h3><div class="lgstr-dtable"><table><thead><tr><th>Program</th><th>' + esc(meta.tabanYili) + '</th><th>' + esc(meta.oncekiYil) + '</th>' + (VAR_YUZDE ? '<th>' + esc(meta.yuzdelikYili) + ' yüzdelik</th>' : '') + '<th>' + esc(meta.ikiOnceki) + '</th><th>Değişim</th></tr></thead><tbody>' +
      o.programlar.map(function (p) { return '<tr><td>' + esc(p.alan || o.tur) + (p.dil ? '<small>' + esc(p.dil) + (p.hz ? ' · Hazırlık sınıflı' : '') + '</small>' : '') + '</td><td>' + fmt(p.t26, 2) + '</td><td>' + fmt(p.t25, 2) + '</td>' + (VAR_YUZDE ? '<td>' + (p.y25 != null ? '%' + fmt(p.y25, 2) : '–') + '</td>' : '') + '<td>' + fmt(p.t24, 2) + '</td><td>' + degisim(p) + '</td></tr>'; }).join('') +
      '</tbody></table></div>';
    if (dr) h += '<p class="lgstr-dnote"><span class="lgstr-durum is-' + dr.c + '">' + dr.ad + '</span> Puanın (' + fmt(S.puan, 3) + ') bu programın ' + esc(pr.tabanYil) + ' taban puanından ' + fmt(Math.abs(dr.f), 2) + ' puan ' + (dr.f >= 0 ? 'yüksek' : 'düşük') + '.' + (S.yuzde != null && pr.p.y25 != null ? ' Yüzdelik dilimin %' + fmt(S.yuzde, 2) + ', okulun ' + esc(meta.yuzdelikYili) + ' yüzdelik dilimi %' + fmt(pr.p.y25, 2) + '.' : '') + ' Taban puanlar her yıl değişebilir.</p>';
    h += '<button type="button" class="lgstr-btn' + (sec ? ' lgstr-btn--ghost' : '') + '" data-act="ekle" data-id="' + pr.id + '">' + (sec ? 'Tercih listemden çıkar' : 'Tercih listeme ekle') + '</button></div>';
    if (d && d.n) {
      var lbl = { tr: 'Türkçe', mat: 'Matematik', fen: 'Fen', ink: 'İnkılap', din: 'Din', ing: 'Yabancı dil' };
      h += '<div class="lgstr-dsec"><h3>Okula yerleşen son öğrencinin netleri <small>(' + esc(meta.yuzdelikYili) + ')</small></h3><div class="lgstr-dnets">' + Object.keys(lbl).map(function (k) { return d.n[k] != null ? '<div><span>' + lbl[k] + '</span><b>' + fmt(d.n[k], 2) + '</b></div>' : ''; }).join('') + '</div></div>';
    }
    if (d && d.aciklama) h += '<div class="lgstr-dsec"><h3>Okul hakkında</h3><p class="lgstr-dnote">' + esc(d.aciklama).replace(/\n/g, '<br>') + '</p></div>';
    if (d) {
      var rows = [];
      function r(a, b) { if (b != null && b !== '') rows.push('<tr><th>' + a + '</th><td>' + b + '</td></tr>'); }
      r('Adres', esc(d.adres));
      if (d.tel) r('Telefon', '<a href="tel:+90' + esc(d.tel) + '">0 ' + esc(d.tel).replace(/(\d{3})(\d{3})(\d{2})(\d{2})/, '($1) $2 $3 $4') + '</a>');
      if (d.web) r('Web sitesi', '<a href="https://' + esc(d.web.replace(/^https?:\/\//, '')) + '" target="_blank" rel="noopener nofollow">' + esc(d.web) + '</a>');
      if (d.eposta) r('E-posta', '<a href="mailto:' + esc(d.eposta) + '">' + esc(d.eposta) + '</a>');
      r('Pansiyon', d.pans ? ('Var' + (d.pansK || d.pansE ? ' (' + [d.pansK ? 'kız' : '', d.pansE ? 'erkek' : ''].filter(Boolean).join(', ') + ')' : '')) : 'Yok');
      r('Öğretim şekli', esc(d.ogretim));
      if (d.bas) r('Ders saatleri', esc(d.bas) + (d.bit ? ' – ' + esc(d.bit) : ''));
      r('Öğretim süresi', esc(d.sure));
      if (d.proje) r('Proje okulu', 'Evet');
      if (d.ogr) r('Öğrenci sayısı', fmt(d.ogr, 0));
      if (d.ogrt) r('Öğretmen sayısı', fmt(d.ogrt, 0));
      if (d.derslik) r('Derslik', fmt(d.derslik, 0));
      if (d.olanak && d.olanak.length) r('Olanaklar', esc(d.olanak.join(', ')));
      if (rows.length) h += '<div class="lgstr-dsec"><h3>Okul bilgileri</h3><table class="lgstr-dinfo"><tbody>' + rows.join('') + '</tbody></table></div>';
    }
    var q = encodeURIComponent(o.ad + ' ' + o.ilce + ' ' + o.il), ep = (d && d.eposta) || (o.k + '@meb.k12.tr');
    var site = o.w ? 'https://' + o.w + '.meb.k12.tr' : '';
    var harita = d && d.lat && d.lng ? 'https://www.google.com/maps?q=' + d.lat + ',' + d.lng : (site ? site + '/tema/harita.php' : 'https://www.google.com/maps/search/?api=1&query=' + q);
    var B = function (href, txt, cls) { return '<a class="lgstr-btn lgstr-btn--sm ' + (cls || 'lgstr-btn--ghost') + '" href="' + esc(href) + '" target="_blank" rel="noopener">' + txt + '</a>'; };
    h += '<div class="lgstr-dsec"><h3>Okulu tanı</h3>' +
      '<p class="lgstr-dnote">' + (site ? 'Okulun tarihçesi, imkânları, pansiyonu ve iletişim bilgileri okulun MEB\'teki resmî sitesinde yer alıyor.' : 'Okulun adresini, pansiyon durumunu ve olanaklarını tercih yapmadan önce okulun kendi sitesinden kontrol et.') + ' Mümkünse okulu ziyaret et.</p>' +
      '<div class="lgstr-tact">' +
      (site && o.y ? B(site + '/meb_iys_dosyalar/' + o.y + '/' + o.k + '/okulumuz_hakkinda.html', 'Okulumuz hakkında', ' ') : '') +
      (site ? B(site, 'Okulun web sitesi') : (d && d.web ? '' : B('https://www.google.com/search?q=' + q + '+meb.k12.tr', 'Okulun web sitesini ara'))) +
      B(harita, 'Haritada göster') +
      (d && d.eposta ? '' : '<a class="lgstr-btn lgstr-btn--ghost lgstr-btn--sm" href="mailto:' + esc(ep) + '">E-posta gönder</a>') + '</div></div>';
    h += '<p class="lgstr-dsrc">Taban puanlar: MEB e-Okul' + (site ? ' · Okul sitesi: MEB okul listesi' : '') + (d ? ' · Okul bilgileri: site yönetimi' : '') + '. Kurum kodu ' + esc(o.k) + '.</p>';
    return h;
  }

  /* ---------- genişlik: tema sütununa göre düzen ---------- */
  function genislik() {
    var w = root.getBoundingClientRect().width || window.innerWidth;
    root.classList.toggle('lgstr-w-md', w < 980);
    root.classList.toggle('lgstr-w-sm', w < 680);
    if (typeof mbarCiz === 'function' && S.data) mbarCiz();
  }
  genislik();
  if (window.ResizeObserver) new ResizeObserver(genislik).observe(root); else window.addEventListener('resize', genislik);

  /* ---------- başlat ---------- */
  fetch(CFG.okullar).then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); }).then(function (j) {
    S.data = j; hazirla(); iskelet();
  }).catch(function () {
    root.innerHTML = '<p class="lgstr-empty">Okul verileri yüklenemedi. Sayfayı yenilemeyi dene.</p>';
  });
})();
