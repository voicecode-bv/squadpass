import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

document.addEventListener('alpine:init', () => {
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
