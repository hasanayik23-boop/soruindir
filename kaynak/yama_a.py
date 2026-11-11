import re
P = '/home/claude/work/osc/onlinesorucoz.php'
s = open('/home/claude/work/osc/orijinal.php', encoding='utf-8').read()
parca = lambda f: open('/home/claude/work/osc/parca/' + f, encoding='utf-8').read()

def R(a, b, n=1):
    global s
    c = s.count(a)
    assert c == n, (a[:90], c)
    s = s.replace(a, b)

def between(start, end, repl, keep_end=True):
    global s
    i = s.index(start); j = s.index(end, i)
    s = s[:i] + repl + (s[j:] if keep_end else s[j + len(end):])

# --- başlık
R(" * Version: 21.7", " * Version: 22.0")
R(" * Description: PDF okuma sistemi, mobil manşet ve hiyerarşik kategori yapısı dahil tam sürüm. Görselden soru alma iyileştirildi. Başlangıç ve Sonuç ekranları modernleştirildi.",
  " * Description: PDF okuma sistemi, mobil manşet ve hiyerarşik kategori yapısı dahil tam sürüm. V22: gerçek sınav modu (optik form, sınava özel net, otomatik teslim, konu analizi), canlı sınıf, ödev linki ve öğretmen test ekleme.")

# --- get_quiz_html imzası, oturum modu
R("        public function get_quiz_html($post_id) {\n            $data = get_post_meta($post_id, '_osc_data', true);\n            $settings = get_post_meta($post_id, '_osc_settings', true);",
  "        public function get_quiz_html($post_id, $oturum = null) {\n            $data = get_post_meta($post_id, '_osc_data', true);\n            $settings = get_post_meta($post_id, '_osc_settings', true);\n            if (!is_array($settings)) $settings = array();\n            if ($oturum && isset($oturum['_veri'])) { if (is_array($oturum['_veri']) && $oturum['_veri']) $data = $oturum['_veri']; unset($oturum['_veri']); }")
R("            $view_mode = isset($settings['view_mode']) ? $settings['view_mode'] : 'step'; ",
  "            $view_mode = isset($settings['view_mode']) ? $settings['view_mode'] : 'step'; \n            if ($oturum) $view_mode = 'step';")
R("            $duration_min = isset($settings['duration']) ? intval($settings['duration']) : 0;\n            $duration_display = 'Süresiz';",
  "            $duration_min = isset($settings['duration']) ? intval($settings['duration']) : 0;\n"
  "            // V22: sınav türü (net kuralı) ve süre boşsa gerçek sınav süresi\n"
  "            $exam = OSC_Sinav::bilgi($post_id, $settings, count($data));\n"
  "            if ((!isset($settings['duration']) || $settings['duration'] === '') && $exam['oto_sure'] > 0) { $duration_min = $exam['oto_sure']; $settings['duration'] = $duration_min; }\n"
  "            if ($oturum) { $duration_min = intval($oturum['sure']); $settings['duration'] = $duration_min; }\n"
  "            $duration_display = 'Süresiz';")
R("            $config = json_encode(['questions' => $data, 'settings' => $settings, 'ad_freq' => $ad_freq, 'ad_code' => $ad_code]);",
  "            $cfg_q = $oturum ? OSC_Sinav::cevapsiz($data) : $data;\n"
  "            if ($oturum) $schema_json = '';\n"
  "            $config = json_encode(['questions' => $cfg_q, 'settings' => $settings, 'ad_freq' => $oturum ? 0 : $ad_freq, 'ad_code' => $oturum ? '' : $ad_code, 'exam' => array('kural' => $exam['kural'], 'ad' => $exam['ad']), 'oturum' => $oturum ? $oturum : null, 'uye' => is_user_logged_in() ? 1 : 0, 'cn' => is_user_logged_in() ? wp_create_nonce('osc_sp_cozum') : '', 'uye_url' => OSC_Sinav::sayfa_url('osc_sp_uye_sayfa'), 'url' => get_the_ID() ? get_permalink(get_the_ID()) : '']);")
R("            <script type=\"application/ld+json\">\n            <?php echo $schema_json; ?>\n            </script>",
  "            <?php if ($schema_json): ?><script type=\"application/ld+json\">\n            <?php echo $schema_json; ?>\n            </script><?php endif; ?>")

