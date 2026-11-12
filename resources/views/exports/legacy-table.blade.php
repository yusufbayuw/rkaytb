<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4 {{ $orientation }}; margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; background: white; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 4px; overflow-wrap: anywhere; text-align: left; }
        thead { display: table-header-group; } tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <table><thead><tr>@foreach ($dataset['headers'] as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>@foreach ($dataset['rows'] as $row)<tr>@foreach ($row as $value)<td>{{ $value }}</td>@endforeach</tr>@endforeach</tbody>
    </table>
</body>
</html>
