import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['digit', 'hidden'];

    connect() {
        this.digitTargets[0].focus();
    }

    onInput(event) {
        const input = event.target;
        const index = this.digitTargets.indexOf(input);
        const value = input.value;

        // Keep only the last digit typed
        if (value.length > 1) {
            input.value = value.slice(-1);
        }

        // Reject non-digits
        if (!/^\d$/.test(input.value)) {
            input.value = '';
            this.syncHidden();
            return;
        }

        this.syncHidden();

        // Auto-advance to next box
        if (input.value && index < this.digitTargets.length - 1) {
            this.digitTargets[index + 1].focus();
            this.digitTargets[index + 1].select();
        }
    }

    onKeydown(event) {
        const input = event.target;
        const index = this.digitTargets.indexOf(input);

        if (event.key === 'Backspace' && !input.value && index > 0) {
            this.digitTargets[index - 1].focus();
            this.digitTargets[index - 1].value = '';
            this.syncHidden();
            event.preventDefault();
        }

        // Arrow keys
        if (event.key === 'ArrowLeft' && index > 0) {
            this.digitTargets[index - 1].focus();
            event.preventDefault();
        }
        if (event.key === 'ArrowRight' && index < this.digitTargets.length - 1) {
            this.digitTargets[index + 1].focus();
            event.preventDefault();
        }
    }

    onPaste(event) {
        event.preventDefault();
        const pasted = (event.clipboardData || window.clipboardData).getData('text');
        const digits = pasted.replace(/\D/g, '').slice(0, 6);

        digits.split('').forEach((digit, i) => {
            if (i < this.digitTargets.length) {
                this.digitTargets[i].value = digit;
            }
        });

        this.syncHidden();

        // Focus the next empty box, or the last one
        const nextEmpty = this.digitTargets.findIndex(d => !d.value);
        if (nextEmpty !== -1) {
            this.digitTargets[nextEmpty].focus();
        } else {
            this.digitTargets[this.digitTargets.length - 1].focus();
        }
    }

    onFocus(event) {
        event.target.select();
    }

    syncHidden() {
        this.hiddenTarget.value = this.digitTargets.map(d => d.value).join('');
    }
}
