"""MEB (meslek.meb.gov.tr) MTAL çerçeve öğretim programı metinlerinden yıllık plan kütüphanesi kayıtları üretir.
Girdi: {pdf_yolu: metin} JSON. Çıktı: meb2.json biçiminde liste (tur, sinif, ders, dosya, alan, haftalar)."""
import json, re, sys, unicodedata

HAFTA = 36
TUR = 'Mesleki ve Teknik Anadolu Lisesi'

def tr_lower(w): return w.replace('I', 'ı').replace('İ', 'i').lower()
def tr_cap(w): return (('İ' if w[0] == 'i' else 'I' if w[0] == 'ı' else w[0].upper()) + w[1:]) if w else w
KISALT = {'AC', 'DC', 'RF', 'LED', 'PCB', 'SMD', 'ABC', 'TS', 'EN', 'İŞ', 'BİM', 'HMI', 'SCADA', 'ERP', 'CRM', 'UAV', 'İHA', 'CNC', 'PLC', 'CAD', 'CAM', 'SQL', 'HTML', 'TV', 'II', 'III', 'IV', 'VI', 'BT', 'İSG', 'ISO', 'GPS', 'HACCP', 'CBS', 'LPG', 'MIG', 'MAG', 'TIG', 'AR', 'VR', 'IOT', 'IoT', '3D'}
def tr_title(s):
    out = []
    for i, w in enumerate(s.split()):
        on, cek, son = re.match(r'^([(\[]*)(.*?)([)\],.:;]*)$', w).groups()
        if cek.upper() in KISALT or re.fullmatch(r'[0-9]+[A-Z]?', cek): out.append(on + cek + son); continue
        lw = tr_lower(cek)
        if i and lw in ('ve', 'ile', 'veya', 'için', 'de', 'da'): out.append(on + lw + son); continue
        out.append(on + '-'.join(tr_cap(x) if (k == 0 or len(x) > 1) else x for k, x in enumerate(lw.split('-'))) + son)
    return ' '.join(out)

def temizle(s):
    s = s.replace('\f', '\n')
    satir = []
    for l in s.split('\n'):
        l = l.strip()
        if not l: continue
        if re.match(r'^\d*\s*[A-ZÇĞİÖŞÜ\- ]+ALANI\s+ÇERÇEVE ÖĞRETİM PROGRAMI', l):  # sayfa başlığı
            l = re.sub(r'^\d*\s*[A-ZÇĞİÖŞÜ\- ]+ALANI\s+ÇERÇEVE ÖĞRETİM PROGRAMI', '', l).strip()
            if not l: continue
        if re.fullmatch(r'\d{1,3}', l): continue
        satir.append(l)
    return satir

def cizelge(satirlar):
    """haftalık ders çizelgelerinden: DERS ADI -> {sınıf: saat}"""
    d = {}; icinde = False; onceki = ''; bes = False
    for l in satirlar:
        if 'KATEGORİLERİ' in l: icinde = True; onceki = ''; bes = False; continue
        if not icinde: continue
        if 'AMP ATP' in l or l in ('AMP', 'ATP'): bes = True; continue
        if l.startswith('NOT:') or l.startswith('TOPLAM DERS'): icinde = False; continue
        m = re.match(r'^(.*?)\s*((?:\d{1,2}|-)(?:\s+(?:\d{1,2}|-)){2,4})$', l)
        if m and m.group(1).strip() and not re.fullmatch(r'[\d\s\.]*SINIF.*', m.group(1)):
            ad = (onceki + ' ' + m.group(1)).strip() if onceki else m.group(1).strip()
            onceki = ''
            ad = re.sub(r'\s*\(\*+\)\s*', ' ', ad).strip()
            if not ad or re.search(r'TOPLAM|REHBERLİK', ad): continue
            say = m.group(2).split()
            kol = (9, 10, 11, 12, 12) if len(say) == 5 else (9, 10, 11, 12)
            for sn, x in zip(kol, say):
                if x != '-': d.setdefault(ad, {}).setdefault(sn, int(x))
        elif re.fullmatch(r'[A-ZÇĞİÖŞÜÂ0-9 ,\-/()\.\*]+', l) and not re.search(r'DERSLER$|SINIF$|^MESLEK$|^ORTAK$|^\d+\.$|^DERS$|KATEGORİ', l):
            onceki = (onceki + ' ' + l).strip() if onceki else l
        else:
            onceki = ''
    return d

