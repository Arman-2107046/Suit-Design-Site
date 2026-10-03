/*
 * Asking the image host for a size that fits, in a modern format.
 *
 * Renders are stored as 2-4 MB PNGs (the suit layers are 2291x2727). The host
 * resizes and transcodes on the fly, so pages ask for a device-sized AVIF or
 * WebP instead (~50-200 KB) — that is what turns a fabric swap from a long
 * buffer into a short one. Transparency survives: the format is negotiated
 * from the browser's Accept header and only ever falls back to PNG.
 *
 * Images live on Cloudflare Images, at imagedelivery.net/<hash>/<id>/<variant>.
 * Flexible variants are switched on for the account, so the last segment can
 * be a size rather than "public". An id may itself contain slashes.
 */
const CLOUDFLARE_IMAGE = /^(https:\/\/imagedelivery\.net\/[^/]+\/.+)\/[^/]+$/;

/*
 * Addresses from before the move off Cloudinary, so they still resize until
 * `php artisan media:move-to-cloudflare` has copied them across. Once it has
 * run, nothing produces these any more and this can go.
 */
const CLOUDINARY_IMAGE = /^(https?:\/\/res\.cloudinary\.com\/[^/]+\/image\/upload\/)(v\d+\/.+)$/;

export function resizeImage(url, width) {
    if (!url) return url;

    const cloudflare = CLOUDFLARE_IMAGE.exec(url);
    if (cloudflare) return `${cloudflare[1]}/${width ? `w=${width},` : ""}fit=scale-down,f=auto`;

    const cloudinary = CLOUDINARY_IMAGE.exec(url);
    if (cloudinary) return `${cloudinary[1]}f_auto,q_auto${width ? `,w_${width}` : ""}/${cloudinary[2]}`;

    return url;
}

/*
 * The homepage video. On Cloudflare Stream the stored address is already the
 * ready-made MP4, and the poster is a frame from the same video.
 */
const STREAM_MP4 = /^(https:\/\/customer-[a-z0-9]+\.cloudflarestream\.com\/[a-f0-9]{32})\/downloads\/default\.mp4$/i;
const CLOUDINARY_VIDEO = /^(https?:\/\/res\.cloudinary\.com\/[^/]+\/video\/upload\/)(v\d+\/.+)$/;

export function videoUrl(url) {
    const cloudinary = url && CLOUDINARY_VIDEO.exec(url);
    return cloudinary ? `${cloudinary[1]}f_auto,q_auto,w_1600,c_limit/${cloudinary[2]}` : url;
}

export function videoPoster(url) {
    if (!url) return null;

    const stream = STREAM_MP4.exec(url);
    if (stream) return `${stream[1]}/thumbnails/thumbnail.jpg?time=0s&width=1600`;

    const cloudinary = CLOUDINARY_VIDEO.exec(url);
    if (cloudinary) return `${cloudinary[1]}so_0,f_auto,q_auto,w_1600,c_limit/${cloudinary[2].replace(/\.[a-z0-9]+$/i, ".jpg")}`;

    return null;
}
