
/* =====================================================================
 * TOPLULUK: XP / rozet / haftalık liderlik, Soru-Cevap, kullanım
 * istatistiği ve hatırlatma e-postaları
 * ===================================================================== */
if (!class_exists('OSC_Oyun')) {

/** Ortak yardımcılar */
class OSC_Tk {
    public static function kisa_ad($u) {
        $u = is_object($u) ? $u : get_userdata($u); if (!$u) return 'Üye';
        $p = preg_split('/\s+/u', trim($u->display_name)); if (!$p || $p[0] === '') return 'Üye';
        $ad = $p[0]; if (count($p) > 1) $ad .= ' ' . mb_substr(end($p), 0, 1, 'UTF-8') . '.';
        return $ad;
    }
    public static function okul_k($s) { $s = trim(preg_replace('/\s+/u', ' ', mb_strtolower(strtr((string) $s, array('İ' => 'i', 'I' => 'ı')), 'UTF-8'))); return $s === '' ? '' : substr(md5($s), 0, 16); }
    public static function seviye($s) { return preg_match('/(\d{1,2})/', (string) $s, $m) ? (int) $m[1] : 0; }
    public static function rol($uid) { $r = get_user_meta($uid, 'osc_rol', true); return $r ? $r : (user_can($uid, 'edit_others_posts') ? 'yonetici' : 'ogrenci'); }
    public static function yol() { return isset($_SERVER['REQUEST_URI']) ? trim(strtok(wp_unslash($_SERVER['REQUEST_URI']), '?'), '/') : ''; }
    public static function ip() { return isset($_SERVER['REMOTE_ADDR']) ? preg_replace('/[^0-9a-f:.]/i', '', $_SERVER['REMOTE_ADDR']) : ''; }
    /** sanal sayfa: tema başlığı + içerik + tema altlığı */
    public static function sayfa($baslik, $aciklama, $html, $kanonik, $ldjson = null) {
        global $wp_query; $wp_query->is_404 = false; status_header(200);
        add_filter('pre_get_document_title', function () use ($baslik) { return $baslik . ' | ' . get_bloginfo('name'); }, 99);
        add_filter('body_class', function ($c) { return array_diff($c, array('error404')); });
        add_action('wp_head', function () use ($aciklama, $kanonik, $ldjson, $baslik) {
            echo '<meta name="description" content="' . esc_attr(wp_trim_words($aciklama, 30, '…')) . '">' . "\n" . '<link rel="canonical" href="' . esc_url($kanonik) . '">' . "\n";
            echo '<meta property="og:title" content="' . esc_attr($baslik) . '"><meta property="og:description" content="' . esc_attr(wp_trim_words($aciklama, 30, '…')) . '"><meta property="og:url" content="' . esc_url($kanonik) . '"><meta property="og:type" content="article">' . "\n";
            if ($ldjson) echo '<script type="application/ld+json">' . wp_json_encode($ldjson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        }, 1);
        get_header(); echo $html; get_footer(); exit;
    }
    public static function css() { return '--i:#13304F;--i2:#5B7189;--c:#E3EBF3;--m:#2F80ED;--y:#22A06B;font-family:Figtree,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--i)'; }
}

/* ---------------------------------------------------------------------
 * XP, seviye, rozet ve haftalık liderlik
 * ------------------------------------------------------------------- */
class OSC_Oyun {
    public static $rozetler = array(
        'ilk'     => array('🌱', 'İlk adım', 'İlk testini çöz'),
        't10'     => array('📘', 'Çalışkan', '10 test çöz'),
        't50'     => array('📚', 'Kitap kurdu', '50 test çöz'),
        't100'    => array('🏛️', 'Test ustası', '100 test çöz'),
        'tam1'    => array('💯', 'Kusursuz', 'Bir testten 100 puan al'),
        'tam10'   => array('🎯', 'Keskin nişancı', '10 testten 100 puan al'),
        'seri3'   => array('🔥', 'Isınıyor', '3 gün üst üste test çöz'),
        'seri7'   => array('⚡', 'Bir hafta durmadan', '7 gün üst üste test çöz'),
        'seri30'  => array('🌋', 'Durdurulamaz', '30 gün üst üste test çöz'),
        'odev5'   => array('📝', 'Sorumluluk sahibi', '5 ödev ya da sınav teslim et'),
        'yardim'  => array('🤝', 'Yardımsever', 'Soru-Cevap’ta 10 cevap yaz'),
        'cozum'   => array('🧠', 'Çözüm ortağı', 'Bir cevabın çözüm olarak seçilsin'),
        'cozum10' => array('🏆', 'Usta çözücü', '10 cevabın çözüm olarak seçilsin'),
    );
    public function __construct() {
        add_action('osc_cozum_kaydedildi', array($this, 'cozum'), 10, 3);
        add_action('osc_teslim_edildi', array($this, 'teslim'), 10, 3);
        add_action('wp_ajax_osc_oyun_ozet', array($this, 'ajax_ozet'));
        add_action('wp_ajax_osc_oyun_profil', array($this, 'ajax_profil'));
        add_shortcode('osc_liderlik', '__return_empty_string');
        add_action('wp_footer', array($this, 'panel_betik'), 50);

    }
    public static function hafta($t = null) { return wp_date('oW', $t ? $t : time()); }
    public static function hk($h = null) { return 'osc_xph_' . ($h ? $h : self::hafta()); }
    public static function seviye($xp) { return (int) floor(sqrt(max(0, $xp) / 40)) + 1; }
    public static function seviye_xp($sv) { return ($sv - 1) * ($sv - 1) * 40; }
    public static function ekle($uid, $xp, $neden = '') {
        $uid = (int) $uid; $xp = (int) $xp; if (!$uid || !$xp) return;
        update_user_meta($uid, 'osc_xp', (int) get_user_meta($uid, 'osc_xp', true) + $xp);
        $k = self::hk(); update_user_meta($uid, $k, (int) get_user_meta($uid, $k, true) + $xp);
        $ks = get_user_meta($uid, 'osc_xph_k', true); $ks = is_array($ks) ? $ks : array();
        if (!in_array($k, $ks, true)) { $ks[] = $k; while (count($ks) > 3) delete_user_meta($uid, array_shift($ks)); update_user_meta($uid, 'osc_xph_k', $ks); }
        $l = get_user_meta($uid, 'osc_xp_log', true); $l = is_array($l) ? $l : array();
        array_unshift($l, array('x' => $xp, 'n' => $neden, 't' => time())); update_user_meta($uid, 'osc_xp_log', array_slice($l, 0, 15));
    }
    private static function say($uid, $k, $n = 1) { $v = (int) get_user_meta($uid, $k, true) + $n; update_user_meta($uid, $k, $v); return $v; }
    public function cozum($uid, $qid, $puan) {
        if (!$uid) return;
        $gun = wp_date('Ymd'); $g = get_user_meta($uid, 'osc_xp_gun', true);
        if (!is_array($g) || ($g['d'] ?? '') !== $gun) $g = array('d' => $gun, 'q' => array());
        $tekrar = in_array((int) $qid, $g['q'], true); $g['q'][] = (int) $qid; $g['q'] = array_slice(array_unique($g['q']), -60); update_user_meta($uid, 'osc_xp_gun', $g);
        self::ekle($uid, $tekrar ? 2 : 10 + (int) floor($puan / 10), $tekrar ? 'Test tekrarı' : 'Test çözüldü (' . (int) $puan . ' puan)');
        if (!$tekrar) self::say($uid, 'osc_n_test');
        if ($puan >= 100 && !$tekrar) self::say($uid, 'osc_n_tam');
        // seri
        $son = (string) get_user_meta($uid, 'osc_seri_gun', true); $dun = wp_date('Ymd', time() - DAY_IN_SECONDS);
        if ($son !== $gun) { $s = $son === $dun ? (int) get_user_meta($uid, 'osc_seri', true) + 1 : 1; update_user_meta($uid, 'osc_seri', $s); update_user_meta($uid, 'osc_seri_gun', $gun); if ($s > (int) get_user_meta($uid, 'osc_seri_max', true)) update_user_meta($uid, 'osc_seri_max', $s); if ($s > 1) self::ekle($uid, min(10, $s), $s . ' günlük seri'); }
        self::rozet_kontrol($uid);
    }
    public function teslim($uid, $ot, $r) {
        if (!$uid) return;
        self::ekle($uid, 20 + (int) floor(((int) ($r['puan'] ?? 0)) / 10), ($ot->tur === 'odev' ? 'Ödev' : 'Sınav') . ' teslim edildi');
        self::say($uid, 'osc_n_odev'); self::rozet_kontrol($uid);
    }
    public static function rozet_ver($uid, $k) {
        $r = get_user_meta($uid, 'osc_rozet', true); $r = is_array($r) ? $r : array();
        if (isset($r[$k]) || !isset(self::$rozetler[$k])) return false;
        $r[$k] = time(); update_user_meta($uid, 'osc_rozet', $r);
        $y = get_user_meta($uid, 'osc_rozet_yeni', true); $y = is_array($y) ? $y : array(); $y[] = $k; update_user_meta($uid, 'osc_rozet_yeni', array_slice($y, -5));
        self::ekle($uid, 25, 'Rozet: ' . self::$rozetler[$k][1]);
        return true;
    }
    public static function rozet_kontrol($uid) {
        $n = (int) get_user_meta($uid, 'osc_n_test', true); $tam = (int) get_user_meta($uid, 'osc_n_tam', true); $seri = (int) get_user_meta($uid, 'osc_seri_max', true); $od = (int) get_user_meta($uid, 'osc_n_odev', true);
        $cv = (int) get_user_meta($uid, 'osc_n_cevap', true); $kb = (int) get_user_meta($uid, 'osc_n_kabul', true);
        $kosul = array('ilk' => $n >= 1, 't10' => $n >= 10, 't50' => $n >= 50, 't100' => $n >= 100, 'tam1' => $tam >= 1, 'tam10' => $tam >= 10, 'seri3' => $seri >= 3, 'seri7' => $seri >= 7, 'seri30' => $seri >= 30, 'odev5' => $od >= 5, 'yardim' => $cv >= 10, 'cozum' => $kb >= 1, 'cozum10' => $kb >= 10);
        foreach ($kosul as $k => $v) if ($v) self::rozet_ver($uid, $k);
    }
    /** haftalık lig: kapsam genel | seviye | okul | sinif */
    public static function lig($kapsam, $uid = 0, $limit = 10, $hafta = null, $seviye = 0) {
        global $wpdb; $k = self::hk($hafta); $um = $wpdb->usermeta;
        $okul = $uid ? (string) get_user_meta($uid, 'osc_okul_k', true) : ''; $sv = $uid ? (int) get_user_meta($uid, 'osc_sinif_k', true) : (int) $seviye;
        $j = "JOIN $um r ON r.user_id = x.user_id AND r.meta_key = 'osc_rol' AND r.meta_value = 'ogrenci' LEFT JOIN $um g ON g.user_id = x.user_id AND g.meta_key = 'osc_lig_gizli'"; $w = "x.meta_key = %s AND (g.meta_value IS NULL OR g.meta_value = '' OR g.meta_value = '0')"; $p = array($k);
        if ($kapsam === 'okul' || $kapsam === 'sinif') { if ($okul === '') return array('liste' => array(), 'sira' => 0, 'eksik' => 'okul'); $j .= " JOIN $um o ON o.user_id = x.user_id AND o.meta_key = 'osc_okul_k' AND o.meta_value = %s"; array_unshift($p, $okul); }
        if ($kapsam === 'seviye' || $kapsam === 'sinif') { if (!$sv) return array('liste' => array(), 'sira' => 0, 'eksik' => 'sinif'); $j .= " JOIN $um s ON s.user_id = x.user_id AND s.meta_key = 'osc_sinif_k' AND s.meta_value = %s"; $p = $kapsam === 'sinif' ? array($okul, (string) $sv, $k) : array((string) $sv, $k); }
        $sat = $wpdb->get_results($wpdb->prepare("SELECT x.user_id, x.meta_value AS xp FROM $um x $j WHERE $w ORDER BY (x.meta_value + 0) DESC, x.user_id ASC LIMIT " . (int) $limit, $p));
        $liste = array(); foreach ($sat as $i => $s) { $u = get_userdata($s->user_id); if (!$u) continue; $liste[] = array('s' => $i + 1, 'ad' => OSC_Tk::kisa_ad($u), 'xp' => (int) $s->xp, 'sv' => self::seviye((int) get_user_meta($s->user_id, 'osc_xp', true)), 'ben' => (int) $s->user_id === (int) $uid ? 1 : 0); }
        $sira = 0;
        if ($uid) { $bx = (int) get_user_meta($uid, $k, true); if ($bx > 0) { $pp = $p; $pp[] = $bx; $sira = 1 + (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $um x $j WHERE $w AND (x.meta_value + 0) > %d", $pp)); } }
        return array('liste' => $liste, 'sira' => $sira);
    }
    public static function ozet($uid) {
        $xp = (int) get_user_meta($uid, 'osc_xp', true); $sv = self::seviye($xp);
        $r = get_user_meta($uid, 'osc_rozet', true); $r = is_array($r) ? $r : array();
        $roz = array(); foreach (self::$rozetler as $k => $v) $roz[] = array('k' => $k, 'ik' => $v[0], 'ad' => $v[1], 'ac' => $v[2], 'var' => isset($r[$k]) ? wp_date('j M Y', $r[$k]) : '');
        $yeni = get_user_meta($uid, 'osc_rozet_yeni', true); delete_user_meta($uid, 'osc_rozet_yeni');
        $okul = (string) get_user_meta($uid, 'osc_okul', true); $sinif = (string) get_user_meta($uid, 'osc_sinif', true);
        if (!get_user_meta($uid, 'osc_sinif_k', true) && $sinif) update_user_meta($uid, 'osc_sinif_k', OSC_Tk::seviye($sinif));
        if ($okul && !get_user_meta($uid, 'osc_okul_k', true)) update_user_meta($uid, 'osc_okul_k', OSC_Tk::okul_k($okul));
        $seri_gun = (string) get_user_meta($uid, 'osc_seri_gun', true); $seri = in_array($seri_gun, array(wp_date('Ymd'), wp_date('Ymd', time() - DAY_IN_SECONDS)), true) ? (int) get_user_meta($uid, 'osc_seri', true) : 0;
        return array('xp' => $xp, 'sv' => $sv, 'sv_bas' => self::seviye_xp($sv), 'sv_son' => self::seviye_xp($sv + 1), 'hafta' => (int) get_user_meta($uid, self::hk(), true), 'seri' => $seri,
            'rozet' => $roz, 'yeni' => is_array($yeni) ? array_values(array_intersect_key(array_map(function ($k) { return OSC_Oyun::$rozetler[$k][0] . ' ' . OSC_Oyun::$rozetler[$k][1]; }, array_combine($yeni, $yeni)), self::$rozetler)) : array(),
            'okul' => $okul, 'sinif' => $sinif, 'seviye' => (int) get_user_meta($uid, 'osc_sinif_k', true), 'gizli' => (int) get_user_meta($uid, 'osc_lig_gizli', true), 'log' => array_map(function ($x) { return array('x' => $x['x'], 'n' => $x['n'], 't' => human_time_diff($x['t']) . ' önce'); }, is_array($lg = get_user_meta($uid, 'osc_xp_log', true)) ? $lg : array()),
            'rol' => OSC_Tk::rol($uid));
    }
    public function ajax_ozet() {
        $uid = get_current_user_id(); if (!$uid) wp_send_json_error('Giriş yapın.');
        $o = self::ozet($uid); $o['lig'] = array();

        wp_send_json_success($o);
    }
    public function ajax_profil() {
        $uid = get_current_user_id(); if (!$uid) wp_send_json_error('Giriş yapın.'); check_ajax_referer('osc_oyun', 'nonce');
        $okul = sanitize_text_field(wp_unslash($_POST['okul'] ?? '')); $okul = mb_substr($okul, 0, 120, 'UTF-8'); $sv = max(0, min(12, (int) ($_POST['seviye'] ?? 0)));
        update_user_meta($uid, 'osc_okul', $okul); update_user_meta($uid, 'osc_okul_k', OSC_Tk::okul_k($okul));
        if ($sv) { update_user_meta($uid, 'osc_sinif_k', $sv); update_user_meta($uid, 'osc_sinif', $sv . '. sınıf'); }
        update_user_meta($uid, 'osc_lig_gizli', !empty($_POST['gizli']) ? 1 : 0);
        $this->ajax_ozet();
    }
    public function ajax_lig() {
        $sv = max(0, min(12, (int) ($_REQUEST['seviye'] ?? 0)));
        $c = 'osc_lig_' . self::hafta() . '_' . $sv; $v = get_transient($c);
        if ($v === false) { $v = self::lig($sv ? 'seviye' : 'genel', 0, 20, null, $sv); set_transient($c, $v, 5 * MINUTE_IN_SECONDS); }
        wp_send_json_success($v);
    }
    /** pazartesi: geçen haftanın her seviyedeki ilk 3'üne rozet */
    public function hafta_kapat() {
        $onceki = self::hafta(time() - 7 * DAY_IN_SECONDS); if (get_option('osc_oyun_kapanan') === $onceki) return;
        update_option('osc_oyun_kapanan', $onceki, false);
        global $wpdb; $um = $wpdb->usermeta; $k = self::hk($onceki);
        foreach (range(1, 12) as $sv) {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT x.user_id FROM $um x JOIN $um s ON s.user_id = x.user_id AND s.meta_key = 'osc_sinif_k' AND s.meta_value = %s JOIN $um r ON r.user_id = x.user_id AND r.meta_key = 'osc_rol' AND r.meta_value = 'ogrenci' WHERE x.meta_key = %s AND (x.meta_value + 0) >= 50 ORDER BY (x.meta_value + 0) DESC LIMIT 3", (string) $sv, $k));
            foreach ($ids as $id) self::rozet_ver((int) $id, 'yildiz');
        }
    }
    public function kisa_kod() {
        $sec = ''; foreach (range(1, 12) as $s) $sec .= '<option value="' . $s . '">' . $s . '. sınıflar</option>';
        return '<div class="osc-lig-k" style="' . OSC_Tk::css() . ';max-width:640px;margin:0 auto"><div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px"><b style="font-size:19px">🏆 Haftanın liderleri</b><select id="osc-lig-sv" style="padding:8px 12px;border-radius:10px;border:1px solid #DCE6F0"><option value="0">Türkiye geneli</option>' . $sec . '</select></div><div id="osc-lig-l">Yükleniyor…</div><p style="color:#8AA2BA;font-size:12.5px;margin-top:8px">Sıralama her pazartesi sıfırlanır. Öğrenci adları gizlilik için kısaltılarak gösterilir.</p></div>
<script>(function(){var s=document.getElementById("osc-lig-sv"),l=document.getElementById("osc-lig-l");function y(){var f=new FormData();f.append("action","osc_oyun_lig");f.append("seviye",s.value);fetch(' . wp_json_encode(admin_url('admin-ajax.php')) . ',{method:"POST",body:f}).then(function(r){return r.json()}).then(function(j){var d=j.data.liste;l.innerHTML=d.length?d.map(function(x){return\'<div style="display:flex;align-items:center;gap:12px;padding:10px 14px;margin-bottom:6px;border-radius:14px;background:\'+(x.s<4?"#FFF8E6":"#F7FAFD")+\'"><b style="width:28px;text-align:center">\'+(["🥇","🥈","🥉"][x.s-1]||x.s)+\'</b><span style="flex:1;font-weight:600">\'+x.ad.replace(/</g,"&lt;")+\' <small style="color:#8AA2BA">Sv \'+x.sv+\'</small></span><b style="color:#2F80ED">\'+x.xp+\' XP</b></div>\'}).join(""):"<p>Bu hafta henüz puan toplayan yok. İlk sen ol!</p>"})}s.onchange=y;y()})();</script>';
    }
    /** öğrenci panelinin içine seviye / rozet / lig kutusu */
    public function panel_betik() {
        if (!is_user_logged_in() || !is_singular()) return; global $post; if (!$post || !has_shortcode($post->post_content, 'osc_ogrenci_paneli')) return; ?>
<style>
.oy-k{margin:16px 0;padding:20px;border-radius:22px;background:#fff;border:1px solid #E3EBF3;box-shadow:0 14px 32px -28px rgba(19,48,79,.5);<?php echo OSC_Tk::css(); ?>}
.oy-ust{display:grid;grid-template-columns:auto 1fr auto;gap:16px;align-items:center}
.oy-sv{width:74px;height:74px;border-radius:50%;display:grid;place-items:center;background:conic-gradient(#2F80ED calc(var(--p)*1%),#E8EEF6 0);position:relative}
.oy-sv:before{content:"";position:absolute;inset:7px;border-radius:50%;background:#fff}
.oy-sv b{position:relative;font-size:22px;line-height:1;text-align:center}.oy-sv b small{display:block;font-size:10px;color:#8AA2BA;font-weight:600}
.oy-ust h3{margin:0 !important;font-size:18px}.oy-ust p{margin:3px 0 0 !important;color:#5B7189;font-size:13.5px}
.oy-cub{height:8px;border-radius:99px;background:#E8EEF6;margin-top:8px;overflow:hidden}.oy-cub i{display:block;height:100%;background:linear-gradient(90deg,#2F80ED,#22A06B);border-radius:99px}
.oy-mini{display:flex;gap:8px}.oy-mini div{padding:8px 12px;border-radius:14px;background:#F7FAFD;text-align:center;min-width:76px}.oy-mini b{display:block;font-size:18px}.oy-mini small{font-size:11.5px;color:#5B7189}
.oy-roz{display:grid;grid-template-columns:repeat(auto-fill,minmax(92px,1fr));gap:8px;margin-top:16px}
.oy-roz div{text-align:center;padding:10px 6px;border-radius:14px;background:#F7FAFD;border:1px solid #EEF3F8;font-size:11.5px;color:#5B7189;line-height:1.3}
.oy-roz div span{display:block;font-size:26px;margin-bottom:4px}.oy-roz div b{display:block;font-size:12px;color:#13304F}
.oy-roz div.yok{opacity:.45;filter:grayscale(1)}.oy-roz div.var{background:#FFF8E6;border-color:#FDE4A7}
.oy-lig{margin-top:18px}.oy-sek{display:flex;gap:6px;flex-wrap:wrap;margin:8px 0}.oy-sek button{border:1px solid #DCE6F0;background:#fff;border-radius:99px;padding:7px 13px;font:600 13px inherit;font-family:inherit;cursor:pointer;color:#46607A}.oy-sek button.on{background:#13304F;color:#fff;border-color:#13304F}
.oy-sat{display:flex;align-items:center;gap:12px;padding:9px 12px;border-radius:12px;background:#F7FAFD;margin-bottom:5px;font-size:14px}.oy-sat.ben{background:#EAF2FD;outline:2px solid #2F80ED}.oy-sat b.s{width:28px;text-align:center}.oy-sat span{flex:1;font-weight:600}.oy-sat em{font-style:normal;font-weight:800;color:#2F80ED}
.oy-form{display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:12px;border-radius:14px;background:#FFF8E6;margin:8px 0;font-size:13.5px}.oy-form input[type=text],.oy-form select{padding:8px 10px;border:1px solid #E5D3A4;border-radius:10px;font:inherit}.oy-form input[type=text]{flex:1;min-width:180px}.oy-form button{border:0;background:#13304F;color:#fff;border-radius:10px;padding:9px 14px;font-weight:700;cursor:pointer}
.oy-toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:9999;background:#13304F;color:#fff;padding:14px 20px;border-radius:16px;font-weight:700;box-shadow:0 18px 40px -16px rgba(0,0,0,.5)}
@media(max-width:640px){.oy-ust{grid-template-columns:auto 1fr}.oy-mini{grid-column:1/-1}}
</style>
<script>
(function () {
  var A = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, N = <?php echo wp_json_encode(wp_create_nonce('osc_oyun')); ?>, kok = document.getElementById('osc-op'), kap = null, D = null, sek = 'sinif';
  if (!kok) return;
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function yerlestir() { if (kap && kok.contains(kap)) return true; var k = kok.querySelector('.osc-op-kartlar'); if (!k) return false; kap = kap || document.createElement('div'); kap.className = 'oy-k'; k.insertAdjacentElement('afterend', kap); if (D) ciz(); return true; }
  new MutationObserver(yerlestir).observe(kok, { childList: true, subtree: false });
  function al(fd) { fd = fd || new FormData(); if (!fd.get('action')) fd.append('action', 'osc_oyun_ozet'); return fetch(A, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (!j.success) throw 0; D = j.data; yerlestir(); ciz(); if (D.yeni && D.yeni.length) { var t = document.createElement('div'); t.className = 'oy-toast'; t.textContent = '🎉 Yeni rozet: ' + D.yeni.join(', '); document.body.appendChild(t); setTimeout(function () { t.remove(); }, 5000); } }).catch(function () {}); }
  var AD = { sinif: 'Sınıfım', okul: 'Okulum', seviye: 'Seviyem (Türkiye)', genel: 'Türkiye geneli' };
  function ciz() {
    if (!kap) return;
    var p = D.sv_son > D.sv_bas ? Math.round((D.xp - D.sv_bas) / (D.sv_son - D.sv_bas) * 100) : 0;
    var form = (!D.okul || !D.seviye) ? '<div class="oy-form"><b>Sınıf ve okul ligine katıl:</b><input type="text" id="oy-okul" placeholder="Okulunun adı (ör. Atatürk Ortaokulu, Kadıköy)" value="' + esc(D.okul) + '"><select id="oy-sv"><option value="">Sınıf</option>' + [1,2,3,4,5,6,7,8,9,10,11,12].map(function (s) { return '<option value="' + s + '"' + (D.seviye === s ? ' selected' : '') + '>' + s + '. sınıf</option>'; }).join('') + '</select><button type="button" id="oy-kay">Kaydet</button></div>' : '';
    kap.innerHTML = '<div class="oy-ust"><div class="oy-sv" style="--p:' + p + '"><b>' + D.sv + '<small>SEVİYE</small></b></div><div><h3>' + D.xp + ' XP</h3><p>Bir sonraki seviyeye <b>' + Math.max(0, D.sv_son - D.xp) + ' XP</b> kaldı</p><div class="oy-cub"><i style="width:' + p + '%"></i></div></div><div class="oy-mini"><div><b>' + D.hafta + '</b><small>Bu hafta XP</small></div><div><b>' + (D.seri ? '🔥 ' + D.seri : '0') + '</b><small>Günlük seri</small></div></div></div>' +
      '<div class="oy-roz">' + D.rozet.map(function (r) { return '<div class="' + (r.var ? 'var' : 'yok') + '" title="' + esc(r.ac + (r.var ? ' · ' + r.var : '')) + '"><span>' + r.ik + '</span><b>' + esc(r.ad) + '</b>' + esc(r.var ? r.var : r.ac) + '</div>'; }).join('') + '</div>' +
      '<p style="color:#8AA2BA;font-size:12px;margin:12px 0 0 !important">Her test 10 XP + puanının onda biri, ödev ve sınav teslimi 20 XP, rozetler 25 XP kazandırır. XP ve rozetlerini yalnızca sen görürsün.</p>';

  }
  function kaydet() { var f = new FormData(); f.append('action', 'osc_oyun_profil'); f.append('nonce', N); var o = kap.querySelector('#oy-okul'), s = kap.querySelector('#oy-sv'); f.append('okul', o ? o.value : D.okul); f.append('seviye', s ? s.value : D.seviye); if (kap.querySelector('#oy-gizli').checked) f.append('gizli', 1); al(f); }
  al();
})();
</script>
<?php }
}

/* ---------------------------------------------------------------------
 * SORU-CEVAP
 * ------------------------------------------------------------------- */
class OSC_Forum {
    const TUR = 'osc_soru';
    public static $dersler = array('Matematik', 'Türkçe', 'Fen Bilimleri', 'Sosyal Bilgiler', 'T.C. İnkılap Tarihi', 'İngilizce', 'Din Kültürü', 'Hayat Bilgisi', 'Türk Dili ve Edebiyatı', 'Fizik', 'Kimya', 'Biyoloji', 'Tarih', 'Coğrafya', 'Felsefe', 'Diğer', 'Öğretmenler odası');
    public function __construct() {
        add_action('init', array($this, 'kayit'));
        add_action('template_redirect', array($this, 'yonlendir'), 5);
        foreach (array('sor', 'cevap', 'oy', 'kabul', 'sikayet') as $a) add_action('wp_ajax_osc_sc_' . $a, array($this, 'ajax_' . $a));
        add_action('wp_ajax_nopriv_osc_sc_sor', array($this, 'giris_gerek')); add_action('wp_ajax_nopriv_osc_sc_cevap', array($this, 'giris_gerek'));
        add_action('transition_post_status', function ($y, $e, $p) { if ($p->post_type === self::TUR && $y === 'publish' && $e !== 'publish') { delete_transient('osc_sc_sayi'); } }, 10, 3);
        add_action('admin_init', array($this, 'ayar_kayit'));
        add_action('admin_menu', array($this, 'onay_menu'));
        add_action('admin_post_osc_sc_onay', array($this, 'onay_islem'));
        add_filter('post_row_actions', function ($a, $p) { if ($p->post_type === self::TUR && $p->post_status === 'pending' && current_user_can('edit_others_posts')) $a = array('osc_onay' => '<a style="color:#008a20;font-weight:600" href="' . esc_url(self::onay_url('p', $p->ID, 'onay')) . '">✓ Onayla</a>') + $a; return $a; }, 10, 2);
    }
    public function kayit() {
        register_post_type(self::TUR, array('label' => 'Soru-Cevap', 'labels' => array('name' => 'Soru-Cevap', 'singular_name' => 'Soru', 'menu_name' => 'Soru-Cevap', 'all_items' => 'Tüm sorular'), 'public' => false, 'show_ui' => true, 'menu_icon' => 'dashicons-format-chat', 'menu_position' => 27, 'supports' => array('title', 'editor', 'author', 'comments'), 'capability_type' => 'post'));
    }
    public static function url($id = 0) { if (!$id) return home_url('/soru-cevap/'); $p = get_post($id); return home_url('/soru-cevap/' . (int) $id . '-' . ($p ? sanitize_title(remove_accents(mb_substr($p->post_title, 0, 70, 'UTF-8'))) : '') . '/'); }
    public static function onay_url($tip, $id, $is) { return wp_nonce_url(admin_url('admin-post.php?action=osc_sc_onay&t=' . $tip . '&id=' . (int) $id . '&is=' . $is), 'osc_sc_onay'); }
    public static function bekleyen() { return (int) wp_count_posts(self::TUR)->pending + (int) get_comments(array('type' => 'osc_cevap', 'status' => 'hold', 'count' => true)); }
    public function onay_menu() {
        $n = self::bekleyen();
        add_submenu_page('edit.php?post_type=' . self::TUR, 'Onay bekleyenler', 'Onay bekleyenler' . ($n ? ' <span class="awaiting-mod">' . $n . '</span>' : ''), 'edit_others_posts', 'osc-sc-onay', array($this, 'onay_sayfa'));
        if ($n) { global $menu; foreach ((array) $menu as $i => $m) if (isset($m[2]) && $m[2] === 'edit.php?post_type=' . self::TUR) $menu[$i][0] .= ' <span class="awaiting-mod">' . $n . '</span>'; }
    }
    public function onay_islem() {
        if (!current_user_can('edit_others_posts')) wp_die('Yetkiniz yok.'); check_admin_referer('osc_sc_onay');
        $t = $_GET['t'] ?? ''; $id = (int) ($_GET['id'] ?? 0); $is = $_GET['is'] ?? '';
        if ($t === 'p' && get_post_type($id) === self::TUR) { if ($is === 'onay') wp_update_post(array('ID' => $id, 'post_status' => 'publish')); elseif ($is === 'sil') wp_trash_post($id); }
        if ($t === 'c' && ($c = get_comment($id)) && $c->comment_type === 'osc_cevap') { if ($is === 'onay') { wp_set_comment_status($id, 'approve'); $this->cevap_sayac((int) $c->comment_post_ID); do_action('osc_sc_cevap', (int) $c->comment_post_ID, $id); } elseif ($is === 'sil') wp_trash_comment($id); }
        if ($t === 'hepsi' && $is === 'onay') { foreach (get_posts(array('post_type' => self::TUR, 'post_status' => 'pending', 'numberposts' => 200, 'fields' => 'ids')) as $p) wp_update_post(array('ID' => $p, 'post_status' => 'publish')); foreach (get_comments(array('type' => 'osc_cevap', 'status' => 'hold', 'number' => 200)) as $c) { wp_set_comment_status($c->comment_ID, 'approve'); $this->cevap_sayac((int) $c->comment_post_ID); } }
        wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url('edit.php?post_type=' . self::TUR . '&page=osc-sc-onay')); exit;
    }
    public function onay_sayfa() {
        $ps = get_posts(array('post_type' => self::TUR, 'post_status' => 'pending', 'numberposts' => 100));
        $cs = get_comments(array('type' => 'osc_cevap', 'status' => 'hold', 'number' => 100)); ?>
<div class="wrap"><h1 class="wp-heading-inline">Soru-Cevap: onay bekleyenler</h1>
<?php if ($ps || $cs): ?> <a class="page-title-action" href="<?php echo esc_url(self::onay_url('hepsi', 0, 'onay')); ?>" onclick="return confirm('Bekleyenlerin hepsi onaylansın mı?')">Hepsini onayla</a><?php endif; ?>
<style>.osc-on{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:14px 16px;margin:10px 0;display:flex;gap:14px;align-items:flex-start}.osc-on .m{flex:1}.osc-on h3{margin:0 0 4px}.osc-on p{margin:6px 0 0;color:#3c434a;white-space:pre-wrap}.osc-on small{color:#646970}.osc-on .b{display:flex;gap:6px;flex-shrink:0}.osc-on .b a.on{background:#008a20;border-color:#008a20;color:#fff}</style>
<h2>Sorular (<?php echo count($ps); ?>)</h2>
<?php if (!$ps) echo '<p>Onay bekleyen soru yok.</p>'; foreach ($ps as $p) { $u = get_userdata($p->post_author); ?>
<div class="osc-on"><div class="m"><h3><?php echo esc_html($p->post_title); ?></h3><small><?php echo esc_html($u ? $u->display_name . ' · ' . $u->user_email : ''); ?> · <?php echo esc_html(human_time_diff(get_post_time('U', true, $p)) . ' önce'); ?></small><p><?php echo esc_html(wp_strip_all_tags($p->post_content)); ?></p></div>
<div class="b"><a class="button on" href="<?php echo esc_url(self::onay_url('p', $p->ID, 'onay')); ?>">✓ Onayla</a><a class="button" href="<?php echo esc_url(get_edit_post_link($p->ID)); ?>">Düzenle</a><a class="button" style="color:#b32d2e" href="<?php echo esc_url(self::onay_url('p', $p->ID, 'sil')); ?>">Sil</a></div></div>
<?php } ?>
<h2 style="margin-top:24px">Cevaplar (<?php echo count($cs); ?>)</h2>
<?php if (!$cs) echo '<p>Onay bekleyen cevap yok.</p>'; foreach ($cs as $c) { ?>
<div class="osc-on"><div class="m"><h3 style="font-size:14px">Soru: <?php echo esc_html(get_the_title($c->comment_post_ID)); ?></h3><small><?php echo esc_html($c->comment_author . ' · ' . $c->comment_author_email . ' · ' . human_time_diff(strtotime($c->comment_date_gmt . ' UTC')) . ' önce'); ?></small><p><?php echo esc_html($c->comment_content); ?></p></div>
<div class="b"><a class="button on" href="<?php echo esc_url(self::onay_url('c', $c->comment_ID, 'onay')); ?>">✓ Onayla</a><a class="button" style="color:#b32d2e" href="<?php echo esc_url(self::onay_url('c', $c->comment_ID, 'sil')); ?>">Sil</a></div></div>
<?php } ?></div>
<?php }
    public function giris_gerek() { wp_send_json_error('Soru sormak ve cevap yazmak için giriş yapın.'); }
    public function ayar_kayit() { register_setting('osc_tk', 'osc_sc_yasak', array('sanitize_callback' => 'sanitize_textarea_field')); }
    /* ---- spam / güvenlik ---- */
    private function denetle($metin, $tur) {
        if (!is_user_logged_in()) wp_send_json_error('Giriş yapın.');
        check_ajax_referer('osc_sc', 'nonce');
        $uid = get_current_user_id();
        if (get_user_meta($uid, 'osc_dogrulama', true)) wp_send_json_error('Önce e-posta adresinizi doğrulayın.');
        if (!empty($_POST['web'])) wp_send_json_error('Gönderilemedi.');   // bal küpü
        $z = (int) ($_POST['z'] ?? 0); $zs = (string) ($_POST['zs'] ?? '');
        if (!$z || !hash_equals(wp_hash('osc_sc' . $z), $zs) || time() - $z < 5) wp_send_json_error('Biraz yavaş: lütfen yazdıklarınızı kontrol edip tekrar gönderin.');
        if (get_user_meta($uid, 'osc_sc_yasakli', true)) wp_send_json_error('Hesabınız Soru-Cevap bölümüne yazamıyor.');
        $lim = $tur === 'soru' ? 4 : 12; $k = 'osc_sc_h_' . $tur . '_' . $uid; $n = (int) get_transient($k);
        $ki = 'osc_sc_ip_' . md5(OSC_Tk::ip()); $ni = (int) get_transient($ki);
        if (!current_user_can('edit_others_posts') && ($n >= $lim || $ni >= 30)) wp_send_json_error('Kısa sürede çok fazla gönderi yaptınız. Biraz sonra tekrar deneyin.');
        $duz = trim(preg_replace('/\s+/u', ' ', $metin));
        if (mb_strlen($duz, 'UTF-8') < ($tur === 'soru' ? 20 : 8)) wp_send_json_error($tur === 'soru' ? 'Sorunuzu biraz daha ayrıntılı yazın (en az 20 karakter).' : 'Cevabınız çok kısa.');
        if (mb_strlen($duz, 'UTF-8') > 5000) wp_send_json_error('Metin çok uzun (en fazla 5000 karakter).');
        if (preg_match('/(.)\1{9,}/u', $duz)) wp_send_json_error('Lütfen anlamlı bir metin yazın.');
        $h = md5(mb_strtolower($duz, 'UTF-8')); $son = get_user_meta($uid, 'osc_sc_son', true);
        if ($son === $h) wp_send_json_error('Bu metni zaten gönderdiniz.');
        $link = preg_match_all('#(https?://|www\.)\S+#i', $duz);
        $yasak = array_filter(array_map('trim', explode("\n", mb_strtolower((string) get_option('osc_sc_yasak', ''), 'UTF-8'))));
        $kotu = false; foreach ($yasak as $y) if ($y !== '' && mb_strpos(mb_strtolower($duz, 'UTF-8'), $y) !== false) $kotu = true;
        $u = get_userdata($uid); $yeni = (time() - strtotime($u->user_registered)) < 2 * DAY_IN_SECONDS && (int) get_user_meta($uid, 'osc_xp', true) < 30;
        if ($link > 2) wp_send_json_error('En fazla 2 bağlantı ekleyebilirsiniz.');
        set_transient($k, $n + 1, HOUR_IN_SECONDS); set_transient($ki, $ni + 1, HOUR_IN_SECONDS); update_user_meta($uid, 'osc_sc_son', $h);
        return array('uid' => $uid, 'onay' => current_user_can('edit_others_posts') || (!$kotu && !$link && !$yeni) || ($link && !$kotu && (int) get_user_meta($uid, 'osc_xp', true) >= 200));
    }
    private static function temiz($s) { return trim(sanitize_textarea_field(wp_unslash($s))); }
    public function ajax_sor() {
        $b = self::temiz($_POST['baslik'] ?? ''); $m = self::temiz($_POST['metin'] ?? '');
        if (mb_strlen($b, 'UTF-8') < 12 || mb_strlen($b, 'UTF-8') > 150) wp_send_json_error('Başlık 12–150 karakter olmalı. Sorunuzu kısaca özetleyin.');
        $d = $this->denetle($b . "\n" . $m, 'soru');
        $ders = in_array($_POST['ders'] ?? '', self::$dersler, true) ? $_POST['ders'] : 'Diğer'; $sv = max(0, min(12, (int) ($_POST['sinif'] ?? 0)));
        $id = wp_insert_post(array('post_type' => self::TUR, 'post_title' => $b, 'post_content' => $m, 'post_status' => $d['onay'] ? 'publish' : 'pending', 'post_author' => $d['uid'], 'comment_status' => 'open'), true);
        if (is_wp_error($id)) wp_send_json_error('Kaydedilemedi.');
        update_post_meta($id, '_sc_ders', $ders); update_post_meta($id, '_sc_sinif', $sv); update_post_meta($id, '_sc_oy', 0);
        if ($d['onay']) { OSC_Oyun::ekle($d['uid'], 3, 'Soru sordun'); do_action('osc_sc_soru', $id); }
        wp_send_json_success(array('url' => self::url($id), 'onay' => $d['onay'] ? 1 : 0));
    }
    public function ajax_cevap() {
        $qid = (int) ($_POST['soru'] ?? 0); $q = get_post($qid);
        if (!$q || $q->post_type !== self::TUR || $q->post_status !== 'publish') wp_send_json_error('Soru bulunamadı.');
        if (get_post_meta($qid, '_sc_kilit', true)) wp_send_json_error('Bu soru cevaplara kapatıldı.');
        $m = self::temiz($_POST['metin'] ?? ''); $d = $this->denetle($m, 'cevap'); $u = get_userdata($d['uid']);
        $cid = wp_insert_comment(array('comment_post_ID' => $qid, 'comment_content' => $m, 'comment_type' => 'osc_cevap', 'user_id' => $d['uid'], 'comment_author' => $u->display_name, 'comment_author_email' => $u->user_email, 'comment_approved' => $d['onay'] ? 1 : 0, 'comment_author_IP' => OSC_Tk::ip()));
        if (!$cid) wp_send_json_error('Kaydedilemedi.');
        if ($d['onay']) { $this->cevap_sayac($qid); if ((int) $q->post_author !== $d['uid']) { OSC_Oyun::ekle($d['uid'], 10, 'Soruya cevap yazdın'); update_user_meta($d['uid'], 'osc_n_cevap', (int) get_user_meta($d['uid'], 'osc_n_cevap', true) + 1); OSC_Oyun::rozet_kontrol($d['uid']); } do_action('osc_sc_cevap', $cid, $qid); }
        wp_send_json_success(array('onay' => $d['onay'] ? 1 : 0));
    }
    private function cevap_sayac($qid) { update_post_meta($qid, '_sc_cevap', (int) get_comments(array('post_id' => $qid, 'type' => 'osc_cevap', 'status' => 'approve', 'count' => true))); }
    public function ajax_oy() {
        if (!is_user_logged_in()) wp_send_json_error('Oy vermek için giriş yapın.'); check_ajax_referer('osc_sc', 'nonce');
        $uid = get_current_user_id(); $tip = ($_POST['tip'] ?? '') === 'c' ? 'c' : 's'; $id = (int) ($_POST['id'] ?? 0);
        $sahip = $tip === 'c' ? (($c = get_comment($id)) && $c->comment_type === 'osc_cevap' ? (int) $c->user_id : -1) : (($p = get_post($id)) && $p->post_type === self::TUR ? (int) $p->post_author : -1);
        if ($sahip < 0) wp_send_json_error('Bulunamadı.'); if ($sahip === $uid) wp_send_json_error('Kendi gönderinize oy veremezsiniz.');
        $get = $tip === 'c' ? 'get_comment_meta' : 'get_post_meta'; $upd = $tip === 'c' ? 'update_comment_meta' : 'update_post_meta';
        $l = $get($id, '_sc_oyl', true); $l = is_array($l) ? $l : array();
        if (in_array($uid, $l, true)) { $l = array_values(array_diff($l, array($uid))); $art = -1; } else { $l[] = $uid; $art = 1; }
        $upd($id, '_sc_oyl', $l); $upd($id, '_sc_oy', count($l));
        if ($art > 0 && $sahip) OSC_Oyun::ekle($sahip, 2, 'Gönderin faydalı bulundu');
        wp_send_json_success(array('oy' => count($l), 'ben' => $art > 0 ? 1 : 0));
    }
    public function ajax_kabul() {
        if (!is_user_logged_in()) wp_send_json_error('Giriş yapın.'); check_ajax_referer('osc_sc', 'nonce');
        $c = get_comment((int) ($_POST['id'] ?? 0)); if (!$c || $c->comment_type !== 'osc_cevap') wp_send_json_error('Bulunamadı.');
        $q = get_post($c->comment_post_ID); $uid = get_current_user_id();
        if ((int) $q->post_author !== $uid && !current_user_can('edit_others_posts')) wp_send_json_error('Yalnızca soruyu soran seçebilir.');
        $eski = (int) get_post_meta($q->ID, '_sc_kabul', true); if ($eski === (int) $c->comment_ID) wp_send_json_success();
        update_post_meta($q->ID, '_sc_kabul', (int) $c->comment_ID);
        if (!$eski && (int) $c->user_id && (int) $c->user_id !== (int) $q->post_author) { OSC_Oyun::ekle((int) $c->user_id, 30, 'Cevabın çözüm seçildi'); update_user_meta($c->user_id, 'osc_n_kabul', (int) get_user_meta($c->user_id, 'osc_n_kabul', true) + 1); OSC_Oyun::rozet_kontrol((int) $c->user_id); }
        wp_send_json_success();
    }
    public function ajax_sikayet() {
        if (!is_user_logged_in()) wp_send_json_error('Giriş yapın.'); check_ajax_referer('osc_sc', 'nonce');
        $uid = get_current_user_id(); $tip = ($_POST['tip'] ?? '') === 'c' ? 'c' : 's'; $id = (int) ($_POST['id'] ?? 0);
        $get = $tip === 'c' ? 'get_comment_meta' : 'get_post_meta'; $upd = $tip === 'c' ? 'update_comment_meta' : 'update_post_meta';
        $l = $get($id, '_sc_sik', true); $l = is_array($l) ? $l : array(); if (!in_array($uid, $l, true)) $l[] = $uid; $upd($id, '_sc_sik', $l);
        if (count($l) >= 3 || current_user_can('edit_others_posts')) { if ($tip === 'c') wp_set_comment_status($id, 'hold'); else wp_update_post(array('ID' => $id, 'post_status' => 'pending')); }
        wp_send_json_success('Bildiriminiz alındı. Teşekkürler.');
    }
    /* ---- sayfalar ---- */
    public function yonlendir() {
        $y = OSC_Tk::yol(); if (strpos($y, 'soru-cevap') !== 0 || !is_404()) return;
        if ($y === 'soru-cevap') $this->liste();
        if ($y === 'soru-cevap/sor') $this->sor_sayfa();
        if (preg_match('#^soru-cevap/(\d+)(-[^/]*)?$#', $y, $m)) $this->detay((int) $m[1]);
    }
    private static function stil() { return '<style>
.sc{' . OSC_Tk::css() . ';max-width:1080px;margin:0 auto;padding:22px 16px 60px}.sc *{box-sizing:border-box}
.sc a{text-decoration:none}.sc-hero{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;padding:28px 26px;border-radius:26px;background:linear-gradient(180deg,#EAF4FC,#F5F9FE);border:1px solid #DCEAF7}
.sc-hero h1{margin:0 !important;font-size:clamp(26px,3.4vw,36px);font-weight:800;letter-spacing:-.02em;color:#13304F}.sc-hero p{margin:6px 0 0 !important;color:#46607A}
.sc .sc-btn{display:inline-flex;align-items:center;gap:8px;border:0;background:#2F80ED;color:#fff;font-weight:700;font-size:15px;padding:12px 20px;border-radius:14px;cursor:pointer;font-family:inherit}.sc .sc-btn:hover{background:#1F6FD8;color:#fff}.sc .sc-btn.hafif{background:#fff;color:#13304F;border:1px solid #DCE6F0}
.sc-filtre{display:flex;flex-wrap:wrap;gap:8px;margin:18px 0 12px}.sc-filtre select,.sc-filtre input{padding:10px 12px;border:1px solid #DCE6F0;border-radius:12px;font:inherit;background:#fff}.sc-filtre input{flex:1;min-width:200px}
.sc-sek{display:flex;gap:6px;margin-bottom:12px}.sc-sek a{padding:7px 14px;border-radius:99px;border:1px solid #DCE6F0;color:#46607A;font-weight:600;font-size:13.5px;background:#fff}.sc-sek a.on{background:#13304F;color:#fff;border-color:#13304F}
.sc-l{display:grid;gap:10px}.sc .sc-s{display:flex;gap:14px;padding:16px 18px;border-radius:18px;background:#fff;border:1px solid var(--c);color:#13304F;transition:border-color .15s,box-shadow .15s}.sc .sc-s:hover{border-color:#C9DCF0;box-shadow:0 16px 30px -26px rgba(19,48,79,.6)}
.sc-say{flex:0 0 64px;text-align:center;padding:8px 4px;border-radius:14px;background:#F7FAFD;font-size:11.5px;color:#5B7189}.sc-say b{display:block;font-size:19px;color:#13304F}.sc-say.cozuldu{background:#E9F7EF;color:#15803D}.sc-say.cozuldu b{color:#15803D}
.sc-s h3{margin:0 !important;font-size:16.5px;line-height:1.35}.sc-s p{margin:5px 0 0 !important;color:#5B7189;font-size:14px;line-height:1.5}
.sc-et{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;font-size:12px;color:#8AA2BA;align-items:center}.sc-et em{font-style:normal;font-weight:700;padding:2px 9px;border-radius:99px;background:#EAF2FD;color:#2F80ED}.sc-et em.s{background:#F3ECFB;color:#7C3AED}.sc-et em.og{background:#E9F7EF;color:#15803D}
.sc-kart{padding:22px;border-radius:22px;background:#fff;border:1px solid var(--c);margin-top:16px}.sc-kart h1{margin:0 0 6px !important;font-size:clamp(22px,2.8vw,28px);line-height:1.25}
.sc-metin{font-size:16px;line-height:1.7;color:#1C3653;white-space:pre-wrap;overflow-wrap:anywhere;margin-top:12px}
.sc-eylem{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.sc-eylem button{border:1px solid #DCE6F0;background:#fff;border-radius:10px;padding:7px 12px;font:600 13px inherit;font-family:inherit;color:#46607A;cursor:pointer}.sc-eylem button.on{background:#EAF2FD;border-color:#2F80ED;color:#2F80ED}
.sc-c{padding:18px 20px;border-radius:18px;background:#fff;border:1px solid var(--c);margin-top:10px}.sc-c.kabul{border:2px solid #22A06B;background:#F6FCF8}.sc-c .rozet{display:inline-block;background:#22A06B;color:#fff;border-radius:99px;padding:3px 10px;font-size:12px;font-weight:700;margin-bottom:6px}
.sc-form{display:grid;gap:10px}.sc-form label{font-weight:600;font-size:14px;display:grid;gap:6px}.sc-form input,.sc-form select,.sc-form textarea{padding:12px 14px;border:1px solid #DCE6F0;border-radius:12px;font:inherit;font-size:15px;width:100%;background:#fff}.sc-form textarea{min-height:150px;resize:vertical}
.sc-iki{display:grid;grid-template-columns:1fr 1fr;gap:10px}.sc-not{font-size:13px;color:#5B7189;background:#F7FAFD;border-radius:12px;padding:10px 12px}.sc-msj{padding:12px 14px;border-radius:12px;font-weight:600}.sc-msj.ok{background:#E9F7EF;color:#15803D}.sc-msj.hata{background:#FEE2E2;color:#B91C1C}
.sc-sayfa{display:flex;gap:6px;justify-content:center;margin-top:18px}.sc-sayfa a{padding:8px 13px;border-radius:10px;border:1px solid #DCE6F0;color:#13304F;background:#fff}.sc-sayfa a.on{background:#13304F;color:#fff}
.sc-bos{text-align:center;padding:40px 20px;color:#5B7189;background:#fff;border:1px dashed #C9DCF0;border-radius:18px}
@media(max-width:640px){.sc-iki{grid-template-columns:1fr}.sc-say{flex-basis:52px}}
</style>'; }
    private static function form_ek() { $z = time(); return '<input type="text" name="web" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true"><input type="hidden" name="z" value="' . $z . '"><input type="hidden" name="zs" value="' . esc_attr(wp_hash('osc_sc' . $z)) . '"><input type="hidden" name="nonce" value="' . esc_attr(wp_create_nonce('osc_sc')) . '">'; }
    private static function giris_url() { return class_exists('OSC_Sinav') ? OSC_Sinav::sayfa_url('osc_sp_uye_sayfa', array('git' => home_url(add_query_arg(array())))) : wp_login_url(); }
    private static function yazar($uid) {
        $r = OSC_Tk::rol($uid); $et = $r === 'ogretmen' ? '<em class="og">Öğretmen ✓</em>' : ($r === 'yonetici' ? '<em class="og">Editör ✓</em>' : '');
        return '<b style="color:#46607A">' . esc_html(OSC_Tk::kisa_ad($uid)) . '</b>' . $et . '<span>Sv ' . OSC_Oyun::seviye((int) get_user_meta($uid, 'osc_xp', true)) . '</span>';
    }
    private static function betik() { return '<script>(function(){var A=' . wp_json_encode(admin_url('admin-ajax.php')) . ';document.addEventListener("submit",function(e){var f=e.target;if(!f.matches("form[data-sc]"))return;e.preventDefault();var b=f.querySelector("button[type=submit]"),m=f.querySelector(".sc-msj");b.disabled=true;var d=new FormData(f);d.append("action","osc_sc_"+f.dataset.sc);fetch(A,{method:"POST",body:d,credentials:"same-origin"}).then(function(r){return r.json()}).then(function(j){b.disabled=false;if(!j.success){m.className="sc-msj hata";m.textContent=j.data||"Gönderilemedi.";return}if(f.dataset.sc==="sor"){if(j.data.onay)location.href=j.data.url;else{f.innerHTML=\'<div class="sc-msj ok">Sorunuz alındı. Kısa bir kontrolden sonra yayımlanacak.</div>\'}}else{if(j.data.onay)location.reload();else{f.innerHTML=\'<div class="sc-msj ok">Cevabınız alındı. Kısa bir kontrolden sonra yayımlanacak.</div>\'}}}).catch(function(){b.disabled=false;m.className="sc-msj hata";m.textContent="Bağlantı hatası."})});document.addEventListener("click",function(e){var b=e.target.closest("[data-sce]");if(!b)return;var d=new FormData();d.append("action","osc_sc_"+b.dataset.sce);d.append("id",b.dataset.id);d.append("tip",b.dataset.tip||"s");d.append("nonce",' . wp_json_encode(wp_create_nonce('osc_sc')) . ');if(b.dataset.sce==="sikayet"&&!confirm("Bu gönderiyi uygunsuz olarak bildirmek istiyor musunuz?"))return;fetch(A,{method:"POST",body:d,credentials:"same-origin"}).then(function(r){return r.json()}).then(function(j){if(!j.success){alert(j.data);return}if(b.dataset.sce==="oy"){b.classList.toggle("on",!!j.data.ben);b.querySelector("b").textContent=j.data.oy}else if(b.dataset.sce==="kabul")location.reload();else{b.textContent="Bildirildi";b.disabled=true}})})})();</script>'; }
    public function liste() {
        $ders = isset($_GET['ders']) && in_array($_GET['ders'], self::$dersler, true) ? $_GET['ders'] : ''; $sv = max(0, min(12, (int) ($_GET['sinif'] ?? 0))); $q = sanitize_text_field(wp_unslash($_GET['q'] ?? '')); $d = in_array($_GET['d'] ?? '', array('cevapsiz', 'cozuldu'), true) ? $_GET['d'] : ''; $sf = max(1, (int) ($_GET['s'] ?? 1));
        $mq = array(); if ($ders) $mq[] = array('key' => '_sc_ders', 'value' => $ders); if ($sv) $mq[] = array('key' => '_sc_sinif', 'value' => $sv);
        if ($d === 'cevapsiz') $mq[] = array('relation' => 'OR', array('key' => '_sc_cevap', 'compare' => 'NOT EXISTS'), array('key' => '_sc_cevap', 'value' => 0)); if ($d === 'cozuldu') $mq[] = array('key' => '_sc_kabul', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC');
        $sq = new WP_Query(array('post_type' => self::TUR, 'post_status' => 'publish', 'posts_per_page' => 20, 'paged' => $sf, 's' => $q, 'meta_query' => $mq, 'orderby' => 'date', 'order' => 'DESC'));
        $u = function ($e) use ($ders, $sv, $q, $d) { return esc_url(add_query_arg(array_filter(array_merge(array('ders' => $ders, 'sinif' => $sv ?: '', 'q' => $q, 'd' => $d), $e)), self::url())); };
        $h = self::stil() . '<div class="sc"><section class="sc-hero"><div><h1>Soru-Cevap</h1><p>Takıldığın soruyu sor; öğretmenler ve arkadaşların yardımcı olsun. Yardım ettikçe XP ve rozet kazan.</p></div><a class="sc-btn" href="' . esc_url(home_url('/soru-cevap/sor/')) . '">✍️ Soru sor</a></section>';
        $h .= '<form class="sc-filtre" method="get" action="' . esc_url(self::url()) . '"><input type="search" name="q" value="' . esc_attr($q) . '" placeholder="Sorularda ara…"><select name="ders" onchange="this.form.submit()"><option value="">Tüm dersler</option>' . implode('', array_map(function ($x) use ($ders) { return '<option' . selected($ders, $x, false) . '>' . esc_html($x) . '</option>'; }, self::$dersler)) . '</select><select name="sinif" onchange="this.form.submit()"><option value="">Tüm sınıflar</option>' . implode('', array_map(function ($x) use ($sv) { return '<option value="' . $x . '"' . selected($sv, $x, false) . '>' . $x . '. sınıf</option>'; }, range(1, 12))) . '</select>' . ($d ? '<input type="hidden" name="d" value="' . esc_attr($d) . '">' : '') . '<button class="sc-btn hafif" type="submit">Ara</button></form>';
        $h .= '<nav class="sc-sek"><a class="' . (!$d ? 'on' : '') . '" href="' . $u(array('d' => '', 's' => '')) . '">Tümü</a><a class="' . ($d === 'cevapsiz' ? 'on' : '') . '" href="' . $u(array('d' => 'cevapsiz', 's' => '')) . '">Cevap bekleyenler</a><a class="' . ($d === 'cozuldu' ? 'on' : '') . '" href="' . $u(array('d' => 'cozuldu', 's' => '')) . '">Çözülenler</a></nav><div class="sc-l">';
        foreach ($sq->posts as $p) {
            $cv = (int) get_post_meta($p->ID, '_sc_cevap', true); $kb = (int) get_post_meta($p->ID, '_sc_kabul', true); $sn = (int) get_post_meta($p->ID, '_sc_sinif', true);
            $h .= '<a class="sc-s" href="' . esc_url(self::url($p->ID)) . '"><span class="sc-say' . ($kb ? ' cozuldu' : '') . '"><b>' . $cv . '</b>' . ($kb ? '✓ çözüldü' : 'cevap') . '</span><span style="flex:1;min-width:0"><h3>' . esc_html($p->post_title) . '</h3><p>' . esc_html(wp_trim_words($p->post_content, 26, '…')) . '</p><span class="sc-et"><em>' . esc_html(get_post_meta($p->ID, '_sc_ders', true)) . '</em>' . ($sn ? '<em class="s">' . $sn . '. sınıf</em>' : '') . self::yazar((int) $p->post_author) . '<span>· ' . esc_html(human_time_diff(get_post_time('U', true, $p)) . ' önce') . '</span></span></span></a>';
        }
        if (!$sq->posts) $h .= '<div class="sc-bos">' . ($q || $ders || $sv || $d ? 'Bu filtrelere uyan soru yok.' : 'Henüz soru yok. İlk soruyu sen sor!') . '</div>';
        $h .= '</div>';
        if ($sq->max_num_pages > 1) { $h .= '<nav class="sc-sayfa">'; for ($i = 1; $i <= min($sq->max_num_pages, 30); $i++) $h .= '<a class="' . ($i === $sf ? 'on' : '') . '" href="' . $u(array('s' => $i > 1 ? $i : '')) . '">' . $i . '</a>'; $h .= '</nav>'; }
        $h .= '</div>';
        OSC_Tk::sayfa('Soru-Cevap: Ders soruları ve cevapları', 'Matematik, Türkçe, Fen ve tüm dersler için öğrenci ve öğretmenlerin soruları ve cevapları. Takıldığın soruyu sor, hemen yardım al.', $h, self::url());
    }
    public function sor_sayfa() {
        $h = self::stil() . '<div class="sc" style="max-width:760px"><a href="' . esc_url(self::url()) . '" style="color:#5B7189;font-weight:600">‹ Soru-Cevap</a><div class="sc-kart"><h1>Soru sor</h1>';
        if (!is_user_logged_in()) $h .= '<p>Soru sormak için ücretsiz üye olmanız ya da giriş yapmanız gerekir.</p><a class="sc-btn" href="' . esc_url(self::giris_url()) . '">Giriş yap / üye ol</a>';
        else $h .= '<form class="sc-form" data-sc="sor">' . self::form_ek() . '<label>Başlık<input name="baslik" maxlength="150" required placeholder="Örn: Üslü ifadelerde negatif kuvvet nasıl hesaplanır?"></label><div class="sc-iki"><label>Ders<select name="ders" required>' . implode('', array_map(function ($x) { return '<option>' . esc_html($x) . '</option>'; }, self::$dersler)) . '</select></label><label>Sınıf<select name="sinif"><option value="0">Seçin</option>' . implode('', array_map(function ($x) { return '<option value="' . $x . '">' . $x . '. sınıf</option>'; }, range(1, 12))) . '</select></label></div><label>Sorunuz<textarea name="metin" required placeholder="Soruyu ve nerede takıldığınızı yazın. Ne denediğinizi de eklerseniz daha hızlı yardım alırsınız."></textarea></label><p class="sc-not">Kişisel bilgi (telefon, adres, okul numarası) paylaşmayın. Reklam ve uygunsuz içerik silinir. Yeni üyelerin ilk gönderileri kısa bir kontrolden sonra yayımlanır.</p><div class="sc-msj"></div><button class="sc-btn" type="submit">Soruyu gönder</button></form>';
        $h .= '</div></div>' . self::betik();
        OSC_Tk::sayfa('Soru sor', 'Takıldığın ders sorusunu sor; öğretmenler ve arkadaşların cevaplasın.', $h, home_url('/soru-cevap/sor/'));
    }
    public function detay($id) {
        $p = get_post($id);
        if (!$p || $p->post_type !== self::TUR || ($p->post_status !== 'publish' && !current_user_can('edit_post', $id))) return;
        if (strpos(home_url(OSC_Tk::yol()), rtrim(self::url($id), '/')) === false && $p->post_status === 'publish') { wp_safe_redirect(self::url($id), 301); exit; }
        $uid = get_current_user_id(); $sahip = $uid && ((int) $p->post_author === $uid || current_user_can('edit_others_posts'));
        $kb = (int) get_post_meta($id, '_sc_kabul', true); $sn = (int) get_post_meta($id, '_sc_sinif', true);
        $cv = get_comments(array('post_id' => $id, 'type' => 'osc_cevap', 'status' => 'approve', 'orderby' => 'comment_date_gmt', 'order' => 'ASC'));
        usort($cv, function ($a, $b) use ($kb) { if ((int) $a->comment_ID === $kb) return -1; if ((int) $b->comment_ID === $kb) return 1; return (int) get_comment_meta($b->comment_ID, '_sc_oy', true) - (int) get_comment_meta($a->comment_ID, '_sc_oy', true); });
        $oyb = function ($tip, $oid, $get) use ($uid) { $l = $get($oid, '_sc_oyl', true); $on = $uid && is_array($l) && in_array($uid, $l, true); return '<button type="button" data-sce="oy" data-tip="' . $tip . '" data-id="' . $oid . '" class="' . ($on ? 'on' : '') . '">👍 Faydalı <b>' . (int) $get($oid, '_sc_oy', true) . '</b></button>'; };
        $h = self::stil() . '<div class="sc" style="max-width:860px"><a href="' . esc_url(self::url()) . '" style="color:#5B7189;font-weight:600">‹ Soru-Cevap</a>';
        if ($p->post_status !== 'publish') $h .= '<div class="sc-msj hata" style="margin-top:10px">Bu soru onay bekliyor; yalnızca siz görüyorsunuz.</div>';
        $h .= '<article class="sc-kart"><span class="sc-et" style="margin:0 0 8px"><em>' . esc_html(get_post_meta($id, '_sc_ders', true)) . '</em>' . ($sn ? '<em class="s">' . $sn . '. sınıf</em>' : '') . ($kb ? '<em class="og">✓ Çözüldü</em>' : '') . '</span><h1>' . esc_html($p->post_title) . '</h1><span class="sc-et">' . self::yazar((int) $p->post_author) . '<span>· ' . esc_html(human_time_diff(get_post_time('U', true, $p)) . ' önce') . '</span></span><div class="sc-metin">' . esc_html($p->post_content) . '</div><div class="sc-eylem">' . $oyb('s', $id, 'get_post_meta') . ($uid ? '<button type="button" data-sce="sikayet" data-tip="s" data-id="' . $id . '">⚑ Bildir</button>' : '') . '</div></article>';
        $h .= '<h2 style="margin:26px 0 4px !important;font-size:19px">' . count($cv) . ' cevap</h2>';
        foreach ($cv as $c) {
            $k = (int) $c->comment_ID === $kb;
            $h .= '<div class="sc-c' . ($k ? ' kabul' : '') . '" id="c' . (int) $c->comment_ID . '">' . ($k ? '<span class="rozet">✓ Çözüm olarak seçildi</span>' : '') . '<span class="sc-et" style="margin:0">' . self::yazar((int) $c->user_id) . '<span>· ' . esc_html(human_time_diff(strtotime($c->comment_date_gmt . ' UTC')) . ' önce') . '</span></span><div class="sc-metin">' . esc_html($c->comment_content) . '</div><div class="sc-eylem">' . $oyb('c', (int) $c->comment_ID, 'get_comment_meta') . ($sahip && !$k && (int) $c->user_id !== (int) $p->post_author ? '<button type="button" data-sce="kabul" data-id="' . (int) $c->comment_ID . '">✓ Çözüm olarak seç</button>' : '') . ($uid ? '<button type="button" data-sce="sikayet" data-tip="c" data-id="' . (int) $c->comment_ID . '">⚑ Bildir</button>' : '') . '</div></div>';
        }
        if (!$cv) $h .= '<div class="sc-bos" style="margin-top:10px">Henüz cevap yok. İlk cevabı sen yaz!</div>';
        $h .= '<div class="sc-kart"><h2 style="margin:0 0 10px !important;font-size:18px">Cevabın</h2>';
        if (!$uid) $h .= '<p style="margin:0 0 10px !important">Cevap yazmak için giriş yapın.</p><a class="sc-btn" href="' . esc_url(self::giris_url()) . '">Giriş yap / üye ol</a>';
        elseif ($p->post_status === 'publish') $h .= '<form class="sc-form" data-sc="cevap">' . self::form_ek() . '<input type="hidden" name="soru" value="' . $id . '"><textarea name="metin" required placeholder="Çözümü adım adım açıklayın. Yalnızca cevabı değil, nasıl bulunduğunu da yazın."></textarea><div class="sc-msj"></div><button class="sc-btn" type="submit">Cevabı gönder</button></form>';
        $h .= '</div>';
        $ben = new WP_Query(array('post_type' => self::TUR, 'post_status' => 'publish', 'posts_per_page' => 5, 'post__not_in' => array($id), 'meta_key' => '_sc_ders', 'meta_value' => get_post_meta($id, '_sc_ders', true), 'no_found_rows' => true));
        if ($ben->posts) { $h .= '<h2 style="margin:26px 0 8px !important;font-size:18px">Benzer sorular</h2><div class="sc-l">'; foreach ($ben->posts as $b) $h .= '<a class="sc-s" href="' . esc_url(self::url($b->ID)) . '"><span style="flex:1"><h3 style="font-size:15px">' . esc_html($b->post_title) . '</h3></span></a>'; $h .= '</div>'; }
        $h .= '</div>' . self::betik();
        $ld = array('@context' => 'https://schema.org', '@type' => 'QAPage', 'mainEntity' => array('@type' => 'Question', 'name' => $p->post_title, 'text' => wp_trim_words($p->post_content, 80), 'answerCount' => count($cv), 'dateCreated' => get_post_time('c', true, $p), 'author' => array('@type' => 'Person', 'name' => OSC_Tk::kisa_ad((int) $p->post_author))));
        foreach ($cv as $c) { $a = array('@type' => 'Answer', 'text' => wp_trim_words($c->comment_content, 120), 'upvoteCount' => (int) get_comment_meta($c->comment_ID, '_sc_oy', true), 'dateCreated' => mysql2date('c', $c->comment_date_gmt), 'url' => self::url($id) . '#c' . $c->comment_ID, 'author' => array('@type' => 'Person', 'name' => OSC_Tk::kisa_ad((int) $c->user_id))); if ((int) $c->comment_ID === $kb) $ld['mainEntity']['acceptedAnswer'] = $a; else $ld['mainEntity']['suggestedAnswer'][] = $a; }
        OSC_Tk::sayfa($p->post_title, $p->post_content, $h, self::url($id), $ld);
    }
}

/* ---------------------------------------------------------------------
 * KULLANIM İSTATİSTİĞİ
 * ------------------------------------------------------------------- */
class OSC_Istat {
    const OPT = 'osc_istat_g';
    public static $adlar = array('test_coz' => 'Online test çözümü', 'teslim' => 'Ödev / sınav teslimi', 'oturum_canli' => 'Canlı sınav açıldı', 'oturum_odev' => 'Ödev verildi', 'yazili_indir' => 'Yazılı indirildi', 'evrak' => 'Evrak indirildi', 'uye' => 'Yeni üye', 'forum_soru' => 'Soru-Cevap: soru', 'forum_cevap' => 'Soru-Cevap: cevap', 'test_yayin' => 'Yayımlanan test', 'eposta' => 'Hatırlatma e-postası');
    public function __construct() {
        add_action('osc_cozum_kaydedildi', function () { OSC_Istat::say('test_coz'); });
        add_action('osc_teslim_edildi', function () { OSC_Istat::say('teslim'); });
        add_action('osc_oturum_olustu', function ($tur) { OSC_Istat::say($tur === 'odev' ? 'oturum_odev' : 'oturum_canli'); });
        add_action('user_register', function () { OSC_Istat::say('uye'); });
        add_action('osc_sc_soru', function () { OSC_Istat::say('forum_soru'); });
        add_action('osc_sc_cevap', function () { OSC_Istat::say('forum_cevap'); });
        add_action('transition_post_status', function ($y, $e, $p) { if ($p->post_type === 'osc_quiz' && $y === 'publish' && $e !== 'publish') OSC_Istat::say('test_yayin'); }, 10, 3);
        add_action('wp_ajax_osc_istat', array($this, 'isaret')); add_action('wp_ajax_nopriv_osc_istat', array($this, 'isaret'));
        add_action('admin_menu', array($this, 'menu'));
    }
    public static function say($k, $n = 1) {
        $g = get_option(self::OPT, array()); if (!is_array($g)) $g = array(); $d = wp_date('Y-m-d');
        if (!isset($g[$d])) { $g[$d] = array(); ksort($g); while (count($g) > 400) array_shift($g); }
        $g[$d][$k] = (isset($g[$d][$k]) ? $g[$d][$k] : 0) + $n; update_option(self::OPT, $g, false);
    }
    /** tarayıcıdan gelen indirme işaretleri (evrak, yazılı) */
    public function isaret() {
        $k = isset($_POST['k']) ? sanitize_key($_POST['k']) : ''; if (!preg_match('/^(evrak_[a-z_]{2,20}|yazili_indir)$/', $k)) wp_die();
        $t = 'osc_is_' . md5(OSC_Tk::ip()); $n = (int) get_transient($t); if ($n > 120) wp_die(); set_transient($t, $n + 1, HOUR_IN_SECONDS);
        if (strpos($k, 'evrak_') === 0) { self::say('evrak'); self::say($k); } else self::say($k);
        wp_die();
    }
    public function menu() { add_submenu_page('edit.php?post_type=osc_quiz', 'Kullanım istatistiği', 'İstatistik', 'edit_others_posts', 'osc-istat', array($this, 'sayfa')); }
    public function sayfa() {
        global $wpdb; $g = get_option(self::OPT, array()); if (!is_array($g)) $g = array();
        $gun = (int) ($_GET['g'] ?? 30); if (!in_array($gun, array(7, 30, 90, 365), true)) $gun = 30; $m = isset($_GET['m'], self::$adlar[$_GET['m']]) ? $_GET['m'] : 'test_coz';
        $top = function ($k, $bas, $bit) use ($g) { $s = 0; for ($t = $bas; $t <= $bit; $t += DAY_IN_SECONDS) { $d = wp_date('Y-m-d', $t); if (isset($g[$d][$k])) $s += $g[$d][$k]; } return $s; };
        $bit = time(); $bas = $bit - ($gun - 1) * DAY_IN_SECONDS; $obit = $bas - DAY_IN_SECONDS; $obas = $obit - ($gun - 1) * DAY_IN_SECONDS;
        $rol = $wpdb->get_results("SELECT meta_value AS r, COUNT(*) AS n FROM {$wpdb->usermeta} WHERE meta_key = 'osc_rol' GROUP BY meta_value", OBJECT_K);
        $czt = $wpdb->prefix . 'osc_cozum'; $cz_top = (int) $wpdb->get_var("SELECT COUNT(*) FROM $czt");
        $quiz = wp_count_posts('osc_quiz'); $soru = wp_count_posts('osc_soru');
        $ev = array(); foreach ($g as $d => $v) if (strtotime($d) >= $bas - DAY_IN_SECONDS) foreach ($v as $k => $n) if (strpos($k, 'evrak_') === 0) $ev[substr($k, 6)] = (isset($ev[substr($k, 6)]) ? $ev[substr($k, 6)] : 0) + $n; arsort($ev);
        $evad = class_exists('OSC_Evrak') ? OSC_Evrak::$turler : array();
        $u = function ($e) use ($gun, $m) { return esc_url(add_query_arg(array_merge(array('post_type' => 'osc_quiz', 'page' => 'osc-istat', 'g' => $gun, 'm' => $m), $e), admin_url('edit.php'))); };
        $seri = array(); $mx = 1; for ($t = $bas; $t <= $bit; $t += DAY_IN_SECONDS) { $d = wp_date('Y-m-d', $t); $v = isset($g[$d][$m]) ? (int) $g[$d][$m] : 0; $seri[] = array($d, $v); $mx = max($mx, $v); } ?>
<div class="wrap"><h1>Kullanım istatistiği</h1>
<style>.oi-k{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px;margin:14px 0}.oi-k a{display:block;padding:14px 16px;border-radius:14px;background:#fff;border:1px solid #dcdcde;text-decoration:none;color:#1d2327}.oi-k a.on{border:2px solid #2271b1;box-shadow:0 0 0 3px #e7f0f9}.oi-k b{display:block;font-size:26px;margin:4px 0}.oi-k small{color:#646970}.oi-k i{font-style:normal;font-weight:600}.oi-k i.a{color:#008a20}.oi-k i.d{color:#b32d2e}
.oi-g{background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:16px;margin-bottom:16px}.oi-bar{display:flex;align-items:flex-end;gap:2px;height:180px;border-bottom:1px solid #dcdcde}.oi-bar span{flex:1;background:#2271b1;border-radius:3px 3px 0 0;min-height:1px;position:relative}.oi-bar span:hover{background:#135e96}.oi-bar span:hover:after{content:attr(data-t);position:absolute;bottom:100%;left:50%;transform:translateX(-50%);background:#1d2327;color:#fff;padding:3px 7px;border-radius:5px;font-size:11px;white-space:nowrap}
.oi-iki{display:grid;grid-template-columns:1fr 1fr;gap:16px}@media(max-width:900px){.oi-iki{grid-template-columns:1fr}}</style>
<p><?php foreach (array(7, 30, 90, 365) as $x) echo '<a class="button' . ($x === $gun ? ' button-primary' : '') . '" href="' . $u(array('g' => $x)) . '">Son ' . $x . ' gün</a> '; ?></p>
<div class="oi-k"><?php foreach (self::$adlar as $k => $ad) { $s = $top($k, $bas, $bit); $o = $top($k, $obas, $obit); $f = $o ? round(($s - $o) / $o * 100) : ($s ? 100 : 0);
    echo '<a href="' . $u(array('m' => $k)) . '" class="' . ($k === $m ? 'on' : '') . '"><small>' . esc_html($ad) . '</small><b>' . number_format_i18n($s) . '</b><i class="' . ($f >= 0 ? 'a' : 'd') . '">' . ($f >= 0 ? '▲ ' : '▼ ') . abs($f) . '%</i> <small>önceki ' . $gun . ' güne göre</small></a>'; } ?></div>
<div class="oi-g"><h2 style="margin-top:0"><?php echo esc_html(self::$adlar[$m]); ?> — günlük</h2><div class="oi-bar"><?php foreach ($seri as $s) echo '<span style="height:' . round($s[1] / $mx * 100, 1) . '%" data-t="' . esc_attr(wp_date('j M', strtotime($s[0])) . ': ' . $s[1]) . '"></span>'; ?></div><p style="color:#646970;margin-bottom:0"><?php echo esc_html(wp_date('j M Y', $bas) . ' – ' . wp_date('j M Y', $bit)); ?> · en yüksek gün: <?php echo (int) $mx; ?></p></div>
<div class="oi-iki"><div class="oi-g"><h2 style="margin-top:0">Genel toplamlar</h2><table class="widefat striped"><tbody>
<tr><td>Öğretmen üye</td><td><b><?php echo number_format_i18n(isset($rol['ogretmen']) ? $rol['ogretmen']->n : 0); ?></b></td></tr><tr><td>Öğrenci üye</td><td><b><?php echo number_format_i18n(isset($rol['ogrenci']) ? $rol['ogrenci']->n : 0); ?></b></td></tr>
<tr><td>Yayındaki online test</td><td><b><?php echo number_format_i18n((int) ($quiz->publish ?? 0)); ?></b> (onay bekleyen: <?php echo (int) ($quiz->pending ?? 0); ?>)</td></tr><tr><td>Kayıtlı test çözümü (üyeler)</td><td><b><?php echo number_format_i18n($cz_top); ?></b></td></tr>
<tr><td>Soru-Cevap</td><td><b><?php echo number_format_i18n((int) ($soru->publish ?? 0)); ?></b> soru (onay bekleyen: <?php echo (int) ($soru->pending ?? 0); ?>)</td></tr></tbody></table><p style="color:#646970">Sayaçlar bu sürümden itibaren tutulur. Öğrenci bilgisi kaydedilmez; yalnızca günlük toplam sayılar saklanır.</p></div>
<div class="oi-g"><h2 style="margin-top:0">En çok indirilen evraklar (son <?php echo $gun; ?> gün)</h2><?php if ($ev) { echo '<table class="widefat striped"><tbody>'; foreach (array_slice($ev, 0, 15, true) as $k => $n) echo '<tr><td>' . esc_html(isset($evad[$k]) ? $evad[$k] : $k) . '</td><td><b>' . number_format_i18n($n) . '</b></td></tr>'; echo '</tbody></table>'; } else echo '<p>Henüz veri yok.</p>'; ?></div></div>
<?php OSC_Hatirlat::ayar_formu(); ?>
</div>
<?php }
}

/* ---------------------------------------------------------------------
 * HATIRLATMA E-POSTALARI
 * ------------------------------------------------------------------- */
class OSC_Hatirlat {
    public static $ayarlar = array('osc_ht_odev' => 'Ödev son tarihi yaklaşınca öğrenciye hatırlatma (son 24 saat)', 'osc_ht_cevap' => 'Sorusu cevaplanan üyeye bildirim (Soru-Cevap)', 'osc_ht_haftalik' => 'Pazar akşamı öğrencilere haftalık özet (XP, sıralama, rozetler)', 'osc_ht_ogretmen' => 'Pazar akşamı öğretmenlere haftalık sınıf özeti');
    public function __construct() {
        add_action('init', array($this, 'planla')); add_action('osc_tk_gunluk', array($this, 'gunluk'));
        add_action('osc_sc_cevap', array($this, 'cevap_bildir'), 10, 2);
        add_action('admin_init', function () { foreach (array_keys(OSC_Hatirlat::$ayarlar) as $k) register_setting('osc_tk', $k, array('sanitize_callback' => function ($v) { return $v ? 1 : 0; })); });
        add_action('admin_post_osc_ht_deneme', array($this, 'deneme'));
        add_action('template_redirect', array($this, 'iptal'), 1);
    }
    public static function acik($k) { return (int) get_option($k, 1) === 1; }
    public function planla() {
        if (!wp_next_scheduled('osc_tk_gunluk')) { $t = strtotime(wp_date('Y-m-d') . ' 17:30:00 ' . wp_timezone_string()); if ($t < time()) $t += DAY_IN_SECONDS; wp_schedule_event($t, 'daily', 'osc_tk_gunluk'); }
    }
    public static function ayar_formu() { ?>
<div class="oi-g" style="background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:16px"><h2 style="margin-top:0">E-posta hatırlatmaları ve Soru-Cevap ayarları</h2>
<form method="post" action="options.php"><?php settings_fields('osc_tk'); foreach (self::$ayarlar as $k => $ad) echo '<p><label><input type="hidden" name="' . esc_attr($k) . '" value="0"><input type="checkbox" name="' . esc_attr($k) . '" value="1" ' . checked(self::acik($k), true, false) . '> ' . esc_html($ad) . '</label></p>'; ?>
<p><label><b>Soru-Cevap yasaklı sözcükler</b> (her satıra bir tane; içeren gönderiler onaya düşer)<br><textarea name="osc_sc_yasak" rows="4" class="large-text"><?php echo esc_textarea(get_option('osc_sc_yasak', '')); ?></textarea></label></p>
<p class="description">E-postalar her gün 17.30'da gönderilir; her e-postada “bu e-postaları almak istemiyorum” bağlantısı bulunur. Soru-Cevap'ta yeni üyelerin ve bağlantı içeren gönderilerin ilk paylaşımları onaya düşer; onay bekleyenler sol menüdeki “Soru-Cevap” ve “Yorumlar” bölümündedir. 3 kişinin bildirdiği gönderi otomatik gizlenir.</p>
<?php submit_button('Kaydet'); ?></form>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="osc_ht_deneme"><?php wp_nonce_field('osc_ht_deneme'); ?><button class="button">Bana örnek haftalık özet gönder</button></form></div>
<?php }
    public static function iptal_url($uid) { return add_query_arg(array('osc_ht_iptal' => $uid, 't' => substr(wp_hash('osc_ht' . $uid), 0, 20)), home_url('/')); }
    public function iptal() {
        if (!isset($_GET['osc_ht_iptal'])) return; $uid = (int) $_GET['osc_ht_iptal'];
        if (!$uid || !hash_equals(substr(wp_hash('osc_ht' . $uid), 0, 20), (string) ($_GET['t'] ?? ''))) return;
        update_user_meta($uid, 'osc_ht_kapali', 1); status_header(200);
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Abonelik</title><body style="font-family:system-ui;background:#F4F8FC;display:grid;place-items:center;min-height:100vh;margin:0"><div style="background:#fff;padding:32px;border-radius:20px;max-width:420px;text-align:center"><h2>Tamam 👍</h2><p>Artık hatırlatma e-postası almayacaksınız.</p><a href="' . esc_url(home_url('/')) . '">Siteye dön</a></div></body>'; exit;
    }
    public static function gonder($uid, $konu, $baslik, $govde, $dugme = null) {
        $u = get_userdata($uid); if (!$u || !is_email($u->user_email) || get_user_meta($uid, 'osc_ht_kapali', true) || get_user_meta($uid, 'osc_dogrulama', true)) return false;
        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $h = '<div style="background:#F4F8FC;padding:24px 12px;font-family:Segoe UI,Arial,sans-serif"><div style="max-width:560px;margin:0 auto;background:#fff;border-radius:18px;overflow:hidden;border:1px solid #E3EBF3"><div style="background:#13304F;color:#fff;padding:18px 24px;font-weight:800;font-size:18px">' . esc_html($site) . '</div><div style="padding:24px;color:#1C3653;font-size:15px;line-height:1.6"><h2 style="margin:0 0 12px;font-size:20px;color:#13304F">' . esc_html($baslik) . '</h2>' . $govde .
            ($dugme ? '<p style="margin:22px 0 4px"><a href="' . esc_url($dugme[1]) . '" style="display:inline-block;background:#2F80ED;color:#fff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:12px">' . esc_html($dugme[0]) . '</a></p>' : '') .
            '</div><div style="padding:14px 24px;background:#F7FAFD;color:#8AA2BA;font-size:12px">Bu e-postayı ' . esc_html($site) . ' üyeliğiniz nedeniyle aldınız. <a href="' . esc_url(self::iptal_url($uid)) . '" style="color:#8AA2BA">Hatırlatma e-postalarını kapat</a></div></div></div>';
        $ok = wp_mail($u->user_email, $konu, $h, array('Content-Type: text/html; charset=UTF-8'));
        if ($ok && class_exists('OSC_Istat')) OSC_Istat::say('eposta');
        return $ok;
    }
    public function cevap_bildir($cid, $qid) {
        if (!self::acik('osc_ht_cevap')) return; $q = get_post($qid); $c = get_comment($cid); if (!$q || !$c || (int) $c->user_id === (int) $q->post_author) return;
        $k = 'osc_ht_cv_' . $qid; if (get_transient($k)) return; set_transient($k, 1, 3 * HOUR_IN_SECONDS);   // aynı soru için 3 saatte bir
        self::gonder((int) $q->post_author, 'Sorunuza yeni cevap geldi', 'Sorunuza cevap yazıldı 💬', '<p><b>' . esc_html($q->post_title) . '</b></p><p style="color:#5B7189">' . esc_html(OSC_Tk::kisa_ad((int) $c->user_id)) . ': “' . esc_html(wp_trim_words($c->comment_content, 30, '…')) . '”</p><p>Cevap işinize yaradıysa “Çözüm olarak seç” ile teşekkür edebilirsiniz.</p>', array('Cevabı gör', OSC_Forum::url($qid) . '#c' . $cid));
    }
    public function gunluk() {
        if (self::acik('osc_ht_odev')) $this->odev();
        if (wp_date('N') === '7') { if (self::acik('osc_ht_haftalik')) $this->haftalik_ogrenci(); if (self::acik('osc_ht_ogretmen')) $this->haftalik_ogretmen(); }
    }
    private function odev() {
        global $wpdb; $ot = $wpdb->prefix . 'osc_oturum'; $kt = $wpdb->prefix . 'osc_katilim'; $now = time();
        $l = $wpdb->get_results($wpdb->prepare("SELECT k.id, k.user_id, o.baslik, o.son_tarih, o.kod FROM $kt k JOIN $ot o ON o.id = k.oturum_id WHERE o.tur = 'odev' AND o.son_tarih > %d AND o.son_tarih <= %d AND k.durum <> 'bitti' AND k.user_id > 0 LIMIT 500", $now, $now + 30 * HOUR_IN_SECONDS));
        $g = get_option('osc_ht_gonderilen', array()); $g = is_array($g) ? $g : array();
        foreach ($l as $r) { if (isset($g[$r->id])) continue; $g[$r->id] = $now;
            self::gonder((int) $r->user_id, 'Ödevinin teslim süresi yaklaşıyor', 'Ödevin seni bekliyor ⏰', '<p><b>' . esc_html($r->baslik) . '</b> ödevinin son teslim zamanı: <b>' . esc_html(wp_date('j F l, H:i', (int) $r->son_tarih)) . '</b>.</p><p>Henüz bitirmedin. Teslim edersen <b>20 XP</b> ve puanına göre ek XP kazanırsın.</p>', array('Ödeve devam et', add_query_arg('osc_k', $r->kod, home_url('/')))); }
        foreach ($g as $k => $t) if ($t < $now - 30 * DAY_IN_SECONDS) unset($g[$k]); update_option('osc_ht_gonderilen', $g, false);
    }
    private static function ogrenci_govde($uid) {
        $o = OSC_Oyun::ozet($uid); $lig = array('sira' => 0); $r = array_filter($o['rozet'], function ($x) { return $x['var']; });
        return '<p>Bu hafta <b>' . (int) $o['hafta'] . ' XP</b> topladın' . ($lig['sira'] ? ' ve ' . ($o['seviye'] ? $o['seviye'] . '. sınıflar arasında ' : '') . '<b>' . (int) $lig['sira'] . '. sıradasın</b>' : '') . '. Toplam ' . (int) $o['xp'] . ' XP ile <b>' . (int) $o['sv'] . '. seviyedesin</b>.</p>'
            . ($r ? '<p>Rozetlerin: ' . implode(' ', array_map(function ($x) { return $x['ik']; }, $r)) . '</p>' : '')
            . '<p>Yeni hafta pazartesi başlıyor; lig sıfırlanıyor. Her gün bir test çözersen seri bonusu da kazanırsın 🔥</p>';
    }
    private function haftalik_ogrenci() {
        global $wpdb; $k = OSC_Oyun::hk();
        $ids = $wpdb->get_col($wpdb->prepare("SELECT x.user_id FROM {$wpdb->usermeta} x JOIN {$wpdb->usermeta} r ON r.user_id = x.user_id AND r.meta_key = 'osc_rol' AND r.meta_value = 'ogrenci' WHERE x.meta_key = %s AND (x.meta_value + 0) > 0 LIMIT 1000", $k));
        foreach ($ids as $id) self::gonder((int) $id, 'Haftalık özetin: ' . (int) get_user_meta($id, $k, true) . ' XP', 'Bu haftan nasıl geçti? 🏆', self::ogrenci_govde((int) $id), array('Paneline git', class_exists('OSC_Sinav') ? OSC_Sinav::sayfa_url('osc_sp_ogrenci_sayfa') : home_url('/')));
    }
    private function haftalik_ogretmen() {
        global $wpdb; $ot = $wpdb->prefix . 'osc_oturum'; $kt = $wpdb->prefix . 'osc_katilim'; $bas = time() - 7 * DAY_IN_SECONDS;
        $l = $wpdb->get_results($wpdb->prepare("SELECT o.ogretmen_id, COUNT(DISTINCT o.id) AS ot, COUNT(k.id) AS kt, AVG(CASE WHEN k.durum = 'bitti' THEN k.puan END) AS ort FROM $ot o LEFT JOIN $kt k ON k.oturum_id = o.id WHERE o.olusturma > %d GROUP BY o.ogretmen_id LIMIT 1000", $bas));
        foreach ($l as $r) self::gonder((int) $r->ogretmen_id, 'Haftalık sınıf özetiniz', 'Bu hafta sınıflarınızda neler oldu? 📊', '<p>Bu hafta <b>' . (int) $r->ot . '</b> canlı sınav / ödev açtınız; <b>' . (int) $r->kt . '</b> öğrenci katıldı' . ($r->ort !== null ? ', ortalama puan <b>' . round((float) $r->ort) . '</b>' : '') . '.</p><p>Ayrıntılı sonuçları, konu analizini ve yanlış yapılan soruları panelinizde görebilirsiniz.</p>', array('Öğretmen paneline git', class_exists('OSC_Sinav') ? OSC_Sinav::sayfa_url('osc_sp_panel_sayfa') : home_url('/')));
    }
    public function deneme() {
        check_admin_referer('osc_ht_deneme'); if (!current_user_can('edit_others_posts')) wp_die('Yetkiniz yok.');
        $uid = get_current_user_id(); delete_user_meta($uid, 'osc_ht_kapali');
        $ok = self::gonder($uid, 'Örnek: haftalık özet', 'Bu haftan nasıl geçti? 🏆', self::ogrenci_govde($uid), array('Paneline git', home_url('/')));
        wp_safe_redirect(add_query_arg(array('post_type' => 'osc_quiz', 'page' => 'osc-istat', 'deneme' => $ok ? 1 : 0), admin_url('edit.php'))); exit;
    }
}

new OSC_Oyun(); new OSC_Forum(); new OSC_Istat(); new OSC_Hatirlat();
}
