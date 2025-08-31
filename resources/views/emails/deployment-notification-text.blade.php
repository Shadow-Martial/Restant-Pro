{{ $data['message'] }}

Environment: {{ ucfirst($data['environment']) }}
Time: {{ \Carbon\Carbon::parse($data['timestamp'])->format('Y-m-d H:i:s T') }}

@if(isset($data['branch']))
Branch: {{ $data['branch'] }}
@endif

@if(isset($data['commit']))
Commit: {{ $data['commit'] }}
@endif

@if(isset($data['url']))
Application URL: {{ $data['url'] }}
@endif

@if(isset($data['previous_commit']))
Previous Commit: {{ $data['previous_commit'] }}
@endif

@if(isset($data['error']))

ERROR DETAILS:
{{ $data['error'] }}
@endif

@if(isset($data['reason']))

ROLLBACK REASON:
{{ $data['reason'] }}
@endif

@if(isset($data['details']) && !empty($data['details']))

ADDITIONAL DETAILS:
@foreach($data['details'] as $key => $value)
{{ ucfirst(str_replace('_', ' ', $key)) }}: {{ is_array($value) ? json_encode($value) : $value }}
@endforeach
@endif

---
This is an automated notification from the deployment system.
Generated at {{ now()->format('Y-m-d H:i:s T') }}