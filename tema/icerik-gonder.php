<?php
/* Template Name: İçerik Gönderme Modern V2 */

/* ---------------------------------------------------------------------
 * İçerik gönder: dosya gönder ya da online test ekle
 * Gönderiler taslak olarak kaydedilir, yönetici onaylayınca yayımlanır.
 * Spam önlemleri: tek kullanımlık form anahtarı, gizli tuzak alanı, en az
 * 5 saniyelik doldurma süresi, IP başına saatte 5 gönderim, dosya türü ve
 * boyut denetimi, bağlantı yığını içeren açıklamaların reddi.
 * ------------------------------------------------------------------- */
if (!defined('ABSPATH')) exit;

if (!function_exists('sx_ig_dosya_yukle')) {
    function sx_ig_dosya_yukle($file, $post_id) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $izin = array('zip' => 'application/zip', 'rar' => 'application/x-rar-compressed', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'pdf' => 'application/pdf', 'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation');
        $r = wp_handle_upload($file, array('test_form' => false, 'mimes' => $izin));
        if (isset($r['error'])) return new WP_Error('yukleme', $r['error']);
        $att = wp_insert_attachment(array('post_mime_type' => $r['type'], 'post_title' => preg_replace('/\.[^.]+$/', '', basename($r['file'])), 'post_content' => '', 'post_status' => 'inherit', 'guid' => $r['url']), $r['file'], $post_id);
        if (is_wp_error($att) || !$att) return new WP_Error('yukleme', 'Dosya kaydedilemedi.');
        wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $r['file']));
        return (int) $att;
    }
}

$sx_ig_mb      = 2;   // en büyük dosya boyutu (MB)
$sx_ig_durum   = '';  // basari | hata
$sx_ig_mesaj   = '';
$sx_ig_ip      = isset($_SERVER['REMOTE_ADDR']) ? preg_replace('/[^0-9a-f:.]/i', '', $_SERVER['REMOTE_ADDR']) : '';
$sx_ig_sayac   = 'sx_ig_' . md5($sx_ig_ip);
$sx_ig_kullanici = wp_get_current_user();
$sx_ig_mod     = (isset($_GET['tur']) && $_GET['tur'] === 'test') ? 'test' : 'dosya';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sx_ig'])) {
    $baslik   = sanitize_text_field(wp_unslash($_POST['baslik'] ?? ''));
    $kategori = (int) ($_POST['kategori'] ?? 0);
    $icerik   = wp_kses_post(wp_unslash($_POST['icerik'] ?? ''));
    $gonderen = sanitize_text_field(wp_unslash($_POST['gonderen'] ?? ''));
    $zaman    = (int) ($_POST['sx_ig_z'] ?? 0);
    $deneme   = (int) get_transient($sx_ig_sayac);
    if (!isset($_POST['sx_ig_n']) || !wp_verify_nonce($_POST['sx_ig_n'], 'sx_ig_gonder')) {
        $sx_ig_durum = 'hata'; $sx_ig_mesaj = 'Formun süresi doldu. Sayfayı yenileyip tekrar deneyin.';
    } elseif (!empty($_POST['web_adresi']) || ($zaman && time() - $zaman < 5)) {
        $sx_ig_durum = 'hata'; $sx_ig_mesaj = 'Gönderiminiz otomatik gönderim gibi göründüğü için alınmadı. Lütfen formu yeniden doldurun.';
    } elseif ($deneme >= 5 && !current_user_can('edit_posts')) {
        $sx_ig_durum = 'hata'; $sx_ig_mesaj = 'Kısa sürede çok fazla gönderim yaptınız. Lütfen bir saat sonra tekrar deneyin.';
    } elseif (mb_strlen($baslik) < 6 || !$kategori || !term_exists($kategori, 'category')) {
        $sx_ig_durum = 'hata'; $sx_ig_mesaj = 'Başlık en az 6 karakter olmalı ve bir kategori seçilmelidir.';
    } elseif (preg_match_all('#https?://#i', $icerik) > 2) {
        $sx_ig_durum = 'hata'; $sx_ig_mesaj = 'Açıklamada en fazla 2 bağlantı olabilir.';
    } elseif (empty($_FILES['dosya']['name'])) {
        $sx_ig_durum = 'hata'; $sx_ig_mesaj = 'Lütfen göndereceğiniz dosyayı seçin.';
    } elseif ((int) $_FILES['dosya']['size'] > $sx_ig_mb * 1024 * 1024) {
        $sx_ig_durum = 'hata'; $sx_ig_mesaj = 'Dosya en fazla ' . $sx_ig_mb . ' MB olabilir.';
    } else {
        $post_id = wp_insert_post(array('post_title' => $baslik, 'post_content' => $icerik ? $icerik : $baslik, 'post_category' => array($kategori), 'post_status' => 'draft', 'post_author' => $sx_ig_kullanici->ID ? $sx_ig_kullanici->ID : 0), true);
        if (is_wp_error($post_id)) {
            $sx_ig_durum = 'hata'; $sx_ig_mesaj = 'İçerik kaydedilemedi. Lütfen tekrar deneyin.';
        } else {
            $att = sx_ig_dosya_yukle($_FILES['dosya'], $post_id);
            if (is_wp_error($att)) {
                wp_delete_post($post_id, true);
                $sx_ig_durum = 'hata'; $sx_ig_mesaj = 'Dosya yüklenemedi: ' . $att->get_error_message() . ' (İzin verilen türler: ZIP, RAR, Word, Excel, PowerPoint, PDF)';
            } else {
                add_post_meta($post_id, 'gonderen', $gonderen ? $gonderen : ($sx_ig_kullanici->ID ? $sx_ig_kullanici->display_name : ''), true);
                add_post_meta($post_id, 'indirme_sayisi', 0, true);
                add_post_meta($post_id, 'tesekkur_sayisi', 0, true);
                add_post_meta($post_id, 'kategori_turu', $kategori, true);
                add_post_meta($post_id, 'boyut', $att, true);
                add_post_meta($post_id, 'gonderen_ip', wp_hash($sx_ig_ip), true);
                set_transient($sx_ig_sayac, $deneme + 1, HOUR_IN_SECONDS);
                $sx_ig_durum = 'basari';
                $sx_ig_mesaj = 'Teşekkürler! İçeriğiniz alındı. İncelendikten sonra sitede yayımlanacak.';
            }
        }
    }
}

