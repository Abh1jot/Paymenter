<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="flex flex-col gap-4 col-span-2">
        <div class="flex flex-col md:flex-row gap-4 bg-background-secondary p-3 rounded-md">
            @if ($product->image)
                <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" class="w-full md:w-48 h-48 object-cover rounded-md">
            @endif
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-bold">
                    {{ $product->name }}
                </h1>
                <p class="text-sm">
                    {!! $product->description !!}
                </p>
                <div class="flex flex-col gap-1">
                    <span class="text-xl font-bold">
                        {{ $plan->price() }}
                    </span>
                    @if ($product->availablePlans()->count() > 1)
                        <div class="flex flex-row gap-2">
                            <x-form.select wire:model.live="plan_id" name="plan_id" class="w-fit" :label="__('product.plan')">
                                @foreach ($product->availablePlans() as $availablePlan)
                                    <option value="{{ $availablePlan->id }}">
                                        {{ $availablePlan->name }} - {{ $availablePlan->price() }}
                                    </option>
                                @endforeach
                            </x-form.select>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @foreach ($this->getCheckoutConfig() as $configOption)
            @php
                $configOption = (object) $configOption;
            @endphp
            <x-form.configoption :config="$configOption" :name="'checkoutConfig.' . $configOption->name" />
        @endforeach
        @foreach ($product->configOptions as $configOption)
            <x-form.configoption :config="$configOption" :name="'configOptions.' . $configOption->id">
                @if ($configOption->type == 'select')
                    @foreach ($configOption->children as $configOptionValue)
                        <option value="{{ $configOptionValue->id }}">
                            {{ $configOptionValue->name }} -
                            {{ $configOptionValue->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit) }}
                        </option>
                    @endforeach
                @elseif($configOption->type == 'radio')
                    @foreach ($configOption->children as $configOptionValue)
                        <div class="flex flex-row gap-2">
                            <input type="radio" value="{{ $configOptionValue->id }}"
                                wire:model.live="configOptions.{{ $configOption->id }}"
                                id="{{ $configOptionValue->id }}">
                            <label for="{{ $configOptionValue->id }}">
                                {{ $configOptionValue->name }} -
                                {{ $configOptionValue->price(billing_period: $plan->billing_period, billing_unit: $plan->billing_unit) }}
                            </label>
                        </div>
                    @endforeach
                @elseif($configOption->type == 'dropdown')
                    @foreach ($configOption->children as $configOptionValue => $configOptionValueName)
                        <div class="flex flex-row gap-2">
                            <input type="radio" value="{{ $configOptionValue }}"
                                wire:model.live="configOptions.{{ $configOption->id }}"
                                id="{{ $configOptionValue }}">
                            <label for="{{ $configOptionValue }}">
                                {{ $configOptionValueName }}
                            </label>
                        </div>
                    @endforeach
                @endif
            </x-form.configoption>
        @endforeach
    </div>
    <div class="flex flex-col gap-2 w-full col-span-1 bg-background-secondary p-3 rounded-md h-fit">
        <h2 class="text-2xl font-semibold  mb-2">
            {{ __('product.order_summary') }}
        </h2>
        @php
            if (!isset($fees) || !is_array($fees)) {
                $applicableFees = class_exists(\Paymenter\Extensions\Others\CustomFees\Models\Fee::class)
                    ? \Paymenter\Extensions\Others\CustomFees\Models\Fee::forProduct($product)
                    : collect();
                $fees = [];
                $baseSubtotal = (float) ($total->subtotal ?: ($total->price ?? 0));
                foreach ($applicableFees as $f) {
                    $fees[] = [
                        'name' => $f->name,
                        'rate' => (float) $f->rate,
                        'amount' => $f->calculateFee($baseSubtotal),
                    ];
                }
            }
        @endphp

        @if ((isset($fees) && count($fees) > 0) || $total->total_tax > 0)
            <div class="font-semibold flex justify-between">
                <h4>{{ __('invoices.subtotal') }}:</h4> {{ $total->format($total->subtotal ?: $total->price) }}
            </div>
        @endif

        @if (isset($fees) && count($fees) > 0)
            @foreach ($fees as $fee)
                <div class="font-semibold flex justify-between text-sm text-secondary">
                    <h4>{{ $fee['name'] }} ({{ rtrim(rtrim(number_format($fee['rate'], 2), '0'), '.') }}%):</h4>
                    <span>{{ $total->format($fee['amount']) }}</span>
                </div>
            @endforeach
        @endif

        @if ($total->total_tax > 0)
            <div class="font-semibold flex justify-between">
                <h4>{{ \App\Classes\Settings::tax()->name }} ({{ \App\Classes\Settings::tax()->rate }}%):</h4> {{ $total->formatted->total_tax }}
            </div>
        @endif
        <div class="text-lg font-semibold flex justify-between">
            <h4>{{ __('product.total_today') }}:</h4> {{ $total }}
        </div>
        @if ($total->setup_fee > 0 && $plan->type == 'recurring')
            <div class="text- font-semibold flex justify-between ">
                <h4>{{ __('product.then_after_x', ['time' => $plan->billing_period . ' ' . trans_choice(__('services.billing_cycles.' . $plan->billing_unit), $plan->billing_period)]) }}:
                </h4> {{ $total->format($total->price) }}
            </div>
        @endif
        @if (($product->stock > 0 || !$product->stock) && $product->price()->available)
            <div>
                <x-button.primary wire:click="checkout" wire:loading.attr="disabled">
                    <x-loading target="checkout" />
                    <div wire:loading.remove wire:target="checkout">
                        {{ __('product.checkout') }}
                    </div>
                </x-button.primary>
            </div>
        @endif
    </div>
</div>
