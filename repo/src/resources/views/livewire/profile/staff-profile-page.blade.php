<div>
    <h1 class="text-2xl font-bold text-surface-900 mb-6">Staff Profile</h1>

    @if($saved)<div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-3 text-green-700 text-sm" role="status">Profile saved successfully.</div>@endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Account info --}}
        <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
            <h2 class="text-lg font-semibold mb-4">Account</h2>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-surface-500">Username</dt><dd class="font-medium">{{ $user->username }}</dd></div>
                <div class="flex justify-between"><dt class="text-surface-500">Full Name</dt><dd class="font-medium">{{ $user->full_name }}</dd></div>
                <div class="flex justify-between"><dt class="text-surface-500">Role</dt><dd><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-brand-100 text-brand-800">{{ ucfirst($user->role) }}</span></dd></div>
            </dl>
        </div>

        {{-- Editable profile --}}
        <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
            <h2 class="text-lg font-semibold mb-4">Staff Details</h2>
            @if(!$profile || !$profile->isComplete())
                <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 p-3 text-amber-800 text-sm" role="alert">
                    <x-icon name="exclamation-triangle" class="w-4 h-4 inline -mt-0.5 mr-1"/>
                    Complete all fields to unlock check-in/check-out and order approval.
                </div>
            @endif

            <form wire:submit="save" class="space-y-4">
                <div>
                    <label for="employee_id" class="block text-sm font-medium text-surface-700 mb-1">Employee ID <span class="text-red-500">*</span></label>
                    <input wire:model="employee_id" id="employee_id" type="text" required class="w-full rounded-lg border-surface-200 text-sm"/>
                    @error('employee_id')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="department" class="block text-sm font-medium text-surface-700 mb-1">Department <span class="text-red-500">*</span></label>
                    <input wire:model="department" id="department" type="text" required class="w-full rounded-lg border-surface-200 text-sm"/>
                    @error('department')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="title" class="block text-sm font-medium text-surface-700 mb-1">Title <span class="text-red-500">*</span></label>
                    <input wire:model="title" id="title" type="text" required class="w-full rounded-lg border-surface-200 text-sm"/>
                    @error('title')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="w-full py-2 px-4 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition" tabindex="0">Save Profile</button>
            </form>
        </div>
    </div>
</div>
