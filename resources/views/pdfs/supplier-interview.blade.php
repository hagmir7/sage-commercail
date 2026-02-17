<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche d'évaluation des prestataires externes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body class="bg-white p-8">
    <div class="max-w-4xl mx-auto">

    <table class="w-full mb-5 border border-black border-collapse table-fixed">
        <tr>
            <td rowspan="3" class="w-1/4 align-middle border border-black py-2">
                <img src="{{ public_path('imgs/intercocina-logo.png') }}" class="mx-auto" width="110">
            </td>

            <td class="w-1/2 font-bold align-middle border border-black text-center">
                SYSTEME DE MANAGEMENT DE LA QUALITE
            </td>

            <td class="w-1/4 text-xs align-middle border border-black text-center py-2">
                <strong></strong> ENR.ACH.06
            </td>
        </tr>

        <tr>
            <td rowspan="2" class="font-bold w-1/2 uppercase align-middle border border-black text-center">
                Fiche d’évaluation des prestataires externes
            </td>

            <td class="w-1/4 text-xs align-middle border border-black text-center py-2">
                <strong>Version :</strong> 01
            </td>
        </tr>

        <tr>
            <td class="w-1/4 text-xs align-middle border border-black text-center py-2">
                <strong>Page :</strong> 1 sur 1
            </td>
        </tr>
    </table>


        @php
            $globalTotal = 0;
        @endphp

        <!-- Evaluation Criteria Table -->
        <div class="mb-6">
            <table class="w-full border-collapse border border-gray-400">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="border border-gray-400 px-4 py-3 text-left font-bold text-base" rowspan="2">Critère d'évaluation</th>
                        <th class="border border-gray-400 px-4 py-3 text-center font-bold text-base" colspan="3">Niveau réel</th>
                    </tr>
                    <tr class="bg-gray-200">
                        <th class="border border-gray-400 px-4 text-center font-bold text-sm">
                            <div>1</div>
                            <div class="font-normal">Insatisfaisant</div>
                        </th>
                        <th class="border border-gray-400 px-4 text-center font-bold text-sm">
                            <div>2</div>
                            <div class="font-normal">Satisfaisant</div>
                        </th>
                        <th class="border border-gray-400 px-4 text-center font-bold text-sm">
                            <div>3</div>
                            <div class="font-normal">Très satisfaisant</div>
                        </th>
                    </tr>
                </thead>
                <tbody>

                    @foreach($criterias as $criteria)
                        @php
                            $note = (int) $criteria['note'];
                            $globalTotal += $note;
                        @endphp
                        <tr>
                            <td class="border border-gray-400 px-4 text-sm py-3 bg-gray-50 font-medium">
                                {{ $criteria['description'] }}
                            </td>
                            <td class="border border-gray-400 px-4 text-sm py-3 text-center bg-white">
                                {{ $note === 1 ? 'X' : '' }}
                            </td>
                            <td class="border border-gray-400 px-4 text-sm py-3 text-center bg-white">
                                {{ $note === 2 ? 'X' : '' }}
                            </td>
                            <td class="border border-gray-400 px-4 text-sm py-3 text-center bg-white">
                                {{ $note === 3 ? 'X' : '' }}
                            </td>
                        </tr>
                    @endforeach

                    <!-- Total row (ONLY changed part) -->
                    <tr class="bg-gray-100">
                        <td class="border border-gray-400 px-4 py-3 font-bold">Total</td>
                        <td colspan="3" class="border border-gray-400 px-4 py-3 text-center bg-white font-bold">
                            {{ $globalTotal }}
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>

        <!-- Conclusion Table -->
        <div class="mb-6">
            <table class="w-full border-collapse border border-gray-400">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="border border-gray-400 px-4 py-3 text-center font-bold">Conclusion</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="border border-gray-400 px-4 py-4 bg-white">
                            <div class="space-y-2 text-sm">
                                <p><strong>Note &gt; 14</strong> Résultat très satisfaisant : Prestataire qualifié de catégorie A</p>
                                <p><strong>Note = 14</strong> Prestation satisfaisante : Prestataire confirmé de catégorie A</p>
                                <p><strong>14 &lt; Note ≤ 11</strong> Prestation moyennement satisfaisante : Prestataire à surveiller de catégorie B</p>
                                <p><strong>Note &lt; 11</strong> Prestation insatisfaisante : Prestataire non qualifié de catégorie C</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Decision Table -->
        <div class="mb-6">
            <table class="w-full border-collapse border border-gray-400">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="border border-gray-400 px-4 py-3 text-center font-bold">Décision</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="border border-gray-400 px-4 py-4 bg-white">
                            <div class="space-y-2 text-sm">
                                <p><strong>Prestataire de catégorie A :</strong> Continuer la collaboration</p>
                                <p><strong>Prestataire de catégorie B :</strong> Engager un plan d'action</p>
                                <p><strong>Prestataire de catégorie C :</strong> à enlever de la liste des prestataires référencés</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</body>
</html>
