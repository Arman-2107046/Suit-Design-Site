<style>
    .bfu-dropzone {
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .bfu-dropzone:hover {
        border-color: #818cf8 !important;
        background: linear-gradient(135deg, #f5f3ff 0%, #eef2ff 100%) !important;
    }
    .bfu-icon-circle {
        transition: transform 0.25s ease;
    }
    .bfu-dropzone:hover .bfu-icon-circle {
        transform: scale(1.08);
    }
    .bfu-btn-primary {
        transition: all 0.2s ease;
        box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
    }
    .bfu-btn-primary:hover:not(:disabled) {
        box-shadow: 0 6px 20px rgba(99, 102, 241, 0.45);
        transform: translateY(-1px);
    }
    .bfu-btn-primary:active:not(:disabled) {
        transform: translateY(0);
    }
    .bfu-btn-ghost {
        transition: all 0.2s ease;
    }
    .bfu-btn-ghost:hover:not(:disabled) {
        background: #f9fafb !important;
        border-color: #9ca3af !important;
    }
    .bfu-row {
        transition: all 0.2s ease;
        animation: bfu-fade-in 0.3s ease;
    }
    @keyframes bfu-fade-in {
        from { opacity: 0; transform: translateY(-4px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .bfu-pulse {
        animation: bfu-pulse 1.4s ease-in-out infinite;
    }
    @keyframes bfu-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    /* ── Completion overlay ──────────────────────────────────────────── */

    .bfu-overlay {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: rgba(12, 12, 20, 0.55);
        backdrop-filter: blur(10px) saturate(130%);
        -webkit-backdrop-filter: blur(10px) saturate(130%);
        animation: bfu-veil 0.4s ease both;
    }
    @keyframes bfu-veil {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .bfu-card {
        position: relative;
        overflow: hidden;
        width: min(420px, 100%);
        padding: 46px 40px 32px;
        text-align: center;
        background: #fff;
        border-radius: 28px;
        box-shadow: 0 40px 90px -28px rgba(10, 10, 30, 0.55), 0 1px 0 rgba(255, 255, 255, 0.7) inset;
        animation: bfu-card-in 0.75s cubic-bezier(0.16, 1, 0.3, 1) both;
    }
    @keyframes bfu-card-in {
        from { opacity: 0; transform: translateY(26px) scale(0.94); }
        to { opacity: 1; transform: none; }
    }

    /* One slow pass of light across the card, like a polished surface turning */
    .bfu-card::after {
        content: "";
        position: absolute;
        inset: -40%;
        pointer-events: none;
        background: linear-gradient(115deg, transparent 38%, rgba(99, 102, 241, 0.16) 50%, transparent 62%);
        transform: translateX(-100%);
        animation: bfu-sheen 1.6s 0.5s cubic-bezier(0.4, 0, 0.2, 1) both;
    }
    @keyframes bfu-sheen {
        to { transform: translateX(100%); }
    }

    .bfu-mark {
        position: relative;
        width: 96px;
        height: 96px;
        margin: 0 auto 22px;
    }
    .bfu-mark-disc {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%);
        box-shadow: 0 20px 40px -14px rgba(99, 102, 241, 0.8);
        animation: bfu-disc-in 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
    }
    @keyframes bfu-disc-in {
        0% { opacity: 0; transform: scale(0.3); }
        55% { opacity: 1; transform: scale(1.07); }
        100% { opacity: 1; transform: scale(1); }
    }
    .bfu-halo {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        border: 1.5px solid rgba(99, 102, 241, 0.5);
        animation: bfu-halo 2.6s 0.35s cubic-bezier(0.16, 1, 0.3, 1) infinite;
    }
    .bfu-halo + .bfu-halo {
        animation-delay: 1.15s;
    }
    @keyframes bfu-halo {
        0% { opacity: 0.8; transform: scale(0.72); }
        70% { opacity: 0; }
        100% { opacity: 0; transform: scale(1.75); }
    }
    .bfu-spark {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 5px;
        height: 5px;
        margin: -2.5px 0 0 -2.5px;
        border-radius: 50%;
        background: linear-gradient(135deg, #a5b4fc, #6366f1);
        animation: bfu-spark 0.95s cubic-bezier(0.16, 1, 0.3, 1) both;
    }
    @keyframes bfu-spark {
        0% { opacity: 0; transform: rotate(var(--a)) translateY(-34px) scale(0); }
        35% { opacity: 1; transform: rotate(var(--a)) translateY(-52px) scale(1); }
        100% { opacity: 0; transform: rotate(var(--a)) translateY(-78px) scale(0.2); }
    }
    .bfu-check {
        stroke-dasharray: 26;
        stroke-dashoffset: 26;
        animation: bfu-check 0.55s 0.42s cubic-bezier(0.65, 0, 0.35, 1) forwards;
    }
    @keyframes bfu-check {
        to { stroke-dashoffset: 0; }
    }

    .bfu-rise {
        animation: bfu-rise 0.75s cubic-bezier(0.16, 1, 0.3, 1) both;
    }
    @keyframes bfu-rise {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: none; }
    }

    .bfu-eyebrow {
        margin: 0;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: #6366f1;
    }
    .bfu-title {
        margin: 8px 0 0;
        font-size: 30px;
        font-weight: 600;
        letter-spacing: -0.02em;
        line-height: 1.1;
        color: #0f172a;
    }
    .bfu-sub {
        margin: 8px 0 0;
        font-size: 14px;
        color: #6b7280;
    }
    .bfu-warn {
        margin: 12px 0 0;
        font-size: 13px;
        font-weight: 500;
        color: #b45309;
    }
    .bfu-chips {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 6px;
        margin-top: 18px;
    }
    .bfu-chip {
        font-size: 11px;
        font-weight: 500;
        color: #4338ca;
        background: #eef2ff;
        border-radius: 999px;
        padding: 5px 11px;
    }
    .bfu-chip b {
        font-weight: 700;
    }
    .bfu-done {
        margin-top: 26px;
        width: 100%;
        background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%);
        color: #fff;
        border: none;
        border-radius: 12px;
        padding: 12px 20px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 10px 24px -10px rgba(99, 102, 241, 0.9);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .bfu-done:hover {
        transform: translateY(-1px);
        box-shadow: 0 14px 30px -10px rgba(99, 102, 241, 1);
    }
    .bfu-done:active {
        transform: translateY(0);
    }

    .dark .bfu-card {
        background: #18181b;
        box-shadow: 0 40px 90px -28px rgba(0, 0, 0, 0.8);
    }
    .dark .bfu-title { color: #fafafa; }
    .dark .bfu-sub { color: #a1a1aa; }
    .dark .bfu-chip { color: #c7d2fe; background: rgba(99, 102, 241, 0.16); }

    @media (prefers-reduced-motion: reduce) {
        .bfu-overlay, .bfu-card, .bfu-mark-disc, .bfu-rise {
            animation: none !important;
            opacity: 1 !important;
            transform: none !important;
        }
        .bfu-halo, .bfu-spark, .bfu-card::after { display: none; }
        .bfu-check { stroke-dashoffset: 0; animation: none; }
    }
    /* ── Progress stage ──────────────────────────────────────────────── */

    .bfu-grid {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        gap: 16px;
    }
    .bfu-main {
        flex: 1 1 360px;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .bfu-stage {
        width: 100%;
        padding: 20px 22px 18px;
        background: linear-gradient(135deg, #fbfbff 0%, #f6f6fc 100%);
        border: 1px solid #ececf4;
        border-radius: 16px;
        animation: bfu-rise 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
    }
    .bfu-stage-top {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 12px;
    }
    .bfu-stage-label {
        margin: 0;
        font-size: 13px;
        font-weight: 600;
        color: #111827;
    }
    .bfu-stage-meta {
        margin: 3px 0 0;
        font-size: 11px;
        color: #9ca3af;
        font-variant-numeric: tabular-nums;
    }
    .bfu-pct {
        flex-shrink: 0;
        font-size: 34px;
        font-weight: 600;
        line-height: 0.9;
        letter-spacing: -0.03em;
        color: #4338ca;
        font-variant-numeric: tabular-nums;
    }
    .bfu-pct i {
        font-size: 16px;
        font-style: normal;
        font-weight: 500;
        color: #a5b4fc;
        margin-left: 1px;
    }

    .bfu-bar {
        position: relative;
        width: 100%;
        height: 24px;
        background: #ececf4;
        border-radius: 999px;
        overflow: hidden;
        box-shadow: inset 0 1px 3px rgba(15, 15, 40, 0.09);
    }
    .bfu-bar-fill {
        position: relative;
        height: 100%;
        border-radius: 999px;
        overflow: hidden;
        background: linear-gradient(90deg, #a5b4fc, #6366f1);
        /* A highlight along the top edge keeps the thicker bar from reading as a flat slab */
        box-shadow: 0 0 14px rgba(99, 102, 241, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.4);
        transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    /* A highlight travels the filled section while bytes are actually moving */
    .bfu-bar-live::after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.55), transparent);
        transform: translateX(-100%);
        animation: bfu-bar-sheen 1.4s linear infinite;
    }
    @keyframes bfu-bar-sheen {
        to { transform: translateX(100%); }
    }
    .bfu-eta {
        margin: 10px 0 0;
        font-size: 11px;
        font-weight: 500;
        color: #6366f1;
        font-variant-numeric: tabular-nums;
    }

    /* ── Rejected panel ──────────────────────────────────────────────── */

    .bfu-rejects {
        flex: 1 1 272px;
        min-width: 0;
        display: flex;
        flex-direction: column;
        background: #fff;
        border: 1px solid #fadcdc;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 30px -22px rgba(185, 28, 28, 0.55);
        animation: bfu-rise 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
    }
    .bfu-rejects-head {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 13px 16px;
        background: linear-gradient(135deg, #fef4f4 0%, #fdeeee 100%);
        border-bottom: 1px solid #fadcdc;
    }
    .bfu-rejects-title {
        flex: 1;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #b91c1c;
    }
    .bfu-rejects-count {
        font-size: 11px;
        font-weight: 700;
        color: #fff;
        background: #dc2626;
        border-radius: 999px;
        padding: 2px 9px;
    }
    .bfu-rejects-clear {
        padding: 3px 9px;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: #9a5757;
        background: transparent;
        border: 1px solid #f0cfcf;
        border-radius: 999px;
        cursor: pointer;
        transition: color 0.2s ease, border-color 0.2s ease, background 0.2s ease;
    }
    .bfu-rejects-clear:hover {
        color: #b91c1c;
        border-color: #dc2626;
        background: #fff;
    }
    .bfu-rejects-list {
        max-height: 268px;
        overflow-y: auto;
    }
    .bfu-reject {
        padding: 11px 16px;
        border-bottom: 1px solid #f7eaea;
        animation: bfu-fade-in 0.3s ease both;
    }
    .bfu-reject:last-child { border-bottom: 0; }
    .bfu-reject-name {
        margin: 0;
        font-size: 12px;
        font-weight: 600;
        color: #111827;
        word-break: break-all;
    }
    .bfu-reject-reason {
        margin: 3px 0 0;
        font-size: 11px;
        line-height: 1.45;
        color: #6b7280;
    }
    .bfu-reject-stage {
        display: inline-block;
        margin-top: 6px;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #b91c1c;
        background: #fee2e2;
        border-radius: 5px;
        padding: 2px 6px;
    }
    .bfu-reject-stage.filing {
        color: #c2410c;
        background: #ffedd5;
    }
    .bfu-pdf-row {
        display: flex;
        gap: 8px;
        margin: 12px 16px 16px;
    }
    .bfu-pdf {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 10px 12px;
        font-size: 12px;
        font-weight: 600;
        color: #b91c1c;
        background: #fff;
        border: 1px solid #f3c9c9;
        border-radius: 10px;
        cursor: pointer;
        transition: background 0.2s ease, border-color 0.2s ease, transform 0.15s ease;
    }
    .bfu-pdf:hover:not(:disabled) {
        background: #fef4f4;
        border-color: #e9a8a8;
        transform: translateY(-1px);
    }
    .bfu-pdf:disabled { opacity: 0.6; cursor: wait; }

    .bfu-overlay-reports {
        display: flex;
        gap: 8px;
        margin-top: 10px;
    }
    .bfu-overlay-pdf {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 11px 14px;
        font-size: 13px;
        font-weight: 600;
        color: #4b5563;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        cursor: pointer;
        transition: background 0.2s ease, border-color 0.2s ease;
    }
    .bfu-overlay-pdf:hover:not(:disabled) { background: #f9fafb; border-color: #d1d5db; }
    .bfu-overlay-pdf:disabled { opacity: 0.6; cursor: wait; }

    @media (prefers-reduced-motion: reduce) {
        .bfu-stage, .bfu-rejects, .bfu-reject { animation: none; }
        .bfu-bar-live::after { animation: none; opacity: 0; }
    }

</style>

{{--
    This is a view over window.bulkUploadQueue, not an owner of the batch. The
    queue, the progress and the rejections live there so they survive the admin
    navigating to another menu mid-upload; this component subscribes, renders a
    snapshot, and hands clicks back.
--}}
<div
    x-data="{
        cloudName: @js($cloudName),
        uploadPreset: @js($uploadPreset),
        wireMethod: @js($wireMethod ?? null),
        processUrl: @js($processUrl ?? null),
        reportUrl: @js(route('admin.bulk-upload.report')),

        dragging: false,
        buildingReport: null,
        showSuccess: false,
        displayCount: 0,
        off: null,

        s: window.bulkUploadQueue.snapshot(),

        init() {
            this.off = window.bulkUploadQueue.subscribe(() => {
                this.s = window.bulkUploadQueue.snapshot();
                this.maybeCelebrate();
            });

            /* The batch may well have finished while this page was closed */
            this.maybeCelebrate();
        },

        destroy() {
            if (this.off) this.off();
        },

        maybeCelebrate() {
            if (this.s.stage !== 'done' || this.s.celebrated) return;

            window.bulkUploadQueue.celebrated = true;
            this.s = window.bulkUploadQueue.snapshot();
            this.celebrate();
        },

        handleDrop(e) {
            this.dragging = false;
            window.bulkUploadQueue.add(e.dataTransfer.files);
        },

        handleFiles(fileList) {
            window.bulkUploadQueue.add(fileList);
        },

        clearQueue() {
            window.bulkUploadQueue.clearQueue();
        },

        clearRejects() {
            window.bulkUploadQueue.clearRejects();
        },

        formatBytes(n) {
            return window.bulkUploadQueue.formatBytes(n);
        },

        uploadAll() {
            if (!this.cloudName || !this.uploadPreset) {
                alert('Cloudinary cloud name or upload preset is not configured.');
                return;
            }

            window.bulkUploadQueue.start({
                cloudName: this.cloudName,
                uploadPreset: this.uploadPreset,
                finalize: (payload) => this.file(payload),
            });
        },

        /* A route outlives this page; the resource modals have no route and stay on Livewire */
        file(payload) {
            if (!this.processUrl) return this.$wire.call(this.wireMethod, payload);

            return fetch(this.processUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.bulkUploadQueue.csrf(),
                },
                body: JSON.stringify({ files: payload }),
            }).then((res) => {
                if (!res.ok) throw new Error('the server returned HTTP ' + res.status);
                return res.json();
            });
        },

        async downloadReport(format) {
            this.buildingReport = format;

            try {
                const res = await fetch(this.reportUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.bulkUploadQueue.csrf(),
                    },
                    body: JSON.stringify({
                        format: format,
                        rejected: this.s.rejected,
                        batch: { total: this.s.files.length, filed: this.s.okCount },
                    }),
                });

                if (!res.ok) throw new Error('the server returned HTTP ' + res.status);

                const url = URL.createObjectURL(await res.blob());
                const link = document.createElement('a');

                link.href = url;
                link.download = 'bulk-upload-rejected-' + new Date().toISOString().slice(0, 10) + '.' + format;

                document.body.appendChild(link);
                link.click();
                link.remove();

                URL.revokeObjectURL(url);
            } catch (e) {
                alert('Could not build the report: ' + e.message);
            } finally {
                this.buildingReport = null;
            }
        },

        celebrate() {
            this.displayCount = 0;
            this.showSuccess = true;

            const target = this.s.okCount;

            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                this.displayCount = target;
                return;
            }

            /* Count up alongside the card, easing out so it settles rather than stops */
            const start = performance.now() + 260;
            const tick = (now) => {
                const progress = Math.min(1, Math.max(0, now - start) / 800);
                this.displayCount = Math.round(target * (1 - Math.pow(1 - progress, 3)));
                if (progress < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        },

        get stageLabel() {
            if (this.s.stage === 'uploading') return 'Uploading to Cloudinary';
            if (this.s.stage === 'filing') return 'Filing images';
            return 'Batch complete';
        },

        get breakdown() {
            return Object.entries(this.s.summary?.breakdown ?? {});
        },
    }"
    style="display: flex; flex-direction: column; gap: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"
>
    <div
        class="bfu-dropzone"
        x-on:dragover.prevent="dragging = true"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="handleDrop($event)"
        x-on:click="$refs.fileInput.click()"
        x-bind:style="dragging
            ? 'border: 2px dashed #6366f1; background: linear-gradient(135deg, #f5f3ff 0%, #eef2ff 100%); border-radius: 20px; padding: 40px 32px; text-align: center; cursor: pointer;'
            : 'border: 2px dashed #e0e0ea; background: linear-gradient(135deg, #fafafa 0%, #f7f7fb 100%); border-radius: 20px; padding: 40px 32px; text-align: center; cursor: pointer;'"
    >
        <div style="display: flex; flex-direction: column; align-items: center; gap: 14px;">
            <div
                class="bfu-icon-circle"
                style="width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);"
            >
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
            </div>

            <div>
                <h3 style="font-size: 16px; font-weight: 600; color: #111827; margin: 0;">{{ $title ?? 'Upload Images' }}</h3>
                <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0 0;">{{ $subtitle ?? 'Drag & drop images here, or click to browse' }}</p>
            </div>

            @if(!empty($filenameHint))
                <div style="font-size: 11px; font-weight: 500; color: #6366f1; background: white; border: 1px solid #e0e0ea; border-radius: 999px; padding: 6px 14px; letter-spacing: 0.02em;">
                    {{ $filenameHint }}
                </div>
            @endif
        </div>

        <input
            type="file"
            x-ref="fileInput"
            multiple
            accept="{{ $accept ?? 'image/*' }}"
            style="display: none;"
            x-on:change="handleFiles($event.target.files); $event.target.value = ''"
        >
    </div>

    <div class="bfu-stage" x-show="s.busy || s.stage === 'done'" x-cloak style="display: none;">
        <div class="bfu-stage-top">
            <div style="min-width: 0;">
                <p class="bfu-stage-label" x-text="stageLabel"></p>
                <p class="bfu-stage-meta">
                    <span x-text="s.doneCount"></span> of <span x-text="s.files.length"></span>
                    <span x-text="s.files.length === 1 ? 'file' : 'files'"></span>
                    <span x-show="s.totalBytes > 1">
                        &middot;
                        <span x-text="formatBytes(s.loadedBytes)"></span> of <span x-text="formatBytes(s.totalBytes)"></span>
                    </span>
                </p>
            </div>

            <div class="bfu-pct"><span x-text="Math.round(s.percent)"></span><i>%</i></div>
        </div>

        <div class="bfu-bar">
            <div
                class="bfu-bar-fill"
                x-bind:class="s.busy ? 'bfu-bar-live' : ''"
                x-bind:style="'width: ' + s.percent + '%;'"
            ></div>
        </div>

        <p class="bfu-eta" x-show="s.stage === 'uploading' && s.etaText" x-text="s.etaText"></p>
        <p class="bfu-eta bfu-pulse" x-show="s.stage === 'filing'">Sorting them into the right tables…</p>
        <p class="bfu-eta" x-show="s.busy">You can keep working — leaving this page will not stop the upload.</p>
    </div>

    <div class="bfu-grid">
        <div class="bfu-main">
            <div x-show="s.files.length > 0" style="display: flex; flex-direction: column; gap: 6px; max-height: 240px; overflow-y: auto; padding-right: 2px;">
                <template x-for="(f, index) in s.files" :key="index">
                    <div
                        class="bfu-row"
                        style="display: flex; align-items: center; justify-content: space-between; gap: 12px; font-size: 13px; background: white; border: 1px solid #ececf2; border-radius: 10px; padding: 10px 14px;"
                    >
                        <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
                                <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21 15 16 10 5 21"></polyline>
                            </svg>
                            <span x-text="f.name" style="color: #374151; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></span>
                        </div>

                        <span
                            x-bind:class="f.status === 'uploading' ? 'bfu-pulse' : ''"
                            x-text="f.status === 'pending' ? 'Pending'
                                : f.status === 'uploading' ? Math.round((f.loaded / (f.size || 1)) * 100) + '%'
                                : f.status === 'done' ? 'Done'
                                : f.status === 'rejected' ? 'Rejected'
                                : 'Error'"
                            x-bind:style="{
                                pending: 'flex-shrink: 0; font-size: 11px; font-weight: 600; color: #9ca3af; background: #f3f4f6; padding: 3px 10px; border-radius: 999px;',
                                uploading: 'flex-shrink: 0; font-size: 11px; font-weight: 600; color: #6366f1; background: #eef2ff; padding: 3px 10px; border-radius: 999px; font-variant-numeric: tabular-nums;',
                                done: 'flex-shrink: 0; font-size: 11px; font-weight: 600; color: #15803d; background: #dcfce7; padding: 3px 10px; border-radius: 999px;',
                                rejected: 'flex-shrink: 0; font-size: 11px; font-weight: 600; color: #c2410c; background: #ffedd5; padding: 3px 10px; border-radius: 999px;',
                                error: 'flex-shrink: 0; font-size: 11px; font-weight: 600; color: #dc2626; background: #fee2e2; padding: 3px 10px; border-radius: 999px;',
                            }[f.status]"
                        ></span>
                    </div>
                </template>
            </div>

            <div style="display: flex; align-items: center; gap: 10px;">
                <button
                    type="button"
                    class="bfu-btn-primary"
                    x-on:click="uploadAll()"
                    x-bind:disabled="s.busy || s.files.length === 0"
                    x-bind:style="(s.busy || s.files.length === 0)
                        ? 'background: #c7c8f5; color: white; border: none; border-radius: 10px; padding: 10px 20px; font-size: 14px; font-weight: 600; cursor: not-allowed; box-shadow: none;'
                        : 'background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%); color: white; border: none; border-radius: 10px; padding: 10px 20px; font-size: 14px; font-weight: 600; cursor: pointer;'"
                >
                    <span x-show="!s.busy">Upload All</span>
                    <span x-show="s.stage === 'uploading'">Uploading… <span x-text="s.doneCount"></span>/<span x-text="s.files.length"></span></span>
                    <span x-show="s.stage === 'filing'" class="bfu-pulse">Filing images…</span>
                </button>

                <button
                    type="button"
                    class="bfu-btn-ghost"
                    x-on:click="clearQueue()"
                    x-bind:disabled="s.busy"
                    style="background: white; color: #374151; border: 1px solid #e0e0ea; border-radius: 10px; padding: 10px 20px; font-size: 14px; font-weight: 500; cursor: pointer;"
                >
                    Clear
                </button>

                <div x-show="s.stage === 'done' && !showSuccess" style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; color: #15803d;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Done — you can close this now</span>
                </div>
            </div>
        </div>

        {{-- Rejections stay on screen after the overlay is dismissed, so they can be read and reported on --}}
        <aside class="bfu-rejects" x-show="s.rejected.length > 0" x-cloak style="display: none;">
            <div class="bfu-rejects-head">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b91c1c" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
                <span class="bfu-rejects-title">Rejected</span>
                <span class="bfu-rejects-count" x-text="s.rejected.length"></span>

                <button
                    type="button"
                    class="bfu-rejects-clear"
                    title="Clear this list"
                    x-on:click="clearRejects()"
                >
                    Clear
                </button>
            </div>

            <div class="bfu-rejects-list">
                <template x-for="(r, i) in s.rejected" :key="i">
                    <div class="bfu-reject">
                        <p class="bfu-reject-name" x-text="r.name"></p>
                        <p class="bfu-reject-reason" x-text="r.reason"></p>
                        <span class="bfu-reject-stage" x-bind:class="r.stage === 'Filing' ? 'filing' : ''" x-text="r.stage"></span>
                    </div>
                </template>
            </div>

            <div class="bfu-pdf-row">
                <button type="button" class="bfu-pdf" x-on:click="downloadReport('pdf')" x-bind:disabled="buildingReport">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span x-text="buildingReport === 'pdf' ? 'Preparing…' : 'PDF'"></span>
                </button>

                <button type="button" class="bfu-pdf" x-on:click="downloadReport('csv')" x-bind:disabled="buildingReport">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span x-text="buildingReport === 'csv' ? 'Preparing…' : 'CSV'"></span>
                </button>
            </div>
        </aside>
    </div>

    {{-- The finish line: a full-screen moment so a long upload ends on something worth watching --}}
    <div
        x-show="showSuccess"
        x-cloak
        class="bfu-overlay"
        role="status"
        aria-live="polite"
        x-on:click.self="showSuccess = false"
        x-on:keydown.escape.window="showSuccess = false"
        style="display: none;"
    >
        <div class="bfu-card">
            <div class="bfu-mark">
                <span class="bfu-halo"></span>
                <span class="bfu-halo"></span>

                <template x-for="i in 10" :key="i">
                    <span
                        class="bfu-spark"
                        x-bind:style="'--a: ' + (i * 36) + 'deg; animation-delay: ' + (0.3 + i * 0.012).toFixed(3) + 's;'"
                    ></span>
                </template>

                <span class="bfu-mark-disc">
                    <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                        <path class="bfu-check" d="M20 6 9 17l-5-5"></path>
                    </svg>
                </span>
            </div>

            <p class="bfu-eyebrow bfu-rise" style="animation-delay: 0.34s;">Bulk upload</p>

            <h2 class="bfu-title bfu-rise" style="animation-delay: 0.4s;">Completed</h2>

            <p class="bfu-sub bfu-rise" style="animation-delay: 0.48s;">
                <span x-text="displayCount"></span>
                <span x-text="s.okCount === 1 ? 'image' : 'images'"></span>
                uploaded and filed
            </p>

            <div class="bfu-chips bfu-rise" x-show="breakdown.length" style="animation-delay: 0.56s;">
                <template x-for="[label, count] in breakdown" :key="label">
                    <span class="bfu-chip"><b x-text="count"></b> <span x-text="label"></span></span>
                </template>
            </div>

            <p class="bfu-warn bfu-rise" x-show="s.failCount > 0" style="animation-delay: 0.6s;">
                <span x-text="s.failCount"></span>
                <span x-text="s.failCount === 1 ? 'file was' : 'files were'"></span> rejected
            </p>

            <button type="button" class="bfu-done bfu-rise" style="animation-delay: 0.66s;" x-on:click="showSuccess = false">
                Done
            </button>

            <div class="bfu-overlay-reports bfu-rise" style="animation-delay: 0.7s;" x-show="s.rejected.length > 0">
                <button
                    type="button"
                    class="bfu-overlay-pdf"
                    x-on:click="downloadReport('pdf')"
                    x-bind:disabled="buildingReport"
                >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span x-text="buildingReport === 'pdf' ? 'Preparing…' : 'PDF report'"></span>
                </button>

                <button
                    type="button"
                    class="bfu-overlay-pdf"
                    x-on:click="downloadReport('csv')"
                    x-bind:disabled="buildingReport"
                >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span x-text="buildingReport === 'csv' ? 'Preparing…' : 'CSV'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
