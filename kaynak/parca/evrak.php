
/* =====================================================================
 * Evrak Hazırla: öğretmen evrakları (tutanaklar, BEP, kulüp evrakları)
 * ===================================================================== */
if (!class_exists('OSC_Evrak')) {
class OSC_Evrak {
    public function __construct() {
        add_action('admin_init', array($this, 'sayfa_kur'));
        add_shortcode('osc_evrak_hazirla', array($this, 'kisa_kod'));
        add_action('wp_ajax_osc_ev_yz', array($this, 'ajax_yz'));
        add_action('wp_ajax_osc_ev_hesap', array($this, 'ajax_hesap'));
        add_action('template_redirect', array($this, 'kaynak_servis'), 0);
        add_action('template_redirect', array($this, 'yayin_kontrol'), 1);
        add_action('template_redirect', array($this, 'tam_sayfa'), 20);
        // her evrakın kendi adresi: /evrak-hazirla/yillik-plan/ …
        add_filter('request', array($this, 'yol_coz'));
        add_action('template_redirect', array($this, 'eski_adres'), 2);
        add_action('wp', array($this, 'seo_kur'));
        add_action('admin_menu', array($this, 'ayar_menu'), 31);
        add_action('admin_init', array($this, 'ayar_kayit'));
    }
    public static function yayinda() { return get_option('osc_ev_yayinda', '0') === '1'; }
    public static function gorebilir() { return self::yayinda() || current_user_can('manage_options'); }
    public static function url() { $id = (int) get_option('osc_ev_sayfa'); return $id && get_post_status($id) === 'publish' ? get_permalink($id) : home_url('/evrak-hazirla/'); }
    public static function surum() { return substr(md5((string) @filemtime(__FILE__) . '|' . DB_NAME . '|ev3|' . get_option('osc_senaryo_surum', '')), 0, 10); }
    public static function kaynak_url($t) { return add_query_arg(array('osc_ev_kaynak' => $t, 'v' => self::surum()), home_url('/')); }
    private function ogretmen_mi() { return is_user_logged_in() && OSC_Sinav::rol() !== 'ogrenci'; }
    public function yayin_kontrol() {
        if (self::yayinda() || is_admin()) return;
        $id = (int) get_option('osc_ev_sayfa');
        if ($id && is_page($id)) {
            if (!current_user_can('manage_options')) { wp_safe_redirect(home_url('/')); exit; }
            add_action('wp_head', function () { echo '<meta name="robots" content="noindex,nofollow">' . "\n"; }, 1);
        }
    }
    public function sayfa_kur() {
        if (!current_user_can('manage_options') || get_option('osc_ev_kur') === '1') return;
        $id = (int) get_option('osc_ev_sayfa');
        if (!$id || get_post_status($id) !== 'publish') {
            $var = get_page_by_path('evrak-hazirla');
            if ($var) { $id = $var->ID; if ($var->post_status !== 'publish' || strpos($var->post_content, '[osc_evrak_hazirla') === false) wp_update_post(array('ID' => $id, 'post_status' => 'publish', 'post_content' => '[osc_evrak_hazirla]')); }
            else $id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Evrak Hazırla', 'post_name' => 'evrak-hazirla', 'post_content' => '[osc_evrak_hazirla]'));
            if ($id && !is_wp_error($id)) update_option('osc_ev_sayfa', $id);
        }
        update_option('osc_ev_kur', '1');
    }
    public function kaynak_servis() {
        if (empty($_GET['osc_ev_kaynak'])) return;
        $t = $_GET['osc_ev_kaynak'] === 'css' ? 'css' : 'js';
        header('Content-Type: ' . ($t === 'css' ? 'text/css' : 'application/javascript') . '; charset=utf-8');
        header('Cache-Control: public, max-age=2592000'); header('X-Robots-Tag: noindex');
        if ($t === 'css') self::css_cikti(); else { if (class_exists('OSC_Havuz')) echo 'window.OSC_SNR=' . wp_json_encode(OSC_Havuz::senaryolar()) . ";\n"; self::js_cikti(); }
        exit;
    }
    public static function css_cikti() { ?>/*__EVRAK_CSS__*/<?php }
    public static function js_cikti() { ?>/*__EVRAK_JS__*/<?php }

    /* ---------- ayarlar ---------- */
    public function ayar_menu() { add_submenu_page('osc-ogretmen-masasi', 'Evrak Hazırla', 'Evrak Hazırla', 'manage_options', 'osc-evrak-ayar', array($this, 'ayar_sayfa')); }
    public function ayar_kayit() {
        register_setting('osc_evrak', 'osc_ev_yayinda', array('sanitize_callback' => function ($v) { return $v ? '1' : '0'; }));
        register_setting('osc_evrak', 'osc_ev_yz', array('sanitize_callback' => function ($v) { return $v ? '1' : '0'; }));
        register_setting('osc_evrak', 'osc_ev_gunluk', array('sanitize_callback' => function ($v) { return max(0, min(500, (int) $v)); }));
        register_setting('osc_evrak', 'osc_ev_turler', array('sanitize_callback' => function ($v) { update_option('osc_ev_turler_bilinen', array_keys(OSC_Evrak::$turler)); return array_values(array_intersect(is_array($v) ? array_map('strval', $v) : array(), array_keys(OSC_Evrak::$turler))); }));
        foreach (array_keys(self::reklam_alanlari()) as $ra) register_setting('osc_evrak', 'osc_reklam_' . $ra, array('sanitize_callback' => function ($v) { $v = trim((string) $v); return current_user_can('unfiltered_html') ? $v : wp_kses_post($v); }));
    }
    public static $turler = array('zumre' => 'Zümre öğretmenler kurulu tutanağı', 'ogr_kurul' => 'Öğretmenler kurulu toplantı tutanağı', 'sok' => 'Şube öğretmenler kurulu (ŞÖK) tutanağı', 'veli' => 'Veli toplantısı tutanağı', 'bep' => 'BEP planı',
        'kulup_plan' => 'Sosyal kulüp: yıllık çalışma planı', 'kulup_tutanak' => 'Sosyal kulüp: ilk toplantı ve seçim tutanağı', 'kulup_aylik' => 'Sosyal kulüp: aylık çalışma raporu', 'kulup_rapor' => 'Sosyal kulüp: dönem sonu çalışma raporu',
        'yillik' => 'Ünitelendirilmiş yıllık plan', 'gunluk_plan' => 'Günlük ders planı', 'performans' => 'Performans görevi ve rubrik', 'dyk' => 'DYK kurs planı ve devam çizelgesi', 'rehberlik' => 'Sınıf rehberlik planı / raporu', 'veli_gorusme' => 'Veli görüşme formu', 'nobet' => 'Nöbet çizelgesi', 'karne' => 'Karne görüşleri', 'egzersiz' => 'Ders dışı egzersiz planı', 'belirli_gun' => 'Belirli gün ve haftalar programı', 'gezi' => 'Gezi planı (Ek-9)', 'dilekce' => 'Dilekçe', 'oys' => 'OYS puan çizelgesi',
        'iyep' => 'İYEP planı', 'yazili_teslim' => 'Yazılı sınav kâğıdı teslim tutanağı', 'sinav_tutanak' => 'Yazılı sınav uygulama tutanağı', 'sinav_takvim' => 'Yazılı sınav takvimi', 'ders_kesim' => 'Ders kesim raporu', 'oturma' => 'Sınıf oturma planı', 'yoklama' => 'Sınıf listesi ve yoklama çizelgesi', 'tutanak' => 'Tutanak (olay, kaza, teslim, genel)', 'ogrenci_gorusme' => 'Öğrenci görüşme formu', 'ogrenci_bilgi' => 'Öğrenci tanıma ve veli bilgi formu', 'demirbas' => 'Sınıf demirbaş ve zimmet listesi', 'devamsizlik' => 'Devamsızlık veli bilgilendirme yazısı', 'kelebek' => 'Kelebek sınav sistemi');
    /** evrak türü → adres parçası (Türkçe, Google'da aranan biçimde) */
    public static $yollar = array('zumre' => 'zumre-ogretmenler-kurulu-tutanagi', 'ogr_kurul' => 'ogretmenler-kurulu-tutanagi', 'sok' => 'sube-ogretmenler-kurulu-tutanagi', 'veli' => 'veli-toplantisi-tutanagi', 'bep' => 'bep-plani',
        'kulup_plan' => 'kulup-yillik-calisma-plani', 'kulup_tutanak' => 'kulup-ilk-toplanti-tutanagi', 'kulup_aylik' => 'kulup-aylik-calisma-raporu', 'kulup_rapor' => 'kulup-donem-sonu-raporu',
        'yillik' => 'yillik-plan', 'gunluk_plan' => 'gunluk-ders-plani', 'performans' => 'performans-gorevi', 'dyk' => 'dyk-kurs-plani', 'rehberlik' => 'sinif-rehberlik-plani', 'veli_gorusme' => 'veli-gorusme-formu', 'nobet' => 'nobet-cizelgesi', 'karne' => 'karne-gorusleri', 'egzersiz' => 'ders-disi-egzersiz-plani', 'belirli_gun' => 'belirli-gun-ve-haftalar-programi', 'gezi' => 'gezi-plani', 'dilekce' => 'dilekce', 'oys' => 'oys-puan-cizelgesi',
        'iyep' => 'iyep-plani', 'yazili_teslim' => 'yazili-sinav-kagidi-teslim-tutanagi', 'sinav_tutanak' => 'yazili-sinav-uygulama-tutanagi', 'sinav_takvim' => 'yazili-sinav-takvimi', 'ders_kesim' => 'ders-kesim-raporu', 'oturma' => 'sinif-oturma-plani', 'yoklama' => 'yoklama-cizelgesi', 'tutanak' => 'tutanak', 'ogrenci_gorusme' => 'ogrenci-gorusme-formu', 'ogrenci_bilgi' => 'ogrenci-bilgi-formu', 'demirbas' => 'sinif-demirbas-listesi', 'devamsizlik' => 'devamsizlik-veli-bilgilendirme-yazisi', 'kelebek' => 'kelebek-sinav-sistemi');
    public static $baslik = array('zumre' => 'Zümre Öğretmenler Kurulu Tutanağı Hazırla', 'ogr_kurul' => 'Öğretmenler Kurulu Tutanağı Hazırla', 'sok' => 'Şube Öğretmenler Kurulu (ŞÖK) Tutanağı Hazırla', 'veli' => 'Veli Toplantısı Tutanağı Hazırla', 'bep' => 'BEP Planı Hazırla',
        'kulup_plan' => 'Sosyal Kulüp Yıllık Çalışma Planı Hazırla', 'kulup_tutanak' => 'Kulüp İlk Toplantı ve Seçim Tutanağı Hazırla', 'kulup_aylik' => 'Kulüp Aylık Çalışma Raporu Hazırla', 'kulup_rapor' => 'Kulüp Dönem Sonu Çalışma Raporu Hazırla',
        'yillik' => 'Yıllık Plan Hazırla (Ünitelendirilmiş)', 'gunluk_plan' => 'Günlük Ders Planı Hazırla', 'performans' => 'Performans Görevi ve Rubrik Hazırla', 'dyk' => 'DYK Kurs Planı Hazırla', 'rehberlik' => 'Sınıf Rehberlik Planı Hazırla', 'veli_gorusme' => 'Veli Görüşme Formu Hazırla', 'nobet' => 'Nöbet Çizelgesi Hazırla', 'karne' => 'Karne Görüşleri Hazırla', 'egzersiz' => 'Ders Dışı Egzersiz Planı Hazırla', 'belirli_gun' => 'Belirli Gün ve Haftalar Programı Hazırla', 'gezi' => 'Gezi Planı (Ek-9) Hazırla', 'dilekce' => 'Öğretmen Dilekçesi Hazırla', 'oys' => 'OYS Puan Çizelgesi Hazırla',
        'iyep' => 'İYEP Planı Hazırla (Türkçe – Matematik)', 'yazili_teslim' => 'Yazılı Sınav Kâğıdı Teslim Tutanağı Hazırla', 'sinav_tutanak' => 'Yazılı Sınav Uygulama Tutanağı Hazırla', 'sinav_takvim' => 'Yazılı Sınav Takvimi Hazırla', 'ders_kesim' => 'Ders Kesim Raporu Hazırla', 'oturma' => 'Sınıf Oturma Planı Hazırla', 'yoklama' => 'Yoklama Çizelgesi ve Sınıf Listesi Hazırla', 'tutanak' => 'Tutanak Hazırla (Olay, Kaza, Teslim, Genel)', 'ogrenci_gorusme' => 'Öğrenci Görüşme Formu Hazırla', 'ogrenci_bilgi' => 'Öğrenci Tanıma ve Veli Bilgi Formu Hazırla', 'demirbas' => 'Sınıf Demirbaş ve Zimmet Listesi Hazırla', 'devamsizlik' => 'Devamsızlık Veli Bilgilendirme Yazısı Hazırla', 'kelebek' => 'Kelebek Sınav Sistemi – Karma Oturma Planı Hazırla (Excel’den)');
    public static $grup_yol = 'sosyal-kulup-evraklari';
    private static $aktif = null;   // adresteki evrak türü
    public static function taban_yol() { return trim((string) wp_parse_url(self::url(), PHP_URL_PATH), '/'); }
    public static function tur_url($tur) { return isset(self::$yollar[$tur]) ? trailingslashit(self::url()) . self::$yollar[$tur] . '/' : self::url(); }
    public static function aktif_tur() { return self::$aktif; }
    /** /evrak-hazirla/{evrak}/ isteğini Evrak Hazırla sayfasına yönlendir (yeniden yazma kuralı gerekmez) */
    public function yol_coz($q) {
        if (is_admin() || !isset($_SERVER['REQUEST_URI'])) return $q;
        $id = (int) get_option('osc_ev_sayfa'); if (!$id) return $q;
        $yol = trim((string) strtok(wp_unslash($_SERVER['REQUEST_URI']), '?'), '/'); $taban = self::taban_yol();
        if ($taban === '' || strpos($yol, $taban . '/') !== 0) return $q;
        $parca = substr($yol, strlen($taban) + 1);
        if ($parca === self::$grup_yol) { self::$aktif = 'kulup'; return array('page_id' => $id); }
        $tur = array_search($parca, self::$yollar, true);
        if ($tur === false || !in_array($tur, self::acik_turler(), true)) return $q;
        self::$aktif = $tur; return array('page_id' => $id);
    }
    /** eski ?evrak=zumre adreslerini kalıcı olarak yeni adrese taşı */
    public function eski_adres() {
        $id = (int) get_option('osc_ev_sayfa');
        if (!$id || !is_page($id) || empty($_GET['evrak'])) return;
        $t = sanitize_key($_GET['evrak']); if (!isset(self::$yollar[$t])) return;
        $u = self::tur_url($t); if (!empty($_GET['gun'])) $u = add_query_arg('gun', rawurlencode(wp_unslash($_GET['gun'])), $u);
        wp_safe_redirect($u, 301); exit;
    }
    /** evrak adresinde başlık, açıklama ve kanonik adres o evraka göre */
    public function seo_kur() {
        $t = self::$aktif; if (!$t) return;
        $yil = (int) wp_date('n') >= 7 ? wp_date('Y') . '-' . ((int) wp_date('Y') + 1) : ((int) wp_date('Y') - 1) . '-' . wp_date('Y');
        if ($t === 'kulup') { $bas = 'Sosyal Kulüp Evrakları Hazırla ' . $yil . ' – Plan, Tutanak, Rapor'; $ac = 'Sosyal kulüp yıllık çalışma planı, ilk toplantı ve seçim tutanağı, aylık ve dönem sonu çalışma raporlarını ücretsiz hazırlayın, Word olarak indirin.'; $kan = trailingslashit(self::url()) . self::$grup_yol . '/'; }
        else {
            $bas = self::$baslik[$t] . ' ' . $yil . ' – Ücretsiz, Word İndir'; $kan = self::tur_url($t);
            $ac = isset(self::$turler[$t]) ? self::$turler[$t] : '';
            if (class_exists('OSC_Seo')) foreach (OSC_Seo::evraklar() as $e) if ($e['id'] === $t && !empty($e['ac'])) { $ac = $e['ac']; break; }
            $ac = rtrim($ac, '. ') . '. ' . $yil . ' güncel; bilgileri girin, taslağı düzenleyin, Word olarak ücretsiz indirin.';
        }
        $tam = $bas . ' | ' . get_bloginfo('name');
        add_filter('pre_get_document_title', function () use ($tam) { return $tam; }, 99);
        add_filter('wpseo_title', function () use ($tam) { return $tam; }, 99); add_filter('rank_math/frontend/title', function () use ($tam) { return $tam; }, 99);
        add_filter('wpseo_metadesc', function () use ($ac) { return $ac; }, 99); add_filter('rank_math/frontend/description', function () use ($ac) { return $ac; }, 99);
        add_filter('wpseo_canonical', function () use ($kan) { return $kan; }, 99); add_filter('rank_math/frontend/canonical', function () use ($kan) { return $kan; }, 99);
        add_filter('wpseo_opengraph_url', function () use ($kan) { return $kan; }, 99); add_filter('wpseo_opengraph_title', function () use ($bas) { return $bas; }, 99);
        remove_action('wp_head', 'rel_canonical');
        $seo_eklentisi = defined('WPSEO_VERSION') || class_exists('RankMath');
        add_action('wp_head', function () use ($ac, $kan, $bas, $seo_eklentisi) {
            if (!$seo_eklentisi) echo '<meta name="description" content="' . esc_attr($ac) . '">' . "
" . '<link rel="canonical" href="' . esc_url($kan) . '">' . "
" . '<meta property="og:title" content="' . esc_attr($bas) . '"><meta property="og:url" content="' . esc_url($kan) . '"><meta property="og:description" content="' . esc_attr($ac) . '">' . "
";
            echo '<script type="application/ld+json">' . wp_json_encode(array('@context' => 'https://schema.org', '@type' => 'WebApplication', 'name' => $bas, 'url' => $kan, 'description' => $ac, 'applicationCategory' => 'EducationalApplication', 'operatingSystem' => 'Web', 'inLanguage' => 'tr', 'offers' => array('@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'TRY')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "
";
        }, 2);
    }
    public static function acik_turler() {
        $v = get_option('osc_ev_turler', false); $hep = array_keys(self::$turler); if (!is_array($v)) return $hep;
        // ayar kaydedildikten sonra eklenen yeni evrak türleri varsayılan olarak açık gelsin
        $bil = get_option('osc_ev_turler_bilinen', array('zumre', 'ogr_kurul', 'sok', 'veli', 'bep', 'kulup_plan', 'kulup_tutanak', 'kulup_aylik', 'kulup_rapor', 'egzersiz', 'belirli_gun', 'gezi', 'dilekce', 'oys'));
        return array_values(array_filter($hep, function ($k) use ($v, $bil) { return in_array($k, $v, true) || !in_array($k, (array) $bil, true); }));
    }
    public static function reklam_alanlari() { return array('ev_ust' => 'Üst (aracın üstü)', 'ev_form' => 'Evrak seçimi ve form altı', 'ev_yan' => 'Önizlemede indirme düğmelerinin altı', 'ev_alt' => 'Alt (sayfa sonu)'); }
    private static function reklam($a) { return class_exists('OSC_Yazili') ? OSC_Yazili::reklam($a) : ''; }
    public function ayar_sayfa() {
        $id = (int) get_option('osc_ev_sayfa'); $u = $id ? get_permalink($id) : ''; $acik = self::acik_turler(); ?>
<div class="wrap"><h1>Evrak Hazırla</h1>
<p>Öğretmenler kurul ve toplantı tutanaklarını, BEP planını, sosyal kulüp evraklarını, gezi/egzersiz planlarını, dilekçe ve puan çizelgelerini hazırlayıp Word olarak indirir. Evraklar öğretmenin tarayıcısında saklanır; öğrenci bilgileri sunucuya kaydedilmez.</p>
<form method="post" action="options.php"><?php settings_fields('osc_evrak'); ?>
<h2>Genel</h2>
<table class="form-table">
<tr><th>Yayın durumu</th><td><label><input type="checkbox" name="osc_ev_yayinda" value="1" <?php checked(self::yayinda()); ?>> <b>Yayında</b> — öğretmenler kullanabilir</label><p class="description"><?php echo self::yayinda() ? '✅ Şu an herkese açık.' : '🔒 Şu an gizli: yalnızca yöneticiler açıp deneyebilir, Google’a kapalıdır.'; ?><?php if ($u) echo ' Adres: <a href="' . esc_url($u) . '" target="_blank">' . esc_html($u) . '</a>'; ?></p></td></tr>
<tr><th>Gösterilecek evraklar</th><td><input type="hidden" name="osc_ev_turler[]" value=""><?php foreach (self::$turler as $k => $ad) echo '<label style="display:block;margin:0 0 6px"><input type="checkbox" name="osc_ev_turler[]" value="' . esc_attr($k) . '" ' . checked(in_array($k, $acik, true), true, false) . '> ' . esc_html($ad) . '</label>'; ?><p class="description">İşaretlenmeyen evraklar öğretmenlere gösterilmez. Sosyal kulüp evrakları öğretmene tek kart altında görünür.</p></td></tr>
</table>
<h2>Yapay zekâ</h2>
<table class="form-table">
<tr><th>Yapay zekâ ile yazma</th><td><label><input type="checkbox" name="osc_ev_yz" value="1" <?php checked(get_option('osc_ev_yz', '0') === '1'); ?>> Açık</label><p class="description">Kapalıyken öğretmen yapay zekâ seçeneğini hiç görmez; “Evrakı oluştur” dediğinde hazır taslak gelir (ücretsiz). Açıkken öğretmen isterse metinleri yapay zekâ yazar; Yazılı Hazırla’daki yapay zekâ anahtarları kullanılır.<?php echo OSC_YZ_API::var_mi() ? '' : ' <b>Şu an tanımlı yapay zekâ anahtarı yok.</b>'; ?></p></td></tr>
<tr><th>Günlük sınır</th><td><input type="number" min="0" max="500" name="osc_ev_gunluk" value="<?php echo (int) get_option('osc_ev_gunluk', 20); ?>" class="small-text"> evrak / öğretmen / gün <span class="description">(yalnızca yapay zekâ ile oluşturulanlar sayılır; yöneticiler sınırsız)</span></td></tr>
</table>
<h2>Reklam alanları</h2>
<p class="description">AdSense (ya da başka) reklam kodunu ilgili kutuya olduğu gibi yapıştırın; boş bırakılan alan gösterilmez. Reklamların genel olarak açılıp kapatılması ve yöneticilere gösterilmesi Yazılı Hazırla ayarlarındaki “Reklamlar” seçeneğine bağlıdır.</p>
<table class="form-table">
<?php foreach (self::reklam_alanlari() as $ra => $ad) echo '<tr><th>' . esc_html($ad) . '</th><td><textarea name="osc_reklam_' . esc_attr($ra) . '" rows="4" class="large-text code">' . esc_textarea(get_option('osc_reklam_' . $ra, '')) . '</textarea></td></tr>'; ?>
</table><?php submit_button(); ?></form></div>
<?php }
    public static function profil() { if (!is_user_logged_in()) return new stdClass(); $p = get_user_meta(get_current_user_id(), 'osc_om_profil', true); if (!is_array($p)) $p = array(); if (empty($p['okul'])) { $o = (string) get_user_meta(get_current_user_id(), 'osc_okul', true); if ($o) $p['okul'] = $o; } if (empty($p['ogretmen'])) $p['ogretmen'] = wp_get_current_user()->display_name; return $p ? $p : new stdClass(); }
    /** üyelerin evrakları hesapta (öğrenci bilgisi içeren türler hariç; onlar yalnızca tarayıcıda kalır) */
    public static $kisisel = array('bep', 'oys', 'karne', 'veli_gorusme', 'kulup_tutanak', 'dyk', 'gezi', 'egzersiz', 'iyep', 'sinav_tutanak', 'oturma', 'yoklama', 'tutanak', 'ogrenci_gorusme', 'devamsizlik', 'kelebek');
    public function ajax_hesap() {
        if (!is_user_logged_in() || !wp_verify_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '', 'osc_ev')) wp_send_json_error('', 403);
        $uid = get_current_user_id(); $l = get_user_meta($uid, 'osc_ev_kayitlar', true); if (!is_array($l)) $l = array();
        $is = isset($_POST['is']) ? (string) $_POST['is'] : 'liste';
        if ($is === 'kaydet') {
            $x = json_decode(wp_unslash(isset($_POST['veri']) ? $_POST['veri'] : ''), true);
            if (!is_array($x) || empty($x['id']) || empty($x['tur']) || in_array($x['tur'], self::$kisisel, true) || !isset(self::$turler[$x['tur']])) wp_send_json_error('Kaydedilemedi.');
            $j = wp_json_encode($x); if (strlen($j) > 200000) wp_send_json_error('Evrak çok büyük.');
            $x['ad'] = mb_substr(sanitize_text_field(isset($x['ad']) ? $x['ad'] : ''), 0, 160);
            $l = array_values(array_filter($l, function ($k) use ($x) { return (string) $k['id'] !== (string) $x['id']; })); array_unshift($l, $x); $l = array_slice($l, 0, 60);
            update_user_meta($uid, 'osc_ev_kayitlar', $l);
        } elseif ($is === 'sil') {
            $id = (string) (isset($_POST['id']) ? $_POST['id'] : ''); $l = array_values(array_filter($l, function ($k) use ($id) { return (string) $k['id'] !== $id; })); update_user_meta($uid, 'osc_ev_kayitlar', $l);
        }
        wp_send_json_success($l);
    }
    public static function yz_acik() { return get_option('osc_ev_yz', '0') === '1' && OSC_YZ_API::var_mi(); }

    /* ---------- yapay zekâ ---------- */
    private static $tanim = array(
        'zumre' => 'zümre öğretmenler kurulu toplantı tutanağı', 'sok' => 'şube öğretmenler kurulu (ŞÖK) toplantı tutanağı', 'veli' => 'sınıf veli toplantısı tutanağı',
        'bep' => 'bireyselleştirilmiş eğitim programı (BEP)', 'ogr_kurul' => 'öğretmenler kurulu toplantı tutanağı', 'egzersiz' => 'ders dışı egzersiz çalışma planı', 'gezi' => 'okul gezisi planı', 'kulup_aylik' => 'öğrenci kulübü aylık çalışma raporu', 'dilekce' => 'öğretmen dilekçesi', 'belirli_gun' => 'okul kutlama/anma programı', 'gunluk_plan' => 'Türkiye Yüzyılı Maarif Modeli’ne uygun günlük ders planı', 'kulup_plan' => 'öğrenci kulübü yıllık çalışma planı', 'kulup_tutanak' => 'öğrenci kulübü ilk toplantı tutanağı', 'kulup_rapor' => 'öğrenci kulübü dönem sonu çalışma raporu');
    private function kota() {
        if (current_user_can('manage_options')) return true;
        $lim = (int) get_option('osc_ev_gunluk', 20); if ($lim <= 0) return false;
        $k = 'osc_ev_kota_' . get_current_user_id() . '_' . gmdate('Ymd'); $n = (int) get_transient($k);
        if ($n >= $lim) return false;
        set_transient($k, $n + 1, DAY_IN_SECONDS); return true;
    }
    private static function temiz($v, $n = 600) { return is_scalar($v) ? mb_substr(trim(wp_strip_all_tags((string) $v)), 0, $n) : ''; }
    private static function liste($v, $n = 14, $u = 700) { return is_array($v) ? array_values(array_filter(array_map(function ($x) use ($u) { return self::temiz($x, $u); }, array_slice($v, 0, $n)), 'strlen')) : array(); }
    public function ajax_yz() {
        if (!$this->ogretmen_mi()) wp_send_json_error('Öğretmen hesabıyla giriş yapmalısınız.', 403);
        if (!wp_verify_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '', 'osc_ev')) wp_send_json_error('Oturum süresi doldu. Sayfayı yenileyin.', 403);
        if (!self::gorebilir()) wp_send_json_error('Bu bölüm henüz kullanıma açılmadı.', 403);
        if (!self::yz_acik()) wp_send_json_error('Yapay zekâ ile yazma şu an kapalı.');
        $d = json_decode(wp_unslash(isset($_POST['veri']) ? $_POST['veri'] : '{}'), true); if (!is_array($d)) $d = array();
        $tur = isset($d['tur']) ? (string) $d['tur'] : ''; if (!isset(self::$tanim[$tur]) || !in_array($tur, self::acik_turler(), true)) wp_send_json_error('Geçersiz evrak türü.');
        $v = isset($d['veri']) && is_array($d['veri']) ? $d['veri'] : array();
        if (!$this->kota()) wp_send_json_error('Bugünkü yapay zekâ ile evrak hakkınız doldu. Hazır taslakla devam edebilirsiniz.');
        $bilgi = array(); $etiket = array('ders' => 'Ders/zümre', 'siniflar' => 'Sınıf seviyeleri', 'toplanti' => 'Toplantı zamanı', 'sube' => 'Şube', 'mevcut' => 'Öğrenci sayısı', 'katilim' => 'Katılan veli', 'kademe' => 'Okul kademesi', 'kulup' => 'Kulüp', 'donem' => 'Dönem', 'uye' => 'Üye sayısı', 'ay' => 'Ay', 'egz' => 'Egzersiz', 'alan' => 'Alanı', 'grup' => 'Öğrenci grubu', 'sure' => 'Haftalık ders saati', 'gezi' => 'Gezilecek yer', 'tur' => 'Gezi türü', 'siniflar' => 'Sınıflar', 'dtur' => 'Dilekçe türü', 'gun' => 'Belirli gün', 'siirler' => 'Şiirler ve okuyanlar', 'gosteriler' => 'Gösteriler', 'sunucu' => 'Sunucu', 'konusmaci' => 'Konuşmacı', 'unite' => 'Ünite/tema', 'ciktilar' => 'Öğrenme çıktıları', 'konu' => 'Konu', 'kavram' => 'Anahtar kavramlar', 'sure' => 'Süre (ders saati)', 'gorev' => 'Unvan/branş', 'aciklama' => 'Açıklama', 'makam' => 'Makam', 'tani' => 'Eğitsel ihtiyaç', 'sinif' => 'Sınıf', 'performans' => 'Öğrencinin mevcut performansı', 'yapilanlar' => 'Yapılan çalışmalar', 'not' => 'Öğretmenin notu');
        $tp = array('donem_basi' => 'eğitim-öğretim yılı başı', 'donem_ara' => '1. dönem ara değerlendirme', 'ikinci_donem' => '2. dönem başı', 'yil_sonu' => 'eğitim-öğretim yılı sonu');
        foreach ($etiket as $k => $e) { $x = self::temiz(isset($v[$k]) ? $v[$k] : '', 900); if ($k === 'toplanti' && isset($tp[$x])) $x = $tp[$x]; if ($x !== '') $bilgi[] = '- ' . $e . ': ' . $x; }
        $sema = array(
            'zumre' => '{"gundem":["madde",...],"gorusmeler":["her gündem maddesi için aynı sırayla 2-4 cümlelik görüşme metni",...],"kararlar":["karar",...]}',
            'sok' => '{"gundem":[...],"gorusmeler":[...],"kararlar":[...]}', 'veli' => '{"gundem":[...],"gorusmeler":[...],"kararlar":[...]}',
            'bep' => '{"performans":"öğrencinin performans düzeyi (3-4 cümle)","hedefler":[{"uda":"uzun dönemli amaç","kda":["kısa dönemli amaç",...],"yontem":"","materyal":"","degerlendirme":""}],"uyarlamalar":["ölçme-değerlendirme uyarlaması",...],"aile":["aile katılımı önerisi",...]}',
            'kulup_plan' => '{"aylar":[{"ay":"Eylül","etkinlikler":["...","..."],"hedef":"","yontem":""}, ... Haziran\'a kadar 10 ay]}',
            'kulup_tutanak' => '{"gundem":[...],"gorusmeler":[...],"kararlar":[...]}',
            'kulup_rapor' => '{"yapilanlar":["...",...],"degerlendirme":"4-5 cümle","oneriler":["...",...]}',
            'ogr_kurul' => '{"gundem":[...],"gorusmeler":[...],"kararlar":[...]}',
            'egzersiz' => '{"aylar":[{"ay":"Ekim","etkinlikler":["çalışma konusu",...],"hedef":"","yontem":""}, ... Mayıs\'a kadar 8 ay]}',
            'gezi' => '{"amac":"2-3 cümle","kazanimlar":["...",...],"guvenlik":["alınacak güvenlik önlemi",...]}',
            'kulup_aylik' => '{"yapilanlar":["gg.aa.yyyy - etkinlik",...],"degerlendirme":"3-4 cümle"}',
            'belirli_gun' => '{"akis":["program akışındaki her bölüm, görevlisiyle",...],"konusma":"günün anlam ve önemini anlatan 6-8 cümlelik özgün konuşma metni"}',
            'gunluk_plan' => '{"giris":["dikkat çekme ve ön bilgileri yoklama adımı",...],"kesfetme":["öğrencinin keşfettiği somut etkinlik adımı",...],"uygulama":["uygulama adımı",...],"kapanis":["özetleme/yansıtma adımı",...],"zenginlestirme":"","destekleme":"","olcme":["ölçme aracı",...],"bilesenler":"sosyal-duygusal beceriler, değerler, okuryazarlık becerileri","aile":""}',
            'dilekce' => '{"metin":"dilekçenin hitap ve imza dışındaki gövde metni; arz ederim ile biter"}');
        $p = 'Türkiye’de MEB’e bağlı bir okulda görev yapan deneyimli bir öğretmen gibi resmi yazışma diliyle bir ' . self::$tanim[$tur] . ' içeriği yaz.' . "\n" . implode("\n", $bilgi) . "\nKurallar:\n- Türkiye Yüzyılı Maarif Modeli, güncel ölçme-değerlendirme uygulamaları (senaryolara uygun ortak yazılılar, performans görevi) ve okulun gerçek işleyişine uygun, somut ve uygulanabilir ifadeler kullan.\n- Kişi adı uydurma; öğretmenin verdiği bilgileri ve notu içeriğe yerleştir.\n- Kalıp ve klişe cümlelerden kaçın, her maddeyi bu bilgilere özgü yaz.\n"
            . ($tur === 'kulup_plan' || $tur === 'kulup_rapor' || $tur === 'kulup_tutanak' ? "- 2026’da yayımlanan Sosyal Etkinlikler Politika Belgesi’ne göre kulüpler 5 tema altında (Kültür Dünyamız, Sanat, Spor, Bilim, Doğa-Çevre-Toplum) okul temelli sosyal etkinlikler eylem planıyla uyumlu yürütülür; etkinlikler e-Okul Sosyal Etkinlik Modülü’ne işlenir.\n" : '')
            . ($tur === 'bep' ? "- Amaçlar ölçülebilir, gözlenebilir davranışlarla yazılsın; kısa dönemli amaçlar basitten karmaşığa sıralansın. 2-3 uzun dönemli amaç, her birine 3-5 kısa dönemli amaç yaz.\n" : '')
            . ($tur === 'dilekce' ? "- Resmî Yazışma Kurallarına uygun, kısa ve açık bir dilekçe metni yaz; üst makama hitapta “arz ederim” kullan.\n" : '') . ($tur === 'zumre' || $tur === 'sok' || $tur === 'veli' || $tur === 'kulup_tutanak' || $tur === 'ogr_kurul' ? "- 6-9 gündem maddesi; ilk madde açılış/yoklama, son madde dilek ve kapanış olsun. gorusmeler dizisi gundem ile aynı uzunlukta olsun.\n" : '')
            . "Yalnızca şu biçimde geçerli JSON döndür, başka metin yazma:\n" . $sema[$tur];
        $r = OSC_YZ_API::json($p, 3500, 50);
        if (!is_array($r)) wp_send_json_error(is_string($r) ? $r : 'Yapay zekâ yanıt vermedi.');
        if (in_array($tur, array('kulup_plan', 'egzersiz'), true) && isset($r[0])) $r = array('aylar' => $r);
        $c = array();
        foreach (array('gundem', 'gorusmeler', 'kararlar', 'uyarlamalar', 'yapilanlar', 'oneriler', 'kazanimlar', 'guvenlik', 'akis', 'giris', 'kesfetme', 'uygulama', 'kapanis', 'olcme') as $k) if (isset($r[$k])) $c[$k] = self::liste($r[$k], 16, 1200);
        foreach (array('performans', 'degerlendirme', 'amac', 'metin', 'konusma', 'zenginlestirme', 'destekleme', 'bilesenler') as $k) if (isset($r[$k])) $c[$k] = self::temiz($r[$k], 2000);
        if (isset($r['aile'])) $c['aile'] = is_array($r['aile']) ? self::liste($r['aile'], 16, 1200) : self::temiz($r['aile'], 2000);
        if (isset($r['hedefler']) && is_array($r['hedefler'])) foreach (array_slice($r['hedefler'], 0, 6) as $h) if (is_array($h) && !empty($h['uda'])) $c['hedefler'][] = array('uda' => self::temiz($h['uda']), 'kda' => self::liste(isset($h['kda']) ? $h['kda'] : array(), 8, 300), 'yontem' => self::temiz(isset($h['yontem']) ? $h['yontem'] : ''), 'materyal' => self::temiz(isset($h['materyal']) ? $h['materyal'] : ''), 'degerlendirme' => self::temiz(isset($h['degerlendirme']) ? $h['degerlendirme'] : ''));
        if (isset($r['aylar']) && is_array($r['aylar'])) foreach (array_slice($r['aylar'], 0, 12) as $a) if (is_array($a) && !empty($a['ay'])) $c['aylar'][] = array('ay' => self::temiz($a['ay'], 20), 'etkinlikler' => self::liste(isset($a['etkinlikler']) ? $a['etkinlikler'] : array(), 5, 300), 'hedef' => self::temiz(isset($a['hedef']) ? $a['hedef'] : ''), 'yontem' => self::temiz(isset($a['yontem']) ? $a['yontem'] : ''));
        if (isset($c['gundem'], $c['gorusmeler'])) $c['gorusmeler'] = array_slice(array_pad($c['gorusmeler'], count($c['gundem']), ''), 0, count($c['gundem']));
        if (!$c) wp_send_json_error('Yapay zekâ yanıtı çözümlenemedi.');
        wp_send_json_success($c);
    }

    /* ---------- sayfa ---------- */
    public function tam_sayfa() {
        $id = (int) get_option('osc_ev_sayfa');
        if (!$id || !is_page($id) || is_admin() || apply_filters('osc_ev_tema_sablonu', false)) return;
        add_filter('body_class', function ($c) { $c[] = 'osc-ev-tam'; $c[] = 'no-sidebar'; return array_diff($c, array('has-sidebar', 'right-sidebar', 'left-sidebar')); });
        add_filter('is_active_sidebar', '__return_false', 99);
        get_header(); ?>
<style>body.osc-ev-tam{background:#f4f6fb}body.osc-ev-tam #secondary,body.osc-ev-tam .sidebar,body.osc-ev-tam .widget-area{display:none!important}.osc-ev-sayfa{width:100%;max-width:none;margin:0;padding:0 0 30px;box-sizing:border-box;clear:both;float:none}.osc-ev-sayfa>.ic{max-width:1240px;margin:0 auto;padding:26px 18px 0;box-sizing:border-box}@media(max-width:640px){.osc-ev-sayfa>.ic{padding:16px 12px 0}}</style>
<main id="main" class="osc-ev-sayfa" role="main"><div class="ic"><?php echo $this->kisa_kod(); ?></div></main>
<?php get_footer(); exit;
    }
    public function kisa_kod() {
        if (!self::gorebilir()) return '';
        $u = wp_get_current_user();
        $yl = array(); foreach (self::acik_turler() as $t0) if (isset(self::$yollar[$t0])) $yl[$t0] = self::$yollar[$t0];
        $cfg = array('taban' => '/' . self::taban_yol() . '/', 'yollar' => $yl, 'grupYol' => self::$grup_yol, 'aktif' => self::$aktif ? self::$aktif : '',
            'ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('osc_ev'), 'yz' => self::yz_acik() && is_user_logged_in() ? 1 : 0, 'yzUye' => self::yz_acik() ? 1 : 0, 'uye' => is_user_logged_in() ? 1 : 0, 'pnonce' => wp_create_nonce('osc_om'), 'profil' => self::profil(), 'giris' => is_user_logged_in() ? '' : (class_exists('OSC_Sinav') ? OSC_Sinav::sayfa_url('osc_sp_uye_sayfa', array('rol' => 'ogretmen', 'git' => self::url())) : wp_login_url(self::url())), 'site' => preg_replace('#^https?://(www\.)?#', '', untrailingslashit(home_url())), 'ad' => $u->display_name, 'okul' => (string) get_user_meta($u->ID, 'osc_okul', true), 'turler' => self::acik_turler(), 'takvim' => class_exists('OSC_YPlan') ? OSC_YPlan::takvim() : null, 'katalog' => class_exists('OSC_YPlan') ? OSC_YPlan::katalog() : null);
        $rk = array(self::reklam('ev_ust'), self::reklam('ev_alt')); $rf = self::reklam('ev_form'); $ry = self::reklam('ev_yan');
        ob_start(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<?php echo $rk[0]; ?>
<?php if (class_exists('OSC_Masa')) echo OSC_Masa::kirinti(self::$aktif && isset(self::$baslik[self::$aktif]) ? 'Evrak Hazırla › ' . preg_replace('/ Hazırla.*$/u', '', self::$baslik[self::$aktif]) : 'Evrak Hazırla'); ?>
<?php if (self::$aktif && isset(self::$baslik[self::$aktif])) echo '<h1 class="osc-ev-sh1" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">' . esc_html(self::$baslik[self::$aktif]) . '</h1>'; ?>
<div id="osc-ev" class="osc-ev"><div class="ev-yuk">Yükleniyor…</div></div>
<?php if ($rf) echo '<div id="osc-ev-rk-form">' . $rf . '</div>'; if ($ry) echo '<div id="osc-ev-rk-yan">' . $ry . '</div>'; ?>
<link rel="stylesheet" href="<?php echo esc_url(self::kaynak_url('css')); ?>">
<script>window.OSC_EV = <?php echo wp_json_encode($cfg); ?>;</script>
<script src="<?php echo esc_url(self::kaynak_url('js')); ?>"></script>
<?php echo $rk[1]; ?>
<?php return ob_get_clean();
    }
}
$GLOBALS['osc_evrak'] = new OSC_Evrak();
}
