<?php
/**
 * Plugin Name: Online Soru Çöz Pro V20 (Tam Sürüm - Modern UI)
 * Description: PDF okuma sistemi, mobil manşet ve hiyerarşik kategori yapısı dahil tam sürüm. Görselden soru alma iyileştirildi. Başlangıç ve Sonuç ekranları modernleştirildi.
 * Version: 21.7
 * Author: Soruindir
 * Text Domain: soruindir.net
 */

if (!defined('ABSPATH')) { exit; }

// Tam genişlik sayfa şablonu: [osc_anasayfa] içeren sayfalarda temanın kenar çubuğu olmadan, ortalanmış gösterim
if (defined('OSC_FW_TPL') && !defined('OSC_FW_TPL_DONE')) {
    define('OSC_FW_TPL_DONE', 1);
    get_header();
    echo '<style>#osc-fw-main{box-sizing:border-box!important;display:block!important;width:100%!important;max-width:1240px!important;margin:0 auto!important;padding:28px 0 56px!important;float:none!important;clear:both!important;flex:1 1 100%!important;position:relative!important;left:auto!important;right:auto!important}</style>';
    echo '<main id="osc-fw-main" role="main">';
    while (have_posts()) { the_post(); the_content(); }
    echo '</main>';
    get_footer();
    return;
}

if (!class_exists('OnlineSoruCozProV20')) {

    class OnlineSoruCozProV20 {

        public function __construct() {
            // Kurulum ve Ayarlar
            add_action('init', array($this, 'setup_cpt'));
            add_action('add_meta_boxes', array($this, 'add_meta_box'));
            add_action('save_post', array($this, 'save_data'));
            
            // Admin Menü ve Ayarlar
            add_action('admin_menu', array($this, 'add_settings_page'));
            add_action('admin_init', array($this, 'register_settings'));

            // Frontend ve Scriptler
            add_action('admin_footer', array($this, 'admin_scripts'));
            add_filter('the_content', array($this, 'inject_quiz'));
            add_action('admin_head', array($this, 'admin_styles'));
            add_action('wp_enqueue_scripts', array($this, 'frontend_styles'));
            
            // Kısa Kodlar
            add_shortcode('osc_anasayfa', array($this, 'homepage_shortcode'));
            add_filter('template_include', array($this, 'fw_template_include'), 99);
            add_filter('body_class', array($this, 'fw_body_class'));
            add_shortcode('osc_quiz', array($this, 'single_quiz_shortcode'));

            // Admin Listesi Düzenlemeleri
            add_filter('manage_osc_quiz_posts_columns', array($this, 'add_shortcode_column'));
            add_action('manage_osc_quiz_posts_custom_column', array($this, 'display_shortcode_column'), 10, 2);
            
            // Kategori Sayfaları ve 404
            add_action('admin_init', array($this, 'check_rewrite_rules'));
            
           // Veritabanlı Liderlik Tablosu AJAX Kancaları
            add_action('wp_ajax_osc_upload_image', array($this, 'upload_image_ajax'));
            add_action('wp_ajax_osc_save_score', array($this, 'save_score_ajax'));
            add_action('wp_ajax_nopriv_osc_save_score', array($this, 'save_score_ajax'));
            
            // --- YENİ: Haftalık İndirme ve Çözülme Sayacı Kancaları ---
            add_action('wp_ajax_osc_track_solve', array($this, 'track_solve_ajax'));
            add_action('wp_ajax_nopriv_osc_track_solve', array($this, 'track_solve_ajax'));
            add_action('wp_ajax_osc_track_download', array($this, 'track_download_ajax'));
            add_action('wp_ajax_nopriv_osc_track_download', array($this, 'track_download_ajax'));

                        // Sadece Testi Gösteren Embed (İframe) Kancası
            add_action('template_redirect', array($this, 'handle_embed_request'));
            
            // Rank Math SEO - Otomatik Meta Açıklama Kancası
            add_filter('rank_math/frontend/description', array($this, 'rankmath_dynamic_description'));
        }

        // --- RANK MATH SEO DİNAMİK AÇIKLAMA FONKSİYONU ---
        public function rankmath_dynamic_description($description) {
            if (is_singular('post')) {
                global $post;
                // Yazının içinde testimizin kısa kodu var mı diye bakıyoruz
                if (has_shortcode($post->post_content, 'osc_quiz')) {
                    // Kısa kodun içindeki ID numarasını çekiyoruz
                    preg_match('/\[osc_quiz\s+id=["\']?(\d+)["\']?\]/', $post->post_content, $matches);
                    
                    if (!empty($matches[1])) {
                        $quiz_id = intval($matches[1]);
                        $data = get_post_meta($quiz_id, '_osc_data', true);
                        $settings = get_post_meta($quiz_id, '_osc_settings', true);
                        
                        if (!empty($data) && is_array($data)) {
                            $q_count = count($data);
                            $quiz_title = get_the_title($quiz_id);
                            $duration_min = isset($settings['duration']) ? intval($settings['duration']) : 0;
                            
                            // Google'ın bayılacağı, anahtar kelime ve tıklamaya teşvik (CTA) içeren dinamik metni oluşturuyoruz
                            $desc = $quiz_title . " konulu bu online test toplam " . $q_count . " sorudan oluşmaktadır. ";
                            
                            if ($duration_min > 0) {
                                $duration_display = ($duration_min > 60) ? floor($duration_min / 60) . " sa " . ($duration_min % 60) . " dk" : $duration_min . " dk";
                                $desc .= "Testi tamamlamanız için belirlenen süre " . $duration_display . "'dir. ";
                            } else {
                                $desc .= "Bu test için herhangi bir süre sınırı bulunmamaktadır. ";
                            }
                            
                            $desc .= "Kendini test et, soruları çöz, sonucunu anında gör ve Soruİndir liderlik tablosundaki yerini al!";
                            
                            // Rank Math'e kendi oluşturduğumuz bu harika açıklamayı gönderiyoruz
                            return $desc;
                        }
                    }
                }
            }
            return $description; // Test yoksa Rank Math'in kendi normal açıklamasını kullanmaya devam et
        }

        public function frontend_styles() {

            wp_enqueue_style('dashicons');
        }

        // --- AYARLAR SAYFASI ---
        public function add_settings_page() {
            add_submenu_page('edit.php?post_type=osc_quiz', 'OSC Ayarlar', 'Ayarlar', 'manage_options', 'osc-settings', array($this, 'render_settings_page'));
        }

                        public function register_settings() {
            register_setting('osc_options_group', 'osc_home_categories');
            register_setting('osc_options_group', 'osc_disable_auto_inject');
            register_setting('osc_options_group', 'osc_home_keep_sidebar');
            register_setting('osc_options_group', 'osc_global_ad_code');
            register_setting('osc_options_group', 'osc_global_ad_freq');
            
            // YENİ: 3 Farklı Reklam Alanı İçin Kayıtlar
          register_setting('osc_options_group', 'osc_ad_start_screen');
            register_setting('osc_options_group', 'osc_ad_result_screen');
            register_setting('osc_options_group', 'osc_ad_mistakes_screen');
            register_setting('osc_options_group', 'osc_ad_homepage_manset');
            register_setting('osc_options_group', 'osc_ad_homepage_slider');
            register_setting('osc_options_group', 'osc_ad_slider_freq');

            register_setting('osc_options_group', 'osc_ai_api_key');
        }



        // Kategori Ağacı Oluşturma Fonksiyonu
        public function display_category_tree($parent_id = 0, $level = 0, $saved_cats = array()) {
            $args = array(
                'parent' => $parent_id,
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC'
            );
            $cats = get_categories($args);
            
            if (!empty($cats)) {
                foreach ($cats as $cat) {
                    $checked = in_array($cat->term_id, $saved_cats) ? 'checked' : '';
                    $indent = $level * 25; 
                    
                    $style = $level == 0 ? "font-weight:bold; color:#2271b1; font-size:14px; margin-top:10px;" : "margin-left:{$indent}px; color:#444;";
                    $bg = $level == 0 ? "background:#f0f6fc; border-bottom:1px solid #ddd;" : "";
                    
                    echo '<div style="padding:5px; '.$bg.'">';
                    echo '<label style="'.$style.' display:inline-block;">';
                    echo '<input type="checkbox" name="osc_home_categories[]" value="'.$cat->term_id.'" '.$checked.'> ';
                    echo $level > 0 ? '↳ ' : ''; 
                    echo $cat->name;
                    echo '</label>';
                    echo '</div>';
                    
                    $this->display_category_tree($cat->term_id, $level + 1, $saved_cats);
                }
            }
        }

        public function render_settings_page() {
            ?>
            <div class="wrap">
                <h1>Online Soru Çöz - Genel Ayarlar</h1>
                <form method="post" action="options.php" style="background:#fff; padding:20px; border:1px solid #ccc; max-width:800px;">
                    <?php settings_fields('osc_options_group'); ?>
                    <?php do_settings_sections('osc_options_group'); ?>
                    
                    <h2 style="border-bottom:1px solid #eee; padding-bottom:10px;">1. Anasayfa Kategori Seçimi</h2>
                    <p>Aşağıdan ana kategorileri seçtiğinizde, o kategorinin <strong>alt kategorilerine</strong> eklenen yazılar da otomatik olarak manşette ve listelerde görünecektir.</p>
                    
                    <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                        <?php 
                        $saved_cats = get_option('osc_home_categories', array());
                        if(!is_array($saved_cats)) $saved_cats = array();
                        $this->display_category_tree(0, 0, $saved_cats);
                        if(empty(get_categories(array('hide_empty'=>false)))) { echo "Henüz hiç kategori yok."; }
                        ?>
                    </div>

                    <h2 style="border-bottom:1px solid #eee; padding-bottom:10px; margin-top:30px;">2. Otomatik Ekleme Ayarı</h2>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Otomatik Eklemeyi Kapat</th>
                            <td>
                                <?php $disable_auto = get_option('osc_disable_auto_inject'); ?>
                                <label>
                                    <input type="checkbox" name="osc_disable_auto_inject" value="1" <?php checked($disable_auto, 1); ?>>
                                    Testleri yazı sonuna otomatik ekleme.
                                </label>
                                <br><label style="display:inline-block;margin-top:8px;">
                                    <input type="checkbox" name="osc_home_keep_sidebar" value="1" <?php checked(get_option('osc_home_keep_sidebar'), 1); ?>>
                                    Test Çöz ana sayfasında temanın kenar çubuğunu (sidebar) göster. (İşaretli değilse sayfa tam genişlik ve ortalı açılır.)
                                </label>
                            </td>
                        </tr>
                    </table>

                                        <h2 style="border-bottom:1px solid #eee; padding-bottom:10px; margin-top:30px;">3. Global Reklam Ayarları</h2>
                    <p class="description">Testlerinize özel reklam kodu eklemezseniz, "Soru Arası Reklam Kodu" geçerli olacaktır. Diğer alanlar her testte sabit görünür.</p>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Soru Arası Reklam Kodu</th>
                            <td>
                                <textarea name="osc_global_ad_code" rows="3" class="large-text code"><?php echo stripslashes(get_option('osc_global_ad_code')); ?></textarea>
                                <p class="description">Test çözülürken aralarda çıkan reklam.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Gösterim Sıklığı</th>
                            <td>
                                <input type="number" name="osc_global_ad_freq" value="<?php echo esc_attr(get_option('osc_global_ad_freq')); ?>" class="small-text">
                                <p class="description">Kaç soruda bir reklam gösterilsin? 0 yazarsanız kapalıdır.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Başlangıç Ekranı Reklamı</th>
                            <td>
                                <textarea name="osc_ad_start_screen" rows="3" class="large-text code"><?php echo stripslashes(get_option('osc_ad_start_screen')); ?></textarea>
                                <p class="description">Kullanıcı testi başlatmadan hemen önce butunon altında görünür.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Sonuç Ekranı Reklamı</th>
                            <td>
                                <textarea name="osc_ad_result_screen" rows="3" class="large-text code"><?php echo stripslashes(get_option('osc_ad_result_screen')); ?></textarea>
                                <p class="description">Test bittiğinde puanın hemen altında görünür.</p>
                            </td>
                        </tr>
                       <tr valign="top">
                            <th scope="row">Yanlışları İncele Reklamı</th>
                            <td>
                                <textarea name="osc_ad_mistakes_screen" rows="3" class="large-text code"><?php echo stripslashes(get_option('osc_ad_mistakes_screen')); ?></textarea>
                                <p class="description">Kullanıcı yanlışlarını incelerken en altta görünür.</p>
                            </td>
                        </tr>
                       <tr valign="top">
                            <th scope="row">Ana Sayfa Manşet Altı Reklamı</th>
                            <td>
                                <textarea name="osc_ad_homepage_manset" rows="3" class="large-text code"><?php echo stripslashes(get_option('osc_ad_homepage_manset')); ?></textarea>
                                <p class="description">Ana sayfadaki kayan manşet ile kategorilerin arasında, merkeze hizalanmış olarak görünür.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Manşet (Slider) İçi Reklam Kodu</th>
                            <td>
                                <textarea name="osc_ad_homepage_slider" rows="3" class="large-text code"><?php echo stripslashes(get_option('osc_ad_homepage_slider')); ?></textarea>
                                <p class="description">Manşetlerin arasında dönen <strong>Sponsorlu</strong> slaytın reklam kodudur.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Manşet İçi Reklam Sıklığı</th>
                            <td>
                                <input type="number" name="osc_ad_slider_freq" value="<?php echo esc_attr(get_option('osc_ad_slider_freq', 3)); ?>" class="small-text">
                                <p class="description">Kaç manşetten sonra reklam çıksın? (Örn: 3 yazarsanız, her 3 testten sonra 1 reklam slaytı (R) çıkar. Kapatmak için 0 yazın.)</p>
                            </td>
                        </tr>
                    </table>


                    <h2 style="border-bottom:1px solid #eee; padding-bottom:10px; margin-top:30px;">4. Yapay Zeka (Akıllı OCR) Ayarları</h2>
                    <p class="description">Resimden soru okuma işlemini kusursuz hale getirmek için ücretsiz Google Gemini API anahtarınızı buraya girebilirsiniz. Boş bırakırsanız standart OCR (Tesseract) kullanılır.</p>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Gemini API Anahtarı (Key)</th>
                            <td>
                                <input type="password" name="osc_ai_api_key" value="<?php echo esc_attr(get_option('osc_ai_api_key')); ?>" class="regular-text">
                                <p class="description"><a href="https://aistudio.google.com/app/apikey" target="_blank">Buraya tıklayarak</a> Google AI Studio'dan ücretsiz bir API anahtarı alabilirsiniz.</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Ayarları Kaydet'); ?>
                </form>
            </div>
            <?php
        }


       // --- ANASAYFA KISA KODU (V21 - optik form tasarımı) ---
        // Testin soru sayısı ve süresi: yazının içindeki [osc_quiz id="..."] kısa kodundan okunur (3 saat önbellek)
        private function osc_card_info($post) {
            static $memo = array();
            $pid = is_object($post) ? $post->ID : intval($post);
            if (isset($memo[$pid])) return $memo[$pid];
            $info = get_transient('osc_ci_' . $pid);
            if (!is_array($info)) {
                $info = array('q' => 0, 'min' => 0);
                $content = is_object($post) ? $post->post_content : get_post_field('post_content', $pid);
                if (preg_match('/\[osc_quiz[^\]]*id=["\']?(\d+)/i', (string) $content, $m)) {
                    $qd = get_post_meta(intval($m[1]), '_osc_data', true);
                    $qs = get_post_meta(intval($m[1]), '_osc_settings', true);
                    $info['q'] = is_array($qd) ? count($qd) : 0;
                    $info['min'] = (is_array($qs) && !empty($qs['duration'])) ? intval($qs['duration']) : 0;
                }
                set_transient('osc_ci_' . $pid, $info, 3 * HOUR_IN_SECONDS);
            }
            return $memo[$pid] = $info;
        }

        private function osc_short_cat($name) {
            $s = trim(preg_replace('/\s*(?:kariyer\s+s[ıi]nav[ıi]\s+)?(?:online\s+)?(?:testleri|testi|test)(?:\s+online)?(?:\s+[çc][öo]z)?\s*$/iu', '', $name));
            return $s !== '' ? $s : $name;
        }

        private function osc_week_key() { return '_haftalik_cozulme_' . date('W_Y'); }

        // Her test için görsel: öne çıkan görsel varsa o; yoksa başlıktaki derse göre üretilen görsel (ikon + renk + sınıf/sınav etiketi)
        private function osc_subject($title) {
            $t = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
            $t = str_replace(array('İ', 'I'), array('i', 'ı'), $t);
            $map = array(
                array('law',  '/ekys|kanun|mevzuat|yönetmelik|uzman|başöğretmen|kariyer/u', '#475569', '#E3E8EF', '<path d="M12 3v18M7 21h10M4 7h16"/><path d="M6 7l-3 6.5a3 3 0 0 0 6 0zM18 7l-3 6.5a3 3 0 0 0 6 0z"/>'),
                array('math', '/matematik|sayı|toplama|çıkarma|çarpma|bölme|geometri|problem|kesir|ritmik|rasyonel/u', '#2563EB', '#DCE8FF', '<path d="M5 7h5M7.5 4.5v5M14 7h5M5.5 15.5l4 4M9.5 15.5l-4 4M14 16h5M14 19.5h5"/>'),
                array('sci',  '/fen bilim|fen |biyoloji|kimya|fizik|canlı/u', '#059669', '#D5F5E6', '<path d="M9 3h6M10 3v6.5L4.8 18.6A1.6 1.6 0 0 0 6.2 21h11.6a1.6 1.6 0 0 0 1.4-2.4L14 9.5V3"/><path d="M7.4 15h9.2"/>'),
                array('hist', '/tarih|inkılap|milli mücadele|kurtuluş|osmanlı|cephe/u', '#B45309', '#FCEFD3', '<path d="M3 21h18M5 21v-9M9.7 21v-9M14.3 21v-9M19 21v-9M2.5 9.5 12 3l9.5 6.5z"/>'),
                array('soc',  '/sosyal|coğrafya|vatandaşlık|toplum/u', '#0D9488', '#D2F4EF', '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 2.6 3.8 5.6 3.8 9s-1.2 6.4-3.8 9M12 3C9.4 5.6 8.2 8.6 8.2 12s1.2 6.4 3.8 9"/>'),
                array('rel',  '/din kültürü|ahlak/u', '#7C3AED', '#ECE5FF', '<path d="M19.5 14.6A8 8 0 1 1 9.4 4.5a6.4 6.4 0 0 0 10.1 10.1z"/><path d="M17 3.5l.8 1.7 1.8.3-1.3 1.3.3 1.8-1.6-.9-1.6.9.3-1.8-1.3-1.3 1.8-.3z"/>'),
                array('eng',  '/ingilizce|english|almanca|yabancı dil/u', '#4F46E5', '#E2E4FF', '<path d="M4 5h16v10.5H9.5L5 19.5V15.5H4z"/><path d="M8 9h8M8 12h5"/>'),
                array('tr',   '/türkçe|edebiyat|türk dili|noktalama|anlam|sözcük|cümle|paragraf|deyim|yazım|dil bilgisi/u', '#C2346B', '#FCE4EE', '<path d="M2.5 5.5c3-1.4 6.3-1.2 9.5.9 3.2-2.1 6.5-2.3 9.5-.9V19c-3-1.4-6.3-1.2-9.5.9-3.2-2.1-6.5-2.3-9.5-.9z"/><path d="M12 6.4v13.5"/>'),
            );
            $sub = array('gen', '#16233F', '#E7EAF2', '<circle cx="7" cy="8" r="2.6"/><circle cx="17" cy="8" r="2.6"/><circle cx="7" cy="16" r="2.6"/><circle cx="17" cy="16" r="2.6" fill="currentColor"/>');
            foreach ($map as $m) { if (preg_match($m[1], $t)) { $sub = array($m[0], $m[2], $m[3], $m[4]); break; } }
            $badge = '';
            if (preg_match('/\b(kpss|tyt|ayt|yks|lgs|ekys|ales|dgs|yds)\b/iu', $t, $mm)) $badge = strtoupper(str_replace('ı', 'i', $mm[1]));
            elseif (preg_match('/(\d{1,2})\.\s*sınıf/u', $t, $mm)) $badge = $mm[1] . '. sınıf';
            return array('key' => $sub[0], 'color' => $sub[1], 'bg' => $sub[2], 'icon' => $sub[3], 'badge' => $badge);
        }

        // Kategori renkleri: her kategoriye sırayla farklı bir renk; testler kendi (ya da üst) kategorisinin rengini alır
        private $osc_cat_colors = array();
        private function osc_palette() {
            return array(
                array('#C2346B', '#FCE4EE'), array('#2563EB', '#DCE8FF'), array('#059669', '#D5F5E6'), array('#B45309', '#FCEFD3'),
                array('#7C3AED', '#ECE5FF'), array('#0D9488', '#D2F4EF'), array('#DC2626', '#FEE4E2'), array('#4F46E5', '#E2E4FF'),
                array('#0284C7', '#DDF1FD'), array('#4D7C0F', '#E9F7CF'), array('#EA580C', '#FFE9D6'), array('#475569', '#E3E8EF'),
            );
        }
        private function osc_set_cat_colors($categories) {
            $pal = $this->osc_palette(); $this->osc_cat_colors = array();
            foreach (array_values($categories) as $i => $c) $this->osc_cat_colors[$c->term_id] = $pal[$i % count($pal)];
        }
        private function osc_cat_color($term_id) {
            if (isset($this->osc_cat_colors[$term_id])) return $this->osc_cat_colors[$term_id];
            foreach ((array) get_ancestors($term_id, 'category') as $a) { if (isset($this->osc_cat_colors[$a])) return $this->osc_cat_colors[$a]; }
            return null;
        }
        private function osc_post_color($p) {
            $cats = get_the_category($p->ID);
            foreach ((array) $cats as $c) { $col = $this->osc_cat_color($c->term_id); if ($col) return $col; }
            $pal = $this->osc_palette();
            return $pal[!empty($cats) ? (abs(crc32((string) $cats[0]->term_id)) % count($pal)) : 0];
        }
        private function osc_book_svg($sw = '1.8') {
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $sw . '" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 5.5c3-1.4 6.3-1.2 9.5.9 3.2-2.1 6.5-2.3 9.5-.9V19c-3-1.4-6.3-1.2-9.5.9-3.2-2.1-6.5-2.3-9.5-.9z"/><path d="M12 6.4v13.5"/></svg>';
        }

        // Test görseli: öne çıkan görsel varsa o; yoksa kategorinin renginde kitap simgesi (+ sınıf/sınav etiketi)
        private function osc_test_art($p, $size = 'sm') {
            $col = $this->osc_post_color($p);
            $st = '--a:' . $col[0] . ';--b:' . $col[1];
            $thumb = get_the_post_thumbnail_url($p->ID, $size === 'sm' ? 'thumbnail' : 'medium_large');
            if ($thumb) return '<span class="oscp-art oscp-art--' . $size . ' has-img" style="' . $st . '"><img src="' . esc_url($thumb) . '" alt="" loading="lazy"></span>';
            $s = $this->osc_subject(get_the_title($p));
            $b = $s['badge'];
            return '<span class="oscp-art oscp-art--' . $size . '" style="' . $st . '" aria-hidden="true">' . $this->osc_book_svg('1.7')
                . ($b !== '' && $size !== 'sm' ? '<em>' . esc_html($b) . '</em>' : '')
                . ($b !== '' && $size === 'sm' ? '<i>' . esc_html(preg_replace('/\.\s*sınıf$/u', '', $b)) . '</i>' : '')
                . '</span>';
        }

        public function homepage_shortcode() {
            ob_start();

            $selected_cats = get_option('osc_home_categories', array());
            $common_args = array('post_type' => 'post', 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC', 'ignore_sticky_posts' => true);
            if (!empty($selected_cats) && is_array($selected_cats)) {
                $common_args['tax_query'] = array(
                    array('taxonomy' => 'category', 'field' => 'term_id', 'terms' => $selected_cats, 'include_children' => true, 'operator' => 'IN'),
                );
            }
            $cat_args = array('hide_empty' => true);
            if (!empty($selected_cats) && is_array($selected_cats)) { $cat_args['include'] = $selected_cats; }
            $categories = get_categories($cat_args);
            if (is_wp_error($categories)) $categories = array();

            // Sayılar: toplam test ve bu haftaki çözüm
            $count_args = $common_args; $count_args['posts_per_page'] = 1; $count_args['fields'] = 'ids';
            $count_q = new WP_Query($count_args);
            $total_tests = intval($count_q->found_posts);
            global $wpdb;
            $week_key = $this->osc_week_key();
            $week_total = intval($wpdb->get_var($wpdb->prepare("SELECT SUM(meta_value) FROM {$wpdb->postmeta} WHERE meta_key = %s", $week_key)));

            // Öne çıkanlar (slayt) + sponsorlu slaytlar
            $slider_args = $common_args; $slider_args['posts_per_page'] = 5;
            $slides = (new WP_Query($slider_args))->posts;
            $ad_slider_code = get_option('osc_ad_homepage_slider', '');
            $ad_slider_freq = intval(get_option('osc_ad_slider_freq', 3));
            $all_slides = array(); $p_count = 0;
            foreach ($slides as $slide) {
                $all_slides[] = array('type' => 'post', 'data' => $slide); $p_count++;
                if ($ad_slider_freq > 0 && !empty($ad_slider_code) && $p_count % $ad_slider_freq == 0) $all_slides[] = array('type' => 'ad', 'data' => $ad_slider_code);
            }

            // Son eklenenler
            $list_args = $common_args; $list_args['posts_per_page'] = 12;
            $latest = (new WP_Query($list_args))->posts;

            // Bu hafta en çok çözülenler (sayaç verisi varsa)
            $pop_args = $common_args; $pop_args['posts_per_page'] = 8; $pop_args['meta_key'] = $week_key; $pop_args['orderby'] = 'meta_value_num'; $pop_args['order'] = 'DESC';
            $popular = (new WP_Query($pop_args))->posts;

            $now = current_time('timestamp');
            $uid = 'oscp' . wp_rand(100, 999);
            $fmt = function ($n) { return number_format_i18n((int) $n); };
            $ago = function ($p) use ($now) { return human_time_diff(get_post_time('U', false, $p), $now) . ' önce'; };
            $is_new = function ($p) use ($now) { return ($now - get_post_time('U', false, $p)) < 3 * DAY_IN_SECONDS; };
            $latest = array_slice($latest, 0, 8);
            $this->osc_set_cat_colors($categories);
            ?>
            <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
            <style>
                .oscp { --ink:#16233F; --ink2:#4A5468; --mute:#7A8396; --pink:#E0457B; --pink-d:#B92D5F; --paper:#FDF0F4; --pencil:#363B45; --line:#E4E7EE; --bg:#FFFFFF; --soft:#F5F6FA; --brand:#1E5AA8; --brand-d:#174A8B;
                    font-family:'Figtree',system-ui,-apple-system,'Segoe UI',sans-serif; color:var(--ink); background:var(--bg); font-size:16px; line-height:1.5; -webkit-font-smoothing:antialiased; }
                .oscp *, .oscp *::before, .oscp *::after { box-sizing:border-box; }
                .oscp a { color:inherit; text-decoration:none; }
                .oscp a:focus-visible, .oscp button:focus-visible, .oscp input:focus-visible { outline:3px solid var(--pink); outline-offset:2px; border-radius:10px; }
                .oscp h1, .oscp h2, .oscp h3 { font-family:'Bricolage Grotesque','Figtree',sans-serif; color:var(--ink); margin:0; letter-spacing:-0.01em; }
                .oscp ul, .oscp ol { list-style:none; margin:0; padding:0; }
                .oscp-wrap { max-width:1200px; margin:0 auto; padding:0 20px; }
                .oscp-sec { margin-top:52px; scroll-margin-top:96px; }
                .oscp-sec-head { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:20px; }
                .oscp-sec-head h2 { font-size:clamp(22px, 2.3vw, 28px); font-weight:800; }
                .oscp-sec-head p { margin:4px 0 0; color:var(--mute); font-size:15px; }
                .oscp .oscp-more { display:inline-flex; align-items:center; gap:6px; font-weight:700; font-size:14px; color:var(--pink-d); white-space:nowrap; padding:7px 12px; border-radius:999px; background:var(--paper); transition:background .15s, color .15s; }
                .oscp .oscp-more:hover { background:var(--pink); color:#fff; }
                .oscp .oscp-more svg { width:14px; height:14px; }

                /* ---------- Test görseli (öne çıkan görsel ya da derse göre üretilen) ---------- */
                .oscp-art { position:relative; display:grid; place-items:center; overflow:hidden; color:var(--a); background-color:var(--b); flex:0 0 auto;
                    background-image:radial-gradient(circle, transparent 5px, color-mix(in srgb, var(--a) 22%, transparent) 5.5px, color-mix(in srgb, var(--a) 22%, transparent) 6.5px, transparent 7px); background-size:22px 22px; background-position:-4px -4px; }
                .oscp-art img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
                .oscp-art svg { position:relative; width:46%; height:46%; filter:drop-shadow(0 2px 0 rgba(255,255,255,.7)); }
                .oscp-art--sm { width:58px; height:58px; border-radius:16px; background-size:14px 14px; }
                .oscp-art--sm i { position:absolute; right:3px; bottom:3px; font-style:normal; font-size:10px; font-weight:800; line-height:1; color:#fff; background:var(--a); padding:3px 5px; border-radius:6px; font-family:'Bricolage Grotesque',sans-serif; }
                .oscp-art--md { width:100%; aspect-ratio:16/9; border-radius:16px; }
                .oscp-art--md svg { width:30%; height:44%; }
                .oscp-art--lg { width:100%; height:100%; min-height:240px; border-radius:22px; }
                .oscp-art--lg svg { width:34%; height:40%; }
                .oscp-art em { position:absolute; left:12px; bottom:12px; font-style:normal; font-size:13px; font-weight:800; color:#fff; background:var(--a); padding:4px 10px; border-radius:8px; font-family:'Bricolage Grotesque',sans-serif; }
                .oscp-art--lg em { font-size:16px; padding:6px 14px; left:18px; bottom:18px; border-radius:10px; }

                /* ---------- GİRİŞ (kompakt optik form) ---------- */
                .oscp-hero > * { min-width:0; }
                .oscp-hero { position:relative; background:var(--paper); border-radius:24px; padding:30px 36px 28px 52px; overflow:hidden; display:grid; grid-template-columns:minmax(0,1fr) auto; gap:32px; align-items:center;
                    background-image:linear-gradient(rgba(224,69,123,.10) 1px, transparent 1px); background-size:100% 30px; }
                .oscp-hero::before { content:''; position:absolute; left:26px; top:0; bottom:0; width:2px; background:rgba(224,69,123,.28); }
                .oscp-hero h1 { font-size:clamp(26px, 2.8vw, 36px); line-height:1.1; font-weight:800; }
                .oscp-lead { margin:8px 0 18px; font-size:16px; color:var(--ink2); max-width:56ch; }
                .oscp-search { display:flex; background:#fff; border:2px solid var(--brand); border-radius:14px; padding:5px; max-width:560px; box-shadow:3px 3px 0 var(--brand); }
                .oscp-search input { flex:1; min-width:0; border:0; background:transparent; font:inherit; font-size:16px; padding:8px 10px; color:var(--ink); }
                .oscp-search input::placeholder { color:#9AA1B1; }
                .oscp-search input:focus { outline:none; }
                .oscp-search button { border:0; background:var(--pink); color:#fff; font:inherit; font-weight:700; font-size:15px; padding:8px 18px; border-radius:10px; cursor:pointer; }
                .oscp-search button:hover { background:var(--pink-d); }
                .oscp-levels { display:flex; flex-wrap:wrap; gap:7px; margin-top:16px; }
                .oscp-level { display:inline-flex; align-items:center; gap:7px; padding:5px 12px 5px 7px; background:#fff; border:1.5px solid var(--line); border-radius:999px; font-weight:600; font-size:14px; color:var(--ink); transition:border-color .15s; }
                .oscp-level::before { content:''; width:12px; height:12px; border-radius:50%; border:2px solid var(--pink); flex:0 0 auto; transition:background .15s; }
                .oscp-level:hover { border-color:var(--pink); }
                .oscp-level:hover::before { background:var(--pencil); border-color:var(--pencil); }
                .oscp-sheet { background:#fff; border:1.5px solid rgba(224,69,123,.45); border-radius:16px; padding:14px 16px 12px; width:270px; transform:rotate(1.5deg); box-shadow:0 14px 30px -20px rgba(22,35,63,.5); }
                .oscp-sheet-top { display:flex; justify-content:space-between; color:var(--pink-d); font-weight:700; font-size:12px; padding-bottom:7px; border-bottom:1.5px dashed rgba(224,69,123,.4); margin-bottom:6px; }
                .oscp-row { display:grid; grid-template-columns:20px repeat(5, 1fr); align-items:center; gap:4px; padding:3px 0; }
                .oscp-row b { font-family:'Bricolage Grotesque',sans-serif; color:var(--pink-d); font-size:13px; }
                .oscp-bub { width:21px; height:21px; border-radius:50%; border:1.8px solid rgba(224,69,123,.75); display:grid; place-items:center; font-size:9.5px; font-weight:700; color:rgba(185,45,95,.85); position:relative; justify-self:center; }
                .oscp-bub.on::after { content:''; position:absolute; inset:2px; border-radius:50%; background:var(--pencil); transform:scale(0); animation:oscpMark .35s cubic-bezier(.2,.9,.3,1.3) forwards; animation-delay:var(--d,0s); }
                @keyframes oscpMark { to { transform:scale(1); } }
                .oscp-sheet-stats { display:flex; gap:14px; margin-top:8px; padding-top:8px; border-top:1.5px dashed rgba(224,69,123,.4); }
                .oscp-sheet-stats div { font-size:12px; color:var(--mute); line-height:1.2; }
                .oscp-sheet-stats strong { display:block; font-family:'Bricolage Grotesque',sans-serif; font-size:19px; color:var(--ink); }

                /* ---------- MANŞET (tam genişlik) ---------- */
                .oscp-feature { background:var(--brand); color:#fff; border-radius:26px; overflow:hidden; position:relative; }
                .oscp-stage { position:relative; min-height:400px; touch-action:pan-y; }
                .oscp-slide { position:absolute; inset:0; display:grid; grid-template-columns:minmax(0,1.15fr) minmax(0,1fr); gap:36px; align-items:stretch; padding:36px 36px 96px 44px; opacity:0; visibility:hidden; transition:opacity .45s ease, visibility .45s; }
                .oscp-slide.is-on { opacity:1; visibility:visible; }
                .oscp-slide-text { display:flex; flex-direction:column; justify-content:center; min-width:0; }
                .oscp-cat { display:inline-block; align-self:flex-start; background:var(--pink); color:#fff; font-weight:700; font-size:13.5px; padding:5px 12px; border-radius:8px; margin-bottom:14px; }
                .oscp-slide h3 { color:#fff; font-size:clamp(24px, 2.7vw, 36px); line-height:1.14; font-weight:800; max-width:26ch; }
                .oscp-facts { display:flex; flex-wrap:wrap; gap:8px; margin:16px 0 22px; }
                .oscp-fact { display:inline-flex; align-items:center; gap:6px; font-size:14px; color:#fff; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.22); padding:5px 10px; border-radius:8px; }
                .oscp-fact svg { width:15px; height:15px; }
                .oscp-cta { display:inline-flex; align-self:flex-start; align-items:center; gap:10px; background:#fff; color:var(--ink); font-weight:700; font-size:16px; padding:13px 22px; border-radius:12px; transition:transform .15s, background .15s; }
                .oscp-slide:hover .oscp-cta { background:var(--paper); transform:translateY(-1px); }
                .oscp-slide-art { min-width:0; display:flex; }
                .oscp-slide-art .oscp-art { box-shadow:0 0 0 6px rgba(255,255,255,.06); transition:transform .4s ease; }
                .oscp-slide:hover .oscp-slide-art .oscp-art { transform:rotate(-1.2deg) scale(1.01); }
                .oscp-sponsor { grid-template-columns:1fr; place-items:center; text-align:center; background:#F5F6FA; color:var(--mute); }
                .oscp-sponsor small { font-size:12.5px; font-weight:600; margin-bottom:10px; color:#8B93A5; display:block; }
                .oscp-nav { position:absolute; left:44px; bottom:22px; display:flex; gap:8px; z-index:2; }
                .oscp-dot { display:flex; padding:4px; border:0; background:transparent; font:inherit; cursor:pointer; color:rgba(255,255,255,.8); position:relative; }
                .oscp-dot i { font-style:normal; width:32px; height:32px; border-radius:50%; border:2px solid rgba(255,255,255,.35); display:grid; place-items:center; font-weight:700; font-size:13px; font-family:'Bricolage Grotesque',sans-serif; transition:background .2s, border-color .2s; }
                .oscp-dot span { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); }
                .oscp-dot:hover i { border-color:#fff; color:#fff; }
                .oscp-dot.is-on i { border-color:#fff; background:#fff; color:var(--brand); }
                .oscp-feature.on-sponsor .oscp-dot i { border-color:rgba(22,35,63,.3); color:var(--mute); }
                .oscp-feature.on-sponsor .oscp-dot.is-on i { background:var(--brand); border-color:var(--brand); color:#fff; }

                /* ---------- SON EKLENENLER (kart ızgarası) ---------- */
                .oscp-cards { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:20px; }
                .oscp-card { position:relative; display:flex; flex-direction:column; border-radius:20px; background:#fff; border:1.5px solid var(--line); overflow:hidden; padding:18px 18px 16px; transition:border-color .15s, transform .15s, box-shadow .15s; }
                .oscp-card::before { content:''; position:absolute; left:0; top:0; right:0; height:5px; background:var(--a); }
                .oscp-card::after { content:''; position:absolute; right:-18px; top:-18px; width:96px; height:96px; opacity:.5; pointer-events:none;
                    background-image:radial-gradient(circle, transparent 5px, color-mix(in srgb, var(--a) 30%, transparent) 5.5px, color-mix(in srgb, var(--a) 30%, transparent) 6.5px, transparent 7px); background-size:20px 20px;
                    -webkit-mask-image:radial-gradient(circle at top right, #000 25%, transparent 70%); mask-image:radial-gradient(circle at top right, #000 25%, transparent 70%); }
                .oscp-card:hover { border-color:var(--a); transform:translateY(-3px); box-shadow:0 16px 30px -20px color-mix(in srgb, var(--a) 70%, #000); }
                .oscp-card-top { position:relative; z-index:1; display:flex; align-items:center; gap:6px; min-height:26px; }
                .oscp-card-tag { background:var(--b); color:var(--a); font-size:12.5px; font-weight:700; padding:4px 10px; border-radius:7px; }
                .oscp-card-new { background:var(--pink); color:#fff; font-size:12px; font-weight:700; padding:4px 9px; border-radius:7px; }
                .oscp-card-body { position:relative; z-index:1; padding:12px 0 0; display:flex; flex-direction:column; gap:10px; flex:1; }
                .oscp-card h3 { font-family:'Figtree',sans-serif; font-size:16px; line-height:1.38; font-weight:700; letter-spacing:0; }
                .oscp-card:hover h3 { color:var(--a); }
                .oscp-chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:auto; }
                .oscp-chip { display:inline-flex; align-items:center; gap:5px; font-size:12.5px; font-weight:600; color:var(--ink2); background:var(--soft); padding:4px 9px; border-radius:7px; }
                .oscp-chip svg { width:13px; height:13px; }

                /* ---------- BU HAFTA EN ÇOK ÇÖZÜLENLER (sıralama) ---------- */
                .oscp-pop { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px 28px; counter-reset:oscprank; }
                .oscp-pop li a { display:flex; align-items:center; gap:16px; padding:12px 14px; border-radius:18px; transition:background .15s; }
                .oscp-pop li a:hover { background:var(--soft); }
                .oscp-rank { flex:0 0 34px; font-family:'Bricolage Grotesque',sans-serif; font-size:28px; font-weight:800; color:var(--pink); text-align:center; line-height:1; }
                .oscp-pop h3 { font-family:'Figtree',sans-serif; font-size:16px; font-weight:700; line-height:1.38; letter-spacing:0; }
                .oscp-pop li a:hover h3 { color:var(--pink-d); }
                .oscp-heat { display:block; margin-top:3px; font-size:13.5px; color:var(--mute); font-weight:500; }
                .oscp-heat b { color:var(--ink); font-weight:700; }
                .oscp-pop .oscp-body { flex:1; min-width:0; }

                /* ---------- KATEGORİLER ---------- */
                .oscp-cats { display:grid; grid-template-columns:repeat(auto-fill, minmax(340px, 1fr)); gap:22px; }
                .oscp-catbox { border-radius:24px; background:#fff; border:1.5px solid var(--line); overflow:hidden; scroll-margin-top:96px; display:flex; flex-direction:column; }
                .oscp-catbox:target { border-color:var(--pink); box-shadow:0 0 0 4px rgba(224,69,123,.15); }
                .oscp-catbox-head { position:relative; display:flex; align-items:center; gap:14px; padding:18px 18px 16px; background:var(--b); color:var(--a); overflow:hidden; }
                .oscp-catbox-head::after { content:''; position:absolute; right:-10px; top:-14px; width:150px; height:100px; opacity:.55;
                    background-image:radial-gradient(circle, transparent 7px, color-mix(in srgb, var(--a) 30%, transparent) 7.5px, color-mix(in srgb, var(--a) 30%, transparent) 9px, transparent 9.5px); background-size:30px 30px;
                    -webkit-mask-image:linear-gradient(240deg, #000 20%, transparent 80%); mask-image:linear-gradient(240deg, #000 20%, transparent 80%); }
                .oscp-catbox-icon { position:relative; z-index:1; flex:0 0 44px; height:44px; border-radius:14px; background:#fff; display:grid; place-items:center; box-shadow:0 6px 14px -8px rgba(22,35,63,.45); }
                .oscp-catbox-icon svg { width:24px; height:24px; }
                .oscp-catbox-head h3 { position:relative; z-index:1; font-size:19px; font-weight:800; line-height:1.15; color:var(--ink); }
                .oscp-catbox-head small { display:block; color:var(--ink2); font-size:13px; font-weight:600; margin-top:3px; font-family:'Figtree',sans-serif; }
                .oscp-catbox-head .oscp-more { position:relative; z-index:1; margin-left:auto; background:#fff; color:var(--ink); }
                .oscp-catbox-head .oscp-more:hover { background:var(--brand); color:#fff; }
                .oscp-catbox ul { padding:8px; }
                .oscp-row-test a { display:flex; gap:14px; align-items:center; padding:10px; border-radius:16px; transition:background .15s; }
                .oscp-row-test a:hover { background:var(--soft); }
                .oscp-row-test .oscp-body { flex:1; min-width:0; }
                .oscp-row-test h4 { margin:0; font-family:'Figtree',sans-serif; font-size:15px; font-weight:700; line-height:1.38; color:var(--ink); }
                .oscp-row-test a:hover h4 { color:var(--pink-d); }
                .oscp-meta { display:flex; flex-wrap:wrap; gap:4px 10px; margin-top:4px; font-size:13px; color:var(--mute); }
                .oscp-new { color:var(--pink-d); font-weight:700; }
                .oscp-go { flex:0 0 30px; height:30px; border-radius:50%; display:grid; place-items:center; color:var(--mute); transition:background .15s, color .15s, transform .15s; }
                .oscp-go svg { width:15px; height:15px; }
                .oscp-row-test a:hover .oscp-go { background:var(--brand); color:#fff; transform:translateX(2px); }
                .oscp-empty { padding:16px 12px; color:var(--mute); font-size:15px; }

                .oscp-ad { margin-top:36px; display:flex; justify-content:center; padding:18px; border-radius:18px; background:var(--soft); }
                .oscp-ad > div { width:100%; overflow:hidden; text-align:center; }
                /* Tam genişlik sayfa: temanın kenar çubuğunu gizle, içerik sütununu tam genişlik yap */
                body.osc-fw-page #secondary, body.osc-fw-page #sidebar, body.osc-fw-page #right-sidebar, body.osc-fw-page .sidebar-primary, body.osc-fw-page aside.sidebar, body.osc-fw-page .right-sidebar-area, body.osc-fw-page #content aside.widget-area, body.osc-fw-page .site-content aside.widget-area { display:none !important; }
                body.osc-fw-page #primary, body.osc-fw-page .content-area { width:100% !important; max-width:100% !important; float:none !important; flex:1 1 100% !important; margin-left:auto !important; margin-right:auto !important; }
                .oscp.oscp-bleed { position:relative; max-width:none !important; }

                @media (max-width: 1080px) { .oscp-cards { grid-template-columns:repeat(3, minmax(0, 1fr)); } }
                @media (max-width: 900px) {
                    .oscp-hero { grid-template-columns:minmax(0,1fr); padding:26px 24px 24px 40px; }
                    .oscp-sheet { display:none; }
                    .oscp-slide { grid-template-columns:minmax(0,1fr); padding:28px 26px 88px; }
                    .oscp-slide-art { display:none; }
                    .oscp-nav { left:26px; }
                    .oscp-cards { grid-template-columns:repeat(2, minmax(0, 1fr)); }
                    .oscp-pop { grid-template-columns:minmax(0,1fr); }
                }
                @media (max-width: 600px) {
                    .oscp-wrap { padding:0 14px; }
                    .oscp-hero { border-radius:20px; padding:22px 18px 20px 30px; }
                    .oscp-hero::before { left:15px; }
                    .oscp-lead { font-size:15px; }
                    .oscp-search button { padding:8px 14px; }
                    .oscp-levels { flex-wrap:nowrap; overflow-x:auto; margin-right:-18px; padding-right:18px; padding-bottom:4px; scrollbar-width:none; }
                    .oscp-levels::-webkit-scrollbar { display:none; }
                    .oscp-level { white-space:nowrap; }
                    .oscp-feature { border-radius:20px; }
                    .oscp-stage { min-height:400px; }
                    .oscp-slide { padding:26px 20px 72px; }
                    .oscp-nav { left:16px; bottom:16px; gap:4px; }
                    .oscp-cards { display:flex; overflow-x:auto; scroll-snap-type:x mandatory; margin-right:-14px; padding:2px 14px 8px 0; gap:14px; scrollbar-width:none; }
                    .oscp-cards::-webkit-scrollbar { display:none; }
                    .oscp-card { flex:0 0 78%; scroll-snap-align:start; }
                    .oscp-cats { grid-template-columns:minmax(0,1fr); }
                    .oscp-sec { margin-top:42px; }
                }
                @media (prefers-reduced-motion: reduce) {
                    .oscp-bub.on::after { animation:none; transform:scale(1); }
                    .oscp-slide, .oscp-card, .oscp-slide-art .oscp-art { transition:none; }
                }
            </style>

            <?php
            $ico_q = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14"/><circle cx="12" cy="17" r=".6" fill="currentColor"/></svg>';
            $ico_t = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2M9 2h6"/></svg>';
            $ico_c = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>';
            $ico_go = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
            ?>
            <div class="oscp" id="<?php echo esc_attr($uid); ?>"><div class="oscp-wrap">

                <!-- GİRİŞ -->
                <section class="oscp-hero" aria-labelledby="<?php echo esc_attr($uid); ?>-h">
                    <div>
                        <h1 id="<?php echo esc_attr($uid); ?>-h">Bugün hangi testi çözüyorsun?</h1>
                        <p class="oscp-lead">İlkokuldan KPSS'ye kadar online testler. Çöz, sonucunu hemen gör, yanlışlarını incele.</p>
                        <form class="oscp-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                            <label for="<?php echo esc_attr($uid); ?>-s" style="position:absolute;left:-9999px;">Test ara</label>
                            <input type="search" id="<?php echo esc_attr($uid); ?>-s" name="s" placeholder="Ders, sınıf ya da konu ara" autocomplete="off">
                            <button type="submit">Ara</button>
                        </form>
                        <?php if ($categories): ?>
                        <nav class="oscp-levels" aria-label="Kategoriler">
                            <?php foreach ($categories as $cat): ?>
                                <a class="oscp-level" href="#<?php echo esc_attr($uid . '-c' . $cat->term_id); ?>" title="<?php echo esc_attr($cat->name); ?>"><?php echo esc_html($this->osc_short_cat($cat->name)); ?></a>
                            <?php endforeach; ?>
                        </nav>
                        <?php endif; ?>
                    </div>
                    <div class="oscp-sheet" aria-hidden="true">
                        <div class="oscp-sheet-top"><span>Cevap kâğıdı</span><span>A kitapçığı</span></div>
                        <?php $marks = array(2, 4, 1, 3, 0); foreach ($marks as $r => $mk): ?>
                            <div class="oscp-row"><b><?php echo $r + 1; ?></b>
                                <?php foreach (array('A', 'B', 'C', 'D', 'E') as $c => $L): ?>
                                    <span class="oscp-bub<?php echo $c === $mk ? ' on' : ''; ?>" style="--d:<?php echo number_format(0.35 + $r * 0.22, 2, '.', ''); ?>s"><?php echo $L; ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="oscp-sheet-stats">
                            <div><strong><?php echo $fmt($total_tests); ?></strong>test</div>
                            <div><strong><?php echo $fmt(count($categories)); ?></strong>kategori</div>
                            <?php if ($week_total > 0): ?><div><strong><?php echo $fmt($week_total); ?></strong>bu hafta çözüldü</div><?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- MANŞET -->
                <section class="oscp-sec" style="margin-top:28px">
                    <div class="oscp-feature" aria-roledescription="carousel" aria-label="Öne çıkan testler">
                        <?php if ($all_slides): ?>
                        <div class="oscp-stage" data-stage>
                            <?php foreach ($all_slides as $k => $item):
                                if ($item['type'] === 'post'):
                                    $p = $item['data']; $ci = $this->osc_card_info($p);
                                    $pc = get_the_category($p->ID); $cat_name = !empty($pc) ? $this->osc_short_cat($pc[0]->name) : 'Test'; ?>
                                <a class="oscp-slide<?php echo $k === 0 ? ' is-on' : ''; ?>" href="<?php echo esc_url(get_permalink($p)); ?>" data-slide aria-label="<?php echo esc_attr(get_the_title($p)); ?>">
                                    <span class="oscp-slide-text">
                                        <span class="oscp-cat"><?php echo esc_html($cat_name); ?></span>
                                        <h3><?php echo esc_html(get_the_title($p)); ?></h3>
                                        <span class="oscp-facts">
                                            <?php if ($ci['q']): ?><span class="oscp-fact"><?php echo $ico_q; ?><?php echo intval($ci['q']); ?> soru</span><?php endif; ?>
                                            <?php if ($ci['min']): ?><span class="oscp-fact"><?php echo $ico_t; ?><?php echo intval($ci['min']); ?> dakika</span><?php endif; ?>
                                            <span class="oscp-fact"><?php echo $ico_c; ?><?php echo esc_html($ago($p)); ?></span>
                                        </span>
                                        <span class="oscp-cta">Testi çöz</span>
                                    </span>
                                    <span class="oscp-slide-art"><?php echo $this->osc_test_art($p, 'lg'); ?></span>
                                </a>
                                <?php else: ?>
                                <div class="oscp-slide oscp-sponsor<?php echo $k === 0 ? ' is-on' : ''; ?>" data-slide data-sponsor>
                                    <div><small>Sponsorlu</small>
                                    <div style="width:100%;max-height:100%;overflow:hidden;display:flex;justify-content:center;align-items:center;"><?php echo stripslashes($item['data']); ?></div></div>
                                </div>
                                <?php endif;
                            endforeach; ?>
                        </div>
                        <div class="oscp-nav" role="tablist">
                            <?php $num = 1; foreach ($all_slides as $k => $item):
                                $is_post = $item['type'] === 'post';
                                $label = $is_post ? $num++ : 'R';
                                $txt = $is_post ? get_the_title($item['data']) : 'Sponsorlu'; ?>
                                <button type="button" class="oscp-dot<?php echo $k === 0 ? ' is-on' : ''; ?>" data-go="<?php echo $k; ?>" role="tab" aria-selected="<?php echo $k === 0 ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr($txt); ?>"><i><?php echo esc_html($label); ?></i><span><?php echo esc_html($txt); ?></span></button>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                            <div class="oscp-stage"><div class="oscp-slide is-on"><span class="oscp-slide-text"><h3>İlk test eklendiğinde burada görünecek.</h3></span></div></div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php $manset_ad = get_option('osc_ad_homepage_manset', ''); if (!empty($manset_ad)): ?>
                <div class="oscp-ad"><div><?php echo stripslashes($manset_ad); ?></div></div>
                <?php endif; ?>

                <?php if ($latest): ?>
                <!-- SON EKLENENLER -->
                <section class="oscp-sec" aria-labelledby="<?php echo esc_attr($uid); ?>-l">
                    <div class="oscp-sec-head"><div><h2 id="<?php echo esc_attr($uid); ?>-l">Son eklenenler</h2><p>Siteye en son eklenen testler.</p></div></div>
                    <div class="oscp-cards">
                        <?php foreach ($latest as $p): $ci = $this->osc_card_info($p); $pc = get_the_category($p->ID); ?>
                        <?php $pcol = $this->osc_post_color($p); ?>
                        <a class="oscp-card" href="<?php echo esc_url(get_permalink($p)); ?>" style="--a:<?php echo $pcol[0]; ?>;--b:<?php echo $pcol[1]; ?>">
                            <span class="oscp-card-top">
                                <?php if (!empty($pc)): ?><span class="oscp-card-tag"><?php echo esc_html($this->osc_short_cat($pc[0]->name)); ?></span><?php endif; ?>
                                <?php if ($is_new($p)): ?><span class="oscp-card-new">Yeni</span><?php endif; ?>
                            </span>
                            <span class="oscp-card-body">
                                <h3><?php echo esc_html(get_the_title($p)); ?></h3>
                                <span class="oscp-chips">
                                    <?php if ($ci['q']): ?><span class="oscp-chip"><?php echo $ico_q; ?><?php echo intval($ci['q']); ?> soru</span><?php endif; ?>
                                    <span class="oscp-chip"><?php echo $ico_c; ?><?php echo esc_html($ago($p)); ?></span>
                                </span>
                            </span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php
                $pop_rows = array();
                foreach ($popular as $p) { $wk = intval(get_post_meta($p->ID, $week_key, true)); if ($wk > 0) $pop_rows[] = array($p, $wk); }
                if ($pop_rows): ?>
                <!-- BU HAFTA EN ÇOK ÇÖZÜLENLER -->
                <section class="oscp-sec" aria-labelledby="<?php echo esc_attr($uid); ?>-p">
                    <div class="oscp-sec-head"><div><h2 id="<?php echo esc_attr($uid); ?>-p">Bu hafta en çok çözülenler</h2><p>Bu hafta en çok çözülen testlerin sıralaması.</p></div></div>
                    <ol class="oscp-pop">
                        <?php foreach ($pop_rows as $i => $row): list($p, $wk) = $row; ?>
                        <li><a href="<?php echo esc_url(get_permalink($p)); ?>">
                            <span class="oscp-rank"><?php echo $i + 1; ?></span>
                            <?php echo $this->osc_test_art($p, 'sm'); ?>
                            <span class="oscp-body"><h3><?php echo esc_html(get_the_title($p)); ?></h3><span class="oscp-heat"><b><?php echo $fmt($wk); ?></b> kez çözüldü</span></span>
                        </a></li>
                        <?php endforeach; ?>
                    </ol>
                </section>
                <?php endif; ?>

                <?php if ($categories): ?>
                <!-- KATEGORİLER -->
                <section class="oscp-sec" aria-labelledby="<?php echo esc_attr($uid); ?>-k">
                    <div class="oscp-sec-head"><div><h2 id="<?php echo esc_attr($uid); ?>-k">Kategoriler</h2><p>Sınıfını ya da sınavını seç, en yeni testlerden başla.</p></div></div>
                    <div class="oscp-cats">
                        <?php foreach ($categories as $cat):
                            $cq = new WP_Query(array('post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 5, 'cat' => $cat->term_id, 'orderby' => 'date', 'order' => 'DESC', 'ignore_sticky_posts' => true));
                            $cs = $this->osc_cat_color($cat->term_id); ?>
                        <div class="oscp-catbox" id="<?php echo esc_attr($uid . '-c' . $cat->term_id); ?>" style="--a:<?php echo $cs[0]; ?>;--b:<?php echo $cs[1]; ?>">
                            <div class="oscp-catbox-head">
                                <span class="oscp-catbox-icon" aria-hidden="true"><?php echo $this->osc_book_svg(); ?></span>
                                <h3 title="<?php echo esc_attr($cat->name); ?>"><?php echo esc_html($this->osc_short_cat($cat->name)); ?><small><?php echo $fmt(max(intval($cq->found_posts), intval($cat->count))); ?> test</small></h3>
                                <a class="oscp-more" href="<?php echo esc_url(get_category_link($cat->term_id)); ?>" aria-label="<?php echo esc_attr($cat->name); ?>: tüm testler">Tümü <?php echo $ico_go; ?></a>
                            </div>
                            <ul>
                                <?php foreach ($cq->posts as $p): $ci = $this->osc_card_info($p); ?>
                                <li class="oscp-row-test"><a href="<?php echo esc_url(get_permalink($p)); ?>">
                                    <?php echo $this->osc_test_art($p, 'sm'); ?>
                                    <span class="oscp-body"><h4><?php echo esc_html(get_the_title($p)); ?></h4>
                                        <span class="oscp-meta"><?php if ($is_new($p)): ?><span class="oscp-new">Yeni</span><?php endif; ?><?php if ($ci['q']): ?><span><?php echo intval($ci['q']); ?> soru</span><?php endif; ?><span><?php echo esc_html($ago($p)); ?></span></span></span>
                                    <span class="oscp-go" aria-hidden="true"><?php echo $ico_go; ?></span>
                                </a></li>
                                <?php endforeach; if (!$cq->posts): ?><li class="oscp-empty">Bu kategoride henüz test yok.</li><?php endif; ?>
                            </ul>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
            </div></div>

            <script>
            (function () {
                var root = document.getElementById('<?php echo esc_js($uid); ?>'); if (!root) return;
                var slides = root.querySelectorAll('[data-slide]'), dots = root.querySelectorAll('[data-go]'), feat = root.querySelector('.oscp-feature'), cur = 0, timer = null, paused = false;
                var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                function show(n) {
                    if (!slides.length) return;
                    cur = (n + slides.length) % slides.length;
                    slides.forEach(function (s, i) { s.classList.toggle('is-on', i === cur); s.setAttribute('aria-hidden', i === cur ? 'false' : 'true'); s.tabIndex = i === cur ? 0 : -1; });
                    dots.forEach(function (d, i) { d.classList.toggle('is-on', i === cur); d.setAttribute('aria-selected', i === cur ? 'true' : 'false'); });
                    if (feat) feat.classList.toggle('on-sponsor', slides[cur].hasAttribute('data-sponsor'));
                }
                function play() { clearInterval(timer); if (!reduce && slides.length > 1) timer = setInterval(function () { if (!paused) show(cur + 1); }, 6000); }
                dots.forEach(function (d) {
                    d.addEventListener('click', function () { show(+d.getAttribute('data-go')); play(); });
                    d.addEventListener('mouseenter', function () { show(+d.getAttribute('data-go')); });
                });
                if (feat) {
                    feat.addEventListener('mouseenter', function () { paused = true; });
                    feat.addEventListener('mouseleave', function () { paused = false; });
                    feat.addEventListener('focusin', function () { paused = true; });
                    feat.addEventListener('focusout', function () { paused = false; });
                    feat.addEventListener('keydown', function (e) { if (e.key === 'ArrowRight') { show(cur + 1); play(); } if (e.key === 'ArrowLeft') { show(cur - 1); play(); } });
                    var sx = 0, stage = root.querySelector('[data-stage]');
                    if (stage) {
                        stage.addEventListener('touchstart', function (e) { sx = e.changedTouches[0].screenX; }, { passive: true });
                        stage.addEventListener('touchend', function (e) { var dx = e.changedTouches[0].screenX - sx; if (dx < -50) { show(cur + 1); play(); } else if (dx > 50) { show(cur - 1); play(); } }, { passive: true });
                    }
                }
                show(0); play();

                // Ortalama: tema içeriği sola yaslı ve dar bir sütuna koyduysa, sayfayı ekranın tam ortasına al.
                // Sağda görünen bir kenar çubuğu varsa ya da üst kapsayıcı taşmayı kesiyorsa dokunma.
                function osCenter() {
                    root.classList.remove('oscp-bleed'); root.style.width = ''; root.style.marginLeft = '';
                    var vw = document.documentElement.clientWidth, r = root.getBoundingClientRect();
                    if (r.width < 50) return;
                    var gapL = r.left, gapR = vw - r.right;
                    if (Math.abs(gapL - gapR) < 24 && r.width >= Math.min(vw - 40, 1180)) return; // zaten ortalı ve geniş
                    var sb = document.querySelectorAll('#secondary,#sidebar,#right-sidebar,.sidebar,aside,.widget-area,[class*="sidebar"]');
                    for (var i = 0; i < sb.length; i++) {
                        var el = sb[i]; if (root.contains(el) || el.contains(root) || el.closest('footer,.site-footer,#colophon,header,.site-header')) continue;
                        var q = el.getBoundingClientRect();
                        if (q.width > 60 && q.height > 60 && q.top < r.bottom && q.bottom > r.top && (q.left >= r.right - 10 || q.right <= r.left + 10)) return; // yanında görünür sidebar var
                    }
                    for (var a = root.parentElement; a && a !== document.body; a = a.parentElement) {
                        var cs = getComputedStyle(a), ar = a.getBoundingClientRect();
                        if ((cs.overflowX === 'hidden' || cs.overflowX === 'clip' || cs.overflow === 'hidden') && (ar.left > 1 || ar.right < vw - 1)) return;
                    }
                    root.classList.add('oscp-bleed');
                    root.style.width = vw + 'px';
                    root.style.marginLeft = (-r.left + (parseFloat(root.style.marginLeft) || 0)) + 'px';
                }
                osCenter();
                var rt; window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(osCenter, 120); });
                window.addEventListener('load', osCenter);
            })();
            </script>
            <?php
            return ob_get_clean();
        }

        // Tam genişlik: [osc_anasayfa] kısa kodunu içeren tekil sayfalarda temanın sidebar'lı şablonu yerine eklentinin ortalı şablonu
        private function fw_applies() {
            if (is_admin() || get_option('osc_home_keep_sidebar') == 1 || !is_singular()) return false;
            if (function_exists('wp_is_block_theme') && wp_is_block_theme()) return false;
            $p = get_queried_object();
            return ($p && !empty($p->post_content) && has_shortcode($p->post_content, 'osc_anasayfa'));
        }
        public function fw_template_include($template) {
            if (!$this->fw_applies()) return $template;
            if (!defined('OSC_FW_TPL')) define('OSC_FW_TPL', 1);
            return __FILE__;
        }
        public function fw_body_class($classes) {
            if ($this->fw_applies()) { $classes[] = 'osc-fw-page'; $classes[] = 'no-sidebar'; $classes[] = 'full-width'; $classes = array_diff($classes, array('has-sidebar', 'right-sidebar', 'sidebar-right')); }
            return $classes;
        }

        // --- DİĞER FONKSİYONLAR ---
      public function setup_cpt() {
            register_post_type('osc_quiz', array(
                'labels' => array('name' => 'Online Testler', 'singular_name' => 'Test'),
                'public' => false, // Google'dan ve dışarıdan erişimi tamamen kapatır
                'show_ui' => true, // Sadece Admin panelinde senin görmeni sağlar
                'publicly_queryable' => false, // URL ile direkt girilmesini engeller (404 verir)
                'exclude_from_search' => true, 
                'has_archive' => false, 
                'taxonomies' => array('category'), 
                'supports' => array('title', 'thumbnail', 'excerpt', 'editor'),
                'show_in_rest' => true, 
                'capability_type' => 'post', 
                'map_meta_cap' => true,
            ));
        }

        public function check_rewrite_rules() {
            if (!get_option('osc_v19_flush_done')) { flush_rewrite_rules(); update_option('osc_v19_flush_done', true); }
        }

        public function add_quiz_to_category_pages($query) {
            if ( !is_admin() && $query->is_category() && $query->is_main_query() ) { $query->set( 'post_type', array( 'post', 'osc_quiz' ) ); }
        }

        public function add_shortcode_column($columns) {
            $new_columns = array();
            foreach($columns as $key => $title) {
                $new_columns[$key] = $title;
                if ($key === 'title') { $new_columns['shortcode'] = 'Kısa Kod (Kopyala)'; }
            }
            return $new_columns;
        }

        public function display_shortcode_column($column, $post_id) {
            if ($column === 'shortcode') {
                echo '<input type="text" readonly value="[osc_quiz id=&quot;' . $post_id . '&quot;]" style="width:100%; background:#f0f0f0; border:1px solid #ddd; padding:5px; font-family:monospace; color:#d63638;" onclick="this.select()">';
            }
        }

        public function inject_quiz($content) {
            if(get_option('osc_disable_auto_inject') == 1) { return $content; }
            if (is_singular('osc_quiz') && in_the_loop() && is_main_query()) { return $content . $this->get_quiz_html(get_the_ID()); }
            return $content;
        }

        public function single_quiz_shortcode($atts) {
            $atts = shortcode_atts(array('id' => 0), $atts, 'osc_quiz');
            if(!$atts['id']) return '';
            return $this->get_quiz_html($atts['id']);
        }

        public function get_quiz_html($post_id) {
            $data = get_post_meta($post_id, '_osc_data', true);
            $settings = get_post_meta($post_id, '_osc_settings', true);
            if (empty($data) || !is_array($data)) return '<div style="padding:20px; background:#fff3cd; color:#856404; border:1px solid #ffeeba;">Bu teste henüz soru eklenmemiş. (ID: '.$post_id.')</div>';
            
                        foreach($data as &$q) { 
                if(!isset($q['type'])) $q['type'] = 'multiple'; 
                if(!isset($q['root'])) $q['root'] = isset($q['text']) ? $q['text'] : ''; 
                if(!isset($q['paragraph'])) $q['paragraph'] = ''; 
                                                                // SADECE CÜMLE SONUNDAKİ (EN SONDAYA YER ALAN) OLUMSUZ FİİLLERİ VURGULAR
                $pattern = '/(^|[^a-zA-ZğüşıöçĞÜŞİÖÇ])([a-zA-ZğüşıöçĞÜŞİÖÇ]+(?:maz|mez|mam[ıI][şŞ]t[ıI]r|mem[iİ][şŞ]t[iİ]r|mamaktad[ıI]r|memektedir|mad[ıI][ğĞ][ıI]|med[iİ][ğĞ][iİ]|mayan|meyen|m[ıI]yor|m[iİ]yor)|de[ğĞ][iİ]ld[iİ]r|de[ğĞ][iİ]l|yoktur|yanl[ıI][şŞ]t[ıI]r)(?=[^a-zA-ZğüşıöçĞÜŞİÖÇ]*$)/ui';



                // Geçmiş testlerde paragrafta kalmış olası altı çizili kelimeleri tamamen temizle
                if(!empty($q['paragraph'])) {
                    $q['paragraph'] = preg_replace('/<u>\s*<strong>(.*?)<\/strong>\s*<\/u>/ui', '$1', $q['paragraph']);
                }

                // Soru kökündeki eski etiketleri temizle ve yeni kusursuz pattern ile tekrar uygula
                $q['root'] = preg_replace('/<u>\s*<strong>(.*?)<\/strong>\s*<\/u>/ui', '$1', $q['root']);
                $q['root'] = preg_replace($pattern, '$1<u><strong>$2</strong></u>', $q['root']);

            }
            unset($q); // DİKKAT: PHP referans hatasını (son sorunun kopyalanması) çözen kritik ekleme!
            
            $view_mode = isset($settings['view_mode']) ? $settings['view_mode'] : 'step'; 

            $global_ad_code = get_option('osc_global_ad_code', '');
            $global_ad_freq = get_option('osc_global_ad_freq', 0);
            $ad_code = (isset($settings['ad_code']) && !empty($settings['ad_code'])) ? $settings['ad_code'] : $global_ad_code;
            $ad_freq = (isset($settings['ad_freq']) && $settings['ad_freq'] !== '') ? intval($settings['ad_freq']) : intval($global_ad_freq);

                        // YENİ: Admin panelden özel reklam kodlarını çekiyoruz
            $ad_start = get_option('osc_ad_start_screen', '');
            $ad_result = get_option('osc_ad_result_screen', '');
            $ad_mistakes = get_option('osc_ad_mistakes_screen', '');

            $duration_min = isset($settings['duration']) ? intval($settings['duration']) : 0;
            $duration_display = 'Süresiz';

            if($duration_min > 0) {
                if($duration_min > 60) {
                    $h = floor($duration_min / 60);
                    $m = $duration_min % 60;
                    $duration_display = "{$h} sa {$m} dk";
                } else {
                    $duration_display = "{$duration_min} dk";
                }
            }

                        $motivation_text = "Başarılar dileriz";

            // --- SCHEMA.ORG (JSON-LD) ENTEGRASYONU BAŞLANGICI ---
            $schema_questions = [];
            foreach($data as $q) {
                $answers = [];
                $correct_answer = null;
                $opts = $q['type'] === 'truefalse' ? ['A'=>'Doğru', 'B'=>'Yanlış'] : (isset($q['options']) ? $q['options'] : []);
                
                if (is_array($opts) && !in_array($q['type'], ['gap','classic'])) {
                    foreach($opts as $k => $val) {
                        if(!$val) continue;
                        $answer_obj = [
                            "@type" => "Answer",
                            "text" => wp_strip_all_tags($val),
                            "encodingFormat" => "text/html"
                        ];
                        if ($k === $q['correct']) {
                            $correct_answer = $answer_obj;
                        } else {
                            $answers[] = $answer_obj;
                        }
                    }
                }

                // Boşluk doldurma (gap) soruları için özel yapı
                if(in_array($q['type'], ['gap','classic'])) {
                    $correct_answer = [
                        "@type" => "Answer",
                        "text" => wp_strip_all_tags(isset($q['options']['A']) ? $q['options']['A'] : ''),
                        "encodingFormat" => "text/html"
                    ];
                }

                $schema_questions[] = [
                    "@type" => "Question",
                    "eduQuestionType" => $q['type'] === 'multiple' ? "Multiple choice" : ($q['type'] === 'truefalse' ? "True/False" : ($q['type'] === 'classic' ? "Open-ended" : "Fill in the blank")),
                    "text" => wp_strip_all_tags($q['root'] . ' ' . $q['paragraph']),
                    "acceptedAnswer" => $correct_answer,
                    "suggestedAnswer" => $answers
                ];
            }

            $schema = [
                "@context" => "https://schema.org",
                "@type" => "Quiz",
                "name" => get_the_title($post_id),
                "hasPart" => $schema_questions
            ];
            $schema_json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            // --- SCHEMA.ORG BİTİŞİ ---

            $config = json_encode(['questions' => $data, 'settings' => $settings, 'ad_freq' => $ad_freq, 'ad_code' => $ad_code]);
            ob_start();
            ?>
            <script type="application/ld+json">
            <?php echo $schema_json; ?>
            </script>
            
            <div id="osc-app-<?php echo $post_id; ?>" class="osc-wrapper">

                <div id="osc-ad-modal-<?php echo $post_id; ?>" class="osc-ad-overlay" style="display:none;">
                    <div class="osc-ad-content"><h3>Sponsorlu Bağlantı</h3><div class="osc-ad-slot"><?php echo $ad_code ? stripslashes($ad_code) : 'Reklam Alanı'; ?></div><button class="osc-btn-skip" onclick="oscCloseAd(<?php echo $post_id; ?>)">Reklamı Geç ve Devam Et ➔</button></div>
                </div>
                <?php if($view_mode === 'list'): ?>
                    <div class="osc-list-view">
                        <div class="osc-header-modern"><div class="osc-badges"><span class="osc-badge info">Liste Modu</span><span class="osc-badge warning"><?php echo count($data); ?> Soru</span></div></div>
                        <?php foreach($data as $i => $q): ?>
                            <div class="osc-list-item" id="osc-list-q-<?php echo $i; ?>">
                                <div class="osc-q-num">SORU <?php echo $i+1; ?></div>
                                <?php if(!empty($q['paragraph'])): ?><div class="osc-q-paragraph"><?php echo $q['paragraph']; ?></div><?php endif; ?>
                                <div class="osc-q-root"><?php echo $q['root']; ?></div>
                                <div class="osc-list-opts">
                                    <?php if($q['type'] === 'classic'): ?>
                                        <div class="osc-gap-container" style="display:block"><textarea id="osc-list-cl-<?php echo $i; ?>" class="osc-classic-input" rows="3" placeholder="Cevabınızı buraya yazın..."></textarea><button class="osc-btn-small" style="margin:8px 0 0 0" onclick="showListClassic(<?php echo $i; ?>)">Kontrol Et</button><div id="osc-list-cl-ans-<?php echo $i; ?>" style="display:none"></div></div>
                                    <?php elseif($q['type'] === 'gap'): ?>
                                        <div class="osc-gap-container"><input type="text" id="osc-list-gap-<?php echo $i; ?>" class="osc-gap-input" placeholder="Cevabınız..." onchange="checkListGap(<?php echo $i; ?>)"><button class="osc-btn-small" onclick="checkListGap(<?php echo $i; ?>)">Kontrol Et</button><div id="osc-list-fb-<?php echo $i; ?>" class="osc-fb-text"></div></div>
                                    <?php else: $opts = $q['type'] === 'truefalse' ? ['A'=>'Doğru', 'B'=>'Yanlış'] : $q['options']; foreach($opts as $k => $val): if(!$val) continue; ?><button class="osc-opt-btn list-btn-<?php echo $i; ?>" onclick="checkListOpt(<?php echo $i; ?>, '<?php echo $k; ?>', this)"><div class="osc-opt-char"><?php echo $k; ?></div><div class="osc-opt-text"><?php echo $val; ?></div></button><?php endforeach; endif; ?>
                                </div>
                            </div>
                            <?php if($ad_freq > 0 && !empty($ad_code) && ($i+1) % $ad_freq === 0 && ($i+1) != count($data)): ?><div class="osc-ad-separator"><span>REKLAM</span><?php echo stripslashes($ad_code); ?></div><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- MODERN TASARIM BAŞLANGIÇ EKRANI -->
                    <div class="osc-screen osc-start active">
                        <div class="osc-card osc-start-card">
                            <div class="osc-card-bg-header"></div>
                            <div class="osc-card-icon-wrap">
                                <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><line x1="10" y1="9" x2="8" y2="9"></line></svg>
                            </div>
                            <div class="osc-card-body">
                                                                <h2 class="osc-quiz-title"><?php echo get_the_title($post_id); ?></h2>
                                <div class="osc-modern-stats">
                                    <div class="osc-m-stat"><span class="dashicons dashicons-format-aside"></span> <?php echo count($data); ?> Soru</div>
                                    <div class="osc-m-stat"><span class="dashicons dashicons-clock"></span> <?php echo $duration_display; ?></div>
                                </div>
                                
                                <?php 
                                $q_count = count($data);
                                $seo_title = get_the_title($post_id);
                                $seo_desc = "<strong>{$seo_title}</strong> konulu bu online test toplam <strong>{$q_count} sorudan</strong> oluşmaktadır. ";
                                $seo_desc .= ($duration_min > 0) ? "Testi tamamlamanız için belirlenen süre <strong>{$duration_display}</strong>'dir. " : "Bu test için herhangi bir süre sınırı bulunmamaktadır. ";
                                $seo_desc .= "Soruları dikkatle okuyarak online testinizi bitirin, sonuçları anında görün ve Soruİndir liderlik tablosundaki yerinizi hemen alın!";
                                ?>
                                <p style="font-size: 13.5px; color: #475569; margin: 0 0 25px 0; line-height: 1.6; font-weight: 500; background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px dashed #cbd5e1; text-align: left;">
                                    <span class="dashicons dashicons-info-outline" style="color:#3b82f6; font-size: 16px; width: 16px; height: 16px; margin-top:2px;"></span> <?php echo $seo_desc; ?>
                                </p>

                                <div class="osc-motivation-badge"><?php echo esc_html($motivation_text); ?></div>
                                                                                                <button class="osc-btn-modern-start pulse-anim" onclick="oscRun(<?php echo $post_id; ?>)">
                                    TESTİ BAŞLAT <span class="dashicons dashicons-arrow-right-alt2" style="margin-top:4px;"></span>
                                </button>
                                
                                <?php if(!empty($ad_start)): ?>
                                <div style="margin-top:20px; text-align:center; display:flex; justify-content:center;">
                                    <?php echo stripslashes($ad_start); ?>
                                </div>
                                <?php endif; ?>

                                <?php
                                $start_entries = get_post_meta($post_id, '_osc_lb_entry', false);
                                if (!empty($start_entries) && is_array($start_entries)) {
                                    // Puanları büyükten küçüğe sırala
                                    usort($start_entries, function($a, $b) { return $b['score'] - $a['score']; });
                                    // Ekranda çok yer kaplamaması için sadece ilk 5'i al
                                    $top5 = array_slice($start_entries, 0, 5);
                                    ?>
                                    <div class="osc-start-leaderboard" style="margin-top: 25px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 15px; text-align: left; animation: oscFadeIn 0.5s;">
                                        <h4 style="margin: 0 0 15px 0; color: #475569; font-size: 14px; font-weight: 800; text-align: center; display: flex; align-items: center; justify-content: center; gap: 6px;">
                                            <span class="dashicons dashicons-awards" style="color:#f59e0b;"></span> EN İYİLER
                                        </h4>
                                        <div style="display: flex; flex-direction: column; gap: 8px;">
                                            <?php foreach($top5 as $index => $l): 
                                                $rankClass = ''; $rankIcon = ($index + 1) . '.';
                                                if($index === 0) { $rankClass = 'top-1'; $rankIcon = '🥇'; }
                                                elseif($index === 1) { $rankClass = 'top-2'; $rankIcon = '🥈'; }
                                                elseif($index === 2) { $rankClass = 'top-3'; $rankIcon = '🥉'; }
                                            ?>
                                                <div class="osc-lb-item <?php echo $rankClass; ?>" style="padding: 8px 12px; font-size: 13px; margin-bottom: 0;">
                                                    <div class="osc-lb-rank" style="width: 25px; font-size: 14px;"><?php echo $rankIcon; ?></div>
                                                    <div class="osc-lb-name" style="font-size: 13px;"><?php echo esc_html($l['name']); ?></div>
                                                    <div class="osc-lb-score" style="padding: 4px 8px; font-size: 12px;">%<?php echo intval($l['score']); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php } ?>

                            </div>

                        </div>
                    </div>


                   <div class="osc-screen osc-quiz" style="display:none;">
    <div id="osc-fs-bar-<?php echo $post_id; ?>" style="display:none; position: sticky; top: 15px; z-index: 9999; text-align: center; margin-bottom: 25px; pointer-events: none;">
        <button onclick="oscExitFS()" style="pointer-events: auto; background: linear-gradient(135deg, rgba(59, 130, 246, 0.9) 0%, rgba(99, 102, 241, 0.9) 100%); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.3); color: #fff; padding: 10px 24px; border-radius: 50px; font-size: 14.5px; font-weight: 700; cursor: pointer; box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4); display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); letter-spacing: 0.5px; margin: 0 auto;">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"></path></svg>
            Tam Ekrandan Çık
        </button>
    </div>
    <div class="osc-top-bar">

                            <div class="osc-progress-text">Soru <span id="osc-curr-<?php echo $post_id; ?>">1</span> / <?php echo count($data); ?></div>
                            
                            <div class="osc-timer-pill">⏳ <span id="osc-timer-<?php echo $post_id; ?>">--:--</span></div>
                            
                            <div class="osc-joker-container">
                                <button id="osc-joker-btn-<?php echo $post_id; ?>" class="osc-joker-btn" onclick="useJoker(<?php echo $post_id; ?>)" title="50:50 Joker">
                                    <span class="dashicons dashicons-lightbulb"></span> 50:50
                                </button>
                                <button id="osc-skip-btn-<?php echo $post_id; ?>" class="osc-joker-btn" onclick="useSkipJoker(<?php echo $post_id; ?>)" style="background: linear-gradient(135deg, #3b82f6, #2563eb);" title="Pas Geç">
                                    <span class="dashicons dashicons-controls-forward"></span> PAS GEÇ
                                </button>
                            </div>
                        </div>
                        <div class="osc-progress-track"><div id="osc-bar-fill-<?php echo $post_id; ?>" style="width:0%"></div></div>
                        <div id="osc-render-area-<?php echo $post_id; ?>" class="osc-question-area"></div>
                    </div>

                    <!-- MODERN TASARIM SONUÇ EKRANI -->
                    <div class="osc-screen osc-result" style="display:none;">
                        <div class="osc-card osc-result-card">
                            <div class="osc-score-circle-wrap">
                                <div class="osc-score-circle">
                                    <span id="osc-final-score-<?php echo $post_id; ?>">0</span>
                                    <small>PUAN</small>
                                </div>
                            </div>
                            <h3 class="osc-result-title">Test Tamamlandı</h3>
                                                     <div id="osc-name-input-area-<?php echo $post_id; ?>" class="osc-score-save-box">
                                <h4 style="margin: 0 0 8px 0; color: #4c1d95; font-size: 17px; font-weight: 900; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                    <span class="dashicons dashicons-trophy" style="color: #f59e0b; font-size: 26px; width: 26px; height: 26px;"></span> 
                                    Puanını Kaydet, Sıralamaya Gir!
                                </h4>
                                <p style="font-size: 13px; color: #6d28d9; margin-top: 0; margin-bottom: 20px; font-weight: 600; opacity: 0.85;">
                                    Testi tamamladın! Liderlik tablosundaki yerini almak ve diğerleriyle yarışmak için adını yazabilirsin.
                                </p>
                                <div class="osc-score-save-form">
                                    <input type="text" id="osc-student-name-<?php echo $post_id; ?>" class="osc-score-input" placeholder="Adın Soyadın...">
                                    
                                    <button onclick="oscSaveScore(<?php echo $post_id; ?>)" class="osc-score-btn">
                                        Kaydet <span class="dashicons dashicons-saved" style="font-size: 18px; width: 18px; height: 18px;"></span>
                                    </button>
                                </div>
                            </div>

                            <div class="osc-stat-modern-grid">
  <div class="osc-sm-box correct">
                                    <span class="lbl">DOĞRU</span>
                                    <span class="val" id="osc-stat-correct-<?php echo $post_id; ?>">0</span>
                                </div>
                                <div class="osc-sm-box wrong">
                                    <span class="lbl">YANLIŞ</span>
                                    <span class="val" id="osc-stat-wrong-<?php echo $post_id; ?>">0</span>
                                </div>
                                <div class="osc-sm-box empty">
                                    <span class="lbl">BOŞ</span>
                                    <span class="val" id="osc-stat-empty-<?php echo $post_id; ?>">0</span>
                                </div>
                                                                <div class="osc-sm-box net">
                                    <span class="lbl">NET</span>
                                    <span class="val" id="osc-stat-net-<?php echo $post_id; ?>">0</span>
                                </div>
                            </div>

                                                        <?php if(!empty($ad_result)): ?>
                            <div style="margin: 0 30px 25px 30px; text-align:center; display:flex; justify-content:center;">
                                <?php echo stripslashes($ad_result); ?>
                            </div>
                            <?php endif; ?>


                                                                 <div class="osc-actions">
                                <button class="osc-btn-modern-restart" onclick="oscShowMistakes(<?php echo $post_id; ?>)" style="margin-bottom:10px; width:100%; justify-content:center; background:#fef2f2; border-color:#fecaca; color:#991b1b;">

                                    <span class="dashicons dashicons-visibility"></span> Yanlışları İncele
                                </button>
                                <div id="osc-mistakes-area-<?php echo $post_id; ?>" style="display:none; text-align:left; margin-bottom:20px; padding:20px; background:#f8fafc; border-radius:12px; border:2px dashed #cbd5e1;"></div>

                                                                <button class="osc-btn-modern-restart" onclick="location.reload()" style="margin-bottom:10px; width:100%; justify-content:center;">
                                    <span class="dashicons dashicons-update"></span> Tekrar Çöz
                                </button>
                                
                                <button class="osc-btn-modern-restart" onclick="oscPrintPDF(<?php echo $post_id; ?>)" style="margin-bottom:20px; width:100%; justify-content:center; background:#eff6ff; border-color:#bfdbfe; color:#1e40af;">
                                    <span class="dashicons dashicons-media-document"></span> PDF İndir / Yazdır
                                </button>

                                
                                <div class="osc-share-buttons">

                                
                               <div class="osc-share-buttons">
                                    <p style="font-size:13px; font-weight:800; color:#64748b; margin-bottom:12px; text-transform:uppercase; letter-spacing:0.5px;">Paylaş & Sitene Ekle</p>
                                    <div style="display:flex; gap:12px; justify-content:center;">
                                        <button onclick="oscShare('whatsapp', <?php echo $post_id; ?>)" class="osc-share-btn osc-whatsapp" title="WhatsApp'ta Paylaş">
                                            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                                        </button>
                                        <button onclick="oscShare('twitter', <?php echo $post_id; ?>)" class="osc-share-btn osc-twitter" title="X'te Paylaş">
                                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                        </button>
                                        <button onclick="oscShare('facebook', <?php echo $post_id; ?>)" class="osc-share-btn osc-facebook" title="Facebook'ta Paylaş">
                                            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                        </button>
                                        <button onclick="oscShowEmbed(<?php echo $post_id; ?>)" class="osc-share-btn" title="Sitene Ekle" style="background: #475569; box-shadow: 0 5px 15px rgba(71, 85, 105, 0.3);">
                                            <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                                        </button>
                                    </div>
                                    
                                    <div id="osc-embed-area-<?php echo $post_id; ?>" style="display:none; margin-top:20px; padding:15px; background:#f8fafc; border:2px dashed #cbd5e1; border-radius:12px; text-align:left; animation: oscFadeIn 0.3s;">
                                        <p style="font-size:13px; color:#475569; margin:0 0 10px 0; font-weight:700;">Aşağıdaki kodu kopyalayıp sitenize ekleyin:</p>
                                        <textarea id="osc-embed-code-<?php echo $post_id; ?>" readonly style="width:100%; height:80px; padding:10px; border:1px solid #e2e8f0; border-radius:8px; font-family:monospace; font-size:12px; resize:none; color:#1e293b; background:#fff; outline:none;"></textarea>
                                        <button onclick="oscCopyEmbed(<?php echo $post_id; ?>)" id="osc-copy-btn-<?php echo $post_id; ?>" style="margin-top:10px; width:100%; padding:10px; background:#3b82f6; color:#fff; border:none; border-radius:8px; font-weight:bold; cursor:pointer; transition:0.3s; display:flex; justify-content:center; align-items:center; gap:8px;">
                                            <span class="dashicons dashicons-admin-page"></span> Kodu Kopyala
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                                                <div id="osc-leaderboard-<?php echo $post_id; ?>" style="display:none; margin-top:35px; padding:25px; background:#fff; border: 2px solid #e2e8f0; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
                            <h3 style="text-align:center; color:#1e293b; margin-top:0; margin-bottom:20px; font-weight:800; display:flex; align-items:center; justify-content:center; gap:10px;">
                                <span class="dashicons dashicons-awards" style="color:#f59e0b; font-size:28px; width:28px; height:28px;"></span> En İyiler Sıralaması
                            </h3>
                            <div class="osc-lb-list" id="osc-lb-list-<?php echo $post_id; ?>">
                                </div>
                        </div>
                        
                    </div>
                    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
                    
                    <style>
                        .osc-lb-item { display:flex; justify-content:space-between; align-items:center; padding:12px 15px; border-bottom:1px solid #f1f5f9; transition:0.3s; margin-bottom:5px; }
                        .osc-lb-item:last-child { border-bottom:none; margin-bottom:0; }
                        .osc-lb-item.top-1 { background:#fffbeb; border-radius:12px; font-weight:bold; color:#b45309; border:1px solid #fde68a; transform:scale(1.02); }
                        .osc-lb-item.top-2 { background:#f8fafc; border-radius:12px; font-weight:bold; color:#475569; border:1px solid #e2e8f0; }
                        .osc-lb-item.top-3 { background:#fff7ed; border-radius:12px; font-weight:bold; color:#c2410c; border:1px solid #ffedd5; }
                        .osc-lb-rank { width:35px; font-weight:800; font-size:16px; text-align:center; }
                        .osc-lb-name { flex:1; font-weight:700; text-align:left; padding-left:15px; font-size:15px; }
                        .osc-lb-score { font-weight:900; background:#10b981; color:#fff; padding:6px 12px; border-radius:20px; font-size:14px; box-shadow:0 2px 5px rgba(16,185,129,0.3); }
                    </style>



                <?php endif; ?>

            </div>
            <style>
                .osc-wrapper, .osc-wrapper * { box-sizing: border-box; } 
                .osc-wrapper { max-width: 100%; margin: 20px auto; font-family: 'Segoe UI', Roboto, sans-serif; background: #fff; border-radius: 16px; border: 1px solid #edf2f7; position: relative; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); } 
                               /* TAM EKRAN KAYDIRMA (SCROLL) VE MERKEZLEME ÇÖZÜMÜ */
                .osc-wrapper:fullscreen { overflow-y: auto !important; height: 100vh !important; max-height: 100vh !important; background: #f1f5f9 !important; margin: 0 !important; border-radius: 0 !important; }
                .osc-wrapper:-webkit-full-screen { overflow-y: auto !important; height: 100vh !important; max-height: 100vh !important; background: #f1f5f9 !important; margin: 0 !important; border-radius: 0 !important; }
                .osc-wrapper:-ms-fullscreen { overflow-y: auto !important; height: 100vh !important; max-height: 100vh !important; background: #f1f5f9 !important; margin: 0 !important; border-radius: 0 !important; }

                /* Tam ekranda göz yorulmasını engellemek için içeriği 900px ile sınırla ve ortala */
                .osc-wrapper:fullscreen .osc-screen { max-width: 900px !important; margin: 0 auto !important; background: #ffffff !important; min-height: 100vh; box-shadow: 0 0 50px rgba(0,0,0,0.1); }
                .osc-wrapper:-webkit-full-screen .osc-screen { max-width: 900px !important; margin: 0 auto !important; background: #ffffff !important; min-height: 100vh; box-shadow: 0 0 50px rgba(0,0,0,0.1); }
                .osc-wrapper:-ms-fullscreen .osc-screen { max-width: 900px !important; margin: 0 auto !important; background: #ffffff !important; min-height: 100vh; box-shadow: 0 0 50px rgba(0,0,0,0.1); }

                .osc-screen { padding: 40px; }
                @media(max-width:600px){ .osc-screen { padding: 20px; } }

                /* MODERN KART TASARIMI CSS */
                .osc-card { background: #fff; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); overflow: hidden; position: relative; text-align: center; margin: 0 auto; max-width: 500px; animation: oscSlideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
                @keyframes oscSlideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

               /* MODERN BAŞLANGIÇ EKRANI YENİ NESİL */
                .osc-card-bg-header { height: 140px; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); position: relative; overflow: hidden; }
                .osc-card-bg-header::after { content: ''; position: absolute; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.15) 10%, transparent 10.01%); background-size: 20px 20px; top: 0; left: 0; opacity: 0.6; }
                .osc-card-icon-wrap { width: 90px; height: 90px; background: linear-gradient(to bottom right, #ffffff, #f8fafc); border-radius: 22px; display: flex; align-items: center; justify-content: center; margin: -45px auto 15px; border: 4px solid #fff; box-shadow: 0 10px 25px -5px rgba(124, 58, 237, 0.4); position: relative; z-index: 2; transform: rotate(-5deg); transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
                .osc-card-icon-wrap:hover { transform: rotate(0deg) scale(1.08); }
                .osc-card-icon-wrap svg { stroke: #6d28d9; width: 45px; height: 45px; }
                .osc-card-body { padding: 10px 30px 40px; }
                .osc-quiz-title { font-size: 26px; font-weight: 800; color: #0f172a; margin: 10px 0 20px; line-height: 1.3; letter-spacing: -0.5px; }
                .osc-modern-stats { display: flex; justify-content: center; gap: 12px; margin-bottom: 25px; }
                .osc-m-stat { background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 12px; color: #475569; font-weight: 700; font-size: 13.5px; display: flex; align-items: center; gap: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
                .osc-m-stat .dashicons { color: #6366f1; }
                .osc-motivation-badge { background: #fffbeb; color: #d97706; font-weight: 800; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: 30px; margin-bottom: 30px; border: 1px solid #fde68a; text-transform: uppercase; letter-spacing: 0.5px; }
                .osc-motivation-badge::before { content: '✨'; font-size: 16px; }
                .osc-btn-modern-start { width: 100%; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; padding: 20px; border: none; border-radius: 16px; font-size: 19px; font-weight: 800; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; justify-content: center; gap: 12px; box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.5); text-transform: uppercase; letter-spacing: 0.5px; position: relative; overflow: hidden; }
                .osc-btn-modern-start:hover { transform: translateY(-4px); box-shadow: 0 15px 35px -5px rgba(16, 185, 129, 0.6); }
                .osc-btn-modern-start::after { content: ''; position: absolute; top: 0; left: -100%; width: 50%; height: 100%; background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.3) 50%, rgba(255,255,255,0) 100%); transform: skewX(-25deg); animation: oscShine 3s infinite; }
                @keyframes oscShine { 0% { left: -100%; } 20% { left: 200%; } 100% { left: 200%; } }

                /* Sonuç Ekranı */
                .osc-result-card { padding-top: 40px; padding-bottom: 40px; }
                
                /* YENİ PUAN KAYDETME KUTUSU TASARIMI */
                .osc-score-save-box { margin: 0 20px 30px 20px; background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); padding: 25px 20px; border-radius: 20px; border: 1px solid #ddd6fe; box-shadow: 0 10px 25px -5px rgba(139, 92, 246, 0.15); position: relative; overflow: hidden; }
                .osc-score-save-box::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #8b5cf6, #3b82f6); }
                .osc-score-save-form { display: flex; gap: 10px; justify-content: center; max-width: 420px; margin: 0 auto; }
                .osc-score-input { flex: 1; min-width: 0; padding: 14px 20px; border-radius: 50px; border: 2px solid #c4b5fd; font-size: 15px; font-weight: 600; color: #334155; outline: none; transition: 0.3s; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02); background: #fff; }
                .osc-score-input:focus { border-color: #8b5cf6; box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15); }
                .osc-score-btn { background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: #fff; border: none; padding: 0 25px; border-radius: 50px; font-weight: 800; font-size: 15px; cursor: pointer; transition: 0.3s; box-shadow: 0 6px 15px rgba(139, 92, 246, 0.4); display: inline-flex; align-items: center; justify-content: center; gap: 6px; letter-spacing: 0.5px; flex-shrink: 0; height: 50px; }
                .osc-score-btn:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(139, 92, 246, 0.6); }
                
                @media (max-width: 480px) {
                    .osc-score-save-box { margin: 0 10px 25px 10px; padding: 20px 15px; }
                    .osc-score-save-form { flex-direction: column; }
                    .osc-score-btn { width: 100%; }
                }
                .osc-score-circle-wrap { position: relative; width: 140px; height: 140px; margin: 0 auto 20px; }
                .osc-score-circle { width: 100%; height: 100%; border-radius: 50%; background: #fff; border: 8px solid #eff6ff; display: flex; flex-direction: column; align-items: center; justify-content: center; box-shadow: inset 0 0 20px rgba(0,0,0,0.05); }
                .osc-score-circle span { font-size: 42px; font-weight: 900; color: #2563eb; line-height: 1; }
                .osc-score-circle small { font-size: 12px; font-weight: 700; color: #94a3b8; letter-spacing: 1px; margin-top: 5px; }
                .osc-result-title { font-size: 20px; font-weight: 700; color: #334155; margin-bottom: 30px; }
                
                .osc-stat-modern-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; padding: 0 30px; margin-bottom: 30px; }
                .osc-sm-box { padding: 15px; border-radius: 12px; text-align: center; border: 1px solid transparent; }
                .osc-sm-box.correct { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
                .osc-sm-box.wrong { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
                .osc-sm-box.empty { background: #f8fafc; border-color: #e2e8f0; color: #475569; }
                .osc-sm-box.net { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
                .osc-sm-box .lbl { display: block; font-size: 11px; font-weight: 800; opacity: 0.8; margin-bottom: 4px; letter-spacing: 0.5px; }
                .osc-sm-box .val { font-size: 20px; font-weight: 900; }
                
                .osc-btn-modern-restart { background: #fff; border: 2px solid #e2e8f0; color: #475569; padding: 12px 30px; border-radius: 50px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; font-size: 15px; }
                                .osc-btn-modern-restart:hover { border-color: #94a3b8; background: #f8fafc; color: #1e293b; }
                
                .osc-share-buttons { margin-top: 20px; border-top: 2px dashed #f1f5f9; padding-top: 20px; }
                .osc-share-btn { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; color: #fff; transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
                .osc-share-btn:hover { transform: translateY(-4px) scale(1.05); }
                .osc-whatsapp { background: #25D366; box-shadow: 0 5px 15px rgba(37, 211, 102, 0.3); }
                .osc-twitter { background: #0f1419; box-shadow: 0 5px 15px rgba(15, 20, 25, 0.3); }
                .osc-facebook { background: #1877F2; box-shadow: 0 5px 15px rgba(24, 119, 242, 0.3); }

                                /* Genel Quiz Alanı - MODERNİZE EDİLMİŞ VERSİYON */
                .osc-question-area { animation: oscFadeInQ 0.5s cubic-bezier(0.16, 1, 0.3, 1); } 
                @keyframes oscFadeInQ { from { opacity: 0; transform: translateX(15px); } to { opacity: 1; transform: translateX(0); } }

               .osc-top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; width: 100%; padding-bottom: 15px; border-bottom: 2px dashed #e2e8f0; flex-wrap: wrap; }
                .osc-progress-text { font-weight: 800; color: #3b82f6; font-size: 14px; background: #eff6ff; padding: 6px 14px; border-radius: 30px; order: 1; }
                .osc-timer-pill { font-weight: 800; color: #ef4444; background: #fef2f2; padding: 6px 14px; border-radius: 30px; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 10px rgba(239, 68, 68, 0.1); order: 2; }
                .osc-joker-container { width: 100%; display: flex; justify-content: center; gap: 10px; margin-top: 15px; order: 3; }
                .osc-joker-btn { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; border: none; padding: 8px 16px; border-radius: 30px; font-size: 13px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2); transition: 0.2s; }
                .osc-joker-btn:hover { transform: translateY(-2px); }
                .osc-joker-btn.used { background: #e2e8f0 !important; color: #94a3b8 !important; box-shadow: none !important; cursor: not-allowed !important; transform: none !important; }
                @media (max-width: 480px) { 
                    .osc-timer-pill, .osc-progress-text { font-size: 12px; padding: 5px 10px; } 
                    .osc-joker-btn { font-size: 11px; padding: 6px 12px; } 
                }
                
                /* JOKER BUTONU TASARIMI */
                .osc-joker-btn { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; border: none; padding: 8px 18px; border-radius: 30px; font-size: 14px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); transition: 0.3s; margin: 0 auto; letter-spacing: 0.5px; }
                .osc-joker-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(245, 158, 11, 0.5); }
                .osc-joker-btn.used { background: #e2e8f0 !important; color: #94a3b8 !important; box-shadow: none !important; cursor: not-allowed !important; transform: none !important; }
                .osc-progress-track { height: 10px; background: #f1f5f9; border-radius: 10px; overflow: hidden; margin-bottom: 30px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1); }
                .osc-progress-track div { height: 100%; background: linear-gradient(90deg, #3b82f6, #2dd4bf); border-radius: 10px; transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1); }
                
               .osc-q-paragraph { 
    position: relative; 
    background: #ffffff; 
    padding: 24px 25px 24px 35px; 
    border-radius: 14px; 
    color: #1e293b; 
    margin-bottom: 25px; 
    font-size: 15.5px; 
    line-height: 1.7; 
    border: 1px solid #e2e8f0; 
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05); 
}
.osc-q-paragraph::before { 
    content: ''; 
    position: absolute; 
    top: -1px; 
    left: -1px; 
    width: 6px; 
    bottom: -1px; 
    background: linear-gradient(180deg, #3b82f6 0%, #8b5cf6 100%); 
    border-radius: 14px 0 0 14px; 
}
            .osc-q-root { font-weight: 800; color: #0f172a; font-size: 1.25em; margin-bottom: 25px; display: block; line-height: 1.5; }
                .osc-q-root u, .osc-q-paragraph u { text-decoration: none; }
                .osc-q-root u strong, .osc-q-paragraph u strong { font-weight: 900; color: #b91c1c; text-decoration: underline; text-decoration-color: #b91c1c; text-decoration-thickness: 3px; text-underline-offset: 3px; }

/* YENİ: Sorulara Eklenen Resimlerin Mobilde Taşmasını Engelleyen CSS */
.osc-q-paragraph img, .osc-q-root img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    display: block;
    margin: 15px auto;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
}

                
                .osc-opt-btn { display: flex !important; align-items: center; width: 100%; min-height: 65px; height: auto; padding: 12px 20px; margin-bottom: 15px; background: #fff; color: #334155; border: 2px solid #cbd5e1; border-radius: 16px; cursor: pointer; font-size: 16px; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); text-align: left; text-transform: none !important; position: relative; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); } 
               .osc-opt-btn:hover { border-color: #3b82f6; background: #f8fafc; color: #0f172a !important; transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.15); }
.osc-opt-btn:hover .osc-opt-text { color: #0f172a !important; }
.osc-opt-btn:hover .osc-opt-char { background: #3b82f6; color: #ffffff !important; border-color: #3b82f6; }
                .osc-opt-btn:active { transform: translateY(0) scale(0.98); }
                .osc-opt-char { display: flex; align-items: center; justify-content: center; width: 40px; min-width: 40px; height: 40px; background: #e2e8f0; color: #475569; border-radius: 12px; font-weight: 800; font-size: 16px; margin-right: 15px; flex-shrink: 0; transition: 0.3s; } 
                .osc-opt-text { flex: 1; line-height: 1.5; font-weight: 600; white-space: normal; word-wrap: break-word; }
                
                .osc-opt-btn.correct { border-color: #22c55e !important; background: #f0fdf4 !important; color: #166534 !important; font-weight: 800 !important; box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.2) !important; animation: oscPop 0.4s ease; } 
                .osc-opt-btn.correct .osc-opt-char { background: #22c55e; color: #fff; } 
                .osc-opt-btn.wrong { border-color: #ef4444 !important; background: #fef2f2 !important; color: #991b1b !important; font-weight: 800 !important; box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.2) !important; animation: oscShake 0.4s ease; } 
                .osc-opt-btn.wrong .osc-opt-char { background: #ef4444; color: #fff; }
                
                @keyframes oscPop { 0% { transform: scale(1); } 50% { transform: scale(1.03); } 100% { transform: scale(1); } }
                @keyframes oscShake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }

                .osc-btn-main { width: 100%; max-width: 100%; min-height: 60px; padding: 16px; background: linear-gradient(135deg, #10b981, #059669); color: #fff; border: none; border-radius: 16px; font-size: 18px; font-weight: 800; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); display: flex; justify-content: center; align-items: center; text-align: center; } 
                .osc-btn-main:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4); }

                .osc-q-paragraph img, .osc-q-root img, .osc-opt-text img, .osc-classic-fb img { max-width:100%; height:auto; vertical-align:middle; border-radius:6px; }
                .osc-q-paragraph img, .osc-q-root img { max-height:340px; display:inline-block; margin:6px 0; }
                .osc-opt-text img { max-height:120px; }
                .osc-q-table { border-collapse:collapse; margin:8px 0; max-width:100%; font-size:15px; }
                .osc-q-table td { border:1px solid #64748b; padding:5px 10px; }
                .osc-q-paragraph { overflow-x:auto; }
                .osc-classic-input { width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; box-sizing:border-box; }
                .osc-classic-ans { margin-top:10px; padding:12px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; color:#166534; text-align:left; }
                .osc-btn-small { padding: 10px 20px; background: #3b82f6; color: #fff; border:none; border-radius:8px; cursor:pointer; margin-left:10px; font-weight: 600; }
                .osc-btn-finish-early { width:100%; max-width: 100%; min-height: 50px; margin-top:25px; padding:12px; background: transparent; color:#94a3b8; border: 2px dashed #cbd5e1; border-radius:12px; font-weight:700; cursor:pointer; transition:0.3s; }
                .osc-btn-finish-early:hover { background:#f8fafc; color:#ef4444; border-color:#ef4444; }
                
                .osc-list-item { padding-bottom:30px; margin-bottom:30px; border-bottom:2px dashed #e2e8f0; } 
                .osc-q-num { color:#3b82f6; font-weight:800; margin-bottom:15px; font-size:16px; display: inline-block; background: #eff6ff; padding: 6px 12px; border-radius: 8px; }
                
                .osc-ad-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.95); backdrop-filter: blur(5px); z-index: 999; display: flex; align-items: center; justify-content: center; flex-direction: column; padding: 20px; } 
                .osc-ad-content { width: 100%; max-width: 400px; text-align: center; background: #fff; padding: 30px; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.1); } 
                .osc-ad-slot { margin: 20px 0; min-height: 250px; background: #f8fafc; display: flex; align-items: center; justify-content: center; border: 2px dashed #cbd5e1; border-radius: 12px; } 
                                .osc-btn-skip { background: #1e293b; color: #fff; border: none; padding: 12px 25px; border-radius: 30px; font-weight: 800; font-size: 15px; cursor: pointer; transition: 0.3s; width: 100%; }
                .osc-btn-skip:hover { background: #0f172a; transform: translateY(-2px); }

                                                /* YAZDIRMA VE PDF ÇIKTISI İÇİN ÖZEL CSS */
                @media screen {
                    /* Normal ekranda yazdırma alanını gizler, JS'in display:none yapmasına gerek kalmaz */
                    #osc-print-area { display: none !important; }
                }
                
                @media print {
                    /* Ekranda görünen diğer her şeyi fiziken kaldır ki boş sayfa yaratmasın */

                    body > *:not(#osc-print-area) { display: none !important; }
                    
                    /* Sadece çıktı alanını normal akışta göster */
                    #osc-print-area { display: block !important; width: 100%; padding: 0; margin: 0; box-sizing: border-box; }
                    #osc-print-area img { max-width: 100% !important; max-height: 230px; height: auto; vertical-align: middle; }
                    #osc-print-area table { border-collapse: collapse; margin: 4px 0; }
                    #osc-print-area td { border: 1px solid #333; padding: 3px 8px; }

                    
                                                            /* Sayfa Ortasındaki Dikey Filigran (soruindir.net) */
                    .osc-print-watermark {
                        position: fixed;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%) rotate(-90deg);
                        font-size: 20px; /* 36px'den 20px'e düşürüldü, daha zarif duracak */
                        color: #cbd5e1;
                        background: #fff; 
                        padding: 0 20px; /* Yazının etrafındaki beyaz boşluk orantılı küçültüldü */
                        font-weight: 800; /* Aşırı kalın durmaması için bir tık inceltildi */
                        letter-spacing: 8px; /* Harf arası boşluk orantılı daraltıldı */
                        z-index: 10; 
                        text-transform: lowercase; 
                        pointer-events: none;
                    }


                    
                                        /* Ortadaki Sabit Çizgi (Her sayfanın en altına kadar iner) */
                    .osc-print-center-line {
                        position: fixed;
                        top: 20px;
                        bottom: 20px;
                        left: 50%;
                        width: 1px;
                        background-color: #94a3b8;
                        z-index: -2;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }

                    /* Test Başlığı (Çizginin üstte görünmesini engellemek için beyaz arka plan eklendi) */
                    .osc-print-header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #333; padding-bottom: 15px; background: #fff; position: relative; z-index: 5; }
                    .osc-print-header h2 { margin: 0; font-size: 22px; color: #000; font-family: 'Segoe UI', sans-serif; }
                    
                                        /* 2 Sütunlu Görünüm (Eski çizgi iptal edildi) */
                    .osc-print-columns {
                        column-count: 2;
                        column-gap: 50px;
                    }
                    
                                        /* Cevap Anahtarı Tasarımı */
                    .osc-print-answer-key {
                        clear: both; 
                        margin-top: 40px;
                        padding-top: 20px;
                        border-top: 2px solid #333; /* Alt tarafa jilet gibi belirgin bir çizgi çeker */
                        font-family: 'Segoe UI', sans-serif;
                        font-size: 13px;
                        font-weight: 600;
                        text-align: center;
                        color: #1e293b;
                        page-break-inside: avoid; 
                        page-break-before: avoid; /* Tarayıcının bunu yeni sayfaya atmasını zorla engeller */
                        break-before: avoid; /* Modern tarayıcılar için yeni sayfaya atma engeli */
                        line-height: 1.6;
                        position: relative; /* Ortadan inen çizginin üstüne çıkması için */
                        z-index: 20;
                        background: #fff; /* Ortadan inen çizgiyi kesip arkasını temizler */
                        width: 100%;
                    }



                    
                    /* Deneme Sınavı Soru Tasarımı */
                    .osc-print-q { break-inside: avoid; page-break-inside: avoid; margin-bottom: 30px; font-size: 14px; color: #000; font-family: 'Times New Roman', Times, serif; line-height: 1.4; }
                    .osc-print-q-num { font-weight: bold; float: left; margin-right: 8px; font-size: 15px; }
                    .osc-print-para { font-style: italic; margin-bottom: 10px; text-align: justify; display: block; clear: both; padding-top: 2px; }
                    .osc-print-root { font-weight: bold; margin-bottom: 12px; text-align: justify; display: block; clear: both; }
                    .osc-print-opts { display: flex; flex-direction: column; gap: 6px; margin-left: 20px; }
                    .osc-print-opts span { display: block; }
                }
            </style>

            <script>
                        (function() {
                const quizId = <?php echo $post_id; ?>; const config = <?php echo $config; ?>;
                              let state = { idx: 0, correct: 0, wrong: 0, empty: 0, timeLeft: (config.settings.duration||0)*60, timer: null, locked: false, qSinceAd: 0, history: [], jokerUsed: false };

                // YARI YARIYA JOKER FONKSİYONU
                window.useJoker = function(id) {
                    if(id !== quizId || state.jokerUsed || state.locked) return;
                    const q = config.questions[state.idx];
                    
                    // Sadece çoktan seçmeli sorularda çalışır (Boşluk doldurma veya D/Y'de çalışmaz)
                    if(q.type !== 'multiple') return; 
                    
                    const correctKey = q.correct;
                    let wrongKeys = [];
                    
                    // Şıkları tara ve yanlış olan DOLU şıkları listeye al
                    Object.keys(q.options).forEach(key => {
                        if(key !== correctKey && q.options[key] && q.options[key].trim() !== '') {
                            wrongKeys.push(key);
                        }
                    });
                    
                    // Eğer silinecek en az 2 yanlış şık varsa işlemi yap
                    if(wrongKeys.length >= 2) {
                        // Rastgele 2 tanesini seç
                        wrongKeys = wrongKeys.sort(() => 0.5 - Math.random()).slice(0, 2);
                        
                        // Ekrandaki butonları bul ve devre dışı bırak
                        const area = document.getElementById(`osc-render-area-${quizId}`);
                        const buttons = area.querySelectorAll('.osc-opt-btn');
                        buttons.forEach(btn => {
                            const char = btn.querySelector('.osc-opt-char').innerText.trim();
                            if(wrongKeys.includes(char)) {
                                btn.style.opacity = '0.35';
                                btn.style.pointerEvents = 'none';
                                btn.style.background = '#f8fafc';
                                btn.querySelector('.osc-opt-text').style.textDecoration = 'line-through';
                                btn.querySelector('.osc-opt-text').style.color = '#cbd5e1';
                            }
                        });
                        
                        // Jokeri kullanıldı olarak işaretle ve butonu kapat
                        state.jokerUsed = true;
                        const jBtn = document.getElementById(`osc-joker-btn-${quizId}`);
                        if(jBtn) {
                            jBtn.classList.add('used');
                            jBtn.innerHTML = '<span class="dashicons dashicons-lock" style="margin-top: 3px;"></span> KULLANILDI';
                        }
                        
                        sfxCorrect.play().catch(e=>{}); // Jokere basınca tatmin edici bir ses çalsın
                    }
                };
// PAS GEÇ JOKERİ FONKSİYONU
                window.useSkipJoker = function(id) {
                    if(id !== quizId || state.locked) return;
                    if(!confirm("Bu soruyu boş geçerek bir sonraki soruya atlamak istediğinize emin misiniz?")) return;
                    
                    state.empty++; // Boş sayısına ekle
                    state.history.push({ qIdx: state.idx, isCorrect: false, skipped: true }); // Geçmişe ekle
                    
                    // Butonu pasif yap (Her testte 1 kez kullanılabilir)
                    const sBtn = document.getElementById(`osc-skip-btn-${quizId}`);
                    if(sBtn) {
                        sBtn.classList.add('used');
                        sBtn.disabled = true;
                        sBtn.innerHTML = '<span class="dashicons dashicons-lock" style="margin-top: 3px;"></span> KULLANILDI';
                    }
                    
                    oscNext(); // Sonraki soruya geç
                };
                
                // Oyunlaştırma: Ses Efektleri
                const sfxCorrect = new Audio('https://assets.mixkit.co/active_storage/sfx/2018/2018-preview.mp3');
                const sfxWrong = new Audio('https://assets.mixkit.co/active_storage/sfx/2997/2997-preview.mp3');
                const sfxFinish = new Audio('https://assets.mixkit.co/active_storage/sfx/2003/2003-preview.mp3');

                
                window.checkListOpt = function(qIdx, key, btn) {
                    if(btn.parentElement.classList.contains('locked')) return; 
                    const q = config.questions[qIdx]; const isCorrect = key === q.correct; 
                    const parent = btn.parentElement; parent.classList.add('locked'); parent.querySelectorAll('.osc-opt-btn').forEach(s => s.style.pointerEvents = 'none'); 
                    btn.classList.add(isCorrect ? 'correct' : 'wrong'); 
                    if(!isCorrect) { parent.querySelectorAll('.osc-opt-btn').forEach(s => { if(s.querySelector('.osc-opt-char').innerText === q.correct) s.classList.add('correct'); }); }
                }
                
                /* ===== OSC KLASİK / BOŞLUK CEVAP KARŞILAŞTIRMA ===== */
var OSCGrade = (typeof window !== 'undefined' && window.OSCGrade) || (function () {
    'use strict';

    // Anlam taşımayan bağlaç/edat/ek sözcükler (karşılaştırmada önemsenmez)
    var STOP = {};
    ('ve ile bir bu şu o da de ki mi mı mu mü için gibi olarak olan en çok daha ise ya veya yada ama fakat ancak çünkü kadar ' +
     'sonra önce diye hem yani her ne nasıl hangi göre dolayı tarafından üzere ayrıca bunun bunu buna bunlar onun onu ona ' +
     'şey şekilde dır dir dur dür tır tir tur tür dı di du dü tı ti tu tü the a an of and or to is are').split(' ').forEach(function (w) { if (w) STOP[w] = 1; });

    function decode(s) {
        return String(s).replace(/&nbsp;/gi, ' ').replace(/&amp;/gi, '&').replace(/&lt;/gi, '<').replace(/&gt;/gi, '>')
            .replace(/&quot;/gi, '"').replace(/&#0?39;|&apos;/gi, "'").replace(/&#(\d+);/g, function (_, n) { return String.fromCharCode(+n); });
    }
    function stripHtml(s) { return decode(String(s == null ? '' : s).replace(/<br\s*\/?>/gi, ' ').replace(/<img[^>]*>/gi, ' ').replace(/<[^>]+>/g, ' ')); }

    // Küçük harf (Türkçe kurallı), noktalama ve fazla boşluk temizliği
    function norm(s) {
        s = stripHtml(s);
        if (s.normalize) s = s.normalize('NFC');
        s = s.toLocaleLowerCase('tr-TR').replace(/i̇/g, 'i');
        s = s.replace(/[^0-9a-zçğıöşüâîûéèàäëïô]+/g, ' ');
        s = s.replace(/(\d)(?=[a-zçğıöşüâîû])/g, '$1 ').replace(/([a-zçğıöşüâîû])(?=\d)/g, '$1 ');   // 1299da -> 1299 da
        return s.replace(/\s+/g, ' ').trim();
    }
    // Türkçe karakter katlama: "ışık" = "isik" (klavyesinde Türkçe karakter olmayan öğrenciler için)
    function fold(s) {
        return s.replace(/ı/g, 'i').replace(/ğ/g, 'g').replace(/ü/g, 'u').replace(/ş/g, 's').replace(/ö/g, 'o').replace(/ç/g, 'c')
            .replace(/[âà]/g, 'a').replace(/î/g, 'i').replace(/û/g, 'u').replace(/[éèë]/g, 'e').replace(/ä/g, 'a').replace(/ï/g, 'i').replace(/ô/g, 'o');
    }
    function key(s) { return fold(norm(s)); }

    function lev(a, b) {
        if (a === b) return 0;
        var n = a.length, m = b.length;
        if (!n) return m; if (!m) return n;
        var prev = new Array(m + 1), cur = new Array(m + 1), i, j;
        for (j = 0; j <= m; j++) prev[j] = j;
        for (i = 1; i <= n; i++) {
            cur[0] = i;
            var ca = a.charCodeAt(i - 1);
            for (j = 1; j <= m; j++) {
                var cost = ca === b.charCodeAt(j - 1) ? 0 : 1;
                cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + cost);
            }
            var t = prev; prev = cur; cur = t;
        }
        return prev[m];
    }
    function ratio(a, b) { var L = Math.max(a.length, b.length); return L ? 1 - lev(a, b) / L : 1; }

    // İki sözcük ne kadar benzer? Yazım hatası (Levenshtein) ve Türkçe ekler (ortak kök) dikkate alınır.
    function tokSim(a, b) {
        if (a === b) return 1;
        var na = /^\d+$/.test(a), nb = /^\d+$/.test(b);
        if (na || nb) return 0;                         // sayılar birebir aynı olmalı (1299 ≠ 1229)
        var cp = 0, L = Math.min(a.length, b.length);
        while (cp < L && a.charAt(cp) === b.charAt(cp)) cp++;
        var stem = (cp >= 4 && cp / L >= 0.6) || (cp >= 3 && cp === L && L >= 3) ? 0.9 : (cp >= 3 && cp / L >= 0.6 ? 0.82 : 0);   // bitki ~ bitkilerin, kurdu ~ kurmuştur
        var r = ratio(a, b);
        if (Math.min(a.length, b.length) <= 3 && r < 1) r = r * 0.8;      // çok kısa sözcüklerde yazım toleransını azalt
        return Math.max(r, stem);
    }

    function tokens(s, keepAll) {
        var out = [], raw = norm(s).split(' ');
        raw.forEach(function (w) {
            if (!w) return;
            var f = fold(w);
            if (!keepAll && (STOP[w] || STOP[f] || (f.length < 2 && !/^\d$/.test(f)))) return;
            out.push({ f: f, o: w });
        });
        if (!out.length && !keepAll) return tokens(s, true);
        return out;
    }
    function weight(t) { return /^\d+$/.test(t) ? 2 : (t.length >= 6 ? 1.5 : 1); }

    /*
     * Klasik (açık uçlu) cevap karşılaştırması.
     * Döner: { verdict: 'correct' | 'partial' | 'mismatch' | 'empty' | 'nomodel', score: 0..1, exact: bool, missing: [sözcükler] }
     */
    function gradeClassic(model, student) {
        var mk = key(model), sk = key(student);
        if (!sk) return { verdict: 'empty', score: 0, exact: false, missing: [] };
        if (!mk) return { verdict: 'nomodel', score: 0, exact: false, missing: [] };
        if (mk === sk) return { verdict: 'correct', score: 1, exact: norm(model) === norm(student), missing: [] };

        var mt = tokens(model), st = tokens(student);
        var seen = {}, totalW = 0, gotW = 0, missing = [];
        mt.forEach(function (m) {
            if (seen[m.f]) return; seen[m.f] = 1;
            var best = 0;
            st.forEach(function (s) { var v = tokSim(m.f, s.f); if (v > best) best = v; });
            var w = weight(m.f);
            totalW += w;
            if (best >= 0.8) gotW += w * Math.min(1, best + 0.05); else missing.push(m.o);
        });
        var recall = totalW ? gotW / totalW : 0;

        // Öğrencinin yazdıklarının ne kadarı model cevapta karşılık buluyor (alakasız uzun cevapları ayırt etmek için)
        var sseen = {}, sTot = 0, sGot = 0;
        st.forEach(function (s) {
            if (sseen[s.f]) return; sseen[s.f] = 1;
            var best = 0;
            mt.forEach(function (m) { var v = tokSim(s.f, m.f); if (v > best) best = v; });
            sTot++; if (best >= 0.8) sGot++;
        });
        var precision = sTot ? sGot / sTot : 0;

        // Bütün metin benzerliği (sözcük sırası farkı için sıralı sürümüyle de)
        var a = mt.map(function (x) { return x.f; }), b = st.map(function (x) { return x.f; });
        var charSim = Math.max(ratio(a.join(' ').slice(0, 800), b.join(' ').slice(0, 800)),
                               ratio(a.slice().sort().join(' ').slice(0, 800), b.slice().sort().join(' ').slice(0, 800)));

        // Harf benzerliği yalnızca çok yakınsa (yazım hatası düzeyi) kullanılır; ortak ekler ("-ları", "-dır") alakasız cevapları şişirmesin
        // Model cevaptaki sayılar (tarih, miktar) öğrencide birebir yoksa harf benzerliğine güvenilmez (1923 ≠ 1924)
        var sNums = {}; st.forEach(function (x) { if (/^\d+$/.test(x.f)) sNums[x.f] = 1; });
        var numsOK = mt.every(function (x) { return !/^\d+$/.test(x.f) || sNums[x.f]; });
        var score = (charSim >= 0.75 && numsOK) ? Math.max(charSim, recall) : recall;
        // Model cevabın tüm anahtar kelimelerini içeren ama yanına başka çok şey yazan cevap: doğru sayılır, sadece hafif düşür
        if (recall >= 0.99 && precision < 0.3 && st.length > 4 * Math.max(1, mt.length)) score = Math.min(score, 0.8);
        score = Math.max(0, Math.min(1, score));

        var verdict = score >= 0.7 ? 'correct' : (score >= 0.3 ? 'partial' : 'mismatch');
        return { verdict: verdict, score: score, exact: false, missing: verdict === 'correct' ? [] : missing.slice(0, 8) };
    }

    // Boşluk doldurma: büyük/küçük harf, noktalama, boşluk ve Türkçe karakter farkı önemsiz; "Ankara / Angora" gibi alternatifler kabul
    function sameGap(model, student) {
        var sk = key(student);
        if (!sk) return false;
        var raw = stripHtml(model), alts = [raw];
        if (/[\/;|]/.test(raw)) {
            var parts = raw.split(/\s*[\/;|]\s*/).filter(function (p) { return /[A-Za-zÇĞİÖŞÜçğıöşü]/.test(p); });
            if (parts.length > 1) alts = alts.concat(parts);
        }
        return alts.some(function (a) { return key(a) === sk; });
    }

    function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

    // Sonuç kutusu HTML'i (adım adım ve tek sayfa görünümünde aynı)
    function feedbackHtml(res, model, student) {
        var pct = Math.round(res.score * 100), head, color, bg, border;
        if (res.verdict === 'correct') {
            color = '#166534'; bg = '#f0fdf4'; border = '#86efac';
            head = '✔ Doğru kabul edildi' + (res.exact ? '' : ' <span style="font-weight:600; font-size:13px;">(küçük yazım / noktalama farkları dikkate alınmadı)</span>');
        } else if (res.verdict === 'partial') {
            color = '#92400e'; bg = '#fffbeb'; border = '#fcd34d';
            head = '½ Kısmen doğru <span style="font-weight:600; font-size:13px;">(yarım puan • model cevaba benzerlik %' + pct + ')</span>';
        } else if (res.verdict === 'empty') {
            color = '#334155'; bg = '#f8fafc'; border = '#cbd5e1';
            head = 'Cevap yazmadınız. Doğru cevap aşağıda:';
        } else if (res.verdict === 'nomodel') {
            color = '#334155'; bg = '#f8fafc'; border = '#cbd5e1';
            head = 'Bu soru için model cevap girilmemiş. Cevabınızı kendiniz değerlendirin.';
        } else {
            color = '#9a3412'; bg = '#fff7ed'; border = '#fdba74';
            head = '✖ Cevabınız model cevapla örtüşmüyor <span style="font-weight:600; font-size:13px;">(benzerlik %' + pct + ')</span>';
        }
        var h = '<div class="osc-classic-fb" style="margin-top:12px; padding:14px; border-radius:10px; background:' + bg + '; border:1px solid ' + border + '; color:' + color + '; text-align:left; line-height:1.55;">';
        h += '<div style="font-weight:800; margin-bottom:8px;">' + head + '</div>';
        if (res.verdict !== 'empty') h += '<div style="margin-bottom:6px; color:#1e293b;"><strong>Sizin cevabınız:</strong> ' + esc(student) + '</div>';
        if (res.verdict !== 'nomodel') h += '<div style="color:#1e293b;"><strong>Doğru (model) cevap:</strong> ' + (model || '') + '</div>';
        if (res.missing && res.missing.length && res.verdict !== 'empty') h += '<div style="margin-top:8px; font-size:13px; color:#475569;"><strong>Cevabınızda eksik görünen anahtar kelimeler:</strong> ' + res.missing.map(esc).join(', ') + '</div>';
        h += '</div>';
        return h;
    }

    var api = { gradeClassic: gradeClassic, sameGap: sameGap, feedbackHtml: feedbackHtml, esc: esc, norm: norm, key: key };
    if (typeof window !== 'undefined') window.OSCGrade = api;
    return api;
})();


                window.showListClassic = function(qIdx) {
                    const q = config.questions[qIdx]; const ta = document.getElementById(`osc-list-cl-${qIdx}`); const box = document.getElementById(`osc-list-cl-ans-${qIdx}`);
                    if(!box || (ta && ta.disabled)) return;
                    const val = ta ? ta.value.trim() : ''; const model = q.options.A || '';
                    const res = OSCGrade.gradeClassic(model, val);
                    if(ta) ta.disabled = true;
                    box.style.display = 'block'; box.innerHTML = OSCGrade.feedbackHtml(res, model, val);
                };
                window.checkListGap = function(qIdx) { 
                    const input = document.getElementById(`osc-list-gap-${qIdx}`); const fb = document.getElementById(`osc-list-fb-${qIdx}`); const q = config.questions[qIdx]; 
                    if(input.disabled) return; const userAns = input.value.trim().toLowerCase(); const correctAns = (q.options.A || '').trim().toLowerCase(); 
                    input.disabled = true; 
                    if(OSCGrade.sameGap(q.options.A || '', input.value)) fb.innerHTML = '<span style="color:green">✔ Doğru</span>'; else fb.innerHTML = `<span style="color:red">✖ Yanlış. Cevap: ${q.options.A}</span>`; 
                }
                
                window.oscRun = function(id) { 
    if(id!==quizId) return; 
    
    // Tam Ekranı Tetikle
    var elem = document.getElementById('osc-app-' + id);
    if (elem.requestFullscreen) { elem.requestFullscreen().catch(e=>{}); }
    else if (elem.webkitRequestFullscreen) { elem.webkitRequestFullscreen(); } // Safari/Chrome
    else if (elem.msRequestFullscreen) { elem.msRequestFullscreen(); }
    
    // Çıkış Butonunu Göster
    var fsBar = document.getElementById('osc-fs-bar-' + id);
    if(fsBar) fsBar.style.display = 'block';

    document.querySelector(`#osc-app-${id} .osc-start`).style.display = 'none'; 
    document.querySelector(`#osc-app-${id} .osc-quiz`).style.display = 'block'; 
    renderQ(); if(state.timeLeft>0) startTimer(); 
}

                
                window.oscCloseAd = function(id) { document.getElementById(`osc-ad-modal-${id}`).style.display = 'none'; oscNext(); }

// TAM EKRAN FONKSİYONLARI
window.oscExitFS = function() {
    if (document.exitFullscreen) document.exitFullscreen();
    else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
}

// Kullanıcı ESC veya Geri tuşuyla çıkarsa barı gizle
function fsChangeHandler() {
    var fsBar = document.getElementById('osc-fs-bar-' + quizId);
    if (!document.fullscreenElement && !document.webkitIsFullScreen && !document.mozFullScreen && !document.msFullscreenElement) {
        if(fsBar) fsBar.style.display = 'none';
    } else {
        if(fsBar) fsBar.style.display = 'block';
    }
}
document.addEventListener('fullscreenchange', fsChangeHandler);
document.addEventListener('webkitfullscreenchange', fsChangeHandler);
document.addEventListener('mozfullscreenchange', fsChangeHandler);
document.addEventListener('MSFullscreenChange', fsChangeHandler);

                
                function renderQ() { 
                    state.locked = false; const q = config.questions[state.idx]; 
                    const area = document.getElementById(`osc-render-area-${quizId}`); 
                    document.getElementById(`osc-curr-${quizId}`).innerText = state.idx+1; 
                    document.getElementById(`osc-bar-fill-${quizId}`).style.width = ((state.idx/config.questions.length)*100) + '%'; 
                    
                    let html = ''; 
                    if(q.paragraph) html += `<div class="osc-q-paragraph">${q.paragraph}</div>`; 
                    html += `<div class="osc-q-root">${q.root || q.text}</div>`; 
                    
                    if (q.type === 'classic') { 
                        html += `<textarea id="osc-cl-ans-${quizId}" class="osc-classic-input" rows="4" placeholder="Cevabınızı buraya yazın..."></textarea><button id="osc-cl-btn-${quizId}" class="osc-btn-main" style="margin-top:12px" onclick="checkClassic()">Kontrol Et</button><div id="osc-cl-box-${quizId}" style="display:none;"></div>`; 
                    } else if (q.type === 'gap') { 
                        html += `<input type="text" id="osc-gap-ans-${quizId}" class="osc-gap-input" placeholder="Cevabınız..." style="width:100%; padding:10px; border:1px solid #ccc; border-radius:5px;"><button class="osc-btn-main" style="margin-top:12px" onclick="checkGap()">Gönder</button><div id="osc-gap-feedback-${quizId}" style="margin-top:10px; font-weight:bold;"></div>`; 
                    } else { 
                        const opts = q.type === 'truefalse' ? {A:'Doğru', B:'Yanlış'} : q.options; 
                        Object.keys(opts).forEach(key => { 
                            if(!opts[key]) return; 
                            html += `<button class="osc-opt-btn" onclick="checkOpt('${key}', this)"><div class="osc-opt-char">${key}</div><div class="osc-opt-text">${opts[key]}</div></button>`; 
                        }); 
                    }
                    html += `<button class="osc-btn-finish-early" onclick="window.oscFinishEarly(${quizId})">Testi Bitir ve Raporu Gör</button>`;
                    
                    area.innerHTML = html; 
                }
                
                                                                window.checkOpt = function(key, btn) { 
                    if(state.locked) return; state.locked = true; const q = config.questions[state.idx]; const isCorrect = key === q.correct; 
                    btn.classList.add(isCorrect ? 'correct' : 'wrong'); 
                    state.history.push({ qIdx: state.idx, isCorrect: isCorrect, userKey: key });
                    if(!isCorrect) { 
                        sfxWrong.play().catch(e=>{});
                        document.querySelectorAll(`#osc-render-area-${quizId} .osc-opt-btn`).forEach(b => { if(b.querySelector('.osc-opt-char').innerText === q.correct) b.classList.add('correct'); }); 
                        state.wrong++; 
                    } else { 
                        sfxCorrect.play().catch(e=>{});
                        state.correct++; 
                    } 
                    setTimeout(() => { checkAd(); }, 1200); 
                }

                
                                window.checkClassic = function() {
                    if(state.locked) return; state.locked = true;
                    const q = config.questions[state.idx]; const model = q.options.A || '';
                    const ta = document.getElementById(`osc-cl-ans-${quizId}`); const val = ta ? ta.value.trim() : ''; if(ta) ta.disabled = true;
                    const btn = document.getElementById(`osc-cl-btn-${quizId}`); if(btn) btn.style.display = 'none';
                    const res = OSCGrade.gradeClassic(model, val);
                    const box = document.getElementById(`osc-cl-box-${quizId}`);
                    let extra = '';
                    if(res.verdict === 'nomodel') {
                        // model cevap girilmemiş: öğrenci kendisi değerlendirir
                        state.locked = false;
                        extra = `<div style="display:flex; gap:10px; margin-top:10px;"><button class="osc-btn-main" style="background:#16a34a" onclick="selfClassic(true)">✔ Doğru Yaptım</button><button class="osc-btn-main" style="background:#dc2626" onclick="selfClassic(false)">✖ Yapamadım</button></div>`;
                    } else {
                        let ok = false;
                        if(res.verdict === 'correct') { state.correct++; ok = true; sfxCorrect.play().catch(e=>{}); }
                        else if(res.verdict === 'partial') { state.correct += 0.5; state.wrong += 0.5; }   // kısmi puan
                        else if(res.verdict === 'mismatch') { state.wrong++; sfxWrong.play().catch(e=>{}); }
                        // 'empty' -> boş sayılır
                        state.history.push({ qIdx: state.idx, isCorrect: ok, userAns: val, verdict: res.verdict, sim: Math.round(res.score * 100) });
                        extra = `<button class="osc-btn-main" style="margin-top:12px" onclick="oscClassicNext()">Devam Et →</button>`;
                    }
                    if(box) { box.style.display = 'block'; box.innerHTML = OSCGrade.feedbackHtml(res, model, val) + extra; }
                };
                window.selfClassic = function(ok) {
                    if(state.locked) return; state.locked = true; const ta = document.getElementById(`osc-cl-ans-${quizId}`);
                    state.history.push({ qIdx: state.idx, isCorrect: ok, userAns: ta ? ta.value.trim() : '', verdict: ok ? 'correct' : 'mismatch' });
                    if(ok) { sfxCorrect.play().catch(e=>{}); state.correct++; } else { sfxWrong.play().catch(e=>{}); state.wrong++; }
                    setTimeout(() => { checkAd(); }, 600);
                };
                window.oscClassicNext = function() { checkAd(); };
                window.checkGap = function() { 
                    if(state.locked) return; state.locked = true; const input = document.getElementById(`osc-gap-ans-${quizId}`); const q = config.questions[state.idx]; 
                    const userAns = input.value.trim().toLowerCase(); const correctAns = (q.options.A || '').trim().toLowerCase(); input.disabled = true; 
                    const isCorrect = OSCGrade.sameGap(q.options.A || '', input.value);
                    state.history.push({ qIdx: state.idx, isCorrect: isCorrect, userAns: input.value.trim() });
                    if(isCorrect) { sfxCorrect.play().catch(e=>{}); document.getElementById(`osc-gap-feedback-${quizId}`).innerHTML = '<span style="color:green">✔ Doğru!</span>'; state.correct++; } 
                    else { sfxWrong.play().catch(e=>{}); document.getElementById(`osc-gap-feedback-${quizId}`).innerHTML = `<span style="color:red">✖ Yanlış. Doğru: ${q.options.A}</span>`; state.wrong++; } 
                    setTimeout(() => { checkAd(); }, 1500); 
                }


                
                function checkAd() { 
                    state.qSinceAd++; const freq = parseInt(config.ad_freq); 
                    if(freq > 0 && config.ad_code && state.qSinceAd >= freq && (state.idx + 1) < config.questions.length) { 
                        state.qSinceAd = 0; document.getElementById(`osc-ad-modal-${quizId}`).style.display = 'flex'; 
                    } else { oscNext(); } 
                }
                
                function oscNext() { state.idx++; if(state.idx >= config.questions.length) finish(); else renderQ(); }
                
                window.oscFinishEarly = function(id) { if(id===quizId && confirm('Testi bitirmek istediğinize emin misiniz?')) finish(); }

                function startTimer() { 
                    const t = document.getElementById(`osc-timer-${quizId}`); 
                    state.timer = setInterval(() => { 
                        state.timeLeft--; 
                        if(state.timeLeft < 0) { finish(); return; }
                        let h = Math.floor(state.timeLeft / 3600);
                        let m = Math.floor((state.timeLeft % 3600) / 60);
                        let s = state.timeLeft % 60;
                        let str = "";
                        if(h > 0) str += (h < 10 ? '0'+h : h) + ":";
                        str += (m < 10 ? '0'+m : m) + ":";
                        str += (s < 10 ? '0'+s : s);
                        t.innerText = str; 
                    }, 1000); 
                }
                
                                                function finish() { 
                    clearInterval(state.timer); 
                    
                    // TEST BİTİNCE OTOMATİK TAM EKRANDAN ÇIK
                    if(typeof window.oscExitFS === 'function') { 
                        window.oscExitFS(); 
                    }
                    
                    const total = config.questions.length; 

                    const answered = state.correct + state.wrong;
                    state.empty = total - answered; 
                    const score = total > 0 ? Math.round((state.correct / total) * 100) : 0; 
                    const net = state.correct - (state.wrong * 0.25); 
                    document.querySelector(`#osc-app-${quizId} .osc-quiz`).style.display = 'none'; 
                    document.querySelector(`#osc-app-${quizId} .osc-result`).style.display = 'block'; 
                    document.getElementById(`osc-final-score-${quizId}`).innerText = score; 
                    document.getElementById(`osc-stat-correct-${quizId}`).innerText = state.correct; 
                    document.getElementById(`osc-stat-wrong-${quizId}`).innerText = state.wrong; 
                    document.getElementById(`osc-stat-empty-${quizId}`).innerText = state.empty; 
                    document.getElementById(`osc-stat-net-${quizId}`).innerText = Math.round(net * 100) / 100; 
                    
                                        sfxFinish.play().catch(e=>{});
                    
                   // HAFTALIK ÇÖZÜLME SAYACINI ARKA PLANDA TETİKLE (Artık asıl yazının ID'sine kaydeder)
                    const formData = new URLSearchParams();
                    formData.append('action', 'osc_track_solve');
                    formData.append('quiz_id', quizId);
                    formData.append('parent_id', <?php echo get_the_ID() ? get_the_ID() : $post_id; ?>);
                    fetch(oscAjaxUrl, { method: 'POST', body: formData }).catch(e=>{});
                    
                    // Görsel Şölen: Başarıya Göre Farklı Renklerde Konfeti Patlatma

                    if(score >= 50 && typeof confetti === 'function') {
                        var duration = 4 * 1000;
                        var animationEnd = Date.now() + duration;
                        var defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 9999, colors: ['#6366f1', '#8b5cf6', '#10b981', '#f59e0b', '#ec4899'] };

                        var interval = setInterval(function() {
                            var timeLeft = animationEnd - Date.now();
                            if (timeLeft <= 0) { return clearInterval(interval); }
                            var particleCount = 50 * (timeLeft / duration);
                            confetti(Object.assign({}, defaults, { particleCount, origin: { x: Math.random(), y: Math.random() - 0.2 } }));
                        }, 250);
                    }
                }

                                const oscAjaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
                const oscNonce = '<?php echo wp_create_nonce('osc_quiz_nonce'); ?>';

                // Öğrenci Adını Gerçek Veritabanına (AJAX) Kaydetme
                window.oscSaveScore = function(id) {
                    const nameInput = document.getElementById(`osc-student-name-${id}`);
                    const name = nameInput.value.trim();
                    const btn = nameInput.nextElementSibling;
                    
                    if(!name) { 
                        nameInput.style.borderColor = '#ef4444';
                        nameInput.placeholder = 'Lütfen adını gir!';
                        return; 
                    }
                    
                    const score = document.getElementById(`osc-final-score-${id}`).innerText;
                    
                    // Yükleniyor efekti
                    btn.innerText = 'Gönderiliyor...';
                    btn.disabled = true;
                    btn.style.opacity = '0.7';

                    const formData = new URLSearchParams();
                    formData.append('action', 'osc_save_score');
                    formData.append('quiz_id', id);
                    formData.append('name', name);
                    formData.append('score', score);
                    formData.append('nonce', oscNonce);

                    fetch(oscAjaxUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(res => {
                        if(res.success) {
                            // Giriş alanını başarı mesajıyla değiştir
                            document.getElementById(`osc-name-input-area-${id}`).innerHTML = '<div style="color:#10b981; font-weight:800; font-size:16px; margin-bottom:20px;">🎉 Tebrikler! Puanın Soruİndir sistemine kaydedildi.</div>';
                            
                            // Veritabanından gelen global tabloyu çiz
                            window.oscRenderLeaderboard(id, res.data);
                        }
                    })
                    .catch(err => {
                        btn.innerText = 'Kaydet';
                        btn.disabled = false;
                        btn.style.opacity = '1';
                        alert('Bağlantı hatası oluştu, lütfen tekrar dene.');
                    });
                }

                // Global Liderlik Tablosunu Ekrana Çizdirme İşlevi
                window.oscRenderLeaderboard = function(id, leaders) {
                    const lbContainer = document.getElementById(`osc-leaderboard-${id}`);
                    const lbList = document.getElementById(`osc-lb-list-${id}`);
                    
                    if(!leaders || leaders.length === 0) return;
                    
                    let html = '';
                    leaders.forEach((l, index) => {
                        let rankClass = '';
                        let rankIcon = (index + 1) + '.';
                        
                        if(index === 0) { rankClass = 'top-1'; rankIcon = '🥇'; }
                        else if(index === 1) { rankClass = 'top-2'; rankIcon = '🥈'; }
                        else if(index === 2) { rankClass = 'top-3'; rankIcon = '🥉'; }
                        
                        html += `<div class="osc-lb-item ${rankClass}">
                                    <div class="osc-lb-rank">${rankIcon}</div>
                                    <div class="osc-lb-name">${l.name} <span style="font-size:11px; color:#94a3b8; font-weight:normal; margin-left:8px;">${l.date || ''}</span></div>
                                    <div class="osc-lb-score">%${l.score}</div>
                                 </div>`;
                    });
                    
                    lbList.innerHTML = html;
                    lbContainer.style.display = 'block';
                    
                    // Tabloya doğru yumuşak kaydırma
                    setTimeout(() => {
                        lbContainer.scrollIntoView({behavior: "smooth", block: "center"});
                    }, 300);
                };




                                window.oscShowMistakes = function(id) {
                    if(id !== quizId) return;
                    const area = document.getElementById(`osc-mistakes-area-${id}`);
                    if(area.style.display === 'block') { area.style.display = 'none'; return; }
                    let html = '<h3 style="margin-top:0; margin-bottom:15px; color:#1e293b; border-bottom:2px solid #e2e8f0; padding-bottom:10px;">Yanlış Cevaplanan Sorular</h3>';
                    const mistakes = state.history.filter(h => !h.isCorrect);
                    if(mistakes.length === 0) { html += '<p style="color:#10b981; font-weight:bold;">Harika! Hiç yanlışınız yok.</p>'; }
                    else {
                        mistakes.forEach(m => {
                            const q = config.questions[m.qIdx];
                            html += `<div style="background:#fff; padding:15px; border-radius:8px; border-left:4px solid #ef4444; margin-bottom:15px; box-shadow:0 2px 5px rgba(0,0,0,0.05);">`;
                            html += `<strong style="color:#3b82f6;">Soru ${m.qIdx + 1}:</strong><br>`;
                            if(q.paragraph) html += `<div style="font-size:13px; color:#64748b; margin:8px 0; background:#f1f5f9; padding:10px; border-radius:6px;">${q.paragraph}</div>`;
                            html += `<div style="font-weight:bold; margin-bottom:10px; color:#334155;">${q.root || q.text}</div>`;
                            if(q.type === 'classic') {
                                const lbl = m.verdict === 'partial' ? '½ Kısmen doğru – yarım puan (benzerlik %' + (m.sim||0) + ')' : (m.verdict === 'empty' ? 'Boş bırakıldı' : 'Model cevapla örtüşmüyor (benzerlik %' + (m.sim||0) + ')');
                                html += `<div style="color:#b45309; font-size:13px; font-weight:700; margin-bottom:6px;">${lbl}</div>`;
                                html += `<div style="color:#334155; font-size:14px; margin-bottom:5px;"><strong>Sizin Cevabınız:</strong> ${m.userAns ? OSCGrade.esc(m.userAns) : 'Boş'}</div>`;
                                html += `<div style="color:#10b981; font-size:14px;"><strong>Doğru Cevap:</strong> ${q.options.A}</div>`;
                            } else if(q.type === 'gap') {
                                html += `<div style="color:#ef4444; font-size:14px; margin-bottom:5px;"><strong>Sizin Cevabınız:</strong> ${m.userAns || 'Boş'}</div>`;
                                html += `<div style="color:#10b981; font-size:14px;"><strong>Doğru Cevap:</strong> ${q.options.A}</div>`;
                            } else {
                                const opts = q.type === 'truefalse' ? {A:'Doğru', B:'Yanlış'} : q.options;
                                html += `<div style="color:#ef4444; font-size:14px; margin-bottom:5px;"><strong>Sizin Cevabınız:</strong> ${m.userKey ? opts[m.userKey] : 'Boş'}</div>`;
                                html += `<div style="color:#10b981; font-size:14px;"><strong>Doğru Cevap:</strong> ${opts[q.correct]}</div>`;
                                                        }
                            html += `</div>`;
                        });
                        
                                                // YANLIŞLARI İNCELE SONU REKLAMI (Şeffaf ve Esnek)
                        const rawAdMistakes = <?php echo json_encode(stripslashes($ad_mistakes)); ?>;
                        if(rawAdMistakes && rawAdMistakes.trim() !== '') {
                            html += `<div style="margin-top:20px; text-align:center; display:flex; justify-content:center;">`;
                            html += rawAdMistakes;
                            html += `</div>`;
                        }

                    }
                    area.innerHTML = html;
                    area.style.display = 'block';

                    setTimeout(() => area.scrollIntoView({behavior: "smooth", block: "nearest"}), 100);
                }

                window.oscShare = function(platform, id) {

                    const score = document.getElementById(`osc-final-score-${id}`).innerText;
                    const url = encodeURIComponent(window.location.href);
                    const text = encodeURIComponent(`Bu testi %${score} başarı ile tamamladım! Sende durum ne? 🏆 `);
                    let shareUrl = '';
                    
                    if(platform === 'whatsapp') {
                        shareUrl = `https://api.whatsapp.com/send?text=${text}${url}`;
                    } else if(platform === 'twitter') {
                        shareUrl = `https://twitter.com/intent/tweet?text=${text}&url=${url}`;
                    } else if(platform === 'facebook') {
                        shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}&quote=${text}`;
                    }
                    
                                        window.open(shareUrl, '_blank', 'width=600,height=500,toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1,left=0,top=0');
                };
							// SİTENE EKLE (EMBED) KODLARI
                window.oscShowEmbed = function(id) {
                    const area = document.getElementById(`osc-embed-area-${id}`);
                    const textarea = document.getElementById(`osc-embed-code-${id}`);
                    
                    // Sitenin ana dizinini alıp, özel iframe şifremizi DOĞRUDAN TEST ID'Sİ İLE ekliyoruz
                    const embedUrl = '<?php echo site_url(); ?>/?osc_embed=' + id;
                    const iframeCode = `<iframe src="${embedUrl}" width="100%" height="800" frameborder="0" style="border:none; border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,0.1);"></iframe>`;
                    
                    textarea.value = iframeCode;
                    
                    // Kutuyu Aç / Kapat (Toggle)
                    if(area.style.display === 'none') {
                        area.style.display = 'block';
                    } else {
                        area.style.display = 'none';
                    }
                };

                window.oscCopyEmbed = function(id) {
                    const textarea = document.getElementById(`osc-embed-code-${id}`);
                    const btn = document.getElementById(`osc-copy-btn-${id}`);
                    
                    // Metni kopyala
                    textarea.select();
                    textarea.setSelectionRange(0, 99999); // Mobil cihazlar için güvenli kopyalama
                    
                    navigator.clipboard.writeText(textarea.value).then(() => {
                        const originalHTML = btn.innerHTML;
                        btn.innerHTML = '<span class="dashicons dashicons-yes"></span> Kopyalandı!';
                        btn.style.background = '#10b981'; // Başarılı olunca yeşile dön
                        
                        // 2 saniye sonra butonu eski haline (mavi) döndür
                        setTimeout(() => {
                            btn.innerHTML = originalHTML;
                            btn.style.background = '#3b82f6';
                        }, 2000);
                    }).catch(err => {
                        console.error('Kopyalama başarısız!', err);
                        // Eski tarayıcılar için yedek yöntem
                        document.execCommand('copy');
                        btn.innerHTML = 'Kopyalandı!';
                    });
                };

         // PDF / YAZDIRMA ŞABLONU OLUŞTURMA VE TETİKLEME
                window.oscPrintPDF = function(id) {
                    let printDiv = document.getElementById('osc-print-area');
                    
                    // Varsa eski çıktı alanını temizle, yenisini oluştur
                    if(printDiv) { printDiv.remove(); }
                    
                                        printDiv = document.createElement('div');
                    printDiv.id = 'osc-print-area';
                    // DİKKAT: Burada inline olarak display: none YAPMIYORUZ! Boş PDF hatasının sebebi buydu.
                    // Gizleme işlemini artık CSS'teki @media screen kuralı kusursuzca halledecek.
                    document.body.appendChild(printDiv);

                    
                    const quizTitle = <?php echo json_encode(get_the_title($post_id)); ?>;
                    
                    // Şablon Başlangıcı (Sabit Çizgi, Filigran ve Başlık)
                    let html = '<div class="osc-print-center-line"></div>';
                    html += '<div class="osc-print-watermark">soruindir.net</div>';
                    html += '<div class="osc-print-header"><h2>' + quizTitle + '</h2></div>';
                    html += '<div class="osc-print-columns">';

                    // Soruları Döngüyle Ekle
                    config.questions.forEach((q, i) => {
                        html += '<div class="osc-print-q">';
                        html += '<div class="osc-print-q-num">' + (i + 1) + '.</div>';
                        
                        let mainText = '';
                        if(q.paragraph) mainText += '<div class="osc-print-para">' + q.paragraph + '</div>';
                        mainText += '<div class="osc-print-root">' + (q.root || q.text) + '</div>';
                        html += '<div>' + mainText + '</div>';
                        
                        // Şıklar
                        html += '<div class="osc-print-opts">';
                        if(q.type === 'classic') { html += '<span style="display:block;width:100%">' + '<span style="display:block;border-bottom:1px dotted #333;height:22px;margin-bottom:6px"></span>'.repeat(4) + '</span>'; } else if(q.type === 'gap') {
                            html += '<span>....................................................................</span>';
                        } else if(q.type === 'truefalse') {
                            html += '<span>A) Doğru</span><span>B) Yanlış</span>';
                        } else {
                            ['A', 'B', 'C', 'D', 'E'].forEach(k => {
                                if(q.options && q.options[k]) {
                                    html += '<span>' + k + ') ' + q.options[k] + '</span>';
                                }
                            });
                        }
                        html += '</div></div>';
                    });
                    
                    html += '</div>'; // Sütun bitişi
                    
                    // Cevap Anahtarını Topla ve Ekle
                    let answerArr = [];
                    config.questions.forEach((q, i) => {
                        // Boşluk doldurma (gap) ise metni, çoktan seçmeliyse şıkkın harfini alır
                        let ans = q.type === 'gap' ? (q.options.A || '') : (q.type === 'classic' ? String(q.options.A || '').replace(/<[^>]*>/g, '').slice(0, 60) : (q.correct || ''));
                        answerArr.push((i + 1) + '-' + ans);
                    });
                    
                    html += '<div class="osc-print-answer-key"><strong>CEVAP ANAHTARI:</strong> ' + answerArr.join(' , ') + '</div>';
                    
                    printDiv.innerHTML = html;
                    
                    // DOM'un güncellenmesini bekleyip yazdır komutunu ver
                    setTimeout(() => {
                        window.print();
                        
                        // TEMİZLİK: Yazdırma işlemi bittikten kısa bir süre sonra HTML'i tamamen sil
                        setTimeout(() => {
                            if(document.getElementById('osc-print-area')) {
                                document.getElementById('osc-print-area').remove();
                            }
                        }, 2000);
                        
                    }, 300);
                };

            })();
            </script>


            <?php
            return ob_get_clean();
        }

        public function add_meta_box() { add_meta_box('osc_builder', 'Test Yönetim Paneli', array($this, 'render_admin'), 'osc_quiz', 'normal', 'high'); }

        public function render_admin($post) {
            $data = get_post_meta($post->ID, '_osc_data', true);
            $settings = get_post_meta($post->ID, '_osc_settings', true);
            wp_nonce_field('osc_save', 'osc_nonce');
            ?>
            <div class="osc-admin-wrap">
                <div class="osc-panel" style="background:#e8f4fd; border-color:#d0e6f9;">
                    <h3 style="margin-top:0;">TEST KISA KODU (Bunu Kopyalayın)</h3>
                    <p style="margin-bottom:5px; font-size:13px; color:#555;">Testi yazı içine eklemek için aşağıdaki kodu kopyalayıp editörünüze yapıştırın:</p>
                    <input type="text" readonly value="[osc_quiz id=&quot;<?php echo $post->ID; ?>&quot;]" style="width:100%; padding:10px; font-family:monospace; font-size:16px; border:2px solid #2271b1;" onclick="this.select()">
                </div>
                <div class="osc-panel">
                    <h3>Ayarlar</h3>
                    <div class="osc-grid-2">
                        <label><strong>Süre (Dk):</strong> <input type="number" name="osc_settings[duration]" value="<?php echo $settings['duration']??''; ?>" placeholder="Örn: 150"></label>
                        <label><strong>Görünüm:</strong> <select name="osc_settings[view_mode]"><option value="step" <?php selected($settings['view_mode']??'','step'); ?>>Adım Adım</option><option value="list" <?php selected($settings['view_mode']??'','list'); ?>>Tek Sayfa</option></select></label>
                    </div>
                    <div class="osc-grid-2" style="margin-top:10px;">
                        <label><strong>Reklam Sıklık (Opsiyonel):</strong> <input type="number" name="osc_settings[ad_freq]" value="<?php echo $settings['ad_freq']??''; ?>" placeholder="Boş bırakırsanız genel ayar kullanılır"></label>
                        <label><strong>HTML Kodu (Opsiyonel):</strong> <textarea name="osc_settings[ad_code]" placeholder="Boş bırakırsanız genel ayar kullanılır"><?php echo stripslashes($settings['ad_code']??''); ?></textarea></label>
                    </div>
                </div>
                <div class="osc-panel">
                    <h3>Dosya Yükle (Word, PDF, OCR)</h3>
                    <input type="file" id="osc-file-in" accept=".pdf,.docx,.txt,image/*"> 
                    <span id="osc-status" style="font-weight:bold; color:#2271b1; margin-left:10px;"></span>
                    <p style="margin:8px 0 0;color:#50575e"><label><input type="checkbox" id="osc-yz-kapali"> PDF'i yapay zekâ olmadan oku</label> — yapay zekâ anahtarı tanımlıysa PDF sayfaları yapay zekâyla birebir okunur; şekiller, sayı doğruları ve tablolar PDF'ten ayrı resim olarak kesilir.</p>
                </div>
                <input type="hidden" id="osc_img_nonce" value="<?php echo esc_attr(wp_create_nonce('osc_img_upload')); ?>">
                <div id="osc-q-list"></div>
                <button type="button" class="button button-primary button-large" onclick="addQ()" style="display:block; width:100%; text-align:center; padding:10px;">+ YENİ SORU EKLE</button>
                <textarea name="osc_data" id="osc_data" style="display:none;"><?php echo $data ? esc_textarea(json_encode($data)) : '[]'; ?></textarea>
            </div>
            <?php
        }

        // --- CSS KISMI ---
        public function admin_styles() { echo '<style>.osc-admin-wrap{max-width:100%}.osc-panel{background:#fff;padding:20px;margin-bottom:20px;border:1px solid #ddd;}.osc-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px}.osc-q-item{background:#fff;border:1px solid #ccc;margin-bottom:15px;padding:15px;border-left:5px solid #2271b1;}.osc-row{display:flex;align-items:center;margin-bottom:8px}.osc-inp{width:100%;padding:5px}.osc-txt{width:100%;border:1px solid #ddd;padding:8px;font-weight:700 !important;}.osc-type-sel{padding:5px; border:1px solid #2271b1; font-weight:bold; cursor:pointer;}.osc-prev{margin:4px 0 10px;padding:8px;background:#f8fafc;border:1px dashed #94a3b8;border-radius:6px;overflow:auto}.osc-prev img{max-width:260px;max-height:180px;height:auto;vertical-align:middle}.osc-prev-s{margin:0 0 0 8px;padding:4px;flex:0 0 auto}.osc-prev-s img{max-width:90px;max-height:70px}.osc-prev table{border-collapse:collapse}.osc-prev td{border:1px solid #94a3b8;padding:2px 6px}</style>'; }

        // --- PDF/WORD OKUMA SİSTEMİ ---
        public function admin_scripts() {
            global $post; if(!$post || $post->post_type!=='osc_quiz') return;
            ?>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
            <script src="https://unpkg.com/tesseract.js@v2.1.0/dist/tesseract.min.js"></script>
            <script>
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';

            /* ===== V21: Gelişmiş PDF / Word içe aktarma ===== */
/* ===== OSC IMPORT ÇEKİRDEĞİ: metin -> sorular + cevap anahtarı ===== */
var OSCImport = (function () {
    'use strict';

    var TR_UP = 'A-ZÇĞİÖŞÜ';
    var LET = 'A-Za-zğüşıöçĞÜŞİÖÇ';

    var TOKEN_RE = /§IMG\d+§|§TBL\d+§/gi;
    function toSentenceCase(str) {
        if (!str) return '';
        var core = str.replace(TOKEN_RE, ''), t = core.trim();
        if (t.length > 3 && t === t.toLocaleUpperCase('tr-TR') && /[A-ZÇĞİÖŞÜ]/.test(t)) {
            // yalnızca belirteç dışındaki yazıyı küçült; resim/tablo belirteçleri olduğu gibi kalsın
            var parts = str.split(/(§IMG\d+§|§TBL\d+§)/i), first = true;
            return parts.map(function (p) {
                if (/^§(?:IMG|TBL)\d+§$/i.test(p)) return p;
                var l = p.toLocaleLowerCase('tr-TR');
                if (first && /\S/.test(l)) { var k = l.search(/\S/); l = l.slice(0, k) + l.charAt(k).toLocaleUpperCase('tr-TR') + l.slice(k + 1); first = false; }
                return l;
            }).join('');
        }
        return str;
    }

    function normalize(text) {
        text = String(text || '');
        text = text.replace(/\r\n?/g, '\n');
        text = text.replace(/[\u00A0\u2007\u202F\u2000-\u200A]/g, ' ').replace(/[\u200B-\u200D\uFEFF\u00AD]/g, '');
        text = text.replace(/\uFB01/g, 'fi').replace(/\uFB02/g, 'fl').replace(/\uFB00/g, 'ff').replace(/\uFB03/g, 'ffi').replace(/\uFB04/g, 'ffl');
        text = text.replace(/[\u2018\u2019\u201B]/g, "'").replace(/[\u201C\u201D\u201E]/g, '"');
        text = text.replace(/[ \t\f\v]+/g, ' ');
        text = text.split('\n').map(function (l) {
            l = l.trim();
            // "soru 1", "SORU 1:", "Soru-1" rozet/başlık satırları -> "1."
            var m = l.match(/^soru\s*[-–:]?\s*(\d{1,3})\s*[\.\):\-–]?\s*(.*)$/i);
            if (m) return m[1] + '. ' + m[2];
            return l;
        }).join('\n');
        text = text.replace(/\n{3,}/g, '\n\n');
        return text.trim();
    }

    /* ================= ORTAK SABİTLER ================= */
    var LETTERS = 'A-Za-zğüşıöçĞÜŞİÖÇ';
    function lc(s) { return String(s == null ? '' : s).toLocaleLowerCase('tr-TR'); }
    function alnumLen(s) { return (s.match(/[0-9A-Za-zğüşıöçĞÜŞİÖÇ]/g) || []).length; }
    function normKey(s) { return lc(s).replace(/<[^>]*>/g, '').replace(/[^0-9a-zğüşıöç]/g, ''); }

    var KEY_HEAD_SRC = '(?:CEVAP\\s*ANAHTAR[A-Za-zğüşıöçĞÜŞİÖÇ]*|DO[ĞG]RU\\s*CEVAPLAR[A-Za-zğüşıöçĞÜŞİÖÇ]*|CEVAPLAR(?![ıiİI]n[ıiİI]z)[A-Za-zğüşıöçĞÜŞİÖÇ]*|YANIT\\s*ANAHTAR[A-Za-zğüşıöçĞÜŞİÖÇ]*|YANITLAR(?![ıiİI]n[ıiİI]z)[A-Za-zğüşıöçĞÜŞİÖÇ]*|ANSWER\\s*KEY|ANSWERS)';
    var KEY_HEAD = new RegExp(KEY_HEAD_SRC, 'gi');
    var KEY_HEAD_STRIP = new RegExp('^.*?' + KEY_HEAD_SRC + '\\s*[:：\\-–]?', 'i');

    var Q_START = /^(?:\(\s*([A-Ea-eDdYy])?\s*\)\s*)?(?:SORU\s*)?(\d{1,3})\s*[\.\)\-:](?!\d)\s*/i;
    var OPT_LINE = /^\(?[A-Ea-e]\s?[\)\.]\s*\S/;
    var VAL = '(?:do[ğg]ru|yanl[ıi][şs]|true|false|[A-Ea-eYyTtFf])';
    var PAIR_SRC = '(\\d{1,3})\\s*[-\\.\\)\\:–=\\(]?\\s*(' + VAL + ')(?![' + LETTERS + '0-9])\\)?';

    /* ---------- bölüm başlığı / yönerge türü ---------- */
    function headingKind(line) {
        var t = String(line || '').trim();
        if (!t || t.length > 140 || /\?/.test(t)) return null;
        if (Q_START.test(t)) return null;
        if (OPT_LINE.test(t)) {
            var restT = t.replace(/^\(?[A-Ea-e]\s?[\)\.]\s*/, ''), rl = restT.replace(/[^A-Za-zğüşıöçĞÜŞİÖÇ]/g, ''), ru = restT.replace(/[^A-ZÇĞİÖŞÜ]/g, '');
            if (!(rl.length >= 8 && ru.length / rl.length >= 0.8)) return null;
        }
        var s = lc(t), kind = null;
        if (/çoktan\s*seçmeli|^\W*(?:[a-zıivx0-9]{1,4}[\.\):\-]\s*)?test(?:\s+sorular\w*)?\W*$/.test(s)) kind = 'multiple';
        else if (/doğru\s*[-–\/,&]?\s*(?:ya\s*da\s*|veya\s*|ve\s*)?yanlış|\bd\s*\/\s*y\b|doğru\s*ise\s*d\b/.test(s)) kind = 'truefalse';
        else if (/boşluk\s*doldurma|boşluk(?:lar|ları)?\s*(?:doldur|tamamla)|boşlukları|cümleleri\s*tamamla|noktalı\s*yer|uygun\s*(?:sözcük|kelime|ifade)\w*.*(?:tamamla|doldur|yaz)/.test(s)) kind = 'gap';
        else if (/klasik|açık\s*uçlu|yazılı\s*soru|kısa\s*(?:cevaplı|yanıtlı|cevap)|uzun\s*cevap/.test(s)) kind = 'classic';
        if (!kind) return null;
        var letters = t.replace(/[^A-Za-zğüşıöçĞÜŞİÖÇ]/g, ''), up = t.replace(/[^A-ZÇĞİÖŞÜ]/g, '');
        var mostlyUp = letters.length > 0 && up.length / letters.length >= 0.6;
        if (mostlyUp || t.length <= 60 || /(?:yazınız|işaretleyiniz|doldurunuz|tamamlayınız|belirtiniz|cevaplayınız|bölüm|kısım)/.test(s)) return kind;
        return null;
    }

    /* ---------- cevap değeri normalleştirme ---------- */
    function tfOf(raw) {
        var m = String(raw || '').trim().match(/^[\(\[]?\s*([^\s\)\]\.,;:]+)/);
        if (!m) return null;
        var t = lc(m[1]);
        if (/^(d|doğru|dogru|t|true|a|✓|✔|\+|evet)$/.test(t)) return 'A';
        if (/^(y|yanlış|yanlis|f|false|b|✗|✘|×|-|hayır)$/.test(t)) return 'B';
        return null;
    }
    function normAnswer(type, opts, raw) {
        raw = String(raw == null ? '' : raw).trim();
        if (!raw) return null;
        if (type === 'multiple') {
            var m = raw.match(/^\(?\s*([A-Ea-e])\s*[\)\.\-,:;]?(?:\s|$)/);
            if (m && opts[m[1].toUpperCase()]) return { correct: m[1].toUpperCase() };
            var nr = normKey(raw), k, keys = ['A', 'B', 'C', 'D', 'E'];
            if (nr) for (var i = 0; i < 5; i++) { k = keys[i]; if (opts[k] && normKey(opts[k]) === nr) return { correct: k }; }
            return null;
        }
        if (type === 'truefalse') { var v = tfOf(raw); return v ? { correct: v } : null; }
        var t = raw.replace(/\s+/g, ' ').trim();
        if (type === 'gap') t = t.replace(/^["']+|["']+$/g, '').replace(/[\.\s]+$/, '');
        return t ? { text: t } : null;
    }

    /* ================= CEVAP ANAHTARI ================= */
    function pairsOf(line) {
        var re = new RegExp(PAIR_SRC, 'gi'), out = [], m;
        while ((m = re.exec(line)) !== null) {
            var prev = m.index > 0 ? line.charAt(m.index - 1) : ' ';
            if (/[0-9A-Za-zğüşıöçĞÜŞİÖÇ]/.test(prev)) continue;
            out.push({ n: parseInt(m[1], 10), v: m[2] });
        }
        return out;
    }
    function isPairsLine(line) {
        var ps = pairsOf(line);
        if (!ps.length) return false;
        var rest = line.replace(new RegExp(PAIR_SRC, 'gi'), ' ');
        return /^[\s,;|\/\-–\.:]*$/.test(rest);
    }
    function isKeyLine(ln) {
        if (headingKind(ln)) return true;
        KEY_HEAD.lastIndex = 0;
        var t = ln.replace(KEY_HEAD, ' ').replace(/\b(?:TEST|SORU|NO|CEVAP|YANIT|S[İI]RA|[ŞS][İI]K)\b/gi, ' ').replace(new RegExp(PAIR_SRC, 'gi'), ' ');
        t = t.replace(/\b\d{1,3}\b/g, ' ').replace(/\b[A-Ea-eYyTtFf]{1,60}\b/g, ' ');
        return alnumLen(t) === 0;
    }

    // "1-B 2-D 3-D 4. Ankara" -> sıralı numaralı girdiler (her numara bir öncekinden tam 1 büyük olmalı)
    function splitEntries(line) {
        var re = /(^|[\s,;|])(\d{1,3})\s*[\.\)\-:–=]\s*/g, m, hits = [], last = null;
        while ((m = re.exec(line)) !== null) {
            var n = parseInt(m[2], 10);
            if (last === null || n === last + 1) { hits.push({ n: n, start: m.index + m[1].length, end: m.index + m[0].length }); last = n; }
        }
        if (!hits.length || hits[0].start !== 0) return [];
        return hits.map(function (h, i) {
            var v = line.slice(h.end, i + 1 < hits.length ? hits[i + 1].start : line.length).replace(/[\s,;|]+$/, '').trim();
            return { n: h.n, v: v };
        }).filter(function (e) { return e.v; });
    }

    // Anahtar satırlarını "koşular" (run) listesine çevirir. Numara başa dönünce ya da bölüm başlığı gelince yeni koşu başlar.
    function parseKeyLines(lines, firstNo, hasHeader) {
        var runs = [], run = null, last = 0, lastText = null;
        function newRun(hint) {
            if (run && !run.entries.length) { if (hint) run.hint = hint; return; }
            run = { hint: hint || null, entries: [] }; runs.push(run); last = 0; lastText = null;
        }
        function add(n, v, isText) {
            if (!run) newRun(null);
            if (run.entries.length && n <= last) newRun(null);
            run.entries.push({ n: n, v: v }); last = n; lastText = isText ? run.entries[run.entries.length - 1] : null;
        }
        for (var i = 0; i < lines.length; i++) {
            var ln = lines[i].trim();
            if (!ln) { continue; }
            if (/^[:：\-–]\s*/.test(ln)) ln = ln.replace(/^[:：\-–]\s*/, '');
            if (!ln) continue;
            // etiket + çiftler: "ÇOKTAN SEÇMELİ: 1-A 2-B"
            var lm = ln.match(/^([^\d]{3,80}?)\s*[:：\-–]\s*(\d.*)$/);
            if (lm && headingKind(lm[1])) { newRun(headingKind(lm[1])); ln = lm[2]; }
            else if (!/^\d/.test(ln) && headingKind(ln)) { newRun(headingKind(ln)); continue; }
            // tablo: "1 2 3 4 5" + "A B C D E"
            if (/^(?:\d{1,3}[\s,;|]*){2,}$/.test(ln) && i + 1 < lines.length && /^(?:[A-Ea-eYyTtFf][\s,;|]*){2,}$/.test(lines[i + 1].trim())) {
                var ns = ln.match(/\d{1,3}/g), ls = lines[i + 1].trim().match(/[A-Ea-eYyTtFf]/g);
                if (ns.length === ls.length) { ns.forEach(function (x, k) { add(parseInt(x, 10), ls[k], false); }); i++; continue; }
            }
            if (isPairsLine(ln)) { pairsOf(ln).forEach(function (p) { add(p.n, p.v, false); }); continue; }
            var ents = splitEntries(ln.replace(/^soru\s*(?=\d)/i, ''));
            if (ents.length) {
                ents.forEach(function (e, k) { add(e.n, e.v, k === ents.length - 1 && !new RegExp('^' + VAL + '$', 'i').test(e.v)); });
                continue;
            }
            if (hasHeader && (!run || !run.entries.length) && /^(?:[A-Ea-eYyTtFf][\s,;|\-\.\)]*){3,}$/.test(ln)) {
                var lt = ln.match(/[A-Ea-eYyTtFf]/g);
                lt.forEach(function (L, k) { add(firstNo + k, L, false); });
                continue;
            }
            if (lastText) { lastText.v += ' ' + ln; }
        }
        return runs.filter(function (r) { return r.entries.length; });
    }
    function countEntries(runs) { return runs.reduce(function (a, r) { return a + r.entries.length; }, 0); }

    // Cevap anahtarlarını bulur ve metinden çıkarır. Döner: {text, runs}
    function extractKeys(text, firstNo, qCount) {
        var lines = text.split('\n'), heads = [], i;
        for (i = 0; i < lines.length; i++) {
            KEY_HEAD.lastIndex = 0;
            var hmm = KEY_HEAD.exec(lines[i]);
            if (hmm && lines[i].length < 220 && hmm.index <= 25 && !Q_START.test(lines[i].trim()) && lines[i].length - hmm.index - hmm[0].length <= 140) heads.push(i);
        }
        var cuts = [], found = [], upperOpt = /^\(?[A-E]\s?[\)\.]\s*\S/;
        // Satır bir sorunun kendisi gibi mi duruyor? (soru işareti, boşluk, D/Y parantezi ya da altında şık var)
        function looksLikeQuestion(idx) {
            var t = lines[idx];
            if (/\?/.test(t) || /_{2,}|\.{4,}|…/.test(t) || /\(\s*(?:[DYdy]|do[ğg]ru|yanl[ıi][şs])?\s*\)/.test(t)) return true;
            for (var j = idx + 1; j < lines.length; j++) { var u = lines[j].trim(); if (!u) continue; return upperOpt.test(u); }
            return false;
        }
        // j satırı bir cevap anahtarı bloğunun içinde mi? (yukarı doğru numaralı/anahtar satırlarından geçip başlığa ulaşıyorsa)
        function inKeyZone(j) {
            if (looksLikeQuestion(j)) return false;
            var nj = lines[j].trim().match(/^(?:soru\s*)?(\d{1,3})/i);
            // hemen üstteki numaralı satır bir eksiği değilse (numara geri döndüyse) bu satır listenin devamı değildir
            for (var p = j - 1; p >= 0; p--) {
                var up = lines[p].trim(); if (!up) continue;
                var np = up.match(/^(?:soru\s*)?(\d{1,3})\s*[\.\)\-:–=]/i);
                if (np && nj && parseInt(np[1], 10) !== parseInt(nj[1], 10) - 1) return false;
                break;
            }
            var cur = nj ? parseInt(nj[1], 10) : null;
            for (var k = j - 1; k >= 0; k--) {
                var u = lines[k].trim();
                if (!u) continue;
                KEY_HEAD.lastIndex = 0;
                if (KEY_HEAD.test(u) && u.length < 220) return true;
                var nu = u.match(/^(?:soru\s*)?(\d{1,3})\s*[\.\)\-:–=]\s*\S/i);
                if (nu && !looksLikeQuestion(k)) {
                    var vu = parseInt(nu[1], 10);
                    if (cur !== null && vu !== cur - 1 && !isPairsLine(u)) return false; // liste sırası bozuldu
                    cur = vu; continue;
                }
                if (isKeyLine(u)) continue;
                return false;
            }
            return false;
        }
        // Bu numaralı soru, anahtar bölgesinden sonra gerçekten soru olarak geçiyor mu?
        function laterQuestion(n, from) {
            for (var j = from + 1; j < lines.length; j++) {
                var qm = lines[j].trim().match(Q_START);
                if (qm && parseInt(qm[2], 10) === n && !inKeyZone(j)) return true;
            }
            return false;
        }
        for (var h = heads.length - 1; h >= 0; h--) {
            var st = heads[h];
            if (cuts.some(function (c) { return st >= c.st && st < c.en; })) continue;
            KEY_HEAD.lastIndex = 0;
            var hm = KEY_HEAD.exec(lines[st]);
            var prefix = hm ? lines[st].slice(0, hm.index).trim() : '';
            var headPart = lines[st].slice(hm ? hm.index : 0).replace(KEY_HEAD_STRIP, '').trim();
            var optsAfter = 0;
            for (i = st + 1; i < lines.length; i++) if (upperOpt.test(lines[i].trim())) optsAfter++;
            var en = st + 1, blank = 0;
            if (h === heads.length - 1 && optsAfter < 3 && st >= lines.length * 0.25) {
                // anahtar belgenin sonunda: alt başlıklar ve çok satırlı cevaplarla birlikte devam eder;
                // soru kalıbı taşıyan ya da (başlık olmadan) numarası geri dönen satırda durur
                var lastE = null, maxE = 0, nBefore = 0;
                for (i = st - 1; i >= 0; i--) { var qb = lines[i].trim().match(Q_START); if (qb) { nBefore = parseInt(qb[2], 10); break; } }
                while (en < lines.length) {
                    var tz = lines[en].trim();
                    if (!tz) { en++; continue; }
                    KEY_HEAD.lastIndex = 0;
                    if ((headingKind(tz) && !/\d/.test(tz)) || (KEY_HEAD.test(tz) && tz.length < 80 && !/^\d/.test(tz))) { lastE = null; en++; continue; }
                    var lm0 = tz.match(/^([^\d]{3,80}?)\s*[:：\-–]\s*(\d.*)$/);
                    if (lm0 && headingKind(lm0[1])) { lastE = null; tz = lm0[2]; }
                    if (isPairsLine(tz)) { var pz = pairsOf(tz); lastE = pz[pz.length - 1].n; maxE = Math.max(maxE, lastE); en++; continue; }
                    var nz = tz.match(/^(?:soru\s*)?(\d{1,3})\s*[\.\)\-:–=]\s*\S/i);
                    if (nz) {
                        var nn = parseInt(nz[1], 10);
                        if (looksLikeQuestion(en)) break;
                        // numara geri döndü ve anahtar, kendisinden SONRA gelen sorulara da cevap veriyordu -> burası sorular
                        if (lastE !== null && nn <= lastE && maxE > nBefore) break;
                        lastE = nn; maxE = Math.max(maxE, nn); en++; continue;
                    }
                    en++;
                }
                while (en - 1 > st && lines[en - 1].trim() === '') en--;
            } else {
                // Başlıktan önce görülen son soru numarası: metin cevaplar ("1. Ankara") bu numarayı aşamaz
                var nLast = 0, lastEntry = null, sawHeading = false;
                for (i = st - 1; i >= 0; i--) { var qm0 = lines[i].trim().match(Q_START); if (qm0) { nLast = parseInt(qm0[2], 10); break; } }
                while (en < lines.length) {
                    var t = lines[en].trim();
                    if (t === '') { blank++; if (blank > 2) break; en++; continue; }
                    if (headingKind(t) && !/\d/.test(t)) sawHeading = true;
                    if (t.length < 400 && isPairsLine(t)) { var ps0 = pairsOf(t); lastEntry = ps0[ps0.length - 1].n; blank = 0; en++; continue; }
                    var em = t.match(/^(?:soru\s*)?(\d{1,3})\s*[\.\)\-:–=]\s*\S/i);
                    if (em) {
                        var en0 = parseInt(em[1], 10);
                        var seqOK = lastEntry === null || en0 === lastEntry + 1;
                        if (!sawHeading && en0 <= nLast && seqOK) { lastEntry = en0; blank = 0; en++; continue; }
                        // Anahtar belgenin başında/ortasında: soru daha sonra geliyorsa ("3. Ankara" ... "3. Başkent ____")
                        if (!sawHeading && seqOK && !isKeyLine(t) && !looksLikeQuestion(en) && laterQuestion(en0, en)) { lastEntry = en0; blank = 0; en++; continue; }
                        if (!isKeyLine(t)) break;
                        lastEntry = en0;
                    }
                    if (isKeyLine(t) && t.length < 400) { blank = 0; en++; } else break;
                }
                while (en - 1 > st && (lines[en - 1].trim() === '' || (headingKind(lines[en - 1].trim()) && !/\d/.test(lines[en - 1])))) en--;
            }
            var region = [headPart].concat(lines.slice(st + 1, en));
            var runs = parseKeyLines(region, firstNo, true);
            var shortHead = lines[st].length - (hm ? hm.index : 0) <= 40 && !/\d/.test(lines[st].slice(0, hm ? hm.index + hm[0].length : 0));
            if (countEntries(runs) >= (shortHead ? 1 : Math.min(2, qCount || 2))) { cuts.push({ st: st, en: en, prefix: prefix }); found.unshift(runs); }
        }
        if (cuts.length) {
            cuts.sort(function (a, b) { return b.st - a.st; });
            cuts.forEach(function (c) { lines.splice(c.st, c.en - c.st, c.prefix ? c.prefix : null); });
            lines = lines.filter(function (l) { return l !== null; });
            return { text: lines.join('\n').trim(), runs: [].concat.apply([], found) };
        }

        // Başlıksız: metnin sonundaki satırlar yalnızca "1-A 2-B ..." ise
        var i2 = lines.length - 1, startIdx = lines.length, seen = 0;
        var okLine = new RegExp('^(?:(?:\\d{1,3}\\s*[-\\.\\)\\:–=]?\\s*' + VAL + '|\\d{1,3}|[A-Ea-e])(?:\\s*[,;|\\s]\\s*|$))+$', 'i');
        while (i2 >= 0) {
            var ln = lines[i2].trim();
            if (ln === '') { i2--; continue; }
            if (okLine.test(ln) && ln.length < 400) { startIdx = i2; seen++; i2--; } else break;
        }
        if (seen > 0) {
            var runs2 = parseKeyLines(lines.slice(startIdx), firstNo, false);
            if (countEntries(runs2) >= Math.max(2, Math.min(3, qCount || 3))) return { text: lines.slice(0, startIdx).join('\n').trim(), runs: runs2 };
        }
        return { text: text, runs: [] };
    }

    /* ================= SORU BÖLME (bölümler + numara sıfırlanması) ================= */
    function splitQuestions(text) {
        var lines = text.split('\n'), cands = [], i;
        for (i = 0; i < lines.length; i++) {
            var m = lines[i].match(Q_START);
            // "7. Sınıf 1. Dönem", "1. Yazılı Hazırlık", "2. Ünite" gibi başlık satırları soru değildir
            if (m && lines[i].length < 48 && !/\?/.test(lines[i]) && /^\s*\d{1,3}\s*\.\s*(?:s[ıi]n[ıi]f|d[öo]nem|yaz[ıi]l[ıi]|yaz[ıi]lya|[üu]nite|b[öo]l[üu]m|tema|deneme|hafta|s[ıi]nav[ıi]?\b|d[öo]nem)/i.test(lines[i])) m = null;
            if (m && lines[i].length > m[0].length - 1) cands.push({ line: i, num: parseInt(m[2], 10), len: m[0].length, pre: m[1] || '', paren: m[0].charAt(0) === '(' });
        }
        if (!cands.length) return { sections: [], pre: text };
        var startNo = null, a;
        for (a = 0; a < cands.length; a++) { if (cands[a].num === 1) { startNo = 1; break; } }
        if (startNo === null) startNo = Math.min.apply(null, cands.map(function (c) { return c.num; }));

        function restartOK(c) {
            var end = cands[c + 1] ? cands[c + 1].line : lines.length;
            var seg = lines.slice(cands[c].line, end).join('\n');
            return /(?:^|\n)\s*\(?[A-Ea-e]\s?[\)\.]\s*\S/.test(seg) || cands[c].paren || /_{2,}|\.{4,}|…/.test(seg) || /\(\s*(?:[DYdy]|do[ğg]ru|yanl[ıi][şs])?\s*\)/.test(seg);
        }
        function hasEv(a, b) {
            var seg = lines.slice(a, b).join('\n');
            return /(?:^|\n)\s*\(?[A-Ea-e]\s?[\)\.]\s*\S/.test(seg) || /_{2,}|\.{4,}|…/.test(seg) || /\(\s*(?:[DYdy]|do[ğg]ru|yanl[ıi][şs])?\s*\)/.test(seg) || /(?:cevap|yan[ıi]t)\s*[:：]/i.test(seg);
        }
        var secs = [], cur = null, expected = 0;
        function startSec(cd) { cur = { cands: [cd] }; secs.push(cur); expected = cd.num + 1; }
        for (var c = 0; c < cands.length; c++) {
            var cd = cands[c];
            if (!cur) { if (cd.num === startNo) startSec(cd); continue; }
            if (cd.num === expected) {
                var prevLn = cur.cands[cur.cands.length - 1].line, skip = false;
                if (!hasEv(prevLn, cd.line)) {
                    for (var d = c + 1; d < cands.length; d++) {
                        var dd = cands[d];
                        if (dd.num === expected + 1 || dd.num < expected) break;
                        if (dd.num === expected && hasEv(prevLn, dd.line)) { skip = true; break; }
                    }
                }
                if (!skip) { cur.cands.push(cd); expected++; }
            }
            else if (cd.num > expected && cd.num <= expected + 3) {
                var nxt = cands[c + 1];
                if (!nxt || nxt.num === cd.num + 1) { cur.cands.push(cd); expected = cd.num + 1; }
            } else if (cd.num === 1 && restartOK(c)) startSec(cd);
        }
        if (!secs.length) return { sections: [], pre: text };
        // Tek adaylı, kanıtsız (şık/boşluk/cevap yok) bölüm ve hemen ardından aynı numarayla başlayan bölüm: ilki başlıktır ("1. SINIF TÜRKÇE")
        while (secs.length >= 2 && secs[0].cands.length === 1 && secs[1].cands[0].num === secs[0].cands[0].num && !hasEv(secs[0].cands[0].line, secs[1].cands[0].line)) secs.shift();

        var sections = [];
        secs.forEach(function (sec, si) {
            var endLine = secs[si + 1] ? secs[si + 1].cands[0].line : lines.length;
            var blocks = sec.cands.map(function (cd, bi) {
                var from = cd.line, to = bi + 1 < sec.cands.length ? sec.cands[bi + 1].line : endLine;
                var arr = lines.slice(from, to);
                arr[0] = arr[0].slice(cd.len);
                return { num: cd.num, lines: arr, pre: cd.pre, paren: cd.paren };
            });
            sections.push({ pre: si === 0 ? lines.slice(0, sec.cands[0].line).join('\n') : '', blocks: blocks });
        });
        return { sections: sections, pre: sections[0].pre };
    }

    // Numarasız "( ) Cümle" satırlarına önceki soru numarasının devamı olarak numara ver
    function numberMarked(lines) {
        var last = 0, out = [], any = false;
        lines.forEach(function (l) {
            var t = l.trim(), qm = t.match(Q_START);
            if (qm) { last = parseInt(qm[2], 10); out.push(l); }
            else if (TF_START.test(t) && t.replace(TF_START, '').length >= 8 && !isStandaloneMarker(t.replace(TF_START, ''))) { last++; any = true; out.push(last + '. ' + t); }
            else out.push(l);
        });
        return any ? out : lines;
    }

    // Numarasız ama "( ) Cümle" ile başlayan Doğru/Yanlış satırları
    var TF_START = /^[\(\[]\s*(do[ğg]ru|yanl[ıi][şs]|[DYdy])?\s*(?:[\/\-,]\s*(do[ğg]ru|yanl[ıi][şs]|[DYdy]))?\s*[\)\]]\s*(?=\S)/i;
    function splitMarked(text) {
        var lines = text.split('\n'), blocks = [], cur = null;
        lines.forEach(function (l) {
            var t = l.trim();
            if (TF_START.test(t)) { cur = { num: blocks.length + 1, lines: [t], pre: '', paren: false }; blocks.push(cur); }
            else if (cur && t) cur.lines.push(t);
        });
        return blocks.length >= 2 ? blocks : [];
    }

    /* ---------- ŞIK AYIRMA ---------- */
    function findOptionChain(flat, lowerCase) {
        var L = lowerCase ? 'a-e' : 'A-E';
        var re = new RegExp('(^|[\\s>\\]])(\\(?)([' + L + '])\\s?(?:\\)|\\.(?=\\s|$)|\\-(?=\\s))', 'g');
        var marks = [], m;
        while ((m = re.exec(flat)) !== null) {
            var letter = m[3].toUpperCase();
            // "(A" parantezi açıksa kapanış ")" şart değil; ama "A." için sonrası boşluk zorunlu (regex'te)
            marks.push({ letter: letter, idx: m.index + m[1].length, end: m.index + m[0].length });
        }
        if (!marks.length) return null;
        var order = ['A', 'B', 'C', 'D', 'E'];
        var bestChain = null;
        // En sondaki geçerli A'dan başlayan zinciri tercih et (soru metnindeki "A." yanlış alarmını önler)
        for (var s = marks.length - 1; s >= 0; s--) {
            if (marks[s].letter !== 'A') continue;
            var chain = [marks[s]], pos = s;
            for (var k = 1; k < 5; k++) {
                var found = -1;
                for (var j = pos + 1; j < marks.length; j++) { if (marks[j].letter === order[k]) { found = j; break; } }
                if (found < 0) break;
                chain.push(marks[found]); pos = found;
            }
            if (chain.length >= 2) { bestChain = chain; break; }
        }
        return bestChain;
    }

    function extractOptions(blockLines, kind) {
        var flat = blockLines.join('\n');
        var chain = findOptionChain(flat, false), lower = false;
        if (!chain) { chain = findOptionChain(flat, true); lower = !!chain; }
        if (chain) {
            var n0 = chain.length;
            if (kind === 'classic') { if (lower || n0 < 4) chain = null; }
            else if (lower && n0 < 4 && kind !== 'multiple') chain = null;
        }
        if (!chain) return { stem: flat, opts: null, tail: '', flagged: [], optLines: 0 };

        var stem = flat.slice(0, chain[0].idx).trim();
        var opts = { A: '', B: '', C: '', D: '', E: '' }, tail = '';
        for (var i = 0; i < chain.length; i++) {
            var seg = flat.slice(chain[i].end, i + 1 < chain.length ? chain[i + 1].idx : flat.length);
            if (i === chain.length - 1) {
                var segLines = seg.split('\n');
                var take = [segLines[0]], r = 1;
                while (r < segLines.length && /^\s*(?:§IMG\d+§\s*)+(?:§K§)?$/.test(segLines[r])) { take.push(segLines[r]); r++; }
                while (r < segLines.length && segLines[r].trim() !== '' && /^[a-zçğıöşü]/.test(segLines[r].trim()) && !/[\.\?\!:]$/.test(take[take.length - 1].trim()) && !/(^|\s)(?:diğer|sayfa)/i.test(segLines[r])) { take.push(segLines[r]); r++; }
                seg = take.join(' ');
                tail = segLines.slice(r).join('\n').trim();
            }
            opts[chain[i].letter] = seg.replace(/\s*\n\s*/g, ' ').replace(/\s{2,}/g, ' ').trim();
        }
        // İki şık: büyük harfli, kısa ve soru işaretsizse gerçek şıktır ("A) Kedi B) Kuş"); değilse klasik sorunun alt maddeleri ("a) Güneş nedir? b) ...")
        var twoOK = !lower && [opts.A, opts.B].every(function (o) { var t = String(o).replace(/§K§/g, '').trim(); return t && t.length <= 60 && !/\?\s*$/.test(t); });
        if (chain.length === 2 && kind !== 'multiple' && !(isTFWord(opts.A) && isTFWord(opts.B)) && !(twoOK && kind !== 'classic')) return { stem: flat, opts: null, tail: '', flagged: [], optLines: 0 };

        // Resimli şıklar: "[r1] [r2] [r3]" satırı harflerin ÜSTÜNDE ya da ALTINDA ise resimleri sırayla şıklara dağıt
        var letters = chain.map(function (c) { return c.letter; });
        var onlyTok = function (x) { return /^\s*(?:§IMG\d+§\s*)+$/i.test(x || ''); };
        var empties = letters.filter(function (L) { return !String(opts[L]).replace(/§K§/g, '').trim(); }).length;
        if (empties === letters.length) {
            var stemLines = stem.split('\n'), li = stemLines.length - 1;
            while (li >= 0 && !stemLines[li].trim()) li--;
            if (li >= 0 && onlyTok(stemLines[li])) {
                var toks = stemLines[li].match(/§IMG\d+§/gi);
                if (toks.length === letters.length) { letters.forEach(function (L, k) { opts[L] = toks[k]; }); stemLines.splice(li, 1); stem = stemLines.join('\n').trim(); }
            }
        } else if (empties === letters.length - 1 && onlyTok(opts[letters[letters.length - 1]])) {
            var toks2 = opts[letters[letters.length - 1]].match(/§IMG\d+§/gi);
            if (toks2.length === letters.length) letters.forEach(function (L, k) { opts[L] = toks2[k]; });
        }

        // Vurgulu/kalın şık: her şık kendi satırındaysa ve yalnızca bir kısmı vurguluysa cevap odur
        var flagged = chain.filter(function (c) { return opts[c.letter].indexOf('§K§') >= 0; }).map(function (c) { return c.letter; });
        var optLines = flat.split('\n').filter(function (l) { return /^\(?[A-Ea-e]\s?[\)\.]/.test(l.trim()); }).length;
        if (flagged.length === chain.length || optLines < chain.length) flagged = [];
        return { stem: stem, opts: opts, tail: tail, flagged: flagged, optLines: optLines };
    }

    /* ---------- GÖVDE: PARAGRAF / SORU KÖKÜ / <br> ---------- */
    var ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
    var PREMISE_START = /^(?:\(?(?:I{1,3}|IV|VI{0,3}|IX|X)[\.\)]|\(?\d{1,2}[\.\)]|\([a-e]\)|[a-e]\)|[•▪●◦■□○\-–—])\s*(?=[^\sa-zçğıöşü])/;

    // Aynı satırda "I. ... II. ... III. ..." gibi gömülü sıralı öncülleri ayrı satırlara böler
    function splitInlineLists(line) {
        var re = /(^|\s)(\(?)(I{1,3}|IV|VI{0,3}|IX|X)([\.\)])\s+/g, m, hits = [];
        while ((m = re.exec(line)) !== null) hits.push({ idx: m.index + m[1].length, label: m[3] });
        // Roma rakamları: I, II, III... ardışık olmalı
        var seq = [], want = 0;
        for (var i = 0; i < hits.length; i++) { if (hits[i].label === ROMAN[want]) { seq.push(hits[i]); want++; } }
        if (seq.length >= 2 && seq[0].label === 'I') line = breakAt(line, seq);
        // (1) (2) (3) parantezli sayılar
        var re2 = /(^|\s)\((\d{1,2})\)\s*/g, h2 = [], w2 = 1;
        while ((m = re2.exec(line)) !== null) { if (parseInt(m[2], 10) === w2) { h2.push({ idx: m.index + m[1].length }); w2++; } }
        if (h2.length >= 2) line = breakAt(line, h2);
        // 1. 2. 3. düz sayılar (en az 3 ardışık)
        var re3 = /(^|\s)(\d{1,2})[\.\)]\s+(?=\S)/g, h3 = [], w3 = 1;
        while ((m = re3.exec(line)) !== null) { if (parseInt(m[2], 10) === w3) { h3.push({ idx: m.index + m[1].length }); w3++; } }
        if (h3.length >= 3) line = breakAt(line, h3);
        return line;
    }
    function breakAt(line, hits) {
        var out = '', last = 0;
        hits.forEach(function (h) { if (h.idx > last) { out += line.slice(last, h.idx).replace(/\s+$/, '') + '\n'; last = h.idx; } });
        return out + line.slice(last);
    }

    var CUE = /(\?|\bhangi\w*|\başağıda\w*|\byukarıda\w*|bulunuz|hesaplay[ıi]n[ıi]z|[çc]evirin[ıi]z|s[ıi]ralay[ıi]n[ıi]z|belirleyin[ıi]z|yaz[ıi]n[ıi]z|a[çc][ıi]klay[ıi]n[ıi]z|g[öo]sterin[ıi]z|cevaplay[ıi]n[ıi]z|i[şs]aretleyin[ıi]z|doldurunuz|e[şs]le[şs]tirin[ıi]z|tamamlay[ıi]n[ıi]z|[çc]izin[ıi]z|kar[şs][ıi]la[şs]t[ıi]r[ıi]n[ıi]z|yap[ıi]n[ıi]z|\b\bkaçtır|\bkimdir|\bnedir|\bnelerdir|\bnasıl\b|söylenemez|\bçıkarılamaz|\bçıkarılabilir|\bdeğildir|\bgetirilebilir|\byerleştirilmelidir|\bsıralanır|\bsıralanırsa|\btamamlanır|\bgelmelidir)/i;
    var ABBR = /(?:^|\s)(?:Dr|Prof|Doç|Yrd|Sn|Av|St|Müh|vb|vs|bkz|Bkz|yy|Çev|Haz|Alb|Gen|Kd|Öğr|Gör|No|M|S|[A-ZÇĞİÖŞÜ])$/;

    function lastSentenceSplit(line) {
        // Tek satırda "... paragraf. Aşağıdakilerden hangisi ...?" -> [paragraf, kök]; yalnızca kök cümlesi belirgin ise böler
        var re = /([\.\!\?…:])\s+(?=["'(\[]?[A-ZÇĞİÖŞÜ0-9])/g, m, cut = -1;
        while ((m = re.exec(line)) !== null) {
            var before = line.slice(0, m.index + 1);
            if (m[1] === '.' && ABBR.test(before.slice(0, -1))) continue;
            if (/(?:^|\s)(?:I{1,3}|IV|V|VI{0,3}|IX|X)\.$/.test(before)) continue;
            cut = m.index + m[0].length;
        }
        if (cut < 0) return null;
        // tek/iki kelimelik yönerge ("Açıklayınız.") kendi başına kök olmaz: önceki cümleyle birlikte kalır
        var cuts = []; re.lastIndex = 0;
        while ((m = re.exec(line)) !== null) {
            var bf = line.slice(0, m.index + 1);
            if (m[1] === '.' && ABBR.test(bf.slice(0, -1))) continue;
            if (/(?:^|\s)(?:I{1,3}|IV|V|VI{0,3}|IX|X)\.$/.test(bf)) continue;
            cuts.push(m.index + m[0].length);
        }
        cut = -1;
        for (var ci = cuts.length - 1; ci >= 0; ci--) {
            var tl = line.slice(cuts[ci]).trim();
            if (tl.split(/\s+/).length <= 2) continue;
            cut = cuts[ci]; break;
        }
        if (cut < 0) return null;
        var head = line.slice(0, cut).trim(), tail = line.slice(cut).trim();
        if (tail.length < 8 || !CUE.test(tail)) return null;
        // tek cümleden uzun bir kök olabilir: kökün başlangıcını geriye doğru, ilk "ipucu içeren" cümleye çek
        return [head, tail];
    }

    function buildStem(stemText) {
        var rawLines = stemText.split('\n').map(function (l) { return l.trim(); }).filter(function (l) { return l !== ''; });
        // gömülü sıralı listeleri aç
        var lines = [];
        rawLines.forEach(function (l) { splitInlineLists(l).split('\n').forEach(function (x) { x = x.trim(); if (x) lines.push(x); }); });
        if (!lines.length) return { paragraph: '', root: '' };

        // Satır sonunda bölünmüş cümleleri birleştir (sonraki satır küçük harf ya da rakamla devam ediyorsa)
        var TOKL = /^\s*(?:§(?:IMG|TBL)\d+§\s*)+$/i;
        function mathy(l) { return !/[A-Za-zÇĞİÖŞÜçğıöşü]{3,}/.test(l.replace(/§(?:IMG|TBL)\d+§/gi, '')) && l.length <= 80; }
        function labelLine(l) { return l.length <= 45 && /[:=]\s*$/.test(l); }
        var merged = [];
        lines.forEach(function (l) {
            var prev = merged.length ? merged[merged.length - 1] : null;
            if (prev && !/[\.\?\!:;…]["')]?\s*$/.test(prev) && /^[a-zçğıöşü0-9]/.test(l) && !PREMISE_START.test(l) && !PREMISE_START.test(prev) &&
                !TOKL.test(l) && !TOKL.test(prev) && !mathy(prev) && !mathy(l) && !labelLine(l)) { merged[merged.length - 1] = prev + ' ' + l; return; }
            merged.push(l);
        });
        lines = merged;

        // Son öncül satırının sonunda soru kökü cümlesi varsa ayır ("III. Can kaldı. Hangisi doğrudur?")
        var lastIdx = lines.length - 1;
        if (lines.length >= 2 && PREMISE_START.test(lines[lastIdx])) {
            var sp3 = lastSentenceSplit(lines[lastIdx]);
            if (sp3) { lines.splice(lastIdx, 1, sp3[0], sp3[1]); }
        }
        var isP = lines.map(function (l) { return PREMISE_START.test(l); });

        // Tek satır ve premise değilse: cümle düzeyinde kök ayır
        if (lines.length === 1) {
            var sp = !isP[0] ? lastSentenceSplit(lines[0]) : null;
            if (sp) return { paragraph: sp[0], root: sp[1] };
            return { paragraph: '', root: lines[0] };
        }

        // Kök satırı: sondan başlayarak, öncül olmayan ve ipucu içeren ilk satır; yoksa öncül olmayan son satır
        var rootIdx = -1, i;
        for (i = lines.length - 1; i >= 0; i--) { if (!isP[i] && CUE.test(lines[i])) { rootIdx = i; break; } }
        if (rootIdx < 0) { for (i = lines.length - 1; i >= 0; i--) { if (!isP[i]) { rootIdx = i; break; } } }
        if (rootIdx < 0) rootIdx = lines.length - 1;

        var before = lines.slice(0, rootIdx), after = lines.slice(rootIdx + 1);
        var rootLines = [lines[rootIdx]];
        // Kökten SONRA gelen öncüller, cevap etiketleri ("Doğal Sayı :", "1,2 ="), işlem satırları ve resimler kökün devamıdır
        var allAfterPremise = after.length > 0 && after.every(function (l, k) { return isP[rootIdx + 1 + k] || labelLine(l) || mathy(l) || TOKL.test(l); });
        if (allAfterPremise) rootLines = rootLines.concat(after); else if (after.length) before = before.concat(after);

        // Kök satırının kendi içinde de paragraf + kök olabilir (tek satırlık uzun kök)
        var rootFirst = rootLines[0], extraPara = null;
        if (before.length === 0 || true) {
            var sp2 = !PREMISE_START.test(rootFirst) ? lastSentenceSplit(rootFirst) : null;
            if (sp2 && sp2[1].length < rootFirst.length) { extraPara = sp2[0]; rootLines[0] = sp2[1]; }
        }
        if (extraPara) before = before.concat([extraPara]);

        return { paragraph: joinLines(before), root: joinLines(rootLines) };
    }

    // Satırları HTML'e çevirir: öncül/sıralı cümleler -> <br>, ayrı paragraflar -> <br><br>
    function joinLines(arr) {
        var out = '';
        for (var i = 0; i < arr.length; i++) {
            var cur = arr[i], p = PREMISE_START.test(cur);
            if (i === 0) { out = cur; continue; }
            var prevP = PREMISE_START.test(arr[i - 1]);
            var compact = function (l) { return l.length <= 45 && /[:=]\s*$/.test(l); };
            out += (p || prevP || compact(cur) || compact(arr[i - 1])) ? '<br>' : '<br><br>';
            out += cur;
        }
        return out;
    }

    /* ---------- OLUMSUZ KELİME VURGUSU ---------- */
    function highlightNegatives(text) {
        if (!text) return '';
        var negRegex = /(^|[^a-zA-ZğüşıöçĞÜŞİÖÇ])([a-zA-ZğüşıöçĞÜŞİÖÇ]+(?:maz|mez|mam[ıI][şŞ]t[ıI]r|mem[iİ][şŞ]t[iİ]r|mamaktad[ıI]r|memektedir|mad[ıI][ğĞ][ıI]|med[iİ][ğĞ][iİ]|mayan|meyen|m[ıI]yor|m[iİ]yor)|de[ğĞ][iİ]ld[iİ]r|de[ğĞ][iİ]l|yoktur|yanl[ıI][şŞ]t[ıI]r)(?=[^a-zA-ZğüşıöçĞÜŞİÖÇ]*$)/gi;
        var clean = text.replace(/<u>\s*<strong>(.*?)<\/strong>\s*<\/u>/gi, '$1');
        // <br> sonrası öncülleri etkilememek için yalnızca ilk satıra uygula
        var parts = clean.split('<br>');
        parts[0] = parts[0].replace(negRegex, '$1<u><strong>$2</strong></u>');
        return parts.join('<br>');
    }

    /* ---------- GRUP PARAGRAFI: "5-8. soruları aşağıdaki parçaya göre ..." ---------- */
    var GROUP_HEAD = /(\d{1,3})\s*[-–—]\s*(\d{1,3})\s*\.?\s*(?:ve\s+\d+\.?\s*)?soru/i;
    function parseGroup(tailText) {
        if (!tailText) return null;
        var lines = tailText.split('\n');
        for (var i = 0; i < lines.length; i++) {
            var m = lines[i].match(GROUP_HEAD);
            if (m) {
                var from = parseInt(m[1], 10), to = parseInt(m[2], 10);
                if (to >= from && to - from < 40) {
                    var rest = lines.slice(i + 1).map(function (x) { return x.trim(); }).filter(Boolean);
                    // Satırın kendisinde başlık sonrası metin varsa onu atla (yönerge)
                    return { from: from, to: to, passage: joinLines(rest) };
                }
            }
        }
        return null;
    }


    /* ---------- OCR / düz metin: satır sonu birleştirme ---------- */
    function startsBlock(l) { KEY_HEAD.lastIndex = 0; return Q_START.test(l) || OPT_LINE.test(l) || PREMISE_START.test(l) || GROUP_HEAD.test(l) || TF_START.test(l) || !!headingKind(l) || (KEY_HEAD.test(l) && l.length < 80); }
    function reflowPlain(text) {
        var lines = String(text || '').replace(/\r\n?/g, '\n').split('\n'), out = [];
        for (var i = 0; i < lines.length; i++) {
            var cur = lines[i].trim();
            if (cur === '') { out.push(''); continue; }
            while (i + 1 < lines.length) {
                var nx = lines[i + 1].trim();
                if (nx === '' || /[\.\?\!:;…"')]$/.test(cur) || startsBlock(nx)) break;
                cur += ' ' + nx; i++;
            }
            out.push(cur);
        }
        return out.join('\n');
    }

    /* ---------- Soru numarası yoksa: şık dizilerine göre böl ---------- */
    function splitUnnumbered(text) {
        var lines = text.split('\n'), blocks = [], cur = [], inOpts = false, last = '';
        function flush() { if (cur.some(function (x) { return x.trim(); })) blocks.push({ num: blocks.length + 1, lines: cur }); cur = []; inOpts = false; last = ''; }
        for (var i = 0; i < lines.length; i++) {
            var l = lines[i], t = l.trim(), om = t.match(/^\(?([A-Ea-e])\s?[\)\.]\s*\S/);
            if (om) { inOpts = true; last = om[1].toUpperCase(); cur.push(l); continue; }
            if (!inOpts) { cur.push(l); if (/(?:^|\s)[A-E]\s?\)\s*\S.*\s[B]\s?\)/.test(t)) { inOpts = true; last = 'E'; } continue; }
            if (t === '') { cur.push(l); continue; }
            var contLine = /^[a-zçğıöşü]/.test(t) && !/[\.\?\!:]$/.test(cur[cur.length - 1] ? cur[cur.length - 1].trim() : '');
            if (contLine || /^§IMG\d+§$/.test(t)) { cur.push(l); continue; }
            if (last === 'E' || last === 'D' || last === 'C') { flush(); cur.push(l); } else { cur.push(l); }
        }
        flush();
        return blocks;
    }

    /* ================= CEVAP / TÜR ALGILAMA ================= */
    var ANS_RE = /(?:^|[\s(\[{|•*\-–—])(?:do[ğg]ru\s+)?(?:cevap|yan[ıi]t|cvp|answer|ans)(?:\s*anahtar[ıi])?\s*(?:[:：=→►]|->|=>|\s[-–—]\s|(?=\s*\(\s*[A-Ea-e]\s*\)))\s*/i;
    var ANS_END_RE = /(?:^|\s)(?:do[ğg]ru\s+)?(?:cevap|yan[ıi]t)\s+\(?([A-Ea-e])\)?\s*[\.\)]?\s*$/i;
    var NOT_ANS_FIRST = /^(?:a[çc][ıi]klama|[çc][öo]z[üu]m)\s*[:：]/i;

    // Satırlar içinde "Cevap: ..." arar (sorunun altında, üstünde, yanında). Bulursa satırı o noktadan kesip değeri döner.
    function findAnswer(lines) {
        for (var i = 0; i < lines.length; i++) {
            var ln = lines[i], m = ANS_RE.exec(ln);
            if (m) {
                var opener = m[0].charAt(0), start = m.index + (/^[\s|•*\-–—]/.test(opener) ? 1 : 0);
                var val = ln.slice(m.index + m[0].length).trim();
                if (/[\(\[\{]/.test(opener)) val = val.replace(/[\)\]\}]\s*[\.]?\s*$/, '').trim();
                if (!val && i + 1 < lines.length && lines[i + 1].trim() && !OPT_LINE.test(lines[i + 1].trim())) { val = lines[i + 1].trim(); lines[i + 1] = ''; }
                lines[i] = ln.slice(0, start).replace(/\s+$/, '');
                return { idx: i, raw: val };
            }
            var m2 = ANS_END_RE.exec(ln);
            if (m2) { lines[i] = ln.slice(0, m2.index).replace(/\s+$/, ''); return { idx: i, raw: m2[1] }; }
        }
        return null;
    }
    function hasAnswerMarker(text) {
        var ls = String(text || '').split('\n');
        for (var i = 0; i < ls.length; i++) { if (ANS_RE.test(ls[i]) || ANS_END_RE.test(ls[i])) return true; }
        return false;
    }

    // Satır baştan bir cevap işaretiyle mi başlıyor? ("Cevap: B", "(Cevap: B)", "Doğru cevap: ...")
    function isStandaloneMarker(line) {
        var t = String(line || '').trim();
        if (!t) return false;
        var m = ANS_RE.exec(t);
        if (m && m.index === 0) return true;
        var m2 = ANS_END_RE.exec(t);
        return !!(m2 && m2.index === 0);
    }
    function lineHasMarker(line) { return ANS_RE.test(line) || ANS_END_RE.test(line); }

    // Bloğun sonundaki (şıklardan sonra gelen) bağımsız cevap satırını ve devamını bulur
    function trailingAnswer(lines) {
        for (var t = lines.length - 1; t >= 1; t--) {
            if (!isStandaloneMarker(lines[t])) continue;
            var optAfter = false;
            for (var j = t + 1; j < lines.length; j++) if (OPT_LINE.test(lines[j].trim())) { optAfter = true; break; }
            if (optAfter) return null;
            var r = t + 1;
            while (r < lines.length && lines[r].trim() !== '' && !isStandaloneMarker(lines[r])) r++;
            var before = t - 1; while (before >= 0 && lines[before].trim() === '') before--;
            return { t: t, r: r, blankBefore: lines[t - 1].trim() === '', blankAfter: r < lines.length };
        }
        return null;
    }
    function hasOwnMarker(lines, skip) {
        for (var i = 0; i < lines.length; i++) {
            if (skip && i >= skip.t && i < skip.r) continue;
            if (lineHasMarker(lines[i])) return true;
        }
        return false;
    }
    function takeAnswerText(lines, tr) {
        var region = lines.slice(tr.t, tr.r), fa = findAnswer(region);
        if (!fa) return null;
        var rest = region.slice(fa.idx + 1).map(function (x) { return x.trim(); }).filter(Boolean);
        return ((fa.raw || '') + (rest.length ? ' ' + rest.join(' ') : '')).trim();
    }

    function assignStandaloneAnswers(sec) {
        var B = sec.blocks;
        // İlk sorunun ÜSTÜNDEKİ cevap (ön metnin sonunda)
        var aboveConv = false;
        if (sec.pre && hasAnswerMarker(sec.pre)) {
            var pl = sec.pre.split('\n'), pa = findAnswer(pl);
            if (pa) {
                var restP = pl.slice(pa.idx + 1).map(function (x) { return x.trim(); }).filter(Boolean);
                B[0].carryAns = ((pa.raw || '') + (restP.length ? ' ' + restP.join(' ') : '')).trim();
                aboveConv = true;
            }
        }
        var info = B.map(function (b) { var tr = trailingAnswer(b.lines); return { tr: tr, own: hasOwnMarker(b.lines, tr) }; });
        for (var k = 0; k < B.length; k++) {
            var tr = info[k].tr;
            if (!tr) continue;
            var ownK = info[k].own || !!B[k].carryAns;
            var toNext = false;
            if (k < B.length - 1) {
                var ownNext = info[k + 1].own;
                if (ownK && !ownNext) toNext = true;
                else if (ownK && ownNext) toNext = false;
                else if (ownNext) toNext = false;
                else if (tr.blankBefore && !tr.blankAfter) toNext = true;
                else if (!tr.blankBefore && tr.blankAfter) toNext = false;
                else toNext = aboveConv;
            }
            if (toNext) {
                var txt = takeAnswerText(B[k].lines, tr);
                if (txt !== null) { B[k + 1].carryAns = txt; B[k].lines.splice(tr.t, tr.r - tr.t); }
            }
        }
    }

    var TF_START_G = TF_START;
    var TF_END = /\s*[\(\[]\s*(do[ğg]ru|yanl[ıi][şs]|[DYdy])?\s*(?:[\/\-,]\s*(do[ğg]ru|yanl[ıi][şs]|[DYdy]))?\s*[\)\]]\s*[\.\!]?\s*$/i;
    var TF_TAIL = /\s+(?:D\s*\/\s*Y|do[ğg]ru\s*[\/\-–]\s*yanl[ıi][şs]|\(\s*\)\s*do[ğg]ru\s*\(\s*\)\s*yanl[ıi][şs])\s*$/i;
    var BLANK_RE = /_{2,}|\.{4,}|…{1,}\s*…|…{2,}|(?:^|\s)…(?:\s|[\.,;:!?]|$)|\(\s*\.{2,}\s*\)|\[\s*\]|\(\s*\)/;

    // Doğru/Yanlış kalıbı: {isTF, ans, stem}
    function detectTF(stem, paren) {
        var s = stem.trim(), ans = null, hit = false, m;
        if ((m = s.match(TF_START_G)) && s.length - m[0].length >= 8) {
            hit = true; if (m[1] && !m[2]) ans = m[1]; s = s.slice(m[0].length);
        } else if ((m = s.match(TF_END))) {
            var before = s.slice(0, m.index);
            if (before.trim().length >= 8 && !/\(\s*\)|\[\s*\]/.test(before.replace(/\s+/g, ' '))) { hit = true; if (m[1] && !m[2]) ans = m[1]; s = before; }
        }
        if (!hit && (m = s.match(TF_TAIL))) { hit = true; s = s.slice(0, m.index); }
        if (!hit && paren) hit = true;
        return { isTF: hit, ans: ans, stem: s.trim() };
    }

    function isTFWord(x) { return /^(do[ğg]ru|yanl[ıi][şs])$/i.test(String(x || '').trim()); }

    /* ================= ANA FONKSİYON ================= */
    function parse(rawText) {
        var text = normalize(rawText);
        var report = { total: 0, keyFound: false, keyed: 0, missing: 0, noOptions: 0, counts: { multiple: 0, truefalse: 0, gap: 0, classic: 0 }, warnings: [] };
        if (!text) return { questions: [], report: report };

        // Şıkkı işaretli (kalın/vurgulu) satır imi: yalnızca şık satırlarında kalsın
        text = text.split('\n').map(function (l) { return (/§K§$/.test(l) && !OPT_LINE.test(l)) ? l.replace(/\s*§K§$/, '') : l; }).join('\n');

        // 1) Kaba soru sayısı + cevap anahtarları
        var rough = splitQuestions(text);
        var roughBlocks = rough.sections.reduce(function (a, s) { return a + s.blocks.length; }, 0);
        var firstNo = rough.sections.length ? rough.sections[0].blocks[0].num : 1;
        var ak = extractKeys(text, firstNo, roughBlocks);
        var body = ak.text, runs = ak.runs || [];
        report.keyFound = runs.length > 0;

        // 2) Bölüm başlıklarıyla parçalara ayır
        var lines = body.split('\n'), segs = [{ kind: null, lines: [] }];
        for (var i = 0; i < lines.length; i++) {
            var ln = lines[i], t = ln.trim(), hk = t ? headingKind(t) : null;
            if (hk) {
                var prev = '';
                for (var p = i - 1; p >= 0; p--) { if (lines[p].trim()) { prev = lines[p].trim(); break; } }
                var letters = t.replace(/[^A-Za-zğüşıöçĞÜŞİÖÇ]/g, ''), up = t.replace(/[^A-ZÇĞİÖŞÜ]/g, '');
                var mostlyUp = letters.length > 0 && up.length / letters.length >= 0.6;
                var prevOK = !prev || (i > 0 && !lines[i - 1].trim()) || OPT_LINE.test(prev) || /[\.\!\:]$/.test(prev) || headingKind(prev);
                if (mostlyUp || t.length <= 60 || prevOK) { segs.push({ kind: hk, lines: [] }); continue; }
            }
            segs[segs.length - 1].lines.push(ln);
        }

        // 3) Her parçadaki soruları böl
        var sections = [];
        segs.forEach(function (seg) {
            var segText = numberMarked(seg.lines).join('\n').trim();
            if (!segText) return;
            var sp = splitQuestions(segText);
            if (sp.sections.length) {
                sp.sections.forEach(function (s) { sections.push({ kind: seg.kind, pre: s.pre, blocks: s.blocks }); });
                return;
            }
            var blocks = splitMarked(segText);
            if (!blocks.length && /(?:^|\n)\s*\(?[A-E]\s?[\)\.]\s*\S/.test(segText)) blocks = splitUnnumbered(segText);
            if (blocks.length) sections.push({ kind: seg.kind, pre: '', blocks: blocks });
        });
        if (!sections.length) return { questions: [], report: report };

        // 4) Blokları işle
        var out = [], secMeta = [];
        sections.forEach(function (sec, si) {
            var kind = sec.kind, groups = [];
            var g0 = parseGroup(sec.pre); if (g0) groups.push(g0);

            // Kendi satırında duran "Cevap: ..." bir sorunun ALTINDA mı, sonraki sorunun ÜSTÜNDE mi? Her soru için ayrı karar verilir.
            assignStandaloneAnswers(sec);

            var parsed = sec.blocks.map(function (b) {
                var ls = b.lines.slice(), ans = findAnswer(ls), tail = '';
                if (!ans && b.carryAns) ans = { idx: -1, raw: b.carryAns };
                var ex = extractOptions(ls, kind);
                if (!ex.opts && ans && ans.idx >= 0 && ans.raw !== undefined && !/^[A-Ea-e]$/.test(ans.raw)) {
                    // şıksız: "Cevap:" satırının devamı (boş satıra kadar) cevabın parçasıdır
                    var j = ans.idx + 1, extra = [];
                    while (j < ls.length && ls[j].trim() !== '') { extra.push(ls[j].trim()); j++; }
                    if (extra.length) { ans.raw = (ans.raw + ' ' + extra.join(' ')).trim(); ls = ls.slice(0, ans.idx + 1).concat(ls.slice(j)); ex = extractOptions(ls, kind); }
                }
                return { num: b.num, stem: ex.stem, opts: ex.opts, tail: ex.tail, ans: ans ? ans.raw : null, pre: b.pre, paren: b.paren, flagged: ex.flagged, optLines: ex.optLines };
            });

            // Öncül listesi yüzünden yanlış bölünmüş kısa, şıksız blokları sonraki şıklı bloğa birleştir
            var withOpts = parsed.filter(function (q) { return q.opts; }).length, merged = parsed;
            if ((kind === null || kind === 'multiple') && withOpts > 0 && withOpts / parsed.length >= 0.5 && withOpts < parsed.length) {
                merged = []; var runq = [];
                var premiseLike = function (q) { return !q.opts && !q.ans && !q.paren && !q.pre && q.stem.length <= 120 && q.stem.indexOf('\n') < 0 && !/[\?]|hangi|nedir/i.test(q.stem) && !TF_START.test(q.stem) && !TF_END.test(q.stem) && !BLANK_RE.test(q.stem); };
                parsed.forEach(function (q) {
                    if (!q.opts && premiseLike(q)) { runq.push(q); return; }
                    if (runq.length && q.opts) {
                        var stemAll = runq.map(function (r, i) { return (i === 0 ? '' : r.num + '. ') + r.stem; }).concat([q.num + '. ' + q.stem]).join('\n');
                        q = { num: runq[0].num, stem: stemAll, opts: q.opts, tail: q.tail, ans: q.ans, pre: q.pre, paren: q.paren, flagged: q.flagged, optLines: q.optLines };
                    } else { runq.forEach(function (r) { merged.push(r); }); }
                    runq = [];
                    merged.push(q);
                });
                runq.forEach(function (r) { merged.push(r); });
            }
            merged.forEach(function (q) { var g = parseGroup(q.tail); if (g) groups.push(g); });

            var qs = merged.map(function (q) {
                var stemText = q.stem.replace(/§K§/g, ''), type, opts = { A: '', B: '', C: '', D: '', E: '' };
                var ansRaw = q.ans, tfAns = null;
                if (q.opts) {
                    Object.keys(q.opts).forEach(function (k) { opts[k] = toSentenceCase(q.opts[k].replace(/\s*§K§/g, '').trim()); });
                    var filled = ['A', 'B', 'C', 'D', 'E'].filter(function (k) { return opts[k]; });
                    type = (filled.length === 2 && isTFWord(opts.A) && isTFWord(opts.B)) ? 'truefalse' : 'multiple';
                    if (type === 'truefalse') opts = { A: 'Doğru', B: 'Yanlış', C: '', D: '', E: '' };
                    // şık işareti (kalın/vurgu) ile cevap
                    if (!ansRaw && q.flagged && q.flagged.length === 1 && type === 'multiple') ansRaw = q.flagged[0];
                    // soru sonundaki "( C )" ya da başındaki "(C) 1." ile cevap
                    var em = stemText.match(/\(\s+([A-Ea-e])\s+\)\s*$/);
                    if (em && type === 'multiple') { if (!ansRaw) ansRaw = em[1]; stemText = stemText.slice(0, em.index).trim(); }
                    if (!ansRaw && q.pre && type === 'multiple' && /^[A-Ea-e]$/.test(q.pre)) ansRaw = q.pre;
                    if (!ansRaw && q.pre && type === 'truefalse') ansRaw = q.pre;
                } else {
                    var tf = (kind === 'gap' || kind === 'classic') ? { isTF: false, ans: null, stem: stemText } : detectTF(stemText, q.paren);
                    if (kind === 'truefalse' && !tf.isTF) tf = { isTF: true, ans: null, stem: stemText };
                    if (kind === 'truefalse' || tf.isTF) {
                        type = 'truefalse'; tfAns = tf.ans; stemText = tf.stem; opts = { A: 'Doğru', B: 'Yanlış', C: '', D: '', E: '' };
                        if (!ansRaw && q.pre) ansRaw = q.pre;
                        if (!ansRaw && tfAns) ansRaw = tfAns;
                    } else if (kind === 'gap' || (kind !== 'classic' && BLANK_RE.test(stemText) && !(/(?:bulunuz|hesaplay[ıi]n[ıi]z|a[çc][ıi]klay[ıi]n[ıi]z|g[öo]sterin[ıi]z|belirleyin[ıi]z)\W*$/i.test(stemText.trim()) && !/bo[şs]lu[ğg]/i.test(stemText)))) type = 'gap';
                    else type = 'classic';
                    report.noOptions++;
                }
                var st = buildStem(stemText);
                var root = toSentenceCase(st.root), para = toSentenceCase(st.paragraph);
                if (type === 'multiple') root = highlightNegatives(root);
                return { _n: q.num, _ans: ansRaw, paragraph: para, root: root, type: type, options: opts, correct: 'A', _answered: false };
            });

            qs.forEach(function (q) { q._sec = si; });
            groups.forEach(function (g) {
                if (!g.passage) return;
                qs.forEach(function (q) { if (q._n >= g.from && q._n <= g.to) q.paragraph = q.paragraph ? g.passage + '<br><br>' + q.paragraph : g.passage; });
            });
            var cnt = {}; qs.forEach(function (q) { cnt[q.type] = (cnt[q.type] || 0) + 1; });
            var dom = Object.keys(cnt).sort(function (a, b) { return cnt[b] - cnt[a]; })[0] || null;
            secMeta.push({ qs: qs, dom: dom, kind: kind });
            out = out.concat(qs);
        });

        // 5) Soru altı/yanı cevapları uygula
        out.forEach(function (q) {
            if (!q._ans) return;
            var r = normAnswer(q.type, q.options, q._ans);
            applyAnswer(q, r);
        });

        // 6) Cevap anahtarı koşularını bölümlerle eşle
        if (runs.length) {
            var plan = planKeys(secMeta, runs, out.length), flatIdx = 0;
            secMeta.forEach(function (sm, si) {
                sm.qs.forEach(function (q, qi) {
                    var raw = lookupKey(plan, si, qi, q._n, flatIdx);
                    flatIdx++;
                    if (q._answered || raw === undefined) return;
                    applyAnswer(q, normAnswer(q.type, q.options, raw));
                });
            });
        }

        out.forEach(function (q) {
            report.counts[q.type]++;
            if (q._answered) report.keyed++; else report.missing++;
            delete q._n; delete q._ans; delete q._answered; delete q._sec;
        });
        report.total = out.length;
        return { questions: out, report: report };
    }

    function applyAnswer(q, r) {
        if (!r) return;
        if (r.correct) { q.correct = r.correct; q._answered = true; }
        else if (r.text !== undefined) { q.options.A = r.text; q.correct = 'A'; q._answered = true; }
    }

    // Anahtar koşularını bölümlere dağıtır
    function planKeys(secMeta, runs, total) {
        var S = secMeta.length, R = runs.length, plan = { mode: 'none', assign: [], flat: null, union: null };
        var maps = runs.map(function (r) { var m = {}; r.entries.forEach(function (e) { if (m[e.n] === undefined) m[e.n] = e.v; }); return { map: m, arr: r.entries.map(function (e) { return e.v; }) }; });
        if (S === 1) {
            var u = {}; maps.forEach(function (m) { Object.keys(m.map).forEach(function (k) { if (u[k] === undefined) u[k] = m.map[k]; }); });
            var arrAll = [].concat.apply([], maps.map(function (m) { return m.arr; }));
            plan.mode = 'union'; plan.union = u; plan.arr = arrAll; return plan;
        }
        var restart = false;
        for (var i = 1; i < S; i++) { var a = secMeta[i - 1].qs, b = secMeta[i].qs; if (a.length && b.length && b[0]._n <= a[a.length - 1]._n) restart = true; }
        if (R === 1) {
            if (restart && maps[0].arr.length === total) { plan.mode = 'flat'; plan.flat = maps[0].arr; }
            else { plan.mode = 'single'; plan.single = maps[0]; }
            return plan;
        }
        plan.mode = 'pair';
        plan.counts = secMeta.map(function (x) { return x.qs.length; });
        plan.assign = new Array(S);
        if (R === S) { for (var s = 0; s < S; s++) plan.assign[s] = maps[s]; return plan; }
        var used = {}, ri;
        for (ri = 0; ri < R; ri++) {
            if (!runs[ri].hint) continue;
            for (var s2 = 0; s2 < S; s2++) { if (!plan.assign[s2] && secMeta[s2].dom === runs[ri].hint) { plan.assign[s2] = maps[ri]; used[ri] = true; break; } }
        }
        var rest = []; for (ri = 0; ri < R; ri++) if (!used[ri]) rest.push(maps[ri]);
        var any = plan.assign.some(Boolean);
        if (!any && maps.reduce(function (x, m) { return x + m.arr.length; }, 0) === total) { plan.mode = 'flat'; plan.flat = [].concat.apply([], maps.map(function (m) { return m.arr; })); return plan; }
        for (var s3 = 0; s3 < S && rest.length; s3++) { if (!plan.assign[s3]) plan.assign[s3] = rest.shift(); }
        return plan;
    }
    function lookupKey(plan, si, qi, num, flatIdx) {
        if (plan.mode === 'union') { if (plan.union[num] !== undefined) return plan.union[num]; return undefined; }
        if (plan.mode === 'flat') return plan.flat[flatIdx];
        if (plan.mode === 'single') return plan.single.map[num];
        if (plan.mode === 'pair') { var m = plan.assign[si]; if (!m) return undefined; if (m.map[num] !== undefined) return m.map[num]; if (m.arr.length === plan.counts[si]) return m.arr[qi]; return undefined; }
        return undefined;
    }

    return { parse: parse, reflowPlain: reflowPlain, normalize: normalize, toSentenceCase: toSentenceCase };
})();


/* ===== OSC DOSYADAN METİN ÇIKARMA: PDF (sütun + satır birleştirme) ve DOCX (otomatik numaralar dahil) ===== */
var OSCExtract = (function () {
    'use strict';

    /* ---------------- PDF ---------------- */
    var BLOCK_START = /^(?:(?:[Ss][Oo][Rr][Uu]\s*)?\d{1,3}\s*[\.\)\-:](?!\d)\s*(?![a-zçğıöşü\s])|\(\s*[A-Ea-eDdYy]?\s*\)\s*\d{1,3}\s*[\.\)\-:]|\(\s*[DdYy]?\s*\)\s*\S|\(?[A-Ea-e]\s?[\)\.]\s*\S|\(?(?:I{1,3}|IV|VI{0,3}|IX|X)[\.\)]\s|[•▪●◦■□○]|\d{1,3}\s*[-–—]\s*\d{1,3}\s*\.?\s*[Ss]oru|(?:[Dd]o[ğg]ru\s+)?(?:[Cc]evap|[Yy]an[ıi]t|CEVAP|YANIT)\s*[:：=]|(?:[Cc]evap|CEVAP)\s*(?:[Aa]nahtar|ANAHTAR)|(?:[Aa][çc][ıi]klama|[Çç][öo]z[üu]m)\s*[:：])/;
    // Büyük harfli kısa başlık satırı ("BOŞLUK DOLDURMA", "B. DOĞRU / YANLIŞ SORULARI") her zaman yeni bloktur
    function isBlockStart(t) {
        if (BLOCK_START.test(t)) return true;
        var letters = t.replace(/[^A-Za-zğüşıöçĞÜŞİÖÇ]/g, ''), up = t.replace(/[^A-ZÇĞİÖŞÜ]/g, '');
        return letters.length >= 4 && t.length <= 70 && up.length / letters.length >= 0.75 && !/[\?]$/.test(t);
    }
    var NOISE = /^(?:\d{1,3}|sayfa\s*\d+(?:\s*\/\s*\d+)?|[-–—]\s*\d+\s*[-–—]|di[ğg]er\s+sayfaya\s+ge[çc]iniz\.?)$/i;

    /* ================= PDF ŞEKİL / RESİM / TABLO BULUCU ================= */
    // pdf.js operatör listesinden sayfadaki resim, çizim ve tablo alanlarını bulur (pdf.js 2.x ve 4.x+/6.x biçimleri).
    function mulM(A, B) { return [A[0] * B[0] + A[2] * B[1], A[1] * B[0] + A[3] * B[1], A[0] * B[2] + A[2] * B[3], A[1] * B[2] + A[3] * B[3], A[0] * B[4] + A[2] * B[5] + A[4], A[1] * B[4] + A[3] * B[5] + A[5]]; }
    function apM(m, x, y) { return [m[0] * x + m[2] * y + m[4], m[1] * x + m[3] * y + m[5]]; }
    function isWhiteColor(args) {
        if (!args) return false;
        var a = args[0];
        if (typeof a === 'string') { var h = a.replace('#', '').toLowerCase(); return h === 'ffffff' || h === 'fff'; }
        if (args.length >= 3 && typeof args[0] === 'number') { var mx = Math.max(args[0], args[1], args[2]); var sc = mx <= 1 ? 1 : 255; return args[0] >= 0.97 * sc && args[1] >= 0.97 * sc && args[2] >= 0.97 * sc; }
        return false;
    }

    function analyzeOps(ol, OPS) {
        var fn = ol.fnArray, ar = ol.argsArray, ctm = [1, 0, 0, 1, 0, 0], stack = [], fillWhite = false, strokeWhite = false;
        var images = [], paths = [], pending = null, annot = 0;
        var PAINT = {}; ['stroke', 'closeStroke', 'fill', 'eoFill', 'fillStroke', 'eoFillStroke', 'closeFillStroke', 'closeEOFillStroke', 'endPath'].forEach(function (k) { if (OPS[k] !== undefined) PAINT[OPS[k]] = k; });
        var IMG = {}; ['paintImageXObject', 'paintInlineImageXObject', 'paintImageMaskXObject', 'paintJpegXObject', 'paintImageXObjectRepeat', 'paintInlineImageXObjectGroup', 'paintImageMaskXObjectGroup', 'paintSolidColorImageMask'].forEach(function (k) { if (OPS[k] !== undefined) IMG[OPS[k]] = k; });

        function commit(p, paintName) {
            if (!p || !paintName || paintName === 'endPath') return;
            var filled = /fill/i.test(paintName), stroked = /stroke/i.test(paintName);
            if ((!filled || fillWhite) && (!stroked || strokeWhite)) return;          // görünmez (beyaz) boyama
            p.filled = filled && !fillWhite; p.stroked = stroked && !strokeWhite;
            paths.push(p);
        }
        // Yol geometrisini sayfa koordinatlarına çevirip istatistik çıkarır
        function buildPath(points, segs, curves, subpaths, minMax, st) {
            var x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
            if (points.length) points.forEach(function (q) { var t = apM(ctm, q[0], q[1]); if (t[0] < x0) x0 = t[0]; if (t[0] > x1) x1 = t[0]; if (t[1] < y0) y0 = t[1]; if (t[1] > y1) y1 = t[1]; });
            else if (minMax && isFinite(minMax[0])) { [[minMax[0], minMax[1]], [minMax[2], minMax[1]], [minMax[0], minMax[3]], [minMax[2], minMax[3]]].forEach(function (q) { var t = apM(ctm, q[0], q[1]); if (t[0] < x0) x0 = t[0]; if (t[0] > x1) x1 = t[0]; if (t[1] < y0) y0 = t[1]; if (t[1] > y1) y1 = t[1]; }); }
            if (!isFinite(x0)) return null;
            var hs = [], vs = [], diag = 0;
            segs.forEach(function (s) {
                var a = apM(ctm, s[0], s[1]), b = apM(ctm, s[2], s[3]);
                if (Math.abs(a[1] - b[1]) < 0.6) hs.push({ y: a[1], a: Math.min(a[0], b[0]), b: Math.max(a[0], b[0]) });
                else if (Math.abs(a[0] - b[0]) < 0.6) vs.push({ x: a[0], a: Math.min(a[1], b[1]), b: Math.max(a[1], b[1]) });
                else diag++;
            });
            var sb = null;
            if (st && st.length > 1 && st.length <= 16) sb = st.map(function (a, k) { var e = k + 1 < st.length ? st[k + 1] : points.length, b = { x0: Infinity, y0: Infinity, x1: -Infinity, y1: -Infinity }; points.slice(a, e).forEach(function (q) { var t = apM(ctm, q[0], q[1]); b.x0 = Math.min(b.x0, t[0]); b.x1 = Math.max(b.x1, t[0]); b.y0 = Math.min(b.y0, t[1]); b.y1 = Math.max(b.y1, t[1]); }); return b; });
            return { x0: x0, y0: y0, x1: x1, y1: y1, hs: hs, vs: vs, diag: diag, curves: curves, nseg: segs.length, subpaths: subpaths, sb: sb };
        }
        function parseNew(data, minMax) {          // pdf.js 4+/6: [DrawOPS...] (0 moveTo, 1 lineTo, 2 curveTo, 3 quadratic, 4 closePath)
            var pts = [], segs = [], curves = 0, sub = 0, cx = 0, cy = 0, sx = 0, sy = 0, i = 0, st = [];
            if (!data) return buildPath([], [], 0, 0, minMax);
            while (i < data.length) {
                var op = data[i++];
                if (op === 0) { cx = data[i++]; cy = data[i++]; sx = cx; sy = cy; sub++; st.push(pts.length); pts.push([cx, cy]); }
                else if (op === 1) { var nx = data[i++], ny = data[i++]; segs.push([cx, cy, nx, ny]); cx = nx; cy = ny; pts.push([cx, cy]); }
                else if (op === 2) { for (var k = 0; k < 3; k++) pts.push([data[i + 2 * k], data[i + 2 * k + 1]]); cx = data[i + 4]; cy = data[i + 5]; i += 6; curves++; }
                else if (op === 3) { pts.push([data[i], data[i + 1]], [data[i + 2], data[i + 3]]); cx = data[i + 2]; cy = data[i + 3]; i += 4; curves++; }
                else if (op === 4) { if (cx !== sx || cy !== sy) segs.push([cx, cy, sx, sy]); cx = sx; cy = sy; }
                else break;
            }
            return buildPath(pts, segs, curves, sub, minMax, st);
        }
        function parseOld(ops, c, minMax) {        // pdf.js 2.x/3.x: OPS kodları + düz koordinat dizisi
            var pts = [], segs = [], curves = 0, sub = 0, cx = 0, cy = 0, sx = 0, sy = 0, j = 0, st = [];
            for (var i = 0; i < ops.length; i++) {
                var op = ops[i];
                if (op === OPS.rectangle) {
                    var x = c[j++], y = c[j++], w = c[j++], h = c[j++];
                    segs.push([x, y, x + w, y], [x + w, y, x + w, y + h], [x + w, y + h, x, y + h], [x, y + h, x, y]);
                    st.push(pts.length); pts.push([x, y], [x + w, y + h]); sub++; cx = sx = x; cy = sy = y;
                } else if (op === OPS.moveTo) { cx = sx = c[j++]; cy = sy = c[j++]; sub++; st.push(pts.length); pts.push([cx, cy]); }
                else if (op === OPS.lineTo) { var nx = c[j++], ny = c[j++]; segs.push([cx, cy, nx, ny]); cx = nx; cy = ny; pts.push([cx, cy]); }
                else if (op === OPS.curveTo) { pts.push([c[j], c[j + 1]], [c[j + 2], c[j + 3]], [c[j + 4], c[j + 5]]); cx = c[j + 4]; cy = c[j + 5]; j += 6; curves++; }
                else if (op === OPS.curveTo2 || op === OPS.curveTo3) { pts.push([c[j], c[j + 1]], [c[j + 2], c[j + 3]]); cx = c[j + 2]; cy = c[j + 3]; j += 4; curves++; }
                else if (op === OPS.closePath) { if (cx !== sx || cy !== sy) segs.push([cx, cy, sx, sy]); cx = sx; cy = sy; }
            }
            return buildPath(pts, segs, curves, sub, minMax, st);
        }

        for (var i = 0; i < fn.length; i++) {
            var f = fn[i], a = ar[i];
            if (f === OPS.beginAnnotation) { annot++; continue; }
            if (f === OPS.endAnnotation) { annot = Math.max(0, annot - 1); continue; }
            if (annot) continue;
            if (pending && PAINT[f] !== undefined) { commit(pending, PAINT[f]); pending = null; continue; }
            if (pending && f !== OPS.constructPath) pending = null;
            if (f === OPS.save) stack.push([ctm, fillWhite, strokeWhite]);
            else if (f === OPS.restore) { var st = stack.pop(); if (st) { ctm = st[0]; fillWhite = st[1]; strokeWhite = st[2]; } }
            else if (f === OPS.transform) ctm = mulM(ctm, a);
            else if (f === OPS.paintFormXObjectBegin) { stack.push([ctm, fillWhite, strokeWhite]); if (a && a[0] && a[0].length === 6) ctm = mulM(ctm, a[0]); }
            else if (f === OPS.paintFormXObjectEnd) { var s2 = stack.pop(); if (s2) { ctm = s2[0]; fillWhite = s2[1]; strokeWhite = s2[2]; } }
            else if (f === OPS.setFillRGBColor) fillWhite = isWhiteColor(a);
            else if (f === OPS.setStrokeRGBColor) strokeWhite = isWhiteColor(a);
            else if (f === OPS.setFillGray) fillWhite = a && a[0] >= 0.97;
            else if (f === OPS.setStrokeGray) strokeWhite = a && a[0] >= 0.97;
            else if (IMG[f] !== undefined) {
                var c4 = [[0, 0], [1, 0], [0, 1], [1, 1]].map(function (q) { return apM(ctm, q[0], q[1]); });
                var xs = c4.map(function (q) { return q[0]; }), ys = c4.map(function (q) { return q[1]; });
                images.push({ x0: Math.min.apply(null, xs), y0: Math.min.apply(null, ys), x1: Math.max.apply(null, xs), y1: Math.max.apply(null, ys), img: true });
            } else if (f === OPS.constructPath) {
                if (typeof a[0] === 'number') {                                  // yeni biçim: boyama türü ilk argümanda
                    var p = parseNew(a[1] && a[1][0], a[2]);
                    var pn = PAINT[a[0]];
                    if (p) commit(p, pn);
                } else if (Array.isArray(a[0]) || (a[0] && a[0].length !== undefined)) {   // eski biçim: boyama sonraki komutta
                    pending = parseOld(a[0], a[1] || [], a[2]);
                }
            }
        }
        return { images: images, paths: paths };
    }

    // pdf.js metin öğesinin sayfa koordinatlarında merkezi
    function textBoxes(items) {
        var out = [];
        items.forEach(function (it) {
            if (!it || typeof it.str !== 'string' || !it.str.trim()) return;
            var h = Math.abs(it.height || (it.transform && it.transform[3]) || 10) || 10, x = it.transform[4], y = it.transform[5], w = it.width || 0;
            out.push({ s: it.str, cx: x + w / 2, cy: y + h * 0.35, x0: x, x1: x + w, y0: y, y1: y + h, n: it.str.replace(/\s/g, '').length });
        });
        return out;
    }
    function inside(b, x, y, pad) { pad = pad || 0; return x >= b.x0 - pad && x <= b.x1 + pad && y >= b.y0 - pad && y <= b.y1 + pad; }
    function distinct(vals, tol) { vals = vals.slice().sort(function (a, b) { return a - b; }); var n = 0, last = null; vals.forEach(function (v) { if (last === null || v - last > tol) { n++; last = v; } }); return n; }

    // PDF yazı öğelerini temizler:
    //  - döndürülmüş yazılar (kenardaki dikey filigran) ve dev puntolu filigranlar ("no35 matematik" 94pt) atılır
    //  - süs için aynı yere iki kez basılan yazılar (kontur/gölge efekti: "ssooruru") teke indirilir
    //  - matematik kalın/italik rakam ve harfleri (𝟏, 𝟐, 𝒂) normal karaktere çevrilir
    function cleanItems(items) {
        var sizes = [], out = [];
        items.forEach(function (it) {
            if (!it || typeof it.str !== 'string' || !it.str.trim() || !it.transform) return;
            var t = it.transform, sz = Math.hypot(t[0], t[1]) || Math.abs(t[3]) || 10;
            for (var k = 0; k < it.str.replace(/\s/g, '').length; k++) sizes.push(sz);
        });
        sizes.sort(function (a, b) { return a - b; });
        var med = sizes.length ? sizes[Math.floor(sizes.length / 2)] : 10;
        items.forEach(function (it) {
            if (!it || typeof it.str !== 'string' || !it.transform) return;
            if (!it.str.trim()) return;
            var t = it.transform, sz = Math.hypot(t[0], t[1]) || Math.abs(t[3]) || 10;
            if (Math.abs(t[1]) > sz * 0.15 || Math.abs(t[2]) > sz * 0.15) return;          // döndürülmüş yazı
            if (sz >= Math.max(36, med * 3)) return;                                      // dev filigran
            var s = it.str;
            if (/[\uD835]/.test(s)) s = s.replace(/[\uD835][\uDC00-\uDFFF]/g, function (c) { return c.normalize('NFKC'); });
            var x = t[4], y = t[5];
            if (out.some(function (o) { return o.str === s && Math.abs(o.transform[4] - x) < 1.5 && Math.abs(o.transform[5] - y) < 1.5; })) return;   // çift basım
            out.push({ str: s, transform: t, width: it.width, height: it.height, fontName: it.fontName });
        });
        return out;
    }

    // Kısa dikey çizgiler (mutlak değer |a|) -> "|" karakteri
    function findBars(paths, items) {
        var tb = textBoxes(items), out = [];
        paths.forEach(function (p) {
            var w = p.x1 - p.x0, h = p.y1 - p.y0;
            if (!(w < 1.6 && h >= 6 && h <= 26) || p.curves || p.diag) return;
            // tablo / çerçeve parçası değil: uçlarına değen yatay çizgi yok
            if (paths.some(function (q) { return q !== p && (q.y1 - q.y0) < 2.5 && (q.x1 - q.x0) > 3 && q.x0 - 2 <= p.x0 && q.x1 + 2 >= p.x0 && (Math.abs(q.y0 - p.y0) < 2 || Math.abs(q.y0 - p.y1) < 2); })) return;
            var near = tb.filter(function (t) { return t.cy > p.y0 - 2 && t.cy < p.y1 + 2 && (Math.abs(t.x0 - p.x1) < 14 || Math.abs(p.x0 - t.x1) < 14); });
            if (!near.length) return;
            var base = near.map(function (t) { return t.y0; }).sort(function (a, b) { return a - b; })[0];
            out.push({ str: '|', transform: [1, 0, 0, 1, p.x0 - 0.5, base], width: 1.5, height: near[0].y1 - near[0].y0 });
        });
        return out;
    }

    // Üst üste yazılmış kesirleri (pay / çizgi / payda) ve devirli ondalık üst çizgisini (0,2̅) bulur.
    // Döner: [{drop:[öğe dizinleri], add:[yeni yazı öğeleri], box:{x0,y0,x1,y1}}]
    function findFractions(paths, items) {
        var tb = [], used = {}, out = [];
        items.forEach(function (it, i) {
            if (!it || typeof it.str !== 'string' || !it.str.trim() || !it.transform) return;
            var h = Math.abs(it.height || it.transform[3] || 10) || 10, x = it.transform[4], y = it.transform[5], w = it.width || 0;
            tb.push({ i: i, s: it.str, x0: x, x1: x + w, y0: y, y1: y + h, cx: x + w / 2, cy: y + h * 0.35 });
        });
        function hline(p) { return (p.y1 - p.y0) < 2.2 && !p.curves && !p.diag && (p.stroked || p.filled); }
        paths.forEach(function (p) {
            var w = p.x1 - p.x0;
            if (!hline(p) || w < 4 || w > 90) return;
            var y = (p.y0 + p.y1) / 2;
            // tablo kenarlığı değil: uçlarına dikey çizgi değmiyor, aynı hizada devam eden yatay çizgi yok
            var tableLike = paths.some(function (q) {
                if (q === p) return false;
                var qw = q.x1 - q.x0, qh = q.y1 - q.y0;
                if (qw < 2.5 && qh > 4 && q.y0 - 2 <= y && q.y1 + 2 >= y && (Math.abs(q.x0 - p.x0) < 2.5 || Math.abs(q.x0 - p.x1) < 2.5)) return true;
                if (hline(q) && Math.abs((q.y0 + q.y1) / 2 - y) < 1 && (Math.abs(q.x0 - p.x1) < 2.5 || Math.abs(p.x0 - q.x1) < 2.5)) return true;
                return false;
            });
            if (tableLike) return;
            function grab(isUp) {
                return tb.filter(function (t) {
                    if (used[t.i]) return false;
                    var hgt = t.y1 - t.y0, d = isUp ? t.y0 - y : y - t.y1;
                    return t.x0 >= p.x0 - 4 && t.x1 <= p.x1 + 4 && d >= -hgt * 0.35 && d <= Math.max(6, hgt * 0.9) && t.s.trim().length <= 14;
                }).sort(function (a, b) { return a.x0 - b.x0; });
            }
            var up = grab(true), dn = grab(false);
            // altı çizili sütun başlığı ("A  B  C" şık tablosu) kesir değildir
            if (up.length === 1 && /^[A-EIV]{1,3}$/.test(up[0].s.trim()) && paths.some(function (q) { return q !== p && hline(q) && Math.abs((q.y0 + q.y1) / 2 - y) < 1.5 && q.x1 - q.x0 < 90 && Math.abs(q.x0 - p.x0) > 10; })) return;
            function wrap(s) { s = s.replace(/\s+/g, ' ').trim(); return /[\s+\-−×÷·*\/]/.test(s) && s.length > 1 ? '(' + s + ')' : s; }
            var all = up.concat(dn), box = { x0: p.x0, x1: p.x1, y0: y, y1: y };
            all.forEach(function (t) { box.x0 = Math.min(box.x0, t.x0); box.x1 = Math.max(box.x1, t.x1); box.y0 = Math.min(box.y0, t.y0 - 2); box.y1 = Math.max(box.y1, t.y1); });
            if (up.length && dn.length) {
                all.forEach(function (t) { used[t.i] = 1; });
                out.push({ drop: all.map(function (t) { return t.i; }), box: box, bar: p,
                    add: [{ str: wrap(up.map(function (t) { return t.s; }).join(' ')) + '/' + wrap(dn.map(function (t) { return t.s; }).join(' ')), transform: [1, 0, 0, 1, p.x0, null], width: w, height: 9, _barY: y }] });
            } else if (!up.length) {
                // devirli ondalık: rakamların hemen üstündeki kısa çizgi -> çizginin altına düşen rakamlara üst çizgi (U+0305)
                var under = tb.filter(function (t) {
                    if (used[t.i]) return false;
                    var hgt = t.y1 - t.y0, d = y - t.y1;
                    return t.x1 > p.x0 && t.x0 < p.x1 && d > -hgt * 0.35 && d < 3 && /\d/.test(t.s);
                });
                if (!under.length) return;
                var add = [], hit = false;
                under.forEach(function (t) {
                    var it = items[t.i], s = it.str, cw = (t.x1 - t.x0) / Math.max(1, s.length), ns = '';
                    for (var k = 0; k < s.length; k++) {
                        var cxk = t.x0 + cw * (k + 0.5), ch = s.charAt(k);
                        ns += ch; if (/\d/.test(ch) && cxk >= p.x0 - 1 && cxk <= p.x1 + 1) { ns += '̅'; hit = true; }
                    }
                    add.push({ str: ns, transform: it.transform, width: it.width, height: it.height });
                });
                if (!hit) return;
                under.forEach(function (t) { used[t.i] = 1; });
                out.push({ drop: under.map(function (t) { return t.i; }), box: box, bar: p, add: add });
            }
        });
        return out;
    }

    // Döner: [{x0,y0,x1,y1,kind:'img'|'blank'}]
    function findFigures(ol, items, W, H, OPS) {
        if (!ol || !ol.fnArray || !OPS) return [];
        var an = analyzeOps(ol, OPS), pageA = W * H;
        var fracs = findFractions(an.paths, items), fracDrop = {};
        fracs.forEach(function (f) { f.drop.forEach(function (i) { fracDrop[i] = 1; }); });
        items = items.map(function (it, i) { return fracDrop[i] ? null : it; });
        var tb = textBoxes(items);
        function textIn(b) {
            var n = 0, q = 0, o = 0;
            tb.forEach(function (t) {
                if (!inside(b, t.cx, t.cy, 0.5)) return;
                n += t.n;
                if (/^\s*(?:soru\s*)?\d{1,3}\s*[\.\)]\s*\S/i.test(t.s) || /^\s*(?:soru\s*)?\d{1,3}\s*[\.\)]\s*$/i.test(t.s)) q++;
                if (/^\s*\(?[A-E]\s?\)/.test(t.s)) o++;
            });
            return { n: n, q: q, o: o };
        }
        var els = [];
        an.images.forEach(function (im) {
            var w = im.x1 - im.x0, h = im.y1 - im.y0;
            if (w < 6 || h < 6) return;
            if (w * h > pageA * 0.45) return;                                      // tam sayfa arka plan / kenarlık
            els.push({ x0: im.x0, y0: im.y0, x1: im.x1, y1: im.y1, img: 1, hs: [], vs: [], diag: 0, curves: 0, nseg: 0, filled: false });
        });
        var fracBars = fracs.map(function (f) { return f.bar; });
        an.paths.forEach(function (p) {
            var w = p.x1 - p.x0, h = p.y1 - p.y0;
            if (fracBars.indexOf(p) >= 0) return;
            if (w * h > pageA * 0.6) return;
            if (p.x0 < -4 || p.y0 < -4 || p.x1 > W + 4 || p.y1 > H + 4) return;            // sayfa dışına taşan süs çizgileri
            if (h < 2.5 && w > W * 0.9) return;                                    // sayfa boyu yatay çizgi
            if (w < 2.5 && h > H * 0.7) return;                                    // sütun ayırıcı
            // ince dolu dikdörtgen = çizgi (Word tablo kenarlıkları böyle çizilir)
            if (p.filled && !p.stroked && p.nseg <= 5 && p.curves === 0 && Math.min(w, h) < 2.5) {
                if (w >= h) p.hs = [{ y: (p.y0 + p.y1) / 2, a: p.x0, b: p.x1 }], p.vs = []; else p.vs = [{ x: (p.x0 + p.x1) / 2, a: p.y0, b: p.y1 }], p.hs = [];
                p.thin = true;
            }
            // tek yolda çizilmiş birkaç küçük kutu ("(–6) □ (–2) □ (–8)") -> her biri ayrı boşluk
            if (p.sb && p.sb.length > 1 && p.curves === 0 && p.diag === 0 && p.sb.every(function (b) { var bw = b.x1 - b.x0, bh = b.y1 - b.y0; return bw >= 4 && bh >= 4 && bw <= 20 && bh <= 20; })) {
                var bos = p.sb.filter(function (b) { return textIn(b).n === 0; });
                if (bos.length === p.sb.length) { bos.forEach(function (b) { els.push({ x0: b.x0, y0: b.y0, x1: b.x1, y1: b.y1, hs: [], vs: [], diag: 0, curves: 0, nseg: 4, filled: false, blankCand: true, box: true }); }); return; }
            }
            var box = p.subpaths <= 1 && !p.thin && p.diag === 0 && p.curves <= 4 && p.nseg <= 8 && w > 5 && h > 5 && (p.curves === 0 || p.curves === 4);
            p.box = box;
            if (box) {
                var ti = textIn(p);
                if (ti.n >= 1 && (ti.n > 3 || w > 60 || h > 40)) return;          // çerçeve / vurgulu kutu (içinde yazı var) -> şekil değil
                if (ti.n >= 1) { p.etiketli = true; els.push(p); return; }        // şema kutusu (içinde 'A', '−4' gibi kısa etiket)
                if (w <= 46 && h <= 40 && p.curves === 0) { p.blankCand = true; }
                else if (w > 160 || h > 160) return;                               // büyük boş kutu = yazı alanı
            }
            els.push(p);
        });

        // Büyük parantez gibi çizilmiş formül parçası: aynı satırdaki bitişik rakam, işaret ve kesirleri de şekle kat
        // ("−3/4 − 2/3 + (−1/6) =" ifadesinin yarısı yazı, yarısı resim olmasın; tamamı tek resim olsun)
        var MATHY = /^[\s0-9.,+\-−–×÷·*\/=:()\[\]{}|<>≤≥²³√π%°'\u0305a-zA-Z]{1,16}$/;
        function growFormula(fig) {
            var boxes = tb.filter(function (t) { return MATHY.test(t.s) && !/[A-Za-zÇĞİÖŞÜçğıöşü]{2,}/.test(t.s) && !/^\s*\(?[A-E]\s?\)/.test(t.s) && !/^\s*\d{1,3}\s*[\.\)]\s*$/.test(t.s); })
                .map(function (t) { return { x0: t.x0, x1: t.x1, y0: t.y0 - (t.y1 - t.y0) * 0.2, y1: t.y1, cy: t.cy }; })
                .concat(fracs.map(function (f) { return { x0: f.box.x0, x1: f.box.x1, y0: f.box.y0, y1: f.box.y1, cy: (f.box.y0 + f.box.y1) / 2 }; }));
            var grew = true, n = 0;
            while (grew && n++ < 40) {
                grew = false;
                boxes.forEach(function (b) {
                    if (b.done) return;
                    if (b.cy < fig.y0 - 3 || b.cy > fig.y1 + 3) return;
                    var gap = b.x0 >= fig.x1 ? b.x0 - fig.x1 : (b.x1 <= fig.x0 ? fig.x0 - b.x1 : 0);
                    if (gap > 14) return;
                    b.done = true; grew = true;
                    fig.x0 = Math.min(fig.x0, b.x0); fig.x1 = Math.max(fig.x1, b.x1); fig.y0 = Math.min(fig.y0, b.y0); fig.y1 = Math.max(fig.y1, b.y1);
                });
            }
        }

        // Grafik eksen yazıları, sayı doğrusu rakamları gibi şeklin hemen altındaki/içinden taşan kısa etiketleri şekle dahil et
        function absorbLabels(fig) {
            var rowsT = [];
            tb.filter(function (t) { return t.x1 > fig.x0 - 15 && t.x0 < fig.x1 + 15; }).sort(function (a, b) { return b.cy - a.cy; }).forEach(function (t) {
                var r = rowsT.length ? rowsT[rowsT.length - 1] : null;
                if (r && Math.abs(r.cy - t.cy) < 3) r.items.push(t); else rowsT.push({ cy: t.cy, items: [t] });
            });
            rowsT.forEach(function (r) {
                var line = r.items.map(function (t) { return t.s; }).join(' ').trim();
                var x0 = Math.min.apply(null, r.items.map(function (t) { return t.x0; })), x1 = Math.max.apply(null, r.items.map(function (t) { return t.x1; }));
                var above = r.cy > fig.y1 - 1;
                // üstte yalnızca çok kısa etiketler (köşe harfi "C", eksen adı "Yol (km)"); soru metni asla
                if (above && (r.cy > fig.y1 + 16 || line.length > 16 || r.items.some(function (t) { return t.s.replace(/\s/g, '').length > 12; }))) return;
                if (r.cy < fig.y0 - 16) return;
                var rightLimit = line.length <= 16 && !above ? fig.x1 + 80 : fig.x1 + 15;   // x ekseninin ucundaki kısa ad
                if (x0 < fig.x0 - 18 || x1 > rightLimit) return;
                if (/^\s*(?:soru\s*)?\d{1,3}\s*[\.\)]\s*$/i.test(line)) return;            // tek başına soru numarası ("5.") asla etiket değildir
                if (!line || line.length > 60 || /\?\s*$/.test(line) || /^\s*(?:soru\s*)?\d{1,3}\s*[\.\)]\s*\S/i.test(line) && /[a-zçğıöşü]{3}/i.test(line) || /^\s*\(?[A-Ea-e]\s?\)/.test(line)) return;
                if (r.items.some(function (t) { return t.s.replace(/\s/g, '').length > 25; })) return;
                r.items.forEach(function (t) { fig.x0 = Math.min(fig.x0, t.x0); fig.x1 = Math.max(fig.x1, t.x1); fig.y0 = Math.min(fig.y0, t.y0 - 2); fig.y1 = Math.max(fig.y1, t.y1); });
            });
        }

        var excluded = {}, figs = [];
        for (var pass = 0; pass < 3; pass++) {
            var act = els.map(function (e, i) { return i; }).filter(function (i) { return !excluded[i]; });
            // birbirine değen/yakın öğeleri kümele
            var parent = {}; act.forEach(function (i) { parent[i] = i; });
            function find(i) { while (parent[i] !== i) { parent[i] = parent[parent[i]]; i = parent[i]; } return i; }
            var G = 5, GS = 16;
            for (var x = 0; x < act.length; x++) for (var y = x + 1; y < act.length; y++) {
                var a = els[act[x]], b = els[act[y]], join = false;
                if (!!a.blankCand !== !!b.blankCand) { var o2 = a.blankCand ? b : a; if (o2.curves > 0 || (o2.x1 - o2.x0) * (o2.y1 - o2.y0) > 2500) continue; }
                if (a.x0 - G <= b.x1 && b.x0 - G <= a.x1 && a.y0 - G <= b.y1 && b.y0 - G <= a.y1) join = true;
                else {
                    // yan yana duran parçalar (ör. üç üçgen bir daire): dikeyde örtüşüyor, aradaki boşluk küçük ve arada yazı yok
                    var ov = Math.min(a.y1, b.y1) - Math.max(a.y0, b.y0), minH = Math.min(a.y1 - a.y0, b.y1 - b.y0);
                    var L = a.x1 <= b.x0 ? a : b, R = L === a ? b : a, gap = R.x0 - L.x1;
                    if (ov > minH * 0.5 && gap >= 0 && gap <= GS && !(a.img && b.img)) {
                        var gy0 = Math.max(a.y0, b.y0), gy1 = Math.min(a.y1, b.y1);
                        join = !tb.some(function (t) { return t.cx > L.x1 - 1 && t.cx < R.x0 + 1 && t.cy > gy0 && t.cy < gy1; });
                    }
                }
                if (join) { var ra = find(act[x]), rb = find(act[y]); if (ra !== rb) parent[ra] = rb; }
            }
            var groups = {}; act.forEach(function (i) { var r = find(i); (groups[r] = groups[r] || []).push(i); });
            figs = []; var changed = false;
            Object.keys(groups).forEach(function (r) {
                var g = groups[r], bb = { x0: Infinity, y0: Infinity, x1: -Infinity, y1: -Infinity }, hs = [], vs = [], diag = 0, curves = 0, imgs = 0, nseg = 0, filled = 0, boxes = 0, blankC = 0;
                g.forEach(function (i) {
                    var e = els[i];
                    bb.x0 = Math.min(bb.x0, e.x0); bb.y0 = Math.min(bb.y0, e.y0); bb.x1 = Math.max(bb.x1, e.x1); bb.y1 = Math.max(bb.y1, e.y1);
                    hs = hs.concat(e.hs || []); vs = vs.concat(e.vs || []); diag += e.diag || 0; curves += e.curves || 0; nseg += e.nseg || 0;
                    if (e.img) imgs++; if (e.filled && !e.thin) filled++; if (e.box) boxes++; if (e.blankCand) blankC++;
                });
                var w = bb.x1 - bb.x0, h = bb.y1 - bb.y0;
                // küçük boş kutu (dolgu + kenar çizgisi aynı kutuyu iki kez çizebilir): "5 + □ = 9" -> boşluk
                if (blankC === g.length && w >= 5 && h >= 5 && w <= 46 && h <= 40) { figs.push({ x0: bb.x0, y0: bb.y0, x1: bb.x1, y1: bb.y1, kind: 'blank' }); return; }
                if (blankC === g.length && g.length > 1 && h <= 40) { var tek = []; g.forEach(function (i) { var e = els[i]; if (e.x1 - e.x0 >= 5 && e.y1 - e.y0 >= 5 && e.x1 - e.x0 <= 46 && !tek.some(function (q) { return Math.abs(q.x0 - e.x0) < 2 && Math.abs(q.y0 - e.y0) < 2; })) tek.push({ x0: e.x0, y0: e.y0, x1: e.x1, y1: e.y1, kind: 'blank' }); }); if (tek.length) { figs = figs.concat(tek); return; } }
                if (w < 14 || h < 10) return;
                // soru numarası rozeti ("soru ①", daire içinde numara): şekil değil, içindeki yazı numaradır
                var inTxt = tb.filter(function (t) { return inside(bb, t.cx, t.cy, 0.5); }).map(function (t) { return t.s; }).join('').replace(/\s+/g, '');
                if (!imgs || w * h < 6000) { if (/^(?:soru|s\.)?\d{1,3}[\.\)]?$/i.test(inTxt) || /^soru$/i.test(inTxt)) return; }
                var longH = hs.filter(function (l) { return l.b - l.a >= w * 0.5; }).map(function (l) { return l.y; });
                var longV = vs.filter(function (l) { return l.b - l.a >= h * 0.5; }).map(function (l) { return l.x; });
                var nH = distinct(longH, 1.5), nV = distinct(longV, 1.5);
                var grid = (nH >= 3 && nV >= 2) || (nH >= 2 && nV >= 3);
                var ti = textIn(bb);
                var layout = ti.q >= 1 && ti.n > 30 || ti.o >= 2 || ti.n > (grid ? 900 : (imgs ? 160 : 220)) || (!imgs && !grid && ti.n >= 25 && w > 120 && (function () { var ka = 0; tb.forEach(function (t) { if (inside(bb, t.cx, t.cy, 0.5)) ka += (t.x1 - t.x0) * Math.max(4, t.y1 - t.y0); }); return ka / Math.max(1, w * h) > 0.12; })());   // yazı kutusu (kesikli çerçeveli çözüm/cevap kutusu)
                if (layout) {
                    // Soruları/çok yazıyı içine alan küme bir şekil değil, sayfa düzenidir (soru kutuları, ızgara).
                    // Düz çizgileri at; içindeki resim ve çizimleri tek tek yeniden değerlendir.
                    var anyEx = false;
                    g.forEach(function (i) { var e = els[i]; if (!e.img && (e.curves || 0) < 2 && (e.diag || 0) === 0) { excluded[i] = 1; anyEx = true; } });
                    if (!anyEx) g.forEach(function (i) { excluded[i] = 1; });    // yazının arkasındaki arka plan resmi/süsü
                    changed = true;
                    return;
                }
                if (w < 20 && h < 14) return;
                var meaningful = imgs > 0 || grid || curves >= 1 || diag >= 1 || nseg >= 6 || filled >= 1;
                if (!meaningful) return;
                var fig = { x0: bb.x0, y0: bb.y0, x1: bb.x1, y1: bb.y1, kind: 'img', table: grid && !imgs };
                if (!imgs && !grid && h <= 50) growFormula(fig);
                if (!imgs) absorbLabels(fig);
                figs.push(fig);
            });
            if (!changed) break;
        }
        // Parçalara bölünmüş tek şekli birleştir (sayı doğrusu + yayları, şema parçaları): üst üste/yakın, arada yalnız kısa etiketler
        for (var mg = true; mg;) {
            mg = false;
            for (var fa = 0; fa < figs.length && !mg; fa++) for (var fb = fa + 1; fb < figs.length && !mg; fb++) {
                var A1 = figs[fa], B1 = figs[fb]; if (A1.kind !== 'img' || B1.kind !== 'img') continue;
                var hov = Math.min(A1.x1, B1.x1) - Math.max(A1.x0, B1.x0), vgap = Math.max(A1.y0, B1.y0) - Math.min(A1.y1, B1.y1);
                if (hov < Math.min(A1.x1 - A1.x0, B1.x1 - B1.x0) * 0.4 || vgap > 24) continue;
                var U = { x0: Math.min(A1.x0, B1.x0), y0: Math.min(A1.y0, B1.y0), x1: Math.max(A1.x1, B1.x1), y1: Math.max(A1.y1, B1.y1) };
                var uzun = tb.some(function (t) { return inside(U, t.cx, t.cy, 0) && !inside(A1, t.cx, t.cy, 1) && !inside(B1, t.cx, t.cy, 1) && t.n > 4; });
                if (uzun) continue;
                A1.x0 = U.x0; A1.y0 = U.y0; A1.x1 = U.x1; A1.y1 = U.y1; A1.table = A1.table && B1.table; figs.splice(fb, 1); mg = true;
            }
        }
        // Kesim kutusu: 3pt pay bırak ama şekle ait olmayan komşu yazının içine girme
        figs.forEach(function (f) {
            if (f.kind !== 'img') return;
            var c = { x0: f.x0 - 3, y0: f.y0 - 3, x1: f.x1 + 3, y1: f.y1 + 3 };
            tb.forEach(function (t) {
                if (inside(f, t.cx, t.cy, 1)) return;                               // şeklin kendi etiketi
                var h = t.y1 - t.y0, ty0 = t.y0 - h * 0.22, ty1 = t.y0 + h * 0.8;  // inen ve çıkan harf payıyla
                if (t.x1 < c.x0 || t.x0 > c.x1 || ty1 < c.y0 || ty0 > c.y1) return;
                if (ty1 <= f.y0 + 0.5) c.y0 = Math.max(c.y0, Math.min(f.y0, ty1 + 0.5));
                else if (ty0 >= f.y1 - 0.5) c.y1 = Math.min(c.y1, Math.max(f.y1, ty0 - 0.5));
                else if (t.x1 <= f.x0 + 0.5) c.x0 = Math.max(c.x0, Math.min(f.x0, t.x1 + 0.5));
                else if (t.x0 >= f.x1 - 0.5) c.x1 = Math.min(c.x1, Math.max(f.x1, t.x0 - 0.5));
            });
            f.crop = c;
        });
        figs.fracs = fracs;
        figs.bars = findBars(an.paths, items.filter(Boolean));
        return figs;
    }

    // Birden çok sayfada aynı yerde tekrar eden şekiller (logo, üst/alt bilgi süsü) -> at
    function dropRepeated(pagesFigs, pagesWH) {
        var cnt = {};
        function k(f) { return Math.round(f.x0 / 4) + ':' + Math.round(f.y0 / 4) + ':' + Math.round(f.x1 / 4) + ':' + Math.round(f.y1 / 4); }
        pagesFigs.forEach(function (fs) { var seen = {}; fs.forEach(function (f) { var key = k(f); if (!seen[key]) { seen[key] = 1; cnt[key] = (cnt[key] || 0) + 1; } }); });
        var np = pagesFigs.length;
        return pagesFigs.map(function (fs, pi) {
            var H = pagesWH[pi][1];
            return fs.filter(function (f) {
                var c = cnt[k(f)];
                if (c < 2) return true;
                var inMargin = f.y0 > H * 0.85 || f.y1 < H * 0.12;
                return !(inMargin || c >= Math.max(3, Math.ceil(np * 0.6)));
            });
        });
    }

    // Tarayıcıda: sayfayı çizip şekil alanlarını keser, data-URI döner
    async function cropFigures(page, figs) {
        if (typeof document === 'undefined' || !figs.length) return figs.map(function () { return ''; });
        var scale = 2, vp = page.getViewport({ scale: scale });
        var cv = document.createElement('canvas'); cv.width = Math.ceil(vp.width); cv.height = Math.ceil(vp.height);
        var ctx = cv.getContext('2d'); ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, cv.width, cv.height);
        var task = page.render({ canvasContext: ctx, canvas: cv, viewport: vp, annotationMode: 0 });
        await (task.promise || task);
        return figs.map(function (f) {
            var c = f.crop || { x0: f.x0 - 3, y0: f.y0 - 3, x1: f.x1 + 3, y1: f.y1 + 3 };
            var p1 = vp.convertToViewportPoint(c.x0, c.y0), p2 = vp.convertToViewportPoint(c.x1, c.y1);
            var x = Math.max(0, Math.floor(Math.min(p1[0], p2[0]))), y = Math.max(0, Math.floor(Math.min(p1[1], p2[1])));
            var w = Math.min(cv.width - x, Math.ceil(Math.abs(p2[0] - p1[0]))), h = Math.min(cv.height - y, Math.ceil(Math.abs(p2[1] - p1[1])));
            if (w < 4 || h < 4) return '';
            var maxW = 900, k = w > maxW ? maxW / w : 1;
            var out = document.createElement('canvas'); out.width = Math.round(w * k); out.height = Math.round(h * k);
            var oc = out.getContext('2d'); oc.fillStyle = '#ffffff'; oc.fillRect(0, 0, out.width, out.height);
            oc.drawImage(cv, x, y, w, h, 0, 0, out.width, out.height);
            var png = out.toDataURL('image/png'), jpg = out.toDataURL('image/jpeg', 0.85);
            return png.length <= jpg.length * 1.15 ? png : jpg;
        });
    }

    function percentile(arr, p) { if (!arr.length) return 0; var a = arr.slice().sort(function (x, y) { return x - y; }); return a[Math.min(a.length - 1, Math.floor(a.length * p))]; }

    // items: pdf.js textContent.items ; W,H: sayfa boyutu. Döner: satır dizisi (string[])
    function layoutPage(items, W, H, figs) {
        var its = [], fracs = (figs && figs.fracs) || [];
        if (figs && figs.bars && figs.bars.length) items = items.concat(figs.bars);
        if (fracs.length) {
            var drop = {}; fracs.forEach(function (f) { f.drop.forEach(function (i) { drop[i] = 1; }); });
            var keep = items.filter(function (it, i) { return !drop[i]; });
            fracs.forEach(function (f) {
                f.add.forEach(function (a) {
                    if (a.transform[5] !== null) { keep.push(a); return; }
                    // kesri ana satırın taban çizgisine yerleştir (çizginin biraz altı)
                    var base = a._barY - 3, best = null;
                    keep.forEach(function (it) { if (it && it.transform && it.transform[5] !== null && Math.abs(it.transform[5] - base) <= 6 && (best === null || Math.abs(it.transform[5] - base) < Math.abs(best - base))) best = it.transform[5]; });
                    keep.push({ str: a.str, transform: [1, 0, 0, 1, a.transform[4], best === null ? base : best], width: a.width, height: a.height });
                });
            });
            items = keep;
        }
        figs = (figs || []).filter(function (f) { return f.token; });
        var imgFigs = figs.filter(function (f) { return f.kind === 'img'; });
        items.forEach(function (it) {
            if (!it || typeof it.str !== 'string') return;
            if (imgFigs.length && it.transform) {
                var ih = Math.abs(it.height || it.transform[3] || 10) || 10, icx = it.transform[4] + (it.width || 0) / 2, icy = it.transform[5] + ih * 0.35;
                if (imgFigs.some(function (f) { return inside(f, icx, icy, 1); })) return;   // şeklin içindeki yazı (etiket, tablo hücresi) resimde zaten var
            }
            var t = it.str.replace(/\s+$/g, function (m) { return m.length ? ' ' : ''; });
            if (!t.trim()) return;
            var h = Math.abs(it.height || (it.transform && it.transform[3]) || 10) || 10;
            its.push({ s: it.str, x: it.transform[4], y: it.transform[5], w: it.width || 0, h: h });
        });
        if (!its.length && !figs.length) return [];
        var medH = percentile(its.map(function (i) { return i.h; }), 0.5) || 10;
        // küçük kutu/boşluk şekillerini yazı öğesi gibi satırın içine, doğru yerine koy
        figs.forEach(function (f) {
            if (f.kind !== 'blank') return;
            var best = null; its.forEach(function (it) { if (it.y >= f.y0 - it.h * 0.7 && it.y <= f.y1 + 1 && (best === null || Math.abs(it.y - f.y0) < Math.abs(best - f.y0))) best = it.y; });
            if (best === null) return;
            its.push({ s: f.token, x: f.x0, y: best, w: f.x1 - f.x0, h: medH }); f._done = 1;
        });

        // 0) alt bilgi: sayfa numarası, "Diğer sayfaya geçiniz", "Sayfa 2/8" -> at
        its = its.filter(function (it) {
            if (it.y > H * 0.07) return true;
            return !/^\s*(?:\d{1,3}|sayfa\s*\d+(?:\s*\/\s*\d+)?|[-–—]\s*\d+\s*[-–—]|di[ğg]er\s+sayfaya\s+ge[çc]iniz\.?)\s*$/i.test(it.s);
        });

        // 1) y'ye göre satır grupla
        var sorted = its.slice().sort(function (a, b) { return b.y - a.y || a.x - b.x; });
        var rows = [];
        sorted.forEach(function (it) {
            var row = rows.length ? rows[rows.length - 1] : null;
            if (row && Math.abs(row.y - it.y) <= Math.max(2, it.h * 0.45)) { row.items.push(it); }
            else rows.push({ y: it.y, items: [it] });
        });

        // 2) satır içinde segmentlere ayır (büyük boşluk = yeni segment)
        rows.forEach(function (row) {
            row.items.sort(function (a, b) { return a.x - b.x; });
            var segs = [], cur = null;
            row.items.forEach(function (it) {
                if (cur && it.x - cur.right <= Math.max(medH * 1.6, 8)) {
                    var gap = it.x - cur.right;
                    var numJoin = /[\d,.\u0305]$/.test(cur.s) && /^[\d,.]/.test(it.s) && it.s.indexOf('/') < 0 && gap < medH * 0.5;
                    cur.s += (gap > medH * 0.12 && !numJoin && !/\s$/.test(cur.s) && !/^\s/.test(it.s) ? ' ' : '') + it.s;
                    cur.right = Math.max(cur.right, it.x + it.w);
                } else {
                    if (cur) segs.push(cur);
                    cur = { s: it.s, x: it.x, right: it.x + it.w, y: row.y, h: it.h };
                }
            });
            if (cur) segs.push(cur);
            row.segs = segs;
        });

        // 2b) şekilleri metin akışına yerleştir: yanında soldan bir yazı (ör. "A)") varsa o satıra, yoksa kendi satırına
        if (figs.length) {
            figs.forEach(function (f) {
                if (f._done) return;
                var fcy = (f.y0 + f.y1) / 2, cands = [];
                rows.forEach(function (row) {
                    if (f.kind === 'blank') {
                        if (row.y >= f.y0 - medH * 0.8 && row.y <= f.y1) cands.push({ row: row, d: Math.abs(row.y - f.y0) });
                        return;
                    }
                    if (row.y < f.y0 - 2 || row.y > f.y1 + 2) return;
                    var leftNear = row.segs.some(function (sg) { return !sg.fig && sg.right <= f.x0 + 3 && f.x0 - sg.right <= 120; });
                    var overlap = row.segs.some(function (sg) { return !sg.fig && sg.x < f.x1 - 1 && sg.right > f.x0 + 1; });
                    if (leftNear && !overlap) cands.push({ row: row, d: Math.abs(row.y - fcy) });
                });
                var target = null;
                if (f.kind === 'blank') { cands.sort(function (a, b) { return a.d - b.d; }); target = cands.length ? cands[0].row : null; }
                else if (cands.length === 1) target = cands[0].row;                 // birden çok satıra yayılan yan resim -> kendi satırı
                var seg = { s: f.token, x: f.x0, right: f.x1, y: target ? target.y : f.y1, h: medH, fig: true };
                if (target) { target.segs.push(seg); return; }
                // resmin sağında, aynı yükseklikte yazı (ör. "8. Yandaki resimde ...") varsa resim o yazıdan SONRA gelsin
                var side = rows.filter(function (row) {
                    return row.y >= f.y0 - 2 && row.y <= f.y1 + 2 && row.segs.some(function (sg) { return !sg.fig && sg.x >= f.x1 - 3 && sg.x - f.x1 <= 120; });
                });
                if (f.kind !== 'blank' && side.length) {
                    var lowest = Math.min.apply(null, side.map(function (r) { return r.y; }));
                    seg.y = lowest - medH * 0.7; rows.push({ y: seg.y, segs: [seg] });
                } else rows.push({ y: f.y1, segs: [seg] });
            });
            rows.sort(function (a, b) { return b.y - a.y; });
            rows.forEach(function (r) { r.segs.sort(function (a, b) { return a.x - b.x; }); });
        }

        // 3) iki sütun var mı? (ortada hiçbir segmentin geçmediği dikey boşluk)
        var allSegs = []; rows.forEach(function (r) { r.segs.forEach(function (s) { allSegs.push(s); }); });
        var cx = null, best = Infinity;
        if (allSegs.length >= 12) {
            for (var x = W * 0.3; x <= W * 0.7; x += 2) {
                var cross = 0;
                for (var i = 0; i < allSegs.length; i++) { if (allSegs[i].x < x - 1 && allSegs[i].right > x + 1) cross++; }
                if (cross < best || (cross === best && Math.abs(x - W / 2) < Math.abs(cx - W / 2))) { best = cross; cx = x; }
            }
            var left = 0, right = 0;
            allSegs.forEach(function (s) { if ((s.x + s.right) / 2 < cx) left++; else right++; });
            var strict = best <= Math.max(2, allSegs.length * 0.04) && left >= allSegs.length * 0.2 && right >= allSegs.length * 0.2;
            // Ortadan hiçbir metin geçmiyorsa (cross=0) sağ sütunda tek satır olsa bile iki sütun say
            var clean = best === 0 && left >= 1 && right >= 1 && cx > W * 0.38 && cx < W * 0.62;
            if (!(strict || clean)) cx = null;
        }

        function colLines(segs) {
            // segs -> satırlar (y gruplu), her satır: {s, left, right, y, h}
            var ss = segs.slice().sort(function (a, b) { return b.y - a.y || a.x - b.x; });
            var out = [];
            ss.forEach(function (sg) {
                var l = out.length ? out[out.length - 1] : null;
                if (l && Math.abs(l.y - sg.y) <= Math.max(2, sg.h * 0.45)) {
                    var gap = sg.x - l.right;
                    l.s += (gap > medH * 0.12 ? ' ' : '') + sg.s; l.right = Math.max(l.right, sg.right);
                } else out.push({ s: sg.s, left: sg.x, right: sg.right, y: sg.y, h: sg.h });
            });
            return out;
        }

        function reflow(lines) {
            if (!lines.length) return [];
            var R = percentile(lines.map(function (l) { return l.right; }), 0.95);
            var res = [], i = 0;
            while (i < lines.length) {
                var cur = lines[i].s.trim(), ln = lines[i];
                while (i + 1 < lines.length) {
                    var nx = lines[i + 1], gapY = ln.y - nx.y, nxt = nx.s.trim();
                    if (!nxt || isBlockStart(nxt) || /§IMG\d+§/.test(nxt) || /§IMG\d+§\s*$/.test(cur)) break;
                    if (gapY > Math.max(ln.h, medH) * 1.9) break;
                    // Kıvrılma ölçütü: bir sonraki satırın ilk sözcüğü, bu satırın sonunda kalan boşluğa sığmıyorsa satır kıvrılmıştır
                    var avgChar = (nx.right - nx.left) / Math.max(1, nxt.length);
                    var firstWord = (nxt.split(/\s+/)[0] || '');
                    var room = R - ln.right;
                    var endsSentence = /[\.\?\!:…]["')]?$/.test(cur);
                    // cümle ortasında biten satırlar kesin kıvrılmadır (toleranslı ölçüt); nokta ile bitenlerde sıkı ölçüt
                    if (!(room < (firstWord.length + 1) * avgChar * (endsSentence ? 1.05 : 1.6) + (endsSentence ? 0 : avgChar))) break;
                    // satır sonu heceleme tiresi: "yazıla-" + "bilecek" -> "yazılabilecek" (büyük harfle devam ediyorsa tire kalır: "Türk-İslam")
                    if (/[a-zçğıöşü]-$/.test(cur) && /^[a-zçğıöşü]/.test(nxt)) cur = cur.slice(0, -1) + nxt;
                    else cur += (/-$/.test(cur) && /^[a-zçğıöşü]/.test(nxt) ? '' : ' ') + nxt;
                    i++; ln = lines[i];
                }
                var prevLn = i + 1 < lines.length ? lines[i + 1] : null;
                res.push({ s: cur, y: lines[Math.min(i, lines.length - 1)].y });
                // paragraf boşluğu: sonraki satıra olan mesafe büyükse boş satır ekle
                if (prevLn && (ln.y - prevLn.y) > Math.max(ln.h, medH) * 2.2) res.push({ s: '', y: ln.y });
                i++;
            }
            return res.map(function (r) { return r.s; });
        }

        var out = [];
        if (cx === null) {
            out = reflow(colLines(allSegs));
        } else {
            // yukarıdan aşağı: tam genişlikli satırlar ayırıcı; aralarında önce sol sonra sağ sütun
            var L = [], Rr = [];
            // Üst/alt kenardaki başlık satırı ("TYT ... A KİTAPÇIĞI"): sayfanın en üstünden (altından) kesintisiz bir blok,
            // içerikten belirgin boşlukla ayrılmış ve iki sütun tarafında da yazısı var
            var marginSet = {};
            (function () {
                for (var k = 0; k < rows.length && rows[k].y > H * 0.9; k++) {
                    var nx = rows[k + 1];
                    if (!nx || rows[k].y - nx.y > medH * 2.2) { for (var j = 0; j <= k; j++) marginSet[j] = 1; break; }
                }
                for (var k2 = rows.length - 1; k2 >= 0 && rows[k2].y < H * 0.1; k2--) {
                    var pv = rows[k2 - 1];
                    if (!pv || pv.y - rows[k2].y > medH * 2.2) { for (var j2 = k2; j2 < rows.length; j2++) marginSet[j2] = 1; break; }
                }
            })();
            function isMarginRow(row) {
                var ri = rows.indexOf(row);
                return !!marginSet[ri] && row.segs.some(function (s) { return s.right < cx; }) && row.segs.some(function (s) { return s.x > cx; });
            }
            function flush() { out = out.concat(reflow(colLines(L)), reflow(colLines(Rr))); L = []; Rr = []; }
            rows.forEach(function (row) {
                var spans = row.segs.some(function (s) { return s.x < cx - 1 && s.right > cx + 1; }) || isMarginRow(row);
                if (spans) { flush(); out = out.concat(reflow(colLines(row.segs))); }
                else row.segs.forEach(function (s) { if ((s.x + s.right) / 2 < cx) L.push(s); else Rr.push(s); });
            });
            flush();
        }

        // sayfa numarası / altlık gürültüsünü ayıkla (yalnızca ilk ve son satırlarda)
        while (out.length && (out[out.length - 1] === '' || NOISE.test(out[out.length - 1].trim()))) out.pop();
        while (out.length && (out[0] === '' || NOISE.test(out[0].trim()))) out.shift();
        out = out.filter(function (l) { return !/^di[ğg]er\s+sayfaya\s+ge[çc]iniz\.?$/i.test(l.trim()); });
        return out;
    }

    // Her sayfanın başında/sonunda aynı olan satırlar (okul adı, ders başlığı, alt bilgi) -> at
    function dropRunningHeaders(pages) {
        if (pages.length < 2) return pages;
        // soru numarasıyla başlayan satırlar rakamlarıyla birlikte karşılaştırılır ("1. Bu soru" ile "9. Bu soru" aynı değildir)
        function key(t) { var k = t.toLocaleLowerCase('tr-TR').replace(/\s+/g, ' ').trim(); return isBlockStart(t.trim()) ? k : k.replace(/\d+/g, '#'); }
        var OPTL = /^\(?[A-Ea-e]\s?[\)\.]/;
        var cand = pages.map(function (lines) {
            var nz = []; lines.forEach(function (l, i) { if (l.trim()) nz.push(i); });
            var top = nz.slice(0, 2), bot = nz.slice(-2);
            return { first: nz[0], pos: top.concat(bot) };
        });
        var cnt = {}, firstCnt = {};
        pages.forEach(function (lines, pi) {
            var seen = {};
            cand[pi].pos.forEach(function (i) { var k = key(lines[i]); if (k.length >= 4 && !seen[k]) { seen[k] = 1; cnt[k] = (cnt[k] || 0) + 1; } });
            if (cand[pi].first !== undefined) { var kf = key(lines[cand[pi].first]); firstCnt[kf] = (firstCnt[kf] || 0) + 1; }
        });
        return pages.map(function (lines, pi) {
            var drop = {};
            cand[pi].pos.forEach(function (i) {
                var t = lines[i].trim(), k = key(t);
                if (/§IMG/i.test(t) || OPTL.test(t) || /^\s*soru\s*[-–:]?\s*\d{1,3}\b/i.test(t) || (cnt[k] || 0) < 2) return;
                // soru numarasıyla başlayan satır yalnızca birden çok sayfanın EN ÜST satırıysa başlıktır ("2. SINIF ... TESTİ")
                if (isBlockStart(t) && !(i === cand[pi].first && (firstCnt[k] || 0) >= 2)) return;
                drop[i] = 1;
            });
            return lines.filter(function (l, i) { return !drop[i]; });
        });
    }

    /* MathMagic / Symbol gibi Unicode karşılığı olmayan matematik yazı tiplerini düzelt:
       glifler tek tek konumlandırıldığı için her harfi ayrı öğeye böl (sıra bozulmasın) */
    var MM_MAP = [
        [/MMVariableA/i, { '^': '(', 'h': ')', '6': '[', '@': ']' }],
        [/MMVariableB/i, { '6': '[', '@': ']', '^': '(', 'h': ')' }],
        [/MMBinary/i, { '$': '·', '#': '×', '\'': '÷', '!': '±' }],
        [/MMExtra/i, { '9': '△', '4': '□', '1': '<', '2': '>', '3': '≤', '5': '≥', '0': '○' }],
        [/MMTimes|MMSymbol|MMMath/i, { '-': '−' }]
    ];
    function fontRemap(page, items) {
        var names = {}, out = [];
        function fname(id) { if (names[id] !== undefined) return names[id]; var n = ''; try { if (page.commonObjs.has(id)) n = page.commonObjs.get(id).name || ''; } catch (e) {} return (names[id] = n); }
        items.forEach(function (it) {
            var n = it.fontName ? fname(it.fontName) : '', map = null;
            for (var i = 0; i < MM_MAP.length; i++) if (MM_MAP[i][0].test(n)) { map = MM_MAP[i][1]; break; }
            if (!map && !/^[A-Z]{6}\+MM/.test(n)) { out.push(it); return; }
            map = map || {};
            var s = it.str, L = s.length, w = it.width || 0, t = it.transform;
            if (L <= 1) { var c0 = map[s]; out.push(Object.assign({}, it, { str: c0 || s, _mm: 1 })); return; }
            for (var k = 0; k < L; k++) {
                var ch = s.charAt(k); if (!ch.trim()) continue;
                var nt = t.slice(); nt[4] = t[4] + w * k / L;
                out.push(Object.assign({}, it, { str: map[ch] || ch, width: w / L, transform: nt, _mm: 1 }));
            }
        });
        function sz(it) { var t = it.transform; return Math.hypot(t[2], t[3]) || Math.abs(t[3]) || 10; }
        // araya matematik glifi giren boşluklu düz yazıyı da harflerine böl: "+ +" ( "(+3) + (+5)" )
        var mm = out.filter(function (o) { return o._mm; });
        if (mm.length) out = out.reduce(function (acc, it) {
            var s = it.str, L = s.length, w = it.width || 0, t = it.transform, z = sz(it);
            if (it._mm || L < 2 || !/\S\s+\S/.test(s) || !mm.some(function (m) { return Math.abs(m.transform[5] - t[5]) < z * 0.7 && m.transform[4] > t[4] + 0.5 && m.transform[4] < t[4] + w - 0.5; })) { acc.push(it); return acc; }
            for (var k = 0; k < L; k++) { var ch = s.charAt(k); if (!ch.trim()) continue; var nt = t.slice(); nt[4] = t[4] + w * k / L; acc.push(Object.assign({}, it, { str: ch, width: w / L, transform: nt })); }
            return acc;
        }, []);
        // üsler: küçük puntolu, yukarıda duran rakamlar -> ⁰¹²³
        var SUP = { '0': '⁰', '1': '¹', '2': '²', '3': '³', '4': '⁴', '5': '⁵', '6': '⁶', '7': '⁷', '8': '⁸', '9': '⁹', '+': '⁺', '-': '⁻', '−': '⁻', '–': '⁻', 'n': 'ⁿ' };
        out.forEach(function (it) {
            var s = it.str.trim(); if (!s || s.length > 3) return;
            for (var i = 0; i < s.length; i++) if (!SUP[s.charAt(i)]) return;
            var z = sz(it), x = it.transform[4], y = it.transform[5], b = null;
            out.forEach(function (o) { if (o === it || b) return; var zo = sz(o), dy = y - o.transform[5]; if (zo > z * 1.15 && Math.abs(o.transform[4] + (o.width || 0) - x) < Math.max(2.5, zo * 0.45) && dy > zo * 0.18 && dy < zo * 0.85) b = o; });
            if (!b) return;
            it.str = s.split('').map(function (c) { return SUP[c]; }).join(''); it.transform = it.transform.slice(); it.transform[5] = b.transform[5]; it.height = b.height;
        });
        return out;
    }
    async function pdfToText(pdf, onProgress, ocrFn, opts) {
        opts = opts || {};
        var OPS = opts.OPS || (typeof pdfjsLib !== 'undefined' && pdfjsLib.OPS) || null;
        var crop = opts.crop || cropFigures;
        var pd = [], total = 0, p;
        for (p = 1; p <= pdf.numPages; p++) {
            if (onProgress) onProgress('PDF okunuyor: sayfa ' + p + '/' + pdf.numPages);
            var pg = await pdf.getPage(p), vp = pg.getViewport({ scale: 1 }), ct = await pg.getTextContent();
            pd.push({ page: pg, vp: vp, ct: { items: cleanItems(ct.items) }, figs: [] });
        }
        // Sayfaların üst/alt kenarında aynı yerde tekrar eden yazılar (okul/kanal adı, sınav başlığı, sayfa no) -> at
        if (pd.length >= 2) {
            // Satır imzası: aynı yükseklikteki tüm yazılar (metin + yaklaşık konum). Başka sayfada birebir aynı satır varsa üst/alt bilgidir.
            var rowsOf = function (d) {
                var rs = [];
                d.ct.items.slice().sort(function (a, b) { return b.transform[5] - a.transform[5] || a.transform[4] - b.transform[4]; }).forEach(function (it) {
                    var r = rs.length ? rs[rs.length - 1] : null;
                    if (r && Math.abs(r.y - it.transform[5]) < 2.5) r.items.push(it); else rs.push({ y: it.transform[5], items: [it] });
                });
                rs.forEach(function (r) { r.sig = r.items.slice().sort(function (a, b) { return a.transform[4] - b.transform[4]; }).map(function (it) { return it.str.replace(/\s+/g, '') + '@' + Math.round(it.transform[4] / 4); }).join('|') + '#' + Math.round(r.y / 4); });
                return rs;
            };
            var allRows = pd.map(rowsOf), sigCnt = {};
            allRows.forEach(function (rs) { var once = {}; rs.forEach(function (r) { if (!once[r.sig]) { once[r.sig] = 1; sigCnt[r.sig] = (sigCnt[r.sig] || 0) + 1; } }); });
            pd.forEach(function (d, pi) {
                var H = d.vp.height, dropSet = [];
                // yalnızca sayfanın en üstünden/altından kesintisiz giden tekrar eden satırlar; ilk içerik satırında dur
                var rs = allRows[pi];
                for (var a1 = 0; a1 < rs.length && rs[a1].y > H * 0.8 && sigCnt[rs[a1].sig] >= 2; a1++) dropSet = dropSet.concat(rs[a1].items);
                for (var b1 = rs.length - 1; b1 > a1 && rs[b1].y < H * 0.15 && sigCnt[rs[b1].sig] >= 2; b1--) dropSet = dropSet.concat(rs[b1].items);
                if (dropSet.length) d.ct.items = d.ct.items.filter(function (it) { return dropSet.indexOf(it) < 0; });
            });
        }
        for (p = 0; p < pd.length; p++) {
            if (!(OPS && opts.figures !== false)) break;
            try { var opl = await pd[p].page.getOperatorList({ annotationMode: 0 }); pd[p].ct.items = fontRemap(pd[p].page, pd[p].ct.items); pd[p].figs = findFigures(opl, pd[p].ct.items, pd[p].vp.width, pd[p].vp.height, OPS); }
            catch (e) { if (typeof console !== 'undefined') console.warn('OSC: şekil analizi atlandı (sayfa ' + (p + 1) + ')', e); pd[p].figs = []; }
        }
        var kept = dropRepeated(pd.map(function (d) { return d.figs; }), pd.map(function (d) { return [d.vp.width, d.vp.height]; }));
        // sayfa altındaki sayfa-numarası rozeti, üst köşedeki logo gibi kenar süsleri (tek sayfalık dosyada da)
        kept = kept.map(function (fs, i) {
            var W = pd[i].vp.width, H = pd[i].vp.height;
            var r = fs.filter(function (f) { return !(f.kind === 'img' && (f.y1 < H * 0.075 || f.y0 > H * 0.9) && (f.x1 - f.x0) < W * 0.3); });
            r.fracs = fs.fracs; return r;
        });
        var images = [], texts = [], stats = { images: 0, tables: 0, blanks: 0 };
        for (var i = 0; i < pd.length; i++) {
            var fs = kept[i], imgFigs = fs.filter(function (f) { return f.kind === 'img'; });
            fs.fracs = pd[i].figs.fracs || []; fs.bars = pd[i].figs.bars || [];
            if (imgFigs.length) {
                if (onProgress) onProgress('Resim ve tablolar alınıyor: sayfa ' + (i + 1) + '/' + pd.length);
                var uris = [];
                try { uris = await crop(pd[i].page, imgFigs); } catch (e) { if (typeof console !== 'undefined') console.warn('OSC: resim kesilemedi', e); }
                imgFigs.forEach(function (f, k) {
                    if (uris[k]) { f.token = '§IMG' + images.length + '§'; images.push(uris[k]); if (f.table) stats.tables++; else stats.images++; }
                });
            }
            fs.forEach(function (f) { if (f.kind === 'blank') { var bw = f.x1 - f.x0, bh = f.y1 - f.y0; f.token = (bw <= 16 && bh <= 16 && bw / bh > 0.6 && bw / bh < 1.6) ? '□' : '____'; stats.blanks++; } });
            texts.push(layoutPage(pd[i].ct.items, pd[i].vp.width, pd[i].vp.height, fs));
        }
        // şıkları sütunlu tablo olan sorular: "A B C" başlığı + "A) 2 + −4" -> "A) A: 2 · B: + · C: −4"
        function sutunluSik(lines) {
            for (var i = 0; i < lines.length; i++) {
                var hm = lines[i].trim(); if (!/^[A-EI]{1,3}(\s+[A-EI]{1,3}){1,4}$/.test(hm)) continue;
                var hd = hm.split(/\s+/), j = i + 1, yeni = [], ok = true;
                while (j < lines.length && !lines[j].trim()) j++;
                for (var k = j; k < lines.length && /^\s*[A-E]\s*\)/.test(lines[k]); k++) {
                    var m = lines[k].match(/^\s*([A-E])\s*\)\s*(.*)$/), tk = m[2].trim().replace(/([−–\-])\s+(?=\d)/g, '$1').split(/\s+/);
                    if (tk.length !== hd.length) { ok = false; break; }
                    yeni.push(m[1] + ') ' + tk.join('  |  '));
                }
                if (ok && yeni.length >= 2) { lines.splice(j, yeni.length, ...yeni); lines[i] = '(' + hd.join('  |  ') + ')'; }
            }
            return lines;
        }
        texts = dropRunningHeaders(texts).map(sutunluSik).map(function (lines) {
            var joined = lines.join('\n').replace(/([a-zçğıöşü])-\s*\n\s*([a-zçğıöşü])/g, '$1$2').replace(/([a-zçğıöşü])- ([a-zçğıöşü]{2,})/g, '$1$2');
            total += (joined.replace(/§IMG\d+§/g, '').match(/[0-9A-Za-zğüşıöçĞÜŞİÖÇ]/g) || []).length;
            return joined;
        });
        var scanned = total < 25 * pdf.numPages;
        if (scanned && ocrFn) {
            var ocrTexts = [];
            for (var j = 0; j < pd.length; j++) {
                if (onProgress) onProgress('Taranmış PDF, OCR çalışıyor: sayfa ' + (j + 1) + '/' + pd.length + ' (biraz sürebilir)');
                ocrTexts.push(await ocrFn(pd[j].page));
            }
            return { text: ocrTexts.join('\n'), ocr: true, images: [], stats: stats };
        }
        return { text: texts.join('\n'), ocr: false, images: images, stats: stats };
    }

    /* ---------------- DOCX ---------------- */
    function kids(n) { var r = [], c = n.firstChild; while (c) { if (c.nodeType === 1) r.push(c); c = c.nextSibling; } return r; }
    function A(n, name) { return n.getAttribute('w:' + name) || n.getAttribute(name) || ''; }
    function child(n, local) { var c = n.firstChild; while (c) { if (c.nodeType === 1 && c.localName === local) return c; c = c.nextSibling; } return null; }
    function descendants(n, local, acc) { acc = acc || []; var c = n.firstChild; while (c) { if (c.nodeType === 1) { if (c.localName === local) acc.push(c); descendants(c, local, acc); } c = c.nextSibling; } return acc; }

    function toLetters(n, upper) { var s = ''; while (n > 0) { n--; s = String.fromCharCode(65 + (n % 26)) + s; n = Math.floor(n / 26); } return upper ? s : s.toLowerCase(); }
    function toRoman(n, upper) {
        var map = [[1000, 'M'], [900, 'CM'], [500, 'D'], [400, 'CD'], [100, 'C'], [90, 'XC'], [50, 'L'], [40, 'XL'], [10, 'X'], [9, 'IX'], [5, 'V'], [4, 'IV'], [1, 'I']], s = '';
        map.forEach(function (m) { while (n >= m[0]) { s += m[1]; n -= m[0]; } });
        return upper ? s : s.toLowerCase();
    }
    function fmtNum(n, fmt) {
        switch (fmt) {
            case 'upperLetter': return toLetters(n, true);
            case 'lowerLetter': return toLetters(n, false);
            case 'upperRoman': return toRoman(n, true);
            case 'lowerRoman': return toRoman(n, false);
            case 'decimalZero': return (n < 10 ? '0' : '') + n;
            case 'none': return '';
            default: return String(n);
        }
    }
    var SUP = { '0': '⁰', '1': '¹', '2': '²', '3': '³', '4': '⁴', '5': '⁵', '6': '⁶', '7': '⁷', '8': '⁸', '9': '⁹', '+': '⁺', '-': '⁻', '=': '⁼', '(': '⁽', ')': '⁾', 'n': 'ⁿ' };
    var SUB = { '0': '₀', '1': '₁', '2': '₂', '3': '₃', '4': '₄', '5': '₅', '6': '₆', '7': '₇', '8': '₈', '9': '₉', '+': '₊', '-': '₋', '=': '₌', '(': '₍', ')': '₎' };
    function mapChars(t, map) { var ok = true, o = ''; for (var i = 0; i < t.length; i++) { var c = map[t.charAt(i)]; if (c === undefined) { ok = false; break; } o += c; } return ok ? o : null; }

    function parseDocxXml(str) {
        var doc = new DOMParser().parseFromString(str, 'application/xml');
        if (doc.getElementsByTagName('parsererror').length) throw new Error('Word dosyasının XML yapısı okunamadı.');
        return doc;
    }

    var DOCX_LATEX = false;
    async function docxToText(arrayBuffer, opts) {
        DOCX_LATEX = !!(opts && opts.latex);
        var zip = await JSZip.loadAsync(arrayBuffer);
        var docFile = zip.file('word/document.xml');
        if (!docFile) throw new Error('Geçerli bir .docx dosyası değil.');
        var doc = parseDocxXml(await docFile.async('string'));

        var numbering = { abs: {}, nums: {} }, styles = {}, rels = {};
        var nf = zip.file('word/numbering.xml');
        if (nf) {
            var nd = parseDocxXml(await nf.async('string'));
            descendants(nd.documentElement, 'abstractNum').forEach(function (an) {
                var id = A(an, 'abstractNumId'), lv = {};
                kids(an).forEach(function (l) {
                    if (l.localName !== 'lvl') return;
                    var il = A(l, 'ilvl'), st = child(l, 'start'), fm = child(l, 'numFmt'), tx = child(l, 'lvlText');
                    lv[il] = { start: st ? parseInt(A(st, 'val'), 10) : 1, fmt: fm ? A(fm, 'val') : 'decimal', text: tx ? A(tx, 'val') : '%' + (parseInt(il, 10) + 1) + '.' };
                });
                numbering.abs[id] = lv;
            });
            kids(nd.documentElement).forEach(function (n) {
                if (n.localName !== 'num') return;
                var id = A(n, 'numId'), ab = child(n, 'abstractNumId'), ov = {};
                kids(n).forEach(function (o) { if (o.localName === 'lvlOverride') { var so = child(o, 'startOverride'); if (so) ov[A(o, 'ilvl')] = parseInt(A(so, 'val'), 10); } });
                numbering.nums[id] = { abs: ab ? A(ab, 'val') : null, ov: ov };
            });
        }
        var sf = zip.file('word/styles.xml');
        if (sf) {
            var sd = parseDocxXml(await sf.async('string'));
            kids(sd.documentElement).forEach(function (s) {
                if (s.localName !== 'style') return;
                var id = A(s, 'styleId'), bo = child(s, 'basedOn'), ppr = child(s, 'pPr'), np = ppr ? child(ppr, 'numPr') : null, ni = np ? child(np, 'numId') : null, il = np ? child(np, 'ilvl') : null;
                styles[id] = { base: bo ? A(bo, 'val') : null, numId: ni ? A(ni, 'val') : null, ilvl: il ? A(il, 'val') : null };
            });
        }
        var rf = zip.file('word/_rels/document.xml.rels');
        if (rf) {
            var rd = parseDocxXml(await rf.async('string'));
            descendants(rd.documentElement, 'Relationship').forEach(function (r) { rels[r.getAttribute('Id')] = r.getAttribute('Target'); });
        }

        function styleNum(id, depth) {
            if (!id || depth > 8 || !styles[id]) return null;
            var s = styles[id];
            if (s.numId) return { numId: s.numId, ilvl: s.ilvl };
            return styleNum(s.base, depth + 1);
        }

        var counters = {}, usedNum = {}, images = [], imgIndex = {};
        function imageToken(rid) {
            if (!rid || !rels[rid]) return '';
            if (imgIndex[rid] === undefined) { imgIndex[rid] = images.length; images.push(rels[rid]); }
            return '§IMG' + imgIndex[rid] + '§';
        }

        // node altında 'local' adlı öğeleri bulur ama 'skip' öğelerinin (metin kutusu içeriği) içine inmez
        function descSkip(n, local, skip, acc) {
            acc = acc || []; var c = n.firstChild;
            while (c) { if (c.nodeType === 1) { if (c.localName === local) acc.push(c); if (c.localName !== skip) descSkip(c, local, skip, acc); } c = c.nextSibling; }
            return acc;
        }
        // Çizim / şekil / metin kutusu: resimler + metin kutusundaki yazılar (ayrı satırlar olarak)
        function drawingContent(node) {
            var t = '';
            descSkip(node, 'blip', 'txbxContent').forEach(function (b) { t += imageToken(b.getAttribute('r:embed') || b.getAttribute('r:link')); });
            descSkip(node, 'imagedata', 'txbxContent').forEach(function (b) { t += imageToken(b.getAttribute('r:id')); });
            var boxes = descSkip(node, 'txbxContent', 'txbxContent');
            if (boxes.length) {
                var saved = lines; lines = [];
                boxes.forEach(function (bx) { walkBlock(bx); });
                var inner = lines.filter(function (l) { return l.replace(/§K§/g, '').trim(); });
                lines = saved;
                if (inner.length) t += '\n' + inner.join('\n') + '\n';
            }
            return t;
        }

        function runText(r) {
            var t = '', vaEl = null, rp = child(r, 'rPr');
            if (rp) vaEl = child(rp, 'vertAlign');
            var va = vaEl ? A(vaEl, 'val') : '';
            kids(r).forEach(function (c) {
                switch (c.localName) {
                    case 't': t += c.textContent; break;
                    case 'tab': t += ' '; break;
                    case 'br': case 'cr': t += '\n'; break;
                    case 'noBreakHyphen': t += '-'; break;
                    case 'drawing': case 'pict': case 'object': t += drawingContent(c); break;
                    case 'AlternateContent': {
                        var alt = child(c, 'Choice') || child(c, 'Fallback');
                        if (alt) kids(alt).forEach(function (g) { if (/^(drawing|pict|object)$/.test(g.localName)) t += drawingContent(g); });
                        break;
                    }
                }
            });
            if (va === 'superscript' && t) { var m1 = mapChars(t, SUP); if (m1 !== null) return m1; }
            if (va === 'subscript' && t) { var m2 = mapChars(t, SUB); if (m2 !== null) return m2; }
            return t;
        }

        // Word denklemi (OMML) -> okunur metin: kesir (a)/(b), üs x², kök √(x), parantez, toplam/integral
        function ommlText(n) {
            function kidsOf(e, name) { var c = child(e, name); return c ? ommlText(c) : ''; }
            function wrap(s) { s = s.trim(); return /^[0-9A-Za-zçğıöşüÇĞİÖŞÜ.,]+$/.test(s) || /^\(.*\)$/.test(s) ? s : '(' + s + ')'; }
            function chrOf(e, prName, attr, def) { var pr = child(e, prName), c = pr && child(pr, attr); return c ? (c.getAttribute('m:val') || c.getAttribute('val') || def) : def; }
            var out = '';
            kids(n).forEach(function (e) {
                var ln = e.localName;
                if (/Pr$/.test(ln)) return;
                switch (ln) {
                    case 't': out += e.textContent; break;
                    case 'f': out += wrap(kidsOf(e, 'num')) + '/' + wrap(kidsOf(e, 'den')); break;
                    case 'sSup': { var b = kidsOf(e, 'e'), p = kidsOf(e, 'sup'), m1 = mapChars(p.trim(), SUP); out += b + (m1 !== null ? m1 : '^' + wrap(p)); break; }
                    case 'sSub': { var b2 = kidsOf(e, 'e'), s2 = kidsOf(e, 'sub'), m2 = mapChars(s2.trim(), SUB); out += b2 + (m2 !== null ? m2 : '_' + wrap(s2)); break; }
                    case 'sSubSup': { var b3 = kidsOf(e, 'e'), s3 = kidsOf(e, 'sub'), p3 = kidsOf(e, 'sup'), ms = mapChars(s3.trim(), SUB), mp = mapChars(p3.trim(), SUP); out += b3 + (ms !== null ? ms : '_' + wrap(s3)) + (mp !== null ? mp : '^' + wrap(p3)); break; }
                    case 'rad': { var dg = kidsOf(e, 'deg').trim(), md = dg ? mapChars(dg, SUP) : ''; out += (dg ? (md !== null ? md : dg) : '') + '√' + wrap(kidsOf(e, 'e')); break; }
                    case 'd': {
                        var beg = chrOf(e, 'dPr', 'begChr', '('), end = chrOf(e, 'dPr', 'endChr', ')'), sep = chrOf(e, 'dPr', 'sepChr', '; ');
                        out += beg + kids(e).filter(function (k) { return k.localName === 'e'; }).map(ommlText).join(sep) + end; break;
                    }
                    case 'nary': { var ch = chrOf(e, 'naryPr', 'chr', '∫'); out += ch + kidsOf(e, 'sub') + (kidsOf(e, 'sup') ? '^' + wrap(kidsOf(e, 'sup')) : '') + ' ' + kidsOf(e, 'e'); break; }
                    case 'bar': case 'acc': case 'box': case 'borderBox': case 'groupChr': case 'func': case 'limLow': case 'limUpp': case 'eqArr': case 'm': case 'mr':
                    default: out += ommlText(e);
                }
            });
            return out;
        }

        // Word denklemi → LaTeX (yazılı havuzu: KaTeX ile gerçek formül)
        function ommlLatex(n) {
            function K(e, name) { var c = child(e, name); return c ? ommlLatex(c) : ''; }
            function chrOf(e, prName, attr, def) { var pr = child(e, prName), c = pr && child(pr, attr); return c ? (c.getAttribute('m:val') || c.getAttribute('val') || def) : def; }
            var SYM = { '×': '\\times ', '÷': '\\div ', '·': '\\cdot ', '≤': '\\le ', '≥': '\\ge ', '≠': '\\ne ', '±': '\\pm ', '∞': '\\infty ', 'π': '\\pi ', '°': '^{\\circ}', '√': '\\sqrt{}', '∠': '\\angle ', '△': '\\triangle ', '⊥': '\\perp ', '∥': '\\parallel ', '∈': '\\in ', '∉': '\\notin ', '⊂': '\\subset ', '∪': '\\cup ', '∩': '\\cap ', '∅': '\\emptyset ', '→': '\\to ', '⇒': '\\Rightarrow ', '≅': '\\cong ', '≈': '\\approx ', '−': '-', 'α': '\\alpha ', 'β': '\\beta ', 'θ': '\\theta ', 'Δ': '\\Delta ', '%': '\\% ', '{': '\\{', '}': '\\}' };
            var out = '';
            kids(n).forEach(function (e) {
                var ln = e.localName;
                if (/Pr$/.test(ln)) return;
                switch (ln) {
                    case 't': out += e.textContent.replace(/[×÷·≤≥≠±∞π°∠△⊥∥∈∉⊂∪∩∅→⇒≅≈−αβθΔ%{}]/g, function (c) { return SYM[c] || c; }); break;
                    case 'f': out += '\\frac{' + K(e, 'num') + '}{' + K(e, 'den') + '}'; break;
                    case 'sSup': out += '{' + K(e, 'e') + '}^{' + K(e, 'sup') + '}'; break;
                    case 'sSub': out += '{' + K(e, 'e') + '}_{' + K(e, 'sub') + '}'; break;
                    case 'sSubSup': out += '{' + K(e, 'e') + '}_{' + K(e, 'sub') + '}^{' + K(e, 'sup') + '}'; break;
                    case 'rad': { var dg = K(e, 'deg').trim(); out += '\\sqrt' + (dg ? '[' + dg + ']' : '') + '{' + K(e, 'e') + '}'; break; }
                    case 'd': { var beg = chrOf(e, 'dPr', 'begChr', '('), end = chrOf(e, 'dPr', 'endChr', ')'), sep = chrOf(e, 'dPr', 'sepChr', ',');
                        var bm = { '(': '(', ')': ')', '[': '[', ']': ']', '{': '\\{', '}': '\\}', '|': '|', '': '.' };
                        out += '\\left' + (bm[beg] !== undefined ? bm[beg] : beg) + kids(e).filter(function (k) { return k.localName === 'e'; }).map(ommlLatex).join(sep) + '\\right' + (bm[end] !== undefined ? bm[end] : end); break; }
                    case 'bar': out += '\\overline{' + K(e, 'e') + '}'; break;
                    case 'acc': { var ac = chrOf(e, 'accPr', 'chr', '̂'); out += (ac === '⃗' || ac === '→' ? '\\vec{' : (ac === '̅' || ac === '¯' ? '\\overline{' : '\\hat{')) + K(e, 'e') + '}'; break; }
                    case 'nary': { var ch = chrOf(e, 'naryPr', 'chr', '∫'), cm = { '∑': '\\sum', '∏': '\\prod', '∫': '\\int' }; out += (cm[ch] || ch) + (K(e, 'sub') ? '_{' + K(e, 'sub') + '}' : '') + (K(e, 'sup') ? '^{' + K(e, 'sup') + '}' : '') + ' ' + K(e, 'e'); break; }
                    case 'func': out += '\\operatorname{' + K(e, 'fName') + '}' + K(e, 'e'); break;
                    default: out += ommlLatex(e);
                }
            });
            return out;
        }
        function inlineText(node) {
            var out = '';
            kids(node).forEach(function (c) {
                switch (c.localName) {
                    case 'r': out += runText(c); break;
                    case 'hyperlink': case 'ins': case 'smartTag': case 'fldSimple': case 'sdt': case 'sdtContent': case 'moveTo': case 'customXml': out += inlineText(c); break;
                    case 'AlternateContent': { var ch = child(c, 'Choice') || child(c, 'Fallback'); if (ch) out += inlineText(ch); break; }
                    case 'oMath': case 'oMathPara': out += DOCX_LATEX ? ' $' + ommlLatex(c).replace(/\s+/g, ' ').trim() + '$ ' : ommlText(c); break;
                    default: break; // pPr, del, bookmark vb.
                }
            });
            return out;
        }

        function numberPrefix(p) {
            var ppr = child(p, 'pPr'), numId = null, ilvl = '0';
            if (ppr) {
                var np = child(ppr, 'numPr');
                if (np) { var ni = child(np, 'numId'), il = child(np, 'ilvl'); if (ni) numId = A(ni, 'val'); if (il) ilvl = A(il, 'val'); }
                if (numId === null) {
                    var ps = child(ppr, 'pStyle'), sn = ps ? styleNum(A(ps, 'val'), 0) : null;
                    if (sn) { numId = sn.numId; ilvl = sn.ilvl || '0'; }
                } else if (!child(child(ppr, 'numPr'), 'ilvl')) {
                    var ps2 = child(ppr, 'pStyle'), sn2 = ps2 ? styleNum(A(ps2, 'val'), 0) : null;
                    if (sn2 && sn2.ilvl) ilvl = sn2.ilvl;
                }
            }
            if (numId === null || numId === '0') return '';
            var nm = numbering.nums[numId]; if (!nm || nm.abs === null) return '';
            var lv = numbering.abs[nm.abs]; if (!lv || !lv[ilvl]) return '';
            var c = counters[nm.abs] || (counters[nm.abs] = {});
            var L = parseInt(ilvl, 10), def = lv[ilvl];
            if (!usedNum[numId]) { usedNum[numId] = true; if (nm.ov[ilvl] !== undefined) c[L] = nm.ov[ilvl] - 1; }
            c[L] = (c[L] === undefined ? def.start - 1 : c[L]) + 1;
            Object.keys(c).forEach(function (k) { if (parseInt(k, 10) > L) delete c[k]; });
            if (def.fmt === 'bullet') return '• ';
            var txt = def.text.replace(/%(\d)/g, function (_, d) {
                var li = parseInt(d, 10) - 1, ld = lv[String(li)] || def, val = c[li] === undefined ? ld.start : c[li];
                return fmtNum(val, ld.fmt);
            });
            if (/[\uF000-\uF0FF]/.test(txt)) return '• ';
            return txt ? txt + ' ' : '';
        }

        // Paragrafın tamamı kalın / vurgulu / gölgeli ise sonuna §K§ imi konur (doğru şık işareti olabilir)
        function paraFlag(p) {
            var total = 0, flagged = 0;
            descendants(p, 'r').forEach(function (r) {
                var t = descendants(r, 't').map(function (x) { return x.textContent; }).join('');
                if (!t.trim()) return;
                total++;
                var rp = child(r, 'rPr'); if (!rp) return;
                var b = child(rp, 'b'), hl = child(rp, 'highlight'), sh = child(rp, 'shd');
                var bold = b && !/^(0|false|off)$/i.test(A(b, 'val'));
                var hi = hl && A(hl, 'val') && A(hl, 'val') !== 'none';
                var shd = sh && A(sh, 'fill') && !/^(auto|FFFFFF)$/i.test(A(sh, 'fill'));
                if (bold || hi || shd) flagged++;
            });
            return total > 0 && flagged === total;
        }
        function paragraphText(p) { return (numberPrefix(p) + inlineText(p)).replace(/[ \t]+$/g, '') + (paraFlag(p) ? '§K§' : ''); }

        var lines = [];
        function walkBlock(node) {
            kids(node).forEach(function (c) {
                switch (c.localName) {
                    case 'p': lines.push(paragraphText(c)); break;
                    case 'tbl': handleTable(c); break;
                    case 'sdt': { var sc = child(c, 'sdtContent'); if (sc) walkBlock(sc); break; }
                    case 'AlternateContent': { var ch = child(c, 'Choice'); if (ch) walkBlock(ch); break; }
                    case 'customXml': case 'ins': walkBlock(c); break;
                }
            });
        }
        /* ---- Word tabloları ----
           1) Cevap anahtarı tablosu ("1 2 3" / "B E C")  -> satır satır düz metin
           2) Düzen tablosu (hücrelerde soru numarası, şık ya da harf satırı var) -> hücre hücre satırlar
           3) Veri tablosu (sorunun içindeki gerçek tablo) -> HTML <table> olarak soruya eklenir */
        var tables = [];
        var RQ = /^\s*(?:soru\s*)?\d{1,3}\s*[\.\)]\s*\S/i, ROPT = /^\s*\(?[A-Ea-e]\s?[\)\.]\s*\S/, RTF = /^\s*[\(\[]\s*(?:[DYdy]|do[ğg]ru|yanl[ıi][şs])?\s*[\)\]]\s*\S/i;
        function escT(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
        function cellText(c) { return c.lines.join(' ').replace(/§K§/g, '').replace(/\s+/g, ' ').trim(); }
        function handleTable(tbl) {
            var rowsArr = [];
            kids(tbl).forEach(function (tr) {
                if (tr.localName !== 'tr') return;
                var cells = [];
                kids(tr).forEach(function (tc) {
                    if (tc.localName !== 'tc') return;
                    var tcPr = child(tc, 'tcPr'), span = 1, vm = null;
                    if (tcPr) {
                        var gs = child(tcPr, 'gridSpan'); if (gs) span = parseInt(A(gs, 'val'), 10) || 1;
                        var v = child(tcPr, 'vMerge'); if (v) vm = A(v, 'val') === 'restart' ? 'restart' : 'continue';
                    }
                    var saved = lines; lines = [];
                    walkBlock(tc);
                    var cl = lines.filter(function (l) { return l.replace(/§K§/g, '').trim(); });
                    lines = saved;
                    cells.push({ lines: cl, span: span, vm: vm });
                });
                if (cells.length) rowsArr.push(cells);
            });
            if (!rowsArr.length) return;
            var all = [].concat.apply([], rowsArr), texts = all.map(cellText).filter(Boolean);
            var prev = ''; for (var pi = lines.length - 1; pi >= 0; pi--) { if (lines[pi].trim()) { prev = lines[pi].trim(); break; } }
            var keyCell = /^(?:\d{1,3}|[A-Ea-e]|[DYdy]|do[ğg]ru|yanl[ıi][şs]|\d{1,3}\s*[\.\)\-:]?\s*(?:[A-Ea-eDdYy]|do[ğg]ru|yanl[ıi][şs]))$/i;
            var keyLike = texts.length >= 2 && texts.every(function (t) { return keyCell.test(t); }) && texts.some(function (t) { return /\d/.test(t); });
            var prevKey = prev.length < 60 && /cevap|anahtar|yan[ıi]t/i.test(prev);
            if (keyLike || (prevKey && texts.every(function (t) { return t.length <= 40; }))) {
                rowsArr.forEach(function (cells) { lines.push(cells.map(cellText).join(' ')); });
                return;
            }
            function isLetters(cells) {
                return cells && cells.length >= 2 && cells.every(function (c, k) { return k < 5 && new RegExp('^\\(?' + 'ABCDE'.charAt(k) + '\\s?[\\)\\.]?$', 'i').test(cellText(c)); });
            }
            var layout = all.some(function (c) { return c.lines.some(function (l) { var t = l.replace(/§K§/g, ''); return RQ.test(t) || ROPT.test(t) || RTF.test(t); }); }) || rowsArr.some(isLetters);
            if (layout) {
                for (var i = 0; i < rowsArr.length; i++) {
                    var r = rowsArr[i], nx = rowsArr[i + 1];
                    if (!isLetters(r) && isLetters(nx) && nx.length === r.length) {           // resimler üstte, harfler altta
                        nx.forEach(function (c, k) { lines.push('ABCDE'.charAt(k) + ') ' + cellText(r[k])); }); i++; continue;
                    }
                    if (isLetters(r) && nx && !isLetters(nx) && nx.length === r.length) {      // harfler üstte, resimler altta
                        r.forEach(function (c, k) { lines.push('ABCDE'.charAt(k) + ') ' + cellText(nx[k])); }); i++; continue;
                    }
                    r.forEach(function (c) { c.lines.forEach(function (l) { lines.push(l); }); });
                }
                return;
            }
            // veri tablosu -> HTML (birleşik hücreler: gridSpan = colspan, vMerge = rowspan)
            var open = {}, html = '<table class="osc-q-table" style="border-collapse:collapse;margin:6px 0;max-width:100%;">';
            var built = rowsArr.map(function (cells, ri) {
                var col = 0, out = [];
                cells.forEach(function (c) {
                    if (c.vm === 'continue') { if (open[col]) open[col].rowspan++; col += c.span; return; }
                    var cell = { html: c.lines.map(function (l) { return escT(l.replace(/§K§/g, '')); }).join('<br>'), colspan: c.span, rowspan: 1, head: ri === 0 && rowsArr.length > 1 };
                    if (c.vm === 'restart') open[col] = cell; else delete open[col];
                    out.push(cell); col += c.span;
                });
                return out;
            });
            built.forEach(function (cells) {
                html += '<tr>';
                cells.forEach(function (c) {
                    html += '<td' + (c.colspan > 1 ? ' colspan="' + c.colspan + '"' : '') + (c.rowspan > 1 ? ' rowspan="' + c.rowspan + '"' : '') +
                        ' style="border:1px solid #64748b;padding:4px 10px;text-align:center;vertical-align:middle;' + (c.head ? 'background:#f1f5f9;font-weight:700;' : '') + '">' + (c.html || '&nbsp;') + '</td>';
                });
                html += '</tr>';
            });
            html += '</table>';
            lines.push('§TBL' + tables.length + '§');
            tables.push(html);
        }

        var body = child(doc.documentElement, 'body');
        walkBlock(body || doc.documentElement);

        // Görselleri data-URI'ye çevir
        var dataUris = [], skipped = 0;
        for (var i = 0; i < images.length; i++) {
            var path = images[i].replace(/^\/+/, ''); path = path.indexOf('word/') === 0 ? path : 'word/' + path;
            path = path.replace(/\/[^\/]+\/\.\.\//g, '/');                      // "word/media/../media/x.png"
            var f = zip.file(path), ext = (path.split('.').pop() || '').toLowerCase();
            if (!f || ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp'].indexOf(ext) < 0) { dataUris.push(''); skipped++; continue; }   // EMF/WMF/TIFF tarayıcıda gösterilemez
            var mime = ext === 'jpg' ? 'image/jpeg' : ('image/' + ext);
            dataUris.push('data:' + mime + ';base64,' + await f.async('base64'));
        }
        return { text: lines.join('\n'), images: dataUris, tables: tables, skipped: skipped };
    }

    function restoreImages(html, images, tables) {
        html = html || '';
        if (tables && tables.length) html = html.replace(/§[Tt][Bb][Ll](\d+)§/g, function (_, n) { return tables[parseInt(n, 10)] || ''; });
        return html.replace(/§[IİıiI][Mm][Gg](\d+)§/g, function (_, n) {
            var u = images && images[parseInt(n, 10)];
            return u ? '<img src="' + u + '" style="max-width:100%;height:auto;vertical-align:middle;">' : '';
        }).replace(/§[Tt][Bb][Ll]\d+§/g, '');
    }

    // Tarayıcıda büyük resimleri küçültür (en uzun kenar en fazla maxDim) ve PNG/JPEG'den küçük olanı seçer
    function compressImage(uri, maxDim) {
        maxDim = maxDim || 1000;
        if (typeof document === 'undefined' || !/^data:image\/(png|jpe?g|bmp|webp)/i.test(uri)) return Promise.resolve(uri);
        return new Promise(function (resolve) {
            var im = new Image();
            im.onload = function () {
                try {
                    var w = im.naturalWidth, h = im.naturalHeight, k = Math.min(1, maxDim / Math.max(w, h));
                    var cv = document.createElement('canvas'); cv.width = Math.max(1, Math.round(w * k)); cv.height = Math.max(1, Math.round(h * k));
                    var cx = cv.getContext('2d'); cx.fillStyle = '#ffffff'; cx.fillRect(0, 0, cv.width, cv.height); cx.drawImage(im, 0, 0, cv.width, cv.height);
                    var png = cv.toDataURL('image/png'), jpg = cv.toDataURL('image/jpeg', 0.85);
                    var best = png.length <= jpg.length * 1.15 ? png : jpg;
                    resolve(best.length < uri.length ? best : uri);
                } catch (e) { resolve(uri); }
            };
            im.onerror = function () { resolve(uri); };
            im.src = uri;
        });
    }

    /* ---------- Yapay zekâ ile PDF okuma: sayfa görüntüsü → sorular; şekiller PDF'ten kesilir ---------- */
    function yzGuvenli(h) {
        h = String(h == null ? '' : h).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        h = h.replace(/&lt;(\/?)(sup|sub|u|b|i|br)\s*\/?&gt;/gi, function (_, k, t) { return '<' + k + t.toLowerCase() + '>'; });
        return h.replace(/\n/g, '<br>').replace(/^(\s|<br>)+|(\s|<br>)+$/g, '').trim();
    }
    function resimYukle(file) { return new Promise(function (ok, no) { var u = URL.createObjectURL(file), im = new Image(); im.onload = function () { ok(im); }; im.onerror = function () { no(new Error('Resim açılamadı')); }; im.src = u; }); }
    async function resimYZ(file, opts) { var im = await resimYukle(file); return pdfYZ({ numPages: 1, resim: im }, opts); }
    /* yazılı kâğıdı: yapay zekâ yanıtı → havuz sorusu (öncül, kök + alt maddeler, cevap) */
    function yaziliSoru(s, q, op) {
        var tur = String(s.tur || 'acik'), dolu = Object.keys(op).filter(function (k) { return op[k]; }).length;
        var md = Array.isArray(s.maddeler) ? s.maddeler.filter(function (x) { return String(x || '').trim(); }) : [];
        if (md.length) q.root += md.map(function (m, i) { return '<br>' + 'abcdefgh'.charAt(i) + ') ' + yzGuvenli(String(m).replace(/^\s*[a-hA-H][\)\.]\s*/, '')); }).join('');
        var cv = Array.isArray(s.cevap) ? s.cevap.join('\n') : String(s.cevap || '');
        q.kod = String(s.kod || '').replace(/[\[\]\s]/g, '').slice(0, 30); q.sinav = +s.sinav || 1;
        if ((tur === 'test' || dolu >= 3) && dolu >= 2) { q.type = 'multiple'; if (cv.trim()) q.cozum = yzGuvenli(cv); if (!/[A-E]/.test(q.correct)) q.correct = ''; }
        else { q.type = tur === 'bosluk' ? 'gap' : 'classic'; q.options = { A: yzGuvenli(cv) }; q.correct = ''; }
        if (!q.root && q.paragraph) { q.root = q.paragraph; q.paragraph = ''; }
        return q.root || q.paragraph || !q.no ? q : null;
    }
    function yaziliBirlestir(o, q) {
        if (q.paragraph) o.paragraph += (o.paragraph ? '<br>' : '') + q.paragraph;
        if (q.root) o.root += (o.root ? '<br>' : '') + q.root;
        if (o.type !== 'multiple' && q.options && q.options.A && !(o.options && o.options.A)) o.options = { A: q.options.A };
    }
    function yaziliSonuc(qs, anahtarlar) {
        // ayrı cevap anahtarı (kâğıt sırasıyla): {"1":"…"} — sınav sırasına göre eşleştir
        var an = anahtarlar.filter(function (a) { return a && Object.keys(a).length; });
        if (an.length) {
            var tum = {}; an.forEach(function (a) { Object.keys(a).forEach(function (k) { (tum[+k] = tum[+k] || []).push(a[k]); }); });
            var say = {}; qs.forEach(function (q) { var l = tum[q.no]; if (!l) return; var i = say[q.no] = (say[q.no] || 0); say[q.no]++; var v = l[Math.min(i, l.length - 1)]; if (!v) return;
                if (q.type === 'multiple') { var h = String(v).trim().toUpperCase().charAt(0); if (!q.correct && /[A-E]/.test(h)) q.correct = h; }
                else if (!q.options.A) q.options.A = yzGuvenli(v); });
        }
        var cnt = { multiple: 0, truefalse: 0, gap: 0, classic: 0 }, keyed = 0;
        qs.forEach(function (q) { cnt[q.type] = (cnt[q.type] || 0) + 1; if (q.type === 'multiple' ? q.correct : (q.options && q.options.A)) keyed++; delete q.no; });
        return { questions: qs, report: { keyed: keyed, missing: qs.length - keyed, total: qs.length, counts: cnt, yz: true } };
    }
    /* Word'den çıkarılmış metni yapay zekâyla soru soru ayır (resim/tablo işaretleri korunur) */
    async function metinYZ(metin, opts) {
        opts = opts || {}; var cfg = opts.cfg || (typeof window !== 'undefined' && window.OSC_PDF_YZ), prog = opts.onProgress || function () {};
        if (!cfg) throw new Error('Yapay zekâ kapalı');
        var satir = String(metin || '').split('\n'), parca = [], cur = '';
        satir.forEach(function (l) { if (cur.length > 7000 && /^\s*(?:soru\s*)?\d{1,2}\s*[\.\)\-]/i.test(l)) { parca.push(cur); cur = ''; } cur += l + '\n'; });
        if (cur.trim()) parca.push(cur);
        var sonuc = [], hazir = 0;
        var gonder = function (t, i) {
            var fd = new FormData(); fd.append('action', 'osc_pdf_yz'); fd.append('nonce', cfg.n); fd.append('sayfa', i + 1); fd.append('mod', 'yazili'); fd.append('metin', t);
            return fetch(cfg.u, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (!j || !j.success) throw new Error((j && j.data) || 'Yapay zekâ okuyamadı'); hazir++; prog('Yapay zekâ belgeyi okuyor: ' + hazir + '/' + parca.length); return j.data; });
        };
        prog('Yapay zekâ belgeyi okuyor: 0/' + parca.length);
        for (var i = 0; i < parca.length; i += 3) { var grup = await Promise.all(parca.slice(i, i + 3).map(function (t, k) { return gonder(t, i + k); })); sonuc = sonuc.concat(grup); }
        var qs = [];
        sonuc.forEach(function (r) { (r.sorular || []).forEach(function (s) {
            if (!s || typeof s !== 'object') return;
            var sec = s.secenekler && typeof s.secenekler === 'object' ? s.secenekler : {}, op = {};
            ['A', 'B', 'C', 'D', 'E'].forEach(function (h) { op[h] = yzGuvenli(sec[h] || ''); });
            var q = { no: +s.no || 0, type: 'multiple', paragraph: yzGuvenli(s.oncul), root: yzGuvenli(s.kok), options: op, correct: String(s.dogru || '').trim().toUpperCase().charAt(0) };
            var yq = yaziliSoru(s, q, op); if (!yq) return; if (!yq.no && qs.length) { yaziliBirlestir(qs[qs.length - 1], yq); return; } qs.push(yq);
        }); });
        return yaziliSonuc(qs, sonuc.map(function (r) { return r.anahtar || {}; }));
    }
    async function pdfYZ(pdf, opts) {
        opts = opts || {}; var cfg = opts.cfg || (typeof window !== 'undefined' && window.OSC_PDF_YZ), OPS = opts.OPS || null, prog = opts.onProgress || function () {};
        if (!cfg) throw new Error('Yapay zekâ kapalı');
        var sayfalar = [], hazir = 0;
        async function sayfaHazirla(no) {
            if (pdf.resim) {   // tek bir soru fotoğrafı / taranmış sayfa
                var im = pdf.resim, k0 = Math.min(1, 1600 / (im.naturalWidth || im.width)), c0 = document.createElement('canvas');
                c0.width = Math.round((im.naturalWidth || im.width) * k0); c0.height = Math.round((im.naturalHeight || im.height) * k0);
                var x0 = c0.getContext('2d'); x0.fillStyle = '#fff'; x0.fillRect(0, 0, c0.width, c0.height); x0.drawImage(im, 0, 0, c0.width, c0.height);
                return { no: no, cv: c0, figs: [], vp: { convertToViewportPoint: function (x, y) { return [x, y]; } } };
            }
            var pg = await pdf.getPage(no), vp1 = pg.getViewport({ scale: 1 }), sc = Math.min(2.4, 1500 / vp1.width), vp = pg.getViewport({ scale: sc });
            var cv = document.createElement('canvas'); cv.width = Math.ceil(vp.width); cv.height = Math.ceil(vp.height);
            var cx = cv.getContext('2d'); cx.fillStyle = '#fff'; cx.fillRect(0, 0, cv.width, cv.height);
            var t = pg.render({ canvasContext: cx, canvas: cv, viewport: vp, annotationMode: 0 }); await (t.promise || t);
            var figs = [];
            if (OPS) { try { var ct = await pg.getTextContent(), ol = await pg.getOperatorList({ annotationMode: 0 }); figs = findFigures(ol, cleanItems(ct.items), vp1.width, vp1.height, OPS).filter(function (f) { return f.kind === 'img'; }); } catch (e) { figs = []; } }
            return { no: no, pg: pg, vp: vp, vp1: vp1, cv: cv, figs: figs };
        }
        function sor(sp) {
            var fd = new FormData(); fd.append('action', 'osc_pdf_yz'); fd.append('nonce', cfg.n); fd.append('sayfa', sp.no); fd.append('gorsel', sp.cv.toDataURL('image/jpeg', 0.82)); if (opts.mod) fd.append('mod', opts.mod);
            var dene = function (k) {
                return fetch(cfg.u, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (!j || !j.success) throw new Error((j && j.data) || 'Yapay zekâ okuyamadı'); return j.data; })
                    .catch(function (e) { if (k > 0 && !/sınır|Yetki|anahtar/i.test(e.message)) return dene(k - 1); throw e; });
            };
            return dene(1);
        }
        // görüntüde mürekkep (beyaz olmayan piksel) var mı
        function murekkep(id, W, x0, y0, x1, y1) { for (var y = y0; y < y1; y++) for (var x = x0; x < x1; x++) { var i = (y * W + x) * 4; if (id[i] < 225 || id[i + 1] < 225 || id[i + 2] < 225) return true; } return false; }
        function kes(sp, kutu) {
            var W = sp.cv.width, H = sp.cv.height, k = kutu.map(function (v) { return Math.max(0, Math.min(1000, +v || 0)); });
            var b = { x0: k[0] / 1000 * W, y0: k[1] / 1000 * H, x1: k[2] / 1000 * W, y1: k[3] / 1000 * H };
            if (b.x1 - b.x0 < 8 || b.y1 - b.y0 < 8) return '';
            // PDF'ten bulunan çizim/resim alanlarıyla birleştir (yapay zekâ kutusu biraz dar kalmış olabilir)
            sp.figs.forEach(function (f) {
                var p1 = sp.vp.convertToViewportPoint(f.x0, f.y0), p2 = sp.vp.convertToViewportPoint(f.x1, f.y1);
                var g = { x0: Math.min(p1[0], p2[0]), y0: Math.min(p1[1], p2[1]), x1: Math.max(p1[0], p2[0]), y1: Math.max(p1[1], p2[1]) };
                var ix = Math.max(0, Math.min(b.x1, g.x1) - Math.max(b.x0, g.x0)), iy = Math.max(0, Math.min(b.y1, g.y1) - Math.max(b.y0, g.y0));
                if (ix * iy > 0.3 * (g.x1 - g.x0) * (g.y1 - g.y0)) { b.x0 = Math.min(b.x0, g.x0); b.y0 = Math.min(b.y0, g.y0); b.x1 = Math.max(b.x1, g.x1); b.y1 = Math.max(b.y1, g.y1); }
            });
            b = { x0: Math.max(0, Math.floor(b.x0)), y0: Math.max(0, Math.floor(b.y0)), x1: Math.min(W, Math.ceil(b.x1)), y1: Math.min(H, Math.ceil(b.y1)) };
            var id = sp.cv.getContext('2d').getImageData(0, 0, W, H).data, lim = Math.round(W * 0.025), d;
            // kenarda kesilmiş çizgi/etiket varsa biraz genişlet
            for (d = 0; d < lim && b.y0 > 0 && murekkep(id, W, b.x0, b.y0 - 1, b.x1, b.y0); d++) b.y0--;
            for (d = 0; d < lim && b.y1 < H && murekkep(id, W, b.x0, b.y1, b.x1, b.y1 + 1); d++) b.y1++;
            for (d = 0; d < lim && b.x0 > 0 && murekkep(id, W, b.x0 - 1, b.y0, b.x0, b.y1); d++) b.x0--;
            for (d = 0; d < lim && b.x1 < W && murekkep(id, W, b.x1, b.y0, b.x1 + 1, b.y1); d++) b.x1++;
            // boş kenarları kırp
            while (b.y1 - b.y0 > 6 && !murekkep(id, W, b.x0, b.y0, b.x1, b.y0 + 1)) b.y0++;
            while (b.y1 - b.y0 > 6 && !murekkep(id, W, b.x0, b.y1 - 1, b.x1, b.y1)) b.y1--;
            while (b.x1 - b.x0 > 6 && !murekkep(id, W, b.x0, b.y0, b.x0 + 1, b.y1)) b.x0++;
            while (b.x1 - b.x0 > 6 && !murekkep(id, W, b.x1 - 1, b.y0, b.x1, b.y1)) b.x1--;
            var p = 8, x = Math.max(0, b.x0 - p), y = Math.max(0, b.y0 - p), w = Math.min(W, b.x1 + p) - x, h = Math.min(H, b.y1 + p) - y;
            if (w < 12 || h < 12) return '';
            var kk = w > 1000 ? 1000 / w : 1, o = document.createElement('canvas'); o.width = Math.round(w * kk); o.height = Math.round(h * kk);
            var oc = o.getContext('2d'); oc.fillStyle = '#fff'; oc.fillRect(0, 0, o.width, o.height); oc.drawImage(sp.cv, x, y, w, h, 0, 0, o.width, o.height);
            var png = o.toDataURL('image/png'), jpg = o.toDataURL('image/jpeg', 0.88);
            return png.length <= jpg.length * 1.15 ? png : jpg;
        }
        var N = pdf.numPages, sonuc = new Array(N), sira = 0, hata = null;
        async function isci() {
            while (sira < N && !hata) {
                var no = ++sira, sp;
                try { sp = await sayfaHazirla(no); sp.yz = await sor(sp); sonuc[no - 1] = sp; }
                catch (e) { hata = e; break; }
                hazir++; prog('Yapay zekâ sayfaları okuyor: ' + hazir + '/' + N);
            }
        }
        prog('Yapay zekâ sayfaları okuyor: 0/' + N);
        await Promise.all([isci(), isci(), isci()]);
        if (hata) throw hata;
        var qs = [], anahtar = {};
        sonuc.forEach(function (sp) {
            Object.keys(sp.yz.anahtar || {}).forEach(function (k) { var v = String(sp.yz.anahtar[k] || '').trim().toUpperCase().charAt(0); if (/[A-E]/.test(v)) anahtar[+k] = v; });
            (sp.yz.sorular || []).forEach(function (s) {
                if (!s || typeof s !== 'object') return;
                var resim = { oncul: [], kok: [], A: [], B: [], C: [], D: [], E: [] };
                (Array.isArray(s.sekiller) ? s.sekiller : []).forEach(function (f) { if (!f || !Array.isArray(f.kutu) || f.kutu.length !== 4) return; var u = kes(sp, f.kutu); if (!u) return; var yer = resim[f.yer] ? f.yer : 'oncul'; resim[yer].push('<img src="' + u + '" alt="">'); });
                var sec = s.secenekler && typeof s.secenekler === 'object' ? s.secenekler : {}, op = {};
                ['A', 'B', 'C', 'D', 'E'].forEach(function (h) { var t = yzGuvenli(sec[h] || ''); op[h] = (resim[h].length ? resim[h].join('') + (t ? '<br>' + t : '') : t); });
                var q = { no: +s.no || 0, type: 'multiple', paragraph: resim.oncul.join('<br>') + (resim.oncul.length && s.oncul ? '<br>' : '') + yzGuvenli(s.oncul), root: yzGuvenli(s.kok) + (resim.kok.length ? '<br>' + resim.kok.join('<br>') : ''), options: op, correct: String(s.dogru || '').trim().toUpperCase().charAt(0) };
                if (opts.mod === 'yazili') { var yq = yaziliSoru(s, q, op); if (!yq) return; if (!yq.no && qs.length) { yaziliBirlestir(qs[qs.length - 1], yq); return; } qs.push(yq); return; }
                if (!/[A-E]/.test(q.correct)) q.correct = '';
                var dolu = Object.keys(op).filter(function (k) { return op[k]; }).length;
                if (!q.no && qs.length) {   // önceki sayfadan devam eden soru
                    var o = qs[qs.length - 1];
                    if (q.paragraph) o.paragraph += (o.paragraph ? '<br>' : '') + q.paragraph;
                    if (q.root) o.root += (o.root ? '<br>' : '') + q.root;
                    Object.keys(op).forEach(function (k) { if (op[k] && !o.options[k]) o.options[k] = op[k]; });
                    return;
                }
                if (dolu < 2) q.type = 'classic';
                if (!q.root && q.paragraph) { q.root = q.paragraph; q.paragraph = ''; }
                if (q.root || q.paragraph) qs.push(q);
            });
        });
        if (opts.mod === 'yazili') return yaziliSonuc(qs, sonuc.map(function (sp) { return sp.yz.anahtar || {}; }));
        qs.forEach(function (q) { if (!q.correct && anahtar[q.no]) q.correct = anahtar[q.no]; });
        var keyed = qs.filter(function (q) { return q.correct; }).length;
        var cnt = { multiple: 0, truefalse: 0, gap: 0, classic: 0 }; qs.forEach(function (q) { cnt[q.type] = (cnt[q.type] || 0) + 1; delete q.no; });
        return { questions: qs, report: { keyed: keyed, missing: qs.filter(function (q) { return q.type === 'multiple' && !q.correct; }).length, total: qs.length, counts: cnt, yz: true } };
    }

    return { analyzeOps: analyzeOps, layoutPage: layoutPage, findFigures: findFigures, pdfToText: pdfToText, pdfYZ: pdfYZ, metinYZ: metinYZ, resimYZ: resimYZ, docxToText: docxToText, restoreImages: restoreImages, compressImage: compressImage };
})();


            jQuery(document).ready(function($){
                let questions = JSON.parse($('#osc_data').val()||'[]');
                questions.forEach(q => { if(!q.root && q.text) q.root = q.text; if(!q.paragraph) q.paragraph = ""; });
                
                function toSentenceCase(str) { if(!str) return ''; if (str.length > 3 && str === str.toLocaleUpperCase('tr-TR')) { const s = str.toLocaleLowerCase('tr-TR'); return s.charAt(0).toLocaleUpperCase('tr-TR') + s.slice(1); } return str; }
                
                function oscPrev(v, small){ v = String(v || ''); if (!/<img|<table/i.test(v)) return ''; return '<div class="osc-prev' + (small ? ' osc-prev-s' : '') + '">' + v + '</div>'; }
                function esc(v){ return String(v==null?'':v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
                window.render = function() {
                    const list = $('#osc-q-list'); list.empty();
                    questions.forEach((q, i) => {
                        let inputs = '';
                        if(q.type === 'classic') { inputs = `<div style="background:#f3e8ff; padding:10px;"><label><b>Model Cevap (Açık Uçlu):</b></label><textarea class="osc-txt" style="height:90px; font-weight:normal !important;" oninput="setOpt(${i},'A',this.value)">${esc(q.options.A)}</textarea></div>`; }
                        else if(q.type === 'gap') { inputs = `<div style="background:#fff8e1; padding:10px;"><label>Doğru Cevap:</label> <input type="text" class="osc-inp" value="${esc(q.options.A)}" oninput="setOpt(${i},'A',this.value)"></div>`; } 
                        else if(q.type === 'truefalse') { inputs=`<div style="background:#e3f2fd; padding:10px; display:flex; gap:20px;"><label><input type="radio" name="r${i}" ${q.correct==='A'?'checked':''} onclick="setCor(${i},'A')"> Doğru</label><label><input type="radio" name="r${i}" ${q.correct==='B'?'checked':''} onclick="setCor(${i},'B')"> Yanlış</label></div>`; q.options={A:'Doğru',B:'Yanlış'}; } 
                        else { ['A','B','C','D','E'].forEach(k=>{ inputs+=`<div class="osc-row"><input type="radio" name="r${i}" ${q.correct===k?'checked':''} onclick="setCor(${i},'${k}')" style="margin-right:8px"><b style="width:25px">${k})</b><input type="text" class="osc-inp" value="${esc(q.options[k])}" oninput="setOpt(${i},'${k}',this.value)">${oscPrev(q.options[k], true)}</div>`; }); }
                        list.append(`<div class="osc-q-item"><div style="display:flex;justify-content:space-between; margin-bottom:10px;"><strong>Soru ${i+1}</strong> <div><select class="osc-type-sel" onchange="setType(${i},this.value)"><option value="multiple" ${q.type==='multiple'?'selected':''}>Çoktan Seçmeli</option><option value="truefalse" ${q.type==='truefalse'?'selected':''}>D / Y</option><option value="gap" ${q.type==='gap'?'selected':''}>Boşluk</option><option value="classic" ${q.type==='classic'?'selected':''}>Klasik (Açık Uçlu)</option></select> <button type="button" class="button" onclick="delQ(${i})" style="color:red;">Sil</button></div></div><textarea class="osc-txt" style="height:50px; margin-bottom:5px; font-weight:normal !important;" oninput="setField(${i},'paragraph',this.value)" placeholder="Paragraf (Opsiyonel)">${esc(q.paragraph)}</textarea>${oscPrev(q.paragraph)}<textarea class="osc-txt" style="height:50px; margin-bottom:10px;" oninput="setField(${i},'root',this.value)" placeholder="Soru Kökü (Kalın Görünür)">${esc(q.root)}</textarea>${oscPrev(q.root)}<div>${inputs}</div></div>`);
                    });
                    $('#osc_data').val(JSON.stringify(questions));
                }
                
                window.addQ=()=>{questions.push({paragraph:'',root:'',type:'multiple',options:{A:'',B:'',C:'',D:'',E:''},correct:'A'});render();};
                window.setType=(i,t)=>{questions[i].type=t; render();}; window.setField=(i,f,v)=>{questions[i][f]=v;sync();}; window.setOpt=(i,k,v)=>{questions[i].options[k]=v;sync();}; window.setCor=(i,k)=>{questions[i].correct=k;render();}; window.delQ=(i)=>{if(confirm('Sil?'))questions.splice(i,1);render();}; window.sync=()=>$('#osc_data').val(JSON.stringify(questions));
                
                // PDF kütüphanesi: resim/tablo çıkarımı pdf.js 6.2 ile test edildi; yüklenemezse sayfadaki 2.16 kullanılır
                async function oscGetPdfLib() {
                    if (window.__oscPdfLib) return window.__oscPdfLib;
                    const base = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@6.2.108/legacy/build/';
                    try {
                        const m = await import(base + 'pdf.min.mjs');
                        m.GlobalWorkerOptions.workerSrc = base + 'pdf.worker.min.mjs';
                        window.__oscPdfLib = m; return m;
                    } catch (e) { console.warn('OSC: pdf.js 6.2 yüklenemedi, 2.16 kullanılıyor.', e); }
                    if (typeof pdfjsLib !== 'undefined') { window.__oscPdfLib = pdfjsLib; return pdfjsLib; }
                    throw new Error('PDF kütüphanesi yüklenemedi (internet bağlantısını kontrol edin).');
                }
                // Resmi küçültüp WordPress ortam kütüphanesine yükler; başarısız olursa resim soruya gömülü kalır
                async function oscUploadImage(dataUri) {
                    const small = await OSCExtract.compressImage(dataUri, 1000);
                    const nonceEl = document.getElementById('osc_img_nonce');
                    if (!nonceEl || typeof ajaxurl === 'undefined') return { url: small, ok: false };
                    try {
                        const fd = new FormData();
                        fd.append('action', 'osc_upload_image'); fd.append('nonce', nonceEl.value);
                        fd.append('post_id', $('#post_ID').val() || '0'); fd.append('data', small);
                        const r = await fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' });
                        const j = await r.json();
                        if (j && j.success && j.data && j.data.url) return { url: j.data.url, ok: true };
                    } catch (e) { console.warn('OSC: resim yüklenemedi', e); }
                    return { url: small, ok: false };
                }

                // OCR yardımcıları
                async function ocrPdfPage(page) {
                    if (typeof Tesseract === 'undefined') throw new Error('OCR kütüphanesi yüklenemedi.');
                    const vp = page.getViewport({scale: 2});
                    const cv = document.createElement('canvas'); cv.width = vp.width; cv.height = vp.height;
                    await page.render({canvasContext: cv.getContext('2d'), canvas: cv, viewport: vp}).promise;
                    const r = await Tesseract.recognize(cv, 'tur');
                    return r.data.text;
                }

                $('#osc-file-in').change(async function(e){
                    const input = this;
                    const file = input.files && input.files[0]; if(!file) return;
                    const status = $('#osc-status'); status.css('color','#2271b1').text('Dosya analiz ediliyor...');
                    try {
                        const fname = (file.name || '').toLowerCase(), ftype = (file.type || '').toLowerCase();
                        let rawText = '', images = [], tables = [], skippedImgs = 0, pdfStats = null; let yzRes = null;

                        if (/\.pdf$/.test(fname) || ftype.indexOf('pdf') > -1) {
                            status.text('PDF kütüphanesi hazırlanıyor...');
                            const lib = await oscGetPdfLib();
                            const buf = await file.arrayBuffer();
                            const pdf = await lib.getDocument({data: new Uint8Array(buf)}).promise;
                            if (window.OSC_PDF_YZ && OSCExtract.pdfYZ && !$('#osc-yz-kapali').is(':checked')) {
                                try { yzRes = await OSCExtract.pdfYZ(pdf, { OPS: lib.OPS, onProgress: m => status.text('🤖 ' + m) }); if (!yzRes.questions.length) yzRes = null; }
                                catch (e) { console.warn(e); yzRes = null; status.text('Yapay zekâ okuyamadı (' + e.message + '), standart okuma deneniyor...'); }
                            }
                            if (!yzRes) {
                            const pr = await OSCExtract.pdfToText(pdf, m => status.text(m), ocrPdfPage, { OPS: lib.OPS });
                            rawText = pr.ocr ? OSCImport.reflowPlain(pr.text) : pr.text;
                            images = pr.images || []; pdfStats = pr.stats || null;
                            }
                        } else if (/\.docx$/.test(fname) || ftype.indexOf('wordprocessingml') > -1) {
                            if (typeof JSZip === 'undefined') throw new Error('Word okuyucu (JSZip) yüklenemedi (internet bağlantısını kontrol edin).');
                            status.text('Word okunuyor...');
                            const dr = await OSCExtract.docxToText(await file.arrayBuffer());
                            rawText = dr.text; images = dr.images; tables = dr.tables || []; skippedImgs = dr.skipped || 0;
                        } else if (/\.doc$/.test(fname)) {
                            status.css('color','#b32d2e').text('Eski .doc biçimi okunamıyor. Lütfen Word\'de "Farklı Kaydet > .docx" yapıp yükleyin.'); input.value = ''; return;
                        } else if (/\.txt$/.test(fname) || ftype === 'text/plain') {
                            rawText = await file.text();
                        } else if (ftype.indexOf('image/') === 0 || /\.(png|jpe?g|webp|bmp|gif)$/.test(fname)) {
                            const imgType = ftype.indexOf('image/') === 0 ? ftype : 'image/jpeg';
                            if (window.OSC_PDF_YZ && OSCExtract.resimYZ) {
                                try { yzRes = await OSCExtract.resimYZ(file, { onProgress: m => status.text('🤖 Yapay zekâ resmi okuyor...') }); if (!yzRes.questions.length) yzRes = null; } catch (e) { console.warn(e); yzRes = null; }
                            }
                            const apiKey = yzRes ? '' : '';
                            
                            if(apiKey) {
                                status.text('🤖 Yapay Zeka resmi inceliyor, lütfen bekleyin...');
                                try {
                                    // Resmi Base64 formatına çevirme (Yapay zekanın anlayacağı dile)
                                    const base64Img = await new Promise((resolve, reject) => {
                                        const reader = new FileReader();
                                        reader.readAsDataURL(file);
                                        reader.onload = () => resolve(reader.result.split(',')[1]);
                                        reader.onerror = error => reject(error);
                                    });

                                    // Gemini AI'a Talimat Gönderme
                                    const response = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=${apiKey}`, {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({
                                            contents: [{
                                                parts: [
                                                    {text: "Sen bir test okuma asistanısın. Bu resimdeki tüm soruları oku. Şıkları A, B, C, D, E olarak ayır. Sorunun bir paragrafı veya öncülü varsa 'paragraph' kısmına yaz, asıl soru cümlesini 'root' kısmına yaz. Şıkların başındaki A), B) gibi harfleri silerek sadece şıkkın metnini yaz. YALNIZCA şu formatta geçerli bir JSON dizisi (array) döndür, kod bloğu veya markdown (```json) kullanma: [{\"paragraph\": \"...\", \"root\": \"...\", \"type\": \"multiple\", \"options\": {\"A\": \"...\", \"B\": \"...\", \"C\": \"...\", \"D\": \"...\", \"E\": \"...\"}, \"correct\": \"A\"}]"},
                                                    {inline_data: { mime_type: imgType, data: base64Img }}
                                                ]
                                            }]
                                        })
                                    });
                                    
                                    const aiData = await response.json();
                                    if(aiData.error) throw new Error(aiData.error.message);
                                    
                                    let aiText = aiData.candidates[0].content.parts[0].text;
                                    aiText = aiText.replace(/```json/g, '').replace(/```/g, '').trim(); // Temizleme
                                    
                                    const parsedQs = JSON.parse(aiText);
                                    parsedQs.forEach(q => questions.push(q));
                                    
                                    status.text('✅ ' + parsedQs.length + ' soru yapay zeka ile kusursuz eklendi!');
                                    render();
                                    input.value = ''; return; // AI başarılı olduysa döngüden çık, aşağıdaki Tesseract kodları çalışmasın
                                } catch(e) {
                                    console.error(e);
                                    status.text('Yapay zeka hatası, standart OCR (Tesseract) deneniyor...');
                                }
                            } else {
                                if (!yzRes) status.text('Standart OCR çalışıyor...');
                            }

                            // Eğer API Key yoksa veya hata verirse yedek olarak Tesseract çalışsın
                            if (!yzRes) {
                            if (typeof Tesseract === 'undefined') throw new Error('OCR kütüphanesi yüklenemedi.');
                            const result = await Tesseract.recognize(file, 'tur');
                            rawText = OSCImport.reflowPlain(result.data.text);
                            }
                        } else {
                            status.css('color','#b32d2e').text('Desteklenmeyen format. PDF, DOCX, TXT veya resim yükleyin.'); input.value = ''; return;
                        }

                        const res = yzRes || OSCImport.parse(rawText);
                        const rep = res.report;
                        if (!res.questions.length) {
                            console.log('OSC içe aktarma - çıkarılan ham metin:\n', rawText);
                            status.css('color','#b32d2e').text('Soru bulunamadı. Soru numaraları satır başında "1." veya "1)" biçiminde olmalı. (Ham metin tarayıcı konsoluna yazıldı.)');
                            input.value = ''; return;
                        }
                        // Resim ve tabloları sorulara yerleştir
                        res.questions.forEach(q => {
                            q.paragraph = OSCExtract.restoreImages(q.paragraph, images, tables);
                            q.root = OSCExtract.restoreImages(q.root, images, tables);
                            Object.keys(q.options).forEach(k => { q.options[k] = OSCExtract.restoreImages(q.options[k], images, tables); });
                        });
                        // Kullanılan resimleri küçült ve ortam kütüphanesine yükle (veritabanı şişmesin)
                        const used = [];
                        const scan = h => String(h || '').replace(/src="(data:image\/[^"]+)"/g, (m, u) => { if (used.indexOf(u) < 0) used.push(u); return m; });
                        res.questions.forEach(q => { scan(q.paragraph); scan(q.root); Object.keys(q.options).forEach(k => scan(q.options[k])); });
                        let upOK = 0, upFail = 0; const urlMap = {};
                        for (let i = 0; i < used.length; i++) {
                            status.text('Resimler ortam kütüphanesine yükleniyor: ' + (i + 1) + '/' + used.length);
                            const r = await oscUploadImage(used[i]); urlMap[used[i]] = r.url; if (r.ok) upOK++; else upFail++;
                        }
                        const swap = h => String(h || '').replace(/src="(data:image\/[^"]+)"/g, (m, u) => 'src="' + (urlMap[u] || u) + '"');
                        let imgCount = 0, tblCount = 0;
                        res.questions.forEach(q => {
                            q.paragraph = swap(q.paragraph); q.root = swap(q.root);
                            Object.keys(q.options).forEach(k => { q.options[k] = swap(q.options[k]); });
                            const all = q.paragraph + q.root + Object.values(q.options).join('');
                            imgCount += (all.match(/<img /g) || []).length; tblCount += (all.match(/<table /g) || []).length;
                            questions.push(q);
                        });

                        const parts = [];
                        if (rep.counts.multiple) parts.push(rep.counts.multiple + ' çoktan seçmeli');
                        if (rep.counts.truefalse) parts.push(rep.counts.truefalse + ' doğru/yanlış');
                        if (rep.counts.gap) parts.push(rep.counts.gap + ' boşluk doldurma');
                        if (rep.counts.classic) parts.push(rep.counts.classic + ' klasik (açık uçlu)');
                        let msg = '✅ ' + rep.total + ' soru eklendi (' + parts.join(', ') + ') • ' + rep.keyed + ' sorunun cevabı otomatik işlendi';
                        if (imgCount || tblCount) msg += ' • 🖼 ' + (imgCount ? imgCount + ' resim' : '') + (imgCount && tblCount ? ', ' : '') + (tblCount ? tblCount + ' tablo' : '') + ' sorulara yerleştirildi';
                        if (pdfStats && pdfStats.tables) msg += ' (PDF tabloları resim olarak alındı)';
                        if (upFail) msg += ' • ⚠ ' + upFail + ' resim ortam kütüphanesine yüklenemedi, soruya gömülü kaydedildi';
                        if (skippedImgs) msg += ' • ⚠ ' + skippedImgs + ' resim Word\'ün eski çizim biçiminde (EMF/WMF) olduğu için alınamadı; Word\'de resmi PNG olarak yeniden ekleyin';
                        if (rep.missing) msg += ' • ⚠ ' + rep.missing + ' soruda cevap bulunamadı (çoktan seçmelilerde A kaldı, elle işaretleyin)';
                        status.css('color', (rep.missing === 0 && !upFail && !skippedImgs) ? '#00a32a' : '#b26200').text(msg);
                        render();
                    } catch(err) { console.error(err); status.css('color','#b32d2e').text('Hata: ' + err.message); }
                    input.value = ''; // aynı dosyayı tekrar seçince de çalışsın
                });
                render();
            });
            </script>
            <?php
        }
                public function save_data($pid) {
            if(!isset($_POST['osc_nonce']) || !wp_verify_nonce($_POST['osc_nonce'],'osc_save')) return;
            if(isset($_POST['osc_data'])) update_post_meta($pid, '_osc_data', json_decode(stripslashes($_POST['osc_data']),true));
            if(isset($_POST['osc_settings'])) update_post_meta($pid, '_osc_settings', $_POST['osc_settings']);
        }

        // --- İÇE AKTARILAN SORU GÖRSELLERİNİ ORTAM KÜTÜPHANESİNE YÜKLE ---
        public function upload_image_ajax() {
            check_ajax_referer('osc_img_upload', 'nonce');
            if (!current_user_can('upload_files')) wp_send_json_error('Yetkiniz yok.', 403);
            $data = isset($_POST['data']) ? wp_unslash($_POST['data']) : '';
            if (!preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $data, $m)) wp_send_json_error('Geçersiz resim verisi.');
            $bin = base64_decode(substr($data, strpos($data, ',') + 1), true);
            if ($bin === false || strlen($bin) < 20 || strlen($bin) > 10 * 1024 * 1024) wp_send_json_error('Resim okunamadı.');
            $info = function_exists('getimagesizefromstring') ? @getimagesizefromstring($bin) : false;
            if (!$info || empty($info['mime']) || !in_array($info['mime'], array('image/png', 'image/jpeg', 'image/gif', 'image/webp'), true)) wp_send_json_error('Dosya bir resim değil.');
            $ext = array('image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp');
            $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            if ($post_id && !current_user_can('edit_post', $post_id)) $post_id = 0;
            $name = 'soru-gorseli-' . ($post_id ? $post_id . '-' : '') . wp_generate_password(8, false, false) . '.' . $ext[$info['mime']];
            $up = wp_upload_bits($name, null, $bin);
            if (!empty($up['error'])) wp_send_json_error($up['error']);
            $att_id = wp_insert_attachment(array('post_mime_type' => $info['mime'], 'post_title' => 'Soru görseli', 'post_content' => '', 'post_status' => 'inherit'), $up['file'], $post_id);
            if ($att_id && !is_wp_error($att_id)) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                wp_update_attachment_metadata($att_id, wp_generate_attachment_metadata($att_id, $up['file']));
            }
            wp_send_json_success(array('url' => $up['url'], 'id' => is_wp_error($att_id) ? 0 : (int) $att_id));
        }

        // --- VERİTABANLI LİDERLİK TABLOSU İŞLEMLERİ ---
        public function save_score_ajax() {
            check_ajax_referer('osc_quiz_nonce', 'nonce');
            $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
            $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
            $score = isset($_POST['score']) ? intval($_POST['score']) : 0;
            
            if($quiz_id && $name) {
                // Her skoru ayrı bir satır olarak kaydet (Çakışmaları önler)
                $entry = array('name' => $name, 'score' => $score, 'date' => current_time('d.m.Y H:i'));
                add_post_meta($quiz_id, '_osc_lb_entry', $entry);
            }
            
            // Tüm skorları çek, sırala ve ilk 10'u döndür
            $entries = get_post_meta($quiz_id, '_osc_lb_entry', false);
            if(!is_array($entries)) $entries = array();
            
            usort($entries, function($a, $b) { return $b['score'] - $a['score']; });
            
            // Puanı aynı olanları tarihe göre filtreleme (Geliştirilebilir)
          $top10 = array_slice($entries, 0, 10);
            
            wp_send_json_success($top10);
        }
        // --- HAFTALIK İNDİRME VE ÇÖZÜLME SAYAÇLARI (ARKA PLAN) ---
      public function track_solve_ajax() {
            // Skoru ham teste değil, kısa kodun eklendiği normal yazıya (parent_id) kaydeder
            $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
            if($parent_id) {
                $week_key = '_haftalik_cozulme_' . date('W_Y'); 
                $count = (int)get_post_meta($parent_id, $week_key, true);
                update_post_meta($parent_id, $week_key, $count + 1);
            }
            wp_send_json_success();
        }

        public function track_download_ajax() {
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            if($post_id) {
                $week_key = '_haftalik_indirme_' . date('W_Y');
                $count = (int)get_post_meta($post_id, $week_key, true);
                update_post_meta($post_id, $week_key, $count + 1);
            }
            wp_send_json_success();
        }

  // --- SADECE TESTİ EKRANA BASAN İFRAME MODU FONKSİYONU ---
        public function handle_embed_request() {
            // Şifreyi ve Test ID'sini URL'den alıyoruz
            if (isset($_GET['osc_embed']) && is_numeric($_GET['osc_embed'])) {
                $quiz_id = intval($_GET['osc_embed']);
                
                // Eğer bu ID gerçekten bir teste aitse, temiz bir sayfa oluştur
                if (get_post_type($quiz_id) === 'osc_quiz') {
                    ?>
                    <!DOCTYPE html>
                    <html lang="tr">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title><?php echo get_the_title($quiz_id); ?> - Soruİndir</title>
                        <link rel="stylesheet" href="<?php echo includes_url('css/dashicons.min.css'); ?>" type="text/css" media="all" />
                        <style>
                            /* Temanın arka planını sıfırla, iframe içine tam oturt */
                            body { margin: 0; padding: 0; background: transparent; font-family: 'Segoe UI', Roboto, sans-serif; }
                            /* Çerçeveyi kaldır ki dışarıdaki iframe'in kendi güzel köşeleriyle birleşsin */
                            .osc-wrapper { margin: 0 !important; border: none !important; box-shadow: none !important; border-radius: 0 !important; }
                        </style>
                    </head>
                    <body>
                        <?php 
                        // Doğrudan o Test ID'sini ekrana bas
                        echo $this->get_quiz_html($quiz_id); 
                        ?>
                    </body>
                    </html>
                    <?php
                    exit; 
                }
            }
        }
    }
}
if (class_exists('OnlineSoruCozProV20')) { $osc_instance = new OnlineSoruCozProV20(); }