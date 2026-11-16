
/* =====================================================================
 * Yönetim panelinde test ekleme ekranı: geniş, tek sütunlu düzen;
 * alanlarda biçim araç çubuğu (altı çizili, kalın, italik, üs, alt simge)
 * ===================================================================== */
if (!class_exists('OSC_Admin_Test')) {
class OSC_Admin_Test {
    public function __construct() {
        // blok düzenleyici test oluşturucuyu ekranın altına sıkıştırıyor: klasik düzenleyici
        add_filter('use_block_editor_for_post_type', function ($k, $pt) { return $pt === 'osc_quiz' ? false : $k; }, 50, 2);
        // tek sütun: test oluşturucu tam genişlikte, yan kutular altta
        add_filter('get_user_option_screen_layout_osc_quiz', function () { return 1; });
        add_action('admin_footer-post.php', array($this, 'arac'));
        add_action('admin_footer-post-new.php', array($this, 'arac'));
    }
    public function arac() {
        $ekran = get_current_screen(); if (!$ekran || $ekran->post_type !== 'osc_quiz') return; ?>
<style>
#poststuff #post-body.columns-1 #postbox-container-1{margin-top:10px}
#osc_builder .inside{padding:0 14px 14px}
.osc-q-item{border-radius:10px;box-shadow:0 1px 0 rgba(0,0,0,.03)}
.osc-q-item .osc-txt,.osc-q-item .osc-inp{font-size:14px}
.osc-q-item .osc-row .osc-inp{flex:1}
#osc-bicim{position:fixed;z-index:100000;display:none;gap:2px;background:#1e1b4b;padding:4px;border-radius:10px;box-shadow:0 10px 24px -10px rgba(15,23,42,.6)}
#osc-bicim button{border:0;background:transparent;color:#fff;min-width:30px;height:28px;border-radius:7px;cursor:pointer;font:600 14px/1 system-ui,sans-serif;padding:0 7px}
#osc-bicim button:hover{background:rgba(255,255,255,.16)}
#osc-bicim .ip{font:500 11px system-ui,sans-serif;color:#c7d2fe;padding:0 6px;align-self:center}
.osc-row+.osc-onz{margin-left:58px}.osc-onz{margin:4px 0 8px;padding:6px 10px;background:#f8fafc;border:1px dashed #94a3b8;border-radius:6px;font-size:14px;line-height:1.5}
.osc-onz:before{content:"Önizleme: ";color:#64748b;font-size:12px}
.osc-onz u{text-decoration-thickness:2px;text-underline-offset:2px}
#osc-kaydet-bar{position:sticky;bottom:0;z-index:50;display:flex;align-items:center;gap:10px;justify-content:flex-end;background:#fff;border-top:1px solid #dcdcde;padding:10px 14px;margin:14px -14px -14px;box-shadow:0 -8px 16px -12px rgba(0,0,0,.25)}
#osc-kaydet-bar span{margin-right:auto;color:#50575e;font-size:12.5px}
/* öğretmen paneli tarzı soru düzenleyici */
#osc-q-list .oq{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:14px 16px;margin-bottom:14px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
#osc-q-list .oq.hata{border-color:#d63638;box-shadow:0 0 0 2px #fcdada}
.oq-bas{display:flex;align-items:center;gap:8px;margin-bottom:10px}
.oq-bas .no{background:#4f46e5;color:#fff;border-radius:8px;min-width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;font-weight:700}
.oq-bas .bos{flex:1}.oq-bas button{border:1px solid #dcdcde;background:#f6f7f7;border-radius:7px;min-width:30px;height:30px;cursor:pointer}.oq-bas button.sil{color:#b32d2e}
.oq-alan{margin-bottom:10px}.oq-alan>label,.oq-sik-bas{display:block;font-weight:600;font-size:12.5px;color:#50575e;margin-bottom:4px}
.oq-alan.kapali{display:none}
.oq-arac{display:flex;flex-wrap:wrap;gap:2px;margin-bottom:3px}.oq-arac button{border:1px solid #e2e4e7;background:#fff;border-radius:6px;min-width:28px;height:26px;padding:0 6px;cursor:pointer;font-size:13px}.oq-arac button:hover{background:#eef2ff;border-color:#c7d2fe}
.oq-ce{min-height:38px;border:1px solid #c3c4c7;border-radius:8px;padding:8px 10px;font-size:15px;line-height:1.55;background:#fff;outline:0;overflow-wrap:anywhere}
.oq-ce:focus{border-color:#4f46e5;box-shadow:0 0 0 2px #e0e7ff}
.oq-ce.kok{min-height:56px;font-weight:600}.oq-ce:empty:before{content:attr(data-ph);color:#a7aaad;font-weight:400}
.oq-ce img{max-width:100%;max-height:220px;vertical-align:middle;border-radius:4px}
.oq-ce table{border-collapse:collapse}.oq-ce td,.oq-ce th{border:1px solid #94a3b8;padding:2px 6px}
.oq-sik{display:flex;align-items:flex-start;gap:8px;margin-bottom:6px}
.oq-sik .harf{flex:0 0 34px;height:34px;border-radius:50%;border:2px solid #c3c4c7;background:#fff;font-weight:700;cursor:pointer;margin-top:28px}
.oq-sik.dogru .harf{background:#16a34a;border-color:#16a34a;color:#fff}
.oq-sik.dogru .oq-ce{border-color:#16a34a;background:#f0fdf4}
.oq-sik .ic{flex:1;min-width:0}
.oq-dy button{border:2px solid #c3c4c7;background:#fff;border-radius:8px;padding:6px 14px;margin-right:6px;cursor:pointer;font-weight:600}.oq-dy button.on{border-color:#16a34a;background:#16a34a;color:#fff}
.oq-link{border:0;background:none;color:#2271b1;cursor:pointer;padding:0;margin:0 0 10px;font-size:13px}
#oq-ust{display:flex;flex-wrap:wrap;gap:8px;align-items:center;background:#f6f7ff;border:1px solid #e0e7ff;border-radius:12px;padding:10px 14px;margin:0 0 14px}
#oq-ust input{min-width:220px}#oq-ust .ozet{margin-left:auto;color:#50575e}#oq-ust .ozet .uy{color:#b32d2e;font-weight:600}
.osc-kesir{display:inline-flex;flex-direction:column;align-items:center;vertical-align:middle;margin:0 3px;line-height:1.15;font-size:.95em}
.osc-kesir>span{padding:0 3px;min-width:.8em;text-align:center}.osc-kesir>span:first-child{border-bottom:1.6px solid currentColor}
</style>
<div id="osc-bicim" role="toolbar" aria-label="Biçim"><button type="button" data-e="u" title="Altı çizili (Ctrl+U)"><u>A</u></button><button type="button" data-e="b" title="Kalın (Ctrl+B)"><b>K</b></button><button type="button" data-e="i" title="İtalik (Ctrl+I)"><i>İ</i></button><button type="button" data-e="sup" title="Üs">x²</button><button type="button" data-e="sub" title="Alt simge">x₂</button><button type="button" data-e="temizle" title="Seçimdeki biçimi kaldır">⌫</button><span class="ip">Sözcüğü seçip tıklayın</span></div>
<script>
(function () {
  var bar = document.getElementById('osc-bicim'), alan = null;
  function uygun(e) { return e && (e.matches('#osc-q-list textarea, #osc-q-list input[type=text]')); }
  function konum(e) { var r = e.getBoundingClientRect(); bar.style.display = 'flex'; var h = bar.offsetHeight || 36; bar.style.top = Math.max(34, r.top - h - 6) + 'px'; bar.style.left = r.left + 'px'; }
  function onizle(e) {
    var hedef = e.closest('.osc-row') || e, o = hedef.nextElementSibling && hedef.nextElementSibling.classList && hedef.nextElementSibling.classList.contains('osc-onz') ? hedef.nextElementSibling : null;
    if (!/<(u|b|i|strong|em|sup|sub)>/i.test(e.value)) { if (o) o.remove(); return; }
    if (!o) { o = document.createElement('div'); o.className = 'osc-onz'; hedef.insertAdjacentElement('afterend', o); }
    o.innerHTML = e.value.replace(/<(?!\/?(u|b|i|strong|em|sup|sub|br)\b)[^>]*>/gi, '');
  }
  function sar(e, etk) {
    var a = e.selectionStart, b = e.selectionEnd, v = e.value;
    if (a === b) { // seçim yoksa imlecin bulunduğu sözcük
      var m1 = /[^\s.,;:!?()"'“”]*$/.exec(v.slice(0, a))[0].length, m2 = /^[^\s.,;:!?()"'“”]*/.exec(v.slice(a))[0].length; a -= m1; b += m2;
      if (a === b) return;
    }
    var sec = v.slice(a, b), yeni;
    if (etk === 'temizle') yeni = sec.replace(/<\/?(u|b|i|strong|em|sup|sub)>/gi, '');
    else {
      var ac = '<' + etk + '>', kp = '</' + etk + '>';
      if (v.slice(a - ac.length, a) === ac && v.slice(b, b + kp.length) === kp) { e.value = v.slice(0, a - ac.length) + sec + v.slice(b + kp.length); e.setSelectionRange(a - ac.length, b - ac.length); e.dispatchEvent(new Event('input', { bubbles: true })); onizle(e); return; }
      yeni = ac + sec + kp;
    }
    e.value = v.slice(0, a) + yeni + v.slice(b);
    e.setSelectionRange(a, a + yeni.length);
    e.dispatchEvent(new Event('input', { bubbles: true })); onizle(e);
  }
  document.addEventListener('focusin', function (ev) { if (uygun(ev.target)) { alan = ev.target; konum(alan); onizle(alan); } });
  document.addEventListener('focusout', function (ev) { setTimeout(function () { if (!bar.contains(document.activeElement) && !uygun(document.activeElement)) bar.style.display = 'none'; }, 150); });
  bar.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
  bar.addEventListener('click', function (ev) { var b = ev.target.closest('button'); if (b && alan) { sar(alan, b.dataset.e); alan.focus(); } });
  document.addEventListener('keydown', function (ev) {
    if (!(ev.ctrlKey || ev.metaKey) || !uygun(ev.target)) return;
    var k = ev.key.toLowerCase(), m = { u: 'u', b: 'b', i: 'i' }[k]; if (!m) return;
    ev.preventDefault(); sar(ev.target, m);
  });
  window.addEventListener('scroll', function () { if (alan && bar.style.display === 'flex') konum(alan); }, { passive: true });
  // liste yeniden çizilince önizlemeleri tazele
  var l = document.getElementById('osc-q-list');
  if (l) new MutationObserver(function () { l.querySelectorAll('textarea, input[type=text]').forEach(function (e) { if (/<(u|b|i|strong|em|sup|sub)>/i.test(e.value)) onizle(e); }); }).observe(l, { childList: true });
  // oluşturucunun altında yapışkan kaydet çubuğu
  var kutu = document.querySelector('#osc_builder .inside'), yay = document.getElementById('publish');
  if (kutu && yay) { var d = document.createElement('div'); d.id = 'osc-kaydet-bar'; d.innerHTML = '<span>İpucu: Bir sözcüğü seçip <b>Ctrl+U</b> ile altını çizin; <b>Ctrl+B</b> kalın, <b>Ctrl+I</b> italik.</span><button type="button" class="button button-primary button-large">' + (yay.value || 'Kaydet') + '</button>'; d.querySelector('button').onclick = function () { yay.click(); }; kutu.appendChild(d); }
})();
</script>
<input type="file" id="oq-dosya" accept="image/*" hidden>
<script>
/* Test oluşturucu: öğretmen panelindeki gibi görsel düzenleyici (şıklarda altı çizili, kesir, görsel) */
(function () {
  var Q = null, liste = document.getElementById('osc-q-list'); if (!liste) return;
  function esc(v) { return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function goster(h) { h = String(h || ''); return /<(br|div|p|table)\b/i.test(h) ? h : h.replace(/\r?\n/g, '<br>'); }
  function temiz(h) { return String(h || '').replace(/<(\/?)(div|p)[^>]*>/gi, function (m, s) { return s ? '' : '<br>'; }).replace(/^(<br>)+/, '').replace(/(<br>\s*)+$/, '').replace(/ style="[^"]*"/gi, function (m) { return /width/.test(m) ? m : ''; }).replace(/<span>([^<]*)<\/span>/gi, '$1').replace(/&nbsp;/g, ' ').replace(/<font[^>]*>|<\/font>/gi, ''); }
  function bos(h) { return !String(h || '').replace(/<br\s*\/?>/gi, '').replace(/&nbsp;/g, ' ').replace(/<(?!img)[^>]+>/gi, '').trim(); }
  function sync() { document.getElementById('osc_data').value = JSON.stringify(Q); ozet(); }
  function dogrula(q) {
    if (bos(q.root) && bos(q.paragraph)) return false;
    if (q.type === 'multiple') { var d = ['A', 'B', 'C', 'D', 'E'].filter(function (k) { return !bos(q.options[k]); }); return d.length >= 2 && q.correct && !bos(q.options[q.correct]); }
    if (q.type === 'truefalse') return q.correct === 'A' || q.correct === 'B';
    if (q.type === 'gap') return !bos(q.options.A);
    return true;
  }
  function ozet() { var e = document.querySelector('#oq-ust .ozet'); if (!e || !Q) return; var x = Q.filter(function (q) { return !dogrula(q); }).length; e.innerHTML = '<b>' + Q.length + '</b> soru' + (x ? ' · <span class="uy">' + x + ' soruda eksik var</span>' : ' · hazır ✓'); }
  function arac() { return '<div class="oq-arac"><button type="button" data-a="u" title="Altı çizili (Ctrl+U)"><u>A</u></button><button type="button" data-a="b" title="Kalın"><b>K</b></button><button type="button" data-a="i" title="İtalik"><i>İ</i></button><button type="button" data-a="sup" title="Üs">x²</button><button type="button" data-a="sub" title="Alt simge">x₂</button><button type="button" data-a="kesir" title="Kesir (3/4 yazıp seçin ya da boş kesir ekleyin)">a⁄b</button><button type="button" data-a="sembol" title="Matematik sembolü">∈ ℕ</button><button type="button" data-a="img" title="Görsel ekle">🖼</button><button type="button" data-a="temizle" title="Biçimi kaldır">⌫</button></div>'; }
  function ce(f, v, ph, cls, o) { return '<div class="oq-ce' + (cls ? ' ' + cls : '') + '" contenteditable="true" ' + (o ? 'data-o="' + o + '"' : 'data-f="' + f + '"') + ' data-ph="' + esc(ph) + '">' + goster(v) + '</div>'; }
  function soru(q, i) {
    q.options = q.options || {}; ['A', 'B', 'C', 'D', 'E'].forEach(function (k) { if (q.options[k] === undefined) q.options[k] = ''; });
    var h = '<div class="oq" data-i="' + i + '"><div class="oq-bas"><span class="no">' + (i + 1) + '</span><select data-f="type"><option value="multiple"' + (q.type === 'multiple' ? ' selected' : '') + '>Çoktan seçmeli</option><option value="truefalse"' + (q.type === 'truefalse' ? ' selected' : '') + '>Doğru / Yanlış</option><option value="gap"' + (q.type === 'gap' ? ' selected' : '') + '>Boşluk doldurma</option><option value="classic"' + (q.type === 'classic' ? ' selected' : '') + '>Açık uçlu (klasik)</option></select><span class="bos"></span><button type="button" data-q="yukari" title="Yukarı">↑</button><button type="button" data-q="asagi" title="Aşağı">↓</button><button type="button" data-q="kopya" title="Çoğalt">⧉</button><button type="button" data-q="sil" class="sil" title="Sil">🗑</button></div>';
    h += '<div class="oq-alan' + (bos(q.paragraph) ? ' kapali' : '') + '"><label>Paragraf / öncül</label>' + arac() + ce('paragraph', q.paragraph, 'Okuma parçası, tablo, öncüller…') + '</div>' + (bos(q.paragraph) ? '<button type="button" class="oq-link" data-q="para">+ Paragraf / öncül ekle</button>' : '');
    h += '<div class="oq-alan"><label>Soru kökü</label>' + arac() + ce('root', q.root, 'Soruyu yazın; görseli yapıştırabilir ya da 🖼 ile ekleyebilirsiniz.', 'kok') + '</div>';
    if (q.type === 'multiple') h += '<span class="oq-sik-bas">Şıklar — doğru şıkkın harfine tıklayın. Şıklara görsel, kesir, altı çizili sözcük eklenebilir.</span>' + ['A', 'B', 'C', 'D', 'E'].map(function (k) { return '<div class="oq-sik' + (q.correct === k ? ' dogru' : '') + '"><button type="button" class="harf" data-dogru="' + k + '" title="Doğru cevap">' + k + '</button><div class="ic">' + arac() + ce('', q.options[k], k === 'E' ? 'E şıkkı (4 şıklı testlerde boş bırakın)' : k + ' şıkkı', 'sik', k) + '</div></div>'; }).join('');
    else if (q.type === 'truefalse') h += '<div class="oq-dy"><label class="oq-sik-bas">Doğru cevap</label><button type="button" data-dogru="A" class="' + (q.correct === 'A' ? 'on' : '') + '">✓ Doğru</button><button type="button" data-dogru="B" class="' + (q.correct === 'B' ? 'on' : '') + '">✗ Yanlış</button></div>';
    else h += '<div class="oq-alan"><label>' + (q.type === 'gap' ? 'Doğru cevap (alternatifleri / ile ayırın)' : 'Model cevap') + '</label>' + arac() + ce('', q.options.A, 'Cevap', '', 'A') + '</div>';
    return h + '</div>';
  }
  function ust() {
    if (document.getElementById('oq-ust')) return;
    var d = document.createElement('div'); d.id = 'oq-ust';
    d.innerHTML = '<b>Cevap anahtarı:</b><input id="oq-anahtar" placeholder="Örn: ABCDA BCDAB" autocomplete="off"><button type="button" class="button" data-u="anahtar">Uygula</button><button type="button" class="button" data-u="kesir" title="Şık ve sorulardaki 3/4 gibi yazılmış kesirleri alt alta kesre çevirir">a/b → kesir</button><span class="ozet"></span>';
    liste.parentNode.insertBefore(d, liste);
    d.addEventListener('click', function (e) {
      var b = e.target.closest('[data-u]'); if (!b) return;
      if (b.dataset.u === 'anahtar') { var h = (document.getElementById('oq-anahtar').value || '').toUpperCase().replace(/[^A-E]/g, '').split(''), j = 0; Q.forEach(function (q) { if (q.type === 'multiple' && j < h.length) q.correct = h[j++]; }); window.render(); }
      if (b.dataset.u === 'kesir') { var n = 0, cv = function (v) { return String(v || '').replace(/(^|>)([^<]*)/g, function (m, a, t) { return a + t.replace(/(^|[^\d\/.,])(\d+)\s*\/\s*(\d+)(?![\d\/])/g, function (m2, o, x, y) { n++; return o + '<span class="osc-kesir"><span class="p">' + x + '</span><span class="d">' + y + '</span></span>'; }); }); }; Q.forEach(function (q) { q.root = cv(q.root); q.paragraph = cv(q.paragraph); if (q.type === 'multiple') ['A', 'B', 'C', 'D', 'E'].forEach(function (k) { q.options[k] = cv(q.options[k]); }); }); window.render(); alert(n + ' kesir düzenlendi.'); }
    });
  }
  window.oscYeniRender = function (qs) {
    Q = qs; ust();
    var y = window.scrollY;
    liste.innerHTML = Q.length ? Q.map(soru).join('') : '<p style="color:#50575e">Henüz soru yok. Dosya yükleyin ya da “+ YENİ SORU EKLE” ile başlayın.</p>';
    window.scrollTo(0, y); sync();
  };
  function no(el) { var q = el.closest('.oq'); return q ? +q.dataset.i : -1; }
  function kaydetAlan(t) { var i = no(t); if (i < 0) return; var q = Q[i], v = temiz(t.innerHTML); if (t.dataset.f) q[t.dataset.f] = v; else if (t.dataset.o) q.options[t.dataset.o] = v; sync(); }
  liste.addEventListener('input', function (e) { if (e.target.classList.contains('oq-ce')) kaydetAlan(e.target); });
  liste.addEventListener('change', function (e) { var t = e.target; if (t.dataset.f !== 'type') return; var q = Q[no(t)]; q.type = t.value; q.correct = ''; if (q.type === 'truefalse') q.options = { A: 'Doğru', B: 'Yanlış', C: '', D: '', E: '' }; window.render(); });
  liste.addEventListener('mousedown', function (e) { if (e.target.closest('[data-a]')) e.preventDefault(); });
  var hedef = null;
  liste.addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return; var i = no(b), q = Q[i];
    if (b.dataset.a) {
      var c = b.closest('.oq-alan, .ic').querySelector('.oq-ce'); var a = b.dataset.a;
      if (a === 'img') { hedef = c; var f = document.getElementById('oq-dosya'); f.value = ''; f.onchange = function () { if (f.files[0]) gorsel(f.files[0], hedef); }; f.click(); return; }
      c.focus();
      if (a === 'kesir') { var sel = String(window.getSelection() || ''), m = /^\s*(.+?)\s*[\/÷]\s*(.+?)\s*$/.exec(sel), p = m ? m[1] : (sel.trim() || '1'), d = m ? m[2] : (sel.trim() ? '?' : '2'); document.execCommand('insertHTML', false, '<span class="osc-kesir"><span class="p">' + esc(p) + '</span><span class="d">' + esc(d) + '</span></span>&nbsp;'); }
      else if (a === 'sembol') { sembolMenu(b, c); return; }
      else if (a === 'temizle') document.execCommand('removeFormat');
      else document.execCommand({ b: 'bold', i: 'italic', u: 'underline', sup: 'superscript', sub: 'subscript' }[a]);
      kaydetAlan(c); return;
    }
    if (b.dataset.dogru) { q.correct = b.dataset.dogru; var k = b.closest('.oq'); k.querySelectorAll('.oq-sik').forEach(function (s) { s.classList.toggle('dogru', s.querySelector('[data-dogru]').dataset.dogru === q.correct); }); k.querySelectorAll('.oq-dy button').forEach(function (s) { s.classList.toggle('on', s.dataset.dogru === q.correct); }); k.classList.remove('hata'); sync(); return; }
    if (b.dataset.q === 'para') { var al = b.previousElementSibling; al.classList.remove('kapali'); b.remove(); al.querySelector('.oq-ce').focus(); return; }
    if (b.dataset.q === 'sil') { if (confirm((i + 1) + '. soru silinsin mi?')) { Q.splice(i, 1); window.render(); } return; }
    if (b.dataset.q === 'kopya') { Q.splice(i + 1, 0, JSON.parse(JSON.stringify(q))); window.render(); return; }
    if (b.dataset.q === 'yukari' && i > 0) { Q.splice(i - 1, 0, Q.splice(i, 1)[0]); window.render(); return; }
    if (b.dataset.q === 'asagi' && i < Q.length - 1) { Q.splice(i + 1, 0, Q.splice(i, 1)[0]); window.render(); return; }
  });
  var SEMB = ['∈', '∉', '⊂', '⊆', '⊄', '∪', '∩', '∅', 'ℕ', 'ℤ', 'ℚ', 'ℝ', 'ℤ⁺', 'ℤ⁻', '≤', '≥', '≠', '≈', '±', '×', '÷', '·', '√', '∛', 'π', '°', '∠', '△', '⊥', '∥', '≅', '∞', '→', '⇒', '⇔', 'α', 'β', 'θ', 'Δ', '½', '¼', '¾', '²', '³'];
  function sembolMenu(b, c) {
    var m = document.getElementById('oq-semb'); if (m) m.remove();
    m = document.createElement('div'); m.id = 'oq-semb'; m.style.cssText = 'position:absolute;z-index:100001;background:#fff;border:1px solid #c3c4c7;border-radius:10px;padding:6px;display:grid;grid-template-columns:repeat(9,30px);gap:2px;box-shadow:0 10px 24px -8px rgba(0,0,0,.3)';
    m.innerHTML = SEMB.map(function (x) { return '<button type="button" style="height:30px;border:0;background:#f6f7f7;border-radius:6px;cursor:pointer;font-size:15px">' + x + '</button>'; }).join('');
    var r = b.getBoundingClientRect(); m.style.left = (r.left + window.scrollX) + 'px'; m.style.top = (r.bottom + window.scrollY + 4) + 'px';
    m.addEventListener('mousedown', function (e) { e.preventDefault(); });
    m.addEventListener('click', function (e) { var x = e.target.closest('button'); if (!x) return; c.focus(); document.execCommand('insertText', false, x.textContent); kaydetAlan(c); m.remove(); });
    document.body.appendChild(m);
    setTimeout(function () { document.addEventListener('mousedown', function k(e) { if (!m.contains(e.target)) { m.remove(); document.removeEventListener('mousedown', k); } }); }, 0);
  }
  function yukle(veri) {
    var n = document.getElementById('osc_img_nonce'), fd = new FormData();
    fd.append('action', 'osc_upload_image'); fd.append('nonce', n ? n.value : ''); fd.append('post_id', (document.getElementById('post_ID') || {}).value || '0'); fd.append('data', veri);
    return fetch(window.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { return j && j.success && j.data && j.data.url ? j.data.url : veri; }).catch(function () { return veri; });
  }
  function gorsel(f, c) {
    if (!/^image\//.test(f.type)) return;
    var fr = new FileReader(); fr.onload = function () { var im = new Image(); im.onload = function () {
      var k = Math.min(1, 1000 / Math.max(im.width, im.height)), cv = document.createElement('canvas'); cv.width = Math.round(im.width * k); cv.height = Math.round(im.height * k);
      var cx = cv.getContext('2d'); cx.fillStyle = '#fff'; cx.fillRect(0, 0, cv.width, cv.height); cx.drawImage(im, 0, 0, cv.width, cv.height);
      var png = cv.toDataURL('image/png'), jpg = cv.toDataURL('image/jpeg', 0.86);
      yukle(png.length < jpg.length * 1.2 ? png : jpg).then(function (u) { c.focus(); document.execCommand('insertHTML', false, '<img src="' + esc(u) + '" alt="">'); kaydetAlan(c); });
    }; im.src = fr.result; }; fr.readAsDataURL(f);
  }
  // yapıştırma: resim -> yükle; Word'den metin -> altı çizili/kalın/üs korunur
  function sadeHtml(h) {
    var d = document.createElement('div'); d.innerHTML = h;
    (function yuru(n) {
      Array.prototype.slice.call(n.childNodes).forEach(function (c) {
        if (c.nodeType === 3) return; if (c.nodeType !== 1) { c.remove(); return; }
        yuru(c);
        var t = c.tagName.toLowerCase(), st = (c.getAttribute('style') || '').toLowerCase(), sar = null;
        if (/^(u|b|i|sup|sub|br|img)$/.test(t)) { if (t === 'img') { var s = c.getAttribute('src') || ''; if (!/^(https?:|data:image)/.test(s)) { c.remove(); return; } } Array.prototype.slice.call(c.attributes).forEach(function (a) { if (!(t === 'img' && a.name === 'src')) c.removeAttribute(a.name); }); return; }
        if (t === 'strong') sar = 'b'; else if (t === 'em') sar = 'i';
        else if (/underline/.test(st)) sar = 'u'; else if (/font-weight:\s*(bold|[6-9]00)/.test(st)) sar = 'b'; else if (/vertical-align:\s*super/.test(st)) sar = 'sup'; else if (/vertical-align:\s*sub/.test(st)) sar = 'sub';
        if (sar) { var y = document.createElement(sar); while (c.firstChild) y.appendChild(c.firstChild); c.replaceWith(y); return; }
        var blok = /^(p|div|li|tr|h\d)$/.test(t); var fr2 = document.createDocumentFragment(); while (c.firstChild) fr2.appendChild(c.firstChild); if (blok) fr2.appendChild(document.createElement('br')); c.replaceWith(fr2);
      });
    })(d);
    return d.innerHTML.replace(/(<br>\s*)+$/, '');
  }
  liste.addEventListener('paste', function (e) {
    var c = e.target.closest && e.target.closest('.oq-ce'); if (!c) return; var cd = e.clipboardData; if (!cd) return;
    for (var k = 0; k < (cd.items || []).length; k++) if (cd.items[k].type.indexOf('image/') === 0 && !cd.getData('text/html')) { e.preventDefault(); gorsel(cd.items[k].getAsFile(), c); return; }
    e.preventDefault(); var h = cd.getData('text/html');
    document.execCommand('insertHTML', false, h ? sadeHtml(h.replace(/<!--[\s\S]*?-->/g, '')) : esc(cd.getData('text/plain') || '').replace(/\r?\n/g, '<br>'));
    kaydetAlan(c);
  });
  // yayımlamadan önce eksik soruları göster
  var form = document.getElementById('post');
  if (form) form.addEventListener('submit', function () { if (Q) { document.querySelectorAll('#osc-q-list .oq-ce').forEach(kaydetAlan); } });
})();
</script>
<?php }
}
new OSC_Admin_Test();
}
