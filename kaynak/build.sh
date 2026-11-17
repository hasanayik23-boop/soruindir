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
    if f=='panel.js': c=open(d+'parca/matarac.js',encoding='utf-8').read()+'\n'+c
    if f.endswith('.css'): c=c+'\n'+open(d+'parca/mat.css',encoding='utf-8').read()
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
_mat=open(d+'parca/matarac.js',encoding='utf-8').read(); _mcss=open(d+'parca/mat.css',encoding='utf-8').read()
k=k+'\n'+open(d+'parca/admin_test.php',encoding='utf-8').read().replace('/*__MATARAC__*/',_mat).replace('/*__MATCSS__*/',_mcss)
for _f in ('sayfalar.php','topluluk.php'):
    import os
    if os.path.exists(d+'parca/'+_f): k=k+'\n'+open(d+'parca/'+_f,encoding='utf-8').read()
y=open(d+'parca/yazili.php',encoding='utf-8').read()
y=y.replace('/*__YAZILI_CSS__*/',open(d+'parca/yazili.css',encoding='utf-8').read()).replace('/*__YAZILI_JS__*/',open(d+'parca/docx.js',encoding='utf-8').read()+'\n'+open(d+'parca/yazili.js',encoding='utf-8').read())
y=y.replace("'/*__DIGER_JSON__*/'", q(open('/home/claude/work/dersler/diger.json',encoding='utf-8').read()))
assert '__DIGER_JSON__' not in y
assert '/*__YAZILI' not in y
om=y
ev=open(d+'parca/evrak.php',encoding='utf-8').read()
for ph,c in (('/*__EVRAK_CSS__*/',open(d+'parca/evrak.css',encoding='utf-8').read()),('/*__EVRAK_JS__*/',open(d+'parca/docx.js',encoding='utf-8').read()+'\n'+open(d+'parca/bgh.js',encoding='utf-8').read()+'\n'+open(d+'parca/evrak.js',encoding='utf-8').read())):
    assert ev.count(ph)==1 and '<?' not in c.replace('<?xml',''), ph
    ev=ev.replace(ph,c)
om=om+'\n'+ev+'\n'+open(d+'parca/masa.php',encoding='utf-8').read()+'\n'+open(d+'parca/yplan.php',encoding='utf-8').read().replace('/*__YP_MEB__*/',open(d+'veri/yp_meb.b64').read().strip()).replace('/*__YP_MTAL__*/',open(d+'veri/yp_mtal.b64').read().strip()).replace('/*__YP_MTAL_IDX__*/',open(d+'veri/yp_mtal_idx.b64').read().strip()).replace('/*__YPXLSX__*/',open(d+'parca/ypxlsx.js',encoding='utf-8').read())
import re as _re, subprocess as _sp
_ev=open(d+'parca/evrak.js',encoding='utf-8').read(); _a=_ev.index('var TURLER = ['); _b=_ev.index('\n  ];',_a)
_tl=[{'id':m.group(1),'ad':m.group(2),'ik':m.group(3),'ac':m.group(4)} for m in _re.finditer(r"\{ id: '([a-z_]+)'(?:, grup: '[a-z]+')?, ad: '([^']+)', ik: '([^']+)'(?:, yatay: true)?, ac: '([^']+)'", _ev[_a:_b])]
assert len(_tl)>=15, len(_tl)
_bg=_sp.check_output(['node','-e',"global.window={};require('"+d+"parca/bgh.js');process.stdout.write(JSON.stringify(window.OSC_BGH))"]).decode()
_seo=open(d+'parca/seo.php',encoding='utf-8').read().replace("'/*__SEO_EVRAK__*/'", q(_j.dumps(_tl,ensure_ascii=False))).replace("'/*__SEO_BGH__*/'", q(_bg))
assert '__SEO_' not in _seo
om=om+'\n'+_seo
bas=open(d+'parca/masa_bas.php',encoding='utf-8').read()
assert bas.count('/*__GOVDE__*/')==1
open(d+'ogretmen-masasi.php','w',encoding='utf-8').write(bas.replace('/*__GOVDE__*/',om))
print('masa', len(om))
s=s.rstrip()+'\n'+k
open(d+'onlinesorucoz.php','w',encoding='utf-8').write(s)
print('birlesti', len(s))
E
php -l onlinesorucoz.php
php -l ogretmen-masasi.php
