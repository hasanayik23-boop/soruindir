/* Ek Ders Ücreti Hesaplama — tarayıcıda çalışır, sunucuya yük bindirmez */
(function () {
	'use strict';
	function tl(n) {
		return (Math.round(n * 100) / 100).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' TL';
	}
	function sayi(el) {
		var v = parseFloat(String(el && el.value || '').replace(',', '.'));
		return isFinite(v) && v > 0 ? v : 0;
	}
	function yaz(n) { return String(Math.round(n * 10) / 10).replace('.', ','); }
	var ORAN = [0.15, 0.20, 0.27, 0.35, 0.40];
	function vergi(m, sinir) {
		var v = 0, alt = 0;
		for (var i = 0; i < ORAN.length; i++) {
			var ust = i < sinir.length ? sinir[i] : Infinity;
			if (m > alt) v += (Math.min(m, ust) - alt) * ORAN[i];
			alt = ust;
		}
		return v;
	}

	document.querySelectorAll('.edh[data-edh]').forEach(function (kok) {
		var A;
		try { A = JSON.parse(kok.getAttribute('data-edh')); } catch (e) { return; }
		var f = kok.querySelector('form');
		var o = function (ad) { return kok.querySelector('[data-o="' + ad + '"]'); };
		var saatler = f.querySelectorAll('input[data-g]');
		var r2 = function (x) { return Math.round(x * 100 + 1e-7) / 100; };
		var son = '';

		function deger(ad) { var el = f.querySelector('[name="' + ad + '"]:checked') || f.querySelector('[name="' + ad + '"]'); return el ? el.value : ''; }

		function hesapla() {
			var statu = deger('statu'), ucretli = statu === 'ucretli';
			kok.querySelectorAll('[data-yalniz]').forEach(function (el) {
				el.hidden = el.getAttribute('data-yalniz').split(' ').indexOf(statu) === -1;
			});
			kok.querySelector('[data-oto]').hidden = f.dilim.value !== 'oto';

			var k = A.k, e = ucretli ? 1 : (parseFloat(deger('egitim')) || 1);
			var brut = 0, toplam = 0, ders = 0, grup = {};
			saatler.forEach(function (el) {
				var kap = el.closest('[data-yalniz]');
				var s = kap && kap.hidden ? 0 : sayi(el);
				var g = el.getAttribute('data-grup');
				grup[g] = (grup[g] || 0) + s;
				if (!s) return;
				toplam += s;
				if (el.hasAttribute('data-ders')) ders += s;
				var lu = el.getAttribute('data-lu');
				var art = (lu === 'ders' || (lu === 'edyg' && statu === 'yonetici')) ? e : 1;
				brut += r2(s * parseFloat(el.getAttribute('data-g')) * parseFloat(el.getAttribute('data-c')) * art * k);
			});
			var ilave = ucretli && f.destek.checked ? Math.floor(ders / 5) : 0;
			brut += r2(ilave * 140 * k);
			brut = r2(brut);

			var sgk = r2(brut * (ucretli ? 0.15 : (statu === 'sozlesmeli' ? 0.14 : 0)));
			var matrah = brut - sgk, gv;
			if (f.dilim.value === 'oto') {
				var kum = sayi(f.kumulatif);
				gv = vergi(kum + matrah, A.dilimler) - vergi(kum, A.dilimler);
			} else {
				gv = matrah * (parseFloat(f.dilim.value) || 0.15);
			}
			var dv = brut * 0.00759;
			if (f.istisna.checked) {
				gv = Math.max(0, gv - (A.gvAylar[(parseInt(f.ay.value, 10) || 1) - 1] || 0));
				dv = Math.max(0, dv - A.dvIst);
			}
			gv = r2(gv); dv = r2(dv);
			var net = r2(brut - sgk - gv - dv);

			o('saat').textContent = yaz(toplam) + (ilave ? ' + ' + ilave + ' ilave' : '');
			o('saatbasi').textContent = tl(toplam + ilave ? net / (toplam + ilave) : 0);
			o('brut').textContent = tl(brut);
			o('sgkad').textContent = ucretli ? 'SGK + işsizlik (%15)' : 'SGK primi (%14)';
			o('sgk').textContent = tl(sgk);
			o('gv').textContent = tl(gv);
			o('dv').textContent = tl(dv);
			o('net').textContent = tl(net);
			o('net2').textContent = tl(net);
			Object.keys(grup).forEach(function (g) {
				var el = kok.querySelector('.edh-grup__top[data-grup="' + g + '"]');
				if (el) el.textContent = grup[g] ? yaz(grup[g]) + ' saat' : '';
			});
			// Dağılım çubuğu
			var b = brut || 1;
			[['net', net], ['sgk', sgk], ['gv', gv], ['dv', dv]].forEach(function (x) {
				var el = kok.querySelector('[data-bar="' + x[0] + '"]');
				if (el) el.style.width = (brut ? (x[1] / b * 100) : (x[0] === 'net' ? 100 : 0)) + '%';
			});
			kok.classList.toggle('edh--dolu', toplam > 0);
			son = 'Ek ders (' + yaz(toplam) + ' saat): brüt ' + tl(brut) + ', net ' + tl(net);
		}

		// +/- düğmeleri
		kok.addEventListener('click', function (ev) {
			var b = ev.target.closest('[data-adim]');
			if (b) {
				var inp = b.parentNode.querySelector('input');
				var v = Math.max(0, Math.min(400, sayi(inp) + parseFloat(b.getAttribute('data-adim'))));
				inp.value = v ? v : '';
				hesapla();
				return;
			}
			if (ev.target.closest('[data-sifirla]')) {
				saatler.forEach(function (el) { el.value = ''; });
				hesapla();
				return;
			}
			// Telefonda sonuç kartına dokununca ayrıntılar açılır
			var sk = ev.target.closest('.edh-sonuc__kart');
			if (sk && !ev.target.closest('button')) { sk.parentNode.classList.toggle('edh-acik'); return; }
			var kb = ev.target.closest('[data-kopyala]');
			if (kb && navigator.clipboard) {
				navigator.clipboard.writeText(son).then(function () {
					var eski = kb.textContent; kb.textContent = 'Kopyalandı ✓';
					setTimeout(function () { kb.textContent = eski; }, 1500);
				});
			}
		});
		f.addEventListener('input', hesapla);
		f.addEventListener('change', hesapla);
		hesapla();
	});
})();
