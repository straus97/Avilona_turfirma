<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IncomingInquiry;
use App\Services\Tourvisor\TourvisorInquiryIntake;
use App\Services\Tourvisor\TourvisorInquiryType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Приёмник уведомлений Tourvisor webhook (GET ?id=…&type=…).
 *
 * Уведомление НЕ считается доверенным: подписи в документации нет. Из него
 * берутся только два строго проверенных значения (числовой id и тип 0/1);
 * реальные данные обращения загружаются отдельным серверным запросом к
 * Tourvisor с серверным ключом. Контроллер не аутентифицирует пользователя,
 * не создаёт Booking и не принимает никаких URL/данных клиента из запроса.
 */
class TourvisorWebhookController extends Controller
{
    public function __invoke(Request $request, TourvisorInquiryIntake $intake): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'id' => ['required', 'string', 'regex:/^[0-9]{1,18}$/'],
            'type' => ['required', 'string', 'in:0,1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'invalid'], 400);
        }

        $result = $intake->receive(
            TourvisorInquiryType::from((int) $request->query('type')),
            (string) $request->query('id'),
        );

        /** @var IncomingInquiry $inquiry */
        $inquiry = $result['inquiry'];

        if ($inquiry->state === IncomingInquiry::STATE_IMPORTED || $inquiry->state === IncomingInquiry::STATE_UNSUPPORTED) {
            return response()->json(['status' => $result['created'] ? 'accepted' : 'duplicate']);
        }

        return response()->json(['status' => 'accepted'], 202);
    }
}
