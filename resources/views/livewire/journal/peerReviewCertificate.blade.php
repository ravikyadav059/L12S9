<?php

use App\Models\ReviewerJournal;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $slug = '';

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function with(): array
    {
        $journal = ReviewerJournal::where('slug', $this->slug)->first();

        if (! $journal) {
            abort(404, 'Journal not found.');
        }

        $publisher = null;
        if (! empty($journal->user_id)) {
            $publisher = User::where('user_id', $journal->user_id)->first();
            if (! $publisher) {
                $publisher = User::find($journal->user_id);
            }
        }

        if (! $publisher) {
            $publisher = new User([
                'publisher_name' => $journal->organization_name ?: 'Scholar9 Verified Publisher',
                'publisher_organization' => $journal->organization_name,
            ]);
        }

        $safeTitle = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) ($journal->journal_title ?: $journal->title));
        $fileName = ($journal->slug ?: 'journal') . '_' . $safeTitle . '.pdf';
        $directory = public_path('assets/pdf');

        if (! file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $pdfPath = 'assets/pdf/' . $fileName;
        $fullPath = public_path($pdfPath);

        // Generate PDF via Dompdf if not already generated
        if (! file_exists($fullPath)) {
            $this->generatePdf($journal, $publisher, $fullPath);
        }

        return [
            'journal' => $journal,
            'publisher' => $publisher,
            'pdfPath' => $pdfPath,
        ];
    }

    protected function generatePdf(ReviewerJournal $journal, User $publisher, string $fullPath): void
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        $journalTitle = (string) ($journal->journal_title ?: $journal->title);
        $issn = ! empty($journal->e_issn) ? $journal->e_issn : (! empty($journal->p_issn) ? $journal->p_issn : 'N/A');
        $serialNumber = str_pad((string) ($journal->serial_number ?? 1), 8, '0', STR_PAD_LEFT);
        $certificateId = 'PRC' . $serialNumber;
        $createdAt = $journal->created_at ? date('d/m/Y', strtotime((string) $journal->created_at)) : date('d/m/Y');

        $publisherName = 'Verified Publisher';
        if (! empty($publisher->publisher_name)) {
            $publisherName = $publisher->publisher_name;
        } elseif (! empty($publisher->publisher_organization)) {
            $publisherName = $publisher->publisher_organization;
        } else {
            $fullName = trim(($publisher->first_name ?? '') . ' ' . ($publisher->last_name ?? ''));
            if (! empty($fullName)) {
                $publisherName = $fullName;
            } elseif (! empty($publisher->fullname)) {
                $publisherName = $publisher->fullname;
            }
        }

        $bgPath = public_path('images/assets/Peer_Review_Certificate.png');
        if (! file_exists($bgPath)) {
            $bgPath = public_path('assets/images/Peer_Review_Certificate.png');
        }
        $bgBase64 = file_exists($bgPath) ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($bgPath)) : '';

        $stampPath = public_path('assets/images/Stamp.png');
        if (! file_exists($stampPath)) {
            $stampPath = public_path('images/assets/Stamp.png');
        }
        $stampBase64 = file_exists($stampPath) ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($stampPath)) : '';

        $sigPath = public_path('assets/images/signature.png');
        if (! file_exists($sigPath)) {
            $sigPath = public_path('images/assets/signature.png');
        }
        $sigBase64 = file_exists($sigPath) ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($sigPath)) : '';

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Peer Review Certificate - ' . htmlspecialchars($journalTitle) . '</title>
    <style>
        @page { margin: 0; size: A4 portrait; }
        * { box-sizing: border-box; }
        body {
            width: 100%; height: 100%;
            ' . ($bgBase64 ? 'background-image: url("' . $bgBase64 . '"); background-repeat: no-repeat; background-size: contain; background-position: center top;' : 'background-color: #ffffff;') . '
            font-family: "Helvetica", "Arial", sans-serif;
            color: #000; margin: 0; padding: 0;
        }
        .container { position: relative; padding: 0; }
        .certificate-container { width: 100%; }
        .user-data { margin: 0px 80px; padding-top: 22%; display: table; width: calc(100% - 160px); }
        .user-data p { display: table-cell; width: 50%; font-size: 15px; margin: 0; color: #092A45; }
        .certificate-title-wrapper { margin: 48px 0 40px; text-align: center; }
        .title { font-size: 26px; margin: 0; font-weight: bold; letter-spacing: -0.2px; }
        .details { font-size: 18px; text-align: center; margin-top: 36px; }
        .details h3 { margin: 0px 80px 16px; font-size: 18px; font-weight: bold; }
        .details .journal-name { color: #1783DC; margin-top: 16px; margin-bottom: 20px; font-size: 21px; font-weight: bold; text-transform: uppercase; }
        .details p { font-size: 14.5px; margin: 18px 80px 0; text-align: justify; line-height: 25px; color: #092A45; }
        .official-info { width: calc(100% - 160px); margin: 48px 80px 0; display: table; }
        .official-info .stamp { display: table-cell; vertical-align: middle; width: 50%; margin: 0; }
        .stamp { width: 100%; margin: 0; }
        .signaturetest { margin: 0; text-align: right; }
        .signature { font-size: 15px; text-align: right; margin: 4px 0 0; color: #092A45; line-height: 1.3; }
        .validity { font-size: 13px; margin: 34px 80px 0; text-align: justify; line-height: 21px; color: #475569; }
        .details .journal-name { color: #1783DC; }
        .certificate-title { color: #092A45; }
        .journal-link { margin: 14px 0px 0; font-size: 14px; }
        .journal-link p { margin: 0 80px; color: #092A45; }
        .journal-link a { color: #1783DC; text-decoration: none; }
        .date { text-align: right; }
    </style>
</head>
<body>
    <div class="container">
        <div class="certificate-container">
            <div class="user-data">
                <p class="serial-number"><strong>Certificate ID: </strong>' . htmlspecialchars($certificateId) . '</p>
                <p class="date"><strong>Date of Issue: </strong>' . htmlspecialchars($createdAt) . '</p>
            </div>
            <div class="certificate-title-wrapper">
                <h1 class="title certificate-title">Certificate of Peer-Reviewed Journal</h1>
            </div>
            <div class="details">
                <h3 class="certificate-title">This Certificate is Proudly Awarded To</h3>
                <h3 class="journal-name">' . htmlspecialchars($journalTitle) . '</h3>
                <p>Scholar9 proudly certifies that the <strong>Journal: </strong> "' . htmlspecialchars($journalTitle) . '", <strong>ISSN No: </strong> ' . htmlspecialchars($issn) . ', <strong>Published by: </strong> ' . htmlspecialchars($publisherName) . ', fully adheres to the principles and standards of the peer-review process, as verified during its onboarding through Scholar9. By meeting the rigorous academic integrity criteria, this journal has been recognized as a verified peer-reviewed journal. All peer-review records for this journal are securely stored and preserved on Scholar9’s servers. This certificate has been issued based on the verified peer-review practices and compliance demonstrated by the journal.</p>
            </div>
            <div class="journal-link">
                <p><strong>Explorare Journals: </strong> <a href="https://scholar9.com/journal/' . htmlspecialchars((string) $journal->slug) . '" target="_blank">https://scholar9.com/journal/' . htmlspecialchars((string) $journal->slug) . '</a></p>
            </div>
            <div class="official-info">
                <div class="stamp">
                    <div class="signatureimage">' . ($stampBase64 ? '<img src="' . $stampBase64 . '" width="80px" height="80px" alt="Stamp">' : '') . '</div>
                </div>
                <div class="stamp signature-data">
                    ' . ($sigBase64 ? '<p class="signaturetest"><img src="' . $sigBase64 . '" width="155px" height="80px" alt="Signature"></p>' : '') . '
                    <p class="signature"><strong>Hitesh Patel,</strong><br>Director, Scholar9</p>
                </div>
            </div>
            <div class="validity">
                <p><strong>Verification Validity &amp; Disclaimer: </strong>
                    This certification remains valid as long as the journal upholds the peer-review process on Scholar9. This certificate confirms the journal’s compliance with Scholar9’s peer-review verification standards. Scholar9 is not responsible for the operational practices or content published in the journal beyond this verification scope.
                </p>
            </div>
        </div>
    </div>
</body>
</html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($fullPath, $dompdf->output());
    }
}; ?>

@php
    $journalTitle = $journal->journal_title ?: $journal->title;
    $serialNumber = str_pad((string)($journal->serial_number ?? 1), 8, '0', STR_PAD_LEFT);
    $certificateId = 'PRC' . $serialNumber;
    $createdAt = $journal->created_at ? date('d/m/Y', strtotime($journal->created_at)) : date('d/m/Y');
@endphp

<div 
    x-data="{
        copied: false,
        copyLink() {
            navigator.clipboard.writeText(window.location.href);
            this.copied = true;
            setTimeout(() => this.copied = false, 2500);
        }
    }"
    class="min-h-screen bg-zinc-100/80 dark:bg-zinc-950 font-sans pb-12"
>

    <!-- Structured JSON-LD Data for Certificate -->
    @php
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'DigitalDocument',
            'name' => 'Peer Review Certificate - ' . $journalTitle,
            'identifier' => $certificateId,
            'datePublished' => $createdAt,
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'Scholar9',
                'url' => url('/'),
            ],
            'about' => [
                '@type' => 'Periodical',
                'name' => $journalTitle,
            ],
        ];
    @endphp
    <script type="application/ld+json">
    {!! json_encode(array_filter($jsonLd), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>

    <!-- Top Action Toolbar -->
    <div class="sticky top-14 sm:top-16 z-40 w-full bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md border-b border-zinc-200/80 dark:border-zinc-800 shadow-xs mb-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-4">
            
            <!-- Left: Back Button & Title Info -->
            <div class="flex items-center gap-3 min-w-0">
                <a 
                    href="{{ route('journal.show', ['slug' => $journal->slug]) }}" 
                    wire:navigate 
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-zinc-700 dark:text-zinc-200 bg-zinc-100 dark:bg-zinc-800 hover:bg-[#198BEA] hover:text-white dark:hover:bg-[#198BEA] dark:hover:text-white rounded-lg transition-all shadow-2xs shrink-0 cursor-pointer group"
                >
                    <flux:icon name="arrow-left" class="size-3.5 transition-transform group-hover:-translate-x-0.5" />
                    <span class="hidden sm:inline">Back to Journal</span>
                    <span class="sm:hidden">Back</span>
                </a>

                <div class="hidden md:flex items-center gap-2 min-w-0 border-l border-zinc-200 dark:border-zinc-800 pl-3">
                    <span class="text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">{{ $certificateId }}</span>
                    <span class="text-zinc-300 dark:text-zinc-700">•</span>
                    <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 truncate max-w-sm">{{ $journalTitle }}</span>
                </div>
            </div>

            <!-- Right: Actions (Copy Link, Share) -->
            <div class="flex items-center gap-2 shrink-0">
                <!-- Copy Link Button -->
                <button 
                    type="button" 
                    @click="copyLink()" 
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-zinc-100 dark:bg-zinc-800 hover:bg-[#198BEA] hover:text-white dark:hover:bg-[#198BEA] dark:hover:text-white text-zinc-700 dark:text-zinc-300 text-xs font-semibold transition shadow-2xs cursor-pointer"
                    title="Copy Certificate Link"
                >
                    <flux:icon name="link" class="size-3.5" x-show="!copied" />
                    <flux:icon name="check" class="size-3.5 text-emerald-500" x-show="copied" style="display: none;" />
                    <span x-show="!copied">Copy Link</span>
                    <span x-show="copied" class="text-emerald-600 dark:text-emerald-400 font-bold" style="display: none;">Copied!</span>
                </button>

                <!-- Share Button -->
                <button 
                    type="button" 
                    @click="$dispatch('open-share-modal', { url: @js(url()->current()), title: @js('Peer Review Certificate - ' . $journalTitle), type: 'journal', header: 'Share Certificate', subtitle: 'Share this verified Peer Review Certificate' })"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-zinc-100 dark:bg-zinc-800 hover:bg-[#198BEA] hover:text-white dark:hover:bg-[#198BEA] dark:hover:text-white text-zinc-700 dark:text-zinc-300 text-xs font-semibold transition shadow-2xs cursor-pointer"
                    title="Share Certificate"
                >
                    <flux:icon name="share" class="size-3.5" />
                    <span class="hidden sm:inline">Share</span>
                </button>
            </div>

        </div>
    </div>

    <!-- PDF Viewer Container -->
    <main class="max-w-5xl mx-auto px-4 sm:px-6">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-xl border border-zinc-200/80 dark:border-zinc-800 p-2 sm:p-4 overflow-hidden">
            <iframe 
                src="{{ asset($pdfPath) }}#view=FitH" 
                class="w-full h-[78vh] sm:h-[84vh] rounded-xl border-0"
                title="Peer Review Certificate PDF Viewer"
            >
            </iframe>
        </div>
    </main>

    <!-- Global Dynamic Share Modal -->
    <x-modals.share />

</div>