def dersler(satirlar):
    """ders tanımları: [{ad, saat, birimler:[{ad, ciktilar:[...], icerik:[...]}]}]"""
    out = []; cur = None; bir = None; mod = None
    i = 0; n = len(satirlar)
    def yeni_ders(ad):
        nonlocal cur, bir
        cur = {'ad': ad, 'saat': 0, 'birimler': []}; out.append(cur); bir = None
    while i < n:
        l = satirlar[i]
        m = re.fullmatch(r'([A-ZÇĞİÖŞÜÂ0-9 ,\-/()\.\']{3,}) DERSİ', l)
        if m and i + 1 < n:
            nx = satirlar[i + 1]
            ad = m.group(1).strip()
            pv = satirlar[i - 1] if i else ''
            if (ad.startswith('(') or len(ad) < 4 or re.search(r'\b(VE|İLE)$', pv)) and re.fullmatch(r'[A-ZÇĞİÖŞÜÂ0-9 ,\-/()\.\']{3,}', pv) and not pv.endswith('DERSİ') and not re.search(r'DALI$|ÇIKTILARI$|KAZANIMLARI$|PROGRAMI$', pv):
                ad = pv + ' ' + ad
            if nx.startswith('Dersin Amacı') or nx.startswith('Bu derste'):
                yeni_ders(ad); mod = None; i += 1; continue
            if cur and cur['ad'] == ad: i += 1; continue  # sayfa devamı
            if nx.startswith('Öğrenme Birimi'):
                if not cur or cur['ad'] != ad: yeni_ders(ad)
                i += 1; continue
        if cur is None: i += 1; continue
        m = re.match(r'^(?:Haftalık )?(?:DERS SAATİ|Ders Saati)\s*:\s*(\d+)', l, re.I)
        if m: cur['saat'] = int(m.group(1)); i += 1; continue
        m = re.match(r'^Öğrenme Birimi(?:nin Adı|/Modül Adı)\s*(.*)$', l)
        if m:
            ad = m.group(1).strip()
            # ad bir sonraki satıra taşmış olabilir
            j = i + 1
            while j < n and not re.match(r'^(Öğrenme Birimi|Öğrenme Çıktısı|Kazanımları)', satirlar[j]) and len(ad) < 90 and not ad.endswith('.'):
                if satirlar[j].startswith('Öğrenme Biriminin'): break
                ad += ' ' + satirlar[j]; j += 1
            bir = {'ad': ad.strip(), 'ciktilar': [], 'icerik': [], 'grup': ''}; cur['birimler'].append(bir); mod = 'bas'; i = j; continue
        if bir is None: i += 1; continue
        if l in ('Öğrenme Biriminin', 'Kazanımları') or l.startswith('Öğrenme Biriminin Kazanımları'): mod = 'kaz'; i += 1; continue
        if l.startswith('Öğrenme Çıktısı Grubu'): mod = 'grup'; i += 1; continue
        if l.startswith('İçerik Çerçevesi'): mod = 'ic'; i += 1; continue
        if re.match(r'^(TÜRKİYE YÜZYILI MAARİF|ÖĞRENME BİRİMLERİ/MODÜLLERE|\(Öğrenme Birimi|\d+\.\d+\.|SEÇMELİ|10, 11|9\. SINIF|1[0-2]\. SINIF|[A-ZÇĞİÖŞÜ ]+ DALI$)', l):
            mod = None; i += 1; continue
        if mod == 'grup':
            bir['grup'] = (bir['grup'] + ' ' + l).strip()
            if re.search(r'(ebilme|abilme)$', l): mod = 'cik'
            i += 1; continue
        if mod in ('cik', 'kaz'):
            t = re.sub(r'^\d+\.\s*', '', l)
            if bir['ciktilar'] and (re.match(r'^\d+\.\s', l) is None and not bir['ciktilar'][-1].endswith('.')):
                bir['ciktilar'][-1] += ' ' + t
            else:
                bir['ciktilar'].append(t)
            i += 1; continue
        if mod == 'ic':
            bir['icerik'].append(l); i += 1; continue
        i += 1
    for c in out:
        for b in c['birimler']:
            b['ciktilar'] = [re.sub(r'\s+', ' ', x).strip() for x in b['ciktilar'] if len(x) > 6]
            b['icerik'] = [x for x in b['icerik'] if len(x) > 2]
        c['birimler'] = [b for b in c['birimler'] if b['ciktilar'] or b['icerik']]
    return [c for c in out if c['birimler']]

