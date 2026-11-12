<?php
/**
 * Plugin Name: Öğretmen Masası
 * Description: Yazılı Hazırla (MEB senaryolarına uygun yazılı) ve Evrak Hazırla (tutanaklar, BEP, kulüp evrakları, OYS çizelgesi). Online Soru Çöz eklentisinin etkin olması gerekir.
 * Version: 1.0.0
 * Author: soruindir.net
 */
if (!defined('ABSPATH')) exit;

register_activation_hook(__FILE__, function () {
    // ilk kurulumda iki aracı da yayına al; sayfaları yeniden kontrol ettir
    if (get_option('osc_om_ilk') !== '1') { update_option('osc_yz_yayinda', '1'); update_option('osc_ev_yayinda', '1'); update_option('osc_om_ilk', '1'); }
    delete_option('osc_om_kur'); delete_option('osc_ev_kur'); delete_option('osc_yz_kur');
});

add_action('plugins_loaded', 'osc_om_yukle', 20);
function osc_om_yukle() {
    if (!class_exists('OSC_Sinav') || !class_exists('OSC_Havuz') || !class_exists('OSC_YZ_API')) {
        add_action('admin_notices', function () { echo '<div class="notice notice-error"><p><b>Öğretmen Masası</b> çalışmak için <b>Online Soru Çöz</b> eklentisinin etkin olmasını gerektirir.</p></div>'; });
        return;
    }
/*__GOVDE__*/
}
