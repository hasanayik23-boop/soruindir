
/* =====================================================================
 * Evrak Hazırla: öğretmen evrakları (tutanaklar, BEP, kulüp evrakları)
 * ===================================================================== */
if (!class_exists('OSC_Evrak')) {
class OSC_Evrak {
    public function __construct() {
        add_action('admin_init', array($this, 'sayfa_kur'));
        add_shortcode('osc_evrak_hazirla', array($this, 'kisa_kod'));
        add_action('wp_ajax_osc_ev_yz', array($this, 'ajax_yz'));
        add_action('template_redirect', array($this, 'kaynak_servis'), 0);
        add_action('template_redirect', array($this, 'yayin_kontrol'), 1);
        add_action('template_redirect', array($this, 'tam_sayfa'), 20);
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
    public function ayar_menu() { add_submenu_page('edit.php?post_type=osc_quiz', 'Evrak Hazırla', 'Evrak Hazırla', 'manage_options', 'osc-evrak-ayar', array($this, 'ayar_sayfa')); }
    public function ayar_kayit() {
        register_setting('osc_evrak', 'osc_ev_yayinda', array('sanitize_callback' => function ($v) { return $v ? '1' : '0'; }));
        register_setting('osc_evrak', 'osc_ev_yz', array('sanitize_callback' => function ($v) { return $v ? '1' : '0'; }));
        register_setting('osc_evrak', 'osc_ev_gunluk', array('sanitize_callback' => function ($v) { return max(0, min(500, (int) $v)); }));
        register_setting('osc_evrak', 'osc_ev_turler', array('sanitize_callback' => function ($v) { return array_values(array_intersect(is_array($v) ? array_map('strval', $v) : array(), array_keys(OSC_Evrak::$turler))); }));
        foreach (array_keys(self::reklam_alanlari()) as $ra) register_setting('osc_evrak', 'osc_reklam_' . $ra, array('sanitize_callback' => function ($v) { $v = trim((string) $v); return current_user_can('unfiltered_html') ? $v : wp_kses_post($v); }));
    }
    public static $turler = array('zumre' => 'Zümre öğretmenler kurulu tutanağı', 'ogr_kurul' => 'Öğretmenler kurulu toplantı tutanağı', 'sok' => 'Şube öğretmenler kurulu (ŞÖK) tutanağı', 'veli' => 'Veli toplantısı tutanağı', 'bep' => 'BEP planı',
        'kulup_plan' => 'Sosyal kulüp: yıllık çalışma planı', 'kulup_tutanak' => 'Sosyal kulüp: ilk toplantı ve seçim tutanağı', 'kulup_aylik' => 'Sosyal kulüp: aylık çalışma raporu', 'kulup_rapor' => 'Sosyal kulüp: dönem sonu çalışma raporu',
        'egzersiz' => 'Ders dışı egzersiz planı', 'belirli_gun' => 'Belirli gün ve haftalar programı', 'gezi' => 'Gezi planı (Ek-9)', 'dilekce' => 'Dilekçe', 'oys' => 'OYS puan çizelgesi');
    public static function acik_turler() { $v = get_option('osc_ev_turler', false); return is_array($v) ? array_values(array_intersect($v, array_keys(self::$turler))) : array_keys(self::$turler); }
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
    public static function yz_acik() { return get_option('osc_ev_yz', '0') === '1' && OSC_YZ_API::var_mi(); }

    /* ---------- yapay zekâ ---------- */
    private static $tanim = array(
        'zumre' => 'zümre öğretmenler kurulu toplantı tutanağı', 'sok' => 'şube öğretmenler kurulu (ŞÖK) toplantı tutanağı', 'veli' => 'sınıf veli toplantısı tutanağı',
        'bep' => 'bireyselleştirilmiş eğitim programı (BEP)', 'ogr_kurul' => 'öğretmenler kurulu toplantı tutanağı', 'egzersiz' => 'ders dışı egzersiz çalışma planı', 'gezi' => 'okul gezisi planı', 'kulup_aylik' => 'öğrenci kulübü aylık çalışma raporu', 'dilekce' => 'öğretmen dilekçesi', 'belirli_gun' => 'okul kutlama/anma programı', 'kulup_plan' => 'öğrenci kulübü yıllık çalışma planı', 'kulup_tutanak' => 'öğrenci kulübü ilk toplantı tutanağı', 'kulup_rapor' => 'öğrenci kulübü dönem sonu çalışma raporu');
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
        $bilgi = array(); $etiket = array('ders' => 'Ders/zümre', 'siniflar' => 'Sınıf seviyeleri', 'toplanti' => 'Toplantı zamanı', 'sube' => 'Şube', 'mevcut' => 'Öğrenci sayısı', 'katilim' => 'Katılan veli', 'kademe' => 'Okul kademesi', 'kulup' => 'Kulüp', 'donem' => 'Dönem', 'uye' => 'Üye sayısı', 'ay' => 'Ay', 'egz' => 'Egzersiz', 'alan' => 'Alanı', 'grup' => 'Öğrenci grubu', 'sure' => 'Haftalık ders saati', 'gezi' => 'Gezilecek yer', 'tur' => 'Gezi türü', 'siniflar' => 'Sınıflar', 'dtur' => 'Dilekçe türü', 'gun' => 'Belirli gün', 'siirler' => 'Şiirler ve okuyanlar', 'gosteriler' => 'Gösteriler', 'sunucu' => 'Sunucu', 'konusmaci' => 'Konuşmacı', 'gorev' => 'Unvan/branş', 'aciklama' => 'Açıklama', 'makam' => 'Makam', 'tani' => 'Eğitsel ihtiyaç', 'sinif' => 'Sınıf', 'performans' => 'Öğrencinin mevcut performansı', 'yapilanlar' => 'Yapılan çalışmalar', 'not' => 'Öğretmenin notu');
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
        foreach (array('gundem', 'gorusmeler', 'kararlar', 'uyarlamalar', 'aile', 'yapilanlar', 'oneriler', 'kazanimlar', 'guvenlik', 'akis') as $k) if (isset($r[$k])) $c[$k] = self::liste($r[$k], 16, 1200);
        foreach (array('performans', 'degerlendirme', 'amac', 'metin', 'konusma') as $k) if (isset($r[$k])) $c[$k] = self::temiz($r[$k], 2000);
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
        if (!$this->ogretmen_mi()) {
            $ogr = is_user_logged_in(); $g = OSC_Sinav::sayfa_url('osc_sp_uye_sayfa', array('rol' => 'ogretmen', 'git' => self::url()));
            return '<div style="max-width:620px;margin:30px auto;padding:34px 28px;border-radius:24px;text-align:center;background:#fff;border:1px solid #e6e9f2;box-shadow:0 24px 50px -36px rgba(30,27,75,.5);font-family:\'Plus Jakarta Sans\',system-ui,sans-serif"><div style="width:64px;height:64px;margin:0 auto 14px;border-radius:18px;background:linear-gradient(135deg,#0f766e,#2563eb);display:flex;align-items:center;justify-content:center;font-size:30px">🗂️</div><h2 style="margin:0 0 8px;font-size:24px;color:#0b1220">Evrak Hazırla öğretmenlere özeldir</h2><p style="margin:0 0 20px;color:#475569;line-height:1.6">' . ($ogr ? 'Bu araç yalnızca öğretmen hesaplarıyla kullanılabilir.' : 'Zümre, ŞÖK ve veli toplantısı tutanakları, BEP planı ve kulüp evraklarını hazırlamak için ücretsiz öğretmen hesabınızla giriş yapın.') . '</p>' . ($ogr ? '' : '<a href="' . esc_url($g) . '" style="display:inline-block;background:linear-gradient(135deg,#0f766e,#2563eb);color:#fff;text-decoration:none;font-weight:800;padding:13px 22px;border-radius:14px">Giriş yap / Ücretsiz üye ol →</a>') . '</div>';
        }
        $u = wp_get_current_user();
        $cfg = array('ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('osc_ev'), 'yz' => self::yz_acik() ? 1 : 0, 'site' => preg_replace('#^https?://(www\.)?#', '', untrailingslashit(home_url())), 'ad' => $u->display_name, 'okul' => (string) get_user_meta($u->ID, 'osc_okul', true), 'turler' => self::acik_turler());
        $rk = array(self::reklam('ev_ust'), self::reklam('ev_alt')); $rf = self::reklam('ev_form'); $ry = self::reklam('ev_yan');
        ob_start(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<?php echo $rk[0]; ?>
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
