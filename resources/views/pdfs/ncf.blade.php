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
        <span class="text-[9pt] font-bold text-[#002060] tracking-wide">ENR.ACH.07</span>
      </td>
    </tr>
    <tr>
      <td rowspan="2" class="border border-black px-4 py-2.5 text-center align-middle bg-white">
        <span class="text-[11pt] font-bold text-[#002060]">Fiche NC Fournisseur</span>
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

{{-- Reference + Date line --}}
<table class="w-full mb-3">
  <tr>
    <td class="text-[9pt] font-bold">
      Fiche NCF N° : <span class="text-navy">{{ $ncf->reference }}</span>
    </td>
    <td class="text-right text-[9pt] font-bold">
      Date : {{ $ncf->date?->format('d/m/Y') }}
    </td>
  </tr>
</table>

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
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black w-[200px]">Fournisseur</td>
        <td class="p-1.5 border border-black">{{ $ncf->fournisseur }}</td>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black w-[180px]">Référence commande / contrat</td>
        <td class="p-1.5 border border-black w-[140px]">{{ $ncf->reference_commande }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Bon de livraison n° / Date</td>
        <td class="p-1.5 border border-black">{{ $ncf->bon_livraison }}</td>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Date de réception</td>
        <td class="p-1.5 border border-black">{{ $ncf->date_reception?->format('d/m/Y') }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Code article / Lot</td>
        <td colspan="3" class="p-1.5 border border-black">{{ $ncf->code_article }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Produit concerné</td>
        <td colspan="3" class="p-1.5 border border-black">{{ $ncf->produit_concerne }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Quantité réceptionnée</td>
        <td class="p-1.5 border border-black">{{ $ncf->quantite_receptionnee }}</td>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Quantité non conforme</td>
        <td class="p-1.5 border border-black">{{ $ncf->quantite_non_conforme }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Détectée par</td>
        <td colspan="3" class="p-1.5 border border-black">
            @php
                $detections = [
                    'approvisionnement' => 'Approvisionnement',
                    'controle_qualite'  => 'Contrôle qualité',
                    'production'        => 'Production',
                    'autre'             => 'Autre',
                ];
            @endphp
            @foreach($detections as $key => $label)
                <span class="mr-4">
                    @if($ncf->detectee_par === $key)
                        ☑
                    @else
                        ☐
                    @endif
                    {{ $label }}
                </span>
            @endforeach
            @if($ncf->detectee_par === 'autre' && $ncf->detectee_par_autre)
                : {{ $ncf->detectee_par_autre }}
            @endif
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Date de détection</td>
        <td colspan="3" class="p-1.5 border border-black">{{ $ncf->date_detection?->format('d/m/Y') }}</td>
    </tr>
</table>

{{-- ═══════════════════════════════════════════════════════ --}}
{{-- SECTION 2 : Description de la non-conformité           --}}
{{-- ═══════════════════════════════════════════════════════ --}}
@php
    $natures = [
        'quantitative'  => 'Quantitative',
        'qualitative'   => 'Qualitative',
        'documentaire'  => 'Documentaire',
        'autre'         => 'Autre',
    ];

    $ecarts = [
        'erreur_quantite'          => 'Erreur de quantité',
        'defaut_emballage'         => 'Défaut d\'emballage',
        'defaut_visuel'            => 'Défaut visuel',
        'dimensions_non_conformes' => 'Dimensions non conformes',
        'produit_endommage'        => 'Produit endommagé',
        'etiquetage_manquant'      => 'Étiquetage manquant',
        'autre'                    => 'Autre',
    ];

    $typesEcart = $ncf->types_ecart ?? [];
@endphp

<table class="w-full border-collapse mb-4">
    <tr>
        <td colspan="2" class="bg-navy text-white font-bold text-[11pt] text-center p-1.5 border border-black">
            Description de la non-conformité
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black w-[200px]">Nature de la non-conformité</td>
        <td class="p-1.5 border border-black">
            @foreach($natures as $key => $label)
                <span class="mr-4">
                    @if($ncf->nature_nc === $key) ☑ @else ☐ @endif
                    {{ $label }}
                </span>
            @endforeach
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black align-top">Type d'écart constaté</td>
        <td class="p-1.5 border border-black">
            @foreach($ecarts as $key => $label)
                <span class="mr-3">
                    @if(in_array($key, $typesEcart)) ☑ @else ☐ @endif
                    {{ $label }}
                </span>
            @endforeach
            @if(in_array('autre', $typesEcart) && $ncf->type_ecart_autre)
                : {{ $ncf->type_ecart_autre }}
            @endif
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black align-top">Description détaillée</td>
        <td class="p-1.5 border border-black min-h-[60px]">{{ $ncf->description_detaillee }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Preuves / photos jointes</td>
        <td class="p-1.5 border border-black">
            <span class="mr-6">@if($ncf->preuves_jointes) ☑ @else ☐ @endif Oui</span>
            <span>@if(!$ncf->preuves_jointes) ☑ @else ☐ @endif Non</span>
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Référence du lot / série</td>
        <td class="p-1.5 border border-black">{{ $ncf->reference_lot }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Gravité estimée</td>
        <td class="p-1.5 border border-black">
            @php
                $gravites = ['mineure' => 'Mineure', 'majeure' => 'Majeure', 'critique' => 'Critique'];
            @endphp
            @foreach($gravites as $key => $label)
                <span class="mr-6">
                    @if($ncf->gravite === $key) ☑ @else ☐ @endif
                    {{ $label }}
                </span>
            @endforeach
        </td>
    </tr>
</table>

{{-- ═══════════════════════════════════════════════════════ --}}
{{-- SECTION 3 : Analyse et traitement immédiat             --}}
{{-- ═══════════════════════════════════════════════════════ --}}
@php
    $decisions = [
        'accepte'             => 'Accepté',
        'accepte_sous_reserve' => 'Accepté sous réserve',
        'refuse_retour'       => 'Refusé / Retour fournisseur',
    ];
@endphp

<table class="w-full border-collapse mb-4">
    <tr>
        <td colspan="2" class="bg-navy text-white font-bold text-[11pt] text-center p-1.5 border border-black">
            Analyse et traitement immédiat
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black w-[200px]">Décision provisoire</td>
        <td class="p-1.5 border border-black">
            @foreach($decisions as $key => $label)
                <span class="mr-4">
                    @if($ncf->decision_provisoire === $key) ☑ @else ☐ @endif
                    {{ $label }}
                </span>
            @endforeach
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black align-top">Mesures immédiates prises</td>
        <td class="p-1.5 border border-black min-h-[60px]">{{ $ncf->mesures_immediates }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Responsable de l'action</td>
        <td class="p-1.5 border border-black">{{ $ncf->responsable_action }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Date d'exécution</td>
        <td class="p-1.5 border border-black">{{ $ncf->date_execution?->format('d/m/Y') }}</td>
    </tr>
</table>

{{-- ═══════════════════════════════════════════════════════ --}}
{{-- SECTION 4 : Analyse des causes                         --}}
{{-- ═══════════════════════════════════════════════════════ --}}
<table class="w-full border-collapse mb-4">
    <tr>
        <td colspan="2" class="bg-navy text-white font-bold text-[11pt] text-center p-1.5 border border-black">
            Analyse des causes
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black align-top w-[220px]">Analyse des causes probables</td>
        <td class="p-1.5 border border-black min-h-[50px]">{{ $ncf->causes_probables }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black align-top">Cause principale identifiée</td>
        <td class="p-1.5 border border-black min-h-[50px]">{{ $ncf->cause_principale }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black align-top">Proposition d'action corrective</td>
        <td class="p-1.5 border border-black min-h-[50px]">{{ $ncf->action_corrective }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Responsable de l'action</td>
        <td class="p-1.5 border border-black">{{ $ncf->responsable_action_corrective }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Date prévisionnelle de réalisation</td>
        <td class="p-1.5 border border-black">{{ $ncf->date_previsionnelle?->format('d/m/Y') }}</td>
    </tr>
</table>

{{-- ═══════════════════════════════════════════════════════ --}}
{{-- SECTION 5 : Suivi et Jugement d'efficacité             --}}
{{-- ═══════════════════════════════════════════════════════ --}}
<table class="w-full border-collapse mb-4">
    <tr>
        <td colspan="3" class="bg-navy text-white font-bold text-[11pt] text-center p-1.5 border border-black">
            Suivi et Jugement d'efficacité
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black w-[200px]">Responsable du suivi</td>
        <td colspan="2" class="p-1.5 border border-black">{{ $ncf->responsable_suivi }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Date de vérification</td>
        <td colspan="2" class="p-1.5 border border-black">{{ $ncf->date_verification?->format('d/m/Y') }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Action réalisée</td>
        <td class="p-1.5 border border-black">
            @if($ncf->action_realisee === true) ☑ @else ☐ @endif Oui
        </td>
        <td class="p-1.5 border border-black">
            @if($ncf->action_realisee === false) ☑ @else ☐ @endif Non
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Action Efficace</td>
        <td class="p-1.5 border border-black">
            @if($ncf->action_efficace === true) ☑ @else ☐ @endif Oui
        </td>
        <td class="p-1.5 border border-black">
            @if($ncf->action_efficace === false) ☑ @else ☐ @endif Non
            @if($ncf->action_efficace === false && $ncf->fnc_reference)
                &nbsp;&nbsp;&nbsp;&nbsp;N° FNC : {{ $ncf->fnc_reference }}
            @endif
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Date de clôture</td>
        <td colspan="2" class="p-1.5 border border-black">{{ $ncf->date_cloture?->format('d/m/Y') }}</td>
    </tr>
</table>

{{-- ═══════════════════════════════════════════════════════ --}}
{{-- SECTION 6 : Décision Finale                            --}}
{{-- ═══════════════════════════════════════════════════════ --}}
@php
    $decisionsFinales = [
        'accepte_apres_correction' => 'Accepté après correction',
        'refuse_definitivement'    => 'Refusé définitivement',
        'accepte_avec_derogation'  => 'Accepté avec dérogation',
    ];

    $sigAchats    = $ncf->signatures->where('entite', 'achats')->first();
    $sigDirection = $ncf->signatures->where('entite', 'direction')->first();
@endphp

<table class="w-full border-collapse mb-4">
    <tr>
        <td colspan="5" class="bg-navy text-white font-bold text-[11pt] text-center p-1.5 border border-black">
            Décision Finale
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black w-[200px]">Décision Direction / Achats</td>
        <td colspan="4" class="p-1.5 border border-black">
            @foreach($decisionsFinales as $key => $label)
                <span class="mr-4">
                    @if($ncf->decision_finale === $key) ☑ @else ☐ @endif
                    {{ $label }}
                </span>
            @endforeach
        </td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Entité</td>
        <td class="bg-gold font-bold text-[9pt] text-center p-1.5 border border-black" colspan="2">Achats</td>
        <td class="bg-gold font-bold text-[9pt] text-center p-1.5 border border-black" colspan="2">Direction</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Nom & Prénom</td>
        <td class="p-1.5 border border-black" colspan="2">{{ $sigAchats?->nom_prenom }}</td>
        <td class="p-1.5 border border-black" colspan="2">{{ $sigDirection?->nom_prenom }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Date</td>
        <td class="p-1.5 border border-black" colspan="2">{{ $sigAchats?->date?->format('d/m/Y') }}</td>
        <td class="p-1.5 border border-black" colspan="2">{{ $sigDirection?->date?->format('d/m/Y') }}</td>
    </tr>
    <tr>
        <td class="bg-gold font-bold text-[9pt] p-1.5 border border-black">Visa</td>
        <td class="p-1.5 border border-black h-[50px]" colspan="2">{{ $sigAchats?->visa }}</td>
        <td class="p-1.5 border border-black h-[50px]" colspan="2">{{ $sigDirection?->visa }}</td>
    </tr>
</table>

</body>
</html>