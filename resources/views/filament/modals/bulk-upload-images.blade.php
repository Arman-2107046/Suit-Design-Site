<style>
    /*
     * The uploader. One accent — indigo, used sparingly — on white, hairline
     * borders and ink type; colour is kept for status, where it carries meaning.
     */
    .bx { display: flex; flex-direction: column; gap: 16px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Inter, sans-serif; color: #0f172a; }
    .bx * { box-sizing: border-box; }
    .bx [x-cloak] { display: none !important; }

    /* Drop zone */
    .bx-drop {
        display: flex; flex-direction: column; align-items: center; gap: 12px;
        padding: 34px 24px 28px; text-align: center; cursor: pointer;
        background: #fcfcfd; border: 1.5px dashed #d6d6de; border-radius: 16px;
        transition: border-color 0.2s ease, background 0.2s ease, opacity 0.2s ease;
    }
    .bx-drop:hover, .bx-drop.is-over { border-color: #4f46e5; background: #f8f8ff; }
    .bx-drop.is-locked { opacity: 0.55; cursor: not-allowed; pointer-events: none; }
    .bx-drop-icon {
        display: flex; align-items: center; justify-content: center; width: 46px; height: 46px;
        color: #4f46e5; background: #eef2ff; border-radius: 12px; transition: transform 0.2s ease;
    }
    .bx-drop:hover .bx-drop-icon { transform: translateY(-2px); }
    .bx-drop-title { margin: 0; font-size: 15px; font-weight: 600; letter-spacing: -0.01em; }
    .bx-drop-sub { margin: 2px 0 0; font-size: 13px; color: #6b7280; }
    .bx-drop-sub b { font-weight: 600; color: #4f46e5; }
    .bx-chips { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; margin-top: 4px; }
    .bx-chip { font-size: 11px; font-weight: 500; color: #4b5563; background: #fff; border: 1px solid #e5e7eb; border-radius: 999px; padding: 4px 10px; }
    .bx-chip.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10.5px; color: #4f46e5; }

    /* Notices */
    .bx-alert { display: flex; gap: 10px; padding: 12px 14px; font-size: 13px; line-height: 1.45; color: #991b1b; background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; }
    .bx-note { padding: 12px 14px; font-size: 13px; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; }
    .bx-note-head { display: flex; align-items: center; gap: 10px; }
    .bx-note-head strong { flex: 1; font-weight: 600; }
    .bx-note-list { margin: 10px 0 0; padding: 0; list-style: none; max-height: 180px; overflow-y: auto; border-top: 1px solid #fde68a; }
    .bx-note-list li { display: flex; gap: 10px; padding: 6px 0; font-size: 12px; border-bottom: 1px solid #fef3c7; }
    .bx-note-list li span:first-child { flex: 0 1 45%; min-width: 0; font-weight: 600; color: #78350f; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bx-note-list li span:last-child { flex: 1; color: #92400e; }
    .bx-link { padding: 0; font: inherit; font-size: 12px; font-weight: 600; color: inherit; background: none; border: 0; cursor: pointer; text-decoration: underline; text-underline-offset: 2px; }

    /* Stats */
    .bx-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    @media (max-width: 640px) { .bx-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .bx-stat { padding: 12px 14px; background: #fff; border: 1px solid #ececf1; border-radius: 12px; }
    .bx-stat-n { display: block; font-size: 22px; font-weight: 600; letter-spacing: -0.02em; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .bx-stat-k { display: block; margin-top: 4px; font-size: 11px; font-weight: 500; letter-spacing: 0.04em; text-transform: uppercase; color: #9ca3af; }
    .bx-stat.good .bx-stat-n { color: #047857; }
    .bx-stat.warn .bx-stat-n { color: #b45309; }

    /* Progress */
    .bx-progress { padding: 18px 20px 16px; background: #fff; border: 1px solid #ececf1; border-radius: 14px; animation: bx-rise 0.4s cubic-bezier(0.16, 1, 0.3, 1) both; }
    .bx-progress-top { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 12px; }
    .bx-progress-label { margin: 0; font-size: 13px; font-weight: 600; }
    .bx-progress-meta { margin: 3px 0 0; font-size: 12px; color: #6b7280; font-variant-numeric: tabular-nums; }
    .bx-pct { flex-shrink: 0; font-size: 32px; font-weight: 600; line-height: 0.9; letter-spacing: -0.03em; font-variant-numeric: tabular-nums; }
    .bx-pct i { margin-left: 1px; font-size: 15px; font-style: normal; font-weight: 500; color: #9ca3af; }
    .bx-bar { position: relative; height: 24px; overflow: hidden; background: #f1f1f5; border-radius: 999px; }
    .bx-fill { position: relative; height: 100%; overflow: hidden; background: #4f46e5; border-radius: 999px; transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1); }
    .bx-fill.is-done { background: #10b981; }
    .bx-fill.is-live::after {
        content: ""; position: absolute; inset: 0;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.35), transparent);
        transform: translateX(-100%); animation: bx-sheen 1.6s linear infinite;
    }
    .bx-progress-foot { display: flex; align-items: center; gap: 8px; margin: 12px 0 0; font-size: 12px; color: #6b7280; }
    .bx-spin { width: 13px; height: 13px; flex-shrink: 0; border: 2px solid #e5e7eb; border-top-color: #4f46e5; border-radius: 50%; animation: bx-spin 0.8s linear infinite; }

    /* Actions */
    .bx-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .bx-actions .bx-grow { flex: 1; }
    .bx-btn {
        display: inline-flex; align-items: center; gap: 7px; height: 38px; padding: 0 16px;
        font: inherit; font-size: 13px; font-weight: 600; white-space: nowrap;
        border-radius: 10px; border: 1px solid transparent; cursor: pointer;
        transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
    }
    .bx-btn:disabled { cursor: not-allowed; opacity: 0.5; }
    .bx-btn-primary { color: #fff; background: #0f172a; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.18); }
    .bx-btn-primary:hover:not(:disabled) { background: #1e293b; }
    .bx-btn-ghost { color: #374151; background: #fff; border-color: #e5e7eb; }
    .bx-btn-ghost:hover:not(:disabled) { border-color: #cbd5e1; background: #f9fafb; }
    .bx-btn-warn { color: #92400e; background: #fffbeb; border-color: #fde68a; }
    .bx-btn-warn:hover:not(:disabled) { background: #fef3c7; }
    .bx-btn-stop { color: #b91c1c; background: #fff; border-color: #fecaca; }
    .bx-btn-stop:hover:not(:disabled) { background: #fef2f2; }
    .bx-btn-sm { height: 32px; padding: 0 12px; font-size: 12px; }

    /* Lists */
    .bx-grid { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 16px; }
    .bx-panel { flex: 1 1 380px; min-width: 0; overflow: hidden; background: #fff; border: 1px solid #ececf1; border-radius: 14px; }
    .bx-panel.bx-side { flex: 1 1 280px; }
    .bx-panel-head { display: flex; align-items: center; gap: 8px; padding: 11px 14px; border-bottom: 1px solid #f1f1f4; }
    .bx-panel-title { flex: 1; margin: 0; font-size: 12px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; color: #6b7280; }
    .bx-count { font-size: 11px; font-weight: 600; color: #374151; background: #f3f4f6; border-radius: 999px; padding: 2px 8px; font-variant-numeric: tabular-nums; }
    .bx-count.warn { color: #92400e; background: #fef3c7; }
    .bx-rows { max-height: 360px; overflow-y: auto; }
    .bx-row { display: flex; align-items: center; gap: 10px; padding: 9px 14px; border-bottom: 1px solid #f6f6f8; animation: bx-fade 0.25s ease both; }
    .bx-row:last-child { border-bottom: 0; }
    .bx-ext { flex-shrink: 0; width: 38px; padding: 3px 0; font-size: 9.5px; font-weight: 700; letter-spacing: 0.04em; text-align: center; text-transform: uppercase; color: #6b7280; background: #f3f4f6; border-radius: 6px; }
    .bx-name { flex: 1; min-width: 0; font-size: 13px; color: #1f2937; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bx-size { flex-shrink: 0; font-size: 11.5px; color: #9ca3af; font-variant-numeric: tabular-nums; }
    .bx-remove { flex-shrink: 0; width: 22px; height: 22px; padding: 0; font-size: 15px; line-height: 1; color: #9ca3af; background: none; border: 0; border-radius: 6px; cursor: pointer; }
    .bx-remove:hover { color: #b91c1c; background: #fef2f2; }
    .bx-more { padding: 10px 14px; font-size: 12px; color: #9ca3af; text-align: center; background: #fcfcfd; }
    .bx-empty { padding: 26px 14px; font-size: 13px; color: #9ca3af; text-align: center; }

    /* Status pills */
    .bx-pill { flex-shrink: 0; min-width: 74px; padding: 3px 9px; font-size: 11px; font-weight: 600; text-align: center; border-radius: 999px; font-variant-numeric: tabular-nums; }
    .bx-pill.waiting  { color: #6b7280; background: #f3f4f6; }
    .bx-pill.moving   { color: #4338ca; background: #eef2ff; }
    .bx-pill.retrying { color: #b45309; background: #fef3c7; }
    .bx-pill.landed   { color: #0369a1; background: #e0f2fe; }
    .bx-pill.filed    { color: #047857; background: #d1fae5; }
    .bx-pill.failed   { color: #b91c1c; background: #fee2e2; }
    .bx-pill.refused  { color: #c2410c; background: #ffedd5; }

    /* Failures */
    .bx-fail { padding: 10px 14px; border-bottom: 1px solid #f6f6f8; }
    .bx-fail:last-child { border-bottom: 0; }
    .bx-fail-top { display: flex; align-items: center; gap: 8px; }
    .bx-fail-name { flex: 1; min-width: 0; margin: 0; font-size: 12.5px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bx-fail-why { margin: 3px 0 0; font-size: 12px; line-height: 1.45; color: #6b7280; }
    .bx-tag { flex-shrink: 0; font-size: 9.5px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; border-radius: 5px; padding: 2px 6px; color: #b91c1c; background: #fee2e2; }
    .bx-tag.filing { color: #c2410c; background: #ffedd5; }

    @keyframes bx-rise { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
    @keyframes bx-fade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes bx-sheen { to { transform: translateX(100%); } }
    @keyframes bx-spin { to { transform: rotate(360deg); } }

    @media (prefers-reduced-motion: reduce) {
        .bx-progress, .bx-row { animation: none; }
        .bx-fill.is-live::after { animation: none; opacity: 0; }
        .bx-spin { animation-duration: 2.4s; }
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
</style>

{{--
    A view over window.bulkUploadQueue, not the owner of the batch. The queue,
    the progress and the failures live there so they survive the admin moving
    to another menu mid-upload; this component subscribes, renders a snapshot,
    and hands clicks back.
--}}
<div
    class="bx"
    x-data="{
        uploadEndpoint: @js(route('admin.uploads.image')),
        wireMethod: @js($wireMethod ?? null),
        processUrl: @js($processUrl ?? null),
        reportUrl: @js(route('admin.bulk-upload.report')),

        /* Only the unified page files by prefix; it hands over the order to file in. */
        priority: @js($priority ?? null),

        dragging: false,
        showSkipped: false,
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

        n(v) { return window.bulkUploadQueue.formatCount(v); },
        bytes(v) { return window.bulkUploadQueue.formatBytes(v); },
        plural(count, one, many) { return count === 1 ? one : many; },
        ext(name) { const i = name.lastIndexOf('.'); return i > 0 ? name.slice(i + 1, i + 5) : '—'; },

        /* ---------- adding files ---------- */

        pick() {
            if (! this.s.uploading) this.$refs.input.click();
        },

        addFiles(list) {
            window.bulkUploadQueue.add(list, { prefixes: this.priority ? Object.keys(this.priority) : null });
        },

        /* A dropped folder arrives as one entry; walk it for the files inside, however deep. */
        handleDrop(e) {
            this.dragging = false;
            if (this.s.uploading) return;

            const entries = Array.from(e.dataTransfer.items || [])
                .map((item) => (item.webkitGetAsEntry ? item.webkitGetAsEntry() : null))
                .filter(Boolean);

            if (entries.length === 0) return this.addFiles(e.dataTransfer.files);

            this.collect(entries).then((files) => this.addFiles(files));
        },

        async collect(entries) {
            const files = [];

            const walk = async (entry) => {
                if (entry.isFile) {
                    files.push(await new Promise((resolve, reject) => entry.file(resolve, reject)));
                } else if (entry.isDirectory) {
                    const reader = entry.createReader();
                    for (;;) {
                        const batch = await new Promise((resolve, reject) => reader.readEntries(resolve, reject));
                        if (batch.length === 0) break;
                        for (const child of batch) await walk(child);
                    }
                }
            };

            for (const entry of entries) {
                try { await walk(entry); } catch (err) { console.error('could not read', entry.name, err); }
            }

            return files;
        },

        /* ---------- running ---------- */

        priorityOf(name) {
            if (! this.priority) return 0;
            const prefix = name.replace(/\.[^.]*$/, '').split('_')[0];
            return this.priority[prefix] ?? 999;
        },

        upload() {
            window.bulkUploadQueue.start({
                uploadEndpoint: this.uploadEndpoint,
                finalize: (payload) => this.file(payload),
                priorityOf: (name) => this.priorityOf(name),
            });
        },

        retry() {
            window.bulkUploadQueue.retryFailed();
            this.upload();
        },

        stop() { window.bulkUploadQueue.stop(); },
        clearAll() { window.bulkUploadQueue.clearQueue(); },
        clearFailed() { window.bulkUploadQueue.clearFailed(); },
        clearSkipped() { window.bulkUploadQueue.clearSkipped(); this.showSkipped = false; },
        remove(id) { window.bulkUploadQueue.remove(id); },

        /* A route outlives this page; the resource modals have no route and stay on Livewire. */
        async file(payload) {
            if (! this.processUrl) return this.$wire.call(this.wireMethod, payload);

            const res = await fetch(this.processUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.bulkUploadQueue.csrf(),
                },
                body: JSON.stringify({ files: payload }),
            });

            if (! res.ok) {
                const body = await res.json().catch(() => ({}));
                throw new Error(body.message || ('HTTP ' + res.status));
            }

            return res.json();
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
                        rejected: window.bulkUploadQueue.reportRows(),
                        batch: { total: this.s.counts.total + this.s.skippedCount, filed: this.s.okCount },
                    }),
                });

                if (! res.ok) throw new Error('the server returned HTTP ' + res.status);

                const url = URL.createObjectURL(await res.blob());
                const link = document.createElement('a');

                link.href = url;
                link.download = 'bulk-upload-report-' + new Date().toISOString().slice(0, 10) + '.' + format;

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

        /* ---------- what to show ---------- */

        get stageLabel() {
            return {
                checking: 'Checking files',
                uploading: this.s.cancelRequested ? 'Stopping after the files in flight' : 'Uploading to Cloudflare',
                filing: 'Filing into the catalogue',
                done: 'Batch complete',
            }[this.s.stage] || 'Ready';
        },

        get progressMeta() {
            if (this.s.stage === 'filing') return this.n(this.s.phaseDone) + ' of ' + this.n(this.s.phaseTotal) + ' filed';
            if (this.s.stage === 'uploading') {
                return this.n(this.s.phaseDone) + ' of ' + this.n(this.s.phaseTotal) + ' files · '
                    + this.bytes(this.s.loadedBytes) + ' of ' + this.bytes(this.s.totalBytes);
            }
            return this.n(this.s.okCount) + ' filed' + (this.s.failCount ? ' · ' + this.n(this.s.failCount) + ' need attention' : '');
        },

        get showProgress() {
            return this.s.uploading || this.s.stage === 'done';
        },

        get primaryLabel() {
            const waiting = this.s.counts.pending;
            const landed = this.s.counts.uploaded;
            if (waiting) return 'Upload ' + this.n(waiting) + ' ' + this.plural(waiting, 'file', 'files');
            if (landed) return 'File ' + this.n(landed) + ' uploaded ' + this.plural(landed, 'image', 'images');
            return 'Upload files';
        },

        get canStart() {
            return ! this.s.busy && (this.s.counts.pending > 0 || this.s.counts.uploaded > 0);
        },

        get breakdown() {
            return Object.entries(this.s.summary?.breakdown ?? {});
        },

        status(f) {
            switch (f.status) {
                case 'pending':   return ['waiting', 'Waiting'];
                case 'uploading': return ['moving', Math.round((f.loaded / (f.size || 1)) * 100) + '%'];
                case 'retrying':  return ['retrying', 'Retry ' + f.attempts + '/3'];
                case 'uploaded':  return ['landed', 'Uploaded'];
                case 'filing':    return ['moving', 'Filing'];
                case 'filed':     return ['filed', 'Filed'];
                case 'rejected':  return ['refused', 'Rejected'];
                case 'unfiled':   return ['refused', 'Not filed'];
                default:          return ['failed', 'Failed'];
            }
        },
    }"
>
    {{-- Drop zone --}}
    <div
        class="bx-drop"
        x-bind:class="{ 'is-over': dragging, 'is-locked': s.uploading }"
        x-on:click="pick()"
        x-on:dragover.prevent="dragging = ! s.uploading"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="handleDrop($event)"
        role="button"
        tabindex="0"
        x-on:keydown.enter.prevent="pick()"
        x-on:keydown.space.prevent="pick()"
        aria-label="Add images to upload"
    >
        <div class="bx-drop-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
        </div>

        <div>
            <p class="bx-drop-title">{{ $title ?? 'Upload images' }}</p>
            <p class="bx-drop-sub">Drop files or a whole folder here, or <b>browse</b></p>
        </div>

        <div class="bx-chips">
            <span class="bx-chip">PNG · JPG · WebP · GIF · SVG</span>
            <span class="bx-chip">Up to 10 MB each</span>
            @if (! empty($filenameHint))
                <span class="bx-chip mono">{{ $filenameHint }}</span>
            @endif
        </div>

        <input
            type="file"
            x-ref="input"
            multiple
            accept="{{ $accept ?? 'image/png,image/jpeg,image/webp,image/gif,image/svg+xml' }}"
            style="display: none;"
            x-on:change="addFiles($event.target.files); $event.target.value = ''"
        >
    </div>

    {{-- Something that stops the whole batch, such as an expired session --}}
    <div class="bx-alert" x-show="s.notice" x-cloak style="display: none;" role="alert">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 1px;">
            <circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span x-text="s.notice"></span>
    </div>

    {{-- Files turned away before anything was uploaded --}}
    <div class="bx-note" x-show="s.skippedCount > 0" x-cloak style="display: none;">
        <div class="bx-note-head">
            <strong x-text="n(s.skippedCount) + ' ' + plural(s.skippedCount, 'file was', 'files were') + ' set aside before uploading'"></strong>
            <button type="button" class="bx-link" x-on:click="showSkipped = ! showSkipped" x-text="showSkipped ? 'Hide' : 'Show why'"></button>
            <button type="button" class="bx-link" x-on:click="clearSkipped()">Dismiss</button>
        </div>
        <ul class="bx-note-list" x-show="showSkipped">
            <template x-for="(item, i) in s.skipped" :key="i">
                <li><span x-text="item.name"></span><span x-text="item.reason"></span></li>
            </template>
            <li x-show="s.skippedCount > s.skipped.length">
                <span></span><span x-text="'and ' + n(s.skippedCount - s.skipped.length) + ' more — all listed in the report'"></span>
            </li>
        </ul>
    </div>

    {{-- At a glance --}}
    <div class="bx-stats" x-show="s.counts.total > 0 || s.skippedCount > 0" x-cloak style="display: none;">
        <div class="bx-stat">
            <span class="bx-stat-n" x-text="n(s.counts.total)"></span>
            <span class="bx-stat-k">Files</span>
        </div>
        <div class="bx-stat good">
            <span class="bx-stat-n" x-text="n(s.counts.filed)"></span>
            <span class="bx-stat-k">Filed</span>
        </div>
        <div class="bx-stat" x-bind:class="s.counts.failed ? 'warn' : ''">
            <span class="bx-stat-n" x-text="n(s.counts.failed)"></span>
            <span class="bx-stat-k">Need attention</span>
        </div>
        <div class="bx-stat" x-bind:class="s.skippedCount ? 'warn' : ''">
            <span class="bx-stat-n" x-text="n(s.skippedCount)"></span>
            <span class="bx-stat-k">Set aside</span>
        </div>
    </div>

    {{-- Progress --}}
    <div class="bx-progress" x-show="showProgress" x-cloak style="display: none;">
        <div class="bx-progress-top">
            <div style="min-width: 0;">
                <p class="bx-progress-label" x-text="stageLabel"></p>
                <p class="bx-progress-meta" x-text="progressMeta"></p>
            </div>
            <div class="bx-pct"><span x-text="s.uploading ? Math.round(s.percent) : 100"></span><i>%</i></div>
        </div>

        <div class="bx-bar">
            <div
                class="bx-fill"
                x-bind:class="{ 'is-live': s.uploading, 'is-done': ! s.uploading }"
                x-bind:style="'width: ' + (s.uploading ? s.percent : 100) + '%;'"
            ></div>
        </div>

        <p class="bx-progress-foot" x-show="s.uploading">
            <span class="bx-spin"></span>
            <span>You can keep working — leaving this page will not stop the upload.</span>
        </p>
    </div>

    <p class="bx-progress-foot" x-show="s.stage === 'checking'" x-cloak style="display: none; margin: 0;">
        <span class="bx-spin"></span><span>Checking each file is a real image…</span>
    </p>

    {{-- Actions --}}
    <div class="bx-actions">
        <button type="button" class="bx-btn bx-btn-primary" x-on:click="upload()" x-bind:disabled="! canStart" x-show="! s.uploading">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path></svg>
            <span x-text="primaryLabel"></span>
        </button>

        <button type="button" class="bx-btn bx-btn-stop" x-on:click="stop()" x-show="s.stage === 'uploading'" x-bind:disabled="s.cancelRequested" x-cloak style="display: none;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><rect x="5" y="5" width="14" height="14" rx="2"></rect></svg>
            <span x-text="s.cancelRequested ? 'Stopping…' : 'Stop uploading'"></span>
        </button>

        <button type="button" class="bx-btn bx-btn-warn" x-on:click="retry()" x-show="! s.busy && s.counts.failed > 0" x-cloak style="display: none;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
            <span x-text="'Retry ' + n(s.counts.failed) + ' failed'"></span>
        </button>

        <button type="button" class="bx-btn bx-btn-ghost" x-on:click="clearAll()" x-bind:disabled="s.busy" x-show="s.counts.total > 0 || s.skippedCount > 0" x-cloak style="display: none;">
            Clear all
        </button>

        <span class="bx-grow"></span>

        <template x-if="! s.busy && (s.counts.failed > 0 || s.skippedCount > 0)">
            <div style="display: flex; gap: 6px;">
                <button type="button" class="bx-btn bx-btn-ghost bx-btn-sm" x-on:click="downloadReport('pdf')" x-bind:disabled="buildingReport">
                    <span x-text="buildingReport === 'pdf' ? 'Preparing…' : 'Report · PDF'"></span>
                </button>
                <button type="button" class="bx-btn bx-btn-ghost bx-btn-sm" x-on:click="downloadReport('csv')" x-bind:disabled="buildingReport">
                    <span x-text="buildingReport === 'csv' ? 'Preparing…' : 'CSV'"></span>
                </button>
            </div>
        </template>
    </div>

    {{-- The queue, and what needs a second look --}}
    <div class="bx-grid" x-show="s.counts.total > 0" x-cloak style="display: none;">
        <section class="bx-panel">
            <div class="bx-panel-head">
                <h4 class="bx-panel-title">Files</h4>
                <span class="bx-count" x-text="n(s.counts.total)"></span>
            </div>

            <div class="bx-rows">
                <template x-for="f in s.rows" :key="f.id">
                    <div class="bx-row">
                        <span class="bx-ext" x-text="ext(f.name)"></span>
                        <span class="bx-name" x-text="f.name" x-bind:title="f.name"></span>
                        <span class="bx-size" x-text="bytes(f.size)"></span>
                        <span class="bx-pill" x-bind:class="status(f)[0]" x-text="status(f)[1]" x-bind:title="f.reason || ''"></span>
                        <button
                            type="button"
                            class="bx-remove"
                            x-show="! s.uploading && ['pending', 'error'].includes(f.status)"
                            x-on:click="remove(f.id)"
                            title="Remove from the queue"
                            aria-label="Remove from the queue"
                        >&times;</button>
                    </div>
                </template>
            </div>

            <div class="bx-more" x-show="s.hiddenRows > 0" x-text="'+ ' + n(s.hiddenRows) + ' more ' + plural(s.hiddenRows, 'file', 'files') + ' — the numbers above count them all'"></div>
        </section>

        <aside class="bx-panel bx-side" x-show="s.counts.failed > 0" x-cloak style="display: none;">
            <div class="bx-panel-head">
                <h4 class="bx-panel-title">Need attention</h4>
                <span class="bx-count warn" x-text="n(s.counts.failed)"></span>
                <button type="button" class="bx-link" style="color: #6b7280; font-weight: 500;" x-on:click="clearFailed()" x-bind:disabled="s.busy" x-show="! s.busy">Remove</button>
            </div>

            <div class="bx-rows">
                <template x-for="r in s.failures" :key="r.id">
                    <div class="bx-fail">
                        <div class="bx-fail-top">
                            <p class="bx-fail-name" x-text="r.name" x-bind:title="r.name"></p>
                            <span class="bx-tag" x-bind:class="r.stage === 'Filing' ? 'filing' : ''" x-text="r.stage"></span>
                        </div>
                        <p class="bx-fail-why" x-text="r.reason"></p>
                    </div>
                </template>
            </div>

            <div class="bx-more" x-show="s.hiddenFailures > 0" x-text="'+ ' + n(s.hiddenFailures) + ' more — every one is in the report'"></div>
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
                <span x-text="n(displayCount)"></span>
                <span x-text="plural(s.okCount, 'image', 'images')"></span>
                uploaded and filed
            </p>

            <div class="bfu-chips bfu-rise" x-show="breakdown.length" style="animation-delay: 0.56s;">
                <template x-for="[label, count] in breakdown" :key="label">
                    <span class="bfu-chip"><b x-text="n(count)"></b> <span x-text="label"></span></span>
                </template>
            </div>

            <p class="bfu-warn bfu-rise" x-show="s.failCount > 0" style="animation-delay: 0.6s;">
                <span x-text="n(s.failCount)"></span>
                <span x-text="plural(s.failCount, 'file needs', 'files need')"></span> attention
            </p>

            <button type="button" class="bfu-done bfu-rise" style="animation-delay: 0.66s;" x-on:click="showSuccess = false">
                Done
            </button>

            <div class="bfu-overlay-reports bfu-rise" style="animation-delay: 0.7s;" x-show="s.failCount > 0 || s.skippedCount > 0">
                <button type="button" class="bfu-overlay-pdf" x-on:click="downloadReport('pdf')" x-bind:disabled="buildingReport">
                    <span x-text="buildingReport === 'pdf' ? 'Preparing…' : 'PDF report'"></span>
                </button>
                <button type="button" class="bfu-overlay-pdf" x-on:click="downloadReport('csv')" x-bind:disabled="buildingReport">
                    <span x-text="buildingReport === 'csv' ? 'Preparing…' : 'CSV'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