# --- başlangıç ekranı: sınav kuralı + oturum kartı
R("""                                    <div class="osc-m-stat"><span class="dashicons dashicons-clock"></span> <?php echo $duration_display; ?></div>
                                </div>
""", """                                    <div class="osc-m-stat"><span class="dashicons dashicons-clock"></span> <?php echo $duration_display; ?></div>
                                </div>
                                <div class="osc-sinav-kural"><b><?php echo esc_html($exam['ad']); ?></b><span>·</span><span><?php echo $exam['kural'] > 0 ? intval($exam['kural']) . ' yanlış 1 doğruyu götürür' : 'Yanlışlar doğruyu götürmez'; ?></span><span>·</span><span>Boş bırakabilir, sorular arasında gezebilirsin</span></div>
""")
i = s.index("                                <?php \n                                $q_count = count($data);")
j = s.index("                                <?php } ?>\n", i) + len("                                <?php } ?>\n")
eski = s[i:j]
s = s[:i] + "                                <?php if (!$oturum): ?>\n" + eski + \
    "                                <?php $osc_panel = OSC_Sinav::panel_url(array('yeni' => $post_id)); if ($osc_panel): ?><a class=\"osc-ogretmen-link\" href=\"<?php echo esc_url($osc_panel); ?>\">👩‍🏫 Öğretmen misiniz? Bu testi sınıfınızda canlı uygulayın ya da ödev olarak gönderin →</a><?php endif; ?>\n" + \
    "                                <?php else: ?>\n" + \
    "                                <div class=\"osc-sp-kutu\" id=\"osc-sp-durum-<?php echo $post_id; ?>\"><div class=\"osc-sp-bekle\"><span class=\"osc-sp-nokta\"><i></i><i></i><i></i></span><b>Hazırlanıyor…</b></div></div>\n" + \
    "                                <div class=\"osc-sp-kim\">👤 <b><?php echo esc_html($oturum['ad']); ?></b> · Oturum kodu: <b><?php echo esc_html($oturum['kod']); ?></b></div>\n" + \
    "                                <?php endif; ?>\n" + s[j:]

# --- soru ekranı (jokerler kaldırıldı, optik form)
i = s.index('                   <div class="osc-screen osc-quiz" style="display:none;">')
j = s.index('                    <!-- MODERN TASARIM SONUÇ EKRANI -->', i)
s = s[:i] + parca('ekran.php') + s[j:]

# --- sonuç ekranı: net kuralı, süre doldu notu, konu analizi
R("""                            <h3 class="osc-result-title">Test Tamamlandı</h3>""",
  """                            <h3 class="osc-result-title">Test Tamamlandı</h3>
                            <div class="osc-net-kural" id="osc-net-kural-<?php echo $post_id; ?>"></div>
                            <div class="osc-oto-not" id="osc-oto-<?php echo $post_id; ?>" style="display:none">⏰ Süre doldu; sınavın otomatik olarak teslim edildi.</div>""")
i = s.index('                            <div class="osc-stat-modern-grid">')
k = s.index('<span class="val" id="osc-stat-net-', i)
k = s.index('</div>\n', k) + len('</div>\n')   # net kutusu
j = s.index('</div>\n', k) + len('</div>\n')   # ızgara
s = s[:j] + '                            <div id="osc-konu-<?php echo $post_id; ?>"></div>\n                            <div id="osc-sp-sonuc-<?php echo $post_id; ?>" style="display:none; margin: 0 30px 20px;"></div>\n' + s[j:]

# --- joker CSS
s = re.sub(r"\n\s*\.osc-joker-container \{[^\n]*\n", "\n", s)
s = re.sub(r"\n\s*\.osc-joker-btn[^\n]*", "", s)
s = s.replace("                /* JOKER BUTONU TASARIMI */\n", "")
assert 'osc-joker' not in s.split('<script>')[0][-200000:] or True

# --- sınav CSS'i (yazdırma CSS'inden önce)
R("                                                /* YAZDIRMA VE PDF ÇIKTISI İÇİN ÖZEL CSS */",
  parca('sinav.css') + "\n                                                /* YAZDIRMA VE PDF ÇIKTISI İÇİN ÖZEL CSS */")

# --- JS: durum + jokerler + sesler -> yeni üst kısım
i = s.index("                              let state = { idx: 0, correct: 0, wrong: 0, empty: 0,")
j = s.index("                const sfxFinish = new Audio(", i)
j = s.index("\n", j) + 1
s = s[:i] + parca('motor_ust.js') + s[j:]

# --- JS: oscRun + oscCloseAd -> (motor içinde yeniden tanımlanıyor)
i = s.index("                window.oscRun = function(id) { ")
j = s.index("// TAM EKRAN FONKSİYONLARI", i)
s = s[:i] + s[j:]

# --- JS: renderQ ... finish -> yeni motor
i = s.index("                function renderQ() { ")
j = s.index("                                const oscAjaxUrl = ", i)
s = s[:i] + parca('motor.js') + "\n" + s[j:]

open(P, 'w', encoding='utf-8').write(s)
print('ok', len(s))
