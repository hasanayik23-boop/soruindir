
/* =====================================================================
 * V22 — Gerçek sınav modu yardımcıları, Canlı Sınıf, Ödev Linki,
 *        Öğretmen test ekleme ve yönetici onayı
 * ===================================================================== */
if (!class_exists('OSC_Sinav')) {
class OSC_Sinav {
    const DB_SURUM = '2';
    const KOD_HARF = 'ABCDEFGHJKLMNPRSTUVYZ23456789';
    const MAX_KATILIMCI = 300;

    /* ---------------- sınav türleri ve net kuralı ---------------- */
    public static function turler() {
        return array(
            'genel' => array('ad' => 'Genel test', 'kural' => 4, 'dk' => 0),
            'lgs'   => array('ad' => 'LGS', 'kural' => 3, 'dk' => 155 / 90),
            'tyt'   => array('ad' => 'YKS – TYT', 'kural' => 4, 'dk' => 165 / 120),
            'ayt'   => array('ad' => 'YKS – AYT', 'kural' => 4, 'dk' => 180 / 160),
            'ydt'   => array('ad' => 'YKS – YDT', 'kural' => 4, 'dk' => 120 / 80),
            'kpss'  => array('ad' => 'KPSS', 'kural' => 4, 'dk' => 130 / 120),
            'ales'  => array('ad' => 'ALES', 'kural' => 4, 'dk' => 150 / 100),
            'dgs'   => array('ad' => 'DGS', 'kural' => 4, 'dk' => 150 / 100),
            'msu'   => array('ad' => 'MSÜ', 'kural' => 4, 'dk' => 165 / 120),
            'ekys'  => array('ad' => 'MEB EKYS', 'kural' => 0, 'dk' => 150 / 80),
            'yds'   => array('ad' => 'YDS / YÖKDİL', 'kural' => 0, 'dk' => 180 / 80),
            'okul'  => array('ad' => 'Okul sınavı / yazılı', 'kural' => 0, 'dk' => 0),
        );
    }
    public static function tur_algila($metin) {
        $m = function_exists('mb_strtoupper') ? mb_strtoupper($metin, 'UTF-8') : strtoupper($metin);
        $kurallar = array('ekys' => '/\bEKYS\b|EĞİTİM KURUMLARINA YÖNETİCİ/u', 'lgs' => '/\bLGS\b/u', 'tyt' => '/\bTYT\b/u', 'ayt' => '/\bAYT\b/u', 'ydt' => '/\bYDT\b/u', 'kpss' => '/\bKPSS\b/u', 'ales' => '/\bALES\b/u', 'dgs' => '/\bDGS\b/u', 'msu' => '/\bMSÜ\b|\bMSU\b/u', 'yds' => '/\bYDS\b|YÖKDİL/u');
        foreach ($kurallar as $k => $re) if (preg_match($re, $m)) return $k;
        if (preg_match('/\bYKS\b/u', $m)) return 'tyt';
        return 'genel';
    }
    public static function bilgi($post_id, $settings, $n) {
        $t = self::turler();
        $k = is_array($settings) && !empty($settings['sinav_turu']) && isset($t[$settings['sinav_turu']]) ? $settings['sinav_turu'] : '';
        if (!$k) {
            $metin = get_the_title($post_id);
            $cats = get_the_category($post_id);
            if ($cats) foreach ($cats as $c) $metin .= ' ' . $c->name;
            $k = self::tur_algila($metin);
        }
        $x = $t[$k];
        return array('kod' => $k, 'ad' => $x['ad'], 'kural' => $x['kural'], 'oto_sure' => $x['dk'] > 0 && $n > 0 ? max(1, (int) round($n * $x['dk'])) : 0);
    }
    public static function cevapsiz($data) {
        $o = array();
        foreach ((array) $data as $q) {
            $t = isset($q['type']) ? $q['type'] : 'multiple';
            unset($q['correct']);
            if ($t === 'gap' || $t === 'classic') $q['options'] = array('A' => '');
            $o[] = $q;
        }
        return $o;
    }
    public static function anahtar($data) {
        $o = array();
        foreach ((array) $data as $q) {
            $t = isset($q['type']) ? $q['type'] : 'multiple';
            $o[] = ($t === 'gap' || $t === 'classic') ? (isset($q['options']['A']) ? $q['options']['A'] : '') : (isset($q['correct']) ? $q['correct'] : '');
        }
        return $o;
    }
    private static function anahtar_kelime($s) {
        $s = wp_strip_all_tags(html_entity_decode(preg_replace('/<br\s*\/?>/i', ' ', (string) $s), ENT_QUOTES, 'UTF-8'));
        $s = str_replace(array('I', 'İ'), array('ı', 'i'), $s);
        $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
        $s = str_replace("i\xCC\x87", 'i', $s);
        $s = preg_replace('/[^0-9a-zçğıöşüâîûéèàäëïô]+/u', ' ', $s);
        $s = strtr($s, array('ı' => 'i', 'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ö' => 'o', 'ç' => 'c', 'â' => 'a', 'à' => 'a', 'î' => 'i', 'û' => 'u', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ä' => 'a', 'ï' => 'i', 'ô' => 'o'));
        return trim(preg_replace('/\s+/', ' ', $s));
    }
    public static function bosluk_ayni($model, $ogr) {
        $sk = self::anahtar_kelime($ogr);
        if ($sk === '') return false;
        $raw = wp_strip_all_tags((string) $model);
        $alts = array($raw);
        if (preg_match('/[\/;|]/', $raw)) { $parts = array_filter(preg_split('/\s*[\/;|]\s*/', $raw), function ($p) { return preg_match('/[A-Za-zÇĞİÖŞÜçğıöşü]/u', $p); }); if (count($parts) > 1) $alts = array_merge($alts, $parts); }
        foreach ($alts as $a) if (self::anahtar_kelime($a) === $sk) return true;
        return false;
    }
    /** Sunucuda puanlama. Durum kodları: d doğru, y yanlış, b boş, n değerlendirilmedi (açık uçlu) */
    public static function puanla($data, $cev, $kural) {
        $durum = array(); $d = 0; $y = 0; $b = 0; $n = 0; $konu = array();
        foreach (array_values((array) $data) as $i => $q) {
            $a = isset($cev[$i]) ? $cev[$i] : null;
            $t = isset($q['type']) ? $q['type'] : 'multiple';
            if ($a === null || trim((string) $a) === '') $s = 'b';
            elseif ($t === 'classic') $s = 'n';
            elseif ($t === 'gap') $s = self::bosluk_ayni(isset($q['options']['A']) ? $q['options']['A'] : '', $a) ? 'd' : 'y';
            else $s = ((string) $a === (string) (isset($q['correct']) ? $q['correct'] : '')) ? 'd' : 'y';
            $durum[] = $s;
            if ($s === 'd') $d++; elseif ($s === 'y') $y++; elseif ($s === 'n') $n++; else $b++;
        }
        $net = $kural > 0 ? $d - $y / $kural : $d;
        $say = count($durum) - $n;
        return array('d' => $d, 'y' => $y, 'b' => $b, 'n' => $n, 'net' => round($net, 2), 'puan' => $say > 0 ? (int) round($d / $say * 100) : 0, 'durum' => $durum);
    }
    public static function panel_url($args = array()) {
        $id = (int) get_option('osc_sp_panel_sayfa');
        if (!$id || get_post_status($id) !== 'publish') return '';
        return add_query_arg($args, get_permalink($id));
    }
    public static function sayfa_url($opt, $args = array()) {
        $id = (int) get_option($opt);
        if (!$id || get_post_status($id) !== 'publish') return '';
        return add_query_arg($args, get_permalink($id));
    }
    public static function rol($uid = 0) {
        $uid = $uid ? $uid : get_current_user_id();
        if (!$uid) return '';
        if (user_can($uid, 'edit_others_posts')) return 'ogretmen';
        $r = get_user_meta($uid, 'osc_rol', true);
        return $r ? $r : 'ogretmen';
    }
    public static function logo_src() { return 'data:image/png;base64,/*__LOGO__*/'; }
    public static function logo($h = 34) { return '<img class="osc-logo" src="' . self::logo_src() . '" alt="soruindir.net" height="' . (int) $h . '" style="height:' . (int) $h . 'px;width:auto;display:block">'; }
    public static function ogrenci_url($kod) { return add_query_arg('osc_k', $kod, home_url('/')); }

    /* ---------------- kurulum ---------------- */
    public function __construct() {
        add_action('init', array($this, 'db_kur'), 5);
        add_action('admin_init', array($this, 'sayfalari_kur'));
        add_action('template_redirect', array($this, 'ogrenci_sayfa'), 1);
        add_shortcode('osc_ogretmen_paneli', array($this, 'panel_kisa_kod'));
        add_shortcode('osc_katil', array($this, 'katil_kisa_kod'));
        add_shortcode('osc_uyelik', array($this, 'uyelik_kisa_kod'));
        add_shortcode('osc_ogrenci_paneli', array($this, 'ogrenci_paneli_kisa_kod'));
        add_action('template_redirect', array($this, 'uyelik_islem'), 0);
        add_action('init', array($this, 'dogrulama_link'));
        add_filter('authenticate', array($this, 'giris_koruma'), 5, 3);
        add_filter('wp_authenticate_user', array($this, 'dogrulama_kontrol'), 20);
        add_action('wp_login_failed', array($this, 'giris_hatali'));
        add_action('register_form', array($this, 'wp_kayit_formu'));
        add_filter('registration_errors', array($this, 'wp_kayit_hatalari'), 10, 3);
        add_action('admin_menu', array($this, 'ayar_menu'));
        add_action('admin_init', array($this, 'ayar_kaydet'));
        add_action('template_redirect', array($this, 'kutuphane_servis'), 0);
        add_action('template_redirect', array($this, 'onbellek_engel'), 1);
        add_action('template_redirect', array($this, 'cikis_islem'), 0);
        add_action('login_init', array($this, 'wp_login_yonlendir'), 1);
        add_action('admin_init', array($this, 'admin_engel'), 1);
        add_filter('logout_url', array($this, 'f_logout_url'), 20, 2);
        add_filter('login_url', array($this, 'f_login_url'), 20, 2);
        add_filter('lostpassword_url', array($this, 'f_lostpassword_url'), 20);
        add_filter('register_url', array($this, 'f_register_url'), 20);
        add_filter('show_admin_bar', function ($g) { return current_user_can('edit_posts') ? $g : false; });
        add_filter('wp_mail_from_name', array($this, 'f_mail_from_name'));
        add_filter('wp_mail_from', array($this, 'f_mail_from'));
        add_filter('the_generator', '__return_empty_string');
        remove_action('wp_head', 'wp_generator');
        add_action('wp_ajax_osc_sp_cozum_kaydet', array($this, 'ajax_cozum_kaydet'));
        add_action('wp_ajax_osc_sp_ogr_ozet', array($this, 'ajax_ogr_ozet'));
        foreach (array('durum', 'basla', 'kaydet', 'teslim') as $a) {
            add_action('wp_ajax_osc_sp_' . $a, array($this, 'ajax_' . $a));
            add_action('wp_ajax_nopriv_osc_sp_' . $a, array($this, 'ajax_' . $a));
        }
        foreach (array('ara', 'olustur', 'oturumlar', 'izle', 'komut', 'testlerim', 'test_al', 'test_kaydet', 'test_sil', 'gorsel') as $a) add_action('wp_ajax_osc_sp_' . $a, array($this, 'ajax_' . $a));
        // yönetici onayı
        add_action('transition_post_status', array($this, 'yayin_gecisi'), 10, 3);
        add_action('admin_post_osc_sp_onay', array($this, 'admin_onay'));
        add_action('admin_post_osc_sp_red', array($this, 'admin_red'));
        add_filter('post_row_actions', array($this, 'satir_eylemleri'), 10, 2);
        add_filter('manage_osc_quiz_posts_columns', array($this, 'sutun_ekle'), 20);
        add_action('manage_osc_quiz_posts_custom_column', array($this, 'sutun_goster'), 20, 2);
        add_action('admin_menu', array($this, 'menu_rozet'), 999);
        add_action('admin_notices', array($this, 'admin_bildirim'));
    }
    private function t_ot() { global $wpdb; return $wpdb->prefix . 'osc_oturum'; }
    private function t_kt() { global $wpdb; return $wpdb->prefix . 'osc_katilim'; }
    private function t_cz() { global $wpdb; return $wpdb->prefix . 'osc_cozum'; }
    public function db_kur() {
        if (get_option('osc_sp_db') === self::DB_SURUM) return;
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$this->t_ot()} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 kod varchar(10) NOT NULL,
 quiz_id bigint(20) unsigned NOT NULL DEFAULT 0,
 ogretmen_id bigint(20) unsigned NOT NULL DEFAULT 0,
 tur varchar(10) NOT NULL DEFAULT 'canli',
 baslik varchar(200) NOT NULL DEFAULT '',
 durum varchar(12) NOT NULL DEFAULT 'bekliyor',
 sure int(11) NOT NULL DEFAULT 0,
 baslangic bigint(20) NOT NULL DEFAULT 0,
 bitis bigint(20) NOT NULL DEFAULT 0,
 son_tarih bigint(20) NOT NULL DEFAULT 0,
 ayar text,
 veri longtext,
 olusturma bigint(20) NOT NULL DEFAULT 0,
 PRIMARY KEY  (id),
 UNIQUE KEY kod (kod),
 KEY ogretmen_id (ogretmen_id)
) $c;");
        dbDelta("CREATE TABLE {$this->t_kt()} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 oturum_id bigint(20) unsigned NOT NULL DEFAULT 0,
 ad varchar(80) NOT NULL DEFAULT '',
 token varchar(40) NOT NULL DEFAULT '',
 user_id bigint(20) unsigned NOT NULL DEFAULT 0,
 cevaplar mediumtext,
 ilerleme int(11) NOT NULL DEFAULT 0,
 durum varchar(12) NOT NULL DEFAULT 'bekliyor',
 baslama bigint(20) NOT NULL DEFAULT 0,
 bitis bigint(20) NOT NULL DEFAULT 0,
 son bigint(20) NOT NULL DEFAULT 0,
 dogru float NOT NULL DEFAULT 0,
 yanlis float NOT NULL DEFAULT 0,
 bos int(11) NOT NULL DEFAULT 0,
 net float NOT NULL DEFAULT 0,
 puan int(11) NOT NULL DEFAULT 0,
 sonuc mediumtext,
 PRIMARY KEY  (id),
 UNIQUE KEY token (token),
 KEY oturum_id (oturum_id),
 KEY user_id (user_id)
) $c;");
        dbDelta("CREATE TABLE {$this->t_cz()} (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 user_id bigint(20) unsigned NOT NULL DEFAULT 0,
 quiz_id bigint(20) unsigned NOT NULL DEFAULT 0,
 tarih bigint(20) NOT NULL DEFAULT 0,
 d float NOT NULL DEFAULT 0,
 y float NOT NULL DEFAULT 0,
 b int(11) NOT NULL DEFAULT 0,
 net float NOT NULL DEFAULT 0,
 puan int(11) NOT NULL DEFAULT 0,
 soru int(11) NOT NULL DEFAULT 0,
 url varchar(255) NOT NULL DEFAULT '',
 konu text,
 yanlis text,
 PRIMARY KEY  (id),
 KEY user_id (user_id)
) $c;");
        update_option('osc_sp_db', self::DB_SURUM);
    }
    public function sayfalari_kur() {
        if (!current_user_can('manage_options') || get_option('osc_sp_sayfalar') === '3') return;
        if (!get_option('osc_kvkk_sayfa') && !get_page_by_path('kvkk-aydinlatma-metni')) {
            $metin = strtr(/*__KVKK__*/'', array('{SITE}' => esc_html(get_bloginfo('name')), '{URL}' => esc_html(home_url()), '{EPOSTA}' => esc_html(get_option('admin_email')), '{TARIH}' => wp_date('d.m.Y')));
            $kid = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'KVKK Aydınlatma Metni', 'post_name' => 'kvkk-aydinlatma-metni', 'post_content' => $metin));
            if ($kid && !is_wp_error($kid)) update_option('osc_kvkk_sayfa', $kid);
        }
        $sayfalar = array('osc_sp_panel_sayfa' => array('Öğretmen Paneli', 'ogretmen-paneli', '[osc_ogretmen_paneli]'), 'osc_sp_katil_sayfa' => array('Sınava Katıl', 'sinava-katil', '[osc_katil]'), 'osc_sp_uye_sayfa' => array('Üye Ol / Giriş', 'uyelik', '[osc_uyelik]'), 'osc_sp_ogrenci_sayfa' => array('Öğrenci Paneli', 'ogrenci-paneli', '[osc_ogrenci_paneli]'));
        foreach ($sayfalar as $opt => $p) {
            $id = (int) get_option($opt);
            if ($id && get_post_status($id)) continue;
            $var = get_page_by_path($p[1]);
            $id = $var ? $var->ID : wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => $p[0], 'post_name' => $p[1], 'post_content' => $p[2]));
            if ($id && !is_wp_error($id)) update_option($opt, $id);
        }
        update_option('osc_sp_sayfalar', '3');
    }

    /* ---------------- yardımcılar ---------------- */
    private function oturum($where, $deger) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t_ot()} WHERE $where = %s", $deger));
    }
    private function ayar($ot) { $a = json_decode((string) $ot->ayar, true); return is_array($a) ? $a : array(); }
    private function veri($ot) { $v = json_decode((string) $ot->veri, true); return is_array($v) ? $v : array(); }
    private function kural($ot) { $a = $this->ayar($ot); return isset($a['kural']) ? (int) $a['kural'] : 4; }
    /** Gerçek durum: süresi geçen canlı oturum / son tarihi geçen ödev "bitti" sayılır */
    private function etkin_durum($ot) {
        $now = time();
        if ($ot->durum === 'bitti') return 'bitti';
        if ($ot->tur === 'odev') return ($ot->son_tarih && $now > $ot->son_tarih) ? 'bitti' : 'basladi';
        if ($ot->durum === 'basladi' && $ot->bitis && $now > $ot->bitis + 20) {
            global $wpdb; $wpdb->update($this->t_ot(), array('durum' => 'bitti'), array('id' => $ot->id)); $ot->durum = 'bitti';
            return 'bitti';
        }
        return $ot->durum;
    }
    private function kalan($ot, $kt) {
        $now = time();
        if ($ot->tur === 'canli') return ($ot->durum === 'basladi' && $ot->bitis) ? max(0, $ot->bitis - $now) : 0;
        if ($kt && $kt->bitis) return max(0, $kt->bitis - $now);
        return 0;
    }
    private function anahtar_acik($ot) { $a = $this->ayar($ot); return (isset($a['sonuc_goster']) && $a['sonuc_goster'] === 'hemen') || $this->etkin_durum($ot) === 'bitti'; }
    private function cevap_dizisi($json, $n) {
        $a = json_decode(wp_unslash((string) $json), true);
        if (!is_array($a)) return null;
        $o = array();
        for ($i = 0; $i < $n; $i++) {
            $v = isset($a[$i]) ? $a[$i] : null;
            $o[] = ($v === null || $v === '') ? null : (function_exists('mb_substr') ? mb_substr(sanitize_textarea_field((string) $v), 0, 2000) : substr(sanitize_textarea_field((string) $v), 0, 2000));
        }
        return $o;
    }
    private function sonuc_veri($ot, $kt) {
        $s = json_decode((string) $kt->sonuc, true);
        if (!is_array($s)) return null;
        $cev = json_decode((string) $kt->cevaplar, true);
        return array('sonuc' => $s, 'cevaplar' => is_array($cev) ? $cev : array(), 'anahtar' => $this->anahtar_acik($ot) ? self::anahtar($this->veri($ot)) : null);
    }
    private function bitir_katilimci($ot, $kt, $cev) {
        global $wpdb;
        $veri = $this->veri($ot);
        $r = self::puanla($veri, $cev, $this->kural($ot));
        $wpdb->update($this->t_kt(), array('cevaplar' => wp_json_encode($cev), 'durum' => 'bitti', 'bitis' => time(), 'son' => time(), 'dogru' => $r['d'], 'yanlis' => $r['y'], 'bos' => $r['b'], 'net' => $r['net'], 'puan' => $r['puan'], 'ilerleme' => count(array_filter($cev, function ($x) { return $x !== null && $x !== ''; })), 'sonuc' => wp_json_encode($r)), array('id' => $kt->id));
        $kt->durum = 'bitti'; $kt->sonuc = wp_json_encode($r); $kt->cevaplar = wp_json_encode($cev);
        return $r;
    }
    private function yeni_kod() {
        global $wpdb;
        for ($i = 0; $i < 40; $i++) {
            $k = ''; for ($j = 0; $j < 6; $j++) $k .= self::KOD_HARF[random_int(0, strlen(self::KOD_HARF) - 1)];
            if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t_ot()} WHERE kod = %s", $k))) return $k;
        }
        return strtoupper(wp_generate_password(8, false, false));
    }
    private function tarih($ts) { return $ts ? wp_date('d.m.Y H:i', $ts) : ''; }

    /* ---------------- öğrenci tarafı ---------------- */
    private function katilimci($ot) {
        if (!$ot) return null;
        $c = 'osc_tk_' . $ot->kod;
        if (empty($_COOKIE[$c])) return null;
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t_kt()} WHERE token = %s AND oturum_id = %d", sanitize_key($_COOKIE[$c]), $ot->id));
    }
    private function ajax_katilimci() {
        $kod = isset($_POST['kod']) ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', wp_unslash($_POST['kod']))) : '';
        $tok = isset($_POST['token']) ? sanitize_key(wp_unslash($_POST['token'])) : '';
        $ot = $kod ? $this->oturum('kod', $kod) : null;
        if (!$ot || !$tok) wp_send_json_error('Oturum bulunamadı.');
        global $wpdb;
        $kt = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t_kt()} WHERE token = %s AND oturum_id = %d", $tok, $ot->id));
        if (!$kt) wp_send_json_error('Katılım bulunamadı.');
        return array($ot, $kt);
    }
    public function ajax_durum() {
        list($ot, $kt) = $this->ajax_katilimci();
        global $wpdb;
        $d = $this->etkin_durum($ot);
        $ek = array('son' => time());
        if ($ot->tur === 'canli' && $d === 'basladi' && $kt->durum === 'bekliyor') { $ek['durum'] = 'cozuyor'; $ek['baslama'] = time(); }
        $wpdb->update($this->t_kt(), $ek, array('id' => $kt->id));
        $cevap = array('durum' => $d, 'kalan' => $this->kalan($ot, $kt), 'anahtar' => false);
        if ($kt->durum === 'bitti' && $this->anahtar_acik($ot)) { $cevap['anahtar'] = true; $cevap['sonuc_veri'] = $this->sonuc_veri($ot, $kt); }
        wp_send_json_success($cevap);
    }
    public function ajax_basla() {
        list($ot, $kt) = $this->ajax_katilimci();
        global $wpdb;
        if ($this->etkin_durum($ot) === 'bitti') wp_send_json_error('Bu ödevin süresi doldu.');
        if ($kt->durum === 'bitti') wp_send_json_error('Bu sınavı zaten teslim ettin.');
        if ($kt->durum === 'bekliyor') {
            $now = time();
            $bitis = $ot->sure > 0 ? $now + $ot->sure * 60 : 0;
            if ($ot->tur === 'odev' && $ot->son_tarih) $bitis = $bitis ? min($bitis, (int) $ot->son_tarih) : (int) $ot->son_tarih;
            if ($ot->tur === 'canli') $bitis = 0;
            $wpdb->update($this->t_kt(), array('durum' => 'cozuyor', 'baslama' => $now, 'bitis' => $bitis, 'son' => $now), array('id' => $kt->id));
            $kt->bitis = $bitis;
        }
        $cev = json_decode((string) $kt->cevaplar, true);
        wp_send_json_success(array('kalan' => $this->kalan($ot, $kt), 'cevaplar' => is_array($cev) ? $cev : null));
    }
    public function ajax_kaydet() {
        list($ot, $kt) = $this->ajax_katilimci();
        if ($kt->durum === 'bitti') wp_send_json_success(array('bitti' => true));
        $n = count($this->veri($ot));
        $cev = $this->cevap_dizisi(isset($_POST['cevaplar']) ? $_POST['cevaplar'] : '', $n);
        if ($cev === null) wp_send_json_error('Geçersiz veri.');
        global $wpdb;
        $ek = array('cevaplar' => wp_json_encode($cev), 'son' => time(), 'ilerleme' => count(array_filter($cev, function ($x) { return $x !== null && $x !== ''; })));
        if ($kt->durum === 'bekliyor') { $ek['durum'] = 'cozuyor'; $ek['baslama'] = time(); }
        $wpdb->update($this->t_kt(), $ek, array('id' => $kt->id));
        wp_send_json_success(array('ok' => true));
    }
    public function ajax_teslim() {
        list($ot, $kt) = $this->ajax_katilimci();
        if ($kt->durum !== 'bitti') {
            $n = count($this->veri($ot));
            $cev = $this->cevap_dizisi(isset($_POST['cevaplar']) ? $_POST['cevaplar'] : '', $n);
            $kayitli = json_decode((string) $kt->cevaplar, true);
            // süre bittikten çok sonra gelen teslimde en son kaydedilen cevaplar esas alınır
            $son = $ot->tur === 'canli' ? (int) $ot->bitis : (int) $kt->bitis;
            if ($ot->tur === 'canli' && $ot->durum === 'bitti' && !$son) $son = time();
            if ($cev === null || ($son && time() > $son + 120)) $cev = is_array($kayitli) ? $this->cevap_dizisi(wp_json_encode($kayitli), $n) : array_fill(0, $n, null);
            $this->bitir_katilimci($ot, $kt, $cev);
        }
        wp_send_json_success($this->sonuc_veri($ot, $kt));
    }
    private function ogrenci_ctx($ot, $kt) {
        $a = $this->ayar($ot);
        $cev = json_decode((string) $kt->cevaplar, true);
        $ctx = array(
            'kod' => $ot->kod, 'token' => $kt->token, 'ad' => $kt->ad, 'tur' => $ot->tur, 'durum' => $this->etkin_durum($ot),
            'sure' => (int) $ot->sure, 'kalan' => $this->kalan($ot, $kt), 'katilim_durum' => $kt->durum, 'cevaplar' => is_array($cev) ? $cev : null,
            'son_tarih_metin' => $ot->tur === 'odev' && $ot->son_tarih ? $this->tarih($ot->son_tarih) : '',
            '_veri' => $this->veri($ot),
        );
        if ($kt->durum === 'bitti') $ctx['sonuc_veri'] = $this->sonuc_veri($ot, $kt);
        return $ctx;
    }
    public function ogrenci_sayfa() {
        if (!isset($_GET['osc_k'])) return;
        nocache_headers();
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        $kod = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', wp_unslash($_GET['osc_k'])));
        $ot = $kod ? $this->oturum('kod', $kod) : null;
        $hata = ($kod && !$ot) ? 'Bu kodla bir sınav bulunamadı. Kodu kontrol edip tekrar dene.' : '';
        global $wpdb;
        if ($ot && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['osc_ad'])) {
            $ad = trim(preg_replace('/\s+/', ' ', sanitize_text_field(wp_unslash($_POST['osc_ad']))));
            $ad = function_exists('mb_substr') ? mb_substr($ad, 0, 60) : substr($ad, 0, 60);
            $ipk = 'osc_katil_ip_' . md5($this->ip()); $ips = (int) get_transient($ipk);
            if (!empty($_POST['osc_web']) || (!is_user_logged_in() && !$this->zaman_gecerli(1))) $hata = 'Form doğrulanamadı, lütfen tekrar deneyin.';
            elseif ($ips >= 120) $hata = 'Bu bağlantıdan çok fazla katılım yapıldı. Biraz sonra tekrar deneyin.';
            elseif (get_option('osc_uye_katil_rc') === '1' && !$this->rc_dogrula('katil')) $hata = 'Lütfen robot olmadığını doğrula.';
            elseif ((function_exists('mb_strlen') ? mb_strlen($ad) : strlen($ad)) < 3) $hata = 'Lütfen adını ve soyadını yaz.';
            elseif ($this->etkin_durum($ot) === 'bitti') $hata = 'Bu sınav oturumu sona erdi.';
            elseif ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->t_kt()} WHERE oturum_id = %d", $ot->id)) >= self::MAX_KATILIMCI) $hata = 'Bu oturum dolu.';
            else {
                set_transient($ipk, $ips + 1, 10 * MINUTE_IN_SECONDS);
                $tok = strtolower(wp_generate_password(32, false, false));
                $wpdb->insert($this->t_kt(), array('oturum_id' => $ot->id, 'user_id' => get_current_user_id(), 'ad' => $ad, 'token' => $tok, 'durum' => 'bekliyor', 'son' => time(), 'cevaplar' => '', 'sonuc' => ''));
                setcookie('osc_tk_' . $ot->kod, $tok, time() + 30 * DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true);
                wp_safe_redirect(self::ogrenci_url($ot->kod)); exit;
            }
        }
        $kt = $this->katilimci($ot);
        $baslik = $ot ? get_the_title($ot->quiz_id) : 'Sınava Katıl';
        header('Content-Type: text/html; charset=utf-8');
        ?><!DOCTYPE html>
