@php
    /** @var \App\Models\Tmrisk $record */
    $record = $getRecord();
    $tracker = \App\Support\RiskStatusTracker::for($record);
@endphp

<div
    x-data="{ open: false, active: {{ (int) ($tracker['current_index'] ?? 0) }} }"
    x-on:mousedown.stop.prevent
    x-on:mouseup.stop.prevent
    x-on:click.stop.prevent
    class="w-full"
>
    <style>
        .risk-status-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 24px;
        }

        .risk-status-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .72);
        }

        .risk-status-dialog {
            position: relative;
            margin: 0 auto;
            width: 100%;
            max-width: 1280px;
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgb(24, 24, 27);
            box-shadow: 0 24px 80px rgba(0, 0, 0, .45);
            overflow: hidden;
        }

        .risk-status-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .risk-status-shell {
            padding: 24px;
            overflow-x: hidden;
        }

        .risk-status-grid {
            display: grid;
            grid-template-columns: 360px minmax(0, 1fr);
            gap: 24px;
            margin-top: 24px;
        }

        @media (max-width: 1280px) {
            .risk-status-grid {
                grid-template-columns: 1fr;
            }
        }

        .risk-status-list,
        .risk-status-detail {
            max-height: 65vh;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 4px;
        }

        .risk-status-step {
            width: 100%;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 16px;
            padding: 14px;
            text-align: left;
            background: rgba(255, 255, 255, 0.02);
            transition: .15s ease;
        }

        .risk-status-step:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .risk-status-step.is-current {
            border-color: rgba(245, 158, 11, 0.55);
            background: rgba(245, 158, 11, 0.10);
        }

        .risk-status-step.is-disabled {
            opacity: .65;
            cursor: default;
        }

        .risk-status-step-dot {
            width: 38px;
            height: 38px;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            flex: 0 0 auto;
        }

        .risk-status-step-dot.current {
            background: rgb(245, 158, 11);
            color: #fff;
        }

        .risk-status-step-dot.passed {
            background: rgb(16, 185, 129);
            color: #fff;
        }

        .risk-status-step-dot.pending {
            background: rgba(255, 255, 255, 0.10);
            color: rgba(255, 255, 255, 0.85);
        }

        .risk-status-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 9999px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 600;
        }

        .risk-status-badge.current {
            background: rgba(245, 158, 11, 0.18);
            color: rgb(253, 230, 138);
        }

        .risk-status-badge.done {
            background: rgba(16, 185, 129, 0.18);
            color: rgb(167, 243, 208);
        }

        .risk-status-badge.pending {
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.75);
        }

        .risk-status-card {
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 16px;
            padding: 18px;
            background: rgba(255, 255, 255, 0.02);
        }

        .risk-status-info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        @media (max-width: 768px) {
            .risk-status-info-grid {
                grid-template-columns: 1fr;
            }
        }

        .risk-status-info-box {
            border-radius: 14px;
            padding: 14px;
            background: rgba(255, 255, 255, 0.04);
        }

        .risk-status-change {
            border-radius: 14px;
            padding: 14px;
            background: rgba(255, 255, 255, 0.04);
        }

        .risk-status-change-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .risk-status-change-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <x-filament::button
        type="button"
        size="sm"
        color="gray"
        icon="heroicon-o-eye"
        x-on:mousedown.stop.prevent
        x-on:mouseup.stop.prevent
        x-on:click.stop.prevent="open = true; active = {{ (int) ($tracker['current_index'] ?? 0) }}"
    >
        Lihat Status
    </x-filament::button>

    <template x-teleport="body">
        <div
            x-cloak
            x-show="open"
            x-transition.opacity.duration.150ms
            class="risk-status-overlay"
            x-on:keydown.escape.window="open = false"
            x-on:mousedown.stop
            x-on:mouseup.stop
            x-on:click.stop
        >
            <div
                class="risk-status-backdrop"
                x-on:click="open = false"
            ></div>

            <div
                class="risk-status-dialog"
                x-on:mousedown.stop
                x-on:mouseup.stop
                x-on:click.stop
            >
                <div class="risk-status-header">
                    <div>
                        <div style="font-size: 28px; font-weight: 700; line-height: 1.2;">
                            Tracking Status Risk Register
                        </div>
                        <div style="margin-top: 8px; font-size: 15px; opacity: .78;">
                            Risk No: {{ $tracker['record_no'] }}
                        </div>
                    </div>

                    <button
                        type="button"
                        x-on:mousedown.stop.prevent
                        x-on:mouseup.stop.prevent
                        x-on:click.stop.prevent="open = false"
                        style="display:inline-flex; align-items:center; justify-content:center; width:40px; height:40px; border-radius:12px; border:1px solid rgba(255,255,255,.10); background:rgba(255,255,255,.02); font-size:24px; line-height:1;"
                    >
                        ×
                    </button>
                </div>

                <div class="risk-status-shell">
                    <div class="risk-status-card" style="background: rgba(245, 158, 11, 0.10); border-color: rgba(245, 158, 11, 0.25);">
                        <div style="font-size: 12px; text-transform: uppercase; letter-spacing: .08em; opacity: .75;">
                            Tahap: {{ $tracker['current_phase'] }}
                        </div>
                        <div style="margin-top: 6px; font-size: 18px; font-weight: 700;">
                            {{ $tracker['current_status_label'] }}
                        </div>
                        <div style="margin-top: 8px; font-size: 14px; line-height: 1.6;">
                            {{ $tracker['current_message'] }}
                        </div>
                    </div>

                    <div class="risk-status-grid">
                        <div class="risk-status-list">
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                @foreach ($tracker['items'] as $index => $item)
                                    @php
                                        $isPassed = (bool) ($item['passed'] ?? false);
                                        $isCurrent = (bool) ($item['current'] ?? false);
                                        $isClickable = (bool) ($item['clickable'] ?? false);
                                    @endphp

                                    <button
                                        type="button"
                                        @if ($isClickable)
                                            x-on:click.stop.prevent="active = {{ $index }}"
                                        @else
                                            disabled
                                        @endif
                                        class="risk-status-step {{ $isCurrent ? 'is-current' : '' }} {{ $isClickable ? '' : 'is-disabled' }}"
                                    >
                                        <span class="risk-status-step-dot {{ $isCurrent ? 'current' : ($isPassed ? 'passed' : 'pending') }}">
                                            @if ($isPassed)
                                                ✓
                                            @else
                                                {{ str_pad((string) $item['code'], 2, '0', STR_PAD_LEFT) }}
                                            @endif
                                        </span>

                                        <span style="display: block; min-width: 0; flex: 1 1 auto;">
                                            <span style="display:block; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; opacity: .68;">
                                                Tahap: {{ $item['phase'] }}
                                            </span>

                                            <span style="display:block; margin-top: 4px; font-size: 14px; font-weight: 700; line-height: 1.45; word-break: break-word;">
                                                {{ $item['label'] }}
                                            </span>

                                            <span style="display:block; margin-top: 6px; font-size: 12px; opacity: .75; word-break: break-word;">
                                                @if (! empty($item['at_label']))
                                                    {{ $item['at_label'] }}
                                                @elseif ($isCurrent)
                                                    Status saat ini
                                                @else
                                                    Belum tercapai
                                                @endif
                                            </span>

                                            <span style="display:block; margin-top: 10px;">
                                                @if ($isCurrent)
                                                    <span class="risk-status-badge current">Status saat ini</span>
                                                @elseif ($isPassed)
                                                    <span class="risk-status-badge done">Selesai</span>
                                                @else
                                                    <span class="risk-status-badge pending">Belum tercapai</span>
                                                @endif
                                            </span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="risk-status-modal-detail">
                            @foreach ($tracker['items'] as $index => $item)
                                <section
                                    x-cloak
                                    x-show="active === {{ $index }}"
                                    style="display: none;"
                                >
                                    <div class="risk-status-card">
                                        <div style="font-size: 12px; text-transform: uppercase; letter-spacing: .08em; opacity: .70;">
                                            Tahap: {{ $item['phase'] }}
                                        </div>

                                        <div style="margin-top: 6px; display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
                                            <h4 style="margin: 0; font-size: 20px; font-weight: 700; line-height: 1.4; word-break: break-word;">
                                                {{ $item['label'] }}
                                            </h4>

                                            @if ($item['current'])
                                                <span class="risk-status-badge current">Status saat ini</span>
                                            @elseif ($item['passed'])
                                                <span class="risk-status-badge done">Selesai</span>
                                            @else
                                                <span class="risk-status-badge pending">Belum tercapai</span>
                                            @endif
                                        </div>

                                        <div class="risk-status-info-grid">
                                            <div class="risk-status-info-box">
                                                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: .08em; opacity: .70;">
                                                    Waktu
                                                </div>
                                                <div style="margin-top: 6px; font-size: 14px; font-weight: 600; word-break: break-word;">
                                                    {{ $item['at_label'] ?: '-' }}
                                                </div>
                                            </div>

                                            <div class="risk-status-info-box">
                                                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: .08em; opacity: .70;">
                                                    Pelaku / Approver
                                                </div>
                                                <div style="margin-top: 6px; font-size: 14px; font-weight: 600; word-break: break-word;">
                                                    {{ $item['actor'] ?: '-' }}
                                                </div>

                                                @if (! empty($item['role']))
                                                    <div style="margin-top: 4px; font-size: 12px; opacity: .75; word-break: break-word;">
                                                        {{ $item['role'] }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        @if (! empty($item['hint']))
                                            <div class="risk-status-card" style="margin-top: 16px; background: rgba(245, 158, 11, 0.10); border-color: rgba(245, 158, 11, 0.20);">
                                                {{ $item['hint'] }}
                                            </div>
                                        @endif
                                    </div>

                                    <div style="margin-top: 20px;">
                                        <h5 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 700;">
                                            Perubahan pada tahap ini
                                        </h5>

                                        @if (! empty($item['history']))
                                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                                @foreach ($item['history'] as $entry)
                                                    <div class="risk-status-card">
                                                        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;">
                                                            <div>
                                                                <div style="font-size: 15px; font-weight: 700;">
                                                                    {{ $entry['title'] ?? 'Perubahan data' }}
                                                                </div>

                                                                <div style="margin-top: 4px; font-size: 12px; opacity: .75; word-break: break-word;">
                                                                    {{ $entry['at_label'] ?: '-' }}
                                                                    @if (! empty($entry['actor']))
                                                                        • {{ $entry['actor'] }}
                                                                    @endif
                                                                </div>
                                                            </div>

                                                            <span class="risk-status-badge pending">Perubahan</span>
                                                        </div>

                                                        <div style="margin-top: 14px; display: flex; flex-direction: column; gap: 10px;">
                                                            @foreach ($entry['changes'] as $change)
                                                                <div class="risk-status-change">
                                                                    <div style="font-size: 14px; font-weight: 700;">
                                                                        {{ $change['field'] }}
                                                                    </div>

                                                                    <div class="risk-status-change-grid">
                                                                        <div>
                                                                            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: .08em; opacity: .70;">
                                                                                Sebelum
                                                                            </div>
                                                                            <div style="margin-top: 4px; font-size: 14px; word-break: break-word;">
                                                                                {{ $change['old'] }}
                                                                            </div>
                                                                        </div>

                                                                        <div>
                                                                            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: .08em; opacity: .70;">
                                                                                Sesudah
                                                                            </div>
                                                                            <div style="margin-top: 4px; font-size: 14px; word-break: break-word;">
                                                                                {{ $change['new'] }}
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="risk-status-card" style="opacity: .82;">
                                                Tidak ada perubahan data pada status ini.
                                            </div>
                                        @endif
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>