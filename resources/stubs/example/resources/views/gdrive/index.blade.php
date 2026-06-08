@extends('gdrive.layout')

@section('title', 'Drive Saya')

@php
    // Redefine formatSize helper to ensure scope safety in child view
    $formatSize = function (mixed $bytes): string {
        if ($bytes === null || $bytes === '' || $bytes === 'n/a') {
            return 'n/a';
        }
        if (!is_numeric($bytes)) {
            return (string) $bytes;
        }
        $value = (float) $bytes;
        if ($value <= 0) {
            return '0 byte';
        }

        $units = ['byte', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($value, 1024));
        $power = max(0, min($power, count($units) - 1));

        $value = $value / 1024 ** $power;

        $rounded = round($value, 1);
        $precision = $power === 0 || $rounded == round($value, 0) ? 0 : 1;

        return number_format($value, $precision, ',', '.') . ' ' . $units[$power];
    };

    $fileIconClass = function (string $mimeType, string $name): string {
        if ($mimeType === 'application/vnd.google-apps.folder') {
            return 'folder';
        }
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if ($mimeType === 'application/pdf' || str_ends_with(strtolower($name), '.pdf')) {
            return 'pdf';
        }
        if (str_contains($mimeType, 'spreadsheet') || str_ends_with(strtolower($name), '.xlsx')) {
            return 'sheet';
        }
        if (str_contains($mimeType, 'document') || str_ends_with(strtolower($name), '.docx')) {
            return 'doc';
        }
        if (str_contains($mimeType, 'presentation') || str_ends_with(strtolower($name), '.pptx')) {
            return 'slide';
        }
        if (str_starts_with($mimeType, 'text/')) {
            return 'text';
        }
        return 'generic';
    };

    $fileIconLabel = function (string $type): string {
        return match ($type) {
            'image' => '🖼',
            'pdf' => 'PDF',
            'doc' => 'DOC',
            'sheet' => 'XLS',
            'slide' => 'PPT',
            'text' => 'TXT',
            'folder' => '📁',
            default => '📄',
        };
    };
@endphp

