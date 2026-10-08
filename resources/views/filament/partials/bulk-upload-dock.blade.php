{{--
    A bulk upload outlives the page that started it.

    Everything about a running batch — the File objects, the progress, the
    failures — lives on window, not in an Alpine component, because the admin
    can walk off to Fabrics halfway through. The panel runs in SPA mode, so
    navigating swaps the body without unloading the document: requests in
    flight keep going, and this store keeps the numbers. The uploader page
    re-attaches to it on arrival; the dock below reports from anywhere else.

    A file goes: pending → uploading (→ retrying) → uploaded → filing → filed.
    It can stop at error (Cloudflare never took it), rejected (the server would
    not file it — usually its name) or unfiled (the server did not answer).
--}}
<script>
    window.bulkUploadQueue = window.bulkUploadQueue || (function () {
        /* Cloudflare Images accepts these formats, up to 10 MB each. */
        const MAX_BYTES = 10 * 1024 * 1024;
        const EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];
        const SYSTEM_FILES = ['.ds_store', 'thumbs.db', 'desktop.ini'];

        /*
         * Uploads in flight at once. Each asks this server for a one-time address
         * first, and Cloudflare allows about 4 of those a second; when a burst of
         * small files goes faster than that, the 429 is retried with back-off.
         */
        const CONCURRENCY = 10;
        const ATTEMPTS = 3;         /* per file, for failures worth retrying */
        const FILE_CHUNK = 50;      /* files per filing request */
        const VISIBLE_ROWS = 120;   /* the list never renders more than this */
        const VISIBLE_FAILURES = 200;

        const ACTIVE = ['uploading', 'retrying', 'filing'];
        const FAILED = ['error', 'rejected', 'unfiled'];

        let nextId = 1;

        /* An error that knows whether trying again could help. */
        function failure(message, { retryable = false, fatal = false, cancelled = false } = {}) {
            const e = new Error(message);
            e.retryable = retryable;
            e.fatal = fatal;
            e.cancelled = cancelled;
            return e;
        }

        function extensionOf(name) {
            const dot = name.lastIndexOf('.');
            return dot > 0 ? name.slice(dot + 1).toLowerCase() : '';
        }

        /*
         * Whether the file really is an image, judged by its first bytes rather
         * than its name — a renamed PDF has a .png extension too.
         */
        async function looksLikeImage(file) {
            const head = new Uint8Array(await file.slice(0, 1024).arrayBuffer());
            const at = (offset, ...bytes) => bytes.every((b, i) => head[offset + i] === b);

            if (at(0, 0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a)) return true;   /* PNG  */
            if (at(0, 0xff, 0xd8, 0xff)) return true;                                   /* JPEG */
            if (at(0, 0x47, 0x49, 0x46, 0x38)) return true;                             /* GIF  */
            if (at(0, 0x52, 0x49, 0x46, 0x46) && at(8, 0x57, 0x45, 0x42, 0x50)) return true; /* WebP */

            /* SVG is text: an <svg> element near the top, after any XML preamble. */
            const text = new TextDecoder('utf-8', { fatal: false }).decode(head);
            return /<svg[\s>]/i.test(text);
        }

        /*
         * The suit renders come out of Adobe RGB documents, and a browser
         * reads colours with no profile (or Photoshop's "uncalibrated" note)
         * as sRGB, so they look washed out. Before such a file leaves the
         * browser its colours are converted to sRGB, by the same maths the
         * designer uses, and an sRGB profile is embedded; a file already in
         * sRGB goes up as it is. Both are then stored under an srgb-ready/ id,
         * which tells the designer to show them without converting again.
         * The profile bytes come from App\Support\ColorProfile, which does the
         * same for form uploads.
         */
        const decode64 = (s) => Uint8Array.from(atob(s), (c) => c.charCodeAt(0));
        const SRGB_PNG_CHUNK = decode64(@js(base64_encode(\App\Support\ColorProfile::pngChunk())));
        const SRGB_JPEG_SEGMENT = decode64(@js(base64_encode(\App\Support\ColorProfile::jpegSegment())));

        function byteString(bytes) {
            let s = '';
            for (let i = 0; i < bytes.length; i += 0x8000) s += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
            return s;
        }

        /* EXIF ColorSpace from XMP or binary EXIF of either byte order: 1 is sRGB, 65535 "uncalibrated". */
        function exifColourSpace(meta) {
            const xmp = /exif:ColorSpace(?:="|>)(\d+)/.exec(meta);
            if (xmp) return Number(xmp[1]);
            let at = meta.indexOf('\xA0\x01\x00\x03\x00\x00\x00\x01');
            if (at >= 0) return (meta.charCodeAt(at + 8) << 8) | meta.charCodeAt(at + 9);
            at = meta.indexOf('\x01\xA0\x03\x00\x01\x00\x00\x00');
            if (at >= 0) return meta.charCodeAt(at + 8) | (meta.charCodeAt(at + 9) << 8);
            return null;
        }

        /* Which space an embedded profile describes, by its name (ASCII in v2 profiles, UTF-16 in v4). */
        function profileSpace(icc) {
            const utf16 = (name) => Array.from(name, (c) => '\0' + c).join('');
            const has = (name) => icc.includes(name) || icc.includes(utf16(name));
            return has('Adobe RGB') ? 'adobe-rgb' : has('sRGB') ? 'srgb' : 'other';
        }

        async function inflate(bytes) {
            if (typeof DecompressionStream !== 'function') return '';
            const stream = new Blob([bytes]).stream().pipeThrough(new DecompressionStream('deflate'));
            return byteString(new Uint8Array(await new Response(stream).arrayBuffer()));
        }

        /* 'adobe-rgb' | 'srgb' | 'other' | 'unknown', mirroring ColorProfile::colourSpace(). */
        async function colourSpace(bytes, isPng) {
            const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
            let icc = null;
            let srgbChunk = false;
            let meta = '';

            if (isPng) {
                for (let at = 8; at + 12 <= bytes.length;) {
                    const length = view.getUint32(at);
                    const type = byteString(bytes.subarray(at + 4, at + 8));
                    const data = bytes.subarray(at + 8, at + 8 + length);

                    if (type === 'iCCP') {
                        const name = byteString(data.subarray(0, data.indexOf(0)));
                        icc = name + ' ' + await inflate(data.subarray(name.length + 2)).catch(() => '');
                    } else if (type === 'sRGB') {
                        srgbChunk = true;
                    } else if (['iTXt', 'tEXt', 'zTXt', 'eXIf'].includes(type)) {
                        meta += byteString(data);
                    } else if (type === 'IDAT' || type === 'IEND') {
                        break;
                    }
                    at += 12 + length;
                }
            } else {
                for (let at = 2; at + 4 <= bytes.length && bytes[at] === 0xff;) {
                    const marker = bytes[at + 1];
                    if (marker === 0xda) break; /* start of scan: the headers are over */

                    const length = view.getUint16(at + 2);
                    const segment = bytes.subarray(at + 4, at + 2 + length);

                    /* A large profile spans several segments, each after a 2-byte sequence number. */
                    if (marker === 0xe2 && byteString(segment.subarray(0, 12)) === 'ICC_PROFILE\0') icc = (icc ?? '') + byteString(segment.subarray(14));
                    else if (marker === 0xe1) meta += byteString(segment);
                    at += 2 + length;
                }
            }

            if (icc !== null) return profileSpace(icc);
            if (srgbChunk) return 'srgb';
            const exif = exifColourSpace(meta);
            return exif === 65535 ? 'adobe-rgb' : exif === 1 ? 'srgb' : 'unknown';
        }

        /* Adobe RGB code values to sRGB ones in place: the same tables and matrix as Welcome.jsx. */
        let adobeToSrgb = null;
        function makeAdobeToSrgb() {
            const toLinear = new Float32Array(256);
            for (let i = 0; i < 256; i++) toLinear[i] = Math.pow(i / 255, 563 / 256);
            const N = 4096;
            const toSrgb = new Uint8ClampedArray(N + 1);
            for (let i = 0; i <= N; i++) {
                const v = i / N;
                toSrgb[i] = Math.round((v <= 0.0031308 ? 12.92 * v : 1.055 * Math.pow(v, 1 / 2.4) - 0.055) * 255);
            }
            const enc = (v) => toSrgb[v <= 0 ? 0 : v >= 1 ? N : (v * N + 0.5) | 0];
            return (data) => {
                for (let i = 0; i < data.length; i += 4) {
                    if (data[i + 3] === 0) continue;
                    const r = toLinear[data[i]];
                    const g = toLinear[data[i + 1]];
                    const b = toLinear[data[i + 2]];
                    data[i] = enc(1.39835 * r - 0.39835 * g);
                    data[i + 1] = enc(g);
                    data[i + 2] = enc(-0.04292 * g + 1.04292 * b);
                }
            };
        }

        async function convertToSrgb(file, isPng) {
            /* The raw numbers: no colour management, no premultiplying. */
            const bitmap = await createImageBitmap(file, { colorSpaceConversion: 'none', premultiplyAlpha: 'none' });
            const canvas = document.createElement('canvas');
            canvas.width = bitmap.width;
            canvas.height = bitmap.height;
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(bitmap, 0, 0);
            bitmap.close();

            const image = ctx.getImageData(0, 0, canvas.width, canvas.height);
            (adobeToSrgb ??= makeAdobeToSrgb())(image.data);
            ctx.putImageData(image, 0, 0);

            const blob = await new Promise((resolve, reject) => canvas.toBlob(
                (b) => (b ? resolve(b) : reject(new Error('could not encode'))),
                isPng ? 'image/png' : 'image/jpeg',
                0.92,
            ));
            const out = new Uint8Array(await blob.arrayBuffer());
            canvas.width = canvas.height = 0;

            /* The profile goes straight after the PNG header, or after the JPEG's JFIF header, which has to stay first. */
            let insertAt = 2;
            if (isPng) insertAt = 8 + 12 + new DataView(out.buffer).getUint32(8);
            else if (out[2] === 0xff && out[3] === 0xe0) insertAt = 4 + ((out[4] << 8) | out[5]);

            return new File(
                [out.subarray(0, insertAt), isPng ? SRGB_PNG_CHUNK : SRGB_JPEG_SEGMENT, out.subarray(insertAt)],
                file.name,
                { type: isPng ? 'image/png' : 'image/jpeg' },
            );
        }

        /*
         * The file to upload, and whether its colours are now sRGB. Renders that
         * say nothing about their colours are Adobe RGB, as the designer assumes.
         * Other formats, other declared spaces, and anything that fails to
         * convert go up untouched and unmarked, so the designer treats them as before.
         */
        async function asSrgb(file) {
            const bytes = new Uint8Array(await file.arrayBuffer());
            const at = (offset, ...expected) => expected.every((b, i) => bytes[offset + i] === b);
            const isPng = at(0, 0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a);

            if (!isPng && !at(0, 0xff, 0xd8)) return { body: file, srgb: false };

            const space = await colourSpace(bytes, isPng);
            if (space === 'srgb') return { body: file, srgb: true };
            if (space === 'other') return { body: file, srgb: false };

            try {
                return { body: await convertToSrgb(file, isPng), srgb: true };
            } catch (e) {
                return { body: file, srgb: false };
            }
        }

        const store = {
            stage: 'idle', /* idle | checking | uploading | filing | done */
            files: [],
            skipped: [],
            summary: null,
            notice: null,

            totalBytes: 0,
            loadedBytes: 0,
            batchSize: 0,
            settled: 0,
            fileTotal: 0,
            fileDone: 0,

            celebrated: false,
            dockDismissed: false,
            cancelRequested: false,

            inflight: new Set(),
            listeners: new Set(),
            emitQueued: false,
            wakeLock: null,

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

            /*
             * Upload progress fires dozens of times a second per file. With
             * thousands of files queued, redrawing on each would freeze the
             * page, so the view hears about it a few times a second at most.
             */
            emitSoon() {
                if (this.emitQueued) return;
                this.emitQueued = true;
                setTimeout(() => {
                    this.emitQueued = false;
                    this.emit();
                }, 160);
            },

            get busy() {
                return ['checking', 'uploading', 'filing'].includes(this.stage);
            },

            get uploading() {
                return this.stage === 'uploading' || this.stage === 'filing';
            },

            /*
             * A plain copy for Alpine to render. Counts cover every file; the
             * rows are a window — what is moving, then what failed, then what
             * waits — so a 4,000-file batch renders as lightly as a 40-file one.
             */
            snapshot() {
                const counts = { total: 0, pending: 0, active: 0, uploaded: 0, filed: 0, failed: 0 };
                const buckets = { active: [], failed: [], pending: [], uploaded: [], filed: [] };

                for (const f of this.files) {
                    counts.total++;
                    const bucket = ACTIVE.includes(f.status) ? 'active'
                        : FAILED.includes(f.status) ? 'failed'
                        : f.status === 'pending' ? 'pending'
                        : f.status === 'uploaded' ? 'uploaded'
                        : 'filed';
                    counts[bucket]++;
                    if (buckets[bucket].length < VISIBLE_ROWS) buckets[bucket].push(f);
                }

                const row = (f) => ({ id: f.id, name: f.name, size: f.size, loaded: f.loaded, status: f.status, reason: f.reason, attempts: f.attempts });
                const rows = [...buckets.active, ...buckets.failed, ...buckets.pending, ...buckets.uploaded, ...buckets.filed]
                    .slice(0, VISIBLE_ROWS)
                    .map(row);

                const failures = this.files
                    .filter((f) => FAILED.includes(f.status))
                    .slice(0, VISIBLE_FAILURES)
                    .map((f) => ({ id: f.id, name: f.name, stage: f.status === 'error' ? 'Upload' : 'Filing', reason: f.reason }));

                const filingPhase = this.stage === 'filing';

                return {
                    stage: this.stage,
                    busy: this.busy,
                    uploading: this.uploading,
                    counts,
                    rows,
                    hiddenRows: Math.max(0, counts.total - rows.length),
                    failures,
                    hiddenFailures: Math.max(0, counts.failed - failures.length),
                    skipped: this.skipped.slice(0, VISIBLE_FAILURES),
                    skippedCount: this.skipped.length,
                    phaseDone: filingPhase ? this.fileDone : this.settled,
                    phaseTotal: filingPhase ? this.fileTotal : this.batchSize,
                    percent: filingPhase
                        ? (this.fileTotal ? (this.fileDone / this.fileTotal) * 100 : 100)
                        : Math.min(100, (this.loadedBytes / Math.max(1, this.totalBytes)) * 100),
                    totalBytes: this.totalBytes,
                    loadedBytes: this.loadedBytes,
                    okCount: counts.filed,
                    failCount: counts.failed,
                    summary: this.summary,
                    notice: this.notice,
                    celebrated: this.celebrated,
                    dockDismissed: this.dockDismissed,
                    cancelRequested: this.cancelRequested,
                };
            },

            /** Every file that did not make it, for the downloadable report. */
            reportRows() {
                return [
                    ...this.files
                        .filter((f) => FAILED.includes(f.status))
                        .map((f) => ({ name: f.name, stage: f.status === 'error' ? 'Upload' : 'Filing', reason: f.reason || 'Unknown error' })),
                    ...this.skipped.map((s) => ({ name: s.name, stage: 'Not uploaded', reason: s.reason })),
                ];
            },

            /* ---------------------------------------------------------- */
            /*  Intake: nothing reaches Cloudflare unless it is an image   */
            /* ---------------------------------------------------------- */

            /*
             * fileList: a FileList or an array of File.
             * options.prefixes: when given, the only names that can be filed;
             * anything else is set aside before upload. (No double braces in
             * here — Blade would read them as an echo.)
             */
            add(fileList, options = {}) {
                /* Copied now: a file input hands over a live list that empties when it is reset. */
                const incoming = Array.from(fileList || []);

                /* One drop at a time, so two quick drops cannot both slip a duplicate past the check. */
                this.intake = (this.intake || Promise.resolve())
                    .then(() => this.addNow(incoming, options))
                    .catch((e) => console.error('bulk upload intake failed', e));

                return this.intake;
            },

            async addNow(incoming, options) {
                if (this.uploading || incoming.length === 0) return;

                const wasStage = this.stage;
                this.stage = 'checking';
                this.notice = null;
                this.emit();

                const known = new Set(this.files.map((f) => f.name.toLowerCase()));
                const prefixes = options.prefixes ? new Set(options.prefixes) : null;

                /* Reading the first bytes is quick, but not free; a few dozen at a time. */
                for (let i = 0; i < incoming.length; i += 24) {
                    const verdicts = await Promise.all(
                        incoming.slice(i, i + 24).map((file) => this.inspect(file, prefixes))
                    );

                    incoming.slice(i, i + 24).forEach((file, n) => {
                        /* Judged here, one by one, not in the parallel check above —
                           otherwise two copies in the same drop would both pass. */
                        const reason = verdicts[n]
                            || (known.has(file.name.toLowerCase()) ? 'Already in the queue' : null);

                        if (reason) {
                            this.skipped.push({ name: file.name, reason });
                            return;
                        }

                        known.add(file.name.toLowerCase());
                        this.files.push({
                            id: nextId++,
                            file: file,
                            name: file.name,
                            size: file.size,
                            loaded: 0,
                            status: 'pending',
                            url: null,
                            reason: null,
                            attempts: 0,
                        });
                    });

                    this.emitSoon();
                }

                this.stage = wasStage === 'done' ? 'done' : 'idle';
                this.emit();
            },

            /** Why a file cannot go, or null when it can. */
            async inspect(file, prefixes) {
                const name = file.name;
                const lower = name.toLowerCase();
                const ext = extensionOf(name);

                if (lower.startsWith('.') || SYSTEM_FILES.includes(lower)) return 'A system file, not an image';
                if (!EXTENSIONS.includes(ext)) return ext ? `Not an image (.${ext})` : 'Not an image (no file extension)';
                if (file.size === 0) return 'The file is empty';
                if (file.size > MAX_BYTES) return `${this.formatBytes(file.size)} — Cloudflare takes up to 10 MB per image`;

                /* Read exactly as the server reads it — case and all — or it would pass here and fail there. */
                if (prefixes) {
                    const prefix = name.replace(/\.[^.]*$/, '').split('_')[0];
                    if (!prefixes.has(prefix)) return `No recognised prefix (${prefix}_), so it could not be filed`;
                }

                try {
                    if (!(await looksLikeImage(file))) return `Named .${ext}, but the contents are not an image`;
                } catch (e) {
                    return 'The file could not be read';
                }

                return null;
            },

            /* ---------------------------------------------------------- */
            /*  Upload: a few at a time, retried when it might help        */
            /* ---------------------------------------------------------- */

            async start(config) {
                if (this.busy) return;

                this.uploadEndpoint = config.uploadEndpoint;
                this.finalize = config.finalize;
                this.priorityOf = config.priorityOf || (() => 0);

                const toUpload = this.files.filter((f) => f.status === 'pending');
                const toFile = this.files.filter((f) => f.status === 'uploaded');
                if (toUpload.length === 0 && toFile.length === 0) return;

                this.cancelRequested = false;
                this.notice = null;
                this.summary = null;
                this.celebrated = false;
                this.dockDismissed = false;

                this.batchSize = toUpload.length;
                this.settled = 0;
                this.totalBytes = Math.max(1, toUpload.reduce((n, f) => n + f.size, 0));
                this.loadedBytes = 0;

                this.holdWakeLock();

                if (toUpload.length > 0) {
                    this.stage = 'uploading';
                    this.emit();

                    const queue = toUpload.slice();
                    const worker = async () => {
                        while (queue.length && !this.cancelRequested) {
                            await this.uploadOne(queue.shift());
                            this.settled++;
                            this.emitSoon();
                        }
                    };

                    await Promise.all(Array.from({ length: Math.min(CONCURRENCY, queue.length) }, worker));
                }

                /* Whatever reached Cloudflare is filed, even after a stop. */
                if (!this.notice) await this.fileUploaded();

                this.stage = 'done';
                this.cancelRequested = false;
                this.releaseWakeLock();

                /* Nothing filed means nothing to celebrate; the failure list tells it. */
                if (!this.files.some((f) => f.status === 'filed')) this.celebrated = true;

                this.emit();
            },

            async uploadOne(f) {
                /* A file that cannot be read for this goes up as it is; the upload reports the real problem. */
                const { body, srgb } = await asSrgb(f.file).catch(() => ({ body: f.file, srgb: false }));

                for (let attempt = 1; attempt <= ATTEMPTS; attempt++) {
                    if (this.cancelRequested) return this.backToPending(f);

                    f.attempts = attempt;
                    f.status = attempt === 1 ? 'uploading' : 'retrying';
                    this.resetProgress(f);
                    this.emitSoon();

                    try {
                        const target = await this.mintUploadUrl(srgb);
                        f.url = await this.postFile(f, body, target.uploadURL);
                        this.advance(f, f.size);
                        f.status = 'uploaded';
                        f.reason = null;
                        return;
                    } catch (e) {
                        if (e.cancelled) return this.backToPending(f);

                        if (e.fatal) {
                            this.notice = e.message;
                            this.cancelRequested = true;
                        }

                        if (e.fatal || !e.retryable || attempt === ATTEMPTS) {
                            f.status = 'error';
                            f.reason = attempt > 1 ? `${e.message} (after ${attempt} tries)` : e.message;
                            this.resetProgress(f);
                            return;
                        }

                        f.reason = e.message;
                        f.status = 'retrying';
                        this.emitSoon();

                        /* Back off: 1.5 s, then 3 s, with a little jitter so retries do not land together. */
                        await this.wait(1500 * 2 ** (attempt - 1) + Math.random() * 600);
                    }
                }
            },

            /*
             * Cloudflare needs a one-time address per file, minted by the server
             * so the API token never reaches the browser; the file then goes
             * straight to Cloudflare without passing through PHP.
             */
            async mintUploadUrl(srgb) {
                let res;

                try {
                    res = await fetch(this.uploadEndpoint, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                        body: JSON.stringify({ srgb }),
                    });
                } catch (e) {
                    throw failure('Could not reach the server', { retryable: true });
                }

                if (res.status === 419) {
                    throw failure('Your admin session has expired. Reload the page, then add the remaining files again.', { fatal: true });
                }
                if (res.status === 401 || res.status === 403) {
                    throw failure('You are no longer signed in to the admin. Sign in again, then add the remaining files.', { fatal: true });
                }

                const body = await res.json().catch(() => ({}));

                if (res.ok && body.uploadURL) return body;

                throw failure(
                    body.message || `The server could not prepare an upload (HTTP ${res.status})`,
                    { retryable: res.status === 429 || res.status >= 500 }
                );
            },

            postFile(f, body, uploadURL) {
                return new Promise((resolve, reject) => {
                    const form = new FormData();
                    form.append('file', body);

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', uploadURL);
                    xhr.timeout = 120000;
                    this.inflight.add(xhr);

                    const done = (fn) => (...args) => {
                        this.inflight.delete(xhr);
                        fn(...args);
                    };

                    /* Byte-level progress is the whole reason this is XHR and not fetch */
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) this.advance(f, Math.min(e.loaded, f.size));
                    });

                    xhr.addEventListener('load', done(() => {
                        const status = xhr.status;

                        if (status >= 200 && status < 300) {
                            let url = null;
                            try {
                                url = this.publicVariant(JSON.parse(xhr.responseText).result?.variants);
                            } catch (e) {
                                /* handled below */
                            }

                            return url
                                ? resolve(url)
                                : reject(failure('Cloudflare sent back a reply that could not be read', { retryable: true }));
                        }

                        reject(failure(this.cloudflareReason(xhr.responseText, status), {
                            retryable: status === 408 || status === 429 || status >= 500,
                        }));
                    }));

                    xhr.addEventListener('error', done(() => reject(failure('The connection to Cloudflare dropped', { retryable: true }))));
                    xhr.addEventListener('timeout', done(() => reject(failure('Cloudflare took too long to answer', { retryable: true }))));
                    xhr.addEventListener('abort', done(() => reject(failure('Stopped', { cancelled: true }))));

                    xhr.send(form);
                });
            },

            /** Stop starting new uploads and abandon the ones in flight; what has landed is still filed. */
            stop() {
                if (this.stage !== 'uploading') return;
                this.cancelRequested = true;
                for (const xhr of this.inflight) xhr.abort();
                this.emit();
            },

            /* ---------------------------------------------------------- */
            /*  Filing: in order, in chunks, each retried once             */
            /* ---------------------------------------------------------- */

            async fileUploaded() {
                /* Parents first, so nothing arrives ahead of what it refers to — even across chunks. */
                const ready = this.files
                    .filter((f) => f.status === 'uploaded')
                    .sort((a, b) => this.priorityOf(a.name) - this.priorityOf(b.name));

                if (ready.length === 0) return;

                this.stage = 'filing';
                this.fileTotal = ready.length;
                this.fileDone = 0;
                this.emit();

                const totals = { filed: 0, breakdown: {} };
                let heard = false;

                for (let i = 0; i < ready.length; i += FILE_CHUNK) {
                    const chunk = ready.slice(i, i + FILE_CHUNK);
                    chunk.forEach((f) => (f.status = 'filing'));
                    this.emitSoon();

                    let result;
                    let error = null;

                    for (let attempt = 1; attempt <= 2; attempt++) {
                        try {
                            result = await this.finalize(chunk.map((f) => ({ name: f.name, url: f.url })));
                            error = null;
                            break;
                        } catch (e) {
                            error = e;
                            if (attempt < 2) await this.wait(2000);
                        }
                    }

                    if (error) {
                        chunk.forEach((f) => {
                            f.status = 'unfiled';
                            f.reason = 'Uploaded, but the server did not file it: ' + error.message;
                        });
                    } else {
                        const refused = new Map((result?.rejected || []).map((r) => [r.file, r.reason]));

                        chunk.forEach((f) => {
                            if (refused.has(f.name)) {
                                f.status = 'rejected';
                                f.reason = refused.get(f.name);
                            } else {
                                f.status = 'filed';
                                f.reason = null;
                            }
                        });

                        /* Pages that report what they filed return a summary; the resource modals return nothing. */
                        if (result) {
                            heard = true;
                            totals.filed += result.filed ?? 0;
                            for (const [label, n] of Object.entries(result.breakdown || {})) {
                                totals.breakdown[label] = (totals.breakdown[label] || 0) + n;
                            }
                        }
                    }

                    this.fileDone += chunk.length;
                    this.emitSoon();
                }

                this.summary = heard ? totals : null;
            },

            /* ---------------------------------------------------------- */
            /*  Housekeeping                                               */
            /* ---------------------------------------------------------- */

            /** Send back round whatever did not make it: uploads again, filing again. */
            retryFailed() {
                if (this.busy) return;

                for (const f of this.files) {
                    if (f.status === 'error') {
                        f.status = 'pending';
                        f.reason = null;
                        f.loaded = 0;
                    } else if (f.status === 'rejected' || f.status === 'unfiled') {
                        /* Already on Cloudflare: only the filing is repeated. */
                        f.status = 'uploaded';
                        f.reason = null;
                    }
                }

                this.notice = null;
                this.emit();
            },

            remove(id) {
                if (this.uploading) return;
                this.files = this.files.filter((f) => f.id !== id);
                this.emit();
            },

            clearQueue() {
                if (this.busy) return;
                this.files = [];
                this.skipped = [];
                this.notice = null;
                this.stage = 'idle';
                this.batchSize = this.settled = this.fileTotal = this.fileDone = 0;
                this.totalBytes = this.loadedBytes = 0;
                this.emit();
            },

            clearFailed() {
                if (this.busy) return;
                this.files = this.files.filter((f) => !FAILED.includes(f.status));
                this.emit();
            },

            clearSkipped() {
                this.skipped = [];
                this.emit();
            },

            dismissDock() {
                this.dockDismissed = true;

                /* Dismissing here counts as having seen the result, so opening the
                   uploader later does not ambush the admin with the finish overlay. */
                this.celebrated = true;

                this.emit();
            },

            backToPending(f) {
                this.resetProgress(f);
                f.status = 'pending';
                f.reason = null;
            },

            resetProgress(f) {
                this.loadedBytes = Math.max(0, this.loadedBytes - f.loaded);
                f.loaded = 0;
            },

            advance(f, loaded) {
                const capped = Math.min(loaded, f.size || loaded);
                this.loadedBytes += Math.max(0, capped - f.loaded);
                f.loaded = capped;
                this.emitSoon();
            },

            /* A long batch should not die because the laptop went to sleep. */
            async holdWakeLock() {
                try {
                    this.wakeLock = await navigator.wakeLock?.request('screen');
                } catch (e) {
                    this.wakeLock = null;
                }
            },

            releaseWakeLock() {
                try {
                    this.wakeLock?.release();
                } catch (e) {
                    /* already released */
                }
                this.wakeLock = null;
            },

            /* The full-size address; the site asks for smaller sizes itself. */
            publicVariant(variants) {
                const list = variants || [];
                return list.find((v) => v.endsWith('/public')) || list[0] || null;
            },

            cloudflareReason(body, status) {
                try {
                    const message = JSON.parse(body)?.errors?.[0]?.message;
                    if (message) return message;
                } catch (e) {
                    /* not JSON, so fall through to the status code */
                }
                return `Cloudflare refused the file (HTTP ${status})`;
            },

            wait(ms) {
                return new Promise((resolve) => setTimeout(resolve, ms));
            },

            formatBytes(n) {
                if (!n) return '0 KB';
                if (n >= 1073741824) return (n / 1073741824).toFixed(2) + ' GB';
                if (n >= 1048576) return (n / 1048576).toFixed(1) + ' MB';
                return Math.max(1, Math.round(n / 1024)) + ' KB';
            },

            formatCount(n) {
                return Number(n || 0).toLocaleString();
            },

            csrf() {
                return document.querySelector('meta[name=csrf-token]')?.content ?? '';
            },
        };

        /* SPA navigation cannot interrupt a batch, but a reload or a closed tab can */
        window.addEventListener('beforeunload', (e) => {
            if (!store.uploading) return;
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
        width: 300px;
        padding: 14px 16px 15px;
        background: #fff;
        border: 1px solid #e8e8ee;
        border-radius: 14px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 18px 40px -18px rgba(15, 23, 42, 0.28);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        animation: bud-in 0.4s cubic-bezier(0.16, 1, 0.3, 1) both;
    }
    @keyframes bud-in {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: none; }
    }
    .bud-top { display: flex; align-items: center; gap: 9px; margin-bottom: 10px; }
    .bud-spin {
        width: 14px; height: 14px; flex-shrink: 0;
        border: 2px solid #e5e7eb; border-top-color: #4f46e5; border-radius: 50%;
        animation: bud-spin 0.8s linear infinite;
    }
    @keyframes bud-spin { to { transform: rotate(360deg); } }
    .bud-dot { width: 8px; height: 8px; flex-shrink: 0; margin: 0 3px; border-radius: 50%; background: #10b981; }
    .bud-dot.warn { background: #f59e0b; }
    .bud-title { flex: 1; min-width: 0; margin: 0; font-size: 12.5px; font-weight: 600; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bud-pct { font-size: 12px; font-weight: 600; color: #0f172a; font-variant-numeric: tabular-nums; }
    .bud-x { padding: 0 2px; background: none; border: 0; font-size: 16px; line-height: 1; color: #9ca3af; cursor: pointer; }
    .bud-x:hover { color: #374151; }
    .bud-bar { height: 4px; background: #f1f1f4; border-radius: 999px; overflow: hidden; }
    .bud-fill { height: 100%; border-radius: 999px; background: #4f46e5; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .bud-meta { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin: 9px 0 0; font-size: 11px; color: #6b7280; font-variant-numeric: tabular-nums; }
    .bud-link { font-size: 11px; font-weight: 600; color: #4f46e5; text-decoration: none; }
    .bud-link:hover { text-decoration: underline; }
    .bud-bad { color: #b45309; font-weight: 600; }

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
            return this.s.uploading || this.s.stage === 'done';
        },
        n(v) { return window.bulkUploadQueue.formatCount(v); },
    }"
    x-show="shown"
    x-cloak
    class="bud"
    style="display: none;"
>
    <div class="bud-top">
        <div class="bud-spin" x-show="s.uploading"></div>
        <div class="bud-dot" x-show="!s.uploading" x-bind:class="s.failCount > 0 ? 'warn' : ''"></div>

        <p class="bud-title" x-text="s.stage === 'filing' ? 'Filing images' : s.uploading ? 'Uploading images' : 'Upload finished'"></p>

        <span class="bud-pct" x-show="s.uploading" x-text="Math.round(s.percent) + '%'"></span>

        <button type="button" class="bud-x" x-show="!s.uploading" x-on:click="window.bulkUploadQueue.dismissDock()" title="Dismiss" aria-label="Dismiss">&times;</button>
    </div>

    <div class="bud-bar">
        <div class="bud-fill" x-bind:style="'width: ' + (s.uploading ? s.percent : 100) + '%;'"></div>
    </div>

    <div class="bud-meta">
        <span x-show="s.uploading" x-text="n(s.phaseDone) + ' of ' + n(s.phaseTotal) + ' files'"></span>

        <span x-show="!s.uploading">
            <span x-text="n(s.okCount) + ' filed'"></span>
            <span x-show="s.failCount > 0" class="bud-bad">· <span x-text="n(s.failCount)"></span> need attention</span>
        </span>

        <a class="bud-link" href="{{ route('filament.admin.pages.bulk-upload') }}" wire:navigate>Open</a>
    </div>
</div>
