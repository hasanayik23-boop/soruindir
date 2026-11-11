P = '/home/claude/work/osc/onlinesorucoz.php'
s = open(P, encoding='utf-8').read()
i = s.index("/* ===== V21: Gelişmiş PDF / Word içe aktarma ===== */")
j = s.index("            jQuery(document).ready(function($){", i)
lib = s[i:j]
s = s[:i] + "<?php $this->import_kutuphane_js(); ?>\n" + s[j:]
k = s.index("        public function admin_scripts() {")
s = s[:k] + "        // V22: PDF/Word/resim içe aktarma kütüphanesi (yönetici paneli ve öğretmen paneli ortak kullanır)\n        public function import_kutuphane_js() {\n            ?>" + lib + "<?php\n        }\n\n" + s[k:]
open(P, 'w', encoding='utf-8').write(s)
print('ok-c')
