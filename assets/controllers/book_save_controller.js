import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['contactFields', 'saveToggle', 'nameField'];

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
}
