/* MEB taslak/çerçeve yıllık plan Excel dosyalarını okur (tarayıcıda ve Node'da).
 * ypOku(dosyalar) -> Promise<[{tur, sinif, ders, dosya, haftalar:[{n, saat, unite, konu, cikti, surec, olcme, sdb, deger, okur, bgh, otp}]}]>
 * dosyalar: [{ad, veri(ArrayBuffer|Uint8Array)}]  .zip içindeki .xlsx dosyaları da açılır. */
(function (kok) {
  var JSZ = kok.JSZip || (typeof require === 'function' ? require('jszip') : null);
  var CP857 = 'ÇüéâäàåçêëèïîıÄÅÉæÆôöòûùİÖÜø£ØŞşáíóúñÑĞğ';
  function adCoz(b) { try { return new TextDecoder('utf-8', { fatal: true }).decode(b); } catch (e) { var s = ''; for (var i = 0; i < b.length; i++) { var c = b[i]; s += c < 128 ? String.fromCharCode(c) : (CP857[c - 128] || '?'); } return s; } }
  function temiz(s) { return String(s || '').replace(/#U([0-9a-f]{4})/gi, function (_, h) { return String.fromCharCode(parseInt(h, 16)); }).normalize('NFC'); }
  function xmlMetin(s) { return s.replace(/<[^>]+>/g, '').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&apos;/g, "'").replace(/&#(\d+);/g, function (_, n) { return String.fromCharCode(+n); }).replace(/&amp;/g, '&'); }
  function sutun(r) { var m = /^([A-Z]+)/.exec(r), n = 0; for (var i = 0; i < m[1].length; i++) n = n * 26 + m[1].charCodeAt(i) - 64; return n - 1; }
  function up(s) { return String(s || '').replace(/i/g, 'İ').replace(/ı/g, 'I').toUpperCase(); }

  var DERS = [['TÜRK DİLİ VE EDEBİYATI', 'Türk Dili ve Edebiyatı'], ['TEMEL DÜZEY MATEMATİK', 'Temel Düzey Matematik'], ['TEMEL MATEMATİK', 'Temel Matematik'], ['İNKILAP TARİHİ', 'T.C. İnkılap Tarihi ve Atatürkçülük'], ['INKILAP TARİHİ', 'T.C. İnkılap Tarihi ve Atatürkçülük'], ['SOSYAL BİLİM ÇALIŞMALARI', 'Sosyal Bilim Çalışmaları'], ['SBÇ', 'Sosyal Bilim Çalışmaları'], ['SÇB', 'Sosyal Bilim Çalışmaları'],
    ['BİLİŞİM TEKNOLOJİLERİ', 'Bilişim Teknolojileri ve Yazılım'], ['İNSAN HAKLARI', 'İnsan Hakları, Vatandaşlık ve Demokrasi'], ['TEKNOLOJİ VE TASARIM', 'Teknoloji ve Tasarım'], ['HAYAT BİLGİSİ', 'Hayat Bilgisi'], ['HAYAT BILGISI', 'Hayat Bilgisi'], ['TURK DİLİ VE EDEBİYAT', 'Türk Dili ve Edebiyatı'], ['BEDEN EĞİTİMİ VE OYUN', 'Beden Eğitimi ve Oyun'], ['BEDEN EĞİTİMİ VE SPOR', 'Beden Eğitimi ve Spor'], ['BEDEN EĞİTİMİ', 'Beden Eğitimi ve Spor'],
    ['GÖRSEL SANATLAR', 'Görsel Sanatlar'], ['MÜZİK', 'Müzik'], ['FEN BİLİMLERİ', 'Fen Bilimleri'], ['SOSYAL BİLGİLER', 'Sosyal Bilgiler'], ['ÇOKLU YABANCI DİL ALMANCA', 'Almanca'], ['ALMANCA', 'Almanca'], ['ÇOKLU YABANCI DİL İNGİLİZCE', 'İngilizce (çoklu yabancı dil)'], ['İNGİLİZCE', 'İngilizce'], ['TÜRKÇE', 'Türkçe'], ['TURKÇE', 'Türkçe'], ['TÜRKCE', 'Türkçe'],
    ['MATEMATİK', 'Matematik'], ['FİZİK', 'Fizik'], ['KİMYA', 'Kimya'], ['BİYOLOJİ', 'Biyoloji'], ['COĞRAFYA', 'Coğrafya'], ['TARİH', 'Tarih'], ['FELSEFE', 'Felsefe'], ['SOSYOLOJİ', 'Sosyoloji'], ['MANTIK', 'Mantık'], ['PSİKOLOJİ', 'Psikoloji'], ['PSİOKOLOJİ', 'Psikoloji']];
  function dersBul(yol) { var u = up(String(yol).replace(/[-_]+/g, ' ')).replace(/LİSESİ|LISESI/g, ' ').replace(/SOSYAL BİLİMLER/g, ' ').replace(/FEN BİLİMLER(?!İ)/g, ' '); for (var i = 0; i < DERS.length; i++) if (u.indexOf(DERS[i][0]) >= 0) return DERS[i][1]; return ''; }
  function turBul(yol) { var u = up(yol); if (/FEN (BİLİMLER )?LİSESİ/.test(u)) return 'Fen Lisesi'; if (/SOSYAL BİLİMLER LİSESİ/.test(u)) return 'Sosyal Bilimler Lisesi'; if (/ANADOLU LİSESİ/.test(u)) return 'Anadolu Lisesi'; return ''; }
  function sinifBul(ad) { var u = up(ad); if (/HAZIRLIK/.test(u)) return 'H'; var m = /(\d{1,2})/.exec(u); return m && +m[1] >= 1 && +m[1] <= 12 ? String(+m[1]) : ''; }

  var ALAN = [['ay', /^AY$/], ['hafta', /HAFTA/], ['saat', /SAAT|SÜRE/], ['unite', /ÜNİTE|TEMA|ÖĞRENME ALANI/], ['konu', /KONU|METİN|İÇERİK/], ['surec', /SÜREÇ|AÇIKLAMA/], ['cikti', /ÇIKTI|KAZANIM/], ['olcme', /ÖLÇME/], ['sdb', /SOSYAL|DUYGUSAL/], ['deger', /DEĞER/], ['okur', /OKURYAZAR/], ['bgh', /BELİRLİ/], ['yontem', /YÖNTEM/], ['diger', /OKUL DIŞI|FARKLILAŞ|KANIT|AÇIKLAMALAR/]];
  function sinifla(h) { h = up(h).replace(/\s+/g, ' ').trim(); for (var i = 0; i < ALAN.length; i++) if (ALAN[i][1].test(h)) return ALAN[i][0]; return ''; }

  function sayfaOku(rows, ad, yol) {
    var hi = -1, hk = -1;
    for (var i = 0; i < Math.min(rows.length, 15) && hi < 0; i++) (rows[i] || []).forEach(function (c, j) { if (hi < 0 && /^HAFTA\s*$/.test(up(String(c || '').trim()))) { hi = i; hk = j; } });
    if (hi < 0) { if (kok.YP_DEBUG) console.log('başlık yok', ad); return null; }
    var h = rows[hi] || [], alt = rows[hi + 1] || [], top = rows[hi - 1] || [], kol = [], son = '';
    var altBaslik = !/\d/.test(String(alt[hk] || '')) && alt.some(function (x) { return String(x || '').trim(); });
    var n = Math.max(h.length, alt.length, top.length);
    for (var j = 0; j < n; j++) { var t = String(h[j] || '').trim(), k = t ? sinifla(t) : ''; if (!t) k = top[j] && String(top[j]).trim() ? sinifla(top[j]) : son; if (j === hk) k = 'hafta'; son = k; kol[j] = { k: k, alt: altBaslik ? String(alt[j] || '').trim() : '' }; }
    var bas = hi + (altBaslik ? 2 : 1), haftalar = [], cur = null, otp = false;
    for (var r = bas; r < rows.length; r++) {
      var row = rows[r] || [], dolu = row.filter(function (x) { return String(x || '').trim(); });
      if (!dolu.length) continue;
      var hf = String(row[kol.findIndex(function (c) { return c && c.k === 'hafta'; })] || '');
      var tum = up(dolu.join(' '));
      if (dolu.length <= 2 && /TATİL/.test(tum)) { cur = null; continue; }
      if (/OKUL TEMELLİ PLANLAMA/.test(tum) && dolu.length <= 2) { otp = true; continue; }
      var m = /(\d{1,2})\s*\.\s*hafta/i.exec(hf);
      if (m) { cur = { n: +m[1], saat: '', unite: [], konu: [], cikti: [], surec: [], olcme: [], sdb: [], deger: [], okur: [], bgh: [], otp: otp }; otp = false; haftalar.push(cur); }
      if (!cur) continue;
      kol.forEach(function (c, j) {
        var v = String(row[j] == null ? '' : row[j]).replace(/\r/g, '').replace(/[ \t]+/g, ' ').replace(/\n\s*\n+/g, '\n').trim(); if (!v || !c || !c.k) return;
        if (c.k === 'saat') { if (!cur.saat) cur.saat = v; else if (cur.saat !== v) cur.saat = String((parseFloat(cur.saat) || 0) + (parseFloat(v) || 0)); return; }
        var hedef = c.k === 'yontem' || c.k === 'diger' || c.k === 'ay' || c.k === 'hafta' ? null : cur[c.k]; if (!hedef) return;
        if (c.alt && c.k === 'cikti' && !/^(ÖĞRENME|KAZANIM)/.test(up(c.alt))) v = c.alt.charAt(0) + c.alt.slice(1).toLocaleLowerCase('tr') + ': ' + v;
        if (hedef.indexOf(v) < 0) hedef.push(v);
      });
    }
    if (!haftalar.length) return null;
    haftalar.forEach(function (w) { ['unite', 'konu', 'cikti', 'surec', 'olcme', 'sdb', 'deger', 'okur', 'bgh'].forEach(function (k) { w[k] = w[k].join('\n'); }); });
    var dosyaAdi = String(yol).split('/').pop(), sbc = /SBÇ\s*(\d)/i.exec(ad), sin = sbc ? 'L' : sinifBul(ad);
    var ders = sbc ? 'Sosyal Bilim Çalışmaları ' + sbc[1] : /\(TD\)/i.test(ad) ? 'Temel Düzey Matematik' : (dersBul(dosyaAdi) || dersBul(yol) || dersBul(ad));
    var st = /(\d)\s*SAAT/i.exec(ad); if (st && ders) ders += ' (' + st[1] + ' saat)';
    if (!sin) { var fm = up(dosyaAdi).match(/(\d{1,2})\s*\.\s*SINIF/g); sin = fm && fm.length === 1 ? sinifBul(fm[0]) : (/LİSE/.test(up(yol)) ? 'L' : ''); }
    if (!sin || !ders) return null;
    return { tur: turBul(yol), sinif: sin, ders: ders, dosya: yol, haftalar: haftalar };
  }

  function xlsxOku(zip, yol) {
    var f = function (p) { var x = zip.file(p); return x ? x.async('string') : Promise.resolve(''); };
    return Promise.all([f('xl/workbook.xml'), f('xl/_rels/workbook.xml.rels'), f('xl/sharedStrings.xml')]).then(function (a) {
      var ss = []; (a[2].match(/<si>[\s\S]*?<\/si>/g) || []).forEach(function (s) { ss.push(xmlMetin((s.match(/<t[^>]*>[\s\S]*?<\/t>/g) || []).join(''))); });
      var rel = {}; (a[1].match(/<Relationship [^>]+>/g) || []).forEach(function (r) { var id = /Id="([^"]+)"/.exec(r), t = /Target="([^"]+)"/.exec(r); if (id && t) rel[id[1]] = t[1].replace(/^\/?xl\//, ''); });
      var sh = []; (a[0].match(/<sheet [^>]+>/g) || []).forEach(function (s) { var ad = /name="([^"]+)"/.exec(s), id = /r:id="([^"]+)"/.exec(s); if (ad && id && rel[id[1]]) sh.push({ ad: xmlMetin(ad[1]), yol: 'xl/' + rel[id[1]] }); });
      return Promise.all(sh.map(function (s) { return f(s.yol).then(function (x) {
        var rows = [];
        (x.match(/<row\b[^>]*?(?:\/>|>[\s\S]*?<\/row>)/g) || []).forEach(function (rw) {
          var ri = +(/r="(\d+)"/.exec(rw) || [0, rows.length + 1])[1] - 1, row = [];
          (rw.match(/<c\b[^>]*?(?:\/>|>[\s\S]*?<\/c>)/g) || []).forEach(function (c) {
            var r = /r="([A-Z]+)\d+"/.exec(c), t = /t="([^"]+)"/.exec(c), v = /<v>([\s\S]*?)<\/v>/.exec(c), is = /<is>([\s\S]*?)<\/is>/.exec(c);
            if (!r) return; var val = '';
            if (t && t[1] === 's' && v) val = ss[+v[1]] || ''; else if (is) val = xmlMetin(is[1]); else if (v) val = xmlMetin(v[1]);
            row[sutun(r[1])] = val;
          });
          rows[ri] = row;
        });
        return sayfaOku(rows, s.ad, yol);
      }); }));
    });
  }

  function ypOku(dosyalar) {
    var sonuc = [];
    return dosyalar.reduce(function (p, d) {
      return p.then(function () {
        var ad = temiz(d.ad);
        if (/\.zip$/i.test(ad)) return JSZ.loadAsync(d.veri, { decodeFileName: adCoz }).then(function (z) {
          var l = []; z.forEach(function (yol, e) { if (!e.dir && /\.xlsx$/i.test(yol) && !/__MACOSX|\/\._|yay[ıi]nlar/i.test(yol)) l.push(e); });
          return l.reduce(function (q, e) { return q.then(function () { return e.async('uint8array').then(function (b) { return JSZ.loadAsync(b); }).then(function (x) { return xlsxOku(x, temiz(ad.replace(/\.zip$/i, '') + '/' + e.name)); }).then(function (r) { r.forEach(function (x) { if (x) sonuc.push(x); }); }).catch(function () {}); }); }, Promise.resolve());
        });
        if (/\.xlsx$/i.test(ad)) return JSZ.loadAsync(d.veri).then(function (x) { return xlsxOku(x, ad); }).then(function (r) { r.forEach(function (x) { if (x) sonuc.push(x); }); });
      });
    }, Promise.resolve()).then(function () { return sonuc; });
  }
  kok.ypOku = ypOku;
  if (typeof module !== 'undefined') module.exports = ypOku;
})(typeof window !== 'undefined' ? window : globalThis);
