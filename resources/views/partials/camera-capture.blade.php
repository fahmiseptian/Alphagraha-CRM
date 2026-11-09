@once
@push('scripts')
<div id="crm-camera" class="fixed inset-0 z-[80] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="crm-camera-title">
    <div class="absolute inset-0 bg-slate-900/70" data-camera-close></div>
    <div class="relative flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <h3 id="crm-camera-title" class="text-sm font-semibold text-slate-800" data-camera-title>Ambil foto</h3>
            <button type="button" data-camera-close class="rounded-lg px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100">Tutup</button>
        </div>
        <div class="bg-slate-900">
            <video data-camera-video class="max-h-[60vh] w-full bg-black object-contain" autoplay playsinline muted></video>
            <img data-camera-preview class="hidden max-h-[60vh] w-full object-contain" alt="Hasil foto">
            <canvas data-camera-canvas class="hidden"></canvas>
        </div>
        <p data-camera-error class="px-4 pt-3 text-sm text-red-600"></p>
        <div class="flex flex-wrap justify-end gap-2 px-4 py-3">
            <button type="button" data-camera-shutter class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Ambil foto</button>
            <button type="button" data-camera-retake class="hidden rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Ulangi</button>
            <button type="button" data-camera-use class="hidden rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Pakai foto</button>
        </div>
    </div>
</div>
<script>
window.CrmCamera = (() => {
    const root = () => document.getElementById('crm-camera');
    let stream = null;
    let shot = null;
    let onFile = null;
    let previewUrl = null;

    const stop = () => {
        if (!stream) return;
        stream.getTracks().forEach((track) => track.stop());
        stream = null;
        const video = root()?.querySelector('[data-camera-video]');
        if (video) video.srcObject = null;
    };

    const setLive = (live) => {
        const el = root();
        if (!el) return;
        el.querySelector('[data-camera-video]')?.classList.toggle('hidden', !live);
        el.querySelector('[data-camera-preview]')?.classList.toggle('hidden', live);
        el.querySelector('[data-camera-shutter]')?.classList.toggle('hidden', !live);
        el.querySelector('[data-camera-retake]')?.classList.toggle('hidden', live);
        el.querySelector('[data-camera-use]')?.classList.toggle('hidden', live);
    };

    const close = () => {
        stop();
        shot = null;
        onFile = null;
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }
        const el = root();
        if (!el) return;
        el.style.display = '';
        el.classList.add('hidden');
        const error = el.querySelector('[data-camera-error]');
        if (error) error.textContent = '';
    };

    const start = async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error('Browser ini tidak bisa membuka kamera. Gunakan Upload.');
        }
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: { ideal: 'environment' } },
            });
        } catch (error) {
            stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: true });
        }
        const video = root().querySelector('[data-camera-video]');
        video.srcObject = stream;
        await video.play();
    };

    const takeShot = () => new Promise((resolve) => {
        const video = root().querySelector('[data-camera-video]');
        const canvas = root().querySelector('[data-camera-canvas]');
        let width = video.videoWidth || 1280;
        let height = video.videoHeight || 720;
        const scale = Math.min(1, 1600 / Math.max(width, height));
        width = Math.max(1, Math.round(width * scale));
        height = Math.max(1, Math.round(height * scale));
        canvas.width = width;
        canvas.height = height;
        canvas.getContext('2d').drawImage(video, 0, 0, width, height);
        canvas.toBlob((blob) => {
            if (!blob) {
                resolve(null);
                return;
            }
            resolve(new File([blob], `do-${Date.now()}.jpg`, { type: 'image/jpeg' }));
        }, 'image/jpeg', 0.85);
    });

    const bind = () => {
        const el = root();
        if (!el || el.dataset.bound === '1') return;
        el.dataset.bound = '1';
        el.querySelectorAll('[data-camera-close]').forEach((button) => {
            button.addEventListener('click', close);
        });
        el.querySelector('[data-camera-shutter]')?.addEventListener('click', async () => {
            const error = el.querySelector('[data-camera-error]');
            shot = await takeShot();
            if (!shot) {
                if (error) error.textContent = 'Foto gagal diambil. Coba lagi.';
                return;
            }
            const preview = el.querySelector('[data-camera-preview]');
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(shot);
            preview.src = previewUrl;
            setLive(false);
            stop();
        });
        el.querySelector('[data-camera-retake]')?.addEventListener('click', async () => {
            const error = el.querySelector('[data-camera-error]');
            if (error) error.textContent = '';
            shot = null;
            setLive(true);
            try {
                await start();
            } catch (error) {
                if (el.querySelector('[data-camera-error]')) {
                    el.querySelector('[data-camera-error]').textContent = 'Kamera tidak bisa dibuka. Izinkan akses kamera, atau gunakan Upload.';
                }
            }
        });
        el.querySelector('[data-camera-use]')?.addEventListener('click', async () => {
            const file = shot;
            const callback = onFile;
            close();
            if (file && callback) await callback(file);
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !el.classList.contains('hidden')) close();
        });
    };

    return {
        async open(options = {}) {
            bind();
            const el = root();
            if (!el) return;
            onFile = options.onFile || null;
            shot = null;
            el.querySelector('[data-camera-title]').textContent = options.title || 'Ambil foto';
            const error = el.querySelector('[data-camera-error]');
            if (error) error.textContent = '';
            setLive(true);
            el.classList.remove('hidden');
            el.style.display = 'flex';
            try {
                await start();
            } catch (error) {
                const message = el.querySelector('[data-camera-error]');
                if (message) message.textContent = 'Kamera tidak bisa dibuka. Izinkan akses kamera, atau gunakan Upload.';
                el.querySelector('[data-camera-shutter]')?.classList.add('hidden');
            }
        },
    };
})();
</script>
@endpush
@endonce
