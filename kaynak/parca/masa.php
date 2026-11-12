
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
