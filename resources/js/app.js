

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('submit', (event) => {
	const form = event.target;

	if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-loading-form')) {
		return;
	}

	if (form.dataset.submitting === 'true') {
		event.preventDefault();
		return;
	}

	form.dataset.submitting = 'true';

	const submitter = event.submitter;

	if (submitter?.name) {
		const submittedValue = document.createElement('input');
		submittedValue.type = 'hidden';
		submittedValue.name = submitter.name;
		submittedValue.value = submitter.value;
		form.append(submittedValue);
	}

	form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
		button.disabled = true;

		if (button !== submitter) {
			return;
		}

		button.setAttribute('aria-busy', 'true');
		button.querySelector('[data-submit-label]')?.classList.add('hidden');
		button.querySelector('[data-submit-loading]')?.classList.remove('hidden');
	});
});
