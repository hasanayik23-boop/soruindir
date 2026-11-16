<?php
/**
 * Soruindir.net Özel Ana Sayfa Şablonu (v2 - optik form tasarımı)
 * Eklentiden bağımsız; doğrudan WordPress sorguları ile çalışır.
 * Ayarlar: sp_gp_home_categories, sp_gp_ad_home_top, sp_gp_ad_home_mid, sp_gp_ad_home_infeed
 * Menü: "Anasayfa Gruplari" (üst öğe = grup başlığı, alt öğeler = bağlantılar)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$selected_cats  = get_option('sp_gp_home_categories', array());
$ad_home_top    = get_option('sp_gp_ad_home_top', '');
$ad_home_mid    = get_option('sp_gp_ad_home_mid', '');
$ad_home_infeed = get_option('sp_gp_ad_home_infeed', '');
$now            = current_time('timestamp');
$test_page_url  = home_url('/test-coz/');
// Üyelik bağlantıları (Online Soru Çöz eklentisinin oluşturduğu sayfalar; yoksa varsayılan adresler)
$sph_sayfa = function ($opt, $yol) { $id = (int) get_option($opt); return ($id && get_post_status($id) === 'publish') ? get_permalink($id) : home_url($yol); };
$uye_url      = $sph_sayfa('osc_sp_uye_sayfa', '/uyelik/');
$ogt_panel    = $sph_sayfa('osc_sp_panel_sayfa', '/ogretmen-paneli/');
$ogr_panel    = $sph_sayfa('osc_sp_ogrenci_sayfa', '/ogrenci-paneli/');
$sph_giris    = is_user_logged_in();
$sph_rol      = $sph_giris ? (class_exists('OSC_Sinav') ? OSC_Sinav::rol() : 'ogretmen') : '';

$common_args = array('post_type' => 'post', 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC', 'ignore_sticky_posts' => true);

// Son eklenenler (tüm site)
$latest_args = $common_args; $latest_args['posts_per_page'] = 9;
$latest = (new WP_Query($latest_args))->posts;

// Sayılar
$total_posts = wp_count_posts('post');
$total_posts = isset($total_posts->publish) ? intval($total_posts->publish) : 0;

// Bu hafta en çok indirilenler / çözülen testler (eklentinin haftalık sayaçları)
$wk_down  = '_haftalik_indirme_' . date('W_Y');
$wk_solve = '_haftalik_cozulme_' . date('W_Y');
$pop_args = $common_args; $pop_args['posts_per_page'] = 6; $pop_args['meta_key'] = $wk_down; $pop_args['orderby'] = 'meta_value_num'; $pop_args['order'] = 'DESC';
$popular = array();
foreach ((new WP_Query($pop_args))->posts as $p) { $n = intval(get_post_meta($p->ID, $wk_down, true)); if ($n > 0) $popular[] = array($p, $n); }
$solve_args = $common_args; $solve_args['posts_per_page'] = 3; $solve_args['meta_key'] = $wk_solve; $solve_args['orderby'] = 'meta_value_num'; $solve_args['order'] = 'DESC';
$top_tests = array();
foreach ((new WP_Query($solve_args))->posts as $p) { $n = intval(get_post_meta($p->ID, $wk_solve, true)); if ($n > 0) $top_tests[] = array($p, $n); }

// Kategori renkleri
$sph_palette = array(
    array('#2563EB', '#DCE8FF'), array('#C2346B', '#FCE4EE'), array('#059669', '#D5F5E6'), array('#B45309', '#FCEFD3'),
    array('#7C3AED', '#ECE5FF'), array('#0D9488', '#D2F4EF'), array('#DC2626', '#FEE4E2'), array('#4F46E5', '#E2E4FF'),
    array('#0284C7', '#DDF1FD'), array('#4D7C0F', '#E9F7CF'), array('#EA580C', '#FFE9D6'), array('#475569', '#E3E8EF'),
);

// Akıllı ikon (başlığa göre); functions.php'de tanımlıysa o kullanılır
if (!function_exists('sp_get_kategori_ikonu')) {
    function sp_get_kategori_ikonu($title) {
        $t = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
        $o = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">';
        if (preg_match('/oyun|resfebe|eğlen|zeka/u', $t)) return $o . '<rect x="2" y="6" width="20" height="12" rx="3"/><path d="M6 12h4M8 10v4"/><circle cx="15.5" cy="13" r=".8" fill="currentColor"/><circle cx="18" cy="11" r=".8" fill="currentColor"/></svg>';
        if (preg_match('/plan|çalışma|dyk/u', $t)) return $o . '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>';
        if (preg_match('/hesap|matematik|puan/u', $t)) return $o . '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 11h.01M12 11h.01M16 11h.01M8 15h.01M12 15h.01M16 15h.01M8 19h.01M12 19h.01M16 19h.01"/></svg>';
        if (preg_match('/yazılı|sınav|test|deneme|soru/u', $t)) return $o . '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>';
        if (preg_match('/tutanak|zümre|veli|toplantı|şök|öğretmen/u', $t)) return $o . '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
        if (preg_match('/karne|görüş|bep|rehberlik/u', $t)) return $o . '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>';
        return $o . '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2zM22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>';
    }
}

$sph_ago   = function ($p) use ($now) { return human_time_diff(get_post_time('U', false, $p), $now) . ' önce'; };
$sph_isnew = function ($p) use ($now) { return ($now - get_post_time('U', false, $p)) < 3 * DAY_IN_SECONDS; };
$sph_istest = function ($p) { return (strpos((string) $p->post_content, '[osc_quiz') !== false); };
$ico_doc   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M12 18v-6M9 15l3 3 3-3"/></svg>';
$ico_test  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="6.5" cy="7" r="2.5"/><circle cx="6.5" cy="17" r="2.5"/><path d="M12 7h8M12 17h8"/><circle cx="6.5" cy="7" r="1" fill="currentColor"/></svg>';
$ico_go    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$ico_search = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7.5"/><path d="m20.5 20.5-4.2-4.2"/></svg>';

// Liste satırı (son eklenenler / kategori kutuları ortak)
$sph_row = function ($p, $col, $show_cat = false) use ($sph_ago, $sph_isnew, $sph_istest, $ico_doc, $ico_test, $ico_go) {
    $thumb = get_the_post_thumbnail_url($p->ID, 'thumbnail');
    $is_test = $sph_istest($p);
    $cat_html = '';
    if ($show_cat) { $pc = get_the_category($p->ID); if (!empty($pc)) $cat_html = '<span class="sph2-tag">' . esc_html($pc[0]->name) . '</span>'; }
    $h  = '<li><a class="sph2-row" href="' . esc_url(get_permalink($p)) . '" style="--a:' . $col[0] . ';--b:' . $col[1] . '">';
    $h .= $thumb ? '<span class="sph2-ico has-img"><img src="' . esc_url($thumb) . '" alt="" loading="lazy"></span>' : '<span class="sph2-ico" aria-hidden="true">' . ($is_test ? $ico_test : $ico_doc) . '</span>';
    $h .= '<span class="sph2-row-body"><span class="sph2-row-title">' . esc_html(get_the_title($p)) . '</span><span class="sph2-row-meta">';
    if ($sph_isnew($p)) $h .= '<span class="sph2-new">Yeni</span>';
    if ($is_test) $h .= '<span class="sph2-kind">Online test</span>';
    $h .= $cat_html . '<span>' . esc_html($sph_ago($p)) . '</span></span></span>';
    $h .= '<span class="sph2-go" aria-hidden="true">' . $ico_go . '</span></a></li>';
    return $h;
};
// Satıra kaç öğe: son satır boş kalmayacak (ya da en az boşluk kalacak) en geniş sütun sayısı
$sph_cols = function ($n, $opts) {
    if ($n <= $opts[0]) return max(1, $n);
    $best = $opts[0]; $best_gap = PHP_INT_MAX;
    foreach ($opts as $c) { $gap = ($c - $n % $c) % $c; if ($gap < $best_gap) { $best = $c; $best_gap = $gap; } }
    return $best;
};
$sph_post_color = function ($p) use ($sph_palette) {
    $pc = get_the_category($p->ID);
    return $sph_palette[!empty($pc) ? (abs(crc32((string) $pc[0]->term_id)) % count($sph_palette)) : 0];
};

$grades = array(
    'İlkokul'  => array(1, 2, 3, 4),
    'Ortaokul' => array(5, 6, 7, 8),
    'Lise'     => array(9, 10, 11, 12),
);
?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    .sph2 { --ink:#16233F; --ink2:#4A5468; --mute:#7A8396; --pink:#E0457B; --pink-d:#B92D5F; --paper:#FDF0F4; --pencil:#363B45; --line:#E4E7EE; --soft:#F5F6FA; --brand:#1E5AA8; --brand-d:#174A8B;
        width:100%; background:#fff; color:var(--ink); font-family:'Figtree',system-ui,-apple-system,'Segoe UI',sans-serif; font-size:16px; line-height:1.5; -webkit-font-smoothing:antialiased; padding:28px 0 64px; }
    .sph2 *, .sph2 *::before, .sph2 *::after { box-sizing:border-box; }
    .sph2 a { color:inherit; text-decoration:none !important; box-shadow:none; }
    .sph2 a:focus-visible, .sph2 button:focus-visible, .sph2 input:focus-visible { outline:3px solid var(--pink); outline-offset:2px; border-radius:10px; }
    .sph2 h1, .sph2 h2, .sph2 h3 { font-family:'Bricolage Grotesque','Figtree',sans-serif; color:var(--ink); margin:0; letter-spacing:-0.01em; }
    .sph2 ul, .sph2 ol { list-style:none; margin:0; padding:0; }
    .sph2 p { margin:0; }
    .sph2-wrap { max-width:1200px; margin:0 auto; padding:0 20px; }
    .sph2-sec { margin-top:52px; }
    .sph2-head { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:18px; }
    .sph2-head h2 { font-size:clamp(22px, 2.3vw, 28px); font-weight:800; }
    .sph2-head p { margin-top:4px; color:var(--mute); font-size:15px; }
    .sph2 .sph2-more { display:inline-flex; align-items:center; gap:6px; white-space:nowrap; font-weight:700; font-size:14px; padding:7px 12px; border-radius:999px; background:#fff; color:var(--ink); transition:background .15s, color .15s; }
    .sph2 .sph2-more svg { width:14px; height:14px; }
    .sph2 .sph2-more:hover { background:var(--brand); color:#fff; }
    .sph2-ad { display:flex; justify-content:center; margin:0 0 28px; overflow:hidden; text-align:center; }
    .sph2-ad--mid { margin:40px 0 0; padding:16px; border-radius:18px; background:var(--soft); }
    .sph2-ad > div { width:100%; overflow:hidden; }

    /* ---------- GİRİŞ ---------- */
    .sph2-hero { position:relative; display:grid; grid-template-columns:minmax(0,1fr) 380px; gap:40px; align-items:center; padding:38px 40px 36px 60px; border-radius:26px; overflow:hidden;
        background:var(--paper); background-image:linear-gradient(rgba(224,69,123,.10) 1px, transparent 1px); background-size:100% 32px; }
    .sph2-hero::before { content:''; position:absolute; left:30px; top:0; bottom:0; width:2px; background:rgba(224,69,123,.28); }
    .sph2-hero > * { min-width:0; position:relative; }
    .sph2-hero h1 { font-size:clamp(30px, 3.6vw, 46px); line-height:1.06; font-weight:800; max-width:19ch; }
    .sph2-lead { margin-top:12px !important; font-size:17px; color:var(--ink2); max-width:50ch; }
    .sph2-search { display:flex; align-items:center; margin-top:22px; max-width:600px; background:#fff !important; border:2px solid var(--brand); border-radius:16px; padding:6px 6px 6px 14px; box-shadow:4px 4px 0 var(--brand); }
    .sph2-search > svg { width:20px; height:20px; color:var(--mute); flex:0 0 auto; }
    .sph2 .sph2-search input { flex:1; min-width:0; border:0 !important; background:transparent !important; box-shadow:none !important; outline:none; font:inherit; font-size:16px !important; color:var(--ink) !important; padding:10px 10px !important; margin:0 !important; -webkit-appearance:none; border-radius:0 !important; }
    .sph2 .sph2-search input::placeholder { color:#9AA1B1 !important; opacity:1; }
    .sph2 .sph2-search button { border:0 !important; background:var(--pink) !important; color:#fff !important; font:inherit; font-weight:700; font-size:15px; padding:11px 20px !important; border-radius:11px !important; cursor:pointer; margin:0 !important; line-height:1.2; }
    .sph2 .sph2-search button:hover { background:var(--pink-d) !important; }
    .sph2-facts { display:flex; flex-wrap:wrap; gap:8px 22px; margin-top:20px; color:var(--ink2); font-size:14.5px; }
    .sph2-facts strong { font-family:'Bricolage Grotesque',sans-serif; color:var(--ink); font-size:20px; margin-right:5px; }

    /* Sınıf seçimi: optik form */
    .sph2-sheet { background:#fff; border:1.5px solid rgba(224,69,123,.45); border-radius:20px; padding:18px 20px 18px; transform:rotate(1.2deg); box-shadow:0 18px 36px -24px rgba(22,35,63,.55); }
    .sph2-sheet h2 { font-size:19px; font-weight:800; }
    .sph2-sheet > p { color:var(--mute); font-size:13.5px; margin:2px 0 10px !important; }
    .sph2-grade-row { display:grid; grid-template-columns:76px repeat(4, 1fr); align-items:center; gap:6px; padding:8px 0; border-top:1.5px dashed rgba(224,69,123,.35); }
    .sph2-grade-row > span { font-weight:700; font-size:13.5px; color:var(--pink-d); }
    .sph2 .sph2-bub { justify-self:center; width:44px; height:44px; border-radius:50%; border:2px solid rgba(224,69,123,.7); display:grid; place-items:center; font-family:'Bricolage Grotesque',sans-serif; font-weight:700; font-size:16px; color:var(--pink-d); position:relative; transition:color .15s; }
    .sph2-bub::after { content:''; position:absolute; inset:3px; border-radius:50%; background:var(--pencil); transform:scale(0); transition:transform .22s cubic-bezier(.2,.9,.3,1.3); z-index:0; }
    .sph2-bub b { position:relative; z-index:1; font-weight:700; }
    .sph2 .sph2-bub:hover, .sph2 .sph2-bub:focus-visible { color:#fff; }
    .sph2-bub:hover::after, .sph2-bub:focus-visible::after { transform:scale(1); }
    .sph2 .sph2-test-cta { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:8px; padding:13px 16px; border-radius:14px; background:var(--brand); color:#fff; transition:background .15s; }
    .sph2-test-cta strong { display:block; font-size:16px; }
    .sph2-test-cta small { display:block; font-size:13px; color:rgba(255,255,255,.8); }
    .sph2-test-cta svg { width:18px; height:18px; flex:0 0 auto; }
    .sph2 .sph2-test-cta:hover { background:var(--brand-d); }

    /* ---------- Liste satırları ---------- */
    .sph2-panel { border:1.5px solid var(--line); border-radius:22px; padding:8px; background:#fff; }
    .sph2-latest { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); column-gap:8px; }
    .sph2 .sph2-row { display:flex; align-items:center; gap:14px; padding:11px 12px; border-radius:15px; transition:background .15s; }
    .sph2 .sph2-row:hover { background:var(--soft); }
    .sph2-ico { flex:0 0 46px; height:46px; border-radius:13px; display:grid; place-items:center; color:var(--a); background:var(--b); overflow:hidden; position:relative; }
    .sph2-ico svg { width:22px; height:22px; }
    .sph2-ico img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
    .sph2-row-body { flex:1; min-width:0; }
    .sph2-row-title { display:block; font-weight:700; font-size:15px; line-height:1.4; color:var(--ink); }
    .sph2 .sph2-row:hover .sph2-row-title { color:var(--a); }
    .sph2-row-meta { display:flex; flex-wrap:wrap; align-items:center; gap:4px 10px; margin-top:4px; font-size:13px; color:var(--mute); }
    .sph2-new { color:#fff; background:var(--pink); font-weight:700; font-size:11.5px; padding:2px 7px; border-radius:6px; }
    .sph2-kind { color:var(--ink); font-weight:700; }
    .sph2-tag { color:var(--a); font-weight:600; }
    .sph2-go { flex:0 0 30px; height:30px; border-radius:50%; display:grid; place-items:center; color:var(--mute); transition:background .15s, color .15s, transform .15s; }
    .sph2-go svg { width:15px; height:15px; }
    .sph2 .sph2-row:hover .sph2-go { background:var(--a); color:#fff; transform:translateX(2px); }


    /* Son eklenenler: en yenisi büyük vitrin kartı, diğerleri iki sütunlu sade liste */
    .sph2-tabs { display:inline-flex; gap:4px; padding:4px; border-radius:12px; background:var(--soft); flex:0 0 auto; }
    .sph2 .sph2-tabs button { border:0 !important; background:transparent !important; color:var(--ink2) !important; font:inherit; font-weight:700; font-size:14px; padding:8px 14px !important; border-radius:9px !important; cursor:pointer; margin:0 !important; line-height:1.2; }
    .sph2 .sph2-tabs button[aria-selected="true"] { background:#fff !important; color:var(--ink) !important; box-shadow:0 1px 3px rgba(22,35,63,.12); }
    .sph2-feed { display:grid; grid-template-columns:minmax(0,1.05fr) minmax(0,1fr) minmax(0,1fr); grid-auto-rows:auto; gap:10px; }
    .sph2-feed li[hidden] { display:none; }
    .sph2-feed li.is-feat { grid-column:1; grid-row:1 / span 4; }
    .sph2 .sph2-feed-row { position:relative; display:flex; align-items:center; gap:13px; height:100%; padding:13px 14px; border-radius:16px; background:#fff; border:1.5px solid var(--line); transition:border-color .15s, box-shadow .15s, transform .15s; }
    .sph2 .sph2-feed-row:hover { border-color:var(--brand); box-shadow:3px 3px 0 var(--brand); transform:translate(-1px, -1px); }
    .sph2-feed-ico { flex:0 0 40px; height:40px; border-radius:11px; display:grid; place-items:center; background:var(--soft); color:var(--ink); position:relative; overflow:hidden; }
    .sph2-feed-row.is-test .sph2-feed-ico { background:var(--paper); color:var(--pink-d); }
    .sph2-feed-ico svg { width:20px; height:20px; }
    .sph2-feed-ico img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
    .sph2-feed-body { flex:1; min-width:0; }
    .sph2-feed-title { display:block; font-weight:700; font-size:15px; line-height:1.38; color:var(--ink); }
    .sph2-feed-cta { display:none; }
    .sph2-feed-when { flex:0 0 auto; display:flex; flex-direction:column; align-items:flex-end; gap:4px; font-size:12.5px; color:var(--mute); white-space:nowrap; text-align:right; }
    .sph2-feed-when b { font-size:11.5px; font-weight:700; color:#fff; background:var(--pink); padding:2px 8px; border-radius:6px; }
    /* vitrin kartı */
    .sph2 .is-feat .sph2-feed-row { flex-direction:column; align-items:flex-start; justify-content:flex-end; gap:16px; padding:26px 26px 24px; border:0; border-radius:24px; background:var(--brand); color:#fff; overflow:hidden; min-height:300px; }
    .sph2 .is-feat .sph2-feed-row::before { content:''; position:absolute; right:-30px; top:-30px; width:240px; height:200px; opacity:.18; pointer-events:none;
        background-image:radial-gradient(circle, transparent 8px, #fff 8.5px, #fff 10px, transparent 10.5px); background-size:32px 32px;
        -webkit-mask-image:linear-gradient(225deg, #000 10%, transparent 75%); mask-image:linear-gradient(225deg, #000 10%, transparent 75%); }
    .sph2 .is-feat .sph2-feed-row:hover { background:var(--brand-d); box-shadow:4px 4px 0 var(--pink); transform:translate(-2px, -2px); }
    .sph2 .is-feat .sph2-feed-ico { flex:0 0 auto; width:56px; height:56px; border-radius:16px; background:rgba(255,255,255,.12); color:#fff; order:-2; }
    .sph2 .is-feat .sph2-feed-ico svg { width:27px; height:27px; }
    .sph2 .is-feat .sph2-feed-when { order:-1; position:absolute; top:26px; right:26px; flex-direction:row-reverse; align-items:center; gap:8px; color:rgba(255,255,255,.82); font-size:13.5px; }
    .sph2 .is-feat .sph2-feed-body { flex:0 0 auto; width:100%; }
    .sph2 .is-feat .sph2-feed-title { color:#fff; font-family:'Bricolage Grotesque','Figtree',sans-serif; font-size:clamp(21px, 2vw, 26px); font-weight:800; line-height:1.18; letter-spacing:-0.01em; }
    .sph2 .is-feat .sph2-feed-cta { display:inline-flex; align-items:center; gap:8px; margin-top:18px; padding:10px 16px; border-radius:11px; background:#fff; color:var(--ink); font-weight:700; font-size:15px; }
    .sph2 .is-feat .sph2-feed-cta svg { width:15px; height:15px; }
    .sph2 .is-feat .sph2-feed-row:hover .sph2-feed-cta { background:var(--paper); }

    /* ---------- Popüler ---------- */
    .sph2-pop { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:14px; }
    .sph2 .sph2-pop a { display:flex; gap:14px; align-items:flex-start; height:100%; padding:16px 18px; border-radius:18px; background:var(--soft); transition:background .15s; }
    .sph2 .sph2-pop a:hover { background:var(--paper); }
    .sph2-rank { font-family:'Bricolage Grotesque',sans-serif; font-weight:800; font-size:34px; line-height:.9; color:var(--pink); min-width:26px; }
    .sph2-pop h3 { font-family:'Figtree',sans-serif; font-size:15.5px; font-weight:700; line-height:1.4; letter-spacing:0; }
    .sph2 .sph2-pop a:hover h3 { color:var(--pink-d); }
    .sph2-pop small { display:block; margin-top:5px; font-size:13px; color:var(--mute); }
    .sph2-pop small b { color:var(--ink); }

    /* ---------- Online test bandı ---------- */
    .sph2-band { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.1fr); gap:32px; align-items:center; padding:34px 38px; border-radius:26px; background:var(--brand); color:#fff; position:relative; overflow:hidden; }
    .sph2-band::after { content:''; position:absolute; right:-40px; top:-40px; width:260px; height:200px; opacity:.16; pointer-events:none;
        background-image:radial-gradient(circle, transparent 8px, #fff 8.5px, #fff 10px, transparent 10.5px); background-size:32px 32px;
        -webkit-mask-image:linear-gradient(225deg, #000 10%, transparent 75%); mask-image:linear-gradient(225deg, #000 10%, transparent 75%); }
    .sph2-band > * { position:relative; z-index:1; min-width:0; }
    .sph2-band h2 { color:#fff; font-size:clamp(24px, 2.6vw, 32px); font-weight:800; line-height:1.12; }
    .sph2-band p { margin-top:10px !important; color:rgba(255,255,255,.85); font-size:16px; max-width:44ch; }
    .sph2 .sph2-band-cta { display:inline-flex; align-items:center; gap:10px; margin-top:20px; background:#fff; color:var(--ink); font-weight:700; padding:12px 20px; border-radius:12px; transition:background .15s; }
    .sph2-band-cta svg { width:16px; height:16px; }
    .sph2 .sph2-band-cta:hover { background:var(--paper); }
    .sph2-band ol { display:grid; gap:8px; }
    .sph2 .sph2-band ol a { display:flex; align-items:center; gap:14px; padding:12px 14px; border-radius:14px; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.12); transition:background .15s; }
    .sph2 .sph2-band ol a:hover { background:rgba(255,255,255,.14); }
    .sph2-band ol .sph2-bubn { flex:0 0 34px; height:34px; border-radius:50%; border:2px solid rgba(255,255,255,.5); display:grid; place-items:center; font-family:'Bricolage Grotesque',sans-serif; font-weight:700; font-size:14px; }
    .sph2-band ol li:first-child .sph2-bubn { background:#fff; color:var(--brand); border-color:#fff; }
    .sph2-band ol span.t { flex:1; min-width:0; font-weight:600; font-size:15px; line-height:1.38; }
    .sph2-band ol small { display:block; color:rgba(255,255,255,.75); font-size:13px; font-weight:500; margin-top:2px; }
    .sph2-band-steps { display:grid; gap:10px; }
    .sph2-band-steps div { display:flex; gap:12px; align-items:center; padding:12px 14px; border-radius:14px; background:rgba(255,255,255,.1); color:#fff; font-size:15px; }
    .sph2-band-steps i { flex:0 0 30px; height:30px; border-radius:50%; border:2px solid rgba(255,255,255,.5); display:grid; place-items:center; font-style:normal; font-weight:700; font-family:'Bricolage Grotesque',sans-serif; font-size:13px; }

    /* ---------- Kategoriler ---------- */
    .sph2-grid { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:22px; align-items:stretch; }
    .sph2-box { border:1.5px solid var(--line); border-radius:24px; background:#fff; overflow:hidden; }
    .sph2-box--wide { grid-column:1 / -1; }
    .sph2-box-head { position:relative; display:flex; align-items:center; gap:14px; padding:16px 18px; background:var(--b); overflow:hidden; }
    .sph2-box-head::after { content:''; position:absolute; right:-10px; top:-14px; width:160px; height:100px; opacity:.55; pointer-events:none;
        background-image:radial-gradient(circle, transparent 7px, color-mix(in srgb, var(--a) 30%, transparent) 7.5px, color-mix(in srgb, var(--a) 30%, transparent) 9px, transparent 9.5px); background-size:30px 30px;
        -webkit-mask-image:linear-gradient(240deg, #000 20%, transparent 80%); mask-image:linear-gradient(240deg, #000 20%, transparent 80%); }
    .sph2-box-icon { position:relative; z-index:1; flex:0 0 44px; height:44px; border-radius:14px; background:#fff; color:var(--a); display:grid; place-items:center; box-shadow:0 6px 14px -8px rgba(22,35,63,.45); }
    .sph2-box-icon svg { width:23px; height:23px; }
    .sph2-box-head h3 { position:relative; z-index:1; flex:1; min-width:0; font-size:18.5px; font-weight:800; line-height:1.18; }
    .sph2-box-head small { display:block; margin-top:2px; font-family:'Figtree',sans-serif; font-size:13px; font-weight:600; color:var(--ink2); }
    .sph2-box-head .sph2-more { position:relative; z-index:1; }
    .sph2-box > ul { padding:8px; }
    .sph2-box .sph2-row { padding:10px; }
    .sph2-box .sph2-ico { flex-basis:40px; height:40px; border-radius:12px; }
    .sph2-box .sph2-ico svg { width:19px; height:19px; }
    .sph2-box .sph2-row-title { font-size:14.5px; }
    .sph2-links { display:grid; grid-template-columns:repeat(var(--cols, 4), minmax(0, 1fr)); gap:8px; padding:14px; }
    .sph2-box:not(.sph2-box--wide) .sph2-links { grid-template-columns:minmax(0,1fr); }
    .sph2 .sph2-link { display:flex; align-items:center; gap:10px; padding:11px 13px; border-radius:13px; background:var(--soft); font-weight:700; font-size:14.5px; line-height:1.3; color:var(--ink); transition:background .15s, color .15s; }
    .sph2-link > span:first-child { flex:0 0 auto; display:flex; color:var(--a); }
    .sph2-link > span:first-child svg { width:19px; height:19px; }
    .sph2-link > span:nth-child(2) { flex:1; min-width:0; }
    .sph2 .sph2-link:hover { background:var(--b); color:var(--a); }
    .sph2 .sph2-links--game .sph2-link { flex-direction:column; justify-content:center; text-align:center; gap:10px; padding:20px 12px; font-size:15.5px; }
    .sph2-links--game .sph2-link > span:first-child svg { width:30px; height:30px; }
    .sph2-empty { padding:16px 12px; color:var(--mute); font-size:15px; }
    .sph2-infeed { grid-column:1 / -1; display:flex; justify-content:center; overflow:hidden; text-align:center; }

    /* ---------- SEO metni ---------- */
    .sph2-about { margin-top:56px; display:grid; grid-template-columns:minmax(0, 320px) minmax(0, 1fr); gap:40px; padding-top:32px; border-top:1.5px solid var(--line); }
    .sph2-about h2 { font-size:22px; font-weight:800; line-height:1.2; }
    .sph2-about-text { color:var(--ink2); font-size:15.5px; line-height:1.7; max-width:72ch; }
    .sph2-about-text p + p { margin-top:14px !important; }
    .sph2-about-text strong { color:var(--ink); }

    /* ---------- Üyelik ---------- */
    .sph2-uye { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:18px; }
    .sph2-uye-k { position:relative; overflow:hidden; display:flex; flex-direction:column; gap:14px; padding:26px 26px 24px; border-radius:24px; }
    .sph2-uye-k > * { position:relative; z-index:1; }
    .sph2-uye-k.ogt { background:var(--brand); color:#fff; }
    .sph2-uye-k.ogt::after { content:''; position:absolute; right:-30px; top:-30px; width:230px; height:190px; opacity:.16; pointer-events:none; z-index:0;
        background-image:radial-gradient(circle, transparent 8px, #fff 8.5px, #fff 10px, transparent 10.5px); background-size:32px 32px;
        -webkit-mask-image:linear-gradient(225deg, #000 10%, transparent 75%); mask-image:linear-gradient(225deg, #000 10%, transparent 75%); }
    .sph2-uye-k.ogr { background:var(--paper); background-image:linear-gradient(rgba(224,69,123,.10) 1px, transparent 1px); background-size:100% 32px; border:1.5px solid rgba(224,69,123,.35); }
    .sph2-uye-bas { display:flex; align-items:center; gap:14px; }
    .sph2-uye-ik { flex:0 0 52px; height:52px; border-radius:16px; display:grid; place-items:center; font-size:26px; background:rgba(255,255,255,.14); }
    .sph2-uye-k.ogr .sph2-uye-ik { background:#fff; box-shadow:0 6px 14px -8px rgba(22,35,63,.45); }
    .sph2-uye-k h2 { font-size:clamp(20px, 2vw, 25px); font-weight:800; line-height:1.15; }
    .sph2-uye-k.ogt h2 { color:#fff; }
    .sph2-uye-bas small { display:block; font-size:13.5px; font-weight:600; margin-top:3px; }
    .sph2-uye-k.ogt .sph2-uye-bas small { color:rgba(255,255,255,.78); } .sph2-uye-k.ogr .sph2-uye-bas small { color:var(--pink-d); }
    .sph2-uye-k ul { display:grid; gap:8px; }
    .sph2-uye-k li { display:flex; gap:10px; align-items:flex-start; font-size:15px; line-height:1.4; }
    .sph2-uye-k li::before { content:''; flex:0 0 18px; height:18px; margin-top:1px; border-radius:50%; border:2px solid currentColor; opacity:.55; }
    .sph2-uye-k.ogt li { color:rgba(255,255,255,.92); } .sph2-uye-k.ogr li { color:var(--ink2); }
    .sph2-uye-k.ogr li::before { border-color:var(--pink); opacity:.8; }
    .sph2-uye-btn { display:flex; flex-wrap:wrap; gap:10px; margin-top:auto; }
    .sph2 .sph2-uye-btn a { display:inline-flex; align-items:center; gap:8px; padding:12px 18px; border-radius:12px; font-weight:700; font-size:15px; transition:background .15s, color .15s, transform .15s; }
    .sph2 .sph2-uye-btn a svg { width:15px; height:15px; }
    .sph2 .sph2-uye-k.ogt .sph2-uye-btn a.ana { background:#fff; color:var(--ink); }
    .sph2 .sph2-uye-k.ogt .sph2-uye-btn a.ana:hover { background:var(--paper); }
    .sph2 .sph2-uye-k.ogt .sph2-uye-btn a.ik2 { background:rgba(255,255,255,.12); color:#fff; border:1px solid rgba(255,255,255,.25); }
    .sph2 .sph2-uye-k.ogt .sph2-uye-btn a.ik2:hover { background:rgba(255,255,255,.2); }
    .sph2 .sph2-uye-k.ogr .sph2-uye-btn a.ana { background:var(--pink); color:#fff; box-shadow:3px 3px 0 var(--ink); }
    .sph2 .sph2-uye-k.ogr .sph2-uye-btn a.ana:hover { background:var(--pink-d); }
    .sph2 .sph2-uye-k.ogr .sph2-uye-btn a.ik2 { background:#fff; color:var(--ink); border:1.5px solid var(--line); }
    .sph2 .sph2-uye-k.ogr .sph2-uye-btn a.ik2:hover { border-color:var(--pink); color:var(--pink-d); }
    .sph2-kod { display:flex; align-items:center; gap:8px; background:#fff; border:1.5px dashed rgba(224,69,123,.55); border-radius:14px; padding:6px 6px 6px 14px; }
    .sph2-kod span { flex:1; min-width:0; font-size:13.5px; font-weight:600; color:var(--ink2); line-height:1.3; }
    .sph2 .sph2-kod input { width:118px; border:0 !important; border-bottom:2px solid rgba(224,69,123,.4) !important; background:transparent !important; box-shadow:none !important; font-family:'Bricolage Grotesque',sans-serif; font-weight:800; font-size:18px !important; letter-spacing:.16em; text-transform:uppercase; text-align:center; color:var(--ink) !important; padding:6px 4px !important; margin:0 !important; border-radius:0 !important; outline:none; }
    .sph2 .sph2-kod button { border:0 !important; background:var(--ink) !important; color:#fff !important; font:inherit; font-weight:700; font-size:14px; padding:10px 14px !important; border-radius:10px !important; cursor:pointer; margin:0 !important; line-height:1.2; }
    .sph2-uye-hos { display:flex; align-items:center; gap:18px; flex-wrap:wrap; padding:20px 24px; border-radius:22px; background:var(--soft); border:1.5px solid var(--line); }
    .sph2-uye-hos .sph2-uye-ik { background:#fff; box-shadow:0 6px 14px -8px rgba(22,35,63,.45); }
    .sph2-uye-hos h2 { font-size:21px; font-weight:800; }
    .sph2-uye-hos p { color:var(--mute); font-size:14.5px; margin-top:2px !important; }
    .sph2-uye-hos > div:nth-child(2) { flex:1; min-width:200px; }
    .sph2 .sph2-uye-hos a { display:inline-flex; align-items:center; gap:8px; padding:12px 18px; border-radius:12px; font-weight:700; font-size:15px; background:var(--brand); color:#fff; }
    .sph2 .sph2-uye-hos a:hover { background:var(--brand-d); }
    .sph2 .sph2-uye-hos a.ik2 { background:#fff; color:var(--ink); border:1.5px solid var(--line); }
    .sph2-uye-hos .sph2-kod { flex-basis:100%; }
    .sph2 .sph2-uye-hos a svg { width:15px; height:15px; }
    .sph2 .sph2-uye-btn a, .sph2 .sph2-uye-hos a { white-space:nowrap; justify-content:center; }

    @media (max-width: 1000px) {
        .sph2-hero { grid-template-columns:minmax(0,1fr); padding:30px 26px 30px 46px; }
        .sph2-sheet { transform:none; max-width:460px; }
        .sph2-grid { grid-template-columns:repeat(2, minmax(0,1fr)); }
        .sph2-pop { grid-template-columns:repeat(2, minmax(0,1fr)); }
        .sph2-band { grid-template-columns:minmax(0,1fr); }
        .sph2-box--wide .sph2-links { grid-template-columns:repeat(var(--cols-t, 3), minmax(0, 1fr)); }
        .sph2-feed { grid-template-columns:repeat(2, minmax(0,1fr)); }
        .sph2-feed li.is-feat { grid-column:1 / -1; grid-row:auto; }
        .sph2 .is-feat .sph2-feed-row { min-height:220px; }
    }
    @media (max-width: 760px) {
        .sph2-feed { grid-template-columns:minmax(0,1fr); }
        .sph2-feed li.is-feat { grid-row:auto; }
        .sph2 .is-feat .sph2-feed-row { min-height:0; padding:22px 20px 20px; }
        .sph2 .is-feat .sph2-feed-when { top:22px; right:20px; }
        .sph2-about { grid-template-columns:minmax(0,1fr); gap:14px; }
    }
    @media (max-width: 600px) {
        .sph2 { padding-top:16px; }
        .sph2-wrap { padding:0 14px; }
        .sph2-hero { padding:24px 18px 22px 32px; border-radius:20px; gap:26px; }
        .sph2-hero::before { left:16px; }
        .sph2-lead { font-size:15.5px; }
        .sph2-search { padding-left:10px; }
        .sph2 .sph2-search button { padding:11px 14px !important; }
        .sph2-sheet { padding:16px 14px; }
        .sph2-grade-row { grid-template-columns:62px repeat(4, 1fr); gap:4px; }
        .sph2 .sph2-bub { width:40px; height:40px; font-size:15px; }
        .sph2-panel { padding:4px; border-radius:18px; }
        .sph2 .sph2-row { padding:10px 8px; gap:12px; }
        .sph2-go { display:none; }
        .sph2-head { flex-wrap:wrap; }
        .sph2-tabs { width:100%; }
        .sph2 .sph2-tabs button { flex:1; padding:8px 6px !important; }
        .sph2 .sph2-feed-row { padding:12px; gap:12px; }
        .sph2-grid, .sph2-pop { grid-template-columns:minmax(0,1fr); }
        .sph2-band { padding:26px 20px; border-radius:20px; }
        .sph2-sec { margin-top:40px; }
        .sph2-box--wide .sph2-links { grid-template-columns:repeat(2, minmax(0,1fr)); padding:10px; }
        .sph2-box--wide .sph2-link:last-child:nth-child(odd) { grid-column:1 / -1; }
        .sph2-uye { grid-template-columns:minmax(0,1fr); }
        .sph2-uye-k { padding:22px 18px 20px; border-radius:20px; }
        .sph2 .sph2-uye-btn a { flex:1 1 auto; padding:12px 14px; font-size:14.5px; }
        .sph2-uye-hos { padding:18px; } .sph2 .sph2-uye-hos a { flex:1 1 auto; }
        .sph2-kod { flex-wrap:wrap; } .sph2-kod span { flex-basis:100%; padding-top:4px; } .sph2 .sph2-kod input { flex:1; }
    }
    @media (prefers-reduced-motion: reduce) { .sph2 * { transition:none !important; } }

    /* ===== Sade ana sayfa (v3) ===== */
    .sph2 { --brand:#2563EB; --brand-d:#1D4ED8; --ink:#0F172A; --ink2:#475569; --line:#E5E7EB; --soft:#F8FAFC; background:#fff; padding-top:0; }
    .sx3-hero { position:relative; text-align:center; padding:56px 0 8px; }
    .sx3-hero:before { content:""; position:absolute; left:50%; top:0; width:100vw; height:100%; transform:translateX(-50%); z-index:-1; background:radial-gradient(700px 280px at 50% -40px, #EEF4FF, transparent 70%), linear-gradient(#fff,#fff); }
    .sx3-hero h1 { font-size:clamp(30px,4.6vw,50px); line-height:1.08; letter-spacing:-.03em; font-weight:800; max-width:820px; margin:0 auto !important; }
    .sx3-hero h1 span { color:var(--brand); }
    .sx3-lead { margin:14px auto 0 !important; color:var(--ink2); font-size:17px; max-width:620px; }
    .sx3-search { position:relative; display:flex; align-items:center; gap:8px; max-width:640px; margin:26px auto 0; background:#fff; border:1.5px solid #D6DCE6; border-radius:16px; padding:6px 6px 6px 46px; box-shadow:0 18px 40px -28px rgba(15,23,42,.45); }
    .sx3-search:focus-within { border-color:var(--brand); box-shadow:0 0 0 4px rgba(37,99,235,.12); }
    .sx3-search svg { position:absolute; left:16px; width:20px; height:20px; color:#94A3B8; }
    .sx3-search input { flex:1; min-width:0; border:0 !important; outline:0; background:transparent; font:inherit; font-size:16px; padding:10px 4px !important; box-shadow:none !important; color:var(--ink); }
    .sph2 .sx3-search button { border:0; background:var(--brand); color:#fff; font:700 15px/1 inherit; font-family:inherit; padding:13px 22px; border-radius:12px; cursor:pointer; }
    .sph2 .sx3-search button:hover { background:var(--brand-d); }
    .sx3-siniflar { display:flex; flex-wrap:wrap; justify-content:center; gap:10px 22px; margin:22px auto 0; max-width:980px; }
    .sx3-siniflar div { display:flex; align-items:center; gap:6px; flex-wrap:wrap; justify-content:center; }
    .sx3-siniflar span { font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#94A3B8; margin-right:2px; }
    .sph2 .sx3-siniflar a { padding:6px 11px; border-radius:99px; border:1px solid var(--line); background:#fff; font-size:13.5px; font-weight:600; color:var(--ink2); transition:all .15s; }
    .sph2 .sx3-siniflar a:hover { border-color:var(--brand); color:var(--brand); background:#EEF4FF; }
    .sx3-kartlar { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin:38px 0 0; text-align:left; }
    .sph2 .sx3-kart { position:relative; display:flex; flex-direction:column; gap:6px; padding:20px 18px 18px; border-radius:18px; border:1px solid var(--line); background:#fff; transition:transform .2s, box-shadow .2s, border-color .2s; }
    .sph2 .sx3-kart:hover { transform:translateY(-3px); border-color:#C7D2FE; box-shadow:0 22px 40px -30px rgba(15,23,42,.55); }
    .sx3-ik { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:22px; margin-bottom:6px; }
    .sx3-a .sx3-ik { background:#EEF4FF; } .sx3-b .sx3-ik { background:#ECFDF5; } .sx3-c .sx3-ik { background:#F5F3FF; } .sx3-d .sx3-ik { background:#FFF7ED; }
    .sx3-kart b { font-size:17px; color:var(--ink); }
    .sx3-kart small { font-size:14px; line-height:1.5; color:var(--ink2); padding-right:14px; }
    .sx3-kart i { position:absolute; top:22px; right:18px; width:18px; height:18px; color:#94A3B8; transition:transform .2s, color .2s; }
    .sx3-kart:hover i { transform:translateX(3px); color:var(--brand); }
    .sx3-kart i svg { width:18px; height:18px; }
    /* bölümleri sadeleştir */
    .sph2 .sph2-sec { margin-top:64px; }
    .sph2 .sph2-band { background:var(--soft); color:var(--ink); border:1px solid var(--line); box-shadow:none; }
    .sph2 .sph2-band:before, .sph2 .sph2-band:after { display:none; }
    .sph2 .sph2-band h2 { color:var(--ink) !important; } .sph2 .sph2-band p { color:var(--ink2) !important; }
    .sph2 .sph2-band .sph2-band-cta { background:var(--brand); color:#fff !important; }
    .sph2 .sph2-band-steps div, .sph2 .sph2-band li a { background:#fff !important; color:var(--ink) !important; border:1px solid var(--line); }
    .sph2 .sph2-band-steps i, .sph2 .sph2-bubn { background:#EEF4FF !important; color:var(--brand) !important; border-color:#C7D2FE !important; }
    .sph2 .sph2-uye-k { background:#fff !important; color:var(--ink) !important; border:1px solid var(--line) !important; box-shadow:none !important; }
    .sph2 .sph2-uye-k:before, .sph2 .sph2-uye-k:after { display:none !important; }
    .sph2 .sph2-uye-k h2 { color:var(--ink) !important; } .sph2 .sph2-uye-k small, .sph2 .sph2-uye-k li { color:var(--ink2) !important; }
    .sph2 .sph2-uye-k .ana { background:var(--brand) !important; color:#fff !important; } .sph2 .sph2-uye-k .ik2 { background:var(--soft) !important; color:var(--ink) !important; border:1px solid var(--line) !important; }
    .sph2 .sph2-uye-k li:before { border-color:var(--brand) !important; }
    .sph2 .om-ana-kap { background:linear-gradient(135deg,#F5F3FF,#EEF4FF 60%,#ECFEFF); color:var(--ink); box-shadow:none; border:1px solid #E0E7FF; }
    .sph2 .om-ana h2 { color:var(--ink) !important; } .sph2 .om-ana h2 em { background:linear-gradient(90deg,#4F46E5,#0EA5E9); -webkit-background-clip:text; background-clip:text; }
    .sph2 .om-ana-sol p { color:var(--ink2); } .sph2 .om-ana-et { color:#4338CA; background:#fff; border-color:#E0E7FF; }
    .sph2 .om-ana-btn { background:#4F46E5; color:#fff !important; }
    .sph2 .om-ana-kart { background:#fff; border-color:#E0E7FF; color:var(--ink) !important; } .sph2 .om-ana-kart:hover { background:#fff; box-shadow:0 14px 30px -24px rgba(30,27,75,.6); }
    .sph2 .om-ana-kart small { color:var(--ink2); } .sph2 .om-ana-kart em { color:#4F46E5; }
    @media (max-width: 900px) { .sx3-kartlar { grid-template-columns:repeat(2,1fr); } .sx3-hero { padding-top:36px; } }
    @media (max-width: 520px) { .sx3-kartlar { grid-template-columns:1fr 1fr; gap:10px; } .sph2 .sx3-kart { padding:14px; } .sx3-kart small { display:none; } .sx3-kart b { font-size:15px; } .sx3-kart i { display:none; } .sx3-siniflar { gap:8px; } .sx3-siniflar span { width:100%; text-align:center; } .sx3-search { padding-left:40px; } .sph2 .sx3-search button { padding:12px 16px; } }
</style>

<div class="sph2"><div class="sph2-wrap">

    <?php if (!empty($ad_home_top)): ?>
    <div class="sph2-ad"><div><?php echo stripslashes($ad_home_top); ?></div></div>
    <?php endif; ?>

    <!-- GİRİŞ: karşılama + arama + giriş düğmeleri; hizmet kartları + yan sütun -->
    <?php
    $sx4_om  = class_exists('OSC_Masa') && OSC_Masa::yayinda();
    $sx4_lgs = class_exists('LGS_Tercih_Robotu') && method_exists('LGS_Tercih_Robotu', 'robot_url') ? LGS_Tercih_Robotu::robot_url() : '';
    $sx4_yks = class_exists('YKS_Tercih_Robotu') && method_exists('YKS_Tercih_Robotu', 'robot_url') ? YKS_Tercih_Robotu::robot_url() : '';
    $sx4_svg = array(
        'test'  => '<svg viewBox="0 0 96 80"><rect x="22" y="6" width="44" height="66" rx="7" fill="#1E3A5F"/><rect x="26" y="12" width="36" height="52" rx="3" fill="#fff"/><g stroke-linecap="round" stroke-width="3"><path d="M31 22l3 3 5-6" stroke="#22A06B" fill="none"/><path d="M43 22h14" stroke="#CBD5E1"/><path d="M31 35l3 3 5-6" stroke="#22A06B" fill="none"/><path d="M43 35h14" stroke="#CBD5E1"/><path d="M31 48l3 3 5-6" stroke="#22A06B" fill="none"/><path d="M43 48h10" stroke="#CBD5E1"/></g><circle cx="72" cy="58" r="14" fill="#FDBA3B"/><path d="M66 58l4 4 8-9" stroke="#fff" stroke-width="3.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'indir' => '<svg viewBox="0 0 96 80"><rect x="30" y="4" width="36" height="30" rx="2" fill="#fff" stroke="#CBD5E1" stroke-width="2"/><path d="M36 13h24M36 20h24M36 27h16" stroke="#93C5FD" stroke-width="3" stroke-linecap="round"/><rect x="14" y="30" width="68" height="30" rx="8" fill="#3B5B7D"/><rect x="24" y="52" width="48" height="24" rx="2" fill="#fff" stroke="#CBD5E1" stroke-width="2"/><path d="M31 61h34M31 68h22" stroke="#CBD5E1" stroke-width="3" stroke-linecap="round"/><circle cx="72" cy="40" r="3" fill="#4ADE80"/></svg>',
        'yazili'=> '<svg viewBox="0 0 96 80"><rect x="14" y="10" width="46" height="62" rx="4" fill="#fff" stroke="#CBD5E1" stroke-width="2" transform="rotate(-6 37 41)"/><rect x="28" y="6" width="46" height="62" rx="4" fill="#fff" stroke="#94A3B8" stroke-width="2"/><path d="M35 18h30M35 27h30M35 36h22M35 45h28M35 54h18" stroke="#CBD5E1" stroke-width="3" stroke-linecap="round"/><path d="M33 18h0M33 27h0" stroke="#2F80ED"/><g transform="rotate(35 74 50)"><rect x="70" y="22" width="9" height="40" rx="2" fill="#F59E0B"/><path d="M70 62l4.5 9 4.5-9z" fill="#FDE68A"/><rect x="70" y="18" width="9" height="6" rx="2" fill="#F87171"/></g></svg>',
        'plan'  => '<svg viewBox="0 0 96 80"><rect x="14" y="12" width="60" height="58" rx="8" fill="#fff" stroke="#CBD5E1" stroke-width="2"/><rect x="14" y="12" width="60" height="16" rx="8" fill="#EF6C57"/><rect x="14" y="20" width="60" height="8" fill="#EF6C57"/><path d="M28 6v12M60 6v12" stroke="#1E3A5F" stroke-width="4" stroke-linecap="round"/><g fill="#E2E8F0"><rect x="22" y="35" width="9" height="8" rx="2"/><rect x="35" y="35" width="9" height="8" rx="2"/><rect x="48" y="35" width="9" height="8" rx="2"/><rect x="61" y="35" width="7" height="8" rx="2"/><rect x="22" y="48" width="9" height="8" rx="2"/><rect x="48" y="48" width="9" height="8" rx="2"/><rect x="61" y="48" width="7" height="8" rx="2"/><rect x="22" y="60" width="9" height="6" rx="2"/></g><rect x="35" y="48" width="9" height="8" rx="2" fill="#2F80ED"/><circle cx="76" cy="60" r="13" fill="#22A06B"/><path d="M76 53v7l5 3" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round"/></svg>',
        'masa'  => '<svg viewBox="0 0 96 80"><rect x="8" y="50" width="80" height="7" rx="3" fill="#8B5E3C"/><path d="M16 57v18M80 57v18" stroke="#8B5E3C" stroke-width="5" stroke-linecap="round"/><rect x="18" y="20" width="38" height="26" rx="3" fill="#1E3A5F"/><rect x="21" y="23" width="32" height="20" rx="1" fill="#DBEAFE"/><path d="M25 30h14M25 36h20" stroke="#2F80ED" stroke-width="2.5" stroke-linecap="round"/><path d="M14 50h46" stroke="#334155" stroke-width="3" stroke-linecap="round"/><rect x="62" y="32" width="18" height="6" rx="1.5" fill="#22A06B"/><rect x="60" y="38" width="22" height="6" rx="1.5" fill="#2F80ED"/><rect x="63" y="44" width="18" height="6" rx="1.5" fill="#F59E0B"/><circle cx="74" cy="22" r="6" fill="#EF4444"/><path d="M74 16c0-3 2-5 4-5" stroke="#16A34A" stroke-width="2" fill="none"/></svg>',
        'tercih'=> '<svg viewBox="0 0 96 80"><path d="M48 10L8 28l40 18 40-18z" fill="#1E3A5F"/><path d="M24 36v16c0 6 11 12 24 12s24-6 24-12V36L48 47z" fill="#3B5B7D"/><path d="M84 30v22" stroke="#F59E0B" stroke-width="3"/><circle cx="84" cy="55" r="4" fill="#F59E0B"/><rect x="58" y="56" width="30" height="20" rx="4" fill="#fff" stroke="#CBD5E1" stroke-width="2"/><path d="M63 70l6-6 5 4 9-9" stroke="#22A06B" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'zumre' => '<svg viewBox="0 0 96 80"><ellipse cx="48" cy="50" rx="34" ry="12" fill="#C08457"/><rect x="40" y="40" width="18" height="12" rx="1.5" fill="#fff" transform="rotate(-8 49 46)"/><circle cx="20" cy="28" r="8" fill="#E8B48A"/><path d="M8 50c0-10 5-14 12-14s12 4 12 14z" fill="#2F80ED"/><circle cx="48" cy="18" r="8" fill="#F4C7A1"/><path d="M36 40c0-10 5-14 12-14s12 4 12 14z" fill="#22A06B"/><circle cx="76" cy="28" r="8" fill="#C68B5E"/><path d="M64 50c0-10 5-14 12-14s12 4 12 14z" fill="#EF6C57"/><path d="M30 70l4-10M66 70l-4-10" stroke="#8B5E3C" stroke-width="4" stroke-linecap="round"/></svg>',
        'hesap' => '<svg viewBox="0 0 96 80"><rect x="24" y="4" width="48" height="72" rx="8" fill="#1E3A5F"/><rect x="30" y="11" width="36" height="16" rx="3" fill="#C7F0D8"/><text x="62" y="24" text-anchor="end" font-size="11" font-family="monospace" fill="#14532D" font-weight="700">482,5</text><g fill="#3B5B7D"><rect x="30" y="33" width="10" height="9" rx="2"/><rect x="43" y="33" width="10" height="9" rx="2"/><rect x="30" y="46" width="10" height="9" rx="2"/><rect x="43" y="46" width="10" height="9" rx="2"/><rect x="30" y="59" width="10" height="9" rx="2"/><rect x="43" y="59" width="10" height="9" rx="2"/></g><rect x="56" y="33" width="10" height="9" rx="2" fill="#F59E0B"/><rect x="56" y="46" width="10" height="22" rx="2" fill="#22A06B"/></svg>',
    );
    $sx4_kart = array(
        array('test', 'Online test çöz', 'Deneme, yaprak test ve LGS–YKS sorularını ekranda çöz; puanını ve yanlışlarını anında gör.', $test_page_url, '#E9F7EF'),
        array('indir', 'İndir & yazdır', 'Tüm kategoriler: deneme, çalışma kâğıdı, etkinlik ve evraklar; PDF ve Word olarak.', '#sph2-kat', '#EAF2FD'),
        array('yazili', 'Yazılı soruları', 'Senaryolara uygun 1. ve 2. dönem yazılıları; sınıf ve derse göre.', home_url('/category/yazili-sorulari/'), '#FFF6E5'),
        array('plan', 'Yıllık planlar', 'Tüm sınıf ve dersler için güncel, ünitelendirilmiş yıllık planlar.', $sx4_om ? OSC_Masa::url() . '#yillik' : home_url('/category/yillik-planlar/'), '#FDEDEA'),
    );
    if ($sx4_om) $sx4_kart[] = array('masa', 'Öğretmen Masası', 'Yazılı hazırla, tutanak, BEP, kulüp evrakı ve OYS çizelgesi; dakikalar içinde.', OSC_Masa::url(), '#EEF0FE');
    if ($sx4_lgs || $sx4_yks) $sx4_kart[] = array('tercih', 'Tercih robotları', 'LGS ve YKS taban puanlarıyla puanına uygun okul ve bölümleri gör.', $sx4_lgs ? $sx4_lgs : $sx4_yks, '#E8F6F8');
    $sx4_kart[] = array('zumre', 'Zümre tutanakları', 'Şube, zümre ve öğretmenler kurulu tutanakları; düzenlenebilir Word.', home_url('/category/zumre-toplanti-tutanaklari/'), '#F3ECFB');
    $sx4_kart[] = array('hesap', 'Puan hesaplama', 'LGS, YKS, AGS, KPSS ve daha fazlası için puan hesaplama araçları.', home_url('/lgs-puan-hesaplama/'), '#EEF6E8');
    if (count($sx4_kart) % 2) array_pop($sx4_kart);
    $sx4_yan = $popular ? array_slice($popular, 0, 5) : array();
    ?>
    <style>
    .sx4-hero{position:relative;margin:22px 0 0;padding:40px 24px 34px;border-radius:28px;background:linear-gradient(180deg,#EAF4FC 0%,#F4F9FE 100%);border:1px solid #DCEAF7;overflow:hidden;text-align:center}
    .sx4-hero:before{content:"";position:absolute;inset:auto -10% -60% -10%;height:70%;background:radial-gradient(closest-side,rgba(255,255,255,.9),transparent);pointer-events:none}
    .sx4-ill{position:absolute;bottom:0;width:210px;height:auto;pointer-events:none}
    .sx4-ill.sol{left:18px}.sx4-ill.sag{right:18px}
    .sx4-ic{position:relative;max-width:700px;margin:0 auto}
    .sx4-hero h1{font-size:clamp(27px,3.6vw,40px);line-height:1.12;letter-spacing:-.025em;font-weight:800;color:#13304F;margin:0 !important}
    .sx4-hero h1 span{color:#2F80ED}
    .sx4-lead{margin:10px auto 0 !important;color:#46607A;font-size:16.5px}
    .sx4-search{position:relative;display:flex;align-items:center;gap:8px;margin:22px auto 0;background:#fff;border:1.5px solid #D3E2F1;border-radius:999px;padding:6px 6px 6px 48px;box-shadow:0 16px 34px -26px rgba(19,48,79,.55)}
    .sx4-search:focus-within{border-color:#2F80ED;box-shadow:0 0 0 4px rgba(47,128,237,.14)}
    .sx4-search svg{position:absolute;left:18px;width:20px;height:20px;color:#8AA2BA}
    .sx4-search input{flex:1;min-width:0;border:0 !important;outline:0;background:transparent;font:inherit;font-size:16px;padding:10px 4px !important;box-shadow:none !important;color:#13304F}
    .sph2 .sx4-search button{border:0;background:#2F80ED;color:#fff;font-weight:700;font-size:15px;font-family:inherit;padding:12px 24px;border-radius:999px;cursor:pointer}
    .sph2 .sx4-search button:hover{background:#1F6FD8}
    .sx4-btns{display:inline-flex;flex-wrap:wrap;justify-content:center;gap:10px;margin:18px auto 0;padding:8px;background:rgba(255,255,255,.75);border:1px solid #DCEAF7;border-radius:20px;box-shadow:0 18px 36px -30px rgba(19,48,79,.5)}
    .sph2 .sx4-btn{display:flex;align-items:center;gap:10px;padding:10px 18px 10px 12px;border-radius:14px;color:#fff;text-align:left;min-width:178px;transition:transform .15s,filter .15s}
    .sph2 .sx4-btn:hover{transform:translateY(-2px);filter:brightness(1.05);color:#fff}
    .sx4-btn i{width:34px;height:34px;border-radius:10px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-style:normal}
    .sx4-btn i svg{width:19px;height:19px}
    .sx4-btn b{display:block;font-size:15.5px;line-height:1.2}.sx4-btn small{display:block;font-size:12px;opacity:.85}
    .sx4-btn.yesil{background:#22A06B}.sx4-btn.mavi{background:#2F80ED}.sx4-btn.lacivert{background:#1E4E79}
    .sx4-sinif{display:flex;flex-wrap:wrap;justify-content:center;gap:6px;margin:20px auto 0;max-width:600px;position:relative}
    .sph2 .sx4-sinif a{padding:5px 11px;border-radius:99px;background:#fff;border:1px solid #DCEAF7;font-size:13px;font-weight:600;color:#46607A}
    .sph2 .sx4-sinif a:hover{border-color:#2F80ED;color:#2F80ED}
    .sx4-govde{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:18px;margin-top:22px;align-items:stretch}
    .sx4-kartlar{display:grid;grid-template-columns:1fr 1fr;grid-auto-rows:1fr;gap:14px}
    .sph2 .sx4-kart{display:flex;align-items:center;gap:14px;padding:16px 16px 16px 20px;border-radius:20px;background:#fff;border:1px solid #E3EBF3;box-shadow:0 1px 2px rgba(19,48,79,.04);transition:transform .18s,box-shadow .18s,border-color .18s;min-height:118px}
    .sph2 .sx4-kart:hover{transform:translateY(-3px);border-color:#C9DCF0;box-shadow:0 20px 36px -28px rgba(19,48,79,.55)}
    .sx4-kart .yz{flex:1;min-width:0}
    .sx4-kart b{display:block;font-size:18px;color:#13304F;letter-spacing:-.01em}
    .sx4-kart small{display:block;margin-top:5px;font-size:13.5px;line-height:1.5;color:#5B7189}
    .sx4-kart .re{flex:0 0 104px;height:88px;border-radius:16px;display:flex;align-items:center;justify-content:center}
    .sx4-kart .re svg{width:92px;height:76px}
    .sx4-yan{display:flex;flex-direction:column;gap:14px}.sx4-yan .sx4-kutu:last-child{flex:1}
    .sx4-kutu{background:#fff;border:1px solid #E3EBF3;border-radius:20px;padding:16px 16px 10px}
    .sx4-kutu h3{margin:0 0 8px !important;font-size:16px;color:#13304F;display:flex;align-items:center;gap:8px}
    .sx4-kutu h3:before{content:"";width:8px;height:8px;border-radius:50%;background:#2F80ED}
    .sx4-kutu.pop h3:before{background:#F59E0B}
    .sx4-kutu ul{list-style:none;margin:0;padding:0}
    .sx4-kutu li{border-top:1px solid #EEF3F8}.sx4-kutu li:first-child{border-top:0}
    .sph2 .sx4-kutu li a{display:flex;gap:8px;padding:9px 2px;font-size:14px;line-height:1.4;color:#24405E;font-weight:600}
    .sph2 .sx4-kutu li a:hover{color:#2F80ED}
    .sx4-kutu li a em{flex:0 0 auto;font-style:normal;color:#8AA2BA;font-weight:700;font-size:12px;min-width:16px;padding-top:2px}
    .sx4-kutu li a span{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .sx4-kutu .alt{display:block;font-size:12px;color:#8AA2BA;font-weight:500;margin-top:2px}
    @media (max-width:1100px){.sx4-ill{width:150px;opacity:.55}}
    @media (max-width:960px){.sx4-ill{display:none}.sx4-govde{grid-template-columns:1fr}.sx4-yan{display:grid;grid-template-columns:1fr 1fr}}
    @media (max-width:640px){.sx4-hero{padding:28px 14px 24px;border-radius:22px}.sx4-kartlar{grid-template-columns:1fr}.sx4-yan{grid-template-columns:1fr}.sph2 .sx4-btn{min-width:0;flex:1 1 100%}.sx4-btns{display:flex}.sx4-search{padding-left:42px}.sx4-search svg{left:15px}.sph2 .sx4-search button{padding:11px 16px}.sx4-kart .re{flex-basis:84px;height:76px}.sx4-kart .re svg{width:74px;height:62px}}
    </style>
    <section class="sx4-hero" aria-labelledby="sph2-h">
        <svg class="sx4-ill sol" viewBox="0 0 220 170" aria-hidden="true"><rect x="10" y="14" width="150" height="96" rx="8" fill="#8B5E3C"/><rect x="17" y="21" width="136" height="82" rx="4" fill="#2E5E4E"/><text x="32" y="52" fill="#E8F5E9" font-size="17" font-family="Georgia,serif" font-style="italic">a² + b² = c²</text><path d="M32 66h70M32 80h46" stroke="#A7D3B8" stroke-width="3" stroke-linecap="round" opacity=".8"/><path d="M118 72l22-22M118 50h22v22" stroke="#FDE68A" stroke-width="2.5" fill="none" opacity=".9"/><rect x="40" y="103" width="90" height="5" rx="2" fill="#6B4528"/><rect x="128" y="96" width="14" height="4" rx="1" fill="#fff"/><rect x="0" y="150" width="220" height="20" fill="#DCEAF7"/><rect x="138" y="112" width="70" height="40" rx="4" fill="#C08457"/><rect x="146" y="98" width="52" height="8" rx="2" fill="#2F80ED"/><rect x="142" y="90" width="58" height="8" rx="2" fill="#22A06B"/><rect x="148" y="82" width="48" height="8" rx="2" fill="#F59E0B"/><circle cx="60" cy="140" r="12" fill="#F4C7A1"/><path d="M48 168c0-14 6-20 12-20s12 6 12 20z" fill="#2F80ED"/><circle cx="94" cy="142" r="11" fill="#C68B5E"/><path d="M83 168c0-13 5-18 11-18s11 5 11 18z" fill="#F59E0B"/><circle cx="26" cy="143" r="11" fill="#E8B48A"/><path d="M15 168c0-13 5-18 11-18s11 5 11 18z" fill="#22A06B"/></svg>
        <svg class="sx4-ill sag" viewBox="0 0 220 170" aria-hidden="true"><rect x="60" y="10" width="130" height="92" rx="8" fill="#D9A066"/><rect x="66" y="16" width="118" height="80" rx="4" fill="#F3D9B1"/><rect x="74" y="24" width="34" height="28" rx="2" fill="#fff" transform="rotate(-4 91 38)"/><rect x="116" y="22" width="30" height="24" rx="2" fill="#FDE68A"/><rect x="152" y="26" width="26" height="30" rx="2" fill="#BFDBFE" transform="rotate(5 165 41)"/><rect x="82" y="58" width="40" height="30" rx="2" fill="#fff"/><rect x="130" y="56" width="44" height="32" rx="2" fill="#BBF7D0" transform="rotate(-3 152 72)"/><circle cx="90" cy="25" r="3" fill="#EF4444"/><circle cx="131" cy="22" r="3" fill="#2F80ED"/><circle cx="165" cy="27" r="3" fill="#22A06B"/><circle cx="102" cy="59" r="3" fill="#F59E0B"/><path d="M80 32h20M80 39h16M88 68h26M88 75h18" stroke="#94A3B8" stroke-width="2" stroke-linecap="round"/><rect x="0" y="150" width="220" height="20" fill="#DCEAF7"/><circle cx="40" cy="96" r="13" fill="#E8B48A"/><path d="M27 96c0-10 6-15 13-15s13 5 13 15c-3-5-8-7-13-7s-10 2-13 7z" fill="#3F2A1D"/><path d="M24 168l4-46c1-8 6-12 12-12s11 4 12 12l4 46z" fill="#1E4E79"/><rect x="34" y="112" width="12" height="18" rx="2" fill="#fff"/><path d="M52 118l14-12" stroke="#E8B48A" stroke-width="6" stroke-linecap="round"/><circle cx="188" cy="140" r="11" fill="#F4C7A1"/><path d="M176 168c0-13 5-19 12-19s12 6 12 19z" fill="#EF6C57"/><rect x="172" y="146" width="10" height="16" rx="3" fill="#F59E0B"/></svg>
        <div class="sx4-ic">
            <h1 id="sph2-h">Eğitimde aradığın her şey <span>tek çatı altında</span></h1>
            <p class="sx4-lead">Yazılı soruları, testler, planlar ve evraklar. Ara, indir ya da ekranda çöz.</p>
            <form role="search" method="get" class="sx4-search" action="<?php echo esc_url(home_url('/')); ?>">
                <?php echo $ico_search; ?>
                <label for="sph2-s" style="position:absolute;left:-9999px;">Sitede ara</label>
                <input type="search" id="sph2-s" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Örn: 8. sınıf matematik 1. yazılı" autocomplete="off" required>
                <button type="submit">Ara</button>
            </form>
        </div>
        <div class="sx4-btns">
            <?php if ($sph_giris): ?>
            <a class="sx4-btn yesil" href="<?php echo esc_url($sph_rol === 'ogrenci' ? $ogr_panel : $ogt_panel); ?>"><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></i><span><b>Panelime git</b><small><?php echo $sph_rol === 'ogrenci' ? 'Ödevler, sınavlar, netler' : 'Canlı sınav, ödev, test'; ?></small></span></a>
            <?php if ($sx4_om): ?><a class="sx4-btn lacivert" href="<?php echo esc_url(OSC_Masa::url()); ?>"><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h4"/></svg></i><span><b>Öğretmen Masası</b><small>Yazılı, plan, evrak</small></span></a><?php endif; ?>
            <?php else: ?>
            <a class="sx4-btn yesil" href="<?php echo esc_url(add_query_arg(array('rol' => 'ogretmen', 'git' => $ogt_panel), $uye_url)); ?>"><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg></i><span><b>Öğretmen girişi</b><small>Canlı sınav, ödev, test</small></span></a>
            <a class="sx4-btn mavi" href="<?php echo esc_url(add_query_arg(array('rol' => 'ogrenci', 'git' => $ogr_panel), $uye_url)); ?>"><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 19V6a2 2 0 0 1 2-2h12v15H6a2 2 0 0 0-2 2z"/><path d="M8 8h6"/></svg></i><span><b>Öğrenci girişi</b><small>Netler, ödevler, sınavlar</small></span></a>
            <a class="sx4-btn lacivert" href="<?php echo esc_url(add_query_arg('kayit', '1', $uye_url)); ?>"><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-7 7-7s7 3 7 7M19 8v6M16 11h6"/></svg></i><span><b>Ücretsiz üye ol</b><small>Tüm araçlar ücretsiz</small></span></a>
            <?php endif; ?>
        </div>
        <nav class="sx4-sinif" aria-label="Sınıflar">
            <?php foreach ($grades as $label => $nums) foreach ($nums as $n): ?><a href="<?php echo esc_url(home_url('/category/' . $n . '-sinif')); ?>"><?php echo $n; ?>. sınıf</a><?php endforeach; ?>
        </nav>
    </section>

    <div class="sx4-govde">
        <div class="sx4-kartlar">
            <?php foreach ($sx4_kart as $k): ?>
            <a class="sx4-kart" href="<?php echo esc_url($k[3]); ?>"><span class="yz"><b><?php echo esc_html($k[1]); ?></b><small><?php echo esc_html($k[2]); ?></small></span><span class="re" style="background:<?php echo $k[4]; ?>" aria-hidden="true"><?php echo $sx4_svg[$k[0]]; ?></span></a>
            <?php endforeach; ?>
        </div>
        <aside class="sx4-yan" aria-label="Öne çıkanlar">
            <div class="sx4-kutu"><h3>Son eklenenler</h3><ul>
                <?php foreach (array_slice($latest, 0, 5) as $p): ?><li><a href="<?php echo esc_url(get_permalink($p)); ?>"><span><?php echo esc_html(get_the_title($p)); ?><small class="alt"><?php echo $sph_istest($p) ? 'Online test · ' : ''; ?><?php echo esc_html($sph_ago($p)); ?></small></span></a></li><?php endforeach; ?>
                <?php if (!$latest): ?><li><a href="#">Henüz içerik yok</a></li><?php endif; ?>
            </ul></div>
            <?php if ($sx4_yan): ?>
            <div class="sx4-kutu pop"><h3>Bu hafta en çok indirilenler</h3><ul>
                <?php foreach ($sx4_yan as $i => $row): ?><li><a href="<?php echo esc_url(get_permalink($row[0])); ?>"><em><?php echo $i + 1; ?></em><span><?php echo esc_html(get_the_title($row[0])); ?></span></a></li><?php endforeach; ?>
            </ul></div>
            <?php elseif ($top_tests): ?>
            <div class="sx4-kutu pop"><h3>Bu hafta en çok çözülen testler</h3><ul>
                <?php foreach ($top_tests as $i => $row): ?><li><a href="<?php echo esc_url(get_permalink($row[0])); ?>"><em><?php echo $i + 1; ?></em><span><?php echo esc_html(get_the_title($row[0])); ?></span></a></li><?php endforeach; ?>
            </ul></div>
            <?php endif; ?>
        </aside>
    </div>

    <!-- SON EKLENENLER -->
    <section class="sph2-sec" aria-labelledby="sph2-l">
        <div class="sph2-head">
            <h2 id="sph2-l">Yeni içerikler</h2>
            <?php $kinds = array(); foreach ($latest as $p) $kinds[$sph_istest($p) ? 'test' : 'file'] = 1; if (count($kinds) > 1): ?>
            <div class="sph2-tabs" role="tablist" aria-label="Son eklenenleri süz">
                <button type="button" role="tab" aria-selected="true" data-f="all">Tümü</button>
                <button type="button" role="tab" aria-selected="false" data-f="file">Dosyalar</button>
                <button type="button" role="tab" aria-selected="false" data-f="test">Online testler</button>
            </div>
            <?php endif; ?>
        </div>
        <div>
            <?php if ($latest): ?>
            <ul class="sph2-feed" id="sph2-feed">
                <?php foreach ($latest as $p):
                    $is_test = $sph_istest($p); $pc = get_the_category($p->ID); $thumb = get_the_post_thumbnail_url($p->ID, 'thumbnail'); ?>
                <li data-kind="<?php echo $is_test ? 'test' : 'file'; ?>"<?php echo $p === $latest[0] ? ' class="is-feat"' : ''; ?>><a class="sph2-feed-row<?php echo $is_test ? ' is-test' : ''; ?>" href="<?php echo esc_url(get_permalink($p)); ?>">
                    <span class="sph2-feed-ico<?php echo $thumb ? ' has-img' : ''; ?>" aria-hidden="true"><?php echo $thumb ? '<img src="' . esc_url($thumb) . '" alt="" loading="lazy">' : ($is_test ? $ico_test : $ico_doc); ?></span>
                    <span class="sph2-feed-body">
                        <span class="sph2-feed-title"><?php echo esc_html(get_the_title($p)); ?></span>
                        <span class="sph2-feed-cta"><?php echo $is_test ? 'Testi çöz' : 'Dosyaya git'; ?> <?php echo $ico_go; ?></span>
                    </span>
                    <span class="sph2-feed-when"><?php if ($sph_isnew($p)): ?><b>Yeni</b><?php endif; ?><?php echo esc_html($sph_ago($p)); ?></span>
                </a></li>
                <?php endforeach; ?>
            </ul>
            <p class="sph2-empty" id="sph2-feed-empty" hidden style="text-align:center">Bu türde yeni içerik yok.</p>
            <?php else: ?>
            <p class="sph2-empty">Henüz içerik yok.</p>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($ad_home_mid)): ?>
    <div class="sph2-ad sph2-ad--mid"><div><?php echo stripslashes($ad_home_mid); ?></div></div>
    <?php endif; ?>

    <?php if ($popular): ?>
    <!-- BU HAFTA EN ÇOK İNDİRİLENLER -->
    <section class="sph2-sec" aria-labelledby="sph2-p">
        <div class="sph2-head"><div><h2 id="sph2-p">Bu hafta en çok indirilenler</h2><p>Bu hafta en çok indirilen dosyaların sıralaması.</p></div></div>
        <ol class="sph2-pop">
            <?php foreach ($popular as $i => $row): list($p, $n) = $row; ?>
            <li><a href="<?php echo esc_url(get_permalink($p)); ?>">
                <span class="sph2-rank"><?php echo $i + 1; ?></span>
                <span><h3><?php echo esc_html(get_the_title($p)); ?></h3><small><b><?php echo number_format_i18n($n); ?></b> kez indirildi</small></span>
            </a></li>
            <?php endforeach; ?>
        </ol>
    </section>
    <?php endif; ?>

    <!-- KATEGORİLER -->
    <section class="sph2-sec" id="sph2-kat" aria-label="Kategoriler" style="scroll-margin-top:90px">
        <div class="sph2-grid">
        <?php
        $c_idx = 0; $kategori_sayaci = 0; $infeed_done = false;
        $infeed = function () use (&$kategori_sayaci, &$infeed_done, $ad_home_infeed) {
            if (!$infeed_done && $kategori_sayaci == 3 && !empty($ad_home_infeed)) { $infeed_done = true; echo '<div class="sph2-infeed">' . stripslashes($ad_home_infeed) . '</div>'; }
        };

        // 1) Menüden gelen özel gruplar ("Anasayfa Gruplari")
        $wide_boxes = array(); $narrow_boxes = array();
        $menu_items = wp_get_nav_menu_items('Anasayfa Gruplari');
        $kullanilan_menu_obj_idleri = array();
        if ($menu_items && !is_wp_error($menu_items)) {
            $menu_hierarchy = array();
            foreach ($menu_items as $item) { if (empty($item->menu_item_parent)) $menu_hierarchy[$item->ID] = array('title' => $item->title, 'children' => array(), 'ID' => $item->ID); }
            foreach ($menu_items as $item) {
                if (!empty($item->menu_item_parent) && isset($menu_hierarchy[$item->menu_item_parent])) {
                    $menu_hierarchy[$item->menu_item_parent]['children'][] = $item;
                    if ($item->object == 'category') $kullanilan_menu_obj_idleri[] = $item->object_id;
                }
            }
            foreach ($menu_hierarchy as $grup) {
                if (empty($grup['children'])) continue;
                $col = $sph_palette[$c_idx % count($sph_palette)]; $c_idx++;
                $g_title = function_exists('mb_strtolower') ? mb_strtolower($grup['title'], 'UTF-8') : strtolower($grup['title']);
                $child_count = count($grup['children']);
                $is_game = (strpos($g_title, 'oyun') !== false);
                $is_wide = $child_count > 8 || $is_game || strpos($g_title, 'hesaplama') !== false || strpos($g_title, 'yıllık plan') !== false || strpos($g_title, 'yillik plan') !== false;
                $g_icon = sp_get_kategori_ikonu($grup['title']);
                $cols_d = $sph_cols($child_count, array(6, 5, 4, 3));
                $cols_t = $sph_cols($child_count, array(4, 3, 2));
                ob_start();
                ?>
                <div class="sph2-box<?php echo $is_wide ? ' sph2-box--wide' : ''; ?>" style="--a:<?php echo $col[0]; ?>;--b:<?php echo $col[1]; ?>">
                    <div class="sph2-box-head">
                        <span class="sph2-box-icon" aria-hidden="true"><?php echo $g_icon; ?></span>
                        <h3><?php echo esc_html($grup['title']); ?></h3>
                    </div>
                    <div class="sph2-links<?php echo $is_game ? ' sph2-links--game' : ''; ?>" style="--cols:<?php echo $cols_d; ?>;--cols-t:<?php echo $cols_t; ?>">
                        <?php foreach ($grup['children'] as $child): ?>
                        <a class="sph2-link" href="<?php echo esc_url($child->url); ?>"><span aria-hidden="true"><?php echo $g_icon; ?></span><span><?php echo esc_html($child->title); ?></span></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
                if ($is_wide) $wide_boxes[] = ob_get_clean(); else $narrow_boxes[] = ob_get_clean();
            }
        }
        // Geniş gruplar önce (tam satır), dar gruplar kategori kutularıyla aynı ızgarada akar; boşluk kalmaz
        foreach ($wide_boxes as $html) { echo $html; $kategori_sayaci++; $infeed(); }
        foreach ($narrow_boxes as $html) { echo $html; $kategori_sayaci++; $infeed(); }

        // 2) Tema ayarlarında seçilen kategoriler (menüde olmayanlar), en son içerik eklenen önce
        $categories = array();
        if (!empty($selected_cats)) {
            $categories = get_categories(array('hide_empty' => false, 'include' => $selected_cats));
        }
        if (!empty($categories) && !is_wp_error($categories)) {
            $rows = array();
            foreach ($categories as $cat) {
                if (in_array($cat->term_id, $kullanilan_menu_obj_idleri)) continue;
                $q = new WP_Query(array('post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 6, 'cat' => $cat->term_id, 'orderby' => 'date', 'order' => 'DESC', 'ignore_sticky_posts' => true));
                if (!$q->posts) continue;
                $rows[] = array($cat, $q, get_post_time('U', false, $q->posts[0]));
            }
            usort($rows, function ($a, $b) { return $b[2] - $a[2]; });
            foreach ($rows as $r):
                list($cat, $q) = $r;
                $col = $sph_palette[$c_idx % count($sph_palette)]; $c_idx++;
                $count = max(intval($q->found_posts), intval($cat->count)); ?>
                <div class="sph2-box" style="--a:<?php echo $col[0]; ?>;--b:<?php echo $col[1]; ?>">
                    <div class="sph2-box-head">
                        <span class="sph2-box-icon" aria-hidden="true"><?php echo sp_get_kategori_ikonu($cat->name); ?></span>
                        <h3><?php echo esc_html($cat->name); ?><small><?php echo number_format_i18n($count); ?> içerik</small></h3>
                        <a class="sph2-more" href="<?php echo esc_url(get_category_link($cat->term_id)); ?>" aria-label="<?php echo esc_attr($cat->name); ?>: tüm içerikler">Tümü <?php echo $ico_go; ?></a>
                    </div>
                    <ul>
                        <?php foreach ($q->posts as $p) echo $sph_row($p, $col, false); ?>
                    </ul>
                </div>
                <?php
                $kategori_sayaci++; $infeed();
            endforeach;
        }
        ?>
        </div>
    </section>

    <!-- ÖĞRETMEN MASASI (Öğretmen Masası eklentisi): Yazılı Hazırla + Evrak Hazırla -->
    <?php $sph2_om = class_exists('OSC_Masa') ? OSC_Masa::anasayfa_bolumu() : ''; if ($sph2_om): echo $sph2_om; else: ?>
    <!-- YAZILI HAZIRLA: MEB senaryolarına uygun yazılı -->
    <?php $sph2_yz = class_exists('OSC_Yazili') ? OSC_Yazili::url() : home_url('/yazili-hazirla/'); ?>
    <?php if (class_exists('OSC_Yazili') && method_exists('OSC_Yazili', 'yayinda') && OSC_Yazili::yayinda()): // yayına alınana kadar gizli ?>
    <section class="sph2-sec" aria-labelledby="sph2-yz">
        <a class="sph2-yz" href="<?php echo esc_url($sph2_yz); ?>">
            <div class="sph2-yz-sol">
                <span class="sph2-yz-et"><i></i>YENİ · MEB 2026-2027 senaryolarına uygun</span>
                <h2 id="sph2-yz">Yazılınızı <em>bir dakikada</em> hazırlayın</h2>
                <p>Sınıfı, dersi ve senaryoyu seçin; sorular senaryodaki öğrenme çıktılarına göre otomatik gelsin. Beğenmediğinizi değiştirin, isterseniz kendiniz yazın; cevap anahtarlı Word veya PDF olarak indirin.</p>
                <div class="sph2-yz-ok">
                    <span>📚 5–12. sınıf, tüm dersler</span><span>🎯 Senaryoya birebir</span><span>✍️ Cevap boşluklu kâğıt</span><span>🔑 Cevap anahtarı</span>
                </div>
                <span class="sph2-yz-btn">Hemen yazılı hazırla <b>→</b></span>
            </div>
            <div class="sph2-yz-sag" aria-hidden="true">
                <div class="sph2-yz-kagit">
                    <div class="k1">T.C.<br><b>ATATÜRK ORTAOKULU</b><br><small>8. SINIF MATEMATİK 1. YAZILI</small></div>
                    <div class="k2"><span>1.</span><i style="width:88%"></i><em>10 P</em></div><div class="k3"></div><div class="k3"></div>
                    <div class="k2"><span>2.</span><i style="width:70%"></i><em>15 P</em></div><div class="k4"><s></s><s class="d"></s><s></s><s></s></div>
                    <div class="k2"><span>3.</span><i style="width:80%"></i><em>10 P</em></div><div class="k3"></div><div class="k3"></div>
                </div>
                <div class="sph2-yz-rozet">⚡ Senaryo 2 · 8 soru</div>
            </div>
        </a>
        <style>
        .sph2-yz{position:relative;display:grid;grid-template-columns:1.25fr 1fr;gap:28px;align-items:center;overflow:hidden;border-radius:28px;padding:36px 40px;color:#fff!important;text-decoration:none!important;background:radial-gradient(700px 260px at 90% -20%,rgba(192,38,211,.55),transparent 60%),radial-gradient(600px 300px at 0% 130%,rgba(14,165,233,.35),transparent 60%),linear-gradient(135deg,#0b1024,#1e1b4b 55%,#312e81);box-shadow:0 30px 60px -36px rgba(30,27,75,.9);transition:transform .25s,box-shadow .25s}
        .sph2-yz:hover{transform:translateY(-3px);box-shadow:0 40px 70px -36px rgba(30,27,75,.95)}
        .sph2-yz::before{content:"";position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.06) 1px,transparent 1px);background-size:32px 32px;-webkit-mask-image:radial-gradient(ellipse at 80% 0,#000 10%,transparent 70%);mask-image:radial-gradient(ellipse at 80% 0,#000 10%,transparent 70%)}
        .sph2-yz>div{position:relative}
        .sph2-yz-et{display:inline-flex;align-items:center;gap:8px;font-size:12.5px;font-weight:700;color:#e0e7ff;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);padding:6px 12px;border-radius:99px}
        .sph2-yz-et i{width:7px;height:7px;border-radius:50%;background:#34d399;box-shadow:0 0 0 4px rgba(52,211,153,.25)}
        .sph2-yz h2{color:#fff!important;font-size:clamp(26px,3.4vw,40px)!important;line-height:1.1!important;margin:14px 0 10px!important;letter-spacing:-.02em}
        .sph2-yz h2 em{font-style:normal;background:linear-gradient(90deg,#a5b4fc,#f0abfc,#fda4af);-webkit-background-clip:text;background-clip:text;color:transparent}
        .sph2-yz p{color:#cbd5e1;font-size:15.5px;line-height:1.6;margin:0 0 16px}
        .sph2-yz-ok{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:22px}
        .sph2-yz-ok span{font-size:12.5px;font-weight:700;color:#e0e7ff;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);border-radius:11px;padding:7px 11px}
        .sph2-yz-btn{display:inline-flex;align-items:center;gap:10px;background:#fff;color:#1e1b4b;font-weight:800;font-size:15.5px;padding:14px 22px;border-radius:15px;box-shadow:0 14px 28px -14px rgba(0,0,0,.6)}
        .sph2-yz-btn b{transition:transform .2s}.sph2-yz:hover .sph2-yz-btn b{transform:translateX(4px)}
        .sph2-yz-sag{display:flex;justify-content:center}
        .sph2-yz-kagit{width:min(300px,100%);background:#fff;border-radius:10px;padding:18px 18px 22px;transform:rotate(3deg);box-shadow:0 30px 50px -20px rgba(0,0,0,.6)}
        .sph2-yz-kagit .k1{border:1.5px solid #111;text-align:center;font-size:9px;line-height:1.4;color:#111;padding:5px;margin-bottom:12px}
        .sph2-yz-kagit .k1 b{font-size:10px}.sph2-yz-kagit .k1 small{font-size:8px;font-weight:700}
        .sph2-yz-kagit .k2{display:flex;align-items:center;gap:6px;margin:10px 0 6px}
        .sph2-yz-kagit .k2 span{font-size:10px;font-weight:800;color:#111}
        .sph2-yz-kagit .k2 i{height:7px;border-radius:4px;background:#cbd5e1}
        .sph2-yz-kagit .k2 em{margin-left:auto;font-style:normal;font-size:8.5px;font-weight:800;color:#111}
        .sph2-yz-kagit .k3{height:14px;border-bottom:1px dotted #94a3b8;margin-left:16px}
        .sph2-yz-kagit .k4{display:flex;gap:6px;margin-left:16px}
        .sph2-yz-kagit .k4 s{flex:1;height:14px;border:1px solid #cbd5e1;border-radius:4px}
        .sph2-yz-kagit .k4 s.d{background:#d1fae5;border-color:#34d399}
        .sph2-yz-rozet{position:absolute;bottom:-4px;left:6%;background:linear-gradient(135deg,#5b5bf6,#c026d3);color:#fff;font-size:12.5px;font-weight:800;padding:8px 13px;border-radius:12px;box-shadow:0 14px 26px -12px rgba(124,58,237,.9);transform:rotate(-4deg)}
        @media (max-width:820px){.sph2-yz{grid-template-columns:1fr;padding:26px 22px}.sph2-yz-sag{display:none}}
        </style>
    </section>
    <?php endif; ?>
    <?php endif; // öğretmen masası ?>

    <!-- ONLINE TEST BANDI -->
    <section class="sph2-sec sph2-band" aria-labelledby="sph2-t">
        <div>
            <h2 id="sph2-t">İndirmeden ekranda çöz</h2>
            <p>Online testlerde cevabını işaretle, sonucunu ve yanlışlarını hemen gör. İstersen testi tek tıkla yazdır.</p>
            <a class="sph2-band-cta" href="<?php echo esc_url($test_page_url); ?>">Online testlere git <?php echo $ico_go; ?></a>
        </div>
        <?php if ($top_tests): ?>
        <div>
            <ol>
                <?php foreach ($top_tests as $i => $row): list($p, $n) = $row; ?>
                <li><a href="<?php echo esc_url(get_permalink($p)); ?>"><span class="sph2-bubn"><?php echo $i + 1; ?></span><span class="t"><?php echo esc_html(get_the_title($p)); ?><small>Bu hafta <?php echo number_format_i18n($n); ?> kez çözüldü</small></span></a></li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php else: ?>
        <div class="sph2-band-steps">
            <div><i>1</i>Testi aç, soruları ekranda çöz.</div>
            <div><i>2</i>Bitir'e bas, puanını hemen gör.</div>
            <div><i>3</i>Yanlışlarını doğru cevaplarıyla incele.</div>
        </div>
        <?php endif; ?>
    </section>

    <!-- ÜYELİK: öğretmen ve öğrenci girişleri -->
    <section class="sph2-sec" aria-label="Üyelik">
        <?php if ($sph_giris): $sph_u = wp_get_current_user(); $sph_ogr = ($sph_rol === 'ogrenci'); ?>
        <div class="sph2-uye-hos">
            <span class="sph2-uye-ik" aria-hidden="true"><?php echo $sph_ogr ? '🎒' : '👩‍🏫'; ?></span>
            <div><h2>Tekrar hoş geldin, <?php echo esc_html($sph_u->display_name); ?></h2><p><?php echo $sph_ogr ? 'Testlerin, netlerin, ödevlerin ve konu karnen panelinde seni bekliyor.' : 'Canlı sınıf sınavı başlat, ödev linki gönder ya da yeni bir test hazırla.'; ?></p></div>
            <a href="<?php echo esc_url($sph_ogr ? $ogr_panel : $ogt_panel); ?>"><?php echo $sph_ogr ? 'Öğrenci panelim' : 'Öğretmen panelim'; ?> <?php echo $ico_go; ?></a>
            <a class="ik2" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Çıkış</a>
            <?php if ($sph_ogr): ?>
            <form class="sph2-kod" method="get" action="<?php echo esc_url(home_url('/')); ?>"><span>Öğretmeninden kod mu aldın? Canlı sınava ya da ödeve hemen katıl.</span><input name="osc_k" maxlength="8" placeholder="KOD" autocomplete="off" required aria-label="Sınav kodu"><button type="submit">Katıl</button></form>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="sph2-uye">
            <div class="sph2-uye-k ogt">
                <div class="sph2-uye-bas"><span class="sph2-uye-ik" aria-hidden="true">👩‍🏫</span><div><h2>Öğretmenler için</h2><small>Ücretsiz öğretmen hesabı</small></div></div>
                <ul>
                    <li>Sınıfta canlı sınav yap; öğrenciler kodla katılsın, sen anlık izle.</li>
                    <li>Son teslim tarihli ödev linki gönder, sonuçlar otomatik toplansın.</li>
                    <li>Kendi testini hazırla ya da PDF/Word dosyasından tek tıkla oluştur.</li>
                </ul>
                <div class="sph2-uye-btn">
                    <a class="ana" href="<?php echo esc_url(add_query_arg(array('kayit' => 1, 'rol' => 'ogretmen'), $uye_url)); ?>">Öğretmen olarak kaydol <?php echo $ico_go; ?></a>
                    <a class="ik2" href="<?php echo esc_url(add_query_arg(array('rol' => 'ogretmen', 'git' => $ogt_panel), $uye_url)); ?>">Giriş yap</a>
                </div>
            </div>
            <div class="sph2-uye-k ogr">
                <div class="sph2-uye-bas"><span class="sph2-uye-ik" aria-hidden="true">🎒</span><div><h2>Öğrenciler için</h2><small>Ücretsiz öğrenci hesabı</small></div></div>
                <ul>
                    <li>Çözdüğün testler, netlerin ve puanların tek yerde birikir.</li>
                    <li>Konu karnenle hangi konuya çalışman gerektiğini gör.</li>
                    <li>Öğretmeninin gönderdiği ödevleri ve canlı sınavları kaçırma.</li>
                </ul>
                <form class="sph2-kod" method="get" action="<?php echo esc_url(home_url('/')); ?>"><span>Öğretmeninden kod mu aldın?</span><input name="osc_k" maxlength="8" placeholder="KOD" autocomplete="off" required aria-label="Sınav kodu"><button type="submit">Katıl</button></form>
                <div class="sph2-uye-btn">
                    <a class="ana" href="<?php echo esc_url(add_query_arg(array('kayit' => 1, 'rol' => 'ogrenci'), $uye_url)); ?>">Öğrenci olarak kaydol <?php echo $ico_go; ?></a>
                    <a class="ik2" href="<?php echo esc_url(add_query_arg(array('rol' => 'ogrenci', 'git' => $ogr_panel), $uye_url)); ?>">Giriş yap</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <!-- HAKKINDA (SEO) -->
    <section class="sph2-about" aria-labelledby="sph2-a">
        <h2 id="sph2-a">Soruindir.net: eğitim materyalleri ve online test platformu</h2>
        <div class="sph2-about-text">
            <p><strong>Soruindir.net</strong>, öğretmenlerin, öğrencilerin ve velilerin aradıkları eğitim materyallerine hızlı ve güvenle ulaşabildiği bir platformdur. Güncel müfredata uygun <strong>deneme sınavları, yazılı soruları, yaprak testler, ders notları</strong> ve <strong>resfebe bulmacaları</strong> gibi yüzlerce içeriği bulabilir, cihazınıza indirebilirsiniz.</p>
            <p>İndirmek istemeyenler için <strong>online test çöz</strong> bölümünde testleri ekranda çözüp sonucunuzu anında görebilirsiniz. Kâğıt üzerinde çalışmayı sevenler dilediği testi tek tıkla <strong>yazdırabilir</strong>. Eğitimde aradığınız her şey tek çatı altında.</p>
        </div>
    </section>

</div></div>

<script>
(function () {
    var feed = document.getElementById('sph2-feed'); if (!feed) return;
    var tabs = document.querySelectorAll('.sph2-tabs [data-f]'), empty = document.getElementById('sph2-feed-empty');
    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            var f = t.getAttribute('data-f'), shown = 0;
            tabs.forEach(function (o) { o.setAttribute('aria-selected', o === t ? 'true' : 'false'); });
            feed.querySelectorAll('li').forEach(function (li) { var ok = f === 'all' || li.getAttribute('data-kind') === f; li.hidden = !ok; li.classList.toggle('is-feat', ok && shown === 0); if (ok) shown++; });
            if (empty) empty.hidden = shown > 0;
        });
    });
})();

function dosyayıIndir(){
    jQuery(function($){
        $.ajax({ url: "<?php echo esc_js(admin_url('admin-ajax.php')); ?>", type: 'POST', data: { 'post_id': <?php echo intval(get_the_ID()); ?>, action: 'indirme' },
            success: function(){ var el = $("#indirmeSayisi"); var val = parseInt(el.text()); if (!isNaN(val)) el.text(val + 1); } });
        $.ajax({ url: "<?php echo esc_js(admin_url('admin-ajax.php')); ?>", type: 'POST', data: { action: 'osc_track_download', post_id: <?php echo intval(get_the_ID()); ?> } });
    });
}
function tesekkurEt(){
    jQuery(function($){
        $.ajax({ url: "<?php echo esc_js(admin_url('admin-ajax.php')); ?>", type: 'POST', data: { 'post_id': <?php echo intval(get_the_ID()); ?>, action: 'tesekkur' },
            success: function(){ var el = $("#tesekkurSayisi"); var val = parseInt(el.text()); if (!isNaN(val)) el.text(val + 1); } });
    });
}
</script>

<?php get_footer(); ?>