// Falnic Server Configurator — admin helpers (vanilla, no dependencies)
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		// Confirm dialogs
		document.querySelectorAll('[data-falnic-confirm]').forEach(function (el) {
			el.addEventListener('click', function (event) {
				var message = el.getAttribute('data-falnic-confirm') || 'مطمئنید؟';
				if (!window.confirm(message)) {
					event.preventDefault();
					event.stopPropagation();
				}
			});
		});

		// Check-all for bulk rows
		var checkAll = document.querySelector('[data-falnic-check-all]');
		if (checkAll) {
			checkAll.addEventListener('change', function () {
				var form = checkAll.closest('form');
				if (!form) return;
				form.querySelectorAll('input[name="bulk_ids[]"]').forEach(function (box) {
					box.checked = checkAll.checked;
				});
			});
		}

		// Copy shortcode button
		document.querySelectorAll('[data-falnic-copy]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var text = btn.getAttribute('data-falnic-copy') || '';
				var done = function () {
					var original = btn.textContent;
					btn.textContent = 'کپی شد ✓';
					window.setTimeout(function () { btn.textContent = original; }, 1400);
				};
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(done, done);
				} else {
					var tmp = document.createElement('textarea');
					tmp.value = text;
					document.body.appendChild(tmp);
					tmp.select();
					try { document.execCommand('copy'); } catch (e) {}
					document.body.removeChild(tmp);
					done();
				}
			});
		});
	});
})();
