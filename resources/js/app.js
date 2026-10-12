import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import jsQR from 'jsqr';

const SIDEBAR_GROUP_KEY = 'sidebar-group:';

/**
 * Remember which sidebar groups are collapsed. The inline script in the layout
 * sets the initial state before Flux upgrades <ui-disclosure>; this keeps it in
 * sync afterwards, including across wire:navigate page swaps.
 */
function rememberSidebarGroups() {
    document.querySelectorAll('[data-sidebar-group]').forEach((group) => {
        const name = SIDEBAR_GROUP_KEY + group.dataset.sidebarGroup;
        let stored = null;

        try {
            stored = localStorage.getItem(name);
        } catch (error) {
            return;
        }

        // After a navigation the element is already booted, so toggling has to
        // go through the button instead of the `open` attribute.
        if (stored !== null && (stored === 'open') !== group.hasAttribute('data-open')) {
            group.querySelector('button')?.click();
        }

        if (group.dataset.sidebarGroupBound) {
            return;
        }

        group.dataset.sidebarGroupBound = 'true';

        group.addEventListener('lofi-disclosable-change', () => {
            // Flux fires this event before it syncs `data-open`, so read the state back
            // once the synchronous change handlers have finished.
            queueMicrotask(() => {
                try {
                    localStorage.setItem(name, group.hasAttribute('data-open') ? 'open' : 'closed');
                } catch (error) {
                    // Storage unavailable: the group still collapses, just without memory.
                }
            });
        });
    });
}

const syncSidebarGroups = () => customElements.whenDefined('ui-disclosure').then(rememberSidebarGroups);

document.addEventListener('DOMContentLoaded', syncSidebarGroups);
document.addEventListener('livewire:navigated', syncSidebarGroups);

const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