@section('content')
    @if ($connected)
        <div
            id="gdrive-content-area"
            class="bg-gd-surface rounded-[16px] border border-gd-border p-5 flex-grow flex flex-col overflow-hidden min-h-0 shadow-gd-content relative">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="flex flex-col gap-1 min-w-0">
                    <div class="flex items-baseline gap-3 flex-wrap">
                        <h1 class="font-sans text-[22px] font-normal m-0 text-gd-text" id="gdrive-page-title">Drive Saya</h1>
                        <span class="text-gd-muted text-sm" id="gdrive-file-count">{{ count($files) }} file</span>
                    </div>
                    <nav id="gdrive-breadcrumb" class="hidden flex items-center flex-wrap gap-1 text-[13px] text-gd-muted" aria-label="Lokasi folder"></nav>
                    <p id="gdrive-status" class="hidden text-[13px] text-gd-muted m-0 min-h-[18px]"></p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="gdrive-sync-btn"
                        class="inline-flex items-center justify-center gap-2 border-none rounded-[20px] py-2 px-[18px] text-[13px] font-medium cursor-pointer no-underline transition-all bg-transparent text-gd-blue hover:bg-gd-active disabled:opacity-60 disabled:cursor-not-allowed">
                        <svg id="gdrive-sync-icon" viewBox="0 0 24 24" class="w-[18px] h-[18px] fill-current" style="vertical-align: middle;">
                            <path
                                d="M19 8l-4 4h3c0 3.31-2.69 6-6 6-1.01 0-1.97-.25-2.8-.7l-1.46 1.46C8.97 19.54 10.43 20 12 20c4.42 0 8-3.58 8-8h3l-4-4zM6 12c0-3.31 2.69-6 6-6 1.01 0 1.97.25 2.8.7l1.46-1.46C15.03 4.46 13.57 4 12 4c-4.42 0-8 3.58-8 8H1l4 4 4-4H6z" />
                        </svg>
                        <span id="gdrive-sync-label">Sinkronkan</span>
                    </button>
                    <div class="inline-flex border border-gd-border rounded-full overflow-hidden bg-gd-surface"
                        role="group" aria-label="Tampilan">
                        <button type="button" id="gdrive-view-list"
                            class="gdrive-view-btn inline-flex items-center justify-center w-10 h-9 border-none cursor-pointer transition-all bg-gd-active text-gd-text"
                            title="Tampilan daftar">
                            <svg class="w-[18px] h-[18px] fill-current" viewBox="0 0 24 24">
                                <path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z" />
                            </svg>
                        </button>
                        <button type="button" id="gdrive-view-grid"
                            class="gdrive-view-btn inline-flex items-center justify-center w-10 h-9 border-none border-l border-gd-border cursor-pointer transition-all bg-gd-surface text-gd-muted hover:bg-gd-hover"
                            title="Tampilan kotak">
                            <svg class="w-[18px] h-[18px] fill-current" viewBox="0 0 24 24">
                                <path
                                    d="M4 8h4V4H4v4zm6 12h4v-4h-4v4zm-6 0h4v-4H4v4zm0-6h4v-4H4v4zm6 0h4v-4h-4v4zm6 10h4v-4h-4v4zm0-6h4v-4h-4v4zm0-6h4V4h-4v4zm6 6h4v-4h-4v4zm0-6h4V4h-4v4z" />
                            </svg>
                        </button>
                    </div>
                    <button type="button" id="gdrive-sort-btn"
                        class="inline-flex items-center gap-1.5 border-none rounded-[16px] py-1.5 px-3 text-[13px] font-medium cursor-pointer transition-all bg-gd-active text-gd-blue hover:bg-[#d2e3fc]">
                        <svg class="w-[18px] h-[18px] fill-current" viewBox="0 0 24 24">
                            <path d="M3 18h6v-2H3v2zM3 6v2h18V6H3zm0 7h12v-2H3v2z" />
                        </svg>
                        Urutkan
                    </button>
                    <!-- Hidden file inputs to trigger via JS -->
                    <input type="file" id="gdrive-files"
                        class="absolute w-px h-px p-0 m-[-1px] overflow-hidden clip-[rect(0,0,0,0)] border-none" multiple>
                    <input type="file" id="gdrive-folder-upload"
                        class="absolute w-px h-px p-0 m-[-1px] overflow-hidden clip-[rect(0,0,0,0)] border-none" multiple
                        webkitdirectory directory>
                </div>
            </div>

            <!-- Context menu (klik kanan) -->
            <div id="gdrive-context-menu"
                class="fixed hidden z-[1100] bg-gd-surface rounded-lg shadow-gd-panel border border-gd-border py-1.5 min-w-[260px]"
                role="menu" aria-hidden="true">
                <div id="gdrive-context-menu-file" class="hidden">
                    <button type="button" data-action="toggle-star"
                        class="gd-context-menu-item flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer transition-colors hover:bg-gd-hover"
                        role="menuitem">
                        <svg class="w-5 h-5 flex-shrink-0 fill-[#f4b400]" viewBox="0 0 24 24">
                            <path
                                d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
                        </svg>
                        <span class="flex-grow" id="gdrive-context-star-label">Tandai dengan bintang</span>
                    </button>
                </div>
                <div id="gdrive-context-menu-general">
                <button type="button" data-action="new-folder"
                    class="gd-context-menu-item flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer transition-colors hover:bg-gd-hover"
                    role="menuitem">
                    <svg class="w-5 h-5 flex-shrink-0 fill-gd-muted" viewBox="0 0 24 24">
                        <path
                            d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-1 12H5V8h14v10zm-9-1h4v-3h3v-4h-3V9h-4v3H8v4h3z" />
                    </svg>
                    <span class="flex-grow">Folder baru</span>
                    <span class="text-[12px] text-gd-muted">Alt+C lalu F</span>
                </button>
                <div class="border-t border-gd-border my-1.5 mx-0"></div>
                <button type="button" data-action="file-upload"
                    class="gd-context-menu-item flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer transition-colors hover:bg-gd-hover"
                    role="menuitem">
                    <svg class="w-5 h-5 flex-shrink-0 fill-gd-muted" viewBox="0 0 24 24">
                        <path
                            d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 15.01l1.41 1.41L11 14.84V19h2v-4.16l1.59 1.59L16 15.01 12.01 11 8 15.01z" />
                    </svg>
                    <span class="flex-grow">Unggah file</span>
                    <span class="text-[12px] text-gd-muted">Alt+C lalu U</span>
                </button>
                <button type="button" data-action="folder-upload"
                    class="gd-context-menu-item flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer transition-colors hover:bg-gd-hover"
                    role="menuitem">
                    <svg class="w-5 h-5 flex-shrink-0 fill-gd-muted" viewBox="0 0 24 24">
                        <path
                            d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-5 10h-3v3h-2v-3H7v-2h3V11h2v3h3v2z" />
                    </svg>
                    <span class="flex-grow">Unggah folder</span>
                    <span class="text-[12px] text-gd-muted">Alt+C lalu I</span>
                </button>
                </div>
            </div>

            <!-- Toolbar seleksi multi-file -->
            <div id="gdrive-selection-bar"
                class="hidden flex items-center justify-between mb-3 py-2.5 px-4 bg-gd-surface border border-gd-border rounded-lg shadow-[0_1px_2px_0_rgba(60,64,67,0.1)]">
                <span id="gdrive-selection-count" class="text-[14px] font-medium text-gd-text">0 dipilih</span>
                <div class="flex items-center gap-0.5">
                    <button type="button" id="gdrive-bulk-star"
                        class="w-9 h-9 p-0 rounded-full bg-transparent border-none text-gd-muted cursor-pointer flex items-center justify-center transition-all hover:bg-gd-hover hover:text-[#f4b400]"
                        title="Tandai dengan bintang">
                        <svg class="w-[20px] h-[20px] fill-current" viewBox="0 0 24 24">
                            <path
                                d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
                        </svg>
                    </button>
                    <button type="button" id="gdrive-bulk-delete"
                        class="w-9 h-9 p-0 rounded-full bg-transparent border-none text-gd-muted cursor-pointer flex items-center justify-center transition-all hover:bg-[#fce8e6] hover:text-gd-red"
                        title="Hapus">
                        <svg class="w-[20px] h-[20px] fill-current" viewBox="0 0 24 24">
                            <path
                                d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z" />
                        </svg>
                    </button>
                    <button type="button" id="gdrive-selection-clear"
                        class="w-9 h-9 p-0 rounded-full bg-transparent border-none text-gd-muted cursor-pointer flex items-center justify-center transition-all hover:bg-gd-hover"
                        title="Batal pilih">
                        <svg class="w-[20px] h-[20px] fill-current" viewBox="0 0 24 24">
                            <path
                                d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" />
                        </svg>
                    </button>
                </div>
            </div>

            <div id="gdrive-file-list-container" class="overflow-y-auto flex-grow mt-3 relative">
                <div id="gdrive-loading-overlay"
                    class="hidden absolute inset-0 z-[20] items-center justify-center bg-gd-surface/80 backdrop-blur-[1px]"
                    aria-hidden="true">
                    <div class="flex flex-col items-center gap-3 px-6 py-5 rounded-xl bg-gd-surface shadow-gd-panel border border-gd-border">
                        <svg class="gdrive-spinner w-9 h-9 text-gd-blue" viewBox="0 0 50 50" aria-hidden="true">
                            <circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="4"
                                stroke-linecap="round" stroke-dasharray="90 150" />
                        </svg>
                        <p id="gdrive-loading-message" class="text-[14px] text-gd-text m-0 font-medium">Memuat...</p>
                    </div>
                </div>
                <div id="gdrive-list-view">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr>
                            <th class="text-[13px] font-medium text-gd-muted py-3 px-2 border-b border-gd-border bg-gd-surface sticky top-0 z-[5] w-10">
                                <input type="checkbox" id="gdrive-select-all"
                                    class="w-4 h-4 cursor-pointer accent-gd-blue" title="Pilih semua">
                            </th>
                            <th class="text-[13px] font-medium text-gd-muted py-3 px-4 border-b border-gd-border bg-gd-surface sticky top-0 z-[5]"
                                style="width: 42%;">Nama</th>
                            <th class="text-[13px] font-medium text-gd-muted py-3 px-4 border-b border-gd-border bg-gd-surface sticky top-0 z-[5]"
                                style="width: 20%;">Jenis</th>
                            <th class="text-[13px] font-medium text-gd-muted py-3 px-4 border-b border-gd-border bg-gd-surface sticky top-0 z-[5]"
                                style="width: 15%;">Ukuran file</th>
                            <th class="text-[13px] font-medium text-gd-muted py-3 px-4 border-b border-gd-border bg-gd-surface sticky top-0 z-[5] text-right pr-4"
                                style="width: 20%;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody id="gdrive-file-rows">
                        @forelse ($files as $file)
                            @php
                                $iconType = $fileIconClass($file['mime_type'], $file['name']);
                                $iconLabel = $fileIconLabel($iconType);
                                $rowBgColor =
                                    $iconType === 'image'
                                    ? 'bg-[#ea4335]'
                                    : ($iconType === 'pdf'
                                        ? 'bg-[#ea4335]'
                                        : ($iconType === 'sheet'
                                            ? 'bg-[#0f9d58]'
                                            : ($iconType === 'doc'
                                                ? 'bg-[#1a73e8]'
                                                : ($iconType === 'slide'
                                                    ? 'bg-[#f4b400]'
                                                    : 'bg-[#5f6368]'))));
                            @endphp
                            @php
                                $isStarred = !empty($file['starred']);
                                $isFolder = !empty($file['is_folder']) || ($file['mime_type'] ?? '') === 'application/vnd.google-apps.folder';
                            @endphp
                            <tr class="group transition-all h-12 hover:bg-gd-hover gd-file-row-tr gd-file-item cursor-pointer"
                                data-name="{{ strtolower($file['name']) }}"
                                data-file-id="{{ $file['id'] }}"
                                data-file-name="{{ $file['name'] }}"
                                data-starred="{{ $isStarred ? '1' : '0' }}"
                                data-is-folder="{{ $isFolder ? '1' : '0' }}">
                                <td class="text-[13px] py-2 px-2 border-b border-gd-border align-middle text-gd-text">
                                    <input type="checkbox"
                                        class="gd-row-checkbox w-4 h-4 cursor-pointer accent-gd-blue"
                                        data-file-id="{{ $file['id'] }}">
                                </td>
                                <td class="text-[13px] py-2 px-4 border-b border-gd-border align-middle text-gd-text">
                                    <div class="flex items-center gap-3 max-w-[450px]">
                                        <span class="w-6 h-6 flex-shrink-0 flex items-center justify-center">
                                            <span
                                                class="inline-block py-0.5 px-1.5 rounded-[4px] text-white text-[10px] font-bold {{ $rowBgColor }}">
                                                {{ $iconLabel }}
                                            </span>
                                        </span>
                                        <span class="font-medium truncate text-gd-text"
                                            title="{{ $file['name'] }}">{{ $file['name'] }}</span>
                                    </div>
                                </td>
                                <td class="text-[13px] py-2 px-4 border-b border-gd-border align-middle text-gd-text">
                                    <span class="text-gd-muted">{{ $isFolder ? 'Folder' : $file['mime_type'] }}</span>
                                </td>
                                <td class="text-[13px] py-2 px-4 border-b border-gd-border align-middle text-gd-text">
                                    <span>{{ $isFolder ? '—' : $formatSize($file['size']) }}</span>
                                </td>
                                <td class="text-[13px] py-2 px-4 border-b border-gd-border align-middle text-gd-text">
                                    <div
                                        class="flex gap-0.5 justify-end items-center opacity-0 transition-all group-hover:opacity-100">
                                        @unless ($isFolder)
                                        <a class="gd-action-btn w-8 h-8 p-0 rounded-full bg-transparent border-none text-gd-muted cursor-pointer flex items-center justify-center transition-all hover:bg-gd-hover hover:text-gd-text"
                                            href="{{ route('gdrive.download', $file['id']) }}" title="Unduh">
                                            <svg class="w-[18px] h-[18px] fill-current" viewBox="0 0 24 24">
                                                <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z" />
                                            </svg>
                                        </a>
                                        @endunless
                                        <button type="button"
                                            class="gd-rename-btn gd-action-btn w-8 h-8 p-0 rounded-full bg-transparent border-none text-gd-muted cursor-pointer flex items-center justify-center transition-all hover:bg-gd-hover hover:text-gd-text"
                                            data-file-id="{{ $file['id'] }}"
                                            data-file-name="{{ $file['name'] }}"
                                            title="Ganti nama">
                                            <svg class="w-[18px] h-[18px] fill-current" viewBox="0 0 24 24">
                                                <path
                                                    d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1.003 1.003 0 0 0 0-1.42l-2.34-2.34a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z" />
                                            </svg>
                                        </button>
                                        <button type="button"
                                            class="gd-star-btn w-8 h-8 p-0 rounded-full bg-transparent border-none cursor-pointer flex items-center justify-center transition-all hover:bg-gd-hover {{ $isStarred ? 'text-[#f4b400]' : 'text-gd-muted' }}"
                                            data-file-id="{{ $file['id'] }}"
                                            data-starred="{{ $isStarred ? '1' : '0' }}"
                                            title="{{ $isStarred ? 'Hapus tanda bintang' : 'Tandai dengan bintang' }}">
                                            <svg class="w-[18px] h-[18px] fill-current" viewBox="0 0 24 24">
                                                <path
                                                    d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
                                            </svg>
                                        </button>
                                        <button type="button"
                                            class="gd-more-btn w-8 h-8 p-0 rounded-full bg-transparent border-none text-gd-muted cursor-pointer flex items-center justify-center transition-all hover:bg-gd-hover hover:text-gd-text"
                                            data-file-id="{{ $file['id'] }}"
                                            data-file-name="{{ $file['name'] }}"
                                            data-starred="{{ $isStarred ? '1' : '0' }}"
                                            data-is-folder="{{ $isFolder ? '1' : '0' }}"
                                            title="Lainnya">
                                            <svg class="w-[18px] h-[18px] fill-current" viewBox="0 0 24 24">
                                                <path
                                                    d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 px-4 text-center text-gd-muted">
                                    Belum ada file. Unggah file pertama Anda dengan menyeret file kemari atau klik tombol "+
                                    Baru" di sidebar.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                <div id="gdrive-grid-view" class="hidden">
                    <div id="gdrive-file-grid"
                        class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 p-1">
                    </div>
                </div>
            </div>

            <!-- Menu urutkan -->
            <div id="gdrive-sort-menu"
                class="fixed hidden z-[1200] bg-gd-surface rounded-lg shadow-gd-panel border border-gd-border py-2 min-w-[240px]"
                role="menu" aria-hidden="true">
                <div class="px-4 py-1.5 text-[12px] font-medium text-gd-muted uppercase tracking-wide">Urutkan menurut</div>
                <button type="button" data-sort-by="name"
                    class="gd-sort-option flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer hover:bg-gd-hover">
                    <span class="w-5 flex-shrink-0 text-gd-blue gd-sort-check">✓</span>
                    <span>Nama</span>
                </button>
                <button type="button" data-sort-by="modified"
                    class="gd-sort-option flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer hover:bg-gd-hover">
                    <span class="w-5 flex-shrink-0 text-gd-blue gd-sort-check invisible">✓</span>
                    <span>Tanggal diubah</span>
                </button>
                <button type="button" data-sort-by="modified_by_me"
                    class="gd-sort-option flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer hover:bg-gd-hover">
                    <span class="w-5 flex-shrink-0 text-gd-blue gd-sort-check invisible">✓</span>
                    <span>Tanggal saya ubah</span>
                </button>
                <button type="button" data-sort-by="opened_by_me"
                    class="gd-sort-option flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer hover:bg-gd-hover">
                    <span class="w-5 flex-shrink-0 text-gd-blue gd-sort-check invisible">✓</span>
                    <span>Tanggal saya buka</span>
                </button>
                <div class="border-t border-gd-border my-1.5"></div>
                <div class="px-4 py-1.5 text-[12px] font-medium text-gd-muted uppercase tracking-wide">Arah urutan</div>
                <button type="button" data-sort-dir="asc"
                    class="gd-sort-option flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer hover:bg-gd-hover">
                    <span class="w-5 flex-shrink-0 text-gd-blue gd-sort-check">✓</span>
                    <span id="gdrive-sort-dir-asc-label">A ke Z</span>
                </button>
                <button type="button" data-sort-dir="desc"
                    class="gd-sort-option flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer hover:bg-gd-hover">
                    <span class="w-5 flex-shrink-0 text-gd-blue gd-sort-check invisible">✓</span>
                    <span id="gdrive-sort-dir-desc-label">Z ke A</span>
                </button>
                <div class="border-t border-gd-border my-1.5"></div>
                <div class="px-4 py-1.5 text-[12px] font-medium text-gd-muted uppercase tracking-wide">Folder</div>
                <button type="button" data-sort-folders="top"
                    class="gd-sort-option flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer hover:bg-gd-hover">
                    <span class="w-5 flex-shrink-0 text-gd-blue gd-sort-check">✓</span>
                    <span>Di atas</span>
                </button>
                <button type="button" data-sort-folders="mixed"
                    class="gd-sort-option flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer hover:bg-gd-hover">
                    <span class="w-5 flex-shrink-0 text-gd-blue gd-sort-check invisible">✓</span>
                    <span>Campur dengan file</span>
                </button>
            </div>

            <!-- Menu detail file (tiga titik) -->
            <div id="gdrive-more-menu"
                class="fixed hidden z-[1200] bg-gd-surface rounded-lg shadow-gd-panel border border-gd-border py-1.5 min-w-[220px]"
                role="menu" aria-hidden="true">
                <button type="button" data-action="download"
                    class="gd-more-menu-item flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer hover:bg-gd-hover">
                    <svg class="w-5 h-5 flex-shrink-0 fill-gd-muted" viewBox="0 0 24 24">
                        <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z" />
                    </svg>
                    <span>Unduh</span>
                </button>
                <button type="button" data-action="rename"
                    class="gd-more-menu-item flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer hover:bg-gd-hover">
                    <svg class="w-5 h-5 flex-shrink-0 fill-gd-muted" viewBox="0 0 24 24">
                        <path
                            d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1.003 1.003 0 0 0 0-1.42l-2.34-2.34a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z" />
                    </svg>
                    <span class="flex-grow">Ganti nama</span>
                </button>
                <button type="button" data-action="copy"
                    class="gd-more-menu-item flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer hover:bg-gd-hover">
                    <svg class="w-5 h-5 flex-shrink-0 fill-gd-muted" viewBox="0 0 24 24">
                        <path
                            d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z" />
                    </svg>
                    <span>Buat salinan</span>
                </button>
                <div class="border-t border-gd-border my-1.5"></div>
                <button type="button" data-action="star"
                    class="gd-more-menu-item flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer hover:bg-gd-hover">
                    <svg class="w-5 h-5 flex-shrink-0 fill-[#f4b400]" viewBox="0 0 24 24">
                        <path
                            d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
                    </svg>
                    <span id="gdrive-more-star-label">Tandai dengan bintang</span>
                </button>
                <button type="button" data-action="info"
                    class="gd-more-menu-item flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer hover:bg-gd-hover">
                    <svg class="w-5 h-5 flex-shrink-0 fill-gd-muted" viewBox="0 0 24 24">
                        <path
                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z" />
                    </svg>
                    <span>Info file</span>
                </button>
                <div class="border-t border-gd-border my-1.5"></div>
                <button type="button" data-action="delete"
                    class="gd-more-menu-item flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer hover:bg-gd-hover">
                    <svg class="w-5 h-5 flex-shrink-0 fill-gd-muted" viewBox="0 0 24 24">
                        <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z" />
                    </svg>
                    <span>Pindahkan ke sampah</span>
                </button>
            </div>

            <!-- Modal info file -->
            <div id="gdrive-info-modal"
                class="fixed inset-0 z-[1300] hidden items-center justify-center bg-[rgba(0,0,0,0.4)]">
                <div class="bg-gd-surface rounded-lg shadow-gd-panel border border-gd-border w-[400px] max-w-[90vw]">
                    <div class="flex items-center justify-between py-4 px-5 border-b border-gd-border">
                        <h3 class="text-[16px] font-medium text-gd-text m-0">Info file</h3>
                        <button type="button" id="gdrive-info-close"
                            class="w-8 h-8 border-none bg-transparent text-gd-muted cursor-pointer rounded-full hover:bg-gd-hover flex items-center justify-center text-lg">✕</button>
                    </div>
                    <div id="gdrive-info-content" class="py-4 px-5 text-[14px] text-gd-text space-y-2"></div>
                </div>
            </div>
            <style>
                @keyframes gdrive-spin {
                    to { transform: rotate(360deg); }
                }

                .gdrive-spinner {
                    animation: gdrive-spin 0.85s linear infinite;
                    transform-origin: center;
                }

                #gdrive-content-area.is-loading {
                    pointer-events: none;
                }
            </style>
        </div>
    @else
        <div
            class="bg-gd-surface rounded-[16px] border border-gd-border p-10 flex-grow flex flex-col items-center justify-center text-center overflow-hidden min-h-[400px] shadow-gd-content">
            <svg class="w-24 h-24 mb-6" viewBox="0 0 87.3 78" xmlns="http://www.w3.org/2000/svg">
                <path d="M6.6 66.85 3.3 61.05l26.1-45.2h26.5z" fill="#0066da" />
                <path d="M58.5 66.85H29.7L3.3 15.85h29.2z" fill="#00ac47" />
                <path d="M58.5 66.85 32.1 15.85h26.4l26.1 45.2z" fill="#ea4335" />
                <path d="M29.7 66.85 16.5 44.05l13.2-22.8h26.4z" fill="#00832d" />
                <path d="M58.5 15.85 45.3 44.05 32.1 15.85z" fill="#2684fc" />
                <path d="M16.5 44.05 3.3 15.85h26.4z" fill="#ffba00" />
            </svg>
            <h2 class="font-sans text-2xl font-normal mb-3 text-gd-text">Google Drive belum terhubung</h2>
            <p class="text-gd-muted text-[15px] leading-relaxed max-w-[480px] mb-6">
                Hubungkan aplikasi ini dengan akun Google Drive Anda untuk mulai mengelola, melihat pratinjau, mengunduh,
                dan mengunggah file Anda.
            </p>
            @if ($oauthConfigured ?? false)
                <div>
                    <a href="{{ route('gdrive.connect') }}"
                        class="inline-flex items-center justify-center gap-2 border-none rounded-[24px] py-3 px-8 text-[15px] font-medium cursor-pointer transition-all bg-gd-blue text-white hover:bg-gd-blue-hover hover:shadow-[0_1px_2px_0_rgba(60,64,67,0.3)]">
                        Hubungkan Google Drive
                    </a>
                </div>
            @else
                <div class="rounded-lg p-4 mb-6 text-[14px] bg-[#fef7e0] text-[#b06000] border border-[#feefc3] max-w-[480px]">
                    OAuth belum dikonfigurasi. Isi variabel di bawah pada file <code>.env</code>, lalu jalankan
                    <code>php artisan config:clear</code> sebelum menghubungkan Google Drive.
                </div>
            @endif
            <div class="mt-10 text-[13px] text-left bg-gd-hover p-4 px-5 rounded-lg max-w-[500px] border border-gd-border">
                <strong class="text-gd-text">Petunjuk Konfigurasi Lingkungan (.env):</strong>
                <p class="margin-6px-0-12px">Pastikan Anda telah mengisi nilai-nilai berikut pada file konfigurasi
                    <code>.env</code> proyek Anda:
                </p>
                <code class="block whitespace-pre-wrap font-mono text-[12px] bg-[#e8eaed] p-2 rounded mt-1.5 leading-relaxed">GDRIVE_CLIENT_ID=masukkan_client_id_anda
                            GDRIVE_CLIENT_SECRET=masukkan_client_secret_anda
                            GDRIVE_REDIRECT_URI={{ url('/gdrive/callback') }}</code>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        window.GDRIVE_DEMO = {
            initUrl: @json(route('gdrive.api.upload.init')),
            chunkUrl: @json(route('gdrive.api.upload.chunk')),
            syncUrl: @json(route('gdrive.api.sync')),
            createFolderUrl: @json(route('gdrive.api.folders.create')),
            chunkSize: 2 * 1024 * 1024,
            csrf: @json(csrf_token()),
            previewUrlTemplate: @json(route('gdrive.preview', ['id' => '__ID__'])),
            downloadUrlTemplate: @json(route('gdrive.download', ['id' => '__ID__'])),
            deleteUrlTemplate: @json(route('gdrive.destroy', ['id' => '__ID__'])),
            starUrlTemplate: @json(route('gdrive.api.files.star', ['id' => '__ID__'])),
            renameUrlTemplate: @json(route('gdrive.api.files.rename', ['id' => '__ID__'])),
            copyUrlTemplate: @json(route('gdrive.api.files.copy', ['id' => '__ID__'])),
            infoUrlTemplate: @json(route('gdrive.api.files.show', ['id' => '__ID__'])),
            initialFiles: @json($connected ? $files : []),
            initialFolderId: @json($currentFolderId ?? null),
            initialFolderName: @json($currentFolderName ?? null),
            indexUrl: @json(route('gdrive.index')),
        };
    </script>
    <script src="{{ asset('js/gdrive-demo.js') }}?v={{ filemtime(public_path('js/gdrive-demo.js')) }}"></script>
@endpush