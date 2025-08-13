<!DOCTYPE html> 
<html>
     <head> 
        <meta charset="utf-8"> 
        <title>Rapport du stock - {{ $annee }}</title> 
        <style> table { border-collapse: collapse; width: 100%; }
         th, td { border: 1px solid #999; padding: 6px; text-align: left; }
          th { background-color: #f0f0f0; } </style> 
          </head> 
          <body> 
            <h2 style="text-align:center;">Rapport du stock</h2>
            <p>Date : 31/12/{{ $annee }}</p> 
            <table> 
                <thead> 
                    <tr> 
                        <th>CODE</th>
                        <th>DESIGNATION</th>
                        <th>STK-DEB</th>
                        <th>ENTREES</th>
                        <th>SORTIES</th>
                        <th>STK-FIN</th> 
                        <th>PRIX-UNIT</th> 
                        <th>VALEUR-STK</th> 
                    </tr> 
                </thead> 
                <tbody> 
                    @foreach ($donnees as $item) 
                        <tr> 
                            <td>{{ $item['code'] }}</td> 
                            <td>{{ $item['designation'] }}</td> 
                            <td>{{ $item['stock_initial'] }}</td> 
                            <td>{{ $item['entrees'] }}</td> 
                            <td>{{ $item['sorties'] }}</td> 
                            <td>{{ $item['stock_final'] }}</td> 
                            <td>{{ number_format($item['prix_unitaire'], 2) }}</td> 
                            <td>{{ number_format($item['valeur_stock'], 2) }}</td> 
                        </tr> 
                    @endforeach 
                </tbody> 
                <tfoot> 
                    <tr> 
                        <th colspan="7">TOTAL</th> 
                        <th>{{ number_format(collect($donnees)->sum('valeur_stock'), 2) }}</th> 
                    </tr> 
                </tfoot> 
            </table> 
        </body> 
    </html>