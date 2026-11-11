/* =====================================================================
 * V23.2 — Soru Havuzu: SUNUCUDA arka plan aktarımı
 *   Tarayıcı açık kalmasına gerek yok. Zip / Word (.docx) / .txt dosyaları
 *   sunucuda açılır, sorular ayıklanır, MEB öğrenme çıktılarıyla eşleştirilir.
 *   Kuyruk; WP-Cron + kendi kendini tetikleyen istekle işlenir.
 * ===================================================================== */
if (!class_exists('OSC_Havuz_Arka') && class_exists('OSC_Havuz')) {
class OSC_Havuz_Arka {
    const KUYRUK = 'osc_hv_kuyruk';
    const DURUM = 'osc_hv_durum';
    const KILIT = 'osc_hv_kilit';
    const OLAY = 'osc_hv_arka_olay';
    const SURE = 20; // tek adımda en fazla kaç saniye çalışılsın

    public function __construct() {
        add_filter('cron_schedules', function ($s) { $s['osc_dakika'] = array('interval' => 60, 'display' => 'Her dakika (Soru Havuzu)'); return $s; });
        add_action(self::OLAY, array($this, 'cron'));
        foreach (array('arka_basla', 'arka_durum', 'arka_dur', 'cevap_basla', 'cevap_durum', 'cevap_dur') as $a) add_action('wp_ajax_osc_hv_' . $a, array($this, 'ajax_' . $a));
        add_action('wp_ajax_nopriv_osc_hv_arka_tetik', array($this, 'tetik'));
        add_action('wp_ajax_osc_hv_arka_tetik', array($this, 'tetik'));
        add_action('transition_post_status', array($this, 'yeni_yazi'), 30, 3);
    }

    /* ---------------- kuyruk yönetimi ---------------- */
    private static function durum() { $d = get_option(self::DURUM, array()); return is_array($d) ? $d : array(); }
    private static function durum_yaz($d) { update_option(self::DURUM, $d, false); }
    private static function kuyruk() { $k = get_option(self::KUYRUK, array()); return is_array($k) ? $k : array(); }
    private static function log(&$d, $m, $tur = '') { if (!isset($d['log'])) $d['log'] = array(); array_unshift($d['log'], array('m' => $m, 't' => $tur, 'z' => time())); $d['log'] = array_slice($d['log'], 0, 80); }
    private static function gizli() { $g = get_option('osc_hv_gizli'); if (!$g) { $g = wp_generate_password(32, false); update_option('osc_hv_gizli', $g, false); } return $g; }
    private function yetki() { if (!current_user_can('edit_others_posts')) wp_send_json_error('Yetkiniz yok.', 403); check_ajax_referer('osc_hv', 'nonce'); }

    public static function kuyruga_ekle($liste, $yeniden = false) {
        $isl = get_option('osc_hv_islenen', array()); if (!is_array($isl)) $isl = array();
        $k = self::kuyruk(); $var = array(); foreach ($k as $x) $var[$x['k']] = 1;
        $n = 0;
        foreach ($liste as $x) {
            if (isset($var[$x['k']])) continue;
            if (!$yeniden && isset($isl[$x['k']]) && empty($isl[$x['k']]['gecici'])) continue;
            $k[] = array('k' => $x['k'], 'url' => $x['url'], 'baslik' => $x['baslik'], 'kategori' => isset($x['kategori']) ? $x['kategori'] : '', 'etiket' => isset($x['etiket']) && is_array($x['etiket']) ? $x['etiket'] : array(), 'kaynak' => isset($x['kaynak']) ? $x['kaynak'] : 'arsiv', 'deneme' => 0);
            $var[$x['k']] = 1; $n++;
        }
        update_option(self::KUYRUK, $k, false);
        return $n;
    }
    public static function planla() {
        if (!wp_next_scheduled(self::OLAY)) wp_schedule_event(time() + 30, 'osc_dakika', self::OLAY);
        self::tetikle();
    }
    /** Kendi kendine, arka planda (yanıt beklemeden) bir sonraki adımı başlat */
    public static function tetikle() {
        wp_remote_post(admin_url('admin-ajax.php'), array('blocking' => false, 'timeout' => 0.5, 'sslverify' => false, 'body' => array('action' => 'osc_hv_arka_tetik', 'g' => self::gizli())));
    }
    public function tetik() {
        if (!hash_equals(self::gizli(), (string) (isset($_POST['g']) ? $_POST['g'] : ''))) { status_header(403); exit; }
        osc_uzun_islem(120);
        try { if (self::kuyruk()) $this->adim(); elseif (self::cevap_aktif()) $this->cevap_adim(); } catch (\Throwable $e) { delete_transient(self::KILIT); delete_transient('osc_hv_ckilit'); }
        if ((self::kuyruk() && !get_option('osc_hv_durdur')) || self::cevap_aktif()) self::tetikle();
        exit;
    }
    public function cron() {
        if (!self::kuyruk() && !self::cevap_aktif()) { wp_clear_scheduled_hook(self::OLAY); return; }
        try { if (self::kuyruk()) $this->adim(); else $this->cevap_adim(); } catch (\Throwable $e) { delete_transient(self::KILIT); delete_transient('osc_hv_ckilit'); }
    }

    /* =============== cevap anahtarı olmayan sorulara yapay zekâ ile cevap =============== */
    const CDURUM = 'osc_hv_cevap_durum';
    private static function cdurum() { $d = get_option(self::CDURUM, array()); return is_array($d) ? $d : array(); }
    private static function cevap_aktif() { $d = self::cdurum(); return !empty($d['aktif']) && (empty($d['bekle']) || $d['bekle'] < time() + 3600); }
    private static function cevapsiz_sql($say = false) {
        $t = OSC_Havuz::tablo();
        // cevabı eksik ya da zorluğu belirlenmemiş, daha önce yapay zekâya gönderilmemiş sorular
        $kos = "durum <> 'eslesmedi' AND soru NOT LIKE '%\"cevap_denendi\"%' AND (soru NOT LIKE '%\"zorluk\"%' OR (tur = 'multiple' AND soru LIKE '%\"correct\":\"\"%') OR (tur IN ('classic','gap') AND (soru LIKE '%\"options\":{\"A\":\"\"}%' OR soru LIKE '%\"options\":[]%')))";
        if (!$say) $kos = str_replace('%', '%%', $kos);
        return $say ? "SELECT COUNT(*) FROM $t WHERE $kos" : "SELECT id, sinif, ders, cikti_kod, tur, soru FROM $t WHERE $kos ORDER BY (durum = 'onayli') DESC, id ASC LIMIT %d";
    }
    public static function cevapsiz_sayi() { global $wpdb; return (int) $wpdb->get_var(self::cevapsiz_sql(true)); }
    public function ajax_cevap_basla() {
        $this->yetki();
        if (!OSC_YZ_API::var_mi()) wp_send_json_error('Önce Online Testler → Yazılı Hazırla sayfasından bir yapay zekâ anahtarı (Claude ya da Gemini) girin.');
        $n = self::cevapsiz_sayi();
        $d = array('aktif' => $n ? 1 : 0, 'basladi' => time(), 'toplam' => $n, 'yapilan' => 0, 'cevap' => 0, 'atlanan' => 0, 'istek' => 0, 'log' => array(), 'saglayici' => OSC_YZ_API::saglayici());
        self::log($d, $n ? '🔑 ' . $n . ' sorunun cevap anahtarı yapay zekâyla tamamlanacak (' . ucfirst($d['saglayici']) . ').' : '✓ Cevabı eksik soru yok.', $n ? '' : 'ok');
        update_option(self::CDURUM, $d, false);
        if ($n) { if (!wp_next_scheduled(self::OLAY)) wp_schedule_event(time() + 30, 'osc_dakika', self::OLAY); self::tetikle(); }
        wp_send_json_success(self::cevap_ozet());
    }
    public function ajax_cevap_durum() {
        $this->yetki();
        $d = self::cdurum();
        if (self::cevap_aktif() && !self::kuyruk() && (!isset($d['son']) || time() - $d['son'] > 25) && !get_transient('osc_hv_ckilit')) { $this->cevap_adim(); if (self::cevap_aktif()) self::tetikle(); }
        wp_send_json_success(self::cevap_ozet());
    }
    public function ajax_cevap_dur() {
        $this->yetki(); $d = self::cdurum(); $d['aktif'] = 0; self::log($d, '■ Durduruldu.', 'uyari'); update_option(self::CDURUM, $d, false);
        wp_send_json_success(self::cevap_ozet());
    }
    public static function cevap_ozet() { $d = self::cdurum(); $d['kalan'] = self::cevapsiz_sayi(); $d['calisiyor'] = !empty($d['aktif']); return $d; }
    public function cevap_adim() {
        if (get_transient('osc_hv_ckilit')) return;
        set_transient('osc_hv_ckilit', 1, 90); osc_uzun_islem(120);
        global $wpdb; $t = OSC_Havuz::tablo(); $bas = microtime(true);
        $d = self::cdurum(); $d['son'] = time();
        if (!empty($d['bekle']) && $d['bekle'] > time()) { update_option(self::CDURUM, $d, false); delete_transient('osc_hv_ckilit'); return; }
        while (!empty($d['aktif']) && microtime(true) - $bas < 25) {
            $rows = $wpdb->get_results($wpdb->prepare(self::cevapsiz_sql(), 12));
            if (!$rows) { $d['aktif'] = 0; $d['bitti'] = time(); self::log($d, '■ Bitti: ' . $d['cevap'] . ' soruya cevap eklendi.', 'ok'); break; }
            // görselsiz sorular 12'şer, görselli sorular (görseller de gönderilir) 4'er
            $metinli = array(); $gorselli = array();
            foreach ($rows as $r) { if (strpos($r->soru, '<img') !== false) $gorselli[] = $r; else $metinli[] = $r; }
            $grup = $metinli ? $metinli : array_slice($gorselli, 0, 4);
            $liste = array(); $harita = array(); $gorseller = array();
            foreach ($grup as $r) {
                $q = json_decode($r->soru, true); if (!is_array($q)) continue;
                $q += array('paragraph' => '', 'root' => '', 'options' => array(), 'correct' => '');
                $tb = self::tablo_bul((int) $r->sinif, $r->ders); $x = null;
                if ($tb) foreach ($tb['satirlar'] as $sr) if (self::kodu($sr) === $r->cikti_kod) { $x = $sr; break; }
                $cevap_var = $r->tur === 'multiple' ? $q['correct'] !== '' : (isset($q['options']['A']) && trim(wp_strip_all_tags((string) $q['options']['A'])) !== '');
                $no = 0; $etiketle = function ($h) use (&$gorseller, &$no, $r) {
                    return preg_replace_callback('#<img[^>]+src="([^"]+)"[^>]*>#i', function ($m) use (&$gorseller, &$no, $r) {
                        $no++; $g = $no <= 3 ? $this->gorsel_oku($m[1]) : null;
                        if (!$g) return ' [görsel] ';
                        $et = 'Görsel ' . $no . ' (soru id=' . $r->id . ')'; $g['etiket'] = $et; $gorseller[] = $g; return ' [' . $et . '] ';
                    }, (string) $h);
                };
                $metin = trim(html_entity_decode(wp_strip_all_tags($etiketle(str_replace(array('<br>', '<br/>', '<br />'), "\n", ($q['paragraph'] ? $q['paragraph'] . "\n" : '') . $q['root']))), ENT_QUOTES, 'UTF-8'));
                $sat = '### id=' . $r->id . ' | ' . ($r->sinif ? $r->sinif . '. sınıf ' : '') . $r->ders . ($x ? ' | çıktı: ' . $x['cikti'] : '') . "\n" . mb_substr($metin, 0, 1500);
                if ($r->tur === 'multiple') { foreach ((array) $q['options'] as $h => $v) if (trim(wp_strip_all_tags((string) $v)) !== '' || strpos((string) $v, '<img') !== false) $sat .= "\n" . $h . ') ' . trim(html_entity_decode(wp_strip_all_tags($etiketle((string) $v)), ENT_QUOTES, 'UTF-8')); }
                $sat .= "\n(" . ($cevap_var ? 'cevabı zaten var: yalnızca zorluk belirle' : ($r->tur === 'multiple' ? 'tür: çoktan seçmeli → dogru alanına doğru şıkkın harfi' : ($r->tur === 'gap' ? 'tür: boşluk doldurma → boşluğa gelecek ifade' : 'tür: açık uçlu → kısa, doğru, puanlamaya uygun örnek cevap'))) . ')';
                $liste[] = $sat; $harita[(int) $r->id] = array('q' => $q, 'tur' => $r->tur, 'var' => $cevap_var);
            }
            $prompt = "Sen Türkiye'de MEB müfredatını çok iyi bilen deneyimli bir öğretmensin. Aşağıdaki okul yazılı sınavı sorularının cevap anahtarını hazırla ve her sorunun zorluğunu belirle." . ($gorseller ? ' Bazı soruların görselleri ekte, etiketleriyle verilmiştir; görselleri dikkatle incele.' : '') . "\n\nKurallar:\n- Çoktan seçmelide 'dogru' alanına yalnızca harf yaz (A-E).\n- Açık uçlu ve boşluk doldurmada 'cevap' alanına öğrencinin yazması beklenen kısa ve doğru cevabı yaz (en fazla 2-3 cümle; hesaplama sorularında sonucu ve kısa çözümü ver). Çizim/grafik gerekiyorsa ne çizileceğini kısaca tarif et.\n- Matematiksel ifadeleri (kesir, kök, üs, çarpma, eşitsizlik vb.) LaTeX ile ve dolar işaretleri arasında yaz: $\\frac{3}{4}$, $\\sqrt[3]{54}$, $2^{5}$, $3 \\times 4$, \$x \\le 5\$. JSON içinde her ters eğik çizgiyi çift yaz (\\\\frac).\n- 'zorluk': 1 = kolay (bilgi/hatırlama), 2 = orta (uygulama), 3 = zor (analiz, çok adımlı, yorum). Sınıf düzeyine göre değerlendir.\n- Sorudan emin değilsen, soru '[görsel]' ya da '[formül/görsel okunamadı]' yüzünden eksikse 'cozulemez': true yaz ve cevap uydurma (zorluk yine de yaz).\n- Türkçe dil bilgisine uy; İngilizce dersinde cevaplar İngilizce olabilir.\n\nSorular:\n" . implode("\n\n", $liste) . "\n\nYalnızca şu biçimde JSON dizisi döndür: [{\"id\":123,\"dogru\":\"\",\"cevap\":\"\",\"zorluk\":2,\"cozulemez\":false}]";
            try { $y = OSC_YZ_API::json($prompt, 2500, 50, $gorseller); } catch (\Throwable $e) { $y = 'Yapay zekâ isteği başarısız: ' . $e->getMessage(); }
            $d['istek'] = (isset($d['istek']) ? $d['istek'] : 0) + 1;
            if (is_string($y)) {
                self::log($d, '⚠ ' . $y, 'uyari');
                if (preg_match('/kota|yoğun|429|529|kredi|geçersiz|tanımlı değil/iu', $y)) { $d['bekle'] = time() + (preg_match('/kredi|geçersiz|tanımlı değil/iu', $y) ? 86400 : 300); if (preg_match('/kredi|geçersiz|tanımlı değil/iu', $y)) $d['aktif'] = 0; }
                else { $d['hata_sayi'] = (isset($d['hata_sayi']) ? $d['hata_sayi'] : 0) + 1; if ($d['hata_sayi'] >= 3) { foreach ($harita as $id => $h) { $q = $h['q']; $q['cevap_denendi'] = 1; $wpdb->update($t, array('soru' => wp_json_encode($q)), array('id' => $id)); } $d['hata_sayi'] = 0; } }
                break;
            }
            $d['hata_sayi'] = 0;
            $gelen = array();
            foreach ($y as $c) {
                if (!is_array($c) || empty($c['id']) || !isset($harita[(int) $c['id']])) continue;
                $id = (int) $c['id']; $q = $harita[$id]['q']; $gelen[$id] = 1;
                $ok = false; $z = isset($c['zorluk']) ? (int) $c['zorluk'] : 0; if ($z >= 1 && $z <= 3) $q['zorluk'] = $z;
                if ($harita[$id]['var']) { $q['cevap_denendi'] = 1; $wpdb->update($t, array('soru' => wp_json_encode($q)), array('id' => $id)); $d['yapilan']++; $d['zorluk'] = (isset($d['zorluk']) ? $d['zorluk'] : 0) + 1; continue; }
                if (empty($c['cozulemez'])) {
                    if ($harita[$id]['tur'] === 'multiple') { $h = strtoupper(substr(trim((string) (isset($c['dogru']) ? $c['dogru'] : '')), 0, 1)); if ($h && !empty($q['options'][$h])) { $q['correct'] = $h; $ok = true; } }
                    else { $cv = trim((string) (isset($c['cevap']) ? $c['cevap'] : '')); if ($cv !== '') { $q['options'] = array('A' => nl2br(esc_html(mb_substr($cv, 0, 1200)))); $ok = true; } }
                }
                $q['cevap_denendi'] = 1; if ($ok) $q['cevap_yz'] = OSC_YZ_API::saglayici();
                $wpdb->update($t, array('soru' => wp_json_encode($q)), array('id' => $id));
                $d['yapilan']++; if ($ok) $d['cevap']++; else $d['atlanan']++;
            }
            foreach ($harita as $id => $h) if (!isset($gelen[$id])) { $q = $h['q']; $q['cevap_denendi'] = 1; $wpdb->update($t, array('soru' => wp_json_encode($q)), array('id' => $id)); $d['yapilan']++; $d['atlanan']++; }
            self::log($d, '✓ ' . count($gelen) . ' soru işlendi' . ($gorseller ? ' (' . count($gorseller) . ' görselle)' : '') . ' · toplam ' . $d['cevap'] . ' cevap eklendi' . ($d['atlanan'] ? ', ' . $d['atlanan'] . ' atlandı (emin değil)' : ''), 'ok');
            unset($d['bekle']);
            update_option(self::CDURUM, $d, false);
        }
        update_option(self::CDURUM, $d, false);
        delete_transient('osc_hv_ckilit');
    }

    /** Kuyruktan SURE saniye boyunca dosya işle */
    public function adim() {
        if (get_transient(self::KILIT)) return false;
        set_transient(self::KILIT, 1, 90);
        osc_uzun_islem(120);
        $bas = microtime(true);
        $d = self::durum(); $d['son'] = time();
        while (microtime(true) - $bas < self::SURE) {
            if (get_option('osc_hv_durdur')) break;
            $k = self::kuyruk(); if (!$k) break;
            $x = array_shift($k); update_option(self::KUYRUK, $k, false);
            try { $sonuc = $this->kaynak_isle($x); } catch (\Throwable $e) { $sonuc = 'dosya işlenemedi: ' . mb_substr($e->getMessage(), 0, 120); }
            $isl = get_option('osc_hv_islenen', array()); if (!is_array($isl)) $isl = array();
            $ad = rawurldecode(basename(parse_url($x['url'], PHP_URL_PATH)));
            if (is_string($sonuc)) {
                $gecici = strpos($sonuc, 'indirilemedi') !== false;
                if ($gecici && $x['deneme'] < 2) { $x['deneme']++; $k = self::kuyruk(); $k[] = $x; update_option(self::KUYRUK, $k, false); self::log($d, '↻ ' . $ad . ': ' . $sonuc . ' (sonra tekrar denenecek)', 'uyari'); }
                else { $isl[$x['k']] = array('t' => time(), 'n' => 0, 'hata' => mb_substr($sonuc, 0, 200), 'gecici' => 0); $d['hata'] = (isset($d['hata']) ? $d['hata'] : 0) + 1; $d['islenen'] = (isset($d['islenen']) ? $d['islenen'] : 0) + 1; self::log($d, '✗ ' . $ad . ': ' . $sonuc, 'hata'); }
            } else {
                $isl[$x['k']] = array('t' => time(), 'n' => $sonuc['soru'], 'hata' => $sonuc['not'], 'gecici' => 0);
                foreach (array('islenen' => 1, 'soru' => $sonuc['soru'], 'eklenen' => $sonuc['eklenen'], 'onayli' => $sonuc['onayli'], 'tekrar' => $sonuc['tekrar']) as $a => $v) $d[$a] = (isset($d[$a]) ? $d[$a] : 0) + $v;
                self::log($d, ($sonuc['soru'] ? '✓ ' : '• ') . $ad . ($sonuc['meta'] ? ' [' . $sonuc['meta'] . ']' : '') . ': ' . $sonuc['soru'] . ' soru (' . $sonuc['eklenen'] . ' yeni, ' . $sonuc['onayli'] . ' onaylı)' . ($sonuc['not'] ? ' — ' . $sonuc['not'] : ''), $sonuc['soru'] ? 'ok' : '');
            }
            update_option('osc_hv_islenen', $isl, false);
            self::durum_yaz($d);
        }
        if (!self::kuyruk() && empty($d['bitti']) && !empty($d['basladi'])) {
            $d['bitti'] = time(); self::log($d, '■ Aktarım bitti.', 'ok'); wp_clear_scheduled_hook(self::OLAY);
            // ardından cevap anahtarı ve zorluk işini kendiliğinden başlat
            if (OSC_YZ_API::var_mi() && get_option('osc_hv_oto_cevap', '1') !== '0' && !self::cevap_aktif() && ($n = self::cevapsiz_sayi())) {
                $c = array('aktif' => 1, 'basladi' => time(), 'toplam' => $n, 'yapilan' => 0, 'cevap' => 0, 'atlanan' => 0, 'istek' => 0, 'log' => array(), 'saglayici' => OSC_YZ_API::saglayici());
                self::log($c, '🔑 Aktarım bitti; ' . $n . ' soru için cevap anahtarı ve zorluk kendiliğinden hazırlanıyor.'); update_option(self::CDURUM, $c, false);
                self::log($d, '→ Cevap anahtarları yapay zekâyla hazırlanmaya başlandı (Özet sayfasında izleyebilirsiniz).', 'ok');
                if (!wp_next_scheduled(self::OLAY)) wp_schedule_event(time() + 30, 'osc_dakika', self::OLAY); self::tetikle();
            }
        }
        self::durum_yaz($d);
        delete_transient(self::KILIT);
        return true;
    }

    /* ---------------- AJAX (yönetim sayfası) ---------------- */
    public function ajax_arka_basla() {
        $this->yetki();
        delete_option('osc_hv_durdur');
        $silinen = 0;
        if (!empty($_POST['sifirla'])) {
            // eski ayrıştırıcıyla gelmiş arşiv sorularını sil, hepsini yeni kurallarla baştan aktar (öğretmen/yapay zekâ soruları korunur)
            global $wpdb; $silinen = (int) $wpdb->query("DELETE FROM " . OSC_Havuz::tablo() . " WHERE kaynak IN ('arsiv','yazilimatik')");
            delete_option('osc_hv_islenen'); update_option(self::KUYRUK, array(), false);
        }
        $r = OSC_Havuz::kaynak_listesi(sanitize_title(isset($_POST['kategori']) ? wp_unslash($_POST['kategori']) : 'yazili-sorulari'), !empty($_POST['tum']), !empty($_POST['yazilimatik']));
        $n = self::kuyruga_ekle($r['kaynaklar'], !empty($_POST['yeniden']) || $silinen > 0);
        $toplam = count(self::kuyruk());
        $d = array('basladi' => time(), 'toplam' => $toplam, 'islenen' => 0, 'soru' => 0, 'eklenen' => 0, 'onayli' => 0, 'tekrar' => 0, 'hata' => 0, 'log' => array());
        if ($silinen) self::log($d, '🧹 Eski aktarımdan gelen ' . $silinen . ' soru silindi; arşiv yeni kurallarla baştan aktarılıyor.');
        self::log($d, '🔎 ' . $r['yazi'] . ' yazı tarandı, ' . count($r['kaynaklar']) . ' dosya bulundu; ' . $toplam . ' dosya sıraya alındı.');
        if (!$toplam) { $d['bitti'] = time(); self::log($d, '✓ Aktarılacak yeni dosya yok. Hepsi daha önce aktarılmış.', 'ok'); }
        self::durum_yaz($d);
        if ($toplam) self::planla();
        wp_send_json_success(self::durum_ozet());
    }
    public function ajax_arka_durum() {
        $this->yetki();
        // Sayfa açıkken de ilerlesin: kilit boşsa ve bir süredir adım atılmadıysa burada bir adım çalıştır
        $d = self::durum();
        if (self::kuyruk() && !get_option('osc_hv_durdur') && (!isset($d['son']) || time() - $d['son'] > 25) && !get_transient(self::KILIT)) { $this->adim(); if (self::kuyruk()) self::tetikle(); }
        wp_send_json_success(self::durum_ozet());
    }
    public function ajax_arka_dur() {
        $this->yetki();
        update_option('osc_hv_durdur', 1, false); update_option(self::KUYRUK, array(), false); wp_clear_scheduled_hook(self::OLAY);
        $d = self::durum(); $d['bitti'] = time(); self::log($d, '■ Aktarım durduruldu.', 'uyari'); self::durum_yaz($d);
        wp_send_json_success(self::durum_ozet());
    }
    public static function durum_ozet() { $d = self::durum(); $d['kalan'] = count(self::kuyruk()); $d['calisiyor'] = $d['kalan'] > 0; $d['simdi'] = time(); return $d; }

    /** Yeni yayımlanan yazılı yazısının dosyaları otomatik sıraya girsin */
    public function yeni_yazi($yeni, $eski, $post) {
        if ($post->post_type !== 'post' || $yeni !== 'publish') return;
        $baslik = $post->post_title;
        $kat = wp_list_pluck(get_the_category($post->ID), 'slug');
        if (!preg_match('/yaz[ıi]l[ıi]/iu', $baslik . ' ' . $post->post_name) && !array_intersect($kat, array('yazili-sorulari'))) return;
        $o = array();
        if (preg_match_all('#(?:href|src)=["\']([^"\']+\.(?:zip|docx|txt))(?:\?[^"\']*)?["\']#i', (string) $post->post_content, $m)) foreach (array_unique($m[1]) as $u) {
            $u = html_entity_decode($u); $o[] = array('k' => md5(strtolower(preg_replace('#^https?://(www\.)?#', '', $u))), 'url' => $u, 'baslik' => $baslik, 'kategori' => implode(' / ', wp_list_pluck(get_the_category($post->ID), 'name')), 'etiket' => array(), 'kaynak' => 'arsiv');
        }
        if ($o && self::kuyruga_ekle($o)) { $d = self::durum(); if (!empty($d['bitti'])) $d = array('basladi' => time(), 'toplam' => count(self::kuyruk()), 'log' => array()); self::log($d, '＋ Yeni yazı: ' . $baslik); self::durum_yaz($d); if (!wp_next_scheduled(self::OLAY)) wp_schedule_event(time() + 60, 'osc_dakika', self::OLAY); }
    }

    private static function gecici($ad) { if (!function_exists('wp_tempnam')) require_once ABSPATH . 'wp-admin/includes/file.php'; return wp_tempnam($ad); }
    /** Havuzdaki bir görseli yapay zekâya göndermek için oku (yalnızca sitedeki dosyalar) */
    public function gorsel_oku($url) {
        $y = $this->yerel_yol(html_entity_decode($url)); if (!$y) return null;
        $uz = strtolower(pathinfo($y, PATHINFO_EXTENSION)); $mm = array('png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp');
        if (!isset($mm[$uz]) || filesize($y) > 1500000 || filesize($y) < 100) return null;
        return array('mime' => $mm[$uz], 'veri' => base64_encode(file_get_contents($y)));
    }
    /* ---------------- dosya indirme ---------------- */
    private function yerel_yol($url) {
        $up = wp_upload_dir(); $h = function ($u) { return strtolower(preg_replace('#^www\.#', '', (string) parse_url($u, PHP_URL_HOST))); };
        if ($h($url) !== $h(home_url())) return '';
        $yol = rawurldecode((string) parse_url($url, PHP_URL_PATH)); $tb = (string) parse_url($up['baseurl'], PHP_URL_PATH);
        if ($tb && strpos($yol, $tb . '/') === 0) {
            $f = $up['basedir'] . substr($yol, strlen($tb)); $r = realpath($f); $b = realpath($up['basedir']);
            if ($r && $b && strpos($r, $b) === 0 && is_file($r)) return $r;
        }
        return '';
    }
    private function indir($url) {
        $y = $this->yerel_yol($url); if ($y) return array($y, false);
        if (!function_exists('download_url')) require_once ABSPATH . 'wp-admin/includes/file.php';
        $t = download_url($url, 60);
        if (is_wp_error($t)) return 'dosya indirilemedi (' . $t->get_error_message() . ')';
        return array($t, true);
    }

    /* ---------------- bir kaynağı işle ---------------- */
    private function kaynak_isle($x) {
        $ad = rawurldecode(basename((string) parse_url($x['url'], PHP_URL_PATH)));
        $uz = strtolower(pathinfo($ad, PATHINFO_EXTENSION));
        if ($uz === 'doc') return 'eski Word (.doc) okunamıyor; .docx olarak kaydedilip yeniden yüklenmeli';
        if ($uz === 'pdf') return 'PDF sunucuda okunamıyor; “Tarayıcıda aktar” ile aktarılabilir';
        if (!in_array($uz, array('zip', 'docx', 'txt'), true)) return 'desteklenmeyen dosya türü';
        $dl = $this->indir($x['url']); if (is_string($dl)) return $dl;
        list($yol, $gecici) = $dl;
        $belgeler = array(); $notlar = array();
        try {
            if ($uz === 'zip') $this->zip_ac($yol, $belgeler, $notlar, 0);
            else $belgeler[] = array('ad' => $ad, 'tur' => $uz, 'veri' => file_get_contents($yol));
        } catch (Exception $e) { $notlar[] = $e->getMessage(); }
        if ($gecici) @unlink($yol);
        if (!$belgeler) return $notlar ? implode('; ', array_unique($notlar)) : 'içinde Word dosyası yok';
        $top = array('soru' => 0, 'eklenen' => 0, 'onayli' => 0, 'tekrar' => 0, 'meta' => '', 'not' => '');
        foreach ($belgeler as $b) {
            if ($b['tur'] === 'docx') { try { list($metin, $resimler) = $this->docx_metin($b['veri']); } catch (Exception $e) { $notlar[] = basename($b['ad']) . ': ' . $e->getMessage(); continue; } }
            else { $metin = str_replace("\r", '', (string) $b['veri']); if (!preg_match('//u', $metin)) $metin = mb_convert_encoding($metin, 'UTF-8', 'Windows-1254'); $resimler = array(); }
            $l = $this->belge_isle($b['ad'], $metin, $resimler, $x);
            if (!$l['sorular']) { $notlar[] = basename($b['ad']) . ': ' . (isset($l['not']) ? $l['not'] : 'soru bulunamadı'); continue; }
            $r = OSC_Havuz::ekle_dizi($l['sorular']);
            $top['soru'] += count($l['sorular']); $top['eklenen'] += $r['eklenen']; $top['tekrar'] += $r['tekrar'];
            foreach ($l['sorular'] as $i => $q) if ($q['durum'] === 'onayli' && !empty($r['idler'][$i])) $top['onayli']++;
            if (!$top['meta']) $top['meta'] = $l['meta'];
        }
        $top['not'] = $notlar ? implode('; ', array_slice(array_unique($notlar), 0, 3)) : '';
        return $top;
    }
    private function zip_ac($yol, &$belgeler, &$notlar, $derinlik) {
        if (!class_exists('ZipArchive')) throw new Exception('sunucuda ZipArchive yok');
        $z = new ZipArchive(); if ($z->open($yol) !== true) throw new Exception('zip açılamadı');
        for ($i = 0; $i < $z->numFiles && count($belgeler) < 40; $i++) {
            $n = $z->getNameIndex($i); if (!$n || substr($n, -1) === '/' || preg_match('#(^|/)(__MACOSX|\.|~\$)#', $n)) continue;
            $uz = strtolower(pathinfo($n, PATHINFO_EXTENSION));
            if ($uz === 'doc') { $notlar[] = basename($n) . ' (.doc okunamaz)'; continue; }
            if ($uz === 'pdf') { $notlar[] = basename($n) . ' (PDF tarayıcıda aktarılmalı)'; continue; }
            if (in_array($uz, array('docx', 'txt'), true)) { $v = $z->getFromIndex($i); if ($v !== false && strlen($v) < 40 * 1024 * 1024) $belgeler[] = array('ad' => $n, 'tur' => $uz, 'veri' => $v); }
            elseif ($uz === 'zip' && $derinlik < 1) { $v = $z->getFromIndex($i); $t = self::gecici('osc-ic.zip'); file_put_contents($t, $v); try { $this->zip_ac($t, $belgeler, $notlar, $derinlik + 1); } catch (Exception $e) { $notlar[] = $e->getMessage(); } @unlink($t); }
        }
        $z->close();
    }

    /* ---------------- Word (.docx) → metin + görseller ---------------- */
    private function docx_metin($veri) {
        $t = self::gecici('osc.docx'); file_put_contents($t, $veri);
        $z = new ZipArchive(); if ($z->open($t) !== true) { @unlink($t); throw new Exception('Word dosyası açılamadı'); }
        $xml = $z->getFromName('word/document.xml');
        $rels = array(); $rx = $z->getFromName('word/_rels/document.xml.rels');
        if ($rx && preg_match_all('#<Relationship [^>]*Id="([^"]+)"[^>]*Target="([^"]+)"#', $rx, $m, PREG_SET_ORDER)) foreach ($m as $r) $rels[$r[1]] = $r[2];
        if ($rx && preg_match_all('#<Relationship [^>]*Target="([^"]+)"[^>]*Id="([^"]+)"#', $rx, $m, PREG_SET_ORDER)) foreach ($m as $r) if (!isset($rels[$r[2]])) $rels[$r[2]] = $r[1];
        $satirlar = array(); $resimler = array();
        // Word otomatik numaralandırması (1. 2. 3. / A) B) C)) metinde yer almaz; yeniden üret
        $numara = array(); $nx = $z->getFromName('word/numbering.xml');
        if ($nx) {
            $soyut = array();
            if (preg_match_all('#<w:abstractNum\b[^>]*w:abstractNumId="(\d+)"[^>]*>(.*?)</w:abstractNum>#s', $nx, $am, PREG_SET_ORDER)) foreach ($am as $a) {
                if (preg_match_all('#<w:lvl\b[^>]*w:ilvl="(\d+)"[^>]*>(.*?)</w:lvl>#s', $a[2], $lm, PREG_SET_ORDER)) foreach ($lm as $l) {
                    $fmt = preg_match('#<w:numFmt w:val="([^"]+)"#', $l[2], $f) ? $f[1] : 'decimal';
                    $bas = preg_match('#<w:start w:val="(\d+)"#', $l[2], $f) ? (int) $f[1] : 1;
                    $soyut[$a[1]][(int) $l[1]] = array('fmt' => $fmt, 'bas' => $bas);
                }
            }
            if (preg_match_all('#<w:num\b[^>]*w:numId="(\d+)"[^>]*>.*?<w:abstractNumId w:val="(\d+)"#s', $nx, $nm, PREG_SET_ORDER)) foreach ($nm as $n) if (isset($soyut[$n[2]])) $numara[$n[1]] = $soyut[$n[2]];
        }
        // stil üzerinden numaralandırma (ör. "Liste Numarası" stili)
        $stil_num = array(); $sx = $z->getFromName('word/styles.xml');
        if ($sx && preg_match_all('#<w:style\b[^>]*w:styleId="([^"]+)"[^>]*>(.*?)</w:style>#s', $sx, $sm, PREG_SET_ORDER)) foreach ($sm as $st) if (preg_match('#<w:numPr>(.*?)</w:numPr>#s', $st[2], $x)) $stil_num[$st[1]] = $x[1];
        $sayac = array();
        $on_ek = function ($p) use ($numara, &$sayac, $stil_num) {
            $ppr = $p->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'pPr')->item(0);
            $px = $ppr ? $p->ownerDocument->saveXML($ppr) : '';
            if (preg_match('#<w:numPr>(.*?)</w:numPr>#s', $px, $m)) $np = $m[1];
            elseif (preg_match('#<w:pStyle w:val="([^"]+)"#', $px, $m) && isset($stil_num[$m[1]])) $np = $stil_num[$m[1]];
            else return '';
            $m = array(1 => $np);
            $il = preg_match('#w:ilvl w:val="(\d+)"#', $m[1], $x) ? (int) $x[1] : 0; $id = preg_match('#w:numId w:val="(\d+)"#', $m[1], $x) ? $x[1] : '';
            if ($id === '' || $id === '0' || !isset($numara[$id][$il])) return '';
            $lv = $numara[$id][$il]; $k = $id . ':' . $il;
            $sayac[$k] = isset($sayac[$k]) ? $sayac[$k] + 1 : $lv['bas'];
            foreach ($sayac as $kk => $v) { list($i2, $l2) = explode(':', $kk); if ($i2 === $id && (int) $l2 > $il) unset($sayac[$kk]); }
            $n = $sayac[$k];
            switch ($lv['fmt']) {
                case 'decimal': case 'decimalZero': return $n . ') ';
                case 'upperLetter': return chr(64 + min(26, $n)) . ') ';
                case 'lowerLetter': return chr(96 + min(26, $n)) . ') ';
                case 'upperRoman': case 'lowerRoman': return '';
                case 'bullet': return '• ';
            }
            return '';
        };
        if ($xml) {
            if (!class_exists('DOMDocument')) { $z->close(); @unlink($t); throw new Exception('sunucuda PHP XML (DOM) eklentisi yok'); }
            $dom = new DOMDocument(); $eski = libxml_use_internal_errors(true);
            $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE); libxml_clear_errors(); libxml_use_internal_errors($eski);
            $xp = new DOMXPath($dom);
            foreach (array('w' => 'http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'a' => 'http://schemas.openxmlformats.org/drawingml/2006/main', 'r' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'm' => 'http://schemas.openxmlformats.org/officeDocument/2006/math', 'v' => 'urn:schemas-microsoft-com:vml') as $p => $ns) $xp->registerNamespace($p, $ns);
            $resim = function ($rid) use ($z, $rels, &$resimler) {
                if (!$rid || !isset($rels[$rid])) return '';
                $hedef = 'word/' . ltrim(preg_replace('#^\./#', '', $rels[$rid]), '/'); $hedef = preg_replace('#word/\.\./#', '', $hedef);
                $uz = strtolower(pathinfo($hedef, PATHINFO_EXTENSION));
                if (in_array($uz, array('wmf', 'emf', 'wdp'), true)) return '§EKSIK§';   // MathType formülü/eski çizim: tarayıcıda gösterilemez
                if (!in_array($uz, array('png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp'), true)) return '';
                $b = $z->getFromName($hedef); if ($b === false || strlen($b) > 3 * 1024 * 1024 || strlen($b) < 200) return '';
                $resimler[] = array('uz' => $uz === 'jpeg' ? 'jpg' : $uz, 'veri' => $b);
                return '§IMG' . (count($resimler) - 1) . '§';
            };
            // Word denklemleri (OMML) → LaTeX ($...$): ekranda ve Word çıktısında gerçek formül olarak görünür
            $omml = function ($n) use (&$omml, $xp) {
                $ic = function ($ad) use ($n, $xp, &$omml) { $e = $xp->query('./m:' . $ad, $n)->item(0); return $e ? trim($omml($e)) : ''; };
                $val = function ($yol) use ($n, $xp) { $e = $xp->query($yol, $n)->item(0); return $e ? $e->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/math', 'val') : null; };
                switch ($n->localName) {
                    case 't': return strtr($n->textContent, array('×' => '\times ', '÷' => '\div ', '·' => '\cdot ', '⋅' => '\cdot ', 'π' => '\pi ', '≤' => '\le ', '≥' => '\ge ', '≠' => '\ne ', '±' => '\pm ', '∞' => '\infty ', '−' => '-', '°' => '^{\circ}', '%' => '\%', '{' => '\{', '}' => '\}', '∈' => '\in ', '∉' => '\notin ', '⊂' => '\subset ', '∪' => '\cup ', '∩' => '\cap ', '∅' => '\emptyset ', '→' => '\rightarrow ', '⇒' => '\Rightarrow ', 'α' => '\alpha ', 'β' => '\beta ', 'θ' => '\theta ', 'Δ' => '\Delta ', '∆' => '\Delta ', '√' => '\sqrt{}'));
                    case 'f': return '\frac{' . $ic('num') . '}{' . $ic('den') . '}';
                    case 'sSup': return '{' . $ic('e') . '}^{' . $ic('sup') . '}';
                    case 'sSub': return '{' . $ic('e') . '}_{' . $ic('sub') . '}';
                    case 'sSubSup': return '{' . $ic('e') . '}_{' . $ic('sub') . '}^{' . $ic('sup') . '}';
                    case 'sPre': return '{}_{' . $ic('sub') . '}^{' . $ic('sup') . '}{' . $ic('e') . '}';
                    case 'rad': $d = $ic('deg'); return '\sqrt' . ($d !== '' ? '[' . $d . ']' : '') . '{' . $ic('e') . '}';
                    case 'd': $b = $val('./m:dPr/m:begChr'); $e2 = $val('./m:dPr/m:endChr'); $ps = array(); foreach ($xp->query('./m:e', $n) as $x) $ps[] = trim($omml($x));
                        $kc = function ($c, $v) { if ($c === null) $c = $v; if ($c === '') return '.'; return strtr($c, array('{' => '\{', '}' => '\}', '|' => '|', '⌊' => '\lfloor ', '⌋' => '\rfloor ', '⟨' => '\langle ', '⟩' => '\rangle ')); };
                        return '\left' . $kc($b, '(') . implode(', ', $ps) . '\right' . $kc($e2, ')');
                    case 'bar': return ($val('./m:barPr/m:pos') === 'bot' ? '\underline{' : '\overline{') . $ic('e') . '}';
                    case 'acc': $c = $val('./m:accPr/m:chr'); $m = array('̄' => '\bar', '̂' => '\hat', '⃗' => '\vec', '̃' => '\tilde', '̇' => '\dot'); return (isset($m[$c]) ? $m[$c] : '\hat') . '{' . $ic('e') . '}';
                    case 'nary': $c = $val('./m:naryPr/m:chr'); $m = array('∑' => '\sum', '∏' => '\prod', '∫' => '\int', '∮' => '\oint'); $sb = $ic('sub'); $sp = $ic('sup');
                        return (isset($m[$c]) ? $m[$c] : '\int') . ($sb !== '' ? '_{' . $sb . '}' : '') . ($sp !== '' ? '^{' . $sp . '}' : '') . ' ' . $ic('e');
                    case 'func': return '\operatorname{' . trim(strip_tags($ic('fName'))) . '}' . $ic('e') . ' ';
                    case 'limLow': return $ic('e') . '_{' . $ic('lim') . '}';
                    case 'limUpp': return $ic('e') . '^{' . $ic('lim') . '}';
                    case 'eqArr': $ps = array(); foreach ($xp->query('./m:e', $n) as $x) $ps[] = trim($omml($x)); return '\begin{matrix}' . implode('\\\\', $ps) . '\end{matrix}';
                    case 'm': $rs = array(); foreach ($xp->query('./m:mr', $n) as $mr) { $cs = array(); foreach ($xp->query('./m:e', $mr) as $x) $cs[] = trim($omml($x)); $rs[] = implode(' & ', $cs); } return '\begin{matrix}' . implode('\\\\', $rs) . '\end{matrix}';
                    case 'box': case 'borderBox': case 'groupChr': case 'phant': return $ic('e');
                    case 'rPr': case 'ctrlPr': case 'fPr': case 'radPr': case 'dPr': case 'sSupPr': case 'sSubPr': case 'sSubSupPr': case 'naryPr': case 'oMathParaPr': case 'barPr': case 'accPr': case 'funcPr': case 'mPr': case 'eqArrPr': case 'limLowPr': case 'limUppPr': case 'boxPr': case 'borderBoxPr': case 'groupChrPr': case 'phantPr': case 'sPrePr': return '';
                    case 'r': $o = ''; foreach ($n->childNodes as $c) if ($c instanceof DOMElement && $c->localName === 't') $o .= $omml($c); return $o;
                }
                $o = ''; foreach ($n->childNodes as $c) if ($c instanceof DOMElement) $o .= $omml($c); return $o;
            };
            $paragraf = function ($p) use ($xp, $resim, $omml) {
                $s = '';
                foreach ($xp->query('.//w:t[not(ancestor::m:oMath)]|.//w:tab[not(ancestor::m:oMath)]|.//w:br|.//w:cr|.//a:blip|.//v:imagedata|.//w:sym|.//m:oMath[not(ancestor::m:oMath)]|.//w:object[not(.//v:imagedata)]', $p) as $n) {
                    switch ($n->localName) {
                        case 'oMath': $lt = trim(preg_replace('/\s+/', ' ', $omml($n))); if ($lt !== '') $s .= ' $' . $lt . '$ '; break;
                        case 'object': $s .= ' §EKSIK§ '; break;
                        case 't': $s .= $n->textContent; break;
                        case 'tab': $s .= ' '; break;
                        case 'br': case 'cr': $s .= "\n"; break;
                        case 'blip': $s .= ' ' . $resim($n->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed')) . ' '; break;
                        case 'imagedata': $s .= ' ' . $resim($n->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id')) . ' '; break;
                        case 'sym': $s .= '•'; break;
                    }
                }
                return $s;
            };
            $govde = $xp->query('/w:document/w:body')->item(0);
            if ($govde) foreach ($govde->childNodes as $c) {
                if (!($c instanceof DOMElement)) continue;
                if ($c->localName === 'p') { $pt = $paragraf($c); $oe = trim($pt) !== '' ? $on_ek($c) : ''; foreach (explode("\n", $oe . ltrim($pt)) as $l) $satirlar[] = $l; }
                elseif ($c->localName === 'tbl') {
                    foreach ($xp->query('./w:tr', $c) as $tr) {
                        $hucre = array();
                        foreach ($xp->query('./w:tc', $tr) as $tc) { $ic = array(); foreach ($xp->query('.//w:p', $tc) as $p) { $y = trim($paragraf($p)); if ($y !== '') $ic[] = $on_ek($p) . $y; } if ($ic) $hucre[] = $ic; }
                        if (count($hucre) > 1) $hucre = array_values(array_filter($hucre, function ($ic) { return !(count($ic) === 1 && preg_match('/^(\d{1,2}[\.\)]?|[A-E][\.\)]?|\(\s*\d+\s*(p|puan)?\s*\))$/iu', $ic[0])); }));
                        // tek sütunlu ya da numaralı hücreler: her paragrafı ayrı satır yap (soru tabloları)
                        $tek = count($hucre) <= 2;
                        if ($tek) { foreach ($hucre as $ic) foreach ($ic as $y) $satirlar[] = $y; }
                        else $satirlar[] = implode(' | ', array_map(function ($ic) { return implode(' ', $ic); }, $hucre));
                    }
                } elseif ($c->localName === 'sdt') { foreach ($xp->query('.//w:p', $c) as $p) foreach (explode("\n", $paragraf($p)) as $l) $satirlar[] = $l; }
            }
        }
        $z->close(); @unlink($t);
        return array(implode("\n", $satirlar), $resimler);
    }

    /* ---------------- yazılı kâğıdı ayrıştırıcı ---------------- */
    /** Soru olmayan satırlar: imza, puan tablosu, "Başarılar dilerim", öğrenci bilgi satırları… */
    private static function cop_satir($l) {
        if (strpos($l, '?') !== false) return 0;
        $t = self::trl(strip_tags($l));
        if (preg_match('/^(başarılar|başarı dilerim|basarilar|kolay gelsin|bol şans|iyi şanslar|uygundur|okul müdürü|müdür yardımcısı|zümre başkanı|.{0,45}öğretmen(i|leri)\s*[\.,]?\s*$)/u', $t)) return 2; // bundan sonrası soru değil
        if (preg_match('/^(süre\b|sınav süresi|her soru \d+|her sorunun|her bir soru|puanlama\b|puan tablosu|toplam puan|aldığı puan|not\s*:|adı\b|ad[ıi]?\s*-?\s*soyad|sınıfı|numara|okul no|no\s*:|puan\s*:|imza|tarih\s*:|t\.?\s*c\.?\s*$|\d{4}\s*[-–\/]\s*\d{4}\b.*(yılı|yili)|.*\b(dönem|donem)\b.*\b(yazılı|yazili|sınav|sinav)\w*\s*$)/u', $t)) return 1;
        if (preg_match('/^(soru\s*)?\d{1,2}\s*$/u', $t)) return 1;
        if (preg_match('#(https?://|www\.|\.(com|net|org|biz|info)(\.tr)?\b|sitesinden|indirilmiştir|indirildi)#u', $t) && mb_strlen($t) < 120) return 1; // site adı / bağlantı satırı
        if (preg_match('/^[\(\[]?\s*\d+\s*([x×*]\s*\d+\s*=?\s*\d*)?\s*(puan|p|pn)\.?\s*[\)\]]?\s*$/u', $t)) return 1; // yalnız puan notu
        return 0;
    }
    private static function soru_gibi($metin) {
        $t = self::trl(strip_tags(str_replace('<br>', "\n", $metin)));
        if (strpos($t, '?') !== false) return true;
        if (preg_match('/(\.{4,}|…{2,}|_{3,}|\(\s*\)\s)/u', $t)) return true;
        if (preg_match('/\b\w*(ınız|iniz|unuz|ünüz)\b/u', $t)) return true;
        if (preg_match('/\b(nedir|nelerdir|kaçtır|hangisidir|hangileridir|neden|nasıl|örnek ver|doğru mu|yanlış mı|eşleştir|sıralay|tamamlay)\b/u', $t)) return true;
        return false;
    }
    private static function soru_say($metin) {
        $n = 0;
        foreach (preg_split('/\n|<br>|(?<=[\.\?!])\s+/u', $metin) as $c) {
            $c = trim(strip_tags($c)); if ($c === '' || preg_match('/^[a-eA-E]\s*[\)\.]/u', $c)) continue;
            if (preg_match('/\?\s*$/u', $c) || preg_match('/(ınız|iniz|unuz|ünüz)\s*[\.!]?\s*$/u', self::trl($c))) $n++;
        }
        return $n;
    }
    public static function ayristir($metin, &$red = null) {
        $red = 0;
        $satir = array_map(function ($x) { return trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $x)); }, explode("\n", str_replace("\r", '', (string) $metin)));
        $ab = -1;
        foreach ($satir as $i => $l) if (preg_match('/^(cevap\s*anahtar|cevaplar\b|yanıt\s*anahtar|cevap\s*kağıdı|CEVAP\s*ANAHTAR)/iu', $l)) { $ab = $i; break; }
        $govde = $ab >= 0 ? array_slice($satir, 0, $ab) : $satir; $anahtar = $ab >= 0 ? implode("\n", array_slice($satir, $ab)) : '';
        $sorular = array(); $cur = -1; $bek = 1; $kapali = false;
        foreach ($govde as $l) {
            if ($l === '') { if ($cur >= 0 && !$kapali) $sorular[$cur]['s'][] = ''; continue; }
            $n = 0; $geri = '';
            if (preg_match('/^(?:soru\s*)?(\d{1,2})\s*[\)\.\-–:]\s*(?=\S)(.*)$/iu', $l, $m)) { $n = (int) $m[1]; $geri = $m[2]; }
            elseif (preg_match('/^soru\s*(\d{1,2})\s*$/iu', $l, $m)) { $n = (int) $m[1]; }
            elseif (preg_match('/^S\s*(\d{1,2})\s*[\)\.\-:]\s*(.*)$/u', $l, $m)) { $n = (int) $m[1]; $geri = $m[2]; }
            if ($n && ($n === $bek || ($n === 1 && !$sorular) || ($cur >= 0 && $n === $sorular[$cur]['no'] + 1))) {
                $sorular[] = array('no' => $n, 's' => $geri !== '' ? array($geri) : array()); $cur = count($sorular) - 1; $bek = $n + 1; $kapali = false; continue;
            }
            $c = self::cop_satir($l);
            if ($c === 2) { $kapali = true; continue; }
            if ($c === 1 || $kapali) continue;
            if ($cur >= 0) $sorular[$cur]['s'][] = $l;
        }
        $tk = array(); $ak = array();
        if ($anahtar) {
            if (preg_match_all('/(\d{1,2})\s*[\)\.\-–:]\s*([A-Ea-e])(?![a-zçğıöşü])/u', $anahtar, $m, PREG_SET_ORDER)) foreach ($m as $x) $tk[(int) $x[1]] = strtoupper($x[2]);
            foreach (explode("\n", $anahtar) as $l) if (preg_match('/^\s*(?:soru\s*)?(\d{1,2})\s*[\)\.\-–:]\s*(.{2,})$/iu', $l, $m) && !preg_match('/^[A-Ea-e](\s|$|[,;])/u', $m[2])) $ak[(int) $m[1]] = trim($m[2]);
        }
        $o = array();
        foreach ($sorular as $q) {
            $t = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $q['s']))); $puan = 0;
            $t = trim(preg_replace_callback('/\(\s*(\d{1,3})\s*(puan|p|pn)\s*\.?\)/iu', function ($m) use (&$puan) { $puan = (int) $m[1]; return ''; }, $t));
            $op = array(); $kok = array(); $son = null; $kucuk = false;
            foreach (explode("\n", $t) as $l) {
                $par = preg_split('/\s+(?=[A-E]\s*[\)\.\-]\s*\S)/u', $l);
                $hepsi = count($par) > 1; if ($hepsi) foreach ($par as $p) if (!preg_match('/^[A-E]\s*[\)\.\-]\s*\S/u', trim($p))) { $hepsi = false; break; }
                if ($hepsi || (preg_match('/^[A-Ea-e]\s*[\)\.]\s*\S/u', $l) && ($son || !$op))) {
                    foreach ($hepsi ? $par : array($l) as $p) if (preg_match('/^([A-Ea-e])\s*[\)\.\-]\s*(.*)$/u', trim($p), $m)) { if (preg_match('/^[a-e]$/', $m[1])) $kucuk = true; $son = strtoupper($m[1]); $op[$son] = $m[2]; }
                } elseif ($son && $op && !preg_match('/[?:]$/u', $l)) $op[$son] .= ' ' . $l;
                else $kok[] = $l;
            }
            $harf = array_keys($op);
            $kokm = self::trl(implode(' ', $kok));
            $hangisi = (bool) preg_match('/hangisi|hangileri|doğru olan|yanlış olan|yanlıştır|doğrudur|seçenek/u', $kokm);
            // çoktan seçmeli mi? A) B) C)… büyük harfle ve en az 3 şık; küçük harfli a) b) c) çoğu zaman açık uçlu sorunun alt maddeleridir
            $test = count($harf) >= 2 && $harf[0] === 'A' && ((!$kucuk && (count($harf) >= 3 || $hangisi)) || ($kucuk && $hangisi && count($harf) >= 3));
            if (!$test) { $kok = explode("\n", $t); $op = array(); $harf = array(); }
            $ks = array_values(array_filter($kok, 'strlen'));
            $root = implode('<br>', $ks); $paragraph = '';
            if (!$test && count($ks) > 1 && preg_match('/^[a-e]\s*[\)\.]/u', end($ks))) { $paragraph = ''; } // alt maddeli açık uçlu soru: hepsi kökte kalsın
            elseif (count($ks) > 2) { $r = array_pop($ks); while ($ks && preg_match('/^(§IMG\d+§\s*)+$/u', $r)) $r = array_pop($ks) . '<br>' . $r; $paragraph = implode('<br>', $ks); $root = $r; }
            $tum = $paragraph . '<br>' . $root;
            if (trim(strip_tags(str_replace('§EKSIK§', '', $tum))) === '' && strpos($tum, '§IMG') === false) continue;
            // kalite: soru gibi görünmüyorsa ya da birkaç soru birleşmişse alma
            $uz = mb_strlen(strip_tags($tum));
            if (!$test && (!self::soru_gibi($tum) || self::soru_say($tum) >= 4 || $uz > 2200)) { $red++; continue; }
            if ($test && $uz > 2500) { $red++; continue; }
            $s = array('type' => 'classic', 'paragraph' => $paragraph, 'root' => $root, 'options' => array(), 'correct' => '', 'puan' => $puan);
            if ($test) { $s['type'] = 'multiple'; foreach (array('A', 'B', 'C', 'D', 'E') as $h) $s['options'][$h] = isset($op[$h]) ? $op[$h] : ''; $s['correct'] = isset($tk[$q['no']]) ? $tk[$q['no']] : ''; }
            elseif (preg_match('/(\.{5,}|…{2,}|_{4,})/u', $root) && !preg_match('/\?\s*$/u', $root)) { $s['type'] = 'gap'; $s['options'] = array('A' => isset($ak[$q['no']]) ? $ak[$q['no']] : ''); }
            else $s['options'] = array('A' => isset($ak[$q['no']]) ? $ak[$q['no']] : '');
            $o[] = $s;
        }
        return $o;
    }

    /* ---------------- sınıf / ders / dönem tahmini ---------------- */
    private static function trl($s) { return mb_strtolower(str_replace(array('I', 'İ'), array('ı', 'i'), (string) $s), 'UTF-8'); }
    public static function meta($metin, $ust = array()) {
        $m = self::trl($metin); $d = ' ' . preg_replace('/[_\-\.\/]+/u', ' ', $m) . ' ';
        $o = array('sinif' => 0, 'ders' => '', 'donem' => 0, 'yazili' => 0);
        if (preg_match('/(\d{1,2})\s*[\.\-_ ]?\s*(sınıf|sinif)/u', $m, $x)) $o['sinif'] = (int) $x[1];
        elseif (preg_match('/(?:^|\s)(\d{1,2})\s*(?:s\s)?(?=[a-zçğıöşü])/u', $d, $x) && (int) $x[1] >= 1 && (int) $x[1] <= 12) $o['sinif'] = (int) $x[1];
        if (preg_match('/(\d)\s*[\.\-_ ]?\s*(dönem|donem)/u', $m, $x)) $o['donem'] = (int) $x[1];
        if (preg_match('/(\d)\s*[\.\-_ ]?\s*(yazılı|yazili|sınav|sinav|ortak)/u', $m, $x)) $o['yazili'] = (int) $x[1];
        if (preg_match('/(\d)\s*d\s*[\-_ ]?\s*(\d)\s*y\b/u', $m, $x)) { if (!$o['donem']) $o['donem'] = (int) $x[1]; if (!$o['yazili']) $o['yazili'] = (int) $x[2]; }
        $dl = array(array('türk dili', 'Türk Dili ve Edebiyatı'), array('turk dili', 'Türk Dili ve Edebiyatı'), array('edebiyat', 'Türk Dili ve Edebiyatı'), array(' tde ', 'Türk Dili ve Edebiyatı'), array('türkçe', 'Türkçe'), array('turkce', 'Türkçe'), array('matematik', 'Matematik'), array(' mat ', 'Matematik'), array('fen bilim', 'Fen Bilimleri'), array('fenbilim', 'Fen Bilimleri'), array(' fen ', 'Fen Bilimleri'), array('sosyal', 'Sosyal Bilgiler'), array('inkılap', 'T.C. İnkılap Tarihi ve Atatürkçülük'), array('inkilap', 'T.C. İnkılap Tarihi ve Atatürkçülük'), array('inkilkap', 'T.C. İnkılap Tarihi ve Atatürkçülük'), array('ingilizce', 'İngilizce'), array('din kültür', 'Din Kültürü ve Ahlak Bilgisi'), array('din kultur', 'Din Kültürü ve Ahlak Bilgisi'), array(' din ', 'Din Kültürü ve Ahlak Bilgisi'), array('fizik', 'Fizik'), array('kimya', 'Kimya'), array('biyoloji', 'Biyoloji'), array('coğrafya', 'Coğrafya'), array('cografya', 'Coğrafya'), array('felsefe', 'Felsefe'), array('tarih', 'Tarih'));
        foreach ($dl as $x) if (mb_strpos($d, $x[0]) !== false) { $o['ders'] = $x[1]; break; }
        foreach ((array) $ust as $k => $v) if ($v) $o[$k] = $v;
        return $o;
    }

    /* ---------------- öğrenme çıktısı eşleştirme ---------------- */
    private static function tablo_bul($sinif, $ders, $donem = 0) {
        if (!$sinif || !$ders) return null;
        if ($ders === 'T.C. İnkılap Tarihi ve Atatürkçülük') $ders = $sinif <= 8 ? 'Sosyal Bilgiler' : 'Tarih';
        $t = OSC_Havuz::tablo_donem($sinif, $ders, $donem); if ($t) return $t;
        return null;
    }
    private static $DUR = null;
    private static $ES = array('üssü' => 'üslü', 'üzeri' => 'üslü', 'kuvveti' => 'üslü', 'kuvvet' => 'üslü', 'zar' => 'olasılık', 'torba' => 'olasılık', 'ihtimal' => 'olasılık', 'kök' => 'karekök', 'kökü' => 'karekök', 'karekökü' => 'karekök', 'grafik' => 'grafiği', 'grafikte' => 'grafiği');
    public static function kelimeler($s) {
        if (self::$DUR === null) self::$DUR = array_flip(explode(' ', 've ile bir bu şu o da de ki mi mı mu mü için gibi olarak olan en çok daha ise ya veya ama fakat ancak çünkü kadar sonra önce diye hem yani her ne nasıl hangi göre üzere ayrıca bunun bunu buna onun onu ona şey şekilde aşağıdaki yukarıdaki verilen verilmiştir buna göre yazınız açıklayınız belirtiniz bulunuz hesaplayınız örnek örnekle soru cevap puan nedir neler nelerdir hangisi hangileri kaç cevaplayınız yazın açıklayın the and of to is are a an in on for with that this what which how students can will able be use their about'));
        $t = preg_split('/\s+/u', trim(preg_replace('/[^a-zçğıöşüâîû0-9]+/u', ' ', self::trl(strip_tags((string) $s)))));
        $o = array();
        foreach ($t as $w) { if (mb_strlen($w) < 3 || isset(self::$DUR[$w]) || preg_match('/^\d+$/', $w)) continue; if (isset(self::$ES[$w])) $w = self::$ES[$w]; $o[] = mb_strlen($w) > 6 ? mb_substr($w, 0, 6) : $w; }
        return $o;
    }
    private static $dizin = array();
    private static function dizin($t) {
        $key = $t['ders'] . '|' . $t['sinif']; if (isset(self::$dizin[$key])) return self::$dizin[$key];
        $df = array(); $docs = array();
        foreach ($t['satirlar'] as $x) { $tf = array(); foreach (self::kelimeler($x['cikti'] . ' ' . $x['konu'] . ' ' . $x['konu'] . ' ' . (isset($x['unite']) ? $x['unite'] : '')) as $w) $tf[$w] = (isset($tf[$w]) ? $tf[$w] : 0) + 1; foreach ($tf as $w => $_) $df[$w] = (isset($df[$w]) ? $df[$w] : 0) + 1; $docs[] = array('x' => $x, 'tf' => $tf, 'n' => max(1, count($tf))); }
        $N = count($docs); $idf = array(); foreach ($df as $w => $n) $idf[$w] = log(1 + $N / $n);
        return self::$dizin[$key] = array('docs' => $docs, 'idf' => $idf);
    }
    private static function kullanilan($x, $yazili = 0) { $s = isset($x['sayi']) ? (array) $x['sayi'] : array(); if ($yazili) return !empty($s[(string) $yazili]); return !empty($s['1']) || !empty($s['2']); }
    private static function kodu($x) { return $x['kod'] ? $x['kod'] : mb_substr($x['cikti'], 0, 38); }
    private static function eslestir($soru, $meta, $t) {
        $D = self::dizin($t); $q = array();
        $op = isset($soru['options']) ? (array) $soru['options'] : array();
        foreach (self::kelimeler((isset($soru['paragraph']) ? $soru['paragraph'] : '') . ' ' . (isset($soru['root']) ? $soru['root'] : '') . ' ' . implode(' ', $op)) as $w) $q[$w] = 1;
        $tum = array(); $sira = array();
        foreach ($D['docs'] as $j => $d) {
            $s = 0; foreach ($q as $w => $_) if (isset($d['tf'][$w])) $s += isset($D['idf'][$w]) ? $D['idf'][$w] : 0;
            $s = $s / sqrt($d['n']);
            if ($meta['donem'] === 1 && self::kullanilan($d['x'], $meta['yazili'])) $s *= 1.25; elseif (!self::kullanilan($d['x'])) $s *= 0.7;
            $tum[$j] = $s; $sira[$j] = $s;
        }
        arsort($sira); $k = array_keys($sira);
        if (!$k || $sira[$k[0]] <= 0) return array('kod' => '', 'puan' => 0, 'guven' => 0, 'j' => -1, 'tum' => $tum);
        $b = $sira[$k[0]]; $i = isset($k[1]) ? $sira[$k[1]] : 0;
        $guven = min(1, ($b / 1.6) * ($i ? min(1, ($b - $i) / $b * 2.5 + 0.2) : 1));
        return array('kod' => self::kodu($t['satirlar'][$k[0]]), 'puan' => round($b, 2), 'guven' => round($guven, 2), 'j' => $k[0], 'tum' => $tum);
    }
    /** Yazılılar müfredat sırasını izler: belgedeki sorular sıraya duyarlı eşleştirilir */
    private static function sirali($sorular, $meta, $t) {
        $tek = array(); foreach ($sorular as $q) $tek[] = self::eslestir($q, $meta, $t);
        $J = count($t['satirlar']); $n = count($sorular); if (!$n || !$J) return $tek;
        $S = array(); $mx = array(); foreach ($tek as $i => $e) { $S[$i] = $e['tum']; $mx[$i] = max($e['tum']) ?: 1; }
        $best = array(); $from = array();
        for ($i = 0; $i < $n; $i++) { $onc = -1; $oncv = -INF; for ($j = 0; $j < $J; $j++) { if ($i && $best[$i - 1][$j] > $oncv) { $oncv = $best[$i - 1][$j]; $onc = $j; } $best[$i][$j] = $S[$i][$j] / $mx[$i] + ($i ? $oncv : 0); $from[$i][$j] = $onc; } }
        $j = 0; for ($k = 1; $k < $J; $k++) if ($best[$n - 1][$k] > $best[$n - 1][$j]) $j = $k;
        $yol = array(); for ($i = $n - 1; $i >= 0; $i--) { $yol[$i] = $j; $j = $from[$i][$j]; }
        $o = array();
        foreach ($sorular as $i => $q) {
            $e = $tek[$i]; if (!$e['puan']) { $o[] = $e; continue; }
            $jj = $yol[$i]; $x = $t['satirlar'][$jj]; $oran = $S[$i][$jj] / $mx[$i];
            if ($jj === $e['j']) { $e['guven'] = min(1, $e['guven'] + 0.15); $o[] = $e; continue; }
            if ($oran < 0.35 || $t['satirlar'][$e['j']]['konu'] === $x['konu']) { $o[] = $e; continue; }
            $o[] = array('kod' => self::kodu($x), 'puan' => round($S[$i][$jj], 2), 'guven' => round(min(0.6, 0.3 + $oran * 0.3), 2));
        }
        return $o;
    }

    /* ---------------- belge → havuz satırları ---------------- */
    private function belge_isle($ad, $metin, $resimler, $k) {
        // sınav kâğıdı olmayan belgeler (tutanak, plan, rapor…) havuza girmesin
        $bas = self::trl(basename($ad) . ' ' . mb_substr($metin, 0, 300));
        if (preg_match('/tutanak|zümre|zumre|toplantı|toplanti|dilekçe|dilekce|yıllık plan|yillik plan|günlük plan|ders planı|ünitelendirilmiş|unitelendirilmis|rapor|karar defteri|rehberlik|veli bilgilendirme|kazanım listesi|soru analizi|madde analizi|başarı analizi/u', $bas)) return array('sorular' => array(), 'meta' => '', 'not' => 'sınav kâğıdı değil (tutanak/plan/rapor)');
        $sorular = self::ayristir($metin, $red);
        if (count($sorular) < 2 || $red > count($sorular)) return array('sorular' => array(), 'meta' => '', 'not' => count($sorular) < 2 ? 'soru bulunamadı' : 'belge sınav kâğıdına benzemiyor');
        // görselleri yükle ve yer tutucuları <img> yap
        $url = array();
        if ($resimler) {
            $up = wp_upload_dir(); $dz = trailingslashit($up['basedir']) . 'osc-havuz/' . gmdate('Y/m'); wp_mkdir_p($dz);
            foreach ($resimler as $i => $r) { $f = md5($r['veri']) . '.' . $r['uz']; if (!file_exists($dz . '/' . $f)) file_put_contents($dz . '/' . $f, $r['veri']); $url[$i] = trailingslashit($up['baseurl']) . 'osc-havuz/' . gmdate('Y/m') . '/' . $f; }
        }
        $html = function ($s) use ($url) {
            $s = esc_html($s); $s = str_replace('&lt;br&gt;', '<br>', $s);
            $s = str_replace('§EKSIK§', '<span class="osc-eksik">[formül/görsel okunamadı]</span>', $s);
            return trim(preg_replace_callback('/§IMG(\d+)§/u', function ($m) use ($url) { return isset($url[(int) $m[1]]) ? '<img src="' . esc_url($url[(int) $m[1]]) . '" style="max-width:100%;height:auto;vertical-align:middle">' : ''; }, $s));
        };
        foreach ($sorular as &$q) {
            $q['_eksik'] = strpos($q['paragraph'] . $q['root'] . implode(' ', $q['options']), '§EKSIK§') !== false;
            $q['paragraph'] = $html($q['paragraph']); $q['root'] = $html($q['root']); foreach ($q['options'] as $h => $v) $q['options'][$h] = $html($v);
        } unset($q);
        $meta = self::meta(basename($ad) . ' ' . mb_substr($metin, 0, 400) . ' ' . $k['baslik'] . ' ' . $k['kategori'], isset($k['etiket']) ? $k['etiket'] : array());
        $t = self::tablo_bul($meta['sinif'], $meta['ders'], isset($meta['donem']) ? (int) $meta['donem'] : 0);
        $es = $t ? self::sirali($sorular, $meta, $t) : array();
        $esik = (float) get_option('osc_hv_esik', 0.45); if ($esik <= 0) $esik = 0.45;
        $o = array();
        foreach ($sorular as $i => $q) {
            $e = isset($es[$i]) ? $es[$i] : null; unset($q['puan']); $eksik = !empty($q['_eksik']); unset($q['_eksik']); if ($eksik) $q['gorsel_eksik'] = 1;
            $o[] = array('kaynak' => isset($k['kaynak']) ? $k['kaynak'] : 'arsiv', 'kaynak_url' => $k['url'], 'kaynak_baslik' => mb_substr($k['baslik'] . ' · ' . basename($ad), 0, 250), 'sinif' => $meta['sinif'], 'ders' => $meta['ders'], 'donem' => $meta['donem'], 'yazili' => $meta['yazili'], 'cikti_kod' => $e ? $e['kod'] : '', 'cikti_puan' => $e ? $e['guven'] : 0, 'durum' => !$e || !$e['kod'] ? ($meta['sinif'] && $meta['ders'] && (empty($meta['donem']) || in_array((int) $meta['donem'], OSC_Havuz::senaryolar()['donemler'], true)) ? 'inceleme' : 'eslesmedi') : ($e['guven'] >= $esik && !$eksik ? 'onayli' : 'inceleme'), 'soru' => $q);
        }
        return array('sorular' => $o, 'meta' => trim(($meta['sinif'] ? $meta['sinif'] . '. sınıf ' : '') . $meta['ders']));
    }
}
$GLOBALS['osc_havuz_arka'] = new OSC_Havuz_Arka();
}
