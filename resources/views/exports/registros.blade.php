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
            color: #333;
        }
        .header {
            margin-bottom: 30px;
        }
        h1 {
            color: #1a237e;
            font-size: 24px;
            margin-bottom: 20px;
        }
        h2 {
            color: #1a237e;
            font-size: 18px;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        .periodo {
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            text-align: right;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #dee2e6;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .status-ativo {
            background-color: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
        }
        .registros-table {
            margin-top: 15px;
        }
        .footer {
            text-align: center;
            font-size: 10px;
            margin-top: 30px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Detalhamento por Aluno</h1>
    </div>

    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Matrícula</th>
                <th>Dias Presentes</th>
                <th>% Presença</th>
                <th>Primeiro Registro</th>
                <th>Último Registro</th>
                <th>Horário Último Registro</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($alunos as $aluno)
            <tr>
                <td>{{ $aluno['nome'] }}</td>
                <td>{{ $aluno['matricula'] }}</td>
                <td>{{ $aluno['dias_presenca'] }}</td>
                <td>{{ number_format($aluno['porcentagem_presenca'], 2) }}%</td>
                <td>{{ $aluno['primeiro_registro'] }}</td>
                <td>{{ $aluno['ultimo_registro'] }}</td>
                <td>{{ $aluno['horario_ultimo_registro'] }}</td>
                <td>
                    @if($aluno['ativo'])
                    <span class="status-ativo">Ativo</span>
                    @else
                    Inativo
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Registros do Período</h2>
    <div class="periodo">
        Período: {{ $periodo['inicio'] }} a {{ $periodo['fim'] }}
    </div>

    <table class="registros-table">
        <thead>
            <tr>
                <th>Data</th>
                <th>Nome do Aluno</th>
                <th>Entrada</th>
                <th>Saída</th>
                <th>Total de Horas</th>
            </tr>
        </thead>
        <tbody>
            @foreach($registros_diarios as $registro)
            <tr>
                <td>{{ $registro['data'] }}</td>
                <td>{{ $registro['nome_aluno'] }}</td>
                <td>{{ $registro['entrada'] }}</td>
                <td>{{ $registro['saida'] }}</td>
                <td>{{ $registro['total_horas'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Gerado em {{ now()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}
        @if(isset($user))
        <br>
        Registro realizado por: {{ $user->nome }}
        @endif
    </div>
</body>
</html>
