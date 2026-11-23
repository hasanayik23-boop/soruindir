<?php
/*
Plugin Name: Soruindir.net Sınav Puanı Hesaplamaları
Description: LGS, YKS, KPSS, MEB-AGS, TUS, DUS, ALES, DGS, MSÜ, EKPSS, Hâkim-Savcı Yardımcılığı, İOKBS, YDS, DİB-MBSTS, Ehliyet ve Lise Takdir/Teşekkür hesaplama araçları. Yönetim paneli destekli (katsayı, ortalama ve standart sapmalar güncellenebilir).
Version: 2.1
Author: Soruindir.Net
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Net, sunucuda doğru ve yanlış sayılarından hesaplanır.
 * (Önceden tarayıcıdaki "Net" kutusundan okunuyordu; kutu güncellenmezse puan yanlış çıkıyordu.)
 */
function si_post_net($isim, $bolen = 4) {
    $varD = isset($_POST[$isim . '_d']) && $_POST[$isim . '_d'] !== '';
    $varY = isset($_POST[$isim . '_y']) && $_POST[$isim . '_y'] !== '';
    if (!$varD && !$varY) {
        if (isset($_POST[$isim . '_d']) || isset($_POST[$isim . '_y'])) return 0;
        return isset($_POST[$isim . '_n']) ? max(0, floatval($_POST[$isim . '_n'])) : 0;
    }
    $d = $varD ? max(0, intval($_POST[$isim . '_d'])) : 0;
    $y = $varY ? max(0, intval($_POST[$isim . '_y'])) : 0;
    $net = $d - $y / $bolen;
    return $net > 0 ? $net : 0;
}

// ========================================================================
// 1. WORDPRESS ADMİN PANELİ (AYARLAR SAYFASI)
// ========================================================================

// Sol menüye sayfa ekle
add_action('admin_menu', 'si_hesaplama_admin_menu');
function si_hesaplama_admin_menu() {
    add_menu_page(
        'Sınav Puan Ayarları', // Sayfa Başlığı
        'Puan Ayarları',       // Menü Adı
        'manage_options',      // Yetki (Sadece Adminler)
        'si-sinav-ayarlari',   // URL Slug
        'si_hesaplama_ayarlari_html', // Çıktı Fonksiyonu
        'dashicons-calculator', // İkon
        80                     // Menüdeki sırası
    );
}

// Ayarları veritabanına kaydet
add_action('admin_init', 'si_hesaplama_ayarlari_kaydet');
function si_hesaplama_ayarlari_kaydet() {
    // LGS Ayarları
    register_setting('si_hesaplama_ayarlar_grubu', 'si_lgs_taban');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_lgs_k_tr');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_lgs_k_mat');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_lgs_k_fen');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_lgs_k_ink');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_lgs_k_din');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_lgs_k_ing');

   // YKS Ayarları
    register_setting('si_hesaplama_ayarlar_grubu', 'si_yks_taban');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_yks_tyt_katsayi');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_yks_ayt_katsayi');

       // KPSS Ayarları
    register_setting('si_hesaplama_ayarlar_grubu', 'si_kpss_taban');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_kpss_gy_katsayi');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_kpss_gk_katsayi');
    foreach (array('lis_gy_ort', 'lis_gy_ss', 'lis_gk_ort', 'lis_gk_ss', 'lis_r', 'onl_taban', 'onl_gy', 'onl_gk', 'ort_taban', 'ort_gy', 'ort_gk') as $si_k) register_setting('si_hesaplama_ayarlar_grubu', 'si_kpss_' . $si_k);

            // TUS Ayarları (Gerçek ÖSYM Algoritmasına Geçildi)
    register_setting('si_hesaplama_ayarlar_grubu', 'si_tus_temel_ort');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_tus_temel_ss');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_tus_klinik_ort');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_tus_klinik_ss');

        // ALES Ayarları (Doğrusal Nokta Atışı Sistem)
    register_setting('si_hesaplama_ayarlar_grubu', 'si_ales_say_taban');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_ales_say_k_say');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_ales_say_k_soz');

    register_setting('si_hesaplama_ayarlar_grubu', 'si_ales_ea_taban');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_ales_ea_k_say');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_ales_ea_k_soz');

        register_setting('si_hesaplama_ayarlar_grubu', 'si_ales_soz_taban');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_ales_soz_k_say');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_ales_soz_k_soz');

    // DGS Ayarları (Doğrusal Katsayı)
    register_setting('si_hesaplama_ayarlar_grubu', 'si_dgs_say_taban');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_dgs_say_k_say');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_dgs_say_k_soz');

    register_setting('si_hesaplama_ayarlar_grubu', 'si_dgs_ea_taban');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_dgs_ea_k_say');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_dgs_ea_k_soz');

   register_setting('si_hesaplama_ayarlar_grubu', 'si_dgs_soz_taban');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_dgs_soz_k_say');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_dgs_soz_k_soz');

    // MSÜ Ayarları (Doğrusal Katsayılar)
    register_setting('si_hesaplama_ayarlar_grubu', 'si_msu_taban');
    // SAYISAL
    register_setting('si_hesaplama_ayarlar_grubu', 'si_msu_say_tr'); register_setting('si_hesaplama_ayarlar_grubu', 'si_msu_say_sos');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_msu_say_mat'); register_setting('si_hesaplama_ayarlar_grubu', 'si_msu_say_fen');
    // EA
    register_setting('si_hesaplama_ayarlar_grubu', 'si_msu_ea_tr'); register_setting('si_hesaplama_ayarlar_grubu', 'si_msu_ea_sos');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_msu_ea_mat'); register_setting('si_hesaplama_ayarlar_grubu', 'si_msu_ea_fen');
    // SÖZEL
    register_setting('si_hesaplama_ayarlar_grubu', 'si_msu_soz_tr'); register_setting('si_hesaplama_ayarlar_grubu', 'si_msu_soz_sos');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_msu_soz_mat'); register_setting('si_hesaplama_ayarlar_grubu', 'si_msu_soz_fen');
   // GENEL
    register_setting('si_hesaplama_ayarlar_grubu', 'si_msu_gen_tr'); register_setting('si_hesaplama_ayarlar_grubu', 'si_msu_gen_sos');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_msu_gen_mat'); register_setting('si_hesaplama_ayarlar_grubu', 'si_msu_gen_fen');

   // EHLİYET Ayarları
    register_setting('si_hesaplama_ayarlar_grubu', 'si_ehliyet_soru_puan');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_ehliyet_gecme_notu');

    // YDS Ayarları
    register_setting('si_hesaplama_ayarlar_grubu', 'si_yds_soru_katsayi');
	
// DİB-MBSTS Ayarları
    register_setting('si_hesaplama_ayarlar_grubu', 'si_mbsts_soru_katsayi');

// DUS Ayarları (Standart Puan Algoritması)
    register_setting('si_hesaplama_ayarlar_grubu', 'si_dus_temel_ort');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_dus_temel_ss');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_dus_klinik_ort');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_dus_klinik_ss');

// EKPSS Ayarları (Seviyelere Göre)
    register_setting('si_hesaplama_ayarlar_grubu', 'si_ekpss_orta_taban');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_ekpss_orta_gy');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_ekpss_orta_gk');

    register_setting('si_hesaplama_ayarlar_grubu', 'si_ekpss_onlisans_taban');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_ekpss_onlisans_gy');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_ekpss_onlisans_gk');

    register_setting('si_hesaplama_ayarlar_grubu', 'si_ekpss_lisans_taban');
  register_setting('si_hesaplama_ayarlar_grubu', 'si_ekpss_lisans_gy');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_ekpss_lisans_gk');

// Hakim ve Savcı Yardımcılığı Ayarları (Z-Skoru ve Standart Sapma)
    $hsy_testler = ['gygk', 'ortak', 'adli', 'idari', 'adliav'];
    foreach ($hsy_testler as $test) {
        register_setting('si_hesaplama_ayarlar_grubu', 'si_hsy_'.$test.'_ort');
        register_setting('si_hesaplama_ayarlar_grubu', 'si_hsy_'.$test.'_ss');
    }
    register_setting('si_hesaplama_ayarlar_grubu', 'si_hsy_adli_carpan');
   register_setting('si_hesaplama_ayarlar_grubu', 'si_hsy_idari_carpan');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_hsy_adliav_carpan');

   // İOKBS Ayarları
    register_setting('si_hesaplama_ayarlar_grubu', 'si_iokbs_taban');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_iokbs_turkce');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_iokbs_matematik');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_iokbs_fen');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_iokbs_sosyal');

    // Lise Belge Ayarları
    register_setting('si_hesaplama_ayarlar_grubu', 'si_lise_gecme_notu');
    register_setting('si_hesaplama_ayarlar_grubu', 'si_lise_tesekkur');
   register_setting('si_hesaplama_ayarlar_grubu', 'si_lise_takdir');
}


