<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <!-- User Agreement -->
        <div class="block mt-4">
            <label for="user_agreement_accepted" class="inline-flex items-center">
                <input id="user_agreement_accepted" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="user_agreement_accepted" value="1" @checked(old('user_agreement_accepted'))>
                <span class="ml-2 text-sm text-gray-600">Я принимаю условия <a class="underline" href="{{ asset('/documents/User_Agreement.pdf') }}" target="_blank" rel="noopener">Пользовательского соглашения</a>.</span>
            </label>
            <x-input-error :messages="$errors->get('user_agreement_accepted')" class="mt-2" />
        </div>

        <!-- Personal Data Consent -->
        <div class="block mt-4">
            <label for="personal_data_consent_accepted" class="inline-flex items-center">
                <input id="personal_data_consent_accepted" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="personal_data_consent_accepted" value="1" @checked(old('personal_data_consent_accepted'))>
                <span class="ml-2 text-sm text-gray-600">Я даю согласие на обработку моих персональных данных в целях регистрации учётной записи и использования личного кабинета в соответствии с <a class="underline" href="{{ route('registration_personal_data_consent.info') }}" target="_blank" rel="noopener">Согласием на обработку персональных данных</a>.</span>
            </label>
            <x-input-error :messages="$errors->get('personal_data_consent_accepted')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ml-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>

        <div class="flex items-center justify-center mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                {{ __('Forgot your password?') }}
            </a>
        </div>
    </form>
</x-guest-layout>
