<div>
    <h1 class="text-2xl font-bold text-surface-900 mb-6">Commission Report</h1>

    
    <div class="flex flex-wrap items-end gap-3 mb-6">
        <div><label class="block text-sm font-medium text-surface-700 mb-1">From</label><input wire:model.live="dateFrom" type="date" class="rounded-lg border-surface-200 text-sm"/></div>
        <div><label class="block text-sm font-medium text-surface-700 mb-1">To</label><input wire:model.live="dateTo" type="date" class="rounded-lg border-surface-200 text-sm"/></div>
    </div>

    
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <?php if (isset($component)) { $__componentOriginal527fae77f4db36afc8c8b7e9f5f81682 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.stat-card','data' => ['label' => 'Attributed Revenue','value' => '$'.number_format($totals['revenue'], 2),'icon' => 'banknotes']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Attributed Revenue','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('$'.number_format($totals['revenue'], 2)),'icon' => 'banknotes']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682)): ?>
<?php $attributes = $__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682; ?>
<?php unset($__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal527fae77f4db36afc8c8b7e9f5f81682)): ?>
<?php $component = $__componentOriginal527fae77f4db36afc8c8b7e9f5f81682; ?>
<?php unset($__componentOriginal527fae77f4db36afc8c8b7e9f5f81682); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal527fae77f4db36afc8c8b7e9f5f81682 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.stat-card','data' => ['label' => 'Commission Earned','value' => '$'.number_format($totals['commission'], 2),'icon' => 'chart-bar']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Commission Earned','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('$'.number_format($totals['commission'], 2)),'icon' => 'chart-bar']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682)): ?>
<?php $attributes = $__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682; ?>
<?php unset($__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal527fae77f4db36afc8c8b7e9f5f81682)): ?>
<?php $component = $__componentOriginal527fae77f4db36afc8c8b7e9f5f81682; ?>
<?php unset($__componentOriginal527fae77f4db36afc8c8b7e9f5f81682); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal527fae77f4db36afc8c8b7e9f5f81682 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.stat-card','data' => ['label' => 'Total Orders','value' => $totals['orders'],'icon' => 'clipboard']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('stat-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Total Orders','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($totals['orders']),'icon' => 'clipboard']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682)): ?>
<?php $attributes = $__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682; ?>
<?php unset($__attributesOriginal527fae77f4db36afc8c8b7e9f5f81682); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal527fae77f4db36afc8c8b7e9f5f81682)): ?>
<?php $component = $__componentOriginal527fae77f4db36afc8c8b7e9f5f81682; ?>
<?php unset($__componentOriginal527fae77f4db36afc8c8b7e9f5f81682); ?>
<?php endif; ?>
    </div>

    
    <div class="bg-white rounded-xl shadow-sm border border-surface-200 overflow-x-auto mb-6">
        <table class="w-full text-sm" role="table" aria-label="Commission cycles">
            <thead><tr class="border-b text-left text-surface-500 text-xs uppercase"><th class="px-4 py-3">Cycle</th><th class="px-4 py-3">Type</th><th class="px-4 py-3 text-right">Revenue</th><th class="px-4 py-3 text-right">Rate</th><th class="px-4 py-3 text-right">Commission</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Hold Until</th></tr></thead>
            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $commissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="border-b border-surface-100">
                    <td class="px-4 py-3"><?php echo e($c->cycle_start->format('M d')); ?> – <?php echo e($c->cycle_end->format('M d')); ?></td>
                    <td class="px-4 py-3"><?php echo e(ucfirst($c->cycle_type)); ?></td>
                    <td class="px-4 py-3 text-right">$<?php echo e(number_format($c->attributed_revenue, 2)); ?></td>
                    <td class="px-4 py-3 text-right"><?php echo e(number_format($c->commission_rate * 100, 1)); ?>%</td>
                    <td class="px-4 py-3 text-right font-bold">$<?php echo e(number_format($c->commission_amount, 2)); ?></td>
                    <td class="px-4 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo e($c->status === 'paid' ? 'bg-green-100 text-green-700' : ($c->status === 'held' ? 'bg-amber-100 text-amber-700' : 'bg-surface-100 text-surface-700')); ?>"><?php echo e(ucfirst($c->status)); ?></span></td>
                    <td class="px-4 py-3 text-xs text-surface-500"><?php echo e($c->hold_until?->format('M d, Y') ?? '—'); ?></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="7" class="px-4 py-8 text-center text-surface-500">No commissions for this period.</td></tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>

    
    <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
        <h2 class="text-lg font-semibold mb-4">Attributed Orders</h2>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $attributedOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="flex justify-between items-center py-2 border-b border-surface-100 last:border-0 text-sm">
                <div><span class="font-medium"><?php echo e($o->order_number); ?></span> <span class="text-surface-500">— <?php echo e($o->confirmed_at?->format('M d, H:i')); ?></span></div>
                <span class="font-medium">$<?php echo e(number_format($o->total, 2)); ?></span>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <p class="text-surface-500 text-sm">No attributed orders in this period.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/settlement/commission-report.blade.php ENDPATH**/ ?>