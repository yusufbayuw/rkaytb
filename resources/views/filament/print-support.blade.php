<script>
document.addEventListener('livewire:init', () => {
    Livewire.on('legacy-print', ({html}) => {
        const frame = document.createElement('iframe');
        frame.title = 'Cetak RKA';
        frame.style.position = 'fixed';
        frame.style.width = '1px';
        frame.style.height = '1px';
        frame.style.opacity = '0';
        frame.onload = () => {
            frame.contentWindow.addEventListener('afterprint', () => frame.remove(), {once: true});
            frame.contentWindow.focus();
            frame.contentWindow.print();
        };
        frame.srcdoc = html;
        document.body.appendChild(frame);
    });
});
</script>
