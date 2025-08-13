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

    .header, .footer {
      text-align: center;
      font-weight: bold;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
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
    <p>Bon d'entrée N° : <strong>{{ $entree->numBond ?? 'N/A' }}</strong></p>
    <p>Date : <strong>{{ \Carbon\Carbon::parse($entree->date)->format('d/m/Y') ?? 'N/A' }}</strong></p>
  </div>

  <h1>BON D'ENTRÉE</h1>

  <table>
    <thead>
      <tr>
        <th>Code Marché</th>
        <th>Produit</th>
        <th>Numéro_Fournisseur</th>
        <th>Quantité</th>
        <th>Prix Unitaire (MAD)</th>
        <th>Valeur Totale (MAD)</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>{{ $entree->codeMarche  }}</td>
        <td>{{ $entree->produit->name ?? 'Produit inconnu' }}</td>
        <td>{{ $entree->fournisseur_id }}</td>
        <td>{{ $entree->quantite }}</td>
        <td>{{ number_format($entree->prixUnitaire, 2, '.', '') }}</td>
        <td>{{ number_format($entree->quantite * $entree->prixUnitaire, 2, '.', '') }}</td>
      </tr>
    </tbody>
  </table>

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
