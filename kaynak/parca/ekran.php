                   <div class="osc-screen osc-quiz" style="display:none;">
    <div id="osc-fs-bar-<?php echo $post_id; ?>" style="display:none; position: sticky; top: 15px; z-index: 9999; text-align: center; margin-bottom: 18px; pointer-events: none;">
        <button onclick="oscExitFS()" style="pointer-events: auto; background: linear-gradient(135deg, rgba(59, 130, 246, 0.9) 0%, rgba(99, 102, 241, 0.9) 100%); border: 1px solid rgba(255, 255, 255, 0.3); color: #fff; padding: 8px 20px; border-radius: 50px; font-size: 13.5px; font-weight: 700; cursor: pointer; box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4); display: inline-flex; align-items: center; gap: 8px;">
            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"></path></svg>
            Tam Ekrandan Çık
        </button>
    </div>
                        <div class="osc-ex">
                            <div class="osc-ex-ust">
                                <div class="osc-ex-ad"><span class="osc-ex-tur"><?php echo esc_html($exam['ad']); ?></span><b><?php echo esc_html(get_the_title($post_id)); ?></b></div>
                                <div class="osc-ex-sure" id="osc-sure-kutu-<?php echo $post_id; ?>" title="Kalan süre"><span class="dashicons dashicons-clock"></span><span id="osc-timer-<?php echo $post_id; ?>">--:--</span></div>
                                <button type="button" class="osc-ex-optik-ac" onclick="OSCX[<?php echo $post_id; ?>].optik()"><span class="dashicons dashicons-editor-ol"></span> Cevap kağıdı <b id="osc-optik-say-<?php echo $post_id; ?>">0/<?php echo count($data); ?></b></button>
                                <button type="button" class="osc-ex-bitir" onclick="OSCX[<?php echo $post_id; ?>].bitir()">Sınavı Bitir</button>
                            </div>
                            <div class="osc-progress-track"><div id="osc-bar-fill-<?php echo $post_id; ?>" style="width:0%"></div></div>
                            <div class="osc-ex-govde">
                                <div class="osc-ex-ana">
                                    <div class="osc-ex-qbas">
                                        <span class="osc-ex-no">Soru <b id="osc-curr-<?php echo $post_id; ?>">1</b> / <?php echo count($data); ?></span>
                                        <button type="button" id="osc-flag-<?php echo $post_id; ?>" class="osc-ex-flag" onclick="OSCX[<?php echo $post_id; ?>].flag()"><span class="dashicons dashicons-flag"></span> Sonra döneceğim</button>
                                    </div>
                                    <div id="osc-render-area-<?php echo $post_id; ?>" class="osc-question-area"></div>
                                    <div class="osc-ex-nav">
                                        <button type="button" id="osc-prev-<?php echo $post_id; ?>" onclick="OSCX[<?php echo $post_id; ?>].git(-1)">‹ Önceki</button>
                                        <button type="button" class="bos" onclick="OSCX[<?php echo $post_id; ?>].bos()">Boş bırak</button>
                                        <button type="button" id="osc-next-<?php echo $post_id; ?>" class="ileri" onclick="OSCX[<?php echo $post_id; ?>].git(1)">Sonraki ›</button>
                                    </div>
                                    <div class="osc-ex-ipucu">İpucu: Klavyede A–E tuşları şık seçer, ← → tuşları sorular arasında gezdirir. Seçili şıkka tekrar tıklarsan soru boş kalır.</div>
                                </div>
                                <aside class="osc-ex-optik" id="osc-optik-<?php echo $post_id; ?>">
                                    <div class="osc-om-bas"><div><b>Cevap Kağıdı</b><small id="osc-say-<?php echo $post_id; ?>">0 / <?php echo count($data); ?> cevaplandı</small></div><button type="button" class="osc-om-kapat" onclick="OSCX[<?php echo $post_id; ?>].optik()" aria-label="Kapat">×</button></div>
                                    <div class="osc-om-lejant"><span><i class="l-c"></i>Cevaplandı</span><span><i class="l-f"></i>Sonra dön</span><span><i class="l-a"></i>Şu anki</span></div>
                                    <div class="osc-om-liste" id="osc-optik-list-<?php echo $post_id; ?>"></div>
                                    <button type="button" class="osc-om-bitir" onclick="OSCX[<?php echo $post_id; ?>].bitir()">Sınavı Bitir ve Teslim Et</button>
                                </aside>
                            </div>
                            <div class="osc-ex-onay" id="osc-onay-<?php echo $post_id; ?>" style="display:none"></div>
                        </div>
                    </div>

