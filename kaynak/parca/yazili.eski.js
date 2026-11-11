(function () {
  'use strict';
  var C = window.OSC_YZ, SNR = window.OSC_SNR || { tablolar: [] }, kok = document.getElementById('osc-yz');
  if (!C || !kok) return;
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function api(a, d) {
    if (C.yerel && YEREL[a]) return new Promise(function (ok, no) { try { ok(YEREL[a](d || {})); } catch (e) { no(e); } });
    var fd = new FormData(); fd.append('action', 'osc_yz_' + a); fd.append('nonce', C.nonce);
    Object.keys(d || {}).forEach(function (k) { fd.append(k, typeof d[k] === 'object' ? JSON.stringify(d[k]) : d[k]); });
    return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json().catch(function () { throw new Error('Sunucu hatası (' + r.status + ')'); }); }).then(function (j) { if (!j || !j.success) throw new Error((j && j.data) || 'Bir hata oluştu'); return j.data; });
  }
  function ls(k, v) { try { if (v === undefined) return JSON.parse(localStorage.getItem(k) || 'null'); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, JSON.stringify(v)); } catch (e) { return null; } }
  /* bağımsız (WordPress'siz) kurulum: Yazılılarım tarayıcıda saklanır */
  function kayitlar(v) { if (v === undefined) return ls('osc_yz_kayitlar') || []; ls('osc_yz_kayitlar', v.slice(0, 60)); }
  function tarihSaat() { var d = new Date(), z = function (n) { return ('0' + n).slice(-2); }; return z(d.getDate()) + '.' + z(d.getMonth() + 1) + '.' + d.getFullYear() + ' ' + z(d.getHours()) + ':' + z(d.getMinutes()); }
  var YEREL = {
    kaydet: function (d) {
      var v = typeof d.veri === 'string' ? JSON.parse(d.veri) : d.veri, l = kayitlar(), id = +d.id || Date.now(), k = { id: id, baslik: (v.bilgi && v.bilgi.baslik) || 'Yazılı', tarih: tarihSaat(), veri: v };
      l = l.filter(function (x) { return x.id !== id; }); l.unshift(k);
      try { kayitlar(l); } catch (e) { throw new Error('Tarayıcı belleği dolu; eski yazılılardan bazılarını silin.'); }
      if (v.sorular && v.sorular.some(function (q) { return q.kaynak === 'ogretmen'; })) {
        var ad = window.OSC_YZ_GONDERILEN = window.OSC_YZ_GONDERILEN || {}, im = JSON.stringify(v.sorular.filter(function (q) { return q.kaynak === 'ogretmen'; }).map(function (q) { return q.soru; }));
        if (!ad[im]) { ad[im] = 1; sunucu('havuza', { veri: { sinif: v.sinif, ders: v.ders, donem: v.donem, yazili: v.yazili, sorular: v.sorular } }).catch(function () {}); }
      }
      if (v.bilgi && v.bilgi.okul) ls('osc_yz_okul', v.bilgi.okul);
      return { id: id };
    },
    liste: function () { return kayitlar().map(function (x) { var v = x.veri || {}; return { id: x.id, baslik: x.baslik, tarih: x.tarih, sinif: v.sinif, ders: v.ders, yazili: v.yazili, senaryo: v.senaryo, n: (v.sorular || []).length }; }); },
    getir: function (d) { var x = kayitlar().filter(function (k) { return k.id === +d.id; })[0]; if (!x) throw new Error('Yazılı bulunamadı.'); return { id: x.id, veri: x.veri }; },
    sil: function (d) { kayitlar(kayitlar().filter(function (k) { return k.id !== +d.id; })); return true; }
  };
  function sunucu(a, d) {
    var fd = new FormData(); fd.append('action', 'osc_yz_' + a); fd.append('nonce', C.nonce);
    Object.keys(d || {}).forEach(function (k) { fd.append(k, typeof d[k] === 'object' ? JSON.stringify(d[k]) : d[k]); });
    return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  /* yapay zekânın bu tarayıcı için ürettiği (henüz onaylanmamış) sorular yeniden kullanılabilsin */
  function benim(ekle) { var l = ls('osc_yz_benim') || []; if (ekle) { [].concat(ekle).forEach(function (x) { if (x && l.indexOf(x) < 0) l.push(x); }); ls('osc_yz_benim', l.slice(-300)); } return l; }
  function bildir(m, tur) { var t = document.createElement('div'); t.className = 'yz-tost ' + (tur || ''); t.textContent = m; document.body.appendChild(t); setTimeout(function () { t.classList.add('on'); }, 10); setTimeout(function () { t.classList.remove('on'); setTimeout(function () { t.remove(); }, 300); }, 3800); }
  var ZOR = [['0', 'Karışık', 'Kolay, orta ve zor dengeli'], ['1', 'Kolay', 'Temel düzey'], ['2', 'Orta', 'Sınıf düzeyi'], ['3', 'Zor', 'Üst düzey düşünme']];
  var TUR = [['karisik', 'Karışık', 'Açık uçlu + test'], ['acik', 'Açık uçlu', 'Klasik yazılı'], ['test', 'Test', 'Çoktan seçmeli']];
  var KAY = { arsiv: ['Arşiv', 'a'], yz: ['Yapay zekâ', 'y'], ogretmen: ['Sizin sorunuz', 'o'], bos: ['Boş', 'b'] };

  function bugun() { var d = new Date(); return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
  function yeniDurum() { return { adim: 1, yil: SNR.yil || '2026-2027', sinif: 0, ders: '', donem: SNR.donem || 1, yazili: 1, senaryo: 0, ozel: {}, bilgi: { okul: C.okul || (C.yerel ? ls('osc_yz_okul') || '' : ''), baslik: '', ogretmen: C.ad || '', tarih: bugun(), sure: 40 }, zorluk: 0, tur: 'karisik', slotlar: [], sorular: [], anahtar: true, id: 0 }; }
  var S = ls('osc_yz_taslak') || yeniDurum(), sekme = 'yeni';
  function sakla() { ls('osc_yz_taslak', S); }

  /* ---------- senaryo verisi ---------- */
  function tablolar(sinif) { return SNR.tablolar.filter(function (t) { return t.sinif === +sinif; }); }
  function tablo() { for (var i = 0; i < SNR.tablolar.length; i++) { var t = SNR.tablolar[i]; if (t.sinif === +S.sinif && t.ders === S.ders) return t; } return null; }
  function kodu(x) { return x.kod || x.cikti.slice(0, 38); }
  function senaryoSlot(t, y, sn) {
    var o = [];
    t.satirlar.forEach(function (x) { var n = ((x.sayi || {})[String(y)] || {})[String(sn)] || 0; for (var i = 0; i < n; i++) o.push({ kod: kodu(x), cikti: x.cikti, konu: x.konu, ben: false }); });
    return o;
  }
  function ozelSlot(t) { var o = []; t.satirlar.forEach(function (x) { var n = S.ozel[kodu(x)] || 0; for (var i = 0; i < n; i++) o.push({ kod: kodu(x), cikti: x.cikti, konu: x.konu, ben: false }); }); return o; }
  function ulkeGeneli(t) { return !!(t.ulke && t.ulke[String(S.yazili)]) || !((t.senaryolar || {})[String(S.yazili)] || []).length; }
  function varsayilanBaslik() { return S.yil + ' EĞİTİM-ÖĞRETİM YILI ' + S.sinif + '. SINIF ' + trUp(S.ders) + ' DERSİ ' + S.donem + '. DÖNEM ' + S.yazili + '. YAZILI SINAVI'; }
  function trUp(s) { return String(s || '').replace(/i/g, 'İ').replace(/ı/g, 'I').toUpperCase(); }

  /* ---------- çerçeve ---------- */
  function cerceve(ic) {
    var ad = ['Seçim', 'Sınav bilgileri', 'Sorular', 'Çıktı'];
    kok.innerHTML = '<header class="yz-ust"><a class="yz-marka" href="' + esc(C.home) + '"><img src="' + C.logo + '" alt=""><span><b>Yazılı Hazırla</b><small>MEB senaryolarına uygun</small></span></a>' +
      '<nav class="yz-sek"><button data-sek="yeni" class="' + (sekme === 'yeni' ? 'on' : '') + '">Yeni yazılı</button><button data-sek="liste" class="' + (sekme === 'liste' ? 'on' : '') + '">Yazılılarım</button>' + (C.panel ? '<a href="' + esc(C.panel) + '">Panel</a>' : '') + '</nav></header>' +
      (sekme === 'yeni' ? '<ol class="yz-adimlar">' + ad.map(function (a, i) { var n = i + 1; return '<li class="' + (S.adim === n ? 'on' : (S.adim > n ? 'ok' : '')) + '"><button data-git="' + n + '"' + (n > enFazlaAdim() ? ' disabled' : '') + '><i>' + (S.adim > n ? '✓' : n) + '</i><span>' + a + '</span></button></li>'; }).join('') + '</ol>' : '') +
      '<main class="yz-ana">' + ic + '</main>';
    kok.querySelectorAll('[data-sek]').forEach(function (b) { b.onclick = function () { sekme = b.dataset.sek; ciz(); }; });
    kok.querySelectorAll('[data-git]').forEach(function (b) { b.onclick = function () { S.adim = +b.dataset.git; sakla(); ciz(); window.scrollTo({ top: kok.offsetTop - 20, behavior: 'smooth' }); }; });
  }
  function enFazlaAdim() { if (!S.slotlar.length) return 1; if (!S.sorular.length) return 2; return 4; }
  function ciz() {
    if (sekme === 'liste') return vListe();
    [null, v1, v2, v3, v4][S.adim]();
  }

  /* ---------- 1. seçim ---------- */
  function v1() {
    var siniflar = []; SNR.tablolar.forEach(function (t) { if (siniflar.indexOf(t.sinif) < 0) siniflar.push(t.sinif); }); siniflar.sort(function (a, b) { return a - b; });
    var tl = S.sinif ? tablolar(S.sinif) : [], t = tablo();
    var h = '<section class="yz-kart"><h2>Yazılınızı seçin</h2><p class="yz-not">Seçimlerinize göre MEB ' + esc(S.yil) + ' konu-soru dağılım tablosundaki senaryolar listelenir.</p>' +
      '<div class="yz-izgara2"><label class="yz-alan"><span>Eğitim-öğretim yılı</span><select data-k="yil"><option>' + esc(S.yil) + '</option></select></label>' +
      '<div class="yz-alan"><span>Dönem</span><div class="yz-seg">' + [1, 2].map(function (d) { var ok = d === (SNR.donem || 1); return '<button data-k="donem" data-v="' + d + '" class="' + (S.donem === d ? 'on' : '') + '"' + (ok ? '' : ' disabled title="2. dönem senaryoları MEB tarafından yayımlanınca eklenecek"') + '>' + d + '. dönem</button>'; }).join('') + '</div></div></div>' +
      '<div class="yz-alan"><span>Sınıf</span><div class="yz-cipler">' + siniflar.map(function (s) { return '<button data-k="sinif" data-v="' + s + '" class="' + (S.sinif === s ? 'on' : '') + '">' + s + '. sınıf</button>'; }).join('') + '</div></div>' +
      (S.sinif ? '<div class="yz-alan"><span>Ders</span><div class="yz-cipler">' + tl.map(function (x) { return '<button data-k="ders" data-v="' + esc(x.ders) + '" class="' + (S.ders === x.ders ? 'on' : '') + '">' + esc(x.ders) + '</button>'; }).join('') + '</div></div>' : '') +
      (t ? '<div class="yz-alan"><span>Yazılı</span><div class="yz-seg">' + [1, 2].map(function (y) { return '<button data-k="yazili" data-v="' + y + '" class="' + (S.yazili === y ? 'on' : '') + '">' + y + '. yazılı</button>'; }).join('') + '</div></div>' : '') + '</section>';
    if (t) {
      var ulke = ulkeGeneli(t), sl = (t.senaryolar || {})[String(S.yazili)] || [];
      h += '<section class="yz-kart"><h2>MEB senaryosu</h2>' + (ulke ? '<div class="yz-bilgi">🇹🇷 <b>' + S.sinif + '. sınıf ' + esc(S.ders) + ' ' + S.yazili + '. yazılı</b> MEB tarafından <b>ülke genelinde ortak sınav</b> olarak yapılır; okul senaryosu yayımlanmadı. İsterseniz aşağıdan kendi dağılımınızla bir deneme yazılısı hazırlayabilirsiniz.</div>' : '<p class="yz-not">Her senaryo, hangi öğrenme çıktısından kaç soru sorulacağını belirler. Bir senaryo seçin.</p>') +
        '<div class="yz-senaryolar">' + sl.map(function (sn) {
          var sl2 = senaryoSlot(t, S.yazili, sn), konu = {};
          sl2.forEach(function (x) { konu[x.konu] = (konu[x.konu] || 0) + 1; });
          return '<button class="yz-snr' + (S.senaryo === sn ? ' on' : '') + '" data-snr="' + sn + '"><b>' + sn + '. Senaryo</b><em>' + sl2.length + ' soru</em><ul>' + Object.keys(konu).map(function (k) { return '<li><span>' + esc(k) + '</span><i>' + konu[k] + '</i></li>'; }).join('') + '</ul></button>';
        }).join('') + '<button class="yz-snr ozel' + (S.senaryo === 'ozel' ? ' on' : '') + '" data-snr="ozel"><b>Kendi dağılımım</b><em>' + (S.senaryo === 'ozel' ? ozelSlot(t).length + ' soru' : 'özel') + '</em><p>Öğrenme çıktılarını ve soru sayılarını kendiniz belirleyin.</p></button></div>' +
        (S.senaryo === 'ozel' ? '<div class="yz-ozel">' + t.satirlar.map(function (x) { var k = kodu(x), n = S.ozel[k] || 0; return '<div class="yz-oz' + (n ? ' var' : '') + '"><div><small>' + esc(x.kod || '') + ' · ' + esc(x.konu) + '</small><p>' + esc(x.cikti) + '</p></div><div class="yz-sayac"><button data-oz="' + esc(k) + '" data-d="-1">−</button><b>' + n + '</b><button data-oz="' + esc(k) + '" data-d="1">+</button></div></div>'; }).join('') + '</div>' : '') +
        (S.senaryo && !(S.senaryo !== 'ozel' && ulke) ? '<div class="yz-tablo-on">' + onizlemeTablo(t) + '</div>' : '') +
        '</section>';
    }
    h += '<div class="yz-alt-cubuk"><span>' + (t && S.senaryo ? (S.senaryo === 'ozel' ? ozelSlot(t).length : senaryoSlot(t, S.yazili, S.senaryo).length) + ' soruluk yazılı' : 'Sınıf, ders, yazılı ve senaryo seçin') + '</span><button class="yz-btn ana" id="yz-uygula"' + (t && S.senaryo && (S.senaryo !== 'ozel' || ozelSlot(t).length) ? '' : ' disabled') + '>Seçimi uygula →</button></div>';
    cerceve(h);
    kok.querySelectorAll('[data-k]').forEach(function (b) {
      var f = function () {
        var k = b.dataset.k, v = b.dataset.v !== undefined ? b.dataset.v : b.value;
        if (k === 'sinif') { S.sinif = +v; S.ders = ''; S.senaryo = 0; }
        else if (k === 'ders') { S.ders = v; S.senaryo = 0; S.ozel = {}; }
        else if (k === 'yazili') { S.yazili = +v; S.senaryo = 0; }
        else if (k === 'donem') S.donem = +v;
        sakla(); v1();
      };
      if (b.tagName === 'BUTTON') b.onclick = f;
    });
    kok.querySelectorAll('[data-snr]').forEach(function (b) { b.onclick = function () { S.senaryo = b.dataset.snr === 'ozel' ? 'ozel' : +b.dataset.snr; sakla(); v1(); }; });
    kok.querySelectorAll('[data-oz]').forEach(function (b) { b.onclick = function () { var k = b.dataset.oz; S.ozel[k] = Math.max(0, Math.min(6, (S.ozel[k] || 0) + +b.dataset.d)); sakla(); v1(); }; });
    var u = document.getElementById('yz-uygula');
    if (u) u.onclick = function () {
      var yeni = S.senaryo === 'ozel' ? ozelSlot(t) : senaryoSlot(t, S.yazili, S.senaryo);
      var ayni = S.slotlar.length === yeni.length && S.slotlar.every(function (x, i) { return x.kod === yeni[i].kod; });
      if (!ayni) { S.slotlar = yeni; S.sorular = []; S.id = 0; }
      if (!S.bilgi.baslik || !ayni) S.bilgi.baslik = varsayilanBaslik();
      S.adim = 2; sakla(); ciz();
    };
  }
  function onizlemeTablo(t) {
    var sl = S.senaryo === 'ozel' ? ozelSlot(t) : senaryoSlot(t, S.yazili, S.senaryo), g = [];
    sl.forEach(function (x) { var s = g[g.length - 1]; if (s && s.kod === x.kod) s.n++; else g.push({ kod: x.kod, konu: x.konu, cikti: x.cikti, n: 1 }); });
    if (!g.length) return '';
    return '<table class="yz-tbl"><thead><tr><th>Konu</th><th>Öğrenme çıktısı</th><th>Soru</th></tr></thead><tbody>' + g.map(function (x) { return '<tr><td>' + esc(x.konu) + '</td><td><small>' + esc(x.kod.indexOf('.') > 0 ? x.kod : '') + '</small> ' + esc(x.cikti) + '</td><td class="n">' + x.n + '</td></tr>'; }).join('') + '</tbody></table>';
  }

  /* ---------- 2. bilgiler ---------- */
  function v2() {
    var b = S.bilgi;
    var h = '<section class="yz-kart"><h2>Sınav bilgileri</h2><div class="yz-izgara2">' +
      '<label class="yz-alan genis"><span>Okul adı</span><input data-b="okul" value="' + esc(b.okul) + '" placeholder="Örn. Atatürk Ortaokulu"></label>' +
      '<label class="yz-alan genis"><span>Sınav başlığı</span><input data-b="baslik" value="' + esc(b.baslik) + '"></label>' +
      '<label class="yz-alan"><span>Öğretmen adı</span><input data-b="ogretmen" value="' + esc(b.ogretmen) + '"></label>' +
      '<div class="yz-izgara2 ic"><label class="yz-alan"><span>Sınav tarihi</span><input type="date" data-b="tarih" value="' + esc(b.tarih) + '"></label><label class="yz-alan"><span>Süre (dakika)</span><input type="number" min="10" max="240" data-b="sure" value="' + esc(b.sure) + '"></label></div>' +
      '</div></section>' +
      '<section class="yz-kart"><h2>Soru tercihleri</h2><div class="yz-izgara2"><div class="yz-alan"><span>Zorluk</span><div class="yz-secim">' + ZOR.map(function (z) { return '<button data-z="' + z[0] + '" class="' + (String(S.zorluk) === z[0] ? 'on' : '') + '"><b>' + z[1] + '</b><small>' + z[2] + '</small></button>'; }).join('') + '</div></div>' +
      '<div class="yz-alan"><span>Soru türü</span><div class="yz-secim">' + TUR.map(function (z) { return '<button data-t="' + z[0] + '" class="' + (S.tur === z[0] ? 'on' : '') + '"><b>' + z[1] + '</b><small>' + z[2] + '</small></button>'; }).join('') + '</div></div></div></section>' +
      '<section class="yz-kart"><div class="yz-bas"><div><h2>Soru dağılımı</h2><p class="yz-not">Kendiniz yazmak istediğiniz soruları işaretleyin; geri kalanı otomatik hazırlanır.</p></div><span class="yz-rozet">' + S.slotlar.filter(function (x) { return x.ben; }).length + ' / ' + S.slotlar.length + ' soruyu siz yazacaksınız</span></div>' +
      '<div class="yz-hizli">✍️ Kendim yazacağım soru sayısı <div class="yz-sayac"><button data-hz="-1">−</button><b>' + S.slotlar.filter(function (x) { return x.ben; }).length + '</b><button data-hz="1">+</button></div><small>Son sorulardan başlayarak işaretlenir; aşağıdan tek tek de seçebilirsiniz.</small></div>' + '<div class="yz-slotlar">' + S.slotlar.map(function (x, i) { return '<label class="yz-slot' + (x.ben ? ' ben' : '') + '"><i>' + (i + 1) + '</i><div><small>' + esc(x.kod.indexOf('.') > 0 ? x.kod + ' · ' : '') + esc(x.konu) + '</small><p>' + esc(x.cikti) + '</p></div><span class="yz-anahtar"><input type="checkbox" data-ben="' + i + '"' + (x.ben ? ' checked' : '') + '><em></em>Ben yazacağım</span></label>'; }).join('') + '</div></section>' +
      '<div class="yz-alt-cubuk"><button class="yz-btn" data-git2="1">← Geri</button><button class="yz-btn ana" id="yz-hazirla">' + (S.sorular.length ? 'Soruları yeniden hazırla ✨' : 'Soruları hazırla ✨') + '</button>' + (S.sorular.length ? '<button class="yz-btn" data-git2="3">Mevcut sorulara git →</button>' : '') + '</div>';
    cerceve(h);
    kok.querySelectorAll('[data-b]').forEach(function (e) { e.oninput = function () { S.bilgi[e.dataset.b] = e.dataset.b === 'sure' ? +e.value : e.value; sakla(); }; });
    kok.querySelectorAll('[data-z]').forEach(function (e) { e.onclick = function () { S.zorluk = +e.dataset.z; sakla(); v2(); }; });
    kok.querySelectorAll('[data-t]').forEach(function (e) { e.onclick = function () { S.tur = e.dataset.t; sakla(); v2(); }; });
    kok.querySelectorAll('[data-hz]').forEach(function (e) { e.onclick = function () {
      var n = S.slotlar.filter(function (x) { return x.ben; }).length + +e.dataset.hz; n = Math.max(0, Math.min(S.slotlar.length, n));
      S.slotlar.forEach(function (x, i) { x.ben = i >= S.slotlar.length - n; }); sakla(); v2(); }; });
    kok.querySelectorAll('[data-ben]').forEach(function (e) { e.onchange = function () { S.slotlar[+e.dataset.ben].ben = e.checked; sakla(); v2(); }; });
    kok.querySelectorAll('[data-git2]').forEach(function (e) { e.onclick = function () { S.adim = +e.dataset.git2; sakla(); ciz(); }; });
    document.getElementById('yz-hazirla').onclick = function () {
      if (S.sorular.length && !confirm('Mevcut sorular silinip yeniden hazırlanacak. Devam edilsin mi?')) return;
      hazirla(this);
    };
  }
  function bosSoru(tur) { return { type: tur === 'test' ? 'multiple' : 'classic', paragraph: '', root: '', options: tur === 'test' ? { A: '', B: '', C: '', D: '' } : { A: '' }, correct: '' }; }
  function hazirla(btn) {
    btn.disabled = true; var eski = btn.innerHTML;
    var oto = S.slotlar.filter(function (x) { return !x.ben; }).length;
    btn.innerHTML = '<span class="yz-don"></span> Hazırlanıyor…';
    var yuk = document.createElement('div'); yuk.className = 'yz-perde'; yuk.innerHTML = '<div><span class="yz-don b"></span><b>Sorular hazırlanıyor</b><p>' + oto + ' soru havuzdan seçiliyor; eksik kalırsa yapay zekâ yazıyor. Bu işlem 10-40 saniye sürebilir.</p></div>'; document.body.appendChild(yuk);
    api('hazirla', { veri: { benim: benim(), sinif: S.sinif, ders: S.ders, yazili: S.yazili, zorluk: S.zorluk, tur: S.tur, slotlar: S.slotlar.map(function (x) { return { kod: x.kod, ben: x.ben ? 1 : 0 }; }) } }).then(function (d) {
      benim(d.sorular.filter(function (r) { return r && r.kaynak === 'yz'; }).map(function (r) { return r.id; }));
      var n = S.slotlar.length, p = puanDagit(n);
      S.sorular = S.slotlar.map(function (x, i) {
        var r = d.sorular[i];
        if (x.ben) return { kod: x.kod, id: 0, kaynak: 'ogretmen', soru: bosSoru(S.tur), puan: p[i], duzen: true, gorulen: [] };
        if (!r) return { kod: x.kod, id: 0, kaynak: 'bos', soru: bosSoru(S.tur), puan: p[i], duzen: false, gorulen: [] };
        return { kod: x.kod, id: r.id, kaynak: r.kaynak, soru: r.soru, puan: p[i], zorluk: r.zorluk, duzen: false, gorulen: [r.id] };
      });
      S.adim = 3; sakla(); yuk.remove(); ciz(); window.scrollTo({ top: kok.offsetTop - 20, behavior: 'smooth' });
      if (d.uyari) bildir(d.uyari, 'uyari');
      var bos = S.sorular.filter(function (q) { return q.kaynak === 'bos'; }).length;
      if (bos) bildir(bos + ' soru için havuzda uygun soru bulunamadı. “Değiştir” ile tekrar deneyebilir ya da kendiniz yazabilirsiniz.', 'uyari');
    }).catch(function (e) { yuk.remove(); btn.disabled = false; btn.innerHTML = eski; bildir(e.message, 'hata'); });
  }
  function puanDagit(n) { if (!n) return []; var b = Math.floor(100 / n), k = 100 - b * n, o = []; for (var i = 0; i < n; i++) o.push(b + (i < k ? 1 : 0)); return o; }

  /* ---------- 3. sorular ---------- */
  function soruIc(q, cevapGoster) {
    var s = q.soru || {}, op = s.options || {}, h = '';
    if (s.paragraph) h += '<div class="yz-p">' + s.paragraph + '</div>';
    h += '<div class="yz-r">' + (s.root || '<span class="yz-sil">Soru metni yok</span>') + '</div>';
    if (s.type === 'multiple' || s.type === 'truefalse') {
      var ks = Object.keys(op).filter(function (k) { return op[k]; });
      h += '<div class="yz-sec' + (ks.every(function (k) { return String(op[k]).replace(/<[^>]+>/g, '').length < 28 && op[k].indexOf('<img') < 0; }) ? ' kisa' : '') + '">' + ks.map(function (k) { return '<div class="' + (cevapGoster && s.correct === k ? 'd' : '') + '"><b>' + k + ')</b> ' + op[k] + '</div>'; }).join('') + '</div>';
    } else if (cevapGoster) h += '<div class="yz-cvp"><b>Cevap:</b> ' + (op.A || '<i>cevap anahtarı yok — düzenleyerek ekleyebilirsiniz</i>') + '</div>';
    return h;
  }
  function v3() {
    var t = tablo(), top = S.sorular.reduce(function (a, q) { return a + (+q.puan || 0); }, 0);
    var h = '<div class="yz-bas ust"><div><h2 class="yz-h">' + esc(S.bilgi.baslik || varsayilanBaslik()) + '</h2><p class="yz-not">' + S.sorular.length + ' soru · ' + (S.senaryo === 'ozel' ? 'Kendi dağılımı' : S.senaryo + '. senaryo') + ' · Beğenmediğiniz soruyu <b>Değiştir</b> ile yenileyin ya da <b>Düzenle</b> ile kendiniz yazın.</p></div></div>' +
      '<div class="yz-sorular">' + S.sorular.map(function (q, i) {
        var x = S.slotlar[i] || {}, ky = KAY[q.kaynak] || KAY.arsiv;
        return '<article class="yz-soru' + (q.duzen ? ' duzen' : '') + (q.kaynak === 'bos' ? ' bos' : '') + '" data-i="' + i + '"><div class="yz-soru-ust"><i class="no">' + (i + 1) + '</i><div class="ck"><small>' + esc(x.kod && x.kod.indexOf('.') > 0 ? x.kod : '') + ' · ' + esc(x.konu || '') + '</small><span title="' + esc(x.cikti) + '">' + esc(x.cikti || '') + '</span></div>' +
          '<span class="yz-k ' + ky[1] + '">' + ky[0] + '</span><label class="yz-puan"><input type="number" min="0" max="100" value="' + (q.puan || 0) + '" data-puan="' + i + '"><span>puan</span></label></div>' +
          (q.duzen ? duzenleyici(q, i) : '<div class="yz-soru-ic">' + (q.kaynak === 'bos' ? '<div class="yz-bos">Bu öğrenme çıktısı için havuzda soru yok. <b>Değiştir</b>’e basarak yapay zekâya yazdırabilir ya da <b>Düzenle</b> ile kendiniz yazabilirsiniz.</div>' : soruIc(q, true)) + '</div>' +
          '<div class="yz-soru-alt"><button class="yz-btn kucuk" data-x="degistir" data-i="' + i + '">↻ Değiştir</button><button class="yz-btn kucuk" data-x="duzenle" data-i="' + i + '">✎ Düzenle</button><span class="bosluk"></span>' + (i ? '<button class="yz-btn ikon" data-x="yukari" data-i="' + i + '" title="Yukarı taşı">↑</button>' : '') + (i < S.sorular.length - 1 ? '<button class="yz-btn ikon" data-x="asagi" data-i="' + i + '" title="Aşağı taşı">↓</button>' : '') + '</div>') +
          '</article>';
      }).join('') + '</div>' +
      '<div class="yz-alt-cubuk yapiskan"><span class="yz-toplam' + (top === 100 ? ' tamam' : ' eksik') + '">Toplam <b>' + top + '</b> / 100 puan</span><button class="yz-btn" id="yz-esit">Puanları eşit dağıt</button><span class="bosluk"></span><button class="yz-btn" data-git2="2">← Bilgiler</button><button class="yz-btn ana" id="yz-cikti">Çıktıya geç →</button></div>';
    cerceve(h);
    kok.querySelectorAll('[data-puan]').forEach(function (e) { e.oninput = function () { S.sorular[+e.dataset.puan].puan = Math.max(0, +e.value || 0); sakla(); var top2 = S.sorular.reduce(function (a, q) { return a + (+q.puan || 0); }, 0), el = kok.querySelector('.yz-toplam'); el.className = 'yz-toplam' + (top2 === 100 ? ' tamam' : ' eksik'); el.innerHTML = 'Toplam <b>' + top2 + '</b> / 100 puan'; }; });
    document.getElementById('yz-esit').onclick = function () { var p = puanDagit(S.sorular.length); S.sorular.forEach(function (q, i) { q.puan = p[i]; }); sakla(); v3(); };
    kok.querySelectorAll('[data-git2]').forEach(function (e) { e.onclick = function () { S.adim = +e.dataset.git2; sakla(); ciz(); }; });
    document.getElementById('yz-cikti').onclick = function () {
      var acik = S.sorular.filter(function (q) { return q.duzen; }).length, bos = S.sorular.filter(function (q) { return q.kaynak === 'bos' || !(q.soru.root || q.soru.paragraph); }).length;
      if (acik) return bildir('Düzenlemesi bitmemiş ' + acik + ' soru var. Önce “Tamam”a basın.', 'uyari');
      if (bos && !confirm(bos + ' soru boş. Yine de devam edilsin mi?')) return;
      var top2 = S.sorular.reduce(function (a, q) { return a + (+q.puan || 0); }, 0);
      if (top2 !== 100 && !confirm('Toplam puan ' + top2 + '. Yine de devam edilsin mi?')) return;
      S.adim = 4; sakla(); ciz(); kaydet(true);
    };
    kok.querySelectorAll('[data-x]').forEach(function (b) { b.onclick = function () { islem(b.dataset.x, +b.dataset.i, b); }; });
    kok.querySelectorAll('.yz-ed').forEach(edBagla);
  }
  function islem(x, i, b) {
    var q = S.sorular[i];
    if (x === 'yukari' || x === 'asagi') { var j = x === 'yukari' ? i - 1 : i + 1; var t1 = S.sorular[i]; S.sorular[i] = S.sorular[j]; S.sorular[j] = t1; var t2 = S.slotlar[i]; S.slotlar[i] = S.slotlar[j]; S.slotlar[j] = t2; sakla(); v3(); return; }
    if (x === 'duzenle') { if (q.kaynak === 'bos') { q.kaynak = 'ogretmen'; q.soru = bosSoru(S.tur); } q.duzen = true; sakla(); v3(); return; }
    if (x === 'degistir') {
      b.disabled = true; b.innerHTML = '<span class="yz-don"></span> Aranıyor…';
      var haric = []; S.sorular.forEach(function (s) { if (s.id) haric.push(s.id); (s.gorulen || []).forEach(function (g) { haric.push(g); }); });
      api('degistir', { veri: { benim: benim(), sinif: S.sinif, ders: S.ders, kod: q.kod, zorluk: S.zorluk, tur: S.tur, haric: haric } }).then(function (r) {
        if (r.kaynak === 'yz') benim(r.id);
        q.id = r.id; q.kaynak = r.kaynak; q.soru = r.soru; q.zorluk = r.zorluk; q.duzen = false; (q.gorulen = q.gorulen || []).push(r.id); sakla(); v3();
        var el = kok.querySelector('.yz-soru[data-i="' + i + '"]'); if (el) { el.classList.add('yeni'); }
      }).catch(function (e) { b.disabled = false; b.innerHTML = '↻ Değiştir'; bildir(e.message, 'hata'); });
    }
  }
  function duzenleyici(q, i) {
    var s = q.soru, tur = s.type || 'classic', op = s.options || {};
    var harfler = tur === 'truefalse' ? ['A', 'B'] : ['A', 'B', 'C', 'D', 'E'];
    return '<div class="yz-ed" data-i="' + i + '"><div class="yz-ed-tur">' + [['classic', 'Açık uçlu'], ['multiple', 'Çoktan seçmeli'], ['gap', 'Boşluk doldurma'], ['truefalse', 'Doğru / Yanlış']].map(function (t) { return '<button data-tur="' + t[0] + '" class="' + (tur === t[0] ? 'on' : '') + '">' + t[1] + '</button>'; }).join('') + '</div>' +
      '<div class="yz-alan"><span>Öncül / metin <small>(isteğe bağlı)</small></span><div class="yz-ce" contenteditable="true" data-f="paragraph" data-ph="Soru için okuma metni, tablo açıklaması vb.">' + (s.paragraph || '') + '</div></div>' +
      '<div class="yz-alan"><span>Soru</span><div class="yz-ce buyuk" contenteditable="true" data-f="root" data-ph="Soru kökünü yazın…">' + (s.root || '') + '</div></div>' +
      (tur === 'multiple' || tur === 'truefalse' ? '<div class="yz-ed-sik">' + harfler.map(function (h) { var v = tur === 'truefalse' && !op[h] ? (h === 'A' ? 'Doğru' : 'Yanlış') : (op[h] || ''); return '<div class="yz-sk' + (s.correct === h ? ' d' : '') + '"><input type="radio" name="yz-d' + i + '" value="' + h + '"' + (s.correct === h ? ' checked' : '') + ' title="Doğru cevap"><b>' + h + '</b><div class="yz-ce" contenteditable="true" data-sik="' + h + '" data-ph="' + (h === 'E' ? 'E şıkkı (isteğe bağlı)' : h + ' şıkkı') + '">' + v + '</div></div>'; }).join('') + '<p class="yz-not">Doğru şıkkı soldaki daireden işaretleyin.</p></div>'
        : '<div class="yz-alan"><span>Cevap / puanlama anahtarı</span><div class="yz-ce" contenteditable="true" data-f="cevap" data-ph="Beklenen cevap (cevap anahtarında görünür)">' + (op.A || '') + '</div></div>') +
      '<div class="yz-ed-alt"><button class="yz-btn" data-ed="iptal">Vazgeç</button><button class="yz-btn ana" data-ed="tamam">Tamam</button></div></div>';
  }
  function temizHtml(h) { var d = document.createElement('div'); d.innerHTML = h; d.querySelectorAll('script,style,iframe,object,embed,link,meta').forEach(function (x) { x.remove(); }); d.querySelectorAll('*').forEach(function (x) { [].slice.call(x.attributes).forEach(function (a) { if (/^on/i.test(a.name) || (/^(href|src)$/i.test(a.name) && /^\s*javascript:/i.test(a.value))) x.removeAttribute(a.name); }); }); var t = d.innerHTML.replace(/^(<br>|\s|&nbsp;)+|(<br>|\s|&nbsp;)+$/g, ''); return t; }
  function edOku(el, q) {
    var s = q.soru, tur = (el.querySelector('[data-tur].on') || {}).dataset ? el.querySelector('[data-tur].on').dataset.tur : s.type;
    var o = { type: tur, paragraph: temizHtml(el.querySelector('[data-f=paragraph]').innerHTML), root: temizHtml(el.querySelector('[data-f=root]').innerHTML), options: {}, correct: '' };
    if (tur === 'multiple' || tur === 'truefalse') { el.querySelectorAll('[data-sik]').forEach(function (c) { o.options[c.dataset.sik] = temizHtml(c.innerHTML); }); var r = el.querySelector('input[type=radio]:checked'); o.correct = r ? r.value : ''; }
    else { var c = el.querySelector('[data-f=cevap]'); o.options = { A: c ? temizHtml(c.innerHTML) : '' }; }
    return o;
  }
  function edBagla(el) {
    var i = +el.dataset.i, q = S.sorular[i], yedek = JSON.stringify(q.soru);
    el.querySelectorAll('[data-tur]').forEach(function (b) { b.onclick = function (e) { e.preventDefault(); var o = edOku(el, q); o.type = b.dataset.tur; if ((o.type === 'multiple') && Object.keys(o.options).length < 4) o.options = { A: o.options.A && q.soru.type === 'multiple' ? o.options.A : '', B: '', C: '', D: '' }; if (o.type === 'truefalse') o.options = { A: 'Doğru', B: 'Yanlış' }; if ((o.type === 'classic' || o.type === 'gap') && q.soru.type !== 'classic' && q.soru.type !== 'gap') o.options = { A: '' }; q.soru = o; sakla(); v3(); }; });
    el.querySelectorAll('input[type=radio]').forEach(function (r) { r.onchange = function () { el.querySelectorAll('.yz-ed-sik .yz-sk').forEach(function (l) { l.classList.toggle('d', l.contains(r)); }); }; });
    el.querySelectorAll('.yz-ce').forEach(function (c) { c.addEventListener('paste', function (e) { var t = (e.clipboardData || window.clipboardData).getData('text/plain'); if (t && !(e.clipboardData.types || []).includes('Files')) { e.preventDefault(); document.execCommand('insertText', false, t); } }); });
    el.querySelector('[data-ed=tamam]').onclick = function () {
      var o = edOku(el, q);
      if (!o.root && !o.paragraph) return bildir('Soru metni boş olamaz.', 'uyari');
      if ((o.type === 'multiple') && !o.correct) return bildir('Doğru şıkkı işaretleyin.', 'uyari');
      if (JSON.stringify(o) !== yedek) { if (q.kaynak !== 'ogretmen') { q.kaynak = 'ogretmen'; } q.id = 0; }
      q.soru = o; q.duzen = false; sakla(); v3();
    };
    el.querySelector('[data-ed=iptal]').onclick = function () { q.duzen = false; if (q.kaynak === 'ogretmen' && !(q.soru.root || q.soru.paragraph)) q.kaynak = 'bos'; sakla(); v3(); };
  }

  /* ---------- 4. çıktı ---------- */
  function tarihTr(s) { if (!s) return ''; var p = s.split('-'); return p.length === 3 ? p[2] + '.' + p[1] + '.' + p[0] : s; }
  function kagit(anahtar, word) {
    var b = S.bilgi;
    var soruHtml = S.sorular.map(function (q, i) {
      var s = q.soru || {}, op = s.options || {}, h = '';
      var alan = (s.type === 'classic') ? Math.max(70, Math.min(260, 40 + (+q.puan || 0) * 9)) : (s.type === 'gap' ? 14 : 8);
      h += '<div class="s"><table class="sb" width="100%"><tr><td class="sn">' + (i + 1) + '.</td><td class="sm">' + (s.paragraph ? '<div class="p">' + s.paragraph + '</div>' : '') + '<div class="r">' + (s.root || '') + '</div>';
      if (s.type === 'multiple' || s.type === 'truefalse') {
        var ks = Object.keys(op).filter(function (k) { return op[k]; }), kisa = ks.every(function (k) { return String(op[k]).replace(/<[^>]+>/g, '').length < 26 && op[k].indexOf('<img') < 0; });
        h += kisa ? '<table class="sk" width="100%"><tr>' + ks.map(function (k) { return '<td><b>' + k + ')</b> ' + op[k] + '</td>'; }).join('') + '</tr></table>' : '<div class="sl">' + ks.map(function (k) { return '<div><b>' + k + ')</b> ' + op[k] + '</div>'; }).join('') + '</div>';
      }
      h += '</td><td class="sp">(' + (q.puan || 0) + ' P)</td></tr></table>' + (alan > 10 ? '<div class="cv" style="height:' + alan + 'px"></div>' : '') + '</div>';
      return h;
    }).join('');
    var key = '';
    if (anahtar) key = (word ? '<br clear="all" style="page-break-before:always">' : '<div class="kesme"></div>') + '<h2 class="ka">CEVAP ANAHTARI</h2><p class="kb">' + esc(b.baslik) + '</p><table class="ak" width="100%"><thead><tr><th>No</th><th>Öğrenme çıktısı</th><th>Cevap</th><th>Puan</th></tr></thead><tbody>' + S.sorular.map(function (q, i) { var s = q.soru || {}, op = s.options || {}, x = S.slotlar[i] || {}; var c = (s.type === 'multiple' || s.type === 'truefalse') ? '<b>' + esc(s.correct || '?') + '</b>' + (s.correct && op[s.correct] ? ') ' + op[s.correct] : '') : (op.A || '—'); return '<tr><td class="c">' + (i + 1) + '</td><td class="kk">' + esc(x.kod && x.kod.indexOf('.') > 0 ? x.kod : '') + '<br><small>' + esc(x.konu || '') + '</small></td><td>' + c + '</td><td class="c">' + (q.puan || 0) + '</td></tr>'; }).join('') + '</tbody></table>';
    var css = '@page{size:A4;margin:1.3cm 1.4cm}' + (word ? '@page Section1{size:21cm 29.7cm;margin:1.3cm 1.4cm}div.Section1{page:Section1}' : '') +
      'body{font-family:Calibri,"Segoe UI",Arial,sans-serif;font-size:11.5pt;color:#000;margin:0}' +
      '.bas{text-align:center;border:1.5px solid #000;padding:6px 8px 4px}.bas .tc{font-weight:700;font-size:11pt}.bas .ok{font-weight:700;font-size:12pt;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.bas .bs{font-weight:700;font-size:10.5pt;margin-top:2px}' +
      'table{border-collapse:collapse}.ogr{width:100%;margin:6px 0 12px}.ogr td{border:1.5px solid #000;padding:5px 7px;font-size:10.5pt;vertical-align:top}.ogr .pk{width:70px;text-align:center;font-weight:700}' +
      '.s{margin:0 0 10px;page-break-inside:avoid}.sb td{vertical-align:top;padding:0}.sn{width:26px;font-weight:700}.sp{width:52px;text-align:right;font-weight:700;font-size:10pt;white-space:nowrap}.p{border:1px solid #999;padding:5px 8px;margin:0 0 5px;font-size:11pt}.r{font-weight:600}.sk{margin-top:5px}.sk td{padding:2px 6px 2px 0;vertical-align:top}.sl div{margin:3px 0}.cv{border-bottom:1px dotted #bbb;margin-left:26px}' +
      'img{max-width:100%;height:auto}.alt{margin-top:18px;text-align:right;font-size:10.5pt}.alt b{display:block}.imza{font-size:8pt;color:#888;text-align:center;margin-top:16px}' +
      '.ka{text-align:center;font-size:14pt;margin:0 0 2px}.kb{text-align:center;font-size:9.5pt;margin:0 0 10px}.ak th,.ak td{border:1px solid #000;padding:5px 7px;font-size:10.5pt;vertical-align:top;text-align:left}.ak .c{text-align:center;width:40px}.ak .kk{width:150px;font-size:9.5pt}.kesme{page-break-before:always}';
    var govde = '<div class="bas"><div class="tc">T.C.</div><div class="ok">' + esc(trUp(b.okul || '……………………… OKULU')) + '</div><div class="bs">' + esc(b.baslik) + '</div></div>' +
      '<table class="ogr"><tr><td>Adı Soyadı: </td><td style="width:22%">Sınıfı / No: </td><td style="width:16%">Tarih: ' + esc(tarihTr(b.tarih)) + '</td><td style="width:13%">Süre: ' + esc(b.sure) + ' dk</td><td class="pk">PUAN<br><br></td></tr></table>' +
      soruHtml + '<div class="alt">Başarılar dilerim.<b>' + esc(b.ogretmen) + '</b>' + esc(S.ders) + ' Öğretmeni</div><div class="imza">' + esc(C.site || 'soruindir.net') + '</div>' + key;
    return '<!DOCTYPE html><html' + (word ? ' xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"' : ' lang="tr"') + '><head><meta charset="utf-8"><title>' + esc(b.baslik) + '</title>' + (word ? '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml><![endif]-->' : '') + '<style>' + css + '</style></head><body>' + (word ? '<div class="Section1">' + govde + '</div>' : govde) + '</body></html>';
  }
  function dosyaAdi(ek) { return (S.sinif + '-sinif-' + S.ders + '-' + S.donem + '-donem-' + S.yazili + '-yazili' + ek).toLocaleLowerCase('tr').replace(/ç/g, 'c').replace(/ğ/g, 'g').replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ş/g, 's').replace(/ü/g, 'u').replace(/[^a-z0-9]+/g, '-').replace(/-+$/, ''); }
  function wordIndir(anahtar) {
    var blob = new Blob(['﻿', kagit(anahtar, true)], { type: 'application/msword' });
    var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = dosyaAdi(anahtar ? '-cevap-anahtarli' : '') + '.doc'; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
  }
  function yazdir(anahtar) {
    var f = document.createElement('iframe'); f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0'; document.body.appendChild(f);
    var d = f.contentWindow.document; d.open(); d.write(kagit(anahtar, false)); d.close();
    var bas = function () { try { f.contentWindow.focus(); f.contentWindow.print(); } catch (e) {} setTimeout(function () { f.remove(); }, 4000); };
    var imgs = d.images, kalan = imgs.length; if (!kalan) return setTimeout(bas, 250);
    [].forEach.call(imgs, function (im) { if (im.complete) { if (!--kalan) setTimeout(bas, 200); } else im.onload = im.onerror = function () { if (!--kalan) setTimeout(bas, 200); }; });
  }
  function v4() {
    var h = '<div class="yz-cikti"><div class="yz-onizle"><div class="yz-onizle-bas"><b>Önizleme</b><label class="yz-anahtar"><input type="checkbox" id="yz-ak"' + (S.anahtar ? ' checked' : '') + '><em></em>Cevap anahtarını göster</label></div><iframe id="yz-if" title="Yazılı önizleme"></iframe></div>' +
      '<aside class="yz-yan"><div class="yz-kart"><h2>İndir ve yazdır</h2><p class="yz-not">Öğrencilere dağıtacağınız kâğıt için <b>cevap anahtarsız</b> sürümü kullanın.</p>' +
      '<div class="yz-indir"><button class="yz-btn ana tam" data-o="word0"><span>📄</span> Word (cevap anahtarsız)</button><button class="yz-btn tam" data-o="word1"><span>🔑</span> Word (cevap anahtarlı)</button><button class="yz-btn tam" data-o="pdf0"><span>🖨️</span> Yazdır / PDF (anahtarsız)</button><button class="yz-btn tam" data-o="pdf1"><span>🖨️</span> Yazdır / PDF (anahtarlı)</button></div>' +
      '<p class="yz-not kucuk">PDF için yazdırma penceresinde hedef olarak <b>“PDF olarak kaydet”</b>i seçin.</p></div>' +
      '<div class="yz-kart"><h2>Kaydet</h2><p class="yz-not" id="yz-kd">' + (S.id ? '✓ Yazılılarım’a kaydedildi.' : 'Yazılılarım’a kaydedilir; sonra açıp düzenleyebilirsiniz.') + '</p><button class="yz-btn tam" id="yz-kaydet">💾 Kaydet</button><button class="yz-btn tam hafif" data-git2="3">✎ Sorulara dön</button><button class="yz-btn tam hafif" id="yz-yeni">+ Yeni yazılı</button></div></aside></div>';
    cerceve(h);
    var ifr = document.getElementById('yz-if'), yenile = function () { ifr.srcdoc = kagit(S.anahtar, false).replace('</style>', 'body{padding:28px 30px;background:#fff}.kesme{border-top:2px dashed #94a3b8;margin:30px 0 20px}</style>'); };
    yenile();
    ifr.onload = function () { try { ifr.style.height = Math.max(600, ifr.contentWindow.document.documentElement.scrollHeight + 20) + 'px'; } catch (e) {} };
    document.getElementById('yz-ak').onchange = function () { S.anahtar = this.checked; sakla(); yenile(); };
    kok.querySelectorAll('[data-o]').forEach(function (b) { b.onclick = function () { var o = b.dataset.o; if (o.indexOf('word') === 0) wordIndir(o === 'word1'); else yazdir(o === 'pdf1'); }; });
    kok.querySelectorAll('[data-git2]').forEach(function (e) { e.onclick = function () { S.adim = +e.dataset.git2; sakla(); ciz(); }; });
    document.getElementById('yz-kaydet').onclick = function () { kaydet(false); };
    document.getElementById('yz-yeni').onclick = function () { if (!confirm('Yeni yazılıya başlansın mı? (Bu yazılı Yazılılarım’da kayıtlı kalır.)')) return; var ok = S.bilgi.okul; S = yeniDurum(); S.bilgi.okul = ok; sakla(); ciz(); };
  }
  function kaydet(sessiz) {
    var veri = { sinif: S.sinif, ders: S.ders, donem: S.donem, yazili: S.yazili, yil: S.yil, senaryo: S.senaryo, ozel: S.ozel, bilgi: S.bilgi, zorluk: S.zorluk, tur: S.tur, slotlar: S.slotlar, anahtar: S.anahtar, sorular: S.sorular.map(function (q) { return { kod: q.kod, id: q.id, kaynak: q.kaynak, soru: q.soru, puan: q.puan }; }) };
    return api('kaydet', { id: S.id || 0, veri: veri }).then(function (r) { S.id = r.id; sakla(); var e = document.getElementById('yz-kd'); if (e) e.textContent = '✓ Yazılılarım’a kaydedildi.'; if (!sessiz) bildir('Yazılı kaydedildi.', 'ok'); }).catch(function (e) { if (!sessiz) bildir(e.message, 'hata'); });
  }

  /* ---------- yazılılarım ---------- */
  function vListe() {
    cerceve('<section class="yz-kart"><div class="yz-bas"><h2>Yazılılarım</h2><button class="yz-btn ana" id="yz-yeni2">+ Yeni yazılı</button></div><div id="yz-l" class="yz-liste"><div class="yz-yuk">Yükleniyor…</div></div></section>');
    document.getElementById('yz-yeni2').onclick = function () { var ok = S.bilgi.okul; S = yeniDurum(); S.bilgi.okul = ok; sakla(); sekme = 'yeni'; ciz(); };
    api('liste').then(function (l) {
      var el = document.getElementById('yz-l');
      if (!l.length) { el.innerHTML = '<div class="yz-bos-liste"><div>📝</div><b>Henüz kayıtlı yazılınız yok</b><p>Hazırladığınız yazılılar burada listelenir.</p></div>'; return; }
      el.innerHTML = l.map(function (x) { return '<div class="yz-li"><div class="ik">' + esc(x.sinif) + '</div><div class="bil"><b>' + esc(x.baslik) + '</b><small>' + esc(x.ders) + ' · ' + esc(x.yazili) + '. yazılı · ' + (x.senaryo === 'ozel' ? 'kendi dağılımı' : esc(x.senaryo) + '. senaryo') + ' · ' + x.n + ' soru · ' + esc(x.tarih) + '</small></div><button class="yz-btn kucuk ana" data-ac="' + x.id + '">Aç</button><button class="yz-btn kucuk" data-sil="' + x.id + '">Sil</button></div>'; }).join('');
      el.querySelectorAll('[data-ac]').forEach(function (b) { b.onclick = function () { b.disabled = true; api('getir', { id: b.dataset.ac }).then(function (r) { var v = r.veri, n = yeniDurum(); Object.keys(n).forEach(function (k) { if (v[k] !== undefined) n[k] = v[k]; }); n.id = r.id; n.sorular = (v.sorular || []).map(function (q) { q.duzen = false; q.gorulen = q.id ? [q.id] : []; return q; }); n.adim = 4; S = n; sakla(); sekme = 'yeni'; ciz(); }).catch(function (e) { b.disabled = false; bildir(e.message, 'hata'); }); }; });
      el.querySelectorAll('[data-sil]').forEach(function (b) { b.onclick = function () { if (!confirm('Bu yazılı silinsin mi?')) return; api('sil', { id: b.dataset.sil }).then(function () { if (S.id === +b.dataset.sil) S.id = 0; vListe(); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
    }).catch(function (e) { document.getElementById('yz-l').innerHTML = '<div class="yz-bos">' + esc(e.message) + '</div>'; });
  }
  if (S.adim > enFazlaAdim()) S.adim = enFazlaAdim();
  ciz();
})();
