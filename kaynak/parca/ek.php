/* ---------------- üyelik (öğretmen / öğrenci) ---------------- */
    private $uy_hata = '';
    private $uy_bilgi = '';
    private function git_url($rol) {
        $git = isset($_REQUEST['git']) ? esc_url_raw(wp_unslash($_REQUEST['git'])) : '';
        if ($git && wp_validate_redirect($git, '')) return $git;
        $u = self::sayfa_url($rol === 'ogrenci' ? 'osc_sp_ogrenci_sayfa' : 'osc_sp_panel_sayfa');
        return $u ? $u : home_url('/');
    }
    /* ---------- spam koruması yardımcıları ---------- */
    private function ip() { return isset($_SERVER['REMOTE_ADDR']) ? preg_replace('/[^0-9a-f:.]/i', '', $_SERVER['REMOTE_ADDR']) : ''; }
    public static function rc_tip() { $t = get_option('osc_rc_tip', 'kapali'); return (get_option('osc_rc_site') && get_option('osc_rc_gizli') && in_array($t, array('v2', 'v3'), true)) ? $t : ''; }
    private function zaman_alani() { $t = time(); return '<input type="hidden" name="osc_t" value="' . $t . '.' . substr(wp_hash('osc_t' . $t), 0, 12) . '">'; }
    private function zaman_gecerli($en_az = 2) {
        $v = isset($_POST['osc_t']) ? explode('.', (string) $_POST['osc_t']) : array();
        if (count($v) !== 2 || !hash_equals(substr(wp_hash('osc_t' . (int) $v[0]), 0, 12), $v[1])) return false;
        $gecen = time() - (int) $v[0];
        return $gecen >= $en_az && $gecen <= 4 * HOUR_IN_SECONDS;
    }
    /** reCAPTCHA doğrulaması; anahtarlar girilmemişse koruma devre dışıdır */
    public function rc_dogrula($eylem = '') {
        $tip = self::rc_tip(); if (!$tip) return true;
        $tok = isset($_POST['g-recaptcha-response']) ? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response'])) : '';
        if ($tok === '') return false;
        $r = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array('timeout' => 10, 'body' => array('secret' => get_option('osc_rc_gizli'), 'response' => $tok, 'remoteip' => $this->ip())));
        if (is_wp_error($r)) return true; // Google'a ulaşılamazsa kullanıcıyı kilitlemeyelim; diğer korumalar devrede
        $j = json_decode(wp_remote_retrieve_body($r), true);
        if (empty($j['success'])) return false;
        if ($tip === 'v3') { $esik = (float) get_option('osc_rc_esik', 0.5); if (isset($j['score']) && (float) $j['score'] < $esik) return false; if ($eylem && isset($j['action']) && $j['action'] !== $eylem) return false; }
        return true;
    }
    private function rc_alani($eylem) {
        $tip = self::rc_tip(); if (!$tip) return '';
        if ($tip === 'v2') return '<div class="g-recaptcha" data-sitekey="' . esc_attr(get_option('osc_rc_site')) . '"></div>';
        return '<input type="hidden" name="g-recaptcha-response" class="osc-rc3" data-eylem="' . esc_attr($eylem) . '">';
    }
    private static $gecici_alanlar = array('mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'sharklasers.com', '10minutemail.com', '10minutemail.net', 'tempmail.com', 'temp-mail.org', 'temp-mail.io', 'yopmail.com', 'yopmail.net', 'getnada.com', 'trashmail.com', 'trashmail.de', 'dispostable.com', 'maildrop.cc', 'mohmal.com', 'emailondeck.com', 'throwawaymail.com', 'fakeinbox.com', 'mintemail.com', 'mailnesia.com', 'spamgourmet.com', 'tempail.com', 'tempr.email', 'discard.email', 'mailcatch.com', 'moakt.com', 'emailfake.com', 'burnermail.io', 'inboxkitten.com', 'mail.tm', 'minuteinbox.com', 'tmpmail.org', 'tmail.ws', 'byom.de', 'spambox.us', 'trbvm.com', 'cuvox.de', 'armyspy.com', 'dayrep.com', 'einrot.com', 'fleckens.hu', 'gustr.com', 'jourrapide.com', 'rhyta.com', 'superrito.com', 'teleworm.us');
    private function eposta_sorunu($eposta) {
        $alan = strtolower(substr(strrchr($eposta, '@'), 1));
        if (get_option('osc_uye_gecici', '1') === '1' && in_array($alan, self::$gecici_alanlar, true)) return 'Geçici (tek kullanımlık) e-posta adresleriyle kayıt olunamaz.';
        if (get_option('osc_uye_mx', '1') === '1' && function_exists('checkdnsrr') && $alan && !checkdnsrr($alan, 'MX') && !checkdnsrr($alan, 'A') && checkdnsrr('gmail.com', 'MX')) return 'Bu e-posta adresinin alan adı bulunamadı. Lütfen kontrol edin.';
        return '';
    }
    private function kilitli($anahtar) { return (int) get_transient('osc_kilit_' . md5($anahtar)) >= 5; }
    private function hata_say($anahtar) { $k = 'osc_kilit_' . md5($anahtar); set_transient($k, (int) get_transient($k) + 1, 15 * MINUTE_IN_SECONDS); }
    /** wp-login.php dahil tüm girişlerde: art arda hatalı denemelerde 15 dk kilit + e-posta doğrulaması */
    public function giris_koruma($user, $login, $sifre) {
        if (empty($login) || empty($sifre)) return $user;
        $ip = $this->ip();
        if ($this->kilitli('ip' . $ip) || $this->kilitli('u' . strtolower($login))) return new WP_Error('osc_kilit', 'Çok fazla hatalı giriş denemesi yapıldı. Lütfen 15 dakika sonra tekrar deneyin.');
        return $user;
    }
    public function giris_hatali($login) { $this->hata_say('ip' . $this->ip()); $this->hata_say('u' . strtolower((string) $login)); }
    public function dogrulama_kontrol($user) {
        if ($user instanceof WP_User && get_user_meta($user->ID, 'osc_dogrulama', true)) return new WP_Error('osc_dogrulanmadi', 'E-posta adresiniz henüz doğrulanmadı. Size gönderdiğimiz e-postadaki bağlantıya tıklayın.');
        return $user;
    }
    public function dogrulama_link() {
        if (empty($_GET['osc_dogrula']) || empty($_GET['u'])) return;
        $uid = (int) $_GET['u']; $tok = sanitize_key($_GET['osc_dogrula']);
        $kayitli = get_user_meta($uid, 'osc_dogrulama', true);
        $hedef = self::sayfa_url('osc_sp_uye_sayfa');
        if ($kayitli && hash_equals($kayitli, $tok)) {
            delete_user_meta($uid, 'osc_dogrulama');
            wp_set_current_user($uid); wp_set_auth_cookie($uid, true, is_ssl());
            wp_safe_redirect($this->git_url(self::rol($uid))); exit;
        }
        wp_safe_redirect(add_query_arg('osc_mesaj', 'gecersiz', $hedef ? $hedef : home_url('/'))); exit;
    }
    /* wp-login.php kayıt formu da korunsun */
    public function wp_kayit_formu() { echo $this->rc_alani('kayit') . $this->zaman_alani() . '<input type="text" name="osc_web" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">'; $this->rc_betik(); }
    public function wp_kayit_hatalari($hata, $login, $eposta) {
        if (!empty($_POST['osc_web']) || !$this->zaman_gecerli()) $hata->add('osc_spam', 'Kayıt doğrulanamadı, lütfen tekrar deneyin.');
        elseif (!$this->rc_dogrula('kayit')) $hata->add('osc_rc', 'Lütfen “Ben robot değilim” doğrulamasını tamamlayın.');
        elseif ($e = $this->eposta_sorunu($eposta)) $hata->add('osc_eposta', $e);
        return $hata;
    }
    private function rc_betik() {
        $tip = self::rc_tip(); if (!$tip) return;
        if ($tip === 'v2') { echo '<script src="https://www.google.com/recaptcha/api.js?hl=tr" async defer></script>'; return; }
        $k = esc_attr(get_option('osc_rc_site'));
        echo '<script src="https://www.google.com/recaptcha/api.js?render=' . $k . '"></script><script>document.addEventListener("submit",function(e){var f=e.target,i=f.querySelector&&f.querySelector(".osc-rc3");if(!i||f.dataset.oscOk)return;e.preventDefault();grecaptcha.ready(function(){grecaptcha.execute("' . $k . '",{action:i.dataset.eylem||"giris"}).then(function(t){i.value=t;f.dataset.oscOk=1;f.submit();});});},true);</script>';
    }
    private function google_kullanici($cred) {
        $cid = get_option('osc_g_client_id'); if (!$cid || !$cred) return null;
        $r = wp_remote_get('https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($cred), array('timeout' => 10));
        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) return null;
        $j = json_decode(wp_remote_retrieve_body($r), true);
        if (!is_array($j) || ($j['aud'] ?? '') !== $cid || !in_array($j['iss'] ?? '', array('accounts.google.com', 'https://accounts.google.com'), true) || (int) ($j['exp'] ?? 0) < time() || !in_array((string) ($j['email_verified'] ?? ''), array('true', '1'), true) || empty($j['email'])) return null;
        return array('email' => sanitize_email($j['email']), 'ad' => sanitize_text_field($j['name'] ?? ''), 'sub' => sanitize_text_field($j['sub'] ?? ''));
    }
    private function kullanici_olustur($eposta, $ad, $sifre, $rol, $ek = array()) {
        $kok = sanitize_user(strtolower(remove_accents(strstr($eposta, '@', true))), true);
        if ($kok === '') $kok = 'uye';
        $login = $kok; $n = 1;
        while (username_exists($login)) $login = $kok . (++$n);
        $parca = explode(' ', $ad ? $ad : $kok);
        $soyad = count($parca) > 1 ? array_pop($parca) : '';
        $dr = get_option('default_role', 'subscriber');
        $id = wp_insert_user(array('user_login' => $login, 'user_email' => $eposta, 'user_pass' => $sifre, 'display_name' => $ad ? $ad : $kok, 'first_name' => implode(' ', $parca), 'last_name' => $soyad, 'role' => in_array($dr, array('administrator', 'editor'), true) ? 'subscriber' : $dr));
        if (is_wp_error($id)) return $id;
        update_user_meta($id, 'osc_rol', $rol);
        foreach ($ek as $k => $v) update_user_meta($id, $k, $v);
        return $id;
    }

    public function uyelik_islem() {
        if (empty($_POST['osc_uye_islem']) || $_SERVER['REQUEST_METHOD'] !== 'POST') return;
        if (!isset($_POST['osc_uye_nonce']) || !wp_verify_nonce($_POST['osc_uye_nonce'], 'osc_uye')) { $this->uy_hata = 'Sayfanın süresi doldu, lütfen tekrar deneyin.'; return; }
        $islem = sanitize_key($_POST['osc_uye_islem']);
        $ip = $this->ip();
        // Google ile giriş / kayıt
        if ($islem === 'google' || $islem === 'google_rol') {
            if ($islem === 'google') {
                $g = $this->google_kullanici(isset($_POST['credential']) ? sanitize_text_field(wp_unslash($_POST['credential'])) : '');
                if (!$g) { $this->uy_hata = 'Google ile giriş doğrulanamadı. Lütfen tekrar deneyin.'; return; }
            } else {
                $anahtar = sanitize_key(isset($_POST['g_anahtar']) ? $_POST['g_anahtar'] : '');
                $g = get_transient('osc_g_' . $anahtar); delete_transient('osc_g_' . $anahtar);
                if (!$g) { $this->uy_hata = 'İşlemin süresi doldu, lütfen tekrar Google ile devam edin.'; return; }
            }
            $u = get_user_by('email', $g['email']);
            $rol = isset($_POST['rol']) && in_array($_POST['rol'], array('ogretmen', 'ogrenci'), true) ? $_POST['rol'] : '';
            // eski (rolü belirlenmemiş) hesap "Öğrenci/Öğretmen girişi"nden geldiyse rolünü kaydet
            if ($u && $rol && !get_user_meta($u->ID, 'osc_rol', true) && !user_can($u->ID, 'edit_others_posts')) update_user_meta($u->ID, 'osc_rol', $rol);
            if (!$u) {
                if (!$rol) {
                    $anahtar = strtolower(wp_generate_password(20, false, false));
                    set_transient('osc_g_' . $anahtar, $g, 15 * MINUTE_IN_SECONDS);
                    wp_safe_redirect(add_query_arg(array('osc_g' => $anahtar, 'git' => isset($_POST['git']) ? rawurlencode(wp_unslash($_POST['git'])) : ''), self::sayfa_url('osc_sp_uye_sayfa'))); exit;
                }
                $id = $this->kullanici_olustur($g['email'], $g['ad'], wp_generate_password(24), $rol, array('osc_google_id' => $g['sub']));
                if (is_wp_error($id)) { $this->uy_hata = $id->get_error_message(); return; }
                $u = get_user_by('id', $id);
            }
            delete_user_meta($u->ID, 'osc_dogrulama');
            wp_set_current_user($u->ID); wp_set_auth_cookie($u->ID, true, is_ssl());
            wp_safe_redirect($this->git_url(self::rol($u->ID))); exit;
        }
        if (!empty($_POST['osc_web']) || !$this->zaman_gecerli()) { $this->uy_hata = 'Form doğrulanamadı. Sayfayı yenileyip tekrar deneyin.'; return; }
        $eposta = isset($_POST['eposta']) ? sanitize_text_field(wp_unslash($_POST['eposta'])) : '';
        $sifre = isset($_POST['sifre']) ? (string) wp_unslash($_POST['sifre']) : '';
        if ($islem === 'giris') {
            if ($this->kilitli('ip' . $ip) || $this->kilitli('u' . strtolower($eposta))) { $this->uy_hata = 'Çok fazla hatalı giriş denemesi yapıldı. Lütfen 15 dakika sonra tekrar deneyin.'; return; }
            if (!$this->rc_dogrula('giris')) { $this->uy_hata = 'Lütfen robot olmadığınızı doğrulayın.'; return; }
            $login = $eposta;
            if (is_email($eposta)) { $u = get_user_by('email', $eposta); if ($u) $login = $u->user_login; }
            $u = wp_signon(array('user_login' => $login, 'user_password' => $sifre, 'remember' => true), is_ssl());
            if (is_wp_error($u)) { $kod = $u->get_error_code(); $this->uy_hata = in_array($kod, array('osc_kilit', 'osc_dogrulanmadi'), true) ? $u->get_error_message() : 'E-posta ya da şifre hatalı.'; return; }
            $istenen = isset($_POST['rol']) && in_array($_POST['rol'], array('ogretmen', 'ogrenci'), true) ? $_POST['rol'] : '';
            if ($istenen && !get_user_meta($u->ID, 'osc_rol', true) && !user_can($u->ID, 'edit_others_posts')) update_user_meta($u->ID, 'osc_rol', $istenen);
            wp_safe_redirect($this->git_url(self::rol($u->ID))); exit;
        }
        if ($islem === 'sifre' || $islem === 'yeni_sifre') { $this->sifre_islem($islem); return; }
        if ($islem !== 'kayit') return;
        $say = (int) get_transient('osc_uye_ip_' . md5($ip));
        $rol = (isset($_POST['rol']) && $_POST['rol'] === 'ogrenci') ? 'ogrenci' : 'ogretmen';
        $ad = trim(preg_replace('/\s+/', ' ', sanitize_text_field(wp_unslash(isset($_POST['ad']) ? $_POST['ad'] : ''))));
        $uzun = function_exists('mb_strlen') ? mb_strlen($ad) : strlen($ad);
        if ($say >= 5) $this->uy_hata = 'Bu bağlantıdan çok fazla kayıt yapıldı. Lütfen bir saat sonra tekrar deneyin.';
        elseif ($uzun < 3 || $uzun > 60) $this->uy_hata = 'Adınızı ve soyadınızı yazın.';
        elseif (preg_match('#https?:|www\.|\.(com|net|org|ru|cn|xyz|top)\b|[<>@\#\$%\*=_\d]{2,}#i', $ad)) $this->uy_hata = 'Ad soyad alanına bağlantı ya da özel karakter yazılamaz.';
        elseif (!is_email($eposta)) $this->uy_hata = 'Geçerli bir e-posta adresi yazın.';
        elseif (email_exists($eposta)) $this->uy_hata = 'Bu e-posta ile zaten bir hesap var. Giriş yapabilirsiniz.';
        elseif ($e = $this->eposta_sorunu($eposta)) $this->uy_hata = $e;
        elseif (strlen($sifre) < 8 || !preg_match('/[A-Za-zÇĞİÖŞÜçğıöşü]/u', $sifre) || !preg_match('/\d/', $sifre)) $this->uy_hata = 'Şifre en az 8 karakter olmalı ve harf ile rakam içermeli.';
        elseif (empty($_POST['kvkk'])) $this->uy_hata = 'Devam etmek için kullanım koşullarını onaylayın.';
        elseif (!$this->rc_dogrula('kayit')) $this->uy_hata = 'Lütfen robot olmadığınızı doğrulayın.';
        if ($this->uy_hata) return;
        $ek = $rol === 'ogretmen' ? array('osc_brans' => sanitize_text_field(wp_unslash(isset($_POST['brans']) ? $_POST['brans'] : '')), 'osc_okul' => sanitize_text_field(wp_unslash(isset($_POST['okul']) ? $_POST['okul'] : ''))) : array('osc_sinif' => sanitize_text_field(wp_unslash(isset($_POST['sinif']) ? $_POST['sinif'] : '')));
        $id = $this->kullanici_olustur($eposta, $ad, $sifre, $rol, $ek);
        if (is_wp_error($id)) { $this->uy_hata = $id->get_error_message(); return; }
        set_transient('osc_uye_ip_' . md5($ip), $say + 1, HOUR_IN_SECONDS);
        $site = get_bloginfo('name');
        if (get_option('osc_uye_dogrulama') === '1') {
            $tok = strtolower(wp_generate_password(32, false, false));
            update_user_meta($id, 'osc_dogrulama', $tok);
            $link = add_query_arg(array('osc_dogrula' => $tok, 'u' => $id, 'git' => isset($_POST['git']) ? rawurlencode(wp_unslash($_POST['git'])) : ''), home_url('/'));
            wp_mail($eposta, '[' . $site . '] E-posta adresinizi doğrulayın', 'Merhaba ' . $ad . ",\n\n" . $site . " hesabınızı etkinleştirmek için aşağıdaki bağlantıya tıklayın:\n\n" . $link . "\n\nBu kaydı siz yapmadıysanız bu e-postayı yok sayabilirsiniz.");
            wp_safe_redirect(add_query_arg('osc_mesaj', 'dogrula', self::sayfa_url('osc_sp_uye_sayfa'))); exit;
        }
        wp_set_current_user($id); wp_set_auth_cookie($id, true, is_ssl());
        wp_mail($eposta, '[' . $site . '] Hoş geldiniz', 'Merhaba ' . $ad . ",\n\n" . $site . ' ' . ($rol === 'ogretmen' ? 'öğretmen' : 'öğrenci') . " hesabınız oluşturuldu.\n\nGiriş için e-posta adresinizi ve şifrenizi kullanabilirsiniz:\n" . self::sayfa_url('osc_sp_uye_sayfa') . "\n\nİyi çalışmalar!");
        wp_safe_redirect($this->git_url($rol)); exit;
    }

    /* ---------- Üyelik ve güvenlik ayarları ---------- */
    public function ayar_menu() { add_submenu_page('edit.php?post_type=osc_quiz', 'Üyelik ve Güvenlik', 'Üyelik ve Güvenlik', 'manage_options', 'osc-uyelik-guvenlik', array($this, 'ayar_sayfa')); }
    public function ayar_kaydet() { foreach (array('osc_kosul_sayfa', 'osc_kvkk_sayfa', 'osc_g_client_id', 'osc_rc_tip', 'osc_rc_site', 'osc_rc_gizli', 'osc_rc_esik', 'osc_uye_dogrulama', 'osc_uye_gecici', 'osc_uye_mx', 'osc_uye_katil_rc') as $o) register_setting('osc_uyelik_guvenlik', $o, array('sanitize_callback' => 'sanitize_text_field')); }
    public function ayar_sayfa() {
        $o = function ($k, $v = '') { return esc_attr(get_option($k, $v)); };
        $site = esc_html(home_url()); ?>
<div class="wrap"><h1>Üyelik ve Güvenlik</h1>
<form method="post" action="options.php"><?php settings_fields('osc_uyelik_guvenlik'); ?>
<h2>Google ile giriş</h2>
<table class="form-table"><tr><th>Google OAuth İstemci Kimliği (Client ID)</th><td><input type="text" class="large-text" name="osc_g_client_id" value="<?php echo $o('osc_g_client_id'); ?>" placeholder="1234567890-xxxx.apps.googleusercontent.com">
<p class="description">Boş bırakılırsa “Google ile devam et” düğmesi görünmez. Nasıl alınır: <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Google Cloud Console → Kimlik bilgileri</a> → “Kimlik bilgisi oluştur → OAuth istemci kimliği” → Uygulama türü: <b>Web uygulaması</b> → “Yetkilendirilmiş JavaScript kaynakları” kısmına <code><?php echo $site; ?></code> yazın → Oluştur. Çıkan istemci kimliğini buraya yapıştırın. (OAuth izin ekranını “Harici” ve “Yayında” olarak ayarlamayı unutmayın.)</p>
<?php if (get_option('osc_g_client_id')): $uu = self::sayfa_url('osc_sp_uye_sayfa'); ?>
<div style="margin-top:12px;padding:12px 14px;background:#fff;border:1px solid #c3c4c7;border-radius:8px;max-width:640px"><strong>✔ Kayıtlı.</strong> Aşağıda Google düğmesi görünüyorsa kimlik doğru demektir. (Düğmeye tıklayınca “origin_mismatch” hatası çıkarsa Google Cloud'da “Yetkilendirilmiş JavaScript kaynakları”na <code><?php echo $site; ?></code> eklenmemiştir.)
<div id="osc-g-test" style="margin-top:10px;min-height:44px"></div>
<?php if ($uu): ?><p style="margin:8px 0 0"><a href="<?php echo esc_url(add_query_arg('onizle', 1, $uu)); ?>" target="_blank">Üyelik sayfasını ziyaretçi gibi önizle →</a></p><?php endif; ?></div>
<script>function oscGTest(){if(!window.google||!google.accounts)return;google.accounts.id.initialize({client_id:<?php echo wp_json_encode(get_option('osc_g_client_id')); ?>,callback:function(){alert('Google girişi çalışıyor ✔');}});google.accounts.id.renderButton(document.getElementById('osc-g-test'),{theme:'outline',size:'large',shape:'pill',text:'continue_with',locale:'tr'});}</script>
<script src="https://accounts.google.com/gsi/client" async defer onload="oscGTest()"></script>
<?php else: ?><p style="color:#b32d2e;margin-top:8px"><strong>Henüz kayıtlı bir istemci kimliği yok.</strong> Kimliği yapıştırıp sayfanın altındaki “Değişiklikleri kaydet” düğmesine basın.</p><?php endif; ?>
</td></tr></table>
<h2>reCAPTCHA</h2>
<table class="form-table">
<tr><th>Tür</th><td><select name="osc_rc_tip"><option value="kapali" <?php selected(get_option('osc_rc_tip', 'kapali'), 'kapali'); ?>>Kapalı</option><option value="v3" <?php selected(get_option('osc_rc_tip'), 'v3'); ?>>v3 – görünmez (önerilen, kullanıcı hiçbir şey yapmaz)</option><option value="v2" <?php selected(get_option('osc_rc_tip'), 'v2'); ?>>v2 – “Ben robot değilim” kutusu</option></select></td></tr>
<tr><th>Site anahtarı</th><td><input type="text" class="large-text" name="osc_rc_site" value="<?php echo $o('osc_rc_site'); ?>"></td></tr>
<tr><th>Gizli anahtar</th><td><input type="text" class="large-text" name="osc_rc_gizli" value="<?php echo $o('osc_rc_gizli'); ?>">
<p class="description">Anahtarlar: <a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener">google.com/recaptcha/admin</a> → seçtiğiniz türü (v3 ya da v2 “onay kutusu”) seçin → alan adı olarak <code><?php echo esc_html(wp_parse_url(home_url(), PHP_URL_HOST)); ?></code> yazın. Seçilen tür ile anahtar türü aynı olmalıdır.</p></td></tr>
<tr><th>v3 en düşük puan</th><td><input type="number" step="0.1" min="0.1" max="0.9" name="osc_rc_esik" value="<?php echo $o('osc_rc_esik', '0.5'); ?>"> <span class="description">0.5 önerilir. Puanı bunun altında kalan istekler bot sayılır.</span></td></tr>
<tr><th>Sınava katılma formu</th><td><label><input type="checkbox" name="osc_uye_katil_rc" value="1" <?php checked(get_option('osc_uye_katil_rc'), '1'); ?>> Öğrencilerin kodla sınava katıldığı formda da reCAPTCHA kullan</label></td></tr>
</table>
<h2>Yasal metinler</h2>
<table class="form-table">
<tr><th>Kullanım koşulları sayfası</th><td><?php wp_dropdown_pages(array('name' => 'osc_kosul_sayfa', 'selected' => (int) get_option('osc_kosul_sayfa'), 'show_option_none' => '— Otomatik bul (kullanim-kosullari) —', 'option_none_value' => '0')); ?></td></tr>
<tr><th>KVKK aydınlatma metni sayfası</th><td><?php wp_dropdown_pages(array('name' => 'osc_kvkk_sayfa', 'selected' => (int) get_option('osc_kvkk_sayfa'), 'show_option_none' => '— Otomatik bul (kvkk-aydinlatma-metni) —', 'option_none_value' => '0')); ?><p class="description">Üyelik formundaki onay kutusunda bu sayfalara bağlantı verilir. Eklenti, yoksa örnek bir “KVKK Aydınlatma Metni” sayfası oluşturur; yayından önce kendi bilgilerinize göre gözden geçirin.</p></td></tr>
</table>
<h2>Diğer korumalar</h2>
<table class="form-table">
<tr><th>E-posta doğrulaması</th><td><label><input type="checkbox" name="osc_uye_dogrulama" value="1" <?php checked(get_option('osc_uye_dogrulama'), '1'); ?>> Yeni üyeler e-postalarındaki bağlantıya tıklamadan giriş yapamasın</label><p class="description">Sitenizin e-posta gönderebildiğinden emin olun (ör. WP Mail SMTP eklentisi). Google ile girenler otomatik doğrulanmış sayılır.</p></td></tr>
<tr><th>Geçici e-postalar</th><td><label><input type="hidden" name="osc_uye_gecici" value="0"><input type="checkbox" name="osc_uye_gecici" value="1" <?php checked(get_option('osc_uye_gecici', '1'), '1'); ?>> Tek kullanımlık e-posta servisleriyle (mailinator, yopmail, 10minutemail…) kayıt engellensin</label></td></tr>
<tr><th>Alan adı kontrolü</th><td><label><input type="hidden" name="osc_uye_mx" value="0"><input type="checkbox" name="osc_uye_mx" value="1" <?php checked(get_option('osc_uye_mx', '1'), '1'); ?>> E-posta adresinin alan adı gerçekten var mı kontrol edilsin</label></td></tr>
</table>
<p>Her zaman açık olanlar: gizli tuzak alan (honeypot), formun 2 saniyeden hızlı doldurulmasını engelleme, aynı bağlantıdan saatte en fazla 5 kayıt, 5 hatalı girişten sonra 15 dakika kilit (wp-login.php dahil), güçlü şifre zorunluluğu, ad alanında bağlantı yasağı.</p>
<?php submit_button(); ?></form></div>
<?php
    }
    public static function metin_url($opt, $yollar) {
        $id = (int) get_option($opt);
        if ($id && get_post_status($id) === 'publish') return get_permalink($id);
        foreach ($yollar as $y) { $p = get_page_by_path($y); if ($p && $p->post_status === 'publish') return get_permalink($p); }
        return '';
    }
    private function uy_kucuk_css() {
        return '<style>.osc-uy-kucuk{max-width:440px;margin:30px auto;background:#fff;border:1px solid #e5e7eb;border-radius:22px;padding:30px 24px;text-align:center;box-shadow:0 20px 40px -24px rgba(15,23,42,.35);font-family:inherit}.osc-uy-kucuk .osc-logo{margin:0 auto 14px}.osc-uy-kucuk .ik{font-size:44px}.osc-uy-kucuk h2{margin:8px 0 6px}.osc-uy-kucuk p{color:#64748b;margin:0 0 18px}.osc-uy-btn{display:inline-block;background:#4f46e5;color:#fff!important;text-decoration:none!important;font-weight:800;padding:13px 20px;border-radius:14px}.osc-uy-btn.ikinci{background:#eef2ff;color:#3730a3!important;margin-left:6px}</style>';
    }
    public function uyelik_kisa_kod() {
        $onizle = isset($_GET['onizle']) && current_user_can('manage_options');
        if (is_user_logged_in() && !$onizle) {
            $u = wp_get_current_user(); $rol = self::rol();
            if (current_user_can('manage_options')) return '<div class="osc-uy-kucuk">' . self::logo(34) . '<div class="ik">🔐</div><h2>Giriş yapmış durumdasınız</h2><p>Üyelik formu (Google ile giriş düğmesi dahil) yalnızca giriş yapmamış ziyaretçilere görünür. Ziyaretçilerin gördüğü hali incelemek için önizlemeyi açın ya da sayfayı gizli pencerede açın.</p><a class="osc-uy-btn" href="' . esc_url(add_query_arg('onizle', 1, get_permalink())) . '">Ziyaretçi görünümünü önizle</a><a class="osc-uy-btn ikinci" href="' . esc_url(wp_logout_url(get_permalink())) . '">Çıkış yap</a></div>' . $this->uy_kucuk_css();
            $panel = self::sayfa_url($rol === 'ogrenci' ? 'osc_sp_ogrenci_sayfa' : 'osc_sp_panel_sayfa');
            return '<div class="osc-uy-kucuk">' . self::logo(34) . '<div class="ik">' . ($rol === 'ogrenci' ? '🎒' : '👩‍🏫') . '</div><h2>Merhaba, ' . esc_html($u->display_name) . '</h2><p>' . ($rol === 'ogrenci' ? 'Öğrenci' : 'Öğretmen') . ' hesabınızla giriş yaptınız.</p>' . ($panel ? '<a class="osc-uy-btn" href="' . esc_url($panel) . '">Panelime git</a>' : '') . '<a class="osc-uy-btn ikinci" href="' . esc_url(wp_logout_url(get_permalink())) . '">Çıkış yap</a></div>' . $this->uy_kucuk_css();
        }
        $pi = isset($_POST['osc_uye_islem']) ? sanitize_key($_POST['osc_uye_islem']) : '';
        $sekme = $pi ? ($pi === 'giris' ? 'giris' : ($pi === 'sifre' ? 'sifre' : 'kayit')) : (isset($_GET['kayit']) ? 'kayit' : (isset($_GET['sifre']) ? 'sifre' : 'giris'));
        if (isset($_GET['interim-login'])) { wp_safe_redirect(add_query_arg(array('osc_wp' => 1, 'interim-login' => 1), site_url('wp-login.php', 'login'))); exit; }
        if (!empty($_GET['sifirla']) && !empty($_GET['login'])) return $this->sifre_yeni_form();
        $rol = isset($_REQUEST['rol']) && $_REQUEST['rol'] === 'ogrenci' ? 'ogrenci' : (isset($_REQUEST['rol']) ? 'ogretmen' : '');
        $v = function ($k) { return isset($_POST[$k]) ? esc_attr(wp_unslash($_POST[$k])) : ''; };
        $git = isset($_REQUEST['git']) ? esc_url(wp_unslash($_REQUEST['git'])) : '';
        $gizli = wp_nonce_field('osc_uye', 'osc_uye_nonce', false, false) . '<input type="hidden" name="git" value="' . $git . '"><input type="text" name="osc_web" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">' . $this->zaman_alani();
        $gid = get_option('osc_g_client_id');
        $mesaj = isset($_GET['osc_mesaj']) ? sanitize_key($_GET['osc_mesaj']) : '';
        // Google ile ilk girişte rol seçimi
        if (!empty($_GET['osc_g']) && ($gg = get_transient('osc_g_' . sanitize_key($_GET['osc_g'])))) {
            $gn = wp_nonce_field('osc_uye', 'osc_uye_nonce', false, false) . '<input type="hidden" name="osc_uye_islem" value="google_rol"><input type="hidden" name="g_anahtar" value="' . esc_attr(sanitize_key($_GET['osc_g'])) . '"><input type="hidden" name="git" value="' . $git . '">';
            return '<div class="osc-uy-kucuk">' . self::logo(34) . '<h2>Son bir adım</h2><p>' . esc_html($gg['email']) . ' ile ilk kez giriş yapıyorsun. Hesabını nasıl kullanacaksın?</p><form method="post" class="osc-uy-gsec">' . $gn . '<button name="rol" value="ogretmen"><i>👩‍🏫</i><b>Öğretmenim</b></button><button name="rol" value="ogrenci"><i>🎒</i><b>Öğrenciyim</b></button></form></div>' . $this->uy_kucuk_css() . '<style>.osc-uy-gsec{display:grid;grid-template-columns:1fr 1fr;gap:10px}.osc-uy-gsec button{display:flex;flex-direction:column;align-items:center;gap:4px;border:2px solid #e2e8f0!important;background:#fff!important;color:#0f172a!important;border-radius:16px!important;padding:18px 8px!important;cursor:pointer;font-family:inherit!important;font-size:15px!important;text-transform:none!important}.osc-uy-gsec button:hover{border-color:#4f46e5!important;background:#eef2ff!important}.osc-uy-gsec i{font-style:normal;font-size:30px}</style>';
        }
        $siniflar = array('1. sınıf', '2. sınıf', '3. sınıf', '4. sınıf', '5. sınıf', '6. sınıf', '7. sınıf', '8. sınıf (LGS)', '9. sınıf', '10. sınıf', '11. sınıf', '12. sınıf (YKS)', 'Mezun (YKS)', 'Üniversite / KPSS', 'Diğer');
        ob_start(); ?>
<div class="osc-uy" data-sekme="<?php echo esc_attr($sekme); ?>" data-rol="<?php echo esc_attr($rol); ?>" data-istenen="<?php echo esc_attr($rol); ?>">
  <div class="osc-uy-sol">
    <a class="osc-uy-marka" href="<?php echo esc_url(home_url('/')); ?>"><?php echo self::logo(36); ?></a>
    <h2>Türkiye'nin ücretsiz online test ve ödev platformu</h2>
    <ul><li><b>Öğretmenler</b> sınıfta canlı sınav yapar, ödev linki gönderir, kendi testlerini hazırlar.</li><li><b>Öğrenciler</b> çözdüğü testleri, netlerini ve konu karnesini tek yerde görür.</li><li>LGS, YKS, KPSS ve EKYS'ye uygun gerçek sınav deneyimi.</li></ul>
  </div>
  <div class="osc-uy-sag">
    <div class="osc-uy-sek"><button type="button" data-s="giris">Giriş yap</button><button type="button" data-s="kayit">Üye ol</button></div>
    <?php if ($this->uy_hata): ?><div class="osc-uy-hata"><?php echo esc_html($this->uy_hata); ?></div><?php endif; ?>
    <?php if ($this->uy_bilgi): ?><div class="osc-uy-bilgi"><?php echo esc_html($this->uy_bilgi); ?></div><?php endif; ?>
    <?php if ($mesaj === 'sifre_ok'): ?><div class="osc-uy-bilgi">✅ Şifren güncellendi. Yeni şifrenle giriş yapabilirsin.</div><?php elseif ($mesaj === 'cikis'): ?><div class="osc-uy-bilgi">Güvenli şekilde çıkış yaptın. Görüşmek üzere! 👋</div><?php endif; ?>
    <?php if ($mesaj === 'dogrula'): ?><div class="osc-uy-bilgi">📧 Hesabın oluşturuldu. E-posta adresine gönderdiğimiz bağlantıya tıklayarak hesabını etkinleştir. (Gelen kutunda yoksa “Spam” klasörüne bak.)</div><?php elseif ($mesaj === 'gecersiz'): ?><div class="osc-uy-hata">Doğrulama bağlantısı geçersiz ya da daha önce kullanılmış.</div><?php endif; ?>
    <?php if ($gid): ?><div class="osc-uy-google"><div id="osc-g-btn"></div><span>veya e-posta ile</span></div>
    <form method="post" id="osc-g-form" style="display:none"><?php echo wp_nonce_field('osc_uye', 'osc_uye_nonce', false, false); ?><input type="hidden" name="osc_uye_islem" value="google"><input type="hidden" name="credential"><input type="hidden" name="rol"><input type="hidden" name="git" value="<?php echo $git; ?>"></form><?php endif; ?>
    <form method="post" class="osc-uy-form giris"><?php echo $gizli; ?><input type="hidden" name="osc_uye_islem" value="giris"><?php if ($rol && isset($_GET['rol'])): ?><input type="hidden" name="rol" value="<?php echo esc_attr($rol); ?>"><?php endif; ?>
      <label>E-posta<input type="text" name="eposta" value="<?php echo $v('eposta'); ?>" autocomplete="username" required></label>
      <label>Şifre<input type="password" name="sifre" autocomplete="current-password" required></label>
      <?php echo $this->rc_alani('giris'); ?>
      <button class="osc-uy-ana" type="submit">Giriş yap</button>
      <a class="osc-uy-kucuk-link" href="#" data-sifre="1">Şifremi unuttum</a>
    </form>
    <form method="post" class="osc-uy-form sifre"><?php echo $gizli; ?><input type="hidden" name="osc_uye_islem" value="sifre">
      <p class="osc-uy-aciklama">Hesabına kayıtlı e-posta adresini yaz; şifreni yenilemen için bir bağlantı gönderelim.</p>
      <label>E-posta<input type="email" name="eposta" autocomplete="email" required></label>
      <?php echo $this->rc_alani('sifre'); ?>
      <button class="osc-uy-ana" type="submit">Yenileme bağlantısı gönder</button>
      <a class="osc-uy-kucuk-link" href="#" data-s2="giris">← Girişe dön</a>
    </form>
    <form method="post" class="osc-uy-form kayit"><?php echo $gizli; ?><input type="hidden" name="osc_uye_islem" value="kayit">
      <div class="osc-uy-rol"><label><input type="radio" name="rol" value="ogretmen" <?php checked($rol !== 'ogrenci'); ?>><span><i>👩‍🏫</i><b>Öğretmenim</b><small>Canlı sınav, ödev, test ekleme</small></span></label><label><input type="radio" name="rol" value="ogrenci" <?php checked($rol, 'ogrenci'); ?>><span><i>🎒</i><b>Öğrenciyim</b><small>Testler, netler, konu karnesi</small></span></label></div>
      <label>Adınız soyadınız<input type="text" name="ad" value="<?php echo $v('ad'); ?>" autocomplete="name" required></label>
      <label>E-posta<input type="email" name="eposta" value="<?php echo $v('eposta'); ?>" autocomplete="email" required></label>
      <label>Şifre <small>(en az 8 karakter, harf ve rakam)</small><input type="password" name="sifre" minlength="8" autocomplete="new-password" required></label>
      <div class="osc-uy-ogretmen"><label>Branşınız<input type="text" name="brans" value="<?php echo $v('brans'); ?>" placeholder="Örn: Matematik"></label><label>Okulunuz <small>(isteğe bağlı)</small><input type="text" name="okul" value="<?php echo $v('okul'); ?>"></label></div>
      <div class="osc-uy-ogrenci"><label>Sınıfın<select name="sinif"><?php foreach ($siniflar as $s) echo '<option' . ($v('sinif') === esc_attr($s) ? ' selected' : '') . '>' . esc_html($s) . '</option>'; ?></select></label></div>
      <label class="osc-uy-onay"><input type="checkbox" name="kvkk" value="1" required> <span><?php $ku = self::metin_url('osc_kosul_sayfa', array('kullanim-kosullari', 'kullanim-sartlari', 'uyelik-sozlesmesi')); $kv = self::metin_url('osc_kvkk_sayfa', array('kvkk-aydinlatma-metni', 'kvkk', 'gizlilik-politikasi')); echo ($ku ? '<a href="' . esc_url($ku) . '" target="_blank" rel="noopener">Kullanım Koşulları</a>’nı' : 'Kullanım koşullarını') . ' ve ' . ($kv ? '<a href="' . esc_url($kv) . '" target="_blank" rel="noopener">KVKK Aydınlatma Metni</a>’ni' : 'KVKK aydınlatma metnini'); ?> okudum, kabul ediyorum.</span></label>
      <?php echo $this->rc_alani('kayit'); ?>
      <button class="osc-uy-ana" type="submit">Ücretsiz üye ol</button>
    </form>
  </div>
</div>
<style>/*__UYELIK_CSS__*/</style>
<script>(function(){var k=document.querySelector('.osc-uy');if(!k)return;function s(x){k.dataset.sekme=x;}k.querySelectorAll('.osc-uy-sek button').forEach(function(b){b.onclick=function(){s(b.dataset.s);};});k.querySelectorAll('[data-sifre]').forEach(function(a){a.onclick=function(e){e.preventDefault();s('sifre');};});k.querySelectorAll('[data-s2]').forEach(function(a){a.onclick=function(e){e.preventDefault();s(a.dataset.s2);};});function r(){var c=k.querySelector('.osc-uy-rol input:checked');k.dataset.rol=c?c.value:'ogretmen';}k.querySelectorAll('.osc-uy-rol input').forEach(function(i){i.onchange=r;});r();})();</script>
<?php $this->rc_betik(); if ($gid): ?>
<script>var oscGoogleTamam=false;function oscGoogle(){var k=document.querySelector('.osc-uy'),f=document.getElementById('osc-g-form'),b=document.getElementById('osc-g-btn');if(oscGoogleTamam||!window.google||!google.accounts||!f||!b)return;oscGoogleTamam=true;google.accounts.id.initialize({client_id:<?php echo wp_json_encode($gid); ?>,callback:function(r){f.credential.value=r.credential;f.rol.value=k.dataset.sekme==='kayit'?(k.dataset.rol||''):(k.dataset.istenen||'');f.submit();},ux_mode:'popup',locale:'tr'});google.accounts.id.renderButton(b,{theme:'outline',size:'large',shape:'pill',text:'continue_with',width:Math.min(380,b.offsetWidth||380),locale:'tr'});}
(function(){var n=0,t=setInterval(function(){n++;if(window.google&&google.accounts){clearInterval(t);oscGoogle();}else if(n>40){clearInterval(t);var b=document.getElementById('osc-g-btn');if(b&&!b.children.length)b.innerHTML='<small style="color:#94a3b8">Google ile giriş şu an yüklenemedi (reklam engelleyici açık olabilir).</small>';}},250);})();</script>
<script src="https://accounts.google.com/gsi/client" async defer onload="oscGoogle()"></script>
<?php endif; ?>
<?php return ob_get_clean();
    }

    /* ---------------- içe aktarma kütüphanesi (öğretmen paneli) ---------------- */
    /* ---------- şifre yenileme (WordPress ekranı yerine) ---------- */
    private function sifre_islem($islem) {
        $ip = $this->ip();
        if (!empty($_POST['osc_web']) || !$this->zaman_gecerli()) { $this->uy_hata = 'Form doğrulanamadı. Sayfayı yenileyip tekrar deneyin.'; return; }
        if ($islem === 'sifre') {
            if ($this->kilitli('sf' . $ip)) { $this->uy_hata = 'Çok fazla deneme yapıldı. Lütfen biraz sonra tekrar deneyin.'; return; }
            if (!$this->rc_dogrula('sifre')) { $this->uy_hata = 'Lütfen robot olmadığınızı doğrulayın.'; return; }
            $this->hata_say('sf' . $ip);
            $eposta = sanitize_email(wp_unslash(isset($_POST['eposta']) ? $_POST['eposta'] : ''));
            $u = $eposta ? get_user_by('email', $eposta) : null;
            if ($u) {
                $key = get_password_reset_key($u);
                if (!is_wp_error($key)) {
                    $link = add_query_arg(array('sifirla' => $key, 'login' => rawurlencode($u->user_login)), self::sayfa_url('osc_sp_uye_sayfa'));
                    wp_mail($u->user_email, '[' . get_bloginfo('name') . '] Şifre yenileme', 'Merhaba ' . $u->display_name . ",\n\nŞifreni yenilemek için aşağıdaki bağlantıya tıkla:\n\n" . $link . "\n\nBu isteği sen yapmadıysan bu e-postayı yok sayabilirsin; şifren değişmez.\n\n" . get_bloginfo('name'));
                }
            }
            $this->uy_bilgi = 'Bu e-posta adresiyle bir hesap varsa şifre yenileme bağlantısı gönderildi. Gelen kutunu (ve Spam klasörünü) kontrol et.';
            $_POST['osc_uye_islem'] = 'giris';
            return;
        }
        $login = sanitize_user(wp_unslash(isset($_POST['login']) ? $_POST['login'] : ''));
        $key = preg_replace('/[^A-Za-z0-9]/', '', wp_unslash(isset($_POST['anahtar']) ? $_POST['anahtar'] : ''));
        $u = check_password_reset_key($key, $login);
        if (is_wp_error($u)) { $this->uy_hata = 'Bağlantının süresi dolmuş ya da daha önce kullanılmış. Lütfen yeniden şifre yenileme isteği gönder.'; return; }
        $s1 = (string) wp_unslash(isset($_POST['sifre']) ? $_POST['sifre'] : ''); $s2 = (string) wp_unslash(isset($_POST['sifre2']) ? $_POST['sifre2'] : '');
        if (strlen($s1) < 8 || !preg_match('/[A-Za-zÇĞİÖŞÜçğıöşü]/u', $s1) || !preg_match('/\d/', $s1)) { $this->uy_hata = 'Şifre en az 8 karakter olmalı ve harf ile rakam içermeli.'; return; }
        if ($s1 !== $s2) { $this->uy_hata = 'Şifreler aynı değil.'; return; }
        reset_password($u, $s1);
        delete_user_meta($u->ID, 'osc_dogrulama');
        wp_set_current_user($u->ID); wp_set_auth_cookie($u->ID, true, is_ssl());
        wp_safe_redirect($this->git_url(self::rol($u->ID))); exit;
    }
    private function sifre_yeni_form() {
        $login = sanitize_user(wp_unslash($_GET['login'])); $key = preg_replace('/[^A-Za-z0-9]/', '', wp_unslash($_GET['sifirla']));
        $gecerli = !is_wp_error(check_password_reset_key($key, $login));
        ob_start(); ?>
<div class="osc-uy-kucuk"><?php echo self::logo(34); ?><div class="ik">🔑</div><h2>Yeni şifre belirle</h2>
<?php if ($this->uy_hata): ?><p style="color:#b42318;font-weight:700"><?php echo esc_html($this->uy_hata); ?></p><?php endif; ?>
<?php if (!$gecerli): ?><p>Bu bağlantının süresi dolmuş ya da daha önce kullanılmış.</p><a class="osc-uy-btn" href="<?php echo esc_url(add_query_arg('sifre', 1, self::sayfa_url('osc_sp_uye_sayfa'))); ?>">Yeni bağlantı iste</a>
<?php else: ?><form method="post" class="osc-uy-ys"><?php echo wp_nonce_field('osc_uye', 'osc_uye_nonce', false, false) . $this->zaman_alani(); ?><input type="hidden" name="osc_uye_islem" value="yeni_sifre"><input type="hidden" name="login" value="<?php echo esc_attr($login); ?>"><input type="hidden" name="anahtar" value="<?php echo esc_attr($key); ?>"><input type="text" name="osc_web" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
<input type="password" name="sifre" minlength="8" placeholder="Yeni şifre (en az 8 karakter, harf ve rakam)" autocomplete="new-password" required><input type="password" name="sifre2" minlength="8" placeholder="Yeni şifre (tekrar)" autocomplete="new-password" required><button class="osc-uy-btn" type="submit">Şifremi güncelle</button></form><?php endif; ?></div>
<style>.osc-uy-ys{display:flex;flex-direction:column;gap:10px}.osc-uy-ys input[type=password]{width:100%;box-sizing:border-box;padding:13px 14px;border:1.5px solid #e2e8f0;border-radius:12px;font-size:15px;font-family:inherit}.osc-uy-ys .osc-uy-btn{border:0;cursor:pointer;font-size:16px;font-family:inherit}</style>
<?php return ob_get_clean() . $this->uy_kucuk_css();
    }

    /* ---------- WordPress izlerini gizle: çıkış, giriş, şifre, yönetim paneli ---------- */
    public static function cikis_url($git = '') { return add_query_arg(array('osc_cikis' => 1, '_n' => wp_create_nonce('osc_cikis'), 'git' => $git ? rawurlencode($git) : false), home_url('/')); }
    public function f_logout_url($url, $redirect) { return self::cikis_url($redirect); }
    public function f_login_url($url, $redirect) { $u = self::sayfa_url('osc_sp_uye_sayfa'); return $u ? add_query_arg($redirect ? array('git' => rawurlencode($redirect)) : array(), $u) : $url; }
    public function f_lostpassword_url($url) { $u = self::sayfa_url('osc_sp_uye_sayfa'); return $u ? add_query_arg('sifre', 1, $u) : $url; }
    public function f_register_url($url) { $u = self::sayfa_url('osc_sp_uye_sayfa'); return $u ? add_query_arg('kayit', 1, $u) : $url; }
    public function cikis_islem() {
        if (!isset($_GET['osc_cikis'])) return;
        nocache_headers();
        $git = isset($_GET['git']) ? esc_url_raw(rawurldecode(wp_unslash($_GET['git']))) : '';
        $uye = self::sayfa_url('osc_sp_uye_sayfa');
        $hedef = ($git && wp_validate_redirect($git, '')) ? $git : ($uye ? add_query_arg('osc_mesaj', 'cikis', $uye) : home_url('/'));
        $ref = wp_get_referer(); $evimiz = wp_parse_url(home_url(), PHP_URL_HOST);
        $guvenli = (isset($_GET['_n']) && wp_verify_nonce($_GET['_n'], 'osc_cikis')) || ($ref && wp_parse_url($ref, PHP_URL_HOST) === $evimiz) || (isset($_POST['_n']) && wp_verify_nonce($_POST['_n'], 'osc_cikis'));
        if (!is_user_logged_in()) { wp_safe_redirect($hedef); exit; }
        if ($guvenli) { wp_logout(); wp_safe_redirect($hedef); exit; }
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Çıkış — ' . esc_html(get_bloginfo('name')) . '</title><style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f6f7fb;font-family:system-ui,Segoe UI,sans-serif;color:#0f172a}.k{background:#fff;border:1px solid #e5e7eb;border-radius:22px;padding:30px;text-align:center;max-width:380px;box-shadow:0 20px 40px -24px rgba(15,23,42,.35)}h1{font-size:21px;margin:14px 0 6px}p{color:#64748b;margin:0 0 18px}button{border:0;background:#4f46e5;color:#fff;font-weight:800;font-size:16px;padding:13px 22px;border-radius:14px;cursor:pointer}a{display:block;margin-top:12px;color:#64748b}</style></head><body><form class="k" method="post">' . self::logo(36) . '<h1>Çıkış yapılsın mı?</h1><p>Hesabından güvenli şekilde çıkış yapmak için onayla.</p><input type="hidden" name="_n" value="' . esc_attr(wp_create_nonce('osc_cikis')) . '"><button type="submit">Çıkış yap</button><a href="' . esc_url(home_url('/')) . '">Vazgeç</a></form></body></html>';
        exit;
    }
    public function wp_login_yonlendir() {
        if (isset($_REQUEST['osc_wp']) || isset($_REQUEST['interim-login']) || $_SERVER['REQUEST_METHOD'] !== 'GET') return;
        $uye = self::sayfa_url('osc_sp_uye_sayfa'); if (!$uye) return;
        $a = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'login';
        $red = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';
        if ($a === 'logout') { wp_safe_redirect(self::cikis_url($red)); exit; }
        if ($a === 'rp' || $a === 'resetpass') { if (!empty($_GET['key']) && !empty($_GET['login'])) { wp_safe_redirect(add_query_arg(array('sifirla' => preg_replace('/[^A-Za-z0-9]/', '', $_GET['key']), 'login' => rawurlencode(wp_unslash($_GET['login']))), $uye)); exit; } return; }
        $map = array('login' => array(), 'register' => array('kayit' => 1), 'lostpassword' => array('sifre' => 1), 'retrievepassword' => array('sifre' => 1));
        if (!isset($map[$a])) return;
        $q = $map[$a]; if ($red) $q['git'] = rawurlencode($red);
        if (isset($_GET['loggedout'])) $q['osc_mesaj'] = 'cikis';
        wp_safe_redirect(add_query_arg($q, $uye)); exit;
    }
    public function admin_engel() {
        if (wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON) || current_user_can('edit_posts')) return;
        global $pagenow; if ($pagenow === 'admin-post.php' || $pagenow === 'async-upload.php') return;
        $rol = self::rol();
        $u = self::sayfa_url($rol === 'ogrenci' ? 'osc_sp_ogrenci_sayfa' : 'osc_sp_panel_sayfa');
        wp_safe_redirect($u ? $u : home_url('/')); exit;
    }
    public function f_mail_from_name($ad) { return ($ad === 'WordPress' || $ad === '') ? wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES) : $ad; }
    public function f_mail_from($e) { return strpos($e, 'wordpress@') === 0 ? 'bilgi@' . preg_replace('/^www\./', '', (string) wp_parse_url(home_url(), PHP_URL_HOST)) : $e; }
    public function onbellek_engel() {
        if (!is_singular()) return;
        $id = get_queried_object_id();
        foreach (array('osc_sp_uye_sayfa', 'osc_sp_panel_sayfa', 'osc_sp_ogrenci_sayfa', 'osc_sp_katil_sayfa', 'osc_yz_sayfa') as $o) {
            if ((int) get_option($o) === $id) {
                if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
                if (!defined('DONOTCACHEOBJECT')) define('DONOTCACHEOBJECT', true);
                nocache_headers();
                do_action('litespeed_control_set_nocache', 'osc uyelik');
                return;
            }
        }
    }
    public function kutuphane_servis() {
        if (!isset($_GET['osc_import_lib'])) return;
        if (!is_user_logged_in()) { status_header(403); exit; }
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: private, max-age=604800');
        if (isset($GLOBALS['osc_instance']) && method_exists($GLOBALS['osc_instance'], 'import_kutuphane_js')) $GLOBALS['osc_instance']->import_kutuphane_js();
        exit;
    }

    /* ---------------- öğrenci paneli ---------------- */
    public function ajax_cozum_kaydet() {
        check_ajax_referer('osc_sp_cozum', 'nonce');
        global $wpdb;
        $uid = get_current_user_id(); $qid = isset($_POST['quiz_id']) ? (int) $_POST['quiz_id'] : 0;
        if (!$uid || get_post_type($qid) !== 'osc_quiz') wp_send_json_error('Geçersiz.');
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->t_cz()} WHERE user_id = %d AND quiz_id = %d AND tarih > %d", $uid, $qid, time() - 15))) wp_send_json_success();
        $konu = json_decode(wp_unslash(isset($_POST['konu']) ? $_POST['konu'] : ''), true);
        $temiz = array();
        if (is_array($konu)) foreach (array_slice($konu, 0, 60, true) as $k => $x) if (is_array($x)) $temiz[sanitize_text_field($k)] = array('d' => (float) ($x['d'] ?? 0), 'y' => (float) ($x['y'] ?? 0), 'b' => (int) ($x['b'] ?? 0), 't' => (int) ($x['t'] ?? 0));
        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        if ($url && strpos($url, home_url()) !== 0) $url = '';
        $wpdb->insert($this->t_cz(), array('user_id' => $uid, 'quiz_id' => $qid, 'tarih' => time(), 'd' => (float) $_POST['d'], 'y' => (float) $_POST['y'], 'b' => (int) $_POST['b'], 'net' => round((float) $_POST['net'], 2), 'puan' => max(0, min(100, (int) $_POST['puan'])), 'soru' => (int) $_POST['soru'], 'url' => substr($url, 0, 250), 'konu' => wp_json_encode($temiz), 'yanlis' => preg_replace('/[^0-9,]/', '', isset($_POST['yanlis']) ? $_POST['yanlis'] : '')));
        wp_send_json_success();
    }
    public function ajax_ogr_ozet() {
        if (!is_user_logged_in()) wp_send_json_error('Giriş yapın.');
        global $wpdb;
        $uid = get_current_user_id();
        $cz = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->t_cz()} WHERE user_id = %d ORDER BY id DESC LIMIT 300", $uid));
        $kt = $wpdb->get_results($wpdb->prepare("SELECT k.*, o.kod, o.tur, o.baslik, o.durum AS odurum, o.son_tarih, o.bitis AS obitis, o.quiz_id, o.ogretmen_id FROM {$this->t_kt()} k JOIN {$this->t_ot()} o ON o.id = k.oturum_id WHERE k.user_id = %d ORDER BY k.id DESC LIMIT 100", $uid));
        $cozum = array(); $konu = array();
        foreach ($cz as $r) {
            $cozum[] = array('test' => get_the_title($r->quiz_id), 'url' => $r->url, 'tarih' => (int) $r->tarih, 'tarih_m' => $this->tarih($r->tarih), 'd' => (float) $r->d, 'y' => (float) $r->y, 'b' => (int) $r->b, 'net' => (float) $r->net, 'puan' => (int) $r->puan, 'soru' => (int) $r->soru, 'yanlis' => $r->yanlis ? count(explode(',', $r->yanlis)) : 0, 'kaynak' => 'test');
            $k = json_decode((string) $r->konu, true);
            if (is_array($k)) foreach ($k as $ad => $x) { $o = isset($konu[$ad]) ? $konu[$ad] : array('d' => 0, 'y' => 0, 'b' => 0, 't' => 0); foreach (array('d', 'y', 'b', 't') as $f) $o[$f] += $x[$f]; $konu[$ad] = $o; }
        }
        $odev = array();
        foreach ($kt as $r) {
            $ot = (object) array('id' => $r->oturum_id, 'tur' => $r->tur, 'durum' => $r->odurum, 'son_tarih' => $r->son_tarih, 'bitis' => $r->obitis);
            $d = $this->etkin_durum($ot); $og = get_userdata($r->ogretmen_id);
            $odev[] = array('baslik' => $r->baslik, 'test' => get_the_title($r->quiz_id), 'tur' => $r->tur, 'ogretmen' => $og ? $og->display_name : '', 'link' => self::ogrenci_url($r->kod), 'bitti' => $r->durum === 'bitti', 'acik' => $d !== 'bitti', 'son_tarih' => $this->tarih($r->son_tarih), 'net' => (float) $r->net, 'puan' => (int) $r->puan, 'd' => (float) $r->dogru, 'y' => (float) $r->yanlis, 'b' => (int) $r->bos, 'tarih' => (int) ($r->bitis ? $r->bitis : $r->son), 'tarih_m' => $this->tarih($r->bitis ? $r->bitis : $r->son));
            if ($r->durum === 'bitti') $cozum[] = array('test' => $r->baslik, 'url' => '', 'tarih' => (int) $r->bitis, 'tarih_m' => $this->tarih($r->bitis), 'd' => (float) $r->dogru, 'y' => (float) $r->yanlis, 'b' => (int) $r->bos, 'net' => (float) $r->net, 'puan' => (int) $r->puan, 'soru' => 0, 'yanlis' => 0, 'kaynak' => $r->tur);
        }
        usort($cozum, function ($a, $b) { return $b['tarih'] - $a['tarih']; });
        $u = wp_get_current_user();
        wp_send_json_success(array('ad' => $u->display_name, 'sinif' => (string) get_user_meta($uid, 'osc_sinif', true), 'cozum' => $cozum, 'odev' => $odev, 'konu' => $konu));
    }
    public function ogrenci_paneli_kisa_kod() {
        if (!is_user_logged_in()) {
            $uu = self::sayfa_url('osc_sp_uye_sayfa', array('git' => get_permalink(), 'rol' => 'ogrenci'));
            return '<div class="osc-uy-kucuk">' . self::logo(34) . '<div class="ik">🎒</div><h2>Öğrenci Paneli</h2><p>Çözdüğün testleri, netlerini, ödevlerini ve konu karneni görmek için giriş yap.</p><a class="osc-uy-btn" href="' . esc_url($uu ? $uu : wp_login_url(get_permalink())) . '">Giriş yap / Ücretsiz üye ol</a></div>' . $this->uy_kucuk_css();
        }
        $cfg = array('ajax' => admin_url('admin-ajax.php'), 'logo' => self::logo_src(), 'home' => home_url('/'), 'cikis' => wp_logout_url(), 'ogretmen' => self::rol() !== 'ogrenci' ? self::panel_url() : '', 'site' => get_bloginfo('name'));
        ob_start(); ?>
<div id="osc-op" class="osc-op"><div class="osc-op-yuk">Yükleniyor…</div></div>
<style>/*__OGRPANEL_CSS__*/</style>
<script>window.OSC_OP = <?php echo wp_json_encode($cfg); ?>;</script>
<script>/*__OGRPANEL_JS__*/</script>
<?php return ob_get_clean();
    }
