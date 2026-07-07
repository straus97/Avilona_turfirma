@extends('cabinet.layouts.app')

@section('title', 'Роли и права')

@section('sidebar')
    @include('cabinet.components.sidebar.admin')
@endsection

@section('content')
<div class="page-header">
    <h1 class="page-title">Роли и права</h1>
    <p class="page-subtitle">Список ролей и количество пользователей</p>
</div>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom">Роли</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Роль</th>
                    <th>Описание</th>
                    <th>Пользователей</th>
                </tr>
            </thead>
            <tbody>
                @foreach($roles as $role)
                    <tr>
                        <td>
                            @if($role->name === 'admin')
                                <span class="badge bg-danger">Администратор</span>
                            @elseif($role->name === 'manager')
                                <span class="badge bg-primary">Менеджер</span>
                            @else
                                <span class="badge bg-secondary">Турист</span>
                            @endif
                        </td>
                        <td>
                            @if($role->name === 'admin')
                                Полный доступ к системе
                            @elseif($role->name === 'manager')
                                Работа с заявками и клиентами
                            @else
                                Доступ к личному кабинету
                            @endif
                        </td>
                        <td><strong>{{ $role->users_count }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
