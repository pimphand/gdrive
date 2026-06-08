<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Drive') — Google Drive Demo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@400;500&display=swap"
        rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        gd: {
                            bg: '#f8f9fa',
                            surface: '#ffffff',
                            text: '#202124',
                            muted: '#5f6368',
                            border: '#dadce0',
                            blue: '#1a73e8',
                            'blue-hover': '#1557b0',
                            green: '#188038',
                            red: '#d93025',
                            hover: '#f1f3f4',
                            active: '#e8f0fe',
                            'active-text': '#1a73e8',
                        }
                    },
                    fontFamily: {
                        sans: ['Roboto', 'Google Sans', 'sans-serif'],
                    },
                    boxShadow: {
                        'gd': '0 1px 3px 0 rgba(60,64,67,0.3), 0 4px 8px 3px rgba(60,64,67,0.15)',
                        'gd-hover': '0 4px 8px 3px rgba(60,64,67,0.15), 0 8px 12px 5px rgba(60,64,67,0.1)',
                        'gd-panel': '0 4px 16px rgba(60,64,67,0.28)',
                        'gd-content': '0 1px 2px 0 rgba(60,64,67,0.1), 0 2px 6px 2px rgba(60,64,67,0.05)',
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gd-bg text-gd-text font-sans text-[14px] leading-normal h-screen overflow-hidden m-0">
    @php
        // Share format size function with views
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
    @endphp

    <div class="flex flex-col h-screen overflow-hidden">
        <!-- Topbar / Header -->
        <header
            class="flex items-center justify-between h-16 px-5 bg-gd-surface border-b border-gd-border relative z-10">
            <div class="flex items-center gap-3 min-w-[220px]">
                <a href="{{ route('gdrive.index') }}"
                    class="flex items-center gap-2 font-sans text-[22px] font-normal text-gd-muted no-underline">
                    <svg class="w-9 h-9" viewBox="0 0 87.3 78" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6.6 66.85 3.3 61.05l26.1-45.2h26.5z" fill="#0066da" />
                        <path d="M58.5 66.85H29.7L3.3 15.85h29.2z" fill="#00ac47" />
                        <path d="M58.5 66.85 32.1 15.85h26.4l26.1 45.2z" fill="#ea4335" />
                        <path d="M29.7 66.85 16.5 44.05l13.2-22.8h26.4z" fill="#00832d" />
                        <path d="M58.5 15.85 45.3 44.05 32.1 15.85z" fill="#2684fc" />
                        <path d="M16.5 44.05 3.3 15.85h26.4z" fill="#ffba00" />
                    </svg>
                    <span><strong class="text-gd-text font-medium">Drive</strong></span>
                </a>
            </div>

            @if (isset($connected) && $connected)
                <div
                    class="flex items-center max-w-[720px] flex-grow bg-gd-hover rounded-[24px] px-4 h-12 mx-10 transition-all border border-transparent focus-within:bg-gd-surface focus-within:shadow-[0_1px_1px_0_rgba(65,69,73,0.3),0_1px_3px_1px_rgba(65,69,73,0.15)]">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0 fill-gd-muted" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
                    </svg>
                    <input type="text" id="gd-search-input"
                        class="border-none bg-transparent text-base text-gd-text w-full outline-none placeholder-gd-muted font-normal"
                        placeholder="Cari di Drive" autocomplete="off">
                </div>
            @endif

            <div class="flex items-center gap-3 flex-shrink-0">
                @if (isset($connected) && $connected)
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-block py-0.5 px-2.5 rounded-[12px] text-[12px] font-medium bg-[#e6f4ea] text-gd-green">Terhubung</span>
                        <form method="POST" action="{{ route('gdrive.disconnect') }}"
                            onsubmit="return confirm('Putus koneksi Google Drive?')">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center justify-center gap-2 border-none rounded-[20px] py-1.5 px-3 text-[12px] font-medium cursor-pointer transition-all bg-transparent text-gd-red hover:bg-[#fce8e6]">Putuskan</button>
                        </form>
                    </div>
                @else
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-block py-0.5 px-2.5 rounded-[12px] text-[12px] font-medium bg-[#fce8e6] text-gd-red">Belum
                            terhubung</span>
                        @if ($oauthConfigured ?? \Pimphand\GDrive\GoogleDriveService::oauthConfigured())
                            <a href="{{ route('gdrive.connect') }}"
                                class="inline-flex items-center justify-center gap-2 border-none rounded-[20px] py-1.5 px-3 text-[12px] font-medium cursor-pointer transition-all bg-gd-blue text-white hover:bg-gd-blue-hover hover:shadow-[0_1px_2px_0_rgba(60,64,67,0.3)]">Hubungkan
                                Google Drive</a>
                        @else
                            <span
                                class="inline-flex items-center justify-center gap-2 rounded-[20px] py-1.5 px-3 text-[12px] font-medium bg-gd-hover text-gd-muted cursor-not-allowed"
                                title="Isi GDRIVE_CLIENT_ID dan GDRIVE_CLIENT_SECRET di .env">OAuth belum dikonfigurasi</span>
                        @endif
                    </div>
                @endif
            </div>
        </header>

        <div class="flex flex-1 overflow-hidden relative">
            @if (isset($connected) && $connected)
                <!-- Sidebar -->
                <aside
                    class="w-[256px] flex-shrink-0 py-4 flex flex-col justify-between overflow-y-auto bg-gd-bg hidden md:flex">
                    <div class="px-3">
                        <div class="relative mb-5 ml-2">
                            <button type="button"
                                class="inline-flex items-center gap-3 bg-gd-surface text-[#3c4043] border-none rounded-[24px] py-3 pr-6 pl-4 text-[14px] font-medium shadow-gd cursor-pointer transition-all hover:bg-[#f6fafe] hover:shadow-gd-hover hover:text-gd-blue"
                                id="gdrive-sidebar-upload-btn" aria-haspopup="menu" aria-expanded="false"
                                aria-controls="gdrive-new-menu">
                                <svg class="w-6 h-6" viewBox="0 0 36 36">
                                    <path fill="#34A853" d="M16 16v14h4V20z" />
                                    <path fill="#4285F4" d="M30 16H20l-4 4h14z" />
                                    <path fill="#FBBC05" d="M6 16v4h10l4-4z" />
                                    <path fill="#EA4335" d="M20 16V6h-4v10z" />
                                </svg>
                                <span>Baru</span>
                            </button>
                        </div>

                        <nav>
                            <ul class="list-none p-0 m-0">
                                <li class="mb-0.5">
                                    <a href="#" id="gdrive-nav-all" data-view="all"
                                        class="gdrive-nav-link flex items-center gap-4 py-2.5 pr-4 pl-6 text-gd-blue-hover no-underline text-[14px] font-medium rounded-r-[24px] bg-gd-active">
                                        <svg class="w-5 h-5 flex-shrink-0 fill-current" viewBox="0 0 24 24">
                                            <path
                                                d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z" />
                                        </svg>
                                        <span>Drive Saya</span>
                                    </a>
                                </li>
                                <li class="mb-0.5">
                                    <a href="#"
                                        class="flex items-center gap-4 py-2.5 pr-4 pl-6 text-[#3c4043] no-underline text-[14px] font-medium rounded-r-[24px] transition-all hover:bg-[rgba(0,0,0,0.04)]">
                                        <svg class="w-5 h-5 flex-shrink-0 fill-current" viewBox="0 0 24 24">
                                            <path
                                                d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4zM4 6h16v10H4V6z" />
                                        </svg>
                                        <span>Komputer</span>
                                    </a>
                                </li>
                                <li class="mb-0.5">
                                    <a href="#"
                                        class="flex items-center gap-4 py-2.5 pr-4 pl-6 text-[#3c4043] no-underline text-[14px] font-medium rounded-r-[24px] transition-all hover:bg-[rgba(0,0,0,0.04)]">
                                        <svg class="w-5 h-5 flex-shrink-0 fill-current" viewBox="0 0 24 24">
                                            <path
                                                d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2c1.66 0 3-1.34 3-3S7.66 5 6 5 3 6.34 3 8s1.34 3 3 3zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4zm-9 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45v1.5H1v-1.5c0-1.92 3.73-2.98 5-3z" />
                                        </svg>
                                        <span>Dibagikan dengan saya</span>
                                    </a>
                                </li>
                                <li class="mb-0.5">
                                    <a href="#"
                                        class="flex items-center gap-4 py-2.5 pr-4 pl-6 text-[#3c4043] no-underline text-[14px] font-medium rounded-r-[24px] transition-all hover:bg-[rgba(0,0,0,0.04)]">
                                        <svg class="w-5 h-5 flex-shrink-0 fill-current" viewBox="0 0 24 24">
                                            <path
                                                d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z" />
                                        </svg>
                                        <span>Terbaru</span>
                                    </a>
                                </li>
                                <li class="mb-0.5">
                                    <a href="#" id="gdrive-nav-starred" data-view="starred"
                                        class="gdrive-nav-link flex items-center gap-4 py-2.5 pr-4 pl-6 text-[#3c4043] no-underline text-[14px] font-medium rounded-r-[24px] transition-all hover:bg-[rgba(0,0,0,0.04)]">
                                        <svg class="w-5 h-5 flex-shrink-0 fill-current" viewBox="0 0 24 24">
                                            <path
                                                d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
                                        </svg>
                                        <span>Berbintang</span>
                                    </a>
                                </li>
                                <li class="mb-0.5">
                                    <a href="#"
                                        class="flex items-center gap-4 py-2.5 pr-4 pl-6 text-[#3c4043] no-underline text-[14px] font-medium rounded-r-[24px] transition-all hover:bg-[rgba(0,0,0,0.04)]">
                                        <svg class="w-5 h-5 flex-shrink-0 fill-current" viewBox="0 0 24 24">
                                            <path
                                                d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z" />
                                        </svg>
                                        <span>Sampah</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>

                    @if (isset($quota))
                        <div class="p-6">
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center gap-2 text-gd-text text-[13px]">
                                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                                        <path
                                            d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM19 18H6c-2.21 0-4-1.79-4-4 0-2.05 1.53-3.76 3.56-3.97l1.07-.11.5-.95C8.08 7.14 9.94 6 12 6c2.62 0 4.88 1.86 5.39 4.43l.3 1.5 1.53.11c1.56.1 2.78 1.41 2.78 2.96 0 1.65-1.35 3-3 3z" />
                                    </svg>
                                    <span>Penyimpanan</span>
                                </div>
                                @php
                                    $usedBytes = (float) $quota['used_bytes'];
                                    $totalBytes = isset($quota['total_bytes']) ? (float) $quota['total_bytes'] : 0;
                                    $percentage = $totalBytes > 0 ? min(100, ($usedBytes / $totalBytes) * 100) : 0;
                                    $isWarning = $percentage > 85;
                                @endphp
                                <div class="h-1 bg-[#e8eaed] rounded-[2px] overflow-hidden w-full">
                                    <div class="gd-storage-fill h-full bg-gd-blue transition-all duration-300 ease-in-out {{ $isWarning ? 'bg-gd-red' : '' }}"
                                        style="width: {{ $percentage }}%"></div>
                                </div>
                                <div class="gd-storage-text text-[12px] text-gd-muted">
                                    {{ $formatSize($quota['used_bytes']) }} dari
                                    {{ isset($quota['total_bytes']) ? $formatSize($quota['total_bytes']) : 'Tak terbatas' }}
                                    digunakan
                                </div>
                            </div>
                        </div>
                    @endif
                </aside>
            @endif

            <main class="flex-grow p-6 pb-6 overflow-y-auto flex flex-col h-full md:pt-5 md:pr-6 md:pb-6 md:pl-0">
                <!-- Drag-and-drop indicator overlay -->
                <div id="gd-drag-overlay"
                    class="absolute top-5 left-0 right-6 bottom-6 bg-[rgba(26,115,232,0.08)] border-2 border-dashed border-gd-blue rounded-[16px] z-[999] hidden items-center justify-center pointer-events-none text-gd-blue text-[20px] font-medium">
                    <span>Lepas file untuk mengunggah langsung ke Drive</span>
                </div>

                @if (session('success'))
                    <div
                        class="rounded-lg p-4 mb-4 text-[14px] flex items-center gap-2 bg-[#e6f4ea] text-[#137333] border border-[#ceead6]">
                        <span class="text-[18px]">✓</span>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif
                @if (session('error'))
                    <div
                        class="rounded-lg p-4 mb-4 text-[14px] flex items-center gap-2 bg-[#fce8e6] text-[#c5221f] border border-[#fad2cf]">
                        <span class="text-[18px]">✕</span>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif
                @if (!empty($error))
                    <div
                        class="rounded-lg p-4 mb-4 text-[14px] flex items-center gap-2 bg-[#fce8e6] text-[#c5221f] border border-[#fad2cf]">
                        <span class="text-[18px]">✕</span>
                        <span>{{ $error }}</span>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @if (isset($connected) && $connected)
        <div id="gdrive-new-menu"
            class="fixed hidden z-[1300] bg-gd-surface rounded-lg shadow-gd-panel border border-gd-border py-1.5 min-w-[280px]"
            role="menu" aria-hidden="true">
            <button type="button" data-action="new-folder"
                class="gd-new-menu-item flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer transition-colors hover:bg-gd-hover"
                role="menuitem">
                <svg class="w-5 h-5 flex-shrink-0 fill-gd-muted" viewBox="0 0 24 24">
                    <path
                        d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-1 12H5V8h14v10zm-9-1h4v-3h3v-4h-3V9h-4v3H8v4h3z" />
                </svg>
                <span class="flex-grow">Folder baru</span>
                <span class="text-[12px] text-gd-muted whitespace-nowrap">Alt+C lalu F</span>
            </button>
            <div class="border-t border-gd-border my-1.5 mx-0"></div>
            <button type="button" data-action="file-upload"
                class="gd-new-menu-item flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer transition-colors hover:bg-gd-hover"
                role="menuitem">
                <svg class="w-5 h-5 flex-shrink-0 fill-gd-muted" viewBox="0 0 24 24">
                    <path
                        d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 15.01l1.41 1.41L11 14.84V19h2v-4.16l1.59 1.59L16 15.01 12.01 11 8 15.01z" />
                </svg>
                <span class="flex-grow">Unggah file</span>
                <span class="text-[12px] text-gd-muted whitespace-nowrap">Alt+C lalu U</span>
            </button>
            <button type="button" data-action="folder-upload"
                class="gd-new-menu-item flex items-center gap-3 w-full border-none bg-transparent text-left py-2 px-4 text-[14px] text-gd-text cursor-pointer transition-colors hover:bg-gd-hover"
                role="menuitem">
                <svg class="w-5 h-5 flex-shrink-0 fill-gd-muted" viewBox="0 0 24 24">
                    <path
                        d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-5 10h-3v3h-2v-3H7v-2h3V11h2v3h3v2z" />
                </svg>
                <span class="flex-grow">Unggah folder</span>
                <span class="text-[12px] text-gd-muted whitespace-nowrap">Alt+C lalu I</span>
            </button>
        </div>
    @endif

    <!-- Upload Status Panel (Google Drive style) -->
    <div id="gdrive-upload-panel"
        class="fixed right-6 bottom-6 w-[380px] bg-gd-surface rounded-[8px] shadow-gd-panel z-[1000] overflow-hidden hidden border border-gd-border"
        aria-live="polite">
        <div class="flex items-center justify-between py-3 px-4 border-b border-gd-border text-[14px] font-medium text-gd-text">
            <span id="gdrive-upload-panel-title">Mengunggah 1 item</span>
            <div class="flex gap-1">
                <button type="button" id="gdrive-upload-minimize"
                    class="text-gd-muted w-7 h-7 hover:bg-gd-hover flex items-center justify-center rounded-full text-base"
                    title="Perkecil">▾</button>
                <button type="button" id="gdrive-upload-close"
                    class="text-gd-muted w-7 h-7 hover:bg-gd-hover flex items-center justify-center rounded-full text-sm"
                    title="Tutup">✕</button>
            </div>
        </div>
        <div id="gdrive-upload-panel-body">
            <div id="gdrive-upload-status-bar"
                class="flex items-center justify-between py-2.5 px-4 bg-[#e8f0fe] text-[13px] text-gd-text border-b border-[#d2e3fc]">
                <span id="gdrive-upload-status-text">Memulai unggahan...</span>
                <button type="button" id="gdrive-upload-cancel"
                    class="border-none bg-transparent text-gd-blue text-[13px] font-medium cursor-pointer hover:underline p-0">Batal</button>
            </div>
            <div class="max-h-[240px] overflow-y-auto" id="gdrive-upload-panel-list"></div>
        </div>
    </div>
    <style>
        #gdrive-upload-panel.is-minimized #gdrive-upload-panel-body { display: none; }
    </style>

    @stack('scripts')
</body>

</html>
