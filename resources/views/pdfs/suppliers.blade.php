<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Liste des prestataires externes référencés</title>
<!-- Tailwind CDN -->
<script src="https://cdn.tailwindcss.com"></script>
<style>
    @page {
        margin: 50px 30px 80px 30px; /* top right bottom left */
    }

    .footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
    }
</style>
</head>

<body class="font-sans text-sm m-0">

<div class="content pb-24">

    <!-- Header Table -->
    <table class="w-full mb-5 border border-black border-collapse">
        <tr>
            <td rowspan="3" class="w-1/3 align-middle border border-black py-2">
                <img src="{{ public_path('imgs/intercocina-logo.png') }}" class="mx-auto" width="160">
            </td>
            <td class="w-1/3 font-bold align-middle border border-black text-center ">
                SYSTEME DE MANAGEMENT DE LA QUALITE
            </td>
            <td class="w-1/3 text-xs align-middle border border-black text-center py-2">
                <strong></strong> ENR.ACH.06
            </td>
        </tr>

        <tr>
            <td rowspan="2" class="font-bold uppercase align-middle border border-black text-center ">
                LISTE DES PRESTATAIRES EXTERNES RÉFÉRENCÉS
            </td>
            <td class="text-xs align-middle border border-black text-center py-2">
                <strong>Version :</strong> 01
            </td>
        </tr>

        <tr>
            <td class="text-xs align-middle border border-black text-center py-2">
                <strong>Page :</strong> 1 sur 1
            </td>
        </tr>
    </table>

    <!-- Update Date -->
    <p class="mb-2">
        <strong>Date de mise à jour :</strong> {{ now()->format('d/m/Y') }}
    </p>

    <!-- Suppliers Table -->
    <table class="w-full mb-5 border border-black border-collapse">
        <thead class="bg-[#1f497d] text-white">
            <tr>
                <th class="border border-black px-2 py-1">Nature d’achat</th>
                <th class="border border-black px-2 py-1">Nom des fournisseurs</th>
                <th class="border border-black px-2 py-1">N° Téléphone / Fax</th>
                <th class="border border-black px-2 py-1">Email</th>
                <th class="border border-black px-2 py-1">Adresse</th>
                <th class="border border-black px-2 py-1">Interlocuteur</th>
            </tr>
        </thead>
        <tbody>
            @foreach($suppliers as $supplier)
                <tr>
                    <td class="border border-black px-2 py-1">{{ $supplier->Nature_Achat }}</td>
                    <td class="border border-black px-2 py-1">{{ $supplier->CT_Intitule }}</td>
                    <td class="border border-black px-2 py-1">
                        {{ $supplier->CT_Telephone }}
                        @if($supplier->CT_Telecopie)
                            / {{ $supplier->CT_Telecopie }}
                        @endif
                    </td>
                    <td class="border border-black px-2 py-1">{{ $supplier->CT_EMail }}</td>
                    <td class="border border-black px-2 py-1">{{ $supplier->CT_Adresse }} - {{ $supplier->CT_Ville }} - {{ $supplier->CT_Pays }}</td>
                    <td class="border border-black px-2 py-1"></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="flex justify-end w-full">
        <table class="mb-4 w-1/2 table-fixed">
            <tr class="">
                <th class="w-[30%] border border-gray- px-4 py-1 text-left font-bold text-sm">
                    Nom & Prénom :
                </th>
                <th class="w-[30%] border border-gray- px-4 py-1 text-center text-sm font-normal">
                    Mme.
                </th>
            </tr>
            <tr class="">
                <th class="w-[30%] border border-gray- px-4 py-1 text-left font-bold text-sm">
                    Fonction:
                </th>
                <th class="w-[30%] border border-gray- px-4 py-1 text-center text-sm font-normal">
                    Responsable ACHATS
                </th>
            </tr>

            <tr class=" py-6">
                <th class="w-[30%] border border-gray- px-4 py-1 text-left font-bold text-sm">
                    Signature:
                </th>
                <th class="w-[30%] border border-gray- px-4 py-1 text-center text-sm font-normal">
                    
                </th>
            </tr>
        </table>
    </div>

</div>

<!-- Fixed Footer -->
{{-- <div class="footer text-center text-xs pt-5">
    
</div> --}}

</body>
</html>
