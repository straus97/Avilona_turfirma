<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserRegistrationConsent;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use App\Models\Role;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'user_agreement_accepted' => ['required', 'accepted'],
            'personal_data_consent_accepted' => ['required', 'accepted'],
        ], [
            'user_agreement_accepted.required' => 'Пожалуйста, примите условия Пользовательского соглашения',
            'user_agreement_accepted.accepted' => 'Пожалуйста, примите условия Пользовательского соглашения',
            'personal_data_consent_accepted.required' => 'Пожалуйста, дайте согласие на обработку персональных данных',
            'personal_data_consent_accepted.accepted' => 'Пожалуйста, дайте согласие на обработку персональных данных',
        ]);

        $userAgreementVersion = $this->documentVersion(public_path('documents/User_Agreement.pdf'));
        $personalDataConsentVersion = $this->documentVersion(resource_path('views/legal/registration-personal-data-consent.blade.php'));

        $acceptedAt = now();

        $user = DB::transaction(function () use ($request, $userAgreementVersion, $personalDataConsentVersion, $acceptedAt) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // Назначить пользователю роль туриста по умолчанию
            $touristRole = Role::firstOrCreate(
                ['name' => Role::TOURIST],
                ['description' => Role::availableRoles()[Role::TOURIST]]
            );
            $user->roles()->attach($touristRole->id);

            UserRegistrationConsent::create([
                'user_id' => $user->id,
                'user_agreement_accepted_at' => $acceptedAt,
                'user_agreement_version' => $userAgreementVersion,
                'personal_data_consent_accepted_at' => $acceptedAt,
                'personal_data_consent_version' => $personalDataConsentVersion,
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(RouteServiceProvider::HOME);
    }

    private function documentVersion(string $path): string
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Legal document not found for version fingerprint: {$path}");
        }

        $hash = hash_file('sha256', $path);

        if (! is_string($hash) || $hash === '') {
            throw new \RuntimeException("Failed to compute version fingerprint for: {$path}");
        }

        return 'sha256:' . $hash;
    }
}
