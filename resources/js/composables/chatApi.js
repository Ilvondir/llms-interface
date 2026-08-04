export function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    if (meta?.content) {
        return meta.content;
    }

    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export function extractModelIds(payload) {
    const rows = Array.isArray(payload?.data)
        ? payload.data
        : (Array.isArray(payload) ? payload : []);

    return rows
        .map((row) => {
            if (typeof row === 'string') {
                return row;
            }

            return row?.id ?? row?.name ?? null;
        })
        .filter((id) => typeof id === 'string' && id.trim() !== '');
}
