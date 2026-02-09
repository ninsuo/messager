import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        interval: { type: Number, default: 5000 },
    };

    connect() {
        this.timer = setInterval(() => this.poll(), this.intervalValue);
    }

    disconnect() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }

    async poll() {
        try {
            const response = await fetch(this.urlValue);
            if (!response.ok) return;

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newCard = doc.querySelector(`#${this.element.id}`);
            if (!newCard) return;

            this.element.innerHTML = newCard.innerHTML;

            // Copy attributes from the new card
            for (const attr of Array.from(newCard.attributes)) {
                this.element.setAttribute(attr.name, attr.value);
            }

            // If the new card no longer has our controller, stop polling
            const controllers = (newCard.getAttribute('data-controller') || '');
            if (!controllers.split(/\s+/).includes('trigger-status')) {
                this.disconnect();
                this.element.removeAttribute('data-controller');
                this.element.removeAttribute('data-trigger-status-url-value');
                this.element.removeAttribute('data-trigger-status-interval-value');
            }
        } catch {
            // Silently ignore network errors; next poll will retry
        }
    }
}
