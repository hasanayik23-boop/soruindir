/* =====================================================================
 * Öğretmen Masası: Ek Ders Ücreti Hesaplama  (/ek-ders-hesaplama/)
 * Hesap tamamen tarayıcıda yapılır; hiçbir veri sunucuya gönderilmez.
 * ===================================================================== */
if (!class_exists('OSC_Ekders')) {
class OSC_Ekders {
    const YOL = 'ek-ders-hesaplama';
    public static $kullan = false;
    public function __construct() {
        add_action('admin_menu', array($this, 'menu'), 40);
        add_action('admin_init', array($this, 'ayar_kayit'));
        add_action('init', function () { add_shortcode('ek_ders_hesaplama', array('OSC_Ekders', 'kisa_kod')); add_shortcode('ek_ders_hesaplama_modern', array('OSC_Ekders', 'kisa_kod')); }, 30);
        add_action('template_redirect', array($this, 'tam_sayfa'), 7);
        add_action('wp_footer', array($this, 'varlik'), 5);
    }
    public static function url() { return home_url('/' . self::YOL . '/'); }
    public static function varsayilan() {
        return array('donem' => 'Temmuz–Aralık 2026', 'katsayi' => '1,575512',
            'gv_aylar' => '4.211,33; 4.211,33; 4.211,33; 4.211,33; 4.211,33; 4.211,33; 4.537,75; 5.615,10; 5.615,10; 5.615,10; 5.615,10; 5.615,10',
            'dv_istisna' => '250,70', 'dilimler' => '190.000 / 400.000 / 1.500.000 / 5.300.000', 'sss' => 1);
    }
    public static function sayi($v) {
        $v = trim((string) $v);
        if (strpos($v, ',') !== false) $v = str_replace(array('.', ','), array('', '.'), $v);
        elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $v)) $v = str_replace('.', '', $v);
        return (float) $v;
    }
    public static function ayar($k) {
        $a = wp_parse_args((array) get_option('edh_ayar', array()), self::varsayilan());
        if ($k === 'donem') return (string) $a['donem'];
        if ($k === 'sss') return (int) $a['sss'];
        if ($k === 'dilimler') { $d = array_values(array_filter(array_map(array(__CLASS__, 'sayi'), explode('/', (string) $a['dilimler'])))); return count($d) === 4 ? $d : array(190000, 400000, 1500000, 5300000); }
        if ($k === 'gv_aylar') { $d = array_map(array(__CLASS__, 'sayi'), explode(';', (string) $a['gv_aylar'])); return count($d) === 12 ? $d : array_fill(0, 12, 5615.10); }
        return self::sayi($a[$k]);
    }
    public static function tl($n) { return number_format((float) $n, 2, ',', '.') . ' TL'; }

    /* ---------- yönetim: Öğretmen Masası › Ek Ders Hesaplama ---------- */
    public function menu() { add_submenu_page('osc-ogretmen-masasi', 'Ek Ders Hesaplama', 'Ek Ders Hesaplama', 'manage_options', 'osc-ek-ders', array($this, 'ayar_sayfa')); }
    public function ayar_kayit() {
        register_setting('osc_edh', 'edh_ayar', array('sanitize_callback' => function ($v) {
            $v = (array) $v; $t = array();
            foreach (OSC_Ekders::varsayilan() as $k => $vars) { if ($k === 'sss') { $t[$k] = empty($v[$k]) ? 0 : 1; continue; } $t[$k] = isset($v[$k]) && trim($v[$k]) !== '' ? sanitize_text_field($v[$k]) : $vars; }
            if (OSC_Ekders::sayi($t['katsayi']) <= 0) { add_settings_error('edh_ayar', 'katsayi', 'Geçersiz katsayı; eski değer korundu.'); $eski = wp_parse_args((array) get_option('edh_ayar', array()), OSC_Ekders::varsayilan()); $t['katsayi'] = $eski['katsayi']; }
            return $t;
        }));
    }
    public function ayar_sayfa() {
        if (!current_user_can('manage_options')) return;
        $a = wp_parse_args((array) get_option('edh_ayar', array()), self::varsayilan());
        $alan = function ($ad, $baslik, $acik, $gen = '160px') use ($a) { echo '<tr><th scope="row">' . esc_html($baslik) . '</th><td><input type="text" name="edh_ayar[' . esc_attr($ad) . ']" value="' . esc_attr($a[$ad]) . '" style="width:' . esc_attr($gen) . ';max-width:100%"><p class="description">' . wp_kses_post($acik) . '</p></td></tr>'; };
        echo '<div class="wrap"><h1>Ek Ders Ücreti Hesaplama</h1>'; settings_errors('edh_ayar');
        echo '<p>Araç <a href="' . esc_url(self::url()) . '" target="_blank">' . esc_html(self::url()) . '</a> adresinde yayında; Öğretmen Masası ve Hesaplamalar sayfasından bağlantı verilir. İsterseniz başka bir sayfaya <code>[ek_ders_hesaplama]</code> kısa kodunu da ekleyebilirsiniz. Memur aylık katsayısı her yıl <strong>Ocak</strong> ve <strong>Temmuz</strong>’da değişir; genelge yayımlanınca aşağıdan güncelleyin.</p>';
        echo '<form method="post" action="options.php" style="background:#fff;border:1px solid #c3c4c7;padding:4px 20px 12px;max-width:900px">'; settings_fields('osc_edh'); echo '<table class="form-table" role="presentation">';
        $alan('donem', 'Dönem adı', 'Araçta görünür. Örnek: Ocak–Haziran 2027', '260px');
        $alan('katsayi', 'Memur aylık katsayısı', 'Temmuz–Aralık 2026: 1,575512');
        $alan('gv_aylar', 'Aylık gelir vergisi istisnaları', 'Ocak’tan Aralık’a 12 ayın asgari ücret gelir vergisi istisnası, aralarında “;” ile. 2026: Ocak–Haziran 4.211,33 · Temmuz 4.537,75 · Ağustos–Aralık 5.615,10', '100%');
        $alan('dv_istisna', 'Damga vergisi istisnası', '2026: 250,70 TL');
        $alan('dilimler', 'Gelir vergisi dilimleri', '%15 / %20 / %27 / %35 / %40 dilimlerinin sınırları, aralarında “/” ile. 2026: 190.000 / 400.000 / 1.500.000 / 5.300.000', '320px');
        echo '<tr><th scope="row">Sık sorulan sorular</th><td><label><input type="checkbox" name="edh_ayar[sss]" value="1" ' . checked((int) $a['sss'], 1, false) . '> Aracın altında kısa “Sık sorulan sorular” bölümü görünsün</label></td></tr></table>';
        submit_button('Kaydet'); echo '</form></div>';
    }

    /* ---------- tam sayfa: /ek-ders-hesaplama/ ---------- */
    public function tam_sayfa() {
        $yol = isset($_SERVER['REQUEST_URI']) ? trim((string) strtok(wp_unslash($_SERVER['REQUEST_URI']), '?'), '/') : '';
        if ($yol !== self::YOL || !is_404()) return;
        global $wp_query; $wp_query->is_404 = false; status_header(200);
        $k = self::ayar('katsayi'); $donem = self::ayar('donem'); $yil = wp_date('Y');
        $bas = 'Ek Ders Ücreti Hesaplama ' . $yil . ' – Net Ek Ders Hesapla (' . $donem . ')';
        $ac = 'Öğretmenler için ' . $donem . ' güncel ek ders ücreti hesaplama: gündüz ' . self::tl(140 * $k) . ', gece ' . self::tl(150 * $k) . ' brüt. Kadrolu, sözleşmeli, ücretli ve yönetici; DYK, nöbet, özel eğitim; vergi kesintileriyle net tutar.';
        $kan = self::url();
        add_filter('pre_get_document_title', function () use ($bas) { return $bas . ' | ' . get_bloginfo('name'); }, 99);
        add_filter('wpseo_title', function () use ($bas) { return $bas . ' | ' . get_bloginfo('name'); }, 99); add_filter('rank_math/frontend/title', function () use ($bas) { return $bas . ' | ' . get_bloginfo('name'); }, 99);
        remove_action('wp_head', 'rel_canonical');
        add_action('wp_head', function () use ($ac, $kan, $bas) {
            echo '<meta name="description" content="' . esc_attr($ac) . '">' . "\n" . '<link rel="canonical" href="' . esc_url($kan) . '">' . "\n" . '<meta property="og:title" content="' . esc_attr($bas) . '"><meta property="og:url" content="' . esc_url($kan) . '"><meta property="og:description" content="' . esc_attr($ac) . '">' . "\n";
            echo '<script type="application/ld+json">' . wp_json_encode(array('@context' => 'https://schema.org', '@type' => 'WebApplication', 'name' => 'Ek Ders Ücreti Hesaplama', 'url' => $kan, 'description' => $ac, 'applicationCategory' => 'FinanceApplication', 'operatingSystem' => 'Web', 'inLanguage' => 'tr', 'offers' => array('@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'TRY')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        }, 2);
        get_header();
        echo '<main class="edh-sayfa" style="max-width:1180px;margin:0 auto;padding:22px 16px 40px;font-family:Figtree,\'Plus Jakarta Sans\',system-ui,-apple-system,\'Segoe UI\',Roboto,sans-serif">';
        if (class_exists('OSC_Masa')) echo OSC_Masa::kirinti('Ek Ders Hesaplama');
        echo '<h1 style="margin:0 0 6px;font-size:clamp(26px,3.4vw,36px);line-height:1.15;letter-spacing:-.02em;font-weight:800;color:#0f172a">Ek Ders Ücreti Hesaplama ' . esc_html($yil) . '</h1>';
        echo '<p style="margin:0 0 18px;color:#475569;font-size:16px;line-height:1.6;max-width:760px">Kadrolu, sözleşmeli, ücretli öğretmen ve yöneticiler için güncel katsayıyla brüt ve net ek ders ücreti. Hesap tarayıcınızda yapılır, girdiğiniz bilgiler hiçbir yere gönderilmez.</p>';
        echo self::kisa_kod();
        echo '</main>';
        get_footer(); exit;
    }
    /** stil ve betik yalnızca araç kullanılan sayfada, sayfanın içine gömülü */
    public function varlik() {
        if (!self::$kullan) return;
        echo '<style id="edh-css">' . self::CSS . '</style><script id="edh-js">' . self::JS . '</script>';
    }
    const CSS = '/*__EDH_CSS__*/';
    const JS = '/*__EDH_JS__*/';

	public static function kisa_kod() {
	self::$kullan = true;
	$k = self::ayar( 'katsayi' );
	$donem = self::ayar( 'donem' );

	$veri = array(
		'k'        => $k,
		'gvAylar'  => self::ayar( 'gv_aylar' ),
		'dvIst'    => self::ayar( 'dv_istisna' ),
		'dilimler' => self::ayar( 'dilimler' ),
		'ay'       => (int) wp_date( 'n' ),
	);

	// Saat alanları: ad => [etiket, açıklama, gösterge, çarpan, lisansüstü (ders|edyg|''), ücretli ilavesine esas mı, kimlere]
	$gruplar = array(
		'ders' => array( 'Ders ve görevler', 'fa-chalkboard', array(
			'gunduz'   => array( 'Gündüz ek ders', 'Hafta içi gündüz girilen dersler', 140, 1, 'ders', 1, '' ),
			'gece'     => array( 'Gece / hafta sonu ek ders', 'Saat 18.00 sonrası ve hafta sonu', 150, 1, 'ders', 1, '' ),
			'edygg'    => array( 'Ek ders yerine geçen görev (gündüz)', 'Yönetim, rehberlik, belleticilik dışı görevler', 140, 1, 'edyg', 0, 'kadrolu yonetici sozlesmeli' ),
			'edygn'    => array( 'Ek ders yerine geçen görev (gece)', 'Gece / hafta sonu yürütülen görevler', 150, 1, 'edyg', 0, 'kadrolu yonetici sozlesmeli' ),
			'nobet'    => array( 'Nöbet', 'Haftada en fazla 3 saat', 140, 1, '', 0, '' ),
			'bellet'   => array( 'Belleticilik', 'Yatılı okullarda', 140, 1, '', 0, '' ),
			'egzersiz' => array( 'Egzersiz (sosyal etkinlik)', 'Ders dışı egzersiz çalışmaları', 140, 1, '', 0, '' ),
			'sinav'    => array( 'Okul sınav görevi', 'Sorumluluk, beceri sınavları vb.', 140, 1, '', 0, '' ),
		) ),
		'ozel' => array( 'Özel eğitim (%25 fazla)', 'fa-hand-holding-heart', array(
			'ozg'      => array( 'Gündüz ek ders', 'Özel eğitim sınıfı / okulu', 140, 1.25, 'ders', 1, '' ),
			'ozn'      => array( 'Gece / hafta sonu ek ders', '', 150, 1.25, 'ders', 1, '' ),
			'oznobet'  => array( 'Nöbet', '', 140, 1.25, '', 0, '' ),
		) ),
		'kurs' => array( 'Kurslar', 'fa-book-open', array(
			'dykg'     => array( 'DYK – gündüz', 'Destekleme ve yetiştirme kursu (%100 fazla)', 140, 2, 'ders', 1, '' ),
			'dykn'     => array( 'DYK – gece / hafta sonu', '%100 fazla ödenir', 150, 2, 'ders', 1, '' ),
			'iyepg'    => array( 'İYEP – gündüz', 'İlkokullarda yetiştirme programı', 140, 1, 'ders', 1, '' ),
			'iyepn'    => array( 'İYEP – gece / hafta sonu', '', 150, 1, 'ders', 1, '' ),
		) ),
	);
	$aylar = array( 1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık' );

	ob_start();
	?>
	<div class="edh" data-edh="<?php echo esc_attr( wp_json_encode( $veri ) ); ?>">
		<div class="edh-ust">
			<div class="edh-ust__baslik">
				<span class="edh-rozet"><?php echo esc_html( $donem ); ?></span>
				<p>Kadronuzu seçin, bu ayki saatlerinizi girin; <strong>net ek ders ücretiniz</strong> anında hesaplansın.</p>
			</div>
			<div class="edh-ust__fiyat">
				<div><span>Gündüz saat ücreti</span><strong><?php echo esc_html( self::tl( 140 * $k ) ); ?></strong></div>
				<div><span>Gece saat ücreti</span><strong><?php echo esc_html( self::tl( 150 * $k ) ); ?></strong></div>
				<div><span>Katsayı</span><strong><?php echo esc_html( number_format( $k, 6, ',', '.' ) ); ?></strong></div>
			</div>
		</div>

		<form class="edh-govde" onsubmit="return false" novalidate>
			<div class="edh-sol">
				<section class="edh-kart">
					<h3 class="edh-kart__t"><span class="edh-no">1</span> Kadro ve bilgiler</h3>
					<div class="edh-etiket">Kadro durumunuz</div>
					<div class="edh-secim" role="radiogroup" aria-label="Kadro durumu">
						<label><input type="radio" name="statu" value="kadrolu" checked><span>Kadrolu</span></label>
						<label><input type="radio" name="statu" value="yonetici"><span>Yönetici</span></label>
						<label><input type="radio" name="statu" value="sozlesmeli"><span>Sözleşmeli</span></label>
						<label><input type="radio" name="statu" value="ucretli"><span>Ücretli</span></label>
					</div>
					<div class="edh-ipucu" data-yalniz="yonetici">Müdür ve müdür yardımcılarında yüksek lisans / doktora artırımı, ek ders yerine geçen yönetim görevine de uygulanır.</div>

					<div data-yalniz="kadrolu yonetici sozlesmeli">
						<div class="edh-etiket">Eğitim durumunuz</div>
						<div class="edh-secim" role="radiogroup" aria-label="Eğitim durumu">
							<label><input type="radio" name="egitim" value="1" checked><span>Lisans</span></label>
							<label><input type="radio" name="egitim" value="1.07"><span>Yüksek lisans <small>+%7</small></span></label>
							<label><input type="radio" name="egitim" value="1.20"><span>Doktora <small>+%20</small></span></label>
						</div>
					</div>

					<div class="edh-ikili">
						<label class="edh-alan"><span class="edh-etiket">Ödeme ayı</span>
							<select name="ay">
								<?php foreach ( $aylar as $i => $ad ) echo '<option value="' . (int) $i . '"' . selected( (int) wp_date( 'n' ), $i, false ) . '>' . esc_html( $ad ) . '</option>'; ?>
							</select>
						</label>
						<label class="edh-alan"><span class="edh-etiket">Gelir vergisi dilimi</span>
							<select name="dilim">
								<option value="0.15" selected>%15</option>
								<option value="0.20">%20</option>
								<option value="0.27">%27</option>
								<option value="0.35">%35</option>
								<option value="oto">Bilmiyorum – matrahtan hesapla</option>
							</select>
						</label>
					</div>
					<label class="edh-alan" data-oto hidden><span class="edh-etiket">Yılbaşından bu yana kümülatif vergi matrahınız</span>
						<input type="number" name="kumulatif" min="0" step="0.01" inputmode="decimal" placeholder="e-Bordro’daki “Kümülatif vergi matrahı”, örn. 420000">
					</label>
				</section>

				<section class="edh-kart">
					<h3 class="edh-kart__t"><span class="edh-no">2</span> Bu ayki saatleriniz</h3>
					<p class="edh-kart__a">Ay boyunca girdiğiniz toplam saatleri yazın. Boş bıraktıklarınız hesaba katılmaz.</p>
					<?php $ilk = true; foreach ( $gruplar as $gk => $g ) : ?>
						<details class="edh-grup" <?php echo $ilk ? 'open' : ''; ?>>
							<summary><?php echo esc_html( $g[0] ); ?><span class="edh-grup__top" data-grup="<?php echo esc_attr( $gk ); ?>"></span></summary>
							<div class="edh-saatler">
							<?php foreach ( $g[2] as $ad => $s ) : ?>
								<div class="edh-saat"<?php echo $s[6] ? ' data-yalniz="' . esc_attr( $s[6] ) . '"' : ''; ?>>
									<div class="edh-saat__ad"><strong><?php echo esc_html( $s[0] ); ?></strong><?php if ( $s[1] ) echo '<small>' . esc_html( $s[1] ) . '</small>'; ?></div>
									<div class="edh-adim">
										<button type="button" data-adim="-1" aria-label="Azalt">−</button>
										<input type="number" name="<?php echo esc_attr( $ad ); ?>" data-grup="<?php echo esc_attr( $gk ); ?>" data-g="<?php echo (int) $s[2]; ?>" data-c="<?php echo esc_attr( $s[3] ); ?>" data-lu="<?php echo esc_attr( $s[4] ); ?>"<?php echo $s[5] ? ' data-ders' : ''; ?> min="0" max="400" step="1" inputmode="decimal" placeholder="0" aria-label="<?php echo esc_attr( $s[0] ); ?> saati">
										<button type="button" data-adim="1" aria-label="Artır">+</button>
									</div>
								</div>
							<?php endforeach; ?>
							</div>
						</details>
					<?php $ilk = false; endforeach; ?>
					<label class="edh-onay" data-yalniz="ucretli"><input type="checkbox" name="destek" checked> <span>Her 5 ders saatine 1 saat ilave ücret ekle (ücretli öğretmenlere ödenir)</span></label>
					<label class="edh-onay"><input type="checkbox" name="istisna" checked> <span>Asgari ücret vergi istisnası bu ek derse uygulansın</span></label>
					<p class="edh-ipucu">Bordronuzda istisna maaştan düşülmüşse işareti kaldırın. Emin değilseniz işaretli bırakın; çoğu bordroda istisna önce ek derse uygulanır.</p>
				</section>
			</div>

			<aside class="edh-sonuc" aria-live="polite">
				<div class="edh-sonuc__kart">
					<span class="edh-sonuc__ad">Tahmini net ek ders ücreti</span>
					<strong class="edh-sonuc__net" data-o="net">0,00 TL</strong>
					<span class="edh-sonuc__alt"><span data-o="saat">0</span> saat · saat başı net <span data-o="saatbasi">0,00 TL</span><span class="edh-dokun"> · ayrıntı için dokunun</span></span>
					<div class="edh-bar" aria-hidden="true"><i data-bar="net"></i><i data-bar="sgk"></i><i data-bar="gv"></i><i data-bar="dv"></i></div>
					<dl class="edh-kalem">
						<div><dt>Brüt tutar</dt><dd data-o="brut">0,00 TL</dd></div>
						<div data-yalniz="ucretli sozlesmeli"><dt><i class="edh-nokta edh-nokta--sgk"></i><span data-o="sgkad">SGK primi</span></dt><dd data-o="sgk">0,00 TL</dd></div>
						<div><dt><i class="edh-nokta edh-nokta--gv"></i>Gelir vergisi</dt><dd data-o="gv">0,00 TL</dd></div>
						<div><dt><i class="edh-nokta edh-nokta--dv"></i>Damga vergisi</dt><dd data-o="dv">0,00 TL</dd></div>
						<div class="edh-kalem__net"><dt><i class="edh-nokta edh-nokta--net"></i>Net</dt><dd data-o="net2">0,00 TL</dd></div>
					</dl>
					<div class="edh-dugmeler">
						<button type="button" class="edh-btn" data-kopyala>Sonucu kopyala</button>
						<button type="button" class="edh-btn edh-btn--ghost" data-sifirla>Sıfırla</button>
					</div>
				</div>
				<p class="edh-uyari">Sonuçlar mevzuattaki gösterge ve katsayılarla hesaplanmış <strong>tahmini</strong> tutarlardır. Kesin tutar için e-Bordro’ya bakınız. Merkezî sınav görev ücretleri dahil değildir.</p>
			</aside>
		</form>

		<details class="edh-tablo">
			<summary>Saat ücretleri tablosu (<?php echo esc_html( $donem ); ?>)</summary>
			<div class="edh-tablo__kay">
			<table>
				<thead><tr><th>Görev</th><th>Lisans</th><th>Yüksek lisans</th><th>Doktora</th></tr></thead>
				<tbody>
				<?php
				$satir = array(
					array( 'Gündüz ek ders', 140, 1, true ),
					array( 'Gece / hafta sonu ek ders', 150, 1, true ),
					array( 'Nöbet, belleticilik, egzersiz, okul sınav görevi', 140, 1, false ),
					array( 'Özel eğitim – gündüz (%25 fazla)', 140, 1.25, true ),
					array( 'Özel eğitim – gece (%25 fazla)', 150, 1.25, true ),
					array( 'DYK – gündüz (%100 fazla)', 140, 2, true ),
					array( 'DYK – gece / hafta sonu (%100 fazla)', 150, 2, true ),
				);
				foreach ( $satir as $s ) {
					echo '<tr><td>' . esc_html( $s[0] ) . '</td><td><strong>' . esc_html( self::tl( $s[1] * $s[2] * $k ) ) . '</strong></td>';
					echo '<td>' . ( $s[3] ? esc_html( self::tl( $s[1] * $s[2] * 1.07 * $k ) ) : '—' ) . '</td>';
					echo '<td>' . ( $s[3] ? esc_html( self::tl( $s[1] * $s[2] * 1.20 * $k ) ) : '—' ) . '</td></tr>';
				}
				?>
				</tbody>
			</table>
			</div>
			<p class="edh-ipucu">Tutarlar brüttür. Hesap: gösterge (gündüz 140, gece 150) × memur aylık katsayısı. Yüksek lisans %7, doktora %20 artırımı fiilen girilen derslere uygulanır.</p>
		</details>

		<?php if ( self::ayar( 'sss' ) ) echo self::sss( $k, $donem ); // phpcs:ignore ?>
	</div>
	<?php
	return ob_get_clean();
}

    /** Sık sorulan sorular (+ FAQPage yapılandırılmış verisi) */
	public static function sss( $k, $donem ) {
	$s = array(
		array( 'Ek ders ücreti nasıl hesaplanır?', 'Bir saatlik brüt ek ders ücreti, gösterge rakamının memur aylık katsayısıyla çarpılmasıyla bulunur. Gündüz dersleri için gösterge 140, gece ve hafta sonu dersleri için 150’dir. ' . $donem . ' döneminde gündüz saat ücreti ' . self::tl( 140 * $k ) . ', gece saat ücreti ' . self::tl( 150 * $k ) . ' (brüt) olarak uygulanır.' ),
		array( 'Yüksek lisans ve doktora ek ders ücretini artırır mı?', 'Evet. Yüksek lisans mezunlarına %7, doktora mezunlarına %20 fazla ödenir. Bu artırım fiilen girilen derslere uygulanır; okul müdürü ve müdür yardımcılarında ek ders yerine geçen yönetim görevine de uygulanır.' ),
		array( 'Nöbet ücreti kaç saat ek ders sayılır?', 'Nöbet görevi, ek ders yerine geçen görev olarak gündüz ek ders ücretinden ödenir. Nöbet ücretine lisansüstü artırımı uygulanmaz.' ),
		array( 'Ek dersten hangi kesintiler yapılır?', 'Kadrolu öğretmenlerin ek dersinden gelir vergisi ve damga vergisi kesilir. Sözleşmeli öğretmenlerde ayrıca %14 SGK primi, ücretli öğretmenlerde %14 SGK ve %1 işsizlik primi kesilir. Asgari ücret istisnası gelir ve damga vergisini azaltır.' ),
		array( 'DYK kurs ücreti normal ek dersten farklı mı?', 'Destekleme ve yetiştirme kurslarında (DYK) ek ders ücreti %100 fazlasıyla, yani iki katı olarak ödenir. Özel eğitim sınıflarında görev yapan öğretmenlere ise %25 fazla ödenir.' ),
	);
	$h = '<section class="edh-sss"><h2>Sık sorulan sorular</h2>';
	$json = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array() );
	foreach ( $s as $x ) {
		$h .= '<details><summary>' . esc_html( $x[0] ) . '</summary><p>' . esc_html( $x[1] ) . '</p></details>';
		$json['mainEntity'][] = array( '@type' => 'Question', 'name' => $x[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $x[1] ) );
	}
	$h .= '</section><script type="application/ld+json">' . wp_json_encode( $json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
	return $h;
}
}
new OSC_Ekders();
}
