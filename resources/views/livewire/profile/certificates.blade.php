<?php

use App\Models\Certificate;
use App\Models\ReviewerProfile;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/**
 * Profile Certificates Section Component (Livewire Volt Single-File Component)
 *
 * Encapsulates the user's professional certifications and credentials:
 * - Certificate Name, Event Name, Issuing Organization, Issue Date
 * - Credential ID / Verification URL
 * - Certificate file upload (jpg, png, pdf) with rich preview card & removal
 * - Description & "Share this with your network" card
 * - Inline edit & bottom add forms with snapshot dirty tracking
 * - Unsaved changes confirmation modal
 * - Atomic synchronization with ReviewerProfile->certificate
 */
new class extends Component {
    use WithFileUploads;

    /**
     * User Eloquent model instance whose profile is being viewed.
     */
    public ?User $user = null;

    /**
     * Flag indicating whether current authenticated user owns this profile.
     */
    public bool $isOwnProfile = true;

    /**
     * ID of the certificate currently being edited inline.
     */
    public ?string $editingId = null;

    /**
     * Flag indicating whether Add form is open at bottom.
     */
    public bool $showAddForm = false;

    /**
     * Temporary uploaded certificate file.
     */
    public $certificateFile = null;

    /**
     * Error boundary flag.
     */
    public bool $hasError = false;

    /**
     * Component mount lifecycle hook.
     */
    public function mount(?User $user = null): void
    {
        $this->user = $user ?? $this->user;
    }

    /**
     * Catch and stop propagation of any unexpected runtime errors.
     */
    public function exception(\Throwable $e, callable $stopPropagation): void
    {
        report($e);
        $stopPropagation();
        $this->hasError = true;
    }

    /**
     * Open the Add form.
     */
    public function openAdd(): void
    {
        $this->editingId = null;
        $this->certificateFile = null;
        $this->showAddForm = true;
    }

    /**
     * Open inline editing for a certificate record.
     */
    public function openEdit(string $id): void
    {
        $this->showAddForm = false;
        $this->certificateFile = null;
        $this->editingId = $id;
    }

    /**
     * Close form and reset state.
     */
    public function closeForm(): void
    {
        $this->showAddForm = false;
        $this->editingId = null;
        $this->certificateFile = null;
    }

    /**
     * Clear currently staged certificate file.
     */
    public function clearCertificateFile(): void
    {
        $this->certificateFile = null;
    }

    /**
     * Get active certificates for this user profile.
     * Scholar/user view ONLY displays records with status 1.
     *
     * @return list<array<string, mixed>>
     */
    public function getCertificatesProperty(): array
    {
        try {
            $reviewerProfile = $this->user?->reviewerProfile ?? ($this->user ? ReviewerProfile::where('user_id', (string) $this->user->_id)->first() : null);
            $certIds = (array) ($reviewerProfile->certificate ?? []);

            // Also query by user_id to ensure legacy or directly assigned certificates are included
            $query = Certificate::query();
            if (! empty($certIds)) {
                $query->where(function ($q) use ($certIds) {
                    $q->whereIn('_id', $certIds)
                        ->orWhere('user_id', (string) $this->user->_id);
                });
            } else {
                $query->where('user_id', (string) $this->user->_id);
            }

            // ONLY active records (status = 1 or '1' or true), ordered latest first
            $records = $query->whereIn('status', [1, '1', true])
                ->orderBy('_id', 'desc')
                ->get();

            $formatted = [];
            foreach ($records as $cert) {
                $formatted[] = [
                    'id' => (string) $cert->_id,
                    'certificate' => (string) ($cert->certificate ?? ''),
                    'event_name' => (string) ($cert->event_name ?? ''),
                    'organizer' => (string) ($cert->organizer ?? ''),
                    'month_year' => (string) ($cert->month_year ?? ''),
                    'certificate_no' => (string) ($cert->certificate_no ?? ''),
                    'description' => (string) ($cert->description ?? ''),
                    'image' => (string) ($cert->image ?? ''),
                    'image_url' => $cert->image_url,
                    'file_name' => $cert->file_name,
                    'is_pdf' => (bool) $cert->is_pdf,
                    'status' => 1,
                ];
            }

            return $formatted;
        } catch (\Throwable $e) {
            report($e);
            $this->hasError = true;

            return [];
        }
    }

    /**
     * Save or update a certificate record.
     */
    public function saveCertificate(array $payload): bool
    {
        $id = ! empty($payload['id']) ? (string) $payload['id'] : null;
        $name = trim((string) ($payload['certificate'] ?? ''));
        $organizer = trim((string) ($payload['organizer'] ?? ''));
        $monthYear = trim((string) ($payload['month_year'] ?? ''));

        if ($name === '' || $organizer === '' || $monthYear === '') {
            $this->dispatch('toast', message: 'Please fill in all required fields.', type: 'danger');

            return false;
        }

        // Validate staged file upload if present
        if ($this->certificateFile) {
            try {
                $this->validate([
                    'certificateFile' => 'file|mimes:jpg,jpeg,png,pdf|max:5120',
                ]);
            } catch (\Illuminate\Validation\ValidationException $ve) {
                $errorMsg = $ve->validator->errors()->first('certificateFile') ?: 'Invalid certificate file format or size.';
                $this->dispatch('toast', message: $errorMsg, type: 'danger');

                return false;
            }
        }

        try {
            if ($id) {
                $cert = Certificate::findOrFail($id);
            } else {
                $cert = new Certificate;
                $cert->user_id = (string) $this->user->_id;
            }

            $cert->certificate = $name;
            $cert->event_name = trim((string) ($payload['event_name'] ?? '')) ?: null;
            $cert->organizer = $organizer;
            $cert->month_year = Certificate::formatMonthYear($monthYear);
            $cert->certificate_no = trim((string) ($payload['certificate_no'] ?? '')) ?: null;
            $cert->description = trim((string) ($payload['description'] ?? '')) ?: null;
            $cert->status = 1;

            // Handle file removal if requested and no new file staged
            $removeFile = ! empty($payload['remove_file']);
            if ($removeFile && ! $this->certificateFile) {
                if (! empty($cert->image) && file_exists(public_path($cert->image))) {
                    @unlink(public_path($cert->image));
                }
                $cert->image = null;
            }

            // Handle new file upload
            if ($this->certificateFile) {
                // Delete previous file if replacing
                if (! empty($cert->image) && file_exists(public_path($cert->image))) {
                    @unlink(public_path($cert->image));
                }

                $destDir = public_path('certificate');
                if (! File::isDirectory($destDir)) {
                    File::makeDirectory($destDir, 0755, true, true);
                }

                $ext = strtolower($this->certificateFile->getClientOriginalExtension());
                $filename = md5(uniqid((string) microtime(true), true)) . '.' . $ext;
                $destPath = $destDir . DIRECTORY_SEPARATOR . $filename;
                $sourcePath = $this->certificateFile->getRealPath();
                $saved = false;

                // On Windows, Symfony's UploadedFile::move() uses rename() which fails if source has a lock.
                // Using copy() + unlink() with fallback ensures reliable file saving across environments.
                if ($sourcePath && file_exists($sourcePath)) {
                    if (@copy($sourcePath, $destPath)) {
                        @unlink($sourcePath);
                        $saved = true;
                    }
                }

                if (! $saved) {
                    try {
                        $this->certificateFile->move($destDir, $filename);
                        $saved = true;
                    } catch (\Throwable $moveEx) {
                        if ($sourcePath && file_exists($sourcePath)) {
                            file_put_contents($destPath, file_get_contents($sourcePath));
                            @unlink($sourcePath);
                            $saved = true;
                        } else {
                            throw $moveEx;
                        }
                    }
                }

                $cert->image = 'certificate/' . $filename;
            }

            $cert->save();

            // Atomically append _id to ReviewerProfile->certificate if not present
            $strId = (string) $cert->_id;
            $profile = ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
            if (! $profile) {
                ReviewerProfile::create([
                    'user_id' => (string) $this->user->_id,
                    'unique_id' => (string) ($this->user->unique_id ?? ''),
                    'status' => (string) $this->user->getStatusCode(),
                    'certificate' => [$strId],
                ]);
            } else {
                $currentCerts = array_map('strval', (array) ($profile->certificate ?? []));
                if (! in_array($strId, $currentCerts, true)) {
                    $currentCerts[] = $strId;
                    $profile->certificate = array_values(array_unique($currentCerts));
                    $profile->save();
                }
            }

            $this->editingId = null;
            $this->showAddForm = false;
            $this->certificateFile = null;

            $this->dispatch('toast', message: $id ? 'Certificate updated successfully!' : 'Certificate added successfully!', type: 'success');

            return true;
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'Failed to save certificate. Please try again.', type: 'danger');

            return false;
        }
    }

    /**
     * Delete a certificate record.
     */
    public function deleteCertificate(string $id): bool
    {
        try {
            $cert = Certificate::findOrFail($id);

            // Clean up file from disk
            if (! empty($cert->image) && file_exists(public_path($cert->image))) {
                @unlink(public_path($cert->image));
            }

            $cert->delete();

            // Remove from ReviewerProfile->certificate array
            $profile = ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
            if ($profile && ! empty($profile->certificate)) {
                $certs = array_map('strval', (array) $profile->certificate);
                $profile->certificate = array_values(array_diff($certs, [(string) $id]));
                $profile->save();
            }

            if ($this->editingId === $id) {
                $this->editingId = null;
            }

            $this->dispatch('toast', message: 'Certificate deleted successfully!', type: 'success');

            return true;
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'Failed to delete certificate.', type: 'danger');

            return false;
        }
    }
};
?>