<html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html(($ot ? $ot->baslik : 'Sınava Katıl') . ' — ' . get_bloginfo('name')); ?></title>
<link rel="stylesheet" href="<?php echo esc_url(includes_url('css/dashicons.min.css')); ?>">
<style>/*__OGRENCI_CSS__*/</style></head><body class="osc-ogr-body">
<header class="osc-ogr-ust"><a href="<?php echo esc_url(home_url('/')); ?>" class="osc-ogr-logo" aria-label="soruindir.net ana sayfa"><?php echo self::logo(36); ?></a><?php if ($ot): ?><span class="osc-ogr-rozet"><?php echo $ot->tur === 'odev' ? '📚 Ödev' : '🔴 Canlı sınıf'; ?></span><?php endif; ?></header>
<main class="osc-ogr-ana<?php echo ($ot && $kt) ? ' genis' : ''; ?>">
<?php if (!$ot): ?>
  <form class="osc-ogr-kart" method="get" action="<?php echo esc_url(home_url('/')); ?>">
    <div class="osc-ogr-ikon">🎓</div><h1>Sınava katıl</h1><p>Öğretmeninin verdiği 6 haneli kodu yaz.</p>
    <?php if ($hata): ?><div class="osc-ogr-hata"><?php echo esc_html($hata); ?></div><?php endif; ?>
    <input class="osc-ogr-kod" name="osc_k" maxlength="8" autocomplete="off" autocapitalize="characters" placeholder="ÖRN: K7M2PA" value="<?php echo esc_attr($kod); ?>" required autofocus>
    <button class="osc-ogr-btn" type="submit">Devam et →</button>
  </form>
