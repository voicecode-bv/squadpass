<div>
    <flux:modal wire:model.self="open" @close="complete" class="w-full md:w-[32rem]" data-test="product-tour">
        @if ($open)
            @php($steps = $this->steps)

            <div x-data="{ step: 0, total: {{ count($steps) }} }" class="space-y-6">
                <div class="flex items-center gap-1.5 pe-10" aria-hidden="true">
                    @foreach ($steps as $index => $tourStep)
                        <button
                            type="button"
                            tabindex="-1"
                            x-on:click="step = {{ $index }}"
                            class="h-1.5 flex-1 rounded-full transition-colors duration-300"
                            x-bind:class="step >= {{ $index }} ? 'bg-blue-600' : 'bg-zinc-200 dark:bg-zinc-700'"
                        ></button>
                    @endforeach
                </div>

                @foreach ($steps as $index => $tourStep)
                    <div x-show="step === {{ $index }}" @if ($index > 0) x-cloak @endif wire:key="tour-step-{{ $index }}" class="animate-fade">
                        <div class="flex size-14 items-center justify-center rounded-2xl bg-blue-600 text-white">
                            <flux:icon :name="$tourStep['icon']" class="size-7" />
                        </div>

                        <p class="sp-card-subtitle mt-6 uppercase tracking-wide">
                            {{ __('Step :current of :total', ['current' => $index + 1, 'total' => count($steps)]) }}
                        </p>
                        <h2 class="sp-card-title mt-1">{{ $tourStep['title'] }}</h2>
                        <flux:text class="mt-3 leading-relaxed">{{ $tourStep['body'] }}</flux:text>

                        @if ($tourStep['url'])
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon:trailing="arrow-right"
                                class="mt-4 -ms-2"
                                wire:click="visit({{ $index }})"
                            >
                                {{ $tourStep['action'] }}
                            </flux:button>
                        @endif
                    </div>
                @endforeach

                <div class="flex items-center gap-2 pt-2">
                    <flux:button variant="ghost" x-show="step < total - 1" x-on:click="$wire.open = false">
                        {{ __('Skip') }}
                    </flux:button>

                    <flux:spacer />

                    <flux:button variant="filled" x-show="step > 0" x-cloak x-on:click="step--">
                        {{ __('Back') }}
                    </flux:button>
                    <flux:button variant="primary" x-show="step < total - 1" x-on:click="step++" data-test="product-tour-next">
                        {{ __('Next') }}
                    </flux:button>
                    <flux:button variant="primary" x-show="step === total - 1" x-cloak x-on:click="$wire.open = false">
                        {{ __('Get started') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
