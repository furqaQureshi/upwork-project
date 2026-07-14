<button {{ $attributes->merge(['type' => 'button', 'class' => 'app-btn-muted']) }}>
    {{ $slot }}
</button>
