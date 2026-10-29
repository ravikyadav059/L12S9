{{-- resources/views/components/admin/users/crop-modal.blade.php --}}
<!-- CROP PHOTO MODAL (ALPINE.JS + CROPPER.JS - CLIENT-SIDE ONLY, ZERO NETWORK ROUNDTRIPS) -->
<div 
    x-show="showCropModal" 
    x-cloak 
    @keydown.escape.window="closeCropModal()"
    class="fixed inset-0 z-50 overflow-y-auto" 
    aria-labelledby="crop-modal-title" 
    role="dialog" 
    aria-modal="true"
    style="display: none;"
>
    <div class="fixed inset-0 bg-zinc-900/70 backdrop-blur-xs transition-opacity" @click="closeCropModal()"></div>

    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div 
            class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-[#111c26] text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-4"
            @click.away="closeCropModal()"
        >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2.5">
                    <div class="size-8 rounded-lg bg-brand/10 text-brand flex items-center justify-center">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-zinc-900 dark:text-white" id="crop-modal-title">
                            Crop User Photo
                        </h3>
                        <p class="text-[11px] text-zinc-500">Position the crop box to center the user's face.</p>
                    </div>
                </div>
                <button type="button" @click="closeCropModal()" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-1 rounded-lg cursor-pointer">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Cropper Image Container -->
            <div class="w-full bg-zinc-950 rounded-xl overflow-hidden flex items-center justify-center border border-zinc-800" style="min-height: 340px; max-height: 420px;">
                <img 
                    x-ref="cropImage" 
                    :src="imageSrc" 
                    alt="Crop Preview" 
                    class="max-w-full block" 
                    style="max-height: 400px;" 
                />
            </div>

            <!-- Controls Bar -->
            <div class="flex flex-wrap items-center justify-center gap-2 pt-1 pb-1">
                <button 
                    type="button" 
                    @click="zoomCrop(0.1)" 
                    title="Zoom In" 
                    class="size-9 rounded-xl bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 font-bold flex items-center justify-center text-sm transition cursor-pointer shadow-xs"
                >
                    +
                </button>
                <button 
                    type="button" 
                    @click="zoomCrop(-0.1)" 
                    title="Zoom Out" 
                    class="size-9 rounded-xl bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 font-bold flex items-center justify-center text-sm transition cursor-pointer shadow-xs"
                >
                    -
                </button>
                <button 
                    type="button" 
                    @click="rotateCrop(-45)" 
                    title="Rotate Left 45°" 
                    class="size-9 rounded-xl bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 flex items-center justify-center text-xs transition cursor-pointer shadow-xs"
                >
                    ↺
                </button>
                <button 
                    type="button" 
                    @click="rotateCrop(45)" 
                    title="Rotate Right 45°" 
                    class="size-9 rounded-xl bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 flex items-center justify-center text-xs transition cursor-pointer shadow-xs"
                >
                    ↻
                </button>
                <button 
                    type="button" 
                    @click="resetCrop()" 
                    title="Reset Crop Box" 
                    class="px-3 h-9 rounded-xl bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 text-xs font-semibold flex items-center justify-center transition cursor-pointer shadow-xs"
                >
                    Reset
                </button>
            </div>

            <!-- Modal Actions -->
            <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-end gap-2.5">
                <button 
                    type="button" 
                    @click="closeCropModal()" 
                    :disabled="isProcessingCrop"
                    class="px-4 py-2 rounded-xl text-xs font-semibold text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition cursor-pointer"
                >
                    Cancel
                </button>
                <button 
                    type="button" 
                    @click="saveCrop()" 
                    :disabled="isProcessingCrop"
                    class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-xs font-bold text-white bg-brand hover:bg-brand-600 active:scale-[0.98] transition shadow-xs cursor-pointer disabled:opacity-50"
                >
                    <svg x-show="isProcessingCrop" class="animate-spin size-3.5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="isProcessingCrop ? 'Saving...' : 'Crop & Save'">Crop & Save</span>
                </button>
            </div>
        </div>
    </div>
</div>
