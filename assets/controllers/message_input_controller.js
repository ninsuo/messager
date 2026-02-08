import { Controller } from '@hotwired/stimulus';

const LIMITS = { sms: 306, call: 1600 };

export default class extends Controller {
    static targets = ['type', 'content', 'progressBar', 'counter'];

    connect() {
        this.updateCounter();
    }

    typeChanged() {
        this.updateCounter();
    }

    updateCounter() {
        const type = this.getSelectedType();
        const max = LIMITS[type];
        const length = this.contentTarget.value.length;
        const percent = Math.min((length / max) * 100, 100);

        this.counterTarget.textContent = `${length} / ${max}`;

        this.progressBarTarget.style.width = `${percent}%`;

        if (percent >= 100) {
            this.progressBarTarget.classList.remove('bg-warning');
            this.progressBarTarget.classList.add('bg-danger');
        } else if (percent >= 80) {
            this.progressBarTarget.classList.remove('bg-danger');
            this.progressBarTarget.classList.add('bg-warning');
        } else {
            this.progressBarTarget.classList.remove('bg-warning', 'bg-danger');
        }

        // Enforce hard limit
        if (length > max) {
            this.contentTarget.value = this.contentTarget.value.slice(0, max);
            this.counterTarget.textContent = `${max} / ${max}`;
            this.progressBarTarget.style.width = '100%';
        }
    }

    getSelectedType() {
        for (const radio of this.typeTargets) {
            if (radio.checked) return radio.value;
        }
        return 'sms';
    }
}
