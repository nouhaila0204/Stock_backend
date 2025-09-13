<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bon de sortie</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: white;
            padding: 20px;
            color: #000;
        }
        h1 {
            color: black;
            text-align: center;
            margin-bottom: 10px;
        }
        h2 {
            color: black;
            margin-top: 20px;
            margin-bottom: 10px;
            font-size: 1.2em;
            font-weight: bold;
        }
        .header, .footer {
            text-align: center;
            font-weight: bold;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-section p {
            margin: 5px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 20px;
        }
        th {
            background-color: black;
            color: white;
            padding: 10px;
            border: 1px solid black;
        }
        td {
            border: 1px solid black;
            padding: 8px;
            text-align: center;
        }
        .totals-section {
            margin-top: 20px;
            text-align: right;
        }
        .totals-section p {
            margin: 5px 0;
            font-weight: bold;
        }
        .signature {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            font-weight: bold;
        }
        .signature div {
            width: 30%;
            text-align: center;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <p>ENTREPRISE Al OMRANE</p>
        <h1>BON DE SORTIE </h1>
    </div>

    <div class="info-section">
        <h2>Informations générales</h2>
        <p><strong>Date :</strong> {{ \Carbon\Carbon::parse($sortie->date)->format('d/m/Y') ?? 'N/A' }}</p>
        <p><strong>Destination :</strong> {{ $sortie->destination ?? 'N/A' }}</p>
        <p><strong>Employé :</strong> {{ $sortie->employe->nom ?? 'N/A' }}</p>
        <p><strong>Commentaire :</strong> {{ $sortie->commentaire ?? 'N/A' }}</p>
    </div>
    
    <br> <br>
    <h2>Produits sortis</h2>
    <table>
        <thead>
            <tr>
                <th>Référence</th>
                <th>Nom du Produit</th>
                <th>Quantité</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sortie->produits as $produit)
                <tr>
                    <td>{{ $produit->reference ?? 'N/A' }}</td>
                    <td>{{ $produit->name ?? 'Produit inconnu' }}</td>
                    <td>{{ $produit->pivot->quantite ?? 0 }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">Aucun produit associé à cette sortie.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals-section">
        @php
            $totalQuantite = $sortie->produits->sum('pivot.quantite');
        @endphp
        <p><strong>Quantité totale :</strong> {{ $totalQuantite }}</p>
    </div>

    <div class="signature">
        <div>Établi par</div>
        <div>Approuvé par</div>
        <div><strong>Responsable :</strong> {{ $responsablestock }}</div>
    </div>

    <div class="footer">
        <p>Merci de vérifier les articles au moment de la sortie.</p>
    </div>
</body>
</html>