<div>
    @if ($hasError)
        <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 flex items-start gap-3 shadow-xs mb-4">
            <svg class="size-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="flex-1">
                <h3 class="text-sm font-semibold">Certificates Section Notice</h3>
                <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">
                    An error occurred while loading this section. Other profile sections remain unaffected.
                </p>
            </div>
        </div>
    @endif

    {{-- CERTIFICATES CARD CONTAINER --}}
    <div 
        id="certificates" 
        class="scroll-mt-28 bg-white dark:bg-[#111c26] border border-zinc-200/80 dark:border-zinc-800 rounded-2xl shadow-xs overflow-hidden"
        x-data="{
            showAddForm: @entangle('showAddForm'),
            editingId: @entangle('editingId'),
            isSaving: false,
            showUnsavedModal: false,
            pendingAction: null,
            originalSnapshot: '',

            // File upload & preview state
            currentFileName: '',
            previewUrl: '',
            isPdfFile: false,
            removeExistingFile: false,

            // Certificate Media Preview Modal
            showMediaModal: false,
            modalMediaUrl: '',
            modalIsPdf: false,
            modalMediaTitle: '',

            openMediaModal(url, isPdf = false, title = '') {
                if (!url) return;
                this.$dispatch('close-popovers');
                this.modalMediaUrl = url;
                this.modalIsPdf = Boolean(isPdf);
                this.modalMediaTitle = title || 'Certificate Preview';
                this.showMediaModal = true;
            },

            closeMediaModal() {
                this.showMediaModal = false;
                this.modalMediaUrl = '';
                this.modalIsPdf = false;
                this.modalMediaTitle = '';
            },

            form: {
                id: null,
                certificate: '',
                event_name: '',
                organizer: '',
                month_year: '',
                certificate_no: '',
                description: '',
                share_network: false,
                image_url: '',
                remove_file: false,
                status: 1
            },

            errors: {
                certificate: false,
                organizer: false,
                month_year: false,
                description: false
            },

            resetErrors() {
                this.errors = {
                    certificate: false,
                    organizer: false,
                    month_year: false,
                    description: false
                };
            },

            takeSnapshot() {
                return JSON.stringify({
                    ...this.form,
                    currentFileName: this.currentFileName,
                    previewUrl: this.previewUrl,
                    removeExistingFile: this.removeExistingFile
                });
            },

            isDirty() {
                return this.originalSnapshot !== '' && this.takeSnapshot() !== this.originalSnapshot;
            },

            handleFileChange(event) {
                const file = event.target.files[0];
                if (!file) return;

                this.currentFileName = file.name;
                this.removeExistingFile = false;
                this.form.remove_file = false;

                const ext = file.name.split('.').pop().toLowerCase();
                this.isPdfFile = (ext === 'pdf');

                if (this.isPdfFile) {
                    this.previewUrl = '';
                } else if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        this.previewUrl = e.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            },

            removeFile() {
                this.currentFileName = '';
                this.previewUrl = '';
                this.isPdfFile = false;
                this.removeExistingFile = true;
                this.form.remove_file = true;
                this.form.image_url = '';

                // Reset file input element
                const inputId = 'cert_file_input_' + (this.form.id || 'new');
                const fileInput = document.getElementById(inputId);
                if (fileInput) {
                    fileInput.value = '';
                }

                // Clear Livewire staged file
                this.$wire.clearCertificateFile();
            },

            openAddForm() {
                this.$dispatch('close-popovers');
                if ((this.showAddForm || this.editingId) && this.isDirty()) {
                    this.pendingAction = 'openAdd';
                    this.showUnsavedModal = true;
                    return;
                }
                this.resetErrors();
                this.currentFileName = '';
                this.previewUrl = '';
                this.isPdfFile = false;
                this.removeExistingFile = false;

                this.form = {
                    id: null,
                    certificate: '',
                    event_name: '',
                    organizer: '',
                    month_year: '',
                    certificate_no: '',
                    description: '',
                    share_network: false,
                    image_url: '',
                    remove_file: false,
                    status: 1
                };

                this.editingId = null;
                this.showAddForm = true;

                const fileInput = document.getElementById('cert_file_input_new');
                if (fileInput) {
                    fileInput.value = '';
                }

                this.$nextTick(() => {
                    this.originalSnapshot = this.takeSnapshot();
                });
            },

            openEditForm(item) {
                this.$dispatch('close-popovers');
                if ((this.showAddForm || this.editingId) && this.isDirty()) {
                    this.pendingAction = { type: 'edit', item };
                    this.showUnsavedModal = true;
                    return;
                }
                this.resetErrors();

                this.currentFileName = item.file_name || '';
                this.previewUrl = item.is_pdf ? '' : (item.image_url || '');
                this.isPdfFile = Boolean(item.is_pdf);
                this.removeExistingFile = false;

                this.form = {
                    id: item.id,
                    certificate: item.certificate || '',
                    event_name: item.event_name || '',
                    organizer: item.organizer || '',
                    month_year: item.month_year || '',
                    certificate_no: item.certificate_no || '',
                    description: item.description || '',
                    share_network: false,
                    image_url: item.image_url || '',
                    remove_file: false,
                    status: 1
                };

                this.showAddForm = false;
                this.editingId = item.id;

                const fileInput = document.getElementById('cert_file_input_' + item.id);
                if (fileInput) {
                    fileInput.value = '';
                }

                this.$nextTick(() => {
                    this.originalSnapshot = this.takeSnapshot();
                });
            },

            closeForm(force = false) {
                this.$dispatch('close-popovers');
                if (!force && this.isDirty()) {
                    this.pendingAction = 'close';
                    this.showUnsavedModal = true;
                    return;
                }
                this.showUnsavedModal = false;
                this.showAddForm = false;
                this.editingId = null;
                this.resetErrors();
                this.currentFileName = '';
                this.previewUrl = '';
                this.isPdfFile = false;
                this.removeExistingFile = false;
                this.originalSnapshot = '';
                this.$wire.closeForm();
            },

            confirmDiscard() {
                this.showUnsavedModal = false;
                const act = this.pendingAction;
                this.pendingAction = null;
                this.originalSnapshot = '';

                if (act === 'close') {
                    this.closeForm(true);
                } else if (act === 'openAdd') {
                    this.closeForm(true);
                    this.openAddForm();
                } else if (act && act.type === 'edit') {
                    this.closeForm(true);
                    this.openEditForm(act.item);
                }
            },

            cancelDiscard() {
                this.pendingAction = null;
                this.showUnsavedModal = false;
            },

            validate() {
                this.resetErrors();
                let valid = true;

                if (!this.form.certificate || this.form.certificate.trim().length < 2) {
                    this.errors.certificate = true;
                    valid = false;
                }

                if (!this.form.organizer || this.form.organizer.trim().length < 2) {
                    this.errors.organizer = true;
                    valid = false;
                }

                if (!this.form.month_year || this.form.month_year.trim() === '') {
                    this.errors.month_year = true;
                    valid = false;
                }

                const descLen = (this.form.description || '').trim().length;
                if (descLen > 1500) {
                    this.errors.description = true;
                    valid = false;
                }

                return valid;
            },

            saveItem() {
                if (!this.validate()) return;
                this.isSaving = true;

                this.form.remove_file = this.removeExistingFile;

                this.$wire.saveCertificate(this.form).then((res) => {
                    this.isSaving = false;
                    if (res) {
                        this.showAddForm = false;
                        this.editingId = null;
                        this.originalSnapshot = '';
                        this.currentFileName = '';
                        this.previewUrl = '';
                        this.isPdfFile = false;
                        this.removeExistingFile = false;
                    }
                }).catch(() => {
                    this.isSaving = false;
                });
            },

            confirmDelete(id) {
                if (confirm('Are you sure you want to delete this certificate?')) {
                    this.$wire.deleteCertificate(id).then((res) => {
                        if (res && this.editingId === id) {
                            this.closeForm(true);
                        }
                    });
                }
            }
        }"
    >
        {{-- SECTION HEADER --}}
        <div class="p-6 border-b border-zinc-100 dark:border-zinc-800">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-purple-50 dark:bg-purple-950/60 border border-purple-200 dark:border-purple-800/80 flex items-center justify-center text-purple-600 dark:text-purple-400">
                        <i class="fa-solid fa-certificate text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                            <span>Certificates</span>
                            <span class="inline-flex items-center justify-center size-5 text-xs font-bold bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 rounded-full">
                                {{ count($this->certificates) }}
                            </span>
                        </h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6">
            {{-- CERTIFICATES LIST --}}
            <div class="space-y-4">
                @forelse($this->certificates as $cert)
                    <div 
                        wire:key="profile-cert-card-{{ $cert['id'] }}" 
                        class="p-4 rounded-xl border border-zinc-200/80 dark:border-zinc-800/80 hover:border-brand dark:hover:border-brand hover:border-l-4 hover:border-l-brand hover:pl-[13px] bg-zinc-50/40 dark:bg-zinc-900/40 hover:bg-white dark:hover:bg-zinc-800/50 hover:shadow-xs transition-all duration-200"
                    >
                        {{-- DISPLAY MODE (Always visible in list) --}}
                        <div>
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-4 min-w-0 flex-1">
                                    {{-- Certificate Media Thumbnail (Image 1 / Default Fallback) --}}
                                    <button 
                                        type="button" 
                                        @click.stop="openMediaModal('{{ !empty($cert['image_url']) ? $cert['image_url'] : asset('assets/images/Peer_Review_Certificate.png') }}', {{ !empty($cert['image_url']) && $cert['is_pdf'] ? 'true' : 'false' }}, '{{ addslashes($cert['certificate']) }}')"
                                        class="size-[74px] rounded-2xl border-[2px] border-blue-400 dark:border-blue-500/50 bg-white dark:bg-zinc-800 flex flex-col items-center justify-center p-1.5 shrink-0 shadow-2xs overflow-hidden cursor-pointer hover:border-blue-500 hover:shadow-md transition-all group select-none"
                                        title="Click to view {{ !empty($cert['image_url']) && $cert['is_pdf'] ? 'PDF document' : 'certificate' }}"
                                    >
                                        @if(!empty($cert['image_url']) && $cert['is_pdf'])
                                            <svg class="size-11 group-hover:scale-105 transition-transform" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M12 6C10.8954 6 10 6.89543 10 8V40C10 41.1046 10.8954 42 12 42H36C37.1046 42 38 41.1046 38 40V16L28 6H12Z" fill="#EF4444"/>
                                                <path d="M28 6V14C28 15.1046 28.8954 16 30 16H38L28 6Z" fill="#FCA5A5"/>
                                                <text x="24" y="33" font-family="system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="9" font-weight="900" fill="white" text-anchor="middle" letter-spacing="0.5">PDF</text>
                                            </svg>
                                        @elseif(!empty($cert['image_url']))
                                            <img 
                                                src="{{ $cert['image_url'] }}" 
                                                alt="{{ $cert['certificate'] }}" 
                                                class="w-full h-full object-cover rounded-xl group-hover:scale-105 transition-transform" 
                                            />
                                        @else
                                            <img 
                                                src="{{ asset('assets/images/Peer_Review_Certificate.png') }}" 
                                                alt="{{ $cert['certificate'] }}" 
                                                class="w-full h-full object-cover rounded-xl group-hover:scale-105 transition-transform" 
                                            />
                                        @endif
                                    </button>

                                    <div class="space-y-1.5 flex-1 min-w-0">
                                        {{-- Certificate Name --}}
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h4 class="text-sm font-bold text-zinc-900 dark:text-white leading-snug">
                                                {{ $cert['certificate'] }}
                                            </h4>
                                            @if(!empty($cert['event_name']))
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-medium bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                                                    <i class="fa-regular fa-calendar-check text-[10px] text-zinc-400"></i>
                                                    <span>{{ $cert['event_name'] }}</span>
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Meta Row: Organizer, Date, Credential --}}
                                        <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400 flex-wrap">
                                            {{-- Issuing Organization --}}
                                            @if(!empty($cert['organizer']))
                                                <span class="inline-flex items-center gap-1.5 font-semibold text-zinc-700 dark:text-zinc-300">
                                                    <i class="fa-solid fa-building-columns text-[11px] text-zinc-400"></i>
                                                    <span>{{ $cert['organizer'] }}</span>
                                                </span>
                                            @endif

                                            {{-- Date --}}
                                            @if(!empty($cert['month_year']))
                                                @if(!empty($cert['organizer']))
                                                    <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                                @endif
                                                <span class="inline-flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                                    <i class="fa-regular fa-calendar text-[11px] text-zinc-400"></i>
                                                    <span>{{ $cert['month_year'] }}</span>
                                                </span>
                                            @endif

                                            {{-- Credential ID / URL --}}
                                            @if(!empty($cert['certificate_no']))
                                                <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                                @if(str_starts_with($cert['certificate_no'], 'http://') || str_starts_with($cert['certificate_no'], 'https://'))
                                                    <a 
                                                        href="{{ $cert['certificate_no'] }}" 
                                                        target="_blank" 
                                                        rel="noopener noreferrer" 
                                                        class="inline-flex items-center gap-1 text-blue-600 dark:text-blue-400 hover:underline font-medium"
                                                    >
                                                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                                        <span>View Credential</span>
                                                    </a>
                                                @else
                                                    <span class="inline-flex items-center gap-1 font-mono text-[11px] bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">
                                                        <span class="text-zinc-400">ID:</span>
                                                        <span>{{ $cert['certificate_no'] }}</span>
                                                    </span>
                                                @endif
                                            @endif
                                        </div>

                                        {{-- Description --}}
                                        @if(!empty($cert['description']))
                                            <p class="text-xs text-zinc-600 dark:text-zinc-300 mt-2 leading-relaxed whitespace-pre-line">
                                                {{ $cert['description'] }}
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                {{-- Action Buttons (Owner only) --}}
                                @if($isOwnProfile)
                                    <div class="flex items-center gap-1 shrink-0 ml-2">
                                        <button 
                                            type="button" 
                                            @click="openEditForm({{ json_encode($cert) }})"
                                            class="p-1.5 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition cursor-pointer"
                                            title="Edit certificate"
                                        >
                                            <i class="fa-solid fa-pen text-xs"></i>
                                        </button>
                                        <button 
                                            type="button" 
                                            @click="confirmDelete('{{ $cert['id'] }}')"
                                            class="p-1.5 text-zinc-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 rounded-lg transition cursor-pointer"
                                            title="Delete certificate"
                                        >
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- INLINE EDIT FORM CONTAINER --}}
                        <div x-show="editingId === '{{ $cert['id'] }}'" x-cloak class="mt-4 pt-4 border-t border-zinc-200/80 dark:border-zinc-800">
                            <x-profile.certificate-form :item-id="$cert['id']" />
                        </div>
                    </div>
                @empty
                    @if(!$showAddForm)
                        <div class="text-center py-8 text-zinc-400 dark:text-zinc-500">
                            <div class="size-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center mx-auto mb-3 text-zinc-400">
                                <i class="fa-solid fa-certificate text-xl"></i>
                            </div>
                            <p class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">No certificates added yet</p>
                            @if($isOwnProfile)
                                <p class="text-[11px] text-zinc-400 mt-0.5">Click "Add Certificate" below to showcase your verified certifications, professional licenses, and course credentials.</p>
                            @endif
                        </div>
                    @endif
                @endforelse
            </div>

            {{-- BOTTOM ADD FORM CONTAINER --}}
            <div x-show="showAddForm" x-cloak class="mt-4">
                <x-profile.certificate-form />
            </div>

            {{-- ADD CERTIFICATE BUTTON (Always At Bottom) --}}
            @if($isOwnProfile)
                <div class="mt-6 pt-2" x-show="!showAddForm">
                    <button 
                        type="button" 
                        @click="openAddForm()" 
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-brand hover:text-brand-600 hover:underline cursor-pointer transition-colors"
                    >
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Add Certificate</span>
                    </button>
                </div>
            @endif
        </div>

        {{-- UNSAVED CHANGES CONFIRMATION MODAL --}}
        @if($isOwnProfile)
            <x-profile.unsaved-modal />
        @endif

        {{-- CERTIFICATE MEDIA MODAL (Alpine.js - Image & PDF preview) --}}
        <div 
            x-show="showMediaModal" 
            x-cloak
            class="fixed inset-0 z-[99999] flex items-center justify-center p-3 sm:p-5 bg-black/60 backdrop-blur-xs"
            @keydown.escape.window="closeMediaModal()"
        >
            <div 
                @click.outside="closeMediaModal()"
                x-show="showMediaModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="bg-white dark:bg-[#111c26] rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 w-full max-w-4xl max-h-[92vh] flex flex-col overflow-hidden"
            >
                {{-- Modal Header --}}
                <div class="px-5 py-3.5 border-b border-zinc-200/80 dark:border-zinc-800 flex items-center justify-between gap-3 bg-zinc-50/60 dark:bg-zinc-900/60 shrink-0">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="size-8 rounded-lg bg-blue-50 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-800/80 flex items-center justify-center text-brand shrink-0">
                            <template x-if="modalIsPdf">
                                <i class="fa-solid fa-file-pdf text-red-500 text-sm"></i>
                            </template>
                            <template x-if="!modalIsPdf">
                                <i class="fa-regular fa-image text-emerald-500 text-sm"></i>
                            </template>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-sm font-bold text-zinc-900 dark:text-white truncate" x-text="modalMediaTitle || 'Certificate Preview'"></h3>
                            <p class="text-[11px] text-zinc-400 truncate" x-text="modalIsPdf ? 'PDF Document' : 'Image Document'"></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        {{-- Open in new tab link --}}
                        <template x-if="modalMediaUrl">
                            <a 
                                :href="modalMediaUrl" 
                                target="_blank" 
                                rel="noopener noreferrer" 
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-zinc-700 dark:text-zinc-200 hover:text-brand bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg hover:border-zinc-300 transition"
                                title="Open in new window"
                            >
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                <span class="hidden sm:inline">Open in new tab</span>
                            </a>
                        </template>

                        {{-- Close Button --}}
                        <button 
                            type="button" 
                            @click="closeMediaModal()" 
                            class="size-8 rounded-lg flex items-center justify-center text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition cursor-pointer"
                            title="Close"
                        >
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>
                </div>

                {{-- Modal Content Body --}}
                <div class="p-4 sm:p-6 overflow-auto flex-1 flex items-center justify-center bg-zinc-100/50 dark:bg-zinc-950/40 min-h-[350px]">
                    {{-- PDF Viewer --}}
                    <template x-if="modalIsPdf && modalMediaUrl">
                        <div class="w-full h-full min-h-[65vh] flex flex-col">
                            <iframe 
                                :src="modalMediaUrl" 
                                class="w-full h-full min-h-[65vh] rounded-xl border border-zinc-200 dark:border-zinc-700/80 bg-white shadow-xs"
                            ></iframe>
                        </div>
                    </template>

                    {{-- Image Viewer --}}
                    <template x-if="!modalIsPdf && modalMediaUrl">
                        <div class="flex items-center justify-center w-full h-full">
                            <img 
                                :src="modalMediaUrl" 
                                :alt="modalMediaTitle" 
                                class="max-h-[75vh] max-w-full object-contain rounded-xl shadow-md border border-zinc-200 dark:border-zinc-800" 
                            />
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
