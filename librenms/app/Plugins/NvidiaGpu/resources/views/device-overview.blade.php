{{-- Device Overview Panel: NVIDIA GPU Status --}}
@if(!empty($gpus))
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-microchip"></i> NVIDIA GPUs
                    <span class="badge">{{ count($gpus) }}</span>
                </h3>
            </div>
            <div class="panel-body" style="padding: 0;">
                @foreach($gpus as $gpu)
                    <div class="gpu-card" style="padding: 12px 15px; border-bottom: 1px solid #eee;">
                        {{-- GPU header --}}
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <div>
                                <strong>GPU {{ $gpu['gpu_index'] }}: {{ $gpu['gpu_name'] }}</strong>
                                <span class="label label-default" style="margin-left: 6px;">{{ $gpu['pstate'] }}</span>
                            </div>
                            <div class="text-muted" style="font-size: 12px;">
                                Driver {{ $gpu['driver'] }}
                            </div>
                        </div>

                        {{-- Metrics grid --}}
                        <div class="row">
                            {{-- Temperature --}}
                            <div class="col-sm-3 col-xs-6" style="margin-bottom: 6px;">
                                <div class="text-muted" style="font-size: 11px; text-transform: uppercase;">Temperature</div>
                                @php
                                    $tempColor = '#5cb85c';
                                    if ($gpu['temp_gpu'] >= 85) $tempColor = '#d9534f';
                                    elseif ($gpu['temp_gpu'] >= 70) $tempColor = '#f0ad4e';
                                @endphp
                                <div style="font-size: 22px; font-weight: bold; color: {{ $tempColor }};">
                                    {{ $gpu['temp_gpu'] !== null ? $gpu['temp_gpu'] . '°C' : '-' }}
                                </div>
                                @if($gpu['temp_mem'] !== null)
                                    <small class="text-muted">VRAM: {{ $gpu['temp_mem'] }}°C</small>
                                @endif
                            </div>

                            {{-- Utilization --}}
                            <div class="col-sm-3 col-xs-6" style="margin-bottom: 6px;">
                                <div class="text-muted" style="font-size: 11px; text-transform: uppercase;">GPU Utilization</div>
                                @php
                                    $utilColor = '#5cb85c';
                                    if ($gpu['util_gpu'] >= 90) $utilColor = '#d9534f';
                                    elseif ($gpu['util_gpu'] >= 70) $utilColor = '#f0ad4e';
                                @endphp
                                <div style="font-size: 22px; font-weight: bold; color: {{ $utilColor }};">
                                    {{ $gpu['util_gpu'] !== null ? $gpu['util_gpu'] . '%' : '-' }}
                                </div>
                                @if($gpu['util_mem'] !== null)
                                    <small class="text-muted">Mem bus: {{ $gpu['util_mem'] }}%</small>
                                @endif
                            </div>

                            {{-- Memory --}}
                            <div class="col-sm-3 col-xs-6" style="margin-bottom: 6px;">
                                <div class="text-muted" style="font-size: 11px; text-transform: uppercase;">VRAM</div>
                                @if($gpu['mem_total'] > 0)
                                    @php
                                        $memPct = round(($gpu['mem_used'] / $gpu['mem_total']) * 100, 1);
                                        $memBarClass = 'progress-bar-success';
                                        if ($memPct >= 90) $memBarClass = 'progress-bar-danger';
                                        elseif ($memPct >= 70) $memBarClass = 'progress-bar-warning';
                                    @endphp
                                    <div style="font-size: 16px; font-weight: bold;">
                                        {{ number_format($gpu['mem_used']) }} <small>/ {{ number_format($gpu['mem_total']) }} MiB</small>
                                    </div>
                                    <div class="progress" style="margin-bottom: 0; height: 8px; margin-top: 4px;">
                                        <div class="progress-bar {{ $memBarClass }}" style="width: {{ $memPct }}%;"></div>
                                    </div>
                                @else
                                    <div style="font-size: 16px;">-</div>
                                @endif
                            </div>

                            {{-- Power --}}
                            <div class="col-sm-3 col-xs-6" style="margin-bottom: 6px;">
                                <div class="text-muted" style="font-size: 11px; text-transform: uppercase;">Power</div>
                                <div style="font-size: 22px; font-weight: bold;">
                                    @if($gpu['power_draw'] !== null)
                                        {{ round($gpu['power_draw']) }}<small>W</small>
                                    @else
                                        -
                                    @endif
                                </div>
                                @if($gpu['power_limit'] !== null)
                                    <small class="text-muted">Limit: {{ round($gpu['power_limit']) }}W</small>
                                @endif
                            </div>
                        </div>

                        {{-- Secondary metrics row --}}
                        <div class="row" style="margin-top: 4px;">
                            <div class="col-sm-3 col-xs-6">
                                <small class="text-muted">
                                    <i class="fa fa-tachometer"></i>
                                    GFX: {{ $gpu['clock_graphics'] ?? '-' }} MHz &nbsp;
                                    MEM: {{ $gpu['clock_memory'] ?? '-' }} MHz
                                </small>
                            </div>
                            <div class="col-sm-3 col-xs-6">
                                <small class="text-muted">
                                    <i class="fa fa-fan"></i>
                                    Fan: {{ $gpu['fan_speed'] !== null ? $gpu['fan_speed'] . '%' : '-' }}
                                </small>
                            </div>
                            <div class="col-sm-3 col-xs-6">
                                <small class="text-muted">
                                    <i class="fa fa-plug"></i>
                                    PCIe Gen{{ $gpu['pcie_gen'] ?? '?' }} x{{ $gpu['pcie_width'] ?? '?' }}
                                </small>
                            </div>
                            <div class="col-sm-3 col-xs-6">
                                <small class="text-muted">
                                    <i class="fa fa-cogs"></i>
                                    Processes: {{ $gpu['compute_procs'] ?? '0' }}
                                </small>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif
