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
        @page { margin: 27mm 10mm 10mm 10mm; }
    </style>
</head>
<body class="font-['Segoe_UI',Arial,sans-serif] text-[9pt] text-black leading-tight">

{{-- ═══════════════════════════════════════════════════════ --}}
{{-- HEADER (Logo + Title + Reference)                      --}}
{{-- ═══════════════════════════════════════════════════════ --}}


{{-- ═══════════════════════════════════════════════════════ --}}
{{-- SECTION 1 : Informations générales                     --}}
{{-- ═══════════════════════════════════════════════════════ --}}
<table class="w-full border-collapse mb-4">
    <tr>
        <td colspan="4" class="bg-navy text-white font-bold text-[11pt] text-center p-1.5 border border-black">
            Informations générales
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black w-[200px]">Référence de la demande :</td>
        <td class="p-1.5 border border-black">{{ $comparison->reference }}</td>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black w-[140px]">Date du comparatif :</td>
        <td class="p-1.5 border border-black w-[140px]">{{ $comparison->comparison_date?->format('d/m/Y') }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Service Demandeur :</td>
        <td colspan="3" class="p-1.5 border border-black">{{ $comparison->department }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Objet de l'achat :</td>
        <td colspan="3" class="p-1.5 border border-black">{{ $comparison->purchase_object }}</td>
    </tr>
</table>

{{-- ═══════════════════════════════════════════════════════ --}}
{{-- SECTION 2 : Détail des offres reçues                   --}}
{{-- ═══════════════════════════════════════════════════════ --}}
@php
    $offers = $comparison->offers;
    $maxOffers = max($offers->count(), 3);
@endphp

<table class="w-full border-collapse mb-4">
    <tr>
        <td colspan="{{ $maxOffers + 1 }}" class="bg-navy text-white font-bold text-[11pt] text-center p-1.5 border border-black">
            Détail des offres reçues
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] text-center p-1.5 border border-black w-[220px]">Critères de comparaison</td>
        @foreach($offers as $offer)
            <td class="bg-gold font-bold text-[9pt] text-center p-1.5 border border-black">{{ $offer->provider_name ?: 'Prestataire ' . $loop->iteration }}</td>
        @endforeach
        @for($i = $offers->count(); $i < 3; $i++)
            <td class="bg-gold font-bold text-[9pt] text-center p-1.5 border border-black">Prestataire {{ $i + 1 }}</td>
        @endfor
    </tr>

    @php
        $rows = [
            ['Nom du prestataire',                      'provider_name'],
            ['Référence du devis / Date',               'quote_reference'],
            ['Délai de validité du devis',              'validity_period'],
            ['Désignation du produit / service',        'product_designation'],
            ['Quantité',                                'quantity'],
            ['Prix unitaire (MAD)',                     'unit_price'],
            ['Prix total (MAD)',                        'total_price'],
            ['Conditions de paiement',                  'payment_conditions'],
            ['Délai de livraison',                      'delivery_delay'],
            ['Garantie / SAV',                          'warranty'],
            ['Conformité technique / spécifications',   'technical_compliance'],
            ['Observations',                            'observations'],
        ];
    @endphp

    @foreach($rows as [$label, $field])
        <tr>
            <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">{{ $label }}</td>
            @foreach($offers as $offer)
                <td class="p-1.5 border border-black align-top {{ in_array($field, ['unit_price','total_price','quantity']) ? 'text-right' : '' }}">
                    @if(in_array($field, ['unit_price','total_price']))
                        {{ $offer->$field ? number_format($offer->$field, 2, ',', ' ') : '' }}
                    @elseif($field === 'quote_reference')
                        {{ $offer->quote_reference }}{{ $offer->quote_date ? ' / ' . $offer->quote_date->format('d/m/Y') : '' }}
                    @else
                        {{ $offer->$field }}
                    @endif
                </td>
            @endforeach
            @for($i = $offers->count(); $i < 3; $i++)
                <td class="p-1.5 border border-black min-h-[20px]"></td>
            @endfor
        </tr>
    @endforeach
</table>

{{-- ═══════════════════════════════════════════════════════ --}}
{{-- SECTION 3 : Évaluation et pondération                  --}}
{{-- ═══════════════════════════════════════════════════════ --}}
@php
    $evaluations = $comparison->evaluations;
    $maxEvals = max($evaluations->count(), 3);
    $criteria = [
        ['Prix et conditions commerciales', '30%', 'price_score'],
        ['Délai de livraison',              '25%', 'delivery_score'],
        ['Conformité technique',            '25%', 'technical_score'],
        ['Fiabilité / Réactivité',          '10%', 'reliability_score'],
        ['Conditions de paiement',          '10%', 'payment_score'],
    ];
@endphp

<table class="w-full border-collapse mb-4">
    <tr>
        <td colspan="{{ $maxEvals + 2 }}" class="bg-navy text-white font-bold text-[11pt] text-center p-1.5 border border-black">
            Évaluation et pondération
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] text-center p-1.5 border border-black w-[200px]">Critère d'évaluation</td>
        <td class="bg-gold font-bold text-[9pt] text-center p-1.5 border border-black w-[100px]">Pondération (%)</td>
        @foreach($evaluations as $eval)
            <td class="bg-gold font-bold text-[9pt] text-center p-1.5 border border-black">{{ $eval->provider_name }}</td>
        @endforeach
        @for($i = $evaluations->count(); $i < 3; $i++)
            <td class="bg-gold font-bold text-[9pt] text-center p-1.5 border border-black">Prestataire {{ $i + 1 }}</td>
        @endfor
    </tr>

    @foreach($criteria as [$label, $weight, $field])
        <tr>
            <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">{{ $label }}</td>
            <td class="text-center p-1.5 border border-black">{{ $weight }}</td>
            @foreach($evaluations as $eval)
                <td class="text-center p-1.5 border border-black">{{ $eval->$field }}/10</td>
            @endforeach
            @for($i = $evaluations->count(); $i < 3; $i++)
                <td class="p-1.5 border border-black min-h-[20px]"></td>
            @endfor
        </tr>
    @endforeach

    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Note finale pondérée</td>
        <td class="bg-gold font-bold text-center p-1.5 border border-black">100%</td>
        @foreach($evaluations as $eval)
            <td class="bg-gold font-bold text-center p-1.5 border border-black">{{ number_format($eval->weighted_total, 2, ',', ' ') }} / 10</td>
        @endforeach
        @for($i = $evaluations->count(); $i < 3; $i++)
            <td class="p-1.5 border border-black min-h-[20px]"></td>
        @endfor
    </tr>
