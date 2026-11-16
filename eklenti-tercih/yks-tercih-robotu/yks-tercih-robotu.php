<?php
/**
 * Plugin Name: YKS Puan Hesaplama ve Tercih Robotu (Soruindir)
 * Description: TYT, AYT ve YDT netlerinden TYT, SAY, EA, SÖZ ve DİL puanlarını, OBP'li yerleştirme puanını ve tahmini sıralamayı hesaplar; ÖSYM taban puanları ve YÖK Atlas başarı sıralarıyla tüm üniversite programlarını listeler ve 24 tercihlik liste oluşturur. Kısa kodlar: [yks_tercih_robotu] ve [yks_puan_hesaplama]
 * Version: 1.1.0
 * Author: Soruindir
 * Text Domain: yks-tercih-robotu
 */

if (!defined('ABSPATH')) exit;

define('YKSTR_VER', '1.1.0');
define('YKSTR_DIR', plugin_dir_path(__FILE__));
define('YKSTR_URL', plugin_dir_url(__FILE__));

class YKS_Tercih_Robotu {

    public function __construct() {
        add_shortcode('yks_tercih_robotu', array($this, 'sc_robot'));
        add_shortcode('yks_puan_hesaplama', array($this, 'sc_hesaplama'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_post_ykstr_upload', array($this, 'handle_upload'));
        add_filter('body_class', array($this, 'body_class'));
        add_action('wp_head', array($this, 'page_css'), 99);
        add_action('save_post_page', function () { delete_transient('sx_yks_robot_url'); });
    }

    /* ---------- sayfa düzeni ---------- */
    private function is_tool_page() {
        if (!is_singular()) return false;
        $p = get_post();
        return $p && (has_shortcode($p->post_content, 'yks_tercih_robotu') || has_shortcode($p->post_content, 'yks_puan_hesaplama'));
    }

    public function body_class($c) {
        if ($this->is_tool_page()) { $c[] = 'ykstr-fw'; $c[] = 'no-sidebar'; $c[] = 'full-width-content'; }
        return $c;
    }

    public function page_css() {
        if (!$this->is_tool_page()) return;
        echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600..800&family=Figtree:wght@400;500;600;700&display=swap">';
        echo '<style id="ykstr-fw">'
            . 'body.ykstr-fw #right-sidebar,body.ykstr-fw #left-sidebar,body.ykstr-fw .widget-area.sidebar,body.ykstr-fw aside.sidebar,body.ykstr-fw .sidebar-wrap,body.ykstr-fw .ozel-sag-sidebar{display:none!important}'
            . 'body.ykstr-fw #primary,body.ykstr-fw .content-area,body.ykstr-fw .site-main,body.ykstr-fw .main-content,body.ykstr-fw .col-8{width:100%!important;max-width:100%!important;flex:1 1 100%!important;float:none!important;margin-left:0!important;margin-right:0!important}'
            . 'body.ykstr-fw .site-content,body.ykstr-fw .ts-row,body.ykstr-fw .inside-article,body.ykstr-fw article.page{display:block!important}'
            . 'body.ykstr-fw .inside-article,body.ykstr-fw article.page,body.ykstr-fw .site-main>article,body.ykstr-fw .sayfa-kutu{padding:0!important;background:transparent!important;box-shadow:none!important;border:0!important;border-radius:0!important}'
            . 'body.ykstr-fw .entry-header,body.ykstr-fw .page-header,body.ykstr-fw .breadcrumbs,body.ykstr-fw .rank-math-breadcrumb,body.ykstr-fw .post-share,body.ykstr-fw .comments-area{display:none!important}'
            . 'body.ykstr-fw .entry-content{margin:0!important;padding:0!important;max-width:none!important}'
            . 'body.ykstr-fw #primary{padding-top:24px!important}'
            . 'body.ykstr-fw .entry-content>#ykstr-app{margin:0 auto}'
            . '</style>';
    }

    /* ---------- veri ---------- */
    private function up_dir() { $u = wp_upload_dir(); return trailingslashit($u['basedir']) . 'yks-tercih/'; }
    private function up_url() { $u = wp_upload_dir(); return trailingslashit($u['baseurl']) . 'yks-tercih/'; }

    private function data_path() {
        $c = $this->up_dir() . 'programlar.json';
        return file_exists($c) ? $c : YKSTR_DIR . 'data/programlar.json';
    }

    private function data_url() {
        $c = $this->up_dir() . 'programlar.json';
        if (file_exists($c)) return $this->up_url() . 'programlar.json?v=' . filemtime($c);
        $f = YKSTR_DIR . 'data/programlar.json';
        return YKSTR_URL . 'data/programlar.json?v=' . (file_exists($f) ? filemtime($f) : YKSTR_VER);
    }

    /** [yks_tercih_robotu] kısa kodunun olduğu yayımlanmış sayfa */
    public static function robot_url() {
        $u = get_transient('sx_yks_robot_url');
        if ($u === false) {
            $u = '';
            $q = get_posts(array('post_type' => 'page', 'post_status' => 'publish', 's' => 'yks_tercih_robotu', 'posts_per_page' => 5, 'fields' => 'ids', 'no_found_rows' => true));
            foreach ($q as $id) { if (has_shortcode(get_post_field('post_content', $id), 'yks_tercih_robotu')) { $u = get_permalink($id); break; } }
            set_transient('sx_yks_robot_url', $u, $u ? 6 * HOUR_IN_SECONDS : 10 * MINUTE_IN_SECONDS);
        }
        return $u;
    }

    /* ---------- kısa kodlar ---------- */
    private function render($mod, $atts) {
        $atts = shortcode_atts(array('baslik' => $mod === 'robot' ? 'YKS Tercih Robotu' : 'YKS Puan Hesaplama'), $atts);
        wp_enqueue_style('ykstr', YKSTR_URL . 'assets/app.css', array(), YKSTR_VER);
        wp_enqueue_script('ykstr', YKSTR_URL . 'assets/app.js', array(), YKSTR_VER, true);
        wp_localize_script('ykstr', 'YKSTR', array(
            'programlar' => $this->data_url(),
            'baslik'     => $atts['baslik'],
            'mod'        => $mod === 'robot' ? 'robot' : 'hesaplama',
            'robotUrl'   => $mod === 'robot' ? '' : self::robot_url(),
        ));
        return '<div id="ykstr-app" class="ykstr"><div class="ykstr-loading" aria-live="polite"><span></span>Araç hazırlanıyor…</div>' . $this->ssr($mod) . '</div>';
    }
    /** Arama motorları ve JavaScript'siz ziyaretçiler için HTML içerik; araç yüklenince yerini etkileşimli araç alır */
    private function ssr($mod) {
        if ($mod !== 'robot') {
            return '<section class="ykstr-ssr"><h2>YKS puan hesaplama</h2><p>TYT, AYT ve YDT netlerinizi girerek TYT, SAY, EA, SÖZ ve DİL ham ve yerleştirme puanlarınızı hesaplayın. Ortaöğretim başarı puanınız (OBP) yerleştirme puanına otomatik eklenir; puanınızla birlikte tahmini başarı sıralamanız da gösterilir.</p>'
                . '<h3>Puanlar nasıl hesaplanır?</h3><p>Her testte net sayısı, doğru sayısından yanlış sayısının dörtte biri çıkarılarak bulunur. Netler ÖSYM’nin ağırlıklarıyla çarpılıp taban puana eklenir; OBP’nin 0,12 katı (önceki yıl bir programa yerleşenlerde yarısı) yerleştirme puanına katılır. Hesaplama son YKS verilerine göre tahminidir; kesin sonuç ÖSYM sonuç belgesindedir.</p></section>';
        }
        $f = $this->data_path(); if (!file_exists($f)) return '';
        $k = 'ykstr_ssr_' . md5($f . '|' . filemtime($f) . '|' . YKSTR_VER);
        $h = get_transient($k); if ($h !== false) return $h;
        $d = json_decode(file_get_contents($f), true); if (!$d || empty($d['p'])) return '';
        $A = array_flip($d['alanlar']); $yil = isset($d['meta']['yil']) ? $d['meta']['yil'] : '';
        $g = array();
        foreach ($d['p'] as $r) {
            if (!empty($r[$A['onlisans']]) || empty($r[$A['sira']]) || !empty($r[$A['ogr']])) continue;
            $g[$r[$A['pt']]][] = $r;
        }
        $f2 = function ($x) { return $x ? number_format((float) $x, 3, ',', '.') : '–'; };
        ob_start(); ?>
<section class="ykstr-ssr">
<h2><?php echo esc_html($yil); ?> YKS taban puanları ve başarı sıralamaları</h2>
<p>YKS Tercih Robotu ile <?php echo (int) $d['meta']['universiteSayisi']; ?> üniversitedeki <?php echo number_format((int) $d['meta']['programSayisi'], 0, ',', '.'); ?> lisans ve ön lisans programının taban puanlarını ve başarı sıralamalarını inceleyebilir, netlerinizi girerek tercih listenizi oluşturabilirsiniz. Kaynak: <?php echo esc_html(isset($d['meta']['kaynak']) ? $d['meta']['kaynak'] : 'ÖSYM'); ?>.</p>
<?php foreach ($d['pt'] as $i => $pt) { if (empty($g[$i])) continue; usort($g[$i], function ($a, $b) use ($A) { return $a[$A['sira']] <=> $b[$A['sira']]; }); ?>
<h3><?php echo esc_html($pt); ?> puan türünde başarı sırası en yüksek 40 program</h3>
<table><tr><th>#</th><th>Üniversite</th><th>Program</th><th>Taban puan</th><th>Başarı sırası</th></tr>
<?php $n = 0; foreach (array_slice($g[$i], 0, 40) as $r) { $u = isset($d['unis'][$r[$A['u']]]) ? $d['unis'][$r[$A['u']]][0] : ''; echo '<tr><td>' . (++$n) . '</td><td>' . esc_html($u) . '</td><td>' . esc_html($r[$A['ad']]) . '</td><td>' . $f2($r[$A['min']]) . '</td><td>' . number_format((int) $r[$A['sira']], 0, ',', '.') . '</td></tr>'; } ?>
</table>
<?php } ?>
<h3>Programları listelenen üniversiteler</h3>
<p><?php echo esc_html(implode(', ', array_map(function ($u) { return $u[0]; }, $d['unis']))); ?></p>
</section>
<?php $h = ob_get_clean(); set_transient($k, $h, WEEK_IN_SECONDS); return $h;
    }
    public function sc_robot($atts) { return $this->render('robot', $atts); }
    public function sc_hesaplama($atts) { return $this->render('hesaplama', $atts); }

    /* ---------- yönetim ---------- */
    public function admin_menu() {
        add_menu_page('YKS Tercih Robotu', 'YKS Tercih', 'manage_options', 'ykstr', array($this, 'admin_page'), 'dashicons-welcome-learn-more', 59);
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) return;
        $j = json_decode((string) @file_get_contents($this->data_path()), true);
        $meta = is_array($j) && isset($j['meta']) ? $j['meta'] : array();
        $msg = isset($_GET['ykstr_msg']) ? sanitize_text_field(wp_unslash($_GET['ykstr_msg'])) : '';
        ?>
        <div class="wrap">
            <h1>YKS Puan Hesaplama ve Tercih Robotu</h1>
            <?php if ($msg): ?><div class="notice notice-info is-dismissible"><p><?php echo esc_html($msg); ?></p></div><?php endif; ?>
            <h2>Kullanım</h2>
            <p><code>[yks_tercih_robotu]</code> — puan hesaplama, program listesi ve tercih listesi bir arada (örneğin <em>/yks-tercih-robotu/</em> sayfası).</p>
            <p><code>[yks_puan_hesaplama]</code> — yalnızca puan hesaplama. Sonuç kartındaki düğme öğrenciyi tercih robotu sayfasına götürür (örneğin <em>/yks-puan-hesaplama/</em> sayfası).</p>
            <?php if ($meta): ?>
            <h2>Yüklü veri</h2>
            <table class="widefat striped" style="max-width:760px"><tbody>
                <tr><th>Yıl</th><td><?php echo esc_html($meta['yil'] ?? ''); ?></td></tr>
                <tr><th>Program sayısı</th><td><?php echo number_format_i18n(intval($meta['programSayisi'] ?? 0)); ?> (lisans <?php echo number_format_i18n(intval($meta['lisans'] ?? 0)); ?>, ön lisans <?php echo number_format_i18n(intval($meta['onlisans'] ?? 0)); ?>)</td></tr>
                <tr><th>Üniversite sayısı</th><td><?php echo number_format_i18n(intval($meta['universiteSayisi'] ?? 0)); ?></td></tr>
                <tr><th>YÖK Atlas başarı sırası olan program</th><td><?php echo number_format_i18n(intval($meta['resmiSira'] ?? 0)); ?></td></tr>
                <tr><th>Kaynak</th><td><?php echo esc_html($meta['kaynak'] ?? ''); ?></td></tr>
                <tr><th>Oluşturma</th><td><?php echo esc_html($meta['olusturma'] ?? ''); ?></td></tr>
            </tbody></table>
            <?php endif; ?>
            <h2>Veriyi güncelle</h2>
            <p>Yeni yılın verisi hazırlandığında <code>programlar.json</code> dosyasını buradan yükle. Yüklenen dosya eklentiyle gelenin yerine kullanılır; eklenti güncellense de silinmez.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('ykstr_upload'); ?>
                <input type="hidden" name="action" value="ykstr_upload">
                <p><input type="file" name="programlar" accept=".json,application/json"></p>
                <p><label><input type="checkbox" name="sifirla" value="1"> Yüklenen dosyayı sil, eklentiyle gelen veriye dön</label></p>
                <?php submit_button('Kaydet'); ?>
            </form>
        </div>
        <?php
    }

    public function handle_upload() {
        if (!current_user_can('manage_options') || !check_admin_referer('ykstr_upload')) wp_die('Yetkiniz yok.');
        $dir = $this->up_dir(); wp_mkdir_p($dir);
        $msg = 'Değişiklik yapılmadı';
        if (!empty($_POST['sifirla']) && file_exists($dir . 'programlar.json')) { unlink($dir . 'programlar.json'); $msg = 'Yüklenen dosya silindi'; }
        if (!empty($_FILES['programlar']['tmp_name']) && is_uploaded_file($_FILES['programlar']['tmp_name'])) {
            $raw = file_get_contents($_FILES['programlar']['tmp_name']);
            $j = json_decode($raw, true);
            if (is_array($j) && isset($j['p'], $j['model'], $j['unis'])) { file_put_contents($dir . 'programlar.json', $raw); $msg = 'programlar.json yüklendi'; }
            else $msg = 'Dosya geçerli bir veri dosyası değil';
        }
        wp_safe_redirect(add_query_arg('ykstr_msg', rawurlencode($msg), admin_url('admin.php?page=ykstr')));
        exit;
    }
}

new YKS_Tercih_Robotu();
