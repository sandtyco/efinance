/* =================================================================
   /assets/js/custom-menu.js
   Script untuk mengaktifkan dropdown di sidebar
   ================================================================= */
$(function() {
    // Fungsi untuk toggle menu saat diklik
    $('.sidebar .nav a').on('click', function(e) {
        // Cek apakah link memiliki sub-menu (ul.nav-second-level)
        var $this = $(this);
        var $parent = $this.parent(); // <li>
        var $subMenu = $this.next('.nav-second-level'); // <ul>

        // Hanya proses jika link memiliki sub-menu
        if ($subMenu.length) {
            e.preventDefault(); // Mencegah navigasi ke '#'
            
            // Toggle sub-menu (geser ke atas/bawah)
            $subMenu.slideToggle(250);

            // Menutup sub-menu lain yang mungkin terbuka di level yang sama
            $parent.siblings().children('ul.nav-second-level').slideUp(250);
            
            // Opsional: Toggle kelas 'active' pada parent <li>
            $parent.toggleClass('active'); 
        }
    });

    // Menutup menu jika resolusi layar besar (desktop)
    $(window).on('resize', function() {
        if ($(window).width() > 768) {
            $('.nav-second-level').slideUp(0);
        }
    });
});