<?php elseif (!$kt): $ogr = get_userdata($ot->ogretmen_id); $veri = $this->veri($ot); ?>
  <form class="osc-ogr-kart" method="post">
    <div class="osc-ogr-ikon"><?php echo $ot->tur === 'odev' ? '📚' : '✏️'; ?></div>
    <h1><?php echo esc_html($ot->baslik); ?></h1>
    <p class="osc-ogr-alt"><?php echo esc_html($baslik); ?></p>
    <div class="osc-ogr-bilgi"><span><?php echo count($veri); ?> soru</span><span><?php echo $ot->sure ? (int) $ot->sure . ' dk' : 'Süresiz'; ?></span><?php if ($ogr): ?><span>👩‍🏫 <?php echo esc_html($ogr->display_name); ?></span><?php endif; ?></div>
    <?php if ($ot->tur === 'odev' && $ot->son_tarih): ?><div class="osc-ogr-son">Son teslim: <b><?php echo esc_html($this->tarih($ot->son_tarih)); ?></b></div><?php endif; ?>
    <?php if ($hata): ?><div class="osc-ogr-hata"><?php echo esc_html($hata); ?></div><?php endif; ?>
    <?php if ($this->etkin_durum($ot) === 'bitti'): ?><div class="osc-ogr-hata">Bu sınav oturumu sona erdi.</div><?php else: ?>
    <label class="osc-ogr-etiket" for="osc-ad">Adın ve soyadın</label>
    <input id="osc-ad" class="osc-ogr-inp" name="osc_ad" maxlength="60" autocomplete="name" placeholder="Örn: Elif Yılmaz" value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->display_name) : ''; ?>" required autofocus>
    <input type="text" name="osc_web" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true"><?php echo $this->zaman_alani(); if (get_option('osc_uye_katil_rc') === '1') echo '<div style="margin-top:10px;display:flex;justify-content:center">' . $this->rc_alani('katil') . '</div>'; ?>
    <button class="osc-ogr-btn" type="submit">Katıl →</button>
    <small class="osc-ogr-kucuk"><?php if (is_user_logged_in()): ?>Sonucun öğrenci panelinde de saklanacak.<?php else: $uu = self::sayfa_url('osc_sp_uye_sayfa', array('git' => self::ogrenci_url($ot->kod))); ?>Adın yalnızca öğretmeninin raporunda görünür.<?php if ($uu): ?><br><a href="<?php echo esc_url($uu); ?>">Giriş yap</a>, sonuçların öğrenci panelinde saklansın.<?php endif; endif; ?></small>
    <?php endif; ?>
  </form>
