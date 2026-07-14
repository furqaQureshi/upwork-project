import { getApps, initializeApp } from 'firebase/app';
import { RecaptchaVerifier, getAuth, signInWithPhoneNumber } from 'firebase/auth';

const metaContent = (name) => document.querySelector(`meta[name="${name}"]`)?.getAttribute('content')?.trim() ?? '';

const INDIA_DIAL_CODE = '91';
const INDIA_E164_PATTERN = /^\+91[6-9]\d{9}$/;

const firebaseConfig = {
    apiKey: metaContent('fcm-api-key'),
    projectId: metaContent('fcm-project-id'),
    messagingSenderId: metaContent('fcm-messaging-sender-id'),
    appId: metaContent('fcm-app-id'),
    authDomain: metaContent('firebase-auth-domain'),
};

if (!firebaseConfig.authDomain && firebaseConfig.projectId) {
    firebaseConfig.authDomain = `${firebaseConfig.projectId}.firebaseapp.com`;
}

let firebaseAuthRuntime = null;

const hasFirebaseConfig = () => Boolean(
    firebaseConfig.apiKey
    && firebaseConfig.projectId
    && firebaseConfig.messagingSenderId
    && firebaseConfig.appId
    && firebaseConfig.authDomain
);

const bootstrapFirebaseAuth = () => {
    if (firebaseAuthRuntime) {
        return firebaseAuthRuntime;
    }

    if (!hasFirebaseConfig()) {
        return null;
    }

    const app = getApps().find((candidate) => candidate.name === 'unsell-phone-auth')
        ?? initializeApp(firebaseConfig, 'unsell-phone-auth');

    firebaseAuthRuntime = {
        app,
        auth: getAuth(app),
    };

    return firebaseAuthRuntime;
};

const extractErrorMessage = (payload) => {
    if (!payload || typeof payload !== 'object') {
        return 'Unable to complete request. Please try again.';
    }

    if (typeof payload.message === 'string' && payload.message.trim() !== '') {
        return payload.message;
    }

    if (payload.errors && typeof payload.errors === 'object') {
        const firstKey = Object.keys(payload.errors)[0];
        if (firstKey) {
            const messages = payload.errors[firstKey];
            if (Array.isArray(messages) && messages[0]) {
                return String(messages[0]);
            }

            if (typeof messages === 'string') {
                return messages;
            }
        }
    }

    return 'Unable to complete request. Please try again.';
};

const normalizePhone = (value) => {
    const raw = String(value ?? '').trim();
    if (raw === '') {
        return '';
    }

    const digits = raw.replace(/\D+/g, '');

    if (digits === '') {
        return '';
    }

    if (raw.startsWith('+')) {
        return `+${digits}`;
    }

    if (digits.length === 10) {
        return `+${INDIA_DIAL_CODE}${digits}`;
    }

    if (digits.length === 11 && digits.startsWith('0')) {
        return `+${INDIA_DIAL_CODE}${digits.slice(1)}`;
    }

    if (digits.length === 12 && digits.startsWith(INDIA_DIAL_CODE)) {
        return `+${digits}`;
    }

    return digits;
};

const isValidIndianPhone = (value) => INDIA_E164_PATTERN.test(normalizePhone(value));

const setMode = (root, mode) => {
    const tabs = root.querySelectorAll('[data-auth-mode-btn]');
    tabs.forEach((button) => {
        const active = button.getAttribute('data-auth-mode-btn') === mode;
        button.classList.toggle('bg-orange-500', active);
        button.classList.toggle('text-white', active);
        button.classList.toggle('text-slate-600', !active);
        button.classList.toggle('bg-white', !active);
    });

    const panels = root.querySelectorAll('[data-auth-panel]');
    panels.forEach((panel) => {
        const active = panel.getAttribute('data-auth-panel') === mode;
        panel.classList.toggle('hidden', !active);
    });

    const modeInput = root.querySelector('input[name="auth_mode"]');
    if (modeInput) {
        modeInput.value = mode;
    }
};

const bindModeToggle = (root) => {
    const initialMode = root.getAttribute('data-auth-initial-mode') || 'email';

    root.querySelectorAll('[data-auth-mode-btn]').forEach((button) => {
        button.addEventListener('click', () => {
            const mode = button.getAttribute('data-auth-mode-btn') || 'email';
            setMode(root, mode);
        });
    });

    setMode(root, initialMode);
};

