{{--
    A bulk upload outlives the page that started it.

    Everything about a running batch — the File objects, the progress, the
    rejections — lives on window, not in the Alpine component, because the
    admin can walk off to Fabrics halfway through. The panel runs in SPA mode,
    so navigating swaps the body without unloading the document: the requests
    in flight keep going, and this store keeps the numbers. The uploader page
    re-attaches to it on arrival; the dock below reports from anywhere else.
--}}
<script>
    window.bulkUploadQueue = window.bulkUploadQueue || (function () {
        const store = {
            stage: 'idle', /* idle | uploading | filing | done */
            files: [],
            rejected: [],
            summary: null,
            totalBytes: 0,
            loadedBytes: 0,
            percent: 0,
            etaText: '',
            doneCount: 0,
            okCount: 0,
            failCount: 0,
            celebrated: false,
            dockDismissed: false,

            rate: 0,
            lastLoaded: 0,
            lastTime: 0,
            etaTimer: null,
            emitQueued: false,

            listeners: new Set(),

            subscribe(fn) {
                this.listeners.add(fn);
                return () => this.listeners.delete(fn);
            },

            emit() {
                for (const fn of this.listeners) {
                    try {
                        fn();
                    } catch (e) {
                        console.error('bulk upload listener failed', e);
                    }
                }
            },

            /* Progress events fire far faster than anything needs redrawing */
            emitSoon() {
                if (this.emitQueued) return;
                this.emitQueued = true;
                requestAnimationFrame(() => {
                    this.emitQueued = false;
                    this.emit();
                });
            },

            get busy() {
                return this.stage === 'uploading' || this.stage === 'filing';
            },

            /* A plain copy for Alpine to render, so no File objects cross into reactive state */
            snapshot() {
                return {
                    stage: this.stage,
                    busy: this.busy,
                    percent: this.percent,
                    etaText: this.etaText,
                    doneCount: this.doneCount,
                    okCount: this.okCount,
                    failCount: this.failCount,
                    totalBytes: this.totalBytes,
                    loadedBytes: this.loadedBytes,
                    summary: this.summary,
                    celebrated: this.celebrated,
                    dockDismissed: this.dockDismissed,
                    rejected: this.rejected.map(r => ({ ...r })),
                    files: this.files.map(f => ({
                        name: f.name,
                        size: f.size,
                        loaded: f.loaded,
                        status: f.status,
                        reason: f.reason,
                    })),
                };
            },

            add(fileList) {
                for (const file of fileList) {
                    this.files.push({
                        file: file,
                        name: file.name,
                        size: file.size || 0,
                        loaded: 0,
                        status: 'pending',
                        url: null,
                        reason: null,
                    });
                }
                this.emit();
            },

            clearQueue() {
                if (this.busy) return;
                this.files = [];
                this.stage = 'idle';
                this.percent = 0;
                this.doneCount = 0;
                this.emit();
            },

            clearRejects() {
                this.rejected = [];
                this.emit();
            },

            dismissDock() {
                this.dockDismissed = true;

                /* Dismissing here counts as having seen the result, so opening the
                   uploader later does not ambush the admin with the finish overlay. */
                this.celebrated = true;

                this.emit();
            },

            async start(config) {
                if (this.busy) return;

                const queue = this.files.filter(f => f.status !== 'done');
                if (queue.length === 0) return;

                this.cloudName = config.cloudName;
                this.uploadPreset = config.uploadPreset;
                this.finalize = config.finalize;

                this.stage = 'uploading';
                this.rejected = [];
                this.summary = null;
                this.celebrated = false;
                this.dockDismissed = false;
                this.doneCount = this.files.length - queue.length;

                this.totalBytes = Math.max(1, queue.reduce((n, f) => n + f.size, 0));
                this.loadedBytes = 0;
                this.percent = 0;
                this.rate = 0;
                this.lastLoaded = 0;
                this.lastTime = performance.now();
                this.etaText = 'estimating time left';
                this.etaTimer = setInterval(() => this.tickEta(), 450);
                this.emit();

                for (const f of queue) {
                    f.status = 'uploading';
                    f.loaded = 0;
                    f.reason = null;

                    try {
                        await this.sendToCloudinary(f);
                        f.status = 'done';
                    } catch (e) {
                        f.status = 'error';
                        f.reason = e.message;
                        this.rejected.push({ name: f.name, stage: 'Cloudinary upload', reason: e.message });

                        /* A failed file still counts towards the bar, or it stalls short of the end */
                        this.advance(f, f.size);
                    }

                    this.doneCount++;
                    this.emit();
                }

                clearInterval(this.etaTimer);
                this.etaText = '';
                this.percent = 100;

                const payload = this.files
                    .filter(f => f.status === 'done')
                    .map(f => ({ name: f.name, url: f.url }));

                if (payload.length === 0) {
                    /* Nothing reached Cloudinary, so the rejects list is the whole story */
                    this.okCount = 0;
                    this.failCount = this.rejected.length;
                    this.stage = 'done';
                    this.emit();
                    return;
                }

                this.stage = 'filing';
                this.emit();

                try {
                    this.summary = (await this.finalize(payload)) ?? null;
                } catch (e) {
                    for (const f of this.files.filter(f => f.status === 'done')) {
                        this.rejected.push({ name: f.name, stage: 'Filing', reason: 'The server did not answer: ' + e.message });
                    }
                }

                this.absorbFilingRejects();

                this.okCount = this.summary?.filed ?? payload.length;
                this.failCount = this.rejected.length;
                this.stage = 'done';
                this.emit();
            },

            sendToCloudinary(f) {
                return new Promise((resolve, reject) => {
                    const form = new FormData();
                    form.append('file', f.file);
                    form.append('upload_preset', this.uploadPreset);

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'https://api.cloudinary.com/v1_1/' + this.cloudName + '/image/upload');

                    /* Byte-level progress is the whole reason this is XHR and not fetch */
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) this.advance(f, e.loaded);
                    });

                    xhr.addEventListener('load', () => {
                        if (xhr.status - 200 >= 100 || xhr.status - 200 < 0) {
                            console.error('Cloudinary upload failed for', f.name, xhr.responseText);
                            reject(new Error(this.cloudinaryReason(xhr.responseText, xhr.status)));
                            return;
                        }

                        this.advance(f, f.size);

                        try {
                            f.url = JSON.parse(xhr.responseText).secure_url;
                        } catch (e) {
                            reject(new Error('Cloudinary sent back a response we could not read'));
                            return;
                        }

                        resolve();
                    });

                    xhr.addEventListener('error', () => reject(new Error('Network error while reaching Cloudinary')));
                    xhr.addEventListener('timeout', () => reject(new Error('Cloudinary took too long to respond')));

                    xhr.send(form);
                });
            },

            advance(f, loaded) {
                const capped = Math.min(loaded, f.size || loaded);
                this.loadedBytes += Math.max(0, capped - f.loaded);
                f.loaded = capped;
                this.percent = Math.min(100, (this.loadedBytes / this.totalBytes) * 100);
                this.emitSoon();
            },

            /* Files that reached Cloudinary but the server would not file */
            absorbFilingRejects() {
                for (const row of this.summary?.rejected ?? []) {
                    this.rejected.push({ name: row.file, stage: 'Filing', reason: row.reason });

                    const match = this.files.find(f => f.name === row.file);
                    if (match) {
                        match.status = 'rejected';
                        match.reason = row.reason;
                    }
                }
            },

            tickEta() {
                const now = performance.now();
                const deltaTime = now - this.lastTime;
                const deltaBytes = this.loadedBytes - this.lastLoaded;

                this.lastTime = now;
                this.lastLoaded = this.loadedBytes;

                if (deltaTime <= 0 || this.loadedBytes <= 0) return;

                /* Smoothed, so one slow chunk does not throw the estimate around */
                const instant = deltaBytes / deltaTime;
                this.rate = this.rate ? this.rate * 0.75 + instant * 0.25 : instant;

                if (this.rate <= 0) return;

                this.etaText = this.formatDuration((this.totalBytes - this.loadedBytes) / this.rate);
                this.emit();
            },

            formatDuration(ms) {
                const seconds = Math.max(0, Math.round(ms / 1000));

                if (seconds <= 2) return 'almost done';
                if (seconds - 60 < 0) return 'about ' + seconds + 's left';

                const minutes = Math.floor(seconds / 60);
                const rest = seconds % 60;

                return 'about ' + minutes + 'm ' + (rest ? rest + 's ' : '') + 'left';
            },

            formatBytes(n) {
                if (!n) return '0 KB';
                const mb = n / 1048576;
                return mb - 1 >= 0 ? mb.toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB';
            },

            cloudinaryReason(body, status) {
                try {
                    const parsed = JSON.parse(body);
                    if (parsed && parsed.error && parsed.error.message) return parsed.error.message;
                } catch (e) {
                    /* not JSON, so fall through to the status code */
                }
                return 'Cloudinary refused the file (HTTP ' + status + ')';
            },

            csrf() {
                return document.querySelector('meta[name=csrf-token]')?.content ?? '';
            },
        };

        /* SPA navigation cannot interrupt a batch, but a reload or a closed tab can */
        window.addEventListener('beforeunload', (e) => {
            if (!store.busy) return;
            e.preventDefault();
            e.returnValue = '';
        });

        return store;
    })();
