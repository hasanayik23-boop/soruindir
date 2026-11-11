/* =====================================================================
 * OSC_DOCX — yazılı kâğıdını gerçek Word (.docx) dosyası olarak üretir.
 *   - $…$ LaTeX formülleri Word'ün kendi denklemlerine (OMML) çevrilir
 *   - resimler dosyanın içine gömülür
 *   Gerekli: JSZip (window.JSZip)
 * ===================================================================== */
(function () {
  'use strict';
  if (window.OSC_DOCX) return;
  var NS = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"';
  function x(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function varlik(t) { return String(t).replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&'); }

  /* ---------------- LaTeX → OMML ---------------- */
  var SEMBOL = { times: '×', div: '÷', cdot: '·', pi: 'π', le: '≤', leq: '≤', ge: '≥', geq: '≥', ne: '≠', neq: '≠', pm: '±', mp: '∓', infty: '∞', circ: '°', degree: '°', angle: '∠', triangle: '△', perp: '⊥', parallel: '∥', 'in': '∈', notin: '∉', subset: '⊂', subseteq: '⊆', supset: '⊃', cup: '∪', cap: '∩', emptyset: '∅', varnothing: '∅', Rightarrow: '⇒', rightarrow: '→', to: '→', Leftrightarrow: '⇔', leftarrow: '←', approx: '≈', equiv: '≡', sim: '∼', cong: '≅', propto: '∝', ldots: '…', dots: '…', cdots: '⋯', square: '□', alpha: 'α', beta: 'β', gamma: 'γ', delta: 'δ', Delta: 'Δ', theta: 'θ', lambda: 'λ', mu: 'μ', sigma: 'σ', Sigma: 'Σ', omega: 'ω', Omega: 'Ω', phi: 'φ', varphi: 'φ', epsilon: 'ε', rho: 'ρ', tau: 'τ', sum: '∑', prod: '∏', int: '∫', forall: '∀', exists: '∃', neg: '¬', wedge: '∧', vee: '∨', mid: '|', lfloor: '⌊', rfloor: '⌋', lceil: '⌈', rceil: '⌉', langle: '⟨', rangle: '⟩', '%': '%', '{': '{', '}': '}', '$': '$', '#': '#', '&': '&', '_': '_', ',': ' ', ';': ' ', ':': ' ', quad: ' ', qquad: '  ', '!': '', ' ': ' ' };
  var BB = { R: 'ℝ', N: 'ℕ', Z: 'ℤ', Q: 'ℚ', C: 'ℂ', I: '𝕀' };
  var FONK = { log: 1, ln: 1, sin: 1, cos: 1, tan: 1, cot: 1, sec: 1, csc: 1, lim: 1, max: 1, min: 1, exp: 1 };
  function mr(t, normal) { return '<m:r>' + (normal ? '<m:rPr><m:nor/></m:rPr>' : '') + '<m:t xml:space="preserve">' + x(t) + '</m:t></m:r>'; }
  function omml(tex) {
    var s = varlik(tex), i = 0; if (window.OSC_TEX_DUZELT) s = window.OSC_TEX_DUZELT(s);
    function bosluk() { while (i < s.length && /\s/.test(s[i])) i++; }
    function komut() { var m = /^\\([a-zA-Z]+|.)/.exec(s.slice(i)); if (!m) { i++; return ''; } i += m[0].length; return m[1]; }
    function arguman() { bosluk(); if (s[i] === '{') { i++; var r = dizi('}'); i++; return r; } if (s[i] === '\\') { var p = i; var c = komut(); i = p; return dizi(null, 1); } var ch = s[i++] || ''; return ch ? mr(ch) : ''; }
    function secenek() { bosluk(); if (s[i] !== '[') return null; i++; var r = dizi(']'); i++; return r; }
    function sinirlayici() { bosluk(); if (s[i] === '\\') { var c = komut(); return c === '{' ? '{' : c === '}' ? '}' : c === '|' ? '‖' : (SEMBOL[c] !== undefined ? SEMBOL[c] : c === 'lbrace' ? '{' : c === 'rbrace' ? '}' : ''); } var ch = s[i++]; return ch === '.' ? '' : ch; }
    function oge(stop) {
      bosluk(); if (i >= s.length) return null;
      var c = s[i];
      if (stop && c === stop) return null;
      if (c === '}' || c === ']' && stop === ']') return null;
      if (c === '{') { i++; var g = dizi('}'); i++; return g; }
      if (c === '^' || c === '_') return null;
      if (c === '&') { i++; return mr(' '); }
      if (c === '\\') {
        var save = i, k = komut();
        if (k === 'right') { i = save; return null; }
        if (k === 'frac' || k === 'dfrac' || k === 'tfrac' || k === 'cfrac') { var a = arguman(), b = arguman(); return '<m:f><m:num>' + a + '</m:num><m:den>' + b + '</m:den></m:f>'; }
        if (k === 'binom') { var a2 = arguman(), b2 = arguman(); return '<m:d><m:e><m:f><m:fPr><m:type m:val="noBar"/></m:fPr><m:num>' + a2 + '</m:num><m:den>' + b2 + '</m:den></m:f></m:e></m:d>'; }
        if (k === 'sqrt') { var d = secenek(), e = arguman(); return '<m:rad><m:radPr>' + (d ? '' : '<m:degHide m:val="1"/>') + '</m:radPr><m:deg>' + (d || '') + '</m:deg><m:e>' + e + '</m:e></m:rad>'; }
        if (k === 'overline' || k === 'bar' && s[i] === '{') { return '<m:bar><m:barPr><m:pos m:val="top"/></m:barPr><m:e>' + arguman() + '</m:e></m:bar>'; }
        if (k === 'underline') return '<m:bar><m:barPr><m:pos m:val="bot"/></m:barPr><m:e>' + arguman() + '</m:e></m:bar>';
        var AKS = { vec: '⃗', hat: '̂', widehat: '̂', dot: '̇', tilde: '̃', bar: '̄', overrightarrow: '⃗' };
        if (AKS[k]) return '<m:acc><m:accPr><m:chr m:val="' + AKS[k] + '"/></m:accPr><m:e>' + arguman() + '</m:e></m:acc>';
        if (k === 'left') { var bc = sinirlayici(), ic = dizi('\\right'); var ec = ''; if (s.slice(i, i + 6) === '\\right') { i += 6; ec = sinirlayici(); } return '<m:d><m:dPr><m:begChr m:val="' + x(bc) + '"/><m:endChr m:val="' + x(ec) + '"/></m:dPr><m:e>' + ic + '</m:e></m:d>'; }
        if (k === 'text' || k === 'mathrm' || k === 'textrm' || k === 'operatorname' || k === 'mbox') { bosluk(); var t = ''; if (s[i] === '{') { var dz = 1; i++; while (i < s.length && dz) { if (s[i] === '{') dz++; else if (s[i] === '}') { dz--; if (!dz) break; } t += s[i++]; } i++; } return mr(t, true); }
        if (k === 'mathbb') { bosluk(); var l = ''; if (s[i] === '{') { i++; while (i < s.length && s[i] !== '}') l += s[i++]; i++; } else l = s[i++]; return mr(BB[l] || l, true); }
        if (k === 'mathbf' || k === 'boldsymbol' || k === 'mathit' || k === 'boxed' || k === 'displaystyle' || k === 'textstyle') { return k === 'displaystyle' || k === 'textstyle' ? '' : arguman(); }
        if (k === 'begin' || k === 'end') { arguman(); return ''; }
        if (k === '\\') return mr(' ');
        if (FONK[k]) return mr(k, true);
        if (SEMBOL[k] !== undefined) return SEMBOL[k] ? mr(SEMBOL[k]) : '';
        return mr(k);
      }
      i++;
      if (/\s/.test(c)) return '';
      if (/\d/.test(c)) { while (i < s.length && /\d/.test(s[i])) c += s[i++]; }
      return mr(c);
    }
    function dizi(stop, tek) {
      var parcalar = [];
      while (i < s.length) {
        if (stop === '\\right' && s.slice(i, i + 6) === '\\right') break;
        if (stop && stop.length === 1 && s[i] === stop) break;
        if (!stop && s[i] === '}') { i++; continue; }
        if (s[i] === '^' || s[i] === '_') {
          var taban = parcalar.length ? parcalar.pop() : mr(''), alt = null, ust = null;
          while (s[i] === '^' || s[i] === '_') { var tur = s[i++]; var arg = arguman(); if (tur === '^') ust = arg; else alt = arg; bosluk(); }
          if (alt !== null && ust !== null) parcalar.push('<m:sSubSup><m:e>' + taban + '</m:e><m:sub>' + alt + '</m:sub><m:sup>' + ust + '</m:sup></m:sSubSup>');
          else if (ust !== null) parcalar.push('<m:sSup><m:e>' + taban + '</m:e><m:sup>' + ust + '</m:sup></m:sSup>');
          else parcalar.push('<m:sSub><m:e>' + taban + '</m:e><m:sub>' + alt + '</m:sub></m:sSub>');
          if (tek) break; continue;
        }
        var o = oge(stop && stop.length === 1 ? stop : null); if (o === null) { if (i < s.length && (s[i] === '}' || s[i] === ']') && !stop) { i++; continue; } if (stop === '\\right') { if (s.slice(i, i + 6) === '\\right') break; i++; continue; } break; }
        if (o !== '') parcalar.push(o); if (tek && o !== '') break;
      }
      return parcalar.join('');
    }
    var sonuc = '';
    try { sonuc = dizi(null); } catch (e) { sonuc = mr(s); }
    return '<m:oMath>' + sonuc + '</m:oMath>';
  }

  /* ---------------- HTML → Word paragraf içeriği ---------------- */
  function rpr(f) { var o = ''; if (f.b) o += '<w:b/>'; if (f.i) o += '<w:i/>'; if (f.u) o += '<w:u w:val="single"/>'; if (f.sz) o += '<w:sz w:val="' + f.sz + '"/><w:szCs w:val="' + f.sz + '"/>'; if (f.renk) o += '<w:color w:val="' + f.renk + '"/>'; if (f.sup) o += '<w:vertAlign w:val="superscript"/>'; if (f.sub) o += '<w:vertAlign w:val="subscript"/>'; return o ? '<w:rPr>' + o + '</w:rPr>' : ''; }
  function wr(t, f) { return t === '' ? '' : '<w:r>' + rpr(f || {}) + '<w:t xml:space="preserve">' + x(t) + '</w:t></w:r>'; }
  function metinParca(t, f) {
    // $…$, $$…$$, \(…\) formülleri OMML'e
    var o = '', re = /\$\$([\s\S]+?)\$\$|\\\(([\s\S]+?)\\\)|\$([^$\n]+?)\$/g, son = 0, m;
    while ((m = re.exec(t))) { o += wr(t.slice(son, m.index), f); o += omml(m[1] || m[2] || m[3]); son = re.lastIndex; }
    return o + wr(t.slice(son), f);
  }
  function Belge() { this.resimler = []; this.harita = {}; this.id = 1; }
  Belge.prototype.icerik = function (html, f0) {
    var self = this, d = new DOMParser().parseFromString('<div>' + String(html || '') + '</div>', 'text/html').body.firstChild, o = '';
    // metin düğümleri ayrık gelse bile $…$ tek parça işlensin diye: önce satır içi düğümleri birleştir
    function yuru(n, f) {
      if (n.nodeType === 3) { o += metinParca(n.nodeValue.replace(/\s+/g, ' '), f); return; }
      if (n.nodeType !== 1) return;
      var t = n.tagName.toLowerCase(), g = Object.assign({}, f);
      if (t === 'br') { o += '<w:r><w:br/></w:r>'; return; }
      if (t === 'img') { o += self.resim(n.getAttribute('src')); return; }
      if (t === 'b' || t === 'strong') g.b = true;
      if (t === 'i' || t === 'em') g.i = true;
      if (t === 'u') g.u = true;
      if (t === 'sup') g.sup = true;
      if (t === 'sub') g.sub = true;
      if (t === 'tr' && n.previousElementSibling) o += '<w:r><w:br/></w:r>';
      if ((t === 'td' || t === 'th') && n.previousElementSibling) o += wr('  |  ', g);
      if ((t === 'p' || t === 'div' || t === 'li') && n.previousSibling && o) o += '<w:r><w:br/></w:r>';
      if (t === 'span' && /okunamad/.test(n.textContent || '')) g.renk = '999999';
      // birleşik metin: alt düğümler yalnızca metin ise tek parça (formül bölünmesin)
      var yalin = [].every.call(n.childNodes, function (c) { return c.nodeType === 3; });
      if (yalin) { o += metinParca((n.textContent || '').replace(/\s+/g, ' '), g); return; }
      [].forEach.call(n.childNodes, function (c) { yuru(c, g); });
    }
    var hepsiMetin = [].every.call(d.childNodes, function (c) { return c.nodeType === 3 || (c.nodeType === 1 && /^(b|strong|i|em|u|span)$/i.test(c.tagName) && ![].some.call(c.childNodes, function (z) { return z.nodeType === 1; })); });
    if (hepsiMetin && /\$/.test(d.textContent || '') && d.querySelector('b,strong,i,em,u') === null) { o = metinParca((d.textContent || '').replace(/\s+/g, ' '), f0 || {}); return o; }
    [].forEach.call(d.childNodes, function (c) { yuru(c, f0 || {}); });
    return o;
  };
  Belge.prototype.resim = function (src) {
    var r = this.harita[src];
    if (!r) return wr('[görsel]', { i: true, renk: '999999' });
    var id = this.id++, cx = Math.round(r.w * 9525), cy = Math.round(r.h * 9525);
    return '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="' + cx + '" cy="' + cy + '"/><wp:docPr id="' + id + '" name="Resim ' + id + '"/><wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr><a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic><pic:nvPicPr><pic:cNvPr id="' + id + '" name="Resim ' + id + '"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip r:embed="' + r.rid + '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill><pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' + cx + '" cy="' + cy + '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
  };
  /** Resimleri indir, boyutlarını bul (en fazla 14 cm x 9 cm) */
  Belge.prototype.resimleriHazirla = function (srcler) {
    var self = this, n = 0;
    return Promise.all(srcler.filter(function (s, i, a) { return s && a.indexOf(s) === i; }).map(function (src) {
      return fetch(src, { credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw 0; return r.blob(); }).then(function (b) {
        return new Promise(function (ok, no) {
          var u = URL.createObjectURL(b), im = new Image();
          im.onload = function () {
            var w = im.naturalWidth, h = im.naturalHeight, tur = (b.type || '').split('/')[1] || 'png';
            var mx = 529, my = 340, k = Math.min(1, mx / w, my / h); // px (96 dpi): 14 cm, 9 cm
            var bitir = function (veri, uz) { n++; var rid = 'rIdImg' + n; self.resimler.push({ ad: 'image' + n + '.' + uz, veri: veri, rid: rid }); self.harita[src] = { rid: rid, w: Math.max(8, Math.round(w * k)), h: Math.max(8, Math.round(h * k)) }; URL.revokeObjectURL(u); ok(); };
            if (/^(png|jpeg|jpg|gif)$/.test(tur)) b.arrayBuffer().then(function (ab) { bitir(ab, tur === 'jpeg' ? 'jpg' : tur); });
            else { var c = document.createElement('canvas'); c.width = w; c.height = h; c.getContext('2d').drawImage(im, 0, 0); c.toBlob(function (pb) { pb.arrayBuffer().then(function (ab) { bitir(ab, 'png'); }); }, 'image/png'); }
          };
          im.onerror = function () { URL.revokeObjectURL(u); no(); };
          im.src = u;
        });
      }).catch(function () {});
    }));
  };

  /* ---------------- belge düzeni ---------------- */
  var GEN = 10318; // içerik genişliği (twip): A4 - 2 x 1,4 cm
  var MAVI = '1E3A8A';
  function p(icerik, ppr) { return '<w:p>' + (ppr ? '<w:pPr>' + ppr + '</w:pPr>' : '') + icerik + '</w:p>'; }
  function kenarlik(v, sz, renk) { var t = ''; ['top', 'left', 'bottom', 'right', 'insideH', 'insideV'].forEach(function (k) { t += '<w:' + k + ' w:val="' + v + '" w:sz="' + (sz || 4) + '" w:space="0" w:color="' + (renk || '000000') + '"/>'; }); return '<w:tblBorders>' + t + '</w:tblBorders>'; }
  /** satirlar: [[hucre, ...]]; hucre: xml ya da {x: xml, span: 2, dolgu: 'RRGGBB'} */
  function tablo(sutunlar, satirlar, opt) {
    opt = opt || {}; var gen = sutunlar.reduce(function (a, b) { return a + b; }, 0);
    return '<w:tbl><w:tblPr><w:tblW w:w="' + gen + '" w:type="dxa"/><w:tblLayout w:type="fixed"/>' + kenarlik(opt.kenar || 'none', opt.sz, opt.renk) + '<w:tblCellMar><w:top w:w="' + (opt.ust || 40) + '" w:type="dxa"/><w:left w:w="' + (opt.sol === undefined ? 90 : opt.sol) + '" w:type="dxa"/><w:bottom w:w="' + (opt.alt || 40) + '" w:type="dxa"/><w:right w:w="90" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>' + sutunlar.map(function (w) { return '<w:gridCol w:w="' + w + '"/>'; }).join('') + '</w:tblGrid>' +
      satirlar.map(function (r, ri) {
        var col = 0;
        return '<w:tr><w:trPr><w:cantSplit/>' + (opt.baslik && ri === 0 ? '<w:tblHeader/>' : '') + '</w:trPr>' + r.map(function (h) {
          var o = typeof h === 'object' && h !== null ? h : { x: h }, sp = o.span || 1, w = 0; for (var k = 0; k < sp; k++) w += sutunlar[col + k] || 0; col += sp;
          var dolgu = o.dolgu || (opt.dolgu && ri === 0 ? opt.dolgu : '');
          return '<w:tc><w:tcPr><w:tcW w:w="' + w + '" w:type="dxa"/>' + (sp > 1 ? '<w:gridSpan w:val="' + sp + '"/>' : '') + (dolgu ? '<w:shd w:val="clear" w:color="auto" w:fill="' + dolgu + '"/>' : '') + '<w:vAlign w:val="' + (opt.dikey || 'top') + '"/></w:tcPr>' + (o.x || '<w:p/>') + '</w:tc>';
        }).join('') + '</w:tr>';
      }).join('') + '</w:tbl>';
  }
  function ortala(ic, ek) { return p(ic, '<w:jc w:val="center"/>' + (ek || '')); }
  /** HTML içeriği paragraflara ve (varsa) tablolara çevir */
  Belge.prototype.blok = function (html, f0, ppr, genislik) {
    var self = this, o = '', parca = String(html || '').split(/(<table[\s\S]*?<\/table>)/i);
    parca.forEach(function (h) {
      if (!h) return;
      if (/^<table/i.test(h)) {
        var d = new DOMParser().parseFromString(h, 'text/html'), sat = [].slice.call(d.querySelectorAll('tr'));
        var n = Math.max.apply(null, sat.map(function (r) { return r.children.length; }).concat([1])), w = Math.floor((genislik || GEN) / n), sut = []; for (var i = 0; i < n; i++) sut.push(w);
        o += tablo(sut, sat.map(function (r, ri) { var hs = [].slice.call(r.children); while (hs.length < n) hs.push(null); return hs.map(function (c) { return { x: p(c ? self.icerik(c.innerHTML, c.tagName === 'TH' ? { b: true, sz: 20 } : { sz: 20 }) : '', '<w:spacing w:after="0"/>'), dolgu: c && c.tagName === 'TH' ? 'EEF2F7' : '' }; }); }), { kenar: 'single', sz: 4, renk: '9CA3AF', ust: 30, alt: 30 });
        o += p('', '<w:spacing w:after="60"/>');
        return;
      }
      // tek başına satırda duran formüller ayrı, büyük (display) denklem paragrafı olsun
      var satirlar = h.split(/<br\s*\/?>/i), grup = [];
      var bosalt = function () { if (!grup.length) return; var ic = self.icerik(grup.join('<br>'), f0); grup = []; if (ic.replace(/<w:r><w:br\/><\/w:r>/g, '').trim() !== '') o += p(ic.replace(/^(<w:r><w:br\/><\/w:r>)+|(<w:r><w:br\/><\/w:r>)+$/g, ''), ppr); };
      satirlar.forEach(function (l) {
        var m1 = /^\s*\$([^$]+)\$\s*$/.exec(l.replace(/<[^>]+>/g, '') === l ? varlik(l) : '');
        if (m1) { bosalt(); o += p('<m:oMathPara><m:oMathParaPr><m:jc m:val="left"/></m:oMathParaPr>' + omml(m1[1]) + '</m:oMathPara>', ppr); }
        else grup.push(l);
      });
      bosalt();
    });
    return o;
  };

  /* ---------- genel evrak (tutanak, plan, rapor) ---------- */
  function evrakBelge(m) {
    var SP0 = '<w:spacing w:after="0"/>', GENI = m.yatay ? 15240 : GEN, g = '';
    var sat = function (t, f, ppr) { return p(metinParca(String(t == null ? '' : t), f || {}), ppr || SP0); };
    var cokSatir = function (t, f, ppr) { return String(t == null ? '' : t).split('\n').map(function (l) { return sat(l, f, ppr); }).join('') || p(''); };
    (m.bloklar || []).forEach(function (b) {
      switch (b.t) {
        case 'baslik': g += ortala(wr(b.s, { b: true, sz: b.sz || 24 }), '<w:spacing w:after="' + (b.after || 40) + '"/>'); break;
        case 'alt': g += ortala(wr(b.s, { sz: 21 }), '<w:spacing w:after="120"/>'); break;
        case 'h': g += p(wr(b.s, { b: true, sz: 22, renk: MAVI }), '<w:spacing w:before="200" w:after="80"/><w:keepNext/>'); break;
        case 'p': g += cokSatir(b.s, {}, '<w:spacing w:after="80"/><w:jc w:val="both"/>'); break;
        case 'liste': (b.l || []).forEach(function (x, i) { g += p(wr((b.numara === false ? '•' : (i + 1) + '.') + ' ', { b: true }) + metinParca(String(x), {}), '<w:spacing w:after="50"/><w:ind w:left="360" w:hanging="360"/><w:jc w:val="both"/>'); }); break;
        case 'bilgi': { var w1 = Math.round(GENI * 0.3); g += tablo([w1, GENI - w1], (b.satirlar || []).map(function (r) { return [{ x: sat(r[0], { b: true, sz: 21 }), dolgu: 'F1F4F9' }, sat(r[1], { sz: 21 })]; }), { kenar: 'single', sz: 4, renk: '9CA3AF', ust: 50, alt: 50 }); g += p('', '<w:spacing w:after="80"/>'); break; }
        case 'tablo': {
          var n = (b.bas || b.sat[0] || []).length, top = (b.gen || []).reduce(function (a, c) { return a + c; }, 0), gen = b.gen ? b.gen.map(function (x) { return Math.round(x / top * GENI); }) : (function () { var o = [], w = Math.floor(GENI / n); for (var i = 0; i < n; i++) o.push(w); return o; })();
          var rows = []; if (b.bas) rows.push(b.bas.map(function (h) { return { x: sat(h, { b: true, sz: 20 }, SP0), dolgu: 'E8EDF6' }; }));
          (b.sat || []).forEach(function (r) { rows.push(r.map(function (c) { return { x: cokSatir(c, { sz: 20 }) }; })); });
          g += tablo(gen, rows, { kenar: 'single', sz: 4, renk: '6B7280', ust: 50, alt: 50, baslik: !!b.bas }); g += p('', '<w:spacing w:after="80"/>'); break;
        }
        case 'imza': {
          var k = b.kisiler || [], sut = Math.min(b.sutun || 3, Math.max(1, k.length)), w = Math.floor(GENI / sut), sat2 = [];
          for (var i = 0; i < k.length; i += sut) { var r = []; for (var j = 0; j < sut; j++) { var x = k[i + j]; r.push(x ? ortala(wr(x.ad || '………………………', { b: true, sz: 21 }), SP0) + ortala(wr(x.gorev || '', { sz: 20 }), SP0) + ortala(wr('İmza', { sz: 18, renk: '888888' }), '<w:spacing w:before="160" w:after="200"/>') : p('')); } sat2.push(r); }
          if (b.baslik) g += p(wr(b.baslik, { b: true, sz: 22 }), '<w:spacing w:before="200" w:after="80"/><w:keepNext/>');
          var ws = []; for (var c = 0; c < sut; c++) ws.push(w);
          g += tablo(ws, sat2, {}); break;
        }
        case 'onay': g += p('', '<w:spacing w:after="120"/>') + ortala(wr('UYGUNDUR', { b: true, sz: 22 }), SP0) + ortala(wr(b.tarih || '…/…/20…', { sz: 21 }), SP0) + p('', '<w:spacing w:after="360"/>') + ortala(wr(b.ad || '………………………', { b: true, sz: 21 }), SP0) + ortala(wr(b.gorev || 'Okul Müdürü', { sz: 21 }), SP0); break;
        case 'sayfa': g += p('<w:r><w:br w:type="page"/></w:r>'); break;
        default: g += p('');
      }
    });
    return g;
  }
  window.OSC_DOCX_EVRAK = function (m) {
    var g = evrakBelge(m), y = !!m.yatay;
    var sect = '<w:sectPr><w:pgSz w:w="' + (y ? 16838 : 11906) + '" w:h="' + (y ? 11906 : 16838) + '"' + (y ? ' w:orient="landscape"' : '') + '/><w:pgMar w:top="794" w:right="794" w:bottom="794" w:left="794" w:header="400" w:footer="400" w:gutter="0"/></w:sectPr>';
    var doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document ' + NS + '><w:body>' + g + sect + '</w:body></w:document>';
    var stil = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:eastAsia="Calibri" w:cs="Calibri"/><w:sz w:val="22"/><w:szCs w:val="22"/><w:lang w:val="tr-TR"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="0" w:line="264" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style><w:style w:type="table" w:default="1" w:styleId="TableNormal"><w:name w:val="Normal Table"/><w:tblPr><w:tblInd w:w="0" w:type="dxa"/><w:tblCellMar><w:top w:w="0" w:type="dxa"/><w:left w:w="108" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/><w:right w:w="108" w:type="dxa"/></w:tblCellMar></w:tblPr></w:style></w:styles>';
    var z = new window.JSZip();
    z.file('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>');
    z.file('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');
    z.file('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>' + x(m.baslik || 'Evrak') + '</dc:title><dc:creator>' + x(m.site || 'soruindir.net') + '</dc:creator></cp:coreProperties>');
    z.file('word/document.xml', doc);
    z.file('word/styles.xml', stil);
    z.file('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdStil" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    return z.generateAsync({ type: 'blob', mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', compression: 'DEFLATE' });
  };
  window.OSC_DOCX = {
    omml: omml,
    /**
     * @param {object} m { yil, okul, baslik, ogretmen, ders, tarih, sure, site, marka, yonerge, anahtar,
     *                    sorular:[{paragraph,root,type,options,correct,cozum,puan,satir,bosluk,kod,konu}] }
     * @return Promise<Blob>
     */
    olustur: function (m) {
      var B = new Belge(), srcler = [];
      m.sorular.forEach(function (q) { var h = (q.paragraph || '') + (q.root || '') + Object.keys(q.options || {}).map(function (k) { return q.options[k]; }).join(''); h.replace(/<img[^>]+src="([^"]+)"/g, function (_, s) { srcler.push(varlik(s)); return _; }); });
      return B.resimleriHazirla(srcler).then(function () {
        var g = '', SP0 = '<w:spacing w:after="0"/>';
        // başlık kutusu
        g += tablo([GEN], [[ortala(wr('T.C.', { b: true, sz: 22 }), SP0) + ortala(wr((m.okul || '……………………… OKULU').toLocaleUpperCase('tr'), { b: true, sz: 24 }), SP0) + ortala(wr(m.baslik, { b: true, sz: 21 }), SP0)]], { kenar: 'single', sz: 12, ust: 60, alt: 60 });
        g += p('', '<w:spacing w:after="60"/>');
        var ks = [3500, 2350, 1950, 1550, GEN - 3500 - 2350 - 1950 - 1550], bh = function (t) { return p(wr(t, { sz: 21 }), SP0); };
        g += tablo(ks, [[bh('Adı Soyadı:'), bh('Sınıfı / No:'), bh('Tarih: ' + (m.tarih || '')), bh('Süre: ' + (m.sure || '') + ' dk'), ortala(wr('PUAN', { b: true, sz: 21 }), SP0) + p('')]], { kenar: 'single', sz: 12, ust: 70, alt: 200 });
        g += p('', '<w:spacing w:after="120"/>');
        // sorular: [no | içerik | puan]
        var SN = 480, SPN = 900, SM = GEN - SN - SPN;
        m.sorular.forEach(function (q, i) {
          var hucre = '', op = q.options || {};
          if (q.paragraph) hucre += B.blok(q.paragraph, {}, '<w:pBdr><w:top w:val="single" w:sz="4" w:space="4" w:color="999999"/><w:left w:val="single" w:sz="4" w:space="4" w:color="999999"/><w:bottom w:val="single" w:sz="4" w:space="4" w:color="999999"/><w:right w:val="single" w:sz="4" w:space="4" w:color="999999"/></w:pBdr><w:spacing w:after="100"/><w:ind w:left="80" w:right="80"/>', SM);
          hucre += B.blok(q.root || '', { b: true }, '<w:spacing w:after="80"/>', SM);
          if (q.type === 'multiple' || q.type === 'truefalse') {
            var ks2 = Object.keys(op).filter(function (k) { return op[k]; });
            var kisa = ks2.every(function (k) { return String(op[k]).replace(/<[^>]+>/g, '').replace(/\$[^$]*\$/g, 'xxxx').length < 24 && op[k].indexOf('<img') < 0; });
            if (kisa) { var tab = Math.floor(SM / Math.max(4, ks2.length)); hucre += p(ks2.map(function (k, j) { return (j ? '<w:r><w:tab/></w:r>' : '') + wr(k + ') ', { b: true }) + B.icerik(op[k]); }).join(''), '<w:tabs>' + ks2.map(function (k, j) { return j ? '<w:tab w:val="left" w:pos="' + (tab * j) + '"/>' : ''; }).join('') + '</w:tabs><w:spacing w:after="60"/>'); }
            else ks2.forEach(function (k) { hucre += p(wr(k + ') ', { b: true }) + B.icerik(op[k]), '<w:spacing w:after="40"/><w:ind w:left="284" w:hanging="284"/>'); });
          }
          for (var j = 0; j < (q.satir || 0); j++) hucre += p('', '<w:pBdr><w:bottom w:val="dotted" w:sz="6" w:space="1" w:color="' + (j % 2 ? '8A8A8A' : '8B8B8B') + '"/></w:pBdr><w:spacing w:before="0" w:after="0" w:line="420" w:lineRule="exact"/>');
          for (var k = 0; k < Math.round((q.bosluk || 0) / 20); k++) hucre += p('', '<w:spacing w:before="0" w:after="0" w:line="300" w:lineRule="exact"/>');
          if (!hucre) hucre = p('');
          g += tablo([SN, SM, SPN], [[p(wr((i + 1) + '.', { b: true })), hucre, p(wr('(' + (q.puan || 0) + ' P)', { b: true, sz: 20 }), '<w:jc w:val="right"/>')]], { sol: 0 });
          g += p('', '<w:spacing w:after="80"/>');
        });
        g += p(wr('Başarılar dilerim.', { sz: 21 }), '<w:jc w:val="right"/><w:spacing w:before="240" w:after="0"/>');
        if (m.ogretmen) g += p(wr(m.ogretmen, { b: true, sz: 21 }), '<w:jc w:val="right"/><w:spacing w:after="0"/>');
        g += p(wr((m.ders || '') + ' Öğretmeni', { sz: 21 }), '<w:jc w:val="right"/><w:spacing w:after="200"/>');
        g += ortala(wr(m.site || 'soruindir.net', { sz: 16, renk: '888888' }));
        // cevap anahtarı tablosu
        if (m.anahtar) {
          g += p('<w:r><w:br w:type="page"/></w:r>');
          g += ortala(wr('CEVAP ANAHTARI', { b: true, sz: 28 }), '<w:spacing w:after="40"/>');
          g += ortala(wr(m.baslik, { sz: 19 }), '<w:spacing w:after="160"/>');
          var hs = [650, 2400, GEN - 650 - 2400 - 850, 850], hb = function (t) { return p(wr(t, { b: true, sz: 21 }), SP0); }, CW = hs[2];
          var sat = [[hb('No'), hb('Öğrenme çıktısı'), hb('Cevap'), hb('Puan')]];
          m.sorular.forEach(function (q, i) {
            var op = q.options || {}, cv;
            if (q.type === 'multiple' || q.type === 'truefalse') cv = p(wr((q.correct || '?') + (q.correct && op[q.correct] ? ') ' : ''), { b: true }) + (q.correct && op[q.correct] ? B.icerik(op[q.correct]) : ''), SP0) + (q.cozum ? B.blok(q.cozum, { sz: 20 }, SP0, CW) : '');
            else cv = op.A ? B.blok(op.A, {}, SP0, CW) : p(wr('—'), SP0);
            sat.push([ortala(wr(String(i + 1), { sz: 21 }), SP0), p(wr(q.kod || '', { sz: 18 }) + (q.konu ? '<w:r><w:br/></w:r>' + wr(q.konu, { sz: 16, renk: '555555' }) : ''), SP0), cv || p(''), ortala(wr(String(q.puan || 0), { sz: 21 }), SP0)]);
          });
          g += tablo(hs, sat, { kenar: 'single', sz: 6, ust: 60, alt: 60, baslik: true, dolgu: 'EEF0F6' });
        }
        var footer = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:ftr ' + NS + '>' + p(wr((m.site || 'soruindir.net') + '   ·   Sayfa ', { sz: 16, renk: '9CA3AF' }) + '<w:r><w:rPr><w:sz w:val="16"/><w:color w:val="9CA3AF"/></w:rPr><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:rPr><w:sz w:val="16"/><w:color w:val="9CA3AF"/></w:rPr><w:instrText xml:space="preserve"> PAGE </w:instrText></w:r><w:r><w:rPr><w:sz w:val="16"/><w:color w:val="9CA3AF"/></w:rPr><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:rPr><w:sz w:val="16"/><w:color w:val="9CA3AF"/></w:rPr><w:t>1</w:t></w:r><w:r><w:rPr><w:sz w:val="16"/><w:color w:val="9CA3AF"/></w:rPr><w:fldChar w:fldCharType="end"/></w:r>' + wr(' / ', { sz: 16, renk: '9CA3AF' }) + '<w:r><w:rPr><w:sz w:val="16"/><w:color w:val="9CA3AF"/></w:rPr><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:rPr><w:sz w:val="16"/><w:color w:val="9CA3AF"/></w:rPr><w:instrText xml:space="preserve"> NUMPAGES </w:instrText></w:r><w:r><w:rPr><w:sz w:val="16"/><w:color w:val="9CA3AF"/></w:rPr><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:rPr><w:sz w:val="16"/><w:color w:val="9CA3AF"/></w:rPr><w:t>1</w:t></w:r><w:r><w:rPr><w:sz w:val="16"/><w:color w:val="9CA3AF"/></w:rPr><w:fldChar w:fldCharType="end"/></w:r>', '<w:jc w:val="right"/>') + '</w:ftr>';
        var sect = '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="737" w:right="794" w:bottom="737" w:left="794" w:header="400" w:footer="400" w:gutter="0"/></w:sectPr>';
        var doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document ' + NS + '><w:body>' + g + sect + '</w:body></w:document>';
        var stil = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:eastAsia="Calibri" w:cs="Calibri"/><w:sz w:val="23"/><w:szCs w:val="23"/><w:lang w:val="tr-TR"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="0" w:line="264" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style><w:style w:type="table" w:default="1" w:styleId="TableNormal"><w:name w:val="Normal Table"/><w:tblPr><w:tblInd w:w="0" w:type="dxa"/><w:tblCellMar><w:top w:w="0" w:type="dxa"/><w:left w:w="108" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/><w:right w:w="108" w:type="dxa"/></w:tblCellMar></w:tblPr></w:style></w:styles>';
        var ayar = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"><m:mathPr><m:mathFont m:val="Cambria Math"/><m:dispDef/></m:mathPr><w:defaultTabStop w:val="708"/><w:compat><w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/></w:compat></w:settings>';
        var z = new window.JSZip();
        z.file('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Default Extension="jpg" ContentType="image/jpeg"/><Default Extension="gif" ContentType="image/gif"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>');
        z.file('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');
        z.file('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>' + x(m.baslik) + '</dc:title><dc:creator>' + x(m.site || 'soruindir.net') + '</dc:creator></cp:coreProperties>');
        z.file('word/document.xml', doc);
        z.file('word/styles.xml', stil);
        z.file('word/settings.xml', ayar);
        z.file('word/footer1.xml', footer);
        z.file('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdStil" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rIdAyar" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/><Relationship Id="rIdAlt" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>' + B.resimler.map(function (r) { return '<Relationship Id="' + r.rid + '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/' + r.ad + '"/>'; }).join('') + '</Relationships>');
        B.resimler.forEach(function (r) { z.file('word/media/' + r.ad, r.veri); });
        return z.generateAsync({ type: 'blob', mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', compression: 'DEFLATE' });
      });
    }
  };
})();
