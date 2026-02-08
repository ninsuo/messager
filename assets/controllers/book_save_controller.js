import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['toggle', 'nameField'];

    toggle() {
        if (this.toggleTarget.checked) {
            this.nameFieldTarget.classList.remove('d-none');
            this.nameFieldTarget.querySelector('input')?.focus();
        } else {
            this.nameFieldTarget.classList.add('d-none');
        }
    }
}