</script>

<style>
    .bud {
        position: fixed;
        right: 20px;
        bottom: 20px;
        z-index: 40;
        width: 296px;
        padding: 15px 17px 16px;
        background: #fff;
        border: 1px solid #e7e7f0;
        border-radius: 16px;
        box-shadow: 0 24px 50px -20px rgba(15, 15, 40, 0.4);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        animation: bud-in 0.45s cubic-bezier(0.16, 1, 0.3, 1) both;
    }
    @keyframes bud-in {
        from { opacity: 0; transform: translateY(14px) scale(0.97); }
        to { opacity: 1; transform: none; }
    }
    .bud-top {
        display: flex;
        align-items: center;
        gap: 9px;
        margin-bottom: 11px;
    }
    .bud-spin {
        width: 15px;
        height: 15px;
        flex-shrink: 0;
        border: 2px solid #e0e0ea;
        border-top-color: #6366f1;
        border-radius: 50%;
        animation: bud-spin 0.8s linear infinite;
    }
    @keyframes bud-spin {
        to { transform: rotate(360deg); }
    }
    .bud-tick {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
        border-radius: 50%;
        background: linear-gradient(135deg, #818cf8, #6366f1);
    }
    .bud-title {
        flex: 1;
        min-width: 0;
        margin: 0;
        font-size: 12.5px;
        font-weight: 600;
        color: #111827;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .bud-pct {
        font-size: 12.5px;
        font-weight: 700;
        color: #4338ca;
        font-variant-numeric: tabular-nums;
    }
    .bud-x {
        padding: 0 2px;
        background: none;
        border: 0;
        font-size: 15px;
        line-height: 1;
        color: #c7c7d4;
        cursor: pointer;
    }
    .bud-x:hover { color: #6b7280; }
    .bud-bar {
        height: 7px;
        background: #ececf4;
        border-radius: 999px;
        overflow: hidden;
    }
    .bud-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #a5b4fc, #6366f1);
        transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .bud-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin: 9px 0 0;
        font-size: 10.5px;
        color: #9ca3af;
        font-variant-numeric: tabular-nums;
    }
    .bud-link {
        font-size: 10.5px;
        font-weight: 600;
        color: #6366f1;
        text-decoration: none;
    }
    .bud-link:hover { text-decoration: underline; }
    .bud-bad { color: #b91c1c; font-weight: 600; }

    @media (prefers-reduced-motion: reduce) {
        .bud { animation: none; }
        .bud-spin { animation-duration: 2.4s; }
    }
</style>

{{-- Shown on every admin page except the uploader itself, which has the full view --}}
<div
    x-data="{
        s: window.bulkUploadQueue.snapshot(),
        here: window.location.pathname.replace(/\/$/, '').endsWith('/admin/bulk-upload'),
        init() {
            this.off = window.bulkUploadQueue.subscribe(() => { this.s = window.bulkUploadQueue.snapshot(); });
        },
        destroy() { this.off && this.off(); },
        get shown() {
            if (this.here || this.s.dockDismissed) return false;
            return this.s.busy || this.s.stage === 'done';
        },
    }"
    x-show="shown"
    x-cloak
    class="bud"
    style="display: none;"
>
    <div class="bud-top">
        <div class="bud-spin" x-show="s.busy"></div>
        <div class="bud-tick" x-show="!s.busy"></div>

        <p class="bud-title" x-text="s.stage === 'filing' ? 'Filing images…' : s.busy ? 'Uploading images' : 'Upload complete'"></p>

        <span class="bud-pct" x-show="s.busy" x-text="Math.round(s.percent) + '%'"></span>

        <button type="button" class="bud-x" x-show="!s.busy" x-on:click="window.bulkUploadQueue.dismissDock()" title="Dismiss">&times;</button>
    </div>

    <div class="bud-bar">
        <div class="bud-fill" x-bind:style="'width: ' + s.percent + '%;'"></div>
    </div>

    <div class="bud-meta">
        <span x-show="s.busy" x-text="s.doneCount + ' of ' + s.files.length + (s.etaText ? ' · ' + s.etaText : '')"></span>

        <span x-show="!s.busy">
            <span x-text="s.okCount + ' filed'"></span>
            <span x-show="s.failCount > 0" class="bud-bad">
                · <span x-text="s.failCount"></span> rejected
            </span>
        </span>

        <a class="bud-link" href="{{ route('filament.admin.pages.bulk-upload') }}" wire:navigate>Open</a>
    </div>
</div>
