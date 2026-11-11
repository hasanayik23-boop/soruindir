/* =====================================================================
 * Yapay zekâ bağlantısı: Google Gemini veya Anthropic Claude
 *   Ayar: Online Testler → Yazılı Hazırla → Yapay zekâ sağlayıcısı
 * ===================================================================== */
if (!function_exists('osc_uzun_islem')) {
    /** Uzun süren arka plan işleri: bazı sunucularda bu fonksiyonlar kapalıdır, varsa kullan */
    function osc_uzun_islem($sn = 120) {
        if (function_exists('set_time_limit')) @set_time_limit($sn);
        if (function_exists('ignore_user_abort')) @ignore_user_abort(true);
        if (function_exists('ini_get') && function_exists('ini_set')) { $m = @ini_get('memory_limit'); if ($m && $m !== '-1' && (int) $m < 256 && stripos($m, 'G') === false) @ini_set('memory_limit', '256M'); }
    }
}
if (!class_exists('OSC_YZ_API')) {
class OSC_YZ_API {
    public static $sicaklik = 0.7;
    /** tahmini harcama (dolar) — toplu üretimde bütçe takibi için */
    public static $harcama = 0.0, $son_saglayici = '', $genis = false, $son_hatalar = array();
    public static function fiyat($saglayici, $model) {
        $m = strtolower((string) $model);
        if ($saglayici === 'gemini_ucretsiz') return array(0, 0);
        if ($saglayici === 'claude') return strpos($m, 'opus') !== false ? array(5, 25) : (strpos($m, 'sonnet') !== false ? array(3, 15) : array(0.10, 0.50));
        if ($saglayici === 'openai') return strpos($m, 'nano') !== false ? array(0.05, 0.40) : (strpos($m, 'mini') !== false ? array(0.25, 2.0) : array(1.25, 10));
        return strpos($m, 'pro') !== false ? array(1.25, 10) : (strpos($m, 'lite') !== false ? array(0.10, 0.40) : array(0.30, 2.50));
    }
    private static function say($saglayici, $model, $in, $out) { $f = self::fiyat($saglayici, $model); self::$harcama += ($in * $f[0] + $out * $f[1]) / 1000000; self::$son_saglayici = $saglayici; }
    /** Anahtarı tanımlı sağlayıcılar, tercih edilen başta */
    public static function saglayicilar() {
        $var = array();
        if (self::claude_anahtar()) $var[] = 'claude';
        if (trim((string) get_option('osc_ai_api_key'))) $var[] = 'gemini';
        if (trim((string) get_option('osc_openai_key'))) $var[] = 'openai';
        $s = get_option('osc_ai_saglayici', '');
        if ($s && in_array($s, $var, true)) $var = array_values(array_unique(array_merge(array($s), $var)));
        // önce ücretsiz Gemini anahtarı (faturalandırması kapalı proje); kotası dolunca ücretli sağlayıcılara geçilir
        if (trim((string) get_option('osc_gemini_ucretsiz_key')) && !get_transient('osc_yz_ucretsiz_doldu')) array_unshift($var, 'gemini_ucretsiz');
        // kredisi biten sağlayıcıyı bir süre atla (hepsi bittiyse yine de dene)
        $acik = array_values(array_filter($var, function ($x) { return !get_transient('osc_yz_bitti_' . $x); }));
        return $acik ? $acik : $var;
    }
    public static function saglayici() { $v = self::saglayicilar(); return $v ? $v[0] : ''; }
    public static function claude_anahtar() { return trim((string) get_option('osc_claude_key', '')); }
    public static function var_mi() { return self::saglayici() !== ''; }
    /** JSON beklenen istem gönder; ayrıştırılmış diziyi ya da hata metnini döndür */
    public static function json($prompt, $max = 3000, $butce = 52, $gorseller = array()) {
        $t = self::istek($prompt, $max, $butce, $gorseller);
        if (is_wp_error($t)) return $t->get_error_message();
        $t = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($t)));
        $t = self::latex_onar($t);
        $d = json_decode($t, true);
        if (!is_array($d) && preg_match('/(\[.*\]|\{.*\})/s', $t, $m)) $d = json_decode($m[1], true);
        if (is_array($d) && count($d) === 1) { $v = reset($d); if (is_array($v) && array_keys($v) === range(0, count($v) - 1) && !is_int(key($d))) $d = $v; }   // {"sorular":[...]} -> [...]
        return is_array($d) ? $d : 'Yapay zekâ yanıtı çözümlenemedi.';
    }
    /** JSON içinde tek ters eğik çizgiyle yazılmış LaTeX komutlarını onar (\frac → \\frac) */
    public static function latex_onar($t) {
        $k = 'frac|dfrac|tfrac|sqrt|times|div|cdot|pi|leq?|geq?|neq?|pm|mp|infty|alpha|beta|gamma|delta|Delta|theta|lambda|mu|sigma|omega|Omega|phi|overline|underline|left|right|text|mathrm|mathbf|circ|degree|angle|triangle|perp|parallel|in|notin|subset|subseteq|cup|cap|emptyset|varnothing|Rightarrow|rightarrow|Leftrightarrow|leftarrow|sum|int|prod|log|ln|sin|cos|tan|cot|binom|vec|hat|bar|dot|dots|ldots|cdots|quad|qquad|approx|equiv|sim|cong|propto|mathbb|operatorname|begin|end|widehat|overrightarrow|square|%|\{|\}';
        return preg_replace('/(?<!\\\\)\\\\(?=(?:' . $k . ')(?![a-zA-Z]))/', '\\\\\\\\', $t);
    }
    /** @return string|WP_Error */
    /** @param array $gorseller [ ['etiket'=>'Görsel 1 (id=5)', 'mime'=>'image/png', 'veri'=>base64], ... ] */
    public static function istek($prompt, $max = 3000, $butce = 52, $gorseller = array()) {
        osc_uzun_islem(120);
        $liste = self::saglayicilar();
        if (!$liste) return new WP_Error('yok', 'Yapay zekâ anahtarı tanımlı değil.');
        $bas = microtime(true); $ilk = null;
        foreach ($liste as $s) {
            $kalan = $butce - (microtime(true) - $bas); if ($ilk && $kalan < 15) { if (!empty(self::$genis)) $kalan = 45; else break; }
            $t = $s === 'claude' ? self::claude($prompt, $max, $kalan, $gorseller) : ($s === 'openai' ? self::openai($prompt, $max, $kalan, $gorseller) : ($s === 'gemini_ucretsiz' ? self::gemini($prompt, $max, min($kalan, 40), $gorseller, trim((string) get_option('osc_gemini_ucretsiz_key')), true) : self::gemini($prompt, $max, $kalan, $gorseller)));
            if (!is_wp_error($t) && trim((string) $t) !== '') return $t;
            if (is_wp_error($t) && preg_match('/kredi|credit|quota|billing|bakiye|insufficient/i', $t->get_error_message())) set_transient('osc_yz_bitti_' . $s, 1, HOUR_IN_SECONDS);
            if (is_wp_error($t)) self::$son_hatalar[$s] = $t->get_error_message();
            if (!$ilk) $ilk = is_wp_error($t) ? $t : new WP_Error('bos', 'Yapay zekâ boş yanıt verdi.');
        }
        return $ilk;
    }
    /** tek bir sağlayıcıyı doğrudan dene (bağlantı testi) */
    public static function tek($s, $prompt, $max = 200, $butce = 40) {
        osc_uzun_islem(90);
        return $s === 'claude' ? self::claude($prompt, $max, $butce) : ($s === 'openai' ? self::openai($prompt, $max, $butce) : ($s === 'gemini_ucretsiz' ? self::gemini($prompt, $max, $butce, array(), trim((string) get_option('osc_gemini_ucretsiz_key')), true) : self::gemini($prompt, $max, $butce)));
    }
    /** anahtarı tanımlı tüm sağlayıcılar (kredisi biten dahil) */
    public static function tanimli() {
        $v = array();
        if (trim((string) get_option('osc_gemini_ucretsiz_key'))) $v[] = 'gemini_ucretsiz';
        if (self::claude_anahtar()) $v[] = 'claude';
        if (trim((string) get_option('osc_ai_api_key'))) $v[] = 'gemini';
        if (trim((string) get_option('osc_openai_key'))) $v[] = 'openai';
        return $v;
    }
    private static function claude_icerik($metin, $g) {
        if (!$g) return $metin;
        $o = array(array('type' => 'text', 'text' => $metin));
        foreach ($g as $x) { $o[] = array('type' => 'text', 'text' => $x['etiket'] . ':'); $o[] = array('type' => 'image', 'source' => array('type' => 'base64', 'media_type' => $x['mime'], 'data' => $x['veri'])); }
        return $o;
    }
    private static function gemini_parca($metin, $g) {
        $o = array(array('text' => $metin));
        foreach ((array) $g as $x) { $o[] = array('text' => $x['etiket'] . ':'); $o[] = array('inline_data' => array('mime_type' => $x['mime'], 'data' => $x['veri'])); }
        return $o;
    }
    private static function claude($prompt, $max, $butce, $gorseller = array()) {
        $model = trim((string) get_option('osc_claude_model', '')); if ($model === '') $model = 'claude-haiku-5-5';
        $r = wp_remote_post(apply_filters('osc_yz_claude_url', 'https://api.anthropic.com/v1/messages'), array(
            'timeout' => (int) min(85, $butce), 'headers' => array('content-type' => 'application/json', 'x-api-key' => self::claude_anahtar(), 'anthropic-version' => '2023-06-01'),
            'body' => wp_json_encode(array('model' => $model, 'max_tokens' => (int) $max, 'messages' => array(array('role' => 'user', 'content' => self::claude_icerik($prompt . "\n\nYanıtın yalnızca geçerli JSON olsun; açıklama ekleme.", $gorseller))))),
        ));
        if (is_wp_error($r)) return new WP_Error('ag', 'Claude’a bağlanılamadı: ' . $r->get_error_message());
        $kod = wp_remote_retrieve_response_code($r); $j = json_decode(wp_remote_retrieve_body($r), true);
        if ($kod !== 200) {
            $m = isset($j['error']['message']) ? $j['error']['message'] : 'HTTP ' . $kod;
            if ($kod === 429 || $kod === 529) $m = 'Yapay zekâ şu an yoğun (Claude ' . $kod . '). Biraz sonra tekrar deneyin.';
            if ($kod === 401) $m = 'Claude anahtarı geçersiz.';
            if ($kod === 400 && stripos($m, 'credit') !== false) $m = 'Claude hesabınızda kredi kalmamış.';
            return new WP_Error('claude' . $kod, $m);
        }
        if (isset($j['usage'])) self::say('claude', $model, (int) @$j['usage']['input_tokens'], (int) @$j['usage']['output_tokens']);
        $t = ''; foreach ((array) (isset($j['content']) ? $j['content'] : array()) as $p) if (isset($p['type'], $p['text']) && $p['type'] === 'text') $t .= $p['text'];
        return $t;
    }
    private static function openai($prompt, $max, $butce, $gorseller = array()) {
        $model = trim((string) get_option('osc_openai_model', '')); if ($model === '') $model = 'gpt-5-mini';
        $ic = array(array('type' => 'text', 'text' => $prompt . "\n\nYanıtın yalnızca geçerli bir JSON nesnesi olsun; dizi gerekiyorsa {\"sorular\": [...]} biçiminde sar, açıklama ekleme."));
        foreach ((array) $gorseller as $x) { $ic[] = array('type' => 'text', 'text' => $x['etiket'] . ':'); $ic[] = array('type' => 'image_url', 'image_url' => array('url' => 'data:' . $x['mime'] . ';base64,' . $x['veri'])); }
        $govde = array('model' => $model, 'messages' => array(array('role' => 'user', 'content' => $ic)), 'max_completion_tokens' => (int) max(2000, $max * 2), 'response_format' => array('type' => 'json_object'));
        if (preg_match('/^(gpt-5|o\d)/', $model)) $govde['reasoning_effort'] = 'minimal';
        $r = wp_remote_post(apply_filters('osc_yz_openai_url', 'https://api.openai.com/v1/chat/completions'), array('timeout' => (int) max(10, min(85, $butce)), 'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . trim((string) get_option('osc_openai_key'))), 'body' => wp_json_encode($govde)));
        if (is_wp_error($r)) return new WP_Error('ag', 'ChatGPT’ye bağlanılamadı: ' . $r->get_error_message());
        $kod = wp_remote_retrieve_response_code($r); $j = json_decode(wp_remote_retrieve_body($r), true);
        if ($kod !== 200) {
            $m = isset($j['error']['message']) ? $j['error']['message'] : 'HTTP ' . $kod;
            if ($kod === 401) $m = 'ChatGPT (OpenAI) anahtarı geçersiz.';
            if ($kod === 429) $m = stripos($m, 'quota') !== false ? 'OpenAI hesabınızda kredi kalmamış.' : 'Yapay zekâ şu an yoğun (OpenAI). Biraz sonra tekrar deneyin.';
            if ($kod === 400 && stripos($m, 'reasoning_effort') !== false) { unset($govde['reasoning_effort']); $r = wp_remote_post(apply_filters('osc_yz_openai_url', 'https://api.openai.com/v1/chat/completions'), array('timeout' => (int) max(10, min(85, $butce)), 'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . trim((string) get_option('osc_openai_key'))), 'body' => wp_json_encode($govde))); $j = json_decode(wp_remote_retrieve_body($r), true); if (wp_remote_retrieve_response_code($r) === 200) return (string) $j['choices'][0]['message']['content']; }
            return new WP_Error('openai' . $kod, $m);
        }
        if (isset($j['usage'])) self::say('openai', $model, (int) @$j['usage']['prompt_tokens'], (int) @$j['usage']['completion_tokens']);
        return isset($j['choices'][0]['message']['content']) ? (string) $j['choices'][0]['message']['content'] : '';
    }
    private static function gemini($prompt, $max, $butce, $gorseller = array(), $key = null, $ucretsiz = false) {
        if ($key === null) $key = trim((string) get_option('osc_ai_api_key'));
        $ml = trim((string) get_option('osc_ai_model', '')); if ($ml === '') $ml = 'gemini-flash-lite-latest,gemini-2.5-flash-lite,gemini-flash-latest';
        $taban = apply_filters('osc_yz_gemini_url', 'https://generativelanguage.googleapis.com/v1beta/models/');
        $hata = 'Yapay zekâ yanıt vermedi.'; $bas = microtime(true);
        foreach (array_filter(array_map('trim', explode(',', $ml))) as $m) {
            $kalan = $butce - (microtime(true) - $bas); if ($kalan < 12) break;
            $gc = array('temperature' => self::$sicaklik, 'responseMimeType' => 'application/json', 'maxOutputTokens' => (int) $max);
            if (strpos($m, '2.5') !== false) $gc['thinkingConfig'] = array('thinkingBudget' => 0);
            $r = wp_remote_post($taban . rawurlencode($m) . ':generateContent', array('timeout' => (int) min(80, $kalan), 'headers' => array('Content-Type' => 'application/json', 'x-goog-api-key' => $key), 'body' => wp_json_encode(array('contents' => array(array('role' => 'user', 'parts' => self::gemini_parca($prompt, $gorseller))), 'generationConfig' => $gc))));
            if (is_wp_error($r)) { $hata = $r->get_error_message(); continue; }
            $kod = wp_remote_retrieve_response_code($r); $j = json_decode(wp_remote_retrieve_body($r), true);
            if ($kod !== 200) { $hata = isset($j['error']['message']) ? $j['error']['message'] : 'HTTP ' . $kod; if ($kod === 429) { if ($ucretsiz) { $gunluk = preg_match('/per\s*day|PerDay|daily/i', $hata); $sn = $gunluk ? max(600, (int) (strtotime('tomorrow 08:00 UTC') - time()) % DAY_IN_SECONDS) : 70; set_transient('osc_yz_ucretsiz_doldu', 1, $sn); return new WP_Error('ucretsiz', 'Ücretsiz kota doldu.'); } $hata = 'Yapay zekâ kotası doldu (Google). Biraz sonra tekrar deneyin.'; } if ($kod === 400 && stripos($hata, 'key') !== false) break; continue; }
            if (isset($j['usageMetadata'])) self::say($ucretsiz ? 'gemini_ucretsiz' : 'gemini', $m, (int) @$j['usageMetadata']['promptTokenCount'], (int) @$j['usageMetadata']['candidatesTokenCount'] + (int) @$j['usageMetadata']['thoughtsTokenCount']);
            $t = ''; foreach ((array) (isset($j['candidates'][0]['content']['parts']) ? $j['candidates'][0]['content']['parts'] : array()) as $pp) if (isset($pp['text'])) $t .= $pp['text'];
            return $t;
        }
        return new WP_Error('gemini', $hata);
    }
}
}

/* =====================================================================
 * PDF / görüntüden test okuma: sayfa görüntüsünü yapay zekâ okur,
 * şekillerin yerini verir; şekiller tarayıcıda PDF'ten kesilir.
 * ===================================================================== */
if (!class_exists('OSC_PDF_YZ')) {
class OSC_PDF_YZ {
    public function __construct() {
        add_action('wp_ajax_osc_pdf_yz', array($this, 'ajax'));
        add_action('wp_footer', array($this, 'cfg'), 5);
        add_action('admin_footer', array($this, 'cfg'), 5);
    }
    public static $yazili_kural = '';
    /** yazılı modu: sayfa görüntüsü (PDF) ya da Word'den çıkarılmış metin */
    private function yazili($sayfa) {
        osc_uzun_islem(110);
        $metin = isset($_POST['metin']) ? mb_substr((string) wp_unslash($_POST['metin']), 0, 24000) : '';
        $g = array(); $veri = isset($_POST['gorsel']) ? (string) wp_unslash($_POST['gorsel']) : '';
        if ($metin === '') {
            if (!preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#', $veri, $m) || strlen($m[2]) > 6000000) wp_send_json_error('Sayfa görüntüsü okunamadı.');
            $g[] = array('etiket' => 'Sayfa ' . $sayfa, 'mime' => 'image/' . $m[1], 'veri' => $m[2]);
            $giris = "Bu görüntü bir yazılı sınav kâğıdının / soru belgesinin {$sayfa}. sayfası. Sayfadaki soruları yazdığı haliyle BİREBİR oku.\n- Sütunlu düzende önce sol sütunu, sonra sağ sütunu oku.\n- 'sekiller': soruya ait her resim, grafik, tablo, şekil, sayı doğrusu için {\"yer\":\"oncul\"|\"kok\"|\"A\"…,\"kutu\":[x0,y0,x1,y1]} (görüntünün sol üstüne göre 0-1000 ölçekli; şeklin yazıları kutunun içinde kalsın). Şeklin içindeki yazıları metne yazma.\n- Sayfanın en üstündeki soru önceki sayfadan devam ediyorsa (numarası yoksa) 'no': 0 yaz.\n";
        } else {
            $giris = "Aşağıdaki metin bir Word belgesinden çıkarılmış yazılı sınav kâğıdı / soru belgesidir. Soruları yazdığı haliyle BİREBİR ayıkla.\n- Metindeki §IMG3§ gibi resim işaretlerini ve §TBL1§ gibi tablo işaretlerini ait oldukları alana (oncul, kok, madde ya da cevap) AYNEN koy; silme, değiştirme.\n- Metin önceki parçadan devam eden bir soruyla başlıyorsa (numarası yoksa) 'no': 0 yaz.\n\n=== METİN ===\n" . $metin . "\n=== METİN SONU ===\n";
        }
        $prompt = $giris . "\n" . self::$yazili_kural . "\nYalnızca JSON döndür: {\"sorular\":[{\"no\":1,\"sinav\":1,\"kod\":\"\",\"tur\":\"acik\",\"oncul\":\"\",\"kok\":\"\",\"maddeler\":[],\"secenekler\":{},\"dogru\":\"\",\"cevap\":\"\"" . ($metin === '' ? ",\"sekiller\":[]" : '') . "}],\"anahtar\":{}}";
        OSC_YZ_API::$sicaklik = 0;
        $d = OSC_YZ_API::json($prompt, 8000, 100, $g);
        if (is_string($d)) wp_send_json_error($d);
        if (isset($d[0]) && !isset($d['sorular'])) $d = array('sorular' => $d);
        wp_send_json_success(array('sorular' => isset($d['sorular']) && is_array($d['sorular']) ? $d['sorular'] : array(), 'anahtar' => isset($d['anahtar']) && is_array($d['anahtar']) ? $d['anahtar'] : array()));
    }
    private static function yetkili() { return is_user_logged_in() && (current_user_can('edit_posts') || (class_exists('OSC_Sinav') && OSC_Sinav::rol() === 'ogretmen')); }
    public function cfg() {
        if (!self::yetkili() || !OSC_YZ_API::var_mi()) return;
        echo '<script>window.OSC_PDF_YZ=' . wp_json_encode(array('u' => admin_url('admin-ajax.php'), 'n' => wp_create_nonce('osc_pdf_yz'))) . ';</script>' . "\n";
    }
    public function ajax() {
        if (!self::yetkili()) wp_send_json_error('Yetkiniz yok.', 403);
        check_ajax_referer('osc_pdf_yz', 'nonce');
        if (!OSC_YZ_API::var_mi()) wp_send_json_error('Yapay zekâ anahtarı tanımlı değil.');
        $uid = get_current_user_id(); $k = 'osc_pdfyz_' . $uid . '_' . gmdate('Ymd'); $n = (int) get_transient($k);
        if (!current_user_can('manage_options') && $n >= 120) wp_send_json_error('Bugünlük yapay zekâ ile okuma sınırına ulaşıldı.');
        set_transient($k, $n + 1, DAY_IN_SECONDS);
        $sayfa = max(1, (int) (isset($_POST['sayfa']) ? $_POST['sayfa'] : 1));
        if (isset($_POST['mod']) && $_POST['mod'] === 'yazili') $this->yazili($sayfa);
        $veri = isset($_POST['gorsel']) ? (string) wp_unslash($_POST['gorsel']) : '';
        if (!preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#', $veri, $m) || strlen($m[2]) > 6000000) wp_send_json_error('Sayfa görüntüsü okunamadı.');
        osc_uzun_islem(90);
        $prompt = "Bu görüntü bir test (çoktan seçmeli sınav) kitapçığının {$sayfa}. sayfası. Sayfadaki soruları, kitapçıkta yazdığı haliyle BİREBİR oku ve JSON döndür.\n\n"
            . "Kurallar:\n"
            . "- Sütunlu düzende önce sol sütunu yukarıdan aşağı, sonra sağ sütunu oku. Soru numaralarını kitapçıktaki gibi yaz.\n"
            . "- Üst/alt bilgi, sayfa numarası, logo, yayıncı/kurum adı, ders başlığı, filigran, 'Diğer sayfaya geçiniz' gibi soruya ait olmayan hiçbir şeyi ALMA.\n"
            . "- 'oncul': soru kökünden önce gelen okuma metni, veri, eşitlikler, maddeler (I., II., III.) gibi bilgiler. Satırları <br> ile ayır. Yoksa boş.\n"
            . "- 'kok': yalnızca asıl soru cümlesi (ör. 'Buna göre … hangisidir?'). Kalın yazılmışsa <b> kullanma, düz yaz; altı çizili sözcükleri <u> ile yaz.\n"
            . "- 'secenekler': A, B, C, D (varsa E) şıklarının metni, harf olmadan. Şıklar bir tablo gibi sütunlu verildiyse her şıkkı 'sütun başlığı: değer' biçiminde tek satırda yaz (ör. 'A: 2 · B: + · C: −4').\n"
            . "- Matematik: üsleri <sup>, altları <sub> ile yaz ((−2)<sup>3</sup>); eksi için −, çarpma için · ya da ×, bölme için : ya da ÷; kesirleri a/b biçiminde; köklü ifadeleri √ ile; kutu ve üçgen sembollerini □ △ ile yaz. LaTeX KULLANMA.\n"
            . "- 'sekiller': soruya ait her resim, çizim, grafik, sayı doğrusu, şema, geometrik şekil ya da tablo için bir eleman. 'kutu': [x0,y0,x1,y1] görüntünün sol üst köşesine göre 0-1000 arası ölçekli sınırlar; şeklin etiketleri ve yazıları da kutunun İÇİNDE kalsın, soru metni dışarıda kalsın. 'yer': 'oncul' (soru metninden önce/üstünde), 'kok' (soru cümlesiyle birlikte) ya da şık harfi ('A'…'E', şıklar resimse). Şekil yoksa boş dizi. Şeklin içindeki yazıları metne YAZMA.\n"
            . "- Sayfanın en üstündeki soru bir önceki sayfadan devam ediyorsa (numarası yoksa) 'no': 0 yaz.\n"
            . "- 'dogru': sayfada cevap anahtarı varsa doğru şık, yoksa boş. Sayfada ayrı bir cevap anahtarı tablosu varsa 'anahtar' alanına {\"1\":\"A\",…} olarak yaz.\n\n"
            . "Yalnızca şu biçimde JSON döndür:\n{\"sorular\":[{\"no\":1,\"oncul\":\"\",\"kok\":\"\",\"secenekler\":{\"A\":\"\",\"B\":\"\",\"C\":\"\",\"D\":\"\"},\"dogru\":\"\",\"sekiller\":[{\"yer\":\"oncul\",\"kutu\":[0,0,0,0]}]}],\"anahtar\":{}}";
        OSC_YZ_API::$sicaklik = 0;
        $d = OSC_YZ_API::json($prompt, 6000, 80, array(array('etiket' => 'Sayfa ' . $sayfa, 'mime' => 'image/' . $m[1], 'veri' => $m[2])));
        if (is_string($d)) wp_send_json_error($d);
        if (isset($d[0]) && !isset($d['sorular'])) $d = array('sorular' => $d);
        wp_send_json_success(array('sorular' => isset($d['sorular']) && is_array($d['sorular']) ? $d['sorular'] : array(), 'anahtar' => isset($d['anahtar']) && is_array($d['anahtar']) ? $d['anahtar'] : array()));
    }
}
/* yazılı kâğıdı / soru dosyası okuma (görüntü ya da Word metni): öncül, kök, alt maddeler, cevap ayrı */
OSC_PDF_YZ::$yazili_kural = "Kurallar:\n"
    . "- Belgede birden çok sınav kâğıdı (ör. Senaryo 1, Senaryo 2…) ya da birden çok sayfa olabilir; her soruyu ayrı bir eleman olarak ver, numaralar yeniden 1'den başlayabilir.\n"
    . "- Kâğıt başlığını, okul/ders/sınıf/dönem başlığını, 'Adı Soyadı', 'Sınıfı/No', 'Süre', 'Her soru … puan', 'Yönerge', puan notlarını (ör. (10 P), 5x2=10 puan), sayfa numaralarını, alt bilgileri, site adlarını ve bağlantıları, 'Başarılar' gibi kapanış yazılarını ALMA.\n"
    . "- 'oncul': sorudaki durum/bağlam/okuma metni/veri (soru cümlesinden önceki kısım). Yoksa boş.\n"
    . "- 'kok': yalnızca öğrenciye sorulan soru ya da yönerge cümle(ler)i.\n"
    . "- 'maddeler': soruda a), b), c) gibi alt sorular varsa her biri ayrı eleman (harfsiz); yoksa boş dizi.\n"
    . "- 'tur': 'acik' (açık uçlu), 'test' (şıklı), 'dogru_yanlis', 'bosluk'.\n"
    . "- 'secenekler' ve 'dogru': yalnızca şıklı sorularda.\n"
    . "- 'cevap': sorunun cevabı/çözümü. Cevap sorunun hemen altında ('Cevap:', 'Yanıt:') ya da belgenin sonundaki cevap anahtarında olabilir; bulduğun cevabı ilgili soruya yaz. Alt madde varsa her maddenin cevabını ayrı satırda 'a) …' biçiminde yaz. Cevap yoksa boş bırak, UYDURMA.\n"
    . "- 'kod': soruda öğrenme çıktısı/kazanım kodu yazıyorsa (ör. MAT.5.3.1, [FB.6.2.1]) aynen yaz; yoksa boş.\n"
    . "- 'sinav': sorunun ait olduğu kâğıdın sıra numarası (aynı belgede birden çok kâğıt varsa 1, 2, 3…).\n"
    . "- Matematik ifadelerini LaTeX ile \$…\$ arasında yaz (\$\\frac{3}{4}\$, \$2^{5}\$, \$\\sqrt{16}\$, \$180^{\\circ}\$). Düz sayılar için gerekmez. JSON içinde ters eğik çizgiyi bir kez kaçır (\\\\frac).\n"
    . "- Metni değiştirme, özetleme, düzeltme yapma; kâğıtta yazdığı gibi aktar.\n";
new OSC_PDF_YZ();
}
