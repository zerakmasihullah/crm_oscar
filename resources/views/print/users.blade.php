@extends('print.layout')

@section('title', __('Users'))

@section('meta')
    {{ now()->timezone(config('app.timezone'))->toDayDateTimeString() }}
    · {{ $users->count() }} {{ __('records') }}
@endsection

@section('content')
    <table>
        <thead>
            <tr>
                <th>{{ __('ID') }}</th>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Email') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Roles') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->is_active ? __('Active') : __('Disabled') }}</td>
                    <td>{{ $user->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td class="empty" colspan="5">{{ __('No users found') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
