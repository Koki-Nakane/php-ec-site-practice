(() => {
    const form = document.getElementById('post-search-form');
    const input = document.getElementById('post-search');
    const result = document.getElementById('post-search-result');

    if (!form || !input || !result) {
        return;
    }

    const renderMessage = (text, isError = false) => {
        result.textContent = text;
        result.style.color = isError ? '#b91c1c' : '#334155';
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const keyword = (input.value || '').trim();
        if (keyword === '') {
            renderMessage('キーワードを入力してください。', true);
            return;
        }

        renderMessage('検索中です...');
        try {
            const res = await fetch(`/posts?q=${encodeURIComponent(keyword)}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();

            if (!res.ok || !data || !Array.isArray(data.data)) {
                throw new Error('検索結果の取得に失敗しました。');
            }

            if (data.data.length === 0) {
                renderMessage('該当する記事は見つかりませんでした。');
                return;
            }

            const titles = data.data.map((p) => p.title || '(タイトルなし)').slice(0, 5);
            renderMessage(`検索結果: ${data.data.length}件 / 先頭5件: ${titles.join(' / ')}`);
        } catch (e) {
            const message = e instanceof Error ? e.message : '検索に失敗しました。';
            renderMessage(message, true);
        }
    });
})();
