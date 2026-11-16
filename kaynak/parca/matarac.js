/* Ortak matematik / biçim araçları: yönetim paneli ve öğretmen paneli soru düzenleyicileri */
window.OSCMat = window.OSCMat || (function () {
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function secim() { return String(window.getSelection() || ''); }
  function ekle(ce, html) { ce.focus(); document.execCommand('insertHTML', false, html); ce.dispatchEvent(new Event('input', { bubbles: true })); }
  var ROMA = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
  function etiket(stil, n) { return stil === 'r' ? (ROMA[n - 1] || n) : stil === 'a' ? String.fromCharCode(96 + n) : stil === 'A' ? String.fromCharCode(64 + n) : String(n); }
  /* altı çizili + altında numara (I, II, III …) */
  function numarali(ce, stil, uyar) {
    var s = secim().trim();
    if (!s) { if (uyar) uyar('Önce numaralanacak sözcüğü seçin.'); return; }
    var n = ce.querySelectorAll('.osc-num').length + 1;
    ekle(ce, '<span class="osc-num"><u>' + esc(s) + '</u><span class="osc-num-e">' + etiket(stil, n) + '</span></span>\u200b');
  }
  var YAPI = [
    ['a⁄b', 'Kesir (3/4 yazıp seçebilirsiniz)', function (s) { var m = /^\s*(.+?)\s*[\/÷]\s*(.+?)\s*$/.exec(s); return '<span class="osc-kesir"><span class="p">' + esc(m ? m[1] : (s || '1')) + '</span><span class="d">' + esc(m ? m[2] : (s ? '?' : '2')) + '</span></span>&nbsp;'; }],
    ['a b⁄c', 'Tam sayılı kesir', function (s) { return '1<span class="osc-kesir"><span class="p">2</span><span class="d">3</span></span>&nbsp;'; }],
    ['√x', 'Karekök', function (s) { return '<span class="osc-kok">√<span class="osc-kok-ic">' + esc(s || 'x') + '</span></span>&nbsp;'; }],
    ['∛x', 'Küpkök', function (s) { return '<span class="osc-kok"><span class="osc-kok-d">3</span>√<span class="osc-kok-ic">' + esc(s || 'x') + '</span></span>&nbsp;'; }],
    ['ⁿ√x', 'n. dereceden kök', function (s) { return '<span class="osc-kok"><span class="osc-kok-d">n</span>√<span class="osc-kok-ic">' + esc(s || 'x') + '</span></span>&nbsp;'; }],
    ['xⁿ', 'Üs', function (s) { return esc(s || 'x') + '<sup>2</sup>&nbsp;'; }],
    ['xₙ', 'Alt indis', function (s) { return esc(s || 'x') + '<sub>1</sub>&nbsp;'; }],
    ['0,3̄', 'Devirli ondalık / üst çizgi', function (s) { return '<span class="osc-ust">' + esc(s || '3') + '</span>&nbsp;'; }],
    ['[AB]', 'Doğru parçası', function (s) { return '<span class="osc-ust">' + esc(s || 'AB') + '</span>&nbsp;'; }],
    ['AB→', 'Işın', function (s) { return '<span class="osc-isin">' + esc(s || 'AB') + '</span>&nbsp;'; }],
    ['AB↔', 'Doğru', function (s) { return '<span class="osc-dogru">' + esc(s || 'AB') + '</span>&nbsp;'; }],
    ['|x|', 'Mutlak değer', function (s) { return '|' + esc(s || 'x') + '|&nbsp;'; }],
    ['(a⁄b)ⁿ', 'Parantezli kesrin kuvveti', function (s) { return '(<span class="osc-kesir"><span class="p">a</span><span class="d">b</span></span>)<sup>2</sup>&nbsp;'; }],
    ['x°', 'Derece', function (s) { return esc(s || 'x') + '°'; }],
    ['m(∠A)', 'Açı ölçüsü', function () { return 'm(∠A)&nbsp;'; }],
    ['log', 'Logaritma', function (s) { return 'log<sub>2</sub>' + esc(s || 'x') + '&nbsp;'; }],
    ['∑', 'Toplam sembolü', function () { return '∑<sub>k=1</sub><sup>n</sup>&nbsp;'; }],
    ['lim', 'Limit', function () { return 'lim<sub>x→0</sub>&nbsp;'; }],
    ['∫', 'İntegral', function () { return '∫<sub>a</sub><sup>b</sup>&nbsp;'; }]
  ];
  var SEMB = [
    ['İşlem', '+ − × ÷ · ± ∓ = ≠ ≈ ≅ ≡ < > ≤ ≥ ≪ ≫ % ‰ ∝ ∞'],
    ['Küme / mantık', '∈ ∉ ∋ ⊂ ⊃ ⊆ ⊇ ⊄ ∪ ∩ ∖ ∅ ℕ ℤ ℚ ℝ ℂ ℤ⁺ ℤ⁻ ℝ⁺ ℝ⁻ ∀ ∃ ⇒ ⇔ → ← ↔ ∧ ∨ ¬ ⊻ ∴'],
    ['Geometri', '∠ ∡ △ □ ○ ⊥ ∥ ∦ ∼ ≅ ° ′ ″ π ⌒'],
    ['Kuvvet / kesir', '² ³ ⁴ ⁵ ⁿ ⁻¹ ₀ ₁ ₂ ₃ ½ ⅓ ⅔ ¼ ¾ ⅕ ⅛ √ ∛ ∜'],
    ['Yunan', 'α β γ δ ε θ λ μ π ρ σ τ φ ω Δ Σ Π Ω'],
    ['Diğer', '∑ ∏ ∫ ∂ ∇ ⌊ ⌋ ⌈ ⌉ … ⋯ ⁄ ✓ ✗']
  ];
  function kapat() { var m = document.getElementById('osc-mat-m'); if (m) m.remove(); }
  function menu(btn, ce, tur, uyar) {
    kapat(); var sel = secim();
    var m = document.createElement('div'); m.id = 'osc-mat-m'; m.className = 'osc-mat-m';
    if (tur === 'num') {
      m.innerHTML = '<div class="b">Altı çizili + numara</div><div class="ny">' + [['r', 'I  II  III'], ['1', '1  2  3'], ['a', 'a  b  c'], ['A', 'A  B  C']].map(function (x) { return '<button type="button" data-n="' + x[0] + '">' + x[1] + '</button>'; }).join('') + '</div><div class="i">Sözcüğü seçin, sonra numara biçimine tıklayın. Numara sırayla artar; altındaki numaraya tıklayıp değiştirebilirsiniz.</div>';
    } else {
      m.innerHTML = '<div class="b">Matematik yapıları</div><div class="yp">' + YAPI.map(function (y, i) { return '<button type="button" data-y="' + i + '" title="' + esc(y[1]) + '">' + y[0] + '</button>'; }).join('') + '</div>' +
        SEMB.map(function (g) { return '<div class="b">' + g[0] + '</div><div class="sm">' + g[1].split(' ').map(function (x) { return '<button type="button" data-s="' + esc(x) + '">' + x + '</button>'; }).join('') + '</div>'; }).join('');
    }
    var r = btn.getBoundingClientRect(); m.style.left = Math.max(8, Math.min(r.left, window.innerWidth - 400)) + window.scrollX + 'px'; m.style.top = (r.bottom + window.scrollY + 4) + 'px';
    m.addEventListener('mousedown', function (e) { e.preventDefault(); });
    m.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return;
      if (b.dataset.n) { numarali(ce, b.dataset.n, uyar); kapat(); return; }
      if (b.dataset.y) { ekle(ce, YAPI[+b.dataset.y][2](sel.trim())); kapat(); return; }
      if (b.dataset.s) { ce.focus(); document.execCommand('insertText', false, b.dataset.s); ce.dispatchEvent(new Event('input', { bubbles: true })); }
    });
    document.body.appendChild(m);
    setTimeout(function () { document.addEventListener('mousedown', function k(e) { if (!m.contains(e.target)) { kapat(); document.removeEventListener('mousedown', k); } }); }, 0);
  }
  /* sütunlu şıklar: "I. / II." başlıklı tablo biçimi */
  function sutunBas(n, adlar) { return '<div class="osc-sut-bas osc-n' + n + '">' + adlar.slice(0, n).map(function (a) { return '<span>' + esc(a) + '</span>'; }).join('') + '</div>'; }
  function sutunHucre(h, n) { var d = document.createElement('div'); d.innerHTML = h || ''; var s = d.querySelector('.osc-sut'); var o = []; if (s) { for (var i = 0; i < s.children.length; i++) o.push(s.children[i].innerHTML); } else if (h) o.push(h); while (o.length < n) o.push(''); return o.slice(0, n); }
  function sutunHtml(hucreler) { return hucreler.some(function (x) { return String(x).replace(/<[^>]*>|&nbsp;|\s/g, ''); }) ? '<span class="osc-sut osc-n' + hucreler.length + '">' + hucreler.map(function (x) { return '<span>' + x + '</span>'; }).join('') + '</span>' : ''; }
  function kokAyir(root) { var d = document.createElement('div'); d.innerHTML = root || ''; var b = d.querySelector('.osc-sut-bas'); var adlar = null; if (b) { adlar = Array.prototype.map.call(b.children, function (x) { return x.textContent; }); b.remove(); } return { kok: d.innerHTML.replace(/(<br>\s*)+$/, ''), adlar: adlar }; }
  return { menu: menu, numarali: numarali, kapat: kapat, sutunBas: sutunBas, sutunHucre: sutunHucre, sutunHtml: sutunHtml, kokAyir: kokAyir };
})();