// kategori listesi (üst + alt)
$sx_ig_kat = array();
foreach (get_categories(array('orderby' => 'name', 'parent' => 0, 'hide_empty' => 0)) as $c) {
    if ($c->slug === 'uncategorized') continue;
    $sx_ig_kat[] = array($c->term_id, $c->name, 0);
    foreach (get_categories(array('parent' => $c->term_id, 'hide_empty' => 0, 'orderby' => 'name')) as $s) $sx_ig_kat[] = array($s->term_id, $s->name, 1);
}
$sx_ig_son = get_posts(array('numberposts' => 5, 'post_status' => 'publish'));
$sx_ig_reklam = get_option('sp_gp_ad_sidebar', '');
$sx_ig_test_var = shortcode_exists('osc_ogretmen_paneli');
get_header();
?>
<style>
.sxig{--i:#13304F;--i2:#5B7189;--c:#E3EBF3;--m:#2F80ED;--y:#22A06B;--mor:#7C3AED;font-family:Figtree,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--i);max-width:1180px;margin:0 auto;padding:22px 16px 60px}
.sxig *{box-sizing:border-box}
.sxig a{text-decoration:none}
.sxig-hero{position:relative;overflow:hidden;border-radius:28px;padding:38px 28px 30px;background:linear-gradient(135deg,#EAF4FC 0%,#F5F9FE 55%,#F1ECFE 100%);border:1px solid #DCEAF7}
.sxig-hero:after{content:"";position:absolute;right:-70px;top:-70px;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,rgba(124,58,237,.12),transparent 70%)}
.sxig-ust{display:inline-flex;gap:6px;align-items:center;padding:5px 12px;border-radius:99px;background:#fff;border:1px solid #DCEAF7;font-size:12.5px;font-weight:700;color:var(--m)}
.sxig h1{margin:12px 0 0 !important;font-size:clamp(28px,3.8vw,42px);line-height:1.1;letter-spacing:-.025em;font-weight:800;color:var(--i)}
.sxig h1 span{background:linear-gradient(135deg,#2F80ED,#7C3AED);-webkit-background-clip:text;background-clip:text;color:transparent}
.sxig-hero p{margin:10px 0 0 !important;max-width:640px;color:#46607A;font-size:16.5px;line-height:1.6;position:relative}
.sxig-sec{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:22px;max-width:720px;position:relative;z-index:1}
.sxig-sec button{display:flex;align-items:center;gap:14px;text-align:left;padding:16px 18px;border-radius:20px;border:2px solid #DCEAF7;background:#fff;cursor:pointer;font:inherit;color:var(--i);transition:all .18s;box-shadow:0 14px 30px -26px rgba(19,48,79,.6)}
.sxig-sec button:hover{transform:translateY(-2px);border-color:#C9DCF0}
.sxig-sec button i{flex:0 0 48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-style:normal;font-size:24px;background:#EAF2FD}
.sxig-sec button.test i{background:#F1ECFE}
.sxig-sec b{display:block;font-size:16.5px}.sxig-sec small{display:block;font-size:13px;color:var(--i2);margin-top:2px;line-height:1.4}
.sxig-sec button.on{border-color:var(--m);background:#F4F9FF;box-shadow:0 0 0 4px rgba(47,128,237,.12)}
.sxig-sec button.test.on{border-color:var(--mor);background:#FAF7FF;box-shadow:0 0 0 4px rgba(124,58,237,.12)}
.sxig-govde{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:20px;margin-top:22px;align-items:start}
.sxig-kart{background:#fff;border:1px solid var(--c);border-radius:24px;padding:26px;box-shadow:0 18px 40px -34px rgba(19,48,79,.6)}
.sxig-kart h2{margin:0 0 4px !important;font-size:21px;font-weight:800;color:var(--i)}
.sxig-kart .alt{margin:0 0 20px !important;color:var(--i2);font-size:14.5px}
.sxig-alan{margin-bottom:18px}
.sxig-alan>label{display:block;font-weight:700;font-size:14px;margin-bottom:7px;color:#24405E}
.sxig-alan>label small{font-weight:500;color:#8AA2BA}
.sxig .sxig-in{width:100%;padding:13px 14px !important;border:1.5px solid #DCE6F0 !important;border-radius:14px !important;font:inherit;font-size:15.5px;background:#FAFCFE !important;color:var(--i);outline:0;transition:border-color .15s,box-shadow .15s;box-shadow:none !important;margin:0 !important}
.sxig .sxig-in:focus{border-color:var(--m) !important;background:#fff !important;box-shadow:0 0 0 4px rgba(47,128,237,.12) !important}
.sxig textarea.sxig-in{min-height:110px;resize:vertical}
.sxig-iki{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.sxig-kat{position:relative}
.sxig-kat-btn{display:flex;justify-content:space-between;align-items:center;gap:10px;cursor:pointer;user-select:none}
.sxig-kat-btn.secili{color:var(--i);font-weight:700}
.sxig-kat-btn:not(.secili){color:#8AA2BA}
.sxig-kat-m{display:none;position:absolute;z-index:40;left:0;right:0;top:calc(100% + 6px);background:#fff;border:1px solid var(--c);border-radius:16px;box-shadow:0 24px 50px -24px rgba(19,48,79,.5);overflow:hidden}
.sxig-kat.acik .sxig-kat-m{display:block}
.sxig-kat-m input{width:100%;border:0 !important;border-bottom:1px solid var(--c) !important;padding:13px 14px !important;font:inherit;font-size:15px;outline:0;border-radius:0 !important;margin:0 !important}
.sxig-kat-m ul{list-style:none;margin:0;padding:6px;max-height:280px;overflow:auto}
.sxig-kat-m li{padding:9px 12px;border-radius:10px;cursor:pointer;font-size:14.5px;color:#24405E}
.sxig-kat-m li:hover,.sxig-kat-m li.on{background:#F0F6FD;color:var(--m)}
.sxig-kat-m li.ana{font-weight:700}
.sxig-kat-m li.alt{padding-left:28px;color:#46607A}.sxig-kat-m li.alt:before{content:"↳ ";color:#C3D3E4}
.sxig-yukle{position:relative;display:flex;align-items:center;gap:16px;padding:22px;border:2px dashed #C9DCF0;border-radius:18px;background:#F7FAFD;cursor:pointer;transition:all .18s}
.sxig-yukle:hover,.sxig-yukle.ust{border-color:var(--m);background:#F0F6FD}
.sxig-yukle input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.sxig-yukle .ik{flex:0 0 52px;height:52px;border-radius:14px;background:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;box-shadow:0 8px 18px -12px rgba(19,48,79,.5)}
.sxig-yukle b{display:block;font-size:15.5px}.sxig-yukle small{display:block;color:var(--i2);font-size:13px;margin-top:2px}
.sxig-yukle.dolu{border-style:solid;border-color:var(--y);background:#F2FBF6}
.sxig .sxig-gonder{width:100%;display:flex;align-items:center;justify-content:center;gap:10px;margin-top:6px;padding:16px;border:0;border-radius:16px;background:linear-gradient(135deg,#2F80ED,#1E5AA8);color:#fff;font:inherit;font-weight:800;font-size:16.5px;cursor:pointer;box-shadow:0 16px 30px -16px rgba(47,128,237,.9);transition:transform .15s}
.sxig .sxig-gonder:hover{transform:translateY(-1px)}
.sxig .sxig-gonder:disabled{opacity:.7;cursor:wait}
.sxig-not{display:flex;gap:10px;align-items:flex-start;padding:14px 16px;border-radius:16px;margin-bottom:18px;font-weight:600;font-size:15px;line-height:1.5}
.sxig-not.basari{background:#E9F7EF;color:#15603A;border:1px solid #BFE8CF}
.sxig-not.hata{background:#FEF0F0;color:#9B1C1C;border:1px solid #F8C9C9}
.sxig-tuzak{position:absolute !important;left:-9999px !important;width:1px;height:1px;overflow:hidden}
.sxig-yan{display:flex;flex-direction:column;gap:14px}
.sxig-yk{background:#fff;border:1px solid var(--c);border-radius:22px;padding:18px}
.sxig-yk h3{margin:0 0 10px !important;font-size:16px;display:flex;align-items:center;gap:8px;color:var(--i)}
.sxig-kural{list-style:none;margin:0;padding:0;display:grid;gap:9px}
.sxig-kural li{display:flex;gap:10px;font-size:14px;line-height:1.5;color:#334E68}
.sxig-kural li i{flex:0 0 26px;height:26px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-style:normal;font-size:14px;background:#F0F6FD}
.sxig-hizli{list-style:none;margin:0;padding:0;display:grid;gap:6px}
.sxig .sxig-hizli a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;background:#F7FAFD;color:#24405E;font-weight:600;font-size:14.5px}
.sxig .sxig-hizli a:hover{background:#EEF4FB;color:var(--m)}
.sxig-son{list-style:none;margin:0;padding:0;display:grid;gap:4px}
.sxig .sxig-son a{display:block;padding:8px 4px;border-bottom:1px solid #EEF3F8;color:#24405E;font-weight:600;font-size:14px;line-height:1.4}
.sxig .sxig-son li:last-child a{border-bottom:0}
.sxig-test-bas{display:flex;gap:14px;align-items:center;padding:16px 18px;border-radius:18px;background:linear-gradient(135deg,#F5F0FF,#EEF4FF);border:1px solid #E4DAFB;margin-bottom:16px}
.sxig-test-bas i{flex:0 0 46px;height:46px;border-radius:14px;background:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;font-style:normal}
.sxig-test-bas b{display:block;font-size:16px}.sxig-test-bas small{display:block;color:var(--i2);font-size:13.5px;line-height:1.5;margin-top:2px}
.sxig-panel[hidden]{display:none}
.sxig-test .osc-pn{max-width:none !important;margin:0 !important}
@media(max-width:960px){.sxig-govde{grid-template-columns:1fr}.sxig-yan{display:grid;grid-template-columns:1fr 1fr}}
@media(max-width:640px){.sxig-sec,.sxig-iki,.sxig-yan{grid-template-columns:1fr}.sxig-hero{padding:28px 18px 22px;border-radius:22px}.sxig-kart{padding:18px;border-radius:20px}}
</style>

<div class="sxig">
  <section class="sxig-hero">
    <span class="sxig-ust">💌 Katkın binlerce öğretmen ve öğrenciye ulaşır</span>
    <h1>İçerik <span>gönder</span></h1>
    <p>Hazırladığın yazılı, çalışma kâğıdı, plan ya da evrakı dosya olarak gönder; veya soruları tek tek yazarak doğrudan online test oluştur. Gönderilerin incelendikten sonra yayımlanır.</p>
    <div class="sxig-sec" role="tablist" aria-label="Gönderim türü">
      <button type="button" role="tab" class="dosya<?php echo $sx_ig_mod === 'dosya' ? ' on' : ''; ?>" data-mod="dosya" aria-selected="<?php echo $sx_ig_mod === 'dosya' ? 'true' : 'false'; ?>"><i>📁</i><span><b>Dosya gönder</b><small>Word, PDF, Excel ya da ZIP</small></span></button>
      <?php if ($sx_ig_test_var): ?>
      <button type="button" role="tab" class="test<?php echo $sx_ig_mod === 'test' ? ' on' : ''; ?>" data-mod="test" aria-selected="<?php echo $sx_ig_mod === 'test' ? 'true' : 'false'; ?>"><i>✅</i><span><b>Online test ekle</b><small>Soruları yaz ya da dosyadan aktar</small></span></button>
      <?php endif; ?>
    </div>
  </section>

  <div class="sxig-govde">
    <div>
      <!-- DOSYA GÖNDER -->
      <section class="sxig-panel sxig-kart" id="sxig-dosya"<?php echo $sx_ig_mod === 'dosya' ? '' : ' hidden'; ?>>
        <h2>Dosya gönder</h2>
        <p class="alt">Bilgileri doldur, dosyanı seç ve gönder. Yayımlanınca adınla birlikte görünür.</p>
        <?php if ($sx_ig_durum): ?><div class="sxig-not <?php echo esc_attr($sx_ig_durum); ?>"><span><?php echo $sx_ig_durum === 'basari' ? '🎉' : '⚠️'; ?></span><span><?php echo esc_html($sx_ig_mesaj); ?></span></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" id="sxig-form" novalidate>
          <?php wp_nonce_field('sx_ig_gonder', 'sx_ig_n'); ?>
          <input type="hidden" name="sx_ig" value="1"><input type="hidden" name="sx_ig_z" value="<?php echo time(); ?>">
          <div class="sxig-tuzak" aria-hidden="true"><label>Web adresiniz<input type="text" name="web_adresi" tabindex="-1" autocomplete="off"></label></div>
          <div class="sxig-alan"><label for="sxig-baslik">İçerik başlığı</label><input class="sxig-in" id="sxig-baslik" name="baslik" maxlength="160" required placeholder="Örn: 10. Sınıf Biyoloji 1. Dönem 1. Yazılı Soruları" value="<?php echo $sx_ig_durum === 'hata' ? esc_attr(wp_unslash($_POST['baslik'] ?? '')) : ''; ?>"></div>
          <div class="sxig-alan"><label>Kategori</label>
            <input type="hidden" name="kategori" id="sxig-kat-id" value="<?php echo $sx_ig_durum === 'hata' ? (int) ($_POST['kategori'] ?? 0) : ''; ?>">
            <div class="sxig-kat" id="sxig-kat">
              <div class="sxig-in sxig-kat-btn" id="sxig-kat-btn" tabindex="0" role="button" aria-haspopup="listbox"><span id="sxig-kat-ad">Kategori seçin…</span><span aria-hidden="true">▾</span></div>
              <div class="sxig-kat-m"><input type="search" id="sxig-kat-ara" placeholder="Kategori ara: matematik, yazılı, plan…" autocomplete="off"><ul role="listbox" id="sxig-kat-l">
                <?php foreach ($sx_ig_kat as $k) echo '<li role="option" class="' . ($k[2] ? 'alt' : 'ana') . '" data-id="' . (int) $k[0] . '">' . esc_html($k[1]) . '</li>'; ?>
              </ul></div>
            </div>
          </div>
          <div class="sxig-alan"><label for="sxig-ac">Açıklama <small>(isteğe bağlı)</small></label><textarea class="sxig-in" id="sxig-ac" name="icerik" maxlength="3000" placeholder="Dosyanın içeriği: kaç soru var, cevap anahtarı var mı, hangi kazanımları kapsıyor…"><?php echo $sx_ig_durum === 'hata' ? esc_textarea(wp_unslash($_POST['icerik'] ?? '')) : ''; ?></textarea></div>
          <div class="sxig-alan"><label>Dosya</label>
            <div class="sxig-yukle" id="sxig-yukle"><input type="file" name="dosya" id="sxig-dosya-in" accept=".zip,.rar,.doc,.docx,.pdf,.xls,.xlsx,.ppt,.pptx"><span class="ik">📎</span><span><b id="sxig-dosya-ad">Dosyayı sürükleyip bırakın ya da seçin</b><small id="sxig-dosya-bilgi">Word, PDF, Excel, PowerPoint, ZIP veya RAR · en fazla <?php echo (int) $sx_ig_mb; ?> MB</small></span></div>
          </div>
          <div class="sxig-alan"><label for="sxig-ad">Adınız soyadınız <small>(isteğe bağlı)</small></label><input class="sxig-in" id="sxig-ad" name="gonderen" maxlength="80" placeholder="Yayımlanınca gönderen olarak görünür" value="<?php echo esc_attr($sx_ig_kullanici->ID ? $sx_ig_kullanici->display_name : ''); ?>"></div>
          <button type="submit" class="sxig-gonder" id="sxig-gonder">İçeriği gönder <span aria-hidden="true">→</span></button>
        </form>
      </section>

      <?php if ($sx_ig_test_var): ?>
      <!-- ONLINE TEST EKLE -->
      <section class="sxig-panel sxig-test" id="sxig-test"<?php echo $sx_ig_mod === 'test' ? '' : ' hidden'; ?>>
        <div class="sxig-test-bas"><i>✅</i><span><b>Online test ekle</b><small>Soruları tek tek yazabilir ya da Word/PDF dosyasından otomatik aktarabilirsiniz. Test onaylanınca sitede yayımlanır; bu sürede kendi sınıfınızda kullanabilirsiniz.</small></span></div>
        <script>if (document.getElementById('sxig-test') && !document.getElementById('sxig-test').hidden && !/^#test\//.test(location.hash)) history.replaceState(null, '', location.pathname + location.search + '#test/yeni');</script>
        <?php echo do_shortcode('[osc_ogretmen_paneli]'); ?>
      </section>
      <?php endif; ?>
    </div>

    <aside class="sxig-yan">
      <div class="sxig-yk"><h3>📌 Göndermeden önce</h3>
        <ul class="sxig-kural">
          <li><i>✏️</i><span>Başlığa sınıf, ders ve dönemi yazın (ör. <b>10. Sınıf Biyoloji 1. Dönem 1. Yazılı</b>).</span></li>
          <li><i>📄</i><span>Yazılıları PDF yerine <b>Word</b> olarak gönderin; birden çok dosyayı <b>ZIP</b> yapın.</span></li>
          <li><i>⚖️</i><span>Dosya en fazla <b><?php echo (int) $sx_ig_mb; ?> MB</b> olmalı.</span></li>
          <li><i>©️</i><span>Telif hakkı başkasına ait (yayınevi, başka site) içerik göndermeyin.</span></li>
          <li><i>🔒</i><span>Öğrenci adı, numara gibi kişisel bilgileri dosyadan silin.</span></li>
        </ul>
      </div>
      <?php if (!empty($sx_ig_reklam)): ?><div class="sxig-yk" style="text-align:center"><span style="display:block;font-size:10px;color:#94a3b8;margin-bottom:8px;letter-spacing:1px">SPONSORLU</span><div style="overflow:hidden;display:flex;justify-content:center"><?php echo stripslashes($sx_ig_reklam); ?></div></div><?php endif; ?>
      <div class="sxig-yk"><h3>⚡ Hızlı erişim</h3>
        <ul class="sxig-hizli">
          <li><a href="<?php echo esc_url(home_url('/test-coz/')); ?>"><span>✍️</span>Online test çöz</a></li>
          <li><a href="<?php echo esc_url(class_exists('OSC_Sayfalar') ? OSC_Sayfalar::url('kategoriler') : home_url('/kategoriler/')); ?>"><span>📚</span>Tüm kategoriler</a></li>
          <li><a href="<?php echo esc_url(home_url('/category/yazili-sorulari/')); ?>"><span>📝</span>Yazılı soruları</a></li>
          <?php if (class_exists('OSC_Masa')): ?><li><a href="<?php echo esc_url(OSC_Masa::url()); ?>"><span>🧑‍🏫</span>Öğretmen Masası</a></li><?php endif; ?>
        </ul>
      </div>
      <?php if ($sx_ig_son): ?><div class="sxig-yk"><h3>🆕 Son eklenenler</h3><ul class="sxig-son"><?php foreach ($sx_ig_son as $p) echo '<li><a href="' . esc_url(get_permalink($p)) . '">' . esc_html(get_the_title($p)) . '</a></li>'; ?></ul></div><?php endif; ?>
    </aside>
  </div>
</div>

<script>
(function () {
  // sekmeler: dosya / test
  var bt = document.querySelectorAll('.sxig-sec [data-mod]');
  function ac(m) {
    bt.forEach(function (b) { var on = b.dataset.mod === m; b.classList.toggle('on', on); b.setAttribute('aria-selected', on ? 'true' : 'false'); });
    var d = document.getElementById('sxig-dosya'), t = document.getElementById('sxig-test');
    if (d) d.hidden = m !== 'dosya'; if (t) t.hidden = m !== 'test';
    var u = new URL(location.href); if (m === 'test') u.searchParams.set('tur', 'test'); else u.searchParams.delete('tur');
    history.replaceState(null, '', u.pathname + u.search + (m === 'test' ? (/^#test\//.test(location.hash) ? location.hash : '#test/yeni') : ''));
    if (m === 'test') window.dispatchEvent(new HashChangeEvent('hashchange'));
  }
  bt.forEach(function (b) { b.onclick = function () { ac(b.dataset.mod); }; });

  // kategori seçici
  var kat = document.getElementById('sxig-kat'), btn = document.getElementById('sxig-kat-btn'), ad = document.getElementById('sxig-kat-ad'), gz = document.getElementById('sxig-kat-id'), ara = document.getElementById('sxig-kat-ara'), lis = document.querySelectorAll('#sxig-kat-l li');
  function sec(li) { gz.value = li.dataset.id; ad.textContent = li.textContent; btn.classList.add('secili'); lis.forEach(function (x) { x.classList.toggle('on', x === li); }); kat.classList.remove('acik'); }
  if (kat) {
    if (gz.value) lis.forEach(function (li) { if (li.dataset.id === gz.value) sec(li); });
    btn.onclick = function (e) { e.stopPropagation(); kat.classList.toggle('acik'); if (kat.classList.contains('acik')) setTimeout(function () { ara.focus(); }, 30); };
    btn.onkeydown = function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); btn.click(); } };
    document.addEventListener('click', function (e) { if (!kat.contains(e.target)) kat.classList.remove('acik'); });
    lis.forEach(function (li) { li.onclick = function () { sec(li); }; });
    var tr = function (s) { return s.toLocaleLowerCase('tr-TR'); };
    ara.oninput = function () { var v = tr(ara.value.trim()); lis.forEach(function (li) { li.style.display = !v || tr(li.textContent).indexOf(v) > -1 ? '' : 'none'; }); };
  }

  // dosya
  var fi = document.getElementById('sxig-dosya-in'), yk = document.getElementById('sxig-yukle'), MB = <?php echo (int) $sx_ig_mb; ?>;
  if (fi) {
    fi.onchange = function () {
      var f = fi.files && fi.files[0];
      if (!f) { yk.classList.remove('dolu'); document.getElementById('sxig-dosya-ad').textContent = 'Dosyayı sürükleyip bırakın ya da seçin'; return; }
      document.getElementById('sxig-dosya-ad').textContent = f.name; yk.classList.add('dolu');
      document.getElementById('sxig-dosya-bilgi').textContent = (f.size / 1048576).toFixed(2) + ' MB' + (f.size > MB * 1048576 ? ' · ⚠️ ' + MB + ' MB sınırını aşıyor' : ' · hazır');
    };
    ['dragenter', 'dragover'].forEach(function (e) { yk.addEventListener(e, function () { yk.classList.add('ust'); }); });
    ['dragleave', 'drop'].forEach(function (e) { yk.addEventListener(e, function () { yk.classList.remove('ust'); }); });
  }

  // gönderim denetimi
  var fo = document.getElementById('sxig-form');
  if (fo) fo.onsubmit = function (e) {
    var h = '';
    if (document.getElementById('sxig-baslik').value.trim().length < 6) h = 'Başlık en az 6 karakter olmalı.';
    else if (!gz.value) { h = 'Lütfen bir kategori seçin.'; kat.classList.add('acik'); }
    else if (!fi.files || !fi.files[0]) h = 'Lütfen göndereceğiniz dosyayı seçin.';
    else if (fi.files[0].size > MB * 1048576) h = 'Dosya en fazla ' + MB + ' MB olabilir.';
    if (h) { e.preventDefault(); var n = fo.parentNode.querySelector('.sxig-not'); if (!n) { n = document.createElement('div'); fo.parentNode.insertBefore(n, fo); } n.className = 'sxig-not hata'; n.innerHTML = '<span>⚠️</span><span></span>'; n.lastChild.textContent = h; n.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
    var g = document.getElementById('sxig-gonder'); g.disabled = true; g.textContent = 'Gönderiliyor…';
  };
})();
</script>
<?php get_footer(); ?>
