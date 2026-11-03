{{-- resources/views/components/profile/certificate-form.blade.php --}}
@props([
    'showStatus' => false,
    'itemId' => null,
])

<div class="mt-4 border-[1.5px] border-dashed border-brand rounded-2xl p-5 sm:p-6 bg-form-bg shadow-2xs">
    <div class="space-y-4">

        @if($showStatus)
            <template x-if="form.id">
                <div class="flex items-center gap-2 pb-1 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-xs font-semibold text-zinc-500">Certificate Record ID:</span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-mono text-[10.5px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700/80 select-all" title="Click to select">
                        <span class="text-zinc-400 select-none">ID:</span>
                        <span x-text="form.id"></span>
                    </span>
                </div>
            </template>
        @endif

        {{-- Row 1: Certificate Name * & Event Name --}}
        <div wire:ignore.self class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- 1. Certificate Name * --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Certificate Name <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    x-model="form.certificate" 
                    @input="errors.certificate = false"
                    placeholder="e.g., AWS Certified Solutions Architect"
                    :class="errors.certificate ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.certificate" x-cloak class="text-[11px] text-red-500 mt-1">Please enter certificate name (minimum 2 characters)</p>
            </div>

            {{-- 2. Event Name --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Event Name
                </label>
                <input 
                    type="text" 
                    x-model="form.event_name" 
                    placeholder="e.g., AWS re:Invent 2024"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
            </div>
        </div>

        {{-- Row 2: Issuing Organization * & Issue Date * --}}
        <div wire:ignore.self class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- 3. Issuing Organization * --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Issuing Organization <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    x-model="form.organizer" 
                    @input="errors.organizer = false"
                    placeholder="e.g., Amazon Web Services"
                    :class="errors.organizer ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.organizer" x-cloak class="text-[11px] text-red-500 mt-1">Please enter issuing organization</p>
            </div>

            {{-- 4. Issue Date * (Month Picker) --}}
            <div>
                <x-profile.month-picker 
                    label="Issue Date" 
                    :required="true" 
                    model="form.month_year" 
                    placeholder="---------, ----" 
                    hasErrorExpr="errors.month_year" 
                    :disableFuture="false" 
                />
                <p x-show="errors.month_year" x-cloak class="text-[11px] text-red-500 mt-1">Please select issue date</p>
            </div>
        </div>

        {{-- Row 3: Credential ID / URL & Upload Certificate --}}
        <div wire:ignore.self class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
            {{-- 5. Credential ID / URL --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Credential ID / URL
                </label>
                <input 
                    type="text" 
                    x-model="form.certificate_no" 
                    placeholder="Certificate ID or verification URL"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
            </div>

            {{-- 6. Upload Certificate --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Upload Certificate
                </label>

                {{-- Custom Styled File Input Container (Entire area clickable) --}}
                <label 
                    for="cert_file_input_{{ $itemId ?? 'new' }}" 
                    class="relative flex items-center h-10 px-1 border border-zinc-300 dark:border-zinc-700 rounded-xl bg-white dark:bg-zinc-900 overflow-hidden shadow-2xs cursor-pointer hover:border-zinc-400 dark:hover:border-zinc-600 focus-within:ring-2 focus-within:ring-brand/20 transition-all select-none"
                >
                    <input 
                        type="file" 
                        id="cert_file_input_{{ $itemId ?? 'new' }}" 
                        wire:model="certificateFile" 
                        @change="handleFileChange($event)" 
                        accept=".jpg,.jpeg,.png,.pdf" 
                        class="hidden" 
                    />
                    <span 
                        class="h-8 px-3.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition-colors shadow-2xs shrink-0 flex items-center justify-center"
                    >
                        Choose file
                    </span>
                    <span 
                        class="ml-3 text-xs text-zinc-600 dark:text-zinc-300 truncate flex-1" 
                        x-text="currentFileName || 'No file chosen'"
                    ></span>
                </label>

                <p class="text-[11px] text-zinc-400 mt-1">Supports .jpg, .png, .pdf (Max 5MB)</p>

                {{-- Livewire Upload Loading Indicator --}}
                <div wire:loading wire:target="certificateFile" class="text-xs text-blue-600 dark:text-blue-400 mt-1 flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                    <span>Uploading certificate...</span>
                </div>

                {{-- File validation error from Livewire --}}
                @if(isset($errors) && $errors->has('certificateFile'))
                    <p class="text-[11px] text-red-500 mt-1">{{ $errors->first('certificateFile') }}</p>
                @endif

                {{-- Certificate Preview Card (Matching Screenshot Image 2) --}}
                <div 
                    x-show="(previewUrl || (form.image_url && !removeExistingFile))" 
                    x-cloak 
                    class="mt-3 p-3 bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/80 rounded-xl shadow-2xs"
                >
                    {{-- Header: Certificate Preview [icon] + Remove [x] --}}
                    <div class="flex items-center justify-between pb-2 mb-2 border-b border-zinc-200 dark:border-zinc-700/60">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-zinc-800 dark:text-zinc-200">
                            <i class="fa-regular fa-file-lines text-zinc-500"></i>
                            <span>Certificate Preview</span>
                        </div>
                        <button 
                            type="button" 
                            @click="removeFile()" 
                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-white bg-red-500 hover:bg-red-600 rounded-lg shadow-2xs transition-colors cursor-pointer"
                        >
                            <i class="fa-solid fa-xmark text-xs"></i>
                            <span>Remove</span>
                        </button>
                    </div>

                    {{-- Preview Body --}}
                    <div class="overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-900/60 flex items-center justify-center p-2 min-h-[140px]">
                        {{-- PDF Preview --}}
                        <template x-if="isPdfFile">
                            <div class="flex flex-col items-center justify-center py-4 text-center">
                                <i class="fa-solid fa-file-pdf text-red-500 text-4xl mb-2"></i>
                                <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200" x-text="currentFileName || 'PDF Certificate'"></span>
                                <template x-if="previewUrl || form.image_url">
                                    <a :href="previewUrl || form.image_url" target="_blank" class="mt-2 text-[11px] font-semibold text-blue-600 hover:underline inline-flex items-center gap-1">
                                        <span>View Document</span>
                                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                    </a>
                                </template>
                            </div>
                        </template>

                        {{-- Image Preview --}}
                        <template x-if="!isPdfFile && (previewUrl || form.image_url)">
                            <img 
                                :src="previewUrl || form.image_url" 
                                alt="Certificate Preview" 
                                class="max-h-56 w-full object-contain rounded-lg shadow-xs" 
                            />
                        </template>
                    </div>

                    {{-- Filename caption beneath preview --}}
                    <div class="pt-2 text-center text-xs text-zinc-500 dark:text-zinc-400 font-medium truncate" x-text="currentFileName"></div>
                </div>
            </div>
        </div>

        {{-- Row 4: Description --}}
        <div wire:ignore.self>
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                    Description
                </label>
                <span 
                    class="text-[11px] font-mono"
                    :class="form.description.length > 1500 ? 'text-red-500 font-bold' : 'text-zinc-400'"
                >
                    <span x-text="form.description.length"></span> / 1,500
                </span>
            </div>
            <textarea 
                x-model="form.description" 
                rows="3" 
                placeholder="Brief description of the certificate..."
                :class="errors.description ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                class="w-full p-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all leading-relaxed"
            ></textarea>
            <p x-show="errors.description" x-cloak class="text-[11px] text-red-500 mt-1">Description cannot exceed 1,500 characters</p>
        </div>

        {{-- Row 5: Share this with your network --}}
        <div wire:ignore.self>
            <label class="flex items-start gap-3 p-3.5 sm:p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 hover:border-zinc-300 dark:hover:border-zinc-700 transition cursor-pointer select-none">
                <input 
                    type="checkbox" 
                    x-model="form.share_network" 
                    class="mt-0.5 size-4 text-brand rounded border-zinc-300 dark:border-zinc-700 focus:ring-brand cursor-pointer shrink-0" 
                />
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <span class="text-sm">📢</span>
                        <span class="text-xs font-bold text-zinc-900 dark:text-white">Share this with your network</span>
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                        Create a post to share this new certificate with your professional network
                    </p>
                </div>
            </label>
        </div>

        {{-- Row 6: Form Actions & Status (Admin Only) --}}
        <div wire:ignore.self class="pt-3 border-t border-zinc-200/80 dark:border-zinc-800 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-2.5 flex-wrap">
                <button 
                    type="button" 
                    @click="saveItem()"
                    :disabled="isSaving"
                    class="inline-flex items-center gap-2 px-5 py-2 text-xs font-bold text-white bg-brand hover:bg-brand-600 rounded-xl shadow-xs hover:shadow transition-all cursor-pointer disabled:opacity-60"
                >
                    <i x-show="isSaving" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                    <span x-text="form.id ? 'Update Certificate' : 'Save Certificate'"></span>
                </button>

                <button 
                    type="button" 
                    @click="closeForm()"
                    class="px-4 py-2 text-xs font-semibold text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded-xl hover:bg-zinc-50 dark:hover:bg-zinc-700/60 transition cursor-pointer shadow-2xs"
                >
                    Cancel
                </button>

                @if($showStatus)
                    <div class="flex items-center gap-3 sm:ml-2 sm:pl-3 sm:border-l sm:border-zinc-200 dark:sm:border-zinc-700">
                        <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300">Status:</span>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-medium text-zinc-700 dark:text-zinc-200 select-none" @click="form.status = 1">
                            <input 
                                type="radio" 
                                name="form_status_cert_{{ $itemId ?? 'new' }}" 
                                value="1" 
                                :checked="form.status == 1 || form.status === '1'"
                                @change="form.status = 1"
                                class="size-4 text-brand focus:ring-brand cursor-pointer"
                            />
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">
                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                Active
                            </span>
                        </label>

                        <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-medium text-zinc-700 dark:text-zinc-200 select-none" @click="form.status = 0">
                            <input 
                                type="radio" 
                                name="form_status_cert_{{ $itemId ?? 'new' }}" 
                                value="0" 
                                :checked="form.status == 0 || form.status === '0'"
                                @change="form.status = 0"
                                class="size-4 text-brand focus:ring-brand cursor-pointer"
                            />
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">
                                <span class="size-1.5 rounded-full bg-zinc-400"></span>
                                Inactive
                            </span>
                        </label>
                    </div>
                @endif
            </div>

            @if($showStatus)
                <template x-if="form.id">
                    <button 
                        type="button" 
                        @click="promptDelete(form.id)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-red-600 hover:text-white bg-red-50 hover:bg-red-600 dark:bg-red-950/40 dark:hover:bg-red-600 border border-red-200 dark:border-red-900/60 rounded-xl transition cursor-pointer shadow-2xs"
                    >
                        <i class="fa-solid fa-trash-can text-xs"></i>
                        <span>Delete</span>
                    </button>
                </template>
            @endif
        </div>

    </div>
</div>