def haftalara_bol(c, saat):
    """öğrenme birimlerini 36 haftaya, kazanım sayısıyla orantılı dağıt"""
    bs = c['birimler']; agirlik = [max(1, len(b['ciktilar'])) for b in bs]; top = sum(agirlik)
    pay = [max(1, round(HAFTA * a / top)) for a in agirlik]
    while sum(pay) > HAFTA: k = pay.index(max(pay)); pay[k] -= 1
    while sum(pay) < HAFTA: k = agirlik.index(max(agirlik)); pay[k] += 1; agirlik[k] -= 0.5
    h = []; n = 1
    for b, p in zip(bs, pay):
        ck = b['ciktilar'] or b['icerik'] or [b['ad']]
        ic = b['icerik']
        for w in range(p):
            # haftanın kazanımı: kazanımları haftalara sırayla yay
            a = w * len(ck) // p; z = max(a + 1, (w + 1) * len(ck) // p)
            cikti = ' '.join(x if x.endswith('.') else x + '.' for x in ck[a:z]) if ck else ''
            konu = ''
            if ic:
                ia = w * len(ic) // p; iz = max(ia + 1, (w + 1) * len(ic) // p); konu = ', '.join(ic[ia:iz])
            else:
                konu = b['ad']
            if p > 1 and w == p - 1 and len(ck) < p: cikti = cikti or 'Öğrenme birimi genel tekrarı ve uygulama çalışmaları.'
            h.append({'n': n, 'saat': str(saat), 'unite': b['ad'], 'konu': konu, 'cikti': cikti, 'surec': b.get('grup', ''), 'olcme': 'Uygulama faaliyetleri, kontrol listesi ve dereceli puanlama anahtarı ile süreç değerlendirmesi; öğrenme birimi sonunda performansa dayalı değerlendirme.', 'sdb': '', 'deger': '', 'okur': '', 'bgh': '', 'otp': False})
            n += 1
    return h

