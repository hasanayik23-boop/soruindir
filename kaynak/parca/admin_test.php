
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
<?php }
}
new OSC_Admin_Test();
}
