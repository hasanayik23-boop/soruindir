/* =====================================================================
 * V23 — Yazılı Hazırla: MEB senaryosuna göre yazılı kâğıdı
 *        (havuzdan + yapay zekâ + öğretmenin kendi soruları)
 * ===================================================================== */
if (!class_exists('OSC_Yazili') && class_exists('OSC_Havuz')) {
class OSC_Yazili {
    public function __construct() {
        add_action('init', array($this, 'tip_kaydet'));
        add_action('admin_init', array($this, 'sayfa_kur'));
        add_shortcode('osc_yazili_hazirla', array($this, 'kisa_kod'));
        foreach (array('hazirla', 'yz', 'degistir', 'kaydet', 'liste', 'getir', 'sil', 'havuza') as $a) add_action('wp_ajax_osc_yz_' . $a, array($this, 'ajax_' . $a));
        add_action('wp_ajax_osc_yz_konular', array($this, 'ajax_konular'));
        foreach (array('hazirla', 'degistir', 'konular', 'say', 'havuza', 'yz') as $a) add_action('wp_ajax_nopriv_osc_yz_' . $a, array($this, 'ajax_' . $a));
        add_action('wp_ajax_osc_yz_say', array($this, 'ajax_say'));
        add_action('template_redirect', array($this, 'kaynak_servis'), 0);
        add_action('template_redirect', array($this, 'yayin_kontrol'), 1);
        add_action('template_redirect', array($this, 'tam_sayfa'), 20);
        add_action('wp_footer', array($this, 'tanitim'), 50);
        add_shortcode('osc_yazili_tanitim', array($this, 'tanitim_kisa_kod'));
        add_action('admin_menu', array($this, 'ayar_menu'), 30);
        add_action('admin_init', array($this, 'ayar_kayit'));
    }

    /* ---------- adres, yapılandırma, kaynak dosyaları ---------- */
    /** Yazılı Hazırla herkese açık mı? Kapalıyken yalnızca yöneticiler görür ve dener */
    public static function yayinda() { return get_option('osc_yz_yayinda', '0') === '1'; }
    public static function gorebilir() { return self::yayinda() || current_user_can('manage_options'); }
    public function yayin_kontrol() {
        if (self::yayinda() || is_admin()) return;
        $id = (int) get_option('osc_yz_sayfa');
        if ($id && is_page($id)) {
            if (!current_user_can('manage_options')) { wp_safe_redirect(home_url('/')); exit; }
            add_action('wp_head', function () { echo '<meta name="robots" content="noindex,nofollow">' . "\n"; }, 1);
        }
    }
    public static function url() {
        $u = trim((string) get_option('osc_yz_url', ''));
        if ($u && strpos($u, 'yazilimatik.') === false) return $u;
        $id = (int) get_option('osc_yz_sayfa');
        return $id && get_post_status($id) === 'publish' ? get_permalink($id) : home_url('/yazili-hazirla/');
    }
    public static function cfg($gomulu = false) {
        $u = wp_get_current_user();
        return array('ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('osc_yz'), 'ad' => self::profil('ogretmen', $u->display_name), 'okul' => self::profil('okul', (string) get_user_meta($u->ID, 'osc_okul', true)), 'uye' => is_user_logged_in() ? 1 : 0, 'pnonce' => wp_create_nonce('osc_om'), 'logo' => OSC_Sinav::logo_src(), 'home' => home_url('/'), 'panel' => ($gomulu || !is_user_logged_in() || OSC_Sinav::rol() === 'ogrenci') ? '' : OSC_Sinav::sayfa_url('osc_sp_panel_sayfa'), 'yz' => (is_user_logged_in() && OSC_YZ_API::var_mi() && self::canli() !== 'kapali') ? 1 : 0, 'yzmod' => self::canli(), 'site' => preg_replace('#^https?://(www\.)?#', '', untrailingslashit(home_url())), 'gomulu' => $gomulu ? 1 : 0, 'kota' => is_user_logged_in() ? self::kota_durum() : null, 'yerel' => (is_user_logged_in() && OSC_Sinav::rol() !== 'ogrenci') ? 0 : 1, 'giris' => is_user_logged_in() ? '' : OSC_Sinav::sayfa_url('osc_sp_uye_sayfa', array('rol' => 'ogretmen', 'git' => self::url())));
    }
    public static function profil($k, $vars = '') { if (!is_user_logged_in()) return ''; $p = get_user_meta(get_current_user_id(), 'osc_om_profil', true); return is_array($p) && !empty($p[$k]) ? (string) $p[$k] : (string) $vars; }
    public static function surum() { return substr(md5((string) @filemtime(__FILE__) . '|' . DB_NAME . '|' . get_option('osc_senaryo_surum', '')), 0, 10); }
    /** Yazılı Hazırla'da canlı yapay zekâ: kapali | bos (yalnızca havuzda soru kalmayınca) | acik */
    public static function canli() { $v = get_option('osc_yz_canli', 'bos'); return in_array($v, array('kapali', 'bos', 'acik'), true) ? $v : 'bos'; }
    public static function kaynak_url($tur) { return add_query_arg(array('osc_yz_kaynak' => $tur, 'v' => self::surum()), home_url('/')); }
    public function kaynak_servis() {
        if (empty($_GET['osc_yz_kaynak'])) return;
        $tur = $_GET['osc_yz_kaynak'] === 'css' ? 'css' : 'js';
        header('Content-Type: ' . ($tur === 'css' ? 'text/css' : 'application/javascript') . '; charset=utf-8');
        header('Cache-Control: public, max-age=2592000'); header('X-Robots-Tag: noindex');
        if ($tur === 'css') self::css_cikti();
        else { echo 'window.OSC_SNR=' . wp_json_encode(OSC_Havuz::senaryolar()) . ";\nwindow.OSC_DIGER=" . self::diger_json() . ";\n"; self::js_cikti(); }
        exit;
    }
    public static function diger_json() { return '/*__DIGER_JSON__*/'; }
    public static function css_cikti() { ?>/*__YAZILI_CSS__*/<?php }
    public static function js_cikti() { ?>/*__YAZILI_JS__*/<?php }

    /* ---------- sitenin her yerinde tanıtım ---------- */
    public function ayar_menu() { add_submenu_page('osc-ogretmen-masasi', 'Yazılı Hazırla', 'Yazılı Hazırla', 'manage_options', 'osc-yazili-ayar', array($this, 'ayar_sayfa')); }
    public function ayar_kayit() {
        register_setting('osc_yazili', 'osc_yz_url', array('sanitize_callback' => 'esc_url_raw'));
        foreach (array('osc_yz_yayinda', 'osc_yz_bar', 'osc_yz_buton', 'osc_yz_alt', 'osc_yz_oto_onay') as $o) register_setting('osc_yazili', $o, array('sanitize_callback' => function ($v) { return $v ? '1' : '0'; }));
        register_setting('osc_yazili', 'osc_yz_gunluk', array('sanitize_callback' => 'absint'));
        register_setting('osc_yazili', 'osc_yz_toplam', array('sanitize_callback' => 'absint'));
        register_setting('osc_yazili', 'osc_yz_canli', array('sanitize_callback' => function ($v) { return in_array($v, array('kapali', 'bos', 'acik'), true) ? $v : 'bos'; }));
        foreach (array('osc_reklam_acik', 'osc_reklam_yonetici') as $o) register_setting('osc_yazili', $o, array('sanitize_callback' => function ($v) { return $v ? '1' : '0'; }));
        foreach (array_keys(self::reklam_alanlari()) as $ra) register_setting('osc_yazili', 'osc_reklam_' . $ra, array('sanitize_callback' => function ($v) { $v = trim((string) $v); return current_user_can('unfiltered_html') ? $v : wp_kses_post($v); }));
        register_setting('osc_yazili', 'osc_ai_saglayici', array('sanitize_callback' => function ($v) { return in_array($v, array('gemini', 'claude', 'openai'), true) ? $v : ''; }));
        foreach (array('osc_claude_key', 'osc_ai_api_key', 'osc_openai_key', 'osc_gemini_ucretsiz_key') as $o) register_setting('osc_yazili', $o, array('sanitize_callback' => function ($v) use ($o) {
            if (!empty($_POST[$o . '_sil'])) return '';
            $v = trim(preg_replace('/\s+/', '', (string) $v));
            if ($v === '' || preg_match('/^[•\*]+$/u', $v)) return (string) get_option($o, '');   // boş bırakıldı: kayıtlı anahtar korunur
            return sanitize_text_field($v);
        }));
        register_setting('osc_yazili', 'osc_claude_model', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('osc_yazili', 'osc_openai_model', array('sanitize_callback' => 'sanitize_text_field'));
    }
    private function acik($o) { return get_option($o, '1') !== '0'; }
    public function ayar_sayfa() {
        $sayfa = self::sayfa_url_wp(); ?>
<div class="wrap"><h1>Yazılı Hazırla</h1>
<p>Sitenin her sayfasında “Yazılı Hazırla” tanıtımının nasıl görüneceğini buradan yönetin. Öğretmen panelinde araç her zaman <b>📄 Yazılı hazırla</b> sekmesinde açılır.</p>
<form method="post" action="options.php"><?php settings_fields('osc_yazili'); ?>
<table class="form-table">
<tr><th>Yayın durumu</th><td><label><input type="checkbox" name="osc_yz_yayinda" value="1" <?php checked(self::yayinda()); ?>> <b>Yayında</b> — herkes kullanabilir, menüde, ana sayfada ve öğretmen panelinde görünür</label><p class="description"><?php echo self::yayinda() ? '✅ Şu an herkese açık.' : '🔒 Şu an gizli: yalnızca yöneticiler sayfayı açıp deneyebilir; menüde, ana sayfada, panelde ve tanıtım alanlarında görünmez, Google’a kapalıdır.'; ?><?php $sy = self::sayfa_url_wp(); if ($sy) echo ' Deneme adresi: <a href="' . esc_url($sy) . '" target="_blank">' . esc_html($sy) . '</a>'; ?></p></td></tr>
<tr><th>Tanıtım bağlantısı</th><td><input type="url" class="regular-text" name="osc_yz_url" value="<?php echo esc_attr(get_option('osc_yz_url', '')); ?>" placeholder="<?php echo esc_attr(home_url('/yazili-hazirla/')); ?>"><p class="description">Boş bırakılırsa sitedeki Yazılı Hazırla sayfası kullanılır.<?php if ($sayfa) echo ' Sitedeki sayfa: <a href="' . esc_url($sayfa) . '" target="_blank">' . esc_html($sayfa) . '</a>'; ?></p></td></tr>
<tr><th>Üst duyuru şeridi</th><td><label><input type="checkbox" name="osc_yz_bar" value="1" <?php checked($this->acik('osc_yz_bar')); ?>> Tüm sayfaların en üstünde ince duyuru şeridi (ziyaretçi kapatabilir)</label></td></tr>
<tr><th>Yüzen düğme</th><td><label><input type="checkbox" name="osc_yz_buton" value="1" <?php checked($this->acik('osc_yz_buton')); ?>> Sağ altta “Yazılı Hazırla” düğmesi (sınav çözülen sayfalarda ve öğrencilere gösterilmez)</label></td></tr>
<tr><th>Alt bilgi bandı</th><td><label><input type="checkbox" name="osc_yz_alt" value="1" <?php checked($this->acik('osc_yz_alt')); ?>> Sayfa altındaki (footer) bölümün hemen üstünde tanıtım bandı</label></td></tr>
<tr><th colspan="2"><h2 style="margin:10px 0 0">Yapay zekâ</h2></th></tr>
<tr><th>Sağlayıcı</th><td><?php $sg = get_option('osc_ai_saglayici', ''); ?><select name="osc_ai_saglayici"><option value="">Otomatik</option><option value="claude" <?php selected($sg, 'claude'); ?>>Anthropic Claude</option><option value="openai" <?php selected($sg, 'openai'); ?>>OpenAI ChatGPT</option><option value="gemini" <?php selected($sg, 'gemini'); ?>>Google Gemini</option></select> <span class="description">Kullanım sırası: <b><?php echo esc_html(implode(' → ', array_map(function ($x) { $m = array('claude' => 'Claude', 'openai' => 'ChatGPT', 'gemini' => 'Gemini (ücretli)', 'gemini_ucretsiz' => 'Gemini (ücretsiz)'); return isset($m[$x]) ? $m[$x] : $x; }, OSC_YZ_API::saglayicilar())) . (get_transient('osc_yz_ucretsiz_doldu') ? ' (ücretsiz kota şu an dolu, ücretliye geçildi)' : '') ?: 'yok'); ?></b>. Seçilen sağlayıcı hata verirse anahtarı olan diğer sağlayıcı otomatik denenir.</span></td></tr>
<?php foreach (array(
    array('osc_gemini_ucretsiz_key', '① Ücretsiz Gemini anahtarı (önce bu kullanılır)', 'AIza|AQ.', 'https://aistudio.google.com/app/apikey', 'aistudio.google.com → Get API key → Create API key (faturalandırması KAPALI yeni bir proje seçin)', 'Ücretsizdir; günlük ve dakikalık kotası dolunca sistem otomatik olarak aşağıdaki ücretli anahtarlara geçer, kota yenilenince yine ücretsize döner.'),
    array('osc_claude_key', 'Claude API anahtarı', 'sk-ant-', 'https://console.anthropic.com/settings/keys', 'console.anthropic.com → API Keys → Create Key', 'Ücret: console.anthropic.com → Billing (Plans & Billing) bölümünden kredi yüklenir.'),
    array('osc_openai_key', 'ChatGPT (OpenAI) API anahtarı', 'sk-', 'https://platform.openai.com/api-keys', 'platform.openai.com → API keys → Create new secret key', 'Ücret: platform.openai.com → Settings → Billing bölümünden kredi yüklenir. (ChatGPT Plus aboneliği API için geçerli değildir.)'),
    array('osc_ai_api_key', 'Gemini API anahtarı (ücretli)', 'AIza|AQ.', 'https://aistudio.google.com/app/apikey', 'aistudio.google.com → Get API key → Create API key', 'Ücret: Google AI Studio → Billing (Google Cloud faturalandırma hesabı) bölümünden açılır.'),
) as $an) { list($o, $ad, $on, $url, $yol, $ucret) = $an; $k = trim((string) get_option($o, '')); ?>
<tr><th><?php echo esc_html($ad); ?></th><td>
<?php if ($k) { $uyar = true; foreach (explode('|', $on) as $o1) if (strpos($k, $o1) === 0) $uyar = false; ?><p style="margin:0 0 6px"><?php echo $uyar ? '⚠️' : '✅'; ?> <b>Kayıtlı anahtar:</b> <code><?php echo esc_html(substr($k, 0, min(7, strlen($k))) . '…' . substr($k, -4)); ?></code> (<?php echo strlen($k); ?> karakter)<?php if ($uyar) echo ' — <b style="color:#b32d2e">Bu anahtar “' . esc_html(str_replace('|', '” ya da “', $on)) . '” ile başlamıyor; yanlış kopyalanmış olabilir (yine de kaydedildi ve kullanılıyor).</b>'; ?> <label style="margin-left:10px"><input type="checkbox" name="<?php echo esc_attr($o); ?>_sil" value="1"> Anahtarı sil</label></p><?php } ?>
<input type="password" class="regular-text" name="<?php echo esc_attr($o); ?>" value="" placeholder="<?php echo $k ? 'Değiştirmek için yeni anahtarı yapıştırın' : esc_attr(explode('|', $on)[0] . '...'); ?>" autocomplete="new-password">
<p class="description">Anahtar al: <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php echo esc_html($yol); ?></a>. <?php echo esc_html($ucret); ?> Kayıtlı anahtar güvenlik için gizlenir; kutuyu boş bırakırsanız kayıtlı anahtar korunur.</p>
</td></tr>
<?php } ?>
<tr><th>Model</th><td><input type="text" name="osc_claude_model" value="<?php echo esc_attr(get_option('osc_claude_model', '')); ?>" placeholder="claude-haiku-5-5"> Claude &nbsp; <input type="text" name="osc_openai_model" value="<?php echo esc_attr(get_option('osc_openai_model', '')); ?>" placeholder="gpt-5-mini"> ChatGPT<p class="description">Boş bırakılırsa ekonomik modeller kullanılır (Claude: <code>claude-haiku-5-5</code>, ChatGPT: <code>gpt-5-mini</code>).</p></td></tr>
<tr><th>Yapay zekâ soruları</th><td><label><input type="checkbox" name="osc_yz_oto_onay" value="1" <?php checked($this->acik('osc_yz_oto_onay')); ?>> Yapay zekânın yazdığı sorular soru havuzuna <b>onaylı</b> eklensin (sonraki yazılılarda da kullanılır)</label></td></tr>
<tr><th>Yazılı hazırlarken yapay zekâ</th><td><?php $cn = self::canli(); foreach (array('kapali' => '<b>Kapalı</b> — sorular yalnızca soru havuzundan gelir; havuzda kalmayan soruyu öğretmen kendisi yazar (önerilen: havuzu “Soru Havuzu → 🤖 Yapay zekâ ile doldur” ile doldurduktan sonra)', 'bos' => '<b>Yalnızca havuzda soru kalmayınca</b> — önce havuz, eksikleri yapay zekâ tamamlar (öğretmen hakkından düşer)', 'acik' => '<b>Açık</b> — öğretmen “Yapay zekâ ile özgün” seçeneğiyle tüm soruları yapay zekâya yazdırabilir') as $k => $e) echo '<label style="display:block;margin:4px 0"><input type="radio" name="osc_yz_canli" value="' . $k . '" ' . checked($cn, $k, false) . '> ' . $e . '</label>'; ?></td></tr>
<tr><th>Öğretmen başına günlük hak</th><td><input type="number" min="5" max="2000" name="osc_yz_gunluk" value="<?php echo esc_attr(get_option('osc_yz_gunluk', 40)); ?>" class="small-text"> yapay zekâ sorusu / gün &nbsp; · &nbsp; Sitenin toplam günlük sınırı: <input type="number" min="50" max="100000" name="osc_yz_toplam" value="<?php echo esc_attr(get_option('osc_yz_toplam', 3000)); ?>" class="small-text"> soru<p class="description">Arşivden gelen sorular haktan düşmez; yalnızca yapay zekânın yazdığı sorular sayılır. Yöneticiler sınırsızdır. Yazılı Hazırla yalnızca giriş yapmış öğretmenlere açıktır.</p></td></tr>
<tr><th colspan="2"><h2 style="margin:10px 0 0">Reklamlar</h2><p class="description" style="font-weight:400">AdSense (ya da başka) reklam kodunu ilgili kutuya olduğu gibi yapıştırın. Boş bırakılan alan gösterilmez. Reklamlar Yazılı Hazırla sayfasında görünür (yalnızca giriş yapmış öğretmenler sayfayı kullanabildiği için reklamları da onlar görür).</p></th></tr>
<tr><th>Reklamlar</th><td><label><input type="checkbox" name="osc_reklam_acik" value="1" <?php checked(get_option('osc_reklam_acik', '1') !== '0'); ?>> Reklamları göster</label> &nbsp; <label><input type="checkbox" name="osc_reklam_yonetici" value="1" <?php checked(get_option('osc_reklam_yonetici', '0') === '1'); ?>> Yöneticilere de göster (kontrol için)</label></td></tr>
<?php foreach (self::reklam_alanlari() as $ra => $rad) { ?><tr><th><?php echo esc_html($rad); ?></th><td><textarea name="osc_reklam_<?php echo esc_attr($ra); ?>" rows="4" class="large-text code" placeholder="&lt;script async src=&quot;https://pagead2.googlesyndication.com/...&quot;&gt;&lt;/script&gt; ..."><?php echo esc_textarea(get_option('osc_reklam_' . $ra, '')); ?></textarea></td></tr><?php } ?>
</table><?php submit_button(); ?></form>
<h2>Kısa kod</h2><p>Herhangi bir sayfaya büyük tanıtım kartı eklemek için: <code>[osc_yazili_tanitim]</code></p></div>
<?php }
    private static function sayfa_url_wp() { $id = (int) get_option('osc_yz_sayfa'); return $id && get_post_status($id) === 'publish' ? get_permalink($id) : ''; }
    private function tanitim_gizli() {
        if (is_admin() || wp_doing_ajax() || is_feed() || is_embed()) return true;
        if (isset($_GET['osc_embed']) || isset($_GET['osc_k'])) return true;
        global $post;
        if (is_singular() && $post && (has_shortcode($post->post_content, 'osc_yazili_hazirla') || has_shortcode($post->post_content, 'osc_ogretmen_paneli') || has_shortcode($post->post_content, 'osc_quiz'))) return true;
        foreach (array('osc_sp_panel_sayfa', 'osc_sp_katil_sayfa', 'osc_sp_ogrenci_sayfa', 'osc_yz_sayfa') as $o) { $id = (int) get_option($o); if ($id && is_page($id)) return true; }
        return false;
    }
    public function tanitim() {
        if (!self::yayinda() || $this->tanitim_gizli()) return;
        $ogr = is_user_logged_in() && OSC_Sinav::rol() === 'ogrenci';
        if ($ogr) return;
        $u = esc_url(self::url());
        $bar = $this->acik('osc_yz_bar'); $btn = $this->acik('osc_yz_buton'); $alt = $this->acik('osc_yz_alt');
        if (!$bar && !$btn && !$alt) return; ?>
<style>
.osc-yzt-bar{position:relative;z-index:99990;display:flex;align-items:center;justify-content:center;gap:12px;padding:9px 44px 9px 16px;background:linear-gradient(90deg,#1e1b4b,#4c1d95 50%,#1e1b4b);color:#fff;font:600 14px/1.35 "Plus Jakarta Sans",system-ui,-apple-system,"Segoe UI",sans-serif;text-align:center}
.osc-yzt-bar a{color:#fff!important;text-decoration:none!important;display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:center}
.osc-yzt-bar b{font-weight:800}
.osc-yzt-bar .y{font-size:10px;font-weight:800;letter-spacing:.06em;background:linear-gradient(135deg,#5b5bf6,#c026d3);border-radius:99px;padding:3px 7px}
.osc-yzt-bar .g{background:#fff;color:#1e1b4b;border-radius:9px;padding:4px 10px;font-weight:800;font-size:13px}
.osc-yzt-bar button{position:absolute;right:8px;top:50%;transform:translateY(-50%);width:28px;height:28px;border:0;border-radius:8px;background:rgba(255,255,255,.12);color:#fff;font-size:16px;line-height:1;cursor:pointer;padding:0}
.osc-yzt-fab{position:fixed;right:18px;bottom:18px;z-index:99980;display:flex;align-items:center;gap:10px;background:linear-gradient(135deg,#5b5bf6,#7c3aed 55%,#c026d3);color:#fff!important;text-decoration:none!important;font:800 14.5px/1 "Plus Jakarta Sans",system-ui,-apple-system,"Segoe UI",sans-serif;padding:13px 18px 13px 14px;border-radius:99px;box-shadow:0 18px 34px -12px rgba(124,58,237,.75);transition:transform .2s,box-shadow .2s;animation:oscYztGir .5s .8s both}
.osc-yzt-fab:hover{transform:translateY(-3px);box-shadow:0 24px 40px -14px rgba(124,58,237,.85)}
.osc-yzt-fab i{font-style:normal;width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-size:16px}
.osc-yzt-fab em{font-style:normal;position:absolute;top:-7px;right:10px;font-size:9.5px;letter-spacing:.06em;background:#fff;color:#7c3aed;border-radius:99px;padding:2px 6px;box-shadow:0 4px 10px -4px rgba(0,0,0,.4)}
@keyframes oscYztGir{from{opacity:0;transform:translateY(20px) scale(.9)}to{opacity:1;transform:none}}
.osc-yzt-alt{max-width:1180px;margin:34px auto;padding:0 16px;box-sizing:border-box}
.osc-yzt-alt a{display:flex;align-items:center;gap:20px;flex-wrap:wrap;border-radius:24px;padding:22px 26px;color:#fff!important;text-decoration:none!important;background:radial-gradient(500px 200px at 95% -30%,rgba(192,38,211,.5),transparent 60%),linear-gradient(135deg,#0b1024,#1e1b4b 60%,#312e81);box-shadow:0 26px 50px -34px rgba(30,27,75,.9);font-family:"Plus Jakarta Sans",system-ui,-apple-system,"Segoe UI",sans-serif;transition:transform .2s}
.osc-yzt-alt a:hover{transform:translateY(-2px)}
.osc-yzt-alt .ik{flex:none;width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,#5b5bf6,#c026d3);display:flex;align-items:center;justify-content:center;font-size:26px}
.osc-yzt-alt .yz{flex:1;min-width:220px}
.osc-yzt-alt b{display:block;font-size:19px;font-weight:800;letter-spacing:-.01em}
.osc-yzt-alt small{display:block;color:#cbd5e1;font-size:14px;margin-top:3px}
.osc-yzt-alt .g{background:#fff;color:#1e1b4b;font-weight:800;font-size:14.5px;border-radius:14px;padding:12px 18px;white-space:nowrap}
@media (max-width:640px){.osc-yzt-bar{font-size:13px}.osc-yzt-bar .uz{display:none}.osc-yzt-fab{right:12px;bottom:12px;padding:11px 15px 11px 11px;font-size:13.5px}}
@media print{.osc-yzt-bar,.osc-yzt-fab,.osc-yzt-alt{display:none!important}}
</style>
<?php if ($bar): ?><div class="osc-yzt-bar" id="osc-yzt-bar" hidden><a href="<?php echo $u; ?>"><span class="y">YENİ</span><span><b>Yazılı Hazırla</b><span class="uz"> · MEB 2026-2027 senaryolarına uygun yazılıyı bir dakikada hazırlayın</span></span><span class="g">Hemen dene →</span></a><button type="button" aria-label="Kapat">×</button></div><?php endif; ?>
<?php if ($btn): ?><a class="osc-yzt-fab" href="<?php echo $u; ?>"><i>📄</i>Yazılı Hazırla<em>YENİ</em></a><?php endif; ?>
<?php if ($alt): ?><div class="osc-yzt-alt" id="osc-yzt-alt" hidden><a href="<?php echo $u; ?>"><span class="ik">📄</span><span class="yz"><b>Yazılınızı bir dakikada hazırlayın</b><small>MEB 2026-2027 senaryolarına uygun · 5–12. sınıf, tüm dersler · cevap anahtarlı Word ve PDF</small></span><span class="g">Yazılı Hazırla →</span></a></div><?php endif; ?>
<script>(function(){try{var b=document.getElementById('osc-yzt-bar');if(b){var k=0;try{k=localStorage.getItem('osc_yzt_kapali')==='1';}catch(e){}if(!k){document.body.insertBefore(b,document.body.firstChild);b.hidden=false;}b.querySelector('button').onclick=function(){b.remove();try{localStorage.setItem('osc_yzt_kapali','1');}catch(e){}};}
var a=document.getElementById('osc-yzt-alt');if(a){var f=document.querySelector('footer.site-footer,#colophon,footer#footer,.site-footer,body>footer,.main-wrap>footer,footer');if(f&&f.parentNode){f.parentNode.insertBefore(a,f);}a.hidden=false;}}catch(e){}})();</script>
<?php }
    public function tanitim_kisa_kod() {
        if (!self::yayinda()) return '';
        $u = esc_url(self::url());
        return '<a class="osc-yzt-kart" href="' . $u . '" style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;border-radius:24px;padding:24px 26px;color:#fff;text-decoration:none;background:radial-gradient(500px 200px at 95% -30%,rgba(192,38,211,.5),transparent 60%),linear-gradient(135deg,#0b1024,#1e1b4b 60%,#312e81);font-family:inherit;margin:20px 0"><span style="flex:none;width:58px;height:58px;border-radius:16px;background:linear-gradient(135deg,#5b5bf6,#c026d3);display:flex;align-items:center;justify-content:center;font-size:28px">📄</span><span style="flex:1;min-width:220px"><b style="display:block;font-size:20px">Yazılınızı bir dakikada hazırlayın</b><small style="display:block;color:#cbd5e1;font-size:14px;margin-top:4px">MEB 2026-2027 senaryolarına uygun · 5–12. sınıf, tüm dersler · cevap anahtarlı Word ve PDF</small></span><span style="background:#fff;color:#1e1b4b;font-weight:800;border-radius:14px;padding:12px 18px">Yazılı Hazırla →</span></a>';
    }
    public function tip_kaydet() {
        register_post_type('osc_yazili', array('public' => false, 'show_ui' => false, 'label' => 'Yazılılar', 'supports' => array('title', 'author')));
    }
    public function sayfa_kur() {
        if (!current_user_can('manage_options') || get_option('osc_yz_kur') === '2') return;
        $id = (int) get_option('osc_yz_sayfa');
        // sayfa çöpte/taslakta kaldıysa ya da kısa kodu yoksa onar
        if ($id && ($p = get_post($id))) {
            if ($p->post_status !== 'publish' || strpos($p->post_content, '[osc_yazili_hazirla') === false) wp_update_post(array('ID' => $id, 'post_status' => 'publish', 'post_content' => strpos($p->post_content, '[osc_yazili_hazirla') === false ? '[osc_yazili_hazirla]' . "\n" . $p->post_content : $p->post_content));
        }
        if ($id && get_post_status($id) !== 'publish') $id = 0;
        if (!$id) {
            $var = get_page_by_path('yazili-hazirla');
            $id = $var ? $var->ID : wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Yazılı Hazırla', 'post_name' => 'yazili-hazirla', 'post_content' => '[osc_yazili_hazirla]'));
            if ($var && $var->post_status !== 'publish') wp_update_post(array('ID' => $var->ID, 'post_status' => 'publish', 'post_content' => '[osc_yazili_hazirla]'));
            if ($id && !is_wp_error($id)) update_option('osc_yz_sayfa', $id);
        }
        update_option('osc_yz_kur', '2');
    }
    private function ogretmen_mi() { return is_user_logged_in() && OSC_Sinav::rol() !== 'ogrenci'; }
    private function kontrol() {
        if (!$this->ogretmen_mi()) wp_send_json_error('Öğretmen hesabıyla giriş yapmalısınız.', 403);
        check_ajax_referer('osc_yz', 'nonce');
    }
    /** herkese açık işlemler (soru hazırlama): giriş gerekmez */
    private function kontrol_acik() {
        if (!wp_verify_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '', 'osc_yz')) wp_send_json_error('Oturum süresi doldu. Sayfayı yenileyin.', 403);
        if (!is_user_logged_in()) {   // ziyaretçi: IP başına saatlik sınır (kötüye kullanıma karşı)
            $k = 'osc_yz_zy_' . md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') . '_' . gmdate('YmdH'); $n = (int) get_transient($k);
            if ($n > 150) wp_send_json_error('Çok fazla istek gönderildi. Biraz sonra tekrar deneyin ya da ücretsiz üye olun.', 429);
            set_transient($k, $n + 1, HOUR_IN_SECONDS);
        }
    }
    /** MEB senaryo tablosu ya da öğretmenin kendi kurduğu (diğer dersler) tablo */
    private function tablo_al($d) {
        $sinif = (int) (isset($d['sinif']) ? $d['sinif'] : 0); $ders = isset($d['ders']) ? (string) $d['ders'] : '';
        $t = $this->tablo($sinif, $ders, (int) (isset($d['donem']) ? $d['donem'] : 0)); if ($t) { $this->cur = $t; return $t; }
        $o = isset($d['ozel_tablo']) && is_array($d['ozel_tablo']) ? $d['ozel_tablo'] : null;
        if (!$o || $sinif < 1 || $sinif > 12) return null;
        $ders = mb_substr(trim(sanitize_text_field($ders)), 0, 90); if ($ders === '') return null;
        $sat = array();
        foreach (array_slice(isset($o['satirlar']) && is_array($o['satirlar']) ? $o['satirlar'] : array(), 0, 25) as $x) {
            if (!is_array($x)) continue;
            $kod = isset($x['kod']) ? (string) $x['kod'] : ''; $konu = mb_substr(trim(sanitize_text_field(isset($x['konu']) ? $x['konu'] : '')), 0, 200);
            if (!preg_match('/^oz-[a-z0-9]{1,16}$/', $kod) || $konu === '') continue;
            $sat[] = array('kod' => $kod, 'konu' => $konu, 'cikti' => $konu, 'sayi' => array());
        }
        if (!$sat) return null;
        $this->cur = null;
        $alan = mb_substr(sanitize_text_field(isset($o['alan']) ? $o['alan'] : ''), 0, 90);
        return array('sinif' => $sinif, 'ders' => $ders, 'satirlar' => $sat, 'ozel' => 1, 'alan' => $alan);
    }
    /** Diğer dersler: havuzda bu derse ait kaç soru var */
    public function ajax_say() {
        $this->kontrol_acik(); $d = $this->girdi(); global $wpdb; $t = OSC_Havuz::tablo();
        $ders = sanitize_text_field(isset($d['ders']) ? $d['ders'] : ''); $sinif = (int) (isset($d['sinif']) ? $d['sinif'] : 0);
        $n = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE sinif = %d AND ders = %s AND durum = 'onayli'", $sinif, $ders));
        wp_send_json_success(array('n' => $n));
    }
    /** Diğer dersler: yapay zekâ ile ünite/konu önerisi */
    public function ajax_konular() {
        $this->kontrol_acik(); $d = $this->girdi();
        $ders = mb_substr(sanitize_text_field(isset($d['ders']) ? $d['ders'] : ''), 0, 90); $sinif = (int) (isset($d['sinif']) ? $d['sinif'] : 0); $alan = mb_substr(sanitize_text_field(isset($d['alan']) ? $d['alan'] : ''), 0, 90);
        if ($ders === '' || $sinif < 1) wp_send_json_error('Ders seçin.');
        $ck = 'osc_yz_konu_' . md5($ders . '|' . $sinif . '|' . $alan); $c = get_transient($ck); if (is_array($c)) wp_send_json_success($c);
        if (!OSC_YZ_API::var_mi()) wp_send_json_error('Yapay zekâ tanımlı değil.');
        if (!$this->yz_kota(1)) wp_send_json_error('Bugünlük yapay zekâ hakkınız doldu.');
        $prompt = "Türkiye'de MEB {$sinif}. sınıf \"{$ders}\" dersi" . ($alan ? " ({$alan})" : '') . " öğretim programının ünitelerini/konularını, öğretim yılı sırasıyla listele. Yazılı sınavda soru sorulabilecek 6-12 konu yaz; her biri kısa (en çok 80 karakter) olsun. 'donem' alanına konunun genellikle işlendiği dönemi (1 ya da 2) yaz. Yalnızca JSON döndür: {\"konular\":[{\"konu\":\"\",\"donem\":1}]}";
        OSC_YZ_API::$sicaklik = 0.3;
        $r = OSC_YZ_API::json($prompt, 1500, 40);
        if (is_string($r)) wp_send_json_error($r);
        if (isset($r['konular'])) $r = $r['konular'];
        $o = array();
        foreach ((array) $r as $x) { $k = is_array($x) ? (isset($x['konu']) ? $x['konu'] : '') : $x; $k = mb_substr(trim(sanitize_text_field((string) $k)), 0, 120); if ($k !== '') $o[] = array('konu' => $k, 'donem' => is_array($x) && isset($x['donem']) ? max(1, min(2, (int) $x['donem'])) : 1); }
        if (!$o) wp_send_json_error('Konu önerisi alınamadı, konuları kendiniz yazabilirsiniz.');
        set_transient($ck, $o, 30 * DAY_IN_SECONDS);
        wp_send_json_success($o);
    }
    private function girdi() {
        $d = json_decode(wp_unslash(isset($_POST['veri']) ? $_POST['veri'] : '{}'), true);
        return is_array($d) ? $d : array();
    }
    private function tablo($sinif, $ders, $donem = 0) { return OSC_Havuz::tablo_bul($sinif, $ders, $donem ? $donem : 0); }
    private function satir($t, $kod) {
        foreach ($t['satirlar'] as $x) if (($x['kod'] ? $x['kod'] : mb_substr($x['cikti'], 0, 38)) === $kod) return $x;
        return null;
    }

    /* ---------- zorluk tahmini (1 kolay, 2 orta, 3 zor) ---------- */
    public static function zorluk($s) {
        $metin = wp_strip_all_tags((isset($s['paragraph']) ? $s['paragraph'] : '') . ' ' . (isset($s['root']) ? $s['root'] : ''));
        if (!empty($s['zorluk'])) return max(1, min(3, (int) $s['zorluk']));
        $p = 0; $L = mb_strlen($metin);
        if (isset($s['type']) && $s['type'] === 'classic') $p++;
        if ($L > 260) $p++;
        if ($L > 600) $p++;
        if (preg_match('/(açıklayınız|yorumlayınız|karşılaştırınız|değerlendiriniz|nedenini|gerekçe|ilişki|analiz)/iu', $metin)) $p++;
        if (isset($s['type']) && in_array($s['type'], array('truefalse', 'gap'), true)) $p--;
        return $p <= 0 ? 1 : ($p === 1 ? 2 : 3);
    }
    private function tur_uygun($s, $tur) {
        $t = isset($s['type']) ? $s['type'] : 'classic';
        $metin = (isset($s['paragraph']) ? $s['paragraph'] : '') . ' ' . (isset($s['root']) ? $s['root'] : '');
        if (strpos($metin, 'okunamadı') !== false) return false;                       // eksik formül/görsel
        $duz = mb_strtolower(wp_strip_all_tags($metin), 'UTF-8');
        if (preg_match('/kazanım\s*:|puandır|tüm sorular|her soru \d/u', $duz)) return false;   // kâğıt yönergesi karışmış
        if ($tur === 'acik' && (substr_count($duz, '(…') + substr_count($duz, '(...') + substr_count($duz, '( )') >= 2)) return false; // D/Y listesi
        $sik = 0; foreach ((array) (isset($s['options']) ? $s['options'] : array()) as $k => $v) if (trim(wp_strip_all_tags((string) $v)) !== '' || strpos((string) $v, '<img') !== false) $sik++;
        $gizli_sik = preg_match_all('/(^|<br>|\s|>)[A-E]\s*[\)\.\-]\s*\S/u', $metin) >= 3;      // şıkları metne karışmış test sorusu
        if (preg_match('/hangisi|hangileri|aşağıdakiler(den|in)/u', $duz) && (preg_match_all('/(^|<br>|\s|>)[A-Ea-e]\s*[\)\.\-]/u', $metin) >= 2 || $sik >= 2)) $gizli_sik = true;
        if (preg_match('/(^|<br>|\n)\s*\d{1,2}\s*[\)\.\-]\s*\p{Lu}/u', isset($s['root']) ? $s['root'] : '') && preg_match_all('/(^|<br>|\n)\s*\d{1,2}\s*[\)\.]\s*\p{Lu}/u', $metin) >= 1 && mb_strlen($duz) > 120) return false; // içine başka soru karışmış
        if (preg_match('#https?://|www\.|\.(com|net|org)\b#iu', $metin)) return false;
        if ($tur === 'acik') return $t !== 'multiple' && $t !== 'truefalse' && !$gizli_sik;
        if ($tur === 'test') return $t === 'multiple' && $sik >= 3;
        return $t !== 'multiple' || $sik >= 3;
    }
    /** aynı/çok benzer soruyu bir yazılıda iki kez kullanmamak için anahtar */
    public static function benzerlik_anahtari($s) {
        $t = mb_strtolower(html_entity_decode(wp_strip_all_tags((isset($s['paragraph']) ? $s['paragraph'] : '') . ' ' . (isset($s['root']) ? $s['root'] : '')), ENT_QUOTES, 'UTF-8'), 'UTF-8');
        $t = preg_replace('/[^\p{L}\p{N}]+/u', '', $t);
        return mb_substr($t, 0, 140);
    }
    /** Havuzdan bir çıktı için soru seç */
    private function havuzdan($sinif, $ders, $kod, $adet, $haric, $zorluk, $tur, $benim = array()) {
        global $wpdb; $t = OSC_Havuz::tablo();
        $haric = array_values(array_filter(array_map('intval', (array) $haric)));
        $benim = array_slice(array_values(array_filter(array_map('intval', (array) $benim))), -300);
        $ek = $haric ? ' AND id NOT IN (' . implode(',', $haric) . ')' : '';
        $bn = $benim ? ' OR id IN (' . implode(',', $benim) . ')' : '';
        $uid = get_current_user_id();
        $satirlar = $wpdb->get_results($wpdb->prepare("SELECT id, kaynak, soru, kullanim, durum FROM $t WHERE sinif = %d AND ders = %s AND cikti_kod = %s AND (durum = 'onayli' OR (durum = 'inceleme' AND kaynak = %s)$bn)$ek ORDER BY (kaynak LIKE 'yz%%' OR kaynak IN ('yonetici','ogretmen')) DESC, kullanim ASC, id DESC LIMIT 80", $sinif, $ders, $kod, $uid ? 'yz' . $uid : 'yz-yok'));
        if (count($satirlar) < $adet && strpos($kod, 'oz-') === 0) {   // diğer dersler: aynı dersin arşiv/öğretmen soruları, konu sözcüğüne göre
            $kel = array(); $cikti = '';
            $d0 = json_decode(wp_unslash(isset($_POST['veri']) ? $_POST['veri'] : '{}'), true);
            if (!empty($d0['ozel_tablo']['satirlar'])) foreach ($d0['ozel_tablo']['satirlar'] as $x) if (isset($x['kod']) && $x['kod'] === $kod) $cikti = (string) $x['konu'];
            foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($cikti, 'UTF-8')) as $w) if (mb_strlen($w) >= 4 && !in_array($w, array('ilgili', 'ilişkin', 'olarak', 'temel', 'genel'), true)) $kel[] = $w;
            $ek2 = $kel ? ' AND (' . implode(' OR ', array_map(function ($w) use ($wpdb) { return $wpdb->prepare('soru LIKE %s', '%' . $wpdb->esc_like($w) . '%'); }, array_slice($kel, 0, 4))) . ')' : '';
            $daha = $wpdb->get_results($wpdb->prepare("SELECT id, kaynak, soru, kullanim, durum FROM $t WHERE sinif = %d AND ders = %s AND cikti_kod <> %s AND (durum = 'onayli'$bn)$ek", $sinif, $ders, $kod) . $ek2 . ' ORDER BY kullanim ASC, id DESC LIMIT 60');
            $satirlar = array_merge($satirlar, (array) $daha);
        }
        $aday = array();
        foreach ($satirlar as $r) {
            $s = OSC_Havuz::arindir_soru(json_decode($r->soru, true)); if (!is_array($s) || !$this->tur_uygun($s, $tur)) continue;
            $z = self::zorluk($s);
            $puan = ($zorluk ? abs($z - $zorluk) * 3 : 0) + $r->kullanim * 0.6 + mt_rand(0, 100) / 100 + (strpos($r->kaynak, 'yz') === 0 || in_array($r->kaynak, array('yonetici', 'ogretmen'), true) ? 0 : 6);   // önce bizim (yapay zekâ/yönetici/öğretmen) soruları, yoksa arşiv
            $aday[] = array('p' => $puan, 'id' => (int) $r->id, 'kaynak' => $r->kaynak, 'soru' => $s, 'zorluk' => $z);
        }
        usort($aday, function ($a, $b) { return $a['p'] < $b['p'] ? -1 : ($a['p'] > $b['p'] ? 1 : 0); });
        return array_slice($aday, 0, $adet);
    }

    /* ---------- yapay zekâ ---------- */
    /** Öğretmen başına günlük yapay zekâ soru hakkı (soru sayısıyla) */
    public static function kota_durum() {
        $gun = gmdate('Ymd'); $uid = get_current_user_id();
        $lim = (int) get_option('osc_yz_gunluk', 40); if ($lim <= 0) $lim = 40;
        $n = (int) get_transient('osc_yz_kota_' . $uid . '_' . $gun);
        return array('limit' => $lim, 'kullanilan' => $n, 'kalan' => max(0, $lim - $n), 'sinirsiz' => current_user_can('manage_options') ? 1 : 0);
    }
    private function kota_iade($adet) {
        if (current_user_can('manage_options')) return;
        $k = 'osc_yz_kota_' . get_current_user_id() . '_' . gmdate('Ymd'); $n = (int) get_transient($k);
        if ($n > 0) set_transient($k, max(0, $n - (int) $adet), DAY_IN_SECONDS);
    }
    private function yz_kota($adet = 1) {
        if (current_user_can('manage_options')) return true;
        $gun = gmdate('Ymd'); $uid = get_current_user_id(); if (!$uid) return false;
        $top = (int) get_transient('osc_yz_top_' . $gun); $tl = (int) get_option('osc_yz_toplam', 3000); if ($tl <= 0) $tl = 3000;
        if ($top >= $tl) return false;
        $k = 'osc_yz_kota_' . $uid . '_' . $gun; $lim = (int) get_option('osc_yz_gunluk', 40); if ($lim <= 0) $lim = 40;
        $n = (int) get_transient($k);
        if ($n >= $lim) return false;
        set_transient($k, $n + max(1, (int) $adet), DAY_IN_SECONDS); set_transient('osc_yz_top_' . $gun, $top + max(1, (int) $adet), DAY_IN_SECONDS);
        return true;
    }
    /**
     * @param array $istek [ ['kod'=>..,'n'=>..], ... ]
     * @return array kod => [soru, ...]  | string hata
     */
    private $bag = array('donem' => 1, 'yazili' => 1);
    /** Arşivden bu çıktıya ait 1-2 örnek soru (yalnızca üslup/düzey örneği olarak) */
    private function ornekler($sinif, $ders, $kod) {
        global $wpdb; $t = OSC_Havuz::tablo();
        $r = $wpdb->get_col($wpdb->prepare("SELECT soru FROM $t WHERE sinif = %d AND ders = %s AND cikti_kod = %s AND durum = 'onayli' AND kaynak IN ('arsiv','yazilimatik','ogretmen','yonetici') ORDER BY RAND() LIMIT 6", $sinif, $ders, $kod));
        $o = array();
        foreach ((array) $r as $j) {
            $q = json_decode($j, true); if (!is_array($q)) continue;
            $m = trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags(str_replace('<br>', ' ', (isset($q['paragraph']) ? $q['paragraph'] . ' ' : '') . (isset($q['root']) ? $q['root'] : ''))), ENT_QUOTES, 'UTF-8')));
            if (mb_strlen($m) < 25 || mb_strlen($m) > 420 || preg_match('/kazanım|okunamad|\(\s*…|puandır/iu', $m)) continue;
            $o[] = $m; if (count($o) >= 2) break;
        }
        return $o;
    }
    public $kotasiz = false, $toplu = null, $cur = null;
    /** Yönetici: havuzu toplu doldurma (kota yok, kaynak yzhavuz) */
    public function toplu_uret($t, $kod, $n, $tur, $donem, $yazili, $durum) {
        $this->kotasiz = true; $this->toplu = array('durum' => $durum);
        $this->bag = array('donem' => (int) $donem, 'yazili' => (int) $yazili, 'mevcut' => $this->son_ozetler($t, $kod));
        $r = $this->yz_uret($t, array(array('kod' => $kod, 'n' => (int) $n)), 0, $tur);
        $this->kotasiz = false; $this->toplu = null;
        return $r;
    }
    /** aynı çıktı için havuzdaki son sorular (tekrar üretmemek için) */
    private function son_ozetler($t, $kod) {
        global $wpdb; $tb = OSC_Havuz::tablo(); $o = array();
        foreach ((array) $wpdb->get_col($wpdb->prepare("SELECT soru FROM $tb WHERE sinif = %d AND ders = %s AND cikti_kod = %s ORDER BY id DESC LIMIT 14", $t['sinif'], $t['ders'], $kod)) as $j) {
            $q = json_decode($j, true); if (!is_array($q)) continue;
            $m = trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags((isset($q['paragraph']) ? $q['paragraph'] . ' ' : '') . (isset($q['root']) ? $q['root'] : '')), ENT_QUOTES, 'UTF-8')));
            if ($m !== '') $o[] = mb_substr($m, 0, 110);
        }
        return $o;
    }
    private function yz_uret($t, $istek, $zorluk, $tur) {
        if (!OSC_YZ_API::var_mi()) return 'Yapay zekâ anahtarı tanımlı değil.';
        if (!$this->kotasiz && self::canli() === 'kapali') return 'Havuzda bu konu için uygun soru kalmadı. Bu soruyu kendiniz yazabilirsiniz.';
        $istenen = 0; foreach ($istek as $i) $istenen += (int) $i['n'];
        if (!$this->kotasiz && !$this->yz_kota($istenen)) { $kd = self::kota_durum(); return 'Bugünlük yapay zekâ soru hakkınız (' . $kd['limit'] . ' soru) doldu. Eksik soruları arşivden “Değiştir” ile seçebilir, kendiniz yazabilir ya da yarın devam edebilirsiniz.'; }
        $zad = array(1 => 'kolay (temel bilgi ve doğrudan uygulama)', 2 => 'orta (sınıf düzeyinde uygulama, iki-üç adımlı)', 3 => 'zor (analiz, çok adımlı akıl yürütme, gerekçelendirme)');
        $liste = array(); $toplam = 0;
        foreach ($istek as $i) {
            $x = $this->satir($t, $i['kod']); if (!$x) continue;
            $toplam += (int) $i['n'];
            $sat = '- kod: "' . $i['kod'] . '" | konu: ' . $x['konu'] . ' | öğrenme çıktısı: ' . $x['cikti'] . ' | yazılacak soru sayısı: ' . (int) $i['n'];
            $orn = $this->ornekler((int) $t['sinif'], $t['ders'], $i['kod']);
            if ($orn) $sat .= "\n  Bu çıktı için okul arşivinden örnek sorular (yalnızca düzey ve üslup fikri için; aynen kopyalama): " . implode(' ║ ', array_map(function ($m) { return '«' . $m . '»'; }, $orn));
            $liste[] = $sat;
        }
        if (!$liste) return array();
        $ders = $t['ders']; $sinif = (int) $t['sinif'];
        $turk = array(
            'acik' => "Bütün sorular AÇIK UÇLU olsun ('tur': 'acik'). Çoktan seçmeli, doğru-yanlış ya da boşluk doldurma YAZMA.",
            'test' => "Bütün sorular ÇOKTAN SEÇMELİ olsun ('tur': 'test'): 4 şık (A-D), yalnızca bir doğru şık; çeldiriciler öğrencilerin tipik hatalarından gelsin ve birbirine yakın uzunlukta olsun.",
            'karisik' => "Soruların çoğu AÇIK UÇLU ('tur': 'acik') olsun; uygun olduğunda bir kısmı çoktan seçmeli ('test', 4 şık), doğru-yanlış ('dogru_yanlis': 'maddeler' alanına 4-5 önerme yaz) ya da boşluk doldurma ('bosluk': cümledeki boşluğu …… ile göster) olabilir.",
        );
        $prompt = "Sen MEB Türkiye Yüzyılı Maarif Modeli öğretim programını çok iyi bilen, ölçme-değerlendirme konusunda uzman, deneyimli bir {$ders} öğretmenisin. {$sinif}. sınıf {$ders} dersi " . (int) $this->bag['donem'] . ". dönem " . (int) $this->bag['yazili'] . ". yazılı sınavı için gerçek bir okul yazılı kâğıdında yer alacak kalitede, ÖZGÜN sorular yazacaksın.\n\n"
            . "Öğrenme çıktıları:\n" . implode("\n", $liste) . "\n\n"
            . "Kurallar:\n"
            . "- " . (isset($turk[$tur]) ? $turk[$tur] : $turk['karisik']) . "\n"
            . "- Zorluk: " . ($zorluk ? $zad[$zorluk] : 'dengeli (kolay, orta ve zor karışık)') . ".\n"
            . "- Her soru yalnızca kendi öğrenme çıktısını ölçsün ve {$sinif}. sınıf düzeyine uygun olsun. Sorular birbirinin tekrarı olmasın; farklı bağlam ve sayılar kullan.\n"
            . "- 'oncul': gerekiyorsa kısa bir bağlam, durum, veri ya da gerçek yaşam senaryosu (2-4 cümle). Gerekmiyorsa boş bırak.\n"
            . "- 'tablo': veri/karşılaştırma gerekiyorsa tablo ver ({\"baslik\":[...],\"satirlar\":[[...],...]}); gerekmiyorsa null.\n"
            . "- 'soru': öğrenciye net bir yönerge ('…hesaplayınız.', '…gösteriniz.', '…açıklayınız.', '…bulunuz.').\n"
            . "- 'maddeler': beceri gerektiren açık uçlu sorularda 2-3 alt madde yaz (harf/numara YAZMA, sistem a), b), c) ekler); her madde ayrı ve net bir yönerge olsun. Basit sorularda boş dizi.\n"
            . "- Görsel, şekil ya da grafik gerektiren soru YAZMA; gerekiyorsa durumu sözle ya da tabloyla anlat.\n"
            . "- Matematiksel ifadeleri LaTeX ile dolar işaretleri arasında yaz; ekranda görünmesi gereken biçim: \$\\frac{3}{4}\$, \$\\sqrt[3]{54}\$, \$2^{5}\$, \$3 \\times 4\$, \$x \\le 5\$. JSON metninde bu, \"\$\\\\frac{3}{4}\$\" şeklinde yazılır (JSON kuralı gereği ters eğik çizgi bir kez kaçırılır; ÜÇ ya da DÖRT ters eğik çizgi YAZMA). Her formülün süslü parantezlerini kapat. Düz sayılar ve kısa ifadeler için LaTeX gerekmez.\n"
            . "- Sayıları, sonuçları temiz çıkacak şekilde seç; her sonucu kendin hesapla ve bir kez daha kontrol et. Yanlış ya da tartışmalı cevaplı soru yazma.\n"
            . "- 'cevap': beklenen cevap; 'maddeler' varsa her madde için ayrı bir eleman (aynı sırada), yoksa tek eleman. Kısa ama adım adım yaz; sonucu **kalın** yaz. Çoktan seçmelide kısa çözüm; doğru-yanlışta her önerme için D ya da Y ve kısa gerekçe.\n"
            . "- 'kontrol': sonucu farklı bir yoldan doğrulayan tek cümle (isteğe bağlı, matematik/fen için önerilir).\n"
            . "- 'zorluk': 1 kolay, 2 orta, 3 zor.\n"
            . ($ders === 'İngilizce' ? "- Sorular İngilizce olsun; yönergeler Türkçe olabilir.\n" : '')
            . "- Toplam {$toplam} soru yaz.\n"
            . (!empty($this->bag['mevcut']) ? "- Yazılı kâğıdında zaten şu sorular var; bunlardan FARKLI bağlam, sayı ve soru kalıbı kullan:\n" . implode("\n", array_map(function ($m) { return '  • ' . $m; }, $this->bag['mevcut'])) . "\n" : '')
            . "\n"
            . "Yalnızca şu biçimde JSON dizisi döndür:\n[{\"kod\":\"...\",\"tur\":\"acik|test|dogru_yanlis|bosluk\",\"oncul\":\"\",\"tablo\":null,\"soru\":\"\",\"maddeler\":[],\"secenekler\":{\"A\":\"\",\"B\":\"\",\"C\":\"\",\"D\":\"\"},\"dogru\":\"\",\"cevap\":[\"\"],\"kontrol\":\"\",\"zorluk\":2}]";
        $dz = OSC_YZ_API::json($prompt, min(8000, 1400 + 1300 * max(1, $toplam)), 55);
        if (is_string($dz)) { if (!$this->kotasiz) $this->kota_iade($toplam); return $dz; }
        if (isset($dz['sorular']) && is_array($dz['sorular'])) $dz = $dz['sorular'];
        if (count($istek) === 1 && is_array($dz)) { $tek = (string) $istek[0]['kod']; foreach ($dz as &$q1) if (is_array($q1)) $q1['kod'] = $tek; unset($q1); }   // yapay zekâ kodu bozsa da soru kaybolmasın
        return $this->yz_kaydet($t, $dz);
    }
    /** Yapay zekâ metni → güvenli HTML (**kalın**, satır sonu); LaTeX ($…$) olduğu gibi kalır */
    private static function yz_html($m) {
        $m = esc_html(OSC_Havuz::tex_duzelt(trim((string) $m)));
        $m = preg_replace('/\*\*(.+?)\*\*/su', '<b>$1</b>', $m);
        return nl2br($m, false);
    }
    private static function yz_tablo($t) {
        if (!is_array($t) || empty($t['satirlar']) || !is_array($t['satirlar'])) return '';
        $h = '<table class="yz-tb">';
        if (!empty($t['baslik']) && is_array($t['baslik'])) $h .= '<tr>' . implode('', array_map(function ($c) { return '<th>' . self::yz_html(is_scalar($c) ? $c : '') . '</th>'; }, $t['baslik'])) . '</tr>';
        foreach (array_slice($t['satirlar'], 0, 15) as $r) if (is_array($r)) $h .= '<tr>' . implode('', array_map(function ($c) { return '<td>' . self::yz_html(is_scalar($c) ? $c : '') . '</td>'; }, array_slice($r, 0, 6))) . '</tr>';
        return $h . '</table>';
    }
    private function yz_kaydet($t, $dz) {
        $liste = array(); $uid = get_current_user_id(); $harf = 'abcdefgh';
        foreach ($dz as $q) {
            if (!is_array($q) || empty($q['soru']) || empty($q['kod']) || !$this->satir($t, (string) $q['kod'])) continue;
            $tur = isset($q['tur']) ? (string) $q['tur'] : 'acik';
            $md = isset($q['maddeler']) && is_array($q['maddeler']) ? array_values(array_filter(array_map(function ($x) { return is_scalar($x) ? trim(preg_replace('/^\s*[a-hA-H1-9][\)\.]\s*/u', '', (string) $x)) : ''; }, $q['maddeler']), 'strlen')) : array();
            $cv = isset($q['cevap']) ? (is_array($q['cevap']) ? array_values(array_filter(array_map(function ($x) { return is_scalar($x) ? (string) $x : ''; }, $q['cevap']), 'strlen')) : array((string) $q['cevap'])) : array();
            $sec = isset($q['secenekler']) && is_array($q['secenekler']) ? array_filter(array_map(function ($x) { return is_scalar($x) ? (string) $x : ''; }, $q['secenekler']), 'strlen') : array();
            $par = self::yz_html(isset($q['oncul']) ? $q['oncul'] : '') . self::yz_tablo(isset($q['tablo']) ? $q['tablo'] : null);
            $kok = self::yz_html($q['soru']);
            $s = array('paragraph' => $par, 'root' => $kok, 'options' => array(), 'correct' => '');
            if (($tur === 'test' || count($sec) >= 3) && count($sec) >= 3) {
                $s['type'] = 'multiple';
                foreach (array('A', 'B', 'C', 'D', 'E') as $h) $s['options'][$h] = isset($sec[$h]) ? self::yz_html(preg_replace('/^\s*[A-E][\)\.]\s*/u', '', $sec[$h])) : '';
                $s['correct'] = strtoupper(substr(trim(isset($q['dogru']) ? (string) $q['dogru'] : ''), 0, 1));
                $cevap = $cv ? self::yz_html(implode("\n", $cv)) : '';
            } elseif ($tur === 'dogru_yanlis' && $md) {
                $s['type'] = 'classic';
                $s['root'] = $kok . implode('', array_map(function ($m) { return '<br>(&nbsp;&nbsp;&nbsp;&nbsp;) ' . self::yz_html($m); }, $md));
                $cevap = implode('<br>', array_map(function ($c, $i) use ($harf) { return '<b>' . ($i + 1) . '.</b> ' . self::yz_html($c); }, $cv, array_keys($cv)));
            } else {
                $s['type'] = $tur === 'bosluk' ? 'gap' : 'classic';
                if ($md) $s['root'] = $kok . implode('', array_map(function ($m, $i) use ($harf) { return '<br>' . $harf[$i] . ') ' . self::yz_html($m); }, $md, array_keys($md)));
                if ($md && count($cv) > 1) $cevap = implode('<br>', array_map(function ($c, $i) use ($harf) { return '<b>' . $harf[$i] . ')</b> ' . self::yz_html($c); }, $cv, array_keys($cv)));
                else $cevap = self::yz_html(implode("\n", $cv));
            }
            if (!empty($q['kontrol']) && is_scalar($q['kontrol'])) $cevap .= ($cevap ? '<br>' : '') . '<i>Kontrol:</i> ' . self::yz_html($q['kontrol']);
            if ($s['type'] === 'multiple') { if ($cevap) $s['cozum'] = $cevap; } else $s['options'] = array('A' => $cevap);
            $z = isset($q['zorluk']) ? max(0, min(3, (int) $q['zorluk'])) : 0; if ($z) $s['zorluk'] = $z;
            $liste[] = array('kaynak' => $this->toplu ? 'yzhavuz' : ($uid ? 'yz' . $uid : 'yzweb'), 'kaynak_baslik' => $this->toplu ? 'Yapay zekâ (havuz)' : 'Yapay zekâ', 'sinif' => (int) $t['sinif'], 'ders' => $t['ders'], 'donem' => (int) $this->bag['donem'], 'yazili' => (int) $this->bag['yazili'], 'cikti_kod' => (string) $q['kod'], 'cikti_puan' => 1, 'durum' => $this->toplu ? $this->toplu['durum'] : (get_option('osc_yz_oto_onay', '1') === '0' ? 'inceleme' : 'onayli'), 'soru' => $s, '_z' => $z);
        }
        $r = OSC_Havuz::ekle_dizi($liste);
        $o = array();
        foreach ($liste as $i => $x) {
            if (empty($r['idler'][$i])) continue;
            $x['soru']['zorluk'] = $x['_z'];
            $o[$x['cikti_kod']][] = array('id' => $r['idler'][$i], 'kaynak' => 'yz', 'soru' => $x['soru'], 'zorluk' => self::zorluk($x['soru']));
        }
        return $o;
    }
    private function kaynak_ad($k) { return strpos($k, 'yz') === 0 ? 'yz' : ($k === 'ogretmen' ? 'ogretmen' : 'arsiv'); }

    /* ---------- AJAX ---------- */
    public function ajax_hazirla() {
        $this->kontrol_acik(); $d = $this->girdi();
        $t = $this->tablo_al($d); if (!$t) wp_send_json_error('Ders ya da konu listesi bulunamadı.');
        $zorluk = (int) (isset($d['zorluk']) ? $d['zorluk'] : 0); $tur = sanitize_key(isset($d['tur']) ? $d['tur'] : 'karisik');
        $slotlar = isset($d['slotlar']) && is_array($d['slotlar']) ? array_slice($d['slotlar'], 0, 40) : array();
        $gerek = array(); foreach ($slotlar as $s) if (empty($s['ben']) && !empty($s['kod'])) $gerek[(string) $s['kod']] = (isset($gerek[(string) $s['kod']]) ? $gerek[(string) $s['kod']] : 0) + 1;
        $secilen = array(); $kullanilan = array(); $eksik = array(); $anahtarlar = array();
        $this->bag = array('donem' => (int) (isset($d['donem']) ? $d['donem'] : 1), 'yazili' => (int) (isset($d['yazili']) ? $d['yazili'] : 1));
        $yzk = isset($d['kaynak']) && $d['kaynak'] === 'yz' && self::canli() === 'acik';   // tamamı yapay zekâyla özgün
        foreach ($gerek as $kod => $n) {
            if ($yzk) { $eksik[] = array('kod' => $kod, 'n' => $n); continue; }
            $h = $this->havuzdan($t['sinif'], $t['ders'], $kod, $n + 10, $kullanilan, $zorluk, $tur, isset($d['benim']) ? $d['benim'] : array());
            $al = 0;
            foreach ($h as $x) {
                if ($al >= $n) break;
                $an = self::benzerlik_anahtari($x['soru']); if (isset($anahtarlar[$an])) continue;
                $anahtarlar[$an] = 1; $secilen[$kod][] = $x; $kullanilan[] = $x['id']; $al++;
            }
            if ($al < $n) $eksik[] = array('kod' => $kod, 'n' => $n - $al);
        }
        $uyari = '';
        if ($eksik && !empty($d['yz'])) {
            $y = $this->yz_uret($t, $eksik, $zorluk, $tur);
            if (is_string($y)) $uyari = $y;
            else foreach ($y as $kod => $l) foreach ($l as $x) $secilen[$kod][] = $x;
        }
        $o = array(); $ver = array();
        foreach ($slotlar as $i => $s) {
            if (!empty($s['ben']) || empty($s['kod'])) { $o[] = null; continue; }
            $kod = (string) $s['kod']; $j = isset($ver[$kod]) ? $ver[$kod] : 0;
            $x = isset($secilen[$kod][$j]) ? $secilen[$kod][$j] : null; $ver[$kod] = $j + 1;
            if ($x) $x['kaynak'] = $this->kaynak_ad($x['kaynak']);
            $o[] = $x;
        }
        $this->kullanim_artir(array_filter(wp_list_pluck(array_filter($o), 'id')));
        wp_send_json_success(array('sorular' => $o, 'uyari' => $uyari));
    }
    private function kullanim_artir($ids) {
        global $wpdb; $ids = array_values(array_filter(array_map('intval', (array) $ids)));
        if ($ids) $wpdb->query('UPDATE ' . OSC_Havuz::tablo() . ' SET kullanim = kullanim + 1 WHERE id IN (' . implode(',', $ids) . ')');
    }
    public function ajax_yz() {
        $this->kontrol_acik(); if (!is_user_logged_in()) wp_send_json_error('Yapay zekâ ile soru yazdırmak için ücretsiz üye girişi yapın.', 403); $d = $this->girdi();
        $t = $this->tablo_al($d); if (!$t) wp_send_json_error('Ders ya da konu listesi bulunamadı.');
        $kod = (string) (isset($d['kod']) ? $d['kod'] : ''); $n = max(1, min(3, (int) (isset($d['n']) ? $d['n'] : 1)));
        if (!$this->satir($t, $kod)) wp_send_json_error('Öğrenme çıktısı bulunamadı.');
        $zorluk = (int) (isset($d['zorluk']) ? $d['zorluk'] : 0); $tur = sanitize_key(isset($d['tur']) ? $d['tur'] : 'karisik');
        $this->bag = array('donem' => (int) (isset($d['donem']) ? $d['donem'] : 1), 'yazili' => (int) (isset($d['yazili']) ? $d['yazili'] : 1), 'mevcut' => array_slice(array_map(function ($x) { return mb_substr(sanitize_text_field((string) $x), 0, 120); }, isset($d['mevcut']) ? (array) $d['mevcut'] : array()), 0, 20));
        $mevcut = array_flip(array_map('strval', isset($d['anahtarlar']) ? (array) $d['anahtarlar'] : array()));
        $h = (isset($d['kaynak']) && $d['kaynak'] === 'yz') ? array() : array_slice(array_values(array_filter($this->havuzdan($t['sinif'], $t['ders'], $kod, $n + 8, isset($d['haric']) ? (array) $d['haric'] : array(), $zorluk, $tur, isset($d['benim']) ? $d['benim'] : array()), function ($x) use ($mevcut) { return !isset($mevcut[self::benzerlik_anahtari($x['soru'])]); })), 0, $n);
        if (count($h) < $n) {
            $y = $this->yz_uret($t, array(array('kod' => $kod, 'n' => $n - count($h))), $zorluk, $tur);
            if (is_string($y)) { if (!$h) wp_send_json_error($y); }
            else foreach ((isset($y[$kod]) ? $y[$kod] : array()) as $x) $h[] = $x;
        }
        if (!$h) wp_send_json_error('Yapay zekâ bu öğrenme çıktısı için soru üretemedi. “Değiştir” ile tekrar deneyebilirsiniz.');
        foreach ($h as &$x) $x['kaynak'] = $this->kaynak_ad($x['kaynak']); unset($x);
        $this->kullanim_artir(wp_list_pluck($h, 'id'));
        wp_send_json_success(array_slice($h, 0, $n));
    }
    public function ajax_degistir() {
        $this->kontrol_acik(); $d = $this->girdi();
        $t = $this->tablo_al($d); if (!$t) wp_send_json_error('Ders ya da konu listesi bulunamadı.');
        $kod = (string) (isset($d['kod']) ? $d['kod'] : ''); $zorluk = (int) (isset($d['zorluk']) ? $d['zorluk'] : 0); $tur = sanitize_key(isset($d['tur']) ? $d['tur'] : 'karisik');
        $haric = isset($d['haric']) ? (array) $d['haric'] : array();
        $this->bag = array('donem' => (int) (isset($d['donem']) ? $d['donem'] : 1), 'yazili' => (int) (isset($d['yazili']) ? $d['yazili'] : 1), 'mevcut' => array_slice(array_map(function ($x) { return mb_substr(sanitize_text_field((string) $x), 0, 120); }, isset($d['mevcut']) ? (array) $d['mevcut'] : array()), 0, 20));
        $mevcut = array_flip(array_map('strval', isset($d['anahtarlar']) ? (array) $d['anahtarlar'] : array()));
        $h = (isset($d['kaynak']) && $d['kaynak'] === 'yz') ? array() : array_values(array_filter($this->havuzdan($t['sinif'], $t['ders'], $kod, 12, $haric, $zorluk, $tur, isset($d['benim']) ? $d['benim'] : array()), function ($x) use ($mevcut) { return !isset($mevcut[self::benzerlik_anahtari($x['soru'])]); }));
        $h = array_slice($h, 0, 1);
        if (!$h && !empty($d['yz_yok'])) wp_send_json_error('Bu çıktı için havuzda başka soru kalmadı.');
        if (!$h) {
            $y = $this->yz_uret($t, array(array('kod' => $kod, 'n' => 1)), $zorluk, $tur);
            if (is_string($y)) wp_send_json_error($y);
            $h = isset($y[$kod]) ? $y[$kod] : array();
            if (!$h) wp_send_json_error('Yeni soru üretilemedi, tekrar deneyin.');
        }
        $x = $h[0]; $x['kaynak'] = $this->kaynak_ad($x['kaynak']);
        $this->kullanim_artir(array($x['id']));
        wp_send_json_success($x);
    }
    /** giriş yapmadan kullananların kendi yazdığı sorular havuza (inceleme) */
    public function ajax_havuza() {
        $this->kontrol_acik(); $d = $this->girdi();
        $t = $this->tablo_al($d); if (!$t) wp_send_json_error('Ders ya da konu listesi bulunamadı.');
        $k = 'osc_yz_hv_' . md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') . '_' . gmdate('Ymd'); $n = (int) get_transient($k);
        if ($n > 60) wp_send_json_error('Günlük sınır.'); set_transient($k, $n + 1, DAY_IN_SECONDS);
        $l = array();
        foreach (array_slice((array) (isset($d['sorular']) ? $d['sorular'] : array()), 0, 40) as $q) {
            if (!is_array($q) || (isset($q['kaynak']) ? $q['kaynak'] : '') !== 'ogretmen' || empty($q['soru']) || !$this->satir($t, (string) (isset($q['kod']) ? $q['kod'] : ''))) continue;
            $l[] = array('kaynak' => 'ogretmen', 'kaynak_baslik' => is_user_logged_in() ? 'Öğretmen: ' . wp_get_current_user()->display_name : 'Ziyaretçi öğretmen', 'sinif' => (int) $t['sinif'], 'ders' => $t['ders'], 'donem' => (int) (isset($d['donem']) ? $d['donem'] : 1), 'yazili' => (int) (isset($d['yazili']) ? $d['yazili'] : 0), 'cikti_kod' => (string) $q['kod'], 'cikti_puan' => 1, 'durum' => 'inceleme', 'soru' => $q['soru']);
        }
        $r = $l ? OSC_Havuz::ekle_dizi($l) : array('eklenen' => 0);
        wp_send_json_success(array('n' => $r['eklenen']));
    }
    public function ajax_kaydet() {
        $this->kontrol(); $d = $this->girdi();
        $id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
        if ($id) { $p = get_post($id); if (!$p || $p->post_type !== 'osc_yazili' || ((int) $p->post_author !== get_current_user_id() && !current_user_can('manage_options'))) wp_send_json_error('Yazılı bulunamadı.'); }
        $baslik = mb_substr(sanitize_text_field(isset($d['bilgi']['baslik']) ? $d['bilgi']['baslik'] : 'Yazılı'), 0, 200);
        // öğretmenin kendi yazdığı sorular havuza (inceleme)
        $kendi = array();
        foreach ((array) (isset($d['sorular']) ? $d['sorular'] : array()) as $q) {
            if (!is_array($q) || empty($q['soru']) || !isset($q['kaynak']) || $q['kaynak'] !== 'ogretmen') continue;
            $kendi[] = array('kaynak' => 'ogretmen', 'kaynak_baslik' => 'Öğretmen: ' . wp_get_current_user()->display_name, 'sinif' => (int) $d['sinif'], 'ders' => sanitize_text_field($d['ders']), 'donem' => (int) $d['donem'], 'yazili' => (int) $d['yazili'], 'cikti_kod' => sanitize_text_field(isset($q['kod']) ? $q['kod'] : ''), 'cikti_puan' => 1, 'durum' => 'inceleme', 'soru' => $q['soru']);
        }
        if ($kendi) OSC_Havuz::ekle_dizi($kendi);
        $veri = wp_json_encode($d);
        if (strlen($veri) > 3000000) wp_send_json_error('Yazılı çok büyük.');
        $arr = array('post_type' => 'osc_yazili', 'post_status' => 'private', 'post_title' => $baslik, 'post_author' => get_current_user_id());
        if ($id) { $arr['ID'] = $id; wp_update_post($arr); } else $id = wp_insert_post($arr);
        if (!$id || is_wp_error($id)) wp_send_json_error('Kaydedilemedi.');
        update_post_meta($id, '_osc_yz', wp_slash($veri));
        if (!empty($d['bilgi']['okul'])) update_user_meta(get_current_user_id(), 'osc_okul', sanitize_text_field($d['bilgi']['okul']));
        wp_send_json_success(array('id' => $id));
    }
    public function ajax_liste() {
        $this->kontrol();
        $l = get_posts(array('post_type' => 'osc_yazili', 'post_status' => 'private', 'author' => get_current_user_id(), 'numberposts' => 100, 'orderby' => 'modified'));
        $o = array();
        foreach ($l as $p) { $v = json_decode(get_post_meta($p->ID, '_osc_yz', true), true); $o[] = array('id' => $p->ID, 'baslik' => $p->post_title, 'tarih' => get_the_modified_date('d.m.Y H:i', $p), 'sinif' => isset($v['sinif']) ? $v['sinif'] : '', 'ders' => isset($v['ders']) ? $v['ders'] : '', 'yazili' => isset($v['yazili']) ? $v['yazili'] : '', 'senaryo' => isset($v['senaryo']) ? $v['senaryo'] : '', 'n' => isset($v['sorular']) ? count($v['sorular']) : 0); }
        wp_send_json_success($o);
    }
    private function sahip($id) { $p = get_post($id); return $p && $p->post_type === 'osc_yazili' && ((int) $p->post_author === get_current_user_id() || current_user_can('manage_options')); }
    public function ajax_getir() {
        $this->kontrol(); $id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
        if (!$this->sahip($id)) wp_send_json_error('Yazılı bulunamadı.');
        wp_send_json_success(array('id' => $id, 'veri' => json_decode(get_post_meta($id, '_osc_yz', true), true)));
    }
    public function ajax_sil() {
        $this->kontrol(); $id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
        if (!$this->sahip($id)) wp_send_json_error('Yazılı bulunamadı.');
        wp_delete_post($id, true); wp_send_json_success();
    }

    /* ---------- reklamlar ---------- */
    public static function reklam_alanlari() { return array('ust' => 'Üst (aracın üstü)', 'orta' => 'Orta (adımların altı)', 'hazirlik' => 'Sorular hazırlanırken', 'alt' => 'Alt (sayfa sonu)'); }
    public static function reklam($alan) {
        if (get_option('osc_reklam_acik', '1') === '0') return '';
        if (current_user_can('manage_options') && get_option('osc_reklam_yonetici', '0') !== '1') return '';
        $k = trim((string) get_option('osc_reklam_' . $alan, '')); if ($k === '') return '';
        return '<div class="osc-rk osc-rk-' . esc_attr($alan) . '"><span class="osc-rk-et">Reklam</span>' . $k . '</div>';
    }
    /* ---------- sayfa ---------- */
    /** Yazılı Hazırla sayfası: temanın kenar çubuğu olmadan, tam genişlikte kendi düzeni */
    public function tam_sayfa() {
        $id = (int) get_option('osc_yz_sayfa');
        if (!$id || !is_page($id) || is_admin() || apply_filters('osc_yz_tema_sablonu', false)) return;
        add_filter('body_class', function ($c) { $c[] = 'osc-yz-tam'; $c[] = 'no-sidebar'; return array_diff($c, array('has-sidebar', 'right-sidebar', 'left-sidebar')); });
        add_filter('is_active_sidebar', '__return_false', 99);
        get_header(); ?>
<style>
body.osc-yz-tam{background:#f5f6fb}
body.osc-yz-tam #secondary,body.osc-yz-tam .sidebar,body.osc-yz-tam aside.widget-area,body.osc-yz-tam .widget-area{display:none!important}
.osc-yz-sayfa{position:relative;width:100%;max-width:none;margin:0;padding:0 0 30px;box-sizing:border-box;overflow:hidden;clear:both;float:none}
.osc-yz-sayfa:before{content:"";position:absolute;inset:0 0 auto 0;height:420px;background:radial-gradient(700px 300px at 85% -10%,rgba(192,38,211,.18),transparent 65%),radial-gradient(800px 340px at 10% -20%,rgba(91,91,246,.20),transparent 65%);pointer-events:none;z-index:0}
.osc-yz-sayfa>.ic{position:relative;z-index:1;max-width:1240px;margin:0 auto;padding:28px 18px 0;box-sizing:border-box}
.osc-yz-sayfa .bas{text-align:center;margin:6px auto 24px;max-width:760px;font-family:"Plus Jakarta Sans",system-ui,-apple-system,"Segoe UI",sans-serif}
.osc-yz-sayfa .bas span{display:inline-block;font-size:12px;font-weight:800;letter-spacing:.08em;color:#6d28d9;background:#ede9fe;border-radius:99px;padding:5px 12px}
.osc-yz-sayfa .bas h1{font-size:clamp(28px,4.2vw,44px);line-height:1.12;letter-spacing:-.025em;font-weight:800;color:#0b1220;margin:12px 0 10px}
.osc-yz-sayfa .bas h1 em{font-style:normal;background:linear-gradient(90deg,#5b5bf6,#c026d3);-webkit-background-clip:text;background-clip:text;color:transparent}
.osc-yz-sayfa .bas p{font-size:16px;line-height:1.6;color:#475569;margin:0}
body.osc-yz-tam .osc-yz{width:100%!important;margin:0 0 50px!important}
body.osc-yz-tam .osc-yz-seo{max-width:none}
@media(max-width:640px){.osc-yz-sayfa>.ic{padding:18px 12px 0}.osc-yz-sayfa .bas p{font-size:14.5px}}
@media print{.osc-yz-sayfa .bas,.osc-yz-sayfa:before{display:none}}
</style>
<main id="main" class="osc-yz-sayfa" role="main"><div class="ic">
<?php echo $this->kisa_kod(); ?>
</div></main>
<?php get_footer(); exit;
    }
    public function kisa_kod() {
        $cfg = self::cfg(false); $cfg['kota'] = is_user_logged_in() ? self::kota_durum() : null; $cfg['rk_hazirlik'] = self::reklam('hazirlik') ? 1 : 0;
        ob_start(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<?php echo self::reklam('ust'); ?>
<?php if (class_exists('OSC_Masa')) echo OSC_Masa::kirinti('Yazılı Hazırla'); ?>
<div id="osc-yz" class="osc-yz"><div class="yz-yuk">Yükleniyor…</div></div>
<?php echo self::reklam('orta'); ?>
<?php $rh = self::reklam('hazirlik'); if ($rh) echo '<div id="osc-rk-hazirlik-kap" class="osc-rk-kayan"><button type="button" aria-label="Kapat" onclick="this.parentNode.classList.remove(\'on\');this.parentNode.dataset.kapali=1">×</button><small>Sorularınız hazırlanıyor…</small>' . $rh . '</div>'; ?>
<link rel="stylesheet" href="<?php echo esc_url(self::kaynak_url('css')); ?>">
<script>window.OSC_YZ = <?php echo wp_json_encode($cfg); ?>;</script>
<script src="<?php echo esc_url(self::kaynak_url('js')); ?>"></script>
<section class="osc-yz-seo">
<h2>MEB senaryolarına uygun yazılı nasıl hazırlanır?</h2>
<div class="ic">
<div><b>1. Seçin</b><p>Sınıfı (5–12), dersi, dönemi, yazılıyı ve MEB 2026-2027 konu-soru dağılım tablosundaki senaryoyu seçin. Hangi öğrenme çıktısından kaç soru sorulacağı otomatik belirlenir.</p></div>
<div><b>2. Hazırlayın</b><p>Sorular soruindir.net yazılı arşivinden senaryodaki çıktılara göre gelir; eksik kalanlar yapay zekâ ile yazılır. Beğenmediğinizi değiştirin, isterseniz bazı soruları kendiniz yazın.</p></div>
<div><b>3. İndirin</b><p>Puanlar 100’e otomatik dağıtılır. Cevap boşluklu yazılı kâğıdını cevap anahtarlı ya da anahtarsız Word veya PDF olarak indirin, yazdırın.</p></div>
</div>
<p class="alt">Ücretsizdir, üyelik gerekmez. Ücretsiz üye olursanız yazılılarınız hesabınıza kaydedilir, her cihazdan açabilirsiniz; okul ve öğretmen bilgileriniz tüm yazılı ve evraklarınıza otomatik işlenir.</p>
</section>
<?php echo self::reklam('alt'); ?>
<style>.osc-rk{max-width:1180px;margin:18px auto;text-align:center;min-height:50px;overflow:hidden}.osc-rk-et{display:block;font:600 10px/1 system-ui,sans-serif;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8;margin-bottom:4px}.osc-rk-kayan{position:fixed;left:50%;bottom:0;z-index:99970;width:min(760px,100vw);transform:translate(-50%,110%);visibility:hidden;transition:transform .35s,visibility .35s;background:#fff;border-radius:16px 16px 0 0;box-shadow:0 -12px 40px -16px rgba(15,23,42,.45);padding:8px 10px 10px;box-sizing:border-box}.osc-rk-kayan.on{transform:translate(-50%,0);visibility:visible}.osc-rk-kayan>small{display:block;text-align:center;font:600 12px system-ui,sans-serif;color:#6d28d9;margin-bottom:4px}.osc-rk-kayan>button{position:absolute;right:8px;top:6px;border:0!important;background:#f1f5f9!important;color:#334155!important;padding:0!important;font-size:16px;line-height:1;border-radius:8px;width:26px;height:26px;cursor:pointer}.osc-rk-kayan .osc-rk{margin:0}@media print{.osc-rk,.osc-rk-kayan{display:none!important}}</style>
<style>.osc-yz-seo{max-width:1180px;margin:10px auto 40px;padding:0 4px;font-family:"Plus Jakarta Sans",system-ui,sans-serif;color:#334155}.osc-yz-seo h2{font-size:20px;margin:0 0 14px;color:#0b1220}.osc-yz-seo .ic{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.osc-yz-seo .ic>div{background:#fff;border:1px solid #e6e9f2;border-radius:16px;padding:16px}.osc-yz-seo b{color:#4338ca}.osc-yz-seo p{font-size:14px;line-height:1.6;margin:6px 0 0}.osc-yz-seo .alt{margin-top:12px;color:#64748b}@media(max-width:760px){.osc-yz-seo .ic{grid-template-columns:1fr}}</style>
<?php return ob_get_clean();
    }
}
$GLOBALS['osc_yazili'] = new OSC_Yazili();
}
