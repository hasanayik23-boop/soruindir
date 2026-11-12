<?php
/**
 * Soruindir.net özel üst bölüm (v2 - optik form tasarımı)
 * Ortak renkler, yazı tipleri ve kategori/arama sayfalarının paylaştığı bileşenler burada tanımlanır.
 */
$sx_logo = 'https://soruindir.net/wp-content/uploads/2021/02/soruindirlogo.png';
$sx_menu = array(
    array('Yazılı Soruları', 'https://soruindir.net/category/yazili-sorulari/'),
    array('Yıllık Planlar', 'https://soruindir.net/category/yillik-planlar/'),
    array('Zümreler', 'https://soruindir.net/category/zumre-toplanti-tutanaklari/'),
);
$sx_calc = array(
    array('MEB AGS Puan Hesaplama', 'https://soruindir.net/ags-puan-hesaplama/'),
    array('AÖF Puan Hesaplama', 'https://soruindir.net/aof-puan-hesaplama/'),
    array('Bursluluk (İOKBS) Hesaplama', 'https://soruindir.net/iokbs-puan-hesaplama/'),
    array('DGS Puan Hesaplama', 'https://soruindir.net/dgs-puan-hesaplama/'),
    array('DİB-MBSTS Puan Hesaplama', 'https://soruindir.net/dib-mbsts-puan-hesaplama/'),
    array('DUS Puan Hesaplama', 'https://soruindir.net/dus-puan-hesaplama/'),
    array('Ehliyet Sınavı Hesaplama', 'https://soruindir.net/ehliyet-sinavi-puan-hesaplama/'),
    array('EKPSS Puan Hesaplama', 'https://soruindir.net/ekpss-puan-hesaplama/'),
    array('MEB EKYS Puan Hesaplama', 'https://soruindir.net/ekys-puan-hesapla/'),
    array('Hakim Savcı Yard. Hesaplama', 'https://soruindir.net/hakim-ve-savci-yardimciligi-sinavi-puan-hesaplama/'),
    array('KPSS Puan Hesaplama', 'https://soruindir.net/kpss-puan-hesaplama/'),
    array('LGS Puan Hesaplama', 'https://soruindir.net/lgs-puan-hesaplama/'),
    array('Lise Takdir Teşekkür', 'https://soruindir.net/lise-takdir-tesekkur-hesaplama/'),
    array('YDS Puan Hesaplama', 'https://soruindir.net/yds-puan-hesaplama/'),
    array('YKS Puan Hesaplama', 'https://soruindir.net/yks-puan-hesaplama/'),
);
/* LGS Tercih Robotu: [lgs_tercih_robotu] kısa kodunun olduğu yayımlanmış sayfayı bulup listenin başına ekler */
$sx_robot = get_transient('sx_lgs_robot_url');
if ($sx_robot === false) {
    $sx_robot = '';
    $sx_rq = get_posts(array('post_type' => 'page', 'post_status' => 'publish', 's' => 'lgs_tercih_robotu', 'posts_per_page' => 5, 'fields' => 'ids', 'no_found_rows' => true));
    foreach ($sx_rq as $sx_id) { if (has_shortcode(get_post_field('post_content', $sx_id), 'lgs_tercih_robotu')) { $sx_robot = get_permalink($sx_id); break; } }
    set_transient('sx_lgs_robot_url', $sx_robot, $sx_robot ? 6 * HOUR_IN_SECONDS : 10 * MINUTE_IN_SECONDS);
}
if (class_exists('YKS_Tercih_Robotu') && ($sx_yks = YKS_Tercih_Robotu::robot_url())) array_unshift($sx_calc, array('YKS Tercih Robotu', $sx_yks));
if ($sx_robot) array_unshift($sx_calc, array('LGS Tercih Robotu', $sx_robot));
$sx_corp = array(
    array('Hakkımızda', 'https://soruindir.net/hakkimizda/'),
    array('İletişim', 'https://soruindir.net/iletisim/'),
    array('Gizlilik Politikası', 'https://soruindir.net/gizlilik-politikasi/'),
    array('Kullanım Koşulları', 'https://soruindir.net/kullanim-kosullari/'),
);
$sx_path = isset($_SERVER['REQUEST_URI']) ? trailingslashit(strtok(wp_unslash($_SERVER['REQUEST_URI']), '?')) : '/';
$sx_active = function ($url) use ($sx_path) { $u = wp_parse_url($url, PHP_URL_PATH); return ($u && $u !== '/' && strpos($sx_path, $u) === 0) ? ' is-active' : ''; };
$sx_chev = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
$sx_search_ico = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7.5"/><path d="m20.5 20.5-4.2-4.2"/></svg>';
// Üyelik (Online Soru Çöz eklentisinin sayfaları; yoksa varsayılan adresler)
$sx_sayfa = function ($opt, $yol) { $id = (int) get_option($opt); return ($id && get_post_status($id) === 'publish') ? get_permalink($id) : home_url($yol); };
$sx_yz = class_exists('OSC_Yazili') ? OSC_Yazili::url() : home_url('/yazili-hazirla/');
$sx_yz_on = class_exists('OSC_Yazili') && method_exists('OSC_Yazili', 'yayinda') && OSC_Yazili::yayinda(); // yayına alınana kadar gizli
$sx_om_on = class_exists('OSC_Masa') && OSC_Masa::yayinda(); $sx_om = $sx_om_on ? OSC_Masa::url() : ''; $sx_om_yeni = $sx_om_on && (!method_exists('OSC_Masa', 'yeni_mi') || OSC_Masa::yeni_mi()); // “YENİ” rozeti belli bir süre sonra kendiliğinden kalkar
$sx_uye = $sx_sayfa('osc_sp_uye_sayfa', '/uyelik/'); $sx_ogt = $sx_sayfa('osc_sp_panel_sayfa', '/ogretmen-paneli/'); $sx_ogr = $sx_sayfa('osc_sp_ogrenci_sayfa', '/ogrenci-paneli/');
$sx_giris = is_user_logged_in();
$sx_rol = $sx_giris ? (class_exists('OSC_Sinav') ? OSC_Sinav::rol() : 'ogretmen') : '';
$sx_u = $sx_giris ? wp_get_current_user() : null;
$sx_bas = '';
if ($sx_u) { $sx_p = preg_split('/\s+/u', trim($sx_u->display_name)); foreach (array_slice($sx_p, 0, 2) as $sx_x) $sx_bas .= function_exists('mb_substr') ? mb_strtoupper(mb_substr($sx_x, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($sx_x, 0, 1)); }
$sx_panel = $sx_rol === 'ogrenci' ? $sx_ogr : $sx_ogt;
$sx_burada = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');
$sx_user_ico = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>';
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php wp_head(); ?>

    <style>
        /* Eski temanın (SmartMag/GeneratePress) kendi üst menülerini gizle */
        header.main-header, header.smart-header, .smart-head, .site-header, .ts-header, .smart-head-main, .smart-head-mobile, .smart-head-sticky, .bunyad-header {
            display:none !important; height:0 !important; visibility:hidden !important; margin:0 !important; padding:0 !important; overflow:hidden !important;
        }

        /* ---------- Ortak renkler ---------- */
        :root { --sx-ink:#16233F; --sx-ink2:#4A5468; --sx-mute:#7A8396; --sx-brand:#1E5AA8; --sx-brand-d:#174A8B; --sx-brand-l:#E8F0FA; --sx-pink:#E0457B; --sx-pink-d:#B92D5F; --sx-paper:#FDF0F4; --sx-line:#E4E7EE; --sx-soft:#F5F6FA;
            --sx-font:'Figtree',system-ui,-apple-system,'Segoe UI',sans-serif; --sx-display:'Bricolage Grotesque','Figtree',sans-serif; }

        /* ---------- Üst bölüm ---------- */
        .sx-header { position:sticky; top:0; z-index:9990; background:rgba(255,255,255,.96); -webkit-backdrop-filter:saturate(1.4) blur(10px); backdrop-filter:saturate(1.4) blur(10px); border-bottom:1px solid var(--sx-line); font-family:var(--sx-font); }
        body.admin-bar .sx-header { top:32px; }
        @media screen and (max-width:782px) { body.admin-bar .sx-header { top:46px; } }
        .sx-header *, .sx-header *::before, .sx-header *::after { box-sizing:border-box; }
        .sx-header a { text-decoration:none !important; box-shadow:none; }
        .sx-header a:focus-visible, .sx-header button:focus-visible, .sx-header input:focus-visible, .sx-header summary:focus-visible { outline:3px solid var(--sx-pink); outline-offset:2px; border-radius:10px; }
        .sx-header-in { max-width:1200px; margin:0 auto; padding:0 20px; height:72px; display:flex; align-items:center; gap:24px; }
        .sx-logo { flex:0 0 auto; display:block; }
        .sx-logo img { display:block; height:auto; max-width:158px; }
        .sx-nav { display:flex; align-items:center; gap:2px; margin-right:auto; }
        .sx-header .sx-nav-link { position:relative; display:inline-flex; align-items:center; gap:5px; padding:9px 12px; border:0; background:transparent; border-radius:10px; font:inherit; font-size:15px; font-weight:600; color:var(--sx-ink); cursor:pointer; line-height:1.2; transition:background .15s, color .15s; }
        .sx-header .sx-nav-link svg { width:15px; height:15px; transition:transform .2s; }
        .sx-header .sx-nav-link:hover { background:var(--sx-soft); color:var(--sx-brand); }
        .sx-header .sx-nav-link.is-active { color:var(--sx-brand); }
        .sx-header .sx-nav-link.is-active::after { content:''; position:absolute; left:12px; right:12px; bottom:2px; height:2px; border-radius:2px; background:var(--sx-brand); }
        .sx-dd { position:relative; }
        .sx-dd-menu { position:absolute; top:calc(100% + 8px); left:-8px; width:520px; display:grid; grid-template-columns:1fr 1fr; gap:2px; padding:10px; background:#fff; border:1px solid var(--sx-line); border-radius:18px; box-shadow:0 24px 48px -24px rgba(22,35,63,.4); opacity:0; visibility:hidden; transform:translateY(6px); transition:opacity .18s, transform .18s, visibility .18s; }
        .sx-dd-menu::before { content:''; position:absolute; left:0; right:0; top:-10px; height:10px; }
        .sx-dd:hover .sx-dd-menu, .sx-dd:focus-within .sx-dd-menu, .sx-dd.is-open .sx-dd-menu { opacity:1; visibility:visible; transform:translateY(0); }
        .sx-dd:hover .sx-nav-link svg, .sx-dd.is-open .sx-nav-link svg { transform:rotate(180deg); }
        .sx-header .sx-dd-menu a { display:block; padding:9px 12px; border-radius:10px; font-size:14.5px; font-weight:600; color:var(--sx-ink2); transition:background .15s, color .15s; }
        .sx-header .sx-dd-menu a:hover { background:var(--sx-brand-l); color:var(--sx-brand); }
        .sx-actions { display:flex; align-items:center; gap:8px; flex:0 0 auto; }
        .sx-header .sx-icon-btn, .sx-header .sx-burger { width:42px; height:42px; display:grid; place-items:center; padding:0; border:1px solid var(--sx-line); background:#fff; color:var(--sx-ink); border-radius:12px; cursor:pointer; transition:background .15s, border-color .15s; }
        .sx-header .sx-icon-btn svg, .sx-header .sx-burger svg { width:20px; height:20px; }
        .sx-header .sx-icon-btn:hover, .sx-header .sx-burger:hover, .sx-header .sx-icon-btn[aria-expanded="true"] { background:var(--sx-soft); border-color:var(--sx-ink); }
        .sx-burger { display:none !important; }
        .sx-header .sx-cta { display:inline-flex; align-items:center; gap:8px; padding:11px 18px; border-radius:12px; background:var(--sx-brand); color:#fff; font-weight:700; font-size:15px; line-height:1.2; transition:background .15s; }
        .sx-header .sx-cta:hover { background:var(--sx-brand-d); color:#fff; }
        .sx-searchbar { border-top:1px solid var(--sx-line); background:#fff; }
        .sx-searchbar[hidden] { display:none; }
        .sx-searchbar form { max-width:760px; margin:0 auto; padding:14px 20px; }
        .sx-search-field { display:flex; align-items:center; gap:8px; background:#fff; border:2px solid var(--sx-brand); border-radius:14px; padding:5px 5px 5px 14px; box-shadow:3px 3px 0 var(--sx-brand); }
        .sx-search-field > svg { width:19px; height:19px; color:var(--sx-mute); flex:0 0 auto; }
        .sx-header .sx-search-field input, .sx-page .sx-search-field input { flex:1; min-width:0; border:0 !important; background:transparent !important; box-shadow:none !important; outline:none; font:inherit; font-size:16px !important; color:var(--sx-ink) !important; padding:9px 6px !important; margin:0 !important; -webkit-appearance:none; border-radius:0 !important; }
        .sx-header .sx-search-field button, .sx-page .sx-search-field button { border:0 !important; background:var(--sx-pink) !important; color:#fff !important; font:inherit; font-weight:700; font-size:15px; padding:10px 18px !important; border-radius:10px !important; cursor:pointer; margin:0 !important; line-height:1.2; }
        .sx-header .sx-search-field button:hover, .sx-page .sx-search-field button:hover { background:var(--sx-pink-d) !important; }

        /* Üyelik menüsü */
        .sx-uye { position:relative; }
        .sx-header .sx-uye-btn { display:inline-flex; align-items:center; gap:8px; height:42px; padding:0 14px 0 12px; border:1px solid var(--sx-line); background:#fff; color:var(--sx-ink); border-radius:12px; font:inherit; font-weight:700; font-size:15px; cursor:pointer; line-height:1; transition:background .15s, border-color .15s; }
        .sx-header .sx-uye-btn:hover, .sx-uye.is-open .sx-uye-btn { background:var(--sx-soft); border-color:var(--sx-ink); }
        .sx-header .sx-uye-btn svg { width:19px; height:19px; }
        .sx-header .sx-uye-btn .sx-chev { width:14px; height:14px; color:var(--sx-mute); transition:transform .2s; }
        .sx-uye.is-open .sx-uye-btn .sx-chev { transform:rotate(180deg); }
        .sx-av { width:30px; height:30px; border-radius:50%; display:grid; place-items:center; background:linear-gradient(135deg,var(--sx-brand),var(--sx-pink)); color:#fff; font-size:12.5px; font-weight:800; letter-spacing:.02em; flex:0 0 auto; }
        .sx-header .sx-uye-btn.has-av { padding-left:6px; }
        .sx-uye-ad { max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .sx-uye-menu { position:absolute; right:0; top:calc(100% + 10px); width:330px; background:#fff; border:1px solid var(--sx-line); border-radius:20px; box-shadow:0 28px 56px -24px rgba(22,35,63,.45); padding:14px; opacity:0; visibility:hidden; transform:translateY(6px); transition:opacity .18s, transform .18s, visibility .18s; z-index:10; }
        .sx-uye-menu::before { content:''; position:absolute; left:0; right:0; top:-12px; height:12px; }
        .sx-uye.is-open .sx-uye-menu { opacity:1; visibility:visible; transform:translateY(0); }
        .sx-uye-bas { padding:4px 4px 12px; }
        .sx-uye-bas b { display:block; font-family:var(--sx-display); font-size:18px; color:var(--sx-ink); }
        .sx-uye-bas small { display:block; color:var(--sx-mute); font-size:13px; margin-top:2px; }
        .sx-uye-iki { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
        .sx-header .sx-uye-k { display:flex; flex-direction:column; gap:3px; padding:14px 12px; border-radius:14px; border:1.5px solid var(--sx-line); color:var(--sx-ink); transition:border-color .15s, background .15s, transform .15s; }
        .sx-header .sx-uye-k:hover { transform:translateY(-1px); }
        .sx-uye-k i { font-style:normal; font-size:22px; line-height:1; margin-bottom:4px; }
        .sx-uye-k b { font-size:14.5px; }
        .sx-uye-k small { font-size:12px; color:var(--sx-mute); line-height:1.35; }
        .sx-header .sx-uye-k.ogt:hover { border-color:var(--sx-brand); background:var(--sx-brand-l); }
        .sx-header .sx-uye-k.ogr:hover { border-color:var(--sx-pink); background:var(--sx-paper); }
        .sx-header .sx-uye-kayit { display:flex; align-items:center; justify-content:center; gap:6px; margin-top:10px; padding:12px; border-radius:12px; background:var(--sx-pink); color:#fff; font-weight:700; font-size:15px; transition:background .15s; }
        .sx-header .sx-uye-kayit:hover { background:var(--sx-pink-d); color:#fff; }
        .sx-uye-kod { display:flex; align-items:center; gap:6px; margin-top:12px; padding:10px 6px 6px; border-top:1.5px dashed var(--sx-line); }
        .sx-uye-kod span { flex:1; font-size:12.5px; font-weight:600; color:var(--sx-ink2); line-height:1.3; }
        .sx-header .sx-uye-kod input { width:96px; border:0 !important; border-bottom:2px solid rgba(224,69,123,.45) !important; background:transparent !important; box-shadow:none !important; padding:6px 2px !important; margin:0 !important; font-family:var(--sx-display); font-weight:800; font-size:16px !important; letter-spacing:.14em; text-align:center; text-transform:uppercase; color:var(--sx-ink) !important; border-radius:0 !important; outline:none; }
        .sx-header .sx-uye-kod button { border:0 !important; background:var(--sx-ink) !important; color:#fff !important; font:inherit; font-weight:700; font-size:13.5px; padding:9px 12px !important; border-radius:10px !important; cursor:pointer; margin:0 !important; line-height:1.1; }
        .sx-header .sx-uye-link { display:flex; align-items:center; gap:10px; padding:11px 10px; border-radius:12px; font-weight:600; font-size:15px; color:var(--sx-ink); transition:background .15s; }
        .sx-header .sx-uye-link:hover { background:var(--sx-soft); color:var(--sx-brand); }
        .sx-uye-link i { font-style:normal; width:22px; text-align:center; }
        .sx-uye-link.cikis { color:var(--sx-pink-d) !important; border-top:1px solid var(--sx-line); border-radius:0 0 12px 12px; margin-top:6px; padding-top:13px; }
        .sx-uye-mob { display:none !important; }
.sx-yeni { display:inline-block; margin-left:6px; font-size:9.5px; font-weight:800; letter-spacing:.06em; color:#fff; background:linear-gradient(135deg,#5b5bf6,#c026d3); border-radius:99px; padding:2px 6px; vertical-align:2px; line-height:1.3; }
        .sx-nav .sx-yz-link { color:#4338ca; font-weight:700; }
        .sx-drawer .sx-yz-mob { color:#4338ca; }
                .sx-drawer-uye { display:none; }

        /* Mobil menü */
        .sx-drawer { display:none; }
        @media (max-width: 1060px) {
            .sx-nav, .sx-header .sx-cta, .sx-header .sx-icon-btn { display:none !important; }
            .sx-burger { display:grid !important; }
            .sx-uye-ad, .sx-header .sx-uye-btn .sx-chev, .sx-uye-yazi { display:none; }
            .sx-header .sx-uye-btn { width:42px; padding:0; justify-content:center; }
            .sx-header .sx-uye-btn.has-av { padding:0; }
            .sx-uye-menu { position:fixed; left:12px; right:12px; top:72px; width:auto; }
            body.admin-bar .sx-uye-menu { top:118px; }
            .sx-header-in { height:64px; justify-content:space-between; }
            .sx-logo img { max-width:136px; }
            .sx-drawer { display:block; max-height:calc(100vh - 64px); overflow-y:auto; border-top:1px solid var(--sx-line); background:#fff; padding:14px 20px 28px; box-shadow:0 24px 40px -24px rgba(22,35,63,.4); }
            .sx-drawer[hidden] { display:none; }
            .sx-drawer .sx-search-field { box-shadow:none; margin-bottom:12px; }
            .sx-header .sx-drawer-link, .sx-drawer summary { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:14px 4px; border-bottom:1px solid var(--sx-line); font-size:16px; font-weight:600; color:var(--sx-ink); cursor:pointer; list-style:none; }
            .sx-drawer summary::-webkit-details-marker { display:none; }
            .sx-drawer summary svg { width:18px; height:18px; color:var(--sx-mute); transition:transform .2s; }
            .sx-drawer details[open] summary svg { transform:rotate(180deg); }
            .sx-header .sx-drawer-link.is-active { color:var(--sx-brand); }
            .sx-drawer-sub { display:grid; grid-template-columns:1fr 1fr; gap:4px; padding:10px 0 14px; border-bottom:1px solid var(--sx-line); }
            .sx-header .sx-drawer-sub a { padding:9px 10px; border-radius:10px; background:var(--sx-soft); font-size:14px; font-weight:600; color:var(--sx-ink2); }
            .sx-header .sx-drawer-cta { display:flex; justify-content:center; margin-top:18px; padding:14px; border-radius:14px; background:var(--sx-brand); color:#fff; font-weight:700; font-size:16px; }
        }
        @media (max-width: 420px) { .sx-drawer-sub { grid-template-columns:1fr; } }

        /* ---------- Kategori ve arama sayfalarının ortak bileşenleri ---------- */
        .sx-page { width:100%; background:#fff; color:var(--sx-ink); font-family:var(--sx-font); font-size:16px; line-height:1.5; -webkit-font-smoothing:antialiased; padding:28px 0 64px; }
        .sx-page *, .sx-page *::before, .sx-page *::after { box-sizing:border-box; }
        .sx-page a { color:inherit; text-decoration:none !important; box-shadow:none; }
        .sx-page a:focus-visible, .sx-page button:focus-visible, .sx-page input:focus-visible { outline:3px solid var(--sx-pink); outline-offset:2px; border-radius:10px; }
        .sx-page h1, .sx-page h2, .sx-page h3 { font-family:var(--sx-display); color:var(--sx-ink); margin:0; letter-spacing:-0.01em; }
        .sx-page p { margin:0; }
        .sx-page ul, .sx-page ol { list-style:none; margin:0; padding:0; }
        .sx-wrap { max-width:1200px; margin:0 auto; padding:0 20px; }
        .sx-hero { position:relative; padding:30px 36px 30px 58px; border-radius:24px; overflow:hidden; background:var(--sx-paper); background-image:linear-gradient(rgba(224,69,123,.10) 1px, transparent 1px); background-size:100% 32px; }
        .sx-hero::before { content:''; position:absolute; left:30px; top:0; bottom:0; width:2px; background:rgba(224,69,123,.28); }
        .sx-hero > * { position:relative; }
        .sx-crumbs { display:flex; flex-wrap:wrap; align-items:center; gap:6px; font-size:14px; color:var(--sx-ink2); margin-bottom:10px; }
        .sx-crumbs a:hover { color:var(--sx-brand); }
        .sx-crumbs svg { width:13px; height:13px; color:var(--sx-mute); }
        .sx-hero h1 { font-size:clamp(28px, 3.4vw, 42px); line-height:1.08; font-weight:800; max-width:24ch; }
        .sx-hero-desc { margin-top:10px !important; font-size:16.5px; color:var(--sx-ink2); max-width:65ch; }
        .sx-hero-count { display:inline-flex; align-items:baseline; gap:6px; margin-top:14px; font-size:15px; color:var(--sx-ink2); }
        .sx-hero-count strong { font-family:var(--sx-display); font-size:22px; color:var(--sx-ink); }
        .sx-chips { display:flex; flex-wrap:wrap; gap:8px; margin-top:18px; }
        .sx-page .sx-chip { display:inline-flex; align-items:center; gap:7px; padding:7px 14px 7px 9px; background:#fff; border:1.5px solid var(--sx-line); border-radius:999px; font-weight:600; font-size:14.5px; color:var(--sx-ink); transition:border-color .15s, color .15s; }
        .sx-chip::before { content:''; width:12px; height:12px; border-radius:50%; border:2px solid var(--sx-brand); flex:0 0 auto; transition:background .15s; }
        .sx-page .sx-chip:hover { border-color:var(--sx-brand); color:var(--sx-brand); }
        .sx-chip:hover::before { background:var(--sx-brand); }
        .sx-chip small { color:var(--sx-mute); font-weight:600; font-size:13px; }

        .sx-page .sx-list { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px; margin-top:28px; }
        .sx-page .sx-row { display:flex; align-items:center; gap:14px; height:100%; padding:14px 16px; border-radius:16px; background:#fff; border:1.5px solid var(--sx-line); transition:border-color .15s, box-shadow .15s, transform .15s; }
        .sx-page .sx-row:hover { border-color:var(--sx-brand); box-shadow:3px 3px 0 var(--sx-brand); transform:translate(-1px, -1px); }
        .sx-row-ico { flex:0 0 42px; height:42px; border-radius:12px; display:grid; place-items:center; background:var(--sx-soft); color:var(--sx-ink); position:relative; overflow:hidden; }
        .sx-row.is-test .sx-row-ico { background:var(--sx-paper); color:var(--sx-pink-d); }
        .sx-row-ico svg { width:21px; height:21px; }
        .sx-row-ico img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
        .sx-row-body { flex:1; min-width:0; }
        .sx-page .sx-row-title { display:block; margin:0; font-family:var(--sx-font); font-weight:700; font-size:15.5px; line-height:1.38; letter-spacing:0; color:var(--sx-ink); }
        .sx-page .sx-row:hover .sx-row-title { color:var(--sx-brand); }
        .sx-row-kind { display:block; margin-top:3px; font-size:13px; font-weight:600; color:var(--sx-mute); }
        .sx-row.is-test .sx-row-kind { color:var(--sx-pink-d); }
        .sx-row-when { flex:0 0 auto; display:flex; flex-direction:column; align-items:flex-end; gap:4px; font-size:12.5px; color:var(--sx-mute); white-space:nowrap; text-align:right; }
        .sx-row-when b { font-size:11.5px; font-weight:700; color:#fff; background:var(--sx-pink); padding:2px 8px; border-radius:6px; }
        .sx-ad { grid-column:1 / -1; display:flex; justify-content:center; overflow:hidden; text-align:center; padding:14px; border-radius:16px; background:var(--sx-soft); }
        .sx-ad > div { width:100%; overflow:hidden; }
        .sx-ad-top { margin-top:24px; }
        .sx-empty { grid-column:1 / -1; padding:44px 24px; text-align:center; border:2px dashed var(--sx-line); border-radius:22px; }
        .sx-empty h2 { font-size:22px; font-weight:800; }
        .sx-empty p { margin-top:8px !important; color:var(--sx-ink2); }
        .sx-empty .sx-chips { justify-content:center; }

        /* Sayfalama */
        .sx-pagination { margin-top:36px; display:flex; justify-content:center; }
        .sx-pagination .navigation { margin:0 !important; width:auto !important; }
        .sx-pagination .screen-reader-text { position:absolute !important; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); }
        .sx-pagination .nav-links { display:flex !important; flex-wrap:wrap; justify-content:center; align-items:center; gap:6px; }
        .sx-pagination .page-numbers { display:inline-grid !important; place-items:center; min-width:44px; height:44px; padding:0 14px !important; margin:0 !important; border-radius:50% !important; border:2px solid var(--sx-line) !important; background:#fff !important; color:var(--sx-ink) !important; font-family:var(--sx-display); font-weight:700; font-size:15px; text-decoration:none !important; line-height:1; transition:border-color .15s, color .15s, background .15s; }
        .sx-pagination a.page-numbers:hover { border-color:var(--sx-brand) !important; color:var(--sx-brand) !important; }
        .sx-pagination .page-numbers.current { background:var(--sx-brand) !important; border-color:var(--sx-brand) !important; color:#fff !important; }
        .sx-pagination .page-numbers.dots { border-color:transparent !important; min-width:24px; padding:0 !important; }
        .sx-pagination .prev, .sx-pagination .next { border-radius:999px !important; font-family:var(--sx-font); font-size:14.5px; padding:0 18px !important; }

        @media (max-width: 760px) {
            .sx-page .sx-list { grid-template-columns:minmax(0,1fr); }
        }
        @media (max-width: 600px) {
            .sx-page { padding-top:16px; }
            .sx-wrap { padding:0 14px; }
            .sx-hero { padding:22px 18px 22px 32px; border-radius:20px; }
            .sx-hero::before { left:16px; }
            .sx-hero .sx-chips { flex-wrap:nowrap; overflow-x:auto; margin-right:-18px; padding:0 18px 4px 0; scrollbar-width:none; }
            .sx-hero .sx-chips::-webkit-scrollbar { display:none; }
            .sx-page .sx-chip { white-space:nowrap; flex:0 0 auto; }
            .sx-page .sx-row { padding:12px; gap:12px; }
            .sx-pagination .prev, .sx-pagination .next { padding:0 14px !important; }
        }
        @media (prefers-reduced-motion: reduce) { .sx-header *, .sx-page * { transition:none !important; } }
    </style>
</head>

<body <?php body_class(); ?>>

<?php function_exists('wp_body_open') ? wp_body_open() : do_action('wp_body_open'); ?>

<div class="main-wrap">

    <header class="sx-header" id="sx-header">
        <div class="sx-header-in">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="sx-logo" aria-label="Soruindir.net ana sayfa">
                <img src="<?php echo esc_url($sx_logo); ?>" alt="Soruindir" width="158" height="40">
            </a>

            <nav class="sx-nav" aria-label="Ana menü">
                <?php foreach ($sx_menu as $m): ?>
                <a class="sx-nav-link<?php echo $sx_active($m[1]); ?>" href="<?php echo esc_url($m[1]); ?>"><?php echo esc_html($m[0]); ?></a>
                <?php endforeach; ?>
                <div class="sx-dd" data-sx-dd>
                    <button type="button" class="sx-nav-link" aria-expanded="false" aria-haspopup="true">Hesaplamalar <?php echo $sx_chev; ?></button>
                    <div class="sx-dd-menu">
                        <?php foreach ($sx_calc as $m): ?><a href="<?php echo esc_url($m[1]); ?>"><?php echo esc_html($m[0]); ?></a><?php endforeach; ?>
                    </div>
                </div>
                <?php if ($sx_om_on): ?><a class="sx-nav-link sx-yz-link<?php echo $sx_active($sx_om); ?>" href="<?php echo esc_url($sx_om); ?>">Öğretmen Masası<?php if ($sx_om_yeni): ?> <span class="sx-yeni">YENİ</span><?php endif; ?></a><?php elseif ($sx_yz_on): ?><a class="sx-nav-link sx-yz-link" href="<?php echo esc_url($sx_yz); ?>">Yazılı Hazırla <span class="sx-yeni">YENİ</span></a><?php endif; ?>
                <a class="sx-nav-link<?php echo $sx_active('https://soruindir.net/icerik-gonder/'); ?>" href="https://soruindir.net/icerik-gonder/">İçerik gönder</a>
            </nav>

            <div class="sx-actions">
                <button type="button" class="sx-icon-btn" aria-label="Sitede ara" aria-expanded="false" aria-controls="sx-searchbar" data-sx-search><?php echo $sx_search_ico; ?></button>
                <a class="sx-cta" href="https://soruindir.net/test-coz/">Online test çöz</a>
                <div class="sx-uye" data-sx-uye>
                    <?php if ($sx_giris): ?>
                    <button type="button" class="sx-uye-btn has-av" aria-expanded="false" aria-haspopup="true" aria-label="Hesabım"><span class="sx-av"><?php echo esc_html($sx_bas ? $sx_bas : '?'); ?></span><span class="sx-uye-ad"><?php echo esc_html($sx_u->display_name); ?></span><?php echo str_replace('<svg ', '<svg class="sx-chev" ', $sx_chev); ?></button>
                    <div class="sx-uye-menu">
                        <div class="sx-uye-bas"><b><?php echo esc_html($sx_u->display_name); ?></b><small><?php echo $sx_rol === 'ogrenci' ? '🎒 Öğrenci hesabı' : '👩‍🏫 Öğretmen hesabı'; ?> · <?php echo esc_html($sx_u->user_email); ?></small></div>
                        <a class="sx-uye-link" href="<?php echo esc_url($sx_panel); ?>"><i>📊</i><?php echo $sx_rol === 'ogrenci' ? 'Öğrenci panelim' : 'Öğretmen panelim'; ?></a>
                        <?php if ($sx_rol === 'ogrenci'): ?>
                        <a class="sx-uye-link" href="<?php echo esc_url($sx_ogr); ?>"><i>📚</i>Ödevlerim ve sonuçlarım</a>
                        <?php else: ?>
                        <a class="sx-uye-link" href="<?php echo esc_url($sx_ogt . '#yeni/canli'); ?>"><i>🔴</i>Canlı sınav başlat</a>
                        <a class="sx-uye-link" href="<?php echo esc_url($sx_ogt . '#yeni/odev'); ?>"><i>🔗</i>Ödev linki oluştur</a>
                        <a class="sx-uye-link" href="<?php echo esc_url($sx_ogt . '#test/yeni'); ?>"><i>📝</i>Test hazırla</a>
                        <?php if ($sx_om_on): ?><a class="sx-uye-link" href="<?php echo esc_url($sx_om); ?>"><i>🧑‍🏫</i>Öğretmen Masası</a><?php endif; ?>
                        <?php if ($sx_yz_on): ?><a class="sx-uye-link" href="<?php echo esc_url($sx_ogt . '#yazili'); ?>"><i>📄</i>Yazılı hazırla <span class="sx-yeni">YENİ</span></a><?php endif; ?>
                        <?php endif; ?>
                        <a class="sx-uye-link cikis" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><i>↩</i>Çıkış yap</a>
                    </div>
                    <?php else: ?>
                    <button type="button" class="sx-uye-btn" aria-expanded="false" aria-haspopup="true" aria-label="Giriş yap"><?php echo $sx_user_ico; ?><span class="sx-uye-yazi">Giriş yap</span><?php echo str_replace('<svg ', '<svg class="sx-chev" ', $sx_chev); ?></button>
                    <div class="sx-uye-menu">
                        <div class="sx-uye-bas"><b>Hoş geldin 👋</b><small>Hesabınla giriş yap ya da ücretsiz üye ol.</small></div>
                        <div class="sx-uye-iki">
                            <a class="sx-uye-k ogt" href="<?php echo esc_url(add_query_arg(array('rol' => 'ogretmen', 'git' => $sx_ogt), $sx_uye)); ?>"><i>👩‍🏫</i><b>Öğretmen girişi</b><small>Canlı sınav, ödev, test</small></a>
                            <a class="sx-uye-k ogr" href="<?php echo esc_url(add_query_arg(array('rol' => 'ogrenci', 'git' => $sx_ogr), $sx_uye)); ?>"><i>🎒</i><b>Öğrenci girişi</b><small>Netler, ödevler, karne</small></a>
                        </div>
                        <a class="sx-uye-kayit" href="<?php echo esc_url(add_query_arg('kayit', 1, $sx_uye)); ?>">Ücretsiz üye ol</a>
                        <form class="sx-uye-kod" method="get" action="<?php echo esc_url(home_url('/')); ?>"><span>Öğretmeninden kod mu aldın?</span><input name="osc_k" maxlength="8" placeholder="KOD" autocomplete="off" required aria-label="Sınav kodu"><button type="submit">Katıl</button></form>
                    </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="sx-burger" aria-label="Menüyü aç" aria-expanded="false" aria-controls="sx-drawer" data-sx-burger>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        </div>

        <div class="sx-searchbar" id="sx-searchbar" hidden>
            <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                <div class="sx-search-field">
                    <?php echo $sx_search_ico; ?>
                    <label for="sx-s-top" style="position:absolute;left:-9999px;">Sitede ara</label>
                    <input type="search" id="sx-s-top" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Test, dosya ya da konu ara" required>
                    <button type="submit">Ara</button>
                </div>
            </form>
        </div>

        <div class="sx-drawer" id="sx-drawer" hidden>
            <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                <div class="sx-search-field">
                    <?php echo $sx_search_ico; ?>
                    <label for="sx-s-mob" style="position:absolute;left:-9999px;">Sitede ara</label>
                    <input type="search" id="sx-s-mob" name="s" placeholder="Test veya dosya ara" required>
                    <button type="submit">Ara</button>
                </div>
            </form>
            <a class="sx-drawer-link" href="<?php echo esc_url(home_url('/')); ?>">Ana sayfa</a>
            <?php if ($sx_om_on): ?><a class="sx-drawer-link sx-yz-mob" href="<?php echo esc_url($sx_om); ?>"><span>🧑‍🏫 Öğretmen Masası</span><?php if ($sx_om_yeni): ?><span class="sx-yeni">YENİ</span><?php endif; ?></a><?php endif; ?>
            <?php if ($sx_yz_on): ?><a class="sx-drawer-link sx-yz-mob" href="<?php echo esc_url($sx_yz); ?>"><span>📄 Yazılı Hazırla</span><span class="sx-yeni">YENİ</span></a><?php endif; ?>
            <a class="sx-drawer-link<?php echo $sx_active('https://soruindir.net/test-coz/'); ?>" href="https://soruindir.net/test-coz/">Online testler</a>
            <?php foreach ($sx_menu as $m): ?>
            <a class="sx-drawer-link<?php echo $sx_active($m[1]); ?>" href="<?php echo esc_url($m[1]); ?>"><?php echo esc_html($m[0]); ?></a>
            <?php endforeach; ?>
            <details>
                <summary>Hesaplama araçları <?php echo $sx_chev; ?></summary>
                <div class="sx-drawer-sub"><?php foreach ($sx_calc as $m): ?><a href="<?php echo esc_url($m[1]); ?>"><?php echo esc_html($m[0]); ?></a><?php endforeach; ?></div>
            </details>
            <details>
                <summary>Kurumsal <?php echo $sx_chev; ?></summary>
                <div class="sx-drawer-sub"><?php foreach ($sx_corp as $m): ?><a href="<?php echo esc_url($m[1]); ?>"><?php echo esc_html($m[0]); ?></a><?php endforeach; ?></div>
            </details>
            <a class="sx-drawer-link" href="https://soruindir.net/icerik-gonder/">İçerik gönder</a>
            <a class="sx-drawer-cta" href="https://soruindir.net/test-coz/">Online test çöz</a>
        </div>
    </header>

    <script>
    (function () {
        var h = document.getElementById('sx-header'); if (!h) return;
        function toggle(btn, panel, open) {
            if (open === undefined) open = btn.getAttribute('aria-expanded') !== 'true';
            btn.setAttribute('aria-expanded', open ? 'true' : 'false'); panel.hidden = !open;
            return open;
        }
        var sBtn = h.querySelector('[data-sx-search]'), sBar = document.getElementById('sx-searchbar');
        if (sBtn && sBar) sBtn.addEventListener('click', function () { if (toggle(sBtn, sBar)) { var i = sBar.querySelector('input'); if (i) i.focus(); } });
        var mBtn = h.querySelector('[data-sx-burger]'), drawer = document.getElementById('sx-drawer');
        if (mBtn && drawer) mBtn.addEventListener('click', function () { var o = toggle(mBtn, drawer); mBtn.setAttribute('aria-label', o ? 'Menüyü kapat' : 'Menüyü aç'); });
        h.querySelectorAll('[data-sx-uye]').forEach(function (dd) {
            var b = dd.querySelector('.sx-uye-btn');
            b.addEventListener('click', function (e) { e.stopPropagation(); var o = !dd.classList.contains('is-open'); dd.classList.toggle('is-open', o); b.setAttribute('aria-expanded', o ? 'true' : 'false'); if (o && drawer && !drawer.hidden && mBtn) toggle(mBtn, drawer, false); });
            document.addEventListener('click', function (e) { if (!dd.contains(e.target)) { dd.classList.remove('is-open'); b.setAttribute('aria-expanded', 'false'); } });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { dd.classList.remove('is-open'); b.setAttribute('aria-expanded', 'false'); } });
        });
        h.querySelectorAll('[data-sx-dd]').forEach(function (dd) {
            var b = dd.querySelector('button');
            b.addEventListener('click', function () { var o = !dd.classList.contains('is-open'); dd.classList.toggle('is-open', o); b.setAttribute('aria-expanded', o ? 'true' : 'false'); });
            document.addEventListener('click', function (e) { if (!dd.contains(e.target)) { dd.classList.remove('is-open'); b.setAttribute('aria-expanded', 'false'); } });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (sBtn && sBar && !sBar.hidden) { toggle(sBtn, sBar, false); sBtn.focus(); }
            h.querySelectorAll('[data-sx-dd].is-open').forEach(function (dd) { dd.classList.remove('is-open'); dd.querySelector('button').setAttribute('aria-expanded', 'false'); });
        });
    })();
    </script>
    <?php do_action('bunyad_pre_main_content'); ?>