ALAN_AD = {}
MEB_ALAN = ['Mesleki Gelişim Atölyesi', 'Adalet', 'Ayakkabı ve Saraciye Teknolojisi', 'Basım Teknolojileri', 'Bilişim Teknolojileri', 'Biyomedikal Cihaz Teknolojileri', 'Büro Yönetimi ve Yönetici Asistanlığı', 'Çocuk Gelişimi ve Eğitimi', 'Denizcilik', 'Doğu Anadolu Gastronomi ve Mutfak Sanatları', 'El Sanatları Teknolojisi', 'Elektrik-Elektronik Teknolojisi', 'Endüstriyel Kalite Kontrol', 'Endüstriyel Otomasyon Teknolojileri', 'Gazetecilik', 'Geleneksel Türk Sanatları', 'Gemi Yapımı', 'Gıda Teknolojisi', 'Grafik ve Fotoğraf', 'Güzellik Hizmetleri', 'Halkla İlişkiler', 'Harita-Tapu-Kadastro', 'Hasta ve Yaşlı Hizmetleri', 'Havacılık ve Uzay Teknolojisi', 'Hayvan Yetiştiriciliği ve Sağlığı', 'İnşaat Teknolojisi', 'İtfaiyecilik ve Yangın Güvenliği', 'Kimya Teknolojisi', 'Konaklama ve Seyahat Hizmetleri', 'Kuyumculuk Teknolojisi', 'Laboratuvar Hizmetleri', 'Maden Teknolojisi', 'Makine ve Tasarım Teknolojisi', 'Marmara Gastronomi ve Mutfak Sanatları', 'Metal Teknolojisi', 'Metalürji Teknolojisi', 'Mikromekanik', 'Mobilya ve İç Mekân Tasarımı', 'Moda Tasarım Teknolojileri', 'Muhasebe ve Finansman', 'Otomotiv Teknolojileri', 'Motorlu Araçlar Teknolojisi', 'Pazarlama ve Perakende', 'Plastik Sanatlar', 'Plastik Teknolojisi', 'Radyo-Televizyon', 'Raylı Sistemler Teknolojisi', 'Sağlık Hizmetleri', 'Seramik ve Cam Teknolojisi', 'Siber Güvenlik', 'Sosyal Hizmetler', 'Tarım', 'Tekstil Teknolojisi', 'Tesisat Teknolojisi ve İklimlendirme', 'Uçak Bakım', 'Ulaştırma Hizmetleri', 'Yapay Zekâ', 'Yenilenebilir Enerji Teknolojileri', 'Yiyecek İçecek Hizmetleri', 'Aile ve Tüketici Hizmetleri', 'Matbaa Teknolojisi', 'Makine Teknolojisi', 'Elektrik-Elektronik Teknolojisi', 'Gemi Yapımı', 'Büro Yönetimi']
def _n(x): return re.sub(r'[^a-zçğıöşü]', '', tr_lower(x).replace('â', 'a'))
def alan_adi(satirlar, dosya):
    b = []
    for l in satirlar[:14]:
        if l.startswith('ÇERÇEVE ÖĞRETİM PROGRAMI'): break
        b.append(l)
    aday = ' '.join(b[2:]) if len(b) > 2 else ' '.join(b)
    aday = re.sub(r'\s*ALANI\s*$', '', aday).strip()
    for a in sorted(MEB_ALAN, key=len, reverse=True):
        if _n(a) and _n(a) in _n(aday): return a
    return tr_title(aday) if aday else dosya

def main(gir, cik):
    d = json.load(open(gir, encoding='utf-8'))
    sonuc = {}; rapor = []
    for yol, metin in sorted(d.items()):
        m = re.search(r'cop(\d+)/', yol); sinif = int(m.group(1))
        st = temizle(metin)
        alan = alan_adi(st, yol)
        cz = cizelge(st); ds = dersler(st)
        n = 0
        for c in ds:
            ad_b = re.sub(r'\s+', ' ', c['ad'])
            sin = cz.get(ad_b)
            if sin is None:
                # çizelgede adı kısaltılmış/farklı olabilir
                for k, v in cz.items():
                    if k.replace(' ', '') == ad_b.replace(' ', ''): sin = v; break
            if sin is None:
                if 'GELİŞİM ATÖLYESİ' in ad_b: continue
                hedef = 'M'; saat = c['saat'] or 2   # seçmeli meslek dersi: 11 ve 12. sınıflar
            else:
                if sinif not in sin: continue
                hedef = str(sinif); saat = sin[sinif] or c['saat']
            ders = tr_title(ad_b)
            if re.match(r'^[^(]*\)', ders): continue
            k = TUR + '|' + hedef + '|' + ders
            if hedef == 'M' and any(x.startswith(TUR + '|') and x.endswith('|' + ders) and '|M|' not in x for x in sonuc): continue
            if k in sonuc: continue
            sonuc[k] = {'tur': TUR, 'sinif': hedef, 'ders': ders, 'alan': alan, 'dosya': 'MEB MTEGM ' + yol.split('/')[-1], 'haftalar': haftalara_bol(c, saat), 'saat': saat, 'u': [[b['ad'], b['ciktilar'], b['icerik'], b.get('grup', '')] for b in c['birimler']]}
            n += 1
        rapor.append((yol, alan, len(ds), n))
    json.dump(list(sonuc.values()), open(cik, 'w', encoding='utf-8'), ensure_ascii=False)
    for r in rapor:
        if r[3] == 0: print('BOŞ', r)
    print('TOPLAM', len(sonuc), 'plan;', sum(1 for v in sonuc.values() if v['sinif'] != 'M'), 'sınıflı')

if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