const bindPhoneOtpPanel = (root) => {
    const panel = root.querySelector('[data-auth-panel="mobile"]');
    if (!panel) {
        return;
    }

    const sendButton = panel.querySelector('[data-send-otp]');
    const verifyButton = panel.querySelector('[data-verify-otp]');
    const phoneInput = panel.querySelector('[data-mobile-phone]');
    const otpInput = panel.querySelector('[data-mobile-otp]');
    const otpSection = panel.querySelector('[data-mobile-otp-section]');
    const nameInput = panel.querySelector('[data-mobile-name]');
    const statusNode = panel.querySelector('[data-mobile-status]');
    const errorNode = panel.querySelector('[data-mobile-error]');
    const recaptchaNode = panel.querySelector('[data-firebase-recaptcha]');

    const endpoint = root.getAttribute('data-mobile-endpoint') || '';
    const flow = root.getAttribute('data-auth-flow') || 'login';
    const csrfToken = metaContent('csrf-token');
    const handoffActive = root.getAttribute('data-mobile-handoff-active') === '1';
    const handoffPhone = normalizePhone(root.getAttribute('data-mobile-handoff-phone') || '');

    let confirmationResult = null;
    let recaptchaVerifier = null;

    const runtime = bootstrapFirebaseAuth();

    if (!runtime) {
        if (sendButton) sendButton.disabled = true;
        if (verifyButton) verifyButton.disabled = true;
        if (errorNode) {
            errorNode.textContent = 'Firebase is not configured. Set FCM API key, project ID, sender ID, and app ID in Admin settings.';
            errorNode.classList.remove('hidden');
        }

        return;
    }

    const clearMessages = () => {
        if (statusNode) {
            statusNode.textContent = '';
            statusNode.classList.add('hidden');
        }

        if (errorNode) {
            errorNode.textContent = '';
            errorNode.classList.add('hidden');
        }
    };

    const showStatus = (message) => {
        if (!statusNode) {
            return;
        }

        statusNode.textContent = message;
        statusNode.classList.remove('hidden');
    };

    const showError = (message) => {
        if (!errorNode) {
            return;
        }

        errorNode.textContent = message;
        errorNode.classList.remove('hidden');
    };

    const setOtpVisibility = (visible) => {
        if (!otpSection) {
            return;
        }

        otpSection.classList.toggle('hidden', !visible);
    };

    const syncPhoneState = () => {
        if (handoffActive || !phoneInput) {
            return;
        }

        const validPhone = isValidIndianPhone(phoneInput.value);
        if (sendButton) {
            sendButton.disabled = !validPhone;
        }

        setOtpVisibility(validPhone || Boolean(confirmationResult));
    };

    const ensureRecaptcha = async () => {
        if (recaptchaVerifier) {
            return recaptchaVerifier;
        }

        if (!recaptchaNode) {
            throw new Error('reCAPTCHA container is missing.');
        }

        recaptchaVerifier = new RecaptchaVerifier(runtime.auth, recaptchaNode, {
            size: 'invisible',
        });

        await recaptchaVerifier.render();

        return recaptchaVerifier;
    };

    if (handoffActive && phoneInput && handoffPhone !== '') {
        phoneInput.value = handoffPhone;
        phoneInput.readOnly = true;
        setOtpVisibility(true);
    } else if (phoneInput) {
        phoneInput.setAttribute('placeholder', '9876543210');
        phoneInput.addEventListener('input', () => {
            clearMessages();
            syncPhoneState();
        });

        phoneInput.addEventListener('blur', () => {
            const normalizedPhone = normalizePhone(phoneInput.value);
            phoneInput.value = normalizedPhone;
            syncPhoneState();
        });

        syncPhoneState();
    }

    if (sendButton && phoneInput && !handoffActive) {
        sendButton.addEventListener('click', async () => {
            clearMessages();

            const normalizedPhone = normalizePhone(phoneInput.value);
            if (!isValidIndianPhone(normalizedPhone)) {
                showError('Enter a valid Indian mobile number (10 digits starting with 6, 7, 8, or 9). +91 is added automatically.');
                return;
            }

            phoneInput.value = normalizedPhone;
            sendButton.disabled = true;

            try {
                const verifier = await ensureRecaptcha();
                confirmationResult = await signInWithPhoneNumber(runtime.auth, normalizedPhone, verifier);
                setOtpVisibility(true);
                showStatus('OTP sent successfully. Please enter the code to continue.');
                otpInput?.focus();
            } catch (error) {
                const message = (error && typeof error.message === 'string' && error.message !== '')
                    ? error.message
                    : 'Unable to send OTP right now. Please try again.';
                showError(message);
            } finally {
                sendButton.disabled = false;
            }
        });
    }

    if (verifyButton && phoneInput) {
        verifyButton.addEventListener('click', async () => {
            clearMessages();

            let idToken = '';

            if (!handoffActive) {
                if (!confirmationResult) {
                    showError('Please send OTP first.');
                    return;
                }

                const otp = String(otpInput?.value ?? '').trim();
                if (!/^\d{6}$/.test(otp)) {
                    showError('Enter the 6-digit OTP.');
                    return;
                }
            }

            if (!endpoint) {
                showError('Mobile auth endpoint is not configured.');
                return;
            }

            if (!csrfToken) {
                showError('Missing CSRF token. Refresh the page and try again.');
                return;
            }

            verifyButton.disabled = true;

            try {
                if (!handoffActive) {
                    const otp = String(otpInput?.value ?? '').trim();
                    const credential = await confirmationResult.confirm(otp);
                    idToken = await credential.user.getIdToken(true);
                }

                const payload = {
                    phone: normalizePhone(phoneInput.value),
                };

                if (!handoffActive) {
                    payload.id_token = idToken;
                }

                if (flow === 'register' && nameInput) {
                    payload.name = String(nameInput.value ?? '').trim();
                    if (payload.name === '') {
                        showError('Name is required for registration.');
                        verifyButton.disabled = false;
                        return;
                    }
                }

                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                });

                let json = null;
                try {
                    json = await response.json();
                } catch (_) {
                    json = null;
                }

                if (!response.ok) {
                    throw new Error(extractErrorMessage(json));
                }

                const redirectTo = (json && json.redirect) ? String(json.redirect) : '/dashboard';
                window.location.assign(redirectTo);
            } catch (error) {
                const message = (error && typeof error.message === 'string' && error.message !== '')
                    ? error.message
                    : 'OTP verification failed. Please try again.';
                showError(message);
            } finally {
                verifyButton.disabled = false;
            }
        });
    }
};

document.querySelectorAll('[data-auth-mode-root]').forEach((root) => {
    bindModeToggle(root);
    bindPhoneOtpPanel(root);
});
