<?php
/**
 * Soruindir.net yazı şablonu (v2)
 * Dosya yazılarında download2.php indirme kutusunu, test yazılarında testi gösterir.
 * Reklam: sp_gp_ad_sidebar (yan sütun)
 */
if (!defined('ABSPATH')) exit;

// =====================================================================
// OTOMATİK AÇIKLAMA: başlıktan ve dosya bilgilerinden doğru, sayfaya özel bir özet üretir.
// - Rank Math'te elle açıklama yazılmamışsa meta açıklama (Google sonuç metni) olarak kullanılır.
// - Yazının kendi metni kısaysa (80 kelimeden az) sayfada "kısa bilgi" paragrafı olarak gösterilir.
// =====================================================================
if (!function_exists('sxs_auto_facts')) {
    function sxs_lower($s) { $s = str_replace(array('I', 'İ'), array('ı', 'i'), (string) $s); return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s); }

    function sxs_parse_kb($raw) {
        $s = sxs_lower(trim((string) $raw)); if ($s === '') return 0;
        $mult = 1;
        if (preg_match('/\bgb\b/u', $s)) $mult = 1048576; elseif (preg_match('/\bmb\b/u', $s)) $mult = 1024;
        $n = preg_replace('/[^0-9.,]/', '', $s); if ($n === '') return 0;
        if (strpos($n, '.') !== false && strpos($n, ',') !== false) { $n = str_replace(',', '.', str_replace('.', '', $n)); }
        elseif (strpos($n, '.') !== false) { if (preg_match('/^\d{1,3}(\.\d{3})+$/', $n)) $n = str_replace('.', '', $n); }
        elseif (strpos($n, ',') !== false) { $n = preg_match('/^\d{1,3}(,\d{3})+$/', $n) ? str_replace(',', '', $n) : str_replace(',', '.', $n); }
        return floatval($n) * $mult;
    }

    // Başlık / kategori metninden ders adını bulur (kısaltmalar ve büyük harf yazımlar dahil)
    // Türkçe "Başlık Biçimi": tamamı büyük harfle yazılmış başlıklardaki ifadeleri düzeltir (ADABI MUAŞERET -> Adabı Muaşeret)
    function sxs_title_case($s) {
        $up = function_exists('mb_strtoupper') ? mb_strtoupper(str_replace(array('i', 'ı'), array('İ', 'I'), $s), 'UTF-8') : strtoupper($s);
        if ($s !== $up) {                                            // normal yazılmış: yalnızca "Bİlgisi" gibi yanlış büyük harfleri düzelt
            return preg_replace_callback('/\p{L}[\p{L}\.]*/u', function ($m) {
                $w = $m[0]; $rest = mb_substr($w, 1, null, 'UTF-8');
                $rest_up = mb_strtoupper(str_replace(array('i', 'ı'), array('İ', 'I'), $rest), 'UTF-8');
                if ($rest === '' || $rest === $rest_up) return $w;   // "T.C.", "BEP" gibi kısaltmalar
                if (sxs_lower($rest) === $rest) return $w;           // zaten düzgün
                return mb_substr($w, 0, 1, 'UTF-8') . sxs_lower($rest);
            }, $s);
        }
        $small = array('ve', 'ile', 'veya', 'ya', 'da', 'de', 'için');
        $out = array();
        foreach (preg_split('/(\s+)/u', sxs_lower($s), -1, PREG_SPLIT_DELIM_CAPTURE) as $i => $w) {
            if ($w === '' || preg_match('/^\s+$/u', $w) || ($i > 0 && in_array($w, $small, true))) { $out[] = $w; continue; }
            $first = mb_substr($w, 0, 1, 'UTF-8'); $first = $first === 'i' ? 'İ' : ($first === 'ı' ? 'I' : mb_strtoupper($first, 'UTF-8'));
            $out[] = $first . mb_substr($w, 1, null, 'UTF-8');
        }
        return implode('', $out);
    }

    // Yalnızca kısaltmaları açmak için küçük bir sözlük (TDE -> Türk Dili ve Edebiyatı). Ders adı bundan değil, başlığın kendisinden okunur.
    function sxs_expand_abbr($phrase_low) {
        $w0 = '(?<![\p{L}])'; $w1 = '(?![\p{L}])';
        $map = array(
            $w0 . 'tde' . $w1 => 'Türk Dili ve Edebiyatı', $w0 . 'dkab' . $w1 => 'Din Kültürü ve Ahlak Bilgisi', 'din\s*kül\.' => 'Din Kültürü ve Ahlak Bilgisi',
            $w0 . 'ita' . $w1 => 'T.C. İnkılap Tarihi ve Atatürkçülük', 'fen\s*bil\.' => 'Fen Bilimleri', 'sos\.\s*bil\.?' => 'Sosyal Bilgiler', $w0 . 'mat\.' => 'Matematik',
        );
        foreach ($map as $re => $name) { if (preg_match('/^\s*(?:' . $re . ')\s*$/u', $phrase_low)) return $name; }
        return '';
    }

    // Belge türünü bildiren ve ders adından sonra gelen sözcükler: ders adı bunlardan birine kadar okunur
    function sxs_stop_regex() {
        $stem  = 'yazılı|deneme|test|yaprak|tarama|yıllık|günlük|haftalık|ünitelendirilmiş|plan|soru|sınav|ders\s*not|notlar|çalışma|etkinlik|sunum|slayt|kazanım|online|indir|çöz|cevap|senaryo|performans|proje|föy|fasikül|bulmaca|resfebe|tutanak|tutanağ|rapor|dilekçe|takvim|program|quiz|değerlendirme|analiz|çizelge|ölçek|rubrik|afiş|pano|kavram\s*harita|bilgi\s*yarışma';
        $exact = 'bep|ders|dersi|dersleri|dersine|dersinin|dönem|konu|konusu|tema|ünite|form|kitap|özet|ödev|örnek|pdf|word|şema';
        return '/\d+\s*\.\s*(dönem|yazılı|ünite|tema|hafta|sınav)|(?<![\p{L}])(?:' . $stem . ')|(?<![\p{L}])(?:' . $exact . ')(?![\p{L}])|20\d\d|[\(\[\|:–—]|\s-\s/u';
    }

    // Başlık (ya da kategori adı) içinden "sınıf / sınav + ders + konu" bilgisini çıkarır
    function sxs_parse_title($orig, $hints = array()) {
        $low = sxs_lower($orig);
        $r = array();
        $anchor_end = -1;
        if (preg_match('/(\d{1,2})\s*\.\s*sınıf(lar)?\s*/u', $low, $m, PREG_OFFSET_CAPTURE)) { $r['grade'] = intval($m[1][0]); $anchor_end = $m[0][1] + strlen($m[0][0]); }
        if (preg_match('/(?<![\p{L}])(lgs|kpss|ekpss|tyt|ayt|yks|ekys|ales|dgs|yds|msü|dus|iokbs|bursluluk)(?![\p{L}])\s*/u', $low, $m, PREG_OFFSET_CAPTURE)) {
            $r['exam'] = $m[1][0] === 'bursluluk' ? 'Bursluluk (İOKBS)' : mb_strtoupper(str_replace('i', 'İ', $m[1][0]), 'UTF-8');
            $anchor_end = max($anchor_end, $m[0][1] + strlen($m[0][0]));
        }
        if ($anchor_end < 0 && preg_match('/(?<![\p{L}])(okul\s*öncesi|anaokulu|ilkokul|ortaokul|lise)(?![\p{L}])\s*/u', $low, $m, PREG_OFFSET_CAPTURE)) {
            $lv = array('okul öncesi' => 'Okul öncesi', 'anaokulu' => 'Anaokulu', 'ilkokul' => 'İlkokul', 'ortaokul' => 'Ortaokul', 'lise' => 'Lise'); $r['level'] = $lv[preg_replace('/\s+/u', ' ', $m[1][0])] ?? $m[1][0];
            $anchor_end = $m[0][1] + strlen($m[0][0]);
        }
        if ($anchor_end < 0) return $r;
        $rest_low = substr($low, $anchor_end);
        $stop = preg_match(sxs_stop_regex(), $rest_low, $sm, PREG_OFFSET_CAPTURE) ? $sm[0][1] : strlen($rest_low);
        $phrase_low = trim(substr($rest_low, 0, $stop), " \t,.-–;");
        if ($phrase_low === '') return $r;
        // Aynı sözcükleri özgün başlıktan (büyük/küçük harfleri korunarak) al: harf sayıları eşit olduğundan karakter konumu birebir tutar
        $cstart = mb_strlen(substr($low, 0, $anchor_end), 'UTF-8') + (mb_strlen(substr($rest_low, 0, $stop), 'UTF-8') - mb_strlen(ltrim(substr($rest_low, 0, $stop), " \t,.-–;"), 'UTF-8'));
        $phrase = trim(mb_substr($orig, $cstart, mb_strlen($phrase_low, 'UTF-8'), 'UTF-8'), " \t,.-–;");
        if (count(preg_split('/\s+/u', $phrase_low)) > 7) return $r;   // çok uzunsa ders adı değildir
        $full = sxs_expand_abbr($phrase_low);
        if ($full !== '') { $r['subject'] = $full; return $r; }
        // Ders adından sonra konu geliyorsa ayır (ipucu: kategori adları + yaygın ders adları)
        foreach ($hints as $h) {
            $hl = sxs_lower($h);
            if ($hl !== '' && strpos($phrase_low, $hl) === 0 && mb_strlen($phrase_low, 'UTF-8') > mb_strlen($hl, 'UTF-8') + 2) {
                $r['subject'] = sxs_title_case(mb_substr($phrase, 0, mb_strlen($hl, 'UTF-8'), 'UTF-8'));
                $r['topic'] = sxs_title_case(trim(mb_substr($phrase, mb_strlen($hl, 'UTF-8'), null, 'UTF-8'), " \t,.-–;"));
                return $r;
            }
        }
        $r['subject'] = sxs_title_case($phrase);
        return $r;
    }

    // Belge türü (yazılı, plan, tutanak, dilekçe...) başlıktan
    function sxs_detect_kind($t) {
        $kinds = array(
            'yazılı' => 'yazılı soruları', 'deneme' => 'deneme sınavı', 'yaprak\s*test' => 'yaprak test', 'tarama\s*test' => 'tarama testi', 'konu\s*test' => 'konu testi',
            'yıllık\s*plan' => 'yıllık plan', 'günlük\s*plan' => 'günlük plan', 'haftalık\s*plan' => 'haftalık plan', 'ünitelendirilmiş' => 'ünitelendirilmiş yıllık plan', '(?<![\p{L}])bep\s*plan' => 'BEP planı', 'ders\s*plan' => 'ders planı',
            'zümre' => 'zümre toplantı tutanağı', 'şök|şube\s*öğretmenler' => 'şube öğretmenler kurulu tutanağı', 'veli\s*toplantı' => 'veli toplantısı tutanağı', 'tutanak|tutanağı' => 'tutanak',
            'dilekçe' => 'dilekçe örneği', 'rapor' => 'rapor', 'form' => 'form', 'karne\s*görüş' => 'karne görüşleri', 'sınav\s*takvim' => 'sınav takvimi', 'ders\s*program' => 'ders programı',
            'çalışma\s*k[aâ]ğıd' => 'çalışma kâğıdı', 'etkinlik' => 'etkinlik', 'ders\s*not|notları' => 'ders notu', 'konu\s*anlatım' => 'konu anlatımı', 'özet' => 'özet', 'sunum|slayt' => 'sunum',
            'kazanım' => 'kazanım listesi', 'performans' => 'performans görevi', 'proje' => 'proje ödevi', 'ödev' => 'ödev', 'ölçek|rubrik' => 'değerlendirme ölçeği',
            'resfebe' => 'resfebe bulmacası', 'bulmaca' => 'bulmaca', 'genelge' => 'genelge', 'yönetmelik' => 'yönetmelik', 'kanun' => 'kanun metni',
            'test' => 'test', 'soru' => 'soru çalışması',
        );
        foreach ($kinds as $re => $v) { if (preg_match('/' . $re . '/u', $t)) return $v; }
        return '';
    }

    function sxs_auto_facts($post) {
        $post = get_post($post); if (!$post) return array();
        $title = get_the_title($post); $t = sxs_lower($title);
        $f = array('title' => $title, 'is_test' => ($post->post_type === 'osc_quiz' || strpos((string) $post->post_content, '[osc_quiz') !== false));
        $cats = get_the_category($post->ID); if (!empty($cats)) $f['category'] = $cats[0]->name;

        // Kategori adlarını ve üst kategorileri topla (hem ipucu hem yedek kaynak)
        $names = array();
        foreach ((array) $cats as $c) {
            $names[] = $c->name;
            foreach ((array) get_ancestors($c->term_id, 'category') as $aid) { $a = get_category($aid); if ($a && !is_wp_error($a)) $names[] = $a->name; }
        }
        $hints = array();
        foreach ($names as $nm) {
            $cp = sxs_parse_title($nm);                       // "9. Sınıf Adabı Muaşeret" -> "Adabı Muaşeret"
            if (!empty($cp['subject'])) $hints[] = $cp['subject'];
            elseif (!preg_match('/\d/u', $nm)) $hints[] = $nm;  // "Matematik" gibi düz kategori adları
        }
        $hints = array_merge($hints, array('Türk Dili ve Edebiyatı', 'Din Kültürü ve Ahlak Bilgisi', 'T.C. İnkılap Tarihi ve Atatürkçülük', 'Fen Bilimleri', 'Sosyal Bilgiler', 'Hayat Bilgisi', 'Matematik', 'Türkçe', 'İngilizce', 'Fizik', 'Kimya', 'Biyoloji', 'Tarih', 'Coğrafya', 'Felsefe'));
        usort($hints, function ($x, $y) { return mb_strlen($y, 'UTF-8') - mb_strlen($x, 'UTF-8'); });

        $f = array_merge($f, sxs_parse_title($title, $hints));
        // Başlıkta yoksa sınıf / ders kategorilerden
        foreach ($names as $nm) {
            $cp = sxs_parse_title($nm);
            if (empty($f['grade']) && !empty($cp['grade'])) $f['grade'] = $cp['grade'];
            if (empty($f['subject']) && !empty($cp['subject'])) $f['subject'] = $cp['subject'];
        }

        if (preg_match('/(\d)\s*\.\s*dönem/u', $t, $m)) $f['term'] = intval($m[1]);
        if (preg_match('/(\d)\s*\.\s*yazılı/u', $t, $m)) $f['exam_no'] = intval($m[1]);
        if (preg_match('/(20\d\d)\s*[-–\/]\s*(20\d\d)/u', $t, $m)) $f['year'] = $m[1] . '-' . $m[2];
        $kind = sxs_detect_kind($t); if ($kind !== '') $f['kind'] = $kind;
        $f['answer_key'] = (bool) preg_match('/cevap\s*anahtar|cevaplı|çözümlü/u', $t);
        if (preg_match('/(\d{1,2})\s*\.\s*senaryo/u', $t, $m)) $f['scenario'] = intval($m[1]); elseif (preg_match('/senaryo/u', $t)) $f['scenario'] = true;
        if (preg_match('/(?<![\p{L}])bep(?![\p{L}])|bireyselleştirilmiş\s*eğitim/u', $t)) $f['bep'] = true;
        $f['date'] = get_the_date('j F Y', $post);

        if ($f['is_test']) {
            if (preg_match('/\[osc_quiz[^\]]*id=["\']?(\d+)/i', (string) $post->post_content, $m)) {
                $qd = get_post_meta(intval($m[1]), '_osc_data', true); $qs = get_post_meta(intval($m[1]), '_osc_settings', true);
                if (is_array($qd) && count($qd)) $f['questions'] = count($qd);
                if (is_array($qs) && !empty($qs['duration'])) $f['minutes'] = intval($qs['duration']);
            }
        } else {
            $get = function ($k) use ($post) { return function_exists('get_field') ? get_field($k, $post->ID) : get_post_meta($post->ID, $k, true); };
            $type = trim((string) $get('dosya_turu'));
            $file = $get('boyut'); $url = is_array($file) ? ($file['url'] ?? '') : (is_string($file) ? $file : '');
            if ($type === '' && $url) {
                $ext = strtolower(pathinfo((string) wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                $map = array('pdf' => 'PDF', 'doc' => 'Word', 'docx' => 'Word', 'xls' => 'Excel', 'xlsx' => 'Excel', 'ppt' => 'PowerPoint', 'pptx' => 'PowerPoint', 'zip' => 'ZIP', 'rar' => 'RAR');
                $type = $map[$ext] ?? '';
            }
            if ($type !== '') $f['type'] = $type;
            $kb = sxs_parse_kb($get('dosya_boyutu'));
            if ($kb > 0) $f['size'] = $kb >= 10240 ? number_format_i18n($kb / 1024, 1) . ' MB' : number_format_i18n(max(1, round($kb))) . ' KB';
            $n = intval($get('indirme_sayisi')); if ($n > 0) $f['downloads'] = $n;
            $snd = trim((string) $get('gonderen')); if ($snd !== '') $f['sender'] = $snd;
        }
        return $f;
    }

    // "8. sınıf Matematik", "LGS Türkçe", "Matematik" ...
    function sxs_auto_topic($f) {
        $p = array();
        if (!empty($f['exam'])) $p[] = $f['exam'];
        elseif (!empty($f['grade'])) $p[] = $f['grade'] . '. sınıf';
        elseif (!empty($f['level'])) $p[] = $f['level'];
        if (!empty($f['subject'])) $p[] = $f['subject'];
        return implode(' ', $p);
    }

    // Sayfada gösterilen kısa bilgi paragrafı
    function sxs_auto_summary($f) {
        if (empty($f)) return '';
        $who   = sxs_auto_topic($f);
        $ders  = (!empty($f['subject']) && empty($f['exam'])) ? ' dersi' : '';
        $konu  = !empty($f['topic']) ? ' (' . esc_html($f['topic']) . ' konusu)' : '';
        $s = array();
        if ($f['is_test']) {
            $s[] = $who !== '' ? 'Bu sayfada <strong>' . esc_html($who) . '</strong>' . $ders . $konu . ' için hazırlanmış bir online test bulunuyor.' : 'Bu sayfada <strong>' . esc_html($f['title']) . '</strong> online testi bulunuyor.';
            if (!empty($f['questions'])) $s[] = 'Test ' . intval($f['questions']) . ' sorudan oluşuyor' . (!empty($f['minutes']) ? ' ve çözüm süresi ' . intval($f['minutes']) . ' dakika.' : '.');
            $s[] = 'Cevaplarını ekranda işaretle; testi bitirdiğinde puanını ve yanlışlarını doğru cevaplarıyla birlikte hemen görürsün. İstersen testi yazdırıp kâğıt üzerinde de çözebilirsin.';
        } else {
            $what  = !empty($f['kind']) ? $f['kind'] : 'dosya';
            $sinav = trim((!empty($f['term']) ? $f['term'] . '. dönem ' : '') . (!empty($f['exam_no']) ? $f['exam_no'] . '. yazılı' : ''));
            $bep_on = !empty($f['bep']) && $what !== 'BEP planı';
            if ($bep_on && $what !== 'dosya') $what = 'BEP ' . $what;
            if ($who !== '' && ($what === 'yazılı soruları' || $what === 'BEP yazılı soruları') && $sinav !== '')
                $s[] = 'Bu sayfada <strong>' . esc_html($who) . '</strong>' . ($ders ? ' dersinin' : '') . ' ' . esc_html($sinav) . ' sınavı için hazırlanmış ' . esc_html($what) . ' yer alıyor.';
            elseif ($who !== '')
                $s[] = 'Bu sayfada <strong>' . esc_html($who) . '</strong>' . $ders . $konu . ' için hazırlanmış ' . esc_html($what) . ' yer alıyor.';
            else
                $s[] = 'Bu sayfada <strong>' . esc_html($f['title']) . '</strong> yer alıyor' . (($what !== 'dosya' && strpos(sxs_lower($f['title']), explode(' ', $what)[0]) === false) ? ' (' . esc_html($what) . ')' : '') . '.';
            if (!empty($f['bep'])) {
                $s[] = 'BEP, <strong>Bireyselleştirilmiş Eğitim Programı</strong> demektir: özel eğitim ihtiyacı olan ve kaynaştırma/bütünleştirme yoluyla eğitim alan öğrenciler için, her öğrencinin kendi hedeflerine ve öğrenme düzeyine göre hazırlanan programdır.';
                if ($what === 'BEP planı') $s[] = 'Bu plan, öğrencinin performans düzeyini, kısa ve uzun dönemli hedeflerini ve uygulanacak öğretim yöntemlerini düzenlemek için kullanılabilir.';
                elseif (strpos($what, 'yazılı') !== false || strpos($what, 'test') !== false || strpos($what, 'deneme') !== false) $s[] = 'BEP soruları, bu öğrencilerin düzeyine uygun olarak daha sade ve anlaşılır biçimde hazırlanır; böylece öğrenci sınavda bildiklerini rahatça gösterebilir.';
                else $s[] = 'Bu içerik, BEP\'i bulunan öğrencilerin düzeyine uygun olarak hazırlanmıştır.';
            }
            if (is_int($f['scenario'] ?? null)) $s[] = 'Sorular MEB\'in ' . intval($f['scenario']) . '. örnek senaryosuna uygun hazırlanmış.';
            elseif (!empty($f['scenario'])) $s[] = 'Sorular MEB örnek senaryolarına uygun hazırlanmış.';
            if (!empty($f['year'])) $s[] = 'İçerik ' . esc_html($f['year']) . ' eğitim-öğretim yılına göre hazırlandı.';
            if ($f['answer_key']) $s[] = 'Dosyada cevap anahtarı da bulunuyor.';
            if (!empty($f['type']) || !empty($f['size'])) $s[] = trim((!empty($f['type']) ? esc_html($f['type']) . ' biçiminde' : '') . (!empty($f['type']) && !empty($f['size']) ? ' ve ' : '') . (!empty($f['size']) ? esc_html($f['size']) . ' boyutundaki' : '')) . (!empty($f['size']) ? '' : 'ki') . ' dosyayı ücretsiz indirip yazdırabilirsin.';
            $tail = esc_html($f['date']) . ' tarihinde eklendi';
            if (!empty($f['sender'])) $tail = esc_html($f['sender']) . ' tarafından ' . $tail;
            if (!empty($f['downloads'])) $tail .= ', şimdiye kadar ' . number_format_i18n($f['downloads']) . ' kez indirildi';
            $s[] = $tail . '.';
        }
        return implode(' ', $s);
    }

    // Google sonuç metni (en fazla ~155 karakter)
    function sxs_auto_meta($f) {
        if (empty($f)) return '';
        $topic = sxs_auto_topic($f);
        $base = trim(preg_replace('/[\s\-–|:]*(online\s*)?(test\s*)?(ücretsiz\s*)?(çöz|indir)\s*$/iu', '', $f['title']));
        if ($base === '') $base = $f['title'];
        $base = sxs_title_case($base);
        if ($f['is_test']) {
            $d = $base . (!empty($f['questions']) ? ': ' . $f['questions'] . ' soru' . (!empty($f['minutes']) ? ', ' . $f['minutes'] . ' dakika' : '') : '') . '. Online çöz, sonucunu ve yanlışlarını doğru cevaplarıyla hemen gör.';
        } else {
            $bits = array();
            if ($f['answer_key'] && !preg_match('/cevap\s*anahtar/u', sxs_lower($base))) $bits[] = 'cevap anahtarlı';
            $add_bep = !empty($f['bep']) ? ' BEP (Bireyselleştirilmiş Eğitim Programı) öğrencileri için.' : '';
            if (!empty($f['type'])) $bits[] = $f['type'];
            if (!empty($f['size'])) $bits[] = $f['size'];
            $d = $base . ' ücretsiz indir' . ($bits ? ' (' . implode(', ', $bits) . ')' : '') . '.';
            $len = function ($x) { return function_exists('mb_strlen') ? mb_strlen($x, 'UTF-8') : strlen($x); };
            foreach (array($add_bep, !empty($f['bep']) ? ' BEP öğrencileri için, üyelik gerekmez.' : '', $topic !== '' ? ' ' . $topic . ' için hazırlanmış, yazdırmaya hazır güncel dosya.' : '', ' Yazdırmaya hazır, üyelik gerekmez.', ' Üyelik gerekmez.') as $add) {
                if ($add !== '' && $len($d . $add) <= 155) { $d .= $add; break; }
            }
        }
        $d = trim(preg_replace('/\s+/u', ' ', $d));
        if (function_exists('mb_strlen') && mb_strlen($d, 'UTF-8') > 158) { $d = mb_substr($d, 0, 155, 'UTF-8'); $d = preg_replace('/\s+\S*$/u', '', $d) . '…'; }
        return $d;
    }
}

// Rank Math'te bu yazı için elle açıklama girilmemişse otomatik açıklamayı kullan (meta + sosyal medya)
$sxs_q = get_queried_object();
if ($sxs_q instanceof WP_Post && trim((string) get_post_meta($sxs_q->ID, 'rank_math_description', true)) === '') {
    $sxs_meta = sxs_auto_meta(sxs_auto_facts($sxs_q));
    if ($sxs_meta !== '') {
        $sxs_desc_cb = function ($d) use ($sxs_meta) { return $sxs_meta; };
        add_filter('rank_math/frontend/description', $sxs_desc_cb, 20);
        add_filter('rank_math/opengraph/facebook/og_description', $sxs_desc_cb, 20);
        add_filter('rank_math/opengraph/twitter/twitter_description', $sxs_desc_cb, 20);
    }
}

get_header();
echo "\n<!-- soruindir-single-v2 -->\n";

$sxs_go = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$sxs_sep = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>';
$sxs_is_test = function ($p) { return $p->post_type === 'osc_quiz' || strpos((string) $p->post_content, '[osc_quiz') !== false; };
?>
<style>
    .sxs { width:100%; background:#fff; color:var(--sx-ink, #16233F); font-family:var(--sx-font, 'Figtree', system-ui, sans-serif); font-size:16px; line-height:1.6; -webkit-font-smoothing:antialiased; padding:28px 0 56px; }
    .sxs *, .sxs *::before, .sxs *::after { box-sizing:border-box; }
    .sxs a:focus-visible, .sxs button:focus-visible { outline:3px solid #E0457B; outline-offset:2px; border-radius:10px; }
    .sxs-wrap { max-width:1200px; margin:0 auto; padding:0 20px; display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:36px; align-items:start; }
    .sxs-main { min-width:0; }

    /* Başlık */
    .sxs-crumbs { display:flex; flex-wrap:wrap; align-items:center; gap:6px; font-size:14px; color:#4A5468; margin-bottom:12px; }
    .sxs-crumbs a { color:#4A5468; text-decoration:none !important; }
    .sxs-crumbs a:hover { color:#1E5AA8; }
    .sxs-crumbs svg { width:13px; height:13px; color:#7A8396; }
    .sxs h1.sxs-title { margin:0; font-family:var(--sx-display, 'Bricolage Grotesque', sans-serif); font-size:clamp(28px, 3.3vw, 40px); line-height:1.12; font-weight:800; letter-spacing:-0.015em; color:#16233F; }
    .sxs-meta { display:flex; flex-wrap:wrap; align-items:center; gap:8px 18px; margin-top:14px; font-size:14.5px; color:#4A5468; }
    .sxs-meta span { display:inline-flex; align-items:center; gap:6px; }
    .sxs-meta svg { width:16px; height:16px; color:#7A8396; }
    .sxs-meta a { color:#1E5AA8; font-weight:600; text-decoration:none !important; }
    .sxs-meta a:hover { text-decoration:underline !important; text-underline-offset:3px; }

    /* İçerik */
    .sxs-body { margin-top:26px; }
    .sxs-prose { font-size:16.5px; line-height:1.75; color:#2C3546; overflow-wrap:break-word; }
    .sxs-prose > *:first-child { margin-top:0; }
    .sxs-prose > p { margin:0 0 1.1em; }
    .sxs-prose > h2, .sxs-prose > h3, .sxs-prose > h4 { font-family:var(--sx-display, 'Bricolage Grotesque', sans-serif); color:#16233F; line-height:1.25; margin:1.6em 0 .6em; letter-spacing:-0.01em; }
    .sxs-prose > h2 { font-size:24px; } .sxs-prose > h3 { font-size:20px; } .sxs-prose > h4 { font-size:18px; }
    .sxs-prose > ul, .sxs-prose > ol { margin:0 0 1.1em; padding-left:1.3em; }
    .sxs-prose > ul li, .sxs-prose > ol li { margin:.3em 0; }
    .sxs-prose > p a, .sxs-prose > ul a, .sxs-prose > ol a { color:#1E5AA8; text-decoration:underline; text-underline-offset:3px; }
    .sxs-prose img { max-width:100%; height:auto; border-radius:14px; }
    .sxs-prose > blockquote { margin:1.4em 0; padding:14px 20px; border-left:4px solid #1E5AA8; background:#F5F6FA; border-radius:0 14px 14px 0; color:#16233F; }
    .sxs-prose > table, .sxs-prose > .wp-block-table table { width:100%; border-collapse:collapse; margin:0 0 1.2em; font-size:15px; }
    .sxs-prose > table td, .sxs-prose > table th, .sxs-prose > .wp-block-table td, .sxs-prose > .wp-block-table th { border:1px solid #E4E7EE; padding:8px 12px; text-align:left; }
    .sxs-download { margin-top:28px; }
    .sxs-summary { margin:0 0 20px; padding:16px 20px; border-radius:16px; background:#F5F6FA; font-size:16px; line-height:1.7; color:#2C3546; }
    .sxs-summary strong { color:#16233F; }

    /* Paylaş */
    .sxs-share { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-top:32px; padding-top:22px; border-top:1.5px solid #E4E7EE; }
    .sxs-share > span { font-weight:700; font-size:15px; color:#16233F; margin-right:4px; }
    .sxs .sxs-share a, .sxs .sxs-share button { display:inline-flex; align-items:center; gap:8px; padding:9px 14px; border-radius:12px; border:1.5px solid #E4E7EE; background:#fff; color:#16233F; font:inherit; font-weight:600; font-size:14.5px; line-height:1.2; cursor:pointer; text-decoration:none !important; transition:border-color .15s, background .15s, color .15s; }
    .sxs-share svg { width:17px; height:17px; flex:0 0 auto; }
    .sxs .sxs-share .s-wa svg { color:#1FAF5A; } .sxs .sxs-share .s-fb svg { color:#1877F2; } .sxs .sxs-share .s-x svg { color:#111; }
    .sxs .sxs-share a:hover, .sxs .sxs-share button:hover { border-color:#16233F; background:#F5F6FA; }
    .sxs .sxs-share button.is-done { border-color:#1E5AA8; color:#1E5AA8; background:#E8F0FA; }

    /* Benzer içerikler */
    .sxs-related { margin-top:44px; }
    .sxs-related h2 { margin:0 0 14px; font-family:var(--sx-display, 'Bricolage Grotesque', sans-serif); font-size:24px; font-weight:800; color:#16233F; letter-spacing:-0.01em; }
    .sxs-related ul { list-style:none; margin:0; padding:0; display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px; }
    .sxs .sxs-rel { display:flex; align-items:center; gap:13px; height:100%; padding:13px 14px; border-radius:16px; background:#fff; border:1.5px solid #E4E7EE; text-decoration:none !important; transition:border-color .15s, box-shadow .15s, transform .15s; }
    .sxs .sxs-rel:hover { border-color:#1E5AA8; box-shadow:3px 3px 0 #1E5AA8; transform:translate(-1px,-1px); }
    .sxs-rel-ico { flex:0 0 40px; height:40px; border-radius:11px; display:grid; place-items:center; background:#F5F6FA; color:#16233F; }
    .sxs-rel.is-test .sxs-rel-ico { background:#FDF0F4; color:#B92D5F; }
    .sxs-rel-ico svg { width:20px; height:20px; }
    .sxs-rel-title { flex:1; min-width:0; font-weight:700; font-size:15px; line-height:1.38; color:#16233F; }
    .sxs .sxs-rel:hover .sxs-rel-title { color:#1E5AA8; }

    /* Yan sütun */
    .sxs-side { position:sticky; top:96px; display:grid; gap:16px; align-self:start; }
    body.admin-bar .sxs-side { top:128px; }
    .sxs-box { border:1px solid #E6E9F0; border-radius:20px; padding:18px; background:#fff; box-shadow:0 14px 34px -28px rgba(22,35,63,.45); }
    .sxs-box h2 { margin:0 0 12px; font-family:var(--sx-display, 'Bricolage Grotesque', sans-serif); font-size:16px; font-weight:800; color:#16233F; letter-spacing:0; }
    .sxs-box--ad { padding:10px; text-align:center; overflow:hidden; background:#F7F8FB; border-color:#F0F2F6; box-shadow:none; }
    .sxs-ad-et { display:block; font:600 10px/1 system-ui, sans-serif; letter-spacing:.08em; text-transform:uppercase; color:#9AA3B5; margin-bottom:6px; }
    .sxs .sxs-cta { position:relative; display:grid; grid-template-columns:44px 1fr; gap:6px 12px; padding:18px; border-radius:20px; color:#fff !important; text-decoration:none !important; overflow:hidden; background:radial-gradient(260px 120px at 100% 0, rgba(255,255,255,.18), transparent 60%), linear-gradient(135deg,#1E5AA8,#2563EB 60%,#4F46E5); box-shadow:0 18px 34px -22px rgba(30,90,168,.9); transition:transform .2s; }
    .sxs .sxs-cta:hover { transform:translateY(-2px); }
    .sxs-cta-ik { grid-row:span 2; width:44px; height:44px; border-radius:13px; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,.18); font-size:22px; font-weight:800; }
    .sxs-cta-yazi b { display:block; font-family:var(--sx-display, 'Bricolage Grotesque', sans-serif); font-size:17px; line-height:1.2; }
    .sxs-cta-yazi small { display:block; margin-top:4px; font-size:13.5px; line-height:1.45; color:rgba(255,255,255,.88); }
    .sxs-cta-ok { grid-column:1 / -1; display:inline-flex; align-items:center; justify-content:center; gap:8px; margin-top:8px; padding:10px 14px; border-radius:12px; background:#fff; color:#16233F; font-weight:700; font-size:14.5px; }
    .sxs-cta-ok svg, .sxs-om-ok svg, .sxs-gonder svg { width:15px; height:15px; }
    .sxs .sxs-om { display:block; padding:18px; border-radius:20px; color:#fff !important; text-decoration:none !important; background:radial-gradient(240px 140px at 100% -10%, rgba(45,212,191,.45), transparent 60%), linear-gradient(135deg,#0b1024,#1e1b4b 55%,#0f3d56); box-shadow:0 18px 34px -24px rgba(30,27,75,.9); transition:transform .2s; }
    .sxs .sxs-om:hover { transform:translateY(-2px); }
    .sxs-om-et { display:inline-block; font-size:11px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#5eead4; }
    .sxs-om b { display:block; margin:4px 0 4px; font-family:var(--sx-display, 'Bricolage Grotesque', sans-serif); font-size:19px; }
    .sxs-om small { display:block; font-size:13.5px; line-height:1.5; color:#cbd5e1; }
    .sxs-om-ok { display:inline-flex; align-items:center; gap:8px; margin-top:12px; font-weight:800; font-size:14px; color:#5eead4; }
    .sxs-tiles { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .sxs .sxs-tiles a { display:flex; flex-direction:column; gap:6px; padding:12px; border-radius:14px; background:#F5F7FB; border:1px solid #EDF0F5; color:#16233F !important; text-decoration:none !important; font-size:13.5px; font-weight:700; line-height:1.3; transition:background .15s, border-color .15s, transform .15s; }
    .sxs .sxs-tiles a:hover { background:#EEF4FF; border-color:#C9DBFA; transform:translateY(-1px); }
    .sxs-tiles i { font-style:normal; font-size:20px; line-height:1; }
    .sxs-son ol { list-style:none; margin:0; padding:0; display:grid; gap:4px; }
    .sxs .sxs-son a { display:flex; gap:12px; align-items:center; padding:8px; margin:0 -8px; border-radius:12px; color:#16233F !important; text-decoration:none !important; }
    .sxs .sxs-son a:hover { background:#F5F7FB; }
    .sxs-son-img { flex:0 0 52px; width:52px; height:52px; border-radius:12px; overflow:hidden; background:#EEF2F8; }
    .sxs-son-img img { width:100%; height:100%; object-fit:cover; display:block; }
    .sxs-son-img--yok { display:flex; align-items:center; justify-content:center; font-weight:800; color:#1E5AA8; font-size:20px; background:linear-gradient(135deg,#E8F0FE,#F3E8FF); }
    .sxs-son-y { min-width:0; }
    .sxs-son-y b { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; font-size:14px; line-height:1.35; font-weight:700; }
    .sxs-son-y small { display:block; margin-top:3px; font-size:12px; color:#6B7488; }
    .sxs .sxs-gonder { display:block; padding:16px 18px; border-radius:18px; border:1.5px dashed #C9D3E4; color:#16233F !important; text-decoration:none !important; background:#FBFCFE; }
    .sxs-gonder b { display:block; font-size:14px; line-height:1.4; }
    .sxs-gonder span { display:inline-flex; align-items:center; gap:6px; margin-top:6px; color:#1E5AA8; font-weight:800; font-size:13.5px; }

    @media (max-width: 1000px) {
        .sxs-wrap { grid-template-columns:minmax(0,1fr); gap:40px; }
        .sxs-side { position:static; grid-template-columns:repeat(2, minmax(0,1fr)); }
        .sxs-box--ad { grid-column:1 / -1; }
    }
    @media (max-width: 640px) {
        .sxs { padding-top:16px; }
        .sxs-wrap { padding:0 14px; }
        .sxs-prose { font-size:16px; }
        .sxs-related ul, .sxs-side { grid-template-columns:minmax(0,1fr); }
        .sxs .sxs-share a, .sxs .sxs-share button { flex:1 1 calc(50% - 10px); justify-content:center; }
        .sxs-share > span { flex-basis:100%; }
    }
    @media (prefers-reduced-motion: reduce) { .sxs * { transition:none !important; } }
</style>

<div class="sxs">
<div class="sxs-wrap">

    <div id="primary" class="sxs-main">
        <main id="main">
        <?php if (function_exists('do_action')) do_action('generate_before_main_content'); ?>

        <?php while (have_posts()): the_post();
            $post_obj = get_post();
            $is_test  = $sxs_is_test($post_obj);
            $cats     = get_the_category();
            $cat      = !empty($cats) ? $cats[0] : null;
            $share_url   = rawurlencode(get_permalink());
            $share_title = rawurlencode(get_the_title());
        ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class('sxs-article'); ?>>
                <header>
                    <?php the_title('<h1 class="sxs-title entry-title">', '</h1>'); ?>
                    <div class="sxs-meta">
                        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg><time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('j F Y')); ?></time></span>
                        <?php if ($cat): ?><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg><a href="<?php echo esc_url(get_category_link($cat->term_id)); ?>"><?php echo esc_html($cat->name); ?></a></span><?php endif; ?>
                    </div>
                </header>

                <div class="sxs-body">
                    <?php
                    $sxs_words = count(preg_split('/\s+/u', trim(wp_strip_all_tags(strip_shortcodes($post_obj->post_content))), -1, PREG_SPLIT_NO_EMPTY));
                    $sxs_summary = $sxs_words < 80 ? sxs_auto_summary(sxs_auto_facts($post_obj)) : '';
                    if ($sxs_summary !== ''): ?>
                    <p class="sxs-summary"><?php echo $sxs_summary; ?></p>
                    <?php endif; ?>
                    <div class="sxs-prose entry-content">
                        <?php the_content(); ?>
                    </div>

                    <?php if (!$is_test): ?>
                    <div class="sxs-download">
                        <?php
                        $dl_file = get_stylesheet_directory() . '/download2.php';
                        if (!file_exists($dl_file)) $dl_file = get_template_directory() . '/download2.php';
                        if (file_exists($dl_file)) {
                            include $dl_file;
                        } else {
                            echo '<p style="padding:14px 16px;border-radius:14px;background:#FDF0F4;color:#B92D5F;font-weight:600;">İndirme kutusu yüklenemedi (download2.php bulunamadı).</p>';
                        }
                        ?>
                    </div>
                    <?php endif; ?>

                    <div class="sxs-share">
                        <span>Paylaş</span>
                        <a class="s-wa" href="https://api.whatsapp.com/send?text=<?php echo $share_title . '%20' . $share_url; ?>" target="_blank" rel="nofollow noopener"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.5 14.4c-.3-.1-1.8-.9-2-1-.3-.1-.5-.1-.7.1-.2.3-.8 1-.9 1.2-.2.2-.3.2-.6.1-.3-.1-1.3-.5-2.4-1.5-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.6l.4-.5c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.1.2 2.1 3.3 5.2 4.6.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.8-.7 2-1.4.3-.7.3-1.3.2-1.4-.1-.1-.3-.2-.6-.3zM12 21.8a9.8 9.8 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4A9.8 9.8 0 1 1 12 21.8zm8.4-18.2A11.8 11.8 0 0 0 1.9 17.8L.2 24l6.3-1.7a11.8 11.8 0 0 0 5.6 1.4 11.8 11.8 0 0 0 8.3-20.1z"/></svg>WhatsApp</a>
                        <a class="s-fb" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share_url; ?>" target="_blank" rel="nofollow noopener"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9 8H6v4h3v12h5V12h3.6l.4-4h-4V6.3c0-1 .2-1.3 1.1-1.3H18V0h-3.8C10.6 0 9 1.6 9 4.6z"/></svg>Facebook</a>
                        <a class="s-x" href="https://twitter.com/intent/tweet?text=<?php echo $share_title; ?>&amp;url=<?php echo $share_url; ?>" target="_blank" rel="nofollow noopener"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.8 3h3.1l-6.8 7.8L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.3-8.3L2 3h6.4l4.4 5.8zm-1.1 16.2h1.7L7.4 4.7H5.6z"/></svg>X</a>
                        <button type="button" data-copy="<?php echo esc_attr(get_permalink()); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><span>Linki kopyala</span></button>
                    </div>
                </div>
            </article>

            <?php
            if ($cat):
                $rq = new WP_Query(array('category__in' => array($cat->term_id), 'post__not_in' => array(get_the_ID()), 'posts_per_page' => 6, 'ignore_sticky_posts' => 1, 'no_found_rows' => true));
                if ($rq->have_posts()): ?>
                <section class="sxs-related" aria-labelledby="sxs-rel-h">
                    <h2 id="sxs-rel-h"><?php echo $is_test ? 'Bunları da çöz' : 'Benzer dosyalar'; ?></h2>
                    <ul>
                        <?php foreach ($rq->posts as $rp): $rt = $sxs_is_test($rp); ?>
                        <li><a class="sxs-rel<?php echo $rt ? ' is-test' : ''; ?>" href="<?php echo esc_url(get_permalink($rp)); ?>">
                            <span class="sxs-rel-ico" aria-hidden="true"><?php echo $rt
                                ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="6.5" cy="7" r="2.5"/><circle cx="6.5" cy="17" r="2.5"/><path d="M12 7h8M12 17h8"/><circle cx="6.5" cy="7" r="1" fill="currentColor"/></svg>'
                                : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M12 18v-6M9 15l3 3 3-3"/></svg>'; ?></span>
                            <span class="sxs-rel-title"><?php echo esc_html(get_the_title($rp)); ?></span>
                        </a></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; wp_reset_postdata();
            endif;
        endwhile; ?>

        <?php do_action('generate_after_main_content'); ?>
        </main>
    </div>

    <aside class="sxs-side" aria-label="Yan sütun">
        <?php
        $sxs_om = class_exists('OSC_Masa') && OSC_Masa::yayinda();
        $sxs_lgs = class_exists('LGS_Tercih_Robotu') && method_exists('LGS_Tercih_Robotu', 'robot_url') ? LGS_Tercih_Robotu::robot_url() : get_transient('sx_lgs_robot_url');
        $sxs_yks = class_exists('YKS_Tercih_Robotu') ? YKS_Tercih_Robotu::robot_url() : '';
        $ad_sidebar = get_option('sp_gp_ad_sidebar', ''); ?>

        <a class="sxs-cta" href="https://soruindir.net/test-coz/">
            <span class="sxs-cta-ik" aria-hidden="true">✓</span>
            <span class="sxs-cta-yazi"><b>İndirmeden ekranda çöz</b><small>Online testlerde cevabını işaretle, sonucunu hemen gör.</small></span>
            <span class="sxs-cta-ok">Online testlere git <?php echo $sxs_go; ?></span>
        </a>

        <?php if ($sxs_om): ?>
        <a class="sxs-om" href="<?php echo esc_url(OSC_Masa::url()); ?>">
            <span class="sxs-om-et">Öğretmenler için</span>
            <b>Öğretmen Masası</b>
            <small>Senaryoya uygun yazılı, tutanak, BEP, kulüp evrakları ve OYS çizelgesi; Word olarak indirin.</small>
            <span class="sxs-om-ok">Hemen hazırla <?php echo $sxs_go; ?></span>
        </a>
        <?php endif; ?>

        <?php if (!empty($ad_sidebar)): ?>
        <div class="sxs-box sxs-box--ad"><span class="sxs-ad-et">Reklam</span><?php echo stripslashes($ad_sidebar); ?></div>
        <?php endif; ?>

        <nav class="sxs-box sxs-quick" aria-labelledby="sxs-q-h">
            <h2 id="sxs-q-h">Hızlı erişim</h2>
            <div class="sxs-tiles">
                <a href="https://soruindir.net/category/yazili-sorulari/"><i aria-hidden="true">📝</i><span>Yazılı soruları</span></a>
                <a href="https://soruindir.net/test-coz/"><i aria-hidden="true">🎯</i><span>Online testler</span></a>
                <a href="https://soruindir.net/category/siniflar/"><i aria-hidden="true">📚</i><span>Ders notları</span></a>
                <a href="https://soruindir.net/category/yillik-planlar/"><i aria-hidden="true">🗓️</i><span>Yıllık planlar</span></a>
                <?php if ($sxs_lgs): ?><a href="<?php echo esc_url($sxs_lgs); ?>"><i aria-hidden="true">🏫</i><span>LGS tercih robotu</span></a><?php endif; ?>
                <?php if ($sxs_yks): ?><a href="<?php echo esc_url($sxs_yks); ?>"><i aria-hidden="true">🎓</i><span>YKS tercih robotu</span></a><?php endif; ?>
            </div>
        </nav>

        <?php $sxs_son = new WP_Query(array('post_type' => 'post', 'posts_per_page' => 5, 'post__not_in' => array(get_the_ID()), 'ignore_sticky_posts' => 1, 'no_found_rows' => true));
        if ($sxs_son->have_posts()): ?>
        <div class="sxs-box sxs-son">
            <h2>Son eklenenler</h2>
            <ol>
            <?php while ($sxs_son->have_posts()): $sxs_son->the_post(); $sc = get_the_category(); ?>
                <li><a href="<?php the_permalink(); ?>">
                    <?php if (has_post_thumbnail()): ?><span class="sxs-son-img"><?php the_post_thumbnail('thumbnail', array('loading' => 'lazy', 'alt' => '')); ?></span><?php else: ?><span class="sxs-son-img sxs-son-img--yok" aria-hidden="true"><?php echo esc_html(mb_substr(get_the_title(), 0, 1)); ?></span><?php endif; ?>
                    <span class="sxs-son-y"><b><?php the_title(); ?></b><small><?php echo $sc ? esc_html($sc[0]->name) . ' · ' : ''; echo esc_html(get_the_date('j F Y')); ?></small></span>
                </a></li>
            <?php endwhile; wp_reset_postdata(); ?>
            </ol>
        </div>
        <?php endif; ?>

        <a class="sxs-gonder" href="https://soruindir.net/icerik-gonder/"><b>Sizin de paylaşmak istediğiniz bir dosya mı var?</b><span>İçerik gönder <?php echo $sxs_go; ?></span></a>
    </aside>

</div>
</div>

<script>
(function () {
    document.querySelectorAll('.sxs-share [data-copy]').forEach(function (b) {
        b.addEventListener('click', function () {
            var url = b.getAttribute('data-copy'), label = b.querySelector('span');
            function ok() { b.classList.add('is-done'); label.textContent = 'Kopyalandı'; setTimeout(function () { b.classList.remove('is-done'); label.textContent = 'Linki kopyala'; }, 2200); }
            if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(url).then(ok, function () { window.prompt('Bağlantıyı kopyala:', url); }); }
            else { window.prompt('Bağlantıyı kopyala:', url); }
        });
    });
})();
</script>

<?php get_footer(); ?>
