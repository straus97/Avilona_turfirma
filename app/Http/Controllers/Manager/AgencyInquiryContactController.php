<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Manager\Concerns\ResolvesAgencyInquiry;
use App\Models\Inquiry;
use App\Services\Inquiries\InquiryContactData;
use App\Services\Inquiries\InquiryContactException;
use App\Services\Inquiries\InquiryContactStore;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Непроверенный контакт клиента на карточке обращения (AOA-04-I4, Gate A).
 *
 * Роль проверяет middleware role:manager,admin; кто вправе работать с КОНКРЕТНЫМ обращением —
 * InquiryPolicy/InquiryAuthority (чужое, ничьё и бывшее — 404, как у черновика). Идентификаторы
 * обращения и владельца берутся из маршрута и сессии, но не из полей формы: любое лишнее поле
 * отклоняется целиком. Запись, версию и аудит выполняет только InquiryContactStore.
 */
class AgencyInquiryContactController extends Controller
{
    use ResolvesAgencyInquiry;

    public function __construct(private readonly InquiryContactStore $contacts)
    {
    }

    public function update(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $this->visible($inquiry);

        $allowed = ['_token', '_method', 'revision', ...InquiryContactData::FIELDS];
        $unexpected = array_diff(array_map('strval', array_keys($request->all())), $allowed);

        if ($unexpected !== []) {
            throw ValidationException::withMessages([
                'contact' => ['Форма содержит недопустимые поля. Сохраняются только имя, телефон и контакт в мессенджере.'],
            ]);
        }

        $request->validate([
            'revision' => ['required', 'integer', 'min:0'],
            'contact_name' => ['present', 'nullable', 'string'],
            'contact_phone' => ['present', 'nullable', 'string'],
            'contact_messenger' => ['present', 'nullable', 'string'],
        ], [
            'revision.required' => 'Форма устарела. Обновите страницу и повторите.',
            'revision.integer' => 'Форма устарела. Обновите страницу и повторите.',
            'revision.min' => 'Форма устарела. Обновите страницу и повторите.',
            'contact_name.present' => 'Форма устарела. Обновите страницу и повторите.',
            'contact_phone.present' => 'Форма устарела. Обновите страницу и повторите.',
            'contact_messenger.present' => 'Форма устарела. Обновите страницу и повторите.',
            'contact_name.string' => 'Имя: укажите текст.',
            'contact_phone.string' => 'Телефон: укажите текст.',
            'contact_messenger.string' => 'Контакт в мессенджере: укажите текст.',
        ]);

        $input = $request->only(InquiryContactData::FIELDS);

        try {
            $result = $this->contacts->save($inquiry, Auth::user(), $input, (int) $request->input('revision'));
        } catch (AuthorizationException) {
            // Ответственный сменился между показом формы и отправкой.
            abort(404);
        } catch (InquiryContactException $e) {
            return $this->failed($inquiry, $e, $input);
        }

        return redirect()
            ->route('cabinet.manager.agency-inquiries.show', $inquiry)
            ->with('success', match ($result) {
                InquiryContactStore::RESULT_CLEARED => 'Контакт клиента очищен.',
                InquiryContactStore::RESULT_SAVED => 'Контакт клиента сохранён.',
                default => 'Изменений нет — контакт клиента остался прежним.',
            });
    }

    public function clear(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $this->visible($inquiry);

        $unexpected = array_diff(array_map('strval', array_keys($request->all())), ['_token', '_method', 'revision']);

        if ($unexpected !== []) {
            throw ValidationException::withMessages([
                'contact' => ['Форма содержит недопустимые поля.'],
            ]);
        }

        $request->validate(['revision' => ['required', 'integer', 'min:0']], [
            'revision.required' => 'Форма устарела. Обновите страницу и повторите.',
            'revision.integer' => 'Форма устарела. Обновите страницу и повторите.',
            'revision.min' => 'Форма устарела. Обновите страницу и повторите.',
        ]);

        try {
            $result = $this->contacts->clear($inquiry, Auth::user(), (int) $request->input('revision'));
        } catch (AuthorizationException) {
            abort(404);
        } catch (InquiryContactException $e) {
            return $this->failed($inquiry, $e, []);
        }

        return redirect()
            ->route('cabinet.manager.agency-inquiries.show', $inquiry)
            ->with('success', $result === InquiryContactStore::RESULT_CLEARED
                ? 'Контакт клиента очищен.'
                : 'Контакт клиента уже пуст — изменений нет.');
    }

    /** @param  array<string, mixed>  $input */
    private function failed(Inquiry $inquiry, InquiryContactException $e, array $input): RedirectResponse
    {
        if ($e->isStale()) {
            $values = array_filter(
                Arr::only($input, InquiryContactData::FIELDS),
                static fn ($v): bool => is_string($v) && trim($v) !== ''
            );

            return redirect()
                ->route('cabinet.manager.agency-inquiries.show', $inquiry)
                ->with('error', 'Контакт уже изменён в другой вкладке или другим сотрудником. Ваши правки НЕ сохранены: ниже показан актуальный контакт, а ваши значения приведены для сверки.')
                ->with('contact_conflict', $values);
        }

        return redirect()
            ->route('cabinet.manager.agency-inquiries.show', $inquiry)
            ->withErrors([$e->field => $e->getMessage()]);
    }
}
