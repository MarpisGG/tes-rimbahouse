@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            <h2>tasks</h2>
        </div>
        <div class="pull-right">
            @can('task-create')
            <a class="btn btn-success btn-sm mb-2" href="{{ route('tasks.create') }}"><i class="fa fa-plus"></i> Create New task</a>
            @endcan
        </div>
    </div>
</div>

@session('success')
    <div class="alert alert-success" role="alert"> 
        {{ $value }}
    </div>
@endsession

<table class="table table-bordered">
    <tr>
        <th>No</th>
        <th>Name</th>
        <th>Details</th>
        <th>Status</th>
        <th>Due Date</th>
        @can('task-create')
            <th>Assigned To</th>         
        @endcan
        <th width="280px">Action</th>
    </tr>
    @foreach ($tasks as $task)
    <tr>
        <td>{{ ++$i }}</td>
        <td>{{ $task->name }}</td>
        <td>{{ $task->detail }}</td>
        <td>{{ $task->status}}</td>
        <td>{{ $task->due_date }}</td>
        @can('task-create')    
            <td>{{ $task->assigned_to }}</td>
        @endcan
        <td>
            <form action="{{ route('tasks.destroy',$task->id) }}" method="POST">
                <a class="btn btn-info btn-sm" href="{{ route('tasks.show',$task->id) }}"><i class="fa-solid fa-list"></i> Show</a>
                @can('task-edit')
                <a class="btn btn-primary btn-sm" href="{{ route('tasks.edit',$task->id) }}"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                @endcan

                @csrf
                @method('DELETE')

                @can('task-delete')
                <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i> Delete</button>
                @endcan
            </form>
        </td>
    </tr>
    @endforeach
</table>

{!! $tasks->links() !!}
@endsection