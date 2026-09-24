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
    .bfu-progress-fill {
        transition: width 0.3s ease;
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

<div
    x-data="{
        cloudName: @js($cloudName),
        uploadPreset: @js($uploadPreset),
        wireMethod: @js($wireMethod ?? 'processBulkUpload'),
        files: [],
        dragging: false,
        uploading: false,
        finalizing: false,
        doneCount: 0,
        allDone: false,

        /* Completion overlay */
        showSuccess: false,
        okCount: 0,
        failCount: 0,
        displayCount: 0,
        summary: null,

        handleDrop(e) {
            this.dragging = false;
            this.handleFiles(e.dataTransfer.files);
        },

        handleFiles(fileList) {
            for (const file of fileList) {
                this.files.push({ file: file, name: file.name, status: 'pending', url: null });
            }
        },

        async uploadAll() {
            if (!this.cloudName || !this.uploadPreset) {
                alert('Cloudinary cloud name or upload preset is not configured.');
                return;
            }

            this.uploading = true;
            this.doneCount = 0;
            this.allDone = false;
            this.showSuccess = false;
            this.summary = null;

            for (const f of this.files) {
                if (f.status === 'done') {
                    this.doneCount++;
                    continue;
                }

                f.status = 'uploading';

                try {
                    const formData = new FormData();
                    formData.append('file', f.file);
                    formData.append('upload_preset', this.uploadPreset);

                    const res = await fetch('https://api.cloudinary.com/v1_1/' + this.cloudName + '/image/upload', {
                        method: 'POST',
                        body: formData,
                    });

                    if (!res.ok) {
                        const errorBody = await res.text();
                        console.error('Cloudinary upload failed for', f.name, ':', errorBody);
                        throw new Error('Upload failed: ' + errorBody);
                    }

                    const data = await res.json();
                    f.url = data.secure_url;
                    f.status = 'done';
                } catch (e) {
                    f.status = 'error';
                }

                this.doneCount++;
            }

            const payload = this.files
                .filter(f => f.status === 'done')
                .map(f => ({ name: f.name, url: f.url }));

            this.okCount = payload.length;
            this.failCount = this.files.length - payload.length;
            this.uploading = false;

            if (payload.length === 0) {
                /* Nothing reached Cloudinary — the rows already say so, no celebration. */
                return;
            }

            this.finalizing = true;

            /* Pages that report what they filed return a summary; the rest return nothing. */
            this.summary = (await this.$wire.call(this.wireMethod, payload)) ?? null;

            this.finalizing = false;
            this.allDone = true;
            this.celebrate();
        },

        celebrate() {
            this.displayCount = 0;
            this.showSuccess = true;

            /* Prefer what the server actually filed; fall back to what Cloudinary accepted. */
            const target = this.summary?.filed ?? this.okCount;

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

        get breakdown() {
            return Object.entries(this.summary?.breakdown ?? {});
        },

        get notFiled() {
            return this.summary?.failed ?? 0;
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

    <div x-show="files.length > 0" style="display: flex; flex-direction: column; gap: 8px;">
        <div x-show="uploading || allDone" style="width: 100%; height: 6px; background: #f0f0f5; border-radius: 999px; overflow: hidden;">
            <div
                class="bfu-progress-fill"
                x-bind:style="'height: 100%; border-radius: 999px; background: linear-gradient(90deg, #818cf8, #6366f1); width: ' + (files.length ? Math.round((doneCount / files.length) * 100) : 0) + '%;'"
            ></div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 6px; max-height: 240px; overflow-y: auto; padding-right: 2px;">
            <template x-for="(f, index) in files" :key="index">
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
                        x-text="f.status === 'pending' ? 'Pending' : f.status === 'uploading' ? 'Uploading' : f.status === 'done' ? 'Done' : 'Error'"
                        x-bind:style="{
                            pending: 'flex-shrink: 0; font-size: 11px; font-weight: 600; color: #9ca3af; background: #f3f4f6; padding: 3px 10px; border-radius: 999px;',
                            uploading: 'flex-shrink: 0; font-size: 11px; font-weight: 600; color: #6366f1; background: #eef2ff; padding: 3px 10px; border-radius: 999px;',
                            done: 'flex-shrink: 0; font-size: 11px; font-weight: 600; color: #15803d; background: #dcfce7; padding: 3px 10px; border-radius: 999px;',
                            error: 'flex-shrink: 0; font-size: 11px; font-weight: 600; color: #dc2626; background: #fee2e2; padding: 3px 10px; border-radius: 999px;',
                        }[f.status]"
                    ></span>
                </div>
            </template>
        </div>
    </div>

    <div style="display: flex; align-items: center; gap: 10px;">
        <button
            type="button"
            class="bfu-btn-primary"
            x-on:click="uploadAll()"
            x-bind:disabled="uploading || finalizing || files.length === 0"
            x-bind:style="(uploading || finalizing || files.length === 0)
                ? 'background: #c7c8f5; color: white; border: none; border-radius: 10px; padding: 10px 20px; font-size: 14px; font-weight: 600; cursor: not-allowed; box-shadow: none;'
                : 'background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%); color: white; border: none; border-radius: 10px; padding: 10px 20px; font-size: 14px; font-weight: 600; cursor: pointer;'"
        >
            <span x-show="!uploading && !finalizing">Upload All</span>
            <span x-show="uploading">Uploading… <span x-text="doneCount"></span>/<span x-text="files.length"></span></span>
            <span x-show="finalizing" class="bfu-pulse">Filing images…</span>
        </button>

        <button
            type="button"
            class="bfu-btn-ghost"
            x-on:click="files = []; allDone = false"
            x-bind:disabled="uploading || finalizing"
            style="background: white; color: #374151; border: 1px solid #e0e0ea; border-radius: 10px; padding: 10px 20px; font-size: 14px; font-weight: 500; cursor: pointer;"
        >
            Clear
        </button>

        <div x-show="allDone && !showSuccess" style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; color: #15803d;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <span>Done — you can close this now</span>
        </div>
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
                <span x-text="(summary?.filed ?? okCount) === 1 ? 'image' : 'images'"></span>
                uploaded and filed
            </p>

            <div class="bfu-chips bfu-rise" x-show="breakdown.length" style="animation-delay: 0.56s;">
                <template x-for="[label, count] in breakdown" :key="label">
                    <span class="bfu-chip"><b x-text="count"></b> <span x-text="label"></span></span>
                </template>
            </div>

            <p class="bfu-warn bfu-rise" x-show="failCount > 0" style="animation-delay: 0.6s;">
                <span x-text="failCount"></span>
                <span x-text="failCount === 1 ? 'file' : 'files'"></span> never reached Cloudinary
            </p>

            <p class="bfu-warn bfu-rise" x-show="notFiled > 0" style="animation-delay: 0.62s;">
                <span x-text="notFiled"></span>
                <span x-text="notFiled === 1 ? 'file' : 'files'"></span> uploaded but could not be filed — see the notification
            </p>

            <button type="button" class="bfu-done bfu-rise" style="animation-delay: 0.66s;" x-on:click="showSuccess = false">
                Done
            </button>
        </div>
    </div>
</div>
