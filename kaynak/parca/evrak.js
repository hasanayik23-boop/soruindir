/* =====================================================================
 * Evrak Hazırla: zümre / veli / ŞÖK tutanakları, BEP planı, öğrenci
 * kulübü evrakları. Taslak metinler bize özgüdür; isteğe bağlı olarak
 * yapay zekâ ile kişiselleştirilir. Evraklar yalnızca bu tarayıcıda
 * saklanır (öğrenci bilgileri sunucuya kaydedilmez).
 * ===================================================================== */
(function () {
  function BASLAT() {
  var C = window.OSC_EV || {}, kok = document.getElementById('osc-ev'); if (!kok) return;
  /* okul türü → sınıflar → dersler (genel kültür, seçmeli, meslek sırasıyla) */
  var OKUL_TUR = [['İlkokul', 1, 4], ['Ortaokul', 5, 8], ['İmam Hatip Ortaokulu', 5, 8], ['Anadolu Lisesi', 9, 12], ['Fen Lisesi', 9, 12], ['Sosyal Bilimler Lisesi', 9, 12], ['Anadolu İmam Hatip Lisesi', 9, 12], ['Mesleki ve Teknik Anadolu Lisesi', 9, 12], ['Güzel Sanatlar Lisesi', 9, 12], ['Spor Lisesi', 9, 12]];
  function turAralik(t) { for (var i = 0; i < OKUL_TUR.length; i++) if (OKUL_TUR[i][0] === t) return OKUL_TUR[i]; return null; }
  function turSec(P) { var t = P.tur || ''; if (!turAralik(t) && ORT.okulturu && turAralik(ORT.okulturu)) t = ORT.okulturu; if (!turAralik(t) && P.sinif) t = +P.sinif <= 4 ? 'İlkokul' : +P.sinif <= 8 ? 'Ortaokul' : 'Anadolu Lisesi'; return turAralik(t) ? t : ''; }
  function grupTur(g) { return /^Ortak/i.test(g) ? 'ortak' : /seçmeli|^Diğer$|diğer dersler/i.test(g) ? 'secmeli' : 'meslek'; }
  function meslekUygun(g, tur) {
    if (tur === 'İmam Hatip Ortaokulu') return /İmam Hatip Ortaokulu/i.test(g);
    if (tur === 'Anadolu İmam Hatip Lisesi') return /İmam Hatip Lisesi/i.test(g);
    if (tur === 'Mesleki ve Teknik Anadolu Lisesi') return /^Meslek:/.test(g);
    if (tur === 'Güzel Sanatlar Lisesi') return /Güzel sanatlar/i.test(g);
    if (tur === 'Spor Lisesi') return /Spor lisesi/i.test(g);
    return false;
  }
  function dersSecHtml(dl, sec, tur) {
    var ortak = [], secm = [], mes = [], gor = {}, mesAd = {};
    (dl || []).forEach(function (g) { if (grupTur(g.g) === 'meslek' && meslekUygun(g.g, tur)) g.d.forEach(function (x) { mesAd[x] = 1; }); });
    (dl || []).forEach(function (g) {
      var k = grupTur(g.g);
      if (k === 'meslek') { if (meslekUygun(g.g, tur)) mes.push(g); return; }
      if (k === 'secmeli') g = { g: g.g, d: g.d.filter(function (x) { return !mesAd[x]; }) };
      if (k === 'secmeli' && /Ortaokul seçmeli/i.test(g.g) && turAralik(tur) && turAralik(tur)[1] >= 9) return;
      g.d.forEach(function (x) { if (gor[x]) return; gor[x] = 1; (k === 'ortak' ? ortak : secm).push(x); });
    });
    var sir = function (a, b) { return a.localeCompare(b, 'tr'); }; ortak.sort(sir); secm.sort(sir);
    var o = function (x) { return '<option' + (sec === x ? ' selected' : '') + '>' + esc(x) + '</option>'; };
    var grup = function (ad, l) { return l.length ? '<optgroup label="' + esc(ad) + '">' + l.map(o).join('') + '</optgroup>' : ''; };
    mes.sort(function (a, b) { return sir(a.g, b.g); });
    return grup('Genel kültür dersleri', ortak) + grup('Seçmeli dersler', secm) + mes.map(function (g) { return grup(g.g.replace(/^Meslek: /, 'Meslek dersleri – '), g.d.filter(function (x) { if (gor[x]) return false; gor[x] = 1; return true; })); }).join('');
  }
  function turSecHtml(P) { var t = turSec(P); return '<label><span>Okul türü</span><select data-yp="tur"><option value="">Seçin…</option>' + OKUL_TUR.map(function (x) { return '<option' + (t === x[0] ? ' selected' : '') + '>' + x[0] + '</option>'; }).join('') + '</select></label>'; }
  function sinifSecHtml(P, sin) { var a = turAralik(turSec(P)); var l = a ? sin.filter(function (x) { return x >= a[1] && x <= a[2]; }) : []; return '<label><span>Sınıf</span><select data-yp="sinif"' + (a ? '' : ' disabled') + '><option value="">' + (a ? 'Seçin…' : 'Önce okul türü') + '</option>' + l.map(function (x) { return '<option value="' + x + '"' + (+P.sinif === x ? ' selected' : '') + '>' + x + '. sınıf</option>'; }).join('') + '</select></label>'; }
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function ls(k, v) { try { if (v === undefined) return JSON.parse(localStorage.getItem(k) || 'null'); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, JSON.stringify(v)); } catch (e) { return null; } }
  function trUp(s) { return String(s || '').replace(/i/g, 'İ').replace(/ı/g, 'I').toUpperCase(); }
  function bugun() { var d = new Date(); return ('0' + d.getDate()).slice(-2) + '.' + ('0' + (d.getMonth() + 1)).slice(-2) + '.' + d.getFullYear(); }
  function yil() { var d = new Date(), y = d.getFullYear(); return d.getMonth() >= 7 ? y + '-' + (y + 1) : (y - 1) + '-' + y; }
  function gunAdi(t) { var m = String(t || '').match(/(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})/); if (!m) return ''; var d = new Date(+m[3], +m[2] - 1, +m[1]); return isNaN(d) ? '' : ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'][d.getDay()]; }
  function parcala(x) { x = String(x); return x.split(/\s+[-–]\s+/.test(x) ? /\s+[-–]\s+/ : /\s*[-–]\s*/).map(function (y) { return y.trim(); }); }
  function satirlar(s) { return String(s || '').split('\n').map(function (x) { return x.trim(); }).filter(Boolean); }
  function bildir(m, t) { var e = document.createElement('div'); e.className = 'ev-tost ' + (t || ''); e.textContent = m; document.body.appendChild(e); setTimeout(function () { e.classList.add('on'); }, 10); setTimeout(function () { e.remove(); }, 4200); }
  function sunucu(a, d) {
    var fd = new FormData(); fd.append('action', 'osc_ev_' + a); fd.append('nonce', C.nonce); fd.append('veri', JSON.stringify(d || {}));
    return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (!j || !j.success) throw new Error((j && j.data) || 'Bir hata oluştu'); return j.data; });
  }

  /* ---------------- öğrenci kulüpleri (Sosyal Etkinlikler Politika Belgesi, 2026) ---------------- */
  var KULUP = {
    'Zorunlu kulüpler': ['Kızılay Kulübü', 'Yeşilay Kulübü', 'Beyazay Kulübü', 'Siberay Kulübü', 'Tarih ve Kültürel Miras Kulübü', 'Tabiat ve Yaşanabilir Çevre Kulübü', 'Sağlıklı Yaşam ve Fiziksel Aktivite Kulübü', 'Yaşam Becerileri Kulübü'],
    'Kültür Dünyamız': ['Kültür ve Yayıncılık Kulübü', 'Medeniyet ve Düşünce Kulübü', 'Felsefe ve Münazara Kulübü', 'Dil, Hitabet ve İletişim Kulübü', 'Gezi ve Turizm Kulübü', 'Kapsayıcı Yaşam Kulübü', 'Ahilik ve Fütüvvet Kulübü'],
    'Sanat': ['Fotoğrafçılık ve Film Kulübü', 'Görsel Sanatlar Kulübü', 'Dijital Sanatlar Kulübü', 'Müzik Kulübü', 'Sahne Sanatları Kulübü', 'Geleneksel Sanatlar Kulübü', 'Edebiyat ve Yazarlık Kulübü'],
    'Spor': ['Spor Kulübü', 'Ata Sporları Kulübü', 'Halk Oyunları Kulübü', 'İzcilik ve Doğa Sporları Kulübü', 'Akıl ve Zekâ Oyunları Kulübü', 'Çocuk Oyunları Kulübü'],
    'Bilim': ['Medya ve Bilgi Okuryazarlığı Kulübü', 'Dijital Vatandaşlık, Güvenli İnternet ve Sosyal Medya Kulübü', 'Bilim ve Teknoloji Kulübü', 'Analoji ve Tasarım Kulübü', 'Sosyal Bilimler Kulübü', 'Matematik Kulübü', 'Proje ve Araştırma Kulübü', 'Yapay Zekâ Kulübü', 'Dijital Beceriler Kulübü', 'Girişimcilik ve Üretkenlik Kulübü', 'Havacılık ve Uzay Kulübü', 'Tasarım ve Mühendislik Kulübü'],
    'Doğa-Çevre-Toplum': ['Meslekî Tanıtım ve Meslekî Gelişim Kulübü', 'Nezaket Hareketi Kulübü', 'Demokrasi ve İnsan Hakları Kulübü', 'Trafik ve İlk Yardım Kulübü', 'Afet Farkındalığı Kulübü', 'Diplomasi ve Müzakere Kulübü', 'Sıfır Atık Kulübü', 'İyilik Hareketi Kulübü']
  };
  var TEMA_KULUP = { 'Kızılay Kulübü': 'Doğa-Çevre-Toplum', 'Yeşilay Kulübü': 'Doğa-Çevre-Toplum', 'Beyazay Kulübü': 'Doğa-Çevre-Toplum', 'Siberay Kulübü': 'Bilim', 'Tarih ve Kültürel Miras Kulübü': 'Kültür Dünyamız', 'Tabiat ve Yaşanabilir Çevre Kulübü': 'Doğa-Çevre-Toplum', 'Sağlıklı Yaşam ve Fiziksel Aktivite Kulübü': 'Spor', 'Yaşam Becerileri Kulübü': 'Doğa-Çevre-Toplum' };
  function kulupTema(k) { if (TEMA_KULUP[k]) return TEMA_KULUP[k]; for (var t in KULUP) if (t !== 'Zorunlu kulüpler' && KULUP[t].indexOf(k) >= 0) return t; return ''; }
  var DERSLER = ['Türkçe', 'Matematik', 'Fen Bilimleri', 'Sosyal Bilgiler', 'İngilizce', 'Din Kültürü ve Ahlak Bilgisi', 'T.C. İnkılap Tarihi ve Atatürkçülük', 'Türk Dili ve Edebiyatı', 'Fizik', 'Kimya', 'Biyoloji', 'Tarih', 'Coğrafya', 'Felsefe', 'Beden Eğitimi ve Spor', 'Görsel Sanatlar', 'Müzik', 'Teknoloji ve Tasarım', 'Bilişim Teknolojileri ve Yazılım', 'Hayat Bilgisi', 'Sınıf Öğretmenliği', 'Rehberlik', 'Özel Eğitim', 'Okul Öncesi', 'Meslek Dersleri'];
  var AYLAR = ['Eylül', 'Ekim', 'Kasım', 'Aralık', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran'];

  /* ---------------- evrak türleri ve form alanları ---------------- */
  var ORTAK = [{ k: 'okul', ad: 'Okul adı', ph: 'Atatürk Ortaokulu' }, { k: 'il', ad: 'İl / ilçe', ph: 'Ankara / Çankaya' }, { k: 'yil', ad: 'Eğitim-öğretim yılı', v: yil }];
  var TOPLANTI = { donem_basi: 'Ders yılı başı', ikinci_donem: 'İkinci dönem başı', yil_sonu: 'Ders yılı sonu', donem_ara: 'Gerektiğinde (ara değerlendirme)' };
  var KADEME = { k: 'kademe', ad: 'Okul kademesi', tip: 'secim', l: ['İlkokul', 'Ortaokul', 'Lise'], v: 'Ortaokul' };
  function dayanak(id, v) {
    var yon = v.kademe === 'Lise' ? 'Millî Eğitim Bakanlığı Ortaöğretim Kurumları Yönetmeliği' : 'Millî Eğitim Bakanlığı Okul Öncesi Eğitim ve İlköğretim Kurumları Yönetmeliği';
    if (id === 'zumre' || id === 'sok' || id === 'ogr_kurul') return yon;
    if (id === 'bep') return 'Millî Eğitim Bakanlığı Özel Eğitim Hizmetleri Yönetmeliği';
    if (/^kulup/.test(id)) return 'Millî Eğitim Bakanlığı Sosyal Etkinlikler Politika Belgesi ve okul temelli sosyal etkinlikler eylem planı';
    return '';
  }
  /* MEB belirli gün ve haftalar listesi (bgh.js): [tarih, ad, tür, etkinlik, konuşma] */
  var BGH = (window.OSC_BGH || []).map(function (x) { return x; });
  function bghBul(ad) { return BGH.filter(function (x) { return x[1] === ad; })[0]; }
  function bghTur(ad) { var x = bghBul(ad); return x ? x[2] : (/Bayram/.test(ad || '') ? 'b' : /Anma|Şehit|Gazi/.test(ad || '') ? 'a' : 'g'); }
  function bghEtkinlik(ad) { var x = bghBul(ad); return x && x[3] ? x[3] : 'Konunun önemini anlatan sınıf içi etkinlik; afiş, pano ya da kısa sunum hazırlanması'; }
  /* bugüne en yakın (henüz geçmemiş) belirli gün */
  function bghYakin() {
    var AY = { 'Ocak': 0, 'Şubat': 1, 'Mart': 2, 'Nisan': 3, 'Mayıs': 4, 'Haziran': 5, 'Temmuz': 6, 'Ağustos': 7, 'Eylül': 8, 'Ekim': 9, 'Kasım': 10, 'Aralık': 11 }, b = new Date(), en = null, fark = 1e9;
    b.setHours(0, 0, 0, 0);
    BGH.forEach(function (x) { var m = /^(\d{1,2})(?:-\d{1,2})? (Ocak|Şubat|Mart|Nisan|Mayıs|Haziran|Temmuz|Ağustos|Eylül|Ekim|Kasım|Aralık)/.exec(x[1]); if (!m) return; var t = new Date(b.getFullYear(), AY[m[2]], +m[1]); if (t < b) t.setFullYear(t.getFullYear() + 1); var f = t - b; if (f < fark) { fark = f; en = x[1]; } });
    return en || (BGH[0] && BGH[0][1]) || '';
  }
  var TURLER = [
    { id: 'zumre', ad: 'Zümre öğretmenler kurulu tutanağı', ik: '👥', ac: 'Ders zümresi toplantısı: gündem, görüşmeler, kararlar ve imzalar', alan: ORTAK.concat([
      KADEME, { k: 'ders', ad: 'Ders / zümre', tip: 'secim', l: DERSLER }, { k: 'siniflar', ad: 'Sınıf seviyeleri', ph: '5, 6, 7 ve 8. sınıflar' },
      { k: 'toplanti', ad: 'Toplantı', tip: 'secim', l: TOPLANTI, v: 'donem_basi' }, { k: 'tarih', ad: 'Tarih', v: bugun }, { k: 'saat', ad: 'Saat', v: '15:00' }, { k: 'yer', ad: 'Yer', v: 'Öğretmenler odası' },
      { k: 'baskan', ad: 'Zümre başkanı', ph: 'Ad Soyad' }, { k: 'uyeler', ad: 'Zümre öğretmenleri (her satıra bir kişi)', tip: 'uzun', ph: 'Ayşe Yılmaz\nMehmet Kaya' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }, { k: 'not', ad: 'Eklemek istedikleriniz (isteğe bağlı)', tip: 'uzun', ph: 'Ör. Okulumuzda laboratuvar yenilendi; 8. sınıflar için ek kaynak kullanılacak…' }]) },
    { id: 'sok', ad: 'Şube öğretmenler kurulu (ŞÖK) tutanağı', ik: '🏫', ac: 'Bir şubenin öğretmenlerinin dönem toplantısı', alan: ORTAK.concat([
      KADEME, { k: 'sube', ad: 'Sınıf / şube', ph: '7/A' }, { k: 'rehber', ad: 'Sınıf rehber öğretmeni', ph: 'Ad Soyad' }, { k: 'toplanti', ad: 'Toplantı', tip: 'secim', l: TOPLANTI, v: 'donem_basi' },
      { k: 'tarih', ad: 'Tarih', v: bugun }, { k: 'saat', ad: 'Saat', v: '15:00' }, { k: 'yer', ad: 'Yer', v: 'Öğretmenler odası' }, { k: 'mevcut', ad: 'Öğrenci sayısı', ph: '28' },
      { k: 'ogretmenler', ad: 'Şube öğretmenleri (her satıra “Ders - Ad Soyad”)', tip: 'uzun', ph: 'Türkçe - Ayşe Yılmaz\nMatematik - Mehmet Kaya' }, { k: 'mudur', ad: 'Okul müdürü / müdür yardımcısı', ph: 'Ad Soyad' }, { k: 'not', ad: 'Şubeye özel durumlar (isteğe bağlı)', tip: 'uzun', ph: 'Ör. Devamsızlığı artan 2 öğrenci; okuma hızı düşük öğrenciler…' }]) },
    { id: 'veli', ad: 'Veli toplantısı tutanağı', ik: '👨‍👩‍👧', ac: 'Sınıf veli toplantısı: gündem, görüşmeler, kararlar, katılım listesi', alan: ORTAK.concat([
      { k: 'sube', ad: 'Sınıf / şube', ph: '7/A' }, { k: 'ogretmen', ad: 'Sınıf öğretmeni / rehber öğretmen', ph: 'Ad Soyad' }, { k: 'toplanti', ad: 'Toplantı', tip: 'secim', l: TOPLANTI, v: 'donem_basi' },
      { k: 'tarih', ad: 'Tarih', v: bugun }, { k: 'saat', ad: 'Saat', v: '16:00' }, { k: 'yer', ad: 'Yer', ph: '7/A sınıfı' }, { k: 'katilim', ad: 'Katılan veli sayısı', ph: '21' },
      { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }, { k: 'not', ad: 'Konuşulacak özel konular (isteğe bağlı)', tip: 'uzun', ph: 'Ör. Okul gezisi, kitap okuma kampanyası…' }]) },
    { id: 'bep', ad: 'BEP planı (Bireyselleştirilmiş Eğitim Programı)', ik: '🧩', ac: 'Kaynaştırma / özel eğitim öğrencisi için ders bazlı uzun ve kısa dönemli amaçlar', alan: ORTAK.concat([
      { k: 'ogrenci', ad: 'Öğrencinin adı (baş harfleri yeterli)', ph: 'A. Y.' }, { k: 'sinif', ad: 'Sınıf / şube', ph: '6/B' }, { k: 'tani', ad: 'Eğitsel tanı / ihtiyaç', tip: 'secim', l: ['Öğrenme güçlüğü', 'Hafif düzeyde zihinsel yetersizlik', 'Dikkat eksikliği ve hiperaktivite bozukluğu', 'Otizm spektrum bozukluğu', 'İşitme yetersizliği', 'Görme yetersizliği', 'Dil ve konuşma güçlüğü', 'Bedensel yetersizlik', 'Özel yetenek', 'Diğer'] },
      { k: 'ders', ad: 'Ders', tip: 'secim', l: DERSLER }, { k: 'performans', ad: 'Öğrencinin şu anki performansı (bildikleri ve güçlük çektikleri)', tip: 'uzun', ph: 'Ör. 100’e kadar sayıları okuyup yazabiliyor; toplama işlemini yapabiliyor, eldeli çıkarmada güçlük çekiyor…' },
      { k: 'baslama', ad: 'BEP başlama tarihi', v: bugun }, { k: 'bitis', ad: 'BEP bitiş tarihi', ph: '…/06/20…' }, { k: 'birim', ad: 'BEP geliştirme birimi üyeleri (her satıra “Görev - Ad Soyad”)', tip: 'uzun', ph: 'Müdür yardımcısı - …\nRehber öğretmen - …\nSınıf öğretmeni - …\nDers öğretmeni - …\nVeli - …' }]) },
    { id: 'kulup_plan', grup: 'kulup', ad: 'Kulüp yıllık çalışma planı', ik: '📅', yatay: true, ac: 'Yeni kulüp yapısına göre (48 kulüp, 5 tema) aylara göre etkinlik planı', alan: ORTAK.concat([
      { k: 'kulup', ad: 'Kulüp', tip: 'kulup' }, { k: 'kademe', ad: 'Okul kademesi', tip: 'secim', l: ['İlkokul', 'Ortaokul', 'Lise'], v: 'Ortaokul' }, { k: 'danisman', ad: 'Danışman öğretmen(ler) (her satıra bir kişi)', tip: 'uzun' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }, { k: 'not', ad: 'Planlanan özel etkinlikler (isteğe bağlı)', tip: 'uzun', ph: 'Ör. Mart’ta il genelinde proje yarışmasına katılım…' }]) },
    { id: 'kulup_tutanak', grup: 'kulup', ad: 'Kulüp ilk toplantı ve seçim tutanağı', ik: '🗳️', ac: 'Kulübün kuruluş toplantısı, görev dağılımı ve üye listesi', alan: ORTAK.concat([
      { k: 'kulup', ad: 'Kulüp', tip: 'kulup' }, { k: 'tarih', ad: 'Tarih', v: bugun }, { k: 'yer', ad: 'Yer', ph: 'Konferans salonu' }, { k: 'danisman', ad: 'Danışman öğretmen(ler) (her satıra bir kişi)', tip: 'uzun' },
      { k: 'baskan', ad: 'Kulüp başkanı (öğrenci)', ph: 'Ad Soyad - Sınıf' }, { k: 'baskanyrd', ad: 'Başkan yardımcısı', ph: 'Ad Soyad - Sınıf' }, { k: 'sekreter', ad: 'Sekreter', ph: 'Ad Soyad - Sınıf' }, { k: 'uyeler', ad: 'Üye öğrenciler (her satıra “Ad Soyad - Sınıf - No”)', tip: 'uzun' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'kulup_rapor', grup: 'kulup', ad: 'Kulüp dönem sonu çalışma raporu', ik: '📊', ac: 'Dönem boyunca yapılan kulüp çalışmalarının raporu', alan: ORTAK.concat([
      { k: 'kulup', ad: 'Kulüp', tip: 'kulup' }, { k: 'donem', ad: 'Dönem', tip: 'secim', l: ['1. dönem', '2. dönem'], v: '1. dönem' }, { k: 'danisman', ad: 'Danışman öğretmen(ler)', tip: 'uzun' }, { k: 'uye', ad: 'Üye sayısı', ph: '24' },
      { k: 'yapilanlar', ad: 'Yapılan çalışmalar (her satıra bir etkinlik; boş bırakırsanız örnek içerik oluşturulur)', tip: 'uzun' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'kulup_aylik', grup: 'kulup', ad: 'Kulüp aylık çalışma raporu', ik: '🗓️', ac: 'Kulübün o ay yaptığı etkinliklerin raporu', alan: ORTAK.concat([
      { k: 'kulup', ad: 'Kulüp', tip: 'kulup' }, { k: 'ay', ad: 'Ay', tip: 'secim', l: AYLAR, v: function () { return AYLAR[(new Date().getMonth() + 4) % 12] || 'Ekim'; } }, { k: 'danisman', ad: 'Danışman öğretmen(ler)', tip: 'uzun' }, { k: 'uye', ad: 'Katılan öğrenci sayısı', ph: '20' },
      { k: 'yapilanlar', ad: 'Bu ay yapılanlar (her satıra “Tarih - Etkinlik”; boş bırakırsanız örnek içerik oluşturulur)', tip: 'uzun', ph: '07.10.2026 - Kulüp panosu hazırlandı' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'ogr_kurul', ad: 'Öğretmenler kurulu toplantı tutanağı', ik: '🏛️', ac: 'Okul müdürü başkanlığında öğretmenler kurulu: gündem, görüşmeler, kararlar', alan: ORTAK.concat([KADEME,
      { k: 'toplanti', ad: 'Toplantı', tip: 'secim', l: { donem_basi: 'Ders yılı başı', ikinci_donem: 'İkinci dönem başı', yil_sonu: 'Ders yılı sonu', donem_ara: 'Gerektiğinde (olağanüstü)' }, v: 'donem_basi' }, { k: 'tarih', ad: 'Tarih', v: bugun }, { k: 'saat', ad: 'Saat', v: '14:00' }, { k: 'yer', ad: 'Yer', v: 'Öğretmenler odası' },
      { k: 'mudur', ad: 'Okul müdürü (kurul başkanı)', ph: 'Ad Soyad' }, { k: 'yazman', ad: 'Yazman', ph: 'Ad Soyad' }, { k: 'uyeler', ad: 'Kurul üyeleri (her satıra “Görev/Branş - Ad Soyad”)', tip: 'uzun', ph: 'Müdür Yardımcısı - …\nTürkçe Öğretmeni - …' }, { k: 'not', ad: 'Ek gündem / notlar (isteğe bağlı)', tip: 'uzun' }]) },
    { id: 'egzersiz', ad: 'Ders dışı egzersiz planı', ik: '🏐', yatay: true, ac: 'Spor, sanat ve bilim egzersiz çalışmaları için aylara göre yıllık plan', alan: ORTAK.concat([
      { k: 'egz', ad: 'Egzersizin adı / branşı', ph: 'Voleybol' }, { k: 'alan', ad: 'Alanı', tip: 'secim', l: ['Spor', 'Sanat', 'Bilim', 'Kültür'], v: 'Spor' }, { k: 'ogretmen', ad: 'Egzersiz öğretmeni', ph: 'Ad Soyad' }, { k: 'grup', ad: 'Öğrenci grubu (sınıflar)', ph: '6 ve 7. sınıflar' },
      { k: 'gunsaat', ad: 'Gün ve saat', ph: 'Salı-Perşembe 15:30-16:50' }, { k: 'yer', ad: 'Çalışma yeri', ph: 'Spor salonu' }, { k: 'sure', ad: 'Haftalık süre (ders saati)', ph: '4' }, { k: 'ogrenciler', ad: 'Katılan öğrenciler (isteğe bağlı, her satıra “Ad Soyad - Sınıf - No”)', tip: 'uzun' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'belirli_gun', ad: 'Belirli gün ve haftalar programı', ik: '🎗️', ac: 'Seçilen gün için kutlama/anma programı (akış, konuşma metni, görevliler) ya da yıllık plan', yatayFn: function (v) { return v.program === 'yillik'; }, alan: ORTAK.concat([
      { k: 'program', ad: 'Hazırlanacak program', tip: 'secim', l: { kutlama: 'Kutlama / anma programı (tek gün)', yillik: 'Yıllık belirli gün ve haftalar planı' }, v: 'kutlama', yenile: true },
      { k: 'gun', ad: 'Belirli gün / hafta', tip: 'secim', l: BGH.map(function (x) { return x[1]; }), v: bghYakin, kosul: function (v) { return v.program !== 'yillik'; } },
      { k: 'tarih', ad: 'Program tarihi', v: bugun, kosul: function (v) { return v.program !== 'yillik'; } }, { k: 'saat', ad: 'Saat', ph: '09:00', kosul: function (v) { return v.program !== 'yillik'; } }, { k: 'yer', ad: 'Yer', ph: 'Okul bahçesi / konferans salonu', kosul: function (v) { return v.program !== 'yillik'; } },
      { k: 'sunucu', ad: 'Sunucu(lar)', ph: 'Ece Kaya (7/A)', kosul: function (v) { return v.program !== 'yillik'; } }, { k: 'konusmaci', ad: 'Günün anlam ve önemini anlatacak kişi', ph: 'Ad Soyad - Türkçe Öğretmeni', kosul: function (v) { return v.program !== 'yillik'; } },
      { k: 'siirler', ad: 'Şiirler (her satıra “Şiirin adı - Okuyan öğrenci”)', tip: 'uzun', ph: 'Atatürk’e Mektup - Ali Yılmaz (6/B)', kosul: function (v) { return v.program !== 'yillik'; } },
      { k: 'gosteriler', ad: 'Diğer gösteriler (koro, skeç, halk oyunu, sunum… her satıra bir tane)', tip: 'uzun', kosul: function (v) { return v.program !== 'yillik'; } },
      { k: 'ogretmenler', ad: 'Programdan sorumlu öğretmenler (her satıra bir kişi)', tip: 'uzun', kosul: function (v) { return v.program !== 'yillik'; } },
      { k: 'kapsam', ad: 'Plan', tip: 'secim', l: ['Okul geneli', 'Sınıf / şube', 'Ders'], v: 'Sınıf / şube', kosul: function (v) { return v.program === 'yillik'; } }, { k: 'sube', ad: 'Sınıf / şube ya da ders', ph: '7/A', kosul: function (v) { return v.program === 'yillik'; } }, { k: 'sorumlu', ad: 'Sorumlu öğretmen', ph: 'Ad Soyad', kosul: function (v) { return v.program === 'yillik'; } },
      { k: 'secim', ad: 'Plana alınacak gün ve haftalar', tip: 'secimler', l: BGH.map(function (x) { return x[1]; }), kosul: function (v) { return v.program === 'yillik'; } }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'gezi', ad: 'Gezi planı (Ek-9)', ik: '🚌', ac: 'Okul dışı gezi planı, veli izin belgesi ve katılan öğrenci listesi', alan: ORTAK.concat([
      { k: 'gezi', ad: 'Gezinin adı / gezilecek yer', ph: 'Anıtkabir ve Etnografya Müzesi' }, { k: 'tur', ad: 'Gezinin türü', tip: 'secim', l: ['Kültürel gezi', 'Bilimsel / teknik gezi', 'Doğa gezisi', 'Meslekî gezi', 'Sosyal sorumluluk gezisi'], v: 'Kültürel gezi' }, { k: 'tarih', ad: 'Gezi tarihi', ph: '…/…/20…' },
      { k: 'hareket', ad: 'Hareket yeri ve saati', ph: 'Okul bahçesi, 08:30' }, { k: 'donus', ad: 'Dönüş saati', ph: '16:30' }, { k: 'ulasim', ad: 'Ulaşım aracı / plaka', ph: 'Otobüs, 06 ABC 123' }, { k: 'siniflar', ad: 'Katılan sınıflar', ph: '7/A, 7/B' }, { k: 'sayi', ad: 'Öğrenci sayısı', ph: '40' },
      { k: 'ogretmenler', ad: 'Sorumlu öğretmenler (her satıra bir kişi)', tip: 'uzun' }, { k: 'program', ad: 'Gezi programı (her satıra “Saat - Etkinlik”, isteğe bağlı)', tip: 'uzun', ph: '09:30 - Anıtkabir ziyareti\n12:00 - Öğle yemeği' }, { k: 'ogrenciler', ad: 'Katılan öğrenciler (isteğe bağlı, her satıra “Ad Soyad - Sınıf - No”)', tip: 'uzun' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'dilekce', ad: 'Dilekçe', ik: '✉️', ac: 'İzin, rapor, nöbet değişikliği, belge talebi vb. resmî dilekçeler', alan: [
      { k: 'dtur', ad: 'Dilekçe türü', tip: 'secim', l: { izin: 'İzin talebi', rapor: 'Sağlık raporu teslimi', nobet: 'Nöbet değişikliği', belge: 'Hizmet belgesi / belge talebi', ders: 'Ders/sınav görevlendirme talebi', genel: 'Diğer (serbest)' }, v: 'izin' },
      { k: 'makam', ad: 'Hitap edilecek makam', ph: 'Atatürk Ortaokulu Müdürlüğüne' }, { k: 'il', ad: 'İl / ilçe', ph: 'ÇANKAYA' }, { k: 'adsoyad', ad: 'Adınız soyadınız', ph: 'Ad Soyad' }, { k: 'gorev', ad: 'Unvan / branş', ph: 'Matematik Öğretmeni' },
      { k: 'tarih', ad: 'Dilekçe tarihi', v: bugun }, { k: 'aciklama', ad: 'Açıklama (tarihler, gerekçe vb.)', tip: 'uzun', ph: 'Ör. 14.11.2026 Cuma günü ailevi bir mazeretim nedeniyle…' }, { k: 'adres', ad: 'Adres', ph: '…' }, { k: 'tel', ad: 'Telefon', ph: '05…' }, { k: 'ekler', ad: 'Ekler (her satıra bir ek, isteğe bağlı)', tip: 'uzun', ph: 'Sağlık raporu (1 sayfa)' }] },
    { id: 'yillik', ad: 'Ünitelendirilmiş yıllık plan', ik: '🗓️', yzYok: true, yatay: true, ozel: 'yp', ac: '1–12. sınıf tüm dersler; çalışma takvimine göre hafta hafta, öğrenme çıktılarıyla', alan: ORTAK.concat([
      { k: 'ogretmen', ad: 'Ders öğretmeni', ph: 'Ad Soyad' }, { k: 'zumre', ad: 'Diğer zümre öğretmenleri (her satıra bir kişi, isteğe bağlı)', tip: 'uzun' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'performans', ad: 'Performans görevi ve dereceli puanlama anahtarı', ik: '🏅', ac: 'Öğrenme çıktısına göre görev yönergesi ve 4 düzeyli rubrik', yatayFn: function () { return false; }, alan: ORTAK.concat([
      { k: 'ders', ad: 'Ders', tip: 'secim', l: DERSLER }, { k: 'sinif', ad: 'Sınıf / şube', ph: '7/A' }, { k: 'cikti', ad: 'Öğrenme çıktısı', tip: 'uzun', ph: 'MAT.7.1.3 Rasyonel sayıların karşılaştırma ve sıralama ilişkilerini yorumlayabilme' },
      { k: 'konu', ad: 'Görevin konusu', ph: 'Market fişlerindeki fiyatları karşılaştırma' }, { k: 'urun', ad: 'Ürün türü', tip: 'secim', l: ['Poster', 'Sunum', 'Rapor', 'Model / maket', 'Deney raporu', 'Video', 'Proje dosyası', 'Drama / canlandırma'], v: 'Poster' },
      { k: 'verilis', ad: 'Veriliş tarihi', v: bugun }, { k: 'teslim', ad: 'Teslim tarihi', ph: '…/…/20…' }, { k: 'ogretmen', ad: 'Ders öğretmeni', ph: 'Ad Soyad' }]) },
    { id: 'dyk', ad: 'DYK kurs planı ve devam çizelgesi', ik: '📗', yatay: true, ac: 'Destekleme ve yetiştirme kursu haftalık planı ve öğrenci devam çizelgesi', alan: ORTAK.concat([
      { k: 'ders', ad: 'Kurs (ders)', tip: 'secim', l: DERSLER }, { k: 'sinif', ad: 'Sınıf seviyesi / grup', ph: '8. sınıflar' }, { k: 'gunsaat', ad: 'Gün ve saat', ph: 'Cumartesi 09:00-11:40' }, { k: 'haftalik', ad: 'Haftalık ders saati', v: '3' },
      { k: 'baslama', ad: 'Başlama tarihi', ph: '…/…/20…' }, { k: 'bitis', ad: 'Bitiş tarihi', ph: '…/…/20…' }, { k: 'konular', ad: 'Haftalık konular (her satıra bir hafta)', tip: 'uzun', ph: 'Çarpanlar ve katlar\nÜslü ifadeler\nKareköklü ifadeler' },
      { k: 'ogrenciler', ad: 'Öğrenciler (her satıra “Ad Soyad - No”)', tip: 'uzun' }, { k: 'ogretmen', ad: 'Kurs öğretmeni', ph: 'Ad Soyad' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'rehberlik', ad: 'Sınıf rehberlik planı / yıl sonu raporu', ik: '🧭', ac: 'Sınıf rehber öğretmeninin yıllık rehberlik planı ya da yıl sonu çalışma raporu', alan: ORTAK.concat([
      { k: 'program', ad: 'Evrak', tip: 'secim', l: { plan: 'Sınıf rehberlik yıllık planı', rapor: 'Sınıf rehberlik yıl sonu raporu' }, v: 'plan', yenile: true }, KADEME,
      { k: 'sube', ad: 'Sınıf / şube', ph: '7/A' }, { k: 'mevcut', ad: 'Öğrenci sayısı', ph: '28' }, { k: 'ogretmen', ad: 'Sınıf rehber öğretmeni', ph: 'Ad Soyad' }, { k: 'rehber', ad: 'Okul rehber öğretmeni', ph: 'Ad Soyad' },
      { k: 'yapilanlar', ad: 'Yapılan çalışmalar (isteğe bağlı, her satıra bir tane)', tip: 'uzun', kosul: function (v) { return v.program === 'rapor'; } }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'veli_gorusme', ad: 'Veli görüşme formu', ik: '🤝', ac: 'Öğretmen-veli bireysel görüşme kaydı', alan: ORTAK.concat([
      { k: 'ogrenci', ad: 'Öğrencinin adı soyadı', ph: 'Ad Soyad' }, { k: 'sube', ad: 'Sınıf / şube - no', ph: '7/A - 125' }, { k: 'veli', ad: 'Velinin adı soyadı / yakınlığı', ph: 'Ad Soyad - Annesi' }, { k: 'tarih', ad: 'Görüşme tarihi', v: bugun },
      { k: 'sekil', ad: 'Görüşme şekli', tip: 'secim', l: ['Yüz yüze', 'Telefon', 'Çevrim içi'], v: 'Yüz yüze' }, { k: 'neden', ad: 'Görüşme nedeni', tip: 'secim', l: ['Akademik başarı', 'Devam-devamsızlık', 'Davranış', 'Sosyal-duygusal durum', 'Sağlık', 'Bilgilendirme', 'Diğer'], v: 'Akademik başarı' },
      { k: 'konusulan', ad: 'Görüşülen konular', tip: 'uzun' }, { k: 'kararlar', ad: 'Alınan kararlar / yapılacaklar', tip: 'uzun' }, { k: 'ogretmen', ad: 'Görüşmeyi yapan öğretmen', ph: 'Ad Soyad' }]) },
    { id: 'nobet', ad: 'Nöbet çizelgesi', ik: '🛡️', yatay: true, ac: 'Nöbet yerleri ve öğretmenlere göre haftalık nöbet çizelgesi', ozel: 'nb', alan: ORTAK.concat([
      { k: 'baslama', ad: 'Geçerlilik başlangıcı', v: bugun }, { k: 'yerler', ad: 'Nöbet yerleri (her satıra bir yer)', tip: 'uzun', ph: 'Bahçe\nZemin kat koridor\n1. kat koridor\nKantin önü', yenile: true },
      { k: 'ogretmenler', ad: 'Nöbetçi öğretmenler (her satıra bir kişi)', tip: 'uzun', yenile: true }, { k: 'mudurYrd', ad: 'Nöbetçi müdür yardımcısı (isteğe bağlı)', ph: 'Ad Soyad' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'karne', ad: 'Karne görüşleri', ik: '📝', ac: 'Öğrenci listesine göre kişiye özel, olumlu dille karne görüş cümleleri', alan: ORTAK.concat([
      { k: 'sube', ad: 'Sınıf / şube', ph: '7/A' }, { k: 'donem', ad: 'Dönem', tip: 'secim', l: ['1. dönem', '2. dönem (yıl sonu)'], v: '1. dönem' },
      { k: 'ogrenciler', ad: 'Öğrenciler ve düzeyleri (her satıra “Ad Soyad - düzey”; düzey: çok iyi / iyi / orta / gelişmeli)', tip: 'uzun', ph: 'Ali Yılmaz - çok iyi\nEce Kaya - orta' }, { k: 'ogretmen', ad: 'Sınıf öğretmeni', ph: 'Ad Soyad' }]) },
    { id: 'gunluk_plan', ad: 'Günlük ders planı', ik: '📘', ozel: 'gp', ac: 'Maarif modeline uygun ders planı: öğrenme çıktıları, öğrenme-öğretme uygulamaları, farklılaştırma, ölçme', alan: ORTAK.concat([
      { k: 'sube', ad: 'Sınıf / şube', ph: '7/A' }, { k: 'tarih', ad: 'Tarih', v: bugun }, { k: 'sure', ad: 'Süre (ders saati)', v: '2' }, { k: 'ogretmen', ad: 'Ders öğretmeni', ph: 'Ad Soyad' },
      { k: 'konu', ad: 'Konu / ders başlığı (isteğe bağlı)', ph: 'Ör. Rasyonel sayılarla toplama' }, { k: 'kavram', ad: 'Anahtar kavramlar (isteğe bağlı)', ph: 'rasyonel sayı, payda, sayı doğrusu' },
      { k: 'arac', ad: 'Araç-gereç ve kaynaklar (isteğe bağlı)', tip: 'uzun', ph: 'Ders kitabı, akıllı tahta, çalışma kâğıdı…' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'iyep', ad: 'İYEP planı (İlkokullarda Yetiştirme Programı)', ik: '🌱', ac: '3 ve 4. sınıf Türkçe / Matematik İYEP modül planı, öğrenci izleme ve devam çizelgesi', yzYok: true, alan: ORTAK.concat([
      { k: 'ders', ad: 'Ders', tip: 'secim', l: ['Türkçe', 'Matematik'], v: 'Türkçe' }, { k: 'sinif', ad: 'Sınıf seviyesi / şube', ph: '3/A' }, { k: 'uygulama', ad: 'Uygulama şekli', tip: 'secim', l: ['Sınıf içinde, ders saatlerinde', 'Ders saatleri dışında / hafta sonu'], v: 'Ders saatleri dışında / hafta sonu' },
      { k: 'gunsaat', ad: 'Gün ve saat', ph: 'Cumartesi 09:00-11:40' }, { k: 'haftalik', ad: 'Haftalık ders saati', v: '4' }, { k: 'baslama', ad: 'Başlama tarihi', ph: '…/…/20…' }, { k: 'bitis', ad: 'Bitiş tarihi', ph: '…/…/20…' },
      { k: 'moduller', ad: 'Modüller / kazanımlar (her satıra bir tane; boş bırakırsanız örnek liste gelir)', tip: 'uzun', ph: 'Sesleri tanıma ve harf-ses ilişkisi\nHece ve kelime okuma' },
      { k: 'ogrenciler', ad: 'Öğrenciler (her satıra “Ad Soyad - Okul no”)', tip: 'uzun' }, { k: 'ogretmen', ad: 'Sınıf öğretmeni / İYEP öğretmeni', ph: 'Ad Soyad' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'yazili_teslim', ad: 'Yazılı sınav kâğıdı teslim tutanağı', ik: '📥', ac: 'Okuttuğunuz şubelerin dönem sınav kâğıdı sayılarıyla okul müdürlüğüne teslim dilekçesi', yzYok: true, ozel: 'yt', alan: ORTAK.concat([
      { k: 'kapsam', ad: 'Dönem', tip: 'secim', l: ['I. ve II. dönem', 'I. dönem', 'II. dönem'], v: 'I. ve II. dönem', yenile: true },
      { k: 'tarih', ad: 'Tarih', v: bugun }, { k: 'ogretmen', ad: 'Ders öğretmeni', ph: 'Ad Soyad' }]) },
    { id: 'sinav_tutanak', ad: 'Yazılı sınav uygulama tutanağı', ik: '📋', ac: 'Sınavın yapıldığı şube, saat, gözetmen, sınava girmeyen öğrenciler ve olağan dışı durumlar', yzYok: true, alan: ORTAK.concat([
      { k: 'ders', ad: 'Ders', tip: 'secim', l: DERSLER }, { k: 'sube', ad: 'Sınıf / şube', ph: '7/A' }, { k: 'sinav', ad: 'Sınav', tip: 'secim', l: ['1. yazılı', '2. yazılı', '3. yazılı', 'Ortak yazılı', 'Mazeret sınavı', 'Uygulama sınavı'], v: '1. yazılı' },
      { k: 'tarih', ad: 'Sınav tarihi', v: bugun }, { k: 'saat', ad: 'Ders saati / saat', ph: '3. ders (10:20-11:00)' }, { k: 'mevcut', ad: 'Sınıf mevcudu', ph: '28' }, { k: 'giren', ad: 'Sınava giren öğrenci sayısı', ph: '27' },
      { k: 'girmeyenler', ad: 'Sınava girmeyen öğrenciler (her satıra “Okul no - Ad Soyad - neden”)', tip: 'uzun', ph: '125 - Ali Yılmaz - Raporlu' }, { k: 'durum', ad: 'Sınav sırasında yaşanan durumlar (isteğe bağlı)', tip: 'uzun' },
      { k: 'gozetmen', ad: 'Sınavı uygulayan / gözetmen öğretmen', ph: 'Ad Soyad' }, { k: 'ogretmen', ad: 'Ders öğretmeni', ph: 'Ad Soyad' }, { k: 'mudur', ad: 'Okul müdürü / müdür yardımcısı', ph: 'Ad Soyad' }]) },
    { id: 'sinav_takvim', ad: 'Yazılı sınav takvimi', ik: '🗒️', ac: 'Derslere göre dönem yazılı sınav tarihleri, günleri ve ders saatleri çizelgesi', yatay: true, yzYok: true, alan: ORTAK.concat([
      { k: 'donem', ad: 'Dönem', tip: 'secim', l: ['1. dönem', '2. dönem'], v: '1. dönem' }, { k: 'sinav', ad: 'Sınav', tip: 'secim', l: ['1. yazılı', '2. yazılı', '3. yazılı', 'Ortak yazılılar'], v: '1. yazılı' }, { k: 'siniflar', ad: 'Sınıf / şubeler', ph: '5, 6, 7 ve 8. sınıflar' },
      { k: 'sinavlar', ad: 'Sınavlar (her satıra “Ders - Tarih - Ders saati”)', tip: 'uzun', ph: 'Türkçe - 04.11.2026 - 3. ders\nMatematik - 05.11.2026 - 2. ders' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'ders_kesim', ad: 'Ders kesim raporu', ik: '📉', ac: 'Dönem / yıl sonu: okuttuğunuz sınıf ve dersler, varsa yetiştirilemeyen konu ve sebebi', yzYok: true, alan: ORTAK.concat([
      { k: 'donem', ad: 'Rapor', tip: 'secim', l: ['Yıl sonu', '1. dönem sonu'], v: 'Yıl sonu' },
      { k: 'satirlar', ad: 'Sınıf ve dersler (her satıra “Sınıf - Ders - varsa yetiştirilemeyen konu ve sebebi”)', tip: 'uzun', ph: '9/A - Matematik\n9/B - Matematik - Olasılık konusu, resmî tatiller nedeniyle yetiştirilemedi' },
      { k: 'tarih', ad: 'Tarih', v: bugun }, { k: 'ogretmen', ad: 'Ders öğretmeni', ph: 'Ad Soyad' }]) },
    { id: 'oturma', ad: 'Sınıf oturma planı', ik: '🪑', ac: 'Öğrenci listesinden sıra düzenine göre sınıf ya da sınav oturma planı (numaraya göre, alfabetik ya da karışık)', yatay: true, yzYok: true, alan: ORTAK.concat([
      { k: 'sube', ad: 'Sınıf / şube', ph: '7/A' }, { k: 'amac', ad: 'Plan', tip: 'secim', l: ['Sınıf oturma planı', 'Sınav oturma planı'], v: 'Sınıf oturma planı' }, { k: 'ders', ad: 'Sınav (ders ve tarih, isteğe bağlı)', ph: 'Matematik 1. yazılı – 04.11.2026', kosul: function (v) { return v.amac === 'Sınav oturma planı'; } },
      { k: 'ogrenciler', ad: 'Öğrenciler (her satıra “Okul no - Ad Soyad” ya da yalnızca ad)', tip: 'uzun', ph: '101 - Ali Yılmaz\n102 - Ece Kaya' },
      { k: 'sutun', ad: 'Sıra sütunu sayısı', tip: 'secim', l: ['2', '3', '4', '5'], v: '3' }, { k: 'sira', ad: 'Her sütundaki sıra sayısı', tip: 'secim', l: ['3', '4', '5', '6', '7', '8'], v: '5' }, { k: 'kisi', ad: 'Bir sırada oturan öğrenci', tip: 'secim', l: ['1', '2', '3'], v: '2' },
      { k: 'duzen', ad: 'Yerleştirme', tip: 'secim', l: ['Listedeki sırayla', 'Okul numarasına göre', 'Alfabetik', 'Karışık (rastgele)'], v: 'Listedeki sırayla' }, { k: 'ogretmen', ad: 'Sınıf öğretmeni / gözetmen', ph: 'Ad Soyad' }]) },
    { id: 'yoklama', ad: 'Sınıf listesi ve yoklama çizelgesi', ik: '✅', ac: 'Ders, kurs, kulüp veya etkinlik için tarih sütunlu yoklama ve takip çizelgesi', yatay: true, yzYok: true, alan: ORTAK.concat([
      { k: 'sube', ad: 'Sınıf / şube ya da grup', ph: '7/A' }, { k: 'baslik', ad: 'Çizelge başlığı', ph: 'Kasım ayı yoklama çizelgesi' }, { k: 'ogrenciler', ad: 'Öğrenciler (her satıra “Okul no - Ad Soyad”; boş bırakırsanız boş liste)', tip: 'uzun' },
      { k: 'sutun', ad: 'Tarih / işaret sütunu sayısı', tip: 'secim', l: ['5', '8', '10', '12', '15'], v: '10' }, { k: 'sutunad', ad: 'Sütun başlıkları (isteğe bağlı, her satıra bir tarih veya etkinlik)', tip: 'uzun', ph: '03.11\n04.11\n05.11' }, { k: 'ogretmen', ad: 'Öğretmen', ph: 'Ad Soyad' }]) },
    { id: 'tutanak', ad: 'Tutanak (olay, kaza, teslim, genel)', ik: '🖋️', ac: 'Olay, kaza, kayıp-hasar, teslim-tesellüm, tebliğden imtina ya da genel amaçlı resmî tutanak', yzYok: true, alan: ORTAK.concat([
      { k: 'ttur', ad: 'Tutanak türü', tip: 'secim', l: { genel: 'Genel tutanak', olay: 'Olay tutanağı (öğrenci olayı)', kaza: 'Kaza tutanağı', kayip: 'Kayıp / hasar tutanağı', teslim: 'Teslim-tesellüm tutanağı', imtina: 'Tebliğden imtina tutanağı' }, v: 'genel', yenile: true },
      { k: 'konu', ad: 'Tutanağın konusu', ph: 'Ör. 7/A sınıfında teneffüste yaşanan olay' }, { k: 'tarih', ad: 'Tarih', v: bugun }, { k: 'saat', ad: 'Saat', ph: '10:45' }, { k: 'yer', ad: 'Yer', ph: 'Okul bahçesi' },
      { k: 'olay', ad: 'Olayın / durumun açıklaması (ne oldu, kimler vardı, ne yapıldı)', tip: 'uzun', ph: 'Kısa ve nesnel cümlelerle yazın; yorum katmayın.', kosul: function (v) { return v.ttur !== 'teslim'; } },
      { k: 'esyalar', ad: 'Teslim edilen eşya / belgeler (her satıra “Ad - adet - açıklama”)', tip: 'uzun', ph: 'Laptop - 1 - Seri no: …', kosul: function (v) { return v.ttur === 'teslim'; } },
      { k: 'teslimeden', ad: 'Teslim eden (Ad Soyad - görevi)', ph: 'Ad Soyad - Öğretmen', kosul: function (v) { return v.ttur === 'teslim'; } }, { k: 'teslimalan', ad: 'Teslim alan (Ad Soyad - görevi)', ph: 'Ad Soyad - Müdür Yardımcısı', kosul: function (v) { return v.ttur === 'teslim'; } },
      { k: 'duzenleyenler', ad: 'Tutanağı düzenleyenler / tanıklar (her satıra “Ad Soyad - görevi”)', tip: 'uzun', ph: 'Ayşe Yılmaz - Nöbetçi Öğretmen\nMehmet Kaya - Müdür Yardımcısı', kosul: function (v) { return v.ttur !== 'teslim'; } }]) },
    { id: 'ogrenci_gorusme', ad: 'Öğrenci görüşme formu', ik: '💬', ac: 'Öğretmenin öğrenciyle bireysel görüşme kaydı, alınan kararlar ve yönlendirme', yzYok: true, alan: ORTAK.concat([
      { k: 'ogrenci', ad: 'Öğrencinin adı soyadı', ph: 'Ad Soyad' }, { k: 'sube', ad: 'Sınıf / şube - no', ph: '7/A - 125' }, { k: 'tarih', ad: 'Görüşme tarihi', v: bugun },
      { k: 'neden', ad: 'Görüşme nedeni', tip: 'secim', l: ['Akademik başarı', 'Devamsızlık', 'Davranış', 'Arkadaş ilişkileri', 'Duygusal durum', 'Motivasyon / ders çalışma', 'Öğrencinin kendi isteği', 'Diğer'], v: 'Akademik başarı' },
      { k: 'konusulan', ad: 'Görüşülen konular', tip: 'uzun' }, { k: 'kararlar', ad: 'Alınan kararlar / öğrenciyle yapılan anlaşma', tip: 'uzun' },
      { k: 'yonlendirme', ad: 'Yönlendirme', tip: 'secim', l: ['Gerek görülmedi', 'Okul rehberlik servisine yönlendirildi', 'Veli ile görüşülecek', 'Sınıf rehber öğretmenine bilgi verildi', 'Okul idaresine bilgi verildi'], v: 'Gerek görülmedi' }, { k: 'ogretmen', ad: 'Görüşmeyi yapan öğretmen', ph: 'Ad Soyad' }]) },
    { id: 'ogrenci_bilgi', ad: 'Öğrenci tanıma ve veli bilgi formu', ik: '🪪', ac: 'Veliye doldurtulan öğrenci tanıma, iletişim ve sağlık bilgi formu (KVKK aydınlatmalı)', yzYok: true, alan: ORTAK.concat([
      { k: 'sube', ad: 'Sınıf / şube', ph: '7/A' }, { k: 'ogretmen', ad: 'Sınıf öğretmeni / rehber öğretmen', ph: 'Ad Soyad' }, { k: 'tel', ad: 'Okul telefonu (isteğe bağlı)', ph: '0 (312) … .. ..' }]) },
    { id: 'demirbas', ad: 'Sınıf demirbaş ve zimmet listesi', ik: '📦', ac: 'Sınıftaki demirbaş eşyaların sayımı, durumu ve teslim-tesellüm imzaları', yzYok: true, alan: ORTAK.concat([
      { k: 'sube', ad: 'Sınıf / derslik', ph: '7/A' }, { k: 'tarih', ad: 'Sayım tarihi', v: bugun },
      { k: 'esyalar', ad: 'Eşyalar (her satıra “Eşya - adet - durumu”; boş bırakırsanız örnek liste gelir)', tip: 'uzun', ph: 'Öğrenci sırası - 16 - Sağlam\nEtkileşimli tahta - 1 - Sağlam' },
      { k: 'teslimeden', ad: 'Teslim eden', ph: 'Ad Soyad - Müdür Yardımcısı' }, { k: 'teslimalan', ad: 'Teslim alan (sınıf öğretmeni)', ph: 'Ad Soyad - Sınıf Öğretmeni' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'devamsizlik', ad: 'Devamsızlık veli bilgilendirme yazısı', ik: '📨', ac: 'Devamsızlığı artan öğrencinin velisine resmî bilgilendirme yazısı ve tebliğ bölümü', yzYok: true, alan: ORTAK.concat([
      { k: 'ogrenci', ad: 'Öğrencinin adı soyadı', ph: 'Ad Soyad' }, { k: 'sube', ad: 'Sınıf / şube - no', ph: '9/B - 312' }, { k: 'veli', ad: 'Velinin adı soyadı', ph: 'Ad Soyad' },
      { k: 'ozursuz', ad: 'Özürsüz devamsızlık (gün)', ph: '6' }, { k: 'ozurlu', ad: 'Özürlü devamsızlık (gün)', ph: '2' }, { k: 'tarih', ad: 'Yazı tarihi', v: bugun },
      { k: 'ogretmen', ad: 'Sınıf rehber öğretmeni', ph: 'Ad Soyad' }, { k: 'mudur', ad: 'Okul müdürü / müdür yardımcısı', ph: 'Ad Soyad' }]) },
    { id: 'kelebek', ad: 'Kelebek sınav sistemi (karma oturma planı)', ik: '🦋', ac: 'Şube listelerini Excel’den yükleyin; öğrenciler salonlara şube şube karıştırılarak yerleşsin, salon planları ve şube listeleri hazır olsun', yzYok: true, ozel: 'kb', alan: ORTAK.concat([
      { k: 'sinav', ad: 'Sınav', ph: '1. dönem 1. ortak yazılı – Matematik' }, { k: 'tarih', ad: 'Sınav tarihi', v: bugun }, { k: 'saat', ad: 'Saat / ders saati', ph: '10:20 (3. ders)' }, { k: 'mudur', ad: 'Okul müdürü / müdür yardımcısı', ph: 'Ad Soyad' }]) },
    { id: 'oys', ad: 'OYS puan çizelgesi', ik: '🧮', yzYok: true, yatay: true, ozel: 'oys', ac: 'MEB senaryosuna göre ortak yazılı soru bazlı puan çizelgesi; e-Okul’a not girişi için', alan: ORTAK.concat([
      { k: 'sube', ad: 'Sınıf / şube', ph: '7/A' }, { k: 'tarih', ad: 'Sınav tarihi', ph: '…/…/20…' }, { k: 'ogretmen', ad: 'Ders öğretmeni', ph: 'Ad Soyad' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) }
  ];
  if (Array.isArray(C.turler)) TURLER = TURLER.filter(function (t) { return C.turler.indexOf(t.id) >= 0; });
  /* seçim ekranında kategoriler */
  var EVKAT = { toplanti: ['Toplantı ve tutanak', ['zumre', 'ogr_kurul', 'sok', 'veli', 'tutanak', 'kulup_tutanak']], plan: ['Plan ve program', ['yillik', 'gunluk_plan', 'iyep', 'dyk', 'bep', 'rehberlik', 'egzersiz', 'belirli_gun', 'gezi', 'performans']],
    sinav: ['Sınav ve not', ['kelebek', 'oys', 'yazili_teslim', 'sinav_tutanak', 'sinav_takvim', 'ders_kesim', 'karne']], ogrenci: ['Öğrenci ve veli', ['oturma', 'yoklama', 'ogrenci_gorusme', 'veli_gorusme', 'ogrenci_bilgi', 'devamsizlik']], okul: ['Okul ve görev', ['nobet', 'demirbas', 'dilekce']] };
  function yatayMi(t) { return t.yatayFn ? t.yatayFn(S.veri) : !!t.yatay; }
  function tur(id) { return TURLER.filter(function (t) { return t.id === id; })[0]; }

  /* ---------------- hazır taslak içerikler ---------------- */
  function zumreIcerik(v) {
    var d = v.ders || 'ilgili ders', s = v.siniflar || 'tüm sınıf seviyeleri', t = v.toplanti;
    var g = [], gr = [], k = [];
    var ekle = function (baslik, metin, karar) { g.push(baslik); gr.push(metin); if (karar) k.push(karar); };
    ekle('Açılış, yoklama ve gündemin okunması', 'Zümre başkanı ' + (v.baskan || '') + ' toplantıyı açarak katılımcıları selamladı; yoklama yapıldı ve gündem maddeleri okunarak oy birliğiyle kabul edildi.');
    if (t === 'donem_basi' || t === 'ikinci_donem') {
      ekle('Bir önceki toplantıda alınan kararların değerlendirilmesi', 'Önceki zümre toplantısında alınan kararların uygulanma düzeyi gözden geçirildi. Kararların büyük ölçüde hayata geçirildiği, eksik kalan konuların bu dönem içinde tamamlanacağı belirtildi.', 'Önceki dönemden kalan eksiklikler bu dönemin ilk ayı içinde tamamlanacaktır.');
      ekle('Türkiye Yüzyılı Maarif Modeli öğretim programının incelenmesi ve yıllık planlar', d + ' öğretim programının ' + s + ' için öğrenme çıktıları, beceriler, eğilimler ve değerler boyutuyla incelendiği; ünitelendirilmiş yıllık planların programa ve okulun çalışma takvimine uygun olarak hazırlandığı ifade edildi. Programdaki disiplinler arası ilişkilere ve okuryazarlık becerilerine derslerde yer verilmesinin önemi vurgulandı.', 'Yıllık planlar Türkiye Yüzyılı Maarif Modeli öğretim programına göre hazırlanmış olup ' + s + ' için zümrece ortak uygulanacaktır.');
      ekle('Ölçme ve değerlendirme: ortak yazılı sınavlar, performans görevi ve projeler', 'Yazılı sınavların MEB tarafından yayımlanan konu-soru dağılım tablolarına (senaryolara) uygun hazırlanacağı; açık uçlu, beceri temelli ve gerçek yaşamla ilişkili soruların tercih edileceği belirtildi. Performans görevlerinde dereceli puanlama anahtarı kullanılacağı, proje ödevlerinin öğrenci ilgi ve ihtiyaçlarına göre verileceği konuşuldu. Sınav tarihlerinin e-Okul takvimi ve okul ortak sınav planına göre belirleneceği kararlaştırıldı.', 'Yazılı sınavlar senaryolara uygun, ortak ve açık uçlu ağırlıklı hazırlanacak; performans görevlerinde dereceli puanlama anahtarı kullanılacaktır.');
      ekle('Öğrenci başarısının artırılması ve bireysel farklılıklar', 'Önceki yılın sınav sonuçları ve gözlemler doğrultusunda öğrencilerin güçlük çektiği konular belirlendi. Destekleme ve yetiştirme çalışmaları, ek etkinlikler ve farklılaştırılmış öğretim yöntemleriyle bu konuların pekiştirileceği; kaynaştırma ve BEP öğrencileri için ders içi uyarlamaların yapılacağı belirtildi.', 'Güçlük çekilen konular için destekleme çalışmaları yapılacak; BEP öğrencileri için ölçme-değerlendirmede gerekli uyarlamalar uygulanacaktır.');
      ekle('Ders araç-gereçleri, kaynaklar ve dijital içerik', 'Ders kitaplarının yanında EBA ve MEB onaylı dijital içeriklerden yararlanılacağı; laboratuvar, atölye ve sınıf kitaplığı imkânlarının etkin kullanılacağı, öğrencilere ek mali yük getirecek kaynak istenmeyeceği belirtildi.', 'Ders kitabı ve MEB onaylı dijital içerikler esas alınacak; velilerden ek kaynak alınması istenmeyecektir.');
      ekle('Belirli gün ve haftalar, değerler eğitimi ve diğer zümrelerle iş birliği', 'Dersle ilgili belirli gün ve haftalarda yapılacak etkinlikler planlandı. Değerler eğitimi kapsamında ders içi etkinliklerde sorumluluk, saygı, yardımlaşma gibi değerlere yer verileceği; diğer ders zümreleriyle ortak proje ve etkinlikler yapılabileceği konuşuldu.', 'Belirli gün ve haftalara ait etkinlikler yıllık planda gösterilen tarihlerde zümrece ortak yürütülecektir.');
    } else if (t === 'donem_ara') {
      ekle('Dönem başında alınan kararların uygulanma durumu', 'Dönem başında alınan kararların uygulanma düzeyi görüşüldü; yıllık plana uygun ilerlendiği, planın gerisinde kalınan konuların telafi edileceği belirtildi.', 'Plana göre geride kalınan konular telafi çalışmalarıyla tamamlanacaktır.');
      ekle('Ortak yazılı sınav sonuçlarının analizi', 'Yapılan ortak yazılı sınavların sonuçları soru ve öğrenme çıktısı bazında incelendi. Başarının düşük kaldığı çıktılar belirlenerek bu konularda pekiştirme çalışmaları yapılmasına karar verildi.', 'Başarı düzeyi düşük öğrenme çıktıları için pekiştirme ve yeniden öğretim etkinlikleri yapılacaktır.');
      ekle('Performans görevleri ve projelerin değerlendirilmesi', 'Verilen performans görevlerinin teslim ve değerlendirme süreçleri görüşüldü; dereceli puanlama anahtarlarının öğrencilerle önceden paylaşılmasının olumlu sonuç verdiği belirtildi.');
      ekle('Destekleme ve yetiştirme çalışmaları', 'Güçlük çeken öğrenciler için yürütülen çalışmalar değerlendirildi; BEP öğrencilerinin gelişimlerinin izlenmeye devam edileceği belirtildi.', 'Destekleme ve yetiştirme kursları ile bireysel çalışmalar dönem sonuna kadar sürdürülecektir.');
    } else {
      ekle('Yıl boyunca yapılan çalışmaların değerlendirilmesi', d + ' dersinde yıl başında alınan kararların uygulandığı, yıllık planların büyük ölçüde tamamlandığı; tamamlanamayan konuların gelecek yıl dikkate alınacağı belirtildi.');
      ekle('Öğrenci başarısının değerlendirilmesi', 'Yıl içinde yapılan yazılı sınavlar, performans görevleri ve proje sonuçları değerlendirildi. Başarılı olunan ve güçlük çekilen öğrenme çıktıları belirlendi; sonuçların yeni yıl planlamasında kullanılması kararlaştırıldı.', 'Yıl sonu başarı analizi gelecek eğitim-öğretim yılı planlamasında dikkate alınacaktır.');
      ekle('Gelecek yıl için öneriler ve ihtiyaçlar', 'Ders araç-gereci, laboratuvar ve kaynak ihtiyaçları belirlendi; okul idaresine iletilmesi kararlaştırıldı. Mesleki gelişim için hizmet içi eğitim ihtiyaçları da görüşüldü.', 'Belirlenen araç-gereç ve eğitim ihtiyaçları yazılı olarak okul idaresine bildirilecektir.');
      ekle('Yaz tatili önerileri', 'Öğrencilere yaz tatilinde okuma alışkanlığını ve öğrendiklerini sürdürmeleri için öneriler verilmesi kararlaştırıldı.', 'Öğrencilere yaz tatili için kitap ve etkinlik önerileri verilecektir.');
    }
    if (v.not) ekle('Diğer konular', v.not);
    ekle('Dilek, temenniler ve kapanış', 'Zümre öğretmenleri yeni dönemde başarı dileklerini paylaştı. Toplantı ' + (v.baskan ? 'zümre başkanı ' + v.baskan + ' tarafından ' : '') + 'iyi dileklerle sona erdirildi.');
    return { gundem: g, gorusmeler: gr, kararlar: k };
  }
  function sokIcerik(v) {
    var g = [], gr = [], k = [], sb = v.sube || 'şube', ekle = function (a, b, c) { g.push(a); gr.push(b); if (c) k.push(c); };
    ekle('Açılış ve yoklama', 'Toplantı ' + (v.rehber ? 'sınıf rehber öğretmeni ' + v.rehber + ' tarafından ' : '') + 'açıldı; şube öğretmenlerinin katılımıyla yoklama yapıldı ve gündem kabul edildi.');
    ekle(sb + ' öğrencilerinin genel durumu ve sınıf iklimi', sb + ' şubesinin ' + (v.mevcut ? v.mevcut + ' öğrenciden oluştuğu ' : '') + 've derslere katılımın genel olarak olumlu olduğu belirtildi. Öğretmenler sınıfın güçlü yönlerini ve dikkat edilmesi gereken durumları paylaştı.');
    ekle('Akademik başarı ve dersler arası ortak çalışmalar', 'Öğrencilerin derslere göre başarı durumları karşılaştırıldı; birden fazla derste güçlük çeken öğrenciler belirlenerek ortak bir destek planı yapılması kararlaştırıldı. Okuma-anlama ve problem çözme becerilerinin tüm derslerde desteklenmesi önerildi.', 'Birden fazla derste güçlük çeken öğrenciler için ortak destek planı uygulanacak ve rehberlik servisiyle paylaşılacaktır.');
    ekle('Devam-devamsızlık ve davranış durumu', 'Devamsızlığı artan öğrenciler belirlendi; velileriyle görüşülmesi ve rehberlik servisine bildirilmesi kararlaştırıldı. Okul ve sınıf kurallarına uyum genel olarak iyi değerlendirildi.', 'Devamsızlığı artan öğrencilerin velileriyle sınıf rehber öğretmeni görüşecektir.');
    ekle('Özel eğitim gereksinimi olan ve BEP uygulanan öğrenciler', 'Kaynaştırma eğitimi alan öğrencilerin BEP hedeflerine ulaşma durumları ve ölçme-değerlendirmede yapılacak uyarlamalar görüşüldü.', 'BEP öğrencileri için ders öğretmenleri gerekli uyarlamaları yapacak, gelişim durumu dönem sonunda değerlendirilecektir.');
    ekle('Rehberlik ihtiyaçları ve öğrencilerin ödüllendirilmesi', 'Rehberlik desteğine ihtiyaç duyan öğrencilerin rehberlik servisine yönlendirilmesi; olumlu davranış ve başarı gösteren öğrencilerin teşvik edilmesi kararlaştırıldı.', 'Başarılı ve örnek davranış gösteren öğrenciler okul ödül yönetmeliğine göre teşvik edilecektir.');
    if (v.not) ekle('Şubeye özel konular', v.not);
    ekle('Dilek ve temenniler', 'Öğretmenler iş birliğinin sürdürülmesi dileğiyle görüşlerini paylaştı ve toplantı sona erdi.');
    return { gundem: g, gorusmeler: gr, kararlar: k };
  }
  function veliIcerik(v) {
    var g = [], gr = [], k = [], ekle = function (a, b, c) { g.push(a); gr.push(b); if (c) k.push(c); }, t = v.toplanti;
    ekle('Açılış, tanışma ve gündemin sunulması', 'Sınıf öğretmeni ' + (v.ogretmen || '') + ' velileri selamlayarak toplantıyı açtı; gündem maddeleri hakkında bilgi verdi.');
    if (t === 'donem_basi' || t === 'ikinci_donem') {
      ekle('Okulun işleyişi, ders programı ve önemli tarihler', 'Ders giriş-çıkış saatleri, haftalık ders programı, yazılı sınav ve tatil tarihleri hakkında bilgi verildi. Velilerin e-Okul Veli Bilgilendirme Sistemi’ni düzenli takip etmesi istendi.', 'Veliler e-Okul üzerinden devam, not ve duyuruları düzenli olarak takip edecektir.');
      ekle('Türkiye Yüzyılı Maarif Modeli ve ölçme-değerlendirme', 'Yeni öğretim programlarının beceri ve değer odaklı yapısı, ortak yazılı sınavların MEB senaryolarına göre yapıldığı ve performans görevlerinin değerlendirilme biçimi anlatıldı.');
      ekle('Devam-devamsızlık ve okul kuralları', 'Devamsızlık sınırları ve özürlü devamsızlıkta yapılacak işlemler açıklandı; kılık-kıyafet ve okul kurallarına uyumun önemi vurgulandı.', 'Öğrencinin devamsızlık yapacağı durumlarda veli okula zamanında bilgi verecektir.');
    } else {
      ekle('Dönem içi başarı durumunun değerlendirilmesi', 'Sınıfın genel başarı durumu, yazılı sınav ve performans görevlerinin sonuçları paylaşıldı; bireysel durumlar için velilerle ayrıca görüşülebileceği belirtildi.', 'Başarısı düşen öğrencilerin velileriyle bireysel görüşme yapılacaktır.');
      ekle('Tatil dönemi önerileri', 'Tatil döneminde öğrencilerin kitap okuma, ekran süresi ve uyku düzenine dikkat etmeleri için öneriler verildi.', 'Öğrencilere tatil dönemi için okuma ve etkinlik önerileri verilecektir.');
    }
    ekle('Evde ders çalışma ortamı ve okul-aile iş birliği', 'Düzenli çalışma alışkanlığı, ekran süresinin sınırlandırılması, kitap okuma ve uyku düzeni konularında öneriler sunuldu. Öğrencinin başarısında aile desteğinin önemi vurgulandı.', 'Velilere evde çalışma ve okuma alışkanlığı kazandırma konusunda rehberlik servisinin hazırladığı öneriler iletilecektir.');
    ekle('Rehberlik hizmetleri ve öğrencinin sağlığı', 'Rehberlik servisinin çalışmaları tanıtıldı; öğrencinin sağlık durumu, özel gereksinimi ya da yaşadığı önemli değişiklikler hakkında sınıf öğretmeninin bilgilendirilmesinin önemi belirtildi.');
    if (v.not) ekle('Görüşülen diğer konular', v.not);
    ekle('Velilerin görüş ve önerileri, kapanış', 'Velilerin soruları yanıtlandı ve önerileri not alındı. Toplantı iyi dileklerle sona erdi.');
    return { gundem: g, gorusmeler: gr, kararlar: k };
  }
  var BEP_ORNEK = {
    'Matematik': [['Doğal sayılarla toplama ve çıkarma işlemlerini yapar.', ['Eldesiz toplama işlemi yapar.', 'Eldeli toplama işlemi yapar.', 'Onluk bozmadan çıkarma işlemi yapar.', 'Onluk bozarak çıkarma işlemi yapar.']], ['Günlük yaşamda paralarımızı kullanır.', ['Kâğıt ve madenî paraları tanır.', 'Paraları birbirine dönüştürür.', 'Alışveriş problemlerini çözer.']]],
    'Türkçe': [['Okuduğunu anlar.', ['Kısa bir metni akıcı biçimde sesli okur.', 'Metinle ilgili 5N1K sorularını cevaplar.', 'Metnin ana fikrini söyler.']], ['Duygu ve düşüncelerini yazılı olarak ifade eder.', ['Harfleri doğru ve okunaklı yazar.', 'Basit cümleler kurarak yazar.', 'Bir olayı 3-4 cümleyle anlatır.']]]
  };
  function bepIcerik(v) {
    var d = v.ders || 'Ders', o = BEP_ORNEK[d] || [['' + d + ' dersindeki temel kavramları günlük yaşamla ilişkilendirir.', ['Konuyla ilgili temel kavramları tanır ve örnek verir.', 'Kavramlar arasındaki basit ilişkileri açıklar.', 'Öğrendiklerini günlük yaşamdan bir durumda kullanır.']], ['Dersin temel becerilerini destekle yerine getirir.', ['Yönergeyi dinleyerek etkinliğe başlar.', 'Etkinliği öğretmen desteğiyle tamamlar.', 'Etkinliği bağımsız olarak tamamlar.']]];
    return { performans: v.performans || 'Öğrenci, ' + d + ' dersinde temel kavramları desteklendiğinde kullanabilmekte; çok basamaklı işlemlerde ve dikkat gerektiren etkinliklerde güçlük çekmektedir.', hedefler: o.map(function (x) { return { uda: x[0], kda: x[1], yontem: 'Doğrudan öğretim, model olma, ipucuyla öğretim, bireysel çalışma', materyal: 'Somut materyaller, çalışma yaprakları, görseller, eğitsel oyunlar', degerlendirme: 'Gözlem, kontrol listesi, çalışma yaprağı' }; }), uyarlamalar: ['Sınav süresi ihtiyaca göre uzatılır; sorular sade ve kısa yönergelerle verilir.', 'Sınav ayrı bir ortamda ve okuyucu/yazıcı desteğiyle yapılabilir.', 'Soru sayısı azaltılır, büyük punto ve bol boşluklu kâğıt kullanılır.', 'Değerlendirme BEP’teki kısa dönemli amaçlara göre yapılır.'], aile: ['Aileye her ay öğrencinin gelişimi hakkında bilgi verilir.', 'Evde tekrar edilecek basit etkinlikler aileyle paylaşılır.'] };
  }
  function kulupPlanIcerik(v) {
    var k = v.kulup || 'Kulüp', t = kulupTema(k) || 'Kültür Dünyamız';
    var genel = {
      'Eylül': ['Kulübün tanıtılması, üye öğrencilerin belirlenmesi', 'İlk toplantı: görev dağılımı ve yıllık çalışma planının birlikte hazırlanması'],
      'Ekim': ['Kulüp panosunun hazırlanması', k.replace(' Kulübü', '') + ' konusunda bilgilendirici sunum ve tartışma'],
      'Kasım': ['Konuyla ilgili uzman/kurum ziyareti ya da konuk konuşmacı', 'Öğrenci araştırma ödevlerinin sunulması'],
      'Aralık': ['Okul genelinde farkındalık etkinliği', 'Afiş, broşür ya da kısa video hazırlama'],
      'Ocak': ['1. dönem çalışmalarının değerlendirilmesi ve dönem sonu raporunun hazırlanması'],
      'Şubat': ['2. dönem planının gözden geçirilmesi', 'Grup çalışmasıyla küçük bir proje başlatılması'],
      'Mart': ['Proje çalışmalarının sürdürülmesi', 'Belirli gün ve haftalarla bağlantılı etkinlik'],
      'Nisan': ['Saha gezisi ya da uygulamalı atölye', 'Sosyal sorumluluk çalışması'],
      'Mayıs': ['Yıl sonu sergisi / gösterisi / sunumu', 'Kulüp çalışmalarının okul topluluğuyla paylaşılması'],
      'Haziran': ['Yıl sonu değerlendirme toplantısı', 'Yıllık çalışma raporunun hazırlanması']
    };
    return { tema: t, aylar: AYLAR.map(function (a) { return { ay: a, etkinlikler: genel[a], hedef: a === 'Eylül' ? 'Kulübün yapılandırılması' : (a === 'Ocak' || a === 'Haziran' ? 'Değerlendirme' : t + ' teması kapsamında farkındalık ve beceri geliştirme'), yontem: a === 'Nisan' ? 'Gezi, gözlem, uygulama' : 'Sunum, tartışma, grup çalışması' }; }) };
  }
  function kulupRaporIcerik(v) {
    var l = satirlar(v.yapilanlar);
    if (!l.length) l = ['Kulüp tanıtım toplantısı yapıldı, üyeler belirlendi ve görev dağılımı yapıldı.', 'Yıllık çalışma planı üyelerle birlikte hazırlandı.', 'Kulüp panosu hazırlanarak konuyla ilgili güncel bilgiler paylaşıldı.', 'Okul genelinde farkındalık etkinliği düzenlendi.', 'Öğrenci araştırma ve sunum çalışmaları yapıldı.'];
    return { yapilanlar: l, degerlendirme: 'Kulüp çalışmaları yıllık plana uygun olarak yürütülmüştür. Öğrencilerin etkinliklere katılımı ve sorumluluk alma düzeyi olumlu bulunmuştur. Çalışmalar sayesinde öğrencilerin iş birliği, iletişim ve problem çözme becerilerinin geliştiği gözlemlenmiştir.', oneriler: ['Okul dışı kurumlarla iş birliği artırılabilir.', 'Etkinliklerin e-Okul Sosyal Etkinlik Modülü’ne düzenli işlenmesine devam edilecektir.'] };
  }
  function kurulIcerik(v) {
    var g = [], gr = [], k = [], t = v.toplanti, ekle = function (a, b, c) { g.push(a); gr.push(b); if (c) k.push(c); };
    ekle('Açılış, saygı duruşu ve İstiklâl Marşı', 'Toplantı okul müdürü ' + (v.mudur || '') + ' başkanlığında, saygı duruşu ve İstiklâl Marşı ile açıldı; yoklama yapıldı, ' + (v.yazman ? v.yazman + ' yazman olarak belirlendi ve ' : '') + 'gündem okunarak kabul edildi.');
    if (t === 'donem_basi') {
      ekle('Önceki yıl sonu kurul kararlarının değerlendirilmesi', 'Bir önceki ders yılı sonunda yapılan öğretmenler kurulunda alınan kararlar okunarak uygulanma durumları görüşüldü.');
      ekle('Mevzuat ve Bakanlık yazılarının okunması', (v.kademe === 'Lise' ? 'Ortaöğretim Kurumları Yönetmeliği' : 'Okul Öncesi Eğitim ve İlköğretim Kurumları Yönetmeliği') + ', ölçme ve değerlendirme, sosyal etkinlikler ve disiplin mevzuatındaki güncel düzenlemeler ile Bakanlık ve il/ilçe millî eğitim müdürlüğü yazıları kurula okundu ve açıklandı.', 'Öğretmenler ilgili mevzuat hükümlerine ve Bakanlık yazılarına göre iş ve işlemleri yürütecektir.');
      ekle('Çalışma takvimi, ders dağıtımı ve nöbet çizelgesi', 'Eğitim-öğretim yılı çalışma takvimi, ders dağıtım çizelgesi, sınıf/şube rehber öğretmenlikleri ve nöbet görevleri hakkında bilgi verildi. Nöbetçi öğretmenlerin nöbet yerlerinde zamanında bulunmasının önemi vurgulandı.', 'Ders dağıtımı ve nöbet çizelgesi okul idaresince duyurulduğu şekilde uygulanacaktır.');
      ekle('Öğretim programları ve yıllık planlar', 'Türkiye Yüzyılı Maarif Modeli öğretim programlarının uygulanması, ünitelendirilmiş yıllık planların zümrelerce hazırlanması ve derslerin plana uygun işlenmesi görüşüldü.', 'Yıllık planlar zümre kararlarına uygun olarak hazırlanıp okul idaresine teslim edilecektir.');
      ekle('Ölçme ve değerlendirme', 'Ortak yazılı sınavların MEB konu-soru dağılım tablolarına uygun yapılması, sınav tarihlerinin e-Okul’a zamanında girilmesi, performans görevi ve proje değerlendirmelerinin süresinde tamamlanması konuşuldu.', 'Ortak yazılı sınav takvimi zümrelerce belirlenip okul idaresine bildirilecek; notlar e-Okul’a süresi içinde girilecektir.');
      ekle('Rehberlik ve psikolojik danışma hizmetleri', 'Okul rehberlik ve psikolojik danışma hizmetleri yıllık çalışma programı tanıtıldı; sınıf rehberlik planlarının uygulanması ve risk altındaki öğrencilerin rehberlik servisine yönlendirilmesi görüşüldü.');
      ekle('Özel eğitim ve kaynaştırma uygulamaları', 'Özel eğitim ihtiyacı olan öğrenciler için BEP geliştirme biriminin oluşturulması ve BEP’lerin hazırlanması, ölçme-değerlendirmede gerekli uyarlamaların yapılması konuşuldu.', 'BEP geliştirme birimi oluşturulacak ve BEP’ler süresi içinde hazırlanacaktır.');
      ekle('Sosyal etkinlikler, öğrenci kulüpleri ve belirli gün ve haftalar', 'Sosyal Etkinlikler Politika Belgesi doğrultusunda okul temelli sosyal etkinlikler eylem planının hazırlanması, kulüplerin ve danışman öğretmenlerin belirlenmesi, belirli gün ve haftalara ilişkin görevlendirmeler yapıldı.', 'Kulüp ve sosyal etkinlik görevlendirmeleri okul idaresince duyurulduğu şekilde yürütülecektir.');
      ekle('Okul güvenliği, sağlık ve iş sağlığı ve güvenliği', 'Okul güvenliği, sivil savunma ve acil durum planları, tahliye tatbikatları, iş sağlığı ve güvenliği ile okul sağlığı konularında bilgi verildi.');
      ekle('Destekleme ve yetiştirme kursları, okul-aile iş birliği', 'Destekleme ve yetiştirme kurslarının planlanması, veli toplantılarının zamanı ve okul-aile birliği ile iş birliği konuları görüşüldü.');
    } else if (t === 'ikinci_donem') {
      ekle('Birinci dönemin değerlendirilmesi', 'Birinci dönemde alınan kararların uygulanma durumu, öğrenci başarısı, devam-devamsızlık ve disiplin durumu değerlendirildi.', 'Birinci dönem başarı analizleri zümrelerce incelenerek ikinci dönem planlamasına yansıtılacaktır.');
      ekle('Başarının artırılması için alınacak önlemler', 'Başarısı düşük öğrenciler ve dersler belirlenerek destekleme ve yetiştirme çalışmalarının artırılmasına, velilerle iş birliğinin güçlendirilmesine karar verildi.', 'Başarısı düşük öğrenciler için destekleme çalışmaları yapılacak, velilerle bireysel görüşmeler planlanacaktır.');
      ekle('İkinci dönem ölçme-değerlendirme ve sınav takvimi', 'İkinci dönem ortak yazılı sınav takvimi, performans görevleri ve proje teslim tarihleri görüşüldü.');
      ekle('Sosyal etkinlikler ve belirli gün ve haftalar', 'İkinci dönem kulüp çalışmaları ve belirli gün ve haftalar programı gözden geçirildi.');
      ekle('Rehberlik, okul güvenliği ve diğer konular', 'Rehberlik çalışmaları, okul güvenliği ve nöbet uygulamaları hakkında bilgi verildi.');
    } else if (t === 'yil_sonu') {
      ekle('Ders yılının genel değerlendirmesi', 'Ders yılı başında alınan kararların uygulanma durumu ve yıl boyunca yürütülen çalışmalar değerlendirildi.');
      ekle('Öğrenci başarı durumu ve sınıf geçme', 'Ders ve sınıf bazında başarı durumu, sınıf geçme, sorumluluk ve devamsızlık durumları görüşüldü.', 'Başarı analiz raporları gelecek yıl planlamasında kullanılacaktır.');
      ekle('Ödül ve belgeler, disiplin', 'Teşekkür, takdir ve onur belgesi alacak öğrenciler ile yıl içindeki disiplin durumları hakkında bilgi verildi.');
      ekle('Sosyal etkinlikler ve kulüp raporları', 'Kulüp ve sosyal etkinlik yıl sonu raporlarının teslim edilmesi ve e-Okul Sosyal Etkinlik Modülü’ne işlenmesi istendi.', 'Kulüp yıl sonu raporları belirtilen tarihe kadar okul idaresine teslim edilecektir.');
      ekle('Gelecek ders yılı hazırlıkları ve ihtiyaçlar', 'Gelecek ders yılı için araç-gereç, derslik ve eğitim ihtiyaçları ile mesleki çalışma dönemi programı görüşüldü.');
      ekle('Yaz tatili önerileri ve sene sonu işlemleri', 'Karne dağıtımı, sınıf defterleri ve e-Okul işlemlerinin tamamlanması, öğrencilere yaz tatili önerileri konuşuldu.', 'Sene sonu evrak ve e-Okul işlemleri karne gününden önce tamamlanacaktır.');
    } else {
      ekle('Toplantının yapılma nedeni', 'Kurul, okul müdürünün çağrısıyla olağanüstü olarak toplandı; toplantının gerekçesi hakkında bilgi verildi.');
    }
    if (v.not) ekle('Diğer konular', v.not);
    ekle('Dilek ve temenniler, kapanış', 'Öğretmenlerin görüş ve önerileri alındı; toplantı okul müdürünün iyi dilekleriyle sona erdi.');
    return { gundem: g, gorusmeler: gr, kararlar: k };
  }
  function egzersizIcerik(v) {
    var e = v.egz || 'Egzersiz', sp = v.alan === 'Spor';
    var p = [['Ekim', 'Tanışma, seviye belirleme; çalışma kurallarının ve güvenlik önlemlerinin anlatılması; ' + e + ' hakkında temel bilgiler', 'Öğrencinin hazır bulunuşluğunu belirlemek'], ['Kasım', sp ? 'Isınma ve temel motorik beceriler; temel tekniklerin öğretimi (1)' : 'Temel bilgi ve becerilerin öğretimi (1); uygulamalı çalışmalar', 'Temel becerileri kazanmak'], ['Aralık', sp ? 'Temel tekniklerin öğretimi (2); kuralların uygulamalı anlatımı' : 'Temel bilgi ve becerilerin öğretimi (2); küçük grup çalışmaları', 'Temel becerileri pekiştirmek'],
      ['Ocak', 'Birinci dönem çalışmalarının tekrarı ve değerlendirilmesi', 'Gelişimi değerlendirmek'], ['Şubat', sp ? 'İleri teknik çalışmalar; takım/grup içi iş birliği' : 'İleri düzey çalışmalar; ürün/çalışma hazırlığı', 'Becerileri geliştirmek'], ['Mart', sp ? 'Oyun ve müsabaka uygulamaları; taktik çalışmaları' : 'Proje/ürün çalışmalarının sürdürülmesi', 'Kazanılan becerileri uygulamak'], ['Nisan', sp ? 'Okul içi turnuva/gösteri hazırlığı; centilmenlik ve fair-play' : 'Sergi, gösteri ya da yarışma hazırlığı', 'Öğrenilenleri sergilemek'], ['Mayıs', sp ? 'Okul içi turnuva/gösteri; yıl sonu değerlendirmesi' : 'Sergi/gösteri; yıl sonu değerlendirmesi', 'Yılı değerlendirmek']];
    return { aylar: p.map(function (x) { return { ay: x[0], etkinlikler: [x[1]], hedef: x[2], yontem: sp ? 'Gösterip yaptırma, oyun, uygulama' : 'Anlatım, gösterip yaptırma, uygulama' }; }) };
  }
  function geziIcerik(v) {
    return { amac: 'Öğrencilerin ' + (v.gezi || 'gezilecek yer') + ' hakkında yerinde gözlem yaparak derslerde öğrendiklerini pekiştirmeleri, kültürel ve tarihî değerlerimizi tanımaları, birlikte hareket etme ve sorumluluk alma becerilerini geliştirmeleri amaçlanmaktadır.',
      kazanimlar: ['Gezilen yerin tarihî, kültürel ya da bilimsel önemini açıklar.', 'Gözlemlerini not alarak paylaşır.', 'Grup içinde kurallara uyar, sorumluluk alır.', 'Ortak kullanım alanlarını ve çevreyi korur.'],
      guvenlik: ['Gezi için velilerden yazılı izin alınacak, izni olmayan öğrenci geziye katılmayacaktır.', 'Ulaşım aracının ve sürücünün belgeleri ile araç sigortası kontrol edilecektir.', 'Öğrencilerin sağlık durumları ve kullandıkları ilaçlar önceden öğrenilecek; ilk yardım çantası bulundurulacaktır.', 'Öğrenciler gruplara ayrılacak, her grup bir öğretmenin sorumluluğunda olacak; hareket ve dönüşte yoklama alınacaktır.', 'Veli, öğretmen ve okul idaresinin iletişim numaraları gezi listesinde bulunacaktır.', 'Gezi sonunda öğrenciler gözlemlerini rapor ya da sunum hâlinde paylaşacaktır.'] };
  }
  function kulupAylikIcerik(v) {
    var l = satirlar(v.yapilanlar), ay = v.ay || 'Ekim';
    if (!l.length) { var p = kulupPlanIcerik(v).aylar.filter(function (a) { return a.ay === ay; })[0]; l = (p ? p.etkinlikler : ['Kulüp toplantısı yapıldı.']).map(function (x) { return '…/…/20… - ' + x; }); }
    return { yapilanlar: l, degerlendirme: ay + ' ayında planlanan kulüp çalışmaları yapılmış, öğrencilerin etkinliklere katılımı ve ilgisi olumlu bulunmuştur. Etkinlikler e-Okul Sosyal Etkinlik Modülü’ne işlenmiştir.' };
  }
  var DIL = { izin: '…………… nedeniyle …/…/20… tarihinde (… gün) izinli sayılmam', rapor: 'rahatsızlığım nedeniyle tarafıma verilen ekteki sağlık raporu süresince görevime gelemeyeceğimi; raporumun', nobet: '…/…/20… tarihindeki nöbet görevimin, aynı gün nöbeti bulunmayan …………… öğretmeni ile değiştirilmesi', belge: 'kurumunuzdaki görev sürem ve görev yerimi gösterir hizmet belgesinin tarafıma verilmesi', ders: '…………… görevinde görevlendirilmem', genel: '……………' };
  function dilekceIcerik(v) {
    var a = String(v.aciklama || '').trim(), t = v.dtur || 'izin';
    var giris = 'Okulunuzda ' + (v.gorev || '……………') + ' olarak görev yapmaktayım. ';
    var m = t === 'rapor' ? giris + (a ? a + ' ' : '') + 'Ekte sunduğum sağlık raporu süresince görevime gelemeyeceğim. Raporumun işleme alınması hususunda gereğini bilgilerinize arz ederim.'
      : t === 'genel' ? (a || giris + '……………') + (/arz ederim\.?$/.test(a) ? '' : ' Gereğini bilgilerinize arz ederim.')
      : giris + (a ? a.replace(/\.?$/, '. ') + 'Bu nedenle ' : '') + (t === 'izin' ? (a ? 'belirttiğim tarihlerde izinli sayılmam' : DIL.izin) : DIL[t]) + ' hususunda gereğini bilgilerinize arz ederim.';
    return { metin: m };
  }
  /* ---------- OYS: MEB senaryoları ---------- */
  var SNR = (window.OSC_SNR && window.OSC_SNR.tablolar) || [];
  function oyDurum(v) { var Q = v.oys || (v.oys = {}); Q.sorular = Q.sorular || []; Q.ogr = Q.ogr || []; Q.donem = Q.donem || 1; Q.yazili = Q.yazili || 1; return Q; }
  function oyTablo(Q) { return SNR.filter(function (t) { return +t.sinif === +Q.sinif && t.ders === Q.ders && (+t.donem || 1) === +Q.donem; })[0]; }
  function ciktiTemiz(c) { return String(c || '').replace(/([a-zçğıöşü])a\)\s+[A-ZÇĞİÖŞÜ][\s\S]*$/, '$1').trim(); }
  function oyPlan(Q) {
    var t = oyTablo(Q), l = []; if (!t || !Q.senaryo) return l;
    (t.satirlar || []).forEach(function (r) { var n = ((r.sayi || {})[Q.yazili] || {})[Q.senaryo] || 0; for (var i = 0; i < n; i++) l.push({ kod: r.kod, cikti: ciktiTemiz(r.cikti), puan: 0 }); });
    oyDagit(l); return l;
  }
  function oyDagit(l) { var n = l.length; if (!n) return; var b = Math.floor(100 / n), k = 100 - b * n; l.forEach(function (q, i) { q.puan = b + (i < k ? 1 : 0); }); }
  function oyHesap(Q) {
    var n = Q.sorular.length, top = Q.ogr.map(function (o) { var d = (o.p || []).slice(0, n).filter(function (x) { return x != null && x !== ''; }); return d.length ? d.reduce(function (a, c) { return a + +c; }, 0) : null; });
    var ort = Q.sorular.map(function (_, i) { var s = 0, c = 0; Q.ogr.forEach(function (o) { var x = (o.p || [])[i]; if (x != null && x !== '') { s += +x; c++; } }); return c ? s / c : null; });
    var dt = top.filter(function (x) { return x != null; });
    return { top: top, ort: ort, dolu: dt.length, sinifOrt: dt.length ? dt.reduce(function (a, c) { return a + c; }, 0) / dt.length : 0, toplamPuan: Q.sorular.reduce(function (a, q) { return a + (+q.puan || 0); }, 0) };
  }
  /** e-Okul'dan (ya da Excel'den) kopyalanan öğrenci listesi: sıra no, okul no, ad, soyad, cinsiyet… */
  function eokulAyristir(metin) {
    var o = [];
    String(metin || '').split(/\r?\n/).forEach(function (l) {
      l = l.trim(); if (!l || /öğrenci\s*no|adı\s*soyadı|^s\.?\s*no/i.test(l)) return;
      var p = l.split(/\t+/).map(function (x) { return x.trim(); }).filter(Boolean); if (p.length < 2) p = l.split(/\s+/);
      var say = p.filter(function (x) { return /^\d+$/.test(x); }), ad = p.filter(function (x) { return !/^\d+$/.test(x) && !/^(erkek|kız|kiz|e|k)$/i.test(x) && !/^\d{1,2}[\/.]\d{1,2}/.test(x) && !/^[0-9]+\/[A-ZÇĞİÖŞÜ]$/i.test(x); });
      if (!ad.length) return;
      o.push({ no: say.length > 1 ? say[1] : (say[0] || ''), ad: ad.join(' ').replace(/\s+/g, ' '), p: [] });
    });
    return o;
  }
  /* ---------- YAZILI KÂĞIDI TESLİM: sınıf / ders seçimi, kâğıt sayıları, otomatik toplam ---------- */
  var YT_DERS = DERSLER.filter(function (d) { return ['Sınıf Öğretmenliği', 'Rehberlik', 'Özel Eğitim', 'Okul Öncesi', 'Meslek Dersleri'].indexOf(d) < 0; }).concat(['Almanca', 'Arapça', 'Seçmeli ders', 'Meslek dersi']);
  var YT_SINIF = ['Hazırlık', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'], YT_SUBE = 'ABCÇDEFGHIİJKLMNOPRSŞTUVYZ'.split('');
  function ytDurum(v) { if (!Array.isArray(v.yt)) { v.yt = satirlar(v.satirlar).map(function (x) { var p = parcala(x), sm = String(p[0] || '').split('/'); return { sinif: sm[0] || '', sube: sm[1] || '', ders: p[1] || '', d1: p[2] || '', d2: p[3] || '' }; }); if (!v.yt.length) v.yt.push({ sinif: '', sube: '', ders: '', d1: '', d2: '' }); } return v.yt; }
  function ytSinifAd(r) { return r.sinif ? (r.sinif === 'Hazırlık' ? 'Hz.' : r.sinif) + (r.sube ? '/' + r.sube : '') : ''; }
  function ytSay(x) { var n = parseInt(String(x || '').replace(/\D/g, ''), 10); return isNaN(n) ? 0 : n; }
  function ytFormHtml() {
    var v = S.veri, L = ytDurum(v), kp = v.kapsam || 'I. ve II. dönem', d1 = kp !== 'II. dönem', d2 = kp !== 'I. dönem', t1 = 0, t2 = 0;
    var opt = function (l, s0, bos) { return '<option value="">' + bos + '</option>' + l.map(function (x) { return '<option' + (x === s0 ? ' selected' : '') + '>' + esc(x) + '</option>'; }).join(''); };
    var sat = L.map(function (r, i) { var a = ytSay(r.d1), c = ytSay(r.d2); if (d1) t1 += a; if (d2) t2 += c; var diger = r.ders && YT_DERS.indexOf(r.ders) < 0;
      return '<tr><td class="no">' + (i + 1) + '</td><td><select data-yt="' + i + '" data-k="sinif">' + opt(YT_SINIF, r.sinif, 'Sınıf') + '</select></td><td><select data-yt="' + i + '" data-k="sube">' + opt(YT_SUBE, r.sube, 'Şube') + '</select></td>' +
        '<td><select data-yt="' + i + '" data-k="ders">' + opt(YT_DERS.concat(['Diğer…']), diger ? 'Diğer…' : r.ders, 'Ders seçin') + '</select>' + (diger || r.ders === 'Diğer…' ? '<input data-yt="' + i + '" data-k="dersAd" value="' + esc(diger ? r.ders : '') + '" placeholder="Dersin adı">' : '') + '</td>' +
        (d1 ? '<td><input type="number" min="0" inputmode="numeric" data-yt="' + i + '" data-k="d1" value="' + esc(r.d1) + '" placeholder="0"></td>' : '') + (d2 ? '<td><input type="number" min="0" inputmode="numeric" data-yt="' + i + '" data-k="d2" value="' + esc(r.d2) + '" placeholder="0"></td>' : '') +
        (d1 && d2 ? '<td class="top" data-yttop="' + i + '">' + (a + c || '') + '</td>' : '') + '<td><button type="button" class="ev-ekle" data-ytsil="' + i + '" title="Satırı sil">×</button></td></tr>'; }).join('');
    return '<div class="ev-nb ev-yt"><h3>Şubeler ve kâğıt sayıları <small>toplamlar kendiliğinden hesaplanır</small></h3><div class="ev-kb-tablo"><table><tr><th>Sıra</th><th>Sınıf</th><th>Şube</th><th>Ders</th>' + (d1 ? '<th>I. dönem kâğıt</th>' : '') + (d2 ? '<th>II. dönem kâğıt</th>' : '') + (d1 && d2 ? '<th>Toplam</th>' : '') + '<th></th></tr>' + sat +
      '<tr class="genel"><td></td><td colspan="3">TOPLAM</td>' + (d1 ? '<td id="yt-t1">' + t1 + '</td>' : '') + (d2 ? '<td id="yt-t2">' + t2 + '</td>' : '') + (d1 && d2 ? '<td id="yt-t">' + (t1 + t2) + '</td>' : '') + '<td></td></tr></table></div>' +
      '<div class="ev-kb-dug"><button type="button" class="ev-ekle" id="yt-ekle">+ Şube ekle</button> · <button type="button" class="ev-ekle" id="yt-kopya">+ Son satırı kopyala (sonraki şube)</button></div></div>';
  }
  function ytBagla() {
    var v = S.veri, L = ytDurum(v), yeni = function () { sakla(); vForm(); };
    var toplam = function () { var kp = v.kapsam || 'I. ve II. dönem', d1 = kp !== 'II. dönem', d2 = kp !== 'I. dönem', t1 = 0, t2 = 0; L.forEach(function (r, i) { var a = d1 ? ytSay(r.d1) : 0, c = d2 ? ytSay(r.d2) : 0; t1 += a; t2 += c; var e = kok.querySelector('[data-yttop="' + i + '"]'); if (e) e.textContent = a + c || ''; }); var f = function (id, x) { var e = document.getElementById(id); if (e) e.textContent = x; }; f('yt-t1', t1); f('yt-t2', t2); f('yt-t', t1 + t2); };
    kok.querySelectorAll('[data-yt]').forEach(function (e) {
      var r = L[+e.dataset.yt], k = e.dataset.k;
      if (k === 'd1' || k === 'd2' || k === 'dersAd') e.oninput = function () { if (k === 'dersAd') r.ders = e.value; else r[k] = e.value; sakla(); toplam(); };
      else e.onchange = function () { r[k] = e.value; if (k === 'ders' && e.value === 'Diğer…') r.ders = 'Diğer…'; yeni(); };
    });
    kok.querySelectorAll('[data-ytsil]').forEach(function (b) { b.onclick = function () { L.splice(+b.dataset.ytsil, 1); if (!L.length) L.push({ sinif: '', sube: '', ders: '', d1: '', d2: '' }); yeni(); }; });
    var x = function (id, f) { var e = document.getElementById(id); if (e) e.onclick = f; };
    x('yt-ekle', function () { L.push({ sinif: '', sube: '', ders: '', d1: '', d2: '' }); yeni(); });
    x('yt-kopya', function () { var s0 = L[L.length - 1] || {}, i = YT_SUBE.indexOf(s0.sube); L.push({ sinif: s0.sinif || '', sube: i >= 0 && i < YT_SUBE.length - 1 ? YT_SUBE[i + 1] : '', ders: s0.ders || '', d1: '', d2: '' }); yeni(); });
  }
  /* ---------- NÖBET ÇİZELGESİ: kim, hangi gün, nerede — öğretmen belirler ---------- */
  var NB_GUN = ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma'];
  function nbYerler(v) { var y = satirlar(v.yerler); return y.length ? y : ['Bahçe', 'Zemin kat', '1. kat', '2. kat']; }
  function nbDurum(v) { var N = v.nb || (v.nb = {}); N.max = +N.max || 1; N.a = N.a || {}; return N; }
  function nbSay(v) { var N = nbDurum(v), c = {}, gun = {}; NB_GUN.forEach(function (g) { nbYerler(v).forEach(function (y) { var o = N.a[g + '|' + y]; if (o) { c[o] = (c[o] || 0) + 1; gun[g + '|' + o] = (gun[g + '|' + o] || 0) + 1; } }); }); return { c: c, gun: gun }; }
  function nbFormHtml() {
    var v = S.veri, N = nbDurum(v), ot = satirlar(v.ogretmenler), yr = nbYerler(v), say = nbSay(v);
    if (!ot.length) return '<div class="ev-nb"><h3>Haftalık nöbet yerleri</h3><p class="ev-not">Önce yukarıya nöbetçi öğretmenleri yazın (her satıra bir kişi); ardından her gün ve nöbet yeri için öğretmeni buradan seçin.</p></div>';
    var hucre = function (g, y) { var k = g + '|' + y, s0 = N.a[k] || '', hata = s0 && ((say.c[s0] || 0) > N.max || (say.gun[g + '|' + s0] || 0) > 1);
      return '<td><select data-nb="' + esc(k) + '"' + (hata ? ' class="cift"' : '') + '><option value="">—</option>' + ot.map(function (o) { return '<option' + (o === s0 ? ' selected' : '') + '>' + esc(o) + '</option>'; }).join('') + '</select></td>'; };
    var bos = 0; NB_GUN.forEach(function (g) { yr.forEach(function (y) { if (!N.a[g + '|' + y]) bos++; }); });
    return '<div class="ev-nb"><h3>Haftalık nöbet yerleri <small>her hücrede öğretmeni siz seçin</small></h3>' +
      '<div class="ev-kb-tablo"><table><tr><th>Nöbet yeri</th>' + NB_GUN.map(function (g) { return '<th>' + g + '</th>'; }).join('') + '</tr>' + yr.map(function (y) { return '<tr><th>' + esc(y) + '</th>' + NB_GUN.map(function (g) { return hucre(g, y); }).join('') + '</tr>'; }).join('') + '</table></div>' +
      '<div class="ev-nb-ogr">' + ot.map(function (o) { var n = say.c[o] || 0; return '<span class="' + (n > N.max ? 'fazla' : n ? 'ok' : 'yok') + '">' + esc(o) + ' <b>' + n + '</b></span>'; }).join('') + '</div>' +
      '<div class="ev-kb-dug"><label class="ev-nb-max">Bir öğretmen haftada en fazla <select id="nb-max">' + [1, 2, 3].map(function (n) { return '<option' + (n === N.max ? ' selected' : '') + '>' + n + '</option>'; }).join('') + '</select> nöbet tutsun</label> · <button type="button" class="ev-ekle" id="nb-oto">Boş yerleri dağıt (' + bos + ' boş)</button> · <button type="button" class="ev-ekle" id="nb-temiz">Tümünü temizle</button></div>' +
      '<p class="ev-not">Kırmızı: haftalık sınırı aşan ya da aynı gün iki yerde görünen öğretmen. “Boş yerleri dağıt” yalnızca boş hücreleri, nöbeti az olan öğretmenlerle ve sınırı aşmadan doldurur; sizin seçimlerinize dokunmaz.</p></div>';
  }
  function nbBagla() {
    var v = S.veri, N = nbDurum(v), yeni = function () { sakla(); vForm(); };
    kok.querySelectorAll('[data-nb]').forEach(function (e) { e.onchange = function () { if (e.value) N.a[e.dataset.nb] = e.value; else delete N.a[e.dataset.nb]; yeni(); }; });
    var m = document.getElementById('nb-max'); if (m) m.onchange = function () { N.max = +m.value || 1; yeni(); };
    var x = function (id, f) { var e = document.getElementById(id); if (e) e.onclick = f; };
    x('nb-temiz', function () { if (confirm('Tüm nöbet yerleri temizlensin mi?')) { N.a = {}; yeni(); } });
    x('nb-oto', function () {
      var ot = satirlar(v.ogretmenler), yr = nbYerler(v), say = nbSay(v), kalan = 0;
      NB_GUN.forEach(function (g) { yr.forEach(function (y) { var k = g + '|' + y; if (N.a[k]) return;
        var aday = ot.filter(function (o) { return (say.c[o] || 0) < N.max && !say.gun[g + '|' + o]; }).sort(function (a, b) { return (say.c[a] || 0) - (say.c[b] || 0); })[0];
        if (!aday) { kalan++; return; } N.a[k] = aday; say.c[aday] = (say.c[aday] || 0) + 1; say.gun[g + '|' + aday] = 1; }); });
      yeni(); if (kalan) bildir(kalan + ' yer boş kaldı: öğretmen sayısı yetmiyor. Haftalık sınırı artırın ya da öğretmen ekleyin.', 'hata'); else bildir('Boş yerler dağıtıldı.', 'ok');
    });
  }
  /* ---------- KELEBEK SINAV SİSTEMİ ---------- */
  function kbDurum(v) { var K = v.kb || (v.kb = {}); K.siniflar = K.siniflar || []; K.salonlar = K.salonlar || []; K.kural = K.kural || 'sinif'; K.sira = K.sira || 'karisik'; if (K.kendi === undefined) K.kendi = 1; return K; }
  function kbSeviye(ad) { var m = String(ad || '').match(/^\s*(\d{1,2})/); return m ? m[1] : String(ad || ''); }
  function kbAdBul(satirlar, yedek) {
    for (var i = 0; i < Math.min(satirlar.length, 12); i++) { var t = satirlar[i].join(' '), m = t.match(/(\d{1,2})\s*\.?\s*Sınıf\s*[\/\-]?\s*([A-ZÇĞİÖŞÜ])\s*Şubesi/i) || t.match(/\b(\d{1,2})\s*[\/\-]\s*([A-ZÇĞİÖŞÜ])\b/); if (m) return m[1] + '/' + m[2].toLocaleUpperCase('tr'); }
    return yedek;
  }
  /** bir Excel sayfasını (satır dizisi) şubelere ayırır: e-Okul sınıf listesi, “Sınıf/Şube” sütunlu tek liste ya da başlıksız liste */
  function kbSayfa(satirlar, yedekAd) {
    satirlar = satirlar.map(function (r) { return (r || []).map(function (x) { return x == null ? '' : String(x).trim(); }); });
    var bi = -1, kol = {};
    for (var i = 0; i < Math.min(satirlar.length, 30) && bi < 0; i++) {
      var r = satirlar[i], k = {};
      r.forEach(function (h, j) { var x = h.toLocaleLowerCase('tr').replace(/\s+/g, ' ');
        if (k.no == null && /(öğrenci|okul)\s*(no|numarası)|^no\.?$|^numara(sı)?$/.test(x) && !/s[ıi]ra/.test(x)) k.no = j;
        else if (k.adsoyad == null && /ad[ıi]?\s*(ve\s*)?soyad[ıi]?|ad[ıi]? soyad/.test(x)) k.adsoyad = j;
        else if (k.ad == null && /^(öğrencinin )?ad[ıi]?$/.test(x)) k.ad = j;
        else if (k.soyad == null && /^soyad[ıi]?$/.test(x)) k.soyad = j;
        else if (k.sinif == null && /^(sınıf|şube|sınıfı|şubesi|sınıf\s*\/\s*şube|sınıf-şube)$/.test(x)) k.sinif = j;
        else if (k.cins == null && /cinsiyet/.test(x)) k.cins = j; });
      if ((k.ad != null || k.adsoyad != null) && (k.no != null || k.soyad != null || k.adsoyad != null)) { bi = i; kol = k; }
    }
    var gruplar = {}, sira = [], ekle = function (sn, o) { if (!gruplar[sn]) { gruplar[sn] = []; sira.push(sn); } gruplar[sn].push(o); };
    var varsAd = kbAdBul(satirlar, yedekAd);
    if (bi >= 0) {
      satirlar.slice(bi + 1).forEach(function (r) {
        var ad = kol.adsoyad != null ? r[kol.adsoyad] : ((r[kol.ad] || '') + ' ' + (kol.soyad != null ? r[kol.soyad] || '' : '')).trim();
        if (!ad || /^(toplam|erkek|kız|kiz)\b/i.test(ad) || /ad[ıi]\s*soyad/i.test(ad)) return;
        var no = kol.no != null ? String(r[kol.no] || '').replace(/\.0$/, '') : '';
        if (kol.no != null && !/^\d+$/.test(no) && !/\d/.test(no)) { if (!no) return; }
        var sn = kol.sinif != null && r[kol.sinif] ? String(r[kol.sinif]).replace(/\s*[\-\/]\s*/, '/').replace(/\.\s*sınıf\s*/i, '/').replace(/\s*şube(si)?/i, '').toLocaleUpperCase('tr') : varsAd;
        ekle(sn, { no: no, ad: ad.replace(/\s+/g, ' '), c: kol.cins != null ? String(r[kol.cins] || '').charAt(0).toLocaleUpperCase('tr') : '' });
      });
    } else eokulAyristir(satirlar.map(function (r) { return r.filter(Boolean).join('\t'); }).join('\n')).forEach(function (o) { ekle(varsAd, { no: o.no, ad: o.ad, c: '' }); });
    return sira.map(function (sn) { return { ad: sn, ogr: gruplar[sn] }; });
  }
  function xlsxYukle() { return window.XLSX ? Promise.resolve() : new Promise(function (ok, no) { var sc = document.createElement('script'); sc.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js'; sc.onload = ok; sc.onerror = function () { no(new Error('Excel okuyucu yüklenemedi; internet bağlantınızı kontrol edin.')); }; document.head.appendChild(sc); }); }
  function kbMetinSatir(t) { var l = String(t || '').replace(/^\ufeff/, '').split(/\r?\n/), ay = /\t/.test(l[0] || '') ? '\t' : /;/.test(l.slice(0, 5).join('')) ? ';' : ','; return l.map(function (x) { return x.split(ay).map(function (y) { return y.replace(/^"|"$/g, '').trim(); }); }); }
  function kbDosyalar(files) {
    var hepsiMetin = [].every.call(files, function (f) { return /\.(csv|txt)$/i.test(f.name); });
    if (hepsiMetin) return Promise.all([].map.call(files, function (f) { return f.text().then(function (t) { return kbSayfa(kbMetinSatir(t), f.name.replace(/\.[^.]+$/, '')).filter(function (g) { return g.ogr.length; }); }); })).then(function (ll) { return [].concat.apply([], ll); });
    return xlsxYukle().then(function () {
      return Promise.all([].map.call(files, function (f) { return f.arrayBuffer().then(function (buf) {
        var wb = window.XLSX.read(buf, { type: 'array' }), l = [];
        wb.SheetNames.forEach(function (sn) { var rows = window.XLSX.utils.sheet_to_json(wb.Sheets[sn], { header: 1, raw: false, defval: '' }); if (!rows.length) return;
          var yedek = /^(sheet|sayfa)\s*\d*$/i.test(sn) || wb.SheetNames.length === 1 ? f.name.replace(/\.[^.]+$/, '') : sn;
          kbSayfa(rows, yedek).forEach(function (g) { if (g.ogr.length) l.push(g); }); });
        return l; }); }));
    }).then(function (ll) { return [].concat.apply([], ll); });
  }
  function kbKap(s) { return (+s.sutun || 0) * (+s.sira || 0) * (+s.kisi || 0); }
  function kbKarist(a) { for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)), t = a[i]; a[i] = a[j]; a[j] = t; } return a; }
  /** kelebek dağıtımı: şubeler salonlara eşit yayılır, sıra arkadaşı ve önündeki öğrenci aynı şubeden (ya da seviyeden) olmaz */
  function kbDagit(v) {
    var K = kbDurum(v), uyari = [], kural = K.kural;
    var sal = K.salonlar.filter(function (x) { return kbKap(x) > 0; }).map(function (x) { return { ad: x.ad || 'Salon', gozetmen: x.gozetmen || '', sutun: +x.sutun, sira: +x.sira, kisi: +x.kisi, kap: kbKap(x), ogr: [] }; });
    var sn = K.siniflar.filter(function (c) { return c.ogr && c.ogr.length; });
    if (kural === 'seviye' && sn.map(function (c) { return kbSeviye(c.ad); }).filter(function (x, i, a) { return a.indexOf(x) === i; }).length < 2) { kural = 'sinif'; uyari.push('Tek sınıf seviyesi olduğu için yan yana oturma kuralı “aynı şube” olarak uygulandı.'); }
    var anahtar = function (o) { return kural === 'seviye' ? kbSeviye(o.sinif) : o.sinif; };
    var toplam = 0, kap = sal.reduce(function (a, x) { return a + x.kap; }, 0);
    var kuyruk = sn.map(function (c) { var l = c.ogr.map(function (o) { return { no: o.no, ad: o.ad, sinif: c.ad }; }); toplam += l.length; if (K.sira === 'karisik') kbKarist(l); else l.sort(function (a, b) { return (+a.no || 0) - (+b.no || 0); }); return l; });
    if (toplam > kap) uyari.push(toplam + ' öğrenci var, salonların toplam kapasitesi ' + kap + '. ' + (toplam - kap) + ' öğrenci yerleştirilemedi; salon ekleyin ya da sıra sayısını artırın.');
    /* 1) salonlara dağıtım: her şube, salon kapasitelerine oranla bütün salonlara yayılır */
    var yer = sal.map(function (x) { return x.kap; });
    var anaTop = {}; kuyruk.forEach(function (l) { l.forEach(function (o) { anaTop[anahtar(o)] = (anaTop[anahtar(o)] || 0) + 1; }); });
    /* şubeleri dönüşümlü sırayla dağıt ki her salona her şubeden düzenli gelsin */
    var sirali = [], kq = kuyruk.map(function (l) { return l.slice(); }); while (kq.some(function (l) { return l.length; })) kq.sort(function (a, b) { return b.length - a.length; }).forEach(function (l) { if (l.length) sirali.push(l.shift()); });
    var dolu = sal.map(function () { return { s: {}, a: {} }; });
    [sirali].forEach(function (l) {
      l.forEach(function (o) {
        var en = -1, enP = -Infinity, ak = anahtar(o), st = kuyruk.filter(function (q) { return q.length && q[0].sinif === o.sinif; })[0] || [], sn0 = st.length || 1;
        sal.forEach(function (x, i) { if (!yer[i]) return; var oran = x.kap / kap; var p = (anaTop[ak] * oran - (dolu[i].a[ak] || 0)) * 6 + (sn0 * oran - (dolu[i].s[o.sinif] || 0)) + yer[i] / x.kap * 0.01 - (K.kendi && x.ad === o.sinif ? 1000 : 0); if (p > enP) { enP = p; en = i; } });
        if (en >= 0) { dolu[en].a[ak] = (dolu[en].a[ak] || 0) + 1; dolu[en].s[o.sinif] = (dolu[en].s[o.sinif] || 0) + 1; }
        if (en < 0) return; sal[en].ogr.push(o); yer[en]--;
      });
    });
    /* 2) salon içinde yerleşim */
    var cak = 0;
    sal.forEach(function (x) {
      var grup = {}; x.ogr.forEach(function (o) { var k = anahtar(o); (grup[k] = grup[k] || []).push(o); });
      var yerler = []; for (var r = 0; r < x.sira; r++) for (var c = 0; c < x.sutun; c++) for (var k = 0; k < x.kisi; k++) {
        var yasak = []; var sol = k > 0 ? yerler[yerler.length - 1] : null, on = r > 0 ? yerler[((r - 1) * x.sutun + c) * x.kisi + k] : null;
        var sagKomsu = k === 0 && c > 0 ? yerler[yerler.length - 1] : null;
        [sol, on, sagKomsu].forEach(function (y) { if (y) yasak.push(anahtar(y)); });
        var aday = Object.keys(grup).filter(function (g) { return grup[g].length; }).sort(function (a, b) { return grup[b].length - grup[a].length; });
        if (!aday.length) { yerler.push(null); continue; }
        var sec = aday.filter(function (g) { return yasak.indexOf(g) < 0; })[0];
        if (!sec) { sec = aday[0]; if (sol && anahtar(sol) === sec) cak++; }
        yerler.push(grup[sec].shift());
      }
      /* onarım: çakışan öğrenciyi, takası çakışmayı azaltan başka bir öğrenciyle değiştir */
      var D = x.sutun * x.kisi, komsu = function (i) { var r = Math.floor(i / D), c = Math.floor((i % D) / x.kisi), k = i % x.kisi, l = [];
        if (k > 0) l.push(i - 1); if (k < x.kisi - 1) l.push(i + 1); if (k === 0 && c > 0) l.push(i - 1); if (k === x.kisi - 1 && c < x.sutun - 1) l.push(i + 1);
        if (r > 0) l.push(i - D); if (r < x.sira - 1 && i + D < yerler.length) l.push(i + D); return l; };
      var puan = function (i, o) { if (!o) return 0; var n = 0; komsu(i).forEach(function (j) { var y = yerler[j]; if (y && y !== o && anahtar(y) === anahtar(o)) n++; }); return n; };
      for (var tur = 0; tur < 12; tur++) { var degisti = false;
        for (var i = 0; i < yerler.length; i++) { if (!yerler[i] || !puan(i, yerler[i])) continue;
          for (var j = 0; j < yerler.length; j++) { if (j === i || (yerler[j] && anahtar(yerler[j]) === anahtar(yerler[i]))) continue;
            var once = puan(i, yerler[i]) + puan(j, yerler[j]), a = yerler[i]; yerler[i] = yerler[j]; yerler[j] = a;
            if (puan(i, yerler[i]) + puan(j, yerler[j]) < once) { degisti = true; break; } a = yerler[i]; yerler[i] = yerler[j]; yerler[j] = a; } }
        if (!degisti) break; }
      cak = 0; for (var i2 = 0; i2 < yerler.length; i2++) if (yerler[i2]) komsu(i2).forEach(function (j) { if (j > i2 && yerler[j] && anahtar(yerler[j]) === anahtar(yerler[i2])) cak++; });
      x.yerler = yerler; delete x.ogr; x.cak = cak;
    });
    cak = sal.reduce(function (a, x) { return a + x.cak; }, 0);
    if (cak) uyari.push(cak + ' yerde aynı ' + (kural === 'seviye' ? 'seviyeden' : 'şubeden') + ' iki öğrenci yan yana ya da arka arkaya oturuyor (salondaki şube dağılımı nedeniyle önlenemedi).');
    return { salonlar: sal, uyari: uyari, toplam: Math.min(toplam, kap), kural: kural };
  }
  function kbFormHtml() {
    var K = kbDurum(S.veri), top = K.siniflar.reduce(function (a, c) { return a + c.ogr.length; }, 0), kap = K.salonlar.reduce(function (a, x) { return a + kbKap(x); }, 0);
    var sec = function (k, l, v) { return '<select data-kbo="' + k + '">' + l.map(function (x) { return '<option value="' + x[0] + '"' + (String(v) === String(x[0]) ? ' selected' : '') + '>' + esc(x[1]) + '</option>'; }).join('') + '</select>'; };
    return '<div class="ev-kb">' +
      '<h3>1. Şube listeleri <small>' + K.siniflar.length + ' şube · ' + top + ' öğrenci</small></h3>' +
      '<div class="ev-kb-yukle"><label class="ev-btn ana">📂 Excel’den yükle<input type="file" id="kb-dosya" accept=".xls,.xlsx,.csv,.ods" multiple hidden></label><span>e-Okul’dan aldığınız şube listeleri (.xls / .xlsx). Birden çok dosya seçebilirsiniz; her dosya ya da her sayfa bir şube sayılır. “Sınıf/Şube” sütunu olan tek bir liste de olur.</span></div>' +
      '<details class="ev-kb-yap"><summary>ya da listeyi yapıştırın</summary><div class="ev-kb-yap-ic"><input id="kb-yad" placeholder="Şube adı (ör. 9/A)"><textarea id="kb-ymetin" rows="5" placeholder="e-Okul ya da Excel’den kopyalanan satırlar: No, Ad, Soyad…"></textarea><button type="button" class="ev-btn" id="kb-yekle">Şubeyi ekle</button></div></details>' +
      (K.siniflar.length ? '<div class="ev-kb-sn">' + K.siniflar.map(function (c, i) { return '<span><input data-kbs="' + i + '" value="' + esc(c.ad) + '" title="Şube adı"><b>' + c.ogr.length + '</b><button type="button" data-kbssil="' + i + '" title="Kaldır">×</button></span>'; }).join('') + '<button type="button" class="ev-ekle" id="kb-stemiz">Tümünü kaldır</button></div>' : '<p class="ev-not">Henüz şube yüklenmedi.</p>') +
      '<h3>2. Sınav salonları <small>kapasite ' + kap + (top ? ' · ' + (kap >= top ? '<em class="ok">yeterli</em>' : '<em class="hata">' + (top - kap) + ' kişilik yer eksik</em>') : '') + '</small></h3>' +
      '<div class="ev-kb-tablo"><table><tr><th>Salon</th><th>Sıra sütunu</th><th>Sütundaki sıra</th><th>Sırada kişi</th><th>Kapasite</th><th>Gözetmen</th><th></th></tr>' +
      K.salonlar.map(function (x, i) { var n = function (k, mx) { return '<input type="number" min="1" max="' + mx + '" data-kbl="' + i + '" data-k="' + k + '" value="' + esc(x[k]) + '">'; };
        return '<tr><td><input data-kbl="' + i + '" data-k="ad" value="' + esc(x.ad) + '"></td><td>' + n('sutun', 6) + '</td><td>' + n('sira', 12) + '</td><td>' + n('kisi', 3) + '</td><td><b>' + kbKap(x) + '</b></td><td><input data-kbl="' + i + '" data-k="gozetmen" value="' + esc(x.gozetmen || '') + '" placeholder="Ad Soyad"></td><td><button type="button" class="ev-ekle" data-kblsil="' + i + '">×</button></td></tr>'; }).join('') +
      '</table></div><div class="ev-kb-dug"><button type="button" class="ev-ekle" id="kb-lekle">+ Salon ekle</button>' + (K.siniflar.length ? ' · <button type="button" class="ev-ekle" id="kb-lsinif">Şube dersliklerini salon yap</button>' : '') + '</div>' +
      '<h3>3. Yerleşim kuralları</h3><div class="ev-form"><label><span>Yan yana ve arka arkaya oturmasın</span>' + sec('kural', [['sinif', 'Aynı şubeden öğrenciler'], ['seviye', 'Aynı sınıf seviyesinden öğrenciler (9 ile 10 karışık)']], K.kural) + '</label>' +
      '<label><span>Salon içinde sıra</span>' + sec('sira', [['karisik', 'Karışık (her dağıtımda farklı)'], ['no', 'Okul numarasına göre']], K.sira) + '</label>' +
      '<label><span>Öğrenci kendi dersliğinde sınava girmesin</span>' + sec('kendi', [[1, 'Evet (salon adı şube adıyla aynıysa)'], [0, 'Fark etmez']], K.kendi) + '</label></div>' +
      '<p class="ev-not">🔒 Öğrenci listeleri yalnızca bu tarayıcıda işlenir; sunucumuza gönderilmez. “Oluştur” düğmesine her bastığınızda yeni bir karma dağıtım yapılır.</p></div>';
  }
  function kbBagla() {
    var K = kbDurum(S.veri), yeni = function () { sakla(); vForm(); };
    var ekle = function (l) { var n = 0; l.forEach(function (g) { var var_ = K.siniflar.filter(function (c) { return c.ad === g.ad; })[0]; if (var_) var_.ogr = g.ogr; else K.siniflar.push(g); n += g.ogr.length; }); K.siniflar.sort(function (a, b) { return a.ad.localeCompare(b.ad, 'tr', { numeric: true }); }); return n; };
    var d = document.getElementById('kb-dosya'); if (d) d.onchange = function () { if (!d.files.length) return; bildir('Dosyalar okunuyor…'); kbDosyalar(d.files).then(function (l) { if (!l.length) return bildir('Dosyalarda öğrenci listesi bulunamadı. e-Okul şube listesini Excel olarak indirip yükleyin ya da satırları yapıştırın.', 'hata'); var n = ekle(l); yeni(); bildir(l.length + ' şube, ' + n + ' öğrenci yüklendi. Şube adlarını kontrol edin.', 'ok'); }).catch(function (e) { bildir(e.message || 'Dosya okunamadı.', 'hata'); }); };
    var x = function (id, f) { var e = document.getElementById(id); if (e) e.onclick = f; };
    x('kb-yekle', function () { var ad = document.getElementById('kb-yad').value.trim(), l = eokulAyristir(document.getElementById('kb-ymetin').value); if (!ad) return bildir('Şube adını yazın.', 'hata'); if (!l.length) return bildir('Listede öğrenci bulunamadı.', 'hata'); ekle([{ ad: ad, ogr: l.map(function (o) { return { no: o.no, ad: o.ad, c: '' }; }) }]); yeni(); });
    x('kb-stemiz', function () { if (confirm('Yüklenen tüm şube listeleri kaldırılsın mı?')) { K.siniflar = []; yeni(); } });
    x('kb-lekle', function () { K.salonlar.push({ ad: 'Salon ' + (K.salonlar.length + 1), sutun: 3, sira: 6, kisi: 2, gozetmen: '' }); yeni(); });
    x('kb-lsinif', function () { K.siniflar.forEach(function (c) { if (K.salonlar.some(function (s) { return s.ad === c.ad; })) return; K.salonlar.push({ ad: c.ad, sutun: 3, sira: Math.max(1, Math.ceil(c.ogr.length / 6)), kisi: 2, gozetmen: '' }); }); yeni(); });
    kok.querySelectorAll('[data-kbs]').forEach(function (e) { e.onchange = function () { K.siniflar[+e.dataset.kbs].ad = e.value.trim() || K.siniflar[+e.dataset.kbs].ad; sakla(); }; });
    kok.querySelectorAll('[data-kbssil]').forEach(function (b) { b.onclick = function () { K.siniflar.splice(+b.dataset.kbssil, 1); yeni(); }; });
    kok.querySelectorAll('[data-kblsil]').forEach(function (b) { b.onclick = function () { K.salonlar.splice(+b.dataset.kblsil, 1); yeni(); }; });
    kok.querySelectorAll('[data-kbl]').forEach(function (e) { e.onchange = function () { var o = K.salonlar[+e.dataset.kbl], k = e.dataset.k; o[k] = /sutun|sira|kisi/.test(k) ? Math.max(1, +e.value || 1) : e.value; if (/sutun|sira|kisi/.test(k)) yeni(); else sakla(); }; });
    kok.querySelectorAll('[data-kbo]').forEach(function (e) { e.onchange = function () { K[e.dataset.kbo] = e.dataset.kbo === 'kendi' ? +e.value : e.value; sakla(); }; });
  }
  /* ---------- YILLIK PLAN ---------- */
  var TK = C.takvim || {}, KAT = C.katalog || {};
  function ypDurum(v) { var P = v.yp || (v.yp = {}); P.uniteler = P.uniteler || []; return P; }
  function gun(s) { var p = String(s || '').split('-'); return p.length === 3 ? new Date(+p[0], +p[1] - 1, +p[2]) : null; }
  var AYLAR_TR = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
  function aralik(a, b) { return a.getDate() + (a.getMonth() !== b.getMonth() ? ' ' + AYLAR_TR[a.getMonth()] : '') + ' – ' + b.getDate() + ' ' + AYLAR_TR[b.getMonth()]; }
  function icinde(d, l) { return (l || []).filter(function (x) { var a = gun(x.bas), b = gun(x.bit || x.bas); return a && d >= a && d <= b; })[0]; }
  function bghTarih(ad, yil) { var m = /^(\d{1,2})(?:-\d{1,2})? (Ocak|Şubat|Mart|Nisan|Mayıs|Haziran|Temmuz|Ağustos|Eylül|Ekim|Kasım|Aralık)/.exec(ad); return m ? new Date(yil, AYLAR_TR.indexOf(m[2]), +m[1]) : null; }
  /** takvimden haftalar: {bas, bit, donem, tatil, notlar[], bgh[]} */
  function haftalar() {
    var b = gun(TK.baslangic), e = gun(TK.bitis), d2 = gun(TK.donem2), o = []; if (!b || !e) return o;
    var p = new Date(b); p.setDate(p.getDate() - ((p.getDay() + 6) % 7));
    while (p <= e) {
      var cuma = new Date(p); cuma.setDate(cuma.getDate() + 4); var is = 0, tt = null, not = [];
      for (var i = 0; i < 5; i++) { var g = new Date(p); g.setDate(g.getDate() + i); if (g < b || g > e) continue; var t = icinde(g, TK.tatiller); if (t) { tt = t; continue; } is++; var r = icinde(g, TK.resmi); if (r && not.indexOf(r.ad) < 0) not.push(r.ad + ' (' + g.getDate() + ' ' + AYLAR_TR[g.getMonth()] + ', resmî tatil)'); }
      var bg = BGH.filter(function (x) { var t = bghTarih(x[1], p.getFullYear()) || null, t2 = bghTarih(x[1], cuma.getFullYear()); return (t && t >= p && t <= cuma) || (t2 && t2 >= p && t2 <= cuma); }).map(function (x) { return x[1]; });
      o.push({ bas: new Date(p), bit: cuma, donem: d2 && p >= d2 ? 2 : 1, tatil: is ? null : (tt ? tt.ad : 'Tatil'), notlar: not, bgh: bg, is: is });
      p.setDate(p.getDate() + 7);
    }
    return o;
  }
  function ypYazi(u) { return (u || []).map(function (x) { return '## ' + x.ad + (x.saat ? ' | ' + x.saat : '') + '\n' + (x.ciktilar || []).map(function (c) { return (c.kod ? c.kod + ' | ' : '') + c.ad; }).join('\n'); }).join('\n\n'); }
  function ypCoz(t) { var u = [], s = null; String(t || '').split('\n').forEach(function (l) { l = l.trim(); if (!l) return; if (/^##/.test(l)) { var p = l.replace(/^#+\s*/, '').split('|'); s = { ad: p[0].trim(), saat: parseInt(p[1], 10) || 0, ciktilar: [] }; u.push(s); return; } if (!s) { s = { ad: 'Ünite', saat: 0, ciktilar: [] }; u.push(s); } var q = l.split('|'); if (q.length > 1 && /^[A-ZÇĞİÖŞÜa-z0-9.\-]{2,25}$/.test(q[0].trim())) s.ciktilar.push({ kod: q[0].trim(), ad: q.slice(1).join('|').trim() }); else s.ciktilar.push({ kod: '', ad: l }); }); return u; }
  /** haftalara ünite ve çıktı dağıtımı */
  function ypPlanMeb(P) {
    var H = haftalar(), sat = [], k = 0, W = P.haftalar || [], ders = 0;
    H.forEach(function (w, wi) {
      var tarih = aralik(w.bas, w.bit), ay = AYLAR_TR[w.bas.getMonth()], bgh = w.bgh.join('\n'), not = w.notlar.join('\n');
      if (w.tatil) { sat.push({ ay: ay, hafta: tarih, saat: '', unite: trUp(w.tatil), konu: '', cikti: '', surec: '', olcme: '', bilesen: '', bgh: bgh, aciklama: '', tatil: 1 }); return; }
      var x = W[k++]; ders++;
      if (!x) { sat.push({ ay: ay, hafta: ders + '. hafta\n' + tarih, saat: String(P.saat || ''), unite: 'GENEL TEKRAR / OKUL TEMELLİ PLANLAMA', konu: '', cikti: '', surec: '', olcme: '', bilesen: '', bgh: bgh, aciklama: not }); return; }
      var b2 = String(x[10] || '').split('\n').filter(function (z) { return z.trim() && bgh.indexOf(z.trim()) < 0; });
      sat.push({ ay: ay, hafta: ders + '. hafta\n' + tarih, saat: x[1] || String(P.saat || ''), unite: x[2] || '', konu: x[3] || '', cikti: x[4] || '', surec: x[5] || '', olcme: x[6] || '', bilesen: [x[7], x[8], x[9]].filter(Boolean).join('\n'), bgh: [bgh].concat(b2).filter(Boolean).join('\n'), aciklama: (x[11] ? 'Okul temelli planlama\n' : '') + (w.donem === 2 && H[wi - 1] && H[wi - 1].donem === 1 ? '2. dönem başlangıcı\n' : '') + not });
    });
    return { satir: sat, hafta: H.filter(function (w) { return !w.tatil; }).length, saat: P.saat || '', meb: 1 };
  }
  function ypPlan(P) {
    if (P.kaynak === 'meb' && P.haftalar && P.haftalar.length) return ypPlanMeb(P);
    var H = haftalar(), h = Math.max(1, +P.saat || 2), otp = Math.max(0, +P.otp || 0), dersH = H.filter(function (w) { return !w.tatil; });
    // yalnızca 1. dönem çıktıları varsa (senaryo tablosu), üniteler 1. dönem haftalarına dağıtılır
    var sadece1 = P.kaynak === 'senaryo' && P.donemler && P.donemler.length === 1 && +P.donemler[0] === 1, d1 = dersH.filter(function (w) { return w.donem === 1; }).length;
    var otpH = sadece1 ? 0 : Math.min(dersH.length, Math.ceil(otp / h)), kap = ((sadece1 ? d1 : dersH.length) - otpH) * h, U = (P.uniteler || []).filter(function (u) { return u.ad; });
    var verilen = U.reduce(function (a, u) { return a + (+u.saat || 0); }, 0), eksik = U.filter(function (u) { return !(+u.saat); }), agir = eksik.reduce(function (a, u) { return a + Math.max(1, (u.ciktilar || []).length); }, 0);
    var sure = U.map(function (u) { return +u.saat || 0; });
    if (eksik.length) { var kalan = Math.max(eksik.length, kap - verilen); U.forEach(function (u, i) { if (!(+u.saat)) sure[i] = kalan * Math.max(1, (u.ciktilar || []).length) / agir; }); }
    var top = sure.reduce(function (a, c) { return a + c; }, 0); if (top > kap && top > 0) sure = sure.map(function (x) { return x * kap / top; });
    var bas = [], c = 0; sure.forEach(function (x) { bas.push(c); c += x; }); var bitis = c;
    var sat = [], k = 0;
    H.forEach(function (w, wi) {
      var tarih = aralik(w.bas, w.bit), ay = AYLAR_TR[w.bas.getMonth()];
      var bgh = w.bgh.join('\n'), aciklama = w.notlar.join('\n');
      if (w.tatil) { sat.push({ ay: ay, hafta: tarih, saat: '', unite: trUp(w.tatil), cikti: '', olcme: '', bgh: bgh, aciklama: '', tatil: 1 }); return; }
      var hi = k++, a = hi * h, b = a + h;
      if (sadece1 && w.donem === 2) { sat.push({ ay: ay, hafta: (hi + 1) + '. hafta\n' + tarih, saat: String(h), unite: '', cikti: '', olcme: '', bgh: bgh, aciklama: (H[wi - 1] && H[wi - 1].donem === 1 ? '2. dönem başlangıcı. 2. dönem ünitelerini listeye eklediğinizde bu haftalar dolar.\n' : '') + aciklama }); return; }
      if (hi >= dersH.length - otpH) { sat.push({ ay: ay, hafta: (hi + 1) + '. hafta\n' + tarih, saat: String(h), unite: 'OKUL TEMELLİ PLANLAMA', cikti: 'Okulun ve öğrencilerin ihtiyaçlarına göre planlanan çalışmalar (proje, gezi, sosyal etkinlik, pekiştirme)', olcme: '', bgh: bgh, aciklama: aciklama }); return; }
      var un = [], ci = [], ol = [];
      U.forEach(function (u, i) { var s = bas[i], e = s + sure[i]; if (e <= a || s >= b || sure[i] <= 0) return; un.push(u.ad); var n = (u.ciktilar || []).length; (u.ciktilar || []).forEach(function (x, j) { var cs = s + j * sure[i] / n, ce = s + (j + 1) * sure[i] / n; if (ce > a && cs < b) ci.push((x.kod ? x.kod + ' ' : '') + x.ad); }); if (e > a && e <= b + 1e-9) ol.push('Ünite/tema sonu değerlendirme'); });
      if (!un.length && a >= bitis) { un.push('GENEL TEKRAR VE PEKİŞTİRME'); }
      sat.push({ ay: ay, hafta: (hi + 1) + '. hafta\n' + tarih, saat: String(h), unite: un.join('\n'), cikti: ci.join('\n'), olcme: ol.join('\n'), bgh: bgh, aciklama: (w.donem === 2 && H[wi - 1] && H[wi - 1].donem === 1 ? '2. dönem başlangıcı\n' : '') + aciklama });
    });
    return { satir: sat, hafta: dersH.length, saat: h };
  }
  function ypFormHtml() {
    var P = ypDurum(S.veri), sin = Object.keys(KAT).map(Number).sort(function (a, b) { return a - b; }), dl = (KAT[P.sinif] || []);
    var kay = { meb: P.cop ? '✅ MEB MTEGM çerçeve öğretim programındaki öğrenme birimleri ve kazanımlardan (haftalara dağıtıldı; tarihler çalışma takvimine göre; zümrede düzenleyebilirsiniz)' : '✅ MEB ' + (P.mebTur ? P.mebTur + ' ' : '') + 'taslak yıllık planından (hafta hafta; tarihler çalışma takvimine göre)', program: '✅ MEB öğretim programından', senaryo: '📋 MEB senaryo tablolarındaki öğrenme çıktılarından', taslak: '🤖 Yapay zekâ taslağı (yönetici kontrolü bekliyor; programla karşılaştırın)', yok: '✍️ Bu ders için kayıtlı program yok: üniteleri ve öğrenme çıktılarını aşağıya yazın', elle: '✍️ Sizin düzenlemeniz' }[P.kaynak] || '';
    var h = '<div class="ev-oys"><h3>1. Ders ve süre</h3><div class="ev-form">' +
      turSecHtml(P) + sinifSecHtml(P, sin) +
      '<label><span>Ders</span><select data-yp="ders"><option value="">' + (P.sinif ? 'Seçin…' : 'Önce sınıf') + '</option>' + dersSecHtml(dl, P.ders, turSec(P)) + (P.sinif ? '<option value="__">Listede yok (kendim yazacağım)</option>' : '') + '</select></label>' +
      (P.dersElle || P.ders === '__' ? '<label><span>Ders adı</span><input data-yp="dersAd" value="' + esc(P.dersAd || '') + '"></label>' : '') +

      '<label><span>Haftalık ders saati</span><input type="number" min="1" max="20" data-yp="saat" value="' + esc(P.saat || '') + '" placeholder="ör. 5"></label>' +
      '<label><span>Okul temelli planlama (toplam saat)</span><input type="number" min="0" max="100" data-yp="otp" value="' + esc(P.otp || '') + '" placeholder="0"></label></div>' +
      '<p class="ev-not">📅 Takvim: ' + esc(TK.yil || '') + ' — ' + esc(String(TK.baslangic || '').split('-').reverse().join('.')) + ' / ' + esc(String(TK.bitis || '').split('-').reverse().join('.')) + '. Tatil haftaları ve resmî tatiller plana otomatik işlenir.</p>';
    if (P.sinif && (P.ders || P.dersAd)) {
      h += '<h3>2. Ünite / tema ve öğrenme çıktıları</h3>' + (P.yukleniyor ? '<p class="ev-not">Program yükleniyor…</p>' : '<p class="ev-yp-kay">' + kay + (P.kaynak === 'senaryo' && P.donemler && P.donemler.length < 2 ? ' — <b>yalnızca ' + P.donemler.join(', ') + '. dönem çıktıları var</b>; diğer dönemin ünitelerini ekleyebilirsiniz.' : '') + '</p>') +
        '<textarea id="yp-u" rows="12" class="ev-yp-u" placeholder="## Ünite / tema adı | ders saati&#10;KOD | öğrenme çıktısı&#10;öğrenme çıktısı">' + esc(P.metin != null ? P.metin : ypYazi(P.uniteler)) + '</textarea>' +
        '<p class="ev-not"><code>## Ünite adı | saat</code> yeni ünite başlatır (saat yazmazsanız süre çıktı sayısına göre dağıtılır); altındaki her satır bir öğrenme çıktısıdır.</p>' +
        '<div class="ev-oy-alt"><button type="button" class="ev-btn hafif" id="yp-yenile">↺ Programı yeniden yükle</button>' + (C.yz && (P.kaynak === 'yok') ? '<button type="button" class="ev-btn" id="yp-yz">🤖 Yapay zekâ ile taslak oluştur</button>' : (!C.uye && C.yzUye && P.kaynak === 'yok' ? '<span class="ev-not" style="margin:0">Üye olursanız yapay zekâya taslak yazdırabilirsiniz.</span>' : '')) + '</div>';
    }
    return h + '</div>';
  }
  function ypYukle(P) {
    var ders = P.ders === '__' ? P.dersAd : P.ders; if (!P.sinif || !ders) return;
    P.yukleniyor = 1; P.metin = null;
    var fd = new FormData(); fd.append('action', 'osc_yp_program'); fd.append('sinif', P.sinif); fd.append('ders', ders); var tr0 = turSec(P); fd.append('tur', /Lise|İmam Hatip Ortaokulu/.test(tr0) ? tr0 : '');
    fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { var d = j.data || {}; P.kaynak = d.kaynak || 'yok'; P.cop = !!d.cop; P.uniteler = d.uniteler || []; P.donemler = d.donemler || null; P.haftalar = d.haftalar || null; P.mebTur = d.mebTur || ''; if (d.saat && !P.saatElle) P.saat = d.saat; }).catch(function () { P.kaynak = 'yok'; }).then(function () { P.yukleniyor = 0; sakla(); vForm(); });
  }
  function ypBagla() {
    var P = ypDurum(S.veri);
    kok.querySelectorAll('[data-yp]').forEach(function (e) {
      e.onchange = function () { var k = e.dataset.yp; P[k] = /sinif|saat|otp/.test(k) ? (+e.value || '') : e.value; if (k === 'saat') P.saatElle = 1; if (k === 'tur') { ORT.okulturu = e.value; if (typeof ortakKaydet === 'function') ortakKaydet('okulturu', e.value); var ar = turAralik(e.value); if (!ar || +P.sinif < ar[1] || +P.sinif > ar[2]) { P.sinif = ''; } P.ders = ''; P.uniteler = []; P.kaynak = ''; P.haftalar = null; } if (k === 'sinif') { P.ders = ''; P.uniteler = []; P.kaynak = ''; P.haftalar = null; } sakla(); if (k === 'sinif' || (k === 'ders' && P.ders !== '__') || k === 'dersAd' || (k === 'tur' && P.ders && P.sinif)) { ypYukle(P); } vForm(); };
    });
    var t = document.getElementById('yp-u'); if (t) t.oninput = function () { P.metin = t.value; P.uniteler = ypCoz(t.value); P.haftalar = null; if (P.kaynak !== 'elle') { P.kaynak0 = P.kaynak0 || P.kaynak; } sakla(); };
    var y = document.getElementById('yp-yenile'); if (y) y.onclick = function () { ypYukle(P); vForm(); };
    var z = document.getElementById('yp-yz'); if (z) z.onclick = function () {
      z.disabled = true; z.innerHTML = '<span class="ev-don"></span> Yazılıyor…';
      var fd = new FormData(); fd.append('action', 'osc_yp_yz'); fd.append('nonce', C.nonce); fd.append('sinif', P.sinif); fd.append('ders', P.ders === '__' ? P.dersAd : P.ders);
      fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (!j.success) throw new Error(j.data || 'Hata'); P.kaynak = j.data.kaynak; P.uniteler = j.data.uniteler; P.metin = null; sakla(); vForm(); }).catch(function (e) { bildir(e.message, 'hata'); vForm(); });
    };
  }
  /* ---------- GÜNLÜK PLAN ---------- */
  function gpFormHtml() {
    var P = ypDurum(S.veri), sin = Object.keys(KAT).map(Number).sort(function (a, b) { return a - b; }), dl = (KAT[P.sinif] || []), U = P.uniteler || [];
    var h = '<div class="ev-oys"><h3>1. Ders ve öğrenme çıktıları</h3><div class="ev-form">' +
      turSecHtml(P) + sinifSecHtml(P, sin) +
      '<label><span>Ders</span><select data-yp="ders"><option value="">' + (P.sinif ? 'Seçin…' : 'Önce sınıf') + '</option>' + dersSecHtml(dl, P.ders, turSec(P)) + (P.sinif ? '<option value="__">Listede yok (kendim yazacağım)</option>' : '') + '</select></label>' +
      (P.ders === '__' ? '<label><span>Ders adı</span><input data-yp="dersAd" value="' + esc(P.dersAd || '') + '"></label>' : '') +

      (U.length ? '<label><span>Ünite / tema</span><select data-gp="unite">' + U.map(function (u, i) { return '<option value="' + i + '"' + (+P.gu === i ? ' selected' : '') + '>' + esc(u.ad) + '</option>'; }).join('') + '</select></label>' : '') + '</div>';
    if (P.yukleniyor) h += '<p class="ev-not">Program yükleniyor…</p>';
    else if (U.length) { var u = U[+P.gu || 0] || U[0], sec = P.gc || []; h += '<div class="ev-gp-c">' + (u.ciktilar || []).map(function (c, j) { return '<label><input type="checkbox" data-gc="' + j + '"' + (sec.indexOf(j) >= 0 ? ' checked' : '') + '> <b>' + esc(c.kod || '') + '</b> ' + esc(c.ad) + '</label>'; }).join('') + '</div><p class="ev-not">Bu derste işleyeceğiniz öğrenme çıktılarını işaretleyin.</p>'; }
    else if (P.sinif && (P.ders || P.dersAd)) h += '<label class="ev-gp-el"><span>Bu ders için kayıtlı program yok; öğrenme çıktılarını yazın (her satıra bir tane)</span><textarea data-gp="elle" rows="4">' + esc(P.gElle || '') + '</textarea></label>';
    return h + '</div>';
  }
  function gpBagla() {
    ypBagla(); var P = ypDurum(S.veri);
    var u = kok.querySelector('[data-gp=unite]'); if (u) u.onchange = function () { P.gu = +u.value; P.gc = []; sakla(); vForm(); };
    kok.querySelectorAll('[data-gc]').forEach(function (c) { c.onchange = function () { P.gc = [].map.call(kok.querySelectorAll('[data-gc]:checked'), function (x) { return +x.dataset.gc; }); sakla(); }; });
    var e = kok.querySelector('[data-gp=elle]'); if (e) e.oninput = function () { P.gElle = e.value; sakla(); };
  }
  function gpSecili(v) {
    var P = ypDurum(v), U = P.uniteler || [], u = U[+P.gu || 0];
    if (u) { var l = (P.gc && P.gc.length ? P.gc : [0]).map(function (j) { return u.ciktilar[j]; }).filter(Boolean); return { unite: u.ad, ciktilar: l }; }
    return { unite: '', ciktilar: satirlar(P.gElle).map(function (x) { return { kod: '', ad: x }; }) };
  }
  function gunlukIcerik(v) {
    var g = gpSecili(v), c = g.ciktilar.map(function (x) { return x.ad; }), ana = (c[0] || 'konu').replace(/\s*(yapabilme|edebilme|abilme|ebilme)$/i, ''), konu = v.konu || g.unite || 'Konu';
    return {
      giris: ['Dikkat çekme: “' + konu + '” ile ilgili günlük hayattan bir durum, görsel ya da kısa bir soruyla derse başlanır.', 'Öğrencilerin ön bilgileri soru-cevapla yoklanır; dersin amacı ve öğrenme çıktıları paylaşılır.'],
      kesfetme: ['Öğrenciler küçük gruplarda “' + (c[0] || konu) + '” öğrenme çıktısına yönelik hazırlanan etkinliği inceler; gözlem ve tahminlerini not alır.', 'Gruplar bulgularını sınıfla paylaşır; öğretmen rehberliğinde kavramlar ve ilişkiler belirginleştirilir.', 'Öğretmen kavram yanılgılarını giderecek örnekler sunar; doğru ve eksik düşünceler birlikte tartışılır.'],
      uygulama: ['Öğrenciler bireysel olarak çalışma kâğıdındaki ' + (c.length > 1 ? c.length + ' öğrenme çıktısına' : 'öğrenme çıktısına') + ' yönelik problemleri/görevleri çözer.', 'Çözümler eşli değerlendirilir; farklı çözüm yolları karşılaştırılır.'],
      kapanis: ['Dersin özeti öğrencilerle birlikte çıkarılır (“Bugün ne öğrendim?”).', 'Çıkış kartıyla öğrenciler öğrendiklerini ve merak ettiklerini kısaca yazar.'],
      zenginlestirme: 'Öğrenme çıktısına ulaşan öğrencilere konuyu farklı bir bağlamla ilişkilendiren üst düzey düşünme sorusu ya da küçük bir araştırma görevi verilir.',
      destekleme: 'Güçlük çeken öğrencilerle somut materyal ve basamaklandırılmış örneklerle çalışılır; ek alıştırmalar ve akran desteği sağlanır.',
      olcme: ['Gözlem ve soru-cevapla süreç değerlendirmesi', 'Çıkış kartı', 'Çalışma kâğıdı / kısa yazılı değerlendirme'],
      bilesenler: 'Sosyal-duygusal öğrenme becerileri: iş birliği, iletişim, sorumluluk. Değerler: saygı, sabır, çalışkanlık. Okuryazarlık becerileri: bilgi okuryazarlığı, ' + (/Mat|Fen|Fizik|Kimya|Biyo/i.test(v.yp && v.yp.ders || '') ? 'sayısal okuryazarlık' : 'dil ve görsel okuryazarlık') + '.',
      aile: 'Öğrencilerden konuyu günlük hayatta gözlemleyip aileleriyle paylaşmaları ve kısa bir not hazırlamaları istenir.'
    };
  }
  function oyFormHtml() {
    var Q = oyDurum(S.veri), sinif = SNR.map(function (t) { return +t.sinif; }).filter(function (x, i, a) { return a.indexOf(x) === i; }).sort(function (a, b) { return a - b; });
    var dersler = SNR.filter(function (t) { return +t.sinif === +Q.sinif; }).map(function (t) { return t.ders; }).filter(function (x, i, a) { return a.indexOf(x) === i; }).sort(function (a, b) { return a.localeCompare(b, 'tr'); });
    var donemler = SNR.filter(function (t) { return +t.sinif === +Q.sinif && t.ders === Q.ders; }).map(function (t) { return +t.donem || 1; }).sort();
    var t = oyTablo(Q), sen = t && t.senaryolar ? (t.senaryolar[Q.yazili] || []) : [];
    var opt = function (l, v, bos) { return '<option value="">' + bos + '</option>' + l.map(function (x) { x = Array.isArray(x) ? x : [x, x]; return '<option value="' + esc(x[0]) + '"' + (String(v) === String(x[0]) ? ' selected' : '') + '>' + esc(x[1]) + '</option>'; }).join(''); };
    var cikt = t ? (t.satirlar || []).map(function (r) { return r.kod + '|' + ciktiTemiz(r.cikti); }) : [];
    var n = Q.sorular.length, H = oyHesap(Q);
    var h = '<div class="ev-oys"><h3>1. Senaryo ve soru planı</h3><div class="ev-form">' +
      '<label><span>Sınıf</span><select data-oy="sinif">' + opt(sinif.map(function (x) { return [x, x + '. sınıf']; }), Q.sinif, 'Seçin…') + '</select></label>' +
      '<label><span>Ders</span><select data-oy="ders">' + opt(dersler, Q.ders, Q.sinif ? 'Seçin…' : 'Önce sınıf') + '</select></label>' +
      '<label><span>Dönem</span><select data-oy="donem">' + opt((donemler.length ? donemler : [1, 2]).map(function (x) { return [x, x + '. dönem']; }), Q.donem, 'Seçin…') + '</select></label>' +
      '<label><span>Yazılı</span><select data-oy="yazili">' + opt([[1, '1. ortak yazılı'], [2, '2. ortak yazılı']], Q.yazili, 'Seçin…') + '</select></label>' +
      '<label><span>MEB senaryosu</span><select data-oy="senaryo">' + opt(sen.map(function (x) { return [x, x + '. senaryo']; }), Q.senaryo, t ? (sen.length ? 'Senaryo seçin…' : 'Bu yazılı için senaryo yok') : (Q.ders && Q.sinif ? 'Bu dönem için senaryo henüz yok' : 'Önce ders seçin')) + '</select></label></div>';
    if (n || Q.senaryo) {
      h += '<div class="ev-oy-plan"><table><tr><th>Soru</th><th>Öğrenme çıktısı</th><th>Puan</th><th></th></tr>' + Q.sorular.map(function (q, i) {
        var deger = q.kod + '|' + q.cikti, l = cikt.slice(); if (q.cikti && l.indexOf(deger) < 0) l.unshift(deger);
        return '<tr><td>' + (i + 1) + '</td><td><select data-q="' + i + '" data-qk="cikti">' + '<option value="">— Çıktı seçin —</option>' + l.map(function (x) { var p = x.split('|'); return '<option value="' + esc(x) + '"' + (x === deger ? ' selected' : '') + '>' + esc((p[0] ? p[0] + ' – ' : '') + p.slice(1).join('|')) + '</option>'; }).join('') + '</select></td><td><input type="number" min="0" max="100" step="1" data-q="' + i + '" data-qk="puan" value="' + esc(q.puan) + '"></td><td><button type="button" class="ev-x" data-qsil="' + i + '" title="Soruyu sil">✕</button></td></tr>';
      }).join('') + '</table><div class="ev-oy-alt"><button type="button" class="ev-btn hafif" id="oy-qekle">＋ Soru ekle</button><button type="button" class="ev-btn hafif" id="oy-dagit">Puanları 100’e eşit dağıt</button><button type="button" class="ev-btn hafif" id="oy-sifirla">Senaryoya geri dön</button><span class="ev-oy-top' + (H.toplamPuan !== 100 ? ' uyari' : '') + '">Toplam: <b id="oy-tp">' + H.toplamPuan + '</b> puan</span></div></div>';
    }
    h += '<h3>2. Öğrenciler ve soru puanları</h3><details class="ev-oy-yap"' + (Q.ogr.length ? '' : ' open') + '><summary>📋 e-Okul’dan öğrenci listesini yapıştır</summary><p class="ev-not">e-Okul’da sınıf listesini (ya da Excel listesini) seçip kopyalayın ve aşağıya yapıştırın. Okul numarası ve ad soyad otomatik ayrılır.</p><textarea id="oy-yapistir" rows="5" placeholder="1&#9;123&#9;ALİ&#9;YILMAZ&#9;Erkek&#10;2&#9;145&#9;ECE&#9;KAYA&#9;Kız"></textarea><button type="button" class="ev-btn" id="oy-aktar">Listeye ekle</button></details>';
    h += '<div class="ev-oy-izgara"><table><tr><th>No</th><th>Adı soyadı</th>' + Q.sorular.map(function (q, i) { return '<th title="' + esc(q.cikti || '') + '">' + (i + 1) + '<small>' + esc(q.puan || '') + '</small></th>'; }).join('') + '<th>Toplam</th><th></th></tr>' +
      Q.ogr.map(function (o, k) { return '<tr><td><input data-o="' + k + '" data-ok="no" value="' + esc(o.no) + '" class="no"></td><td><input data-o="' + k + '" data-ok="ad" value="' + esc(o.ad) + '" class="ad" placeholder="Ad Soyad"></td>' + Q.sorular.map(function (q, j) { var x = (o.p || [])[j]; return '<td><input type="number" min="0" max="' + esc(q.puan || 100) + '" data-o="' + k + '" data-p="' + j + '" value="' + (x == null ? '' : esc(x)) + '" class="pn"></td>'; }).join('') + '<td class="tp" id="oy-t' + k + '">' + (H.top[k] == null ? '' : H.top[k]) + '</td><td><button type="button" class="ev-x" data-osil="' + k + '" title="Öğrenciyi sil">✕</button></td></tr>'; }).join('') +
      '</table></div><div class="ev-oy-alt"><button type="button" class="ev-btn hafif" id="oy-oekle">＋ Öğrenci ekle</button>' + (Q.ogr.length ? '<button type="button" class="ev-btn hafif" id="oy-otemiz">Listeyi temizle</button>' : '') + '<span class="ev-not" style="margin:0">Puan kutularında Enter ile sonraki kutuya geçebilirsiniz. Puan girmezseniz elle doldurulacak boş çizelge çıkar.</span></div></div>';
    return h;
  }
  function oyBagla() {
    var Q = oyDurum(S.veri), yeni = function () { sakla(); vForm(); };
    kok.querySelectorAll('[data-oy]').forEach(function (e) { e.onchange = function () {
      var k = e.dataset.oy; Q[k] = /sinif|donem|yazili|senaryo/.test(k) ? (+e.value || '') : e.value;
      if (k === 'sinif') { Q.ders = ''; Q.senaryo = ''; } if (k === 'ders' || k === 'donem' || k === 'yazili') Q.senaryo = '';
      if (k === 'ders' && Q.ders) { var dn = SNR.filter(function (t) { return +t.sinif === +Q.sinif && t.ders === Q.ders; }).map(function (t) { return +t.donem || 1; }); if (dn.indexOf(+Q.donem) < 0) Q.donem = dn.sort().pop() || 1; }
      if (k !== 'sinif' && Q.senaryo) { Q.sorular = oyPlan(Q); Q.duzen = 0; } else if (!Q.senaryo) Q.sorular = Q.sorular.length && k === 'senaryo' ? Q.sorular : [];
      yeni(); }; });
    kok.querySelectorAll('[data-q]').forEach(function (e) {
      var q = Q.sorular[+e.dataset.q];
      e.oninput = function () { if (e.dataset.qk !== 'puan') return; q.puan = Math.max(0, +e.value || 0); Q.duzen = 1; sakla(); var tp = oyHesap(Q).toplamPuan, el = document.getElementById('oy-tp'); el.textContent = tp; el.parentNode.classList.toggle('uyari', tp !== 100); };
      e.onchange = function () { if (e.dataset.qk !== 'puan') { var p = e.value.split('|'); q.kod = p[0]; q.cikti = p.slice(1).join('|'); } Q.duzen = 1; yeni(); };
    });
    kok.querySelectorAll('[data-qsil]').forEach(function (b) { b.onclick = function () { var i = +b.dataset.qsil; Q.sorular.splice(i, 1); Q.ogr.forEach(function (o) { (o.p || []).splice(i, 1); }); Q.duzen = 1; yeni(); }; });
    var x = function (id, f) { var e = document.getElementById(id); if (e) e.onclick = f; };
    x('oy-qekle', function () { Q.sorular.push({ kod: '', cikti: '', puan: 0 }); Q.duzen = 1; yeni(); });
    x('oy-dagit', function () { oyDagit(Q.sorular); yeni(); });
    x('oy-sifirla', function () { Q.sorular = oyPlan(Q); Q.duzen = 0; yeni(); });
    x('oy-oekle', function () { Q.ogr.push({ no: '', ad: '', p: [] }); yeni(); setTimeout(function () { var l = kok.querySelectorAll('input.ad'); if (l.length) l[l.length - 1].focus(); }, 30); });
    x('oy-otemiz', function () { if (confirm('Öğrenci listesi ve girilen puanlar silinsin mi?')) { Q.ogr = []; yeni(); } });
    x('oy-aktar', function () { var l = eokulAyristir(document.getElementById('oy-yapistir').value); if (!l.length) return bildir('Listede öğrenci bulunamadı. Satırları e-Okul’dan kopyaladığınızdan emin olun.', 'hata'); var var_ = {}; Q.ogr.forEach(function (o) { var_[o.no + '|' + o.ad] = 1; }); l.forEach(function (o) { if (!var_[o.no + '|' + o.ad]) Q.ogr.push(o); }); bildir(l.length + ' öğrenci eklendi.', 'ok'); yeni(); });
    kok.querySelectorAll('[data-osil]').forEach(function (b) { b.onclick = function () { Q.ogr.splice(+b.dataset.osil, 1); yeni(); }; });
    kok.querySelectorAll('[data-o]').forEach(function (e) {
      e.oninput = function () {
        var o = Q.ogr[+e.dataset.o];
        if (e.dataset.p != null) { var j = +e.dataset.p, mx = +(Q.sorular[j] || {}).puan || 100; o.p = o.p || []; if (e.value === '') o.p[j] = null; else { var v = Math.max(0, +e.value); if (v > mx) { v = mx; e.value = mx; bildir((j + 1) + '. sorunun puanı en fazla ' + mx + ' olabilir.', 'hata'); } o.p[j] = v; } var tt = oyHesap(Q).top[+e.dataset.o]; document.getElementById('oy-t' + e.dataset.o).textContent = tt == null ? '' : tt; }
        else o[e.dataset.ok] = e.value;
        sakla();
      };
      e.onkeydown = function (ev) { if (ev.key !== 'Enter') return; ev.preventDefault(); var l = [].slice.call(kok.querySelectorAll('.ev-oy-izgara input')), i = l.indexOf(e); if (l[i + 1]) { l[i + 1].focus(); l[i + 1].select && l[i + 1].select(); } };
    });
  }
  function kutlamaIcerik(v) {
    var g = v.gun || 'Belirli gün', tr = bghTur(g), an = tr === 'a', by = tr === 'b', a = [];
    var siir = satirlar(v.siirler), gos = satirlar(v.gosteriler);
    if (g.indexOf('10 Kasım') === 0) a.push('Saat 09.05’te Gazi Mustafa Kemal Atatürk’ün aziz hatırası için saygı duruşu ve ardından İstiklâl Marşı’nın okunması');
    else a.push((an ? 'Saygı duruşu ve İstiklâl Marşı’nın okunması' : 'Saygı duruşu ve İstiklâl Marşı’nın okunması'));
    a.push('Sunucunun açılış konuşması ve programın takdimi' + (v.sunucu ? ' (' + v.sunucu + ')' : ''));
    a.push('Günün anlam ve önemini belirten konuşma' + (v.konusmaci ? ' (' + v.konusmaci + ')' : ''));
    if (g.indexOf('10 Kasım') === 0) a.push('Atatürk’ün hayatı ve eserlerinden kesitler (öğrenci sunumu)');
    if (siir.length) siir.forEach(function (x) { a.push('Şiir: ' + x); }); else a.push('Şiirlerin okunması (görevli öğrenciler)');
    if (gos.length) gos.forEach(function (x) { a.push(x); }); else a.push(an ? 'Günün anlamına uygun müzik dinletisi / kısa belgesel gösterimi' : by ? 'Koro, halk oyunları ve gösteriler' : 'Etkinlik: ' + bghEtkinlik(g));
    if (by || g.indexOf('10 Kasım') === 0) a.push('Şiir, kompozisyon ve resim yarışmalarında dereceye giren öğrencilerin açıklanması');
    a.push(an ? 'Kapanış ve anma panosunun ziyaret edilmesi' : 'Kapanış konuşması' + (by ? ' ve bayram coşkusuyla programın sona ermesi' : ''));
    var konusma = (bghBul(g) || [])[4] || ('Bugün ' + g + ' vesilesiyle bir aradayız. Bu gün, toplum olarak üzerinde durmamız gereken önemli bir konuya dikkat çekmek amacıyla her yıl okullarımızda anılmaktadır. Hepimizin bu konuda bilinçlenmesi, öğrendiklerimizi ailemiz ve çevremizle paylaşması büyük önem taşımaktadır. Programımızı hazırlayan öğrencilerimize ve öğretmenlerimize teşekkür ederiz.');
    return { akis: a, konusma: konusma };
  }
  function performansIcerik(v) {
    var c = v.cikti || 'öğrenme çıktısı', u = v.urun || 'ürün';
    return { yonerge: ['Bu görevde “' + (v.konu || c) + '” konusunu araştırarak bir ' + u.toLocaleLowerCase('tr') + ' hazırlayacaksınız.', 'Görevin amacı şu öğrenme çıktısına ulaşmanızdır: ' + c + '.', 'Araştırmanızda en az iki farklı kaynak kullanın ve kaynaklarınızı ürününüzün sonunda belirtin.', 'Çalışmanızı ' + (v.teslim || 'belirtilen tarihte') + ' teslim edin; görev bireysel olarak yapılacaktır.', 'Değerlendirme aşağıdaki dereceli puanlama anahtarına göre yapılacaktır; teslimden önce kontrol ediniz.'],
      olcut: [['Konuya ve öğrenme çıktısına uygunluk', 20], ['Bilgilerin doğruluğu ve yeterliği', 20], ['Planlama ve çalışma süreci', 15], ['Özgünlük ve yaratıcılık', 15], ['Düzen, görsel sunum ve dil kullanımı', 15], ['Kaynak kullanımı ve zamanında teslim', 15]] };
  }
  var REH = { 'Eylül': 'Okula ve sınıfa uyum; tanışma etkinlikleri; sınıf kurallarının birlikte belirlenmesi', 'Ekim': 'Etkili ve verimli ders çalışma yöntemleri; zaman yönetimi', 'Kasım': 'Akran ilişkileri ve zorbalığı önleme; duyguları tanıma ve ifade etme', 'Aralık': 'Kişisel güvenlik ve güvenli internet kullanımı; teknoloji bağımlılığı', 'Ocak': 'Dönem değerlendirmesi; hedef belirleme', 'Şubat': 'Mesleki farkındalık: ilgi, yetenek ve değerler', 'Mart': 'Sınav kaygısıyla baş etme; stres yönetimi', 'Nisan': 'Problem çözme ve karar verme becerileri; iletişim becerileri', 'Mayıs': 'Eğitim ve meslek seçenekleri; üst öğrenime hazırlık', 'Haziran': 'Yıl sonu değerlendirmesi; tatil önerileri' };
  var KARNE = { 'çok iyi': ['derslere istekle katılan, sorumluluklarını eksiksiz yerine getiren, başarılı ve örnek davranışlar sergileyen bir öğrencidir. Bu çalışkanlığını sürdürmesini dilerim.', 'meraklı, araştırmacı ve üretken yapısıyla sınıfına örnek olan bir öğrencidir. Başarılarının devamını dilerim.'], 'iyi': ['derslerine ilgili, arkadaşlarıyla uyumlu ve sorumluluk sahibi bir öğrencidir. Düzenli çalışmaya devam ederek başarısını daha da artıracağına inanıyorum.', 'çalışkan ve saygılı bir öğrencidir; derse katılımını artırdığında çok daha başarılı olacaktır.'], 'orta': ['iyi niyetli ve arkadaş canlısı bir öğrencidir. Ders çalışma alışkanlığını düzenli hâle getirdiğinde başarısı belirgin şekilde artacaktır.', 'yapabileceklerinin farkına vardığında çok daha başarılı olabilecek bir öğrencidir; tekrar yapmaya ve soru sormaya özen göstermelidir.'], 'gelişmeli': ['gelişime açık, desteklendiğinde başarılı olabilecek bir öğrencidir. Günlük tekrar yapması ve ödevlerini düzenli tamamlaması önemlidir.', 'öğrenme isteğini artırdığında ve düzenli çalıştığında kısa sürede ilerleme kaydedecektir; ailesinin desteğiyle başarısını yükselteceğine inanıyorum.'] };
  var IYEP_MOD = { 'Türkçe': ['Sesleri tanıma ve harf-ses ilişkisi kurma', 'Hece ve kelime okuma', 'Akıcı ve doğru okuma', 'Okuduğunu anlama ve soru cevaplama', 'Harf, kelime ve cümle yazma', 'Dikte, yazım ve noktalama', 'Söz varlığını geliştirme', 'Kısa metin oluşturma'],
    'Matematik': ['Doğal sayıları okuma-yazma ve basamak değeri', 'Sayıları karşılaştırma ve sıralama', 'Toplama işlemi ve problemleri', 'Çıkarma işlemi ve problemleri', 'Çarpma işlemi ve problemleri', 'Bölme işlemi ve problemleri', 'Kesirler', 'Zaman, uzunluk ve para', 'Geometrik şekiller', 'Problem çözme stratejileri'] };
  function oturmaSira(v) {
    var l = satirlar(v.ogrenciler).map(function (x) { var p = parcala(x); return /^\d+$/.test(p[0]) && p.length > 1 ? { no: p[0], ad: p.slice(1).join(' ') } : { no: '', ad: x }; });
    if (v.duzen === 'Okul numarasına göre') l.sort(function (a, b) { return (+a.no || 0) - (+b.no || 0); });
    else if (v.duzen === 'Alfabetik') l.sort(function (a, b) { return a.ad.localeCompare(b.ad, 'tr'); });
    else if (v.duzen === 'Karışık (rastgele)') for (var i = l.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)), t = l[i]; l[i] = l[j]; l[j] = t; }
    return l.map(function (x) { return (x.no ? x.no + ' ' : '') + x.ad; });
  }
  function yeniIcerik(id, v) {
    var O = v.okul || 'okulumuz';
    if (id === 'iyep') return { amac: 'İlkokullarda Yetiştirme Programı (İYEP) kapsamında, ' + (v.sinif || '3. ve 4. sınıf') + ' öğrencilerinden ön değerlendirme sonucunda ' + (v.ders || 'Türkçe') + ' dersinde temel becerilerde desteğe ihtiyaç duyduğu belirlenen öğrencilerin eksik kazanımlarını tamamlamaları, akranlarıyla birlikte öğrenmeye devam edebilmeleri ve derse yönelik olumlu tutum geliştirmeleri amaçlanmaktadır.',
      etkinlik: (satirlar(v.moduller).length ? satirlar(v.moduller) : IYEP_MOD[v.ders] || IYEP_MOD['Türkçe']).map(function (m) { return { modul: m, etkinlik: v.ders === 'Matematik' ? 'Somut materyalle modelleme, oyunlaştırılmış alıştırma, bireysel çalışma kâğıdı' : 'Sesli okuma, eşli okuma, kelime kartları, yazma çalışması', olcme: 'Modül sonu değerlendirme' }; }),
      esaslar: ['Programa katılan öğrenciler ön değerlendirme (tarama) sonuçlarına göre belirlenmiştir.', 'Çalışmalar öğrencilerin bireysel ihtiyaçlarına göre, somuttan soyuta ve kolaydan zora doğru planlanır.', 'Her modül sonunda öğrenci değerlendirilir; kazanımları edinen öğrenci bir sonraki modüle geçer.', 'Program sonunda son değerlendirme yapılır; öğrencinin gelişimi velisiyle paylaşılır.', 'Velilere, öğrencinin evde desteklenmesi için öneriler verilir.'] };
    if (id === 'yazili_teslim') return { metin: (v.yil || yil()) + ' Eğitim Öğretim yılında okuttuğum şubelere ait ' + (v.kapsam || 'I. ve II. dönem').replace('dönem', 'Dönem') + ' sınav kâğıtları sayısı aşağıdaki tabloda yazılmıştır.' };
    if (id === 'sinav_tutanak') return { metin: (v.sube || '……') + ' şubesinin ' + (v.ders || '……') + ' dersi ' + (v.sinav || '') + ' sınavı ' + (v.tarih || '…/…/20…') + ' tarihinde ' + (v.saat ? v.saat + ' saatinde ' : '') + 'yapılmıştır. Sınıf mevcudu ' + (v.mevcut || '…') + ' olup sınava ' + (v.giren || '…') + ' öğrenci katılmıştır. Sınav, öğrencilere sınav kuralları hatırlatıldıktan sonra başlatılmış ve süresi içinde tamamlanmıştır.',
      durum: String(v.durum || '').trim() || 'Sınav süresince herhangi bir olumsuzluk ya da kural dışı durum yaşanmamıştır.' };
    if (id === 'ders_kesim') return { metin: (v.yil || yil()) + ' Eğitim Öğretim yılında okuttuğum dersler, aşağıda belirtilen sınıflarda konular ' + (v.donem === '1. dönem sonu' ? '1. dönem için ' : '') + 'öğretim programına uygun olarak bitirilmiştir.' };
    if (id === 'oturma') return { sira: oturmaSira(v) };
    if (id === 'kelebek') return kbDagit(v);
    if (id === 'tutanak') {
      var tt = v.ttur || 'genel', yer = '', zaman = (v.tarih || '…/…/20…') + ' tarihinde' + (v.saat ? ' saat ' + v.saat + ' sularında' : '');
      var ol = String(v.olay || '').trim() || '……………………………………………………………………………………………………………………';
      var g = { genel: zaman + ' ' + yer + '“' + (v.konu || '……') + '” konusuyla ilgili olarak aşağıdaki hususlar tespit edilmiştir: ' + ol,
        olay: zaman + ' ' + yer + 'meydana gelen olay hakkında yerinde yapılan inceleme ve görüşmeler sonucunda şu hususlar tespit edilmiştir: ' + ol + ' Durum okul idaresine bildirilmiş ve ilgili öğrencilerin velilerine bilgi verilmiştir.',
        kaza: zaman + ' ' + yer + 'meydana gelen kaza ile ilgili olarak şu hususlar tespit edilmiştir: ' + ol + ' Gerekli ilk yardım uygulanmış, öğrencinin velisine bilgi verilmiş ve durum okul idaresine bildirilmiştir.',
        kayip: zaman + ' ' + yer + 'yapılan tespitte, aşağıda açıklanan kayıp / hasar durumu belirlenmiştir: ' + ol,
        teslim: 'Aşağıda cinsi, adedi ve açıklaması yazılı eşya / belgeler ' + zaman + ' ' + yer + 'eksiksiz ve sağlam olarak teslim edilmiş ve teslim alınmıştır.',
        imtina: zaman + ' ' + yer + 'ilgiliye tebliğ edilmek istenen yazı / belge kendisine okunmuş ve içeriği hakkında bilgi verilmiş; ancak ilgili tebliği almaktan ve imzalamaktan imtina etmiştir. ' + ol };
      return { metin: g[tt] + ' İşbu tutanak, hazır bulunanlar tarafından okunarak birlikte imza altına alınmıştır.' };
    }
    if (id === 'ogrenci_gorusme') return { bos: 1 };
    if (id === 'devamsizlik') { var oz = +v.ozursuz || 0, ol2 = +v.ozurlu || 0;
      return { metin: 'Sayın ' + (v.veli || '……………………') + ',\n\n        Okulumuz ' + (v.sube || '……') + ' sınıfı öğrencisi ' + (v.ogrenci || '……………………') + ' (velisi bulunduğunuz öğrenci), ' + (v.tarih || '…/…/20…') + ' tarihi itibarıyla ' + (v.ozursuz || '…') + ' gün özürsüz' + (ol2 ? ', ' + ol2 + ' gün özürlü olmak üzere toplam ' + (oz + ol2) + ' gün' : '') + ' devamsızlık yapmıştır.\n        Devamsızlık süresinin ilgili yönetmelikte belirtilen sınırları aşması hâlinde öğrencinizin başarı durumu olumsuz etkilenebilecektir. Öğrencinizin okula düzenli devamının sağlanması ve devamsızlık nedenleri hakkında görüşmek üzere okulumuza gelmenizi ya da sınıf rehber öğretmeniyle iletişime geçmenizi rica ederiz.\n        Bilgilerinizi rica ederim.' }; }
    return null;
  }
  function varsayilan(id, v) { var yi = yeniIcerik(id, v); if (yi) return yi; if (id === 'performans') return performansIcerik(v); if (id === 'rehberlik') return { aylar: AYLAR.map(function (a) { return { ay: a, konu: REH[a] }; }), yapilanlar: satirlar(v.yapilanlar).length ? satirlar(v.yapilanlar) : ['Sınıf rehberlik planındaki etkinlikler zamanında uygulanmıştır.', 'Öğrenci tanıma çalışmaları yapılmış, ihtiyaç duyan öğrenciler rehberlik servisine yönlendirilmiştir.', 'Velilerle bireysel ve toplu görüşmeler yapılmıştır.'], degerlendirme: 'Sınıf rehberlik çalışmaları yıllık plana uygun olarak yürütülmüş; öğrencilerin uyum, iletişim ve verimli ders çalışma konularında olumlu gelişim gösterdiği gözlenmiştir.' }; if (id === 'gunluk_plan') return gunlukIcerik(v); if (id === 'belirli_gun' && v.program !== 'yillik') return kutlamaIcerik(v); if (id === 'ogr_kurul') return kurulIcerik(v); if (id === 'egzersiz') return egzersizIcerik(v); if (id === 'gezi') return geziIcerik(v); if (id === 'kulup_aylik') return kulupAylikIcerik(v); if (id === 'dilekce') return dilekceIcerik(v); if (id === 'belirli_gun' || id === 'oys') return { bos: 1 }; return id === 'zumre' ? zumreIcerik(v) : id === 'sok' ? sokIcerik(v) : id === 'veli' ? veliIcerik(v) : id === 'bep' ? bepIcerik(v) : id === 'kulup_plan' ? kulupPlanIcerik(v) : id === 'kulup_rapor' ? kulupRaporIcerik(v) : {}; }

  /* ---------------- içerik → belge blokları ---------------- */
  function bloklar(id, v, ic) {
    var Y = v.yil || yil(), O = trUp(v.okul || '………………………… OKULU'), b = [];
    var TB = function (s) { b.push({ t: 'baslik', s: s }); };
    if (id === 'zumre' || id === 'sok' || id === 'veli') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI'); TB(O);
      TB(id === 'zumre' ? trUp((v.ders || '') + ' DERSİ ' + (TOPLANTI[v.toplanti] || '') + ' ZÜMRE ÖĞRETMENLER KURULU TOPLANTI TUTANAĞI') : id === 'sok' ? trUp((v.sube || '') + ' ŞUBE ÖĞRETMENLER KURULU TOPLANTI TUTANAĞI (' + (TOPLANTI[v.toplanti] || '') + ')') : trUp((v.sube || '') + ' SINIFI VELİ TOPLANTISI TUTANAĞI (' + (TOPLANTI[v.toplanti] || '') + ')'));
      b.push({ t: 'bilgi', satirlar: [['Toplantı tarihi', v.tarih || ''], ['Toplantı saati', v.saat || ''], ['Toplantı yeri', v.yer || '']].concat(dayanak(id, v) ? [['Dayanak', dayanak(id, v)]] : [], id === 'zumre' ? [['Zümre başkanı', v.baskan || ''], ['Sınıf seviyeleri', v.siniflar || '']] : id === 'sok' ? [['Sınıf rehber öğretmeni', v.rehber || ''], ['Öğrenci sayısı', v.mevcut || '']] : [['Sınıf öğretmeni', v.ogretmen || ''], ['Katılan veli sayısı', v.katilim || '']]) });
      b.push({ t: 'h', s: 'GÜNDEM MADDELERİ' }); b.push({ t: 'liste', l: ic.gundem || [], yol: 'gundem' });
      b.push({ t: 'h', s: 'GÜNDEM MADDELERİNİN GÖRÜŞÜLMESİ' });
      (ic.gundem || []).forEach(function (g, i) { b.push({ t: 'p', s: (i + 1) + '. ' + g + ': ' + ((ic.gorusmeler || [])[i] || ''), yol: 'gorusmeler.' + i, onek: (i + 1) + '. ' + g + ': ' }); });
      b.push({ t: 'h', s: 'ALINAN KARARLAR' }); b.push({ t: 'liste', l: ic.kararlar || [], yol: 'kararlar' });
      if (id === 'zumre') { var u = satirlar(v.uyeler); if (v.baskan && u.indexOf(v.baskan) < 0) u.unshift(v.baskan); b.push({ t: 'imza', baslik: 'ZÜMRE ÖĞRETMENLERİ', kisiler: u.map(function (a) { return { ad: a, gorev: (v.ders || '') + ' Öğretmeni' + (a === v.baskan ? ' (Zümre Başkanı)' : '') }; }), sutun: 3 }); }
      if (id === 'sok') b.push({ t: 'imza', baslik: 'ŞUBE ÖĞRETMENLERİ', kisiler: satirlar(v.ogretmenler).map(function (a) { var p = a.split(/\s*[-–]\s*/); return p.length > 1 ? { ad: p.slice(1).join(' - '), gorev: p[0] + ' Öğretmeni' } : { ad: a, gorev: '' }; }), sutun: 3 });
      if (id === 'veli') { b.push({ t: 'imza', kisiler: [{ ad: v.ogretmen, gorev: (v.sube || '') + ' Sınıf Öğretmeni' }], sutun: 1 }); }
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü', tarih: v.tarih });
      if (id === 'veli') { b.push({ t: 'sayfa' }); TB(trUp((v.sube || '') + ' SINIFI VELİ TOPLANTISI KATILIM LİSTESİ')); b.push({ t: 'alt', s: (v.tarih || '') }); var sat = []; for (var i = 1; i <= 30; i++) sat.push([String(i), '', '', '']); b.push({ t: 'tablo', bas: ['No', 'Öğrencinin adı soyadı', 'Velinin adı soyadı', 'İmza'], gen: [6, 34, 34, 26], sat: sat }); }
    } else if (id === 'bep') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI'); TB(O); TB('BİREYSELLEŞTİRİLMİŞ EĞİTİM PROGRAMI (BEP)');
      b.push({ t: 'bilgi', satirlar: [['Öğrencinin adı soyadı', v.ogrenci || ''], ['Sınıfı / şubesi', v.sinif || ''], ['Eğitsel tanı / ihtiyaç', v.tani || ''], ['Ders', v.ders || ''], ['BEP başlama – bitiş tarihi', (v.baslama || '') + ' – ' + (v.bitis || '')], ['Dayanak', dayanak(id, v)]] });
      b.push({ t: 'h', s: 'ÖĞRENCİNİN PERFORMANS DÜZEYİ' }); b.push({ t: 'p', s: ic.performans || '', yol: 'performans' });
      b.push({ t: 'h', s: 'UZUN VE KISA DÖNEMLİ AMAÇLAR' });
      b.push({ t: 'tablo', bas: ['Uzun dönemli amaç', 'Kısa dönemli amaçlar', 'Yöntem ve teknikler', 'Materyaller', 'Değerlendirme', 'Başlama / bitiş'], gen: [18, 28, 16, 14, 12, 12], sat: (ic.hedefler || []).map(function (h) { return [h.uda, (h.kda || []).map(function (x, j) { return (j + 1) + '. ' + x; }).join('\n'), h.yontem || '', h.materyal || '', h.degerlendirme || '', (v.baslama || '') + '\n' + (v.bitis || '')]; }), yol: 'hedefler' });
      b.push({ t: 'h', s: 'ÖLÇME VE DEĞERLENDİRMEDE YAPILACAK UYARLAMALAR' }); b.push({ t: 'liste', l: ic.uyarlamalar || [], yol: 'uyarlamalar' });
      b.push({ t: 'h', s: 'AİLE KATILIMI' }); b.push({ t: 'liste', l: [].concat(ic.aile || []), yol: 'aile' });
      b.push({ t: 'imza', baslik: 'BEP GELİŞTİRME BİRİMİ', kisiler: (satirlar(v.birim).length ? satirlar(v.birim) : ['Okul müdürü / müdür yardımcısı - ', 'Rehber öğretmen - ', 'Sınıf öğretmeni - ', 'Ders öğretmeni - ', 'Veli - ']).map(function (a) { var p = a.split(/\s*[-–]\s*/); return p.length > 1 ? { ad: p.slice(1).join(' - '), gorev: p[0] } : { ad: a, gorev: '' }; }), sutun: 3 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü', tarih: v.baslama });
    } else if (id === 'kulup_plan') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.kulup || 'ÖĞRENCİ KULÜBÜ') + ' YILLIK ÇALIŞMA PLANI'));
      b.push({ t: 'bilgi', satirlar: [['Tema', ic.tema || kulupTema(v.kulup)], ['Okul kademesi', v.kademe || ''], ['Danışman öğretmen(ler)', satirlar(v.danisman).join(', ')], ['Dayanak', dayanak(id, v)]] });
      b.push({ t: 'tablo', bas: ['Ay', 'Planlanan etkinlikler', 'Hedef / kazanım', 'Yöntem', 'Sorumlu'], gen: [8, 46, 22, 14, 10], sat: (ic.aylar || []).map(function (a) { return [a.ay, (a.etkinlikler || []).map(function (x) { return '• ' + x; }).join('\n'), a.hedef || '', a.yontem || '', a.sorumlu || 'Danışman öğretmen ve kulüp üyeleri']; }), yol: 'aylar' });
      if (v.not) b.push({ t: 'p', s: 'Not: ' + v.not });
      b.push({ t: 'imza', kisiler: satirlar(v.danisman).map(function (a) { return { ad: a, gorev: 'Danışman Öğretmen' }; }), sutun: 3 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü' });
    } else if (id === 'kulup_tutanak') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.kulup || 'ÖĞRENCİ KULÜBÜ') + ' İLK TOPLANTI VE GÖREV DAĞILIMI TUTANAĞI'));
      b.push({ t: 'bilgi', satirlar: [['Toplantı tarihi', v.tarih || ''], ['Toplantı yeri', v.yer || ''], ['Tema', kulupTema(v.kulup)], ['Danışman öğretmen(ler)', satirlar(v.danisman).join(', ')]] });
      b.push({ t: 'p', s: (v.tarih || '…/…/20…') + ' tarihinde ' + (v.yer ? v.yer + '’nda ' : '') + (v.kulup || 'kulüp') + ' üyesi öğrenciler danışman öğretmen gözetiminde toplanmıştır. Toplantıda kulübün amacı, yeni kulüp yapısı ve sosyal etkinlik temaları tanıtılmış; kulüp çalışmalarının okul temelli sosyal etkinlikler eylem planıyla uyumlu yürütüleceği açıklanmıştır. Görev dağılımı için aday olan öğrenciler arasından açık oylama yapılmış ve aşağıdaki öğrenciler seçilmiştir. Yıllık çalışma planının üyelerin önerileri alınarak hazırlanmasına karar verilmiştir.' });
      b.push({ t: 'tablo', bas: ['Görev', 'Adı soyadı - sınıfı'], gen: [30, 70], sat: [['Kulüp başkanı', v.baskan || ''], ['Başkan yardımcısı', v.baskanyrd || ''], ['Sekreter', v.sekreter || '']] });
      b.push({ t: 'h', s: 'KULÜP ÜYE LİSTESİ' });
      var u = satirlar(v.uyeler); if (!u.length) for (var j = 0; j < 15; j++) u.push('');
      b.push({ t: 'tablo', bas: ['No', 'Adı soyadı', 'Sınıfı', 'Okul no', 'İmza'], gen: [6, 40, 14, 14, 26], sat: u.map(function (a, i) { var p = a.split(/\s*[-–]\s*/); return [String(i + 1), p[0] || '', p[1] || '', p[2] || '', '']; }) });
      b.push({ t: 'imza', kisiler: satirlar(v.danisman).map(function (a) { return { ad: a, gorev: 'Danışman Öğretmen' }; }).concat([{ ad: (v.baskan || '').split(/\s*[-–]\s*/)[0], gorev: 'Kulüp Başkanı' }]), sutun: 3 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü', tarih: v.tarih });
    } else if (id === 'kulup_rapor') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.kulup || 'ÖĞRENCİ KULÜBÜ') + ' ' + (v.donem || '') + ' ÇALIŞMA RAPORU'));
      b.push({ t: 'bilgi', satirlar: [['Tema', kulupTema(v.kulup)], ['Danışman öğretmen(ler)', satirlar(v.danisman).join(', ')], ['Üye sayısı', v.uye || '']] });
      b.push({ t: 'h', s: 'YAPILAN ÇALIŞMALAR' }); b.push({ t: 'liste', l: ic.yapilanlar || [], yol: 'yapilanlar' });
      b.push({ t: 'h', s: 'GENEL DEĞERLENDİRME' }); b.push({ t: 'p', s: ic.degerlendirme || '', yol: 'degerlendirme' });
      b.push({ t: 'h', s: 'ÖNERİLER' }); b.push({ t: 'liste', l: ic.oneriler || [], yol: 'oneriler' });
      b.push({ t: 'imza', kisiler: satirlar(v.danisman).map(function (a) { return { ad: a, gorev: 'Danışman Öğretmen' }; }), sutun: 3 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü' });
    } else if (id === 'kulup_aylik') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.kulup || 'ÖĞRENCİ KULÜBÜ') + ' ' + (v.ay || '') + ' AYI ÇALIŞMA RAPORU'));
      b.push({ t: 'bilgi', satirlar: [['Tema', kulupTema(v.kulup)], ['Danışman öğretmen(ler)', satirlar(v.danisman).join(', ')], ['Katılan öğrenci sayısı', v.uye || ''], ['Dayanak', dayanak(id, v)]] });
      b.push({ t: 'h', s: 'YAPILAN ÇALIŞMALAR' });
      b.push({ t: 'tablo', bas: ['No', 'Tarih', 'Yapılan etkinlik', 'Sonuç / kazanım'], gen: [6, 16, 52, 26], sat: (ic.yapilanlar || []).map(function (x, i) { var p = String(x).split(/\s+[-–]\s+/); return [String(i + 1), p.length > 1 ? p[0] : '', p.length > 1 ? p.slice(1).join(' - ') : x, 'Etkinlik amacına ulaşmıştır.']; }) });
      b.push({ t: 'h', s: 'DEĞERLENDİRME' }); b.push({ t: 'p', s: ic.degerlendirme || '', yol: 'degerlendirme' });
      b.push({ t: 'imza', kisiler: satirlar(v.danisman).map(function (a) { return { ad: a, gorev: 'Danışman Öğretmen' }; }), sutun: 3 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü' });
    } else if (id === 'ogr_kurul') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI'); TB(O); TB(trUp(({ donem_basi: 'Ders yılı başı', ikinci_donem: 'İkinci dönem başı', yil_sonu: 'Ders yılı sonu', donem_ara: 'Olağanüstü' })[v.toplanti] || '') + ' ÖĞRETMENLER KURULU TOPLANTI TUTANAĞI');
      b.push({ t: 'bilgi', satirlar: [['Toplantı tarihi', v.tarih || ''], ['Toplantı saati', v.saat || ''], ['Toplantı yeri', v.yer || ''], ['Kurul başkanı', v.mudur || ''], ['Yazman', v.yazman || ''], ['Dayanak', dayanak(id, v)]] });
      b.push({ t: 'h', s: 'GÜNDEM MADDELERİ' }); b.push({ t: 'liste', l: ic.gundem || [], yol: 'gundem' });
      b.push({ t: 'h', s: 'GÜNDEM MADDELERİNİN GÖRÜŞÜLMESİ' });
      (ic.gundem || []).forEach(function (g, i) { b.push({ t: 'p', s: (i + 1) + '. ' + g + ': ' + ((ic.gorusmeler || [])[i] || ''), yol: 'gorusmeler.' + i, onek: (i + 1) + '. ' + g + ': ' }); });
      b.push({ t: 'h', s: 'ALINAN KARARLAR' }); b.push({ t: 'liste', l: ic.kararlar || [], yol: 'kararlar' });
      b.push({ t: 'imza', kisiler: [{ ad: v.yazman, gorev: 'Yazman' }, { ad: v.mudur, gorev: 'Okul Müdürü (Kurul Başkanı)' }], sutun: 2 });
      b.push({ t: 'sayfa' }); TB('ÖĞRETMENLER KURULU KATILIM LİSTESİ'); b.push({ t: 'alt', s: v.tarih || '' });
      var ku = satirlar(v.uyeler); if (!ku.length) for (var q = 0; q < 25; q++) ku.push('');
      b.push({ t: 'tablo', bas: ['No', 'Adı soyadı', 'Görevi / branşı', 'İmza'], gen: [6, 36, 32, 26], sat: ku.map(function (a, i) { var p = a.split(/\s*[-–]\s*/); return [String(i + 1), p.length > 1 ? p.slice(1).join(' - ') : a, p.length > 1 ? p[0] : '', '']; }) });
    } else if (id === 'egzersiz') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.egz || '') + ' DERS DIŞI EGZERSİZ ÇALIŞMA PLANI'));
      b.push({ t: 'bilgi', satirlar: [['Egzersiz alanı', v.alan || ''], ['Egzersiz öğretmeni', v.ogretmen || ''], ['Öğrenci grubu', v.grup || ''], ['Çalışma gün ve saatleri', v.gunsaat || ''], ['Çalışma yeri', v.yer || ''], ['Haftalık süre', v.sure ? v.sure + ' ders saati' : '']] });
      b.push({ t: 'tablo', bas: ['Ay', 'Çalışma konuları', 'Hedef', 'Yöntem', 'Süre'], gen: [8, 50, 18, 16, 8], sat: (ic.aylar || []).map(function (a) { return [a.ay, (a.etkinlikler || []).join('\n'), a.hedef || '', a.yontem || '', v.sure ? v.sure * 4 + ' saat' : '']; }), yol: 'aylar' });
      var eo = satirlar(v.ogrenciler); if (eo.length) { b.push({ t: 'h', s: 'EGZERSİZE KATILAN ÖĞRENCİLER' }); b.push({ t: 'tablo', bas: ['No', 'Adı soyadı', 'Sınıfı', 'Okul no'], gen: [8, 52, 20, 20], sat: eo.map(function (a, i) { var p = a.split(/\s*[-–]\s*/); return [String(i + 1), p[0] || '', p[1] || '', p[2] || '']; }) }); }
      b.push({ t: 'imza', kisiler: [{ ad: v.ogretmen, gorev: 'Egzersiz Öğretmeni' }], sutun: 1 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü' });
    } else if (id === 'belirli_gun' && v.program !== 'yillik') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI'); TB(O); TB(trUp(v.gun || '') + (/Anma/.test(v.gun || '') ? ' PROGRAMI' : bghTur(v.gun) === 'a' ? ' ANMA PROGRAMI' : bghTur(v.gun) === 'b' ? ' KUTLAMA PROGRAMI' : ' ETKİNLİK PROGRAMI'));
      b.push({ t: 'bilgi', satirlar: [['Tarih', v.tarih || ''], ['Saat', v.saat || ''], ['Yer', v.yer || ''], ['Sunucu(lar)', v.sunucu || ''], ['Sorumlu öğretmenler', satirlar(v.ogretmenler).join(', ')]] });
      b.push({ t: 'h', s: 'PROGRAM AKIŞI' }); b.push({ t: 'liste', l: ic.akis || [], yol: 'akis' });
      b.push({ t: 'h', s: 'GÜNÜN ANLAM VE ÖNEMİ (KONUŞMA METNİ)' }); b.push({ t: 'p', s: ic.konusma || '', yol: 'konusma' });
      b.push({ t: 'imza', baslik: 'PROGRAMDAN SORUMLU ÖĞRETMENLER', kisiler: satirlar(v.ogretmenler).map(function (a) { return { ad: a, gorev: 'Öğretmen' }; }), sutun: 3 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü', tarih: v.tarih });
    } else if (id === 'belirli_gun') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.kapsam === 'Okul geneli' ? '' : (v.sube || '') + ' ') + 'BELİRLİ GÜN VE HAFTALAR PROGRAMI'));
      var sec = Array.isArray(v.secim) ? v.secim : BGH.map(function (x) { return x[1]; });
      b.push({ t: 'tablo', bas: ['No', 'Tarih', 'Belirli gün / hafta', 'Yapılacak etkinlikler', 'Sorumlu', 'Yapıldı'], gen: [5, 17, 25, 33, 12, 8], sat: BGH.filter(function (x) { return sec.indexOf(x[1]) >= 0; }).map(function (x, i) { return [String(i + 1), x[0], x[1], x[3] || bghEtkinlik(x[1]), v.sorumlu || '', '']; }) });
      b.push({ t: 'p', s: 'Not: Tarihler Bakanlıkça yayımlanan güncel belirli gün ve haftalar listesine ve okulun çalışma takvimine göre kontrol edilmelidir.' });
      b.push({ t: 'imza', kisiler: [{ ad: v.sorumlu, gorev: 'Sorumlu Öğretmen' }], sutun: 1 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü' });
    } else if (id === 'gezi') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB('GEZİ PLANI'); b.push({ t: 'alt', s: 'Ek-9' });
      b.push({ t: 'bilgi', satirlar: [['Gezinin adı / gezilecek yer', v.gezi || ''], ['Gezinin türü', v.tur || ''], ['Gezi tarihi', v.tarih || ''], ['Hareket yeri ve saati', v.hareket || ''], ['Dönüş saati', v.donus || ''], ['Ulaşım aracı / plaka', v.ulasim || ''], ['Katılan sınıflar', v.siniflar || ''], ['Öğrenci sayısı', v.sayi || ''], ['Sorumlu öğretmenler', satirlar(v.ogretmenler).join(', ')]] });
      b.push({ t: 'h', s: 'GEZİNİN AMACI' }); b.push({ t: 'p', s: ic.amac || '', yol: 'amac' });
      b.push({ t: 'h', s: 'KAZANIMLAR' }); b.push({ t: 'liste', l: ic.kazanimlar || [], yol: 'kazanimlar' });
      var gp = satirlar(v.program); b.push({ t: 'h', s: 'GEZİ PROGRAMI' }); b.push({ t: 'tablo', bas: ['Saat', 'Yer / etkinlik'], gen: [18, 82], sat: (gp.length ? gp : ['… - Okuldan hareket', '… - Gezi yerine varış ve gezi', '… - Öğle yemeği', '… - Dönüş']).map(function (x) { var p = x.split(/\s*[-–]\s*/); return p.length > 1 ? [p[0], p.slice(1).join(' - ')] : ['', x]; }) });
      b.push({ t: 'h', s: 'ALINACAK GÜVENLİK ÖNLEMLERİ' }); b.push({ t: 'liste', l: ic.guvenlik || [], yol: 'guvenlik' });
      b.push({ t: 'imza', baslik: 'SORUMLU ÖĞRETMENLER', kisiler: satirlar(v.ogretmenler).map(function (a) { return { ad: a, gorev: 'Öğretmen' }; }), sutun: 3 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü' });
      b.push({ t: 'sayfa' }); TB(O + ' MÜDÜRLÜĞÜNE'); b.push({ t: 'bosluk', n: 160 });
      b.push({ t: 'p', s: 'Velisi bulunduğum ……… sınıfı ……… numaralı öğrencim ………………………………’in, okulunuzca ' + (v.tarih || '…/…/20…') + ' tarihinde ' + (v.gezi || '……………') + ' yerine düzenlenecek geziye katılmasına izin veriyorum. Gezi programını ve alınacak önlemleri okudum; öğrencimin gezi süresince öğretmenlerinin uyarılarına uyacağını taahhüt ederim.' });
      b.push({ t: 'p', s: 'Öğrencimin sağlık durumu / kullandığı ilaçlar: ………………………………………………' });
      b.push({ t: 'bosluk', n: 200 }); b.push({ t: 'sag', s: '…/…/20…\nVelinin adı soyadı: ………………………\nİmza: ……………\nTelefon: ………………………' });
      var go = satirlar(v.ogrenciler); if (!go.length) for (var r = 0; r < 25; r++) go.push('');
      b.push({ t: 'sayfa' }); TB('GEZİYE KATILAN ÖĞRENCİ LİSTESİ'); b.push({ t: 'alt', s: (v.gezi || '') + ' – ' + (v.tarih || '') });
      b.push({ t: 'tablo', bas: ['No', 'Adı soyadı', 'Sınıfı', 'Okul no', 'Veli telefonu'], gen: [6, 38, 14, 14, 28], sat: go.map(function (a, i) { var p = a.split(/\s*[-–]\s*/); return [String(i + 1), p[0] || '', p[1] || '', p[2] || '', p[3] || '']; }) });
    } else if (id === 'dilekce') {
      b.push({ t: 'bosluk', n: 600 }); b.push({ t: 'baslik', s: trUp(v.makam || 'OKUL MÜDÜRLÜĞÜNE') }); b.push({ t: 'alt', s: trUp(v.il || '') }); b.push({ t: 'bosluk', n: 300 });
      b.push({ t: 'p', s: '        ' + (ic.metin || ''), yol: 'metin', onek: '        ' }); b.push({ t: 'bosluk', n: 300 });
      b.push({ t: 'sag', s: (v.tarih || '…/…/20…') + '\n\n' + (v.adsoyad || '………………………') + '\n' + (v.gorev || '') + '\nİmza' }); b.push({ t: 'bosluk', n: 600 });
      b.push({ t: 'p', s: 'Adres: ' + (v.adres || '………………………………………') + '\nTelefon: ' + (v.tel || '………………') });
      var ek = satirlar(v.ekler); if (ek.length) { b.push({ t: 'p', s: 'Ekler:' }); b.push({ t: 'liste', l: ek }); }
    } else if (id === 'performans') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.ders || '') + ' DERSİ PERFORMANS GÖREVİ'));
      b.push({ t: 'bilgi', satirlar: [['Ders / sınıf', (v.ders || '') + ' – ' + (v.sinif || '')], ['Görevin konusu', v.konu || ''], ['Öğrenme çıktısı', v.cikti || ''], ['Ürün türü', v.urun || ''], ['Veriliş / teslim tarihi', (v.verilis || '') + ' / ' + (v.teslim || '')], ['Öğrencinin adı soyadı – no', '']] });
      b.push({ t: 'h', s: 'GÖREV YÖNERGESİ' }); b.push({ t: 'liste', l: ic.yonerge || [], yol: 'yonerge' });
      b.push({ t: 'h', s: 'DERECELİ PUANLAMA ANAHTARI' });
      b.push({ t: 'tablo', bas: ['Ölçüt', 'Çok iyi (4)', 'İyi (3)', 'Geliştirilmeli (2)', 'Yetersiz (1)', 'Puan'], gen: [30, 13, 13, 15, 13, 16], sat: (ic.olcut || []).map(function (o) { var p = o[1]; return [o[0] + ' (' + p + ' p)', String(p), String(Math.round(p * .75)), String(Math.round(p * .5)), String(Math.round(p * .25)), '']; }).concat([['TOPLAM', '', '', '', '', '/100']]) });
      b.push({ t: 'p', s: 'Puanlama: Her ölçütte öğrencinin ulaştığı düzeye karşılık gelen puan verilir; toplam puan 100 üzerinden hesaplanır.' });
      b.push({ t: 'imza', kisiler: [{ ad: v.ogretmen, gorev: (v.ders || '') + ' Öğretmeni' }], sutun: 1 });
    } else if (id === 'dyk') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.ders || '') + ' DESTEKLEME VE YETİŞTİRME KURSU PLANI'));
      b.push({ t: 'bilgi', satirlar: [['Kurs / sınıf seviyesi', (v.ders || '') + ' – ' + (v.sinif || '')], ['Gün ve saat', v.gunsaat || ''], ['Haftalık ders saati', v.haftalik || ''], ['Başlama – bitiş', (v.baslama || '') + ' – ' + (v.bitis || '')], ['Kurs öğretmeni', v.ogretmen || '']] });
      var kn = satirlar(v.konular); if (!kn.length) for (var q = 0; q < 10; q++) kn.push('');
      b.push({ t: 'tablo', bas: ['Hafta', 'Konu / öğrenme çıktısı', 'Ders saati', 'Ölçme-değerlendirme', 'Öğretmen imzası'], gen: [8, 50, 10, 18, 14], sat: kn.map(function (x, i) { return [(i + 1) + '. hafta', x, v.haftalik || '', 'Gözlem, kısa sınav', '']; }) });
      b.push({ t: 'imza', kisiler: [{ ad: v.ogretmen, gorev: 'Kurs Öğretmeni' }], sutun: 1 }); b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü' });
      b.push({ t: 'sayfa' }); TB('KURS DEVAM ÇİZELGESİ'); b.push({ t: 'alt', s: (v.ders || '') + ' – ' + (v.sinif || '') });
      var og = satirlar(v.ogrenciler); if (!og.length) for (var q2 = 0; q2 < 15; q2++) og.push('');
      var hb = ['No', 'Adı soyadı']; for (var h1 = 1; h1 <= 10; h1++) hb.push(h1 + '.h');
      b.push({ t: 'tablo', bas: hb, gen: [5, 25].concat([7, 7, 7, 7, 7, 7, 7, 7, 7, 7]), sat: og.map(function (x, i) { var p = x.split(/\s*[-–]\s*/); return [p[1] || String(i + 1), p[0] || ''].concat(['', '', '', '', '', '', '', '', '', '']); }) });
    } else if (id === 'rehberlik') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.sube || '') + (v.program === 'rapor' ? ' SINIF REHBERLİK YIL SONU RAPORU' : ' SINIF REHBERLİK YILLIK PLANI')));
      b.push({ t: 'bilgi', satirlar: [['Sınıf / şube', v.sube || ''], ['Öğrenci sayısı', v.mevcut || ''], ['Sınıf rehber öğretmeni', v.ogretmen || ''], ['Dayanak', 'Millî Eğitim Bakanlığı Rehberlik ve Psikolojik Danışma Hizmetleri Yönetmeliği']] });
      if (v.program === 'rapor') { b.push({ t: 'h', s: 'YAPILAN ÇALIŞMALAR' }); b.push({ t: 'liste', l: ic.yapilanlar || [], yol: 'yapilanlar' }); b.push({ t: 'h', s: 'GENEL DEĞERLENDİRME' }); b.push({ t: 'p', s: ic.degerlendirme || '', yol: 'degerlendirme' }); }
      else b.push({ t: 'tablo', bas: ['Ay', 'Rehberlik etkinliği / konu', 'Uygulama şekli', 'Yapıldı'], gen: [12, 58, 18, 12], sat: (ic.aylar || []).map(function (a) { return [a.ay, a.konu, 'Sınıf rehberlik saati', '']; }) });
      b.push({ t: 'imza', kisiler: [{ ad: v.ogretmen, gorev: 'Sınıf Rehber Öğretmeni' }, { ad: v.rehber, gorev: 'Okul Rehber Öğretmeni' }], sutun: 2 }); b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü' });
    } else if (id === 'veli_gorusme') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB('VELİ GÖRÜŞME FORMU');
      b.push({ t: 'bilgi', satirlar: [['Öğrencinin adı soyadı', v.ogrenci || ''], ['Sınıfı / no', v.sube || ''], ['Velinin adı soyadı / yakınlığı', v.veli || ''], ['Görüşme tarihi ve şekli', (v.tarih || '') + ' – ' + (v.sekil || '')], ['Görüşme nedeni', v.neden || '']] });
      b.push({ t: 'h', s: 'GÖRÜŞÜLEN KONULAR' }); b.push({ t: 'p', s: v.konusulan || '……………………………………………………………………………………\n……………………………………………………………………………………' });
      b.push({ t: 'h', s: 'ALINAN KARARLAR / YAPILACAKLAR' }); b.push({ t: 'p', s: v.kararlar || '……………………………………………………………………………………\n……………………………………………………………………………………' });
      b.push({ t: 'imza', kisiler: [{ ad: v.veli ? v.veli.split(/\s*[-–]\s*/)[0] : '', gorev: 'Veli' }, { ad: v.ogretmen, gorev: 'Öğretmen' }], sutun: 2 });
    } else if (id === 'nobet') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB('ÖĞRETMEN NÖBET ÇİZELGESİ'); b.push({ t: 'alt', s: (v.baslama || '') + ' tarihinden itibaren geçerlidir' });
      var yr = nbYerler(v), NA = nbDurum(v).a;
      b.push({ t: 'tablo', bas: ['Nöbet yeri'].concat(NB_GUN), gen: [20, 16, 16, 16, 16, 16], sat: yr.map(function (y) { return [y].concat(NB_GUN.map(function (g) { return NA[g + '|' + y] || ''; })); }) });
      if (v.mudurYrd) b.push({ t: 'p', s: 'Nöbetçi müdür yardımcısı: ' + v.mudurYrd });
      b.push({ t: 'h', s: 'NÖBETÇİ ÖĞRETMENİN GÖREVLERİ' }); b.push({ t: 'liste', l: ['Nöbet görevine ders başlamadan önce başlar, son ders bitiminden sonra ayrılır.', 'Öğrencilerin güvenliğini, okulun temizlik ve düzenini gözetir; teneffüslerde nöbet yerinde bulunur.', 'Derse girmeyen öğretmenin sınıfında gerekli tedbirleri alır, durumu okul yönetimine bildirir.', 'Nöbet süresince karşılaştığı olayları nöbet defterine yazar.'] });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü', tarih: v.baslama });
    } else if (id === 'kelebek') {
      var KR = ic || {}, bas = (v.sinav ? v.sinav + ' – ' : '') + (v.tarih || '') + (v.saat ? ' – ' + v.saat : ''), yerAd = {};
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB('KELEBEK SINAV SİSTEMİ SALON DAĞILIMI'); if (bas) b.push({ t: 'alt', s: bas });
      var dagi = function (yl) { var c = {}; yl.forEach(function (o) { if (o) c[o.sinif] = (c[o.sinif] || 0) + 1; }); return Object.keys(c).sort(function (a, z) { return a.localeCompare(z, 'tr', { numeric: true }); }).map(function (k) { return k + ': ' + c[k]; }).join(', '); };
      b.push({ t: 'tablo', bas: ['Salon', 'Kapasite', 'Öğrenci', 'Şubelere göre dağılım', 'Gözetmen'], gen: [14, 11, 10, 45, 20], sat: (KR.salonlar || []).map(function (x) { return [x.ad, String(x.kap), String(x.yerler.filter(Boolean).length), dagi(x.yerler), x.gozetmen || '']; }) });
      b.push({ t: 'p', s: 'Toplam ' + (KR.toplam || 0) + ' öğrenci yerleştirildi. Kural: yan yana ve arka arkaya oturan öğrenciler ' + (KR.kural === 'seviye' ? 'farklı sınıf seviyelerinden' : 'farklı şubelerden') + '.' });
      (KR.uyari || []).forEach(function (u) { b.push({ t: 'p', s: '⚠ ' + u }); });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü / Müdür Yardımcısı', tarih: v.tarih });
      (KR.salonlar || []).forEach(function (x) {
        b.push({ t: 'sayfa' }); TB(O); TB(trUp(x.ad) + ' SALONU SINAV OTURMA PLANI'); if (bas) b.push({ t: 'alt', s: bas });
        b.push({ t: 'alt', s: '▲ YAZI TAHTASI ▲' });
        var hb = ['']; for (var c = 1; c <= x.sutun; c++) hb.push(c + '. sıra grubu'); var gg = [7]; for (var c1 = 0; c1 < x.sutun; c1++) gg.push(Math.round(93 / x.sutun));
        var st = [], n = 0, liste = [];
        for (var r = 1; r <= x.sira; r++) { var row = [r + '. sıra']; for (var c2 = 1; c2 <= x.sutun; c2++) { var h = []; for (var k = 0; k < x.kisi; k++) { var o = x.yerler[n++]; h.push(o ? (o.no ? o.no + ' ' : '') + o.ad + ' (' + o.sinif + ')' : '—'); if (o) { var yk = c2 + '. grup ' + r + '. sıra' + (x.kisi > 1 ? (k === 0 ? ' sol' : k === x.kisi - 1 ? ' sağ' : ' orta') : ''); liste.push([o, yk]); yerAd[o.sinif + '|' + o.no + '|' + o.ad] = [x.ad, yk]; } } row.push(h.join('\n')); } st.push(row); }
        b.push({ t: 'tablo', bas: hb, gen: gg, sat: st });
        b.push({ t: 'h', s: 'SALON YOKLAMA LİSTESİ' });
        b.push({ t: 'tablo', bas: ['No', 'Okul no', 'Adı soyadı', 'Şubesi', 'Yeri', 'İmza'], gen: [6, 11, 33, 10, 22, 18], sat: liste.map(function (z, i) { return [String(i + 1), z[0].no, z[0].ad, z[0].sinif, z[1], '']; }) });
        b.push({ t: 'imza', kisiler: [{ ad: x.gozetmen, gorev: 'Gözetmen Öğretmen' }], sutun: 1 });
      });
      var kbs = kbDurum(v).siniflar;
      kbs.forEach(function (cs) {
        b.push({ t: 'sayfa' }); TB(O); TB(trUp(cs.ad) + ' ŞUBESİ SINAV YERLERİ'); if (bas) b.push({ t: 'alt', s: bas });
        b.push({ t: 'tablo', bas: ['No', 'Okul no', 'Adı soyadı', 'Sınav salonu', 'Yeri'], gen: [6, 12, 38, 18, 26], sat: cs.ogr.slice().sort(function (a, z) { return (+a.no || 0) - (+z.no || 0); }).map(function (o, i) { var y = yerAd[cs.ad + '|' + o.no + '|' + o.ad] || ['Yerleşmedi', '']; return [String(i + 1), o.no, o.ad, y[0], y[1]]; }) });
      });
    } else if (id === 'iyep') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.ders || '') + ' DERSİ İLKOKULLARDA YETİŞTİRME PROGRAMI (İYEP) PLANI'));
      b.push({ t: 'bilgi', satirlar: [['Ders / sınıf', (v.ders || '') + ' – ' + (v.sinif || '')], ['Uygulama şekli', v.uygulama || ''], ['Gün ve saat', v.gunsaat || ''], ['Haftalık ders saati', v.haftalik || ''], ['Başlama – bitiş', (v.baslama || '…/…/20…') + ' – ' + (v.bitis || '…/…/20…')], ['Öğretmen', v.ogretmen || '']] });
      b.push({ t: 'h', s: 'PROGRAMIN AMACI' }); b.push({ t: 'p', s: ic.amac || '', yol: 'amac' });
      b.push({ t: 'h', s: 'MODÜL PLANI' });
      b.push({ t: 'tablo', bas: ['No', 'Modül / kazanım', 'Öğrenme-öğretme etkinlikleri', 'Süre (saat)', 'Ölçme', 'Uygulama tarihi'], gen: [5, 30, 30, 9, 13, 13], sat: (ic.etkinlik || []).map(function (e, i) { return [String(i + 1), e.modul, e.etkinlik, '', e.olcme, '']; }) });
      b.push({ t: 'h', s: 'UYGULAMA ESASLARI' }); b.push({ t: 'liste', l: ic.esaslar || [], yol: 'esaslar' });
      var io = satirlar(v.ogrenciler); if (!io.length) for (var ii = 0; ii < 8; ii++) io.push('');
      b.push({ t: 'h', s: 'PROGRAMA KATILAN ÖĞRENCİLER VE İZLEME' });
      b.push({ t: 'tablo', bas: ['No', 'Adı soyadı', 'Okul no', 'Ön değerlendirme', 'Ara değerlendirme', 'Son değerlendirme', 'Sonuç'], gen: [5, 27, 10, 15, 15, 15, 13], sat: io.map(function (x, i) { var p = parcala(x); return [String(i + 1), p[0] || '', p[1] || '', '', '', '', '']; }) });
      b.push({ t: 'imza', kisiler: [{ ad: v.ogretmen, gorev: 'İYEP Öğretmeni' }], sutun: 1 }); b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü' });
      b.push({ t: 'sayfa' }); TB('İYEP DEVAM ÇİZELGESİ'); b.push({ t: 'alt', s: (v.ders || '') + ' – ' + (v.sinif || '') });
      var ihb = ['No', 'Adı soyadı']; for (var ih = 1; ih <= 10; ih++) ihb.push(ih + '.h');
      b.push({ t: 'tablo', bas: ihb, gen: [5, 25, 7, 7, 7, 7, 7, 7, 7, 7, 7, 7], sat: io.map(function (x, i) { return [String(i + 1), parcala(x)[0] || ''].concat(['', '', '', '', '', '', '', '', '', '']); }) });
    } else if (id === 'yazili_teslim') {
      var kp = v.kapsam || 'I. ve II. dönem', d1 = kp !== 'II. dönem', d2 = kp !== 'I. dönem';
      b.push({ t: 'bosluk', n: 300 }); TB(O + ' MÜDÜRLÜĞÜNE'); b.push({ t: 'bosluk', n: 240 });
      b.push({ t: 'p', s: '        ' + (ic.metin || ''), yol: 'metin', onek: '        ' }); b.push({ t: 'p', s: '        Gereğini arz ederim.' }); b.push({ t: 'bosluk', n: 360 });
      b.push({ t: 'sag', s: (v.tarih || '…/…/20…') + '\n' + (v.ogretmen || '………………….') + '\nDers Öğretmeni' }); b.push({ t: 'bosluk', n: 360 });
      var ys = ytDurum(v).filter(function (r) { return r.sinif || r.ders || r.d1 || r.d2; }).map(function (r) { return [ytSinifAd(r), r.ders === 'Diğer…' ? '' : r.ders, String(ytSay(r.d1) || r.d1 || ''), String(ytSay(r.d2) || r.d2 || '')]; }), t1 = 0, t2 = 0; while (ys.length < 6) ys.push('');
      var ybas = ['SIRA', 'SINIF', 'DERSLER'].concat(d1 ? ['I. DÖNEM'] : [], d2 ? ['II. DÖNEM'] : [], d1 && d2 ? ['TOPLAM'] : []);
      var ysat = ys.map(function (x, i) { var p = Array.isArray(x) ? x : x ? parcala(x) : [], a = +p[2] || 0, c = +p[3] || 0; if (!d1) { c = +p[2] || 0; a = 0; } t1 += a; t2 += c; var r = [String(i + 1), p[0] || '', p[1] || '']; if (d1) r.push(p[2] || ''); if (d2) r.push(d1 ? p[3] || '' : p[2] || ''); if (d1 && d2) r.push(a || c ? String(a + c) : ''); return r; });
      var tr = ['', '', 'TOPLAM']; if (d1) tr.push(t1 ? String(t1) : ''); if (d2) tr.push(t2 ? String(t2) : ''); if (d1 && d2) tr.push(t1 + t2 ? String(t1 + t2) : ''); ysat.push(tr);
      b.push({ t: 'tablo', bas: ybas, gen: d1 && d2 ? [9, 15, 28, 16, 16, 16] : [10, 20, 40, 30], sat: ysat });
    } else if (id === 'sinav_tutanak') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI'); TB(O); TB('YAZILI SINAV UYGULAMA TUTANAĞI');
      b.push({ t: 'bilgi', satirlar: [['Ders / sınav', (v.ders || '') + ' – ' + (v.sinav || '')], ['Sınıf / şube', v.sube || ''], ['Tarih / ders saati', (v.tarih || '') + (v.saat ? ' – ' + v.saat : '')], ['Mevcut / sınava giren', (v.mevcut || '…') + ' / ' + (v.giren || '…')], ['Gözetmen öğretmen', v.gozetmen || '']] });
      b.push({ t: 'p', s: ic.metin || '', yol: 'metin' });
      var gm = satirlar(v.girmeyenler); b.push({ t: 'h', s: 'SINAVA GİRMEYEN ÖĞRENCİLER' });
      if (gm.length) b.push({ t: 'tablo', bas: ['No', 'Okul no', 'Adı soyadı', 'Neden / açıklama'], gen: [7, 15, 40, 38], sat: gm.map(function (x, i) { var p = parcala(x); return /^\d+$/.test(p[0]) ? [String(i + 1), p[0], p[1] || '', p.slice(2).join(' - ')] : [String(i + 1), '', p[0], p.slice(1).join(' - ')]; }) });
      else b.push({ t: 'p', s: 'Sınava girmeyen öğrenci bulunmamaktadır.' });
      b.push({ t: 'h', s: 'SINAV SIRASINDA YAŞANAN DURUMLAR' }); b.push({ t: 'p', s: ic.durum || '', yol: 'durum' });
      b.push({ t: 'imza', kisiler: [{ ad: v.gozetmen, gorev: 'Gözetmen Öğretmen' }, { ad: v.ogretmen, gorev: (v.ders || '') + ' Öğretmeni' }], sutun: 2 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü / Müdür Yardımcısı', tarih: v.tarih });
    } else if (id === 'sinav_takvim') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.donem || '') + ' ' + (v.sinav || '') + ' SINAV TAKVİMİ')); if (v.siniflar) b.push({ t: 'alt', s: v.siniflar });
      var st = satirlar(v.sinavlar).map(function (x) { var p = parcala(x); return { ders: p[0] || '', tarih: p[1] || '', saat: p.slice(2).join(' - ') }; });
      st.sort(function (a, c) { var f = function (t) { var m = String(t).match(/(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})/); return m ? +m[3] * 10000 + +m[2] * 100 + +m[1] : 99999999; }; return f(a.tarih) - f(c.tarih); });
      b.push({ t: 'tablo', bas: ['No', 'Ders', 'Sınav tarihi', 'Gün', 'Ders saati', 'Açıklama'], gen: [5, 30, 15, 13, 15, 22], sat: st.map(function (x, i) { return [String(i + 1), x.ders, x.tarih, gunAdi(x.tarih), x.saat, '']; }) });
      b.push({ t: 'liste', l: ['Sınavlar, ders öğretmenlerinin zümre kararları doğrultusunda hazırladığı sorularla yapılır.', 'Sınav tarihleri öğrencilere ve velilere önceden duyurulur; değişiklikler okul idaresince ilan edilir.', 'Raporlu ya da izinli olan öğrenciler için mazeret sınavı tarihi ayrıca bildirilir.'] });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü' });
    } else if (id === 'ders_kesim') {
      TB(Y + ' EĞİTİM ÖĞRETİM YILI'); TB(O); TB(v.donem === '1. dönem sonu' ? '1. DÖNEM SONU DERS KESİM RAPORU' : 'YIL SONU DERS KESİM RAPORU'); b.push({ t: 'bosluk', n: 200 });
      b.push({ t: 'p', s: '        ' + (ic.metin || ''), yol: 'metin', onek: '        ' }); b.push({ t: 'bosluk', n: 200 });
      var ks = satirlar(v.satirlar); while (ks.length < 13) ks.push('');
      b.push({ t: 'tablo', bas: ['SIRA', 'SINIF', 'DERSLER', 'VARSA YETİŞTİRİLEMEYEN KONU VE SEBEBİ'], gen: [9, 15, 30, 46], sat: ks.map(function (x, i) { var p = x ? parcala(x) : []; return [String(i + 1), p[0] || '', p[1] || '', p.slice(2).join(' - ')]; }) });
      b.push({ t: 'bosluk', n: 400 }); b.push({ t: 'alt', s: v.tarih || '…/06/20…' }); b.push({ t: 'alt', s: v.ogretmen || '……….……………' }); b.push({ t: 'alt', s: 'Ders Öğretmeni' });
    } else if (id === 'oturma') {
      var sinav = v.amac === 'Sınav oturma planı';
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.sube || '') + ' SINIFI ' + (sinav ? 'SINAV OTURMA PLANI' : 'OTURMA PLANI'))); if (sinav && v.ders) b.push({ t: 'alt', s: v.ders });
      var sut = +v.sutun || 3, sr = +v.sira || 5, ks2 = +v.kisi || 2, ad = (ic.sira || []).slice(), n0 = 0, kap = sut * sr * ks2;
      b.push({ t: 'alt', s: '▲ YAZI TAHTASI / ÖĞRETMEN MASASI ▲' });
      var ob = ['']; for (var c = 1; c <= sut; c++) ob.push(c + '. sıra grubu');
      var osat = []; for (var r = 1; r <= sr; r++) { var row = [r + '. sıra']; for (var c2 = 0; c2 < sut; c2++) { var h = []; for (var q3 = 0; q3 < ks2; q3++) h.push(ad[n0++] || '—'); row.push(h.join('\n')); } osat.push(row); }
      var og2 = [8]; for (var c3 = 0; c3 < sut; c3++) og2.push(Math.round(92 / sut));
      b.push({ t: 'tablo', bas: ob, gen: og2, sat: osat });
      b.push({ t: 'p', s: 'Öğrenci sayısı: ' + ad.length + ' · Sıra kapasitesi: ' + kap + (ad.length > kap ? ' · Uyarı: ' + (ad.length - kap) + ' öğrenci yerleşmedi; sıra veya sütun sayısını artırın.' : '') + (v.duzen ? ' · Yerleştirme: ' + v.duzen.toLocaleLowerCase('tr') : '') });
      b.push({ t: 'imza', kisiler: [{ ad: v.ogretmen, gorev: sinav ? 'Gözetmen Öğretmen' : 'Sınıf Öğretmeni' }], sutun: 1 });
    } else if (id === 'yoklama') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.sube ? v.sube + ' ' : '') + (v.baslik || 'YOKLAMA ÇİZELGESİ')));
      var yn = +v.sutun || 10, yb2 = satirlar(v.sutunad), yl = satirlar(v.ogrenciler); if (!yl.length) for (var y3 = 0; y3 < 30; y3++) yl.push('');
      var yh = ['No', 'Okul no', 'Adı soyadı']; for (var y4 = 0; y4 < yn; y4++) yh.push(yb2[y4] || ''); yh.push('Açıklama');
      var yg = [4, 7, 22]; for (var y5 = 0; y5 < yn; y5++) yg.push(Math.round(55 / yn)); yg.push(12);
      b.push({ t: 'tablo', bas: yh, gen: yg, sat: yl.map(function (x, i) { var p = parcala(x), nn = /^\d+$/.test(p[0]) && p.length > 1; var r = [String(i + 1), nn ? p[0] : '', nn ? p.slice(1).join(' ') : x]; for (var y6 = 0; y6 < yn; y6++) r.push(''); r.push(''); return r; }) });
      b.push({ t: 'p', s: 'İşaretler: + geldi, – gelmedi, İ izinli, R raporlu' });
      b.push({ t: 'imza', kisiler: [{ ad: v.ogretmen, gorev: 'Öğretmen' }], sutun: 1 });
    } else if (id === 'tutanak') {
      var TTA = { genel: 'TUTANAK', olay: 'OLAY TUTANAĞI', kaza: 'KAZA TUTANAĞI', kayip: 'KAYIP / HASAR TUTANAĞI', teslim: 'TESLİM-TESELLÜM TUTANAĞI', imtina: 'TEBLİĞDEN İMTİNA TUTANAĞI' };
      TB(O); TB(TTA[v.ttur] || 'TUTANAK');
      b.push({ t: 'bilgi', satirlar: [['Konu', v.konu || ''], ['Tarih / saat', (v.tarih || '') + (v.saat ? ' – ' + v.saat : '')], ['Yer', v.yer || '']] });
      b.push({ t: 'p', s: ic.metin || '', yol: 'metin' });
      if (v.ttur === 'teslim') {
        var te = satirlar(v.esyalar); if (!te.length) for (var t4 = 0; t4 < 5; t4++) te.push('');
        b.push({ t: 'tablo', bas: ['No', 'Eşya / belgenin cinsi', 'Adet', 'Açıklama'], gen: [7, 45, 12, 36], sat: te.map(function (x, i) { var p = parcala(x); return [String(i + 1), p[0] || '', p[1] || '', p.slice(2).join(' - ')]; }) });
        var kk = function (x, g) { var p = parcala(x || ''); return { ad: p[0] || '', gorev: g + (p[1] ? ' (' + p.slice(1).join(' ') + ')' : '') }; };
        b.push({ t: 'imza', kisiler: [kk(v.teslimeden, 'Teslim Eden'), kk(v.teslimalan, 'Teslim Alan')], sutun: 2 });
      } else {
        var dz = satirlar(v.duzenleyenler); if (!dz.length) dz = ['', '', ''];
        b.push({ t: 'imza', kisiler: dz.map(function (x) { var p = parcala(x); return { ad: p[0] || '', gorev: p.slice(1).join(' ') }; }), sutun: 3 });
      }
    } else if (id === 'ogrenci_gorusme') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB('ÖĞRENCİ GÖRÜŞME FORMU');
      b.push({ t: 'bilgi', satirlar: [['Öğrencinin adı soyadı', v.ogrenci || ''], ['Sınıfı / no', v.sube || ''], ['Görüşme tarihi', v.tarih || ''], ['Görüşme nedeni', v.neden || ''], ['Görüşmeyi yapan', v.ogretmen || '']] });
      b.push({ t: 'h', s: 'GÖRÜŞÜLEN KONULAR' }); b.push({ t: 'p', s: v.konusulan || '……………………………………………………………………………………\n……………………………………………………………………………………\n……………………………………………………………………………………' });
      b.push({ t: 'h', s: 'ALINAN KARARLAR / ÖĞRENCİYLE YAPILAN ANLAŞMA' }); b.push({ t: 'p', s: v.kararlar || '……………………………………………………………………………………\n……………………………………………………………………………………' });
      b.push({ t: 'bilgi', satirlar: [['Yönlendirme', v.yonlendirme || ''], ['Takip görüşmesi tarihi', '…/…/20…']] });
      b.push({ t: 'p', s: 'Bu form gizlidir; yalnızca öğrencinin gelişimini izlemek amacıyla kullanılır.' });
      b.push({ t: 'imza', kisiler: [{ ad: v.ogrenci, gorev: 'Öğrenci' }, { ad: v.ogretmen, gorev: 'Öğretmen' }], sutun: 2 });
    } else if (id === 'ogrenci_bilgi') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.sube ? v.sube + ' SINIFI ' : '') + 'ÖĞRENCİ TANIMA VE VELİ BİLGİ FORMU'));
      b.push({ t: 'h', s: 'ÖĞRENCİ BİLGİLERİ' }); b.push({ t: 'bilgi', satirlar: [['Adı soyadı', ''], ['Okul no / sınıfı', ''], ['Doğum yeri ve tarihi', ''], ['Kardeş sayısı / kaçıncı çocuk', ''], ['Kiminle yaşıyor', '☐ Anne-baba  ☐ Anne  ☐ Baba  ☐ Diğer: ……………'], ['İlgi alanları ve hobileri', ''], ['Sevdiği / zorlandığı dersler', '']] });
      b.push({ t: 'h', s: 'VELİ VE İLETİŞİM BİLGİLERİ' }); b.push({ t: 'bilgi', satirlar: [['Annenin adı soyadı / mesleği', ''], ['Annenin telefonu', ''], ['Babanın adı soyadı / mesleği', ''], ['Babanın telefonu', ''], ['Acil durumda aranacak kişi / telefon', ''], ['Ev adresi', '']] });
      b.push({ t: 'h', s: 'SAĞLIK BİLGİLERİ' }); b.push({ t: 'bilgi', satirlar: [['Sürekli hastalığı', ''], ['Alerjisi', ''], ['Sürekli kullandığı ilaç', ''], ['Görme / işitme sorunu', ''], ['Öğretmenin bilmesini istediğiniz durumlar', '']] });
      b.push({ t: 'p', s: 'Aydınlatma: Bu formdaki bilgiler 6698 sayılı Kişisel Verilerin Korunması Kanunu kapsamında yalnızca öğrencinin eğitim-öğretim süreci, sağlığı ve güvenliği ile veli iletişimi amacıyla okul içinde kullanılır; üçüncü kişilerle paylaşılmaz. Bilgiler değiştiğinde sınıf öğretmenine bildirilmesi rica olunur.' + (v.tel ? ' Okul telefonu: ' + v.tel : '') });
      b.push({ t: 'sag', s: '…/…/20…\nVelinin adı soyadı: ………………………\nİmza: ……………' });
      b.push({ t: 'imza', kisiler: [{ ad: v.ogretmen, gorev: 'Sınıf Öğretmeni' }], sutun: 1 });
    } else if (id === 'demirbas') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.sube || '') + ' SINIFI DEMİRBAŞ VE ZİMMET LİSTESİ')); b.push({ t: 'alt', s: 'Sayım tarihi: ' + (v.tarih || '…/…/20…') });
      var de = satirlar(v.esyalar); if (!de.length) de = ['Öğrenci sırası', 'Öğrenci sandalyesi', 'Öğretmen masası', 'Öğretmen sandalyesi', 'Yazı tahtası', 'Etkileşimli tahta', 'Dolap', 'Pano', 'Perde', 'Çöp kutusu'];
      b.push({ t: 'tablo', bas: ['No', 'Demirbaşın cinsi', 'Adet', 'Durumu', 'Açıklama'], gen: [6, 38, 10, 18, 28], sat: de.map(function (x, i) { var p = parcala(x); return [String(i + 1), p[0] || '', p[1] || '', p[2] || '', p.slice(3).join(' - ')]; }) });
      b.push({ t: 'p', s: 'Yukarıda cinsi, adedi ve durumu yazılı demirbaş eşyalar sayılarak teslim edilmiş ve teslim alınmıştır. Eşyaların özenle kullanılmasından ve korunmasından sınıf öğretmeni sorumludur; arıza ve eksiklikler okul idaresine yazılı olarak bildirilir.' });
      var dk = function (x, g) { var p = parcala(x || ''); return { ad: p[0] || '', gorev: g + (p[1] ? ' (' + p.slice(1).join(' ') + ')' : '') }; };
      b.push({ t: 'imza', kisiler: [dk(v.teslimeden, 'Teslim Eden'), dk(v.teslimalan, 'Teslim Alan')], sutun: 2 }); b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü', tarih: v.tarih });
    } else if (id === 'devamsizlik') {
      TB(O + ' MÜDÜRLÜĞÜ'); b.push({ t: 'bosluk', n: 120 });
      b.push({ t: 'p', s: 'Sayı: ……………\nKonu: Öğrenci devamsızlığı hk.' }); b.push({ t: 'sag', s: v.tarih || '…/…/20…' }); b.push({ t: 'bosluk', n: 120 });
      b.push({ t: 'p', s: ic.metin || '', yol: 'metin' }); b.push({ t: 'bosluk', n: 200 });
      b.push({ t: 'imza', kisiler: [{ ad: v.ogretmen, gorev: 'Sınıf Rehber Öğretmeni' }, { ad: v.mudur, gorev: 'Okul Müdürü' }], sutun: 2 });
      b.push({ t: 'h', s: 'TEBLİĞ-TEBELLÜĞ' }); b.push({ t: 'p', s: 'Bu yazının bir örneğini …/…/20… tarihinde elden teslim aldım.' }); b.push({ t: 'sag', s: 'Velinin adı soyadı: ' + (v.veli || '………………………') + '\nİmza: ……………' });
    } else if (id === 'karne') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.sube || '') + ' SINIFI ' + (v.donem || '') + ' KARNE GÖRÜŞLERİ'));
      var kg = satirlar(v.ogrenciler);
      b.push({ t: 'tablo', bas: ['No', 'Öğrencinin adı soyadı', 'Görüş'], gen: [6, 24, 70], sat: kg.map(function (x, i) { var p = x.split(/\s*[-–]\s*/), ad = p[0], d = (p[1] || 'iyi').toLocaleLowerCase('tr'); var l = KARNE[d] || KARNE['iyi']; return [String(i + 1), ad, ad.split(' ')[0] + ', ' + l[i % l.length]]; }) });
      b.push({ t: 'p', s: 'Görüşler e-Okul’a girilmeden önce her öğrenci için gözden geçirilip kişiselleştirilmelidir.' });
      b.push({ t: 'imza', kisiler: [{ ad: v.ogretmen, gorev: 'Sınıf Öğretmeni' }], sutun: 1 });
    } else if (id === 'gunluk_plan') {
      var gs = gpSecili(v), YPg = ypDurum(v), dad = YPg.ders === '__' ? YPg.dersAd : YPg.ders;
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((dad || '') + ' DERSİ GÜNLÜK DERS PLANI'));
      b.push({ t: 'bilgi', satirlar: [['Ders', dad || ''], ['Sınıf / şube', v.sube || (YPg.sinif ? YPg.sinif + '. sınıf' : '')], ['Tarih', v.tarih || ''], ['Süre', (v.sure || '1') + ' ders saati (' + (+(v.sure || 1) * 40) + ' dk)'], ['Ünite / tema', gs.unite || ''], ['Konu', v.konu || ''], ['Anahtar kavramlar', v.kavram || ''], ['Araç-gereç ve kaynaklar', v.arac || '']] });
      b.push({ t: 'h', s: 'ÖĞRENME ÇIKTILARI VE SÜREÇ BİLEŞENLERİ' }); b.push({ t: 'liste', l: gs.ciktilar.map(function (x) { return (x.kod ? x.kod + ' ' : '') + x.ad; }) });
      b.push({ t: 'h', s: 'ÖĞRENME-ÖĞRETME UYGULAMALARI' });
      b.push({ t: 'p', s: 'Giriş' }); b.push({ t: 'liste', l: ic.giris || [], yol: 'giris', numara: false });
      b.push({ t: 'p', s: 'Keşfetme ve gelişme' }); b.push({ t: 'liste', l: ic.kesfetme || [], yol: 'kesfetme', numara: false });
      b.push({ t: 'p', s: 'Uygulama' }); b.push({ t: 'liste', l: ic.uygulama || [], yol: 'uygulama', numara: false });
      b.push({ t: 'p', s: 'Kapanış ve yansıtma' }); b.push({ t: 'liste', l: ic.kapanis || [], yol: 'kapanis', numara: false });
      b.push({ t: 'h', s: 'FARKLILAŞTIRMA' }); b.push({ t: 'p', s: 'Zenginleştirme: ' + (ic.zenginlestirme || ''), yol: 'zenginlestirme', onek: 'Zenginleştirme: ' }); b.push({ t: 'p', s: 'Destekleme: ' + (ic.destekleme || ''), yol: 'destekleme', onek: 'Destekleme: ' });
      b.push({ t: 'h', s: 'ÖLÇME VE DEĞERLENDİRME' }); b.push({ t: 'liste', l: ic.olcme || [], yol: 'olcme' });
      b.push({ t: 'h', s: 'PROGRAMLAR ARASI BİLEŞENLER' }); b.push({ t: 'p', s: ic.bilesenler || '', yol: 'bilesenler' });
      b.push({ t: 'h', s: 'OKUL-AİLE-TOPLUM İŞ BİRLİĞİ' }); b.push({ t: 'p', s: [].concat(ic.aile || '').join(' '), yol: 'aile' });
      b.push({ t: 'h', s: 'ÖĞRETMEN YANSITMALARI' }); b.push({ t: 'p', s: '……………………………………………………………………………………………………………………………\n……………………………………………………………………………………………………………………………' });
      b.push({ t: 'imza', kisiler: [{ ad: v.ogretmen, gorev: (dad || '') + ' Öğretmeni' }], sutun: 1 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü', tarih: v.tarih });
    } else if (id === 'yillik') {
      var P = ypDurum(v), dad = P.ders === '__' ? P.dersAd : P.ders, PL = ypPlan(P);
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((P.sinif || '') + '. SINIF ' + (dad || '') + ' DERSİ ÜNİTELENDİRİLMİŞ YILLIK PLANI'));
      if (+P.sinif >= 9 && P.tur) b.push({ t: 'alt', s: P.tur });
      b.push({ t: 'bilgi', satirlar: [['Haftalık ders saati', String(PL.saat)], ['Ders yapılan hafta', String(PL.hafta) + ' hafta (' + String(TK.baslangic || '').split('-').reverse().join('.') + ' – ' + String(TK.bitis || '').split('-').reverse().join('.') + ')'], ['Dayanak', 'Türkiye Yüzyılı Maarif Modeli ' + (dad || '') + ' dersi öğretim programı; ' + (TK.yil || '') + ' eğitim-öğretim yılı çalışma takvimi'], ['Ders öğretmeni', v.ogretmen || '']] });
      if (PL.meb) b.push({ t: 'tablo', bas: ['Ay', 'Hafta', 'Saat', 'Ünite / tema', 'Konu (içerik çerçevesi)', 'Öğrenme çıktıları', 'Süreç bileşenleri', 'Ölçme ve değerlendirme', 'Programlar arası bileşenler', 'Belirli gün ve haftalar', 'Açıklama'], gen: [5, 8, 3, 9, 10, 17, 16, 9, 10, 8, 5], sat: PL.satir.map(function (r) { return [r.ay, r.hafta, r.saat, r.unite, r.konu, r.cikti, r.surec, r.olcme, r.bilesen, r.bgh, r.aciklama]; }) });
      else b.push({ t: 'tablo', bas: ['Ay', 'Hafta', 'Saat', 'Ünite / tema', 'Öğrenme çıktıları', 'Ölçme ve değerlendirme', 'Belirli gün ve haftalar', 'Açıklama'], gen: [6, 11, 4, 14, 35, 10, 11, 9], sat: PL.satir.map(function (r) { return [r.ay, r.hafta, r.saat, r.unite, r.cikti, r.olcme, r.bgh, r.aciklama]; }) });
      b.push({ t: 'p', s: 'Bu plan Türkiye Yüzyılı Maarif Modeli öğretim programı ve çalışma takvimi esas alınarak hazırlanmıştır. Belirli gün ve haftalar, ilgili derslerde öğrenme çıktılarıyla ilişkilendirilerek işlenir.' });
      b.push({ t: 'imza', baslik: 'ZÜMRE ÖĞRETMENLERİ', kisiler: [{ ad: v.ogretmen, gorev: (dad || '') + ' Öğretmeni' }].concat(satirlar(v.zumre).map(function (a) { return { ad: a, gorev: (dad || '') + ' Öğretmeni' }; })), sutun: 3 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü', tarih: TK.baslangic ? TK.baslangic.split('-').reverse().join('.') : '' });
    } else if (id === 'oys') {
      var Q = oyDurum(v), n = Q.sorular.length, H = oyHesap(Q), bas = ['No', 'Adı soyadı'];
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((Q.ders || '') + ' DERSİ ' + (v.sube || (Q.sinif ? Q.sinif + '. SINIF' : '')) + ' ' + Q.donem + '. DÖNEM ' + Q.yazili + '. ORTAK YAZILI SINAV PUAN ÇİZELGESİ'));
      b.push({ t: 'bilgi', satirlar: [['Sınav tarihi', v.tarih || ''], ['Konu-soru dağılımı', Q.senaryo ? 'MEB ' + Q.senaryo + '. senaryo' + (Q.duzen ? ' (öğretmen düzenlemesiyle)' : '') : 'Öğretmen planı'], ['Soru sayısı / toplam puan', n + ' soru / ' + H.toplamPuan + ' puan'], ['Ders öğretmeni', v.ogretmen || '']] });
      b.push({ t: 'h', s: 'SORU – ÖĞRENME ÇIKTISI EŞLEŞMESİ' });
      b.push({ t: 'tablo', bas: ['Soru', 'Öğrenme çıktısı', 'Puan', 'Ortalama', 'Başarı'], gen: [6, 64, 8, 10, 12], sat: Q.sorular.map(function (q, i) { return [String(i + 1), (q.kod ? q.kod + ' – ' : '') + (q.cikti || ''), String(q.puan || ''), H.ort[i] == null ? '' : H.ort[i].toFixed(1), H.ort[i] == null || !q.puan ? '' : '%' + Math.round(H.ort[i] / q.puan * 100)]; }) });
      for (var si = 1; si <= n; si++) bas.push(String(si)); bas.push('Top.');
      var rows = [['', 'Soru puanı'].concat(Q.sorular.map(function (q) { return String(q.puan || ''); }), [String(H.toplamPuan)])];
      var og = Q.ogr.length ? Q.ogr : (function () { var o = []; for (var z = 0; z < 30; z++) o.push({ no: '', ad: '', p: [] }); return o; })();
      og.forEach(function (o, k) { var r = [o.no || '', o.ad || '']; for (var j = 0; j < n; j++) r.push(o.p && o.p[j] != null ? String(o.p[j]) : ''); r.push(H.top[k] == null ? '' : String(H.top[k])); rows.push(r); });
      if (H.dolu) { rows.push(['', 'Ortalama'].concat(H.ort.map(function (x) { return x == null ? '' : x.toFixed(1); }), [H.sinifOrt.toFixed(1)])); rows.push(['', 'Başarı %'].concat(H.ort.map(function (x, i) { return x == null || !Q.sorular[i].puan ? '' : String(Math.round(x / Q.sorular[i].puan * 100)); }), [''])); }
      b.push({ t: 'sayfa' }); TB('ÖĞRENCİ PUANLARI');
      b.push({ t: 'tablo', bas: bas, gen: [6, 22].concat(Q.sorular.map(function () { return 64 / Math.max(1, n); }), [8]), sat: rows });
      if (H.dolu) {
        var cik = {}; Q.sorular.forEach(function (q, i) { var k = q.kod || q.cikti || ('Soru ' + (i + 1)); if (!cik[k]) cik[k] = { ad: (q.kod ? q.kod + ' – ' : '') + (q.cikti || ''), s: [], al: 0, max: 0 }; cik[k].s.push(i + 1); if (H.ort[i] != null) { cik[k].al += H.ort[i]; cik[k].max += q.puan || 0; } });
        b.push({ t: 'h', s: 'ÖĞRENME ÇIKTILARINA GÖRE BAŞARI' });
        b.push({ t: 'tablo', bas: ['Öğrenme çıktısı', 'Sorular', 'Başarı'], gen: [70, 15, 15], sat: Object.keys(cik).map(function (k) { var c = cik[k]; return [c.ad, c.s.join(', '), c.max ? '%' + Math.round(c.al / c.max * 100) : '']; }) });
        var tp = H.top.filter(function (x) { return x != null; }), gec = tp.filter(function (x) { return x >= 50; }).length;
        b.push({ t: 'bilgi', satirlar: [['Sınava giren öğrenci', String(tp.length)], ['Sınıf ortalaması', H.sinifOrt.toFixed(2)], ['En yüksek / en düşük puan', Math.max.apply(null, tp) + ' / ' + Math.min.apply(null, tp)], ['50 ve üzeri alan', gec + ' öğrenci (%' + Math.round(gec / tp.length * 100) + ')']] });
      }
      b.push({ t: 'imza', kisiler: [{ ad: v.ogretmen, gorev: (Q.ders || '') + ' Öğretmeni' }], sutun: 1 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü', tarih: v.tarih });
    }
    return b;
  }

  /* ---------------- durum ---------------- */
  var S = ls('osc_ev_taslak') || { ekran: 'sec', tur: '', veri: {}, ic: null, id: 0 };
  /* Yazılı Hazırla'dan aktarılan evrak (ör. OYS puan çizelgesi) */
  /* tanıtım sayfalarından gelen bağlantı: ?evrak=zumre  /  ?evrak=belirli_gun&gun=Gün adı */
  try { var QS = new URLSearchParams(location.search), QT = QS.get('evrak'); if (QT && TURLER.some(function (t) { return t.id === QT; })) { S = { ekran: 'form', tur: QT, veri: {}, ic: null, id: 0 }; if (QT === 'belirli_gun' && QS.get('gun')) { S.veri.program = 'kutlama'; S.veri.gun = QS.get('gun'); } history.replaceState(null, '', location.pathname + location.hash); } } catch (e) {}
  function istat(k) { try { var f = new FormData(); f.append('action', 'osc_istat'); f.append('k', k); navigator.sendBeacon ? navigator.sendBeacon(C.ajax, f) : fetch(C.ajax, { method: 'POST', body: f, keepalive: true }); } catch (e) {} }
  /* her evrakın kendi adresi: /evrak-hazirla/yillik-plan/ */
  var YOL = C.yollar || {}, TABAN = C.taban || '';
  function yoldanTur() {
    if (!TABAN) return ''; var p = decodeURIComponent(location.pathname); if (p.indexOf(TABAN) !== 0) return '';
    var g = p.slice(TABAN.length).replace(/\/+$/, ''); if (!g) return '';
    if (g === C.grupYol) return 'kulup';
    for (var k in YOL) if (YOL[k] === g) return k; return '';
  }
  function yolUygula(t) {
    if (t === 'kulup') { if (S.ekran !== 'kulup') S = { ekran: 'kulup', tur: '', veri: {}, ic: null, id: 0 }; }
    else if (t) { if (!(S.tur === t && (S.ekran === 'form' || S.ekran === 'onizle'))) { S = { ekran: 'form', tur: t, veri: {}, ic: null, id: 0 }; } }
    else if (S.ekran === 'form' || S.ekran === 'onizle' || S.ekran === 'kulup') S.ekran = 'sec';
  }
  try { yolUygula(yoldanTur()); var QG = new URLSearchParams(location.search).get('gun'); if (QG && S.tur === 'belirli_gun') { S.veri.program = 'kutlama'; S.veri.gun = QG; history.replaceState(null, '', location.pathname + location.hash); } } catch (e) {}
  function adresEsle() {
    if (!TABAN || !history.pushState) return;
    var h = TABAN; if ((S.ekran === 'form' || S.ekran === 'onizle') && YOL[S.tur]) h = TABAN + YOL[S.tur] + '/'; else if (S.ekran === 'kulup' && C.grupYol) h = TABAN + C.grupYol + '/';
    if (decodeURIComponent(location.pathname) !== h) { try { history.pushState({ ev: 1 }, '', h + location.hash); var t = tur(S.tur); document.title = (h === TABAN || !t ? 'Evrak Hazırla' : t.ad + ' Hazırla') + ' | ' + (document.title.split(' | ').pop()); } catch (e) {} }
  }
  window.addEventListener('popstate', function () { yolUygula(yoldanTur()); sakla(); ciz(); });
  var AKT = ls('osc_ev_aktar'); if (AKT && AKT.tur) { ls('osc_ev_aktar', null); S = { ekran: 'form', tur: AKT.tur, veri: AKT.veri || {}, ic: null, id: 0 }; }
  /* ortak bilgiler (okul, müdür, öğretmen…): yalnızca üyelerde hesaba kaydedilir ve tüm evraklara işlenir */
  var ORTAK_K = { okul: 'okul', il: 'il', mudur: 'mudur', ogretmen: 'ogretmen', adsoyad: 'ogretmen', gorev: 'gorev', kademe: 'kademe', okulturu: 'okulturu' };
  var ORT = C.uye && C.profil ? C.profil : {};
  var pZ = 0, pB = {};
  function ortakKaydet(k, v) {
    if (!C.uye) return; var a = ORTAK_K[k]; if (!a) return; ORT[a] = v; pB[a] = v; clearTimeout(pZ);
    pZ = setTimeout(function () { var fd = new FormData(); fd.append('action', 'osc_om_profil'); fd.append('nonce', C.pnonce); fd.append('veri', JSON.stringify(pB)); pB = {}; fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {}); }, 900);
  }
  function sakla() { ls('osc_ev_taslak', S); }
  function kayitlar(v) { if (v === undefined) return ls('osc_ev_kayitlar') || []; ls('osc_ev_kayitlar', v.slice(0, 80)); }

  var RK = { form: document.getElementById('osc-ev-rk-form'), yan: document.getElementById('osc-ev-rk-yan') };
  function rkPark() { for (var k in RK) if (RK[k] && kok.contains(RK[k])) kok.parentNode.insertBefore(RK[k], kok.nextSibling); }
  function rkKoy(k, hedef) { if (RK[k] && hedef) hedef.appendChild(RK[k]); }
  function cerceve(ic) {
    rkPark();
    kok.innerHTML = '<header class="ev-hero"><div><span class="ev-et">Öğretmen evrakları</span><h1>Evrakınız <em>dakikalar içinde</em> hazır</h1><p>Kurul ve toplantı tutanakları, BEP planı, sosyal kulüp evrakları, gezi ve egzersiz planları, dilekçe ve puan çizelgeleri. Bilgileri doldurun, taslağı düzenleyin, Word olarak indirin.</p></div><nav><button data-ek="sec" class="' + (S.ekran !== 'liste' ? 'on' : '') + '">✨ Yeni evrak</button><button data-ek="liste" class="' + (S.ekran === 'liste' ? 'on' : '') + '">📁 Evraklarım</button></nav></header>' + ic;
    kok.querySelectorAll('[data-ek]').forEach(function (b) { b.onclick = function () { S.ekran = b.dataset.ek; sakla(); ciz(); }; });
  }
  function ciz() { cizIc(); adresEsle(); }
  function cizIc() { if (S.ekran === 'liste') return vListe(); if (S.ekran === 'form' && tur(S.tur)) return vForm(); if (S.ekran === 'onizle' && tur(S.tur) && S.ic) return vOnizle(); if (S.ekran !== 'kulup') S.ekran = 'sec'; vSec(); }

  function vSec() {
    var kul = TURLER.filter(function (t) { return t.grup === 'kulup'; }), alt = S.ekran === 'kulup' && kul.length;
    var kart = function (t) { return '<button data-tur="' + t.id + '"><span>' + t.ik + '</span><b>' + esc(t.ad) + '</b><small>' + esc(t.ac) + '</small></button>'; };
    var KS = S.kat && EVKAT[S.kat] ? EVKAT[S.kat][1] : null, kulGor = !KS || KS.indexOf('kulup_tutanak') >= 0 || S.kat === 'plan';
    var sekme = alt ? '' : '<div class="ev-sekme"><button data-kat=""' + (!KS ? ' class="on"' : '') + '>Tümü</button>' + Object.keys(EVKAT).map(function (k) { return '<button data-kat="' + k + '"' + (S.kat === k ? ' class="on"' : '') + '>' + esc(EVKAT[k][0]) + '</button>'; }).join('') + '</div>';
    var liste = alt ? kul.map(kart).join('') : TURLER.filter(function (t) { return t.grup !== 'kulup' && (!KS || KS.indexOf(t.id) >= 0); }).map(kart).join('') + (kul.length && kulGor ? '<button data-grup="kulup"><span>🎭</span><b>Sosyal kulüp evrakları</b><small>' + kul.length + ' evrak: yıllık plan, ilk toplantı tutanağı, aylık ve dönem sonu raporlar (48 kulüp, 5 tema)</small></button>' : '');
    cerceve('<section class="ev-kart">' + (alt ? '<div class="ev-ust"><button class="ev-btn hafif" id="ev-geri">← Tüm evraklar</button><h2>🎭 Sosyal kulüp evrakları</h2></div>' : '<h2>Hangi evrakı hazırlayacaksınız?</h2>' + sekme) + '<div class="ev-turler">' + (liste || '<div class="ev-bos">Şu an kullanılabilir evrak yok.</div>') + '</div><p class="ev-not">🔒 Evraklarınız yalnızca bu tarayıcıda saklanır; öğrenci bilgileri sunucumuza kaydedilmez.</p></section>');
    kok.querySelectorAll('[data-grup]').forEach(function (b) { b.onclick = function () { S.ekran = 'kulup'; sakla(); ciz(); }; });
    kok.querySelectorAll('[data-kat]').forEach(function (b) { b.onclick = function () { S.kat = b.dataset.kat; sakla(); vSec(); }; });
    var gr = document.getElementById('ev-geri'); if (gr) gr.onclick = function () { S.ekran = 'sec'; sakla(); ciz(); };
    rkKoy('form', kok);
    kok.querySelectorAll('[data-tur]').forEach(function (b) { b.onclick = function () { S.tur = b.dataset.tur; S.veri = {}; S.ic = null; S.id = 0; S.ekran = 'form'; sakla(); ciz(); window.scrollTo({ top: kok.getBoundingClientRect().top + window.pageYOffset - 20, behavior: 'smooth' }); }; });
  }
  function alanHtml(a) {
    var v = S.veri[a.k]; if (v === undefined) { v = ORTAK_K[a.k] && ORT[ORTAK_K[a.k]] && !(a.tip === 'secim' && Array.isArray(a.l) && a.l.indexOf(ORT[ORTAK_K[a.k]]) < 0) ? ORT[ORTAK_K[a.k]] : (typeof a.v === 'function' ? a.v() : (a.v || '')); S.veri[a.k] = v; }
    var g = '';
    if (a.tip === 'secim') { var l = Array.isArray(a.l) ? a.l.map(function (x) { return [x, x]; }) : Object.keys(a.l).map(function (k) { return [k, a.l[k]]; }); g = '<select data-a="' + a.k + '"><option value="">Seçin…</option>' + l.map(function (x) { return '<option value="' + esc(x[0]) + '"' + (String(v) === x[0] ? ' selected' : '') + '>' + esc(x[1]) + '</option>'; }).join('') + '</select>'; }
    else if (a.tip === 'kulup') g = '<select data-a="' + a.k + '"><option value="">Kulüp seçin…</option>' + Object.keys(KULUP).map(function (t) { return '<optgroup label="' + esc(t) + '">' + KULUP[t].map(function (x) { return '<option' + (v === x ? ' selected' : '') + '>' + esc(x) + '</option>'; }).join('') + '</optgroup>'; }).join('') + '</select>';
    else if (a.tip === 'secimler') { if (!Array.isArray(v)) { v = a.l.slice(); S.veri[a.k] = v; } g = '<div class="ev-secimler" data-coklu="' + a.k + '"><div><button type="button" class="ev-ekle" data-hep="1">Tümünü seç</button> · <button type="button" class="ev-ekle" data-hep="0">Hiçbirini seçme</button></div>' + a.l.map(function (x) { return '<label><input type="checkbox" value="' + esc(x) + '"' + (v.indexOf(x) >= 0 ? ' checked' : '') + '> ' + esc(x) + '</label>'; }).join('') + '</div>'; return '<div class="gen ev-coklu"><span>' + esc(a.ad) + '</span>' + g + '</div>'; }
    else if (a.tip === 'uzun') g = '<textarea data-a="' + a.k + '" rows="4" placeholder="' + esc(a.ph || '') + '">' + esc(v) + '</textarea>';
    else g = '<input data-a="' + a.k + '" value="' + esc(v) + '" placeholder="' + esc(a.ph || '') + '">';
    return '<label class="' + (a.tip === 'uzun' ? 'gen' : '') + '"><span>' + esc(a.ad) + '</span>' + g + '</label>';
  }
  function vForm() {
    var t = tur(S.tur), YZ = C.yz && !t.yzYok && !(S.tur === 'belirli_gun' && S.veri.program === 'yillik');
    cerceve('<section class="ev-kart"><div class="ev-ust"><button class="ev-btn hafif" id="ev-geri">← Evrak türleri</button><h2>' + t.ik + ' ' + esc(t.ad) + '</h2></div>' + (C.uye ? '<p class="ev-uye ok">✓ Okul, müdür ve kendi bilgileriniz hesabınızdan otomatik dolduruldu. Burada değiştirirseniz tüm evrak ve yazılılarınızda güncellenir.</p>' : '<p class="ev-uye">💡 <b>Ücretsiz üye olursanız</b> okul adı, il/ilçe, müdür adı ve adınız gibi bilgileri bir kez yazmanız yeterli: tüm evraklarınıza ve yazılılarınıza otomatik işlenir' + (C.yzUye ? '; metinleri yapay zekâya da yazdırabilirsiniz' : '') + '. <a href="' + esc(C.giris || '#') + '">Üye ol / giriş yap →</a></p>') + '<div class="ev-form">' + t.alan.filter(function (a) { return !a.kosul || a.kosul(S.veri); }).map(alanHtml).join('') + '</div>' + (t.ozel === 'yt' ? ytFormHtml() : t.ozel === 'nb' ? nbFormHtml() : t.ozel === 'kb' ? kbFormHtml() : t.ozel === 'oys' ? oyFormHtml() : t.ozel === 'gp' ? gpFormHtml() : t.ozel === 'yp' ? ypFormHtml() : '') +
      '<div class="ev-cubuk"><span>' + (YZ ? '🤖 Yapay zekâ, bilgilerinize göre gündemi, görüşmeleri ve kararları özgün olarak yazar.' : 'Taslak içerik bilgilerinize göre otomatik oluşturulur; dilediğiniz gibi düzenleyebilirsiniz.') + '</span><div>' + (YZ ? '<button class="ev-btn" id="ev-hizli">Hazır taslakla oluştur</button><button class="ev-btn ana" id="ev-yz">🤖 Yapay zekâ ile oluştur</button>' : '<button class="ev-btn ana" id="ev-hizli">Evrakı oluştur →</button>') + '</div></div></section>');
    if (t.ozel === 'yt') ytBagla(); if (t.ozel === 'nb') nbBagla(); if (t.ozel === 'kb') kbBagla(); if (t.ozel === 'oys') oyBagla(); if (t.ozel === 'yp') ypBagla(); if (t.ozel === 'gp') gpBagla();
    kok.querySelectorAll('[data-a]').forEach(function (e) { e.oninput = e.onchange = function (ev) { S.veri[e.dataset.a] = e.value; if (ev.type === 'change' && (t.alan.filter(function (a) { return a.k === e.dataset.a; })[0] || {}).yenile) { sakla(); return vForm(); } ortakKaydet(e.dataset.a, e.value); sakla(); }; });
    document.getElementById('ev-geri').onclick = function () { S.ekran = t.grup === 'kulup' ? 'kulup' : 'sec'; sakla(); ciz(); };
    kok.querySelectorAll('[data-coklu]').forEach(function (d) { var k = d.dataset.coklu, guncel = function () { S.veri[k] = [].map.call(d.querySelectorAll('input:checked'), function (i) { return i.value; }); sakla(); }; d.querySelectorAll('input').forEach(function (i) { i.onchange = guncel; }); d.querySelectorAll('[data-hep]').forEach(function (b) { b.onclick = function () { d.querySelectorAll('input').forEach(function (i) { i.checked = b.dataset.hep === '1'; }); guncel(); }; }); });
    rkKoy('form', kok);
    var eksik = function () { var z = { zumre: ['okul', 'ders'], sok: ['okul', 'sube'], veli: ['okul', 'sube'], bep: ['okul', 'ders'], kulup_plan: ['okul', 'kulup'], kulup_tutanak: ['okul', 'kulup'], kulup_rapor: ['okul', 'kulup'], kulup_aylik: ['okul', 'kulup'], ogr_kurul: ['okul'], egzersiz: ['okul', 'egz'], belirli_gun: ['okul'], gezi: ['okul', 'gezi'], dilekce: ['makam', 'adsoyad'], oys: ['okul'], yillik: ['okul'], gunluk_plan: ['okul'], performans: ['okul', 'ders'], dyk: ['okul', 'ders'], rehberlik: ['okul', 'sube'], veli_gorusme: ['ogrenci'], nobet: ['okul', 'ogretmenler'], karne: ['ogrenciler'], kelebek: ['okul'], iyep: ['okul', 'ders'], yazili_teslim: ['okul'], sinav_tutanak: ['okul', 'ders', 'sube'], sinav_takvim: ['okul', 'sinavlar'], ders_kesim: ['okul'], oturma: ['ogrenciler'], yoklama: ['okul'], tutanak: ['konu'], ogrenci_gorusme: ['ogrenci'], ogrenci_bilgi: ['okul'], demirbas: ['okul', 'sube'], devamsizlik: ['ogrenci', 'ozursuz'] }[S.tur] || []; if (S.tur === 'gunluk_plan' && !gpSecili(S.veri).ciktilar.length) { bildir('Sınıf ve dersi seçip en az bir öğrenme çıktısı işaretleyin.', 'hata'); return true; } if (S.tur === 'yillik') { var YP = ypDurum(S.veri); if (!YP.sinif || !(YP.ders && YP.ders !== '__' || YP.dersAd) || !YP.uniteler.length || !(+YP.saat)) { bildir('Sınıf, ders, haftalık ders saati ve en az bir ünite gerekli.', 'hata'); return true; } } if (S.tur === 'kelebek') { var KB = kbDurum(S.veri); if (!KB.siniflar.length || !KB.salonlar.length) { bildir('Önce şube listelerini yükleyin ve en az bir sınav salonu ekleyin.', 'hata'); return true; } } if (S.tur === 'oys' && !oyDurum(S.veri).sorular.length) { bildir('Önce sınıf, ders ve senaryo seçin (ya da “Soru ekle” ile soruları kendiniz girin).', 'hata'); return true; } var e = z.filter(function (k) { return !String(S.veri[k] || '').trim(); }); if (e.length) { bildir('Lütfen doldurun: ' + e.map(function (k) { return (t.alan.filter(function (a) { return a.k === k; })[0] || { ad: k }).ad; }).join(', '), 'hata'); return true; } return false; };
    document.getElementById('ev-hizli').onclick = function () { if (eksik()) return; S.ic = varsayilan(S.tur, S.veri); S.ekran = 'onizle'; sakla(); ciz(); };
    var yb = document.getElementById('ev-yz'); if (yb) yb.onclick = function () {
      if (eksik()) return; yb.disabled = true; yb.innerHTML = '<span class="ev-don"></span> Yazılıyor…';
      sunucu('yz', { tur: S.tur, veri: S.tur === 'gunluk_plan' ? Object.assign({}, S.veri, { yp: null, ders: (function () { var P = ypDurum(S.veri); return P.ders === '__' ? P.dersAd : P.ders; })(), sinif: ypDurum(S.veri).sinif, unite: gpSecili(S.veri).unite, ciktilar: gpSecili(S.veri).ciktilar.map(function (x) { return (x.kod ? x.kod + ' ' : '') + x.ad; }).join('\n') }) : S.tur === 'bep' ? Object.assign({}, S.veri, { ogrenci: '', birim: '' }) : S.veri }).then(function (ic) {
        var taban = varsayilan(S.tur, S.veri); Object.keys(taban).forEach(function (k) { if (ic[k] === undefined || ic[k] === null || (Array.isArray(ic[k]) && !ic[k].length)) ic[k] = taban[k]; });
        S.ic = ic; S.ekran = 'onizle'; sakla(); ciz(); bildir('Taslak hazır. Metinlerin üzerine tıklayarak düzenleyebilirsiniz.', 'ok');
      }).catch(function (e) { yb.disabled = false; yb.textContent = '🤖 Yapay zekâ ile oluştur'; bildir(e.message + ' Hazır taslakla devam edebilirsiniz.', 'hata'); });
    };
  }
  /* önizleme: kâğıt görünümünde, metinler yerinde düzenlenebilir */
  function vOnizle() {
    var t = tur(S.tur), bl = bloklar(S.tur, S.veri, S.ic);
    var html = bl.map(function (b, bi) {
      switch (b.t) {
        case 'baslik': return '<div class="ev-b">' + esc(b.s) + '</div>';
        case 'alt': return '<div class="ev-alt">' + esc(b.s) + '</div>';
        case 'h': return '<h4>' + esc(b.s) + '</h4>';
        case 'p': return '<p' + (b.yol ? ' contenteditable="true" data-yol="' + b.yol + '" data-onek="' + esc(b.onek || '') + '"' : '') + '>' + (b.yol ? esc(b.s) : esc(b.s).replace(/\n/g, '<br>')) + '</p>';
        case 'liste': return '<ol' + (b.numara === false ? ' class="nok"' : '') + '>' + (b.l || []).map(function (x, i) { return '<li' + (b.yol ? ' contenteditable="true" data-yol="' + b.yol + '.' + i + '"' : '') + '>' + esc(x) + '</li>'; }).join('') + '</ol>' + (b.yol ? '<button class="ev-ekle" data-ekle="' + b.yol + '">＋ madde ekle</button>' : '');
        case 'bilgi': return '<table class="ev-bilgi">' + b.satirlar.map(function (r) { return '<tr><th>' + esc(r[0]) + '</th><td>' + esc(r[1]) + '</td></tr>'; }).join('') + '</table>';
        case 'tablo': return '<table class="ev-tb' + (yatayMi(t) ? ' yatay' : '') + '">' + (b.bas ? '<tr>' + b.bas.map(function (h) { return '<th>' + esc(h) + '</th>'; }).join('') + '</tr>' : '') + b.sat.slice(0, 80).map(function (r) { return '<tr>' + r.map(function (c) { return '<td>' + esc(c).replace(/\n/g, '<br>') + '</td>'; }).join('') + '</tr>'; }).join('') + '</table>';
        case 'imza': return (b.baslik ? '<h4>' + esc(b.baslik) + '</h4>' : '') + '<div class="ev-imza">' + (b.kisiler || []).map(function (k) { return '<div><b>' + esc(k.ad || '………………') + '</b><small>' + esc(k.gorev) + '</small><i>İmza</i></div>'; }).join('') + '</div>';
        case 'onay': return '<div class="ev-onay"><b>UYGUNDUR</b><span>' + esc(b.tarih || '…/…/20…') + '</span><b>' + esc(b.ad || '………………') + '</b><span>' + esc(b.gorev) + '</span></div>';
        case 'sayfa': return '<hr class="ev-sayfa">';
        case 'sag': return '<div class="ev-sag">' + esc(b.s).replace(/\n/g, '<br>') + '</div>';
        case 'bosluk': return '<div style="height:' + Math.round((b.n || 240) / 20) + 'px"></div>';
      }
      return '';
    }).join('');
    cerceve('<section class="ev-izgara"><div class="ev-kagit' + (yatayMi(t) ? ' yatay' : '') + '">' + html + '</div><aside class="ev-yan"><div class="ev-kart"><h3>' + t.ik + ' ' + esc(t.ad) + '</h3><p class="ev-not">Kâğıt üzerindeki gündem, görüşme, karar ve plan metinlerine tıklayıp düzenleyebilirsiniz.</p>' +
      '<button class="ev-btn ana tam" id="ev-word">⬇ Word (.docx) indir</button>' + (S.tur === 'oys' ? '<button class="ev-btn tam" id="ev-csv">📊 Excel olarak indir</button>' : '') + '<button class="ev-btn tam" id="ev-yazdir">🖨 Yazdır / PDF</button><button class="ev-btn tam" id="ev-kaydet">💾 Evraklarıma kaydet</button><button class="ev-btn hafif tam" id="ev-duzen">✎ Bilgileri düzenle</button><button class="ev-btn hafif tam" id="ev-yeni">＋ Yeni evrak</button></div></aside></section>');
    rkKoy('yan', kok.querySelector('.ev-yan'));
    kok.querySelectorAll('[data-yol]').forEach(function (e) {
      e.oninput = function () {
        var y = e.dataset.yol.split('.'), metin = e.textContent;
        if (e.dataset.onek && metin.indexOf(e.dataset.onek) === 0) metin = metin.slice(e.dataset.onek.length);
        var hedef = S.ic; for (var i = 0; i < y.length - 1; i++) { if (hedef[y[i]] === undefined) hedef[y[i]] = /^\d+$/.test(y[i + 1]) ? [] : {}; hedef = hedef[y[i]]; }
        if (y.length === 1) S.ic[y[0]] = metin; else hedef[y[y.length - 1]] = metin; sakla();
      };
    });
    kok.querySelectorAll('[data-ekle]').forEach(function (b) { b.onclick = function () { var l = S.ic[b.dataset.ekle] = S.ic[b.dataset.ekle] || []; l.push('Yeni madde'); sakla(); vOnizle(); }; });
    document.getElementById('ev-word').onclick = function () { wordIndir(); };
    document.getElementById('ev-yazdir').onclick = function () { yazdir(); };
    var csvb = document.getElementById('ev-csv'); if (csvb) csvb.onclick = function () {
      var Q = oyDurum(S.veri), H = oyHesap(Q), q = function (x) { x = String(x == null ? '' : x); return /[;"\n]/.test(x) ? '"' + x.replace(/"/g, '""') + '"' : x; };
      var l = [['No', 'Adı soyadı'].concat(Q.sorular.map(function (s, i) { return (i + 1) + '. soru (' + (s.puan || 0) + ' p)'; }), ['Toplam'])];
      Q.ogr.forEach(function (o, k) { l.push([o.no || '', o.ad || ''].concat(Q.sorular.map(function (s, j) { var x = (o.p || [])[j]; return x == null ? '' : String(x).replace('.', ','); }), [H.top[k] == null ? '' : String(H.top[k]).replace('.', ',')])); });
      l.push([]); l.push(['', 'Öğrenme çıktısı']); Q.sorular.forEach(function (s, i) { l.push([String(i + 1), (s.kod ? s.kod + ' ' : '') + (s.cikti || '')]); });
      var blob = new Blob(['\ufeff' + l.map(function (r) { return r.map(q).join(';'); }).join('\r\n')], { type: 'text/csv;charset=utf-8' }), a = document.createElement('a');
      a.href = URL.createObjectURL(blob); a.download = dosyaAdi().replace(/[\\\/:*?"<>|]+/g, '-') + '.csv'; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
    };
    document.getElementById('ev-kaydet').onclick = function () {
      var l = kayitlar(), id = S.id || Date.now(), x = { id: id, tur: S.tur, ad: dosyaAdi(), tarih: bugun(), veri: S.veri, ic: S.ic }; l = l.filter(function (k) { return k.id !== id; }); l.unshift(x); kayitlar(l); S.id = id; sakla();
      if (C.uye && KISISEL.indexOf(S.tur) < 0) hesap('kaydet', { veri: JSON.stringify(x) }).then(function () { bildir('Hesabınıza kaydedildi; her cihazdan açabilirsiniz.', 'ok'); }).catch(function () { bildir('Bu tarayıcıya kaydedildi.', 'ok'); });
      else bildir(C.uye ? 'Öğrenci bilgisi içerdiği için yalnızca bu tarayıcıya kaydedildi.' : 'Bu tarayıcıya kaydedildi. Üye olursanız evraklarınız hesabınıza kaydedilir.', 'ok');
    };
    document.getElementById('ev-duzen').onclick = function () { S.ekran = 'form'; sakla(); ciz(); };
    document.getElementById('ev-yeni').onclick = function () { S = { ekran: 'sec', tur: '', veri: {}, ic: null, id: 0 }; sakla(); ciz(); };
  }
  function dosyaAdi() { var t = tur(S.tur), v = S.veri; return (t.ad.replace(/\s*\(.*?\)/g, '') + ' ' + (v.gun && v.program !== 'yillik' ? v.gun + ' ' : '') + (v.ders || (v.oys && v.oys.ders) || (v.yp && ((v.yp.sinif ? v.yp.sinif + '. sınıf ' : '') + (v.yp.ders === '__' ? v.yp.dersAd : v.yp.ders || ''))) || v.sube || v.kulup || v.sinif || '') + ' ' + (v.yil || '')).replace(/\s+/g, ' ').trim(); }
  function jszip() { return window.JSZip ? Promise.resolve() : new Promise(function (ok, no) { var s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js'; s.onload = ok; s.onerror = function () { no(new Error('Word oluşturucu yüklenemedi.')); }; document.head.appendChild(s); }); }
  function wordIndir() {
    var t = tur(S.tur);
    jszip().then(function () { return window.OSC_DOCX_EVRAK({ baslik: dosyaAdi(), site: C.site, yatay: yatayMi(t), bloklar: bloklar(S.tur, S.veri, S.ic) }); }).then(function (blob) {
      var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = dosyaAdi().replace(/[\\\/:*?"<>|]+/g, '-') + '.docx'; istat('evrak_' + S.tur); document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
    }).catch(function (e) { bildir(e.message, 'hata'); });
  }
  function yazdir() {
    var t = tur(S.tur), k = kok.querySelector('.ev-kagit'); if (!k) return;
    var w = window.open('', '_blank'); if (!w) return bildir('Açılır pencere engellendi.', 'hata');
    w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>' + esc(dosyaAdi()) + '</title><style>@page{size:A4 ' + (yatayMi(t) ? 'landscape' : 'portrait') + ';margin:14mm}body{font:11pt Calibri,Arial,sans-serif;color:#111}' + EV_BASKI + '</style></head><body><div class="ev-kagit">' + k.innerHTML.replace(/contenteditable="true"/g, '').replace(/<button[^>]*>.*?<\/button>/g, '') + '</div><script>onload=function(){print()}<\/script></body></html>');
    w.document.close();
  }
  var EV_BASKI = '.ev-b{text-align:center;font-weight:700;margin:0 0 3px}.ev-alt{text-align:center;margin-bottom:8px}h4{color:#1e3a8a;margin:14px 0 6px;font-size:11.5pt}p{text-align:justify;margin:0 0 6px}ol{margin:0 0 6px;padding-left:22px}li{margin-bottom:3px}table{border-collapse:collapse;width:100%;margin:6px 0 10px}td,th{border:1px solid #6b7280;padding:4px 6px;vertical-align:top;font-size:10pt;text-align:left}th{background:#e8edf6}.ev-bilgi th{width:30%;background:#f1f4f9}.ev-imza{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;text-align:center;margin-top:8px}.ev-imza b,.ev-imza small,.ev-imza i{display:block}.ev-imza i{color:#888;font-style:normal;margin-top:14px;font-size:9pt}.ev-onay{text-align:center;margin-top:18px}.ev-onay b,.ev-onay span{display:block}.ev-onay span{margin-bottom:6px}.ev-sayfa{page-break-after:always;border:0}';

  var KISISEL = ['bep', 'oys', 'karne', 'veli_gorusme', 'kulup_tutanak', 'dyk', 'gezi', 'egzersiz', 'iyep', 'sinav_tutanak', 'oturma', 'yoklama', 'tutanak', 'ogrenci_gorusme', 'devamsizlik', 'kelebek'], SUNUCU = null;
  function hesap(is, d) { var fd = new FormData(); fd.append('action', 'osc_ev_hesap'); fd.append('nonce', C.nonce); fd.append('is', is); for (var k in (d || {})) fd.append(k, d[k]); return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (!j.success) throw new Error('hata'); return j.data; }); }
  function vListe() {
    if (C.uye && SUNUCU === null) { SUNUCU = []; hesap('liste').then(function (l) { SUNUCU = l || []; if (S.ekran === 'liste') vListe(); }).catch(function () {}); }
    var yerel = kayitlar(), idler = yerel.map(function (x) { return String(x.id); }), l = yerel.concat((SUNUCU || []).filter(function (x) { return idler.indexOf(String(x.id)) < 0; }));
    cerceve('<section class="ev-kart"><h2>📁 Evraklarım</h2><p class="ev-not">' + (C.uye ? 'Hesabınıza ve bu tarayıcıya kaydettiğiniz evraklar. Öğrenci bilgisi içeren evraklar (BEP, OYS, karne görüşleri vb.) yalnızca bu tarayıcıda saklanır.' : 'Bu tarayıcıda kaydettiğiniz evraklar. Üye olursanız evraklarınız hesabınıza kaydedilir ve her cihazdan açılır.') + '</p>' + (l.length ? '<div class="ev-liste">' + l.map(function (x) { var t = tur(x.tur) || { ik: '📄' }; return '<div class="ev-li"><span>' + t.ik + '</span><div><b>' + esc(x.ad) + '</b><small>' + esc(x.tarih) + '</small></div><button class="ev-btn" data-ac="' + x.id + '">Aç</button><button class="ev-btn hafif" data-sil="' + x.id + '">🗑</button></div>'; }).join('') + '</div>' : '<div class="ev-bos">Henüz kaydettiğiniz evrak yok.</div>') + '</section>');
    kok.querySelectorAll('[data-ac]').forEach(function (b) { b.onclick = function () { var x = l.filter(function (k) { return String(k.id) === b.dataset.ac; })[0]; if (!x) return; S = { ekran: 'onizle', tur: x.tur, veri: x.veri, ic: x.ic, id: x.id }; sakla(); ciz(); }; });
    kok.querySelectorAll('[data-sil]').forEach(function (b) { b.onclick = function () { if (!confirm('Silinsin mi?')) return; kayitlar(kayitlar().filter(function (k) { return String(k.id) !== b.dataset.sil; })); if (C.uye) { SUNUCU = (SUNUCU || []).filter(function (k) { return String(k.id) !== b.dataset.sil; }); hesap('sil', { id: b.dataset.sil }).catch(function () {}); } vListe(); }; });
  }
  ciz();
  }
  window.OSC_EV_BASLAT = BASLAT;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', BASLAT); else BASLAT();
})();
