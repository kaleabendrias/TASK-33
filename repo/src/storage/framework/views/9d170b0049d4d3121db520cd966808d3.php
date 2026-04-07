<div class="w-full max-w-md mx-auto">
    <div class="bg-white rounded-2xl shadow-lg p-8">
        <h1 class="text-2xl font-bold text-surface-900 mb-1 text-center">ServicePlatform</h1>
        <p class="text-sm text-surface-500 text-center mb-6">Sign in to your account</p>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($error): ?>
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-red-700 text-sm" role="alert"><?php echo e($error); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <form wire:submit="login" class="space-y-4">
            <div>
                <label for="username" class="block text-sm font-medium text-surface-700 mb-1">Username</label>
                <input
                    wire:model="username"
                    id="username"
                    type="text"
                    autocomplete="username"
                    required
                    autofocus
                    class="block w-full rounded-lg border-surface-200 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm px-3 py-2.5"
                    aria-describedby="username-help"
                />
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-surface-700 mb-1">Password</label>
                <input
                    wire:model="password"
                    id="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="block w-full rounded-lg border-surface-200 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm px-3 py-2.5"
                />
            </div>
            <button
                type="submit"
                class="w-full flex justify-center py-2.5 px-4 rounded-lg text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 transition"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-60"
            >
                <span wire:loading.remove>Sign In</span>
                <span wire:loading>Signing in…</span>
            </button>
        </form>
    </div>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/auth/login.blade.php ENDPATH**/ ?>