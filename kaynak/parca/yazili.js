(function () {
  'use strict';
  function BASLAT() {
  var C = window.OSC_YZ, SNR = window.OSC_SNR || { tablolar: [] }, kok = document.getElementById('osc-yz');
  if (!C || !kok) return;

  /* ================= yardımcılar ================= */
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function ls(k, v) { try { if (v === undefined) return JSON.parse(localStorage.getItem(k) || 'null'); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, JSON.stringify(v)); } catch (e) { if (v !== undefined) throw e; return null; } }
  function trUp(s) { return String(s || '').replace(/i/g, 'İ').replace(/ı/g, 'I').toUpperCase(); }
  function bugun() { var d = new Date(); return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
  function tarihTr(s) { if (!s) return ''; var p = s.split('-'); return p.length === 3 ? p[2] + '.' + p[1] + '.' + p[0] : s; }
  function tarihSaat() { var d = new Date(), z = function (n) { return ('0' + n).slice(-2); }; return z(d.getDate()) + '.' + z(d.getMonth() + 1) + '.' + d.getFullYear() + ' ' + z(d.getHours()) + ':' + z(d.getMinutes()); }
  function bildir(m, tur) {
    var t = document.createElement('div'); t.className = 'yz-tost ' + (tur || ''); t.innerHTML = '<i>' + ({ hata: '!', uyari: '!', ok: '✓' }[tur] || 'i') + '</i><span>' + esc(m) + '</span>'; document.body.appendChild(t);
    setTimeout(function () { t.classList.add('on'); }, 10); setTimeout(function () { t.classList.remove('on'); setTimeout(function () { t.remove(); }, 300); }, 4200);
  }

  /* ================= matematik (LaTeX → KaTeX) ================= */
  var KATEX = 'https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/';
  function katexYukle() {
    if (window.katex || document.getElementById('osc-katex')) return;
    var l = document.createElement('link'); l.rel = 'stylesheet'; l.href = KATEX + 'katex.min.css'; l.id = 'osc-katex-css'; document.head.appendChild(l);
    var sc = document.createElement('script'); sc.id = 'osc-katex'; sc.src = KATEX + 'katex.min.js'; sc.onload = function () { try { if (sekme === 'yeni' && (S.adim === 3 || S.adim === 4)) ciz(); } catch (e) {} }; document.head.appendChild(sc);
  }
  function varliklariCoz(t) { return String(t).replace(/<br\s*\/?>/g, ' ').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&'); }
  /* yapay zekânın bozuk yazdığı LaTeX'i onar: \\frac -> \frac, "frac{" -> "\frac{", eksik kapanan parantez */
  function texDuzelt(t) {
    t = String(t || '').replace(/\\{2,}(?=[a-zA-Z])/g, '\\').replace(/(^|[^\\a-zA-Z])(d?frac|sqrt|times|cdot|div|overline|left|right|leq?|geq?|neq?|pi)(?=[\{\[\(\s\d])/g, '$1\\$2');
    var ac = (t.match(/\{/g) || []).length - (t.match(/\}/g) || []).length; while (ac-- > 0) t += '}';
    return t;
  }
  window.OSC_TEX_DUZELT = texDuzelt;
  function mat(html) {
    html = String(html == null ? '' : html);
    if (!window.katex || (html.indexOf('$') < 0 && html.indexOf('\\(') < 0)) return html;
    var r = function (tex, blok) { tex = texDuzelt(varliklariCoz(tex)); try { return window.katex.renderToString(tex, { throwOnError: false, strict: false, displayMode: !!blok }); } catch (e) { return tex; } };
    // tek geçiş: işlenmiş formülün içinde yeniden $ aranmaz
    return html.replace(/\$\$([\s\S]{1,600}?)\$\$|\\\(([\s\S]{1,600}?)\\\)|\$([^$\n]{1,600}?)\$/g, function (m, a, b, c, ofs, tum) {
      if (a != null) return r(a, true);
      if (b != null) return r(b);
      var once = tum.slice(0, ofs), sonra = tum.slice(ofs + m.length);
      // tek başına satırdaki formül büyük, satır içindekiler de kesirleri okunur boyutta
      if (/(^|<br\s*\/?>)\s*$/.test(once) && /^\s*(<br|$)/.test(sonra)) return '<span class="yz-blok">' + r('\\displaystyle ' + c) + '</span>';
      return r(/\\frac|\\sqrt\[|frac\{/.test(c) ? '\\displaystyle ' + c : c);
    });
  }
  function matVar(html) { return /\$[^$]+\$|\\\(/.test(String(html || '')); }

  /* ================= sunucu ================= */
  function sunucu(a, d, ms) {
    var fd = new FormData(); fd.append('action', 'osc_yz_' + a); fd.append('nonce', C.nonce);
    Object.keys(d || {}).forEach(function (k) { fd.append(k, typeof d[k] === 'object' ? JSON.stringify(d[k]) : d[k]); });
    var ac = window.AbortController ? new AbortController() : null, zt = ac && ms ? setTimeout(function () { ac.abort(); }, ms) : 0;
    return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin', signal: ac ? ac.signal : undefined })
      .then(function (r) { return r.text().then(function (t) { try { return JSON.parse(t); } catch (e) { throw new Error(r.status >= 500 || r.status === 0 ? 'Sunucu zaman aşımına uğradı (' + r.status + ').' : 'Sunucu hatası (' + r.status + ').'); } }); })
      .then(function (j) { clearTimeout(zt); if (!j || !j.success) throw new Error((j && j.data) || 'Bir hata oluştu'); return j.data; })
      .catch(function (e) { clearTimeout(zt); if (e && e.name === 'AbortError') throw new Error('Yanıt çok uzun sürdü.'); throw e; });
  }
  /* bağımsız kurulum (yazılımatik): Yazılılarım tarayıcıda saklanır */
  function kayitlar(v) { if (v === undefined) return ls('osc_yz_kayitlar') || []; ls('osc_yz_kayitlar', v.slice(0, 60)); }
  var YEREL = {
    kaydet: function (d) {
      var v = d.veri, l = kayitlar(), id = +d.id || Date.now();
      l = l.filter(function (x) { return x.id !== id; }); l.unshift({ id: id, baslik: (v.bilgi && v.bilgi.baslik) || 'Yazılı', tarih: tarihSaat(), veri: v });
      try { kayitlar(l); } catch (e) { throw new Error('Tarayıcı belleği dolu; eski yazılılardan bazılarını silin.'); }
      var kendi = (v.sorular || []).filter(function (q) { return q.kaynak === 'ogretmen'; });
      if (kendi.length) { var im = JSON.stringify(kendi.map(function (q) { return q.soru; })), g = YEREL._g = YEREL._g || {}; if (!g[im]) { g[im] = 1; sunucu('havuza', { veri: { sinif: v.sinif, ders: v.ders, donem: v.donem, yazili: v.yazili, sorular: kendi } }).catch(function () {}); } }
      if (v.bilgi && v.bilgi.okul) { try { ls('osc_yz_okul', v.bilgi.okul); } catch (e) {} }
      return { id: id };
    },
    liste: function () { return kayitlar().map(function (x) { var v = x.veri || {}; return { id: x.id, baslik: x.baslik, tarih: x.tarih, sinif: v.sinif, ders: v.ders, yazili: v.yazili, senaryo: v.senaryo, n: (v.sorular || []).length }; }); },
    getir: function (d) { var x = kayitlar().filter(function (k) { return k.id === +d.id; })[0]; if (!x) throw new Error('Yazılı bulunamadı.'); return { id: x.id, veri: x.veri }; },
    sil: function (d) { kayitlar(kayitlar().filter(function (k) { return k.id !== +d.id; })); return true; }
  };
  /* üyeler: okul / öğretmen bilgisi hesapta saklanır, tüm yazılı ve evraklara işlenir */
  var pZaman = {}, pBekle = {};
  function profilKaydet(k, v) {
    if (!C.uye || !C.pnonce) return; pBekle[k] = v; clearTimeout(pZaman.t);
    pZaman.t = setTimeout(function () { var fd = new FormData(); fd.append('action', 'osc_om_profil'); fd.append('nonce', C.pnonce); fd.append('veri', JSON.stringify(pBekle)); pBekle = {}; fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {}); }, 900);
  }
  function api(a, d, ms) {
    if (C.yerel && YEREL[a]) return new Promise(function (ok, no) { try { ok(YEREL[a](d || {})); } catch (e) { no(e); } });
    return sunucu(a, d, ms);
  }
  /* yapay zekânın bu tarayıcı için yazdığı (henüz onaylanmamış) sorular tekrar kullanılabilsin */
  function benim(ekle) { var l = ls('osc_yz_benim') || []; if (ekle) { [].concat(ekle).forEach(function (x) { if (x && l.indexOf(x) < 0) l.push(x); }); try { ls('osc_yz_benim', l.slice(-300)); } catch (e) {} } return l; }

  /* ================= durum ================= */
  var ZOR = [['0', 'Karışık', 'Dengeli dağılım', '🎯'], ['1', 'Kolay', 'Temel düzey', '🌱'], ['2', 'Orta', 'Sınıf düzeyi', '⚖️'], ['3', 'Zor', 'Üst düzey düşünme', '🔥']];
  var TUR = [['karisik', 'Karışık', 'Açık uçlu + test', '🧩'], ['acik', 'Açık uçlu', 'Klasik yazılı', '✍️'], ['test', 'Test', 'Çoktan seçmeli', '🔘']];
  var ALAN = [['az', 'Dar', 'Kısa cevaplar'], ['normal', 'Normal', 'Puana göre'], ['genis', 'Geniş', 'Uzun cevaplar'], ['bos', 'Boş alan', 'Çizgisiz']];
  var KAYNAK = [['yz', 'Yapay zekâ ile özgün', 'Her soru çıktıya göre yeniden yazılır', '✨'], ['arsiv', 'Soru havuzu', 'Senaryoya uygun sorular havuzdan hemen gelir', '📚']];
  var KAY = { arsiv: ['Arşiv', 'a'], yz: ['Yapay zekâ', 'y'], ogretmen: ['Sizin sorunuz', 'o'], bos: ['Boş', 'b'], yukleniyor: ['Yazılıyor…', 'l'] };
  var DERS_IK = { 'Türkçe': '📖', 'Matematik': '➗', 'Fen Bilimleri': '🔬', 'Sosyal Bilgiler': '🌍', 'İngilizce': '🇬🇧', 'Din Kültürü ve Ahlak Bilgisi': '🕌', 'T.C. İnkılap Tarihi ve Atatürkçülük': '🇹🇷', 'Fizik': '⚛️', 'Kimya': '⚗️', 'Biyoloji': '🧬', 'Tarih': '🏛️', 'Coğrafya': '🗺️', 'Felsefe': '💭', 'Türk Dili ve Edebiyatı': '✒️' };
  var RENK = ['#6366f1', '#06b6d4', '#f59e0b', '#10b981', '#ec4899', '#8b5cf6', '#ef4444', '#14b8a6', '#84cc16', '#f97316'];

  function yeniDurum() { return { adim: 1, yil: SNR.yil || '2026-2027', sinif: 0, ders: '', donem: varsayilanDonem(), yazili: 1, senaryo: 0, ozel: {}, bilgi: { okul: C.okul || '', baslik: '', ogretmen: C.ad || '', tarih: bugun(), sure: 40 }, zorluk: 0, tur: 'acik', alan: 'normal', kaynak: 'arsiv', slotlar: [], sorular: [], anahtar: true, id: 0 }; }
  var S = ls('osc_yz_taslak') || yeniDurum(), sekme = 'yeni', kuyrukNo = 0;
  if (!S.alan) S.alan = 'normal';
  if (!S.kaynak) S.kaynak = 'arsiv';
  if (!C.yz || C.yzmod !== 'acik') S.kaynak = 'arsiv';
  (S.sorular || []).forEach(function (q) { if (q.kaynak === 'yukleniyor') q.kaynak = 'bos'; });
  function sakla() { try { ls('osc_yz_taslak', S); } catch (e) {} }

  /* ================= senaryo verisi ================= */
  function tablolar(sinif) { return SNR.tablolar.filter(function (t) { return t.sinif === +sinif && (t.donem || 1) === S.donem; }); }
  function donemVar(d) { return (SNR.donemler || [1]).indexOf(d) >= 0; }
  function varsayilanDonem() { var m = new Date().getMonth() + 1; return m >= 2 && m <= 8 && donemVar(2) ? 2 : (donemVar(1) ? 1 : 2); }
  var DG = window.OSC_DIGER || { gruplar: [] };
  function ozKod(k) { var t = String(k || '').toLocaleLowerCase('tr-TR').replace(/\s+/g, ' ').trim(), h = 5381; for (var i = 0; i < t.length; i++) h = ((h * 33) ^ t.charCodeAt(i)) >>> 0; return 'oz-' + h.toString(36); }
  function digerTablo() {
    var d = S.dg || {}; if (!S.ders || !S.sinif) return null;
    var st = (d.konular || []).filter(function (k) { return String(k.konu || '').trim(); }).map(function (k) { var t = String(k.konu).trim(); return { kod: ozKod(t), konu: S.ders, cikti: t, sayi: {} }; });
    return st.length ? { sinif: S.sinif, ders: S.ders, ozel: 1, alan: d.alan || '', satirlar: st } : null;
  }
  function tablo() { if (S.mod === 'diger') return digerTablo(); for (var i = 0; i < SNR.tablolar.length; i++) { var t = SNR.tablolar[i]; if (t.sinif === +S.sinif && t.ders === S.ders && (t.donem || 1) === S.donem) return t; } return null; }
  function kodu(x) { return x.kod || x.cikti.slice(0, 38); }
  function kodGoster(k) { return k && k.indexOf('.') > 0 && k.length < 20 ? k : ''; }
  function senaryoSlot(t, y, sn) { var o = []; t.satirlar.forEach(function (x) { var n = ((x.sayi || {})[String(y)] || {})[String(sn)] || 0; for (var i = 0; i < n; i++) o.push({ kod: kodu(x), cikti: x.cikti, konu: x.konu, ben: false }); }); return o; }
  function ozelSlot(t) { var o = []; t.satirlar.forEach(function (x) { var n = S.ozel[kodu(x)] || 0; for (var i = 0; i < n; i++) o.push({ kod: kodu(x), cikti: x.cikti, konu: x.konu, ben: false }); }); return o; }
  function ulkeGeneli(t) { return !!(t.ulke && t.ulke[String(S.yazili)]) || !((t.senaryolar || {})[String(S.yazili)] || []).length; }
  function varsayilanBaslik() { return S.yil + ' EĞİTİM-ÖĞRETİM YILI ' + S.sinif + '. SINIF ' + trUp(S.ders) + ' DERSİ ' + S.donem + '. DÖNEM ' + S.yazili + '. YAZILI SINAVI'; }
  function baslikYalin(b) { return String(b || '').replace(/^\s*\d{4}\s*[-–]\s*\d{4}\s*EĞİTİM[\s-]*ÖĞRETİM YILI\s*/i, ''); }
  function konuRenk(t) { var m = {}, i = 0; t.satirlar.forEach(function (x) { if (!(x.konu in m)) m[x.konu] = RENK[i++ % RENK.length]; }); return m; }

  /* ================= çerçeve ================= */
  function enFazlaAdim() { if (!S.slotlar.length) return 1; if (!S.sorular.length) return 2; return 4; }
  function cerceve(ic) {
    var ad = [['Seçim', 'Sınıf · ders · senaryo'], ['Bilgiler', 'Okul · zorluk · tür'], ['Sorular', 'Değiştir · düzenle · puanla'], ['Çıktı', 'Word · PDF · yazdır']];
    var ilerleme = sekme === 'yeni' ? Math.round((S.adim - 1) / 3 * 100) : 0;
    kok.innerHTML = '<header class="yz-hero' + (sekme === 'yeni' && S.adim === 1 && !C.gomulu ? ' buyuk' : '') + '"><div class="yz-hero-ic"><a class="yz-marka" href="' + esc(C.home) + '">' + (C.gomulu ? '' : '<img src="' + esc(C.logo) + '" alt="soruindir">') + '<span>' + (C.gomulu ? '📄 ' : '') + 'Yazılı Hazırla</span></a>' +
      '<nav class="yz-sek"><button data-sek="yeni" class="' + (sekme === 'yeni' ? 'on' : '') + '">✨ Yeni yazılı</button><button data-sek="liste" class="' + (sekme === 'liste' ? 'on' : '') + '">📁 Yazılılarım</button>' + (C.panel ? '<a href="' + esc(C.panel) + '">Panel</a>' : '') + (C.giris ? '<a href="' + esc(C.giris) + '">👤 Giriş yap</a>' : '') + '</nav></div>' +
      (sekme === 'yeni' && S.adim === 1 && !C.gomulu ? '<div class="yz-hero-yazi"><span class="yz-etiket">MEB ' + esc(S.yil) + ' konu-soru dağılım tablolarına uygun</span><h1>Yazılınız <em>bir dakikada</em> hazır</h1><p>Sınıfı, dersi ve senaryoyu seçin; sorular senaryodaki öğrenme çıktılarına göre otomatik gelsin. Beğenmediğinizi değiştirin, isterseniz kendiniz yazın, Word veya PDF olarak indirin.</p><div class="yz-hero-ozellik"><span>📚 5–12. sınıf, tüm dersler</span><span>🎯 Senaryoya birebir uygun</span><span>🔑 Cevap anahtarlı çıktı</span></div></div>' : '') +
      '</header>' +
      (sekme === 'yeni' ? '<ol class="yz-adimlar" style="--ilerleme:' + ilerleme + '%">' + ad.map(function (a, i) { var n = i + 1; return '<li class="' + (S.adim === n ? 'on' : (S.adim > n ? 'ok' : '')) + '"><button data-git="' + n + '"' + (n > enFazlaAdim() ? ' disabled' : '') + '><i>' + (S.adim > n ? '✓' : n) + '</i><span><b>' + a[0] + '</b><small>' + a[1] + '</small></span></button></li>'; }).join('') + '</ol>' : '') +
      '<main class="yz-ana">' + ic + '</main>';
    kok.querySelectorAll('[data-sek]').forEach(function (b) { b.onclick = function () { editorleriSakla(); sekme = b.dataset.sek; ciz(); }; });
    kok.querySelectorAll('[data-git]').forEach(function (b) { b.onclick = function () { git(+b.dataset.git); }; });
  }
  function git(n) { editorleriSakla(); S.adim = n; sakla(); ciz(); var y = kok.getBoundingClientRect().top + window.pageYOffset - 16; if (window.pageYOffset > y) window.scrollTo({ top: y, behavior: 'smooth' }); }
  function ciz() { if (sekme === 'liste') return vListe(); [null, v1, v2, v3, v4][S.adim](); }

  /* ================= 1. seçim ================= */
  function modSec() {
    return '<div class="yz-mod"><button data-mod="meb" class="' + (S.mod !== 'diger' ? 'on' : '') + '"><span>🏫</span><b>MEB senaryolu dersler</b><small>Ortak dersler · konu-soru dağılım tablosu</small></button><button data-mod="diger" class="' + (S.mod === 'diger' ? 'on' : '') + '"><span>🧩</span><b>Diğer dersler</b><small>Seçmeli · meslek lisesi · İmam Hatip · güzel sanatlar · spor</small></button></div>';
  }
  function modBagla() { kok.querySelectorAll('[data-mod]').forEach(function (b) { b.onclick = function () { var m = b.dataset.mod; if ((S.mod || 'meb') === m) return; S.mod = m; S.sinif = 0; S.ders = ''; S.senaryo = 0; S.ozel = {}; S.dg = { grup: '', alan: '', konular: [] }; sakla(); v1(); }; }); }
  function vDiger() {
    var d = S.dg = S.dg || { grup: '', alan: '', konular: [] }, g = DG.gruplar.filter(function (x) { return x.id === d.grup; })[0];
    var al = g && g.alanlar ? g.alanlar.filter(function (a) { return a.ad === d.alan; })[0] : null, liste = g ? (g.alanlar ? (al ? al.dersler : []) : g.dersler) : [];
    var sn = []; liste.forEach(function (x) { x.s.forEach(function (k) { if (sn.indexOf(k) < 0) sn.push(k); }); }); sn.sort(function (a, b) { return a - b; });
    if (g && !sn.length) sn = g.id === 'oo_secmeli' || g.id === 'iho' ? [5, 6, 7, 8] : [9, 10, 11, 12];
    var dl = liste.filter(function (x) { return x.s.indexOf(S.sinif) >= 0; });
    var t = digerTablo(), toplam = (d.konular || []).reduce(function (a, k) { return a + (String(k.konu || '').trim() ? (+k.n || 0) : 0); }, 0);
    var h = modSec() + '<section class="yz-kart"><div class="yz-kart-bas"><span class="yz-num">1</span><div><h2>Ders</h2><p>Seçmeli, meslek ve alan derslerinin tamamı · listede olmayan dersi kendiniz yazabilirsiniz</p></div></div>' +
      '<div class="yz-gruplar">' + DG.gruplar.map(function (x) { return '<button data-dg="' + x.id + '" class="' + (d.grup === x.id ? 'on' : '') + '"><span>' + x.ik + '</span><b>' + esc(x.ad) + '</b></button>'; }).join('') + '</div>' +
      (g && g.alanlar ? '<div class="yz-satir"><span class="yz-et">Alan</span><select class="yz-alansec" id="yz-alan"><option value="">— Alan seçin (' + g.alanlar.length + ' alan) —</option>' + g.alanlar.map(function (a) { return '<option' + (a.ad === d.alan ? ' selected' : '') + '>' + esc(a.ad) + '</option>'; }).join('') + '</select></div>' : '') +
      (g && (!g.alanlar || al) ? '<div class="yz-siniflar">' + sn.map(function (s) { return '<button data-dsn="' + s + '" class="' + (S.sinif === s ? 'on' : '') + '"><b>' + s + '</b><small>' + (s <= 8 ? 'ortaokul' : 'lise') + '</small></button>'; }).join('') + '</div>' : '') +
      (S.sinif && g && (!g.alanlar || al) ? '<div class="yz-dersler kucuk">' + dl.map(function (x) { return '<button data-dders="' + esc(x.ad) + '" class="' + (S.ders === x.ad ? 'on' : '') + '"><b>' + esc(x.ad) + '</b>' + (x.dal ? '<small>' + esc(x.dal) + '</small>' : '') + '</button>'; }).join('') + '</div>' +
        '<div class="yz-satir"><span class="yz-et">Listede yok mu?</span><input class="yz-in" id="yz-dders" maxlength="90" placeholder="Ders adını yazın" value="' + esc(dl.some(function (x) { return x.ad === S.ders; }) ? '' : S.ders) + '"></div>' : (g ? '' : '<div class="yz-ipucu">👆 Ders grubunu seçin</div>')) +
      (S.ders ? '<div class="yz-satir"><span class="yz-et">Dönem / yazılı</span><div class="yz-seg">' + [1, 2].map(function (x) { return '<button data-ddon="' + x + '" class="' + (S.donem === x ? 'on' : '') + '">' + x + '. dönem</button>'; }).join('') + '</div><div class="yz-seg">' + [1, 2].map(function (y) { return '<button data-dyaz="' + y + '" class="' + (S.yazili === y ? 'on' : '') + '">' + y + '. yazılı</button>'; }).join('') + '</div></div>' : '') + '</section>';
    var sk = S.sinif + '|' + S.ders, sy = d.say && d.say.k === sk ? d.say.n : null;
    if (S.ders && sy === 0) h += '<section class="yz-kart yz-bosh"><div class="yz-bosh-ic"><span>📭</span><div><h2>Bu ders için soru havuzunda henüz soru yok</h2><p>Konularınızı ve soru sayılarını yazın; sorularınızı kendiniz yazıp sınav kâğıdını <b>hemen yazdırın</b>. Yazdığınız sorular havuza da eklenir, sonraki yazılılarda size ve diğer öğretmenlere yardımcı olur.' + (C.yz ? ' İsterseniz eksik kalan soruları yapay zekâ da yazabilir.' : '') + '</p><label class="yz-anahtar"><input type="checkbox" id="yz-kendim"' + (d.kendim ? ' checked' : '') + '><em></em><span>✍️ Sorularımı kendim yazacağım</span></label></div></div></section>';
    else if (S.ders && sy > 0) h += '<div class="yz-ipucu">📚 Soru havuzunda bu derse ait <b>' + sy + '</b> soru var; konularınıza uygun olanlar otomatik gelir.</div>';
    if (S.ders) {
      h += '<section class="yz-kart"><div class="yz-kart-bas"><span class="yz-num">2</span><div><h2>Kendi senaryonuz</h2><p>Yazılıya girecek konuları ve her konudan kaç soru sorulacağını belirleyin</p></div>' + (C.yz ? '<button class="yz-btn hafif sag" id="yz-konu-oner">🤖 Konuları yapay zekâ önersin</button>' : '') + '</div>' +
        '<div class="yz-konular">' + (d.konular.length ? d.konular.map(function (k, i) { return '<div class="yz-konu"><i>' + (i + 1) + '</i><input class="yz-in" data-kn="' + i + '" maxlength="180" placeholder="Konu / öğrenme çıktısı (ör. Ohm Kanunu ve direnç hesapları)" value="' + esc(k.konu) + '"><div class="yz-sayac"><button data-kd="' + i + '" data-d="-1">−</button><b>' + (+k.n || 0) + '</b><button data-kd="' + i + '" data-d="1">+</button></div><button class="yz-x" data-ksil="' + i + '" title="Kaldır">×</button></div>'; }).join('') : '<div class="yz-ipucu">Konu ekleyin ya da yapay zekâya önerdirin. Sorular arşivden, yapay zekâdan gelir; isterseniz kendiniz yazarsınız.</div>') + '</div>' +
        '<button class="yz-btn hafif" id="yz-konu-ekle">＋ Konu ekle</button></section>';
    }
    h += '<div class="yz-cubuk"><div class="yz-ozet">' + (t && toplam ? '<b>' + toplam + ' soruluk yazılı</b><span>' + S.sinif + '. sınıf · ' + esc(S.ders) + ' · ' + S.yazili + '. yazılı · kendi senaryonuz</span>' : '<b>Seçim yapın</b><span>Ders, sınıf ve konular</span>') + '</div><button class="yz-btn ana" id="yz-uygula"' + (t && toplam ? '' : ' disabled') + '>Seçimi uygula <i>→</i></button></div>';
    cerceve(h); modBagla();
    var yen = function () { sakla(); vDiger(); };
    if (S.ders && sy === null && !(d.say && d.say.yukl === sk)) { d.say = { k: '', yukl: sk }; api('say', { veri: { sinif: S.sinif, ders: S.ders } }, 20000).then(function (r) { d.say = { k: sk, n: r.n }; if (r.n === 0 && d.kendim === undefined) d.kendim = true; if (S.mod === 'diger' && S.adim === 1 && sekme === 'yeni') vDiger(); }).catch(function () {}); }
    var kd0 = document.getElementById('yz-kendim'); if (kd0) kd0.onchange = function () { d.kendim = kd0.checked; sakla(); };
    kok.querySelectorAll('[data-dg]').forEach(function (b) { b.onclick = function () { d.grup = b.dataset.dg; d.alan = ''; S.sinif = 0; S.ders = ''; yen(); }; });
    var as = document.getElementById('yz-alan'); if (as) as.onchange = function () { d.alan = as.value; S.sinif = 0; S.ders = ''; yen(); };
    kok.querySelectorAll('[data-dsn]').forEach(function (b) { b.onclick = function () { S.sinif = +b.dataset.dsn; S.ders = ''; yen(); }; });
    kok.querySelectorAll('[data-dders]').forEach(function (b) { b.onclick = function () { if (S.ders !== b.dataset.dders) d.konular = []; S.ders = b.dataset.dders; yen(); }; });
    var di = document.getElementById('yz-dders'); if (di) di.onchange = function () { var v = di.value.trim(); if (v) { if (S.ders !== v) d.konular = []; S.ders = v; yen(); } };
    kok.querySelectorAll('[data-ddon]').forEach(function (b) { b.onclick = function () { S.donem = +b.dataset.ddon; yen(); }; });
    kok.querySelectorAll('[data-dyaz]').forEach(function (b) { b.onclick = function () { S.yazili = +b.dataset.dyaz; yen(); }; });
    kok.querySelectorAll('[data-kn]').forEach(function (e) { e.oninput = function () { d.konular[+e.dataset.kn].konu = e.value; sakla(); }; e.onchange = yen; });
    kok.querySelectorAll('[data-kd]').forEach(function (b) { b.onclick = function () { var k = d.konular[+b.dataset.kd]; k.n = Math.max(0, Math.min(8, (+k.n || 0) + +b.dataset.d)); yen(); }; });
    kok.querySelectorAll('[data-ksil]').forEach(function (b) { b.onclick = function () { d.konular.splice(+b.dataset.ksil, 1); yen(); }; });
    var ke = document.getElementById('yz-konu-ekle'); if (ke) ke.onclick = function () { d.konular.push({ konu: '', n: 1 }); yen(); var l = kok.querySelectorAll('[data-kn]'); if (l.length) l[l.length - 1].focus(); };
    var ko = document.getElementById('yz-konu-oner'); if (ko) ko.onclick = function () {
      ko.disabled = true; ko.innerHTML = '<span class="yz-don"></span> Öneriliyor…';
      api('konular', { veri: { ders: S.ders, sinif: S.sinif, alan: d.alan || (g ? g.ad : '') } }, 60000).then(function (l) {
        var bu = l.filter(function (x) { return (x.donem || 1) === S.donem; }); if (bu.length < 3) bu = l;
        var mevcut = d.konular.map(function (k) { return String(k.konu).toLocaleLowerCase('tr-TR'); });
        bu.forEach(function (x) { if (mevcut.indexOf(x.konu.toLocaleLowerCase('tr-TR')) < 0) d.konular.push({ konu: x.konu, n: 1 }); });
        d.konular = d.konular.slice(0, 20); yen(); bildir(bu.length + ' konu önerildi. Soru sayılarını ayarlayın, gereksizleri silin.', 'ok');
      }).catch(function (e) { ko.disabled = false; ko.textContent = '🤖 Konuları yapay zekâ önersin'; bildir(e.message, 'hata'); });
    };
    document.getElementById('yz-uygula').onclick = function () {
      var t2 = digerTablo(); if (!t2) return;
      S.senaryo = 'ozel'; S.ozel = {}; d.konular.forEach(function (k) { var tt = String(k.konu || '').trim(); if (tt) S.ozel[ozKod(tt)] = (S.ozel[ozKod(tt)] || 0) + (+k.n || 0); });
      var yeni = ozelSlot(t2), ayni = S.slotlar.length === yeni.length && S.slotlar.every(function (x, i) { return x.kod === yeni[i].kod; });
      if (d.kendim) yeni.forEach(function (x) { x.ben = true; });
      if (d.kendim && S.slotlar.length === yeni.length) S.slotlar.forEach(function (x) { x.ben = true; });
      if (!ayni) { S.slotlar = yeni; S.sorular = []; S.id = 0; }
      if (!S.bilgi.baslik || !ayni) S.bilgi.baslik = varsayilanBaslik();
      git(2);
    };
  }
  function v1() {
    if (S.mod === 'diger') return vDiger();
    var siniflar = []; SNR.tablolar.forEach(function (t) { if ((t.donem || 1) === S.donem && siniflar.indexOf(t.sinif) < 0) siniflar.push(t.sinif); }); siniflar.sort(function (a, b) { return a - b; });
    var tl = S.sinif ? tablolar(S.sinif) : [], t = tablo();
    var h = modSec() + '<section class="yz-kart"><div class="yz-kart-bas"><span class="yz-num">1</span><div><h2>Sınıf ve ders</h2><p>' + esc(S.yil) + ' eğitim-öğretim yılı · ' + S.donem + '. dönem</p></div><div class="yz-seg sag">' + [1, 2].map(function (d) { var ok = donemVar(d); return '<button data-k="donem" data-v="' + d + '" class="' + (S.donem === d ? 'on' : '') + '"' + (ok ? '' : ' disabled title="' + d + '. dönem senaryoları MEB yayımlayınca eklenecek"') + '>' + d + '. dönem</button>'; }).join('') + '</div></div>' +
      '<div class="yz-siniflar">' + siniflar.map(function (s) { return '<button data-k="sinif" data-v="' + s + '" class="' + (S.sinif === s ? 'on' : '') + '"><b>' + s + '</b><small>' + (s <= 8 ? 'ortaokul' : 'lise') + '</small></button>'; }).join('') + '</div>' +
      (S.sinif ? '<div class="yz-dersler">' + tl.map(function (x) { return '<button data-k="ders" data-v="' + esc(x.ders) + '" class="' + (S.ders === x.ders ? 'on' : '') + '"><span>' + (DERS_IK[x.ders] || '📘') + '</span><b>' + esc(x.ders) + '</b></button>'; }).join('') + '</div>' : '<div class="yz-ipucu">👆 Önce sınıfı seçin</div>') +
      (t ? '<div class="yz-satir"><span class="yz-et">Yazılı</span><div class="yz-seg">' + [1, 2].map(function (y) { return '<button data-k="yazili" data-v="' + y + '" class="' + (S.yazili === y ? 'on' : '') + '">' + y + '. yazılı</button>'; }).join('') + '</div></div>' : '') + '</section>';
    if (t) {
      var ulke = ulkeGeneli(t), sl = (t.senaryolar || {})[String(S.yazili)] || [], rk = konuRenk(t);
      h += '<section class="yz-kart"><div class="yz-kart-bas"><span class="yz-num">2</span><div><h2>MEB senaryosu</h2><p>' + (ulke ? 'Bu yazılı için okul senaryosu yok' : 'Her senaryo, hangi öğrenme çıktısından kaç soru sorulacağını belirler') + '</p></div></div>' +
        (ulke ? '<div class="yz-bilgi"><span>🇹🇷</span><div><b>' + S.sinif + '. sınıf ' + esc(S.ders) + ' ' + S.yazili + '. yazılı ülke genelinde ortak sınav olarak yapılır.</b> MEB okul senaryosu yayımlamadı. “Kendi dağılımım” ile deneme yazılısı hazırlayabilirsiniz.</div></div>' : '') +
        '<div class="yz-senaryolar">' + sl.map(function (sn) {
          var sl2 = senaryoSlot(t, S.yazili, sn), konu = {}, sira = [];
          sl2.forEach(function (x) { if (!konu[x.konu]) sira.push(x.konu); konu[x.konu] = (konu[x.konu] || 0) + 1; });
          return '<button class="yz-snr' + (S.senaryo === sn ? ' on' : '') + '" data-snr="' + sn + '"><div class="ust"><b>' + sn + '. Senaryo</b><em>' + sl2.length + ' soru</em></div><div class="bar">' + sira.map(function (k) { return '<i style="flex:' + konu[k] + ';background:' + rk[k] + '"></i>'; }).join('') + '</div><ul>' + sira.map(function (k) { return '<li><s style="background:' + rk[k] + '"></s><span>' + esc(k) + '</span><i>' + konu[k] + '</i></li>'; }).join('') + '</ul></button>';
        }).join('') + '<button class="yz-snr ozel' + (S.senaryo === 'ozel' ? ' on' : '') + '" data-snr="ozel"><div class="ust"><b>Kendi dağılımım</b><em>' + (S.senaryo === 'ozel' ? ozelSlot(t).length + ' soru' : 'özel') + '</em></div><p>Öğrenme çıktılarını ve soru sayılarını kendiniz belirleyin.</p></button></div>' +
        (S.senaryo === 'ozel' ? '<div class="yz-ozel">' + t.satirlar.map(function (x) { var k = kodu(x), n = S.ozel[k] || 0; return '<div class="yz-oz' + (n ? ' var' : '') + '"><s style="background:' + rk[x.konu] + '"></s><div><small>' + esc(kodGoster(x.kod)) + (kodGoster(x.kod) ? ' · ' : '') + esc(x.konu) + '</small><p>' + esc(x.cikti) + '</p></div><div class="yz-sayac"><button data-oz="' + esc(k) + '" data-d="-1">−</button><b>' + n + '</b><button data-oz="' + esc(k) + '" data-d="1">+</button></div></div>'; }).join('') + '</div>' : '') +
        (S.senaryo && !(S.senaryo !== 'ozel' && ulke) ? onizlemeTablo(t, rk) : '') + '</section>';
    }
    var n = t && S.senaryo ? (S.senaryo === 'ozel' ? ozelSlot(t).length : senaryoSlot(t, S.yazili, S.senaryo).length) : 0;
    h += '<div class="yz-cubuk"><div class="yz-ozet">' + (n ? '<b>' + n + ' soruluk yazılı</b><span>' + S.sinif + '. sınıf · ' + esc(S.ders) + ' · ' + S.yazili + '. yazılı · ' + (S.senaryo === 'ozel' ? 'kendi dağılımı' : S.senaryo + '. senaryo') + '</span>' : '<b>Seçim yapın</b><span>Sınıf, ders, yazılı ve senaryo</span>') + '</div><button class="yz-btn ana" id="yz-uygula"' + (n ? '' : ' disabled') + '>Seçimi uygula <i>→</i></button></div>';
    cerceve(h); modBagla();
    kok.querySelectorAll('[data-k]').forEach(function (b) {
      b.onclick = function () {
        var k = b.dataset.k, v = b.dataset.v;
        if (k === 'sinif') { S.sinif = +v; S.ders = ''; S.senaryo = 0; }
        else if (k === 'ders') { S.ders = v; S.senaryo = 0; S.ozel = {}; }
        else if (k === 'yazili') { S.yazili = +v; S.senaryo = 0; }
        else if (k === 'donem') { S.donem = +v; if (!tablo()) { S.ders = ''; S.senaryo = 0; } }
        sakla(); v1();
      };
    });
    kok.querySelectorAll('[data-snr]').forEach(function (b) { b.onclick = function () { S.senaryo = b.dataset.snr === 'ozel' ? 'ozel' : +b.dataset.snr; sakla(); v1(); }; });
    kok.querySelectorAll('[data-oz]').forEach(function (b) { b.onclick = function () { var k = b.dataset.oz; S.ozel[k] = Math.max(0, Math.min(6, (S.ozel[k] || 0) + +b.dataset.d)); sakla(); v1(); }; });
    document.getElementById('yz-uygula').onclick = function () {
      var yeni = S.senaryo === 'ozel' ? ozelSlot(t) : senaryoSlot(t, S.yazili, S.senaryo);
      var ayni = S.slotlar.length === yeni.length && S.slotlar.every(function (x, i) { return x.kod === yeni[i].kod; });
      if (!ayni) { S.slotlar = yeni; S.sorular = []; S.id = 0; }
      if (!S.bilgi.baslik || !ayni) S.bilgi.baslik = varsayilanBaslik();
      git(2);
    };
  }
  function onizlemeTablo(t, rk) {
    var sl = S.senaryo === 'ozel' ? ozelSlot(t) : senaryoSlot(t, S.yazili, S.senaryo), g = [];
    sl.forEach(function (x) { var s = g[g.length - 1]; if (s && s.kod === x.kod) s.n++; else g.push({ kod: x.kod, konu: x.konu, cikti: x.cikti, n: 1 }); });
    if (!g.length) return '';
    return '<div class="yz-dagilim"><h3>Soru dağılımı</h3>' + g.map(function (x) { return '<div class="yz-dg"><s style="background:' + rk[x.konu] + '"></s><div><small>' + esc(kodGoster(x.kod)) + (kodGoster(x.kod) ? ' · ' : '') + esc(x.konu) + '</small><p>' + esc(x.cikti) + '</p></div><b>' + x.n + '</b></div>'; }).join('') + '</div>';
  }

  /* ================= 2. bilgiler ================= */
  function secim(liste, ad, deger) { return '<div class="yz-secim">' + liste.map(function (z) { return '<button data-' + ad + '="' + z[0] + '" class="' + (String(deger) === z[0] ? 'on' : '') + '">' + (z[3] ? '<span>' + z[3] + '</span>' : '') + '<b>' + z[1] + '</b><small>' + z[2] + '</small></button>'; }).join('') + '</div>'; }
  function v2() {
    var b = S.bilgi, benSay = S.slotlar.filter(function (x) { return x.ben; }).length, t = tablo(), rk = t ? konuRenk(t) : {};
    var h = '<div class="yz-iki"><section class="yz-kart"><div class="yz-kart-bas"><span class="yz-num">🏫</span><div><h2>Sınav bilgileri</h2><p>Kâğıdın başlığında görünür</p></div></div>' +
      (C.uye ? '<p class="yz-uye ok">✓ Okul ve öğretmen bilgileriniz hesabınızdan geldi. Burada değiştirirseniz tüm yazılı ve evraklarınızda güncellenir.</p>' : '<p class="yz-uye">💡 <b>Ücretsiz üye olursanız</b> okul adı ve öğretmen adınız bir kez yazıldıktan sonra tüm yazılılarınıza ve evraklarınıza otomatik işlenir; yazılılarınız her cihazdan açılır.' + (C.giris ? ' <a href="' + esc(C.giris) + '">Üye ol / giriş yap →</a>' : '') + '</p>') +
      '<label class="yz-alan"><span>Okul adı</span><input data-b="okul" value="' + esc(b.okul) + '" placeholder="Örn. Atatürk Ortaokulu"></label>' +
      '<label class="yz-alan"><span>Sınav başlığı</span><input data-b="baslik" value="' + esc(b.baslik) + '"></label>' +
      '<label class="yz-alan"><span>Öğretmen adı</span><input data-b="ogretmen" value="' + esc(b.ogretmen) + '" placeholder="Adınız soyadınız"></label>' +
      '<div class="yz-izgara2"><label class="yz-alan"><span>Sınav tarihi</span><input type="date" data-b="tarih" value="' + esc(b.tarih) + '"></label><label class="yz-alan"><span>Süre (dakika)</span><input type="number" min="10" max="240" data-b="sure" value="' + esc(b.sure) + '"></label></div></section>' +
      '<section class="yz-kart"><div class="yz-kart-bas"><span class="yz-num">🎚️</span><div><h2>Soru tercihleri</h2><p>Sorular bu tercihlere göre seçilir</p></div></div>' +
      (C.yz && C.yzmod === 'acik' ? '<div class="yz-alan"><span>Soru kaynağı</span>' + secim(KAYNAK, 'kn', S.kaynak) + '</div>' : '') +
      '<div class="yz-alan"><span>Zorluk</span>' + secim(ZOR, 'z', S.zorluk) + '</div><div class="yz-alan"><span>Soru türü</span>' + secim(TUR, 't', S.tur) + '</div><div class="yz-alan"><span>Cevap yazma alanı</span>' + secim(ALAN, 'al', S.alan) + '</div></section></div>' +
      '<section class="yz-kart"><div class="yz-kart-bas"><span class="yz-num">✍️</span><div><h2>Soruları kim yazsın?</h2><p>İşaretlemediğiniz sorular havuzdan otomatik gelir</p></div><span class="yz-rozet' + (benSay ? ' on' : '') + '">' + benSay + ' / ' + S.slotlar.length + ' soru sizde</span></div>' +
      '<div class="yz-hizli"><div><b>Kendim yazacağım soru sayısı</b><small>Sondan başlayarak işaretlenir; aşağıdan tek tek de seçebilirsiniz.</small></div><div class="yz-sayac buyuk"><button data-hz="-1">−</button><b>' + benSay + '</b><button data-hz="1">+</button></div></div>' +
      '<div class="yz-slotlar">' + S.slotlar.map(function (x, i) { return '<label class="yz-slot' + (x.ben ? ' ben' : '') + '"><i style="--r:' + (rk[x.konu] || '#6366f1') + '">' + (i + 1) + '</i><div><small>' + esc(kodGoster(x.kod)) + (kodGoster(x.kod) ? ' · ' : '') + esc(x.konu) + '</small><p>' + esc(x.cikti) + '</p></div><span class="yz-anahtar"><input type="checkbox" data-ben="' + i + '"' + (x.ben ? ' checked' : '') + '><em></em><span>' + (x.ben ? 'Ben yazacağım' : 'Otomatik') + '</span></span></label>'; }).join('') + '</div></section>' +
      '<div class="yz-cubuk"><button class="yz-btn" data-git2="1">← Geri</button><div class="yz-ozet"><b>' + (S.slotlar.length - benSay) + ' soru ' + (S.kaynak === 'yz' ? 'yapay zekâyla yazılacak' : 'otomatik hazırlanacak') + '</b><span>' + (benSay ? benSay + ' soruyu siz yazacaksınız' : 'Tüm sorular otomatik') + '</span></div>' + (S.sorular.length ? '<button class="yz-btn" data-git2="3">Mevcut sorular →</button>' : '') + '<button class="yz-btn ana" id="yz-hazirla">' + (S.sorular.length ? 'Yeniden hazırla' : 'Soruları hazırla') + ' <i>✨</i></button></div>';
    cerceve(h);
    kok.querySelectorAll('[data-b]').forEach(function (e) { e.oninput = function () { S.bilgi[e.dataset.b] = e.dataset.b === 'sure' ? +e.value : e.value; sakla(); if (e.dataset.b === 'okul' || e.dataset.b === 'ogretmen') profilKaydet(e.dataset.b, e.value); }; });
    kok.querySelectorAll('[data-z]').forEach(function (e) { e.onclick = function () { S.zorluk = +e.dataset.z; sakla(); v2(); }; });
    kok.querySelectorAll('[data-t]').forEach(function (e) { e.onclick = function () { S.tur = e.dataset.t; sakla(); v2(); }; });
    kok.querySelectorAll('[data-al]').forEach(function (e) { e.onclick = function () { S.alan = e.dataset.al; sakla(); v2(); }; });
    kok.querySelectorAll('[data-kn]').forEach(function (e) { e.onclick = function () { S.kaynak = e.dataset.kn; sakla(); v2(); }; });
    kok.querySelectorAll('[data-ben]').forEach(function (e) { e.onchange = function () { S.slotlar[+e.dataset.ben].ben = e.checked; sakla(); v2(); }; });
    kok.querySelectorAll('[data-hz]').forEach(function (e) { e.onclick = function () { var n = Math.max(0, Math.min(S.slotlar.length, benSay + +e.dataset.hz)); S.slotlar.forEach(function (x, i) { x.ben = i >= S.slotlar.length - n; }); sakla(); v2(); }; });
    kok.querySelectorAll('[data-git2]').forEach(function (e) { e.onclick = function () { git(+e.dataset.git2); }; });
    document.getElementById('yz-hazirla').onclick = function () {
      if (S.sorular.length && !confirm('Mevcut sorular silinip yeniden hazırlanacak. Devam edilsin mi?')) return;
      hazirla(this);
    };
  }
  function bosSoru(tur) { return { type: tur === 'test' ? 'multiple' : 'classic', paragraph: '', root: '', options: tur === 'test' ? { A: '', B: '', C: '', D: '' } : { A: '' }, correct: '' }; }
  function puanDagit(n) { if (!n) return []; var b = Math.floor(100 / n), k = 100 - b * n, o = []; for (var i = 0; i < n; i++) o.push(b + (i >= n - k ? 1 : 0)); return o; }
  function ortak() { var o = { benim: benim(), sinif: S.sinif, ders: S.ders, donem: S.donem, yazili: S.yazili, zorluk: S.zorluk, tur: S.tur, kaynak: S.kaynak }; if (S.mod === 'diger') { var t = digerTablo(); if (t) o.ozel_tablo = { alan: t.alan, satirlar: t.satirlar.map(function (x) { return { kod: x.kod, konu: x.konu }; }) }; } return o; }

  /* 1. aşama: havuzdaki sorular hemen gelir */
  function hazirla(btn) {
    btn.disabled = true; btn.innerHTML = '<span class="yz-don"></span> Havuz taranıyor…';
    var v = ortak(); v.slotlar = S.slotlar.map(function (x) { return { kod: x.kod, ben: x.ben ? 1 : 0 }; }); v.yz = 0;
    api('hazirla', { veri: v }, 30000).then(function (d) {
      var p = puanDagit(S.slotlar.length);
      S.sorular = S.slotlar.map(function (x, i) {
        var r = d.sorular[i];
        if (x.ben) return { kod: x.kod, id: 0, kaynak: 'ogretmen', soru: bosSoru(S.tur), puan: p[i], duzen: true, gorulen: [] };
        if (!r) return { kod: x.kod, id: 0, kaynak: 'yukleniyor', soru: bosSoru(S.tur), puan: p[i], duzen: false, gorulen: [] };
        return { kod: x.kod, id: r.id, kaynak: r.kaynak, soru: r.soru, puan: p[i], zorluk: r.zorluk, duzen: false, gorulen: [r.id] };
      });
      S.id = 0; git(3); if (d.uyari) bildir(d.uyari, 'uyari');
      yzKuyruk();
    }).catch(function (e) { btn.disabled = false; btn.innerHTML = 'Soruları hazırla <i>✨</i>'; bildir(e.message, 'hata'); });
  }
  /* 2. aşama: eksikler yapay zekâya küçük paketler hâlinde, aynı anda 3 istek */
  function reklamHazirlik(ac) { var r = document.getElementById('osc-rk-hazirlik-kap'); if (!r || r.dataset.kapali) return; r.classList.toggle('on', !!ac); }
  function yukleniyorMu() { return S.sorular.some(function (q) { return q.kaynak === 'yukleniyor'; }); }
  setInterval(function () { try { reklamHazirlik(sekme === 'yeni' && yukleniyorMu()); } catch (e) {} }, 1200);
  function yzKuyruk() {
    var no = ++kuyrukNo, grup = {}, is = [];
    S.sorular.forEach(function (q, i) { if (q.kaynak === 'yukleniyor') (grup[q.kod] = grup[q.kod] || []).push(i); });
    var parca = S.kaynak === 'yz' ? 1 : 2; Object.keys(grup).forEach(function (k) { var l = grup[k]; for (var j = 0; j < l.length; j += parca) is.push({ kod: k, idx: l.slice(j, j + parca) }); });
    if (!is.length) return;
    if (!C.yz) { is.forEach(function (x) { x.idx.forEach(function (i) { S.sorular[i].kaynak = 'bos'; kartYenile(i); }); }); sakla(); return; }
    var hata = '', kalan = is.length;
    var calis = function () {
      var x = is.shift(); if (!x || no !== kuyrukNo) return;
      var v = ortak(); v.kod = x.kod; v.n = x.idx.length; v.haric = kullanilanIdler(); v.anahtarlar = anahtarlar(-1); v.mevcut = ozetler(-1);
      api('yz', { veri: v }, 75000).then(function (l) {
        if (no !== kuyrukNo) return;
        benim(l.map(function (r) { return r.id; }));
        x.idx.forEach(function (i, j) { var q = S.sorular[i], r = l[j]; if (!q || q.kaynak !== 'yukleniyor') return; if (r) { q.id = r.id; q.kaynak = r.kaynak; q.soru = r.soru; q.zorluk = r.zorluk; q.gorulen = [r.id]; } else q.kaynak = 'bos'; kartYenile(i, true); });
        var bos = x.idx.filter(function (i) { var q = S.sorular[i]; return q && q.kaynak === 'bos'; });
        if (bos.length && !x.tekrar) { bos.forEach(function (i) { S.sorular[i].kaynak = 'yukleniyor'; kartYenile(i); }); is.push({ kod: x.kod, idx: bos, tekrar: 1 }); kalan++; }
      }).catch(function (e) {
        if (no !== kuyrukNo) return;
        if (!x.tekrar && !/hakkınız|doldu|giriş|Oturum/i.test(e.message)) { is.push({ kod: x.kod, idx: x.idx, tekrar: 1 }); kalan++; return; }   // bir kez daha dene
        hata = e.message;
        x.idx.forEach(function (i) { var q = S.sorular[i]; if (q && q.kaynak === 'yukleniyor') { q.kaynak = 'bos'; kartYenile(i); } });
      }).then(function () { if (no !== kuyrukNo) return; sakla(); if (--kalan === 0 && hata) bildir(hata + ' Boş kalan soruları “Değiştir” ile yeniden deneyebilir ya da kendiniz yazabilirsiniz.', 'uyari'); calis(); });
    };
    for (var w = 0; w < (S.kaynak === 'yz' ? 6 : 4); w++) calis();
  }
  function benzerlikAnahtari(s) { var d = document.createElement('div'); d.innerHTML = ((s && s.paragraph) || '') + ' ' + ((s && s.root) || ''); var t = (d.textContent || '').toLowerCase(); try { t = t.replace(/[^\p{L}\p{N}]+/gu, ''); } catch (e) { t = t.replace(/[\s\W]+/g, ''); } return t.slice(0, 140); }
  function anahtarlar(haric) { return S.sorular.filter(function (q, i) { return i !== haric && q.soru && (q.soru.root || q.soru.paragraph); }).map(function (q) { return benzerlikAnahtari(q.soru); }); }
  function ozetler(haric) { return S.sorular.filter(function (q, i) { return i !== haric && q.soru && (q.soru.root || q.soru.paragraph) && q.kaynak !== 'yukleniyor'; }).map(function (q) { var d = document.createElement('div'); d.innerHTML = (q.soru.paragraph || '') + ' ' + (q.soru.root || ''); return (d.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 110); }).slice(0, 20); }
  function kullanilanIdler() { var h = []; S.sorular.forEach(function (s) { if (s.id) h.push(s.id); (s.gorulen || []).forEach(function (g) { if (h.indexOf(g) < 0) h.push(g); }); }); return h; }

  /* ================= 3. sorular ================= */
  function soruIc(q) {
    var s = q.soru || {}, op = s.options || {}, h = '';
    if (s.paragraph) h += '<div class="yz-p">' + mat(s.paragraph) + '</div>';
    h += '<div class="yz-r">' + (s.root ? mat(s.root) : '<span class="yz-sil">Soru metni yok</span>') + '</div>';
    if (s.type === 'multiple' || s.type === 'truefalse') {
      var ks = Object.keys(op).filter(function (k) { return op[k]; });
      var cz = s.cozum ? '<div class="yz-cvp"><b>Çözüm</b><span>' + mat(s.cozum) + '</span></div>' : '';
      h += '<div class="yz-sec' + (ks.every(function (k) { return String(op[k]).replace(/<[^>]+>/g, '').length < 28 && op[k].indexOf('<img') < 0; }) ? ' kisa' : '') + '">' + ks.map(function (k) { return '<div class="' + (s.correct === k ? 'd' : '') + '"><b>' + k + '</b><span>' + mat(op[k]) + '</span></div>'; }).join('') + '</div>' + cz;
    } else h += '<div class="yz-cvp"><b>Cevap</b><span>' + (op.A ? mat(op.A) : '<i>cevap anahtarı yok — Düzenle ile ekleyebilirsiniz</i>') + '</span></div>';
    return h;
  }
  function kart(q, i) {
    var x = S.slotlar[i] || {}, ky = KAY[q.kaynak] || KAY.arsiv, son = S.sorular.length - 1, ic;
    if (q.duzen) ic = duzenleyici(q, i);
    else if (q.kaynak === 'yukleniyor') ic = '<div class="yz-soru-ic"><div class="yz-iskelet"><i></i><i></i><i class="k"></i></div><p class="yz-yaziyor"><span class="yz-don"></span> Yapay zekâ bu çıktıya uygun soru yazıyor…</p></div>';
    else ic = '<div class="yz-soru-ic">' + (q.kaynak === 'bos' ? '<div class="yz-bos"><b>Bu çıktı için hazır soru bulunamadı.</b><span><b>Değiştir</b> ile yeniden deneyin ya da <b>Düzenle</b> ile kendiniz yazın.</span></div>' : soruIc(q)) + '</div>' +
      '<div class="yz-soru-alt"><button class="yz-btn kucuk" data-x="degistir" data-i="' + i + '">↻ Değiştir</button><button class="yz-btn kucuk" data-x="duzenle" data-i="' + i + '">✎ Düzenle</button><span class="bosluk"></span><button class="yz-btn ikon" data-x="yukari" data-i="' + i + '" title="Yukarı taşı"' + (i ? '' : ' disabled') + '>↑</button><button class="yz-btn ikon" data-x="asagi" data-i="' + i + '" title="Aşağı taşı"' + (i < son ? '' : ' disabled') + '>↓</button></div>';
    return '<article class="yz-soru k-' + ky[1] + (q.duzen ? ' duzen' : '') + '" data-i="' + i + '"><div class="yz-soru-ust"><i class="no">' + (i + 1) + '</i><div class="ck"><small>' + esc(kodGoster(x.kod)) + (kodGoster(x.kod) ? ' · ' : '') + esc(x.konu || '') + '</small><span title="' + esc(x.cikti) + '">' + esc(x.cikti || '') + '</span></div>' +
      '<span class="yz-k ' + ky[1] + '">' + ky[0] + '</span><label class="yz-puan"><input type="number" min="0" max="100" value="' + (q.puan || 0) + '" data-puan="' + i + '"><span>puan</span></label></div>' + ic + '</article>';
  }
  function toplamPuan() { return S.sorular.reduce(function (a, q) { return a + (+q.puan || 0); }, 0); }
  function toplamYaz() { var t = toplamPuan(), el = kok.querySelector('.yz-toplam'); if (!el) return; el.className = 'yz-toplam' + (t === 100 ? ' tamam' : ' eksik'); el.style.setProperty('--p', Math.min(100, t)); el.querySelector('b').textContent = t; }
  function v3() {
    var yuk = S.sorular.filter(function (q) { return q.kaynak === 'yukleniyor'; }).length;
    var h = '<div class="yz-baslik3"><div><h2>' + esc(S.bilgi.baslik || varsayilanBaslik()) + '</h2><p>' + S.sorular.length + ' soru · ' + (S.senaryo === 'ozel' ? 'kendi dağılımı' : S.senaryo + '. senaryo') + ' · ' + (ZOR[S.zorluk] || ZOR[0])[1].toLowerCase() + ' zorluk</p></div>' + (yuk ? '<span class="yz-rozet on yz-yuk-rozet"><span class="yz-don"></span><span> ' + yuk + ' soru yazılıyor (20-40 sn)</span></span>' : '') + '</div>' +
      '<div class="yz-sorular">' + S.sorular.map(kart).join('') + '</div>' +
      '<div class="yz-cubuk"><span class="yz-toplam" style="--p:0"><i></i><span><b>0</b> / 100 puan</span></span><button class="yz-btn" id="yz-esit">⚖️ Eşit dağıt</button><span class="bosluk"></span><button class="yz-btn" data-git2="2">← Bilgiler</button><button class="yz-btn ana" id="yz-cikti">Çıktıya geç <i>→</i></button></div>';
    cerceve(h); toplamYaz();
    kok.querySelectorAll('.yz-soru').forEach(bagla);
    document.getElementById('yz-esit').onclick = function () { editorleriSakla(); var p = puanDagit(S.sorular.length); S.sorular.forEach(function (q, i) { q.puan = p[i]; }); sakla(); v3(); };
    kok.querySelectorAll('[data-git2]').forEach(function (e) { e.onclick = function () { git(+e.dataset.git2); }; });
    document.getElementById('yz-cikti').onclick = function () {
      var acik = S.sorular.filter(function (q) { return q.duzen; }).length, yk = S.sorular.filter(function (q) { return q.kaynak === 'yukleniyor'; }).length, bos = S.sorular.filter(function (q) { return q.kaynak === 'bos' || !(q.soru.root || q.soru.paragraph); }).length;
      if (acik) return bildir('Düzenlemesi bitmemiş ' + acik + ' soru var. Önce “Tamam”a basın.', 'uyari');
      if (yk) return bildir(yk + ' soru hâlâ yazılıyor, birkaç saniye bekleyin.', 'uyari');
      if (bos && !confirm(bos + ' soru boş. Yine de devam edilsin mi?')) return;
      var t = toplamPuan(); if (t !== 100 && !confirm('Toplam puan ' + t + '. Yine de devam edilsin mi?')) return;
      git(4); kaydet(true);
    };
  }
  function bagla(el) {
    el.querySelectorAll('[data-puan]').forEach(function (e) { e.oninput = function () { S.sorular[+e.dataset.puan].puan = Math.max(0, +e.value || 0); sakla(); toplamYaz(); }; });
    el.querySelectorAll('[data-x]').forEach(function (b) { b.onclick = function () { islem(b.dataset.x, +b.dataset.i, b); }; });
    var ed = el.querySelector('.yz-ed'); if (ed) edBagla(ed);
  }
  function kartYenile(i, yeni) {
    if (S.adim !== 3 || sekme !== 'yeni') return;
    var el = kok.querySelector('.yz-soru[data-i="' + i + '"]'); if (!el || el.querySelector('.yz-ed')) return;
    var d = document.createElement('div'); d.innerHTML = kart(S.sorular[i], i); var n = d.firstChild; el.parentNode.replaceChild(n, el); bagla(n); if (yeni) n.classList.add('yeni');
    var yk = S.sorular.filter(function (q) { return q.kaynak === 'yukleniyor'; }).length, r = kok.querySelector('.yz-yuk-rozet');
    if (r) { if (yk) r.lastChild.textContent = ' ' + yk + ' soru yazılıyor'; else r.remove(); }
  }
  function editorleriSakla() { kok.querySelectorAll('.yz-ed').forEach(function (el) { var q = S.sorular[+el.dataset.i]; if (q) q.soru = edOku(el, q); }); sakla(); }
  function islem(x, i, b) {
    var q = S.sorular[i];
    if (x === 'yukari' || x === 'asagi') { editorleriSakla(); var j = x === 'yukari' ? i - 1 : i + 1; var t1 = S.sorular[i]; S.sorular[i] = S.sorular[j]; S.sorular[j] = t1; var t2 = S.slotlar[i]; S.slotlar[i] = S.slotlar[j]; S.slotlar[j] = t2; sakla(); v3(); return; }
    if (x === 'duzenle') { if (q.kaynak === 'bos') { q.kaynak = 'ogretmen'; q.soru = bosSoru(S.tur); } q.duzen = true; sakla(); kartYenileZorla(i); return; }
    if (x === 'degistir') {
      b.disabled = true; b.innerHTML = '<span class="yz-don"></span> Aranıyor…';
      var v = ortak(); v.kod = q.kod; v.haric = kullanilanIdler(); v.anahtarlar = anahtarlar(i); v.mevcut = ozetler(i);
      api('degistir', { veri: v }, 70000).then(function (r) {
        if (r.kaynak === 'yz') benim(r.id);
        q.id = r.id; q.kaynak = r.kaynak; q.soru = r.soru; q.zorluk = r.zorluk; q.duzen = false; (q.gorulen = q.gorulen || []).push(r.id); sakla(); kartYenile(i, true);
      }).catch(function (e) { b.disabled = false; b.innerHTML = '↻ Değiştir'; bildir(e.message, 'hata'); });
    }
  }
  function kartYenileZorla(i) { var el = kok.querySelector('.yz-soru[data-i="' + i + '"]'); if (!el) return v3(); var d = document.createElement('div'); d.innerHTML = kart(S.sorular[i], i); var n = d.firstChild; el.parentNode.replaceChild(n, el); bagla(n); var ce = n.querySelector('[data-f=root]'); if (ce) ce.focus(); }
  function duzenleyici(q, i) {
    var s = q.soru, tur = s.type || 'classic', op = s.options || {};
    var harfler = tur === 'truefalse' ? ['A', 'B'] : ['A', 'B', 'C', 'D', 'E'];
    return '<div class="yz-ed" data-i="' + i + '"><div class="yz-ed-tur">' + [['classic', 'Açık uçlu'], ['multiple', 'Çoktan seçmeli'], ['gap', 'Boşluk doldurma'], ['truefalse', 'Doğru / Yanlış']].map(function (t) { return '<button data-tur="' + t[0] + '" class="' + (tur === t[0] ? 'on' : '') + '">' + t[1] + '</button>'; }).join('') + '</div>' +
      '<div class="yz-mat" title="Matematik: tıklayın, imlecin olduğu yere eklensin">' + MATBTN.map(function (b, j) { return '<button type="button" data-mb="' + j + '" title="' + esc(b[2] || b[0]) + '">' + b[0] + '</button>'; }).join('') + '<span class="ipucu">$…$ arasına yazılan ifade formül olarak görünür</span></div>' +
      '<div class="yz-alan"><span>Öncül / metin <small>(isteğe bağlı)</small></span><div class="yz-ce" contenteditable="true" data-f="paragraph" data-ph="Okuma metni, tablo açıklaması vb.">' + (s.paragraph || '') + '</div></div>' +
      '<div class="yz-alan"><span>Soru</span><div class="yz-ce buyuk" contenteditable="true" data-f="root" data-ph="Soru kökünü yazın…">' + (s.root || '') + '</div></div>' +
      (tur === 'multiple' || tur === 'truefalse' ? '<div class="yz-ed-sik">' + harfler.map(function (h) { var v = tur === 'truefalse' && !op[h] ? (h === 'A' ? 'Doğru' : 'Yanlış') : (op[h] || ''); return '<label class="yz-sk' + (s.correct === h ? ' d' : '') + '"><input type="radio" name="yz-d' + i + '" value="' + h + '"' + (s.correct === h ? ' checked' : '') + ' title="Doğru cevap"><b>' + h + '</b><div class="yz-ce" contenteditable="true" data-sik="' + h + '" data-ph="' + (h === 'E' ? 'E şıkkı (isteğe bağlı)' : h + ' şıkkı') + '">' + v + '</div></label>'; }).join('') + '<p class="yz-not">Doğru şıkkı soldaki harfe tıklayarak işaretleyin.</p></div>'
        : '<div class="yz-alan"><span>Cevap / puanlama anahtarı</span><div class="yz-ce" contenteditable="true" data-f="cevap" data-ph="Beklenen cevap (cevap anahtarında görünür)">' + (op.A || '') + '</div></div>') +
      '<div class="yz-ed-on"' + (matVar(s.root + s.paragraph + JSON.stringify(op)) ? '' : ' hidden') + '><small>Önizleme</small><div></div></div>' +
      '<div class="yz-ed-alt"><button class="yz-btn" data-ed="iptal">Vazgeç</button><button class="yz-btn ana" data-ed="tamam">Tamam ✓</button></div></div>';
  }
  var MATBTN = [['<i>a</i>⁄<i>b</i>', '$\\frac{a}{b}$', 'Kesir'], ['√', '$\\sqrt{x}$', 'Karekök'], ['ⁿ√', '$\\sqrt[3]{x}$', 'n. dereceden kök'], ['x²', '$x^{2}$', 'Üs'], ['x₁', '$x_{1}$', 'Alt indis'], ['1,6̄', '$1,\\overline{6}$', 'Devirli ondalık'], ['×', '$\\times$'], ['÷', '$\\div$'], ['π', '$\\pi$'], ['≤', '$\\le$'], ['≥', '$\\ge$'], ['≠', '$\\ne$'], ['°', '$^{\\circ}$', 'Derece'], ['∠', '$\\angle$', 'Açı'], ['ℝ', '$\\mathbb{R}$', 'Reel sayılar'], ['∈', '$\\in$']];
  var sonCe = null;
  function temizHtml(h) { var d = document.createElement('div'); d.innerHTML = h; d.querySelectorAll('script,style,iframe,object,embed,link,meta').forEach(function (x) { x.remove(); }); d.querySelectorAll('*').forEach(function (x) { [].slice.call(x.attributes).forEach(function (a) { if (/^on/i.test(a.name) || (/^(href|src)$/i.test(a.name) && /^\s*javascript:/i.test(a.value))) x.removeAttribute(a.name); }); }); return d.innerHTML.replace(/^(<br>|\s|&nbsp;)+|(<br>|\s|&nbsp;)+$/g, ''); }
  function edOku(el, q) {
    var on = el.querySelector('[data-tur].on'), tur = on ? on.dataset.tur : q.soru.type;
    var o = { type: tur, paragraph: temizHtml(el.querySelector('[data-f=paragraph]').innerHTML), root: temizHtml(el.querySelector('[data-f=root]').innerHTML), options: {}, correct: '' };
    if (tur === 'multiple' || tur === 'truefalse') { el.querySelectorAll('[data-sik]').forEach(function (c) { o.options[c.dataset.sik] = temizHtml(c.innerHTML); }); var r = el.querySelector('input[type=radio]:checked'); o.correct = r ? r.value : ''; }
    else { var c = el.querySelector('[data-f=cevap]'); o.options = { A: c ? temizHtml(c.innerHTML) : '' }; }
    return o;
  }
  function edBagla(el) {
    var i = +el.dataset.i, q = S.sorular[i], yedek = JSON.stringify(q.soru);
    el.querySelectorAll('[data-tur]').forEach(function (b) {
      b.onclick = function (e) {
        e.preventDefault(); var o = edOku(el, q), eski = o.type; o.type = b.dataset.tur;
        if (o.type === 'multiple' && eski !== 'multiple') o.options = { A: '', B: '', C: '', D: '' };
        if (o.type === 'truefalse') o.options = { A: 'Doğru', B: 'Yanlış' };
        if ((o.type === 'classic' || o.type === 'gap') && eski !== 'classic' && eski !== 'gap') o.options = { A: '' };
        q.soru = o; sakla(); kartYenileZorla(i);
      };
    });
    el.querySelectorAll('input[type=radio]').forEach(function (r) { r.onchange = function () { el.querySelectorAll('.yz-sk').forEach(function (l) { l.classList.toggle('d', l.contains(r)); }); }; });
    var onz = el.querySelector('.yz-ed-on'), onIc = onz ? onz.lastChild : null;
    var onGuncelle = function () { if (!onz) return; var o = edOku(el, q), h = (o.paragraph ? '<div class="yz-p">' + mat(o.paragraph) + '</div>' : '') + '<div class="yz-r">' + mat(o.root) + '</div>' + ((o.type === 'multiple' || o.type === 'truefalse') ? Object.keys(o.options).filter(function (k) { return o.options[k]; }).map(function (k) { return '<div><b>' + k + ')</b> ' + mat(o.options[k]) + '</div>'; }).join('') : (o.options.A ? '<div class="yz-cvp"><b>Cevap</b><span>' + mat(o.options.A) + '</span></div>' : '')); onIc.innerHTML = h; onz.hidden = !matVar(JSON.stringify(o)); };
    el.querySelectorAll('.yz-ce').forEach(function (c) { c.addEventListener('focus', function () { sonCe = c; }); c.addEventListener('input', onGuncelle); });
    el.querySelectorAll('[data-mb]').forEach(function (b) {
      b.addEventListener('mousedown', function (e) { e.preventDefault(); });
      b.onclick = function (e) { e.preventDefault(); var c = sonCe && el.contains(sonCe) ? sonCe : el.querySelector('[data-f=root]'); c.focus(); document.execCommand('insertText', false, MATBTN[+b.dataset.mb][1]); onGuncelle(); };
    });
    if (onz && !onz.hidden) onGuncelle();
    el.querySelectorAll('.yz-ce').forEach(function (c) { c.addEventListener('paste', function (e) { var cd = e.clipboardData || window.clipboardData, t = cd.getData('text/plain'); if (t && !([].slice.call(cd.types || []).indexOf('Files') >= 0)) { e.preventDefault(); document.execCommand('insertText', false, t); } }); });
    el.querySelector('[data-ed=tamam]').onclick = function () {
      var o = edOku(el, q);
      if (!o.root && !o.paragraph) return bildir('Soru metni boş olamaz.', 'uyari');
      if (o.type === 'multiple' && !o.correct) return bildir('Doğru şıkkı işaretleyin.', 'uyari');
      if (JSON.stringify(o) !== yedek) { q.kaynak = 'ogretmen'; q.id = 0; }
      q.soru = o; q.duzen = false; sakla(); kartYenileZorla(i);
    };
    el.querySelector('[data-ed=iptal]').onclick = function () { q.soru = JSON.parse(yedek); q.duzen = false; if (q.kaynak === 'ogretmen' && !(q.soru.root || q.soru.paragraph)) q.kaynak = 'bos'; sakla(); kartYenileZorla(i); };
  }

  /* ================= 4. çıktı ================= */
  function satirSayisi(q) {
    var s = q.soru || {}, p = +q.puan || 0;
    if (s.type === 'multiple' || s.type === 'truefalse') return 0;
    if (s.type === 'gap') return /(\.{4,}|…{2,}|_{3,})/.test(s.root || '') ? 0 : 1;
    if (S.alan === 'az') return Math.max(2, Math.min(5, Math.round(p / 5)));
    if (S.alan === 'genis') return Math.max(6, Math.min(16, Math.round(p / 1.6)));
    return Math.max(3, Math.min(10, Math.round(p / 2.6)));
  }
  /** cevap için bırakılacak boşluk (piksel); çizgili seçimde 0 */
  function bosluk(q) {
    var s = q.soru || {}, p = +q.puan || 0;
    if (S.alan !== 'bos' || s.type === 'multiple' || s.type === 'truefalse' || s.type === 'gap') return 0;
    var md = (String(s.root || '').match(/<br>\s*[a-hç]\)/g) || []).length;
    return Math.max(110, Math.min(380, 60 + p * 9 + md * 40));
  }
  function kagit(anahtar, word) {
    var b = S.bilgi, M = word ? function (x) { return x; } : mat;
    var soruHtml = S.sorular.map(function (q, i) {
      var s = q.soru || {}, op = s.options || {}, h = '', n = S.alan === 'bos' ? 0 : satirSayisi(q), bs = bosluk(q);
      h += '<div class="s"><table class="sb" width="100%" cellpadding="0" cellspacing="0"><tr><td class="sn" valign="top">' + (i + 1) + '.</td><td class="sm" valign="top">' + (s.paragraph ? '<div class="p">' + M(s.paragraph) + '</div>' : '') + '<div class="r">' + M(s.root || '') + '</div>';
      if (s.type === 'multiple' || s.type === 'truefalse') {
        var ks = Object.keys(op).filter(function (k) { return op[k]; }), kisa = ks.every(function (k) { return String(op[k]).replace(/<[^>]+>/g, '').replace(/\$[^$]*\$/g, 'xxxx').length < 26 && op[k].indexOf('<img') < 0; });
        h += kisa ? '<table class="sk" width="100%"><tr>' + ks.map(function (k) { return '<td><b>' + k + ')</b> ' + M(op[k]) + '</td>'; }).join('') + '</tr></table>' : '<div class="sl">' + ks.map(function (k) { return '<div><b>' + k + ')</b> ' + M(op[k]) + '</div>'; }).join('') + '</div>';
      }
      if (n) { h += '<div class="cv">'; for (var j = 0; j < n; j++) h += '<div class="cs">&nbsp;</div>'; h += '</div>'; }
      if (bs) h += '<div style="height:' + bs + 'px"></div>';
      h += '</td><td class="sp" valign="top">(' + (q.puan || 0) + ' P)</td></tr></table></div>';
      return h;
    }).join('');
    var key = '';
    if (anahtar) key = (word ? '<br clear="all" style="page-break-before:always">' : '<div class="kesme"></div>') + '<h2 class="ka">CEVAP ANAHTARI</h2><p class="kb">' + esc(b.baslik) + '</p><table class="ak" width="100%"><thead><tr><th>No</th><th>Öğrenme çıktısı</th><th>Cevap</th><th>Puan</th></tr></thead><tbody>' + S.sorular.map(function (q, i) {
      var s = q.soru || {}, op = s.options || {}, x = S.slotlar[i] || {};
      var c = (s.type === 'multiple' || s.type === 'truefalse') ? '<b>' + esc(s.correct || '?') + '</b>' + (s.correct && op[s.correct] ? ') ' + M(op[s.correct]) : '') + (s.cozum ? '<div class="cz">' + M(s.cozum) + '</div>' : '') : (op.A ? M(op.A) : '—');
      return '<tr><td class="c">' + (i + 1) + '</td><td class="kk">' + esc(kodGoster(x.kod)) + '<br><small>' + esc(x.konu || '') + '</small></td><td>' + c + '</td><td class="c">' + (q.puan || 0) + '</td></tr>';
    }).join('') + '</tbody></table>';
    var css = '@page{size:A4;margin:1.3cm 1.4cm}' + (word ? '@page Section1{size:21cm 29.7cm;margin:1.3cm 1.4cm}div.Section1{page:Section1}' : '') +
      'body{font-family:Calibri,"Segoe UI",Arial,sans-serif;font-size:11.5pt;color:#000;margin:0}' +
      '.bas{text-align:center;border:1.5px solid #000;padding:6px 8px 4px}.bas .tc{font-weight:700;font-size:11pt}.bas .ok{font-weight:700;font-size:12pt;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.bas .bs{font-weight:700;font-size:10.5pt;margin-top:2px}' +
      'table{border-collapse:collapse}.ogr{width:100%;margin:6px 0 14px}.ogr td{border:1.5px solid #000;padding:5px 7px;font-size:10.5pt;vertical-align:top}.ogr .pk{width:70px;text-align:center;font-weight:700}' +
      '.s{margin:0 0 12px;page-break-inside:avoid}.sb td{vertical-align:top;padding:0}.sn{width:26px;font-weight:700}.sp{width:52px;text-align:right;font-weight:700;font-size:10pt;white-space:nowrap}.p{border:1px solid #999;padding:5px 8px;margin:0 0 5px;font-size:11pt}.r{font-weight:600}.sk{margin-top:5px}.sk td{padding:2px 6px 2px 0;vertical-align:top}.sl div{margin:3px 0}' +
      '.yz-tb{border-collapse:collapse;margin:5px 0;width:100%}.yz-tb th,.yz-tb td{border:1px solid #777;padding:3px 7px;text-align:left;font-size:10.5pt;vertical-align:top}.yz-tb th{background:#f0f0f0}' +
      '.cv{margin:6px 0 0}.cs{height:26px;line-height:26px;border-bottom:1px dotted #8a8a8a;font-size:10pt}' +
      'img{max-width:100%;height:auto}.alt{margin-top:18px;text-align:right;font-size:10.5pt}.alt b{display:block}.imza{font-size:8pt;color:#888;text-align:center;margin-top:16px}' +
      '.ka{text-align:center;font-size:14pt;margin:0 0 2px}.kb{text-align:center;font-size:9.5pt;margin:0 0 10px}.ak th,.ak td{border:1px solid #000;padding:5px 7px;font-size:10.5pt;vertical-align:top;text-align:left}.ak .c{text-align:center;width:40px}.ak .kk{width:150px;font-size:9.5pt}.ak .cz{margin-top:3px;font-size:10pt}.kesme{page-break-before:always}';
    var govde = '<div class="bas"><div class="tc">T.C.</div><div class="ok">' + esc(trUp(b.okul || '……………………… OKULU')) + '</div><div class="bs">' + esc(b.baslik) + '</div></div>' +
      '<table class="ogr"><tr><td>Adı Soyadı: </td><td style="width:22%">Sınıfı / No: </td><td style="width:16%">Tarih: ' + esc(tarihTr(b.tarih)) + '</td><td style="width:13%">Süre: ' + esc(b.sure) + ' dk</td><td class="pk">PUAN<br><br></td></tr></table>' +
      soruHtml + '<div class="alt">Başarılar dilerim.<b>' + esc(b.ogretmen) + '</b>' + esc(S.ders) + ' Öğretmeni</div><div class="imza">' + esc(C.site || 'soruindir.net') + '</div>' + key;
    return '<!DOCTYPE html><html' + (word ? ' xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"' : ' lang="tr"') + '><head><meta charset="utf-8"><title>' + esc(b.baslik) + '</title>' + (word ? '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml><![endif]-->' : '') + (!word && window.katex ? '<link rel="stylesheet" href="' + KATEX + 'katex.min.css">' : '') + '<style>' + css + '.katex{font-size:1.08em}.yz-blok{display:inline-block;margin:4px 0 2px}.yz-blok .katex{font-size:1.25em}</style></head><body>' + (word ? '<div class="Section1">' + govde + '</div>' : govde) + '</body></html>';
  }
  function dosyaAdi(ek) { return (S.sinif + '-sinif-' + S.ders + '-' + S.donem + '-donem-' + S.yazili + '-yazili' + ek).toLocaleLowerCase('tr').replace(/ç/g, 'c').replace(/ğ/g, 'g').replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ş/g, 's').replace(/ü/g, 'u').replace(/[^a-z0-9]+/g, '-').replace(/-+$/, ''); }
  function indirBlob(blob, ad) { var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = ad; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 2000); }
  function eskiWord(anahtar) { indirBlob(new Blob(['\ufeff', kagit(anahtar, true)], { type: 'application/msword' }), dosyaAdi(anahtar ? '-cevap-anahtarli' : '') + '.doc'); }
  function jszip() {
    if (window.JSZip) return Promise.resolve();
    return new Promise(function (ok, no) { var sc = document.createElement('script'); sc.src = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js'; sc.onload = ok; sc.onerror = no; document.head.appendChild(sc); });
  }
  /** Gerçek .docx: formüller Word denklemi, resimler gömülü */
  function wordIndir(anahtar, b) {
    var eski = b ? b.innerHTML : '';
    if (b) { b.disabled = true; var bb = b.querySelector('b'); if (bb) bb.textContent = 'Hazırlanıyor…'; }
    var bitti = function () { if (b) { b.disabled = false; b.innerHTML = eski; } };
    var m = { okul: S.bilgi.okul, baslik: S.bilgi.baslik || varsayilanBaslik(), ogretmen: S.bilgi.ogretmen, ders: S.ders, tarih: tarihTr(S.bilgi.tarih), sure: S.bilgi.sure, site: C.site || 'soruindir.net', anahtar: !!anahtar,
      sorular: S.sorular.map(function (q, i) { var s = q.soru || {}, x = S.slotlar[i] || {}; return { paragraph: s.paragraph || '', root: s.root || '', type: s.type, options: s.options || {}, correct: s.correct || '', cozum: s.cozum || '', puan: q.puan, satir: S.alan === 'bos' ? 0 : satirSayisi(q), bosluk: bosluk(q), kod: kodGoster(x.kod), konu: x.konu || '' }; }) };
    jszip().then(function () { if (!window.OSC_DOCX) throw new Error('docx'); return window.OSC_DOCX.olustur(m); })
      .then(function (blob) { indirBlob(blob, dosyaAdi(anahtar ? '-cevap-anahtarli' : '') + '.docx'); bitti(); })
      .catch(function (e) { if (window.console) console.warn('docx', e); eskiWord(anahtar); bitti(); });
  }
  function yazdir(anahtar) {
    var f = document.createElement('iframe'); f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0'; document.body.appendChild(f);
    var d = f.contentWindow.document; d.open(); d.write(kagit(anahtar, false)); d.close();
    var bas0 = function () { try { f.contentWindow.focus(); f.contentWindow.print(); } catch (e) {} setTimeout(function () { f.remove(); }, 4000); };
    var bas = function () { try { var fs = f.contentWindow.document.fonts; if (fs && fs.ready) { var t = setTimeout(bas0, 2500); fs.ready.then(function () { clearTimeout(t); setTimeout(bas0, 100); }); return; } } catch (e) {} bas0(); };
    var imgs = d.images, kalan = imgs.length; if (!kalan) return setTimeout(bas, 250);
    [].forEach.call(imgs, function (im) { if (im.complete) { if (!--kalan) setTimeout(bas, 200); } else im.onload = im.onerror = function () { if (!--kalan) setTimeout(bas, 200); }; });
  }
  function v4() {
    var h = '<div class="yz-cikti"><div class="yz-onizle"><div class="yz-onizle-bas"><b>📄 Önizleme</b><span class="yz-et">Cevap alanı</span><div class="yz-seg kucuk">' + ALAN.map(function (a) { return '<button data-al="' + a[0] + '" class="' + (S.alan === a[0] ? 'on' : '') + '">' + a[1] + '</button>'; }).join('') + '</div><label class="yz-anahtar"><input type="checkbox" id="yz-ak"' + (S.anahtar ? ' checked' : '') + '><em></em><span>Cevap anahtarı</span></label></div><div class="yz-kagit"><iframe id="yz-if" title="Yazılı önizleme"></iframe></div></div>' +
      '<aside class="yz-yan"><div class="yz-kart koyu"><h2>İndir ve yazdır</h2><p>Öğrencilere dağıtacağınız kâğıt için <b>cevap anahtarsız</b> sürümü kullanın.</p>' +
      '<div class="yz-indir"><button class="yz-ib ana" data-o="word0"><span>W</span><div><b>Word</b><small>Cevap anahtarsız</small></div></button><button class="yz-ib" data-o="word1"><span>W</span><div><b>Word</b><small>Cevap anahtarlı</small></div></button><button class="yz-ib pdf" data-o="pdf0"><span>PDF</span><div><b>Yazdır / PDF</b><small>Cevap anahtarsız</small></div></button><button class="yz-ib pdf" data-o="pdf1"><span>PDF</span><div><b>Yazdır / PDF</b><small>Cevap anahtarlı</small></div></button></div>' +
      '<p class="kucuk">PDF için yazdırma penceresinde hedef olarak <b>“PDF olarak kaydet”</b>i seçin.</p></div>' +
      '<div class="yz-kart"><h2>Kaydet</h2><p class="yz-not" id="yz-kd">' + (S.id ? '✓ Yazılılarım’a kaydedildi.' : 'Yazılılarım’a kaydedilir; sonra açıp düzenleyebilirsiniz.') + '</p>' + (C.giris ? '<p class="yz-not kucuk">Şu an bu tarayıcıya kaydediliyor. <a href="' + esc(C.giris) + '"><b>Ücretsiz öğretmen girişi</b></a> yaparsanız yazılılarınız hesabınıza kaydedilir, her cihazdan açılır.</p>' : '') + '<button class="yz-btn tam" id="yz-kaydet">💾 Kaydet</button><button class="yz-btn tam hafif" data-git2="3">✎ Sorulara dön</button><button class="yz-btn tam hafif" id="yz-yeni">＋ Yeni yazılı</button></div></aside></div>';
    cerceve(h);
    var ifr = document.getElementById('yz-if'), yenile = function () { ifr.srcdoc = kagit(S.anahtar, false).replace('</style>', 'body{padding:34px 38px;background:#fff}.kesme{border-top:2px dashed #94a3b8;margin:30px 0 20px}</style>'); };
    ifr.onload = function () { try { ifr.style.height = Math.max(700, ifr.contentWindow.document.documentElement.scrollHeight + 20) + 'px'; } catch (e) {} };
    yenile();
    document.getElementById('yz-ak').onchange = function () { S.anahtar = this.checked; sakla(); yenile(); };
    kok.querySelectorAll('[data-al]').forEach(function (e) { e.onclick = function () { S.alan = e.dataset.al; sakla(); kok.querySelectorAll('[data-al]').forEach(function (x) { x.classList.toggle('on', x === e); }); yenile(); }; });
    kok.querySelectorAll('[data-o]').forEach(function (b) { b.onclick = function () { var o = b.dataset.o; if (o.indexOf('word') === 0) wordIndir(o === 'word1', b); else yazdir(o === 'pdf1'); }; });
    kok.querySelectorAll('[data-git2]').forEach(function (e) { e.onclick = function () { git(+e.dataset.git2); }; });
    document.getElementById('yz-kaydet').onclick = function () { kaydet(false); };
    document.getElementById('yz-yeni').onclick = function () { if (!confirm('Yeni yazılıya başlansın mı? (Bu yazılı Yazılılarım’da kayıtlı kalır.)')) return; yeniBasla(); };
  }
  function yeniBasla() { var ok = S.bilgi.okul, og = S.bilgi.ogretmen; kuyrukNo++; S = yeniDurum(); S.bilgi.okul = ok; S.bilgi.ogretmen = og; sakla(); sekme = 'yeni'; ciz(); }
  function kaydet(sessiz) {
    var veri = { sinif: S.sinif, ders: S.ders, donem: S.donem, yazili: S.yazili, yil: S.yil, senaryo: S.senaryo, ozel: S.ozel, bilgi: S.bilgi, zorluk: S.zorluk, tur: S.tur, alan: S.alan, slotlar: S.slotlar, anahtar: S.anahtar, sorular: S.sorular.map(function (q) { return { kod: q.kod, id: q.id, kaynak: q.kaynak, soru: q.soru, puan: q.puan }; }) };
    return api('kaydet', { id: S.id || 0, veri: veri }).then(function (r) { S.id = r.id; sakla(); var e = document.getElementById('yz-kd'); if (e) e.textContent = '✓ Yazılılarım’a kaydedildi.'; if (!sessiz) bildir('Yazılı kaydedildi.', 'ok'); }).catch(function (e) { if (!sessiz) bildir(e.message, 'hata'); });
  }

  /* ================= yazılılarım ================= */
  function vListe() {
    cerceve('<section class="yz-kart"><div class="yz-kart-bas"><span class="yz-num">📁</span><div><h2>Yazılılarım</h2><p>' + (C.yerel ? 'Bu tarayıcıda kaydettiğiniz yazılılar' + (C.giris ? ' · <a href="' + esc(C.giris) + '"><b>giriş yaparak hesabınıza kaydedin</b></a>' : '') : 'Kaydettiğiniz yazılılar') + '</p></div><button class="yz-btn ana sag" id="yz-yeni2">＋ Yeni yazılı</button></div><div id="yz-l" class="yz-liste"><div class="yz-yuk"><span class="yz-don b"></span></div></div></section>');
    document.getElementById('yz-yeni2').onclick = yeniBasla;
    api('liste').then(function (l) {
      var el = document.getElementById('yz-l');
      if (!l.length) { el.innerHTML = '<div class="yz-bos-liste"><div>📝</div><b>Henüz kayıtlı yazılınız yok</b><p>Hazırladığınız yazılılar burada listelenir.</p></div>'; return; }
      el.innerHTML = l.map(function (x) { return '<div class="yz-li"><div class="ik"><b>' + esc(x.sinif) + '</b><small>sınıf</small></div><div class="bil"><b>' + esc(x.ders) + ' · ' + esc(x.yazili) + '. yazılı</b><small>' + (x.senaryo === 'ozel' ? 'kendi dağılımı' : esc(x.senaryo) + '. senaryo') + ' · ' + x.n + ' soru · ' + esc(x.tarih) + '</small></div><button class="yz-btn kucuk ana" data-ac="' + x.id + '">Aç</button><button class="yz-btn kucuk" data-sil="' + x.id + '" title="Sil">🗑</button></div>'; }).join('');
      el.querySelectorAll('[data-ac]').forEach(function (b) { b.onclick = function () { b.disabled = true; api('getir', { id: b.dataset.ac }).then(function (r) { var v = r.veri, n = yeniDurum(); Object.keys(n).forEach(function (k) { if (v[k] !== undefined) n[k] = v[k]; }); n.id = r.id; n.sorular = (v.sorular || []).map(function (q) { q.duzen = false; q.gorulen = q.id ? [q.id] : []; if (q.kaynak === 'yukleniyor') q.kaynak = 'bos'; return q; }); n.adim = 4; kuyrukNo++; S = n; sakla(); sekme = 'yeni'; ciz(); }).catch(function (e) { b.disabled = false; bildir(e.message, 'hata'); }); }; });
      el.querySelectorAll('[data-sil]').forEach(function (b) { b.onclick = function () { if (!confirm('Bu yazılı silinsin mi?')) return; api('sil', { id: b.dataset.sil }).then(function () { if (S.id === +b.dataset.sil) S.id = 0; vListe(); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
    }).catch(function (e) { document.getElementById('yz-l').innerHTML = '<div class="yz-bos">' + esc(e.message) + '</div>'; });
  }

  if (S.adim > enFazlaAdim()) S.adim = enFazlaAdim();
  katexYukle();
  ciz();
  }
  window.OSC_YZ_BASLAT = BASLAT;
  BASLAT();
})();
