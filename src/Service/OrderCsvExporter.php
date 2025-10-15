<?php

declare(strict_types=1);

namespace App\Service;

use App\Mapper\OrderMapper;
use App\Model\Order;
use App\Model\User;
use App\Service\Exception\NoOrdersForExportException;
use DateTimeImmutable;

final class OrderCsvExporter
{
    public function __construct(
        private OrderMapper $orders,
    ) {
    }

    public function exportMonthly(User $user, DateTimeImmutable $month): CsvDocument
    {
        $orders = $this->orders->findByUserAndMonth($user, $month);

        if ($orders === []) {
            throw new NoOrdersForExportException($month);
        }

        $csv = $this->buildCsv($orders);
        $filename = sprintf('orders-%s.csv', $month->format('Y-m'));

        return new CsvDocument($filename, $csv);
    }

    /**
     * @param Order[] $orders
     */
    private function buildCsv(array $orders): string
    {
        $handle = fopen('php://temp', 'rb+');
        if ($handle === false) {
            throw new \RuntimeException('一時ストリームを開けませんでした。');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['注文ID', '注文日時', '合計金額', '商品一覧', '配送先']);

        foreach ($orders as $order) {
            fputcsv($handle, [
                $order->getId(),
                $order->getDate()->format('Y-m-d H:i:s'),
                $order->getTotalPrice(),
                $this->formatItems($order),
                $order->getShippingAddress(),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        if ($csv === false) {
            throw new \RuntimeException('CSV生成に失敗しました。');
        }

        return $csv;
    }

    private function formatItems(Order $order): string
    {
        $items = [];
        foreach ($order->getCartItems() as $item) {
            $items[] = sprintf('%s×%d', $item['product']->getName(), $item['quantity']);
        }

        return implode('; ', $items);
    }
}
