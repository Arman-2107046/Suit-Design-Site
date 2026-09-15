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
            .cv-progress .cv-row { display: flex; justify-content: space-between; }
            .cv-progress .cv-track { width: 100%; height: 0.375rem; margin-top: 0.5rem; overflow: hidden; border-radius: 9999px; background: var(--gray-200); }
            .cv-progress .cv-fill { height: 100%; background: var(--primary-600); transition: width 0.2s; }
            .cv-error { font-size: 0.875rem; color: var(--danger-600); }
        </style>
    @endonce

    <div
        x-data="{
            state: $wire.$entangle('{{ $statePath }}'),
            cloudName: @js($getCloudName()),
            preset: @js($getUploadPreset()),
            folder: @js($getFolder()),
            maxBytes: {{ $getMaxMegabytes() }} * 1024 * 1024,
            progress: null,
            error: null,
            dragging: false,

            pick() { this.$refs.input.click(); },

            handle(files) {
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
                if (! this.cloudName || ! this.preset) {
                    this.error = 'Cloudinary is not configured.';
                    return;
                }

                const form = new FormData();
                form.append('file', file);
                form.append('upload_preset', this.preset);
                form.append('folder', this.folder);

                const xhr = new XMLHttpRequest();
                xhr.open('POST', `https://api.cloudinary.com/v1_1/${this.cloudName}/video/upload`);
                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) this.progress = Math.round((e.loaded / e.total) * 100);
                };
                xhr.onload = () => {
                    this.progress = null;
                    if (xhr.status < 200 || xhr.status >= 300) {
                        this.error = 'Upload failed. Please try again.';
                        return;
                    }
                    this.state = JSON.parse(xhr.responseText).secure_url;
                };
                xhr.onerror = () => {
                    this.progress = null;
                    this.error = 'Upload failed. Check your connection and try again.';
                };
                this.progress = 0;
                xhr.send(form);
            },

            remove() { this.state = null; this.error = null; },
        }"
        class="cv-upload"
    >
        <input type="file" accept="video/*" x-ref="input" x-on:change="handle($event.target.files); $event.target.value = ''" @disabled($isDisabled) />

        <template x-if="state && progress === null">
            <div class="cv-preview">
                <video x-bind:src="state" controls muted playsinline preload="metadata"></video>
                <div class="cv-bar">
                    <span class="cv-name" x-text="state.split('/').pop()"></span>
                    @unless ($isDisabled)
                        <div class="cv-actions">
                            <button type="button" x-on:click="pick()">Replace</button>
                            <button type="button" class="cv-remove" x-on:click="remove()">Remove</button>
                        </div>
                    @endunless
                </div>
            </div>
        </template>

        <template x-if="progress !== null">
            <div class="cv-progress">
                <div class="cv-row">
                    <span>Uploading to Cloudinary…</span>
                    <span x-text="progress + '%'"></span>
                </div>
                <div class="cv-track"><div class="cv-fill" x-bind:style="'width:' + progress + '%'"></div></div>
            </div>
        </template>

        <template x-if="! state && progress === null">
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
                <small>MP4 or WebM, up to {{ $getMaxMegabytes() }} MB. Uploads directly to Cloudinary.</small>
            </button>
        </template>

        <p class="cv-error" x-show="error" x-text="error" x-cloak></p>
    </div>
</x-dynamic-component>
