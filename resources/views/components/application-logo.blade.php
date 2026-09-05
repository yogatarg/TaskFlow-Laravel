{{--
    Logo TaskFlow — dokumen dengan dua baris bercentang, menggambarkan task yang
    diajukan lalu disetujui.

    Dipakai di dua tempat sekaligus: navbar (layouts/navigation.blade.php) dan
    halaman login/register (layouts/guest.blade.php). Ukuran dan warnanya
    ditentukan pemanggil lewat kelas Tailwind, jadi jangan dipatok di sini.

    Memakai `stroke="currentColor"` supaya ikut mewarisi warna teks dari
    pemanggilnya, sama seperti komponen logo bawaan Breeze yang digantikannya.
--}}

<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.6"
     stroke-linecap="round" stroke-linejoin="round"
     xmlns="http://www.w3.org/2000/svg" {{ $attributes }}>
    <rect fill="none" x="9" y="5" width="30" height="38" rx="4"/>
    <path fill="none" d="M16 17l3.2 3.2L25 14.5"/>
    <path fill="none" d="M29 17.5h5"/>
    <path fill="none" d="M16 30l3.2 3.2L25 27.5"/>
    <path fill="none" d="M29 30.5h5"/>
</svg>
