import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['contactFields', 'saveToggle', 'nameField', 'phones'];

    toggleContacts(event) {
        if (event.target.checked) {
            this.contactFieldsTarget.classList.remove('d-none');
            this.contactFieldsTarget.querySelector('textarea')?.focus();
        } else {
            this.contactFieldsTarget.classList.add('d-none');
        }
    }

    toggleSave() {
        if (this.saveToggleTarget.checked) {
            this.nameFieldTarget.classList.remove('d-none');
            this.nameFieldTarget.querySelector('input')?.focus();
        } else {
            this.nameFieldTarget.classList.add('d-none');
        }
    }

    fillRandom() {
        const numbers = [];
        for (let i = 0; i < 1000; i++) {
            const prefix = Math.random() < 0.5 ? '06' : '07';
            const suffix = String(Math.floor(Math.random() * 100000000)).padStart(8, '0');
            numbers.push(prefix + suffix);
        }
        const textarea = this.phonesTarget;
        textarea.value = (textarea.value ? textarea.value.trimEnd() + '\n' : '') + numbers.join('\n');
    }
}
