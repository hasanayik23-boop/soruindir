
/* =====================================================================
 * Yıllık plan: çalışma takvimi + öğretim programı kütüphanesi
 * Her yıl: yönetici takvimi günceller; öğretim programı değişen dersin
 * programını (MEB PDF'inden yapay zekâ ile ya da elle) yeniden yükler.
 * ===================================================================== */
if (!class_exists('OSC_YPlan')) {
class OSC_YPlan {
    public function __construct() {
        add_action('admin_menu', array($this, 'menu'), 32);
        foreach (array('program') as $a) { add_action('wp_ajax_osc_yp_' . $a, array($this, 'ajax_' . $a)); add_action('wp_ajax_nopriv_osc_yp_' . $a, array($this, 'ajax_' . $a)); }
        foreach (array('yz', 'ayikla', 'kaydet', 'sil', 'takvim') as $a) add_action('wp_ajax_osc_yp_' . $a, array($this, 'ajax_' . $a));
    }
    /* ---------- çalışma takvimi ---------- */
    public static function varsayilan_takvim() {
        return array('yil' => '2026-2027', 'baslangic' => '2026-09-14', 'bitis' => '2027-06-25',
            'tatiller' => array(
                array('ad' => '1. dönem ara tatili', 'bas' => '2026-11-16', 'bit' => '2026-11-20'),
                array('ad' => 'Yarıyıl tatili', 'bas' => '2027-01-25', 'bit' => '2027-02-05'),
                array('ad' => '2. dönem ara tatili', 'bas' => '2027-03-08', 'bit' => '2027-03-12')),
            'resmi' => array(
                array('ad' => 'Cumhuriyet Bayramı', 'bas' => '2026-10-29', 'bit' => '2026-10-29'),
                array('ad' => 'Yılbaşı', 'bas' => '2027-01-01', 'bit' => '2027-01-01'),
                array('ad' => 'Ulusal Egemenlik ve Çocuk Bayramı', 'bas' => '2027-04-23', 'bit' => '2027-04-23'),
                array('ad' => 'Emek ve Dayanışma Günü', 'bas' => '2027-05-01', 'bit' => '2027-05-01'),
                array('ad' => 'Kurban Bayramı', 'bas' => '2027-05-15', 'bit' => '2027-05-19'),
                array('ad' => 'Atatürk’ü Anma, Gençlik ve Spor Bayramı', 'bas' => '2027-05-19', 'bit' => '2027-05-19')),
            'donem2' => '2027-02-08');
    }
    public static function takvim() { $t = get_option('osc_yp_takvim'); return is_array($t) && !empty($t['baslangic']) ? $t : self::varsayilan_takvim(); }

    /* ---------- ders kataloğu (tüm sınıflar) ---------- */
    /** sınıf => [ ['g' => grup adı, 'd' => [ders, ...]], ... ] */
    public static function katalog() {
        $c = array();
        $ekle = function ($grup, $ad, $siniflar) use (&$c) { foreach ($siniflar as $s) { $s = (int) $s; if ($s < 1 || $s > 12) continue; if (!isset($c[$s][$grup])) $c[$s][$grup] = array(); if (!in_array($ad, $c[$s][$grup], true)) $c[$s][$grup][] = $ad; } };
        $o = 'Ortak dersler';
        $ekle($o, 'Türkçe', range(1, 8)); $ekle($o, 'Matematik', range(1, 12)); $ekle($o, 'Hayat Bilgisi', array(1, 2, 3)); $ekle($o, 'Fen Bilimleri', range(3, 8)); $ekle($o, 'Sosyal Bilgiler', array(4, 5, 6, 7));
        $ekle($o, 'İngilizce', range(2, 12)); $ekle($o, 'Din Kültürü ve Ahlak Bilgisi', range(4, 12)); $ekle($o, 'Trafik Güvenliği', array(4)); $ekle($o, 'İnsan Hakları, Yurttaşlık ve Demokrasi', array(4));
        $ekle($o, 'Görsel Sanatlar', range(1, 12)); $ekle($o, 'Müzik', range(1, 12)); $ekle($o, 'Beden Eğitimi ve Oyun', range(1, 4)); $ekle($o, 'Beden Eğitimi ve Spor', range(5, 12));
        $ekle($o, 'T.C. İnkılap Tarihi ve Atatürkçülük', array(8, 12)); $ekle($o, 'Teknoloji ve Tasarım', array(7, 8)); $ekle($o, 'Bilişim Teknolojileri ve Yazılım', array(5, 6));
        $ekle($o, 'Türk Dili ve Edebiyatı', range(9, 12)); $ekle($o, 'Fizik', range(9, 12)); $ekle($o, 'Kimya', range(9, 12)); $ekle($o, 'Biyoloji', range(9, 12)); $ekle($o, 'Tarih', array(9, 10, 11)); $ekle($o, 'Coğrafya', array(9, 10, 11, 12)); $ekle($o, 'Felsefe', array(10, 11));
        $ekle($o, 'Sağlık Bilgisi ve Trafik Kültürü', array(9)); $ekle($o, 'İkinci Yabancı Dil', range(9, 12));
        if (class_exists('OSC_Yazili')) {
            $d = json_decode(OSC_Yazili::diger_json(), true);
            if (is_array($d) && !empty($d['gruplar'])) foreach ($d['gruplar'] as $g) {
                $ga = isset($g['ad']) ? $g['ad'] : 'Diğer dersler';
                if (!empty($g['dersler'])) foreach ($g['dersler'] as $x) if (!empty($x['ad']) && !empty($x['s'])) $ekle($ga, $x['ad'], (array) $x['s']);
                if (!empty($g['alanlar'])) foreach ($g['alanlar'] as $al) if (!empty($al['dersler'])) foreach ($al['dersler'] as $x) if (!empty($x['ad']) && !empty($x['s'])) $ekle('Meslek: ' . (isset($al['ad']) ? $al['ad'] : ''), $x['ad'], (array) $x['s']);
            }
        }
        foreach (self::kutuphane() as $k => $v) { list($s, $ad) = explode('|', $k, 2); $var = false; if (isset($c[(int) $s])) foreach ($c[(int) $s] as $l) if (in_array($ad, $l, true)) $var = true; if (!$var) $ekle('Diğer', $ad, array((int) $s)); }
        $out = array(); ksort($c);
        foreach ($c as $s => $gr) { $o2 = array(); foreach ($gr as $g => $l) { usort($l, function ($a, $b) { return strcmp(OSC_YPlan::tr_sira($a), OSC_YPlan::tr_sira($b)); }); $o2[] = array('g' => $g, 'd' => $l); } $out[$s] = $o2; }
        return $out;
    }
    public static function tr_sira($s) { return strtr(mb_strtolower($s), array('ç' => 'c~', 'ğ' => 'g~', 'ı' => 'h~', 'i' => 'i', 'ö' => 'o~', 'ş' => 's~', 'ü' => 'u~')); }

    /* ---------- öğretim programı kütüphanesi ---------- */
    public static function kutuphane() { $k = get_option('osc_yp_kutuphane'); return is_array($k) ? $k : array(); }
    private static function kutuphane_yaz($k) { if (get_option('osc_yp_kutuphane') === false) add_option('osc_yp_kutuphane', $k, '', 'no'); else update_option('osc_yp_kutuphane', $k, false); }
    /** MEB senaryo tablolarındaki çıktılardan (tüm dönemler) ünite listesi */
    private static function senaryodan($sinif, $ders) {
        if (!class_exists('OSC_Havuz')) return null;
        $s = OSC_Havuz::senaryolar(); $u = array(); $don = array();
        foreach ($s['tablolar'] as $t) {
            if ((int) $t['sinif'] !== (int) $sinif || $t['ders'] !== $ders) continue;
            $don[] = (int) (isset($t['donem']) ? $t['donem'] : 1);
            foreach ((array) $t['satirlar'] as $r) {
                $ad = trim((string) (!empty($r['unite']) ? $r['unite'] : (isset($r['konu']) ? $r['konu'] : 'Ünite')));
                $c = trim(preg_replace('/([a-zçğıöşü])a\)\s+[A-ZÇĞİÖŞÜ][\s\S]*$/u', '$1', (string) $r['cikti']));
                $son = count($u) - 1;
                if ($son < 0 || $u[$son]['ad'] !== $ad) { $u[] = array('ad' => $ad, 'saat' => 0, 'ciktilar' => array()); $son++; }
                $var = false; foreach ($u[$son]['ciktilar'] as $x) if ($x['kod'] === $r['kod']) $var = true;
                if (!$var) $u[$son]['ciktilar'][] = array('kod' => (string) $r['kod'], 'ad' => $c);
            }
        }
        if (!$u) return null;
        $don = array_values(array_unique($don)); sort($don);
        return array('kaynak' => 'senaryo', 'donemler' => $don, 'uniteler' => $u);
    }
    public function ajax_program() {
        $sinif = (int) (isset($_POST['sinif']) ? $_POST['sinif'] : 0); $ders = sanitize_text_field(wp_unslash(isset($_POST['ders']) ? $_POST['ders'] : ''));
        $k = self::kutuphane(); $anah = $sinif . '|' . $ders;
        if (isset($k[$anah]) && (empty($k[$anah]['durum']) || $k[$anah]['durum'] === 'onayli' || current_user_can('manage_options'))) wp_send_json_success(array_merge(array('kaynak' => 'program'), $k[$anah]));
        $s = self::senaryodan($sinif, $ders); if ($s) wp_send_json_success($s);
        if (isset($k[$anah])) wp_send_json_success(array_merge(array('kaynak' => 'taslak'), $k[$anah]));
        wp_send_json_success(array('kaynak' => 'yok', 'uniteler' => array()));
    }
    /** yapay zekânın döndürdüğü ünite listesini temizle */
    private static function temizle($r) {
        $l = isset($r['uniteler']) ? $r['uniteler'] : (isset($r[0]) ? $r : array()); $o = array();
        foreach ((array) $l as $u) {
            if (!is_array($u) || empty($u['ad'])) continue;
            $c = array(); foreach ((array) (isset($u['ciktilar']) ? $u['ciktilar'] : array()) as $x) { if (is_string($x)) $x = array('kod' => '', 'ad' => $x); if (!is_array($x) || empty($x['ad'])) continue; $c[] = array('kod' => mb_substr(sanitize_text_field((string) (isset($x['kod']) ? $x['kod'] : '')), 0, 30), 'ad' => mb_substr(sanitize_text_field((string) $x['ad']), 0, 600)); }
            $o[] = array('ad' => mb_substr(sanitize_text_field((string) $u['ad']), 0, 200), 'saat' => max(0, min(400, (int) (isset($u['saat']) ? $u['saat'] : 0))), 'ciktilar' => array_slice($c, 0, 60));
        }
        return array_slice($o, 0, 30);
    }
    /** öğretmen: programı olmayan ders için yapay zekâ taslağı (üyeler, yapay zekâ açıkken). Sonuç yöneticinin incelemesi için kütüphaneye “taslak” olarak düşer. */
    public function ajax_yz() {
        if (!is_user_logged_in() || !wp_verify_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '', 'osc_ev')) wp_send_json_error('Yapay zekâ için ücretsiz üye girişi yapın.', 403);
        if (!class_exists('OSC_Evrak') || !OSC_Evrak::yz_acik()) wp_send_json_error('Yapay zekâ şu an kapalı.');
        $sinif = (int) (isset($_POST['sinif']) ? $_POST['sinif'] : 0); $ders = sanitize_text_field(wp_unslash(isset($_POST['ders']) ? $_POST['ders'] : ''));
        if ($sinif < 1 || $sinif > 12 || $ders === '') wp_send_json_error('Sınıf ve ders seçin.');
        $k = self::kutuphane(); $anah = $sinif . '|' . $ders;
        if (isset($k[$anah])) wp_send_json_success(array_merge(array('kaynak' => !empty($k[$anah]['durum']) && $k[$anah]['durum'] === 'taslak' ? 'taslak' : 'program'), $k[$anah]));
        $kk = 'osc_yp_yz_' . get_current_user_id() . '_' . gmdate('Ymd'); $n = (int) get_transient($kk);
        if ($n >= 5 && !current_user_can('manage_options')) wp_send_json_error('Bugünkü yapay zekâ hakkınız doldu.'); set_transient($kk, $n + 1, DAY_IN_SECONDS);
        $p = "Türkiye’de MEB Türkiye Yüzyılı Maarif Modeli kapsamında okutulan {$sinif}. sınıf “{$ders}” dersinin öğretim programındaki ünite/temaları ve her birinin öğrenme çıktılarını, yaklaşık ders saatleriyle birlikte yaz. Bilmediğin kodları uydurma; kod alanını boş bırak. Ünite sırası öğretim programındaki sıra olsun.\nYalnızca şu biçimde geçerli JSON döndür:\n{\"uniteler\":[{\"ad\":\"ünite/tema adı\",\"saat\":24,\"ciktilar\":[{\"kod\":\"\",\"ad\":\"öğrenme çıktısı\"}]}]}";
        $r = OSC_YZ_API::json($p, 6000, 80);
        if (!is_array($r)) wp_send_json_error(is_string($r) ? $r : 'Yapay zekâ yanıt vermedi.');
        $u = self::temizle($r); if (!$u) wp_send_json_error('Yapay zekâ yanıtı çözümlenemedi.');
        $k[$anah] = array('yil' => self::takvim()['yil'], 'durum' => 'taslak', 'kaynak_ad' => 'Yapay zekâ taslağı (yönetici onayı bekliyor)', 'tarih' => current_time('Y-m-d'), 'uniteler' => $u);
        self::kutuphane_yaz($k);
        wp_send_json_success(array_merge(array('kaynak' => 'taslak'), $k[$anah]));
    }
    /* ---------- yönetim ---------- */
    private function yonetici() { if (!current_user_can('manage_options') || !wp_verify_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '', 'osc_yp_yon')) wp_send_json_error('Yetkisiz.', 403); }
    public function ajax_ayikla() {
        $this->yonetici();
        $sinif = (int) $_POST['sinif']; $ders = sanitize_text_field(wp_unslash($_POST['ders'])); $metin = mb_substr(wp_unslash(isset($_POST['metin']) ? $_POST['metin'] : ''), 0, 60000);
        if (mb_strlen($metin) < 200) wp_send_json_error('Metin çok kısa. PDF’te doğru sayfaları seçtiğinizden emin olun.');
        $p = "Aşağıda MEB öğretim programı belgesinden çıkarılmış metin var. Bu metinden yalnızca {$sinif}. SINIF “{$ders}” dersine ait ünite/temaları, her ünitenin/temanın ders saatini (süresini) ve öğrenme çıktılarını (kodlarıyla birlikte, ör. MAT.5.1.1) sırasıyla çıkar. Öğrenme çıktısının alt maddelerini (a, b, c… süreç bileşenleri) yazma; yalnızca ana çıktı cümlesini yaz. Metinde olmayan bilgi uydurma.\nYalnızca şu biçimde geçerli JSON döndür:\n{\"uniteler\":[{\"ad\":\"\",\"saat\":0,\"ciktilar\":[{\"kod\":\"\",\"ad\":\"\"}]}]}\n\nMETİN:\n" . $metin;
        $r = OSC_YZ_API::json($p, 8000, 110);
        if (!is_array($r)) wp_send_json_error(is_string($r) ? $r : 'Yapay zekâ yanıt vermedi.');
        $u = self::temizle($r); if (!$u) wp_send_json_error('Bu metinde ünite/çıktı bulunamadı.');
        wp_send_json_success(array('uniteler' => $u));
    }
    public function ajax_kaydet() {
        $this->yonetici();
        $sinif = (int) $_POST['sinif']; $ders = trim(sanitize_text_field(wp_unslash($_POST['ders'])));
        if ($sinif < 1 || $sinif > 12 || $ders === '') wp_send_json_error('Sınıf ve ders gerekli.');
        $u = self::temizle(array('uniteler' => json_decode(wp_unslash(isset($_POST['uniteler']) ? $_POST['uniteler'] : '[]'), true)));
        if (!$u) wp_send_json_error('En az bir ünite girin.');
        $k = self::kutuphane(); $k[$sinif . '|' . $ders] = array('yil' => self::takvim()['yil'], 'durum' => 'onayli', 'kaynak_ad' => sanitize_text_field(wp_unslash(isset($_POST['kaynak_ad']) ? $_POST['kaynak_ad'] : 'MEB öğretim programı')), 'tarih' => current_time('Y-m-d'), 'uniteler' => $u);
        ksort($k); self::kutuphane_yaz($k); wp_send_json_success();
    }
    public function ajax_sil() { $this->yonetici(); $k = self::kutuphane(); unset($k[sanitize_text_field(wp_unslash($_POST['anahtar']))]); self::kutuphane_yaz($k); wp_send_json_success(); }
    public function ajax_takvim() {
        $this->yonetici();
        $d = json_decode(wp_unslash(isset($_POST['takvim']) ? $_POST['takvim'] : '{}'), true); if (!is_array($d)) wp_send_json_error('Geçersiz.');
        $tar = function ($x) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $x) ? (string) $x : ''; };
        $l = function ($a) use ($tar) { $o = array(); foreach ((array) $a as $x) if (is_array($x) && $tar(isset($x['bas']) ? $x['bas'] : '')) $o[] = array('ad' => sanitize_text_field((string) (isset($x['ad']) ? $x['ad'] : '')), 'bas' => $tar($x['bas']), 'bit' => $tar(isset($x['bit']) && $x['bit'] ? $x['bit'] : $x['bas'])); return $o; };
        $t = array('yil' => sanitize_text_field((string) $d['yil']), 'baslangic' => $tar($d['baslangic']), 'bitis' => $tar($d['bitis']), 'donem2' => $tar(isset($d['donem2']) ? $d['donem2'] : ''), 'tatiller' => $l(isset($d['tatiller']) ? $d['tatiller'] : array()), 'resmi' => $l(isset($d['resmi']) ? $d['resmi'] : array()));
        if (!$t['baslangic'] || !$t['bitis']) wp_send_json_error('Başlangıç ve bitiş tarihi gerekli.');
        update_option('osc_yp_takvim', $t); wp_send_json_success();
    }
    public function menu() { add_submenu_page('osc-ogretmen-masasi', 'Yıllık Plan ve Takvim', 'Yıllık Plan ve Takvim', 'manage_options', 'osc-yplan', array($this, 'sayfa')); }
    public function sayfa() {
        $t = self::takvim(); $k = self::kutuphane(); $kat = self::katalog();
        $l = array(); foreach ($k as $a => $v) { list($s, $d) = explode('|', $a, 2); $n = 0; foreach ($v['uniteler'] as $u) $n += count($u['ciktilar']); $l[] = array('anahtar' => $a, 'sinif' => (int) $s, 'ders' => $d, 'yil' => isset($v['yil']) ? $v['yil'] : '', 'durum' => isset($v['durum']) ? $v['durum'] : 'onayli', 'kaynak_ad' => isset($v['kaynak_ad']) ? $v['kaynak_ad'] : '', 'unite' => count($v['uniteler']), 'cikti' => $n, 'uniteler' => $v['uniteler']); }
        ?>
<div class="wrap" id="yp-yon"><h1>Yıllık Plan ve Takvim</h1>
<p>Öğretmenler Evrak Hazırla’da 1–12. sınıfların tüm dersleri için yıllık plan hazırlar. Plan; buradaki <b>çalışma takvimine</b> ve <b>öğretim programı kütüphanesine</b> göre hafta hafta otomatik oluşur.</p>
<div class="yp-k"><h2>📅 Her yıl yapılacaklar</h2><ol><li>MEB yeni yılın çalışma takvimini yayımlayınca aşağıdaki takvimi güncelleyin (başlangıç, bitiş, ara tatiller, resmî tatiller).</li><li>Öğretim programı değişen dersler için programı yeniden yükleyin: MEB’in öğretim programı PDF’ini seçin, ilgili sınıfın sayfalarını yazın, “Yapay zekâ ile oku” deyin, kontrol edip kaydedin.</li><li>Kütüphanede programı olmayan derslerde, senaryo tablosu varsa o tablodaki çıktılar kullanılır; o da yoksa öğretmen üniteleri kendisi yazar (ya da üyeler yapay zekâya taslak yazdırır; bu taslaklar burada “inceleme bekliyor” olarak görünür).</li></ol></div>

<div class="yp-k"><h2>Çalışma takvimi</h2>
<p><label>Eğitim-öğretim yılı <input id="yp-yil" value="<?php echo esc_attr($t['yil']); ?>" class="small-text" style="width:100px"></label> &nbsp; <label>Ders yılı başlangıcı <input type="date" id="yp-bas" value="<?php echo esc_attr($t['baslangic']); ?>"></label> &nbsp; <label>2. dönem başlangıcı <input type="date" id="yp-d2" value="<?php echo esc_attr(isset($t['donem2']) ? $t['donem2'] : ''); ?>"></label> &nbsp; <label>Ders yılı sonu <input type="date" id="yp-bit" value="<?php echo esc_attr($t['bitis']); ?>"></label></p>
<p><b>Tatil haftaları</b> (plana ders yazılmaz) — her satıra <code>Ad | başlangıç | bitiş</code> (YYYY-AA-GG):</p>
<textarea id="yp-tatil" rows="4" class="large-text code"><?php echo esc_textarea(implode("\n", array_map(function ($x) { return $x['ad'] . ' | ' . $x['bas'] . ' | ' . $x['bit']; }, $t['tatiller']))); ?></textarea>
<p><b>Resmî tatil günleri</b> (planda ilgili haftaya not düşülür):</p>
<textarea id="yp-resmi" rows="5" class="large-text code"><?php echo esc_textarea(implode("\n", array_map(function ($x) { return $x['ad'] . ' | ' . $x['bas'] . ' | ' . $x['bit']; }, $t['resmi']))); ?></textarea>
<p><button class="button button-primary" id="yp-tk">Takvimi kaydet</button> <span id="yp-tk-d"></span></p></div>

<div class="yp-k"><h2>Öğretim programı ekle / güncelle</h2>
<p><label>Sınıf <select id="yp-s"><?php for ($i = 1; $i <= 12; $i++) echo '<option value="' . $i . '">' . $i . '. sınıf</option>'; ?></select></label> &nbsp; <label>Ders <input id="yp-d" list="yp-dl" style="width:320px" placeholder="Ders adı"></label><datalist id="yp-dl"></datalist> &nbsp; <label>Kaynak <input id="yp-ka" value="MEB öğretim programı <?php echo esc_attr($t['yil']); ?>" style="width:240px"></label></p>
<p><b>1) PDF’ten oku:</b> <input type="file" id="yp-pdf" accept="application/pdf"> sayfalar <input id="yp-sf" placeholder="ör. 40-75" style="width:110px"> <button class="button" id="yp-oku">🤖 Yapay zekâ ile oku</button> <span id="yp-oku-d"></span><br><small>MEB öğretim programı PDF’leri <a href="https://mufredat.meb.gov.tr" target="_blank" rel="noopener">mufredat.meb.gov.tr</a> ve <a href="https://tymm.meb.gov.tr" target="_blank" rel="noopener">tymm.meb.gov.tr</a> adreslerindedir. Birkaç sınıfın birlikte olduğu PDF’lerde yalnızca ilgili sınıfın sayfalarını yazın.</small></p>
<p><b>2) Kontrol edin / elle yazın</b> — <code>## Ünite adı | ders saati</code> satırı yeni ünite başlatır; altındaki her satır bir öğrenme çıktısıdır (<code>KOD | çıktı</code> ya da yalnızca çıktı):</p>
<textarea id="yp-u" rows="14" class="large-text code" placeholder="## SAYILAR VE NİCELİKLER (1) | 30&#10;MAT.5.1.1 | Doğal sayılarla ilgili ..."></textarea>
<p><button class="button button-primary" id="yp-kay">Kütüphaneye kaydet</button> <button class="button" id="yp-tem">Temizle</button> <span id="yp-kay-d"></span></p></div>

