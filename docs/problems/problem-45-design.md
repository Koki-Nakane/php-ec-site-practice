## Problem 45: クーポン適用ロジック

- `App\Model\Coupon` 配下に共通インターフェースを定義し、固定額 (`FixedAmountCoupon`) と率 (`RateCoupon`) の2種類を実装。
- `Cart` は `applyCoupon()` でクーポンを受け取り、小計 (`getSubtotal`) と割引額 (`getDiscountAmount`) を切り分けたうえで、`getTotalPrice()` に割引後金額を返すように変更。
- 永続化や API 連携はスコープ外。テスト用に `sandbox/coupon_test.php` でインスタンスを直接生成して動作を確認できるようにしている。
