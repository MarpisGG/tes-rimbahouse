@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Activity Logs</h1>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>User</th>
                <th>Action</th>
                <th>Description</th>
                <th>Logged At</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($logs as $log)
            <tr>
                <td>{{ $log->user->name ?? 'Unknown' }}</td>
                <td>{{ $log->action }}</td>
                <td>{{ $log->description }}</td>
                <td>{{ $log->logged_at }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{ $logs->links() }}
</div>
@endsection
