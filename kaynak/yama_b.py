P = '/home/claude/work/osc/onlinesorucoz.php'
s = open(P, encoding='utf-8').read()

def R(a, b, n=1):
    global s
    c = s.count(a)
    assert c == n, (a[:90], c)
    s = s.replace(a, b)

# --- admin ayarlar: sınav türü + süre açıklaması + toplu konu
R("""                        <label><strong>Süre (Dk):</strong> <input type="number" name="osc_settings[duration]" value="<?php echo $settings['duration']??''; ?>" placeholder="Örn: 150"></label>""",
  """                        <label><strong>Süre (Dk):</strong> <input type="number" min="0" name="osc_settings[duration]" value="<?php echo $settings['duration']??''; ?>" placeholder="Boş: sınav türüne göre gerçek süre · 0: süresiz"></label>""")
R("""                    <div class="osc-grid-2" style="margin-top:10px;">
                        <label><strong>Reklam Sıklık (Opsiyonel):</strong>""",
  """                    <div class="osc-grid-2" style="margin-top:10px;">
                        <label><strong>Sınav türü (net hesabı):</strong> <select name="osc_settings[sinav_turu]"><?php $osc_st = $settings['sinav_turu'] ?? ''; ?><option value="" <?php selected($osc_st, ''); ?>>Otomatik (başlık/kategoriden algıla)</option><?php foreach (OSC_Sinav::turler() as $osc_k => $osc_t): ?><option value="<?php echo esc_attr($osc_k); ?>" <?php selected($osc_st, $osc_k); ?>><?php echo esc_html($osc_t['ad'] . ' — ' . ($osc_t['kural'] ? $osc_t['kural'] . ' yanlış 1 doğru' : 'yanlış götürmez') . ($osc_t['dk'] ? ' · soru başı ' . round($osc_t['dk'] * 60) . ' sn' : '')); ?></option><?php endforeach; ?></select></label>
                        <label><strong>Toplu konu ata:</strong> <span style="display:flex; gap:6px;"><input type="number" id="osc-konu-ilk" min="1" placeholder="İlk soru" style="width:90px"><input type="number" id="osc-konu-son" min="1" placeholder="Son soru" style="width:90px"><input type="text" id="osc-konu-ad" placeholder="Konu (ör. Üslü İfadeler)" style="flex:1"><button type="button" class="button" onclick="oscKonuAta()">Uygula</button></span><small style="color:#666">Konular, öğrencinin sonuç ekranındaki “en çok kaybettiğin konular” analizinde kullanılır.</small></label>
                    </div>
                    <div class="osc-grid-2" style="margin-top:10px;">
                        <label><strong>Reklam Sıklık (Opsiyonel):</strong>""")

# --- soru kartı: konu alanı
R("""<button type="button" class="button" onclick="delQ(${i})" style="color:red;">Sil</button></div></div><textarea class="osc-txt" style="height:50px; margin-bottom:5px; font-weight:normal !important;" oninput="setField(${i},'paragraph',this.value)" placeholder="Paragraf (Opsiyonel)">""",
  """<button type="button" class="button" onclick="delQ(${i})" style="color:red;">Sil</button></div></div><input type="text" class="osc-inp" list="osc-konu-dl" style="margin-bottom:6px; border:1px solid #c7d2fe; background:#f5f7ff;" value="${esc(q.konu||'')}" oninput="setField(${i},'konu',this.value)" placeholder="Konu (opsiyonel, ör. Üslü İfadeler)"><textarea class="osc-txt" style="height:50px; margin-bottom:5px; font-weight:normal !important;" oninput="setField(${i},'paragraph',this.value)" placeholder="Paragraf (Opsiyonel)">""")
R("""                    $('#osc_data').val(JSON.stringify(questions));
                }
                """,
  """                    const ks = []; questions.forEach(q => { const k = String(q.konu||'').trim(); if (k && ks.indexOf(k) < 0) ks.push(k); });
                    let dl = document.getElementById('osc-konu-dl'); if (!dl) { dl = document.createElement('datalist'); dl.id = 'osc-konu-dl'; document.body.appendChild(dl); }
                    dl.innerHTML = ks.map(k => '<option value="' + esc(k) + '">').join('');
                    $('#osc_data').val(JSON.stringify(questions));
                }
                window.oscKonuAta = function () {
                    const a = parseInt($('#osc-konu-ilk').val(), 10), b = parseInt($('#osc-konu-son').val(), 10), k = String($('#osc-konu-ad').val() || '').trim();
                    if (!a || !b || a > b || !k) { alert('İlk ve son soru numarasını ve konu adını yazın.'); return; }
                    for (let i = a - 1; i < Math.min(b, questions.length); i++) questions[i].konu = k;
                    render(); $('#osc-konu-ad').val('');
                };
                """)

open(P, 'w', encoding='utf-8').write(s)
print('ok')
s = open(P, encoding='utf-8').read()
R("return ($p && !empty($p->post_content) && has_shortcode($p->post_content, 'osc_anasayfa'));",
  "return ($p && !empty($p->post_content) && (has_shortcode($p->post_content, 'osc_anasayfa') || has_shortcode($p->post_content, 'osc_ogretmen_paneli') || has_shortcode($p->post_content, 'osc_ogrenci_paneli') || has_shortcode($p->post_content, 'osc_uyelik')));")
open(P, 'w', encoding='utf-8').write(s)
print('ok2')
s = open(P, encoding='utf-8').read()
R("            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';",
  "            if (typeof pdfjsLib !== 'undefined') pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';")
open(P, 'w', encoding='utf-8').write(s)
print('ok3')
