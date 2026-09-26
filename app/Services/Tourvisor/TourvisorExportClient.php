<?php

namespace App\Services\Tourvisor;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * Серверный клиент Tourvisor Export API (экспорт заявок).
 *
 * Границы безопасности:
 *  - ключ берётся только из config('services.tourvisor.export_api_key');
 *  - базовый URL зафиксирован в коде, из запроса не принимается;
 *  - все ошибки приводятся к TourvisorExportException с фиксированным кодом,
 *    исходные исключения HTTP-клиента не пробрасываются (их текст содержит URL
 *    вместе с ключом в query-строке);
 *  - тело ответа никогда не логируется и не попадает в сообщения.
 *
 * Контракт (публичная документация wiki.tourvisor.ru, живой ответ не проверен):
 *  GET https://tourvisor.ru/xml/orders.php?authkey=…&id=…&format=json
 *  → {"orders":{"order":[{…}]}}
 * Подлинность получаемых данных обеспечивается самим фактом запроса к
 * известному хосту с серверным ключом; идентификатор из webhook — лишь указатель.
 */
class TourvisorExportClient
{
    private const ORDERS_URL = 'https://tourvisor.ru/xml/orders.php';

    /**
     * Получить обычную заявку по её идентификатору.
     *
     * @throws TourvisorExportException
     */
    public function fetchInquiry(TourvisorInquiryType $type, string $externalId): TourvisorInquiry
    {
        if (! $type->isImportSupported()) {
            throw new TourvisorExportException(TourvisorExportException::UNSUPPORTED_TYPE);
        }

        if (! preg_match('/^[0-9]{1,18}$/', $externalId)) {
            throw new TourvisorExportException(TourvisorExportException::MALFORMED);
        }

        $key = config('services.tourvisor.export_api_key');

        if (! is_string($key) || trim($key) === '') {
            throw new TourvisorExportException(TourvisorExportException::NOT_CONFIGURED);
        }

        $response = $this->send($key, $externalId);

        return $this->parse($type, $externalId, $response);
    }

    private function send(string $key, string $externalId): Response
    {
        try {
            $response = Http::acceptJson()
                ->timeout(max(1, (int) config('services.tourvisor.timeout', 10)))
                ->connectTimeout(max(1, (int) config('services.tourvisor.connect_timeout', 5)))
                ->get(self::ORDERS_URL, [
                    'authkey' => $key,
                    'id' => $externalId,
                    'format' => 'json',
                ]);
        } catch (ConnectionException $e) {
            // Текст исключения содержит URL с ключом — используем только признак таймаута.
            $timedOut = str_contains($e->getMessage(), 'cURL error 28') || stripos($e->getMessage(), 'timed out') !== false;

            throw new TourvisorExportException($timedOut ? TourvisorExportException::TIMEOUT : TourvisorExportException::NETWORK);
        }

        $status = $response->status();

        if ($status === 401 || $status === 403) {
            throw new TourvisorExportException(TourvisorExportException::UNAUTHORIZED);
        }

        if ($status === 429) {
            throw new TourvisorExportException(TourvisorExportException::RATE_LIMITED);
        }

        if ($status >= 500) {
            throw new TourvisorExportException(TourvisorExportException::HTTP_ERROR);
        }

        if ($status < 200 || $status >= 300) {
            throw new TourvisorExportException(TourvisorExportException::HTTP_CLIENT_ERROR);
        }

        return $response;
    }

    private function parse(TourvisorInquiryType $type, string $externalId, Response $response): TourvisorInquiry
    {
        try {
            $decoded = json_decode($response->body(), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new TourvisorExportException(TourvisorExportException::MALFORMED);
        }

        if (! is_array($decoded) || ! array_key_exists('orders', $decoded)) {
            throw new TourvisorExportException(TourvisorExportException::MALFORMED);
        }

        $orders = $decoded['orders'];

        // Пустой результат в документации не описан: пустая строка/массив/отсутствие
        // `order` трактуем как «заявка не найдена».
        if ($orders === '' || $orders === null || $orders === [] || (is_array($orders) && ! array_key_exists('order', $orders))) {
            throw new TourvisorExportException(TourvisorExportException::NOT_FOUND);
        }

        if (! is_array($orders) || ! is_array($orders['order'])) {
            throw new TourvisorExportException(TourvisorExportException::MALFORMED);
        }

        $list = $orders['order'];

        // Одиночная заявка может прийти объектом, а не списком (типично для XML→JSON).
        if ($list !== [] && ! array_is_list($list)) {
            $list = [$list];
        }

        if ($list === []) {
            throw new TourvisorExportException(TourvisorExportException::NOT_FOUND);
        }

        foreach ($list as $order) {
            if (is_array($order) && isset($order['id']) && (is_string($order['id']) || is_int($order['id'])) && (string) $order['id'] === $externalId) {
                return TourvisorInquiry::fromOrderArray($type, $order);
            }
        }

        // Ответ не содержит запрошенный id: данным не доверяем.
        throw new TourvisorExportException(TourvisorExportException::ID_MISMATCH);
    }
}