<?php else:
    $quiz = isset($GLOBALS['osc_instance']) ? $GLOBALS['osc_instance'] : null;
    echo $quiz ? $quiz->get_quiz_html((int) $ot->quiz_id, $this->ogrenci_ctx($ot, $kt)) : 'Eklenti yüklenemedi.';
endif; ?>
</main>
<?php if ($ot && !$kt && get_option('osc_uye_katil_rc') === '1') $this->rc_betik(); ?>
<footer class="osc-ogr-alt-bilgi">soruindir.net · Online Sınav</footer>
</body></html><?php
        exit;
    }
    public function katil_kisa_kod() {
        ob_start(); ?>
<form class="osc-katil-kk" method="get" action="<?php echo esc_url(home_url('/')); ?>" style="max-width:420px;margin:20px auto;background:#fff;border:1px solid #e5e7eb;border-radius:20px;padding:26px;text-align:center;box-shadow:0 10px 30px -15px rgba(15,23,42,.25);font-family:inherit">
  <div style="display:flex;justify-content:center;margin-bottom:8px"><?php echo self::logo(38); ?></div>
  <h2 style="margin:6px 0 4px">Sınava katıl</h2>
  <p style="color:#64748b;margin:0 0 16px">Öğretmeninin verdiği kodu yaz.</p>
  <input name="osc_k" maxlength="8" required placeholder="ÖRN: K7M2PA" autocomplete="off" style="width:100%;box-sizing:border-box;font-size:26px;letter-spacing:.2em;text-align:center;text-transform:uppercase;padding:14px;border:2px solid #c7d2fe;border-radius:14px;font-weight:800">
  <button type="submit" style="margin-top:12px;width:100%;padding:15px;border:0;border-radius:14px;background:#4f46e5;color:#fff;font-weight:800;font-size:16px;cursor:pointer">Devam et →</button>
</form>
<?php return ob_get_clean();
    }

    /* ---------------- öğretmen paneli ---------------- */
    private function ogretmen_mi() { return is_user_logged_in() && self::rol() !== 'ogrenci'; }
    private function ogr_kontrol() {
        if (!$this->ogretmen_mi()) wp_send_json_error('Oturum açmanız gerekiyor.', 403);
        check_ajax_referer('osc_sp_ogr', 'nonce');
    }
    private function benim_oturum($id) {
        $ot = $this->oturum('id', (int) $id);
        if (!$ot || ((int) $ot->ogretmen_id !== get_current_user_id() && !current_user_can('manage_options'))) wp_send_json_error('Oturum bulunamadı.');
        return $ot;
    }
    private function test_ozet($p) {
        $data = get_post_meta($p->ID, '_osc_data', true);
        $set = get_post_meta($p->ID, '_osc_settings', true);
        if (!is_array($set)) $set = array();
        $n = is_array($data) ? count($data) : 0;
        $b = self::bilgi($p->ID, $set, $n);
        $sure = (isset($set['duration']) && $set['duration'] !== '') ? (int) $set['duration'] : $b['oto_sure'];
        $cats = get_the_category($p->ID);
        return array('id' => $p->ID, 'baslik' => get_the_title($p), 'soru' => $n, 'tur' => $b['ad'], 'kural' => $b['kural'], 'sure' => $sure, 'kategori' => $cats ? $cats[0]->name : '', 'benim' => (int) $p->post_author === get_current_user_id(), 'durum' => $p->post_status);
    }
    public function ajax_ara() {
        $this->ogr_kontrol();
        $q = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $o = array();
        if ($id) {
            $p = get_post($id);
            if ($p && $p->post_type === 'osc_quiz' && ($p->post_status === 'publish' || (int) $p->post_author === get_current_user_id())) $o[] = $this->test_ozet($p);
            wp_send_json_success($o);
        }
        $args = array('post_type' => 'osc_quiz', 'post_status' => 'publish', 'posts_per_page' => 25, 'orderby' => 'date', 'order' => 'DESC');
        if ($q !== '') $args['s'] = $q;
        $benim = get_posts(array_merge($args, array('post_status' => array('publish', 'pending', 'draft'), 'author' => get_current_user_id(), 'posts_per_page' => 10)));
        $gor = array();
        foreach (array_merge($benim, get_posts($args)) as $p) { if (isset($gor[$p->ID])) continue; $gor[$p->ID] = 1; $x = $this->test_ozet($p); if ($x['soru'] > 0) $o[] = $x; }
        wp_send_json_success($o);
    }
    public function ajax_olustur() {
        $this->ogr_kontrol();
        global $wpdb;
        $qid = isset($_POST['quiz_id']) ? (int) $_POST['quiz_id'] : 0;
        $p = get_post($qid);
        if (!$p || $p->post_type !== 'osc_quiz' || !($p->post_status === 'publish' || (int) $p->post_author === get_current_user_id())) wp_send_json_error('Test bulunamadı.');
        $data = get_post_meta($qid, '_osc_data', true);
        if (!is_array($data) || !$data) wp_send_json_error('Bu testte soru yok.');
        $set = get_post_meta($qid, '_osc_settings', true); if (!is_array($set)) $set = array();
        $b = self::bilgi($qid, $set, count($data));
        $tur = (isset($_POST['tur']) && $_POST['tur'] === 'odev') ? 'odev' : 'canli';
        $sure = max(0, min(600, isset($_POST['sure']) ? (int) $_POST['sure'] : 0));
        $son = 0;
        if ($tur === 'odev') {
            $st = isset($_POST['son_tarih']) ? sanitize_text_field(wp_unslash($_POST['son_tarih'])) : '';
            try { $dt = new DateTime($st, wp_timezone()); $son = $dt->getTimestamp(); } catch (Exception $e) { $son = 0; }
            if ($son && $son < time() + 300) wp_send_json_error('Son teslim tarihi ileri bir zaman olmalı.');
        }
        $baslik = isset($_POST['baslik']) ? sanitize_text_field(wp_unslash($_POST['baslik'])) : '';
        if ($baslik === '') $baslik = get_the_title($qid);
        $ayar = array('sonuc_goster' => (isset($_POST['sonuc_goster']) && $_POST['sonuc_goster'] === 'hemen') ? 'hemen' : 'bitince', 'kural' => $b['kural'], 'tur_ad' => $b['ad']);
        $kod = $this->yeni_kod();
        $ok = $wpdb->insert($this->t_ot(), array('kod' => $kod, 'quiz_id' => $qid, 'ogretmen_id' => get_current_user_id(), 'tur' => $tur, 'baslik' => function_exists('mb_substr') ? mb_substr($baslik, 0, 190) : substr($baslik, 0, 190), 'durum' => $tur === 'odev' ? 'basladi' : 'bekliyor', 'sure' => $sure, 'baslangic' => $tur === 'odev' ? time() : 0, 'son_tarih' => $son, 'ayar' => wp_json_encode($ayar), 'veri' => wp_json_encode(array_values($data)), 'olusturma' => time()));
        if (!$ok) wp_send_json_error('Oturum oluşturulamadı: ' . $wpdb->last_error);
        wp_send_json_success(array('id' => (int) $wpdb->insert_id, 'kod' => $kod));
    }
    public function ajax_oturumlar() {
        $this->ogr_kontrol();
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT o.id, o.kod, o.quiz_id, o.tur, o.baslik, o.durum, o.sure, o.bitis, o.son_tarih, o.olusturma, (SELECT COUNT(*) FROM {$this->t_kt()} k WHERE k.oturum_id = o.id) AS katilan, (SELECT COUNT(*) FROM {$this->t_kt()} k WHERE k.oturum_id = o.id AND k.durum = 'bitti') AS biten, (SELECT AVG(k.net) FROM {$this->t_kt()} k WHERE k.oturum_id = o.id AND k.durum = 'bitti') AS ort FROM {$this->t_ot()} o WHERE o.ogretmen_id = %d ORDER BY o.id DESC LIMIT 100", get_current_user_id()));
        $o = array();
        foreach ($rows as $r) {
            $r->durum = $this->etkin_durum($r);
            $o[] = array('id' => (int) $r->id, 'kod' => $r->kod, 'tur' => $r->tur, 'baslik' => $r->baslik, 'test' => get_the_title($r->quiz_id), 'durum' => $r->durum, 'katilan' => (int) $r->katilan, 'biten' => (int) $r->biten, 'ort' => $r->ort !== null ? round((float) $r->ort, 2) : null, 'tarih' => $this->tarih($r->olusturma), 'son_tarih' => $this->tarih($r->son_tarih));
        }
        wp_send_json_success($o);
    }
    public function ajax_izle() {
        $this->ogr_kontrol();
        global $wpdb;
        $ot = $this->benim_oturum(isset($_POST['id']) ? $_POST['id'] : 0);
        $d = $this->etkin_durum($ot);
        $veri = $this->veri($ot);
        $kts = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->t_kt()} WHERE oturum_id = %d ORDER BY id ASC", $ot->id));
        $now = time();
        $liste = array();
        foreach ($kts as $kt) {
            // oturum bittiği halde teslim etmeyenleri son kaydedilen cevaplarla değerlendir
            $kapandi = $d === 'bitti' && (($ot->tur === 'canli' && $now > (int) $ot->bitis + 30) || ($ot->tur === 'odev') || ($ot->tur === 'canli' && !$ot->bitis));
            if ($kt->durum === 'cozuyor' && ($kapandi || ($ot->tur === 'odev' && $kt->bitis && $now > $kt->bitis + 150))) {
                $cev = json_decode((string) $kt->cevaplar, true);
                $this->bitir_katilimci($ot, $kt, $this->cevap_dizisi(wp_json_encode(is_array($cev) ? $cev : array()), count($veri)));
                $kt = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t_kt()} WHERE id = %d", $kt->id));
            }
            $s = json_decode((string) $kt->sonuc, true);
            $cev = json_decode((string) $kt->cevaplar, true);
            $liste[] = array('id' => (int) $kt->id, 'ad' => $kt->ad, 'durum' => $kt->durum, 'ilerleme' => (int) $kt->ilerleme, 'cevrimici' => ($now - (int) $kt->son) < 25, 'd' => (float) $kt->dogru, 'y' => (float) $kt->yanlis, 'b' => (int) $kt->bos, 'net' => (float) $kt->net, 'puan' => (int) $kt->puan, 'sure' => ($kt->durum === 'bitti' && $kt->baslama) ? max(0, (int) $kt->bitis - (int) $kt->baslama) : 0, 'durumlar' => is_array($s) && isset($s['durum']) ? $s['durum'] : null, 'cevaplar' => !empty($_POST['rapor']) && is_array($cev) ? $cev : null);
        }
        $cevap = array(
            'id' => (int) $ot->id, 'kod' => $ot->kod, 'tur' => $ot->tur, 'baslik' => $ot->baslik, 'test' => get_the_title($ot->quiz_id), 'durum' => $d,
            'sure' => (int) $ot->sure, 'kalan' => $this->kalan($ot, null), 'son_tarih' => $this->tarih($ot->son_tarih), 'link' => self::ogrenci_url($ot->kod),
            'katil_link' => ($pid = (int) get_option('osc_sp_katil_sayfa')) ? get_permalink($pid) : home_url('/?osc_k='), 'soru' => count($veri), 'kural' => $this->kural($ot), 'ayar' => $this->ayar($ot), 'katilimcilar' => $liste,
        );
        if (!empty($_POST['rapor'])) {
            $sorular = array();
            foreach ($veri as $q) {
                $t = isset($q['type']) ? $q['type'] : 'multiple';
                $kok = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(html_entity_decode((isset($q['root']) ? $q['root'] : '') . '', ENT_QUOTES, 'UTF-8'))));
                $sorular[] = array('tur' => $t, 'kok' => function_exists('mb_substr') ? mb_substr($kok, 0, 140) : substr($kok, 0, 140), 'konu' => isset($q['konu']) ? (string) $q['konu'] : '', 'dogru' => ($t === 'gap' || $t === 'classic') ? wp_strip_all_tags(isset($q['options']['A']) ? $q['options']['A'] : '') : (isset($q['correct']) ? $q['correct'] : ''), 'secenekler' => $t === 'truefalse' ? array('A', 'B') : ($t === 'multiple' ? array_values(array_filter(array('A', 'B', 'C', 'D', 'E'), function ($k) use ($q) { return !empty($q['options'][$k]); })) : array()));
            }
            $cevap['sorular'] = $sorular;
        }
        wp_send_json_success($cevap);
    }
    public function ajax_komut() {
        $this->ogr_kontrol();
        global $wpdb;
        $ot = $this->benim_oturum(isset($_POST['id']) ? $_POST['id'] : 0);
        $islem = isset($_POST['islem']) ? sanitize_key($_POST['islem']) : '';
        $now = time();
        switch ($islem) {
            case 'baslat':
                if ($ot->tur !== 'canli' || $ot->durum !== 'bekliyor') wp_send_json_error('Oturum zaten başlamış.');
                $wpdb->update($this->t_ot(), array('durum' => 'basladi', 'baslangic' => $now, 'bitis' => $ot->sure > 0 ? $now + $ot->sure * 60 : 0), array('id' => $ot->id));
                $wpdb->query($wpdb->prepare("UPDATE {$this->t_kt()} SET durum = 'cozuyor', baslama = %d WHERE oturum_id = %d AND durum = 'bekliyor'", $now, $ot->id));
                break;
            case 'sure_ekle':
                $dk = max(1, min($ot->tur === 'odev' ? 10080 : 60, isset($_POST['dk']) ? (int) $_POST['dk'] : 5));
                if ($ot->tur === 'canli' && $ot->bitis) $wpdb->update($this->t_ot(), array('bitis' => max($now, (int) $ot->bitis) + $dk * 60, 'durum' => 'basladi'), array('id' => $ot->id));
                elseif ($ot->tur === 'odev' && $ot->son_tarih) $wpdb->update($this->t_ot(), array('son_tarih' => max($now, (int) $ot->son_tarih) + $dk * 60, 'durum' => 'basladi'), array('id' => $ot->id));
                break;
            case 'bitir':
                $wpdb->update($this->t_ot(), array('durum' => 'bitti', 'bitis' => $now), array('id' => $ot->id));
                break;
            case 'sil':
                $wpdb->delete($this->t_kt(), array('oturum_id' => $ot->id));
                $wpdb->delete($this->t_ot(), array('id' => $ot->id));
                break;
            case 'katilimci_sil':
                $wpdb->delete($this->t_kt(), array('id' => isset($_POST['kid']) ? (int) $_POST['kid'] : 0, 'oturum_id' => $ot->id));
                break;
            default: wp_send_json_error('Geçersiz işlem.');
        }
        wp_send_json_success(array('ok' => true));
    }

    /* ---------------- öğretmen testleri ---------------- */
    private function izinli_html() {
        $a = array('class' => true, 'style' => true);
        return array('b' => array(), 'strong' => array(), 'i' => array(), 'em' => array(), 'u' => array(), 'br' => array(), 'p' => $a, 'sub' => array(), 'sup' => array(), 'span' => $a, 'div' => $a,
            'img' => array('src' => true, 'alt' => true, 'width' => true, 'height' => true, 'style' => true, 'class' => true), 'table' => $a, 'tbody' => array(), 'thead' => array(), 'tr' => array(), 'td' => array('colspan' => true, 'rowspan' => true, 'style' => true), 'th' => array('colspan' => true, 'rowspan' => true, 'style' => true), 'ul' => array(), 'ol' => array(), 'li' => array());
    }
    private function temiz($s) { return trim(wp_kses((string) $s, $this->izinli_html())); }
    public function ajax_testlerim() {
        $this->ogr_kontrol();
        $ps = get_posts(array('post_type' => 'osc_quiz', 'post_status' => array('publish', 'pending', 'draft'), 'author' => get_current_user_id(), 'posts_per_page' => 200, 'orderby' => 'modified', 'order' => 'DESC'));
        $o = array();
        foreach ($ps as $p) {
            $x = $this->test_ozet($p);
            $x['red'] = (string) get_post_meta($p->ID, '_osc_red_notu', true);
            $x['tarih'] = get_the_modified_date('d.m.Y H:i', $p);
            $yz = (int) get_post_meta($p->ID, '_osc_yazi_id', true);
            $x['link'] = ($yz && get_post_status($yz) === 'publish') ? get_permalink($yz) : '';
            $o[] = $x;
        }
        wp_send_json_success($o);
    }
    public function ajax_test_al() {
        $this->ogr_kontrol();
        $p = get_post(isset($_POST['id']) ? (int) $_POST['id'] : 0);
        if (!$p || $p->post_type !== 'osc_quiz' || ((int) $p->post_author !== get_current_user_id() && $p->post_status !== 'publish')) wp_send_json_error('Test bulunamadı.');
        $set = get_post_meta($p->ID, '_osc_settings', true); if (!is_array($set)) $set = array();
        $cats = wp_get_post_categories($p->ID);
        wp_send_json_success(array('id' => (int) $p->post_author === get_current_user_id() && $p->post_status !== 'publish' ? $p->ID : 0, 'baslik' => $p->post_title, 'aciklama' => $p->post_excerpt, 'kategori' => $cats ? (int) $cats[0] : 0, 'sinav_turu' => isset($set['sinav_turu']) ? $set['sinav_turu'] : '', 'sure' => isset($set['duration']) ? $set['duration'] : '', 'sorular' => get_post_meta($p->ID, '_osc_data', true) ?: array(), 'durum' => $p->post_status));
    }
    public function ajax_test_kaydet() {
        $this->ogr_kontrol();
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id) {
            $p = get_post($id);
            if (!$p || $p->post_type !== 'osc_quiz' || (int) $p->post_author !== get_current_user_id()) wp_send_json_error('Test bulunamadı.');
            if ($p->post_status === 'publish') wp_send_json_error('Yayındaki test değiştirilemez. “Kopyala” ile yeni bir sürüm oluşturabilirsiniz.');
        }
        $baslik = isset($_POST['baslik']) ? sanitize_text_field(wp_unslash($_POST['baslik'])) : '';
        if ((function_exists('mb_strlen') ? mb_strlen($baslik) : strlen($baslik)) < 5) wp_send_json_error('Test başlığı en az 5 karakter olmalı.');
        $ham = json_decode(wp_unslash(isset($_POST['sorular']) ? $_POST['sorular'] : '[]'), true);
        if (!is_array($ham) || !$ham) wp_send_json_error('En az bir soru ekleyin.');
        if (count($ham) > 200) wp_send_json_error('Bir testte en fazla 200 soru olabilir.');
        $sorular = array();
        foreach ($ham as $i => $q) {
            $t = isset($q['type']) && in_array($q['type'], array('multiple', 'truefalse', 'gap', 'classic'), true) ? $q['type'] : 'multiple';
            $o = array('type' => $t, 'konu' => sanitize_text_field(isset($q['konu']) ? $q['konu'] : ''), 'paragraph' => $this->temiz(isset($q['paragraph']) ? $q['paragraph'] : ''), 'root' => $this->temiz(isset($q['root']) ? $q['root'] : ''), 'options' => array(), 'correct' => 'A');
            if ($o['root'] === '' && $o['paragraph'] === '') wp_send_json_error(($i + 1) . '. sorunun metni boş.');
            if ($t === 'truefalse') { $o['options'] = array('A' => 'Doğru', 'B' => 'Yanlış'); $o['correct'] = (isset($q['correct']) && $q['correct'] === 'B') ? 'B' : 'A'; }
            elseif ($t === 'gap' || $t === 'classic') { $o['options'] = array('A' => $this->temiz(isset($q['options']['A']) ? $q['options']['A'] : '')); if ($t === 'gap' && $o['options']['A'] === '') wp_send_json_error(($i + 1) . '. sorunun doğru cevabını yazın.'); }
            else {
                $dolu = 0;
                foreach (array('A', 'B', 'C', 'D', 'E') as $k) { $v = $this->temiz(isset($q['options'][$k]) ? $q['options'][$k] : ''); $o['options'][$k] = $v; if ($v !== '') $dolu++; }
                if ($dolu < 2) wp_send_json_error(($i + 1) . '. soruda en az iki şık olmalı.');
                $c = isset($q['correct']) ? strtoupper((string) $q['correct']) : '';
                if (!isset($o['options'][$c]) || $o['options'][$c] === '') wp_send_json_error(($i + 1) . '. sorunun doğru şıkkını işaretleyin.');
                $o['correct'] = $c;
            }
            $sorular[] = $o;
        }
        $gonder = !empty($_POST['gonder']);
        $tur = isset($_POST['sinav_turu']) && array_key_exists($_POST['sinav_turu'], self::turler()) ? sanitize_key($_POST['sinav_turu']) : '';
        $sure = isset($_POST['sure']) && $_POST['sure'] !== '' ? (string) max(0, min(600, (int) $_POST['sure'])) : '';
        $arr = array('post_type' => 'osc_quiz', 'post_title' => $baslik, 'post_excerpt' => sanitize_textarea_field(wp_unslash(isset($_POST['aciklama']) ? $_POST['aciklama'] : '')), 'post_status' => $gonder ? 'pending' : 'draft', 'post_author' => get_current_user_id());
        if ($id) { $arr['ID'] = $id; $id = wp_update_post($arr, true); } else $id = wp_insert_post($arr, true);
        if (is_wp_error($id)) wp_send_json_error($id->get_error_message());
        update_post_meta($id, '_osc_data', $sorular);
        update_post_meta($id, '_osc_settings', array('duration' => $sure, 'view_mode' => 'step', 'sinav_turu' => $tur, 'ad_freq' => '', 'ad_code' => ''));
        update_post_meta($id, '_osc_ogretmen', 1);
        delete_post_meta($id, '_osc_red_notu');
        $kat = isset($_POST['kategori']) ? (int) $_POST['kategori'] : 0;
        if ($kat && term_exists($kat, 'category')) wp_set_post_categories($id, array($kat));
        if ($gonder) {
            $u = wp_get_current_user();
            wp_mail(get_option('admin_email'), '[' . get_bloginfo('name') . '] Onay bekleyen yeni öğretmen testi', $u->display_name . ' (' . $u->user_email . ") yeni bir test gönderdi:\n\n" . $baslik . ' — ' . count($sorular) . " soru\n\nİncelemek ve onaylamak için:\n" . admin_url('post.php?post=' . $id . '&action=edit') . "\n\nOnay bekleyen tüm testler:\n" . admin_url('edit.php?post_type=osc_quiz&post_status=pending'));
        }
        wp_send_json_success(array('id' => $id, 'durum' => $gonder ? 'pending' : 'draft'));
    }
    public function ajax_test_sil() {
        $this->ogr_kontrol();
        $p = get_post(isset($_POST['id']) ? (int) $_POST['id'] : 0);
        if (!$p || $p->post_type !== 'osc_quiz' || (int) $p->post_author !== get_current_user_id() || $p->post_status === 'publish') wp_send_json_error('Bu test silinemez.');
        wp_trash_post($p->ID);
        wp_send_json_success(array('ok' => true));
    }
    public function ajax_gorsel() {
        $this->ogr_kontrol();
        $uid = get_current_user_id();
        $say = (int) get_transient('osc_sp_gorsel_' . $uid);
        if ($say > 80) wp_send_json_error('Saatlik görsel yükleme sınırına ulaştınız.');
        $data = isset($_POST['data']) ? wp_unslash($_POST['data']) : '';
        if (!preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $data)) wp_send_json_error('Geçersiz resim.');
        $bin = base64_decode(substr($data, strpos($data, ',') + 1), true);
        if ($bin === false || strlen($bin) < 20 || strlen($bin) > 3 * 1024 * 1024) wp_send_json_error('Resim en fazla 3 MB olabilir.');
        $info = function_exists('getimagesizefromstring') ? @getimagesizefromstring($bin) : false;
        $ext = array('image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp');
        if (!$info || empty($info['mime']) || !isset($ext[$info['mime']])) wp_send_json_error('Dosya bir resim değil.');
        $up = wp_upload_bits('ogretmen-soru-' . $uid . '-' . wp_generate_password(8, false, false) . '.' . $ext[$info['mime']], null, $bin);
        if (!empty($up['error'])) wp_send_json_error($up['error']);
        $att = wp_insert_attachment(array('post_mime_type' => $info['mime'], 'post_title' => 'Öğretmen soru görseli', 'post_content' => '', 'post_status' => 'inherit', 'post_author' => $uid), $up['file']);
        if ($att && !is_wp_error($att)) { require_once ABSPATH . 'wp-admin/includes/image.php'; wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $up['file'])); }
        set_transient('osc_sp_gorsel_' . $uid, $say + 1, HOUR_IN_SECONDS);
        wp_send_json_success(array('url' => $up['url']));
    }

    /* ---------------- yönetici onayı ---------------- */
    public function yayin_gecisi($yeni, $eski, $post) {
        if ($post->post_type !== 'osc_quiz' || $yeni !== 'publish' || $eski === 'publish' || !get_post_meta($post->ID, '_osc_ogretmen', true)) return;
        delete_post_meta($post->ID, '_osc_red_notu');
        $yz = (int) get_post_meta($post->ID, '_osc_yazi_id', true);
        $u = get_userdata($post->post_author);
        if (!$yz || !get_post($yz)) {
            $icerik = ($post->post_excerpt ? '<p>' . esc_html($post->post_excerpt) . '</p>' . "\n\n" : '') . '[osc_quiz id="' . $post->ID . '"]' . "\n\n" . ($u ? '<p class="osc-hazirlayan">Bu test öğretmenimiz <strong>' . esc_html($u->display_name) . '</strong> tarafından hazırlanmıştır.</p>' : '');
            $yz = wp_insert_post(array('post_type' => 'post', 'post_status' => 'publish', 'post_title' => $post->post_title, 'post_content' => $icerik, 'post_author' => $post->post_author, 'post_category' => wp_get_post_categories($post->ID)));
            if ($yz && !is_wp_error($yz)) { update_post_meta($post->ID, '_osc_yazi_id', $yz); $th = get_post_thumbnail_id($post->ID); if ($th) set_post_thumbnail($yz, $th); }
        }
        if ($u && $u->user_email) wp_mail($u->user_email, '[' . get_bloginfo('name') . '] Testiniz yayınlandı 🎉', 'Merhaba ' . $u->display_name . ",\n\n“" . $post->post_title . "” testiniz onaylandı ve yayınlandı.\n\n" . ($yz && !is_wp_error($yz) ? get_permalink($yz) . "\n\n" : '') . "Teşekkür ederiz!");
    }
    public function admin_onay() {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if (!current_user_can('publish_post', $id) || !wp_verify_nonce(isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '', 'osc_sp_onay_' . $id)) wp_die('Yetkiniz yok.');
        wp_update_post(array('ID' => $id, 'post_status' => 'publish'));
        wp_safe_redirect(admin_url('edit.php?post_type=osc_quiz&osc_sp=onay')); exit;
    }
    public function admin_red() {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if (!current_user_can('edit_post', $id) || !wp_verify_nonce(isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '', 'osc_sp_red_' . $id)) wp_die('Yetkiniz yok.');
        $not = isset($_GET['not']) ? sanitize_textarea_field(wp_unslash($_GET['not'])) : '';
        wp_update_post(array('ID' => $id, 'post_status' => 'draft'));
        update_post_meta($id, '_osc_red_notu', $not ? $not : 'Test yayın kurallarına uygun bulunmadı.');
        $p = get_post($id); $u = $p ? get_userdata($p->post_author) : null;
        if ($u && $u->user_email) wp_mail($u->user_email, '[' . get_bloginfo('name') . '] Testiniz için düzeltme gerekiyor', 'Merhaba ' . $u->display_name . ",\n\n“" . $p->post_title . "” testiniz şu an yayınlanamadı.\n\nNot: " . get_post_meta($id, '_osc_red_notu', true) . "\n\nDüzeltip yeniden göndermek için öğretmen paneline girebilirsiniz:\n" . self::panel_url());
        wp_safe_redirect(admin_url('edit.php?post_type=osc_quiz&osc_sp=red')); exit;
    }
    public function satir_eylemleri($a, $post) {
        if ($post->post_type !== 'osc_quiz' || !get_post_meta($post->ID, '_osc_ogretmen', true) || $post->post_status === 'publish') return $a;
        if (current_user_can('publish_post', $post->ID)) {
            $a = array('osc_onay' => '<a style="color:#008a20;font-weight:600" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=osc_sp_onay&id=' . $post->ID), 'osc_sp_onay_' . $post->ID)) . '">✔ Onayla ve yayınla</a>', 'osc_red' => '<a style="color:#b32d2e" href="#" onclick="var n=prompt(\'Öğretmene iletilecek düzeltme notu:\');if(n!==null){location.href=\'' . esc_js(wp_nonce_url(admin_url('admin-post.php?action=osc_sp_red&id=' . $post->ID), 'osc_sp_red_' . $post->ID)) . '&not=\'+encodeURIComponent(n);}return false;">✖ Reddet</a>') + $a;
        }
        return $a;
    }
    public function sutun_ekle($c) { $c['osc_gonderen'] = 'Gönderen'; return $c; }
    public function sutun_goster($col, $id) {
        if ($col !== 'osc_gonderen') return;
        if (!get_post_meta($id, '_osc_ogretmen', true)) { echo '<span style="color:#999">Yönetici</span>'; return; }
        $p = get_post($id); $u = get_userdata($p->post_author);
        $st = array('pending' => '<span style="background:#fff3cd;color:#8a6100;padding:2px 8px;border-radius:10px;font-weight:600">Onay bekliyor</span>', 'draft' => get_post_meta($id, '_osc_red_notu', true) ? '<span style="background:#fde7e5;color:#b42318;padding:2px 8px;border-radius:10px">Reddedildi</span>' : '<span style="color:#666">Taslak</span>', 'publish' => '<span style="background:#ddf4e8;color:#0f7b4b;padding:2px 8px;border-radius:10px">Yayında</span>');
        echo '👩‍🏫 ' . esc_html($u ? $u->display_name : '?') . '<br>' . (isset($st[$p->post_status]) ? $st[$p->post_status] : esc_html($p->post_status));
    }
    private function bekleyen_say() {
        $q = new WP_Query(array('post_type' => 'osc_quiz', 'post_status' => 'pending', 'meta_key' => '_osc_ogretmen', 'posts_per_page' => 1, 'fields' => 'ids'));
        return (int) $q->found_posts;
    }
    public function menu_rozet() {
        global $menu;
        if (!current_user_can('edit_others_posts') || !is_array($menu)) return;
        $n = $this->bekleyen_say();
        if (!$n) return;
        foreach ($menu as $k => $m) if (isset($m[2]) && $m[2] === 'edit.php?post_type=osc_quiz') $menu[$k][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . $n . '</span></span>';
    }
    public function admin_bildirim() {
        if (!current_user_can('edit_others_posts')) return;
        $sc = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$sc || $sc->post_type !== 'osc_quiz') return;
        if (isset($_GET['osc_sp'])) echo '<div class="notice notice-success is-dismissible"><p>' . ($_GET['osc_sp'] === 'onay' ? 'Test onaylandı; sitede yayın yazısı oluşturuldu ve öğretmene e-posta gönderildi.' : 'Test reddedildi ve öğretmene not iletildi.') . '</p></div>';
        $n = $this->bekleyen_say();
        if ($n && $sc->base === 'edit') echo '<div class="notice notice-warning"><p><strong>' . $n . ' öğretmen testi onay bekliyor.</strong> <a href="' . esc_url(admin_url('edit.php?post_type=osc_quiz&post_status=pending')) . '">Onay bekleyenleri göster</a> · Satırdaki “Onayla ve yayınla” testi yayınlar ve sitede otomatik bir yazı oluşturur.</p></div>';
    }

    /* ---------------- panel kısa kodu ---------------- */
    public function panel_kisa_kod() {
        if (!$this->ogretmen_mi()) {
            $uu = self::sayfa_url('osc_sp_uye_sayfa', array('git' => get_permalink(), 'rol' => 'ogretmen'));
            $og = is_user_logged_in();
            return '<div class="osc-uy-kucuk">' . self::logo(34) . '<div class="ik">👩‍🏫</div><h2>Öğretmen Paneli</h2><p>' . ($og ? 'Bu sayfa öğretmen hesapları içindir. Öğrenci hesabıyla giriş yaptınız.' : 'Canlı sınıf sınavı başlatmak, ödev linki göndermek ve test eklemek için öğretmen hesabınızla giriş yapın.') . '</p>' . ($og ? '<a class="osc-uy-btn" href="' . esc_url(self::sayfa_url('osc_sp_ogrenci_sayfa')) . '">Öğrenci paneline git</a>' : ($uu ? '<a class="osc-uy-btn" href="' . esc_url($uu) . '">Giriş yap / Öğretmen olarak kaydol</a>' : '<a class="osc-uy-btn" href="' . esc_url(wp_login_url(get_permalink())) . '">Giriş yap</a>')) . '</div>' . $this->uy_kucuk_css();
        }
        $u = wp_get_current_user();
        $kat = array();
        $ekle = function ($parent, $d) use (&$ekle, &$kat) { foreach (get_categories(array('hide_empty' => false, 'parent' => $parent, 'orderby' => 'name')) as $c) { $kat[] = array('id' => $c->term_id, 'ad' => str_repeat('— ', $d) . $c->name); $ekle($c->term_id, $d + 1); } };
        $ekle(0, 0);
        $turler = array(); foreach (self::turler() as $k => $t) $turler[] = array('k' => $k, 'ad' => $t['ad'], 'kural' => $t['kural'], 'dk' => $t['dk']);
        $cfg = array('ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('osc_sp_ogr'), 'ad' => $u->display_name, 'kategoriler' => $kat, 'turler' => $turler, 'yeni' => isset($_GET['yeni']) ? (int) $_GET['yeni'] : 0, 'cikis' => wp_logout_url(), 'site' => get_bloginfo('name'), 'lib' => add_query_arg('osc_import_lib', '22', home_url('/')), 'logo' => self::logo_src(), 'home' => home_url('/'), 'ogrenci' => self::sayfa_url('osc_sp_ogrenci_sayfa'), 'yazili' => class_exists('OSC_Yazili') && OSC_Yazili::gorebilir() ? self::sayfa_url('osc_yz_sayfa') : '', 'yz' => class_exists('OSC_Yazili') && OSC_Yazili::gorebilir() ? OSC_Yazili::cfg(true) : null, 'yz_js' => class_exists('OSC_Yazili') ? OSC_Yazili::kaynak_url('js') : '', 'yz_css' => class_exists('OSC_Yazili') ? OSC_Yazili::kaynak_url('css') : '');
        ob_start(); ?>
<div id="osc-pn" class="osc-pn"><div class="osc-pn-yukleniyor">Yükleniyor…</div></div>
<style>/*__PANEL_CSS__*/</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>window.OSC_PN = <?php echo wp_json_encode($cfg); ?>;</script>
<script>/*__PANEL_JS__*/</script>
<?php return ob_get_clean();
    }
    /*__EK_METODLAR__*/
}
$GLOBALS['osc_sinav'] = new OSC_Sinav();
}
