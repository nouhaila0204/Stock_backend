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
    <p>Bon de sortie N° : <strong>{{ $sortie->id ?? 'N/A' }}</strong></p>
    <p>Date : <strong>{{ \Carbon\Carbon::parse($sortie->date)->format('d/m/Y') ?? 'N/A' }}</strong></p>
  </div>

  <h1>BON DE SORTIE</h1>

  <table>
    <thead>
      <tr>
        <th>Nom du Produit</th>
        <th>Quantité</th>
        <th>Destination</th>
        <th>Employé</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>{{ $sortie->produit->name ?? 'Produit inconnu' }}</td>
        <td>{{ $sortie->quantite }}</td>
        <td>{{ $sortie->destination ?? 'inconnu' }}</td>
        <td>{{ $sortie->employe->nom ?? '..........................' }}</td>
      </tr>
    </tbody>
  </table>

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
