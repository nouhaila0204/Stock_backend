<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Bon d'entrée</title>
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
    <h1>BON D'ENTRÉE N° : <strong>{{ $entree->numBond ?? 'N/A' }}</strong></h1>
  </div>

  <div class="info-section">
    <br>
    <p><strong>Numéro de bon :</strong> {{ $entree->numBond ?? 'N/A' }}</p>
    <p><strong>Code Marché :</strong> {{ $entree->codeMarche ?? 'N/A' }}</p>
    <p><strong>Date :</strong> {{ \Carbon\Carbon::parse($entree->date)->format('d/m/Y') ?? 'N/A' }}</p>
    <p><strong>Fournisseur :</strong> {{ $entree->fournisseur->raisonSocial ?? 'N/A' }}</p>
  </div>
  
  <br>
  <br>
  <h2>Produits reçus</h2>
  <table>
    <thead>
      <tr>
        <th>Référence</th>
        <th>Produit</th>
        <th>Quantité</th>
        <th>Prix unitaire (MAD)</th>
        <th>TVA (%)</th>
        <th>Total (MAD)</th>
        <th>Total TTC (MAD)</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($entree->produits as $produit)
        <tr>
          <td>{{ $produit->reference ?? 'N/A' }}</td>
          <td>{{ $produit->name ?? 'Produit inconnu' }}</td>
          <td>{{ $produit->pivot->quantite ?? 0 }}</td>
          <td>{{ number_format($produit->pivot->prixUnitaire, 0, '.', ' ') }} DH</td>
          <td>{{ number_format($produit->tva->taux ?? 0, 2, '.', '') }}%</td>
          <td>{{ number_format($produit->pivot->quantite * $produit->pivot->prixUnitaire, 0, '.', ' ') }} DH</td>
          <td>{{ number_format($produit->pivot->quantite * $produit->pivot->prixUnitaire * (1 + ($produit->tva->taux ?? 0) / 100), 0, '.', ' ') }} DH</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <!-- Section des totaux -->
  <div class="totals-section">
    @php
      $totalQuantite = $entree->produits->sum('pivot.quantite');
      $totalHT = $entree->produits->sum(function ($produit) {
          return $produit->pivot->quantite * $produit->pivot->prixUnitaire;
      });
      $totalTTC = $entree->produits->sum(function ($produit) {
          return $produit->pivot->quantite * $produit->pivot->prixUnitaire * (1 + ($produit->tva->taux ?? 0) / 100);
      });
    @endphp
    <p><strong>Quantité totale :</strong> {{ $totalQuantite }}</p>
    <p><strong>Total HT :</strong> {{ number_format($totalHT, 0, '.', ' ') }} DH</p>
    <p><strong>Total TTC :</strong> {{ number_format($totalTTC, 0, '.', ' ') }} DH</p>
  </div>

  <div class="signature">
    <div>Établi par</div>
    <div>Contrôlé par</div>
    <div><strong>Responsable :</strong> {{ $responsablestock }}</div>
  </div>

  <div class="footer">
    <p>Merci de vérifier les articles à la réception.</p>
  </div>

</body>
</html>