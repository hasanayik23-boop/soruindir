
/* =====================================================================
 * Sanal sayfalar (sayfa oluşturmaya gerek yok):
 *   /kategoriler/       tüm kategoriler, arama ve sınıf filtresiyle
 *   /tercih-robotlari/  LGS ve YKS tercih robotları bir arada
 * ===================================================================== */
if (!class_exists('OSC_Sayfalar')) {
class OSC_Sayfalar {
    public static $yollar = array('kategoriler' => 'kategoriler', 'tercih-robotlari' => 'robotlar');
    public function __construct() {
        add_action('template_redirect', array($this, 'yonlendir'), 5);
        add_filter('pre_get_document_title', array($this, 'baslik'), 60);
    }
    public static function yol() { return isset($_SERVER['REQUEST_URI']) ? trim(strtok(wp_unslash($_SERVER['REQUEST_URI']), '?'), '/') : ''; }
    public static function url($k) { return home_url('/' . $k . '/'); }
    public function baslik($t) {
        $y = self::yol();
        if ($y === 'kategoriler') return 'Tüm Kategoriler – Yazılı, Test, Plan ve Evraklar | Soruindir';
        if ($y === 'liderlik') return 'Haftanın Liderleri – Öğrenci Liderlik Tablosu | Soruindir';
        if ($y === 'tercih-robotlari') return 'LGS ve YKS Tercih Robotu – Puanına Uygun Okul ve Bölümler | Soruindir';
        return $t;
    }
    public function yonlendir() {
        $y = self::yol(); if (!isset(self::$yollar[$y]) || !is_404()) return;
        global $wp_query; $wp_query->is_404 = false; status_header(200);
        $acik = array('kategoriler' => 'Yazılı soruları, testler, yıllık planlar, zümre tutanakları ve tüm evraklar; sınıf ve derse göre kategoriler.', 'liderlik' => 'Online test çözen öğrencilerin haftalık XP liderlik tablosu: Türkiye geneli ve sınıf seviyelerine göre sıralama.', 'tercih-robotlari' => 'LGS ve YKS tercih robotları: puanına ve başarı sırana göre girebileceğin lise, üniversite ve bölümleri gör.');
        add_action('wp_head', function () use ($y, $acik) { echo '<meta name="description" content="' . esc_attr($acik[$y]) . '">' . "\n" . '<link rel="canonical" href="' . esc_url(self::url($y)) . '">' . "\n"; }, 1);
        $f = self::$yollar[$y]; if ($f === 'liderlik' && !class_exists('OSC_Oyun')) return;
        get_header(); echo self::stil(); echo $this->$f(); get_footer(); exit;
    }
    public static function stil() {
        return '<style>
.osp{--i:#13304F;--i2:#5B7189;--c:#E3EBF3;--m:#2F80ED;font-family:Figtree,system-ui,-apple-system,"Segoe UI",sans-serif;max-width:1180px;margin:0 auto;padding:22px 16px 56px;color:var(--i)}
.osp *{box-sizing:border-box}
.osp-hero{position:relative;overflow:hidden;border-radius:28px;padding:40px 24px 30px;text-align:center;background:linear-gradient(180deg,#EAF4FC,#F5F9FE);border:1px solid #DCEAF7}
.osp-hero:after{content:"";position:absolute;right:-60px;top:-60px;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle,rgba(47,128,237,.14),transparent 70%)}
.osp-ust{display:inline-flex;gap:6px;align-items:center;padding:5px 12px;border-radius:99px;background:#fff;border:1px solid #DCEAF7;font-size:12.5px;font-weight:700;color:var(--m)}
.osp h1{margin:14px 0 0 !important;font-size:clamp(27px,3.8vw,42px);line-height:1.12;letter-spacing:-.025em;font-weight:800;color:var(--i)}
.osp h1 span{color:var(--m)}
.osp-hero p{margin:10px auto 0 !important;max-width:620px;color:#46607A;font-size:16.5px;position:relative}
.osp-ara{position:relative;max-width:560px;margin:22px auto 0;z-index:1}
.osp-ara svg{position:absolute;left:18px;top:50%;transform:translateY(-50%);width:20px;height:20px;color:#8AA2BA}
.osp .osp-ara input{width:100%;border:1.5px solid #D3E2F1 !important;border-radius:99px !important;padding:15px 18px 15px 48px !important;font:inherit;font-size:16px;background:#fff;outline:0;box-shadow:0 16px 34px -26px rgba(19,48,79,.55)}
.osp .osp-ara input:focus{border-color:var(--m) !important;box-shadow:0 0 0 4px rgba(47,128,237,.14)}
.osp-filtre{display:flex;flex-wrap:wrap;justify-content:center;gap:6px;margin-top:16px;position:relative;z-index:1}
.osp-filtre button{border:1px solid #DCEAF7;background:#fff;color:#46607A;font:600 13.5px/1 inherit;font-family:inherit;padding:9px 14px;border-radius:99px;cursor:pointer}
.osp-filtre button.on{background:#13304F;border-color:#13304F;color:#fff}
.osp-gr{margin-top:30px}
.osp-gr h2{margin:0 0 12px !important;font-size:19px;font-weight:800;display:flex;align-items:center;gap:10px;color:var(--i)}
.osp-gr h2:after{content:"";flex:1;height:1px;background:var(--c)}
.osp-iz{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.osp-k{display:flex;flex-direction:column;border-radius:20px;background:#fff;border:1px solid var(--c);overflow:hidden;transition:transform .16s,box-shadow .16s,border-color .16s}
.osp-k:hover{transform:translateY(-2px);border-color:#C9DCF0;box-shadow:0 18px 34px -28px rgba(19,48,79,.6)}
.osp .osp-k>a{display:flex;gap:14px;align-items:center;padding:16px;color:var(--i);text-decoration:none}
.osp-r{flex:0 0 48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;color:var(--r);background:color-mix(in srgb,var(--r) 12%,#fff)}
.osp-r svg{width:24px;height:24px}
.osp-k .y{flex:1;min-width:0}.osp-k b{display:block;font-size:15.5px;line-height:1.3}.osp-k small{display:block;margin-top:3px;font-size:12.5px;color:var(--i2)}
.osp-k .ok{flex:0 0 auto;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#F1F5F9;color:#8AA2BA;transition:all .16s}
.osp-k:hover .ok{background:var(--r);color:#fff}
.osp-alt{display:flex;flex-wrap:wrap;gap:6px;padding:0 16px 14px}
.osp .osp-alt a{font-size:12.5px;font-weight:600;color:#46607A;background:#F4F7FB;border:1px solid #E8EEF5;border-radius:99px;padding:4px 10px;text-decoration:none}
.osp .osp-alt a:hover{color:var(--r);border-color:var(--r)}
.osp-sinif{display:grid;grid-template-columns:repeat(6,1fr);gap:10px}
.osp .osp-sinif a{display:flex;flex-direction:column;align-items:center;gap:2px;padding:14px 6px;border-radius:16px;background:#fff;border:1px solid var(--c);text-decoration:none;color:var(--i);transition:all .15s}
.osp .osp-sinif a:hover{border-color:var(--m);transform:translateY(-2px)}
.osp-sinif b{font-size:22px;font-weight:800;color:var(--r)}.osp-sinif span{font-size:12.5px;font-weight:600;color:var(--i2)}
.osp-yok{display:none;text-align:center;color:var(--i2);margin-top:28px}
.osp-rb{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:22px}
.osp .osp-rk{position:relative;overflow:hidden;display:flex;flex-direction:column;gap:10px;padding:28px;border-radius:26px;color:#fff;text-decoration:none;background:var(--r);min-height:260px;box-shadow:0 26px 46px -34px var(--r);transition:transform .18s}
.osp .osp-rk:hover{transform:translateY(-3px);color:#fff}
.osp-rk:after{content:attr(data-k);position:absolute;right:-8px;bottom:-34px;font-size:130px;font-weight:900;opacity:.13;letter-spacing:-.05em}
.osp-rk small{align-self:flex-start;padding:4px 10px;border-radius:99px;background:rgba(255,255,255,.2);font-size:12px;font-weight:700}
.osp-rk b{font-size:28px;letter-spacing:-.02em;line-height:1.1}
.osp-rk ul{margin:2px 0 0;padding:0;list-style:none;display:grid;gap:6px;font-size:14.5px;opacity:.95}
.osp-rk li:before{content:"✓  ";font-weight:800}
.osp-rk i{margin-top:auto;align-self:flex-start;font-style:normal;font-weight:700;font-size:15px;background:#fff;color:var(--r);padding:11px 18px;border-radius:12px}
.osp-ek{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:16px}
.osp .osp-ek a{display:flex;gap:12px;align-items:center;padding:16px;border-radius:18px;background:#fff;border:1px solid var(--c);text-decoration:none;color:var(--i);font-weight:700;font-size:15px}
.osp .osp-ek a:hover{border-color:var(--m)}
.osp-ek span{font-size:22px}
.osp-bilgi{margin-top:26px;padding:20px 22px;border-radius:20px;background:#F7FAFD;border:1px solid var(--c);color:#46607A;font-size:14.5px;line-height:1.6}
.osp-bilgi b{color:var(--i)}
@media(max-width:900px){.osp-iz,.osp-ek{grid-template-columns:1fr 1fr}.osp-sinif{grid-template-columns:repeat(4,1fr)}}
@media(max-width:640px){.osp-iz,.osp-ek,.osp-rb{grid-template-columns:1fr}.osp-sinif{grid-template-columns:repeat(3,1fr)}.osp-hero{padding:30px 16px 24px;border-radius:22px}}
</style>';
    }
    private static function ikon($ad) {
        $t = mb_strtolower($ad, 'UTF-8');
        $o = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">';
        if (preg_match('/plan/u', $t)) return $o . '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>';
        if (preg_match('/hesap|puan/u', $t)) return $o . '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 11h.01M12 11h.01M16 11h.01M8 15h.01M12 15h.01M16 15h.01"/></svg>';
        if (preg_match('/test|deneme|soru|sınav|yazılı/u', $t)) return $o . '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8"/></svg>';
        if (preg_match('/zümre|tutanak|toplantı|idare|müdür|öğretmen|evrak|dilekçe/u', $t)) return $o . '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
        if (preg_match('/oyun|etkinlik|boyama/u', $t)) return $o . '<circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01"/></svg>';
        if (preg_match('/rehber|bep|karne|veli/u', $t)) return $o . '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>';
        return $o . '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2zM22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>';
    }
    private static function kucuk($s) { return mb_strtolower(strtr($s, array('İ' => 'i', 'I' => 'ı')), 'UTF-8'); }
    public function kategoriler() {
        $renk = array('#2F80ED', '#22A06B', '#7C3AED', '#E8890B', '#DB2777', '#0D9488', '#DC2626', '#4F46E5', '#0369A1', '#4D7C0F', '#C2410C', '#475569');
        $tum = get_categories(array('hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC'));
        $cocuk = array(); foreach ($tum as $c) $cocuk[$c->parent][] = $c;
        $sinif = array(); $ana = array(); $toplam = 0;
        foreach ($tum as $c) { $toplam += (int) $c->count; if ($c->slug === 'uncategorized' || $c->slug === 'genel') continue; if (preg_match('/^(\d{1,2})-sinif$/', $c->slug, $m)) { $sinif[(int) $m[1]] = $c; continue; } if (!$c->parent) $ana[] = $c; }
        ksort($sinif);
        ob_start(); ?>
<div class="osp">
  <section class="osp-hero">
    <span class="osp-ust">📚 <?php echo count($tum); ?> kategori</span>
    <h1>Tüm <span>kategoriler</span></h1>
    <p>Yazılı soruları, testler, yıllık planlar, zümre tutanakları ve evraklar. Aradığın kategoriyi bul, tek tıkla indir.</p>
    <div class="osp-ara"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7.5"/><path d="m20.5 20.5-4.2-4.2"/></svg><input type="search" id="osp-q" placeholder="Kategori ara: yazılı, plan, zümre…" aria-label="Kategori ara" autocomplete="off"></div>
  </section>
  <?php if ($sinif): ?>
  <section class="osp-gr" data-tip="sinif"><h2>Sınıflara göre</h2><div class="osp-sinif">
    <?php foreach ($sinif as $n => $c) echo '<a href="' . esc_url(get_category_link($c)) . '" style="--r:' . $renk[$n % count($renk)] . '" data-s="' . esc_attr(self::kucuk($c->name)) . '"><b>' . $n . '</b><span>' . ($n <= 4 ? 'İlkokul' : ($n <= 8 ? 'Ortaokul' : 'Lise')) . ' · ' . (int) $c->count . '</span></a>'; ?>
  </div></section>
  <?php endif; ?>
  <section class="osp-gr"><h2>Tüm kategoriler</h2><div class="osp-iz">
    <?php foreach ($ana as $i => $c):
        $r = $renk[$i % count($renk)]; $alt = isset($cocuk[$c->term_id]) ? $cocuk[$c->term_id] : array();
        $ara = self::kucuk($c->name . ' ' . implode(' ', wp_list_pluck($alt, 'name'))); ?>
    <div class="osp-k" style="--r:<?php echo $r; ?>" data-s="<?php echo esc_attr($ara); ?>">
      <a href="<?php echo esc_url(get_category_link($c)); ?>"><span class="osp-r" aria-hidden="true"><?php echo self::ikon($c->name); ?></span><span class="y"><b><?php echo esc_html($c->name); ?></b><small><?php echo number_format_i18n($c->count); ?> içerik<?php echo $alt ? ' · ' . count($alt) . ' alt kategori' : ''; ?></small></span><span class="ok" aria-hidden="true">→</span></a>
      <?php if ($alt): ?><div class="osp-alt"><?php foreach (array_slice($alt, 0, 8) as $a) echo '<a href="' . esc_url(get_category_link($a)) . '">' . esc_html($a->name) . '</a>'; if (count($alt) > 8) echo '<a href="' . esc_url(get_category_link($c)) . '">+' . (count($alt) - 8) . ' daha</a>'; ?></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div></section>
  <p class="osp-yok" id="osp-yok">Bu aramaya uygun kategori bulunamadı.</p>
</div>
<script>
(function () { var q = document.getElementById('osp-q'); if (!q) return;
  q.addEventListener('input', function () { var t = q.value.trim().toLocaleLowerCase('tr-TR'), n = 0;
    document.querySelectorAll('.osp [data-s]').forEach(function (k) { var v = !t || k.dataset.s.indexOf(t) > -1; k.style.display = v ? '' : 'none'; if (v) n++; });
    document.querySelectorAll('.osp-gr').forEach(function (g) { g.style.display = g.querySelector('[data-s]:not([style*="none"])') ? '' : 'none'; });
    document.getElementById('osp-yok').style.display = n ? 'none' : 'block'; });
})();
</script>
<?php return ob_get_clean();
    }
    public function liderlik() {
        $op = class_exists('OSC_Sinav') ? (int) get_option('osc_sp_ogrenci_sayfa') : 0; $ou = $op ? get_permalink($op) : home_url('/ogrenci-paneli/');
        ob_start(); ?>
<div class="osp">
  <section class="osp-hero">
    <span class="osp-ust">🏆 Her pazartesi yeniden başlar</span>
    <h1>Haftanın <span>liderleri</span></h1>
    <p>Test çöz, ödevini teslim et, rozet kazan. Topladığın XP ile sınıfında, okulunda ve Türkiye genelinde sıralamaya gir.</p>
  </section>
  <div class="osp-rb" style="grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);align-items:start">
    <div class="osp-k" style="padding:20px"><?php echo do_shortcode('[osc_liderlik]'); ?></div>
    <div style="display:grid;gap:12px">
      <div class="osp-bilgi" style="margin:0"><b>Nasıl XP kazanılır?</b><br>✅ Her test: 10 XP + puanının onda biri<br>📝 Ödev / sınav teslimi: 20 XP<br>🔥 Günlük seri: her gün +1 XP (en fazla 10)<br>🏅 Her rozet: 25 XP</div>
      <div class="osp-ek" style="grid-template-columns:1fr;margin:0"><a href="<?php echo esc_url($ou); ?>"><span>🎒</span>Rozetlerim ve sınıf ligim</a><a href="<?php echo esc_url(home_url('/test-coz/')); ?>"><span>✅</span>Test çöz, XP kazan</a></div>
    </div>
  </div>
</div>
<?php return ob_get_clean();
    }
    public static function robot_lgs() { return class_exists('LGS_Tercih_Robotu') && method_exists('LGS_Tercih_Robotu', 'robot_url') ? LGS_Tercih_Robotu::robot_url() : ''; }
    public static function robot_yks() { return class_exists('YKS_Tercih_Robotu') && method_exists('YKS_Tercih_Robotu', 'robot_url') ? YKS_Tercih_Robotu::robot_url() : ''; }
    public function robotlar() {
        $l = self::robot_lgs(); $y = self::robot_yks();
        $hub = function_exists('si_hub_url') ? si_hub_url() : home_url('/hesaplamalar/');
        ob_start(); ?>
<div class="osp">
  <section class="osp-hero">
    <span class="osp-ust">🎓 Ücretsiz · üyelik gerekmez</span>
    <h1>Tercih <span>robotları</span></h1>
    <p>Puanını ya da başarı sıranı gir; geçen yılın taban puanlarına göre girebileceğin okul ve bölümleri hemen gör.</p>
  </section>
  <div class="osp-rb">
    <a class="osp-rk" style="--r:#2F80ED" data-k="LGS" href="<?php echo esc_url($l ? $l : home_url('/lgs-tercih-robotu/')); ?>"><small>8. sınıf</small><b>LGS Tercih Robotu</b><ul><li>Puan ve yüzdelik dilime göre liseler</li><li>İl, ilçe ve okul türüne göre süzme</li><li>Taban puan ve kontenjan bilgisi</li></ul><i>LGS robotunu aç →</i></a>
    <a class="osp-rk" style="--r:#7C3AED" data-k="YKS" href="<?php echo esc_url($y ? $y : home_url('/yks-tercih-robotu/')); ?>"><small>12. sınıf ve mezun</small><b>YKS Tercih Robotu</b><ul><li>Başarı sırasına göre üniversite ve bölümler</li><li>SAY, EA, SÖZ, DİL ve TYT puan türleri</li><li>Devlet ve vakıf üniversiteleri</li></ul><i>YKS robotunu aç →</i></a>
  </div>
  <div class="osp-ek">
    <a href="<?php echo esc_url($hub); ?>"><span>🧮</span>Puanını önce hesapla</a>
    <a href="<?php echo esc_url(get_category_link(get_category_by_slug('8-sinif')) ?: home_url('/')); ?>"><span>📝</span>8. sınıf LGS denemeleri</a>
    <a href="<?php echo esc_url(home_url('/test-coz/')); ?>"><span>✅</span>Online test çöz</a>
  </div>
  <div class="osp-bilgi"><b>Nasıl çalışır?</b> Robotlar, ÖSYM ve MEB’in açıkladığı son yerleştirme sonuçlarındaki taban puan ve başarı sıralarını kullanır. Sonuçlar yol göstericidir; kontenjan ve puanlar her yıl değişir.</div>
</div>
<?php return ob_get_clean();
    }
}
new OSC_Sayfalar();
}
