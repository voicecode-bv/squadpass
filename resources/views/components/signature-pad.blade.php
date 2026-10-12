@props(['model'])

{{-- Draws into a canvas and stores the result as a PNG data URL on the Livewire property. --}}
<div
    x-data="{
        value: @entangle($model),
        drawing: false,
        context: null,
        init() {
            this.context = this.$refs.canvas.getContext('2d');
            this.context.lineWidth = 2;
            this.context.lineCap = 'round';
            this.context.strokeStyle = '#18181b';

            // Once saved, the property holds the stored path instead of the
            // drawing. Wipe the canvas then, so the signature is not shown
            // twice: as the saved image and as a leftover drawing.
            this.$watch('value', (value) => {
                if (! String(value ?? '').startsWith('data:image/')) {
                    this.erase();
                }
            });
        },
        erase() {
            this.context.clearRect(0, 0, this.$refs.canvas.width, this.$refs.canvas.height);
        },
        point(event) {
            const bounds = this.$refs.canvas.getBoundingClientRect();
            const source = event.touches ? event.touches[0] : event;

            return {
                x: (source.clientX - bounds.left) * (this.$refs.canvas.width / bounds.width),
                y: (source.clientY - bounds.top) * (this.$refs.canvas.height / bounds.height),
            };
        },
        start(event) {
            event.preventDefault();
            this.drawing = true;
            const { x, y } = this.point(event);
            this.context.beginPath();
            this.context.moveTo(x, y);
        },
        move(event) {
            if (! this.drawing) return;
            event.preventDefault();
            const { x, y } = this.point(event);
            this.context.lineTo(x, y);
            this.context.stroke();
        },
        stop() {
            if (! this.drawing) return;
            this.drawing = false;
            this.value = this.$refs.canvas.toDataURL('image/png');
        },
        clear() {
            this.erase();
            this.value = '';
        },
    }"
    class="flex flex-col gap-2"
>
    <canvas
        x-ref="canvas"
        width="600"
        height="200"
        class="h-40 w-full touch-none rounded-lg border border-zinc-300 bg-white dark:border-zinc-700"
        @mousedown="start($event)"
        @mousemove="move($event)"
        @mouseup="stop()"
        @mouseleave="stop()"
        @touchstart="start($event)"
        @touchmove="move($event)"
        @touchend="stop()"
    ></canvas>

    <div>
        <flux:button size="sm" variant="ghost" x-on:click="clear()">{{ __('Clear signature') }}</flux:button>
    </div>
</div>
