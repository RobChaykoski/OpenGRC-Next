{{-- Self-hosted brand font: avoids a third-party font request and the visitor IP leak that comes with it. --}}
<style>
    @font-face {
        font-family: 'Bruno Ace SC';
        src: url('{{ asset('fonts/BrunoAceSC-Regular.ttf') }}') format('truetype');
        font-weight: 400;
        font-style: normal;
        font-display: swap;
    }
</style>
