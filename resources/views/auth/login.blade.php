<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4" x-data="{ showPassword: false }">
            <x-input-label for="password" :value="__('Password')" />

            <div class="relative mt-1">
                <x-text-input id="password" class="block w-full pr-10"
                                type="password"
                                x-bind:type="showPassword ? 'text' : 'password'"
                                name="password"
                                required autocomplete="current-password" />

                <button type="button" @click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-md"
                        x-bind:aria-label="showPassword ? 'Скрыть пароль' : 'Показать пароль'"
                        x-bind:aria-pressed="showPassword.toString()">
                    <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10 3.5c-4.5 0-8.34 2.94-9.5 6.5 1.16 3.56 5 6.5 9.5 6.5s8.34-2.94 9.5-6.5C18.34 6.44 14.5 3.5 10 3.5zm0 10.5a4 4 0 110-8 4 4 0 010 8z"/>
                        <path d="M10 8a2 2 0 100 4 2 2 0 000-4z"/>
                    </svg>
                    <svg x-show="showPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="display:none">
                        <path d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-2.2-2.2c1.83-1.32 3.19-3.13 3.92-5.02-1.16-3.56-5-6.5-9.5-6.5-1.6 0-3.11.36-4.44.99L3.28 2.22zM10 6a4 4 0 013.86 5.02l-1.5-1.5A2 2 0 0010 8.02l-1.5-1.5A3.98 3.98 0 0110 6zM3.4 6.6l1.47 1.47C3.3 9.02 2.3 10.36 1.75 10c1.16 3.56 5 6.5 9.5 6.5.98 0 1.93-.14 2.82-.4l-1.45-1.45A4 4 0 016.35 8.65L4.87 7.17c-.53.46-1 .99-1.47 1.57z"/>
                    </svg>
                </button>
            </div>

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ml-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ml-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>

        <div class="flex items-center justify-center mt-4">
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('register') }}">
                    {{ __('Register') }}
                </a>
        </div>
    </form>
</x-guest-layout>
