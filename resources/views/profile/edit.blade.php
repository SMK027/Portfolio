<x-app-layout>
    <x-slot name="title">Mon compte</x-slot>
    <x-slot name="header">Mon compte</x-slot>

    <div class="card p-4 sm:p-8">
        <div class="max-w-xl">
            @include('profile.partials.update-profile-information-form')
        </div>
    </div>

    <div class="card p-4 sm:p-8">
        <div class="max-w-xl">
            @include('profile.partials.update-password-form')
        </div>
    </div>

    <div class="card p-4 sm:p-8">
        <div class="max-w-xl">
            @include('profile.partials.two-factor-form')
        </div>
    </div>

    <div class="card p-4 sm:p-8">
        <div class="max-w-xl">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-app-layout>
