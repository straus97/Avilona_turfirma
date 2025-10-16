<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\SendContactRequest;
use App\Mail\ContactFormMail;
use App\Mail\UserFormMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SendContactController extends Controller
{
    public function __invoke(SendContactRequest $request)
    {
        try {
            $validatedData = $request->validated();
            // Форматируем сообщение перед отправкой
            $validatedData['message'] = $this->formatMessage($validatedData['message']);
            Mail::to('straus97@mail.ru')
                ->send(new ContactFormMail($validatedData));
            Mail::to($validatedData['email'])
                ->send(new UserFormMail($validatedData));
            return redirect()->back()->with('success', 'Ваше сообщение успешно отправлено.');
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Ошибка отправки сообщения.');
        }
    }

    private function formatMessage($message)
    {
        return wordwrap($message, 63, "-\n", true);
    }
}
