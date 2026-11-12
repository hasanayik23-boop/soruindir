/* =====================================================================
 * Evrak Hazırla: zümre / veli / ŞÖK tutanakları, BEP planı, öğrenci
 * kulübü evrakları. Taslak metinler bize özgüdür; isteğe bağlı olarak
 * yapay zekâ ile kişiselleştirilir. Evraklar yalnızca bu tarayıcıda
 * saklanır (öğrenci bilgileri sunucuya kaydedilmez).
 * ===================================================================== */
(function () {
  function BASLAT() {
  var C = window.OSC_EV || {}, kok = document.getElementById('osc-ev'); if (!kok) return;
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function ls(k, v) { try { if (v === undefined) return JSON.parse(localStorage.getItem(k) || 'null'); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, JSON.stringify(v)); } catch (e) { return null; } }
  function trUp(s) { return String(s || '').replace(/i/g, 'İ').replace(/ı/g, 'I').toUpperCase(); }
  function bugun() { var d = new Date(); return ('0' + d.getDate()).slice(-2) + '.' + ('0' + (d.getMonth() + 1)).slice(-2) + '.' + d.getFullYear(); }
  function yil() { var d = new Date(), y = d.getFullYear(); return d.getMonth() >= 7 ? y + '-' + (y + 1) : (y - 1) + '-' + y; }
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
    { id: 'oys', ad: 'OYS puan çizelgesi', ik: '🧮', yzYok: true, yatay: true, ozel: 'oys', ac: 'MEB senaryosuna göre ortak yazılı soru bazlı puan çizelgesi; e-Okul’a not girişi için', alan: ORTAK.concat([
      { k: 'sube', ad: 'Sınıf / şube', ph: '7/A' }, { k: 'tarih', ad: 'Sınav tarihi', ph: '…/…/20…' }, { k: 'ogretmen', ad: 'Ders öğretmeni', ph: 'Ad Soyad' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) }
  ];
  if (Array.isArray(C.turler)) TURLER = TURLER.filter(function (t) { return C.turler.indexOf(t.id) >= 0; });
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
  function varsayilan(id, v) { if (id === 'belirli_gun' && v.program !== 'yillik') return kutlamaIcerik(v); if (id === 'ogr_kurul') return kurulIcerik(v); if (id === 'egzersiz') return egzersizIcerik(v); if (id === 'gezi') return geziIcerik(v); if (id === 'kulup_aylik') return kulupAylikIcerik(v); if (id === 'dilekce') return dilekceIcerik(v); if (id === 'belirli_gun' || id === 'oys') return { bos: 1 }; return id === 'zumre' ? zumreIcerik(v) : id === 'sok' ? sokIcerik(v) : id === 'veli' ? veliIcerik(v) : id === 'bep' ? bepIcerik(v) : id === 'kulup_plan' ? kulupPlanIcerik(v) : id === 'kulup_rapor' ? kulupRaporIcerik(v) : {}; }

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
      b.push({ t: 'h', s: 'AİLE KATILIMI' }); b.push({ t: 'liste', l: ic.aile || [], yol: 'aile' });
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
  /* ortak bilgiler (okul, müdür, öğretmen…): yalnızca üyelerde hesaba kaydedilir ve tüm evraklara işlenir */
  var ORTAK_K = { okul: 'okul', il: 'il', mudur: 'mudur', ogretmen: 'ogretmen', adsoyad: 'ogretmen', gorev: 'gorev', kademe: 'kademe' };
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
  function ciz() { if (S.ekran === 'liste') return vListe(); if (S.ekran === 'form' && tur(S.tur)) return vForm(); if (S.ekran === 'onizle' && tur(S.tur) && S.ic) return vOnizle(); if (S.ekran !== 'kulup') S.ekran = 'sec'; vSec(); }

  function vSec() {
    var kul = TURLER.filter(function (t) { return t.grup === 'kulup'; }), alt = S.ekran === 'kulup' && kul.length;
    var kart = function (t) { return '<button data-tur="' + t.id + '"><span>' + t.ik + '</span><b>' + esc(t.ad) + '</b><small>' + esc(t.ac) + '</small></button>'; };
    var liste = alt ? kul.map(kart).join('') : TURLER.filter(function (t) { return t.grup !== 'kulup'; }).map(kart).join('') + (kul.length ? '<button data-grup="kulup"><span>🎭</span><b>Sosyal kulüp evrakları</b><small>' + kul.length + ' evrak: yıllık plan, ilk toplantı tutanağı, aylık ve dönem sonu raporlar (48 kulüp, 5 tema)</small></button>' : '');
    cerceve('<section class="ev-kart">' + (alt ? '<div class="ev-ust"><button class="ev-btn hafif" id="ev-geri">← Tüm evraklar</button><h2>🎭 Sosyal kulüp evrakları</h2></div>' : '<h2>Hangi evrakı hazırlayacaksınız?</h2>') + '<div class="ev-turler">' + (liste || '<div class="ev-bos">Şu an kullanılabilir evrak yok.</div>') + '</div><p class="ev-not">🔒 Evraklarınız yalnızca bu tarayıcıda saklanır; öğrenci bilgileri sunucumuza kaydedilmez.</p></section>');
    kok.querySelectorAll('[data-grup]').forEach(function (b) { b.onclick = function () { S.ekran = 'kulup'; sakla(); ciz(); }; });
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
    cerceve('<section class="ev-kart"><div class="ev-ust"><button class="ev-btn hafif" id="ev-geri">← Evrak türleri</button><h2>' + t.ik + ' ' + esc(t.ad) + '</h2></div>' + (C.uye ? '<p class="ev-uye ok">✓ Okul, müdür ve kendi bilgileriniz hesabınızdan otomatik dolduruldu. Burada değiştirirseniz tüm evrak ve yazılılarınızda güncellenir.</p>' : '<p class="ev-uye">💡 <b>Ücretsiz üye olursanız</b> okul adı, il/ilçe, müdür adı ve adınız gibi bilgileri bir kez yazmanız yeterli: tüm evraklarınıza ve yazılılarınıza otomatik işlenir' + (C.yzUye ? '; metinleri yapay zekâya da yazdırabilirsiniz' : '') + '. <a href="' + esc(C.giris || '#') + '">Üye ol / giriş yap →</a></p>') + '<div class="ev-form">' + t.alan.filter(function (a) { return !a.kosul || a.kosul(S.veri); }).map(alanHtml).join('') + '</div>' + (t.ozel === 'oys' ? oyFormHtml() : '') +
      '<div class="ev-cubuk"><span>' + (YZ ? '🤖 Yapay zekâ, bilgilerinize göre gündemi, görüşmeleri ve kararları özgün olarak yazar.' : 'Taslak içerik bilgilerinize göre otomatik oluşturulur; dilediğiniz gibi düzenleyebilirsiniz.') + '</span><div>' + (YZ ? '<button class="ev-btn" id="ev-hizli">Hazır taslakla oluştur</button><button class="ev-btn ana" id="ev-yz">🤖 Yapay zekâ ile oluştur</button>' : '<button class="ev-btn ana" id="ev-hizli">Evrakı oluştur →</button>') + '</div></div></section>');
    if (t.ozel === 'oys') oyBagla();
    kok.querySelectorAll('[data-a]').forEach(function (e) { e.oninput = e.onchange = function (ev) { S.veri[e.dataset.a] = e.value; if (ev.type === 'change' && (t.alan.filter(function (a) { return a.k === e.dataset.a; })[0] || {}).yenile) { sakla(); return vForm(); } ortakKaydet(e.dataset.a, e.value); sakla(); }; });
    document.getElementById('ev-geri').onclick = function () { S.ekran = t.grup === 'kulup' ? 'kulup' : 'sec'; sakla(); ciz(); };
    kok.querySelectorAll('[data-coklu]').forEach(function (d) { var k = d.dataset.coklu, guncel = function () { S.veri[k] = [].map.call(d.querySelectorAll('input:checked'), function (i) { return i.value; }); sakla(); }; d.querySelectorAll('input').forEach(function (i) { i.onchange = guncel; }); d.querySelectorAll('[data-hep]').forEach(function (b) { b.onclick = function () { d.querySelectorAll('input').forEach(function (i) { i.checked = b.dataset.hep === '1'; }); guncel(); }; }); });
    rkKoy('form', kok);
    var eksik = function () { var z = { zumre: ['okul', 'ders'], sok: ['okul', 'sube'], veli: ['okul', 'sube'], bep: ['okul', 'ders'], kulup_plan: ['okul', 'kulup'], kulup_tutanak: ['okul', 'kulup'], kulup_rapor: ['okul', 'kulup'], kulup_aylik: ['okul', 'kulup'], ogr_kurul: ['okul'], egzersiz: ['okul', 'egz'], belirli_gun: ['okul'], gezi: ['okul', 'gezi'], dilekce: ['makam', 'adsoyad'], oys: ['okul'] }[S.tur] || []; if (S.tur === 'oys' && !oyDurum(S.veri).sorular.length) { bildir('Önce sınıf, ders ve senaryo seçin (ya da “Soru ekle” ile soruları kendiniz girin).', 'hata'); return true; } var e = z.filter(function (k) { return !String(S.veri[k] || '').trim(); }); if (e.length) { bildir('Lütfen doldurun: ' + e.map(function (k) { return (t.alan.filter(function (a) { return a.k === k; })[0] || { ad: k }).ad; }).join(', '), 'hata'); return true; } return false; };
    document.getElementById('ev-hizli').onclick = function () { if (eksik()) return; S.ic = varsayilan(S.tur, S.veri); S.ekran = 'onizle'; sakla(); ciz(); };
    var yb = document.getElementById('ev-yz'); if (yb) yb.onclick = function () {
      if (eksik()) return; yb.disabled = true; yb.innerHTML = '<span class="ev-don"></span> Yazılıyor…';
      sunucu('yz', { tur: S.tur, veri: S.tur === 'bep' ? Object.assign({}, S.veri, { ogrenci: '', birim: '' }) : S.veri }).then(function (ic) {
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
      '<button class="ev-btn ana tam" id="ev-word">⬇ Word (.docx) indir</button><button class="ev-btn tam" id="ev-yazdir">🖨 Yazdır / PDF</button><button class="ev-btn tam" id="ev-kaydet">💾 Evraklarıma kaydet</button><button class="ev-btn hafif tam" id="ev-duzen">✎ Bilgileri düzenle</button><button class="ev-btn hafif tam" id="ev-yeni">＋ Yeni evrak</button></div></aside></section>');
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
    document.getElementById('ev-kaydet').onclick = function () { var l = kayitlar(), id = S.id || Date.now(); l = l.filter(function (x) { return x.id !== id; }); l.unshift({ id: id, tur: S.tur, ad: dosyaAdi(), tarih: bugun(), veri: S.veri, ic: S.ic }); kayitlar(l); S.id = id; sakla(); bildir('Evraklarım’a kaydedildi.', 'ok'); };
    document.getElementById('ev-duzen').onclick = function () { S.ekran = 'form'; sakla(); ciz(); };
    document.getElementById('ev-yeni').onclick = function () { S = { ekran: 'sec', tur: '', veri: {}, ic: null, id: 0 }; sakla(); ciz(); };
  }
  function dosyaAdi() { var t = tur(S.tur), v = S.veri; return (t.ad.replace(/\s*\(.*?\)/g, '') + ' ' + (v.gun && v.program !== 'yillik' ? v.gun + ' ' : '') + (v.ders || (v.oys && v.oys.ders) || v.sube || v.kulup || v.sinif || '') + ' ' + (v.yil || '')).replace(/\s+/g, ' ').trim(); }
  function jszip() { return window.JSZip ? Promise.resolve() : new Promise(function (ok, no) { var s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js'; s.onload = ok; s.onerror = function () { no(new Error('Word oluşturucu yüklenemedi.')); }; document.head.appendChild(s); }); }
  function wordIndir() {
    var t = tur(S.tur);
    jszip().then(function () { return window.OSC_DOCX_EVRAK({ baslik: dosyaAdi(), site: C.site, yatay: yatayMi(t), bloklar: bloklar(S.tur, S.veri, S.ic) }); }).then(function (blob) {
      var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = dosyaAdi().replace(/[\\\/:*?"<>|]+/g, '-') + '.docx'; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
    }).catch(function (e) { bildir(e.message, 'hata'); });
  }
  function yazdir() {
    var t = tur(S.tur), k = kok.querySelector('.ev-kagit'); if (!k) return;
    var w = window.open('', '_blank'); if (!w) return bildir('Açılır pencere engellendi.', 'hata');
    w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>' + esc(dosyaAdi()) + '</title><style>@page{size:A4 ' + (yatayMi(t) ? 'landscape' : 'portrait') + ';margin:14mm}body{font:11pt Calibri,Arial,sans-serif;color:#111}' + EV_BASKI + '</style></head><body><div class="ev-kagit">' + k.innerHTML.replace(/contenteditable="true"/g, '').replace(/<button[^>]*>.*?<\/button>/g, '') + '</div><script>onload=function(){print()}<\/script></body></html>');
    w.document.close();
  }
  var EV_BASKI = '.ev-b{text-align:center;font-weight:700;margin:0 0 3px}.ev-alt{text-align:center;margin-bottom:8px}h4{color:#1e3a8a;margin:14px 0 6px;font-size:11.5pt}p{text-align:justify;margin:0 0 6px}ol{margin:0 0 6px;padding-left:22px}li{margin-bottom:3px}table{border-collapse:collapse;width:100%;margin:6px 0 10px}td,th{border:1px solid #6b7280;padding:4px 6px;vertical-align:top;font-size:10pt;text-align:left}th{background:#e8edf6}.ev-bilgi th{width:30%;background:#f1f4f9}.ev-imza{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;text-align:center;margin-top:8px}.ev-imza b,.ev-imza small,.ev-imza i{display:block}.ev-imza i{color:#888;font-style:normal;margin-top:14px;font-size:9pt}.ev-onay{text-align:center;margin-top:18px}.ev-onay b,.ev-onay span{display:block}.ev-onay span{margin-bottom:6px}.ev-sayfa{page-break-after:always;border:0}';

  function vListe() {
    var l = kayitlar();
    cerceve('<section class="ev-kart"><h2>📁 Evraklarım</h2><p class="ev-not">Bu tarayıcıda kaydettiğiniz evraklar.</p>' + (l.length ? '<div class="ev-liste">' + l.map(function (x) { var t = tur(x.tur) || { ik: '📄' }; return '<div class="ev-li"><span>' + t.ik + '</span><div><b>' + esc(x.ad) + '</b><small>' + esc(x.tarih) + '</small></div><button class="ev-btn" data-ac="' + x.id + '">Aç</button><button class="ev-btn hafif" data-sil="' + x.id + '">🗑</button></div>'; }).join('') + '</div>' : '<div class="ev-bos">Henüz kaydettiğiniz evrak yok.</div>') + '</section>');
    kok.querySelectorAll('[data-ac]').forEach(function (b) { b.onclick = function () { var x = kayitlar().filter(function (k) { return String(k.id) === b.dataset.ac; })[0]; if (!x) return; S = { ekran: 'onizle', tur: x.tur, veri: x.veri, ic: x.ic, id: x.id }; sakla(); ciz(); }; });
    kok.querySelectorAll('[data-sil]').forEach(function (b) { b.onclick = function () { if (!confirm('Silinsin mi?')) return; kayitlar(kayitlar().filter(function (k) { return String(k.id) !== b.dataset.sil; })); vListe(); }; });
  }
  ciz();
  }
  window.OSC_EV_BASLAT = BASLAT;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', BASLAT); else BASLAT();
})();