</table>

<p class="text-[9pt] text-gray-600 -mt-2 mb-4">
    Calcul de la note finale : (Note × Pondération) / 100. Chaque critère est noté sur 10 (1 = insuffisant, 10 = excellent).
</p>

{{-- ═══════════════════════════════════════════════════════ --}}
{{-- SECTION 4 : Résultat du comparatif                     --}}
{{-- ═══════════════════════════════════════════════════════ --}}
<table class="w-full border-collapse mb-4">
    <tr>
        <td colspan="2" class="bg-navy text-white font-bold text-[11pt] text-center p-1.5 border border-black">
            Résultat du comparatif
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] text-center p-1.5 border border-black w-[220px]">Prestataire sélectionné</td>
        <td class="bg-gold font-bold text-[9pt] text-center p-1.5 border border-black">Motif du choix / justification technique et économique</td>
    </tr>
    <tr>
        <td class="p-1.5 border border-black font-bold">{{ $comparison->selected_provider ?? '' }}</td>
        <td class="p-1.5 border border-black">{{ $comparison->selection_justification ?? '' }}</td>
    </tr>
</table>

{{-- ═══════════════════════════════════════════════════════ --}}
{{-- SECTION 5 : Validation                                 --}}
{{-- ═══════════════════════════════════════════════════════ --}}
<table class="w-full border-collapse mb-4">
    <tr>
        <td class="border border-black p-1.5">&nbsp;</td>
        <td colspan="3" class="bg-navy text-white font-bold text-[11pt] text-center p-1.5 border border-black">
            Validation
        </td>
    </tr>
    <tr>
        <td class="border border-black p-1.5">&nbsp;</td>
        <td class="bg-gold font-bold text-[9pt] text-center p-1.5 border border-black">Nom &amp; Prénom</td>
        <td class="bg-gold font-bold text-[9pt] text-center p-1.5 border border-black">Date</td>
        <td class="bg-gold font-bold text-[9pt] text-center p-1.5 border border-black">Signature</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Responsable Achats</td>
        <td class="p-1.5 border border-black">{{ $comparison->purchasing_manager ?? '' }}</td>
        <td class="p-1.5 border border-black text-center">{{ $comparison->purchasing_manager_date?->format('d/m/Y') ?? '' }}</td>
        <td class="p-1.5 border border-black h-[50px]"></td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Directeur Général</td>
        <td class="p-1.5 border border-black">{{ $comparison->general_director ?? '' }}</td>
        <td class="p-1.5 border border-black text-center">{{ $comparison->general_director_date?->format('d/m/Y') ?? '' }}</td>
        <td class="p-1.5 border border-black h-[50px]"></td>
    </tr>
</table>

</body>
</html>