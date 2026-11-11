/* =====================================================================
 * Soru Havuzu: YAPAY ZEKÂ İLE TOPLU DOLDURMA
 *   MEB senaryo tablolarındaki her öğrenme çıktısı için, seçilen dönem ve
 *   yazılının senaryolarına göre yeterli sayıda soru üretir; isteğe bağlı
 *   ikinci bir yapay zekâ çağrısıyla cevapları doğrular. Sunucuda, arka
 *   planda çalışır (WP-Cron + kendi kendini tetikleyen istek).
 * ===================================================================== */
if (!class_exists('OSC_Havuz_Uret') && class_exists('OSC_Havuz')) {
class OSC_Havuz_Uret {
    const DURUM = 'osc_hvu_durum';
    const OLAY = 'osc_hvu_olay';
    const KILIT = 'osc_hvu_kilit';

    public function __construct() {
        add_filter('cron_schedules', function ($s) { if (!isset($s['osc_dakika'])) $s['osc_dakika'] = array('interval' => 60, 'display' => 'Her dakika (Soru Havuzu)'); return $s; });
        add_action(self::OLAY, array($this, 'cron'));
        foreach (array('hesapla', 'basla', 'durum', 'dur', 'devam', 'sifirla', 'adim', 'test') as $a) add_action('wp_ajax_osc_hv_uret_' . $a, array($this, 'ajax_' . $a));
        add_action('wp_ajax_nopriv_osc_hv_uret_tetik', array($this, 'tetik'));
        add_action('wp_ajax_osc_hv_uret_tetik', array($this, 'tetik'));
    }
    private function yetki() { if (!current_user_can('edit_others_posts')) wp_send_json_error('Yetkiniz yok.', 403); check_ajax_referer('osc_hv', 'nonce'); }
    private static function d() { $d = get_option(self::DURUM, array()); return is_array($d) ? $d : array(); }
    private static function yaz($d) { update_option(self::DURUM, $d, false); }
    private static function log(&$d, $m, $t = '') { if (!isset($d['log'])) $d['log'] = array(); array_unshift($d['log'], array('m' => $m, 't' => $t, 'z' => time())); $d['log'] = array_slice($d['log'], 0, 60); }
    private static function gizli() { $g = get_option('osc_hv_gizli'); if (!$g) { $g = wp_generate_password(32, false); update_option('osc_hv_gizli', $g, false); } return $g; }
    private static function kodu($x) { return $x['kod'] ? $x['kod'] : mb_substr($x['cikti'], 0, 38); }

    /* ---------- plan: hangi çıktıdan kaç soru eksik ---------- */
    private static function parametre($src) {
        return array(
            'donem' => in_array((int) (isset($src['donem']) ? $src['donem'] : 1), array(1, 2), true) ? (int) $src['donem'] : 1,
            'yazili' => in_array((string) (isset($src['yazili']) ? $src['yazili'] : 'hepsi'), array('1', '2'), true) ? (string) $src['yazili'] : 'hepsi',
            'sinif' => (int) (isset($src['sinif']) ? $src['sinif'] : 0),
            'ders' => sanitize_text_field(isset($src['ders']) ? $src['ders'] : ''),
            'carpan' => max(2, min(10, (int) (isset($src['carpan']) ? $src['carpan'] : 4))),
            'en_az' => max(2, min(30, (int) (isset($src['en_az']) ? $src['en_az'] : 6))),
            'test' => max(0, min(60, (int) (isset($src['test']) ? $src['test'] : 25))),
            'dogrula' => !empty($src['dogrula']) ? 1 : 0,
            'butce' => max(0.2, min(500, (float) (isset($src['butce']) ? $src['butce'] : 5))),
        );
    }
    private static function plan($p) {
        global $wpdb; $tb = OSC_Havuz::tablo();
        $var = array();
        foreach ((array) $wpdb->get_results("SELECT sinif, ders, cikti_kod, tur, COUNT(*) n FROM $tb WHERE durum = 'onayli' AND cikti_kod <> '' GROUP BY sinif, ders, cikti_kod, tur") as $r) {
            $k = $r->sinif . '|' . $r->ders . '|' . $r->cikti_kod;
            if (!isset($var[$k])) $var[$k] = array('acik' => 0, 'test' => 0);
            $var[$k][$r->tur === 'multiple' ? 'test' : 'acik'] += (int) $r->n;
        }
        $liste = array(); $top = array('cikti' => 0, 'hedef' => 0, 'mevcut' => 0, 'acik' => 0, 'test' => 0, 'ders' => 0);
        $dersler = array();
        foreach (OSC_Havuz::senaryolar()['tablolar'] as $t) {
            if ((int) $t['donem'] !== $p['donem']) continue;
            if ($p['sinif'] && (int) $t['sinif'] !== $p['sinif']) continue;
            if ($p['ders'] !== '' && $t['ders'] !== $p['ders']) continue;
            $yazililar = $p['yazili'] === 'hepsi' ? array('1', '2') : array($p['yazili']);
            foreach ($t['satirlar'] as $x) {
                $maxn = 0; $ilk = 0;
                foreach ($yazililar as $y) foreach ((array) (isset($x['sayi'][$y]) ? $x['sayi'][$y] : array()) as $v) { if ((int) $v > $maxn) $maxn = (int) $v; if (!$ilk && (int) $v > 0) $ilk = (int) $y; }
                if (!$maxn) continue;
                $kod = self::kodu($x); $hedef = max($p['en_az'], $maxn * $p['carpan']);
                $ht = (int) round($hedef * $p['test'] / 100); $ha = $hedef - $ht;
                $m = isset($var[$t['sinif'] . '|' . $t['ders'] . '|' . $kod]) ? $var[$t['sinif'] . '|' . $t['ders'] . '|' . $kod] : array('acik' => 0, 'test' => 0);
                $ea = max(0, $ha - $m['acik']); $et = max(0, $ht - $m['test']);
                $top['cikti']++; $top['hedef'] += $hedef; $top['mevcut'] += min($hedef, $m['acik'] + $m['test']);
                if ($ea + $et === 0) continue;
                $top['acik'] += $ea; $top['test'] += $et; $dersler[$t['sinif'] . '|' . $t['ders']] = 1;
                $liste[] = array('sinif' => (int) $t['sinif'], 'ders' => $t['ders'], 'kod' => $kod, 'cikti' => mb_substr($x['cikti'], 0, 90), 'yazili' => $ilk ? $ilk : 1, 'acik' => $ea, 'test' => $et, 'ha' => $ha, 'ht' => $ht, 'deneme' => 0, 'hata' => 0);
            }
        }
        $top['ders'] = count($dersler);
        // tahmini maliyet: soru başına ~600 giriş + ~570 çıktı tokenı; doğrulama ~500 + ~120
        $s = OSC_YZ_API::saglayicilar(); $pr = 'claude'; foreach ($s as $x) if ($x !== 'gemini_ucretsiz') { $pr = $x; break; }
        $model = $pr === 'claude' ? (get_option('osc_claude_model', '') ?: 'claude-haiku-5-5') : ($pr === 'openai' ? (get_option('osc_openai_model', '') ?: 'gpt-5-mini') : 'gemini-flash-lite-latest');
        $f = OSC_YZ_API::fiyat($pr, $model); $n = $top['acik'] + $top['test'];
        $top['tahmin'] = round($n * ((600 * $f[0] + 570 * $f[1]) + ($p['dogrula'] ? (500 * $f[0] + 120 * $f[1]) : 0)) / 1000000 * 1.15, 2);
        $top['saglayici'] = $pr; $top['model'] = $model;
        return array('liste' => $liste, 'ozet' => $top);
    }

    /* ---------- AJAX ---------- */
    public function ajax_hesapla() {
        $this->yetki();
        if (!OSC_YZ_API::var_mi()) wp_send_json_error('Yapay zekâ anahtarı tanımlı değil (Online Testler → Yazılı Hazırla ayarları).');
        $p = self::parametre($_POST); $pl = self::plan($p);
        wp_send_json_success(array('ozet' => $pl['ozet'], 'ornek' => array_slice($pl['liste'], 0, 8)));
    }
    public function ajax_basla() {
        $this->yetki();
        if (!OSC_YZ_API::var_mi()) wp_send_json_error('Yapay zekâ anahtarı tanımlı değil.');
        $p = self::parametre($_POST); $pl = self::plan($p);
        if (!$pl['liste']) wp_send_json_error('Bu seçimde eksik soru yok: havuz zaten hedefe ulaşmış.');
        $d = array('aktif' => 1, 'p' => $p, 'kuyruk' => $pl['liste'], 'ozet' => $pl['ozet'], 'toplam' => $pl['ozet']['acik'] + $pl['ozet']['test'], 'uretilen' => 0, 'onayli' => 0, 'reddedilen' => 0, 'harcama' => 0.0, 'istek' => 0, 'hata_ust' => 0, 'basladi' => time(), 'son' => time(), 'log' => array(), 'mesaj' => '');
        self::log($d, '▶ Başladı: ' . $pl['ozet']['ders'] . ' ders, ' . count($pl['liste']) . ' çıktı, ' . $d['toplam'] . ' soru üretilecek (bütçe ' . $p['butce'] . ' $).', 'ok');
        self::yaz($d); self::planla();
        wp_send_json_success(self::ozet_ver());
    }
    public function ajax_durum() { $this->yetki(); $d = self::d(); if (!empty($d['aktif']) && time() - (int) @$d['son'] > 90) self::planla(); wp_send_json_success(self::ozet_ver()); }
    public function ajax_dur() { $this->yetki(); $d = self::d(); $d['aktif'] = 0; self::log($d, '■ Durduruldu.', 'uyari'); self::yaz($d); wp_send_json_success(self::ozet_ver()); }
    public function ajax_devam() {
        $this->yetki(); $d = self::d(); if (empty($d['kuyruk'])) wp_send_json_error('Devam edilecek iş yok.');
        if (isset($_POST['butce'])) $d['p']['butce'] = max(0.2, min(500, (float) $_POST['butce']));
        $d['aktif'] = 1; $d['hata_ust'] = 0; $d['mesaj'] = ''; $d['bekle'] = 0; foreach ($d['kuyruk'] as &$k) $k['hata'] = 0; unset($k); foreach (OSC_YZ_API::tanimli() as $s) delete_transient('osc_yz_bitti_' . $s); self::log($d, '▶ Devam ediyor.', 'ok'); self::yaz($d); self::planla();
        wp_send_json_success(self::ozet_ver());
    }
    public function ajax_sifirla() { $this->yetki(); delete_option(self::DURUM); wp_send_json_success(self::ozet_ver()); }
    private static function ozet_ver() {
        $d = self::d(); if (!$d) return array('var' => 0);
        $kalan = 0; foreach ((array) @$d['kuyruk'] as $x) $kalan += $x['acik'] + $x['test'];
        return array('var' => 1, 'aktif' => (int) @$d['aktif'], 'p' => @$d['p'], 'toplam' => (int) @$d['toplam'], 'kalan' => $kalan, 'uretilen' => (int) @$d['uretilen'], 'onayli' => (int) @$d['onayli'], 'reddedilen' => (int) @$d['reddedilen'], 'harcama' => round((float) @$d['harcama'], 3), 'istek' => (int) @$d['istek'], 'mesaj' => (string) @$d['mesaj'], 'son_hata' => (string) @$d['son_hata'], 'bekle' => max(0, (int) @$d['bekle'] - time()), 'log' => array_slice((array) @$d['log'], 0, 25), 'simdi' => isset($d['kuyruk'][0]) ? $d['kuyruk'][0] : null);
    }

    /* ---------- çalıştırma ---------- */
    public static function planla() {
        if (!wp_next_scheduled(self::OLAY)) wp_schedule_event(time() + 30, 'osc_dakika', self::OLAY);
        self::tetikle();
    }
    public static function tetikle() { wp_remote_post(admin_url('admin-ajax.php'), array('blocking' => false, 'timeout' => 0.5, 'sslverify' => false, 'body' => array('action' => 'osc_hv_uret_tetik', 'g' => self::gizli()))); }
    public function tetik() {
        if (!hash_equals(self::gizli(), (string) (isset($_POST['g']) ? $_POST['g'] : ''))) { status_header(403); exit; }
        osc_uzun_islem(120);
        $r = 'hata'; try { $r = $this->adim(); } catch (\Throwable $e) { delete_transient(self::KILIT); }
        $d = self::d(); if (!empty($d['aktif']) && $r === 'ok') self::tetikle();   // bekleme/meşgulde dakikalık cron devam ettirir
        exit;
    }
    public function cron() {
        $d = self::d(); if (empty($d['aktif'])) { wp_clear_scheduled_hook(self::OLAY); return; }
        try { $this->adim(); } catch (\Throwable $e) { delete_transient(self::KILIT); }
    }
    /** arka planda: süre dolana kadar art arda adım */
    public function adim() {
        $bas = microtime(true);
        $r = 'pasif'; while (microtime(true) - $bas < 35) { $r = $this->adim_bir(); if ($r !== 'ok') break; }
        return $r;
    }
    /** sayfa açıkken tarayıcı da adım attırır (hosting arka plan isteklerini engellese bile çalışır) */
    public function ajax_adim() {
        $this->yetki(); osc_uzun_islem(120); OSC_YZ_API::$genis = true;
        $r = 'pasif'; try { $r = $this->adim_bir(); } catch (\Throwable $e) { $r = 'hata'; }
        $o = self::ozet_ver(); $o['adim'] = $r; wp_send_json_success($o);
    }
    private static function anahtar($x) { return 'osc_hvu_k_' . md5($x['sinif'] . '|' . $x['ders'] . '|' . $x['kod']); }
    private static function mevcut($x) {
        global $wpdb; $tb = OSC_Havuz::tablo(); $o = array('acik' => 0, 'test' => 0);
        foreach ((array) $wpdb->get_results($wpdb->prepare("SELECT tur, COUNT(*) n FROM $tb WHERE sinif = %d AND ders = %s AND cikti_kod = %s AND durum = 'onayli' GROUP BY tur", $x['sinif'], $x['ders'], $x['kod'])) as $r) $o[$r->tur === 'multiple' ? 'test' : 'acik'] += (int) $r->n;
        return $o;
    }
    private static function guncelle($fn) { $d = self::d(); if (!$d) return; $fn($d); self::yaz($d); }
    /** tek bir çıktı için bir parti (en çok 3 soru) üret; aynı anda birkaç işçi farklı çıktılarda çalışabilir */
    public function adim_bir() {
        $d = self::d();
        if (empty($d['aktif'])) return 'pasif';
        if (!empty($d['bekle']) && $d['bekle'] > time()) return 'bekle';
        if (empty($d['kuyruk'])) { self::guncelle(function (&$d) { if (!empty($d['aktif'])) { $d['aktif'] = 0; $d['mesaj'] = 'Tamamlandı.'; OSC_Havuz_Uret::logla($d, '✓ Tamamlandı: ' . $d['uretilen'] . ' soru üretildi, ' . $d['onayli'] . ' onaylandı.', 'ok'); } }); return 'bitti'; }
        if ((float) $d['harcama'] >= (float) $d['p']['butce']) { self::guncelle(function (&$d) { $d['aktif'] = 0; $d['mesaj'] = 'Bütçe sınırına ulaşıldı.'; OSC_Havuz_Uret::logla($d, '■ Bütçe sınırına ulaşıldı (' . round($d['harcama'], 2) . ' $). Bütçeyi artırıp devam edebilirsiniz.', 'uyari'); }); return 'butce'; }
        $x = null;
        foreach ($d['kuyruk'] as $k) { if (!get_transient(self::anahtar($k))) { $x = $k; break; } }
        if (!$x) return 'mesgul';
        set_transient(self::anahtar($x), 1, 240);
        $key = self::anahtar($x);
        $m = self::mevcut($x);
        $ha = isset($x['ha']) ? $x['ha'] : $x['acik'] + $m['acik']; $ht = isset($x['ht']) ? $x['ht'] : $x['test'] + $m['test'];
        $na = max(0, $ha - $m['acik']); $nt = max(0, $ht - $m['test']);
        $sinir = (int) ceil(($ha + $ht) / 3) + 4;
        if ((int) @$x['hata'] >= 3 && $na + $nt > 0 && (int) @$x['deneme'] < $sinir) {   // bu çıktıda üst üste hata: sona al, sonra yeniden dene
            self::guncelle(function (&$d) use ($key) { $son = null; foreach ($d['kuyruk'] as $i => $k) if (OSC_Havuz_Uret::anahtar_dis($k) === $key) { $son = $k; unset($d['kuyruk'][$i]); } if ($son) { $son['hata'] = 0; $d['kuyruk'][] = $son; } $d['kuyruk'] = array_values($d['kuyruk']); });
            delete_transient($key); return 'ok';
        }
        if ($na + $nt <= 0 || (int) @$x['deneme'] >= $sinir) {
            self::guncelle(function (&$d) use ($key, $x, $na, $nt) {
                $d['kuyruk'] = array_values(array_filter($d['kuyruk'], function ($k) use ($key) { return OSC_Havuz_Uret::anahtar_dis($k) !== $key; }));
                OSC_Havuz_Uret::logla($d, ($na + $nt <= 0 ? '✓ ' : '↷ atlandı: ') . $x['sinif'] . '. sınıf ' . $x['ders'] . ' · ' . $x['kod'], $na + $nt <= 0 ? '' : 'uyari');
            });
            delete_transient($key); return 'ok';
        }
        $tur = $na > 0 ? 'acik' : 'test'; $n = min(3, $tur === 'acik' ? $na : $nt);
        $t = OSC_Havuz::tablo_bul($x['sinif'], $x['ders'], $d['p']['donem']);
        if (!$t || empty($GLOBALS['osc_yazili'])) { self::guncelle(function (&$d) use ($key) { $d['kuyruk'] = array_values(array_filter($d['kuyruk'], function ($k) use ($key) { return OSC_Havuz_Uret::anahtar_dis($k) !== $key; })); }); delete_transient($key); return 'ok'; }
        $once = OSC_YZ_API::$harcama; OSC_YZ_API::$son_hatalar = array();
        $r = $GLOBALS['osc_yazili']->toplu_uret($t, $x['kod'], $n, $tur, $d['p']['donem'], $x['yazili'], $d['p']['dogrula'] ? 'inceleme' : 'onayli');
        $uret = is_array($r) && isset($r[$x['kod']]) ? $r[$x['kod']] : array();
        $v = array('ok' => 0, 'red' => 0);
        if ($uret) { if ($d['p']['dogrula']) $v = self::dogrula($uret); else $v['ok'] = count($uret); }
        $harca = OSC_YZ_API::$harcama - $once; $hatalar = OSC_YZ_API::$son_hatalar;
        self::guncelle(function (&$d) use ($key, $x, $r, $uret, $v, $harca, $hatalar, $tur, $n, $na, $nt) {
            $d['istek']++; $d['harcama'] += $harca; $d['son'] = time();
            foreach ($d['kuyruk'] as &$k) if (OSC_Havuz_Uret::anahtar_dis($k) === $key) {
                $k['deneme'] = (int) @$k['deneme'] + 1;
                if (!$uret) $k['hata'] = (int) @$k['hata'] + 1; else $k['hata'] = 0;
                $k['acik'] = $tur === 'acik' ? max(0, $na - $v['ok']) : $na; $k['test'] = $tur === 'test' ? max(0, $nt - $v['ok']) : $nt;
            }
            unset($k);
            if (!$uret) {
                $d['hata_ust'] = (int) @$d['hata_ust'] + 1;
                $msg = is_string($r) ? $r : 'soru üretilemedi';
                $ek = array(); foreach ($hatalar as $s => $h) $ek[] = $s . ': ' . $h;
                OSC_Havuz_Uret::logla($d, '⚠ ' . $x['sinif'] . '. sınıf ' . $x['ders'] . ' · ' . $x['kod'] . ' — ' . $msg . ($ek ? ' [' . implode(' | ', array_unique($ek)) . ']' : ''), 'uyari');
                $d['son_hata'] = $msg . ($ek ? ' [' . implode(' | ', array_unique($ek)) . ']' : '');
                $bitti = OSC_YZ_API::tanimli(); $hepsi = $bitti ? true : false; foreach ($bitti as $s) if (!get_transient('osc_yz_bitti_' . $s)) $hepsi = false;
                if ($hepsi) { $d['aktif'] = 0; $d['mesaj'] = 'Tüm yapay zekâ hesaplarının kredisi bitmiş görünüyor. Kredi yükleyip “Devam et” deyin.'; OSC_Havuz_Uret::logla($d, '■ ' . $d['mesaj'], 'hata'); }
                elseif ($d['hata_ust'] >= 8) { $d['bekle'] = time() + 120; $d['hata_ust'] = 0; $d['mesaj'] = 'Üst üste hata alındı, 2 dakika beklenip otomatik devam edilecek. Son hata: ' . $d['son_hata']; OSC_Havuz_Uret::logla($d, '⏸ 8 hata üst üste — 2 dk bekleniyor', 'uyari'); }
            } else {
                $d['hata_ust'] = 0; $d['mesaj'] = ''; $d['uretilen'] += count($uret); $d['onayli'] += $v['ok']; $d['reddedilen'] += $v['red'];
            }
        });
        delete_transient($key);
        return 'ok';
    }
    public static function anahtar_dis($x) { return self::anahtar($x); }
    public static function logla(&$d, $m, $t = '') { self::log($d, $m, $t); }
    /** her sağlayıcıyı küçük bir istekle dene */
    public function ajax_test() {
        $this->yetki(); $o = array();
        foreach (OSC_YZ_API::tanimli() as $s) {
            $b = microtime(true); OSC_YZ_API::$sicaklik = 0;
            $r = OSC_YZ_API::tek($s, 'Yalnızca şu JSON nesnesini döndür: {"tamam": true}', 300, 40);
            $o[] = array('s' => $s, 'ok' => !is_wp_error($r) && stripos((string) $r, 'tamam') !== false, 'sure' => round(microtime(true) - $b, 1), 'mesaj' => is_wp_error($r) ? $r->get_error_message() : mb_substr((string) $r, 0, 120), 'bitti' => get_transient('osc_yz_bitti_' . $s) ? 1 : 0);
            if (!is_wp_error($r)) delete_transient('osc_yz_bitti_' . $s);
        }
        wp_send_json_success($o);
    }
    /** ikinci yapay zekâ çağrısı: soru + beklenen cevap doğru mu? */
    private static function dogrula($uret) {
        global $wpdb; $tb = OSC_Havuz::tablo();
        $metin = array(); $map = array();
        foreach ($uret as $q) {
            $s = $q['soru']; $id = (int) $q['id']; $map[$id] = $s;
            $tx = function ($h) { return trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags(str_replace(array('<br>', '</tr>', '</td>'), array("\n", "\n", ' | '), (string) $h)), ENT_QUOTES, 'UTF-8'))); };
            $m = "### id=$id\n" . ($s['paragraph'] ? 'Öncül: ' . $tx($s['paragraph']) . "\n" : '') . 'Soru: ' . $tx($s['root']) . "\n";
            if ($s['type'] === 'multiple') { foreach ((array) $s['options'] as $k => $v) if (trim(wp_strip_all_tags($v)) !== '') $m .= $k . ') ' . $tx($v) . "\n"; $m .= 'Verilen doğru şık: ' . $s['correct'] . "\n"; }
            else $m .= 'Beklenen cevap: ' . $tx(isset($s['options']['A']) ? $s['options']['A'] : '') . "\n";
            $metin[] = $m;
        }
        $prompt = "Sen deneyimli bir ölçme-değerlendirme uzmanı ve konu alanı öğretmenisin. Aşağıdaki yazılı sınav sorularını tek tek çöz ve kontrol et.\nHer soru için: (1) soru açık, sınıf düzeyine uygun ve tek/kesin cevaplı mı, (2) verilen cevap ya da doğru şık gerçekten doğru mu, (3) hesaplamalar doğru mu?\nKüçük üslup kusurlarını sorun sayma; yalnızca yanlış cevap, hatalı hesap, birden çok doğru şık, çözülemeyen ya da bilimsel hata içeren soruları 'dogru': false yap.\n\n" . implode("\n", $metin) . "\nYalnızca JSON döndür: {\"sonuc\":[{\"id\":0,\"dogru\":true,\"not\":\"kısa gerekçe (yalnızca yanlışsa)\"}]}";
        OSC_YZ_API::$sicaklik = 0;
        $r = OSC_YZ_API::json($prompt, 1200, 45);
        $ok = 0; $red = 0; $sonuc = array();
        if (is_array($r)) { if (isset($r['sonuc'])) $r = $r['sonuc']; foreach ((array) $r as $x) if (is_array($x) && isset($x['id'])) $sonuc[(int) $x['id']] = $x; }
        foreach ($map as $id => $s) {
            $x = isset($sonuc[$id]) ? $sonuc[$id] : null;
            if ($x && !empty($x['dogru']) && $x['dogru'] !== 'false') { $wpdb->update($tb, array('durum' => 'onayli'), array('id' => $id)); $ok++; }
            elseif ($x) { $s['dogrulama_notu'] = mb_substr(sanitize_text_field(isset($x['not']) ? $x['not'] : 'yapay zekâ doğrulamasından geçemedi'), 0, 300); $wpdb->update($tb, array('durum' => 'inceleme', 'soru' => wp_json_encode($s)), array('id' => $id)); $red++; }
            else { $wpdb->update($tb, array('durum' => 'onayli'), array('id' => $id)); $ok++; }   // doğrulama yanıtı alınamadı: üretimdeki öz-kontrole güvenilir
        }
        return array('ok' => $ok, 'red' => $red);
    }
}
new OSC_Havuz_Uret();
}
