/**
 * pimphand/gdrive — upload demo dengan panel status seperti Google Drive
 */
(function () {
    var config = window.GDRIVE_DEMO || {};
    var fileInput = document.getElementById('gdrive-files');
    var folderInput = document.getElementById('gdrive-folder-upload');
    var contentArea = document.getElementById('gdrive-content-area');
    var contextMenu = document.getElementById('gdrive-context-menu');
    var fileContextSection = document.getElementById('gdrive-context-menu-file');
    var generalContextSection = document.getElementById('gdrive-context-menu-general');
    var contextStarLabel = document.getElementById('gdrive-context-star-label');
    var pageTitleEl = document.getElementById('gdrive-page-title');
    var breadcrumbEl = document.getElementById('gdrive-breadcrumb');
    var navAll = document.getElementById('gdrive-nav-all');
    var navStarred = document.getElementById('gdrive-nav-starred');
    var sidebarUploadBtn = document.getElementById('gdrive-sidebar-upload-btn');
    var newMenu = document.getElementById('gdrive-new-menu');
    var syncBtn = document.getElementById('gdrive-sync-btn');
    var syncIcon = document.getElementById('gdrive-sync-icon');
    var syncLabel = document.getElementById('gdrive-sync-label');
    var statusEl = document.getElementById('gdrive-status');
    var loadingOverlay = document.getElementById('gdrive-loading-overlay');
    var loadingMessageEl = document.getElementById('gdrive-loading-message');
    var fileListEl = document.getElementById('gdrive-file-rows');
    var fileCountEl = document.getElementById('gdrive-file-count');
    var panel = document.getElementById('gdrive-upload-panel');
    var panelTitle = document.getElementById('gdrive-upload-panel-title');
    var panelList = document.getElementById('gdrive-upload-panel-list');
    var panelMinimize = document.getElementById('gdrive-upload-minimize');
    var panelClose = document.getElementById('gdrive-upload-close');
    var panelBody = document.getElementById('gdrive-upload-panel-body');
    var uploadStatusText = document.getElementById('gdrive-upload-status-text');
    var uploadCancelBtn = document.getElementById('gdrive-upload-cancel');
    var uploadStatusBar = document.getElementById('gdrive-upload-status-bar');
    var selectionBar = document.getElementById('gdrive-selection-bar');
    var selectionCountEl = document.getElementById('gdrive-selection-count');
    var selectAllCheckbox = document.getElementById('gdrive-select-all');
    var bulkStarBtn = document.getElementById('gdrive-bulk-star');
    var bulkDeleteBtn = document.getElementById('gdrive-bulk-delete');
    var selectionClearBtn = document.getElementById('gdrive-selection-clear');
    var moreMenu = document.getElementById('gdrive-more-menu');
    var moreStarLabel = document.getElementById('gdrive-more-star-label');
    var infoModal = document.getElementById('gdrive-info-modal');
    var infoContent = document.getElementById('gdrive-info-content');
    var infoCloseBtn = document.getElementById('gdrive-info-close');
    var sortBtn = document.getElementById('gdrive-sort-btn');
    var sortMenu = document.getElementById('gdrive-sort-menu');
    var sortDirAscLabel = document.getElementById('gdrive-sort-dir-asc-label');
    var sortDirDescLabel = document.getElementById('gdrive-sort-dir-desc-label');
    var viewListBtn = document.getElementById('gdrive-view-list');
    var viewGridBtn = document.getElementById('gdrive-view-grid');
    var listViewEl = document.getElementById('gdrive-list-view');
    var gridViewEl = document.getElementById('gdrive-grid-view');
    var fileGridEl = document.getElementById('gdrive-file-grid');
    var searchInput = document.getElementById('gd-search-input');
    var chunkSize = config.chunkSize || (2 * 1024 * 1024);
    var uploadItems = [];
    var allFiles = [];
    var currentView = 'all';
    var contextTargetFileId = null;
    var contextTargetStarred = false;
    var moreMenuTarget = null;
    var selectedFileIds = {};
    var uploadCancelled = false;
    var sortConfig = {
        by: 'name',
        dir: 'asc',
        foldersOnTop: true,
    };
    var displayMode = 'list';
    var currentFolderId = null;
    var folderStack = [];
    var FOLDER_STATE_KEY = 'gdrive_folder_state';
    var loadingCount = 0;
    var statusHideTimer = null;

    if (!config.initUrl || !config.chunkUrl || !config.syncUrl || !fileInput) {
        return;
    }

    function setLoading(active, message) {
        if (active) {
            loadingCount += 1;
            if (loadingMessageEl && message) {
                loadingMessageEl.textContent = message;
            }
            if (loadingOverlay) {
                loadingOverlay.classList.remove('hidden');
                loadingOverlay.classList.add('flex');
                loadingOverlay.setAttribute('aria-hidden', 'false');
            }
            if (contentArea) {
                contentArea.classList.add('is-loading');
                contentArea.setAttribute('aria-busy', 'true');
            }
            return;
        }

        loadingCount = Math.max(0, loadingCount - 1);
        if (loadingCount === 0) {
            if (loadingOverlay) {
                loadingOverlay.classList.add('hidden');
                loadingOverlay.classList.remove('flex');
                loadingOverlay.setAttribute('aria-hidden', 'true');
            }
            if (contentArea) {
                contentArea.classList.remove('is-loading');
                contentArea.removeAttribute('aria-busy');
            }
        }
    }

    function setSyncButtonLoading(active, label) {
        if (syncBtn) {
            syncBtn.disabled = !!active;
        }
        if (syncIcon) {
            syncIcon.classList.toggle('gdrive-spinner', !!active);
        }
        if (syncLabel && label) {
            syncLabel.textContent = label;
        } else if (syncLabel && !active) {
            syncLabel.textContent = 'Sinkronkan';
        }
    }

    async function withLoading(message, task, options) {
        var opts = options || {};
        var useOverlay = opts.overlay !== false;
        var useSyncButton = !!opts.syncButton;

        if (useOverlay) {
            setLoading(true, message);
        }
        if (useSyncButton) {
            setSyncButtonLoading(true, message);
        }

        try {
            return await task();
        } finally {
            if (useOverlay) {
                setLoading(false);
            }
            if (useSyncButton) {
                setSyncButtonLoading(false);
            }
        }
    }

    function setStatus(message, isError) {
        if (!statusEl) return;

        if (statusHideTimer) {
            clearTimeout(statusHideTimer);
            statusHideTimer = null;
        }

        if (!message) {
            statusEl.classList.add('hidden');
            statusEl.textContent = '';
            return;
        }

        statusEl.textContent = message;
        statusEl.style.color = isError ? '#c5221f' : '#5f6368';
        statusEl.classList.remove('hidden');

        if (!isError) {
            statusHideTimer = setTimeout(function () {
                if (statusEl) {
                    statusEl.classList.add('hidden');
                }
            }, 4000);
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Modern file size formatting with comma decimals
    function formatSize(bytes) {
        var value = Number(bytes);
        if (isNaN(value) || value <= 0) return '0 byte';

        var units = ['byte', 'KB', 'MB', 'GB', 'TB'];
        var power = Math.floor(Math.log(value) / Math.log(1024));
        power = Math.max(0, Math.min(power, units.length - 1));

        value = value / Math.pow(1024, power);

        var rounded = Math.round(value * 10) / 10;
        var precision = (power === 0 || rounded === Math.round(value)) ? 0 : 1;

        var formatted = value.toFixed(precision);
        formatted = formatted.replace('.', ',');

        var parts = formatted.split(',');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return parts.join(',') + ' ' + units[power];
    }

    function fileIconInfo(file) {
        var name = (file.name || '').toLowerCase();
        var mime = file.mime_type || file.type || '';

        if (file.is_folder || mime === 'application/vnd.google-apps.folder') {
            return { cls: 'folder', label: '📁' };
        }
        if (mime.indexOf('image/') === 0) return { cls: 'image', label: '🖼' };
        if (mime === 'application/pdf' || name.endsWith('.pdf')) return { cls: 'pdf', label: 'PDF' };
        if (mime.indexOf('spreadsheet') !== -1 || name.endsWith('.xlsx')) return { cls: 'sheet', label: 'XLS' };
        if (mime.indexOf('document') !== -1 || name.endsWith('.docx')) return { cls: 'doc', label: 'DOC' };
        if (mime.indexOf('presentation') !== -1 || name.endsWith('.pptx')) return { cls: 'slide', label: 'PPT' };
        if (mime.indexOf('text/') === 0 || name.endsWith('.txt')) return { cls: 'text', label: 'TXT' };
        return { cls: 'generic', label: '📄' };
    }

    function truncateName(name, max) {
        if (name.length <= max) return name;
        return name.slice(0, max - 3) + '...';
    }

    function showPanel() {
        if (!panel) return;
        panel.classList.remove('hidden');
        panel.classList.remove('is-minimized');
        uploadCancelled = false;
    }

    function hidePanel() {
        if (!panel) return;
        panel.classList.add('hidden');
        panel.classList.remove('is-minimized');
    }

    function renderCircularProgress(percent, status) {
        if (status === 'done') {
            return '<span class="w-5 h-5 bg-gd-green rounded-full flex items-center justify-center text-[11px] font-bold text-white">✓</span>';
        }
        if (status === 'error') {
            return '<span class="w-5 h-5 bg-gd-red rounded-full flex items-center justify-center text-[11px] font-bold text-white">!</span>';
        }
        if (status === 'cancelled') {
            return '<span class="w-5 h-5 bg-gd-muted rounded-full flex items-center justify-center text-[11px] font-bold text-white">✕</span>';
        }

        var circumference = 62.83;
        var offset = circumference * (1 - percent / 100);

        return '<svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24">'
            + '<circle cx="12" cy="12" r="10" fill="none" stroke="#e8eaed" stroke-width="2.5"></circle>'
            + '<circle cx="12" cy="12" r="10" fill="none" stroke="#1a73e8" stroke-width="2.5"'
            + ' stroke-dasharray="' + circumference + '" stroke-dashoffset="' + offset + '"'
            + ' stroke-linecap="round" transform="rotate(-90 12 12)"></circle>'
            + '</svg>';
    }

    function updatePanelTitle() {
        if (!panelTitle) return;
        var done = uploadItems.filter(function (i) { return i.status === 'done'; }).length;
        var total = uploadItems.length;
        var uploading = uploadItems.filter(function (i) { return i.status === 'uploading'; }).length;
        var failed = uploadItems.filter(function (i) { return i.status === 'error'; }).length;
        var cancelled = uploadItems.filter(function (i) { return i.status === 'cancelled'; }).length;

        if (uploading > 0) {
            panelTitle.textContent = 'Mengunggah ' + total + ' item';
        } else if (failed > 0) {
            panelTitle.textContent = done + ' selesai, ' + failed + ' gagal';
        } else if (cancelled > 0) {
            panelTitle.textContent = done + ' selesai, ' + cancelled + ' dibatalkan';
        } else if (done === total && total > 0) {
            panelTitle.textContent = done + ' unggahan selesai';
        } else {
            panelTitle.textContent = 'Mengunggah ' + total + ' item';
        }
    }

    function updateUploadStatusBar() {
        if (!uploadStatusText) return;

        var uploading = uploadItems.filter(function (i) { return i.status === 'uploading'; }).length;
        var done = uploadItems.filter(function (i) { return i.status === 'done'; }).length;
        var total = uploadItems.length;

        if (uploading > 0) {
            uploadStatusText.textContent = 'Mengunggah ' + (done + 1) + ' dari ' + total + '...';
            if (uploadStatusBar) uploadStatusBar.classList.remove('hidden');
            if (uploadCancelBtn) uploadCancelBtn.classList.remove('hidden');
        } else if (done === total && total > 0) {
            uploadStatusText.textContent = 'Unggahan selesai';
            if (uploadCancelBtn) uploadCancelBtn.classList.add('hidden');
        } else if (uploadCancelled) {
            uploadStatusText.textContent = 'Unggahan dibatalkan';
            if (uploadCancelBtn) uploadCancelBtn.classList.add('hidden');
        } else {
            uploadStatusText.textContent = 'Memulai unggahan...';
            if (uploadCancelBtn) uploadCancelBtn.classList.remove('hidden');
        }
    }

    function renderPanel() {
        if (!panelList) return;

        panelList.innerHTML = uploadItems.map(function (item) {
            var icon = fileIconInfo(item);
            var progress = item.status === 'done' ? 100 : (item.progress || 0);
            var statusHtml = renderCircularProgress(progress, item.status);

            var iconBgColor = icon.cls === 'folder' ? 'bg-[#5f6368]' :
                             (icon.cls === 'image' ? 'bg-[#ea4335]' :
                             (icon.cls === 'pdf' ? 'bg-[#ea4335]' :
                             (icon.cls === 'sheet' ? 'bg-[#0f9d58]' :
                             (icon.cls === 'doc' ? 'bg-[#1a73e8]' :
                             (icon.cls === 'slide' ? 'bg-[#f4b400]' : 'bg-[#5f6368]')))));

            return '<div class="flex items-center gap-3 py-3 px-4 border-b border-[#f1f3f4] last:border-none" data-id="' + item.id + '">'
                + '<span class="inline-flex w-6 h-6 flex-shrink-0 items-center justify-center rounded-[4px] text-white text-[10px] font-bold ' + iconBgColor + '">' + icon.label + '</span>'
                + '<div class="flex-grow min-w-0 overflow-hidden text-ellipsis whitespace-nowrap text-[13px] text-gd-text" title="' + escapeHtml(item.name) + '">' + escapeHtml(truncateName(item.name, 42)) + '</div>'
                + '<div class="w-6 h-6 flex-shrink-0 flex items-center justify-center">' + statusHtml + '</div>'
                + '</div>';
        }).join('');

        updatePanelTitle();
        updateUploadStatusBar();
    }

    function initUploadItems(files) {
        uploadCancelled = false;
        uploadItems = files.map(function (file, index) {
            return {
                id: 'upload-' + Date.now() + '-' + index,
                name: file.name,
                type: file.type,
                status: 'uploading',
                progress: 0,
            };
        });
        showPanel();
        renderPanel();
    }

    function cancelUpload() {
        uploadCancelled = true;
        uploadItems.forEach(function (item) {
            if (item.status === 'uploading') {
                item.status = 'cancelled';
            }
        });
        renderPanel();
        if (syncBtn) syncBtn.disabled = false;
    }

    function getSelectedCount() {
        return Object.keys(selectedFileIds).length;
    }

    function updateSelectionUI() {
        var count = getSelectedCount();

        if (selectionBar) {
            selectionBar.classList.toggle('hidden', count === 0);
            selectionBar.classList.toggle('flex', count > 0);
        }

        if (selectionCountEl) {
            selectionCountEl.textContent = count + ' dipilih';
        }

        document.querySelectorAll('.gd-file-item').forEach(function (item) {
            var fileId = item.getAttribute('data-file-id');
            var selected = !!selectedFileIds[fileId];

            if (item.classList.contains('gd-file-row-tr')) {
                item.classList.toggle('bg-[#e8f0fe]', selected);
                item.classList.toggle('hover:bg-gd-hover', !selected);
            } else {
                item.classList.toggle('ring-2', selected);
                item.classList.toggle('ring-gd-blue', selected);
                item.classList.toggle('bg-[#e8f0fe]', selected);
            }

            var checkbox = item.querySelector('.gd-row-checkbox');
            if (checkbox) checkbox.checked = selected;
        });

        if (selectAllCheckbox) {
            var visibleRows = Array.from(document.querySelectorAll('.gd-file-item')).filter(function (row) {
                return row.style.display !== 'none';
            });
            var visibleIds = visibleRows.map(function (row) { return row.getAttribute('data-file-id'); });
            var allSelected = visibleIds.length > 0 && visibleIds.every(function (id) { return !!selectedFileIds[id]; });
            selectAllCheckbox.checked = allSelected;
            selectAllCheckbox.indeterminate = !allSelected && visibleIds.some(function (id) { return !!selectedFileIds[id]; });
        }
    }

    function toggleFileSelection(fileId, forceState) {
        if (!fileId) return;

        var shouldSelect = typeof forceState === 'boolean' ? forceState : !selectedFileIds[fileId];
        if (shouldSelect) {
            selectedFileIds[fileId] = true;
        } else {
            delete selectedFileIds[fileId];
        }
        updateSelectionUI();
    }

    function clearSelection() {
        selectedFileIds = {};
        updateSelectionUI();
    }

    async function bulkStarSelected() {
        var ids = Object.keys(selectedFileIds);
        if (!ids.length) return;

        var toStar = ids.filter(function (id) {
            var file = allFiles.find(function (f) { return f.id === id; });
            return file && !file.starred;
        });

        var toUnstar = ids.filter(function (id) {
            var file = allFiles.find(function (f) { return f.id === id; });
            return file && file.starred;
        });

        var targets = toStar.length ? toStar : toUnstar;
        var starred = toStar.length > 0;

        try {
            await withLoading('Memperbarui tanda bintang...', async function () {
                for (var i = 0; i < targets.length; i++) {
                    await toggleStar(targets[i], starred, true);
                }
            });
            setStatus('Tanda bintang diperbarui.', false);
        } catch (error) {
            setStatus(error.message || 'Gagal memperbarui tanda bintang.', true);
        }
    }

    async function bulkDeleteSelected() {
        var ids = Object.keys(selectedFileIds);
        if (!ids.length) return;
        if (!confirm('Hapus ' + ids.length + ' item dari Google Drive?')) return;

        try {
            await withLoading('Menghapus file...', async function () {
                for (var i = 0; i < ids.length; i++) {
                    var deleteUrl = config.deleteUrlTemplate.replace('__ID__', encodeURIComponent(ids[i]));
                    await fetch(deleteUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': config.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: new URLSearchParams({ _token: config.csrf, _method: 'DELETE' }),
                    });
                }

                clearSelection();
                await syncFiles();
            });
            setStatus(ids.length + ' item dihapus.', false);
        } catch (error) {
            setStatus(error.message || 'Gagal menghapus file.', true);
        }
    }

    function setItemProgress(id, percent) {
        uploadItems.forEach(function (item) {
            if (item.id === id) item.progress = percent;
        });
        renderPanel();
    }

    function setItemStatus(id, status) {
        uploadItems.forEach(function (item) {
            if (item.id === id) {
                item.status = status;
                if (status === 'done') item.progress = 100;
            }
        });
        renderPanel();
    }

    // Dynamic quota storage element updates
    function updateQuotaDisplay(quota) {
        var fillEl = document.querySelector('.gd-storage-fill');
        var textEl = document.querySelector('.gd-storage-text');
        if (!quota || (!fillEl && !textEl)) return;

        var used = Number(quota.used_bytes) || 0;
        var total = Number(quota.total_bytes) || 0;
        var percent = total > 0 ? Math.min(100, (used / total) * 100) : 0;

        if (fillEl) {
            fillEl.style.width = percent + '%';
            if (percent > 85) {
                fillEl.classList.add('bg-gd-red');
                fillEl.classList.remove('bg-gd-blue');
            } else {
                fillEl.classList.add('bg-gd-blue');
                fillEl.classList.remove('bg-gd-red');
            }
        }

        if (textEl) {
            var totalStr = total > 0 ? formatSize(total) : 'Tak terbatas';
            textEl.textContent = formatSize(used) + ' dari ' + totalStr + ' digunakan';
        }
    }

    function renderStarButton(file) {
        var starred = !!file.starred;
        var starClass = starred ? 'text-[#f4b400]' : 'text-gd-muted';

        return '<button type="button"'
            + ' class="gd-star-btn w-8 h-8 p-0 rounded-full bg-transparent border-none cursor-pointer flex items-center justify-center transition-all hover:bg-gd-hover ' + starClass + '"'
            + ' data-file-id="' + escapeHtml(file.id) + '"'
            + ' data-starred="' + (starred ? '1' : '0') + '"'
            + ' title="' + (starred ? 'Hapus tanda bintang' : 'Tandai dengan bintang') + '">'
            + '<svg class="w-[18px] h-[18px] fill-current" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>'
            + '</button>';
    }

    function getIconBgColor(icon) {
        return icon.cls === 'folder' ? 'bg-[#5f6368]' :
            (icon.cls === 'image' ? 'bg-[#ea4335]' :
            (icon.cls === 'pdf' ? 'bg-[#ea4335]' :
            (icon.cls === 'sheet' ? 'bg-[#0f9d58]' :
            (icon.cls === 'doc' ? 'bg-[#1a73e8]' :
            (icon.cls === 'slide' ? 'bg-[#f4b400]' : 'bg-[#5f6368]')))));
    }

    function setDisplayMode(mode) {
        displayMode = mode === 'grid' ? 'grid' : 'list';

        if (listViewEl) listViewEl.classList.toggle('hidden', displayMode !== 'list');
        if (gridViewEl) gridViewEl.classList.toggle('hidden', displayMode !== 'grid');
        if (selectAllCheckbox) {
            selectAllCheckbox.closest('th').style.display = displayMode === 'list' ? '' : 'none';
        }

        if (viewListBtn) {
            viewListBtn.classList.toggle('bg-gd-active', displayMode === 'list');
            viewListBtn.classList.toggle('text-gd-text', displayMode === 'list');
            viewListBtn.classList.toggle('bg-gd-surface', displayMode !== 'list');
            viewListBtn.classList.toggle('text-gd-muted', displayMode !== 'list');
        }

        if (viewGridBtn) {
            viewGridBtn.classList.toggle('bg-gd-active', displayMode === 'grid');
            viewGridBtn.classList.toggle('text-gd-text', displayMode === 'grid');
            viewGridBtn.classList.toggle('bg-gd-surface', displayMode !== 'grid');
            viewGridBtn.classList.toggle('text-gd-muted', displayMode !== 'grid');
        }

        renderFiles(allFiles);
    }

    function renderGridPreview(icon, iconBgColor, isFolder) {
        if (isFolder) {
            return '<svg class="w-16 h-16 fill-[#5f6368] opacity-80" viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>';
        }

        return '<div class="flex flex-col items-center gap-2">'
            + '<span class="inline-flex w-14 h-14 items-center justify-center rounded-[8px] text-white text-[22px] font-bold ' + iconBgColor + '">' + icon.label + '</span>'
            + '<span class="text-[11px] text-gd-muted uppercase tracking-wide">' + escapeHtml(icon.cls) + '</span>'
            + '</div>';
    }

    function renderGridCard(file) {
        var icon = fileIconInfo(file);
        var isFolder = !!file.is_folder || file.mime_type === 'application/vnd.google-apps.folder';
        var iconBgColor = getIconBgColor(icon);
        var isSelected = !!selectedFileIds[file.id];
        var cardClass = isSelected ? 'ring-2 ring-gd-blue bg-[#e8f0fe]' : 'bg-gd-surface hover:shadow-gd';

        return '<div class="gd-file-card gd-file-item group rounded-[12px] border border-gd-border overflow-hidden cursor-pointer transition-all ' + cardClass + '"'
            + ' data-name="' + escapeHtml(file.name.toLowerCase()) + '"'
            + ' data-file-id="' + escapeHtml(file.id) + '"'
            + ' data-file-name="' + escapeHtml(file.name) + '"'
            + ' data-starred="' + (file.starred ? '1' : '0') + '"'
            + ' data-is-folder="' + (isFolder ? '1' : '0') + '">'
            + '<div class="flex items-center gap-2 px-2.5 py-2 bg-[#f1f3f4] border-b border-gd-border min-h-[40px]">'
            + '<span class="inline-flex w-5 h-5 flex-shrink-0 items-center justify-center rounded-[3px] text-white text-[9px] font-bold ' + iconBgColor + '">' + icon.label + '</span>'
            + '<span class="flex-grow truncate text-[13px] font-medium text-gd-text" title="' + escapeHtml(file.name) + '">' + escapeHtml(truncateName(file.name, 22)) + '</span>'
            + (file.starred ? '<svg class="w-4 h-4 flex-shrink-0 fill-[#f4b400]" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>' : '')
            + '<button type="button"'
            + ' class="gd-more-btn w-7 h-7 p-0 rounded-full bg-transparent border-none text-gd-muted cursor-pointer flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all hover:bg-gd-hover"'
            + ' data-file-id="' + escapeHtml(file.id) + '"'
            + ' data-file-name="' + escapeHtml(file.name) + '"'
            + ' data-starred="' + (file.starred ? '1' : '0') + '"'
            + ' data-is-folder="' + (isFolder ? '1' : '0') + '"'
            + ' title="Lainnya">'
            + '<svg class="w-[18px] h-[18px] fill-current" viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>'
            + '</button>'
            + '</div>'
            + '<div class="h-[130px] flex items-center justify-center bg-gd-surface p-4">'
            + renderGridPreview(icon, iconBgColor, isFolder)
            + '</div>'
            + '</div>';
    }

    function renderListRow(file) {
        var icon = fileIconInfo(file);
        var isFolder = icon.cls === 'folder';
        var iconBgColor = getIconBgColor(icon);
        var actionsHtml = renderRowActions(file);
        var isSelected = !!selectedFileIds[file.id];
        var rowClass = isSelected ? 'bg-[#e8f0fe]' : 'hover:bg-gd-hover';

        return '<tr class="group transition-all h-12 gd-file-row-tr gd-file-item cursor-pointer ' + rowClass + '"'
            + ' data-name="' + escapeHtml(file.name.toLowerCase()) + '"'
            + ' data-file-id="' + escapeHtml(file.id) + '"'
            + ' data-file-name="' + escapeHtml(file.name) + '"'
            + ' data-starred="' + (file.starred ? '1' : '0') + '"'
            + ' data-is-folder="' + (isFolder ? '1' : '0') + '"'
            + (isFolder ? ' title="Klik dua kali untuk membuka folder"' : '') + '>'
            + '<td class="text-[13px] py-2 px-2 border-b border-gd-border align-middle text-gd-text">'
            + '<input type="checkbox" class="gd-row-checkbox w-4 h-4 cursor-pointer accent-gd-blue"'
            + ' data-file-id="' + escapeHtml(file.id) + '"' + (isSelected ? ' checked' : '') + '>'
            + '</td>'
            + '<td class="text-[13px] py-2 px-4 border-b border-gd-border align-middle text-gd-text">'
            + '<div class="flex items-center gap-3 max-w-[450px]">'
            + '<span class="w-6 h-6 flex-shrink-0 flex items-center justify-center">'
            + '<span class="inline-block py-0.5 px-1.5 rounded-[4px] text-white text-[10px] font-bold ' + iconBgColor + '">' + icon.label + '</span>'
            + '</span>'
            + '<span class="font-medium truncate text-gd-text" title="' + escapeHtml(file.name) + '">' + escapeHtml(file.name) + '</span>'
            + '</div>'
            + '</td>'
            + '<td class="text-[13px] py-2 px-4 border-b border-gd-border align-middle text-gd-text">'
            + '<span class="text-gd-muted">' + escapeHtml(isFolder ? 'Folder' : file.mime_type) + '</span>'
            + '</td>'
            + '<td class="text-[13px] py-2 px-4 border-b border-gd-border align-middle text-gd-text">'
            + '<span>' + (isFolder ? '—' : formatSize(file.size)) + '</span>'
            + '</td>'
            + '<td class="text-[13px] py-2 px-4 border-b border-gd-border align-middle text-gd-text">'
            + '<div class="flex gap-0.5 justify-end items-center opacity-0 transition-all group-hover:opacity-100">'
            + actionsHtml
            + '</div>'
            + '</td>'
            + '</tr>';
    }

    function renderRowActions(file) {
        var isFolder = !!file.is_folder || file.mime_type === 'application/vnd.google-apps.folder';
        var downloadUrl = config.downloadUrlTemplate.replace('__ID__', encodeURIComponent(file.id));
        var downloadBtn = isFolder ? '' :
            '<a class="gd-action-btn w-8 h-8 p-0 rounded-full bg-transparent border-none text-gd-muted cursor-pointer flex items-center justify-center transition-all hover:bg-gd-hover hover:text-gd-text"'
            + ' href="' + downloadUrl + '" title="Unduh">'
            + '<svg class="w-[18px] h-[18px] fill-current" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>'
            + '</a>';

        return downloadBtn
            + '<button type="button"'
            + ' class="gd-rename-btn gd-action-btn w-8 h-8 p-0 rounded-full bg-transparent border-none text-gd-muted cursor-pointer flex items-center justify-center transition-all hover:bg-gd-hover hover:text-gd-text"'
            + ' data-file-id="' + escapeHtml(file.id) + '"'
            + ' data-file-name="' + escapeHtml(file.name) + '"'
            + ' title="Ganti nama">'
            + '<svg class="w-[18px] h-[18px] fill-current" viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1.003 1.003 0 0 0 0-1.42l-2.34-2.34a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z"/></svg>'
            + '</button>'
            + renderStarButton(file)
            + '<button type="button"'
            + ' class="gd-more-btn w-8 h-8 p-0 rounded-full bg-transparent border-none text-gd-muted cursor-pointer flex items-center justify-center transition-all hover:bg-gd-hover hover:text-gd-text"'
            + ' data-file-id="' + escapeHtml(file.id) + '"'
            + ' data-file-name="' + escapeHtml(file.name) + '"'
            + ' data-starred="' + (file.starred ? '1' : '0') + '"'
            + ' data-is-folder="' + (isFolder ? '1' : '0') + '"'
            + ' title="Lainnya">'
            + '<svg class="w-[18px] h-[18px] fill-current" viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>'
            + '</button>';
    }

    function hideMoreMenu() {
        if (!moreMenu) return;
        moreMenu.classList.add('hidden');
        moreMenu.setAttribute('aria-hidden', 'true');
        moreMenuTarget = null;
    }

    function showMoreMenu(btn) {
        if (!moreMenu || !btn) return;

        moreMenuTarget = {
            id: btn.getAttribute('data-file-id'),
            name: btn.getAttribute('data-file-name'),
            starred: btn.getAttribute('data-starred') === '1',
            isFolder: btn.getAttribute('data-is-folder') === '1',
        };

        var downloadItem = moreMenu.querySelector('[data-action="download"]');
        if (downloadItem) {
            downloadItem.classList.toggle('hidden', moreMenuTarget.isFolder);
        }

        if (moreStarLabel) {
            moreStarLabel.textContent = moreMenuTarget.starred ? 'Hapus tanda bintang' : 'Tandai dengan bintang';
        }

        moreMenu.classList.remove('hidden');
        moreMenu.setAttribute('aria-hidden', 'false');

        var rect = btn.getBoundingClientRect();
        var menuWidth = moreMenu.offsetWidth || 220;
        var menuHeight = moreMenu.offsetHeight || 280;
        var left = Math.max(8, Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8));
        var top = Math.max(8, Math.min(rect.bottom + 4, window.innerHeight - menuHeight - 8));

        moreMenu.style.left = left + 'px';
        moreMenu.style.top = top + 'px';
    }

    function showInfoModal(contentHtml) {
        if (!infoModal || !infoContent) return;
        infoContent.innerHTML = contentHtml;
        infoModal.classList.remove('hidden');
        infoModal.classList.add('flex');
    }

    function hideInfoModal() {
        if (!infoModal) return;
        infoModal.classList.add('hidden');
        infoModal.classList.remove('flex');
    }

    function getActiveFolderId() {
        if (folderStack.length) {
            return folderStack[folderStack.length - 1].id;
        }

        return currentFolderId || null;
    }

    function persistFolderState() {
        try {
            sessionStorage.setItem(FOLDER_STATE_KEY, JSON.stringify({
                currentFolderId: currentFolderId,
                folderStack: folderStack,
            }));
        } catch (error) {
            // ignore storage errors
        }
    }

    function restoreFolderState() {
        try {
            var saved = sessionStorage.getItem(FOLDER_STATE_KEY);
            if (!saved) return;

            var state = JSON.parse(saved);
            if (!state || !Array.isArray(state.folderStack) || !state.folderStack.length) {
                return;
            }

            folderStack = state.folderStack;
            currentFolderId = state.currentFolderId || folderStack[folderStack.length - 1].id;
            updatePageTitle();
            renderBreadcrumb();
        } catch (error) {
            // ignore invalid session state
        }
    }

    function buildUploadInitPayload(file) {
        var payload = {
            name: file.name,
            mime_type: file.type || 'application/octet-stream',
            size: file.size,
        };

        var parentId = getActiveFolderId();
        if (parentId) {
            payload.parent_id = parentId;
            payload.folder_id = parentId;
        }

        if (file.webkitRelativePath && file.webkitRelativePath.indexOf('/') !== -1) {
            payload.relative_path = file.webkitRelativePath;
        }

        return payload;
    }

    function buildSyncOptions() {
        var body = {};
        var folderId = getActiveFolderId();
        if (folderId) {
            body.folder_id = folderId;
        }

        return {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': config.csrf,
            },
            body: JSON.stringify(body),
        };
    }

    function updateFolderUrl() {
        if (!config.indexUrl || !window.history || !window.history.replaceState) return;

        var url = new URL(config.indexUrl, window.location.origin);
        if (currentFolderId) {
            url.searchParams.set('folder', currentFolderId);
        } else {
            url.searchParams.delete('folder');
        }

        window.history.replaceState({ folderId: currentFolderId }, '', url.pathname + url.search);
    }

    function renderBreadcrumb() {
        if (!breadcrumbEl) return;

        if (!folderStack.length) {
            breadcrumbEl.innerHTML = '';
            breadcrumbEl.classList.add('hidden');
            return;
        }

        breadcrumbEl.classList.remove('hidden');

        var parts = [
            '<button type="button" class="gd-breadcrumb-item border-none bg-transparent p-0 text-gd-blue cursor-pointer hover:underline"'
            + ' data-folder-index="-1">Drive Saya</button>',
        ];

        folderStack.forEach(function (item, index) {
            parts.push('<span class="text-gd-muted mx-0.5" aria-hidden="true">›</span>');

            if (index === folderStack.length - 1) {
                parts.push('<span class="text-gd-text font-medium truncate max-w-[200px]" title="' + escapeHtml(item.name) + '">'
                    + escapeHtml(item.name) + '</span>');
            } else {
                parts.push('<button type="button" class="gd-breadcrumb-item border-none bg-transparent p-0 text-gd-blue cursor-pointer hover:underline truncate max-w-[160px]"'
                    + ' data-folder-index="' + index + '" title="' + escapeHtml(item.name) + '">'
                    + escapeHtml(item.name) + '</button>');
            }
        });

        breadcrumbEl.innerHTML = parts.join('');
    }

    async function navigateToFolder(index) {
        if (index < 0) {
            folderStack = [];
            currentFolderId = null;
        } else {
            folderStack = folderStack.slice(0, index + 1);
            currentFolderId = folderStack[folderStack.length - 1].id;
        }

        currentView = 'all';
        setActiveNav('all');
        clearSelection();
        hideContextMenu();
        hideMoreMenu();
        updatePageTitle();
        renderBreadcrumb();
        updateFolderUrl();
        persistFolderState();

        try {
            return await withLoading('Memuat folder...', async function () {
                var synced = await syncFiles();
                setStatus('Folder dimuat.', false);
                return synced;
            });
        } catch (error) {
            setStatus(error.message || 'Gagal memuat folder.', true);
            throw error;
        }
    }

    async function openFolder(folderId, folderName) {
        if (!folderId) return;

        folderStack.push({ id: folderId, name: folderName });
        currentFolderId = folderId;
        currentView = 'all';
        setActiveNav('all');
        clearSelection();
        hideContextMenu();
        hideMoreMenu();
        updatePageTitle();
        renderBreadcrumb();
        updateFolderUrl();
        persistFolderState();

        try {
            return await withLoading('Membuka folder...', async function () {
                var synced = await syncFiles();
                setStatus('Folder dibuka.', false);
                return synced;
            });
        } catch (error) {
            folderStack.pop();
            currentFolderId = folderStack.length ? folderStack[folderStack.length - 1].id : null;
            updatePageTitle();
            renderBreadcrumb();
            updateFolderUrl();
            setStatus(error.message || 'Gagal membuka folder.', true);
            throw error;
        }
    }

    async function syncFiles() {
        var synced = await requestJson(config.syncUrl, buildSyncOptions());
        renderFiles(synced.files || []);
        updateQuotaDisplay(synced.quota);
        return synced;
    }

    async function renameFile(fileId, currentName) {
        var newName = window.prompt('Ganti nama:', currentName);
        if (!newName || !newName.trim() || newName.trim() === currentName) return;

        var renameUrl = config.renameUrlTemplate.replace('__ID__', encodeURIComponent(fileId));

        try {
            await withLoading('Mengganti nama...', async function () {
                await requestJson(renameUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body: JSON.stringify({ name: newName.trim() }),
                });

                await syncFiles();
            });
            setStatus('Nama berhasil diubah.', false);
        } catch (error) {
            setStatus(error.message || 'Gagal mengganti nama.', true);
        }
    }

    async function copyFile(fileId, fileName) {
        try {
            await withLoading('Membuat salinan...', async function () {
                var copyUrl = config.copyUrlTemplate.replace('__ID__', encodeURIComponent(fileId));
                await requestJson(copyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body: JSON.stringify({}),
                });

                await syncFiles();
            });
            setStatus('Salinan "' + fileName + '" berhasil dibuat.', false);
        } catch (error) {
            setStatus(error.message || 'Gagal membuat salinan.', true);
        }
    }

    async function showFileInfo(fileId) {
        showInfoModal('<div class="flex items-center gap-3 text-gd-muted"><svg class="gdrive-spinner w-5 h-5 text-gd-blue" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-dasharray="90 150" /></svg><span>Memuat info file...</span></div>');

        try {
            var meta = await requestJson(config.infoUrlTemplate.replace('__ID__', encodeURIComponent(fileId)), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrf },
            });

            var html = '<div><strong>Nama:</strong> ' + escapeHtml(meta.name || '-') + '</div>'
                + '<div><strong>Jenis:</strong> ' + escapeHtml(meta.mime_type || '-') + '</div>'
                + '<div><strong>Ukuran:</strong> ' + formatSize(meta.size) + '</div>';

            if (meta.modified_at) {
                html += '<div><strong>Diubah:</strong> ' + escapeHtml(meta.modified_at) + '</div>';
            }
            if (meta.web_view_link) {
                html += '<div class="pt-2"><a href="' + escapeHtml(meta.web_view_link) + '" target="_blank" class="text-gd-blue no-underline hover:underline">Buka di Google Drive</a></div>';
            }

            showInfoModal(html);
        } catch (error) {
            setStatus(error.message || 'Gagal memuat info file.', true);
        }
    }

    async function deleteFile(fileId, fileName, isFolder) {
        var msg = isFolder
            ? 'Pindahkan folder "' + fileName + '" ke sampah?'
            : 'Pindahkan "' + fileName + '" ke sampah?';
        if (!confirm(msg)) return;

        try {
            await withLoading('Memindahkan ke sampah...', async function () {
                var deleteUrl = config.deleteUrlTemplate.replace('__ID__', encodeURIComponent(fileId));
                await fetch(deleteUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new URLSearchParams({ _token: config.csrf, _method: 'DELETE' }),
                });

                delete selectedFileIds[fileId];
                await syncFiles();
            });
            setStatus('File dipindahkan ke sampah.', false);
        } catch (error) {
            setStatus(error.message || 'Gagal menghapus file.', true);
        }
    }

    function handleMoreMenuAction(action) {
        if (!moreMenuTarget) return;

        var target = moreMenuTarget;
        hideMoreMenu();

        if (action === 'download' && !target.isFolder) {
            window.location.href = config.downloadUrlTemplate.replace('__ID__', encodeURIComponent(target.id));
            return;
        }
        if (action === 'rename') {
            renameFile(target.id, target.name);
            return;
        }
        if (action === 'copy') {
            copyFile(target.id, target.name);
            return;
        }
        if (action === 'star') {
            toggleStar(target.id, !target.starred);
            return;
        }
        if (action === 'info') {
            showFileInfo(target.id);
            return;
        }
        if (action === 'delete') {
            deleteFile(target.id, target.name, target.isFolder);
        }
    }

    function parseSortDate(value) {
        if (!value) return 0;
        var time = Date.parse(value);
        return isNaN(time) ? 0 : time;
    }

    function isDateSortField(field) {
        return field === 'modified' || field === 'modified_by_me' || field === 'opened_by_me';
    }

    function getSortValue(file) {
        if (sortConfig.by === 'modified') {
            return parseSortDate(file.modified_at);
        }
        if (sortConfig.by === 'modified_by_me') {
            return parseSortDate(file.modified_by_me_at || file.modified_at);
        }
        if (sortConfig.by === 'opened_by_me') {
            return parseSortDate(file.opened_by_me_at);
        }
        return (file.name || '').toLowerCase();
    }

    function sortFiles(files) {
        var list = files.slice();
        var dateSort = isDateSortField(sortConfig.by);

        list.sort(function (a, b) {
            if (sortConfig.foldersOnTop) {
                var aFolder = !!(a.is_folder || a.mime_type === 'application/vnd.google-apps.folder');
                var bFolder = !!(b.is_folder || b.mime_type === 'application/vnd.google-apps.folder');
                if (aFolder !== bFolder) {
                    return aFolder ? -1 : 1;
                }
            }

            var aVal = getSortValue(a);
            var bVal = getSortValue(b);
            var cmp = 0;

            if (dateSort) {
                cmp = aVal - bVal;
            } else {
                cmp = String(aVal).localeCompare(String(bVal), 'id', { sensitivity: 'base' });
            }

            return sortConfig.dir === 'desc' ? -cmp : cmp;
        });

        return list;
    }

    function updateSortDirLabels() {
        var dateSort = isDateSortField(sortConfig.by);
        if (sortDirAscLabel) {
            sortDirAscLabel.textContent = dateSort ? 'Terlama di atas' : 'A ke Z';
        }
        if (sortDirDescLabel) {
            sortDirDescLabel.textContent = dateSort ? 'Terbaru di atas' : 'Z ke A';
        }
    }

    function updateSortMenuUI() {
        if (!sortMenu) return;

        sortMenu.querySelectorAll('[data-sort-by]').forEach(function (btn) {
            var active = btn.getAttribute('data-sort-by') === sortConfig.by;
            btn.classList.toggle('bg-gd-hover', active);
            var check = btn.querySelector('.gd-sort-check');
            if (check) check.classList.toggle('invisible', !active);
        });

        sortMenu.querySelectorAll('[data-sort-dir]').forEach(function (btn) {
            var active = btn.getAttribute('data-sort-dir') === sortConfig.dir;
            btn.classList.toggle('bg-gd-hover', active);
            var check = btn.querySelector('.gd-sort-check');
            if (check) check.classList.toggle('invisible', !active);
        });

        sortMenu.querySelectorAll('[data-sort-folders]').forEach(function (btn) {
            var isTop = btn.getAttribute('data-sort-folders') === 'top';
            var active = sortConfig.foldersOnTop ? isTop : !isTop;
            btn.classList.toggle('bg-gd-hover', active);
            var check = btn.querySelector('.gd-sort-check');
            if (check) check.classList.toggle('invisible', !active);
        });

        updateSortDirLabels();
    }

    function hideSortMenu() {
        if (!sortMenu) return;
        sortMenu.classList.add('hidden');
        sortMenu.setAttribute('aria-hidden', 'true');
    }

    function showSortMenu() {
        if (!sortMenu || !sortBtn) return;

        updateSortMenuUI();
        sortMenu.classList.remove('hidden');
        sortMenu.setAttribute('aria-hidden', 'false');

        var rect = sortBtn.getBoundingClientRect();
        var menuWidth = sortMenu.offsetWidth || 240;
        var menuHeight = sortMenu.offsetHeight || 360;
        var left = Math.max(8, Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8));
        var top = Math.max(8, Math.min(rect.bottom + 4, window.innerHeight - menuHeight - 8));

        sortMenu.style.left = left + 'px';
        sortMenu.style.top = top + 'px';
    }

    function applySortOption(type, value) {
        if (type === 'by') {
            var previousBy = sortConfig.by;
            sortConfig.by = value;

            if (isDateSortField(value) && !isDateSortField(previousBy)) {
                sortConfig.dir = 'desc';
            } else if (value === 'name' && isDateSortField(previousBy)) {
                sortConfig.dir = 'asc';
            }
        } else if (type === 'dir') {
            sortConfig.dir = value;
        } else if (type === 'folders') {
            sortConfig.foldersOnTop = value === 'top';
        }

        updateSortMenuUI();
        renderFiles(allFiles);
    }

    function getVisibleFiles() {
        var files = currentView === 'starred'
            ? allFiles.filter(function (file) { return !!file.starred; })
            : allFiles;

        return sortFiles(files);
    }

    function updatePageTitle() {
        if (!pageTitleEl) return;

        if (currentView === 'starred') {
            pageTitleEl.textContent = 'Berkas berbintang';
            return;
        }

        if (folderStack.length) {
            pageTitleEl.textContent = folderStack[folderStack.length - 1].name;
            return;
        }

        pageTitleEl.textContent = 'Drive Saya';
    }

    function setActiveNav(view) {
        var links = document.querySelectorAll('.gdrive-nav-link');
        links.forEach(function (link) {
            var isActive = link.getAttribute('data-view') === view;
            link.classList.toggle('bg-gd-active', isActive);
            link.classList.toggle('text-gd-blue-hover', isActive);
            link.classList.toggle('text-[#3c4043]', !isActive);
        });
    }

    function setView(view) {
        if (view === 'all' && currentFolderId) {
            navigateToFolder(-1);
            return;
        }

        currentView = view === 'starred' ? 'starred' : 'all';
        updatePageTitle();
        setActiveNav(currentView);
        renderFiles(allFiles);
    }

    function updateStarState(fileId, starred) {
        allFiles = allFiles.map(function (file) {
            if (file.id === fileId) {
                return Object.assign({}, file, { starred: starred });
            }
            return file;
        });

        var row = document.querySelector('.gd-file-item[data-file-id="' + fileId + '"]');
        if (row) {
            row.setAttribute('data-starred', starred ? '1' : '0');
        }

        var btn = document.querySelector('.gd-star-btn[data-file-id="' + fileId + '"]');
        if (btn) {
            btn.setAttribute('data-starred', starred ? '1' : '0');
            btn.title = starred ? 'Hapus tanda bintang' : 'Tandai dengan bintang';
            btn.classList.toggle('text-[#f4b400]', starred);
            btn.classList.toggle('text-gd-muted', !starred);
        }

        if (currentView === 'starred' || displayMode === 'grid') {
            renderFiles(allFiles);
        }
    }

    async function toggleStar(fileId, starred, skipStatusMessage) {
        if (!config.starUrlTemplate) {
            setStatus('Endpoint bintang belum dikonfigurasi.', true);
            return;
        }

        var starUrl = config.starUrlTemplate.replace('__ID__', encodeURIComponent(fileId));

        try {
            await requestJson(starUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                },
                body: JSON.stringify({ starred: starred }),
            });

            updateStarState(fileId, starred);
            if (!skipStatusMessage) {
                setStatus(starred ? 'File ditandai dengan bintang.' : 'Tanda bintang dihapus.', false);
            }
        } catch (error) {
            setStatus(error.message || 'Gagal memperbarui tanda bintang.', true);
            throw error;
        }
    }

    // Dynamically render tables rows with action SVGs and confirm deletes using Tailwind classes
    function renderFiles(files) {
        if (!fileListEl) return;

        allFiles = files || [];
        var visibleFiles = getVisibleFiles();

        if (fileCountEl) {
            fileCountEl.textContent = visibleFiles.length + ' file';
        }

        var emptyMessage = currentView === 'starred'
            ? 'Belum ada file berbintang. Klik ikon bintang pada file untuk menandainya.'
            : (currentFolderId
                ? 'Folder ini kosong. Buat folder baru atau unggah file ke folder ini.'
                : 'Belum ada file. Unggah file pertama Anda dengan menyeret file kemari atau klik tombol "+ Baru" di sidebar.');

        if (!visibleFiles.length) {
            if (fileListEl) {
                fileListEl.innerHTML = '<tr><td colspan="5" class="py-10 px-4 text-center text-gd-muted">' + emptyMessage + '</td></tr>';
            }
            if (fileGridEl) {
                fileGridEl.innerHTML = '<div class="col-span-full py-10 px-4 text-center text-gd-muted">' + emptyMessage + '</div>';
            }
            clearSelection();
            return;
        }

        if (fileListEl) {
            fileListEl.innerHTML = visibleFiles.map(renderListRow).join('');
        }

        if (fileGridEl) {
            fileGridEl.innerHTML = visibleFiles.map(renderGridCard).join('');
        }

        applySearchFilter();
        updateSelectionUI();
    }

    function applySearchFilter() {
        if (!searchInput) return;
        var query = (searchInput.value || '').toLowerCase().trim();
        var rows = document.querySelectorAll('.gd-file-item');
        rows.forEach(function (row) {
            var name = row.getAttribute('data-name') || '';
            if (name.indexOf(query) !== -1) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        updateSelectionUI();
    }

    async function requestJson(url, options) {
        var response = await fetch(url, options);
        var data = await response.json().catch(function () { return {}; });
        if (!response.ok) throw new Error(data.message || 'Request failed');
        return data;
    }

    async function uploadChunk(file, session, start, end, totalSize, itemId, chunkIndex, chunkTotal) {
        var chunk = file.slice(start, end);
        var contentRange = 'bytes ' + start + '-' + (end - 1) + '/' + totalSize;
        var percent = Math.round((end / totalSize) * 100);
        setItemProgress(itemId, percent);

        return requestJson(config.chunkUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': config.csrf,
                'X-Upload-Url': session.upload_url,
                'X-Content-Range': contentRange,
                'Content-Type': 'application/octet-stream',
            },
            body: chunk,
        });
    }

    async function uploadOne(file, itemId) {
        var parentId = getActiveFolderId();
        var headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': config.csrf,
        };

        if (parentId) {
            headers['X-Parent-Folder-Id'] = parentId;
        }

        var session = await requestJson(config.initUrl, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(buildUploadInitPayload(file)),
        });

        var offset = 0;
        var chunkIndex = 0;

        while (offset < file.size) {
            chunkIndex += 1;
            var end = Math.min(offset + chunkSize, file.size);
            var result = await uploadChunk(file, session, offset, end, file.size, itemId, chunkIndex);
            if (result.complete) {
                setItemStatus(itemId, 'done');
                return;
            }
            offset = end;
        }

        setItemStatus(itemId, 'done');
    }

    // Reusable upload task flow runner
    async function startUpload(files) {
        if (!files || !files.length) return;

        initUploadItems(files);
        if (syncBtn) syncBtn.disabled = true;

        var hadError = false;

        try {
            for (var i = 0; i < files.length; i++) {
                if (uploadCancelled) break;

                try {
                    setStatus('Mengunggah ' + files[i].name + '...', false);
                    await uploadOne(files[i], uploadItems[i].id);
                } catch (err) {
                    if (uploadCancelled) break;
                    setItemStatus(uploadItems[i].id, 'error');
                    hadError = true;
                }
            }

            if (!uploadCancelled && !hadError) {
                await withLoading('Menyinkronkan daftar file...', syncFiles);
                if (fileInput) fileInput.value = '';
                setStatus('Semua file berhasil diunggah.', false);
            } else if (uploadCancelled) {
                setStatus('Unggahan dibatalkan.', false);
            }
        } catch (error) {
            setStatus(error.message || 'Upload gagal.', true);
        } finally {
            if (syncBtn) syncBtn.disabled = false;
            renderPanel();
        }
    }

    function hideContextMenu() {
        if (!contextMenu) return;
        contextMenu.classList.add('hidden');
        contextMenu.setAttribute('aria-hidden', 'true');
        contextTargetFileId = null;
        contextTargetStarred = false;
    }

    function positionContextMenu(x, y) {
        if (!contextMenu) return;

        var menuRect = contextMenu.getBoundingClientRect();
        var maxX = window.innerWidth - menuRect.width - 8;
        var maxY = window.innerHeight - menuRect.height - 8;
        var left = Math.max(8, Math.min(x, maxX));
        var top = Math.max(8, Math.min(y, maxY));

        contextMenu.style.left = left + 'px';
        contextMenu.style.top = top + 'px';
    }

    function showGeneralContextMenu(x, y) {
        if (!contextMenu) return;

        hideNewMenu();

        if (fileContextSection) fileContextSection.classList.add('hidden');
        if (generalContextSection) generalContextSection.classList.remove('hidden');

        contextMenu.classList.remove('hidden');
        contextMenu.setAttribute('aria-hidden', 'false');
        positionContextMenu(x, y);
    }

    function showFileContextMenu(x, y, fileId, starred) {
        if (!contextMenu) return;

        hideNewMenu();

        contextTargetFileId = fileId;
        contextTargetStarred = starred;

        if (generalContextSection) generalContextSection.classList.add('hidden');
        if (fileContextSection) fileContextSection.classList.remove('hidden');
        if (contextStarLabel) {
            contextStarLabel.textContent = starred ? 'Hapus tanda bintang' : 'Tandai dengan bintang';
        }

        contextMenu.classList.remove('hidden');
        contextMenu.setAttribute('aria-hidden', 'false');
        positionContextMenu(x, y);
    }

    function isInteractiveTarget(target) {
        if (!target || !target.closest) return false;
        return !!target.closest('a, button, input, form, label, select, textarea, .gd-action-btn, [data-no-context-menu]');
    }

    async function createNewFolder() {
        if (!config.createFolderUrl) {
            setStatus('Endpoint folder belum dikonfigurasi.', true);
            return;
        }

        var name = window.prompt('Nama folder baru:');
        if (!name || !name.trim()) return;

        hideContextMenu();

        try {
            await withLoading('Membuat folder...', async function () {
                await requestJson(config.createFolderUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body: JSON.stringify({
                        name: name.trim(),
                        parent_id: getActiveFolderId(),
                    }),
                });

                await syncFiles();
            });
            setStatus('Folder berhasil dibuat.', false);
        } catch (error) {
            setStatus(error.message || 'Gagal membuat folder.', true);
        }
    }

    function hideNewMenu() {
        if (!newMenu) return;
        newMenu.classList.add('hidden');
        newMenu.setAttribute('aria-hidden', 'true');
        if (sidebarUploadBtn) {
            sidebarUploadBtn.setAttribute('aria-expanded', 'false');
        }
    }

    function positionNewMenu() {
        if (!newMenu || !sidebarUploadBtn) return;

        var rect = sidebarUploadBtn.getBoundingClientRect();
        var menuWidth = newMenu.offsetWidth || 280;
        var menuHeight = newMenu.offsetHeight || 160;
        var left = Math.max(8, Math.min(rect.left, window.innerWidth - menuWidth - 8));
        var top = rect.bottom + 8;

        if (top + menuHeight > window.innerHeight - 8) {
            top = Math.max(8, rect.top - menuHeight - 8);
        }

        newMenu.style.left = left + 'px';
        newMenu.style.top = top + 'px';
        newMenu.style.minWidth = Math.max(rect.width, 280) + 'px';
    }

    function showNewMenu() {
        if (!newMenu || !sidebarUploadBtn) return;

        hideContextMenu();
        hideMoreMenu();
        hideSortMenu();

        newMenu.classList.remove('hidden');
        newMenu.setAttribute('aria-hidden', 'false');
        sidebarUploadBtn.setAttribute('aria-expanded', 'true');
        positionNewMenu();
    }

    function toggleNewMenu() {
        if (!newMenu) return;
        if (newMenu.classList.contains('hidden')) {
            showNewMenu();
        } else {
            hideNewMenu();
        }
    }

    function handleNewMenuAction(action) {
        hideNewMenu();
        hideContextMenu();

        if (action === 'new-folder') {
            createNewFolder();
            return;
        }

        if (action === 'file-upload' && fileInput) {
            fileInput.click();
            return;
        }

        if (action === 'folder-upload' && folderInput) {
            folderInput.click();
        }
    }

    function handleContextMenuAction(action) {
        if (action === 'toggle-star' && contextTargetFileId) {
            var fileId = contextTargetFileId;
            var nextStarred = !contextTargetStarred;
            hideContextMenu();
            toggleStar(fileId, nextStarred);
            return;
        }

        handleNewMenuAction(action);
    }

    if (contentArea && contextMenu) {
        contentArea.addEventListener('contextmenu', function (e) {
            var row = e.target.closest('.gd-file-item');
            if (row) {
                e.preventDefault();
                showFileContextMenu(
                    e.clientX,
                    e.clientY,
                    row.getAttribute('data-file-id'),
                    row.getAttribute('data-starred') === '1'
                );
                return;
            }

            if (isInteractiveTarget(e.target)) return;

            e.preventDefault();
            showGeneralContextMenu(e.clientX, e.clientY);
        });

        contentArea.addEventListener('click', function (e) {
            var moreBtn = e.target.closest('.gd-more-btn');
            if (moreBtn) {
                e.preventDefault();
                e.stopPropagation();
                hideContextMenu();
                showMoreMenu(moreBtn);
                return;
            }

            var renameBtn = e.target.closest('.gd-rename-btn');
            if (renameBtn) {
                e.preventDefault();
                e.stopPropagation();
                renameFile(renameBtn.getAttribute('data-file-id'), renameBtn.getAttribute('data-file-name'));
                return;
            }

            var starBtn = e.target.closest('.gd-star-btn');
            if (starBtn) {
                e.preventDefault();
                e.stopPropagation();
                var fileId = starBtn.getAttribute('data-file-id');
                var starred = starBtn.getAttribute('data-starred') === '1';
                toggleStar(fileId, !starred);
                return;
            }

            var rowCheckbox = e.target.closest('.gd-row-checkbox');
            if (rowCheckbox) {
                e.stopPropagation();
                toggleFileSelection(rowCheckbox.getAttribute('data-file-id'), rowCheckbox.checked);
                return;
            }

            var breadcrumbBtn = e.target.closest('.gd-breadcrumb-item');
            if (breadcrumbBtn) {
                e.preventDefault();
                navigateToFolder(Number(breadcrumbBtn.getAttribute('data-folder-index')));
                return;
            }

            var row = e.target.closest('.gd-file-item');
            if (row && !isInteractiveTarget(e.target)) {
                var rowFileId = row.getAttribute('data-file-id');
                toggleFileSelection(rowFileId);
            }
        });

        contentArea.addEventListener('dblclick', function (e) {
            if (isInteractiveTarget(e.target)) return;

            var row = e.target.closest('.gd-file-item');
            if (!row || row.getAttribute('data-is-folder') !== '1') return;

            e.preventDefault();
            openFolder(row.getAttribute('data-file-id'), row.getAttribute('data-file-name') || 'Folder');
        });

        contextMenu.addEventListener('click', function (e) {
            var item = e.target.closest('[data-action]');
            if (!item) return;
            handleContextMenuAction(item.getAttribute('data-action'));
        });

        document.addEventListener('click', function (e) {
            if (!contextMenu.classList.contains('hidden') && !contextMenu.contains(e.target)) {
                hideContextMenu();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                hideContextMenu();
                hideNewMenu();
            }
        });

        window.addEventListener('resize', function () {
            hideContextMenu();
            if (newMenu && !newMenu.classList.contains('hidden')) {
                positionNewMenu();
            } else {
                hideNewMenu();
            }
        });
        window.addEventListener('scroll', function () {
            hideContextMenu();
            hideNewMenu();
        }, true);
    }

    if (folderInput) {
        folderInput.addEventListener('change', function () {
            var files = Array.from(folderInput.files || []);
            if (files.length > 0) {
                startUpload(files);
            }
            folderInput.value = '';
        });
    }

    if (sidebarUploadBtn && newMenu) {
        sidebarUploadBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleNewMenu();
        });

        newMenu.addEventListener('click', function (e) {
            var item = e.target.closest('[data-action]');
            if (!item) return;
            e.stopPropagation();
            handleNewMenuAction(item.getAttribute('data-action'));
        });
    }

    document.addEventListener('click', function (e) {
        if (newMenu && !newMenu.classList.contains('hidden')
            && !newMenu.contains(e.target)
            && !e.target.closest('#gdrive-sidebar-upload-btn')) {
            hideNewMenu();
        }
    });

    // Auto-upload when files are selected
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            var files = Array.from(fileInput.files || []);
            if (files.length > 0) {
                startUpload(files);
            }
        });
    }

    // HTML5 Drag and Drop handler
    var dragOverlay = document.getElementById('gd-drag-overlay');
    var dragTarget = document.querySelector('main') || document.body;

    if (dragOverlay && dragTarget) {
        window.addEventListener('dragenter', function (e) {
            e.preventDefault();
            dragOverlay.classList.remove('hidden');
            dragOverlay.classList.add('flex');
        });

        dragOverlay.addEventListener('dragover', function (e) {
            e.preventDefault();
        });

        dragOverlay.addEventListener('dragleave', function (e) {
            e.preventDefault();
            if (e.target === dragOverlay) {
                dragOverlay.classList.remove('flex');
                dragOverlay.classList.add('hidden');
            }
        });

        window.addEventListener('drop', function (e) {
            e.preventDefault();
            dragOverlay.classList.remove('flex');
            dragOverlay.classList.add('hidden');

            var files = Array.from(e.dataTransfer.files || []);
            if (files.length > 0) {
                startUpload(files);
            }
        });
    }

    if (navAll) {
        navAll.addEventListener('click', function (e) {
            e.preventDefault();
            setView('all');
        });
    }

    if (navStarred) {
        navStarred.addEventListener('click', function (e) {
            e.preventDefault();
            setView('starred');
        });
    }

    // Live search input filtering
    if (searchInput) {
        searchInput.addEventListener('input', applySearchFilter);
    }

    // Sync button interaction
    if (syncBtn) {
        syncBtn.addEventListener('click', async function () {
            try {
                await withLoading('Menyinkronkan dari Google Drive...', syncFiles, { syncButton: true });
                setStatus('Sinkronisasi selesai.', false);
            } catch (error) {
                setStatus(error.message || 'Sinkronisasi gagal.', true);
            }
        });
    }

    if (panelMinimize) {
        panelMinimize.addEventListener('click', function () {
            panel.classList.toggle('is-minimized');
            panelMinimize.textContent = panel.classList.contains('is-minimized') ? '▴' : '▾';
        });
    }

    if (panelClose) {
        panelClose.addEventListener('click', hidePanel);
    }

    if (uploadCancelBtn) {
        uploadCancelBtn.addEventListener('click', cancelUpload);
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            var visibleRows = Array.from(document.querySelectorAll('.gd-file-item')).filter(function (row) {
                return row.style.display !== 'none';
            });

            visibleRows.forEach(function (row) {
                var fileId = row.getAttribute('data-file-id');
                if (selectAllCheckbox.checked) {
                    selectedFileIds[fileId] = true;
                } else {
                    delete selectedFileIds[fileId];
                }
            });

            updateSelectionUI();
        });
    }

    if (bulkStarBtn) {
        bulkStarBtn.addEventListener('click', function () {
            bulkStarSelected();
        });
    }

    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function () {
            bulkDeleteSelected();
        });
    }

    if (selectionClearBtn) {
        selectionClearBtn.addEventListener('click', clearSelection);
    }

    if (moreMenu) {
        moreMenu.addEventListener('click', function (e) {
            var item = e.target.closest('[data-action]');
            if (!item) return;
            e.stopPropagation();
            handleMoreMenuAction(item.getAttribute('data-action'));
        });
    }

    document.addEventListener('click', function (e) {
        if (moreMenu && !moreMenu.classList.contains('hidden') && !moreMenu.contains(e.target) && !e.target.closest('.gd-more-btn')) {
            hideMoreMenu();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            hideMoreMenu();
            hideSortMenu();
            hideNewMenu();
        }
    });

    if (infoCloseBtn) {
        infoCloseBtn.addEventListener('click', hideInfoModal);
    }

    if (infoModal) {
        infoModal.addEventListener('click', function (e) {
            if (e.target === infoModal) hideInfoModal();
        });
    }

    if (sortBtn && sortMenu) {
        sortBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (sortMenu.classList.contains('hidden')) {
                hideMoreMenu();
                showSortMenu();
            } else {
                hideSortMenu();
            }
        });

        sortMenu.addEventListener('click', function (e) {
            var byBtn = e.target.closest('[data-sort-by]');
            if (byBtn) {
                applySortOption('by', byBtn.getAttribute('data-sort-by'));
                return;
            }

            var dirBtn = e.target.closest('[data-sort-dir]');
            if (dirBtn) {
                applySortOption('dir', dirBtn.getAttribute('data-sort-dir'));
                return;
            }

            var folderBtn = e.target.closest('[data-sort-folders]');
            if (folderBtn) {
                applySortOption('folders', folderBtn.getAttribute('data-sort-folders'));
            }
        });
    }

    document.addEventListener('click', function (e) {
        if (sortMenu && !sortMenu.classList.contains('hidden') && !sortMenu.contains(e.target) && !e.target.closest('#gdrive-sort-btn')) {
            hideSortMenu();
        }
    });

    if (viewListBtn) {
        viewListBtn.addEventListener('click', function (e) {
            e.preventDefault();
            setDisplayMode('list');
        });
    }

    if (viewGridBtn) {
        viewGridBtn.addEventListener('click', function (e) {
            e.preventDefault();
            setDisplayMode('grid');
        });
    }

    if (config.initialFolderId) {
        currentFolderId = config.initialFolderId;
        folderStack = [{
            id: config.initialFolderId,
            name: config.initialFolderName || 'Folder',
        }];
        updatePageTitle();
        renderBreadcrumb();
        persistFolderState();
    } else {
        restoreFolderState();
    }

    if (config.initialFiles && config.initialFiles.length) {
        renderFiles(config.initialFiles);
    } else {
        updateSortMenuUI();
    }
})();
