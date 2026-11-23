
/* =====================================================================
 * TANITIM (SEO) SAYFALARI: her evrak türü ve her belirli gün için
 *   /ogretmen-evraklari/ , /ogretmen-evraklari/{evrak}/
 *   /belirli-gun-ve-haftalar/ , /belirli-gun-ve-haftalar/{gun}/
 * ===================================================================== */
if (!class_exists('OSC_Seo')) {
class OSC_Seo {
    const EV = 'ogretmen-evraklari';
    const BG = 'belirli-gun-ve-haftalar';
    private static $evrak = null; private static $bgh = null;
    public function __construct() {
        add_action('template_redirect', array($this, 'yonlendir'), 6);
        add_action('init', array($this, 'harita'), 20);
    }
    public static function evraklar() {
        if (self::$evrak === null) {
            self::$evrak = array(); $acik = class_exists('OSC_Evrak') ? OSC_Evrak::acik_turler() : null;
            foreach (json_decode('/*__SEO_EVRAK__*/', true) as $e) if ($acik === null || in_array($e['id'], $acik, true)) { $e['slug'] = sanitize_title(remove_accents(preg_replace('/\s*\(.*?\)\s*/u', ' ', $e['ad']))); self::$evrak[$e['slug']] = $e; }
        }
        return self::$evrak;
    }
    public static function gunler() {
        if (self::$bgh === null) { self::$bgh = array(); foreach (json_decode('/*__SEO_BGH__*/', true) as $i => $g) { $s = sanitize_title(remove_accents(preg_replace('/^[\d\s\.\-–]+(Ocak|Şubat|Mart|Nisan|Mayıs|Haziran|Temmuz|Ağustos|Eylül|Ekim|Kasım|Aralık)?\s*/u', '', $g[1]))); if ($s === '' || isset(self::$bgh[$s])) $s = sanitize_title(remove_accents($g[1])); self::$bgh[$s] = array('i' => $i, 'tarih' => $g[0], 'ad' => $g[1], 'tur' => $g[2], 'oneri' => $g[3], 'metin' => $g[4], 'slug' => $s); } }
        return self::$bgh;
    }
    private static function arac_url($args) {
        if (!class_exists('OSC_Evrak')) return add_query_arg(array_map('rawurlencode', $args), home_url('/'));
        $u = OSC_Evrak::url(); if (!empty($args['evrak'])) { $u = OSC_Evrak::tur_url($args['evrak']); unset($args['evrak']); }
        return $args ? add_query_arg(array_map('rawurlencode', $args), $u) : $u;
    }
    public function yonlendir() {
        if (!is_404()) return; $y = OSC_Seo_Yol::yol();
        if ($y === self::EV) $this->ev_index();
        elseif (strpos($y, self::EV . '/') === 0) { $e = self::evraklar(); $s = substr($y, strlen(self::EV) + 1); if (isset($e[$s])) $this->ev_sayfa($e[$s]); }
        elseif ($y === self::BG) $this->bg_index();
        elseif (strpos($y, self::BG . '/') === 0) { $g = self::gunler(); $s = substr($y, strlen(self::BG) + 1); if (isset($g[$s])) $this->bg_sayfa($g[$s]); }
    }
    private static function stil() { return '<style>
.sx-s{--i:#13304F;--i2:#5B7189;--c:#E3EBF3;font-family:Figtree,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--i);max-width:1060px;margin:0 auto;padding:22px 16px 60px}.sx-s *{box-sizing:border-box}.sx-s a{text-decoration:none}
.sx-kir{font-size:13px;color:var(--i2);margin-bottom:12px}.sx-kir a{color:var(--i2)}.sx-kir a:hover{color:#2F80ED}
.sxs-hero{position:relative;overflow:hidden;padding:34px 30px;border-radius:26px;background:linear-gradient(135deg,#EAF4FC,#F5F9FE 60%,#EEF7F1);border:1px solid #DCEAF7}
.sxs-hero .ik{font-size:44px;line-height:1}.sxs-hero h1{margin:12px 0 0 !important;font-size:clamp(26px,3.6vw,38px);line-height:1.15;letter-spacing:-.02em;font-weight:800;color:var(--i)}
.sxs-hero p{margin:10px 0 0 !important;font-size:17px;line-height:1.6;color:#46607A;max-width:720px}
.sx-s .sx-cta{display:inline-flex;align-items:center;gap:8px;margin-top:20px;background:#2F80ED;color:#fff;font-weight:700;font-size:16px;padding:14px 22px;border-radius:14px;box-shadow:0 14px 28px -16px #2F80ED}.sx-s .sx-cta:hover{background:#1F6FD8;color:#fff}
.sx-rozet{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}.sx-rozet span{padding:6px 12px;border-radius:99px;background:#fff;border:1px solid #DCEAF7;font-size:13px;font-weight:600;color:#46607A}
.sx-iki{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:18px;margin-top:20px;align-items:start}
.sx-k{padding:22px;border-radius:22px;background:#fff;border:1px solid var(--c)}.sx-k h2{margin:0 0 12px !important;font-size:20px;color:var(--i)}.sx-k p,.sx-k li{font-size:15.5px;line-height:1.7;color:#334E68}
.sx-adim{counter-reset:a;list-style:none;padding:0 !important;margin:0 !important;display:grid;gap:10px}.sx-adim li{counter-increment:a;display:flex;gap:12px;align-items:flex-start}.sx-adim li:before{content:counter(a);flex:0 0 30px;height:30px;border-radius:50%;background:#EAF2FD;color:#2F80ED;font-weight:800;display:grid;place-items:center}
.sx-ok{list-style:none;padding:0 !important;margin:0 !important}.sx-ok li{padding-left:28px;position:relative;margin-bottom:6px}.sx-ok li:before{content:"✓";position:absolute;left:0;top:0;width:20px;height:20px;border-radius:50%;background:#E9F7EF;color:#15803D;font-weight:800;font-size:12px;display:grid;place-items:center;margin-top:3px}
.sx-sss details{border-top:1px solid var(--c);padding:12px 0}.sx-sss details:first-of-type{border-top:0}.sx-sss summary{font-weight:700;cursor:pointer;font-size:15.5px}.sx-sss p{margin:8px 0 0 !important}
.sx-izg{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px}.sx-s .sx-o{display:flex;gap:12px;align-items:center;padding:14px;border-radius:16px;background:#fff;border:1px solid var(--c);color:var(--i);transition:border-color .15s,transform .15s}.sx-s .sx-o:hover{border-color:#C9DCF0;transform:translateY(-2px);color:var(--i)}
.sx-o span{font-size:24px;flex:0 0 auto}.sx-o b{display:block;font-size:14.5px;line-height:1.35}.sx-o small{display:block;color:var(--i2);font-size:12.5px;margin-top:2px;line-height:1.4}
.sx-ay{margin-top:24px}.sx-ay h2{font-size:19px;margin:0 0 10px !important;display:flex;gap:10px;align-items:center}.sx-ay h2:after{content:"";flex:1;height:1px;background:var(--c)}
.sx-metin{font-size:16.5px;line-height:1.8;color:#1C3653;white-space:pre-wrap}
.sx-tur{display:inline-block;padding:4px 11px;border-radius:99px;font-size:12.5px;font-weight:700}.sx-tur.b{background:#FEE2E2;color:#B91C1C}.sx-tur.a{background:#EEF2F7;color:#334155}.sx-tur.g{background:#EAF2FD;color:#2F80ED}
.sx-gez{display:flex;justify-content:space-between;gap:10px;margin-top:18px;flex-wrap:wrap}.sx-gez a{flex:1;min-width:220px;padding:14px 16px;border-radius:16px;background:#fff;border:1px solid var(--c);color:var(--i);font-weight:600}.sx-gez a small{display:block;color:var(--i2);font-weight:500;font-size:12px}
@media(max-width:820px){.sx-iki{grid-template-columns:1fr}.sxs-hero{padding:26px 18px}}
</style>'; }
    private static function kir($l) { $h = '<nav class="sx-kir"><a href="' . esc_url(home_url('/')) . '">Ana sayfa</a>'; $ld = array(array('@type' => 'ListItem', 'position' => 1, 'name' => 'Ana sayfa', 'item' => home_url('/'))); foreach ($l as $i => $x) { $h .= ' › ' . ($x[1] ? '<a href="' . esc_url($x[1]) . '">' . esc_html($x[0]) . '</a>' : esc_html($x[0])); $ld[] = array('@type' => 'ListItem', 'position' => $i + 2, 'name' => $x[0], 'item' => $x[1] ? $x[1] : null); } return array($h . '</nav>', array('@type' => 'BreadcrumbList', 'itemListElement' => $ld)); }
    private static function yil() { $y = (int) wp_date('Y'); return (int) wp_date('n') >= 7 ? $y . '-' . ($y + 1) : ($y - 1) . '-' . $y; }
    public function ev_index() {
        list($kh, $kl) = self::kir(array(array('Öğretmen evrakları', '')));
        $h = self::stil() . '<div class="sx-s">' . $kh . '<section class="sxs-hero"><div class="ik">🗂️</div><h1>Öğretmen evrakları ' . self::yil() . '</h1><p>Zümre, ŞÖK ve veli toplantısı tutanakları, BEP planı, yıllık ve günlük planlar, kulüp evrakları, dilekçeler ve daha fazlası. Bilgilerinizi girin, taslağı düzenleyin, Word olarak indirin. Tamamı ücretsiz.</p><a class="sx-cta" href="' . esc_url(self::arac_url(array())) . '">Evrak hazırlamaya başla →</a></section><div class="sx-ay"><h2>Tüm evraklar</h2><div class="sx-izg">';
        foreach (self::evraklar() as $e) $h .= '<a class="sx-o" href="' . esc_url(home_url('/' . self::EV . '/' . $e['slug'] . '/')) . '"><span>' . $e['ik'] . '</span><span style="font-size:inherit"><b>' . esc_html($e['ad']) . '</b><small>' . esc_html($e['ac']) . '</small></span></a>';
        $h .= '</div></div><div class="sx-ay"><h2>Belirli gün ve haftalar</h2><p style="color:#5B7189">70 belirli gün ve hafta için anlam ve önem yazıları, etkinlik önerileri ve hazır kutlama programları. <a href="' . esc_url(home_url('/' . self::BG . '/')) . '" style="color:#2F80ED;font-weight:700">Tümünü gör →</a></p></div></div>';
        self::sayfa('Öğretmen Evrakları ' . self::yil() . ' – Ücretsiz Hazırla, Word İndir', 'Zümre, ŞÖK, veli toplantısı tutanakları, BEP planı, yıllık plan, günlük plan, kulüp evrakları ve dilekçeleri ücretsiz hazırlayın ve Word olarak indirin.', $h, home_url('/' . self::EV . '/'), array('@context' => 'https://schema.org', '@graph' => array($kl)));
    }
    public function ev_sayfa($e) {
        $ad = $e['ad']; $u = self::arac_url(array('evrak' => $e['id'])); $yil = self::yil();
        list($kh, $kl) = self::kir(array(array('Öğretmen evrakları', home_url('/' . self::EV . '/')), array($ad, '')));
        $sss = array(
            array($ad . ' ücretsiz mi?', 'Evet. ' . $ad . ' Öğretmen Masası’nda tamamen ücretsiz hazırlanır ve Word (.docx) olarak indirilir. Üye olmanız gerekmez; üye olursanız okul ve müdür bilgileriniz tüm evraklarınıza otomatik işlenir.'),
            array('İndirdiğim belgeyi düzenleyebilir miyim?', 'Evet. Taslağı ekranda doğrudan düzenleyebilir, indirdikten sonra Word’de istediğiniz değişikliği yapabilirsiniz. Okul logosu, imza ve onay bölümleri hazır gelir.'),
            array('Öğrenci bilgileri kaydediliyor mu?', 'Hayır. Öğrenci adı, not ve görüş gibi kişisel veriler yalnızca kendi tarayıcınızda tutulur; sunucumuza gönderilmez. Böylece KVKK’ya uygun çalışırsınız.'),
            array($ad . ' güncel mi?', $yil . ' eğitim-öğretim yılı çalışma takvimi, Türkiye Yüzyılı Maarif Modeli ve güncel MEB yönetmelikleri esas alınarak hazırlanır.'),
        );
        $diger = ''; $n = 0; foreach (self::evraklar() as $x) { if ($x['id'] === $e['id'] || $n++ >= 8) continue; $diger .= '<a class="sx-o" href="' . esc_url(home_url('/' . self::EV . '/' . $x['slug'] . '/')) . '"><span>' . $x['ik'] . '</span><span style="font-size:inherit"><b>' . esc_html($x['ad']) . '</b></span></a>'; }
        $h = self::stil() . '<div class="sx-s">' . $kh . '<section class="sxs-hero"><div class="ik">' . $e['ik'] . '</div><h1>' . esc_html($ad) . ' hazırla (' . $yil . ')</h1><p>' . esc_html($e['ac']) . '. Bilgileri doldurun; taslak saniyeler içinde hazır olsun, ekranda düzenleyip Word olarak indirin.</p><div class="sx-rozet"><span>✓ Ücretsiz</span><span>✓ Word (.docx) olarak indir</span><span>✓ ' . $yil . ' güncel</span><span>🔒 Öğrenci bilgisi sunucuya gitmez</span></div><a class="sx-cta" href="' . esc_url($u) . '">' . esc_html($ad) . ' hazırla →</a></section>';
        $h .= '<div class="sx-iki"><div style="display:grid;gap:18px"><section class="sx-k"><h2>Nasıl hazırlanır?</h2><ol class="sx-adim"><li><span><b>Evrakı açın.</b> “' . esc_html($ad) . ' hazırla” düğmesiyle form doğrudan açılır.</span></li><li><span><b>Bilgileri girin.</b> Okul, sınıf, tarih ve gündem gibi alanları doldurun; üyeyseniz okul ve müdür bilgileri kendiliğinden gelir.</span></li><li><span><b>Taslağı düzenleyin.</b> Metinler mevzuata uygun hazır gelir; dilediğiniz cümleye tıklayıp değiştirin.</span></li><li><span><b>Word olarak indirin.</b> Çıktı alıp imzaya sunun ya da e-posta ile gönderin.</span></li></ol></section>';
        $h .= '<section class="sx-k sx-sss"><h2>Sık sorulan sorular</h2>' . implode('', array_map(function ($x) { return '<details><summary>' . esc_html($x[0]) . '</summary><p>' . esc_html($x[1]) . '</p></details>'; }, $sss)) . '</section></div>';
        $h .= '<aside class="sx-k"><h2>Neden Öğretmen Masası?</h2><ul class="sx-ok"><li>Hazır, mevzuata uygun metinler: boş sayfadan başlamazsınız.</li><li>Okul, il, müdür ve öğretmen bilgileri tüm evraklarınıza bir kez yazılır.</li><li>Evraklarım bölümünde kaydettiğiniz belgelere istediğiniz zaman dönersiniz.</li><li>Telefon, tablet ve bilgisayarda çalışır.</li></ul><a class="sx-cta" style="width:100%;justify-content:center" href="' . esc_url($u) . '">Hemen başla →</a></aside></div>';
        $h .= '<div class="sx-ay"><h2>Diğer öğretmen evrakları</h2><div class="sx-izg">' . $diger . '</div></div></div>';
        $ld = array('@context' => 'https://schema.org', '@graph' => array($kl, array('@type' => 'FAQPage', 'mainEntity' => array_map(function ($x) { return array('@type' => 'Question', 'name' => $x[0], 'acceptedAnswer' => array('@type' => 'Answer', 'text' => $x[1])); }, $sss)), array('@type' => 'HowTo', 'name' => $ad . ' nasıl hazırlanır?', 'step' => array(array('@type' => 'HowToStep', 'text' => 'Evrak formunu açın.'), array('@type' => 'HowToStep', 'text' => 'Okul, sınıf, tarih ve gündem bilgilerini girin.'), array('@type' => 'HowToStep', 'text' => 'Hazır taslağı düzenleyin.'), array('@type' => 'HowToStep', 'text' => 'Word olarak indirin.')))));
        self::sayfa($ad . ' ' . $yil . ' – Ücretsiz Hazırla, Word İndir', $ad . ' ' . $yil . ': ' . $e['ac'] . '. Ücretsiz hazırlayın, düzenleyin ve Word olarak indirin.', $h, home_url('/' . self::EV . '/' . $e['slug'] . '/'), $ld);
    }
    private static $aylar = array('Eylül', 'Ekim', 'Kasım', 'Aralık', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran');
    private static function ay($t) { foreach (self::$aylar as $a) if (mb_stripos($t, $a, 0, 'UTF-8') !== false) return $a; return 'Diğer'; }
    private static $turad = array('b' => 'Millî bayram', 'a' => 'Anma günü', 'g' => 'Belirli gün / hafta');
    public function bg_index() {
        list($kh, $kl) = self::kir(array(array('Belirli gün ve haftalar', '')));
        $gr = array(); foreach (self::gunler() as $g) $gr[self::ay($g['tarih'])][] = $g;
        $h = self::stil() . '<div class="sx-s">' . $kh . '<section class="sxs-hero"><div class="ik">🎗️</div><h1>Belirli gün ve haftalar ' . self::yil() . '</h1><p>MEB listesindeki ' . count(self::gunler()) . ' belirli gün ve hafta: tarihleri, anlam ve önemi, sınıfta yapılabilecek etkinlikler ve tek tıkla hazırlanan kutlama / anma programları.</p><a class="sx-cta" href="' . esc_url(self::arac_url(array('evrak' => 'belirli_gun'))) . '">Kutlama programı hazırla →</a></section>';
        foreach (array_merge(self::$aylar, array('Diğer')) as $a) { if (empty($gr[$a])) continue; $h .= '<div class="sx-ay"><h2>' . esc_html($a) . '</h2><div class="sx-izg">'; foreach ($gr[$a] as $g) $h .= '<a class="sx-o" href="' . esc_url(home_url('/' . self::BG . '/' . $g['slug'] . '/')) . '"><span>' . ($g['tur'] === 'b' ? '🇹🇷' : ($g['tur'] === 'a' ? '🕯️' : '📅')) . '</span><span style="font-size:inherit"><b>' . esc_html($g['ad']) . '</b><small>' . esc_html($g['tarih']) . '</small></span></a>'; $h .= '</div></div>'; }
        $h .= '</div>';
        self::sayfa('Belirli Gün ve Haftalar ' . self::yil() . ' – Anlam ve Önemi, Etkinlikler', 'Belirli gün ve haftalar listesi ' . self::yil() . ': tarihleri, anlam ve önemi yazıları, etkinlik önerileri ve hazır kutlama programları.', $h, home_url('/' . self::BG . '/'), array('@context' => 'https://schema.org', '@graph' => array($kl)));
    }
    public function bg_sayfa($g) {
        $l = array_values(self::gunler()); $i = array_search($g['slug'], array_column($l, 'slug'), true); $onc = $i > 0 ? $l[$i - 1] : null; $snr = $i < count($l) - 1 ? $l[$i + 1] : null;
        $u = self::arac_url(array('evrak' => 'belirli_gun', 'gun' => $g['ad']));
        list($kh, $kl) = self::kir(array(array('Belirli gün ve haftalar', home_url('/' . self::BG . '/')), array($g['ad'], '')));
        $h = self::stil() . '<div class="sx-s">' . $kh . '<section class="sxs-hero"><span class="sx-tur ' . esc_attr($g['tur']) . '">' . esc_html(self::$turad[$g['tur']] ?? 'Belirli gün') . '</span><h1>' . esc_html($g['ad']) . '</h1><p>📅 ' . esc_html($g['tarih']) . ' · Anlam ve önemi, etkinlik önerileri ve hazır kutlama / anma programı</p><a class="sx-cta" href="' . esc_url($u) . '">Bu gün için program hazırla →</a></section>';
        $h .= '<div class="sx-iki"><section class="sx-k"><h2>' . esc_html($g['ad']) . ' anlam ve önemi</h2><div class="sx-metin">' . esc_html($g['metin']) . '</div></section><aside style="display:grid;gap:18px"><section class="sx-k"><h2>Etkinlik önerileri</h2><ul class="sx-ok">' . implode('', array_map(function ($x) { return '<li>' . esc_html(trim($x)) . '</li>'; }, array_filter(preg_split('/\s*;\s*/u', $g['oneri'])))) . '</ul></section><section class="sx-k"><h2>Hazır program</h2><p>Program akışı, görevliler, şiir ve gösteri sırası ile konuşma metni hazır gelir; düzenleyip Word olarak indirin.</p><a class="sx-cta" style="width:100%;justify-content:center" href="' . esc_url($u) . '">Programı hazırla →</a></section></aside></div>';
        $h .= '<nav class="sx-gez">' . ($onc ? '<a href="' . esc_url(home_url('/' . self::BG . '/' . $onc['slug'] . '/')) . '"><small>‹ Önceki</small>' . esc_html($onc['ad']) . '</a>' : '') . ($snr ? '<a href="' . esc_url(home_url('/' . self::BG . '/' . $snr['slug'] . '/')) . '" style="text-align:right"><small>Sonraki ›</small>' . esc_html($snr['ad']) . '</a>' : '') . '</nav></div>';
        $ld = array('@context' => 'https://schema.org', '@graph' => array($kl, array('@type' => 'Article', 'headline' => $g['ad'] . ' anlam ve önemi', 'description' => wp_trim_words($g['metin'], 30), 'inLanguage' => 'tr', 'mainEntityOfPage' => home_url('/' . self::BG . '/' . $g['slug'] . '/'), 'publisher' => array('@type' => 'Organization', 'name' => get_bloginfo('name')))));
        self::sayfa($g['ad'] . ' – Anlam ve Önemi, Etkinlikler (' . self::yil() . ')', $g['metin'], $h, home_url('/' . self::BG . '/' . $g['slug'] . '/'), $ld);
    }
    /** WordPress site haritasına ekle (wp-sitemap.xml açıksa) */
    public function harita() { if (function_exists('wp_register_sitemap_provider') && class_exists('OSC_Seo_Harita')) wp_register_sitemap_provider('ogretmen', new OSC_Seo_Harita()); }
    public static function sayfa($baslik, $aciklama, $html, $kanonik, $ld) {
        if (class_exists('OSC_Tk')) OSC_Tk::sayfa($baslik, $aciklama, $html, $kanonik, $ld);
        global $wp_query; $wp_query->is_404 = false; status_header(200);
        add_filter('pre_get_document_title', function () use ($baslik) { return $baslik . ' | ' . get_bloginfo('name'); }, 99);
        add_action('wp_head', function () use ($aciklama, $kanonik, $ld) { echo '<meta name="description" content="' . esc_attr(wp_trim_words($aciklama, 30, '…')) . '"><link rel="canonical" href="' . esc_url($kanonik) . '"><script type="application/ld+json">' . wp_json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>'; }, 1);
        get_header(); echo $html; get_footer(); exit;
    }
}
if (class_exists('WP_Sitemaps_Provider') && !class_exists('OSC_Seo_Harita')) {
class OSC_Seo_Harita extends WP_Sitemaps_Provider {
    public function __construct() { $this->name = 'ogretmen'; $this->object_type = 'ogretmen'; }
    public function get_url_list($p, $s = '') {
        if ($p > 1) return array();
        $l = array(array('loc' => home_url('/' . OSC_Seo::EV . '/')), array('loc' => home_url('/' . OSC_Seo::BG . '/')));
        if (class_exists('OSC_Forum')) $l[] = array('loc' => home_url('/soru-cevap/'));
        if (function_exists('si_hub_url')) $l[] = array('loc' => si_hub_url());
        foreach (OSC_Seo::evraklar() as $e) $l[] = array('loc' => home_url('/' . OSC_Seo::EV . '/' . $e['slug'] . '/'));
        if (class_exists('OSC_Ekders')) $l[] = array('loc' => OSC_Ekders::url());
        if (class_exists('OSC_Evrak') && get_option('osc_ev_sayfa')) { $l[] = array('loc' => OSC_Evrak::url()); foreach (OSC_Evrak::acik_turler() as $t) if (isset(OSC_Evrak::$yollar[$t])) $l[] = array('loc' => OSC_Evrak::tur_url($t)); }
        foreach (OSC_Seo::gunler() as $g) $l[] = array('loc' => home_url('/' . OSC_Seo::BG . '/' . $g['slug'] . '/'));
        return $l;
    }
    public function get_max_num_pages($s = '') { return 1; }
}
}
class OSC_Seo_Yol { public static function yol() { return isset($_SERVER['REQUEST_URI']) ? trim(strtok(wp_unslash($_SERVER['REQUEST_URI']), '?'), '/') : ''; } }
new OSC_Seo();
}
