<div {{ $attributes->merge(['class' => 'saas-metrics-panel']) }}>
    @if($title)
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900">{{ $title }}</h2>
            <p class="text-gray-600">SaaS Metrics Dashboard</p>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        @foreach($metrics as $key => $metric)
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">{{ $metric['title'] }}</p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">
                            {{ $formatMetric($metric) }}
                        </p>
                        @if($metric['value'] instanceof \PlusInfoLab\CashierSaaSMetrics\Support\MetricResult)
                            @if($metric['value']->metadata())
                                <details class="mt-2">
                                    <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700">
                                        Details
                                    </summary>
                                    <div class="mt-2 text-xs text-gray-600 space-y-1">
                                        @foreach($metric['value']->metadata() as $k => $v)
                                            <div>
                                                <span class="font-medium">{{ str($k)->replace('_', ' ')->title() }}:</span>
                                                @if(is_numeric($v))
                                                    {{ number_format($v, 2) }}
                                                @elseif(is_bool($v))
                                                    {{ $v ? 'Yes' : 'No' }}
                                                @else
                                                    {{ $v }}
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        @endif
                    </div>
                    <div class="p-3 rounded-full bg-opacity-10
                        @if($metric['color'] === 'green') bg-green-500 text-green-600
                        @elseif($metric['color'] === 'red') bg-red-500 text-red-600
                        @elseif($metric['color'] === 'blue') bg-blue-500 text-blue-600
                        @elseif($metric['color'] === 'purple') bg-purple-500 text-purple-600
                        @else bg-gray-500 text-gray-600
                        @endif">
                        <x-dynamic-component :component="$metric['icon']" class="h-6 w-6" />
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if(empty($metrics))
        <div class="text-center py-12 bg-gray-50 rounded-lg">
            <p class="text-gray-500">Unable to load metrics. Please check your configuration.</p>
        </div>
    @endif
</div>
