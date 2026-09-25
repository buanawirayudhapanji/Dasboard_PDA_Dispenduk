<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - Dashboard Kependudukan Jember' : 'Dashboard Kependudukan Jember' }}
</title>

<link rel="icon" href="{{ asset('images/lambang-kabupaten-jember.png') }}" type="image/png">
<link rel="apple-touch-icon" href="{{ asset('images/lambang-kabupaten-jember.png') }}">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
<script>
    // Dashboard Dispenduk secara sengaja hanya memakai tampilan terang.
    // Simpan preferensi ini sebelum Flux membaca pengaturan browser.
    window.localStorage.setItem('flux.appearance', 'light');
    document.documentElement.classList.remove('dark');
    document.documentElement.style.colorScheme = 'light';
</script>
@fluxAppearance
