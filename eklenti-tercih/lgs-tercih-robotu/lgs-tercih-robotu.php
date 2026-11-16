<?php
/**
 * Plugin Name: LGS Tercih Robotu (Soruindir)
 * Description: LGS puanına göre Türkiye'deki sınavla öğrenci alan liseleri listeler; netlerden tahmini puan hesaplar, öğrencinin kendi tercih listesini oluşturmasını sağlar. Okullar yönetim panelinden eklenip düzenlenebilir. Kısa kod: [lgs_tercih_robotu]
 * Version: 1.5.0
 * Author: Soruindir
 * Text Domain: lgs-tercih-robotu
 */

if (!defined('ABSPATH')) exit;

define('LGSTR_VER', '1.5.0');
define('LGSTR_DIR', plugin_dir_path(__FILE__));
define('LGSTR_URL', plugin_dir_url(__FILE__));

class LGS_Tercih_Robotu {

    const OPT_DUZEN = 'lgstr_duzen';   // kurum kodu => okul kaydı (eklenen ya da düzenlenen)
    const OPT_GIZLI = 'lgstr_gizli';   // gizlenen kurum kodları

    public static $ILLER = array('Adana','Adıyaman','Afyonkarahisar','Ağrı','Aksaray','Amasya','Ankara','Antalya','Ardahan','Artvin','Aydın','Balıkesir','Bartın','Batman','Bayburt','Bilecik','Bingöl','Bitlis','Bolu','Burdur','Bursa','Çanakkale','Çankırı','Çorum','Denizli','Diyarbakır','Düzce','Edirne','Elazığ','Erzincan','Erzurum','Eskişehir','Gaziantep','Giresun','Gümüşhane','Hakkari','Hatay','Iğdır','Isparta','İstanbul','İzmir','Kahramanmaraş','Karabük','Karaman','Kars','Kastamonu','Kayseri','Kilis','Kırıkkale','Kırklareli','Kırşehir','Kocaeli','Konya','Kütahya','Malatya','Manisa','Mardin','Mersin','Muğla','Muş','Nevşehir','Niğde','Ordu','Osmaniye','Rize','Sakarya','Samsun','Şanlıurfa','Siirt','Sinop','Şırnak','Sivas','Tekirdağ','Tokat','Trabzon','Tunceli','Uşak','Van','Yalova','Yozgat','Zonguldak');
    public static $GRUPLAR = array('Fen Lisesi', 'Sosyal Bilimler Lisesi', 'Anadolu Lisesi', 'Anadolu İmam Hatip Lisesi', 'Mesleki ve Teknik Anadolu Lisesi', 'Diğer');
    public static $DILLER = array('İngilizce', 'Almanca', 'Fransızca', 'Arapça', 'Rusça', 'İspanyolca', 'İtalyanca', 'Çince', 'Japonca', 'Korece', 'Farsça');

