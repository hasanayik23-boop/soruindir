    /* ---------- spam koruması yardımcıları ---------- */
    private function ip() { return isset($_SERVER['REMOTE_ADDR']) ? preg_replace('/[^0-9a-f:.]/i', '', $_SERVER['REMOTE_ADDR']) : ''; }
    public static function rc_tip() { $t = get_option('osc_rc_tip', 'kapali'); return (get_option('osc_rc_site') && get_option('osc_rc_gizli') && in_array($t, array('v2', 'v3'), true)) ? $t : ''; }
    private function zaman_alani() { $t = time(); return '<input type="hidden" name="osc_t" value="' . $t . '.' . substr(wp_hash('osc_t' . $t), 0, 12) . '">'; }
    private function zaman_gecerli() {
        $v = isset($_POST['osc_t']) ? explode('.', (string) $_POST['osc_t']) : array();
        if (count($v) !== 2 || !hash_equals(substr(wp_hash('osc_t' . (int) $v[0]), 0, 12), $v[1])) return false;
        $gecen = time() - (int) $v[0];
        return $gecen >= 3 && $gecen <= 4 * HOUR_IN_SECONDS;
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
        if (get_option('osc_uye_mx', '1') === '1' && function_exists('checkdnsrr') && $alan && !checkdnsrr($alan, 'MX') && !checkdnsrr($alan, 'A')) return 'Bu e-posta adresinin alan adı bulunamadı. Lütfen kontrol edin.';
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
            if (!$u) {
                $rol = isset($_POST['rol']) && in_array($_POST['rol'], array('ogretmen', 'ogrenci'), true) ? $_POST['rol'] : '';
                if (!$rol || ($islem === 'google' && empty($_POST['kayit_sekmesi']))) {
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
            wp_safe_redirect($this->git_url(self::rol($u->ID))); exit;
        }
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
    public function ayar_kaydet() { foreach (array('osc_g_client_id', 'osc_rc_tip', 'osc_rc_site', 'osc_rc_gizli', 'osc_rc_esik', 'osc_uye_dogrulama', 'osc_uye_gecici', 'osc_uye_mx', 'osc_uye_katil_rc') as $o) register_setting('osc_uyelik_guvenlik', $o, array('sanitize_callback' => 'sanitize_text_field')); }
    public function ayar_sayfa() {
        $o = function ($k, $v = '') { return esc_attr(get_option($k, $v)); };
        $site = esc_html(home_url()); ?>
<div class="wrap"><h1>Üyelik ve Güvenlik</h1>
<form method="post" action="options.php"><?php settings_fields('osc_uyelik_guvenlik'); ?>
<h2>Google ile giriş</h2>
<table class="form-table"><tr><th>Google OAuth İstemci Kimliği (Client ID)</th><td><input type="text" class="large-text" name="osc_g_client_id" value="<?php echo $o('osc_g_client_id'); ?>" placeholder="1234567890-xxxx.apps.googleusercontent.com">
<p class="description">Boş bırakılırsa “Google ile devam et” düğmesi görünmez. Nasıl alınır: <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Google Cloud Console → Kimlik bilgileri</a> → “Kimlik bilgisi oluştur → OAuth istemci kimliği” → Uygulama türü: <b>Web uygulaması</b> → “Yetkilendirilmiş JavaScript kaynakları” kısmına <code><?php echo $site; ?></code> yazın → Oluştur. Çıkan istemci kimliğini buraya yapıştırın. (OAuth izin ekranını “Harici” ve “Yayında” olarak ayarlamayı unutmayın.)</p></td></tr></table>
<h2>reCAPTCHA</h2>
<table class="form-table">
<tr><th>Tür</th><td><select name="osc_rc_tip"><option value="kapali" <?php selected(get_option('osc_rc_tip', 'kapali'), 'kapali'); ?>>Kapalı</option><option value="v3" <?php selected(get_option('osc_rc_tip'), 'v3'); ?>>v3 – görünmez (önerilen, kullanıcı hiçbir şey yapmaz)</option><option value="v2" <?php selected(get_option('osc_rc_tip'), 'v2'); ?>>v2 – “Ben robot değilim” kutusu</option></select></td></tr>
<tr><th>Site anahtarı</th><td><input type="text" class="large-text" name="osc_rc_site" value="<?php echo $o('osc_rc_site'); ?>"></td></tr>
<tr><th>Gizli anahtar</th><td><input type="text" class="large-text" name="osc_rc_gizli" value="<?php echo $o('osc_rc_gizli'); ?>">
<p class="description">Anahtarlar: <a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener">google.com/recaptcha/admin</a> → seçtiğiniz türü (v3 ya da v2 “onay kutusu”) seçin → alan adı olarak <code><?php echo esc_html(wp_parse_url(home_url(), PHP_URL_HOST)); ?></code> yazın. Seçilen tür ile anahtar türü aynı olmalıdır.</p></td></tr>
<tr><th>v3 en düşük puan</th><td><input type="number" step="0.1" min="0.1" max="0.9" name="osc_rc_esik" value="<?php echo $o('osc_rc_esik', '0.5'); ?>"> <span class="description">0.5 önerilir. Puanı bunun altında kalan istekler bot sayılır.</span></td></tr>
<tr><th>Sınava katılma formu</th><td><label><input type="checkbox" name="osc_uye_katil_rc" value="1" <?php checked(get_option('osc_uye_katil_rc'), '1'); ?>> Öğrencilerin kodla sınava katıldığı formda da reCAPTCHA kullan</label></td></tr>
</table>
<h2>Diğer korumalar</h2>
<table class="form-table">
<tr><th>E-posta doğrulaması</th><td><label><input type="checkbox" name="osc_uye_dogrulama" value="1" <?php checked(get_option('osc_uye_dogrulama'), '1'); ?>> Yeni üyeler e-postalarındaki bağlantıya tıklamadan giriş yapamasın</label><p class="description">Sitenizin e-posta gönderebildiğinden emin olun (ör. WP Mail SMTP eklentisi). Google ile girenler otomatik doğrulanmış sayılır.</p></td></tr>
<tr><th>Geçici e-postalar</th><td><label><input type="hidden" name="osc_uye_gecici" value="0"><input type="checkbox" name="osc_uye_gecici" value="1" <?php checked(get_option('osc_uye_gecici', '1'), '1'); ?>> Tek kullanımlık e-posta servisleriyle (mailinator, yopmail, 10minutemail…) kayıt engellensin</label></td></tr>
<tr><th>Alan adı kontrolü</th><td><label><input type="hidden" name="osc_uye_mx" value="0"><input type="checkbox" name="osc_uye_mx" value="1" <?php checked(get_option('osc_uye_mx', '1'), '1'); ?>> E-posta adresinin alan adı gerçekten var mı kontrol edilsin</label></td></tr>
</table>
<p>Her zaman açık olanlar: gizli tuzak alan (honeypot), formun 3 saniyeden hızlı doldurulmasını engelleme, aynı bağlantıdan saatte en fazla 5 kayıt, 5 hatalı girişten sonra 15 dakika kilit (wp-login.php dahil), güçlü şifre zorunluluğu, ad alanında bağlantı yasağı.</p>
<?php submit_button(); ?></form></div>
<?php
    }
