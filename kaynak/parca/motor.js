                /* ===================== SINAV MOTORU (gerçek sınav deneyimi) ===================== */
                function oscSecenekler(q) { return q.type === 'truefalse' ? { A: 'Doğru', B: 'Yanlış' } : (q.options || {}); }
                function oscCevapliSay() { let n = 0; state.answers.forEach(function (a) { if (!oscBosMu(a)) n++; }); return n; }
                function oscIsaretSay() { return Object.keys(state.flags).filter(function (k) { return state.flags[k]; }).length; }
                function oscEl(s) { return document.querySelector('#osc-app-' + quizId + ' ' + s); }

                function renderQ() {
                    const q = config.questions[state.idx], area = byId('osc-render-area-' + quizId);
                    byId('osc-curr-' + quizId).innerText = state.idx + 1;
                    const a = state.answers[state.idx];
                    let h = '';
                    if (q.konu) h += '<div class="osc-ex-konu">' + oscEsc(q.konu) + '</div>';
                    if (q.paragraph) h += '<div class="osc-q-paragraph">' + q.paragraph + '</div>';
                    h += '<div class="osc-q-root">' + (q.root || q.text || '') + '</div>';
                    if (q.type === 'classic') {
                        h += '<textarea class="osc-classic-input osc-ex-yazi" rows="5" placeholder="Cevabınızı buraya yazın...">' + oscEsc(a || '') + '</textarea><div class="osc-ex-not">Cevabın sınav bitince değerlendirilir.</div>';
                    } else if (q.type === 'gap') {
                        h += '<input type="text" class="osc-gap-input osc-ex-yazi" value="' + oscEsc(a || '') + '" placeholder="Cevabınızı yazın..." autocomplete="off">';
                    } else {
                        const o = oscSecenekler(q);
                        Object.keys(o).forEach(function (k) { if (!o[k]) return; h += '<button type="button" class="osc-opt-btn' + (a === k ? ' secili' : '') + '" data-k="' + k + '"><div class="osc-opt-char">' + k + '</div><div class="osc-opt-text">' + o[k] + '</div></button>'; });
                    }
                    area.innerHTML = h;
                    area.querySelectorAll('.osc-opt-btn').forEach(function (b) { b.addEventListener('click', function () { oscSec(b.dataset.k); }); });
                    const y = area.querySelector('.osc-ex-yazi');
                    if (y) y.addEventListener('input', function () { state.answers[state.idx] = y.value; oscOptikGuncelle(state.idx); oscUstGuncelle(); oscKaydetPlan(); });
                    byId('osc-flag-' + quizId).classList.toggle('aktif', !!state.flags[state.idx]);
                    byId('osc-prev-' + quizId).disabled = state.idx === 0;
                    byId('osc-next-' + quizId).innerHTML = state.idx === N - 1 ? 'Sınavı Bitir ✓' : 'Sonraki ›';
                    oscUstGuncelle(); oscOptikAktif();
                    area.classList.remove('osc-anim'); void area.offsetWidth; area.classList.add('osc-anim');
                }

                function oscSec(k) {
                    if (state.finished) return;
                    state.answers[state.idx] = state.answers[state.idx] === k ? null : k;
                    byId('osc-render-area-' + quizId).querySelectorAll('.osc-opt-btn').forEach(function (b) { b.classList.toggle('secili', b.dataset.k === state.answers[state.idx]); });
                    oscOptikGuncelle(state.idx); oscUstGuncelle(); oscKaydetPlan();
                }

                function oscUstGuncelle() {
                    const c = oscCevapliSay(), f = oscIsaretSay();
                    const s = byId('osc-say-' + quizId); if (s) s.innerText = c + ' / ' + N + ' cevaplandı' + (f ? ' · ' + f + ' işaretli' : '');
                    const bar = byId('osc-bar-fill-' + quizId); if (bar) bar.style.width = (N ? c / N * 100 : 0) + '%';
                    const ob = byId('osc-optik-say-' + quizId); if (ob) ob.innerText = c + '/' + N;
                }

                function oscOptikCiz() {
                    const list = byId('osc-optik-list-' + quizId); if (!list) return;
                    let h = '';
                    config.questions.forEach(function (q, i) {
                        h += '<div class="osc-om-row" data-i="' + i + '"><button type="button" class="osc-om-no" data-git="' + i + '">' + (i + 1) + '</button><span class="osc-om-bub">';
                        if (q.type === 'gap' || q.type === 'classic') h += '<button type="button" class="osc-om-yazi" data-git="' + i + '">✎ yazılı</button>';
                        else {
                            const o = oscSecenekler(q);
                            Object.keys(o).forEach(function (k) { if (!o[k]) return; h += '<button type="button" class="osc-om-b" data-i="' + i + '" data-k="' + k + '">' + (q.type === 'truefalse' ? (k === 'A' ? 'D' : 'Y') : k) + '</button>'; });
                        }
                        h += '</span><span class="osc-om-flag" title="Sonra döneceğim">⚑</span></div>';
                    });
                    list.innerHTML = h;
                    list.onclick = function (e) {
                        const b = e.target.closest('.osc-om-b');
                        if (b) { const i = +b.dataset.i; state.answers[i] = state.answers[i] === b.dataset.k ? null : b.dataset.k; oscOptikGuncelle(i); if (i === state.idx) renderQ(); else oscGit(i); oscKaydetPlan(); return; }
                        const g = e.target.closest('[data-git]'); if (g) { oscGit(+g.dataset.git); oscOptikKapat(); }
                    };
                    config.questions.forEach(function (q, i) { oscOptikGuncelle(i); });
                }
                function oscOptikGuncelle(i) {
                    const row = oscEl('.osc-om-row[data-i="' + i + '"]'); if (!row) return;
                    const a = state.answers[i];
                    row.classList.toggle('cevapli', !oscBosMu(a));
                    row.classList.toggle('isaretli', !!state.flags[i]);
                    row.querySelectorAll('.osc-om-b').forEach(function (b) { b.classList.toggle('dolu', b.dataset.k === a); });
                }
                function oscOptikAktif() {
                    const list = byId('osc-optik-list-' + quizId); if (!list) return;
                    list.querySelectorAll('.osc-om-row.aktif').forEach(function (r) { r.classList.remove('aktif'); });
                    const row = list.querySelector('.osc-om-row[data-i="' + state.idx + '"]'); if (!row) return;
                    row.classList.add('aktif');
                    const top = row.offsetTop - list.offsetTop;
                    if (top < list.scrollTop || top + row.offsetHeight > list.scrollTop + list.clientHeight) list.scrollTop = top - list.clientHeight / 2;
                }
                function oscOptikKapat() { const o = byId('osc-optik-' + quizId); if (o) o.classList.remove('acik'); }

                function oscGit(i) {
                    if (state.finished || i < 0 || i >= N) return;
                    state.idx = i; renderQ();
                    const ana = oscEl('.osc-ex-ana');
                    if (ana && ana.getBoundingClientRect().top < 0) ana.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                function oscIleri() {
                    if (state.idx >= N - 1) { oscBitirSor(); return; }
                    state.navCount++;
                    const freq = parseInt(config.ad_freq, 10);
                    if (!OT && freq > 0 && config.ad_code && state.navCount % freq === 0) {
                        state.pendingNav = state.idx + 1;
                        byId('osc-ad-modal-' + quizId).style.display = 'flex';
                        return;
                    }
                    oscGit(state.idx + 1);
                }
                window.oscCloseAd = function (id) {
                    const m = byId('osc-ad-modal-' + id); if (m) m.style.display = 'none';
                    if (id === quizId && state.pendingNav !== null) { const n = state.pendingNav; state.pendingNav = null; oscGit(n); }
                };

                function oscBitirSor() {
                    if (state.finished) return;
                    const bos = [], isaret = [];
                    state.answers.forEach(function (a, i) { if (oscBosMu(a)) bos.push(i + 1); if (state.flags[i]) isaret.push(i + 1); });
                    const kutu = byId('osc-onay-' + quizId);
                    let h = '<div class="osc-onay-k"><div class="osc-onay-ic">📝</div><h3>Sınavı bitirmek istiyor musun?</h3>';
                    h += '<div class="osc-onay-oz"><div><b>' + (N - bos.length) + '</b><small>Cevaplanan</small></div><div><b>' + bos.length + '</b><small>Boş</small></div><div><b>' + isaret.length + '</b><small>Sonra dön</small></div></div>';
                    if (bos.length) h += '<p>Boş sorular: ' + bos.slice(0, 25).join(', ') + (bos.length > 25 ? '…' : '') + '</p>';
                    if (isaret.length) h += '<p>İşaretlediğin sorular: ' + isaret.join(', ') + '</p>';
                    h += '<div class="osc-onay-btn">' + ((bos.length || isaret.length) ? '<button type="button" class="geri" data-x="don">Sorulara dön</button>' : '<button type="button" class="geri" data-x="kapat">Vazgeç</button>') + '<button type="button" class="bitir" data-x="bitir">Sınavı bitir</button></div></div>';
                    kutu.innerHTML = h; kutu.style.display = 'flex';
                    kutu.onclick = function (e) {
                        const x = e.target.closest('[data-x]'); if (!x && e.target !== kutu) return;
                        kutu.style.display = 'none';
                        if (!x) return;
                        if (x.dataset.x === 'bitir') finish(false);
                        else if (x.dataset.x === 'don') { const ilk = isaret.length ? isaret[0] : bos[0]; oscGit((ilk || 1) - 1); }
                    };
                }

                OSCX[quizId] = {
                    git: function (d) { if (d > 0) oscIleri(); else oscGit(state.idx - 1); },
                    bos: function () { if (state.finished) return; state.answers[state.idx] = null; renderQ(); oscOptikGuncelle(state.idx); oscKaydetPlan(); },
                    flag: function () { if (state.finished) return; state.flags[state.idx] = !state.flags[state.idx]; byId('osc-flag-' + quizId).classList.toggle('aktif', !!state.flags[state.idx]); oscOptikGuncelle(state.idx); oscUstGuncelle(); },
                    bitir: oscBitirSor,
                    optik: function () { const o = byId('osc-optik-' + quizId); if (o) o.classList.toggle('acik'); }
                };

                document.addEventListener('keydown', function (e) {
                    if (!state.started || state.finished || e.ctrlKey || e.metaKey || e.altKey) return;
                    const scr = oscEl('.osc-quiz'); if (!scr || scr.style.display === 'none') return;
                    const tg = (e.target && e.target.tagName) || '';
                    if (/INPUT|TEXTAREA|SELECT/.test(tg)) return;
                    const q = config.questions[state.idx], key = (e.key || '').toUpperCase();
                    if (key === 'ARROWRIGHT') { e.preventDefault(); oscIleri(); return; }
                    if (key === 'ARROWLEFT') { e.preventDefault(); oscGit(state.idx - 1); return; }
                    if (/^[A-E]$/.test(key) && q.type === 'multiple' && q.options && q.options[key]) { e.preventDefault(); oscSec(key); }
                    if (q.type === 'truefalse' && (key === 'D' || key === 'Y')) { e.preventDefault(); oscSec(key === 'D' ? 'A' : 'B'); }
                });

                /* ---- süre ---- */
                function oscSureYaz(left) {
                    const t = byId('osc-timer-' + quizId); if (!t) return;
                    if (left >= 86400) { t.innerText = Math.floor(left / 86400) + ' gün ' + Math.floor((left % 86400) / 3600) + ' sa'; return; }
                    const h = Math.floor(left / 3600), m = Math.floor((left % 3600) / 60), s = left % 60, p = function (x) { return x < 10 ? '0' + x : x; };
                    t.innerText = (h > 0 ? p(h) + ':' : '') + p(m) + ':' + p(s);
                    const k = byId('osc-sure-kutu-' + quizId); if (k) { k.classList.toggle('az', left <= 300); k.classList.toggle('cok-az', left <= 60); }
                }
                function oscZamanlayici(sn) {
                    clearInterval(state.timer);
                    state.deadline = Date.now() + sn * 1000;
                    const tick = function () {
                        const left = Math.max(0, Math.ceil((state.deadline - Date.now()) / 1000));
                        oscSureYaz(left);
                        if (left <= 0) { clearInterval(state.timer); finish(true); }
                    };
                    tick(); state.timer = setInterval(tick, 500);
                }

                /* ---- başlat ---- */
                function oscBasla(kalan) {
                    if (state.started) return;
                    state.started = true;
                    if (OT && Array.isArray(OT.cevaplar)) OT.cevaplar.forEach(function (v, i) { if (i < N) state.answers[i] = v; });
                    oscEl('.osc-start').style.display = 'none';
                    oscEl('.osc-quiz').style.display = 'block';
                    oscOptikCiz(); renderQ();
                    const sn = OT ? (kalan || 0) : state.timeLeft;
                    if (sn > 0) oscZamanlayici(sn); else { const t = byId('osc-timer-' + quizId); if (t) t.innerText = 'Süresiz'; }
                    if (OT && OT.tur === 'canli') oscIzle();
                }
                window.oscRun = function (id) {
                    if (id !== quizId) return;
                    var elem = byId('osc-app-' + id);
                    try { if (elem.requestFullscreen) elem.requestFullscreen().catch(function () {}); else if (elem.webkitRequestFullscreen) elem.webkitRequestFullscreen(); } catch (e) {}
                    oscBasla(0);
                };

                /* ---- puanlama ---- */
                function oscOzetle(durum, hist) {
                    let d = 0, y = 0, b = 0, n = 0; const konular = {};
                    durum.forEach(function (s, i) {
                        const q = config.questions[i] || {}, k = String(q.konu || '').trim() || '—';
                        const kk = konular[k] || (konular[k] = { d: 0, y: 0, b: 0, t: 0 });
                        kk.t++;
                        if (s === 'd') { d++; kk.d++; } else if (s === 'y') { y++; kk.y++; } else if (s === 'k') { d += 0.5; y += 0.5; kk.d += 0.5; kk.y += 0.5; } else if (s === 'n') { n++; kk.t--; } else { b++; kk.b++; }
                    });
                    const net = EXAM.kural > 0 ? d - y / EXAM.kural : d, puanlanan = N - n;
                    return { d: d, y: y, b: b, n: n, net: oscSay(net), puan: puanlanan > 0 ? Math.round(d / puanlanan * 100) : 0, durum: durum, konular: konular, hist: hist || [] };
                }
                function oscPuanla() {
                    const durum = [], hist = [];
                    config.questions.forEach(function (q, i) {
                        const a = state.answers[i]; let s, ek = {};
                        if (oscBosMu(a)) s = 'b';
                        else if (q.type === 'classic') {
                            const res = OSCGrade.gradeClassic((q.options && q.options.A) || '', a);
                            ek = { verdict: res.verdict, sim: Math.round((res.score || 0) * 100) };
                            s = res.verdict === 'correct' ? 'd' : res.verdict === 'partial' ? 'k' : res.verdict === 'nomodel' ? 'n' : res.verdict === 'empty' ? 'b' : 'y';
                        } else if (q.type === 'gap') s = OSCGrade.sameGap((q.options && q.options.A) || '', a) ? 'd' : 'y';
                        else s = a === q.correct ? 'd' : 'y';
                        durum.push(s);
                        hist.push(Object.assign({ qIdx: i, isCorrect: s === 'd', skipped: s === 'b', userKey: (q.type === 'gap' || q.type === 'classic') ? undefined : (a || undefined), userAns: (q.type === 'gap' || q.type === 'classic') ? (a || '') : undefined }, ek));
                    });
                    return oscOzetle(durum, hist);
                }

                function oscKonuHtml(r) {
                    const AD = { d: 'Doğru', y: 'Yanlış', b: 'Boş', k: 'Kısmen doğru', n: 'Değerlendirilmedi' };
                    let h = '<div class="osc-sk"><div class="osc-sk-bas">Soru haritası</div><div class="osc-sk-harita">' + r.durum.map(function (s, i) { return '<span class="osc-sk-k ' + s + '" title="' + (i + 1) + '. soru: ' + AD[s] + '">' + (i + 1) + '</span>'; }).join('') + '</div>';
                    h += '<div class="osc-sk-lejant"><span><i class="d"></i>Doğru</span><span><i class="y"></i>Yanlış</span><span><i class="b"></i>Boş</span></div>';
                    const ks = Object.keys(r.konular).filter(function (k) { return k !== '—' && r.konular[k].t > 0; });
                    if (ks.length) {
                        if (r.konular['—'] && r.konular['—'].t > 0) ks.push('—');
                        const satir = ks.map(function (k) { const v = r.konular[k], net = EXAM.kural > 0 ? v.d - v.y / EXAM.kural : v.d; return { k: k === '—' ? 'Konusu belirtilmemiş' : k, v: v, net: net, oran: v.t ? Math.max(0, net) / v.t : 0, kayip: v.y + v.b }; });
                        const kotu = satir.filter(function (x) { return x.kayip > 0; }).sort(function (a, b) { return (b.kayip - a.kayip) || (a.oran - b.oran); }).slice(0, 3);
                        if (kotu.length) h += '<div class="osc-sk-kotu"><b>🎯 En çok kaybettiğin konular</b>' + kotu.map(function (x, j) { return '<div class="osc-sk-kotu-s"><span class="no">' + (j + 1) + '</span><div><strong>' + oscEsc(x.k) + '</strong><small>' + x.v.t + ' sorudan ' + oscSay(x.kayip) + ' tanesini kaçırdın (' + oscSay(x.v.y) + ' yanlış, ' + x.v.b + ' boş)</small></div></div>'; }).join('') + '<p>Bu konuları tekrar edip benzer testler çözmeni öneririz.</p></div>';
                        else h += '<div class="osc-sk-tam">🎉 Bütün konularda tam isabet!</div>';
                        h += '<div class="osc-sk-tablo-kap"><table class="osc-sk-tablo"><thead><tr><th>Konu</th><th>Soru</th><th>D</th><th>Y</th><th>B</th><th>Net</th><th>Başarı</th></tr></thead><tbody>' + satir.map(function (x) { const yuzde = Math.round(x.oran * 100); return '<tr><td>' + oscEsc(x.k) + '</td><td>' + x.v.t + '</td><td class="d">' + oscSay(x.v.d) + '</td><td class="y">' + oscSay(x.v.y) + '</td><td>' + x.v.b + '</td><td><b>' + oscSay(x.net) + '</b></td><td class="bar"><div class="osc-sk-bar"><i style="width:' + yuzde + '%;background:' + (yuzde >= 70 ? '#10b981' : yuzde >= 40 ? '#f59e0b' : '#ef4444') + '"></i></div><small>%' + yuzde + '</small></td></tr>'; }).join('') + '</tbody></table></div>';
                    } else {
                        const kac = []; r.durum.forEach(function (s, i) { if (s === 'y' || s === 'b') kac.push(i + 1); });
                        if (kac.length) h += '<div class="osc-sk-kotu"><b>🎯 Kaçırdığın sorular</b><p>' + kac.join(', ') + '</p></div>';
                    }
                    return h + '</div>';
                }

                function oscSonucGoster(r, kayit, otomatik) {
                    state.history = r.hist; state.correct = r.d; state.wrong = r.y; state.empty = r.b;
                    oscEl('.osc-quiz').style.display = 'none';
                    oscEl('.osc-start').style.display = 'none';
                    oscEl('.osc-result').style.display = 'block';
                    byId('osc-final-score-' + quizId).innerText = r.puan;
                    byId('osc-stat-correct-' + quizId).innerText = oscSay(r.d);
                    byId('osc-stat-wrong-' + quizId).innerText = oscSay(r.y);
                    byId('osc-stat-empty-' + quizId).innerText = r.b;
                    byId('osc-stat-net-' + quizId).innerText = r.net;
                    const kl = byId('osc-net-kural-' + quizId);
                    if (kl) kl.innerHTML = '<b>' + oscEsc(EXAM.ad) + '</b> · ' + (EXAM.kural > 0 ? EXAM.kural + ' yanlış 1 doğruyu götürür' : 'yanlışlar doğruyu götürmez') + (r.n ? ' · ' + r.n + ' açık uçlu soru puana katılmadı' : '');
                    const ot = byId('osc-oto-' + quizId); if (ot) ot.style.display = otomatik ? 'block' : 'none';
                    const ka = byId('osc-konu-' + quizId); if (ka) ka.innerHTML = oscKonuHtml(r);
                    if (OT) {
                        ['#osc-name-input-area-' + quizId, '#osc-leaderboard-' + quizId].forEach(function (s) { const e = document.querySelector(s); if (e) e.style.display = 'none'; });
                        document.querySelectorAll('#osc-app-' + quizId + ' .osc-share-buttons, #osc-app-' + quizId + ' .osc-actions > .osc-btn-modern-restart').forEach(function (e) { e.style.display = 'none'; });
                        const ac = byId('osc-sp-sonuc-' + quizId);
                        if (ac) { ac.style.display = 'block'; ac.innerHTML = r.anahtarVar ? '<button type="button" class="osc-btn-modern-restart" onclick="oscShowMistakes(' + quizId + ')" style="width:100%;justify-content:center;background:#fef2f2;border-color:#fecaca;color:#991b1b"><span class="dashicons dashicons-visibility"></span> Yanlışları ve doğru cevapları incele</button><div id="osc-mistakes-area-' + quizId + '" style="display:none;text-align:left;margin:12px 0 0;padding:20px;background:#f8fafc;border-radius:12px;border:2px dashed #cbd5e1"></div>' : '<div class="osc-sp-not">Doğru cevaplar öğretmenin oturumu bitirince burada açılacak.</div>'; }
                    }
                    if (kayit) {
                        sfxFinish.play().catch(function () {});
                        const fd = new URLSearchParams(); fd.append('action', 'osc_track_solve'); fd.append('quiz_id', quizId); fd.append('parent_id', <?php echo get_the_ID() ? get_the_ID() : $post_id; ?>);
                        fetch(OSC_AJAX, { method: 'POST', body: fd }).catch(function () {});
                        if (config.uye && config.cn) {
                            const kz = {}; Object.keys(r.konular).forEach(function (k) { if (k !== '—' && r.konular[k].t > 0) kz[k] = r.konular[k]; });
                            const yl = []; r.durum.forEach(function (x, i) { if (x === 'y' || x === 'b') yl.push(i + 1); });
                            oscAjax('osc_sp_cozum_kaydet', { nonce: config.cn, quiz_id: quizId, d: r.d, y: r.y, b: r.b, net: r.net, puan: r.puan, soru: N, konu: JSON.stringify(kz), yanlis: yl.join(','), url: config.url || location.href.split('#')[0] }).catch(function () {});
                        } else if (config.uye_url && ka) {
                            ka.insertAdjacentHTML('afterend', '<a class="osc-uye-davet" href="' + oscEsc(config.uye_url + (config.uye_url.indexOf('?') < 0 ? '?' : '&') + 'git=' + encodeURIComponent(location.href.split('#')[0])) + '">📈 <b>Sonuçlarını kaydet, gelişimini takip et.</b> Ücretsiz öğrenci üyeliği ile çözdüğün testler, netlerin ve konu karnen panelinde birikir →</a>');
                        }
                        if (r.puan >= 50 && typeof confetti === 'function') {
                            var son = Date.now() + 3000, def = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 9999, colors: ['#6366f1', '#8b5cf6', '#10b981', '#f59e0b', '#ec4899'] };
                            var iv = setInterval(function () { var kalan = son - Date.now(); if (kalan <= 0) return clearInterval(iv); confetti(Object.assign({}, def, { particleCount: 50 * (kalan / 3000), origin: { x: Math.random(), y: Math.random() - 0.2 } })); }, 250);
                        }
                    }
                    const res = oscEl('.osc-result'); if (res && res.getBoundingClientRect().top < 0) res.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }

                function finish(otomatik) {
                    if (state.finished) return;
                    state.finished = true; clearInterval(state.timer); clearInterval(state.izle);
                    const kutu = byId('osc-onay-' + quizId); if (kutu) kutu.style.display = 'none';
                    if (document.fullscreenElement || document.webkitIsFullScreen) { try { window.oscExitFS(); } catch (e) {} }
                    if (OT) { oscTeslim(otomatik); return; }
                    oscSonucGoster(oscPuanla(), true, otomatik);
                }

                /* ---- canlı sınıf / ödev oturumu ---- */
                function oscAjax(action, data) {
                    const fd = new URLSearchParams(); fd.append('action', action);
                    Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
                    return fetch(OSC_AJAX, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
                }
                function oscOt(ek) { return Object.assign({ kod: OT.kod, token: OT.token }, ek || {}); }
                let oscKayitT = null;
                function oscKaydetPlan() { if (!OT || state.finished) return; clearTimeout(oscKayitT); oscKayitT = setTimeout(oscKaydetSimdi, 1200); }
                function oscKaydetSimdi() { if (!OT || state.finished) return; clearTimeout(oscKayitT); oscAjax('osc_sp_kaydet', oscOt({ cevaplar: JSON.stringify(state.answers) })).catch(function () {}); }
                document.addEventListener('visibilitychange', function () {
                    if (document.visibilityState !== 'hidden' || !OT || !state.started || state.finished || !navigator.sendBeacon) return;
                    const fd = new FormData(); fd.append('action', 'osc_sp_kaydet'); fd.append('kod', OT.kod); fd.append('token', OT.token); fd.append('cevaplar', JSON.stringify(state.answers));
                    navigator.sendBeacon(OSC_AJAX, fd);
                });
                function oscTeslim(otomatik) {
                    const area = byId('osc-render-area-' + quizId);
                    oscEl('.osc-quiz').classList.add('osc-gonderiliyor');
                    const dene = function (n) {
                        oscAjax('osc_sp_teslim', oscOt({ cevaplar: JSON.stringify(state.answers) })).then(function (res) {
                            oscEl('.osc-quiz').classList.remove('osc-gonderiliyor');
                            if (!res || !res.success) { state.finished = false; alert((res && res.data) || 'Teslim edilemedi.'); return; }
                            oscOturumSonuc(res.data, otomatik);
                        }).catch(function () {
                            if (n < 4) setTimeout(function () { dene(n + 1); }, 2000 * (n + 1));
                            else { oscEl('.osc-quiz').classList.remove('osc-gonderiliyor'); state.finished = false; alert('İnternet bağlantısı yok gibi görünüyor. Bağlantı gelince “Sınavı Bitir” düğmesine tekrar basın; cevaplarınız kaybolmaz.'); }
                        });
                    };
                    dene(0);
                }
                function oscOturumSonuc(data, otomatik) {
                    if (data.anahtar) data.anahtar.forEach(function (a, i) { const q = config.questions[i]; if (!q) return; if (q.type === 'gap' || q.type === 'classic') q.options = { A: a }; else q.correct = a; });
                    const cev = Array.isArray(data.cevaplar) ? data.cevaplar : state.answers;
                    const hist = data.sonuc.durum.map(function (s, i) { const q = config.questions[i] || {}, a = cev[i]; return { qIdx: i, isCorrect: s === 'd', skipped: s === 'b', userKey: (q.type === 'gap' || q.type === 'classic') ? undefined : (a || undefined), userAns: (q.type === 'gap' || q.type === 'classic') ? (a || '') : undefined, verdict: s === 'n' ? 'nomodel' : (s === 'b' ? 'empty' : 'mismatch') }; });
                    const r = oscOzetle(data.sonuc.durum, hist); r.anahtarVar = !!data.anahtar;
                    state.finished = true; state.started = true;
                    oscSonucGoster(r, false, otomatik);
                    if (!data.anahtar) {
                        const bekle = setInterval(function () {
                            oscAjax('osc_sp_durum', oscOt()).then(function (x) { if (x && x.success && x.data.anahtar) { clearInterval(bekle); oscOturumSonuc(x.data.sonuc_veri, false); } }).catch(function () {});
                        }, 15000);
                    }
                }
                function oscIzle() {
                    clearInterval(state.izle);
                    state.izle = setInterval(function () {
                        if (state.finished) return clearInterval(state.izle);
                        oscAjax('osc_sp_durum', oscOt()).then(function (x) {
                            if (!x || !x.success) return;
                            if (x.data.durum === 'bitti') { finish(true); return; }
                            if (x.data.kalan > 0 && Math.abs(state.deadline - (Date.now() + x.data.kalan * 1000)) > 4000) oscZamanlayici(x.data.kalan);
                        }).catch(function () {});
                    }, 8000);
                }
                function oscOturumAc() {
                    const kutu = byId('osc-sp-durum-' + quizId); if (!kutu) return;
                    if (OT.katilim_durum === 'bitti' && OT.sonuc_veri) { oscOturumSonuc(OT.sonuc_veri, false); return; }
                    if (OT.durum === 'bitti') { kutu.innerHTML = '<div class="osc-sp-mesaj kirmizi">Bu oturum sona erdi.</div>'; return; }
                    if (OT.tur === 'canli') {
                        if (OT.durum === 'basladi') { oscBasla(OT.kalan); return; }
                        kutu.innerHTML = '<div class="osc-sp-bekle"><span class="osc-sp-nokta"><i></i><i></i><i></i></span><b>Öğretmeninin sınavı başlatması bekleniyor</b><small>Bu sayfayı kapatma. Sınav başlayınca kendiliğinden açılacak.</small></div>';
                        const t = setInterval(function () {
                            oscAjax('osc_sp_durum', oscOt()).then(function (x) {
                                if (!x || !x.success) return;
                                if (x.data.durum === 'basladi') { clearInterval(t); oscBasla(x.data.kalan); }
                                else if (x.data.durum === 'bitti') { clearInterval(t); kutu.innerHTML = '<div class="osc-sp-mesaj kirmizi">Bu oturum sona erdi.</div>'; }
                            }).catch(function () {});
                        }, 3000);
                        return;
                    }
                    if (OT.katilim_durum === 'cozuyor') { oscBasla(OT.kalan); return; }
                    kutu.innerHTML = '<div class="osc-sp-odev">' + (OT.son_tarih_metin ? '<div class="osc-sp-son">Son teslim: <b>' + oscEsc(OT.son_tarih_metin) + '</b></div>' : '') + '<button type="button" class="osc-btn-modern-start" data-x="basla">SINAVA BAŞLA <span class="dashicons dashicons-arrow-right-alt2" style="margin-top:4px"></span></button><small>' + (OT.sure ? 'Başladıktan sonra ' + OT.sure + ' dakikan olacak; süre dolunca sınav kendiliğinden teslim edilir.' : 'Süre sınırı yok; son teslim tarihine kadar bitirmelisin.') + '</small></div>';
                    kutu.querySelector('[data-x=basla]').onclick = function (e) {
                        e.target.disabled = true;
                        oscAjax('osc_sp_basla', oscOt()).then(function (x) { if (!x || !x.success) { e.target.disabled = false; alert((x && x.data) || 'Başlatılamadı'); return; } OT.cevaplar = x.data.cevaplar || OT.cevaplar; oscBasla(x.data.kalan); }).catch(function () { e.target.disabled = false; alert('Bağlantı hatası'); });
                    };
                }
                if (OT) { if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', oscOturumAc); else setTimeout(oscOturumAc, 0); }
