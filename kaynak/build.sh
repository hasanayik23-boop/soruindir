#!/bin/bash
set -e
cd /home/claude/work/osc
python3 yama_a.py
python3 yama_b.py
python3 yama_c.py
python3 - <<'E'
d='/home/claude/work/osc/'
s=open(d+'onlinesorucoz.php',encoding='utf-8').read()
k=open(d+'parca/sinif.php',encoding='utf-8').read()
k=k.replace('/*__EK_METODLAR__*/',open(d+'parca/ek.php',encoding='utf-8').read())
for ph,f in (('/*__OGRENCI_CSS__*/','ogrenci.css'),('/*__UYELIK_CSS__*/','uyelik.css'),('/*__OGRPANEL_CSS__*/','ogrpanel.css'),('/*__OGRPANEL_JS__*/','ogrpanel.js'),('/*__PANEL_CSS__*/','panel.css'),('/*__PANEL_JS__*/','panel.js')):
    c=open(d+'parca/'+f,encoding='utf-8').read()
    assert '<?' not in c, f
    assert k.count(ph)==1, ph
    k=k.replace(ph,c)
import json
k=k.replace("/*__KVKK__*/''", "'"+open(d+'parca/kvkk.html').read().replace('\\','\\\\').replace("'","\\'")+"'")
k=k.replace('/*__LOGO__*/',open(d+'parca/logo.b64').read().strip())
import json as _j
h=open(d+'parca/havuz.php',encoding='utf-8').read()
def q(x): return "'"+x.replace('\\','\\\\').replace("'","\\'")+"'"
h=h.replace("/*__SENARYO_JSON__*/''", q(_j.dumps(_j.load(open('/home/claude/work/senaryo/senaryolar.json')),ensure_ascii=False,separators=(',',':'))))
h=h.replace("/*__YAZILIMATIK_JSON__*/''", q(_j.dumps(_j.load(open('/home/claude/work/yazilimatik/zip/sinav_data.json')),ensure_ascii=False,separators=(',',':'))))
h=h.replace('/*__HAVUZ_CSS__*/',open(d+'parca/havuz.css',encoding='utf-8').read()).replace('/*__HAVUZ_JS__*/',open(d+'parca/havuz.js',encoding='utf-8').read())
assert '__' not in ''.join(x for x in ['__SENARYO','__YAZILIMATIK','__HAVUZ'] if x in h)
k=k+'\n'+h
k=k+'\n'+open(d+'parca/yzapi.php',encoding='utf-8').read()
k=k+'\n'+open(d+'parca/havuz_arka.php',encoding='utf-8').read()
k=k+'\n'+open(d+'parca/havuz_uret.php',encoding='utf-8').read()
y=open(d+'parca/yazili.php',encoding='utf-8').read()
y=y.replace('/*__YAZILI_CSS__*/',open(d+'parca/yazili.css',encoding='utf-8').read()).replace('/*__YAZILI_JS__*/',open(d+'parca/docx.js',encoding='utf-8').read()+'\n'+open(d+'parca/yazili.js',encoding='utf-8').read())
y=y.replace("'/*__DIGER_JSON__*/'", q(open('/home/claude/work/dersler/diger.json',encoding='utf-8').read()))
assert '__DIGER_JSON__' not in y
assert '/*__YAZILI' not in y
k=k+'\n'+y
s=s.rstrip()+'\n'+k
open(d+'onlinesorucoz.php','w',encoding='utf-8').write(s)
print('birlesti', len(s))
E
php -l onlinesorucoz.php
