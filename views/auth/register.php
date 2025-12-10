<?php
/** @var array $msgs */
/** @var array{name:string,email:string,postal_code:string,prefecture:string,city:string,street:string} $old */
/** @var string $passwordPolicy */
/** @var string $csrfToken */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー登録</title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 520px; line-height: 1.6; }
        form { border: 1px solid #ddd; padding: 1.5rem; border-radius: 8px; background: #fff; }
        label { display: block; margin-bottom: .75rem; }
        input, textarea { width: 100%; padding: .5rem; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; padding: .75rem; border: none; border-radius: 4px; background: #0d47a1; color: #fff; font-size: 1rem; cursor: pointer; }
        button:hover { background: #0b3c8c; }
        .flash { margin-bottom: .75rem; padding: .75rem; border-radius: 4px; background: #fbe9e7; color: #c62828; }
        .link { margin-top: 1rem; text-align: center; }
        .hint { margin-top: .25rem; font-size: .9rem; color: #555; }
        .inline-row { display: flex; gap: .5rem; align-items: center; }
    </style>
</head>
<body>
    <h1>ユーザー登録</h1>
    <?php foreach ((array) $msgs as $message): ?>
        <div class="flash">※ <?php echo htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>
    <form action="/register" method="post">
        <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <label>
            ユーザー名 (半角英数字とアンダースコア)
            <input type="text" name="name" value="<?php echo htmlspecialchars($old['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>
        <label>
            メールアドレス
            <input type="email" name="email" value="<?php echo htmlspecialchars($old['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>
        <label>
            郵便番号 (ハイフンなし7桁)
            <div class="inline-row">
                <input type="text" name="postal_code" inputmode="numeric" pattern="\d{7}" maxlength="7" value="<?php echo htmlspecialchars($old['postal_code'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <button type="button" id="postal-code-lookup" style="flex-shrink:0; padding:.5rem 1rem;">郵便番号から住所を自動入力</button>
            </div>
            <p id="lookup-message" class="hint"></p>
        </label>
        <label>
            都道府県
            <input type="text" name="prefecture" value="<?php echo htmlspecialchars($old['prefecture'], ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>
        <label>
            市区町村
            <input type="text" name="city" value="<?php echo htmlspecialchars($old['city'], ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>
        <label>
            それ以降の住所
            <input type="text" name="street" value="<?php echo htmlspecialchars($old['street'], ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>
        <label>
            パスワード (8文字以上)
            <input type="password" name="password" required>
            <small class="hint"><?php echo htmlspecialchars($passwordPolicy, ENT_QUOTES, 'UTF-8'); ?></small>
        </label>
        <label>
            パスワード（確認）
            <input type="password" name="password_confirmation" required>
        </label>
        <button type="submit">登録する</button>
    </form>
    <div class="link"><a href="/login">ログインはこちら</a></div>
    <script>
    (() => {
        const button = document.getElementById('postal-code-lookup');
        const postalInput = document.querySelector('input[name="postal_code"]');
        const prefInput = document.querySelector('input[name="prefecture"]');
        const cityInput = document.querySelector('input[name="city"]');
        const streetInput = document.querySelector('input[name="street"]');
        const messageEl = document.getElementById('lookup-message');

        const setMessage = (text, isError) => {
            if (!messageEl) return;
            messageEl.textContent = text;
            messageEl.style.color = isError ? '#c62828' : '#1b5e20';
        };

        if (!button || !postalInput) {
            return;
        }

        button.addEventListener('click', async () => {
            const postal = (postalInput.value || '').replace(/\D/g, '');
            if (postal.length !== 7) {
                setMessage('郵便番号はハイフンなしの7桁で入力してください。', true);
                return;
            }

            setMessage('検索中です...', false);

            try {
                const response = await fetch(`/api/postal-code?postal_code=${encodeURIComponent(postal)}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();

                if (!response.ok || (data && data.error)) {
                    const message = typeof data?.error === 'string' ? data.error : '住所の取得に失敗しました。';
                    throw new Error(message);
                }

                if (prefInput) {
                    prefInput.value = data.prefecture ?? '';
                }
                if (cityInput) {
                    cityInput.value = data.city ?? '';
                }
                if (streetInput) {
                    streetInput.value = data.town ?? '';
                }

                setMessage('住所を補完しました。', false);
            } catch (error) {
                const message = error instanceof Error && error.message !== ''
                    ? error.message
                    : '住所の取得に失敗しました。';
                setMessage(message, true);
            }
        });
    })();
    </script>
</body>
</html>
