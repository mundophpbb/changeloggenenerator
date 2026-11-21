(() => {
	'use strict';

	function bindCopy(copy, output, status) {
		copy.addEventListener('click', async () => {
			try {
				await navigator.clipboard.writeText(output.value);
				status.textContent = copy.dataset.copied;
			} catch (error) {
				output.focus();
				output.select();
				let copied = false;
				try {
					copied = document.execCommand('copy');
				} catch (copyError) {
					// Leave the text selected for manual copying.
				}
				status.textContent = copied ? copy.dataset.copied : copy.dataset.failed;
			}
		});
		copy.hidden = false;
	}

	const productCopy = document.getElementById('cl-copy-product');
	if (productCopy) {
		bindCopy(productCopy, document.getElementById('cl-product-announcement'), document.getElementById('cl-copy-product-status'));
	}

	const form = document.getElementById('cl-editor-form');
	if (!form) {
		return;
	}

	const locale = document.getElementById('cl-bb-locale');
	const output = document.getElementById('cl-bbcode');
	const copy = document.getElementById('cl-copy');
	const status = document.getElementById('cl-copy-status');
	const fields = Array.from(form.querySelectorAll('[data-heading]'));
	const linkFields = Array.from(form.querySelectorAll('[data-link-label]'));
	const labels = JSON.parse(form.dataset.bbLabels);
	const literal = text => text.replace(/\[/g, '［').replace(/\]/g, '］');

	function validLink(value, maxLength) {
		if (value.length > maxLength || !/^https?:\/\/[!-~]+$/i.test(value) || /[\[\]<>"'\\`]/.test(value)) {
			return false;
		}
		try {
			const url = new URL(value);
			return Boolean(url.hostname && !url.username && !url.password);
		} catch (error) {
			return false;
		}
	}

	function update() {
		const version = form.elements.version.value.trim();
		const blocks = ['[b]' + literal(form.dataset.product + ' ' + version) + '[/b]'];

		for (const field of fields) {
			const items = field.value.split(/\r?\n/).map(line => line.trim()).filter(Boolean);
			if (items.length) {
				const heading = labels[locale.value][field.dataset.heading];
				blocks.push(['[b]' + heading + '[/b]', '[list]',
					...items.map(item => '[*]' + literal(item)), '[/list]'].join('\n'));
			}
		}

		const links = [];
		for (const field of linkFields) {
			const url = field.value.trim();
			if (validLink(url, Number(field.dataset.linkMax))) {
				links.push('[url=' + url + ']' + labels[locale.value][field.dataset.linkLabel] + '[/url]');
			}
		}
		if (links.length) {
			blocks.push(links.join('\n'));
		}

		status.textContent = '';
		output.value = version ? blocks.join('\n\n') : '';
		copy.disabled = !output.value;
	}

	form.addEventListener('input', update);
	locale.addEventListener('change', update);
	bindCopy(copy, output, status);
	update();
})();
