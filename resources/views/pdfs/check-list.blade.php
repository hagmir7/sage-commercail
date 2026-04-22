<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'navy': '#002060',
                        'gold': '#FABF8F',
                    }
                }
            }
        }
    </script>
    <style>
        @page { margin: 5mm 5mm 10mm 5mm; }
    </style>
</head>
<body class="font-['Segoe_UI',Arial,sans-serif] text-[9pt] text-black leading-tight">

{{-- ═══════════════════════════════════════════════════════ --}}
{{-- HEADER (Logo + Title + Reference)                      --}}
{{-- ═══════════════════════════════════════════════════════ --}}
<table class="w-full border-collapse border border-black mb-3">
  <tbody>
    <tr>
      <td rowspan="3" class="border border-black p-2 text-center align-middle w-36">
        <img src="{{ public_path('imgs/inter-icon.webp') }}"
             class="mx-auto w-28 block">
      </td>
      <td class="border border-black px-4 py-1.5 text-center">
        <span class="text-black text-[9pt] font-bold tracking-wide uppercase">
          Système de Management de la Qualité
        </span>
      </td>
      <td class="border border-black px-3 py-1.5 text-center align-middle w-32">
        <span class="text-[9pt] font-bold text-[#002060] tracking-wide">ENR.EXL.01</span>
      </td>
    </tr>
    <tr>
      <td rowspan="2" class="border border-black px-4 py-2.5 text-center align-middle bg-white">
        <span class="text-[11pt] font-bold text-[#002060]">Check-list expédition</span>
      </td>
      <td class="border border-black px-3 py-1.5 text-center">
        <span class="text-[9pt] text-gray-600 font-medium">Version : 1.0</span>
      </td>
    </tr>
    <tr>
      <td class="border border-black px-3 py-1.5 text-center">
        <span class="text-[9pt] text-gray-600 font-medium">
          Page <span class="pageNumber"></span> | <span class="totalPages"></span>
        </span>
      </td>
    </tr>
  </tbody>
</table>

{{-- ═══════════════════════════════════════════════════════ --}}
{{-- SECTION 1 : Informations Générales                     --}}
{{-- ═══════════════════════════════════════════════════════ --}}
@php
    $expedMap = [
        1  => 'EX-WORK',
        2  => 'LA VOIE EXPRESS',
        3  => 'SDTM',
        4  => 'LODIVE',
        5  => 'MTR',
        6  => 'CARRE',
        7  => 'MAROC EXPRESS',
        8  => 'GLOG MAROC',
        9  => 'AL JAZZERA',
        10 => 'C YAHYA',
        11 => 'C YASSIN',
        12 => 'GHAZALA',
        13 => 'GISNAD',
    ];

    $expedCode     = $shipping->document->expedition ?? null;
    $expedLabel    = $expedMap[$expedCode] ?? 'N/A';
    $isExWork      = $expedCode === 1;
    $isIntercocina = $expedCode === null; // no carrier = handled internally
    $isExternal    = !$isExWork && !$isIntercocina && $expedCode !== null;
@endphp

<table class="w-full border-collapse mb-4">
    <tr>
        <td colspan="4" class="bg-navy text-white font-bold text-[11pt] text-center p-1.5 border border-black">
            Informations Générales
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black w-[200px]">Référence de la commande</td>
        <td class="p-1.5 border border-black">{{ $shipping->document->piece ?? '' }}</td>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black w-[200px]">Référence de la check-list</td>
        <td class="p-1.5 border border-black w-[140px]">{{ $shipping->code }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Adresse de livraison</td>
        <td class="p-1.5 border border-black">{{ ($shipping->document->docentete->compt->CT_Adresse ?? '') . ' ' . ($shipping->document->docentete->compt->CT_Ville ?? '') }}</td>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Date de chargement</td>
        <td class="p-1.5 border border-black">{{ $shipping->shipping_date }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Mode d'expédition</td>
        <td colspan="3" class="p-1.5 border border-black">
            <span class="mr-5">
                @if($isIntercocina) ☑ @else ☐ @endif INTERCOCINA
            </span>
            <span class="mr-5">
                @if($isExternal) ☑ @else ☐ @endif Transporteur externe
            </span>
            <span>
                @if($isExWork) ☑ @else ☐ @endif EX-WORK
            </span>
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Transporteur</td>
        <td colspan="3" class="p-1.5 border border-black">{{ $expedLabel }}</td>
    </tr>
</table>

{{-- ═══════════════════════════════════════════════════════ --}}
{{-- SECTION 2 : Éléments à vérifier                        --}}
{{-- ═══════════════════════════════════════════════════════ --}}
<table class="w-full border-collapse mb-4">
    <tr>
        <td colspan="3" class="bg-navy text-white font-bold text-[11pt] text-center p-1.5 border border-black">
            Éléments à vérifier
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black w-[50%]">Point de contrôle</td>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black text-center w-[25%]">État</td>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black text-center w-[25%]">Observation</td>
    </tr>

    @forelse($shipping->criteria ?? [] as $criteriaValue)
    <tr>
        <td class="p-1.5 border border-black text-[9pt]">
            {{ $criteriaValue->shippingCriteria->name ?? '' }}
        </td>
        <td class="p-1.5 border border-black text-center">
            @foreach(['Oui', 'Non', 'N.A'] as $option)
                <span class="mr-3">
                    @if($criteriaValue->status === $option) ☑ @else ☐ @endif
                    {{ $option }}
                </span>
            @endforeach
        </td>
        <td class="p-1.5 border border-black text-[9pt]">
            {{ $criteriaValue->note ?? '' }}
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="3" class="p-1.5 border border-black text-center text-gray-400 italic text-[9pt]">
            Aucun élément à vérifier.
        </td>
    </tr>
    @endforelse

    {{-- Oui Pour Tout summary row --}}
    @php
        $allOui = $shipping->criteria->isNotEmpty() &&
                  $shipping->criteria->every(fn($c) => $c->status === 'Oui');
    @endphp
    <tr>
        <td colspan="2" class="bg-gold font-bold text-[9pt] p-1.5 border border-black">
            Oui Pour Tout
        </td>
        <td class="p-1.5 border border-black text-center">
            <span class="mr-4">@if($allOui) ☑ @else ☐ @endif Oui</span>
            <span>@if(!$allOui) ☑ @else ☐ @endif Non</span>
        </td>
    </tr>
</table>

{{-- ═══════════════════════════════════════════════════════ --}}
{{-- SECTION 3 : Validation                                 --}}
{{-- ═══════════════════════════════════════════════════════ --}}
<table class="w-full border-collapse mb-4">
    <tr>
        <td colspan="2" class="bg-navy text-white font-bold text-[11pt] text-center p-1.5 border border-black">
            Validation
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black w-[200px]">Nom &amp; Prénom</td>
        <td class="p-1.5 border border-black">{{ $shipping->user->name ?? '' }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Fonction</td>
        <td class="p-1.5 border border-black">{{ $shipping->user->fonction ?? '' }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Date</td>
        <td class="p-1.5 border border-black">{{ $shipping->validation_date }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Visa</td>
        <td class="p-1.5 border border-black h-[60px]"></td>
    </tr>
</table>


</body>
</html>