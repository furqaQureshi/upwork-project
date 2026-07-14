@props([
    'name',
    'variant' => 'outline',
])

@switch($name)
    @case('map-pin')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21c4.5-4.2 6.75-7.8 6.75-10.6a6.75 6.75 0 1 0-13.5 0C5.25 13.2 7.5 16.8 12 21Z" />
            <circle cx="12" cy="10.25" r="2.25" />
        </svg>
        @break

    @case('chevron-down')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
        </svg>
        @break

    @case('heart')
        @if ($variant === 'solid')
            <svg {{ $attributes }} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="m11.645 20.91-.007-.003-.022-.012a10.877 10.877 0 0 1-.383-.218 25.18 25.18 0 0 1-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.5 3c1.942 0 3.523.97 4.43 2.286C12.977 3.97 14.558 3 16.5 3 19.286 3 21.75 5.322 21.75 8.25c0 3.924-2.438 7.11-4.739 9.257a25.175 25.175 0 0 1-4.244 3.17 10.878 10.878 0 0 1-.383.219l-.022.012-.007.003-.003.001a.75.75 0 0 1-.704 0l-.003-.001Z" />
            </svg>
        @else
            <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 8.25c0-2.485-2.239-4.5-5-4.5-1.873 0-3.505.927-4.5 2.296-.995-1.37-2.627-2.296-4.5-2.296-2.761 0-5 2.015-5 4.5 0 7.22 9.5 12 9.5 12S21 15.47 21 8.25Z" />
            </svg>
        @endif
        @break

    @case('bell')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.802 23.802 0 0 0 5.454-1.31A8.968 8.968 0 0 1 18 9.75V9a6 6 0 1 0-12 0v.75a8.968 8.968 0 0 1-2.311 6.022 23.802 23.802 0 0 0 5.454 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>
        @break

    @case('magnifying-glass')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <circle cx="11" cy="11" r="7" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m20 20-3.5-3.5" />
        </svg>
        @break

    @case('microphone')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <rect x="9" y="3.75" width="6" height="10.5" rx="3" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 10.5v.75a6 6 0 1 0 12 0v-.75" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 17.25v3M8.25 20.25h7.5" />
        </svg>
        @break

    @case('plus')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
        </svg>
        @break

    @case('squares-2x2')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H5.25A1.5 1.5 0 0 0 3.75 5.25V9a1.5 1.5 0 0 0 1.5 1.5H9A1.5 1.5 0 0 0 10.5 9V5.25A1.5 1.5 0 0 0 9 3.75Zm9.75 0H15A1.5 1.5 0 0 0 13.5 5.25V9a1.5 1.5 0 0 0 1.5 1.5h3.75A1.5 1.5 0 0 0 20.25 9V5.25a1.5 1.5 0 0 0-1.5-1.5ZM9 13.5H5.25A1.5 1.5 0 0 0 3.75 15V18.75a1.5 1.5 0 0 0 1.5 1.5H9a1.5 1.5 0 0 0 1.5-1.5V15A1.5 1.5 0 0 0 9 13.5Zm9.75 0H15A1.5 1.5 0 0 0 13.5 15V18.75a1.5 1.5 0 0 0 1.5 1.5h3.75a1.5 1.5 0 0 0 1.5-1.5V15a1.5 1.5 0 0 0-1.5-1.5Z" />
        </svg>
        @break

    @case('bars-3')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
        @break

    @case('photo')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75V8.25A2.25 2.25 0 0 1 4.5 6h15a2.25 2.25 0 0 1 2.25 2.25v7.5A2.25 2.25 0 0 1 19.5 18h-15a2.25 2.25 0 0 1-2.25-2.25Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m3 16.5 5.25-5.25a1.5 1.5 0 0 1 2.122 0L15 15.878l1.628-1.628a1.5 1.5 0 0 1 2.122 0L21 16.5" />
            <circle cx="8.25" cy="9" r="1.125" />
        </svg>
        @break

    @case('chat-bubble-left-right')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75h6.75m-6.75 3h4.5M21 12c0 4.97-4.03 9-9 9a8.96 8.96 0 0 1-4.565-1.238L3 21l1.238-4.435A8.96 8.96 0 0 1 3 12c0-4.97 4.03-9 9-9s9 4.03 9 9Z" />
        </svg>
        @break

    @case('phone')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25A2.25 2.25 0 0 0 21.75 19.5v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106a1.125 1.125 0 0 0-1.173.417l-.97 1.293a1.125 1.125 0 0 1-1.21.396 12.035 12.035 0 0 1-7.159-7.159 1.125 1.125 0 0 1 .396-1.21l1.293-.97c.369-.277.536-.754.417-1.173L6.963 3.102A1.125 1.125 0 0 0 5.872 2.25H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
        </svg>
        @break

    @case('pencil-square')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a2.126 2.126 0 1 1 3.006 3.006L7.5 18.862 3 20.25l1.388-4.5L16.862 3.487Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25V18A2.25 2.25 0 0 1 17.25 20.25H6A2.25 2.25 0 0 1 3.75 18V6.75A2.25 2.25 0 0 1 6 4.5h3.75" />
        </svg>
        @break

    @case('eye')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.644C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.574 3.01 9.964 7.178.07.21.07.434 0 .644C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.574-3.01-9.964-7.178Z" />
            <circle cx="12" cy="12" r="3" />
        </svg>
        @break

    @case('home')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955a1.125 1.125 0 0 1 1.592 0L21.75 12M4.5 9.75v9A2.25 2.25 0 0 0 6.75 21h10.5a2.25 2.25 0 0 0 2.25-2.25v-9" />
        </svg>
        @break

    @case('clipboard-document-list')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5.25h6m-6 3h6m-7.5 12h9A2.25 2.25 0 0 0 18.75 18V5.625A2.625 2.625 0 0 0 16.125 3h-8.25A2.625 2.625 0 0 0 5.25 5.625V18A2.25 2.25 0 0 0 7.5 20.25Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25h6m-6 3h4.5" />
        </svg>
        @break

    @case('user-circle')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.964 0A9 9 0 1 0 6.018 18.725m11.964 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
        </svg>
        @break

    @case('check-badge')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m9 12.75 2.25 2.25L15 9.75" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 4.125 12 3l2.187 1.125 2.484-.282 1.469 2.023 2.317.95-.282 2.484L21 12l-1.125 2.187.282 2.484-2.023 1.469-.95 2.317-2.484-.282L12 21l-2.187-1.125-2.484.282-1.469-2.023-2.317-.95.282-2.484L3 12l1.125-2.187-.282-2.484 2.023-1.469.95-2.317 2.484.282Z" />
        </svg>
        @break

    @case('tag')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m15 5.25 3 3m-7.5-.75h-3A2.25 2.25 0 0 0 5.25 9.75v3a2.25 2.25 0 0 0 .659 1.591l5.25 5.25a2.25 2.25 0 0 0 3.182 0l5.25-5.25a2.25 2.25 0 0 0 0-3.182l-5.25-5.25A2.25 2.25 0 0 0 12.75 5.25h-2.25Z" />
            <circle cx="9" cy="9" r=".75" fill="currentColor" stroke="none" />
        </svg>
        @break

    @case('lock-closed')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V7.875a4.5 4.5 0 1 0-9 0V10.5m-.75 0h10.5A2.25 2.25 0 0 1 19.5 12.75v6A2.25 2.25 0 0 1 17.25 21h-10.5A2.25 2.25 0 0 1 4.5 18.75v-6A2.25 2.25 0 0 1 6.75 10.5Z" />
        </svg>
        @break
@endswitch