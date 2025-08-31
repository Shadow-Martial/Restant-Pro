<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Deployment Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .status-success {
            border-left: 4px solid #28a745;
        }
        .status-failure {
            border-left: 4px solid #dc3545;
        }
        .status-rollback {
            border-left: 4px solid #ffc107;
        }
        .status-started {
            border-left: 4px solid #007bff;
        }
        .details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .details table {
            width: 100%;
            border-collapse: collapse;
        }
        .details td {
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .details td:first-child {
            font-weight: bold;
            width: 30%;
        }
        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="header status-{{ str_replace('deployment_', '', $data['type']) }}">
        <h2>{{ $data['message'] }}</h2>
        <p><strong>Environment:</strong> {{ ucfirst($data['environment']) }}</p>
        <p><strong>Time:</strong> {{ \Carbon\Carbon::parse($data['timestamp'])->format('Y-m-d H:i:s T') }}</p>
    </div>

    <div class="details">
        <table>
            @if(isset($data['branch']))
            <tr>
                <td>Branch:</td>
                <td>{{ $data['branch'] }}</td>
            </tr>
            @endif
            
            @if(isset($data['commit']))
            <tr>
                <td>Commit:</td>
                <td>{{ $data['commit'] }}</td>
            </tr>
            @endif
            
            @if(isset($data['url']))
            <tr>
                <td>Application URL:</td>
                <td><a href="{{ $data['url'] }}">{{ $data['url'] }}</a></td>
            </tr>
            @endif
            
            @if(isset($data['previous_commit']))
            <tr>
                <td>Previous Commit:</td>
                <td>{{ $data['previous_commit'] }}</td>
            </tr>
            @endif
        </table>
    </div>

    @if(isset($data['error']))
    <div class="error-box">
        <h4>Error Details:</h4>
        <pre>{{ $data['error'] }}</pre>
    </div>
    @endif

    @if(isset($data['reason']))
    <div class="details">
        <h4>Rollback Reason:</h4>
        <p>{{ $data['reason'] }}</p>
    </div>
    @endif

    @if(isset($data['details']) && !empty($data['details']))
    <div class="details">
        <h4>Additional Details:</h4>
        <table>
            @foreach($data['details'] as $key => $value)
            <tr>
                <td>{{ ucfirst(str_replace('_', ' ', $key)) }}:</td>
                <td>{{ is_array($value) ? json_encode($value) : $value }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    <div class="footer">
        <p>This is an automated notification from the deployment system.</p>
        <p>Generated at {{ now()->format('Y-m-d H:i:s T') }}</p>
    </div>
</body>
</html>