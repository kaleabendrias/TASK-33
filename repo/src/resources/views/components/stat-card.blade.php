@props(['label', 'value', 'icon' => 'home'])
<div class="bg-white rounded-xl shadow-sm border border-surface-200 p-5" role="group" aria-label="{{ $label }}">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-surface-500">{{ $label }}</p>
            <p class="mt-1 text-2xl font-bold text-surface-900">{{ $value }}</p>
        </div>
        <div class="p-3 bg-brand-50 rounded-lg">
            <x-icon :name="$icon" class="w-6 h-6 text-brand-600"/>
        </div>
    </div>
</div>