// Admin paneli HTML tasarımı
function si_hesaplama_ayarlari_html() {
    if (!current_user_can('manage_options')) { return; }
    ?>
    <div class="wrap">
        <h1>⚙️ Sınav Hesaplama Sistemi Ayarları</h1>
        <p>Sınavların katsayıları ve taban puanları her yıl değişebilmektedir. Kodlara dokunmadan bu panelden güncel değerleri girebilirsiniz. (Ondalıklı sayılar için nokta (.) kullanınız, örn: 4.12)</p>

        <form method="post" action="options.php">
            <?php settings_fields('si_hesaplama_ayarlar_grubu'); ?>

                        <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:20px;">
                <h2 style="border-bottom:2px solid #ef4444; padding-bottom:10px; margin-top:0;">TUS Standart Sapma Ayarları (ÖSYM Z-Skoru)</h2>
                <table class="form-table">
                    <tr valign="top"><th scope="row">Temel Tıp Türkiye Ortalaması</th><td><input type="text" name="si_tus_temel_ort" value="<?php echo esc_attr(get_option('si_tus_temel_ort', '40.00')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Temel Tıp Standart Sapması</th><td><input type="text" name="si_tus_temel_ss" value="<?php echo esc_attr(get_option('si_tus_temel_ss', '15.00')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Klinik Tıp Türkiye Ortalaması</th><td><input type="text" name="si_tus_klinik_ort" value="<?php echo esc_attr(get_option('si_tus_klinik_ort', '45.00')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Klinik Tıp Standart Sapması</th><td><input type="text" name="si_tus_klinik_ss" value="<?php echo esc_attr(get_option('si_tus_klinik_ss', '15.00')); ?>" /></td></tr>
                </table>
                <p class="description">TUS puanları sabit katsayıyla değil, Z-Skoru formülüyle (Ortalama ve Sapma kullanılarak) hesaplanır. Diğer büyük sitelerle birebir aynı sonucu vermesi için en güncel varsayılan değerler girilmiştir.</p>
            </div>

                        <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:20px;">
                <h2 style="border-bottom:2px solid #4f46e5; padding-bottom:10px; margin-top:0;">ALES Doğrusal Katsayı Ayarları</h2>
                <p class="description">ALES puanları diğer sitelerle virgülüne kadar aynı çıksın diye karmaşık Z-skoru yerine doğrudan net çarpanları sistemine geçilmiştir. Gerekirse bu çarpanlarla oynayarak her sitenin sonucunu birebir tutturabilirsiniz.</p>
                
                <h3 style="color:#4f46e5;">1. ALES SAYISAL Puanı İçin:</h3>
                <table class="form-table">
                    <tr valign="top"><th scope="row">SAY Taban Puanı</th><td><input type="text" name="si_ales_say_taban" value="<?php echo esc_attr(get_option('si_ales_say_taban', '55.00000')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Sayısal Net Çarpanı</th><td><input type="text" name="si_ales_say_k_say" value="<?php echo esc_attr(get_option('si_ales_say_k_say', '0.68765')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Sözel Net Çarpanı</th><td><input type="text" name="si_ales_say_k_soz" value="<?php echo esc_attr(get_option('si_ales_say_k_soz', '0.22920')); ?>" /></td></tr>
                </table>

                <h3 style="color:#10b981; border-top:1px solid #e2e8f0; padding-top:10px;">2. ALES EŞİT AĞIRLIK Puanı İçin:</h3>
                <table class="form-table">
                    <tr valign="top"><th scope="row">EA Taban Puanı</th><td><input type="text" name="si_ales_ea_taban" value="<?php echo esc_attr(get_option('si_ales_ea_taban', '55.00000')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Sayısal Net Çarpanı</th><td><input type="text" name="si_ales_ea_k_say" value="<?php echo esc_attr(get_option('si_ales_ea_k_say', '0.46924')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Sözel Net Çarpanı</th><td><input type="text" name="si_ales_ea_k_soz" value="<?php echo esc_attr(get_option('si_ales_ea_k_soz', '0.46924')); ?>" /></td></tr>
                </table>

                <h3 style="color:#f59e0b; border-top:1px solid #e2e8f0; padding-top:10px;">3. ALES SÖZEL Puanı İçin:</h3>
                <table class="form-table">
                    <tr valign="top"><th scope="row">SÖZ Taban Puanı</th><td><input type="text" name="si_ales_soz_taban" value="<?php echo esc_attr(get_option('si_ales_soz_taban', '55.00000')); ?>" /></td></tr>
                                        <tr valign="top"><th scope="row">Sayısal Net Çarpanı</th><td><input type="text" name="si_ales_soz_k_say" value="<?php echo esc_attr(get_option('si_ales_soz_k_say', '0.23566')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Sözel Net Çarpanı</th><td><input type="text" name="si_ales_soz_k_soz" value="<?php echo esc_attr(get_option('si_ales_soz_k_soz', '0.70698')); ?>" /></td></tr>
                </table>
            </div>

                        <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:20px;">
                <h2 style="border-bottom:2px solid #0d9488; padding-bottom:10px; margin-top:0;">DGS Doğrusal Katsayı Ayarları (2024-2025 Güncel)</h2>
                
                <h3 style="color:#0d9488;">1. DGS SAYISAL Puanı İçin:</h3>
                <table class="form-table">
                    <tr valign="top"><th scope="row">SAY Taban Puanı</th><td><input type="text" name="si_dgs_say_taban" value="<?php echo esc_attr(get_option('si_dgs_say_taban', '140.77635')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Sayısal Net Çarpanı</th><td><input type="text" name="si_dgs_say_k_say" value="<?php echo esc_attr(get_option('si_dgs_say_k_say', '3.48620')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Sözel Net Çarpanı</th><td><input type="text" name="si_dgs_say_k_soz" value="<?php echo esc_attr(get_option('si_dgs_say_k_soz', '0.61240')); ?>" /></td></tr>
                </table>

                <h3 style="color:#0f766e; border-top:1px solid #e2e8f0; padding-top:10px;">2. DGS EŞİT AĞIRLIK Puanı İçin:</h3>
                <table class="form-table">
                    <tr valign="top"><th scope="row">EA Taban Puanı</th><td><input type="text" name="si_dgs_ea_taban" value="<?php echo esc_attr(get_option('si_dgs_ea_taban', '122.04809')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Sayısal Net Çarpanı</th><td><input type="text" name="si_dgs_ea_k_say" value="<?php echo esc_attr(get_option('si_dgs_ea_k_say', '2.04930')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Sözel Net Çarpanı</th><td><input type="text" name="si_dgs_ea_k_soz" value="<?php echo esc_attr(get_option('si_dgs_ea_k_soz', '2.04930')); ?>" /></td></tr>
                </table>

                <h3 style="color:#115e59; border-top:1px solid #e2e8f0; padding-top:10px;">3. DGS SÖZEL Puanı İçin:</h3>
                <table class="form-table">
                    <tr valign="top"><th scope="row">SÖZ Taban Puanı</th><td><input type="text" name="si_dgs_soz_taban" value="<?php echo esc_attr(get_option('si_dgs_soz_taban', '103.31980')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Sayısal Net Çarpanı</th><td><input type="text" name="si_dgs_soz_k_say" value="<?php echo esc_attr(get_option('si_dgs_soz_k_say', '0.61240')); ?>" /></td></tr>
                   <tr valign="top"><th scope="row">Sözel Net Çarpanı</th><td><input type="text" name="si_dgs_soz_k_soz" value="<?php echo esc_attr(get_option('si_dgs_soz_k_soz', '3.48620')); ?>" /></td></tr>
                </table>
            </div>

            <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:20px;">
                <h2 style="border-bottom:2px solid #4d7c0f; padding-bottom:10px; margin-top:0;">MSÜ Katsayı Ayarları (2024-2025 Güncel)</h2>
                <p class="description">ÖSYM'nin MSÜ yüzdelik ağırlıklarına göre (Örn: Sayısal için %50 Mat, %25 Tr) 500 tam puana ulaşacak şekilde normalize edilmiş doğrusal çarpanlar.</p>
                
                <table class="form-table">
                    <tr valign="top"><th scope="row">MSÜ Taban Puanı</th><td><input type="text" name="si_msu_taban" value="<?php echo esc_attr(get_option('si_msu_taban', '100.000')); ?>" /></td></tr>
                </table>

                <table class="widefat" style="margin-top:15px; text-align:center;">
                    <thead>
                        <tr>
                            <th>Puan Türü</th>
                            <th>Türkçe Çarpanı</th>
                            <th>Sosyal Çarpanı</th>
                            <th>Matematik Çarpanı</th>
                            <th>Fen Çarpanı</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>MSÜ - SAYISAL</strong></td>
                            <td><input type="text" style="width:80px;" name="si_msu_say_tr" value="<?php echo esc_attr(get_option('si_msu_say_tr', '2.500')); ?>" /></td>
                            <td><input type="text" style="width:80px;" name="si_msu_say_sos" value="<?php echo esc_attr(get_option('si_msu_say_sos', '2.000')); ?>" /></td>
                            <td><input type="text" style="width:80px;" name="si_msu_say_mat" value="<?php echo esc_attr(get_option('si_msu_say_mat', '5.000')); ?>" /></td>
                            <td><input type="text" style="width:80px;" name="si_msu_say_fen" value="<?php echo esc_attr(get_option('si_msu_say_fen', '3.000')); ?>" /></td>
                        </tr>
                        <tr>
                            <td><strong>MSÜ - EŞİT AĞIRLIK</strong></td>
                            <td><input type="text" style="width:80px;" name="si_msu_ea_tr" value="<?php echo esc_attr(get_option('si_msu_ea_tr', '3.500')); ?>" /></td>
                            <td><input type="text" style="width:80px;" name="si_msu_ea_sos" value="<?php echo esc_attr(get_option('si_msu_ea_sos', '4.000')); ?>" /></td>
                            <td><input type="text" style="width:80px;" name="si_msu_ea_mat" value="<?php echo esc_attr(get_option('si_msu_ea_mat', '3.500')); ?>" /></td>
                            <td><input type="text" style="width:80px;" name="si_msu_ea_fen" value="<?php echo esc_attr(get_option('si_msu_ea_fen', '2.000')); ?>" /></td>
                        </tr>
                        <tr>
                            <td><strong>MSÜ - SÖZEL</strong></td>
                            <td><input type="text" style="width:80px;" name="si_msu_soz_tr" value="<?php echo esc_attr(get_option('si_msu_soz_tr', '3.500')); ?>" /></td>
                            <td><input type="text" style="width:80px;" name="si_msu_soz_sos" value="<?php echo esc_attr(get_option('si_msu_soz_sos', '7.000')); ?>" /></td>
                            <td><input type="text" style="width:80px;" name="si_msu_soz_mat" value="<?php echo esc_attr(get_option('si_msu_soz_mat', '2.000')); ?>" /></td>
                            <td><input type="text" style="width:80px;" name="si_msu_soz_fen" value="<?php echo esc_attr(get_option('si_msu_soz_fen', '2.000')); ?>" /></td>
                        </tr>
                        <tr>
                            <td><strong>MSÜ - GENEL</strong></td>
                            <td><input type="text" style="width:80px;" name="si_msu_gen_tr" value="<?php echo esc_attr(get_option('si_msu_gen_tr', '3.300')); ?>" /></td>
                            <td><input type="text" style="width:80px;" name="si_msu_gen_sos" value="<?php echo esc_attr(get_option('si_msu_gen_sos', '3.400')); ?>" /></td>
                            <td><input type="text" style="width:80px;" name="si_msu_gen_mat" value="<?php echo esc_attr(get_option('si_msu_gen_mat', '3.300')); ?>" /></td>
                            <td><input type="text" style="width:80px;" name="si_msu_gen_fen" value="<?php echo esc_attr(get_option('si_msu_gen_fen', '3.400')); ?>" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:20px;">
                <h2 style="border-bottom:2px solid #f59e0b; padding-bottom:10px; margin-top:0;">Ehliyet Sınavı Ayarları</h2>
                <table class="form-table">
                    <tr valign="top"><th scope="row">Bir Sorunun Puan Değeri</th><td><input type="text" name="si_ehliyet_soru_puan" value="<?php echo esc_attr(get_option('si_ehliyet_soru_puan', '2')); ?>" /> <span class="description">Ehliyet sınavında her soru standart 2 puandır.</span></td></tr>
                    <tr valign="top"><th scope="row">Geçme Notu (Baraj)</th><td><input type="text" name="si_ehliyet_gecme_notu" value="<?php echo esc_attr(get_option('si_ehliyet_gecme_notu', '70')); ?>" /> <span class="description">Başarılı sayılmak için alınması gereken minimum puan.</span></td></tr>
                </table>
            </div>



            <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:20px;">
                <h2 style="border-bottom:2px solid #8b5cf6; padding-bottom:10px; margin-top:0;">KPSS Ayarları (Lisans / Ön Lisans / Ortaöğretim)</h2>
                <table class="form-table">
                    <tr valign="top"><th scope="row">KPSS Taban Puanı</th><td><input type="text" name="si_kpss_taban" value="<?php echo esc_attr(get_option('si_kpss_taban', '45.000')); ?>" /> <span class="description">Genellikle 40-50 arası bir taban değer baz alınır.</span></td></tr>
                    <tr valign="top"><th scope="row">Genel Yetenek (GY) Katsayısı</th><td><input type="text" name="si_kpss_gy_katsayi" value="<?php echo esc_attr(get_option('si_kpss_gy_katsayi', '0.485')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Genel Kültür (GK) Katsayısı</th><td><input type="text" name="si_kpss_gk_katsayi" value="<?php echo esc_attr(get_option('si_kpss_gk_katsayi', '0.450')); ?>" /></td></tr>
                </table>
                <p class="description">Yukarıdaki değerler ortak varsayılandır. Ön Lisans (P93) ve Ortaöğretim (P94) için ayrı değer girersen o kullanılır.</p>
                <table class="form-table">
                    <tr valign="top"><th scope="row">P93 Taban / GY / GK</th><td><input type="text" size="8" name="si_kpss_onl_taban" value="<?php echo esc_attr(get_option('si_kpss_onl_taban', get_option('si_kpss_taban', '45.000'))); ?>" /> <input type="text" size="8" name="si_kpss_onl_gy" value="<?php echo esc_attr(get_option('si_kpss_onl_gy', get_option('si_kpss_gy_katsayi', '0.485'))); ?>" /> <input type="text" size="8" name="si_kpss_onl_gk" value="<?php echo esc_attr(get_option('si_kpss_onl_gk', get_option('si_kpss_gk_katsayi', '0.450'))); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">P94 Taban / GY / GK</th><td><input type="text" size="8" name="si_kpss_ort_taban" value="<?php echo esc_attr(get_option('si_kpss_ort_taban', get_option('si_kpss_taban', '45.000'))); ?>" /> <input type="text" size="8" name="si_kpss_ort_gy" value="<?php echo esc_attr(get_option('si_kpss_ort_gy', get_option('si_kpss_gy_katsayi', '0.485'))); ?>" /> <input type="text" size="8" name="si_kpss_ort_gk" value="<?php echo esc_attr(get_option('si_kpss_ort_gk', get_option('si_kpss_gk_katsayi', '0.450'))); ?>" /></td></tr>
                </table>
                <h3>KPSS Lisans (P3) — ÖSYM standart puan yöntemi</h3>
                <table class="form-table">
                    <tr valign="top"><th scope="row">Genel Yetenek ortalama / standart sapma</th><td><input type="text" size="8" name="si_kpss_lis_gy_ort" value="<?php echo esc_attr(get_option('si_kpss_lis_gy_ort', '17.43')); ?>" /> <input type="text" size="8" name="si_kpss_lis_gy_ss" value="<?php echo esc_attr(get_option('si_kpss_lis_gy_ss', '9.11')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Genel Kültür ortalama / standart sapma</th><td><input type="text" size="8" name="si_kpss_lis_gk_ort" value="<?php echo esc_attr(get_option('si_kpss_lis_gk_ort', '14.30')); ?>" /> <input type="text" size="8" name="si_kpss_lis_gk_ss" value="<?php echo esc_attr(get_option('si_kpss_lis_gk_ss', '9.26')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">GY–GK ilişki katsayısı</th><td><input type="text" size="8" name="si_kpss_lis_r" value="<?php echo esc_attr(get_option('si_kpss_lis_r', '0.70')); ?>" /> <span class="description">ÖSYM yayımlamaz; 0,6–0,8 arası bir değer kullanılır.</span></td></tr>
                </table>
                <p class="description">Yeni KPSS Lisans sonuçları açıklandığında ÖSYM'nin "Sayısal Bilgiler" belgesindeki GY ve GK net ortalamalarını ve standart sapmalarını buraya yaz.</p>
            </div>

            <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:20px;">
                <h2 style="border-bottom:2px solid #1E5AA8; padding-bottom:10px; margin-top:0;">MEB-AGS Ayarları (ÖSYM standart puan yöntemi)</h2>
                <p class="description">Varsayılanlar ÖSYM'nin "2026-MEB-AGS Sınav Sonuçlarına İlişkin Sayısal Bilgiler" belgesinden alındı. Ağırlıklar yüzde olarak girilir. Kısa kod: <code>[ags_hesapla]</code></p>
                <table class="form-table">
                <?php $si_av = si_ags_varsayilan(); foreach ($si_av['testler'] as $si_k => $si_t): ?>
                    <tr valign="top"><th scope="row"><?php echo esc_html($si_t[0]); ?> — ortalama / ss / ağırlık %</th><td><input type="text" size="7" name="si_ags_<?php echo $si_k; ?>_ort" value="<?php echo esc_attr(get_option('si_ags_' . $si_k . '_ort', $si_t[2])); ?>" /> <input type="text" size="7" name="si_ags_<?php echo $si_k; ?>_ss" value="<?php echo esc_attr(get_option('si_ags_' . $si_k . '_ss', $si_t[3])); ?>" /> <input type="text" size="7" name="si_ags_<?php echo $si_k; ?>_w" value="<?php echo esc_attr(get_option('si_ags_' . $si_k . '_w', $si_t[4])); ?>" /></td></tr>
                <?php endforeach; foreach ($si_av['oabt'] as $si_k => $si_o): ?>
                    <tr valign="top"><th scope="row">ÖABT <?php echo esc_html($si_o[0]); ?> — ortalama / ss</th><td><input type="text" size="7" name="si_ags_oabt_<?php echo $si_k; ?>_ort" value="<?php echo esc_attr(get_option('si_ags_oabt_' . $si_k . '_ort', $si_o[1])); ?>" /> <input type="text" size="7" name="si_ags_oabt_<?php echo $si_k; ?>_ss" value="<?php echo esc_attr(get_option('si_ags_oabt_' . $si_k . '_ss', $si_o[2])); ?>" /></td></tr>
                <?php endforeach; ?>
                    <tr valign="top"><th scope="row">Alt testler arası ilişki katsayısı</th><td><input type="text" size="7" name="si_ags_r" value="<?php echo esc_attr(get_option('si_ags_r', '0.50')); ?>" /> <span class="description">ÖSYM yayımlamaz; ağırlıklı puanın standart sapmasını tahmin etmek için kullanılır.</span></td></tr>
                </table>
            </div>

            <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:20px;">
                <h2 style="border-bottom:2px solid #22c55e; padding-bottom:10px; margin-top:0;">LGS Katsayı ve Puan Ayarları</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">LGS Taban Puanı</th>
                        <td><input type="text" name="si_lgs_taban" value="<?php echo esc_attr(get_option('si_lgs_taban', '194.76')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Türkçe Katsayısı</th>
                        <td><input type="text" name="si_lgs_k_tr" value="<?php echo esc_attr(get_option('si_lgs_k_tr', '3.90')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Matematik Katsayısı</th>
                        <td><input type="text" name="si_lgs_k_mat" value="<?php echo esc_attr(get_option('si_lgs_k_mat', '5.10')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Fen Bilimleri Katsayısı</th>
                        <td><input type="text" name="si_lgs_k_fen" value="<?php echo esc_attr(get_option('si_lgs_k_fen', '4.00')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">İnkılap Tarihi Katsayısı</th>
                        <td><input type="text" name="si_lgs_k_ink" value="<?php echo esc_attr(get_option('si_lgs_k_ink', '1.50')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Din Kültürü Katsayısı</th>
                        <td><input type="text" name="si_lgs_k_din" value="<?php echo esc_attr(get_option('si_lgs_k_din', '1.55')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">İngilizce Katsayısı</th>
                        <td><input type="text" name="si_lgs_k_ing" value="<?php echo esc_attr(get_option('si_lgs_k_ing', '1.474')); ?>" /></td>
                    </tr>
                </table>
            </div>

            <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:20px;">
                <h2 style="border-bottom:2px solid #3b82f6; padding-bottom:10px; margin-top:0;">YKS Katsayı ve Puan Ayarları</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">YKS Taban Puanı (ÖSYM Sabiti)</th>
                        <td><input type="text" name="si_yks_taban" value="<?php echo esc_attr(get_option('si_yks_taban', '100')); ?>" /> <span class="description">Genelde 100'dür.</span></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">TYT Ortalama Soru Katsayısı</th>
                        <td><input type="text" name="si_yks_tyt_katsayi" value="<?php echo esc_attr(get_option('si_yks_tyt_katsayi', '3.35')); ?>" /> <span class="description">TYT sorularının yaklaşık puan getirisi.</span></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">AYT Alan Sorusu Katsayısı</th>
                        <td><input type="text" name="si_yks_ayt_katsayi" value="<?php echo esc_attr(get_option('si_yks_ayt_katsayi', '3.00')); ?>" /> <span class="description">AYT sorularının yaklaşık puan getirisi.</span></td>
                    </tr>
                </table>
            </div>

            <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:20px;">
                <h2 style="border-bottom:2px solid #06b6d4; padding-bottom:10px; margin-top:0;">YDS Ayarları</h2>
                <table class="form-table">
                    <tr valign="top"><th scope="row">Bir Sorunun Puan Değeri</th><td><input type="text" name="si_yds_soru_katsayi" value="<?php echo esc_attr(get_option('si_yds_soru_katsayi', '1.25')); ?>" /> <span class="description">YDS'de 80 soru vardır. Yanlışlar doğruları götürmez. Her soru standart 1.25 puandır.</span></td></tr>
                </table>
            </div>
<div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:20px;">
                <h2 style="border-bottom:2px solid #14b8a6; padding-bottom:10px; margin-top:0;">DİB-MBSTS Ayarları</h2>
                <table class="form-table">
                    <tr valign="top"><th scope="row">Bir Sorunun Puan Değeri</th><td><input type="text" name="si_mbsts_soru_katsayi" value="<?php echo esc_attr(get_option('si_mbsts_soru_katsayi', '1.66667')); ?>" /> <span class="description">Sınavda 60 soru vardır ve yanlışlar doğruları götürmez. (100 / 60 = 1.66667)</span></td></tr>
                </table>
            </div>

           <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:20px;">
                <h2 style="border-bottom:2px solid #ec4899; padding-bottom:10px; margin-top:0;">DUS Ayarları (ÖSYM Z-Skoru)</h2>
                <table class="form-table">
                    <tr valign="top"><th scope="row">Temel Bilimler (TBT) Ortalaması</th><td><input type="text" name="si_dus_temel_ort" value="<?php echo esc_attr(get_option('si_dus_temel_ort', '15.000')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Temel Bilimler (TBT) Standart Sapması</th><td><input type="text" name="si_dus_temel_ss" value="<?php echo esc_attr(get_option('si_dus_temel_ss', '8.1925')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Klinik Bilimler (KBT) Ortalaması</th><td><input type="text" name="si_dus_klinik_ort" value="<?php echo esc_attr(get_option('si_dus_klinik_ort', '35.000')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Klinik Bilimler (KBT) Standart Sapması</th><td><input type="text" name="si_dus_klinik_ss" value="<?php echo esc_attr(get_option('si_dus_klinik_ss', '19.1827')); ?>" /></td></tr>
                </table>
                <p class="description">DUS puanları ÖSYM'nin Standart Puan (SP) mantığı ile hesaplanır. K Puanı = (TBT SP * 0,4) + (KBT SP * 0,6) | T Puanı = (TBT SP * 0,6) + (KBT SP * 0,4)</p>
            </div>

           <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:20px;">
                <h2 style="border-bottom:2px solid #8b5cf6; padding-bottom:10px; margin-top:0;">EKPSS Ayarları (Eğitim Seviyelerine Göre)</h2>
                
               <h3 style="margin-top:20px; color:#4c1d95;">1. Ortaöğretim (Lise) Katsayıları</h3>
                <table class="form-table">
                    <tr valign="top"><th scope="row">Taban Puan</th><td><input type="text" name="si_ekpss_orta_taban" value="<?php echo esc_attr(get_option('si_ekpss_orta_taban', '40.00')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Genel Yetenek (GY)</th><td><input type="text" name="si_ekpss_orta_gy" value="<?php echo esc_attr(get_option('si_ekpss_orta_gy', '1.05')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Genel Kültür (GK)</th><td><input type="text" name="si_ekpss_orta_gk" value="<?php echo esc_attr(get_option('si_ekpss_orta_gk', '0.95')); ?>" /></td></tr>
                </table>

                <h3 style="margin-top:20px; color:#4c1d95; border-top:1px solid #eee; padding-top:15px;">2. Önlisans Katsayıları</h3>
                <table class="form-table">
                    <tr valign="top"><th scope="row">Taban Puan</th><td><input type="text" name="si_ekpss_onlisans_taban" value="<?php echo esc_attr(get_option('si_ekpss_onlisans_taban', '40.00')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Genel Yetenek (GY)</th><td><input type="text" name="si_ekpss_onlisans_gy" value="<?php echo esc_attr(get_option('si_ekpss_onlisans_gy', '1.00')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Genel Kültür (GK)</th><td><input type="text" name="si_ekpss_onlisans_gk" value="<?php echo esc_attr(get_option('si_ekpss_onlisans_gk', '1.00')); ?>" /></td></tr>
                </table>

                <h3 style="margin-top:20px; color:#4c1d95; border-top:1px solid #eee; padding-top:15px;">3. Lisans Katsayıları</h3>
                <table class="form-table">
                    <tr valign="top"><th scope="row">Taban Puan</th><td><input type="text" name="si_ekpss_lisans_taban" value="<?php echo esc_attr(get_option('si_ekpss_lisans_taban', '40.00')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Genel Yetenek (GY)</th><td><input type="text" name="si_ekpss_lisans_gy" value="<?php echo esc_attr(get_option('si_ekpss_lisans_gy', '0.95')); ?>" /></td></tr>
                   <tr valign="top"><th scope="row">Genel Kültür (GK)</th><td><input type="text" name="si_ekpss_lisans_gk" value="<?php echo esc_attr(get_option('si_ekpss_lisans_gk', '1.05')); ?>" /></td></tr>
                </table>
            </div>

        <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:20px;">
                <h2 style="border-bottom:2px solid #b91c1c; padding-bottom:10px; margin-top:0;">Hakim ve Savcı Yardımcılığı Ayarları (ÖSYM Z-Skoru)</h2>
                <p class="description">Puanlar ÖSYM'nin algoritmik Standart Sapma (Z-Skoru) mantığı ile hesaplanır. Sistem 100 puan katsayısını <strong>otomatik olarak kalibre eder</strong> (Tam net = 100 Puan).</p>
                <table class="form-table">
                    <tr valign="top"><th scope="row">GY-GK Ortalaması | Sapması</th><td><input type="text" name="si_hsy_gygk_ort" value="<?php echo esc_attr(get_option('si_hsy_gygk_ort', '14.200')); ?>" style="width:100px;"/> / <input type="text" name="si_hsy_gygk_ss" value="<?php echo esc_attr(get_option('si_hsy_gygk_ss', '5.100')); ?>" style="width:100px;"/></td></tr>
                    <tr valign="top"><th scope="row">Ortak Alan Ort. | Sapması</th><td><input type="text" name="si_hsy_ortak_ort" value="<?php echo esc_attr(get_option('si_hsy_ortak_ort', '17.500')); ?>" style="width:100px;"/> / <input type="text" name="si_hsy_ortak_ss" value="<?php echo esc_attr(get_option('si_hsy_ortak_ss', '6.200')); ?>" style="width:100px;"/></td></tr>
                    <tr valign="top"><th scope="row">Adli Yargı Ort. | Sapması</th><td><input type="text" name="si_hsy_adli_ort" value="<?php echo esc_attr(get_option('si_hsy_adli_ort', '16.800')); ?>" style="width:100px;"/> / <input type="text" name="si_hsy_adli_ss" value="<?php echo esc_attr(get_option('si_hsy_adli_ss', '5.900')); ?>" style="width:100px;"/></td></tr>
                    <tr valign="top"><th scope="row">İdari Yargı Ort. | Sapması</th><td><input type="text" name="si_hsy_idari_ort" value="<?php echo esc_attr(get_option('si_hsy_idari_ort', '11.500')); ?>" style="width:100px;"/> / <input type="text" name="si_hsy_idari_ss" value="<?php echo esc_attr(get_option('si_hsy_idari_ss', '4.800')); ?>" style="width:100px;"/></td></tr>
                  <tr valign="top"><th scope="row">Adli-Avukat Ort. | Sapması</th><td><input type="text" name="si_hsy_adliav_ort" value="<?php echo esc_attr(get_option('si_hsy_adliav_ort', '15.200')); ?>" style="width:100px;"/> / <input type="text" name="si_hsy_adliav_ss" value="<?php echo esc_attr(get_option('si_hsy_adliav_ss', '5.700')); ?>" style="width:100px;"/></td></tr>
                </table>
            </div>

            <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:20px;">
                <h2 style="border-bottom:2px solid #f59e0b; padding-bottom:10px; margin-top:0;">İOKBS (Bursluluk) Ayarları</h2>
                <table class="form-table">
                    <tr valign="top"><th scope="row">Taban Puan</th><td><input type="text" name="si_iokbs_taban" value="<?php echo esc_attr(get_option('si_iokbs_taban', '100.000')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Türkçe Katsayısı</th><td><input type="text" name="si_iokbs_turkce" value="<?php echo esc_attr(get_option('si_iokbs_turkce', '5.000')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Matematik Katsayısı</th><td><input type="text" name="si_iokbs_matematik" value="<?php echo esc_attr(get_option('si_iokbs_matematik', '5.000')); ?>" /></td></tr>
                    <tr valign="top"><th scope="row">Fen Bilimleri Katsayısı</th><td><input type="text" name="si_iokbs_fen" value="<?php echo esc_attr(get_option('si_iokbs_fen', '5.000')); ?>" /></td></tr>
                   <tr valign="top"><th scope="row">Sosyal Bilgiler Katsayısı</th><td><input type="text" name="si_iokbs_sosyal" value="<?php echo esc_attr(get_option('si_iokbs_sosyal', '5.000')); ?>" /></td></tr>
                </table>
                <p class="description">MEB'in güncel sisteminde sınavda <strong>80 soru</strong> (her dersten 20) vardır ve 3 yanlış 1 doğruyu götürür. Katsayılar varsayılan 5 puandır [(80 net x 5) + 100 taban = 500 Tam Puan].</p>
            </div>

            <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-top:20px;">
                <h2 style="border-bottom:2px solid #3b82f6; padding-bottom:10px; margin-top:0;">Lise Takdir & Teşekkür Ayarları</h2>
                <table class="form-table">
                    <tr valign="top"><th scope="row">Ders Geçme Notu Barajı</th><td><input type="text" name="si_lise_gecme_notu" value="<?php echo esc_attr(get_option('si_lise_gecme_notu', '50.00')); ?>" /> <span class="description">Zayıf ders sınırı. Bir ders bu notun altındaysa belge alınamaz.</span></td></tr>
                    <tr valign="top"><th scope="row">Teşekkür Belgesi Sınırı</th><td><input type="text" name="si_lise_tesekkur" value="<?php echo esc_attr(get_option('si_lise_tesekkur', '70.00')); ?>" /></td></tr>
                   <tr valign="top"><th scope="row">Takdir Belgesi Sınırı</th><td><input type="text" name="si_lise_takdir" value="<?php echo esc_attr(get_option('si_lise_takdir', '85.00')); ?>" /></td></tr>
                </table>
            </div>

            <?php submit_button('Ayarları Kaydet'); ?>
        </form>
    </div>
    <?php
}

// ========================================================================
// 2. LGS PUAN HESAPLAMA MODÜLÜ (MODERN TASARIM)
// ========================================================================
function soruindir_lgs_hesapla_shortcode() {
    ob_start(); 
    $guncel_yil = date("Y");
    ?>
    <style>
        .si-lgs-wrapper { background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 20px; padding: 40px 30px; max-width: 900px; margin: 0 auto 30px auto; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.04); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .si-lgs-header { text-align: center; margin-bottom: 35px; }
        .si-lgs-title { color: #0f172a; font-size: 28px; font-weight: 900; margin: 0 0 10px 0; letter-spacing: -0.5px; }
        .si-lgs-title span { color: #16a34a; }
        .si-lgs-desc { color: #64748b; font-size: 15px; margin: 0; }
        .si-lgs-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 35px; }
        .si-lgs-card { background: #fff; border-radius: 16px; padding: 20px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 15px; transition: all 0.3s ease; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
        .si-lgs-card:hover { transform: translateY(-5px); border-color: #16a34a; box-shadow: 0 10px 25px rgba(22, 163, 74, 0.1); }
        .si-lgs-card-header { display: flex; align-items: center; gap: 15px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 15px; }
        .si-lgs-icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .si-lgs-icon-box svg { width: 24px; height: 24px; }
        .si-lgs-subj-title { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; display: block; }
        .si-lgs-subj-meta { font-size: 12px; font-weight: 600; color: #94a3b8; display: block; margin-top: 4px; }
        .icon-tr { background: #eff6ff; color: #3b82f6; } .icon-mat { background: #fdf4ff; color: #d946ef; } .icon-fen { background: #f0fdf4; color: #16a34a; } .icon-ink { background: #fff7ed; color: #f97316; } .icon-din { background: #f0fdfa; color: #0d9488; } .icon-ing { background: #fef2f2; color: #ef4444; }
        .si-lgs-inputs { display: flex; justify-content: space-between; gap: 10px; }
        .si-lgs-input-group { flex: 1; text-align: center; }
        .si-lgs-input-group label { display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 8px; }
        .si-lgs-input-group input { width: 100%; padding: 12px 5px; text-align: center; font-size: 16px; font-weight: 800; border-radius: 10px; border: 2px solid #e2e8f0; color: #0f172a; transition: 0.3s; box-sizing: border-box; }
        .si-lgs-input-group.do input:focus { border-color: #16a34a; background: #f0fdf4; outline: none; }
        .si-lgs-input-group.ya input:focus { border-color: #ef4444; background: #fef2f2; outline: none; }
        .si-lgs-input-group.ne input { background: #f8fafc; border-color: transparent; color: #3b82f6; }
        .si-btn-lgs { width: 100%; padding: 20px; background: linear-gradient(135deg, #16a34a, #15803d); color: #fff; border: none; border-radius: 14px; font-size: 20px; font-weight: 900; letter-spacing: 0.5px; cursor: pointer; transition: 0.3s; box-shadow: 0 8px 25px rgba(22, 163, 74, 0.3); display: flex; align-items: center; justify-content: center; gap: 10px; }
        .si-btn-lgs:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(22, 163, 74, 0.4); }
        .si-lgs-result-box { margin-top: 30px; background: linear-gradient(135deg, #ffffff, #f0fdf4); border: 2px solid #22c55e; border-radius: 20px; padding: 35px 20px; text-align: center; box-shadow: 0 10px 30px rgba(34, 197, 94, 0.15); animation: scaleUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .si-lgs-result-label { font-size: 16px; font-weight: 800; color: #15803d; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 10px; }
        .si-lgs-result-score { font-size: 52px; font-weight: 900; color: #0f172a; line-height: 1; text-shadow: 2px 2px 0px #fff; }
        .si-info-text { text-align:center; font-size:13px; color:#64748b; margin-top:20px; background: #f1f5f9; padding: 15px; border-radius: 8px;}
        @media (max-width: 768px) { .si-lgs-grid { grid-template-columns: 1fr; } .si-lgs-wrapper{ padding: 25px 15px; } }
        @keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>

    <div class="si-lgs-wrapper">
        <div class="si-lgs-header">
            <h3 class="si-lgs-title"><span><?php echo $guncel_yil; ?></span> LGS Puan Hesaplama</h3>
            <p class="si-lgs-desc">3 Yanlış 1 Doğruyu Götürür. Yeni nesil tahmini katsayılarla en gerçekçi sonuç.</p>
        </div>
        
        <form method="post" action="#lgs-sonuc">
            <?php 
            $si_lgs_kart_uret = function($ders_adi, $soru_sayisi, $name_prefix, $icon_class, $svg_path) {
                echo "<div class='si-lgs-card' data-max='$soru_sayisi'><div class='si-lgs-card-header'><div class='si-lgs-icon-box $icon_class'><svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'>$svg_path</svg></div><div><h4 class='si-lgs-subj-title'>$ders_adi</h4><span class='si-lgs-subj-meta'>$soru_sayisi Soru Sorulur</span></div></div><div class='si-lgs-inputs'><div class='si-lgs-input-group do'><label>Doğru</label><input type='number' name='{$name_prefix}_d' class='si-lgs-d' min='0' max='$soru_sayisi' placeholder='0'></div><div class='si-lgs-input-group ya'><label>Yanlış</label><input type='number' name='{$name_prefix}_y' class='si-lgs-y' min='0' max='$soru_sayisi' placeholder='0'></div><div class='si-lgs-input-group ne'><label>Net</label><input type='number' step='0.01' name='{$name_prefix}_n' class='si-lgs-n' value='0' readonly tabindex='-1'></div></div></div>";
            };
            ?>

            <div class="si-lgs-grid">
                <?php 
                $si_lgs_kart_uret("Türkçe", 20, "lgs_tr", "icon-tr", "<path d='M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z'></path><path d='M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z'></path>");
                $si_lgs_kart_uret("Matematik", 20, "lgs_mat", "icon-mat", "<rect x='4' y='4' width='16' height='16' rx='2' ry='2'></rect><line x1='8' y1='10' x2='16' y2='10'></line><line x1='8' y1='14' x2='16' y2='14'></line><line x1='12' y1='6' x2='12' y2='18'></line>");
                $si_lgs_kart_uret("Fen Bilimleri", 20, "lgs_fen", "icon-fen", "<path d='M9 3v13a3 3 0 0 0 6 0V3'></path><path d='M7 3h10'></path><path d='M9 10h6'></path>");
                $si_lgs_kart_uret("T.C. İnkılap Tarihi", 10, "lgs_ink", "icon-ink", "<circle cx='12' cy='12' r='10'></circle><polyline points='12 6 12 12 16 14'></polyline>");
                $si_lgs_kart_uret("Din Kültürü", 10, "lgs_din", "icon-din", "<path d='M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'></path>");
                $si_lgs_kart_uret("Yabancı Dil", 10, "lgs_ing", "icon-ing", "<circle cx='12' cy='12' r='10'></circle><line x1='2' y1='12' x2='22' y2='12'></line><path d='M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z'></path>");
                ?>
            </div>

            <button type="submit" name="hesapla_lgs" class="si-btn-lgs">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                LGS PUANIMI HESAPLA
            </button>
        </form>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const lgsCards = document.querySelectorAll('.si-lgs-card');
                lgsCards.forEach(card => {
                    const dInput = card.querySelector('.si-lgs-d'); const yInput = card.querySelector('.si-lgs-y'); const nInput = card.querySelector('.si-lgs-n');
                    const maxQ = parseInt(card.getAttribute('data-max'));
                    function calculateLGSNet() {
                        let d = parseInt(dInput.value) || 0; let y = parseInt(yInput.value) || 0;
                        if (d + y > maxQ) { yInput.value = maxQ - d; y = maxQ - d; }
                        let net = d - (y / 3); nInput.value = net > 0 ? net.toFixed(2) : 0;
                    }
                    dInput.addEventListener('input', calculateLGSNet); yInput.addEventListener('input', calculateLGSNet);
                });
            });
        </script>

        <?php
        if(isset($_POST['hesapla_lgs'])) {
            $n = function($isim) { return si_post_net($isim, 3); };
            $tr = $n('lgs_tr'); $mat = $n('lgs_mat'); $fen = $n('lgs_fen');
            $ink = $n('lgs_ink'); $din = $n('lgs_din'); $ing = $n('lgs_ing');

            // ADMİN PANELİNDEN VERİLERİ ÇEKİYORUZ!
            $taban_puan = floatval(get_option('si_lgs_taban', '194.76'));
            $k_tr = floatval(get_option('si_lgs_k_tr', '3.90'));
            $k_mat = floatval(get_option('si_lgs_k_mat', '5.10'));
            $k_fen = floatval(get_option('si_lgs_k_fen', '4.00'));
            $k_ink = floatval(get_option('si_lgs_k_ink', '1.50'));
            $k_din = floatval(get_option('si_lgs_k_din', '1.55'));
            $k_ing = floatval(get_option('si_lgs_k_ing', '1.474'));

            $hesaplanan_ek_puan = ($tr * $k_tr) + ($mat * $k_mat) + ($fen * $k_fen) + ($ink * $k_ink) + ($din * $k_din) + ($ing * $k_ing);
            $lgs_puan = $taban_puan + $hesaplanan_ek_puan;
            
            if ($lgs_puan > 500) $lgs_puan = 500;
            if ($lgs_puan < 100) $lgs_puan = 100;

            echo '<div id="lgs-sonuc" class="si-lgs-result-box">';
            echo '<span class="si-lgs-result-label">LGS Puanınız</span>';
            echo '<span class="si-lgs-result-score">' . number_format($lgs_puan, 4, ',', '.') . '</span>';
            echo '</div>';
            echo '<div class="si-info-text" style="background:#fff; border:1px solid #e2e8f0;"><strong>Önemli Bilgilendirme:</strong> Bu hesaplama aracının resmi bir geçerliliği yoktur. Sonuçlar, MEB\'in geçmiş yıllarda uyguladığı ağırlıklı standart sapma verilerine dayanarak gerçeğe en yakın şekilde tahmin edilmektedir. LGS sınavında 3 yanlış 1 doğruyu götürmektedir.</div>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('lgs_hesapla', 'soruindir_lgs_hesapla_shortcode');

// ========================================================================
// 3. YKS PUAN HESAPLAMA MODÜLÜ (GÜNCELLENMİŞ)
// ========================================================================
function soruindir_yks_hesapla_shortcode() {
    // "YKS Puan Hesaplama ve Tercih Robotu" eklentisi kuruluysa ÖSYM 2026 verileriyle çalışan yeni hesaplayıcı gösterilir
    if (shortcode_exists('yks_puan_hesaplama')) return do_shortcode('[yks_puan_hesaplama]');
    ob_start(); 
    $guncel_yil = date("Y");
    ?>
    <style>
        .si-yks-wrapper { background: #ffffff; border: 2px solid #e2e8f0; border-radius: 16px; padding: 30px; max-width: 900px; margin: 0 auto 30px auto; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .si-yks-title { text-align: center; color: #1e293b; font-size: 24px; font-weight: 800; margin-bottom: 25px; border-bottom: 2px dashed #e2e8f0; padding-bottom: 15px; }
        .si-yks-title span { color: #3b82f6; }
        .si-yks-top-bar { display:flex; justify-content:center; margin-bottom:25px; }
        .si-yks-top-bar input { padding: 10px; font-size: 16px; font-weight: bold; border-radius: 8px; border: 2px solid #3b82f6; text-align:center; outline:none; }
        .si-table-container { margin-bottom: 30px; overflow-x: auto; }
        .si-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 0 0 1px #e2e8f0; }
        .si-table th { background: #f1f5f9; color: #334155; font-weight: 700; padding: 12px; text-align: center; border-bottom: 2px solid #cbd5e1; }
        .si-table th:first-child { text-align: left; }
        .si-table td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .si-subj-name { font-weight: 600; color: #1e293b; display: block; }
        .si-subj-count { font-size: 12px; color: #64748b; }
        .si-input-cell { text-align: center; width: 80px; }
        .si-input-cell input { width: 100%; padding: 8px; text-align: center; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; }
        .si-input-cell input[readonly] { background: #f1f5f9; border-color: transparent; }
        .si-cat-header { background: #3b82f6 !important; color: #fff !important; font-size: 18px; padding: 15px !important; }
        .si-cat-header.ayt-say { background: #16a34a !important; } .si-cat-header.ayt-soz { background: #ef4444 !important; } .si-cat-header.ydt { background: #8b5cf6 !important; }
        .si-btn { width: 100%; padding: 18px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; border: none; border-radius: 10px; font-size: 18px; font-weight: 700; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 15px rgba(37, 99, 235, 0.2); margin-top: 10px; }
        .si-results { margin-top: 30px; display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; }
        .si-result-card { background: #fff; border: 2px solid #e2e8f0; padding: 20px 15px; border-radius: 12px; text-align: center; }
        .si-result-card.tyt { border-color: #3b82f6; background: #eff6ff; } .si-result-card.say { border-color: #16a34a; background: #f0fdf4; } .si-result-card.ea { border-color: #f59e0b; background: #fffbeb; } .si-result-card.soz { border-color: #ef4444; background: #fef2f2; } .si-result-card.dil { border-color: #8b5cf6; background: #f5f3ff; }
        .si-score-label { display: block; font-size: 14px; font-weight: 700; color: #64748b; margin-bottom: 8px; text-transform: uppercase; }
        .si-score-val { font-size: 28px; font-weight: 900; color: #0f172a; }
        .si-info-text { text-align:center; font-size:13px; color:#64748b; margin-top:20px; background: #f1f5f9; padding: 15px; border-radius: 8px;}
    </style>

    <div class="si-yks-wrapper">
        <h3 class="si-yks-title"><span><?php echo $guncel_yil; ?></span> YKS Puan Hesaplama Robotu</h3>
        
        <form method="post" action="#yks-sonuc">
            <div class="si-yks-top-bar">
                <div>
                    <label for="diploma" style="font-weight: 700; color: #334155; margin-right: 10px;">Diploma Notu (OBP):</label>
                    <input type="number" step="0.01" name="diploma" id="diploma" placeholder="Örn: 85.50" min="50" max="100" required>
                </div>
            </div>

            <?php 
            $si_yks_satir = function($ders_adi, $soru_sayisi, $name_prefix) {
                echo "<tr class='si-calc-row' data-max='$soru_sayisi'>";
                echo "<td><span class='si-subj-name'>$ders_adi</span><span class='si-subj-count'>$soru_sayisi Soru</span></td>";
                echo "<td class='si-input-cell'><input type='number' name='{$name_prefix}_d' class='si-d' min='0' max='$soru_sayisi' placeholder='0'></td>";
                echo "<td class='si-input-cell'><input type='number' name='{$name_prefix}_y' class='si-y' min='0' max='$soru_sayisi' placeholder='0'></td>";
                echo "<td class='si-input-cell'><input type='number' step='0.25' name='{$name_prefix}_n' class='si-n' value='0' readonly tabindex='-1'></td>";
                echo "</tr>";
            };
            ?>

            <div class="si-table-container">
                <table class="si-table">
                    <thead><tr><th colspan="4" class="si-cat-header">TYT (Temel Yeterlilik Testi)</th></tr><tr><th>Dersler</th><th>Doğru</th><th>Yanlış</th><th>Net</th></tr></thead>
                    <tbody>
                        <?php $si_yks_satir("Türkçe", 40, "tyt_tr"); $si_yks_satir("Sosyal Bilimler", 20, "tyt_sos"); $si_yks_satir("Temel Matematik", 40, "tyt_mat"); $si_yks_satir("Fen Bilimleri", 20, "tyt_fen"); ?>
                    </tbody>
                    <thead><tr><th colspan="4" class="si-cat-header ayt-say">AYT - Sayısal / Eşit Ağırlık Temel</th></tr></thead>
                    <tbody>
                        <?php $si_yks_satir("Matematik", 40, "ayt_mat"); $si_yks_satir("Fizik", 14, "ayt_fiz"); $si_yks_satir("Kimya", 13, "ayt_kim"); $si_yks_satir("Biyoloji", 13, "ayt_biy"); ?>
                    </tbody>
                    <thead><tr><th colspan="4" class="si-cat-header ayt-soz">AYT - Sözel / Eşit Ağırlık Ekstra</th></tr></thead>
                    <tbody>
                        <?php $si_yks_satir("Türk Dili ve Edebiyatı", 24, "ayt_edb"); $si_yks_satir("Tarih-1", 10, "ayt_tar1"); $si_yks_satir("Coğrafya-1", 6, "ayt_cog1"); $si_yks_satir("Tarih-2", 11, "ayt_tar2"); $si_yks_satir("Coğrafya-2", 11, "ayt_cog2"); $si_yks_satir("Felsefe Grubu", 12, "ayt_fel"); $si_yks_satir("Din Kültürü", 6, "ayt_din"); ?>
                    </tbody>
                    <thead><tr><th colspan="4" class="si-cat-header ydt">YDT (Yabancı Dil Testi)</th></tr></thead>
                    <tbody><?php $si_yks_satir("Yabancı Dil", 80, "ydt_dil"); ?></tbody>
                </table>
            </div>
            <button type="submit" name="hesapla_yks" class="si-btn">YKS PUANLARIMI HESAPLA</button>
        </form>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll('.si-calc-row').forEach(row => {
                    const dInput = row.querySelector('.si-d'); const yInput = row.querySelector('.si-y'); const nInput = row.querySelector('.si-n');
                    const maxQ = parseInt(row.getAttribute('data-max'));
                    function calcYKSNet() {
                        let d = parseInt(dInput.value) || 0; let y = parseInt(yInput.value) || 0;
                        if (d + y > maxQ) { yInput.value = maxQ - d; y = maxQ - d; }
                        let net = d - (y / 4); nInput.value = net > 0 ? net.toFixed(2) : 0;
                    }
                    dInput.addEventListener('input', calcYKSNet); yInput.addEventListener('input', calcYKSNet);
                });
            });
        </script>

        <?php
        if(isset($_POST['hesapla_yks'])) {
            $diploma = floatval($_POST['diploma']);
            $obp_ekleme = ($diploma * 5) * 0.12; 
            
            $n = function($isim) { return si_post_net($isim, 4); };
            $tyt_tr = $n('tyt_tr'); $tyt_sos = $n('tyt_sos'); $tyt_mat = $n('tyt_mat'); $tyt_fen = $n('tyt_fen');
            $ayt_mat = $n('ayt_mat'); $ayt_fen_toplam = $n('ayt_fiz') + $n('ayt_kim') + $n('ayt_biy');
            $ayt_edebiyat_sos1 = $n('ayt_edb') + $n('ayt_tar1') + $n('ayt_cog1');
            $ayt_sos2 = $n('ayt_tar2') + $n('ayt_cog2') + $n('ayt_fel') + $n('ayt_din');
            $ydt_net = $n('ydt_dil');

            // ADMİN PANELİNDEN YKS VERİLERİNİ ÇEKİYORUZ!
            $y_taban = floatval(get_option('si_yks_taban', '100'));
            $k_tyt = floatval(get_option('si_yks_tyt_katsayi', '3.35'));
            $k_ayt = floatval(get_option('si_yks_ayt_katsayi', '3.00'));

            // YKS Puan Mantığı (Panelden gelen katsayılara göre)
            $tyt_ham = $y_taban + (($tyt_tr + $tyt_sos + $tyt_mat + $tyt_fen) * $k_tyt);
            $tyt_yerlestirme = ($tyt_ham >= 150) ? $tyt_ham + $obp_ekleme : 0; 
            
            // TYT'nin AYT'ye tahmini etkisi (TYT Net Toplamı * 1.33 civarı)
            $tyt_ayt_etkisi = ($tyt_tr + $tyt_sos + $tyt_mat + $tyt_fen) * 1.33;

            $say_ham = $y_taban + $tyt_ayt_etkisi + (($ayt_mat + $ayt_fen_toplam) * $k_ayt);
            $ea_ham  = $y_taban + $tyt_ayt_etkisi + (($ayt_mat + $ayt_edebiyat_sos1) * $k_ayt);
            $soz_ham = $y_taban + $tyt_ayt_etkisi + (($ayt_edebiyat_sos1 + $ayt_sos2) * $k_ayt);
            $dil_ham = $y_taban + $tyt_ayt_etkisi + ($ydt_net * $k_ayt);

            echo '<div id="yks-sonuc" class="si-results">';
            echo '<div class="si-result-card tyt"><span class="si-score-label">TYT YERLEŞTİRME</span><span class="si-score-val">' . ($tyt_yerlestirme > 0 ? number_format($tyt_yerlestirme, 4, ',', '.') : 'Geçersiz') . '</span></div>';
            if($ayt_mat > 0 || $ayt_fen_toplam > 0) echo '<div class="si-result-card say"><span class="si-score-label">SAY YERLEŞTİRME</span><span class="si-score-val">' . number_format($say_ham + $obp_ekleme, 4, ',', '.') . '</span></div>';
            if($ayt_mat > 0 || $ayt_edebiyat_sos1 > 0) echo '<div class="si-result-card ea"><span class="si-score-label">EA YERLEŞTİRME</span><span class="si-score-val">' . number_format($ea_ham + $obp_ekleme, 4, ',', '.') . '</span></div>';
            if($ayt_edebiyat_sos1 > 0 || $ayt_sos2 > 0) echo '<div class="si-result-card soz"><span class="si-score-label">SÖZ YERLEŞTİRME</span><span class="si-score-val">' . number_format($soz_ham + $obp_ekleme, 4, ',', '.') . '</span></div>';
            if($ydt_net > 0) echo '<div class="si-result-card dil"><span class="si-score-label">DİL YERLEŞTİRME</span><span class="si-score-val">' . number_format($dil_ham + $obp_ekleme, 4, ',', '.') . '</span></div>';
            echo '</div>';
            echo '<div class="si-info-text"><strong>Önemli Bilgilendirme:</strong> Bu hesaplama aracının hiçbir <strong>resmi geçerliliği yoktur</strong> ve sonuçlar tamamen <strong>tahminidir</strong>. ÖSYM her yıl sınavın zorluk derecesine ve o yıl sınava giren adayların ortalamasına göre farklı standart sapmalar uyguladığı için, puanınız gerçeğe kıyasla veya diğer sitelere göre bir miktar düşük ya da yüksek çıkabilir. Yukarıdaki veriler, OBP (Okul Başarı Puanı) eklenmiş <strong>Yerleştirme</strong> puanlarınızı göstermektedir. Boş bıraktığınız bölümler karmaşa yaratmamak için gizlenmiştir.</div>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('yks_hesapla', 'soruindir_yks_hesapla_shortcode');
// ========================================================================
// 4. KPSS (ÖN LİSANS VE ORTAÖĞRETİM) PUAN HESAPLAMA MODÜLÜ
// ========================================================================
function soruindir_kpss_hesapla_shortcode() {
    ob_start(); 
    $guncel_yil = date("Y");
    ?>
    <style>
        .si-kpss-wrapper { background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 20px; padding: 40px 30px; max-width: 900px; margin: 0 auto 30px auto; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.04); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .si-kpss-header { text-align: center; margin-bottom: 35px; }
        .si-kpss-title { color: #0f172a; font-size: 28px; font-weight: 900; margin: 0 0 10px 0; letter-spacing: -0.5px; }
        .si-kpss-title span { color: #8b5cf6; }
        .si-kpss-desc { color: #64748b; font-size: 15px; margin: 0; }
        .si-kpss-top-bar { background: #fff; padding: 20px; border-radius: 16px; display: flex; justify-content: center; align-items: center; gap: 15px; margin-bottom: 30px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(0,0,0,0.02); flex-wrap: wrap;}
        .si-kpss-top-bar select { padding: 12px 20px; font-size: 16px; font-weight: 800; border-radius: 10px; border: 2px solid #8b5cf6; color: #6d28d9; cursor: pointer; outline: none; background: #f5f3ff; transition: 0.3s; }
        .si-kpss-section-title { font-size: 20px; font-weight: 800; color: #334155; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; display: inline-block;}
        .si-kpss-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 35px; }
        .si-kpss-card { background: #fff; border-radius: 16px; padding: 20px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 15px; transition: all 0.3s ease; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
        .si-kpss-card:hover { transform: translateY(-5px); border-color: #8b5cf6; box-shadow: 0 10px 25px rgba(139, 92, 246, 0.15); }
        .si-kpss-card-header { display: flex; align-items: center; gap: 15px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 15px; }
        .si-kpss-icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .si-kpss-icon-box svg { width: 24px; height: 24px; }
        .si-kpss-subj-title { font-size: 16px; font-weight: 800; color: #1e293b; margin: 0; display: block; }
        .si-kpss-subj-meta { font-size: 12px; font-weight: 600; color: #94a3b8; display: block; margin-top: 4px; }
        .k-icon-tr { background: #eff6ff; color: #3b82f6; } .k-icon-mat { background: #fdf4ff; color: #d946ef; } .k-icon-tar { background: #fffbeb; color: #d97706; } .k-icon-cog { background: #f0fdf4; color: #16a34a; } .k-icon-vat { background: #fef2f2; color: #dc2626; }
        .si-kpss-inputs { display: flex; justify-content: space-between; gap: 10px; }
        .si-kpss-input-group { flex: 1; text-align: center; }
        .si-kpss-input-group label { display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 8px; }
        .si-kpss-input-group input { width: 100%; padding: 12px 5px; text-align: center; font-size: 16px; font-weight: 800; border-radius: 10px; border: 2px solid #e2e8f0; color: #0f172a; transition: 0.3s; box-sizing: border-box; }
        .si-kpss-input-group.do input:focus { border-color: #8b5cf6; background: #f5f3ff; outline: none; }
        .si-kpss-input-group.ya input:focus { border-color: #ef4444; background: #fef2f2; outline: none; }
        .si-kpss-input-group.ne input { background: #f8fafc; border-color: transparent; color: #8b5cf6; }
        .si-btn-kpss { width: 100%; padding: 20px; background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: #fff; border: none; border-radius: 14px; font-size: 20px; font-weight: 900; letter-spacing: 0.5px; cursor: pointer; transition: 0.3s; box-shadow: 0 8px 25px rgba(139, 92, 246, 0.3); display: flex; align-items: center; justify-content: center; gap: 10px; }
        .si-btn-kpss:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(139, 92, 246, 0.4); }
        .si-kpss-result-box { margin-top: 30px; background: linear-gradient(135deg, #ffffff, #f5f3ff); border: 2px solid #8b5cf6; border-radius: 20px; padding: 35px 20px; text-align: center; box-shadow: 0 10px 30px rgba(139, 92, 246, 0.15); animation: scaleUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .si-kpss-result-label { font-size: 16px; font-weight: 800; color: #6d28d9; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 10px; }
        .si-kpss-result-score { font-size: 52px; font-weight: 900; color: #0f172a; line-height: 1; text-shadow: 2px 2px 0px #fff; }
                .si-info-text { text-align:center; font-size:13px; color:#64748b; margin-top:20px; background: #f1f5f9; padding: 15px; border-radius: 8px;}
        @keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        
        /* MOBİL UYUMLULUK DÜZELTMESİ (TAŞMAYI ENGELLER) */
        @media (max-width: 768px) { 
            .si-kpss-wrapper { padding: 25px 15px; } 
            .si-kpss-grid { grid-template-columns: 1fr; gap: 15px; } 
            .si-kpss-top-bar { flex-direction: column; text-align: center; }
            .si-kpss-top-bar select { width: 100%; box-sizing: border-box; }
        }
    </style>


    <div class="si-kpss-wrapper">
        <div class="si-kpss-header">
            <h3 class="si-kpss-title"><span><?php echo $guncel_yil; ?></span> KPSS Puan Hesaplama</h3>
            <p class="si-kpss-desc">Lisans (P3), Ön Lisans (P93) ve Ortaöğretim (P94) adayları içindir. (4 Yanlış 1 Doğruyu Götürür)</p>
        </div>
        
        <form method="post" action="#kpss-sonuc">
            <div class="si-kpss-top-bar">
                <span style="font-weight: 700; color: #334155;">Sınav Türünü Seçin:</span>
                <?php $si_kpss_sec = isset($_POST['kpss_tur']) ? sanitize_key($_POST['kpss_tur']) : 'lisans'; ?>
                <select name="kpss_tur">
                    <option value="lisans" <?php selected($si_kpss_sec, 'lisans'); ?>>KPSS Lisans (P3)</option>
                    <option value="onlisans" <?php selected($si_kpss_sec, 'onlisans'); ?>>KPSS Ön Lisans (P93)</option>
                    <option value="ortaogretim" <?php selected($si_kpss_sec, 'ortaogretim'); ?>>KPSS Ortaöğretim (P94)</option>
                </select>
            </div>

            <?php 
            $si_kpss_kart_uret = function($ders_adi, $soru_sayisi, $name_prefix, $icon_class, $svg_path) {
                echo "<div class='si-kpss-card' data-max='$soru_sayisi'>";
                echo "<div class='si-kpss-card-header'><div class='si-kpss-icon-box $icon_class'><svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'>$svg_path</svg></div><div><h4 class='si-kpss-subj-title'>$ders_adi</h4><span class='si-kpss-subj-meta'>$soru_sayisi Soru</span></div></div>";
                echo "<div class='si-kpss-inputs'><div class='si-kpss-input-group do'><label>Doğru</label><input type='number' name='{$name_prefix}_d' class='si-kpss-d' min='0' max='$soru_sayisi' placeholder='0'></div><div class='si-kpss-input-group ya'><label>Yanlış</label><input type='number' name='{$name_prefix}_y' class='si-kpss-y' min='0' max='$soru_sayisi' placeholder='0'></div><div class='si-kpss-input-group ne'><label>Net</label><input type='number' step='0.25' name='{$name_prefix}_n' class='si-kpss-n' value='0' readonly tabindex='-1'></div></div>";
                echo "</div>";
            };
            ?>

            <span class="si-kpss-section-title">Genel Yetenek (60 Soru)</span>
            <div class="si-kpss-grid">
                <?php 
                $si_kpss_kart_uret("Türkçe", 30, "kpss_tr", "k-icon-tr", "<path d='M4 19.5A2.5 2.5 0 0 1 6.5 17H20'></path><path d='M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z'></path>");
                $si_kpss_kart_uret("Matematik", 30, "kpss_mat", "k-icon-mat", "<rect x='4' y='4' width='16' height='16' rx='2' ry='2'></rect><line x1='8' y1='10' x2='16' y2='10'></line><line x1='8' y1='14' x2='16' y2='14'></line><line x1='12' y1='6' x2='12' y2='18'></line>");
                ?>
            </div>

            <span class="si-kpss-section-title">Genel Kültür (60 Soru)</span>
            <div class="si-kpss-grid">
                <?php 
                $si_kpss_kart_uret("Tarih", 27, "kpss_tar", "k-icon-tar", "<rect x='4' y='4' width='16' height='16' rx='2' ry='2'></rect><rect x='9' y='9' width='6' height='6'></rect><line x1='9' y1='1' x2='9' y2='4'></line><line x1='15' y1='1' x2='15' y2='4'></line><line x1='9' y1='20' x2='9' y2='23'></line><line x1='15' y1='20' x2='15' y2='23'></line>");
                $si_kpss_kart_uret("Coğrafya", 18, "kpss_cog", "k-icon-cog", "<circle cx='12' cy='12' r='10'></circle><line x1='2' y1='12' x2='22' y2='12'></line><path d='M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z'></path>");
                $si_kpss_kart_uret("Vatandaşlık & Güncel", 15, "kpss_vat", "k-icon-vat", "<path d='M12 2v20'></path><path d='M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'></path>");
                ?>
            </div>

            <button type="submit" name="hesapla_kpss" class="si-btn-kpss">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                KPSS PUANIMI HESAPLA
            </button>
        </form>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const kpssCards = document.querySelectorAll('.si-kpss-card');
                kpssCards.forEach(card => {
                    const dInput = card.querySelector('.si-kpss-d'); const yInput = card.querySelector('.si-kpss-y'); const nInput = card.querySelector('.si-kpss-n');
                    const maxQ = parseInt(card.getAttribute('data-max'));
                    function calcKPSSNet() {
                        let d = parseInt(dInput.value) || 0; let y = parseInt(yInput.value) || 0;
                        if (d + y > maxQ) { yInput.value = maxQ - d; y = maxQ - d; }
                        let net = d - (y / 4); nInput.value = net > 0 ? net.toFixed(2) : 0;
                    }
                    dInput.addEventListener('input', calcKPSSNet); yInput.addEventListener('input', calcKPSSNet);
                });
            });
        </script>

        <?php
        if(isset($_POST['hesapla_kpss'])) {
            $sinav_tur = isset($_POST['kpss_tur']) ? sanitize_key($_POST['kpss_tur']) : 'lisans';
            if (!in_array($sinav_tur, array('lisans', 'onlisans', 'ortaogretim'), true)) $sinav_tur = 'lisans';
            $puan_etiketi = $sinav_tur === 'lisans' ? 'P3' : ($sinav_tur === 'onlisans' ? 'P93' : 'P94');
            $tur_adi = $sinav_tur === 'lisans' ? 'LİSANS' : ($sinav_tur === 'onlisans' ? 'ÖN LİSANS' : 'ORTAÖĞRETİM');

            $n = function($isim) { return si_post_net($isim, 4); };
            $gy_net = $n('kpss_tr') + $n('kpss_mat');
            $gk_net = $n('kpss_tar') + $n('kpss_cog') + $n('kpss_vat');

            // ADMİN PANELİNDEN KPSS VERİLERİNİ ÇEKİYORUZ
            $kpss_taban = floatval(get_option('si_kpss_taban', '45.000'));
            $k_gy = floatval(get_option('si_kpss_gy_katsayi', '0.485'));
            $k_gk = floatval(get_option('si_kpss_gk_katsayi', '0.450'));

            if ($sinav_tur === 'lisans') {
                // P3: ÖSYM yöntemi — GY ve GK standart puanları %50/%50, 100'lük dönüşüm (70 + 30 formülü)
                $gy_o = floatval(get_option('si_kpss_lis_gy_ort', '17.43')); $gy_s = max(0.1, floatval(get_option('si_kpss_lis_gy_ss', '9.11')));
                $gk_o = floatval(get_option('si_kpss_lis_gk_ort', '14.30')); $gk_s = max(0.1, floatval(get_option('si_kpss_lis_gk_ss', '9.26')));
                $kr = min(0.95, max(0, floatval(get_option('si_kpss_lis_r', '0.70'))));
                $asp = 0.5 * (50 + 10 * ($gy_net - $gy_o) / $gy_s) + 0.5 * (50 + 10 * ($gk_net - $gk_o) / $gk_s);
                $B = 0.5 * (50 + 10 * (60 - $gy_o) / $gy_s) + 0.5 * (50 + 10 * (60 - $gk_o) / $gk_s);
                $S = 10 * sqrt(0.5 + 0.5 * $kr);
                $kpss_puan = si_osym_100($asp, $S, $B);
                if ($gy_net <= 0 && $gk_net <= 0) $kpss_puan = 0;
            } else {
                // P93 / P94: panelden ayrı ayrı ayarlanabilen doğrusal katsayılar
                $on = $sinav_tur === 'onlisans' ? 'onl' : 'ort';
                $kpss_taban = floatval(get_option('si_kpss_' . $on . '_taban', $kpss_taban));
                $k_gy = floatval(get_option('si_kpss_' . $on . '_gy', $k_gy));
                $k_gk = floatval(get_option('si_kpss_' . $on . '_gk', $k_gk));
                $kpss_puan = $kpss_taban + ($gy_net * $k_gy) + ($gk_net * $k_gk);
                if ($kpss_puan > 100) $kpss_puan = 100;
                if ($kpss_puan < $kpss_taban && ($gy_net > 0 || $gk_net > 0)) $kpss_puan = $kpss_taban;
                if ($gy_net <= 0 && $gk_net <= 0) $kpss_puan = 0;
            }

            echo '<div id="kpss-sonuc" class="si-kpss-result-box">';
            echo '<span class="si-kpss-result-label">KPSS ' . $tur_adi . ' Puanınız (' . $puan_etiketi . ')</span>';
            echo '<span class="si-kpss-result-score">' . number_format($kpss_puan, 4, ',', '.') . '</span>';
            echo '</div>';

            echo '<div class="si-info-text" style="background:#fff; border:1px solid #e2e8f0;"><strong>Önemli Bilgilendirme:</strong> Bu hesaplama aracının resmi bir geçerliliği yoktur. Sonuçlar tahmini olup, ÖSYM\'nin her sınav döneminde açıkladığı standart sapmalara ve Türkiye ortalamasına göre resmi sonucunuz bir miktar farklılık gösterebilir. KPSS\'de 4 yanlış 1 doğruyu götürmektedir.</div>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('kpss_hesapla', 'soruindir_kpss_hesapla_shortcode');
// 5. TUS PUAN HESAPLAMA MODÜLÜ
// ========================================================================
function soruindir_tus_hesapla_shortcode() {
    ob_start(); 
    $guncel_yil = date("Y");
    ?>
    <style>
        .si-tus-wrapper { background: #fdf2f8; border: 2px solid #fbcfe8; border-radius: 20px; padding: 40px 30px; max-width: 900px; margin: 0 auto 30px auto; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.04); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .si-tus-header { text-align: center; margin-bottom: 35px; }
        .si-tus-title { color: #0f172a; font-size: 28px; font-weight: 900; margin: 0 0 10px 0; letter-spacing: -0.5px; }
        .si-tus-title span { color: #e11d48; }
        .si-tus-desc { color: #64748b; font-size: 15px; margin: 0; }
        
        .si-tus-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 35px; }
        .si-tus-card { background: #fff; border-radius: 16px; padding: 25px; border: 1px solid #fce7f3; display: flex; flex-direction: column; gap: 20px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(225, 29, 72, 0.05); }
        .si-tus-card:hover { transform: translateY(-5px); border-color: #e11d48; box-shadow: 0 10px 25px rgba(225, 29, 72, 0.15); }
        
        .si-tus-card-header { display: flex; align-items: center; gap: 15px; border-bottom: 1px dashed #fce7f3; padding-bottom: 15px; }
        .si-tus-icon-box { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: #fff1f2; color: #e11d48; }
        .si-tus-icon-box svg { width: 28px; height: 28px; }
        .si-tus-subj-title { font-size: 18px; font-weight: 800; color: #1e293b; margin: 0; display: block; }
        .si-tus-subj-meta { font-size: 13px; font-weight: 600; color: #94a3b8; display: block; margin-top: 4px; }
        
        .si-tus-inputs { display: flex; justify-content: space-between; gap: 12px; }
        .si-tus-input-group { flex: 1; text-align: center; }
        .si-tus-input-group label { display: block; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 8px; }
        .si-tus-input-group input { width: 100%; padding: 14px 5px; text-align: center; font-size: 18px; font-weight: 800; border-radius: 10px; border: 2px solid #e2e8f0; color: #0f172a; transition: 0.3s; box-sizing: border-box; }
        
        .si-tus-input-group.do input:focus { border-color: #10b981; background: #ecfdf5; outline: none; }
        .si-tus-input-group.ya input:focus { border-color: #ef4444; background: #fef2f2; outline: none; }
        .si-tus-input-group.ne input { background: #f8fafc; border-color: transparent; color: #e11d48; }

        .si-btn-tus { width: 100%; padding: 22px; background: linear-gradient(135deg, #e11d48, #be123c); color: #fff; border: none; border-radius: 14px; font-size: 20px; font-weight: 900; letter-spacing: 0.5px; cursor: pointer; transition: 0.3s; box-shadow: 0 8px 25px rgba(225, 29, 72, 0.3); display: flex; align-items: center; justify-content: center; gap: 10px; }
        .si-btn-tus:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(225, 29, 72, 0.4); }
        
        .si-tus-results { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px; animation: scaleUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .si-tus-result-box { background: #fff; border: 2px solid #e11d48; border-radius: 16px; padding: 25px 15px; text-align: center; box-shadow: 0 10px 25px rgba(225, 29, 72, 0.1); }
        .si-tus-result-label { font-size: 14px; font-weight: 800; color: #be123c; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 8px; }
        .si-tus-result-score { font-size: 42px; font-weight: 900; color: #0f172a; line-height: 1; }

        .si-info-text { text-align:center; font-size:13px; color:#64748b; margin-top:20px; background: #fff; padding: 15px; border-radius: 8px; border:1px solid #fce7f3;}
        
        @media (max-width: 768px) { 
            .si-tus-wrapper { padding: 25px 15px; } 
            .si-tus-grid, .si-tus-results { grid-template-columns: 1fr; gap: 15px; } 
        }
        @keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>

    <div class="si-tus-wrapper">
        <div class="si-tus-header">
            <h3 class="si-tus-title"><span><?php echo $guncel_yil; ?></span> TUS Puan Hesaplama</h3>
            <p class="si-tus-desc">Tıpta Uzmanlık Eğitimi Giriş Sınavı (4 Yanlış 1 Doğruyu Götürür)</p>
        </div>
        
        <form method="post" action="#tus-sonuc">
            
            <div class="si-tus-grid">
                                <div class="si-tus-card" data-max="100">
                    <div class="si-tus-card-header">
                        <div class="si-tus-icon-box">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v7.31"></path><path d="M14 9.3V1.99"></path><path d="M8.5 2h7"></path><path d="M14 9.3a6.5 6.5 0 1 1-4 0"></path><path d="M5.52 16h12.96"></path></svg>
                        </div>
                        <div>
                            <h4 class="si-tus-subj-title">Temel Tıp Bilimleri Testi</h4>
                            <span class="si-tus-subj-meta">100 Soru (Anatomi, Fizyoloji, Biyokimya vb.)</span>
                        </div>
                    </div>
                    <div class="si-tus-inputs">
                        <div class="si-tus-input-group do"><label>Doğru</label><input type="number" name="tus_temel_d" class="si-tus-d" min="0" max="100" placeholder="0"></div>
                        <div class="si-tus-input-group ya"><label>Yanlış</label><input type="number" name="tus_temel_y" class="si-tus-y" min="0" max="100" placeholder="0"></div>
                        <div class="si-tus-input-group ne"><label>Net</label><input type="number" step="0.25" name="tus_temel_n" class="si-tus-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>

                <div class="si-tus-card" data-max="100">
                    <div class="si-tus-card-header">
                        <div class="si-tus-icon-box">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
                        </div>
                        <div>
                            <h4 class="si-tus-subj-title">Klinik Tıp Bilimleri Testi</h4>
                            <span class="si-tus-subj-meta">100 Soru (Dahiliye, Pediatri, Cerrahi vb.)</span>
                        </div>
                    </div>
                    <div class="si-tus-inputs">
                        <div class="si-tus-input-group do"><label>Doğru</label><input type="number" name="tus_klinik_d" class="si-tus-d" min="0" max="100" placeholder="0"></div>
                        <div class="si-tus-input-group ya"><label>Yanlış</label><input type="number" name="tus_klinik_y" class="si-tus-y" min="0" max="100" placeholder="0"></div>
                        <div class="si-tus-input-group ne"><label>Net</label><input type="number" step="0.25" name="tus_klinik_n" class="si-tus-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>

            </div>

            <button type="submit" name="hesapla_tus" class="si-btn-tus">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
                TUS PUANLARIMI HESAPLA
            </button>
        </form>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const tusCards = document.querySelectorAll('.si-tus-card');
                tusCards.forEach(card => {
                    const dInput = card.querySelector('.si-tus-d'); const yInput = card.querySelector('.si-tus-y'); const nInput = card.querySelector('.si-tus-n');
                    const maxQ = parseInt(card.getAttribute('data-max'));
                    function calcTUSNet() {
                        let d = parseInt(dInput.value) || 0; let y = parseInt(yInput.value) || 0;
                        if (d + y > maxQ) { yInput.value = maxQ - d; y = maxQ - d; }
                        let net = d - (y / 4); nInput.value = net > 0 ? net.toFixed(2) : 0;
                    }
                    dInput.addEventListener('input', calcTUSNet); yInput.addEventListener('input', calcTUSNet);
                });
            });
        </script>

                <?php
        if(isset($_POST['hesapla_tus'])) {
            $n = function($isim) { return si_post_net($isim, 4); };
            $temel_net = $n('tus_temel');
            $klinik_net = $n('tus_klinik');

            // ADMİN PANELİNDEN ÖSYM ORTALAMA VE SAPMA VERİLERİNİ ÇEKİYORUZ
            $temel_ort = floatval(get_option('si_tus_temel_ort', '40.00'));
            $temel_ss = floatval(get_option('si_tus_temel_ss', '15.00'));
            $klinik_ort = floatval(get_option('si_tus_klinik_ort', '45.00'));
            $klinik_ss = floatval(get_option('si_tus_klinik_ss', '15.00'));

            // ÖSYM GERÇEK Z-SKORU VE STANDART PUAN (SP) ALGORİTMASI
            // 1. Adım: Adayın netinin Z-Skorunu bul (ÖSYM Formülü)
            $z_temel = ($temel_ss > 0) ? (($temel_net - $temel_ort) / $temel_ss) : 0;
            $z_klinik = ($klinik_ss > 0) ? (($klinik_net - $klinik_ort) / $klinik_ss) : 0;

            // 2. Adım: Z-Skorunu 50 Ortalama, 10 Sapmalı Standart Puana (SP) Çevir
            $sp_temel = 50 + (10 * $z_temel);
            $sp_klinik = 50 + (10 * $z_klinik);

            // 3. Adım: Ağırlıklı TUS Puanlarını Hesapla
            // K Puanı (Klinik): %50 Temel + %50 Klinik
            $k_puani = ($sp_temel * 0.50) + ($sp_klinik * 0.50);
            
            // T Puanı (Temel): %70 Temel + %30 Klinik
            $t_puani = ($sp_temel * 0.70) + ($sp_klinik * 0.30);
            
            if ($t_puani > 85) $t_puani = 85;
            if ($k_puani > 85) $k_puani = 85;
            
            if ($temel_net <= 0 && $klinik_net <= 0) {
                $t_puani = 0; $k_puani = 0;
            }

            echo '<div id="tus-sonuc" class="si-tus-results">';

            echo '<div class="si-tus-result-box"><span class="si-tus-result-label">Klinik Tıp Puanı (K-Puanı)</span><span class="si-tus-result-score">' . number_format($k_puani, 4, ',', '.') . '</span></div>';
            echo '<div class="si-tus-result-box"><span class="si-tus-result-label">Temel Tıp Puanı (T-Puanı)</span><span class="si-tus-result-score">' . number_format($t_puani, 4, ',', '.') . '</span></div>';
            echo '</div>';

            echo '<div class="si-info-text"><strong>Önemli Bilgilendirme:</strong> Bu hesaplama aracının resmi bir geçerliliği yoktur. Sonuçlar tahmini olup, ÖSYM\'nin her sınav döneminde açıkladığı standart sapmalara göre farklılık gösterebilir. TUS sınavında 4 yanlış 1 doğruyu götürmektedir.</div>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('tus_hesapla', 'soruindir_tus_hesapla_shortcode');
// 6. ALES PUAN HESAPLAMA MODÜLÜ
// ========================================================================
function soruindir_ales_hesapla_shortcode() {
    ob_start(); 
    $guncel_yil = date("Y");
    ?>
    <style>
        .si-ales-wrapper { background: #f5f8ff; border: 2px solid #e0e7ff; border-radius: 20px; padding: 40px 30px; max-width: 900px; margin: 0 auto 30px auto; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.04); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .si-ales-header { text-align: center; margin-bottom: 35px; }
        .si-ales-title { color: #0f172a; font-size: 28px; font-weight: 900; margin: 0 0 10px 0; letter-spacing: -0.5px; }
        .si-ales-title span { color: #4f46e5; }
        .si-ales-desc { color: #64748b; font-size: 15px; margin: 0; }
        
        .si-ales-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 35px; }
        .si-ales-card { background: #fff; border-radius: 16px; padding: 25px; border: 1px solid #e0e7ff; display: flex; flex-direction: column; gap: 20px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.05); }
        .si-ales-card:hover { transform: translateY(-5px); border-color: #4f46e5; box-shadow: 0 10px 25px rgba(79, 70, 229, 0.15); }
        
        .si-ales-card-header { display: flex; align-items: center; gap: 15px; border-bottom: 1px dashed #e0e7ff; padding-bottom: 15px; }
        .si-ales-icon-box { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: #eef2ff; color: #4f46e5; }
        .si-ales-icon-box.soz { background: #fffbeb; color: #f59e0b; }
        .si-ales-icon-box svg { width: 28px; height: 28px; }
        .si-ales-subj-title { font-size: 18px; font-weight: 800; color: #1e293b; margin: 0; display: block; }
        .si-ales-subj-meta { font-size: 13px; font-weight: 600; color: #94a3b8; display: block; margin-top: 4px; }
        
        .si-ales-inputs { display: flex; justify-content: space-between; gap: 12px; }
        .si-ales-input-group { flex: 1; text-align: center; }
        .si-ales-input-group label { display: block; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 8px; }
        .si-ales-input-group input { width: 100%; padding: 14px 5px; text-align: center; font-size: 18px; font-weight: 800; border-radius: 10px; border: 2px solid #e2e8f0; color: #0f172a; transition: 0.3s; box-sizing: border-box; }
        
        .si-ales-input-group.do input:focus { border-color: #10b981; background: #ecfdf5; outline: none; }
        .si-ales-input-group.ya input:focus { border-color: #ef4444; background: #fef2f2; outline: none; }
        .si-ales-input-group.ne input { background: #f8fafc; border-color: transparent; color: #4f46e5; }

        .si-btn-ales { width: 100%; padding: 22px; background: linear-gradient(135deg, #4f46e5, #3730a3); color: #fff; border: none; border-radius: 14px; font-size: 20px; font-weight: 900; letter-spacing: 0.5px; cursor: pointer; transition: 0.3s; box-shadow: 0 8px 25px rgba(79, 70, 229, 0.3); display: flex; align-items: center; justify-content: center; gap: 10px; }
        .si-btn-ales:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(79, 70, 229, 0.4); }
        
        .si-ales-results { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 30px; animation: scaleUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .si-ales-result-box { background: #fff; border: 2px solid #e2e8f0; border-radius: 16px; padding: 25px 15px; text-align: center; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05); }
        .si-ales-result-box.say { border-color: #4f46e5; background: #eef2ff; }
        .si-ales-result-box.ea { border-color: #10b981; background: #ecfdf5; }
        .si-ales-result-box.soz { border-color: #f59e0b; background: #fffbeb; }
        .si-ales-result-label { font-size: 15px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 8px; }
        .si-ales-result-score { font-size: 38px; font-weight: 900; color: #0f172a; line-height: 1; }

        .si-info-text { text-align:center; font-size:13px; color:#64748b; margin-top:20px; background: #fff; padding: 15px; border-radius: 8px; border:1px solid #e0e7ff;}
        
        @media (max-width: 768px) { 
            .si-ales-wrapper { padding: 25px 15px; } 
            .si-ales-grid, .si-ales-results { grid-template-columns: 1fr; gap: 15px; } 
        }
        @keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>

    <div class="si-ales-wrapper">
        <div class="si-ales-header">
            <h3 class="si-ales-title"><span><?php echo $guncel_yil; ?></span> ALES Puan Hesaplama</h3>
            <p class="si-ales-desc">Akademik Personel ve Lisansüstü Eğitimi Giriş Sınavı (4 Yanlış 1 Doğruyu Götürür)</p>
        </div>
        
        <form method="post" action="#ales-sonuc">
            
            <div class="si-ales-grid">
                <div class="si-ales-card" data-max="50">
                    <div class="si-ales-card-header">
                        <div class="si-ales-icon-box">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><line x1="8" y1="10" x2="16" y2="10"></line><line x1="8" y1="14" x2="16" y2="14"></line><line x1="12" y1="6" x2="12" y2="18"></line></svg>
                        </div>
                        <div>
                            <h4 class="si-ales-subj-title">Sayısal Testi</h4>
                            <span class="si-ales-subj-meta">50 Soru (Matematik, Geometri vb.)</span>
                        </div>
                    </div>
                    <div class="si-ales-inputs">
                        <div class="si-ales-input-group do"><label>Doğru</label><input type="number" name="ales_say_d" class="si-ales-d" min="0" max="50" placeholder="0"></div>
                        <div class="si-ales-input-group ya"><label>Yanlış</label><input type="number" name="ales_say_y" class="si-ales-y" min="0" max="50" placeholder="0"></div>
                        <div class="si-ales-input-group ne"><label>Net</label><input type="number" step="0.25" name="ales_say_n" class="si-ales-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>

                <div class="si-ales-card" data-max="50">
                    <div class="si-ales-card-header">
                        <div class="si-ales-icon-box soz">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                        </div>
                        <div>
                            <h4 class="si-ales-subj-title">Sözel Testi</h4>
                            <span class="si-ales-subj-meta">50 Soru (Türkçe, Mantık vb.)</span>
                        </div>
                    </div>
                    <div class="si-ales-inputs">
                        <div class="si-ales-input-group do"><label>Doğru</label><input type="number" name="ales_soz_d" class="si-ales-d" min="0" max="50" placeholder="0"></div>
                        <div class="si-ales-input-group ya"><label>Yanlış</label><input type="number" name="ales_soz_y" class="si-ales-y" min="0" max="50" placeholder="0"></div>
                        <div class="si-ales-input-group ne"><label>Net</label><input type="number" step="0.25" name="ales_soz_n" class="si-ales-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>
            </div>

            <button type="submit" name="hesapla_ales" class="si-btn-ales">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                ALES PUANLARIMI HESAPLA
            </button>
        </form>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const alesCards = document.querySelectorAll('.si-ales-card');
                alesCards.forEach(card => {
                    const dInput = card.querySelector('.si-ales-d'); const yInput = card.querySelector('.si-ales-y'); const nInput = card.querySelector('.si-ales-n');
                    const maxQ = parseInt(card.getAttribute('data-max'));
                    function calcALESNet() {
                        let d = parseInt(dInput.value) || 0; let y = parseInt(yInput.value) || 0;
                        if (d + y > maxQ) { yInput.value = maxQ - d; y = maxQ - d; }
                        let net = d - (y / 4); nInput.value = net > 0 ? net.toFixed(2) : 0;
                    }
                    dInput.addEventListener('input', calcALESNet); yInput.addEventListener('input', calcALESNet);
                });
            });
        </script>

                <?php
        if(isset($_POST['hesapla_ales'])) {
            $n = function($isim) { return si_post_net($isim, 4); };
            $say_net = $n('ales_say');
            $soz_net = $n('ales_soz');

            // ADMİN PANELİNDEN DOĞRUSAL ÇARPANLARI ÇEKİYORUZ
            $s_taban = floatval(get_option('si_ales_say_taban', '55.00000'));
            $s_k_say = floatval(get_option('si_ales_say_k_say', '0.68765'));
            $s_k_soz = floatval(get_option('si_ales_say_k_soz', '0.22920'));

            $ea_taban = floatval(get_option('si_ales_ea_taban', '55.00000'));
            $ea_k_say = floatval(get_option('si_ales_ea_k_say', '0.46924'));
            $ea_k_soz = floatval(get_option('si_ales_ea_k_soz', '0.46924'));

            $z_taban = floatval(get_option('si_ales_soz_taban', '55.00000'));
            $z_k_say = floatval(get_option('si_ales_soz_k_say', '0.23566'));
            $z_k_soz = floatval(get_option('si_ales_soz_k_soz', '0.70698'));

            // BASİT VE NOKTA ATIŞI HESAPLAMA MANTIĞI
            $ales_say_puan = $s_taban + ($say_net * $s_k_say) + ($soz_net * $s_k_soz);
            $ales_ea_puan  = $ea_taban + ($say_net * $ea_k_say) + ($soz_net * $ea_k_soz);
            $ales_soz_puan = $z_taban + ($say_net * $z_k_say) + ($soz_net * $z_k_soz);
            
            // Maksimum Puan Sınırı
            if ($ales_say_puan > 100) $ales_say_puan = 100;
            if ($ales_ea_puan > 100) $ales_ea_puan = 100;
            if ($ales_soz_puan > 100) $ales_soz_puan = 100;
            
            // Hiç net yoksa 0 çıksın
            if ($say_net <= 0 && $soz_net <= 0) {
                $ales_say_puan = 0; $ales_ea_puan = 0; $ales_soz_puan = 0;
            }

            echo '<div id="ales-sonuc" class="si-ales-results">';

            echo '<div class="si-ales-result-box say"><span class="si-ales-result-label">ALES SAYISAL</span><span class="si-ales-result-score">' . number_format($ales_say_puan, 4, ',', '.') . '</span></div>';
            echo '<div class="si-ales-result-box ea"><span class="si-ales-result-label">ALES EŞİT AĞIRLIK</span><span class="si-ales-result-score">' . number_format($ales_ea_puan, 4, ',', '.') . '</span></div>';
            echo '<div class="si-ales-result-box soz"><span class="si-ales-result-label">ALES SÖZEL</span><span class="si-ales-result-score">' . number_format($ales_soz_puan, 4, ',', '.') . '</span></div>';
            echo '</div>';

            echo '<div class="si-info-text"><strong>Önemli Bilgilendirme:</strong> Bu hesaplama aracının resmi bir geçerliliği yoktur. Sonuçlar tahmini olup, ÖSYM\'nin her sınav döneminde açıkladığı standart sapmalara ve Z-Skoru algoritmasına göre virgülden sonraki hanelerde farklılık gösterebilir. ALES\'te 4 yanlış 1 doğruyu götürmektedir.</div>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('ales_hesapla', 'soruindir_ales_hesapla_shortcode');
// ========================================================================
// 7. DGS PUAN HESAPLAMA MODÜLÜ
// ========================================================================
function soruindir_dgs_hesapla_shortcode() {
    ob_start(); 
    $guncel_yil = date("Y");
    ?>
    <style>
        .si-dgs-wrapper { background: #f0fdfa; border: 2px solid #ccfbf1; border-radius: 20px; padding: 40px 30px; max-width: 900px; margin: 0 auto 30px auto; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.04); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .si-dgs-header { text-align: center; margin-bottom: 25px; }
        .si-dgs-title { color: #0f172a; font-size: 28px; font-weight: 900; margin: 0 0 10px 0; letter-spacing: -0.5px; }
        .si-dgs-title span { color: #0d9488; }
        .si-dgs-desc { color: #64748b; font-size: 15px; margin: 0; }
        
        .si-dgs-top-bar { background: #fff; padding: 20px; border-radius: 16px; display: flex; justify-content: center; align-items: center; gap: 15px; margin-bottom: 30px; border: 1px solid #ccfbf1; box-shadow: 0 4px 15px rgba(0,0,0,0.02); flex-wrap: wrap;}
        .si-dgs-top-bar label { font-weight: 800; color: #115e59; font-size: 15px; }
        .si-dgs-top-bar input { padding: 12px 15px; font-size: 16px; font-weight: 800; border-radius: 10px; border: 2px solid #0d9488; color: #0f766e; outline: none; background: #f0fdfa; transition: 0.3s; width: 150px; text-align: center; }
        
        .si-dgs-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 35px; }
        .si-dgs-card { background: #fff; border-radius: 16px; padding: 25px; border: 1px solid #ccfbf1; display: flex; flex-direction: column; gap: 20px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(13, 148, 136, 0.05); }
        .si-dgs-card:hover { transform: translateY(-5px); border-color: #0d9488; box-shadow: 0 10px 25px rgba(13, 148, 136, 0.15); }
        
        .si-dgs-card-header { display: flex; align-items: center; gap: 15px; border-bottom: 1px dashed #ccfbf1; padding-bottom: 15px; }
        .si-dgs-icon-box { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: #ccfbf1; color: #0d9488; }
        .si-dgs-icon-box.soz { background: #ffedd5; color: #ea580c; }
        .si-dgs-icon-box svg { width: 28px; height: 28px; }
        .si-dgs-subj-title { font-size: 18px; font-weight: 800; color: #1e293b; margin: 0; display: block; }
        .si-dgs-subj-meta { font-size: 13px; font-weight: 600; color: #94a3b8; display: block; margin-top: 4px; }
        
        .si-dgs-inputs { display: flex; justify-content: space-between; gap: 12px; }
        .si-dgs-input-group { flex: 1; text-align: center; }
        .si-dgs-input-group label { display: block; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 8px; }
        .si-dgs-input-group input { width: 100%; padding: 14px 5px; text-align: center; font-size: 18px; font-weight: 800; border-radius: 10px; border: 2px solid #e2e8f0; color: #0f172a; transition: 0.3s; box-sizing: border-box; }
        
        .si-dgs-input-group.do input:focus { border-color: #10b981; background: #ecfdf5; outline: none; }
        .si-dgs-input-group.ya input:focus { border-color: #ef4444; background: #fef2f2; outline: none; }
        .si-dgs-input-group.ne input { background: #f8fafc; border-color: transparent; color: #0d9488; }

        .si-btn-dgs { width: 100%; padding: 22px; background: linear-gradient(135deg, #0d9488, #0f766e); color: #fff; border: none; border-radius: 14px; font-size: 20px; font-weight: 900; letter-spacing: 0.5px; cursor: pointer; transition: 0.3s; box-shadow: 0 8px 25px rgba(13, 148, 136, 0.3); display: flex; align-items: center; justify-content: center; gap: 10px; }
        .si-btn-dgs:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(13, 148, 136, 0.4); }
        
        .si-dgs-results { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 30px; animation: scaleUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .si-dgs-result-box { background: #fff; border: 2px solid #e2e8f0; border-radius: 16px; padding: 25px 15px; text-align: center; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05); }
        .si-dgs-result-box.say { border-color: #0d9488; background: #f0fdfa; }
        .si-dgs-result-box.ea { border-color: #0ea5e9; background: #f0f9ff; }
        .si-dgs-result-box.soz { border-color: #ea580c; background: #fff7ed; }
        .si-dgs-result-label { font-size: 15px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 8px; }
        .si-dgs-result-score { font-size: 38px; font-weight: 900; color: #0f172a; line-height: 1; }

        .si-info-text { text-align:center; font-size:13px; color:#64748b; margin-top:20px; background: #fff; padding: 15px; border-radius: 8px; border:1px solid #ccfbf1;}
        
        @media (max-width: 768px) { 
            .si-dgs-wrapper { padding: 25px 15px; } 
            .si-dgs-grid, .si-dgs-results { grid-template-columns: 1fr; gap: 15px; } 
            .si-dgs-top-bar { flex-direction: column; text-align: center; }
        }
        @keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>

    <div class="si-dgs-wrapper">
        <div class="si-dgs-header">
            <h3 class="si-dgs-title"><span><?php echo $guncel_yil; ?></span> DGS Puan Hesaplama</h3>
            <p class="si-dgs-desc">Dikey Geçiş Sınavı Robotu (4 Yanlış 1 Doğruyu Götürür)</p>
        </div>
        
        <form method="post" action="#dgs-sonuc">
            
            <div class="si-dgs-top-bar">
                <label for="dgs_diploma">Önlisans Mezuniyet Notu (ÖBP):</label>
                <input type="number" step="0.01" name="dgs_diploma" id="dgs_diploma" placeholder="Örn: 85.50" min="50" max="100" required>
            </div>

            <div class="si-dgs-grid">
                <div class="si-dgs-card" data-max="50">
                    <div class="si-dgs-card-header">
                        <div class="si-dgs-icon-box">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><line x1="8" y1="10" x2="16" y2="10"></line><line x1="8" y1="14" x2="16" y2="14"></line><line x1="12" y1="6" x2="12" y2="18"></line></svg>
                        </div>
                        <div>
                            <h4 class="si-dgs-subj-title">Sayısal Testi</h4>
                            <span class="si-dgs-subj-meta">50 Soru (Matematik, Geometri)</span>
                        </div>
                    </div>
                    <div class="si-dgs-inputs">
                        <div class="si-dgs-input-group do"><label>Doğru</label><input type="number" name="dgs_say_d" class="si-dgs-d" min="0" max="50" placeholder="0"></div>
                        <div class="si-dgs-input-group ya"><label>Yanlış</label><input type="number" name="dgs_say_y" class="si-dgs-y" min="0" max="50" placeholder="0"></div>
                        <div class="si-dgs-input-group ne"><label>Net</label><input type="number" step="0.25" name="dgs_say_n" class="si-dgs-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>

                <div class="si-dgs-card" data-max="50">
                    <div class="si-dgs-card-header">
                        <div class="si-dgs-icon-box soz">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                        </div>
                        <div>
                            <h4 class="si-dgs-subj-title">Sözel Testi</h4>
                            <span class="si-dgs-subj-meta">50 Soru (Türkçe, Sözel Mantık)</span>
                        </div>
                    </div>
                    <div class="si-dgs-inputs">
                        <div class="si-dgs-input-group do"><label>Doğru</label><input type="number" name="dgs_soz_d" class="si-dgs-d" min="0" max="50" placeholder="0"></div>
                        <div class="si-dgs-input-group ya"><label>Yanlış</label><input type="number" name="dgs_soz_y" class="si-dgs-y" min="0" max="50" placeholder="0"></div>
                        <div class="si-dgs-input-group ne"><label>Net</label><input type="number" step="0.25" name="dgs_soz_n" class="si-dgs-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>
            </div>

            <button type="submit" name="hesapla_dgs" class="si-btn-dgs">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                DGS PUANLARIMI HESAPLA
            </button>
        </form>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const dgsCards = document.querySelectorAll('.si-dgs-card');
                dgsCards.forEach(card => {
                    const dInput = card.querySelector('.si-dgs-d'); const yInput = card.querySelector('.si-dgs-y'); const nInput = card.querySelector('.si-dgs-n');
                    const maxQ = parseInt(card.getAttribute('data-max'));
                    function calcDGSNet() {
                        let d = parseInt(dInput.value) || 0; let y = parseInt(yInput.value) || 0;
                        if (d + y > maxQ) { yInput.value = maxQ - d; y = maxQ - d; }
                        let net = d - (y / 4); nInput.value = net > 0 ? net.toFixed(2) : 0;
                    }
                    dInput.addEventListener('input', calcDGSNet); yInput.addEventListener('input', calcDGSNet);
                });
            });
        </script>

        <?php
        if(isset($_POST['hesapla_dgs'])) {
            $n = function($isim) { return si_post_net($isim, 4); };
            $say_net = $n('dgs_say');
            $soz_net = $n('dgs_soz');
            
            // Diploma notu üzerinden ÖBP (Önlisans Başarı Puanı) hesaplama (100 üzerinden girilir. ÖBP = Diploma * 0.8)
            $diploma = floatval($_POST['dgs_diploma']);
            if($diploma > 100) $diploma = 100;
            if($diploma < 50) $diploma = 50;
            
            $obp = $diploma * 0.8;
            $obp_etkisi = $obp * 0.6; // ÖBP'nin DGS puanına etkisi doğrudan 0.6 ile çarpımıdır.

                        // ADMİN PANELİNDEN DGS DOĞRUSAL ÇARPANLARI ÇEKİYORUZ
            $s_taban = floatval(get_option('si_dgs_say_taban', '140.77635'));
            $s_k_say = floatval(get_option('si_dgs_say_k_say', '3.48620'));
            $s_k_soz = floatval(get_option('si_dgs_say_k_soz', '0.61240'));

            $ea_taban = floatval(get_option('si_dgs_ea_taban', '122.04809'));
            $ea_k_say = floatval(get_option('si_dgs_ea_k_say', '2.04930'));
            $ea_k_soz = floatval(get_option('si_dgs_ea_k_soz', '2.04930'));

            $z_taban = floatval(get_option('si_dgs_soz_taban', '103.31980'));
            $z_k_say = floatval(get_option('si_dgs_soz_k_say', '0.61240'));
            $z_k_soz = floatval(get_option('si_dgs_soz_k_soz', '3.48620'));

            // DGS Puan Hesaplamaları
            $dgs_say_puan = $s_taban + ($say_net * $s_k_say) + ($soz_net * $s_k_soz) + $obp_etkisi;
            $dgs_ea_puan  = $ea_taban + ($say_net * $ea_k_say) + ($soz_net * $ea_k_soz) + $obp_etkisi;
            $dgs_soz_puan = $z_taban + ($say_net * $z_k_say) + ($soz_net * $z_k_soz) + $obp_etkisi;
            
            // DGS Maksimum Puanı teorik olarak 350-360 bandındadır. ÖSYM'nin belirlediği bir üst tavan kısıtlaması yerine saf matematiği sunuyoruz.
            if ($say_net <= 0 && $soz_net <= 0) {
                $dgs_say_puan = 0; $dgs_ea_puan = 0; $dgs_soz_puan = 0;
            }

            echo '<div id="dgs-sonuc" class="si-dgs-results">';
            echo '<div class="si-dgs-result-box say"><span class="si-dgs-result-label">DGS SAYISAL YERLEŞTİRME</span><span class="si-dgs-result-score">' . number_format($dgs_say_puan, 4, ',', '.') . '</span></div>';
            echo '<div class="si-dgs-result-box ea"><span class="si-dgs-result-label">DGS EŞİT AĞIRLIK YERLEŞTİRME</span><span class="si-dgs-result-score">' . number_format($dgs_ea_puan, 4, ',', '.') . '</span></div>';
            echo '<div class="si-dgs-result-box soz"><span class="si-dgs-result-label">DGS SÖZEL YERLEŞTİRME</span><span class="si-dgs-result-score">' . number_format($dgs_soz_puan, 4, ',', '.') . '</span></div>';
            echo '</div>';

            echo '<div class="si-info-text"><strong>Önemli Bilgilendirme:</strong> Bu hesaplama aracının resmi bir geçerliliği yoktur. Sonuçlar tahmini olup, ÖSYM\'nin uyguladığı ağırlıklı standart sapmalara ve Z-Skoru algoritmasına göre virgülden sonraki hanelerde farklılık gösterebilir. Gösterilen puanlar diploma notunuzun eklendiği <strong>Yerleştirme</strong> puanlarıdır. DGS\'de 4 yanlış 1 doğruyu götürmektedir.</div>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('dgs_hesapla', 'soruindir_dgs_hesapla_shortcode');
// ========================================================================
// 8. MSÜ PUAN HESAPLAMA MODÜLÜ
// ========================================================================
function soruindir_msu_hesapla_shortcode() {
    ob_start(); 
    $guncel_yil = date("Y");
    ?>
    <style>
        .si-msu-wrapper { background: #f7fee7; border: 2px solid #ecfccb; border-radius: 20px; padding: 40px 30px; max-width: 900px; margin: 0 auto 30px auto; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.04); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .si-msu-header { text-align: center; margin-bottom: 30px; }
        .si-msu-title { color: #1a2e05; font-size: 28px; font-weight: 900; margin: 0 0 10px 0; letter-spacing: -0.5px; }
        .si-msu-title span { color: #4d7c0f; }
        .si-msu-desc { color: #3f6212; font-size: 15px; margin: 0; font-weight: 500; }
        
        .si-msu-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 35px; }
        .si-msu-card { background: #fff; border-radius: 16px; padding: 20px; border: 1px solid #d9f99d; display: flex; flex-direction: column; gap: 15px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(77, 124, 15, 0.05); }
        .si-msu-card:hover { transform: translateY(-5px); border-color: #4d7c0f; box-shadow: 0 10px 25px rgba(77, 124, 15, 0.15); }
        
        .si-msu-card-header { text-align: center; border-bottom: 1px dashed #ecfccb; padding-bottom: 12px; }
        .si-msu-subj-title { font-size: 17px; font-weight: 800; color: #1a2e05; margin: 0; display: block; }
        .si-msu-subj-meta { font-size: 12px; font-weight: 600; color: #65a30d; display: block; margin-top: 4px; }
        
        .si-msu-inputs { display: flex; justify-content: space-between; gap: 8px; }
        .si-msu-input-group { flex: 1; text-align: center; }
        .si-msu-input-group label { display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; color: #4d7c0f; margin-bottom: 6px; }
        .si-msu-input-group input { width: 100%; padding: 10px 4px; text-align: center; font-size: 16px; font-weight: 800; border-radius: 8px; border: 2px solid #e2e8f0; color: #1a2e05; transition: 0.3s; box-sizing: border-box; }
        
        .si-msu-input-group.do input:focus { border-color: #4d7c0f; background: #f7fee7; outline: none; }
        .si-msu-input-group.ya input:focus { border-color: #dc2626; background: #fef2f2; outline: none; }
        .si-msu-input-group.ne input { background: #f8fafc; border-color: transparent; color: #4d7c0f; }

        .si-btn-msu { width: 100%; padding: 22px; background: linear-gradient(135deg, #4d7c0f, #3f6212); color: #fff; border: none; border-radius: 14px; font-size: 20px; font-weight: 900; letter-spacing: 0.5px; cursor: pointer; transition: 0.3s; box-shadow: 0 8px 25px rgba(77, 124, 15, 0.3); display: flex; align-items: center; justify-content: center; gap: 10px; }
        .si-btn-msu:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(77, 124, 15, 0.4); }
        
        .si-msu-results { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-top: 30px; animation: scaleUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .si-msu-result-box { background: #fff; border: 2px solid #e2e8f0; border-radius: 16px; padding: 20px 10px; text-align: center; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05); }
        .si-msu-result-box.say { border-color: #0ea5e9; background: #f0f9ff; }
        .si-msu-result-box.ea { border-color: #8b5cf6; background: #f5f3ff; }
        .si-msu-result-box.soz { border-color: #f59e0b; background: #fffbeb; }
        .si-msu-result-box.gen { border-color: #4d7c0f; background: #f7fee7; }
        
        .si-msu-result-label { font-size: 13px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px; }
        .si-msu-result-score { font-size: 32px; font-weight: 900; color: #1a2e05; line-height: 1; }

        .si-info-text-msu { text-align:center; font-size:13px; color:#3f6212; margin-top:20px; background: #ecfccb; padding: 15px; border-radius: 8px; border:1px solid #d9f99d;}
        
        @media (max-width: 768px) { 
            .si-msu-wrapper { padding: 25px 15px; } 
            .si-msu-grid { grid-template-columns: 1fr; }
        }
        @keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>

    <div class="si-msu-wrapper">
        <div class="si-msu-header">
            <h3 class="si-msu-title"><span><?php echo $guncel_yil; ?></span> MSÜ Puan Hesaplama</h3>
            <p class="si-msu-desc">Milli Savunma Üniversitesi Askeri Öğrenci Aday Belirleme Sınavı</p>
        </div>
        
        <form method="post" action="#msu-sonuc">
            
            <?php 
            $si_msu_kart_uret = function($ders_adi, $soru_sayisi, $name_prefix) {
                echo "<div class='si-msu-card' data-max='$soru_sayisi'>";
                echo "<div class='si-msu-card-header'><h4 class='si-msu-subj-title'>$ders_adi</h4><span class='si-msu-subj-meta'>$soru_sayisi Soru</span></div>";
                echo "<div class='si-msu-inputs'>";
                echo "<div class='si-msu-input-group do'><label>Doğru</label><input type='number' name='{$name_prefix}_d' class='si-msu-d' min='0' max='$soru_sayisi' placeholder='0'></div>";
                echo "<div class='si-msu-input-group ya'><label>Yanlış</label><input type='number' name='{$name_prefix}_y' class='si-msu-y' min='0' max='$soru_sayisi' placeholder='0'></div>";
                echo "<div class='si-msu-input-group ne'><label>Net</label><input type='number' step='0.25' name='{$name_prefix}_n' class='si-msu-n' value='0' readonly tabindex='-1'></div>";
                echo "</div></div>";
            };
            ?>

            <div class="si-msu-grid">
                <?php 
                $si_msu_kart_uret("Türkçe", 40, "msu_tr");
                $si_msu_kart_uret("Sosyal Bilimler", 20, "msu_sos");
                $si_msu_kart_uret("Temel Matematik", 40, "msu_mat");
                $si_msu_kart_uret("Fen Bilimleri", 20, "msu_fen");
                ?>
            </div>

            <button type="submit" name="hesapla_msu" class="si-btn-msu">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                MSÜ PUANLARIMI HESAPLA
            </button>
        </form>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const msuCards = document.querySelectorAll('.si-msu-card');
                msuCards.forEach(card => {
                    const dInput = card.querySelector('.si-msu-d'); const yInput = card.querySelector('.si-msu-y'); const nInput = card.querySelector('.si-msu-n');
                    const maxQ = parseInt(card.getAttribute('data-max'));
                    function calcMSUNet() {
                        let d = parseInt(dInput.value) || 0; let y = parseInt(yInput.value) || 0;
                        if (d + y > maxQ) { yInput.value = maxQ - d; y = maxQ - d; }
                        let net = d - (y / 4); nInput.value = net > 0 ? net.toFixed(2) : 0;
                    }
                    dInput.addEventListener('input', calcMSUNet); yInput.addEventListener('input', calcMSUNet);
                });
            });
        </script>

        <?php
        if(isset($_POST['hesapla_msu'])) {
            $n = function($isim) { return si_post_net($isim, 4); };
            $tr = $n('msu_tr'); $sos = $n('msu_sos'); $mat = $n('msu_mat'); $fen = $n('msu_fen');

            // ADMİN PANELİNDEN MSÜ KATSAYILARINI ÇEKİYORUZ
            $taban = floatval(get_option('si_msu_taban', '100.000'));
            
            $say_puan = $taban + ($tr * floatval(get_option('si_msu_say_tr', '2.500'))) + ($sos * floatval(get_option('si_msu_say_sos', '2.000'))) + ($mat * floatval(get_option('si_msu_say_mat', '5.000'))) + ($fen * floatval(get_option('si_msu_say_fen', '3.000')));
            
            $ea_puan = $taban + ($tr * floatval(get_option('si_msu_ea_tr', '3.500'))) + ($sos * floatval(get_option('si_msu_ea_sos', '4.000'))) + ($mat * floatval(get_option('si_msu_ea_mat', '3.500'))) + ($fen * floatval(get_option('si_msu_ea_fen', '2.000')));
            
            $soz_puan = $taban + ($tr * floatval(get_option('si_msu_soz_tr', '3.500'))) + ($sos * floatval(get_option('si_msu_soz_sos', '7.000'))) + ($mat * floatval(get_option('si_msu_soz_mat', '2.000'))) + ($fen * floatval(get_option('si_msu_soz_fen', '2.000')));
            
            $gen_puan = $taban + ($tr * floatval(get_option('si_msu_gen_tr', '3.300'))) + ($sos * floatval(get_option('si_msu_gen_sos', '3.400'))) + ($mat * floatval(get_option('si_msu_gen_mat', '3.300'))) + ($fen * floatval(get_option('si_msu_gen_fen', '3.400')));

            // Sınırlandırmalar
            $puanlar = [&$say_puan, &$ea_puan, &$soz_puan, &$gen_puan];
            foreach($puanlar as &$p) {
                if ($p > 500) $p = 500;
                if ($tr <= 0 && $sos <= 0 && $mat <= 0 && $fen <= 0) $p = 0;
            }

            echo '<div id="msu-sonuc" class="si-msu-results">';
            echo '<div class="si-msu-result-box say"><span class="si-msu-result-label">MSÜ - SAYISAL</span><span class="si-msu-result-score">' . number_format($say_puan, 5, ',', '.') . '</span></div>';
            echo '<div class="si-msu-result-box ea"><span class="si-msu-result-label">MSÜ - EŞİT AĞIRLIK</span><span class="si-msu-result-score">' . number_format($ea_puan, 5, ',', '.') . '</span></div>';
            echo '<div class="si-msu-result-box soz"><span class="si-msu-result-label">MSÜ - SÖZEL</span><span class="si-msu-result-score">' . number_format($soz_puan, 5, ',', '.') . '</span></div>';
            echo '<div class="si-msu-result-box gen"><span class="si-msu-result-label">MSÜ - GENEL</span><span class="si-msu-result-score">' . number_format($gen_puan, 5, ',', '.') . '</span></div>';
            echo '</div>';

            echo '<div class="si-info-text-msu"><strong>Önemli Bilgilendirme:</strong> Bu aracın resmi geçerliliği yoktur. Sonuçlar ÖSYM\'nin yüzdelik dilimlerine göre (Standart sapma hariç) normalize edilmiş puanlardır. MSÜ sınavında 4 yanlış 1 doğruyu götürmektedir.</div>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('msu_hesapla', 'soruindir_msu_hesapla_shortcode');
// ========================================================================
// 9. EHLİYET PUAN HESAPLAMA MODÜLÜ
// ========================================================================
function soruindir_ehliyet_hesapla_shortcode() {
    ob_start(); 
    $guncel_yil = date("Y");
    ?>
    <style>
        .si-ehl-wrapper { background: #fdfbf7; border: 2px solid #fde68a; border-radius: 20px; padding: 40px 30px; max-width: 900px; margin: 0 auto 30px auto; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.04); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .si-ehl-header { text-align: center; margin-bottom: 30px; }
        .si-ehl-title { color: #1e293b; font-size: 28px; font-weight: 900; margin: 0 0 10px 0; letter-spacing: -0.5px; }
        .si-ehl-title span { color: #d97706; }
        .si-ehl-desc { color: #475569; font-size: 15px; margin: 0; font-weight: 500; }
        
        .si-ehl-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 35px; }
        .si-ehl-card { background: #fff; border-radius: 16px; padding: 20px; border: 1px solid #fef3c7; display: flex; flex-direction: column; gap: 15px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(217, 119, 6, 0.05); }
        .si-ehl-card:hover { transform: translateY(-5px); border-color: #f59e0b; box-shadow: 0 10px 25px rgba(217, 119, 6, 0.15); }
        
        .si-ehl-card-header { text-align: center; border-bottom: 1px dashed #fde68a; padding-bottom: 12px; }
        .si-ehl-icon-box { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 12px; background: #fffbeb; color: #d97706; margin-bottom: 10px; }
        .si-ehl-subj-title { font-size: 17px; font-weight: 800; color: #1e293b; margin: 0; display: block; }
        .si-ehl-subj-meta { font-size: 12px; font-weight: 600; color: #b45309; display: block; margin-top: 4px; }
        
        .si-ehl-inputs { display: flex; justify-content: center; }
        .si-ehl-input-group { width: 100px; text-align: center; }
        .si-ehl-input-group label { display: block; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #d97706; margin-bottom: 6px; }
        .si-ehl-input-group input { width: 100%; padding: 12px 5px; text-align: center; font-size: 20px; font-weight: 900; border-radius: 10px; border: 2px solid #e2e8f0; color: #0f172a; transition: 0.3s; box-sizing: border-box; }
        .si-ehl-input-group input:focus { border-color: #f59e0b; background: #fffbeb; outline: none; }

        .si-btn-ehl { width: 100%; padding: 22px; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; border: none; border-radius: 14px; font-size: 20px; font-weight: 900; letter-spacing: 0.5px; cursor: pointer; transition: 0.3s; box-shadow: 0 8px 25px rgba(217, 119, 6, 0.3); display: flex; align-items: center; justify-content: center; gap: 10px; }
        .si-btn-ehl:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(217, 119, 6, 0.4); }
        
        .si-ehl-result-box { margin-top: 30px; background: #fff; border: 2px solid #e2e8f0; border-radius: 16px; padding: 30px 15px; text-align: center; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05); animation: scaleUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .si-ehl-result-box.basarili { border-color: #10b981; background: #ecfdf5; }
        .si-ehl-result-box.basarisiz { border-color: #ef4444; background: #fef2f2; }
        
        .si-ehl-result-score { font-size: 48px; font-weight: 900; line-height: 1; display: block; margin-bottom: 10px; }
        .si-ehl-result-status { font-size: 20px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        .basarili .si-ehl-result-score, .basarili .si-ehl-result-status { color: #059669; }
        .basarisiz .si-ehl-result-score, .basarisiz .si-ehl-result-status { color: #b91c1c; }

        .si-info-text-ehl { text-align:center; font-size:13px; color:#475569; margin-top:20px; background: #fff; padding: 15px; border-radius: 8px; border:1px solid #fde68a;}
        
        @media (max-width: 768px) { .si-ehl-wrapper { padding: 25px 15px; } .si-ehl-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 480px) { .si-ehl-grid { grid-template-columns: 1fr; } }
        @keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>

    <div class="si-ehl-wrapper">
        <div class="si-ehl-header">
            <h3 class="si-ehl-title"><span><?php echo $guncel_yil; ?></span> Ehliyet Puan Hesaplama</h3>
            <p class="si-ehl-desc">Ehliyet sınavında yanlışlar doğruyu götürmez. Her soru 2 puandır.</p>
        </div>
        
        <form method="post" action="#ehl-sonuc">
            
            <?php 
            $si_ehl_kart_uret = function($ders_adi, $soru_sayisi, $name_prefix, $svg_path) {
                echo "<div class='si-ehl-card' data-max='$soru_sayisi'>";
                echo "<div class='si-ehl-card-header'><div class='si-ehl-icon-box'><svg viewBox='0 0 24 24' width='24' height='24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'>$svg_path</svg></div><h4 class='si-ehl-subj-title'>$ders_adi</h4><span class='si-ehl-subj-meta'>Maksimum: $soru_sayisi Soru</span></div>";
                echo "<div class='si-ehl-inputs'>";
                echo "<div class='si-ehl-input-group'><label>Doğru Sayısı</label><input type='number' name='{$name_prefix}_d' class='si-ehl-d' min='0' max='$soru_sayisi' placeholder='0'></div>";
                echo "</div></div>";
            };
            ?>

            <div class="si-ehl-grid">
                <?php 
                $si_ehl_kart_uret("İlkyardım", 12, "ehl_ilk", "<path d='M22 12h-4l-3 9L9 3l-3 9H2'></path>");
                $si_ehl_kart_uret("Trafik ve Çevre", 23, "ehl_tra", "<circle cx='12' cy='12' r='10'></circle><path d='M12 8v4l3 3'></path>");
                $si_ehl_kart_uret("Araç Tekniği", 9, "ehl_mot", "<circle cx='12' cy='12' r='3'></circle><path d='M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z'></path>");
                $si_ehl_kart_uret("Trafik Adabı", 6, "ehl_ada", "<path d='M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'></path><circle cx='9' cy='7' r='4'></circle><path d='M23 21v-2a4 4 0 0 0-3-3.87'></path><path d='M16 3.13a4 4 0 0 1 0 7.75'></path>");
                ?>
            </div>

            <button type="submit" name="hesapla_ehl" class="si-btn-ehl">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                EHLİYET PUANIMI HESAPLA
            </button>
        </form>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const ehlCards = document.querySelectorAll('.si-ehl-card');
                ehlCards.forEach(card => {
                    const dInput = card.querySelector('.si-ehl-d');
                    const maxQ = parseInt(card.getAttribute('data-max'));
                    dInput.addEventListener('input', function() {
                        let d = parseInt(dInput.value) || 0;
                        if (d > maxQ) { dInput.value = maxQ; }
                    });
                });
            });
        </script>

        <?php
        if(isset($_POST['hesapla_ehl'])) {
            $n = function($isim) { return isset($_POST[$isim.'_d']) ? intval($_POST[$isim.'_d']) : 0; };
            $toplam_dogru = $n('ehl_ilk') + $n('ehl_tra') + $n('ehl_mot') + $n('ehl_ada');

            // ADMİN PANELİNDEN EHLİYET AYARLARINI ÇEKİYORUZ
            $soru_puan = floatval(get_option('si_ehliyet_soru_puan', '2'));
            $gecme_notu = floatval(get_option('si_ehliyet_gecme_notu', '70'));
            
            $ehliyet_puan = $toplam_dogru * $soru_puan;
            
            if ($ehliyet_puan >= $gecme_notu) {
                $durum_class = "basarili";
                $durum_mesaj = "BAŞARILI - TEBRİKLER!";
            } else {
                $durum_class = "basarisiz";
                $durum_mesaj = "BAŞARISIZ - KALDINIZ";
            }

            echo '<div id="ehl-sonuc" class="si-ehl-result-box ' . $durum_class . '">';
            echo '<span class="si-ehl-result-score">' . $ehliyet_puan . ' Puan</span>';
            echo '<span class="si-ehl-result-status">' . $durum_mesaj . '</span>';
            echo '</div>';

            echo '<div class="si-info-text-ehl"><strong>Bilgilendirme:</strong> Sınavda başarılı sayılabilmek için 50 sorudan en az 35 tanesini doğru cevaplayarak 70 puan almanız gerekmektedir.</div>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('ehliyet_hesapla', 'soruindir_ehliyet_hesapla_shortcode');

// ========================================================================
// 10. YDS PUAN HESAPLAMA MODÜLÜ
// ========================================================================
function soruindir_yds_hesapla_shortcode() {
    ob_start(); 
    $guncel_yil = date("Y");
    ?>
    <style>
        .si-yds-wrapper { background: #f0fdfa; border: 2px solid #a5f3fc; border-radius: 20px; padding: 40px 30px; max-width: 600px; margin: 0 auto 30px auto; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.04); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .si-yds-header { text-align: center; margin-bottom: 30px; }
        .si-yds-title { color: #164e63; font-size: 28px; font-weight: 900; margin: 0 0 10px 0; letter-spacing: -0.5px; }
        .si-yds-title span { color: #0891b2; }
        .si-yds-desc { color: #475569; font-size: 15px; margin: 0; font-weight: 500; }
        
        .si-yds-card { background: #fff; border-radius: 16px; padding: 25px; border: 1px solid #cffafe; display: flex; flex-direction: column; gap: 20px; box-shadow: 0 4px 15px rgba(8, 145, 178, 0.05); margin-bottom: 25px;}
        .si-yds-card-header { text-align: center; border-bottom: 1px dashed #a5f3fc; padding-bottom: 15px; }
        .si-yds-icon-box { display: inline-flex; align-items: center; justify-content: center; width: 54px; height: 54px; border-radius: 14px; background: #ecfeff; color: #0891b2; margin-bottom: 10px; }
        .si-yds-subj-title { font-size: 18px; font-weight: 800; color: #164e63; margin: 0; display: block; }
        .si-yds-subj-meta { font-size: 13px; font-weight: 600; color: #0e7490; display: block; margin-top: 4px; }
        
        .si-yds-inputs { display: flex; justify-content: center; }
        .si-yds-input-group { width: 140px; text-align: center; }
        .si-yds-input-group label { display: block; font-size: 13px; font-weight: 800; text-transform: uppercase; color: #0891b2; margin-bottom: 8px; }
        .si-yds-input-group input { width: 100%; padding: 15px 5px; text-align: center; font-size: 24px; font-weight: 900; border-radius: 12px; border: 2px solid #e2e8f0; color: #0f172a; transition: 0.3s; box-sizing: border-box; }
        .si-yds-input-group input:focus { border-color: #06b6d4; background: #ecfeff; outline: none; }

        .si-btn-yds { width: 100%; padding: 22px; background: linear-gradient(135deg, #0891b2, #0e7490); color: #fff; border: none; border-radius: 14px; font-size: 20px; font-weight: 900; letter-spacing: 0.5px; cursor: pointer; transition: 0.3s; box-shadow: 0 8px 25px rgba(8, 145, 178, 0.3); display: flex; align-items: center; justify-content: center; gap: 10px; }
        .si-btn-yds:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(8, 145, 178, 0.4); }
        
        .si-yds-result-box { margin-top: 30px; background: #fff; border: 2px solid #06b6d4; border-radius: 16px; padding: 30px 15px; text-align: center; box-shadow: 0 10px 25px rgba(6, 182, 212, 0.1); animation: scaleUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .si-yds-result-score { font-size: 54px; font-weight: 900; color: #164e63; line-height: 1; display: block; margin-bottom: 10px; }
        .si-yds-result-level { font-size: 18px; font-weight: 800; color: #0891b2; text-transform: uppercase; letter-spacing: 1px; padding: 8px 16px; background: #ecfeff; border-radius: 8px; display: inline-block;}

        .si-info-text-yds { text-align:center; font-size:13px; color:#475569; margin-top:20px; background: #fff; padding: 15px; border-radius: 8px; border:1px solid #cffafe;}
        
        @media (max-width: 768px) { .si-yds-wrapper { padding: 25px 15px; } }
        @keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>

    <div class="si-yds-wrapper">
        <div class="si-yds-header">
            <h3 class="si-yds-title"><span><?php echo $guncel_yil; ?></span> YDS Puan Hesaplama</h3>
            <p class="si-yds-desc">YDS / e-YDS / YÖKDİL (Yanlışlar Doğruları Götürmez)</p>
        </div>
        
        <form method="post" action="#yds-sonuc">
            
            <div class='si-yds-card'>
                <div class='si-yds-card-header'>
                    <div class='si-yds-icon-box'>
                        <svg viewBox='0 0 24 24' width='28' height='28' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='10'></circle><line x1='2' y1='12' x2='22' y2='12'></line><path d='M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z'></path></svg>
                    </div>
                    <h4 class='si-yds-subj-title'>Yabancı Dil Sınavı</h4>
                    <span class='si-yds-subj-meta'>Maksimum: 80 Soru</span>
                </div>
                <div class='si-yds-inputs'>
                    <div class='si-yds-input-group'>
                        <label>Doğru Sayısı</label>
                        <input type='number' name='yds_dogru' id='yds_dogru' min='0' max='80' placeholder='0' required>
                    </div>
                </div>
            </div>

            <button type="submit" name="hesapla_yds" class="si-btn-yds">
                YDS PUANIMI HESAPLA
            </button>
        </form>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const ydsInput = document.getElementById('yds_dogru');
                ydsInput.addEventListener('input', function() {
                    let d = parseInt(ydsInput.value) || 0;
                    if (d > 80) { ydsInput.value = 80; }
                    if (d < 0) { ydsInput.value = 0; }
                });
            });
        </script>

        <?php
        if(isset($_POST['hesapla_yds'])) {
            $dogru = isset($_POST['yds_dogru']) ? intval($_POST['yds_dogru']) : 0;

            // ADMİN PANELİNDEN YDS AYARLARINI ÇEKİYORUZ
            $katsayi = floatval(get_option('si_yds_soru_katsayi', '1.25'));
            
            $yds_puan = $dogru * $katsayi;
            
            // Harf Notu (Seviye) Belirleme
            $seviye = "";
            if ($yds_puan >= 90) { $seviye = "A Seviyesi (Çok İyi)"; }
            elseif ($yds_puan >= 80) { $seviye = "B Seviyesi (İyi)"; }
            elseif ($yds_puan >= 70) { $seviye = "C Seviyesi (Orta)"; }
            elseif ($yds_puan >= 60) { $seviye = "D Seviyesi (Zayıf)"; }
            elseif ($yds_puan >= 50) { $seviye = "E Seviyesi (Geçer)"; }
            else { $seviye = "Seviye Belirlenemedi"; }

            echo '<div id="yds-sonuc" class="si-yds-result-box">';
            echo '<span class="si-yds-result-score">' . number_format($yds_puan, 2, ',', '.') . '</span>';
            echo '<span class="si-yds-result-level">' . $seviye . '</span>';
            echo '</div>';

            echo '<div class="si-info-text-yds"><strong>Bilgilendirme:</strong> YDS ve YÖKDİL sınavlarında 80 soru bulunmaktadır ve her bir soru ' . $katsayi . ' puandır. Yanlış cevapların doğru cevaplara herhangi bir etkisi yoktur. Bu araç sadece doğru sayınıza göre kesin hesaplama yapar.</div>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('yds_hesapla', 'soruindir_yds_hesapla_shortcode');
// ========================================================================
// 12. DİB-MBSTS PUAN HESAPLAMA MODÜLÜ
// ========================================================================
function soruindir_mbsts_hesapla_shortcode() {
    ob_start(); 
    $guncel_yil = date("Y");
    ?>
    <style>
        .si-mbsts-wrapper { background: #f0fdfa; border: 2px solid #99f6e4; border-radius: 20px; padding: 40px 30px; max-width: 600px; margin: 0 auto 30px auto; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.04); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .si-mbsts-header { text-align: center; margin-bottom: 30px; }
        .si-mbsts-title { color: #115e59; font-size: 28px; font-weight: 900; margin: 0 0 10px 0; letter-spacing: -0.5px; }
        .si-mbsts-title span { color: #0d9488; }
        .si-mbsts-desc { color: #475569; font-size: 15px; margin: 0; font-weight: 500; }
        
        .si-mbsts-card { background: #fff; border-radius: 16px; padding: 25px; border: 1px solid #ccfbf1; display: flex; flex-direction: column; gap: 20px; box-shadow: 0 4px 15px rgba(13, 148, 136, 0.05); margin-bottom: 25px;}
        .si-mbsts-card-header { text-align: center; border-bottom: 1px dashed #99f6e4; padding-bottom: 15px; }
        .si-mbsts-icon-box { display: inline-flex; align-items: center; justify-content: center; width: 54px; height: 54px; border-radius: 14px; background: #ccfbf1; color: #0d9488; margin-bottom: 10px; }
        .si-mbsts-subj-title { font-size: 18px; font-weight: 800; color: #115e59; margin: 0; display: block; }
        .si-mbsts-subj-meta { font-size: 13px; font-weight: 600; color: #0f766e; display: block; margin-top: 4px; }
        
        .si-mbsts-inputs { display: flex; justify-content: center; }
        .si-mbsts-input-group { width: 140px; text-align: center; }
        .si-mbsts-input-group label { display: block; font-size: 13px; font-weight: 800; text-transform: uppercase; color: #0d9488; margin-bottom: 8px; }
        .si-mbsts-input-group input { width: 100%; padding: 15px 5px; text-align: center; font-size: 24px; font-weight: 900; border-radius: 12px; border: 2px solid #e2e8f0; color: #0f172a; transition: 0.3s; box-sizing: border-box; }
        .si-mbsts-input-group input:focus { border-color: #14b8a6; background: #f0fdfa; outline: none; }

        .si-btn-mbsts { width: 100%; padding: 22px; background: linear-gradient(135deg, #0d9488, #0f766e); color: #fff; border: none; border-radius: 14px; font-size: 20px; font-weight: 900; letter-spacing: 0.5px; cursor: pointer; transition: 0.3s; box-shadow: 0 8px 25px rgba(13, 148, 136, 0.3); display: flex; align-items: center; justify-content: center; gap: 10px; }
        .si-btn-mbsts:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(13, 148, 136, 0.4); }
        
        .si-mbsts-result-box { margin-top: 30px; background: #fff; border: 2px solid #14b8a6; border-radius: 16px; padding: 30px 15px; text-align: center; box-shadow: 0 10px 25px rgba(20, 184, 166, 0.1); animation: scaleUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .si-mbsts-result-score { font-size: 54px; font-weight: 900; color: #115e59; line-height: 1; display: block; margin-bottom: 10px; }
        .si-mbsts-result-label { font-size: 16px; font-weight: 800; color: #0d9488; text-transform: uppercase; letter-spacing: 1px; padding: 8px 16px; display: inline-block;}

        .si-info-text-mbsts { text-align:center; font-size:13px; color:#475569; margin-top:20px; background: #fff; padding: 15px; border-radius: 8px; border:1px solid #99f6e4;}
        
        @media (max-width: 768px) { .si-mbsts-wrapper { padding: 25px 15px; } }
        @keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>

    <div class="si-mbsts-wrapper">
        <div class="si-mbsts-header">
            <h3 class="si-mbsts-title"><span><?php echo $guncel_yil; ?></span> DİB-MBSTS Hesaplama</h3>
            <p class="si-mbsts-desc">Mesleki Bilgiler Seviye Tespit Sınavı (Yanlışlar Doğruları Götürmez)</p>
        </div>
        
        <form method="post" action="#mbsts-sonuc">
            
            <div class='si-mbsts-card'>
                <div class='si-mbsts-card-header'>
                    <div class='si-mbsts-icon-box'>
                        <svg viewBox='0 0 24 24' width='28' height='28' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M4 19.5A2.5 2.5 0 0 1 6.5 17H20'></path><path d='M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z'></path></svg>
                    </div>
                    <h4 class='si-mbsts-subj-title'>Alan Bilgisi Sınavı</h4>
                    <span class='si-mbsts-subj-meta'>Maksimum: 60 Soru</span>
                </div>
                <div class='si-mbsts-inputs'>
                    <div class='si-mbsts-input-group'>
                        <label>Doğru Sayısı</label>
                        <input type='number' name='mbsts_dogru' id='mbsts_dogru' min='0' max='60' placeholder='0' required>
                    </div>
                </div>
            </div>

            <button type="submit" name="hesapla_mbsts" class="si-btn-mbsts">
                MBSTS PUANIMI HESAPLA
            </button>
        </form>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const mbstsInput = document.getElementById('mbsts_dogru');
                mbstsInput.addEventListener('input', function() {
                    let d = parseInt(mbstsInput.value) || 0;
                    if (d > 60) { mbstsInput.value = 60; }
                    if (d < 0) { mbstsInput.value = 0; }
                });
            });
        </script>

        <?php
        if(isset($_POST['hesapla_mbsts'])) {
            $dogru = isset($_POST['mbsts_dogru']) ? intval($_POST['mbsts_dogru']) : 0;

            // ADMİN PANELİNDEN MBSTS AYARLARINI ÇEKİYORUZ
            $katsayi = floatval(get_option('si_mbsts_soru_katsayi', '1.66667'));
            
            $mbsts_puan = $dogru * $katsayi;
            
            if ($mbsts_puan > 100) { $mbsts_puan = 100; }

            echo '<div id="mbsts-sonuc" class="si-mbsts-result-box">';
            echo '<span class="si-mbsts-result-label">Tahmini Ham Puanınız</span>';
            echo '<span class="si-mbsts-result-score">' . number_format($mbsts_puan, 3, ',', '.') . '</span>';
            echo '</div>';

            echo '<div class="si-info-text-mbsts"><strong>Bilgilendirme:</strong> DİB-MBSTS sınavında toplam 60 soru sorulmaktadır ve yanlış cevaplar doğruları eksiltmez. Belirtilen sonuç, sınava katılan tüm adayların yapacağı standart sapma dikkate alınmadan hesaplanmış en yakın tahmini ham puandır.</div>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('mbsts_hesapla', 'soruindir_mbsts_hesapla_shortcode');

// ========================================================================
// 13. DUS PUAN HESAPLAMA MODÜLÜ
// ========================================================================
function soruindir_dus_hesapla_shortcode() {
    ob_start(); 
    $guncel_yil = date("Y");
    ?>
    <style>
        .si-dus-wrapper { background: #fdf2f8; border: 2px solid #fbcfe8; border-radius: 20px; padding: 40px 30px; max-width: 900px; margin: 0 auto 30px auto; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.04); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .si-dus-header { text-align: center; margin-bottom: 35px; }
        .si-dus-title { color: #831843; font-size: 28px; font-weight: 900; margin: 0 0 10px 0; letter-spacing: -0.5px; }
        .si-dus-title span { color: #db2777; }
        .si-dus-desc { color: #9d174d; font-size: 15px; margin: 0; }
        
        .si-dus-top-bar { background: #fff; padding: 20px; border-radius: 16px; display: flex; justify-content: center; align-items: center; gap: 15px; margin-bottom: 30px; border: 1px solid #fbcfe8; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .si-dus-top-bar label { font-weight: 800; color: #9d174d; font-size: 15px; cursor: pointer; display: flex; align-items: center; gap: 10px; }
        .si-dus-top-bar input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; accent-color: #db2777; }
        
        .si-dus-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 35px; }
        .si-dus-card { background: #fff; border-radius: 16px; padding: 25px; border: 1px solid #fce7f3; display: flex; flex-direction: column; gap: 20px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(219, 39, 119, 0.05); }
        .si-dus-card:hover { transform: translateY(-5px); border-color: #db2777; box-shadow: 0 10px 25px rgba(219, 39, 119, 0.15); }
        
        .si-dus-card-header { display: flex; align-items: center; gap: 15px; border-bottom: 1px dashed #fce7f3; padding-bottom: 15px; }
        .si-dus-icon-box { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: #fdf2f8; color: #db2777; }
        .si-dus-icon-box svg { width: 28px; height: 28px; }
        .si-dus-subj-title { font-size: 18px; font-weight: 800; color: #831843; margin: 0; display: block; }
        .si-dus-subj-meta { font-size: 13px; font-weight: 600; color: #f472b6; display: block; margin-top: 4px; }
        
        .si-dus-inputs { display: flex; justify-content: space-between; gap: 12px; }
        .si-dus-input-group { flex: 1; text-align: center; }
        .si-dus-input-group label { display: block; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #db2777; margin-bottom: 8px; }
        .si-dus-input-group input { width: 100%; padding: 14px 5px; text-align: center; font-size: 18px; font-weight: 800; border-radius: 10px; border: 2px solid #e2e8f0; color: #0f172a; transition: 0.3s; box-sizing: border-box; }
        
        .si-dus-input-group.do input:focus { border-color: #10b981; background: #ecfdf5; outline: none; }
        .si-dus-input-group.ya input:focus { border-color: #ef4444; background: #fef2f2; outline: none; }
        .si-dus-input-group.ne input { background: #f8fafc; border-color: transparent; color: #db2777; }

        .si-btn-dus { width: 100%; padding: 22px; background: linear-gradient(135deg, #db2777, #be185d); color: #fff; border: none; border-radius: 14px; font-size: 20px; font-weight: 900; letter-spacing: 0.5px; cursor: pointer; transition: 0.3s; box-shadow: 0 8px 25px rgba(219, 39, 119, 0.3); display: flex; align-items: center; justify-content: center; gap: 10px; }
        .si-btn-dus:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(219, 39, 119, 0.4); }
        
        .si-dus-result-box { margin-top: 30px; background: #fff; border: 2px solid #db2777; border-radius: 16px; padding: 35px 20px; text-align: center; box-shadow: 0 10px 25px rgba(219, 39, 119, 0.1); animation: scaleUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .si-dus-result-label { font-size: 16px; font-weight: 800; color: #be185d; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 10px; }
        .si-dus-result-score { font-size: 52px; font-weight: 900; color: #831843; line-height: 1; }

        .si-info-text-dus { text-align:center; font-size:13px; color:#9d174d; margin-top:20px; background: #fff; padding: 15px; border-radius: 8px; border:1px solid #fce7f3;}
        
        @media (max-width: 768px) { .si-dus-wrapper { padding: 25px 15px; } .si-dus-grid { grid-template-columns: 1fr; } .si-dus-top-bar { text-align: center; } }
        @keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>

    <div class="si-dus-wrapper">
        <div class="si-dus-header">
            <h3 class="si-dus-title"><span><?php echo $guncel_yil; ?></span> DUS Puan Hesaplama</h3>
            <p class="si-dus-desc">Diş Hekimliğinde Uzmanlık Eğitimi Giriş Sınavı (4 Yanlış 1 Doğruyu Götürür)</p>
        </div>
        
        <form method="post" action="#dus-sonuc">
            
           <div class="si-dus-top-bar">
                <label for="dus_kesinti">
                    <input type="checkbox" name="dus_kesinti" id="dus_kesinti" value="1">
                    Uzmanlık eğitimine devam ederken sınava giriyorum (%5 Kesinti Uygula)
                </label>
            </div>

            <div class="si-dus-grid">
                <div class="si-dus-card" data-max="40">
                    <div class="si-dus-card-header">
                        <div class="si-dus-icon-box">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v7.31"></path><path d="M14 9.3V1.99"></path><path d="M8.5 2h7"></path><path d="M14 9.3a6.5 6.5 0 1 1-4 0"></path><path d="M5.52 16h12.96"></path></svg>
                        </div>
                        <div>
                            <h4 class="si-dus-subj-title">Temel Bilimler Testi</h4>
                            <span class="si-dus-subj-meta">40 Soru (Anatomi, Biyokimya vb.)</span>
                        </div>
                    </div>
                    <div class="si-dus-inputs">
                        <div class="si-dus-input-group do"><label>Doğru</label><input type="number" name="dus_temel_d" class="si-dus-d" min="0" max="40" placeholder="0"></div>
                        <div class="si-dus-input-group ya"><label>Yanlış</label><input type="number" name="dus_temel_y" class="si-dus-y" min="0" max="40" placeholder="0"></div>
                        <div class="si-dus-input-group ne"><label>Net</label><input type="number" step="0.25" name="dus_temel_n" class="si-dus-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>

                <div class="si-dus-card" data-max="80">
                    <div class="si-dus-card-header">
                        <div class="si-dus-icon-box">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
                        </div>
                        <div>
                            <h4 class="si-dus-subj-title">Klinik Bilimler Testi</h4>
                            <span class="si-dus-subj-meta">80 Soru (Restoratif, Endodonti vb.)</span>
                        </div>
                    </div>
                    <div class="si-dus-inputs">
                        <div class="si-dus-input-group do"><label>Doğru</label><input type="number" name="dus_klinik_d" class="si-dus-d" min="0" max="80" placeholder="0"></div>
                        <div class="si-dus-input-group ya"><label>Yanlış</label><input type="number" name="dus_klinik_y" class="si-dus-y" min="0" max="80" placeholder="0"></div>
                        <div class="si-dus-input-group ne"><label>Net</label><input type="number" step="0.25" name="dus_klinik_n" class="si-dus-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>
            </div>

            <button type="submit" name="hesapla_dus" class="si-btn-dus">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                DUS PUANIMI HESAPLA
            </button>
        </form>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const dusCards = document.querySelectorAll('.si-dus-card');
                dusCards.forEach(card => {
                    const dInput = card.querySelector('.si-dus-d'); const yInput = card.querySelector('.si-dus-y'); const nInput = card.querySelector('.si-dus-n');
                    const maxQ = parseInt(card.getAttribute('data-max'));
                    function calcDUSNet() {
                        let d = parseInt(dInput.value) || 0; let y = parseInt(yInput.value) || 0;
                        if (d + y > maxQ) { yInput.value = maxQ - d; y = maxQ - d; }
                        let net = d - (y / 4); nInput.value = net > 0 ? net.toFixed(2) : 0;
                    }
                    dInput.addEventListener('input', calcDUSNet); yInput.addEventListener('input', calcDUSNet);
                });
            });
        </script>

        <?php
        if(isset($_POST['hesapla_dus'])) {
            $n = function($isim) { return si_post_net($isim, 4); };
            $temel_net = $n('dus_temel');
            $klinik_net = $n('dus_klinik');
            
            $kesinti_uygula = isset($_POST['dus_kesinti']) ? true : false;

           // ADMİN PANELİNDEN DUS ÖSYM STANDART SAPMA AYARLARINI ÇEKİYORUZ
            $temel_ort = floatval(get_option('si_dus_temel_ort', '15.000'));
            $temel_ss = floatval(get_option('si_dus_temel_ss', '8.1925'));
            $klinik_ort = floatval(get_option('si_dus_klinik_ort', '35.000'));
            $klinik_ss = floatval(get_option('si_dus_klinik_ss', '19.1827'));

            // 1. Z-Skorlarını Bul: (Net - Ortalama) / Sapma
            $z_temel = ($temel_ss > 0) ? (($temel_net - $temel_ort) / $temel_ss) : 0;
            $z_klinik = ($klinik_ss > 0) ? (($klinik_net - $klinik_ort) / $klinik_ss) : 0;

            // 2. Standart Puanları (SP) Hesapla: 50 + (10 * Z-Skor)
            $sp_temel = 50 + (10 * $z_temel);
            $sp_klinik = 50 + (10 * $z_klinik);

            // 3. K ve T Puanlarını Hesapla (Belirttiğin katsayılara göre)
            $k_puani = ($sp_temel * 0.40) + ($sp_klinik * 0.60);
            $t_puani = ($sp_temel * 0.60) + ($sp_klinik * 0.40);
            
           // Eğer uzmanlık eğitimine devam ediyorsa puandan %5 kesilir
            if ($kesinti_uygula) {
                $k_puani = $k_puani * 0.95;
                $t_puani = $t_puani * 0.95;
            }
            
            if ($k_puani > 85) $k_puani = 85; 
            if ($t_puani > 85) $t_puani = 85;
            if ($temel_net <= 0 && $klinik_net <= 0) { $k_puani = 0; $t_puani = 0; }

            // Sonuçları yan yana iki kutuda göstermek için küçük bir grid yapısı ekliyoruz
            echo '<div id="dus-sonuc" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:20px; margin-top:30px;">';
            
            echo '<div class="si-dus-result-box" style="margin-top:0;">';
            echo '<span class="si-dus-result-label">DUS K (Klinik) Puanınız</span>';
            echo '<span class="si-dus-result-score">' . number_format($k_puani, 4, ',', '.') . '</span>';
            echo '</div>';

            echo '<div class="si-dus-result-box" style="margin-top:0;">';
            echo '<span class="si-dus-result-label">DUS T (Temel) Puanınız</span>';
            echo '<span class="si-dus-result-score">' . number_format($t_puani, 4, ',', '.') . '</span>';
            echo '</div>';

            echo '</div>';

           if ($kesinti_uygula) {
                echo '<div style="text-align:center; margin-top:15px; color:#ef4444; font-weight:bold; font-size:15px;">⚠️ Uzmanlık eğitimine devam ettiğiniz için puanınızdan %5 kesinti yapılmıştır.</div>';
            }

            echo '<div class="si-info-text-dus"><strong>Önemli Bilgilendirme:</strong> Bu aracın resmi bir geçerliliği yoktur. Sonuçlar ÖSYM\'nin uyguladığı Standart Puan (SP) algoritmasına göre hesaplanmıştır. DUS\'ta 4 yanlış 1 doğruyu götürmektedir.</div>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('dus_hesapla', 'soruindir_dus_hesapla_shortcode');

// ========================================================================
// 14. EKPSS PUAN HESAPLAMA MODÜLÜ (Seviyeli)
// ========================================================================
function soruindir_ekpss_hesapla_shortcode() {
    ob_start(); 
    $guncel_yil = date("Y");
    ?>
    <style>
        .si-ekpss-wrapper { background: #f5f3ff; border: 2px solid #ddd6fe; border-radius: 20px; padding: 40px 30px; max-width: 800px; margin: 0 auto 30px auto; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.04); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .si-ekpss-header { text-align: center; margin-bottom: 25px; }
        .si-ekpss-title { color: #4c1d95; font-size: 28px; font-weight: 900; margin: 0 0 10px 0; letter-spacing: -0.5px; }
        .si-ekpss-title span { color: #7c3aed; }
        .si-ekpss-desc { color: #5b21b6; font-size: 15px; margin: 0; }
        
        /* Seviye Seçim Butonları */
        .si-ekpss-levels { display: flex; justify-content: center; flex-wrap: wrap; gap: 15px; margin-bottom: 30px; }
        .si-ekpss-levels label { cursor: pointer; position: relative; }
        .si-ekpss-levels input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
        .si-ekpss-level-btn { display: inline-block; padding: 12px 24px; background: #fff; border: 2px solid #ddd6fe; border-radius: 12px; font-size: 15px; font-weight: 800; color: #6d28d9; transition: all 0.3s ease; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
        .si-ekpss-levels input[type="radio"]:checked + .si-ekpss-level-btn { background: #7c3aed; color: #fff; border-color: #7c3aed; box-shadow: 0 8px 20px rgba(124, 58, 237, 0.3); transform: translateY(-2px); }
        
        .si-ekpss-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 35px; }
        .si-ekpss-card { background: #fff; border-radius: 16px; padding: 25px; border: 1px solid #ede9fe; display: flex; flex-direction: column; gap: 20px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.05); }
        .si-ekpss-card:hover { transform: translateY(-5px); border-color: #8b5cf6; box-shadow: 0 10px 25px rgba(124, 58, 237, 0.15); }
        
        .si-ekpss-card-header { display: flex; align-items: center; gap: 15px; border-bottom: 1px dashed #ede9fe; padding-bottom: 15px; }
        .si-ekpss-icon-box { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: #f5f3ff; color: #7c3aed; }
        .si-ekpss-icon-box svg { width: 28px; height: 28px; }
        .si-ekpss-subj-title { font-size: 18px; font-weight: 800; color: #4c1d95; margin: 0; display: block; }
        .si-ekpss-subj-meta { font-size: 13px; font-weight: 600; color: #8b5cf6; display: block; margin-top: 4px; }
        
        .si-ekpss-inputs { display: flex; justify-content: space-between; gap: 12px; }
        .si-ekpss-input-group { flex: 1; text-align: center; }
        .si-ekpss-input-group label { display: block; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #7c3aed; margin-bottom: 8px; }
        .si-ekpss-input-group input { width: 100%; padding: 14px 5px; text-align: center; font-size: 18px; font-weight: 800; border-radius: 10px; border: 2px solid #e2e8f0; color: #0f172a; transition: 0.3s; box-sizing: border-box; }
        
        .si-ekpss-input-group.do input:focus { border-color: #10b981; background: #ecfdf5; outline: none; }
        .si-ekpss-input-group.ya input:focus { border-color: #ef4444; background: #fef2f2; outline: none; }
        .si-ekpss-input-group.ne input { background: #f8fafc; border-color: transparent; color: #7c3aed; }

        .si-btn-ekpss { width: 100%; padding: 22px; background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: #fff; border: none; border-radius: 14px; font-size: 20px; font-weight: 900; letter-spacing: 0.5px; cursor: pointer; transition: 0.3s; box-shadow: 0 8px 25px rgba(139, 92, 246, 0.3); display: flex; align-items: center; justify-content: center; gap: 10px; }
        .si-btn-ekpss:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(139, 92, 246, 0.4); }
        
        .si-ekpss-result-box { margin-top: 30px; background: #fff; border: 2px solid #8b5cf6; border-radius: 16px; padding: 35px 20px; text-align: center; box-shadow: 0 10px 25px rgba(139, 92, 246, 0.1); animation: scaleUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .si-ekpss-result-label { font-size: 16px; font-weight: 800; color: #6d28d9; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 10px; }
        .si-ekpss-result-score { font-size: 52px; font-weight: 900; color: #4c1d95; line-height: 1; }
        .si-ekpss-result-level-badge { display: inline-block; margin-top: 10px; padding: 5px 12px; background: #f5f3ff; color: #6d28d9; border-radius: 8px; font-size: 13px; font-weight: 700; }

        .si-info-text-ekpss { text-align:center; font-size:13px; color:#5b21b6; margin-top:20px; background: #fff; padding: 15px; border-radius: 8px; border:1px solid #ede9fe;}
        
        @media (max-width: 768px) { .si-ekpss-wrapper { padding: 25px 15px; } .si-ekpss-grid { grid-template-columns: 1fr; } }
        @keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>

    <div class="si-ekpss-wrapper">
        <div class="si-ekpss-header">
            <h3 class="si-ekpss-title"><span><?php echo $guncel_yil; ?></span> EKPSS Puan Hesaplama</h3>
            <p class="si-ekpss-desc">Eğitim Seviyenize Göre Puanınızı Hesaplayın</p>
        </div>
        
        <form method="post" action="#ekpss-sonuc">
            
            <div class="si-ekpss-levels">
                <label>
                    <input type="radio" name="ekpss_seviye" value="ortaogretim" required checked>
                    <span class="si-ekpss-level-btn">Ortaöğretim (Lise)</span>
                </label>
                <label>
                    <input type="radio" name="ekpss_seviye" value="onlisans" required>
                    <span class="si-ekpss-level-btn">Önlisans</span>
                </label>
                <label>
                    <input type="radio" name="ekpss_seviye" value="lisans" required>
                    <span class="si-ekpss-level-btn">Lisans</span>
                </label>
            </div>

            <div class="si-ekpss-grid">
                <div class="si-ekpss-card" data-max="30">
                    <div class="si-ekpss-card-header">
                        <div class="si-ekpss-icon-box">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        </div>
                        <div>
                            <h4 class="si-ekpss-subj-title">Genel Yetenek</h4>
                            <span class="si-ekpss-subj-meta">30 Soru (Türkçe, Matematik)</span>
                        </div>
                    </div>
                    <div class="si-ekpss-inputs">
                        <div class="si-ekpss-input-group do"><label>Doğru</label><input type="number" name="ekpss_gy_d" class="si-ekpss-d" min="0" max="30" placeholder="0"></div>
                        <div class="si-ekpss-input-group ya"><label>Yanlış</label><input type="number" name="ekpss_gy_y" class="si-ekpss-y" min="0" max="30" placeholder="0"></div>
                        <div class="si-ekpss-input-group ne"><label>Net</label><input type="number" step="0.25" name="ekpss_gy_n" class="si-ekpss-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>

                <div class="si-ekpss-card" data-max="30">
                    <div class="si-ekpss-card-header">
                        <div class="si-ekpss-icon-box">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                        </div>
                        <div>
                            <h4 class="si-ekpss-subj-title">Genel Kültür</h4>
                            <span class="si-ekpss-subj-meta">30 Soru (Tarih, Coğrafya, Vat.)</span>
                        </div>
                    </div>
                    <div class="si-ekpss-inputs">
                        <div class="si-ekpss-input-group do"><label>Doğru</label><input type="number" name="ekpss_gk_d" class="si-ekpss-d" min="0" max="30" placeholder="0"></div>
                        <div class="si-ekpss-input-group ya"><label>Yanlış</label><input type="number" name="ekpss_gk_y" class="si-ekpss-y" min="0" max="30" placeholder="0"></div>
                        <div class="si-ekpss-input-group ne"><label>Net</label><input type="number" step="0.25" name="ekpss_gk_n" class="si-ekpss-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>
            </div>

            <button type="submit" name="hesapla_ekpss" class="si-btn-ekpss">
                EKPSS PUANIMI HESAPLA
            </button>
        </form>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const ekpssCards = document.querySelectorAll('.si-ekpss-card');
                ekpssCards.forEach(card => {
                    const dInput = card.querySelector('.si-ekpss-d'); const yInput = card.querySelector('.si-ekpss-y'); const nInput = card.querySelector('.si-ekpss-n');
                    const maxQ = parseInt(card.getAttribute('data-max'));
                    function calcEKPSSNet() {
                        let d = parseInt(dInput.value) || 0; let y = parseInt(yInput.value) || 0;
                        if (d + y > maxQ) { yInput.value = maxQ - d; y = maxQ - d; }
                        let net = d - (y / 4); nInput.value = net > 0 ? net.toFixed(2) : 0;
                    }
                    dInput.addEventListener('input', calcEKPSSNet); yInput.addEventListener('input', calcEKPSSNet);
                });
            });
        </script>

        <?php
        if(isset($_POST['hesapla_ekpss'])) {
            $n = function($isim) { return si_post_net($isim, 4); };
            $gy_net = $n('ekpss_gy');
            $gk_net = $n('ekpss_gk');
            $secilen_seviye = isset($_POST['ekpss_seviye']) ? $_POST['ekpss_seviye'] : 'ortaogretim';

           // ADMİN PANELİNDEN SEVİYEYE GÖRE DEĞERLERİ ÇEKİYORUZ
            if ($secilen_seviye == 'lisans') {
                $taban = floatval(get_option('si_ekpss_lisans_taban', '40.00'));
                $gy_kat = floatval(get_option('si_ekpss_lisans_gy', '0.95'));
                $gk_kat = floatval(get_option('si_ekpss_lisans_gk', '1.05'));
                $seviye_text = "Lisans";
            } elseif ($secilen_seviye == 'onlisans') {
                $taban = floatval(get_option('si_ekpss_onlisans_taban', '40.00'));
                $gy_kat = floatval(get_option('si_ekpss_onlisans_gy', '1.00'));
                $gk_kat = floatval(get_option('si_ekpss_onlisans_gk', '1.00'));
                $seviye_text = "Önlisans";
            } else {
                $taban = floatval(get_option('si_ekpss_orta_taban', '40.00'));
                $gy_kat = floatval(get_option('si_ekpss_orta_gy', '1.05'));
                $gk_kat = floatval(get_option('si_ekpss_orta_gk', '0.95'));
                $seviye_text = "Ortaöğretim";
            }

            // Puan Hesaplaması
            $ekpss_puan = $taban + ($gy_net * $gy_kat) + ($gk_net * $gk_kat);
            
            if ($ekpss_puan > 100) $ekpss_puan = 100;
            if ($gy_net <= 0 && $gk_net <= 0) $ekpss_puan = 0;

            echo '<div id="ekpss-sonuc" class="si-ekpss-result-box">';
            echo '<span class="si-ekpss-result-label">Tahmini EKPSS Puanınız</span>';
            echo '<span class="si-ekpss-result-score">' . number_format($ekpss_puan, 3, ',', '.') . '</span>';
            echo '<span class="si-ekpss-result-level-badge">Hesaplanan Seviye: ' . $seviye_text . '</span>';
            echo '</div>';

            echo '<div class="si-info-text-ekpss"><strong>Önemli Bilgilendirme:</strong> Bu hesaplama aracının resmi bir geçerliliği yoktur. EKPSS puanları adayın öğrenim düzeyine ve ÖSYM\'nin uyguladığı o yılki standart sapmalara göre farklılık gösterebilir.</div>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('ekpss_hesapla', 'soruindir_ekpss_hesapla_shortcode');

// ========================================================================
// 15. HAKİM VE SAVCI YARDIMCILIĞI PUAN HESAPLAMA MODÜLÜ
// ========================================================================
function soruindir_hsy_hesapla_shortcode() {
    ob_start(); 
    $guncel_yil = date("Y");
    ?>
    <style>
        .si-hsy-wrapper { background: #fef2f2; border: 2px solid #fecaca; border-radius: 20px; padding: 40px 30px; max-width: 900px; margin: 0 auto 30px auto; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.04); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .si-hsy-header { text-align: center; margin-bottom: 35px; }
        .si-hsy-title { color: #7f1d1d; font-size: 28px; font-weight: 900; margin: 0 0 10px 0; letter-spacing: -0.5px; }
        .si-hsy-title span { color: #b91c1c; }
        .si-hsy-desc { color: #991b1b; font-size: 15px; margin: 0; }
        
        .si-hsy-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 35px; }
        .si-hsy-card { background: #fff; border-radius: 16px; padding: 25px; border: 1px solid #fee2e2; display: flex; flex-direction: column; gap: 20px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(185, 28, 28, 0.05); }
        .si-hsy-card:hover { transform: translateY(-5px); border-color: #b91c1c; box-shadow: 0 10px 25px rgba(185, 28, 28, 0.15); }
        
        .si-hsy-card-header { display: flex; align-items: center; gap: 15px; border-bottom: 1px dashed #fee2e2; padding-bottom: 15px; }
        .si-hsy-icon-box { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: #fef2f2; color: #b91c1c; }
        .si-hsy-icon-box svg { width: 28px; height: 28px; }
        .si-hsy-subj-title { font-size: 16px; font-weight: 800; color: #7f1d1d; margin: 0; display: block; }
        .si-hsy-subj-meta { font-size: 12px; font-weight: 600; color: #ef4444; display: block; margin-top: 4px; }
        
        .si-hsy-inputs { display: flex; justify-content: space-between; gap: 12px; }
        .si-hsy-input-group { flex: 1; text-align: center; }
        .si-hsy-input-group label { display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; color: #b91c1c; margin-bottom: 8px; }
        .si-hsy-input-group input { width: 100%; padding: 12px 5px; text-align: center; font-size: 18px; font-weight: 800; border-radius: 10px; border: 2px solid #e2e8f0; color: #0f172a; transition: 0.3s; box-sizing: border-box; }
        
        .si-hsy-input-group.do input:focus { border-color: #10b981; background: #ecfdf5; outline: none; }
        .si-hsy-input-group.ya input:focus { border-color: #ef4444; background: #fef2f2; outline: none; }
        .si-hsy-input-group.ne input { background: #f8fafc; border-color: transparent; color: #b91c1c; }

        .si-btn-hsy { width: 100%; padding: 22px; background: linear-gradient(135deg, #b91c1c, #7f1d1d); color: #fff; border: none; border-radius: 14px; font-size: 20px; font-weight: 900; letter-spacing: 0.5px; cursor: pointer; transition: 0.3s; box-shadow: 0 8px 25px rgba(185, 28, 28, 0.3); display: flex; align-items: center; justify-content: center; gap: 10px; }
        .si-btn-hsy:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(185, 28, 28, 0.4); }
        
        .si-hsy-results-container { display: flex; flex-direction: column; gap: 15px; margin-top: 30px; }
        .si-hsy-result-box { background: #fff; border: 2px solid #b91c1c; border-radius: 16px; padding: 25px 20px; text-align: center; box-shadow: 0 10px 25px rgba(185, 28, 28, 0.1); display: flex; flex-direction: column; align-items: center; animation: scaleUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .si-hsy-result-label { font-size: 15px; font-weight: 800; color: #991b1b; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 8px; }
        .si-hsy-result-score { font-size: 46px; font-weight: 900; color: #7f1d1d; line-height: 1; }

        .si-info-text-hsy { text-align:center; font-size:13px; color:#991b1b; margin-top:25px; background: #fff; padding: 15px; border-radius: 8px; border:1px solid #fee2e2;}
        
        @media (max-width: 768px) { .si-hsy-wrapper { padding: 25px 15px; } .si-hsy-grid { grid-template-columns: 1fr; } }
        @keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>

    <div class="si-hsy-wrapper">
        <div class="si-hsy-header">
            <h3 class="si-hsy-title"><span><?php echo $guncel_yil; ?></span> Hakim ve Savcı Yardımcılığı Sınavı</h3>
            <p class="si-hsy-desc">Girdiğiniz alanların netlerini hesaplayarak başarı puanlarınızı öğrenin.</p>
        </div>
        
        <form method="post" action="#hsy-sonuc">
            <div class="si-hsy-grid">
                
                <div class="si-hsy-card" data-max="30">
                    <div class="si-hsy-card-header">
                        <div class="si-hsy-icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg></div>
                        <div><h4 class="si-hsy-subj-title">Genel Yet. ve Genel. Kült.</h4><span class="si-hsy-subj-meta">30 Soru</span></div>
                    </div>
                    <div class="si-hsy-inputs">
                        <div class="si-hsy-input-group do"><label>Doğru</label><input type="number" name="hsy_gygk_d" class="si-hsy-d" min="0" max="30" placeholder="0"></div>
                        <div class="si-hsy-input-group ya"><label>Yanlış</label><input type="number" name="hsy_gygk_y" class="si-hsy-y" min="0" max="30" placeholder="0"></div>
                        <div class="si-hsy-input-group ne"><label>Net</label><input type="number" step="0.25" name="hsy_gygk_n" class="si-hsy-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>

                <div class="si-hsy-card" data-max="35">
                    <div class="si-hsy-card-header">
                        <div class="si-hsy-icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg></div>
                        <div><h4 class="si-hsy-subj-title">Ortak Alan Bilgisi</h4><span class="si-hsy-subj-meta">35 Soru</span></div>
                    </div>
                    <div class="si-hsy-inputs">
                        <div class="si-hsy-input-group do"><label>Doğru</label><input type="number" name="hsy_ortak_d" class="si-hsy-d" min="0" max="35" placeholder="0"></div>
                        <div class="si-hsy-input-group ya"><label>Yanlış</label><input type="number" name="hsy_ortak_y" class="si-hsy-y" min="0" max="35" placeholder="0"></div>
                        <div class="si-hsy-input-group ne"><label>Net</label><input type="number" step="0.25" name="hsy_ortak_n" class="si-hsy-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>

                <div class="si-hsy-card" data-max="35">
                    <div class="si-hsy-card-header">
                        <div class="si-hsy-icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg></div>
                        <div><h4 class="si-hsy-subj-title">Adli Yargı</h4><span class="si-hsy-subj-meta">35 Soru</span></div>
                    </div>
                    <div class="si-hsy-inputs">
                        <div class="si-hsy-input-group do"><label>Doğru</label><input type="number" name="hsy_adli_d" class="si-hsy-d" min="0" max="35" placeholder="0"></div>
                        <div class="si-hsy-input-group ya"><label>Yanlış</label><input type="number" name="hsy_adli_y" class="si-hsy-y" min="0" max="35" placeholder="0"></div>
                        <div class="si-hsy-input-group ne"><label>Net</label><input type="number" step="0.25" name="hsy_adli_n" class="si-hsy-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>

                <div class="si-hsy-card" data-max="35">
                    <div class="si-hsy-card-header">
                        <div class="si-hsy-icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"></path></svg></div>
                        <div><h4 class="si-hsy-subj-title">İdari Yargı</h4><span class="si-hsy-subj-meta">35 Soru</span></div>
                    </div>
                    <div class="si-hsy-inputs">
                        <div class="si-hsy-input-group do"><label>Doğru</label><input type="number" name="hsy_idari_d" class="si-hsy-d" min="0" max="35" placeholder="0"></div>
                        <div class="si-hsy-input-group ya"><label>Yanlış</label><input type="number" name="hsy_idari_y" class="si-hsy-y" min="0" max="35" placeholder="0"></div>
                        <div class="si-hsy-input-group ne"><label>Net</label><input type="number" step="0.25" name="hsy_idari_n" class="si-hsy-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>

                <div class="si-hsy-card" data-max="35">
                    <div class="si-hsy-card-header">
                        <div class="si-hsy-icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg></div>
                        <div><h4 class="si-hsy-subj-title">Adli Yargı-Avukat</h4><span class="si-hsy-subj-meta">35 Soru</span></div>
                    </div>
                    <div class="si-hsy-inputs">
                        <div class="si-hsy-input-group do"><label>Doğru</label><input type="number" name="hsy_adliav_d" class="si-hsy-d" min="0" max="35" placeholder="0"></div>
                        <div class="si-hsy-input-group ya"><label>Yanlış</label><input type="number" name="hsy_adliav_y" class="si-hsy-y" min="0" max="35" placeholder="0"></div>
                        <div class="si-hsy-input-group ne"><label>Net</label><input type="number" step="0.25" name="hsy_adliav_n" class="si-hsy-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>

            </div>

            <button type="submit" name="hesapla_hsy" class="si-btn-hsy">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="9" x2="15" y2="15"></line><line x1="15" y1="9" x2="9" y2="15"></line></svg>
                BAŞARI PUANLARIMI HESAPLA
            </button>
        </form>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const hsyCards = document.querySelectorAll('.si-hsy-card');
                hsyCards.forEach(card => {
                    const dInput = card.querySelector('.si-hsy-d'); const yInput = card.querySelector('.si-hsy-y'); const nInput = card.querySelector('.si-hsy-n');
                    const maxQ = parseInt(card.getAttribute('data-max'));
                    function calcHSYNet() {
                        let d = parseInt(dInput.value) || 0; let y = parseInt(yInput.value) || 0;
                        if (d + y > maxQ) { yInput.value = maxQ - d; y = maxQ - d; }
                        let net = d - (y / 4); nInput.value = net > 0 ? net.toFixed(2) : 0;
                    }
                    dInput.addEventListener('input', calcHSYNet); yInput.addEventListener('input', calcHSYNet);
                });
            });
        </script>

        <?php
        if(isset($_POST['hesapla_hsy'])) {
            $n = function($isim) { return si_post_net($isim, 4); };
            $gygk_n = $n('hsy_gygk');
            $ortak_n = $n('hsy_ortak');
            $adli_n = $n('hsy_adli');
            $idari_n = $n('hsy_idari');
            $adliav_n = $n('hsy_adliav');

            // ADMİN PANELİNDEN Z-SKORU VERİLERİNİ ÇEKİYORUZ
            $o_gygk = floatval(get_option('si_hsy_gygk_ort', '14.200')); $ss_gygk = floatval(get_option('si_hsy_gygk_ss', '5.100'));
            $o_ortak = floatval(get_option('si_hsy_ortak_ort', '17.500')); $ss_ortak = floatval(get_option('si_hsy_ortak_ss', '6.200'));
            $o_adli = floatval(get_option('si_hsy_adli_ort', '16.800')); $ss_adli = floatval(get_option('si_hsy_adli_ss', '5.900'));
            $o_idari = floatval(get_option('si_hsy_idari_ort', '11.500')); $ss_idari = floatval(get_option('si_hsy_idari_ss', '4.800'));
            $o_adliav = floatval(get_option('si_hsy_adliav_ort', '15.200')); $ss_adliav = floatval(get_option('si_hsy_adliav_ss', '5.700'));
            
            // 1. ADAYIN STANDART PUANLARINI (SP) HESAPLA
            $sp_gygk = ($ss_gygk > 0) ? 50 + 10 * (($gygk_n - $o_gygk) / $ss_gygk) : 0;
            $sp_ortak = ($ss_ortak > 0) ? 50 + 10 * (($ortak_n - $o_ortak) / $ss_ortak) : 0;
            $sp_adli = ($ss_adli > 0) ? 50 + 10 * (($adli_n - $o_adli) / $ss_adli) : 0;
            $sp_idari = ($ss_idari > 0) ? 50 + 10 * (($idari_n - $o_idari) / $ss_idari) : 0;
            $sp_adliav = ($ss_adliav > 0) ? 50 + 10 * (($adliav_n - $o_adliav) / $ss_adliav) : 0;

            // 2. TEORİK MAKSİMUM STANDART PUANLARI (MAX SP) HESAPLA (Tam Net Senaryosu)
            $max_sp_gygk = ($ss_gygk > 0) ? 50 + 10 * ((30 - $o_gygk) / $ss_gygk) : 0;
            $max_sp_ortak = ($ss_ortak > 0) ? 50 + 10 * ((35 - $o_ortak) / $ss_ortak) : 0;
            $max_sp_adli = ($ss_adli > 0) ? 50 + 10 * ((35 - $o_adli) / $ss_adli) : 0;
            $max_sp_idari = ($ss_idari > 0) ? 50 + 10 * ((35 - $o_idari) / $ss_idari) : 0;
            $max_sp_adliav = ($ss_adliav > 0) ? 50 + 10 * ((35 - $o_adliav) / $ss_adliav) : 0;

            // 3. ADAYIN AĞIRLIKLI STANDART PUANLARINI (ASP) HESAPLA
            $ap_adli = ($sp_gygk * 0.20) + ($sp_ortak * 0.35) + ($sp_adli * 0.45);
            $ap_idari = ($sp_gygk * 0.20) + ($sp_ortak * 0.35) + ($sp_idari * 0.45);
            $ap_adliav = ($sp_gygk * 0.20) + ($sp_ortak * 0.35) + ($sp_adliav * 0.45);
            
            // 4. MAKSİMUM ASP DEĞERLERİNİ HESAPLA
            $max_ap_adli = ($max_sp_gygk * 0.20) + ($max_sp_ortak * 0.35) + ($max_sp_adli * 0.45);
            $max_ap_idari = ($max_sp_gygk * 0.20) + ($max_sp_ortak * 0.35) + ($max_sp_idari * 0.45);
            $max_ap_adliav = ($max_sp_gygk * 0.20) + ($max_sp_ortak * 0.35) + ($max_sp_adliav * 0.45);
            
            // 5. NİHAİ PUANI 100'E ORANTILA (ÖSYM FORMÜLÜ: User_ASP / Max_ASP * 100)
            $p_adli = ($max_ap_adli > 0) ? ($ap_adli / $max_ap_adli) * 100 : 0;
            $p_idari = ($max_ap_idari > 0) ? ($ap_idari / $max_ap_idari) * 100 : 0;
            $p_adliav = ($max_ap_adliav > 0) ? ($ap_adliav / $max_ap_adliav) * 100 : 0;

            // Güvenlik sınırları
            if ($p_adli > 100) $p_adli = 100; if ($p_adli < 0) $p_adli = 0;
            if ($p_idari > 100) $p_idari = 100; if ($p_idari < 0) $p_idari = 0;
            if ($p_adliav > 100) $p_adliav = 100; if ($p_adliav < 0) $p_adliav = 0;

            echo '<div id="hsy-sonuc" class="si-hsy-results-container">';
            
            // Sadece veri girilen (0'dan büyük) puan türlerini göster
            if ($p_adli > 0 && ($adli_n > 0 || $gygk_n > 0 || $ortak_n > 0)) {
                echo '<div class="si-hsy-result-box">';
                echo '<span class="si-hsy-result-label">Adli Yargı Başarı Puanı</span>';
                echo '<span class="si-hsy-result-score">' . number_format($p_adli, 5, ',', '.') . '</span>';
                echo '</div>';
            }

            if ($p_idari > 0 && ($idari_n > 0 || $gygk_n > 0 || $ortak_n > 0)) {
                echo '<div class="si-hsy-result-box">';
                echo '<span class="si-hsy-result-label">İdari Yargı Başarı Puanı</span>';
                echo '<span class="si-hsy-result-score">' . number_format($p_idari, 5, ',', '.') . '</span>';
                echo '</div>';
            }

            if ($p_adliav > 0 && ($adliav_n > 0 || $gygk_n > 0 || $ortak_n > 0)) {
                echo '<div class="si-hsy-result-box">';
                echo '<span class="si-hsy-result-label">Adli Yargı-Avukat Başarı Puanı</span>';
                echo '<span class="si-hsy-result-score">' . number_format($p_adliav, 5, ',', '.') . '</span>';
                echo '</div>';
            }
            
            if ($p_adli == 0 && $p_idari == 0 && $p_adliav == 0 && $gygk_n == 0 && $ortak_n == 0) {
                echo '<div class="si-hsy-result-box"><span class="si-hsy-result-label">Lütfen net bilgilerinizi giriniz.</span></div>';
            }

            echo '</div>';

            echo '<div class="si-info-text-hsy"><strong>Bilgilendirme:</strong> HSY sınavında <strong>4 yanlış 1 doğruyu götürmektedir.</strong> Puan hesaplaması tıpkı ÖSYM sisteminde olduğu gibi her testin Türkiye Ortalaması ve Standart Sapması alınarak "Z-Skoru" ve "ASP" formülüyle hesaplanmakta, tam net ise %100 doğruluğa sahip olacak şekilde 100 tam puana otomatik sabitlenmektedir.</div>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('hsy_hesapla', 'soruindir_hsy_hesapla_shortcode');

// ========================================================================
// 16. İOKBS (BURSLULUK) PUAN HESAPLAMA MODÜLÜ (GÜNCEL 80 SORU SİSTEMİ)
// ========================================================================
function soruindir_iokbs_hesapla_shortcode() {
    ob_start(); 
    $guncel_yil = date("Y");
    ?>
    <style>
        .si-iokbs-wrapper { background: #fffbeb; border: 2px solid #fde68a; border-radius: 20px; padding: 40px 30px; max-width: 900px; margin: 0 auto 30px auto; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.04); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .si-iokbs-header { text-align: center; margin-bottom: 30px; }
        .si-iokbs-title { color: #b45309; font-size: 28px; font-weight: 900; margin: 0 0 10px 0; letter-spacing: -0.5px; }
        .si-iokbs-title span { color: #d97706; }
        .si-iokbs-desc { color: #d97706; font-size: 15px; margin: 0; }
        
        .si-iokbs-top-options { display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap; justify-content: center; background: #fff; padding: 20px; border-radius: 16px; border: 1px solid #fef3c7; }
        .si-iokbs-opt-group { flex: 1; min-width: 250px; }
        .si-iokbs-opt-group label { display: block; font-size: 13px; font-weight: 800; color: #b45309; margin-bottom: 8px; }
        .si-iokbs-opt-group select { width: 100%; padding: 14px; border-radius: 12px; border: 2px solid #fde68a; font-weight: 700; color: #b45309; background: #fffbeb; outline: none; transition: 0.3s; cursor: pointer; }
        .si-iokbs-opt-group select:focus { border-color: #f59e0b; }

        .si-iokbs-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 35px; }
        .si-iokbs-card { background: #fff; border-radius: 16px; padding: 25px; border: 1px solid #fef3c7; display: flex; flex-direction: column; gap: 20px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(217, 119, 6, 0.05); }
        .si-iokbs-card:hover { transform: translateY(-5px); border-color: #f59e0b; box-shadow: 0 10px 25px rgba(217, 119, 6, 0.15); }
        
        .si-iokbs-card-header { display: flex; align-items: center; gap: 15px; border-bottom: 1px dashed #fef3c7; padding-bottom: 15px; }
        .si-iokbs-icon-box { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: #fffbeb; color: #f59e0b; }
        .si-iokbs-icon-box svg { width: 28px; height: 28px; }
        .si-iokbs-subj-title { font-size: 17px; font-weight: 800; color: #b45309; margin: 0; display: block; }
        .si-iokbs-subj-meta { font-size: 13px; font-weight: 600; color: #f59e0b; display: block; margin-top: 4px; }
        
        .si-iokbs-inputs { display: flex; justify-content: space-between; gap: 12px; }
        .si-iokbs-input-group { flex: 1; text-align: center; }
        .si-iokbs-input-group label { display: block; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #d97706; margin-bottom: 8px; }
        .si-iokbs-input-group input { width: 100%; padding: 14px 5px; text-align: center; font-size: 18px; font-weight: 800; border-radius: 10px; border: 2px solid #e2e8f0; color: #0f172a; transition: 0.3s; box-sizing: border-box; }
        
        .si-iokbs-input-group.do input:focus { border-color: #10b981; background: #ecfdf5; outline: none; }
        .si-iokbs-input-group.ya input:focus { border-color: #ef4444; background: #fef2f2; outline: none; }
        .si-iokbs-input-group.ne input { background: #f8fafc; border-color: transparent; color: #d97706; }

        .si-btn-iokbs { width: 100%; padding: 22px; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; border: none; border-radius: 14px; font-size: 20px; font-weight: 900; letter-spacing: 0.5px; cursor: pointer; transition: 0.3s; box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3); display: flex; align-items: center; justify-content: center; gap: 10px; }
        .si-btn-iokbs:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(245, 158, 11, 0.4); }
        
        .si-iokbs-result-box { margin-top: 30px; background: #fff; border: 2px solid #e2e8f0; border-radius: 16px; padding: 35px 20px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.05); animation: scaleUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .si-iokbs-result-box.basarili { border-color: #10b981; background: #ecfdf5; }
        .si-iokbs-result-box.basarisiz { border-color: #ef4444; background: #fef2f2; }
        
        .si-iokbs-result-label { font-size: 16px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 10px; }
        .si-iokbs-result-score { font-size: 56px; font-weight: 900; line-height: 1; display: block; margin-bottom: 15px;}
        .si-iokbs-target-note { font-size: 14px; font-weight: 700; padding: 8px 15px; border-radius: 8px; display: inline-block; }
        
        .basarili .si-iokbs-result-label, .basarili .si-iokbs-result-score { color: #047857; }
        .basarili .si-iokbs-target-note { background: #d1fae5; color: #065f46; }
        .basarisiz .si-iokbs-result-label, .basarisiz .si-iokbs-result-score { color: #b91c1c; }
        .basarisiz .si-iokbs-target-note { background: #fee2e2; color: #991b1b; }

        .si-info-text-iokbs { text-align:center; font-size:13px; color:#b45309; margin-top:20px; background: #fff; padding: 15px; border-radius: 8px; border:1px solid #fef3c7;}
        
        @media (max-width: 768px) { .si-iokbs-wrapper { padding: 25px 15px; } .si-iokbs-grid { grid-template-columns: 1fr; } }
        @keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>

    <div class="si-iokbs-wrapper">
        <div class="si-iokbs-header">
            <h3 class="si-iokbs-title"><span><?php echo $guncel_yil; ?></span> İOKBS (Bursluluk) Puan Hesaplama</h3>
            <p class="si-iokbs-desc">Güncel MEB Sistemine Göre (80 Soru - 3 Yanlış 1 Doğruyu Götürür)</p>
        </div>
        
        <form method="post" action="#iokbs-sonuc">
            
            <div class="si-iokbs-top-options">
                <div class="si-iokbs-opt-group">
                    <label>Sınıfınız</label>
                    <select name="iokbs_sinif" required>
                        <option value="5">5. Sınıf</option>
                        <option value="6">6. Sınıf</option>
                        <option value="7">7. Sınıf</option>
                        <option value="8">8. Sınıf / Hazırlık</option>
                        <option value="9">9. Sınıf</option>
                        <option value="10">10. Sınıf</option>
                        <option value="11">11. Sınıf</option>
                    </select>
                </div>
                <div class="si-iokbs-opt-group">
                    <label>Kontenjan Türü</label>
                    <select name="iokbs_kontenjan" required>
                        <option value="diger">Diğer Çocuk Kontenjanı</option>
                        <option value="ogretmen">Öğretmen Çocuğu Kontenjanı</option>
                        <option value="okulyok">Oturduğu Yerde Okul Yok Kontenjanı</option>
                        <option value="kanun">Kanunla Özel Hak Tanınan Kontenjanı</option>
                    </select>
                </div>
            </div>

            <div class="si-iokbs-grid">
                
                <div class="si-iokbs-card" data-max="20">
                    <div class="si-iokbs-card-header">
                        <div class="si-iokbs-icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg></div>
                        <div><h4 class="si-iokbs-subj-title">Türkçe / Edebiyat</h4><span class="si-iokbs-subj-meta">20 Soru</span></div>
                    </div>
                    <div class="si-iokbs-inputs">
                        <div class="si-iokbs-input-group do"><label>Doğru</label><input type="number" name="iokbs_turkce_d" class="si-iokbs-d" min="0" max="20" placeholder="0"></div>
                        <div class="si-iokbs-input-group ya"><label>Yanlış</label><input type="number" name="iokbs_turkce_y" class="si-iokbs-y" min="0" max="20" placeholder="0"></div>
                        <div class="si-iokbs-input-group ne"><label>Net</label><input type="number" step="0.33" name="iokbs_turkce_n" class="si-iokbs-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>

                <div class="si-iokbs-card" data-max="20">
                    <div class="si-iokbs-card-header">
                        <div class="si-iokbs-icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg></div>
                        <div><h4 class="si-iokbs-subj-title">Matematik</h4><span class="si-iokbs-subj-meta">20 Soru</span></div>
                    </div>
                    <div class="si-iokbs-inputs">
                        <div class="si-iokbs-input-group do"><label>Doğru</label><input type="number" name="iokbs_mat_d" class="si-iokbs-d" min="0" max="20" placeholder="0"></div>
                        <div class="si-iokbs-input-group ya"><label>Yanlış</label><input type="number" name="iokbs_mat_y" class="si-iokbs-y" min="0" max="20" placeholder="0"></div>
                        <div class="si-iokbs-input-group ne"><label>Net</label><input type="number" step="0.33" name="iokbs_mat_n" class="si-iokbs-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>

                <div class="si-iokbs-card" data-max="20">
                    <div class="si-iokbs-card-header">
                        <div class="si-iokbs-icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"></path></svg></div>
                        <div><h4 class="si-iokbs-subj-title">Fen Bilimleri</h4><span class="si-iokbs-subj-meta">20 Soru</span></div>
                    </div>
                    <div class="si-iokbs-inputs">
                        <div class="si-iokbs-input-group do"><label>Doğru</label><input type="number" name="iokbs_fen_d" class="si-iokbs-d" min="0" max="20" placeholder="0"></div>
                        <div class="si-iokbs-input-group ya"><label>Yanlış</label><input type="number" name="iokbs_fen_y" class="si-iokbs-y" min="0" max="20" placeholder="0"></div>
                        <div class="si-iokbs-input-group ne"><label>Net</label><input type="number" step="0.33" name="iokbs_fen_n" class="si-iokbs-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>

                <div class="si-iokbs-card" data-max="20">
                    <div class="si-iokbs-card-header">
                        <div class="si-iokbs-icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path><path d="M2 12h20"></path></svg></div>
                        <div><h4 class="si-iokbs-subj-title">Sosyal Bilgiler</h4><span class="si-iokbs-subj-meta">20 Soru</span></div>
                    </div>
                    <div class="si-iokbs-inputs">
                        <div class="si-iokbs-input-group do"><label>Doğru</label><input type="number" name="iokbs_sos_d" class="si-iokbs-d" min="0" max="20" placeholder="0"></div>
                        <div class="si-iokbs-input-group ya"><label>Yanlış</label><input type="number" name="iokbs_sos_y" class="si-iokbs-y" min="0" max="20" placeholder="0"></div>
                        <div class="si-iokbs-input-group ne"><label>Net</label><input type="number" step="0.33" name="iokbs_sos_n" class="si-iokbs-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>

            </div>

            <button type="submit" name="hesapla_iokbs" class="si-btn-iokbs">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
                BURSLULUK PUANIMI HESAPLA
            </button>
        </form>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const iokbsCards = document.querySelectorAll('.si-iokbs-card');
                iokbsCards.forEach(card => {
                    const dInput = card.querySelector('.si-iokbs-d'); const yInput = card.querySelector('.si-iokbs-y'); const nInput = card.querySelector('.si-iokbs-n');
                    const maxQ = parseInt(card.getAttribute('data-max'));
                    function calcIOKBSNet() {
                        let d = parseInt(dInput.value) || 0; let y = parseInt(yInput.value) || 0;
                        if (d + y > maxQ) { yInput.value = maxQ - d; y = maxQ - d; }
                        let net = d - (y / 3); // 3 YANLIŞ 1 DOĞRU
                        nInput.value = net > 0 ? net.toFixed(3) : 0;
                    }
                    dInput.addEventListener('input', calcIOKBSNet); yInput.addEventListener('input', calcIOKBSNet);
                });
            });
        </script>

        <?php
        if(isset($_POST['hesapla_iokbs'])) {
            $n = function($isim) { return si_post_net($isim, 3); };
            $turkce_n = $n('iokbs_turkce');
            $mat_n = $n('iokbs_mat');
            $fen_n = $n('iokbs_fen');
            $sos_n = $n('iokbs_sos');
            
            $sinif = isset($_POST['iokbs_sinif']) ? $_POST['iokbs_sinif'] : '5';
            $kontenjan = isset($_POST['iokbs_kontenjan']) ? $_POST['iokbs_kontenjan'] : 'diger';

            // ADMİN PANELİNDEN İOKBS KATSAYILARINI ÇEKİYORUZ
            $taban = floatval(get_option('si_iokbs_taban', '100.000'));
            $k_turkce = floatval(get_option('si_iokbs_turkce', '5.000'));
            $k_mat = floatval(get_option('si_iokbs_matematik', '5.000'));
            $k_fen = floatval(get_option('si_iokbs_fen', '5.000'));
            $k_sos = floatval(get_option('si_iokbs_sosyal', '5.000'));

            // MEB'in Sınıf ve Kontenjanlara Göre Açıkladığı Tahmini Taban Puanlar Tablosu
            $taban_puanlar_meb = [
                '5' => ['diger' => 462.356, 'ogretmen' => 464.845, 'okulyok' => 452.258, 'kanun' => 100],
                '6' => ['diger' => 450.000, 'ogretmen' => 456.149, 'okulyok' => 450.000, 'kanun' => 100],
                '7' => ['diger' => 453.000, 'ogretmen' => 460.967, 'okulyok' => 455.928, 'kanun' => 100],
                '8' => ['diger' => 462.000, 'ogretmen' => 475.372, 'okulyok' => 468.499, 'kanun' => 100],
                '9' => ['diger' => 422.000, 'ogretmen' => 437.516, 'okulyok' => 419.977, 'kanun' => 100],
                '10' => ['diger' => 432.000, 'ogretmen' => 429.482, 'okulyok' => 414.608, 'kanun' => 100],
                '11' => ['diger' => 425.000, 'ogretmen' => 438.891, 'okulyok' => 419.037, 'kanun' => 100],
            ];
            $hedef_puan = $taban_puanlar_meb[$sinif][$kontenjan];

            // Doğrusal Puan Hesaplaması
            $iokbs_puan = $taban + ($turkce_n * $k_turkce) + ($mat_n * $k_mat) + ($fen_n * $k_fen) + ($sos_n * $k_sos);
            
            if ($iokbs_puan > 500) $iokbs_puan = 500;
            if ($turkce_n <= 0 && $mat_n <= 0 && $fen_n <= 0 && $sos_n <= 0) $iokbs_puan = 0;

            echo '<div id="iokbs-sonuc" class="si-iokbs-result-box">';
            echo '<span class="si-iokbs-result-label">Tahmini Bursluluk (İOKBS) Puanınız</span>';
            echo '<span class="si-iokbs-result-score">' . number_format($iokbs_puan, 3, ',', '.') . '</span>';
            echo '</div>';

            echo '<div class="si-info-text-iokbs"><strong>Bilgilendirme:</strong> Bursluluk değerlendirmesi 500 tam puan üzerinden yapılmaktadır. İOKBS sınavında <strong>3 yanlış 1 doğruyu götürmektedir.</strong> Seçtiğiniz sınıf ve kontenjan türüne göre bursu kazanabilmeniz için gereken tahmini MEB taban puanı <strong>' . number_format($hedef_puan, 3, ',', '.') . '</strong> olarak öngörülmektedir.</div>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('iokbs_hesapla', 'soruindir_iokbs_hesapla_shortcode');

// ========================================================================
// 17. LİSE TAKDİR & TEŞEKKÜR HESAPLAMA MODÜLÜ (GÜNCEL YÖNETMELİK)
// ========================================================================
function soruindir_lise_belge_hesapla_shortcode() {
    ob_start(); 
    $guncel_yil = date("Y");
    ?>
    <style>
        .si-lise-wrapper { background: #eff6ff; border: 2px solid #bfdbfe; border-radius: 20px; padding: 40px 30px; max-width: 800px; margin: 0 auto 30px auto; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.04); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .si-lise-header { text-align: center; margin-bottom: 30px; }
        .si-lise-title { color: #1e3a8a; font-size: 28px; font-weight: 900; margin: 0 0 10px 0; letter-spacing: -0.5px; }
        .si-lise-title span { color: #3b82f6; }
        .si-lise-desc { color: #2563eb; font-size: 15px; margin: 0; }
        
        .si-lise-table-wrapper { background: #fff; border-radius: 16px; padding: 20px; border: 1px solid #dbeafe; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.05); margin-bottom: 25px;}
        .si-lise-row-header { display: grid; grid-template-columns: 2fr 1fr 1fr 40px; gap: 10px; margin-bottom: 15px; font-size: 13px; font-weight: 800; color: #3b82f6; text-transform: uppercase; padding-bottom: 10px; border-bottom: 2px solid #eff6ff;}
        .si-lise-rows-container { display: flex; flex-direction: column; gap: 10px; }
        .si-lise-row { display: grid; grid-template-columns: 2fr 1fr 1fr 40px; gap: 10px; align-items: center; }
        .si-lise-row input { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 15px; font-weight: 600; color: #0f172a; transition: 0.3s; box-sizing: border-box; }
        .si-lise-row input:focus { border-color: #3b82f6; outline: none; background: #eff6ff;}
        .si-lise-row-remove { background: #fee2e2; color: #ef4444; border: none; width: 40px; height: 40px; border-radius: 8px; font-weight: 900; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center;}
        .si-lise-row-remove:hover { background: #ef4444; color: #fff; }

        .si-btn-add-row { width: 100%; padding: 15px; margin-top: 15px; background: #eff6ff; color: #3b82f6; border: 2px dashed #bfdbfe; border-radius: 12px; font-size: 15px; font-weight: 800; cursor: pointer; transition: 0.3s; }
        .si-btn-lise { width: 100%; padding: 22px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; border: none; border-radius: 14px; font-size: 20px; font-weight: 900; letter-spacing: 0.5px; cursor: pointer; transition: 0.3s; box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3); display: flex; align-items: center; justify-content: center; gap: 10px; }
        
        .si-lise-result-box { margin-top: 30px; background: #fff; border: 2px solid #e2e8f0; border-radius: 16px; padding: 35px 20px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.05); animation: scaleUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .si-lise-result-score { font-size: 56px; font-weight: 900; line-height: 1; display: block; margin-bottom: 15px; color: #0f172a;}
        .si-lise-badge { display: inline-block; padding: 10px 20px; border-radius: 10px; font-size: 18px; font-weight: 800; text-transform: uppercase; }
        .badge-takdir { background: #ecfdf5; color: #047857; border: 2px solid #10b981; }
        .badge-tesekkur { background: #eff6ff; color: #1d4ed8; border: 2px solid #3b82f6; }
        .badge-yok { background: #fef2f2; color: #b91c1c; border: 2px solid #ef4444; }

        .si-lise-info-text { text-align:center; font-size:13px; color:#475569; margin-top:20px; background: #fff; padding: 15px; border-radius: 8px; border:1px solid #dbeafe;}
        @keyframes scaleUp { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>

    <div class="si-lise-wrapper">
        <div class="si-lise-header">
            <h3 class="si-lise-title"><span><?php echo $guncel_yil; ?></span> Lise Takdir & Teşekkür Hesaplama</h3>
            
        </div>
        
        <form method="post" action="#lise-sonuc">
            <div class="si-lise-table-wrapper">
                <div class="si-lise-row-header">
                    <div>Dersin Adı</div><div style="text-align:center;">Ders Saati</div><div style="text-align:center;">Dönem Notu</div><div></div>
                </div>
                <div id="si-lise-rows-container" class="si-lise-rows-container">
                    <?php for($i=1; $i<=8; $i++) { ?>
                    <div class="si-lise-row">
                        <input type="text" name="lise_ders_adi[]" placeholder="<?php echo $i; ?>. Ders">
                        <input type="number" name="lise_ders_saati[]" min="1" max="20" placeholder="Saat" required>
                        <input type="number" step="0.01" name="lise_ders_notu[]" min="0" max="100" placeholder="Not" required>
                        <button type="button" class="si-lise-row-remove" onclick="this.parentElement.remove()">X</button>
                    </div>
                    <?php } ?>
                </div>
                <button type="button" id="si-btn-add-row" class="si-btn-add-row">+ YENİ DERS EKLE</button>
            </div>
            <button type="submit" name="hesapla_lise" class="si-btn-lise">ORTALAMAMI HESAPLA</button>
        </form>

        <script>
            document.getElementById('si-btn-add-row').addEventListener('click', function() {
                const row = document.createElement('div');
                row.className = 'si-lise-row';
                row.innerHTML = `<input type="text" name="lise_ders_adi[]" placeholder="Ders"><input type="number" name="lise_ders_saati[]" min="1" max="20" placeholder="Saat" required><input type="number" step="0.01" name="lise_ders_notu[]" min="0" max="100" placeholder="Not" required><button type="button" class="si-lise-row-remove" onclick="this.parentElement.remove()">X</button>`;
                document.getElementById('si-lise-rows-container').appendChild(row);
            });
        </script>

        <?php
        if(isset($_POST['hesapla_lise'])) {
            $saatler = isset($_POST['lise_ders_saati']) ? $_POST['lise_ders_saati'] : [];
            $notlar = isset($_POST['lise_ders_notu']) ? $_POST['lise_ders_notu'] : [];
            $baraj_not = floatval(get_option('si_lise_gecme_notu', '50.00'));
            $baraj_tesekkur = floatval(get_option('si_lise_tesekkur', '70.00'));
            $baraj_takdir = floatval(get_option('si_lise_takdir', '85.00'));

            $toplam_agirlikli_not = 0; $toplam_saat = 0; $zayif_var = false;

            for($i = 0; $i < count($saatler); $i++) {
                $saat = floatval($saatler[$i]); $not = floatval($notlar[$i]);
                if ($saat > 0) {
                    $toplam_saat += $saat;
                    $toplam_agirlikli_not += ($saat * $not);
                    if ($not < $baraj_not) { $zayif_var = true; }
                }
            }

            $ortalama = ($toplam_saat > 0) ? $toplam_agirlikli_not / $toplam_saat : 0;
            $belge_yazi = ""; $belge_sinif = ""; $uyari = "";

            if ($zayif_var) {
                $belge_yazi = "BELGE ALAMAZSINIZ"; $belge_sinif = "badge-yok";
                $uyari = "Tüm derslerden başarılı olmanız gerekmektedir (50.00 barajı).";
            } else {
                if ($ortalama >= $baraj_takdir) { $belge_yazi = "TAKDİR BELGESİ"; $belge_sinif = "badge-takdir"; }
                elseif ($ortalama >= $baraj_tesekkur) { $belge_yazi = "TEŞEKKÜR BELGESİ"; $belge_sinif = "badge-tesekkur"; }
                else { $belge_yazi = "BELGE ALAMAZSINIZ"; $belge_sinif = "badge-yok"; $uyari = "Belge için en az 70.00 ortalama gereklidir."; }
            }

            echo '<div id="lise-sonuc" class="si-lise-result-box">';
            echo '<span class="si-lise-result-score">' . number_format($ortalama, 4, ',', '.') . '</span>';
            echo '<div class="si-lise-badge ' . $belge_sinif . '">' . $belge_yazi . '</div>';
            if ($uyari) echo '<div style="margin-top:15px; color:#ef4444; font-weight:700;">⚠️ ' . $uyari . '</div>';
            echo '</div>';
        }
        ?>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('lise_belge_hesapla', 'soruindir_lise_belge_hesapla_shortcode');

// ========================================================================
// MEB-AGS PUAN HESAPLAMA MODÜLÜ (AGS-P1, ÖABT ile AGS-P2, YDS ile AGS-P3)
// ÖSYM yöntemi: Net = D - Y/4 → standart puan (ort. 50, ss 10) → ağırlıklı
// standart puan (ASP) → 100'lük puan = 70 + 30·[2(ASP−X)−S] / [2(B−X)−S]
// ========================================================================
function si_ags_varsayilan() {
    // 2026-MEB-AGS Sınav Sonuçlarına İlişkin Sayısal Bilgiler (ÖSYM, 26.08.2026)
    return array(
        'testler' => array(
            'soz' => array('Sözel Yetenek', 15, 8.3, 3.2, 20),
            'say' => array('Sayısal Yetenek', 15, 3.4, 3.9, 20),
            'tar' => array('Tarih', 6, 2.7, 2.1, 15),
            'cog' => array('Türkiye Coğrafyası', 6, 2.2, 1.7, 10),
            'egt' => array('Eğitim Bilimleri ve Türk Millî Eğitim Sistemi', 30, 11.2, 6.3, 25),
            'mev' => array('Mevzuat', 8, 2.5, 2.4, 10),
        ),
        'oabt' => array(
            'turkce' => array('Türkçe', 25.5, 9.8), 'ilkmat' => array('İlköğretim Matematik', 19.2, 9.2), 'fen' => array('Fen Bilimleri', 16.5, 8.7),
            'sosyal' => array('Sosyal Bilgiler', 23.7, 8.9), 'tde' => array('Türk Dili ve Edebiyatı', 16.6, 10.8), 'tarih' => array('Tarih', 20.8, 10.1),
            'cografya' => array('Coğrafya', 16.7, 10.2), 'mat' => array('Matematik (Lise)', 13.6, 7.7), 'fizik' => array('Fizik', 19.5, 10.6),
            'kimya' => array('Kimya / Kimya Teknolojisi', 17.6, 9.7), 'biyoloji' => array('Biyoloji', 22.0, 12.2), 'rehberlik' => array('Rehberlik', 33.5, 9.2),
            'sinif' => array('Sınıf Öğretmenliği', 26.5, 7.1), 'okuloncesi' => array('Okul Öncesi', 32.1, 7.9), 'beden' => array('Beden Eğitimi', 15.8, 9.1),
            'ozel' => array('Özel Eğitim', 31.7, 7.4), 'dkab' => array('Din Kültürü ve Ahlak Bilgisi / İHL Meslek Dersleri', 22.1, 9.6),
        ),
        'r' => 0.50, // alt testler arası varsayılan ilişki (ÖSYM S değerini yayımlamaz)
    );
}

function si_ags_ayar() {
    $v = si_ags_varsayilan();
    foreach ($v['testler'] as $k => &$t) {
        $t[2] = floatval(get_option('si_ags_' . $k . '_ort', $t[2]));
        $t[3] = max(0.1, floatval(get_option('si_ags_' . $k . '_ss', $t[3])));
        $t[4] = floatval(get_option('si_ags_' . $k . '_w', $t[4]));
    }
    unset($t);
    foreach ($v['oabt'] as $k => &$o) {
        $o[1] = floatval(get_option('si_ags_oabt_' . $k . '_ort', $o[1]));
        $o[2] = max(0.1, floatval(get_option('si_ags_oabt_' . $k . '_ss', $o[2])));
    }
    unset($o);
    $v['r'] = min(0.95, max(0, floatval(get_option('si_ags_r', $v['r']))));
    return $v;
}

add_action('admin_init', function () {
    $v = si_ags_varsayilan();
    foreach (array_keys($v['testler']) as $k) { foreach (array('ort', 'ss', 'w') as $s) register_setting('si_hesaplama_ayarlar_grubu', 'si_ags_' . $k . '_' . $s); }
    foreach (array_keys($v['oabt']) as $k) { foreach (array('ort', 'ss') as $s) register_setting('si_hesaplama_ayarlar_grubu', 'si_ags_oabt_' . $k . '_' . $s); }
    register_setting('si_hesaplama_ayarlar_grubu', 'si_ags_r');
});

/** 100'lük ÖSYM dönüşümü */
function si_osym_100($asp, $S, $B, $X = 50.0) {
    $pay = 2 * ($asp - $X) - $S; $payda = 2 * ($B - $X) - $S;
    if ($payda <= 0) return 0;
    $p = 70 + 30 * $pay / $payda;
    return max(0, min(100, $p));
}

function soruindir_ags_hesapla_shortcode() {
    $A = si_ags_ayar();
    $T = $A['testler']; $OB = $A['oabt']; $r = $A['r'];
    $yil = date('Y');
    ob_start(); ?>
    <style>
        .si-ags-wrapper { background:#f6f9fc; border:2px solid #dbe5f1; border-radius:20px; padding:40px 30px; max-width:960px; margin:0 auto 30px; box-shadow:0 15px 35px rgba(0,0,0,.04); font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
        .si-ags-header { text-align:center; margin-bottom:30px; }
        .si-ags-title { color:#0f172a; font-size:28px; font-weight:900; margin:0 0 10px; letter-spacing:-.5px; }
        .si-ags-title span { color:#1E5AA8; }
        .si-ags-desc { color:#64748b; font-size:15px; margin:0; }
        .si-ags-section { font-size:19px; font-weight:800; color:#334155; margin:6px 0 14px; border-bottom:2px solid #dbe5f1; padding-bottom:8px; display:block; }
        .si-ags-section small { font-size:13px; font-weight:600; color:#64748b; margin-left:6px; }
        .si-ags-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(270px,1fr)); gap:16px; margin-bottom:30px; }
        .si-ags-card { background:#fff; border-radius:16px; padding:18px; border:1px solid #dbe5f1; display:flex; flex-direction:column; gap:14px; box-shadow:0 4px 10px rgba(0,0,0,.02); transition:.25s; }
        .si-ags-card:hover { border-color:#1E5AA8; box-shadow:0 10px 25px rgba(30,90,168,.12); }
        .si-ags-card h4 { margin:0; font-size:15.5px; font-weight:800; color:#1e293b; line-height:1.3; }
        .si-ags-card h4 small { display:block; font-size:12px; font-weight:700; color:#1E5AA8; margin-top:4px; }
        .si-ags-inputs { display:flex; gap:10px; }
        .si-ags-in { flex:1; text-align:center; }
        .si-ags-in label { display:block; font-size:11px; font-weight:800; text-transform:uppercase; color:#64748b; margin-bottom:6px; }
        .si-ags-in input { width:100%; padding:11px 4px; text-align:center; font-size:16px; font-weight:800; border-radius:10px; border:2px solid #e2e8f0; color:#0f172a; box-sizing:border-box; }
        .si-ags-in input:focus { border-color:#1E5AA8; outline:none; background:#eef4fc; }
        .si-ags-in.ne input { background:#f8fafc; border-color:transparent; color:#1E5AA8; }
        .si-ags-bar { background:#fff; border:1px solid #dbe5f1; border-radius:16px; padding:18px; display:grid; grid-template-columns:minmax(0,2fr) minmax(0,1fr) minmax(0,1fr) minmax(0,1fr); gap:12px; align-items:end; margin-bottom:30px; }
        .si-ags-bar label { display:block; font-size:12px; font-weight:800; text-transform:uppercase; color:#64748b; margin-bottom:6px; }
        .si-ags-bar select, .si-ags-bar input { width:100%; padding:11px 10px; font-size:15px; font-weight:700; border-radius:10px; border:2px solid #e2e8f0; color:#0f172a; box-sizing:border-box; background:#fff; }
        .si-ags-bar .ne input { background:#f8fafc; border-color:transparent; color:#1E5AA8; text-align:center; }
        .si-btn-ags { width:100%; padding:19px; background:linear-gradient(135deg,#1E5AA8,#174A8B); color:#fff; border:none; border-radius:14px; font-size:19px; font-weight:900; cursor:pointer; box-shadow:0 8px 25px rgba(30,90,168,.3); display:flex; align-items:center; justify-content:center; gap:10px; }
        .si-btn-ags:hover { filter:brightness(1.07); }
        .si-ags-results { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px,1fr)); gap:16px; margin-top:30px; }
        .si-ags-res { background:#fff; border:2px solid #1E5AA8; border-radius:18px; padding:26px 16px; text-align:center; box-shadow:0 10px 30px rgba(30,90,168,.12); }
        .si-ags-res.p2 { border-color:#0f7b4b; } .si-ags-res.p3 { border-color:#b5641b; }
        .si-ags-res span { display:block; font-size:14px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:#1E5AA8; margin-bottom:8px; }
        .si-ags-res.p2 span { color:#0f7b4b; } .si-ags-res.p3 span { color:#b5641b; }
        .si-ags-res strong { font-size:42px; font-weight:900; color:#0f172a; line-height:1; }
        .si-ags-res em { display:block; font-style:normal; font-size:12.5px; color:#64748b; margin-top:8px; }
        .si-ags-uyari { margin-top:20px; background:#fff7e6; border:1px solid #f5d9a0; color:#6b4300; padding:14px 16px; border-radius:12px; font-size:13.5px; line-height:1.55; }
        .si-ags-uyari strong { color:#5a3800; }
        @media (max-width:768px) { .si-ags-wrapper { padding:25px 15px; } .si-ags-grid { grid-template-columns:1fr; } .si-ags-bar { grid-template-columns:1fr 1fr; } .si-ags-bar > div:first-child { grid-column:1 / -1; } .si-ags-res strong { font-size:36px; } }
    </style>
    <div class="si-ags-wrapper">
        <div class="si-ags-header">
            <h3 class="si-ags-title"><span><?php echo esc_html($yil); ?></span> MEB-AGS Puan Hesaplama</h3>
            <p class="si-ags-desc">Akademi Giriş Sınavı (AGS-P1), ÖABT ile AGS-P2 ve YDS ile AGS-P3 puanını hesaplayın. 4 yanlış 1 doğruyu götürür.</p>
        </div>
        <form method="post" action="#ags-sonuc">
            <span class="si-ags-section">Akademi Giriş Sınavı <small>80 soru</small></span>
            <div class="si-ags-grid">
                <?php foreach ($T as $k => $t): $v = function ($s) use ($k) { return isset($_POST['ags_' . $k . '_' . $s]) ? esc_attr(intval($_POST['ags_' . $k . '_' . $s])) : ''; }; ?>
                <div class="si-ags-card" data-max="<?php echo intval($t[1]); ?>">
                    <h4><?php echo esc_html($t[0]); ?><small><?php echo intval($t[1]); ?> soru · ağırlık %<?php echo esc_html(rtrim(rtrim(number_format($t[4], 2, ',', ''), '0'), ',')); ?></small></h4>
                    <div class="si-ags-inputs">
                        <div class="si-ags-in"><label>Doğru</label><input type="number" name="ags_<?php echo $k; ?>_d" class="si-ags-d" min="0" max="<?php echo intval($t[1]); ?>" placeholder="0" value="<?php echo $v('d'); ?>"></div>
                        <div class="si-ags-in"><label>Yanlış</label><input type="number" name="ags_<?php echo $k; ?>_y" class="si-ags-y" min="0" max="<?php echo intval($t[1]); ?>" placeholder="0" value="<?php echo $v('y'); ?>"></div>
                        <div class="si-ags-in ne"><label>Net</label><input type="text" class="si-ags-n" value="0" readonly tabindex="-1"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <span class="si-ags-section">ÖABT ve YDS <small>isteğe bağlı</small></span>
            <?php $sec = isset($_POST['ags_oabt_alan']) ? sanitize_key($_POST['ags_oabt_alan']) : ''; ?>
            <div class="si-ags-bar si-ags-card" data-max="50">
                <div><label for="ags_oabt_alan">ÖABT alanı (50 soru)</label><select name="ags_oabt_alan" id="ags_oabt_alan"><option value="">Seçiniz (AGS-P2 için)</option><?php foreach ($OB as $k => $o) echo '<option value="' . esc_attr($k) . '"' . selected($sec, $k, false) . '>' . esc_html($o[0]) . '</option>'; ?></select></div>
                <div class="si-ags-in"><label>Doğru</label><input type="number" name="ags_oabt_d" class="si-ags-d" min="0" max="50" placeholder="0" value="<?php echo isset($_POST['ags_oabt_d']) ? esc_attr(intval($_POST['ags_oabt_d'])) : ''; ?>"></div>
                <div class="si-ags-in"><label>Yanlış</label><input type="number" name="ags_oabt_y" class="si-ags-y" min="0" max="50" placeholder="0" value="<?php echo isset($_POST['ags_oabt_y']) ? esc_attr(intval($_POST['ags_oabt_y'])) : ''; ?>"></div>
                <div class="si-ags-in ne"><label>Net</label><input type="text" class="si-ags-n" value="0" readonly tabindex="-1"></div>
            </div>
            <div class="si-ags-bar" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr);">
                <div><label for="ags_yds">YDS / e-YDS puanı (AGS-P3 için, 0–100)</label><input type="number" step="0.25" min="0" max="100" name="ags_yds" id="ags_yds" placeholder="Örn: 72,50" value="<?php echo isset($_POST['ags_yds']) ? esc_attr(floatval(str_replace(',', '.', $_POST['ags_yds']))) : ''; ?>"></div>
                <div style="font-size:13px; color:#64748b; align-self:center;">Yabancı dil öğretmenliği alanları AGS-P3 ile değerlendirilir.</div>
            </div>

            <button type="submit" name="hesapla_ags" class="si-btn-ags">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                AGS PUANIMI HESAPLA
            </button>
        </form>
        <script>
        (function () {
            function bagla() {
                document.querySelectorAll('.si-ags-card').forEach(function (card) {
                    var d = card.querySelector('.si-ags-d'), y = card.querySelector('.si-ags-y'), n = card.querySelector('.si-ags-n'); if (!d || !y || !n) return;
                    var max = parseInt(card.getAttribute('data-max'), 10) || 0;
                    function hesap() { var a = parseInt(d.value, 10) || 0, b = parseInt(y.value, 10) || 0; if (a + b > max) { b = Math.max(0, max - a); y.value = b; } var net = a - b / 4; n.value = net > 0 ? net.toFixed(2) : '0'; }
                    d.addEventListener('input', hesap); y.addEventListener('input', hesap); hesap();
                });
            }
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bagla); else bagla();
        })();
        </script>
        <?php
        if (isset($_POST['hesapla_ags'])) {
            // Netler sunucuda doğru/yanlış sayılarından hesaplanır
            $net = array(); $agsNetTop = 0;
            foreach ($T as $k => $t) { $net[$k] = min($t[1], si_post_net('ags_' . $k, 4)); $agsNetTop += $net[$k]; }
            $wTop = 0; foreach ($T as $t) $wTop += $t[4];
            if ($wTop <= 0) $wTop = 100;
            $asp1 = 0; $aspB1 = 0; $w2 = 0;
            foreach ($T as $k => $t) {
                $w = $t[4] / $wTop;
                $asp1 += $w * (50 + 10 * ($net[$k] - $t[2]) / $t[3]);
                $aspB1 += $w * (50 + 10 * ($t[1] - $t[2]) / $t[3]);
                $w2 += $w * $w;
            }
            // ASP'nin standart sapması: S = 10·√(Σw² + r·(1 − Σw²))
            $S1 = 10 * sqrt($w2 + $r * (1 - $w2));
            $p1 = $agsNetTop >= 1 ? si_osym_100($asp1, $S1, $aspB1) : null;

            $p2 = null; $alan = isset($_POST['ags_oabt_alan']) ? sanitize_key($_POST['ags_oabt_alan']) : '';
            if ($p1 !== null && $alan && isset($OB[$alan])) {
                $on = min(50, si_post_net('ags_oabt', 4));
                if ($on >= 1) {
                    $o = $OB[$alan];
                    $spO = 50 + 10 * ($on - $o[1]) / $o[2];
                    $spOB = 50 + 10 * (50 - $o[1]) / $o[2];
                    $asp2 = 0.5 * $asp1 + 0.5 * $spO;
                    $B2 = 0.5 * $aspB1 + 0.5 * $spOB;
                    $sd1 = $S1 / 10;
                    $S2 = 10 * sqrt(0.25 * $sd1 * $sd1 + 0.25 + 0.5 * $r * $sd1);
                    $p2 = si_osym_100($asp2, $S2, $B2);
                }
            }
            $p3 = null; $yds = isset($_POST['ags_yds']) && $_POST['ags_yds'] !== '' ? floatval(str_replace(',', '.', $_POST['ags_yds'])) : null;
            if ($p1 !== null && $yds !== null && $yds > 0) $p3 = 0.5 * $p1 + 0.5 * min(100, $yds);

            echo '<div id="ags-sonuc" class="si-ags-results">';
            if ($p1 === null) {
                echo '<div class="si-ags-res"><span>AGS-P1</span><strong>–</strong><em>Puan hesaplanması için AGS\'de en az 1 net gerekir.</em></div>';
            } else {
                echo '<div class="si-ags-res"><span>AGS-P1</span><strong>' . number_format($p1, 3, ',', '.') . '</strong><em>Toplam ' . number_format($agsNetTop, 2, ',', '.') . ' net</em></div>';
                if ($p2 !== null) echo '<div class="si-ags-res p2"><span>AGS-P2 (ÖABT)</span><strong>' . number_format($p2, 3, ',', '.') . '</strong><em>' . esc_html($OB[$alan][0]) . '</em></div>';
                elseif ($alan) echo '<div class="si-ags-res p2"><span>AGS-P2 (ÖABT)</span><strong>–</strong><em>ÖABT\'de en az 1 net gerekir.</em></div>';
                if ($p3 !== null) echo '<div class="si-ags-res p3"><span>AGS-P3 (YDS)</span><strong>' . number_format($p3, 3, ',', '.') . '</strong><em>AGS %50 + YDS %50</em></div>';
            }
            echo '</div>';
            echo '<div class="si-ags-uyari"><strong>Sonuçlar tahminidir.</strong> Hesap, ÖSYM\'nin 2026-MEB-AGS test ortalamaları ve standart sapmalarıyla ÖSYM\'nin 100\'lük puan formülüne göre yapılır. ÖSYM ağırlıklı puanların standart sapmasını ve en yüksek değerini yayımlamadığı için bu değerler tahmin edilmiştir; resmî puanınız birkaç puan farklı çıkabilir. AGS-P3 için YDS puanının %50 ağırlıkla eklendiği varsayılmıştır.</div>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('ags_hesapla', 'soruindir_ags_hesapla_shortcode');


// ========================================================================
// TÜM HESAPLAMALAR SAYFASI: /hesaplamalar/ (sayfa oluşturmaya gerek yok)
// ========================================================================
function si_hub_araclar() {
    $h = 'https://soruindir.net/';
    // anahtar, kısa ad, başlık, açıklama, kısa kod, yedek adres, grup, renk
    return array(
        array('lgs', 'LGS', 'LGS Puan Hesaplama', '8. sınıf merkezi sınav netlerinden LGS puanı ve yüzdelik dilim.', 'lgs_hesapla', $h . 'lgs-puan-hesaplama/', 'ogrenci', '#2F80ED'),
        array('yks', 'YKS', 'YKS Puan Hesaplama', 'TYT, AYT ve YDT netleri ile OBP’den yerleştirme puanları.', 'yks_hesapla', $h . 'yks-puan-hesaplama/', 'ogrenci', '#7C3AED'),
        array('iokbs', 'İOKBS', 'Bursluluk (İOKBS) Hesaplama', '5, 6, 7, 8 ve 11. sınıflar için bursluluk sınavı puanı.', 'iokbs_hesapla', $h . 'iokbs-puan-hesaplama/', 'ogrenci', '#0D9488'),
        array('belge', 'T/T', 'Lise Takdir – Teşekkür Hesaplama', 'Ders notları ve saatlerinden ağırlıklı ortalama ve belge durumu.', 'lise_belge_hesapla', $h . 'lise-takdir-tesekkur-hesaplama/', 'ogrenci', '#E8890B'),
        array('msu', 'MSÜ', 'MSÜ Puan Hesaplama', 'Millî Savunma Üniversitesi askerî öğrenci aday belirleme sınavı.', 'msu_hesapla', $h . 'msu-puan-hesaplama/', 'ogrenci', '#475569'),
        array('aof', 'AÖF', 'AÖF Puan Hesaplama', 'Açıköğretim vize ve final notlarından başarı notu.', '', $h . 'aof-puan-hesaplama/', 'ogrenci', '#DB2777'),
        array('ales', 'ALES', 'ALES Puan Hesaplama', 'Sayısal, sözel ve eşit ağırlık ALES puanları.', 'ales_hesapla', $h . 'ales-puan-hesaplama/', 'lisans', '#2563EB'),
        array('dgs', 'DGS', 'DGS Puan Hesaplama', 'Dikey geçiş sınavı netleri ve ön lisans başarı puanı.', 'dgs_hesapla', $h . 'dgs-puan-hesaplama/', 'lisans', '#059669'),
        array('yds', 'YDS', 'YDS Puan Hesaplama', 'Yabancı dil sınavı doğru sayısından puan ve seviye.', 'yds_hesapla', $h . 'yds-puan-hesaplama/', 'lisans', '#C2410C'),
        array('kpss', 'KPSS', 'KPSS Puan Hesaplama', 'Lisans, ön lisans ve ortaöğretim KPSS P puanları.', 'kpss_hesapla', $h . 'kpss-puan-hesaplama/', 'kamu', '#DC2626'),
        array('ekpss', 'EKPSS', 'EKPSS Puan Hesaplama', 'Engelli kamu personeli seçme sınavı puanı.', 'ekpss_hesapla', $h . 'ekpss-puan-hesaplama/', 'kamu', '#9333EA'),
        array('ekders', 'EK', 'Ek Ders Ücreti Hesaplama', 'Öğretmenler için güncel katsayıyla brüt ve net ek ders ücreti.', 'ek_ders_hesaplama', $h . 'ek-ders-hesaplama/', 'kamu', '#4F46E5'),
        array('ags', 'AGS', 'MEB AGS Puan Hesaplama', 'Akademi Giriş Sınavı (öğretmenlik) puanı.', 'ags_hesapla', $h . 'ags-puan-hesaplama/', 'kamu', '#1E4E79'),
        array('ekys', 'EKYS', 'MEB EKYS Puan Hesaplama', 'Eğitim kurumlarına yönetici görevlendirme sınavı.', '', $h . 'ekys-puan-hesapla/', 'kamu', '#0F766E'),
        array('hsy', 'HSY', 'Hâkim – Savcı Yardımcılığı', 'Adlî ve idarî yargı yazılı sınav puanı.', 'hsy_hesapla', $h . 'hakim-ve-savci-yardimciligi-sinavi-puan-hesaplama/', 'kamu', '#7F1D1D'),
        array('mbsts', 'MBSTS', 'DİB-MBSTS Puan Hesaplama', 'Diyanet mesleki bilgiler seviye tespit sınavı.', 'mbsts_hesapla', $h . 'dib-mbsts-puan-hesaplama/', 'kamu', '#15803D'),
        array('tus', 'TUS', 'TUS Puan Hesaplama', 'Tıpta uzmanlık sınavı temel ve klinik puanları.', 'tus_hesapla', $h . 'tus-puan-hesaplama/', 'kamu', '#BE123C'),
        array('dus', 'DUS', 'DUS Puan Hesaplama', 'Diş hekimliğinde uzmanlık sınavı puanı.', 'dus_hesapla', $h . 'dus-puan-hesaplama/', 'kamu', '#0369A1'),
        array('ehliyet', 'B', 'Ehliyet Sınavı Hesaplama', 'Elektronik sürücü sınavında doğru sayısından sonuç.', 'ehliyet_hesapla', $h . 'ehliyet-sinavi-puan-hesaplama/', 'diger', '#CA8A04'),
    );
}
function si_hub_adresler() {
    $c = get_transient('si_hub_adres');
    if (is_array($c)) return $c;
    $c = array();
    $ids = get_posts(array('post_type' => 'page', 'post_status' => 'publish', 's' => 'hesapla', 'posts_per_page' => 200, 'fields' => 'ids', 'no_found_rows' => true));
    foreach ($ids as $id) { $ic = get_post_field('post_content', $id); foreach (si_hub_araclar() as $a) if ($a[4] && !isset($c[$a[0]]) && has_shortcode($ic, $a[4])) $c[$a[0]] = get_permalink($id); }
    set_transient('si_hub_adres', $c, 6 * HOUR_IN_SECONDS);
    return $c;
}
add_action('save_post_page', function () { delete_transient('si_hub_adres'); });
function si_kucuk($s, $e = 'UTF-8') { return mb_strtolower(strtr($s, array('İ' => 'i', 'I' => 'ı')), 'UTF-8'); }
function si_hub_url() { $id = (int) get_option('si_hub_sayfa'); return $id && get_post_status($id) === 'publish' ? get_permalink($id) : home_url('/hesaplamalar/'); }
function si_hub_istek() { $yol = isset($_SERVER['REQUEST_URI']) ? trim(strtok(wp_unslash($_SERVER['REQUEST_URI']), '?'), '/') : ''; return $yol === 'hesaplamalar'; }
add_filter('pre_get_document_title', function ($t) { return si_hub_istek() ? 'Puan Hesaplama Araçları – LGS, YKS, KPSS ve Tüm Sınavlar | Soruindir' : $t; }, 50);
add_action('template_redirect', function () {
    if (!si_hub_istek() || !is_404()) return;
    global $wp_query; $wp_query->is_404 = false; status_header(200); nocache_headers();
    add_action('wp_head', function () { echo '<meta name="description" content="LGS, YKS, KPSS, ALES, DGS, AGS, İOKBS, takdir-teşekkür ve diğer tüm sınavlar için ücretsiz, güncel puan hesaplama araçları.">' . "\n" . '<link rel="canonical" href="' . esc_url(home_url('/hesaplamalar/')) . '">' . "\n"; }, 1);
    get_header(); echo si_hub_html(); get_footer(); exit;
});
add_shortcode('tum_hesaplamalar', 'si_hub_html');
function si_hub_html() {
    $ad = si_hub_adresler(); $gr = array('ogrenci' => 'Öğrenci sınavları', 'lisans' => 'Lisans ve lisansüstü', 'kamu' => 'Kamu ve meslek sınavları', 'diger' => 'Diğer');
    $kart = array(); foreach (si_hub_araclar() as $a) { $a[5] = isset($ad[$a[0]]) ? $ad[$a[0]] : $a[5]; $kart[] = $a; }
    $robot = array();
    if (class_exists('LGS_Tercih_Robotu') && method_exists('LGS_Tercih_Robotu', 'robot_url') && ($u = LGS_Tercih_Robotu::robot_url())) $robot[] = array('LGS Tercih Robotu', 'Puanına ve yüzdelik dilimine göre girebileceğin liseler.', $u, '#2F80ED');
    if (class_exists('YKS_Tercih_Robotu') && method_exists('YKS_Tercih_Robotu', 'robot_url') && ($u = YKS_Tercih_Robotu::robot_url())) $robot[] = array('YKS Tercih Robotu', 'Başarı sırana göre üniversite ve bölüm önerileri.', $u, '#7C3AED');
    $one = array('lgs', 'yks', 'kpss');
    $lst = array(); foreach ($kart as $i => $a) $lst[] = array('@type' => 'ListItem', 'position' => $i + 1, 'name' => $a[2], 'url' => $a[5]);
    ob_start(); ?>
<style>
.shb{--i:#13304F;--i2:#5B7189;--c:#E3EBF3;font-family:Figtree,system-ui,-apple-system,"Segoe UI",sans-serif;max-width:1180px;margin:0 auto;padding:22px 16px 56px;color:var(--i)}
.shb *{box-sizing:border-box}
.shb-hero{position:relative;overflow:hidden;border-radius:28px;padding:42px 24px 30px;text-align:center;background:linear-gradient(180deg,#EAF4FC,#F5F9FE);border:1px solid #DCEAF7}
.shb-hero:after{content:"";position:absolute;right:-60px;top:-60px;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle,rgba(47,128,237,.14),transparent 70%)}
.shb-hero:before{content:"";position:absolute;left:-50px;bottom:-80px;width:240px;height:240px;border-radius:50%;background:radial-gradient(circle,rgba(34,160,107,.12),transparent 70%)}
.shb-ust{display:inline-flex;gap:6px;align-items:center;padding:5px 12px;border-radius:99px;background:#fff;border:1px solid #DCEAF7;font-size:12.5px;font-weight:700;color:#2F80ED}
.shb h1{margin:14px 0 0 !important;font-size:clamp(27px,3.8vw,42px);line-height:1.12;letter-spacing:-.025em;font-weight:800;color:var(--i)}
.shb h1 span{color:#2F80ED}
.shb-hero p{margin:10px auto 0 !important;max-width:600px;color:#46607A;font-size:16.5px}
.shb-ara{position:relative;max-width:560px;margin:22px auto 0}
.shb-ara svg{position:absolute;left:18px;top:50%;transform:translateY(-50%);width:20px;height:20px;color:#8AA2BA}
.shb .shb-ara input{width:100%;border:1.5px solid #D3E2F1 !important;border-radius:99px !important;padding:15px 18px 15px 48px !important;font:inherit;font-size:16px;background:#fff;outline:0;box-shadow:0 16px 34px -26px rgba(19,48,79,.55)}
.shb .shb-ara input:focus{border-color:#2F80ED !important;box-shadow:0 0 0 4px rgba(47,128,237,.14)}
.shb-filtre{display:flex;flex-wrap:wrap;justify-content:center;gap:6px;margin-top:16px;position:relative;z-index:1}
.shb-filtre button{border:1px solid #DCEAF7;background:#fff;color:#46607A;font:600 13.5px/1 inherit;font-family:inherit;padding:9px 14px;border-radius:99px;cursor:pointer}
.shb-filtre button.on{background:#13304F;border-color:#13304F;color:#fff}
.shb-one{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:22px}
.shb .shb-on{position:relative;overflow:hidden;display:block;padding:22px;border-radius:22px;color:#fff;text-decoration:none;background:var(--r);box-shadow:0 22px 40px -30px var(--r);transition:transform .18s}
.shb .shb-on:hover{transform:translateY(-3px);color:#fff}
.shb-on:after{content:attr(data-k);position:absolute;right:-6px;bottom:-22px;font-size:84px;font-weight:900;opacity:.14;letter-spacing:-.04em}
.shb-on small{display:inline-block;padding:3px 9px;border-radius:99px;background:rgba(255,255,255,.2);font-size:11.5px;font-weight:700}
.shb-on b{display:block;margin-top:12px;font-size:21px;letter-spacing:-.01em}
.shb-on span{display:block;margin-top:6px;font-size:14px;opacity:.9;max-width:30ch;line-height:1.45}
.shb-on i{display:inline-flex;margin-top:14px;font-style:normal;font-weight:700;font-size:14px;background:#fff;color:var(--r);padding:8px 14px;border-radius:10px}
.shb-gr{margin-top:30px}
.shb-gr h2{margin:0 0 12px !important;font-size:19px;font-weight:800;display:flex;align-items:center;gap:10px;color:var(--i)}
.shb-gr h2:after{content:"";flex:1;height:1px;background:var(--c)}
.shb-iz{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.shb .shb-k{display:flex;gap:14px;align-items:center;padding:16px;border-radius:18px;background:#fff;border:1px solid var(--c);text-decoration:none;color:var(--i);transition:transform .16s,box-shadow .16s,border-color .16s}
.shb .shb-k:hover{transform:translateY(-2px);border-color:#C9DCF0;box-shadow:0 18px 34px -28px rgba(19,48,79,.6);color:var(--i)}
.shb-r{flex:0 0 52px;height:52px;border-radius:15px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;letter-spacing:-.01em;color:var(--r);background:color-mix(in srgb,var(--r) 12%,#fff)}
.shb-k .y{flex:1;min-width:0}.shb-k b{display:block;font-size:15.5px}.shb-k small{display:block;margin-top:3px;font-size:13px;line-height:1.45;color:var(--i2)}
.shb-k .ok{flex:0 0 auto;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#F1F5F9;color:#8AA2BA;transition:all .16s}
.shb-k:hover .ok{background:var(--r);color:#fff}
.shb-yok{display:none;text-align:center;color:var(--i2);margin-top:28px}
.shb-bilgi{margin-top:34px;padding:20px 22px;border-radius:20px;background:#F7FAFD;border:1px solid var(--c);color:#46607A;font-size:14.5px;line-height:1.6}
.shb-bilgi b{color:var(--i)}
@media(max-width:900px){.shb-one,.shb-iz{grid-template-columns:1fr 1fr}}
@media(max-width:600px){.shb-one,.shb-iz{grid-template-columns:1fr}.shb-hero{padding:30px 16px 24px;border-radius:22px}}
</style>
<div class="shb">
  <section class="shb-hero">
    <span class="shb-ust">🧮 <?php echo count($kart) + count($robot); ?> ücretsiz araç</span>
    <h1>Puan hesaplama <span>araçları</span></h1>
    <p>LGS’den KPSS’ye tüm sınavlar için güncel katsayılarla puanını hesapla, tercih robotlarıyla seçeneklerini gör.</p>
    <div class="shb-ara"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7.5"/><path d="m20.5 20.5-4.2-4.2"/></svg><input type="search" id="shb-q" placeholder="Sınav ara: LGS, KPSS, ALES…" aria-label="Hesaplama ara" autocomplete="off"></div>
    <div class="shb-filtre" role="tablist"><button type="button" class="on" data-g="">Tümü</button><?php foreach ($gr as $k => $v) echo '<button type="button" data-g="' . esc_attr($k) . '">' . esc_html($v) . '</button>'; if ($robot) echo '<button type="button" data-g="robot">Tercih robotları</button>'; ?></div>
  </section>
  <div class="shb-one" id="shb-one">
    <?php foreach ($kart as $a) if (in_array($a[0], $one, true)) echo '<a class="shb-on" style="--r:' . esc_attr($a[7]) . '" data-k="' . esc_attr($a[1]) . '" href="' . esc_url($a[5]) . '"><small>En çok kullanılan</small><b>' . esc_html($a[2]) . '</b><span>' . esc_html($a[3]) . '</span><i>Hemen hesapla →</i></a>'; ?>
  </div>
  <?php foreach ($gr as $g => $gad): ?>
  <section class="shb-gr" data-g="<?php echo esc_attr($g); ?>"><h2><?php echo esc_html($gad); ?></h2><div class="shb-iz">
    <?php foreach ($kart as $a) if ($a[6] === $g) echo '<a class="shb-k" style="--r:' . esc_attr($a[7]) . '" href="' . esc_url($a[5]) . '" data-s="' . esc_attr(si_kucuk($a[1] . ' ' . $a[2] . ' ' . $a[3], 'UTF-8')) . '"><span class="shb-r">' . esc_html($a[1]) . '</span><span class="y"><b>' . esc_html($a[2]) . '</b><small>' . esc_html($a[3]) . '</small></span><span class="ok" aria-hidden="true">→</span></a>'; ?>
  </div></section>
  <?php endforeach; ?>
  <?php if ($robot): ?>
  <section class="shb-gr" data-g="robot"><h2>Tercih robotları</h2><div class="shb-iz">
    <?php foreach ($robot as $r) echo '<a class="shb-k" style="--r:' . esc_attr($r[3]) . '" href="' . esc_url($r[2]) . '" data-s="' . esc_attr(si_kucuk($r[0] . ' ' . $r[1] . ' tercih robotu', 'UTF-8')) . '"><span class="shb-r">🎓</span><span class="y"><b>' . esc_html($r[0]) . '</b><small>' . esc_html($r[1]) . '</small></span><span class="ok" aria-hidden="true">→</span></a>'; ?>
  </div></section>
  <?php endif; ?>
  <p class="shb-yok" id="shb-yok">Aradığın sınav bulunamadı.</p>
  <div class="shb-bilgi"><b>Nasıl hesaplanır?</b> Doğru ve yanlış sayılarını gir; net, ilgili sınavın kuralına göre (çoğunlukla 4 yanlış 1 doğruyu götürür) hesaplanır ve ÖSYM / MEB’in açıkladığı güncel katsayılarla puana çevrilir. Sonuçlar tahminidir; resmî puan yalnızca sınavı yapan kurumca açıklanır.</div>
  <script type="application/ld+json"><?php echo wp_json_encode(array('@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => 'Puan hesaplama araçları', 'itemListElement' => $lst), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
</div>
<script>
(function () {
  var q = document.getElementById('shb-q'), g = '', one = document.getElementById('shb-one');
  function tr(s) { return s.toLocaleLowerCase('tr-TR'); }
  function suz() {
    var t = tr(q.value.trim()), say = 0;
    document.querySelectorAll('.shb-gr').forEach(function (s) {
      var n = 0; s.querySelectorAll('.shb-k').forEach(function (k) { var v = (!g || s.dataset.g === g) && (!t || k.dataset.s.indexOf(t) > -1); k.style.display = v ? '' : 'none'; if (v) n++; });
      s.style.display = n ? '' : 'none'; say += n;
    });
    one.style.display = t || g ? 'none' : ''; document.getElementById('shb-yok').style.display = say ? 'none' : 'block';
  }
  q.addEventListener('input', suz);
  document.querySelectorAll('.shb-filtre button').forEach(function (b) { b.onclick = function () { document.querySelectorAll('.shb-filtre button').forEach(function (x) { x.classList.toggle('on', x === b); }); g = b.dataset.g; suz(); }; });
})();
</script>
<?php return ob_get_clean();
}