    public function __construct() {
        add_shortcode('lgs_tercih_robotu', array($this, 'shortcode'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_post_lgstr_upload', array($this, 'handle_upload'));
        add_action('admin_post_lgstr_okul_kaydet', array($this, 'handle_okul_kaydet'));
        add_action('admin_post_lgstr_okul_islem', array($this, 'handle_okul_islem'));
        add_filter('body_class', array($this, 'body_class'));
        add_action('wp_head', array($this, 'page_css'), 99);
        add_action('save_post_page', function () { delete_transient('sx_lgs_robot_url'); });
    }

    /* ================= Sayfa düzeni ================= */

    private function is_tool_page() {
        if (!is_singular()) return false;
        $p = get_post();
        return $p && has_shortcode($p->post_content, 'lgs_tercih_robotu');
    }

    public function body_class($c) {
        if ($this->is_tool_page()) { $c[] = 'lgstr-fw'; $c[] = 'no-sidebar'; $c[] = 'full-width-content'; }
        return $c;
    }

    public function page_css() {
        if (!$this->is_tool_page()) return;
        echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600..800&family=Figtree:wght@400;500;600;700&display=swap">';
        echo '<style id="lgstr-fw">'
            . 'body.lgstr-fw #right-sidebar,body.lgstr-fw #left-sidebar,body.lgstr-fw .widget-area.sidebar,body.lgstr-fw aside.sidebar,body.lgstr-fw .sidebar-wrap{display:none!important}'
            . 'body.lgstr-fw #primary,body.lgstr-fw .content-area,body.lgstr-fw .site-main,body.lgstr-fw .main-content,body.lgstr-fw .col-8{width:100%!important;max-width:100%!important;flex:1 1 100%!important;float:none!important;margin-left:0!important;margin-right:0!important}'
            . 'body.lgstr-fw .site-content,body.lgstr-fw .ts-row,body.lgstr-fw .inside-article,body.lgstr-fw article.page{display:block!important}'
            . 'body.lgstr-fw .inside-article,body.lgstr-fw article.page,body.lgstr-fw .site-main>article{padding:0!important;background:transparent!important;box-shadow:none!important;border:0!important}'
            . 'body.lgstr-fw .entry-header,body.lgstr-fw .page-header,body.lgstr-fw .breadcrumbs,body.lgstr-fw .rank-math-breadcrumb,body.lgstr-fw .post-share,body.lgstr-fw .comments-area{display:none!important}'
            . 'body.lgstr-fw .entry-content{margin:0!important;padding:0!important;max-width:none!important}'
            . 'body.lgstr-fw .entry-content>#lgstr-app{margin:0 auto}'
            . 'body.lgstr-fw .ozel-sag-sidebar{display:none!important}'
            . 'body.lgstr-fw .sayfa-kutu{padding:0!important;background:transparent!important;box-shadow:none!important;border:0!important;border-radius:0!important}'
            . 'body.lgstr-fw #primary{padding-top:24px!important}'
            . '</style>';
    }

    /* ================= Veri dosyaları =================
     * Temel veri : uploads/lgs-tercih/base-okullar.json (yönetici yüklediyse) ya da eklentinin data/okullar.json dosyası
     * Düzenlemeler: lgstr_duzen ve lgstr_gizli seçenekleri
     * Yayınlanan : uploads/lgs-tercih/okullar.json ve detay.json (temel + düzenlemeler birleştirilerek yazılır)
     */
    private function up_dir() { $u = wp_upload_dir(); return trailingslashit($u['basedir']) . 'lgs-tercih/'; }
    private function up_url() { $u = wp_upload_dir(); return trailingslashit($u['baseurl']) . 'lgs-tercih/'; }

    private function base_path($name) {
        $c = $this->up_dir() . 'base-' . $name;
        if (file_exists($c)) return $c;
        $f = LGSTR_DIR . 'data/' . $name;
        return file_exists($f) ? $f : '';
    }

    private function read_json($path) {
        if (!$path || !file_exists($path)) return null;
        $j = json_decode(file_get_contents($path), true);
        return is_array($j) ? $j : null;
    }

    private function duzenler() { $d = get_option(self::OPT_DUZEN, array()); return is_array($d) ? $d : array(); }
    private function gizliler() { $g = get_option(self::OPT_GIZLI, array()); return is_array($g) ? array_map('strval', $g) : array(); }

    /** Yayınlanan dosya güncel değilse yeniden üret */
    private function ensure_published() {
        $out = $this->up_dir() . 'okullar.json';
        $base = $this->base_path('okullar.json');
        $need = !file_exists($out) && ($this->duzenler() || $this->gizliler());
        if (file_exists($out) && $base && filemtime($base) > filemtime($out)) $need = true;
        if ($need) $this->rebuild();
    }

    private function tr_key($s) {
        return strtr($s, array('Ç' => 'Cz', 'ç' => 'cz', 'Ğ' => 'Gz', 'ğ' => 'gz', 'İ' => 'Iz', 'I' => 'Iy', 'ı' => 'iy', 'i' => 'iz', 'Ö' => 'Oz', 'ö' => 'oz', 'Ş' => 'Sz', 'ş' => 'sz', 'Ü' => 'Uz', 'ü' => 'uz'));
    }

    private function max_taban($o) {
        $m = 0;
        foreach (($o['programlar'] ?? array()) as $p) { $v = $p['t26'] ?? ($p['t25'] ?? 0); if ($v > $m) $m = $v; }
        return $m;
    }

    /** Temel veri + yönetici düzenlemeleri = yayınlanan veri */
    public function merged_data() {
        $base = $this->read_json($this->base_path('okullar.json'));
        if (!$base) $base = array('meta' => array(), 'model' => array(), 'egri' => array(), 'okullar' => array());
        $by = array();
        foreach ($base['okullar'] as $o) $by[(string) $o['k']] = $o;
        $kaynak = array();
        foreach ($by as $k => $o) $kaynak[$k] = 'temel';
        foreach ($this->duzenler() as $k => $o) {
            $k = (string) $k;
            $kaynak[$k] = isset($by[$k]) ? 'duzenlendi' : 'eklendi';
            unset($o['detay']);
            $by[$k] = $o;
        }
        $gizli = $this->gizliler();
        return array($base, $by, $kaynak, $gizli);
    }

    public function rebuild() {
        list($base, $by, $kaynak, $gizli) = $this->merged_data();
        foreach ($gizli as $k) unset($by[$k]);
        $okullar = array_values($by);
        usort($okullar, function ($a, $b) { $x = $this->max_taban($b) - $this->max_taban($a); return $x > 0 ? 1 : ($x < 0 ? -1 : 0); });
        $iller = array(); $gr = array(); $dl = array(); $pn = 0;
        foreach ($okullar as $o) {
            $iller[$o['il']] = 1; $gr[$o['grup']] = 1;
            foreach ($o['programlar'] as $p) { $pn++; if (!empty($p['dil'])) $dl[$p['dil']] = 1; }
        }
        $iller = array_keys($iller);
        usort($iller, function ($a, $b) { return strcmp($this->tr_key($a), $this->tr_key($b)); });
        $gruplar = array();
        foreach (self::$GRUPLAR as $g) if (isset($gr[$g])) $gruplar[] = $g;
        foreach (array_keys($gr) as $g) if (!in_array($g, $gruplar, true)) $gruplar[] = $g;
        $diller = array_keys($dl);
        usort($diller, function ($a, $b) { return strcmp($this->tr_key($a), $this->tr_key($b)); });
        $meta = is_array($base['meta'] ?? null) ? $base['meta'] : array();
        $meta['okulSayisi'] = count($okullar);
        $meta['programSayisi'] = $pn;
        if ($this->duzenler()) $meta['duzenleme'] = current_time('Y-m-d');
        $out = array('meta' => $meta, 'model' => $base['model'] ?? array(), 'egri' => $base['egri'] ?? array(),
            'iller' => $iller, 'gruplar' => $gruplar, 'diller' => $diller, 'okullar' => $okullar);

        $dir = $this->up_dir();
        wp_mkdir_p($dir);
        file_put_contents($dir . 'okullar.json', wp_json_encode($out, JSON_UNESCAPED_UNICODE));

        // detay
        $det = $this->read_json($this->base_path('detay.json')) ?: array();
        foreach ($this->duzenler() as $k => $o) {
            if (!empty($o['detay'])) $det[(string) $k] = $o['detay'];
        }
        foreach ($gizli as $k) unset($det[$k]);
        if ($det) file_put_contents($dir . 'detay.json', wp_json_encode($det, JSON_UNESCAPED_UNICODE));
        elseif (file_exists($dir . 'detay.json')) unlink($dir . 'detay.json');
    }

    private function public_url($name) {
        $p = $this->up_dir() . $name;
        if (file_exists($p)) return $this->up_url() . $name . '?v=' . filemtime($p);
        $b = $this->base_path($name);
        if (!$b) return '';
        if (strpos($b, LGSTR_DIR) === 0) return LGSTR_URL . 'data/' . $name . '?v=' . filemtime($b);
        return $this->up_url() . basename($b) . '?v=' . filemtime($b);
    }

    /* ================= Kısa kod ================= */

    public function shortcode($atts) {
        $atts = shortcode_atts(array('baslik' => 'LGS Tercih Robotu'), $atts, 'lgs_tercih_robotu');
        $this->ensure_published();
        wp_enqueue_style('lgstr', LGSTR_URL . 'assets/app.css', array(), LGSTR_VER);
        wp_enqueue_script('lgstr', LGSTR_URL . 'assets/app.js', array(), LGSTR_VER, true);
        wp_localize_script('lgstr', 'LGSTR', array(
            'okullar' => $this->public_url('okullar.json'),
            'detay'   => $this->public_url('detay.json'),
            'baslik'  => $atts['baslik'],
        ));
        ob_start(); ?>
        <div id="lgstr-app" class="lgstr" data-baslik="<?php echo esc_attr($atts['baslik']); ?>">
            <div class="lgstr-loading" aria-live="polite"><span></span>Tercih robotu hazırlanıyor…</div>
            <?php echo $this->ssr(); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Arama motorları (ve JavaScript'i kapalı ziyaretçiler) için okul verilerinin HTML hâli.
     * Robot yüklenince bu bölümün yerini etkileşimli araç alır. Önbellek: veri dosyası değişince yenilenir.
     */
    private function ssr() {
        $yol = file_exists($this->up_dir() . 'okullar.json') ? $this->up_dir() . 'okullar.json' : $this->base_path('okullar.json');
        if (!$yol || !file_exists($yol)) return '';
        $anahtar = 'lgstr_ssr_' . md5($yol . '|' . filemtime($yol) . '|' . LGSTR_VER);
        $h = get_transient($anahtar); if ($h !== false) return $h;
        $d = $this->read_json($yol); if (!$d || empty($d['okullar'])) return '';
        $m = isset($d['meta']) ? $d['meta'] : array(); $ty = isset($m['tabanYili']) ? $m['tabanYili'] : ''; $oy = isset($m['oncekiYil']) ? $m['oncekiYil'] : '';
        $gizli = $this->gizliler(); $satir = array();
        foreach ($d['okullar'] as $o) {
            if (in_array((string) $o['k'], $gizli, true)) continue;
            foreach ($o['programlar'] as $p) {
                $t = isset($p['t26']) && $p['t26'] !== null ? $p['t26'] : (isset($p['t25']) ? $p['t25'] : null); if ($t === null) continue;
                $satir[] = array('o' => $o, 'p' => $p, 't' => (float) $t, 'o2' => isset($p['t25']) ? $p['t25'] : null);
            }
        }
        usort($satir, function ($a, $b) { return $b['t'] <=> $a['t']; });
        $f = function ($x) { return $x === null || $x === '' ? '–' : number_format((float) $x, 2, ',', '.'); };
        $ad = function ($r) { return esc_html($r['o']['ad'] . (!empty($r['p']['alan']) ? ' – ' . $r['p']['alan'] : '') . (!empty($r['p']['dil']) && $r['p']['dil'] !== 'İngilizce' ? ' (' . $r['p']['dil'] . ')' : '')); };
        $sat = function ($r, $i) use ($f, $ad) { return '<tr><td>' . $i . '</td><td>' . $ad($r) . '</td><td>' . esc_html($r['o']['il'] . ' / ' . $r['o']['ilce']) . '</td><td>' . esc_html($r['o']['tur']) . '</td><td>' . $f($r['t']) . '</td><td>' . $f($r['o2']) . '</td></tr>'; };
        $bas = '<tr><th>#</th><th>Okul</th><th>İl / ilçe</th><th>Okul türü</th><th>' . esc_html($ty) . ' taban</th><th>' . esc_html($oy) . ' taban</th></tr>';
        ob_start(); ?>
<section class="lgstr-ssr">
<h2><?php echo esc_html($ty); ?> LGS lise taban puanları</h2>
<p>LGS Tercih Robotu ile <?php echo (int) count($d['okullar']); ?> lisenin <?php echo esc_html($ty . ' ve ' . $oy); ?> merkezi yerleştirme taban puanlarını inceleyebilir, puanını ya da netlerini girerek yerleşebileceğin okulları görebilirsin. Veriler <?php echo esc_html(isset($m['kaynak']) ? $m['kaynak'] : 'MEB e-Okul'); ?> kaynaklıdır.</p>
<h3>Taban puanı en yüksek 100 lise</h3>
<table><?php echo $bas; $i = 0; foreach (array_slice($satir, 0, 100) as $r) echo $sat($r, ++$i); ?></table>
<h3>İllere göre en yüksek taban puanlı liseler</h3>
<?php $il = array(); foreach ($satir as $r) { $k = $r['o']['il']; if (!isset($il[$k])) $il[$k] = array(); if (count($il[$k]) < 5) $il[$k][] = $r; }
uksort($il, function ($a, $b) { return strcmp($this->tr_key($a), $this->tr_key($b)); });
foreach ($il as $ad_il => $l) { echo '<h4>' . esc_html($ad_il) . ' liseleri taban puanları</h4><ol>'; foreach ($l as $r) echo '<li>' . $ad($r) . ' (' . esc_html($r['o']['ilce']) . ', ' . esc_html($r['o']['tur']) . ') – ' . esc_html($ty) . ' taban puanı: ' . $f($r['t']) . '</li>'; echo '</ol>'; } ?>
</section>
<?php $h = ob_get_clean();
        set_transient($anahtar, $h, WEEK_IN_SECONDS);
        return $h;
    }

    /* ================= Yönetim menüsü ================= */

    public function admin_menu() {
        add_menu_page('LGS Tercih Robotu', 'LGS Tercih', 'manage_options', 'lgstr-okullar', array($this, 'page_okullar'), 'dashicons-welcome-learn-more', 58);
        add_submenu_page('lgstr-okullar', 'Okullar', 'Okullar', 'manage_options', 'lgstr-okullar', array($this, 'page_okullar'));
        add_submenu_page('lgstr-okullar', 'Okul ekle', 'Okul ekle', 'manage_options', 'lgstr-okul', array($this, 'page_okul'));
        add_submenu_page('lgstr-okullar', 'Veri ve ayarlar', 'Veri ve ayarlar', 'manage_options', 'lgstr-veri', array($this, 'page_veri'));
    }

    private function notice() {
        if (empty($_GET['lgstr_msg'])) return;
        $msg = sanitize_text_field(wp_unslash($_GET['lgstr_msg']));
        $err = !empty($_GET['lgstr_err']);
        echo '<div class="notice ' . ($err ? 'notice-error' : 'notice-success') . ' is-dismissible"><p>' . esc_html($msg) . '</p></div>';
    }

    private function admin_css() { ?>
        <style>
            .lgstr-adm .lgstr-filter{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:14px 0}
            .lgstr-adm .lgstr-filter input[type=search]{min-width:260px}
            .lgstr-adm .lgstr-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;background:#f0f0f1;color:#50575e}
            .lgstr-adm .lgstr-badge.is-edit{background:#e8f0fa;color:#1E5AA8}.lgstr-adm .lgstr-badge.is-new{background:#ddf4e8;color:#0F7B4B}.lgstr-adm .lgstr-badge.is-hide{background:#fdecef;color:#B92D5F}
            .lgstr-adm tr.is-hidden td{opacity:.55}
            .lgstr-adm .lgstr-num{text-align:right;font-variant-numeric:tabular-nums}
            .lgstr-adm .lgstr-box{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px 20px;margin:16px 0;max-width:1100px}
            .lgstr-adm .lgstr-box h2{margin-top:0;font-size:16px}
            .lgstr-adm .lgstr-grid2{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px 18px}
            .lgstr-adm .lgstr-grid2 label{display:block;font-weight:600;margin-bottom:4px}
            .lgstr-adm .lgstr-grid2 input[type=text],.lgstr-adm .lgstr-grid2 input[type=url],.lgstr-adm .lgstr-grid2 input[type=email],.lgstr-adm .lgstr-grid2 input[type=number],.lgstr-adm .lgstr-grid2 select{width:100%}
            .lgstr-adm .lgstr-wide{grid-column:1/-1}
            .lgstr-adm .lgstr-wide textarea{width:100%}
            .lgstr-adm table.lgstr-prog input[type=text]{width:100%}
            .lgstr-adm table.lgstr-prog td{vertical-align:middle}
            .lgstr-adm .description{color:#646970}
        </style>
    <?php }

    /* ---------- Okul listesi ---------- */
    public function page_okullar() {
        if (!current_user_can('manage_options')) return;
        list($base, $by, $kaynak, $gizli) = $this->merged_data();
        $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $fil = isset($_GET['il']) ? sanitize_text_field(wp_unslash($_GET['il'])) : '';
        $fgr = isset($_GET['grup']) ? sanitize_text_field(wp_unslash($_GET['grup'])) : '';
        $fdr = isset($_GET['durum']) ? sanitize_key(wp_unslash($_GET['durum'])) : '';
        $sayfa = max(1, intval($_GET['paged'] ?? 1));
        $lq = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
        $liste = array();
        foreach ($by as $k => $o) {
            $k = (string) $k;
            $durum = in_array($k, $gizli, true) ? 'gizli' : $kaynak[$k];
            if ($fil && $o['il'] !== $fil) continue;
            if ($fgr && $o['grup'] !== $fgr) continue;
            if ($fdr && $fdr !== $durum) continue;
            if ($q !== '') {
                $hay = $k . ' ' . $o['ad'] . ' ' . $o['ilce'];
                $hay = function_exists('mb_strtolower') ? mb_strtolower($hay, 'UTF-8') : strtolower($hay);
                if (strpos($hay, $lq) === false) continue;
            }
            $o['_durum'] = $durum;
            $liste[] = $o;
        }
        usort($liste, function ($a, $b) { $c = strcmp($this->tr_key($a['il']), $this->tr_key($b['il'])); return $c ?: strcmp($this->tr_key($a['ad']), $this->tr_key($b['ad'])); });
        $top = count($liste); $per = 50; $sayfaN = max(1, (int) ceil($top / $per));
        $liste = array_slice($liste, ($sayfa - 1) * $per, $per);
        $this->admin_css();
        $url = admin_url('admin.php?page=lgstr-okullar');
        ?>
        <div class="wrap lgstr-adm">
            <h1 class="wp-heading-inline">Okullar</h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=lgstr-okul')); ?>" class="page-title-action">Yeni okul ekle</a>
            <hr class="wp-header-end">
            <?php $this->notice(); ?>
            <p class="description">Toplam <?php echo number_format_i18n(count($by)); ?> okul. Taban puanlar MEB e-Okul'dan alındı. Bir okulu düzenlediğinde ya da yeni okul eklediğinde değişiklik robota hemen yansır; eklenti güncellense de düzenlemelerin korunur.</p>
            <form method="get" class="lgstr-filter">
                <input type="hidden" name="page" value="lgstr-okullar">
                <input type="search" name="q" value="<?php echo esc_attr($q); ?>" placeholder="Okul adı, ilçe ya da kurum kodu">
                <select name="il"><option value="">Tüm iller</option><?php foreach (self::$ILLER as $il) echo '<option' . selected($fil, $il, false) . '>' . esc_html($il) . '</option>'; ?></select>
                <select name="grup"><option value="">Tüm okul türleri</option><?php foreach (self::$GRUPLAR as $g) echo '<option' . selected($fgr, $g, false) . '>' . esc_html($g) . '</option>'; ?></select>
                <select name="durum">
                    <option value="">Tüm durumlar</option>
                    <option value="duzenlendi" <?php selected($fdr, 'duzenlendi'); ?>>Düzenlenenler</option>
                    <option value="eklendi" <?php selected($fdr, 'eklendi'); ?>>Elle eklenenler</option>
                    <option value="gizli" <?php selected($fdr, 'gizli'); ?>>Gizlenenler</option>
                </select>
                <?php submit_button('Filtrele', 'secondary', '', false); ?>
                <span><?php echo number_format_i18n($top); ?> sonuç</span>
            </form>
            <table class="widefat striped">
                <thead><tr><th style="width:80px">Kurum kodu</th><th>Okul</th><th>İl / İlçe</th><th>Tür</th><th class="lgstr-num">Program</th><th class="lgstr-num">2026 taban</th><th class="lgstr-num">2025 taban</th><th>Durum</th><th style="width:210px">İşlem</th></tr></thead>
                <tbody>
                <?php if (!$liste): ?><tr><td colspan="9">Sonuç yok.</td></tr><?php endif; ?>
                <?php foreach ($liste as $o):
                    $k = (string) $o['k']; $p0 = $o['programlar'][0] ?? array();
                    $t26 = null; $t25 = null;
                    foreach ($o['programlar'] as $p) { if (isset($p['t26']) && $p['t26'] > (float) $t26) $t26 = $p['t26']; if (isset($p['t25']) && $p['t25'] > (float) $t25) $t25 = $p['t25']; }
                    $d = $o['_durum'];
                    $badge = array('temel' => array('', 'MEB verisi'), 'duzenlendi' => array('is-edit', 'Düzenlendi'), 'eklendi' => array('is-new', 'Elle eklendi'), 'gizli' => array('is-hide', 'Gizli'))[$d];
                    $islem = function ($act, $label, $confirm = '') use ($k) {
                        $u = wp_nonce_url(admin_url('admin-post.php?action=lgstr_okul_islem&islem=' . $act . '&k=' . rawurlencode($k)), 'lgstr_okul_' . $k);
                        return '<a href="' . esc_url($u) . '"' . ($confirm ? ' onclick="return confirm(\'' . esc_js($confirm) . '\')"' : '') . '>' . esc_html($label) . '</a>';
                    };
                    $acts = array('<a href="' . esc_url(admin_url('admin.php?page=lgstr-okul&k=' . rawurlencode($k))) . '"><strong>Düzenle</strong></a>');
                    $acts[] = $d === 'gizli' ? $islem('goster', 'Göster') : $islem('gizle', 'Gizle');
                    if ($kaynak[$k] === 'duzenlendi') $acts[] = $islem('geri', 'MEB verisine dön', 'Bu okuldaki düzenlemeler silinsin mi?');
                    if ($kaynak[$k] === 'eklendi') $acts[] = $islem('sil', 'Sil', 'Bu okul tamamen silinsin mi?');
                ?>
                    <tr class="<?php echo $d === 'gizli' ? 'is-hidden' : ''; ?>">
                        <td><?php echo esc_html($k); ?></td>
                        <td><strong><a href="<?php echo esc_url(admin_url('admin.php?page=lgstr-okul&k=' . rawurlencode($k))); ?>"><?php echo esc_html($o['ad']); ?></a></strong></td>
                        <td><?php echo esc_html($o['il'] . ' / ' . $o['ilce']); ?></td>
                        <td><?php echo esc_html($o['grup']); ?></td>
                        <td class="lgstr-num"><?php echo count($o['programlar']); ?></td>
                        <td class="lgstr-num"><?php echo $t26 !== null ? esc_html(number_format_i18n($t26, 3)) : '–'; ?></td>
                        <td class="lgstr-num"><?php echo $t25 !== null ? esc_html(number_format_i18n($t25, 3)) : '–'; ?></td>
                        <td><span class="lgstr-badge <?php echo esc_attr($badge[0]); ?>"><?php echo esc_html($badge[1]); ?></span></td>
                        <td><?php echo implode(' | ', $acts); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($sayfaN > 1): ?>
                <div class="tablenav"><div class="tablenav-pages"><?php
                    echo paginate_links(array('base' => add_query_arg('paged', '%#%'), 'format' => '', 'current' => $sayfa, 'total' => $sayfaN, 'prev_text' => '‹', 'next_text' => '›'));
                ?></div></div>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ---------- Okul ekle / düzenle ---------- */
    public function page_okul() {
        if (!current_user_can('manage_options')) return;
        list($base, $by, $kaynak) = $this->merged_data();
        $duz = $this->duzenler();
        $k = isset($_GET['k']) ? preg_replace('/\D/', '', wp_unslash($_GET['k'])) : '';
        $yeni = ($k === '' || !isset($by[$k]));
        $o = $yeni ? array('k' => '', 'ad' => '', 'il' => '', 'ilce' => '', 'tur' => '', 'grup' => 'Anadolu Lisesi', 'pans' => 0, 'programlar' => array(array('dil' => 'İngilizce'))) : $by[$k];
        $det = array();
        if (!$yeni) {
            if (isset($duz[$k]['detay'])) $det = $duz[$k]['detay'];
            else { $bd = $this->read_json($this->base_path('detay.json')); if ($bd && isset($bd[$k])) $det = $bd[$k]; }
        }
        $v = function ($a, $key) { return isset($a[$key]) ? $a[$key] : ''; };
        $num = function ($x) { return ($x === null || $x === '') ? '' : str_replace('.', ',', (string) $x); };
        $this->admin_css();
        ?>
        <div class="wrap lgstr-adm">
            <h1><?php echo $yeni ? 'Yeni okul ekle' : esc_html($o['ad']); ?></h1>
            <?php $this->notice(); ?>
            <?php if (!$yeni): ?><p class="description">Kurum kodu <?php echo esc_html($k); ?> · <?php echo $kaynak[$k] === 'temel' ? 'MEB e-Okul verisi. Kaydettiğinde bu okul için senin düzenlemen kullanılır.' : ($kaynak[$k] === 'duzenlendi' ? 'Bu okul düzenlendi. Listeden "MEB verisine dön" ile özgün veriye dönebilirsin.' : 'Bu okul elle eklendi.'); ?></p><?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('lgstr_okul_kaydet'); ?>
                <input type="hidden" name="action" value="lgstr_okul_kaydet">
                <input type="hidden" name="yeni" value="<?php echo $yeni ? 1 : 0; ?>">

                <div class="lgstr-box">
                    <h2>Okul</h2>
                    <div class="lgstr-grid2">
                        <div><label for="f-k">Kurum kodu</label><input type="text" id="f-k" name="k" value="<?php echo esc_attr($o['k']); ?>" inputmode="numeric" pattern="\d{3,9}" required <?php echo $yeni ? '' : 'readonly'; ?>><?php if ($yeni): ?><p class="description">MEB kurum kodu (yalnızca rakam).</p><?php endif; ?></div>
                        <div class="lgstr-wide"><label for="f-ad">Okul adı</label><input type="text" id="f-ad" name="ad" value="<?php echo esc_attr($o['ad']); ?>" required style="width:100%"></div>
                        <div><label for="f-il">İl</label><select id="f-il" name="il" required><option value="">Seç</option><?php foreach (self::$ILLER as $il) echo '<option' . selected($o['il'], $il, false) . '>' . esc_html($il) . '</option>'; ?></select></div>
                        <div><label for="f-ilce">İlçe</label><input type="text" id="f-ilce" name="ilce" value="<?php echo esc_attr($o['ilce']); ?>" required></div>
                        <div><label for="f-grup">Okul türü (filtre grubu)</label><select id="f-grup" name="grup"><?php foreach (self::$GRUPLAR as $g) echo '<option' . selected($o['grup'], $g, false) . '>' . esc_html($g) . '</option>'; ?></select></div>
                        <div><label for="f-tur">Tür adı (isteğe bağlı)</label><input type="text" id="f-tur" name="tur" value="<?php echo esc_attr($o['tur'] !== $o['grup'] ? $o['tur'] : ''); ?>" placeholder="Örn: Mesleki ve Teknik Anadolu Lisesi"></div>
                        <div><label><input type="checkbox" name="pans" value="1" <?php checked(!empty($o['pans'])); ?>> Pansiyonu var</label></div>
                        <div><label for="f-w">MEB okul sitesi</label><input type="text" id="f-w" name="w" value="<?php echo esc_attr(!empty($o['w']) ? $o['w'] . '.meb.k12.tr' : ''); ?>" placeholder="okuladi.meb.k12.tr"></div>
                    </div>
                </div>

                <div class="lgstr-box">
                    <h2>Programlar ve taban puanlar</h2>
                    <p class="description">Her satır robotta ayrı bir seçenek olarak listelenir. Puanları virgülle ya da noktayla yazabilirsin (örn. 482,357). Boş bıraktığın yıl gösterilmez.</p>
                    <table class="widefat lgstr-prog">
                        <thead><tr><th>Alan / program</th><th style="width:130px">Yabancı dil</th><th style="width:80px">Hazırlık</th><th style="width:110px">2026 taban</th><th style="width:110px">2025 taban</th><th style="width:110px">2024 taban</th><th style="width:110px">Yüzdelik (%)</th><th style="width:40px"></th></tr></thead>
                        <tbody id="lgstr-prog-rows">
                        <?php foreach ($o['programlar'] as $i => $p) $this->prog_row($i, $p, $num); ?>
                        </tbody>
                    </table>
                    <p><button type="button" class="button" id="lgstr-prog-add">+ Program ekle</button></p>
                    <script type="text/html" id="lgstr-prog-tpl"><?php $this->prog_row('__i__', array('dil' => 'İngilizce'), $num); ?></script>
                    <datalist id="lgstr-diller"><?php foreach (self::$DILLER as $d) echo '<option value="' . esc_attr($d) . '">'; ?></datalist>
                </div>

                <div class="lgstr-box">
                    <h2>Okul bilgileri</h2>
                    <p class="description">Boş bıraktığın alanlar robotta gösterilmez.</p>
                    <div class="lgstr-grid2">
                        <div class="lgstr-wide"><label for="d-adres">Adres</label><input type="text" id="d-adres" name="d[adres]" value="<?php echo esc_attr($v($det, 'adres')); ?>" style="width:100%"></div>
                        <div><label for="d-tel">Telefon</label><input type="text" id="d-tel" name="d[tel]" value="<?php echo esc_attr($v($det, 'tel')); ?>" placeholder="3224537373"></div>
                        <div><label for="d-web">Web sitesi</label><input type="text" id="d-web" name="d[web]" value="<?php echo esc_attr($v($det, 'web')); ?>" placeholder="okuladi.meb.k12.tr"></div>
                        <div><label for="d-eposta">E-posta</label><input type="email" id="d-eposta" name="d[eposta]" value="<?php echo esc_attr($v($det, 'eposta')); ?>" placeholder="<?php echo esc_attr(($o['k'] ?: 'kurumkodu') . '@meb.k12.tr'); ?>"></div>
                        <div><label>Pansiyon</label><label style="font-weight:400"><input type="checkbox" name="d[pansK]" value="1" <?php checked(!empty($det['pansK'])); ?>> Kız</label> <label style="font-weight:400"><input type="checkbox" name="d[pansE]" value="1" <?php checked(!empty($det['pansE'])); ?>> Erkek</label></div>
                        <div><label for="d-ogretim">Öğretim şekli</label><select id="d-ogretim" name="d[ogretim]"><option value="">–</option><?php foreach (array('Normal Eğitim', 'İkili Eğitim') as $x) echo '<option' . selected($v($det, 'ogretim'), $x, false) . '>' . esc_html($x) . '</option>'; ?></select></div>
                        <div><label for="d-bas">Ders başlangıç</label><input type="text" id="d-bas" name="d[bas]" value="<?php echo esc_attr($v($det, 'bas')); ?>" placeholder="08:30"></div>
                        <div><label for="d-bit">Ders bitiş</label><input type="text" id="d-bit" name="d[bit]" value="<?php echo esc_attr($v($det, 'bit')); ?>" placeholder="15:10"></div>
                        <div><label for="d-sure">Öğretim süresi</label><select id="d-sure" name="d[sure]"><option value="">–</option><?php foreach (array('4 yıl', '5 yıl (hazırlık sınıfı ile)') as $x) echo '<option' . selected($v($det, 'sure'), $x, false) . '>' . esc_html($x) . '</option>'; ?></select></div>
                        <div><label><input type="checkbox" name="d[proje]" value="1" <?php checked(!empty($det['proje'])); ?>> Proje okulu</label></div>
                        <div><label for="d-ogr">Öğrenci sayısı</label><input type="number" min="0" id="d-ogr" name="d[ogr]" value="<?php echo esc_attr($v($det, 'ogr')); ?>"></div>
                        <div><label for="d-ogrt">Öğretmen sayısı</label><input type="number" min="0" id="d-ogrt" name="d[ogrt]" value="<?php echo esc_attr($v($det, 'ogrt')); ?>"></div>
                        <div><label for="d-derslik">Derslik sayısı</label><input type="number" min="0" id="d-derslik" name="d[derslik]" value="<?php echo esc_attr($v($det, 'derslik')); ?>"></div>
                        <div class="lgstr-wide"><label for="d-olanak">Olanaklar</label><input type="text" id="d-olanak" name="d[olanak]" value="<?php echo esc_attr(implode(', ', (array) ($det['olanak'] ?? array()))); ?>" style="width:100%" placeholder="Kütüphane, Fizik laboratuvarı, Spor salonu"><p class="description">Virgülle ayır.</p></div>
                        <div><label for="d-lat">Enlem</label><input type="text" id="d-lat" name="d[lat]" value="<?php echo esc_attr($v($det, 'lat')); ?>" placeholder="37.0112"></div>
                        <div><label for="d-lng">Boylam</label><input type="text" id="d-lng" name="d[lng]" value="<?php echo esc_attr($v($det, 'lng')); ?>" placeholder="35.3269"></div>
                        <div class="lgstr-wide"><label for="d-aciklama">Okul hakkında</label><textarea id="d-aciklama" name="d[aciklama]" rows="4"><?php echo esc_textarea($v($det, 'aciklama')); ?></textarea><p class="description">Robotta okul detayında "Okul hakkında" başlığıyla görünür.</p></div>
                    </div>
                </div>
                <?php submit_button($yeni ? 'Okulu ekle' : 'Değişiklikleri kaydet'); ?>
            </form>
        </div>
        <script>
        (function () {
            var rows = document.getElementById('lgstr-prog-rows'), tpl = document.getElementById('lgstr-prog-tpl').innerHTML, n = rows.children.length + 100;
            document.getElementById('lgstr-prog-add').addEventListener('click', function () { rows.insertAdjacentHTML('beforeend', tpl.replace(/__i__/g, n++)); });
            rows.addEventListener('click', function (e) { var b = e.target.closest('.lgstr-prog-del'); if (!b) return; if (rows.children.length > 1) b.closest('tr').remove(); else alert('En az bir program olmalı.'); });
        })();
        </script>
        <?php
    }

    private function prog_row($i, $p, $num) {
        $n = 'p[' . $i . ']'; ?>
        <tr>
            <td><input type="text" name="<?php echo esc_attr($n); ?>[alan]" value="<?php echo esc_attr($p['alan'] ?? ''); ?>" placeholder="Boşsa okul türü yazılır"></td>
            <td><input type="text" name="<?php echo esc_attr($n); ?>[dil]" value="<?php echo esc_attr($p['dil'] ?? ''); ?>" list="lgstr-diller"></td>
            <td style="text-align:center"><input type="checkbox" name="<?php echo esc_attr($n); ?>[hz]" value="1" <?php checked(!empty($p['hz'])); ?>></td>
            <td><input type="text" inputmode="decimal" name="<?php echo esc_attr($n); ?>[t26]" value="<?php echo esc_attr($num($p['t26'] ?? '')); ?>"></td>
            <td><input type="text" inputmode="decimal" name="<?php echo esc_attr($n); ?>[t25]" value="<?php echo esc_attr($num($p['t25'] ?? '')); ?>"></td>
            <td><input type="text" inputmode="decimal" name="<?php echo esc_attr($n); ?>[t24]" value="<?php echo esc_attr($num($p['t24'] ?? '')); ?>"></td>
            <td><input type="text" inputmode="decimal" name="<?php echo esc_attr($n); ?>[y25]" value="<?php echo esc_attr($num($p['y25'] ?? '')); ?>"></td>
            <td><button type="button" class="button-link lgstr-prog-del" aria-label="Programı sil" title="Sil"><span class="dashicons dashicons-trash"></span></button></td>
        </tr>
    <?php }

    private function puan($s, $min, $max) {
        $s = trim((string) $s);
        if ($s === '') return null;
        $s = str_replace(' ', '', $s);
        if (strpos($s, ',') !== false) $s = str_replace(array('.', ','), array('', '.'), $s);
        if (!is_numeric($s)) return null;
        $f = (float) $s;
        return ($f >= $min && $f <= $max) ? round($f, 4) : null;
    }

    private function back($args, $err = false) {
        $u = add_query_arg(array_merge($args, array('lgstr_msg' => rawurlencode($args['lgstr_msg'] ?? ''))), admin_url('admin.php'));
        if ($err) $u = add_query_arg('lgstr_err', 1, $u);
        wp_safe_redirect($u); exit;
    }

    public function handle_okul_kaydet() {
        if (!current_user_can('manage_options') || !check_admin_referer('lgstr_okul_kaydet')) wp_die('Yetkiniz yok.');
        $P = wp_unslash($_POST);
        $k = preg_replace('/\D/', '', (string) ($P['k'] ?? ''));
        $yeni = !empty($P['yeni']);
        list($baseData, $by) = $this->merged_data();
        $baseList = $baseData['okullar'] ?? array();
        $ret = array('page' => 'lgstr-okul');
        if ($k === '') $this->back($ret + array('lgstr_msg' => 'Kurum kodu gerekli.'), true);
        if ($yeni && isset($by[$k])) $this->back($ret + array('k' => $k, 'lgstr_msg' => 'Bu kurum kodu zaten var; okul düzenleme sayfası açıldı.'), true);
        $ad = sanitize_text_field($P['ad'] ?? '');
        $il = sanitize_text_field($P['il'] ?? '');
        $ilce = sanitize_text_field($P['ilce'] ?? '');
        $grup = sanitize_text_field($P['grup'] ?? '');
        if (!in_array($grup, self::$GRUPLAR, true)) $grup = 'Diğer';
        if ($ad === '' || $il === '' || $ilce === '') $this->back($ret + ($yeni ? array() : array('k' => $k)) + array('lgstr_msg' => 'Okul adı, il ve ilçe zorunlu.'), true);
        $tur = sanitize_text_field($P['tur'] ?? '');
        $progs = array();
        foreach ((array) ($P['p'] ?? array()) as $p) {
            $r = array();
            $alan = sanitize_text_field($p['alan'] ?? ''); if ($alan !== '') $r['alan'] = $alan;
            $r['dil'] = sanitize_text_field($p['dil'] ?? '');
            if (!empty($p['hz'])) $r['hz'] = 1;
            $r['t26'] = $this->puan($p['t26'] ?? '', 100, 500);
            $r['t25'] = $this->puan($p['t25'] ?? '', 100, 500);
            $r['y25'] = $this->puan($p['y25'] ?? '', 0, 100);
            $r['t24'] = $this->puan($p['t24'] ?? '', 100, 500);
            if ($r['t26'] === null && $r['t25'] === null && $r['t24'] === null && empty($r['alan'])) continue;
            $progs[] = $r;
        }
        if (!$progs) $this->back($ret + ($yeni ? array() : array('k' => $k)) + array('lgstr_msg' => 'En az bir programa taban puan gir.'), true);

        $d = (array) ($P['d'] ?? array());
        $det = array();
        foreach (array('adres', 'tel', 'web', 'ogretim', 'bas', 'bit', 'sure') as $f) { $x = sanitize_text_field($d[$f] ?? ''); if ($x !== '') $det[$f] = $x; }
        if (!empty($det['tel'])) $det['tel'] = preg_replace('/\D/', '', preg_replace('/^\+?90|^0/', '', $det['tel']));
        if (!empty($det['web'])) $det['web'] = preg_replace('#^https?://#', '', esc_url_raw('https://' . preg_replace('#^https?://#', '', $det['web'])));
        $ep = sanitize_email($d['eposta'] ?? ''); if ($ep) $det['eposta'] = $ep;
        foreach (array('pansK', 'pansE', 'proje') as $f) if (!empty($d[$f])) $det[$f] = 1;
        foreach (array('ogr', 'ogrt', 'derslik') as $f) { $x = intval($d[$f] ?? 0); if ($x > 0) $det[$f] = $x; }
        $ol = array_values(array_filter(array_map('sanitize_text_field', array_map('trim', explode(',', (string) ($d['olanak'] ?? ''))))));
        if ($ol) $det['olanak'] = $ol;
        foreach (array('lat' => array(-90, 90), 'lng' => array(-180, 180)) as $f => $rg) {
            $x = str_replace(',', '.', trim((string) ($d[$f] ?? '')));
            if ($x !== '' && is_numeric($x) && $x >= $rg[0] && $x <= $rg[1]) $det[$f] = round((float) $x, 6);
        }
        $ac = sanitize_textarea_field($d['aciklama'] ?? ''); if ($ac !== '') $det['aciklama'] = $ac;
        $pans = !empty($P['pans']) || !empty($det['pansK']) || !empty($det['pansE']) ? 1 : 0;
        if ($pans) $det['pans'] = 1;

        $kayit = array('k' => (int) $k, 'ad' => $ad, 'il' => $il, 'ilce' => $ilce, 'tur' => $tur !== '' ? $tur : $grup, 'grup' => $grup, 'pans' => $pans, 'programlar' => $progs);
        $w = strtolower(preg_replace('/[^A-Za-z0-9\-]/', '', preg_replace('#^(https?://)?([^./]+)\.meb\.k12\.tr.*$#i', '$2', trim((string) ($P['w'] ?? '')))));
        if ($w !== '') $kayit['w'] = $w;
        if (!empty($by[$k]['y'])) $kayit['y'] = $by[$k]['y'];
        else foreach ($baseList as $bo) { if ((string) $bo['k'] === $k && !empty($bo['y'])) { $kayit['y'] = $bo['y']; break; } }
        if ($det) $kayit['detay'] = $det;
        $duz = $this->duzenler();
        $duz[$k] = $kayit;
        update_option(self::OPT_DUZEN, $duz, false);
        $this->rebuild();
        $this->back(array('page' => 'lgstr-okul', 'k' => $k, 'lgstr_msg' => $yeni ? 'Okul eklendi.' : 'Değişiklikler kaydedildi.'));
    }

    public function handle_okul_islem() {
        $k = preg_replace('/\D/', '', (string) wp_unslash($_GET['k'] ?? ''));
        if (!current_user_can('manage_options') || !check_admin_referer('lgstr_okul_' . $k)) wp_die('Yetkiniz yok.');
        $islem = sanitize_key($_GET['islem'] ?? '');
        $duz = $this->duzenler(); $gizli = $this->gizliler();
        $msg = '';
        if ($islem === 'gizle') { if (!in_array($k, $gizli, true)) $gizli[] = $k; $msg = 'Okul robotta gizlendi.'; }
        elseif ($islem === 'goster') { $gizli = array_values(array_diff($gizli, array($k))); $msg = 'Okul yeniden gösteriliyor.'; }
        elseif ($islem === 'geri' || $islem === 'sil') { unset($duz[$k]); if ($islem === 'sil') $gizli = array_values(array_diff($gizli, array($k))); $msg = $islem === 'sil' ? 'Okul silindi.' : 'Okul MEB verisine döndürüldü.'; }
        update_option(self::OPT_DUZEN, $duz, false);
        update_option(self::OPT_GIZLI, $gizli, false);
        $this->rebuild();
        $ref = wp_get_referer();
        $u = add_query_arg('lgstr_msg', rawurlencode($msg), $ref && strpos($ref, 'page=lgstr-okullar') !== false ? remove_query_arg(array('lgstr_msg', 'lgstr_err'), $ref) : admin_url('admin.php?page=lgstr-okullar'));
        wp_safe_redirect($u); exit;
    }

    /* ---------- Veri ve ayarlar ---------- */
    public function page_veri() {
        if (!current_user_can('manage_options')) return;
        $this->ensure_published();
        $pub = $this->read_json(file_exists($this->up_dir() . 'okullar.json') ? $this->up_dir() . 'okullar.json' : $this->base_path('okullar.json'));
        $meta = $pub['meta'] ?? array();
        $duzN = count($this->duzenler()); $gizN = count($this->gizliler());
        $this->admin_css();
        ?>
        <div class="wrap lgstr-adm">
            <h1>LGS Tercih Robotu: veri ve ayarlar</h1>
            <?php $this->notice(); ?>
            <div class="lgstr-box">
                <h2>Kullanım</h2>
                <p>Robotu bir sayfaya eklemek için sayfa içeriğine şu kısa kodu yaz: <code>[lgs_tercih_robotu]</code></p>
            </div>
            <div class="lgstr-box">
                <h2>Yayındaki veri</h2>
                <table class="widefat striped" style="max-width:720px"><tbody>
                    <tr><th>Okul sayısı</th><td><?php echo number_format_i18n(intval($meta['okulSayisi'] ?? 0)); ?></td></tr>
                    <tr><th>Program (okul + alan) sayısı</th><td><?php echo number_format_i18n(intval($meta['programSayisi'] ?? 0)); ?></td></tr>
                    <tr><th>Taban puan yılları</th><td><?php echo esc_html(($meta['tabanYili'] ?? '') . ', ' . ($meta['oncekiYil'] ?? '') . ', ' . ($meta['ikiOnceki'] ?? '')); ?></td></tr>
                    <tr><th>Kaynak</th><td><?php echo esc_html($meta['kaynak'] ?? ''); ?></td></tr>
                    <tr><th>Veri tarihi</th><td><?php echo esc_html($meta['olusturma'] ?? ''); ?></td></tr>
                    <tr><th>Senin düzenlemelerin</th><td><?php echo intval($duzN); ?> okul eklendi/düzenlendi, <?php echo intval($gizN); ?> okul gizlendi</td></tr>
                </tbody></table>
            </div>
            <div class="lgstr-box">
                <h2>Toplu veri yükle</h2>
                <p class="description">Yeni yılın verisi hazırlandığında <code>okullar.json</code> (ve varsa <code>detay.json</code>) dosyasını buradan yükle. Yüklenen dosya temel veri olur; okul ekleme ve düzenlemelerin bunun üstüne uygulanmaya devam eder.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field('lgstr_upload'); ?>
                    <input type="hidden" name="action" value="lgstr_upload">
                    <p><label>okullar.json <input type="file" name="okullar" accept=".json,application/json"></label></p>
                    <p><label>detay.json <input type="file" name="detay" accept=".json,application/json"></label></p>
                    <p><label><input type="checkbox" name="sifirla" value="1"> Yüklenen dosyaları sil, eklentiyle gelen veriye dön</label></p>
                    <?php submit_button('Kaydet', 'secondary'); ?>
                </form>
            </div>
        </div>
        <?php
    }

    public function handle_upload() {
        if (!current_user_can('manage_options') || !check_admin_referer('lgstr_upload')) wp_die('Yetkiniz yok.');
        $dir = $this->up_dir();
        wp_mkdir_p($dir);
        $done = array();
        if (!empty($_POST['sifirla'])) {
            foreach (array('okullar.json', 'detay.json') as $n) { if (file_exists($dir . 'base-' . $n)) { unlink($dir . 'base-' . $n); $done[] = $n . ' silindi'; } }
        }
        foreach (array('okullar' => 'okullar.json', 'detay' => 'detay.json') as $field => $name) {
            if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) continue;
            $raw = file_get_contents($_FILES[$field]['tmp_name']);
            $j = json_decode($raw, true);
            $ok = is_array($j) && ($field === 'detay' ? true : (isset($j['okullar']) && is_array($j['okullar'])));
            if (!$ok) { $done[] = $name . ' geçerli bir veri dosyası değil'; continue; }
            file_put_contents($dir . 'base-' . $name, $raw);
            $done[] = $name . ' yüklendi';
        }
        $this->rebuild();
        wp_safe_redirect(add_query_arg('lgstr_msg', rawurlencode($done ? implode(', ', $done) : 'Değişiklik yapılmadı'), admin_url('admin.php?page=lgstr-veri')));
        exit;
    }
}

new LGS_Tercih_Robotu();
