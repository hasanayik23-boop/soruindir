
/* =====================================================================
 * V22.3 — Soru Havuzu: yazılı arşivinden (zip/docx/pdf) soru ayıklama,
 *          MEB senaryolarına (öğrenme çıktılarına) göre eşleştirme
 * ===================================================================== */
if (!class_exists('OSC_Havuz')) {
class OSC_Havuz {
    const DB = '1';
    public function __construct() {
        add_action('init', array($this, 'db_kur'), 6);
        add_action('admin_menu', array($this, 'menu'));
        foreach (array('kaynaklar', 'dosya', 'ekle', 'isaretle', 'liste', 'guncelle', 'ozet', 'sifirla', 'senaryo_kaydet', 'senaryo_sil') as $a) add_action('wp_ajax_osc_hv_' . $a, array($this, 'ajax_' . $a));
        add_action('transition_post_status', array($this, 'ogretmen_testi'), 20, 3);
    }
    public static function tablo() { global $wpdb; return $wpdb->prefix . 'osc_havuz'; }
    public function db_kur() {
        if (get_option('osc_hv_db') === self::DB) return;
        global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta('CREATE TABLE ' . self::tablo() . " (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 hash varchar(32) NOT NULL DEFAULT '',
 kaynak varchar(20) NOT NULL DEFAULT '',
 kaynak_url text,
 kaynak_baslik varchar(255) NOT NULL DEFAULT '',
 sinif tinyint(4) NOT NULL DEFAULT 0,
 ders varchar(60) NOT NULL DEFAULT '',
 donem tinyint(4) NOT NULL DEFAULT 0,
 yazili tinyint(4) NOT NULL DEFAULT 0,
 cikti_kod varchar(40) NOT NULL DEFAULT '',
 cikti_puan float NOT NULL DEFAULT 0,
 tur varchar(12) NOT NULL DEFAULT 'classic',
 soru longtext,
 durum varchar(12) NOT NULL DEFAULT 'inceleme',
 kullanim int(11) NOT NULL DEFAULT 0,
 olusturma bigint(20) NOT NULL DEFAULT 0,
 PRIMARY KEY  (id),
 UNIQUE KEY hash (hash),
 KEY sd (sinif, ders),
 KEY cikti_kod (cikti_kod),
 KEY durum (durum)
) " . $wpdb->get_charset_collate() . ';');
        update_option('osc_hv_db', self::DB);
    }
    /** MEB senaryo tabloları: gömülü 1. dönem + yöneticinin yüklediği (2. dönem, güncellemeler) */
    public static function senaryolar($yenile = false) {
        static $d = null;
        if ($d === null || $yenile) {
            $d = json_decode(/*__SENARYO_JSON__*/'', true);
            $vd = (int) (isset($d['donem']) ? $d['donem'] : 1) ?: 1;
            foreach ($d['tablolar'] as &$t) if (empty($t['donem'])) $t['donem'] = $vd; unset($t);
            $y = get_option('osc_senaryo_yuklenen', array());
            if (is_array($y) && $y) {
                $idx = array(); foreach ($d['tablolar'] as $i => $t) $idx[$t['sinif'] . '|' . $t['ders'] . '|' . $t['donem']] = $i;
                foreach ($y as $t) { $k = $t['sinif'] . '|' . $t['ders'] . '|' . $t['donem']; if (isset($idx[$k])) $d['tablolar'][$idx[$k]] = $t; else { $d['tablolar'][] = $t; $idx[$k] = count($d['tablolar']) - 1; } }
            }
            $dn = array(); foreach ($d['tablolar'] as $t) $dn[(int) $t['donem']] = 1; ksort($dn);
            $d['donemler'] = array_keys($dn); $d['donem'] = max($d['donemler']);
        }
        return $d;
    }
    /** sınıf + ders (+ dönem) tablosu; dönem verilmezse tüm dönemlerin çıktıları birleşik */
    public static function tablo_bul($sinif, $ders, $donem = 0) {
        $bir = null;
        foreach (self::senaryolar()['tablolar'] as $t) {
            if ((int) $t['sinif'] !== (int) $sinif || $t['ders'] !== $ders) continue;
            if ($donem) { if ((int) $t['donem'] === (int) $donem) return $t; continue; }
            if (!$bir) $bir = $t; else $bir['satirlar'] = array_merge($bir['satirlar'], $t['satirlar']);
        }
        return $bir;
    }
    public function ajax_senaryo_kaydet() {
        $this->yetki();
        $l = json_decode(wp_unslash(isset($_POST['tablolar']) ? $_POST['tablolar'] : '[]'), true); if (!is_array($l)) wp_send_json_error('Veri okunamadı.');
        $y = get_option('osc_senaryo_yuklenen', array()); if (!is_array($y)) $y = array();
        $map = array(); foreach ($y as $t) $map[$t['sinif'] . '|' . $t['ders'] . '|' . $t['donem']] = $t;
        $n = 0;
        foreach (array_slice($l, 0, 120) as $t) {
            if (!is_array($t)) continue;
            $sinif = (int) (isset($t['sinif']) ? $t['sinif'] : 0); $donem = (int) (isset($t['donem']) ? $t['donem'] : 0); $ders = sanitize_text_field(isset($t['ders']) ? $t['ders'] : '');
            if ($sinif < 1 || $sinif > 12 || !in_array($donem, array(1, 2), true) || $ders === '') continue;
            $sat = array();
            foreach (array_slice(isset($t['satirlar']) && is_array($t['satirlar']) ? $t['satirlar'] : array(), 0, 120) as $x) {
                if (!is_array($x) || empty($x['cikti'])) continue;
                $sayi = array();
                foreach (array('1', '2') as $yz) if (!empty($x['sayi'][$yz]) && is_array($x['sayi'][$yz])) foreach ($x['sayi'][$yz] as $sn => $v) if ((int) $v > 0) $sayi[$yz][(string) (int) $sn] = (int) $v;
                $sat[] = array('unite' => sanitize_text_field(isset($x['unite']) ? $x['unite'] : ''), 'konu' => sanitize_text_field(isset($x['konu']) ? $x['konu'] : ''), 'kod' => sanitize_text_field(isset($x['kod']) ? $x['kod'] : ''), 'cikti' => sanitize_text_field($x['cikti']), 'sayi' => $sayi);
            }
            if (!$sat) continue;
            $snr = array('1' => array(), '2' => array()); $ulke = array('1' => false, '2' => false);
            foreach (array('1', '2') as $yz) { foreach ((array) (isset($t['senaryolar'][$yz]) ? $t['senaryolar'][$yz] : array()) as $sn) $snr[$yz][] = (int) $sn; $ulke[$yz] = !empty($t['ulke'][$yz]); }
            $map[$sinif . '|' . $ders . '|' . $donem] = array('kademe' => $sinif <= 8 ? 'ortaokul' : 'lise', 'ders' => $ders, 'sinif' => $sinif, 'donem' => $donem, 'baslik' => sanitize_text_field(isset($t['baslik']) ? $t['baslik'] : ''), 'senaryolar' => $snr, 'ulke' => $ulke, 'satirlar' => $sat, 'yuklendi' => time());
            $n++;
        }
        update_option('osc_senaryo_yuklenen', array_values($map), false);
        update_option('osc_senaryo_surum', time());
        wp_send_json_success(array('n' => $n, 'snr' => self::senaryolar(true)));
    }
    public function ajax_senaryo_sil() {
        $this->yetki();
        $k = sanitize_text_field(wp_unslash(isset($_POST['k']) ? $_POST['k'] : ''));
        $y = get_option('osc_senaryo_yuklenen', array()); if (!is_array($y)) $y = array();
        $y = array_values(array_filter($y, function ($t) use ($k) { return $t['sinif'] . '|' . $t['ders'] . '|' . $t['donem'] !== $k; }));
        update_option('osc_senaryo_yuklenen', $y, false); update_option('osc_senaryo_surum', time());
        wp_send_json_success(array('snr' => self::senaryolar(true)));
    }
    public static function yazilimatik() { static $d = null; if ($d === null) $d = json_decode(/*__YAZILIMATIK_JSON__*/'', true); return $d; }
    private function yetki() { if (!current_user_can('edit_others_posts')) wp_send_json_error('Yetkiniz yok.', 403); check_ajax_referer('osc_hv', 'nonce'); }
    public function menu() { add_submenu_page('edit.php?post_type=osc_quiz', 'Soru Havuzu', 'Soru Havuzu', 'edit_others_posts', 'osc-soru-havuzu', array($this, 'sayfa')); }

    /* ---- kaynak dosyaları bul: kategori yazıları + yazılımatik listesi ---- */
    private static function dosya_linkleri($html) {
        $o = array();
        if (preg_match_all('#(?:href|src|data-url|data-href)=["\']([^"\']+\.(?:zip|docx|doc|pdf|txt))(?:\?[^"\']*)?["\']#i', (string) $html, $m)) foreach ($m[1] as $u) $o[] = html_entity_decode($u);
        if (preg_match_all('#https?://[^\s"\'<>\]]+\.(?:zip|docx|doc|pdf)\b#i', (string) $html, $m)) foreach ($m[0] as $u) $o[] = $u;
        return array_values(array_unique($o));
    }
    public function ajax_kaynaklar() {
        $this->yetki();
        wp_send_json_success(self::kaynak_listesi(sanitize_title(isset($_POST['kategori']) ? wp_unslash($_POST['kategori']) : 'yazili-sorulari'), !empty($_POST['tum']), !empty($_POST['yazilimatik'])));
    }
    public static function kaynak_listesi($slug, $tum_site, $yzm) {
        if (!$slug) $slug = 'yazili-sorulari';
        $islenen = get_option('osc_hv_islenen', array()); if (!is_array($islenen)) $islenen = array();
        $o = array(); $gor = array();
        $ekle = function ($url, $baslik, $kat, $etiket, $kaynak) use (&$o, &$gor, $islenen) {
            $url = esc_url_raw($url); if (!$url) return;
            if (strpos($url, '//') === 0) $url = 'https:' . $url;
            if (strpos($url, 'http') !== 0) $url = home_url('/' . ltrim($url, '/'));
            $k = md5(strtolower(preg_replace('#^https?://(www\.)?#', '', $url)));
            if (isset($gor[$k])) return; $gor[$k] = 1;
            $o[] = array('k' => $k, 'url' => $url, 'baslik' => $baslik, 'kategori' => $kat, 'etiket' => $etiket, 'kaynak' => $kaynak, 'islendi' => isset($islenen[$k]) && empty($islenen[$k]['gecici']) ? $islenen[$k] : null);
        };
        $yazi_isle = function ($p) use ($ekle) {
            $kat = implode(' / ', wp_list_pluck(get_the_category($p->ID), 'name'));
            $urls = self::dosya_linkleri($p->post_content);
            foreach (get_post_meta($p->ID) as $mk => $mv) foreach ((array) $mv as $v) if (is_string($v) && preg_match('#\.(zip|docx|pdf)(\?|$)#i', $v)) { if (preg_match('#^\d+$#', $v)) continue; $urls = array_merge($urls, preg_match('#^https?://#', $v) ? array($v) : self::dosya_linkleri($v)); }
            foreach (get_attached_media('', $p->ID) as $at) { $u = wp_get_attachment_url($at->ID); if ($u && preg_match('#\.(zip|docx|doc|pdf)$#i', $u)) $urls[] = $u; }
            foreach (array_unique($urls) as $u) $ekle($u, get_the_title($p), $kat, '', 'arsiv');
        };
        $term = get_category_by_slug($slug); $taranan = array();
        if ($term) {
            $ids = array_merge(array($term->term_id), get_term_children($term->term_id, 'category'));
            $sayfa = 1;
            do {
                $q = new WP_Query(array('post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 200, 'paged' => $sayfa, 'category__in' => $ids, 'fields' => 'all', 'no_found_rows' => false));
                foreach ($q->posts as $p) { $taranan[$p->ID] = 1; $yazi_isle($p); }
                $sayfa++;
            } while ($sayfa <= $q->max_num_pages && $sayfa < 50);
        }
        // sitenin tamamı: başlığında "yazılı" geçen bütün yazılar (kategori fark etmeksizin)
        if ($tum_site) {
            global $wpdb;
            $idler = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND (post_title LIKE '%yazılı%' OR post_title LIKE '%Yazılı%' OR post_title LIKE '%YAZILI%' OR post_title LIKE '%yazili%' OR post_title LIKE '%Yazili%' OR post_name LIKE '%yazili%') ORDER BY ID DESC LIMIT 20000");
            foreach ($idler as $id) { if (isset($taranan[$id])) continue; $p = get_post($id); if ($p) { $taranan[$id] = 1; $yazi_isle($p); } }
        }
        if ($yzm) {
            $dm = array('TURKCE' => 'Türkçe', 'MATEMATIK' => 'Matematik', 'FENBILIMLERI' => 'Fen Bilimleri', 'SOSYALBILGILER' => 'Sosyal Bilgiler', 'INGILIZCE' => 'İngilizce', 'DINKULTURU' => 'Din Kültürü ve Ahlak Bilgisi', 'INKILAPTARIHI' => 'T.C. İnkılap Tarihi ve Atatürkçülük', 'TURKDILIVEEDEBIYATI' => 'Türk Dili ve Edebiyatı', 'FIZIK' => 'Fizik', 'KIMYA' => 'Kimya', 'BIYOLOJI' => 'Biyoloji', 'TARIH' => 'Tarih', 'COGRAFYA' => 'Coğrafya', 'FELSEFE' => 'Felsefe');
            foreach ((array) self::yazilimatik() as $y) {
                $s = (int) $y['class']; $d = isset($dm[$y['lesson']]) ? $dm[$y['lesson']] : ucwords(strtolower($y['lesson']));
                $ekle($y['url'], $s . '. Sınıf ' . $d . ' ' . $y['term'] . '. Dönem ' . $y['exam'] . '. Yazılı', 'Yazılımatik', array('sinif' => $s, 'ders' => $d, 'donem' => (int) $y['term'], 'yazili' => (int) $y['exam']), 'yazilimatik');
            }
        }
        return array('kaynaklar' => $o, 'kategori_var' => (bool) $term, 'yazi' => count($taranan));
    }
    /* Dosyayı sunucudan oku (aynı alan adındaki uploads klasörü; CORS/www sorunlarını önler) */
    public function ajax_dosya() {
        if (!current_user_can('edit_others_posts') || !wp_verify_nonce(isset($_GET['nonce']) ? $_GET['nonce'] : '', 'osc_hv')) { status_header(403); exit; }
        $url = esc_url_raw(wp_unslash(isset($_GET['url']) ? $_GET['url'] : ''));
        $up = wp_upload_dir();
        $yol = wp_parse_url($url, PHP_URL_PATH); $taban = wp_parse_url($up['baseurl'], PHP_URL_PATH);
        $host = preg_replace('/^www\./', '', (string) wp_parse_url($url, PHP_URL_HOST)); $ev = preg_replace('/^www\./', '', (string) wp_parse_url(home_url(), PHP_URL_HOST));
        $dosya = '';
        if ($yol && $taban && $host === $ev && strpos(rawurldecode($yol), $taban . '/') === 0) {
            $dosya = $up['basedir'] . substr(rawurldecode($yol), strlen($taban));
            $gercek = realpath($dosya);
            if (!$gercek || strpos($gercek, realpath($up['basedir'])) !== 0 || !is_file($gercek)) $dosya = '';
            else $dosya = $gercek;
        }
        if (!preg_match('#\.(zip|docx|doc|pdf|txt)$#i', (string) $dosya)) {
            // başka sunucudaki dosya: güvenli indirme
            if ($host !== $ev) { $r = wp_safe_remote_get($url, array('timeout' => 60, 'limit_response_size' => 60 * 1024 * 1024)); if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) { status_header(404); exit; } header('Content-Type: application/octet-stream'); echo wp_remote_retrieve_body($r); exit; }
            status_header(404); exit;
        }
        nocache_headers(); header('Content-Type: application/octet-stream'); header('Content-Length: ' . filesize($dosya));
        readfile($dosya); exit;
    }
    public static function temiz($s) {
        $izin = array('b' => array(), 'strong' => array(), 'i' => array(), 'em' => array(), 'u' => array(), 'br' => array(), 'p' => array(), 'sub' => array(), 'sup' => array(), 'span' => array('style' => true), 'img' => array('src' => true, 'alt' => true, 'style' => true, 'width' => true, 'height' => true), 'table' => array('class' => true), 'tbody' => array(), 'tr' => array(), 'td' => array('colspan' => true, 'rowspan' => true), 'th' => array(), 'ul' => array(), 'ol' => array(), 'li' => array());
        return trim(wp_kses((string) $s, $izin));
    }
    /** Soru metninden site adı/bağlantı, puan notu ve kâğıt yönergesi gibi soruya ait olmayan her şeyi ayıklar */
    public static function arindir($h) {
        $h = (string) $h; if ($h === '') return '';
        $h = preg_replace('#<a\b[^>]*>(.*?)</a>#isu', '$1', $h);
        $h = self::tex_duzelt($h);
        $h = preg_replace('#(https?://|www\.)\S+#iu', '', $h);
        $h = preg_replace('/\b[\p{L}\p{N}\-]+(\.[\p{L}\p{N}\-]+)*\.(com|net|org|biz|info|gen|edu|k12|tr|xyz|site|online)(\.tr)?\b\/?\S*/iu', '', $h);
        $h = preg_replace('/(bu\s+)?(sınav|soru|yazılı|döküman|belge)[^<.\n]{0,40}(sitesinden|adresinden)[^<\n]*/iu', '', $h);
        $h = preg_replace('/(daha fazla|diğer)\s+(soru|yazılı|sınav|döküman)[^<\n]{0,80}/iu', '', $h);
        // puan notları: (10 P), (5x2=10 puan), [8 puan], 10p., 15 Puan
        $h = preg_replace('/[\(\[]\s*\d+\s*[x×X\*]\s*\d+\s*=?\s*\d*\s*(puan|pts|pn|p)?\.?\s*[\)\]]/iu', '', $h);
        $h = preg_replace('/[\(\[]\s*\d+([,\.]\d+)?\s*(puan|pts|pn|p)\.?\s*[\)\]]/iu', '', $h);
        $h = preg_replace('/[\(\[]\s*(puan|p)\s*[:=]?\s*\d+\s*[\)\]]/iu', '', $h);
        $h = preg_replace('/(?<![\p{L}\p{N}])\d+\s*[x×X\*]\s*\d+\s*=\s*\d+\s*(puan|p)\b\.?/iu', '', $h);
        $h = preg_replace('/(?<![\p{L}\p{N}=+\-])\d{1,3}\s*(puan|pn)\b\.?(?=\s*($|<|\n))/iu', '', $h);
        $h = preg_replace('/(tüm|bütün|her)\s+(sorular|soru)[^<\n]{0,40}puan[^<\n]*/iu', '', $h);
        $h = preg_replace('/(başarılar|basarilar)\s*(dilerim|dileriz)?[^<\n]{0,30}/iu', '', $h);
        $h = preg_replace('/(<br\s*\/?>\s*){3,}/iu', '<br><br>', $h);
        $h = preg_replace('/^(\s|<br\s*\/?>)+|(\s|<br\s*\/?>)+$/iu', '', $h);
        $h = preg_replace('/[ \t]{2,}/u', ' ', $h);
        return trim($h);
    }
    /** Bozuk LaTeX: \\frac -> \frac, "frac{" -> "\frac{" (yalnızca $…$ içinde) */
    public static function tex_duzelt($h) {
        if (strpos($h, '$') === false) return $h;
        return preg_replace_callback('/\$([^$]{1,600})\$/u', function ($m) {
            $t = preg_replace('/\\\\{2,}(?=[a-zA-Z])/', '\\\\', $m[1]);
            $t = preg_replace('/(^|[^\\\\a-zA-Z])(d?frac|sqrt|times|cdot|div|overline)(?=[\{\[\s\d])/', '$1\\\\$2', $t);
            $ac = substr_count($t, '{') - substr_count($t, '}'); if ($ac > 0 && $ac < 4) $t .= str_repeat('}', $ac);
            return '$' . $t . '$';
        }, $h);
    }
    /** Bir soruyu arındırır; temizlendikten sonra boş kalırsa null döner */
    public static function arindir_soru($s) {
        if (!is_array($s)) return null;
        foreach (array('paragraph', 'root') as $k) if (isset($s[$k])) $s[$k] = self::arindir($s[$k]);
        if (!empty($s['options']) && is_array($s['options']) && (isset($s['type']) && $s['type'] === 'multiple')) foreach ($s['options'] as $k => $v) $s['options'][$k] = self::arindir($v);
        $m = (isset($s['paragraph']) ? $s['paragraph'] : '') . (isset($s['root']) ? $s['root'] : '');
        if (trim(wp_strip_all_tags($m)) === '' && strpos($m, '<img') === false) return null;
        return $s;
    }
    public static function hash_soru($q) {
        $t = strtolower(remove_accents(wp_strip_all_tags((isset($q['paragraph']) ? $q['paragraph'] : '') . ' ' . (isset($q['root']) ? $q['root'] : ''))));
        return md5(preg_replace('/[^a-z0-9]+/', '', $t));
    }
    public static function ekle_dizi($liste, $kaynak_ust = '') {
        global $wpdb; $n = 0; $tekrar = 0; $t = self::tablo(); $idler = array();
        foreach ((array) $liste as $x) {
            $s = isset($x['soru']) && is_array($x['soru']) ? $x['soru'] : array();
            $tur = isset($s['type']) && in_array($s['type'], array('multiple', 'truefalse', 'gap', 'classic'), true) ? $s['type'] : 'classic';
            $soru = array('type' => $tur, 'paragraph' => self::arindir(self::temiz(isset($s['paragraph']) ? $s['paragraph'] : '')), 'root' => self::arindir(self::temiz(isset($s['root']) ? $s['root'] : '')), 'options' => array(), 'correct' => isset($s['correct']) ? substr(sanitize_text_field($s['correct']), 0, 4) : '');
            foreach ((array) (isset($s['options']) ? $s['options'] : array()) as $k => $v) if (preg_match('/^[A-E]$/', $k)) $soru['options'][$k] = self::temiz($v);
            if (!empty($s['zorluk'])) $soru['zorluk'] = max(1, min(3, (int) $s['zorluk']));
            if (!empty($s['cozum'])) $soru['cozum'] = self::temiz($s['cozum']);
            if (trim(wp_strip_all_tags($soru['root'] . $soru['paragraph'])) === '' && strpos($soru['root'] . $soru['paragraph'], '<img') === false) continue;
            $h = self::hash_soru($soru);
            $var = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE hash = %s", $h));
            if ($var) { $tekrar++; $idler[] = $var; continue; }
            $wpdb->insert($t, array('hash' => $h, 'kaynak' => sanitize_key(isset($x['kaynak']) ? $x['kaynak'] : $kaynak_ust), 'kaynak_url' => esc_url_raw(isset($x['kaynak_url']) ? $x['kaynak_url'] : ''), 'kaynak_baslik' => mb_substr(sanitize_text_field(isset($x['kaynak_baslik']) ? $x['kaynak_baslik'] : ''), 0, 250), 'sinif' => (int) (isset($x['sinif']) ? $x['sinif'] : 0), 'ders' => mb_substr(sanitize_text_field(isset($x['ders']) ? $x['ders'] : ''), 0, 60), 'donem' => (int) (isset($x['donem']) ? $x['donem'] : 0), 'yazili' => (int) (isset($x['yazili']) ? $x['yazili'] : 0), 'cikti_kod' => mb_substr(sanitize_text_field(isset($x['cikti_kod']) ? $x['cikti_kod'] : ''), 0, 40), 'cikti_puan' => (float) (isset($x['cikti_puan']) ? $x['cikti_puan'] : 0), 'tur' => $tur, 'soru' => wp_json_encode($soru), 'durum' => in_array(isset($x['durum']) ? $x['durum'] : '', array('onayli', 'inceleme', 'eslesmedi'), true) ? $x['durum'] : 'inceleme', 'olusturma' => time()));
            if ($wpdb->insert_id) { $n++; $idler[] = (int) $wpdb->insert_id; } else $idler[] = 0;
        }
        return array('eklenen' => $n, 'tekrar' => $tekrar, 'idler' => $idler);
    }
    public function ajax_ekle() {
        $this->yetki();
        $l = json_decode(wp_unslash(isset($_POST['sorular']) ? $_POST['sorular'] : '[]'), true);
        wp_send_json_success(self::ekle_dizi(is_array($l) ? array_slice($l, 0, 400) : array()));
    }
    public function ajax_isaretle() {
        $this->yetki();
        $k = sanitize_key(isset($_POST['k']) ? $_POST['k'] : ''); if (!$k) wp_send_json_error();
        $i = get_option('osc_hv_islenen', array()); if (!is_array($i)) $i = array();
        $i[$k] = array('t' => time(), 'n' => (int) (isset($_POST['n']) ? $_POST['n'] : 0), 'hata' => mb_substr(sanitize_text_field(wp_unslash(isset($_POST['hata']) ? $_POST['hata'] : '')), 0, 200), 'gecici' => empty($_POST['gecici']) ? 0 : 1);
        update_option('osc_hv_islenen', $i, false);
        wp_send_json_success();
    }
    public function ajax_sifirla() { $this->yetki(); delete_option('osc_hv_islenen'); wp_send_json_success(); }
    public function ajax_liste() {
        $this->yetki(); global $wpdb; $t = self::tablo();
        $w = array('1=1'); $a = array();
        if (!empty($_POST['sinif'])) { $w[] = 'sinif = %d'; $a[] = (int) $_POST['sinif']; }
        if (!empty($_POST['ders'])) { $w[] = 'ders = %s'; $a[] = sanitize_text_field(wp_unslash($_POST['ders'])); }
        if (!empty($_POST['durum'])) { $w[] = 'durum = %s'; $a[] = sanitize_key($_POST['durum']); }
        if (isset($_POST['cikti']) && $_POST['cikti'] !== '') { $w[] = 'cikti_kod = %s'; $a[] = sanitize_text_field(wp_unslash($_POST['cikti'])); }
        if (!empty($_POST['ara'])) { $w[] = 'soru LIKE %s'; $a[] = '%' . $wpdb->esc_like(sanitize_text_field(wp_unslash($_POST['ara']))) . '%'; }
        $lim = min(500, max(1, (int) (isset($_POST['adet']) ? $_POST['adet'] : 40))); $off = max(0, (int) (isset($_POST['bas']) ? $_POST['bas'] : 0));
        $sql = "FROM $t WHERE " . implode(' AND ', $w);
        $top = (int) ($a ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) $sql", $a)) : $wpdb->get_var("SELECT COUNT(*) $sql"));
        $q = "SELECT * $sql ORDER BY id DESC LIMIT $lim OFFSET $off";
        $rows = $a ? $wpdb->get_results($wpdb->prepare($q, $a)) : $wpdb->get_results($q);
        foreach ($rows as $r) $r->soru = json_decode($r->soru, true);
        wp_send_json_success(array('toplam' => $top, 'satirlar' => $rows));
    }
    public function ajax_guncelle() {
        $this->yetki(); global $wpdb; $t = self::tablo();
        $ids = array_filter(array_map('intval', (array) (isset($_POST['ids']) ? $_POST['ids'] : array())));
        if (!$ids) wp_send_json_error('Seçim yok.');
        $in = implode(',', $ids);
        if (!empty($_POST['sil'])) { $wpdb->query("DELETE FROM $t WHERE id IN ($in)"); wp_send_json_success(array('silinen' => count($ids))); }
        $set = array();
        if (isset($_POST['durum'])) $set['durum'] = in_array($_POST['durum'], array('onayli', 'inceleme', 'eslesmedi'), true) ? $_POST['durum'] : 'inceleme';
        if (isset($_POST['cikti_kod'])) { $set['cikti_kod'] = mb_substr(sanitize_text_field(wp_unslash($_POST['cikti_kod'])), 0, 40); if (isset($_POST['cikti_puan'])) $set['cikti_puan'] = (float) $_POST['cikti_puan']; }
        if (isset($_POST['sinif'])) $set['sinif'] = (int) $_POST['sinif'];
        if (isset($_POST['ders'])) $set['ders'] = sanitize_text_field(wp_unslash($_POST['ders']));
        if (isset($_POST['soru'])) { $s = json_decode(wp_unslash($_POST['soru']), true); if (is_array($s) && count($ids) === 1) { $set['soru'] = wp_json_encode(array('type' => $s['type'] ?? 'classic', 'paragraph' => self::temiz($s['paragraph'] ?? ''), 'root' => self::temiz($s['root'] ?? ''), 'options' => array_map(array($this, 'temiz'), (array) ($s['options'] ?? array())), 'correct' => sanitize_text_field($s['correct'] ?? ''))); } }
        if (!$set) wp_send_json_error('Değişiklik yok.');
        foreach ($ids as $id) $wpdb->update($t, $set, array('id' => $id));
        wp_send_json_success(array('guncellenen' => count($ids)));
    }
    public function ajax_ozet() {
        $this->yetki(); global $wpdb; $t = self::tablo();
        $rows = $wpdb->get_results("SELECT sinif, ders, durum, cikti_kod, COUNT(*) AS n FROM $t GROUP BY sinif, ders, durum, cikti_kod");
        $ar = get_option('osc_hv_islenen', array());
        wp_send_json_success(array('satirlar' => $rows, 'islenen' => is_array($ar) ? count($ar) : 0));
    }
    /* Öğretmen testi onaylanınca soruları havuza (eşleştirme bekleyen olarak) ekle */
    public function ogretmen_testi($yeni, $eski, $post) {
        if ($post->post_type !== 'osc_quiz' || $yeni !== 'publish' || $eski === 'publish' || !get_post_meta($post->ID, '_osc_ogretmen', true)) return;
        $data = get_post_meta($post->ID, '_osc_data', true); if (!is_array($data)) return;
        $kat = implode(' ', wp_list_pluck(get_the_category($post->ID), 'name'));
        $meta = self::meta_tahmin($post->post_title . ' ' . $kat);
        $l = array();
        foreach ($data as $q) $l[] = array_merge($meta, array('kaynak' => 'ogretmen', 'kaynak_url' => get_permalink((int) get_post_meta($post->ID, '_osc_yazi_id', true)), 'kaynak_baslik' => $post->post_title . (isset($q['konu']) && $q['konu'] ? ' · ' . $q['konu'] : ''), 'durum' => ($meta['sinif'] && $meta['ders']) ? 'inceleme' : 'eslesmedi', 'soru' => $q));
        self::ekle_dizi($l);
    }
    public static function meta_tahmin($metin) {
        $m = function_exists('mb_strtolower') ? mb_strtolower(str_replace(array('I', 'İ'), array('ı', 'i'), $metin), 'UTF-8') : strtolower($metin);
        $o = array('sinif' => 0, 'ders' => '', 'donem' => 0, 'yazili' => 0);
        if (preg_match('/(\d{1,2})\s*\.?\s*sınıf/u', $m, $x)) $o['sinif'] = (int) $x[1];
        if (preg_match('/(\d)\s*\.?\s*dönem/u', $m, $x)) $o['donem'] = (int) $x[1];
        if (preg_match('/(\d)\s*\.?\s*(yazılı|sınav|ortak)/u', $m, $x)) $o['yazili'] = (int) $x[1];
        $dl = array('türk dili' => 'Türk Dili ve Edebiyatı', 'edebiyat' => 'Türk Dili ve Edebiyatı', 'türkçe' => 'Türkçe', 'matematik' => 'Matematik', 'fen bilim' => 'Fen Bilimleri', 'sosyal bilgi' => 'Sosyal Bilgiler', 'inkılap' => 'T.C. İnkılap Tarihi ve Atatürkçülük', 'ingilizce' => 'İngilizce', 'din kültür' => 'Din Kültürü ve Ahlak Bilgisi', 'fizik' => 'Fizik', 'kimya' => 'Kimya', 'biyoloji' => 'Biyoloji', 'tarih' => 'Tarih', 'coğrafya' => 'Coğrafya', 'felsefe' => 'Felsefe');
        foreach ($dl as $k => $v) if (strpos($m, $k) !== false) { $o['ders'] = $v; break; }
        return $o;
    }

    public function sayfa() {
        $cfg = array('ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('osc_hv'), 'img_nonce' => wp_create_nonce('osc_img_upload'), 'lib' => add_query_arg('osc_import_lib', '22', home_url('/')));
        ?>
<div class="wrap osc-hv"><h1>Soru Havuzu</h1>
<p class="osc-hv-alt">Yazılı arşivindeki (zip, Word, PDF) sorular ayıklanır, MEB 2026-2027 konu-soru dağılım tablolarındaki öğrenme çıktılarıyla eşleştirilir. Yazılı hazırlama aracı soruları buradan seçer. Onaylanan öğretmen testlerindeki sorular da buraya düşer.</p>
<nav class="osc-hv-sek"><button data-s="ozet" class="on">Özet</button><button data-s="aktar">Arşivden aktar</button><button data-s="ekle">➕ Soru ekle</button><button data-s="uret">🤖 Yapay zekâ ile doldur</button><button data-s="senaryo">📋 Senaryolar</button><button data-s="havuz">Havuzu incele</button></nav>
<div id="osc-hv"></div></div>
<style>/*__HAVUZ_CSS__*/</style>
<script>window.OSC_HV = <?php echo wp_json_encode($cfg); ?>; window.OSC_SNR = <?php echo wp_json_encode(self::senaryolar()); ?>;</script>
<script>/*__HAVUZ_JS__*/</script>
<?php
    }
}
$GLOBALS['osc_havuz'] = new OSC_Havuz();
}
