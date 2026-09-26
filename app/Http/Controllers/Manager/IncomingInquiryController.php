<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\IncomingInquiry;
use Illuminate\Contracts\View\View;

/**
 * Служебный просмотр входящих обращений (PoC E5-A2A).
 *
 * Доступ ограничен маршрутным middleware role:manager,admin (см. routes/web.php).
 * Только чтение: конвертации в Booking, назначения и смены статусов здесь нет.
 */
class IncomingInquiryController extends Controller
{
    public function index(): View
    {
        $inquiries = IncomingInquiry::query()
            ->orderByDesc('first_notified_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('manager.inquiries.index', compact('inquiries'));
    }

    public function show(IncomingInquiry $inquiry): View
    {
        return view('manager.inquiries.show', compact('inquiry'));
    }
}
