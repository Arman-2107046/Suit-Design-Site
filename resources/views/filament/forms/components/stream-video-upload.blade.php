@php
    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @once
        <style>
            .cv-upload { display: flex; flex-direction: column; gap: 0.5rem; }
            .cv-upload input[type="file"] { display: none; }
            .cv-drop {
                display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.25rem;
                width: 100%; padding: 2.5rem 1rem; border: 1px dashed var(--gray-300); border-radius: 0.5rem;
                background: var(--gray-50); color: var(--gray-600); font-size: 0.875rem; cursor: pointer; transition: background 0.15s, border-color 0.15s;
            }
            .cv-drop:hover, .cv-drop.is-dragging { background: var(--gray-100); border-color: var(--primary-500); }
            .cv-drop strong { font-weight: 500; color: var(--gray-800); }
            .cv-drop small { font-size: 0.75rem; color: var(--gray-500); }
            .cv-preview { overflow: hidden; border-radius: 0.5rem; background: #000; box-shadow: inset 0 0 0 1px var(--gray-200); }
            .cv-preview video { display: block; width: 100%; max-height: 18rem; background: #000; }
            .cv-bar { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.5rem 0.75rem; background: var(--gray-900); color: var(--gray-300); font-size: 0.75rem; }
            .cv-bar .cv-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .cv-bar .cv-actions { display: flex; gap: 0.75rem; flex-shrink: 0; }
            .cv-bar button { font: inherit; font-weight: 500; color: #fff; background: none; border: 0; padding: 0; cursor: pointer; }
            .cv-bar button:hover { text-decoration: underline; }
            .cv-bar button.cv-remove { color: var(--danger-300); }
            .cv-progress { padding: 1rem; border-radius: 0.5rem; background: var(--gray-50); box-shadow: inset 0 0 0 1px var(--gray-200); font-size: 0.875rem; color: var(--gray-700); }
            .cv-progress .cv-row { display: flex; justify-content: space-between; gap: 1rem; }
            .cv-progress .cv-hint { margin-top: 0.375rem; font-size: 0.75rem; color: var(--gray-500); }
            .cv-progress .cv-track { width: 100%; height: 0.375rem; margin-top: 0.5rem; overflow: hidden; border-radius: 9999px; background: var(--gray-200); }
            .cv-progress .cv-fill { height: 100%; background: var(--primary-600); transition: width 0.2s; }
            .cv-error { font-size: 0.875rem; color: var(--danger-600); }
        </style>
    @endonce

    <div
        x-data="{
            state: $wire.$entangle('{{ $statePath }}'),
            uploadUrl: @js(route('admin.uploads.video')),
            statusUrl: @js(route('admin.uploads.video.status', ['uid' => '__UID__'])),
            maxBytes: {{ $getMaxMegabytes() }} * 1024 * 1024,

            /* uploading | encoding | rendering, or null when idle */
            phase: null,
            progress: 0,
            error: null,
            dragging: false,
            timer: null,

            pick() { this.$refs.input.click(); },

            csrf() { return document.querySelector('meta[name=csrf-token]')?.content ?? ''; },

            async handle(files) {
                const file = files?.[0];
                if (! file) return;
                this.error = null;

                if (! file.type.startsWith('video/')) {
                    this.error = 'Please choose a video file.';
                    return;
                }
                if (file.size > this.maxBytes) {
                    this.error = 'That video is larger than {{ $getMaxMegabytes() }} MB.';
                    return;
                }

                this.phase = 'uploading';
                this.progress = 0;

                let target;
                try {
                    const res = await fetch(this.uploadUrl, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    });
                    target = await res.json();
                    if (! res.ok) throw new Error(target.message || 'HTTP ' + res.status);
                } catch (e) {
                    this.fail('Cloudflare would not accept an upload: ' + e.message);
                    return;
                }

                const form = new FormData();
                form.append('file', file);

                const xhr = new XMLHttpRequest();
                xhr.open('POST', target.uploadURL);
                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) this.progress = Math.round((e.loaded / e.total) * 100);
                };
                xhr.onload = () => {
                    if (xhr.status - 200 >= 100 || xhr.status - 200 < 0) {
                        this.fail('Upload failed. Please try again.');
                        return;
                    }
                    this.waitFor(target.uid);
                };
                xhr.onerror = () => this.fail('Upload failed. Check your connection and try again.');
                xhr.send(form);
            },

            /* Stream encodes, then renders the MP4; the address is saved only once it plays. */
            waitFor(uid) {
                this.phase = 'encoding';
                this.progress = 0;

                const check = async () => {
                    try {
                        const res = await fetch(this.statusUrl.replace('__UID__', uid), { headers: { 'Accept': 'application/json' } });
                        const status = await res.json();
                        if (! res.ok) throw new Error(status.message || 'HTTP ' + res.status);

                        if (status.state === 'ready' && status.url) {
                            this.state = status.url;
                            this.phase = null;
                            return;
                        }

                        this.phase = status.state;
                        this.progress = Math.round(status.percent || 0);
                    } catch (e) {
                        /* A blip while polling is not a failed upload; try again shortly. */
                    }

                    this.timer = setTimeout(check, 3000);
                };

                check();
            },

            fail(message) {
                this.phase = null;
                this.error = message;
            },

            remove() { this.state = null; this.error = null; },

            destroy() { clearTimeout(this.timer); },
        }"
        class="cv-upload"
    >
        <input type="file" accept="video/*" x-ref="input" x-on:change="handle($event.target.files); $event.target.value = ''" @disabled($isDisabled) />

        <template x-if="state && ! phase">
            <div class="cv-preview">
                <video x-bind:src="state" controls muted playsinline preload="metadata"></video>
                <div class="cv-bar">
                    <span class="cv-name">Cloudflare Stream</span>
                    @unless ($isDisabled)
                        <div class="cv-actions">
                            <button type="button" x-on:click="pick()">Replace</button>
                            <button type="button" class="cv-remove" x-on:click="remove()">Remove</button>
                        </div>
                    @endunless
                </div>
            </div>
        </template>

        <template x-if="phase">
            <div class="cv-progress">
                <div class="cv-row">
                    <span x-text="{ uploading: 'Uploading to Cloudflare…', encoding: 'Encoding on Cloudflare…', rendering: 'Preparing the MP4…' }[phase]"></span>
                    <span x-text="progress + '%'"></span>
                </div>
                <div class="cv-track"><div class="cv-fill" x-bind:style="'width:' + progress + '%'"></div></div>
                <p class="cv-hint" x-show="phase !== 'uploading'">Usually under a minute. Stay on this page — the video is saved once it is ready to play.</p>
            </div>
        </template>

        <template x-if="! state && ! phase">
            <button
                type="button"
                class="cv-drop"
                x-bind:class="{ 'is-dragging': dragging }"
                x-on:click="pick()"
                x-on:dragover.prevent="dragging = true"
                x-on:dragleave.prevent="dragging = false"
                x-on:drop.prevent="dragging = false; handle($event.dataTransfer.files)"
                @disabled($isDisabled)
            >
                <strong>Drag &amp; drop a video, or click to browse</strong>
                <small>MP4 or WebM, up to {{ $getMaxMegabytes() }} MB. Uploads directly to Cloudflare Stream.</small>
            </button>
        </template>

        <p class="cv-error" x-show="error" x-text="error" x-cloak></p>
    </div>
</x-dynamic-component>
