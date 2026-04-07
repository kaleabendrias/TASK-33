<div>
    <div class="flex items-center gap-3 mb-6">
        <a href="/orders" class="text-surface-500 hover:text-surface-700 text-sm" tabindex="0">← Orders</a>
        <h1 class="text-2xl font-bold text-surface-900"><?php echo e($order->order_number); ?></h1>
        <?php $colors = ['confirmed'=>'blue','checked_in'=>'indigo','checked_out'=>'violet','completed'=>'green','cancelled'=>'red','refunded'=>'amber','draft'=>'gray']; ?>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-<?php echo e($colors[$order->status] ?? 'gray'); ?>-100 text-<?php echo e($colors[$order->status] ?? 'gray'); ?>-700"><?php echo e(ucfirst(str_replace('_',' ',$order->status))); ?></span>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($error): ?><div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-red-700 text-sm" role="alert"><?php echo e($error); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-surface-200 p-6">
            <h2 class="text-lg font-semibold mb-4">Line Items</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm" role="table" aria-label="Order line items">
                    <thead><tr class="border-b text-left text-surface-500 text-xs uppercase"><th class="py-2 pr-3">Item</th><th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Time</th><th class="py-2 pr-3 text-right">Qty</th><th class="py-2 pr-3 text-right">Subtotal</th><th class="py-2 text-right">Tax</th><th class="py-2 text-right">Total</th></tr></thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $order->lineItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $li): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="border-b border-surface-100">
                            <td class="py-2 pr-3 font-medium"><?php echo e($li->bookableItem->name ?? '—'); ?></td>
                            <td class="py-2 pr-3"><?php echo e($li->booking_date->format('M d')); ?></td>
                            <td class="py-2 pr-3"><?php echo e($li->start_time ?? ''); ?> <?php echo e($li->end_time ? '– '.$li->end_time : ''); ?></td>
                            <td class="py-2 pr-3 text-right"><?php echo e($li->quantity); ?></td>
                            <td class="py-2 pr-3 text-right">$<?php echo e(number_format($li->line_subtotal, 2)); ?></td>
                            <td class="py-2 text-right">$<?php echo e(number_format($li->line_tax, 2)); ?></td>
                            <td class="py-2 text-right font-medium">$<?php echo e(number_format($li->line_total, 2)); ?></td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        
        <div class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
                <h2 class="text-lg font-semibold mb-3">Summary</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd>$<?php echo e(number_format($order->subtotal, 2)); ?></dd></div>
                    <div class="flex justify-between"><dt>Tax</dt><dd>$<?php echo e(number_format($order->tax_amount, 2)); ?></dd></div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->discount_amount > 0): ?><div class="flex justify-between text-green-600"><dt>Discount</dt><dd>-$<?php echo e(number_format($order->discount_amount, 2)); ?></dd></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <div class="flex justify-between font-bold text-lg border-t pt-2"><dt>Total</dt><dd>$<?php echo e(number_format($order->total, 2)); ?></dd></div>
                </dl>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->coupon): ?><p class="text-xs text-surface-500 mt-2">Coupon: <?php echo e($order->coupon->code); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->group_leader_id): ?><p class="text-xs text-surface-500 mt-1">Group Leader: <?php echo e($order->groupLeader->full_name ?? '—'); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->notes): ?><p class="text-xs text-surface-400 mt-2 italic"><?php echo e($order->notes); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6 space-y-2">
                <h2 class="text-lg font-semibold mb-3">Actions</h2>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->status === 'confirmed'): ?>
                    <button wire:click="checkIn" class="w-full py-2 px-4 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition" tabindex="0">Check In</button>
                    <button wire:click="cancel" class="w-full py-2 px-4 rounded-lg bg-red-50 text-red-700 text-sm font-medium hover:bg-red-100 transition" tabindex="0">Cancel</button>
                <?php elseif($order->status === 'checked_in'): ?>
                    <button wire:click="checkOut" class="w-full py-2 px-4 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition" tabindex="0">Check Out</button>
                <?php elseif($order->status === 'checked_out'): ?>
                    <button wire:click="complete" class="w-full py-2 px-4 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700 transition" tabindex="0">Complete</button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($order->status, ['completed','cancelled'])): ?>
                    <button wire:click="refund" class="w-full py-2 px-4 rounded-lg bg-amber-50 text-amber-700 text-sm font-medium hover:bg-amber-100 transition" tabindex="0">
                        Process Refund <?php echo e($order->isWithinFullRefundWindow() ? '(Full)' : '(20% fee)'); ?>

                    </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$order->staff_marked_unavailable): ?>
                        <button wire:click="markUnavailable" class="w-full py-2 px-4 rounded-lg bg-surface-100 text-surface-600 text-sm hover:bg-surface-200 transition" tabindex="0">Mark Staff Unavailable (waive fee)</button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->refunds->count()): ?>
            <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
                <h3 class="text-sm font-semibold mb-2">Refunds</h3>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $order->refunds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="text-xs text-surface-600 py-1 border-b border-surface-100 last:border-0">
                        $<?php echo e(number_format($r->refund_amount, 2)); ?> <?php echo e($r->is_full_refund ? '(full)' : "(fee: \${$r->cancellation_fee})"); ?> — <?php echo e($r->created_at->format('M d H:i')); ?>

                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/orders/order-show.blade.php ENDPATH**/ ?>