/* YKS Puan Hesaplama ve Tercih Robotu — Soruindir */
(function () {
  'use strict';
  var CFG = window.YKSTR || {};
  var root = document.getElementById('ykstr-app');
  if (!root) return;
  var MOD = CFG.mod === 'hesaplama' ? 'hesaplama' : 'robot';

  /* ---------- yardımcılar ---------- */
  var TR = 'tr-TR';
  function el(tag, attrs, html) { var e = document.createElement(tag); if (attrs) for (var k in attrs) { if (k === 'class') e.className = attrs[k]; else if (k === 'text') e.textContent = attrs[k]; else e.setAttribute(k, attrs[k]); } if (html != null) e.innerHTML = html; return e; }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function fmt(n, d) { if (n == null || isNaN(n)) return '–'; return Number(n).toLocaleString(TR, { minimumFractionDigits: d, maximumFractionDigits: d }); }
  function lower(s) { return String(s || '').replace(/I/g, 'ı').replace(/İ/g, 'i').toLowerCase(); }
  function norm(s) { return lower(s).replace(/ç/g, 'c').replace(/ğ/g, 'g').replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ş/g, 's').replace(/ü/g, 'u'); }
  function store(k, v) { try { if (v === undefined) return JSON.parse(localStorage.getItem('ykstr_' + k) || 'null'); localStorage.setItem('ykstr_' + k, JSON.stringify(v)); } catch (e) { return null; } }
  function sayi(v) { v = String(v || '').trim().replace(/\s/g, ''); if (!v) return null; if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.'); var n = parseFloat(v); return isNaN(n) ? null : n; }
  var ICO = {
    star: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg>',
    info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>',
    up: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg>',
    down: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>',
    x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>',
    pin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>',
    uy: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 2 20h20L12 3z"/><path d="M12 10v4M12 17h.01"/></svg>'
  };
  function uyari(b, m, cls) { return '<div class="ykstr-uyari' + (cls ? ' ' + cls : '') + '" role="note">' + ICO.uy + '<div><b>' + b + '</b><span>' + m + '</span></div></div>'; }

  /* ---------- testler ---------- */
  var TESTLER = {
    tyt: [
      { k: 'tr', ad: 'Türkçe', soru: 40, c: '#E0457B' }, { k: 'sos', ad: 'Sosyal Bilimler', soru: 20, c: '#B5641B' },
      { k: 'mat', ad: 'Temel Matematik', soru: 40, c: '#1E5AA8' }, { k: 'fen', ad: 'Fen Bilimleri', soru: 20, c: '#0F7B4B' }
    ],
    ayt: [
      { grup: 'Matematik' }, { k: 'amat', ad: 'Matematik', soru: 40, c: '#1E5AA8' },
      { grup: 'Fen Bilimleri' }, { k: 'fiz', ad: 'Fizik', soru: 14, c: '#0E8FA8' }, { k: 'kim', ad: 'Kimya', soru: 13, c: '#0F7B4B' }, { k: 'bio', ad: 'Biyoloji', soru: 13, c: '#3E9B3A' },
      { grup: 'Türk Dili ve Edebiyatı - Sosyal Bilimler 1' }, { k: 'edb', ad: 'Türk Dili ve Edebiyatı', soru: 24, c: '#E0457B' }, { k: 'tar1', ad: 'Tarih-1', soru: 10, c: '#B5641B' }, { k: 'cog1', ad: 'Coğrafya-1', soru: 6, c: '#8A6D1F' },
      { grup: 'Sosyal Bilimler 2' }, { k: 'tar2', ad: 'Tarih-2', soru: 11, c: '#B5641B' }, { k: 'cog2', ad: 'Coğrafya-2', soru: 11, c: '#8A6D1F' }, { k: 'fel', ad: 'Felsefe Grubu', soru: 12, c: '#6D4AFF' }, { k: 'dkab', ad: 'Din Kültürü / Ek Felsefe', soru: 6, c: '#9B4AD1' }
    ],
    ydt: [{ k: 'ydt', ad: 'Yabancı Dil', soru: 80, c: '#6D4AFF' }]
  };
  // 2026-YKS YDT istatistikleri (ÖSYM sayısal bilgiler, son sınıf)
  var YDT_DIL = { 'İngilizce': [37.1, 21.7], 'Almanca': [43.0, 24.9], 'Arapça': [44.3, 22.9], 'Fransızca': [46.1, 19.7], 'Rusça': [56.9, 22.0] };
  var KOSUL = { TYT: [['tr', 'mat']], SAY: [['tr', 'mat'], ['amat', 'fiz', 'kim', 'bio']], EA: [['tr', 'mat'], ['amat', 'edb', 'tar1', 'cog1']], 'SÖZ': [['tr', 'mat'], ['edb', 'tar1', 'cog1', 'tar2', 'cog2', 'fel', 'dkab']], 'DİL': [['tr', 'mat'], ['ydt']] };
  var PT_AD = { SAY: 'Sayısal', EA: 'Eşit Ağırlık', 'SÖZ': 'Sözel', 'DİL': 'Dil', TYT: 'TYT' };
  var PT_SIRA = ['TYT', 'SAY', 'EA', 'SÖZ', 'DİL'];

  /* ---------- durum ---------- */
  var S = {
    data: null, view: 'puan', sekme: 'tyt',
    netler: store('netler') || {}, ydtDil: store('ydtdil') || 'İngilizce', dipl: store('dipl') || '', kirik: !!store('kirik'),
    puan: null, // { TYT: {ham, yer, sira}, ... }
    pt: store('pt') || null, ptSecildi: !!store('pt'),
    iller: store('iller') || [], unitur: store('unitur') || [], burs: '', dil: '', ogr: '', ara: '', uniAra: '',
    kapsam: 'uygun', sirala: 'taban', goster: 30,
    tercih: store('tercih') || []
  };
  var MAX_TERCIH = 24;

  /* ---------- puan hesabı ---------- */
  function netOf(k) { var v = S.netler[k]; if (!v) return 0; var d = parseInt(v[0], 10) || 0, y = parseInt(v[1], 10) || 0; return d - y / 4; }
  function hesapla() {
    var M = S.data.model, T = M.testler, out = {}, any = false;
    var dipl = sayi(S.dipl), obp = dipl != null ? Math.max(250, Math.min(500, Math.max(50, Math.min(100, dipl)) * 5)) : null;
    var kats = S.kirik ? M.obp / 2 : M.obp;
    PT_SIRA.forEach(function (pt) {
      var ok = KOSUL[pt].every(function (grp) { return grp.some(function (k) { return netOf(k) >= 0.5; }); });
      if (!ok) return;
      var W = M.agirlik[pt], A = 0;
      for (var k in W) {
        var st = T[k], ort = st.ort, ss = st.ss;
        if (k === 'ydt' && YDT_DIL[S.ydtDil]) { ort = YDT_DIL[S.ydtDil][0]; ss = YDT_DIL[S.ydtDil][1]; }
        A += W[k] * (50 + 10 * (netOf(k) - ort) / ss);
      }
      var tr = M.donusum[pt], ham = Math.max(100, Math.min(500, tr.a + tr.b * A));
      var yer = obp != null ? ham + obp * kats : null;
      out[pt] = { ham: ham, yer: yer, sira: sira(yer != null ? yer : ham + 250 * kats, pt), obpYok: obp == null };
      any = true;
    });
    S.obp = obp;
    S.puan = any ? out : null;
    return S.puan;
  }
  // ÖSYM yerleştirme puanlarının yığınsal dağılımı üzerinde probit interpolasyon
  function ndtr(z) { var t = 1 / (1 + 0.2316419 * Math.abs(z)), d = 0.3989423 * Math.exp(-z * z / 2), p = d * t * (0.3193815 + t * (-0.3565638 + t * (1.781478 + t * (-1.821256 + t * 1.330274)))); return z > 0 ? 1 - p : p; }
  function ndtri(p) {
    if (p <= 0) return -40; if (p >= 1) return 40;
    var a = [-39.69683028665376, 220.9460984245205, -275.9285104469687, 138.357751867269, -30.66479806614716, 2.506628277459239], b = [-54.47609879822406, 161.5858368580409, -155.6989798598866, 66.80131188771972, -13.28068155288572], c = [-0.007784894002430293, -0.3223964580411365, -2.400758277161838, -2.549732539343734, 4.374664141464968, 2.938163982698783], d = [0.007784695709041462, 0.3224671290700398, 2.445134137142996, 3.754408661907416];
    var q, r, pl = 0.02425;
    if (p < pl) { q = Math.sqrt(-2 * Math.log(p)); return (((((c[0] * q + c[1]) * q + c[2]) * q + c[3]) * q + c[4]) * q + c[5]) / ((((d[0] * q + d[1]) * q + d[2]) * q + d[3]) * q + 1); }
    if (p <= 1 - pl) { q = p - 0.5; r = q * q; return (((((a[0] * r + a[1]) * r + a[2]) * r + a[3]) * r + a[4]) * r + a[5]) * q / (((((b[0] * r + b[1]) * r + b[2]) * r + b[3]) * r + b[4]) * r + 1); }
    q = Math.sqrt(-2 * Math.log(1 - p)); return -(((((c[0] * q + c[1]) * q + c[2]) * q + c[3]) * q + c[4]) * q + c[5]) / ((((d[0] * q + d[1]) * q + d[2]) * q + d[3]) * q + 1);
  }
  function sira(puan, pt) {
    var pts = S.data.cumY[pt]; if (!pts || puan == null) return null;
    var ntot = pts[pts.length - 1][1];
    var z = function (n) { return ndtri(1 - Math.min(Math.max(n / ntot, 1e-12), 1 - 1e-12)); };
    if (puan >= pts[0][0]) { var z2 = z(pts[0][1]), z1 = z(pts[1][1]), zz = z2 + (z2 - z1) / (pts[0][0] - pts[1][0]) * (puan - pts[0][0]); return Math.max(1, Math.round(ntot * (1 - ndtr(zz)))); }
    if (puan <= pts[pts.length - 1][0]) return ntot;
    for (var i = 0; i < pts.length - 1; i++) {
      var hi = pts[i], lo = pts[i + 1];
      if (puan >= lo[0] && puan < hi[0]) { var f = (puan - lo[0]) / (hi[0] - lo[0]), zv = z(lo[1]) + f * (z(hi[1]) - z(lo[1])); return Math.max(1, Math.round(ntot * (1 - ndtr(zv)))); }
    }
    return null;
  }
  function enIyi() {
    if (!S.puan) return null;
    var best = null, bp = 1e9;
    ['SAY', 'EA', 'SÖZ', 'DİL', 'TYT'].forEach(function (pt) { var p = S.puan[pt]; if (!p) return; var n = S.data.cumY[pt]; var yz = p.sira / n[n.length - 1][1]; if (yz < bp) { bp = yz; best = pt; } });
    return best;
  }

  /* ---------- program listesi ---------- */
  var PROG = [], PROG_BY_KOD = {};
  function hazirla() {
    var d = S.data;
    d.p.forEach(function (r) {
      var u = d.unis[r[1]];
      var pr = { kod: String(r[0]), u: u, fak: d.faks[r[2]], ad: r[3], pt: d.pt[r[4]], kont: r[5], yer: r[6], min: r[7], max: r[8], sira: r[9], tahmini: !!r[10], burs: r[11], dil: r[12], ogr: r[13], onlisans: !!r[14] };
      pr.ara = norm(pr.ad); pr.uara = norm(u[0] + ' ' + u[2]);
      PROG.push(pr); PROG_BY_KOD[pr.kod] = pr;
    });
    S.tercih = S.tercih.filter(function (k) { return PROG_BY_KOD[k]; });
    var q = new URLSearchParams(location.search).get('tercih');
    if (q) { var ids = q.split(',').filter(function (k) { return PROG_BY_KOD[k]; }).slice(0, MAX_TERCIH); if (ids.length) { S.tercih = ids; store('tercih', ids); } }
  }
  function kullaniciPuan(pt) { if (!S.puan || !S.puan[pt]) return null; var p = S.puan[pt]; return p.yer != null ? p.yer : null; }
  function durum(pr) {
    var up = kullaniciPuan(pr.pt);
    if (up == null || pr.min == null) return null;
    var f = up - pr.min;
    if (f >= 10) return { c: 'ok', ad: 'Yüksek ihtimal', f: f };
    if (f >= 0) return { c: 'iyi', ad: 'Muhtemel', f: f };
    if (f >= -10) return { c: 'sinir', ad: 'Sınırda', f: f };
    return { c: 'dusuk', ad: 'Düşük ihtimal', f: f };
  }
  function filtrele() {
    var q = norm(S.ara.trim()), uq = norm(S.uniAra.trim()), pt = S.pt, up = kullaniciPuan(pt);
    var out = PROG.filter(function (pr) {
      if (pr.pt !== pt) return false;
      if (S.iller.length && S.iller.indexOf(pr.u[2]) < 0) return false;
      if (S.unitur.length && S.unitur.indexOf(pr.u[1]) < 0) return false;
      if (S.burs !== '' && String(pr.burs) !== S.burs) return false;
      if (S.dil !== '' && String(pr.dil) !== S.dil) return false;
      if (S.ogr !== '' && String(pr.ogr) !== S.ogr) return false;
      if (q && pr.ara.indexOf(q) < 0) return false;
      if (uq && pr.uara.indexOf(uq) < 0) return false;
      if (S.kapsam === 'uygun' && up != null) { if (pr.min == null || up - pr.min < -10) return false; }
      return true;
    });
    out.sort(function (a, b) {
      if (S.sirala === 'yakin' && up != null) return Math.abs(up - (a.min || 0)) - Math.abs(up - (b.min || 0));
      if (S.sirala === 'ad') return a.ad.localeCompare(b.ad, TR) || a.u[0].localeCompare(b.u[0], TR);
      if (S.sirala === 'kont') return b.kont - a.kont;
      return (b.min || 0) - (a.min || 0);
    });
    return out;
  }

  /* ---------- iskelet ---------- */
  function dersSatiri(t) {
    var v = S.netler[t.k] || ['', ''];
    return '<div class="ykstr-ders" data-ders="' + t.k + '" style="--c:' + t.c + '">' +
      '<div class="ykstr-ders-ad"><b>' + t.ad + '</b><em>' + t.soru + ' soru</em></div>' +
      '<label class="ykstr-in"><span>Doğru</span><input type="number" min="0" max="' + t.soru + '" inputmode="numeric" data-f="d" placeholder="0" value="' + esc(v[0]) + '" aria-label="' + t.ad + ' doğru (' + t.soru + ' soru)"></label>' +
      '<label class="ykstr-in"><span>Yanlış</span><input type="number" min="0" max="' + t.soru + '" inputmode="numeric" data-f="y" placeholder="0" value="' + esc(v[1]) + '" aria-label="' + t.ad + ' yanlış"></label>' +
      '<div class="ykstr-ders-net"><span>Net</span><b class="ykstr-netv">0</b></div>' +
      '<div class="ykstr-ders-bar" aria-hidden="true"><i></i></div></div>';
  }
  function iskelet() {
    var m = S.data.meta;
    var robot = MOD === 'robot';
    root.innerHTML = '' +
      '<div class="ykstr-hero">' +
        '<div class="ykstr-hero-in">' +
          '<p class="ykstr-kicker">' + m.yil + ' YKS · ÖSYM verileriyle</p>' +
          '<h1>' + esc(CFG.baslik || (robot ? 'YKS Tercih Robotu' : 'YKS Puan Hesaplama')) + '</h1>' +
          '<p class="ykstr-lead">' + (robot ? 'TYT, AYT ve YDT netlerinden puanını ve sıralamanı hesapla, puanınla girebileceğin üniversite programlarını gör, ' + MAX_TERCIH + ' tercihlik listeni oluştur.' : 'TYT, AYT ve YDT netlerini gir; TYT, Sayısal, Eşit Ağırlık, Sözel ve Dil puanlarını, OBP\'li yerleştirme puanını ve tahmini sıralamanı anında gör.') + '</p>' +
          '<ul class="ykstr-stats"><li><b>' + fmt(m.programSayisi, 0) + '</b><span>program</span></li><li><b>' + fmt(m.universiteSayisi, 0) + '</b><span>üniversite</span></li><li><b>' + fmt(m.lisans, 0) + '</b><span>lisans</span></li><li><b>' + fmt(m.onlisans, 0) + '</b><span>ön lisans</span></li></ul>' +
        '</div>' +
        (robot ? '<ol class="ykstr-steps" role="list">' +
          '<li><button type="button" data-git="puan"><span>1</span><b>Puanını hesapla</b></button></li>' +
          '<li><button type="button" data-git="programlar"><span>2</span><b>Programları keşfet</b></button></li>' +
          '<li><button type="button" data-git="tercih"><span>3</span><b>Tercih listen</b><i id="ykstr-step-n">0</i></button></li>' +
        '</ol>' : '<div class="ykstr-steps-pad"></div>') +
      '</div>' +

      '<section class="ykstr-view" data-view="puan" aria-labelledby="ykstr-p-h">' +
        '<div class="ykstr-calc">' +
          '<div class="ykstr-card ykstr-calc-form">' +
            '<div class="ykstr-calc-h"><h2 id="ykstr-p-h">Puanını hesapla</h2><p>Her testte doğru ve yanlış sayılarını yaz; 4 yanlış 1 doğruyu götürür. Puanların ve sıralaman anında hesaplanır.</p></div>' +
            uyari('Hesaplanan puanlar tahminidir.', 'Hesap, ÖSYM\'nin ' + m.yil + '-YKS test ortalamaları, standart sapmaları ve kılavuzdaki test ağırlıklarıyla yapılır. Kesin puanın ÖSYM sonuç belgende yazar.') +
            '<div class="ykstr-obp">' +
              '<label><span>Diploma notu</span><input type="text" inputmode="decimal" id="ykstr-dipl" placeholder="Örn: 85,40" value="' + esc(S.dipl) + '"></label>' +
              '<div class="ykstr-obp-v"><span>OBP</span><b id="ykstr-obp-v">–</b></div>' +
              '<label class="ykstr-check"><input type="checkbox" id="ykstr-kirik"' + (S.kirik ? ' checked' : '') + '> Geçen yıl bir programa yerleştim <small>(OBP katsayısı 0,06\'ya iner)</small></label>' +
            '</div>' +
            '<div class="ykstr-tabs" role="tablist">' +
              '<button type="button" role="tab" data-tab="tyt" aria-selected="true">TYT</button>' +
              '<button type="button" role="tab" data-tab="ayt" aria-selected="false">AYT</button>' +
              '<button type="button" role="tab" data-tab="ydt" aria-selected="false">YDT</button>' +
            '</div>' +
            '<div class="ykstr-tab" data-pane="tyt"><div class="ykstr-dersler">' + TESTLER.tyt.map(dersSatiri).join('') + '</div></div>' +
            '<div class="ykstr-tab" data-pane="ayt" hidden><p class="ykstr-hint">Yalnızca puan türünün gerektirdiği testleri doldurman yeterli: Sayısal için Matematik ve Fen, Eşit Ağırlık için Matematik ve Edebiyat-Sosyal 1, Sözel için Edebiyat-Sosyal 1 ve Sosyal 2.</p><div class="ykstr-dersler">' +
              TESTLER.ayt.map(function (t) { return t.grup ? '<h3 class="ykstr-grup">' + t.grup + '</h3>' : dersSatiri(t); }).join('') + '</div></div>' +
            '<div class="ykstr-tab" data-pane="ydt" hidden><label class="ykstr-dilsec"><span>Sınav dili</span><select id="ykstr-ydtdil">' + Object.keys(YDT_DIL).map(function (d) { return '<option' + (d === S.ydtDil ? ' selected' : '') + '>' + d + '</option>'; }).join('') + '</select></label><div class="ykstr-dersler">' + TESTLER.ydt.map(dersSatiri).join('') + '</div></div>' +
            '<p class="ykstr-err" id="ykstr-err" hidden></p>' +
            '<div class="ykstr-calc-foot"><span>Ön lisans programları TYT puanıyla, lisans programları SAY, EA, SÖZ ya da DİL puanıyla öğrenci alır.</span><button type="button" class="ykstr-link" id="ykstr-sil">Temizle</button></div>' +
          '</div>' +
          '<aside class="ykstr-result" id="ykstr-sonuc" aria-live="polite"></aside>' +
        '</div>' +
        uyari('Sonuçlar bilgilendirme amaçlıdır.', 'Netlerden hesaplanan puan ve sıralamalar tahminidir; gerçek puanın birkaç puan farklı çıkabilir. Programlar ' + m.yil + ' yerleştirme taban puanlarıyla karşılaştırılır ve taban puanlar her yıl değişir.', 'ykstr-uyari--alt') +
      '</section>' +

      (robot ? '<section class="ykstr-view" data-view="programlar" hidden>' +
        '<div class="ykstr-ozet" id="ykstr-ozet"></div>' +
        uyari('Yerleşme ihtimalleri tahminidir.', 'Programlar ÖSYM\'nin ' + m.yil + '-YKS en küçük puanlarıyla (taban) ve YÖK Atlas başarı sıralarıyla karşılaştırılır. Bu yılın taban puanları kontenjana ve tercihlere göre değişebilir.', 'ykstr-uyari--ust') +
        '<div class="ykstr-grid">' +
          '<div class="ykstr-main">' +
            '<section class="ykstr-card ykstr-filtre" aria-labelledby="ykstr-f-h">' +
              '<h2 id="ykstr-f-h">Programları süz</h2>' +
              '<div class="ykstr-frow">' +
                '<label><span class="ykstr-lbl">Program / bölüm</span><input type="search" id="ykstr-ara" placeholder="Örn: Bilgisayar Mühendisliği, Hukuk, Hemşirelik"></label>' +
                '<label><span class="ykstr-lbl">Üniversite</span><input type="search" id="ykstr-uara" placeholder="Üniversite adı"></label>' +
              '</div>' +
              '<div class="ykstr-il"><span class="ykstr-lbl">Şehirler</span><div class="ykstr-ilbox"><div class="ykstr-chips" id="ykstr-ilchips"></div><input type="text" id="ykstr-ilara" placeholder="Şehir ekle (boşsa tümü)" autocomplete="off" aria-label="Şehir ara ve ekle"><ul class="ykstr-ilmenu" id="ykstr-ilmenu" hidden></ul></div></div>' +
              '<div class="ykstr-turler" id="ykstr-unitur"></div>' +
              '<div class="ykstr-frow ykstr-frow--4">' +
                '<label><span class="ykstr-lbl">Burs / ücret</span><select id="ykstr-burs"><option value="">Tümü</option><option value="0">Devlet (ücretsiz)</option><option value="1">Tam burslu</option><option value="2">%50 indirimli</option><option value="3">%25 indirimli</option><option value="4">Ücretli</option></select></label>' +
                '<label><span class="ykstr-lbl">Öğretim dili</span><select id="ykstr-dil"><option value="">Tümü</option></select></label>' +
                '<label><span class="ykstr-lbl">Öğretim türü</span><select id="ykstr-ogr"><option value="">Tümü</option><option value="0">Örgün öğretim</option><option value="1">Uzaktan öğretim</option><option value="2">Açıköğretim</option></select></label>' +
                '<label><span class="ykstr-lbl">Sırala</span><select id="ykstr-sirala"><option value="taban">Taban puan (yüksekten)</option><option value="yakin">Puanıma en yakın</option><option value="kont">Kontenjan</option><option value="ad">Program adı</option></select></label>' +
              '</div>' +
              '<label class="ykstr-check"><input type="checkbox" id="ykstr-kapsam" checked> Yalnızca puanımla yerleşebileceğim programlar</label>' +
            '</section>' +
            '<section aria-labelledby="ykstr-r-h"><h2 id="ykstr-r-h" class="ykstr-rh"></h2><ol class="ykstr-list" id="ykstr-list"></ol><button type="button" class="ykstr-btn ykstr-btn--ghost" id="ykstr-more" hidden>Daha fazla göster</button>' +
              uyari('Bu liste bir tahmindir, kesin sonuç değildir.', 'Tercihlerini ÖSYM tercih kılavuzundaki koşulları okuyarak ÖSYM\'nin tercih sisteminden yap. Listene puanına göre daha güvenli programlar eklemeyi unutma.', 'ykstr-uyari--alt') +
            '</section>' +
          '</div>' +
          '<aside class="ykstr-side" id="ykstr-side" aria-labelledby="ykstr-t-h"></aside>' +
        '</div>' +
      '</section>' : '') +
      '<p class="ykstr-src">' + ICO.info + '<span>Kaynak: ÖSYM ' + m.yil + '-YKS sayısal bilgileri ve en küçük/en büyük puan tabloları (TABLO-3, TABLO-4). Başarı sıraları YÖK Atlas\'tan; ≈ işaretli olanlar ÖSYM puan dağılımından tahmin edilmiştir. Son güncelleme: ' + esc(m.olusturma) + '.</span></p>' +
      '<div class="ykstr-modal" id="ykstr-modal" hidden><div class="ykstr-modal-bg" data-close></div><div class="ykstr-modal-box" role="dialog" aria-modal="true" aria-labelledby="ykstr-m-h"><button type="button" class="ykstr-modal-x" data-close aria-label="Kapat">' + ICO.x + '</button><div id="ykstr-modal-body"></div></div></div>' +
      '<button type="button" class="ykstr-fab" id="ykstr-fab" hidden></button>' +
      '<div class="ykstr-mbar" id="ykstr-mbar" hidden></div>';

    if (robot) {
      document.getElementById('ykstr-unitur').innerHTML = ['Devlet', 'Vakıf', 'KKTC', 'Yurt dışı devlet', 'Yurt dışı vakıf'].map(function (t) { return '<label class="ykstr-tur"><input type="checkbox" value="' + esc(t) + '"' + (S.unitur.indexOf(t) >= 0 ? ' checked' : '') + '><span>' + esc(t) + '</span></label>'; }).join('');
      var dilSel = document.getElementById('ykstr-dil');
      S.data.diller.forEach(function (d, i) { dilSel.appendChild(el('option', { value: String(i), text: d })); });
    }
    olaylar();
    netHesap(); sonucCiz();
    if (robot) { ozetCiz(); ilCiz(); listeCiz(); tercihCiz(); }
    goster(robot && location.hash === '#programlar' && S.puan ? 'programlar' : 'puan', true);
  }

  /* ---------- görünüm ---------- */
  function goster(v, ilk) {
    S.view = v;
    root.querySelectorAll('.ykstr-view').forEach(function (x) { x.hidden = x.getAttribute('data-view') !== v; });
    root.querySelectorAll('.ykstr-steps [data-git]').forEach(function (b) {
      var g = b.getAttribute('data-git'), on = g === v;
      b.classList.toggle('is-on', on); b.classList.toggle('is-done', v === 'programlar' && g === 'puan' && !!S.puan);
      if (on) b.setAttribute('aria-current', 'step'); else b.removeAttribute('aria-current');
    });
    var fab = document.getElementById('ykstr-fab'); if (fab) fab.hidden = v !== 'programlar';
    mbarCiz();
    if (!ilk) { var top = root.getBoundingClientRect().top + window.pageYOffset - 90; if (window.pageYOffset > top) window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' }); }
    if (MOD === 'robot') { try { history.replaceState(null, '', v === 'programlar' ? '#programlar' : location.pathname + location.search); } catch (e) {} }
  }
  function sekme(t) {
    S.sekme = t;
    root.querySelectorAll('.ykstr-tabs [data-tab]').forEach(function (x) { x.setAttribute('aria-selected', x.getAttribute('data-tab') === t ? 'true' : 'false'); });
    root.querySelectorAll('[data-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== t; });
  }

  function netHesap() {
    var err = '';
    root.querySelectorAll('.ykstr-ders').forEach(function (r) {
      var k = r.getAttribute('data-ders'), di = r.querySelector('[data-f=d]'), yi = r.querySelector('[data-f=y]');
      var soru = parseInt(di.getAttribute('max'), 10);
      var d = parseInt(di.value, 10) || 0, y = parseInt(yi.value, 10) || 0;
      var hata = d < 0 || y < 0 || d + y > soru;
      if (hata && !err) err = r.querySelector('.ykstr-ders-ad b').textContent + ' için doğru ve yanlış toplamı ' + soru + ' soruyu geçemez.';
      r.classList.toggle('is-err', hata);
      if (di.value === '' && yi.value === '') delete S.netler[k]; else S.netler[k] = hata ? ['0', '0'] : [di.value, yi.value];
      var n = hata ? 0 : d - y / 4;
      r.querySelector('.ykstr-netv').textContent = fmt(n, 2);
      r.querySelector('.ykstr-ders-bar i').style.width = Math.max(0, n / soru * 100) + '%';
    });
    store('netler', S.netler);
    var e = document.getElementById('ykstr-err'); e.hidden = !err; e.textContent = err;
    hesapla();
    var o = document.getElementById('ykstr-obp-v'); if (o) o.textContent = S.obp != null ? fmt(S.obp, 2) : '–';
    // sekme rozetleri: o sekmede girilen toplam net
    ['tyt', 'ayt', 'ydt'].forEach(function (t) {
      var tot = 0, dolu = false;
      TESTLER[t].forEach(function (x) { if (x.k && S.netler[x.k]) { dolu = true; tot += netOf(x.k); } });
      var b = root.querySelector('.ykstr-tabs [data-tab="' + t + '"]');
      if (b) b.innerHTML = t.toUpperCase() + (dolu ? ' <small>' + fmt(tot, 2) + ' net</small>' : '');
    });
  }

  function sonucCiz() {
    var box = document.getElementById('ykstr-sonuc'), P = S.puan, best = enIyi();
    var rows = PT_SIRA.map(function (pt) {
      var p = P && P[pt];
      return '<li class="' + (p ? '' : 'is-yok') + (pt === best ? ' is-best' : '') + '">' +
        '<div class="ykstr-pt"><b>' + pt + '</b><span>' + PT_AD[pt] + '</span></div>' +
        (p ? '<div class="ykstr-pv"><strong>' + fmt(p.ham, 3) + '</strong><span>ham puan</span></div>' +
             '<div class="ykstr-pv"><strong>' + (p.yer != null ? fmt(p.yer, 3) : '–') + '</strong><span>yerleştirme</span></div>' +
             '<div class="ykstr-pv"><strong>' + (p.sira ? '≈' + fmt(p.sira, 0) : '–') + '</strong><span>sıralama</span></div>'
           : '<div class="ykstr-pnot">' + (pt === 'TYT' ? 'Türkçe ya da Temel Matematik\'te en az 0,5 net gerekli' : pt === 'DİL' ? 'TYT ve YDT netleri gerekli' : 'TYT ve ilgili AYT testleri gerekli') + '</div>') +
      '</li>';
    }).join('');
    var obpNot = S.obp == null && P ? '<p class="ykstr-result-n">Yerleştirme puanını görmek için diploma notunu yaz. Sıralama OBP\'si 250 kabul edilerek gösteriliyor.</p>' : '';
    box.innerHTML = '<p class="ykstr-result-k">Tahmini YKS puanların</p>' +
      '<ul class="ykstr-plist">' + rows + '</ul>' + obpNot +
      (MOD === 'robot' ? '<button type="button" class="ykstr-btn ykstr-btn--cta" data-act="programlara"' + (P ? '' : ' disabled') + '>' + (P ? 'Bu puanlarla girebileceğin programları gör' : 'Netlerini girince programlar açılır') + ' <span aria-hidden="true">→</span></button>'
        : (CFG.robotUrl ? '<a class="ykstr-btn ykstr-btn--cta' + (P ? '' : ' is-off') + '" href="' + esc(CFG.robotUrl) + '#programlar" data-act="robota">Bu puanlarla girebileceğin programları gör <span aria-hidden="true">→</span></a>' : '')) +
      '<p class="ykstr-result-n">Ham puan OBP\'siz sınav puanıdır; yerleştirme puanı = ham puan + OBP × ' + fmt(S.kirik ? S.data.model.obp / 2 : S.data.model.obp, 2) + '. Sıralama, ÖSYM\'nin ' + S.data.meta.yil + ' yerleştirme puanı dağılımından tahmin edilir.</p>';
    mbarCiz();
  }

  var SONUC_GORUNUR = false;
  function mbarCiz() {
    var b = document.getElementById('ykstr-mbar'); if (!b) return;
    var best = enIyi(), p = best && S.puan[best];
    var g = S.view === 'puan' && root.classList.contains('ykstr-w-md') && !SONUC_GORUNUR;
    b.hidden = !g; if (!g) return;
    b.innerHTML = '<div><span>' + (best ? best + ' · tahmini' : 'Tahmini puan') + '</span><strong>' + (p ? fmt(p.yer != null ? p.yer : p.ham, 3) : '—') + '</strong></div>' +
      (MOD === 'robot' ? '<button type="button" class="ykstr-btn ykstr-btn--cta" data-act="programlara"' + (p ? '' : ' disabled') + '>Programlar <span aria-hidden="true">→</span></button>' : '<button type="button" class="ykstr-btn ykstr-btn--cta" data-act="sonuca">Sonuçlar <span aria-hidden="true">↓</span></button>');
  }

  /* ---------- olaylar ---------- */
  function olaylar() {
    var $ = function (id) { return document.getElementById(id); };
    if (window.IntersectionObserver) new IntersectionObserver(function (es) { SONUC_GORUNUR = es[0].isIntersecting; mbarCiz(); }, { threshold: 0.2 }).observe($('ykstr-sonuc'));
    root.querySelectorAll('.ykstr-tabs [data-tab]').forEach(function (b) { b.addEventListener('click', function () { sekme(b.getAttribute('data-tab')); }); });
    root.querySelector('.ykstr-calc-form').addEventListener('input', function (e) {
      if (e.target.id === 'ykstr-dipl') { S.dipl = e.target.value; store('dipl', S.dipl); }
      netHesap(); sonucCiz();
    });
    $('ykstr-kirik').addEventListener('change', function () { S.kirik = this.checked; store('kirik', S.kirik); netHesap(); sonucCiz(); });
    $('ykstr-ydtdil').addEventListener('change', function () { S.ydtDil = this.value; store('ydtdil', S.ydtDil); netHesap(); sonucCiz(); });
    $('ykstr-sil').addEventListener('click', function () { if (!window.confirm('Tüm netler silinsin mi?')) return; root.querySelectorAll('.ykstr-ders input').forEach(function (i) { i.value = ''; }); netHesap(); sonucCiz(); });
    root.querySelectorAll('.ykstr-steps [data-git]').forEach(function (b) {
      b.addEventListener('click', function () {
        var g = b.getAttribute('data-git');
        if (g === 'puan') return goster('puan');
        if (!S.pt) S.pt = enIyi() || 'SAY';
        goster('programlar'); ozetCiz(); listeCiz();
        if (g === 'tercih' && root.classList.contains('ykstr-w-md')) { $('ykstr-side').classList.add('is-open'); document.body.classList.add('ykstr-lock'); }
      });
    });
    if (MOD === 'robot') {
      var ilAra = $('ykstr-ilara'), ilMenu = $('ykstr-ilmenu');
      var ilMenuCiz = function () {
        var q = norm(ilAra.value);
        var list = S.data.iller.filter(function (il) { return S.iller.indexOf(il) < 0 && (!q || norm(il).indexOf(q) >= 0); }).slice(0, 12);
        ilMenu.innerHTML = list.map(function (il, i) { return '<li role="option" data-il="' + esc(il) + '"' + (i === 0 ? ' class="is-act"' : '') + '>' + esc(il) + '</li>'; }).join('');
        ilMenu.hidden = !list.length;
      };
      var ilEkle = function (il) { if (il && S.iller.indexOf(il) < 0) { S.iller.push(il); store('iller', S.iller); } ilAra.value = ''; ilMenu.hidden = true; ilCiz(); yenile(); };
      ilAra.addEventListener('focus', ilMenuCiz); ilAra.addEventListener('input', ilMenuCiz);
      ilAra.addEventListener('keydown', function (e) {
        var items = [].slice.call(ilMenu.querySelectorAll('li')), i = items.findIndex(function (x) { return x.classList.contains('is-act'); });
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); if (!items.length) return; if (items[i]) items[i].classList.remove('is-act'); i = (i + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length; items[i].classList.add('is-act'); }
        else if (e.key === 'Enter') { e.preventDefault(); if (items[i]) ilEkle(items[i].getAttribute('data-il')); }
        else if (e.key === 'Escape') ilMenu.hidden = true;
        else if (e.key === 'Backspace' && !ilAra.value && S.iller.length) { S.iller.pop(); store('iller', S.iller); ilCiz(); yenile(); }
      });
      ilMenu.addEventListener('mousedown', function (e) { var li = e.target.closest('li'); if (li) { e.preventDefault(); ilEkle(li.getAttribute('data-il')); } });
      document.addEventListener('click', function (e) { if (!e.target.closest('.ykstr-ilbox')) ilMenu.hidden = true; });
      $('ykstr-ilchips').addEventListener('click', function (e) { var b = e.target.closest('[data-rm]'); if (!b) return; S.iller = S.iller.filter(function (x) { return x !== b.getAttribute('data-rm'); }); store('iller', S.iller); ilCiz(); yenile(); });
      $('ykstr-unitur').addEventListener('change', function () { S.unitur = [].map.call(root.querySelectorAll('#ykstr-unitur input:checked'), function (i) { return i.value; }); store('unitur', S.unitur); yenile(); });
      $('ykstr-burs').addEventListener('change', function () { S.burs = this.value; yenile(); });
      $('ykstr-dil').addEventListener('change', function () { S.dil = this.value; yenile(); });
      $('ykstr-ogr').addEventListener('change', function () { S.ogr = this.value; yenile(); });
      $('ykstr-sirala').addEventListener('change', function () { S.sirala = this.value; yenile(); });
      $('ykstr-kapsam').addEventListener('change', function () { S.kapsam = this.checked ? 'uygun' : 'hepsi'; yenile(); });
      var t1, t2;
      $('ykstr-ara').addEventListener('input', function () { var v = this.value; clearTimeout(t1); t1 = setTimeout(function () { S.ara = v; yenile(); }, 200); });
      $('ykstr-uara').addEventListener('input', function () { var v = this.value; clearTimeout(t2); t2 = setTimeout(function () { S.uniAra = v; yenile(); }, 200); });
      $('ykstr-more').addEventListener('click', function () { S.goster += 30; listeCiz(); });
      $('ykstr-fab').addEventListener('click', function () { $('ykstr-side').classList.add('is-open'); document.body.classList.add('ykstr-lock'); });
    }
    root.addEventListener('click', function (e) {
      var b = e.target.closest('[data-act]'); if (!b) { if (e.target.closest('[data-close]')) modalKapat(); return; }
      var act = b.getAttribute('data-act'), kod = b.getAttribute('data-kod');
      if (act === 'robota') { if (!S.puan) { e.preventDefault(); return; } return; }
      if (act === 'sonuca') { $('ykstr-sonuc').scrollIntoView({ behavior: 'smooth', block: 'start' }); return; }
      if (act === 'programlara') { if (!S.puan) return; S.pt = (S.ptSecildi && S.pt && S.puan[S.pt]) ? S.pt : enIyi(); S.kapsam = 'uygun'; var kc = $('ykstr-kapsam'); if (kc) kc.checked = true; ozetCiz(); yenile(); goster('programlar'); return; }
      if (act === 'puana') { goster('puan'); return; }
      if (act === 'pt') { S.pt = b.getAttribute('data-pt'); S.ptSecildi = true; store('pt', S.pt); ozetCiz(); yenile(); return; }
      if (act === 'ekle' || act === 'cikar') tercihDegis(kod);
      else if (act === 'detay') detayAc(kod);
      else if (act === 'yukari' || act === 'asagi') { var i = S.tercih.indexOf(kod), j = i + (act === 'yukari' ? -1 : 1); if (j < 0 || j >= S.tercih.length) return; var x = S.tercih[i]; S.tercih[i] = S.tercih[j]; S.tercih[j] = x; store('tercih', S.tercih); tercihCiz(); }
      else if (act === 'temizle') { if (S.tercih.length && window.confirm('Tercih listen silinsin mi?')) { S.tercih = []; store('tercih', S.tercih); tercihCiz(); listeCiz(); } }
      else if (act === 'yazdir') yazdir();
      else if (act === 'paylas') paylas();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { modalKapat(); var sd = $('ykstr-side'); if (sd) sd.classList.remove('is-open'); document.body.classList.remove('ykstr-lock'); } });
  }

  /* ---------- programlar görünümü ---------- */
  function ozetCiz() {
    var o = document.getElementById('ykstr-ozet'); if (!o) return;
    if (!S.pt || (!S.ptSecildi && S.puan)) S.pt = enIyi() || S.pt || 'SAY';
    var chips = PT_SIRA.map(function (pt) {
      var p = S.puan && S.puan[pt];
      return '<button type="button" class="ykstr-ptchip' + (pt === S.pt ? ' is-on' : '') + '" data-act="pt" data-pt="' + pt + '" aria-pressed="' + (pt === S.pt) + '"><b>' + pt + '</b><span>' + (p && p.yer != null ? fmt(p.yer, 2) : p ? fmt(p.ham, 2) + ' ham' : PT_AD[pt]) + '</span></button>';
    }).join('');
    var p = S.puan && S.puan[S.pt];
    o.innerHTML = '<div class="ykstr-ozet-in"><div class="ykstr-ozet-p"><span>' + S.pt + ' yerleştirme puanın (tahmini)</span><strong>' + (p && p.yer != null ? fmt(p.yer, 3) : '–') + '</strong>' + (p && p.sira ? '<small>Tahmini sıralama ≈ ' + fmt(p.sira, 0) + '</small>' : '') + '</div>' +
      '<div class="ykstr-ptchips" role="group" aria-label="Puan türü">' + chips + '</div>' +
      '<button type="button" class="ykstr-btn ykstr-btn--sm ykstr-btn--ghost" data-act="puana">Puanı değiştir</button></div>' +
      (p && p.yer == null ? '<p class="ykstr-ozet-not">Diploma notunu girmediğin için programlar puanınla karşılaştırılamıyor. <button type="button" class="ykstr-link" data-act="puana">Diploma notunu ekle</button></p>' : '');
  }
  function ilCiz() { document.getElementById('ykstr-ilchips').innerHTML = S.iller.map(function (il) { return '<span class="ykstr-chip">' + esc(il) + '<button type="button" data-rm="' + esc(il) + '" aria-label="' + esc(il) + ' kaldır">' + ICO.x + '</button></span>'; }).join(''); }
  function yenile() { S.goster = 30; listeCiz(); }
  function etiketler(pr) {
    var t = ['<span class="ykstr-tag ykstr-tag--' + (pr.u[1] === 'Devlet' ? 'dev' : 'vak') + '">' + esc(pr.u[1]) + '</span>'];
    if (pr.burs) t.push('<span class="ykstr-tag">' + esc(S.data.burs[pr.burs]) + '</span>');
    if (pr.dil) t.push('<span class="ykstr-tag">' + esc(S.data.diller[pr.dil]) + '</span>');
    if (pr.ogr) t.push('<span class="ykstr-tag">' + esc(S.data.ogr[pr.ogr]) + '</span>');
    return t.join('');
  }
  function siraHtml(pr) { return pr.sira ? (pr.tahmini ? '≈' : '') + fmt(pr.sira, 0) : '–'; }
  function satir(pr) {
    var dr = durum(pr), sec = S.tercih.indexOf(pr.kod) >= 0;
    return '<li class="ykstr-item' + (sec ? ' is-sel' : '') + '">' +
      '<div class="ykstr-item-main"><h3><button type="button" data-act="detay" data-kod="' + pr.kod + '">' + esc(pr.ad) + '</button></h3>' +
        '<p class="ykstr-uni">' + esc(pr.u[0]) + '</p>' +
        '<p class="ykstr-meta"><span>' + ICO.pin + esc(pr.u[2] || '–') + '</span>' + etiketler(pr) + '</p></div>' +
      '<div class="ykstr-item-nums"><div><span>Taban puan</span><b>' + fmt(pr.min, 2) + '</b></div><div><span>Başarı sırası</span><b>' + siraHtml(pr) + '</b></div><div><span>Kontenjan</span><b>' + fmt(pr.kont, 0) + '</b></div></div>' +
      '<div class="ykstr-item-act">' + (dr ? '<span class="ykstr-durum is-' + dr.c + '">' + dr.ad + '<small>' + (dr.f >= 0 ? '+' : '') + fmt(dr.f, 1) + '</small></span>' : '') +
        '<button type="button" class="ykstr-add' + (sec ? ' is-on' : '') + '" data-act="ekle" data-kod="' + pr.kod + '" aria-pressed="' + sec + '">' + ICO.star + '<span>' + (sec ? 'Listemde' : 'Listeme ekle') + '</span></button></div>' +
    '</li>';
  }
  function listeCiz() {
    if (MOD !== 'robot') return;
    var res = filtrele(), h = document.getElementById('ykstr-r-h'), up = kullaniciPuan(S.pt);
    var tur = S.pt === 'TYT' ? 'ön lisans' : PT_AD[S.pt] + ' (' + S.pt + ') lisans';
    h.innerHTML = (S.kapsam === 'uygun' && up != null ? 'Puanınla girebileceğin ' : '') + '<b>' + fmt(res.length, 0) + '</b> ' + tur + ' programı' + (up == null ? '<small>Puanını hesaplarsan programların yanında yerleşme ihtimalini gösteririz.</small>' : '');
    var list = document.getElementById('ykstr-list');
    list.innerHTML = res.length ? res.slice(0, S.goster).map(satir).join('') : '<li class="ykstr-empty">Bu seçimlere uyan program yok. Filtreleri genişletmeyi ya da "Yalnızca puanımla yerleşebileceğim" seçeneğini kaldırmayı dene.</li>';
    document.getElementById('ykstr-more').hidden = res.length <= S.goster;
  }

  /* ---------- tercih listesi ---------- */
  function tercihDegis(kod) {
    var i = S.tercih.indexOf(kod);
    if (i >= 0) S.tercih.splice(i, 1);
    else { if (S.tercih.length >= MAX_TERCIH) { bildir('YKS\'de en fazla ' + MAX_TERCIH + ' tercih yapabilirsin.'); return; } S.tercih.push(kod); }
    store('tercih', S.tercih); tercihCiz(); listeCiz();
    var m = document.getElementById('ykstr-modal'); if (m && !m.hidden) detayAc(kod);
  }
  function tercihCiz() {
    var side = document.getElementById('ykstr-side'); if (!side) return;
    var n = S.tercih.length;
    var items = S.tercih.map(function (kod, i) {
      var pr = PROG_BY_KOD[kod], dr = durum(pr);
      return '<li><span class="ykstr-tn">' + (i + 1) + '</span><div class="ykstr-tb"><button type="button" data-act="detay" data-kod="' + kod + '">' + esc(pr.ad) + '</button><small>' + esc(pr.u[0]) + '</small><small>' + pr.pt + ' · Taban ' + fmt(pr.min, 2) + (dr ? ' · <span class="is-' + dr.c + '">' + dr.ad + '</span>' : '') + '</small></div>' +
        '<div class="ykstr-tbtn"><button type="button" data-act="yukari" data-kod="' + kod + '" aria-label="Yukarı taşı"' + (i === 0 ? ' disabled' : '') + '>' + ICO.up + '</button><button type="button" data-act="asagi" data-kod="' + kod + '" aria-label="Aşağı taşı"' + (i === n - 1 ? ' disabled' : '') + '>' + ICO.down + '</button><button type="button" data-act="cikar" data-kod="' + kod + '" aria-label="Listeden çıkar">' + ICO.x + '</button></div></li>';
    }).join('');
    side.innerHTML = '<div class="ykstr-side-in"><div class="ykstr-side-h"><h2 id="ykstr-t-h">Tercih listem</h2><span class="ykstr-count">' + n + '/' + MAX_TERCIH + '</span><button type="button" class="ykstr-side-x" aria-label="Kapat" onclick="this.closest(\'.ykstr-side\').classList.remove(\'is-open\');document.body.classList.remove(\'ykstr-lock\')">' + ICO.x + '</button></div>' +
      (n ? '<ol class="ykstr-tlist">' + items + '</ol><p class="ykstr-tip">Tercih sırası önemlidir: en çok istediğin programı en üste yaz. Listene puanının altında taban puanı olan güvenli seçenekler de ekle.</p>' +
        '<div class="ykstr-tact"><button type="button" class="ykstr-btn ykstr-btn--sm" data-act="yazdir">Yazdır / PDF</button><button type="button" class="ykstr-btn ykstr-btn--sm ykstr-btn--ghost" data-act="paylas">Bağlantıyı kopyala</button><button type="button" class="ykstr-link" data-act="temizle">Listeyi temizle</button></div>'
        : '<p class="ykstr-tempty">Programların yanındaki ' + ICO.star + ' düğmesiyle en fazla ' + MAX_TERCIH + ' program ekleyebilirsin. Listen bu cihazda saklanır.</p>') + '</div>';
    var fab = document.getElementById('ykstr-fab');
    fab.hidden = S.view !== 'programlar'; fab.innerHTML = ICO.star + '<span>Tercih listem</span><b>' + n + '</b>';
    var sn = document.getElementById('ykstr-step-n'); if (sn) { sn.textContent = n; sn.classList.toggle('is-on', n > 0); }
  }
  function bildir(t) { var b = el('div', { class: 'ykstr-toast', role: 'status' }); b.textContent = t; root.appendChild(b); setTimeout(function () { b.classList.add('is-on'); }, 10); setTimeout(function () { b.classList.remove('is-on'); setTimeout(function () { b.remove(); }, 300); }, 2800); }
  function paylas() {
    var u = location.origin + location.pathname + '?tercih=' + S.tercih.join(',');
    var ok = function () { bildir('Bağlantı kopyalandı. Bu bağlantıyı açan kişi tercih listeni görür.'); };
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(u).then(ok, function () { window.prompt('Bağlantıyı kopyala:', u); }); else window.prompt('Bağlantıyı kopyala:', u);
  }
  function yazdir() {
    var rows = S.tercih.map(function (kod, i) { var pr = PROG_BY_KOD[kod]; return '<tr><td>' + (i + 1) + '</td><td>' + pr.kod + '</td><td><b>' + esc(pr.ad) + '</b><br><small>' + esc(pr.u[0]) + ' · ' + esc(pr.fak) + '</small></td><td>' + pr.pt + '</td><td>' + fmt(pr.min, 3) + '</td><td>' + siraHtml(pr) + '</td><td>' + fmt(pr.kont, 0) + '</td></tr>'; }).join('');
    var p = S.puan && S.pt && S.puan[S.pt];
    var w = window.open('', '_blank'); if (!w) { bildir('Yazdırma penceresi açılamadı. Açılır pencere engelini kapat.'); return; }
    w.document.write('<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>YKS Tercih Listem</title><style>body{font-family:system-ui,sans-serif;color:#16233F;margin:28px}h1{font-size:22px;margin:0 0 4px}p{margin:0 0 14px;color:#4A5468;font-size:13px}table{width:100%;border-collapse:collapse;font-size:12.5px}th,td{border:1px solid #D5DAE3;padding:7px;text-align:left;vertical-align:top}th{background:#F5F6FA}small{color:#4A5468}</style></head><body><h1>YKS Tercih Listem</h1><p>' + (p && p.yer != null ? S.pt + ' yerleştirme puanı (tahmini): ' + fmt(p.yer, 3) + ' · ' : '') + 'Oluşturma: ' + new Date().toLocaleDateString(TR) + ' · soruindir.net YKS Tercih Robotu</p><table><thead><tr><th>#</th><th>Program kodu</th><th>Program</th><th>Puan türü</th><th>' + S.data.meta.yil + ' taban</th><th>Başarı sırası</th><th>Kontenjan</th></tr></thead><tbody>' + rows + '</tbody></table><p style="margin-top:14px">Taban puanlar ÖSYM ' + S.data.meta.yil + '-YKS en küçük puan tablolarından, başarı sıraları YÖK Atlas\'tan alınmıştır. Kesin tercihlerini ÖSYM tercih kılavuzunu okuyarak ÖSYM sisteminden yapmalısın.</p><script>window.onload=function(){window.print()}<\/script></body></html>');
    w.document.close();
  }

  /* ---------- program detayı ---------- */
  var sonOdak = null;
  function detayAc(kod) {
    var pr = PROG_BY_KOD[kod]; if (!pr) return;
    var m = document.getElementById('ykstr-modal'), body = document.getElementById('ykstr-modal-body');
    if (m.hidden) sonOdak = document.activeElement;
    var dr = durum(pr), sec = S.tercih.indexOf(kod) >= 0, up = kullaniciPuan(pr.pt);
    var atlas = 'https://yokatlas.yok.gov.tr/' + (pr.onlisans ? 'onlisans' : 'lisans') + '.php?y=' + pr.kod;
    var rows = [];
    var r = function (a, b) { if (b != null && b !== '') rows.push('<tr><th>' + a + '</th><td>' + b + '</td></tr>'); };
    r('Program kodu', pr.kod); r('Fakülte / yüksekokul', esc(pr.fak)); r('Şehir', esc(pr.u[2])); r('Üniversite türü', esc(pr.u[1]));
    r('Puan türü', pr.pt + ' (' + PT_AD[pr.pt] + ')'); r('Düzey', pr.onlisans ? 'Ön lisans' : 'Lisans');
    r('Burs / ücret', pr.burs ? esc(S.data.burs[pr.burs]) : (pr.u[1] === 'Devlet' ? 'Devlet üniversitesi' : '–'));
    r('Öğretim dili', esc(S.data.diller[pr.dil])); if (pr.ogr) r('Öğretim türü', esc(S.data.ogr[pr.ogr]));
    body.innerHTML = '<div class="ykstr-dhead"><p class="ykstr-dtur">' + esc(pr.u[0]) + '</p><h2 id="ykstr-m-h">' + esc(pr.ad) + '</h2><p class="ykstr-meta">' + etiketler(pr) + '</p></div>' +
      '<div class="ykstr-dnums"><div><span>' + S.data.meta.yil + ' taban puan</span><b>' + fmt(pr.min, 3) + '</b></div><div><span>Tavan puan</span><b>' + fmt(pr.max, 3) + '</b></div><div><span>Başarı sırası' + (pr.tahmini ? ' (tahmini)' : '') + '</span><b>' + siraHtml(pr) + '</b></div><div><span>Kontenjan / yerleşen</span><b>' + fmt(pr.kont, 0) + ' / ' + fmt(pr.yer, 0) + '</b></div></div>' +
      (dr ? '<p class="ykstr-dnote"><span class="ykstr-durum is-' + dr.c + '">' + dr.ad + '</span> Tahmini ' + pr.pt + ' yerleştirme puanın (' + fmt(up, 3) + ') bu programın ' + S.data.meta.yil + ' taban puanından ' + fmt(Math.abs(dr.f), 2) + ' puan ' + (dr.f >= 0 ? 'yüksek' : 'düşük') + '. Taban puanlar her yıl değişebilir.</p>' : '') +
      '<div class="ykstr-dsec"><h3>Program bilgileri</h3><table class="ykstr-dinfo"><tbody>' + rows.join('') + '</tbody></table></div>' +
      '<div class="ykstr-tact ykstr-dact"><button type="button" class="ykstr-btn' + (sec ? ' ykstr-btn--ghost' : '') + '" data-act="ekle" data-kod="' + pr.kod + '">' + (sec ? 'Tercih listemden çıkar' : 'Tercih listeme ekle') + '</button><a class="ykstr-btn ykstr-btn--ghost" href="' + atlas + '" target="_blank" rel="noopener">YÖK Atlas\'ta incele</a></div>' +
      '<p class="ykstr-dsrc">Taban/tavan puan ve kontenjan: ÖSYM ' + S.data.meta.yil + '-YKS ' + (pr.onlisans ? 'TABLO-3' : 'TABLO-4') + ' (genel kontenjan). Başarı sırası: ' + (pr.tahmini ? 'ÖSYM puan dağılımından tahmin' : 'YÖK Atlas') + '. Burs, ücret ve özel koşullar için ÖSYM tercih kılavuzunu kontrol et.</p>';
    m.hidden = false; document.body.classList.add('ykstr-lock');
    m.querySelector('.ykstr-modal-x').focus();
  }
  function modalKapat() { var m = document.getElementById('ykstr-modal'); if (!m || m.hidden) return; m.hidden = true; document.body.classList.remove('ykstr-lock'); if (sonOdak && sonOdak.focus) sonOdak.focus(); }

  /* ---------- genişlik ---------- */
  function genislik() {
    var w = root.getBoundingClientRect().width || window.innerWidth;
    root.classList.toggle('ykstr-w-md', w < 980);
    root.classList.toggle('ykstr-w-sm', w < 680);
    if (S.data) mbarCiz();
  }
  genislik();
  if (window.ResizeObserver) new ResizeObserver(genislik).observe(root); else window.addEventListener('resize', genislik);

  /* ---------- başlat ---------- */
  fetch(CFG.programlar).then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); }).then(function (j) {
    S.data = j; hazirla(); iskelet();
  }).catch(function () { root.innerHTML = '<p class="ykstr-empty">Veriler yüklenemedi. Sayfayı yenilemeyi dene.</p>'; });
})();
