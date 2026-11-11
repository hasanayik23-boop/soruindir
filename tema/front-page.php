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
</style>

<div class="sph2"><div class="sph2-wrap">

    <?php if (!empty($ad_home_top)): ?>
    <div class="sph2-ad"><div><?php echo stripslashes($ad_home_top); ?></div></div>
    <?php endif; ?>

    <!-- GİRİŞ: arama + sınıf seçimi -->
    <section class="sph2-hero" aria-labelledby="sph2-h">
        <div>
            <h1 id="sph2-h">Soruindir.net'te ne aramak istersin?</h1>
            <p class="sph2-lead">Deneme sınavı, yazılı soruları, yaprak test, plan ve evrak. Ara, indir ya da ekranda çöz.</p>
            <form role="search" method="get" class="sph2-search" action="<?php echo esc_url(home_url('/')); ?>">
                <?php echo $ico_search; ?>
                <label for="sph2-s" style="position:absolute;left:-9999px;">Sitede ara</label>
                <input type="search" id="sph2-s" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Örn: 8. sınıf matematik deneme" autocomplete="off" required>
                <button type="submit">Ara</button>
            </form>
            <div class="sph2-facts">
                <?php if ($total_posts > 0): ?><span><strong><?php echo number_format_i18n($total_posts); ?></strong>içerik</span><?php endif; ?>
                <span><strong>12</strong>sınıf düzeyi</span>
                <span><strong>Ücretsiz</strong>indir ve yazdır</span>
            </div>
        </div>

        <div class="sph2-sheet">
            <h2>Sınıfını işaretle</h2>
            <p>O sınıfa ait tüm dosya ve testler açılır.</p>
            <?php foreach ($grades as $label => $nums): ?>
            <div class="sph2-grade-row">
                <span><?php echo esc_html($label); ?></span>
                <?php foreach ($nums as $n): ?>
                <a class="sph2-bub" href="<?php echo esc_url(home_url('/category/' . $n . '-sinif')); ?>" aria-label="<?php echo esc_attr($n . '. sınıf'); ?>"><b><?php echo $n; ?></b></a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <a class="sph2-test-cta" href="<?php echo esc_url($test_page_url); ?>">
                <span><strong>Online test çöz</strong><small>İndirmeden ekranda çöz, sonucunu hemen gör</small></span>
                <?php echo $ico_go; ?>
            </a>
        </div>
    </section>

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

    <!-- SON EKLENENLER -->
    <section class="sph2-sec" aria-labelledby="sph2-l">
        <div class="sph2-head">
            <h2 id="sph2-l">Son eklenenler</h2>
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

    <!-- KATEGORİLER -->
    <section class="sph2-sec" aria-label="Kategoriler">
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