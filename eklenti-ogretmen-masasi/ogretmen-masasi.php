<?php
/**
 * Plugin Name: Öğretmen Masası
 * Description: Yazılı Hazırla (MEB senaryolarına uygun yazılı) ve Evrak Hazırla (tutanaklar, BEP, kulüp evrakları, OYS çizelgesi). Online Soru Çöz eklentisinin etkin olması gerekir.
 * Version: 1.0.0
 * Author: soruindir.net
 */
if (!defined('ABSPATH')) exit;

register_activation_hook(__FILE__, function () {
    // ilk kurulumda iki aracı da yayına al; sayfaları yeniden kontrol ettir
    if (get_option('osc_om_ilk') !== '1') { update_option('osc_yz_yayinda', '1'); update_option('osc_ev_yayinda', '1'); update_option('osc_om_ilk', '1'); }
    delete_option('osc_om_kur'); delete_option('osc_ev_kur'); delete_option('osc_yz_kur');
});

add_action('plugins_loaded', 'osc_om_yukle', 20);
function osc_om_yukle() {
    if (!class_exists('OSC_Sinav') || !class_exists('OSC_Havuz') || !class_exists('OSC_YZ_API')) {
        add_action('admin_notices', function () { echo '<div class="notice notice-error"><p><b>Öğretmen Masası</b> çalışmak için <b>Online Soru Çöz</b> eklentisinin etkin olmasını gerektirir.</p></div>'; });
        return;
    }
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
        return array('ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('osc_yz'), 'ad' => $u->display_name, 'okul' => (string) get_user_meta($u->ID, 'osc_okul', true), 'logo' => OSC_Sinav::logo_src(), 'home' => home_url('/'), 'panel' => ($gomulu || !is_user_logged_in() || OSC_Sinav::rol() === 'ogrenci') ? '' : OSC_Sinav::sayfa_url('osc_sp_panel_sayfa'), 'yz' => (OSC_YZ_API::var_mi() && self::canli() !== 'kapali') ? 1 : 0, 'yzmod' => self::canli(), 'site' => preg_replace('#^https?://(www\.)?#', '', untrailingslashit(home_url())), 'gomulu' => $gomulu ? 1 : 0, 'kota' => is_user_logged_in() ? self::kota_durum() : null, 'yerel' => (is_user_logged_in() && OSC_Sinav::rol() !== 'ogrenci') ? 0 : 1, 'giris' => is_user_logged_in() ? '' : OSC_Sinav::sayfa_url('osc_sp_uye_sayfa', array('rol' => 'ogretmen', 'git' => self::url())));
    }
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
    public static function diger_json() { return '{"gruplar":[{"id":"lise_secmeli","ad":"Lise seçmeli dersleri","ik":"📗","dersler":[{"ad":"Seçmeli Matematik","s":[11,12]},{"ad":"Temel Matematik","s":[11,12]},{"ad":"Seçmeli Fizik","s":[11,12]},{"ad":"Seçmeli Kimya","s":[11,12]},{"ad":"Seçmeli Biyoloji","s":[11,12]},{"ad":"Seçmeli Türk Dili ve Edebiyatı","s":[11,12]},{"ad":"Seçmeli Tarih","s":[11,12]},{"ad":"Çağdaş Türk ve Dünya Tarihi","s":[12]},{"ad":"Seçmeli Coğrafya","s":[11,12]},{"ad":"Psikoloji","s":[11,12]},{"ad":"Sosyoloji","s":[11,12]},{"ad":"Mantık","s":[11,12]},{"ad":"Seçmeli Birinci Yabancı Dil","s":[11,12]},{"ad":"Fen Bilimleri Uygulamaları","s":[11,12]},{"ad":"Matematik Uygulamaları","s":[11,12]},{"ad":"Astronomi ve Uzay Bilimleri","s":[9,10,11,12]},{"ad":"Sosyal Bilim Çalışmaları","s":[9,10,11,12]},{"ad":"Bilişim Teknolojileri ve Yazılım","s":[9,10,11,12]},{"ad":"Proje Tasarımı ve Uygulamaları","s":[9,10,11,12]},{"ad":"Düşünme Eğitimi","s":[9,10]},{"ad":"Demokrasi ve İnsan Hakları","s":[9,10,11,12]},{"ad":"Sürdürülebilir Tarım ve Gıda Güvenliği","s":[10,11]},{"ad":"İklim, Çevre ve Yenilikçi Çözümler","s":[10,11]},{"ad":"Temel Hukuk Bilgisi","s":[10,11,12]},{"ad":"Metin Tahlilleri","s":[9,10,11]},{"ad":"Seçmeli İkinci Yabancı Dil","s":[9,10,11,12]},{"ad":"Osmanlı Türkçesi","s":[9,10,11,12]},{"ad":"Türk Dünyası Coğrafyası","s":[10,11]},{"ad":"Ortak Türk Edebiyatı","s":[10,11]},{"ad":"Ortak Türk Tarihi","s":[10,11]},{"ad":"Kur’an-ı Kerim","s":[9,10,11,12]},{"ad":"Kur’an-ı Kerim’in Anlam Dünyası","s":[11,12]},{"ad":"Peygamberimizin Hayatı","s":[9,10,11,12]},{"ad":"Temel Dinî Bilgiler","s":[9,10,11,12]},{"ad":"Türk Düşünce Tarihi","s":[10,11,12]},{"ad":"Klasik Ahlak Metinleri","s":[10,11,12]},{"ad":"Adabımuaşeret","s":[9,10]},{"ad":"Türk Sosyal Hayatında Aile","s":[9,10,11,12]},{"ad":"Tezhip","s":[11,12]},{"ad":"Ebru","s":[11,12]},{"ad":"Hüsnühat","s":[11,12]},{"ad":"Dini Musiki","s":[11,12]},{"ad":"Fütüvvet","s":[9,10,11,12]},{"ad":"Ahilik Kültürü ve Girişimcilik","s":[9,10,11,12]},{"ad":"İslam Bilim Tarihi","s":[9,10,11,12]},{"ad":"Türk Kültür ve Medeniyet Tarihi","s":[9,10,11,12]},{"ad":"İslam Kültür ve Medeniyeti","s":[9,10,11,12]},{"ad":"Spor Eğitimi","s":[9,10,11,12]},{"ad":"Sanat Eğitimi","s":[9,10,11,12]},{"ad":"Girişimcilik","s":[9,10,11,12]},{"ad":"Hedef Temelli Destek Eğitimi","s":[11,12]}]},{"id":"oo_secmeli","ad":"Ortaokul seçmeli dersleri","ik":"📘","dersler":[{"ad":"Matematik ve Bilim Uygulamaları","s":[5,6,7,8]},{"ad":"Okuma Becerileri","s":[5,6,7,8]},{"ad":"Yazarlık ve Yazma Becerileri","s":[5,6,7,8]},{"ad":"Yaşayan Diller ve Lehçeler","s":[5,6,7,8]},{"ad":"Çevre Eğitimi ve İklim Değişikliği","s":[5,6,7,8]},{"ad":"Şehrimiz","s":[5,6,7,8]},{"ad":"Hukuk ve Adalet","s":[5,6,7,8]},{"ad":"Düşünme Eğitimi","s":[5,6,7,8]},{"ad":"Robotik Kodlama","s":[5,6,7,8]},{"ad":"Yapay Zekâ Uygulamaları","s":[5,6,7,8]},{"ad":"Proje Tasarımı ve Uygulamaları","s":[5,6,7,8]},{"ad":"Okul Temelli Sosyal Sorumluluk Çalışmaları","s":[5,6,7,8]},{"ad":"Medya Okuryazarlığı","s":[5,6,7,8]},{"ad":"Afet Bilinci","s":[5,6,7,8]},{"ad":"Temel Yaşam Becerileri","s":[5,6,7,8]},{"ad":"Türk Sosyal Hayatında Aile","s":[5,6,7,8]},{"ad":"Kur’an-ı Kerim","s":[5,6,7,8]},{"ad":"Peygamberimizin Hayatı","s":[5,6,7,8]},{"ad":"Temel Dinî Bilgiler","s":[5,6,7,8]},{"ad":"Kültür ve Medeniyetimize Yön Verenler","s":[5,6,7,8]},{"ad":"Ahlak ve Vatandaşlık Eğitimi","s":[5,6,7,8]},{"ad":"Görgü Kuralları ve Nezaket","s":[5,6,7,8]},{"ad":"Masal ve Destanlarımız","s":[5,6,7,8]},{"ad":"Geleneksel Sanatlar","s":[5,6,7,8]},{"ad":"Dijital Sanatlar","s":[5,6,7,8]},{"ad":"Spor ve Fizikî Etkinlikler","s":[5,6,7,8]},{"ad":"Oyun ve Oyun Etkinlikleri","s":[5,6,7,8]},{"ad":"Halk Oyunları","s":[5,6,7,8]},{"ad":"Bilişim Teknolojileri ve Yazılım","s":[5,6,7,8]},{"ad":"Teknoloji ve Tasarım","s":[5,6,7,8]}]},{"id":"ihl","ad":"İmam Hatip Lisesi meslek dersleri","ik":"🕌","dersler":[{"ad":"Kur’an-ı Kerim","s":[9,10,11,12]},{"ad":"Temel Dinî Bilgiler","s":[9]},{"ad":"Siyer","s":[10]},{"ad":"Fıkıh","s":[10]},{"ad":"Tefsir","s":[10]},{"ad":"Hadis","s":[10]},{"ad":"Arapça","s":[9,10,11,12]},{"ad":"Mesleki Arapça","s":[11,12]},{"ad":"Kelam","s":[11]},{"ad":"Hitabet ve Mesleki Uygulama","s":[11]},{"ad":"İslam Kültür ve Medeniyeti","s":[11]},{"ad":"Dinler Tarihi","s":[12]},{"ad":"Akaid","s":[12]},{"ad":"Osmanlı Türkçesi","s":[12]},{"ad":"Arapça (Metin-Mükâleme)","s":[9,10,11,12]},{"ad":"Arapça (Sarf, Nahiv ve Klasik Metinler)","s":[9,10,11,12]},{"ad":"Fıkıh Okumaları","s":[9,10,11,12]},{"ad":"Hadis Metinleri","s":[9,10,11,12]},{"ad":"İslam Ahlakı","s":[9,10,11,12]},{"ad":"İslam Bilim Tarihi","s":[9,10,11,12]},{"ad":"İslam Felsefesi","s":[9,10,11,12]},{"ad":"İslam Tarihi","s":[9,10,11,12]},{"ad":"Kur’an Okuma Teknikleri","s":[9,10,11,12]},{"ad":"Kur’an’ın Ana Konuları","s":[9,10,11,12]},{"ad":"Mesleki Uygulama","s":[9,10,11,12]},{"ad":"Tasavvuf Kültürü","s":[9,10,11,12]},{"ad":"Tefsir Okumaları","s":[9,10,11,12]},{"ad":"Klasik Ahlak Metinleri","s":[9,10,11,12]},{"ad":"Dinî Musiki","s":[9,10,11,12]},{"ad":"Din Eğitimi","s":[9,10,11,12]},{"ad":"İslam’da Çocuk Eğitimi","s":[9,10,11,12]},{"ad":"Türk İslam Sanatı Tarihi","s":[9,10,11,12]}]},{"id":"iho","ad":"İmam Hatip Ortaokulu meslek dersleri","ik":"🕌","dersler":[{"ad":"Kur’an-ı Kerim","s":[5,6,7,8]},{"ad":"Arapça","s":[5,6,7,8]},{"ad":"Peygamberimizin Hayatı","s":[5,6,7,8]},{"ad":"Temel Dinî Bilgiler","s":[6,7]}]},{"id":"mtal","ad":"Meslek lisesi (MTAL) alan dersleri","ik":"🛠️","alanlar":[{"ad":"Adalet","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Klavye Teknikleri","s":[9]},{"ad":"Hukuk Dili ve Terminolojisi","s":[9]},{"ad":"Medeni Hukuk ve Medeni Usul Hukuku Uygulamaları","s":[10]},{"ad":"Hukuki Klavye Teknikleri","s":[10]},{"ad":"Ofis Uygulamaları","s":[10]},{"ad":"Personel Hukuku Uygulamaları","s":[10]},{"ad":"Hukuki Dosyalama ve Evrak İşlemleri","s":[10]},{"ad":"İdare Hukuku Uygulamaları","s":[11],"dal":"Zabıt Kâtipliği, İnfaz ve Koruma"},{"ad":"Cumhuriyet Başsavcılığı Kalem Hizmetleri","s":[11],"dal":"Zabıt Kâtipliği"},{"ad":"Ceza ve Ceza Muhakemesi Hukuku Uygulamaları","s":[11],"dal":"Zabıt Kâtipliği"},{"ad":"Kalem Hizmetleri","s":[11],"dal":"Zabıt Kâtipliği"},{"ad":"Nöbet İşlemleri","s":[11],"dal":"İnfaz ve Koruma"},{"ad":"Hükümlü ve Tutuklu Psikolojisi","s":[11],"dal":"İnfaz ve Koruma"},{"ad":"Ceza ve İnfaz Hukuku Uygulamaları","s":[11],"dal":"İnfaz ve Koruma"},{"ad":"Ceza İnfaz Kurumu Güvenliği","s":[11],"dal":"İnfaz ve Koruma"}]},{"ad":"Aile ve Tüketici Hizmetleri","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Etkili İletişim","s":[9]},{"ad":"Tüketici Hizmetlerine Giriş","s":[9]},{"ad":"Sosyal Destek Alanları","s":[9]},{"ad":"Beslenme","s":[10],"dal":"Sosyal Destek Hizmetleri"},{"ad":"Atölye Uygulamaları","s":[10,11],"dal":"Sosyal Destek Hizmetleri, Tüketici Hizmetleri"},{"ad":"Zararlı Alışkanlıklardan Korunma","s":[10],"dal":"Sosyal Destek Hizmetleri"},{"ad":"Çocuklarda ve Gençlerde Sosyal Destek Hizmetleri","s":[11],"dal":"Sosyal Destek Hizmetleri"},{"ad":"Yaşlı ve Engelli Bireylerde Sosyal Destek Hizmetleri","s":[11],"dal":"Sosyal Destek Hizmetleri"},{"ad":"Temel Bilgi Teknolojisi Kullanımı","s":[11],"dal":"Sosyal Destek Hizmetleri"},{"ad":"Tüketici Davranışları","s":[10],"dal":"Tüketici Hizmetleri"},{"ad":"Dosyalama ve Arşivleme","s":[10],"dal":"Tüketici Hizmetleri"},{"ad":"Temel Hukuk Bilgileri","s":[11],"dal":"Tüketici Hizmetleri"},{"ad":"Ticari Hesaplamalar","s":[11],"dal":"Tüketici Hizmetleri"},{"ad":"Ürün İade Hizmetleri","s":[11],"dal":"Tüketici Hizmetleri"}]},{"ad":"Ayakkabı ve Saraciye Teknolojisi","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Temel Üretim Atölyesi","s":[9]},{"ad":"Ayakkabı Üretim Atölyesi","s":[10],"dal":"Ayakkabı Tasarımı ve Üretimi"},{"ad":"Ayakkabı İstampası Çıkarma Atölyesi","s":[10],"dal":"Ayakkabı Tasarımı ve Üretimi"},{"ad":"Saraciye Üretim Atölyesi","s":[10],"dal":"Saraciye Tasarımı ve Üretimi"},{"ad":"Saraciye Kalıp Atölyesi","s":[10],"dal":"Saraciye Tasarımı ve Üretimi"},{"ad":"Bilgisayarlı Modelleme","s":[11],"dal":"Ayakkabı Tasarımı ve Üretimi, Saraciye Tasarımı ve Üretimi"},{"ad":"Ayakkabı Üretim Uygulamaları","s":[11],"dal":"Ayakkabı Tasarımı ve Üretimi"},{"ad":"Saraciye Üretim Uygulamaları","s":[11],"dal":"Saraciye Tasarımı ve Üretimi"}]},{"ad":"Bilişim Teknolojileri","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Programlama Temelleri","s":[9]},{"ad":"Bilişim Teknolojilerinin Temelleri","s":[9]},{"ad":"Bilgisayarlı Tasarım Uygulamaları","s":[9]},{"ad":"Nesne Tabanlı Programlama","s":[10],"dal":"Yazılım Geliştirme"},{"ad":"Robotik ve Kodlama","s":[10],"dal":"Yazılım Geliştirme, Ağ İşletmenliği"},{"ad":"Web Tabanlı Uygulama Geliştirme","s":[11],"dal":"Yazılım Geliştirme"},{"ad":"Mobil Uygulamalar","s":[11],"dal":"Yazılım Geliştirme"},{"ad":"Grafik ve Canlandırma","s":[11],"dal":"Yazılım Geliştirme"},{"ad":"Ağ Sistemleri ve Anahtarlama","s":[10],"dal":"Ağ İşletmenliği"},{"ad":"Ağ Sistemleri ve Yönlendirme","s":[11],"dal":"Ağ İşletmenliği"},{"ad":"Siber Güvenlik Temelleri","s":[11],"dal":"Ağ İşletmenliği"},{"ad":"Sunucu İşletim Sistemleri","s":[11],"dal":"Ağ İşletmenliği"}]},{"ad":"Biyomedikal Cihaz Teknolojileri","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Biyo Ölçme Atölyesi","s":[9]},{"ad":"Biyomedikal Teknik Resim","s":[10]},{"ad":"Mesleki Fizyoloji ve Terminoloji","s":[10]},{"ad":"Biyo Enstrümantasyon Atölyesi","s":[10]},{"ad":"Elektrofizyoloji Cihazları","s":[11],"dal":"Fizyolojik Sinyal İzleme Teşhis ve Kayıt Cihazları"},{"ad":"Teşhis Takip ve Kayıt Cihazları","s":[11],"dal":"Fizyolojik Sinyal İzleme Teşhis ve Kayıt Cihazları"},{"ad":"Biyo Sinyal İzleme ve Takip Cihazları Atölyesi","s":[11],"dal":"Fizyolojik Sinyal İzleme Teşhis ve Kayıt Cihazları"},{"ad":"X-Işınlı Görüntüleyiciler","s":[11],"dal":"Tıbbi Görüntüleme Sistemleri"},{"ad":"Klinik Görüntüleme Cihazları","s":[11],"dal":"Tıbbi Görüntüleme Sistemleri"},{"ad":"Radyoloji Cihazları Atölyesi","s":[11],"dal":"Tıbbi Görüntüleme Sistemleri"},{"ad":"Laboratuvar Destek Cihazları","s":[11],"dal":"Tıbbi Laboratuvar ve Hasta Dışı Uygulama Cihazları"},{"ad":"Analiz ve Laboratuvar Cihazları","s":[11],"dal":"Tıbbi Laboratuvar ve Hasta Dışı Uygulama Cihazları"},{"ad":"Klinik Laboratuvar Cihazları Atölyesi","s":[11],"dal":"Tıbbi Laboratuvar ve Hasta Dışı Uygulama Cihazları"},{"ad":"Ameliyathane ve Yoğun Bakım Cihazları","s":[11],"dal":"Yaşam Destek ve Tedavi Cihazları"},{"ad":"Acil Servis ve Destek Tedavi Cihazları","s":[11],"dal":"Yaşam Destek ve Tedavi Cihazları"},{"ad":"Yaşam Destek Cihazları Atölyesi","s":[11],"dal":"Yaşam Destek ve Tedavi Cihazları"}]},{"ad":"Büro Yönetimi ve Yönetici Asistanlığı","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Büro Teknolojileri","s":[9]},{"ad":"Klavye Teknikleri","s":[9]},{"ad":"Temel Hukuk","s":[9]},{"ad":"Büro Hizmetleri","s":[10]},{"ad":"Ofis Uygulamaları","s":[10]},{"ad":"Diksiyon ve Etkili İletişim","s":[10]},{"ad":"Hızlı Klavye Kullanımı","s":[10]},{"ad":"Yazışma ve Dosyalama Teknikleri","s":[11]},{"ad":"Tasarım Programları","s":[11]},{"ad":"İleri Ofis Uygulamaları","s":[11]},{"ad":"Web Uygulamaları","s":[11]},{"ad":"Dijital Ofis Uygulamaları","s":[11]},{"ad":"Mesleki Yabancı Dil","s":[11]}]},{"ad":"Denizcilik","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Temel Denizcilik Atölyesi","s":[9]},{"ad":"Yüzme","s":[9]},{"ad":"Gemi Elektroniği Atölyesi","s":[10],"dal":"Gemi Elektroniği ve Haberleşme"},{"ad":"Denizde Emniyet","s":[10],"dal":"Gemi Elektroniği ve Haberleşme, Gemi Makineleri İşletme, Güverte İşletme"},{"ad":"Mesleki Yabancı Dil (Denizcilik)","s":[10],"dal":"Gemi Elektroniği ve Haberleşme, Gemi Makineleri İşletme, Güverte İşletme"},{"ad":"Elektronik Seyir Sistemleri Atölyesi","s":[11],"dal":"Gemi Elektroniği ve Haberleşme"},{"ad":"Küresel Denizcilik Acil Durum Emniyet Haberleşme Sistemi (GMDSS)","s":[11],"dal":"Gemi Elektroniği ve Haberleşme, Güverte İşletme"},{"ad":"GMDSS Teknik","s":[11],"dal":"Gemi Elektroniği ve Haberleşme"},{"ad":"Temel Gemi Makineleri Atölyesi","s":[10],"dal":"Gemi Makineleri İşletme"},{"ad":"Gemi Makineleri Atölyesi","s":[11],"dal":"Gemi Makineleri İşletme"},{"ad":"Gemi Otomasyonu","s":[11],"dal":"Gemi Makineleri İşletme"},{"ad":"Mekanik ve Termodinamik","s":[11],"dal":"Gemi Makineleri İşletme"},{"ad":"Emniyetli Makine Vardiyası Tutma","s":[11],"dal":"Gemi Makineleri İşletme"},{"ad":"Deniz Hukuku","s":[11],"dal":"Gemi Makineleri İşletme, Güverte İşletme"},{"ad":"Gemicilik ve Gemi Manevraları Atölyesi","s":[10],"dal":"Güverte İşletme"},{"ad":"Meteoroloji","s":[10],"dal":"Güverte İşletme"},{"ad":"Seyir ve Elektronik Seyir Atölyesi","s":[11],"dal":"Güverte İşletme"},{"ad":"Yük İşlem","s":[11],"dal":"Güverte İşletme"}]},{"ad":"El Sanatları Teknolojisi","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Temel Teknikler Atölyesi","s":[9]},{"ad":"Desen Tasarımı","s":[10],"dal":"Dekoratif El Sanatları, Dekoratif Ev Tekstili"},{"ad":"Aksesuar Atölyesi","s":[10],"dal":"Dekoratif El Sanatları"},{"ad":"Ahşap Boyama Atölyesi","s":[11],"dal":"Dekoratif El Sanatları"},{"ad":"Geleneksel El Sanatları","s":[11],"dal":"Dekoratif El Sanatları, Dekoratif Ev Tekstili"},{"ad":"Prototip Ürünler ve Organizasyon Atölyesi","s":[11],"dal":"Dekoratif El Sanatları, Dekoratif Ev Tekstili"},{"ad":"Örgü Atölyesi","s":[11],"dal":"Dekoratif El Sanatları"},{"ad":"Ev Tekstili Üretimi Atölyesi","s":[10,11],"dal":"Dekoratif Ev Tekstili"},{"ad":"Süsleme Teknikleri Atölyesi","s":[10],"dal":"Dekoratif Ev Tekstili"}]},{"ad":"Elektrik-Elektronik Teknolojisi","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Temel Elektrik-Elektronik Atölyesi","s":[9]},{"ad":"Kumanda Teknikleri Atölyesi","s":[10],"dal":"Asansör Sistemleri"},{"ad":"Elektrik-Elektronik Esasları","s":[10],"dal":"Asansör Sistemleri, Elektrik Tesisatları ve Dağıtımı, Elektrikli Cihazlar Teknik Servisi, Elektronik ve Haberleşme, Endüstriyel Bakım Onarım"},{"ad":"Bilgisayarla Devre Dizaynı","s":[10],"dal":"Asansör Sistemleri, Elektrik Tesisatları ve Dağıtımı, Elektrikli Cihazlar Teknik Servisi, Elektronik ve Haberleşme, Endüstriyel Bakım Onarım"},{"ad":"Asansör Montaj Atölyesi","s":[11],"dal":"Asansör Sistemleri"},{"ad":"Asansör Bakım Onarımı","s":[11],"dal":"Asansör Sistemleri"},{"ad":"Bilgisayarlı Asansör Tasarım","s":[11],"dal":"Asansör Sistemleri"},{"ad":"Tesisat Atölyesi","s":[10],"dal":"Elektrik Tesisatları ve Dağıtımı"},{"ad":"Pano Atölyesi","s":[11],"dal":"Elektrik Tesisatları ve Dağıtımı"},{"ad":"Elektrik Tesisat Projeleri","s":[11],"dal":"Elektrik Tesisatları ve Dağıtımı"},{"ad":"Kontrol Panoları","s":[11],"dal":"Elektrik Tesisatları ve Dağıtımı"},{"ad":"Küçük Ev Aletleri Atölyesi","s":[10],"dal":"Elektrikli Cihazlar Teknik Servisi"},{"ad":"Tesisatlı Ev Cihazları Atölyesi","s":[11],"dal":"Elektrikli Cihazlar Teknik Servisi"},{"ad":"Soğutucular ve Klimalar","s":[11],"dal":"Elektrikli Cihazlar Teknik Servisi"},{"ad":"Elektronik Atölyesi","s":[10],"dal":"Elektronik ve Haberleşme"},{"ad":"Mikrodenetleyiciler ve Güvenlik Atölyesi","s":[11],"dal":"Elektronik ve Haberleşme"},{"ad":"Görüntü Sistemleri","s":[11],"dal":"Elektronik ve Haberleşme"},{"ad":"Haberleşme","s":[11],"dal":"Elektronik ve Haberleşme"},{"ad":"Kumanda ve Kontrol Atölyesi","s":[10],"dal":"Endüstriyel Bakım Onarım"},{"ad":"Otomasyon Atölyesi","s":[11],"dal":"Endüstriyel Bakım Onarım"},{"ad":"Endüstriyel Elektronik","s":[11],"dal":"Endüstriyel Bakım Onarım"},{"ad":"Mikrodenetleyici ve Kodlama","s":[11],"dal":"Endüstriyel Bakım Onarım"}]},{"ad":"Endüstriyel Kalite Kontrol","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Teknik Resim","s":[9]},{"ad":"Temel Elektrik","s":[9]},{"ad":"Temel İmalat ve Ölçme Teknikleri","s":[9]},{"ad":"Malzeme ve Üretim Yöntemleri","s":[10]},{"ad":"Endüstriyel Kalite","s":[10]},{"ad":"Tahribatlı Malzeme Muayene","s":[10]},{"ad":"Makine Elemanları","s":[11]},{"ad":"Tahribatsız Malzeme Muayene","s":[11]},{"ad":"Bilgisayar Destekli Tasarım","s":[11]},{"ad":"Metalografi Laboratuvarı","s":[11]}]},{"ad":"Endüstriyel Otomasyon Teknolojileri","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Elektroteknik","s":[9]},{"ad":"Endüstriyel Otomasyon Teknolojileri Atölye","s":[9,10,11]},{"ad":"Modelleme ve Montaj","s":[10],"dal":"Endüstriyel Otomasyon Teknolojileri"},{"ad":"Sensörler","s":[10],"dal":"Endüstriyel Otomasyon Teknolojileri"},{"ad":"Mekatronik Sistemler","s":[11],"dal":"Endüstriyel Otomasyon Teknolojileri"},{"ad":"Otomatik Üretim","s":[11],"dal":"Endüstriyel Otomasyon Teknolojileri"}]},{"ad":"Gazetecilik","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Etkili Konuşma ve Yazma","s":[9]},{"ad":"Temel Fotoğraf","s":[9]},{"ad":"Haber Toplama ve Yazma","s":[9]},{"ad":"Haber Fotoğrafçılığı","s":[10],"dal":"Gazetecilik"},{"ad":"Dijital Görsel Düzenleme","s":[10],"dal":"Gazetecilik"},{"ad":"Yayın Türüne Göre Gazetecilik","s":[10],"dal":"Gazetecilik"},{"ad":"Redaksiyon","s":[11,12],"dal":"Gazetecilik"},{"ad":"Haber Çekimi ve Kurgusu","s":[11],"dal":"Gazetecilik"},{"ad":"Dijital Medyada Habercilik","s":[11],"dal":"Gazetecilik"},{"ad":"Mizanpaj","s":[11,12],"dal":"Gazetecilik"}]},{"ad":"Geleneksel Türk Sanatları","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Temel Geleneksel Türk Sanatları","s":[9]},{"ad":"Osmanlı Türkçesi","s":[9]},{"ad":"Temel Tasarım","s":[9]},{"ad":"Geleneksel Türk Sanatları Desen","s":[9]},{"ad":"Hüsn-i Hat Atölyesi","s":[10],"dal":"Geleneksel Türk Sanatları"},{"ad":"Ebru Atölyesi","s":[10],"dal":"Geleneksel Türk Sanatları"},{"ad":"Minyatür Atölyesi","s":[10],"dal":"Geleneksel Türk Sanatları"},{"ad":"Kātı\' Atölyesi","s":[10],"dal":"Geleneksel Türk Sanatları"},{"ad":"Tezhip Atölyesi","s":[11],"dal":"Geleneksel Türk Sanatları"},{"ad":"Çini Atölyesi","s":[11],"dal":"Geleneksel Türk Sanatları"},{"ad":"Kalemişi Atölyesi","s":[11],"dal":"Geleneksel Türk Sanatları"},{"ad":"Cilt Atölyesi","s":[11],"dal":"Geleneksel Türk Sanatları"}]},{"ad":"Gemi Yapımı","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Teknik Resim","s":[9]},{"ad":"Gemi Temel İşlemleri","s":[9]},{"ad":"Endaze Çizimi","s":[10],"dal":"Gemi İnşa, Yat İnşa, Gemi Donatım"},{"ad":"Gemi İmalatı","s":[10],"dal":"Gemi İnşa"},{"ad":"Gemi İmalatı Resmi","s":[11],"dal":"Gemi İnşa"},{"ad":"Gemi Bakım Onarımı","s":[11],"dal":"Gemi İnşa"},{"ad":"Gemi Kaynak Uygulamaları","s":[11],"dal":"Gemi İnşa"},{"ad":"Ahşap Yat İmalatı","s":[10],"dal":"Yat İnşa"},{"ad":"Yat İmalatı Resmi","s":[11],"dal":"Yat İnşa"},{"ad":"Yat Bakım Onarımı","s":[11],"dal":"Yat İnşa"},{"ad":"Kompozit Yat İmalatı","s":[11],"dal":"Yat İnşa"},{"ad":"Gemi Donatım İmalatı","s":[10],"dal":"Gemi Donatım"},{"ad":"Gemi Donatımı Resmi","s":[11],"dal":"Gemi Donatım"},{"ad":"Gemi Boru İşçiliği","s":[11],"dal":"Gemi Donatım"},{"ad":"Gemi Makine Donatımı","s":[11],"dal":"Gemi Donatım"}]},{"ad":"Grafik ve Fotoğraf","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Temel Fotoğraf","s":[9]},{"ad":"Temel Tasarım","s":[9]},{"ad":"Bilgisayarda Grafik Tasarım","s":[10],"dal":"Grafik"},{"ad":"Grafik Desen","s":[10],"dal":"Grafik"},{"ad":"Perspektif ve Desen","s":[10],"dal":"Grafik"},{"ad":"Fotoğraf Çekimi","s":[10],"dal":"Fotoğraf"},{"ad":"Fotoğrafta Perspektif","s":[10],"dal":"Fotoğraf"},{"ad":"Fotoğraf Yorumlama","s":[10],"dal":"Fotoğraf"},{"ad":"İllüstrasyon Uygulamaları","s":[11],"dal":"Grafik"},{"ad":"Tanıtım Grafiği","s":[11],"dal":"Grafik"},{"ad":"Yayın Grafiği","s":[11],"dal":"Grafik"},{"ad":"Bilgisayarda Fotoğraf","s":[11],"dal":"Fotoğraf"},{"ad":"Çekim Teknikleri ve Sunumu","s":[11],"dal":"Fotoğraf"},{"ad":"Video Çekimi","s":[11],"dal":"Fotoğraf"}]},{"ad":"Güzellik Hizmetleri","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Temel Bakım Atölyesi","s":[9]},{"ad":"Temel Anatomi ve Fizyoloji","s":[9]},{"ad":"Mesleki Resim","s":[10],"dal":"Güzellik Hizmetleri"},{"ad":"Cilt Bakım Uygulamaları Atölyesi","s":[10,11],"dal":"Güzellik Hizmetleri"},{"ad":"Makyaj Uygulamaları Atölyesi","s":[10,11],"dal":"Güzellik Hizmetleri"},{"ad":"Müşteri İlişkileri Yönetimi","s":[11],"dal":"Güzellik Hizmetleri"}]},{"ad":"Gıda Teknolojisi","dersler":[{"ad":"Gıda Mikrobiyolojisi","s":[9]},{"ad":"Laboratuvar Tekniği","s":[9]},{"ad":"Gıda Hijyeni ve Sanitasyon","s":[9]},{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Gıdalarda Mikrobiyolojik Analizler","s":[10],"dal":"Gıda Teknolojisi"},{"ad":"Gıda Kimyası","s":[10],"dal":"Gıda Teknolojisi"},{"ad":"Maddede Temel Değişim ve Ölçümler","s":[10],"dal":"Gıda Teknolojisi"},{"ad":"Gıda Muhafaza ve Ambalajlama Teknikleri","s":[10],"dal":"Gıda Teknolojisi"},{"ad":"Gıdalarda Temel Kalite Kontrol Analizleri","s":[11],"dal":"Gıda Teknolojisi"},{"ad":"Gıda Üretim Teknolojileri","s":[11,12],"dal":"Gıda Teknolojisi"},{"ad":"Gıda İşleme Makineleri","s":[11,12],"dal":"Gıda Teknolojisi"}]},{"ad":"Halkla İlişkiler","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Halkla İlişkilerde Yazma ve Konuşma","s":[9]},{"ad":"Halkla İlişkiler ve İletişim","s":[9]},{"ad":"Ofis Uygulamaları","s":[9]},{"ad":"Halkla İlişkiler ve Meslek Uygulamaları","s":[10],"dal":"Halkla İlişkiler"},{"ad":"Halkla İlişkiler ve Medya","s":[10],"dal":"Halkla İlişkiler"},{"ad":"Mesleki Hukuk","s":[10],"dal":"Halkla İlişkiler"},{"ad":"Halkla İlişkilerde Bütünleşik Pazarlama İletişimi","s":[11],"dal":"Halkla İlişkiler"},{"ad":"Halkla İlişkiler ve Reklam","s":[11],"dal":"Halkla İlişkiler"},{"ad":"Yeni Medyada Halkla İlişkiler","s":[11],"dal":"Halkla İlişkiler"},{"ad":"Çağrı Merkezi İşlemleri","s":[11],"dal":"Halkla İlişkiler"}]},{"ad":"Harita-Tapu-Kadastro","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Medeni Hukuk","s":[9]},{"ad":"Temel Mesleki Uygulamalar","s":[9]},{"ad":"Harita Hesapları","s":[10],"dal":"Harita Kadastro"},{"ad":"Harita-Tapu-Kadastro Uygulama","s":[10,11],"dal":"Harita Kadastro, Tapu"},{"ad":"Bilgisayarla Harita Çizimi","s":[11],"dal":"Harita Kadastro"},{"ad":"İmar","s":[11],"dal":"Harita Kadastro"},{"ad":"Kadastro","s":[11],"dal":"Harita Kadastro"},{"ad":"Ceza Hukuku","s":[10],"dal":"Tapu"},{"ad":"İdare Hukuku","s":[10],"dal":"Tapu"},{"ad":"Borçlar Hukuku","s":[11],"dal":"Tapu"},{"ad":"Medeni Usul ve İcra İflas Hukuku","s":[11],"dal":"Tapu"},{"ad":"Miras ve Ayni Haklar Hukuku","s":[11],"dal":"Tapu"}]},{"ad":"Hasta ve Yaşlı Hizmetleri","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Anatomi ve Fizyoloji","s":[9]},{"ad":"Hasta ve Yaşlı Hizmetleri Atölye","s":[9,10,11]},{"ad":"Enfeksiyon Hastalıklarından Korunma","s":[10],"dal":"Hasta ve Yaşlı Bakımı"},{"ad":"Sağlık İletişimi ve Etik","s":[10],"dal":"Hasta ve Yaşlı Bakımı"},{"ad":"Genel Beslenme","s":[10],"dal":"Hasta ve Yaşlı Bakımı"},{"ad":"Hasta ve Yaşlı Psikolojisi","s":[11],"dal":"Hasta ve Yaşlı Bakımı"},{"ad":"Hastalıklar ve İlaç Bilgisi","s":[11],"dal":"Hasta ve Yaşlı Bakımı"}]},{"ad":"Havacılık ve Uzay Teknolojisi","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Malzeme Teknolojileri","s":[9]},{"ad":"Sistem Tasarım Temelleri","s":[9]},{"ad":"Montaj, Entegrasyon ve Kontrol","s":[9]},{"ad":"Bilgi, Çalışma ve Malzeme Güvenliği","s":[10],"dal":"Tasarım ve İmalat, İtki Sistemleri, Elektronik Sistemler"},{"ad":"Bilgisayar Destekli Tasarım ve Modelleme","s":[10],"dal":"Tasarım ve İmalat, İtki Sistemleri, Elektronik Sistemler"},{"ad":"Havacılık ve Uzay Bilimlerine Giriş","s":[10],"dal":"Tasarım ve İmalat, İtki Sistemleri, Elektronik Sistemler"},{"ad":"Kompozit Üretimi ve Analizi Atölyesi","s":[10],"dal":"Tasarım ve İmalat"},{"ad":"İmalat Teknikleri Atölyesi","s":[11],"dal":"Tasarım ve İmalat"},{"ad":"Özel Prosesler","s":[12],"dal":"Tasarım ve İmalat"},{"ad":"Hidrolik-Pnömatik ve Mekanik Sistemler","s":[12],"dal":"Tasarım ve İmalat"},{"ad":"İtki Sistemleri Atölyesi","s":[10],"dal":"İtki Sistemleri"},{"ad":"Yanma ve Yakıtlar","s":[11],"dal":"İtki Sistemleri"},{"ad":"İleri İtki Sistemleri Atölyesi","s":[11,12],"dal":"İtki Sistemleri"},{"ad":"Pervane Atölyesi","s":[12],"dal":"İtki Sistemleri"},{"ad":"Temel Elektronik","s":[10],"dal":"Elektronik Sistemler"},{"ad":"Elektronik Kart Tasarımı","s":[10],"dal":"Elektronik Sistemler"},{"ad":"Elektrik Sistemleri Atölyesi","s":[11],"dal":"Elektronik Sistemler"},{"ad":"Elektronik Sistem Üretimi Atölyesi","s":[11],"dal":"Elektronik Sistemler"},{"ad":"Dijital Uygulamalar Atölyesi","s":[12],"dal":"Elektronik Sistemler"},{"ad":"Havacılık ve Uzay Aviyonik Sistemleri Atölyesi","s":[12],"dal":"Elektronik Sistemler"}]},{"ad":"Hayvan Yetiştiriciliği ve Sağlığı","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Anatomi ve Fizyoloji Laboratuvarı","s":[9]},{"ad":"Hayvancılığa Giriş","s":[9]},{"ad":"Mikrobiyoloji ve Hijyen","s":[10],"dal":"Hayvan Yetiştiriciliği ve Sağlığı"},{"ad":"Zootekni","s":[10],"dal":"Hayvan Yetiştiriciliği ve Sağlığı"},{"ad":"Hayvan Yetiştiriciliği ve Sağlığı Uygulama","s":[10,11],"dal":"Hayvan Yetiştiriciliği ve Sağlığı"},{"ad":"Hastalıklarla Mücadele","s":[11],"dal":"Hayvan Yetiştiriciliği ve Sağlığı"},{"ad":"Klinik Hizmetleri","s":[11],"dal":"Hayvan Yetiştiriciliği ve Sağlığı"},{"ad":"Suni Tohumlama ve Doğum","s":[11],"dal":"Hayvan Yetiştiriciliği ve Sağlığı"}]},{"ad":"Kimya Teknolojisi","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Temel Kimya","s":[9]},{"ad":"Kimyada Mesleki Hesaplamalar","s":[9]},{"ad":"Kimyasal Kinetik","s":[10],"dal":"Kimya Laboratuvarı, Proses"},{"ad":"Nitel Analiz","s":[10],"dal":"Kimya Laboratuvarı"},{"ad":"Sınai Kimya","s":[10],"dal":"Kimya Laboratuvarı"},{"ad":"Organik Kimya","s":[11],"dal":"Kimya Laboratuvarı, Petrol Endüstrisi, Proses"},{"ad":"Nicel Analiz","s":[11],"dal":"Kimya Laboratuvarı"},{"ad":"Enstrümantal Analiz","s":[11],"dal":"Kimya Laboratuvarı"},{"ad":"Petrol Teknolojisine Giriş","s":[10],"dal":"Petrol Endüstrisi"},{"ad":"Petrol Teknolojisinde Kontrol","s":[10],"dal":"Petrol Endüstrisi"},{"ad":"Proseste Numune, Hava ve Su","s":[11],"dal":"Petrol Endüstrisi"},{"ad":"Petrol Endüstrisi Prosesleri","s":[11],"dal":"Petrol Endüstrisi"},{"ad":"Proseste Temel İşlemler","s":[10],"dal":"Proses"},{"ad":"Proseste Akış ve Kontrol","s":[11],"dal":"Proses"},{"ad":"Ünit Operasyonlar","s":[11],"dal":"Proses"}]},{"ad":"Konaklama ve Seyahat Hizmetleri","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Genel Turizm","s":[9]},{"ad":"Konaklama ve Seyahat Hizmetleri Atölyesi","s":[9]},{"ad":"Önbüroda Rezervasyon","s":[10],"dal":"Konaklama Hizmetleri"},{"ad":"Konuk Giriş Çıkış İşlemleri","s":[10],"dal":"Konaklama Hizmetleri"},{"ad":"Kat Hizmetleri Atölyesi","s":[10,11],"dal":"Konaklama Hizmetleri"},{"ad":"Önbüro Hizmetleri Atölyesi","s":[10,11],"dal":"Konaklama Hizmetleri"},{"ad":"Sürdürülebilir Turizm","s":[11],"dal":"Konaklama Hizmetleri, Seyahat Acenteciliği"},{"ad":"Konaklama İşletmeciliği","s":[11],"dal":"Konaklama Hizmetleri"},{"ad":"Mesleki Yabancı Dil","s":[11],"dal":"Konaklama Hizmetleri, Seyahat Acenteciliği, Animatörlük"},{"ad":"Turist Rehberliği","s":[10],"dal":"Seyahat Acenteciliği"},{"ad":"Seyahat Acenteciliği Atölyesi","s":[10],"dal":"Seyahat Acenteciliği"},{"ad":"Anadolu Kültürü","s":[11],"dal":"Seyahat Acenteciliği"},{"ad":"Acente Paket Programları Atölyesi","s":[11],"dal":"Seyahat Acenteciliği"},{"ad":"Rezervasyon ve İstatistiki İşlemler Atölyesi","s":[11],"dal":"Seyahat Acenteciliği"},{"ad":"Animasyon Hizmetleri","s":[10],"dal":"Animatörlük"},{"ad":"Özel Organizasyon Uygulamaları","s":[10],"dal":"Animatörlük"},{"ad":"Animasyon Aktiviteleri Atölyesi","s":[10],"dal":"Animatörlük"},{"ad":"Animasyonda Spor Aktiviteleri Atölyesi","s":[10],"dal":"Animatörlük"},{"ad":"Animasyonda Teknik Destek","s":[11],"dal":"Animatörlük"},{"ad":"Çalışma Hayatına Hazırlık","s":[11],"dal":"Animatörlük"},{"ad":"Sahne Uygulamaları Atölyesi","s":[11],"dal":"Animatörlük"},{"ad":"Animasyonda Dans ve Müzik Aktiviteleri Atölyesi","s":[11],"dal":"Animatörlük"}]},{"ad":"Kuyumculuk Teknolojisi","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Kuyumculuk Teknolojisi Atölye","s":[9,10,11]},{"ad":"Takıda Teknik Resim","s":[9]},{"ad":"Takıda Temel Tasarım","s":[10],"dal":"Takı İmalatı"},{"ad":"Takı Etüdü ve Çizimi","s":[10],"dal":"Takı İmalatı"},{"ad":"Mum Modelaj ve Döküm","s":[11],"dal":"Takı İmalatı"},{"ad":"Bilgisayarda Takı Tasarımı","s":[11],"dal":"Takı İmalatı"}]},{"ad":"Laboratuvar Hizmetleri","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Laboratuvar Güvenliği ve Analizlere Hazırlık","s":[9]},{"ad":"Laboratuvar Temel İşlemleri","s":[9]},{"ad":"Mikrobiyolojik Analizler","s":[10],"dal":"Gıda, Tarım ve Hayvan Sağlığı Laboratuvarı"},{"ad":"Tarım Analizleri","s":[10],"dal":"Gıda, Tarım ve Hayvan Sağlığı Laboratuvarı"},{"ad":"Gıda Analizleri","s":[11],"dal":"Gıda, Tarım ve Hayvan Sağlığı Laboratuvarı"},{"ad":"Hayvan Sağlığı Laboratuvarı","s":[11],"dal":"Gıda, Tarım ve Hayvan Sağlığı Laboratuvarı"},{"ad":"Yem Analizleri","s":[11],"dal":"Gıda, Tarım ve Hayvan Sağlığı Laboratuvarı"}]},{"ad":"Maden Teknolojisi","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Genel Jeoloji","s":[9]},{"ad":"Makine Elemanları ve Maden Makineleri","s":[9]},{"ad":"Teknik Resim","s":[9]},{"ad":"Madenciliğe Giriş","s":[9]},{"ad":"Temel Mekanik İşlemler","s":[10],"dal":"Madencilik"},{"ad":"Yer Altı Maden İşlemleri","s":[10],"dal":"Madencilik"},{"ad":"Maden Topoğrafyası","s":[10],"dal":"Madencilik"},{"ad":"Bilgisayar Destekli Çizim","s":[10],"dal":"Madencilik"},{"ad":"Yer Üstü Maden İşlemleri","s":[11],"dal":"Madencilik"},{"ad":"Sondaj","s":[11],"dal":"Madencilik"},{"ad":"Hidrolik Pnömatik","s":[11],"dal":"Madencilik"},{"ad":"Temel Elektrik","s":[11],"dal":"Madencilik"},{"ad":"Cevher Zenginleştirme","s":[11],"dal":"Madencilik"}]},{"ad":"Makine ve Tasarım Teknolojisi","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Teknik Resim","s":[9]},{"ad":"Temel İmalat İşlemleri","s":[9]},{"ad":"İmalat İşlemleri","s":[10],"dal":"Bilgisayarlı Makine İmalatı"},{"ad":"Makine Meslek Resmi","s":[10],"dal":"Bilgisayarlı Makine İmalatı"},{"ad":"Bilgisayar Destekli Çizim","s":[10,11],"dal":"Bilgisayarlı Makine İmalatı, Bilgisayar Destekli Makine Ressamlığı, Değirmencilik"},{"ad":"Bilgisayar Kontrollü Üretim (CNC/CAM)","s":[11],"dal":"Bilgisayarlı Makine İmalatı"},{"ad":"Bilgisayar Destekli Tasarım (CAD)","s":[11],"dal":"Bilgisayarlı Makine İmalatı"},{"ad":"Yenilikçi Üretim Yöntemleri","s":[11],"dal":"Bilgisayarlı Makine İmalatı"},{"ad":"Makine Elemanları ve Mekanizmalar","s":[10],"dal":"Bilgisayar Destekli Makine Ressamlığı"},{"ad":"Tasarı Geometri","s":[10],"dal":"Bilgisayar Destekli Makine Ressamlığı"},{"ad":"Katı Modelleme ve Animasyon","s":[11],"dal":"Bilgisayar Destekli Makine Ressamlığı"},{"ad":"Kalıp Tasarımı","s":[11],"dal":"Bilgisayar Destekli Makine Ressamlığı"},{"ad":"Hidrolik-Pnömatik Devre Çizimleri","s":[11],"dal":"Bilgisayar Destekli Makine Ressamlığı"},{"ad":"Cisimlerin Dayanımı","s":[11],"dal":"Bilgisayar Destekli Makine Ressamlığı"},{"ad":"Sac Metal Kalıpları","s":[10],"dal":"Endüstriyel Kalıp"},{"ad":"Kalıp Meslek Resmi","s":[10],"dal":"Endüstriyel Kalıp"},{"ad":"Hacim Kalıpları","s":[11],"dal":"Endüstriyel Kalıp"},{"ad":"İş Kalıpları","s":[11],"dal":"Endüstriyel Kalıp"},{"ad":"Kalıp Üretim Teknolojisi","s":[11],"dal":"Endüstriyel Kalıp"},{"ad":"İmalat Yöntemleri","s":[10],"dal":"Makine Bakım Onarım"},{"ad":"Bakım Onarım Meslek Resmi","s":[10],"dal":"Makine Bakım Onarım, Değirmencilik"},{"ad":"Ölçme ve Kontrol","s":[10],"dal":"Makine Bakım Onarım"},{"ad":"Malzeme Bilgisi","s":[10],"dal":"Makine Bakım Onarım"},{"ad":"Mekanik Bakım Onarım","s":[10,11],"dal":"Makine Bakım Onarım, Değirmencilik"},{"ad":"Otomatik Kontrol Sistemleri","s":[11],"dal":"Makine Bakım Onarım"},{"ad":"Mekanizmalar","s":[11],"dal":"Makine Bakım Onarım"},{"ad":"Temel Elektrik","s":[11],"dal":"Makine Bakım Onarım, Değirmencilik"},{"ad":"Tıbbi Cihaz Üretiminde Metal İşlemleri","s":[10],"dal":"Tıbbi Cihaz Üretimi"},{"ad":"Kalıp İmalatı Uygulamaları","s":[10],"dal":"Tıbbi Cihaz Üretimi"},{"ad":"Tıbbi Cihaz Üretim Terminolojisi","s":[10],"dal":"Tıbbi Cihaz Üretimi"},{"ad":"Bilgisayar Destekli Tıbbi Cihaz Çizimi","s":[10],"dal":"Tıbbi Cihaz Üretimi"},{"ad":"Bilgisayar Kontrollü Tezgâhlarla Tıbbi Cihaz Üretimi","s":[11],"dal":"Tıbbi Cihaz Üretimi"},{"ad":"Bilgisayar Destekli Tıbbi Cihaz Tasarımı ve Üretimi","s":[11],"dal":"Tıbbi Cihaz Üretimi"},{"ad":"Tıbbi Cihaz Meslek Resmi","s":[11],"dal":"Tıbbi Cihaz Üretimi"},{"ad":"AR-GE ve Kalite Kontrol","s":[11],"dal":"Tıbbi Cihaz Üretimi, Değirmencilik"},{"ad":"Temel Modelleme","s":[10],"dal":"Bilgisayar Destekli Endüstriyel Modelleme"},{"ad":"Model Meslek Resmi","s":[10],"dal":"Bilgisayar Destekli Endüstriyel Modelleme"},{"ad":"Model Meslek Teknolojisi","s":[10],"dal":"Bilgisayar Destekli Endüstriyel Modelleme"},{"ad":"Bilgisayar Destekli Modellemeler","s":[11],"dal":"Bilgisayar Destekli Endüstriyel Modelleme"},{"ad":"Bilgisayar Destekli Seri Üretim Modellemeleri","s":[11],"dal":"Bilgisayar Destekli Endüstriyel Modelleme"},{"ad":"Kompozit Modelleme Teknikleri","s":[11],"dal":"Bilgisayar Destekli Endüstriyel Modelleme"},{"ad":"Tasarım Tarihi ve Ergonomi","s":[10],"dal":"Endüstriyel Ürünler Tasarımı"},{"ad":"Fotoğraf ve Sunum","s":[10],"dal":"Endüstriyel Ürünler Tasarımı"},{"ad":"Tasarım Atölyesi","s":[10],"dal":"Endüstriyel Ürünler Tasarımı"},{"ad":"Model ve Prototip Atölyesi","s":[11],"dal":"Endüstriyel Ürünler Tasarımı"},{"ad":"Katı Modelleme","s":[11],"dal":"Endüstriyel Ürünler Tasarımı"},{"ad":"Tarama ve Sayısallaştırma","s":[11],"dal":"Endüstriyel Ürünler Tasarımı"},{"ad":"Ürün Geliştirme Uygulamaları","s":[11],"dal":"Endüstriyel Ürünler Tasarımı"},{"ad":"Öğütme Teknolojisi","s":[10],"dal":"Değirmencilik"},{"ad":"Değirmen Makineleri","s":[11],"dal":"Değirmencilik"}]},{"ad":"Matbaa Teknolojisi","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Baskı Öncesi İşlemler","s":[9]},{"ad":"Baskı ve Baskı Sonrası İşlemler","s":[9]},{"ad":"Görüntü İşleme","s":[10],"dal":"Baskı Öncesi"},{"ad":"Vektörel Çizim","s":[10],"dal":"Baskı Öncesi"},{"ad":"Bilgisayardan Kalıba Çıkış","s":[11],"dal":"Baskı Öncesi"},{"ad":"Dijital Baskı","s":[11],"dal":"Baskı Öncesi"},{"ad":"Vektörel Çizim Uygulamaları","s":[11],"dal":"Baskı Öncesi"},{"ad":"Baskı Sonrası Teknikleri","s":[10],"dal":"Ofset Baskı Sistemleri"},{"ad":"Ofset Baskı Teknikleri","s":[10],"dal":"Ofset Baskı Sistemleri"},{"ad":"Ofset Baskı Uygulamaları","s":[11],"dal":"Ofset Baskı Sistemleri"},{"ad":"Ciltleme Teknikleri","s":[12],"dal":"Ofset Baskı Sistemleri"}]},{"ad":"Metal Teknolojisi","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Meslek Teknolojisi","s":[9]},{"ad":"Metal Teknolojisi Atölye","s":[9]},{"ad":"Teknik Resim","s":[9]},{"ad":"Temel Kaynak Teknolojisi","s":[10]},{"ad":"Temel Kaynak Atölyesi","s":[10]},{"ad":"Bilgisayarda Katı Modelleme","s":[10]},{"ad":"Ark Kaynak Teknolojisi","s":[11],"dal":"Kaynakçılık"},{"ad":"Ark Kaynak Atölyesi","s":[11],"dal":"Kaynakçılık"},{"ad":"Gaz Korumalı Kaynak Teknolojisi","s":[11],"dal":"Kaynakçılık, Metal Doğrama"},{"ad":"Gaz Korumalı Kaynak Atölyesi","s":[11],"dal":"Kaynakçılık, Metal Doğrama"},{"ad":"Metal Doğrama Teknolojisi","s":[11],"dal":"Metal Doğrama"},{"ad":"Metal Doğrama Atölyesi","s":[11],"dal":"Metal Doğrama"}]},{"ad":"Metalürji Teknolojisi","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Meslek Teknolojisi","s":[9,10,11]},{"ad":"Metalürji Teknolojisi Atölye","s":[9,10,11]},{"ad":"Teknik Resim","s":[9]},{"ad":"Bilgisayar Destekli Katı Modelleme","s":[10]},{"ad":"Döküm Laboratuvarı","s":[11],"dal":"Döküm"},{"ad":"Bilgisayar Destekli Döküm Meslek Resmi","s":[11],"dal":"Döküm"},{"ad":"Kalite Kontrol","s":[11],"dal":"İzabe ve Haddecilik"},{"ad":"Bilgisayar Destekli İzabe Meslek Resmi","s":[11],"dal":"İzabe ve Haddecilik"}]},{"ad":"Mikromekanik","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Temel Mikro İmalat İşlemleri","s":[9]},{"ad":"Teknik Resim","s":[9]},{"ad":"Bilgisayar Destekli Çizim","s":[10],"dal":"Mikromekanik ve Saatçilik"},{"ad":"Temel Mikromekanik","s":[10],"dal":"Mikromekanik ve Saatçilik"},{"ad":"Mikromekanikte Talaşlı İmalat","s":[10],"dal":"Mikromekanik ve Saatçilik"},{"ad":"Yenilikçi Üretim Yöntemleri","s":[11],"dal":"Mikromekanik ve Saatçilik"},{"ad":"Mikromekanikte Bilgisayar Destekli Çizim ve İmalat","s":[11],"dal":"Mikromekanik ve Saatçilik"},{"ad":"Mikromekanik Sistem Bakım Onarımı","s":[11],"dal":"Mikromekanik ve Saatçilik"}]},{"ad":"Mobilya ve İç Mekân Tasarımı","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Mobilya Teknik Resmi","s":[9]},{"ad":"Birleştirme Teknikleri","s":[9]},{"ad":"Makine Bilgisi","s":[10],"dal":"Mobilya Üretim Teknolojisi"},{"ad":"Bilgisayarlı Temel Mobilya Çizimi","s":[10],"dal":"Mobilya Üretim Teknolojisi"},{"ad":"Masif Mobilya Üretimi","s":[10],"dal":"Mobilya Üretim Teknolojisi"},{"ad":"Tasarım ve Malzeme Bilgisi","s":[11],"dal":"Mobilya Üretim Teknolojisi"},{"ad":"Bilgisayar Kontrollü Makinelerde Üretim (CNC)","s":[11],"dal":"Mobilya Üretim Teknolojisi, Mobilya İç Mekân Ressamlığı"},{"ad":"Bilgisayarlı İç Mekân Resmi","s":[11],"dal":"Mobilya Üretim Teknolojisi"},{"ad":"Panel Mobilya Üretimi","s":[11],"dal":"Mobilya Üretim Teknolojisi"},{"ad":"Temel Tasarım ve Süsleme","s":[10],"dal":"Mobilya İç Mekân Ressamlığı"},{"ad":"Mekân Yardımcı Gereçleri","s":[10],"dal":"Mobilya İç Mekân Ressamlığı"},{"ad":"Bilgisayarlı Mobilya Tasarımı","s":[10],"dal":"Mobilya İç Mekân Ressamlığı"},{"ad":"Üç Boyutlu Görsel Sunum Teknikleri","s":[11],"dal":"Mobilya İç Mekân Ressamlığı"},{"ad":"Bilgisayarlı Mekân Tasarımı","s":[11],"dal":"Mobilya İç Mekân Ressamlığı"}]},{"ad":"Moda Tasarım Teknolojileri","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Moda Tasarım Teknolojileri Atölye","s":[9]},{"ad":"Model Analizi ve Model Geliştirme","s":[9]},{"ad":"Giysi Kalıp Tasarımı ve Üretimi Atölyesi","s":[10,11],"dal":"Giysi Kalıp Tasarımı ve Üretimi"},{"ad":"Modada Temel Sanat","s":[10],"dal":"Giysi Kalıp Tasarımı ve Üretimi, Terzilik"},{"ad":"Model Tasarımı","s":[11],"dal":"Giysi Kalıp Tasarımı ve Üretimi"},{"ad":"Terzilik Atölyesi","s":[10,11],"dal":"Terzilik"},{"ad":"Giyim Aksesuarları","s":[11],"dal":"Terzilik"}]},{"ad":"Motorlu Araçlar Teknolojisi","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Araç Teknolojisi Atölyesi","s":[9]},{"ad":"Elektrikli Araç Teknolojisi Atölyesi","s":[10],"dal":"Elektrikli Araçlar"},{"ad":"Elektrik Makineleri ve Dönüştürücüler","s":[10],"dal":"Elektrikli Araçlar"},{"ad":"Otomotiv Teknik Resim","s":[10],"dal":"Elektrikli Araçlar, Otomotiv Elektromekanik, İş Makineleri, Otomotiv Boya, Otomotiv Gövde"},{"ad":"Elektrikli Araç Hareket Sistemleri Atölyesi","s":[11],"dal":"Elektrikli Araçlar"},{"ad":"Batarya Teknolojisi","s":[11],"dal":"Elektrikli Araçlar"},{"ad":"Otomotiv Meslek Resmi","s":[11],"dal":"Elektrikli Araçlar, Otomotiv Elektromekanik, İş Makineleri, Otomotiv Boya, Otomotiv Gövde"},{"ad":"Otonom Araç Teknolojisi","s":[11],"dal":"Elektrikli Araçlar"},{"ad":"Otomotiv Elektromekanik Atölyesi","s":[10],"dal":"Otomotiv Elektromekanik"},{"ad":"Araçlarda Hidrolik ve Pnömatik Sistemler","s":[10,11],"dal":"Otomotiv Elektromekanik, İş Makineleri, Otomotiv Boya, Otomotiv Gövde"},{"ad":"Hareket Kontrol Sistemleri Atölyesi","s":[11],"dal":"Otomotiv Elektromekanik"},{"ad":"Dizel Yakıt Sistemleri","s":[11],"dal":"Otomotiv Elektromekanik"},{"ad":"İş Makineleri Teknolojisi Atölyesi","s":[10],"dal":"İş Makineleri"},{"ad":"İş Makineleri Hidroelektrik Atölyesi","s":[11],"dal":"İş Makineleri"},{"ad":"İş Makineleri Hareket Kontrol Sistemleri","s":[11],"dal":"İş Makineleri"},{"ad":"Otomotiv Temel Boya Atölyesi","s":[10],"dal":"Otomotiv Boya"},{"ad":"Otomotiv Boya Teknolojisi","s":[10],"dal":"Otomotiv Boya"},{"ad":"Otomotiv Boya Atölyesi","s":[11],"dal":"Otomotiv Boya"},{"ad":"Otomotiv Boya Renk Uygulamaları","s":[11],"dal":"Otomotiv Boya"},{"ad":"Otomotiv Gövde Kaynak Atölyesi","s":[10],"dal":"Otomotiv Gövde"},{"ad":"Otomotiv Gövde Teknolojisi","s":[10],"dal":"Otomotiv Gövde"},{"ad":"Otomotiv Gövde Onarım Atölyesi","s":[11],"dal":"Otomotiv Gövde"},{"ad":"Otomotiv Gövde Mekaniği","s":[11],"dal":"Otomotiv Gövde"}]},{"ad":"Muhasebe ve Finansman","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Temel Muhasebe","s":[9]},{"ad":"Mesleki Matematik","s":[9]},{"ad":"Ofis Uygulamaları","s":[9]},{"ad":"Genel Muhasebe","s":[10],"dal":"Muhasebe"},{"ad":"Ekonomi","s":[10],"dal":"Muhasebe, Dış Ticaret"},{"ad":"Temel Hukuk","s":[10],"dal":"Muhasebe, Dış Ticaret"},{"ad":"Klavye Teknikleri","s":[10],"dal":"Muhasebe"},{"ad":"Bilgisayarlı Muhasebe","s":[11],"dal":"Muhasebe"},{"ad":"Girişimcilik ve İşletme Yönetimi","s":[11],"dal":"Muhasebe"},{"ad":"Vergi ve Beyannameler","s":[11],"dal":"Muhasebe"},{"ad":"Şirketler Muhasebesi","s":[11],"dal":"Muhasebe"},{"ad":"Maliyet Muhasebesi","s":[11],"dal":"Muhasebe"},{"ad":"İş ve Sosyal Güvenlik Hukuku","s":[11],"dal":"Muhasebe"},{"ad":"Dış Ticarete Giriş","s":[10],"dal":"Dış Ticaret"},{"ad":"Tek Düzen Muhasebe Sistemi","s":[10],"dal":"Dış Ticaret"},{"ad":"E-Ticaret Uygulamaları","s":[10],"dal":"Dış Ticaret"},{"ad":"Dış Ticaret Uygulamaları","s":[11],"dal":"Dış Ticaret"},{"ad":"Dış Ticaret Mevzuatı ve Uygulama","s":[11],"dal":"Dış Ticaret"},{"ad":"Dış Ticarette Paket Programlar","s":[11],"dal":"Dış Ticaret"},{"ad":"Dış Ticaret Muhasebesi","s":[11],"dal":"Dış Ticaret"},{"ad":"Mesleki Yabancı Dil","s":[11],"dal":"Dış Ticaret"}]},{"ad":"Pazarlama ve Perakende","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Pazarlama İlkeleri","s":[9]},{"ad":"Ofis Uygulamaları","s":[9]},{"ad":"Tüketici Davranışları","s":[9]},{"ad":"Satış Teknikleri","s":[10],"dal":"Satış Danışmanlığı"},{"ad":"Temel Hukuk","s":[10],"dal":"Satış Danışmanlığı, Sigortacılık"},{"ad":"Mesleki Matematik","s":[10],"dal":"Satış Danışmanlığı, Sigortacılık"},{"ad":"Perakendecilik","s":[10],"dal":"Satış Danışmanlığı"},{"ad":"Satışta İletişim Teknikleri","s":[10],"dal":"Satış Danışmanlığı, Sigortacılık"},{"ad":"Temel Sigortacılık","s":[10],"dal":"Sigortacılık"},{"ad":"Sigortacılıkta Hizmet Pazarlaması","s":[10],"dal":"Sigortacılık"},{"ad":"Ürün Bilgisi","s":[11],"dal":"Satış Danışmanlığı"},{"ad":"Pazarlama Mevzuatı","s":[11],"dal":"Satış Danışmanlığı"},{"ad":"Mağaza Atmosferi ve Görsellik","s":[11],"dal":"Satış Danışmanlığı"},{"ad":"Perakende Matematiği","s":[11],"dal":"Satış Danışmanlığı"},{"ad":"Perakende Paket Programları","s":[11],"dal":"Satış Danışmanlığı"},{"ad":"Mesleki Yabancı Dil","s":[11],"dal":"Satış Danışmanlığı, Sigortacılık"},{"ad":"Risk ve Hasar Yönetimi","s":[11],"dal":"Sigortacılık"},{"ad":"Sigorta Hukuku","s":[11],"dal":"Sigortacılık"},{"ad":"Can Sigortaları ve Bireysel Emeklilik Sistemi","s":[11],"dal":"Sigortacılık"},{"ad":"Hayat Dışı Sigortalar","s":[11],"dal":"Sigortacılık"},{"ad":"Sigorta Paket Programı","s":[11],"dal":"Sigortacılık"}]},{"ad":"Plastik Sanatlar","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Temel Desen","s":[9]},{"ad":"Temel Tasarım","s":[9]},{"ad":"Bilgisayarda İki Boyutlu Çizim","s":[10]},{"ad":"Resim Uygulamalar Atölyesi","s":[10]},{"ad":"Temel Sanat Tarihi","s":[10]},{"ad":"Teknik Resim","s":[10]},{"ad":"Heykel Sanatı Atölyesi","s":[11]},{"ad":"Sanal Modelleme","s":[11]},{"ad":"Tasarım Uygulama Atölyesi","s":[12]}]},{"ad":"Plastik Teknolojisi","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Temel İşlemler Atölyesi","s":[9]},{"ad":"Teknik Resim","s":[9]},{"ad":"Plastik İşleme Atölyesi","s":[10],"dal":"Plastik Teknolojisi"},{"ad":"Bilgisayar Destekli Çizim","s":[10],"dal":"Plastik Teknolojisi"},{"ad":"Plastik Kalıp Yöntemleri","s":[10],"dal":"Plastik Teknolojisi"},{"ad":"Plastik Kalıp Atölyesi","s":[11],"dal":"Plastik Teknolojisi"},{"ad":"Plastik Teknolojisinde 3 Boyutlu Modelleme","s":[11],"dal":"Plastik Teknolojisi"},{"ad":"CAM (CNC) Programlama","s":[11],"dal":"Plastik Teknolojisi"},{"ad":"Polimer Test Laboratuvarı","s":[11],"dal":"Plastik Teknolojisi"}]},{"ad":"Radyo-Televizyon","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Videonun Temelleri","s":[9]},{"ad":"Fotoğraf ve Video İşleme","s":[9]},{"ad":"Radyo Televizyonun Temel Kavramları","s":[9]},{"ad":"Kamera Ses ve Işık Uygulamaları","s":[10],"dal":"Radyo-Televizyon"},{"ad":"Kurgu Uygulamaları","s":[10],"dal":"Radyo-Televizyon"},{"ad":"Görsel Efekt Teknikleri","s":[10],"dal":"Radyo-Televizyon"},{"ad":"Televizyon Atölyesi","s":[11],"dal":"Radyo-Televizyon"},{"ad":"Senaryo","s":[11],"dal":"Radyo-Televizyon"},{"ad":"Sinema Tarihi","s":[11],"dal":"Radyo-Televizyon"}]},{"ad":"Raylı Sistemler Teknolojisi","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Temel Raylı Sistemler Atölyesi","s":[9]},{"ad":"Raylı Sistemler Araç Mekanik Atölyesi","s":[10],"dal":"Raylı Sistem Araçları"},{"ad":"Raylı Sistem Bilgisi","s":[10],"dal":"Raylı Sistem Araçları, Raylı Sistemler Elektrik-Elektronik, Raylı Sistemler Yol"},{"ad":"Teknik Resim","s":[10],"dal":"Raylı Sistem Araçları, Raylı Sistemler Yol"},{"ad":"Raylı Sistem Araç Elektrik Atölyesi","s":[11],"dal":"Raylı Sistem Araçları"},{"ad":"Araçlarda Hidrolik ve Pnömatik Sistemler","s":[11],"dal":"Raylı Sistem Araçları"},{"ad":"Kent İçi Raylı Sistemler","s":[11],"dal":"Raylı Sistem Araçları, Raylı Sistemler Elektrik-Elektronik"},{"ad":"Trafik Yönetim Sistemleri","s":[11],"dal":"Raylı Sistem Araçları, Raylı Sistemler Yol"},{"ad":"Elektrifikasyon Atölyesi","s":[10],"dal":"Raylı Sistemler Elektrik-Elektronik"},{"ad":"Teknik ve Meslek Resim","s":[10],"dal":"Raylı Sistemler Elektrik-Elektronik"},{"ad":"Sinyalizasyon ve Haberleşme Atölyesi","s":[11],"dal":"Raylı Sistemler Elektrik-Elektronik"},{"ad":"Bilgisayarla Devre Dizaynı","s":[11],"dal":"Raylı Sistemler Elektrik-Elektronik"},{"ad":"Endüstriyel Elektronik","s":[11],"dal":"Raylı Sistemler Elektrik-Elektronik"},{"ad":"Demiryolu Altyapı Atölyesi","s":[10],"dal":"Raylı Sistemler Yol"},{"ad":"Demiryolu Üstyapı Atölyesi","s":[11],"dal":"Raylı Sistemler Yol"},{"ad":"Demiryolu Aplikasyonu ve Hesaplamaları","s":[11],"dal":"Raylı Sistemler Yol"},{"ad":"Ray Kaynağı","s":[11],"dal":"Raylı Sistemler Yol"}]},{"ad":"Sağlık Hizmetleri","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Temel Mesleki Uygulamalar","s":[9]},{"ad":"Anatomi ve Fizyoloji","s":[9]},{"ad":"Aseptik Teknikler ve Kişisel Bakım Uygulamaları","s":[10]},{"ad":"İlk Yardım Uygulamaları","s":[10]},{"ad":"Genel Beslenme","s":[10]},{"ad":"Sağlık Hizmetlerinde İletişim","s":[10]},{"ad":"Enfeksiyon Hastalıkları","s":[10]},{"ad":"Ebe Yardımcılığı Mesleki Uygulamalar","s":[11],"dal":"Ebe Yardımcılığı"},{"ad":"Yenidoğan ve Çocuk Sağlığı","s":[11],"dal":"Ebe Yardımcılığı, Hemşire Yardımcılığı, Sağlık Bakım Teknisyenliği"},{"ad":"Sistem Hastalıkları","s":[11],"dal":"Ebe Yardımcılığı, Hemşire Yardımcılığı, Sağlık Bakım Teknisyenliği"},{"ad":"Sağlık Psikolojisi","s":[11],"dal":"Ebe Yardımcılığı, Hemşire Yardımcılığı, Sağlık Bakım Teknisyenliği"},{"ad":"Temel İlaç Bilgisi","s":[11],"dal":"Ebe Yardımcılığı, Hemşire Yardımcılığı, Sağlık Bakım Teknisyenliği"},{"ad":"Hemşire Yardımcılığı Mesleki Uygulamalar","s":[11],"dal":"Hemşire Yardımcılığı"},{"ad":"Sağlık Bakım Teknisyenliği Mesleki Uygulamalar","s":[11],"dal":"Sağlık Bakım Teknisyenliği"}]},{"ad":"Seramik ve Cam Teknolojisi","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Temel Seramik ve Cam","s":[9]},{"ad":"Temel Teknik Resim","s":[9]},{"ad":"Temel Tasarım","s":[9]},{"ad":"Elde Seramik Şekillendirme","s":[10],"dal":"Seramik"},{"ad":"Temel Desen","s":[10],"dal":"Seramik"},{"ad":"Alçı Şekillendirme","s":[10],"dal":"Seramik"},{"ad":"Türk Motifleri","s":[10],"dal":"Seramik"},{"ad":"Cam Füzyon","s":[10],"dal":"Cam"},{"ad":"Cam İşleme Atölyesi","s":[10],"dal":"Cam"},{"ad":"Bilgisayarda Desen Tasarımı","s":[10,12],"dal":"Cam, Seramik"},{"ad":"Seramik Teknolojisi","s":[11],"dal":"Seramik"},{"ad":"Serbest Seramik Şekillendirme","s":[11],"dal":"Seramik"},{"ad":"Döküm Sır","s":[11],"dal":"Seramik"},{"ad":"Alçı Model Kalıp","s":[11],"dal":"Seramik"},{"ad":"Cam Boyama Teknikleri","s":[11],"dal":"Cam"},{"ad":"Bilgisayar Destekli Çizim","s":[11],"dal":"Cam"},{"ad":"Cam Teknolojisi","s":[11],"dal":"Cam"},{"ad":"Elek Baskı","s":[11],"dal":"Cam"},{"ad":"Kesme Dekor","s":[11],"dal":"Cam"}]},{"ad":"Siber Güvenlik","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Sistem ve Ağ Temelleri","s":[9]},{"ad":"Yazılım Uygulamaları","s":[9]},{"ad":"Siber Güvenlik Atölyesi","s":[10],"dal":"Siber Güvenlik"},{"ad":"Ağ Teknolojileri","s":[10],"dal":"Siber Güvenlik"},{"ad":"Sızma Testi","s":[11],"dal":"Siber Güvenlik"},{"ad":"Siber Olaylara Müdahale","s":[11],"dal":"Siber Güvenlik"},{"ad":"Siber Güvenlikte Açık Kaynak İşletim Sistemi","s":[11],"dal":"Siber Güvenlik"},{"ad":"Adli Bilişim","s":[11],"dal":"Siber Güvenlik"}]},{"ad":"Tarım","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Tarımsal Üretime Hazırlık","s":[9]},{"ad":"Tarım Uygulama","s":[9,10,11]},{"ad":"Üretme ve Çoğaltma Teknikleri","s":[10]},{"ad":"Örtü Altı Yetiştiricilik Sistemleri","s":[10]},{"ad":"Temel Bitki Koruma","s":[10]},{"ad":"Genel Bahçe Bitkileri","s":[11]},{"ad":"Genel Süs Bitkileri","s":[11]},{"ad":"Genel Tarla Bitkileri","s":[11]}]},{"ad":"Tekstil Teknolojisi","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Temel Tekstil Uygulamaları Atölyesi","s":[9]},{"ad":"Tekstil Lifleri Laboratuvarı","s":[10],"dal":"İplik Üretim Teknolojisi, Dokuma Üretim Teknolojisi, Örme Üretim Teknolojisi, Tekstil Baskı ve Desenciliği, Tekstil Boya Apre ve Laborantlığı"},{"ad":"Pamuk İplik Üretim Atölyesi","s":[10],"dal":"İplik Üretim Teknolojisi"},{"ad":"Yün İplik Üretim Atölyesi","s":[10],"dal":"İplik Üretim Teknolojisi"},{"ad":"Temel İplik Meslek Hesapları","s":[10],"dal":"İplik Üretim Teknolojisi"},{"ad":"İplik Bitim İşlemleri Atölyesi","s":[11],"dal":"İplik Üretim Teknolojisi"},{"ad":"İplik Üretimde Kontroller Atölyesi","s":[11],"dal":"İplik Üretim Teknolojisi"},{"ad":"İplik Üretim Hesapları","s":[11],"dal":"İplik Üretim Teknolojisi"},{"ad":"İplikte Farklı Üretim Sistemleri Atölyesi","s":[11],"dal":"İplik Üretim Teknolojisi"},{"ad":"Kumaş Analizi Atölyesi","s":[10],"dal":"Dokuma Üretim Teknolojisi"},{"ad":"Dokuma Örgüleri","s":[10],"dal":"Dokuma Üretim Teknolojisi"},{"ad":"Numune Kumaş Dokuma Atölyesi","s":[11],"dal":"Dokuma Üretim Teknolojisi"},{"ad":"Dokuma Hazırlık","s":[11],"dal":"Dokuma Üretim Teknolojisi"},{"ad":"Kumaş Dokuma Makine Sistemleri Atölyesi","s":[11],"dal":"Dokuma Üretim Teknolojisi"},{"ad":"Örme Makineleri Atölyesi","s":[10],"dal":"Örme Üretim Teknolojisi"},{"ad":"Örmede Meslek Hesapları","s":[10],"dal":"Örme Üretim Teknolojisi"},{"ad":"Örmede Kalite Kontrol","s":[10],"dal":"Örme Üretim Teknolojisi"},{"ad":"Örmede Analiz ve Desen Atölyesi","s":[11],"dal":"Örme Üretim Teknolojisi"},{"ad":"Düz ve Çorap Örme Üretim Atölyesi","s":[11],"dal":"Örme Üretim Teknolojisi"},{"ad":"Yuvarlak Örme Üretim Atölyesi","s":[11],"dal":"Örme Üretim Teknolojisi"},{"ad":"Baskı Deseni Hazırlama Atölyesi","s":[10],"dal":"Tekstil Baskı ve Desenciliği"},{"ad":"Baskı Kimyası","s":[11],"dal":"Tekstil Baskı ve Desenciliği"},{"ad":"Baskı Makineleri","s":[11],"dal":"Tekstil Baskı ve Desenciliği"},{"ad":"Baskı Teknikleri Atölyesi","s":[11],"dal":"Tekstil Baskı ve Desenciliği"},{"ad":"Ön Terbiye İşlemleri Atölyesi","s":[10],"dal":"Tekstil Boya Apre ve Laborantlığı"},{"ad":"Tekstil Kimyası","s":[10],"dal":"Tekstil Boya Apre ve Laborantlığı"},{"ad":"Boyama ve Apre Teknikleri Atölyesi","s":[11],"dal":"Tekstil Boya Apre ve Laborantlığı"},{"ad":"Kimyasal ve Fiziksel Test Laboratuvarı","s":[11],"dal":"Tekstil Boya Apre ve Laborantlığı"},{"ad":"Terbiye Makineleri","s":[11],"dal":"Tekstil Boya Apre ve Laborantlığı"}]},{"ad":"Tesisat Teknolojisi ve İklimlendirme","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Teknik Resim","s":[9]},{"ad":"Temel İşlemler ve Kaynak Atölyesi","s":[9]},{"ad":"Temel Elektrik","s":[10],"dal":"Tesisat ve Enerji Sistemleri, Soğutma ve İklimlendirme Sistemleri"},{"ad":"Bilgisayar Destekli Tesisat Meslek Resmi","s":[10],"dal":"Tesisat ve Enerji Sistemleri"},{"ad":"Isıtma ve Doğal Gaz Tesisatı Atölyesi","s":[10],"dal":"Tesisat ve Enerji Sistemleri"},{"ad":"Tesisat Projeleri","s":[11],"dal":"Tesisat ve Enerji Sistemleri"},{"ad":"Gaz Yakıcı Cihazlar Atölyesi","s":[11],"dal":"Tesisat ve Enerji Sistemleri"},{"ad":"Sıhhi Tesisat ve Güneş Enerjisi Atölyesi","s":[11],"dal":"Tesisat ve Enerji Sistemleri"},{"ad":"Bilgisayar Destekli Soğutma Meslek Resmi","s":[10],"dal":"Soğutma ve İklimlendirme Sistemleri"},{"ad":"Temel Soğutma ve İklimlendirme Atölyesi","s":[10],"dal":"Soğutma ve İklimlendirme Sistemleri"},{"ad":"İklimlendirme ve Soğutma Projeleri","s":[11],"dal":"Soğutma ve İklimlendirme Sistemleri"},{"ad":"Endüstriyel Soğutma Atölyesi","s":[11],"dal":"Soğutma ve İklimlendirme Sistemleri"},{"ad":"Merkezi İklimlendirme ve Havalandırma Atölyesi","s":[11],"dal":"Soğutma ve İklimlendirme Sistemleri"}]},{"ad":"Ulaştırma Hizmetleri","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Temel Ulaştırma","s":[9]},{"ad":"Ofis Uygulamaları","s":[9]},{"ad":"Taşıma Modelleri","s":[10],"dal":"Lojistik"},{"ad":"Depo Yönetimi","s":[10],"dal":"Lojistik"},{"ad":"Depo Programı","s":[10],"dal":"Lojistik"},{"ad":"Teslim ve Ödeme Şekilleri","s":[10],"dal":"Lojistik"},{"ad":"Havacılık Yönetimi","s":[10],"dal":"Sivil Havacılık"},{"ad":"Hava Yolu Taşımacılığı","s":[10],"dal":"Sivil Havacılık"},{"ad":"Havacılık Emniyeti ve Güvenliği","s":[10],"dal":"Sivil Havacılık"},{"ad":"Ramp Hizmetleri","s":[10],"dal":"Sivil Havacılık"},{"ad":"Gümrük","s":[11],"dal":"Lojistik"},{"ad":"Gümrük Programı","s":[11],"dal":"Lojistik"},{"ad":"Lojistik Belgeler","s":[11],"dal":"Lojistik"},{"ad":"Lojistik Hesaplamalar","s":[11],"dal":"Lojistik"},{"ad":"Lojistik Satın Alma","s":[11],"dal":"Lojistik"},{"ad":"Mesleki Yabancı Dil","s":[11],"dal":"Lojistik, Sivil Havacılık"},{"ad":"Harekât Hizmetleri","s":[11],"dal":"Sivil Havacılık"},{"ad":"Yolcu Hizmetleri","s":[11],"dal":"Sivil Havacılık"},{"ad":"Kargo Hizmetleri","s":[11],"dal":"Sivil Havacılık"}]},{"ad":"Uçak Bakım","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Uçak Malzeme ve Donanım Atölyesi","s":[9]},{"ad":"Elektrik Devre Analizi Atölyesi","s":[9]},{"ad":"Uçak Gövde ve Yapıları Atölyesi","s":[10],"dal":"Uçak Gövde-Motor"},{"ad":"Uçak Elektrik Sistemleri Atölyesi","s":[10],"dal":"Uçak Gövde-Motor, Uçak Elektroniği"},{"ad":"Gaz Türbinli Motorlar Atölyesi","s":[10],"dal":"Uçak Gövde-Motor"},{"ad":"Uçak Teknik Resmi","s":[10],"dal":"Uçak Gövde-Motor, Uçak Elektroniği"},{"ad":"Gaz Türbinli Motor Sistemleri Atölyesi","s":[11],"dal":"Uçak Gövde-Motor"},{"ad":"Dijital Uygulamalar Atölyesi","s":[10,11],"dal":"Uçak Gövde-Motor, Uçak Elektroniği"},{"ad":"Pervane Atölyesi","s":[11],"dal":"Uçak Gövde-Motor"},{"ad":"Uçak Sistemleri Atölyesi","s":[11],"dal":"Uçak Gövde-Motor"},{"ad":"Hava Aracı Aerodinamiği","s":[11],"dal":"Uçak Gövde-Motor, Uçak Elektroniği"},{"ad":"Mesleki Yabancı Dil (Uçak Bakım)","s":[11],"dal":"Uçak Gövde-Motor, Uçak Elektroniği"},{"ad":"Havacılık Mevzuatı ve İnsan Faktörleri","s":[11],"dal":"Uçak Gövde-Motor, Uçak Elektroniği"},{"ad":"Uçak Bakım Uygulamaları Atölyesi","s":[11],"dal":"Uçak Gövde-Motor, Uçak Elektroniği"},{"ad":"Analog Elektronik Atölyesi","s":[10],"dal":"Uçak Elektroniği"},{"ad":"Aviyonik Sistemler Atölyesi","s":[11],"dal":"Uçak Elektroniği"},{"ad":"İleri Dijital Teknikleri Atölyesi","s":[11],"dal":"Uçak Elektroniği"},{"ad":"İtme Kuvveti Atölyesi","s":[11],"dal":"Uçak Elektroniği"},{"ad":"Mekanik Sistem Uygulamaları Atölyesi","s":[11],"dal":"Uçak Elektroniği"}]},{"ad":"Yapay Zekâ","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Algoritma ve Programlama","s":[9]},{"ad":"Yapay Zekâ Temelleri","s":[9]},{"ad":"Yapay Zekâ İçin Matematik","s":[9,10]},{"ad":"Makine Öğrenmesi ve Derin Öğrenme","s":[10]},{"ad":"Veri Bilimi","s":[10]},{"ad":"Bilgisayarlı Görü","s":[11]},{"ad":"Doğal Dil İşleme","s":[11]},{"ad":"Yapay Zekâ Proje Uygulamaları","s":[12]}]},{"ad":"Yenilenebilir Enerji Teknolojileri","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Yenilenebilir Enerji Teknolojileri Atölye","s":[9,10,11]},{"ad":"Meslek Elektrik-Elektroniği","s":[10]},{"ad":"Teknik ve Meslek Resim","s":[10]},{"ad":"Modelleme ve Montaj","s":[11]},{"ad":"Santral İşletilmesi","s":[11]}]},{"ad":"Yiyecek İçecek Hizmetleri","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Beslenme İlkeleri ve Hijyen","s":[9]},{"ad":"Temel Yiyecek Üretimi ve Servis Atölyesi","s":[9]},{"ad":"Mesleki Yabancı Dil","s":[10]},{"ad":"Aşçılık Atölyesi","s":[10,11]},{"ad":"Servis Atölyesi","s":[10,11]},{"ad":"Mutfak Hizmetleri Yönetimi","s":[11]}]},{"ad":"Çocuk Gelişimi ve Eğitimi","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Anne-Çocuk Sağlığı","s":[9]},{"ad":"Çocuk Ruh Sağlığı","s":[9]},{"ad":"Müzik ve Dramatik Etkinlikler Atölyesi","s":[9]},{"ad":"Çocuk Gelişimi","s":[10]},{"ad":"Erken Çocukluk ve Özel Eğitimde Öz Bakım","s":[10]},{"ad":"Oyun ve Oyuncak Atölyesi","s":[10]},{"ad":"Yetersizlik Türleri ve Kaynaştırma Eğitimi","s":[11],"dal":"Erken Çocukluk ve Özel Eğitim"},{"ad":"Erken Çocuklukta Program Atölyesi","s":[11],"dal":"Erken Çocukluk ve Özel Eğitim"},{"ad":"Özel Eğitimde Program Atölyesi","s":[11],"dal":"Erken Çocukluk ve Özel Eğitim"}]},{"ad":"İnşaat Teknolojisi","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Yapı Teknik ve Temel Meslek Resmi","s":[9]},{"ad":"Ahşap Kâgir Atölyesi","s":[9]},{"ad":"Karayolları ve Trafik","s":[10],"dal":"İnşaat Altyapı"},{"ad":"Meslek Hesapları","s":[10],"dal":"İnşaat Altyapı, İnşaat Üstyapı, Mimari Restorasyon, Mimari Yapı Teknik Ressamlığı, Statik Yapı Teknik Ressamlığı, Yapı Yüzey Kaplama ve Yalıtım"},{"ad":"İnşaat Altyapı Atölyesi","s":[10,11],"dal":"İnşaat Altyapı"},{"ad":"Bilgisayarla Altyapı Maliyet Hesapları","s":[11],"dal":"İnşaat Altyapı"},{"ad":"Bilgisayarla Altyapı Meslek Resmi","s":[11],"dal":"İnşaat Altyapı"},{"ad":"Temel Yapı Teknolojisi","s":[10],"dal":"İnşaat Üstyapı, Mimari Restorasyon, Mimari Yapı Teknik Ressamlığı, Statik Yapı Teknik Ressamlığı, Yapı Yüzey Kaplama ve Yalıtım"},{"ad":"İnşaat Üstyapı Atölyesi","s":[10,11],"dal":"İnşaat Üstyapı"},{"ad":"Bilgisayarla Yapı Maliyet Hesapları","s":[11],"dal":"İnşaat Üstyapı, Mimari Restorasyon, Mimari Yapı Teknik Ressamlığı, Statik Yapı Teknik Ressamlığı, Yapı Yüzey Kaplama ve Yalıtım"},{"ad":"Bilgisayarla Üstyapı Meslek Resmi","s":[11],"dal":"İnşaat Üstyapı"},{"ad":"Mimari Restorasyon Atölyesi","s":[10,11],"dal":"Mimari Restorasyon"},{"ad":"Bilgisayarla Restorasyon Meslek Resmi","s":[11],"dal":"Mimari Restorasyon"},{"ad":"Temel Bina Tasarımı","s":[10],"dal":"Mimari Yapı Teknik Ressamlığı, Statik Yapı Teknik Ressamlığı"},{"ad":"Bilgisayarla Mimari Proje Çizim Atölyesi","s":[10,11],"dal":"Mimari Yapı Teknik Ressamlığı"},{"ad":"Bilgisayarla Mimari Proje Modelleme","s":[11],"dal":"Mimari Yapı Teknik Ressamlığı"},{"ad":"Bilgisayarla Statik Proje Çizim Atölyesi","s":[10,11],"dal":"Statik Yapı Teknik Ressamlığı"},{"ad":"Bilgisayarla Statik Proje Modelleme","s":[11],"dal":"Statik Yapı Teknik Ressamlığı"},{"ad":"Yapı Yüzey Kaplama ve Yalıtım Atölyesi","s":[10,11],"dal":"Yapı Yüzey Kaplama ve Yalıtım"},{"ad":"Bilgisayarla Yüzey Kaplama Meslek Resmi","s":[11],"dal":"Yapı Yüzey Kaplama ve Yalıtım"}]},{"ad":"İtfaiyecilik ve Yangın Güvenliği","dersler":[{"ad":"Mesleki Gelişim Atölyesi","s":[9]},{"ad":"Temel İtfaiyecilik","s":[9]},{"ad":"İtfaiyecilik ve Yangın Güvenliği Atölye","s":[9,10,11]},{"ad":"İtfaiyecilikte Teknik Resim","s":[10],"dal":"İtfaiyecilik ve Yangın Güvenliği"},{"ad":"Yangın Önleme Tedbirleri","s":[11],"dal":"İtfaiyecilik ve Yangın Güvenliği"},{"ad":"Uçak Kaza Kırım ve Yangınlar","s":[11],"dal":"İtfaiyecilik ve Yangın Güvenliği"}]}]},{"id":"gsl_muzik","ad":"Güzel sanatlar lisesi – müzik","ik":"🎼","dersler":[{"ad":"Batı Müziği Teori ve Uygulaması","s":[9,10,11,12]},{"ad":"Bireysel Ses Eğitimi","s":[9,10,11,12]},{"ad":"Çalgı Eğitimi","s":[9,10,11,12]},{"ad":"Çalgı Toplulukları","s":[9,10,11,12]},{"ad":"Bilişim Destekli Müzik","s":[9,10,11,12]},{"ad":"Halk Dansları ve Müzikleri","s":[9,10,11,12]},{"ad":"Müzik Kültürü","s":[9,10,11,12]},{"ad":"Temel Piyano","s":[9,10,11,12]},{"ad":"Toplu Ses Eğitimi","s":[9,10,11,12]},{"ad":"Türk Dünyası Müzikleri","s":[9,10,11,12]},{"ad":"Türk Halk Müziği Koro","s":[9,10,11,12]},{"ad":"Türk Sanat Müziği Koro","s":[9,10,11,12]},{"ad":"Türk Halk Müziği Teori ve Uygulaması","s":[9,10,11,12]},{"ad":"Türk Müziği Tarihi","s":[9,10,11,12]},{"ad":"Türk Sanat Müziği Teori ve Uygulaması","s":[9,10,11,12]}]},{"id":"gsl_gorsel","ad":"Güzel sanatlar lisesi – görsel sanatlar","ik":"🎨","dersler":[{"ad":"Desen","s":[9,10,11,12]},{"ad":"Dijital Grafik","s":[9,10,11,12]},{"ad":"Estetik","s":[9,10,11,12]},{"ad":"Fotoğraf","s":[9,10,11,12]},{"ad":"Genel Sanat Tarihi","s":[9,10,11,12]},{"ad":"Grafik Tasarım","s":[9,10,11,12]},{"ad":"Hüsnühat Yazı Çeşitleri","s":[9,10,11,12]},{"ad":"İki Boyutlu Sanat Atölye","s":[9,10,11,12]},{"ad":"İmgesel Resim","s":[9,10,11,12]},{"ad":"Sanat Eserlerini İnceleme","s":[9,10,11,12]},{"ad":"Temel Sanat Eğitimi","s":[9,10,11,12]},{"ad":"Türk İslam Sanatı Tarihi","s":[9,10,11,12]},{"ad":"Üç Boyutlu Sanat Atölye","s":[9,10,11,12]},{"ad":"Geleneksel Türk Sanatları","s":[9,10,11,12]}]},{"id":"spor","ad":"Spor lisesi alan dersleri","ik":"🏅","dersler":[{"ad":"Spor Anatomisi ve Fizyolojisi","s":[9,10,11,12]},{"ad":"Spor Psikolojisi ve Sosyolojisi","s":[9,10,11,12]},{"ad":"Spor Uygulamaları","s":[9,10,11,12]},{"ad":"Spor ve Beslenme","s":[9,10,11,12]},{"ad":"Sporcu Sağlığı","s":[9,10,11,12]},{"ad":"Spor Yönetimi ve Organizasyonu","s":[9,10,11,12]},{"ad":"Takım Sporları","s":[9,10,11,12]},{"ad":"Temel Spor Eğitimi","s":[9,10,11,12]},{"ad":"Atletizm","s":[9,10,11,12]},{"ad":"Antrenman Bilgisi","s":[9,10,11,12]},{"ad":"Antrenörlük Eğitimi","s":[9,10,11,12]},{"ad":"Artistik Jimnastik","s":[9,10,11,12]},{"ad":"Beden Eğitimi ve Spor Tarihi","s":[9,10,11,12]},{"ad":"Bireysel Sporlar","s":[9,10,11,12]},{"ad":"Eğitsel Oyunlar","s":[9,10,11,12]},{"ad":"Geleneksel Sporlar (Ata Sporları)","s":[9,10,11,12]},{"ad":"Genel Jimnastik","s":[9,10,11,12]}]},{"id":"fen_teknik","ad":"Fen ve teknoloji odaklı seçmeli dersler","ik":"🔬","dersler":[{"ad":"Bilgisayarlı Tasarım Uygulamaları","s":[9,10,11,12]},{"ad":"Robotik ve Kodlama","s":[9,10,11,12]},{"ad":"Nesnelerin İnterneti","s":[9,10,11,12]},{"ad":"Mobil Uygulamalar","s":[9,10,11,12]},{"ad":"Web Tabanlı Uygulama Geliştirme","s":[9,10,11,12]},{"ad":"Siber Güvenlik Temelleri","s":[9,10,11,12]},{"ad":"Nano Teknoloji ve Malzeme Bilimi","s":[9,10,11,12]},{"ad":"Açık Kaynak İşletim Sistemi","s":[9,10,11,12]},{"ad":"Ağ Projesi","s":[9,10,11,12]},{"ad":"Blok Zincir","s":[9,10,11,12]},{"ad":"Oyun Programlama","s":[9,10,11,12]},{"ad":"Web Tabanlı İçerik Yönetimi","s":[9,10,11,12]},{"ad":"Dijital Tasarım","s":[9,10,11,12]},{"ad":"Yapay Zekâ ve Makine Öğrenmesi","s":[9,10,11,12]},{"ad":"Havacılık ve Uzay Teknolojileri","s":[9,10,11,12]},{"ad":"Sosyal Medya","s":[9,10,11,12]},{"ad":"Enerji Teknolojileri","s":[9,10,11,12]}]}]}'; }
    public static function css_cikti() { ?>.osc-yz{--i:#5b5bf6;--i2:#7c3aed;--id:#4338ca;--ia:#eef0ff;--k:#0b1220;--k2:#334155;--s:#64748b;--c:#e6e9f2;--z:#f6f7fb;--y:#059669;--t:#d97706;--h:#e11d48;--r:18px;--g:linear-gradient(135deg,#5b5bf6 0%,#7c3aed 55%,#c026d3 100%);
  font-family:"Plus Jakarta Sans",Inter,"Segoe UI",system-ui,-apple-system,Roboto,Arial,sans-serif;color:var(--k);font-size:15px;line-height:1.5;box-sizing:border-box;
  max-width:none!important;width:min(1180px,calc(100vw - 32px));margin:0 0 70px calc(50% - min(590px,50vw - 16px))!important;-webkit-font-smoothing:antialiased}
.osc-yz *,.osc-yz *::before,.osc-yz *::after{box-sizing:border-box}
.osc-yz button,.osc-yz input,.osc-yz select{font-family:inherit!important;font-size:inherit;letter-spacing:normal!important;text-transform:none!important;margin:0;line-height:1.35;box-shadow:none}
.osc-yz button{cursor:pointer;-webkit-appearance:none;appearance:none;text-shadow:none!important;min-height:0!important}
.osc-yz button:disabled{cursor:not-allowed;opacity:.45}
.osc-yz a{text-decoration:none!important}
.osc-yz h1,.osc-yz h2,.osc-yz h3{font-family:inherit!important;color:inherit;letter-spacing:-.02em;margin:0}
.osc-yz h2{font-size:18px;font-weight:800;line-height:1.25}
.osc-yz p{margin:0}
.yz-yuk{padding:40px;text-align:center;color:var(--s)}

/* ---------- hero ---------- */
.yz-hero{position:relative;overflow:hidden;border-radius:26px;padding:16px 18px;margin-bottom:14px;color:#fff;background:radial-gradient(900px 300px at 85% -40%,rgba(192,38,211,.55),transparent 60%),radial-gradient(700px 300px at 0% 120%,rgba(14,165,233,.35),transparent 60%),linear-gradient(135deg,#0b1024 0%,#1e1b4b 55%,#312e81 100%);box-shadow:0 24px 50px -30px rgba(30,27,75,.8)}
.yz-hero::before{content:"";position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.06) 1px,transparent 1px);background-size:34px 34px;-webkit-mask-image:radial-gradient(ellipse at 70% 0%,#000 10%,transparent 70%);mask-image:radial-gradient(ellipse at 70% 0%,#000 10%,transparent 70%);pointer-events:none}
.yz-hero.buyuk{padding:18px 22px 34px}
.yz-hero-ic{position:relative;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.yz-marka{display:flex;align-items:center;gap:12px;color:#fff!important}
.yz-marka img{height:38px;width:auto;background:#fff;border-radius:12px;padding:5px 10px;box-shadow:0 6px 18px -6px rgba(0,0,0,.4)}
.yz-marka span{font-weight:800;font-size:17px;letter-spacing:-.01em}
.yz-sek{display:flex;gap:4px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);border-radius:14px;padding:4px;margin-left:auto;backdrop-filter:blur(6px)}
.osc-yz .yz-sek button,.osc-yz .yz-sek a{background:transparent!important;border:0!important;color:#c7d2fe!important;padding:8px 14px!important;border-radius:10px!important;font-weight:700!important;font-size:14px!important;transition:.2s}
.osc-yz .yz-sek button:hover,.osc-yz .yz-sek a:hover{color:#fff!important}
.osc-yz .yz-sek button.on{background:#fff!important;color:var(--k)!important;box-shadow:0 6px 16px -8px rgba(0,0,0,.5)}
.yz-hero-yazi{position:relative;max-width:760px;margin-top:28px}
.yz-etiket{display:inline-flex;align-items:center;gap:8px;font-size:12.5px;font-weight:700;color:#e0e7ff;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);padding:6px 12px;border-radius:99px}
.yz-etiket::before{content:"";width:7px;height:7px;border-radius:50%;background:#34d399;box-shadow:0 0 0 4px rgba(52,211,153,.25)}
.yz-hero-yazi h1{font-size:clamp(28px,4.2vw,46px)!important;line-height:1.08!important;font-weight:800!important;margin:16px 0 12px!important;color:#fff!important}
.yz-hero-yazi h1 em{font-style:normal;background:linear-gradient(90deg,#a5b4fc,#f0abfc,#fda4af);-webkit-background-clip:text;background-clip:text;color:transparent}
.yz-hero-yazi p{color:#cbd5e1;font-size:16px;max-width:640px}
.yz-hero-ozellik{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}
.yz-hero-ozellik span{font-size:13px;font-weight:700;color:#e0e7ff;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);border-radius:12px;padding:8px 12px}

/* ---------- adımlar ---------- */
.yz-adimlar{position:relative;list-style:none!important;margin:0 0 16px!important;padding:8px!important;display:grid;grid-template-columns:repeat(4,1fr);gap:6px;background:rgba(255,255,255,.85);border:1px solid var(--c);border-radius:20px;box-shadow:0 10px 30px -24px rgba(15,23,42,.4);backdrop-filter:blur(8px)}
.yz-adimlar::after{content:"";position:absolute;left:8px;bottom:-1px;height:3px;width:calc((100% - 16px) * var(--ilerleme) / 100 + 0px);background:var(--g);border-radius:3px;transition:width .4s}
.yz-adimlar li{margin:0!important;padding:0!important;list-style:none!important}
.yz-adimlar li::before{display:none!important}
.osc-yz .yz-adimlar button{width:100%;display:flex;align-items:center;gap:11px;background:transparent!important;border:0!important;border-radius:14px!important;padding:9px 12px!important;color:var(--s)!important;text-align:left;transition:.2s}
.osc-yz .yz-adimlar button:hover:not(:disabled){background:var(--z)!important}
.yz-adimlar i{font-style:normal;flex:none;width:34px;height:34px;border-radius:11px;display:flex;align-items:center;justify-content:center;background:var(--z);border:1.5px solid var(--c);font-weight:800;font-size:14px;color:var(--s)}
.yz-adimlar span{display:flex;flex-direction:column;min-width:0}
.yz-adimlar b{font-size:14.5px;color:inherit}
.yz-adimlar small{font-size:11.5px;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.osc-yz .yz-adimlar li.on button{background:var(--ia)!important;color:var(--id)!important}
.yz-adimlar li.on i{background:var(--g);border-color:transparent;color:#fff;box-shadow:0 8px 18px -8px rgba(91,91,246,.8)}
.yz-adimlar li.ok i{background:#d1fae5;border-color:#a7f3d0;color:var(--y)}
.osc-yz .yz-adimlar li.ok button{color:var(--k2)!important}

/* ---------- kartlar ---------- */
.yz-kart{background:#fff;border:1px solid var(--c);border-radius:22px;padding:22px 24px;margin-bottom:14px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 18px 40px -34px rgba(15,23,42,.35)}
.yz-kart-bas{display:flex;align-items:center;gap:14px;margin-bottom:18px;flex-wrap:wrap}
.yz-kart-bas>div{flex:1;min-width:180px}
.yz-kart-bas>.yz-seg{flex:none;min-width:0}
.yz-kart-bas p{color:var(--s);font-size:13.5px;margin-top:2px}
.yz-kart-bas .sag{margin-left:auto}
.yz-num{flex:none;width:42px;height:42px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:var(--ia);color:var(--id);font-weight:800;font-size:18px}
.yz-not{color:var(--s);font-size:13.5px}
.yz-iki{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.yz-iki .yz-kart{margin-bottom:0}
.yz-iki+.yz-kart{margin-top:14px}

/* ---------- seçim öğeleri ---------- */
.yz-seg{display:inline-flex;gap:4px;background:var(--z);border:1px solid var(--c);border-radius:14px;padding:4px;flex-wrap:wrap}
.osc-yz .yz-seg button{background:transparent!important;border:0!important;color:var(--k2)!important;border-radius:10px!important;padding:8px 14px!important;font-weight:700!important;font-size:14px!important;transition:.15s}
.osc-yz .yz-seg button:hover:not(:disabled){background:#fff!important}
.osc-yz .yz-seg button.on{background:#fff!important;color:var(--id)!important;box-shadow:0 4px 12px -6px rgba(15,23,42,.35)}
.osc-yz .yz-seg.kucuk button{padding:5px 10px!important;font-size:12.5px!important}
.yz-siniflar{display:grid;grid-template-columns:repeat(8,1fr);gap:10px}
.osc-yz .yz-siniflar button{display:flex;flex-direction:column;align-items:center;gap:0;background:#fff!important;border:1.5px solid var(--c)!important;border-radius:16px!important;padding:12px 6px!important;color:var(--k)!important;transition:.18s}
.yz-siniflar b{font-size:24px;font-weight:800;line-height:1.1}
.yz-siniflar small{font-size:11px;color:var(--s);font-weight:600}
.osc-yz .yz-siniflar button:hover{border-color:#a5b4fc!important;transform:translateY(-2px);box-shadow:0 10px 20px -14px rgba(91,91,246,.6)}
.osc-yz .yz-siniflar button.on{background:var(--g)!important;border-color:transparent!important;color:#fff!important;box-shadow:0 14px 26px -14px rgba(124,58,237,.8)}
.osc-yz .yz-siniflar button.on small{color:#e0e7ff}
.yz-dersler{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px;margin-top:14px}
.osc-yz .yz-dersler button{display:flex;align-items:center;gap:10px;background:#fff!important;border:1.5px solid var(--c)!important;border-radius:14px!important;padding:11px 13px!important;color:var(--k)!important;text-align:left;transition:.18s}
.yz-dersler span{flex:none;width:34px;height:34px;border-radius:10px;background:var(--z);display:flex;align-items:center;justify-content:center;font-size:18px}
.yz-dersler b{font-size:13.5px;font-weight:700;line-height:1.25}
.osc-yz .yz-dersler button:hover{border-color:#a5b4fc!important}
.osc-yz .yz-dersler button.on{border-color:var(--i)!important;background:var(--ia)!important;box-shadow:0 0 0 3px rgba(91,91,246,.15)}
.osc-yz .yz-dersler button.on span{background:#fff}
.yz-ipucu{margin-top:14px;padding:14px;border:1.5px dashed var(--c);border-radius:14px;color:var(--s);text-align:center;font-size:14px}
.yz-satir{display:flex;align-items:center;gap:12px;margin-top:18px;flex-wrap:wrap}
.yz-et{font-size:12px;font-weight:800;color:var(--s);text-transform:uppercase;letter-spacing:.06em}
.yz-bilgi{display:flex;gap:12px;align-items:flex-start;background:#fffbeb;border:1px solid #fde68a;border-radius:16px;padding:14px 16px;margin-bottom:14px;color:#78350f;font-size:14px}
.yz-bilgi>span{font-size:22px}

/* senaryo kartları */
.yz-senaryolar{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px}
.osc-yz .yz-snr{display:flex;flex-direction:column;gap:10px;background:#fff!important;border:1.5px solid var(--c)!important;border-radius:18px!important;padding:14px 15px!important;color:var(--k)!important;text-align:left;transition:.2s;position:relative}
.osc-yz .yz-snr:hover{border-color:#a5b4fc!important;transform:translateY(-2px);box-shadow:0 16px 30px -22px rgba(91,91,246,.7)}
.osc-yz .yz-snr.on{border-color:var(--i)!important;background:linear-gradient(180deg,#f5f3ff,#fff)!important;box-shadow:0 0 0 4px rgba(91,91,246,.14),0 16px 30px -22px rgba(91,91,246,.7)}
.osc-yz .yz-snr.on::after{content:"✓";position:absolute;top:-9px;right:-9px;width:24px;height:24px;border-radius:50%;background:var(--g);color:#fff;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 14px -6px rgba(124,58,237,.9)}
.yz-snr .ust{display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%}
.yz-snr .ust b{font-size:15.5px;font-weight:800}
.yz-snr .ust em{font-style:normal;font-size:12px;font-weight:800;background:var(--k);color:#fff;border-radius:99px;padding:3px 9px}
.yz-snr.on .ust em{background:var(--g)}
.yz-snr .bar{display:flex;gap:3px;height:8px;width:100%;border-radius:9px;overflow:hidden}
.yz-snr .bar i{display:block;border-radius:3px}
.yz-snr ul{list-style:none!important;margin:0!important;padding:0!important;width:100%;display:flex;flex-direction:column;gap:4px}
.yz-snr li{display:flex;align-items:center;gap:7px;font-size:12.5px;color:var(--k2);margin:0!important}
.yz-snr li s,.yz-oz>s,.yz-dg>s{flex:none;width:8px;height:8px;border-radius:3px;text-decoration:none}
.yz-snr li span{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.yz-snr li i{font-style:normal;font-weight:800;color:var(--id)}
.osc-yz .yz-snr.ozel{border-style:dashed!important}
.yz-snr p{font-size:13px;color:var(--s)}
.yz-dagilim{margin-top:18px;background:var(--z);border-radius:18px;padding:14px}
.yz-dagilim h3{font-size:13px!important;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--s);margin:0 0 10px 4px!important}
.yz-dg,.yz-oz{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid var(--c);border-radius:14px;padding:10px 14px;margin-bottom:6px}
.yz-dg>div,.yz-oz>div:not(.yz-sayac){flex:1;min-width:0}
.yz-dg small,.yz-oz small,.yz-slot small,.yz-soru-ust small{display:block;font-size:11.5px;font-weight:800;color:var(--id);letter-spacing:.01em}
.yz-dg p,.yz-oz p{font-size:13.5px;color:var(--k2);line-height:1.4}
.yz-dg>b{flex:none;min-width:34px;height:34px;border-radius:11px;background:var(--ia);color:var(--id);display:flex;align-items:center;justify-content:center;font-weight:800}
.yz-ozel{margin-top:14px}
.yz-oz.var{border-color:#c7d2fe;background:#fafaff}
.yz-sayac{display:flex;align-items:center;gap:6px;background:#fff;border:1.5px solid var(--c);border-radius:12px;padding:3px}
.osc-yz .yz-sayac button{width:30px;height:30px;border-radius:9px!important;border:0!important;background:var(--z)!important;color:var(--k)!important;font-weight:800!important;padding:0!important;font-size:17px!important;display:flex;align-items:center;justify-content:center}
.osc-yz .yz-sayac button:hover{background:var(--ia)!important;color:var(--id)!important}
.yz-sayac b{min-width:22px;text-align:center;font-weight:800}
.yz-sayac.buyuk{padding:4px}
.osc-yz .yz-sayac.buyuk button{width:38px;height:38px;font-size:20px!important}
.yz-sayac.buyuk b{font-size:20px;min-width:30px}

/* form */
.yz-alan{display:flex;flex-direction:column;gap:7px;margin:0 0 14px}
.yz-alan>span{font-size:12px;font-weight:800;color:var(--s);text-transform:uppercase;letter-spacing:.06em}
.yz-alan>span small{text-transform:none;font-weight:600;letter-spacing:0}
.osc-yz .yz-alan input{width:100%;border:1.5px solid var(--c)!important;border-radius:13px!important;padding:12px 14px!important;background:var(--z)!important;color:var(--k)!important;outline:0;font-size:15px!important;height:auto!important;transition:.15s}
.osc-yz .yz-alan input:focus,.yz-ce:focus{border-color:var(--i)!important;background:#fff!important;box-shadow:0 0 0 4px rgba(91,91,246,.13)!important}
.yz-izgara2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.yz-izgara2 .yz-alan{margin:0}
.yz-secim{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:8px}
.osc-yz .yz-secim button{display:flex;flex-direction:column;align-items:flex-start;gap:1px;background:#fff!important;border:1.5px solid var(--c)!important;border-radius:14px!important;padding:10px 12px!important;color:var(--k)!important;text-align:left;transition:.15s}
.yz-secim span{font-size:18px;margin-bottom:2px}
.yz-secim b{font-size:14px;font-weight:800}
.yz-secim small{font-size:11.5px;color:var(--s)}
.osc-yz .yz-secim button:hover{border-color:#a5b4fc!important}
.osc-yz .yz-secim button.on{border-color:var(--i)!important;background:var(--ia)!important;box-shadow:0 0 0 3px rgba(91,91,246,.13)}
.osc-yz .yz-secim button.on b{color:var(--id)}

/* kim yazsın */
.yz-rozet{font-size:12.5px;font-weight:800;color:var(--s);background:var(--z);border:1px solid var(--c);border-radius:99px;padding:6px 12px;display:inline-flex;align-items:center;gap:6px}
.yz-rozet.on{color:var(--id);background:var(--ia);border-color:#c7d2fe}
.yz-hizli{display:flex;align-items:center;gap:16px;justify-content:space-between;flex-wrap:wrap;background:linear-gradient(135deg,#eef0ff,#faf5ff);border:1px solid #ddd6fe;border-radius:18px;padding:14px 16px;margin-bottom:14px}
.yz-hizli b{display:block;font-size:15px;color:var(--id)}
.yz-hizli small{color:var(--s);font-size:12.5px}
.yz-slotlar{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.yz-slot{display:flex;align-items:center;gap:12px;border:1.5px solid var(--c);border-radius:16px;padding:10px 12px;cursor:pointer;margin:0!important;transition:.15s;background:#fff}
.yz-slot:hover{border-color:#c7d2fe}
.yz-slot>i{font-style:normal;flex:none;width:32px;height:32px;border-radius:10px;background:var(--z);color:var(--k);border-left:4px solid var(--r);font-weight:800;display:flex;align-items:center;justify-content:center;font-size:13.5px}
.yz-slot>div{flex:1;min-width:0}
.yz-slot p{font-size:13px;color:var(--k2);line-height:1.38;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.yz-slot.ben{border-color:#f59e0b;background:#fffbeb}
.yz-anahtar{display:inline-flex;align-items:center;gap:8px;font-size:12.5px;font-weight:700;color:var(--s);cursor:pointer;white-space:nowrap;user-select:none}
.yz-anahtar input{position:absolute;opacity:0;pointer-events:none}
.yz-anahtar em{flex:none;width:38px;height:22px;border-radius:99px;background:#cbd5e1;position:relative;transition:.2s}
.yz-anahtar em::after{content:"";position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:.2s}
.yz-anahtar input:checked+em{background:#f59e0b}
.yz-anahtar input:checked+em::after{left:19px}
.yz-anahtar input:checked~span{color:#b45309}
.yz-onizle-bas .yz-anahtar input:checked+em{background:var(--i)}
.yz-onizle-bas .yz-anahtar input:checked~span{color:var(--id)}

/* alt çubuk */
.yz-cubuk{position:sticky;bottom:12px;z-index:30;display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:rgba(255,255,255,.88);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid var(--c);border-radius:20px;padding:10px 10px 10px 16px;box-shadow:0 20px 40px -24px rgba(15,23,42,.45);margin-top:16px}
.yz-cubuk .bosluk,.yz-soru-alt .bosluk{flex:1}
.yz-ozet{flex:1;min-width:180px;display:flex;flex-direction:column}
.yz-ozet b{font-size:15px}
.yz-ozet span{font-size:12.5px;color:var(--s)}
.osc-yz .yz-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;background:#fff!important;border:1.5px solid var(--c)!important;color:var(--k)!important;border-radius:13px!important;padding:11px 16px!important;font-weight:700!important;font-size:14.5px!important;transition:.18s;white-space:nowrap;line-height:1.2!important}
.osc-yz .yz-btn:hover:not(:disabled){border-color:#a5b4fc!important;color:var(--id)!important}
.osc-yz .yz-btn.ana{background:var(--g)!important;background-size:150% 150%!important;border-color:transparent!important;color:#fff!important;box-shadow:0 12px 24px -12px rgba(124,58,237,.75)}
.osc-yz .yz-btn.ana:hover:not(:disabled){color:#fff!important;transform:translateY(-1px);box-shadow:0 16px 28px -12px rgba(124,58,237,.85);background-position:100% 0!important}
.osc-yz .yz-btn i{font-style:normal;transition:transform .2s}
.osc-yz .yz-btn.ana:hover i{transform:translateX(3px)}
.osc-yz .yz-btn.kucuk{padding:7px 12px!important;font-size:13px!important;border-radius:11px!important}
.osc-yz .yz-btn.ikon{width:34px;height:34px;padding:0!important;border-radius:11px!important}
.osc-yz .yz-btn.tam{width:100%;margin-top:8px}
.osc-yz .yz-btn.hafif{background:var(--z)!important;border-color:transparent!important}
.yz-don{display:inline-block;width:15px;height:15px;border:2.5px solid currentColor;border-right-color:transparent;border-radius:50%;animation:yzDon .7s linear infinite;vertical-align:-2px;opacity:.85}
.yz-don.b{width:30px;height:30px;border-width:3px;color:var(--i)}
@keyframes yzDon{to{transform:rotate(360deg)}}

/* ---------- 3. sorular ---------- */
.yz-baslik3{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:4px 4px 14px}
.yz-baslik3 h2{font-size:17px!important}
.yz-baslik3 p{color:var(--s);font-size:13px;margin-top:2px}
.yz-sorular{display:flex;flex-direction:column;gap:12px}
.yz-soru{background:#fff;border:1px solid var(--c);border-radius:20px;overflow:hidden;transition:box-shadow .2s,border-color .2s;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.yz-soru:hover{box-shadow:0 18px 36px -28px rgba(15,23,42,.5)}
.yz-soru.duzen{border-color:var(--i);box-shadow:0 0 0 4px rgba(91,91,246,.12)}
.yz-soru.k-b{border:1.5px dashed #fda4af}
.yz-soru.k-l{border-color:#ddd6fe}
.yz-soru.yeni{animation:yzYeni 1.3s ease}
@keyframes yzYeni{0%{box-shadow:0 0 0 0 rgba(16,185,129,.55);border-color:#34d399}100%{box-shadow:0 0 0 14px rgba(16,185,129,0)}}
.yz-soru-ust{display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--c);background:linear-gradient(180deg,#fbfbfe,#f6f7fb)}
.yz-soru-ust .no{font-style:normal;flex:none;width:34px;height:34px;border-radius:11px;background:var(--k);color:#fff;font-weight:800;display:flex;align-items:center;justify-content:center}
.yz-soru-ust .ck{flex:1;min-width:0;display:flex;flex-direction:column}
.yz-soru-ust .ck span{font-size:12.5px;color:var(--s);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.yz-k{flex:none;font-size:11.5px;font-weight:800;border-radius:99px;padding:4px 10px}
.yz-k.a{background:#e0f2fe;color:#0369a1}.yz-k.y{background:#f3e8ff;color:#7e22ce}.yz-k.o{background:#fef3c7;color:#b45309}.yz-k.b{background:#ffe4e6;color:#be123c}.yz-k.l{background:#ede9fe;color:#6d28d9}
.yz-puan{flex:none;display:flex;align-items:center;gap:4px;background:#fff;border:1.5px solid var(--c);border-radius:12px;padding:2px 8px 2px 2px;margin:0!important}
.osc-yz .yz-puan input{width:52px;border:0!important;background:transparent!important;text-align:center;font-weight:800;font-size:15px!important;padding:6px 2px!important;outline:0;color:var(--k)!important;-moz-appearance:textfield}
.yz-puan span{font-size:11.5px;color:var(--s);font-weight:700}
.yz-soru-ic{padding:16px 18px 6px;font-size:15px;line-height:1.6}
.yz-soru-ic img{max-width:100%;height:auto}
.yz-p{background:var(--z);border-left:3px solid #c7d2fe;border-radius:10px;padding:10px 14px;margin-bottom:10px;font-size:14.5px}
.yz-r{font-weight:600}
.yz-sil{color:var(--s);font-style:italic;font-weight:500}
.yz-sec{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:10px}
.yz-sec.kisa{grid-template-columns:repeat(4,1fr)}
.yz-sec>div{display:flex;gap:8px;align-items:flex-start;border:1px solid var(--c);border-radius:12px;padding:7px 10px;font-size:14px}
.yz-sec>div b{flex:none;width:22px;height:22px;border-radius:7px;background:var(--z);display:flex;align-items:center;justify-content:center;font-size:12px}
.yz-sec>div.d{border-color:#6ee7b7;background:#ecfdf5}
.yz-sec>div.d b{background:#10b981;color:#fff}
.yz-cvp{display:flex;gap:10px;align-items:flex-start;margin-top:10px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:8px 12px;font-size:14px}
.yz-cvp>b{flex:none;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#047857;background:#d1fae5;border-radius:6px;padding:2px 7px;margin-top:2px}
.yz-cvp i{color:var(--s)}
.yz-bos{display:flex;flex-direction:column;gap:2px;background:#fff1f2;border-radius:12px;padding:12px 14px;font-size:14px;color:#9f1239}
.yz-iskelet{display:flex;flex-direction:column;gap:9px;padding:4px 0 6px}
.yz-iskelet i{display:block;height:13px;border-radius:7px;background:linear-gradient(90deg,#eef0f6 25%,#e2e5ef 37%,#eef0f6 63%);background-size:400% 100%;animation:yzParla 1.3s ease infinite}
.yz-iskelet i:nth-child(2){width:86%}.yz-iskelet i.k{width:55%}
@keyframes yzParla{0%{background-position:100% 50%}100%{background-position:0 50%}}
.yz-yaziyor{display:flex;align-items:center;gap:8px;font-size:13px;color:#6d28d9;font-weight:700;padding:4px 0 12px}
.yz-soru-alt{display:flex;gap:6px;align-items:center;padding:10px 16px 14px}

/* editör */
.yz-ed{padding:16px 18px}
.yz-ed-tur{display:inline-flex;gap:4px;flex-wrap:wrap;background:var(--z);border:1px solid var(--c);border-radius:13px;padding:4px;margin-bottom:14px}
.osc-yz .yz-ed-tur button{background:transparent!important;border:0!important;border-radius:9px!important;padding:7px 12px!important;font-size:13px!important;font-weight:700!important;color:var(--k2)!important}
.osc-yz .yz-ed-tur button.on{background:#fff!important;color:var(--id)!important;box-shadow:0 4px 12px -6px rgba(15,23,42,.35)}
.yz-ce{min-height:46px;border:1.5px solid var(--c);border-radius:13px;padding:11px 13px;background:var(--z);outline:0;font-size:15px;line-height:1.55;transition:.15s}
.yz-ce.buyuk{min-height:80px}
.yz-ce:empty::before{content:attr(data-ph);color:#94a3b8}
.yz-ed-sik{display:flex;flex-direction:column;gap:7px;margin-bottom:12px}
.yz-sk{display:flex;align-items:center;gap:10px;margin:0!important;cursor:default}
.yz-sk input{position:absolute;opacity:0;pointer-events:none}
.yz-sk b{flex:none;width:34px;height:34px;border-radius:11px;border:1.5px solid var(--c);display:flex;align-items:center;justify-content:center;cursor:pointer;background:#fff;transition:.15s}
.yz-sk b:hover{border-color:#6ee7b7}
.yz-sk.d b{background:#10b981;border-color:#10b981;color:#fff}
.yz-sk .yz-ce{flex:1;min-height:40px;padding:8px 12px}
.yz-ed-alt{display:flex;justify-content:flex-end;gap:8px;padding-top:4px}

/* toplam puan halkası */
.yz-toplam{display:flex;align-items:center;gap:10px;font-size:14px;color:var(--k2);font-weight:600}
.yz-toplam i{flex:none;width:38px;height:38px;border-radius:50%;background:conic-gradient(var(--h) calc(var(--p) * 1%),#e2e8f0 0);display:flex;align-items:center;justify-content:center}
.yz-toplam i::after{content:"";width:28px;height:28px;border-radius:50%;background:#fff}
.yz-toplam.tamam i{background:conic-gradient(#10b981 100%,#e2e8f0 0)}
.yz-toplam b{font-size:17px;color:var(--h)}
.yz-toplam.tamam b{color:#059669}

/* ---------- 4. çıktı ---------- */
.yz-cikti{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:16px;align-items:start}
.yz-onizle{background:linear-gradient(180deg,#e9ecf5,#dfe3ee);border-radius:24px;padding:14px;border:1px solid var(--c)}
.yz-onizle-bas{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:2px 6px 12px}
.yz-onizle-bas>b{margin-right:auto}
.yz-kagit{max-width:820px;margin:0 auto;background:#fff;border-radius:6px;box-shadow:0 30px 60px -30px rgba(15,23,42,.45),0 2px 6px rgba(15,23,42,.08);overflow:hidden}
.yz-kagit iframe{display:block;width:100%;height:900px;border:0;background:#fff}
.yz-yan{position:sticky;top:16px;display:flex;flex-direction:column;gap:14px}
.yz-yan .yz-kart{margin:0}
.yz-yan h2{margin-bottom:6px}
.yz-kart.koyu{background:radial-gradient(500px 200px at 100% 0,rgba(192,38,211,.35),transparent 60%),linear-gradient(160deg,#0b1024,#1e1b4b 70%);border-color:#1e1b4b;color:#fff}
.yz-kart.koyu p{color:#cbd5e1;font-size:13.5px}
.yz-kart.koyu p.kucuk{font-size:12px;color:#a5b4fc;margin-top:12px}
.yz-indir{display:flex;flex-direction:column;gap:8px;margin-top:14px}
.osc-yz .yz-ib{display:flex;align-items:center;gap:12px;width:100%;background:rgba(255,255,255,.07)!important;border:1px solid rgba(255,255,255,.14)!important;border-radius:14px!important;padding:10px 12px!important;color:#fff!important;text-align:left;transition:.18s}
.osc-yz .yz-ib:hover{background:rgba(255,255,255,.14)!important;transform:translateX(2px)}
.yz-ib>span{flex:none;width:40px;height:40px;border-radius:11px;background:#2b579a;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px}
.yz-ib.pdf>span{background:#e11d48;font-size:11px}
.yz-ib b{display:block;font-size:14.5px}
.yz-ib small{color:#c7d2fe;font-size:12px}
.osc-yz .yz-ib.ana{background:#fff!important;color:var(--k)!important;border-color:#fff!important}
.osc-yz .yz-ib.ana small{color:var(--s)}

/* ---------- yazılılarım ---------- */
.yz-liste{display:flex;flex-direction:column;gap:8px}
.yz-li{display:flex;align-items:center;gap:12px;border:1px solid var(--c);border-radius:16px;padding:10px 12px;transition:.15s}
.yz-li:hover{border-color:#c7d2fe;box-shadow:0 12px 26px -22px rgba(91,91,246,.7)}
.yz-li .ik{flex:none;width:48px;height:48px;border-radius:14px;background:var(--g);color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;line-height:1}
.yz-li .ik b{font-size:19px}.yz-li .ik small{font-size:9.5px;opacity:.85}
.yz-li .bil{flex:1;min-width:0;display:flex;flex-direction:column}
.yz-li .bil b{font-size:14.5px}
.yz-li .bil small{color:var(--s);font-size:12.5px}
.yz-bos-liste{text-align:center;padding:36px 10px;color:var(--s)}
.yz-bos-liste div{font-size:44px}
.yz-bos-liste b{display:block;color:var(--k);font-size:16px;margin:6px 0 2px}

/* bildirim */
.yz-tost{position:fixed;left:50%;bottom:24px;transform:translate(-50%,30px);opacity:0;z-index:100000;display:flex;align-items:center;gap:10px;max-width:min(560px,calc(100vw - 32px));background:#0b1220;color:#fff;border-radius:16px;padding:12px 16px;font:600 14px/1.45 "Plus Jakarta Sans",system-ui,sans-serif;box-shadow:0 20px 40px -14px rgba(0,0,0,.5);transition:.3s}
.yz-tost.on{opacity:1;transform:translate(-50%,0)}
.yz-tost i{font-style:normal;flex:none;width:24px;height:24px;border-radius:50%;background:#6366f1;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px}
.yz-tost.hata i{background:#e11d48}.yz-tost.uyari i{background:#f59e0b}.yz-tost.ok i{background:#10b981}

/* ---------- mobil ---------- */
@media (max-width:900px){
  .yz-cikti{grid-template-columns:1fr}
  .yz-yan{position:static}
  .yz-siniflar{grid-template-columns:repeat(4,1fr)}
  .yz-iki,.yz-slotlar{grid-template-columns:1fr}
}
@media (max-width:640px){
  .osc-yz{font-size:14.5px}
  .yz-hero{border-radius:20px;padding:12px 14px}
  .yz-hero.buyuk{padding:14px 16px 24px}
  .yz-sek{margin-left:0;width:100%}
  .osc-yz .yz-sek button,.osc-yz .yz-sek a{flex:1;padding:8px 6px!important;font-size:13px!important}
  .yz-hero-yazi{margin-top:18px}
  .yz-hero-yazi p{font-size:14.5px}
  .yz-adimlar{grid-template-columns:repeat(4,1fr);padding:6px!important}
  .osc-yz .yz-adimlar button{flex-direction:column;gap:4px;padding:6px 2px!important;text-align:center}
  .yz-adimlar small{display:none}
  .yz-adimlar b{font-size:12px}
  .yz-kart{padding:16px;border-radius:18px}
  .yz-dersler{grid-template-columns:1fr 1fr}
  .yz-izgara2{grid-template-columns:1fr}
  .yz-sec,.yz-sec.kisa{grid-template-columns:1fr 1fr}
  .yz-soru-ust{flex-wrap:wrap}
  .yz-soru-ust .ck{order:5;flex-basis:100%}
  .yz-k{margin-left:auto}
  .yz-cubuk{bottom:8px;padding:8px}
  .yz-cubuk .yz-ozet{min-width:0;flex-basis:100%;padding-left:6px}
  .yz-cubuk .yz-btn.ana{flex:1}
  .yz-kagit iframe{height:640px}
}

/* öğretmen paneline gömülü */
.osc-yz.gomulu{width:auto!important;margin:0 0 30px!important}
.osc-yz.gomulu .yz-hero{border-radius:20px;padding:12px 16px}

/* matematik araç çubuğu ve önizleme */
.yz-mat{display:flex;flex-wrap:wrap;gap:5px;align-items:center;background:var(--z);border:1px solid var(--c);border-radius:13px;padding:6px;margin-bottom:12px}
.osc-yz .yz-mat button{min-width:36px;height:34px;background:#fff!important;border:1px solid var(--c)!important;border-radius:9px!important;padding:0 8px!important;font-size:15px!important;color:var(--k)!important;font-weight:600!important}
.osc-yz .yz-mat button:hover{border-color:var(--i)!important;color:var(--id)!important}
.yz-mat .ipucu{font-size:11.5px;color:var(--s);margin-left:4px}
.yz-ed-on{border:1.5px dashed #c7d2fe;border-radius:13px;padding:10px 14px;margin:4px 0 12px;background:#fafaff}
.yz-ed-on small{display:block;font-size:11px;font-weight:800;color:var(--id);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px}
.yz-soru-ic .katex,.yz-ed-on .katex{font-size:1.12em}

.yz-tb{border-collapse:collapse;margin:8px 0;width:100%;font-size:14px}
.yz-tb th,.yz-tb td{border:1px solid var(--c);padding:6px 10px;text-align:left;vertical-align:top}
.yz-tb th{background:var(--z);font-weight:800}
.yz-blok{display:inline-block;margin:4px 0 2px}
.yz-blok .katex{font-size:1.25em}
.osc-yz .yz-mod{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:0 0 16px}
.osc-yz .yz-mod button{display:grid;grid-template-columns:auto 1fr;grid-template-rows:auto auto;column-gap:12px;align-items:center;text-align:left;background:#fff!important;border:1.5px solid var(--c)!important;border-radius:18px!important;padding:14px 16px!important;color:var(--k)!important;transition:.18s}
.osc-yz .yz-mod button span{grid-row:1/3;width:44px;height:44px;border-radius:13px;background:var(--z);display:flex;align-items:center;justify-content:center;font-size:22px}
.osc-yz .yz-mod button b{font-size:15.5px;font-weight:800}
.osc-yz .yz-mod button small{font-size:12.5px;color:var(--s)}
.osc-yz .yz-mod button.on{border-color:var(--i)!important;background:var(--ia)!important;box-shadow:0 12px 26px -18px rgba(91,91,246,.8)}
.osc-yz .yz-mod button.on span{background:var(--g);color:#fff}
.osc-yz .yz-gruplar{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:10px}
.osc-yz .yz-gruplar button{display:flex;align-items:center;gap:10px;background:#fff!important;border:1.5px solid var(--c)!important;border-radius:14px!important;padding:11px 13px!important;color:var(--k)!important;text-align:left;font-size:14px}
.osc-yz .yz-gruplar button span{font-size:20px}
.osc-yz .yz-gruplar button.on{border-color:var(--i)!important;background:var(--ia)!important}
.osc-yz .yz-alansec,.osc-yz .yz-in{flex:1;min-width:220px;border:1.5px solid var(--c)!important;border-radius:12px!important;padding:10px 12px!important;font-size:14.5px!important;background:#fff!important;color:var(--k)!important;outline:0;box-shadow:none!important;height:auto!important}
.osc-yz .yz-alansec:focus,.osc-yz .yz-in:focus{border-color:var(--i)!important}
.osc-yz .yz-dersler.kucuk button{flex-direction:column;align-items:flex-start;gap:3px}
.osc-yz .yz-dersler.kucuk button b{font-size:13.5px;line-height:1.3}
.osc-yz .yz-dersler.kucuk button small{font-size:11.5px;color:var(--s);font-weight:600}
.osc-yz .yz-dersler button.on{border-color:var(--i)!important;background:var(--ia)!important}
.osc-yz .yz-siniflar{margin-top:14px}
.osc-yz .yz-konular{display:flex;flex-direction:column;gap:8px;margin:0 0 12px}
.osc-yz .yz-konu{display:flex;align-items:center;gap:10px}
.osc-yz .yz-konu>i{font-style:normal;width:28px;height:28px;flex:none;border-radius:9px;background:var(--ia);color:var(--id);font-weight:800;font-size:13px;display:flex;align-items:center;justify-content:center}
.osc-yz .yz-konu .yz-in{min-width:0}
.osc-yz .yz-x{border:0!important;background:#fff0f3!important;color:var(--h)!important;width:32px;height:32px;border-radius:10px!important;font-size:18px;padding:0!important;flex:none}
@media(max-width:700px){.osc-yz .yz-mod{grid-template-columns:1fr}.osc-yz .yz-konu{flex-wrap:wrap}.osc-yz .yz-konu .yz-in{flex-basis:calc(100% - 40px)}}
.osc-yz .yz-bosh{background:linear-gradient(135deg,#fff7ed,#fff)!important;border-color:#fed7aa!important}
.osc-yz .yz-bosh-ic{display:flex;gap:16px;align-items:flex-start}
.osc-yz .yz-bosh-ic>span{font-size:34px;line-height:1}
.osc-yz .yz-bosh-ic h2{font-size:17px;margin:0 0 6px}
.osc-yz .yz-bosh-ic p{color:var(--k2);font-size:14px;line-height:1.6;margin:0 0 12px}
<?php }
    public static function js_cikti() { ?>/* =====================================================================
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
        case 'sag': g += cokSatir(b.s, { b: !!b.kalin }, '<w:spacing w:after="40"/><w:jc w:val="right"/>'); break;
        case 'bosluk': g += p('', '<w:spacing w:after="' + (b.n || 240) + '"/>'); break;
        case 'onay': g += p('', '<w:spacing w:after="120"/>') + ortala(wr(b.s || 'UYGUNDUR', { b: true, sz: 22 }), SP0) + ortala(wr(b.tarih || '…/…/20…', { sz: 21 }), SP0) + p('', '<w:spacing w:after="360"/>') + ortala(wr(b.ad || '………………………', { b: true, sz: 21 }), SP0) + ortala(wr(b.gorev || 'Okul Müdürü', { sz: 21 }), SP0); break;
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

(function () {
  'use strict';
  function BASLAT() {
  var C = window.OSC_YZ, SNR = window.OSC_SNR || { tablolar: [] }, kok = document.getElementById('osc-yz');
  if (!C || !kok) return;

  /* ================= yardımcılar ================= */
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function ls(k, v) { try { if (v === undefined) return JSON.parse(localStorage.getItem(k) || 'null'); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, JSON.stringify(v)); } catch (e) { if (v !== undefined) throw e; return null; } }
  function trUp(s) { return String(s || '').replace(/i/g, 'İ').replace(/ı/g, 'I').toUpperCase(); }
  function bugun() { var d = new Date(); return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
  function tarihTr(s) { if (!s) return ''; var p = s.split('-'); return p.length === 3 ? p[2] + '.' + p[1] + '.' + p[0] : s; }
  function tarihSaat() { var d = new Date(), z = function (n) { return ('0' + n).slice(-2); }; return z(d.getDate()) + '.' + z(d.getMonth() + 1) + '.' + d.getFullYear() + ' ' + z(d.getHours()) + ':' + z(d.getMinutes()); }
  function bildir(m, tur) {
    var t = document.createElement('div'); t.className = 'yz-tost ' + (tur || ''); t.innerHTML = '<i>' + ({ hata: '!', uyari: '!', ok: '✓' }[tur] || 'i') + '</i><span>' + esc(m) + '</span>'; document.body.appendChild(t);
    setTimeout(function () { t.classList.add('on'); }, 10); setTimeout(function () { t.classList.remove('on'); setTimeout(function () { t.remove(); }, 300); }, 4200);
  }

  /* ================= matematik (LaTeX → KaTeX) ================= */
  var KATEX = 'https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/';
  function katexYukle() {
    if (window.katex || document.getElementById('osc-katex')) return;
    var l = document.createElement('link'); l.rel = 'stylesheet'; l.href = KATEX + 'katex.min.css'; l.id = 'osc-katex-css'; document.head.appendChild(l);
    var sc = document.createElement('script'); sc.id = 'osc-katex'; sc.src = KATEX + 'katex.min.js'; sc.onload = function () { try { if (sekme === 'yeni' && (S.adim === 3 || S.adim === 4)) ciz(); } catch (e) {} }; document.head.appendChild(sc);
  }
  function varliklariCoz(t) { return String(t).replace(/<br\s*\/?>/g, ' ').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&'); }
  /* yapay zekânın bozuk yazdığı LaTeX'i onar: \\frac -> \frac, "frac{" -> "\frac{", eksik kapanan parantez */
  function texDuzelt(t) {
    t = String(t || '').replace(/\\{2,}(?=[a-zA-Z])/g, '\\').replace(/(^|[^\\a-zA-Z])(d?frac|sqrt|times|cdot|div|overline|left|right|leq?|geq?|neq?|pi)(?=[\{\[\(\s\d])/g, '$1\\$2');
    var ac = (t.match(/\{/g) || []).length - (t.match(/\}/g) || []).length; while (ac-- > 0) t += '}';
    return t;
  }
  window.OSC_TEX_DUZELT = texDuzelt;
  function mat(html) {
    html = String(html == null ? '' : html);
    if (!window.katex || (html.indexOf('$') < 0 && html.indexOf('\\(') < 0)) return html;
    var r = function (tex, blok) { tex = texDuzelt(varliklariCoz(tex)); try { return window.katex.renderToString(tex, { throwOnError: false, strict: false, displayMode: !!blok }); } catch (e) { return tex; } };
    // tek geçiş: işlenmiş formülün içinde yeniden $ aranmaz
    return html.replace(/\$\$([\s\S]{1,600}?)\$\$|\\\(([\s\S]{1,600}?)\\\)|\$([^$\n]{1,600}?)\$/g, function (m, a, b, c, ofs, tum) {
      if (a != null) return r(a, true);
      if (b != null) return r(b);
      var once = tum.slice(0, ofs), sonra = tum.slice(ofs + m.length);
      // tek başına satırdaki formül büyük, satır içindekiler de kesirleri okunur boyutta
      if (/(^|<br\s*\/?>)\s*$/.test(once) && /^\s*(<br|$)/.test(sonra)) return '<span class="yz-blok">' + r('\\displaystyle ' + c) + '</span>';
      return r(/\\frac|\\sqrt\[|frac\{/.test(c) ? '\\displaystyle ' + c : c);
    });
  }
  function matVar(html) { return /\$[^$]+\$|\\\(/.test(String(html || '')); }

  /* ================= sunucu ================= */
  function sunucu(a, d, ms) {
    var fd = new FormData(); fd.append('action', 'osc_yz_' + a); fd.append('nonce', C.nonce);
    Object.keys(d || {}).forEach(function (k) { fd.append(k, typeof d[k] === 'object' ? JSON.stringify(d[k]) : d[k]); });
    var ac = window.AbortController ? new AbortController() : null, zt = ac && ms ? setTimeout(function () { ac.abort(); }, ms) : 0;
    return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin', signal: ac ? ac.signal : undefined })
      .then(function (r) { return r.text().then(function (t) { try { return JSON.parse(t); } catch (e) { throw new Error(r.status >= 500 || r.status === 0 ? 'Sunucu zaman aşımına uğradı (' + r.status + ').' : 'Sunucu hatası (' + r.status + ').'); } }); })
      .then(function (j) { clearTimeout(zt); if (!j || !j.success) throw new Error((j && j.data) || 'Bir hata oluştu'); return j.data; })
      .catch(function (e) { clearTimeout(zt); if (e && e.name === 'AbortError') throw new Error('Yanıt çok uzun sürdü.'); throw e; });
  }
  /* bağımsız kurulum (yazılımatik): Yazılılarım tarayıcıda saklanır */
  function kayitlar(v) { if (v === undefined) return ls('osc_yz_kayitlar') || []; ls('osc_yz_kayitlar', v.slice(0, 60)); }
  var YEREL = {
    kaydet: function (d) {
      var v = d.veri, l = kayitlar(), id = +d.id || Date.now();
      l = l.filter(function (x) { return x.id !== id; }); l.unshift({ id: id, baslik: (v.bilgi && v.bilgi.baslik) || 'Yazılı', tarih: tarihSaat(), veri: v });
      try { kayitlar(l); } catch (e) { throw new Error('Tarayıcı belleği dolu; eski yazılılardan bazılarını silin.'); }
      var kendi = (v.sorular || []).filter(function (q) { return q.kaynak === 'ogretmen'; });
      if (kendi.length) { var im = JSON.stringify(kendi.map(function (q) { return q.soru; })), g = YEREL._g = YEREL._g || {}; if (!g[im]) { g[im] = 1; sunucu('havuza', { veri: { sinif: v.sinif, ders: v.ders, donem: v.donem, yazili: v.yazili, sorular: kendi } }).catch(function () {}); } }
      if (v.bilgi && v.bilgi.okul) { try { ls('osc_yz_okul', v.bilgi.okul); } catch (e) {} }
      return { id: id };
    },
    liste: function () { return kayitlar().map(function (x) { var v = x.veri || {}; return { id: x.id, baslik: x.baslik, tarih: x.tarih, sinif: v.sinif, ders: v.ders, yazili: v.yazili, senaryo: v.senaryo, n: (v.sorular || []).length }; }); },
    getir: function (d) { var x = kayitlar().filter(function (k) { return k.id === +d.id; })[0]; if (!x) throw new Error('Yazılı bulunamadı.'); return { id: x.id, veri: x.veri }; },
    sil: function (d) { kayitlar(kayitlar().filter(function (k) { return k.id !== +d.id; })); return true; }
  };
  function api(a, d, ms) {
    if (C.yerel && YEREL[a]) return new Promise(function (ok, no) { try { ok(YEREL[a](d || {})); } catch (e) { no(e); } });
    return sunucu(a, d, ms);
  }
  /* yapay zekânın bu tarayıcı için yazdığı (henüz onaylanmamış) sorular tekrar kullanılabilsin */
  function benim(ekle) { var l = ls('osc_yz_benim') || []; if (ekle) { [].concat(ekle).forEach(function (x) { if (x && l.indexOf(x) < 0) l.push(x); }); try { ls('osc_yz_benim', l.slice(-300)); } catch (e) {} } return l; }

  /* ================= durum ================= */
  var ZOR = [['0', 'Karışık', 'Dengeli dağılım', '🎯'], ['1', 'Kolay', 'Temel düzey', '🌱'], ['2', 'Orta', 'Sınıf düzeyi', '⚖️'], ['3', 'Zor', 'Üst düzey düşünme', '🔥']];
  var TUR = [['karisik', 'Karışık', 'Açık uçlu + test', '🧩'], ['acik', 'Açık uçlu', 'Klasik yazılı', '✍️'], ['test', 'Test', 'Çoktan seçmeli', '🔘']];
  var ALAN = [['az', 'Dar', 'Kısa cevaplar'], ['normal', 'Normal', 'Puana göre'], ['genis', 'Geniş', 'Uzun cevaplar'], ['bos', 'Boş alan', 'Çizgisiz']];
  var KAYNAK = [['yz', 'Yapay zekâ ile özgün', 'Her soru çıktıya göre yeniden yazılır', '✨'], ['arsiv', 'Soru havuzu', 'Senaryoya uygun sorular havuzdan hemen gelir', '📚']];
  var KAY = { arsiv: ['Arşiv', 'a'], yz: ['Yapay zekâ', 'y'], ogretmen: ['Sizin sorunuz', 'o'], bos: ['Boş', 'b'], yukleniyor: ['Yazılıyor…', 'l'] };
  var DERS_IK = { 'Türkçe': '📖', 'Matematik': '➗', 'Fen Bilimleri': '🔬', 'Sosyal Bilgiler': '🌍', 'İngilizce': '🇬🇧', 'Din Kültürü ve Ahlak Bilgisi': '🕌', 'T.C. İnkılap Tarihi ve Atatürkçülük': '🇹🇷', 'Fizik': '⚛️', 'Kimya': '⚗️', 'Biyoloji': '🧬', 'Tarih': '🏛️', 'Coğrafya': '🗺️', 'Felsefe': '💭', 'Türk Dili ve Edebiyatı': '✒️' };
  var RENK = ['#6366f1', '#06b6d4', '#f59e0b', '#10b981', '#ec4899', '#8b5cf6', '#ef4444', '#14b8a6', '#84cc16', '#f97316'];

  function yeniDurum() { return { adim: 1, yil: SNR.yil || '2026-2027', sinif: 0, ders: '', donem: varsayilanDonem(), yazili: 1, senaryo: 0, ozel: {}, bilgi: { okul: C.okul || (C.yerel ? ls('osc_yz_okul') || '' : ''), baslik: '', ogretmen: C.ad || '', tarih: bugun(), sure: 40 }, zorluk: 0, tur: 'acik', alan: 'normal', kaynak: 'arsiv', slotlar: [], sorular: [], anahtar: true, id: 0 }; }
  var S = ls('osc_yz_taslak') || yeniDurum(), sekme = 'yeni', kuyrukNo = 0;
  if (!S.alan) S.alan = 'normal';
  if (!S.kaynak) S.kaynak = 'arsiv';
  if (!C.yz || C.yzmod !== 'acik') S.kaynak = 'arsiv';
  (S.sorular || []).forEach(function (q) { if (q.kaynak === 'yukleniyor') q.kaynak = 'bos'; });
  function sakla() { try { ls('osc_yz_taslak', S); } catch (e) {} }

  /* ================= senaryo verisi ================= */
  function tablolar(sinif) { return SNR.tablolar.filter(function (t) { return t.sinif === +sinif && (t.donem || 1) === S.donem; }); }
  function donemVar(d) { return (SNR.donemler || [1]).indexOf(d) >= 0; }
  function varsayilanDonem() { var m = new Date().getMonth() + 1; return m >= 2 && m <= 8 && donemVar(2) ? 2 : (donemVar(1) ? 1 : 2); }
  var DG = window.OSC_DIGER || { gruplar: [] };
  function ozKod(k) { var t = String(k || '').toLocaleLowerCase('tr-TR').replace(/\s+/g, ' ').trim(), h = 5381; for (var i = 0; i < t.length; i++) h = ((h * 33) ^ t.charCodeAt(i)) >>> 0; return 'oz-' + h.toString(36); }
  function digerTablo() {
    var d = S.dg || {}; if (!S.ders || !S.sinif) return null;
    var st = (d.konular || []).filter(function (k) { return String(k.konu || '').trim(); }).map(function (k) { var t = String(k.konu).trim(); return { kod: ozKod(t), konu: S.ders, cikti: t, sayi: {} }; });
    return st.length ? { sinif: S.sinif, ders: S.ders, ozel: 1, alan: d.alan || '', satirlar: st } : null;
  }
  function tablo() { if (S.mod === 'diger') return digerTablo(); for (var i = 0; i < SNR.tablolar.length; i++) { var t = SNR.tablolar[i]; if (t.sinif === +S.sinif && t.ders === S.ders && (t.donem || 1) === S.donem) return t; } return null; }
  function kodu(x) { return x.kod || x.cikti.slice(0, 38); }
  function kodGoster(k) { return k && k.indexOf('.') > 0 && k.length < 20 ? k : ''; }
  function senaryoSlot(t, y, sn) { var o = []; t.satirlar.forEach(function (x) { var n = ((x.sayi || {})[String(y)] || {})[String(sn)] || 0; for (var i = 0; i < n; i++) o.push({ kod: kodu(x), cikti: x.cikti, konu: x.konu, ben: false }); }); return o; }
  function ozelSlot(t) { var o = []; t.satirlar.forEach(function (x) { var n = S.ozel[kodu(x)] || 0; for (var i = 0; i < n; i++) o.push({ kod: kodu(x), cikti: x.cikti, konu: x.konu, ben: false }); }); return o; }
  function ulkeGeneli(t) { return !!(t.ulke && t.ulke[String(S.yazili)]) || !((t.senaryolar || {})[String(S.yazili)] || []).length; }
  function varsayilanBaslik() { return S.yil + ' EĞİTİM-ÖĞRETİM YILI ' + S.sinif + '. SINIF ' + trUp(S.ders) + ' DERSİ ' + S.donem + '. DÖNEM ' + S.yazili + '. YAZILI SINAVI'; }
  function baslikYalin(b) { return String(b || '').replace(/^\s*\d{4}\s*[-–]\s*\d{4}\s*EĞİTİM[\s-]*ÖĞRETİM YILI\s*/i, ''); }
  function konuRenk(t) { var m = {}, i = 0; t.satirlar.forEach(function (x) { if (!(x.konu in m)) m[x.konu] = RENK[i++ % RENK.length]; }); return m; }

  /* ================= çerçeve ================= */
  function enFazlaAdim() { if (!S.slotlar.length) return 1; if (!S.sorular.length) return 2; return 4; }
  function cerceve(ic) {
    var ad = [['Seçim', 'Sınıf · ders · senaryo'], ['Bilgiler', 'Okul · zorluk · tür'], ['Sorular', 'Değiştir · düzenle · puanla'], ['Çıktı', 'Word · PDF · yazdır']];
    var ilerleme = sekme === 'yeni' ? Math.round((S.adim - 1) / 3 * 100) : 0;
    kok.innerHTML = '<header class="yz-hero' + (sekme === 'yeni' && S.adim === 1 && !C.gomulu ? ' buyuk' : '') + '"><div class="yz-hero-ic"><a class="yz-marka" href="' + esc(C.home) + '">' + (C.gomulu ? '' : '<img src="' + esc(C.logo) + '" alt="soruindir">') + '<span>' + (C.gomulu ? '📄 ' : '') + 'Yazılı Hazırla</span></a>' +
      '<nav class="yz-sek"><button data-sek="yeni" class="' + (sekme === 'yeni' ? 'on' : '') + '">✨ Yeni yazılı</button><button data-sek="liste" class="' + (sekme === 'liste' ? 'on' : '') + '">📁 Yazılılarım</button>' + (C.panel ? '<a href="' + esc(C.panel) + '">Panel</a>' : '') + (C.giris ? '<a href="' + esc(C.giris) + '">👤 Giriş yap</a>' : '') + '</nav></div>' +
      (sekme === 'yeni' && S.adim === 1 && !C.gomulu ? '<div class="yz-hero-yazi"><span class="yz-etiket">MEB ' + esc(S.yil) + ' konu-soru dağılım tablolarına uygun</span><h1>Yazılınız <em>bir dakikada</em> hazır</h1><p>Sınıfı, dersi ve senaryoyu seçin; sorular senaryodaki öğrenme çıktılarına göre otomatik gelsin. Beğenmediğinizi değiştirin, isterseniz kendiniz yazın, Word veya PDF olarak indirin.</p><div class="yz-hero-ozellik"><span>📚 5–12. sınıf, tüm dersler</span><span>🎯 Senaryoya birebir uygun</span><span>🔑 Cevap anahtarlı çıktı</span></div></div>' : '') +
      '</header>' +
      (sekme === 'yeni' ? '<ol class="yz-adimlar" style="--ilerleme:' + ilerleme + '%">' + ad.map(function (a, i) { var n = i + 1; return '<li class="' + (S.adim === n ? 'on' : (S.adim > n ? 'ok' : '')) + '"><button data-git="' + n + '"' + (n > enFazlaAdim() ? ' disabled' : '') + '><i>' + (S.adim > n ? '✓' : n) + '</i><span><b>' + a[0] + '</b><small>' + a[1] + '</small></span></button></li>'; }).join('') + '</ol>' : '') +
      '<main class="yz-ana">' + ic + '</main>';
    kok.querySelectorAll('[data-sek]').forEach(function (b) { b.onclick = function () { editorleriSakla(); sekme = b.dataset.sek; ciz(); }; });
    kok.querySelectorAll('[data-git]').forEach(function (b) { b.onclick = function () { git(+b.dataset.git); }; });
  }
  function git(n) { editorleriSakla(); S.adim = n; sakla(); ciz(); var y = kok.getBoundingClientRect().top + window.pageYOffset - 16; if (window.pageYOffset > y) window.scrollTo({ top: y, behavior: 'smooth' }); }
  function ciz() { if (sekme === 'liste') return vListe(); [null, v1, v2, v3, v4][S.adim](); }

  /* ================= 1. seçim ================= */
  function modSec() {
    return '<div class="yz-mod"><button data-mod="meb" class="' + (S.mod !== 'diger' ? 'on' : '') + '"><span>🏫</span><b>MEB senaryolu dersler</b><small>Ortak dersler · konu-soru dağılım tablosu</small></button><button data-mod="diger" class="' + (S.mod === 'diger' ? 'on' : '') + '"><span>🧩</span><b>Diğer dersler</b><small>Seçmeli · meslek lisesi · İmam Hatip · güzel sanatlar · spor</small></button></div>';
  }
  function modBagla() { kok.querySelectorAll('[data-mod]').forEach(function (b) { b.onclick = function () { var m = b.dataset.mod; if ((S.mod || 'meb') === m) return; S.mod = m; S.sinif = 0; S.ders = ''; S.senaryo = 0; S.ozel = {}; S.dg = { grup: '', alan: '', konular: [] }; sakla(); v1(); }; }); }
  function vDiger() {
    var d = S.dg = S.dg || { grup: '', alan: '', konular: [] }, g = DG.gruplar.filter(function (x) { return x.id === d.grup; })[0];
    var al = g && g.alanlar ? g.alanlar.filter(function (a) { return a.ad === d.alan; })[0] : null, liste = g ? (g.alanlar ? (al ? al.dersler : []) : g.dersler) : [];
    var sn = []; liste.forEach(function (x) { x.s.forEach(function (k) { if (sn.indexOf(k) < 0) sn.push(k); }); }); sn.sort(function (a, b) { return a - b; });
    if (g && !sn.length) sn = g.id === 'oo_secmeli' || g.id === 'iho' ? [5, 6, 7, 8] : [9, 10, 11, 12];
    var dl = liste.filter(function (x) { return x.s.indexOf(S.sinif) >= 0; });
    var t = digerTablo(), toplam = (d.konular || []).reduce(function (a, k) { return a + (String(k.konu || '').trim() ? (+k.n || 0) : 0); }, 0);
    var h = modSec() + '<section class="yz-kart"><div class="yz-kart-bas"><span class="yz-num">1</span><div><h2>Ders</h2><p>Seçmeli, meslek ve alan derslerinin tamamı · listede olmayan dersi kendiniz yazabilirsiniz</p></div></div>' +
      '<div class="yz-gruplar">' + DG.gruplar.map(function (x) { return '<button data-dg="' + x.id + '" class="' + (d.grup === x.id ? 'on' : '') + '"><span>' + x.ik + '</span><b>' + esc(x.ad) + '</b></button>'; }).join('') + '</div>' +
      (g && g.alanlar ? '<div class="yz-satir"><span class="yz-et">Alan</span><select class="yz-alansec" id="yz-alan"><option value="">— Alan seçin (' + g.alanlar.length + ' alan) —</option>' + g.alanlar.map(function (a) { return '<option' + (a.ad === d.alan ? ' selected' : '') + '>' + esc(a.ad) + '</option>'; }).join('') + '</select></div>' : '') +
      (g && (!g.alanlar || al) ? '<div class="yz-siniflar">' + sn.map(function (s) { return '<button data-dsn="' + s + '" class="' + (S.sinif === s ? 'on' : '') + '"><b>' + s + '</b><small>' + (s <= 8 ? 'ortaokul' : 'lise') + '</small></button>'; }).join('') + '</div>' : '') +
      (S.sinif && g && (!g.alanlar || al) ? '<div class="yz-dersler kucuk">' + dl.map(function (x) { return '<button data-dders="' + esc(x.ad) + '" class="' + (S.ders === x.ad ? 'on' : '') + '"><b>' + esc(x.ad) + '</b>' + (x.dal ? '<small>' + esc(x.dal) + '</small>' : '') + '</button>'; }).join('') + '</div>' +
        '<div class="yz-satir"><span class="yz-et">Listede yok mu?</span><input class="yz-in" id="yz-dders" maxlength="90" placeholder="Ders adını yazın" value="' + esc(dl.some(function (x) { return x.ad === S.ders; }) ? '' : S.ders) + '"></div>' : (g ? '' : '<div class="yz-ipucu">👆 Ders grubunu seçin</div>')) +
      (S.ders ? '<div class="yz-satir"><span class="yz-et">Dönem / yazılı</span><div class="yz-seg">' + [1, 2].map(function (x) { return '<button data-ddon="' + x + '" class="' + (S.donem === x ? 'on' : '') + '">' + x + '. dönem</button>'; }).join('') + '</div><div class="yz-seg">' + [1, 2].map(function (y) { return '<button data-dyaz="' + y + '" class="' + (S.yazili === y ? 'on' : '') + '">' + y + '. yazılı</button>'; }).join('') + '</div></div>' : '') + '</section>';
    var sk = S.sinif + '|' + S.ders, sy = d.say && d.say.k === sk ? d.say.n : null;
    if (S.ders && sy === 0) h += '<section class="yz-kart yz-bosh"><div class="yz-bosh-ic"><span>📭</span><div><h2>Bu ders için soru havuzunda henüz soru yok</h2><p>Konularınızı ve soru sayılarını yazın; sorularınızı kendiniz yazıp sınav kâğıdını <b>hemen yazdırın</b>. Yazdığınız sorular havuza da eklenir, sonraki yazılılarda size ve diğer öğretmenlere yardımcı olur.' + (C.yz ? ' İsterseniz eksik kalan soruları yapay zekâ da yazabilir.' : '') + '</p><label class="yz-anahtar"><input type="checkbox" id="yz-kendim"' + (d.kendim ? ' checked' : '') + '><em></em><span>✍️ Sorularımı kendim yazacağım</span></label></div></div></section>';
    else if (S.ders && sy > 0) h += '<div class="yz-ipucu">📚 Soru havuzunda bu derse ait <b>' + sy + '</b> soru var; konularınıza uygun olanlar otomatik gelir.</div>';
    if (S.ders) {
      h += '<section class="yz-kart"><div class="yz-kart-bas"><span class="yz-num">2</span><div><h2>Kendi senaryonuz</h2><p>Yazılıya girecek konuları ve her konudan kaç soru sorulacağını belirleyin</p></div>' + (C.yz ? '<button class="yz-btn hafif sag" id="yz-konu-oner">🤖 Konuları yapay zekâ önersin</button>' : '') + '</div>' +
        '<div class="yz-konular">' + (d.konular.length ? d.konular.map(function (k, i) { return '<div class="yz-konu"><i>' + (i + 1) + '</i><input class="yz-in" data-kn="' + i + '" maxlength="180" placeholder="Konu / öğrenme çıktısı (ör. Ohm Kanunu ve direnç hesapları)" value="' + esc(k.konu) + '"><div class="yz-sayac"><button data-kd="' + i + '" data-d="-1">−</button><b>' + (+k.n || 0) + '</b><button data-kd="' + i + '" data-d="1">+</button></div><button class="yz-x" data-ksil="' + i + '" title="Kaldır">×</button></div>'; }).join('') : '<div class="yz-ipucu">Konu ekleyin ya da yapay zekâya önerdirin. Sorular arşivden, yapay zekâdan gelir; isterseniz kendiniz yazarsınız.</div>') + '</div>' +
        '<button class="yz-btn hafif" id="yz-konu-ekle">＋ Konu ekle</button></section>';
    }
    h += '<div class="yz-cubuk"><div class="yz-ozet">' + (t && toplam ? '<b>' + toplam + ' soruluk yazılı</b><span>' + S.sinif + '. sınıf · ' + esc(S.ders) + ' · ' + S.yazili + '. yazılı · kendi senaryonuz</span>' : '<b>Seçim yapın</b><span>Ders, sınıf ve konular</span>') + '</div><button class="yz-btn ana" id="yz-uygula"' + (t && toplam ? '' : ' disabled') + '>Seçimi uygula <i>→</i></button></div>';
    cerceve(h); modBagla();
    var yen = function () { sakla(); vDiger(); };
    if (S.ders && sy === null && !(d.say && d.say.yukl === sk)) { d.say = { k: '', yukl: sk }; api('say', { veri: { sinif: S.sinif, ders: S.ders } }, 20000).then(function (r) { d.say = { k: sk, n: r.n }; if (r.n === 0 && d.kendim === undefined) d.kendim = true; if (S.mod === 'diger' && S.adim === 1 && sekme === 'yeni') vDiger(); }).catch(function () {}); }
    var kd0 = document.getElementById('yz-kendim'); if (kd0) kd0.onchange = function () { d.kendim = kd0.checked; sakla(); };
    kok.querySelectorAll('[data-dg]').forEach(function (b) { b.onclick = function () { d.grup = b.dataset.dg; d.alan = ''; S.sinif = 0; S.ders = ''; yen(); }; });
    var as = document.getElementById('yz-alan'); if (as) as.onchange = function () { d.alan = as.value; S.sinif = 0; S.ders = ''; yen(); };
    kok.querySelectorAll('[data-dsn]').forEach(function (b) { b.onclick = function () { S.sinif = +b.dataset.dsn; S.ders = ''; yen(); }; });
    kok.querySelectorAll('[data-dders]').forEach(function (b) { b.onclick = function () { if (S.ders !== b.dataset.dders) d.konular = []; S.ders = b.dataset.dders; yen(); }; });
    var di = document.getElementById('yz-dders'); if (di) di.onchange = function () { var v = di.value.trim(); if (v) { if (S.ders !== v) d.konular = []; S.ders = v; yen(); } };
    kok.querySelectorAll('[data-ddon]').forEach(function (b) { b.onclick = function () { S.donem = +b.dataset.ddon; yen(); }; });
    kok.querySelectorAll('[data-dyaz]').forEach(function (b) { b.onclick = function () { S.yazili = +b.dataset.dyaz; yen(); }; });
    kok.querySelectorAll('[data-kn]').forEach(function (e) { e.oninput = function () { d.konular[+e.dataset.kn].konu = e.value; sakla(); }; e.onchange = yen; });
    kok.querySelectorAll('[data-kd]').forEach(function (b) { b.onclick = function () { var k = d.konular[+b.dataset.kd]; k.n = Math.max(0, Math.min(8, (+k.n || 0) + +b.dataset.d)); yen(); }; });
    kok.querySelectorAll('[data-ksil]').forEach(function (b) { b.onclick = function () { d.konular.splice(+b.dataset.ksil, 1); yen(); }; });
    var ke = document.getElementById('yz-konu-ekle'); if (ke) ke.onclick = function () { d.konular.push({ konu: '', n: 1 }); yen(); var l = kok.querySelectorAll('[data-kn]'); if (l.length) l[l.length - 1].focus(); };
    var ko = document.getElementById('yz-konu-oner'); if (ko) ko.onclick = function () {
      ko.disabled = true; ko.innerHTML = '<span class="yz-don"></span> Öneriliyor…';
      api('konular', { veri: { ders: S.ders, sinif: S.sinif, alan: d.alan || (g ? g.ad : '') } }, 60000).then(function (l) {
        var bu = l.filter(function (x) { return (x.donem || 1) === S.donem; }); if (bu.length < 3) bu = l;
        var mevcut = d.konular.map(function (k) { return String(k.konu).toLocaleLowerCase('tr-TR'); });
        bu.forEach(function (x) { if (mevcut.indexOf(x.konu.toLocaleLowerCase('tr-TR')) < 0) d.konular.push({ konu: x.konu, n: 1 }); });
        d.konular = d.konular.slice(0, 20); yen(); bildir(bu.length + ' konu önerildi. Soru sayılarını ayarlayın, gereksizleri silin.', 'ok');
      }).catch(function (e) { ko.disabled = false; ko.textContent = '🤖 Konuları yapay zekâ önersin'; bildir(e.message, 'hata'); });
    };
    document.getElementById('yz-uygula').onclick = function () {
      var t2 = digerTablo(); if (!t2) return;
      S.senaryo = 'ozel'; S.ozel = {}; d.konular.forEach(function (k) { var tt = String(k.konu || '').trim(); if (tt) S.ozel[ozKod(tt)] = (S.ozel[ozKod(tt)] || 0) + (+k.n || 0); });
      var yeni = ozelSlot(t2), ayni = S.slotlar.length === yeni.length && S.slotlar.every(function (x, i) { return x.kod === yeni[i].kod; });
      if (d.kendim) yeni.forEach(function (x) { x.ben = true; });
      if (d.kendim && S.slotlar.length === yeni.length) S.slotlar.forEach(function (x) { x.ben = true; });
      if (!ayni) { S.slotlar = yeni; S.sorular = []; S.id = 0; }
      if (!S.bilgi.baslik || !ayni) S.bilgi.baslik = varsayilanBaslik();
      git(2);
    };
  }
  function v1() {
    if (S.mod === 'diger') return vDiger();
    var siniflar = []; SNR.tablolar.forEach(function (t) { if ((t.donem || 1) === S.donem && siniflar.indexOf(t.sinif) < 0) siniflar.push(t.sinif); }); siniflar.sort(function (a, b) { return a - b; });
    var tl = S.sinif ? tablolar(S.sinif) : [], t = tablo();
    var h = modSec() + '<section class="yz-kart"><div class="yz-kart-bas"><span class="yz-num">1</span><div><h2>Sınıf ve ders</h2><p>' + esc(S.yil) + ' eğitim-öğretim yılı · ' + S.donem + '. dönem</p></div><div class="yz-seg sag">' + [1, 2].map(function (d) { var ok = donemVar(d); return '<button data-k="donem" data-v="' + d + '" class="' + (S.donem === d ? 'on' : '') + '"' + (ok ? '' : ' disabled title="' + d + '. dönem senaryoları MEB yayımlayınca eklenecek"') + '>' + d + '. dönem</button>'; }).join('') + '</div></div>' +
      '<div class="yz-siniflar">' + siniflar.map(function (s) { return '<button data-k="sinif" data-v="' + s + '" class="' + (S.sinif === s ? 'on' : '') + '"><b>' + s + '</b><small>' + (s <= 8 ? 'ortaokul' : 'lise') + '</small></button>'; }).join('') + '</div>' +
      (S.sinif ? '<div class="yz-dersler">' + tl.map(function (x) { return '<button data-k="ders" data-v="' + esc(x.ders) + '" class="' + (S.ders === x.ders ? 'on' : '') + '"><span>' + (DERS_IK[x.ders] || '📘') + '</span><b>' + esc(x.ders) + '</b></button>'; }).join('') + '</div>' : '<div class="yz-ipucu">👆 Önce sınıfı seçin</div>') +
      (t ? '<div class="yz-satir"><span class="yz-et">Yazılı</span><div class="yz-seg">' + [1, 2].map(function (y) { return '<button data-k="yazili" data-v="' + y + '" class="' + (S.yazili === y ? 'on' : '') + '">' + y + '. yazılı</button>'; }).join('') + '</div></div>' : '') + '</section>';
    if (t) {
      var ulke = ulkeGeneli(t), sl = (t.senaryolar || {})[String(S.yazili)] || [], rk = konuRenk(t);
      h += '<section class="yz-kart"><div class="yz-kart-bas"><span class="yz-num">2</span><div><h2>MEB senaryosu</h2><p>' + (ulke ? 'Bu yazılı için okul senaryosu yok' : 'Her senaryo, hangi öğrenme çıktısından kaç soru sorulacağını belirler') + '</p></div></div>' +
        (ulke ? '<div class="yz-bilgi"><span>🇹🇷</span><div><b>' + S.sinif + '. sınıf ' + esc(S.ders) + ' ' + S.yazili + '. yazılı ülke genelinde ortak sınav olarak yapılır.</b> MEB okul senaryosu yayımlamadı. “Kendi dağılımım” ile deneme yazılısı hazırlayabilirsiniz.</div></div>' : '') +
        '<div class="yz-senaryolar">' + sl.map(function (sn) {
          var sl2 = senaryoSlot(t, S.yazili, sn), konu = {}, sira = [];
          sl2.forEach(function (x) { if (!konu[x.konu]) sira.push(x.konu); konu[x.konu] = (konu[x.konu] || 0) + 1; });
          return '<button class="yz-snr' + (S.senaryo === sn ? ' on' : '') + '" data-snr="' + sn + '"><div class="ust"><b>' + sn + '. Senaryo</b><em>' + sl2.length + ' soru</em></div><div class="bar">' + sira.map(function (k) { return '<i style="flex:' + konu[k] + ';background:' + rk[k] + '"></i>'; }).join('') + '</div><ul>' + sira.map(function (k) { return '<li><s style="background:' + rk[k] + '"></s><span>' + esc(k) + '</span><i>' + konu[k] + '</i></li>'; }).join('') + '</ul></button>';
        }).join('') + '<button class="yz-snr ozel' + (S.senaryo === 'ozel' ? ' on' : '') + '" data-snr="ozel"><div class="ust"><b>Kendi dağılımım</b><em>' + (S.senaryo === 'ozel' ? ozelSlot(t).length + ' soru' : 'özel') + '</em></div><p>Öğrenme çıktılarını ve soru sayılarını kendiniz belirleyin.</p></button></div>' +
        (S.senaryo === 'ozel' ? '<div class="yz-ozel">' + t.satirlar.map(function (x) { var k = kodu(x), n = S.ozel[k] || 0; return '<div class="yz-oz' + (n ? ' var' : '') + '"><s style="background:' + rk[x.konu] + '"></s><div><small>' + esc(kodGoster(x.kod)) + (kodGoster(x.kod) ? ' · ' : '') + esc(x.konu) + '</small><p>' + esc(x.cikti) + '</p></div><div class="yz-sayac"><button data-oz="' + esc(k) + '" data-d="-1">−</button><b>' + n + '</b><button data-oz="' + esc(k) + '" data-d="1">+</button></div></div>'; }).join('') + '</div>' : '') +
        (S.senaryo && !(S.senaryo !== 'ozel' && ulke) ? onizlemeTablo(t, rk) : '') + '</section>';
    }
    var n = t && S.senaryo ? (S.senaryo === 'ozel' ? ozelSlot(t).length : senaryoSlot(t, S.yazili, S.senaryo).length) : 0;
    h += '<div class="yz-cubuk"><div class="yz-ozet">' + (n ? '<b>' + n + ' soruluk yazılı</b><span>' + S.sinif + '. sınıf · ' + esc(S.ders) + ' · ' + S.yazili + '. yazılı · ' + (S.senaryo === 'ozel' ? 'kendi dağılımı' : S.senaryo + '. senaryo') + '</span>' : '<b>Seçim yapın</b><span>Sınıf, ders, yazılı ve senaryo</span>') + '</div><button class="yz-btn ana" id="yz-uygula"' + (n ? '' : ' disabled') + '>Seçimi uygula <i>→</i></button></div>';
    cerceve(h); modBagla();
    kok.querySelectorAll('[data-k]').forEach(function (b) {
      b.onclick = function () {
        var k = b.dataset.k, v = b.dataset.v;
        if (k === 'sinif') { S.sinif = +v; S.ders = ''; S.senaryo = 0; }
        else if (k === 'ders') { S.ders = v; S.senaryo = 0; S.ozel = {}; }
        else if (k === 'yazili') { S.yazili = +v; S.senaryo = 0; }
        else if (k === 'donem') { S.donem = +v; if (!tablo()) { S.ders = ''; S.senaryo = 0; } }
        sakla(); v1();
      };
    });
    kok.querySelectorAll('[data-snr]').forEach(function (b) { b.onclick = function () { S.senaryo = b.dataset.snr === 'ozel' ? 'ozel' : +b.dataset.snr; sakla(); v1(); }; });
    kok.querySelectorAll('[data-oz]').forEach(function (b) { b.onclick = function () { var k = b.dataset.oz; S.ozel[k] = Math.max(0, Math.min(6, (S.ozel[k] || 0) + +b.dataset.d)); sakla(); v1(); }; });
    document.getElementById('yz-uygula').onclick = function () {
      var yeni = S.senaryo === 'ozel' ? ozelSlot(t) : senaryoSlot(t, S.yazili, S.senaryo);
      var ayni = S.slotlar.length === yeni.length && S.slotlar.every(function (x, i) { return x.kod === yeni[i].kod; });
      if (!ayni) { S.slotlar = yeni; S.sorular = []; S.id = 0; }
      if (!S.bilgi.baslik || !ayni) S.bilgi.baslik = varsayilanBaslik();
      git(2);
    };
  }
  function onizlemeTablo(t, rk) {
    var sl = S.senaryo === 'ozel' ? ozelSlot(t) : senaryoSlot(t, S.yazili, S.senaryo), g = [];
    sl.forEach(function (x) { var s = g[g.length - 1]; if (s && s.kod === x.kod) s.n++; else g.push({ kod: x.kod, konu: x.konu, cikti: x.cikti, n: 1 }); });
    if (!g.length) return '';
    return '<div class="yz-dagilim"><h3>Soru dağılımı</h3>' + g.map(function (x) { return '<div class="yz-dg"><s style="background:' + rk[x.konu] + '"></s><div><small>' + esc(kodGoster(x.kod)) + (kodGoster(x.kod) ? ' · ' : '') + esc(x.konu) + '</small><p>' + esc(x.cikti) + '</p></div><b>' + x.n + '</b></div>'; }).join('') + '</div>';
  }

  /* ================= 2. bilgiler ================= */
  function secim(liste, ad, deger) { return '<div class="yz-secim">' + liste.map(function (z) { return '<button data-' + ad + '="' + z[0] + '" class="' + (String(deger) === z[0] ? 'on' : '') + '">' + (z[3] ? '<span>' + z[3] + '</span>' : '') + '<b>' + z[1] + '</b><small>' + z[2] + '</small></button>'; }).join('') + '</div>'; }
  function v2() {
    var b = S.bilgi, benSay = S.slotlar.filter(function (x) { return x.ben; }).length, t = tablo(), rk = t ? konuRenk(t) : {};
    var h = '<div class="yz-iki"><section class="yz-kart"><div class="yz-kart-bas"><span class="yz-num">🏫</span><div><h2>Sınav bilgileri</h2><p>Kâğıdın başlığında görünür</p></div></div>' +
      '<label class="yz-alan"><span>Okul adı</span><input data-b="okul" value="' + esc(b.okul) + '" placeholder="Örn. Atatürk Ortaokulu"></label>' +
      '<label class="yz-alan"><span>Sınav başlığı</span><input data-b="baslik" value="' + esc(b.baslik) + '"></label>' +
      '<label class="yz-alan"><span>Öğretmen adı</span><input data-b="ogretmen" value="' + esc(b.ogretmen) + '" placeholder="Adınız soyadınız"></label>' +
      '<div class="yz-izgara2"><label class="yz-alan"><span>Sınav tarihi</span><input type="date" data-b="tarih" value="' + esc(b.tarih) + '"></label><label class="yz-alan"><span>Süre (dakika)</span><input type="number" min="10" max="240" data-b="sure" value="' + esc(b.sure) + '"></label></div></section>' +
      '<section class="yz-kart"><div class="yz-kart-bas"><span class="yz-num">🎚️</span><div><h2>Soru tercihleri</h2><p>Sorular bu tercihlere göre seçilir</p></div></div>' +
      (C.yz && C.yzmod === 'acik' ? '<div class="yz-alan"><span>Soru kaynağı</span>' + secim(KAYNAK, 'kn', S.kaynak) + '</div>' : '') +
      '<div class="yz-alan"><span>Zorluk</span>' + secim(ZOR, 'z', S.zorluk) + '</div><div class="yz-alan"><span>Soru türü</span>' + secim(TUR, 't', S.tur) + '</div><div class="yz-alan"><span>Cevap yazma alanı</span>' + secim(ALAN, 'al', S.alan) + '</div></section></div>' +
      '<section class="yz-kart"><div class="yz-kart-bas"><span class="yz-num">✍️</span><div><h2>Soruları kim yazsın?</h2><p>İşaretlemediğiniz sorular havuzdan otomatik gelir</p></div><span class="yz-rozet' + (benSay ? ' on' : '') + '">' + benSay + ' / ' + S.slotlar.length + ' soru sizde</span></div>' +
      '<div class="yz-hizli"><div><b>Kendim yazacağım soru sayısı</b><small>Sondan başlayarak işaretlenir; aşağıdan tek tek de seçebilirsiniz.</small></div><div class="yz-sayac buyuk"><button data-hz="-1">−</button><b>' + benSay + '</b><button data-hz="1">+</button></div></div>' +
      '<div class="yz-slotlar">' + S.slotlar.map(function (x, i) { return '<label class="yz-slot' + (x.ben ? ' ben' : '') + '"><i style="--r:' + (rk[x.konu] || '#6366f1') + '">' + (i + 1) + '</i><div><small>' + esc(kodGoster(x.kod)) + (kodGoster(x.kod) ? ' · ' : '') + esc(x.konu) + '</small><p>' + esc(x.cikti) + '</p></div><span class="yz-anahtar"><input type="checkbox" data-ben="' + i + '"' + (x.ben ? ' checked' : '') + '><em></em><span>' + (x.ben ? 'Ben yazacağım' : 'Otomatik') + '</span></span></label>'; }).join('') + '</div></section>' +
      '<div class="yz-cubuk"><button class="yz-btn" data-git2="1">← Geri</button><div class="yz-ozet"><b>' + (S.slotlar.length - benSay) + ' soru ' + (S.kaynak === 'yz' ? 'yapay zekâyla yazılacak' : 'otomatik hazırlanacak') + '</b><span>' + (benSay ? benSay + ' soruyu siz yazacaksınız' : 'Tüm sorular otomatik') + '</span></div>' + (S.sorular.length ? '<button class="yz-btn" data-git2="3">Mevcut sorular →</button>' : '') + '<button class="yz-btn ana" id="yz-hazirla">' + (S.sorular.length ? 'Yeniden hazırla' : 'Soruları hazırla') + ' <i>✨</i></button></div>';
    cerceve(h);
    kok.querySelectorAll('[data-b]').forEach(function (e) { e.oninput = function () { S.bilgi[e.dataset.b] = e.dataset.b === 'sure' ? +e.value : e.value; sakla(); }; });
    kok.querySelectorAll('[data-z]').forEach(function (e) { e.onclick = function () { S.zorluk = +e.dataset.z; sakla(); v2(); }; });
    kok.querySelectorAll('[data-t]').forEach(function (e) { e.onclick = function () { S.tur = e.dataset.t; sakla(); v2(); }; });
    kok.querySelectorAll('[data-al]').forEach(function (e) { e.onclick = function () { S.alan = e.dataset.al; sakla(); v2(); }; });
    kok.querySelectorAll('[data-kn]').forEach(function (e) { e.onclick = function () { S.kaynak = e.dataset.kn; sakla(); v2(); }; });
    kok.querySelectorAll('[data-ben]').forEach(function (e) { e.onchange = function () { S.slotlar[+e.dataset.ben].ben = e.checked; sakla(); v2(); }; });
    kok.querySelectorAll('[data-hz]').forEach(function (e) { e.onclick = function () { var n = Math.max(0, Math.min(S.slotlar.length, benSay + +e.dataset.hz)); S.slotlar.forEach(function (x, i) { x.ben = i >= S.slotlar.length - n; }); sakla(); v2(); }; });
    kok.querySelectorAll('[data-git2]').forEach(function (e) { e.onclick = function () { git(+e.dataset.git2); }; });
    document.getElementById('yz-hazirla').onclick = function () {
      if (S.sorular.length && !confirm('Mevcut sorular silinip yeniden hazırlanacak. Devam edilsin mi?')) return;
      hazirla(this);
    };
  }
  function bosSoru(tur) { return { type: tur === 'test' ? 'multiple' : 'classic', paragraph: '', root: '', options: tur === 'test' ? { A: '', B: '', C: '', D: '' } : { A: '' }, correct: '' }; }
  function puanDagit(n) { if (!n) return []; var b = Math.floor(100 / n), k = 100 - b * n, o = []; for (var i = 0; i < n; i++) o.push(b + (i >= n - k ? 1 : 0)); return o; }
  function ortak() { var o = { benim: benim(), sinif: S.sinif, ders: S.ders, donem: S.donem, yazili: S.yazili, zorluk: S.zorluk, tur: S.tur, kaynak: S.kaynak }; if (S.mod === 'diger') { var t = digerTablo(); if (t) o.ozel_tablo = { alan: t.alan, satirlar: t.satirlar.map(function (x) { return { kod: x.kod, konu: x.konu }; }) }; } return o; }

  /* 1. aşama: havuzdaki sorular hemen gelir */
  function hazirla(btn) {
    btn.disabled = true; btn.innerHTML = '<span class="yz-don"></span> Havuz taranıyor…';
    var v = ortak(); v.slotlar = S.slotlar.map(function (x) { return { kod: x.kod, ben: x.ben ? 1 : 0 }; }); v.yz = 0;
    api('hazirla', { veri: v }, 30000).then(function (d) {
      var p = puanDagit(S.slotlar.length);
      S.sorular = S.slotlar.map(function (x, i) {
        var r = d.sorular[i];
        if (x.ben) return { kod: x.kod, id: 0, kaynak: 'ogretmen', soru: bosSoru(S.tur), puan: p[i], duzen: true, gorulen: [] };
        if (!r) return { kod: x.kod, id: 0, kaynak: 'yukleniyor', soru: bosSoru(S.tur), puan: p[i], duzen: false, gorulen: [] };
        return { kod: x.kod, id: r.id, kaynak: r.kaynak, soru: r.soru, puan: p[i], zorluk: r.zorluk, duzen: false, gorulen: [r.id] };
      });
      S.id = 0; git(3); if (d.uyari) bildir(d.uyari, 'uyari');
      yzKuyruk();
    }).catch(function (e) { btn.disabled = false; btn.innerHTML = 'Soruları hazırla <i>✨</i>'; bildir(e.message, 'hata'); });
  }
  /* 2. aşama: eksikler yapay zekâya küçük paketler hâlinde, aynı anda 3 istek */
  function reklamHazirlik(ac) { var r = document.getElementById('osc-rk-hazirlik-kap'); if (!r || r.dataset.kapali) return; r.classList.toggle('on', !!ac); }
  function yukleniyorMu() { return S.sorular.some(function (q) { return q.kaynak === 'yukleniyor'; }); }
  setInterval(function () { try { reklamHazirlik(sekme === 'yeni' && yukleniyorMu()); } catch (e) {} }, 1200);
  function yzKuyruk() {
    var no = ++kuyrukNo, grup = {}, is = [];
    S.sorular.forEach(function (q, i) { if (q.kaynak === 'yukleniyor') (grup[q.kod] = grup[q.kod] || []).push(i); });
    var parca = S.kaynak === 'yz' ? 1 : 2; Object.keys(grup).forEach(function (k) { var l = grup[k]; for (var j = 0; j < l.length; j += parca) is.push({ kod: k, idx: l.slice(j, j + parca) }); });
    if (!is.length) return;
    if (!C.yz) { is.forEach(function (x) { x.idx.forEach(function (i) { S.sorular[i].kaynak = 'bos'; kartYenile(i); }); }); sakla(); return; }
    var hata = '', kalan = is.length;
    var calis = function () {
      var x = is.shift(); if (!x || no !== kuyrukNo) return;
      var v = ortak(); v.kod = x.kod; v.n = x.idx.length; v.haric = kullanilanIdler(); v.anahtarlar = anahtarlar(-1); v.mevcut = ozetler(-1);
      api('yz', { veri: v }, 75000).then(function (l) {
        if (no !== kuyrukNo) return;
        benim(l.map(function (r) { return r.id; }));
        x.idx.forEach(function (i, j) { var q = S.sorular[i], r = l[j]; if (!q || q.kaynak !== 'yukleniyor') return; if (r) { q.id = r.id; q.kaynak = r.kaynak; q.soru = r.soru; q.zorluk = r.zorluk; q.gorulen = [r.id]; } else q.kaynak = 'bos'; kartYenile(i, true); });
        var bos = x.idx.filter(function (i) { var q = S.sorular[i]; return q && q.kaynak === 'bos'; });
        if (bos.length && !x.tekrar) { bos.forEach(function (i) { S.sorular[i].kaynak = 'yukleniyor'; kartYenile(i); }); is.push({ kod: x.kod, idx: bos, tekrar: 1 }); kalan++; }
      }).catch(function (e) {
        if (no !== kuyrukNo) return;
        if (!x.tekrar && !/hakkınız|doldu|giriş|Oturum/i.test(e.message)) { is.push({ kod: x.kod, idx: x.idx, tekrar: 1 }); kalan++; return; }   // bir kez daha dene
        hata = e.message;
        x.idx.forEach(function (i) { var q = S.sorular[i]; if (q && q.kaynak === 'yukleniyor') { q.kaynak = 'bos'; kartYenile(i); } });
      }).then(function () { if (no !== kuyrukNo) return; sakla(); if (--kalan === 0 && hata) bildir(hata + ' Boş kalan soruları “Değiştir” ile yeniden deneyebilir ya da kendiniz yazabilirsiniz.', 'uyari'); calis(); });
    };
    for (var w = 0; w < (S.kaynak === 'yz' ? 6 : 4); w++) calis();
  }
  function benzerlikAnahtari(s) { var d = document.createElement('div'); d.innerHTML = ((s && s.paragraph) || '') + ' ' + ((s && s.root) || ''); var t = (d.textContent || '').toLowerCase(); try { t = t.replace(/[^\p{L}\p{N}]+/gu, ''); } catch (e) { t = t.replace(/[\s\W]+/g, ''); } return t.slice(0, 140); }
  function anahtarlar(haric) { return S.sorular.filter(function (q, i) { return i !== haric && q.soru && (q.soru.root || q.soru.paragraph); }).map(function (q) { return benzerlikAnahtari(q.soru); }); }
  function ozetler(haric) { return S.sorular.filter(function (q, i) { return i !== haric && q.soru && (q.soru.root || q.soru.paragraph) && q.kaynak !== 'yukleniyor'; }).map(function (q) { var d = document.createElement('div'); d.innerHTML = (q.soru.paragraph || '') + ' ' + (q.soru.root || ''); return (d.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 110); }).slice(0, 20); }
  function kullanilanIdler() { var h = []; S.sorular.forEach(function (s) { if (s.id) h.push(s.id); (s.gorulen || []).forEach(function (g) { if (h.indexOf(g) < 0) h.push(g); }); }); return h; }

  /* ================= 3. sorular ================= */
  function soruIc(q) {
    var s = q.soru || {}, op = s.options || {}, h = '';
    if (s.paragraph) h += '<div class="yz-p">' + mat(s.paragraph) + '</div>';
    h += '<div class="yz-r">' + (s.root ? mat(s.root) : '<span class="yz-sil">Soru metni yok</span>') + '</div>';
    if (s.type === 'multiple' || s.type === 'truefalse') {
      var ks = Object.keys(op).filter(function (k) { return op[k]; });
      var cz = s.cozum ? '<div class="yz-cvp"><b>Çözüm</b><span>' + mat(s.cozum) + '</span></div>' : '';
      h += '<div class="yz-sec' + (ks.every(function (k) { return String(op[k]).replace(/<[^>]+>/g, '').length < 28 && op[k].indexOf('<img') < 0; }) ? ' kisa' : '') + '">' + ks.map(function (k) { return '<div class="' + (s.correct === k ? 'd' : '') + '"><b>' + k + '</b><span>' + mat(op[k]) + '</span></div>'; }).join('') + '</div>' + cz;
    } else h += '<div class="yz-cvp"><b>Cevap</b><span>' + (op.A ? mat(op.A) : '<i>cevap anahtarı yok — Düzenle ile ekleyebilirsiniz</i>') + '</span></div>';
    return h;
  }
  function kart(q, i) {
    var x = S.slotlar[i] || {}, ky = KAY[q.kaynak] || KAY.arsiv, son = S.sorular.length - 1, ic;
    if (q.duzen) ic = duzenleyici(q, i);
    else if (q.kaynak === 'yukleniyor') ic = '<div class="yz-soru-ic"><div class="yz-iskelet"><i></i><i></i><i class="k"></i></div><p class="yz-yaziyor"><span class="yz-don"></span> Yapay zekâ bu çıktıya uygun soru yazıyor…</p></div>';
    else ic = '<div class="yz-soru-ic">' + (q.kaynak === 'bos' ? '<div class="yz-bos"><b>Bu çıktı için hazır soru bulunamadı.</b><span><b>Değiştir</b> ile yeniden deneyin ya da <b>Düzenle</b> ile kendiniz yazın.</span></div>' : soruIc(q)) + '</div>' +
      '<div class="yz-soru-alt"><button class="yz-btn kucuk" data-x="degistir" data-i="' + i + '">↻ Değiştir</button><button class="yz-btn kucuk" data-x="duzenle" data-i="' + i + '">✎ Düzenle</button><span class="bosluk"></span><button class="yz-btn ikon" data-x="yukari" data-i="' + i + '" title="Yukarı taşı"' + (i ? '' : ' disabled') + '>↑</button><button class="yz-btn ikon" data-x="asagi" data-i="' + i + '" title="Aşağı taşı"' + (i < son ? '' : ' disabled') + '>↓</button></div>';
    return '<article class="yz-soru k-' + ky[1] + (q.duzen ? ' duzen' : '') + '" data-i="' + i + '"><div class="yz-soru-ust"><i class="no">' + (i + 1) + '</i><div class="ck"><small>' + esc(kodGoster(x.kod)) + (kodGoster(x.kod) ? ' · ' : '') + esc(x.konu || '') + '</small><span title="' + esc(x.cikti) + '">' + esc(x.cikti || '') + '</span></div>' +
      '<span class="yz-k ' + ky[1] + '">' + ky[0] + '</span><label class="yz-puan"><input type="number" min="0" max="100" value="' + (q.puan || 0) + '" data-puan="' + i + '"><span>puan</span></label></div>' + ic + '</article>';
  }
  function toplamPuan() { return S.sorular.reduce(function (a, q) { return a + (+q.puan || 0); }, 0); }
  function toplamYaz() { var t = toplamPuan(), el = kok.querySelector('.yz-toplam'); if (!el) return; el.className = 'yz-toplam' + (t === 100 ? ' tamam' : ' eksik'); el.style.setProperty('--p', Math.min(100, t)); el.querySelector('b').textContent = t; }
  function v3() {
    var yuk = S.sorular.filter(function (q) { return q.kaynak === 'yukleniyor'; }).length;
    var h = '<div class="yz-baslik3"><div><h2>' + esc(S.bilgi.baslik || varsayilanBaslik()) + '</h2><p>' + S.sorular.length + ' soru · ' + (S.senaryo === 'ozel' ? 'kendi dağılımı' : S.senaryo + '. senaryo') + ' · ' + (ZOR[S.zorluk] || ZOR[0])[1].toLowerCase() + ' zorluk</p></div>' + (yuk ? '<span class="yz-rozet on yz-yuk-rozet"><span class="yz-don"></span><span> ' + yuk + ' soru yazılıyor (20-40 sn)</span></span>' : '') + '</div>' +
      '<div class="yz-sorular">' + S.sorular.map(kart).join('') + '</div>' +
      '<div class="yz-cubuk"><span class="yz-toplam" style="--p:0"><i></i><span><b>0</b> / 100 puan</span></span><button class="yz-btn" id="yz-esit">⚖️ Eşit dağıt</button><span class="bosluk"></span><button class="yz-btn" data-git2="2">← Bilgiler</button><button class="yz-btn ana" id="yz-cikti">Çıktıya geç <i>→</i></button></div>';
    cerceve(h); toplamYaz();
    kok.querySelectorAll('.yz-soru').forEach(bagla);
    document.getElementById('yz-esit').onclick = function () { editorleriSakla(); var p = puanDagit(S.sorular.length); S.sorular.forEach(function (q, i) { q.puan = p[i]; }); sakla(); v3(); };
    kok.querySelectorAll('[data-git2]').forEach(function (e) { e.onclick = function () { git(+e.dataset.git2); }; });
    document.getElementById('yz-cikti').onclick = function () {
      var acik = S.sorular.filter(function (q) { return q.duzen; }).length, yk = S.sorular.filter(function (q) { return q.kaynak === 'yukleniyor'; }).length, bos = S.sorular.filter(function (q) { return q.kaynak === 'bos' || !(q.soru.root || q.soru.paragraph); }).length;
      if (acik) return bildir('Düzenlemesi bitmemiş ' + acik + ' soru var. Önce “Tamam”a basın.', 'uyari');
      if (yk) return bildir(yk + ' soru hâlâ yazılıyor, birkaç saniye bekleyin.', 'uyari');
      if (bos && !confirm(bos + ' soru boş. Yine de devam edilsin mi?')) return;
      var t = toplamPuan(); if (t !== 100 && !confirm('Toplam puan ' + t + '. Yine de devam edilsin mi?')) return;
      git(4); kaydet(true);
    };
  }
  function bagla(el) {
    el.querySelectorAll('[data-puan]').forEach(function (e) { e.oninput = function () { S.sorular[+e.dataset.puan].puan = Math.max(0, +e.value || 0); sakla(); toplamYaz(); }; });
    el.querySelectorAll('[data-x]').forEach(function (b) { b.onclick = function () { islem(b.dataset.x, +b.dataset.i, b); }; });
    var ed = el.querySelector('.yz-ed'); if (ed) edBagla(ed);
  }
  function kartYenile(i, yeni) {
    if (S.adim !== 3 || sekme !== 'yeni') return;
    var el = kok.querySelector('.yz-soru[data-i="' + i + '"]'); if (!el || el.querySelector('.yz-ed')) return;
    var d = document.createElement('div'); d.innerHTML = kart(S.sorular[i], i); var n = d.firstChild; el.parentNode.replaceChild(n, el); bagla(n); if (yeni) n.classList.add('yeni');
    var yk = S.sorular.filter(function (q) { return q.kaynak === 'yukleniyor'; }).length, r = kok.querySelector('.yz-yuk-rozet');
    if (r) { if (yk) r.lastChild.textContent = ' ' + yk + ' soru yazılıyor'; else r.remove(); }
  }
  function editorleriSakla() { kok.querySelectorAll('.yz-ed').forEach(function (el) { var q = S.sorular[+el.dataset.i]; if (q) q.soru = edOku(el, q); }); sakla(); }
  function islem(x, i, b) {
    var q = S.sorular[i];
    if (x === 'yukari' || x === 'asagi') { editorleriSakla(); var j = x === 'yukari' ? i - 1 : i + 1; var t1 = S.sorular[i]; S.sorular[i] = S.sorular[j]; S.sorular[j] = t1; var t2 = S.slotlar[i]; S.slotlar[i] = S.slotlar[j]; S.slotlar[j] = t2; sakla(); v3(); return; }
    if (x === 'duzenle') { if (q.kaynak === 'bos') { q.kaynak = 'ogretmen'; q.soru = bosSoru(S.tur); } q.duzen = true; sakla(); kartYenileZorla(i); return; }
    if (x === 'degistir') {
      b.disabled = true; b.innerHTML = '<span class="yz-don"></span> Aranıyor…';
      var v = ortak(); v.kod = q.kod; v.haric = kullanilanIdler(); v.anahtarlar = anahtarlar(i); v.mevcut = ozetler(i);
      api('degistir', { veri: v }, 70000).then(function (r) {
        if (r.kaynak === 'yz') benim(r.id);
        q.id = r.id; q.kaynak = r.kaynak; q.soru = r.soru; q.zorluk = r.zorluk; q.duzen = false; (q.gorulen = q.gorulen || []).push(r.id); sakla(); kartYenile(i, true);
      }).catch(function (e) { b.disabled = false; b.innerHTML = '↻ Değiştir'; bildir(e.message, 'hata'); });
    }
  }
  function kartYenileZorla(i) { var el = kok.querySelector('.yz-soru[data-i="' + i + '"]'); if (!el) return v3(); var d = document.createElement('div'); d.innerHTML = kart(S.sorular[i], i); var n = d.firstChild; el.parentNode.replaceChild(n, el); bagla(n); var ce = n.querySelector('[data-f=root]'); if (ce) ce.focus(); }
  function duzenleyici(q, i) {
    var s = q.soru, tur = s.type || 'classic', op = s.options || {};
    var harfler = tur === 'truefalse' ? ['A', 'B'] : ['A', 'B', 'C', 'D', 'E'];
    return '<div class="yz-ed" data-i="' + i + '"><div class="yz-ed-tur">' + [['classic', 'Açık uçlu'], ['multiple', 'Çoktan seçmeli'], ['gap', 'Boşluk doldurma'], ['truefalse', 'Doğru / Yanlış']].map(function (t) { return '<button data-tur="' + t[0] + '" class="' + (tur === t[0] ? 'on' : '') + '">' + t[1] + '</button>'; }).join('') + '</div>' +
      '<div class="yz-mat" title="Matematik: tıklayın, imlecin olduğu yere eklensin">' + MATBTN.map(function (b, j) { return '<button type="button" data-mb="' + j + '" title="' + esc(b[2] || b[0]) + '">' + b[0] + '</button>'; }).join('') + '<span class="ipucu">$…$ arasına yazılan ifade formül olarak görünür</span></div>' +
      '<div class="yz-alan"><span>Öncül / metin <small>(isteğe bağlı)</small></span><div class="yz-ce" contenteditable="true" data-f="paragraph" data-ph="Okuma metni, tablo açıklaması vb.">' + (s.paragraph || '') + '</div></div>' +
      '<div class="yz-alan"><span>Soru</span><div class="yz-ce buyuk" contenteditable="true" data-f="root" data-ph="Soru kökünü yazın…">' + (s.root || '') + '</div></div>' +
      (tur === 'multiple' || tur === 'truefalse' ? '<div class="yz-ed-sik">' + harfler.map(function (h) { var v = tur === 'truefalse' && !op[h] ? (h === 'A' ? 'Doğru' : 'Yanlış') : (op[h] || ''); return '<label class="yz-sk' + (s.correct === h ? ' d' : '') + '"><input type="radio" name="yz-d' + i + '" value="' + h + '"' + (s.correct === h ? ' checked' : '') + ' title="Doğru cevap"><b>' + h + '</b><div class="yz-ce" contenteditable="true" data-sik="' + h + '" data-ph="' + (h === 'E' ? 'E şıkkı (isteğe bağlı)' : h + ' şıkkı') + '">' + v + '</div></label>'; }).join('') + '<p class="yz-not">Doğru şıkkı soldaki harfe tıklayarak işaretleyin.</p></div>'
        : '<div class="yz-alan"><span>Cevap / puanlama anahtarı</span><div class="yz-ce" contenteditable="true" data-f="cevap" data-ph="Beklenen cevap (cevap anahtarında görünür)">' + (op.A || '') + '</div></div>') +
      '<div class="yz-ed-on"' + (matVar(s.root + s.paragraph + JSON.stringify(op)) ? '' : ' hidden') + '><small>Önizleme</small><div></div></div>' +
      '<div class="yz-ed-alt"><button class="yz-btn" data-ed="iptal">Vazgeç</button><button class="yz-btn ana" data-ed="tamam">Tamam ✓</button></div></div>';
  }
  var MATBTN = [['<i>a</i>⁄<i>b</i>', '$\\frac{a}{b}$', 'Kesir'], ['√', '$\\sqrt{x}$', 'Karekök'], ['ⁿ√', '$\\sqrt[3]{x}$', 'n. dereceden kök'], ['x²', '$x^{2}$', 'Üs'], ['x₁', '$x_{1}$', 'Alt indis'], ['1,6̄', '$1,\\overline{6}$', 'Devirli ondalık'], ['×', '$\\times$'], ['÷', '$\\div$'], ['π', '$\\pi$'], ['≤', '$\\le$'], ['≥', '$\\ge$'], ['≠', '$\\ne$'], ['°', '$^{\\circ}$', 'Derece'], ['∠', '$\\angle$', 'Açı'], ['ℝ', '$\\mathbb{R}$', 'Reel sayılar'], ['∈', '$\\in$']];
  var sonCe = null;
  function temizHtml(h) { var d = document.createElement('div'); d.innerHTML = h; d.querySelectorAll('script,style,iframe,object,embed,link,meta').forEach(function (x) { x.remove(); }); d.querySelectorAll('*').forEach(function (x) { [].slice.call(x.attributes).forEach(function (a) { if (/^on/i.test(a.name) || (/^(href|src)$/i.test(a.name) && /^\s*javascript:/i.test(a.value))) x.removeAttribute(a.name); }); }); return d.innerHTML.replace(/^(<br>|\s|&nbsp;)+|(<br>|\s|&nbsp;)+$/g, ''); }
  function edOku(el, q) {
    var on = el.querySelector('[data-tur].on'), tur = on ? on.dataset.tur : q.soru.type;
    var o = { type: tur, paragraph: temizHtml(el.querySelector('[data-f=paragraph]').innerHTML), root: temizHtml(el.querySelector('[data-f=root]').innerHTML), options: {}, correct: '' };
    if (tur === 'multiple' || tur === 'truefalse') { el.querySelectorAll('[data-sik]').forEach(function (c) { o.options[c.dataset.sik] = temizHtml(c.innerHTML); }); var r = el.querySelector('input[type=radio]:checked'); o.correct = r ? r.value : ''; }
    else { var c = el.querySelector('[data-f=cevap]'); o.options = { A: c ? temizHtml(c.innerHTML) : '' }; }
    return o;
  }
  function edBagla(el) {
    var i = +el.dataset.i, q = S.sorular[i], yedek = JSON.stringify(q.soru);
    el.querySelectorAll('[data-tur]').forEach(function (b) {
      b.onclick = function (e) {
        e.preventDefault(); var o = edOku(el, q), eski = o.type; o.type = b.dataset.tur;
        if (o.type === 'multiple' && eski !== 'multiple') o.options = { A: '', B: '', C: '', D: '' };
        if (o.type === 'truefalse') o.options = { A: 'Doğru', B: 'Yanlış' };
        if ((o.type === 'classic' || o.type === 'gap') && eski !== 'classic' && eski !== 'gap') o.options = { A: '' };
        q.soru = o; sakla(); kartYenileZorla(i);
      };
    });
    el.querySelectorAll('input[type=radio]').forEach(function (r) { r.onchange = function () { el.querySelectorAll('.yz-sk').forEach(function (l) { l.classList.toggle('d', l.contains(r)); }); }; });
    var onz = el.querySelector('.yz-ed-on'), onIc = onz ? onz.lastChild : null;
    var onGuncelle = function () { if (!onz) return; var o = edOku(el, q), h = (o.paragraph ? '<div class="yz-p">' + mat(o.paragraph) + '</div>' : '') + '<div class="yz-r">' + mat(o.root) + '</div>' + ((o.type === 'multiple' || o.type === 'truefalse') ? Object.keys(o.options).filter(function (k) { return o.options[k]; }).map(function (k) { return '<div><b>' + k + ')</b> ' + mat(o.options[k]) + '</div>'; }).join('') : (o.options.A ? '<div class="yz-cvp"><b>Cevap</b><span>' + mat(o.options.A) + '</span></div>' : '')); onIc.innerHTML = h; onz.hidden = !matVar(JSON.stringify(o)); };
    el.querySelectorAll('.yz-ce').forEach(function (c) { c.addEventListener('focus', function () { sonCe = c; }); c.addEventListener('input', onGuncelle); });
    el.querySelectorAll('[data-mb]').forEach(function (b) {
      b.addEventListener('mousedown', function (e) { e.preventDefault(); });
      b.onclick = function (e) { e.preventDefault(); var c = sonCe && el.contains(sonCe) ? sonCe : el.querySelector('[data-f=root]'); c.focus(); document.execCommand('insertText', false, MATBTN[+b.dataset.mb][1]); onGuncelle(); };
    });
    if (onz && !onz.hidden) onGuncelle();
    el.querySelectorAll('.yz-ce').forEach(function (c) { c.addEventListener('paste', function (e) { var cd = e.clipboardData || window.clipboardData, t = cd.getData('text/plain'); if (t && !([].slice.call(cd.types || []).indexOf('Files') >= 0)) { e.preventDefault(); document.execCommand('insertText', false, t); } }); });
    el.querySelector('[data-ed=tamam]').onclick = function () {
      var o = edOku(el, q);
      if (!o.root && !o.paragraph) return bildir('Soru metni boş olamaz.', 'uyari');
      if (o.type === 'multiple' && !o.correct) return bildir('Doğru şıkkı işaretleyin.', 'uyari');
      if (JSON.stringify(o) !== yedek) { q.kaynak = 'ogretmen'; q.id = 0; }
      q.soru = o; q.duzen = false; sakla(); kartYenileZorla(i);
    };
    el.querySelector('[data-ed=iptal]').onclick = function () { q.soru = JSON.parse(yedek); q.duzen = false; if (q.kaynak === 'ogretmen' && !(q.soru.root || q.soru.paragraph)) q.kaynak = 'bos'; sakla(); kartYenileZorla(i); };
  }

  /* ================= 4. çıktı ================= */
  function satirSayisi(q) {
    var s = q.soru || {}, p = +q.puan || 0;
    if (s.type === 'multiple' || s.type === 'truefalse') return 0;
    if (s.type === 'gap') return /(\.{4,}|…{2,}|_{3,})/.test(s.root || '') ? 0 : 1;
    if (S.alan === 'az') return Math.max(2, Math.min(5, Math.round(p / 5)));
    if (S.alan === 'genis') return Math.max(6, Math.min(16, Math.round(p / 1.6)));
    return Math.max(3, Math.min(10, Math.round(p / 2.6)));
  }
  /** cevap için bırakılacak boşluk (piksel); çizgili seçimde 0 */
  function bosluk(q) {
    var s = q.soru || {}, p = +q.puan || 0;
    if (S.alan !== 'bos' || s.type === 'multiple' || s.type === 'truefalse' || s.type === 'gap') return 0;
    var md = (String(s.root || '').match(/<br>\s*[a-hç]\)/g) || []).length;
    return Math.max(110, Math.min(380, 60 + p * 9 + md * 40));
  }
  function kagit(anahtar, word) {
    var b = S.bilgi, M = word ? function (x) { return x; } : mat;
    var soruHtml = S.sorular.map(function (q, i) {
      var s = q.soru || {}, op = s.options || {}, h = '', n = S.alan === 'bos' ? 0 : satirSayisi(q), bs = bosluk(q);
      h += '<div class="s"><table class="sb" width="100%" cellpadding="0" cellspacing="0"><tr><td class="sn" valign="top">' + (i + 1) + '.</td><td class="sm" valign="top">' + (s.paragraph ? '<div class="p">' + M(s.paragraph) + '</div>' : '') + '<div class="r">' + M(s.root || '') + '</div>';
      if (s.type === 'multiple' || s.type === 'truefalse') {
        var ks = Object.keys(op).filter(function (k) { return op[k]; }), kisa = ks.every(function (k) { return String(op[k]).replace(/<[^>]+>/g, '').replace(/\$[^$]*\$/g, 'xxxx').length < 26 && op[k].indexOf('<img') < 0; });
        h += kisa ? '<table class="sk" width="100%"><tr>' + ks.map(function (k) { return '<td><b>' + k + ')</b> ' + M(op[k]) + '</td>'; }).join('') + '</tr></table>' : '<div class="sl">' + ks.map(function (k) { return '<div><b>' + k + ')</b> ' + M(op[k]) + '</div>'; }).join('') + '</div>';
      }
      if (n) { h += '<div class="cv">'; for (var j = 0; j < n; j++) h += '<div class="cs">&nbsp;</div>'; h += '</div>'; }
      if (bs) h += '<div style="height:' + bs + 'px"></div>';
      h += '</td><td class="sp" valign="top">(' + (q.puan || 0) + ' P)</td></tr></table></div>';
      return h;
    }).join('');
    var key = '';
    if (anahtar) key = (word ? '<br clear="all" style="page-break-before:always">' : '<div class="kesme"></div>') + '<h2 class="ka">CEVAP ANAHTARI</h2><p class="kb">' + esc(b.baslik) + '</p><table class="ak" width="100%"><thead><tr><th>No</th><th>Öğrenme çıktısı</th><th>Cevap</th><th>Puan</th></tr></thead><tbody>' + S.sorular.map(function (q, i) {
      var s = q.soru || {}, op = s.options || {}, x = S.slotlar[i] || {};
      var c = (s.type === 'multiple' || s.type === 'truefalse') ? '<b>' + esc(s.correct || '?') + '</b>' + (s.correct && op[s.correct] ? ') ' + M(op[s.correct]) : '') + (s.cozum ? '<div class="cz">' + M(s.cozum) + '</div>' : '') : (op.A ? M(op.A) : '—');
      return '<tr><td class="c">' + (i + 1) + '</td><td class="kk">' + esc(kodGoster(x.kod)) + '<br><small>' + esc(x.konu || '') + '</small></td><td>' + c + '</td><td class="c">' + (q.puan || 0) + '</td></tr>';
    }).join('') + '</tbody></table>';
    var css = '@page{size:A4;margin:1.3cm 1.4cm}' + (word ? '@page Section1{size:21cm 29.7cm;margin:1.3cm 1.4cm}div.Section1{page:Section1}' : '') +
      'body{font-family:Calibri,"Segoe UI",Arial,sans-serif;font-size:11.5pt;color:#000;margin:0}' +
      '.bas{text-align:center;border:1.5px solid #000;padding:6px 8px 4px}.bas .tc{font-weight:700;font-size:11pt}.bas .ok{font-weight:700;font-size:12pt;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.bas .bs{font-weight:700;font-size:10.5pt;margin-top:2px}' +
      'table{border-collapse:collapse}.ogr{width:100%;margin:6px 0 14px}.ogr td{border:1.5px solid #000;padding:5px 7px;font-size:10.5pt;vertical-align:top}.ogr .pk{width:70px;text-align:center;font-weight:700}' +
      '.s{margin:0 0 12px;page-break-inside:avoid}.sb td{vertical-align:top;padding:0}.sn{width:26px;font-weight:700}.sp{width:52px;text-align:right;font-weight:700;font-size:10pt;white-space:nowrap}.p{border:1px solid #999;padding:5px 8px;margin:0 0 5px;font-size:11pt}.r{font-weight:600}.sk{margin-top:5px}.sk td{padding:2px 6px 2px 0;vertical-align:top}.sl div{margin:3px 0}' +
      '.yz-tb{border-collapse:collapse;margin:5px 0;width:100%}.yz-tb th,.yz-tb td{border:1px solid #777;padding:3px 7px;text-align:left;font-size:10.5pt;vertical-align:top}.yz-tb th{background:#f0f0f0}' +
      '.cv{margin:6px 0 0}.cs{height:26px;line-height:26px;border-bottom:1px dotted #8a8a8a;font-size:10pt}' +
      'img{max-width:100%;height:auto}.alt{margin-top:18px;text-align:right;font-size:10.5pt}.alt b{display:block}.imza{font-size:8pt;color:#888;text-align:center;margin-top:16px}' +
      '.ka{text-align:center;font-size:14pt;margin:0 0 2px}.kb{text-align:center;font-size:9.5pt;margin:0 0 10px}.ak th,.ak td{border:1px solid #000;padding:5px 7px;font-size:10.5pt;vertical-align:top;text-align:left}.ak .c{text-align:center;width:40px}.ak .kk{width:150px;font-size:9.5pt}.ak .cz{margin-top:3px;font-size:10pt}.kesme{page-break-before:always}';
    var govde = '<div class="bas"><div class="tc">T.C.</div><div class="ok">' + esc(trUp(b.okul || '……………………… OKULU')) + '</div><div class="bs">' + esc(b.baslik) + '</div></div>' +
      '<table class="ogr"><tr><td>Adı Soyadı: </td><td style="width:22%">Sınıfı / No: </td><td style="width:16%">Tarih: ' + esc(tarihTr(b.tarih)) + '</td><td style="width:13%">Süre: ' + esc(b.sure) + ' dk</td><td class="pk">PUAN<br><br></td></tr></table>' +
      soruHtml + '<div class="alt">Başarılar dilerim.<b>' + esc(b.ogretmen) + '</b>' + esc(S.ders) + ' Öğretmeni</div><div class="imza">' + esc(C.site || 'soruindir.net') + '</div>' + key;
    return '<!DOCTYPE html><html' + (word ? ' xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"' : ' lang="tr"') + '><head><meta charset="utf-8"><title>' + esc(b.baslik) + '</title>' + (word ? '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml><![endif]-->' : '') + (!word && window.katex ? '<link rel="stylesheet" href="' + KATEX + 'katex.min.css">' : '') + '<style>' + css + '.katex{font-size:1.08em}.yz-blok{display:inline-block;margin:4px 0 2px}.yz-blok .katex{font-size:1.25em}</style></head><body>' + (word ? '<div class="Section1">' + govde + '</div>' : govde) + '</body></html>';
  }
  function dosyaAdi(ek) { return (S.sinif + '-sinif-' + S.ders + '-' + S.donem + '-donem-' + S.yazili + '-yazili' + ek).toLocaleLowerCase('tr').replace(/ç/g, 'c').replace(/ğ/g, 'g').replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ş/g, 's').replace(/ü/g, 'u').replace(/[^a-z0-9]+/g, '-').replace(/-+$/, ''); }
  function indirBlob(blob, ad) { var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = ad; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 2000); }
  function eskiWord(anahtar) { indirBlob(new Blob(['\ufeff', kagit(anahtar, true)], { type: 'application/msword' }), dosyaAdi(anahtar ? '-cevap-anahtarli' : '') + '.doc'); }
  function jszip() {
    if (window.JSZip) return Promise.resolve();
    return new Promise(function (ok, no) { var sc = document.createElement('script'); sc.src = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js'; sc.onload = ok; sc.onerror = no; document.head.appendChild(sc); });
  }
  /** Gerçek .docx: formüller Word denklemi, resimler gömülü */
  function wordIndir(anahtar, b) {
    var eski = b ? b.innerHTML : '';
    if (b) { b.disabled = true; var bb = b.querySelector('b'); if (bb) bb.textContent = 'Hazırlanıyor…'; }
    var bitti = function () { if (b) { b.disabled = false; b.innerHTML = eski; } };
    var m = { okul: S.bilgi.okul, baslik: S.bilgi.baslik || varsayilanBaslik(), ogretmen: S.bilgi.ogretmen, ders: S.ders, tarih: tarihTr(S.bilgi.tarih), sure: S.bilgi.sure, site: C.site || 'soruindir.net', anahtar: !!anahtar,
      sorular: S.sorular.map(function (q, i) { var s = q.soru || {}, x = S.slotlar[i] || {}; return { paragraph: s.paragraph || '', root: s.root || '', type: s.type, options: s.options || {}, correct: s.correct || '', cozum: s.cozum || '', puan: q.puan, satir: S.alan === 'bos' ? 0 : satirSayisi(q), bosluk: bosluk(q), kod: kodGoster(x.kod), konu: x.konu || '' }; }) };
    jszip().then(function () { if (!window.OSC_DOCX) throw new Error('docx'); return window.OSC_DOCX.olustur(m); })
      .then(function (blob) { indirBlob(blob, dosyaAdi(anahtar ? '-cevap-anahtarli' : '') + '.docx'); bitti(); })
      .catch(function (e) { if (window.console) console.warn('docx', e); eskiWord(anahtar); bitti(); });
  }
  function yazdir(anahtar) {
    var f = document.createElement('iframe'); f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0'; document.body.appendChild(f);
    var d = f.contentWindow.document; d.open(); d.write(kagit(anahtar, false)); d.close();
    var bas0 = function () { try { f.contentWindow.focus(); f.contentWindow.print(); } catch (e) {} setTimeout(function () { f.remove(); }, 4000); };
    var bas = function () { try { var fs = f.contentWindow.document.fonts; if (fs && fs.ready) { var t = setTimeout(bas0, 2500); fs.ready.then(function () { clearTimeout(t); setTimeout(bas0, 100); }); return; } } catch (e) {} bas0(); };
    var imgs = d.images, kalan = imgs.length; if (!kalan) return setTimeout(bas, 250);
    [].forEach.call(imgs, function (im) { if (im.complete) { if (!--kalan) setTimeout(bas, 200); } else im.onload = im.onerror = function () { if (!--kalan) setTimeout(bas, 200); }; });
  }
  function v4() {
    var h = '<div class="yz-cikti"><div class="yz-onizle"><div class="yz-onizle-bas"><b>📄 Önizleme</b><span class="yz-et">Cevap alanı</span><div class="yz-seg kucuk">' + ALAN.map(function (a) { return '<button data-al="' + a[0] + '" class="' + (S.alan === a[0] ? 'on' : '') + '">' + a[1] + '</button>'; }).join('') + '</div><label class="yz-anahtar"><input type="checkbox" id="yz-ak"' + (S.anahtar ? ' checked' : '') + '><em></em><span>Cevap anahtarı</span></label></div><div class="yz-kagit"><iframe id="yz-if" title="Yazılı önizleme"></iframe></div></div>' +
      '<aside class="yz-yan"><div class="yz-kart koyu"><h2>İndir ve yazdır</h2><p>Öğrencilere dağıtacağınız kâğıt için <b>cevap anahtarsız</b> sürümü kullanın.</p>' +
      '<div class="yz-indir"><button class="yz-ib ana" data-o="word0"><span>W</span><div><b>Word</b><small>Cevap anahtarsız</small></div></button><button class="yz-ib" data-o="word1"><span>W</span><div><b>Word</b><small>Cevap anahtarlı</small></div></button><button class="yz-ib pdf" data-o="pdf0"><span>PDF</span><div><b>Yazdır / PDF</b><small>Cevap anahtarsız</small></div></button><button class="yz-ib pdf" data-o="pdf1"><span>PDF</span><div><b>Yazdır / PDF</b><small>Cevap anahtarlı</small></div></button></div>' +
      '<p class="kucuk">PDF için yazdırma penceresinde hedef olarak <b>“PDF olarak kaydet”</b>i seçin.</p></div>' +
      '<div class="yz-kart"><h2>Kaydet</h2><p class="yz-not" id="yz-kd">' + (S.id ? '✓ Yazılılarım’a kaydedildi.' : 'Yazılılarım’a kaydedilir; sonra açıp düzenleyebilirsiniz.') + '</p>' + (C.giris ? '<p class="yz-not kucuk">Şu an bu tarayıcıya kaydediliyor. <a href="' + esc(C.giris) + '"><b>Ücretsiz öğretmen girişi</b></a> yaparsanız yazılılarınız hesabınıza kaydedilir, her cihazdan açılır.</p>' : '') + '<button class="yz-btn tam" id="yz-kaydet">💾 Kaydet</button><button class="yz-btn tam hafif" data-git2="3">✎ Sorulara dön</button><button class="yz-btn tam hafif" id="yz-yeni">＋ Yeni yazılı</button></div></aside></div>';
    cerceve(h);
    var ifr = document.getElementById('yz-if'), yenile = function () { ifr.srcdoc = kagit(S.anahtar, false).replace('</style>', 'body{padding:34px 38px;background:#fff}.kesme{border-top:2px dashed #94a3b8;margin:30px 0 20px}</style>'); };
    ifr.onload = function () { try { ifr.style.height = Math.max(700, ifr.contentWindow.document.documentElement.scrollHeight + 20) + 'px'; } catch (e) {} };
    yenile();
    document.getElementById('yz-ak').onchange = function () { S.anahtar = this.checked; sakla(); yenile(); };
    kok.querySelectorAll('[data-al]').forEach(function (e) { e.onclick = function () { S.alan = e.dataset.al; sakla(); kok.querySelectorAll('[data-al]').forEach(function (x) { x.classList.toggle('on', x === e); }); yenile(); }; });
    kok.querySelectorAll('[data-o]').forEach(function (b) { b.onclick = function () { var o = b.dataset.o; if (o.indexOf('word') === 0) wordIndir(o === 'word1', b); else yazdir(o === 'pdf1'); }; });
    kok.querySelectorAll('[data-git2]').forEach(function (e) { e.onclick = function () { git(+e.dataset.git2); }; });
    document.getElementById('yz-kaydet').onclick = function () { kaydet(false); };
    document.getElementById('yz-yeni').onclick = function () { if (!confirm('Yeni yazılıya başlansın mı? (Bu yazılı Yazılılarım’da kayıtlı kalır.)')) return; yeniBasla(); };
  }
  function yeniBasla() { var ok = S.bilgi.okul, og = S.bilgi.ogretmen; kuyrukNo++; S = yeniDurum(); S.bilgi.okul = ok; S.bilgi.ogretmen = og; sakla(); sekme = 'yeni'; ciz(); }
  function kaydet(sessiz) {
    var veri = { sinif: S.sinif, ders: S.ders, donem: S.donem, yazili: S.yazili, yil: S.yil, senaryo: S.senaryo, ozel: S.ozel, bilgi: S.bilgi, zorluk: S.zorluk, tur: S.tur, alan: S.alan, slotlar: S.slotlar, anahtar: S.anahtar, sorular: S.sorular.map(function (q) { return { kod: q.kod, id: q.id, kaynak: q.kaynak, soru: q.soru, puan: q.puan }; }) };
    return api('kaydet', { id: S.id || 0, veri: veri }).then(function (r) { S.id = r.id; sakla(); var e = document.getElementById('yz-kd'); if (e) e.textContent = '✓ Yazılılarım’a kaydedildi.'; if (!sessiz) bildir('Yazılı kaydedildi.', 'ok'); }).catch(function (e) { if (!sessiz) bildir(e.message, 'hata'); });
  }

  /* ================= yazılılarım ================= */
  function vListe() {
    cerceve('<section class="yz-kart"><div class="yz-kart-bas"><span class="yz-num">📁</span><div><h2>Yazılılarım</h2><p>' + (C.yerel ? 'Bu tarayıcıda kaydettiğiniz yazılılar' + (C.giris ? ' · <a href="' + esc(C.giris) + '"><b>giriş yaparak hesabınıza kaydedin</b></a>' : '') : 'Kaydettiğiniz yazılılar') + '</p></div><button class="yz-btn ana sag" id="yz-yeni2">＋ Yeni yazılı</button></div><div id="yz-l" class="yz-liste"><div class="yz-yuk"><span class="yz-don b"></span></div></div></section>');
    document.getElementById('yz-yeni2').onclick = yeniBasla;
    api('liste').then(function (l) {
      var el = document.getElementById('yz-l');
      if (!l.length) { el.innerHTML = '<div class="yz-bos-liste"><div>📝</div><b>Henüz kayıtlı yazılınız yok</b><p>Hazırladığınız yazılılar burada listelenir.</p></div>'; return; }
      el.innerHTML = l.map(function (x) { return '<div class="yz-li"><div class="ik"><b>' + esc(x.sinif) + '</b><small>sınıf</small></div><div class="bil"><b>' + esc(x.ders) + ' · ' + esc(x.yazili) + '. yazılı</b><small>' + (x.senaryo === 'ozel' ? 'kendi dağılımı' : esc(x.senaryo) + '. senaryo') + ' · ' + x.n + ' soru · ' + esc(x.tarih) + '</small></div><button class="yz-btn kucuk ana" data-ac="' + x.id + '">Aç</button><button class="yz-btn kucuk" data-sil="' + x.id + '" title="Sil">🗑</button></div>'; }).join('');
      el.querySelectorAll('[data-ac]').forEach(function (b) { b.onclick = function () { b.disabled = true; api('getir', { id: b.dataset.ac }).then(function (r) { var v = r.veri, n = yeniDurum(); Object.keys(n).forEach(function (k) { if (v[k] !== undefined) n[k] = v[k]; }); n.id = r.id; n.sorular = (v.sorular || []).map(function (q) { q.duzen = false; q.gorulen = q.id ? [q.id] : []; if (q.kaynak === 'yukleniyor') q.kaynak = 'bos'; return q; }); n.adim = 4; kuyrukNo++; S = n; sakla(); sekme = 'yeni'; ciz(); }).catch(function (e) { b.disabled = false; bildir(e.message, 'hata'); }); }; });
      el.querySelectorAll('[data-sil]').forEach(function (b) { b.onclick = function () { if (!confirm('Bu yazılı silinsin mi?')) return; api('sil', { id: b.dataset.sil }).then(function () { if (S.id === +b.dataset.sil) S.id = 0; vListe(); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
    }).catch(function (e) { document.getElementById('yz-l').innerHTML = '<div class="yz-bos">' + esc(e.message) + '</div>'; });
  }

  if (S.adim > enFazlaAdim()) S.adim = enFazlaAdim();
  katexYukle();
  ciz();
  }
  window.OSC_YZ_BASLAT = BASLAT;
  BASLAT();
})();
<?php }

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
        if (!$this->ogretmen_mi()) wp_send_json_error('Yazılı Hazırla’yı kullanmak için öğretmen hesabıyla giriş yapın.', 403);
        if (!wp_verify_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '', 'osc_yz')) wp_send_json_error('Oturum süresi doldu. Sayfayı yenileyin.', 403);
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
        $this->kontrol_acik(); $d = $this->girdi();
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
        if (!$this->ogretmen_mi()) {
            $g = OSC_Sinav::sayfa_url('osc_sp_uye_sayfa', array('rol' => 'ogretmen', 'git' => self::url()));
            $ogr = is_user_logged_in();
            return '<div class="osc-yz-kapi" style="max-width:620px;margin:30px auto;padding:34px 28px;border-radius:24px;text-align:center;background:#fff;border:1px solid #e6e9f2;box-shadow:0 24px 50px -36px rgba(30,27,75,.5);font-family:\'Plus Jakarta Sans\',system-ui,sans-serif"><div style="width:64px;height:64px;margin:0 auto 14px;border-radius:18px;background:linear-gradient(135deg,#5b5bf6,#c026d3);display:flex;align-items:center;justify-content:center;font-size:30px">📄</div><h2 style="margin:0 0 8px;font-size:24px;color:#0b1220">Yazılı Hazırla öğretmenlere özeldir</h2><p style="margin:0 0 20px;color:#475569;line-height:1.6">' . ($ogr ? 'Bu araç yalnızca öğretmen hesaplarıyla kullanılabilir.' : 'MEB senaryolarına uygun, cevap anahtarlı yazılınızı hazırlamak için ücretsiz öğretmen hesabınızla giriş yapın.') . '</p>' . ($ogr ? '' : '<a href="' . esc_url($g) . '" style="display:inline-block;background:linear-gradient(135deg,#5b5bf6,#7c3aed);color:#fff;text-decoration:none;font-weight:800;padding:13px 22px;border-radius:14px">Giriş yap / Ücretsiz üye ol →</a>') . '</div>';
        }
        $cfg = self::cfg(false); $cfg['kota'] = self::kota_durum(); $cfg['rk_hazirlik'] = self::reklam('hazirlik') ? 1 : 0;
        ob_start(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<?php echo self::reklam('ust'); ?>
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
<p class="alt">Ücretsizdir. Hazırladığınız yazılılar hesabınıza kaydedilir ve her cihazdan açabilirsiniz.</p>
</section>
<?php echo self::reklam('alt'); ?>
<style>.osc-rk{max-width:1180px;margin:18px auto;text-align:center;min-height:50px;overflow:hidden}.osc-rk-et{display:block;font:600 10px/1 system-ui,sans-serif;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8;margin-bottom:4px}.osc-rk-kayan{position:fixed;left:50%;bottom:0;z-index:99970;width:min(760px,100vw);transform:translate(-50%,110%);visibility:hidden;transition:transform .35s,visibility .35s;background:#fff;border-radius:16px 16px 0 0;box-shadow:0 -12px 40px -16px rgba(15,23,42,.45);padding:8px 10px 10px;box-sizing:border-box}.osc-rk-kayan.on{transform:translate(-50%,0);visibility:visible}.osc-rk-kayan>small{display:block;text-align:center;font:600 12px system-ui,sans-serif;color:#6d28d9;margin-bottom:4px}.osc-rk-kayan>button{position:absolute;right:8px;top:6px;border:0!important;background:#f1f5f9!important;color:#334155!important;padding:0!important;font-size:16px;line-height:1;border-radius:8px;width:26px;height:26px;cursor:pointer}.osc-rk-kayan .osc-rk{margin:0}@media print{.osc-rk,.osc-rk-kayan{display:none!important}}</style>
<style>.osc-yz-seo{max-width:1180px;margin:10px auto 40px;padding:0 4px;font-family:"Plus Jakarta Sans",system-ui,sans-serif;color:#334155}.osc-yz-seo h2{font-size:20px;margin:0 0 14px;color:#0b1220}.osc-yz-seo .ic{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.osc-yz-seo .ic>div{background:#fff;border:1px solid #e6e9f2;border-radius:16px;padding:16px}.osc-yz-seo b{color:#4338ca}.osc-yz-seo p{font-size:14px;line-height:1.6;margin:6px 0 0}.osc-yz-seo .alt{margin-top:12px;color:#64748b}@media(max-width:760px){.osc-yz-seo .ic{grid-template-columns:1fr}}</style>
<?php return ob_get_clean();
    }
}
$GLOBALS['osc_yazili'] = new OSC_Yazili();
}


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
    public static function css_cikti() { ?>.osc-ev{--ev-a:#0f766e;--ev-b:#2563eb;--ev-k:#0b1220;--ev-g:#475569;--ev-c:#e3e8f0;font-family:"Plus Jakarta Sans",system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ev-k);max-width:1200px;margin:0 auto 40px;box-sizing:border-box}
.osc-ev *{box-sizing:border-box}
.ev-yuk{padding:60px;text-align:center;color:var(--ev-g)}
.ev-hero{display:flex;gap:20px;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;border-radius:26px;padding:30px 30px 24px;margin-bottom:18px;color:#fff;background:radial-gradient(600px 240px at 92% -20%,rgba(45,212,191,.35),transparent 60%),linear-gradient(135deg,#062a2f,#0f3d56 55%,#1e3a8a)}
.ev-hero h1{margin:8px 0 8px;font-size:clamp(26px,3.6vw,38px);line-height:1.12;letter-spacing:-.02em;color:#fff}
.ev-hero h1 em{font-style:normal;color:#5eead4}
.ev-hero p{margin:0;max-width:640px;color:#cbd5e1;line-height:1.6;font-size:15px}
.ev-et{display:inline-block;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;background:rgba(255,255,255,.12);border-radius:99px;padding:5px 12px}
.ev-hero nav{display:flex;gap:6px;background:rgba(255,255,255,.1);padding:5px;border-radius:14px}
#osc-ev .ev-hero nav button{border:0;background:transparent;color:#e2e8f0;font-family:inherit;font-weight:700;font-size:14px;line-height:1.25;letter-spacing:normal;text-transform:none;box-shadow:none;min-height:0;text-decoration:none;padding:9px 14px;border-radius:10px;cursor:pointer}
#osc-ev .ev-hero nav button.on{background:#fff;color:#0f3d56}
.ev-kart{background:#fff;border:1px solid var(--ev-c);border-radius:20px;padding:22px;box-shadow:0 20px 40px -34px rgba(15,23,42,.45)}
.ev-kart h2{margin:0 0 16px;font-size:20px}.ev-kart h3{margin:0 0 8px;font-size:16px}
.ev-turler{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px}
#osc-ev .ev-turler button{text-align:left;border:1px solid var(--ev-c);background:#fbfcfe;border-radius:16px;padding:16px;cursor:pointer;font-family:inherit;color:inherit;transition:transform .15s,border-color .15s,box-shadow .15s}
#osc-ev .ev-turler button:hover{transform:translateY(-2px);border-color:#5eead4;box-shadow:0 14px 30px -22px rgba(15,118,110,.6)}
.ev-turler span{font-size:26px;display:block;margin-bottom:8px}.ev-turler b{display:block;font-size:15px;margin-bottom:4px}.ev-turler small{color:var(--ev-g);font-size:13px;line-height:1.5}
.ev-not{color:var(--ev-g);font-size:13px;margin:14px 0 0;line-height:1.5}
.ev-ust{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px}.ev-ust h2{margin:0}
.ev-form{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px 14px}
.ev-form label{display:flex;flex-direction:column;gap:5px;font-size:13px;font-weight:700;color:#334155}
.ev-form label.gen{grid-column:1/-1}
.ev-form input,.ev-form select,.ev-form textarea{font-family:inherit;font-weight:500;font-size:14px;line-height:1.25;letter-spacing:normal;text-transform:none;box-shadow:none;min-height:0;text-decoration:none;border:1px solid #cfd8e3;border-radius:11px;padding:10px 12px;background:#fff;color:var(--ev-k);width:100%}
.ev-form input:focus,.ev-form select:focus,.ev-form textarea:focus{outline:2px solid #99f6e4;border-color:var(--ev-a)}
.ev-cubuk{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:18px;padding-top:16px;border-top:1px solid var(--ev-c);color:var(--ev-g);font-size:13px}
.ev-cubuk>div{display:flex;gap:8px;flex-wrap:wrap}
#osc-ev .ev-btn{border:1px solid #cfd8e3;background:#fff;color:#0f172a;font-family:inherit;font-weight:700;font-size:14px;line-height:1.25;letter-spacing:normal;text-transform:none;box-shadow:none;min-height:0;text-decoration:none;padding:10px 16px;border-radius:12px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px}
#osc-ev .ev-btn:hover{border-color:#94a3b8}#osc-ev .ev-btn:disabled{opacity:.6;cursor:wait}
#osc-ev .ev-btn.ana{border:0;color:#fff;background:linear-gradient(135deg,var(--ev-a),var(--ev-b))}
#osc-ev .ev-btn.hafif{background:#f1f5f9;border-color:transparent}#osc-ev .ev-btn.tam{width:100%;margin-top:8px}
.ev-don{width:14px;height:14px;border:2px solid rgba(255,255,255,.5);border-top-color:#fff;border-radius:50%;animation:evd .8s linear infinite}@keyframes evd{to{transform:rotate(360deg)}}
.ev-izgara{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:18px;align-items:start}
.ev-yan{position:sticky;top:20px}
.ev-kagit{background:#fff;border:1px solid var(--ev-c);border-radius:6px;box-shadow:0 30px 60px -40px rgba(15,23,42,.55);padding:48px 52px;font:11pt/1.5 Calibri,Arial,sans-serif;color:#111;min-height:600px;overflow-x:auto}
.ev-kagit.yatay{padding:36px 40px}
.ev-kagit .ev-b{text-align:center;font-weight:700;margin:0 0 3px}.ev-kagit .ev-alt{text-align:center;margin-bottom:8px}
.ev-kagit h4{color:#1e3a8a;margin:16px 0 6px;font-size:11.5pt}
.ev-kagit p{text-align:justify;margin:0 0 6px}.ev-kagit ol{margin:0 0 4px;padding-left:22px}.ev-kagit li{margin-bottom:3px}
.ev-kagit table{border-collapse:collapse;width:100%;margin:6px 0 10px}
.ev-kagit td,.ev-kagit th{border:1px solid #6b7280;padding:4px 6px;vertical-align:top;font-size:10pt;text-align:left}
.ev-kagit th{background:#e8edf6}.ev-kagit .ev-bilgi th{width:30%;background:#f1f4f9}
.ev-kagit [contenteditable]{border-radius:4px;transition:background .15s}
.ev-kagit [contenteditable]:hover{background:#f0fdfa;outline:1px dashed #5eead4}
.ev-kagit [contenteditable]:focus{background:#ecfeff;outline:2px solid #2dd4bf}
#osc-ev .ev-ekle{border:0;background:none;color:var(--ev-a);font-family:inherit;font-weight:700;font-size:12px;line-height:1.25;letter-spacing:normal;text-transform:none;box-shadow:none;min-height:0;text-decoration:none;cursor:pointer;padding:2px 0 8px}
.ev-imza{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;text-align:center;margin-top:10px}
.ev-imza b,.ev-imza small,.ev-imza i{display:block}.ev-imza i{color:#888;font-style:normal;margin-top:14px;font-size:9pt}
.ev-onay{text-align:center;margin-top:20px}.ev-onay b,.ev-onay span{display:block}.ev-onay span{margin-bottom:6px}
.ev-sayfa{border:0;border-top:2px dashed #cbd5e1;margin:26px 0}
.ev-liste{display:flex;flex-direction:column;gap:8px}
.ev-li{display:flex;align-items:center;gap:12px;border:1px solid var(--ev-c);border-radius:14px;padding:10px 12px}
.ev-li>span{font-size:22px}.ev-li>div{flex:1;min-width:0}.ev-li b{display:block;font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ev-li small{color:var(--ev-g)}
.ev-bos{padding:40px;text-align:center;color:var(--ev-g);background:#f8fafc;border-radius:14px}
.ev-tost{position:fixed;left:50%;bottom:22px;transform:translate(-50%,30px);opacity:0;z-index:99999;background:#0f172a;color:#fff;padding:12px 18px;border-radius:12px;font:600 14px system-ui,sans-serif;transition:.25s;max-width:min(560px,92vw)}
.ev-tost.on{opacity:1;transform:translate(-50%,0)}.ev-tost.hata{background:#b91c1c}.ev-tost.ok{background:#0f766e}
@media(max-width:900px){.ev-izgara{grid-template-columns:1fr}.ev-yan{position:static}.ev-kagit{padding:26px 18px}.ev-imza{grid-template-columns:repeat(2,1fr)}}
@media(max-width:560px){.ev-hero{padding:22px 18px}.ev-kart{padding:16px}}
@media print{.ev-hero,.ev-yan,#osc-ev .ev-ekle{display:none!important}.ev-izgara{display:block}.ev-kagit{border:0;box-shadow:none;padding:0}}

#osc-ev .ev-turler button{display:block;width:100%;font-size:inherit;font-weight:400;line-height:1.4}
#osc-ev .ev-turler small{display:block;line-height:1.5}
.ev-kagit .ev-sag{text-align:right;margin:0 0 6px}
.ev-coklu{grid-column:1/-1;display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:700;color:#334155}
.ev-secimler{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:4px 12px;border:1px solid #cfd8e3;border-radius:12px;padding:10px 12px;max-height:280px;overflow:auto;background:#fbfcfe}
.ev-secimler>div{grid-column:1/-1}
.osc-ev .ev-secimler label{flex-direction:row;align-items:center;gap:6px;font-weight:500;font-size:13px}
.osc-ev .ev-secimler input{width:auto}
.osc-rk{max-width:1180px;margin:18px auto;text-align:center;min-height:50px;overflow:hidden}.osc-rk-et{display:block;font:600 10px/1 system-ui,sans-serif;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8;margin-bottom:4px}
.ev-yan .osc-rk{margin:14px 0 0}
@media print{.osc-rk{display:none!important}}
.ev-oys{margin-top:18px;border-top:1px solid var(--ev-c);padding-top:6px}
.ev-oys h3{margin:14px 0 10px;font-size:16px}
.ev-oy-plan{margin-top:12px;overflow-x:auto}
.ev-oy-plan table,.ev-oy-izgara table{border-collapse:collapse;width:100%;font-size:13px}
.ev-oy-plan th,.ev-oy-plan td,.ev-oy-izgara th,.ev-oy-izgara td{border:1px solid var(--ev-c);padding:4px 6px;text-align:left;vertical-align:middle}
.ev-oy-plan th,.ev-oy-izgara th{background:#f1f5f9;font-weight:700;white-space:nowrap}
.ev-oy-plan td:first-child{width:46px;text-align:center;font-weight:700}
.ev-oy-plan select{width:100%;font:inherit;font-size:13px;border:1px solid #cfd8e3;border-radius:8px;padding:6px}
.ev-oy-plan input{width:70px;font:inherit;border:1px solid #cfd8e3;border-radius:8px;padding:6px}
.ev-oy-alt{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:10px 0 4px}
.ev-oy-top{margin-left:auto;font-size:14px;color:#0f766e}.ev-oy-top.uyari{color:#b91c1c}
.ev-oy-yap{border:1px dashed #94a3b8;border-radius:12px;padding:10px 12px;margin-bottom:12px;background:#f8fafc}
.ev-oy-yap summary{cursor:pointer;font-weight:700}
.ev-oy-yap textarea{width:100%;font:13px ui-monospace,monospace;border:1px solid #cfd8e3;border-radius:10px;padding:8px;margin:8px 0;box-sizing:border-box}
.ev-oy-izgara{overflow-x:auto;max-height:560px;overflow-y:auto;border:1px solid var(--ev-c);border-radius:12px}
.ev-oy-izgara th{position:sticky;top:0;z-index:1;text-align:center}
.ev-oy-izgara th small{display:block;font-weight:500;color:#64748b;font-size:10px}
.ev-oy-izgara td{padding:2px}
.ev-oy-izgara input{border:1px solid transparent;border-radius:6px;padding:5px;font:inherit;font-size:13px;background:transparent;box-sizing:border-box}
.ev-oy-izgara input:focus{border-color:#2dd4bf;background:#ecfeff;outline:0}
.ev-oy-izgara input.no{width:64px}.ev-oy-izgara input.ad{width:190px}.ev-oy-izgara input.pn{width:46px;text-align:center}
.ev-oy-izgara input.pn::-webkit-inner-spin-button{display:none}
.ev-oy-izgara td.tp{font-weight:800;text-align:center;min-width:52px;color:#0f766e}
#osc-ev .ev-x{border:0;background:none;color:#94a3b8;cursor:pointer;font-size:14px;padding:4px 6px;line-height:1;box-shadow:none}
#osc-ev .ev-x:hover{color:#b91c1c}
<?php }
    public static function js_cikti() { ?>/* =====================================================================
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
        case 'sag': g += cokSatir(b.s, { b: !!b.kalin }, '<w:spacing w:after="40"/><w:jc w:val="right"/>'); break;
        case 'bosluk': g += p('', '<w:spacing w:after="' + (b.n || 240) + '"/>'); break;
        case 'onay': g += p('', '<w:spacing w:after="120"/>') + ortala(wr(b.s || 'UYGUNDUR', { b: true, sz: 22 }), SP0) + ortala(wr(b.tarih || '…/…/20…', { sz: 21 }), SP0) + p('', '<w:spacing w:after="360"/>') + ortala(wr(b.ad || '………………………', { b: true, sz: 21 }), SP0) + ortala(wr(b.gorev || 'Okul Müdürü', { sz: 21 }), SP0); break;
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

/* =====================================================================
 * Evrak Hazırla: zümre / veli / ŞÖK tutanakları, BEP planı, öğrenci
 * kulübü evrakları. Taslak metinler bize özgüdür; isteğe bağlı olarak
 * yapay zekâ ile kişiselleştirilir. Evraklar yalnızca bu tarayıcıda
 * saklanır (öğrenci bilgileri sunucuya kaydedilmez).
 * ===================================================================== */
(function () {
  function BASLAT() {
  var C = window.OSC_EV || {}, kok = document.getElementById('osc-ev'); if (!kok) return;
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function ls(k, v) { try { if (v === undefined) return JSON.parse(localStorage.getItem(k) || 'null'); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, JSON.stringify(v)); } catch (e) { return null; } }
  function trUp(s) { return String(s || '').replace(/i/g, 'İ').replace(/ı/g, 'I').toUpperCase(); }
  function bugun() { var d = new Date(); return ('0' + d.getDate()).slice(-2) + '.' + ('0' + (d.getMonth() + 1)).slice(-2) + '.' + d.getFullYear(); }
  function yil() { var d = new Date(), y = d.getFullYear(); return d.getMonth() >= 7 ? y + '-' + (y + 1) : (y - 1) + '-' + y; }
  function satirlar(s) { return String(s || '').split('\n').map(function (x) { return x.trim(); }).filter(Boolean); }
  function bildir(m, t) { var e = document.createElement('div'); e.className = 'ev-tost ' + (t || ''); e.textContent = m; document.body.appendChild(e); setTimeout(function () { e.classList.add('on'); }, 10); setTimeout(function () { e.remove(); }, 4200); }
  function sunucu(a, d) {
    var fd = new FormData(); fd.append('action', 'osc_ev_' + a); fd.append('nonce', C.nonce); fd.append('veri', JSON.stringify(d || {}));
    return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (!j || !j.success) throw new Error((j && j.data) || 'Bir hata oluştu'); return j.data; });
  }

  /* ---------------- öğrenci kulüpleri (Sosyal Etkinlikler Politika Belgesi, 2026) ---------------- */
  var KULUP = {
    'Zorunlu kulüpler': ['Kızılay Kulübü', 'Yeşilay Kulübü', 'Beyazay Kulübü', 'Siberay Kulübü', 'Tarih ve Kültürel Miras Kulübü', 'Tabiat ve Yaşanabilir Çevre Kulübü', 'Sağlıklı Yaşam ve Fiziksel Aktivite Kulübü', 'Yaşam Becerileri Kulübü'],
    'Kültür Dünyamız': ['Kültür ve Yayıncılık Kulübü', 'Medeniyet ve Düşünce Kulübü', 'Felsefe ve Münazara Kulübü', 'Dil, Hitabet ve İletişim Kulübü', 'Gezi ve Turizm Kulübü', 'Kapsayıcı Yaşam Kulübü', 'Ahilik ve Fütüvvet Kulübü'],
    'Sanat': ['Fotoğrafçılık ve Film Kulübü', 'Görsel Sanatlar Kulübü', 'Dijital Sanatlar Kulübü', 'Müzik Kulübü', 'Sahne Sanatları Kulübü', 'Geleneksel Sanatlar Kulübü', 'Edebiyat ve Yazarlık Kulübü'],
    'Spor': ['Spor Kulübü', 'Ata Sporları Kulübü', 'Halk Oyunları Kulübü', 'İzcilik ve Doğa Sporları Kulübü', 'Akıl ve Zekâ Oyunları Kulübü', 'Çocuk Oyunları Kulübü'],
    'Bilim': ['Medya ve Bilgi Okuryazarlığı Kulübü', 'Dijital Vatandaşlık, Güvenli İnternet ve Sosyal Medya Kulübü', 'Bilim ve Teknoloji Kulübü', 'Analoji ve Tasarım Kulübü', 'Sosyal Bilimler Kulübü', 'Matematik Kulübü', 'Proje ve Araştırma Kulübü', 'Yapay Zekâ Kulübü', 'Dijital Beceriler Kulübü', 'Girişimcilik ve Üretkenlik Kulübü', 'Havacılık ve Uzay Kulübü', 'Tasarım ve Mühendislik Kulübü'],
    'Doğa-Çevre-Toplum': ['Meslekî Tanıtım ve Meslekî Gelişim Kulübü', 'Nezaket Hareketi Kulübü', 'Demokrasi ve İnsan Hakları Kulübü', 'Trafik ve İlk Yardım Kulübü', 'Afet Farkındalığı Kulübü', 'Diplomasi ve Müzakere Kulübü', 'Sıfır Atık Kulübü', 'İyilik Hareketi Kulübü']
  };
  var TEMA_KULUP = { 'Kızılay Kulübü': 'Doğa-Çevre-Toplum', 'Yeşilay Kulübü': 'Doğa-Çevre-Toplum', 'Beyazay Kulübü': 'Doğa-Çevre-Toplum', 'Siberay Kulübü': 'Bilim', 'Tarih ve Kültürel Miras Kulübü': 'Kültür Dünyamız', 'Tabiat ve Yaşanabilir Çevre Kulübü': 'Doğa-Çevre-Toplum', 'Sağlıklı Yaşam ve Fiziksel Aktivite Kulübü': 'Spor', 'Yaşam Becerileri Kulübü': 'Doğa-Çevre-Toplum' };
  function kulupTema(k) { if (TEMA_KULUP[k]) return TEMA_KULUP[k]; for (var t in KULUP) if (t !== 'Zorunlu kulüpler' && KULUP[t].indexOf(k) >= 0) return t; return ''; }
  var DERSLER = ['Türkçe', 'Matematik', 'Fen Bilimleri', 'Sosyal Bilgiler', 'İngilizce', 'Din Kültürü ve Ahlak Bilgisi', 'T.C. İnkılap Tarihi ve Atatürkçülük', 'Türk Dili ve Edebiyatı', 'Fizik', 'Kimya', 'Biyoloji', 'Tarih', 'Coğrafya', 'Felsefe', 'Beden Eğitimi ve Spor', 'Görsel Sanatlar', 'Müzik', 'Teknoloji ve Tasarım', 'Bilişim Teknolojileri ve Yazılım', 'Hayat Bilgisi', 'Sınıf Öğretmenliği', 'Rehberlik', 'Özel Eğitim', 'Okul Öncesi', 'Meslek Dersleri'];
  var AYLAR = ['Eylül', 'Ekim', 'Kasım', 'Aralık', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran'];

  /* ---------------- evrak türleri ve form alanları ---------------- */
  var ORTAK = [{ k: 'okul', ad: 'Okul adı', ph: 'Atatürk Ortaokulu' }, { k: 'il', ad: 'İl / ilçe', ph: 'Ankara / Çankaya' }, { k: 'yil', ad: 'Eğitim-öğretim yılı', v: yil }];
  var TOPLANTI = { donem_basi: 'Ders yılı başı', ikinci_donem: 'İkinci dönem başı', yil_sonu: 'Ders yılı sonu', donem_ara: 'Gerektiğinde (ara değerlendirme)' };
  var KADEME = { k: 'kademe', ad: 'Okul kademesi', tip: 'secim', l: ['İlkokul', 'Ortaokul', 'Lise'], v: 'Ortaokul' };
  function dayanak(id, v) {
    var yon = v.kademe === 'Lise' ? 'Millî Eğitim Bakanlığı Ortaöğretim Kurumları Yönetmeliği' : 'Millî Eğitim Bakanlığı Okul Öncesi Eğitim ve İlköğretim Kurumları Yönetmeliği';
    if (id === 'zumre' || id === 'sok' || id === 'ogr_kurul') return yon;
    if (id === 'bep') return 'Millî Eğitim Bakanlığı Özel Eğitim Hizmetleri Yönetmeliği';
    if (/^kulup/.test(id)) return 'Millî Eğitim Bakanlığı Sosyal Etkinlikler Politika Belgesi ve okul temelli sosyal etkinlikler eylem planı';
    return '';
  }
  var BGH = [['Eğitim-öğretim yılının ilk haftası', 'İlköğretim Haftası'], ['Eğitim-öğretim yılının ilk haftası', '15 Temmuz Demokrasi ve Millî Birlik Günü anma etkinlikleri'], ['19 Eylül', 'Gaziler Günü'], ['4 Ekim', 'Hayvanları Koruma Günü'], ['Ekim ayının ilk pazartesi', 'Dünya Çocuk Günü'], ['Ekim ayının 2. haftası', 'Ahilik Kültürü Haftası'], ['29 Ekim', 'Cumhuriyet Bayramı'], ['29 Ekim – 4 Kasım', 'Kızılay Haftası'], ['2 – 8 Kasım', 'Lösemili Çocuklar Haftası'], ['3 – 9 Kasım', 'Organ Bağışı ve Nakli Haftası'], ['10 – 16 Kasım', 'Atatürk Haftası'], ['10 Kasım', '10 Kasım Atatürk’ü Anma Günü'], ['12 Kasım', 'Afet Eğitimi Hazırlık Günü'], ['14 Kasım', 'Dünya Diyabet Günü'], ['20 Kasım', 'Dünya Çocuk Hakları Günü'], ['21 – 27 Kasım', 'Ağız ve Diş Sağlığı Haftası'], ['24 Kasım', 'Öğretmenler Günü'], ['3 Aralık', 'Dünya Engelliler Günü'], ['5 Aralık', 'Türk Kadınına Seçme ve Seçilme Hakkının Verilişi'], ['10 Aralık’ı içine alan hafta', 'İnsan Hakları ve Demokrasi Haftası'], ['12 – 18 Aralık', 'Tutum, Yatırım ve Türk Malları Haftası'], ['27 Aralık', 'Mehmet Akif Ersoy’u Anma'], ['Ocak ayının 2. haftası', 'Enerji Tasarrufu Haftası'], ['28 Şubat', 'Sivil Savunma Günü'], ['1 – 7 Mart', 'Deprem Haftası'], ['1 Mart’ı içine alan hafta', 'Yeşilay Haftası'], ['Mart ayının ilk haftası', 'Girişimcilik Haftası'], ['8 – 14 Mart', 'Bilim ve Teknoloji Haftası'], ['12 Mart', 'İstiklâl Marşı’nın Kabulü ve Mehmet Akif Ersoy’u Anma Günü'], ['15 – 21 Mart', 'Tüketiciyi Koruma Haftası'], ['18 Mart', 'Çanakkale Zaferi ve Şehitleri Anma Günü'], ['21 – 26 Mart', 'Orman Haftası'], ['22 Mart', 'Dünya Su Günü'], ['Mart ayının son pazartesini içine alan hafta', 'Kütüphaneler Haftası'], ['27 Mart', 'Dünya Tiyatrolar Günü'], ['1 – 7 Nisan', 'Kanser Haftası'], ['2 Nisan', 'Dünya Otizm Farkındalık Günü'], ['7 Nisan', 'Dünya Sağlık Günü'], ['15 – 22 Nisan', 'Turizm Haftası'], ['23 Nisan', 'Ulusal Egemenlik ve Çocuk Bayramı'], ['Mayıs ayının ilk haftası', 'Trafik ve İlk Yardım Haftası'], ['Mayıs ayının 2. pazarı', 'Anneler Günü'], ['10 – 16 Mayıs', 'Engelliler Haftası'], ['18 – 24 Mayıs', 'Müzeler Haftası'], ['19 Mayıs', 'Atatürk’ü Anma, Gençlik ve Spor Bayramı'], ['Mayıs ayının son haftası', 'Hayat Boyu Öğrenme Haftası'], ['29 Mayıs', 'İstanbul’un Fethi'], ['Haziran ayının 2. haftası', 'Çevre Koruma Haftası'], ['Haziran ayının 3. pazarı', 'Babalar Günü'], ['Hicrî takvime göre', 'Mevlid-i Nebî Haftası']];
  function bghEtkinlik(a) { return /Bayram/.test(a) ? 'Tören programı; şiir, kompozisyon ve resim çalışmaları; okul ve sınıf panosunun hazırlanması' : /Anma|Şehit|Gaziler|15 Temmuz/.test(a) ? 'Anma programı ve saygı duruşu; konuyla ilgili sunum; pano hazırlanması' : /Atatürk Haftası/.test(a) ? '10 Kasım anma töreni; Atatürk’ün hayatı ve eserleriyle ilgili okuma ve sunumlar; pano' : 'Konunun önemini anlatan sınıf içi etkinlik; afiş, pano ya da kısa sunum hazırlanması'; }
  var TURLER = [
    { id: 'zumre', ad: 'Zümre öğretmenler kurulu tutanağı', ik: '👥', ac: 'Ders zümresi toplantısı: gündem, görüşmeler, kararlar ve imzalar', alan: ORTAK.concat([
      KADEME, { k: 'ders', ad: 'Ders / zümre', tip: 'secim', l: DERSLER }, { k: 'siniflar', ad: 'Sınıf seviyeleri', ph: '5, 6, 7 ve 8. sınıflar' },
      { k: 'toplanti', ad: 'Toplantı', tip: 'secim', l: TOPLANTI, v: 'donem_basi' }, { k: 'tarih', ad: 'Tarih', v: bugun }, { k: 'saat', ad: 'Saat', v: '15:00' }, { k: 'yer', ad: 'Yer', v: 'Öğretmenler odası' },
      { k: 'baskan', ad: 'Zümre başkanı', ph: 'Ad Soyad' }, { k: 'uyeler', ad: 'Zümre öğretmenleri (her satıra bir kişi)', tip: 'uzun', ph: 'Ayşe Yılmaz\nMehmet Kaya' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }, { k: 'not', ad: 'Eklemek istedikleriniz (isteğe bağlı)', tip: 'uzun', ph: 'Ör. Okulumuzda laboratuvar yenilendi; 8. sınıflar için ek kaynak kullanılacak…' }]) },
    { id: 'sok', ad: 'Şube öğretmenler kurulu (ŞÖK) tutanağı', ik: '🏫', ac: 'Bir şubenin öğretmenlerinin dönem toplantısı', alan: ORTAK.concat([
      KADEME, { k: 'sube', ad: 'Sınıf / şube', ph: '7/A' }, { k: 'rehber', ad: 'Sınıf rehber öğretmeni', ph: 'Ad Soyad' }, { k: 'toplanti', ad: 'Toplantı', tip: 'secim', l: TOPLANTI, v: 'donem_basi' },
      { k: 'tarih', ad: 'Tarih', v: bugun }, { k: 'saat', ad: 'Saat', v: '15:00' }, { k: 'yer', ad: 'Yer', v: 'Öğretmenler odası' }, { k: 'mevcut', ad: 'Öğrenci sayısı', ph: '28' },
      { k: 'ogretmenler', ad: 'Şube öğretmenleri (her satıra “Ders - Ad Soyad”)', tip: 'uzun', ph: 'Türkçe - Ayşe Yılmaz\nMatematik - Mehmet Kaya' }, { k: 'mudur', ad: 'Okul müdürü / müdür yardımcısı', ph: 'Ad Soyad' }, { k: 'not', ad: 'Şubeye özel durumlar (isteğe bağlı)', tip: 'uzun', ph: 'Ör. Devamsızlığı artan 2 öğrenci; okuma hızı düşük öğrenciler…' }]) },
    { id: 'veli', ad: 'Veli toplantısı tutanağı', ik: '👨‍👩‍👧', ac: 'Sınıf veli toplantısı: gündem, görüşmeler, kararlar, katılım listesi', alan: ORTAK.concat([
      { k: 'sube', ad: 'Sınıf / şube', ph: '7/A' }, { k: 'ogretmen', ad: 'Sınıf öğretmeni / rehber öğretmen', ph: 'Ad Soyad' }, { k: 'toplanti', ad: 'Toplantı', tip: 'secim', l: TOPLANTI, v: 'donem_basi' },
      { k: 'tarih', ad: 'Tarih', v: bugun }, { k: 'saat', ad: 'Saat', v: '16:00' }, { k: 'yer', ad: 'Yer', ph: '7/A sınıfı' }, { k: 'katilim', ad: 'Katılan veli sayısı', ph: '21' },
      { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }, { k: 'not', ad: 'Konuşulacak özel konular (isteğe bağlı)', tip: 'uzun', ph: 'Ör. Okul gezisi, kitap okuma kampanyası…' }]) },
    { id: 'bep', ad: 'BEP planı (Bireyselleştirilmiş Eğitim Programı)', ik: '🧩', ac: 'Kaynaştırma / özel eğitim öğrencisi için ders bazlı uzun ve kısa dönemli amaçlar', alan: ORTAK.concat([
      { k: 'ogrenci', ad: 'Öğrencinin adı (baş harfleri yeterli)', ph: 'A. Y.' }, { k: 'sinif', ad: 'Sınıf / şube', ph: '6/B' }, { k: 'tani', ad: 'Eğitsel tanı / ihtiyaç', tip: 'secim', l: ['Öğrenme güçlüğü', 'Hafif düzeyde zihinsel yetersizlik', 'Dikkat eksikliği ve hiperaktivite bozukluğu', 'Otizm spektrum bozukluğu', 'İşitme yetersizliği', 'Görme yetersizliği', 'Dil ve konuşma güçlüğü', 'Bedensel yetersizlik', 'Özel yetenek', 'Diğer'] },
      { k: 'ders', ad: 'Ders', tip: 'secim', l: DERSLER }, { k: 'performans', ad: 'Öğrencinin şu anki performansı (bildikleri ve güçlük çektikleri)', tip: 'uzun', ph: 'Ör. 100’e kadar sayıları okuyup yazabiliyor; toplama işlemini yapabiliyor, eldeli çıkarmada güçlük çekiyor…' },
      { k: 'baslama', ad: 'BEP başlama tarihi', v: bugun }, { k: 'bitis', ad: 'BEP bitiş tarihi', ph: '…/06/20…' }, { k: 'birim', ad: 'BEP geliştirme birimi üyeleri (her satıra “Görev - Ad Soyad”)', tip: 'uzun', ph: 'Müdür yardımcısı - …\nRehber öğretmen - …\nSınıf öğretmeni - …\nDers öğretmeni - …\nVeli - …' }]) },
    { id: 'kulup_plan', grup: 'kulup', ad: 'Kulüp yıllık çalışma planı', ik: '📅', yatay: true, ac: 'Yeni kulüp yapısına göre (48 kulüp, 5 tema) aylara göre etkinlik planı', alan: ORTAK.concat([
      { k: 'kulup', ad: 'Kulüp', tip: 'kulup' }, { k: 'kademe', ad: 'Okul kademesi', tip: 'secim', l: ['İlkokul', 'Ortaokul', 'Lise'], v: 'Ortaokul' }, { k: 'danisman', ad: 'Danışman öğretmen(ler) (her satıra bir kişi)', tip: 'uzun' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }, { k: 'not', ad: 'Planlanan özel etkinlikler (isteğe bağlı)', tip: 'uzun', ph: 'Ör. Mart’ta il genelinde proje yarışmasına katılım…' }]) },
    { id: 'kulup_tutanak', grup: 'kulup', ad: 'Kulüp ilk toplantı ve seçim tutanağı', ik: '🗳️', ac: 'Kulübün kuruluş toplantısı, görev dağılımı ve üye listesi', alan: ORTAK.concat([
      { k: 'kulup', ad: 'Kulüp', tip: 'kulup' }, { k: 'tarih', ad: 'Tarih', v: bugun }, { k: 'yer', ad: 'Yer', ph: 'Konferans salonu' }, { k: 'danisman', ad: 'Danışman öğretmen(ler) (her satıra bir kişi)', tip: 'uzun' },
      { k: 'baskan', ad: 'Kulüp başkanı (öğrenci)', ph: 'Ad Soyad - Sınıf' }, { k: 'baskanyrd', ad: 'Başkan yardımcısı', ph: 'Ad Soyad - Sınıf' }, { k: 'sekreter', ad: 'Sekreter', ph: 'Ad Soyad - Sınıf' }, { k: 'uyeler', ad: 'Üye öğrenciler (her satıra “Ad Soyad - Sınıf - No”)', tip: 'uzun' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'kulup_rapor', grup: 'kulup', ad: 'Kulüp dönem sonu çalışma raporu', ik: '📊', ac: 'Dönem boyunca yapılan kulüp çalışmalarının raporu', alan: ORTAK.concat([
      { k: 'kulup', ad: 'Kulüp', tip: 'kulup' }, { k: 'donem', ad: 'Dönem', tip: 'secim', l: ['1. dönem', '2. dönem'], v: '1. dönem' }, { k: 'danisman', ad: 'Danışman öğretmen(ler)', tip: 'uzun' }, { k: 'uye', ad: 'Üye sayısı', ph: '24' },
      { k: 'yapilanlar', ad: 'Yapılan çalışmalar (her satıra bir etkinlik; boş bırakırsanız örnek içerik oluşturulur)', tip: 'uzun' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'kulup_aylik', grup: 'kulup', ad: 'Kulüp aylık çalışma raporu', ik: '🗓️', ac: 'Kulübün o ay yaptığı etkinliklerin raporu', alan: ORTAK.concat([
      { k: 'kulup', ad: 'Kulüp', tip: 'kulup' }, { k: 'ay', ad: 'Ay', tip: 'secim', l: AYLAR, v: function () { return AYLAR[(new Date().getMonth() + 4) % 12] || 'Ekim'; } }, { k: 'danisman', ad: 'Danışman öğretmen(ler)', tip: 'uzun' }, { k: 'uye', ad: 'Katılan öğrenci sayısı', ph: '20' },
      { k: 'yapilanlar', ad: 'Bu ay yapılanlar (her satıra “Tarih - Etkinlik”; boş bırakırsanız örnek içerik oluşturulur)', tip: 'uzun', ph: '07.10.2026 - Kulüp panosu hazırlandı' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'ogr_kurul', ad: 'Öğretmenler kurulu toplantı tutanağı', ik: '🏛️', ac: 'Okul müdürü başkanlığında öğretmenler kurulu: gündem, görüşmeler, kararlar', alan: ORTAK.concat([KADEME,
      { k: 'toplanti', ad: 'Toplantı', tip: 'secim', l: { donem_basi: 'Ders yılı başı', ikinci_donem: 'İkinci dönem başı', yil_sonu: 'Ders yılı sonu', donem_ara: 'Gerektiğinde (olağanüstü)' }, v: 'donem_basi' }, { k: 'tarih', ad: 'Tarih', v: bugun }, { k: 'saat', ad: 'Saat', v: '14:00' }, { k: 'yer', ad: 'Yer', v: 'Öğretmenler odası' },
      { k: 'mudur', ad: 'Okul müdürü (kurul başkanı)', ph: 'Ad Soyad' }, { k: 'yazman', ad: 'Yazman', ph: 'Ad Soyad' }, { k: 'uyeler', ad: 'Kurul üyeleri (her satıra “Görev/Branş - Ad Soyad”)', tip: 'uzun', ph: 'Müdür Yardımcısı - …\nTürkçe Öğretmeni - …' }, { k: 'not', ad: 'Ek gündem / notlar (isteğe bağlı)', tip: 'uzun' }]) },
    { id: 'egzersiz', ad: 'Ders dışı egzersiz planı', ik: '🏐', yatay: true, ac: 'Spor, sanat ve bilim egzersiz çalışmaları için aylara göre yıllık plan', alan: ORTAK.concat([
      { k: 'egz', ad: 'Egzersizin adı / branşı', ph: 'Voleybol' }, { k: 'alan', ad: 'Alanı', tip: 'secim', l: ['Spor', 'Sanat', 'Bilim', 'Kültür'], v: 'Spor' }, { k: 'ogretmen', ad: 'Egzersiz öğretmeni', ph: 'Ad Soyad' }, { k: 'grup', ad: 'Öğrenci grubu (sınıflar)', ph: '6 ve 7. sınıflar' },
      { k: 'gunsaat', ad: 'Gün ve saat', ph: 'Salı-Perşembe 15:30-16:50' }, { k: 'yer', ad: 'Çalışma yeri', ph: 'Spor salonu' }, { k: 'sure', ad: 'Haftalık süre (ders saati)', ph: '4' }, { k: 'ogrenciler', ad: 'Katılan öğrenciler (isteğe bağlı, her satıra “Ad Soyad - Sınıf - No”)', tip: 'uzun' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'belirli_gun', ad: 'Belirli gün ve haftalar programı', ik: '🎗️', ac: 'Seçilen gün için kutlama/anma programı (akış, konuşma metni, görevliler) ya da yıllık plan', yatayFn: function (v) { return v.program === 'yillik'; }, alan: ORTAK.concat([
      { k: 'program', ad: 'Hazırlanacak program', tip: 'secim', l: { kutlama: 'Kutlama / anma programı (tek gün)', yillik: 'Yıllık belirli gün ve haftalar planı' }, v: 'kutlama', yenile: true },
      { k: 'gun', ad: 'Belirli gün / hafta', tip: 'secim', l: BGH.map(function (x) { return x[1]; }), v: '10 Kasım Atatürk’ü Anma Günü', kosul: function (v) { return v.program !== 'yillik'; } },
      { k: 'tarih', ad: 'Program tarihi', v: bugun, kosul: function (v) { return v.program !== 'yillik'; } }, { k: 'saat', ad: 'Saat', ph: '09:00', kosul: function (v) { return v.program !== 'yillik'; } }, { k: 'yer', ad: 'Yer', ph: 'Okul bahçesi / konferans salonu', kosul: function (v) { return v.program !== 'yillik'; } },
      { k: 'sunucu', ad: 'Sunucu(lar)', ph: 'Ece Kaya (7/A)', kosul: function (v) { return v.program !== 'yillik'; } }, { k: 'konusmaci', ad: 'Günün anlam ve önemini anlatacak kişi', ph: 'Ad Soyad - Türkçe Öğretmeni', kosul: function (v) { return v.program !== 'yillik'; } },
      { k: 'siirler', ad: 'Şiirler (her satıra “Şiirin adı - Okuyan öğrenci”)', tip: 'uzun', ph: 'Atatürk’e Mektup - Ali Yılmaz (6/B)', kosul: function (v) { return v.program !== 'yillik'; } },
      { k: 'gosteriler', ad: 'Diğer gösteriler (koro, skeç, halk oyunu, sunum… her satıra bir tane)', tip: 'uzun', kosul: function (v) { return v.program !== 'yillik'; } },
      { k: 'ogretmenler', ad: 'Programdan sorumlu öğretmenler (her satıra bir kişi)', tip: 'uzun', kosul: function (v) { return v.program !== 'yillik'; } },
      { k: 'kapsam', ad: 'Plan', tip: 'secim', l: ['Okul geneli', 'Sınıf / şube', 'Ders'], v: 'Sınıf / şube', kosul: function (v) { return v.program === 'yillik'; } }, { k: 'sube', ad: 'Sınıf / şube ya da ders', ph: '7/A', kosul: function (v) { return v.program === 'yillik'; } }, { k: 'sorumlu', ad: 'Sorumlu öğretmen', ph: 'Ad Soyad', kosul: function (v) { return v.program === 'yillik'; } },
      { k: 'secim', ad: 'Plana alınacak gün ve haftalar', tip: 'secimler', l: BGH.map(function (x) { return x[1]; }), kosul: function (v) { return v.program === 'yillik'; } }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'gezi', ad: 'Gezi planı (Ek-9)', ik: '🚌', ac: 'Okul dışı gezi planı, veli izin belgesi ve katılan öğrenci listesi', alan: ORTAK.concat([
      { k: 'gezi', ad: 'Gezinin adı / gezilecek yer', ph: 'Anıtkabir ve Etnografya Müzesi' }, { k: 'tur', ad: 'Gezinin türü', tip: 'secim', l: ['Kültürel gezi', 'Bilimsel / teknik gezi', 'Doğa gezisi', 'Meslekî gezi', 'Sosyal sorumluluk gezisi'], v: 'Kültürel gezi' }, { k: 'tarih', ad: 'Gezi tarihi', ph: '…/…/20…' },
      { k: 'hareket', ad: 'Hareket yeri ve saati', ph: 'Okul bahçesi, 08:30' }, { k: 'donus', ad: 'Dönüş saati', ph: '16:30' }, { k: 'ulasim', ad: 'Ulaşım aracı / plaka', ph: 'Otobüs, 06 ABC 123' }, { k: 'siniflar', ad: 'Katılan sınıflar', ph: '7/A, 7/B' }, { k: 'sayi', ad: 'Öğrenci sayısı', ph: '40' },
      { k: 'ogretmenler', ad: 'Sorumlu öğretmenler (her satıra bir kişi)', tip: 'uzun' }, { k: 'program', ad: 'Gezi programı (her satıra “Saat - Etkinlik”, isteğe bağlı)', tip: 'uzun', ph: '09:30 - Anıtkabir ziyareti\n12:00 - Öğle yemeği' }, { k: 'ogrenciler', ad: 'Katılan öğrenciler (isteğe bağlı, her satıra “Ad Soyad - Sınıf - No”)', tip: 'uzun' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'dilekce', ad: 'Dilekçe', ik: '✉️', ac: 'İzin, rapor, nöbet değişikliği, belge talebi vb. resmî dilekçeler', alan: [
      { k: 'dtur', ad: 'Dilekçe türü', tip: 'secim', l: { izin: 'İzin talebi', rapor: 'Sağlık raporu teslimi', nobet: 'Nöbet değişikliği', belge: 'Hizmet belgesi / belge talebi', ders: 'Ders/sınav görevlendirme talebi', genel: 'Diğer (serbest)' }, v: 'izin' },
      { k: 'makam', ad: 'Hitap edilecek makam', ph: 'Atatürk Ortaokulu Müdürlüğüne' }, { k: 'il', ad: 'İl / ilçe', ph: 'ÇANKAYA' }, { k: 'adsoyad', ad: 'Adınız soyadınız', ph: 'Ad Soyad' }, { k: 'gorev', ad: 'Unvan / branş', ph: 'Matematik Öğretmeni' },
      { k: 'tarih', ad: 'Dilekçe tarihi', v: bugun }, { k: 'aciklama', ad: 'Açıklama (tarihler, gerekçe vb.)', tip: 'uzun', ph: 'Ör. 14.11.2026 Cuma günü ailevi bir mazeretim nedeniyle…' }, { k: 'adres', ad: 'Adres', ph: '…' }, { k: 'tel', ad: 'Telefon', ph: '05…' }, { k: 'ekler', ad: 'Ekler (her satıra bir ek, isteğe bağlı)', tip: 'uzun', ph: 'Sağlık raporu (1 sayfa)' }] },
    { id: 'oys', ad: 'OYS puan çizelgesi', ik: '🧮', yzYok: true, yatay: true, ozel: 'oys', ac: 'MEB senaryosuna göre ortak yazılı soru bazlı puan çizelgesi; e-Okul’a not girişi için', alan: ORTAK.concat([
      { k: 'sube', ad: 'Sınıf / şube', ph: '7/A' }, { k: 'tarih', ad: 'Sınav tarihi', ph: '…/…/20…' }, { k: 'ogretmen', ad: 'Ders öğretmeni', ph: 'Ad Soyad' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) }
  ];
  if (Array.isArray(C.turler)) TURLER = TURLER.filter(function (t) { return C.turler.indexOf(t.id) >= 0; });
  function yatayMi(t) { return t.yatayFn ? t.yatayFn(S.veri) : !!t.yatay; }
  function tur(id) { return TURLER.filter(function (t) { return t.id === id; })[0]; }

  /* ---------------- hazır taslak içerikler ---------------- */
  function zumreIcerik(v) {
    var d = v.ders || 'ilgili ders', s = v.siniflar || 'tüm sınıf seviyeleri', t = v.toplanti;
    var g = [], gr = [], k = [];
    var ekle = function (baslik, metin, karar) { g.push(baslik); gr.push(metin); if (karar) k.push(karar); };
    ekle('Açılış, yoklama ve gündemin okunması', 'Zümre başkanı ' + (v.baskan || '') + ' toplantıyı açarak katılımcıları selamladı; yoklama yapıldı ve gündem maddeleri okunarak oy birliğiyle kabul edildi.');
    if (t === 'donem_basi' || t === 'ikinci_donem') {
      ekle('Bir önceki toplantıda alınan kararların değerlendirilmesi', 'Önceki zümre toplantısında alınan kararların uygulanma düzeyi gözden geçirildi. Kararların büyük ölçüde hayata geçirildiği, eksik kalan konuların bu dönem içinde tamamlanacağı belirtildi.', 'Önceki dönemden kalan eksiklikler bu dönemin ilk ayı içinde tamamlanacaktır.');
      ekle('Türkiye Yüzyılı Maarif Modeli öğretim programının incelenmesi ve yıllık planlar', d + ' öğretim programının ' + s + ' için öğrenme çıktıları, beceriler, eğilimler ve değerler boyutuyla incelendiği; ünitelendirilmiş yıllık planların programa ve okulun çalışma takvimine uygun olarak hazırlandığı ifade edildi. Programdaki disiplinler arası ilişkilere ve okuryazarlık becerilerine derslerde yer verilmesinin önemi vurgulandı.', 'Yıllık planlar Türkiye Yüzyılı Maarif Modeli öğretim programına göre hazırlanmış olup ' + s + ' için zümrece ortak uygulanacaktır.');
      ekle('Ölçme ve değerlendirme: ortak yazılı sınavlar, performans görevi ve projeler', 'Yazılı sınavların MEB tarafından yayımlanan konu-soru dağılım tablolarına (senaryolara) uygun hazırlanacağı; açık uçlu, beceri temelli ve gerçek yaşamla ilişkili soruların tercih edileceği belirtildi. Performans görevlerinde dereceli puanlama anahtarı kullanılacağı, proje ödevlerinin öğrenci ilgi ve ihtiyaçlarına göre verileceği konuşuldu. Sınav tarihlerinin e-Okul takvimi ve okul ortak sınav planına göre belirleneceği kararlaştırıldı.', 'Yazılı sınavlar senaryolara uygun, ortak ve açık uçlu ağırlıklı hazırlanacak; performans görevlerinde dereceli puanlama anahtarı kullanılacaktır.');
      ekle('Öğrenci başarısının artırılması ve bireysel farklılıklar', 'Önceki yılın sınav sonuçları ve gözlemler doğrultusunda öğrencilerin güçlük çektiği konular belirlendi. Destekleme ve yetiştirme çalışmaları, ek etkinlikler ve farklılaştırılmış öğretim yöntemleriyle bu konuların pekiştirileceği; kaynaştırma ve BEP öğrencileri için ders içi uyarlamaların yapılacağı belirtildi.', 'Güçlük çekilen konular için destekleme çalışmaları yapılacak; BEP öğrencileri için ölçme-değerlendirmede gerekli uyarlamalar uygulanacaktır.');
      ekle('Ders araç-gereçleri, kaynaklar ve dijital içerik', 'Ders kitaplarının yanında EBA ve MEB onaylı dijital içeriklerden yararlanılacağı; laboratuvar, atölye ve sınıf kitaplığı imkânlarının etkin kullanılacağı, öğrencilere ek mali yük getirecek kaynak istenmeyeceği belirtildi.', 'Ders kitabı ve MEB onaylı dijital içerikler esas alınacak; velilerden ek kaynak alınması istenmeyecektir.');
      ekle('Belirli gün ve haftalar, değerler eğitimi ve diğer zümrelerle iş birliği', 'Dersle ilgili belirli gün ve haftalarda yapılacak etkinlikler planlandı. Değerler eğitimi kapsamında ders içi etkinliklerde sorumluluk, saygı, yardımlaşma gibi değerlere yer verileceği; diğer ders zümreleriyle ortak proje ve etkinlikler yapılabileceği konuşuldu.', 'Belirli gün ve haftalara ait etkinlikler yıllık planda gösterilen tarihlerde zümrece ortak yürütülecektir.');
    } else if (t === 'donem_ara') {
      ekle('Dönem başında alınan kararların uygulanma durumu', 'Dönem başında alınan kararların uygulanma düzeyi görüşüldü; yıllık plana uygun ilerlendiği, planın gerisinde kalınan konuların telafi edileceği belirtildi.', 'Plana göre geride kalınan konular telafi çalışmalarıyla tamamlanacaktır.');
      ekle('Ortak yazılı sınav sonuçlarının analizi', 'Yapılan ortak yazılı sınavların sonuçları soru ve öğrenme çıktısı bazında incelendi. Başarının düşük kaldığı çıktılar belirlenerek bu konularda pekiştirme çalışmaları yapılmasına karar verildi.', 'Başarı düzeyi düşük öğrenme çıktıları için pekiştirme ve yeniden öğretim etkinlikleri yapılacaktır.');
      ekle('Performans görevleri ve projelerin değerlendirilmesi', 'Verilen performans görevlerinin teslim ve değerlendirme süreçleri görüşüldü; dereceli puanlama anahtarlarının öğrencilerle önceden paylaşılmasının olumlu sonuç verdiği belirtildi.');
      ekle('Destekleme ve yetiştirme çalışmaları', 'Güçlük çeken öğrenciler için yürütülen çalışmalar değerlendirildi; BEP öğrencilerinin gelişimlerinin izlenmeye devam edileceği belirtildi.', 'Destekleme ve yetiştirme kursları ile bireysel çalışmalar dönem sonuna kadar sürdürülecektir.');
    } else {
      ekle('Yıl boyunca yapılan çalışmaların değerlendirilmesi', d + ' dersinde yıl başında alınan kararların uygulandığı, yıllık planların büyük ölçüde tamamlandığı; tamamlanamayan konuların gelecek yıl dikkate alınacağı belirtildi.');
      ekle('Öğrenci başarısının değerlendirilmesi', 'Yıl içinde yapılan yazılı sınavlar, performans görevleri ve proje sonuçları değerlendirildi. Başarılı olunan ve güçlük çekilen öğrenme çıktıları belirlendi; sonuçların yeni yıl planlamasında kullanılması kararlaştırıldı.', 'Yıl sonu başarı analizi gelecek eğitim-öğretim yılı planlamasında dikkate alınacaktır.');
      ekle('Gelecek yıl için öneriler ve ihtiyaçlar', 'Ders araç-gereci, laboratuvar ve kaynak ihtiyaçları belirlendi; okul idaresine iletilmesi kararlaştırıldı. Mesleki gelişim için hizmet içi eğitim ihtiyaçları da görüşüldü.', 'Belirlenen araç-gereç ve eğitim ihtiyaçları yazılı olarak okul idaresine bildirilecektir.');
      ekle('Yaz tatili önerileri', 'Öğrencilere yaz tatilinde okuma alışkanlığını ve öğrendiklerini sürdürmeleri için öneriler verilmesi kararlaştırıldı.', 'Öğrencilere yaz tatili için kitap ve etkinlik önerileri verilecektir.');
    }
    if (v.not) ekle('Diğer konular', v.not);
    ekle('Dilek, temenniler ve kapanış', 'Zümre öğretmenleri yeni dönemde başarı dileklerini paylaştı. Toplantı ' + (v.baskan ? 'zümre başkanı ' + v.baskan + ' tarafından ' : '') + 'iyi dileklerle sona erdirildi.');
    return { gundem: g, gorusmeler: gr, kararlar: k };
  }
  function sokIcerik(v) {
    var g = [], gr = [], k = [], sb = v.sube || 'şube', ekle = function (a, b, c) { g.push(a); gr.push(b); if (c) k.push(c); };
    ekle('Açılış ve yoklama', 'Toplantı ' + (v.rehber ? 'sınıf rehber öğretmeni ' + v.rehber + ' tarafından ' : '') + 'açıldı; şube öğretmenlerinin katılımıyla yoklama yapıldı ve gündem kabul edildi.');
    ekle(sb + ' öğrencilerinin genel durumu ve sınıf iklimi', sb + ' şubesinin ' + (v.mevcut ? v.mevcut + ' öğrenciden oluştuğu ' : '') + 've derslere katılımın genel olarak olumlu olduğu belirtildi. Öğretmenler sınıfın güçlü yönlerini ve dikkat edilmesi gereken durumları paylaştı.');
    ekle('Akademik başarı ve dersler arası ortak çalışmalar', 'Öğrencilerin derslere göre başarı durumları karşılaştırıldı; birden fazla derste güçlük çeken öğrenciler belirlenerek ortak bir destek planı yapılması kararlaştırıldı. Okuma-anlama ve problem çözme becerilerinin tüm derslerde desteklenmesi önerildi.', 'Birden fazla derste güçlük çeken öğrenciler için ortak destek planı uygulanacak ve rehberlik servisiyle paylaşılacaktır.');
    ekle('Devam-devamsızlık ve davranış durumu', 'Devamsızlığı artan öğrenciler belirlendi; velileriyle görüşülmesi ve rehberlik servisine bildirilmesi kararlaştırıldı. Okul ve sınıf kurallarına uyum genel olarak iyi değerlendirildi.', 'Devamsızlığı artan öğrencilerin velileriyle sınıf rehber öğretmeni görüşecektir.');
    ekle('Özel eğitim gereksinimi olan ve BEP uygulanan öğrenciler', 'Kaynaştırma eğitimi alan öğrencilerin BEP hedeflerine ulaşma durumları ve ölçme-değerlendirmede yapılacak uyarlamalar görüşüldü.', 'BEP öğrencileri için ders öğretmenleri gerekli uyarlamaları yapacak, gelişim durumu dönem sonunda değerlendirilecektir.');
    ekle('Rehberlik ihtiyaçları ve öğrencilerin ödüllendirilmesi', 'Rehberlik desteğine ihtiyaç duyan öğrencilerin rehberlik servisine yönlendirilmesi; olumlu davranış ve başarı gösteren öğrencilerin teşvik edilmesi kararlaştırıldı.', 'Başarılı ve örnek davranış gösteren öğrenciler okul ödül yönetmeliğine göre teşvik edilecektir.');
    if (v.not) ekle('Şubeye özel konular', v.not);
    ekle('Dilek ve temenniler', 'Öğretmenler iş birliğinin sürdürülmesi dileğiyle görüşlerini paylaştı ve toplantı sona erdi.');
    return { gundem: g, gorusmeler: gr, kararlar: k };
  }
  function veliIcerik(v) {
    var g = [], gr = [], k = [], ekle = function (a, b, c) { g.push(a); gr.push(b); if (c) k.push(c); }, t = v.toplanti;
    ekle('Açılış, tanışma ve gündemin sunulması', 'Sınıf öğretmeni ' + (v.ogretmen || '') + ' velileri selamlayarak toplantıyı açtı; gündem maddeleri hakkında bilgi verdi.');
    if (t === 'donem_basi' || t === 'ikinci_donem') {
      ekle('Okulun işleyişi, ders programı ve önemli tarihler', 'Ders giriş-çıkış saatleri, haftalık ders programı, yazılı sınav ve tatil tarihleri hakkında bilgi verildi. Velilerin e-Okul Veli Bilgilendirme Sistemi’ni düzenli takip etmesi istendi.', 'Veliler e-Okul üzerinden devam, not ve duyuruları düzenli olarak takip edecektir.');
      ekle('Türkiye Yüzyılı Maarif Modeli ve ölçme-değerlendirme', 'Yeni öğretim programlarının beceri ve değer odaklı yapısı, ortak yazılı sınavların MEB senaryolarına göre yapıldığı ve performans görevlerinin değerlendirilme biçimi anlatıldı.');
      ekle('Devam-devamsızlık ve okul kuralları', 'Devamsızlık sınırları ve özürlü devamsızlıkta yapılacak işlemler açıklandı; kılık-kıyafet ve okul kurallarına uyumun önemi vurgulandı.', 'Öğrencinin devamsızlık yapacağı durumlarda veli okula zamanında bilgi verecektir.');
    } else {
      ekle('Dönem içi başarı durumunun değerlendirilmesi', 'Sınıfın genel başarı durumu, yazılı sınav ve performans görevlerinin sonuçları paylaşıldı; bireysel durumlar için velilerle ayrıca görüşülebileceği belirtildi.', 'Başarısı düşen öğrencilerin velileriyle bireysel görüşme yapılacaktır.');
      ekle('Tatil dönemi önerileri', 'Tatil döneminde öğrencilerin kitap okuma, ekran süresi ve uyku düzenine dikkat etmeleri için öneriler verildi.', 'Öğrencilere tatil dönemi için okuma ve etkinlik önerileri verilecektir.');
    }
    ekle('Evde ders çalışma ortamı ve okul-aile iş birliği', 'Düzenli çalışma alışkanlığı, ekran süresinin sınırlandırılması, kitap okuma ve uyku düzeni konularında öneriler sunuldu. Öğrencinin başarısında aile desteğinin önemi vurgulandı.', 'Velilere evde çalışma ve okuma alışkanlığı kazandırma konusunda rehberlik servisinin hazırladığı öneriler iletilecektir.');
    ekle('Rehberlik hizmetleri ve öğrencinin sağlığı', 'Rehberlik servisinin çalışmaları tanıtıldı; öğrencinin sağlık durumu, özel gereksinimi ya da yaşadığı önemli değişiklikler hakkında sınıf öğretmeninin bilgilendirilmesinin önemi belirtildi.');
    if (v.not) ekle('Görüşülen diğer konular', v.not);
    ekle('Velilerin görüş ve önerileri, kapanış', 'Velilerin soruları yanıtlandı ve önerileri not alındı. Toplantı iyi dileklerle sona erdi.');
    return { gundem: g, gorusmeler: gr, kararlar: k };
  }
  var BEP_ORNEK = {
    'Matematik': [['Doğal sayılarla toplama ve çıkarma işlemlerini yapar.', ['Eldesiz toplama işlemi yapar.', 'Eldeli toplama işlemi yapar.', 'Onluk bozmadan çıkarma işlemi yapar.', 'Onluk bozarak çıkarma işlemi yapar.']], ['Günlük yaşamda paralarımızı kullanır.', ['Kâğıt ve madenî paraları tanır.', 'Paraları birbirine dönüştürür.', 'Alışveriş problemlerini çözer.']]],
    'Türkçe': [['Okuduğunu anlar.', ['Kısa bir metni akıcı biçimde sesli okur.', 'Metinle ilgili 5N1K sorularını cevaplar.', 'Metnin ana fikrini söyler.']], ['Duygu ve düşüncelerini yazılı olarak ifade eder.', ['Harfleri doğru ve okunaklı yazar.', 'Basit cümleler kurarak yazar.', 'Bir olayı 3-4 cümleyle anlatır.']]]
  };
  function bepIcerik(v) {
    var d = v.ders || 'Ders', o = BEP_ORNEK[d] || [['' + d + ' dersindeki temel kavramları günlük yaşamla ilişkilendirir.', ['Konuyla ilgili temel kavramları tanır ve örnek verir.', 'Kavramlar arasındaki basit ilişkileri açıklar.', 'Öğrendiklerini günlük yaşamdan bir durumda kullanır.']], ['Dersin temel becerilerini destekle yerine getirir.', ['Yönergeyi dinleyerek etkinliğe başlar.', 'Etkinliği öğretmen desteğiyle tamamlar.', 'Etkinliği bağımsız olarak tamamlar.']]];
    return { performans: v.performans || 'Öğrenci, ' + d + ' dersinde temel kavramları desteklendiğinde kullanabilmekte; çok basamaklı işlemlerde ve dikkat gerektiren etkinliklerde güçlük çekmektedir.', hedefler: o.map(function (x) { return { uda: x[0], kda: x[1], yontem: 'Doğrudan öğretim, model olma, ipucuyla öğretim, bireysel çalışma', materyal: 'Somut materyaller, çalışma yaprakları, görseller, eğitsel oyunlar', degerlendirme: 'Gözlem, kontrol listesi, çalışma yaprağı' }; }), uyarlamalar: ['Sınav süresi ihtiyaca göre uzatılır; sorular sade ve kısa yönergelerle verilir.', 'Sınav ayrı bir ortamda ve okuyucu/yazıcı desteğiyle yapılabilir.', 'Soru sayısı azaltılır, büyük punto ve bol boşluklu kâğıt kullanılır.', 'Değerlendirme BEP’teki kısa dönemli amaçlara göre yapılır.'], aile: ['Aileye her ay öğrencinin gelişimi hakkında bilgi verilir.', 'Evde tekrar edilecek basit etkinlikler aileyle paylaşılır.'] };
  }
  function kulupPlanIcerik(v) {
    var k = v.kulup || 'Kulüp', t = kulupTema(k) || 'Kültür Dünyamız';
    var genel = {
      'Eylül': ['Kulübün tanıtılması, üye öğrencilerin belirlenmesi', 'İlk toplantı: görev dağılımı ve yıllık çalışma planının birlikte hazırlanması'],
      'Ekim': ['Kulüp panosunun hazırlanması', k.replace(' Kulübü', '') + ' konusunda bilgilendirici sunum ve tartışma'],
      'Kasım': ['Konuyla ilgili uzman/kurum ziyareti ya da konuk konuşmacı', 'Öğrenci araştırma ödevlerinin sunulması'],
      'Aralık': ['Okul genelinde farkındalık etkinliği', 'Afiş, broşür ya da kısa video hazırlama'],
      'Ocak': ['1. dönem çalışmalarının değerlendirilmesi ve dönem sonu raporunun hazırlanması'],
      'Şubat': ['2. dönem planının gözden geçirilmesi', 'Grup çalışmasıyla küçük bir proje başlatılması'],
      'Mart': ['Proje çalışmalarının sürdürülmesi', 'Belirli gün ve haftalarla bağlantılı etkinlik'],
      'Nisan': ['Saha gezisi ya da uygulamalı atölye', 'Sosyal sorumluluk çalışması'],
      'Mayıs': ['Yıl sonu sergisi / gösterisi / sunumu', 'Kulüp çalışmalarının okul topluluğuyla paylaşılması'],
      'Haziran': ['Yıl sonu değerlendirme toplantısı', 'Yıllık çalışma raporunun hazırlanması']
    };
    return { tema: t, aylar: AYLAR.map(function (a) { return { ay: a, etkinlikler: genel[a], hedef: a === 'Eylül' ? 'Kulübün yapılandırılması' : (a === 'Ocak' || a === 'Haziran' ? 'Değerlendirme' : t + ' teması kapsamında farkındalık ve beceri geliştirme'), yontem: a === 'Nisan' ? 'Gezi, gözlem, uygulama' : 'Sunum, tartışma, grup çalışması' }; }) };
  }
  function kulupRaporIcerik(v) {
    var l = satirlar(v.yapilanlar);
    if (!l.length) l = ['Kulüp tanıtım toplantısı yapıldı, üyeler belirlendi ve görev dağılımı yapıldı.', 'Yıllık çalışma planı üyelerle birlikte hazırlandı.', 'Kulüp panosu hazırlanarak konuyla ilgili güncel bilgiler paylaşıldı.', 'Okul genelinde farkındalık etkinliği düzenlendi.', 'Öğrenci araştırma ve sunum çalışmaları yapıldı.'];
    return { yapilanlar: l, degerlendirme: 'Kulüp çalışmaları yıllık plana uygun olarak yürütülmüştür. Öğrencilerin etkinliklere katılımı ve sorumluluk alma düzeyi olumlu bulunmuştur. Çalışmalar sayesinde öğrencilerin iş birliği, iletişim ve problem çözme becerilerinin geliştiği gözlemlenmiştir.', oneriler: ['Okul dışı kurumlarla iş birliği artırılabilir.', 'Etkinliklerin e-Okul Sosyal Etkinlik Modülü’ne düzenli işlenmesine devam edilecektir.'] };
  }
  function kurulIcerik(v) {
    var g = [], gr = [], k = [], t = v.toplanti, ekle = function (a, b, c) { g.push(a); gr.push(b); if (c) k.push(c); };
    ekle('Açılış, saygı duruşu ve İstiklâl Marşı', 'Toplantı okul müdürü ' + (v.mudur || '') + ' başkanlığında, saygı duruşu ve İstiklâl Marşı ile açıldı; yoklama yapıldı, ' + (v.yazman ? v.yazman + ' yazman olarak belirlendi ve ' : '') + 'gündem okunarak kabul edildi.');
    if (t === 'donem_basi') {
      ekle('Önceki yıl sonu kurul kararlarının değerlendirilmesi', 'Bir önceki ders yılı sonunda yapılan öğretmenler kurulunda alınan kararlar okunarak uygulanma durumları görüşüldü.');
      ekle('Mevzuat ve Bakanlık yazılarının okunması', (v.kademe === 'Lise' ? 'Ortaöğretim Kurumları Yönetmeliği' : 'Okul Öncesi Eğitim ve İlköğretim Kurumları Yönetmeliği') + ', ölçme ve değerlendirme, sosyal etkinlikler ve disiplin mevzuatındaki güncel düzenlemeler ile Bakanlık ve il/ilçe millî eğitim müdürlüğü yazıları kurula okundu ve açıklandı.', 'Öğretmenler ilgili mevzuat hükümlerine ve Bakanlık yazılarına göre iş ve işlemleri yürütecektir.');
      ekle('Çalışma takvimi, ders dağıtımı ve nöbet çizelgesi', 'Eğitim-öğretim yılı çalışma takvimi, ders dağıtım çizelgesi, sınıf/şube rehber öğretmenlikleri ve nöbet görevleri hakkında bilgi verildi. Nöbetçi öğretmenlerin nöbet yerlerinde zamanında bulunmasının önemi vurgulandı.', 'Ders dağıtımı ve nöbet çizelgesi okul idaresince duyurulduğu şekilde uygulanacaktır.');
      ekle('Öğretim programları ve yıllık planlar', 'Türkiye Yüzyılı Maarif Modeli öğretim programlarının uygulanması, ünitelendirilmiş yıllık planların zümrelerce hazırlanması ve derslerin plana uygun işlenmesi görüşüldü.', 'Yıllık planlar zümre kararlarına uygun olarak hazırlanıp okul idaresine teslim edilecektir.');
      ekle('Ölçme ve değerlendirme', 'Ortak yazılı sınavların MEB konu-soru dağılım tablolarına uygun yapılması, sınav tarihlerinin e-Okul’a zamanında girilmesi, performans görevi ve proje değerlendirmelerinin süresinde tamamlanması konuşuldu.', 'Ortak yazılı sınav takvimi zümrelerce belirlenip okul idaresine bildirilecek; notlar e-Okul’a süresi içinde girilecektir.');
      ekle('Rehberlik ve psikolojik danışma hizmetleri', 'Okul rehberlik ve psikolojik danışma hizmetleri yıllık çalışma programı tanıtıldı; sınıf rehberlik planlarının uygulanması ve risk altındaki öğrencilerin rehberlik servisine yönlendirilmesi görüşüldü.');
      ekle('Özel eğitim ve kaynaştırma uygulamaları', 'Özel eğitim ihtiyacı olan öğrenciler için BEP geliştirme biriminin oluşturulması ve BEP’lerin hazırlanması, ölçme-değerlendirmede gerekli uyarlamaların yapılması konuşuldu.', 'BEP geliştirme birimi oluşturulacak ve BEP’ler süresi içinde hazırlanacaktır.');
      ekle('Sosyal etkinlikler, öğrenci kulüpleri ve belirli gün ve haftalar', 'Sosyal Etkinlikler Politika Belgesi doğrultusunda okul temelli sosyal etkinlikler eylem planının hazırlanması, kulüplerin ve danışman öğretmenlerin belirlenmesi, belirli gün ve haftalara ilişkin görevlendirmeler yapıldı.', 'Kulüp ve sosyal etkinlik görevlendirmeleri okul idaresince duyurulduğu şekilde yürütülecektir.');
      ekle('Okul güvenliği, sağlık ve iş sağlığı ve güvenliği', 'Okul güvenliği, sivil savunma ve acil durum planları, tahliye tatbikatları, iş sağlığı ve güvenliği ile okul sağlığı konularında bilgi verildi.');
      ekle('Destekleme ve yetiştirme kursları, okul-aile iş birliği', 'Destekleme ve yetiştirme kurslarının planlanması, veli toplantılarının zamanı ve okul-aile birliği ile iş birliği konuları görüşüldü.');
    } else if (t === 'ikinci_donem') {
      ekle('Birinci dönemin değerlendirilmesi', 'Birinci dönemde alınan kararların uygulanma durumu, öğrenci başarısı, devam-devamsızlık ve disiplin durumu değerlendirildi.', 'Birinci dönem başarı analizleri zümrelerce incelenerek ikinci dönem planlamasına yansıtılacaktır.');
      ekle('Başarının artırılması için alınacak önlemler', 'Başarısı düşük öğrenciler ve dersler belirlenerek destekleme ve yetiştirme çalışmalarının artırılmasına, velilerle iş birliğinin güçlendirilmesine karar verildi.', 'Başarısı düşük öğrenciler için destekleme çalışmaları yapılacak, velilerle bireysel görüşmeler planlanacaktır.');
      ekle('İkinci dönem ölçme-değerlendirme ve sınav takvimi', 'İkinci dönem ortak yazılı sınav takvimi, performans görevleri ve proje teslim tarihleri görüşüldü.');
      ekle('Sosyal etkinlikler ve belirli gün ve haftalar', 'İkinci dönem kulüp çalışmaları ve belirli gün ve haftalar programı gözden geçirildi.');
      ekle('Rehberlik, okul güvenliği ve diğer konular', 'Rehberlik çalışmaları, okul güvenliği ve nöbet uygulamaları hakkında bilgi verildi.');
    } else if (t === 'yil_sonu') {
      ekle('Ders yılının genel değerlendirmesi', 'Ders yılı başında alınan kararların uygulanma durumu ve yıl boyunca yürütülen çalışmalar değerlendirildi.');
      ekle('Öğrenci başarı durumu ve sınıf geçme', 'Ders ve sınıf bazında başarı durumu, sınıf geçme, sorumluluk ve devamsızlık durumları görüşüldü.', 'Başarı analiz raporları gelecek yıl planlamasında kullanılacaktır.');
      ekle('Ödül ve belgeler, disiplin', 'Teşekkür, takdir ve onur belgesi alacak öğrenciler ile yıl içindeki disiplin durumları hakkında bilgi verildi.');
      ekle('Sosyal etkinlikler ve kulüp raporları', 'Kulüp ve sosyal etkinlik yıl sonu raporlarının teslim edilmesi ve e-Okul Sosyal Etkinlik Modülü’ne işlenmesi istendi.', 'Kulüp yıl sonu raporları belirtilen tarihe kadar okul idaresine teslim edilecektir.');
      ekle('Gelecek ders yılı hazırlıkları ve ihtiyaçlar', 'Gelecek ders yılı için araç-gereç, derslik ve eğitim ihtiyaçları ile mesleki çalışma dönemi programı görüşüldü.');
      ekle('Yaz tatili önerileri ve sene sonu işlemleri', 'Karne dağıtımı, sınıf defterleri ve e-Okul işlemlerinin tamamlanması, öğrencilere yaz tatili önerileri konuşuldu.', 'Sene sonu evrak ve e-Okul işlemleri karne gününden önce tamamlanacaktır.');
    } else {
      ekle('Toplantının yapılma nedeni', 'Kurul, okul müdürünün çağrısıyla olağanüstü olarak toplandı; toplantının gerekçesi hakkında bilgi verildi.');
    }
    if (v.not) ekle('Diğer konular', v.not);
    ekle('Dilek ve temenniler, kapanış', 'Öğretmenlerin görüş ve önerileri alındı; toplantı okul müdürünün iyi dilekleriyle sona erdi.');
    return { gundem: g, gorusmeler: gr, kararlar: k };
  }
  function egzersizIcerik(v) {
    var e = v.egz || 'Egzersiz', sp = v.alan === 'Spor';
    var p = [['Ekim', 'Tanışma, seviye belirleme; çalışma kurallarının ve güvenlik önlemlerinin anlatılması; ' + e + ' hakkında temel bilgiler', 'Öğrencinin hazır bulunuşluğunu belirlemek'], ['Kasım', sp ? 'Isınma ve temel motorik beceriler; temel tekniklerin öğretimi (1)' : 'Temel bilgi ve becerilerin öğretimi (1); uygulamalı çalışmalar', 'Temel becerileri kazanmak'], ['Aralık', sp ? 'Temel tekniklerin öğretimi (2); kuralların uygulamalı anlatımı' : 'Temel bilgi ve becerilerin öğretimi (2); küçük grup çalışmaları', 'Temel becerileri pekiştirmek'],
      ['Ocak', 'Birinci dönem çalışmalarının tekrarı ve değerlendirilmesi', 'Gelişimi değerlendirmek'], ['Şubat', sp ? 'İleri teknik çalışmalar; takım/grup içi iş birliği' : 'İleri düzey çalışmalar; ürün/çalışma hazırlığı', 'Becerileri geliştirmek'], ['Mart', sp ? 'Oyun ve müsabaka uygulamaları; taktik çalışmaları' : 'Proje/ürün çalışmalarının sürdürülmesi', 'Kazanılan becerileri uygulamak'], ['Nisan', sp ? 'Okul içi turnuva/gösteri hazırlığı; centilmenlik ve fair-play' : 'Sergi, gösteri ya da yarışma hazırlığı', 'Öğrenilenleri sergilemek'], ['Mayıs', sp ? 'Okul içi turnuva/gösteri; yıl sonu değerlendirmesi' : 'Sergi/gösteri; yıl sonu değerlendirmesi', 'Yılı değerlendirmek']];
    return { aylar: p.map(function (x) { return { ay: x[0], etkinlikler: [x[1]], hedef: x[2], yontem: sp ? 'Gösterip yaptırma, oyun, uygulama' : 'Anlatım, gösterip yaptırma, uygulama' }; }) };
  }
  function geziIcerik(v) {
    return { amac: 'Öğrencilerin ' + (v.gezi || 'gezilecek yer') + ' hakkında yerinde gözlem yaparak derslerde öğrendiklerini pekiştirmeleri, kültürel ve tarihî değerlerimizi tanımaları, birlikte hareket etme ve sorumluluk alma becerilerini geliştirmeleri amaçlanmaktadır.',
      kazanimlar: ['Gezilen yerin tarihî, kültürel ya da bilimsel önemini açıklar.', 'Gözlemlerini not alarak paylaşır.', 'Grup içinde kurallara uyar, sorumluluk alır.', 'Ortak kullanım alanlarını ve çevreyi korur.'],
      guvenlik: ['Gezi için velilerden yazılı izin alınacak, izni olmayan öğrenci geziye katılmayacaktır.', 'Ulaşım aracının ve sürücünün belgeleri ile araç sigortası kontrol edilecektir.', 'Öğrencilerin sağlık durumları ve kullandıkları ilaçlar önceden öğrenilecek; ilk yardım çantası bulundurulacaktır.', 'Öğrenciler gruplara ayrılacak, her grup bir öğretmenin sorumluluğunda olacak; hareket ve dönüşte yoklama alınacaktır.', 'Veli, öğretmen ve okul idaresinin iletişim numaraları gezi listesinde bulunacaktır.', 'Gezi sonunda öğrenciler gözlemlerini rapor ya da sunum hâlinde paylaşacaktır.'] };
  }
  function kulupAylikIcerik(v) {
    var l = satirlar(v.yapilanlar), ay = v.ay || 'Ekim';
    if (!l.length) { var p = kulupPlanIcerik(v).aylar.filter(function (a) { return a.ay === ay; })[0]; l = (p ? p.etkinlikler : ['Kulüp toplantısı yapıldı.']).map(function (x) { return '…/…/20… - ' + x; }); }
    return { yapilanlar: l, degerlendirme: ay + ' ayında planlanan kulüp çalışmaları yapılmış, öğrencilerin etkinliklere katılımı ve ilgisi olumlu bulunmuştur. Etkinlikler e-Okul Sosyal Etkinlik Modülü’ne işlenmiştir.' };
  }
  var DIL = { izin: '…………… nedeniyle …/…/20… tarihinde (… gün) izinli sayılmam', rapor: 'rahatsızlığım nedeniyle tarafıma verilen ekteki sağlık raporu süresince görevime gelemeyeceğimi; raporumun', nobet: '…/…/20… tarihindeki nöbet görevimin, aynı gün nöbeti bulunmayan …………… öğretmeni ile değiştirilmesi', belge: 'kurumunuzdaki görev sürem ve görev yerimi gösterir hizmet belgesinin tarafıma verilmesi', ders: '…………… görevinde görevlendirilmem', genel: '……………' };
  function dilekceIcerik(v) {
    var a = String(v.aciklama || '').trim(), t = v.dtur || 'izin';
    var giris = 'Okulunuzda ' + (v.gorev || '……………') + ' olarak görev yapmaktayım. ';
    var m = t === 'rapor' ? giris + (a ? a + ' ' : '') + 'Ekte sunduğum sağlık raporu süresince görevime gelemeyeceğim. Raporumun işleme alınması hususunda gereğini bilgilerinize arz ederim.'
      : t === 'genel' ? (a || giris + '……………') + (/arz ederim\.?$/.test(a) ? '' : ' Gereğini bilgilerinize arz ederim.')
      : giris + (a ? a.replace(/\.?$/, '. ') + 'Bu nedenle ' : '') + (t === 'izin' ? (a ? 'belirttiğim tarihlerde izinli sayılmam' : DIL.izin) : DIL[t]) + ' hususunda gereğini bilgilerinize arz ederim.';
    return { metin: m };
  }
  /* ---------- OYS: MEB senaryoları ---------- */
  var SNR = (window.OSC_SNR && window.OSC_SNR.tablolar) || [];
  function oyDurum(v) { var Q = v.oys || (v.oys = {}); Q.sorular = Q.sorular || []; Q.ogr = Q.ogr || []; Q.donem = Q.donem || 1; Q.yazili = Q.yazili || 1; return Q; }
  function oyTablo(Q) { return SNR.filter(function (t) { return +t.sinif === +Q.sinif && t.ders === Q.ders && (+t.donem || 1) === +Q.donem; })[0]; }
  function ciktiTemiz(c) { return String(c || '').replace(/([a-zçğıöşü])a\)\s+[A-ZÇĞİÖŞÜ][\s\S]*$/, '$1').trim(); }
  function oyPlan(Q) {
    var t = oyTablo(Q), l = []; if (!t || !Q.senaryo) return l;
    (t.satirlar || []).forEach(function (r) { var n = ((r.sayi || {})[Q.yazili] || {})[Q.senaryo] || 0; for (var i = 0; i < n; i++) l.push({ kod: r.kod, cikti: ciktiTemiz(r.cikti), puan: 0 }); });
    oyDagit(l); return l;
  }
  function oyDagit(l) { var n = l.length; if (!n) return; var b = Math.floor(100 / n), k = 100 - b * n; l.forEach(function (q, i) { q.puan = b + (i < k ? 1 : 0); }); }
  function oyHesap(Q) {
    var n = Q.sorular.length, top = Q.ogr.map(function (o) { var d = (o.p || []).slice(0, n).filter(function (x) { return x != null && x !== ''; }); return d.length ? d.reduce(function (a, c) { return a + +c; }, 0) : null; });
    var ort = Q.sorular.map(function (_, i) { var s = 0, c = 0; Q.ogr.forEach(function (o) { var x = (o.p || [])[i]; if (x != null && x !== '') { s += +x; c++; } }); return c ? s / c : null; });
    var dt = top.filter(function (x) { return x != null; });
    return { top: top, ort: ort, dolu: dt.length, sinifOrt: dt.length ? dt.reduce(function (a, c) { return a + c; }, 0) / dt.length : 0, toplamPuan: Q.sorular.reduce(function (a, q) { return a + (+q.puan || 0); }, 0) };
  }
  /** e-Okul'dan (ya da Excel'den) kopyalanan öğrenci listesi: sıra no, okul no, ad, soyad, cinsiyet… */
  function eokulAyristir(metin) {
    var o = [];
    String(metin || '').split(/\r?\n/).forEach(function (l) {
      l = l.trim(); if (!l || /öğrenci\s*no|adı\s*soyadı|^s\.?\s*no/i.test(l)) return;
      var p = l.split(/\t+/).map(function (x) { return x.trim(); }).filter(Boolean); if (p.length < 2) p = l.split(/\s+/);
      var say = p.filter(function (x) { return /^\d+$/.test(x); }), ad = p.filter(function (x) { return !/^\d+$/.test(x) && !/^(erkek|kız|kiz|e|k)$/i.test(x) && !/^\d{1,2}[\/.]\d{1,2}/.test(x) && !/^[0-9]+\/[A-ZÇĞİÖŞÜ]$/i.test(x); });
      if (!ad.length) return;
      o.push({ no: say.length > 1 ? say[1] : (say[0] || ''), ad: ad.join(' ').replace(/\s+/g, ' '), p: [] });
    });
    return o;
  }
  function oyFormHtml() {
    var Q = oyDurum(S.veri), sinif = SNR.map(function (t) { return +t.sinif; }).filter(function (x, i, a) { return a.indexOf(x) === i; }).sort(function (a, b) { return a - b; });
    var dersler = SNR.filter(function (t) { return +t.sinif === +Q.sinif; }).map(function (t) { return t.ders; }).filter(function (x, i, a) { return a.indexOf(x) === i; }).sort(function (a, b) { return a.localeCompare(b, 'tr'); });
    var donemler = SNR.filter(function (t) { return +t.sinif === +Q.sinif && t.ders === Q.ders; }).map(function (t) { return +t.donem || 1; }).sort();
    var t = oyTablo(Q), sen = t && t.senaryolar ? (t.senaryolar[Q.yazili] || []) : [];
    var opt = function (l, v, bos) { return '<option value="">' + bos + '</option>' + l.map(function (x) { x = Array.isArray(x) ? x : [x, x]; return '<option value="' + esc(x[0]) + '"' + (String(v) === String(x[0]) ? ' selected' : '') + '>' + esc(x[1]) + '</option>'; }).join(''); };
    var cikt = t ? (t.satirlar || []).map(function (r) { return r.kod + '|' + ciktiTemiz(r.cikti); }) : [];
    var n = Q.sorular.length, H = oyHesap(Q);
    var h = '<div class="ev-oys"><h3>1. Senaryo ve soru planı</h3><div class="ev-form">' +
      '<label><span>Sınıf</span><select data-oy="sinif">' + opt(sinif.map(function (x) { return [x, x + '. sınıf']; }), Q.sinif, 'Seçin…') + '</select></label>' +
      '<label><span>Ders</span><select data-oy="ders">' + opt(dersler, Q.ders, Q.sinif ? 'Seçin…' : 'Önce sınıf') + '</select></label>' +
      '<label><span>Dönem</span><select data-oy="donem">' + opt((donemler.length ? donemler : [1, 2]).map(function (x) { return [x, x + '. dönem']; }), Q.donem, 'Seçin…') + '</select></label>' +
      '<label><span>Yazılı</span><select data-oy="yazili">' + opt([[1, '1. ortak yazılı'], [2, '2. ortak yazılı']], Q.yazili, 'Seçin…') + '</select></label>' +
      '<label><span>MEB senaryosu</span><select data-oy="senaryo">' + opt(sen.map(function (x) { return [x, x + '. senaryo']; }), Q.senaryo, t ? (sen.length ? 'Senaryo seçin…' : 'Bu yazılı için senaryo yok') : (Q.ders && Q.sinif ? 'Bu dönem için senaryo henüz yok' : 'Önce ders seçin')) + '</select></label></div>';
    if (n || Q.senaryo) {
      h += '<div class="ev-oy-plan"><table><tr><th>Soru</th><th>Öğrenme çıktısı</th><th>Puan</th><th></th></tr>' + Q.sorular.map(function (q, i) {
        var deger = q.kod + '|' + q.cikti, l = cikt.slice(); if (q.cikti && l.indexOf(deger) < 0) l.unshift(deger);
        return '<tr><td>' + (i + 1) + '</td><td><select data-q="' + i + '" data-qk="cikti">' + '<option value="">— Çıktı seçin —</option>' + l.map(function (x) { var p = x.split('|'); return '<option value="' + esc(x) + '"' + (x === deger ? ' selected' : '') + '>' + esc((p[0] ? p[0] + ' – ' : '') + p.slice(1).join('|')) + '</option>'; }).join('') + '</select></td><td><input type="number" min="0" max="100" step="1" data-q="' + i + '" data-qk="puan" value="' + esc(q.puan) + '"></td><td><button type="button" class="ev-x" data-qsil="' + i + '" title="Soruyu sil">✕</button></td></tr>';
      }).join('') + '</table><div class="ev-oy-alt"><button type="button" class="ev-btn hafif" id="oy-qekle">＋ Soru ekle</button><button type="button" class="ev-btn hafif" id="oy-dagit">Puanları 100’e eşit dağıt</button><button type="button" class="ev-btn hafif" id="oy-sifirla">Senaryoya geri dön</button><span class="ev-oy-top' + (H.toplamPuan !== 100 ? ' uyari' : '') + '">Toplam: <b id="oy-tp">' + H.toplamPuan + '</b> puan</span></div></div>';
    }
    h += '<h3>2. Öğrenciler ve soru puanları</h3><details class="ev-oy-yap"' + (Q.ogr.length ? '' : ' open') + '><summary>📋 e-Okul’dan öğrenci listesini yapıştır</summary><p class="ev-not">e-Okul’da sınıf listesini (ya da Excel listesini) seçip kopyalayın ve aşağıya yapıştırın. Okul numarası ve ad soyad otomatik ayrılır.</p><textarea id="oy-yapistir" rows="5" placeholder="1&#9;123&#9;ALİ&#9;YILMAZ&#9;Erkek&#10;2&#9;145&#9;ECE&#9;KAYA&#9;Kız"></textarea><button type="button" class="ev-btn" id="oy-aktar">Listeye ekle</button></details>';
    h += '<div class="ev-oy-izgara"><table><tr><th>No</th><th>Adı soyadı</th>' + Q.sorular.map(function (q, i) { return '<th title="' + esc(q.cikti || '') + '">' + (i + 1) + '<small>' + esc(q.puan || '') + '</small></th>'; }).join('') + '<th>Toplam</th><th></th></tr>' +
      Q.ogr.map(function (o, k) { return '<tr><td><input data-o="' + k + '" data-ok="no" value="' + esc(o.no) + '" class="no"></td><td><input data-o="' + k + '" data-ok="ad" value="' + esc(o.ad) + '" class="ad" placeholder="Ad Soyad"></td>' + Q.sorular.map(function (q, j) { var x = (o.p || [])[j]; return '<td><input type="number" min="0" max="' + esc(q.puan || 100) + '" data-o="' + k + '" data-p="' + j + '" value="' + (x == null ? '' : esc(x)) + '" class="pn"></td>'; }).join('') + '<td class="tp" id="oy-t' + k + '">' + (H.top[k] == null ? '' : H.top[k]) + '</td><td><button type="button" class="ev-x" data-osil="' + k + '" title="Öğrenciyi sil">✕</button></td></tr>'; }).join('') +
      '</table></div><div class="ev-oy-alt"><button type="button" class="ev-btn hafif" id="oy-oekle">＋ Öğrenci ekle</button>' + (Q.ogr.length ? '<button type="button" class="ev-btn hafif" id="oy-otemiz">Listeyi temizle</button>' : '') + '<span class="ev-not" style="margin:0">Puan kutularında Enter ile sonraki kutuya geçebilirsiniz. Puan girmezseniz elle doldurulacak boş çizelge çıkar.</span></div></div>';
    return h;
  }
  function oyBagla() {
    var Q = oyDurum(S.veri), yeni = function () { sakla(); vForm(); };
    kok.querySelectorAll('[data-oy]').forEach(function (e) { e.onchange = function () {
      var k = e.dataset.oy; Q[k] = /sinif|donem|yazili|senaryo/.test(k) ? (+e.value || '') : e.value;
      if (k === 'sinif') { Q.ders = ''; Q.senaryo = ''; } if (k === 'ders' || k === 'donem' || k === 'yazili') Q.senaryo = '';
      if (k === 'ders' && Q.ders) { var dn = SNR.filter(function (t) { return +t.sinif === +Q.sinif && t.ders === Q.ders; }).map(function (t) { return +t.donem || 1; }); if (dn.indexOf(+Q.donem) < 0) Q.donem = dn.sort().pop() || 1; }
      if (k !== 'sinif' && Q.senaryo) { Q.sorular = oyPlan(Q); Q.duzen = 0; } else if (!Q.senaryo) Q.sorular = Q.sorular.length && k === 'senaryo' ? Q.sorular : [];
      yeni(); }; });
    kok.querySelectorAll('[data-q]').forEach(function (e) {
      var q = Q.sorular[+e.dataset.q];
      e.oninput = function () { if (e.dataset.qk !== 'puan') return; q.puan = Math.max(0, +e.value || 0); Q.duzen = 1; sakla(); var tp = oyHesap(Q).toplamPuan, el = document.getElementById('oy-tp'); el.textContent = tp; el.parentNode.classList.toggle('uyari', tp !== 100); };
      e.onchange = function () { if (e.dataset.qk !== 'puan') { var p = e.value.split('|'); q.kod = p[0]; q.cikti = p.slice(1).join('|'); } Q.duzen = 1; yeni(); };
    });
    kok.querySelectorAll('[data-qsil]').forEach(function (b) { b.onclick = function () { var i = +b.dataset.qsil; Q.sorular.splice(i, 1); Q.ogr.forEach(function (o) { (o.p || []).splice(i, 1); }); Q.duzen = 1; yeni(); }; });
    var x = function (id, f) { var e = document.getElementById(id); if (e) e.onclick = f; };
    x('oy-qekle', function () { Q.sorular.push({ kod: '', cikti: '', puan: 0 }); Q.duzen = 1; yeni(); });
    x('oy-dagit', function () { oyDagit(Q.sorular); yeni(); });
    x('oy-sifirla', function () { Q.sorular = oyPlan(Q); Q.duzen = 0; yeni(); });
    x('oy-oekle', function () { Q.ogr.push({ no: '', ad: '', p: [] }); yeni(); setTimeout(function () { var l = kok.querySelectorAll('input.ad'); if (l.length) l[l.length - 1].focus(); }, 30); });
    x('oy-otemiz', function () { if (confirm('Öğrenci listesi ve girilen puanlar silinsin mi?')) { Q.ogr = []; yeni(); } });
    x('oy-aktar', function () { var l = eokulAyristir(document.getElementById('oy-yapistir').value); if (!l.length) return bildir('Listede öğrenci bulunamadı. Satırları e-Okul’dan kopyaladığınızdan emin olun.', 'hata'); var var_ = {}; Q.ogr.forEach(function (o) { var_[o.no + '|' + o.ad] = 1; }); l.forEach(function (o) { if (!var_[o.no + '|' + o.ad]) Q.ogr.push(o); }); bildir(l.length + ' öğrenci eklendi.', 'ok'); yeni(); });
    kok.querySelectorAll('[data-osil]').forEach(function (b) { b.onclick = function () { Q.ogr.splice(+b.dataset.osil, 1); yeni(); }; });
    kok.querySelectorAll('[data-o]').forEach(function (e) {
      e.oninput = function () {
        var o = Q.ogr[+e.dataset.o];
        if (e.dataset.p != null) { var j = +e.dataset.p, mx = +(Q.sorular[j] || {}).puan || 100; o.p = o.p || []; if (e.value === '') o.p[j] = null; else { var v = Math.max(0, +e.value); if (v > mx) { v = mx; e.value = mx; bildir((j + 1) + '. sorunun puanı en fazla ' + mx + ' olabilir.', 'hata'); } o.p[j] = v; } var tt = oyHesap(Q).top[+e.dataset.o]; document.getElementById('oy-t' + e.dataset.o).textContent = tt == null ? '' : tt; }
        else o[e.dataset.ok] = e.value;
        sakla();
      };
      e.onkeydown = function (ev) { if (ev.key !== 'Enter') return; ev.preventDefault(); var l = [].slice.call(kok.querySelectorAll('.ev-oy-izgara input')), i = l.indexOf(e); if (l[i + 1]) { l[i + 1].focus(); l[i + 1].select && l[i + 1].select(); } };
    });
  }
  var BAYRAM = /Bayram/, ANMA = /Anma|Şehit|Gaziler|15 Temmuz|İstiklâl Marşı/;
  var KONUSMA = {
    '10 Kasım Atatürk’ü Anma Günü': 'Bugün, Türkiye Cumhuriyeti’nin kurucusu Gazi Mustafa Kemal Atatürk’ü aramızdan ayrılışının yıl dönümünde saygı, minnet ve özlemle anıyoruz. Atatürk, Millî Mücadele’yi önderliğiyle zafere ulaştırmış; Cumhuriyet’i kurarak egemenliğin kayıtsız şartsız milletin olduğu bir devlet düzeni oluşturmuştur. Eğitimden hukuka, ekonomiden sanata kadar her alanda gerçekleştirdiği inkılaplarla milletimizi çağdaş uygarlık düzeyinin üzerine çıkarma hedefini göstermiştir. O, gençliğe ve öğretmenlere büyük önem vermiş, Cumhuriyet’i gençlere emanet etmiştir. Bizlere düşen görev; onun ilke ve inkılaplarını, bilime ve akla verdiği değeri iyi anlamak, çok çalışarak bu emaneti daha da yükseltmektir. Ulu Önder’i ve tüm silah arkadaşlarını rahmet ve saygıyla anıyoruz.',
    'Cumhuriyet Bayramı': '29 Ekim 1923’te Türkiye Büyük Millet Meclisinin kabul ettiği kararla devletimizin yönetim şekli Cumhuriyet olarak ilan edildi. Cumhuriyet, milletin kendi kaderini kendisinin belirlediği, egemenliğin kayıtsız şartsız millete ait olduğu yönetim biçimidir. Millî Mücadele’nin ardından kazanılan bu büyük başarı; eğitimde, hukukta ve toplumsal hayatta pek çok yeniliğin de kapısını açmıştır. Bugün Cumhuriyet’imizin yeni yaşını kutlarken başta Gazi Mustafa Kemal Atatürk olmak üzere bu vatan için mücadele eden herkesi minnetle anıyor; Cumhuriyet’imizi bilimle, çalışkanlıkla ve birlik içinde yaşatma sözümüzü yeniliyoruz.',
    'Ulusal Egemenlik ve Çocuk Bayramı': '23 Nisan 1920’de Türkiye Büyük Millet Meclisi Ankara’da açıldı ve millet egemenliğine dayanan yeni bir dönem başladı. Gazi Mustafa Kemal Atatürk, bu anlamlı günü geleceğin teminatı olarak gördüğü çocuklara armağan etti. Bugün dünyanın dört bir yanındaki çocukları barış ve kardeşlik içinde selamlıyor, egemenliğin kayıtsız şartsız milletin olduğu bilinciyle bayramımızı coşkuyla kutluyoruz.',
    'Atatürk’ü Anma, Gençlik ve Spor Bayramı': '19 Mayıs 1919’da Mustafa Kemal Paşa’nın Samsun’a çıkışıyla Millî Mücadele başladı. Bu tarih, işgallere karşı milletimizin bağımsızlık iradesinin ateşlendiği gündür. Atatürk, bu günü Cumhuriyet’i emanet ettiği gençliğe armağan etmiştir. Bizler de sağlıklı, sporla ve bilimle yetişen, vatanına bağlı gençler olarak bu emanete layık olmaya çalışacağız.',
    'Çanakkale Zaferi ve Şehitleri Anma Günü': '18 Mart 1915’te Çanakkale Boğazı’nı geçmeye çalışan düşman donanması büyük bir yenilgiye uğratıldı. Kara savaşlarıyla devam eden Çanakkale Savaşları, milletimizin vatan sevgisinin ve fedakârlığının en güzel örneklerinden biri oldu. Bu topraklar için canını veren tüm şehitlerimizi rahmetle, gazilerimizi minnetle anıyoruz.',
    'İstiklâl Marşı’nın Kabulü ve Mehmet Akif Ersoy’u Anma Günü': 'İstiklâl Marşı, 12 Mart 1921’de Türkiye Büyük Millet Meclisi tarafından millî marşımız olarak kabul edildi. Mehmet Akif Ersoy’un kaleme aldığı marşımız, Millî Mücadele yıllarında milletimizin bağımsızlık inancını ve fedakârlığını dile getirir. Millî marşımızı saygıyla okumak ve anlamını kavramak hepimizin görevidir. Şairimiz Mehmet Akif Ersoy’u rahmetle anıyoruz.',
    'Öğretmenler Günü': 'Başöğretmen Gazi Mustafa Kemal Atatürk’ün Millet Mektepleri Başöğretmenliğini kabul ettiği 24 Kasım, Öğretmenler Günü olarak kutlanmaktadır. Öğretmenler; bilgiyi, değerleri ve geleceğe dair umudu öğrencilerine aktaran fedakâr insanlardır. Bu anlamlı günde tüm öğretmenlerimize teşekkür ediyor, hayatını kaybetmiş öğretmenlerimizi rahmetle anıyoruz.',
    'Gaziler Günü': '19 Eylül, Sakarya Meydan Muharebesi’nin ardından Türkiye Büyük Millet Meclisinin Mustafa Kemal Paşa’ya Gazilik unvanı ve Mareşal rütbesi verdiği tarihtir. Bu gün, vatanımızın bağımsızlığı ve bütünlüğü için mücadele eden tüm gazilerimizi şükranla andığımız gündür. Gazilerimize minnet borcumuzu her zaman hatırlayacağız.',
    '15 Temmuz Demokrasi ve Millî Birlik Günü anma etkinlikleri': '15 Temmuz 2016 gecesi milletimiz, demokrasiye ve millî iradeye yönelik darbe girişimine karşı birlik içinde direnmiştir. O gece hayatını kaybeden şehitlerimizi rahmetle, gazilerimizi minnetle anıyoruz. Demokrasimize, millî birliğimize ve hukukun üstünlüğüne sahip çıkmak hepimizin ortak sorumluluğudur.'
  };
  function kutlamaIcerik(v) {
    var g = v.gun || 'Belirli gün', an = ANMA.test(g) || g.indexOf('10 Kasım') === 0, by = BAYRAM.test(g), a = [];
    var siir = satirlar(v.siirler), gos = satirlar(v.gosteriler);
    if (g.indexOf('10 Kasım') === 0) a.push('Saat 09.05’te Gazi Mustafa Kemal Atatürk’ün aziz hatırası için saygı duruşu ve ardından İstiklâl Marşı’nın okunması');
    else a.push((an ? 'Saygı duruşu ve İstiklâl Marşı’nın okunması' : 'Saygı duruşu ve İstiklâl Marşı’nın okunması'));
    a.push('Sunucunun açılış konuşması ve programın takdimi' + (v.sunucu ? ' (' + v.sunucu + ')' : ''));
    a.push('Günün anlam ve önemini belirten konuşma' + (v.konusmaci ? ' (' + v.konusmaci + ')' : ''));
    if (g.indexOf('10 Kasım') === 0) a.push('Atatürk’ün hayatı ve eserlerinden kesitler (öğrenci sunumu)');
    if (siir.length) siir.forEach(function (x) { a.push('Şiir: ' + x); }); else a.push('Şiirlerin okunması (görevli öğrenciler)');
    if (gos.length) gos.forEach(function (x) { a.push(x); }); else a.push(an ? 'Günün anlamına uygun müzik dinletisi / kısa belgesel gösterimi' : by ? 'Koro, halk oyunları ve gösteriler' : 'Konuya uygun sunum, drama ya da kısa film gösterimi');
    if (by || g.indexOf('10 Kasım') === 0) a.push('Şiir, kompozisyon ve resim yarışmalarında dereceye giren öğrencilerin açıklanması');
    a.push(an ? 'Kapanış ve anma panosunun ziyaret edilmesi' : 'Kapanış konuşması' + (by ? ' ve bayram coşkusuyla programın sona ermesi' : ''));
    var konusma = KONUSMA[g] || ('Bugün ' + g + ' vesilesiyle bir aradayız. Bu gün, toplum olarak üzerinde durmamız gereken önemli bir konuya dikkat çekmek amacıyla her yıl okullarımızda anılmaktadır. Hepimizin bu konuda bilinçlenmesi, öğrendiklerimizi ailemiz ve çevremizle paylaşması büyük önem taşımaktadır. Programımızı hazırlayan öğrencilerimize ve öğretmenlerimize teşekkür ederiz.');
    return { akis: a, konusma: konusma };
  }
  function varsayilan(id, v) { if (id === 'belirli_gun' && v.program !== 'yillik') return kutlamaIcerik(v); if (id === 'ogr_kurul') return kurulIcerik(v); if (id === 'egzersiz') return egzersizIcerik(v); if (id === 'gezi') return geziIcerik(v); if (id === 'kulup_aylik') return kulupAylikIcerik(v); if (id === 'dilekce') return dilekceIcerik(v); if (id === 'belirli_gun' || id === 'oys') return { bos: 1 }; return id === 'zumre' ? zumreIcerik(v) : id === 'sok' ? sokIcerik(v) : id === 'veli' ? veliIcerik(v) : id === 'bep' ? bepIcerik(v) : id === 'kulup_plan' ? kulupPlanIcerik(v) : id === 'kulup_rapor' ? kulupRaporIcerik(v) : {}; }

  /* ---------------- içerik → belge blokları ---------------- */
  function bloklar(id, v, ic) {
    var Y = v.yil || yil(), O = trUp(v.okul || '………………………… OKULU'), b = [];
    var TB = function (s) { b.push({ t: 'baslik', s: s }); };
    if (id === 'zumre' || id === 'sok' || id === 'veli') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI'); TB(O);
      TB(id === 'zumre' ? trUp((v.ders || '') + ' DERSİ ' + (TOPLANTI[v.toplanti] || '') + ' ZÜMRE ÖĞRETMENLER KURULU TOPLANTI TUTANAĞI') : id === 'sok' ? trUp((v.sube || '') + ' ŞUBE ÖĞRETMENLER KURULU TOPLANTI TUTANAĞI (' + (TOPLANTI[v.toplanti] || '') + ')') : trUp((v.sube || '') + ' SINIFI VELİ TOPLANTISI TUTANAĞI (' + (TOPLANTI[v.toplanti] || '') + ')'));
      b.push({ t: 'bilgi', satirlar: [['Toplantı tarihi', v.tarih || ''], ['Toplantı saati', v.saat || ''], ['Toplantı yeri', v.yer || '']].concat(dayanak(id, v) ? [['Dayanak', dayanak(id, v)]] : [], id === 'zumre' ? [['Zümre başkanı', v.baskan || ''], ['Sınıf seviyeleri', v.siniflar || '']] : id === 'sok' ? [['Sınıf rehber öğretmeni', v.rehber || ''], ['Öğrenci sayısı', v.mevcut || '']] : [['Sınıf öğretmeni', v.ogretmen || ''], ['Katılan veli sayısı', v.katilim || '']]) });
      b.push({ t: 'h', s: 'GÜNDEM MADDELERİ' }); b.push({ t: 'liste', l: ic.gundem || [], yol: 'gundem' });
      b.push({ t: 'h', s: 'GÜNDEM MADDELERİNİN GÖRÜŞÜLMESİ' });
      (ic.gundem || []).forEach(function (g, i) { b.push({ t: 'p', s: (i + 1) + '. ' + g + ': ' + ((ic.gorusmeler || [])[i] || ''), yol: 'gorusmeler.' + i, onek: (i + 1) + '. ' + g + ': ' }); });
      b.push({ t: 'h', s: 'ALINAN KARARLAR' }); b.push({ t: 'liste', l: ic.kararlar || [], yol: 'kararlar' });
      if (id === 'zumre') { var u = satirlar(v.uyeler); if (v.baskan && u.indexOf(v.baskan) < 0) u.unshift(v.baskan); b.push({ t: 'imza', baslik: 'ZÜMRE ÖĞRETMENLERİ', kisiler: u.map(function (a) { return { ad: a, gorev: (v.ders || '') + ' Öğretmeni' + (a === v.baskan ? ' (Zümre Başkanı)' : '') }; }), sutun: 3 }); }
      if (id === 'sok') b.push({ t: 'imza', baslik: 'ŞUBE ÖĞRETMENLERİ', kisiler: satirlar(v.ogretmenler).map(function (a) { var p = a.split(/\s*[-–]\s*/); return p.length > 1 ? { ad: p.slice(1).join(' - '), gorev: p[0] + ' Öğretmeni' } : { ad: a, gorev: '' }; }), sutun: 3 });
      if (id === 'veli') { b.push({ t: 'imza', kisiler: [{ ad: v.ogretmen, gorev: (v.sube || '') + ' Sınıf Öğretmeni' }], sutun: 1 }); }
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü', tarih: v.tarih });
      if (id === 'veli') { b.push({ t: 'sayfa' }); TB(trUp((v.sube || '') + ' SINIFI VELİ TOPLANTISI KATILIM LİSTESİ')); b.push({ t: 'alt', s: (v.tarih || '') }); var sat = []; for (var i = 1; i <= 30; i++) sat.push([String(i), '', '', '']); b.push({ t: 'tablo', bas: ['No', 'Öğrencinin adı soyadı', 'Velinin adı soyadı', 'İmza'], gen: [6, 34, 34, 26], sat: sat }); }
    } else if (id === 'bep') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI'); TB(O); TB('BİREYSELLEŞTİRİLMİŞ EĞİTİM PROGRAMI (BEP)');
      b.push({ t: 'bilgi', satirlar: [['Öğrencinin adı soyadı', v.ogrenci || ''], ['Sınıfı / şubesi', v.sinif || ''], ['Eğitsel tanı / ihtiyaç', v.tani || ''], ['Ders', v.ders || ''], ['BEP başlama – bitiş tarihi', (v.baslama || '') + ' – ' + (v.bitis || '')], ['Dayanak', dayanak(id, v)]] });
      b.push({ t: 'h', s: 'ÖĞRENCİNİN PERFORMANS DÜZEYİ' }); b.push({ t: 'p', s: ic.performans || '', yol: 'performans' });
      b.push({ t: 'h', s: 'UZUN VE KISA DÖNEMLİ AMAÇLAR' });
      b.push({ t: 'tablo', bas: ['Uzun dönemli amaç', 'Kısa dönemli amaçlar', 'Yöntem ve teknikler', 'Materyaller', 'Değerlendirme', 'Başlama / bitiş'], gen: [18, 28, 16, 14, 12, 12], sat: (ic.hedefler || []).map(function (h) { return [h.uda, (h.kda || []).map(function (x, j) { return (j + 1) + '. ' + x; }).join('\n'), h.yontem || '', h.materyal || '', h.degerlendirme || '', (v.baslama || '') + '\n' + (v.bitis || '')]; }), yol: 'hedefler' });
      b.push({ t: 'h', s: 'ÖLÇME VE DEĞERLENDİRMEDE YAPILACAK UYARLAMALAR' }); b.push({ t: 'liste', l: ic.uyarlamalar || [], yol: 'uyarlamalar' });
      b.push({ t: 'h', s: 'AİLE KATILIMI' }); b.push({ t: 'liste', l: ic.aile || [], yol: 'aile' });
      b.push({ t: 'imza', baslik: 'BEP GELİŞTİRME BİRİMİ', kisiler: (satirlar(v.birim).length ? satirlar(v.birim) : ['Okul müdürü / müdür yardımcısı - ', 'Rehber öğretmen - ', 'Sınıf öğretmeni - ', 'Ders öğretmeni - ', 'Veli - ']).map(function (a) { var p = a.split(/\s*[-–]\s*/); return p.length > 1 ? { ad: p.slice(1).join(' - '), gorev: p[0] } : { ad: a, gorev: '' }; }), sutun: 3 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü', tarih: v.baslama });
    } else if (id === 'kulup_plan') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.kulup || 'ÖĞRENCİ KULÜBÜ') + ' YILLIK ÇALIŞMA PLANI'));
      b.push({ t: 'bilgi', satirlar: [['Tema', ic.tema || kulupTema(v.kulup)], ['Okul kademesi', v.kademe || ''], ['Danışman öğretmen(ler)', satirlar(v.danisman).join(', ')], ['Dayanak', dayanak(id, v)]] });
      b.push({ t: 'tablo', bas: ['Ay', 'Planlanan etkinlikler', 'Hedef / kazanım', 'Yöntem', 'Sorumlu'], gen: [8, 46, 22, 14, 10], sat: (ic.aylar || []).map(function (a) { return [a.ay, (a.etkinlikler || []).map(function (x) { return '• ' + x; }).join('\n'), a.hedef || '', a.yontem || '', a.sorumlu || 'Danışman öğretmen ve kulüp üyeleri']; }), yol: 'aylar' });
      if (v.not) b.push({ t: 'p', s: 'Not: ' + v.not });
      b.push({ t: 'imza', kisiler: satirlar(v.danisman).map(function (a) { return { ad: a, gorev: 'Danışman Öğretmen' }; }), sutun: 3 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü' });
    } else if (id === 'kulup_tutanak') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.kulup || 'ÖĞRENCİ KULÜBÜ') + ' İLK TOPLANTI VE GÖREV DAĞILIMI TUTANAĞI'));
      b.push({ t: 'bilgi', satirlar: [['Toplantı tarihi', v.tarih || ''], ['Toplantı yeri', v.yer || ''], ['Tema', kulupTema(v.kulup)], ['Danışman öğretmen(ler)', satirlar(v.danisman).join(', ')]] });
      b.push({ t: 'p', s: (v.tarih || '…/…/20…') + ' tarihinde ' + (v.yer ? v.yer + '’nda ' : '') + (v.kulup || 'kulüp') + ' üyesi öğrenciler danışman öğretmen gözetiminde toplanmıştır. Toplantıda kulübün amacı, yeni kulüp yapısı ve sosyal etkinlik temaları tanıtılmış; kulüp çalışmalarının okul temelli sosyal etkinlikler eylem planıyla uyumlu yürütüleceği açıklanmıştır. Görev dağılımı için aday olan öğrenciler arasından açık oylama yapılmış ve aşağıdaki öğrenciler seçilmiştir. Yıllık çalışma planının üyelerin önerileri alınarak hazırlanmasına karar verilmiştir.' });
      b.push({ t: 'tablo', bas: ['Görev', 'Adı soyadı - sınıfı'], gen: [30, 70], sat: [['Kulüp başkanı', v.baskan || ''], ['Başkan yardımcısı', v.baskanyrd || ''], ['Sekreter', v.sekreter || '']] });
      b.push({ t: 'h', s: 'KULÜP ÜYE LİSTESİ' });
      var u = satirlar(v.uyeler); if (!u.length) for (var j = 0; j < 15; j++) u.push('');
      b.push({ t: 'tablo', bas: ['No', 'Adı soyadı', 'Sınıfı', 'Okul no', 'İmza'], gen: [6, 40, 14, 14, 26], sat: u.map(function (a, i) { var p = a.split(/\s*[-–]\s*/); return [String(i + 1), p[0] || '', p[1] || '', p[2] || '', '']; }) });
      b.push({ t: 'imza', kisiler: satirlar(v.danisman).map(function (a) { return { ad: a, gorev: 'Danışman Öğretmen' }; }).concat([{ ad: (v.baskan || '').split(/\s*[-–]\s*/)[0], gorev: 'Kulüp Başkanı' }]), sutun: 3 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü', tarih: v.tarih });
    } else if (id === 'kulup_rapor') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.kulup || 'ÖĞRENCİ KULÜBÜ') + ' ' + (v.donem || '') + ' ÇALIŞMA RAPORU'));
      b.push({ t: 'bilgi', satirlar: [['Tema', kulupTema(v.kulup)], ['Danışman öğretmen(ler)', satirlar(v.danisman).join(', ')], ['Üye sayısı', v.uye || '']] });
      b.push({ t: 'h', s: 'YAPILAN ÇALIŞMALAR' }); b.push({ t: 'liste', l: ic.yapilanlar || [], yol: 'yapilanlar' });
      b.push({ t: 'h', s: 'GENEL DEĞERLENDİRME' }); b.push({ t: 'p', s: ic.degerlendirme || '', yol: 'degerlendirme' });
      b.push({ t: 'h', s: 'ÖNERİLER' }); b.push({ t: 'liste', l: ic.oneriler || [], yol: 'oneriler' });
      b.push({ t: 'imza', kisiler: satirlar(v.danisman).map(function (a) { return { ad: a, gorev: 'Danışman Öğretmen' }; }), sutun: 3 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü' });
    } else if (id === 'kulup_aylik') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.kulup || 'ÖĞRENCİ KULÜBÜ') + ' ' + (v.ay || '') + ' AYI ÇALIŞMA RAPORU'));
      b.push({ t: 'bilgi', satirlar: [['Tema', kulupTema(v.kulup)], ['Danışman öğretmen(ler)', satirlar(v.danisman).join(', ')], ['Katılan öğrenci sayısı', v.uye || ''], ['Dayanak', dayanak(id, v)]] });
      b.push({ t: 'h', s: 'YAPILAN ÇALIŞMALAR' });
      b.push({ t: 'tablo', bas: ['No', 'Tarih', 'Yapılan etkinlik', 'Sonuç / kazanım'], gen: [6, 16, 52, 26], sat: (ic.yapilanlar || []).map(function (x, i) { var p = String(x).split(/\s+[-–]\s+/); return [String(i + 1), p.length > 1 ? p[0] : '', p.length > 1 ? p.slice(1).join(' - ') : x, 'Etkinlik amacına ulaşmıştır.']; }) });
      b.push({ t: 'h', s: 'DEĞERLENDİRME' }); b.push({ t: 'p', s: ic.degerlendirme || '', yol: 'degerlendirme' });
      b.push({ t: 'imza', kisiler: satirlar(v.danisman).map(function (a) { return { ad: a, gorev: 'Danışman Öğretmen' }; }), sutun: 3 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü' });
    } else if (id === 'ogr_kurul') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI'); TB(O); TB(trUp(({ donem_basi: 'Ders yılı başı', ikinci_donem: 'İkinci dönem başı', yil_sonu: 'Ders yılı sonu', donem_ara: 'Olağanüstü' })[v.toplanti] || '') + ' ÖĞRETMENLER KURULU TOPLANTI TUTANAĞI');
      b.push({ t: 'bilgi', satirlar: [['Toplantı tarihi', v.tarih || ''], ['Toplantı saati', v.saat || ''], ['Toplantı yeri', v.yer || ''], ['Kurul başkanı', v.mudur || ''], ['Yazman', v.yazman || ''], ['Dayanak', dayanak(id, v)]] });
      b.push({ t: 'h', s: 'GÜNDEM MADDELERİ' }); b.push({ t: 'liste', l: ic.gundem || [], yol: 'gundem' });
      b.push({ t: 'h', s: 'GÜNDEM MADDELERİNİN GÖRÜŞÜLMESİ' });
      (ic.gundem || []).forEach(function (g, i) { b.push({ t: 'p', s: (i + 1) + '. ' + g + ': ' + ((ic.gorusmeler || [])[i] || ''), yol: 'gorusmeler.' + i, onek: (i + 1) + '. ' + g + ': ' }); });
      b.push({ t: 'h', s: 'ALINAN KARARLAR' }); b.push({ t: 'liste', l: ic.kararlar || [], yol: 'kararlar' });
      b.push({ t: 'imza', kisiler: [{ ad: v.yazman, gorev: 'Yazman' }, { ad: v.mudur, gorev: 'Okul Müdürü (Kurul Başkanı)' }], sutun: 2 });
      b.push({ t: 'sayfa' }); TB('ÖĞRETMENLER KURULU KATILIM LİSTESİ'); b.push({ t: 'alt', s: v.tarih || '' });
      var ku = satirlar(v.uyeler); if (!ku.length) for (var q = 0; q < 25; q++) ku.push('');
      b.push({ t: 'tablo', bas: ['No', 'Adı soyadı', 'Görevi / branşı', 'İmza'], gen: [6, 36, 32, 26], sat: ku.map(function (a, i) { var p = a.split(/\s*[-–]\s*/); return [String(i + 1), p.length > 1 ? p.slice(1).join(' - ') : a, p.length > 1 ? p[0] : '', '']; }) });
    } else if (id === 'egzersiz') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.egz || '') + ' DERS DIŞI EGZERSİZ ÇALIŞMA PLANI'));
      b.push({ t: 'bilgi', satirlar: [['Egzersiz alanı', v.alan || ''], ['Egzersiz öğretmeni', v.ogretmen || ''], ['Öğrenci grubu', v.grup || ''], ['Çalışma gün ve saatleri', v.gunsaat || ''], ['Çalışma yeri', v.yer || ''], ['Haftalık süre', v.sure ? v.sure + ' ders saati' : '']] });
      b.push({ t: 'tablo', bas: ['Ay', 'Çalışma konuları', 'Hedef', 'Yöntem', 'Süre'], gen: [8, 50, 18, 16, 8], sat: (ic.aylar || []).map(function (a) { return [a.ay, (a.etkinlikler || []).join('\n'), a.hedef || '', a.yontem || '', v.sure ? v.sure * 4 + ' saat' : '']; }), yol: 'aylar' });
      var eo = satirlar(v.ogrenciler); if (eo.length) { b.push({ t: 'h', s: 'EGZERSİZE KATILAN ÖĞRENCİLER' }); b.push({ t: 'tablo', bas: ['No', 'Adı soyadı', 'Sınıfı', 'Okul no'], gen: [8, 52, 20, 20], sat: eo.map(function (a, i) { var p = a.split(/\s*[-–]\s*/); return [String(i + 1), p[0] || '', p[1] || '', p[2] || '']; }) }); }
      b.push({ t: 'imza', kisiler: [{ ad: v.ogretmen, gorev: 'Egzersiz Öğretmeni' }], sutun: 1 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü' });
    } else if (id === 'belirli_gun' && v.program !== 'yillik') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI'); TB(O); TB(trUp(v.gun || '') + (/Anma/.test(v.gun || '') ? ' PROGRAMI' : ANMA.test(v.gun || '') ? ' ANMA PROGRAMI' : ' KUTLAMA PROGRAMI'));
      b.push({ t: 'bilgi', satirlar: [['Tarih', v.tarih || ''], ['Saat', v.saat || ''], ['Yer', v.yer || ''], ['Sunucu(lar)', v.sunucu || ''], ['Sorumlu öğretmenler', satirlar(v.ogretmenler).join(', ')]] });
      b.push({ t: 'h', s: 'PROGRAM AKIŞI' }); b.push({ t: 'liste', l: ic.akis || [], yol: 'akis' });
      b.push({ t: 'h', s: 'GÜNÜN ANLAM VE ÖNEMİ (KONUŞMA METNİ)' }); b.push({ t: 'p', s: ic.konusma || '', yol: 'konusma' });
      b.push({ t: 'imza', baslik: 'PROGRAMDAN SORUMLU ÖĞRETMENLER', kisiler: satirlar(v.ogretmenler).map(function (a) { return { ad: a, gorev: 'Öğretmen' }; }), sutun: 3 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü', tarih: v.tarih });
    } else if (id === 'belirli_gun') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.kapsam === 'Okul geneli' ? '' : (v.sube || '') + ' ') + 'BELİRLİ GÜN VE HAFTALAR PROGRAMI'));
      var sec = Array.isArray(v.secim) ? v.secim : BGH.map(function (x) { return x[1]; });
      b.push({ t: 'tablo', bas: ['No', 'Tarih', 'Belirli gün / hafta', 'Yapılacak etkinlikler', 'Sorumlu', 'Yapıldı'], gen: [5, 17, 25, 33, 12, 8], sat: BGH.filter(function (x) { return sec.indexOf(x[1]) >= 0; }).map(function (x, i) { return [String(i + 1), x[0], x[1], bghEtkinlik(x[1]), v.sorumlu || '', '']; }) });
      b.push({ t: 'p', s: 'Not: Tarihler Bakanlıkça yayımlanan güncel belirli gün ve haftalar listesine ve okulun çalışma takvimine göre kontrol edilmelidir.' });
      b.push({ t: 'imza', kisiler: [{ ad: v.sorumlu, gorev: 'Sorumlu Öğretmen' }], sutun: 1 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü' });
    } else if (id === 'gezi') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB('GEZİ PLANI'); b.push({ t: 'alt', s: 'Ek-9' });
      b.push({ t: 'bilgi', satirlar: [['Gezinin adı / gezilecek yer', v.gezi || ''], ['Gezinin türü', v.tur || ''], ['Gezi tarihi', v.tarih || ''], ['Hareket yeri ve saati', v.hareket || ''], ['Dönüş saati', v.donus || ''], ['Ulaşım aracı / plaka', v.ulasim || ''], ['Katılan sınıflar', v.siniflar || ''], ['Öğrenci sayısı', v.sayi || ''], ['Sorumlu öğretmenler', satirlar(v.ogretmenler).join(', ')]] });
      b.push({ t: 'h', s: 'GEZİNİN AMACI' }); b.push({ t: 'p', s: ic.amac || '', yol: 'amac' });
      b.push({ t: 'h', s: 'KAZANIMLAR' }); b.push({ t: 'liste', l: ic.kazanimlar || [], yol: 'kazanimlar' });
      var gp = satirlar(v.program); b.push({ t: 'h', s: 'GEZİ PROGRAMI' }); b.push({ t: 'tablo', bas: ['Saat', 'Yer / etkinlik'], gen: [18, 82], sat: (gp.length ? gp : ['… - Okuldan hareket', '… - Gezi yerine varış ve gezi', '… - Öğle yemeği', '… - Dönüş']).map(function (x) { var p = x.split(/\s*[-–]\s*/); return p.length > 1 ? [p[0], p.slice(1).join(' - ')] : ['', x]; }) });
      b.push({ t: 'h', s: 'ALINACAK GÜVENLİK ÖNLEMLERİ' }); b.push({ t: 'liste', l: ic.guvenlik || [], yol: 'guvenlik' });
      b.push({ t: 'imza', baslik: 'SORUMLU ÖĞRETMENLER', kisiler: satirlar(v.ogretmenler).map(function (a) { return { ad: a, gorev: 'Öğretmen' }; }), sutun: 3 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü' });
      b.push({ t: 'sayfa' }); TB(O + ' MÜDÜRLÜĞÜNE'); b.push({ t: 'bosluk', n: 160 });
      b.push({ t: 'p', s: 'Velisi bulunduğum ……… sınıfı ……… numaralı öğrencim ………………………………’in, okulunuzca ' + (v.tarih || '…/…/20…') + ' tarihinde ' + (v.gezi || '……………') + ' yerine düzenlenecek geziye katılmasına izin veriyorum. Gezi programını ve alınacak önlemleri okudum; öğrencimin gezi süresince öğretmenlerinin uyarılarına uyacağını taahhüt ederim.' });
      b.push({ t: 'p', s: 'Öğrencimin sağlık durumu / kullandığı ilaçlar: ………………………………………………' });
      b.push({ t: 'bosluk', n: 200 }); b.push({ t: 'sag', s: '…/…/20…\nVelinin adı soyadı: ………………………\nİmza: ……………\nTelefon: ………………………' });
      var go = satirlar(v.ogrenciler); if (!go.length) for (var r = 0; r < 25; r++) go.push('');
      b.push({ t: 'sayfa' }); TB('GEZİYE KATILAN ÖĞRENCİ LİSTESİ'); b.push({ t: 'alt', s: (v.gezi || '') + ' – ' + (v.tarih || '') });
      b.push({ t: 'tablo', bas: ['No', 'Adı soyadı', 'Sınıfı', 'Okul no', 'Veli telefonu'], gen: [6, 38, 14, 14, 28], sat: go.map(function (a, i) { var p = a.split(/\s*[-–]\s*/); return [String(i + 1), p[0] || '', p[1] || '', p[2] || '', p[3] || '']; }) });
    } else if (id === 'dilekce') {
      b.push({ t: 'bosluk', n: 600 }); b.push({ t: 'baslik', s: trUp(v.makam || 'OKUL MÜDÜRLÜĞÜNE') }); b.push({ t: 'alt', s: trUp(v.il || '') }); b.push({ t: 'bosluk', n: 300 });
      b.push({ t: 'p', s: '        ' + (ic.metin || ''), yol: 'metin', onek: '        ' }); b.push({ t: 'bosluk', n: 300 });
      b.push({ t: 'sag', s: (v.tarih || '…/…/20…') + '\n\n' + (v.adsoyad || '………………………') + '\n' + (v.gorev || '') + '\nİmza' }); b.push({ t: 'bosluk', n: 600 });
      b.push({ t: 'p', s: 'Adres: ' + (v.adres || '………………………………………') + '\nTelefon: ' + (v.tel || '………………') });
      var ek = satirlar(v.ekler); if (ek.length) { b.push({ t: 'p', s: 'Ekler:' }); b.push({ t: 'liste', l: ek }); }
    } else if (id === 'oys') {
      var Q = oyDurum(v), n = Q.sorular.length, H = oyHesap(Q), bas = ['No', 'Adı soyadı'];
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((Q.ders || '') + ' DERSİ ' + (v.sube || (Q.sinif ? Q.sinif + '. SINIF' : '')) + ' ' + Q.donem + '. DÖNEM ' + Q.yazili + '. ORTAK YAZILI SINAV PUAN ÇİZELGESİ'));
      b.push({ t: 'bilgi', satirlar: [['Sınav tarihi', v.tarih || ''], ['Konu-soru dağılımı', Q.senaryo ? 'MEB ' + Q.senaryo + '. senaryo' + (Q.duzen ? ' (öğretmen düzenlemesiyle)' : '') : 'Öğretmen planı'], ['Soru sayısı / toplam puan', n + ' soru / ' + H.toplamPuan + ' puan'], ['Ders öğretmeni', v.ogretmen || '']] });
      b.push({ t: 'h', s: 'SORU – ÖĞRENME ÇIKTISI EŞLEŞMESİ' });
      b.push({ t: 'tablo', bas: ['Soru', 'Öğrenme çıktısı', 'Puan', 'Ortalama', 'Başarı'], gen: [6, 64, 8, 10, 12], sat: Q.sorular.map(function (q, i) { return [String(i + 1), (q.kod ? q.kod + ' – ' : '') + (q.cikti || ''), String(q.puan || ''), H.ort[i] == null ? '' : H.ort[i].toFixed(1), H.ort[i] == null || !q.puan ? '' : '%' + Math.round(H.ort[i] / q.puan * 100)]; }) });
      for (var si = 1; si <= n; si++) bas.push(String(si)); bas.push('Top.');
      var rows = [['', 'Soru puanı'].concat(Q.sorular.map(function (q) { return String(q.puan || ''); }), [String(H.toplamPuan)])];
      var og = Q.ogr.length ? Q.ogr : (function () { var o = []; for (var z = 0; z < 30; z++) o.push({ no: '', ad: '', p: [] }); return o; })();
      og.forEach(function (o, k) { var r = [o.no || '', o.ad || '']; for (var j = 0; j < n; j++) r.push(o.p && o.p[j] != null ? String(o.p[j]) : ''); r.push(H.top[k] == null ? '' : String(H.top[k])); rows.push(r); });
      if (H.dolu) { rows.push(['', 'Ortalama'].concat(H.ort.map(function (x) { return x == null ? '' : x.toFixed(1); }), [H.sinifOrt.toFixed(1)])); rows.push(['', 'Başarı %'].concat(H.ort.map(function (x, i) { return x == null || !Q.sorular[i].puan ? '' : String(Math.round(x / Q.sorular[i].puan * 100)); }), [''])); }
      b.push({ t: 'sayfa' }); TB('ÖĞRENCİ PUANLARI');
      b.push({ t: 'tablo', bas: bas, gen: [6, 22].concat(Q.sorular.map(function () { return 64 / Math.max(1, n); }), [8]), sat: rows });
      if (H.dolu) {
        var cik = {}; Q.sorular.forEach(function (q, i) { var k = q.kod || q.cikti || ('Soru ' + (i + 1)); if (!cik[k]) cik[k] = { ad: (q.kod ? q.kod + ' – ' : '') + (q.cikti || ''), s: [], al: 0, max: 0 }; cik[k].s.push(i + 1); if (H.ort[i] != null) { cik[k].al += H.ort[i]; cik[k].max += q.puan || 0; } });
        b.push({ t: 'h', s: 'ÖĞRENME ÇIKTILARINA GÖRE BAŞARI' });
        b.push({ t: 'tablo', bas: ['Öğrenme çıktısı', 'Sorular', 'Başarı'], gen: [70, 15, 15], sat: Object.keys(cik).map(function (k) { var c = cik[k]; return [c.ad, c.s.join(', '), c.max ? '%' + Math.round(c.al / c.max * 100) : '']; }) });
        var tp = H.top.filter(function (x) { return x != null; }), gec = tp.filter(function (x) { return x >= 50; }).length;
        b.push({ t: 'bilgi', satirlar: [['Sınava giren öğrenci', String(tp.length)], ['Sınıf ortalaması', H.sinifOrt.toFixed(2)], ['En yüksek / en düşük puan', Math.max.apply(null, tp) + ' / ' + Math.min.apply(null, tp)], ['50 ve üzeri alan', gec + ' öğrenci (%' + Math.round(gec / tp.length * 100) + ')']] });
      }
      b.push({ t: 'imza', kisiler: [{ ad: v.ogretmen, gorev: (Q.ders || '') + ' Öğretmeni' }], sutun: 1 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü', tarih: v.tarih });
    }
    return b;
  }

  /* ---------------- durum ---------------- */
  var S = ls('osc_ev_taslak') || { ekran: 'sec', tur: '', veri: {}, ic: null, id: 0 };
  var ORT = ls('osc_ev_ortak') || {};
  function sakla() { ls('osc_ev_taslak', S); }
  function kayitlar(v) { if (v === undefined) return ls('osc_ev_kayitlar') || []; ls('osc_ev_kayitlar', v.slice(0, 80)); }

  var RK = { form: document.getElementById('osc-ev-rk-form'), yan: document.getElementById('osc-ev-rk-yan') };
  function rkPark() { for (var k in RK) if (RK[k] && kok.contains(RK[k])) kok.parentNode.insertBefore(RK[k], kok.nextSibling); }
  function rkKoy(k, hedef) { if (RK[k] && hedef) hedef.appendChild(RK[k]); }
  function cerceve(ic) {
    rkPark();
    kok.innerHTML = '<header class="ev-hero"><div><span class="ev-et">Öğretmen evrakları</span><h1>Evrakınız <em>dakikalar içinde</em> hazır</h1><p>Kurul ve toplantı tutanakları, BEP planı, sosyal kulüp evrakları, gezi ve egzersiz planları, dilekçe ve puan çizelgeleri. Bilgileri doldurun, taslağı düzenleyin, Word olarak indirin.</p></div><nav><button data-ek="sec" class="' + (S.ekran !== 'liste' ? 'on' : '') + '">✨ Yeni evrak</button><button data-ek="liste" class="' + (S.ekran === 'liste' ? 'on' : '') + '">📁 Evraklarım</button></nav></header>' + ic;
    kok.querySelectorAll('[data-ek]').forEach(function (b) { b.onclick = function () { S.ekran = b.dataset.ek; sakla(); ciz(); }; });
  }
  function ciz() { if (S.ekran === 'liste') return vListe(); if (S.ekran === 'form' && tur(S.tur)) return vForm(); if (S.ekran === 'onizle' && tur(S.tur) && S.ic) return vOnizle(); if (S.ekran !== 'kulup') S.ekran = 'sec'; vSec(); }

  function vSec() {
    var kul = TURLER.filter(function (t) { return t.grup === 'kulup'; }), alt = S.ekran === 'kulup' && kul.length;
    var kart = function (t) { return '<button data-tur="' + t.id + '"><span>' + t.ik + '</span><b>' + esc(t.ad) + '</b><small>' + esc(t.ac) + '</small></button>'; };
    var liste = alt ? kul.map(kart).join('') : TURLER.filter(function (t) { return t.grup !== 'kulup'; }).map(kart).join('') + (kul.length ? '<button data-grup="kulup"><span>🎭</span><b>Sosyal kulüp evrakları</b><small>' + kul.length + ' evrak: yıllık plan, ilk toplantı tutanağı, aylık ve dönem sonu raporlar (48 kulüp, 5 tema)</small></button>' : '');
    cerceve('<section class="ev-kart">' + (alt ? '<div class="ev-ust"><button class="ev-btn hafif" id="ev-geri">← Tüm evraklar</button><h2>🎭 Sosyal kulüp evrakları</h2></div>' : '<h2>Hangi evrakı hazırlayacaksınız?</h2>') + '<div class="ev-turler">' + (liste || '<div class="ev-bos">Şu an kullanılabilir evrak yok.</div>') + '</div><p class="ev-not">🔒 Evraklarınız yalnızca bu tarayıcıda saklanır; öğrenci bilgileri sunucumuza kaydedilmez.</p></section>');
    kok.querySelectorAll('[data-grup]').forEach(function (b) { b.onclick = function () { S.ekran = 'kulup'; sakla(); ciz(); }; });
    var gr = document.getElementById('ev-geri'); if (gr) gr.onclick = function () { S.ekran = 'sec'; sakla(); ciz(); };
    rkKoy('form', kok);
    kok.querySelectorAll('[data-tur]').forEach(function (b) { b.onclick = function () { S.tur = b.dataset.tur; S.veri = {}; S.ic = null; S.id = 0; S.ekran = 'form'; sakla(); ciz(); window.scrollTo({ top: kok.getBoundingClientRect().top + window.pageYOffset - 20, behavior: 'smooth' }); }; });
  }
  function alanHtml(a) {
    var v = S.veri[a.k]; if (v === undefined) { v = ['okul', 'il', 'mudur'].indexOf(a.k) >= 0 && ORT[a.k] ? ORT[a.k] : a.k === 'okul' && C.okul ? C.okul : (typeof a.v === 'function' ? a.v() : (a.v || '')); S.veri[a.k] = v; }
    var g = '';
    if (a.tip === 'secim') { var l = Array.isArray(a.l) ? a.l.map(function (x) { return [x, x]; }) : Object.keys(a.l).map(function (k) { return [k, a.l[k]]; }); g = '<select data-a="' + a.k + '"><option value="">Seçin…</option>' + l.map(function (x) { return '<option value="' + esc(x[0]) + '"' + (String(v) === x[0] ? ' selected' : '') + '>' + esc(x[1]) + '</option>'; }).join('') + '</select>'; }
    else if (a.tip === 'kulup') g = '<select data-a="' + a.k + '"><option value="">Kulüp seçin…</option>' + Object.keys(KULUP).map(function (t) { return '<optgroup label="' + esc(t) + '">' + KULUP[t].map(function (x) { return '<option' + (v === x ? ' selected' : '') + '>' + esc(x) + '</option>'; }).join('') + '</optgroup>'; }).join('') + '</select>';
    else if (a.tip === 'secimler') { if (!Array.isArray(v)) { v = a.l.slice(); S.veri[a.k] = v; } g = '<div class="ev-secimler" data-coklu="' + a.k + '"><div><button type="button" class="ev-ekle" data-hep="1">Tümünü seç</button> · <button type="button" class="ev-ekle" data-hep="0">Hiçbirini seçme</button></div>' + a.l.map(function (x) { return '<label><input type="checkbox" value="' + esc(x) + '"' + (v.indexOf(x) >= 0 ? ' checked' : '') + '> ' + esc(x) + '</label>'; }).join('') + '</div>'; return '<div class="gen ev-coklu"><span>' + esc(a.ad) + '</span>' + g + '</div>'; }
    else if (a.tip === 'uzun') g = '<textarea data-a="' + a.k + '" rows="4" placeholder="' + esc(a.ph || '') + '">' + esc(v) + '</textarea>';
    else g = '<input data-a="' + a.k + '" value="' + esc(v) + '" placeholder="' + esc(a.ph || '') + '">';
    return '<label class="' + (a.tip === 'uzun' ? 'gen' : '') + '"><span>' + esc(a.ad) + '</span>' + g + '</label>';
  }
  function vForm() {
    var t = tur(S.tur), YZ = C.yz && !t.yzYok && !(S.tur === 'belirli_gun' && S.veri.program === 'yillik');
    cerceve('<section class="ev-kart"><div class="ev-ust"><button class="ev-btn hafif" id="ev-geri">← Evrak türleri</button><h2>' + t.ik + ' ' + esc(t.ad) + '</h2></div><div class="ev-form">' + t.alan.filter(function (a) { return !a.kosul || a.kosul(S.veri); }).map(alanHtml).join('') + '</div>' + (t.ozel === 'oys' ? oyFormHtml() : '') +
      '<div class="ev-cubuk"><span>' + (YZ ? '🤖 Yapay zekâ, bilgilerinize göre gündemi, görüşmeleri ve kararları özgün olarak yazar.' : 'Taslak içerik bilgilerinize göre otomatik oluşturulur; dilediğiniz gibi düzenleyebilirsiniz.') + '</span><div>' + (YZ ? '<button class="ev-btn" id="ev-hizli">Hazır taslakla oluştur</button><button class="ev-btn ana" id="ev-yz">🤖 Yapay zekâ ile oluştur</button>' : '<button class="ev-btn ana" id="ev-hizli">Evrakı oluştur →</button>') + '</div></div></section>');
    if (t.ozel === 'oys') oyBagla();
    kok.querySelectorAll('[data-a]').forEach(function (e) { e.oninput = e.onchange = function (ev) { S.veri[e.dataset.a] = e.value; if (ev.type === 'change' && (t.alan.filter(function (a) { return a.k === e.dataset.a; })[0] || {}).yenile) { sakla(); return vForm(); } if (['okul', 'il', 'mudur'].indexOf(e.dataset.a) >= 0) { ORT[e.dataset.a] = e.value; ls('osc_ev_ortak', ORT); } sakla(); }; });
    document.getElementById('ev-geri').onclick = function () { S.ekran = t.grup === 'kulup' ? 'kulup' : 'sec'; sakla(); ciz(); };
    kok.querySelectorAll('[data-coklu]').forEach(function (d) { var k = d.dataset.coklu, guncel = function () { S.veri[k] = [].map.call(d.querySelectorAll('input:checked'), function (i) { return i.value; }); sakla(); }; d.querySelectorAll('input').forEach(function (i) { i.onchange = guncel; }); d.querySelectorAll('[data-hep]').forEach(function (b) { b.onclick = function () { d.querySelectorAll('input').forEach(function (i) { i.checked = b.dataset.hep === '1'; }); guncel(); }; }); });
    rkKoy('form', kok);
    var eksik = function () { var z = { zumre: ['okul', 'ders'], sok: ['okul', 'sube'], veli: ['okul', 'sube'], bep: ['okul', 'ders'], kulup_plan: ['okul', 'kulup'], kulup_tutanak: ['okul', 'kulup'], kulup_rapor: ['okul', 'kulup'], kulup_aylik: ['okul', 'kulup'], ogr_kurul: ['okul'], egzersiz: ['okul', 'egz'], belirli_gun: ['okul'], gezi: ['okul', 'gezi'], dilekce: ['makam', 'adsoyad'], oys: ['okul'] }[S.tur] || []; if (S.tur === 'oys' && !oyDurum(S.veri).sorular.length) { bildir('Önce sınıf, ders ve senaryo seçin (ya da “Soru ekle” ile soruları kendiniz girin).', 'hata'); return true; } var e = z.filter(function (k) { return !String(S.veri[k] || '').trim(); }); if (e.length) { bildir('Lütfen doldurun: ' + e.map(function (k) { return (t.alan.filter(function (a) { return a.k === k; })[0] || { ad: k }).ad; }).join(', '), 'hata'); return true; } return false; };
    document.getElementById('ev-hizli').onclick = function () { if (eksik()) return; S.ic = varsayilan(S.tur, S.veri); S.ekran = 'onizle'; sakla(); ciz(); };
    var yb = document.getElementById('ev-yz'); if (yb) yb.onclick = function () {
      if (eksik()) return; yb.disabled = true; yb.innerHTML = '<span class="ev-don"></span> Yazılıyor…';
      sunucu('yz', { tur: S.tur, veri: S.tur === 'bep' ? Object.assign({}, S.veri, { ogrenci: '', birim: '' }) : S.veri }).then(function (ic) {
        var taban = varsayilan(S.tur, S.veri); Object.keys(taban).forEach(function (k) { if (ic[k] === undefined || ic[k] === null || (Array.isArray(ic[k]) && !ic[k].length)) ic[k] = taban[k]; });
        S.ic = ic; S.ekran = 'onizle'; sakla(); ciz(); bildir('Taslak hazır. Metinlerin üzerine tıklayarak düzenleyebilirsiniz.', 'ok');
      }).catch(function (e) { yb.disabled = false; yb.textContent = '🤖 Yapay zekâ ile oluştur'; bildir(e.message + ' Hazır taslakla devam edebilirsiniz.', 'hata'); });
    };
  }
  /* önizleme: kâğıt görünümünde, metinler yerinde düzenlenebilir */
  function vOnizle() {
    var t = tur(S.tur), bl = bloklar(S.tur, S.veri, S.ic);
    var html = bl.map(function (b, bi) {
      switch (b.t) {
        case 'baslik': return '<div class="ev-b">' + esc(b.s) + '</div>';
        case 'alt': return '<div class="ev-alt">' + esc(b.s) + '</div>';
        case 'h': return '<h4>' + esc(b.s) + '</h4>';
        case 'p': return '<p' + (b.yol ? ' contenteditable="true" data-yol="' + b.yol + '" data-onek="' + esc(b.onek || '') + '"' : '') + '>' + (b.yol ? esc(b.s) : esc(b.s).replace(/\n/g, '<br>')) + '</p>';
        case 'liste': return '<ol' + (b.numara === false ? ' class="nok"' : '') + '>' + (b.l || []).map(function (x, i) { return '<li' + (b.yol ? ' contenteditable="true" data-yol="' + b.yol + '.' + i + '"' : '') + '>' + esc(x) + '</li>'; }).join('') + '</ol>' + (b.yol ? '<button class="ev-ekle" data-ekle="' + b.yol + '">＋ madde ekle</button>' : '');
        case 'bilgi': return '<table class="ev-bilgi">' + b.satirlar.map(function (r) { return '<tr><th>' + esc(r[0]) + '</th><td>' + esc(r[1]) + '</td></tr>'; }).join('') + '</table>';
        case 'tablo': return '<table class="ev-tb' + (yatayMi(t) ? ' yatay' : '') + '">' + (b.bas ? '<tr>' + b.bas.map(function (h) { return '<th>' + esc(h) + '</th>'; }).join('') + '</tr>' : '') + b.sat.slice(0, 80).map(function (r) { return '<tr>' + r.map(function (c) { return '<td>' + esc(c).replace(/\n/g, '<br>') + '</td>'; }).join('') + '</tr>'; }).join('') + '</table>';
        case 'imza': return (b.baslik ? '<h4>' + esc(b.baslik) + '</h4>' : '') + '<div class="ev-imza">' + (b.kisiler || []).map(function (k) { return '<div><b>' + esc(k.ad || '………………') + '</b><small>' + esc(k.gorev) + '</small><i>İmza</i></div>'; }).join('') + '</div>';
        case 'onay': return '<div class="ev-onay"><b>UYGUNDUR</b><span>' + esc(b.tarih || '…/…/20…') + '</span><b>' + esc(b.ad || '………………') + '</b><span>' + esc(b.gorev) + '</span></div>';
        case 'sayfa': return '<hr class="ev-sayfa">';
        case 'sag': return '<div class="ev-sag">' + esc(b.s).replace(/\n/g, '<br>') + '</div>';
        case 'bosluk': return '<div style="height:' + Math.round((b.n || 240) / 20) + 'px"></div>';
      }
      return '';
    }).join('');
    cerceve('<section class="ev-izgara"><div class="ev-kagit' + (yatayMi(t) ? ' yatay' : '') + '">' + html + '</div><aside class="ev-yan"><div class="ev-kart"><h3>' + t.ik + ' ' + esc(t.ad) + '</h3><p class="ev-not">Kâğıt üzerindeki gündem, görüşme, karar ve plan metinlerine tıklayıp düzenleyebilirsiniz.</p>' +
      '<button class="ev-btn ana tam" id="ev-word">⬇ Word (.docx) indir</button><button class="ev-btn tam" id="ev-yazdir">🖨 Yazdır / PDF</button><button class="ev-btn tam" id="ev-kaydet">💾 Evraklarıma kaydet</button><button class="ev-btn hafif tam" id="ev-duzen">✎ Bilgileri düzenle</button><button class="ev-btn hafif tam" id="ev-yeni">＋ Yeni evrak</button></div></aside></section>');
    rkKoy('yan', kok.querySelector('.ev-yan'));
    kok.querySelectorAll('[data-yol]').forEach(function (e) {
      e.oninput = function () {
        var y = e.dataset.yol.split('.'), metin = e.textContent;
        if (e.dataset.onek && metin.indexOf(e.dataset.onek) === 0) metin = metin.slice(e.dataset.onek.length);
        var hedef = S.ic; for (var i = 0; i < y.length - 1; i++) { if (hedef[y[i]] === undefined) hedef[y[i]] = /^\d+$/.test(y[i + 1]) ? [] : {}; hedef = hedef[y[i]]; }
        if (y.length === 1) S.ic[y[0]] = metin; else hedef[y[y.length - 1]] = metin; sakla();
      };
    });
    kok.querySelectorAll('[data-ekle]').forEach(function (b) { b.onclick = function () { var l = S.ic[b.dataset.ekle] = S.ic[b.dataset.ekle] || []; l.push('Yeni madde'); sakla(); vOnizle(); }; });
    document.getElementById('ev-word').onclick = function () { wordIndir(); };
    document.getElementById('ev-yazdir').onclick = function () { yazdir(); };
    document.getElementById('ev-kaydet').onclick = function () { var l = kayitlar(), id = S.id || Date.now(); l = l.filter(function (x) { return x.id !== id; }); l.unshift({ id: id, tur: S.tur, ad: dosyaAdi(), tarih: bugun(), veri: S.veri, ic: S.ic }); kayitlar(l); S.id = id; sakla(); bildir('Evraklarım’a kaydedildi.', 'ok'); };
    document.getElementById('ev-duzen').onclick = function () { S.ekran = 'form'; sakla(); ciz(); };
    document.getElementById('ev-yeni').onclick = function () { S = { ekran: 'sec', tur: '', veri: {}, ic: null, id: 0 }; sakla(); ciz(); };
  }
  function dosyaAdi() { var t = tur(S.tur), v = S.veri; return (t.ad.replace(/\s*\(.*?\)/g, '') + ' ' + (v.gun && v.program !== 'yillik' ? v.gun + ' ' : '') + (v.ders || (v.oys && v.oys.ders) || v.sube || v.kulup || v.sinif || '') + ' ' + (v.yil || '')).replace(/\s+/g, ' ').trim(); }
  function jszip() { return window.JSZip ? Promise.resolve() : new Promise(function (ok, no) { var s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js'; s.onload = ok; s.onerror = function () { no(new Error('Word oluşturucu yüklenemedi.')); }; document.head.appendChild(s); }); }
  function wordIndir() {
    var t = tur(S.tur);
    jszip().then(function () { return window.OSC_DOCX_EVRAK({ baslik: dosyaAdi(), site: C.site, yatay: yatayMi(t), bloklar: bloklar(S.tur, S.veri, S.ic) }); }).then(function (blob) {
      var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = dosyaAdi().replace(/[\\\/:*?"<>|]+/g, '-') + '.docx'; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
    }).catch(function (e) { bildir(e.message, 'hata'); });
  }
  function yazdir() {
    var t = tur(S.tur), k = kok.querySelector('.ev-kagit'); if (!k) return;
    var w = window.open('', '_blank'); if (!w) return bildir('Açılır pencere engellendi.', 'hata');
    w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>' + esc(dosyaAdi()) + '</title><style>@page{size:A4 ' + (yatayMi(t) ? 'landscape' : 'portrait') + ';margin:14mm}body{font:11pt Calibri,Arial,sans-serif;color:#111}' + EV_BASKI + '</style></head><body><div class="ev-kagit">' + k.innerHTML.replace(/contenteditable="true"/g, '').replace(/<button[^>]*>.*?<\/button>/g, '') + '</div><script>onload=function(){print()}<\/script></body></html>');
    w.document.close();
  }
  var EV_BASKI = '.ev-b{text-align:center;font-weight:700;margin:0 0 3px}.ev-alt{text-align:center;margin-bottom:8px}h4{color:#1e3a8a;margin:14px 0 6px;font-size:11.5pt}p{text-align:justify;margin:0 0 6px}ol{margin:0 0 6px;padding-left:22px}li{margin-bottom:3px}table{border-collapse:collapse;width:100%;margin:6px 0 10px}td,th{border:1px solid #6b7280;padding:4px 6px;vertical-align:top;font-size:10pt;text-align:left}th{background:#e8edf6}.ev-bilgi th{width:30%;background:#f1f4f9}.ev-imza{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;text-align:center;margin-top:8px}.ev-imza b,.ev-imza small,.ev-imza i{display:block}.ev-imza i{color:#888;font-style:normal;margin-top:14px;font-size:9pt}.ev-onay{text-align:center;margin-top:18px}.ev-onay b,.ev-onay span{display:block}.ev-onay span{margin-bottom:6px}.ev-sayfa{page-break-after:always;border:0}';

  function vListe() {
    var l = kayitlar();
    cerceve('<section class="ev-kart"><h2>📁 Evraklarım</h2><p class="ev-not">Bu tarayıcıda kaydettiğiniz evraklar.</p>' + (l.length ? '<div class="ev-liste">' + l.map(function (x) { var t = tur(x.tur) || { ik: '📄' }; return '<div class="ev-li"><span>' + t.ik + '</span><div><b>' + esc(x.ad) + '</b><small>' + esc(x.tarih) + '</small></div><button class="ev-btn" data-ac="' + x.id + '">Aç</button><button class="ev-btn hafif" data-sil="' + x.id + '">🗑</button></div>'; }).join('') + '</div>' : '<div class="ev-bos">Henüz kaydettiğiniz evrak yok.</div>') + '</section>');
    kok.querySelectorAll('[data-ac]').forEach(function (b) { b.onclick = function () { var x = kayitlar().filter(function (k) { return String(k.id) === b.dataset.ac; })[0]; if (!x) return; S = { ekran: 'onizle', tur: x.tur, veri: x.veri, ic: x.ic, id: x.id }; sakla(); ciz(); }; });
    kok.querySelectorAll('[data-sil]').forEach(function (b) { b.onclick = function () { if (!confirm('Silinsin mi?')) return; kayitlar(kayitlar().filter(function (k) { return String(k.id) !== b.dataset.sil; })); vListe(); }; });
  }
  ciz();
  }
  window.OSC_EV_BASLAT = BASLAT;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', BASLAT); else BASLAT();
})();
<?php }

    /* ---------- ayarlar ---------- */
    public function ayar_menu() { add_submenu_page('osc-ogretmen-masasi', 'Evrak Hazırla', 'Evrak Hazırla', 'manage_options', 'osc-evrak-ayar', array($this, 'ayar_sayfa')); }
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


/* =====================================================================
 * Öğretmen Masası: Yazılı Hazırla + Evrak Hazırla tek platformda
 * ===================================================================== */
if (!class_exists('OSC_Masa')) {
class OSC_Masa {
    public function __construct() {
        add_action('admin_menu', array($this, 'menu'), 29);
        add_action('admin_init', array($this, 'ayar_kayit'));
        add_action('admin_init', array($this, 'sayfa_kur'));
        add_shortcode('osc_ogretmen_masasi', array($this, 'kisa_kod'));
        add_shortcode('osc_ogretmen_masasi_tanitim', array(__CLASS__, 'anasayfa_bolumu'));
        add_action('template_redirect', array($this, 'yayin_kontrol'), 1);
        add_action('template_redirect', array($this, 'tam_sayfa'), 20);
    }
    public static function yazili_acik() { return class_exists('OSC_Yazili') && OSC_Yazili::yayinda(); }
    public static function evrak_acik() { return class_exists('OSC_Evrak') && OSC_Evrak::yayinda(); }
    public static function yayinda() { return self::yazili_acik() || self::evrak_acik(); }
    public static function gorebilir() { return self::yayinda() || current_user_can('manage_options'); }
    public static function url() { $id = (int) get_option('osc_om_sayfa'); return $id && get_post_status($id) === 'publish' ? get_permalink($id) : home_url('/ogretmen-masasi/'); }

    /* ---------- yönetim ---------- */
    public function menu() {
        add_menu_page('Öğretmen Masası', 'Öğretmen Masası', 'manage_options', 'osc-ogretmen-masasi', array($this, 'ayar_sayfa'), 'dashicons-welcome-learn-more', 26);
        add_submenu_page('osc-ogretmen-masasi', 'Öğretmen Masası', 'Genel', 'manage_options', 'osc-ogretmen-masasi', array($this, 'ayar_sayfa'));
    }
    public function ayar_kayit() {
        register_setting('osc_masa', 'osc_om_anasayfa', array('sanitize_callback' => function ($v) { return $v ? '1' : '0'; }));
        foreach (array('osc_yz_yayinda', 'osc_ev_yayinda') as $o) register_setting('osc_masa', $o, array('sanitize_callback' => function ($v) { return $v ? '1' : '0'; }));
    }
    public function sayfa_kur() {
        if (!current_user_can('manage_options') || get_option('osc_om_kur') === '1') return;
        $id = (int) get_option('osc_om_sayfa');
        if (!$id || get_post_status($id) !== 'publish') {
            $var = get_page_by_path('ogretmen-masasi');
            if ($var) { $id = $var->ID; if ($var->post_status !== 'publish' || strpos($var->post_content, '[osc_ogretmen_masasi') === false) wp_update_post(array('ID' => $id, 'post_status' => 'publish', 'post_content' => '[osc_ogretmen_masasi]')); }
            else $id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Öğretmen Masası', 'post_name' => 'ogretmen-masasi', 'post_content' => '[osc_ogretmen_masasi]'));
            if ($id && !is_wp_error($id)) update_option('osc_om_sayfa', $id);
        }
        update_option('osc_om_kur', '1');
    }
    public function ayar_sayfa() { $u = self::url(); ?>
<div class="wrap"><h1>Öğretmen Masası</h1>
<p>Yazılı Hazırla ve Evrak Hazırla tek platformda toplanır: <a href="<?php echo esc_url($u); ?>" target="_blank"><?php echo esc_html($u); ?></a></p>
<form method="post" action="options.php"><?php settings_fields('osc_masa'); ?>
<table class="form-table">
<tr><th>Yazılı Hazırla</th><td><label><input type="checkbox" name="osc_yz_yayinda" value="1" <?php checked(get_option('osc_yz_yayinda', '0') === '1'); ?>> Yayında</label> &nbsp; <a href="<?php echo esc_url(admin_url('admin.php?page=osc-yazili-ayar')); ?>">Ayrıntılı ayarlar →</a></td></tr>
<tr><th>Evrak Hazırla</th><td><label><input type="checkbox" name="osc_ev_yayinda" value="1" <?php checked(get_option('osc_ev_yayinda', '0') === '1'); ?>> Yayında</label> &nbsp; <a href="<?php echo esc_url(admin_url('admin.php?page=osc-evrak-ayar')); ?>">Ayrıntılı ayarlar (evraklar, yapay zekâ, reklamlar) →</a></td></tr>
<tr><th>Ana sayfa</th><td><label><input type="checkbox" name="osc_om_anasayfa" value="1" <?php checked(get_option('osc_om_anasayfa', '1') !== '0'); ?>> Ana sayfada “Öğretmen Masası” tanıtım bölümünü göster</label><p class="description">Yayında olan araç yoksa bölüm, menü bağlantısı ve platform sayfası ziyaretçilere gösterilmez (yöneticiler önizleyebilir).</p></td></tr>
</table><?php submit_button(); ?></form></div>
<?php }

    /* ---------- sayfa ---------- */
    public function yayin_kontrol() {
        $id = (int) get_option('osc_om_sayfa');
        if (self::yayinda() || is_admin() || !$id || !is_page($id)) return;
        if (!current_user_can('manage_options')) { wp_safe_redirect(home_url('/')); exit; }
        add_action('wp_head', function () { echo '<meta name="robots" content="noindex,nofollow">' . "\n"; }, 1);
    }
    public function tam_sayfa() {
        $id = (int) get_option('osc_om_sayfa');
        if (!$id || !is_page($id) || is_admin()) return;
        add_filter('body_class', function ($c) { $c[] = 'osc-om-tam'; $c[] = 'no-sidebar'; return array_diff($c, array('has-sidebar', 'right-sidebar', 'left-sidebar')); });
        add_filter('is_active_sidebar', '__return_false', 99);
        add_filter('pre_get_document_title', function () { return 'Öğretmen Masası: Yazılı ve Evrak Hazırlama | ' . get_bloginfo('name'); }, 99);
        add_action('wp_head', function () { echo '<meta name="description" content="MEB senaryolarına uygun yazılı sınav kâğıdı ve zümre, ŞÖK, veli toplantısı tutanakları, BEP planı, kulüp evrakları, OYS puan çizelgesi gibi öğretmen evraklarını dakikalar içinde hazırlayın. Ücretsiz.">' . "\n"; }, 2);
        get_header(); ?>
<style>body.osc-om-tam{background:#f6f7fb}body.osc-om-tam #secondary,body.osc-om-tam .sidebar,body.osc-om-tam .widget-area{display:none!important}</style>
<main id="main" class="osc-om-sayfa" role="main"><?php echo $this->kisa_kod(); ?></main>
<?php get_footer(); exit;
    }
    private static function evrak_listesi() {
        if (!class_exists('OSC_Evrak')) return array();
        $ik = array('zumre' => '👥', 'ogr_kurul' => '🏛️', 'sok' => '🏫', 'veli' => '👨‍👩‍👧', 'bep' => '🧩', 'kulup_plan' => '🎭', 'kulup_tutanak' => '🎭', 'kulup_aylik' => '🎭', 'kulup_rapor' => '🎭', 'egzersiz' => '🏐', 'belirli_gun' => '🎗️', 'gezi' => '🚌', 'dilekce' => '✉️', 'oys' => '🧮');
        $o = array(); $kulup = false;
        foreach (OSC_Evrak::acik_turler() as $k) {
            if (strpos($k, 'kulup_') === 0) { if (!$kulup) { $o[] = array('🎭', 'Sosyal kulüp evrakları', 'Yıllık plan, ilk toplantı tutanağı, aylık ve dönem sonu raporu'); $kulup = true; } continue; }
            $ad = OSC_Evrak::$turler[$k]; $ac = array('zumre' => 'Yıl başı, 2. dönem ve yıl sonu gündemleriyle', 'ogr_kurul' => 'Müdür başkanlığında, katılım listesiyle', 'sok' => 'Şubenin akademik ve rehberlik durumu', 'veli' => 'Katılım ve imza listesiyle', 'bep' => 'Uzun ve kısa dönemli amaçlar, uyarlamalar', 'egzersiz' => 'Aylara göre ders dışı çalışma planı', 'belirli_gun' => 'Kutlama/anma programı ve konuşma metni', 'gezi' => 'Veli izin belgesi ve öğrenci listesiyle', 'dilekce' => 'İzin, rapor, nöbet, belge talebi', 'oys' => 'MEB senaryosuna göre soru bazlı puanlar');
            $o[] = array(isset($ik[$k]) ? $ik[$k] : '📄', $ad, isset($ac[$k]) ? $ac[$k] : '');
        }
        return $o;
    }
    public function kisa_kod() {
        if (!self::gorebilir()) return '';
        $yz = class_exists('OSC_Yazili') && OSC_Yazili::gorebilir(); $ev = class_exists('OSC_Evrak') && OSC_Evrak::gorebilir();
        $yzu = $yz ? OSC_Yazili::url() : ''; $evu = $ev ? OSC_Evrak::url() : '';
        $giris = is_user_logged_in(); $ogr = $giris && class_exists('OSC_Sinav') && OSC_Sinav::rol() === 'ogrenci';
        $uye = class_exists('OSC_Sinav') ? OSC_Sinav::sayfa_url('osc_sp_uye_sayfa', array('rol' => 'ogretmen', 'git' => self::url())) : wp_login_url(self::url());
        $yon = current_user_can('manage_options');
        $rozet = function ($acik) use ($yon) { return (!$acik && $yon) ? '<span class="om-gizli">🔒 Yalnızca yöneticiler görüyor</span>' : ''; };
        ob_start(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<div class="om">
<section class="om-hero"><div class="om-ic">
  <span class="om-et"><i></i>Öğretmenler için ücretsiz</span>
  <h1>Öğretmen <em>Masası</em></h1>
  <p>Yazılı sınav kâğıdınızı MEB senaryolarına göre bir dakikada hazırlayın; tutanaklardan BEP planına, kulüp evraklarından OYS puan çizelgesine kadar evraklarınızı tek yerden oluşturun. Word olarak indirin, düzenleyin, yazdırın.</p>
  <div class="om-cta">
    <?php if ($yz) echo '<a class="om-btn a" href="' . esc_url($yzu) . '">📄 Yazılı hazırla</a>'; if ($ev) echo '<a class="om-btn b" href="' . esc_url($evu) . '">🗂️ Evrak hazırla</a>'; ?>
  </div>
  <?php if (!$giris) echo '<p class="om-kucuk">Araçları kullanmak için <a href="' . esc_url($uye) . '">ücretsiz öğretmen hesabınızla giriş yapın</a>.</p>'; elseif ($ogr) echo '<p class="om-kucuk">Bu araçlar öğretmen hesaplarına özeldir.</p>'; ?>
  <div class="om-say"><div><b>5–12</b><span>sınıf, tüm dersler</span></div><div><b>MEB</b><span>2026-2027 senaryoları</span></div><div><b><?php echo max(1, count(class_exists('OSC_Evrak') ? OSC_Evrak::acik_turler() : array())); ?></b><span>evrak türü</span></div><div><b>.docx</b><span>Word ve PDF çıktı</span></div></div>
</div></section>

<div class="om-ic">
<section class="om-araclar">
<?php if ($yz): ?>
  <a class="om-arac yz" href="<?php echo esc_url($yzu); ?>"><?php echo $rozet(self::yazili_acik()); ?>
    <div class="om-arac-ust"><span class="om-ik">📄</span><div><small>Yazılı Hazırla</small><h2>Senaryoya uygun yazılı, cevap anahtarıyla</h2></div></div>
    <p>Sınıfı, dersi ve MEB konu-soru dağılım tablosundaki senaryoyu seçin; sorular senaryodaki öğrenme çıktılarına göre soru havuzundan gelsin. Beğenmediğinizi değiştirin ya da kendi sorunuzu yazın.</p>
    <ul><li>MEB 2026-2027 senaryolarına birebir uygun</li><li>Seçmeli, meslek ve diğer dersler için kendi konularınız</li><li>Puanlar 100’e otomatik dağıtılır</li><li>Cevap boşluklu kâğıt + cevap anahtarı, Word ve PDF</li></ul>
    <span class="om-git">Yazılı hazırla →</span></a>
<?php endif; if ($ev): ?>
  <a class="om-arac ev" href="<?php echo esc_url($evu); ?>"><?php echo $rozet(self::evrak_acik()); ?>
    <div class="om-arac-ust"><span class="om-ik">🗂️</span><div><small>Evrak Hazırla</small><h2>Tutanaklar, planlar ve çizelgeler hazır</h2></div></div>
    <p>Okulunuzun ve toplantının bilgilerini girin; gündem, görüşmeler ve kararlar yönetmeliğe uygun taslakla gelsin. Kâğıt üzerinde düzenleyip Word olarak indirin.</p>
    <ul><li>Zümre, öğretmenler kurulu, ŞÖK ve veli toplantısı tutanakları</li><li>BEP planı, kulüp evrakları, gezi ve egzersiz planları</li><li>OYS puan çizelgesi: senaryo seçin, e-Okul listesini yapıştırın</li><li>Evraklarınız yalnızca kendi tarayıcınızda saklanır</li></ul>
    <span class="om-git">Evrak hazırla →</span></a>
<?php endif; ?>
</section>

<?php $el = $ev ? self::evrak_listesi() : array(); if ($el): ?>
<section class="om-bolum"><h2>Hazırlayabileceğiniz evraklar</h2><p class="om-alt">Her evrak bilgilerinize göre oluşturulur; metinlerin üzerine tıklayıp değiştirebilirsiniz.</p>
<div class="om-evraklar"><?php foreach ($el as $e) echo '<a href="' . esc_url($evu) . '"><span>' . $e[0] . '</span><b>' . esc_html($e[1]) . '</b><small>' . esc_html($e[2]) . '</small></a>'; ?></div></section>
<?php endif; ?>

<section class="om-bolum"><h2>Nasıl çalışır?</h2>
<div class="om-adim"><div><i>1</i><b>Giriş yapın</b><p>Ücretsiz öğretmen hesabınızla giriş yapın; okul bilgileriniz sonraki evraklarda otomatik dolar.</p></div><div><i>2</i><b>Seçin ve doldurun</b><p>Senaryoyu ya da evrak türünü seçin, birkaç bilgiyi girin. Taslak anında hazırlanır.</p></div><div><i>3</i><b>Düzenleyin, indirin</b><p>Kâğıt görünümünde kontrol edin, istediğinizi değiştirin; Word ya da PDF olarak indirin.</p></div></div></section>

<section class="om-bolum om-sss"><h2>Sık sorulan sorular</h2>
<details><summary>Ücretli mi?</summary><p>Hayır. Öğretmen hesabıyla giriş yapan tüm öğretmenler ücretsiz kullanabilir.</p></details>
<details><summary>Hazırlanan evraklar nerede saklanıyor?</summary><p>Yazılılarınız hesabınıza kaydedilir ve her cihazdan açılabilir. Evraklar ise yalnızca kullandığınız tarayıcıda saklanır; öğrenci bilgileri sunucuya kaydedilmez.</p></details>
<details><summary>Evraklar yönetmeliğe uygun mu?</summary><p>Toplantı zamanları ve evrak bölümleri ilgili MEB yönetmeliklerine göre hazırlanır ve her evrakta dayanağı belirtilir. Okulunuza özgü bilgileri ve tarihleri kontrol edip gerekirse düzenleyin.</p></details>
<details><summary>Word dosyasını değiştirebilir miyim?</summary><p>Evet. İndirdiğiniz .docx dosyası Word, LibreOffice veya Google Dokümanlar’da açılıp düzenlenebilir.</p></details>
</section>
</div>
</div>
<style>
.osc-om-sayfa{width:100%;max-width:none;margin:0;padding:0 0 40px;float:none;clear:both}
.om{font-family:"Plus Jakarta Sans",system-ui,-apple-system,"Segoe UI",sans-serif;color:#0b1220}
.om *{box-sizing:border-box}
.om-ic{max-width:1180px;margin:0 auto;padding:0 18px}
.om-hero{position:relative;overflow:hidden;color:#fff;padding:64px 0 54px;background:radial-gradient(700px 320px at 92% -10%,rgba(45,212,191,.35),transparent 60%),radial-gradient(800px 360px at 0% 120%,rgba(192,38,211,.4),transparent 60%),linear-gradient(135deg,#0b1024,#1e1b4b 50%,#0f3d56)}
.om-hero:before{content:"";position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px);background-size:34px 34px;-webkit-mask-image:radial-gradient(ellipse at 70% 0,#000 10%,transparent 70%);mask-image:radial-gradient(ellipse at 70% 0,#000 10%,transparent 70%)}
.om-hero .om-ic{position:relative;text-align:center}
.om-et{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:700;color:#e0e7ff;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);padding:6px 13px;border-radius:99px}
.om-et i{width:7px;height:7px;border-radius:50%;background:#34d399;box-shadow:0 0 0 4px rgba(52,211,153,.25)}
.om-hero h1{color:#fff!important;font-size:clamp(36px,6vw,62px)!important;line-height:1.05!important;letter-spacing:-.03em;margin:18px 0 14px!important;font-weight:800}
.om-hero h1 em{font-style:normal;background:linear-gradient(90deg,#5eead4,#a5b4fc,#f0abfc);-webkit-background-clip:text;background-clip:text;color:transparent}
.om-hero>.om-ic>p{max-width:720px;margin:0 auto 26px;color:#cbd5e1;font-size:17px;line-height:1.65}
.om-cta{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
.om-btn{display:inline-flex;align-items:center;gap:8px;font-weight:800;font-size:16px;padding:15px 24px;border-radius:16px;text-decoration:none!important;transition:transform .2s,box-shadow .2s}
.om-btn:hover{transform:translateY(-2px)}
.om-btn.a{background:#fff;color:#1e1b4b!important;box-shadow:0 16px 30px -16px rgba(0,0,0,.7)}
.om-btn.b{background:linear-gradient(135deg,#14b8a6,#2563eb);color:#fff!important;box-shadow:0 16px 30px -16px rgba(20,184,166,.8)}
.om-kucuk{margin:16px 0 0;color:#cbd5e1;font-size:14px}.om-kucuk a{color:#5eead4!important;font-weight:700}
.om-say{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;max-width:760px;margin:36px auto 0}
.om-say div{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:16px;padding:14px 10px}
.om-say b{display:block;font-size:24px;color:#fff}.om-say span{display:block;font-size:12.5px;line-height:1.4;color:#cbd5e1}
.om-araclar{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:-30px;position:relative}
.om-arac{position:relative;display:flex;flex-direction:column;background:#fff;border:1px solid #e5e8f0;border-radius:24px;padding:28px;text-decoration:none!important;color:#0b1220!important;box-shadow:0 30px 60px -40px rgba(15,23,42,.6);transition:transform .2s,box-shadow .2s}
.om-arac:hover{transform:translateY(-3px);box-shadow:0 40px 70px -40px rgba(15,23,42,.7)}
.om-arac-ust{display:flex;gap:14px;align-items:center;margin-bottom:12px}
.om-ik{flex:none;width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:28px}
.om-arac.yz .om-ik{background:linear-gradient(135deg,#5b5bf6,#c026d3)}.om-arac.ev .om-ik{background:linear-gradient(135deg,#0f766e,#2563eb)}
.om-arac small{font-weight:800;font-size:12px;letter-spacing:.06em;text-transform:uppercase}.om-arac.yz small{color:#7c3aed}.om-arac.ev small{color:#0f766e}
.om-arac h2{margin:2px 0 0!important;font-size:21px!important;line-height:1.25!important;color:#0b1220!important}
.om-arac p{color:#475569;line-height:1.6;margin:0 0 12px;font-size:15px}
.om-arac ul{list-style:none;margin:0 0 18px;padding:0}
.om-arac li{position:relative;padding:5px 0 5px 26px;font-size:14.5px;color:#1e293b}
.om-arac li:before{content:"✓";position:absolute;left:0;top:5px;width:18px;height:18px;border-radius:50%;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;color:#fff}
.om-arac.yz li:before{background:#7c3aed}.om-arac.ev li:before{background:#0f766e}
.om-git{margin-top:auto;align-self:flex-start;font-weight:800;padding:12px 18px;border-radius:13px;color:#fff}
.om-arac.yz .om-git{background:linear-gradient(135deg,#5b5bf6,#7c3aed)}.om-arac.ev .om-git{background:linear-gradient(135deg,#0f766e,#2563eb)}
.om-gizli{position:absolute;top:14px;right:14px;font-size:11.5px;font-weight:700;background:#fef3c7;color:#92400e;border-radius:99px;padding:4px 10px}
.om-bolum{margin-top:56px}
.om-bolum h2{font-size:clamp(22px,3vw,30px)!important;margin:0 0 6px!important;letter-spacing:-.02em;color:#0b1220!important;text-align:center}
.om-alt{text-align:center;color:#64748b;margin:0 0 22px}
.om-evraklar{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px}
.om-evraklar a{display:block;background:#fff;border:1px solid #e5e8f0;border-radius:16px;padding:16px;text-decoration:none!important;color:#0b1220!important;transition:border-color .2s,transform .2s}
.om-evraklar a:hover{border-color:#5eead4;transform:translateY(-2px)}
.om-evraklar span{font-size:24px}.om-evraklar b{display:block;margin:6px 0 2px;font-size:15px}.om-evraklar small{display:block;color:#64748b;font-size:13px;line-height:1.5}
.om-adim{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:22px}
.om-adim>div{background:#fff;border:1px solid #e5e8f0;border-radius:18px;padding:20px}
.om-adim i{display:inline-flex;width:34px;height:34px;border-radius:10px;align-items:center;justify-content:center;font-style:normal;font-weight:800;color:#fff;background:linear-gradient(135deg,#5b5bf6,#14b8a6)}
.om-adim b{display:block;margin:10px 0 4px;font-size:16px}.om-adim p{margin:0;color:#475569;font-size:14px;line-height:1.6}
.om-sss{max-width:820px;margin-left:auto;margin-right:auto}
.om-sss h2{margin-bottom:18px!important}
.om-sss details{background:#fff;border:1px solid #e5e8f0;border-radius:14px;padding:14px 18px;margin-bottom:10px}
.om-sss summary{cursor:pointer;font-weight:700}.om-sss p{color:#475569;line-height:1.6;margin:10px 0 0}
@media(max-width:860px){.om-araclar{grid-template-columns:1fr;margin-top:-20px}.om-adim{grid-template-columns:1fr}.om-say{grid-template-columns:repeat(2,1fr)}.om-hero{padding:44px 0 46px}.om-arac{padding:22px}}
</style>
<?php return ob_get_clean();
    }

    /* ---------- ana sayfa bölümü ---------- */
    public static function anasayfa_bolumu() {
        if (get_option('osc_om_anasayfa', '1') === '0' || !self::gorebilir()) return '';
        $yz = class_exists('OSC_Yazili') && OSC_Yazili::gorebilir(); $ev = class_exists('OSC_Evrak') && OSC_Evrak::gorebilir();
        ob_start(); ?>
<section class="sph2-sec om-ana" aria-labelledby="om-ana-b">
  <div class="om-ana-kap">
    <div class="om-ana-sol">
      <span class="om-ana-et"><i></i>YENİ · Öğretmenler için ücretsiz</span>
      <h2 id="om-ana-b">Öğretmen <em>Masası</em></h2>
      <p>Yazılı sınav kâğıdınızı MEB senaryolarına göre hazırlayın; tutanak, plan ve çizelgelerinizi dakikalar içinde oluşturun. Hepsi tek yerde, Word olarak indirilebilir.</p>
      <a class="om-ana-btn" href="<?php echo esc_url(self::url()); ?>">Öğretmen Masası’na git <b>→</b></a>
    </div>
    <div class="om-ana-sag">
      <?php if ($yz): ?><a class="om-ana-kart yz" href="<?php echo esc_url(OSC_Yazili::url()); ?>"><span>📄</span><div><b>Yazılı Hazırla</b><small>5–12. sınıf · senaryoya uygun · cevap anahtarlı</small></div><em>→</em></a><?php endif; ?>
      <?php if ($ev): ?><a class="om-ana-kart ev" href="<?php echo esc_url(OSC_Evrak::url()); ?>"><span>🗂️</span><div><b>Evrak Hazırla</b><small>Zümre, ŞÖK, veli tutanağı · BEP · kulüp evrakları</small></div><em>→</em></a>
      <a class="om-ana-kart oy" href="<?php echo esc_url(OSC_Evrak::url()); ?>"><span>🧮</span><div><b>OYS Puan Çizelgesi</b><small>Senaryoya göre soru bazlı puanlar · e-Okul listesi</small></div><em>→</em></a><?php endif; ?>
    </div>
  </div>
<style>
.om-ana-kap{position:relative;display:grid;grid-template-columns:1.1fr 1fr;gap:28px;align-items:center;overflow:hidden;border-radius:28px;padding:36px 40px;color:#fff;background:radial-gradient(700px 260px at 95% -20%,rgba(45,212,191,.45),transparent 60%),radial-gradient(600px 300px at 0% 130%,rgba(192,38,211,.4),transparent 60%),linear-gradient(135deg,#0b1024,#1e1b4b 55%,#0f3d56);box-shadow:0 30px 60px -36px rgba(30,27,75,.9)}
.om-ana-et{display:inline-flex;align-items:center;gap:8px;font-size:12.5px;font-weight:700;color:#e0e7ff;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);padding:6px 12px;border-radius:99px}
.om-ana-et i{width:7px;height:7px;border-radius:50%;background:#34d399;box-shadow:0 0 0 4px rgba(52,211,153,.25)}
.om-ana h2{color:#fff!important;font-size:clamp(28px,3.6vw,42px)!important;line-height:1.1!important;margin:14px 0 10px!important;letter-spacing:-.02em}
.om-ana h2 em{font-style:normal;background:linear-gradient(90deg,#5eead4,#a5b4fc,#f0abfc);-webkit-background-clip:text;background-clip:text;color:transparent}
.om-ana-sol p{color:#cbd5e1;font-size:15.5px;line-height:1.6;margin:0 0 20px}
.om-ana-btn{display:inline-flex;align-items:center;gap:10px;background:#fff;color:#1e1b4b!important;font-weight:800;font-size:15.5px;padding:14px 22px;border-radius:15px;text-decoration:none!important}
.om-ana-btn b{transition:transform .2s}.om-ana-btn:hover b{transform:translateX(4px)}
.om-ana-sag{display:flex;flex-direction:column;gap:10px}
.om-ana-kart{display:flex;align-items:center;gap:14px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);border-radius:18px;padding:14px 16px;color:#fff!important;text-decoration:none!important;transition:background .2s,transform .2s}
.om-ana-kart:hover{background:rgba(255,255,255,.14);transform:translateX(3px)}
.om-ana-kart>span{flex:none;width:46px;height:46px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:22px}
.om-ana-kart.yz>span{background:linear-gradient(135deg,#5b5bf6,#c026d3)}.om-ana-kart.ev>span{background:linear-gradient(135deg,#0f766e,#2563eb)}.om-ana-kart.oy>span{background:linear-gradient(135deg,#f59e0b,#ef4444)}
.om-ana-kart b{display:block;font-size:16px;line-height:1.3}.om-ana-kart small{display:block;color:#cbd5e1;font-size:12.5px;line-height:1.45}
.om-ana-kart>div{flex:1;min-width:0}.om-ana-kart em{font-style:normal;font-weight:800;color:#5eead4}
@media(max-width:860px){.om-ana-kap{grid-template-columns:1fr;padding:26px 22px}}
</style>
</section>
<?php return ob_get_clean();
    }
}
$GLOBALS['osc_masa'] = new OSC_Masa();
}

}
