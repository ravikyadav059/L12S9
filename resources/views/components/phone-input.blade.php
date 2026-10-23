@props([
    'placeholder' => '1234567890',
    'defaultCountry' => null,
    'hasError' => false,
    'id' => null,
    'name' => null,
    'required' => false,
    'disabled' => false,
    'countryCodeModel' => null,
])

@php
    $wireModel = $attributes->wire('model');
    $wireProp = $wireModel->value();
    $isLive = $wireModel->hasModifier('live');
    
    $detectedCountry = strtolower(\App\Helpers\TimezoneHelper::getCountryCodeByTimezone() ?? 'in');
    $resolvedDefault = $defaultCountry ?: $detectedCountry;
    $inputId = $id ?: ($wireProp ?: 'phone_' . uniqid());
@endphp

<div 
    x-data="{
        value: @if($wireProp) @entangle($wireProp){{ $isLive ? '.live' : '' }} @else '' @endif,
        countryCode: @if($countryCodeModel) @entangle($countryCodeModel) @else null @endif,
        iti: null,
        defaultCountry: '{{ $resolvedDefault }}',
        initialized: false,
        
        init() {
            this.$nextTick(() => {
                if (typeof window.intlTelInput === 'undefined') {
                    console.warn('intlTelInput library not yet loaded, waiting...');
                    const checkInterval = setInterval(() => {
                        if (typeof window.intlTelInput !== 'undefined') {
                            clearInterval(checkInterval);
                            this.setupIti();
                        }
                    }, 50);
                    return;
                }
                this.setupIti();
            });
        },

        setupIti() {
            if (this.initialized) return;
            const input = this.$refs.phoneInput;
            if (!input) return;

            let initCountry = this.defaultCountry || 'in';
            if (!initCountry) {
                try {
                    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                    if (tz && (tz.includes('Calcutta') || tz.includes('Kolkata'))) {
                        initCountry = 'in';
                    }
                } catch(e) {}
            }

            this.iti = window.intlTelInput(input, {
                initialCountry: initCountry,
                separateDialCode: true,
                autoPlaceholder: 'polite',
                nationalMode: false,
                formatOnDisplay: true,
                utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.1.1/build/js/utils.js'
            });

            this.initialized = true;

            const updateCountryCode = () => {
                if (this.countryCode !== null && typeof this.countryCode !== 'undefined' && this.iti) {
                    const cData = this.iti.getSelectedCountryData();
                    if (cData && cData.dialCode) {
                        this.countryCode = '+' + cData.dialCode;
                    }
                }
            };

            updateCountryCode();

            // Load initial value if already present in Livewire
            if (this.value) {
                try {
                    let numToSet = String(this.value);
                    if (this.countryCode && !numToSet.startsWith('+')) {
                        numToSet = this.countryCode + numToSet;
                    }
                    this.iti.setNumber(numToSet);
                } catch(e) {}
            }

            const syncValue = () => {
                if (!this.iti) return;
                updateCountryCode();

                const rawVal = input.value.trim();
                if (!rawVal) {
                    this.value = '';
                    return;
                }

                if (this.countryCode !== null && typeof this.countryCode !== 'undefined') {
                    const cData = this.iti.getSelectedCountryData();
                    let digits = rawVal.replace(/\D/g, '');
                    if (cData && cData.dialCode && digits.startsWith(cData.dialCode) && digits.length > cData.dialCode.length) {
                        digits = digits.slice(cData.dialCode.length);
                    }
                    this.value = digits;
                } else {
                    const fullNumber = this.iti.getNumber();
                    if (fullNumber) {
                        this.value = fullNumber;
                    } else {
                        const cData = this.iti.getSelectedCountryData();
                        const dial = cData && cData.dialCode ? ('+' + cData.dialCode) : '';
                        this.value = dial ? (dial + ' ' + rawVal) : rawVal;
                    }
                }
            };

            input.addEventListener('input', syncValue);
            input.addEventListener('countrychange', syncValue);

            // Watch external updates from Livewire (e.g. form reset or server load)
            this.$watch('value', (newVal) => {
                if (!this.iti) return;
                if (this.countryCode !== null && typeof this.countryCode !== 'undefined') {
                    if (newVal !== input.value.replace(/\D/g, '')) {
                        if (newVal) {
                            try {
                                let num = String(newVal);
                                if (this.countryCode && !num.startsWith('+')) {
                                    num = this.countryCode + num;
                                }
                                this.iti.setNumber(num);
                            } catch(e) {}
                        } else {
                            input.value = '';
                        }
                    }
                } else {
                    const currentFull = this.iti.getNumber();
                    if (newVal !== currentFull && newVal !== input.value) {
                        if (newVal) {
                            try {
                                this.iti.setNumber(String(newVal));
                            } catch(e) {}
                        } else {
                            input.value = '';
                        }
                    }
                }
            });
        }
    }"
    class="w-full relative"
    wire:ignore
>
    <input 
        x-ref="phoneInput"
        type="tel"
        id="{{ $inputId }}"
        @if($name) name="{{ $name }}" @endif
        placeholder="{{ $placeholder }}"
        @if($required) required @endif
        @if($disabled) disabled @endif
        {{ $attributes->whereDoesntStartWith('wire:model')->merge([
            'class' => 'w-full py-2.5 pr-4 bg-zinc-50 dark:bg-zinc-900 border ' . ($hasError ? 'border-rose-400 dark:border-rose-500' : 'border-zinc-200 dark:border-zinc-700') . ' rounded-xl text-sm text-zinc-900 dark:text-white placeholder:text-zinc-400 dark:placeholder:text-zinc-500 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition ' . ($disabled ? 'opacity-50 cursor-not-allowed bg-zinc-100 dark:bg-zinc-800' : '')
        ]) }}
    />
</div>
