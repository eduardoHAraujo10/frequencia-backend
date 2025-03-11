<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Relatório de Registros</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .periodo {
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
        }
        .footer {
            text-align: center;
            font-size: 10px;
            margin-top: 20px;
            position: fixed;
            bottom: 0;
            width: 100%;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Relatório de Registros</h2>
    </div>

    <div class="periodo">
        <strong>Período:</strong> {{ $periodo['inicio'] }} a {{ $periodo['fim'] }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Hora</th>
                <th>Nome</th>
                <th>Matrícula</th>
                <th>Tipo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($registros as $registro)
                <tr>
                    <td>{{ $registro['data'] }}</td>
                    <td>{{ $registro['hora'] }}</td>
                    <td>{{ $registro['nome'] }}</td>
                    <td>{{ $registro['matricula'] }}</td>
                    <td>{{ $registro['tipo'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Gerado em {{ now()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>
