// assets/js/app.js - interaksi: tema, like, jawab, ubah kategori, polling notifikasi, toast
(() => {
    const csrf = document.querySelector('meta[name=csrf]').content;
    const $ = (s, r = document) => r.querySelector(s);

    function toast(msg) {
        const t = $('#toast'); t.textContent = msg; t.classList.add('show');
        clearTimeout(t._h); t._h = setTimeout(() => t.classList.remove('show'), 3500);
    }
    // semua respons non-2xx (403/422/429) ikut ditangani, bukan hanya success:false
    async function post(url, data) {
        const body = data instanceof FormData ? data : new URLSearchParams(data);
        const r = await fetch(url, { method: 'POST', body, headers: { 'X-CSRF-Token': csrf } });
        let j = {}; try { j = await r.json(); } catch (e) {}
        if (!r.ok || !j.success) throw { message: j.message || 'Terjadi kesalahan, coba lagi.' };
        return j;
    }

    document.addEventListener('click', async (e) => {
        const tema = e.target.closest('[data-theme-toggle]');
        if (tema) {
            const now = document.documentElement.dataset.theme || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            const next = now === 'dark' ? 'light' : 'dark';
            document.documentElement.dataset.theme = next;
            try { localStorage.setItem('theme', next); } catch (x) {}
            return;
        }
        const like = e.target.closest('[data-like]');
        if (like) {
            try {
                const j = await post('api/like.php', { post_id: like.dataset.like });
                like.classList.toggle('on', j.liked); $('.n', like).textContent = j.count;
                like.setAttribute('aria-pressed', j.liked);
            } catch (err) { toast(err.message); }
            return;
        }
        const card = e.target.closest('.post[data-href]');
        if (card && !e.target.closest('a, button, select, textarea, form')) location.href = card.dataset.href;
    });

    const rf = $('#reply-form');
    if (rf) rf.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = $('button', rf); btn.disabled = true;
        try {
            const j = await post('api/reply.php', new FormData(rf));
            const box = $('#replies'); const kosong = $('.empty', box); if (kosong) kosong.remove();
            box.insertAdjacentHTML('beforeend', j.html);
            rf.content.value = ''; rf.content.dispatchEvent(new Event('input')); toast(j.message);
        } catch (err) { toast(err.message); }
        btn.disabled = false;
    });

    const rc = $('#recat');
    if (rc) rc.addEventListener('submit', async (e) => {
        e.preventDefault();
        try { const j = await post('api/recategorize.php', { post_id: rc.dataset.post, category_id: rc.category_id.value }); toast(j.message); }
        catch (err) { toast(err.message); }
    });

    document.querySelectorAll('textarea').forEach((t) => t.addEventListener('input', () => { t.style.height = 'auto'; t.style.height = t.scrollHeight + 'px'; }));
    // penghitung karakter
    document.querySelectorAll('textarea[maxlength]').forEach((t) => {
        const c = t.closest('.fields')?.querySelector('[data-count]');
        if (!c) return;
            const upd = () => { c.textContent = t.value.length + '/' + t.maxLength; c.classList.toggle('warn', t.value.length > t.maxLength * 0.9); };
            t.addEventListener('input', upd); upd();
        });
    // polling lonceng tiap 30 detik, hanya saat tab terlihat
    async function tick() {
        if (document.visibilityState !== 'visible') return;
        try {
            const j = await (await fetch('api/notifications.php')).json();
            document.querySelectorAll('[data-bell]').forEach((b) => { b.textContent = j.unread; b.hidden = !j.unread; });
        } catch (e) {}
    }
    setInterval(tick, 30000);
    document.addEventListener('visibilitychange', tick);
})();
