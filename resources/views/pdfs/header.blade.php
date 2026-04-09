<style>
* {
    font-size: 20px;
}

.table {
    width: 100%;
    border-collapse: collapse;
    /* margin-bottom: 20px; */
    margin: 0mm 10mm 0mm 10mm;
}

.td {
    border: 1px solid black;
    padding: 4px;
    text-align: center;
    vertical-align: middle;
}

.logo-cell {
    width: 120px;
}

.right-cell {
    width: 120px;
    font-size: 9pt;
}

.small-text {
    font-size: 9pt;
}

.medium-text {
    font-size: 11pt;
    font-weight: bold;
}

.bold {
    font-weight: bold;
}

.logo {
    display: block;
    margin: 0 auto;
    width: 160px;
}

</style>

@php
    $logo = public_path('imgs/inter-icon.webp');
@endphp

<table class="table">
    <tr>
        <td class="td logo-cell" rowspan="3">
            <img src="@inlinedImage($logo)" class="logo">
            {{-- <img src="https://intercocina.com/assets/imgs/intercocina-logo.png" class="logo"> --}}
        </td>
        <td class="td bold small-text">
            SYSTEME DE MANAGEMENT DE LA QUALITE
        </td>
        <td class="td right-cell">
            ENR.ACH.03
        </td>
    </tr>
    <tr>
        <td class="td medium-text" rowspan="2">
            Comparatif des devis
        </td>
        <td class="td small-text">
            Version : 1.0
        </td>
    </tr>
    <tr>
        <td class="td small-text">
            Page <spans style="font-size: 10px!important">@pageNumber</span> | @totalPages
        </td>
    </tr>
</table>