<div class="yp-k"><h2>Kütüphane (<?php echo count($l); ?> ders)</h2>
<table class="widefat striped"><thead><tr><th>Sınıf</th><th>Ders</th><th>Ünite</th><th>Çıktı</th><th>Yıl</th><th>Kaynak</th><th>Durum</th><th></th></tr></thead><tbody>
<?php if (!$l) echo '<tr><td colspan="8">Henüz program yüklenmedi. Senaryo tablosu olan derslerde (5–12. sınıf temel dersler) planlar şimdiden senaryodaki çıktılarla oluşur.</td></tr>'; foreach ($l as $i => $x) echo '<tr><td>' . $x['sinif'] . '</td><td>' . esc_html($x['ders']) . '</td><td>' . $x['unite'] . '</td><td>' . $x['cikti'] . '</td><td>' . esc_html($x['yil']) . '</td><td>' . esc_html($x['kaynak_ad']) . '</td><td>' . ($x['durum'] === 'taslak' ? '<b style="color:#b45309">İnceleme bekliyor</b>' : '✅ Onaylı') . '</td><td><a href="#" data-duz="' . $i . '">Düzenle</a> · <a href="#" data-sil="' . esc_attr($x['anahtar']) . '" style="color:#b91c1c">Sil</a></td></tr>'; ?>
</tbody></table></div>
<style>#yp-yon .yp-k{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:6px 18px 14px;margin:16px 0;max-width:1100px}</style>
<script>
(function () {
  var N = <?php echo wp_json_encode(wp_create_nonce('osc_yp_yon')); ?>, A = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, KAT = <?php echo wp_json_encode($kat, JSON_UNESCAPED_UNICODE); ?>, LIB = <?php echo wp_json_encode($l, JSON_UNESCAPED_UNICODE); ?>;
  function $(i) { return document.getElementById(i); }
  function post(a, d) { var f = new FormData(); f.append('action', 'osc_yp_' + a); f.append('nonce', N); for (var k in d) f.append(k, d[k]); return fetch(A, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (!j.success) throw new Error(j.data || 'Hata'); return j.data; }); }
  function dl() { $('yp-dl').innerHTML = [].concat.apply([], (KAT[$('yp-s').value] || []).map(function (g) { return g.d; })).map(function (d) { return '<option value="' + d.replace(/"/g, '&quot;') + '">'; }).join(''); }
  $('yp-s').onchange = dl; dl();
  function satirlar(t) { return t.split('\n').map(function (l) { var p = l.split('|').map(function (x) { return x.trim(); }); return p[0] ? { ad: p[0], bas: p[1] || '', bit: p[2] || p[1] || '' } : null; }).filter(Boolean); }
  $('yp-tk').onclick = function () { post('takvim', { takvim: JSON.stringify({ yil: $('yp-yil').value, baslangic: $('yp-bas').value, bitis: $('yp-bit').value, donem2: $('yp-d2').value, tatiller: satirlar($('yp-tatil').value), resmi: satirlar($('yp-resmi').value) }) }).then(function () { $('yp-tk-d').textContent = '✅ Kaydedildi'; }).catch(function (e) { $('yp-tk-d').textContent = '❌ ' + e.message; }); };
  function yazi(u) { return u.map(function (x) { return '## ' + x.ad + (x.saat ? ' | ' + x.saat : '') + '\n' + x.ciktilar.map(function (c) { return (c.kod ? c.kod + ' | ' : '') + c.ad; }).join('\n'); }).join('\n\n'); }
  function coz(t) { var u = [], s = null; t.split('\n').forEach(function (l) { l = l.trim(); if (!l) return; if (/^##/.test(l)) { var p = l.replace(/^#+\s*/, '').split('|'); s = { ad: p[0].trim(), saat: parseInt(p[1], 10) || 0, ciktilar: [] }; u.push(s); return; } if (!s) { s = { ad: 'Ünite', saat: 0, ciktilar: [] }; u.push(s); } var q = l.split('|'); if (q.length > 1 && /^[A-ZÇĞİÖŞÜa-z0-9.\-]{2,25}$/.test(q[0].trim())) s.ciktilar.push({ kod: q[0].trim(), ad: q.slice(1).join('|').trim() }); else s.ciktilar.push({ kod: '', ad: l }); }); return u; }
  $('yp-kay').onclick = function () { var u = coz($('yp-u').value); if (!$('yp-d').value.trim()) return alert('Ders adını yazın.'); post('kaydet', { sinif: $('yp-s').value, ders: $('yp-d').value.trim(), kaynak_ad: $('yp-ka').value, uniteler: JSON.stringify(u) }).then(function () { $('yp-kay-d').textContent = '✅ Kaydedildi (' + u.length + ' ünite). Sayfa yenileniyor…'; setTimeout(function () { location.reload(); }, 900); }).catch(function (e) { $('yp-kay-d').textContent = '❌ ' + e.message; }); };
  $('yp-tem').onclick = function () { $('yp-u').value = ''; };
  document.querySelectorAll('[data-duz]').forEach(function (a) { a.onclick = function (e) { e.preventDefault(); var x = LIB[+a.dataset.duz]; $('yp-s').value = x.sinif; dl(); $('yp-d').value = x.ders; $('yp-ka').value = x.durum === 'taslak' ? 'MEB öğretim programı (kontrol edildi)' : x.kaynak_ad; $('yp-u').value = yazi(x.uniteler); $('yp-u').scrollIntoView({ behavior: 'smooth', block: 'center' }); }; });
  document.querySelectorAll('[data-sil]').forEach(function (a) { a.onclick = function (e) { e.preventDefault(); if (!confirm('Silinsin mi?')) return; post('sil', { anahtar: a.dataset.sil }).then(function () { location.reload(); }); }; });
  var pdfLib = null;
  function pdfYukle() { if (pdfLib) return Promise.resolve(pdfLib); return new Promise(function (ok, no) { var s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js'; s.onload = function () { window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js'; pdfLib = window.pdfjsLib; ok(pdfLib); }; s.onerror = no; document.head.appendChild(s); }); }
  function araliklar(t, n) { var o = []; (t || '1-' + n).split(',').forEach(function (p) { var m = p.trim().split('-'); var a = +m[0], b = +(m[1] || m[0]); for (var i = Math.max(1, a); i <= Math.min(n, b); i++) o.push(i); }); return o; }
  $('yp-oku').onclick = function () {
    var f = $('yp-pdf').files[0], d = $('yp-oku-d'); if (!f) return alert('PDF seçin.'); if (!$('yp-d').value.trim()) return alert('Önce sınıf ve dersi seçin.');
    d.textContent = 'PDF okunuyor…';
    pdfYukle().then(function (L) { return f.arrayBuffer().then(function (b) { return L.getDocument({ data: b }).promise; }); }).then(function (pdf) {
      var sf = araliklar($('yp-sf').value, pdf.numPages), metin = [];
      return sf.reduce(function (p, i) { return p.then(function () { return pdf.getPage(i).then(function (pg) { return pg.getTextContent(); }).then(function (c) { metin.push(c.items.map(function (x) { return x.str + (x.hasEOL ? '\n' : ' '); }).join('')); }); }); }, Promise.resolve()).then(function () { return metin; });
    }).then(function (sayfalar) {
      var parca = [], cur = ''; sayfalar.forEach(function (s) { if ((cur + s).length > 45000 && cur) { parca.push(cur); cur = ''; } cur += '\n' + s; }); if (cur) parca.push(cur);
      var tum = [], i = 0;
      return parca.reduce(function (p, m) { return p.then(function () { i++; d.textContent = '🤖 Yapay zekâ okuyor… (' + i + '/' + parca.length + ')'; return post('ayikla', { sinif: $('yp-s').value, ders: $('yp-d').value.trim(), metin: m }).then(function (r) { r.uniteler.forEach(function (u) { var son = tum[tum.length - 1]; if (son && son.ad === u.ad) { son.ciktilar = son.ciktilar.concat(u.ciktilar); son.saat = son.saat || u.saat; } else tum.push(u); }); }).catch(function (e) { if (parca.length === 1) throw e; }); }); }, Promise.resolve()).then(function () { return tum; });
    }).then(function (u) { if (!u.length) throw new Error('Ünite bulunamadı.'); $('yp-u').value = yazi(u); d.textContent = '✅ ' + u.length + ' ünite okundu. Kontrol edip kaydedin.'; }).catch(function (e) { d.textContent = '❌ ' + (e.message || e); });
  };
})();
</script></div>
<?php }
}
$GLOBALS['osc_yplan'] = new OSC_YPlan();
}
