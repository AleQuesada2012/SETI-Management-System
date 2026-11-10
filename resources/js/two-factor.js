// Alpine components of the two-factor screens. Livewire's CSP-safe build (config/livewire.php) evaluates x-data and
// x-on with a parser that accepts one plain expression, so anything with arrow functions, `?.` or several
// statements lives here instead of in the Blade attribute.
const register = () => {
    // Sign-in challenge (pages/auth/two-factor-challenge): an authenticator code or a recovery code.
    window.Alpine.data('twoFactorChallenge', (showRecoveryInput = false) => ({
        showRecoveryInput,
        code: '',
        recovery_code: '',
        init() {
            if (! this.showRecoveryInput) {
                this.focusOtp();
            }
        },
        focusOtp() {
            this.$nextTick(() => this.$refs.otp?.querySelector('input')?.focus());
        },
        toggleInput() {
            this.showRecoveryInput = ! this.showRecoveryInput;
            this.code = '';
            this.recovery_code = '';
            this.$nextTick(() => {
                if (this.showRecoveryInput) {
                    this.$refs.recovery_code?.focus();
                } else {
                    this.focusOtp();
                }
            });
        },
    }));

    // Setup modal (pages/settings/two-factor-setup-modal): focus the first code box when the step appears.
    window.Alpine.data('focusFirstInput', () => ({
        init() {
            this.$nextTick(() => this.$el.querySelector('input')?.focus());
        },
    }));

    // Setup modal: copy the manual setup key. It reads the input, so a key that Livewire renders later is the one copied.
    window.Alpine.data('copySetupKey', () => ({
        copied: false,
        async copy() {
            try {
                await navigator.clipboard.writeText(this.$refs.setupKey.value);
                this.copied = true;
                setTimeout(() => this.copied = false, 1500);
            } catch {
                console.warn('Could not copy to clipboard');
            }
        },
    }));
};

if (window.Alpine) register();
else document.addEventListener('alpine:init', register, { once: true });
