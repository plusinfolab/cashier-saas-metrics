<div {{ $attributes->merge(['class' => 'bg-white rounded-lg shadow p-6']) }}>
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-gray-600">{{ $title }}</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $displayValue() }}</p>
            @if($period)
                <p class="mt-1 text-sm text-gray-500">{{ $period }}</p>
            @endif
        </div>
        @if($icon)
            <div class="p-3 rounded-full {{ $colorClasses() }}">
                <x-dynamic-component :component="$icon" class="h-6 w-6" />
            </div>
        @endif
    </div>
    @if($trend)
        <div class="mt-4 flex items-center text-sm">
            <x-dynamic-component :component="$trendIcon()" class="h-4 w-4 {{ $trendColorClasses() }}" />
            <span class="{{ $trendColorClasses() }} ml-1">{{ $trend === 'up' ? 'Increased' : 'Decreased' }}</span>
        </div>
    @endif
</div>
