{{-- Applied before first paint to avoid a flash of the wrong theme / text size. --}}
<script>
    (function () {
        var stored = localStorage.getItem('theme');
        var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (stored === 'dark' || (!stored && prefersDark)) {
            document.documentElement.classList.add('dark');
        }

        var fontSize = localStorage.getItem('fontSize');
        if (fontSize === 'large' || fontSize === 'xlarge') {
            document.documentElement.classList.add('font-size-' + fontSize);
        }
    })();
</script>
