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
  var TOPLANTI = { donem_basi: 'Eğitim-öğretim yılı başı', donem_ara: '1. dönem ara (değerlendirme)', ikinci_donem: '2. dönem başı', yil_sonu: 'Eğitim-öğretim yılı sonu' };
  var TURLER = [
    { id: 'zumre', ad: 'Zümre öğretmenler kurulu tutanağı', ik: '👥', ac: 'Ders zümresi toplantısı: gündem, görüşmeler, kararlar ve imzalar', alan: ORTAK.concat([
      { k: 'ders', ad: 'Ders / zümre', tip: 'secim', l: DERSLER }, { k: 'siniflar', ad: 'Sınıf seviyeleri', ph: '5, 6, 7 ve 8. sınıflar' },
      { k: 'toplanti', ad: 'Toplantı', tip: 'secim', l: TOPLANTI, v: 'donem_basi' }, { k: 'tarih', ad: 'Tarih', v: bugun }, { k: 'saat', ad: 'Saat', v: '15:00' }, { k: 'yer', ad: 'Yer', v: 'Öğretmenler odası' },
      { k: 'baskan', ad: 'Zümre başkanı', ph: 'Ad Soyad' }, { k: 'uyeler', ad: 'Zümre öğretmenleri (her satıra bir kişi)', tip: 'uzun', ph: 'Ayşe Yılmaz\nMehmet Kaya' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }, { k: 'not', ad: 'Eklemek istedikleriniz (isteğe bağlı)', tip: 'uzun', ph: 'Ör. Okulumuzda laboratuvar yenilendi; 8. sınıflar için ek kaynak kullanılacak…' }]) },
    { id: 'sok', ad: 'Şube öğretmenler kurulu (ŞÖK) tutanağı', ik: '🏫', ac: 'Bir şubenin öğretmenlerinin dönem toplantısı', alan: ORTAK.concat([
      { k: 'sube', ad: 'Sınıf / şube', ph: '7/A' }, { k: 'rehber', ad: 'Sınıf rehber öğretmeni', ph: 'Ad Soyad' }, { k: 'toplanti', ad: 'Toplantı', tip: 'secim', l: TOPLANTI, v: 'donem_basi' },
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
    { id: 'kulup_plan', ad: 'Öğrenci kulübü yıllık çalışma planı', ik: '📅', yatay: true, ac: 'Yeni kulüp yapısına göre (48 kulüp, 5 tema) aylara göre etkinlik planı', alan: ORTAK.concat([
      { k: 'kulup', ad: 'Kulüp', tip: 'kulup' }, { k: 'kademe', ad: 'Okul kademesi', tip: 'secim', l: ['İlkokul', 'Ortaokul', 'Lise'], v: 'Ortaokul' }, { k: 'danisman', ad: 'Danışman öğretmen(ler) (her satıra bir kişi)', tip: 'uzun' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }, { k: 'not', ad: 'Planlanan özel etkinlikler (isteğe bağlı)', tip: 'uzun', ph: 'Ör. Mart’ta il genelinde proje yarışmasına katılım…' }]) },
    { id: 'kulup_tutanak', ad: 'Öğrenci kulübü ilk toplantı ve seçim tutanağı', ik: '🗳️', ac: 'Kulübün kuruluş toplantısı, görev dağılımı ve üye listesi', alan: ORTAK.concat([
      { k: 'kulup', ad: 'Kulüp', tip: 'kulup' }, { k: 'tarih', ad: 'Tarih', v: bugun }, { k: 'yer', ad: 'Yer', ph: 'Konferans salonu' }, { k: 'danisman', ad: 'Danışman öğretmen(ler) (her satıra bir kişi)', tip: 'uzun' },
      { k: 'baskan', ad: 'Kulüp başkanı (öğrenci)', ph: 'Ad Soyad - Sınıf' }, { k: 'baskanyrd', ad: 'Başkan yardımcısı', ph: 'Ad Soyad - Sınıf' }, { k: 'sekreter', ad: 'Sekreter', ph: 'Ad Soyad - Sınıf' }, { k: 'uyeler', ad: 'Üye öğrenciler (her satıra “Ad Soyad - Sınıf - No”)', tip: 'uzun' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) },
    { id: 'kulup_rapor', ad: 'Öğrenci kulübü dönem sonu çalışma raporu', ik: '📊', ac: 'Dönem boyunca yapılan kulüp çalışmalarının raporu', alan: ORTAK.concat([
      { k: 'kulup', ad: 'Kulüp', tip: 'kulup' }, { k: 'donem', ad: 'Dönem', tip: 'secim', l: ['1. dönem', '2. dönem'], v: '1. dönem' }, { k: 'danisman', ad: 'Danışman öğretmen(ler)', tip: 'uzun' }, { k: 'uye', ad: 'Üye sayısı', ph: '24' },
      { k: 'yapilanlar', ad: 'Yapılan çalışmalar (her satıra bir etkinlik; boş bırakırsanız örnek içerik oluşturulur)', tip: 'uzun' }, { k: 'mudur', ad: 'Okul müdürü', ph: 'Ad Soyad' }]) }
  ];
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
  function varsayilan(id, v) { return id === 'zumre' ? zumreIcerik(v) : id === 'sok' ? sokIcerik(v) : id === 'veli' ? veliIcerik(v) : id === 'bep' ? bepIcerik(v) : id === 'kulup_plan' ? kulupPlanIcerik(v) : id === 'kulup_rapor' ? kulupRaporIcerik(v) : {}; }

  /* ---------------- içerik → belge blokları ---------------- */
  function bloklar(id, v, ic) {
    var Y = v.yil || yil(), O = trUp(v.okul || '………………………… OKULU'), b = [];
    var TB = function (s) { b.push({ t: 'baslik', s: s }); };
    if (id === 'zumre' || id === 'sok' || id === 'veli') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI'); TB(O);
      TB(id === 'zumre' ? trUp((v.ders || '') + ' DERSİ ' + (TOPLANTI[v.toplanti] || '') + ' ZÜMRE ÖĞRETMENLER KURULU TOPLANTI TUTANAĞI') : id === 'sok' ? trUp((v.sube || '') + ' ŞUBE ÖĞRETMENLER KURULU TOPLANTI TUTANAĞI (' + (TOPLANTI[v.toplanti] || '') + ')') : trUp((v.sube || '') + ' SINIFI VELİ TOPLANTISI TUTANAĞI (' + (TOPLANTI[v.toplanti] || '') + ')'));
      b.push({ t: 'bilgi', satirlar: [['Toplantı tarihi', v.tarih || ''], ['Toplantı saati', v.saat || ''], ['Toplantı yeri', v.yer || '']].concat(id === 'zumre' ? [['Zümre başkanı', v.baskan || ''], ['Sınıf seviyeleri', v.siniflar || '']] : id === 'sok' ? [['Sınıf rehber öğretmeni', v.rehber || ''], ['Öğrenci sayısı', v.mevcut || '']] : [['Sınıf öğretmeni', v.ogretmen || ''], ['Katılan veli sayısı', v.katilim || '']]) });
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
      b.push({ t: 'bilgi', satirlar: [['Öğrencinin adı soyadı', v.ogrenci || ''], ['Sınıfı / şubesi', v.sinif || ''], ['Eğitsel tanı / ihtiyaç', v.tani || ''], ['Ders', v.ders || ''], ['BEP başlama – bitiş tarihi', (v.baslama || '') + ' – ' + (v.bitis || '')]] });
      b.push({ t: 'h', s: 'ÖĞRENCİNİN PERFORMANS DÜZEYİ' }); b.push({ t: 'p', s: ic.performans || '', yol: 'performans' });
      b.push({ t: 'h', s: 'UZUN VE KISA DÖNEMLİ AMAÇLAR' });
      b.push({ t: 'tablo', bas: ['Uzun dönemli amaç', 'Kısa dönemli amaçlar', 'Yöntem ve teknikler', 'Materyaller', 'Değerlendirme', 'Başlama / bitiş'], gen: [18, 28, 16, 14, 12, 12], sat: (ic.hedefler || []).map(function (h) { return [h.uda, (h.kda || []).map(function (x, j) { return (j + 1) + '. ' + x; }).join('\n'), h.yontem || '', h.materyal || '', h.degerlendirme || '', (v.baslama || '') + '\n' + (v.bitis || '')]; }), yol: 'hedefler' });
      b.push({ t: 'h', s: 'ÖLÇME VE DEĞERLENDİRMEDE YAPILACAK UYARLAMALAR' }); b.push({ t: 'liste', l: ic.uyarlamalar || [], yol: 'uyarlamalar' });
      b.push({ t: 'h', s: 'AİLE KATILIMI' }); b.push({ t: 'liste', l: ic.aile || [], yol: 'aile' });
      b.push({ t: 'imza', baslik: 'BEP GELİŞTİRME BİRİMİ', kisiler: (satirlar(v.birim).length ? satirlar(v.birim) : ['Okul müdürü / müdür yardımcısı - ', 'Rehber öğretmen - ', 'Sınıf öğretmeni - ', 'Ders öğretmeni - ', 'Veli - ']).map(function (a) { var p = a.split(/\s*[-–]\s*/); return p.length > 1 ? { ad: p.slice(1).join(' - '), gorev: p[0] } : { ad: a, gorev: '' }; }), sutun: 3 });
      b.push({ t: 'onay', ad: v.mudur, gorev: 'Okul Müdürü', tarih: v.baslama });
    } else if (id === 'kulup_plan') {
      TB(Y + ' EĞİTİM-ÖĞRETİM YILI ' + O); TB(trUp((v.kulup || 'ÖĞRENCİ KULÜBÜ') + ' YILLIK ÇALIŞMA PLANI'));
      b.push({ t: 'bilgi', satirlar: [['Tema', ic.tema || kulupTema(v.kulup)], ['Okul kademesi', v.kademe || ''], ['Danışman öğretmen(ler)', satirlar(v.danisman).join(', ')]] });
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
    }
    return b;
  }

  /* ---------------- durum ---------------- */
  var S = ls('osc_ev_taslak') || { ekran: 'sec', tur: '', veri: {}, ic: null, id: 0 };
  var ORT = ls('osc_ev_ortak') || {};
  function sakla() { ls('osc_ev_taslak', S); }
  function kayitlar(v) { if (v === undefined) return ls('osc_ev_kayitlar') || []; ls('osc_ev_kayitlar', v.slice(0, 80)); }

  function cerceve(ic) {
    kok.innerHTML = '<header class="ev-hero"><div><span class="ev-et">Öğretmen evrakları</span><h1>Evrakınız <em>dakikalar içinde</em> hazır</h1><p>Zümre, ŞÖK ve veli toplantısı tutanakları, BEP planı ve yeni kulüp yapısına göre kulüp evrakları. Bilgileri doldurun, taslağı düzenleyin, Word olarak indirin.</p></div><nav><button data-ek="sec" class="' + (S.ekran !== 'liste' ? 'on' : '') + '">✨ Yeni evrak</button><button data-ek="liste" class="' + (S.ekran === 'liste' ? 'on' : '') + '">📁 Evraklarım</button></nav></header>' + ic;
    kok.querySelectorAll('[data-ek]').forEach(function (b) { b.onclick = function () { S.ekran = b.dataset.ek; sakla(); ciz(); }; });
  }
  function ciz() { if (S.ekran === 'liste') return vListe(); if (S.ekran === 'form' && tur(S.tur)) return vForm(); if (S.ekran === 'onizle' && tur(S.tur) && S.ic) return vOnizle(); S.ekran = 'sec'; vSec(); }

  function vSec() {
    cerceve('<section class="ev-kart"><h2>Hangi evrakı hazırlayacaksınız?</h2><div class="ev-turler">' + TURLER.map(function (t) { return '<button data-tur="' + t.id + '"><span>' + t.ik + '</span><b>' + esc(t.ad) + '</b><small>' + esc(t.ac) + '</small></button>'; }).join('') + '</div><p class="ev-not">🔒 Evraklarınız yalnızca bu tarayıcıda saklanır; öğrenci bilgileri sunucumuza kaydedilmez.</p></section>');
    kok.querySelectorAll('[data-tur]').forEach(function (b) { b.onclick = function () { S.tur = b.dataset.tur; S.veri = {}; S.ic = null; S.id = 0; S.ekran = 'form'; sakla(); ciz(); window.scrollTo({ top: kok.getBoundingClientRect().top + window.pageYOffset - 20, behavior: 'smooth' }); }; });
  }
  function alanHtml(a) {
    var v = S.veri[a.k]; if (v === undefined) { v = ['okul', 'il', 'mudur'].indexOf(a.k) >= 0 && ORT[a.k] ? ORT[a.k] : a.k === 'okul' && C.okul ? C.okul : (typeof a.v === 'function' ? a.v() : (a.v || '')); S.veri[a.k] = v; }
    var g = '';
    if (a.tip === 'secim') { var l = Array.isArray(a.l) ? a.l.map(function (x) { return [x, x]; }) : Object.keys(a.l).map(function (k) { return [k, a.l[k]]; }); g = '<select data-a="' + a.k + '"><option value="">Seçin…</option>' + l.map(function (x) { return '<option value="' + esc(x[0]) + '"' + (String(v) === x[0] ? ' selected' : '') + '>' + esc(x[1]) + '</option>'; }).join('') + '</select>'; }
    else if (a.tip === 'kulup') g = '<select data-a="' + a.k + '"><option value="">Kulüp seçin…</option>' + Object.keys(KULUP).map(function (t) { return '<optgroup label="' + esc(t) + '">' + KULUP[t].map(function (x) { return '<option' + (v === x ? ' selected' : '') + '>' + esc(x) + '</option>'; }).join('') + '</optgroup>'; }).join('') + '</select>';
    else if (a.tip === 'uzun') g = '<textarea data-a="' + a.k + '" rows="4" placeholder="' + esc(a.ph || '') + '">' + esc(v) + '</textarea>';
    else g = '<input data-a="' + a.k + '" value="' + esc(v) + '" placeholder="' + esc(a.ph || '') + '">';
    return '<label class="' + (a.tip === 'uzun' ? 'gen' : '') + '"><span>' + esc(a.ad) + '</span>' + g + '</label>';
  }
  function vForm() {
    var t = tur(S.tur);
    cerceve('<section class="ev-kart"><div class="ev-ust"><button class="ev-btn hafif" id="ev-geri">← Evrak türleri</button><h2>' + t.ik + ' ' + esc(t.ad) + '</h2></div><div class="ev-form">' + t.alan.map(alanHtml).join('') + '</div>' +
      '<div class="ev-cubuk"><span>' + (C.yz ? '🤖 Yapay zekâ, bilgilerinize göre gündemi, görüşmeleri ve kararları özgün olarak yazar.' : 'Taslak içerik bilgilerinize göre otomatik oluşturulur; dilediğiniz gibi düzenleyebilirsiniz.') + '</span><div>' + (C.yz ? '<button class="ev-btn" id="ev-hizli">Hazır taslakla oluştur</button><button class="ev-btn ana" id="ev-yz">🤖 Yapay zekâ ile oluştur</button>' : '<button class="ev-btn ana" id="ev-hizli">Evrakı oluştur →</button>') + '</div></div></section>');
    kok.querySelectorAll('[data-a]').forEach(function (e) { e.oninput = e.onchange = function () { S.veri[e.dataset.a] = e.value; if (['okul', 'il', 'mudur'].indexOf(e.dataset.a) >= 0) { ORT[e.dataset.a] = e.value; ls('osc_ev_ortak', ORT); } sakla(); }; });
    document.getElementById('ev-geri').onclick = function () { S.ekran = 'sec'; sakla(); ciz(); };
    var eksik = function () { var z = { zumre: ['okul', 'ders'], sok: ['okul', 'sube'], veli: ['okul', 'sube'], bep: ['okul', 'ders'], kulup_plan: ['okul', 'kulup'], kulup_tutanak: ['okul', 'kulup'], kulup_rapor: ['okul', 'kulup'] }[S.tur] || []; var e = z.filter(function (k) { return !String(S.veri[k] || '').trim(); }); if (e.length) { bildir('Lütfen doldurun: ' + e.map(function (k) { return t.alan.filter(function (a) { return a.k === k; })[0].ad; }).join(', '), 'hata'); return true; } return false; };
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
        case 'p': return '<p' + (b.yol ? ' contenteditable="true" data-yol="' + b.yol + '" data-onek="' + esc(b.onek || '') + '"' : '') + '>' + esc(b.s) + '</p>';
        case 'liste': return '<ol' + (b.numara === false ? ' class="nok"' : '') + '>' + (b.l || []).map(function (x, i) { return '<li' + (b.yol ? ' contenteditable="true" data-yol="' + b.yol + '.' + i + '"' : '') + '>' + esc(x) + '</li>'; }).join('') + '</ol>' + (b.yol ? '<button class="ev-ekle" data-ekle="' + b.yol + '">＋ madde ekle</button>' : '');
        case 'bilgi': return '<table class="ev-bilgi">' + b.satirlar.map(function (r) { return '<tr><th>' + esc(r[0]) + '</th><td>' + esc(r[1]) + '</td></tr>'; }).join('') + '</table>';
        case 'tablo': return '<table class="ev-tb' + (t.yatay ? ' yatay' : '') + '">' + (b.bas ? '<tr>' + b.bas.map(function (h) { return '<th>' + esc(h) + '</th>'; }).join('') + '</tr>' : '') + b.sat.slice(0, 40).map(function (r) { return '<tr>' + r.map(function (c) { return '<td>' + esc(c).replace(/\n/g, '<br>') + '</td>'; }).join('') + '</tr>'; }).join('') + '</table>';
        case 'imza': return (b.baslik ? '<h4>' + esc(b.baslik) + '</h4>' : '') + '<div class="ev-imza">' + (b.kisiler || []).map(function (k) { return '<div><b>' + esc(k.ad || '………………') + '</b><small>' + esc(k.gorev) + '</small><i>İmza</i></div>'; }).join('') + '</div>';
        case 'onay': return '<div class="ev-onay"><b>UYGUNDUR</b><span>' + esc(b.tarih || '…/…/20…') + '</span><b>' + esc(b.ad || '………………') + '</b><span>' + esc(b.gorev) + '</span></div>';
        case 'sayfa': return '<hr class="ev-sayfa">';
      }
      return '';
    }).join('');
    cerceve('<section class="ev-izgara"><div class="ev-kagit' + (t.yatay ? ' yatay' : '') + '">' + html + '</div><aside class="ev-yan"><div class="ev-kart"><h3>' + t.ik + ' ' + esc(t.ad) + '</h3><p class="ev-not">Kâğıt üzerindeki gündem, görüşme, karar ve plan metinlerine tıklayıp düzenleyebilirsiniz.</p>' +
      '<button class="ev-btn ana tam" id="ev-word">⬇ Word (.docx) indir</button><button class="ev-btn tam" id="ev-yazdir">🖨 Yazdır / PDF</button><button class="ev-btn tam" id="ev-kaydet">💾 Evraklarıma kaydet</button><button class="ev-btn hafif tam" id="ev-duzen">✎ Bilgileri düzenle</button><button class="ev-btn hafif tam" id="ev-yeni">＋ Yeni evrak</button></div></aside></section>');
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
  function dosyaAdi() { var t = tur(S.tur), v = S.veri; return (t.ad.replace(/\s*\(.*?\)/g, '') + ' ' + (v.ders || v.sube || v.kulup || v.sinif || '') + ' ' + (v.yil || '')).replace(/\s+/g, ' ').trim(); }
  function jszip() { return window.JSZip ? Promise.resolve() : new Promise(function (ok, no) { var s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js'; s.onload = ok; s.onerror = function () { no(new Error('Word oluşturucu yüklenemedi.')); }; document.head.appendChild(s); }); }
  function wordIndir() {
    var t = tur(S.tur);
    jszip().then(function () { return window.OSC_DOCX_EVRAK({ baslik: dosyaAdi(), site: C.site, yatay: !!t.yatay, bloklar: bloklar(S.tur, S.veri, S.ic) }); }).then(function (blob) {
      var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = dosyaAdi().replace(/[\\\/:*?"<>|]+/g, '-') + '.docx'; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
    }).catch(function (e) { bildir(e.message, 'hata'); });
  }
  function yazdir() {
    var t = tur(S.tur), k = kok.querySelector('.ev-kagit'); if (!k) return;
    var w = window.open('', '_blank'); if (!w) return bildir('Açılır pencere engellendi.', 'hata');
    w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>' + esc(dosyaAdi()) + '</title><style>@page{size:A4 ' + (t.yatay ? 'landscape' : 'portrait') + ';margin:14mm}body{font:11pt Calibri,Arial,sans-serif;color:#111}' + EV_BASKI + '</style></head><body><div class="ev-kagit">' + k.innerHTML.replace(/contenteditable="true"/g, '').replace(/<button[^>]*>.*?<\/button>/g, '') + '</div><script>onload=function(){print()}<\/script></body></html>');
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