document.addEventListener('alpine:init', () => {
    /**
     * Pointer-reactive card: tilts slightly towards the cursor.
     */
    window.Alpine.data('spTilt', (maxDegrees = 7) => ({
        rotateX: 0,
        rotateY: 0,
        lifted: false,

        track(event) {
            if (prefersReducedMotion()) {
                return;
            }

            const rect = this.$el.getBoundingClientRect();
            const x = (event.clientX - rect.left) / rect.width;
            const y = (event.clientY - rect.top) / rect.height;

            this.rotateY = (x - 0.5) * 2 * maxDegrees;
            this.rotateX = (0.5 - y) * 2 * maxDegrees;
            this.lifted = true;
        },

        reset() {
            this.rotateX = 0;
            this.rotateY = 0;
            this.lifted = false;
        },

        get tiltStyle() {
            return `transform: perspective(1100px) rotateX(${this.rotateX}deg) rotateY(${this.rotateY}deg) translateZ(${this.lifted ? 6 : 0}px)`;
        },
    }));

    /**
     * Counts up to a number the first time the element scrolls into view.
     */
    window.Alpine.data('spCountUp', (target = 0, duration = 900) => ({
        displayed: 0,

        init() {
            const value = Number(target) || 0;

            if (prefersReducedMotion() || value === 0) {
                this.displayed = value;

                return;
            }

            const observer = new IntersectionObserver((entries) => {
                if (! entries[0].isIntersecting) {
                    return;
                }

                observer.disconnect();

                const startedAt = performance.now();
                const step = (now) => {
                    const progress = Math.min((now - startedAt) / duration, 1);
                    this.displayed = Math.round(value * (1 - Math.pow(1 - progress, 3)));

                    if (progress < 1) {
                        requestAnimationFrame(step);
                    }
                };

                requestAnimationFrame(step);
            }, { threshold: 0.4 });

            observer.observe(this.$el);
            this.$cleanup(() => observer.disconnect());
        },
    }));

    /**
     * Live "x of y answered" progress for a form, counted from the DOM so no
     * extra Livewire round trips are needed while typing.
     */
    window.Alpine.data('spFormProgress', (total = 0) => ({
        total,
        filled: 0,

        init() {
            this.$nextTick(() => this.recount());
        },

        recount() {
            this.filled = Array.from(this.$el.querySelectorAll('[data-sp-field]')).filter((group) => {
                const checkables = group.querySelectorAll('input[type="checkbox"], input[type="radio"]');

                if (checkables.length > 0) {
                    return Array.from(checkables).some((control) => control.checked);
                }

                return Array.from(group.querySelectorAll('input, textarea, select'))
                    .some((control) => control.value !== null && control.value !== '');
            }).length;
        },

        get percentage() {
            return this.total === 0 ? 0 : Math.round((this.filled / this.total) * 100);
        },
    }));

    /**
     * Reads a member's check-in QR code from the webcam and hands the decoded
     * code to Livewire. Frames are downscaled before decoding so a running
     * scanner stays cheap, and every member is only submitted once per minute:
     * their code rotates, but the part before the first dot identifies them.
     */
    window.Alpine.data('spQrScanner', (messages = {}) => ({
        active: false,
        error: '',
        stream: null,
        frame: null,
        canvas: null,
        pausedUntil: 0,
        seen: new Map(),

        async start() {
            this.error = '';

            if (! navigator.mediaDevices?.getUserMedia) {
                this.error = messages.unsupported;

                return;
            }

            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment', width: { ideal: 1280 } },
                });
            } catch (error) {
                this.error = messages.denied;

                return;
            }

            this.active = true;

            await this.$nextTick();

            this.$refs.video.srcObject = this.stream;
            await this.$refs.video.play().catch(() => {});

            this.frame = requestAnimationFrame(() => this.read());
        },

        stop() {
            this.active = false;

            if (this.frame) {
                cancelAnimationFrame(this.frame);
                this.frame = null;
            }

            this.stream?.getTracks().forEach((track) => track.stop());
            this.stream = null;
        },

        read() {
            if (! this.active) {
                return;
            }

            const video = this.$refs.video;

            if (video && video.readyState === video.HAVE_ENOUGH_DATA && Date.now() >= this.pausedUntil) {
                const width = 480;
                const height = Math.round((video.videoHeight / video.videoWidth) * width) || width;

                this.canvas ??= document.createElement('canvas');
                this.canvas.width = width;
                this.canvas.height = height;

                const context = this.canvas.getContext('2d', { willReadFrequently: true });
                context.drawImage(video, 0, 0, width, height);

                const { data } = context.getImageData(0, 0, width, height);
                const result = jsQR(data, width, height, { inversionAttempts: 'dontInvert' });

                if (result?.data) {
                    this.submit(result.data.trim());
                }
            }

            this.frame = requestAnimationFrame(() => this.read());
        },

        submit(code) {
            const now = Date.now();
            const member = code.split('.')[0];

            if (this.seen.get(member) > now) {
                return;
            }

            this.seen.set(member, now + 60000);
            this.pausedUntil = now + 2500;

            this.$wire.scanMemberCode(code);
        },

        destroy() {
            this.stop();
        },
    }));

    /**
     * Reports what a scan did on a club's kiosk screen. Echo delivers both
     * outcomes — the member who got in, or the reason they did not — and the
     * message clears itself after a few seconds, so the screen returns to the
     * code on its own.
     *
     * Echo only exists on the kiosk page. Without it the screen keeps working,
     * it just never reports.
     */
    window.Alpine.data('spCheckInBanner', (clubId, holdMs = 5000) => ({
        name: '',
        detail: '',
        refused: false,
        timer: null,

        init() {
            if (! window.Echo) {
                return;
            }

            const channel = window.Echo.private(`clubs.${clubId}.check-ins`);

            channel.listen('.member.checked-in', (event) => this.show(event.name, event.coverage, false));
            channel.listen('.check-in.refused', (event) => this.show(event.name, event.reason, true));

            this.$cleanup(() => {
                clearTimeout(this.timer);
                window.Echo.leave(`clubs.${clubId}.check-ins`);
            });
        },

        show(name, detail, refused) {
            this.name = name ?? '';
            this.detail = detail ?? '';
            this.refused = refused;

            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                this.name = '';
                this.detail = '';
            }, holdMs);
        },

        get visible() {
            return this.name !== '' || this.detail !== '';
        },
    }));

    window.Alpine.data('avatarCropper', () => ({
        cropping: false,
        uploading: false,
        cropper: null,

        startCropping(event) {
            const file = event.target.files[0];

            if (! file) {
                return;
            }

            this.cropping = true;
            const url = URL.createObjectURL(file);

            this.$nextTick(() => {
                this.$refs.cropImage.src = url;
                this.cropper?.destroy();
                this.cropper = new Cropper(this.$refs.cropImage, {
                    aspectRatio: 1,
                    viewMode: 1,
                    autoCropArea: 1,
                    background: false,
                });
            });

            event.target.value = '';
        },

        cancel() {
            this.cropper?.destroy();
            this.cropper = null;
            this.cropping = false;
            this.uploading = false;
        },

        save() {
            if (! this.cropper || this.uploading) {
                return;
            }

            this.uploading = true;

            this.cropper.getCroppedCanvas({ width: 512, height: 512 }).toBlob((blob) => {
                const file = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });

                this.$wire.upload(
                    'avatar',
                    file,
                    () => this.$wire.saveAvatar().then(() => this.cancel()),
                    () => this.cancel(),
                );
            }, 'image/jpeg', 0.9);
        },
    }));
});

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
