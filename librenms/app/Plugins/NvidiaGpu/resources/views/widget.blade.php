{{-- Dashboard Widget: NVIDIA GPU Fleet Overview --}}
<div class="nvidia-gpu-widget">
    @if(empty($gpus))
        <div class="text-center text-muted" style="padding: 20px;">
            <i class="fa fa-microchip fa-2x"></i>
            <p style="margin-top: 10px;">No NVIDIA GPUs detected.<br>
            <small>Enable the <strong>nvidia-smi</strong> application on devices with GPUs.</small></p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-condensed table-hover" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th>Host</th>
                        <th>GPU</th>
                        <th>Temp</th>
                        <th>Util</th>
                        <th>Memory</th>
                        <th>Power</th>
                        <th>Fan</th>
                        <th>State</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($gpus as $gpu)
                        @php
                            // Temperature colour coding
                            $tempClass = 'text-success';
                            if ($gpu['temp_gpu'] !== null) {
                                if ($gpu['temp_gpu'] >= 85) $tempClass = 'text-danger';
                                elseif ($gpu['temp_gpu'] >= 70) $tempClass = 'text-warning';
                            }

                            // Utilization colour
                            $utilClass = 'text-success';
                            if ($gpu['util_gpu'] !== null) {
                                if ($gpu['util_gpu'] >= 90) $utilClass = 'text-danger';
                                elseif ($gpu['util_gpu'] >= 70) $utilClass = 'text-warning';
                            }

                            // Memory percentage
                            $memPct = ($gpu['mem_total'] > 0)
                                ? round(($gpu['mem_used'] / $gpu['mem_total']) * 100, 1)
                                : 0;
                            $memClass = 'progress-bar-success';
                            if ($memPct >= 90) $memClass = 'progress-bar-danger';
                            elseif ($memPct >= 70) $memClass = 'progress-bar-warning';

                            // Power percentage
                            $powerPct = ($gpu['power_limit'] > 0)
                                ? round(($gpu['power_draw'] / $gpu['power_limit']) * 100, 1)
                                : 0;
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ url('/device/' . $gpu['device_id']) }}">
                                    {{ $gpu['hostname'] }}
                                </a>
                            </td>
                            <td title="{{ $gpu['gpu_name'] }}">
                                {{ Str::limit($gpu['gpu_name'], 22) }}
                            </td>
                            <td class="{{ $tempClass }}" style="font-weight: bold;">
                                {{ $gpu['temp_gpu'] !== null ? $gpu['temp_gpu'] . '°C' : '-' }}
                            </td>
                            <td class="{{ $utilClass }}" style="font-weight: bold;">
                                {{ $gpu['util_gpu'] !== null ? $gpu['util_gpu'] . '%' : '-' }}
                            </td>
                            <td style="min-width: 120px;">
                                @if($gpu['mem_total'] > 0)
                                    <div class="progress" style="margin-bottom: 0; height: 16px;">
                                        <div class="progress-bar {{ $memClass }}"
                                             style="width: {{ $memPct }}%;"
                                             title="{{ $gpu['mem_used'] }} / {{ $gpu['mem_total'] }} MiB">
                                            {{ $memPct }}%
                                        </div>
                                    </div>
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if($gpu['power_draw'] !== null)
                                    {{ round($gpu['power_draw']) }}W
                                    @if($gpu['power_limit'])
                                        <small class="text-muted">/ {{ round($gpu['power_limit']) }}W</small>
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                {{ $gpu['fan_speed'] !== null ? $gpu['fan_speed'] . '%' : '-' }}
                            </td>
                            <td>
                                <span class="label label-default">{{ $gpu['pstate'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="text-muted text-right" style="padding: 4px 8px; font-size: 11px;">
            {{ count($gpus) }} GPU(s) across {{ collect($gpus)->pluck('device_id')->unique()->count() }} device(s)
        </div>
    @endif
</div>
